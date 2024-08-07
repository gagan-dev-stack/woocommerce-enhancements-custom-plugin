jQuery(document).ready(function($) {
    $("#ship-to-different-address-checkbox").trigger("click");
    $('.shipping_address').show();
    $("#ship-to-different-address").hide();
    $("<h2>SHIPPING ADDRESS</h2>").insertAfter("#ship-to-different-address");
    $(".woocommerce-shipping-fields .shipping_address").show();
});