<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JWPM_Ajax {

    public function __construct() {
        // ایک مثال کے لیے 'jwpm_add_customer' نامی AJAX ایکشن رجسٹر کریں
        // یہ صرف لاگ ان صارفین کے لیے کام کرے گا
        add_action( 'wp_ajax_jwpm_add_customer', array( $this, 'handle_add_customer' ) );
        
        // اگر آپ چاہتے ہیں کہ یہ غیر لاگ ان صارفین کے لیے بھی کام کرے:
        // add_action( 'wp_ajax_nopriv_jwpm_add_customer', array( $this, 'handle_add_customer' ) );
    }

    /**
     * نیا کسٹمر شامل کرنے کا AJAX ہینڈلر
     */
    public function handle_add_customer() {
        // === سیکیورٹی چیک: Nonce Verification ===
        // فرنٹ اینڈ سے nonce بھیجنا نہ بھولیں
        if ( ! check_ajax_referer( 'jwpm_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed!', 'jwpm-jewelry-pos-manager' ) ) );
        }

        // === صلاحیت کا چیک: Capability Check ===
        // صرف مینیجر یا ایڈمن ہی کسٹمر شامل کر سکے
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'jwpm-jewelry-pos-manager' ) ) );
        }

        // === ڈیٹا حاصل کریں اور تصفیہ کریں ===
        $name = isset( $_POST['customer_name'] ) ? sanitize_text_field( $_POST['customer_name'] ) : '';
        $phone = isset( $_POST['customer_phone'] ) ? sanitize_text_field( $_POST['customer_phone'] ) : '';
        $email = isset( $_POST['customer_email'] ) ? sanitize_email( $_POST['customer_email'] ) : '';

        // === ڈیٹا بیس میں محفوظ کریں ===
        global $wpdb;
        $table_name = $wpdb->prefix . 'jwpm_customers';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'name'       => $name,
                'phone'      => $phone,
                'email'      => $email,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );

        // === جواب بھیجیں ===
        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Customer added successfully!', 'jwpm-jewelry-pos-manager' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Could not add customer.', 'jwpm-jewelry-pos-manager' ) ) );
        }

        // AJAX ہینڈلر ہمیشہ wp_die() کے ساتھ ختم ہونا چاہیے
        wp_die();
    }
}
