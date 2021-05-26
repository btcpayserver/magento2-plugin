<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\BTCPay\Exception;

class InvalidPermissionFormat extends \RuntimeException
{
    public function __construct(string $permission)
    {
        $message = 'Invalid permission format: '.$permission;
        parent::__construct($message, 0, null);
    }

}
