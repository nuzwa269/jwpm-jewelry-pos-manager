<?php
/**
 * Plugin Name:       JWPM Jewelry POS Manager
 * Plugin URI:        https://example.com/
 * Description:       A complete Point of Sale and management system for jewelry businesses.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jwpm-jewelry-pos-manager
 * Domain Path:       /languages
 */

// اگر کوئی اس فائل کو براہ راست ایکسس کرنے کی کوشش کرے تو روک دیں
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// === پلگ ان کے لیے Constants (ثوابت) تعریف کریں ===
define( 'JWPM_VERSION', '1.0.0' );
define( 'JWPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JWPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JWPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );


// 🟢 یہاں سے [Core File Requirements] شروع ہو رہا ہے
// === ضروری کلاسز کو لان کریں ===
require_once JWPM_PLUGIN_DIR . 'class-jwpm-activator.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-deactivator.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-db.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-assets.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-ajax.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-admin.php'; // 👈 Admin Menu Fix!
// 🔴 یہاں پر [Core File Requirements] ختم ہو رہا ہے


// === پلگ ان کو فعال/غیر فعال کرنے کے لیے Hooks رجسٹر کریں ===
register_activation_hook( __FILE__, array( 'JWPM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JWPM_Deactivator', 'deactivate' ) );


// === زبان کی فائلیں لوڈ کریں (Internationalization) ===
add_action( 'plugins_loaded', 'jwpm_load_textdomain' );
function jwpm_load_textdomain() {
	load_plugin_textdomain(
		'jwpm-jewelry-pos-manager', // آپ کا Text Domain
		false,
		dirname( JWPM_PLUGIN_BASENAME ) . '/languages/'
	);
}

// 🟢 یہاں سے [Core Plugin Initialization] شروع ہو رہا ہے
/**
 * پلگ ان کی مرکزی کلاس کو شروع کریں اور تمام ضروری ہکس کو وائر (Wire) کریں۔
 */
function jwpm_run_plugin() {

	// 1. ڈیٹا بیس کلاس کا ایک instance بنائیں (تاکہ یہ پوری ایپلیکیشن میں دستیاب ہو)
	// $jwpm_db = new JWPM_DB(); // DB Helper صرف Functions کو expose کر سکتا ہے، اسے صرف require کرنا کافی ہے۔

	// 2. اثاثوں (Assets) کی کلاس کو شروع کریں (جو enqueue_admin_assets کو ہک کرے گی)
	new JWPM_Assets();

	// 3. AJAX کی کلاس کو شروع کریں (جو wp_ajax_* ہکس کو رجسٹر کرے گی)
	// AJAX کلاس کے constructor میں ہی register_ajax_hooks کو کال ہونا چاہیے
	new JWPM_Ajax();

	// 4. ایڈمن پیجز اور مینیوز سیٹاپ کریں (Admin Menu Fix!)
	if ( is_admin() ) {
		new JWPM_Admin();
	}
}
// plugins_loaded ایکشن پر پلگ ان کو چلائیں
add_action( 'plugins_loaded', 'jwpm_run_plugin' );
// 🔴 یہاں پر [Core Plugin Initialization] ختم ہو رہا ہے

// ✅ Syntax verified block end
