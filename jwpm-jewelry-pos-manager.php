<?php
/**
 * Plugin Name:       JWPM Jewelry POS Manager
 * Plugin URI:        https://example.com/
 * Description:       A complete Point of Sale and management system for jewelry businesses.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jwpm-jewelry-pos-manager
 * Domain Path:       /languages
 */

// اگر کوئی اس فائل کو براہ راست ایکسس کرنے کی کوشش کرے تو روک دیں
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * --------------------------------------------------------------------------
 * 1. Constants Definition
 * --------------------------------------------------------------------------
 */
define( 'JWPM_VERSION', '1.0.0' );
define( 'JWPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JWPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JWPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * --------------------------------------------------------------------------
 * 2. Core File Requirements
 * --------------------------------------------------------------------------
 */
require_once JWPM_PLUGIN_DIR . 'class-jwpm-activator.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-deactivator.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-db.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-assets.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-ajax.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-admin.php';

/**
 * --------------------------------------------------------------------------
 * 3. Activation & Deactivation Hooks
 * --------------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( 'JWPM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JWPM_Deactivator', 'deactivate' ) );

/**
 * --------------------------------------------------------------------------
 * 4. Localization (Language Support)
 * --------------------------------------------------------------------------
 */
add_action( 'plugins_loaded', 'jwpm_load_textdomain' );
function jwpm_load_textdomain() {
	load_plugin_textdomain(
		'jwpm-jewelry-pos-manager',
		false,
		dirname( JWPM_PLUGIN_BASENAME ) . '/languages/'
	);
}

/**
 * --------------------------------------------------------------------------
 * 5. Main Plugin Initialization
 * --------------------------------------------------------------------------
 * پلگ ان کی مرکزی کلاسز کو شروع کریں اور ہکس کو وائر (Wire) کریں۔
 */
function jwpm_run_plugin() {

	// 1. اثاثوں (Assets: CSS/JS) کی کلاس کو شروع کریں
	new JWPM_Assets();

	// 2. AJAX ہینڈلرز کو رجسٹر کریں
	// نوٹ: پچھلی فائل میں ہم نے static میتھڈ بنایا تھا، اس لیے اسے یہاں کال کر رہے ہیں۔
	if ( class_exists( 'JWPM_Ajax' ) ) {
		JWPM_Ajax::register_ajax_hooks();
	}

	// 3. ایڈمن پیجز اور مینیوز سیٹاپ کریں
	if ( is_admin() ) {
		new JWPM_Admin();
	}
}

// plugins_loaded ایکشن پر پلگ ان کو چلائیں
add_action( 'plugins_loaded', 'jwpm_run_plugin' );
