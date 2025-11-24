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
	 */
	public static function activate() {
		// رولز اور (capabilities)
		self::add_roles();

		// (DB) ٹیبلز
		JWPM_DB::create_tables();

		// ورژن سیو کریں
		update_option( 'jwpm_version', JWPM_VERSION );
		update_option( 'jwpm_db_version', JWPM_DB_VERSION );
	}

	/**
	 * پلگ اِن ڈی ایکٹیویشن – ابھی لائٹ لاجک، مستقبل میں کرون وغیرہ کلئیر ہو سکتے ہیں
	 */
	public static function deactivate() {
		// فی الحال کچھ خاص نہیں، لیکن ہُکس کے لیے پلیس ہولڈر رکھا ہے۔
	}

	/**
	 * پلگ اِن اَن انسٹال – سیٹنگ کے مطابق مکمل صفائی
	 */
	public static function uninstall() {

		$hard_delete = apply_filters( 'jwpm_hard_delete_on_uninstall', false );

		if ( $hard_delete ) {
			// تمام (JWPM) ٹیبلز ڈراپ کریں
			JWPM_DB::drop_tables();

			// آپشنز ڈیلیٹ کریں
			delete_option( 'jwpm_version' );
			delete_option( 'jwpm_db_version' );
		}
	}

	/**
	 * کسٹم (roles) اور (capabilities) ایڈ کریں
	 */
	public static function add_roles() {
		// مالکانہ رول
		add_role(
			'jwpm_owner',
			'JWPM Owner',
			array(
				'read'             => true,
				'manage_options'   => true,
				'manage_jwpm_all'  => true,
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

		// کاریگر
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
	}
}

// ✅ Syntax verified block end
