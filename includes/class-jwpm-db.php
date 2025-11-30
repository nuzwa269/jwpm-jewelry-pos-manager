<?php
/**
 * JWPM_DB
 *
 * یہ کلاس تمام (JWPM) ڈیٹا بیس ٹیبلز کے لیے مرکزی ہیلپر ہے۔
 * - تمام ٹیبل نام ایک جگہ
 * - (dbDelta) کے ذریعے create / upgrade
 * - Activity Log
 * - Reports / Dashboard / Analytics helper methods
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 🟢 یہاں سے JWPM_DB کلاس شروع ہو رہا ہے
class JWPM_DB {

	/**
	 * تمام ٹیبل نام ایک جگہ سے مینیج کرنے کے لیے
	 *
	 * @return array
	 */
	public static function get_table_names() {
		global $wpdb;

		$prefix = $wpdb->prefix;

		$tables = array(
			// بنیادی ماڈیولز
			'branches'              => $prefix . 'jwpm_branches',
			'items'                 => $prefix . 'jwpm_items',
			'stock_ledger'          => $prefix . 'jwpm_stock_ledger',
			'customers'             => $prefix . 'jwpm_customers',
			'sales'                 => $prefix . 'jwpm_sales',
			'sale_items'            => $prefix . 'jwpm_sale_items',
			'installments'          => $prefix . 'jwpm_installments',
			'installment_payments'  => $prefix . 'jwpm_installment_payments',
			// نئی schedule ٹیبل (AJAX میں استعمال)
			'installment_schedule'  => $prefix . 'jwpm_installment_schedule',
			'purchases'             => $prefix . 'jwpm_purchases',
			'purchase_items'        => $prefix . 'jwpm_purchase_items',
			'repair_jobs'           => $prefix . 'jwpm_repair_jobs',
			'repair_logs'           => $prefix . 'jwpm_repair_logs',
			'custom_orders'         => $prefix . 'jwpm_custom_orders',
			'activity_log'          => $prefix . 'jwpm_activity_log',
			'settings'              => $prefix . 'jwpm_settings',

			// اکاؤنٹس ماڈیول
			'cashbook'              => $prefix . 'jwpm_cashbook',
			'expenses'              => $prefix . 'jwpm_expenses',
			'ledger'                => $prefix . 'jwpm_ledger',
		);

		// AJAX کو 'repairs' key بھی چاہیے، اس لیے alias:
		$tables['repairs'] = $tables['repair_jobs'];

		return $tables;
	}

	/**
	 * (dbDelta) کے ذریعے تمام ٹیبلز بنائیں / اپڈیٹ کریں
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::get_table_names();

		$sql = array();

		// 1. برانچز
		$sql[] = "CREATE TABLE {$tables['branches']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
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

		// 2. کسٹمرز
		$sql[] = "CREATE TABLE {$tables['customers']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT(20) UNSIGNED NOT NULL,
			name VARCHAR(191) NOT NULL,
			phone VARCHAR(50) NOT NULL,
			email VARCHAR(191) NULL,
			address TEXT NULL,
			total_sales DECIMAL(18,2) NOT NULL DEFAULT 0,
			balance_due DECIMAL(18,2) NOT NULL DEFAULT 0,
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY phone (phone),
			KEY branch_id (branch_id)
		) $charset_collate;";

		// 3. آئٹمز
		$sql[] = "CREATE TABLE {$tables['items']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT(20) UNSIGNED NOT NULL,
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
			image_id BIGINT(20) UNSIGNED NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'in_stock',
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY sku (sku),
			KEY tag_serial (tag_serial),
			KEY branch_id (branch_id),
			KEY category (category),
			KEY status (status)
		) $charset_collate;";

		// 4. اسٹاک لیجر
		$sql[] = "CREATE TABLE {$tables['stock_ledger']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			item_id BIGINT(20) UNSIGNED NOT NULL,
			branch_id BIGINT(20) UNSIGNED NOT NULL,
			action_type VARCHAR(50) NOT NULL,
			quantity DECIMAL(18,6) NOT NULL DEFAULT 1,
			weight DECIMAL(18,6) NULL,
			ref_type VARCHAR(50) NULL,
			ref_id BIGINT(20) UNSIGNED NULL,
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY item_id (item_id),
			KEY branch_id (branch_id),
			KEY action_type (action_type),
			KEY created_at (created_at)
		) $charset_collate;";

		// 5. سیلز (انوائس ہیڈر)
		$sql[] = "CREATE TABLE {$tables['sales']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT(20) UNSIGNED NOT NULL,
			customer_id BIGINT(20) UNSIGNED NULL,
			invoice_no VARCHAR(100) NOT NULL,
			total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			final_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			payment_mode VARCHAR(50) NOT NULL,
			is_installment TINYINT(1) NOT NULL DEFAULT 0,
			payment_meta LONGTEXT NULL,
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY invoice_no (invoice_no),
			KEY branch_id (branch_id),
			KEY customer_id (customer_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// 6. سیل آئٹمز (لائن آئٹمز)
		$sql[] = "CREATE TABLE {$tables['sale_items']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sale_id BIGINT(20) UNSIGNED NOT NULL,
			item_id BIGINT(20) UNSIGNED NOT NULL,
			quantity DECIMAL(18,6) NOT NULL DEFAULT 1,
			unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
			making_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY sale_id (sale_id),
			KEY item_id (item_id)
		) $charset_collate;";

		// 7. قسطوں کے کنٹریکٹس (installments)
		$sql[] = "CREATE TABLE {$tables['installments']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT(20) UNSIGNED NOT NULL,
			sale_id BIGINT(20) UNSIGNED NULL,
			contract_code VARCHAR(100) NOT NULL,
			total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			advance_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			net_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			installment_count INT NOT NULL,
			installment_frequency VARCHAR(30) NOT NULL,
			start_date DATE NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'active',
			remarks TEXT NULL,
			current_outstanding DECIMAL(18,2) NOT NULL DEFAULT 0,
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY contract_code (contract_code),
			KEY customer_id (customer_id)
		) $charset_collate;";

		// 🔸 نئی Installment Schedule Table (AJAX میں استعمال)
		$sql[] = "CREATE TABLE {$tables['installment_schedule']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			contract_id BIGINT(20) UNSIGNED NOT NULL,
			installment_no INT NOT NULL,
			due_date DATE NOT NULL,
			amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			paid_date DATE NULL,
			is_demo TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY contract_id (contract_id),
			KEY due_date (due_date),
			KEY status (status)
		) $charset_collate;";

		// 8. قسطوں کی ادائیگیاں (summary style)
		$sql[] = "CREATE TABLE {$tables['installment_payments']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			installment_id BIGINT(20) UNSIGNED NOT NULL,
			payment_date DATE NOT NULL,
			amount DECIMAL(18,2) NOT NULL,
			method VARCHAR(50) NOT NULL,
			reference_no VARCHAR(191) NULL,
			received_by BIGINT(20) UNSIGNED NULL,
			note TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY installment_id (installment_id),
			KEY payment_date (payment_date)
		) $charset_collate;";

		// 9. پرچیز (سپلائر سے خریداری)
		$sql[] = "CREATE TABLE {$tables['purchases']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT(20) UNSIGNED NOT NULL,
			supplier_id BIGINT(20) UNSIGNED NULL,
			invoice_no VARCHAR(100) NOT NULL,
			total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY branch_id (branch_id),
			KEY supplier_id (supplier_id)
		) $charset_collate;";

		// 10. پرچیز آئٹمز
		$sql[] = "CREATE TABLE {$tables['purchase_items']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			purchase_id BIGINT(20) UNSIGNED NOT NULL,
			item_id BIGINT(20) UNSIGNED NULL,
			description TEXT NULL,
			weight DECIMAL(18,6) NULL,
			rate DECIMAL(18,6) NULL,
			amount DECIMAL(18,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY purchase_id (purchase_id)
		) $charset_collate;";

		// 11. ریپیئر جابز
		$sql[] = "CREATE TABLE {$tables['repair_jobs']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id BIGINT(20) UNSIGNED NOT NULL,
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
			job_status VARCHAR(30) NOT NULL DEFAULT 'received',
			priority VARCHAR(20) NOT NULL DEFAULT 'normal',
			assigned_to BIGINT(20) UNSIGNED NULL,
			tag_no VARCHAR(100) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY job_code (job_code),
			KEY branch_id (branch_id),
			KEY customer_phone (customer_phone)
		) $charset_collate;";

		// 12. ریپیئر لاگز
		$sql[] = "CREATE TABLE {$tables['repair_logs']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			repair_id BIGINT(20) UNSIGNED NOT NULL,
			status VARCHAR(30) NOT NULL,
			note TEXT NULL,
			updated_by BIGINT(20) UNSIGNED NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY repair_id (repair_id)
		) $charset_collate;";

		// 13. کسٹم آرڈرز
		$sql[] = "CREATE TABLE {$tables['custom_orders']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT(20) UNSIGNED NULL,
			branch_id BIGINT(20) UNSIGNED NOT NULL,
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

		// 14. ایکٹیویٹی لاگ (آڈٹ ٹریل)
		$sql[] = "CREATE TABLE {$tables['activity_log']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NULL,
			action VARCHAR(191) NOT NULL,
			entity_type VARCHAR(50) NULL,
			entity_id BIGINT(20) UNSIGNED NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY entity_type (entity_type),
			KEY entity_id (entity_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// 15. سیٹنگز (گلوبل آپشنز)
		$sql[] = "CREATE TABLE {$tables['settings']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			option_name VARCHAR(191) NOT NULL,
			option_value LONGTEXT NULL,
			autoload VARCHAR(20) NOT NULL DEFAULT 'yes',
			PRIMARY KEY  (id),
			UNIQUE KEY option_name (option_name)
		) $charset_collate;";

		// 16. Cashbook (روزنامچہ)
		$sql[] = "CREATE TABLE {$tables['cashbook']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_date DATE NOT NULL,
			type VARCHAR(10) NOT NULL,
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
		) $charset_collate;";

		// 17. Expenses (اخراجات)
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
			PRIMARY KEY  (id),
			KEY expense_date (expense_date),
			KEY category (category)
		) $charset_collate;";

		// 18. Ledger (کھاتہ جات)
		$sql[] = "CREATE TABLE {$tables['ledger']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_type VARCHAR(50) NOT NULL,
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
		) $charset_collate;";

		// تمام statements چلائیں
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * ضرورت پڑنے پر (DB) اپ گریڈ – ورژن کمپئیر کر کے ٹیبلز اپڈیٹ
	 *
	 * نوٹ: main plugin file میں کہیں define کریں:
	 * define( 'JWPM_DB_VERSION', '1.0.0' );
	 */
	public static function maybe_upgrade() {
		if ( ! defined( 'JWPM_DB_VERSION' ) ) {
			return;
		}

		$current = get_option( 'jwpm_db_version' );

		if ( false === $current || version_compare( $current, JWPM_DB_VERSION, '<' ) ) {
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
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
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
				'user_id'     => (int) $user_id,
				'action'      => $action,
				'entity_type' => $entity_type,
				'entity_id'   => (int) $entity_id,
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

	// 🔴 یہاں تک پرانا core مکمل ہوا
	// 🟢 یہاں سے Analytics / Helper Methods شروع ہو رہے ہیں

	/**
	 * Inventory list کے لیے helper
	 *
	 * @param array $filters
	 * @return array { items[], total }
	 */
	public static function get_items_list( $filters = array() ) {
		global $wpdb;
		$tables = self::get_table_names();
		$table  = $tables['items'];

		$defaults = array(
			'page'      => 1,
			'per_page'  => 50,
			'search'    => '',
			'category'  => '',
			'metal'     => '',
			'karat'     => '',
			'status'    => '',
			'branch_id' => 0,
		);

		$filters = wp_parse_args( $filters, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( (int) $filters['branch_id'] > 0 ) {
			$where     .= ' AND branch_id = %d';
			$params[]   = (int) $filters['branch_id'];
		}

		if ( '' !== $filters['search'] ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where   .= ' AND (sku LIKE %s OR tag_serial LIKE %s OR category LIKE %s OR design_no LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $filters['category'] ) {
			$where   .= ' AND category = %s';
			$params[] = $filters['category'];
		}
		if ( '' !== $filters['metal'] ) {
			$where   .= ' AND metal_type = %s';
			$params[] = $filters['metal'];
		}
		if ( '' !== $filters['karat'] ) {
			$where   .= ' AND karat = %s';
			$params[] = $filters['karat'];
		}
		if ( '' !== $filters['status'] ) {
			$where   .= ' AND status = %s';
			$params[] = $filters['status'];
		}

		$sql_base  = "FROM {$table} {$where}";
		$count_sql = "SELECT COUNT(*) {$sql_base}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		$page     = max( 1, (int) $filters['page'] );
		$per_page = max( 1, (int) $filters['per_page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$list_sql = "SELECT * {$sql_base} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params_l = array_merge( $params, array( $per_page, $offset ) );
		$rows     = $wpdb->get_results( $wpdb->prepare( $list_sql, $params_l ), ARRAY_A );

		return array(
			'items' => $rows,
			'total' => $total,
		);
	}

	/**
	 * Sales report data (date range کے ساتھ)
	 *
	 * @param array $range ['from' => 'Y-m-d', 'to' => 'Y-m-d']
	 * @return array
	 */
	public static function get_sales_data( $range = array() ) {
		global $wpdb;
		$tables = self::get_table_names();
		$sales  = $tables['sales'];

		$from = ! empty( $range['from'] ) ? $range['from'] : date( 'Y-m-01' );
		$to   = ! empty( $range['to'] ) ? $range['to'] : date( 'Y-m-t' );

		// روزانہ summary
		$sql = "
			SELECT DATE(created_at) AS sale_date,
				   COUNT(*) as invoices,
				   SUM(final_amount) as total_amount,
				   SUM(discount_amount) as total_discount
			FROM {$sales}
			WHERE created_at BETWEEN %s AND %s
			GROUP BY DATE(created_at)
			ORDER BY sale_date ASC
		";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A );

		$summary = array(
			'total_invoices' => 0,
			'total_sales'    => 0,
			'total_discount' => 0,
		);

		foreach ( $rows as $r ) {
			$summary['total_invoices'] += (int) $r['invoices'];
			$summary['total_sales']    += (float) $r['total_amount'];
			$summary['total_discount'] += (float) $r['total_discount'];
		}

		return array(
			'rows'    => $rows,
			'summary' => $summary,
		);
	}

	/**
	 * کسی ایک customer کے لیے basic stats
	 *
	 * @param int $customer_id
	 * @return array
	 */
	public static function get_customer_stats( $customer_id ) {
		global $wpdb;
		$tables = self::get_table_names();

		$customers = $tables['customers'];
		$sales     = $tables['sales'];
		$install   = $tables['installments'];

		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$customers} WHERE id = %d",
				$customer_id
			),
			ARRAY_A
		);

		if ( ! $customer ) {
			return array(
				'found' => false,
			);
		}

		$stats = array(
			'found'            => true,
			'customer'         => $customer,
			'total_invoices'   => 0,
			'total_sales'      => 0.0,
			'last_sale_date'   => null,
			'installments'     => array(
				'active'   => 0,
				'overdue'  => 0,
				'closed'   => 0,
				'outstanding' => 0.0,
			),
		);

		// Sales summary
		$sales_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as invoices, SUM(final_amount) as total_amount, MAX(created_at) as last_date
				 FROM {$sales}
				 WHERE customer_id = %d",
				$customer_id
			),
			ARRAY_A
		);

		if ( $sales_row ) {
			$stats['total_invoices'] = (int) $sales_row['invoices'];
			$stats['total_sales']    = (float) $sales_row['total_amount'];
			$stats['last_sale_date'] = $sales_row['last_date'];
		}

		// Installments summary (اگر table میں current_outstanding ہے)
		$inst_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, current_outstanding FROM {$install} WHERE customer_id = %d",
				$customer_id
			),
			ARRAY_A
		);

		if ( $inst_rows ) {
			foreach ( $inst_rows as $r ) {
				$st = $r['status'];
				if ( isset( $stats['installments'][ $st ] ) ) {
					$stats['installments'][ $st ]++;
				} elseif ( 'closed' === $st || 'completed' === $st ) {
					$stats['installments']['closed']++;
				}
				$stats['installments']['outstanding'] += (float) $r['current_outstanding'];
			}
		}

		return $stats;
	}

	/**
	 * Profit Calculation (basic gross profit)
	 *
	 * نوٹ: ابھی ہمارے پاس cost.price کا الگ فیلڈ نہیں، اس لیے
	 * یہ method فی الحال sales.final_amount کو ہی profit سمجھ کر summary دیتا ہے۔
	 * مستقبل میں purchase/cost structure add ہونے پر اسے تبدیل کیا جا سکتا ہے۔
	 *
	 * @param array $filters
	 * @return array
	 */
	public static function calculate_profit( $filters = array() ) {
		global $wpdb;
		$tables = self::get_table_names();
		$sales  = $tables['sales'];

		$from = ! empty( $filters['from'] ) ? $filters['from'] : date( 'Y-m-01' );
		$to   = ! empty( $filters['to'] ) ? $filters['to'] : date( 'Y-m-t' );

		$sql = "
			SELECT SUM(final_amount) as total_sales,
				   SUM(discount_amount) as total_discount,
				   COUNT(*) as invoices
			FROM {$sales}
			WHERE created_at BETWEEN %s AND %s
		";

		$row = $wpdb->get_row(
			$wpdb->prepare(
				$sql,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);

		$total_sales    = (float) ( $row['total_sales'] ?? 0 );
		$total_discount = (float) ( $row['total_discount'] ?? 0 );
		$invoices       = (int) ( $row['invoices'] ?? 0 );

		// فی الحال profit = total_sales (placeholder)
		$profit = $total_sales;

		return array(
			'from'           => $from,
			'to'             => $to,
			'total_sales'    => $total_sales,
			'total_discount' => $total_discount,
			'invoices'       => $invoices,
			'profit'         => $profit,
			'note'           => 'Cost structure نہ ہونے کی وجہ سے profit = total_sales لیا جا رہا ہے۔',
		);
	}

	/**
	 * Stock alerts (low stock وغیرہ) – basic aggregation
	 *
	 * @return array
	 */
	public static function get_stock_alerts() {
		global $wpdb;
		$tables = self::get_table_names();
		$items  = $tables['items'];

		// فی الحال logic: ہر category/metal/karat کی in_stock count
		// اگر count <= 3 ہو تو low stock سمجھیں۔
		$sql = "
			SELECT category, metal_type, karat,
				   COUNT(*) as qty
			FROM {$items}
			WHERE status = 'in_stock'
			GROUP BY category, metal_type, karat
			HAVING qty <= 3
			ORDER BY qty ASC
		";

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return array(
			'alerts' => $rows,
		);
	}

	/**
	 * Dashboard stats (high level summary)
	 *
	 * @return array
	 */
	public static function get_dashboard_stats() {
		global $wpdb;
		$tables = self::get_table_names();

		$sales       = $tables['sales'];
		$customers   = $tables['customers'];
		$items       = $tables['items'];
		$installment = $tables['installments'];

		$today = current_time( 'Y-m-d' );
		$month = date( 'Y-m', current_time( 'timestamp' ) );

		// آج کی سیل
		$row_today = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as invoices, SUM(final_amount) as total
				 FROM {$sales}
				 WHERE DATE(created_at) = %s",
				$today
			),
			ARRAY_A
		);

		// مہینے کی سیل
		$row_month = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as invoices, SUM(final_amount) as total
				 FROM {$sales}
				 WHERE DATE_FORMAT(created_at,'%%Y-%%m') = %s",
				$month
			),
			ARRAY_A
		);

		// کل customers
		$total_customers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$customers}" );

		// Inventory summary
		$row_inv = $wpdb->get_row(
			"SELECT COUNT(*) as in_stock_items, SUM(net_weight) as total_weight
			 FROM {$items}
			 WHERE status = 'in_stock'",
			ARRAY_A
		);

		// Installments due (active)
		$active_installments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$installment} WHERE status = 'active'"
		);

		$alerts = self::get_stock_alerts();

		return array(
			'sales_today'     => (float) ( $row_today['total'] ?? 0 ),
			'sales_today_cnt' => (int) ( $row_today['invoices'] ?? 0 ),
			'sales_month'     => (float) ( $row_month['total'] ?? 0 ),
			'sales_month_cnt' => (int) ( $row_month['invoices'] ?? 0 ),
			'customers_count' => $total_customers,
			'inventory_items' => (int) ( $row_inv['in_stock_items'] ?? 0 ),
			'inventory_weight'=> (float) ( $row_inv['total_weight'] ?? 0 ),
			'installments_active' => $active_installments,
			'low_stock_count' => isset( $alerts['alerts'] ) ? count( $alerts['alerts'] ) : 0,
		);
	}

	/**
	 * آج کے POS stats (POS AJAX کے لیے)
	 *
	 * @return array
	 */
	public static function get_today_pos_stats() {
		global $wpdb;
		$tables = self::get_table_names();
		$sales  = $tables['sales'];

		$today = current_time( 'Y-m-d' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as invoices,
				        SUM(final_amount) as amount,
				        SUM(is_installment) as installment_sales
				 FROM {$sales}
				 WHERE DATE(created_at) = %s",
				$today
			),
			ARRAY_A
		);

		return array(
			'sales_count'      => (int) ( $row['invoices'] ?? 0 ),
			'sales_amount'     => (float) ( $row['amount'] ?? 0 ),
			'installment_sales'=> (int) ( $row['installment_sales'] ?? 0 ),
		);
	}

	/**
	 * Activity log – Dashboard recent activity کے لیے
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function get_recent_activity( $limit = 20 ) {
		global $wpdb;
		$tables = self::get_table_names();
		$log    = $tables['activity_log'];

		$limit = max( 1, (int) $limit );

		$sql = $wpdb->prepare(
			"SELECT * FROM {$log} ORDER BY created_at DESC, id DESC LIMIT %d",
			$limit
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Inventory movement report (stock ledger پر مبنی)
	 *
	 * @param array $range
	 * @return array
	 */
	public static function get_inventory_movement( $range = array() ) {
		global $wpdb;
		$tables = self::get_table_names();
		$ledger = $tables['stock_ledger'];

		$from = ! empty( $range['from'] ) ? $range['from'] : date( 'Y-m-01' );
		$to   = ! empty( $range['to'] ) ? $range['to'] : date( 'Y-m-t' );

		$sql = "
			SELECT DATE(created_at) as movement_date,
			       action_type,
			       COUNT(*) as entries,
			       SUM(quantity) as total_qty,
			       SUM(weight) as total_weight
			FROM {$ledger}
			WHERE created_at BETWEEN %s AND %s
			GROUP BY DATE(created_at), action_type
			ORDER BY movement_date ASC
		";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $from . ' 00:00:00', $to . ' 23:59:59' ),
			ARRAY_A
		);

		return array(
			'rows' => $rows,
		);
	}

	/**
	 * Expense report (category wise)
	 *
	 * @param array $filters
	 * @return array
	 */
	public static function get_expense_report( $filters = array() ) {
		global $wpdb;
		$tables  = self::get_table_names();
		$expense = $tables['expenses'];

		$from = ! empty( $filters['from'] ) ? $filters['from'] : date( 'Y-m-01' );
		$to   = ! empty( $filters['to'] ) ? $filters['to'] : date( 'Y-m-t' );

		$sql = "
			SELECT category,
				   SUM(amount) as total_amount,
				   COUNT(*) as entries
			FROM {$expense}
			WHERE expense_date BETWEEN %s AND %s
			GROUP BY category
			ORDER BY total_amount DESC
		";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $from, $to ),
			ARRAY_A
		);

		$total = 0;
		foreach ( $rows as $r ) {
			$total += (float) $r['total_amount'];
		}

		return array(
			'rows'   => $rows,
			'total'  => $total,
			'from'   => $from,
			'to'     => $to,
		);
	}

	/**
	 * Cashflow report (cashbook in/out summary)
	 *
	 * @param array $filters
	 * @return array
	 */
	public static function get_cashflow_report( $filters = array() ) {
		global $wpdb;
		$tables   = self::get_table_names();
		$cashbook = $tables['cashbook'];

		$from = ! empty( $filters['from'] ) ? $filters['from'] : date( 'Y-m-01' );
		$to   = ! empty( $filters['to'] ) ? $filters['to'] : date( 'Y-m-t' );

		// روزانہ کی سطح پر in/out
		$sql = "
			SELECT entry_date,
			       SUM( CASE WHEN type = 'in'  THEN amount ELSE 0 END ) as total_in,
			       SUM( CASE WHEN type = 'out' THEN amount ELSE 0 END ) as total_out
			FROM {$cashbook}
			WHERE entry_date BETWEEN %s AND %s
			GROUP BY entry_date
			ORDER BY entry_date ASC
		";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $from, $to ),
			ARRAY_A
		);

		$summary = array(
			'total_in'  => 0,
			'total_out' => 0,
			'net'       => 0,
		);

		foreach ( $rows as $r ) {
			$summary['total_in']  += (float) $r['total_in'];
			$summary['total_out'] += (float) $r['total_out'];
		}
		$summary['net'] = $summary['total_in'] - $summary['total_out'];

		return array(
			'rows'    => $rows,
			'summary' => $summary,
			'from'    => $from,
			'to'      => $to,
		);
	}

	// 🔴 یہاں پر Analytics / Helper Methods ختم ہو رہے ہیں
	// ✅ Syntax verified block end
}
// 🔴 یہاں پر JWPM_DB کلاس ختم ہو رہی ہے
// ✅ Syntax verified block end
