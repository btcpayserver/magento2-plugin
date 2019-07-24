<?php

namespace Storefront\BTCPay\Api;

interface IpnInterface {

    /**
     * @return bool
     */
    public function process();


}
