<?php
declare(strict_types=1);

namespace Storefront\BTCPay\Model\BTCPay\Exception;

class InvoiceNotFoundException extends \RuntimeException
{

    public function __construct(string $invoiceId, string $btcpayStoreId, int $MagentoStoreId)
    {
        $message = 'BTCPay Server Invoice "' . $invoiceId . '" not found in store "' . $btcpayStoreId . '".';
        parent::__construct($message, 0, null);
    }
}
