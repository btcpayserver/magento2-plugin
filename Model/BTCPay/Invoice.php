<?php
/**
 * Invoice
 *
 * @copyright Copyright © 2019 Storefront bvba. All rights reserved.
 * @author    info@storefront.be
 */

namespace Storefront\BTCPay\Model\BTCPay;


class Invoice {

    /**
     * @var array
     */
    private $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function getData(){
        return $this->data;
    }

    public function getInvoiceId(){
        return '';
    }

    public function getInvoiceUrl(){
        return '';
    }
}