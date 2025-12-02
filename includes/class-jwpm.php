<?php
/**
 * The Main Plugin Class.
 *
 * یہ کلاس پلگ ان کے تمام حصوں (Admin, Loader, AJAX, Assets) کو آپس میں جوڑتی ہے۔
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
	 *
	 * @var JWPM_Loader
	 */
	protected $loader;

	/**
	 * پلگ ان کا منفرد نام (ID string)۔
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * پلگ ان کا موجودہ ورژن۔
	 *
	 * @var string
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
		$this->define_public_hooks();
	}

	/**
	 * ضروری فائلز اور کلاسز کو لوڈ کرنا۔
	 */
	private function load_dependencies() {
		// 1. لوڈر کلاس شروع کریں
		$this->loader = new JWPM_Loader();

		// 2. Assets (CSS/JS) کلاس شروع کریں
		// یہ کلاس اپنے کنسٹرکٹر میں ہی admin_enqueue_scripts کو ہک کر لے گی۔
		new JWPM_Assets();
	}

	/**
	 * لوکلائزیشن (زبان) سیٹ کریں۔
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	/**
	 * ٹیکسٹ ڈومین لوڈ کرنے کا کال بیک فنکشن۔
	 */
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

		// Admin Class کا انسٹینس بنائیں (صرف مینیو اور پیج رینڈرنگ کے لیے)
		$plugin_admin = new JWPM_Admin( $this->get_plugin_name(), $this->get_version() );

		// نوٹ: ہم نے یہاں سے enqueue_scripts کی لائنز ہٹا دی ہیں
		// کیونکہ اب یہ کام JWPM_Assets کلاس خود بخود کر رہی ہے۔

		// 1. Admin Menu Creation
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_menu_items' );

		// 2. AJAX Hooks Registration
		if ( class_exists( 'JWPM_Ajax' ) ) {
    $ajax_instance = new JWPM_Ajax();
    $this->loader->add_action( 'init', array( 'JWPM_Ajax', 'register_ajax_hooks' ) );
}
	}

	/**
	 * پبلک (Front-end) سائڈ کے ہکس (فی الحال خالی ہے)۔
	 */
	private function define_public_hooks() {
		// فی الحال کوئی پبلک ہکس نہیں ہیں
	}

	/**
	 * پلگ ان کو چلائیں (Run)۔
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * پلگ ان کا نام حاصل کریں۔
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * پلگ ان کا ورژن حاصل کریں۔
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
