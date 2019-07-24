<?php
/**
 * Data
 *
 * @copyright Copyright © 2019 Storefront bvba. All rights reserved.
 * @author    info@storefront.be
 */

namespace Storefront\BTCPay\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Service\InvoiceService;
use RuntimeException;
use stdClass;
use Storefront\BTCPay\Model\Invoice;
use Storefront\BTCPay\Model\Item;

class Data {
    private $invoiceService;
    private $transaction;
    private $orderRepository;

    /**
     * @var AdapterInterface
     */
    private $db;

    /**
     * @param OrderRepository $orderRepository
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(OrderRepository $orderRepository, InvoiceService $invoiceService, Transaction $transaction, ResourceConnection $resourceConnection) {
        $this->orderRepository = $orderRepository;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->db = $resourceConnection->getConnection();
    }

    /**
     * @param int $transactionId
     * @return Order|null
     * @throws LocalizedException
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function updateTransaction(int $transactionId): ?Order {
        $tableName = $this->db->getTableName('btcpay_transactions');
        $select = $this->db->select()->from($tableName)->where('transaction_id = ?', $transactionId)->limit(1);

        $result = $this->db->fetchRow($select);
        if ($result) {
            $row = $result->fetch();
            if ($row) {
                $orderId = $row['order_id'];
                $order = $this->orderRepository->get($orderId);

                $storeId = $order->getStoreId();

                $token = $this->getStoreConfig('payment/btcpay/token', $storeId);
                $host = $this->getStoreConfig('payment/btcpay/host', $storeId);

                $params = new stdClass();
                $params->invoiceID = $transactionId;
                //$params->extension_version = $this->getExtensionVersion();
                $item = new Item($token, $host, $params);
                $invoice = new Invoice($item);

                $orderStatus = json_decode($invoice->checkInvoiceStatus($transactionId), true);

                if ($orderId !== $orderStatus['orderID']) {
                    throw new RuntimeException('The supplied order ID ' . $orderId . ' does not match transaction ID ' . $transactionId . '. Cannot process BTCPay Server IPN.');
                }

                $invoiceStatus = $orderStatus['data']['status'] ?? false;

                // TODO refactor to use the Transaction model instead of direct SQL reading
                $where = $this->db->quoteInto('order_id = ?', $orderId) . ' and ' . $this->db->quoteInto('transaction_id = ?', $transactionId);
                $rowsChanged = $this->db->update($tableName, ['status' => $invoiceStatus], $where);

                // TODO fill $event in some other way...
                $event = [];
                switch ($event['name']) {

                    case 'invoice_paidInFull':

                        if ($invoiceStatus === \Storefront\BTCPay\Model\Transaction::STATUS_PAID) {
                            // 1) Payments have been made to the invoice for the requested amount but the transaction has not been confirmed yet
                            $paidNotConfirmedStatus = $this->getStoreConfig('payment/btcpay/payment_paid_status', $storeId);

                            $order->addStatusHistoryComment('Payment underway, but not confirmed yet', $paidNotConfirmedStatus);
                            $order->save();
                        }
                        break;

                    case 'invoice_confirmed':
                        if ($invoiceStatus === \Storefront\BTCPay\Model\Transaction::STATUS_CONFIRMED) {
                            // 2) Paid and confirmed (happens before completed and transitions to it quickly)

                            // TODO maybe add the transation ID in the comment or something like that?

                            $confirmedStatus = $this->getStoreConfig('payment/btcpay/payment_confirmed_status', $storeId);
                            $order->addStatusHistoryComment('Payment confirmed, but not completed yet', $confirmedStatus);

                            $order->save();
                        }
                        break;

                    case 'invoice_completed':
                        if ($invoiceStatus === \Storefront\BTCPay\Model\Transaction::STATUS_COMPLETE) {
                            // 3) Paid, confirmed and settled. Final!
                            // TODO maybe add the transation ID in the comment or something like that?

                            $completedStatus = $this->getStoreConfig('payment/btcpay/payment_completed_status', $storeId);
                            $order->addStatusHistoryComment('Payment completed', $completedStatus);
                            $invoice = $this->invoiceService->prepareInvoice($order);
                            $invoice->register();

                            // TODO we really need to save the invoice first as we are saving it again in this transaction? Leaving it out for now.
                            //$invoice->save();

                            $transactionSave = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
                            $transactionSave->save();
                        }
                        break;

                    case 'invoice_failedToConfirm':
                        if ($invoiceStatus === \Storefront\BTCPay\Model\Transaction::STATUS_INVALID) {
                            $order->addStatusHistoryComment('Failed to confirm the order. The order will automatically update when the status changes.');
                            $order->save();
                        }
                        break;

                    case 'invoice_expired':
                        if ($invoiceStatus === \Storefront\BTCPay\Model\Transaction::STATUS_EXPIRED) {
                            // Invoice expired - let's do nothing.
                        }
                        break;

                    case 'invoice_refundComplete':
                        // Full refund

                        $order->addStatusHistoryComment('Refund received through BTCPay Server.');
                        $order->setState(Order::STATE_CLOSED)->setStatus(Order::STATE_CLOSED);

                        $order->save();

                        break;

                    // TODO what about partial refunds, partial payments and overpayment?
                }

                return $order;
            } else {
                // No transaction round found
                return null;
            }
        }
    }

    public function updateIncompleteTransations() {
        // TODO poll BTCPay Server for updates on non-completed invoices (just in case we missed an update pushed to Magento)
        // TODO refactor to use the Transaction model instead of direct SQL reading
        $tableName = $this->db->getTableName('btcpay_transactions');
        $select = $this->db->select()->from($tableName)->where('transaction_status != ?', 'completed')->limit(1);

        $result = $this->db->fetchRow($select);
        $row = $result->fetch();
    }
}
