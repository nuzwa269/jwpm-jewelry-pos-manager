<?php
/**
 * JWPM_Activator
 *
 * یہ کلاس پلگ اِن کی (activation)، (deactivation) اور (uninstall) لاجک کو ہینڈل کرتی ہے۔
 * اسی میں رولز، (capabilities) اور بنیادی (DB) ٹیبلز بنائے جاتے ہیں۔
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Activator {

	/**
	 * پلگ اِن ایکٹیویشن پر چلنے والا فنکشن
	 *
	 * - کسٹم (roles) اور (capabilities) ایڈ کرے گا
	 * - تمام (DB) ٹیبلز بنوائے گا (JWPM_DB::create_tables)
	 * - اگر Repair module والا helper موجود ہو تو اس کے (DB) ٹیبلز بھی dbDelta() سے create ہوں گے
	 * - ورژن آپشنز اپ ڈیٹ ہوں گے
	 */
	public static function activate() {
		// 🟢 یہاں سے [Activation Logic] شروع ہو رہا ہے

		// رولز اور (capabilities) سیٹ کریں
		self::add_roles();

		// بنیادی (DB) ٹیبلز — مرکزی (JWPM_DB) کلاس کے ذریعے
		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'create_tables' ) ) {
			JWPM_DB::create_tables();
		}

		// اگر Repair module کے لیے الگ helper موجود ہے تو اسے بھی dbDelta کے ساتھ چلا دیں
		if ( function_exists( 'jwpm_repair_get_table_schemas' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$schemas = jwpm_repair_get_table_schemas();
			if ( is_array( $schemas ) ) {
				foreach ( $schemas as $sql ) {
					if ( ! empty( $sql ) ) {
						dbDelta( $sql );
					}
				}
			}
		}

		// ورژن سیو کریں (یہ کنسٹنٹس main پلگ اِن فائل میں define ہونے چاہئیں)
		if ( defined( 'JWPM_VERSION' ) ) {
			update_option( 'jwpm_version', JWPM_VERSION );
		}
		if ( defined( 'JWPM_DB_VERSION' ) ) {
			update_option( 'jwpm_db_version', JWPM_DB_VERSION );
		}

		// 🔴 یہاں پر [Activation Logic] ختم ہو رہا ہے
	}

	/**
	 * پلگ اِن ڈی ایکٹیویشن
	 *
	 * ابھی لائٹ لاجک — مستقبل میں:
	 * - (cron) jobs clear
	 * - cache / temp data وغیرہ بھی handle ہو سکتا ہے
	 */
	public static function deactivate() {
		// 🟢 یہاں سے [Deactivation Logic] شروع ہو رہا ہے

		// فی الحال کچھ خاص نہیں، لیکن ہُکس کے لیے پلیس ہولڈر رکھا ہے۔

		// 🔴 یہاں پر [Deactivation Logic] ختم ہو رہا ہے
	}

	/**
	 * پلگ اِن اَن انسٹال
	 *
	 * - اگر فلٹر jwpm_hard_delete_on_uninstall TRUE دے تو:
	 *   - تمام (JWPM) ٹیبلز (JWPM_DB::drop_tables) کے ذریعے drop ہوں گے
	 *   - ورژن آپشنز delete ہوں گے
	 * - ورنہ soft uninstall (ڈیٹا محفوظ رہے گا)
	 */
	public static function uninstall() {

		// 🟢 یہاں سے [Uninstall Logic] شروع ہو رہا ہے

		/**
		 * فلٹر: jwpm_hard_delete_on_uninstall
		 *
		 * مثال (theme / custom code میں):
		 * add_filter( 'jwpm_hard_delete_on_uninstall', '__return_true' );
		 */
		$hard_delete = apply_filters( 'jwpm_hard_delete_on_uninstall', false );

		if ( $hard_delete ) {

			// تمام (JWPM) ٹیبلز ڈراپ کریں – اگر کلاس موجود ہو
			if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'drop_tables' ) ) {
				JWPM_DB::drop_tables();
			}

			// Repair module کے ٹیبلز الگ handle کرنے ہوں تو یہاں کر سکتے ہیں
			// (عمومی طور پر JWPM_DB::drop_tables میں cover ہو جانا چاہئے)

			// آپشنز ڈیلیٹ کریں
			delete_option( 'jwpm_version' );
			delete_option( 'jwpm_db_version' );
		}

		// 🔴 یہاں پر [Uninstall Logic] ختم ہو رہا ہے
	}

	/**
	 * کسٹم (roles) اور (capabilities) ایڈ کریں
	 *
	 * یہاں ہم:
	 * - JWPM Owner
	 * - JWPM Manager
	 * - JWPM Salesperson
	 * - JWPM Accountant
	 * - JWPM Karigar
	 *
	 * یہ رولز بناتے ہیں، اور
	 * Administrator کو تمام JWPM (capabilities) دے دیتے ہیں۔
	 */
	public static function add_roles() {

		// 🟢 یہاں سے [Roles & Capabilities] شروع ہو رہا ہے

		// مالکانہ رول
		add_role(
			'jwpm_owner',
			'JWPM Owner',
			array(
				'read'             => true,
				'manage_options'   => true,          // WordPress core capability
				'manage_jwpm_all'  => true,          // Full JWPM access
			)
		);

		// منیجر
		add_role(
			'jwpm_manager',
			'JWPM Manager',
			array(
				'read'                   => true,
				'manage_jwpm_sales'      => true,
				'manage_jwpm_inventory'  => true,
				'manage_jwpm_accounts'   => true,
				'manage_jwpm_reports'    => true,
				'manage_jwpm_customers'  => true,
				'manage_jwpm_orders'     => true,
				'manage_jwpm_repairs'    => true,
			)
		);

		// سیلز پرسن
		add_role(
			'jwpm_salesperson',
			'JWPM Salesperson',
			array(
				'read'                  => true,
				'manage_jwpm_sales'     => true,
				'manage_jwpm_customers' => true,
				'manage_jwpm_orders'    => true,
			)
		);

		// اکاؤنٹنٹ
		add_role(
			'jwpm_accountant',
			'JWPM Accountant',
			array(
				'read'                  => true,
				'manage_jwpm_accounts'  => true,
				'manage_jwpm_reports'   => true,
			)
		);

		// کاریگر / ورکشاپ
		add_role(
			'jwpm_karigar',
			'JWPM Karigar',
			array(
				'read'                 => true,
				'manage_jwpm_repairs'  => true,
			)
		);

		// موجودہ ایڈمن رول میں بھی تمام (JWPM) صلاحیتیں شامل کر دیں
		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {

			// تمام core JWPM (caps) ایک جگہ define
			$caps = array(
				'manage_jwpm_all',
				'manage_jwpm_sales',
				'manage_jwpm_inventory',
				'manage_jwpm_accounts',
				'manage_jwpm_reports',
				'manage_jwpm_settings',
				'manage_jwpm_customers',
				'manage_jwpm_orders',
				'manage_jwpm_repairs',
			);

			foreach ( $caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		// 🔴 یہاں پر [Roles & Capabilities] ختم ہو رہا ہے
	}
}

// ✅ Syntax verified block end
