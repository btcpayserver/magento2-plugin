<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\Security;

use BTCPayServer\Result\Invoice as BTCPayServerInvoice;
use Magento\Sales\Model\Order;
use RuntimeException;
use Storefront\BTCPay\Model\BTCPay;

class PaymentDataValidator
{
    private const MONEY_SCALE = 8;

    public function createOrderToken(Order $order): string
    {
        $protectCode = (string)$order->getProtectCode();
        if ($protectCode === '') {
            throw new RuntimeException('Cannot sign a BTCPay return URL without an order protect code.');
        }

        return hash_hmac('sha256', $this->getOrderTokenPayload($order), $protectCode);
    }

    public function isValidOrderToken(Order $order, string $token): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return false;
        }

        try {
            return hash_equals($this->createOrderToken($order), $token);
        } catch (RuntimeException $exception) {
            return false;
        }
    }

    public function isValidLegacyOrderToken(Order $order, string $token): bool
    {
        if (preg_match('/^[a-f0-9]{40}$/D', $token) !== 1) {
            return false;
        }

        $legacyPayload = $order->getId()
            . '-'
            . (string)$order->getData('secret')
            . '-'
            . $order->getCreatedAt();

        return hash_equals(sha1($legacyPayload), $token);
    }

    public function assertInvoiceMatchesOrder(
        Order $order,
        BTCPayServerInvoice $invoice,
        string $expectedInvoiceId,
        string $expectedStoreId
    ): void {
        $payment = $order->getPayment();
        if (!$payment || $payment->getMethod() !== BTCPay::PAYMENT_METHOD_CODE) {
            throw new RuntimeException('The order is not a BTCPay order.');
        }

        $invoiceData = $invoice->getData();
        $invoiceId = isset($invoiceData['id']) ? (string)$invoiceData['id'] : '';
        if ($invoiceId === '' || !hash_equals($expectedInvoiceId, $invoiceId)) {
            throw new RuntimeException('The BTCPay invoice ID does not match the requested invoice.');
        }

        $invoiceStoreId = isset($invoiceData['storeId']) ? (string)$invoiceData['storeId'] : '';
        if ($invoiceStoreId === '' || !hash_equals($expectedStoreId, $invoiceStoreId)) {
            throw new RuntimeException('The BTCPay invoice store does not match the expected store.');
        }

        $metadataOrderId = isset($invoiceData['metadata']['orderId'])
            ? (string)$invoiceData['metadata']['orderId']
            : '';
        $orderIncrementId = (string)$order->getIncrementId();
        if ($metadataOrderId === '' || !hash_equals($orderIncrementId, $metadataOrderId)) {
            throw new RuntimeException('The BTCPay invoice does not belong to the Magento order.');
        }

        $invoiceCurrency = isset($invoiceData['currency']) ? (string)$invoiceData['currency'] : '';
        $orderCurrency = (string)$order->getOrderCurrencyCode();
        if ($invoiceCurrency === '' || !hash_equals($orderCurrency, $invoiceCurrency)) {
            throw new RuntimeException('The BTCPay invoice currency does not match the Magento order.');
        }

        $invoiceAmount = isset($invoiceData['amount']) ? (string)$invoiceData['amount'] : '';
        $orderAmount = (string)$order->getGrandTotal();
        $decimalPattern = '/^\d+(?:\.\d+)?$/D';
        if (preg_match($decimalPattern, $invoiceAmount) !== 1
            || preg_match($decimalPattern, $orderAmount) !== 1
            || bccomp($invoiceAmount, $orderAmount, self::MONEY_SCALE) !== 0
        ) {
            throw new RuntimeException('The BTCPay invoice amount does not match the Magento order.');
        }
    }

    public function isTerminalUnpaidInvoice(BTCPayServerInvoice $invoice): bool
    {
        return !$invoice->isSettled()
            && !$invoice->isPartiallyPaid()
            && !$invoice->isOverpaid()
            && ($invoice->isExpired() || $invoice->isInvalid());
    }

    private function getOrderTokenPayload(Order $order): string
    {
        return implode('|', [
            'v1',
            (string)$order->getId(),
            (string)$order->getIncrementId(),
            (string)$order->getProtectCode(),
            (string)$order->getCreatedAt(),
        ]);
    }
}
