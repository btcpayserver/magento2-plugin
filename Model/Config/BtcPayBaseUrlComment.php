<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\Config;

use Magento\Config\Model\Config\CommentInterface;

/**
 * Warns when the configured BTCPay Server URL uses plain HTTP.
 *
 * HTTP is still allowed so the module can be tested against a local BTCPay
 * instance, but production setups should always use HTTPS.
 */
class BtcPayBaseUrlComment implements CommentInterface
{
    public function getCommentText($elementValue)
    {
        $value = trim((string)$elementValue);

        if (stripos($value, 'http://') === 0) {
            return '<span style="color: red">' . __(
                'Warning: this URL uses plain HTTP. Only use HTTP for local testing; '
                . 'use HTTPS in production so the API key is not sent in clear text.'
            ) . '</span>';
        }

        return __('The base URL of your BTCPay Server instance. Use HTTPS in production.');
    }
}
