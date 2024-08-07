jQuery(document).ready(function($) {
    $('#share-cart-button').click(function() {
        $.ajax({
            url: shareCart.ajax_url,
            method: 'POST',
            data: {
                action: 'generate_share_cart_url',
                nonce: shareCart.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#share-cart-url').val(response.data).show().select();
                    document.execCommand('copy');
                    alert('Shareable cart URL has been copied to the clipboard!');
                } else {
                    alert('Failed to generate shareable cart URL.');
                }
            },
            error: function() {
                alert('Error occurred while generating shareable cart URL.');
            }
        });
    });
});
