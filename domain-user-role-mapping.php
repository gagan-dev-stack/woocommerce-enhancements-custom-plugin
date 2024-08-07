<?php

function custom_plugin_menu() {
    add_options_page('Domain Roles', 'Domain Roles', 'manage_options', 'domain-roles', 'domain_roles_page');
}
add_action('admin_menu', 'custom_plugin_menu');

function domain_roles_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $domain_roles = get_option('domain_roles_mappings', []);

    // Process POST request for add/edit
    if (isset($_POST['submit_domain_role'])) {
        check_admin_referer('domain_role_action', 'domain_role_nonce');

        $domain = sanitize_text_field($_POST['domain']);
        $role = sanitize_text_field($_POST['role']);
        $mapping = ['domain' => $domain, 'role' => $role];

        if ($_POST['editing_index'] !== '') {
            // Editing an existing mapping
            $editing_index = intval($_POST['editing_index']);
            $domain_roles[$editing_index] = $mapping;
        } else {
            // Adding a new mapping
            $domain_roles[] = $mapping;
        }

        update_option('domain_roles_mappings', $domain_roles);
        wp_redirect(remove_query_arg(['edit'])); // Redirect to avoid form resubmission
        exit;
    }

    // Process GET request for delete
    if (isset($_GET['delete']) && check_admin_referer('delete_domain_role_' . $_GET['delete'], '_wpnonce_delete')) {
        $index_to_delete = intval($_GET['delete']);
        unset($domain_roles[$index_to_delete]);
        $domain_roles = array_values($domain_roles); // Reindex array
        update_option('domain_roles_mappings', $domain_roles);
        wp_redirect(remove_query_arg(['delete', '_wpnonce_delete'])); // Redirect to avoid resubmission
        exit;
    }

    $editing_index = isset($_GET['edit']) ? intval($_GET['edit']) : '';
    $editing_mapping = $editing_index !== '' && isset($domain_roles[$editing_index]) ? $domain_roles[$editing_index] : null;

    // Display page content and form
    ?>
    
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($domain_roles as $index => $mapping): ?>
    <tr>
        <td><?= esc_html($mapping['domain']); ?></td>
        <td>
            <?php 
            // Fetch the role object using the role slug from the mapping          

            global $wp_roles;
                foreach ($wp_roles->roles as $role_value => $role_info) {
                    if($mapping['role'] == $role_value){
                        echo $role_info['name'];
                    }
                    
                }

            ?>
        </td>
        <td>
            <a href="<?= esc_url(add_query_arg(['edit' => $index])) ?>">Edit</a> |
            <a href="<?= esc_url(wp_nonce_url(admin_url('options-general.php?page=domain-roles&delete=' . $index), 'delete_domain_role_' . $index, '_wpnonce_delete')) ?>" onclick="return confirm('Are you sure you want to delete this mapping?');">Remove</a>
        </td>
    </tr>
<?php endforeach; ?>

            </tbody>
        </table>

        <h2><?= $editing_mapping ? 'Edit Domain Role Mapping' : 'Add New Domain Role Mapping'; ?></h2>
        <form method="post">
            <?php wp_nonce_field('domain_role_action', 'domain_role_nonce'); ?>
            <input type="hidden" name="editing_index" value="<?= esc_attr($editing_index); ?>">
            <input type="text" name="domain" value="<?= esc_attr($editing_mapping ? $editing_mapping['domain'] : ''); ?>" placeholder="example.com" required />
            <select name="role">
                <?php
                global $wp_roles;
                foreach ($wp_roles->roles as $role_value => $role_info) {
                    $selected = $editing_mapping && $editing_mapping['role'] === $role_value ? 'selected' : '';
                    echo "<option value='{$role_value}' {$selected}>{$role_info['name']}</option>";
                }
                ?>
            </select>
            <input type="submit" name="submit_domain_role" class="button button-primary" value="<?= $editing_mapping ? 'Update Mapping' : 'Add Mapping'; ?>" />
           <?php if ($editing_mapping !== null) {
            echo '<button type="button" class="button" onclick="window.location.href=\'' . esc_js(remove_query_arg('edit')) . '\'">Cancel</button>';
    echo '<input type="hidden" name="editing_index" value="' . esc_attr($editing_index) . '">';
}
?>
        </form>
    </div>
    <?php
}


function assign_role_based_on_domain($user_id) {
    $user_info = get_userdata($user_id);
    $user_email = $user_info->user_email;
    $email_domain = substr(strrchr($user_email, "@"), 1);

    // Retrieve domain-role mappings
    $domain_roles = get_option('domain_roles_mappings', []);

    foreach ($domain_roles as $mapping) {
        if ($email_domain == $mapping['domain']) {
            $user = new WP_User($user_id);
            $user->set_role($mapping['role']);
            break;
        }
    }
}
add_action('user_register', 'assign_role_based_on_domain');
