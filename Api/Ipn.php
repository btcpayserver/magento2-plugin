<?php

namespace Storefront\BTCPay\Api;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use RuntimeException;
use Storefront\BTCPay\Helper\Data;

class Ipn implements IpnInterface
{

    /**
     * @var Data
     */
    private $helper;

    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @return bool
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function process(): bool
    {
        $postedString = file_get_contents('php://input');
        // We are reading the raw POSTed data, as getting an array as input is not working. This is the safest and easiest solution for now.
        if (!$postedString) {
            throw new RuntimeException('No data posted. Cannot process BTCPay Server IPN.');
        }
        $data = json_decode($postedString, true);

        $btcpayInvoiceId = $data['data']['id'] ?? null;

        // Only use the "id" field from the POSTed data and discard the rest. The posted data can be malicious.
        unset($data);

        if ($btcpayInvoiceId && is_numeric($btcpayInvoiceId)) {
            $this->helper->updateInvoice($btcpayInvoiceId);
            return true;
        } else {
            throw new RuntimeException('Invalid data POSTed');
        }
    }
}
