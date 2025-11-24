<?php
// اگر یہ فائل ورڈپریس کے توسط سے نہیں چلائی جا رہی ہے تو روک دیں
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// === ڈیٹا بیس سے پلگ ان کی ٹیبلز حذف کریں ===
global $wpdb;
 $table_names = [
    $wpdb->prefix . 'jwpm_products',
    $wpdb->prefix . 'jwpm_customers',
    $wpdb->prefix . 'jwpm_sales',
    $wpdb->prefix . 'jwpm_installments',
    // آپ کی دیگر ٹیبلز کے نام یہاں لکھیں
];

foreach ( $table_names as $table_name ) {
    // یہ چیک کرنا بہتر ہے کہ ٹیبل موجود ہے یا نہیں
    $table = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
    if ( $table === $table_name ) {
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
    }
}

// === پلگ ان کے آپشنز حذف کریں ===
delete_option( 'jwpm_version' );
delete_option( 'jwpm_settings' );
// آپ نے جو بھی آپشنز سیو کیے ہیں، انہیں یہاں حذف کریں
