<?php

namespace Simple_Import_Export;

class Admin
{

    public function __construct()
    {

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'export_handle'));

        add_filter('simple_prepare_data_for_export', [$this, 'woocommerce_product'], 20, 3);
        add_action('simple_export_handle_file', [$this, 'excel_export'], 20, 3);
    }

    public function admin_menu()
    {
        add_menu_page(
            __('Import/Export', 'edd-seller-panel'),
            __('Import/Export', 'edd-seller-panel'),
            'manage_options',
            'simple_import_export',
            [$this, 'page'],
            'dashicons-database',
            90
        );
    }

    public function page()
    {
        include \Simple_Import_Export::$plugin_path . '/templates/page.php';
    }

    public static function get_export_types()
    {
        return apply_filters('simple_import_export_type_lists', [
            'woocommerce_product' => 'WooCommerce Product'
        ]);
    }

    public static function get_export_extensions()
    {
        return apply_filters('simple_import_export_extensions_lists', [
            'excel' => 'Excel'
        ]);
    }

    public function export_handle()
    {
        global $pagenow;

        if (is_admin() and isset($_POST['export_nonce_simple'])
            || !wp_verify_nonce($_POST['export_nonce_simple'], 'export_nonce_simple')) {

            // Get Post Data
            $type = $_POST['type'];
            $extension = $_POST['extension'];

            // Set time Limit Zero
            set_time_limit(0);

            // Setup Data
            $data = apply_filters('simple_prepare_data_for_export', [], $type, $extension);

            // Export
            do_action('simple_export_handle_file', $data, $type, $extension);
        }
    }

    public function woocommerce_product($data, $type, $extension)
    {
        if ($type != 'woocommerce_product') {
            return $data;
        }

        $args = array(
            'status' => 'publish',
            'limit' => '-1',
            'order' => 'ASC',
            'return' => 'objects',
        );
        $products = wc_get_products($args);

        // Setup PHP Array
        // @see https://github.com/shuchkin/simplexlsxgen
        $columns = apply_filters('simple_prepare_excel_columns_wc_products', array(
            'ID',
            'Title',
            'Type',
            'SKU',
            'Regular Price',
            'Sale Price',
            'Price'
        ));
        $data = [$columns];

        // Setup Product Item
        foreach ($products as $product) {

            // Check Product Has Variable Or Simple
            $children_ids = (array)$product->get_children();
            if (count($children_ids) > 0) {

                foreach ($product->get_children() as $child_id) {
                    $variation = wc_get_product($child_id);

                    if (!$variation) {
                        continue;
                    }

                    $data[] = apply_filters('simple_import_export_wc_products_row_data', array(
                        $variation->get_id(),
                        $variation->get_formatted_name(),
                        $variation->get_type(),
                        $variation->get_sku(),
                        (int)$variation->get_regular_price() * 10,
                        (int)$variation->get_sale_price() * 10,
                        (int)$variation->get_price() * 10
                    ), $product);
                }
            } else {

                // Append
                $data[] = apply_filters('simple_import_export_wc_products_row_data', array(
                    $product->get_id(),
                    $product->get_name(),
                    $product->get_type(),
                    $product->get_sku(),
                    (int)$product->get_regular_price() * 10,
                    (int)$product->get_sale_price() * 10,
                    (int)$product->get_price() * 10
                ), $product);
            }
        }

        // Return
        return $data;
    }

    public function excel_export($data, $type, $extension)
    {
        if ($extension != 'excel') {
            return;
        }

        if (empty($data)) {
            FlashMessage::set('لیست خالی می باشد', 'info');
            return;
        }

        // include Package
        require_once \Simple_Import_Export::$plugin_path . '/libs/simplexlsxgen/vendor/autoload.php';

        // Setup From Array Excel
        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($data);

        // Dir name
        $parsed_args['dir'] = 'simple-import-export';

        // Get Directory
        $upload_dir = wp_upload_dir(null, false);

        // Get Default Path
        $defaultPath = rtrim($upload_dir['basedir'], "/") . '/' . $parsed_args['dir'] . '/';
        $default_link = rtrim($upload_dir['baseurl'], "/") . '/' . $parsed_args['dir'] . '/';
        if (!file_exists($defaultPath)) {
            @mkdir($defaultPath, 0777, true);
        }

        // Remove Last PDF File
        $expire = strtotime('-1 DAYS');
        $files = glob($defaultPath . '*');
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            if (filemtime($file) > $expire) {
                continue;
            }
            @unlink($file);
        }

        // Create FileName
        $fileName = $parsed_args['prefix'] . current_time('timestamp') . get_current_user_id() . '.xls';
        $path = rtrim($defaultPath, "/") . '/' . ltrim($fileName, "/");

        // Save File in Disk
        $xlsx->saveAs($path);

        // Excel Export
        $createFile = [
            'size' => filesize($path),
            'url' => rtrim($default_link, "/") . '/' . ltrim($fileName, "/")
        ];

        // Show Flush Message
        $text = 'فایل گزارش با موفقیت ایجاد شد.';
        $text .= '<br />';
        $text .= 'تعداد ردیف: ' . number_format(count($data));
        $text .= '<br />';
        $text .= '<a href="' . $createFile['url'] . '" download>' . 'دریافت فایل' . '</a>';
        FlashMessage::set($text, 'success');
    }

}

new Admin();
