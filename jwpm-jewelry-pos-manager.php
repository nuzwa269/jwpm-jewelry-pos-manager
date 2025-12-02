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

// 1. Direct Access Security
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 2. Constants Definition
// === پلگ ان کے لیے Constants (ثوابت) تعریف کریں ===
define( 'JWPM_VERSION', '1.0.0' );
define( 'JWPM_DB_VERSION', '1.0.0' ); // 👈 DB ورژن بھی شامل کیا گیا
define( 'JWPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JWPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JWPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// 3. Require Core Files (ترتیب بہت اہم ہے)
// ہم تمام فائلز کو includes فولڈر سے اٹھا رہے ہیں۔

// A. ہیلپرز اور ڈیٹا بیس
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-db.php';
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-assets.php'; // 👈 یہ فائل آپ کے کوڈ میں نہیں تھی، اس لیے ایرر آ رہا تھا
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-ajax.php';

// B. کور سٹرکچر
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-loader.php';
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-admin.php';

// C. ایکٹیویشن / ڈی ایکٹیویشن کلاسز
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-activator.php';
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-deactivator.php';

// D. مین کلاس (Main Class)
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm.php';


// 4. Activation & Deactivation Hooks
function activate_jwpm_jewelry_pos_manager() {
	JWPM_Activator::activate();
}
function deactivate_jwpm_jewelry_pos_manager() {
	JWPM_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_jwpm_jewelry_pos_manager' );
register_deactivation_hook( __FILE__, 'deactivate_jwpm_jewelry_pos_manager' );


// 5. Run the Plugin
function jwpm_run_plugin() {
	$plugin = new JWPM();
	$plugin->run();
}
add_action( 'plugins_loaded', 'jwpm_run_plugin' );
