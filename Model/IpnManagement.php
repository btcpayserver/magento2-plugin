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

class IpnManagement{

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
        #json ipn
        $all_data = json_decode(file_get_contents('php://input'), true);
        $data = $all_data['data'];
        $event = $all_data['event'];

        $orderid = $data['orderId'];
        $order_status = $data['status'];
        $order_invoice = $data['id'];

        #is it in the lookup table
        // TODO remove ugly SQL
        $sql = "SELECT * FROM $table_name WHERE order_id = '$orderid' AND transaction_id = '$order_invoice' ";
        $result = $connection->query($sql);
        $row = $result->fetch();
        if ($row):

            #verify the ipn
            $token = $this->getStoreConfig('payment/btcpayserver/token');
            $host = $this->getStoreConfig('payment/btcpayserver/host');
            $ipn_mapping = $this->getStoreConfig('payment/btcpayserver/ipn_mapping');

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

                case 'invoice_completed':
                    if ($invoice_status == 'complete'):

                        $order->addStatusHistoryComment('BTCPay Server Invoice <a href = "http://' . $host . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> status has changed to Completed.');
                        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                        $order->save();

                        $this->createMGInvoice($order);

                        return true;
                    endif;
                    break;

                case 'invoice_confirmed':
                    // pending or processing from plugin settings
                    if ($invoice_status === 'confirmed'):
                        $order->addStatusHistoryComment('BTCPay Server Invoice <a href = "http://' . $host . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> processing has been completed.');
                        if ($ipn_mapping != 'processing'):
                            #$order->setState(Order::STATE_NEW)->setStatus(Order::STATE_NEW);
                            $order->setState('new', true);
                            $order->setStatus('pending', true);
                        else:
                            $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                            $this->createMGInvoice($order);
                        endif;

                        $order->save();
                        return true;
                    endif;
                    break;

                case 'invoice_paidInFull':
                    // STATE_PENDING
                    if ($invoice_status === 'paid'):

                        $order->addStatusHistoryComment('BTCPay Server Invoice <a href = "http://' . $host. '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> is processing.');
                        $order->setState('new', true);
                        $order->setStatus('pending', true);
                        $order->save();
                        return true;
                    endif;
                    break;

                case 'invoice_failedToConfirm':
                    if ($invoice_status === 'invalid'):
                        $order->addStatusHistoryComment('BTCPay Server Invoice <a href = "http://' . $host . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> has become invalid because of network congestion.  Order will automatically update when the status changes.');
                        $order->save();
                        return true;
                    endif;
                    break;

                case 'invoice_expired':
                    if ($invoice_status === 'expired'):
                        $order->delete();

                        return true;
                    endif;
                    break;

                case 'invoice_refundComplete':
                    #load the order to update

                    $order->addStatusHistoryComment('BTCPay Server Invoice <a href = "http://' . $host . '/dashboard/payments/' . $order_invoice . '" target = "_blank">' . $order_invoice . '</a> has been refunded.');
                    $order->setState(Order::STATE_CLOSED)->setStatus(Order::STATE_CLOSED);

                    $order->save();

                    return true;
                    break;
            }

        endif;
    }

    public function createMGInvoice($order) {
        $invoice = $this->_invoiceService->prepareInvoice($order);
        $invoice->register();
        $invoice->save();
        $transactionSave = $this->_transaction->addObject($invoice)->addObject($invoice->getOrder());
        $transactionSave->save();
    }

}
