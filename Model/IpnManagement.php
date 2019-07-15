<?php

namespace Storefront\BTCPayServer\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use stdClass;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;

class IpnManagement {

    private $invoiceService;
    private $transaction;
    private $orderRepository;
    /**
     * @var ModuleListInterface
     */
    private $moduleList;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var ResponseFactory
     */
    private $responseFactory;
    /**
     * @var UrlInterface
     */
    private $url;



    /**
     * IpnManagement constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param ResponseFactory $responseFactory
     * @param UrlInterface $url
     * @param ModuleListInterface $moduleList
     * @param OrderRepository $orderRepository
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     */
    public function __construct(ScopeConfigInterface $scopeConfig, ResponseFactory $responseFactory, UrlInterface $url, ModuleListInterface $moduleList, OrderRepository $orderRepository, InvoiceService $invoiceService, Transaction $transaction) {
        $this->moduleList = $moduleList;

        $this->scopeConfig = $scopeConfig;
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
    }

    public function getStoreConfig($path, $storeId) {
        $_val = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        return $_val;

    }

    public function getOrder($_order_id) {
        // TODO remove use of ObjectManager
        $objectManager = ObjectManager::getInstance();

        // TODO should we load by increment?!? Loading by ID is better as increment is not unique! However, this will be less user friendly for the merchant as he cannot see the order ID in Magento and BTCPay Server

        $order = $objectManager->create(OrderInterface::class)->loadByIncrementId($_order_id);
        return $order;

    }

    public function postIpn() {
        // TODO remove use of ObjectManager
        $objectManager = ObjectManager::getInstance(); // Instance of object manager
        $resource = $objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table_name = $resource->getTableName('btcpayserver_transactions');

        $postedString = file_get_contents('php://input');
        $data = json_decode($postedString, true);

        $btcpayInvoiceId = $data['data']['id'];
        $orderId = $data['data']['orderId'];

        $order = $this->getOrder($orderId);

        // Only use "id" and "orderId" fields from the POSTed data and discard the rest. The posted data can be malicious.
        unset($data);

        if(!$order || !$order->getId()){
            return;
        }

        // TODO instead of just updating the transation, let's record all IPN requests! Also create an Admin grid so we can look at them.

        // TODO remove ugly SQL
        $sql = "SELECT * FROM $table_name WHERE order_id = '$orderId' and transaction_id = '$btcpayInvoiceId' ";
        $result = $connection->query($sql);
        $row = $result->fetch();
        if ($row) {

            // Validate

            $storeId = $order->getStoreId();

            $token = $this->getStoreConfig('payment/btcpayserver/token', $storeId);
            $host = $this->getStoreConfig('payment/btcpayserver/host', $storeId);

            $params = new stdClass();

            $params->invoiceID = $btcpayInvoiceId;
            //$params->extension_version = $this->getExtensionVersion();
            $item = new Item($token, $host, $params);
            $invoice = new Invoice($item);

            $orderStatus = json_decode($invoice->checkInvoiceStatus($btcpayInvoiceId), true);
            $invoice_status = $orderStatus['data']['status'] ?? false;

            // TODO fix SQL injection
            $update_sql = "UPDATE $table_name SET transaction_status = '$invoice_status' WHERE order_id = '$orderid' AND transaction_id = '$btcpayInvoiceId'";

            $update_result = $connection->query($update_sql);



            // TODO fill $event in some other way...
            $event = [];
            switch ($event['name']) {

                case 'invoice_paidInFull':

                    if ($invoice_status === 'paid') {
                        // 1) Payments have been made to the invoice for the requested amount but the transaction has not been confirmed yet
                        $paidNotConfirmedStatus = $this->getStoreConfig('payment/btcpayserver/payment_paid_status', $storeId);

                        $order->addStatusHistoryComment('Payment underway, but not confirmed yet', $paidNotConfirmedStatus);
                        $order->save();
                        return true;
                    }
                    break;

                case 'invoice_confirmed':
                    if ($invoice_status === 'confirmed') {
                        // 2) Paid and confirmed (happens before completed and transitions to it quickly)

                        // TODO maybe add the transation ID in the comment or something like that?

                        $confirmedStatus = $this->getStoreConfig('payment/btcpayserver/payment_confirmed_status', $storeId);
                        $order->addStatusHistoryComment('Payment confirmed, but not completed yet', $confirmedStatus);


                        $order->save();
                        return true;
                    }
                    break;


                case 'invoice_completed':
                    if ($invoice_status === 'complete') {
                        // 3) Paid, confirmed and settled. Final!
                        // TODO maybe add the transation ID in the comment or something like that?

                        $completedStatus = $this->getStoreConfig('payment/btcpayserver/payment_completed_status', $storeId);
                        $order->addStatusHistoryComment('Payment completed', $completedStatus);
                        $invoice = $this->invoiceService->prepareInvoice($order);
                        $invoice->register();

                        // TODO we really need to save the invoice first as we are saving it again in this transaction? Leaving it out for now.
                        //$invoice->save();

                        $transactionSave = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
                        $transactionSave->save();

                        return true;
                    }
                    break;


                case 'invoice_failedToConfirm':
                    if ($invoice_status === 'invalid') {
                        $order->addStatusHistoryComment('Failed to confirm the order. The order will automatically update when the status changes.');
                        $order->save();
                        return true;
                    }
                    break;

                case 'invoice_expired':
                    if ($invoice_status === 'expired') {
                        // Invoice expired - let's do nothing.

                        return true;
                    }
                    break;

                case 'invoice_refundComplete':
                    // Full refund

                    $order->addStatusHistoryComment('Refund received through BTCPay Server.');
                    $order->setState(Order::STATE_CLOSED)->setStatus(Order::STATE_CLOSED);

                    $order->save();

                    return true;
                    break;

                // TODO what about partial refunds, partial payments and overpayment?
            }

        }
    }


}
