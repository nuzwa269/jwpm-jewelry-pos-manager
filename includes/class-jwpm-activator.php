<?php
/**
 * JWPM_Activator
 *
 * یہ کلاس پلگ ان کی ایکٹیویشن لاجک کو ہینڈل کرتی ہے۔
 * اس میں رولز، صلاحیتیں (Capabilities) اور ڈیٹا بیس ٹیبلز بنانے کا عمل شامل ہے۔
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Activator {

	/**
	 * پلگ ان ایکٹیویشن پر چلنے والا فنکشن
	 */
	public static function activate() {

		// 1. رولز اور Capabilities سیٹ کریں
		self::add_roles();

		// 2. ڈیٹا بیس ٹیبلز بنائیں
		if ( class_exists( 'JWPM_DB' ) && method_exists( 'JWPM_DB', 'create_tables' ) ) {
			JWPM_DB::create_tables();
		} 

		// 3. ورژن محفوظ کریں
		if ( defined( 'JWPM_VERSION' ) ) {
			update_option( 'jwpm_version', JWPM_VERSION );
		}
		if ( defined( 'JWPM_DB_VERSION' ) ) {
			// DB ورژن کو maybe_upgrade() کے بغیر یہاں سیٹ کرنا ضروری ہے
			update_option( 'jwpm_db_version', JWPM_DB_VERSION );
		} else {
			// Fallback اگر DB version define نہ ہو
			update_option( 'jwpm_db_version', JWPM_VERSION );
		}
		
		// 4. Rewrite rules کو فلش کریں (ضروری اگر آپ Admin Menu بنا رہے ہوں)
		flush_rewrite_rules();
	}

	/**
	 * پلگ ان ڈی ایکٹیویشن (Deactivation)
	 */
	public static function deactivate() {
		// Rewrite rules کو فلش کریں
		flush_rewrite_rules();
	}

	/**
	 * پلگ ان اَن انسٹال (Uninstall)
	 */
	public static function uninstall() {

		/**
		 * فلٹر: کیا ان انسٹال پر تمام ڈیٹا ڈیلیٹ کرنا ہے؟
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
			
			// رولز کو بھی ہٹانا ایک اچھی پریکٹس ہے (مستقبل میں شامل کیا جا سکتا ہے)
		}
	}

	/**
	 * کسٹم رولز (Roles) اور صلاحیتیں (Capabilities) شامل کریں۔
	 *
	 * Note: یہ لاجک check کرتی ہے کہ رول پہلے سے موجود نہ ہو تاکہ ایرر سے بچا جا سکے۔
	 */
	public static function add_roles() {

		// 1. مالکانہ رول (Owner)
		if ( null === get_role( 'jwpm_owner' ) ) {
			add_role(
				'jwpm_owner',
				'JWPM Owner',
				array(
					'read'            => true,
					'manage_options'  => true, // WordPress Core Capability
					'manage_jwpm_all' => true, // Full JWPM Access
				)
			);
		}

		// 2. منیجر (Manager)
		if ( null === get_role( 'jwpm_manager' ) ) {
			add_role(
				'jwpm_manager',
				'JWPM Manager',
				array(
					'read'                  => true,
					'manage_jwpm_sales'     => true,
					'manage_jwpm_inventory' => true,
					'manage_jwpm_accounts'  => true,
					'manage_jwpm_reports'   => true,
					'manage_jwpm_customers' => true,
					'manage_jwpm_orders'    => true,
					'manage_jwpm_repairs'   => true,
				)
			);
		}

		// 3. سیلز پرسن (Salesperson)
		if ( null === get_role( 'jwpm_salesperson' ) ) {
			add_role(
				'jwpm_salesperson',
				'JWPM Salesperson',
				array(
					'read'                  => true,
					'manage_jwpm_sales'     => true,
					'manage_jwpm_customers' => true,
					'manage_jwpm_orders'    => true,
				)
			);
		}

		// 4. اکاؤنٹنٹ (Accountant)
		if ( null === get_role( 'jwpm_accountant' ) ) {
			add_role(
				'jwpm_accountant',
				'JWPM Accountant',
				array(
					'read'                 => true,
					'manage_jwpm_accounts' => true,
					'manage_jwpm_reports'  => true,
				)
			);
		}

		// 5. کاریگر / ورکشاپ (Karigar)
		if ( null === get_role( 'jwpm_karigar' ) ) {
			add_role(
				'jwpm_karigar',
				'JWPM Karigar',
				array(
					'read'                => true,
					'manage_jwpm_repairs' => true,
				)
			);
		}

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
				// صرف وہی cap شامل کریں جو پہلے سے موجود نہیں
				if ( ! $admin->has_cap( $cap ) ) {
					$admin->add_cap( $cap );
				}
			}
		}
	}
}
