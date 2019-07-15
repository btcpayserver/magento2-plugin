<?php

namespace Storefront\BTCPayServer\Model;

use Magento\Store\Model\ScopeInterface;

class Ipn {

    public function __construct(\Storefront\BTCPayServer\Helper\Data $helper) {
        $this->helper = $helper;
    }

    public function getStoreConfig($path, $storeId) {
        $_val = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        return $_val;

    }


    public function processIpn() {
        $postedString = file_get_contents('php://input');
        if (!$postedString) {
            throw new \RuntimeException('No data posted. Cannot process BTCPay Server IPN.');
        }
        $data = json_decode($postedString, true);

        $btcpayInvoiceId = $data['data']['id'];

        // Only use the "id" field from the POSTed data and discard the rest. The posted data can be malicious.
        unset($data);

        $this->helper->updateInvoice($btcpayInvoiceId);
    }


}