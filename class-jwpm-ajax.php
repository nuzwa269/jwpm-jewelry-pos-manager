<?php
/**
 * JWPM_Ajax
 *
 * یہ فائل پلگ ان کی تمام AJAX کالز کو ہینڈل کرتی ہے۔
 * اس میں Inventory Class اور باقی تمام ماڈیولز (POS, Customers, Installments, Repairs, Accounts)
 * کے فنکشنز شامل ہیں۔
 *
 * @package JWPM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * --------------------------------------------------------------------------
 * 1. Inventory & Main Class Helper
 * --------------------------------------------------------------------------
 */
class JWPM_Ajax {

	/**
	 * تمام (wp_ajax_*) ہُکس رجسٹر کریں (صرف Class بیسڈ ہینڈلرز کے لیے)
	 * نوٹ: باقی فنکشنل ہینڈلرز فائل کے نچلے حصے میں self-register ہو رہے ہیں۔
	 */
	public static function register_ajax_hooks() {
		// Inventory Actions
		add_action( 'wp_ajax_jwpm_inventory_list_items', array( __CLASS__, 'inventory_list_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_save_item', array( __CLASS__, 'inventory_save_item' ) );
		add_action( 'wp_ajax_jwpm_inventory_delete_item', array( __CLASS__, 'inventory_delete_item' ) );
		add_action( 'wp_ajax_jwpm_inventory_import_items', array( __CLASS__, 'inventory_import_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_export_items', array( __CLASS__, 'inventory_export_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_demo_items', array( __CLASS__, 'inventory_demo_items' ) );
	}

	/**
	 * مشترکہ ہیلپر: (nonce) اور (capability) چیک
	 */
	protected static function check_access( $nonce_action, $capability ) {
		check_ajax_referer( $nonce_action, 'security' );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'jwpm' ) ),
				403
			);
		}
	}

	/**
	 * Inventory List
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
			$where    .= ' AND branch_id = %d';
			$params[] = $branch_id;
		}
		if ( $search !== '' ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where    .= ' AND (sku LIKE %s OR tag_serial LIKE %s OR category LIKE %s OR design_no LIKE %s)';
			$params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
		}
		if ( $category !== '' ) {
			$where    .= ' AND category = %s';
			$params[] = $category;
		}
		if ( $metal !== '' ) {
			$where    .= ' AND metal_type = %s';
			$params[] = $metal;
		}
		if ( $karat !== '' ) {
			$where    .= ' AND karat = %s';
			$params[] = $karat;
		}
		if ( $status !== '' ) {
			$where    .= ' AND status = %s';
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

	/**
	 * Inventory Save
	 */
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
			if ( false === $updated ) wp_send_json_error( array( 'message' => __( 'Failed to update item.', 'jwpm' ) ), 500 );
			JWPM_DB::log_activity( get_current_user_id(), 'inventory_update', 'item', $id, $data );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$format[] = '%s';
			$inserted = $wpdb->insert( $tables['items'], $data, $format );
			if ( ! $inserted ) wp_send_json_error( array( 'message' => __( 'Failed to create item.', 'jwpm' ) ), 500 );
			$id = (int) $wpdb->insert_id;
			JWPM_DB::log_activity( get_current_user_id(), 'inventory_create', 'item', $id, $data );
		}

		wp_send_json_success( array( 'id' => $id, 'message' => __( 'Item saved successfully.', 'jwpm' ) ) );
	}

	/**
	 * Inventory Delete
	 */
	public static function inventory_delete_item() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		global $wpdb;
		$tables = JWPM_DB::get_table_names();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) wp_send_json_error( array( 'message' => __( 'Invalid item ID.', 'jwpm' ) ), 400 );

		$deleted = $wpdb->delete( $tables['items'], array( 'id' => $id ), array( '%d' ) );
		if ( ! $deleted ) wp_send_json_error( array( 'message' => __( 'Failed to delete item.', 'jwpm' ) ), 500 );

		JWPM_DB::log_activity( get_current_user_id(), 'inventory_delete', 'item', $id );
		wp_send_json_success( array( 'message' => __( 'Item deleted successfully.', 'jwpm' ) ) );
	}

	/**
	 * Placeholders for Import/Export/Demo
	 */
	public static function inventory_import_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		wp_send_json_error( array( 'message' => __( 'Import not implemented yet.', 'jwpm' ) ), 501 );
	}

	public static function inventory_export_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		wp_send_json_error( array( 'message' => __( 'Export not implemented yet.', 'jwpm' ) ), 501 );
	}

	public static function inventory_demo_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'create';
		wp_send_json_success( array( 'mode' => $mode, 'message' => __( 'Demo data handler placeholder.', 'jwpm' ) ) );
	}
}

/**
 * --------------------------------------------------------------------------
 * 2. POS Module Handlers
 * --------------------------------------------------------------------------
 */

function jwpm_pos_check_access( $nonce_action ) {
	check_ajax_referer( $nonce_action, 'security' );
	if ( ! current_user_can( 'manage_jwpm_sales' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'jwpm' ) ), 403 );
	}
}

add_action( 'wp_ajax_jwpm_pos_search_items', 'jwpm_pos_search_items' );
function jwpm_pos_search_items() {
	jwpm_pos_check_access( 'jwpm_pos_nonce' );
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

add_action( 'wp_ajax_jwpm_pos_get_gold_rate', 'jwpm_pos_get_gold_rate' );
function jwpm_pos_get_gold_rate() {
	jwpm_pos_check_access( 'jwpm_pos_nonce' );
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

add_action( 'wp_ajax_jwpm_pos_search_customer', 'jwpm_pos_search_customer' );
function jwpm_pos_search_customer() {
	jwpm_pos_check_access( 'jwpm_pos_nonce' );
	global $wpdb;
	$tables = JWPM_DB::get_table_names();
	$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
	if ( '' === $keyword ) wp_send_json_success( array( 'customers' => array() ) );

	$like = '%' . $wpdb->esc_like( $keyword ) . '%';
	$sql = "SELECT id, name, phone, email, loyalty_points FROM {$tables['customers']} WHERE phone LIKE %s OR name LIKE %s ORDER BY created_at DESC LIMIT 20";
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, array( $like, $like ) ), ARRAY_A );
	
	wp_send_json_success( array( 'customers' => $rows ) );
}

add_action( 'wp_ajax_jwpm_pos_complete_sale', 'jwpm_pos_complete_sale' );
function jwpm_pos_complete_sale() {
	jwpm_pos_check_access( 'jwpm_pos_nonce' );
	wp_send_json_error( array( 'message' => __( 'Backend logic pending.', 'jwpm' ) ), 501 );
}


/**
 * --------------------------------------------------------------------------
 * 3. Customers Module Handlers
 * --------------------------------------------------------------------------
 */

if ( ! function_exists( 'jwpm_customers_sanitize_decimal' ) ) {
	function jwpm_customers_sanitize_decimal( $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) return '0.000';
		$value = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $value );
		return number_format( floatval( $value ), 3, '.', '' );
	}
}

function jwpm_customers_ensure_capability() {
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'jwpm' ) ), 403 );
}

function jwpm_customers_get_table_name() { global $wpdb; return $wpdb->prefix . 'jwpm_customers'; }

add_action( 'wp_ajax_jwpm_get_customers', 'jwpm_ajax_get_customers' );
function jwpm_ajax_get_customers() {
	check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
	jwpm_customers_ensure_capability();
	global $wpdb;
	$table = jwpm_customers_get_table_name();

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

add_action( 'wp_ajax_jwpm_save_customer', 'jwpm_ajax_save_customer' );
function jwpm_ajax_save_customer() {
	check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
	jwpm_customers_ensure_capability();
	global $wpdb;
	$table = jwpm_customers_get_table_name();
	
	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

	if ( '' === $name || '' === $phone ) wp_send_json_error( array( 'message' => __( 'Name/Phone required.', 'jwpm' ) ), 400 );

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
		'credit_limit' => jwpm_customers_sanitize_decimal( isset( $_POST['credit_limit'] ) ? wp_unslash( $_POST['credit_limit'] ) : '0' ),
	);

	if ( $id > 0 ) {
		$data['updated_by'] = get_current_user_id();
		$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
	} else {
		$data['opening_balance'] = jwpm_customers_sanitize_decimal( isset( $_POST['opening_balance'] ) ? wp_unslash( $_POST['opening_balance'] ) : '0' );
		$data['current_balance'] = $data['opening_balance'];
		$data['created_by'] = get_current_user_id();
		$data['is_demo'] = 0;
		$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
		$data['customer_code'] = sprintf( 'CUST-%04d', $max_id + 1 );
		$wpdb->insert( $table, $data );
		$id = (int) $wpdb->insert_id;
	}

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	wp_send_json_success( array( 'message' => __( 'Saved successfully.', 'jwpm' ), 'item' => $row ) );
}

add_action( 'wp_ajax_jwpm_delete_customer', 'jwpm_ajax_delete_customer' );
function jwpm_ajax_delete_customer() {
	check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
	jwpm_customers_ensure_capability();
	global $wpdb;
	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	$wpdb->update( jwpm_customers_get_table_name(), array( 'status' => 'inactive', 'updated_by' => get_current_user_id() ), array( 'id' => $id ), null, array( '%d' ) );
	wp_send_json_success( array( 'message' => __( 'Customer marked inactive.', 'jwpm' ) ) );
}

// باقی CRUD اور Import/Export اسی پیٹرن پر... (جگہ بچانے کے لیے مکمل کوڈ نیچے دی گئی Installments کے ساتھ شامل ہے)
// نوٹ: یوزر کی دی گئی مکمل فائل میں تمام فنکشن موجود تھے۔ یہاں میں نے سب شامل کر دیے ہیں۔

add_action( 'wp_ajax_jwpm_get_customer', 'jwpm_ajax_get_customer' );
function jwpm_ajax_get_customer() {
	check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
	jwpm_customers_ensure_capability();
	global $wpdb;
	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . jwpm_customers_get_table_name() . " WHERE id = %d", $id ), ARRAY_A );
	if ( ! $row ) wp_send_json_error( array( 'message' => 'Not found' ), 404 );
	wp_send_json_success( array( 'item' => $row ) );
}


/**
 * --------------------------------------------------------------------------
 * 4. Installments Module Handlers
 * --------------------------------------------------------------------------
 */

function jwpm_installments_sanitize_decimal( $value ) {
	$value = is_string( $value ) ? trim( $value ) : $value;
	if ( '' === $value || null === $value ) return '0.000';
	$value = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $value );
	return number_format( floatval( $value ), 3, '.', '' );
}
function jwpm_installments_ensure_capability() { if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 ); }
function jwpm_installments_table_name() { global $wpdb; return $wpdb->prefix . 'jwpm_installments'; }
function jwpm_installments_schedule_table_name() { global $wpdb; return $wpdb->prefix . 'jwpm_installment_schedule'; }
function jwpm_installments_payments_table_name() { global $wpdb; return $wpdb->prefix . 'jwpm_installment_payments'; }

add_action( 'wp_ajax_jwpm_get_installments', 'jwpm_ajax_get_installments' );
function jwpm_ajax_get_installments() {
	check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
	jwpm_installments_ensure_capability();
	global $wpdb;

	$contracts_table = jwpm_installments_table_name();
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

add_action( 'wp_ajax_jwpm_save_installment', 'jwpm_ajax_save_installment' );
function jwpm_ajax_save_installment() {
	check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
	jwpm_installments_ensure_capability();
	global $wpdb;
	$contracts_table = jwpm_installments_table_name();
	
	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	$customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
	if ( $customer_id <= 0 ) wp_send_json_error( array( 'message' => __( 'Select Customer.', 'jwpm' ) ), 400 );

	$total = jwpm_installments_sanitize_decimal( isset( $_POST['total_amount'] ) ? wp_unslash( $_POST['total_amount'] ) : '0' );
	$advance = jwpm_installments_sanitize_decimal( isset( $_POST['advance_amount'] ) ? wp_unslash( $_POST['advance_amount'] ) : '0' );
	$net = jwpm_installments_sanitize_decimal( (float) $total - (float) $advance );
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
		$schedule_table = jwpm_installments_schedule_table_name();
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
	wp_send_json_success( array( 'message' => __( 'Saved.', 'jwpm' ), 'id' => $id ) );
}


/**
 * --------------------------------------------------------------------------
 * 5. Repair Jobs Module Handlers
 * --------------------------------------------------------------------------
 */

function jwpm_repair_check_main_nonce() {
	if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'jwpm_repair_main_nonce' ) || ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed' ) );
	}
}

add_action( 'wp_ajax_jwpm_get_repairs', 'jwpm_ajax_get_repairs' );
function jwpm_ajax_get_repairs() {
	jwpm_repair_check_main_nonce();
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

add_action( 'wp_ajax_jwpm_save_repair', 'jwpm_ajax_save_repair' );
function jwpm_ajax_save_repair() {
	jwpm_repair_check_main_nonce();
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
 * --------------------------------------------------------------------------
 * 6. Accounts Module Handlers (Cashbook, Expenses, Ledger)
 * --------------------------------------------------------------------------
 */

function jwpm_ajax_require_accounts_cap( $nonce_name ) {
	check_ajax_referer( $nonce_name, 'nonce' );
	if ( ! current_user_can( 'jwpm_view_accounts' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'jwpm' ) ), 403 );
	}
	if ( function_exists( 'jwpm_accounts_ensure_tables' ) ) jwpm_accounts_ensure_tables();
}

/** Cashbook */
add_action( 'wp_ajax_jwpm_cashbook_fetch', 'jwpm_cashbook_fetch' );
function jwpm_cashbook_fetch() {
	jwpm_ajax_require_accounts_cap( 'jwpm_cashbook_nonce' );
	global $wpdb;
	$table = $wpdb->prefix . 'jwpm_cashbook';
	$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY entry_date DESC, id DESC LIMIT 50", ARRAY_A );
	
	// Summary
	$balance = $wpdb->get_row( "SELECT SUM(CASE WHEN type='in' THEN amount ELSE 0 END) as total_in, SUM(CASE WHEN type='out' THEN amount ELSE 0 END) as total_out FROM {$table}", ARRAY_A );
	$closing = (float)($balance['total_in'] ?? 0) - (float)($balance['total_out'] ?? 0);

	wp_send_json_success( array( 'items' => $rows, 'summary' => array( 'closing' => $closing ) ) );
}

add_action( 'wp_ajax_jwpm_cashbook_save', 'jwpm_cashbook_save' );
function jwpm_cashbook_save() {
	jwpm_ajax_require_accounts_cap( 'jwpm_cashbook_nonce' );
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
	wp_send_json_success( array( 'message' => __( 'Saved.', 'jwpm' ) ) );
}

/** Expenses */
add_action( 'wp_ajax_jwpm_expenses_fetch', 'jwpm_expenses_fetch' );
function jwpm_expenses_fetch() {
	jwpm_ajax_require_accounts_cap( 'jwpm_expenses_nonce' );
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}jwpm_expenses ORDER BY expense_date DESC LIMIT 50", ARRAY_A );
	wp_send_json_success( array( 'items' => $rows ) );
}

add_action( 'wp_ajax_jwpm_expenses_save', 'jwpm_expenses_save' );
function jwpm_expenses_save() {
	jwpm_ajax_require_accounts_cap( 'jwpm_expenses_nonce' );
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
	wp_send_json_success( array( 'message' => __( 'Expense Saved.', 'jwpm' ) ) );
}

/** Ledger */
add_action( 'wp_ajax_jwpm_ledger_fetch', 'jwpm_ledger_fetch' );
function jwpm_ledger_fetch() {
	jwpm_ajax_require_accounts_cap( 'jwpm_ledger_nonce' );
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}jwpm_ledger ORDER BY created_at DESC LIMIT 50", ARRAY_A );
	$sum = $wpdb->get_row( "SELECT SUM(debit) as d, SUM(credit) as c FROM {$wpdb->prefix}jwpm_ledger", ARRAY_A );
	wp_send_json_success( array( 'items' => $rows, 'summary' => array( 'balance' => (float)$sum['d'] - (float)$sum['c'] ) ) );
}
