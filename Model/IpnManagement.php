<?php

namespace Storefront\BTCPayServer\Model;

use Storefront\BTCPayServer\Api\IpnManagementInterface;
use Storefront\BTCPayServer\BitPayLib\_Invoice;
use Storefront\BTCPayServer\BitPayLib\_Item;
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

    protected $_invoiceService;
    protected $_transaction;
    public $orderRepository;

    public function __construct(ScopeConfigInterface $scopeConfig, ResponseFactory $responseFactory, UrlInterface $url, ModuleListInterface $moduleList, OrderRepository $orderRepository, InvoiceService $invoiceService, Transaction $transaction) {
        $this->_moduleList = $moduleList;

        $this->_scopeConfig = $scopeConfig;
        $this->_responseFactory = $responseFactory;
        $this->_url = $url;
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
        $all_data = json_decode($postedString, true);
        $data = $all_data['data'];
        $event = $all_data['event'];

        $orderid = $data['orderId'];
        $order_status = $data['status'];
        $order_invoice = $data['id'];

        // TODO instead of just updating the transation, let's record all IPN requests! Also create an Admin grid so we can look at them.

        // TODO remove ugly SQL
        $sql = "SELECT * FROM $table_name WHERE order_id = '$orderid' AND transaction_id = '$order_invoice' ";
        $result = $connection->query($sql);
        $row = $result->fetch();
        if ($row) {

            // Validate
            $token = $this->getStoreConfig('payment/btcpayserver/token');
            $host = $this->getStoreConfig('payment/btcpayserver/host');


            $params = new stdClass();

            $params->invoiceID = $order_invoice;
            //$params->extension_version = $this->getExtensionVersion();
            $item = new Item($token, $host, $params);
            $invoice = new Invoice($item);

            $orderStatus = json_decode($invoice->checkInvoiceStatus($order_invoice), true);
            $invoice_status = $orderStatus['data']['status'] ?? false;

            // TODO fix SQL injection
            $update_sql = "UPDATE $table_name SET transaction_status = '$invoice_status' WHERE order_id = '$orderid' AND transaction_id = '$order_invoice'";

            $update_result = $connection->query($update_sql);

            $order = $this->getOrder($orderid);
            // now update the order
            switch ($event['name']) {

                case 'invoice_paidInFull':

                    if ($invoice_status === 'paid') {
                        // 1) Payments have been made to the invoice for the requested amount but the transaction has not been confirmed yet
                        $paidNotConfirmedStatus = $this->getStoreConfig('payment/btcpayserver/payment_paid_status');

                        $order->addStatusHistoryComment('Payment underway, but not confirmed yet', $paidNotConfirmedStatus);
                        $order->save();
                        return true;
                    }
                    break;

                case 'invoice_confirmed':
                    if ($invoice_status === 'confirmed') {
                        // 2) Paid and confirmed (happens before completed and transitions to it quickly)

                        // TODO maybe add the transation ID in the comment or something like that?

                        $confirmedStatus = $this->getStoreConfig('payment/btcpayserver/payment_confirmed_status');
                        $order->addStatusHistoryComment('Payment confirmed, but not completed yet', $confirmedStatus);


                        $order->save();
                        return true;
                    }
                    break;


                case 'invoice_completed':
                    if ($invoice_status === 'complete') {
                        // 3) Paid, confirmed and settled. Final!
                        // TODO maybe add the transation ID in the comment or something like that?

                        $completedStatus = $this->getStoreConfig('payment/btcpayserver/payment_completed_status');
                        $order->addStatusHistoryComment('Payment completed', $completedStatus);
                        $invoice = $this->_invoiceService->prepareInvoice($order);
                        $invoice->register();

                        // TODO we really need to save the invoice first as we are saving it again in this transaction? Leaving it out for now.
                        //$invoice->save();

                        $transactionSave = $this->_transaction->addObject($invoice)->addObject($invoice->getOrder());
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
