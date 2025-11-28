<?php
/**
 * Admin menu and page bootstrapping for JWPM.
 *
 * یہ کلاس WordPress admin_menu ہک کو استعمال کرتے ہوئے مرکزی پلگ ان مینو اور
 * اس کے تمام ذیلی صفحات (Submenus) کو رجسٹر کرتی ہے۔
 * یہ ہر صفحے کے لیے ایک منفرد روٹ کنٹینر (<div id="jwpm-*-root">) فراہم کرتی ہے
 * تاکہ فرنٹ اینڈ (JavaScript) اس میں اپنا UI لوڈ کر سکے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Admin {

	/**
	 * کنسٹرکٹر۔ یہاں admin_menu ہک کو رجسٹر کیا جاتا ہے۔
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
	}

	/**
	 * مرکزی JWPM مینو اور تمام ذیلی مینیوز کو رجسٹر کریں۔
	 */
	public function register_menus() {
		// عارضی کیپبلٹی: فی الحال 'manage_options' استعمال کریں گے، بعد میں کسٹم رولز سے بدلیں گے۔
		$capability = 'manage_options'; 

		// 1. مرکزی مینو پیج (Top Level Menu)
		add_menu_page(
			__( 'JWPM POS Manager', 'jwpm-jewelry-pos-manager' ),
			__( 'JWPM POS', 'jwpm-jewelry-pos-manager' ),
			$capability,
			'jwpm-dashboard', // مرکزی slug
			array( $this, 'render_page' ),
			'dashicons-store', // مینیو آئیکن
			26
		);

		// 2. ذیلی صفحات (Submenus)
		// نوٹ: پہلا صفحہ (Dashboard) مرکزی مینیو کے طور پر بھی استعمال ہو رہا ہے۔
		$pages = array(
			'jwpm-dashboard' 	  => __( 'Dashboard', 'jwpm-jewelry-pos-manager' ),
			'jwpm-pos' 			  => __( 'Point of Sale', 'jwpm-jewelry-pos-manager' ),
			'jwpm-inventory' 	  => __( 'Inventory', 'jwpm-jewelry-pos-manager' ),
			'jwpm-customers' 	  => __( 'Customers', 'jwpm-jewelry-pos-manager' ),
			'jwpm-installments'   => __( 'Installments', 'jwpm-jewelry-pos-manager' ),
			'jwpm-purchase' 	  => __( 'Purchase', 'jwpm-jewelry-pos-manager' ),
			'jwpm-custom-orders'  => __( 'Custom Orders', 'jwpm-jewelry-pos-manager' ),
			'jwpm-repairs' 		  => __( 'Repairs', 'jwpm-jewelry-pos-manager' ),
			'jwpm-accounts' 	  => __( 'Accounts', 'jwpm-jewelry-pos-manager' ),
			'jwpm-reports' 		  => __( 'Reports', 'jwpm-jewelry-pos-manager' ),
			'jwpm-settings' 	  => __( 'Settings', 'jwpm-jewelry-pos-manager' ),
		);

		foreach ( $pages as $slug => $title ) {
			// ڈیش بورڈ کو چھپانے کے لیے، اسے ایک بار skip کر سکتے ہیں
			// لیکن اسے sub-menu میں رکھنے سے اس کا ٹائٹل مرکزی slug سے مطابقت رکھتا ہے۔
			add_submenu_page(
				'jwpm-dashboard', // Parent Slug
				$title,
				$title,
				$capability,
				$slug,
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * پیج کے مواد کو رینڈر کرتا ہے، صرف JavaScript کے لیے ایک روٹ کنٹینر فراہم کرتا ہے۔
	 * (اصول 4: ہر پیج Root)
	 */
	public function render_page() {
		// موجودہ slug حاصل کریں اور اسے sanitize کریں
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'jwpm-dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		// روٹ ID بنائیں: مثال کے طور پر 'jwpm-inventory-root'
		$root_id = sprintf( 'jwpm-%s-root', str_replace( 'jwpm-', '', $page ) );
		
		echo '<div class="wrap">';
		// مرکزی div جہاں JavaScript UI لوڈ ہو گا
		printf( '<div id="%s"></div>', esc_attr( $root_id ) );
		echo '</div>';
	}
}
