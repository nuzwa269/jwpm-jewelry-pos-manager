<?php
/**
 * Plugin activator class
 */
class JWPM_Activator {
    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        JWPM_User_Roles::add_roles();
        flush_rewrite_rules();
    }
    
    /**
     * Create required database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Items table
        $table_name = $wpdb->prefix . 'jw_items';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sku varchar(100) NOT NULL,
            category varchar(100) NOT NULL,
            metal varchar(50) NOT NULL,
            karat varchar(10) NOT NULL,
            gross_weight decimal(10,3) NOT NULL,
            net_weight decimal(10,3) NOT NULL,
            stone_details text,
            serial varchar(100) NOT NULL,
            branch_id mediumint(9) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'available',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY sku (sku),
            UNIQUE KEY serial (serial),
            KEY category (category),
            KEY branch_id (branch_id)
        ) $charset_collate;";
        
        // Stock ledger table
        $table_name = $wpdb->prefix . 'jw_stock_ledger';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            item_id mediumint(9) NOT NULL,
            action varchar(20) NOT NULL,
            qty int(11) NOT NULL,
            weight decimal(10,3) NOT NULL,
            branch_id mediumint(9) NOT NULL,
            date datetime DEFAULT CURRENT_TIMESTAMP,
            notes text,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY branch_id (branch_id)
        ) $charset_collate;";
        
        // Customers table
        $table_name = $wpdb->prefix . 'jw_customers';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            email varchar(100),
            loyalty_points int(11) NOT NULL DEFAULT 0,
            dob date,
            anniversary date,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY phone (phone),
            KEY email (email)
        ) $charset_collate;";
        
        // Sales table
        $table_name = $wpdb->prefix . 'jw_sales';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id mediumint(9),
            invoice_no varchar(50) NOT NULL,
            total decimal(10,2) NOT NULL,
            discount decimal(10,2) NOT NULL DEFAULT 0,
            final_total decimal(10,2) NOT NULL,
            payment_method varchar(50) NOT NULL,
            payment_status varchar(20) NOT NULL DEFAULT 'paid',
            date datetime DEFAULT CURRENT_TIMESTAMP,
            notes text,
            PRIMARY KEY  (id),
            UNIQUE KEY invoice_no (invoice_no),
            KEY customer_id (customer_id),
            KEY date (date)
        ) $charset_collate;";
        
        // Sales items table
        $table_name = $wpdb->prefix . 'jw_sales_items';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sale_id mediumint(9) NOT NULL,
            item_id mediumint(9) NOT NULL,
            price decimal(10,2) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY sale_id (sale_id),
            KEY item_id (item_id)
        ) $charset_collate;";
        
        // Installments table
        $table_name = $wpdb->prefix . 'jw_installments';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id mediumint(9) NOT NULL,
            sale_id mediumint(9) NOT NULL,
            due_date date NOT NULL,
            amount decimal(10,2) NOT NULL,
            paid_amount decimal(10,2) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            payment_date datetime,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY sale_id (sale_id),
            KEY due_date (due_date)
        ) $charset_collate;";
        
        // Purchases table
        $table_name = $wpdb->prefix . 'jw_purchases';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            supplier_id mediumint(9) NOT NULL,
            invoice_no varchar(50) NOT NULL,
            total decimal(10,2) NOT NULL,
            paid_amount decimal(10,2) NOT NULL DEFAULT 0,
            payment_status varchar(20) NOT NULL DEFAULT 'pending',
            date datetime DEFAULT CURRENT_TIMESTAMP,
            notes text,
            PRIMARY KEY  (id),
            UNIQUE KEY invoice_no (invoice_no),
            KEY supplier_id (supplier_id)
        ) $charset_collate;";
        
        // Purchase items table
        $table_name = $wpdb->prefix . 'jw_purchase_items';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            purchase_id mediumint(9) NOT NULL,
            item_id mediumint(9) NOT NULL,
            price decimal(10,2) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY purchase_id (purchase_id),
            KEY item_id (item_id)
        ) $charset_collate;";
        
        // Suppliers table
        $table_name = $wpdb->prefix . 'jw_suppliers';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            phone varchar(20),
            email varchar(100),
            address text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Repair jobs table
        $table_name = $wpdb->prefix . 'jw_repair_jobs';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id mediumint(9) NOT NULL,
            item_desc text NOT NULL,
            karigar varchar(100),
            charges decimal(10,2) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            received_date datetime DEFAULT CURRENT_TIMESTAMP,
            delivery_date datetime,
            notes text,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY karigar (karigar),
            KEY status (status)
        ) $charset_collate;";
        
        // Custom orders table
        $table_name = $wpdb->prefix . 'jw_custom_orders';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_id mediumint(9) NOT NULL,
            design_desc text NOT NULL,
            estimated_cost decimal(10,2) NOT NULL,
            final_cost decimal(10,2),
            status varchar(20) NOT NULL DEFAULT 'pending',
            order_date datetime DEFAULT CURRENT_TIMESTAMP,
            delivery_date datetime,
            notes text,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Accounts table
        $table_name = $wpdb->prefix . 'jw_accounts';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            transaction_type varchar(20) NOT NULL,
            category varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            description text,
            date datetime DEFAULT CURRENT_TIMESTAMP,
            reference_id mediumint(9),
            reference_type varchar(50),
            branch_id mediumint(9) NOT NULL,
            PRIMARY KEY  (id),
            KEY transaction_type (transaction_type),
            KEY category (category),
            KEY date (date),
            KEY branch_id (branch_id)
        ) $charset_collate;";
        
        // Branches table
        $table_name = $wpdb->prefix . 'jw_branches';
        $sql .= "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            address text,
            phone varchar(20),
            email varchar(100),
            manager_id mediumint(9),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $default_options = array(
            'jwpm_gold_rate' => 0,
            'jwpm_tax_rate' => 0,
            'jwpm_currency' => 'USD',
            'jwpm_company_name' => get_bloginfo('name'),
            'jwpm_company_address' => '',
            'jwpm_company_phone' => '',
            'jwpm_company_email' => get_option('admin_email'),
            'jwpm_invoice_prefix' => 'INV-',
            'jwpm_invoice_start' => 1001,
            'jwpm_theme_mode' => 'light',
            'jwpm_language' => 'en',
            'jwpm_backup_schedule' => 'weekly',
            'jwpm_backup_location' => 'local',
            'jwpm_woocommerce_sync' => 'disabled',
            'jwpm_shopify_sync' => 'disabled',
            'jwpm_magento_sync' => 'disabled',
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
}
