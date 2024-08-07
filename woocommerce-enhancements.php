<?php
/*
Plugin Name: WooCommerce Enhancements
Description: Custom plugin to modify WooCommerce 'My Account' navigation.
Version: 1.0
Author: Gagan Batra
*/
require_once("orders-export.php");

require_once("tiered-pricing.php");

require_once("domain-user-role-mapping.php");

require_once("share-cart.php");

add_filter( 'woocommerce_account_menu_items', 'remove_my_account_menu_items' );
function remove_my_account_menu_items( $items ) {
    unset($items['dashboard']); // Remove "Dashboard"
    unset($items['downloads']); // Remove "Downloads"
    return $items;
}
// Add Order Refernce Column
add_filter( 'woocommerce_my_account_my_orders_columns', 'add_order_reference_column_header' );
function add_order_reference_column_header( $columns ) {
    // Create a new columns array
    $new_columns = [];

    // Loop through existing columns and add them to the new array
    foreach ( $columns as $key => $name ) {
        $new_columns[$key] = $name;

        // Insert your custom column after the 'order-date' column
        if ( $key === 'order-date' ) {
            $new_columns['order-reference'] = __('Reference', 'your-textdomain');
            $new_columns['order-shipping'] = __('Shipping', 'your-textdomain');
            $new_columns['order-company'] = __('Company', 'your-textdomain');
            $new_columns['order-address'] = __('Address', 'your-textdomain');
        }

    }

    return $new_columns;
}
// Display Order Reference value
add_action( 'woocommerce_my_account_my_orders_column_order-reference', 'add_order_reference_column_content' );
function add_order_reference_column_content( $order ) {
    // Assuming 'custimoo_customer_order_reference' is the meta key for the order reference
    echo esc_html( $order->get_meta( 'custimoo_customer_order_reference' ) );
}
add_action( 'woocommerce_my_account_my_orders_column_order-shipping', function( $order ) {
    echo esc_html( $order->get_shipping_method() );
});
add_action( 'woocommerce_my_account_my_orders_column_order-company', function( $order ) {

// Get the user ID from the order
    $user_id = $order->get_user_id();

    // Fetch the 'team_company_name' user meta
    $team_company_name = get_user_meta( $user_id, 'team_company_name', true );
    if ( empty($team_company_name) ) {

    $address = $order->get_address('shipping');
    echo esc_html( $address['company'] );
    }
    else{
        echo esc_html($team_company_name);
    }
});
add_action( 'woocommerce_my_account_my_orders_column_order-address', function( $order ) {
    echo wp_kses_post( $order->get_formatted_shipping_address() );
});


add_action( 'woocommerce_cart_actions', 'add_continue_shopping_button_to_cart' );
function add_continue_shopping_button_to_cart() {
    // Replace 'shop' with the specific page you want to send users to
    $shop_page_url = get_permalink( wc_get_page_id( 'shop' ) );
    $shop_page_url = home_url("/customizer/#/");
    // Button HTML
    echo '<a href="' . esc_url( $shop_page_url ) . '" class="btn-continue button wc-forward">Continue Shopping</a>';
}
function custom_override_checkout_fields( $fields ) {
    unset($fields['billing']);
    return $fields;
}
add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );

function enqueue_checkout_script() {
    if (is_checkout()) {
        wp_enqueue_script('my-checkout-script', plugin_dir_url(__FILE__) . 'js/checkout-custom.js', array('jquery'), '', true);
    }
}

add_action('wp_enqueue_scripts', 'enqueue_checkout_script');
add_action( 'woocommerce_before_single_product', 'customize_single_product_page_layout', 1 );

function customize_single_product_page_layout() {
  
  // Remove the product description tab
  remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );

  // Add the product description after the product meta
  add_action( 'woocommerce_single_product_summary', 'woocommerce_output_product_data_tabs', 25 );
}

add_filter( 'woocommerce_product_tabs', 'remove_woocommerce_product_tab', 98 );
function remove_woocommerce_product_tab( $tabs ) {
    unset( $tabs['additional_information'] ); 
    return $tabs;
}



function add_custom_user_column( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $title ) {
        // Add each existing column to the new columns array
        $new_columns[$key] = $title;
        
        // Insert the new column after 'Email'
        if ( $key === 'email' ) {
            $new_columns['team_company_name'] = 'Team';
            $new_columns['registration_date'] = 'Date';
            $new_columns['change_role']="Role";
        }
    }
    return $new_columns;
}
add_filter( 'manage_users_columns', 'add_custom_user_column' );


function show_team_company_name_column_content( $value, $column_name, $user_id ) {
    if ( 'team_company_name' == $column_name ) {
        return get_user_meta( $user_id, 'team_company_name', true );
    }

    if ('registration_date' === $column_name) {
        $user = get_userdata($user_id);
       
        $registration_date = date_i18n('F j, Y', strtotime($user->user_registered));
        return $registration_date;
    }

    if ($column_name == 'change_role') { // Assuming 'role' is the key for the existing role column
        global $wp_roles;
        $user = get_userdata($user_id);
        $user_roles = $user->roles;
        $current_role = array_shift($user_roles);
        
        $output = "<select class='custom-role-selector' data-user-id='{$user_id}' data-prev-role='{$current_role}'>";
        foreach ($wp_roles->role_names as $role => $name) {
            $selected = ($current_role == $role) ? 'selected="selected"' : '';
            $output .= "<option value='{$role}' {$selected}>{$name}</option>";
        }
        $output .= "</select>";
        return $output;
    }
    return $value;
}
add_action( 'manage_users_custom_column', 'show_team_company_name_column_content', 10, 3 );

function make_registration_date_column_sortable($columns) {
    $columns['registration_date'] = 'registered';
    $columns['team_company_name'] = 'team';
    return $columns;
}
add_filter('manage_users_sortable_columns', 'make_registration_date_column_sortable');

function sort_users_by_team( $query ) {
    if ( 'team' === $query->get('orderby') ) {
        $query->set('meta_key', 'team_company_name'); // Adjust this if the meta key is different
        $query->set('orderby', 'meta_value');
    }
}
add_action( 'pre_get_users', 'sort_users_by_team' );

function sort_users_by_default_registered_date( $query ) {
    // If no specific orderby has been requested, then we set our default
    if ( ! isset( $_REQUEST['orderby'] ) ) {
        $query->set('orderby', 'registered');
        $query->set('order', 'DESC'); // Use 'ASC' for oldest to newest
    }
    if ( 'team_company_name' === $query->get('orderby') ) {
        $query->set('meta_key', 'team_company_name'); // Adjust this if the meta key is different
        $query->set('orderby', 'meta_value');
    }
}
add_action( 'pre_get_users', 'sort_users_by_default_registered_date' );



function save_billing_company_to_user_meta( $customer_id ) {
    if ( isset( $_POST['billing_company'] ) ) {
        update_user_meta( $customer_id, 'team_company_name', sanitize_text_field( $_POST['billing_company'] ) );
    }
}
add_action( 'woocommerce_created_customer', 'save_billing_company_to_user_meta' );

function add_custom_class_to_order_details( $order ) {
    echo '<style>.woocommerce-customer-details { display: none; }</style>';
}
add_action( 'woocommerce_order_details_after_customer_details', 'add_custom_class_to_order_details' );

function add_custom_fields_to_edit_account_form() {
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);

    if (!$user) {
        return;
    }

    $phone = get_user_meta($user_id, 'billing_phone', true);
    $team_company_name = get_user_meta($user_id, 'team_company_name', true);

    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="billing_phone"><?php esc_html_e('Phone', 'woocommerce'); ?></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="billing_phone" id="billing_phone" value="<?php echo esc_attr($phone); ?>">
    </p>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="team_reseller_name"><?php esc_html_e('Team/Reseller Name', 'woocommerce'); ?></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="team_company_name" id="team_reseller_name" value="<?php echo esc_attr($team_company_name); ?>">
    </p>
    <?php
}
add_action('woocommerce_edit_account_form', 'add_custom_fields_to_edit_account_form');

function save_custom_fields_from_edit_account_form($user_id) {
    if (isset($_POST['billing_phone'])) {
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['billing_phone']));
        update_user_meta($user_id, 'shipping_phone', sanitize_text_field($_POST['billing_phone']));
    }
    if (isset($_POST['team_company_name'])) {
        update_user_meta($user_id, 'team_company_name', sanitize_text_field($_POST['team_company_name']));
        update_user_meta($user_id, 'shipping_company', sanitize_text_field($_POST['team_company_name']));
    }
}
add_action('woocommerce_save_account_details', 'save_custom_fields_from_edit_account_form');


// function custom_override_billing_fields( $fields ) {
//     return array(); // Return an empty array to remove all billing fields
// }
// add_filter( 'woocommerce_billing_fields', 'custom_override_billing_fields', 20 );
function add_custom_fee_based_on_quantity( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $minimum_quantity = 10; // Set the minimum quantity threshold
    $additional_fee = 1600; // Default additional fee amount

    // Get the current user's roles
    $user = wp_get_current_user();
    $roles = ( array ) $user->roles;

    // Check if the user has 'subscriber' or 'shop_manager' roles
    if ( in_array('subscriber', $roles) || in_array('shop_manager', $roles) ) {
        $additional_fee = 600; // Set reduced fee for these roles
    }

    // Calculate the total quantity in the cart
    $total_quantity = 0;
    foreach ( $cart->get_cart() as $cart_item ) {
        $total_quantity += $cart_item['quantity'];
    }

    // Add the fee if the total quantity is below the threshold
    if ( $total_quantity < $minimum_quantity ) {
        $cart->add_fee( __( 'Less then 10 pcs in cart fee', 'woocommerce' ), $additional_fee );
    }
}

add_action( 'woocommerce_cart_calculate_fees', 'add_custom_fee_based_on_quantity', 20, 1 );
function add_continue_shopping_button_to_cart_below() {
    $shop_page_url =  home_url("/customizer/#/"); // URL of your shop page

    echo '<div style="margin-right:20px;"><a href="' . esc_url( $shop_page_url ) . '" class="button wc-forward">Continue Shopping</a></div>';
}
add_action( 'woocommerce_proceed_to_checkout', 'add_continue_shopping_button_to_cart_below', 20 );


add_action( 'woocommerce_account_orders_endpoint', 'custom_orders_search_query' );
function custom_orders_search_query() {
    if ( (isset( $_GET['orders_search'] ) && !empty( $_GET['orders_search'] ) ) || ( isset( $_GET['orders_search_date'] ) && !empty( $_GET['orders_search_date'] ) )|| (isset( $_GET['orders_search_status'] ) && !empty( $_GET['orders_search_status'] ) ) ) {
        add_filter( 'woocommerce_my_account_my_orders_query', 'custom_my_orders_advanced_query' );
    }
}

function custom_my_orders_advanced_query( $args ) {
    global $wpdb;

    $search = isset( $_GET['orders_search'] ) ? sanitize_text_field( $_GET['orders_search'] ) : '';
    $search_date = isset( $_GET['orders_search_date'] ) ? sanitize_text_field( $_GET['orders_search_date'] ) : '';
    $search_status = isset( $_GET['orders_search_status'] ) ? sanitize_text_field( $_GET['orders_search_status'] ) : '';

    // Start building the query
    $search_query = "
        SELECT DISTINCT posts.ID 
        FROM {$wpdb->prefix}posts AS posts
        LEFT JOIN {$wpdb->prefix}postmeta AS postmeta ON posts.ID = postmeta.post_id
        LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS items ON posts.ID = items.order_id
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta ON items.order_item_id = itemmeta.order_item_id
        WHERE posts.post_type = 'shop_order'
    ";

    // Search by keyword
    if ( !empty( $search ) ) {
        $search_query .= $wpdb->prepare( " AND (posts.ID LIKE %s OR postmeta.meta_value LIKE %s OR itemmeta.meta_value LIKE %s)", '%'.$search.'%', '%'.$search.'%', '%'.$search.'%' );
    }

    // Search by date
    if ( !empty( $search_date ) ) {
        $date_query = $wpdb->prepare( " AND DATE(posts.post_date) = %s", $search_date );
        $search_query .= $date_query;
    }

    // Search by status
    if ( !empty( $search_status ) ) {
        $status_query = $wpdb->prepare( " AND posts.post_status = %s", 'wc-' . $search_status );
        $search_query .= $status_query;
    }

    // Execute the query
    $search_results = $wpdb->get_col( $search_query );

    // If results are found
    if ( !empty( $search_results ) ) {
        $args['post__in'] = $search_results;
    } else {
        $args['post__in'] = array(0); // No results found
    }

    return $args;
}

add_action( 'wp_enqueue_scripts', 'enqueue_date_picker' );
function enqueue_date_picker() {
    if ( is_account_page() ) {
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );
    }
}


function search_my_orders_ajax_handler() {

    global $wpdb;

    // Retrieve search parameters
    $search = $_POST['search'] !="" ? sanitize_text_field($_POST['search']) : '';
    $search_date = $_POST['search_date'] !="" ? sanitize_text_field($_POST['search_date']) : '';
    $search_status = $_POST['search_status'] !="" ? sanitize_text_field($_POST['search_status']) : '';

    // Start building the query
    $search_query = "
        SELECT DISTINCT posts.ID 
        FROM {$wpdb->prefix}posts AS posts
        LEFT JOIN {$wpdb->prefix}postmeta AS postmeta ON posts.ID = postmeta.post_id
        LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS items ON posts.ID = items.order_id
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta ON items.order_item_id = itemmeta.order_item_id
        WHERE posts.post_type = 'shop_order'
    ";

    // Search by keyword
    if ( !empty( $search ) ) {
        $search_query .= $wpdb->prepare( " AND (posts.ID LIKE %s OR postmeta.meta_value LIKE %s OR itemmeta.meta_value LIKE %s)", '%'.$search.'%', '%'.$search.'%', '%'.$search.'%' );
    }

    // Search by date
    if ( !empty( $search_date ) ) {
        $date_query = $wpdb->prepare( " AND DATE(posts.post_date) = %s", $search_date );
        $search_query .= $date_query;
    }

    // Search by status
    if ( !empty( $search_status ) ) {
        $status_query = $wpdb->prepare( " AND posts.post_status = %s", 'wc-' . $search_status );
        $search_query .= $status_query;
    }

    // Execute the query
    $search_results = $wpdb->get_col( $search_query );

    // If results are found
    if ( !empty( $search_results ) ) {
        $args['post__in'] = $search_results;
    } else {
        $args['post__in'] = array(0); // No results found
    }

    // Fetch orders based on the search criteria
    $orders = wc_get_orders($args);
    // print_r($orders);
    // Check if orders are found and output results
    if (!empty($orders)) {
         
        // foreach ($orders as $order_id) {
            // Load and display each order
            // You may need to customize how each order is displayed.
            //$order = wc_get_order($order_id);
            $customer_orders = array(
            'orders'        => $orders
        );

        // Include the WooCommerce orders template part
            ob_start();
            print_r($customer_orders);
            wc_get_template('myaccount/orders.php', $customer_orders);
            $output = ob_get_clean(); // Retrieve buffered output
             echo $output;
        // }
        
    } else {
        echo '<p>' . __('No orders found', 'woocommerce') . '</p>';
    }

    wp_die(); // Terminate and return the response
}
add_action('wp_ajax_search_my_orders', 'search_my_orders_ajax_handler');
add_action('wp_ajax_nopriv_search_my_orders', 'search_my_orders_ajax_handler');

// Add reference to order email.
// add_action( 'woocommerce_email_order_meta', 'custimoo_add_email_order_meta', 10, 4 );

function custimoo_add_email_order_meta( $order, $sent_to_admin, $plain_text, $email ) {
    // Check for the custom meta field and add it to the email
    $custimoo_reference = get_post_meta( $order->get_id(), 'custimoo_customer_order_reference', true );
    
    if ( $custimoo_reference ) {
        if ( $plain_text ) {
            echo "Customer Order Reference: " . $custimoo_reference . "\n";
        } else {
            echo "<p><strong>Reference:</strong> " . esc_html( $custimoo_reference ) . "</p>";
        }
    }
}

// add_action('woocommerce_email_after_order_table', 'add_order_reference_to_email', 20, 4);

// function add_order_reference_to_email($order, $sent_to_admin, $plain_text, $email) {
//     // Only run this on customer emails, not admin emails
//     if ($sent_to_admin) return;

//     // Get the custom order meta
//     $order_reference = get_post_meta($order->get_id(), 'custimoo_customer_order_reference', true);

//     // Check if the order reference is available
//     if (!empty($order_reference)) {
//         // Append the order reference to the email
//         if ($plain_text) {
//             echo "Order Reference: " . $order_reference . "\n";
//         } else {
//             echo "<p><strong>Order Reference:</strong> " . $order_reference . "</p>";
//         }
//     }
// }

// Custom Fee Based on User Role

function custimoo_adjust_price_based_on_role_and_quantity12( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        return;

    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 )
        return;

    // Define the price changes
    $customer_price = 1250; // Including VAT
    $shop_manager_price = 600; // Excluding VAT

    // Loop through cart items
    foreach ( $cart->get_cart() as $cart_item ) {
        // Check the quantity and user role
        if ( $cart_item['quantity'] < 10 ) {
            if ( current_user_can( 'customer' ) ) {
                // Set price for "customer" role
                $cart_item['data']->set_price( $customer_price );
            } elseif ( current_user_can( 'shop_manager' ) ) {
                // Set price for "shop_manager" role, adjust for VAT if necessary
                $price_excl_vat = $shop_manager_price / ( 1 + ( wc_get_price_including_tax( $cart_item['data'] ) / wc_get_price_excluding_tax( $cart_item['data'] ) ) );
                $cart_item['data']->set_price( $price_excl_vat );
            }
        }
    }
}
// add_action( 'woocommerce_before_calculate_totals', 'custimoo_adjust_price_based_on_role_and_quantity', 10, 1 );

// Tier Pricing Including VAT

add_action('wp_ajax_change_user_role', 'handle_ajax_role_update');
function handle_ajax_role_update() {
    check_ajax_referer('custom_nonce', 'nonce');

    if (!current_user_can('edit_users')) {
        wp_send_json_error('You do not have permission to edit user roles.');
    }

    $user_id = intval($_POST['user_id']);
    $new_role = sanitize_text_field($_POST['new_role']);

    $user = new WP_User($user_id);
    $user->set_role($new_role);

    wp_send_json_success('User role updated successfully.');
}


function custom_admin_scripts() {
    global $pagenow;
    if ('users.php' === $pagenow) {

        // Use plugins_url() to correctly point to your plugin's directory
        wp_enqueue_script('custom-admin-js', plugins_url('/js/custom-admin.js', __FILE__), array('jquery'), null, true);
        wp_localize_script('custom-admin-js', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('custom_nonce')));
        //wp_enqueue_style('custom-admin-css', plugins_url('/css/custom-admin.css', __FILE__));
    }
}
add_action('admin_enqueue_scripts', 'custom_admin_scripts');



function adjust_product_price_before_tax($price, $product) {

    // Skip the adjustment if we're in the cart, checkout, or doing AJAX, or if the price is already adjusted.
    if (is_cart() || is_checkout() || defined('DOING_AJAX') || get_post_meta($product->get_id(), '_price_adjusted', true)) {
        return $price;
    }
    // Check if the current user has the required role
    $user = wp_get_current_user();
    if ( empty( array_intersect( ['shop_manager', 'subscriber'], (array) $user->roles ) ) ) {
        // User does not have the required role, exit the function
        return $price;
    }


        $tax_rates = WC_Tax::get_rates();
        $standard_rate = 0;

        // Find the standard rate.
        foreach ($tax_rates as $rate) {
            if ('VAT' === $rate['label']) {
                $standard_rate = (float) $rate['rate'];
                break;
            }
        }
        if ($standard_rate > 0) {
            $tax_multiplier = 1 + ($standard_rate / 100);
            $adjusted_price = round($price / $tax_multiplier);

            // If this is a variation, mark it as adjusted
            if ($product->is_type('variation')) {
                update_post_meta($product->get_id(), '_price_adjusted', 'yes');
            }
            // error_log("Price adjusted:".$adjusted_price);
            return $adjusted_price;
        }
    // }

    return $price;
}

add_filter('woocommerce_product_get_price', 'adjust_product_price_before_tax', 999, 2);
add_filter('woocommerce_product_variation_get_price', 'adjust_product_price_before_tax', 999, 2);
