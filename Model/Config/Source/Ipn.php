<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Storefront\BTCPayServer\Model\Config\Source;


use Magento\Framework\Option\ArrayInterface;

/**
 * IPN Model
 */
class Ipn implements ArrayInterface {

    public function toOptionArray() {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
        ];

    }
}
