<div class="wrap">
    <h1 id="add-new-user">
        <?php _e('Simple Import / Export', 'simple-import-export'); ?>
    </h1>

    <?php
    $flashMessage = \Simple_Import_Export\FlashMessage::get();
    if (!empty($flashMessage['data']) and !empty($flashMessage['type'])) {
        echo \Simple_Import_Export\core\Utility::admin_notice($flashMessage['data'], $flashMessage['type']);
    }
    ?>

    <div class="simple_import_export_action__run" data-action="export">
        <p><?php _e('Export Form', 'simple-import-export'); ?>:</p>
        <form method="post" action=""
              name="simple-import-export__export_form"
              id="simple-import-export__export_form">

            <?php wp_nonce_field('export_nonce_simple', 'export_nonce_simple'); ?>
            <table class="form-table" role="presentation">
                <tbody>

                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="type">
                            <span><?php _e('Export Type', 'simple-import-export'); ?></span>
                        </label>
                    </th>
                    <td>
                        <select name="type" data-action="export">
                            <?php
                            $choices = \Simple_Import_Export\Admin::get_export_types();
                            foreach ($choices as $k => $v) {
                                ?>
                                <option value="<?php echo $k; ?>">
                                    <?php echo $v; ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="extension">
                            <span><?php _e('File Format', 'simple-import-export'); ?></span>
                        </label>
                    </th>
                    <td>
                        <select name="extension">
                            <?php
                            $choices = \Simple_Import_Export\Admin::get_export_extensions();
                            foreach ($choices as $k => $v) {
                                ?>
                                <option value="<?php echo $k; ?>">
                                    <?php echo $v; ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <?php
                do_action('simple_import_export_form_fields_export');
                ?>

                </tbody>
            </table>
            <p class="submit">
                <input
                        type="submit"
                        class="button button-primary"
                        style="padding: 6px 50px;"
                        value="<?php _e('Run', 'simple-import-export'); ?>">
            </p>
        </form>
    </div>

    <br/>

    <div class="simple_import_export_action__run" data-action="import">
        <p><?php _e('Import Form', 'simple-import-export'); ?>:</p>
        <form method="post" action=""
              name="simple-import-export__import_form"
              id="simple-import-export__import_form"
              enctype="multipart/form-data">

            <?php wp_nonce_field('import_nonce_simple', 'import_nonce_simple'); ?>
            <table class="form-table" role="presentation">
                <tbody>

                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="user_login">
                            <span><?php _e('File', 'simple-import-export'); ?></span>
                        </label>
                    </th>
                    <td>
                        <input type="file" name="attachment" required/>
                    </td>
                </tr>

                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="user_login">
                            <span><?php _e('Import Type', 'simple-import-export'); ?></span>
                        </label>
                    </th>
                    <td>
                        <select name="type" data-action="import">
                            <?php
                            $choices = \Simple_Import_Export\Admin::get_import_types();
                            foreach ($choices as $k => $v) {
                                ?>
                                <option value="<?php echo $k; ?>">
                                    <?php echo $v; ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="extension">
                            <span><?php _e('File Format', 'simple-import-export'); ?></span>
                        </label>
                    </th>
                    <td>
                        <select name="extension">
                            <?php
                            $choices = \Simple_Import_Export\Admin::get_import_extensions();
                            foreach ($choices as $k => $v) {
                                ?>
                                <option value="<?php echo $k; ?>">
                                    <?php echo $v; ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="per_page">
                            <span><?php _e('Max Item Per Process', 'simple-import-export'); ?></span>
                        </label>
                    </th>
                    <td>
                        <select name="per_page">
                            <?php
                            foreach ([1, 5, 10, 50, 100, 150, 200, 300, 500, 1000, 2000] as $k) {
                                ?>
                                <option value="<?php echo $k; ?>" <?php echo($k == "50" ? 'selected' : ''); ?>>
                                    <?php echo number_format($k); ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>

                <?php
                do_action('simple_import_export_form_fields_import');
                ?>

                </tbody>
            </table>
            <p class="submit">
                <input
                        type="submit"
                        class="button"
                        style="padding: 6px 50px;"
                        value="<?php _e('Run', 'simple-import-export'); ?>">
            </p>

            <div id="simple-import-alert"></div>
        </form>
    </div>

</div>