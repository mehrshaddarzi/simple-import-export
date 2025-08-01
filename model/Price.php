<?php

namespace Simple_Import_Export\Model;

class Price
{

    public static string $key = 'price';

    public function __construct()
    {
        // Import
        add_filter('simple_import_export_type_lists_at_import', [$this, 'method']);
        add_action('simple_import_export_form_fields_import', [$this, 'import_field']);
        add_filter('simple_import_handle_item', [$this, 'import_row'], 10, 6);
    }

    public function method($array)
    {
        // Check Exist WooCommerce
        if (!class_exists('\woocommerce')) {
            return $array;
        }

        $array[self::$key] = __('بروزرسانی محصولات ووکامرس از اکسل', 'simple-import-export');
        return $array;
    }

    public function import_field()
    {
        ?>

        <tr class="form-field form-required simple_import_export_d_none" data-import-type="<?php echo self::$key; ?>">
            <th scope="row">
                دریافت تمونه فایل اکسل
            </th>
            <td>
                <a href="<?php echo \Simple_Import_Export::$plugin_url . '/model/price/example.xlsx'; ?>" download="">دانلود</a>
            </td>
        </tr>

        <tr class="form-field form-required simple_import_export_d_none" data-import-type="<?php echo self::$key; ?>">
            <th scope="row">
                <label for="stock_update">
                    <span><?php _e('برور رسانی موجودی (ستون دوم)', 'simple-import-export'); ?></span>
                </label>
            </th>
            <td>
                <select name="stock_update">
                    <option value="yes">آری</option>
                    <option value="no">خیر</option>
                </select>
            </td>
        </tr>

        <tr class="form-field form-required simple_import_export_d_none" data-import-type="<?php echo self::$key; ?>">
            <th scope="row">
                <label for="price_update">
                    <span><?php _e('بروزرسانی قیمت (ستون سوم)', 'simple-import-export'); ?></span>
                </label>
            </th>
            <td>
                <select name="price_update">
                    <option value="yes">آری</option>
                    <option value="no">خیر</option>
                </select>
            </td>
        </tr>

        <tr class="form-field form-required simple_import_export_d_none" data-import-type="<?php echo self::$key; ?>">
            <th scope="row">
                <label for="convert_price">
                    <span><?php _e('تبدیل ریال به تومان', 'simple-import-export'); ?></span>
                </label>
            </th>
            <td>
                <select name="convert_price">
                    <option value="yes">آری</option>
                    <option value="no">خیر</option>
                </select>
            </td>
        </tr>

        <?php
    }

    public function import_row($return, $row, $key, $type, $extension, $option)
    {
        if ($type != self::$key) {
            return $return;
        }

        // Check First Row
        if ($row[0] == "sku") {
            return $return;
        }

        // $row is product ID
        $sku = $row[0];
        $stock_quantity = (int)str_ireplace([",", "."], "", $row[1]);
        $price = (float)str_ireplace([",", "."], "", $row[2]);
        if (!empty($option['input']['convert_price']) and trim($option['input']['convert_price']) == "yes") {
            $price = (float)($row[2] / 10);
        }

        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            return new \WP_Error('item_import_error_gallery', 'محصولی با SKU ' . $sku . ' یافت نشد.');
        }

        $product = wc_get_product($product_id);

        try {

            $isSave = false;

            if (!empty($option['input']['stock_update']) and trim($option['input']['stock_update']) == "yes") {

                $product->set_stock_quantity($stock_quantity);
                $new_stock_status = ($stock_quantity < 1 ? 'outofstock' : 'instock');
                $product->set_stock_status($new_stock_status);

                $isSave = true;
            }

            if (!empty(trim($option['input']['price_update'])) and trim($option['input']['price_update']) == "yes") {

                $product->set_regular_price($price);
                $product->set_price($price);

                $isSave = true;
            }

            if ($isSave) {
                $product->save();
            }

        } catch (\Exception $e) {

            return new \WP_Error('item_import_error_gallery', 'خطا در آپدیت محصول با شناسه ' . $sku . ': ' . $e->getMessage());
        }

        // Return
        return $return;
    }

}

new Price();