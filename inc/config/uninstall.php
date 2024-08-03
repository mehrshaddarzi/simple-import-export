<?php

namespace Simple_Import_Export\config;

class uninstall
{

    public static function run_uninstall()
    {

        global $wpdb;
        $table = $wpdb->prefix . 'usermeta';
        $wpdb->delete($table, array('meta_key' => 'flash-message'));
    }
}
