<?php

namespace Storefront\BTCPay\Model;

class Item {
    /**
     * @var string
     */
    private $invoice_endpoint;
    private $token;
    private $host;
    private $item_params;
    /**
     * @var string
     */
    private $buyer_transaction_endpoint;

    /**
     * Item constructor.
     * @param $token
     * @param $host
     * @param $item_params
     */
    public function __construct($token, $host, $item_params) {
        $this->token = $token;
        $this->host = $host;
        $this->item_params = $item_params;

        $this->getItem();
    }


    /**
     * @return mixed
     */
    public function getItem() {
        $this->invoice_endpoint = $this->host . '/invoices';
        $this->buyer_transaction_endpoint = $this->host . '/invoiceData/setBuyerSelectedTransactionCurrency';
        $this->item_params->token = $this->token;
        return $this->item_params;
    }

}
