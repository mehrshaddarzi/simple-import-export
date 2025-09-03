<?php

namespace Simple_Import_Export\Model;

class SKU
{

    public static string $keyDemo = 'wc_sku_demo';
    public static string $key = 'wc_sku';

    public function __construct()
    {
        // یک اکسل که ستون اول کد های جدید و ستون دوم کد های فعلی ووکامرس می باشد

        // Import
        add_filter('simple_import_export_type_lists_at_import', [$this, 'method']);
        add_filter('simple_import_handle_item', [$this, 'import_row'], 10, 6);
    }

    public function method($array)
    {
        // Check Exist WooCommerce
        if (!class_exists('\woocommerce')) {
            return $array;
        }

        $array[self::$keyDemo] = __('دمو تغییر SKU ووکامرس', 'simple-import-export');
        $array[self::$key] = __('تغییر SKU ووکامرس', 'simple-import-export');
        return $array;
    }

    public function import_row($return, $row, $key, $type, $extension, $option)
    {

        if ($type == self::$keyDemo) {

            // Check Rows
            if (isset($row[0]) and !empty($row[0]) and isset($row[1]) and !empty($row[1])) {

                $new_sku = trim($row[0]);
                $wc_sku = trim($row[1]);

                $product_id = wc_get_product_id_by_sku($wc_sku);
                if (!$product_id) {
                    return new \WP_Error('item_import_error', 'No | ' . $wc_sku . ' | ' . $new_sku);
                }

                return new \WP_Error('item_import_error', 'Yes | ' . $wc_sku . ' | ' . $new_sku);
            }
        }

        if ($type == self::$key) {

            // Check Rows
            if (isset($row[0]) and !empty($row[0]) and isset($row[1]) and !empty($row[1])) {

                $new_sku = trim($row[0]);
                $wc_sku = trim($row[1]);

                $product_id = wc_get_product_id_by_sku($wc_sku);
                if (!$product_id) {
                    return new \WP_Error('item_import_error', 'No | ' . $wc_sku . ' | ' . $new_sku);
                }

                // use function
                $product = wc_get_product($product_id);
                $product->set_sku($new_sku);
                $product->save();

                // check post meta
                $_sku = get_post_meta($product_id, '_sku', true);
                if ($_sku != $new_sku) {
                    update_post_meta($product_id, '_sku', $new_sku);
                }

                return $return;
            }
        }

        return $return;
    }
}

new SKU();