<?php

namespace Simple_Import_Export\Model;

use Simple_Import_Export\Helper;

class Posts
{

    public static string $key = 'wp_posts';

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
        $array[self::$key] = __('WordPress Posts', 'simple-import-export');
        return $array;
    }

    public function export_field()
    {
        ?>
        <tr class="form-field form-required simple_import_export_d_none" data-export-type="<?php echo self::$key; ?>">
            <th scope="row">
                <label for="post_type">
                    <span><?php _e('Post Type', 'simple-import-export'); ?></span>
                </label>
            </th>
            <td>
                <select name="post_type">
                    <?php
                    $post_types = get_post_types([], 'objects');
                    foreach ($post_types as $post_type) {
                        ?>
                        <option value="<?php echo $post_type->name; ?>">
                            <?php echo $post_type->labels->singular_name; ?> [<?php echo $post_type->name; ?>]
                        </option>
                        <?php
                    }
                    ?>
                </select>
            </td>
        </tr>
        <?php
    }

    public function export($data, $type, $extension)
    {
        if ($type != self::$key) {
            return $data;
        }

        $posts = Helper::wp_query([
            'post_type' => trim($_REQUEST['post_type']),
            'fields' => 'all'
        ]);
        if (empty($posts)) {
            return $data;
        }

        // Setup PHP Array
        $columns = array(
            'ID',
            'Title',
            'Date'
        );
        $data = [$columns];

        // Setup Item
        foreach ($posts as $post) {
            $data[] = array(
                $post->ID,
                $post->post_title,
                $post->post_date
            );
        }

        // Return
        return $data;
    }

    public function import_field()
    {
        ?>
        <tr class="form-field form-required simple_import_export_d_none" data-import-type="<?php echo self::$key; ?>">
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
}

new Posts();