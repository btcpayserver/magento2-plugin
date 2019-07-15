require([
    'jquery',
    'jquery/jquery.cookie',
    'domReady!'// wait for dom ready
], function ($) {

    // TODO this URL check is ugly. Instead put this JS on the page itself using a template and layout XML
    if (window.location.pathname.indexOf('sales/guest/form') != -1) {
        //autofill form
        if (document.cookie.indexOf('btcpayserver_order_id') !== -1) {

            var orderId = $.cookie("btcpayserver_order_id");
            var billingLastName = $.cookie("btcpayserver_billing_lastname");
            var email = $.cookie("btcpayserver_email");


            $("#oar-order-id").val(orderId);
            $("#oar-billing-lastname").val(billingLastName);
            $("#oar_email").val(email);

            // Delete the cookies
            $.cookie("btcpayserver_order_id", null, { path: '/' });
            $.cookie("btcpayserver_billing_lastname", null, { path: '/' });
            $.cookie("btcpayserver_email", null, { path: '/' });

        }
    }

});

