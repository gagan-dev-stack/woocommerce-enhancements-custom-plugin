jQuery(document).ready(function($) {

    $('.custom-role-selector').change(function() {
       
        var selector = $(this);
        var newRole = $(this).val();
        var userId = $(this).data('user-id');
        var prevRole = $(this).data('prev-role');
        
        if (confirm("Are you sure you want to change this user's role?")) {
            $.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'change_user_role',
                    user_id: userId,
                    new_role: newRole,
                    nonce: ajax_object.nonce
                },
                success: function(response) {
                    alert('Role updated successfully.');
                },
                error: function() {
                    alert('There was an error updating the role.');
                    selector.val(prevRole); // Revert to previous role on error
                }
            });
        } else {
            $(this).val(prevRole); // Revert to previous role on cancel
        }
    });
});