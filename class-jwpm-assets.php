<?php
/**
 * JWPM_Assets
 *
 * یہ کلاس (admin) سائیڈ پر تمام (JS) اور (CSS) اسٹس کو رجسٹر اور لوڈ کرتی ہے۔
 * اسی میں ہم گلوبل (jwpmCommon) اور پیج اسپیسفک ڈیٹا (nonces وغیرہ) بھی (localize) کریں گے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Assets {

	/**
	 * (admin_enqueue_scripts) ہُک سے کال ہونے والا فنکشن
	 *
	 * @param string $hook موجودہ ایڈمن پیج ہُک۔
	 */
	public static function enqueue_admin_assets( $hook ) {

		// صرف ہمارے (JWPM) کے مینو پیجز پر لوڈ ہو:
		if ( strpos( $hook, 'jwpm-' ) === false ) {
			return;
		}

		$version = defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time();

		// 🟢 یہاں سے [Common Assets] شروع ہو رہا ہے
		// (CSS)
		wp_register_style(
			'jwpm-common-css',
			JWPM_PLUGIN_URL . 'jwpm-common.css',
			array(),
			$version
		);

		wp_enqueue_style( 'jwpm-common-css' );

		// (JS)
		wp_register_script(
			'jwpm-common-js',
			JWPM_PLUGIN_URL . 'jwpm-common.js',
			array( 'jquery' ),
			$version,
			true
		);

		wp_enqueue_script( 'jwpm-common-js' );

		// گلوبل (localize) آبجیکٹ
		$global_data = array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'nonce_common'  => wp_create_nonce( 'jwpm_common_nonce' ),
			'plugin_url'    => JWPM_PLUGIN_URL,
			'current_user'  => get_current_user_id(),
			'current_time'  => current_time( 'mysql' ),
			'i18n'          => array(
				'error_generic' => __( 'Unexpected error occurred. Please try again.', 'jwpm-jewelry-pos-manager' ),
				'saving'        => __( 'Saving...', 'jwpm-jewelry-pos-manager' ),
				'loading'       => __( 'Loading...', 'jwpm-jewelry-pos-manager' ),
			),
		);

		wp_localize_script(
			'jwpm-common-js',
			'jwpmCommon',
			$global_data
		);
		// 🔴 یہاں پر [Common Assets] ختم ہو رہا ہے

		// اب دیکھتے ہیں کون سا پیج کھلا ہوا ہے تاکہ متعلقہ (JS/CSS) لوڈ کریں۔
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// 🟢 یہاں سے [Inventory Page Assets] شروع ہو رہا ہے
		if ( 'jwpm-inventory' === $page ) {

			// پیج اسپیسفک (CSS)
			wp_register_style(
				'jwpm-inventory-css',
				JWPM_PLUGIN_URL . 'assets/css/jwpm-inventory.css',
				array( 'jwpm-common-css' ),
				$version
			);
			wp_enqueue_style( 'jwpm-inventory-css' );

			// پیج اسپیسفک (JS)
			wp_register_script(
				'jwpm-inventory-js',
				JWPM_PLUGIN_URL . 'assets/js/jwpm-inventory.js',
				array( 'jwpm-common-js', 'jquery' ),
				$version,
				true
			);
			wp_enqueue_script( 'jwpm-inventory-js' );

			// انوینٹری کے لیے خاص (nonce + settings)
			$inventory_data = array(
				'nonce'          => wp_create_nonce( 'jwpm_inventory_nonce' ),
				'page'           => $page,
				'list_action'    => 'jwpm_inventory_list_items',
				'save_action'    => 'jwpm_inventory_save_item',
				'delete_action'  => 'jwpm_inventory_delete_item',
				'import_action'  => 'jwpm_inventory_import_items',
				'export_action'  => 'jwpm_inventory_export_items',
				'demo_action'    => 'jwpm_inventory_demo_items',
				'per_page'       => 50,
				'default_branch' => self::get_default_branch_id(),
			);

			wp_localize_script(
				'jwpm-inventory-js',
				'jwpmInventoryData',
				$inventory_data
			);
		}
		// 🔴 یہاں پر [Inventory Page Assets] ختم ہو رہا ہے
	}

	/**
	 * ڈیفالٹ برانچ حاصل کرنے کے لیے ہیلپر
	 *
	 * @return int
	 */
	protected static function get_default_branch_id() {
		global $wpdb;

		$tables = JWPM_DB::get_table_names();

		$branch_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id FROM {$tables['branches']} WHERE is_default = 1 ORDER BY id ASC LIMIT 1"
		);

		if ( $branch_id > 0 ) {
			return $branch_id;
		}

		// اگر کوئی ڈیفالٹ برانچ نہیں، پہلے والی برانچ لے لیں
		$branch_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id FROM {$tables['branches']} ORDER BY id ASC LIMIT 1"
		);

		return $branch_id > 0 ? $branch_id : 0;
	}
}

// ✅ Syntax verified block end
