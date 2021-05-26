<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\BTCPay\Exception;

class NoBtcPayStoreConfigured extends \RuntimeException
{

    public function __construct()
    {
        $message = 'No BTCPay Server Store ID configured.';
        parent::__construct($message, 0, null);
    }
}
