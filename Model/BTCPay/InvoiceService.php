<?php

namespace Storefront\BTCPay\Model\BTCPay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use stdClass;

class InvoiceService {

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var AdapterInterface
     */
    private $db;

    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    private $httpClientFactory;


    public function __construct(ResourceConnection $resource, StoreManagerInterface $storeManager, UrlInterface $url, \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory, ScopeConfigInterface $scopeConfig) {
        $this->httpClientFactory = $httpClientFactory;
        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
        $this->storeManager = $storeManager;
        $this->db = $resource->getConnection();
    }

    public function checkInvoiceStatus($invoiceId, $storeId) {
        // TODO replace with Zend HTTP Client
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getInvoicesEndpoint($storeId) . '/' . $invoiceId);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function createInvoice(\Magento\Sales\Model\Order $order) {
        $storeId = $order->getStoreId();
        $orderIncrementId = $order->getIncrementId();
        $orderId = $order->getId();

        $token = $this->getToken($storeId);
        $newStatus = $this->getStoreConfig('payment/btcpay/new_status', $storeId);

        $order->setState('new');
        if ($newStatus) {
            $order->setStatus($newStatus);
        } else {
            $order->setStatus('new'); // TODO can we avoid hard coded status here?
        }

        $order->save();

        //create an item, should be passed as an object'
        $params = [];
        //$params->extension_version = $this->getExtensionVersion();
        $params['price'] = $order->getGrandTotal();
        $params['currency'] = $order->getOrderCurrencyCode();

        $buyerInfo = [];

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

        $buyerInfo['name'] = implode(' ', $nameParts);
        $buyerInfo['email'] = $order->getCustomerEmail();

        $params['buyer'] = $buyerInfo;
        $params['orderId'] = $orderIncrementId;

        if ($order->getCustomerId()) {
            // Customer is logged in...
            $params['redirectURL'] = $this->url->getUrl('sales/order/view/', ['order_id' => $orderId]);

        } else {
            // Send the guest back to the order/returns page to lookup
            $params['redirectURL'] = $this->url->getUrl('sales/guest/form');
        }

        // TODO build the URL to the REST API in a more Magento way?
        $params['notificationURL'] = $this->storeManager->getStore()->getBaseUrl() . 'rest/V1/btcpay/ipn';
        $params['extendedNotifications'] = true;
        $params['acceptanceWindow'] = 1200000;


        $params['cartFix'] = $this->url->getUrl('btcpay/cart/restore', ['order_id' => $orderId]);
        $params['token'] = $token;

        $postData = json_encode($params);

//        $request_headers = [];
//        $request_headers[] = 'Content-Type: application/json';

        $url = $this->getInvoicesEndpoint($storeId);


        $client = $this->httpClientFactory->create();
        $client->setUri($url);
        $client->setMethod('POST');
        $client->setHeaders('Content-Type', 'application/json');
        $client->setHeaders('Accept', 'application/json');
        $client->setRawData($postData);
        $response = $client->request();


//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        $result = curl_exec($ch);
//        curl_close($ch);

        $status = $response->getStatus();
        $body = $response->getBody();

        if ($status === 200) {
            $data = json_decode($body, true);
            $invoice = new Invoice($data);

            $table_name = $this->db->getTableName('btcpay_transactions');
            $this->db->insert($table_name, [
                'order_id' => $orderId,
                'transaction_id' => $invoice->getInvoiceId(),
                'transaction_status' => 'new'
            ]);

            return $invoice;
        } else {
            // TODO improve error message
            throw new \RuntimeException('Cannot create new invoice in BTCPay server for order ID ' . $order->getId());
        }
    }

//    public function getInvoiceURL() {
//        $data = json_decode($this->invoiceData, true);
//        return $data['data']['url'] ?? false;
//    }

    public function updateBuyersEmail($invoice_result, $buyers_email) {
        $invoice_result = json_decode($invoice_result, false);

        $token = $this->getToken();

        $update_fields = new stdClass();
        $update_fields->token = $token;
        $update_fields->buyerProvidedEmail = $buyers_email;
        $update_fields->invoiceId = $invoice_result->data->id;
        $update_fields = json_encode($update_fields);
// TODO replace with Zend HTTP Client
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://' . $this->item->getBuyerTransactionEndpoint());
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $update_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function updateBuyerCurrency($invoice_result, $buyer_currency) {
        $invoice_result = json_decode($invoice_result);

        $update_fields = new stdClass();
        $update_fields->token = $this->item->item_params->token;
        $update_fields->buyerSelectedTransactionCurrency = $buyer_currency;
        $update_fields->invoiceId = $invoice_result->data->id;
        $update_fields = json_encode($update_fields);
// TODO replace with Zend HTTP Client
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://' . $this->item->getBuyerTransactionEndpoint());
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $update_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    /**
     * @return string
     */
    public function getBuyerTransactionEndpoint() {
        return $this->host . '/invoiceData/setBuyerSelectedTransactionCurrency';
    }

    public function getStoreConfig($path, $storeId) {
        $r = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        return $r;
    }

    private function getToken($storeId) {
        $r = $this->getStoreConfig('payment/btcpay/token', $storeId);
        return $r;
    }

    private function getHost($storeId) {
        $r = $this->getStoreConfig('payment/btcpay/host', $storeId);
        return $r;
    }

    private function getInvoicesEndpoint(int $storeId) {
        $host = $this->getHost($storeId);
        $r = 'https://' . $host . '/invoices';
        return $r;
    }

}
