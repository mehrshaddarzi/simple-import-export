<?php

namespace Simple_Import_Export;

use Shuchkin\SimpleXLSX;

class Admin
{

    public static string $page_slug = 'simple_import_export';

    public function __construct()
    {

        // Admin Menu
        add_action('admin_menu', array($this, 'admin_menu'));

        // Admin Assets
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));

        // Export
        add_action('admin_init', array($this, 'export_handle'));
        add_action('simple_export_handle_file', [$this, 'excel_export'], 20, 3);
        add_action('simple_export_handle_file', [$this, 'json_export'], 25, 3);

        // Import
        add_action('wp_ajax_simple_import_export__import', [$this, 'import_ajax_step_1']);
        add_action('wp_ajax_simple_import_export__import_run', [$this, 'import_ajax_step_2']);
        add_filter('simple_prepare_data_for_import', [$this, 'json_import'], 20, 4);
        add_filter('simple_prepare_data_for_import', [$this, 'excel_import'], 20, 4);
    }

    public function admin_menu()
    {
        $menu = apply_filters('simple_import_export_admin_menu', [
            'menu_title' => __('Import/Export', 'simple-import-export'),
            'page_title' => __('Simple Import / Export', 'simple-import-export'),
            'capability' => 'manage_options',
            'icon' => 'dashicons-database',
            'position' => 90
        ]);

        if (!empty($menu['menu_title'])) {

            add_menu_page(
                $menu['page_title'],
                $menu['menu_title'],
                $menu['capability'],
                self::$page_slug,
                [$this, 'page'],
                $menu['icon'],
                $menu['position']
            );
        }
    }

    public function admin_assets()
    {
        global $pagenow;

        //List Allow This Script
        if ($pagenow == "admin.php") {

            // Get Plugin Version
            $plugin_version = \Simple_Import_Export::$plugin_version;
            if (defined('SCRIPT_DEBUG') and SCRIPT_DEBUG === true) {
                $plugin_version = time();
            }

            wp_enqueue_style(self::$page_slug, \Simple_Import_Export::$plugin_url . '/asset/admin/css/style.css', array(), $plugin_version, 'all');
            wp_enqueue_script(self::$page_slug, \Simple_Import_Export::$plugin_url . '/asset/admin/js/script.js', array('jquery'), $plugin_version, false);
            wp_localize_script(self::$page_slug, self::$page_slug, array(
                'ajax' => admin_url('admin-ajax.php'),
                'loading' => __('Loading ..', 'simple-import-export'),
                'error' => __('Error', 'simple-import-export'),
                'import_error' => __('An error occurred while executing the operation, please try again', 'simple-import-export')
            ));
        }

    }

    public function page()
    {
        include \Simple_Import_Export::$plugin_path . '/templates/page.php';
    }

    /**
     * Define
     */

    public static function get_export_types()
    {
        return apply_filters('simple_import_export_type_lists_at_export', []);
    }

    public static function get_export_extensions()
    {
        return apply_filters('simple_import_export_extensions_lists_at_export', [
            'excel' => 'Excel',
            'json' => 'Json',
        ]);
    }

    public static function get_accept_import_file_format(): array
    {
        $list = [];
        $extensionList = self::get_import_extensions();
        if (isset($extensionList['excel'])) {
            $list[] = '.xlsx';
        }
        if (isset($extensionList['json'])) {
            $list[] = '.json';
        }

        return apply_filters('simple_import_export_accept_import_file_format', $list);
    }

    public static function get_import_types()
    {
        return apply_filters('simple_import_export_type_lists_at_import', []);
    }

    public static function get_import_extensions()
    {
        return apply_filters('simple_import_export_extensions_lists_at_import', [
            'excel' => 'Excel',
            'json' => 'Json',
        ]);
    }

    /**
     * Export
     */

    public function export_handle()
    {
        global $pagenow;

        if (is_admin() and isset($_POST['export_nonce_simple'])
            and wp_verify_nonce($_POST['export_nonce_simple'], 'export_nonce_simple')) {

            // Get Input Data
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

    public function excel_export($data, $type, $extension)
    {
        if ($extension != 'excel') {
            return;
        }

        if (empty($data)) {
            FlashMessage::set(__('List is empty', 'simple-import-export'), 'info');
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

        // Remove Last File
        $day = apply_filters('simple_import_export_max_number_day_archive_file', 1);
        $expire = strtotime("-$day days");
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
        $fileName = current_time('timestamp') . '-' . get_current_user_id() . '.xlsx';
        $path = rtrim($defaultPath, "/") . '/' . ltrim($fileName, "/");

        // Save File in Disk
        $xlsx->saveAs($path);

        // Excel Export
        $createFile = [
            'size' => filesize($path),
            'url' => rtrim($default_link, "/") . '/' . ltrim($fileName, "/")
        ];

        // Show Flush Message
        $text = __('File created', 'simple-import-export');
        $text .= '<br />';
        $text .= __('Number Row', 'simple-import-export') . ': ' . number_format(count($data));
        $text .= '<br />';
        $text .= '<a href="' . $createFile['url'] . '" style="text-decoration: none;color: #00a32a;" download>' . __('Download File', 'simple-import-export') . '</a>';
        FlashMessage::set($text, 'success');
    }

    public function json_export($data, $type, $extension)
    {
        if ($extension != 'json') {
            return;
        }

        if (empty($data)) {
            FlashMessage::set(__('List is empty', 'simple-import-export'), 'info');
            return;
        }

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

        // Remove Last File
        $day = apply_filters('simple_import_export_max_number_day_archive_file', 1);
        $expire = strtotime("-$day days");
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
        $fileName = current_time('timestamp') . '-' . get_current_user_id() . '.json';
        $path = rtrim($defaultPath, "/") . '/' . ltrim($fileName, "/");

        // Save File in Disk
        $createJson = Helper::createJsonFile($path, $data, false);
        if (!$createJson) {

            FlashMessage::set(__('Error in creating json file', 'simple-import-export'), 'error');
            return;
        }

        // Excel Export
        $createFile = [
            'size' => filesize($path),
            'url' => rtrim($default_link, "/") . '/' . ltrim($fileName, "/")
        ];

        // Show Flush Message
        $text = __('File created', 'simple-import-export');
        $text .= '<br />';
        $text .= __('Number Row', 'simple-import-export') . ': ' . number_format(count($data));
        $text .= '<br />';
        $text .= '<a href="' . $createFile['url'] . '" style="text-decoration: none;color: #00a32a;" download>' . __('Download File', 'simple-import-export') . '</a>';
        FlashMessage::set($text, 'success');
    }

    /**
     * Import
     */

    public function import_ajax_step_1()
    {
        if (wp_doing_ajax()) {

            // Check Nonce
            if (!isset($_POST['import_nonce_simple']) || !wp_verify_nonce($_POST['import_nonce_simple'], 'import_nonce_simple')) {
                wp_send_json_error(['message' => __('Security Error!', 'simple-import-excel')], 400);
            }

            // Check Empty Upload File
            if (!isset($_FILES['attachment']) || empty($_FILES['attachment']['name'])) {
                wp_send_json_error(['message' => __('No select any file', 'simple-import-excel')], 400);
            }

            // Get Input Data
            $type = $_POST['type'];
            $extension = $_POST['extension'];
            $per_page = (int)$_POST['per_page'];

            // Attachment Detail
            $attach_filename = $_FILES['attachment']['name'];
            $attach_extension = pathinfo($attach_filename, PATHINFO_EXTENSION);

            // Upload File
            $upload_dir = wp_upload_dir(null, false);
            $target_file = rtrim($upload_dir['basedir'], "/") . '/' . time() . '.' . $attach_extension;
            $upload = move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file);
            if (!$upload) {

                wp_send_json_error(['message' => __('Error in Upload File', 'simple-import-excel')], 400);
                return;
            }

            // Set time Limit Zero
            set_time_limit(0);

            // Setup Data
            $data = apply_filters('simple_prepare_data_for_import', [], $target_file, $type, $extension);

            // Remove file From Server
            @unlink($target_file);

            // Show If Empty
            if (empty($data)) {
                wp_send_json_error(['message' => __('List is empty', 'simple-import-export')], 400);
            }

            // Get Number Item
            $number_item = count($data);

            // Save To Option
            $option_name = 'simple_import_export_user_' . get_current_user_id();
            update_option($option_name, [
                'input' => $_POST,
                'list' => $data,
                'number_process' => 0,
                'per_page' => $per_page
            ], 'no');

            // Create Table Information
            $table = [
                __('Number Row', 'simple-import-export') => $number_item
            ];

            // Setup Message
            $message = '<div data-import-step="1">';
            $message .= '<table class="widefat" style="border: 0 !important;">';
            foreach ($table as $title => $val) {
                $message .= '<tr>';
                $message .= '<td>' . $title . '</td>';
                $message .= '<td>' . $val . '</td>';
                $message .= '</tr>';
            }
            $message .= '</table>';
            $message .= '<p class="submit" style="float: none; margin: 25px 10px 0 10px;"><input type="submit" name="" id="import-button-action" class="button button-primary" value="' . __('Start', 'simple-import-export') . '" style="padding: 6px 50px;"><span class="spinner"></span></p>';
            $message .= '</div>';

            $message .= '<div data-import-step="2" style="display:none;">';
            $message .= '<input type="hidden" id="import_number_all" value="' . $number_item . '">';
            $message .= '<p>';
            $message .= __('Number Row', 'simple-import-export') . ': ';
            $message .= '<span id="import_num_page_process">0</span> / ' . $number_item . '</span>';
            $message .= '</p>';
            $message .= '<p><progress id="import_html_progress" value="0" max="100" style="height: 40px;width: 100%;"></progress></p>';
            $message .= __('Please do not close the browser until the operation is finished', 'simple-import-export');
            $message .= '</div>';

            $message .= '<div data-import-step="3" style="display:none;">';
            $message .= '<p style="text-align: center;background: #fff;padding: 15px;border-radius: 15px;width: 50%;margin: 15px auto;">';
            $message .= __('Done', 'simple-import-export');
            $message .= '</p>';
            $message .= '</div>';

            // Return
            wp_send_json_success(['message' => $message], 200);
        }
        exit;
    }

    public function import_ajax_step_2()
    {
        # Create Default Obj
        $return = [
            'process_status' => 'complete',
            'number_process' => 0,
            'percentage' => 0
        ];

        # Check is Ajax WordPress
        if (wp_doing_ajax()) {

            # Option Name
            $option_name = 'simple_import_export_user_' . get_current_user_id();
            $option = get_option($option_name, ['per_page' => 50, 'list' => []]);

            # Type $ Extension
            $type = $option['input']['type'];
            $extension = $option['input']['extension'];

            # Number Process Per Query
            $number_per_query = $option['per_page'];

            # Check Number Process
            $i = 0;
            $list_option = get_option($option_name);
            $list = $_saved_list = $list_option['list'];
            $new_number_process = $list_option['number_process'] + $number_per_query;
            $items = [];
            foreach ($list as $key => $item) {
                if ($i > $number_per_query) {
                    break;
                }

                // Run Item
                do_action('simple_import_handle_item', $item, $key, $type, $extension, $option);

                // Append To This Process Items
                $items[] = $item;

                // Removed From List
                unset($_saved_list[$key]);

                // Add++
                $i++;
            }

            // Save Option
            $option['number_process'] = $new_number_process;
            $option['list'] = array_values($_saved_list);
            update_option($option_name, $option, 'no');

            # Check End
            if ($_REQUEST['number_all'] > $new_number_process) {
                # Calculate Number Process
                $return['number_process'] = $new_number_process;

                # Calculate Per
                $return['percentage'] = round(($return['number_process'] / $_GET['number_all']) * 100);

                # Set Process
                $return['process_status'] = 'incomplete';

            } else {

                $return['number_process'] = $_REQUEST['number_all'];
                $return['percentage'] = 100;
                delete_option($option_name);

                // After Completed Process
                do_action('simple_import_after_completed_process', $type, $extension, $option);
            }

            # Export Data
            wp_send_json($return);
            exit;
        }
    }

    public function json_import($data, $target_file, $type, $extension)
    {
        if ($extension != 'json') {
            return $data;
        }

        if (!file_exists($target_file)) {
            return $data;
        }

        // Pre Handle
        $pre = apply_filters('pre_simple_prepare_data_for_import_json', null, $target_file);
        if (!is_null($pre)) {
            return $pre;
        }

        $parseJson = Helper::readJson($target_file);
        if ($parseJson['status']) {
            return apply_filters('simple_import_export_prepare_json_file_rows', $parseJson['data']);
        }

        return $data;
    }

    public function excel_import($data, $target_file, $type, $extension)
    {
        if ($extension != 'excel') {
            return $data;
        }

        if (!file_exists($target_file)) {
            return $data;
        }

        // Pre Handle
        $pre = apply_filters('pre_simple_prepare_data_for_import_excel', null, $target_file);
        if (!is_null($pre)) {
            return $pre;
        }

        // include Package
        require_once \Simple_Import_Export::$plugin_path . '/libs/simplexlsx/vendor/autoload.php';
        if ($xlsx = SimpleXLSX::parse($target_file)) {
            return apply_filters('simple_import_export_prepare_excel_file_rows', $xlsx->rows(), $xlsx);
        }
        /*else{
            echo SimpleXLSX::parseError();
        }*/

        return $data;
    }

}

new Admin();
