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

// === پلگ ان کے لیے Constants (ثوابت) تعریف کریں ===
define( 'JWPM_VERSION', '1.0.0' );
define( 'JWPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JWPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JWPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// === ضروری کلاسز کو لان کریں ===
require_once JWPM_PLUGIN_DIR . 'class-jwpm-activator.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-deactivator.php'; // یہ بھی ایک اچھی practice ہے
require_once JWPM_PLUGIN_DIR . 'class-jwpm-assets.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-ajax.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-db.php';

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

// === پلگ ان کی مرکزی کلاس کو شروع کریں ===
function jwpm_run_plugin() {
    // ڈیٹا بیس کلاس کا ایک instance بنائیں
    $jwpm_db = new JWPM_DB();

    // اثاثوں (Assets) کی کلاس کو شروع کریں
    new JWPM_Assets();

    // AJAX کی کلاس کو شروع کریں
    new JWPM_Ajax();

    // اگر ایڈمن پینل میں ہیں تو ایڈمن پیجز اور مینیو سیٹاپ کریں
    if ( is_admin() ) {
        // آپ یہاں ایک الگ فائل کو require کر سکتے ہیں جو مینیوز بنائے گی
        // require_once JWPM_PLUGIN_DIR . 'admin/class-jwpm-admin-menu.php';
        // new JWPM_Admin_Menu();
    }
}
// plugins_loaded ایکشن پر پلگ ان کو چلائیں
add_action( 'plugins_loaded', 'jwpm_run_plugin' );
