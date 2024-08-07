<?php

    function register_tiered_pricing_menu() {
        add_menu_page(
            __('Tiered Pricing', 'textdomain'),
            __('Tiered Pricing', 'textdomain'),
            'manage_options',
            'tiered-pricing',
            'tiered_pricing_admin_page',
            'dashicons-chart-line',
            6
        );
    }

    add_action('admin_menu', 'register_tiered_pricing_menu');

    function tiered_pricing_admin_page() {
    
    // Handle deletion of rules
    if (isset($_GET['action'], $_GET['rule'], $_GET['_wpnonce']) && $_GET['action'] === 'delete' && wp_verify_nonce($_GET['_wpnonce'], 'delete_tiered_pricing_' . $_GET['rule'])) {

        $rules = get_option('tiered_pricing_rules', array());
        $index = intval($_GET['rule']);
        if (isset($rules[$index])) {
            unset($rules[$index]);
            $rules = array_values($rules); // Re-index the array
            update_option('tiered_pricing_rules', $rules);
            $redirect_url = remove_query_arg(['action', 'rule', '_wpnonce']);
            wp_redirect($redirect_url);
            exit;
        }
    }

    // Add new rules form processing
    if (isset($_POST['submit_tiered_pricing'], $_POST['add_tiered_pricing_nonce']) && check_admin_referer('add_tiered_pricing_action', 'add_tiered_pricing_nonce')) {
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0.0;
        $selected_products = isset($_POST['products']) ? array_map('intval', $_POST['products']) : [];

        // Fetch existing rules
        $rules = get_option('tiered_pricing_rules', array());
 
        // Check if a similar rule already exists
        $rule_exists = false;
        foreach ($rules as $rule) {
            if ($rule['quantity'] === $quantity && $rule['discount'] === $discount && array_diff($rule['products'], $selected_products) === array_diff($selected_products, $rule['products'])) {
                $rule_exists = true;
                break;
            }
        }

        if (!$rule_exists) {
            // Append new rule to the array
            $rules[] = ['quantity' => $quantity, 'discount' => $discount, 'products' => $selected_products];
            update_option('tiered_pricing_rules', $rules);
            echo '<div class="notice notice-success is-dismissible"><p>New tiered pricing rule added.</p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>A similar tiered pricing rule already exists.</p></div>';
        }
    
    }

    // Display the form and existing rules
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Tiered Pricing', 'textdomain') . '</h1>';
    echo '<form method="post" action="">';
    wp_nonce_field('add_tiered_pricing_action', 'add_tiered_pricing_nonce');
    echo '<table class="form-table">';
    echo '<tr><th scope="row">' . esc_html__('Minimum Quantity', 'textdomain') . '</th><td><input type="number" name="quantity" class="small-text" required></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Discount Percentage', 'textdomain') . '</th><td><input type="number" name="discount" step="0.01" class="small-text" required>%</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Select Products', 'textdomain') . '</th><td><select name="products[]" multiple class="widefat" required>';
    $args = array('limit' => -1, 'status' => 'publish');
    $products = wc_get_products($args);
    foreach ($products as $product) {
        echo '<option value="' . esc_attr($product->get_id()) . '">' . esc_html($product->get_name()) . '</option>';
    }
    echo '</select><button type="button" id="select-all-products">' . esc_html__('Select All Products', 'textdomain') . '</button></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="submit_tiered_pricing" class="button-primary" value="' . esc_attr__('Add Tiered Pricing Rule', 'textdomain') . '">';
    echo '</form>';
    echo '<h2>' . esc_html__('Existing Tiered Pricing Rules', 'textdomain') . '</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>' . esc_html__('Minimum Quantity', 'textdomain') . '</th><th>' . esc_html__('Discount Percentage', 'textdomain') . '</th><th>' . esc_html__('Products', 'textdomain') . '</th><th>' . esc_html__('Actions', 'textdomain') . '</th></tr></thead>';
    echo '<tbody>';
 
    foreach (get_option('tiered_pricing_rules', array()) as $index => $rule) {
        $product_names = array_map(function ($id) { return wc_get_product($id)->get_name(); }, $rule['products']);
        echo '<tr><td>' . esc_html($rule['quantity']) . '</td><td>' . esc_html($rule['discount']) . '%</td><td>' . esc_html(implode(', ', $product_names)) . '</td>';
        echo '<td><a href="' . esc_url(wp_nonce_url(add_query_arg(['action' => 'delete', 'rule' => $index], admin_url('admin.php?page=tiered-pricing')), 'delete_tiered_pricing_' . $index)) . '" class="button" onclick="return confirm(\'Are you sure you want to delete this rule?\');">' . esc_html__('Delete', 'textdomain') . '</a></td></tr>';
    }
    echo '</tbody></table></div>';
    echo '<script>jQuery("#select-all-products").click(function(){ jQuery("select[name=\'products[]\'] option").prop("selected", true); });</script>';
}



    function display_tiered_pricing_on_product_page() {

        global $product;
        $rules = get_option('tiered_pricing_rules', array());
        $product_id = $product->get_id();
        $original_price = $product->get_price();

        // Get the current user and their roles
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles; // User roles is an array

        // Check if the current user is an admin
        if (in_array('administrator', $user_roles) || in_array('customer', $user_roles)) {
            // Admin user detected; don't apply any B2B rules
            // Optionally, you can set original_price to regular_price if required
            // $original_price = $product->get_regular_price();
        } else {
            // For non-admin users, proceed with B2B rules

            // We'll take the first role if it exists
            $user_role = !empty($user_roles) ? $user_roles[0] : null;

            // Ensure the Roles & Rules B2B class exists before attempting to use it
            if (class_exists('Rrb2b_Rules') && $user_role !== null) {
                $rrb2b_rules = new Rrb2b_Rules();
                $price_html = $rrb2b_rules::rrb2b_get_price_html($product->get_price(), $product);
                $original_price = extract_lowest_price_from_html($price_html);
            }
        }    
        // print_r($product);
        //wc_price( $product->get_regular_price() );

        $display_table = false;

        // Check if the current product ID is in any of the selected products for the rules
        foreach ($rules as $rule) {
            if (in_array($product_id, $rule['products'])) {
                $display_table = true;
                break; // Exit the loop once a match is found
            }
        }

        // Only display the table if the current product ID matches any of the product IDs in the rules
        if ($display_table) {
            echo '<table class="tiered-pricing-table">';
            echo '<tr><th>' . esc_html__('Quantity', 'textdomain') . '</th><th>' . esc_html__('Discount', 'textdomain') . '</th><th>'.esc_html__('Price').'</th></tr>';
            
            foreach ($rules as $rule) {
                if (in_array($product_id, $rule['products'])) {
                    error_log("Cart original_price :".$original_price);
                    $discounted_price = $original_price - ($original_price * ($rule['discount'] / 100));
                    echo '<tr><td>' . esc_html($rule['quantity']) . '+</td><td>' . esc_html($rule['discount']) . '%</td><td>'. wc_price($discounted_price) .'</td></tr>';
                }
            }
            
            echo '</table>';
        }
    }
    add_action('woocommerce_single_product_summary', 'display_tiered_pricing_on_product_page', 20);


    function extract_lowest_price_from_html($html) {

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $prices = [];

    $bdi_elements = $dom->getElementsByTagName('bdi');
    foreach ($bdi_elements as $element) {
        $price = preg_replace('/[^\d.]/', '', $element->nodeValue);
        $prices[] = floatval($price); // Convert the price to a float to compare values
    }

    return min($prices); // Return the minimum value in the prices array
}


function adjust_cart_item_price_before_tax($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
     // if (isset($_REQUEST['share'])) return;
    // Check if the cart is loaded from shared URL
    if (WC()->session->get('is_shared_cart')) {
        return;
    }

    $user = wp_get_current_user();
    if (empty(array_intersect(['shop_manager', 'subscriber'], (array)$user->roles))) {
        return;
    }

    static $running = false;
    if ($running) return;
    $running = true;

    $total_quantity = 0;
    $standard_rate = 0;

    foreach ($cart->get_cart() as $cart_item) {
        $total_quantity += $cart_item['quantity'];
    }

    // Find the highest applicable discount rule
    $rules = get_option('tiered_pricing_rules', array());
    $highest_discount_rule = 0;

    foreach ($rules as $rule) {
        if ($total_quantity >= $rule['quantity']) {
            if ($rule['discount'] > $highest_discount_rule) {
                $highest_discount_rule = $rule['discount'];
            }
        }
    }

    // Apply the highest discount rule
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        $tax_rates = WC_Tax::get_rates();

        foreach ($tax_rates as $rate) {
            if ('VAT' === $rate['label']) {
                $standard_rate = (float)$rate['rate']; // 25%
                break;
            }
        }

        if ($standard_rate > 0) {
            $tax_multiplier = 1 + ($standard_rate / 100);
            $cart_price = round($product->get_regular_price());
            $original_price = round($cart_price / $tax_multiplier);

            // Calculate the discounted price
            $discount_amount = round($original_price * ($highest_discount_rule / 100));
            $adjusted_price = round($original_price - $discount_amount);

            // Set the adjusted price in the cart item data
            $cart_item['data']->set_price($adjusted_price);
        }
    }

    $running = false;
}
add_action('woocommerce_before_calculate_totals', 'adjust_cart_item_price_before_tax', 999, 1);


function custom_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
    // Get the original price
    $original_price = round($cart_item['data']->get_price());
        
    // Calculate the subtotal with the correct quantity
    $item_subtotal = $original_price * $cart_item['quantity'];

    // Round the subtotal to 2 decimal places
    $rounded_subtotal = round($item_subtotal);

    // Format the subtotal with the currency symbol
    return wc_price($rounded_subtotal);
}

// Apply the filter to modify the cart item subtotal
add_filter('woocommerce_cart_item_subtotal', 'custom_cart_item_subtotal', 10, 3);


function recalculate_cart_total($cart_total) {
    $cart = WC()->cart;
    $new_cart_total = 0;

    // Loop through each cart item and calculate the subtotal
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        $quantity = $cart_item['quantity'];
        $price = round($product->get_price());
        error_log("Cart prices ".$price);
        // Calculate item total (considering discounts, if any)
        $item_total = $price * $quantity;

        // Add item total to new cart total
        $new_cart_total += $item_total;

    }
    // Get the shipping total
    $shipping_total = $cart->get_shipping_total();

    // Add shipping total to new cart total
    $new_cart_total += $shipping_total;

    // Get the coupon discount total
    $discount_total = $cart->get_discount_total();

    // Subtract discount total from new cart total
    $new_cart_total -= $discount_total;

    // Apply any additional logic or rounding if needed
    $new_cart_total = round($new_cart_total);
    error_log("New total ".$new_cart_total);
    return $new_cart_total;
}

// Apply the filter to modify the cart total
add_filter('woocommerce_calculated_total', 'recalculate_cart_total', 999, 1);
// Function to recalculate cart subtotal
function recalculate_cart_subtotal($cart_subtotal) {
    $cart = WC()->cart;
    $new_subtotal = 0;

    // Loop through each cart item and calculate the subtotal
    foreach ($cart->get_cart() as $cart_item) {
        $product = $cart_item['data'];
        $quantity = $cart_item['quantity'];
        $price = round($product->get_price());

        // Calculate item total
        $item_total = $price * $quantity;

        // Add item total to new subtotal
        $new_subtotal += $item_total;
    }

    // Apply any additional logic or rounding if needed
    $new_subtotal = round($new_subtotal);

    return wc_price($new_subtotal);
}

// Apply the filter to modify the cart subtotal
add_filter('woocommerce_cart_subtotal', 'recalculate_cart_subtotal', 10, 1);


// Function to round shipping rates
function custom_round_shipping_rates($rates, $package) {
    // Loop through each shipping rate
    foreach ($rates as $rate_id => $rate) {
        // Round the shipping cost to the nearest whole number
        $rounded_cost = round($rate->cost);

        // Set the new rounded cost
        $rates[$rate_id]->cost = $rounded_cost;

        // Optionally, you can modify the label to indicate rounding
        //$rates[$rate_id]->label .= ' (Rounded)';
    }

    return $rates;
}

// Apply the filter to round the shipping rates
add_filter('woocommerce_package_rates', 'custom_round_shipping_rates', 10, 2);
