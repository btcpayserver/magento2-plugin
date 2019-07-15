<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Storefront\BTCPayServer\Model\Config\Source\Order\Status;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Config\Source\Order\Status;
use Magento\Sales\Model\Order\Config;

/**
 * Order Status source model
 */
abstract class AbstractStatuses implements \Magento\Framework\Data\OptionSourceInterface {


    /**
     * @var Config
     */
    protected $_orderConfig;


    abstract protected function getState();

    /**
     * @param Config $orderConfig
     */
    public function __construct(Config $orderConfig) {
        $this->_orderConfig = $orderConfig;
    }


    /**
     * @return array
     */
    public function toOptionArray() {
        $state = $this->getState();
        $statuses = $this->_orderConfig->getStateStatuses([$state]);

        $options = [
            [
                'value' => '',
                'label' => __('Use Magento\'s default status')
            ]
        ];
        foreach ($statuses as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => $label
            ];
        }
        return $options;
    }
}
