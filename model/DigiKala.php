<?php

namespace Simple_Import_Export\Model;

class DigiKala
{

    public static string $key = 'digikala';

    public function __construct()
    {
        // راهنما
        // خروجی اکسل از پنل فروشندگان گرفته و ستون کد محصولات و کد متغیر و موجودی و قیمت را کپی می کنیم در یک اکسل دیگر سپس آن را ایمپورت میکنیم

        // Import
        add_filter('simple_import_export_type_lists_at_import', [$this, 'method']);
        #add_action('simple_import_export_form_fields_import', [$this, 'import_field']);
        add_filter('simple_import_export_prepare_excel_file_rows', [$this, 'simple_import_export_prepare_excel_file_rows'], 20, 3);
        add_filter('simple_import_handle_item', [$this, 'import_row'], 10, 6);
        apply_filters('simple_import_use_array_values_saved', function ($return, $type) {
            if ($type == self::$key) {
                return false;
            }

            return $return;
        });

        // Check Public API
        add_action('init', [$this, 'init']);
    }

    public function method($array)
    {
        // Check Exist WooCommerce
        if (!class_exists('\woocommerce')) {
            return $array;
        }

        $array[self::$key] = __('DigiKala Products', 'simple-import-export');
        return $array;
    }

    public function simple_import_export_prepare_excel_file_rows($array, $xlsx, $type)
    {
        if ($type != self::$key) {
            return $array;
        }

        if ($array[0][0] != "product_id") {
            return new \WP_Error('item_import_error', 'فرمت اکسل اشتباه می باشد');
        }

        $list = [];
        foreach ($array as $row) {

            if (is_numeric($row[0])) {

                // Check is Active Product
                if (strtolower($row[4]) != "1") {
                    continue;
                }

                $product_id = $row[0];
                if (!isset($list[$product_id])) {

                    $list[$product_id] = [
                        'product_id' => $product_id,
                        'children' => []
                    ];
                }

                $list[$product_id]['children'][] = [
                    'variable_id' => $row[1],
                    'price' => $row[2],
                    'stock' => $row[3],
                ];
            }
        }

        return $list;
    }

    public function import_row($return, $row, $key, $type, $extension, $option)
    {
        if ($type != self::$key) {
            return $return;
        }

        // $row is product ID
        $digiKala_productID = $row['product_id'];

        // Check First Item
        if (!is_numeric($digiKala_productID)) {
            return new \WP_Error('item_import_error', 'شناسه محصول دیجی کالا ' . $digiKala_productID . ' اشتباه می باشد');
        }

        /**
         * Check Before Saved in WooCommerce Product `meta` => digikala_product_id
         */

        $WC_Query = new \WP_Query(array(
            'post_type' => 'product',
            'post_status' => ['draft', 'publish'],
            'posts_per_page' => 1,
            'order' => 'ASC',
            'fields' => 'ids',
            'cache_results' => false,
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters' => true,
            'meta_query' => [
                [
                    'key' => 'digikala_product_id',
                    'compare' => '=',
                    'value' => $digiKala_productID
                ]
            ]
        ));
        if (!empty($query->posts) and is_array($query->posts)) {
            return new \WP_Error('item_import_error', 'شناسه محصول دیجی کالا ' . $digiKala_productID . ' قبلا در ووکامرس به شناسه ' . $query->posts[0] . ' ذخیره شده است.');
        }

        /**
         * Get From APi
         */

        // Get From API
        $data = self::getProductInfoFromPublicAPI($digiKala_productID);
        if ($data['status'] === false) {
            return new \WP_Error('item_import_error_curl', $data['message']);
        }

        // Get Object
        $object = $data['object'];

        /**
         * Get and Save Image
         */

        // Check Thumbnail Images
        $image_id = null;
        $digikalaImageUrl = $object['image'];
        if (!empty($digikalaImageUrl)) {

            $getImage = self::addImageToAttachment($digikalaImageUrl);
            if ($getImage['status'] === true) {
                $image_id = $getImage['attachment_id'];
            } else {
                return new \WP_Error('item_import_error_thumbnail', 'شناسه محصول دیجی کالا ' . $digiKala_productID . ' خطا در دریافت عکس اصلی محصول دارد به آدرس ' . $digikalaImageUrl);
            }
        }

        // Check Gallery Images
        $gallery_ids = [];
        $digiKalaImages = $object['gallery'];
        foreach ($digiKalaImages as $imgUrl) {
            $getImage = self::addImageToAttachment($imgUrl);
            if ($getImage['status'] === true) {
                $gallery_ids[] = $getImage['attachment_id'];
            } else {
                return new \WP_Error('item_import_error_gallery', 'شناسه محصول دیجی کالا ' . $digiKala_productID . ' خطا در دریافت عکس گالری محصول دارد به آدرس ' . $imgUrl);
            }
        }

        /**
         * Create DigiKala Product
         */

        // Setup Variable Product
        try {

            $product = new \WC_Product_Variable();
            $product->set_name($object['title']);
            $product->set_sku($object['id']);
            $product->set_status('draft');
            $product->set_description($object['review']);
            if (!empty($image_id)) {
                $product->set_image_id($image_id);
            }
            if (!empty($gallery_ids)) {
                $product->set_gallery_image_ids($gallery_ids);
            }
            if (!empty($object['category'])) {

                $category_ids = [];
                $term = self::get_term_by_name('product_cat', $object['category']);
                if ($term['status'] === true and isset($term['term_id'])) {
                    $category_ids[] = (int)$term['term_id'];
                } else {
                    $create = self::create_term('product_cat', $object['category'], ['description' => ''], []);
                    if ($create['status'] === true and isset($create['term_id'])) {
                        $category_ids[] = (int)$create['term_id'];
                    } else {
                        return new \WP_Error('item_import_error_gallery', 'خطا در ایجاد دسته بندی با نام ' . $object['category'] . ': ' . $create['message']);
                    }
                }

                $product->set_category_ids($category_ids);
            }
            if (!empty($object['variants'])) {

                // Setup digiKala Attributes
                $digikala_attributes = [];

                // Setup Size Variation attributes
                $sizes = [];
                foreach ($object['variants'] as $variant) {
                    if (isset($variant['size']['title']) and !in_array($variant['size']['title'], $sizes)) {
                        $sizes[] = strtoupper($variant['size']['title']);
                    }
                }

                // Check Empty
                if (empty($sizes)) {
                    return new \WP_Error('item_import_error', 'شناسه محصول دیجی کالا ' . $digiKala_productID . ' هیچ متغیر سایزی ندارد');
                }

                // Set size Attribute
                $digikala_attributes[] = [
                    'position' => 1,
                    'visible' => 1,
                    'variation' => true,
                    'is_tax' => true,
                    'label' => 'سایز',
                    'slug' => 'size',
                    'options' => $sizes
                ];

                // Setup Specifications attributes
                if (isset($object['specifications'][0]['attributes']) and is_array($object['specifications'][0]['attributes'])) {
                    foreach ($object['specifications'][0]['attributes'] as $attrItem) {

                        if ($attrItem['title'] == "سایز") {
                            continue;
                        }

                        $digikala_attributes[] = [
                            'position' => 1,
                            'visible' => 1,
                            'variation' => false,
                            'is_tax' => false,
                            'label' => $attrItem['title'],
                            'options' => array_map('trim', $attrItem['values'])
                        ];
                    }
                }

                // Setup
                $attributes = [];
                $attributes_taxonomy = self::get_attribute_taxonomies();
                foreach ($digikala_attributes as $array) {

                    $attribute = new \WC_Product_Attribute();
                    $attribute->set_position($array['position']);
                    $attribute->set_visible($array['visible']);
                    $attribute->set_variation($array['variation']);

                    if ($array['is_tax'] === true) { # Always use for is_tax

                        // check taxonomy exists
                        $tax_attribute = null;
                        foreach ($attributes_taxonomy as $tax) {
                            if (isset($tax->attribute_label) and $tax->attribute_label == $array['label']) {
                                $tax_attribute = [
                                    'id' => $tax->attribute_id,
                                    'label' => $tax->attribute_label,
                                    'name' => wc_attribute_taxonomy_name($tax->attribute_name)
                                ];
                                break;
                            }
                        }

                        // if not exists taxonomy attributes then created
                        if (is_null($tax_attribute)) {

                            $create_attribute_taxonomy = self::wc_create_taxonomy_attribute([
                                'name' => trim($array['label']),
                                'slug' => $array['slug']
                            ]);
                            if ($create_attribute_taxonomy['status'] === false) {
                                return new \WP_Error('item_import_error_term', 'شناسه محصول دیجی کالا ' . $digiKala_productID . ' ' . 'خطا در زمان ایجاد ویژگی ' . $array['label'] . ' رخ داده است. متن خطا: ' . $create_attribute_taxonomy['message']);
                            }

                            $tax_attribute = [
                                'id' => $create_attribute_taxonomy['id'],
                                'label' => $create_attribute_taxonomy['label'],
                                'name' => $create_attribute_taxonomy['slug']
                            ];
                        }

                        // set taxonomy
                        $attribute->set_id($tax_attribute['id']); // Or wc_attribute_taxonomy_id_by_name($array['name'])
                        $attribute->set_name($tax_attribute['name']);

                        // set options
                        $options_ids = [];
                        foreach ($array['options'] as $term) {

                            $get_term = self::get_term_by_name($tax_attribute['name'], $term);
                            if ($get_term['status'] === true and isset($get_term['term_id'])) {

                                $options_ids[] = (int)$get_term['term_id'];
                            } else {

                                $create = self::create_term($tax_attribute['name'], $term, ['description' => ''], []);
                                if ($create['status'] === true and isset($create['term_id'])) {
                                    $options_ids[] = (int)$create['term_id'];
                                } else {
                                    return new \WP_Error('item_import_error_term', 'شناسه محصول دیجی کالا ' . $digiKala_productID . ' ' . 'خطا در ایجاد آیتم ویژگی ' . $array['label'] . ' با نام ' . $term . ': ' . $create['message']);
                                }
                            }
                        }
                        $attribute->set_options($options_ids);

                    } else {

                        $attribute->set_name($array['label']);
                        $attribute->set_options($array['options']);
                    }

                    $attributes[] = $attribute;
                }

                $product->set_attributes($attributes);
            }

            // Save Product
            $product->save();

        } catch (\Exception $e) {

            return new \WP_Error('item_import_error_product', 'شناسه محصول دیجی کالا ' . $digiKala_productID . ' خطا در زمان ایجاد محصول ' . $e->getMessage());
        }

        // Setup Variations
        if (!empty($object['variants'])) {
            foreach ($object['variants'] as $variant) {

                try {

                    $variation = new \WC_Product_Variation();
                    $variation->set_parent_id($product->get_id());
                    $variation->set_sku($variant['id']);

                    $regular_price = ($variant['price']['selling_price'] ?? 0);
                    $stock = 0;
                    foreach ($row['children'] as $child) {

                        if (isset($child['variable_id']) and $child['variable_id'] == $variant['id']) {
                            $regular_price = $child['price'];
                            $stock = $child['stock'];
                        }

                    }
                    $variation->set_regular_price(($regular_price > 0 ? ($regular_price / 10) : 0));
                    $variation->set_manage_stock(true);
                    $variation->set_stock_quantity($stock);
                    $variation->set_backorders(false);

                    if (!empty($variant['size']['title'])) {

                        $attributes = []; /* Example: ['pa_color' => $term->slug, 'pa_age' => $term->slug]  */
                        $attributes_taxonomy = self::get_attribute_taxonomies();

                        // Setup Variant digiKala Attribute
                        $digikala_variant_attributes = [];
                        $digikala_variant_attributes[] = [
                            'position' => 1,
                            'visible' => 1,
                            'variation' => true,
                            'is_tax' => true,
                            'label' => 'سایز',
                            'slug' => 'size',
                            'options' => [
                                strtoupper($variant['size']['title'])
                            ]
                        ];
                        foreach ($digikala_variant_attributes as $array) {

                            if ($array['is_tax'] === false) {
                                continue;
                            }

                            // get taxonomy name by label from this website because taxonomy slug not equal between two site
                            $tax_attribute = null;
                            foreach ($attributes_taxonomy as $tax) {
                                if (isset($tax->attribute_label) and $tax->attribute_label == $array['label']) {
                                    $tax_attribute = [
                                        'id' => $tax->attribute_id,
                                        'label' => $tax->attribute_label,
                                        'name' => wc_attribute_taxonomy_name($tax->attribute_name)
                                    ];
                                    break;
                                }
                            }
                            if (is_null($tax_attribute)) {
                                continue;
                            }

                            // final taxonomy name
                            $taxonomy_name = $tax_attribute['name'];

                            // set options
                            $options_slug = null;
                            foreach ($array['options'] as $term) {

                                $get_term = self::get_term_by_name($taxonomy_name, $term);
                                if ($get_term['status'] === true and isset($get_term['term_id'])) {

                                    $options_slug = $get_term['slug'];
                                } else {

                                    $create = self::create_term($taxonomy_name, $term, ['description' => ''], []);
                                    if ($create['status'] === true and isset($create['term_id'])) {
                                        $options_slug = $create['slug'];
                                    } else {
                                        return new \WP_Error('item_import_error_term', 'شناسه محصول دیجی کالا ' . $digiKala_productID . ' ' . 'خطا در ایجاد آیتم ویژگی ' . $array['label'] . ' با نام ' . $term['name'] . ': ' . $create['message']);
                                    }
                                }
                            }

                            if (!is_null($options_slug)) {

                                // WooCommerce Set sanitize_title variation attribute when store post meta
                                // @see https://wp-kama.com/filecode/woocommerce/includes/wc-attribute-functions.php#L148
                                $attributes[sanitize_title($taxonomy_name)] = $options_slug;
                            }
                        }

                        $variation->set_attributes($attributes);
                    }

                    $wc_variation_id = $variation->save();

                } catch (\Exception $e) {

                    return new \WP_Error('item_import_error_product', 'شناسه محصول فرزند دیجی کالا ' . $variant['id'] . ' خطا در زمان ایجاد محصول ' . $e->getMessage());
                }
            }
        }

        // Set Comments
        if (!empty($object['comments'])) {
            foreach ($object['comments'] as $comment) {

                $commentID = self::create_product_review(
                    $product->get_id(),
                    [
                        'date' => trim($comment['created_at']),
                        'author' => trim($comment['user_name']),
                        'content' => trim($comment['body']),
                        'rating' => (int)$comment['rate'],
                    ]
                );
            }
        }

        // Set product_type terms
        if (!empty($object['variants'])) {
            wp_set_object_terms($product->get_id(), 'variable', 'product_type');
        }

        // Set DigiKala Post ID
        update_post_meta($product->get_id(), 'digikala_product_id', $digiKala_productID);

        // Set Brands
        if (!empty($object['brand'])) {

            $brand_ids = [];
            $term = self::get_term_by_name('brands', $object['brand']);
            if ($term['status'] === true and isset($term['term_id'])) {
                $brand_ids[] = (int)$term['term_id'];
            } else {
                $create = self::create_term('brands', $object['brand'], ['description' => ''], []);
                if ($create['status'] === true and isset($create['term_id'])) {
                    $brand_ids[] = (int)$create['term_id'];
                } else {
                    return new \WP_Error('item_import_error_gallery', 'خطا در ایجاد دسته بندی برند با نام ' . $object['brand'] . ': ' . $create['message']);
                }
            }

            wp_set_object_terms($product->get_id(), $brand_ids, 'brands');
        }

        // Return
        return $return;
    }

    /**
     * Helper Methods
     */

    public function init()
    {
        if (isset($_GET['_digikala_product_id'])) {

            $get = self::getProductInfoFromPublicAPI($_GET['_digikala_product_id']);
            echo '<pre>';
            var_dump($get);
            echo '</pre>';
            exit;
        }
    }

    public static function getProductInfoFromPublicAPI($dkp): array
    {
        // Setup Url
        $url = 'https://api.digikala.com/v2/product/' . $dkp . '/'; # Ex: 8184 or 3958725

        // Request
        $request = wp_remote_request(
            $url,
            [
                'method' => 'GET',
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Accept' => 'application/json'
                ],
                'sslverify' => false
            ]
        );

        // Check Connect Error
        if (is_wp_error($request)) {
            return array(
                'status' => false,
                'message' => $request->get_error_message()
            );
        }

        // Get Body
        $body = wp_remote_retrieve_body($request);
        $json = json_decode($body, true);

        // Check Not Found
        if (isset($json['status']) and $json['status'] == "404") {

            return array(
                'status' => false,
                'code' => 404,
                'message' => 'کالا با این شناسه در دیجی کالا یافت نشد'
            );
        }

        // Get Json Data
        $data = $json['data']['product'];

        // Setup Object
        $object = [
            'id' => $data['id'],
            'title' => $data['title_fa'],
            'image' => ($data['images']['main']['url'][0] ?? ''),
            'gallery' => [],
            'category' => ($data['category']['title_fa'] ?? ''),
            'brand' => ($data['brand']['title_fa'] ?? ''),
            /**
             * "id": 67733497,
             * "size": {
             * "id": 100175,
             * "title": "S"
             * },
             * "price": {
             * "selling_price": 8900000,
             * "rrp_price": 8900000,
             * "order_limit": 20,
             * "is_incredible": false,
             * "is_promotion": false,
             * "is_locked_for_digiplus": false,
             * "bnpl_active": false,
             * "discount_percent": 0,
             * "is_plus_early_access": false
             * },
             */
            'variants' => ($data['variants'] ?? []),
            /**
             * {
             * "title": "مشخصات",
             * "attributes": [
             * {
             * "title": "جنس",
             * "values": [
             * "الیاف طبیعی ",
             * "پنبه یکرو "
             * ]
             * },
             * }
             */
            'specifications' => ($data['specifications'] ?? []),
            'review' => ($data['review']['description'] ?? ''),
            'comments' => ($data['last_comments'] ?? [])
        ];

        // Set Gallery
        if (isset($data['images']['list']) and !empty($data['images']['list'])) {
            foreach ($data['images']['list'] as $galleryImage) {
                if (!empty($galleryImage['url'][0])) {
                    $object['gallery'][] = self::getImageUrl($galleryImage['url'][0]);
                }
            }
        }

        // Return
        return [
            'status' => true,
            'object' => $object,
            # 'raw' => $json,
            'code' => $request['response']['code']
        ];
    }

    public static function getImageUrl($url = ''): string
    {
        $parse = wp_parse_url($url);
        return $parse['scheme'] . '://' . $parse['host'] . $parse['path'];
    }

    public static function addImageToAttachment($url = '', $desc = ''): array
    {
        $fullImageUrl = self::getImageUrl($url);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        // Check Before Exist with source_url
        $query = new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'source_url',
                    'value' => $fullImageUrl,
                    'compare' => 'LIKE',
                ]
            ]
        ]);
        if (!empty($query->posts)) {

            return array(
                'status' => true,
                'attachment_id' => (int)$query->posts[0]
            );
        }

        // Download Image
        $tmp = download_url($fullImageUrl);
        if (is_wp_error($tmp)) {

            return array(
                'status' => false,
                'message' => 'دانلود عکس محصول با موفقیت انجام نشد',
                'error' => $tmp->get_error_message()
            );
        }

        $file_array = array(
            'name' => basename($fullImageUrl),
            'tmp_name' => $tmp
        );
        $post_id = '0';
        $attachment_id = media_handle_sideload($file_array, $post_id, $desc);
        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']);
            return array('status' => false, 'message' => 'دانلود عکس محصول با موفقیت انجام نشد', 'error' => $attachment_id->get_error_message());
        }

        // Remove tmp File
        @unlink($file_array['tmp_name']);

        // Set Attachment
        update_post_meta($attachment_id, 'source_url', $fullImageUrl);

        // Return Attachment ID
        return array(
            'status' => true,
            'attachment_id' => $attachment_id
        );
    }

    public static function get_term_by_name($taxonomy, $name): array
    {
        $terms = get_terms([
            'taxonomy' => $taxonomy, # wc_attribute_taxonomy_name($attribute_name) pa_'color'
            'hide_empty' => false,
            'name' => trim($name), // We Need Only Equal
        ]);
        if (count($terms) > 0) {

            return [
                'status' => true,
                'term_id' => $terms[0]->term_id,
                'name' => $terms[0]->name,
                'slug' => $terms[0]->slug
            ];
        }

        return ['status' => false];
    }

    public static function create_term($taxonomy, $name, $args = [], $meta_input = []): array
    {
        $term_data = wp_insert_term(
            $name,
            $taxonomy,
            /**
             * description
             * parent
             * slug
             */
            $args
        );
        if (is_wp_error($term_data)) {

            return [
                'status' => false,
                'message' => $term_data->get_error_message()
            ];
        }

        if (!empty($meta_input)) {
            foreach ($meta_input as $meta_name => $meta_value) {
                update_term_meta($term_data['term_id'], $meta_name, $meta_value);
            }
        }

        $term = get_term($term_data['term_id']);
        return [
            'status' => true,
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug
        ];
    }

    public static function get_attribute_taxonomies()
    {
        /**
         * MultiArray:
         *
         * object(stdClass)#12388 (6) {
         * ["attribute_id"]=>
         * string(1) "1"
         * ["attribute_name"]=> {wc_attribute_taxonomy_name($attribute_name)}
         * string(4) "size"
         * ["attribute_label"]=>
         * string(8) "سایز"
         * ["attribute_type"]=>
         * string(6) "select"
         * ["attribute_orderby"]=>
         * string(10) "menu_order"
         * ["attribute_public"]=>
         * int(0)
         * }
         */
        return wc_get_attribute_taxonomies();
    }

    public static function get_attribute($id)
    {
        // @see https://wp-kama.com/plugin/woocommerce/function/wc_get_attribute
        return wc_get_attribute($id);
    }

    public static function wc_get_formatted_name($variation): string
    {
        // https://wp-kama.com/plugin/woocommerce/function/WC_Product_Variation::get_formatted_name
        # $formatted_variation_list = wc_get_formatted_variation($variation, true, true, true);
        return $variation->get_name();
        /* $attributes = self::get_attributes($variation);
         if (!empty($attributes)) {
             foreach ($attributes as $attr) {
                 $label = str_ireplace([":"], "", $attr['label']);
                 $title .= " | " . trim($label) . ': ' . implode(", ", wp_list_pluck($attr['options'], "name"));
             }
         }
        return $title;*/
    }

    public static function wc_create_taxonomy_attribute($data): array
    {
        global $wpdb;

        # Get From File API
        # woocommerce/includes/legacy/api/v3/class-wc-api-products.php
        try {

            if (!isset($data['name'])) {
                $data['name'] = '';
            }

            // Set the attribute slug.
            if (!isset($data['slug'])) {
                $data['slug'] = wc_sanitize_taxonomy_name(stripslashes($data['name']));
            } else {
                $data['slug'] = preg_replace('/^pa\_/', '', wc_sanitize_taxonomy_name(stripslashes($data['slug'])));
            }

            // Set attribute type when not sent.
            if (!isset($data['type'])) {
                $data['type'] = 'select';
            }

            // Set order by when not sent.
            if (!isset($data['order_by'])) {
                $data['order_by'] = 'menu_order';
            }

            $insert = $wpdb->insert(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                array(
                    'attribute_label' => $data['name'],
                    'attribute_name' => $data['slug'],
                    'attribute_type' => $data['type'],
                    'attribute_orderby' => $data['order_by'],
                    'attribute_public' => isset($data['has_archives']) && true === $data['has_archives'] ? 1 : 0,
                ),
                array('%s', '%s', '%s', '%s', '%d')
            );

            // Checks for an error in the product creation.
            if (is_wp_error($insert)) {
                return ['status' => false, 'message' => $insert->get_error_message()];
            }

            $id = $wpdb->insert_id;
            do_action('woocommerce_api_create_product_attribute', $id, $data);

            // Clear transients.
            wp_schedule_single_event(time(), 'woocommerce_flush_rewrite_rules');
            delete_transient('wc_attribute_taxonomies');
            \WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');

            // Set in Global in Same Request For Error invalid_taxonomy
            $taxonomy_name = wc_attribute_taxonomy_name($data['slug']);
            register_taxonomy(
                $taxonomy_name,
                apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product'))
            );

            // Return
            return [
                'status' => true,
                'label' => $data['name'],
                'slug' => $taxonomy_name,
                'id' => $id
            ];
        } catch (\WC_API_Exception $e) {

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public static function get_review_date($date_to_insert)
    {
        require_once \Simple_Import_Export::$plugin_path . '/model/digikala/jdf.php';
        // $date_to_insert is: "14 تیر 1404"

        // تشخیص اینکه تاریخ شمسی است
        if (preg_match('/[^\x00-\x7F]/', $date_to_insert)) {

            // 1. تجزیه رشته تاریخ شمسی
            $parts = explode(' ', $date_to_insert);
            $day = (int)$parts[0];
            $month_name = $parts[1];
            $year = (int)$parts[2];

            $persian_months_map = [
                'فروردین' => 1, 'اردیبهشت' => 2, 'خرداد' => 3, 'تیر' => 4, 'مرداد' => 5, 'شهریور' => 6,
                'مهر' => 7, 'آبان' => 8, 'آذر' => 9, 'دی' => 10, 'بهمن' => 11, 'اسفند' => 12
            ];
            $month = $persian_months_map[$month_name];

            // 2. ✅ استفاده از تابع کتابخانه برای تبدیل
            $gregorian_array = jalali_to_gregorian($year, $month, $day);
            $gregorian_string = sprintf('%04d-%02d-%02d', $gregorian_array[0], $gregorian_array[1], $gregorian_array[2]);

            // 3. تبدیل آرایه به رشته تاریخ و زمان
            $comment_date = $gregorian_string . ' 10:00:00'; // یک زمان دلخواه اضافه می‌کنیم

        } else {

            // اگر فرمت میلادی بود، همان را استفاده کن
            $comment_date = date('Y-m-d H:i:s', strtotime($date_to_insert));
        }

        return $comment_date;
    }

    public static function create_product_review($product_id, $review_data)
    {
        $date_to_insert = $review_data['date'] ?? null;
        $comment_date = current_time('mysql'); // مقدار پیش‌فرض
        if (!empty($date_to_insert)) {
            $comment_date = self::get_review_date($date_to_insert);
        }

        $comment_data = [
            'comment_post_ID' => $product_id,
            'comment_author' => $review_data['author'],
            'comment_author_email' => '',
            'comment_content' => $review_data['content'],
            'comment_type' => 'review',
            'comment_approved' => 1,
            'comment_date' => $comment_date,
            'comment_date_gmt' => get_gmt_from_date($comment_date),
            'comment_meta' => ['rating' => $review_data['rating']],
        ];

        $comment_id = wp_insert_comment($comment_data);
        return $comment_id;
    }

    public function get_product_size(string $title): string
    {
        // الگویی برای پیدا کردن سایزهای مختلف
        // این الگو سایزهای استاندارد (XS, S, M, L), سایزهای X دار (XL, XXL) و سایزهای عددی (2XL, 3XL) را پیدا می‌کند
        $pattern = '/\b(XS|S|M|L|XL|XXL|XXXL|[2-9]XL)\b/i';

        if (preg_match($pattern, $title, $matches)) {
            // $matches[0] حاوی سایز پیدا شده است
            return strtoupper($matches[0]); // برگرداندن سایز با حروف بزرگ
        }

        return 'نامشخص'; // اگر هیچ سایزی در عنوان پیدا نشد
    }
}

new DigiKala();