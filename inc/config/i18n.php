<?php

namespace Simple_Import_Export\config;
class i18n
{

    /**
     * Get name of text domain in this plugin
     * @var string
     */
    public $text_domain;

    /**
     * i18n constructor.
     * @param $text_domain
     */
    public function __construct($text_domain)
    {

        add_filter('gettext', [$this, 'get_text'], 10, 3);

        if (\Simple_Import_Export::$use_i18n === false) {
            return;
        }

        $this->text_domain = $text_domain;

        add_action('init', array($this, 'i18n'));
    }

    public function get_text($translation, $orig, $domain)
    {

        if ($domain != 'simple-import-export') {
            return $translation;
        }

        // Persian
        if (strtolower(get_locale()) != 'fa_ir') {
            return $translation;
        }

        $list = [
            'Simple Import / Export' => 'ورودی / خروجی آسان',
            'Import/Export' => 'ورودی/خروجی',
            'Simple Import Export Data' => 'ورودی / خروجی آسان اطلاعات',
            'Simple Import/Export Data at WordPress' => 'ورودی / خروجی آسان اطلاعات در پلتفرم وردپرس',
            'Mehrshad Darzi' => 'مهرشاد درزی',
            'Export Form' => 'فرم خروجی',
            'Import Form' => 'فرم ورودی',
            'Export Type' => 'نوع خروجی',
            'File Format' => 'فرمت فایل',
            'Run' => 'اجرا',
            'File' => 'فایل',
            'Import Type' => 'نوع ورودی',
            'Post Type' => 'پست تایپ',
            'WordPress Posts' => 'پست وردپرس',
            'Loading ..' => 'لطفا کمی صبر کنید ..',
            'List is empty' => 'لیست خالی می باشد',
            'File created' => 'فایل ایجاد شد',
            'Number Row' => 'تعداد ردیف',
            'Download File' => 'دانلود فایل',
            'Error in creating json file' => 'خطا در زمان ایجاد فایل json رخ داده است',
            'Error' => 'خطا',
            'An error occurred while executing the operation, please try again' => 'خطایی در اجرای عملیات رخ داده است لطفا مجدد تلاش کنید',
            'Security Error!' => 'خطای امنیتی!',
            'No select any file' => 'هیچ فایلی انتخاب نشده است',
            'Error in Upload File' => 'خطا در آپلود فایل رخ داده است',
            'Max Item Per Process' => 'حداکثر آیتم در هر اجرا',
            'Please do not close the browser until the operation is finished' => 'لطفا تا پایان عملیات پنجره مرورگر را نبندید',
            'Done' => 'انجام شد',
            'Post Status' => 'وضعیت پست',
            'Start' => 'شروع',
            'WooCommerce Products' => 'محصولات ووکامرس',
            'Simple' => 'ساده',
            'Variable' => 'متغیر',
            'Product Type' => 'نوع محصول',
            'Product Status' => 'وضعیت محصول',
            'Publish' => 'انتشار یافته',
            'Draft' => 'پیش نویس',
            'Product Category' => 'دسته بندی محصولات',
        ];

        if (in_array($orig, array_keys($list))) {
            return $list[$orig];
        }

        return $translation;
    }

    public function i18n()
    {
        load_plugin_textdomain($this->text_domain, false, wp_normalize_path(\Simple_Import_Export::$plugin_path . '/languages'));
    }

}