<?php

if (!defined('ABSPATH')) {
    die("No direct access!");
}

if (in_array('custimoo-woocommerce/custimoo.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    if (!class_exists('WC_Share_Cart_Url')) {

        class WC_Share_Cart_Url
        {
            private static $session_cart_keys = array(
                'cart', 'cart_totals', 'applied_coupons', 'coupon_discount_totals', 'coupon_discount_tax_totals'
            );

            public static function init()
            {
                register_activation_hook(__FILE__, array(__CLASS__, 'create_shared_cart_table'));

                add_action('woocommerce_before_cart', array(__CLASS__, 'before_cart'));
                add_action('woocommerce_load_cart_from_session', array(__CLASS__, 'set_session_cart'), 1);
                add_action('init', array(__CLASS__, 'handle_download_quote')); // Add init action hook
                
            }

            public static function create_shared_cart_table()
            {
                global $wpdb;
                $table_name = $wpdb->prefix . 'shared_carts';
                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    share_key varchar(100) NOT NULL,
                    cart_data longtext NOT NULL,
                    PRIMARY KEY  (id),
                    UNIQUE KEY share_key (share_key)
                ) $charset_collate;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }

            public static function get_session_cart()
            {
                $cart_session = array();

                foreach (self::$session_cart_keys as $key) {
                    $cart_session[$key] = WC()->session->get($key);
                }

                // Capture final prices for each cart item
                $cart = WC()->cart->get_cart();
                foreach ($cart as $cart_item_key => $cart_item) {
                    $cart_session['final_prices'][$cart_item_key] = round($cart_item['data']->get_price());
                }

                // Capture chosen shipping method
                $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
                $cart_session['chosen_shipping_methods'] = $chosen_shipping_methods;

                return serialize($cart_session);
            }

            public static function save_shared_cart_to_db($session_cart)
            {
                global $wpdb;
                $table_name = $wpdb->prefix . 'shared_carts';
                $share_key = wp_generate_password(16, false);

                $wpdb->insert(
                    $table_name,
                    array(
                        'share_key' => $share_key,
                        'cart_data' => $session_cart,
                    ),
                    array(
                        '%s',
                        '%s',
                    )
                );

                return $share_key;
            }

            public static function load_shared_cart_from_db($share_key)
            {
                global $wpdb;
                $table_name = $wpdb->prefix . 'shared_carts';

                $row = $wpdb->get_row($wpdb->prepare("SELECT cart_data FROM $table_name WHERE share_key = %s", $share_key));
                if ($row) {
                    return unserialize($row->cart_data);
                }

                return null;
            }

            public static function set_session_cart()
            {
                if (isset($_REQUEST['share'])) {
                    $share_key = sanitize_text_field($_REQUEST['share']);
                    $cart_data = self::load_shared_cart_from_db($share_key);

                    if ($cart_data) {
                        foreach (self::$session_cart_keys as $key) {
                            WC()->session->set($key, $cart_data[$key]);
                        }

                        // Set chosen shipping method
                        if (isset($cart_data['chosen_shipping_methods'])) {
                            WC()->session->set('chosen_shipping_methods', $cart_data['chosen_shipping_methods']);
                        }

                        update_user_meta(
                            get_current_user_id(),
                            '_woocommerce_persistent_cart_' . get_current_blog_id(),
                            array('cart' => WC()->cart->get_cart())
                        );
                    } else {
                        self::load_user_previous_cart();
                    }
                } else {
                    self::load_user_previous_cart();
                }
            }

            public static function load_user_previous_cart()
            {
                $user_id = get_current_user_id();
                $cart = get_user_meta($user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);

                if (!empty($cart)) {
                    WC()->session->set('cart', $cart['cart']);
                } else {
                    WC()->cart->empty_cart();
                }
            }

            public static function before_cart()
            {
                ?>
                <form method="post">
                    <button type="submit" name="wc_cart_share"><?php _e('Share this cart', 'wc-share-cart-url'); ?></button>
                    <button type="submit" name="wc_cart_download_quote"><?php _e('Download Quote', 'wc-share-cart-url'); ?></button>
                </form>
                <?php

                if (isset($_POST['wc_cart_share'])) {
                    $session_cart = self::get_session_cart();
                    $share_key = self::save_shared_cart_to_db($session_cart);

                    echo '<h2>Share link:</h2>';
                    echo '<pre>';
                    echo esc_html(wc_get_cart_url()) . '?share=' . esc_html($share_key);
                    echo '</pre>';
                }
            }


public static function download_quote()
            {
                require_once plugin_dir_path(__FILE__) . 'lib/PHP_XLSX/xlsxwriter.class.php';

                $writer = new XLSXWriter();
                $headers = array(
                    'Product Name' => 'string',
                    'Quantity' => 'integer',
                    'Unit Price' => 'price',
                    'Sub Total' => 'price',
                );

                $writer->writeSheetHeader('Quote', $headers);

                $cart = WC()->cart->get_cart();
                $total_price = 0;

                foreach ($cart as $cart_item_key => $cart_item) {
                    $product = $cart_item['data'];
                    $product_name = $product ? $product->get_name() : 'N/A';
                    $quantity = $cart_item['quantity'];
                    $unit_price = $product ? $product->get_price() : 0;
                    $sub_total = $unit_price * $quantity;
                    $total_price += $sub_total;

                    $row_data = array(
                        $product_name,
                        $quantity,
                        $unit_price,
                        $sub_total,
                    );

                    $writer->writeSheetRow('Quote', $row_data);
                }

                // Add shipping cost to total price
                $shipping_total = WC()->cart->get_shipping_total();
                $grand_total = $total_price + $shipping_total;

                // Add total row
                $total_row_data = array(
                    'Total',
                    '',
                    '',
                    $total_price,
                );
                $writer->writeSheetRow('Quote', $total_row_data);

                // Add shipping row
                $shipping_row_data = array(
                    'Shipping',
                    '',
                    '',
                    $shipping_total,
                );
                $writer->writeSheetRow('Quote', $shipping_row_data);

                // Add grand total row
                $grand_total_row_data = array(
                    'Grand Total',
                    '',
                    '',
                    $grand_total,
                );
                $writer->writeSheetRow('Quote', $grand_total_row_data);

                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="Cart_Quote_' . date('Y-m-d_H-i-s') . '.xlsx"');
                header('Cache-Control: max-age=0');

                $writer->writeToStdOut();
                exit();
            }

 public static function handle_download_quote()
            {
                if (isset($_POST['wc_cart_download_quote'])) {
                    self::download_quote();
                }
            }

            
        }

        WC_Share_Cart_Url::init();
    }
}
