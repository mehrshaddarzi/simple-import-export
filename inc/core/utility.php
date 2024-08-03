<?php

namespace Simple_Import_Export\core;

class Utility
{

    public static function wp_query($arg = array(), $title = true)
    {
        // Create Empty List
        $list = array();

        // Prepare Params
        $default = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => '-1',
            'order' => 'ASC',
            'fields' => 'ids',
            'cache_results' => false,
            'no_found_rows' => true, //@see https://10up.github.io/Engineering-Best-Practices/php/#performance
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters' => true
        );
        $args = wp_parse_args($arg, $default);

        // Get Data
        $query = new \WP_Query($args);

        // Get SQL
        //echo $query->request;
        //exit;

        // Added To List
        foreach ($query->posts as $ID) {
            if ($title) {
                $list[$ID] = get_the_title($ID);
            } else {
                $list[] = $ID;
            }
        }

        return $list;
    }

    public static function is_edit_page($new_edit = null)
    {
        global $pagenow;
        //make sure we are on the backend
        if (!is_admin()) return false;

        if ($new_edit == "edit")
            return in_array($pagenow, array('post.php',));
        elseif ($new_edit == "new") //check for new post page
            return in_array($pagenow, array('post-new.php'));
        else //check for either new or edit
            return in_array($pagenow, array('post.php', 'post-new.php'));
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

    public static function wp_user_query($arg = array())
    {

        $list = array();
        $default = array(
            'fields' => array('id'),
            'orderby' => 'id',
            'order' => 'ASC',
            'count_total' => false
        );
        $args = wp_parse_args($arg, $default);

        $user_query = new \WP_User_Query($args);
        //[Get Request SQL]
        //echo $user_query->request; 
        foreach ($user_query->get_results() as $user) {
            $list[] = $user->id;
        }

        return $list;
    }

    public static function json_exit($array)
    {
        wp_send_json($array);
        exit;
    }

    public static function is_request($type)
    {
        switch ($type) {
            case 'admin':
                return is_admin();
            case 'ajax':
                return defined('DOING_AJAX');
            case 'cron':
                return defined('DOING_CRON');
            case 'frontend':
                return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON');
        }
    }

    public static function is_rest_request()
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return false;
        }
        $rest_prefix = trailingslashit(rest_get_url_prefix());
        return (false !== strpos($_SERVER['REQUEST_URI'], $rest_prefix));
    }
}
