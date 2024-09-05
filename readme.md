### Simple Process For Big Data in WordPress ( Import/Export )

<img src="https://raw.githubusercontent.com/mehrshaddarzi/simple-import-export/master/screenshot.jpg">

#### Add New Export Method

```php
add_filter('simple_import_export_type_lists_at_export', [$this, 'method']);
add_filter('simple_prepare_data_for_export', [$this, 'export'], 20, 3);
add_action('simple_import_export_form_fields_export', [$this, 'export_custom_form_field']);

public function method($array)
{
    $array['wp_posts'] = __('WordPress Posts', 'simple-import-export');
    return $array;
}

public function export($data, $type, $extension)
{
    if ($type != 'wp_posts') {
        return $data;
    }

    // Get Data
    $posts = new \WP_Query(array(
        'post_type' => trim($_REQUEST['post_type']),
        'post_status' => 'publish',
        'posts_per_page' => '-1',
        'order' => 'ASC',
        'fields' => 'all',
        'cache_results' => false,
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'suppress_filters' => true
    ));

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

public function export_custom_form_field()
{
    ?>
    <tr class="form-field form-required simple_import_export_d_none" data-export-type="wp_posts">
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
```

#### Add New Import Method

```php
add_filter('simple_import_export_type_lists_at_import', [$this, 'method']);
add_action('simple_import_handle_item', [$this, 'import_row'], 10, 5);
add_action('simple_import_export_form_fields_import', [$this, 'import_custom_form_field']);

public function method($array)
{
    $array['wp_posts'] = __('WordPress Posts', 'simple-import-export');
    return $array;
}

public function import_row($row, $key, $type, $extension, $option)
{
    if ($type != 'wp_posts') {
        return;
    }

    if (isset($row[0]) and !empty($row[0]) and is_numeric($row[0])) {

        $post_id = (int)$row[0]; // ID
        $post = get_post($post_id);
        if (!is_null($post)) {

            $before_post_title = $post->post_title;
            $new_title = trim($row[1]);
            if (!empty($new_title) and $new_title != $before_post_title and $option['input']['post_status'] == $post->post_status) {

                $arg = array(
                    'ID' => $post_id,
                    'post_title' => $new_title
                );
                wp_update_post($arg);
            }
        }
    }
}

public function import_custom_form_field()
{
    ?>
    <tr class="form-field form-required simple_import_export_d_none" data-import-type="wp_posts">
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
```

#### How to Set up New Extension File?

By default, this plugin support `Excel`and `Json` File For import/export.

If You want to add new Extension use this Hook:

```php
// Export File
do_action('simple_export_handle_file', $data, $type, $extension);

// Get File Content For Start Import Process
apply_filters('simple_prepare_data_for_import', [], $target_file, $type, $extension);
```

#### How to disable Import Or Export Form View?

```php
// Disable Export Form
add_filter('simple_import_export_enable_export_system', '__return_false');

// Disable Import Form
add_filter('simple_import_export_enable_import_system', '__return_false');
```

### Add Custom Content to Page

```php
// Top Page
do_action('simple_import_export_page_header');

// Bottom Page
do_action('simple_import_export_page_footer');
```

### Change Admin Menu Option

```php
add_filter('simple_import_export_admin_menu', 'wp_admin_custom_menu_name', 10, 1);
function wp_admin_custom_menu_name($args) {
    return [
            'menu_title' => __('Import/Export', 'simple-import-export'),
            'page_title' => __('Simple Import / Export', 'simple-import-export'),
            'capability' => 'manage_options',
            'icon' => 'dashicons-database',
            'position' => 90
   ];
}
```