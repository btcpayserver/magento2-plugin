<?php

namespace Storefront\BTCPayServer\Observer;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\ScopeInterface;
use stdClass;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Storefront\BTCPayServer\Model\Invoice;
use Storefront\BTCPayServer\Model\Item;
use Magento\Customer\Model\Session as CustomerSession;

class Redirect implements ObserverInterface {

    private $url;
    private $redirect;
    private $response;
    private $orderRepository;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var CustomerSession
     */
    private $customerSession;
    /**
     * @var AdapterInterface
     */
    private $db;

    public function __construct(ScopeConfigInterface $scopeConfig, ResourceConnection $resource, UrlInterface $url, StoreManagerInterface $storeManager, ActionFlag $actionFlag, RedirectInterface $redirect, ResponseInterface $response, OrderRepository $orderRepository, CustomerSession $customerSession) {

        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
        $this->actionFlag = $actionFlag;
        $this->redirect = $redirect;
        $this->response = $response;
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->db = $resource->getConnection();
    }

    public function getStoreConfig($path, $storeId) {
        $r = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        return $r;
    }

    public function getOrder($orderId) {
        // TODO remove use of ObjectManager
        // TODO is this the order ID or the increment id ?
        $objectManager = ObjectManager::getInstance();
        $order = $objectManager->create(OrderInterface::class)->load($orderId);
        return $order;

    }

    public function getBaseUrl() {
        return $this->storeManager->getStore()->getBaseUrl();

    }

    public function execute(Observer $observer) {
        $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);

        $order_ids = $observer->getEvent()->getOrderIds();
        $order_id = $order_ids[0];
        $order = $this->getOrder($order_id);
        $order_id_long = $order->getIncrementId();

        if ($order->getPayment()->getMethodInstance()->getCode() === 'btcpayserver') {
            // Force status
            $order->setState('new', true);
            $order->setStatus('pending', true);

            $order->save();

            $token = $this->getStoreConfig('payment/btcpayserver/token', $order->getStoreId());
            $host = $this->getStoreConfig('payment/btcpayserver/host', $order->getStoreId());


            //create an item, should be passed as an object'
            $params = new stdClass();
            //$params->extension_version = $this->getExtensionVersion();
            $params->price = $order['base_grand_total'];
            $params->currency = $order['base_currency_code']; //set as needed


            $buyerInfo = new stdClass();
            if ($this->customerSession->isLoggedIn()) {
                $buyerInfo->name = $this->customerSession->getCustomer()->getName();
                $buyerInfo->email = $this->customerSession->getCustomer()->getEmail();

            } else {
                $buyerInfo->name = $order->getBillingAddress()->getFirstName() . ' ' . $order->getBillingAddress()->getLastName();
                $buyerInfo->email = $order->getCustomerEmail();
            }
            $params->buyer = $buyerInfo;

            $params->orderId = trim($order_id_long);

            if ($this->customerSession->isLoggedIn()) {
                $params->redirectURL = $this->url->getUrl() . 'sales/order/view/', ['order_id' => $order_id]);

            } else {
                // Send the guest back to the order/returns page to lookup
                $params->redirectURL = $this->url->getUrl('sales/guest/form');

                // TODO set cookies the Magento way
                $duration = 30 * 24 * 60 * 60;
                setcookie('oar_order_id', $order_id_long, time() + $duration, '/');
                setcookie('oar_billing_lastname', $order->getBillingAddress()->getLastName(), time() + $duration, '/');
                setcookie('oar_email', $order->getCustomerEmail(), time() + $duration, '/');
            }

            // TODO build URL to the REST API the Magento way
            $params->notificationURL = $this->getBaseUrl() . 'rest/V1/btcpayserver/ipn';
            $params->extendedNotifications = true;
            $params->acceptanceWindow = 1200000;


            $params->cartFix = $this->url->getUrl('btcpayserver/cart/restore', ['order_id' => $order_id]);
            $item = new Item($token, $host, $params);
            $invoice = new Invoice($item);

            // this creates the invoice with all of the config params from the item
            $invoice->createInvoice();
            $invoiceData = json_decode($invoice->getInvoiceData(), true);

            // now we have to append the invoice transaction id for the callback verification
            $invoiceID = $invoiceData['data']['id'] ?? false;

            $table_name = $this->db->getTableName('btcpayserver_transactions');
            $this->db->insert($table_name, ['order_id' => $order_id_long, 'transaction_id' => $invoiceID, 'transaction_status' => 'new']);

            $this->redirect->redirect($this->response, $invoice->getInvoiceURL());
        }
    }

}
