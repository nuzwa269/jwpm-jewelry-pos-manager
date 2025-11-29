<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JWPM_Deactivator {

    /**
     * پلگ ان غیر فعال ہونے پر یہ میتھڈ چلے گا
     */
    public static function deactivate() {
        // Rewrite rules کو فلش کریں
        flush_rewrite_rules();

        // یہاں آپ کوئی ٹیمپوریری ڈیٹا حذف کر سکتے ہیں
        // مثال کے طور پر، transient ڈیٹا
        // delete_transient('jwpm_daily_report');
    }
}
