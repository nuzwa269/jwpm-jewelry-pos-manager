<?php
/**
 * JWPM_Ajax
 *
 * یہ فائل پلگ ان کی تمام AJAX کالز کو ہینڈل کرتی ہے۔
 * اس میں Inventory Class اور باقی تمام ماڈیولز (POS, Customers, Installments, Repairs, Accounts)
 * کے فنکشنز شامل ہیں۔
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Ajax {

	/**
	 * تمام (wp_ajax_*) ہُکس رجسٹر کریں
	 *
	 * یہ فنکشن Main Class یا Loader کے ذریعے کال ہوتا ہے۔
	 */
	public static function register_ajax_hooks() {
		
		// 1. Inventory Actions
		add_action( 'wp_ajax_jwpm_inventory_list_items', array( __CLASS__, 'inventory_list_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_save_item', array( __CLASS__, 'inventory_save_item' ) );
		add_action( 'wp_ajax_jwpm_inventory_delete_item', array( __CLASS__, 'inventory_delete_item' ) );
		add_action( 'wp_ajax_jwpm_inventory_import_items', array( __CLASS__, 'inventory_import_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_export_items', array( __CLASS__, 'inventory_export_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_demo_items', array( __CLASS__, 'inventory_demo_items' ) );

		// 2. POS Actions
		add_action( 'wp_ajax_jwpm_pos_search_items', array( __CLASS__, 'pos_search_items' ) );
		add_action( 'wp_ajax_jwpm_pos_get_gold_rate', array( __CLASS__, 'pos_get_gold_rate' ) );
		add_action( 'wp_ajax_jwpm_pos_search_customer', array( __CLASS__, 'pos_search_customer' ) );
		add_action( 'wp_ajax_jwpm_pos_complete_sale', array( __CLASS__, 'pos_complete_sale' ) );

		// 3. Customers Actions
		add_action( 'wp_ajax_jwpm_get_customers', array( __CLASS__, 'customers_get' ) );
		add_action( 'wp_ajax_jwpm_save_customer', array( __CLASS__, 'customers_save' ) );
		add_action( 'wp_ajax_jwpm_delete_customer', array( __CLASS__, 'customers_delete' ) );
		add_action( 'wp_ajax_jwpm_get_customer', array( __CLASS__, 'customers_get_single' ) );

		// 4. Installments Actions
		add_action( 'wp_ajax_jwpm_get_installments', array( __CLASS__, 'installments_get' ) );
		add_action( 'wp_ajax_jwpm_save_installment', array( __CLASS__, 'installments_save' ) );

		// 5. Repair Actions
		add_action( 'wp_ajax_jwpm_get_repairs', array( __CLASS__, 'repairs_get' ) );
		add_action( 'wp_ajax_jwpm_save_repair', array( __CLASS__, 'repairs_save' ) );

		// 6. Accounts Actions
		add_action( 'wp_ajax_jwpm_cashbook_fetch', array( __CLASS__, 'accounts_cashbook_fetch' ) );
		add_action( 'wp_ajax_jwpm_cashbook_save', array( __CLASS__, 'accounts_cashbook_save' ) );
		add_action( 'wp_ajax_jwpm_expenses_fetch', array( __CLASS__, 'accounts_expenses_fetch' ) );
		add_action( 'wp_ajax_jwpm_expenses_save', array( __CLASS__, 'accounts_expenses_save' ) );
		add_action( 'wp_ajax_jwpm_ledger_fetch', array( __CLASS__, 'accounts_ledger_fetch' ) );
	}

	/**
	 * مشترکہ ہیلپر: (nonce) اور (capability) چیک
	 */
	protected static function check_access( $nonce_action, $capability = 'manage_options' ) {
		check_ajax_referer( $nonce_action, 'security' ); // 'security' پیرامیٹر اکثر JS میں استعمال ہوتا ہے

		// اگر نونس name 'security' کی بجائے 'nonce' ہو (جیسا کہ Accounts میں ہے)
		if ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), $nonce_action ) ) {
			// Fallback check
		}

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'jwpm-jewelry-pos-manager' ) ),
				403
			);
		}
	}

	/**
	 * ==========================================================================
	 * 1. INVENTORY MODULE
	 * ==========================================================================
	 */

	public static function inventory_list_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		global $wpdb;
		$tables = JWPM_DB::get_table_names();

		$page     = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? max( 1, (int) $_POST['per_page'] ) : 50;
		$offset   = ( $page - 1 ) * $per_page;

		$search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$category  = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$metal     = isset( $_POST['metal'] ) ? sanitize_text_field( wp_unslash( $_POST['metal'] ) ) : '';
		$karat     = isset( $_POST['karat'] ) ? sanitize_text_field( wp_unslash( $_POST['karat'] ) ) : '';
		$status    = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$branch_id = isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0;

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $branch_id > 0 ) {
			$where   .= ' AND branch_id = %d';
			$params[] = $branch_id;
		}
		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (sku LIKE %s OR tag_serial LIKE %s OR category LIKE %s OR design_no LIKE %s)';
			$params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
		}
		if ( $category !== '' ) {
			$where   .= ' AND category = %s';
			$params[] = $category;
		}
		if ( $metal !== '' ) {
			$where   .= ' AND metal_type = %s';
			$params[] = $metal;
		}
		if ( $karat !== '' ) {
			$where   .= ' AND karat = %s';
			$params[] = $karat;
		}
		if ( $status !== '' ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		$sql_base  = "FROM {$tables['items']} {$where}";
		$count_sql = "SELECT COUNT(*) {$sql_base}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		$list_sql  = "SELECT * {$sql_base} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params_l  = array_merge( $params, array( $per_page, $offset ) );
		$items_raw = $wpdb->get_results( $wpdb->prepare( $list_sql, $params_l ), ARRAY_A );

		$items = array();
		if ( ! empty( $items_raw ) ) {
			foreach ( $items_raw as $row ) {
				$items[] = array(
					'id'            => (int) $row['id'],
					'branch_id'     => (int) $row['branch_id'],
					'sku'           => $row['sku'],
					'tag_serial'    => $row['tag_serial'],
					'category'      => $row['category'],
					'metal_type'    => $row['metal_type'],
					'karat'         => $row['karat'],
					'gross_weight'  => (float) $row['gross_weight'],
					'net_weight'    => (float) $row['net_weight'],
					'stone_type'    => $row['stone_type'],
					'stone_carat'   => isset( $row['stone_carat'] ) ? (float) $row['stone_carat'] : 0,
					'stone_qty'     => isset( $row['stone_qty'] ) ? (int) $row['stone_qty'] : 0,
					'labour_amount' => (float) $row['labour_amount'],
					'design_no'     => $row['design_no'],
					'image_id'      => isset( $row['image_id'] ) ? (int) $row['image_id'] : 0,
					'status'        => $row['status'],
					'is_demo'       => (int) $row['is_demo'],
					'created_at'    => $row['created_at'],
				);
			}
		}

		wp_send_json_success( array( 'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $per_page ) );
	}

	public static function inventory_save_item() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		global $wpdb;
		$tables = JWPM_DB::get_table_names();

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = array(
			'branch_id'     => isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0,
			'sku'           => isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '',
			'tag_serial'    => isset( $_POST['tag_serial'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_serial'] ) ) : '',
			'category'      => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'metal_type'    => isset( $_POST['metal_type'] ) ? sanitize_text_field( wp_unslash( $_POST['metal_type'] ) ) : '',
			'karat'         => isset( $_POST['karat'] ) ? sanitize_text_field( wp_unslash( $_POST['karat'] ) ) : '',
			'gross_weight'  => isset( $_POST['gross_weight'] ) ? (float) $_POST['gross_weight'] : 0,
			'net_weight'    => isset( $_POST['net_weight'] ) ? (float) $_POST['net_weight'] : 0,
			'stone_type'    => isset( $_POST['stone_type'] ) ? sanitize_text_field( wp_unslash( $_POST['stone_type'] ) ) : '',
			'stone_carat'   => isset( $_POST['stone_carat'] ) ? (float) $_POST['stone_carat'] : 0,
			'stone_qty'     => isset( $_POST['stone_qty'] ) ? (int) $_POST['stone_qty'] : 0,
			'labour_amount' => isset( $_POST['labour_amount'] ) ? (float) $_POST['labour_amount'] : 0,
			'design_no'     => isset( $_POST['design_no'] ) ? sanitize_text_field( wp_unslash( $_POST['design_no'] ) ) : '',
			'status'        => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'in_stock',
			'is_demo'       => isset( $_POST['is_demo'] ) ? (int) $_POST['is_demo'] : 0,
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%f', '%d', '%f', '%s', '%s', '%d' );

		if ( $id > 0 ) {
			$data['updated_at'] = current_time( 'mysql' );
			$format[] = '%s';
			$updated = $wpdb->update( $tables['items'], $data, array( 'id' => $id ), $format, array( '%d' ) );
			if ( false === $updated ) wp_send_json_error( array( 'message' => __( 'Failed to update item.', 'jwpm-jewelry-pos-manager' ) ), 500 );
			JWPM_DB::log_activity( get_current_user_id(), 'inventory_update', 'item', $id, $data );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$format[] = '%s';
			$inserted = $wpdb->insert( $tables['items'], $data, $format );
			if ( ! $inserted ) wp_send_json_error( array( 'message' => __( 'Failed to create item.', 'jwpm-jewelry-pos-manager' ) ), 500 );
			$id = (int) $wpdb->insert_id;
			JWPM_DB::log_activity( get_current_user_id(), 'inventory_create', 'item', $id, $data );
		}

		wp_send_json_success( array( 'id' => $id, 'message' => __( 'Item saved successfully.', 'jwpm-jewelry-pos-manager' ) ) );
	}

	public static function inventory_delete_item() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		global $wpdb;
		$tables = JWPM_DB::get_table_names();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) wp_send_json_error( array( 'message' => __( 'Invalid item ID.', 'jwpm-jewelry-pos-manager' ) ), 400 );

		$deleted = $wpdb->delete( $tables['items'], array( 'id' => $id ), array( '%d' ) );
		if ( ! $deleted ) wp_send_json_error( array( 'message' => __( 'Failed to delete item.', 'jwpm-jewelry-pos-manager' ) ), 500 );

		JWPM_DB::log_activity( get_current_user_id(), 'inventory_delete', 'item', $id );
		wp_send_json_success( array( 'message' => __( 'Item deleted successfully.', 'jwpm-jewelry-pos-manager' ) ) );
	}

	public static function inventory_import_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		wp_send_json_error( array( 'message' => __( 'Import not implemented yet.', 'jwpm-jewelry-pos-manager' ) ), 501 );
	}

	public static function inventory_export_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		wp_send_json_error( array( 'message' => __( 'Export not implemented yet.', 'jwpm-jewelry-pos-manager' ) ), 501 );
	}

	public static function inventory_demo_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'create';
		wp_send_json_success( array( 'mode' => $mode, 'message' => __( 'Demo data handler placeholder.', 'jwpm-jewelry-pos-manager' ) ) );
	}

	/**
	 * ==========================================================================
	 * 2. POS MODULE
	 * ==========================================================================
	 */

	public static function pos_search_items() {
		check_ajax_referer( 'jwpm_pos_nonce', 'security' );
		if ( ! current_user_can( 'manage_jwpm_sales' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jwpm-jewelry-pos-manager' ) ), 403 );
		}

		global $wpdb;
		if ( ! class_exists( 'JWPM_DB' ) ) wp_send_json_error( array( 'message' => 'DB Helper missing' ), 500 );
		
		$tables = JWPM_DB::get_table_names();
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$karat = isset( $_POST['karat'] ) ? sanitize_text_field( wp_unslash( $_POST['karat'] ) ) : '';
		$branch_id = isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0;

		$where = 'WHERE status != %s';
		$params = array( 'scrap' );

		if ( $branch_id > 0 ) { $where .= ' AND branch_id = %d'; $params[] = $branch_id; }
		if ( $keyword !== '' ) {
			$like = '%' . $wpdb->esc_like( $keyword ) . '%';
			$where .= ' AND (sku LIKE %s OR tag_serial LIKE %s OR category LIKE %s OR design_no LIKE %s)';
			$params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
		}
		if ( $category !== '' ) { $where .= ' AND category = %s'; $params[] = $category; }
		if ( $karat !== '' ) { $where .= ' AND karat = %s'; $params[] = $karat; }

		$sql = "SELECT id, branch_id, sku, tag_serial, category, metal_type, karat, gross_weight, net_weight, stone_type, status FROM {$tables['items']} {$where} ORDER BY created_at DESC LIMIT 30";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$items = array();
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$items[] = array(
					'id' => (int) $row['id'], 'branch_id' => (int) $row['branch_id'], 'sku' => $row['sku'],
					'tag_serial' => $row['tag_serial'], 'category' => $row['category'], 'metal_type' => $row['metal_type'],
					'karat' => $row['karat'], 'gross_weight' => (float) $row['gross_weight'], 'net_weight' => (float) $row['net_weight'],
					'stone_type' => $row['stone_type'], 'status' => $row['status'],
				);
			}
		}
		wp_send_json_success( array( 'items' => $items ) );
	}

	public static function pos_get_gold_rate() {
		check_ajax_referer( 'jwpm_pos_nonce', 'security' );
		global $wpdb;
		$tables = JWPM_DB::get_table_names();
		$val = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$tables['settings']} WHERE option_name = %s LIMIT 1", 'gold_rate_24k' ) );
		$rate = 0;
		if ( ! empty( $val ) ) {
			$decoded = maybe_unserialize( $val );
			$rate = is_numeric( $decoded ) ? (float) $decoded : ( ( is_array( $decoded ) && isset( $decoded['value'] ) ) ? (float) $decoded['value'] : 0 );
		}
		wp_send_json_success( array( 'rate' => $rate ) );
	}

	public static function pos_search_customer() {
		check_ajax_referer( 'jwpm_pos_nonce', 'security' );
		global $wpdb;
		$tables = JWPM_DB::get_table_names();
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		if ( '' === $keyword ) wp_send_json_success( array( 'customers' => array() ) );

		$like = '%' . $wpdb->esc_like( $keyword ) . '%';
		$sql = "SELECT id, name, phone, email, loyalty_points FROM {$tables['customers']} WHERE phone LIKE %s OR name LIKE %s ORDER BY created_at DESC LIMIT 20";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array( $like, $like ) ), ARRAY_A );
		
		wp_send_json_success( array( 'customers' => $rows ) );
	}

	public static function pos_complete_sale() {
		check_ajax_referer( 'jwpm_pos_nonce', 'security' );
		wp_send_json_error( array( 'message' => __( 'Backend logic pending.', 'jwpm-jewelry-pos-manager' ) ), 501 );
	}

	/**
	 * ==========================================================================
	 * 3. CUSTOMERS MODULE
	 * ==========================================================================
	 */

	private static function customers_sanitize_decimal( $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) return '0.000';
		$value = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $value );
		return number_format( floatval( $value ), 3, '.', '' );
	}

	public static function customers_get() {
		check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'jwpm-jewelry-pos-manager' ) ), 403 );

		global $wpdb;
		$table = $wpdb->prefix . 'jwpm_customers';

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$city = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$type = isset( $_POST['customer_type'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_type'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$page = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
		$perpage = isset( $_POST['per_page'] ) ? max( 1, intval( $_POST['per_page'] ) ) : 20;

		$where = 'WHERE 1=1'; $params = array();
		if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where .= ' AND (name LIKE %s OR phone LIKE %s)'; $params[] = $like; $params[] = $like; }
		if ( $city ) { $where .= ' AND city = %s'; $params[] = $city; }
		if ( $type ) { $where .= ' AND customer_type = %s'; $params[] = $type; }
		if ( $status ) { $where .= ' AND status = %s'; $params[] = $status; }

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $params ) );
		$offset = ( $page - 1 ) * $perpage;
		$params_items = array_merge( $params, array( $perpage, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", $params_items ), ARRAY_A );

		wp_send_json_success( array(
			'items' => $rows,
			'pagination' => array( 'total' => $total, 'page' => $page, 'per_page' => $perpage, 'total_page' => $perpage > 0 ? (int) ceil( $total / $perpage ) : 1 )
		) );
	}

	public static function customers_save() {
		check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'jwpm-jewelry-pos-manager' ) ), 403 );

		global $wpdb;
		$table = $wpdb->prefix . 'jwpm_customers';
		
		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( '' === $name || '' === $phone ) wp_send_json_error( array( 'message' => __( 'Name/Phone required.', 'jwpm-jewelry-pos-manager' ) ), 400 );

		$data = array(
			'name' => $name, 'phone' => $phone,
			'whatsapp' => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
			'email' => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'city' => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'area' => isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '',
			'address' => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
			'cnic' => isset( $_POST['cnic'] ) ? sanitize_text_field( wp_unslash( $_POST['cnic'] ) ) : '',
			'dob' => isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '',
			'gender' => isset( $_POST['gender'] ) ? sanitize_text_field( wp_unslash( $_POST['gender'] ) ) : '',
			'customer_type' => isset( $_POST['customer_type'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_type'] ) ) : 'walkin',
			'status' => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
			'price_group' => isset( $_POST['price_group'] ) ? sanitize_text_field( wp_unslash( $_POST['price_group'] ) ) : '',
			'tags' => isset( $_POST['tags'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tags'] ) ) : '',
			'notes' => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'credit_limit' => self::customers_sanitize_decimal( isset( $_POST['credit_limit'] ) ? wp_unslash( $_POST['credit_limit'] ) : '0' ),
		);

		if ( $id > 0 ) {
			$data['updated_by'] = get_current_user_id();
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['opening_balance'] = self::customers_sanitize_decimal( isset( $_POST['opening_balance'] ) ? wp_unslash( $_POST['opening_balance'] ) : '0' );
			$data['current_balance'] = $data['opening_balance'];
			$data['created_by'] = get_current_user_id();
			$data['is_demo'] = 0;
			$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
			$data['customer_code'] = sprintf( 'CUST-%04d', $max_id + 1 );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		wp_send_json_success( array( 'message' => __( 'Saved successfully.', 'jwpm-jewelry-pos-manager' ), 'item' => $row ) );
	}

	public static function customers_delete() {
		check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );

		global $wpdb;
		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$table = $wpdb->prefix . 'jwpm_customers';
		$wpdb->update( $table, array( 'status' => 'inactive', 'updated_by' => get_current_user_id() ), array( 'id' => $id ), null, array( '%d' ) );
		wp_send_json_success( array( 'message' => __( 'Customer marked inactive.', 'jwpm-jewelry-pos-manager' ) ) );
	}

	public static function customers_get_single() {
		check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );

		global $wpdb;
		$table = $wpdb->prefix . 'jwpm_customers';
		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) wp_send_json_error( array( 'message' => 'Not found' ), 404 );
		wp_send_json_success( array( 'item' => $row ) );
	}

	/**
	 * ==========================================================================
	 * 4. INSTALLMENTS MODULE
	 * ==========================================================================
	 */

	private static function installments_sanitize_decimal( $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) return '0.000';
		$value = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $value );
		return number_format( floatval( $value ), 3, '.', '' );
	}

	public static function installments_get() {
		check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );

		global $wpdb;
		$contracts_table = $wpdb->prefix . 'jwpm_installments';
		$customers_table = $wpdb->prefix . 'jwpm_customers';

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		$page = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
		$perpage = isset( $_POST['per_page'] ) ? max( 1, intval( $_POST['per_page'] ) ) : 20;

		$where = 'WHERE 1=1'; $params = array();
		if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where .= ' AND (c.name LIKE %s OR c.phone LIKE %s OR i.contract_code LIKE %s)'; $params[] = $like; $params[] = $like; $params[] = $like; }
		if ( $status ) { $where .= ' AND i.status = %s'; $params[] = $status; }
		if ( $date_from ) { $where .= " AND i.sale_date >= %s"; $params[] = $date_from; }
		if ( $date_to ) { $where .= " AND i.sale_date <= %s"; $params[] = $date_to; }

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$contracts_table} i LEFT JOIN {$customers_table} c ON i.customer_id = c.id {$where}", $params ) );
		$offset = ( $page - 1 ) * $perpage;
		$params_items = array_merge( $params, array( $perpage, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone FROM {$contracts_table} i LEFT JOIN {$customers_table} c ON i.customer_id = c.id {$where} ORDER BY i.created_at DESC LIMIT %d OFFSET %d", $params_items ), ARRAY_A );

		wp_send_json_success( array(
			'items' => $rows,
			'pagination' => array( 'total' => $total, 'page' => $page, 'per_page' => $perpage )
		) );
	}

	public static function installments_save() {
		check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );

		global $wpdb;
		$contracts_table = $wpdb->prefix . 'jwpm_installments';
		
		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
		if ( $customer_id <= 0 ) wp_send_json_error( array( 'message' => __( 'Select Customer.', 'jwpm-jewelry-pos-manager' ) ), 400 );

		$total = self::installments_sanitize_decimal( isset( $_POST['total_amount'] ) ? wp_unslash( $_POST['total_amount'] ) : '0' );
		$advance = self::installments_sanitize_decimal( isset( $_POST['advance_amount'] ) ? wp_unslash( $_POST['advance_amount'] ) : '0' );
		$net = self::installments_sanitize_decimal( (float) $total - (float) $advance );
		$count = isset( $_POST['installment_count'] ) ? max( 0, intval( $_POST['installment_count'] ) ) : 0;
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : current_time( 'mysql' );
		$auto = ! empty( $_POST['auto_generate_schedule'] );

		$data = array(
			'customer_id' => $customer_id, 'sale_date' => $start_date, 'total_amount' => $total,
			'advance_amount' => $advance, 'net_amount' => $net, 'installment_count' => $count,
			'start_date' => $start_date, 'status' => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
			'remarks' => isset( $_POST['remarks'] ) ? sanitize_textarea_field( wp_unslash( $_POST['remarks'] ) ) : '',
		);

		if ( $id > 0 ) {
			$data['updated_by'] = get_current_user_id();
			$wpdb->update( $contracts_table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$max = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$contracts_table}" );
			$data['contract_code'] = sprintf( 'INST-%04d', $max + 1 );
			$data['current_outstanding'] = $net;
			$data['created_by'] = get_current_user_id();
			$data['is_demo'] = 0;
			$wpdb->insert( $contracts_table, $data );
			$id = (int) $wpdb->insert_id;
		}

		if ( $auto && $count > 0 && $net > 0 ) {
			$schedule_table = $wpdb->prefix . 'jwpm_installment_schedule';
			$wpdb->delete( $schedule_table, array( 'contract_id' => $id ), array( '%d' ) );
			$per = number_format( (float) $net / (float) $count, 3, '.', '' );
			$dt = new DateTime( $start_date );
			for ( $i = 1; $i <= $count; $i++ ) {
				if ( $i > 1 ) $dt->modify( '+1 month' );
				$wpdb->insert( $schedule_table, array(
					'contract_id' => $id, 'installment_no' => $i, 'due_date' => $dt->format( 'Y-m-d' ),
					'amount' => $per, 'paid_amount' => '0.000', 'status' => 'pending', 'is_demo' => 0
				) );
			}
		}
		wp_send_json_success( array( 'message' => __( 'Saved.', 'jwpm-jewelry-pos-manager' ), 'id' => $id ) );
	}

	/**
	 * ==========================================================================
	 * 5. REPAIR JOBS MODULE
	 * ==========================================================================
	 */

	public static function repairs_get() {
		// نوٹ: repairs کے لیے REQUEST 'nonce' استعمال ہو رہا ہے
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'jwpm_repair_main_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'jwpm_repairs';
		
		$search = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';
		$where = 'WHERE 1=1'; $params = array();
		if ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= " AND (customer_name LIKE %s OR tag_no LIKE %s OR job_code LIKE %s)";
			$params[] = $like; $params[] = $like; $params[] = $like;
		}

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $params ) );
		$page = max( 1, (int) ( $_REQUEST['page'] ?? 1 ) );
		$per_page = 20;
		$offset = ( $page - 1 ) * $per_page;
		
		$params_items = array_merge( $params, array( $per_page, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY promised_date ASC, id DESC LIMIT %d OFFSET %d", $params_items ), ARRAY_A );

		wp_send_json_success( array( 'items' => $rows, 'pagination' => array( 'total' => $total, 'page' => $page ) ) );
	}

	public static function repairs_save() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'jwpm_repair_main_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'jwpm_repairs';
		$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;

		$data = array(
			'customer_name' => sanitize_text_field( $_REQUEST['customer_name'] ?? '' ),
			'customer_phone' => sanitize_text_field( $_REQUEST['customer_phone'] ?? '' ),
			'tag_no' => sanitize_text_field( $_REQUEST['tag_no'] ?? '' ),
			'item_description' => sanitize_text_field( $_REQUEST['item_description'] ?? '' ),
			'job_status' => sanitize_text_field( $_REQUEST['job_status'] ?? 'received' ),
			'estimated_charges' => (float) ( $_REQUEST['estimated_charges'] ?? 0 ),
			'advance_amount' => (float) ( $_REQUEST['advance_amount'] ?? 0 ),
			'promised_date' => sanitize_text_field( $_REQUEST['promised_date'] ?? '' ),
		);

		if ( $id ) {
			$data['updated_at'] = current_time( 'mysql' );
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$max = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
			$data['job_code'] = sprintf( 'RJ-%04d', $max + 1 );
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = $wpdb->insert_id;
		}
		wp_send_json_success( array( 'id' => $id ) );
	}

	/**
	 * ==========================================================================
	 * 6. ACCOUNTS MODULE
	 * ==========================================================================
	 */

	private static function accounts_check_access( $nonce_name ) {
		check_ajax_referer( $nonce_name, 'nonce' );
		if ( ! current_user_can( 'jwpm_view_accounts' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'jwpm-jewelry-pos-manager' ) ), 403 );
		}
	}

	public static function accounts_cashbook_fetch() {
		self::accounts_check_access( 'jwpm_cashbook_nonce' );
		global $wpdb;
		$table = $wpdb->prefix . 'jwpm_cashbook';
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY entry_date DESC, id DESC LIMIT 50", ARRAY_A );
		
		// Summary
		$balance = $wpdb->get_row( "SELECT SUM(CASE WHEN type='in' THEN amount ELSE 0 END) as total_in, SUM(CASE WHEN type='out' THEN amount ELSE 0 END) as total_out FROM {$table}", ARRAY_A );
		$closing = (float)($balance['total_in'] ?? 0) - (float)($balance['total_out'] ?? 0);

		wp_send_json_success( array( 'items' => $rows, 'summary' => array( 'closing' => $closing ) ) );
	}

	public static function accounts_cashbook_save() {
		self::accounts_check_access( 'jwpm_cashbook_nonce' );
		global $wpdb;
		$table = $wpdb->prefix . 'jwpm_cashbook';
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		
		$data = array(
			'entry_date' => sanitize_text_field( $_POST['entry_date'] ),
			'type' => sanitize_text_field( $_POST['type'] ),
			'amount' => floatval( $_POST['amount'] ),
			'category' => sanitize_text_field( $_POST['category'] ),
			'remarks' => sanitize_textarea_field( $_POST['remarks'] ?? '' ),
			'updated_at' => current_time( 'mysql' )
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
		}
		wp_send_json_success( array( 'message' => __( 'Saved.', 'jwpm-jewelry-pos-manager' ) ) );
	}

	public static function accounts_expenses_fetch() {
		self::accounts_check_access( 'jwpm_expenses_nonce' );
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}jwpm_expenses ORDER BY expense_date DESC LIMIT 50", ARRAY_A );
		wp_send_json_success( array( 'items' => $rows ) );
	}

	public static function accounts_expenses_save() {
		self::accounts_check_access( 'jwpm_expenses_nonce' );
		global $wpdb;
		$table = $wpdb->prefix . 'jwpm_expenses';
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$data = array(
			'expense_date' => sanitize_text_field( $_POST['expense_date'] ),
			'category' => sanitize_text_field( $_POST['category'] ),
			'amount' => floatval( $_POST['amount'] ),
			'vendor' => sanitize_text_field( $_POST['vendor'] ?? '' ),
			'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' ),
			'updated_at' => current_time( 'mysql' )
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
		}
		wp_send_json_success( array( 'message' => __( 'Expense Saved.', 'jwpm-jewelry-pos-manager' ) ) );
	}

	public static function accounts_ledger_fetch() {
		self::accounts_check_access( 'jwpm_ledger_nonce' );
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}jwpm_ledger ORDER BY created_at DESC LIMIT 50", ARRAY_A );
		$sum = $wpdb->get_row( "SELECT SUM(debit) as d, SUM(credit) as c FROM {$wpdb->prefix}jwpm_ledger", ARRAY_A );
		wp_send_json_success( array( 'items' => $rows, 'summary' => array( 'balance' => (float)$sum['d'] - (float)$sum['c'] ) ) );
	}
}
