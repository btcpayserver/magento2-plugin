<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Test\Unit\Model\Security;

use BTCPayServer\Result\Invoice as BTCPayServerInvoice;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Storefront\BTCPay\Model\Security\PaymentDataValidator;

class PaymentDataValidatorTest extends TestCase
{
    /**
     * @var PaymentDataValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new PaymentDataValidator();
    }

    public function testOrderTokenCannotBeUsedForAnotherOrder(): void
    {
        $order = $this->createOrder('42', '000000042', '12.3400');
        $otherOrder = $this->createOrder('43', '000000043', '12.3400');
        $token = $this->validator->createOrderToken($order);

        self::assertTrue($this->validator->isValidOrderToken($order, $token));
        self::assertFalse($this->validator->isValidOrderToken($otherOrder, $token));
    }

    public function testLegacyOrderTokenCanBeRecognizedDuringUpgrade(): void
    {
        $createdAt = '2026-08-12 10:00:00';
        $order = $this->createOrder('42', '000000042', '12.3400', $createdAt);
        $legacyToken = sha1('42--' . $createdAt);

        self::assertTrue($this->validator->isValidLegacyOrderToken($order, $legacyToken));
        self::assertFalse($this->validator->isValidOrderToken($order, $legacyToken));
    }

    public function testInvoiceMustMatchOrderAmountCurrencyAndIdentity(): void
    {
        $order = $this->createOrder('42', '000000042', '12.3400');
        $invoice = new BTCPayServerInvoice([
            'id' => 'invoice-1',
            'storeId' => 'store-1',
            'amount' => '12.34',
            'currency' => 'EUR',
            'metadata' => ['orderId' => '000000042'],
        ]);

        $this->validator->assertInvoiceMatchesOrder($order, $invoice, 'invoice-1', 'store-1');
        self::assertTrue(true);
    }

    public function testInvoiceAmountMismatchIsRejected(): void
    {
        $order = $this->createOrder('42', '000000042', '12.3400');
        $invoice = new BTCPayServerInvoice([
            'id' => 'invoice-1',
            'storeId' => 'store-1',
            'amount' => '0.01',
            'currency' => 'EUR',
            'metadata' => ['orderId' => '000000042'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('amount does not match');
        $this->validator->assertInvoiceMatchesOrder($order, $invoice, 'invoice-1', 'store-1');
    }

    public function testInvoiceCurrencyMismatchIsRejected(): void
    {
        $order = $this->createOrder('42', '000000042', '12.3400');
        $invoice = new BTCPayServerInvoice([
            'id' => 'invoice-1',
            'storeId' => 'store-1',
            'amount' => '12.34',
            'currency' => 'USD',
            'metadata' => ['orderId' => '000000042'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('currency does not match');
        $this->validator->assertInvoiceMatchesOrder($order, $invoice, 'invoice-1', 'store-1');
    }

    public function testInvoiceFromAnotherOrderIsRejected(): void
    {
        $order = $this->createOrder('42', '000000042', '12.3400');
        $invoice = new BTCPayServerInvoice([
            'id' => 'invoice-1',
            'storeId' => 'store-1',
            'amount' => '12.34',
            'currency' => 'EUR',
            'metadata' => ['orderId' => '000000999'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not belong');
        $this->validator->assertInvoiceMatchesOrder($order, $invoice, 'invoice-1', 'store-1');
    }

    public function testInvoiceFromAnotherStoreIsRejected(): void
    {
        $order = $this->createOrder('42', '000000042', '12.3400');
        $invoice = new BTCPayServerInvoice([
            'id' => 'invoice-1',
            'storeId' => 'store-2',
            'amount' => '12.34',
            'currency' => 'EUR',
            'metadata' => ['orderId' => '000000042'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('store does not match');
        $this->validator->assertInvoiceMatchesOrder($order, $invoice, 'invoice-1', 'store-1');
    }

    public function testPartialPaymentIsNeverTreatedAsTerminalUnpaid(): void
    {
        self::assertTrue($this->validator->isTerminalUnpaidInvoice(new BTCPayServerInvoice([
            'status' => BTCPayServerInvoice::STATUS_EXPIRED,
            'additionalStatus' => 'None',
        ])));
        self::assertFalse($this->validator->isTerminalUnpaidInvoice(new BTCPayServerInvoice([
            'status' => BTCPayServerInvoice::STATUS_EXPIRED,
            'additionalStatus' => BTCPayServerInvoice::ADDITIONAL_STATUS_PAID_PARTIAL,
        ])));
        self::assertFalse($this->validator->isTerminalUnpaidInvoice(new BTCPayServerInvoice([
            'status' => BTCPayServerInvoice::STATUS_SETTLED,
            'additionalStatus' => 'None',
        ])));
        self::assertFalse($this->validator->isTerminalUnpaidInvoice(new BTCPayServerInvoice([
            'status' => BTCPayServerInvoice::STATUS_EXPIRED,
            'additionalStatus' => BTCPayServerInvoice::ADDITIONAL_STATUS_PAID_OVER,
        ])));
        self::assertFalse($this->validator->isTerminalUnpaidInvoice(new BTCPayServerInvoice([
            'status' => BTCPayServerInvoice::STATUS_NEW,
            'additionalStatus' => 'None',
        ])));
    }

    private function createOrder(
        string $id,
        string $incrementId,
        string $grandTotal,
        ?string $createdAt = null
    ): Order
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn('btcpay');

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId',
                'getData',
                'getIncrementId',
                'getProtectCode',
                'getCreatedAt',
                'getPayment',
                'getOrderCurrencyCode',
                'getGrandTotal',
            ])
            ->getMock();
        $order->method('getId')->willReturn($id);
        $order->method('getData')->willReturnMap([
            ['secret', null],
        ]);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getProtectCode')->willReturn('protect-' . $id);
        $order->method('getCreatedAt')->willReturn($createdAt ?? gmdate('Y-m-d H:i:s'));
        $order->method('getPayment')->willReturn($payment);
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getGrandTotal')->willReturn($grandTotal);

        return $order;
    }
}
