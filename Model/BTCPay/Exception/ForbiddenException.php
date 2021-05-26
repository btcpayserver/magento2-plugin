<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\BTCPay\Exception;

use GuzzleHttp\Exception\ClientException;

class ForbiddenException extends \RuntimeException
{
    public function __construct(ClientException $e)
    {
        $message = $e->getMessage();
        $code = $e->getCode();
        $previous = $e;
        parent::__construct($message, $code, $previous);
    }

}
