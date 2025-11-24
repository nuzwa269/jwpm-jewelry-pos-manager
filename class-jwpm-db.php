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

		$prefix = $wpdb->prefix;

		return array(
			'branches'       => $prefix . 'jwpm_branches',
			'items'          => $prefix . 'jwpm_items',
			'stock_ledger'   => $prefix . 'jwpm_stock_ledger',
			'customers'      => $prefix . 'jwpm_customers',
			'sales'          => $prefix . 'jwpm_sales',
			'sale_items'     => $prefix . 'jwpm_sale_items',
			'installments'   => $prefix . 'jwpm_installments',
			'purchases'      => $prefix . 'jwpm_purchases',
			'purchase_items' => $prefix . 'jwpm_purchase_items',
			'repair_jobs'    => $prefix . 'jwpm_repair_jobs',
			'custom_orders'  => $prefix . 'jwpm_custom_orders',
			'accounts'       => $prefix . 'jwpm_accounts_ledger',
			'activity_log'   => $prefix . 'jwpm_activity_log',
			'settings'       => $prefix . 'jwpm_settings',
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

		// برانچز
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

		// آئٹمز
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

		// اسٹاک لیجر
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

		// کسٹمرز
		$sql[] = "CREATE TABLE {$tables['customers']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			phone VARCHAR(50) NULL,
			email VARCHAR(100) NULL,
			address TEXT NULL,
			loyalty_points BIGINT NOT NULL DEFAULT 0,
			dob DATE NULL,
			anniversary DATE NULL,
			tags TEXT NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY phone (phone),
			KEY email (email)
		) $charset_collate;";

		// سیلز
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

		// سیل آئٹمز
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

		// اقساط
		$sql[] = "CREATE TABLE {$tables['installments']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sale_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			due_date DATE NOT NULL,
			amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			paid_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY sale_id (sale_id),
			KEY customer_id (customer_id),
			KEY due_date (due_date),
			KEY status (status)
		) $charset_collate;";

		// پرچیز
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

		// پرچیز آئٹمز
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

		// ریپیر جابز
		$sql[] = "CREATE TABLE {$tables['repair_jobs']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NULL,
			branch_id BIGINT UNSIGNED NOT NULL,
			item_description TEXT NOT NULL,
			karigar_id BIGINT UNSIGNED NULL,
			estimated_charges DECIMAL(18,2) NULL,
			final_charges DECIMAL(18,2) NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'received',
			received_at DATETIME NOT NULL,
			completed_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY customer_id (customer_id),
			KEY branch_id (branch_id),
			KEY karigar_id (karigar_id),
			KEY status (status)
		) $charset_collate;";

		// کسٹم آرڈرز
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

		// اکاؤنٹس لیجر
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

		// ایکٹیویٹی لاگ
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

		// سیٹنگز
		$sql[] = "CREATE TABLE {$tables['settings']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			option_name VARCHAR(191) NOT NULL,
			option_value LONGTEXT NULL,
			autoload VARCHAR(20) NOT NULL DEFAULT 'yes',
			PRIMARY KEY  (id),
			UNIQUE KEY option_name (option_name)
		) $charset_collate;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * ضرورت پڑنے پر (DB) اپ گریڈ – ورژن کمپئیر کر کے ٹیبلز اپڈیٹ
	 */
	public static function maybe_upgrade() {
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
	 * @param int    $user_id
	 * @param string $action
	 * @param string $entity_type
	 * @param int    $entity_id
	 * @param array  $meta
	 */
	public static function log_activity( $user_id, $action, $entity_type = '', $entity_id = 0, $meta = array() ) {
		global $wpdb;

		$tables = self::get_table_names();

		$wpdb->insert(
			$tables['activity_log'],
			array(
				'user_id'     => $user_id,
				'action'      => $action,
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'meta'        => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
				'created_at'  => current_time( 'mysql' ),
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

// ✅ Syntax verified block end
