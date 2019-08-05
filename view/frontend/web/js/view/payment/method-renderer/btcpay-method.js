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
            getMailingAddress: function () {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
            getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },
            placeOrder: function (data, event) {
                // Disable the standard redirect to Magento's "Thank You" page
                this.redirectAfterPlaceOrder = false;

                return this._super(data, event);
            },
            afterPlaceOrder: function () {
                $.mage.redirect(window.checkoutConfig.payment.btcpay.paymentRedirectUrl);
            }
        });
    }
);
