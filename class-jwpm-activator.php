<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JWPM_Activator {

    /**
     * پلگ ان فعال ہونے پر یہ میتھڈ چلے گا
     */
    public static function activate() {
        // ڈیٹا بیس میں ضروری ٹیبلز بنائیں
        self::create_tables();
        
        // ڈیفالٹ آپشنز سیٹ کریں
        self::set_default_options();

        // Rewrite rules کو فلش کریں (اگر آپ کے پلگ ان میں Custom URLs ہیں)
        flush_rewrite_rules();
    }

    /**
     * ڈیٹا بیس ٹیبلز کی ساخت بناتی ہے
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // پروڈکٹس ٹیبل کا SQL
        $sql_products = "CREATE TABLE `{$wpdb->prefix}jwpm_products` (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            sku varchar(100) NOT NULL,
            price decimal(10, 2) NOT NULL,
            weight decimal(10, 3) DEFAULT NULL,
            description text DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY sku (sku)
        ) $charset_collate;";

        // کسٹمرز ٹیبل کا SQL
        $sql_customers = "CREATE TABLE `{$wpdb->prefix}jwpm_customers` (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            phone varchar(50) NOT NULL,
            email varchar(100) DEFAULT NULL,
            address text DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_products );
        dbDelta( $sql_customers );
    }

    /**
     * پلگ ان کے لیے ڈیفالٹ آپشنز سیٹ کرتی ہے
     */
    private static function set_default_options() {
        $default_settings = [
            'currency'       => 'PKR', // ڈیفالٹ کرنسی
            'tax_rate'       => 17,    // ڈیفالٹ ٹیکس ریٹ
            'company_name'   => 'Your Jewelry Shop',
            'receipt_header' => 'Original Receipt',
        ];

        add_option( 'jwpm_settings', $default_settings );
        add_option( 'jwpm_version', JWPM_VERSION );
    }
}
