define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default'
    ],
    function ($, Component) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Storefront_BTCPay/payment/btcpay'
            },
            redirectAfterPlaceOrder: false, // Disable the standard redirect to Magento's "Thank You" page
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            afterPlaceOrder: function () {
                $.mage.redirect(window.checkoutConfig.payment.btcpay.paymentRedirectUrl);
            }
        });
    }
);
