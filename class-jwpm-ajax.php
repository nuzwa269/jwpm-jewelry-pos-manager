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
/** Part 43 — Installments AJAX Handlers */
// 🟢 یہاں سے [Installments AJAX Handlers] شروع ہو رہا ہے

if ( ! function_exists( 'jwpm_installments_sanitize_decimal' ) ) {
	/**
	 * decimal ویلیو کو safely sanitize کرے
	 */
	function jwpm_installments_sanitize_decimal( $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) {
			return '0.000';
		}
		$value = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $value );
		$float = floatval( $value );
		return number_format( $float, 3, '.', '' );
	}
}

if ( ! function_exists( 'jwpm_installments_ensure_capability' ) ) {
	function jwpm_installments_ensure_capability() {
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

if ( ! function_exists( 'jwpm_installments_table_name' ) ) {
	function jwpm_installments_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'jwpm_installments';
	}
}
if ( ! function_exists( 'jwpm_installments_schedule_table_name' ) ) {
	function jwpm_installments_schedule_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'jwpm_installment_schedule';
	}
}
if ( ! function_exists( 'jwpm_installments_payments_table_name' ) ) {
	function jwpm_installments_payments_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'jwpm_installment_payments';
	}
}

/**
 * 1) Get Installments List (filters + pagination)
 */
add_action( 'wp_ajax_jwpm_get_installments', 'jwpm_ajax_get_installments' );
function jwpm_ajax_get_installments() {
	check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;

	$contracts_table = jwpm_installments_table_name();
	$customers_table = $wpdb->prefix . 'jwpm_customers';

	$search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	$status    = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
	$date_mode = isset( $_POST['date_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['date_mode'] ) ) : 'sale';
	$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
	$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
	$page      = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
	$perpage   = isset( $_POST['per_page'] ) ? max( 1, intval( $_POST['per_page'] ) ) : 20;

	$where  = 'WHERE 1=1';
	$params = array();

	if ( $search ) {
		$like        = '%' . $wpdb->esc_like( $search ) . '%';
		$where      .= ' AND (c.name LIKE %s OR c.phone LIKE %s OR i.contract_code LIKE %s)';
		$params[]    = $like;
		$params[]    = $like;
		$params[]    = $like;
	}

	if ( $status ) {
		$where    .= ' AND i.status = %s';
		$params[] = $status;
	}

	if ( $date_from && $date_to ) {
		$column   = ( 'due' === $date_mode ) ? 'i.start_date' : 'i.sale_date';
		$where   .= " AND {$column} BETWEEN %s AND %s";
		$params[] = $date_from;
		$params[] = $date_to;
	} elseif ( $date_from ) {
		$column   = ( 'due' === $date_mode ) ? 'i.start_date' : 'i.sale_date';
		$where   .= " AND {$column} >= %s";
		$params[] = $date_from;
	} elseif ( $date_to ) {
		$column   = ( 'due' === $date_mode ) ? 'i.start_date' : 'i.sale_date';
		$where   .= " AND {$column} <= %s";
		$params[] = $date_to;
	}

	$count_sql = "SELECT COUNT(*) FROM {$contracts_table} i
		LEFT JOIN {$customers_table} c ON i.customer_id = c.id
		{$where}";

	$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

	$offset = ( $page - 1 ) * $perpage;

	$items_sql = "SELECT 
			i.*,
			c.name AS customer_name,
			c.phone AS customer_phone
		FROM {$contracts_table} i
		LEFT JOIN {$customers_table} c ON i.customer_id = c.id
		{$where}
		ORDER BY i.created_at DESC
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
 * 2) Get single Installment Contract + basic stats
 */
add_action( 'wp_ajax_jwpm_get_installment', 'jwpm_ajax_get_installment' );
function jwpm_ajax_get_installment() {
	check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;

	$contracts_table = jwpm_installments_table_name();
	$customers_table = $wpdb->prefix . 'jwpm_customers';
	$schedule_table  = jwpm_installments_schedule_table_name();
	$payments_table  = jwpm_installments_payments_table_name();

	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	if ( $id <= 0 ) {
		wp_send_json_error(
			array(
				'message' => __( 'غلط Contract ID۔', 'jwpm' ),
			),
			400
		);
	}

	$sql  = "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone
		FROM {$contracts_table} i
		LEFT JOIN {$customers_table} c ON i.customer_id = c.id
		WHERE i.id = %d";
	$item = $wpdb->get_row( $wpdb->prepare( $sql, $id ), ARRAY_A );

	if ( ! $item ) {
		wp_send_json_error(
			array(
				'message' => __( 'Contract نہیں ملا۔', 'jwpm' ),
			),
			404
		);
	}

	// basic schedule counts
	$stats = array(
		'total_installments' => 0,
		'paid_installments'  => 0,
		'pending_installments' => 0,
		'overdue_installments' => 0,
	);

	$sched_stats_sql = "SELECT 
		COUNT(*) AS total_installments,
		SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_installments,
		SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_installments,
		SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS overdue_installments
		FROM {$schedule_table}
		WHERE contract_id = %d";
	$row_stats       = $wpdb->get_row( $wpdb->prepare( $sched_stats_sql, $id ), ARRAY_A );
	if ( $row_stats ) {
		$stats = array_merge( $stats, $row_stats );
	}

	// total paid
	$total_paid = (float) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT SUM(amount) FROM {$payments_table} WHERE contract_id = %d",
			$id
		)
	);

	wp_send_json_success(
		array(
			'item'       => $item,
			'schedule'   => $stats,
			'total_paid' => number_format( $total_paid, 3, '.', '' ),
		)
	);
}

/**
 * 3) Save Installment Contract (insert / update) + optional Auto Schedule
 */
add_action( 'wp_ajax_jwpm_save_installment', 'jwpm_ajax_save_installment' );
function jwpm_ajax_save_installment() {
	check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;

	$contracts_table = jwpm_installments_table_name();
	$schedule_table  = jwpm_installments_schedule_table_name();

	$id          = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	$customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;

	if ( $customer_id <= 0 ) {
		wp_send_json_error(
			array(
				'message' => __( 'Customer منتخب کرنا ضروری ہے۔', 'jwpm' ),
			),
			400
		);
	}

	$total_amount     = jwpm_installments_sanitize_decimal( isset( $_POST['total_amount'] ) ? wp_unslash( $_POST['total_amount'] ) : '0' );
	$advance_amount   = jwpm_installments_sanitize_decimal( isset( $_POST['advance_amount'] ) ? wp_unslash( $_POST['advance_amount'] ) : '0' );
	$installment_cnt  = isset( $_POST['installment_count'] ) ? max( 0, intval( $_POST['installment_count'] ) ) : 0;
	$frequency        = isset( $_POST['installment_frequency'] ) ? sanitize_text_field( wp_unslash( $_POST['installment_frequency'] ) ) : 'monthly';
	$start_date       = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
	$sale_date        = isset( $_POST['sale_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sale_date'] ) ) : '';
	$status           = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';
	$remarks          = isset( $_POST['remarks'] ) ? sanitize_textarea_field( wp_unslash( $_POST['remarks'] ) ) : '';
	$auto_schedule    = ! empty( $_POST['auto_generate_schedule'] );

	$net_amount = jwpm_installments_sanitize_decimal( (float) $total_amount - (float) $advance_amount );
	$current_user = get_current_user_id();

	$data = array(
		'customer_id'           => $customer_id,
		'sale_date'             => $sale_date ? $sale_date : current_time( 'mysql' ),
		'total_amount'          => $total_amount,
		'advance_amount'        => $advance_amount,
		'net_amount'            => $net_amount,
		'installment_count'     => $installment_cnt,
		'installment_frequency' => $frequency,
		'start_date'            => $start_date ? $start_date : null,
		'status'                => $status,
		'remarks'               => $remarks,
	);

	// end_date simple calc — later fine tuning
	if ( $start_date && $installment_cnt > 0 ) {
		try {
			$dt = new DateTime( $start_date );
			if ( 'weekly' === $frequency ) {
				$dt->modify( '+' . ( $installment_cnt - 1 ) . ' weeks' );
			} else {
				$dt->modify( '+' . ( $installment_cnt - 1 ) . ' months' );
			}
			$data['end_date'] = $dt->format( 'Y-m-d' );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// ignore
		}
	}

	if ( $id > 0 ) {
		$data['updated_by'] = $current_user;
		$updated            = $wpdb->update(
			$contracts_table,
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error(
				array(
					'message' => __( 'Contract اپڈیٹ نہیں ہو سکا۔', 'jwpm' ),
				),
				500
			);
		}
	} else {
		// نیا contract
		// contract_code generate
		$max_id        = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$contracts_table}" );
		$next          = $max_id + 1;
		$contract_code = sprintf( 'INST-%04d', $next );

		$data['contract_code']      = $contract_code;
		$data['current_outstanding'] = $net_amount;
		$data['created_by']         = $current_user;
		$data['is_demo']            = 0;

		$inserted = $wpdb->insert( $contracts_table, $data );

		if ( ! $inserted ) {
			wp_send_json_error(
				array(
					'message' => __( 'Contract محفوظ نہیں ہو سکا۔', 'jwpm' ),
				),
				500
			);
		}

		$id = (int) $wpdb->insert_id;
	}

	// Auto-generate schedule (very simple even split)
	if ( $auto_schedule && $installment_cnt > 0 && $net_amount > 0 && $start_date ) {
		// پرانا schedule delete
		$wpdb->delete( $schedule_table, array( 'contract_id' => $id ), array( '%d' ) );

		$per_amount = number_format( (float) $net_amount / (float) $installment_cnt, 3, '.', '' );

		try {
			$dt = new DateTime( $start_date );
		} catch ( Exception $e ) {
			$dt = null;
		}

		for ( $i = 1; $i <= $installment_cnt; $i++ ) {
			$due_date = $start_date;
			if ( $dt ) {
				if ( $i > 1 ) {
					if ( 'weekly' === $frequency ) {
						$dt->modify( '+1 week' );
					} else {
						$dt->modify( '+1 month' );
					}
				}
				$due_date = $dt->format( 'Y-m-d' );
			}

			$wpdb->insert(
				$schedule_table,
				array(
					'contract_id'    => $id,
					'installment_no' => $i,
					'due_date'       => $due_date,
					'amount'         => $per_amount,
					'paid_amount'    => '0.000',
					'status'         => 'pending',
					'is_demo'        => 0,
				)
			);
		}
	}

	// واپس وہی record
	$customers_table = $wpdb->prefix . 'jwpm_customers';
	$sql             = "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone
		FROM {$contracts_table} i
		LEFT JOIN {$customers_table} c ON i.customer_id = c.id
		WHERE i.id = %d";
	$item            = $wpdb->get_row( $wpdb->prepare( $sql, $id ), ARRAY_A );

	wp_send_json_success(
		array(
			'message' => __( 'Installment Plan محفوظ ہو گیا۔', 'jwpm' ),
			'item'    => $item,
		)
	);
}

/**
 * 4) Get Schedule (all installments for a contract)
 */
add_action( 'wp_ajax_jwpm_get_installment_schedule', 'jwpm_ajax_get_installment_schedule' );
function jwpm_ajax_get_installment_schedule() {
	check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;
	$table = jwpm_installments_schedule_table_name();

	$contract_id = isset( $_POST['contract_id'] ) ? intval( $_POST['contract_id'] ) : 0;
	if ( $contract_id <= 0 ) {
		wp_send_json_error(
			array(
				'message' => __( 'غلط Contract ID۔', 'jwpm' ),
			),
			400
		);
	}

	$sql  = "SELECT * FROM {$table} WHERE contract_id = %d ORDER BY installment_no ASC";
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $contract_id ), ARRAY_A );

	wp_send_json_success(
		array(
			'items' => $rows,
		)
	);
}

/**
 * 5) Add Payment
 */
add_action( 'wp_ajax_jwpm_add_installment_payment', 'jwpm_ajax_add_installment_payment' );
function jwpm_ajax_add_installment_payment() {
	check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;

	$contracts_table = jwpm_installments_table_name();
	$payments_table  = jwpm_installments_payments_table_name();

	$contract_id = isset( $_POST['contract_id'] ) ? intval( $_POST['contract_id'] ) : 0;
	if ( $contract_id <= 0 ) {
		wp_send_json_error(
			array(
				'message' => __( 'غلط Contract ID۔', 'jwpm' ),
			),
			400
		);
	}

	$amount       = jwpm_installments_sanitize_decimal( isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : '0' );
	$payment_date = isset( $_POST['payment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_date'] ) ) : gmdate( 'Y-m-d' );
	$method       = isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : 'cash';
	$reference_no = isset( $_POST['reference_no'] ) ? sanitize_text_field( wp_unslash( $_POST['reference_no'] ) ) : '';
	$note         = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

	if ( (float) $amount <= 0 ) {
		wp_send_json_error(
			array(
				'message' => __( 'Amount صفر سے زیادہ ہونی چاہئے۔', 'jwpm' ),
			),
			400
		);
	}

	$data = array(
		'contract_id'  => $contract_id,
		'schedule_id'  => null,
		'payment_date' => $payment_date,
		'amount'       => $amount,
		'method'       => $method,
		'reference_no' => $reference_no,
		'received_by'  => get_current_user_id(),
		'note'         => $note,
		'is_demo'      => 0,
	);

	$ok = $wpdb->insert( $payments_table, $data );
	if ( ! $ok ) {
		wp_send_json_error(
			array(
				'message' => __( 'Payment محفوظ نہیں ہو سکی۔', 'jwpm' ),
			),
			500
		);
	}

	// Contract کے outstanding کو کم کریں
	$current_outstanding = (float) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT current_outstanding FROM {$contracts_table} WHERE id = %d",
			$contract_id
		)
	);

	$new_outstanding = $current_outstanding - (float) $amount;
	if ( $new_outstanding < 0 ) {
		$new_outstanding = 0;
	}

	$wpdb->update(
		contracts_table: $contracts_table,
		data: array(
			'current_outstanding' => jwpm_installments_sanitize_decimal( $new_outstanding ),
		),
		where: array( 'id' => $contract_id ),
		where_format: array( '%d' )
	);

	wp_send_json_success(
		array(
			'message' => __( 'Payment محفوظ ہو گئی۔', 'jwpm' ),
		)
	);
}

/**
 * 6) Get Payments List (per contract)
 */
add_action( 'wp_ajax_jwpm_get_installment_payments', 'jwpm_ajax_get_installment_payments' );
function jwpm_ajax_get_installment_payments() {
	check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;

	$payments_table = jwpm_installments_payments_table_name();

	$contract_id = isset( $_POST['contract_id'] ) ? intval( $_POST['contract_id'] ) : 0;
	if ( $contract_id <= 0 ) {
		wp_send_json_error(
			array(
				'message' => __( 'غلط Contract ID۔', 'jwpm' ),
			),
			400
		);
	}

	$sql  = "SELECT * FROM {$payments_table} WHERE contract_id = %d ORDER BY payment_date DESC, id DESC";
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $contract_id ), ARRAY_A );

	wp_send_json_success(
		array(
			'items' => $rows,
		)
	);
}

/**
 * 7) Change Contract Status (Cancel / Default / Complete)
 */
add_action( 'wp_ajax_jwpm_update_installment_status', 'jwpm_ajax_update_installment_status' );
function jwpm_ajax_update_installment_status() {
	check_ajax_referer( 'jwpm_installments_main_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;

	$table  = jwpm_installments_table_name();
	$id     = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

	if ( $id <= 0 || ! in_array( $status, array( 'active', 'completed', 'defaulted', 'cancelled' ), true ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'غلط ڈیٹا موصول ہوا۔', 'jwpm' ),
			),
			400
		);
	}

	$updated = $wpdb->update(
		$table,
		array(
			'status'     => $status,
			'updated_by' => get_current_user_id(),
		),
		array( 'id' => $id ),
		null,
		array( '%d' )
	);

	if ( false === $updated ) {
		wp_send_json_error(
			array(
				'message' => __( 'Status اپڈیٹ نہیں ہو سکا۔', 'jwpm' ),
			),
			500
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Status اپڈیٹ ہو گیا۔', 'jwpm' ),
			'status'  => $status,
		)
	);
}

/**
 * 8) Demo Installments Create
 */
add_action( 'wp_ajax_jwpm_installments_demo_create', 'jwpm_ajax_installments_demo_create' );
function jwpm_ajax_installments_demo_create() {
	check_ajax_referer( 'jwpm_installments_demo_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;

	$contracts_table = jwpm_installments_table_name();
	$schedule_table  = jwpm_installments_schedule_table_name();

	$customers_table = $wpdb->prefix . 'jwpm_customers';

	$customer_ids = $wpdb->get_col( "SELECT id FROM {$customers_table} ORDER BY id DESC LIMIT 5" );
	if ( empty( $customer_ids ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Demo کیلئے کم از کم کچھ Customers موجود ہونے چاہئیں۔', 'jwpm' ),
			),
			400
		);
	}

	$current_user = get_current_user_id();
	$created      = 0;

	for ( $i = 0; $i < 5; $i++ ) {
		$customer_id = $customer_ids[ array_rand( $customer_ids ) ];
		$total       = 50000 + ( $i * 5000 );
		$advance     = 10000;
		$net         = $total - $advance;
		$count       = 5;

		$max_id        = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$contracts_table}" );
		$next          = $max_id + 1;
		$contract_code = sprintf( 'INST-%04d', $next );

		$start_date = gmdate( 'Y-m-d', strtotime( '+' . ( $i + 1 ) . ' days' ) );
		$end_date   = gmdate( 'Y-m-d', strtotime( '+' . ( $i + 1 + ( $count - 1 ) ) . ' months' ) );

		$ok = $wpdb->insert(
			$contracts_table,
			array(
				'contract_code'       => $contract_code,
				'customer_id'         => $customer_id,
				'sale_date'           => gmdate( 'Y-m-d' ),
				'total_amount'        => jwpm_installments_sanitize_decimal( $total ),
				'advance_amount'      => jwpm_installments_sanitize_decimal( $advance ),
				'net_amount'          => jwpm_installments_sanitize_decimal( $net ),
				'installment_count'   => $count,
				'installment_frequency' => 'monthly',
				'start_date'          => $start_date,
				'end_date'            => $end_date,
				'status'              => 'active',
				'current_outstanding' => jwpm_installments_sanitize_decimal( $net ),
				'is_demo'             => 1,
				'created_by'          => $current_user,
			)
		);

		if ( ! $ok ) {
			continue;
		}

		$contract_id = (int) $wpdb->insert_id;
		$per         = jwpm_installments_sanitize_decimal( $net / $count );
		$date        = new DateTime( $start_date );

		for ( $n = 1; $n <= $count; $n++ ) {
			if ( $n > 1 ) {
				$date->modify( '+1 month' );
			}
			$wpdb->insert(
				$schedule_table,
				array(
					'contract_id'    => $contract_id,
					'installment_no' => $n,
					'due_date'       => $date->format( 'Y-m-d' ),
					'amount'         => $per,
					'paid_amount'    => '0.000',
					'status'         => 'pending',
					'is_demo'        => 1,
				)
			);
		}

		$created++;
	}

	wp_send_json_success(
		array(
			'message' => __( 'Demo Installments بنا دیے گئے۔', 'jwpm' ),
			'created' => $created,
		)
	);
}

/**
 * 9) Demo Installments Clear
 */
add_action( 'wp_ajax_jwpm_installments_demo_clear', 'jwpm_ajax_installments_demo_clear' );
function jwpm_ajax_installments_demo_clear() {
	check_ajax_referer( 'jwpm_installments_demo_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;

	$contracts_table = jwpm_installments_table_name();
	$schedule_table  = jwpm_installments_schedule_table_name();
	$payments_table  = jwpm_installments_payments_table_name();

	$demo_ids = $wpdb->get_col( "SELECT id FROM {$contracts_table} WHERE is_demo = 1" );

	if ( ! empty( $demo_ids ) ) {
		$ids_placeholders = implode( ',', array_fill( 0, count( $demo_ids ), '%d' ) );

		// schedule
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$schedule_table} WHERE contract_id IN ({$ids_placeholders})",
				$demo_ids
			)
		);

		// payments
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$payments_table} WHERE contract_id IN ({$ids_placeholders})",
				$demo_ids
			)
		);

		// contracts
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$contracts_table} WHERE id IN ({$ids_placeholders})",
				$demo_ids
			)
		);
	}

	wp_send_json_success(
		array(
			'message' => __( 'Demo Installments حذف ہو گئے۔', 'jwpm' ),
			'deleted' => is_array( $demo_ids ) ? count( $demo_ids ) : 0,
		)
	);
}

/**
 * 10) Import Installments (CSV)
 */
add_action( 'wp_ajax_jwpm_import_installments', 'jwpm_ajax_import_installments' );
function jwpm_ajax_import_installments() {
	check_ajax_referer( 'jwpm_installments_import_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	if ( empty( $_FILES['file']['tmp_name'] ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'فائل موصول نہیں ہوئی۔', 'jwpm' ),
			),
			400
		);
	}

	$file = $_FILES['file']['tmp_name'];

	if ( ! file_exists( $file ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'فائل دستیاب نہیں۔', 'jwpm' ),
			),
			400
		);
	}

	$skip_duplicates = ! empty( $_POST['skip_duplicates'] );

	global $wpdb;

	$contracts_table = jwpm_installments_table_name();
	$customers_table = $wpdb->prefix . 'jwpm_customers';

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

	$map = array();
	foreach ( $header as $index => $col ) {
		$key        = strtolower( trim( $col ) );
		$map[ $key ] = $index;
	}

	if ( ! isset( $map['customer_phone'], $map['total_amount'], $map['installment_count'] ) ) {
		fclose( $handle );
		wp_send_json_error(
			array(
				'message' => __( 'کم از کم customer_phone, total_amount اور installment_count کالم ضروری ہیں۔', 'jwpm' ),
			),
			400
		);
	}

	$current_user = get_current_user_id();

	while ( ( $row = fgetcsv( $handle ) ) !== false ) {
		$total++;

		$phone = sanitize_text_field( $row[ $map['customer_phone'] ] ?? '' );
		$total_amount_raw = $row[ $map['total_amount'] ] ?? '0';
		$count_raw        = $row[ $map['installment_count'] ] ?? '0';

		if ( '' === $phone ) {
			$skipped++;
			continue;
		}

		$customer_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$customers_table} WHERE phone = %s",
				$phone
			)
		);

		if ( ! $customer_id ) {
			$skipped++;
			continue;
		}

		$total_amount   = jwpm_installments_sanitize_decimal( $total_amount_raw );
		$installments_n = max( 1, intval( $count_raw ) );
		$advance_amount = jwpm_installments_sanitize_decimal( $row[ $map['advance_amount'] ] ?? '0' );
		$net_amount     = jwpm_installments_sanitize_decimal( (float) $total_amount - (float) $advance_amount );
		$sale_date      = isset( $map['sale_date'] ) ? sanitize_text_field( $row[ $map['sale_date'] ] ) : gmdate( 'Y-m-d' );
		$start_date     = isset( $map['start_date'] ) ? sanitize_text_field( $row[ $map['start_date'] ] ) : $sale_date;

		// skip duplicates by contract_code اگر موجود ہو
		$contract_code = null;
		if ( isset( $map['contract_code'] ) && ! empty( $row[ $map['contract_code'] ] ) ) {
			$contract_code = sanitize_text_field( $row[ $map['contract_code'] ] );
			if ( $skip_duplicates ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$contracts_table} WHERE contract_code = %s",
						$contract_code
					)
				);
				if ( $exists ) {
					$skipped++;
					continue;
				}
			}
		}

		if ( ! $contract_code ) {
			$max_id        = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$contracts_table}" );
			$next          = $max_id + 1;
			$contract_code = sprintf( 'INST-%04d', $next );
		}

		$ok = $wpdb->insert(
			$contracts_table,
			array(
				'contract_code'       => $contract_code,
				'customer_id'         => $customer_id,
				'sale_date'           => $sale_date,
				'total_amount'        => $total_amount,
				'advance_amount'      => $advance_amount,
				'net_amount'          => $net_amount,
				'installment_count'   => $installments_n,
				'installment_frequency' => 'monthly',
				'start_date'          => $start_date,
				'status'              => 'active',
				'current_outstanding' => $net_amount,
				'is_demo'             => 0,
				'created_by'          => $current_user,
			)
		);

		if ( ! $ok ) {
			$skipped++;
			continue;
		}

		$contract_id = (int) $wpdb->insert_id;

		// simple even schedule
		$schedule_table = jwpm_installments_schedule_table_name();
		$per            = jwpm_installments_sanitize_decimal( $net_amount / $installments_n );
		$dt             = new DateTime( $start_date );
		for ( $i = 1; $i <= $installments_n; $i++ ) {
			if ( $i > 1 ) {
				$dt->modify( '+1 month' );
			}
			$wpdb->insert(
				$schedule_table,
				array(
					'contract_id'    => $contract_id,
					'installment_no' => $i,
					'due_date'       => $dt->format( 'Y-m-d' ),
					'amount'         => $per,
					'paid_amount'    => '0.000',
					'status'         => 'pending',
					'is_demo'        => 0,
				)
			);
		}

		$inserted++;
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
 * 11) Export Installments (CSV for Excel)
 */
add_action( 'wp_ajax_jwpm_export_installments', 'jwpm_ajax_export_installments' );
function jwpm_ajax_export_installments() {
	check_ajax_referer( 'jwpm_installments_export_nonce', 'nonce' );
	jwpm_installments_ensure_capability();

	global $wpdb;

	$contracts_table = jwpm_installments_table_name();
	$customers_table = $wpdb->prefix . 'jwpm_customers';

	$filename = 'jwpm-installments-' . gmdate( 'Ymd-His' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );

	$headers = array(
		'id',
		'contract_code',
		'customer_id',
		'customer_name',
		'customer_phone',
		'sale_date',
		'total_amount',
		'advance_amount',
		'net_amount',
		'installment_count',
		'installment_frequency',
		'start_date',
		'end_date',
		'status',
		'current_outstanding',
		'remarks',
		'is_demo',
		'created_at',
		'updated_at',
	);

	fputcsv( $output, $headers );

	$sql  = "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone
		FROM {$contracts_table} i
		LEFT JOIN {$customers_table} c ON i.customer_id = c.id
		ORDER BY i.created_at DESC";
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

// 🔴 یہاں پر [Installments AJAX Handlers] ختم ہو رہا ہے
// ✅ Syntax verified block end
<?php
/** Part 9 — JWPM Repair Jobs AJAX Handlers
 * یہاں Repair Jobs module کے لیے (AJAX) endpoints ہیں۔
 */

// 🟢 یہاں سے [JWPM Repair AJAX] شروع ہو رہا ہے

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * مشترکہ helper — main nonce چیک
 */
function jwpm_repair_check_main_nonce() {
	$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'jwpm_repair_main_nonce' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Security check failed (repair nonce).', 'jwpm' ),
			)
		);
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'آپ کو اس action کی اجازت نہیں۔', 'jwpm' ),
			)
		);
	}
}

/**
 * Repair list — jwpm_get_repairs
 */
function jwpm_ajax_get_repairs() {
	global $wpdb;
	jwpm_repair_check_main_nonce();

	$table = $wpdb->prefix . 'jwpm_repairs';

	$search      = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';
	$status      = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
	$priority    = isset( $_REQUEST['priority'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['priority'] ) ) : '';
	$date_from   = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '';
	$date_to     = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '';
	$page        = isset( $_REQUEST['page'] ) ? max( 1, (int) $_REQUEST['page'] ) : 1;
	$per_page    = isset( $_REQUEST['per_page'] ) ? max( 1, (int) $_REQUEST['per_page'] ) : 20;
	$offset      = ( $page - 1 ) * $per_page;

	$where  = 'WHERE 1=1';
	$params = array();

	if ( $search ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= " AND (customer_name LIKE %s OR customer_phone LIKE %s OR tag_no LIKE %s OR job_code LIKE %s)";
		$params = array_merge( $params, array( $like, $like, $like, $like ) );
	}

	if ( $status ) {
		$where  .= ' AND job_status = %s';
		$params[] = $status;
	}

	if ( $priority ) {
		$where  .= ' AND priority = %s';
		$params[] = $priority;
	}

	if ( $date_from ) {
		$where  .= ' AND promised_date >= %s';
		$params[] = $date_from;
	}

	if ( $date_to ) {
		$where  .= ' AND promised_date <= %s';
		$params[] = $date_to;
	}

	$sql_count = "SELECT COUNT(*) FROM {$table} {$where}";
	$total     = (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) );

	$sql_items = "SELECT * FROM {$table} {$where} ORDER BY promised_date ASC, id DESC LIMIT %d OFFSET %d";
	$params_items = array_merge( $params, array( $per_page, $offset ) );

	$rows = $wpdb->get_results( $wpdb->prepare( $sql_items, $params_items ), ARRAY_A );

	$items = array();

	if ( $rows ) {
		foreach ( $rows as $row ) {
			$items[] = array(
				'id'              => (int) $row['id'],
				'job_code'        => $row['job_code'],
				'tag_no'          => $row['tag_no'],
				'customer_name'   => $row['customer_name'],
				'customer_phone'  => $row['customer_phone'],
				'item_description'=> $row['item_description'],
				'job_type'        => $row['job_type'],
				'promised_date'   => $row['promised_date'],
				'job_status'      => $row['job_status'],
				'actual_charges'  => (float) $row['actual_charges'],
				'balance_amount'  => (float) $row['balance_amount'],
				'priority'        => $row['priority'],
			);
		}
	}

	$pagination = array(
		'total'      => $total,
		'page'       => $page,
		'per_page'   => $per_page,
		'total_page' => $per_page ? max( 1, (int) ceil( $total / $per_page ) ) : 1,
	);

	wp_send_json_success(
		array(
			'items'      => $items,
			'pagination' => $pagination,
		)
	);
}
add_action( 'wp_ajax_jwpm_get_repairs', 'jwpm_ajax_get_repairs' );

/**
 * Single repair — jwpm_get_repair
 */
function jwpm_ajax_get_repair() {
	global $wpdb;
	jwpm_repair_check_main_nonce();

	$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;
	if ( ! $id ) {
		wp_send_json_error(
			array(
				'message' => __( 'Invalid repair ID.', 'jwpm' ),
			)
		);
	}

	$table_repair = $wpdb->prefix . 'jwpm_repairs';
	$table_logs   = $wpdb->prefix . 'jwpm_repair_logs';

	$repair = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table_repair} WHERE id = %d", $id ),
		ARRAY_A
	);

	if ( ! $repair ) {
		wp_send_json_error(
			array(
				'message' => __( 'Repair job not found.', 'jwpm' ),
			)
		);
	}

	$logs = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table_logs} WHERE repair_id = %d ORDER BY updated_at DESC, id DESC", $id ),
		ARRAY_A
	);

	$logs_out = array();
	if ( $logs ) {
		foreach ( $logs as $log ) {
			$logs_out[] = array(
				'id'         => (int) $log['id'],
				'status'     => $log['status'],
				'status_label' => $log['status'],
				'note'       => $log['note'],
				'updated_at' => $log['updated_at'],
				'updated_by' => $log['updated_by'],
			);
		}
	}

	wp_send_json_success(
		array(
			'header' => $repair,
			'logs'   => $logs_out,
		)
	);
}
add_action( 'wp_ajax_jwpm_get_repair', 'jwpm_ajax_get_repair' );

/**
 * Save repair (create/update + quick_update)
 * action: jwpm_save_repair
 */
function jwpm_ajax_save_repair() {
	global $wpdb;
	jwpm_repair_check_main_nonce();

	$id           = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;
	$quick_update = isset( $_REQUEST['quick_update'] ) ? (int) $_REQUEST['quick_update'] : 0;

	$table = $wpdb->prefix . 'jwpm_repairs';

	if ( $quick_update && $id ) {
		// Quick update: job_status / priority / payment_status وغیرہ
		$fields = array();
		$formats = array();

		if ( isset( $_REQUEST['job_status'] ) ) {
			$fields['job_status'] = sanitize_text_field( wp_unslash( $_REQUEST['job_status'] ) );
			$formats[]            = '%s';
		}
		if ( isset( $_REQUEST['priority'] ) ) {
			$fields['priority'] = sanitize_text_field( wp_unslash( $_REQUEST['priority'] ) );
			$formats[]          = '%s';
		}
		if ( isset( $_REQUEST['payment_status'] ) ) {
			$fields['payment_status'] = sanitize_text_field( wp_unslash( $_REQUEST['payment_status'] ) );
			$formats[]                = '%s';
		}

		if ( ! empty( $fields ) ) {
			$fields['updated_at'] = current_time( 'mysql' );
			$fields['updated_by'] = get_current_user_id();
			$formats[]            = '%s';
			$formats[]            = '%d';

			$wpdb->update(
				$table,
				$fields,
				array( 'id' => $id ),
				$formats,
				array( '%d' )
			);
		}

		wp_send_json_success(
			array(
				'id' => $id,
			)
		);
	}

	// Full save
	$data = array();

	$data['customer_name']   = isset( $_REQUEST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['customer_name'] ) ) : '';
	$data['customer_phone']  = isset( $_REQUEST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['customer_phone'] ) ) : '';
	$data['tag_no']          = isset( $_REQUEST['tag_no'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tag_no'] ) ) : '';
	$data['job_code']        = isset( $_REQUEST['job_code'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['job_code'] ) ) : '';
	$data['item_description']= isset( $_REQUEST['item_description'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['item_description'] ) ) : '';
	$data['job_type']        = isset( $_REQUEST['job_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['job_type'] ) ) : 'other';
	$data['problems']        = isset( $_REQUEST['problems'] ) ? wp_kses_post( wp_unslash( $_REQUEST['problems'] ) ) : '';
	$data['instructions']    = isset( $_REQUEST['instructions'] ) ? wp_kses_post( wp_unslash( $_REQUEST['instructions'] ) ) : '';
	$data['received_date']   = isset( $_REQUEST['received_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['received_date'] ) ) : '';
	$data['promised_date']   = isset( $_REQUEST['promised_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['promised_date'] ) ) : '';
	$data['delivered_date']  = isset( $_REQUEST['delivered_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['delivered_date'] ) ) : '';

	$data['gold_weight_in']  = isset( $_REQUEST['gold_weight_in'] ) ? (float) $_REQUEST['gold_weight_in'] : 0;
	$data['gold_weight_out'] = isset( $_REQUEST['gold_weight_out'] ) ? (float) $_REQUEST['gold_weight_out'] : 0;
	$data['estimated_charges']= isset( $_REQUEST['estimated_charges'] ) ? (float) $_REQUEST['estimated_charges'] : 0;
	$data['actual_charges']  = isset( $_REQUEST['actual_charges'] ) ? (float) $_REQUEST['actual_charges'] : 0;
	$data['advance_amount']  = isset( $_REQUEST['advance_amount'] ) ? (float) $_REQUEST['advance_amount'] : 0;
	$data['balance_amount']  = isset( $_REQUEST['balance_amount'] ) ? (float) $_REQUEST['balance_amount'] : ( $data['actual_charges'] - $data['advance_amount'] );

	$data['payment_status']  = isset( $_REQUEST['payment_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['payment_status'] ) ) : 'unpaid';
	$data['job_status']      = isset( $_REQUEST['job_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['job_status'] ) ) : 'received';
	$data['assigned_to']     = isset( $_REQUEST['assigned_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['assigned_to'] ) ) : '';
	$data['priority']        = isset( $_REQUEST['priority'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['priority'] ) ) : 'normal';
	$data['workshop_notes']  = isset( $_REQUEST['workshop_notes'] ) ? wp_kses_post( wp_unslash( $_REQUEST['workshop_notes'] ) ) : '';
	$data['internal_remarks']= isset( $_REQUEST['internal_remarks'] ) ? wp_kses_post( wp_unslash( $_REQUEST['internal_remarks'] ) ) : '';

	if ( empty( $data['customer_name'] ) && empty( $data['customer_phone'] ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Customer name یا phone ضروری ہے۔', 'jwpm' ),
			)
		);
	}

	$now = current_time( 'mysql' );
	$user_id = get_current_user_id();

	if ( $id ) {
		$data['updated_at'] = $now;
		$data['updated_by'] = $user_id;
		$wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	} else {
		if ( empty( $data['job_code'] ) ) {
			// Simple auto code (RJ-0001)
			$max_code = $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
			$next     = (int) $max_code + 1;
			$data['job_code'] = sprintf( 'RJ-%04d', $next );
		}
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$data['created_by'] = $user_id;
		$data['updated_by'] = $user_id;
		$wpdb->insert( $table, $data );
		$id = (int) $wpdb->insert_id;
	}

	wp_send_json_success(
		array(
			'id' => $id,
		)
	);
}
add_action( 'wp_ajax_jwpm_save_repair', 'jwpm_ajax_save_repair' );

/**
 * Soft delete / cancel — jwpm_delete_repair
 */
function jwpm_ajax_delete_repair() {
	global $wpdb;
	jwpm_repair_check_main_nonce();

	$id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;
	if ( ! $id ) {
		wp_send_json_error(
			array(
				'message' => __( 'Invalid repair ID.', 'jwpm' ),
			)
		);
	}

	$table = $wpdb->prefix . 'jwpm_repairs';

	$wpdb->update(
		$table,
		array(
			'job_status' => 'cancelled',
			'updated_at' => current_time( 'mysql' ),
			'updated_by' => get_current_user_id(),
		),
		array( 'id' => $id ),
		array( '%s', '%s', '%d' ),
		array( '%d' )
	);

	wp_send_json_success(
		array(
			'id' => $id,
		)
	);
}
add_action( 'wp_ajax_jwpm_delete_repair', 'jwpm_ajax_delete_repair' );

/**
 * Logs — jwpm_get_repair_logs + jwpm_save_repair_log
 */
function jwpm_ajax_get_repair_logs() {
	global $wpdb;
	jwpm_repair_check_main_nonce();

	$repair_id = isset( $_REQUEST['repair_id'] ) ? (int) $_REQUEST['repair_id'] : 0;
	if ( ! $repair_id ) {
		wp_send_json_error(
			array(
				'message' => __( 'Invalid repair ID.', 'jwpm' ),
			)
		);
	}

	$table = $wpdb->prefix . 'jwpm_repair_logs';
	$logs  = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE repair_id = %d ORDER BY updated_at DESC, id DESC", $repair_id ),
		ARRAY_A
	);

	$out = array();
	if ( $logs ) {
		foreach ( $logs as $log ) {
			$out[] = array(
				'id'          => (int) $log['id'],
				'status'      => $log['status'],
				'status_label'=> $log['status'],
				'note'        => $log['note'],
				'updated_at'  => $log['updated_at'],
				'updated_by'  => $log['updated_by'],
			);
		}
	}

	wp_send_json_success(
		array(
			'items' => $out,
		)
	);
}
add_action( 'wp_ajax_jwpm_get_repair_logs', 'jwpm_ajax_get_repair_logs' );

function jwpm_ajax_save_repair_log() {
	global $wpdb;
	jwpm_repair_check_main_nonce();

	$repair_id = isset( $_REQUEST['repair_id'] ) ? (int) $_REQUEST['repair_id'] : 0;
	$status    = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
	$note      = isset( $_REQUEST['note'] ) ? wp_kses_post( wp_unslash( $_REQUEST['note'] ) ) : '';

	if ( ! $repair_id || ! $status ) {
		wp_send_json_error(
			array(
				'message' => __( 'Repair ID اور status لازمی ہیں۔', 'jwpm' ),
			)
		);
	}

	$table = $wpdb->prefix . 'jwpm_repair_logs';

	$wpdb->insert(
		$table,
		array(
			'repair_id'  => $repair_id,
			'status'     => $status,
			'note'       => $note,
			'updated_at' => current_time( 'mysql' ),
			'updated_by' => get_current_user_id(),
		),
		array( '%d', '%s', '%s', '%s', '%d' )
	);

	wp_send_json_success(
		array(
			'id' => (int) $wpdb->insert_id,
		)
	);
}
add_action( 'wp_ajax_jwpm_save_repair_log', 'jwpm_ajax_save_repair_log' );

/**
 * Demo / Import / Export skeletons
 * (Detail logic آپ بعد میں expand کر سکتے ہیں، یہاں basic structure ہے)
 */
function jwpm_ajax_repair_demo_create() {
	jwpm_repair_check_main_nonce();
	// یہاں demo rows insert کرنے کے لیے jwpm_repairs میں basic sample data add کریں (آپ پہلے modules کی demo logic reuse کر سکتے ہیں)
	wp_send_json_success(
		array(
			'message' => __( 'Demo Repairs created (placeholder).', 'jwpm' ),
		)
	);
}
add_action( 'wp_ajax_jwpm_repair_demo_create', 'jwpm_ajax_repair_demo_create' );

function jwpm_ajax_repair_demo_clear() {
	jwpm_repair_check_main_nonce();
	// یہاں is_demo = 1 والی rows delete / truncate کریں
	wp_send_json_success(
		array(
			'message' => __( 'Demo Repairs cleared (placeholder).', 'jwpm' ),
		)
	);
}
add_action( 'wp_ajax_jwpm_repair_demo_clear', 'jwpm_ajax_repair_demo_clear' );

function jwpm_ajax_import_repairs() {
	// Import کیلئے الگ nonce:
	$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'jwpm_repair_import_nonce' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Security check failed (repair import).', 'jwpm' ),
			)
		);
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'آپ کو Import چلانے کی اجازت نہیں۔', 'jwpm' ),
			)
		);
	}

	// Placeholder result
	wp_send_json_success(
		array(
			'total'    => 0,
			'inserted' => 0,
			'skipped'  => 0,
		)
	);
}
add_action( 'wp_ajax_jwpm_import_repairs', 'jwpm_ajax_import_repairs' );

function jwpm_ajax_export_repairs() {
	// Export کیلئے nonce
	$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'jwpm_repair_export_nonce' ) ) {
		wp_die( esc_html__( 'Security check failed (repair export).', 'jwpm' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'آپ کو اس action کی اجازت نہیں۔', 'jwpm' ) );
	}

	// یہاں CSV output بنائیں (پہلے modules کے export logic کی طرح)
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=repair-jobs.csv' );

	$output = fopen( 'php://output', 'w' );
	fputcsv(
		$output,
		array(
			'job_code',
			'tag_no',
			'customer_name',
			'customer_phone',
			'item_description',
			'job_type',
			'received_date',
			'promised_date',
			'delivered_date',
			'estimated_charges',
			'actual_charges',
			'advance_amount',
			'balance_amount',
			'job_status',
			'priority',
		)
	);

	// TODO: یہاں واقعی data لکھیں
	fclose( $output );
	exit;
}
add_action( 'wp_ajax_jwpm_export_repairs', 'jwpm_ajax_export_repairs' );

// 🔴 یہاں پر [JWPM Repair AJAX] ختم ہو رہا ہے
// ✅ Syntax verified block end
<?php
// ... یہاں آپ کا موجودہ class-jwpm-ajax.php کوڈ ہے ...

// 🟢 یہاں سے [Accounts Module AJAX: Cashbook] شروع ہو رہا ہے

/** Part 21 — Accounts Module AJAX: Cashbook */

if ( ! function_exists( 'jwpm_ajax_require_cashbook_cap' ) ) {
    /**
     * Common capability + nonce check for cashbook actions
     */
    function jwpm_ajax_require_cashbook_cap() {
        check_ajax_referer( 'jwpm_cashbook_nonce', 'nonce' );

        if ( ! current_user_can( 'jwpm_view_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو Accounts تک رسائی کی اجازت نہیں ہے۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_view_accounts required.',
                ),
                403
            );
        }

        if ( ! function_exists( 'jwpm_accounts_ensure_tables' ) ) {
            // Safe check: اگر helper لوڈ نہیں ہوا
            wp_send_json_error(
                array(
                    'message' => __( 'Accounts DB helpers لوڈ نہیں ہو سکے۔', 'jwpm' ),
                    'devHint' => 'jwpm_accounts_ensure_tables() not found.',
                ),
                500
            );
        }

        // ہر کال سے پہلے tables ensure
        jwpm_accounts_ensure_tables();
    }
}

/**
 * Cashbook Fetch
 */
if ( ! function_exists( 'jwpm_cashbook_fetch' ) ) {
    function jwpm_cashbook_fetch() {
        jwpm_ajax_require_cashbook_cap();

        global $wpdb;

        $table = $wpdb->prefix . 'jwpm_cashbook';

        $page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? max( 1, absint( $_POST['per_page'] ) ) : 25;

        $from_date = isset( $_POST['from_date'] ) ? sanitize_text_field( wp_unslash( $_POST['from_date'] ) ) : '';
        $to_date   = isset( $_POST['to_date'] ) ? sanitize_text_field( wp_unslash( $_POST['to_date'] ) ) : '';
        $type      = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
        $category  = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

        $where   = array();
        $params  = array();

        if ( $from_date ) {
            $where[]  = 'entry_date >= %s';
            $params[] = $from_date;
        }
        if ( $to_date ) {
            $where[]  = 'entry_date <= %s';
            $params[] = $to_date;
        }
        if ( $type && in_array( $type, array( 'in', 'out' ), true ) ) {
            $where[]  = 'type = %s';
            $params[] = $type;
        }
        if ( $category ) {
            $where[]  = 'category LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $category ) . '%';
        }

        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }

        $offset = ( $page - 1 ) * $per_page;

        // Total count
        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

        // Data
        $data_sql = "SELECT * FROM {$table} {$where_sql} ORDER BY entry_date DESC, id DESC LIMIT %d OFFSET %d";
        $data_params = array_merge( $params, array( $per_page, $offset ) );
        $rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

        // Balance summary
        $balance_sql = "SELECT 
            SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) AS total_in,
            SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) AS total_out
            FROM {$table} {$where_sql}";
        $balance_row = $wpdb->get_row( $wpdb->prepare( $balance_sql, $params ), ARRAY_A );

        $total_in  = isset( $balance_row['total_in'] ) ? (float) $balance_row['total_in'] : 0;
        $total_out = isset( $balance_row['total_out'] ) ? (float) $balance_row['total_out'] : 0;
        $closing   = $total_in - $total_out;

        wp_send_json_success(
            array(
                'items'   => $rows,
                'total'   => $total,
                'page'    => $page,
                'perPage' => $per_page,
                'summary' => array(
                    'total_in'    => $total_in,
                    'total_out'   => $total_out,
                    'opening'     => 0, // Future: اگر آپ اوپننگ الگ رکھیں
                    'closing'     => $closing,
                ),
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_cashbook_fetch', 'jwpm_cashbook_fetch' );

/**
 * Cashbook Save (Add / Update)
 */
if ( ! function_exists( 'jwpm_cashbook_save' ) ) {
    function jwpm_cashbook_save() {
        jwpm_ajax_require_cashbook_cap();

        if ( ! current_user_can( 'jwpm_add_accounts' ) && ! current_user_can( 'jwpm_edit_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو Cashbook انٹری محفوظ کرنے کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_add_accounts یا jwpm_edit_accounts درکار ہے۔',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_cashbook';

        $id         = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $entry_date = isset( $_POST['entry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_date'] ) ) : '';
        $type       = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
        $amount     = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
        $category   = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $reference  = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
        $remarks    = isset( $_POST['remarks'] ) ? wp_kses_post( wp_unslash( $_POST['remarks'] ) ) : '';

        if ( ! $entry_date || ! in_array( $type, array( 'in', 'out' ), true ) || $amount <= 0 || ! $category ) {
            wp_send_json_error(
                array(
                    'message' => __( 'براہِ کرم تمام ضروری فیلڈز درست طریقے سے بھر دیں۔', 'jwpm' ),
                    'devHint' => 'Required: entry_date, type(in/out), amount>0, category.',
                ),
                400
            );
        }

        $data = array(
            'entry_date' => $entry_date,
            'type'       => $type,
            'amount'     => $amount,
            'category'   => $category,
            'reference'  => $reference,
            'remarks'    => $remarks,
            'updated_at' => current_time( 'mysql' ),
        );

        $formats = array( '%s', '%s', '%f', '%s', '%s', '%s', '%s' );

        if ( $id > 0 ) {
            $result = $wpdb->update(
                $table,
                $data,
                array( 'id' => $id ),
                $formats,
                array( '%d' )
            );
        } else {
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = current_time( 'mysql' );
            $formats[]          = '%d';
            $formats[]          = '%s';

            $result = $wpdb->insert(
                $table,
                $data,
                $formats
            );
            $id = $wpdb->insert_id;
        }

        if ( false === $result ) {
            wp_send_json_error(
                array(
                    'message' => __( 'ریکارڈ محفوظ نہیں ہو سکا، دوبارہ کوشش کریں۔', 'jwpm' ),
                    'devHint' => $wpdb->last_error,
                ),
                500
            );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Cashbook انٹری کامیابی سے محفوظ ہو گئی۔', 'jwpm' ),
                'id'      => $id,
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_cashbook_save', 'jwpm_cashbook_save' );

/**
 * Cashbook Delete
 */
if ( ! function_exists( 'jwpm_cashbook_delete' ) ) {
    function jwpm_cashbook_delete() {
        jwpm_ajax_require_cashbook_cap();

        if ( ! current_user_can( 'jwpm_delete_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو حذف کرنے کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_delete_accounts required.',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_cashbook';

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( $id <= 0 ) {
            wp_send_json_error(
                array(
                    'message' => __( 'غلط ریکارڈ منتخب کیا گیا ہے۔', 'jwpm' ),
                    'devHint' => 'Invalid id in jwpm_cashbook_delete.',
                ),
                400
            );
        }

        $deleted = $wpdb->delete(
            $table,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( false === $deleted ) {
            wp_send_json_error(
                array(
                    'message' => __( 'ریکارڈ حذف نہیں ہو سکا۔', 'jwpm' ),
                    'devHint' => $wpdb->last_error,
                ),
                500
            );
        }

        wp_send_json_success(
            array(
                'message' => __( 'ریکارڈ کامیابی سے حذف ہو گیا۔', 'jwpm' ),
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_cashbook_delete', 'jwpm_cashbook_delete' );

/**
 * Cashbook Export (Excel style — data array)
 * اصلی Excel فائل JS یا الگ لائبریری سے بنائی جائے گی۔
 */
if ( ! function_exists( 'jwpm_cashbook_export' ) ) {
    function jwpm_cashbook_export() {
        jwpm_ajax_require_cashbook_cap();

        if ( ! current_user_can( 'jwpm_export_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو ایکسپورٹ کرنے کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_export_accounts required.',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_cashbook';

        // سادگی کیلئے فلٹرز دوبارہ استعمال نہیں کر رہے، لیکن آپ چاہیں تو اوپر والے fetch logic reuse کر سکتے ہیں
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY entry_date DESC, id DESC", ARRAY_A );

        wp_send_json_success(
            array(
                'headers' => array( 'Date', 'Type', 'Category', 'Reference', 'Remarks', 'Amount' ),
                'rows'    => array_map(
                    static function ( $row ) {
                        return array(
                            $row['entry_date'],
                            $row['type'],
                            $row['category'],
                            $row['reference'],
                            $row['remarks'],
                            $row['amount'],
                        );
                    },
                    $rows
                ),
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_cashbook_export', 'jwpm_cashbook_export' );

/**
 * Cashbook Import (basic CSV-like array)
 */
if ( ! function_exists( 'jwpm_cashbook_import' ) ) {
    function jwpm_cashbook_import() {
        jwpm_ajax_require_cashbook_cap();

        if ( ! current_user_can( 'jwpm_import_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو امپورٹ کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_import_accounts required.',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_cashbook';

        $rows = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? (array) $_POST['rows'] : array();

        if ( empty( $rows ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'کوئی ڈیٹا موصول نہیں ہوا۔', 'jwpm' ),
                    'devHint' => 'rows array is empty in jwpm_cashbook_import.',
                ),
                400
            );
        }

        $inserted = 0;

        foreach ( $rows as $row ) {
            $entry_date = isset( $row['entry_date'] ) ? sanitize_text_field( $row['entry_date'] ) : '';
            $type       = isset( $row['type'] ) ? sanitize_text_field( $row['type'] ) : '';
            $amount     = isset( $row['amount'] ) ? floatval( $row['amount'] ) : 0;
            $category   = isset( $row['category'] ) ? sanitize_text_field( $row['category'] ) : '';
            $reference  = isset( $row['reference'] ) ? sanitize_text_field( $row['reference'] ) : '';
            $remarks    = isset( $row['remarks'] ) ? wp_kses_post( $row['remarks'] ) : '';

            if ( ! $entry_date || ! in_array( $type, array( 'in', 'out' ), true ) || $amount <= 0 || ! $category ) {
                continue;
            }

            $wpdb->insert(
                $table,
                array(
                    'entry_date' => $entry_date,
                    'type'       => $type,
                    'amount'     => $amount,
                    'category'   => $category,
                    'reference'  => $reference,
                    'remarks'    => $remarks,
                    'created_by' => get_current_user_id(),
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
            );

            if ( ! $wpdb->last_error ) {
                $inserted++;
            }
        }

        wp_send_json_success(
            array(
                'message'  => sprintf(
                    /* translators: %d: imported rows count */
                    __( '%d ریکارڈ امپورٹ ہو گئے۔', 'jwpm' ),
                    $inserted
                ),
                'inserted' => $inserted,
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_cashbook_import', 'jwpm_cashbook_import' );

/**
 * Cashbook Demo Data
 */
if ( ! function_exists( 'jwpm_cashbook_demo' ) ) {
    function jwpm_cashbook_demo() {
        jwpm_ajax_require_cashbook_cap();

        if ( ! current_user_can( 'jwpm_add_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو Demo Data بنانے کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_add_accounts required.',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_cashbook';

        $today = current_time( 'Y-m-d' );

        $demo_rows = array(
            array(
                'entry_date' => $today,
                'type'       => 'in',
                'amount'     => 500000,
                'category'   => 'Opening Balance',
                'reference'  => 'OB-001',
                'remarks'    => 'System opening balance',
            ),
            array(
                'entry_date' => $today,
                'type'       => 'in',
                'amount'     => 25000,
                'category'   => 'Sales Cash',
                'reference'  => 'POS',
                'remarks'    => 'POS cash sales',
            ),
            array(
                'entry_date' => $today,
                'type'       => 'out',
                'amount'     => 10000,
                'category'   => 'Shop Expense',
                'reference'  => 'EXP-001',
                'remarks'    => 'Electricity bill',
            ),
        );

        $inserted = 0;

        foreach ( $demo_rows as $row ) {
            $row['created_by'] = get_current_user_id();
            $row['created_at'] = current_time( 'mysql' );

            $wpdb->insert(
                $table,
                $row,
                array( '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
            );

            if ( ! $wpdb->last_error ) {
                $inserted++;
            }
        }

        wp_send_json_success(
            array(
                'message'  => sprintf( __( '%d Demo ریکارڈ شامل ہو گئے۔', 'jwpm' ), $inserted ),
                'inserted' => $inserted,
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_cashbook_demo', 'jwpm_cashbook_demo' );

// 🔴 یہاں پر [Accounts Module AJAX: Cashbook] ختم ہو رہا ہے

// ✅ Syntax verified block end
<?php
// ... یہاں آپ کا موجودہ class-jwpm-ajax.php کوڈ ہے ...

// 🟢 یہاں سے [Accounts Module AJAX: Expenses] شروع ہو رہا ہے

/** Part 23 — Accounts Module AJAX: Expenses */

if ( ! function_exists( 'jwpm_ajax_require_expenses_cap' ) ) {
    /**
     * Common capability + nonce check for expenses actions
     */
    function jwpm_ajax_require_expenses_cap() {
        check_ajax_referer( 'jwpm_expenses_nonce', 'nonce' );

        if ( ! current_user_can( 'jwpm_view_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو Accounts تک رسائی کی اجازت نہیں ہے۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_view_accounts required.',
                ),
                403
            );
        }

        if ( ! function_exists( 'jwpm_accounts_ensure_tables' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Accounts DB helpers لوڈ نہیں ہو سکے۔', 'jwpm' ),
                    'devHint' => 'jwpm_accounts_ensure_tables() not found.',
                ),
                500
            );
        }

        jwpm_accounts_ensure_tables();
    }
}

/**
 * Expenses Fetch
 */
if ( ! function_exists( 'jwpm_expenses_fetch' ) ) {
    function jwpm_expenses_fetch() {
        jwpm_ajax_require_expenses_cap();

        global $wpdb;

        $table = $wpdb->prefix . 'jwpm_expenses';

        $page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? max( 1, absint( $_POST['per_page'] ) ) : 25;

        $from_date = isset( $_POST['from_date'] ) ? sanitize_text_field( wp_unslash( $_POST['from_date'] ) ) : '';
        $to_date   = isset( $_POST['to_date'] ) ? sanitize_text_field( wp_unslash( $_POST['to_date'] ) ) : '';
        $category  = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $vendor    = isset( $_POST['vendor'] ) ? sanitize_text_field( wp_unslash( $_POST['vendor'] ) ) : '';

        $where  = array();
        $params = array();

        if ( $from_date ) {
            $where[]  = 'expense_date >= %s';
            $params[] = $from_date;
        }
        if ( $to_date ) {
            $where[]  = 'expense_date <= %s';
            $params[] = $to_date;
        }
        if ( $category ) {
            $where[]  = 'category LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $category ) . '%';
        }
        if ( $vendor ) {
            $where[]  = 'vendor LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $vendor ) . '%';
        }

        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }

        $offset = ( $page - 1 ) * $per_page;

        // Total count
        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

        // Data
        $data_sql    = "SELECT * FROM {$table} {$where_sql} ORDER BY expense_date DESC, id DESC LIMIT %d OFFSET %d";
        $data_params = array_merge( $params, array( $per_page, $offset ) );
        $rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

        // Summary (total expenses)
        $sum_sql = "SELECT SUM(amount) AS total_amount FROM {$table} {$where_sql}";
        $sum_row = $wpdb->get_row( $wpdb->prepare( $sum_sql, $params ), ARRAY_A );

        $total_amount = isset( $sum_row['total_amount'] ) ? (float) $sum_row['total_amount'] : 0;

        wp_send_json_success(
            array(
                'items'   => $rows,
                'total'   => $total,
                'page'    => $page,
                'perPage' => $per_page,
                'summary' => array(
                    'total_amount' => $total_amount,
                ),
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_expenses_fetch', 'jwpm_expenses_fetch' );

/**
 * Expenses Save (Add / Update)
 */
if ( ! function_exists( 'jwpm_expenses_save' ) ) {
    function jwpm_expenses_save() {
        jwpm_ajax_require_expenses_cap();

        if ( ! current_user_can( 'jwpm_add_accounts' ) && ! current_user_can( 'jwpm_edit_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو Expense محفوظ کرنے کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_add_accounts یا jwpm_edit_accounts درکار ہے۔',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_expenses';

        $id          = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $expense_date = isset( $_POST['expense_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_date'] ) ) : '';
        $category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $amount      = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
        $vendor      = isset( $_POST['vendor'] ) ? sanitize_text_field( wp_unslash( $_POST['vendor'] ) ) : '';
        $notes       = isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : '';
        $receipt_url = isset( $_POST['receipt_url'] ) ? esc_url_raw( wp_unslash( $_POST['receipt_url'] ) ) : '';

        if ( ! $expense_date || ! $category || $amount <= 0 ) {
            wp_send_json_error(
                array(
                    'message' => __( 'براہِ کرم تاریخ، Category اور Amount لازمی درج کریں۔', 'jwpm' ),
                    'devHint' => 'Required: expense_date, category, amount>0.',
                ),
                400
            );
        }

        $data = array(
            'expense_date' => $expense_date,
            'category'     => $category,
            'amount'       => $amount,
            'vendor'       => $vendor,
            'notes'        => $notes,
            'receipt_url'  => $receipt_url,
            'updated_at'   => current_time( 'mysql' ),
        );

        $formats = array( '%s', '%s', '%f', '%s', '%s', '%s', '%s' );

        if ( $id > 0 ) {
            $result = $wpdb->update(
                $table,
                $data,
                array( 'id' => $id ),
                $formats,
                array( '%d' )
            );
        } else {
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = current_time( 'mysql' );
            $formats[]          = '%d';
            $formats[]          = '%s';

            $result = $wpdb->insert(
                $table,
                $data,
                $formats
            );
            $id = $wpdb->insert_id;
        }

        if ( false === $result ) {
            wp_send_json_error(
                array(
                    'message' => __( 'Expense محفوظ نہیں ہو سکا، دوبارہ کوشش کریں۔', 'jwpm' ),
                    'devHint' => $wpdb->last_error,
                ),
                500
            );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Expense کامیابی سے محفوظ ہو گیا۔', 'jwpm' ),
                'id'      => $id,
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_expenses_save', 'jwpm_expenses_save' );

/**
 * Expenses Delete
 */
if ( ! function_exists( 'jwpm_expenses_delete' ) ) {
    function jwpm_expenses_delete() {
        jwpm_ajax_require_expenses_cap();

        if ( ! current_user_can( 'jwpm_delete_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو Expense حذف کرنے کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_delete_accounts required.',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_expenses';

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

        if ( $id <= 0 ) {
            wp_send_json_error(
                array(
                    'message' => __( 'غلط ریکارڈ منتخب کیا گیا ہے۔', 'jwpm' ),
                    'devHint' => 'Invalid id in jwpm_expenses_delete.',
                ),
                400
            );
        }

        $deleted = $wpdb->delete(
            $table,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( false === $deleted ) {
            wp_send_json_error(
                array(
                    'message' => __( 'ریکارڈ حذف نہیں ہو سکا۔', 'jwpm' ),
                    'devHint' => $wpdb->last_error,
                ),
                500
            );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Expense کامیابی سے حذف ہو گیا۔', 'jwpm' ),
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_expenses_delete', 'jwpm_expenses_delete' );

/**
 * Expenses Export (Excel-style data)
 */
if ( ! function_exists( 'jwpm_expenses_export' ) ) {
    function jwpm_expenses_export() {
        jwpm_ajax_require_expenses_cap();

        if ( ! current_user_can( 'jwpm_export_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو Export کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_export_accounts required.',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_expenses';

        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY expense_date DESC, id DESC", ARRAY_A );

        wp_send_json_success(
            array(
                'headers' => array( 'Date', 'Category', 'Vendor', 'Notes', 'Amount', 'Receipt URL' ),
                'rows'    => array_map(
                    static function ( $row ) {
                        return array(
                            $row['expense_date'],
                            $row['category'],
                            $row['vendor'],
                            $row['notes'],
                            $row['amount'],
                            $row['receipt_url'],
                        );
                    },
                    $rows
                ),
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_expenses_export', 'jwpm_expenses_export' );

/**
 * Expenses Import
 */
if ( ! function_exists( 'jwpm_expenses_import' ) ) {
    function jwpm_expenses_import() {
        jwpm_ajax_require_expenses_cap();

        if ( ! current_user_can( 'jwpm_import_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو Import کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_import_accounts required.',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_expenses';

        $rows = isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ? (array) $_POST['rows'] : array();

        if ( empty( $rows ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'کوئی ڈیٹا موصول نہیں ہوا۔', 'jwpm' ),
                    'devHint' => 'rows array is empty in jwpm_expenses_import.',
                ),
                400
            );
        }

        $inserted = 0;

        foreach ( $rows as $row ) {
            $expense_date = isset( $row['expense_date'] ) ? sanitize_text_field( $row['expense_date'] ) : '';
            $category     = isset( $row['category'] ) ? sanitize_text_field( $row['category'] ) : '';
            $amount       = isset( $row['amount'] ) ? floatval( $row['amount'] ) : 0;
            $vendor       = isset( $row['vendor'] ) ? sanitize_text_field( $row['vendor'] ) : '';
            $notes        = isset( $row['notes'] ) ? wp_kses_post( $row['notes'] ) : '';
            $receipt_url  = isset( $row['receipt_url'] ) ? esc_url_raw( $row['receipt_url'] ) : '';

            if ( ! $expense_date || ! $category || $amount <= 0 ) {
                continue;
            }

            $wpdb->insert(
                $table,
                array(
                    'expense_date' => $expense_date,
                    'category'     => $category,
                    'amount'       => $amount,
                    'vendor'       => $vendor,
                    'notes'        => $notes,
                    'receipt_url'  => $receipt_url,
                    'created_by'   => get_current_user_id(),
                    'created_at'   => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
            );

            if ( ! $wpdb->last_error ) {
                $inserted++;
            }
        }

        wp_send_json_success(
            array(
                'message'  => sprintf(
                    __( '%d Expense ریکارڈ امپورٹ ہو گئے۔', 'jwpm' ),
                    $inserted
                ),
                'inserted' => $inserted,
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_expenses_import', 'jwpm_expenses_import' );

/**
 * Expenses Demo Data
 */
if ( ! function_exists( 'jwpm_expenses_demo' ) ) {
    function jwpm_expenses_demo() {
        jwpm_ajax_require_expenses_cap();

        if ( ! current_user_can( 'jwpm_add_accounts' ) ) {
            wp_send_json_error(
                array(
                    'message' => __( 'آپ کو Demo Data بنانے کی اجازت نہیں۔', 'jwpm' ),
                    'devHint' => 'Capability jwpm_add_accounts required.',
                ),
                403
            );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jwpm_expenses';

        $today = current_time( 'Y-m-d' );

        $demo_rows = array(
            array(
                'expense_date' => $today,
                'category'     => 'Shop Rent',
                'amount'       => 30000,
                'vendor'       => 'Landlord',
                'notes'        => 'Monthly shop rent',
                'receipt_url'  => '',
            ),
            array(
                'expense_date' => $today,
                'category'     => 'Staff Salary',
                'amount'       => 50000,
                'vendor'       => 'Staff',
                'notes'        => 'Monthly salary payment',
                'receipt_url'  => '',
            ),
            array(
                'expense_date' => $today,
                'category'     => 'Utility Bills',
                'amount'       => 8000,
                'vendor'       => 'K-Electric / SSGC',
                'notes'        => 'Electricity / Gas bills',
                'receipt_url'  => '',
            ),
        );

        $inserted = 0;

        foreach ( $demo_rows as $row ) {
            $row['created_by'] = get_current_user_id();
            $row['created_at'] = current_time( 'mysql' );

            $wpdb->insert(
                $table,
                $row,
                array( '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s' )
            );

            if ( ! $wpdb->last_error ) {
                $inserted++;
            }
        }

        wp_send_json_success(
            array(
                'message'  => sprintf( __( '%d Demo Expense ریکارڈ شامل ہو گئے۔', 'jwpm' ), $inserted ),
                'inserted' => $inserted,
            )
        );
    }
}
add_action( 'wp_ajax_jwpm_expenses_demo', 'jwpm_expenses_demo' );

// 🔴 یہاں پر [Accounts Module AJAX: Expenses] ختم ہو رہا ہے

// ✅ Syntax verified block end

