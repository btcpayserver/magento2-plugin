<?php

namespace Storefront\BTCPayServer\Model;

class Cron {

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


}
