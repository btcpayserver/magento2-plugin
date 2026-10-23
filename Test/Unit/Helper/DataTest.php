<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Test\Unit\Helper;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Helper\Data;
use Storefront\BTCPay\Model\BTCPay\BTCPayService;

class DataTest extends TestCase
{
    public function testSuperfluousPermissionIsEscapedInInstallationError(): void
    {
        $btcPayService = $this->createMock(BTCPayService::class);
        $btcPayService->method('getWebhookSecret')->willReturn('secret');
        $btcPayService->method('getApiKeyPermissions')->willReturn([
            'btcpay.store.canviewinvoices',
            '<script>alert(1)</script>',
        ]);

        $errors = $this->createHelper($btcPayService)->getInstallationErrors(0, false);
        $rendered = implode("\n", array_map('strval', $errors));

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $rendered);
        self::assertStringNotContainsString('<script>alert(1)</script>', $rendered);
    }

    public function testMissingPermissionIsEscapedInInstallationError(): void
    {
        $maliciousStoreId = '<script>alert(2)</script>';

        $btcPayService = $this->createMock(BTCPayService::class);
        $btcPayService->method('getWebhookSecret')->willReturn('secret');
        $btcPayService->method('getApiKeyPermissions')->willReturn([
            'btcpay.store.canviewinvoices:' . $maliciousStoreId,
        ]);

        $errors = $this->createHelper($btcPayService)->getInstallationErrors(0, false);
        $rendered = implode("\n", array_map('strval', $errors));

        self::assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $rendered);
        self::assertStringNotContainsString('<script>alert(2)</script>', $rendered);
    }

    public function testWebhookSecretIsReadThroughService(): void
    {
        $btcPayService = $this->createMock(BTCPayService::class);
        $btcPayService->expects(self::once())
            ->method('getStoredWebhookSecret')
            ->willReturn('decrypted-secret');

        self::assertSame(
            'decrypted-secret',
            $this->createHelper($btcPayService)->getWebhookSecret()
        );
    }

    private function createHelper(BTCPayService $btcPayService): Data
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(function ($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        });

        return new Data(
            $btcPayService,
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(CacheInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(StoreRepositoryInterface::class),
            $this->createMock(StoreManagerInterface::class),
            $escaper
        );
    }
}
