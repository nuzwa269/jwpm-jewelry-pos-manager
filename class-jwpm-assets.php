<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JWPM_Assets {

    public function __construct() {
        // ایڈمن میں Assets لوڈ کریں
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // اگر فرنٹ اینڈ پر بھی لوڈ کرنا ہو:
        // add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
    }

    /**
     * ایڈمن پینل کے لیے CSS اور JS فائلیں لوڈ کرتا ہے
     *
     * @param string $hook موجودہ ایڈمن پیج کا ہک
     */
    public function enqueue_admin_assets( $hook ) {

        /*
        |--------------------------------------------------------------------------
        | Common Files — ہر ایڈمن پیج پر لوڈ ہوں گی
        |--------------------------------------------------------------------------
        */

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

        // JS میں متغیرات بھیجنا (nonce + messages)
        wp_localize_script(
            'jwpm-common-script',
            'jwpm_common_vars',
            array(
                'nonce'           => wp_create_nonce( 'jwpm_nonce' ),
                'confirm_message' => __( 'Are you sure you want to delete this item?', 'jwpm-jewelry-pos-manager' ),
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Page-Specific Files
        |--------------------------------------------------------------------------
        */

        // صرف POS پیج کے لیے
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

        // دوسرے پیجز کے لیے مثال:
        // if ( 'jewelry-pos_page_jwpm-inventory' === $hook ) {
        //     wp_enqueue_style( ... );
        //     wp_enqueue_script( ... );
        // }
    }

    // اگر فرنٹ اینڈ پر بھی Assets لوڈ کرنا ہوں تو:
    // public function enqueue_frontend_assets() {
    //     // wp_enqueue_style( ... );
    //     // wp_enqueue_script( ... );
    // }
}
