<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JWPM_Assets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        // اگر آپ کے پاس فرنٹ اینڈ بھی ہے تو:
        // add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
    }

    /**
     * ایڈمن پینل کے لیے CSS اور JS فائلیں لوڈ کرتا ہے
     * @param string $hook موجودہ ایڈمن پیج کا ہک
     */
    public function enqueue_admin_assets( $hook ) {
        // === عام (Common) فائلیں، ہر ایڈمن پیج پر لوڈ ہوں گی ===
        wp_enqueue_style(
            'jwpm-common-style',
            JWPM_PLUGIN_URL . 'jwpm-common.css',
            array(),
            JWPM_VERSION
        );

        wp_enqueue_script(
            'jwpm-common-script',
            JWPM_PLUGIN_URL . 'jwpm-common.js',
            array( 'jquery' ),
            JWPM_VERSION,
            true
        );

        // === پیج مخصوص فائلیں ===
        // صرف اس پیج پر لوڈ کریں جہاں ضرورت ہو
        // مثال: POS پیج پر
        if ( 'toplevel_page_jwpm-pos' === $hook ) {
            wp_enqueue_style(
                'jwpm-pos-style',
                JWPM_PLUGIN_URL . 'assets/css/jwpm-pos.css',
                array(),
                JWPM_VERSION
            );
            wp_enqueue_script(
                'jwpm-pos-script',
                JWPM_PLUGIN_URL . 'assets/js/jwpm-pos.js',
                array( 'jquery', 'jwpm-common-script' ),
                JWPM_VERSION,
                true
            );
        }
        
        // دوسرے پیجز کے لیے بھی if condition استعمال کریں
        // if ( 'jewelry-pos_page_jwpm-inventory' === $hook ) { ... }
    }
}
