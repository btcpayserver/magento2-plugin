<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Storefront\BTCPay\Model\Config\Source;


use Magento\Framework\Option\ArrayInterface;

class HttpScheme implements ArrayInterface {

    public function toOptionArray() {
        return [
            'https' => 'HTTPS (Recommended)',
            'http' => 'HTTP (Unsecure, but may be needed if you run BTCPay Server on TOR)',
        ];

    }
}
