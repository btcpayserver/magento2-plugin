<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Test\Unit\Plugin;

use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Request;
use PHPUnit\Framework\TestCase;
use Storefront\BTCPay\Plugin\WebhookBodySizeLimit;

class WebhookBodySizeLimitTest extends TestCase
{
    public function testOversizedBodyIsRejected(): void
    {
        $request = $this->createRequest('/V2/btcpay/webhook', null);
        $plugin = $this->createPlugin(str_repeat('a', WebhookBodySizeLimit::MAX_BODY_BYTES + 1));

        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Payload too large');
        $plugin->beforeGetContent($request);
    }

    public function testOversizedContentLengthIsRejectedWithoutReadingBody(): void
    {
        $request = $this->createRequest(
            '/V2/btcpay/webhook',
            (string)(WebhookBodySizeLimit::MAX_BODY_BYTES + 1)
        );
        $plugin = $this->createPluginThatMustNotBeRead();

        $this->expectException(WebapiException::class);
        $this->expectExceptionMessage('Payload too large');
        $plugin->beforeGetContent($request);
    }

    public function testNormalWebhookBodyIsReturned(): void
    {
        $body = '{"type":"InvoiceCreated"}';
        $request = $this->createRequest('/V2/btcpay/webhook', (string)strlen($body));
        $plugin = $this->createPlugin($body);

        self::assertSame([$body], $plugin->beforeGetContent($request));
    }

    public function testMissingContentLengthUsesBoundedRead(): void
    {
        $body = '{"type":"InvoiceCreated"}';
        $request = $this->createRequest('/V2/btcpay/webhook', null);
        $plugin = $this->createPlugin($body);

        self::assertSame([$body], $plugin->beforeGetContent($request));
    }

    public function testOtherRoutesAreNotAffected(): void
    {
        $request = $this->createRequest('/V2/orders', null);
        $plugin = $this->createPluginThatMustNotBeRead();

        self::assertNull($plugin->beforeGetContent($request));
    }

    private function createRequest(string $pathInfo, ?string $contentLength): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getPathInfo')->willReturn($pathInfo);
        $request->method('getHeader')->willReturn($contentLength ?? false);

        return $request;
    }

    private function createPlugin(string $body): WebhookBodySizeLimit
    {
        return new class($body) extends WebhookBodySizeLimit {
            private $body;

            public function __construct(string $body)
            {
                $this->body = $body;
            }

            protected function readBody(): string
            {
                return $this->body;
            }
        };
    }

    private function createPluginThatMustNotBeRead(): WebhookBodySizeLimit
    {
        return new class extends WebhookBodySizeLimit {
            protected function readBody(): string
            {
                throw new \RuntimeException('Request body should not be read.');
            }
        };
    }
}
