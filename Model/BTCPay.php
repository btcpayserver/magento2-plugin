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


    const PAYMENT_METHOD_CODE = 'btcpay';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;

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

            if (!$token) {
                // Hide the payment method, no token entered
                $r = false;
            }
        }

        return $r;
    }

}
