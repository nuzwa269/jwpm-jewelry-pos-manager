<?php
/**
 * Fired during plugin activation.
 *
 * یہ کلاس پلگ ان کی ایکٹیویشن لاجک کو ہینڈل کرتی ہے۔
 * اس میں رولز، صلاحیتیں (Capabilities) اور ڈیٹا بیس ٹیبلز بنانے کا عمل شامل ہے۔
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Activator {

	/**
	 * پلگ ان ایکٹیویشن پر چلنے والا فنکشن
	 *
	 * - کسٹم رولز (Roles) اور صلاحیتیں (Capabilities) ایڈ کرے گا۔
	 * - تمام ڈیٹا بیس ٹیبلز بنائے گا (JWPM_DB کلاس کے ذریعے)۔
	 * - ورژن آپشنز کو اپ ڈیٹ کرے گا۔
	 */
	public static function activate() {

		// 1. رولز اور Capabilities سیٹ کریں
		self::add_roles();

		// 2. ڈیٹا بیس ٹیبلز بنائیں
		// چونکہ ہم نے تمام ٹیبلز (Repairs, Accounts etc) کو JWPM_DB میں ضم کر دیا ہے،
		// لہذا صرف create_tables کو کال کرنا کافی ہے۔
		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'create_tables' ) ) {
			JWPM_DB::create_tables();
		}

		// 3. ورژن محفوظ کریں
		if ( defined( 'JWPM_VERSION' ) ) {
			update_option( 'jwpm_version', JWPM_VERSION );
		}
		if ( defined( 'JWPM_DB_VERSION' ) ) {
			// نوٹ: بہتر ہے کہ DB Version الگ define کیا جائے، ورنہ پلگ ان ورژن استعمال کریں۔
			update_option( 'jwpm_db_version', JWPM_VERSION );
		}
	}

	/**
	 * پلگ ان ڈی ایکٹیویشن (Deactivation)
	 *
	 * فی الحال یہاں کوئی خاص لاجک نہیں ہے، لیکن یہ فنکشن ہکس کے لیے ضروری ہے۔
	 * مستقبل میں یہاں Cron Jobs کو صاف کیا جا سکتا ہے۔
	 */
	public static function deactivate() {
		// Rewrite rules کو فلش کریں (اگر آپ Custom Post Types استعمال کر رہے ہوں)
		flush_rewrite_rules();
	}

	/**
	 * پلگ ان اَن انسٹال (Uninstall)
	 *
	 * - اگر فلٹر `jwpm_hard_delete_on_uninstall` TRUE ہو تو تمام ڈیٹا حذف ہو جائے گا۔
	 * - ورنہ ڈیٹا محفوظ رہے گا (Soft Uninstall)۔
	 */
	public static function uninstall() {

		/**
		 * فلٹر: کیا ان انسٹال پر تمام ڈیٹا ڈیلیٹ کرنا ہے؟
		 * Default: false (ڈیٹا محفوظ رہے گا)
		 */
		$hard_delete = apply_filters( 'jwpm_hard_delete_on_uninstall', false );

		if ( $hard_delete ) {

			// تمام ٹیبلز ڈراپ کریں (JWPM_DB کلاس کے ذریعے)
			if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'drop_tables' ) ) {
				JWPM_DB::drop_tables();
			}

			// آپشنز ڈیلیٹ کریں
			delete_option( 'jwpm_version' );
			delete_option( 'jwpm_db_version' );
		}
	}

	/**
	 * کسٹم رولز (Roles) اور صلاحیتیں (Capabilities) شامل کریں۔
	 *
	 * یہ فنکشن درج ذیل رولز بناتا ہے:
	 * - JWPM Owner (مکمل کنٹرول)
	 * - JWPM Manager
	 * - JWPM Salesperson
	 * - JWPM Accountant
	 * - JWPM Karigar
	 */
	public static function add_roles() {

		// 1. مالکانہ رول (Owner)
		add_role(
			'jwpm_owner',
			'JWPM Owner',
			array(
				'read'            => true,
				'manage_options'  => true, // WordPress Core Capability
				'manage_jwpm_all' => true, // Full JWPM Access
			)
		);

		// 2. منیجر (Manager)
		add_role(
			'jwpm_manager',
			'JWPM Manager',
			array(
				'read'                  => true,
				'manage_jwpm_sales'     => true,
				'manage_jwpm_inventory' => true,
				'manage_jwpm_accounts'  => true,
				'manage_jwpm_reports'   => true,
				'manage_jwpm_customers' => true,
				'manage_jwpm_orders'    => true,
				'manage_jwpm_repairs'   => true,
			)
		);

		// 3. سیلز پرسن (Salesperson)
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

		// 4. اکاؤنٹنٹ (Accountant)
		add_role(
			'jwpm_accountant',
			'JWPM Accountant',
			array(
				'read'                 => true,
				'manage_jwpm_accounts' => true,
				'manage_jwpm_reports'  => true,
			)
		);

		// 5. کاریگر / ورکشاپ (Karigar)
		add_role(
			'jwpm_karigar',
			'JWPM Karigar',
			array(
				'read'                => true,
				'manage_jwpm_repairs' => true,
			)
		);

		// 6. موجودہ ایڈمن (Administrator) کو بھی تمام JWPM صلاحیتیں دیں
		$admin = get_role( 'administrator' );
		if ( $admin instanceof WP_Role ) {

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
				if ( ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}
	}
}
