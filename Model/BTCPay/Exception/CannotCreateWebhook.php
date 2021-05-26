<?php
declare(strict_types=1);


namespace Storefront\BTCPay\Model\BTCPay\Exception;


class CannotCreateWebhook extends \RuntimeException
{
    public function __construct(array $data, int $status, string $body)
    {
        $message = 'Cannot create webhook using data: ' . \json_encode($data) . '. Response was status ' . $status . ': ' . $body;
        parent::__construct($message, 0, null);
    }

}
