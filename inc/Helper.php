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

    public static function readJson($path): array
    {
        if (!file_exists($path)) {
            return array('status' => false, 'message' => 'file not found');
        }
        $string = file_get_contents($path);
        $array = json_decode($string, true);
        if ($array === null) {
            return array('status' => false, 'message' => 'problem parse json file');
        }

        return ['status' => true, 'data' => $array];
    }

    public static function createJsonFile($file_path, $array, $JSON_PRETTY = false): bool
    {
        //Prepare Data
        if ($JSON_PRETTY) {
            $data = json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        } else {
            $data = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        }

        //Save To File
        if (self::file_put_content($file_path, $data)) {
            return true;
        }

        return false;
    }

    public static function file_put_content($file_path, $data)
    {
        try {
            $isInFolder = preg_match("/^(.*)\/([^\/]+)$/", $file_path, $file_path_match);
            if ($isInFolder) {
                $folderName = $file_path_match[1];
                if (!is_dir($folderName)) {
                    // Create Folder
                    if (!@mkdir($folderName, 0777, true)) {
                        $mkdirErrorArray = error_get_last();
                        return array('status' => false, 'message' => 'Cannot create directory. ' . $mkdirErrorArray['message']);
                    }
                }
            }
            // File Put Content
            file_put_contents($file_path, $data);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function get_post($post_id)
    {
        // Get Post
        $post = get_post($post_id, ARRAY_A);
        if (is_null($post)) {
            return [];
        }

        // Get Meta
        $post['meta'] = array_map(function ($a) {
            return maybe_unserialize($a[0]);
        }, get_post_meta($post_id));

        // Terms
        $taxonomies = get_taxonomies([
            'object_type' => [
                $post['post_type']
            ]
        ]);
        $post['terms'] = [];
        foreach ($taxonomies as $taxonomy) {
            $post['terms'][$taxonomy] = wp_get_post_terms($post_id, $taxonomy, array(['fields' => 'all']));
        }

        // Return
        return apply_filters('simple_import_export_post_data', $post, $post_id);
    }

    public static function get_user($user_id): array
    {
        # Get User Data
        $user_data = get_userdata($user_id);
        $user_info = get_object_vars($user_data->data);

        # Get User roles
        $user_info['role'] = $user_data->roles;

        # Get User Caps
        $user_info['cap'] = $user_data->caps;

        # Get User Meta
        $user_info['meta'] = array_map(function ($a) {
            return maybe_unserialize($a[0]);
        }, get_user_meta($user_id));

        # Return
        return apply_filters('simple_import_export_user_data', $user_info, $user_id);
    }

}

new Helper();
