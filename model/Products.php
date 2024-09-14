<?php

namespace Simple_Import_Export\Model;

use Simple_Import_Export\Helper;

class Products
{

    public static string $key = 'wc_products';

    public function __construct()
    {

        // Export
        add_filter('simple_import_export_type_lists_at_export', [$this, 'method']);
        add_action('simple_import_export_form_fields_export', [$this, 'export_field']);
        add_filter('simple_prepare_data_for_export', [$this, 'export'], 20, 3);

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

        $array[self::$key] = __('WooCommerce Products', 'simple-import-export');
        return $array;
    }

    public function export_field()
    {
        ?>
        <!-- Product Type -->
        <tr class="form-field form-required simple_import_export_d_none" data-export-type="<?php echo self::$key; ?>">
            <th scope="row">
                <label for="product_type">
                    <span><?php _e('Product Type', 'simple-import-export'); ?></span>
                </label>
            </th>
            <td>
                <?php
                $choices = [
                    [
                        'key' => 'simple',
                        'title' => __('Simple', 'simple-import-export'),
                        'choice' => true,
                    ],
                    [
                        'key' => 'variable',
                        'title' => __('Variable', 'simple-import-export'),
                        'choice' => false
                    ]
                ];
                foreach ($choices as $item) {
                    ?>
                    <div class="checkbox__item" style="margin-bottom: 5px;">
                        <input type="checkbox" name="product_type[]"
                               value="<?php echo $item['key']; ?>" <?php echo($item['choice'] === true ? 'checked' : '') ?>>
                        <span style="padding-left: 10px;"><?php echo $item['title']; ?></span>
                    </div>
                    <?php
                }
                ?>
            </td>
        </tr>

        <!-- Product Status -->
        <tr class="form-field form-required simple_import_export_d_none" data-export-type="<?php echo self::$key; ?>">
            <th scope="row">
                <label for="product_status">
                    <span><?php _e('Product Status', 'simple-import-export'); ?></span>
                </label>
            </th>
            <td>
                <?php
                $choices = [
                    [
                        'key' => 'publish',
                        'title' => __('Publish', 'simple-import-export'),
                        'choice' => true,
                    ],
                    [
                        'key' => 'draft',
                        'title' => __('Draft', 'simple-import-export'),
                        'choice' => false
                    ]
                ];
                foreach ($choices as $item) {
                    ?>
                    <div class="checkbox__item" style="margin-bottom: 5px;">
                        <input type="checkbox" name="product_status[]"
                               value="<?php echo $item['key']; ?>" <?php echo($item['choice'] === true ? 'checked' : '') ?>>
                        <span style="padding-left: 10px;"><?php echo $item['title']; ?></span>
                    </div>
                    <?php
                }
                ?>
            </td>
        </tr>

        <!-- Product Category -->
        <tr class="form-field form-required simple_import_export_d_none" data-export-type="<?php echo self::$key; ?>">
            <th scope="row">
                <label for="product_category">
                    <span><?php _e('Product Category', 'simple-import-export'); ?></span>
                </label>
            </th>
            <td>
                <?php
                $args = array(
                    'option_none_value' => '',
                    'show_option_none' => __('دسته را انتخاب کنید ...', ''),
                    'class' => 'form-select form-control-lg form-select-solid mt-3',
                    'name' => 'product_category',
                    'id' => 'product_category',
                    'value_field' => 'term_id',
                    'taxonomy' => 'product_cat',
                    'selected' => 0,
                    'hierarchical' => true,
                    'hide_if_empty' => false,
                    'show_count' => false,
                    'echo' => 0
                );
                $select = wp_dropdown_categories($args);
                $select = str_replace('<select', '<select data-dropdown-parent="#FilterFormDataTable" data-control="select2"', $select);
                echo $select;
                ?>
            </td>
        </tr>
        <?php
    }

    public function export($data, $type, $extension)
    {
        if ($type != self::$key) {
            return $data;
        }

        // @see https://github.com/woocommerce/woocommerce/wiki/wc_get_products-and-WC_Product_Query
        $args = [
            'limit' => -1
        ];

        // Product Type
        if (isset($_POST['product_type'])) {
            $args['type'] = array_filter($_POST['product_type']);
        }

        // Product Status
        if (isset($_POST['product_status'])) {
            $args['status'] = array_filter($_POST['product_status']);
        }

        // Product Category
        if (!empty($_POST['product_category'])) {
            $args['product_category_id'] = array((int)$_POST['product_category']);
        }

        // Get Products
        $products = wc_get_products($args);
        if (empty($products)) {
            return $data;
        }

        // Setup PHP Array
        $columns = array(
            'id',
            'title',
            'type',
            'parent_id',
            'manage_stock',
            'stock_quantity',
            'regular_price',
            'sale_price',
            'price'
        );

        // Setup List Of Attributes
        foreach (wc_get_attribute_taxonomies() as $taxonomy) {
            $columns[] = trim($taxonomy->attribute_label);
        }

        // Append Column To Data
        $data = [
            $columns
        ];

        // Setup Item
        foreach ($products as $product) {

            // Parent Variable Product and Simple
            if (in_array($product->get_type(), ['simple', 'variable'])) {

                $item = [
                    $product->get_id(),
                    $product->get_name(),
                    $product->get_type(),
                    $product->get_parent_id(),
                    ($product->managing_stock() ? 'yes' : 'no'),
                    $product->get_stock_quantity(),
                    $product->get_regular_price(),
                    $product->get_sale_price(),
                    $product->get_price()
                ];

                foreach (wc_get_attribute_taxonomies() as $taxonomy) {
                    $val = '';
                    if ($product->get_type() == "simple") {
                        foreach ($product->get_attributes() as $product_attributes_slug => $product_attributes_array) {
                            $sanitize_slug = str_ireplace("pa_", "", wc_sanitize_taxonomy_name($product_attributes_slug));
                            if ($sanitize_slug == trim($taxonomy->attribute_name)) {
                                $val = implode(",", self::get_attribute_options($product->get_id(), $product_attributes_array));
                            }
                        }
                    }
                    $item[] = $val;
                }

                // Append
                $data[] = $item;
            }

            // Variable
            if ($product->get_type() == "variable") {
                foreach ($product->get_children() as $children_id) {

                    // Get Children Product
                    $children = wc_get_product($children_id);

                    $item = [
                        $children->get_id(),
                        '',
                        // $children->get_formatted_name(),
                        $children->get_type(),
                        $children->get_parent_id(),
                        ($children->managing_stock() ? 'yes' : 'no'),
                        $children->get_stock_quantity(),
                        $children->get_regular_price(),
                        $children->get_sale_price(),
                        $children->get_price()
                    ];

                    foreach (wc_get_attribute_taxonomies() as $taxonomy) {
                        $val = '';
                        foreach ($children->get_attributes() as $product_attributes_slug => $product_attributes_array) {
                            $sanitize_slug = str_ireplace("pa_", "", wc_sanitize_taxonomy_name($product_attributes_slug));
                            if ($sanitize_slug == trim($taxonomy->attribute_name)) {
                                $val = $children->get_attribute($product_attributes_slug);
                            }
                        }
                        $item[] = $val;
                    }

                    // Append
                    $data[] = $item;
                }
            }
        }

        // Return
        return $data;
    }

    public function import_field()
    {
        ?>
        <tr class="form-field form-required simple_import_export_d_none"
            data-import-type="<?php echo self::$key; ?>">
            <th scope="row">
                <label for="post_status">
                    <span><?php _e('Post Status', 'simple-import-export'); ?></span>
                </label>
            </th>
            <td>
                <select name="post_status">
                    <?php
                    $post_status_list = get_post_stati([], 'objects');
                    foreach ($post_status_list as $post_status) {
                        ?>
                        <option value="<?php echo $post_status->name; ?>">
                            <?php echo $post_status->label; ?> [<?php echo $post_status->name; ?>]
                        </option>
                        <?php
                    }
                    ?>
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

        // Check First Item
        if ($row[0] == "ID") {
            return $return;
        }

        // Check Rows
        if (isset($row[0]) and !empty($row[0]) and is_numeric($row[0])) {

            $post_id = (int)$row[0]; //ID
            $post = get_post($post_id);
            if (is_null($post)) {
                return new \WP_Error('item_import_error', 'پست با شناسه ' . $post_id . ' یافت نشد.');
            }

            $before_post_title = $post->post_title;
            $new_title = trim($row[1]); //Title
            if (!empty($new_title) and $new_title != $before_post_title and $option['input']['post_status'] == $post->post_status) {

                $updated = wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $new_title
                ));
                if (is_wp_error($updated)) {
                    return new \WP_Error('item_import_error', 'خطا در آپدیت پست با شناسه ' . $post_id . ': ' . $updated->get_error_message());
                }
            }
        }

        return $return;
    }

    public static function get_attribute_options($product_id, $attribute)
    {
        if (isset($attribute['is_taxonomy']) && $attribute['is_taxonomy']) {
            return wc_get_product_terms($product_id, $attribute['name'], array('fields' => 'names'));
        } elseif (isset($attribute['value'])) {
            return array_map('trim', explode('|', $attribute['value']));
        }

        return array();
    }
}

new Products();