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
/** Part 32 — Customers Table Schema (jwpm_customers) */
// 🟢 یہاں سے [Customers Table Schema] شروع ہو رہا ہے

if ( ! class_exists( 'JWPM_DB_Customers' ) ) {

	class JWPM_DB_Customers {

		const TABLE_SLUG      = 'jwpm_customers';
		const DB_VERSION_OPT  = 'jwpm_customers_db_version';
		const DB_VERSION      = '1.0.0';

		/**
		 * مکمل ٹیبل نام (prefix کے ساتھ)
		 */
		public static function get_table_name() {
			global $wpdb;
			return $wpdb->prefix . self::TABLE_SLUG;
		}

		/**
		 * (dbDelta) کیلئے مکمل SQL
		 */
		public static function get_table_schema() {
			$table_name = self::get_table_name();

			$charset_collate = '';
			global $wpdb;
			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate .= "DEFAULT CHARACTER SET {$wpdb->charset} ";
			}
			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= "COLLATE {$wpdb->collate} ";
			}

			$sql = "CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				customer_code varchar(50) NOT NULL,
				name varchar(191) NOT NULL,
				phone varchar(50) NOT NULL,
				whatsapp varchar(50) DEFAULT NULL,
				email varchar(191) DEFAULT NULL,
				city varchar(100) DEFAULT NULL,
				area varchar(100) DEFAULT NULL,
				address text DEFAULT NULL,
				cnic varchar(50) DEFAULT NULL,
				dob date DEFAULT NULL,
				gender varchar(20) DEFAULT NULL,
				customer_type varchar(50) NOT NULL DEFAULT 'walkin',
				status varchar(20) NOT NULL DEFAULT 'active',
				credit_limit decimal(15,3) NOT NULL DEFAULT 0.000,
				opening_balance decimal(15,3) NOT NULL DEFAULT 0.000,
				current_balance decimal(15,3) NOT NULL DEFAULT 0.000,
				total_purchases decimal(15,3) NOT NULL DEFAULT 0.000,
				total_returns decimal(15,3) NOT NULL DEFAULT 0.000,
				total_paid decimal(15,3) NOT NULL DEFAULT 0.000,
				price_group varchar(50) DEFAULT NULL,
				tags text DEFAULT NULL,
				notes text DEFAULT NULL,
				is_demo tinyint(1) NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned DEFAULT NULL,
				updated_by bigint(20) unsigned DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY customer_code (customer_code),
				KEY phone (phone),
				KEY city (city),
				KEY customer_type (customer_type),
				KEY status (status),
				KEY is_demo (is_demo)
			) {$charset_collate};";

			return $sql;
		}

		/**
		 * ٹیبل موجود نہ ہو تو بنائے، ورژن آپشن بھی اپڈیٹ کرے۔
		 */
		public static function maybe_create_table() {
			$current_version = get_option( self::DB_VERSION_OPT );
			if ( self::DB_VERSION === $current_version ) {
				return;
			}

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$sql = self::get_table_schema();
			dbDelta( $sql );

			update_option( self::DB_VERSION_OPT, self::DB_VERSION );
		}
	}
}

/**
 * admin میں load ہوتے ہی Customers ٹیبل ensure
 */
if ( is_admin() && function_exists( 'add_action' ) ) {
	add_action(
		'admin_init',
		static function () {
			if ( class_exists( 'JWPM_DB_Customers' ) ) {
				JWPM_DB_Customers::maybe_create_table();
			}
		}
	);
}

// 🔴 یہاں پر [Customers Table Schema] ختم ہو رہا ہے
// ✅ Syntax verified block end
/** Part 42 — Installments Tables Schema */
// 🟢 یہاں سے [Installments Tables Schema] شروع ہو رہا ہے

if ( ! class_exists( 'JWPM_DB_Installments' ) ) {

	class JWPM_DB_Installments {

		const TABLE_INSTALLMENTS_SLUG         = 'jwpm_installments';
		const TABLE_SCHEDULE_SLUG             = 'jwpm_installment_schedule';
		const TABLE_PAYMENTS_SLUG             = 'jwpm_installment_payments';
		const DB_VERSION_OPT_INSTALLMENTS     = 'jwpm_installments_db_version';
		const DB_VERSION_OPT_SCHEDULE         = 'jwpm_installment_schedule_db_version';
		const DB_VERSION_OPT_PAYMENTS         = 'jwpm_installment_payments_db_version';
		const DB_VERSION                      = '1.0.0';

		protected static function get_charset_collate() {
			global $wpdb;

			$charset_collate = '';
			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate .= "DEFAULT CHARACTER SET {$wpdb->charset} ";
			}
			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= "COLLATE {$wpdb->collate} ";
			}

			return $charset_collate;
		}

		public static function get_installments_table() {
			global $wpdb;
			return $wpdb->prefix . self::TABLE_INSTALLMENTS_SLUG;
		}

		public static function get_schedule_table() {
			global $wpdb;
			return $wpdb->prefix . self::TABLE_SCHEDULE_SLUG;
		}

		public static function get_payments_table() {
			global $wpdb;
			return $wpdb->prefix . self::TABLE_PAYMENTS_SLUG;
		}

		public static function get_installments_schema() {
			$table   = self::get_installments_table();
			$charset = self::get_charset_collate();

			$sql = "CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contract_code varchar(50) NOT NULL,
				customer_id bigint(20) unsigned NOT NULL,
				sale_id bigint(20) unsigned DEFAULT NULL,
				sale_reference varchar(100) DEFAULT NULL,
				sale_date date DEFAULT NULL,
				total_amount decimal(15,3) NOT NULL DEFAULT 0.000,
				advance_amount decimal(15,3) NOT NULL DEFAULT 0.000,
				net_installment_amount decimal(15,3) NOT NULL DEFAULT 0.000,
				installment_count int(11) NOT NULL DEFAULT 0,
				installment_frequency varchar(20) NOT NULL DEFAULT 'monthly',
				start_date date DEFAULT NULL,
				end_date date DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				current_outstanding decimal(15,3) NOT NULL DEFAULT 0.000,
				remarks text DEFAULT NULL,
				is_demo tinyint(1) NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned DEFAULT NULL,
				updated_by bigint(20) unsigned DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY contract_code (contract_code),
				KEY customer_id (customer_id),
				KEY status (status),
				KEY is_demo (is_demo),
				KEY start_date (start_date),
				KEY end_date (end_date)
			) {$charset};";

			return $sql;
		}

		public static function get_schedule_schema() {
			$table   = self::get_schedule_table();
			$charset = self::get_charset_collate();

			$sql = "CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contract_id bigint(20) unsigned NOT NULL,
				installment_no int(11) NOT NULL DEFAULT 1,
				due_date date NOT NULL,
				amount decimal(15,3) NOT NULL DEFAULT 0.000,
				paid_amount decimal(15,3) NOT NULL DEFAULT 0.000,
				status varchar(20) NOT NULL DEFAULT 'pending',
				paid_date date DEFAULT NULL,
				notes text DEFAULT NULL,
				is_demo tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY contract_id (contract_id),
				KEY due_date (due_date),
				KEY status (status),
				KEY is_demo (is_demo)
			) {$charset};";

			return $sql;
		}

		public static function get_payments_schema() {
			$table   = self::get_payments_table();
			$charset = self::get_charset_collate();

			$sql = "CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contract_id bigint(20) unsigned NOT NULL,
				schedule_id bigint(20) unsigned DEFAULT NULL,
				payment_date date NOT NULL,
				paid_amount decimal(15,3) NOT NULL DEFAULT 0.000,
				payment_method varchar(50) NOT NULL DEFAULT 'cash',
				reference_no varchar(100) DEFAULT NULL,
				received_by bigint(20) unsigned DEFAULT NULL,
				remarks text DEFAULT NULL,
				is_demo tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY contract_id (contract_id),
				KEY schedule_id (schedule_id),
				KEY payment_date (payment_date),
				KEY is_demo (is_demo)
			) {$charset};";

			return $sql;
		}

		public static function maybe_create_tables() {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$current_installments = get_option( self::DB_VERSION_OPT_INSTALLMENTS );
			$current_schedule     = get_option( self::DB_VERSION_OPT_SCHEDULE );
			$current_payments     = get_option( self::DB_VERSION_OPT_PAYMENTS );

			if ( self::DB_VERSION !== $current_installments ) {
				dbDelta( self::get_installments_schema() );
				update_option( self::DB_VERSION_OPT_INSTALLMENTS, self::DB_VERSION );
			}

			if ( self::DB_VERSION !== $current_schedule ) {
				dbDelta( self::get_schedule_schema() );
				update_option( self::DB_VERSION_OPT_SCHEDULE, self::DB_VERSION );
			}

			if ( self::DB_VERSION !== $current_payments ) {
				dbDelta( self::get_payments_schema() );
				update_option( self::DB_VERSION_OPT_PAYMENTS, self::DB_VERSION );
			}
		}
	}
}

/**
 * admin میں load ہوتے ہی Installments tables ensure
 */
if ( is_admin() && function_exists( 'add_action' ) ) {
	add_action(
		'admin_init',
		static function () {
			if ( class_exists( 'JWPM_DB_Installments' ) ) {
				JWPM_DB_Installments::maybe_create_tables();
			}
		}
	);
}

// 🔴 یہاں پر [Installments Tables Schema] ختم ہو رہا ہے
// ✅ Syntax verified block end
/** Part 42 — Installments Tables Schema */
// 🟢 یہاں سے [Installments Tables Schema] شروع ہو رہا ہے

if ( ! class_exists( 'JWPM_DB_Installments' ) ) {

	class JWPM_DB_Installments {

		const DB_VERSION_OPT = 'jwpm_installments_db_version';
		const DB_VERSION     = '1.0.0';

		public static function get_installments_table_name() {
			global $wpdb;
			return $wpdb->prefix . 'jwpm_installments';
		}

		public static function get_schedule_table_name() {
			global $wpdb;
			return $wpdb->prefix . 'jwpm_installment_schedule';
		}

		public static function get_payments_table_name() {
			global $wpdb;
			return $wpdb->prefix . 'jwpm_installment_payments';
		}

		protected static function get_charset_collate() {
			global $wpdb;

			$charset_collate = '';
			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate .= "DEFAULT CHARACTER SET {$wpdb->charset} ";
			}
			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= "COLLATE {$wpdb->collate} ";
			}

			return $charset_collate;
		}

		public static function get_installments_schema_sql() {
			$table_name      = self::get_installments_table_name();
			$charset_collate = self::get_charset_collate();

			$sql = "CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contract_code varchar(50) NOT NULL,
				customer_id bigint(20) unsigned NOT NULL,
				sale_id bigint(20) unsigned DEFAULT NULL,
				sale_date date DEFAULT NULL,
				total_amount decimal(15,3) NOT NULL DEFAULT 0.000,
				advance_amount decimal(15,3) NOT NULL DEFAULT 0.000,
				net_amount decimal(15,3) NOT NULL DEFAULT 0.000,
				installment_count int(11) NOT NULL DEFAULT 0,
				installment_frequency varchar(20) NOT NULL DEFAULT 'monthly',
				start_date date DEFAULT NULL,
				end_date date DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				current_outstanding decimal(15,3) NOT NULL DEFAULT 0.000,
				remarks text DEFAULT NULL,
				is_demo tinyint(1) NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned DEFAULT NULL,
				updated_by bigint(20) unsigned DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY contract_code (contract_code),
				KEY customer_id (customer_id),
				KEY status (status),
				KEY is_demo (is_demo),
				KEY start_date (start_date),
				KEY end_date (end_date)
			) {$charset_collate};";

			return $sql;
		}

		public static function get_schedule_schema_sql() {
			$table_name      = self::get_schedule_table_name();
			$charset_collate = self::get_charset_collate();

			$sql = "CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contract_id bigint(20) unsigned NOT NULL,
				installment_no int(11) NOT NULL DEFAULT 1,
				due_date date NOT NULL,
				amount decimal(15,3) NOT NULL DEFAULT 0.000,
				paid_amount decimal(15,3) NOT NULL DEFAULT 0.000,
				status varchar(20) NOT NULL DEFAULT 'pending',
				paid_date date DEFAULT NULL,
				notes text DEFAULT NULL,
				is_demo tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY contract_id (contract_id),
				KEY due_date (due_date),
				KEY status (status),
				KEY is_demo (is_demo)
			) {$charset_collate};";

			return $sql;
		}

		public static function get_payments_schema_sql() {
			$table_name      = self::get_payments_table_name();
			$charset_collate = self::get_charset_collate();

			$sql = "CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contract_id bigint(20) unsigned NOT NULL,
				schedule_id bigint(20) unsigned DEFAULT NULL,
				payment_date date NOT NULL,
				amount decimal(15,3) NOT NULL DEFAULT 0.000,
				method varchar(50) NOT NULL DEFAULT 'cash',
				reference_no varchar(100) DEFAULT NULL,
				received_by bigint(20) unsigned DEFAULT NULL,
				note text DEFAULT NULL,
				is_demo tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY contract_id (contract_id),
				KEY schedule_id (schedule_id),
				KEY payment_date (payment_date),
				KEY is_demo (is_demo)
			) {$charset_collate};";

			return $sql;
		}

		/**
		 * تینوں tables کو (dbDelta) کے ذریعے ensure کرے۔
		 */
		public static function maybe_create_tables() {
			$current_version = get_option( self::DB_VERSION_OPT );
			if ( self::DB_VERSION === $current_version ) {
				return;
			}

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$schemas = array(
				self::get_installments_schema_sql(),
				self::get_schedule_schema_sql(),
				self::get_payments_schema_sql(),
			);

			foreach ( $schemas as $sql ) {
				if ( $sql ) {
					dbDelta( $sql );
				}
			}

			update_option( self::DB_VERSION_OPT, self::DB_VERSION );
		}
	}
}

/**
 * admin میں load ہوتے ہی Installments tables ensure
 */
if ( is_admin() && function_exists( 'add_action' ) ) {
	add_action(
		'admin_init',
		static function () {
			if ( class_exists( 'JWPM_DB_Installments' ) ) {
				JWPM_DB_Installments::maybe_create_tables();
			}
		}
	);
}

// 🔴 یہاں پر [Installments Tables Schema] ختم ہو رہا ہے
// ✅ Syntax verified block end
<?php
/** Part 8 — JWPM Repair Tables Schema
 * یہاں Repair Jobs کے لیے (SQL) schema helper موجود ہے۔
 */

// 🟢 یہاں سے [JWPM Repair DB Schema] شروع ہو رہا ہے

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation کے وقت dbDelta() میں use کرنے کیلئے:
 *
 * require_once ABSPATH . 'wp-admin/includes/upgrade.php';
 * foreach ( jwpm_repair_get_table_schemas() as $sql ) {
 *     dbDelta( $sql );
 * }
 */
function jwpm_repair_get_table_schemas() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$repairs         = $wpdb->prefix . 'jwpm_repairs';
	$logs            = $wpdb->prefix . 'jwpm_repair_logs';

	$sql_repairs = "CREATE TABLE {$repairs} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		job_code VARCHAR(50) NOT NULL,
		tag_no VARCHAR(50) DEFAULT '' NOT NULL,
		customer_id BIGINT(20) UNSIGNED DEFAULT 0,
		customer_name VARCHAR(191) DEFAULT '' NOT NULL,
		customer_phone VARCHAR(50) DEFAULT '' NOT NULL,
		item_description TEXT,
		job_type VARCHAR(50) DEFAULT '' NOT NULL,
		problems TEXT,
		instructions TEXT,
		received_date DATE DEFAULT NULL,
		promised_date DATE DEFAULT NULL,
		delivered_date DATE DEFAULT NULL,
		gold_weight_in DECIMAL(16,3) DEFAULT 0,
		gold_weight_out DECIMAL(16,3) DEFAULT 0,
		estimated_charges DECIMAL(16,3) DEFAULT 0,
		actual_charges DECIMAL(16,3) DEFAULT 0,
		advance_amount DECIMAL(16,3) DEFAULT 0,
		balance_amount DECIMAL(16,3) DEFAULT 0,
		payment_status VARCHAR(20) DEFAULT 'unpaid' NOT NULL,
		job_status VARCHAR(20) DEFAULT 'received' NOT NULL,
		assigned_to VARCHAR(191) DEFAULT '' NOT NULL,
		priority VARCHAR(20) DEFAULT 'normal' NOT NULL,
		workshop_notes TEXT,
		internal_remarks TEXT,
		is_demo TINYINT(1) DEFAULT 0,
		created_by BIGINT(20) UNSIGNED DEFAULT 0,
		updated_by BIGINT(20) UNSIGNED DEFAULT 0,
		created_at DATETIME DEFAULT NULL,
		updated_at DATETIME DEFAULT NULL,
		PRIMARY KEY (id),
		KEY job_code (job_code),
		KEY tag_no (tag_no),
		KEY customer_phone (customer_phone),
		KEY job_status (job_status),
		KEY priority (priority),
		KEY promised_date (promised_date)
	) {$charset_collate};";

	$sql_logs = "CREATE TABLE {$logs} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		repair_id BIGINT(20) UNSIGNED NOT NULL,
		status VARCHAR(20) DEFAULT '' NOT NULL,
		note TEXT,
		updated_at DATETIME DEFAULT NULL,
		updated_by BIGINT(20) UNSIGNED DEFAULT 0,
		PRIMARY KEY (id),
		KEY repair_id (repair_id),
		KEY status (status),
		KEY updated_at (updated_at)
	) {$charset_collate};";

	return array( $sql_repairs, $sql_logs );
}

// 🔴 یہاں پر [JWPM Repair DB Schema] ختم ہو رہا ہے
// ✅ Syntax verified block end
<?php
/** Part 8 — JWPM Repair Tables Schema
 * یہاں Repair Jobs کے لیے (SQL) schema helper موجود ہے۔
 */

// 🟢 یہاں سے [JWPM Repair DB Schema] شروع ہو رہا ہے

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation کے وقت dbDelta() میں use کرنے کیلئے:
 *
 * require_once ABSPATH . 'wp-admin/includes/upgrade.php';
 * foreach ( jwpm_repair_get_table_schemas() as $sql ) {
 *     dbDelta( $sql );
 * }
 */
function jwpm_repair_get_table_schemas() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$repairs         = $wpdb->prefix . 'jwpm_repairs';
	$logs            = $wpdb->prefix . 'jwpm_repair_logs';

	$sql_repairs = "CREATE TABLE {$repairs} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		job_code VARCHAR(50) NOT NULL,
		tag_no VARCHAR(50) DEFAULT '' NOT NULL,
		customer_id BIGINT(20) UNSIGNED DEFAULT 0,
		customer_name VARCHAR(191) DEFAULT '' NOT NULL,
		customer_phone VARCHAR(50) DEFAULT '' NOT NULL,
		item_description TEXT,
		job_type VARCHAR(50) DEFAULT '' NOT NULL,
		problems TEXT,
		instructions TEXT,
		received_date DATE DEFAULT NULL,
		promised_date DATE DEFAULT NULL,
		delivered_date DATE DEFAULT NULL,
		gold_weight_in DECIMAL(16,3) DEFAULT 0,
		gold_weight_out DECIMAL(16,3) DEFAULT 0,
		estimated_charges DECIMAL(16,3) DEFAULT 0,
		actual_charges DECIMAL(16,3) DEFAULT 0,
		advance_amount DECIMAL(16,3) DEFAULT 0,
		balance_amount DECIMAL(16,3) DEFAULT 0,
		payment_status VARCHAR(20) DEFAULT 'unpaid' NOT NULL,
		job_status VARCHAR(20) DEFAULT 'received' NOT NULL,
		assigned_to VARCHAR(191) DEFAULT '' NOT NULL,
		priority VARCHAR(20) DEFAULT 'normal' NOT NULL,
		workshop_notes TEXT,
		internal_remarks TEXT,
		is_demo TINYINT(1) DEFAULT 0,
		created_by BIGINT(20) UNSIGNED DEFAULT 0,
		updated_by BIGINT(20) UNSIGNED DEFAULT 0,
		created_at DATETIME DEFAULT NULL,
		updated_at DATETIME DEFAULT NULL,
		PRIMARY KEY (id),
		KEY job_code (job_code),
		KEY tag_no (tag_no),
		KEY customer_phone (customer_phone),
		KEY job_status (job_status),
		KEY priority (priority),
		KEY promised_date (promised_date)
	) {$charset_collate};";

	$sql_logs = "CREATE TABLE {$logs} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		repair_id BIGINT(20) UNSIGNED NOT NULL,
		status VARCHAR(20) DEFAULT '' NOT NULL,
		note TEXT,
		updated_at DATETIME DEFAULT NULL,
		updated_by BIGINT(20) UNSIGNED DEFAULT 0,
		PRIMARY KEY (id),
		KEY repair_id (repair_id),
		KEY status (status),
		KEY updated_at (updated_at)
	) {$charset_collate};";

	return array( $sql_repairs, $sql_logs );
}

// 🔴 یہاں پر [JWPM Repair DB Schema] ختم ہو رہا ہے
// ✅ Syntax verified block end
<?php
/** Part 8 — JWPM Repair Tables Schema
 * یہاں Repair Jobs کے لیے (SQL) schema helper موجود ہے۔
 */

// 🟢 یہاں سے [JWPM Repair DB Schema] شروع ہو رہا ہے

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation کے وقت dbDelta() میں use کرنے کیلئے:
 *
 * require_once ABSPATH . 'wp-admin/includes/upgrade.php';
 * foreach ( jwpm_repair_get_table_schemas() as $sql ) {
 *     dbDelta( $sql );
 * }
 */
function jwpm_repair_get_table_schemas() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$repairs         = $wpdb->prefix . 'jwpm_repairs';
	$logs            = $wpdb->prefix . 'jwpm_repair_logs';

	$sql_repairs = "CREATE TABLE {$repairs} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		job_code VARCHAR(50) NOT NULL,
		tag_no VARCHAR(50) DEFAULT '' NOT NULL,
		customer_id BIGINT(20) UNSIGNED DEFAULT 0,
		customer_name VARCHAR(191) DEFAULT '' NOT NULL,
		customer_phone VARCHAR(50) DEFAULT '' NOT NULL,
		item_description TEXT,
		job_type VARCHAR(50) DEFAULT '' NOT NULL,
		problems TEXT,
		instructions TEXT,
		received_date DATE DEFAULT NULL,
		promised_date DATE DEFAULT NULL,
		delivered_date DATE DEFAULT NULL,
		gold_weight_in DECIMAL(16,3) DEFAULT 0,
		gold_weight_out DECIMAL(16,3) DEFAULT 0,
		estimated_charges DECIMAL(16,3) DEFAULT 0,
		actual_charges DECIMAL(16,3) DEFAULT 0,
		advance_amount DECIMAL(16,3) DEFAULT 0,
		balance_amount DECIMAL(16,3) DEFAULT 0,
		payment_status VARCHAR(20) DEFAULT 'unpaid' NOT NULL,
		job_status VARCHAR(20) DEFAULT 'received' NOT NULL,
		assigned_to VARCHAR(191) DEFAULT '' NOT NULL,
		priority VARCHAR(20) DEFAULT 'normal' NOT NULL,
		workshop_notes TEXT,
		internal_remarks TEXT,
		is_demo TINYINT(1) DEFAULT 0,
		created_by BIGINT(20) UNSIGNED DEFAULT 0,
		updated_by BIGINT(20) UNSIGNED DEFAULT 0,
		created_at DATETIME DEFAULT NULL,
		updated_at DATETIME DEFAULT NULL,
		PRIMARY KEY (id),
		KEY job_code (job_code),
		KEY tag_no (tag_no),
		KEY customer_phone (customer_phone),
		KEY job_status (job_status),
		KEY priority (priority),
		KEY promised_date (promised_date)
	) {$charset_collate};";

	$sql_logs = "CREATE TABLE {$logs} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		repair_id BIGINT(20) UNSIGNED NOT NULL,
		status VARCHAR(20) DEFAULT '' NOT NULL,
		note TEXT,
		updated_at DATETIME DEFAULT NULL,
		updated_by BIGINT(20) UNSIGNED DEFAULT 0,
		PRIMARY KEY (id),
		KEY repair_id (repair_id),
		KEY status (status),
		KEY updated_at (updated_at)
	) {$charset_collate};";

	return array( $sql_repairs, $sql_logs );
}

// 🔴 یہاں پر [JWPM Repair DB Schema] ختم ہو رہا ہے
// ✅ Syntax verified block end
<?php
// ... یہاں آپ کا موجودہ class-jwpm-db.php کوڈ ہے ...

// 🟢 یہاں سے [Accounts Module DB Helpers] شروع ہو رہا ہے

/** Part 20 — Accounts Module DB Helpers */
/**
 * Accounts Module کے لئے SQL statements واپس کرنے والا helper
 * Cashbook, Expenses, Ledger تینوں tables یہاں define ہیں۔
 */
if ( ! function_exists( 'jwpm_accounts_get_tables_sql' ) ) {
    function jwpm_accounts_get_tables_sql() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $cashbook_table   = $wpdb->prefix . 'jwpm_cashbook';
        $expenses_table   = $wpdb->prefix . 'jwpm_expenses';
        $ledger_table     = $wpdb->prefix . 'jwpm_ledger';

        $sql = array();

        // Cashbook Table
        $sql[] = "CREATE TABLE {$cashbook_table} (
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
            PRIMARY KEY  (id),
            KEY entry_date (entry_date),
            KEY type (type),
            KEY category (category)
        ) {$charset_collate};";

        // Expenses Table
        $sql[] = "CREATE TABLE {$expenses_table} (
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
            PRIMARY KEY  (id),
            KEY expense_date (expense_date),
            KEY category (category)
        ) {$charset_collate};";

        // Ledger Table
        $sql[] = "CREATE TABLE {$ledger_table} (
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
            PRIMARY KEY  (id),
            KEY entry_type (entry_type),
            KEY customer_id (customer_id),
            KEY supplier_id (supplier_id)
        ) {$charset_collate};";

        return $sql;
    }
}

/**
 * Accounts tables ensure (lazy creation via dbDelta)
 * Activation میں موو بھی کیا جا سکتا ہے، لیکن ابھی کیلئے
 * یہ فنکشن ہر AJAX کال سے پہلے safe ہے۔
 */
if ( ! function_exists( 'jwpm_accounts_ensure_tables' ) ) {
    function jwpm_accounts_ensure_tables() {
        global $wpdb;

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $sql_statements = jwpm_accounts_get_tables_sql();

        foreach ( $sql_statements as $statement ) {
            dbDelta( $statement );
        }
    }
}

// 🔴 یہاں پر [Accounts Module DB Helpers] ختم ہو رہا ہے

// ✅ Syntax verified block end
