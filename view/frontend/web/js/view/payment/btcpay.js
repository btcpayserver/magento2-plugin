define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'btcpay',
                component: 'Storefront_BTCPay/js/view/payment/method-renderer/btcpay-method'
            }
        );
        return Component.extend({});
    }
);