<?php

// Export orders
// Core PhpSpreadsheet classes
	// require_once plugin_dir_path(__FILE__) . 'lib/PhpSpreadsheet/Spreadsheet.php';
	// require_once plugin_dir_path(__FILE__) . 'lib/PhpSpreadsheet/IOFactory.php';
	// require_once plugin_dir_path(__FILE__) . 'lib/PhpSpreadsheet/Writer/Xlsx.php';
	// // Include additional classes as needed

    // use PhpOffice\PhpSpreadsheet\Spreadsheet;
    // use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    // use PhpOffice\PhpSpreadsheet\IOFactory;
    require_once plugin_dir_path(__FILE__) . 'lib/PHP_XLSX/xlsxwriter.class.php';

function add_export_orders_button($which) {
    global $typenow;

    if ('shop_order' === $typenow && 'top' === $which) {
        // Include all relevant query parameters in the export URL
        $export_url = add_query_arg(array(
            'export_orders' => '1', // Custom action parameter for export
            's' => isset($_GET['s']) ? $_GET['s'] : '', // Search term
            'post_status' => isset($_GET['post_status']) ? $_GET['post_status'] : 'all', // Order status
            'm' => isset($_GET['m']) ? $_GET['m'] : '', // Date filter
            '_customer_user' => isset($_GET['_customer_user']) ? $_GET['_customer_user'] : '', // Customer ID
            // Include any other filters as needed
        ), admin_url('edit.php?post_type=shop_order'));

        echo '<a href="' . esc_url($export_url) . '" id="export_orders_to_xls" class="button-primary">Export to XLS</a>';
    }
}
add_action('manage_posts_extra_tablenav', 'add_export_orders_button');


function handle_export_orders_action() {
    global $pagenow, $typenow;

    if ('edit.php' === $pagenow && 'shop_order' === $typenow && isset($_GET['export_orders']) && current_user_can('manage_woocommerce')) {
        // Optional: Verify nonce here if you added one

        // Initialize the writer
        $writer = new XLSXWriter();

        // Define column headers
        $headers = [
            'Order Number' => 'string',
            'Product Description' => 'string',
            'Sku' => 'string',
            'Price per unit before discount'=>'string',
            'Discount percentage' =>'string',
            'Price per unit after discount' => 'string',
            'Quantity' => 'string',
            'Total price after discount'=>'string',
            'Customer Name' => 'string',
            'Customer Email' => 'string',
            'Reference' => 'string',
            'Shipping method' => 'string',
            'Date'         => 'string',            
        ];

        // Write headers to the first sheet
        $writer->writeSheetHeader('b2bcut-orders', $headers);

        // Fetch WooCommerce orders
        $args = ['limit' => -1];
        // Search term
        if (!empty($_GET['s'])) {
            $args['s'] = sanitize_text_field($_GET['s']);
        }

        // Order status
        if (!empty($_GET['post_status']) && $_GET['post_status'] != 'all') {
            $args['status'] = sanitize_text_field($_GET['post_status']);
        }

        // Date filter
        if (!empty($_GET['m'])) {
            $year = substr($_GET['m'], 0, 4);
            $month = substr($_GET['m'], 4, 2);
            $args['date_created'] = $year . '-' . $month . '-01' . '...' . $year . '-' . $month . '-' . date('t', mktime(0, 0, 0, $month, 1, $year));
        }

        // Customer ID
        if (!empty($_GET['_customer_user'])) {
            $args['customer'] = intval($_GET['_customer_user']);
        }

        $orders = wc_get_orders($args);

        foreach ($orders as $order) {
    // Extract order details
    $order_number = $order->get_order_number();
     $product_sku = $product ? $product->get_sku() : 'N/A';
     // "Price per unit before discount" added
        $regular_price = $product ? $product->get_regular_price() : 0; // Regular price of the product

        // "Discount %" calculated
        $sale_price = $product ? $product->get_sale_price() : 0; // Sale price of the product
        $discount_percentage = $regular_price > 0 ? (($regular_price - $sale_price) / $regular_price) * 100 : 0; // Calculate discount percentage
        
        // "Price per unit after discount" added
        $price_after_discount = $sale_price > 0 ? $sale_price : $regular_price; // If there's a sale price, use it; otherwise, use the regular price
        
        

    $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $customer_email = $order->get_billing_email();
    $reference = get_post_meta($order->get_id(), 'custimoo_customer_order_reference', true);
    $reference = !empty($reference) ? $reference : '---'; // Fallback value if the meta is not set
    $shipping_methods = array_map(function($method) { return $method->get_method_title(); }, $order->get_shipping_methods());
    $shipping_methods_string = implode(', ', $shipping_methods);
    $order_date = $order->get_date_created()->date('Y-m-d H:i:s');
    $order_status = $order->get_status();
    $order_total = $order->get_total();

    // Iterate over each item in the order
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        $product_description = $product ? $product->get_name() : 'N/A';
        $product_sku = $product ? $product->get_sku() : 'N/A';
        $item_quantity = $item->get_quantity();
        // "Total price after discount" calculated
        $total_price_after_discount = $price_after_discount * $item_quantity; // Total price after discount

        // Prepare data for this row
        $row_data = [
            $order_number,
            $product_description,
            $product_sku,
            $regular_price,
            $discount_percentage . '%',
            $price_after_discount,
            $item_quantity,
            $total_price_after_discount,
            $customer_name,
            $customer_email,
            $reference,
            $shipping_methods_string,
            $order_date,
            // Add more details as needed
        ];

        // Write this row to the Excel sheet
        $writer->writeSheetRow('b2bcut-orders', $row_data);
        }
    }

        // Set headers for downloading the Excel file
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="b2bcut_Orders_' . date('Y-m-d_H-i-s') . '.xlsx"');
        header('Cache-Control: max-age=0');

        // Output the Excel file
        $writer->writeToStdOut();
        exit();
    }
}
add_action('admin_init', 'handle_export_orders_action');
