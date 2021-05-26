<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\BTCPay\Exception;

use GuzzleHttp\Exception\ClientException;

class UnexpectedSituation extends \RuntimeException
{
    public function __construct(string $message = 'This situation should not be possible.', \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

}
