<?php
/**
 * Plugin Name: Simple Import Export Data
 * Description: Simple Import/Export Data at WordPress
 * Plugin URI:  https://realwp.net
 * Version:     1.5.1
 * Author:      Mehrshad Darzi
 * Author URI:  https://realwp.net
 * License:     MIT
 * Text Domain: simple-import-export
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Simple_Import_Export
{
    /**
     * Use Template Engine
     * if you want use template Engine Please add dir name
     *
     * @var string / dir name
     * @status Core
     */
    public static $Template_Engine = 'simple-import-export';

    /**
     * Minimum PHP version required
     *
     * @var string
     */
    private $min_php = '7.4.0';

    /**
     * Use plugin's translated strings
     *
     * @var string
     * @default true
     */
    public static $use_i18n = true;

    /**
     * List Of Class
     * @var array
     */
    public static $providers = array();

    /**
     * URL to this plugin's directory.
     *
     * @type string
     * @status Core
     */
    public static $plugin_url;

    /**
     * Path to this plugin's directory.
     *
     * @type string
     * @status Core
     */
    public static $plugin_path;

    /**
     * Path to this plugin's directory.
     *
     * @type string
     * @status Core
     */
    public static $plugin_version;

    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @status Core
     */
    protected static $_instance = null;

    /**
     * Access this plugin’s working instance
     *
     * @wp-hook plugins_loaded
     * @return  object of this class
     * @since   2012.09.13
     */
    public static function instance()
    {
        null === self::$_instance and self::$_instance = new self;
        return self::$_instance;
    }

    /**
     * Simple_Import_Export constructor.
     */
    public function __construct()
    {

        /*
         * Check Require Php Version
         */
        if (version_compare(PHP_VERSION, $this->min_php, '<=')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return;
        }

        /*
         * Define Variable
         */
        $this->define_constants();

        /*
         * include files
         */
        $this->includes();

        /*
         * init WordPress hook
         */
        $this->init_hooks();

        /*
         * Plugin Loaded Action
         */
        do_action('simple_import_export_loaded');
    }

    /**
     * Define Constant
     */
    public function define_constants()
    {

        /*
         * Get Plugin Data
         */
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data(__FILE__);

        /*
         * Set Plugin Version
         */
        self::$plugin_version = $plugin_data['Version'];

        /*
         * Set Plugin Url
         */
        self::$plugin_url = plugins_url('', __FILE__);

        /*
         * Set Plugin Path
         */
        self::$plugin_path = plugin_dir_path(__FILE__);
    }

    /**
     * include Plugin Require File
     */
    public function includes()
    {
        require_once dirname(__FILE__) . '/inc/config/i18n.php';
        require_once dirname(__FILE__) . '/inc/config/install.php';
        require_once dirname(__FILE__) . '/inc/config/uninstall.php';
        require_once dirname(__FILE__) . '/inc/Helper.php';
        require_once dirname(__FILE__) . '/inc/FlashMessage.php';
        require_once dirname(__FILE__) . '/inc/Admin.php';
        require_once dirname(__FILE__) . '/inc/ParsiDate.php';
        require_once dirname(__FILE__) . '/inc/Custom.php';
        require_once dirname(__FILE__) . '/inc/core/utility.php';

        // Models
        require_once dirname(__FILE__) . '/model/Posts.php';
        require_once dirname(__FILE__) . '/model/Products.php';
    }

    /**
     * Used for regular plugin work.
     *
     * @wp-hook init Hook
     * @return  void
     */
    public function init_hooks()
    {

        /*
         * Activation Plugin Hook
         */
        register_activation_hook(__FILE__, array('\Simple_Import_Export\config\install', 'run_install'));

        /*
         * Uninstall Plugin Hook
         */
        register_deactivation_hook(__FILE__, array('\Simple_Import_Export\config\uninstall', 'run_uninstall'));

        /*
         * Load i18n
         */
        if (self::$use_i18n === true) {
            new \Simple_Import_Export\config\i18n('simple-import-export');
        }
    }

    /**
     * Show notice about PHP version
     *
     * @return void
     */
    function php_version_notice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $error = __('Your installed PHP Version is: ', 'simple-import-export') . PHP_VERSION . '. ';
        $error .= __('The <strong>Simple Import Export</strong> plugin requires PHP version <strong>', 'simple-import-export') . $this->min_php . __('</strong> or greater.', 'simple-import-export');
        ?>
        <div class="error">
            <p><?php printf($error); ?></p>
        </div>
        <?php
    }

    /**
     * Write WordPress Log
     *
     * @param $log
     */
    public static function log($log)
    {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }
}

/**
 * Main instance of WP_Plugin.
 *
 * @since  1.1.0
 */
function simple_import_export()
{
    return Simple_Import_Export::instance();
}

// Global for backwards compatibility.
$GLOBALS['simple-import-export'] = simple_import_export();
