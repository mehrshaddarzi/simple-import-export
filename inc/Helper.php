<?php

namespace Simple_Import_Export;


class Helper
{

    public function __construct()
    {

    }

    public static function admin_notice($text, $model = "info", $close_button = true, $echo = true, $style_extra = 'padding:12px;')
    {
        $text = '
        <div class="notice notice-' . $model . '' . ($close_button === true ? " is-dismissible" : "") . '">
           <div style="' . $style_extra . '">' . $text . '</div>
        </div>
        ';
        if ($echo) {
            echo $text;
        } else {
            return $text;
        }
    }

    public static function inline_admin_notice($type = 'error', $message = '', $args = [], $priority = 999)
    {
        add_action('admin_notices', function () use ($type, $message, $args) {
            self::admin_notice($message, $type);
            $_SERVER['REQUEST_URI'] = remove_query_arg(array_merge(['_alert_type', '_alert'], $args));
        }, $priority);
    }

    public static function to_gregorian($_date, $explode = "-")
    {
        return ParsiDate::to_gregorian("Y-m-d", str_ireplace($explode, "-", $_date));
    }

    public static function per_number($number)
    {
        if (function_exists('per_number')) {
            return per_number($number);
        } else {
            return str_replace(
                range(0, 9),
                array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'),
                $number
            );
        }
    }

    public static function eng_number($number)
    {
        if (function_exists('eng_number')) {
            return eng_number($number);
        }

        return str_replace(
            array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'),
            range(0, 9),
            $number
        );
    }

    public static function wc_get_available_payment_list($enabled = true, $suppress_filter = false)
    {
        // Suppress filter
        if ($suppress_filter) {
            remove_all_filters('woocommerce_available_payment_gateways');
        }

        // Get List
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $enabled_gateways = array();
        if ($gateways) {
            foreach ($gateways as $gateway_key => $gateway) {
                if ($gateway->enabled == 'yes') {

                    // Get Order
                    $order = (array)get_option('woocommerce_gateway_order');

                    // Setting
                    $settings = array();
                    $gateway->init_form_fields();
                    foreach ($gateway->form_fields as $id => $field) {

                        // Make sure we at least have a title and type.
                        if (empty($field['title']) || empty($field['type'])) {
                            continue;
                        }
                        // Ignore 'title' settings/fields -- they are UI only.
                        if ('title' === $field['type']) {
                            continue;
                        }
                        // Ignore 'enabled' and 'description' which get included elsewhere.
                        if (in_array($id, array('enabled', 'description'), true)) {
                            continue;
                        }
                        $data = array(
                            'id' => $id,
                            'label' => empty($field['label']) ? $field['title'] : $field['label'],
                            'description' => empty($field['description']) ? '' : $field['description'],
                            'type' => $field['type'],
                            'value' => empty($gateway->settings[$id]) ? '' : $gateway->settings[$id],
                            'default' => empty($field['default']) ? '' : $field['default'],
                            'tip' => empty($field['description']) ? '' : $field['description'],
                            'placeholder' => empty($field['placeholder']) ? '' : $field['placeholder'],
                        );
                        if (!empty($field['options'])) {
                            $data['options'] = $field['options'];
                        }
                        $settings[$id] = $data;
                    }

                    // Prepare Item
                    $item = array(
                        'id' => $gateway->id,
                        'title' => $gateway->title,
                        'description' => $gateway->description,
                        'order' => $order[$gateway->id] ?? '',
                        'enabled' => ('yes' === $gateway->enabled),
                        'method_title' => $gateway->get_method_title(),
                        'method_description' => $gateway->get_method_description(),
                        'settings' => $settings,
                    );

                    $enabled_gateways[$gateway_key] = $item;
                }
            }
        }

        if ($enabled) {
            return $enabled_gateways; // Return Only enabled in Setting
        }
        return $gateways; // Return All
    }

    public static function wp_get_sanitize_mobile($mobile)
    {

        // Convert To English
        $mobile = self::eng_number($mobile);

        // Get Only 10 Character From Last for example +9898980[911129228]
        $mobile = substr($mobile, -10);

        // Convert Plus To 00
        $mobile = (int)str_ireplace('+', '00', $mobile);

        // Get Only Numeric
        $mobile = preg_replace('/[^0-9]/', '', $mobile);

        // Check Character Mobile
        $forth_character = substr($mobile, 0, 4);
        if ($forth_character == "0098") {
            $mobile = substr($mobile, 4);
        }

        $twice_character = substr($mobile, 0, 2);
        if ($twice_character == "98") {
            $mobile = substr($mobile, 2);
        }

        $first_character = substr($mobile, 0, 1);
        if ($first_character == "9") {
            $mobile = '0' . $mobile;
        }

        return $mobile;
    }

    public static function wc_get_order_date($order_id)
    {
        // Get Order Post Data
        $post = get_post($order_id);
        $order_date = $post->post_date;
        $explode = explode(' ', $order_date);
        $date = $explode[0];
        $explode_date = explode("-", $date);
        $time = $explode[1];
        if ($explode_date[0] < 2000) {

            // Convert Jalali To Gregorian
            $gregDate = ParsiDate::to_gregorian("Y-m-d", $date);
            $order_date = $gregDate . ' ' . $time;
        }

        return $order_date;
    }

}

new Helper();
