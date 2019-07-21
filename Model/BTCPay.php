<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Storefront\BTCPay\Model;


use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Pay In Store payment method model
 */
class BTCPay extends AbstractMethod {

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'btcpay';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
        $r = parent::isAvailable($quote);

        if ($r) {
            $token = $this->getConfigData('token');

            if ($token === '' || strlen($token) !== 44) {
                // Hide the payment method
                $r = false;
            }
        }

        return $r;
    }

}
