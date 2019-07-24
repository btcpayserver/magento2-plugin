<?php

namespace Storefront\BTCPay\Observer;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\ScopeInterface;
use stdClass;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Storefront\BTCPay\Model\Invoice;
use Storefront\BTCPay\Model\Item;
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
    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;
    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;
    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    public function __construct(ScopeConfigInterface $scopeConfig, ResourceConnection $resource, UrlInterface $url, StoreManagerInterface $storeManager, RedirectInterface $redirect, ResponseInterface $response, OrderRepository $orderRepository, CustomerSession $customerSession, CookieManagerInterface $cookieManager, CookieMetadataFactory $cookieMetadataFactory, SessionManagerInterface $sessionManager) {

        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
        $this->redirect = $redirect;
        $this->response = $response;
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->db = $resource->getConnection();
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->cookieManager = $cookieManager;
        $this->sessionManager = $sessionManager;
    }

    public function getStoreConfig($path, $storeId) {
        $r = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        return $r;
    }

    private function setCookie($name, $value, $duration) {
        $path = $this->sessionManager->getCookiePath();
        $domain = $this->sessionManager->getCookieDomain();

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()->setDuration($duration)->setPath($path)->setDomain($domain);

        $this->cookieManager->setPublicCookie($name, $value, $metadata);
    }


    public function execute(Observer $observer) {
        $orderIds = $observer->getEvent()->getOrderIds();
        $orderId = $orderIds[0];
        $order = $this->orderRepository->get($orderId);
        $orderIncrementId = $order->getIncrementId();

        if ($order->getPayment()->getMethodInstance()->getCode() === \Storefront\BTCPay\Model\BTCPay::PAYMENT_METHOD_CODE) {

            $storeId = $order->getStoreId();

            $token = $this->getStoreConfig('payment/btcpay/token', $storeId);
            $host = $this->getStoreConfig('payment/btcpay/host', $storeId);
            $newStatus = $this->getStoreConfig('payment/btcpay/new_status', $storeId);

            $order->setState('new');
            if($newStatus) {
                $order->setStatus($newStatus);
            }else{
                $order->setStatus('new');
            }

            $order->save();


            //create an item, should be passed as an object'
            $params = new stdClass();
            //$params->extension_version = $this->getExtensionVersion();
            $params->price = $order->getGrandTotal();
            $params->currency = $order->getCurrencyCode();

            $buyerInfo = new stdClass();

            $nameParts = [];
            $billingAddress = $order->getBillingAddress();

            if ($billingAddress->getFirstname()) {
                $nameParts[] = $billingAddress->getFirstname();
            }
            if ($billingAddress->getMiddlename()) {
                $nameParts[] = $billingAddress->getMiddlename();
            }
            if ($billingAddress->getLastname()) {
                $nameParts[] = $billingAddress->getLastname();
            }

            $buyerInfo->name = implode(' ', $nameParts);
            $buyerInfo->email = $order->getCustomerEmail();

            $params->buyer = $buyerInfo;
            $params->orderId = $orderIncrementId;

            if ($this->customerSession->isLoggedIn()) {
                $params->redirectURL = $this->url->getUrl('sales/order/view/', ['order_id' => $orderId]);

            } else {
                // Send the guest back to the order/returns page to lookup
                $params->redirectURL = $this->url->getUrl('sales/guest/form');

                $duration = 30 * 24 * 60 * 60;
                $this->setCookie('oar_order_id', $orderIncrementId, $duration);
                $this->setCookie('oar_billing_lastname', $order->getBillingAddress()->getLastName(),  $duration);
                $this->setCookie('oar_email', $order->getCustomerEmail(), $duration);
            }

            // TODO build URL to the REST API the Magento way
            $params->notificationURL = $this->storeManager->getStore()->getBaseUrl() . 'rest/V1/btcpay/ipn';
            $params->extendedNotifications = true;
            $params->acceptanceWindow = 1200000;


            $params->cartFix = $this->url->getUrl('btcpay/cart/restore', ['order_id' => $orderId]);
            $item = new Item($token, $host, $params);
            $invoice = new Invoice($item);

            // this creates the invoice with all of the config params from the item
            $invoice->createInvoice();
            $invoiceData = json_decode($invoice->getInvoiceData(), true);

            // now we have to append the invoice transaction id for the callback verification
            $invoiceID = $invoiceData['data']['id'] ?? null;

            if (!$invoiceID) {
                $table_name = $this->db->getTableName('btcpay_transactions');
                $this->db->insert($table_name, [
                    'order_id' => $orderId,
                    'transaction_id' => $invoiceID,
                    'transaction_status' => 'new'
                ]);

                $this->redirect->redirect($this->response, $invoice->getInvoiceURL());
            } else {
                throw new \RuntimeException('Could not create the transaction in BTCPay Server');
            }
        }
    }

}
