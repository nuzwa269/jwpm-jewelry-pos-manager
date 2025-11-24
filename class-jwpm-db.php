<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JWPM_DB {

    private $wpdb;
    private $products_table;
    private $customers_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->products_table  = $this->wpdb->prefix . 'jwpm_products';
        $this->customers_table = $this->wpdb->prefix . 'jwpm_customers';
    }

    /**
     * ایک مخصوص ID کے ذریعے پروڈکٹ حاصل کرتا ہے
     * @param int $product_id
     * @return object|null
     */
    public function get_product_by_id( $product_id ) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->products_table} WHERE id = %d",
                $product_id
            )
        );
    }

    /**
     * تمام کسٹمرز کی فہرست حاصل کرتا ہے
     * @return array
     */
    public function get_all_customers() {
        return $this->wpdb->get_results(
            "SELECT id, name, phone FROM {$this->customers_table} ORDER BY name ASC"
        );
    }

    /**
     * ایک نیا کسٹمر شامل کرتا ہے
     * @param array $data کسٹمر کا ڈیٹا (name, phone, email)
     * @return int|false انسرٹ شدہ ID یا ناکامی پر false
     */
    public function add_customer( $data ) {
        // ڈیٹا کو تصفیہ کرنا اور تیار کرنا
        $sanitized_data = [
            'name'       => sanitize_text_field( $data['name'] ),
            'phone'      => sanitize_text_field( $data['phone'] ),
            'email'      => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
            'created_at' => current_time( 'mysql' ),
        ];

        $format = [ '%s', '%s', '%s', '%s' ];

        $result = $this->wpdb->insert( $this->customers_table, $sanitized_data, $format );

        return $result ? $this->wpdb->insert_id : false;
    }
}
