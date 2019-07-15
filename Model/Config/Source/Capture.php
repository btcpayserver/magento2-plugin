<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Storefront\BTCPayServer\Model\Config\Source;


use Magento\Framework\Option\ArrayInterface;

class Capture implements ArrayInterface {

    public function toOptionArray() {
        return [
            '1' => 'Yes',
            '0' => 'No',
        ];

    }
}
