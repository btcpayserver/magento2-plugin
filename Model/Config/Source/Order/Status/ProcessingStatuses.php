<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Storefront\BTCPay\Model\Config\Source\Order\Status;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Config\Source\Order\Status;
use Magento\Sales\Model\Order\Config;

/**
 * Order Status source model
 */
class ProcessingStatuses extends AbstractStatuses {
    protected function getState() {
        return Order::STATE_PROCESSING;
    }
}
