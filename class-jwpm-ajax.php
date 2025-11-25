<?php
/**
 * JWPM_Ajax
 *
 * یہ کلاس تمام (JWPM) AJAX کالز کو رجسٹر اور ہینڈل کرتی ہے۔
 * ہر اینڈ پوائنٹ میں (nonce)، (capability) اور (sanitize) لازمی چیک ہوں گے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Ajax {

	/**
	 * تمام (wp_ajax_*) ہُکس رجسٹر کریں
	 */
	public static function register_ajax_hooks() {

		// انوینٹری لسٹ / سیو / ڈیلیٹ / امپورٹ / ایکسپورٹ / ڈیمو
		add_action( 'wp_ajax_jwpm_inventory_list_items', array( __CLASS__, 'inventory_list_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_save_item', array( __CLASS__, 'inventory_save_item' ) );
		add_action( 'wp_ajax_jwpm_inventory_delete_item', array( __CLASS__, 'inventory_delete_item' ) );
		add_action( 'wp_ajax_jwpm_inventory_import_items', array( __CLASS__, 'inventory_import_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_export_items', array( __CLASS__, 'inventory_export_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_demo_items', array( __CLASS__, 'inventory_demo_items' ) );
	}

	/**
	 * مشترکہ ہیلپر: (nonce) اور (capability) چیک
	 *
	 * @param string $nonce_action
	 * @param string $capability
	 */
	protected static function check_access( $nonce_action, $capability ) {

		check_ajax_referer( $nonce_action, 'security' );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'jwpm-jewelry-pos-manager' ),
				),
				403
			);
		}
	}

	/**
	 * انوینٹری لسٹ – فلٹرز کے ساتھ آئٹم ریکارڈز واپس کرے گی
	 *
	 * Expected POST:
	 * - security (nonce)
	 * - page, per_page
	 * - search, category, metal, karat, status, weight_min, weight_max, branch_id
	 */
	public static function inventory_list_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );

		global $wpdb;

		$tables = JWPM_DB::get_table_names();

		$page     = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? max( 1, (int) $_POST['per_page'] ) : 50;

		$offset = ( $page - 1 ) * $per_page;

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
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
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

		$sql_base = "FROM {$tables['items']} {$where}";

		// ٹوٹل کاؤنٹ
		$count_sql = "SELECT COUNT(*) {$sql_base}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// مین کوئری
		$list_sql  = "SELECT * {$sql_base} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params_l  = array_merge( $params, array( $per_page, $offset ) );
		$items_raw = $wpdb->get_results( $wpdb->prepare( $list_sql, $params_l ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$items = array();

		if ( ! empty( $items_raw ) ) {
			foreach ( $items_raw as $row ) {
				$items[] = array(
					'id'           => (int) $row['id'],
					'branch_id'    => (int) $row['branch_id'],
					'sku'          => $row['sku'],
					'tag_serial'   => $row['tag_serial'],
					'category'     => $row['category'],
					'metal_type'   => $row['metal_type'],
					'karat'        => $row['karat'],
					'gross_weight' => (float) $row['gross_weight'],
					'net_weight'   => (float) $row['net_weight'],
					'stone_type'   => $row['stone_type'],
					'stone_carat'  => isset( $row['stone_carat'] ) ? (float) $row['stone_carat'] : 0,
					'stone_qty'    => isset( $row['stone_qty'] ) ? (int) $row['stone_qty'] : 0,
					'labour_amount'=> (float) $row['labour_amount'],
					'design_no'    => $row['design_no'],
					'image_id'     => isset( $row['image_id'] ) ? (int) $row['image_id'] : 0,
					'status'       => $row['status'],
					'is_demo'      => (int) $row['is_demo'],
					'created_at'   => $row['created_at'],
				);
			}
		}

		wp_send_json_success(
			array(
				'items'     => $items,
				'total'     => $total,
				'page'      => $page,
				'per_page'  => $per_page,
			)
		);
	}

	/**
	 * انوینٹری آئٹم سیو / اپڈیٹ
	 *
	 * Expected POST:
	 * - security, id (optional), branch_id, sku, tag_serial, category, metal_type, karat,
	 *   gross_weight, net_weight, stone_type, stone_carat, stone_qty, labour_amount, design_no, status, is_demo
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

		$format = array(
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%f',
			'%f',
			'%s',
			'%f',
			'%d',
			'%f',
			'%s',
			'%s',
			'%d',
		);

		if ( $id > 0 ) {
			$data['updated_at'] = current_time( 'mysql' );
			$format[]           = '%s';

			$updated = $wpdb->update(
				$tables['items'],
				$data,
				array( 'id' => $id ),
				$format,
				array( '%d' )
			);

			if ( false === $updated ) {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to update item.', 'jwpm-jewelry-pos-manager' ),
					),
					500
				);
			}

			JWPM_DB::log_activity( get_current_user_id(), 'inventory_update', 'item', $id, $data );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$format[]           = '%s';

			$inserted = $wpdb->insert(
				$tables['items'],
				$data,
				$format
			);

			if ( ! $inserted ) {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to create item.', 'jwpm-jewelry-pos-manager' ),
					),
					500
				);
			}

			$id = (int) $wpdb->insert_id;

			JWPM_DB::log_activity( get_current_user_id(), 'inventory_create', 'item', $id, $data );
		}

		wp_send_json_success(
			array(
				'id'      => $id,
				'message' => __( 'Item saved successfully.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	/**
	 * انوینٹری آئٹم ڈیلیٹ (سافٹ ڈیلیٹ کی جگہ اسٹیٹس بھی ہو سکتا ہے، فی الحال ہارڈ ڈیلیٹ)
	 *
	 * Expected POST:
	 * - security, id
	 */
	public static function inventory_delete_item() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );

		global $wpdb;

		$tables = JWPM_DB::get_table_names();

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid item ID.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$deleted = $wpdb->delete(
			$tables['items'],
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to delete item.', 'jwpm-jewelry-pos-manager' ),
				),
				500
			);
		}

		JWPM_DB::log_activity( get_current_user_id(), 'inventory_delete', 'item', $id );

		wp_send_json_success(
			array(
				'message' => __( 'Item deleted successfully.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	/**
	 * انوینٹری امپورٹ – فی الحال پلیس ہولڈر، بعد میں (CSV/Excel) پارسنگ شامل کریں گے
	 */
	public static function inventory_import_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );

		// Developer hint: یہاں بعد میں فائل اپ لوڈ، (CSV) ریڈ، پارسنگ، (DB) میں انسیرٹ لاجک آئے گی۔
		wp_send_json_error(
			array(
				'message' => __( 'Import not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			),
			501
		);
	}

	/**
	 * انوینٹری ایکسپورٹ – فی الحال سادہ پلیس ہولڈر
	 */
	public static function inventory_export_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );

		// یہاں بعد میں (CSV/Excel) آؤٹ پٹ جنریشن ہو گی، فی الحال جواب:
		wp_send_json_error(
			array(
				'message' => __( 'Export not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			),
			501
		);
	}

	/**
	 * ڈیمو ڈیٹا جنریٹ / ڈیلیٹ – فی الحال پلیس ہولڈر
	 */
	public static function inventory_demo_items() {
		self::check_access( 'jwpm_inventory_nonce', 'manage_jwpm_inventory' );

		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'create';

		// Developer hint: یہاں ہم بعد میں Demo items create/delete کرنے کی لاجک لکھیں گے۔
		wp_send_json_success(
			array(
				'mode'    => $mode,
				'message' => __( 'Demo data handler is not fully implemented yet, placeholder only.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}
}

// ✅ Syntax verified block end
<?php
/** Part 2 — POS AJAX handlers (search items, gold rate, customer search, complete sale placeholder)
 *
 * یہ بلاک (POS / Sales) کے لیے الگ AJAX اینڈ پوائنٹس فراہم کرتا ہے:
 * - jwpm_pos_search_items
 * - jwpm_pos_get_gold_rate
 * - jwpm_pos_search_customer
 * - jwpm_pos_complete_sale (فی الحال placeholder)
 *
 * ہر اینڈ پوائنٹ میں (nonce) اور (capability) چیک لازمی ہے۔
 */

/**
 * مشترکہ Access چیک برائے POS
 *
 * @param string $nonce_action
 */
function jwpm_pos_check_access( $nonce_action ) {

	check_ajax_referer( $nonce_action, 'security' );

	if ( ! current_user_can( 'manage_jwpm_sales' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to perform this POS action.', 'jwpm-jewelry-pos-manager' ),
			),
			403
		);
	}
}

/**
 * POS: انوینٹری آئٹمز سرچ (Left Pane Product Search)
 *
 * Expected POST:
 * - security (nonce)
 * - keyword (name/sku/tag)
 * - category
 * - karat
 * - branch_id
 */
function jwpm_pos_search_items() {
	jwpm_pos_check_access( 'jwpm_pos_nonce' );

	global $wpdb;

	if ( ! class_exists( 'JWPM_DB' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Database helper not available.', 'jwpm-jewelry-pos-manager' ),
			),
			500
		);
	}

	$tables = JWPM_DB::get_table_names();

	$keyword   = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
	$category  = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
	$karat     = isset( $_POST['karat'] ) ? sanitize_text_field( wp_unslash( $_POST['karat'] ) ) : '';
	$branch_id = isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0;

	$where  = 'WHERE status != %s';
	$params = array( 'scrap' );

	if ( $branch_id > 0 ) {
		$where   .= ' AND branch_id = %d';
		$params[] = $branch_id;
	}

	if ( $keyword !== '' ) {
		$like     = '%' . $wpdb->esc_like( $keyword ) . '%';
		$where   .= ' AND (sku LIKE %s OR tag_serial LIKE %s OR category LIKE %s OR design_no LIKE %s)';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	if ( $category !== '' ) {
		$where   .= ' AND category = %s';
		$params[] = $category;
	}

	if ( $karat !== '' ) {
		$where   .= ' AND karat = %s';
		$params[] = $karat;
	}

	$sql = "SELECT id, branch_id, sku, tag_serial, category, metal_type, karat, gross_weight, net_weight, stone_type, status
	        FROM {$tables['items']}
			{$where}
			ORDER BY created_at DESC
			LIMIT 30";

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$items = array();

	if ( ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			$items[] = array(
				'id'           => (int) $row['id'],
				'branch_id'    => (int) $row['branch_id'],
				'sku'          => $row['sku'],
				'tag_serial'   => $row['tag_serial'],
				'category'     => $row['category'],
				'metal_type'   => $row['metal_type'],
				'karat'        => $row['karat'],
				'gross_weight' => (float) $row['gross_weight'],
				'net_weight'   => (float) $row['net_weight'],
				'stone_type'   => $row['stone_type'],
				'status'       => $row['status'],
			);
		}
	}

	wp_send_json_success(
		array(
			'items' => $items,
		)
	);
}
add_action( 'wp_ajax_jwpm_pos_search_items', 'jwpm_pos_search_items' );

/**
 * POS: گولڈ ریٹ لوڈ کرنا
 *
 * یہ فی الحال سادہ ورژن ہے:
 * - پہلے (jwpm_settings) ٹیبل میں option_name = 'gold_rate_24k' تلاش کرے گا
 * - اگر نہ ملے تو 0 واپس کرے گا
 */
function jwpm_pos_get_gold_rate() {
	jwpm_pos_check_access( 'jwpm_pos_nonce' );

	global $wpdb;

	if ( ! class_exists( 'JWPM_DB' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Database helper not available.', 'jwpm-jewelry-pos-manager' ),
			),
			500
		);
	}

	$tables = JWPM_DB::get_table_names();

	$sql = "SELECT option_value FROM {$tables['settings']} WHERE option_name = %s LIMIT 1";
	$val = $wpdb->get_var( $wpdb->prepare( $sql, array( 'gold_rate_24k' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$rate = 0;
	if ( ! empty( $val ) ) {
		$decoded = maybe_unserialize( $val );
		if ( is_numeric( $decoded ) ) {
			$rate = (float) $decoded;
		} elseif ( is_array( $decoded ) && isset( $decoded['value'] ) ) {
			$rate = (float) $decoded['value'];
		}
	}

	wp_send_json_success(
		array(
			'rate' => $rate,
		)
	);
}
add_action( 'wp_ajax_jwpm_pos_get_gold_rate', 'jwpm_pos_get_gold_rate' );

/**
 * POS: کسٹمر سرچ (فون / نام کے ذریعے)
 *
 * Expected POST:
 * - security
 * - keyword
 */
function jwpm_pos_search_customer() {
	jwpm_pos_check_access( 'jwpm_pos_nonce' );

	global $wpdb;

	if ( ! class_exists( 'JWPM_DB' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Database helper not available.', 'jwpm-jewelry-pos-manager' ),
			),
			500
		);
	}

	$tables  = JWPM_DB::get_table_names();
	$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

	if ( '' === $keyword ) {
		wp_send_json_success(
			array(
				'customers' => array(),
			)
		);
	}

	$like = '%' . $wpdb->esc_like( $keyword ) . '%';

	$sql = "SELECT id, name, phone, email, loyalty_points
	        FROM {$tables['customers']}
			WHERE phone LIKE %s OR name LIKE %s
			ORDER BY created_at DESC
			LIMIT 20";

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, array( $like, $like ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	$customers = array();

	if ( ! empty( $rows ) ) {
		foreach ( $rows as $row ) {
			$customers[] = array(
				'id'             => (int) $row['id'],
				'name'           => $row['name'],
				'phone'          => $row['phone'],
				'email'          => $row['email'],
				'loyalty_points' => (int) $row['loyalty_points'],
			);
		}
	}

	wp_send_json_success(
		array(
			'customers' => $customers,
		)
	);
}
add_action( 'wp_ajax_jwpm_pos_search_customer', 'jwpm_pos_search_customer' );

/**
 * POS: سیل مکمل کرنا — فی الحال placeholder
 *
 * Expected POST (Future design):
 * - security
 * - cart_items (JSON)
 * - customer_id / guest info
 * - payment details
 * - installment meta (optional)
 *
 * ابھی کے لیے صرف Not Implemented واپس کرے گا تاکہ JS کو صحیح Response Structure ملے۔
 */
function jwpm_pos_complete_sale() {
	jwpm_pos_check_access( 'jwpm_pos_nonce' );

	wp_send_json_error(
		array(
			'message' => __( 'Complete sale is not implemented yet. Backend logic pending.', 'jwpm-jewelry-pos-manager' ),
		),
		501
	);
}
add_action( 'wp_ajax_jwpm_pos_complete_sale', 'jwpm_pos_complete_sale' );

// ✅ Syntax verified block end
/** Part 33 — Customers AJAX Handlers */
// 🟢 یہاں سے [Customers AJAX Handlers] شروع ہو رہا ہے

if ( ! function_exists( 'jwpm_customers_sanitize_decimal' ) ) {
	/**
	 * decimal ویلیو کو safely sanitize کرے (comma → dot وغیرہ)
	 */
	function jwpm_customers_sanitize_decimal( $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) {
			return '0.000';
		}
		$value = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $value );
		$float = floatval( $value );

		return number_format( $float, 3, '.', '' );
	}
}

/**
 * Capability چیک helper
 */
if ( ! function_exists( 'jwpm_customers_ensure_capability' ) ) {
	function jwpm_customers_ensure_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'آپ کو اس عمل کی اجازت نہیں۔', 'jwpm' ),
				),
				403
			);
		}
	}
}

/**
 * Common: table name helper
 */
if ( ! function_exists( 'jwpm_customers_get_table_name' ) ) {
	function jwpm_customers_get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'jwpm_customers';
	}
}

/**
 * 1) Get Customers List (with filters + pagination)
 */
add_action( 'wp_ajax_jwpm_get_customers', 'jwpm_ajax_get_customers' );
function jwpm_ajax_get_customers() {
	check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
	jwpm_customers_ensure_capability();

	global $wpdb;

	$table = jwpm_customers_get_table_name();

	$search  = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	$city    = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
	$type    = isset( $_POST['customer_type'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_type'] ) ) : '';
	$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
	$page    = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
	$perpage = isset( $_POST['per_page'] ) ? max( 1, intval( $_POST['per_page'] ) ) : 20;

	$where  = 'WHERE 1=1';
	$params = array();

	if ( $search ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= ' AND (name LIKE %s OR phone LIKE %s)';
		$params[] = $like;
		$params[] = $like;
	}

	if ( $city ) {
		$where    .= ' AND city = %s';
		$params[] = $city;
	}

	if ( $type ) {
		$where    .= ' AND customer_type = %s';
		$params[] = $type;
	}

	if ( $status ) {
		$where    .= ' AND status = %s';
		$params[] = $status;
	}

	// شمار
	$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
	$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

	$offset = ( $page - 1 ) * $perpage;

	$items_sql = "SELECT *
		FROM {$table}
		{$where}
		ORDER BY created_at DESC
		LIMIT %d OFFSET %d";

	$params_items   = $params;
	$params_items[] = $perpage;
	$params_items[] = $offset;

	$rows = $wpdb->get_results( $wpdb->prepare( $items_sql, $params_items ), ARRAY_A );

	wp_send_json_success(
		array(
			'items'      => $rows,
			'pagination' => array(
				'total'      => $total,
				'page'       => $page,
				'per_page'   => $perpage,
				'total_page' => $perpage > 0 ? (int) ceil( $total / $perpage ) : 1,
			),
		)
	);
}

/**
 * 2) Get single customer
 */
add_action( 'wp_ajax_jwpm_get_customer', 'jwpm_ajax_get_customer' );
function jwpm_ajax_get_customer() {
	check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
	jwpm_customers_ensure_capability();

	global $wpdb;
	$table = jwpm_customers_get_table_name();

	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	if ( $id <= 0 ) {
		wp_send_json_error(
			array(
				'message' => __( 'غلط کسٹمر آئی ڈی۔', 'jwpm' ),
			),
			400
		);
	}

	$sql  = "SELECT * FROM {$table} WHERE id = %d";
	$row  = $wpdb->get_row( $wpdb->prepare( $sql, $id ), ARRAY_A );

	if ( ! $row ) {
		wp_send_json_error(
			array(
				'message' => __( 'کسٹمر نہیں ملا۔', 'jwpm' ),
			),
			404
		);
	}

	wp_send_json_success(
		array(
			'item' => $row,
		)
	);
}

/**
 * 3) Save customer (insert / update)
 */
add_action( 'wp_ajax_jwpm_save_customer', 'jwpm_ajax_save_customer' );
function jwpm_ajax_save_customer() {
	check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
	jwpm_customers_ensure_capability();

	global $wpdb;
	$table = jwpm_customers_get_table_name();

	$data = array();

	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

	if ( '' === $name || '' === $phone ) {
		wp_send_json_error(
			array(
				'message' => __( 'Name اور Phone لازمی فیلڈز ہیں۔', 'jwpm' ),
			),
			400
		);
	}

	$data['name']          = $name;
	$data['phone']         = $phone;
	$data['whatsapp']      = isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '';
	$data['email']         = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$data['city']          = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
	$data['area']          = isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '';
	$data['address']       = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
	$data['cnic']          = isset( $_POST['cnic'] ) ? sanitize_text_field( wp_unslash( $_POST['cnic'] ) ) : '';
	$data['dob']           = isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '';
	$data['gender']        = isset( $_POST['gender'] ) ? sanitize_text_field( wp_unslash( $_POST['gender'] ) ) : '';
	$data['customer_type'] = isset( $_POST['customer_type'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_type'] ) ) : 'walkin';
	$data['status']        = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';
	$data['price_group']   = isset( $_POST['price_group'] ) ? sanitize_text_field( wp_unslash( $_POST['price_group'] ) ) : '';
	$data['tags']          = isset( $_POST['tags'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tags'] ) ) : '';
	$data['notes']         = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

	$data['credit_limit'] = jwpm_customers_sanitize_decimal( isset( $_POST['credit_limit'] ) ? wp_unslash( $_POST['credit_limit'] ) : '0' );

	$current_user = get_current_user_id();

	if ( $id > 0 ) {
		// Update
		$data['updated_by'] = $current_user;

		$updated = $wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error(
				array(
					'message' => __( 'کسٹمر اپڈیٹ نہیں ہو سکا۔', 'jwpm' ),
				),
			 500
			);
		}

	} else {
		// Insert
		$data['opening_balance'] = jwpm_customers_sanitize_decimal( isset( $_POST['opening_balance'] ) ? wp_unslash( $_POST['opening_balance'] ) : '0' );
		$data['current_balance'] = $data['opening_balance'];
		$data['created_by']      = $current_user;
		$data['is_demo']         = 0;

		// customer_code generate
		$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
		$next   = $max_id + 1;
		$data['customer_code'] = sprintf( 'CUST-%04d', $next );

		$inserted = $wpdb->insert( $table, $data );

		if ( ! $inserted ) {
			wp_send_json_error(
				array(
					'message' => __( 'کسٹمر محفوظ نہیں ہو سکا۔', 'jwpm' ),
				),
				500
			);
		}

		$id = (int) $wpdb->insert_id;
	}

	$sql = "SELECT * FROM {$table} WHERE id = %d";
	$row = $wpdb->get_row( $wpdb->prepare( $sql, $id ), ARRAY_A );

	wp_send_json_success(
		array(
			'message' => __( 'کسٹمر کامیابی سے محفوظ ہو گیا۔', 'jwpm' ),
			'item'    => $row,
		)
	);
}

/**
 * 4) Delete (Soft → status = inactive)
 */
add_action( 'wp_ajax_jwpm_delete_customer', 'jwpm_ajax_delete_customer' );
function jwpm_ajax_delete_customer() {
	check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
	jwpm_customers_ensure_capability();

	global $wpdb;
	$table = jwpm_customers_get_table_name();

	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	if ( $id <= 0 ) {
		wp_send_json_error(
			array(
				'message' => __( 'غلط کسٹمر آئی ڈی۔', 'jwpm' ),
			),
			400
		);
	}

	$updated = $wpdb->update(
		$table,
		array(
			'status'     => 'inactive',
			'updated_by' => get_current_user_id(),
		),
		array( 'id' => $id ),
		null,
		array( '%d' )
	);

	if ( false === $updated ) {
		wp_send_json_error(
			array(
				'message' => __( 'کسٹمر کو Inactive نہیں کیا جا سکا۔', 'jwpm' ),
			),
			500
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'کسٹمر کو Inactive کر دیا گیا۔', 'jwpm' ),
		)
	);
}

/**
 * 5) Toggle Status (Active ↔ Inactive)
 */
add_action( 'wp_ajax_jwpm_toggle_customer_status', 'jwpm_ajax_toggle_customer_status' );
function jwpm_ajax_toggle_customer_status() {
	check_ajax_referer( 'jwpm_customers_main_nonce', 'nonce' );
	jwpm_customers_ensure_capability();

	global $wpdb;
	$table = jwpm_customers_get_table_name();

	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	if ( $id <= 0 ) {
		wp_send_json_error(
			array(
				'message' => __( 'غلط کسٹمر آئی ڈی۔', 'jwpm' ),
			),
			400
		);
	}

	$sql   = "SELECT status FROM {$table} WHERE id = %d";
	$cur   = $wpdb->get_var( $wpdb->prepare( $sql, $id ) );
	if ( null === $cur ) {
		wp_send_json_error(
			array(
				'message' => __( 'کسٹمر نہیں ملا۔', 'jwpm' ),
			),
			404
		);
	}

	$new_status = ( 'active' === $cur ) ? 'inactive' : 'active';

	$updated = $wpdb->update(
		$table,
		array(
			'status'     => $new_status,
			'updated_by' => get_current_user_id(),
		),
		array( 'id' => $id ),
		null,
		array( '%d' )
	);

	if ( false === $updated ) {
		wp_send_json_error(
			array(
				'message' => __( 'Status تبدیل نہیں ہو سکا۔', 'jwpm' ),
			),
			500
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Status تبدیل ہو گیا۔', 'jwpm' ),
			'status'  => $new_status,
		)
	);
}

/**
 * 6) Import Customers (CSV)
 */
add_action( 'wp_ajax_jwpm_import_customers', 'jwpm_ajax_import_customers' );
function jwpm_ajax_import_customers() {
	check_ajax_referer( 'jwpm_customers_import_nonce', 'nonce' );
	jwpm_customers_ensure_capability();

	if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'فائل موصول نہیں ہوئی۔', 'jwpm' ),
			),
			400
		);
	}

	$skip_duplicates = ! empty( $_POST['skip_duplicates'] );

	$file = $_FILES['file']['tmp_name'];

	if ( ! file_exists( $file ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'فائل دستیاب نہیں۔', 'jwpm' ),
			),
			400
		);
	}

	global $wpdb;
	$table = jwpm_customers_get_table_name();

	$handle = fopen( $file, 'r' );
	if ( ! $handle ) {
		wp_send_json_error(
			array(
				'message' => __( 'فائل نہیں کھولی جا سکی۔', 'jwpm' ),
			),
			400
		);
	}

	$total    = 0;
	$inserted = 0;
	$skipped  = 0;

	$header = fgetcsv( $handle );
	if ( ! $header ) {
		fclose( $handle );
		wp_send_json_error(
			array(
				'message' => __( 'فائل میں header row نہیں ملا۔', 'jwpm' ),
			),
			400
		);
	}

	$header_map = array();
	foreach ( $header as $index => $col ) {
		$key                    = strtolower( trim( $col ) );
		$header_map[ $key ] = $index;
	}

	if ( ! isset( $header_map['name'], $header_map['phone'] ) ) {
		fclose( $handle );
		wp_send_json_error(
			array(
				'message' => __( 'کم از کم Name اور Phone کالم ضروری ہیں۔', 'jwpm' ),
			),
			400
		);
	}

	$current_user = get_current_user_id();

	while ( ( $row = fgetcsv( $handle ) ) !== false ) {
		$total++;

		$name  = isset( $row[ $header_map['name'] ] ) ? sanitize_text_field( $row[ $header_map['name'] ] ) : '';
		$phone = isset( $row[ $header_map['phone'] ] ) ? sanitize_text_field( $row[ $header_map['phone'] ] ) : '';

		if ( '' === $name || '' === $phone ) {
			$skipped++;
			continue;
		}

		if ( $skip_duplicates ) {
			$exists_sql = "SELECT id FROM {$table} WHERE phone = %s";
			$exists_id  = $wpdb->get_var( $wpdb->prepare( $exists_sql, $phone ) );
			if ( $exists_id ) {
				$skipped++;
				continue;
			}
		}

		$data = array(
			'name'           => $name,
			'phone'          => $phone,
			'whatsapp'       => isset( $header_map['whatsapp'] ) ? sanitize_text_field( $row[ $header_map['whatsapp'] ] ) : '',
			'email'          => isset( $header_map['email'] ) ? sanitize_email( $row[ $header_map['email'] ] ) : '',
			'city'           => isset( $header_map['city'] ) ? sanitize_text_field( $row[ $header_map['city'] ] ) : '',
			'area'           => isset( $header_map['area'] ) ? sanitize_text_field( $row[ $header_map['area'] ] ) : '',
			'customer_type'  => isset( $header_map['customer_type'] ) ? sanitize_text_field( $row[ $header_map['customer_type'] ] ) : 'walkin',
			'status'         => 'active',
			'credit_limit'   => '0.000',
			'opening_balance'=> '0.000',
			'current_balance'=> '0.000',
			'is_demo'        => 0,
			'created_by'     => $current_user,
		);

		// generate customer_code
		$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
		$next   = $max_id + 1;
		$data['customer_code'] = sprintf( 'CUST-%04d', $next );

		$ok = $wpdb->insert( $table, $data );

		if ( $ok ) {
			$inserted++;
		} else {
			$skipped++;
		}
	}

	fclose( $handle );

	wp_send_json_success(
		array(
			'message'  => __( 'Import مکمل ہو گیا۔', 'jwpm' ),
			'total'    => $total,
			'inserted' => $inserted,
			'skipped'  => $skipped,
		)
	);
}

/**
 * 7) Export Customers (CSV for Excel)
 */
add_action( 'wp_ajax_jwpm_export_customers', 'jwpm_ajax_export_customers' );
function jwpm_ajax_export_customers() {
	check_ajax_referer( 'jwpm_customers_export_nonce', 'nonce' );
	jwpm_customers_ensure_capability();

	global $wpdb;
	$table = jwpm_customers_get_table_name();

	$filename = 'jwpm-customers-' . gmdate( 'Ymd-His' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );

	$headers = array(
		'id',
		'customer_code',
		'name',
		'phone',
		'whatsapp',
		'email',
		'city',
		'area',
		'address',
		'cnic',
		'dob',
		'gender',
		'customer_type',
		'status',
		'credit_limit',
		'opening_balance',
		'current_balance',
		'total_purchases',
		'total_returns',
		'total_paid',
		'price_group',
		'tags',
		'notes',
		'is_demo',
		'created_at',
		'updated_at',
	);

	fputcsv( $output, $headers );

	$sql  = "SELECT * FROM {$table} ORDER BY created_at DESC";
	$rows = $wpdb->get_results( $sql, ARRAY_A );

	if ( $rows ) {
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $headers as $key ) {
				$line[] = isset( $row[ $key ] ) ? $row[ $key ] : '';
			}
			fputcsv( $output, $line );
		}
	}

	fclose( $output );
	exit;
}

/**
 * 8) Demo Customers Create
 */
add_action( 'wp_ajax_jwpm_customers_demo_create', 'jwpm_ajax_customers_demo_create' );
function jwpm_ajax_customers_demo_create() {
	check_ajax_referer( 'jwpm_customers_demo_nonce', 'nonce' );
	jwpm_customers_ensure_capability();

	global $wpdb;
	$table = jwpm_customers_get_table_name();

	$names = array(
		'Ali Khan',
		'Ahmed Raza',
		'Fatima Noor',
		'Sana Iqbal',
		'Bilal Hussain',
		'Zainab Sheikh',
		'Usman Ali',
		'Muhammad Asad',
		'Hina Khan',
		'Laiba Ahmed',
	);

	$cities = array( 'Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'Faisalabad' );
	$types  = array( 'walkin', 'regular', 'wholesale', 'vip' );

	$current_user = get_current_user_id();

	$created = 0;

	foreach ( $names as $index => $name ) {
		$phone = '03' . wp_rand( 100000000, 999999999 );

		// skip if phone exists
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE phone = %s",
				$phone
			)
		);

		if ( $exists ) {
			continue;
		}

		$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
		$next   = $max_id + 1;

		$data = array(
			'customer_code'   => sprintf( 'CUST-%04d', $next ),
			'name'            => $name,
			'phone'           => $phone,
			'city'            => $cities[ array_rand( $cities ) ],
			'customer_type'   => $types[ array_rand( $types ) ],
			'status'          => 'active',
			'credit_limit'    => '0.000',
			'opening_balance' => '0.000',
			'current_balance' => '0.000',
			'is_demo'         => 1,
			'created_by'      => $current_user,
		);

		$ok = $wpdb->insert( $table, $data );

		if ( $ok ) {
			$created++;
		}
	}

	wp_send_json_success(
		array(
			'message' => __( 'Demo کسٹمرز بنا دیے گئے۔', 'jwpm' ),
			'created' => $created,
		)
	);
}

/**
 * 9) Demo Customers Clear
 */
add_action( 'wp_ajax_jwpm_customers_demo_clear', 'jwpm_ajax_customers_demo_clear' );
function jwpm_ajax_customers_demo_clear() {
	check_ajax_referer( 'jwpm_customers_demo_nonce', 'nonce' );
	jwpm_customers_ensure_capability();

	global $wpdb;
	$table = jwpm_customers_get_table_name();

	$deleted = $wpdb->query( "DELETE FROM {$table} WHERE is_demo = 1" );

	wp_send_json_success(
		array(
			'message' => __( 'Demo کسٹمرز حذف ہو گئے۔', 'jwpm' ),
			'deleted' => (int) $deleted,
		)
	);
}

// 🔴 یہاں پر [Customers AJAX Handlers] ختم ہو رہا ہے
// ✅ Syntax verified block end
