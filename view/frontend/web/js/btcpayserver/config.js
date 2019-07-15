require([
    'jquery',
    'jquery/jquery.cookie',
    'domReady!'// wait for dom ready
], function ($) {

    // TODO this URL check is ugly. Instead put this JS on the page itself using a template and layout XML
    if (window.location.pathname.indexOf('sales/guest/form') != -1) {
        //autofill form

        var orderId = $.cookie("oar_order_id");
        var billingLastName = $.cookie("oar_billing_lastname");
        var email = $.cookie("oar_email");

        if (orderId && billingLastName && email) {
            $("#oar-order-id").val(orderId);
            $("#oar-billing-lastname").val(billingLastName);
            $("#oar_email").val(email);

            // Delete the cookies
            $.cookie("oar_order_id", null, {path: '/'});
            $.cookie("oar_billing_lastname", null, {path: '/'});
            $.cookie("oar_email", null, {path: '/'});
        }

    }

});

