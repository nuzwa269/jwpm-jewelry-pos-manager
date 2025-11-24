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
