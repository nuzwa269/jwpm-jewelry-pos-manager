<?php
/**
 * JWPM_DB
 *
 * یہ کلاس تمام (JWPM) ڈیٹا بیس ٹیبلز کے لیے ہیلپر ہے۔
 * اس میں ٹیبل نام، (dbDelta) کے ذریعے کریئیٹ، ڈراپ اور (upgrade) میکانزم رکھا گیا ہے۔
 *
 * @package    JWPM
 * @subpackage JWPM/includes
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

		$prefix = $wpdb->prefix;

		return array(
			// بنیادی ماڈیولز
			'branches'       => $prefix . 'jwpm_branches',
			'items'          => $prefix . 'jwpm_items',
			'stock_ledger'   => $prefix . 'jwpm_stock_ledger',
			'customers'      => $prefix . 'jwpm_customers', // شامل کیا گیا
			'sales'          => $prefix . 'jwpm_sales',
			'sale_items'     => $prefix . 'jwpm_sale_items',
			'installments'   => $prefix . 'jwpm_installments', // شامل کیا گیا
			'installment_payments' => $prefix . 'jwpm_installment_payments', // شامل کیا گیا
			'purchases'      => $prefix . 'jwpm_purchases',
			'purchase_items' => $prefix . 'jwpm_purchase_items',
			'repair_jobs'    => $prefix . 'jwpm_repair_jobs', // شامل کیا گیا
			'repair_logs'    => $prefix . 'jwpm_repair_logs', // شامل کیا گیا
			'custom_orders'  => $prefix . 'jwpm_custom_orders',
			'activity_log'   => $prefix . 'jwpm_activity_log',
			'settings'       => $prefix . 'jwpm_settings',
			
			// اکاؤنٹس ماڈیول
			'cashbook'       => $prefix . 'jwpm_cashbook',
			'expenses'       => $prefix . 'jwpm_expenses',
			'ledger'         => $prefix . 'jwpm_ledger',
		);
	}

	/**
	 * (dbDelta) کے ذریعے تمام ٹیبلز بنائیں
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::get_table_names();

		$sql = array();

		// 1. برانچز (برقرار)
		$sql[] = "CREATE TABLE {$tables['branches']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			code VARCHAR(50) NOT NULL,
			address TEXT NULL,
			phone VARCHAR(50) NULL,
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY code (code)
		) $charset_collate;";

		// 2. کسٹمرز (شامل کیا گیا - JS میں استعمال ہوا)
		$sql[] = "CREATE TABLE {$tables['customers']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(191) NOT NULL,
			phone VARCHAR(50) NOT NULL,
			email VARCHAR(191) NULL,
			address TEXT NULL,
			total_sales DECIMAL(18,2) NOT NULL DEFAULT 0,
			balance_due DECIMAL(18,2) NOT NULL DEFAULT 0,
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone (phone),
			KEY branch_id (branch_id)
		) $charset_collate;";

		// 3. آئٹمز (برقرار)
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
			PRIMARY KEY  (id),
			KEY sku (sku),
			KEY tag_serial (tag_serial),
			KEY branch_id (branch_id),
			KEY category (category)
		) $charset_collate;";

		// 4. اسٹاک لیجر (برقرار)
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
			PRIMARY KEY  (id),
			KEY item_id (item_id),
			KEY branch_id (branch_id),
			KEY action_type (action_type)
		) $charset_collate;";

		// 5. سیلز (انوائس ہیڈر) (برقرار)
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
			PRIMARY KEY  (id),
			UNIQUE KEY invoice_no (invoice_no),
			KEY branch_id (branch_id),
			KEY customer_id (customer_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// 6. سیل آئٹمز (لائن آئٹمز) (برقرار)
		$sql[] = "CREATE TABLE {$tables['sale_items']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sale_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NOT NULL,
			quantity DECIMAL(18,6) NOT NULL DEFAULT 1,
			unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
			making_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY sale_id (sale_id),
			KEY item_id (item_id)
		) $charset_collate;";

		// 7. انویسٹمنٹس / قسطیں (شامل کیا گیا - JS میں استعمال ہوا)
		$sql[] = "CREATE TABLE {$tables['installments']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			sale_id BIGINT UNSIGNED NULL,
			contract_code VARCHAR(100) NOT NULL,
			total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			advance_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			net_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			installment_count INT NOT NULL,
			installment_frequency VARCHAR(30) NOT NULL,
			start_date DATE NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'active', -- active, completed, defaulted, cancelled
			remarks TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY contract_code (contract_code),
			KEY customer_id (customer_id)
		) $charset_collate;";

		// 8. انویسٹمنٹ ادائیگی (شامل کیا گیا - JS میں استعمال ہوا)
		$sql[] = "CREATE TABLE {$tables['installment_payments']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			installment_id BIGINT UNSIGNED NOT NULL,
			payment_date DATE NOT NULL,
			amount DECIMAL(18,2) NOT NULL,
			method VARCHAR(50) NOT NULL,
			reference_no VARCHAR(191) NULL,
			received_by BIGINT UNSIGNED NULL,
			note TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY installment_id (installment_id),
			KEY payment_date (payment_date)
		) $charset_collate;";

		// 9. پرچیز (سپلائر سے خریداری) (برقرار)
		$sql[] = "CREATE TABLE {$tables['purchases']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT UNSIGNED NOT NULL,
			supplier_id BIGINT UNSIGNED NULL,
			invoice_no VARCHAR(100) NOT NULL,
			total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY branch_id (branch_id),
			KEY supplier_id (supplier_id)
		) $charset_collate;";

		// 10. پرچیز آئٹمز (برقرار)
		$sql[] = "CREATE TABLE {$tables['purchase_items']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			purchase_id BIGINT UNSIGNED NOT NULL,
			item_id BIGINT UNSIGNED NULL,
			description TEXT NULL,
			weight DECIMAL(18,6) NULL,
			rate DECIMAL(18,6) NULL,
			amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY purchase_id (purchase_id)
		) $charset_collate;";

		// 11. ریپیئر جابز (شامل کیا گیا - JS میں استعمال ہوا)
		$sql[] = "CREATE TABLE {$tables['repair_jobs']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT UNSIGNED NOT NULL,
			job_code VARCHAR(100) NOT NULL,
			customer_name VARCHAR(191) NOT NULL,
			customer_phone VARCHAR(50) NOT NULL,
			item_description TEXT NOT NULL,
			job_type VARCHAR(100) NULL,
			received_date DATE NOT NULL,
			promised_date DATE NULL,
			actual_charges DECIMAL(18,2) NOT NULL DEFAULT 0,
			advance_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			balance_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			job_status VARCHAR(30) NOT NULL DEFAULT 'received', -- received, in_workshop, ready, delivered, cancelled
			priority VARCHAR(20) NOT NULL DEFAULT 'normal', -- normal, urgent, vip
			assigned_to BIGINT UNSIGNED NULL,
			tag_no VARCHAR(100) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY job_code (job_code),
			KEY branch_id (branch_id),
			KEY customer_phone (customer_phone)
		) $charset_collate;";

		// 12. ریپیئر لاگز (شامل کیا گیا - JS میں استعمال ہوا)
		$sql[] = "CREATE TABLE {$tables['repair_logs']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			repair_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(30) NOT NULL,
			note TEXT NULL,
			updated_by BIGINT UNSIGNED NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY repair_id (repair_id)
		) $charset_collate;";

		// 13. کسٹم آرڈرز (برقرار)
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
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY branch_id (branch_id),
			KEY status (status)
		) $charset_collate;";

		// 14. ایکٹیویٹی لاگ (آڈٹ ٹریل) (برقرار)
		$sql[] = "CREATE TABLE {$tables['activity_log']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			action VARCHAR(191) NOT NULL,
			entity_type VARCHAR(50) NULL,
			entity_id BIGINT UNSIGNED NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY entity_type (entity_type),
			KEY entity_id (entity_id)
		) $charset_collate;";

		// 15. سیٹنگز (گلوبل آپشنز) (برقرار)
		$sql[] = "CREATE TABLE {$tables['settings']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			option_name VARCHAR(191) NOT NULL,
			option_value LONGTEXT NULL,
			autoload VARCHAR(20) NOT NULL DEFAULT 'yes',
			PRIMARY KEY  (id),
			UNIQUE KEY option_name (option_name)
		) $charset_collate;";

		// --- Accounts Module Tables ---

		// 16. Cashbook (روزنامچہ) (برقرار)
		$sql[] = "CREATE TABLE {$tables['cashbook']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_date DATE NOT NULL,
			type VARCHAR(10) NOT NULL, -- in / out
			amount DECIMAL(18,4) NOT NULL DEFAULT 0,
			category VARCHAR(191) NOT NULL,
			reference VARCHAR(191) DEFAULT '',
			remarks TEXT NULL,
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY entry_date (entry_date),
			KEY type (type),
			KEY category (category)
		) $charset_collate;";

		// 17. Expenses (اخراجات) (برقرار)
		$sql[] = "CREATE TABLE {$tables['expenses']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			expense_date DATE NOT NULL,
			category VARCHAR(191) NOT NULL,
			amount DECIMAL(18,4) NOT NULL DEFAULT 0,
			vendor VARCHAR(191) DEFAULT '',
			notes TEXT NULL,
			receipt_url VARCHAR(255) DEFAULT '',
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY expense_date (expense_date),
			KEY category (category)
		) $charset_collate;";

		// 18. Ledger (کھاتہ جات) (برقرار)
		$sql[] = "CREATE TABLE {$tables['ledger']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_type VARCHAR(50) NOT NULL, -- sale, purchase, installment, custom, repair, manual
			ref_id BIGINT(20) UNSIGNED NULL,
			customer_id BIGINT(20) UNSIGNED NULL,
			supplier_id BIGINT(20) UNSIGNED NULL,
			debit DECIMAL(18,4) NOT NULL DEFAULT 0,
			credit DECIMAL(18,4) NOT NULL DEFAULT 0,
			description TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY entry_type (entry_type),
			KEY customer_id (customer_id),
			KEY supplier_id (supplier_id)
		) $charset_collate;";


		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * ضرورت پڑنے پر (DB) اپ گریڈ – ورژن کمپئیر کر کے ٹیبلز اپڈیٹ
	 */
	public static function maybe_upgrade() {
		// Define the current plugin version constant somewhere in your main plugin file, e.g., define( 'JWPM_DB_VERSION', '1.0' );
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
	 *
	 * @param int    $user_id
	 * @param string $action
	 * @param string $entity_type
	 * @param int    $entity_id
	 * @param array  $meta
	 */
	public static function log_activity( $user_id, $action, $entity_type = '', $entity_id = 0, $meta = array() ) {
		global $wpdb;

		$tables = self::get_table_names();

		$wpdb->insert(
			$tables['activity_log'],
			array(
				'user_id'     => $user_id,
				'action'      => $action,
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'meta'        => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
				'created_at'  => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);
	}
}
