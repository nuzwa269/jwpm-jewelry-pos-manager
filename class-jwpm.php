<?php
/**
 * The Main Plugin Class.
 *
 * یہ کلاس پلگ ان کے تمام حصوں (Admin, Loader, AJAX) کو آپس میں جوڑتی ہے۔
 * اسے ہم "The Brain" آف پلگ ان کہہ سکتے ہیں۔
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM {

	/**
	 * لوڈر کلاس کا انسٹینس جو ہکس کو سنبھالے گا۔
	 */
	protected $loader;

	/**
	 * پلگ ان کا منفرد نام (ID string)۔
	 */
	protected $plugin_name;

	/**
	 * پلگ ان کا موجودہ ورژن۔
	 */
	protected $version;

	/**
	 * کلاس کنسٹرکٹر
	 *
	 * بنیادی سیٹنگز اور ہکس کو لوڈ کرتا ہے۔
	 */
	public function __construct() {
		if ( defined( 'JWPM_VERSION' ) ) {
			$this->version = JWPM_VERSION;
		} else {
			$this->version = '1.0.0';
		}

		$this->plugin_name = 'jwpm-jewelry-pos-manager';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks(); // اگر فرنٹ اینڈ (Shortcodes) ہو تو یہاں آئے گا
	}

	/**
	 * ضروری فائلز اور کلاسز کو لوڈ کرنا۔
	 */
	private function load_dependencies() {

		// لوڈر کلاس (جو ہم نے پچھلے سٹیپ میں بنائی تھی)
		// فرض کریں کہ یہ فائل پہلے ہی مین فائل میں require ہو چکی ہے۔
		$this->loader = new JWPM_Loader();

	}

	/**
	 * لوکلائزیشن (زبان) سیٹ کریں۔
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'jwpm-jewelry-pos-manager',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * ایڈمن سائڈ کے تمام ہکس (Hooks) یہاں رجسٹر کریں۔
	 */
	private function define_admin_hooks() {

		// Admin Class کا انسٹینس بنائیں
		$plugin_admin = new JWPM_Admin( $this->get_plugin_name(), $this->get_version() );

		// 1. CSS/JS Assets (Admin Class کے اندر فنکشن ہونا چاہیے)
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// 2. Admin Menu
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu_items' );

		// 3. AJAX Hooks
		// چونکہ آپ کی AJAX کلاس static میتھڈز استعمال کر رہی ہے، ہم اسے یہاں بھی رجسٹر کر سکتے ہیں
		// یا جیسے آپ نے پہلے کیا تھا، اسے الگ سے بھی رہنے دیا جا سکتا ہے۔
		// بہترین طریقہ یہ ہے کہ اسے لوڈر کے ذریعے چلایا جائے:
		if ( class_exists( 'JWPM_Ajax' ) ) {
			// ہم 'init' پر AJAX ہکس رجسٹر کر رہے ہیں
			$this->loader->add_action( 'init', 'JWPM_Ajax', 'register_ajax_hooks' );
		}
	}

	/**
	 * پبلک (Front-end) سائڈ کے ہکس (فی الحال خالی ہے)۔
	 */
	private function define_public_hooks() {
		// مثال: Shortcodes وغیرہ
		// $plugin_public = new JWPM_Public( $this->get_plugin_name(), $this->get_version() );
		// $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
	}

	/**
	 * پلگ ان کو چلائیں (Run)۔
	 * یہ لوڈر کے run() فنکشن کو کال کرتا ہے جو تمام ہکس کو ورڈپریس میں داخل کرتا ہے۔
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * پلگ ان کا نام حاصل کریں۔
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * پلگ ان کا ورژن حاصل کریں۔
	 */
	public function get_version() {
		return $this->version;
	}
}
