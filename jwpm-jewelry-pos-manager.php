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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Constants
define( 'JWPM_VERSION', '1.0.0' );
define( 'JWPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JWPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JWPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// 2. Activation / Deactivation Hooks
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-activator.php';
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-deactivator.php';

register_activation_hook( __FILE__, array( 'JWPM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JWPM_Deactivator', 'deactivate' ) );

// 3. Require Core Class Files (The "Includes" folder concept)
// نوٹ: بہتر ہے کہ تمام کلاسز includes فولڈر میں ہوں۔
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-db.php';
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-ajax.php';
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-loader.php'; // 👈 New Loader
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-admin.php';
require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm.php';        // 👈 New Main Class

// 4. Run the Plugin
function jwpm_run_plugin() {
	$plugin = new JWPM();
	$plugin->run();
}
add_action( 'plugins_loaded', 'jwpm_run_plugin' );
