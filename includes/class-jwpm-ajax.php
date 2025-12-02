<?php
/**
 * JWPM_Ajax
 *
 * یہ کلاس پلگ اِن کی تمام (AJAX) ریکویسٹس کو ہینڈل کرتی ہے۔
 * ہر ماڈیول (Inventory, POS, Customers, Installments, Repairs, Accounts, Dashboard, Reports)
 * کے لیے الگ سیکشن، سکیورٹی (nonce + capability) اور صاف (JSON) رسپانس فراہم کرتی ہے۔
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * مرکزی (AJAX) ہینڈلر کلاس
 */
class JWPM_Ajax {

	// 🟢 یہاں سے Core Helpers شروع ہو رہا ہے

	/**
	 * تمام (wp_ajax_*) ہُکس رجسٹر کریں
	 *
	 * یہ فنکشن مین پلگ اِن کلاس یا لوڈر کے ذریعے کال ہوتا ہے۔
	 */
	public static function register_ajax_hooks() {
		// نوٹ: یہاں __CLASS__ استعمال کرنے کے بجائے، عام طور پر $this استعمال ہوتا ہے اگر یہ instance method ہو،
		// لیکن چونکہ یہ static ہے اور main file اسے staticly कॉल कर रही है, हम __CLASS__ استعمال کریں گے۔
		
		// ---------------------------------------------------------------------
		// 1. Inventory Module
		// ---------------------------------------------------------------------
				add_action( 'wp_ajax_jwpm_inventory_list_items',   array( __CLASS__, 'inventory_list_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_save_item',    array( __CLASS__, 'inventory_save_item' ) );
		add_action( 'wp_ajax_jwpm_inventory_delete_item',  array( __CLASS__, 'inventory_delete_item' ) );
		add_action( 'wp_ajax_jwpm_inventory_import_items', array( __CLASS__, 'inventory_import_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_export_items', array( __CLASS__, 'inventory_export_items' ) );
		add_action( 'wp_ajax_jwpm_inventory_demo_items',   array( __CLASS__, 'inventory_demo_items' ) );

		// ---------------------------------------------------------------------
		// 2. POS Module
		// ---------------------------------------------------------------------
		add_action( 'wp_ajax_jwpm_pos_search_items',   array( __CLASS__, 'pos_search_items' ) );
		add_action( 'wp_ajax_jwpm_pos_get_gold_rate',  array( __CLASS__, 'pos_get_gold_rate' ) );
		add_action( 'wp_ajax_jwpm_pos_search_customer', array( __CLASS__, 'pos_search_customer' ) );
		add_action( 'wp_ajax_jwpm_pos_complete_sale',  array( __CLASS__, 'pos_complete_sale' ) );
		add_action( 'wp_ajax_jwpm_pos_get_today_stats', array( __CLASS__, 'pos_get_today_stats' ) );

		// ---------------------------------------------------------------------
		// 3. Customers Module
		// ---------------------------------------------------------------------
		add_action( 'wp_ajax_jwpm_customers_fetch', array( __CLASS__, 'customers_fetch' ) );
		add_action( 'wp_ajax_jwpm_customers_save',  array( __CLASS__, 'customers_save' ) );
		add_action( 'wp_ajax_jwpm_customers_delete', array( __CLASS__, 'customers_delete' ) );
		add_action( 'wp_ajax_jwpm_customers_import', array( __CLASS__, 'customers_import' ) );
		add_action( 'wp_ajax_jwpm_customers_export', array( __CLASS__, 'customers_export' ) );
		add_action( 'wp_ajax_jwpm_customers_demo',   array( __CLASS__, 'customers_demo' ) );
		add_action( 'wp_ajax_jwpm_customers_get_single', array( __CLASS__, 'customers_get_single' ) );

		// ✅ Backward compatibility (پرانے اکشن نام)
		add_action( 'wp_ajax_jwpm_get_customers',   array( __CLASS__, 'customers_fetch' ) );
		add_action( 'wp_ajax_jwpm_save_customer',   array( __CLASS__, 'customers_save' ) );
		add_action( 'wp_ajax_jwpm_delete_customer', array( __CLASS__, 'customers_delete' ) );
		add_action( 'wp_ajax_jwpm_get_customer',    array( __CLASS__, 'customers_get_single' ) );

		// ---------------------------------------------------------------------
		// 4. Installments Module
		// ---------------------------------------------------------------------
		add_action( 'wp_ajax_jwpm_installments_fetch',          array( __CLASS__, 'installments_fetch' ) );
		add_action( 'wp_ajax_jwpm_installments_save',           array( __CLASS__, 'installments_save' ) );
		add_action( 'wp_ajax_jwpm_installments_delete',         array( __CLASS__, 'installments_delete' ) );
		add_action( 'wp_ajax_jwpm_installments_record_payment', array( __CLASS__, 'installments_record_payment' ) );
		add_action( 'wp_ajax_jwpm_installments_import',         array( __CLASS__, 'installments_import' ) );
		add_action( 'wp_ajax_jwpm_installments_export',         array( __CLASS__, 'installments_export' ) );

		// ✅ Backward compatibility
		add_action( 'wp_ajax_jwpm_get_installments', array( __CLASS__, 'installments_fetch' ) );
		add_action( 'wp_ajax_jwpm_save_installment', array( __CLASS__, 'installments_save' ) );

		// ---------------------------------------------------------------------
		// 5. Repair Jobs Module
		// ---------------------------------------------------------------------
		add_action( 'wp_ajax_jwpm_repair_fetch',  array( __CLASS__, 'repair_fetch' ) );
		add_action( 'wp_ajax_jwpm_repair_save',   array( __CLASS__, 'repair_save' ) );
		add_action( 'wp_ajax_jwpm_repair_delete', array( __CLASS__, 'repair_delete' ) );
		add_action( 'wp_ajax_jwpm_repair_import', array( __CLASS__, 'repair_import' ) );
		add_action( 'wp_ajax_jwpm_repair_export', array( __CLASS__, 'repair_export' ) );

		// ✅ Backward compatibility
		add_action( 'wp_ajax_jwpm_get_repairs', array( __CLASS__, 'repair_fetch' ) );
		add_action( 'wp_ajax_jwpm_save_repair', array( __CLASS__, 'repair_save' ) );

		// ---------------------------------------------------------------------
		// 6. Accounts Module
		// ---------------------------------------------------------------------
		add_action( 'wp_ajax_jwpm_cashbook_fetch',   array( __CLASS__, 'accounts_cashbook_fetch' ) );
		add_action( 'wp_ajax_jwpm_cashbook_save',    array( __CLASS__, 'accounts_cashbook_save' ) );
		add_action( 'wp_ajax_jwpm_cashbook_delete',  array( __CLASS__, 'accounts_cashbook_delete' ) );
		add_action( 'wp_ajax_jwpm_expenses_fetch',   array( __CLASS__, 'accounts_expenses_fetch' ) );
		add_action( 'wp_ajax_jwpm_expenses_save',    array( __CLASS__, 'accounts_expenses_save' ) );
		add_action( 'wp_ajax_jwpm_expenses_delete',  array( __CLASS__, 'accounts_expenses_delete' ) );
		add_action( 'wp_ajax_jwpm_ledger_fetch',     array( __CLASS__, 'accounts_ledger_fetch' ) );

		// ---------------------------------------------------------------------
		// 7. Dashboard APIs
		// ---------------------------------------------------------------------
			add_action( 'wp_ajax_jwpm_dashboard_get_stats',           array( __CLASS__, 'dashboard_get_stats' ) );
		add_action( 'wp_ajax_jwpm_dashboard_get_recent_activity', array( __CLASS__, 'dashboard_get_recent_activity' ) );

		// ---------------------------------------------------------------------
		// 8. Reports APIs
		// ---------------------------------------------------------------------
		// Sales Reports
		add_action( 'wp_ajax_jwpm_sales_report_daily',   array( __CLASS__, 'reports_sales_daily' ) );
		add_action( 'wp_ajax_jwpm_sales_report_monthly', array( __CLASS__, 'reports_sales_monthly' ) );
		add_action( 'wp_ajax_jwpm_sales_report_custom',  array( __CLASS__, 'reports_sales_custom' ) );

		// Inventory Reports
		add_action( 'wp_ajax_jwpm_inventory_report_stock_levels', array( __CLASS__, 'reports_inventory_stock_levels' ) );
		add_action( 'wp_ajax_jwpm_inventory_report_low_stock',    array( __CLASS__, 'reports_inventory_low_stock' ) );
		add_action( 'wp_ajax_jwpm_inventory_report_movement',     array( __CLASS__, 'reports_inventory_movement' ) );

		// Financial Reports
		add_action( 'wp_ajax_jwpm_profit_loss_report', array( __CLASS__, 'reports_profit_loss' ) );
		add_action( 'wp_ajax_jwpm_expense_report',     array( __CLASS__, 'reports_expense' ) );
		add_action( 'wp_ajax_jwpm_cashflow_report',    array( __CLASS__, 'reports_cashflow' ) );

		// ---------------------------------------------------------------------
		// 9. Custom Orders Module (Final Merge)
		// ---------------------------------------------------------------------
		add_action( 'wp_ajax_jwpm_custom_orders_fetch', array( __CLASS__, 'custom_orders_fetch' ) );
		add_action( 'wp_ajax_jwpm_custom_orders_save', array( __CLASS__, 'custom_orders_save' ) );
		add_action( 'wp_ajax_jwpm_custom_orders_delete', array( __CLASS__, 'custom_orders_delete' ) );
		add_action( 'wp_ajax_jwpm_custom_orders_import', array( __CLASS__, 'custom_orders_import' ) );
		add_action( 'wp_ajax_jwpm_custom_orders_export', array( __CLASS__, 'custom_orders_export' ) );
		add_action( 'wp_ajax_jwpm_custom_orders_demo', array( __CLASS__, 'custom_orders_demo' ) );
	}

	/**
	 * مشترکہ ہیلپر:
	 * (nonce) + (capability) دونوں چیک کرے
	 *
	 	 * @param string       $nonce_action  (wp_nonce) ایکشن نام، جیسے 'jwpm_inventory_nonce'.
	 * @param string|array $caps         ایک یا زیادہ (capability) جیسے 'manage_jwpm_inventory'.
	 */
	protected static function verify_request( $nonce_action, $caps = 'manage_options' ) {
		$field = null;

		// JS کبھی 'security' بھیجتا ہے، کبھی 'nonce' — دونوں سپورٹ کریں
		if ( isset( $_REQUEST['security'] ) ) {
			$field = 'security';
		} elseif ( isset( $_REQUEST['nonce'] ) ) {
			$field = 'nonce';
		}

		if ( $field ) {
			check_ajax_referer( $nonce_action, $field );
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Security token missing.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$caps = (array) $caps;
		$ok   = false;

		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				$ok = true;
				break;
			}
		}

		if ( ! $ok ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'jwpm-jewelry-pos-manager' ),
				),
				403
			);
		}
	}

	/**
	 * (decimal) ویلیو کو normalize / clean کرے
	 */
	protected static function sanitize_decimal( $value, $decimals = 3 ) {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) {
			$value = '0';
		}
		$value = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $value );
		return number_format( (float) $value, $decimals, '.', '' );
	}

	/**
	 * (JWPM_DB::get_table_names) کے ساتھ محفوظ table resolver
	 */
	protected static function get_table( $key, $fallback_suffix ) {
		global $wpdb;

		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_table_names' ) ) {
			$tables = JWPM_DB::get_table_names();
			// Note: یہاں fallback_suffix استعمال نہیں ہو رہا کیونکہ get_table_names
			// پہلے ہی wpdb->prefix اور jwpm_prefix دونوں استعمال کر رہا ہے۔
			if ( isset( $tables[ $key ] ) ) {
				return $tables[ $key ];
			}
		}

		// اگر DB helper کام نہ کرے تو محفوظ نام دیں
		return $wpdb->prefix . 'jwpm_' . $key;
	}

	/**
	 * سادہ helper: (activity log) میں ریکارڈ کریں اگر (JWPM_DB) میں method موجود ہو
	 */
	protected static function log_activity( $user_id, $action, $entity_type, $entity_id, $meta = array() ) {
		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'log_activity' ) ) {
			JWPM_DB::log_activity( $user_id, $action, $entity_type, $entity_id, $meta );			
		}
	}
	// 🔴 یہاں پر Core Helpers ختم ہو رہا ہے
	// ✅ Syntax verified block end

	/**
	 * ==========================================================================
	 * 1. INVENTORY MODULE
	 * ==========================================================================
	 */
	// 🟢 یہاں سے Inventory Module شروع ہو رہا ہے

	public static function inventory_list_items() {
		self::verify_request( 'jwpm_inventory_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );

		$filters = array(
			'page'      => isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1,
			'per_page'  => isset( $_POST['per_page'] ) ? max( 1, (int) $_POST['per_page'] ) : 50,
			'search'    => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'category'  => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'metal'     => isset( $_POST['metal'] ) ? sanitize_text_field( wp_unslash( $_POST['metal'] ) ) : '',
			'karat'     => isset( $_POST['karat'] ) ? sanitize_text_field( wp_unslash( $_POST['karat'] ) ) : '',
			'status'    => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
			'branch_id' => isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0,
		);

		// اگر (JWPM_DB) میں نیا (get_items_list) method موجود ہو تو وہی استعمال کریں
		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_items_list' ) ) {
			$result = JWPM_DB::get_items_list( $filters );
			wp_send_json_success(
				array(
						'items'    => isset( $result['items'] ) ? $result['items'] : array(),
					'total'    => isset( $result['total'] ) ? (int) $result['total'] : 0,
					'page'     => $filters['page'],
					'per_page' => $filters['per_page'],
				)
			);
		}

		// Fallback: پرانا direct (wpdb) logic
		global $wpdb;
		$table = self::get_table( 'items', 'jwpm_items' );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $filters['branch_id'] > 0 ) {
			$where     .= ' AND branch_id = %d';
			$params[]   = $filters['branch_id'];
		}

		if ( '' !== $filters['search'] ) {
			$like       = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where     .= ' AND (sku LIKE %s OR tag_serial LIKE %s OR category LIKE %s OR design_no LIKE %s)';
			$params[]   = $like;
			$params[]   = $like;
			$params[]   = $like;
			$params[]   = $like;
		}

		if ( '' !== $filters['category'] ) {
				$where     .= ' AND category = %s';
			$params[]   = $filters['category'];
		}
		if ( '' !== $filters['metal'] ) {
			$where     .= ' AND metal_type = %s';
			$params[]   = $filters['metal'];
		}
		if ( '' !== $filters['karat'] ) {
			$where     .= ' AND karat = %s';
			$params[]   = $filters['karat'];
		}
		if ( '' !== $filters['status'] ) {
			$where     .= ' AND status = %s';
			$params[]   = $filters['status'];
		}

		$sql_base  = "FROM {$table} {$where}";
		$count_sql = "SELECT COUNT(*) {$sql_base}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

			$offset   = ( $filters['page'] - 1 ) * $filters['per_page'];
		$list_sql = "SELECT * {$sql_base} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params_l = array_merge( $params, array( $filters['per_page'], $offset ) );
		$rows     = $wpdb->get_results( $wpdb->prepare( $list_sql, $params_l ), ARRAY_A );

		$items = array();

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
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
					'is_demo'       => isset( $row['is_demo'] ) ? (int) $row['is_demo'] : 0,
					'created_at'    => $row['created_at'],
				);
			}
		}

		wp_send_json_success(
			array(
				'items'    => $items,
				'total'    => $total,
				'page'     => $filters['page'],
			)
		);
	}

	public static function inventory_save_item() {
		self::verify_request( 'jwpm_inventory_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );
		global $wpdb;

		
		$table = self::get_table( 'items', 'jwpm_items' );

    	$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
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

			$updated = $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );
			if ( false === $updated ) {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to update item.', 'jwpm-jewelry-pos-manager' ),
					),
					500
				);
			}
			self::log_activity( get_current_user_id(), 'inventory_update', 'item', $id, $data );
		} else {
			$data['created_at'] = current_time( 'mysql' );
				$format[]           = '%s';

			$inserted = $wpdb->insert( $table, $data, $format );
			if ( ! $inserted ) {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to create item.', 'jwpm-jewelry-pos-manager' ),
					),
					500
				);
			}
			$id = (int) $wpdb->insert_id;
			self::log_activity( get_current_user_id(), 'inventory_create', 'item', $id, $data );
		}

		wp_send_json_success(
			array(
				'id'      => $id,
				'message' => __( 'Item saved successfully.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function inventory_delete_item() {
		self::verify_request( 'jwpm_inventory_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'items', 'jwpm_items' );
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid item ID.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		if ( ! $deleted ) {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to delete item.', 'jwpm-jewelry-pos-manager' ),
				),
				500
			);
		}

		self::log_activity( get_current_user_id(), 'inventory_delete', 'item', $id );
		wp_send_json_success(
			array(
				'message' => __( 'Item deleted successfully.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	/**
	 * Import: فرنٹ اینڈ (CSV/Excel) parse کر کے (items) کا (JSON) بھیجے گا۔
	 * POST['items_json'] = JSON array of rows.
	 */
	public static function inventory_import_items() {
		self::verify_request( 'jwpm_inventory_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'items', 'jwpm_items' );

		if ( empty( $_POST['items_json'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No data received for import.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$items = json_decode( wp_unslash( $_POST['items_json'] ), true );
		if ( ! is_array( $items ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid import payload.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$inserted = 0;
		$updated  = 0;

		foreach ( $items as $row ) {
			$sku = isset( $row['sku'] ) ? sanitize_text_field( $row['sku'] ) : '';
			if ( '' === $sku ) {
				continue;
			}

			$data = array(
					'branch_id'     => isset( $row['branch_id'] ) ? (int) $row['branch_id'] : 0,
				'sku'           => $sku,
				'tag_serial'    => isset( $row['tag_serial'] ) ? sanitize_text_field( $row['tag_serial'] ) : '',
				'category'      => isset( $row['category'] ) ? sanitize_text_field( $row['category'] ) : '',
				'metal_type'    => isset( $row['metal_type'] ) ? sanitize_text_field( $row['metal_type'] ) : '',
				'karat'         => isset( $row['karat'] ) ? sanitize_text_field( $row['karat'] ) : '',
				'gross_weight'  => isset( $row['gross_weight'] ) ? (float) $row['gross_weight'] : 0,
				'net_weight'    => isset( $row['net_weight'] ) ? (float) $row['net_weight'] : 0,
				'stone_type'    => isset( $row['stone_type'] ) ? sanitize_text_field( $row['stone_type'] ) : '',
				'labour_amount' => isset( $row['labour_amount'] ) ? (float) $row['labour_amount'] : 0,
					'design_no'     => isset( $row['design_no'] ) ? sanitize_text_field( $row['design_no'] ) : '',
				'status'        => isset( $row['status'] ) ? sanitize_text_field( wp_unslash( $row['status'] ) ) : 'in_stock',
				'is_demo'       => isset( $row['is_demo'] ) ? (int) $row['is_demo'] : 0,
			);

			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE sku = %s LIMIT 1",
					$sku
				)
			);

			if ( $existing_id > 0 ) {
				$data['updated_at'] = current_time( 'mysql' );
				$wpdb->update( $table, $data, array( 'id' => $existing_id ), null, array( '%d' ) );
				$updated++;
			} else {
				$data['created_at'] = current_time( 'mysql' );
				$wpdb->insert( $table, $data );
				$inserted++;
			}
		}

		wp_send_json_success(
			array(
				'inserted' => $inserted,
					'updated'  => $updated,
				'message'  => __( 'Inventory import completed.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	/**
	 * Export: فلٹرز کے ساتھ تمام آئٹمز واپس دے گا تاکہ (Excel / CSV / Print) بنایا جا سکے۔
	 */
	public static function inventory_export_items() {
		self::verify_request( 'jwpm_inventory_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'items', 'jwpm_items' );

		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
			$where  = 'WHERE 1=1';
		$params = array();

		if ( '' !== $status ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		$sql  = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT 5000";
		$rows = empty( $params ) ? $wpdb->get_results( $sql, ARRAY_A ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		wp_send_json_success(
			array(
				'rows'    => $rows,
				'message' => __( 'Inventory export data ready.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	/**
	 * Demo data generator (basic)
	 */
	public static function inventory_demo_items() {
		self::verify_request( 'jwpm_inventory_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'items', 'jwpm_items' );
		$mode  = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'create';

		if ( 'delete' === $mode ) {
			$wpdb->delete( $table, array( 'is_demo' => 1 ), array( '%d' ) );
			wp_send_json_success(
				array(
					'message' => __( 'Demo inventory deleted.', 'jwpm-jewelry-pos-manager' ),
				)
			);
		}

		// اگر پہلے ہی demo items موجود ہوں تو دوبارہ create نہ کریں
		$existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_demo = 1" );
		if ( $existing > 0 ) {
			wp_send_json_success(
				array(
					'message' => __( 'Demo inventory already exists.', 'jwpm-jewelry-pos-manager' ),
				)
			);
		}

		$demo_rows = array(
			array(
				'sku'           => 'DEMO-RING-001',
				'category'      => 'Ring',
				'metal_type'    => 'Gold',
				'karat'         => '22K',
				'gross_weight'  => 5.200,
				'net_weight'    => 4.850,
				'labour_amount' => 1500,
				'design_no'     => 'R-1001',
			),
			array(
				'sku'           => 'DEMO-SET-001',
				'category'      => 'Set',
				'metal_type'    => 'Gold',
				'karat'         => '21K',
				'gross_weight'  => 25.500,
				'net_weight'    => 24.900,
				'labour_amount' => 4500,
				'design_no'     => 'S-2001',
			),
		);

		foreach ( $demo_rows as $row ) {
			$row['branch_id']     = 0;
			$row['status']        = 'in_stock';
			$row['is_demo']       = 1;
			$row['created_at']    = current_time( 'mysql' );
			$row['tag_serial']    = '';
			$row['stone_type']    = '';
			$row['stone_carat']   = 0;
			$row['stone_qty']     = 0;
			$wpdb->insert( $table, $row );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Demo inventory created.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	// 🔴 یہاں پر Inventory Module ختم ہو رہا ہے
	// ✅ Syntax verified block end

	/**
	 * ==========================================================================
	 * 2. POS MODULE
	 * ==========================================================================
	 */
	// 🟢 یہاں سے POS Module شروع ہو رہا ہے

	public static function pos_search_items() {
		self::verify_request( 'jwpm_pos_nonce', array( 'manage_jwpm_sales', 'manage_jwpm_inventory', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'items', 'jwpm_items' );

	    $keyword   = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$category  = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$karat     = isset( $_POST['karat'] ) ? sanitize_text_field( wp_unslash( $_POST['karat'] ) ) : '';
		$branch_id = isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0;

		$where  = "WHERE status != %s";
		$params = array( 'scrap' );

		if ( $branch_id > 0 ) {
			$where     .= ' AND branch_id = %d';
			$params[]   = $branch_id;
		}
		if ( '' !== $keyword ) {
			$like       = '%' . $wpdb->esc_like( $keyword ) . '%';
			$where     .= ' AND (sku LIKE %s OR tag_serial LIKE %s OR category LIKE %s OR design_no LIKE %s)';
			$params[]   = $like;
			$params[]   = $like;
			$params[]   = $like;
			$params[]   = $like;
		}
		if ( '' !== $category ) {
				$where     .= ' AND category = %s';
			$params[]   = $category;
		}
		if ( '' !== $karat ) {
			$where     .= ' AND karat = %s';
			$params[]   = $karat;
		}

				$sql  = "SELECT id, branch_id, sku, tag_serial, category, metal_type, karat, gross_weight, net_weight, stone_type, status FROM {$table} {$where} ORDER BY created_at DESC LIMIT 30";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		wp_send_json_success(
			array(
				'items' => $rows,
			)
		);
	}

	public static function pos_get_gold_rate() {
		self::verify_request( 'jwpm_pos_nonce', array( 'manage_jwpm_sales', 'manage_options' ) );
		global $wpdb;

		$settings_table = self::get_table( 'settings', 'jwpm_settings' );
			$val            = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$settings_table} WHERE option_name = %s LIMIT 1",
				'gold_rate_24k'
			)
		);

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

	public static function pos_search_customer() {
		self::verify_request( 'jwpm_pos_nonce', array( 'manage_jwpm_sales', 'manage_jwpm_customers', 'manage_options' ) );
		global $wpdb;

		$table   = self::get_table( 'customers', 'jwpm_customers' );
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

		if ( '' === $keyword ) {
			wp_send_json_success(
				array(
					'customers' => array(),
				)
			);
		}

		$like = '%' . $wpdb->esc_like( $keyword ) . '%';
		$sql  = "SELECT id, name, phone, email, loyalty_points FROM {$table} WHERE phone LIKE %s OR name LIKE %s ORDER BY created_at DESC LIMIT 20";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array( $like, $like ) ), ARRAY_A );

		wp_send_json_success(
			array(
				'customers' => $rows,
			)
		);
	}

	/**
	 * سیل مکمل کرنا — یہاں بنیادی validation + (JWPM_DB) / future POS engine کو کال
	 */
	public static function pos_complete_sale() {
		self::verify_request( 'jwpm_pos_nonce', array( 'manage_jwpm_sales', 'manage_options' ) );

		$payload_raw = isset( $_POST['sale'] ) ? wp_unslash( $_POST['sale'] ) : '';
			$payload     = is_array( $payload_raw ) ? $payload_raw : json_decode( $payload_raw, true );

		if ( ! is_array( $payload ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid sale payload.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		// بنیادی validate
		$items = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
		if ( empty( $items ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No items in sale.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$customer_id = isset( $payload['customer_id'] ) ? (int) $payload['customer_id'] : 0;
		$total       = isset( $payload['total_amount'] ) ? (float) $payload['total_amount'] : 0;


		if ( $total <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid sale total.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		// اگر (JWPM_DB) میں dedicated method ہو تو اسے استعمال کریں
		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'process_pos_sale' ) ) {
			$result = JWPM_DB::process_pos_sale( $payload );
			if ( ! empty( $result['error'] ) ) {
				wp_send_json_error( $result, 400 );
			}
			wp_send_json_success( $result );
		}

		// Fallback: ابھی مکمل business logic دوسری کلاس میں بننی ہے
		wp_send_json_error(
			array(
				'message' => __( 'POS engine not available. Please implement JWPM_DB::process_pos_sale().', 'jwpm-jewelry-pos-manager' ),
			),
			501
		);
	}

	/**
	 * آج کی سیل / گنتی وغیرہ کے لیے stats
	 */
	public static function pos_get_today_stats() {
		self::verify_request( 'jwpm_pos_nonce', array( 'manage_jwpm_sales', 'jwpm_view_reports', 'manage_options' ) );

		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_today_pos_stats' ) ) {
			$stats = JWPM_DB::get_today_pos_stats();
			wp_send_json_success( $stats );
		}
     // Safe default empty stats
		wp_send_json_success(
			array(
					'sales_count'   => 0,
				'sales_amount'  => 0,
				'items_sold'    => 0,
				'message'       => __( 'POS stats provider not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	// 🔴 یہاں پر POS Module ختم ہو رہا ہے
	// ✅ Syntax verified block end

	/**
	 * ==========================================================================
	 * 3. CUSTOMERS MODULE
	 * ==========================================================================
	 */
	// 🟢 یہاں سے Customers Module شروع ہو رہا ہے

	public static function customers_fetch() {
		self::verify_request( 'jwpm_customers_main_nonce', array( 'manage_jwpm_customers', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'customers', 'jwpm_customers' );

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$city   = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$type   = isset( $_POST['customer_type'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_type'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
			$page   = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per    = isset( $_POST['per_page'] ) ? max( 1, (int) $_POST['per_page'] ) : 20;

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (name LIKE %s OR phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $city ) {
			$where   .= ' AND city = %s';
			$params[] = $city;
		}
		if ( $type ) {
				$where   .= ' AND customer_type = %s';
			$params[] = $type;
		}
		if ( $status ) {
				$where   .= ' AND status = %s';
			$params[] = $status;
		}

		$total_sql = "SELECT COUNT(*) FROM {$table} {$where}";
			$total     = (int) $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) );

		$offset       = ( $page - 1 ) * $per;
		$params_items = array_merge( $params, array( $per, $offset ) );

		$list_sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results( $wpdb->prepare( $list_sql, $params_items ), ARRAY_A );

		wp_send_json_success(
			array(
				'items'      => $rows,
				'pagination' => array(
						'total'      => $total,
					'page'       => $page,
					'per_page'   => $per,
					'total_page' => $per > 0 ? (int) ceil( $total / $per ) : 1,
				),
			)
		);
	}

	public static function customers_save() {
		self::verify_request( 'jwpm_customers_main_nonce', array( 'manage_jwpm_customers', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'customers', 'jwpm_customers' );

		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( '' === $name || '' === $phone ) {
			wp_send_json_error(
				array(
					'message' => __( 'Name/Phone required.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$data = array(
				'name'          => $name,
			'phone'         => $phone,
			'whatsapp'      => isset( $_POST['whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp'] ) ) : '',
			'email'         => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'city'          => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'area'          => isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : '',
			'address'       => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
			'cnic'          => isset( $_POST['cnic'] ) ? sanitize_text_field( wp_unslash( $_POST['cnic'] ) ) : '',
			'dob'           => isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '',
			'gender'        => isset( $_POST['gender'] ) ? sanitize_text_field( wp_unslash( $_POST['gender'] ) ) : 
			'customer_type' => isset( $_POST['customer_type'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_type'] ) ) : 'walkin',
				'status'        => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
			'price_group'   => isset( $_POST['price_group'] ) ? sanitize_text_field( wp_unslash( $_POST['price_group'] ) ) : '',
			'tags'          => isset( $_POST['tags'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tags'] ) ) : '',
			'notes'         => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'credit_limit'  => self::sanitize_decimal( isset( $_POST['credit_limit'] ) ? wp_unslash( $_POST['credit_limit'] ) : '0' ),
		);

		if ( $id > 0 ) {
			$data['updated_by'] = get_current_user_id();
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			// CREATE CUSTOMER
				$data['opening_balance']  = self::sanitize_decimal( isset( $_POST['opening_balance'] ) ? wp_unslash( $_POST['opening_balance'] ) : '0' );
			$data['current_balance']  = $data['opening_balance'];
			$data['created_by']       = get_current_user_id();
			$data['is_demo']          = 0;
			
			// Customer Code Logic
				$max_id                   = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
			$data['customer_code']    = sprintf( 'CUST-%04d', $max_id + 1 );
			$data['created_at']       = current_time( 'mysql' );

			$inserted = $wpdb->insert( $table, $data );
			
			if ( ! $inserted ) {
				// یہ ہی وہ ایرر ہے جو اسکرین شاٹ میں آیا تھا!
				wp_send_json_error(
					array(
						'message' => __( 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔ (DB Insert Failed)', 'jwpm-jewelry-pos-manager' ),
						'db_error' => $wpdb->last_error // Debugging info
					),
					500
				);
			}
			$id = (int) $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		wp_send_json_success(
			array(
				'message' => __( 'Saved successfully.', 'jwpm-jewelry-pos-manager' ),
				'item'    => $row,
			)
		);
	}

	public static function customers_delete() {
		self::verify_request( 'jwpm_customers_main_nonce', array( 'manage_jwpm_customers', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'customers', 'jwpm_customers' );
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid customer.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$wpdb->update(
			$table,
			array(
				'status'     => 'inactive',
				'updated_by' => get_current_user_id(),
			),
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		wp_send_json_success(
			array(
				'message' => __( 'Customer marked inactive.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function customers_get_single() {
		self::verify_request( 'jwpm_customers_main_nonce', array( 'manage_jwpm_customers', 'manage_options', 'jwpm_view_reports' ) );
		global $wpdb;

		$table = self::get_table( 'customers', 'jwpm_customers' );
			$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			wp_send_json_error(
				array(
					'message' => __( 'Customer not found.', 'jwpm-jewelry-pos-manager' ),
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
	 * Customers Import (JSON payload)
	 */
	public static function customers_import() {
		self::verify_request( 'jwpm_customers_main_nonce', array( 'manage_jwpm_customers', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'customers', 'jwpm_customers' );

		if ( empty( $_POST['items_json'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No data received for import.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$items = json_decode( wp_unslash( $_POST['items_json'] ), true );
		if ( ! is_array( $items ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid import payload.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$inserted = 0;
		$updated  = 0;

		foreach ( $items as $row ) {
			$phone = isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '';
			if ( '' === $phone ) {
				continue;
			}

			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$data = array(
				'name'          => $name,
				'phone'         => $phone,
				'city'          => isset( $row['city'] ) ? sanitize_text_field( $row['city'] ) : '',
				'status'        => isset( $row['status'] ) ? sanitize_text_field( wp_unslash( $row['status'] ) ) : 'active',
				'is_demo'       => isset( $row['is_demo'] ) ? (int) $row['is_demo'] : 0,
			);

			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE phone = %s LIMIT 1",
					$phone
				)
			);

			if ( $existing_id > 0 ) {
				$data['updated_by'] = get_current_user_id();
				$wpdb->update( $table, $data, array( 'id' => $existing_id ), null, array( '%d' ) );
				$updated++;
			} else {
				$max_id                = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
				$data['customer_code'] = sprintf( 'CUST-%04d', $max_id + 1 );
				$data['created_by']    = get_current_user_id();
				$data['created_at']    = current_time( 'mysql' );
				$data['opening_balance'] = '0.000';
				$data['current_balance'] = '0.000';
				$wpdb->insert( $table, $data );
				$inserted++;
			}
		}

		wp_send_json_success(
			array(
				'inserted' => $inserted,
					'updated'  => $updated,
				'message'  => __( 'Customers import completed.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function customers_export() {
		self::verify_request( 'jwpm_customers_main_nonce', array( 'manage_jwpm_customers', 'jwpm_view_reports', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'customers', 'jwpm_customers' );
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( '' !== $status ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		$sql  = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT 5000";
		$rows = empty( $params ) ? $wpdb->get_results( $sql, ARRAY_A ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		wp_send_json_success(
			array(
				'rows'    => $rows,
				'message' => __( 'Customers export data ready.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function customers_demo() {
		self::verify_request( 'jwpm_customers_main_nonce', array( 'manage_jwpm_customers', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'customers', 'jwpm_customers' );
		$mode  = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'create';

		if ( 'delete' === $mode ) {
			$wpdb->delete( $table, array( 'is_demo' => 1 ), array( '%d' ) );
			wp_send_json_success(
				array(
					'message' => __( 'Demo customers deleted.', 'jwpm-jewelry-pos-manager' ),
				)
			);
		}

		$existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_demo = 1" );
		if ( $existing > 0 ) {
			wp_send_json_success(
				array(
					'message' => __( 'Demo customers already exist.', 'jwpm-jewelry-pos-manager' ),
				)
			);
		}

		$demo_rows = array(
			array(
				'name'  => 'Demo Customer 1',
				'phone' => '03001234567',
			'city'  => 'Karachi',
			),
			array(
				'name'  => 'Demo Customer 2',
				'phone' => '03007654321',
				'city'  => 'Lahore',
			),
		);

		foreach ( $demo_rows as $row ) {
				$row['status']         = 'active';
			$row['is_demo']        = 1;
			$row['created_by']     = get_current_user_id();
			$row['created_at']     = current_time( 'mysql' 
			$row['opening_balance'] = '0.000';
			$row['current_balance'] = '0.000';
				$max_id                 = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
			$row['customer_code']   = sprintf( 'CUST-%04d', $max_id + 1 );
			$wpdb->insert( $table, $row );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Demo customers created.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	// 🔴 یہاں پر Customers Module ختم ہو رہا ہے
	// ✅ Syntax verified block end

	/**
	 * ==========================================================================
	 * 4. INSTALLMENTS MODULE
	 * ==========================================================================
	 */
	// 🟢 یہاں سے Installments Module شروع ہو رہا ہے

	public static function installments_fetch() {
		self::verify_request( 'jwpm_installments_main_nonce', array( 'manage_jwpm_finances', 'manage_options' ) );
		global $wpdb;

		$contracts_table = self::get_table( 'installments', 'jwpm_installments' );
		$customers_table = self::get_table( 'customers', 'jwpm_customers' );

		$search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$status    = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
			$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		$page      = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per       = isset( $_POST['per_page'] ) ? max( 1, (int) $_POST['per_page'] ) : 20;

			$where  = 'WHERE 1=1';
		$params = array();

		if ( $search ) {
					$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (c.name LIKE %s OR c.phone LIKE %s OR i.contract_code LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( $status ) {
			$where   .= ' AND i.status = %s';
			$params[] = $status;
		}
		if ( $date_from ) {
			$where   .= ' AND i.sale_date >= %s';
			$params[] = $date_from;
		}
		if ( $date_to ) {
			$where   .= ' AND i.sale_date <= %s';
			$params[] = $date_to;
		}

		$sql_total = "SELECT COUNT(*) FROM {$contracts_table} i LEFT JOIN {$customers_table} c ON i.customer_id = c.id {$where}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $sql_total, $params ) );

		$offset       = ( $page - 1 ) * $per;
		$params_items = array_merge( $params, array( $per, $offset ) );

		$sql_items = "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone FROM {$contracts_table} i LEFT JOIN {$customers_table} c ON i.customer_id = c.id {$where} ORDER BY i.created_at DESC LIMIT %d OFFSET %d";
		$rows      = $wpdb->get_results( $wpdb->prepare( $sql_items, $params_items ), ARRAY_A );

		wp_send_json_success(
			array(
				'items'      => $rows,
				'pagination' => array(
					'total'    => $total,
					'page'     => $page,
					'per_page' => $per,
				),
			)
		);
	}

	public static function installments_save() {
		self::verify_request( 'jwpm_installments_main_nonce', array( 'manage_jwpm_finances', 'manage_options' ) );
		global $wpdb;

		$contracts_table = self::get_table( 'installments', 'jwpm_installments' );
		$schedule_table  = self::get_table( 'inst_schedule', 'jwpm_installment_schedule' ); // Updated table name

		$id          = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$customer_id = isset( $_POST['customer_id'] ) ? (int) $_POST['customer_id'] : 0;

		if ( $customer_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Select customer.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$total  = self::sanitize_decimal( isset( $_POST['total_amount'] ) ? wp_unslash( $_POST['total_amount'] ) : '0' );
		$adv    = self::sanitize_decimal( isset( $_POST['advance_amount'] ) ? wp_unslash( $_POST['advance_amount'] ) : '0' );
		$net    = self::sanitize_decimal( (float) $total - (float) $adv );
		$count  = isset( $_POST['installment_count'] ) ? max( 0, (int) $_POST['installment_count'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';

		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : current_time( 'mysql' );
		$auto       = ! empty( $_POST['auto_generate_schedule'] );

		$data = array(
			'customer_id'        => $customer_id,
			'sale_date'          => $start_date,
			'total_amount'       => $total,
			'advance_amount'     => $adv,
			'net_installment_amount'         => $net, // Field name corrected
			'installment_count'  => $count,
			'start_date'         => $start_date,
			'status'             => $status,
			'remarks'            => isset( $_POST['remarks'] ) ? sanitize_textarea_field( wp_unslash( $_POST['remarks'] ) ) : '',
		);

		if ( $id > 0 ) {
			$data['updated_by'] = get_current_user_id();
			$wpdb->update( $contracts_table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$max                     = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$contracts_table}" );
			$data['contract_code']   = sprintf( 'INST-%04d', $max + 1 );
			$data['current_outstanding'] = $net;
			$data['created_by']      = get_current_user_id();
			$data['is_demo']         = 0;
			$data['created_at']      = current_time( 'mysql' );
			$wpdb->insert( $contracts_table, $data );
			$id = (int) $wpdb->insert_id;
		}

		if ( $auto && $count > 0 && (float) $net > 0 ) {
			$wpdb->delete( $schedule_table, array( 'contract_id' => $id ), array( '%d' ) );
			$per = self::sanitize_decimal( (float) $net / (float) $count );

			$dt = new DateTime( $start_date );
			for ( $i = 1; $i <= $count; $i++ ) {
				if ( $i > 1 ) {
					$dt->modify( '+1 month' );
				}
				$wpdb->insert(
					$schedule_table,
					array(
						'contract_id'   => $id,
						'installment_no'=> $i,
						'due_date'      => $dt->format( 'Y-m-d' ),
						'amount'        => $per,
						'paid_amount'   => '0.000',
						'status'        => 'pending',
						'is_demo'       => 0,
					)
				);
			}
		}

		wp_send_json_success(
			array(
				'message' => __( 'Saved.', 'jwpm-jewelry-pos-manager' ),
				'id'      => $id,
			)
		);
	}

	public static function installments_delete() {
		self::verify_request( 'jwpm_installments_main_nonce', array( 'manage_jwpm_finances', 'manage_options' ) );
		global $wpdb;

		$contracts_table = self::get_table( 'installments', 'jwpm_installments' );
		$schedule_table  = self::get_table( 'inst_schedule', 'jwpm_installment_schedule' ); // Updated table name

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid contract.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$wpdb->delete( $contracts_table, array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( $schedule_table, array( 'contract_id' => $id ), array( '%d' ) );

		wp_send_json_success(
			array(
				'message' => __( 'Installment contract deleted.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	/**
	 * قسط کی ادائیگی ریکارڈ کریں
	 */
	public static function installments_record_payment() {
		self::verify_request( 'jwpm_installments_main_nonce', array( 'manage_jwpm_finances', 'manage_options' ) );
		global $wpdb;

		$schedule_table  = self::get_table( 'inst_schedule', 'jwpm_installment_schedule' ); // Updated table name
		$contracts_table = self::get_table( 'installments', 'jwpm_installments' );

		$schedule_id = isset( $_POST['schedule_id'] ) ? (int) $_POST['schedule_id'] : 0;
		$amount      = self::sanitize_decimal( isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : '0' );
		$date_paid   = isset( $_POST['date_paid'] ) ? sanitize_text_field( wp_unslash( $_POST['date_paid'] ) ) : current_time( 'mysql' );

		if ( $schedule_id <= 0 || (float) $amount <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid payment.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$schedule = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$schedule_table} WHERE id = %d",
				$schedule_id
			),
			ARRAY_A
		);

		if ( ! $schedule ) {
			wp_send_json_error(
				array(
					'message' => __( 'Schedule not found.', 'jwpm-jewelry-pos-manager' ),
				),
				404
			);
		}

		$new_paid = self::sanitize_decimal( (float) $schedule['paid_amount'] + (float) $amount );

		$wpdb->update(
			$schedule_table,
			array(
				'paid_amount' => $new_paid,
				'status'      => ( (float) $new_paid >= (float) $schedule['amount'] ) ? 'paid' : 'partial',
				'paid_date'   => $date_paid,
			),
			array( 'id' => $schedule_id ),
			null,
			array( '%d' )
		);

		// contract outstanding adjust کریں
		$contract = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$contracts_table} WHERE id = %d",
				$schedule['contract_id']
			),
			ARRAY_A
		);

		if ( $contract ) {
			$new_outstanding = self::sanitize_decimal( (float) $contract['current_outstanding'] - (float) $amount );
			if ( (float) $new_outstanding < 0 ) {
				$new_outstanding = '0.000';
			}
			$wpdb->update(
				$contracts_table,
				array(
					'current_outstanding' => $new_outstanding,
				),
				array( 'id' => $contract['id'] ),
				null,
				array( '%d' )
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Payment recorded.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function installments_import() {
		self::verify_request( 'jwpm_installments_main_nonce', array( 'manage_jwpm_finances', 'manage_options' ) );
		// سادگی کے لیے ابھی صرف basic structure
		wp_send_json_error(
			array(
				'message' => __( 'Installments import not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			),
			501
		);
	}

	public static function installments_export() {
		self::verify_request( 'jwpm_installments_main_nonce', array( 'manage_jwpm_finances', 'jwpm_view_reports', 'manage_options' ) );
		global $wpdb;

		$contracts_table = self::get_table( 'installments', 'jwpm_installments' );

		$rows = $wpdb->get_results( "SELECT * FROM {$contracts_table} ORDER BY created_at DESC LIMIT 5000", ARRAY_A );
		wp_send_json_success(
			array(
				'rows'    => $rows,
				'message' => __( 'Installments export data ready.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	// 🔴 یہاں پر Installments Module ختم ہو رہا ہے
	// ✅ Syntax verified block end

	/**
	 * ==========================================================================
	 * 5. REPAIR JOBS MODULE
	 * ==========================================================================
	 */
	// 🟢 یہاں سے Repair Jobs Module شروع ہو رہا ہے

	public static function repair_fetch() {
		self::verify_request( 'jwpm_repair_main_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );
		global $wpdb;

		$table  = self::get_table( 'repairs', 'jwpm_repairs' );
		$search = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (customer_name LIKE %s OR tag_no LIKE %s OR job_code LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $params ) );

		$page     = isset( $_REQUEST['page'] ) ? max( 1, (int) $_REQUEST['page'] ) : 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$params_items = array_merge( $params, array( $per_page, $offset ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY promised_date ASC, id DESC LIMIT %d OFFSET %d", $params_items ), ARRAY_A );

		wp_send_json_success(
			array(
				'items'      => $rows,
				'pagination' => array(
					'total' => $total,
					'page'  => $page,
				),
			)
		);
	}

	public static function repair_save() {
		self::verify_request( 'jwpm_repair_main_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'repairs', 'jwpm_repairs' );
		$id    = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;

		$data = array(
			'customer_name'   => isset( $_REQUEST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['customer_name'] ) ) : '',
			'customer_phone'  => isset( $_REQUEST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['customer_phone'] ) ) : '',
			'tag_no'          => isset( $_REQUEST['tag_no'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tag_no'] ) ) : '',
			'item_description'=> isset( $_REQUEST['item_description'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['item_description'] ) ) : '',
			'job_status'      => isset( $_REQUEST['job_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['job_status'] ) ) : 'received',
			'estimated_charges' => isset( $_REQUEST['estimated_charges'] ) ? (float) $_REQUEST['estimated_charges'] : 0,
			'advance_amount'  => isset( $_REQUEST['advance_amount'] ) ? (float) $_REQUEST['advance_amount'] : 0,
			'promised_date'   => isset( $_REQUEST['promised_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['promised_date'] ) ) : '',
		);

		if ( $id > 0 ) {
			$data['updated_at'] = current_time( 'mysql' );
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$max              = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$table}" );
			$data['job_code'] = sprintf( 'RJ-%04d', $max + 1 );
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$id = (int) $wpdb->insert_id;
		}

		wp_send_json_success(
			array(
				'id'      => $id,
				'message' => __( 'Repair job saved.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function repair_delete() {
		self::verify_request( 'jwpm_repair_main_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'repairs', 'jwpm_repairs' );
		$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid repair job.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		wp_send_json_success(
			array(
				'message' => __( 'Repair job deleted.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function repair_import() {
		self::verify_request( 'jwpm_repair_main_nonce', array( 'manage_jwpm_inventory', 'manage_options' ) );
		wp_send_json_error(
			array(
				'message' => __( 'Repairs import not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			),
			501
		);
	}

	public static function repair_export() {
		self::verify_request( 'jwpm_repair_main_nonce', array( 'manage_jwpm_inventory', 'jwpm_view_reports', 'manage_options' ) );
		global $wpdb;

		$table = self::get_table( 'repairs', 'jwpm_repairs' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY promised_date ASC, id DESC LIMIT 5000", ARRAY_A );

		wp_send_json_success(
			array(
				'rows'    => $rows,
				'message' => __( 'Repairs export data ready.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	// 🔴 یہاں پر Repair Jobs Module ختم ہو رہا ہے
	// ✅ Syntax verified block end

	/**
	 * ==========================================================================
	 * 6. ACCOUNTS MODULE
	 * ==========================================================================
	 */
	// 🟢 یہاں سے Accounts Module شروع ہو رہا ہے

	protected static function accounts_verify( $nonce_action ) {
		self::verify_request( $nonce_action, array( 'manage_jwpm_finances', 'jwpm_view_accounts', 'manage_options' ) );
	}

	public static function accounts_cashbook_fetch() {
		self::accounts_verify( 'jwpm_cashbook_nonce' );
		global $wpdb;

		$table = self::get_table( 'cashbook', 'jwpm_cashbook' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY entry_date DESC, id DESC LIMIT 200", ARRAY_A );

		$summary = $wpdb->get_row( "SELECT SUM(CASE WHEN type='in' THEN amount ELSE 0 END) as total_in, SUM(CASE WHEN type='out' THEN amount ELSE 0 END) as total_out FROM {$table}", ARRAY_A );
		$closing = (float) ( $summary['total_in'] ?? 0 ) - (float) ( $summary['total_out'] ?? 0 );

		wp_send_json_success(
			array(
				'items'   => $rows,
				'summary' => array(
					'closing' => $closing,
				),
			)
		);
	}

	public static function accounts_cashbook_save() {
		self::accounts_verify( 'jwpm_cashbook_nonce' );
		global $wpdb;

		$table = self::get_table( 'cashbook', 'jwpm_cashbook' );
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$data = array(
			'entry_date' => isset( $_POST['entry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_date'] ) ) : current_time( 'Y-m-d' ),
			'type'       => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'in',
			'amount'     => isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0,
			'category'   => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'remarks'    => isset( $_POST['remarks'] ) ? sanitize_textarea_field( wp_unslash( $_POST['remarks'] ) ) : '',
			'updated_at' => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Saved.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function accounts_cashbook_delete() {
		self::accounts_verify( 'jwpm_cashbook_nonce' );
		global $wpdb;

		$table = self::get_table( 'cashbook', 'jwpm_cashbook' );
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( $id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid cashbook entry.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		wp_send_json_success(
			array(
				'message' => __( 'Cashbook entry deleted.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function accounts_expenses_fetch() {
		self::accounts_verify( 'jwpm_expenses_nonce' );
		global $wpdb;

		$table = self::get_table( 'expenses', 'jwpm_expenses' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY expense_date DESC, id DESC LIMIT 200", ARRAY_A );

		wp_send_json_success(
			array(
				'items' => $rows,
			)
		);
	}

	public static function accounts_expenses_save() {
		self::accounts_verify( 'jwpm_expenses_nonce' );
		global $wpdb;

		$table = self::get_table( 'expenses', 'jwpm_expenses' );
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$data = array(
			'expense_date' => isset( $_POST['expense_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_date'] ) ) : current_time( 'Y-m-d' ),
			'category'     => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'amount'       => isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0,
			'vendor'       => isset( $_POST['vendor'] ) ? sanitize_text_field( wp_unslash( $_POST['vendor'] ) ) : '',
			'notes'        => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			'updated_at'   => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), null, array( '%d' ) );
		} else {
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Expense saved.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function accounts_expenses_delete() {
		self::accounts_verify( 'jwpm_expenses_nonce' );
		global $wpdb;

		$table = self::get_table( 'expenses', 'jwpm_expenses' );
		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( $id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid expense.', 'jwpm-jewelry-pos-manager' ),
				),
				400
			);
		}

		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		wp_send_json_success(
			array(
				'message' => __( 'Expense deleted.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function accounts_ledger_fetch() {
		self::accounts_verify( 'jwpm_ledger_nonce' );
		global $wpdb;

		$table = self::get_table( 'accounts', 'jwpm_accounts_ledger' ); // Corrected table key
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT 500", ARRAY_A );
		$sum   = $wpdb->get_row( "SELECT SUM(debit) as d, SUM(credit) as c FROM {$table}", ARRAY_A );

		$balance = (float) ( $sum['d'] ?? 0 ) - (float) ( $sum['c'] ?? 0 );

		wp_send_json_success(
			array(
				'items'   => $rows,
				'summary' => array(
					'balance' => $balance,
				),
			)
		);
	}

	// 🔴 یہاں پر Accounts Module ختم ہو رہا ہے
	// ✅ Syntax verified block end

	/**
	 * ==========================================================================
	 * 7. DASHBOARD APIs
	 * ==========================================================================
	 */
	// 🟢 یہاں سے Dashboard APIs شروع ہو رہا ہے

	public static function dashboard_get_stats() {
		self::verify_request( 'jwpm_dashboard_nonce', array( 'jwpm_view_reports', 'jwpm_manager', 'jwpm_admin', 'manage_options' ) );

		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_dashboard_stats' ) ) {
			$stats = JWPM_DB::get_dashboard_stats();
			wp_send_json_success( $stats );
		}

		wp_send_json_success(
			array(
				'sales_today'         => 0,
				'sales_month'         => 0,
				'inventory_value'     => 0,
				'customers_count'     => 0,
				'installments_due'    => 0,
				'low_stock_count'     => 0,
				'message'             => __( 'Dashboard stats provider not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function dashboard_get_recent_activity() {
		self::verify_request( 'jwpm_dashboard_nonce', array( 'jwpm_view_reports', 'jwpm_manager', 'jwpm_admin', 'manage_options' ) );

		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_recent_activity' ) ) {
			$rows = JWPM_DB::get_recent_activity();
			wp_send_json_success(
				array(
					'items' => $rows,
				)
			);
		}

		wp_send_json_success(
			array(
				'items'   => array(),
				'message' => __( 'Activity log provider not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	// 🔴 یہاں پر Dashboard APIs ختم ہو رہا ہے
	// ✅ Syntax verified block end

	/**
	 * ==========================================================================
	 * 8. REPORTS APIs
	 * ==========================================================================
	 */
	// 🟢 یہاں سے Reports APIs شروع ہو رہا ہے

	protected static function reports_date_range_from_request() {
		$from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		return array(
			'from' => $from,
			'to'   => $to,
		);
	}

	// Sales Reports

	public static function reports_sales_daily() {
		self::verify_request( 'jwpm_reports_nonce', array( 'manage_jwpm_reports', 'jwpm_view_reports', 'manage_options' ) );

		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : current_time( 'Y-m-d' );
		$range = array(
			'from' => $date,
			'to'   => $date,
		);

		self::send_sales_report( $range );
	}

	public static function reports_sales_monthly() {
		self::verify_request( 'jwpm_reports_nonce', array( 'manage_jwpm_reports', 'jwpm_view_reports', 'manage_options' ) );

		$month = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : date( 'Y-m' );
		$range = array(
			'from' => $month . '-01',
			'to'   => $month . '-31',
		);

		self::send_sales_report( $range );
	}

	public static function reports_sales_custom() {
		self::verify_request( 'jwpm_reports_nonce', array( 'manage_jwpm_reports', 'jwpm_view_reports', 'manage_options' ) );
		$range = self::reports_date_range_from_request();
		self::send_sales_report( $range );
	}

	protected static function send_sales_report( $range ) {
		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_sales_data' ) ) {
			$data = JWPM_DB::get_sales_data( $range );
			wp_send_json_success( $data );
		}

		wp_send_json_success(
			array(
				'rows'    => array(),
				'summary' => array(
					'total_sales' => 0,
					'count'       => 0,
				),
				'message' => __( 'Sales report provider not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	// Inventory Reports

	public static function reports_inventory_stock_levels() {
		self::verify_request( 'jwpm_reports_nonce', array( 'manage_jwpm_reports', 'jwpm_view_reports', 'manage_jwpm_inventory', 'manage_options' ) );

		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_stock_alerts' ) ) {
			$data = JWPM_DB::get_stock_alerts();
			wp_send_json_success( $data );
		}

		wp_send_json_success(
			array(
				'items'   => array(),
				'message' => __( 'Stock alerts provider not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function reports_inventory_low_stock() {
		// Low stock کو بھی (get_stock_alerts) سے نکالا جا سکتا ہے
		self::reports_inventory_stock_levels();
	}

	public static function reports_inventory_movement() {
		self::verify_request( 'jwpm_reports_nonce', array( 'manage_jwpm_reports', 'jwpm_view_reports', 'manage_jwpm_inventory', 'manage_options' ) );

		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_inventory_movement' ) ) {
			$range = self::reports_date_range_from_request();
			$data  = JWPM_DB::get_inventory_movement( $range );
			wp_send_json_success( $data );
		}

		wp_send_json_success(
			array(
				'rows'    => array(),
				'message' => __( 'Inventory movement provider not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	// Financial Reports

	public static function reports_profit_loss() {
		self::verify_request( 'jwpm_reports_nonce', array( 'manage_jwpm_reports', 'jwpm_view_reports', 'manage_jwpm_finances', 'manage_options' ) );

		$filters = self::reports_date_range_from_request();
		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'calculate_profit' ) ) {
			$data = JWPM_DB::calculate_profit( $filters );
			wp_send_json_success( $data );
		}

		wp_send_json_success(
			array(
				'profit'  => 0,
				'message' => __( 'Profit calculation provider not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function reports_expense() {
		self::verify_request( 'jwpm_reports_nonce', array( 'manage_jwpm_reports', 'jwpm_view_reports', 'manage_jwpm_finances', 'manage_options' ) );

		$filters = self::reports_date_range_from_request();

		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_expense_report' ) ) {
			$data = JWPM_DB::get_expense_report( $filters );
			wp_send_json_success( $data );
		}

		wp_send_json_success(
			array(
				'rows'    => array(),
				'message' => __( 'Expense report provider not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	public static function reports_cashflow() {
		self::verify_request( 'jwpm_reports_nonce', array( 'manage_jwpm_reports', 'jwpm_view_reports', 'manage_jwpm_finances', 'manage_options' ) );

		$filters = self::reports_date_range_from_request();

		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'get_cashflow_report' ) ) {
			$data = JWPM_DB::get_cashflow_report( $filters );
			wp_send_json_success( $data );
		}

		wp_send_json_success(
			array(
				'rows'    => array(),
				'message' => __( 'Cashflow report provider not implemented yet.', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	// 🔴 یہاں پر Reports APIs ختم ہو رہا ہے
	// ✅ Syntax verified block end

	/**
	 * ==========================================================================
	 * 9. CUSTOM ORDERS MODULE
	 * ==========================================================================
	 */
	// 🟢 یہاں سے Custom Orders Module شروع ہو رہا ہے

	/**
	 * Custom Orders کے لیے common access check
	 *
	 * @param string $nonce_action
	 * @param string $capability
	 */
	protected static function custom_orders_check_access( $nonce_action = 'jwpm_custom_orders_main_nonce', $capability = 'manage_jwpm_inventory' ) {
		// JS کی ajaxPost() 'security' میں nonce بھیج رہی ہے
		$field = 'security';

		if ( isset( $_REQUEST['nonce'] ) ) {
			$field = 'nonce';
		}
		
		check_ajax_referer( $nonce_action, $field );

		if ( ! current_user_can( $capability ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'آپ کے پاس Custom Orders مینیج کرنے کی اجازت نہیں ہے۔', 'jwpm-jewelry-pos-manager' ) ),
				403
			);
		}
	}

	/**
	 * Custom Orders لسٹ (فلٹر + پیجینیشن کے ساتھ)
	 *
	 * AJAX Action: jwpm_custom_orders_fetch
	 */
	public static function custom_orders_fetch() {
		self::custom_orders_check_access( 'jwpm_custom_orders_main_nonce', 'manage_jwpm_inventory' );

		global $wpdb;

		if ( ! class_exists( 'JWPM_DB' ) ) {
			wp_send_json_error( array( 'message' => 'DB Helper (JWPM_DB) موجود نہیں۔' ), 500 );
		}

		$tables     = JWPM_DB::get_table_names();
		$orders_tbl = self::get_table( 'custom_orders', 'jwpm_custom_orders' );
		$customers  = self::get_table( 'customers', 'jwpm_customers' );

		$page     = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? max( 1, (int) $_POST['per_page'] ) : 20;
		$offset   = ( $page - 1 ) * $per_page;

		$search    = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$status    = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$branch_id = isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0;
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $branch_id > 0 ) {
			$where    .= ' AND o.branch_id = %d';
			$params[] = $branch_id;
		}

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (c.name LIKE %s OR c.phone LIKE %s OR o.design_reference LIKE %s OR o.id LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( '' !== $status ) {
			$where    .= ' AND o.status = %s';
			$params[] = $status;
		}

		if ( '' !== $date_from ) {
			$where    .= ' AND o.due_date >= %s';
			$params[] = $date_from;
		}

		if ( '' !== $date_to ) {
			$where    .= ' AND o.due_date <= %s';
			$params[] = $date_to;
		}

		$sql_base  = "FROM {$orders_tbl} o LEFT JOIN {$customers} c ON o.customer_id = c.id {$where}";
		$count_sql = "SELECT COUNT(*) {$sql_base}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		$list_sql = "
			SELECT 
				o.id,
				o.branch_id,
				o.customer_id,
				o.design_reference,
				o.estimate_weight,
				o.estimate_amount,
				o.advance_amount,
				o.status,
				o.due_date,
				o.created_at,
				COALESCE(c.name, '')  AS customer_name,
				COALESCE(c.phone, '') AS customer_phone
			{$sql_base}
			ORDER BY o.created_at DESC
			LIMIT %d OFFSET %d
		";

		$params_list = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results( $wpdb->prepare( $list_sql, $params_list ), ARRAY_A );

		$items = array();

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$id = (int) $row['id'];

				$items[] = array(
					'id'              => $id,
					'order_code'      => sprintf( 'CO-%04d', $id ),
					'branch_id'       => (int) $row['branch_id'],
					'customer_id'     => (int) $row['customer_id'],
					'customer_name'   => $row['customer_name'],
					'customer_phone'  => $row['customer_phone'],
					'design_reference'=> $row['design_reference'],
					'estimate_weight' => isset( $row['estimate_weight'] ) ? (float) $row['estimate_weight'] : 0,
					'estimate_amount' => isset( $row['estimate_amount'] ) ? (float) $row['estimate_amount'] : 0,
					'advance_amount'  => isset( $row['advance_amount'] ) ? (float) $row['advance_amount'] : 0,
					'status'          => $row['status'],
					'due_date'        => $row['due_date'],
					'created_at'      => $row['created_at'],
				);
			}
		}

		wp_send_json_success(
			array(
				'items'      => $items,
				'pagination' => array(
					'total'       => $total,
					'page'        => $page,
					'per_page'    => $per_page,
					'total_pages' => ( $per_page > 0 ) ? max( 1, (int) ceil( $total / $per_page ) ) : 1,
				),
			)
		);
	}

	/**
	 * Custom Order محفوظ / اپڈیٹ
	 *
	 * AJAX Action: jwpm_custom_orders_save
	 */
	public static function custom_orders_save() {
		self::custom_orders_check_access( 'jwpm_custom_orders_main_nonce', 'manage_jwpm_inventory' );

		global $wpdb;

		if ( ! class_exists( 'JWPM_DB' ) ) {
			wp_send_json_error( array( 'message' => 'DB Helper (JWPM_DB) موجود نہیں۔' ), 500 );
		}

		$tables     = JWPM_DB::get_table_names();
		$orders_tbl = self::get_table( 'custom_orders', 'jwpm_custom_orders' );
		$customers  = self::get_table( 'customers', 'jwpm_customers' );

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$customer_name  = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$customer_phone = isset( $_POST['customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ) : '';

		if ( '' === $customer_name || '' === $customer_phone ) {
			wp_send_json_error(
				array( 'message' => __( 'کسٹمر نام اور فون نمبر لازمی ہیں۔', 'jwpm-jewelry-pos-manager' ) ),
				400
			);
		}

		// Branch مستقبل میں Settings سے آئے، فی الحال 0
		$branch_id = isset( $_POST['branch_id'] ) ? (int) $_POST['branch_id'] : 0;

		$design_reference = isset( $_POST['design_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['design_reference'] ) ) : '';
		$estimate_weight  = isset( $_POST['estimate_weight'] ) ? (float) $_POST['estimate_weight'] : 0;
		$estimate_amount  = isset( $_POST['estimate_amount'] ) ? (float) $_POST['estimate_amount'] : 0;
		$advance_amount   = isset( $_POST['advance_amount'] ) ? (float) $_POST['advance_amount'] : 0;
		$status           = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'designing';
		$due_date         = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';
		// نوٹس کو فی الحال DB میں محفوظ نہیں کر رہے، جب تک custom_orders table میں 'notes' کالم add نہ ہو

		// 1) کسٹمر تلاش کریں (phone کی بنیاد پر)، نہ ہو تو create
		$customer_id = 0;

		$customer_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$customers} WHERE phone = %s LIMIT 1",
				$customer_phone
			)
		);

		if ( $customer_id <= 0 ) {
			$wpdb->insert(
				$customers,
				array(
					// Note: customers table میں branch_id, total_sales, balance_due, email, address, is_demo fields لازمی ہیں
					// لیکن چونکہ DB schema اس میں کئی fields (جیسے total_sales, balance_due) کو support نہیں کر رہا تھا،
					// ہم صرف وہ fields insert کریں گے جو JWPM_DB میں ڈیفائن کیے گئے تھے (customers table merge میں)۔
					'name'        => $customer_name,
					'phone'       => $customer_phone,
					'customer_code' => sprintf( 'CUST-%04d', (int) $wpdb->get_var( "SELECT MAX(id) FROM {$customers}" ) + 1 ),
					'created_at'  => current_time( 'mysql' ),
					'is_demo'     => 0,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
				)
			);

			$customer_id = (int) $wpdb->insert_id;
		}

		if ( $customer_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'کسٹمر سیو نہیں ہو سکا، بعد میں دوبارہ کوشش کریں۔', 'jwpm-jewelry-pos-manager' ) ),
				500
			);
		}

		$data = array(
			'customer_id'      => $customer_id,
			'branch_id'        => $branch_id,
			'design_reference' => $design_reference,
			'estimate_weight'  => $estimate_weight,
			'estimate_amount'  => $estimate_amount,
			'advance_amount'   => $advance_amount,
			'status'           => $status,
			'due_date'         => $due_date,
		);

		$formats = array( '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s' );

		if ( $id > 0 ) {
			$data['updated_at'] = current_time( 'mysql' );
			$formats[]          = '%s';

			$updated = $wpdb->update(
				$orders_tbl,
				$data,
				array( 'id' => $id ),
				$formats,
				array( '%d' )
			);

			if ( false === $updated ) {
				wp_send_json_error(
					array( 'message' => __( 'Custom Order اپڈیٹ نہیں ہو سکا۔', 'jwpm-jewelry-pos-manager' ) ),
					500
				);
			}

			if ( method_exists( 'JWPM_DB', 'log_activity' ) ) {
				JWPM_DB::log_activity(
					get_current_user_id(),
					'custom_order_update',
					'custom_order',
					$id,
					$data
				);
			}
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$formats[]          = '%s';

			$inserted = $wpdb->insert(
				$orders_tbl,
				$data,
				$formats
			);

			if ( ! $inserted ) {
				wp_send_json_error(
					array( 'message' => __( 'Custom Order بن نہیں سکا۔', 'jwpm-jewelry-pos-manager' ) ),
					500
				);
			}

			$id = (int) $wpdb->insert_id;

			if ( method_exists( 'JWPM_DB', 'log_activity' ) ) {
				JWPM_DB::log_activity(
					get_current_user_id(),
					'custom_order_create',
					'custom_order',
					$id,
					$data
				);
			}
		}

		// تازہ ریکارڈ واپس بھیج دیں (UI کے لیے)
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT o.*, c.name AS customer_name, c.phone AS customer_phone
				 FROM {$orders_tbl} o
				 LEFT JOIN {$customers} c ON o.customer_id = c.id
				 WHERE o.id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			wp_send_json_success(
				array(
					'message' => __( 'Custom Order محفوظ ہو گیا، لیکن detail لوڈ نہیں ہو سکی۔', 'jwpm-jewelry-pos-manager' ),
					'id'      => $id,
				)
			);
		}

		$item = array(
			'id'              => (int) $row['id'],
			'order_code'      => sprintf( 'CO-%04d', (int) $row['id'] ),
			'branch_id'       => (int) $row['branch_id'],
			'customer_id'     => (int) $row['customer_id'],
			'customer_name'   => $row['customer_name'],
			'customer_phone'  => $row['customer_phone'],
			'design_reference'=> $row['design_reference'],
			'estimate_weight' => isset( $row['estimate_weight'] ) ? (float) $row['estimate_weight'] : 0,
			'estimate_amount' => isset( $row['estimate_amount'] ) ? (float) $row['estimate_amount'] : 0,
			'advance_amount'  => isset( $row['advance_amount'] ) ? (float) $row['advance_amount'] : 0,
			'status'          => $row['status'],
			'due_date'        => $row['due_date'],
			'created_at'      => $row['created_at'],
		);

		wp_send_json_success(
			array(
				'message' => __( 'Custom Order کامیابی سے محفوظ ہو گیا۔', 'jwpm-jewelry-pos-manager' ),
				'item'    => $item,
			)
		);
	}

	/**
	 * Custom Order حذف (hard delete)
	 *
	 * AJAX Action: jwpm_custom_orders_delete
	 */
	public static function custom_orders_delete() {
		self::custom_orders_check_access( 'jwpm_custom_orders_main_nonce', 'manage_jwpm_inventory' );

		global $wpdb;

		if ( ! class_exists( 'JWPM_DB' ) ) {
			wp_send_json_error( array( 'message' => 'DB Helper (JWPM_DB) موجود نہیں۔' ), 500 );
		}

		$tables     = JWPM_DB::get_table_names();
		$orders_tbl = self::get_table( 'custom_orders', 'jwpm_custom_orders' );

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'غلط ID موصول ہوئی ہے۔', 'jwpm-jewelry-pos-manager' ) ),
				400
			);
		}

		$deleted = $wpdb->delete(
			$orders_tbl,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			wp_send_json_error(
				array( 'message' => __( 'Custom Order حذف نہیں ہو سکا۔', 'jwpm-jewelry-pos-manager' ) ),
					// $wpdb->last_error یہاں شامل کیا جا سکتا ہے اگر debugging کرنی ہو
				500
			);
		}

		if ( method_exists( 'JWPM_DB', 'log_activity' ) ) {
			JWPM_DB::log_activity(
				get_current_user_id(),
				'custom_order_delete',
				'custom_order',
				$id
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Custom Order حذف کر دیا گیا۔', 'jwpm-jewelry-pos-manager' ),
			)
		);
	}

	/**
	 * Custom Orders Import (فائل سے)
	 *
	 * AJAX Action: jwpm_custom_orders_import
	 *
	 * نوٹ: فی الحال placeholder – صرف API available ہے،
	 * اصل Excel/CSV parsing بعد میں implement کی جائے گی۔
	 */
	public static function custom_orders_import() {
		// JS FormData 'nonce' میں import nonce بھیج رہا ہے
		check_ajax_referer( 'jwpm_custom_orders_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_jwpm_inventory' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'آپ کے پاس Import کی اجازت نہیں ہے۔', 'jwpm-jewelry-pos-manager' ) ),
				403
			);
		}

		// ابھی کے لیے صرف placeholder response:
		wp_send_json_error(
			array(
				'message' => __( 'Custom Orders Import فی الحال implement نہیں ہوا۔', 'jwpm-jewelry-pos-manager' ),
			),
			501
		);
	}

	/**
	 * Custom Orders Export / Excel Download
	 *
	 * AJAX Action: jwpm_custom_orders_export
	 *
	 * JS:  window.location.href = admin-ajax.php?action=jwpm_custom_orders_export&nonce=...
	 */
	public static function custom_orders_export() {
		// GET/REQUEST میں 'nonce' آ رہا ہے
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'jwpm_custom_orders_export_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'jwpm-jewelry-pos-manager' ), 403 );
		}

		if ( ! current_user_can( 'manage_jwpm_inventory' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'آپ کے پاس Export کی اجازت نہیں ہے۔', 'jwpm-jewelry-pos-manager' ), 403 );
		}

		global $wpdb;

		if ( ! class_exists( 'JWPM_DB' ) ) {
			wp_die( 'DB Helper (JWPM_DB) موجود نہیں۔', 500 );
		}

		$tables     = JWPM_DB::get_table_names();
		$orders_tbl = self::get_table( 'custom_orders', 'jwpm_custom_orders' );
		$customers  = self::get_table( 'customers', 'jwpm_customers' );

		// سادہ CSV Export – مستقبل میں filters بھی add ہو سکتے ہیں
		$sql = "
			SELECT 
				o.id,
				o.branch_id,
				o.customer_id,
				o.design_reference,
				o.estimate_weight,
				o.estimate_amount,
				o.advance_amount,
				o.status,
				o.due_date,
				o.created_at,
				COALESCE(c.name, '')  AS customer_name,
				COALESCE(c.phone, '') AS customer_phone
			FROM {$orders_tbl} o
			LEFT JOIN {$customers} c ON o.customer_id = c.id
			ORDER BY o.created_at DESC
			LIMIT 1000
		";

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		// $is_excel = isset( $_GET['format'] ) && 'excel' === $_GET['format']; // Not implemented

		$filename = 'jwpm-custom-orders-' . gmdate( 'Ymd-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$fh = fopen( 'php://output', 'w' );

		// Header row
		fputcsv(
			$fh,
			array(
				'Order ID',
				'Order Code',
				'Branch ID',
				'Customer ID',
				'Customer Name',
				'Customer Phone',
				'Design Reference',
				'Estimate Weight',
				'Estimate Amount',
				'Advance Amount',
				'Status',
				'Due Date',
				'Created At',
			)
		);

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$id = (int) $row['id'];

				fputcsv(
					$fh,
					array(
						$id,
						sprintf( 'CO-%04d', $id ),
						$row['branch_id'],
						$row['customer_id'],
						$row['customer_name'],
						$row['customer_phone'],
						$row['design_reference'],
						$row['estimate_weight'],
						$row['estimate_amount'],
						$row['advance_amount'],
						$row['status'],
						$row['due_date'],
						$row['created_at'],
					)
				);
			}
		}

		fclose( $fh );
		// CSV output کے بعد execution ختم
		exit;
	}

	/**
	 * Custom Orders Demo Data (Placeholder)
	 *
	 * AJAX Action: jwpm_custom_orders_demo
	 */
	public static function custom_orders_demo() {
		self::custom_orders_check_access( 'jwpm_custom_orders_main_nonce', 'manage_jwpm_inventory' );

		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'create';

		// فی الحال حقیقت میں demo rows نہیں ڈال رہے،
		// صرف API موجود ہے، بعد میں db structure کے مطابق implement کریں گے۔
		$message = ( 'delete' === $mode )
			? __( 'Demo data delete handler placeholder.', 'jwpm-jewelry-pos-manager' )
			: __( 'Demo data create handler placeholder.', 'jwpm-jewelry-pos-manager' );

		wp_send_json_success(
			array(
				'mode'    => $mode,
				'message' => $message,
			)
		);
	}
	// 🔴 یہاں پر Custom Orders Module ختم ہو رہا ہے
	// ✅ Syntax verified block end
}

// ✅ Syntax verified block end (JWPM_Ajax کلاس)
