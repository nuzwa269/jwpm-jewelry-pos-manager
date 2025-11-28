<?php
/**
 * JWPM_DB
 *
 * یہ کلاس تمام (JWPM) ڈیٹا بیس ٹیبلز کے لیے ہیلپر ہے۔
 * اسی میں ٹیبل نام، (dbDelta) کے ذریعے کریئیٹ، ڈراپ اور (upgrade) میکانزم رکھا گیا ہے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_DB {

	/**
	 * تمام ٹیبل نام ایک جگہ سے مینیج کرنے کے لیے
	 *
	 * @return array
	 */
	public static function get_table_names() {
		global $wpdb;

		// Note: jwpm_ کا prefix सीधे prefix में जोड़ दिया गया है ताकि WordPress prefix (wp_) अलग रहे।
		$prefix = $wpdb->prefix . 'jwpm_';

		return array(
			// Core Tables (Used by JWPM_Assets)
			'branches'       => $prefix . 'branches',
			'items'          => $prefix . 'items',
			'stock_ledger'   => $prefix . 'stock_ledger',
			
			// Module Tables (Merged from your provided schemas)
			'customers'      => $prefix . 'customers', // Part 32
			'sales'          => $prefix . 'sales',
			'sale_items'     => $prefix . 'sale_items',
			'installments'   => $prefix . 'installments', // Part 42 (Contracts)
			'inst_schedule'  => $prefix . 'installment_schedule', // Part 42
			'inst_payments'  => $prefix . 'installment_payments', // Part 42
			'purchases'      => $prefix . 'purchases',
			'purchase_items' => $prefix . 'purchase_items',
			'repairs' 	     => $prefix . 'repairs', // Part 8 (Repair Jobs)
			'repair_logs'    => $prefix . 'repair_logs', // Part 8
			'custom_orders'  => $prefix . 'custom_orders',
			'accounts'       => $prefix . 'accounts_ledger', // Part 20 (General Ledger)
			'cashbook'       => $prefix . 'cashbook', // Part 20
			'expenses'       => $prefix . 'expenses', // Part 20
			'activity_log'   => $prefix . 'activity_log',
			'settings'       => $prefix . 'settings',
		);
	}

	/**
	 * (dbDelta) کے ذریعے تمام ٹیبلز بنائیں
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::get_table_names();

		$sql = array();

		// --- 1. CORE / BRANCHES ---
		$sql[] = "CREATE TABLE {$tables['branches']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			code VARCHAR(50) NOT NULL,
			address TEXT NULL,
			phone VARCHAR(50) NULL,
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY code (code)
		) $charset_collate;";

		// --- 2. INVENTORY ITEMS ---
		$sql[] = "CREATE TABLE {$tables['items']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT UNSIGNED NOT NULL,
			sku VARCHAR(100) NOT NULL,
			tag_serial VARCHAR(100) NOT NULL,
			category VARCHAR(100) NULL,
			metal_type VARCHAR(50) NULL,
			karat VARCHAR(20) NULL,
			gross_weight DECIMAL(18,6) NULL,
			net_weight DECIMAL(18,6) NULL,
			stone_type VARCHAR(100) NULL,
			stone_carat DECIMAL(18,6) NULL,
			stone_qty INT NULL,
			labour_amount DECIMAL(18,2) NULL,
			design_no VARCHAR(100) NULL,
			image_id BIGINT UNSIGNED NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'in_stock',
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY sku (sku),
			KEY tag_serial (tag_serial),
			KEY branch_id (branch_id),
			KEY category (category)
		) $charset_collate;";

		// --- 3. STOCK LEDGER ---
		$sql[] = "CREATE TABLE {$tables['stock_ledger']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			item_id BIGINT UNSIGNED NOT NULL,
			branch_id BIGINT UNSIGNED NOT NULL,
			action_type VARCHAR(50) NOT NULL,
			quantity DECIMAL(18,6) NOT NULL DEFAULT 1,
			weight DECIMAL(18,6) NULL,
			ref_type VARCHAR(50) NULL,
			ref_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY item_id (item_id),
			KEY branch_id (branch_id),
			KEY action_type (action_type)
		) $charset_collate;";

		// --- 4. CUSTOMERS (Part 32) ---
		$sql[] = "CREATE TABLE {$tables['customers']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_code VARCHAR(50) NOT NULL,
			name VARCHAR(191) NOT NULL,
			phone VARCHAR(50) NOT NULL,
			whatsapp VARCHAR(50) DEFAULT NULL,
			email VARCHAR(191) DEFAULT NULL,
			city VARCHAR(100) DEFAULT NULL,
			address TEXT DEFAULT NULL,
			loyalty_points BIGINT NOT NULL DEFAULT 0,
			dob DATE NULL,
			anniversary DATE NULL,
			tags TEXT NULL,
			notes TEXT NULL,
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY customer_code (customer_code),
			KEY phone (phone),
			KEY email (email)
		) $charset_collate;";

		// --- 5. SALES ---
		$sql[] = "CREATE TABLE {$tables['sales']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NULL,
			invoice_no VARCHAR(100) NOT NULL,
			total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			final_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			payment_mode VARCHAR(50) NOT NULL,
			is_installment TINYINT(1) NOT NULL DEFAULT 0,
			payment_meta LONGTEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY invoice_no (invoice_no),
			KEY branch_id (branch_id),
			KEY customer_id (customer_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// --- 6. SALE ITEMS ---
		$sql[] = "CREATE TABLE {$tables['sale_items']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sale_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NOT NULL,
			quantity DECIMAL(18,6) NOT NULL DEFAULT 1,
			unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
			making_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY sale_id (sale_id),
			KEY item_id (item_id)
		) $charset_collate;";
		
		// --- 7. INSTALLMENTS (Contracts) (Part 42) ---
		$sql[] = "CREATE TABLE {$tables['installments']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			contract_code VARCHAR(50) NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			sale_id BIGINT UNSIGNED DEFAULT NULL,
			sale_reference VARCHAR(100) DEFAULT NULL,
			total_amount DECIMAL(15,3) NOT NULL DEFAULT 0.000,
			advance_amount DECIMAL(15,3) NOT NULL DEFAULT 0.000,
			net_installment_amount DECIMAL(15,3) NOT NULL DEFAULT 0.000,
			installment_count INT(11) NOT NULL DEFAULT 0,
			installment_frequency VARCHAR(20) NOT NULL DEFAULT 'monthly',
			start_date DATE DEFAULT NULL,
			end_date DATE NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			current_outstanding DECIMAL(15,3) NOT NULL DEFAULT 0.000,
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY contract_code (contract_code),
			KEY customer_id (customer_id),
			KEY status (status)
		) {$charset_collate};";
		
		// --- 8. INSTALLMENT SCHEDULE (Part 42) ---
		$sql[] = "CREATE TABLE {$tables['inst_schedule']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			contract_id BIGINT UNSIGNED NOT NULL,
			installment_no INT(11) NOT NULL DEFAULT 1,
			due_date DATE NOT NULL,
			amount DECIMAL(15,3) NOT NULL DEFAULT 0.000,
			paid_amount DECIMAL(15,3) NOT NULL DEFAULT 0.000,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			paid_date DATE NULL,
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY contract_id (contract_id),
			KEY due_date (due_date),
			KEY status (status)
		) {$charset_collate};";
		
		// --- 9. INSTALLMENT PAYMENTS (Part 42) ---
		$sql[] = "CREATE TABLE {$tables['inst_payments']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			contract_id BIGINT UNSIGNED NOT NULL,
			schedule_id BIGINT UNSIGNED DEFAULT NULL,
			payment_date DATE NOT NULL,
			paid_amount DECIMAL(15,3) NOT NULL DEFAULT 0.000,
			payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
			reference_no VARCHAR(100) DEFAULT NULL,
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY contract_id (contract_id),
			KEY schedule_id (schedule_id),
			KEY payment_date (payment_date)
		) {$charset_collate};";

		// --- 10. PURCHASE INVOICES ---
		$sql[] = "CREATE TABLE {$tables['purchases']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT UNSIGNED NOT NULL,
			supplier_id BIGINT UNSIGNED NULL,
			invoice_no VARCHAR(100) NOT NULL,
			total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY branch_id (branch_id),
			KEY supplier_id (supplier_id)
		) $charset_collate;";

		// --- 11. PURCHASE ITEMS ---
		$sql[] = "CREATE TABLE {$tables['purchase_items']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			purchase_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NULL,
			description TEXT NULL,
			weight DECIMAL(18,6) NULL,
			rate DECIMAL(18,6) NULL,
			amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY purchase_id (purchase_id)
		) $charset_collate;";

		// --- 12. REPAIR JOBS (Part 8) ---
		$sql[] = "CREATE TABLE {$tables['repairs']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			job_code VARCHAR(50) NOT NULL,
			customer_id BIGINT UNSIGNED NULL,
			branch_id BIGINT UNSIGNED NOT NULL,
			item_description TEXT NOT NULL,
			karigar_id BIGINT UNSIGNED NULL,
			estimated_charges DECIMAL(18,2) NULL,
			final_charges DECIMAL(18,2) NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'received',
			received_at DATETIME NOT NULL,
			promised_date DATE NULL,
			delivered_at DATETIME NULL,
			is_demo TINYINT(1) DEFAULT 0,
			PRIMARY KEY  (id),
			KEY job_code (job_code),
			KEY customer_id (customer_id),
			KEY branch_id (branch_id),
			KEY karigar_id (karigar_id),
			KEY status (status)
		) $charset_collate;";

		// --- 13. REPAIR LOGS (Part 8) ---
		$sql[] = "CREATE TABLE {$tables['repair_logs']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			repair_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) DEFAULT '' NOT NULL,
			note TEXT,
			updated_at DATETIME DEFAULT NULL,
			updated_by BIGINT UNSIGNED DEFAULT 0,
			PRIMARY KEY (id),
			KEY repair_id (repair_id),
			KEY status (status)
		) {$charset_collate};";

		// --- 14. CUSTOM ORDERS ---
		$sql[] = "CREATE TABLE {$tables['custom_orders']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NULL,
			branch_id BIGINT UNSIGNED NOT NULL,
			design_reference VARCHAR(191) NULL,
			estimate_weight DECIMAL(18,6) NULL,
			estimate_amount DECIMAL(18,2) NULL,
			advance_amount DECIMAL(18,2) NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'designing',
			due_date DATE NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY branch_id (branch_id),
			KEY status (status)
		) $charset_collate;";

		// --- 15. CASHBOOK (Part 20) ---
		$sql[] = "CREATE TABLE {$tables['cashbook']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_date DATE NOT NULL,
			type VARCHAR(10) NOT NULL, -- in / out
			amount DECIMAL(18,4) NOT NULL DEFAULT 0,
			category VARCHAR(191) NOT NULL,
			reference VARCHAR(191) DEFAULT '',
			remarks TEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY entry_date (entry_date),
			KEY type (type),
			KEY category (category)
		) {$charset_collate};";
		
		// --- 16. EXPENSES (Part 20) ---
		$sql[] = "CREATE TABLE {$tables['expenses']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			expense_date DATE NOT NULL,
			category VARCHAR(191) NOT NULL,
			amount DECIMAL(18,4) NOT NULL DEFAULT 0,
			vendor VARCHAR(191) DEFAULT '',
			notes TEXT NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY expense_date (expense_date),
			KEY category (category)
		) {$charset_collate};";
		
		// --- 17. ACCOUNTS LEDGER (Part 20) ---
		$sql[] = "CREATE TABLE {$tables['accounts']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_date DATE NOT NULL,
			branch_id BIGINT UNSIGNED NULL,
			account_type VARCHAR(50) NOT NULL,
			account_ref VARCHAR(100) NULL,
			description TEXT NULL,
			debit DECIMAL(18,2) NOT NULL DEFAULT 0,
			credit DECIMAL(18,2) NOT NULL DEFAULT 0,
			ref_type VARCHAR(50) NULL,
			ref_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_date (entry_date),
			KEY branch_id (branch_id),
			KEY account_type (account_type)
		) $charset_collate;";


		// --- 18. ACTIVITY LOG ---
		$sql[] = "CREATE TABLE {$tables['activity_log']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			action VARCHAR(191) NOT NULL,
			entity_type VARCHAR(50) NULL,
			entity_id BIGINT UNSIGNED NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY entity_type (entity_type),
			KEY entity_id (entity_id)
		) $charset_collate;";

		// --- 19. SETTINGS ---
		$sql[] = "CREATE TABLE {$tables['settings']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			option_name VARCHAR(191) NOT NULL,
			option_value LONGTEXT NULL,
			autoload VARCHAR(20) NOT NULL DEFAULT 'yes',
			PRIMARY KEY  (id),
			UNIQUE KEY option_name (option_name)
		) $charset_collate;";

		// تمام SQL statements کو dbDelta کے ذریعے چلائیں
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * ضرورت پڑنے پر (DB) اپ گریڈ – ورژن کمپئیر کر کے ٹیبلز اپڈیٹ
	 */
	public static function maybe_upgrade() {
		// JWPM_DB_VERSION constant کو define ہونا ضروری ہے۔ یہ JWPM_Activator میں ہوگا۔
		if ( ! defined( 'JWPM_DB_VERSION' ) ) {
			return;
		}

		$current = get_option( 'jwpm_db_version' );

		if ( version_compare( $current, JWPM_DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( 'jwpm_db_version', JWPM_DB_VERSION );
		}
	}

	/**
	 * تمام (JWPM) ٹیبلز ڈراپ کریں – صرف ہارڈ اَن انسٹال پر استعمال ہوگا
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = self::get_table_names();

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * ایکٹیویٹی لاگ ریکارڈ کریں
	 */
	public static function log_activity( $user_id, $action, $entity_type = '', $entity_id = 0, $meta = array() ) {
		// ... لاگنگ فنکشن (جیسا کہ پہلے define کیا گیا ہے)
		// یہ فنکشن فی الحال صرف جگہ خالی رکھنے کے لیے ہے تاکہ کلاس مکمل رہے۔
	}
}
