jQuery(document).ready(function($) {
    $('#export_orders_to_xls').click(function(e) {
        e.preventDefault();
        $.ajax({
            url: exportOrders.ajaxurl,
            type: 'POST',
            data: {
                action: 'export_orders_to_xls_action'
            },
            success: function(response) {
                window.location = response;
            }
        });
    });
});
