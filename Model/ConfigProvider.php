<?php
/**
 * ConfigProvider
 *
 * @copyright Copyright © 2019 Storefront bvba. All rights reserved.
 * @author    info@storefront.be
 */

namespace Storefront\BTCPay\Model;


class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface {


    /**
     * @var BTCPay
     */
    private $btcPaymentMethod;

    public function __construct(BTCPay $BTCPaymentMethod) {
        $this->btcPaymentMethod = $BTCPaymentMethod;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig() {
        return [
            'payment' => [
                'btcpay' => [
                    'paymentRedirectUrl' => $this->btcPaymentMethod->getOrderPlaceRedirectUrl()
                ]
            ]
        ];
    }
}