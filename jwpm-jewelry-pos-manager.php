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



/** Part 6 — Settings Page PHP Loader (AJAX Context Only)
 * یہ بلاک صرف (admin-ajax.php) ریکویسٹ کے دوران
 * Settings Page والی (PHP) فائل (admin/pages/jwpm-settings.php) کو include کرتا ہے،
 * تاکہ اس کے اندر موجود (AJAX) فنکشنز دستیاب ہوں۔
 *
 * اہم بات:
 * - ہم اسے صرف DOING_AJAX کے دوران include کر رہے ہیں
 * - اس طرح jwpm_register_settings_page() والا پرانا menu hook
 *   normal admin menu میں ڈسٹرب نہیں کرے گا۔
 */

// 🟢 یہاں سے [Settings Page PHP Loader] شروع ہو رہا ہے

if ( is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX ) {
	$jwpm_settings_path = trailingslashit( JWPM_PLUGIN_DIR ) . 'admin/pages/jwpm-settings.php';

	if ( file_exists( $jwpm_settings_path ) ) {
		require_once $jwpm_settings_path;
	}
}

// 🔴 یہاں پر [Settings Page PHP Loader] ختم ہو رہا ہے

// ✅ Syntax verified block end



/** Part 7 — Settings Page Assets Loader (JS + CSS)
 * یہ فنکشن صرف Settings Page (?page=jwpm-settings) پر
 * مخصوص (JavaScript) اور (CSS) فائلز لوڈ کرتا ہے۔
 *
 * موجودہ کلاسز (JWPM_Admin وغیرہ) میں کوئی تبدیلی نہیں کی گئی،
 * صرف نیا فنکشن اور نیا hook add کیا گیا ہے۔
 */

// 🟢 یہاں سے [Settings Page Assets Loader] شروع ہو رہا ہے

function jwpm_enqueue_settings_assets( $hook_suffix ) {

	// صرف ایڈمن ایریا کے لیے
	if ( ! is_admin() ) {
		return;
	}

	// موجودہ پیج کا slug نکالیں (?page= سے)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	// اگر یہ Settings Page نہیں ہے تو کچھ نہیں کریں
	if ( 'jwpm-settings' !== $page ) {
		return;
	}

	// (JavaScript) فائل
	$js_handle = 'jwpm-settings-js';
	$js_src    = trailingslashit( JWPM_PLUGIN_URL ) . 'assets/js/jwpm-settings.js';

	// (CSS) فائل
	$css_handle = 'jwpm-settings-css';
	$css_src    = trailingslashit( JWPM_PLUGIN_URL ) . 'assets/css/jwpm-settings.css';

	// (JavaScript) enqueue
	wp_enqueue_script(
		$js_handle,
		$js_src,
		array( 'jquery' ),
		JWPM_VERSION,
		true
	);

	// (CSS) enqueue
	wp_enqueue_style(
		$css_handle,
		$css_src,
		array(),
		JWPM_VERSION
	);

	// Settings Page کے لیے nonce + actions JS تک بھیجیں
	$nonce = wp_create_nonce( 'jwpm_settings_nonce' );

	wp_localize_script(
		$js_handle,
		'jwpmSettings',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => $nonce,
			'rootId'  => 'jwpm-settings-root',
			'actions' => array(
				'fetch'          => 'jwpm_get_settings',
				'save'           => 'jwpm_save_settings',
				'demo_load'      => 'jwpm_load_demo_settings',
				'reset_settings' => 'jwpm_reset_settings',
				'backup_export'  => 'jwpm_export_settings_backup',
				'logo_upload'    => 'jwpm_upload_logo',
				'logo_remove'    => 'jwpm_remove_logo',
			),
			'i18n' => array(
				'noLogo'        => 'کوئی لوگو منتخب نہیں ہوا۔',
				'logoSaved'     => 'لوگو کامیابی سے محفوظ ہو گیا۔',
				'logoRemoved'   => 'لوگو ہٹا دیا گیا ہے۔',
				'saved'         => 'سیٹنگز محفوظ ہو گئیں۔',
				'languageSaved' => 'زبان محفوظ ہو گئی، براہ کرم صفحہ ری فریش کریں۔',
				'error'         => 'کچھ خرابی ہوئی، براہ کرم دوبارہ کوشش کریں۔',
				'demoConfirm'   => 'Demo Settings لوڈ ہونے سے موجودہ سیٹنگز اوور رائٹ ہوں گی، کیا آپ پُر عزم ہیں؟',
				'resetConfirm'  => 'یہ عمل Settings کو default حالت میں لے آئے گا، کیا آپ واقعی ری سیٹ کرنا چاہتے ہیں؟',
				'backupReady'   => 'Backup تیار ہے، فائل ڈاؤن لوڈ ہو رہی ہے۔',
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'jwpm_enqueue_settings_assets' );

// 🔴 یہاں پر [Settings Page Assets Loader] ختم ہو رہا ہے

// ✅ Syntax verified block end
