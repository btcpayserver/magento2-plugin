<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Test\Unit\Api;

use Magento\Framework\Webapi\Rest\Request;
use PHPUnit\Framework\TestCase;
use Storefront\BTCPay\Api\Webhook;
use Storefront\BTCPay\Helper\Data;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;
use Storefront\BTCPay\Model\Webhook\DeliveryTracker;

class WebhookTest extends TestCase
{
    private const SECRET = 'webhook-secret';

    /**
     * @var BTCPayService
     */
    private $btcPayService;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var DeliveryTracker
     */
    private $deliveryTracker;

    protected function setUp(): void
    {
        $this->btcPayService = $this->createMock(BTCPayService::class);
        $this->helper = $this->createMock(Data::class);
        $this->helper->method('getWebhookSecret')->willReturn(self::SECRET);
        $this->deliveryTracker = $this->createMock(DeliveryTracker::class);
    }

    public function testAlreadyProcessedDeliveryIsSkipped(): void
    {
        $request = $this->createRequest($this->payload('delivery-1'));

        $this->deliveryTracker->expects(self::once())
            ->method('claim')
            ->with('delivery-1', 'invoice-1', 'InvoiceSettled')
            ->willReturn(false);
        $this->btcPayService->expects(self::never())->method('updateInvoice');

        self::assertTrue($this->createWebhook($request)->process());
    }

    public function testNewDeliveryIsProcessedAndClaimed(): void
    {
        $request = $this->createRequest($this->payload('delivery-1'));

        $this->deliveryTracker->expects(self::once())
            ->method('claim')
            ->with('delivery-1', 'invoice-1', 'InvoiceSettled')
            ->willReturn(true);
        $this->btcPayService->expects(self::once())
            ->method('updateInvoice')
            ->with('store-1', 'invoice-1', false);

        self::assertTrue($this->createWebhook($request)->process());
    }

    public function testReceivedPaymentDeliveryLogsPayment(): void
    {
        $request = $this->createRequest($this->payload('delivery-1', 'InvoiceReceivedPayment'));

        $this->deliveryTracker->method('claim')->willReturn(true);
        $this->btcPayService->expects(self::once())
            ->method('updateInvoice')
            ->with('store-1', 'invoice-1', true);

        self::assertTrue($this->createWebhook($request)->process());
    }

    public function testRedeliveryUsesOriginalDeliveryId(): void
    {
        $payload = $this->payload('new-delivery');
        $payload['isRedelivery'] = true;
        $payload['originalDeliveryId'] = 'original-delivery';

        $request = $this->createRequest($payload);

        $this->deliveryTracker->expects(self::once())
            ->method('claim')
            ->with('original-delivery', 'invoice-1', 'InvoiceSettled')
            ->willReturn(false);
        $this->btcPayService->expects(self::never())->method('updateInvoice');

        self::assertTrue($this->createWebhook($request)->process());
    }

    public function testFailedProcessingReleasesClaim(): void
    {
        $request = $this->createRequest($this->payload('delivery-1'));

        $this->deliveryTracker->expects(self::once())
            ->method('claim')
            ->willReturn(true);
        $this->deliveryTracker->expects(self::once())
            ->method('release')
            ->with('delivery-1');
        $this->btcPayService->method('updateInvoice')
            ->willThrowException(new \RuntimeException('Processing failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Processing failed');

        $this->createWebhook($request)->process();
    }

    public function testPayloadWithoutDeliveryIdIsProcessedWithoutTracking(): void
    {
        $payload = $this->payload('delivery-1');
        unset($payload['deliveryId']);

        $request = $this->createRequest($payload);

        $this->deliveryTracker->expects(self::never())->method('claim');
        $this->btcPayService->expects(self::once())
            ->method('updateInvoice')
            ->with('store-1', 'invoice-1', false);

        self::assertTrue($this->createWebhook($request)->process());
    }

    public function testInvalidSignatureIsRejectedBeforeTracking(): void
    {
        $request = $this->createRequest($this->payload('delivery-1'), 'sha256=invalid');

        $this->deliveryTracker->expects(self::never())->method('claim');
        $this->btcPayService->expects(self::never())->method('updateInvoice');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access Denied');

        $this->createWebhook($request)->process();
    }

    public function testPayloadWithoutInvoiceIdIsRejected(): void
    {
        $payload = $this->payload('delivery-1');
        unset($payload['invoiceId']);

        $request = $this->createRequest($payload);

        $this->deliveryTracker->expects(self::never())->method('claim');
        $this->btcPayService->expects(self::never())->method('updateInvoice');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid data POSTed');

        $this->createWebhook($request)->process();
    }

    private function createWebhook(Request $request): Webhook
    {
        return new Webhook($this->btcPayService, $request, $this->helper, $this->deliveryTracker);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $deliveryId, string $type = 'InvoiceSettled'): array
    {
        return [
            'deliveryId' => $deliveryId,
            'webhookId' => 'webhook-1',
            'type' => $type,
            'timestamp' => time(),
            'storeId' => 'store-1',
            'invoiceId' => 'invoice-1',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createRequest(array $payload, ?string $signature = null): Request
    {
        $body = (string)\json_encode($payload);

        $request = $this->createMock(Request::class);
        $request->method('getContent')->willReturn($body);
        $request->method('getHeader')
            ->with('BTCPay-Sig')
            ->willReturn($signature ?? 'sha256=' . hash_hmac('sha256', $body, self::SECRET));

        return $request;
    }
}
