<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Plugin;

use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Request;

/**
 * Bounds the size of the public BTCPay webhook request body before Magento's
 * webapi layer reads and JSON-decodes it.
 */
class WebhookBodySizeLimit
{
    /**
     * BTCPay webhook payloads are only a few KB; 64 KiB is intentionally generous.
     */
    public const MAX_BODY_BYTES = 65536;

    private const WEBHOOK_PATH_PATTERN = '#(?:^|/)V2/btcpay/webhook/?$#';

    /**
     * @param Request $subject
     * @return array|null
     * @throws WebapiException
     */
    public function beforeGetContent(Request $subject)
    {
        if (!$this->isWebhookRequest($subject)) {
            return null;
        }

        $contentLength = $subject->getHeader('Content-Length');
        if (is_string($contentLength) && is_numeric($contentLength)
            && (int)$contentLength > self::MAX_BODY_BYTES
        ) {
            throw new WebapiException(__('Payload too large'), 0, 413);
        }

        // Read at most one byte more than the limit. This works regardless of
        // whether Content-Length is present, accurate, or the body is chunked.
        $body = $this->readBody();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new WebapiException(__('Payload too large'), 0, 413);
        }

        // Short-circuit the original Request::getContent() call. php://input is
        // re-readable in supported PHP versions, so later calls remain safe.
        return [$body];
    }

    /**
     * @return string
     */
    protected function readBody(): string
    {
        $body = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);

        return $body === false ? '' : $body;
    }

    private function isWebhookRequest(Request $subject): bool
    {
        return preg_match(self::WEBHOOK_PATH_PATTERN, (string)$subject->getPathInfo()) === 1;
    }
}
