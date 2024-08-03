<div class="wrap">
    <h1 id="add-new-user">
        Simple Import / Export
    </h1>

    <?php
    $flashMessage = \Simple_Import_Export\FlashMessage::get();
    if (!empty($flashMessage['data']) and !empty($flashMessage['type'])) {
        echo \Simple_Import_Export\core\Utility::admin_notice($flashMessage['data'], $flashMessage['type']);
    }
    ?>

    <p>لطفا نوع خروجی را مشخص کنید:</p>
    <form method="post" action="" name="simple-import-export" id="simple-import-export">

        <?php wp_nonce_field('export_nonce_simple', 'export_nonce_simple'); ?>
        <table class="form-table" role="presentation">
            <tbody>

            <tr class="form-field form-required">
                <th scope="row">
                    <label for="user_login">
                        <span>Export Type</span>
                    </label>
                </th>
                <td>
                    <select name="type">
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
                    <label for="user_login">
                        <span>Export Extension</span>
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
                    value="Export">
        </p>
    </form>

    <br/>

    <p>فایل را برای ورودی انتخاب کنید:</p>
    <form method="post" action="" name="simple-import-export" id="simple-import-export" enctype="multipart/form-data">

        <?php wp_nonce_field('import_nonce_simple', 'import_nonce_simple'); ?>
        <table class="form-table" role="presentation">
            <tbody>

            <tr class="form-field form-required">
                <th scope="row">
                    <label for="user_login">
                        <span>Import File</span>
                    </label>
                </th>
                <td>
                    <input type="file" name="attachment">
                </td>
            </tr>

            <tr class="form-field form-required">
                <th scope="row">
                    <label for="user_login">
                        <span>Import Type</span>
                    </label>
                </th>
                <td>
                    <select name="type">
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
                    <label for="user_login">
                        <span>Import Extension</span>
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

            <?php
            do_action('simple_import_export_form_fields_import');
            ?>

            </tbody>
        </table>
        <p class="submit">
            <input
                    type="submit"
                    class="button button-primary"
                    value="Import">
        </p>
    </form>

</div>