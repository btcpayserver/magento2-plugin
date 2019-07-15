<?php

namespace Storefront\BTCPayServer\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use stdClass;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Storefront\BTCPayServer\Model\Invoice;
use Storefront\BTCPayServer\Model\Item;


class Redirect implements ObserverInterface {
    protected $checkoutSession;
    protected $resultRedirect;
    protected $url;
    protected $coreRegistry;
    protected $_redirect;
    protected $_response;
    public $orderRepository;
    protected $_invoiceService;
    protected $_transaction;

    public function __construct(ScopeConfigInterface $scopeConfig, ResponseFactory $responseFactory, UrlInterface $url, ModuleListInterface $moduleList, Session $checkoutSession, ResultFactory $result, Registry $registry, ActionFlag $actionFlag, RedirectInterface $redirect, ResponseInterface $response, OrderRepository $orderRepository, InvoiceService $invoiceService, Transaction $transaction) {
        $this->coreRegistry = $registry;
        $this->_moduleList = $moduleList;
        $this->_scopeConfig = $scopeConfig;
        $this->_responseFactory = $responseFactory;
        $this->_url = $url;
        $this->checkoutSession = $checkoutSession;
        $this->resultRedirect = $result;
        $this->_actionFlag = $actionFlag;
        $this->_redirect = $redirect;
        $this->_response = $response;
        $this->orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
    }

    public function getStoreConfig($path) {
        $_val = $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
        return $_val;

    }

    public function getOrder($_order_id) {
        // TODO remove use of ObjectManager
        $objectManager = ObjectManager::getInstance();
        $order = $objectManager->create(OrderInterface::class)->load($_order_id);
        return $order;

    }

    public function getBaseUrl() {
        // TODO remove use of ObjectManager
        $objectManager = ObjectManager::getInstance();
        $storeManager = $objectManager->get(StoreManagerInterface::class);
        return $storeManager->getStore()->getBaseUrl();

    }

    public function execute(Observer $observer) {
        $this->_actionFlag->set('', Action::FLAG_NO_DISPATCH, true);

        // TODO remove objectmanager
        $objectManager = ObjectManager::getInstance();

        $order_ids = $observer->getEvent()->getOrderIds();
        $order_id = $order_ids[0];
        $order = $this->getOrder($order_id);
        $order_id_long = $order->getIncrementId();

        if ($order->getPayment()->getMethodInstance()->getCode() === 'btcpayserver') {
            // Force status
            $order->setState('new', true);
            $order->setStatus('pending', true);

            $order->save();

            $token = $this->getStoreConfig('payment/btcpayserver/token');
            $host = $this->getStoreConfig('payment/btcpayserver/host');


            //create an item, should be passed as an object'
            $params = new stdClass();
            //$params->extension_version = $this->getExtensionVersion();
            $params->price = $order['base_grand_total'];
            $params->currency = $order['base_currency_code']; //set as needed


            $customerSession = $objectManager->create(\Magento\Customer\Model\Session::class);

            $buyerInfo = new stdClass();
            if ($customerSession->isLoggedIn()) {
                $buyerInfo->name = $customerSession->getCustomer()->getName();
                $buyerInfo->email = $customerSession->getCustomer()->getEmail();

            } else {
                $buyerInfo->name = $order->getBillingAddress()->getFirstName() . ' ' . $order->getBillingAddress()->getLastName();
                $buyerInfo->email = $order->getCustomerEmail();
            }
            $params->buyer = $buyerInfo;

            $params->orderId = trim($order_id_long);

            if ($customerSession->isLoggedIn()) {
                // TODO build URL the Magento way
                $params->redirectURL = $this->getBaseUrl() . 'sales/order/view/order_id/' . $order_id . '/';

            } else {
                // Send the guest back to the order/returns page to lookup
                $params->redirectURL = $this->getBaseUrl() . 'sales/guest/form';

                // TODO set cookies the Magento way
                $duration = 30 * 24 * 60 * 60;
                setcookie('oar_order_id', $order_id_long, time() + $duration, '/');
                setcookie('oar_billing_lastname', $order->getBillingAddress()->getLastName(), time() + $duration, '/');
                setcookie('oar_email', $order->getCustomerEmail(), time() + $duration, '/');
            }

            // TODO build URL the Magento way
            $params->notificationURL = $this->getBaseUrl() . 'rest/V1/btcpayserver/ipn';
            $params->extendedNotifications = true;
            $params->acceptanceWindow = 1200000;

            // TODO build URL the Magento way
            $params->cartFix = $this->getBaseUrl() . 'btcpayserver/cart/restore?order_id=' . $order_id;
            $item = new Item($token, $host, $params);
            $invoice = new Invoice($item);

            // this creates the invoice with all of the config params from the item
            $invoice->createInvoice();
            $invoiceData = json_decode($invoice->getInvoiceData(), true);

            // now we have to append the invoice transaction id for the callback verification
            $invoiceID = $invoiceData['data']['id'] ?? false;

            // TODO insert into the database the Magento way
            $resource = $objectManager->get(ResourceConnection::class);
            $connection = $resource->getConnection();
            $table_name = $resource->getTableName('btcpayserver_transactions');

            // TODO unsafe due to SQL injection
            $sql = "INSERT INTO $table_name (order_id,transaction_id,transaction_status) VALUES ('" . $order_id_long . "','" . $invoiceID . "','new')";

            $connection->query($sql);

            $this->_redirect->redirect($this->_response, $invoice->getInvoiceURL());
        }
    }

}
