<?php
/**
 * Plugin Name: JWPM – Jewelry POS Manager
 * Description: Jewelry POS Management System for inventory, sales, customers, accounts and reporting inside WordPress (wp-admin).
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: jwpm-jewelry-pos-manager
 */

// یہ فائل پلگ اِن کا مین انٹری پوائنٹ ہے، یہاں سے تمام کلاسز، (hooks)، (menus) اور (assets) لوڈ ہوں گے۔
// نیچے ہم کانسٹنٹس، کلاس (includes)، ایکٹیویشن ہُکس اور ایڈمن (menus) رجسٹر کر رہے ہیں۔

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * بنیادی کانسٹنٹس
 */
define( 'JWPM_VERSION', '1.0.0' );
define( 'JWPM_DB_VERSION', '1.0.0' );
define( 'JWPM_PLUGIN_FILE', __FILE__ );
define( 'JWPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JWPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * ضروری کلاس فائلز لوڈ کریں
 */
require_once JWPM_PLUGIN_DIR . 'class-jwpm-activator.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-db.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-assets.php';
require_once JWPM_PLUGIN_DIR . 'class-jwpm-ajax.php';

/**
 * ایکٹیویشن، ڈی ایکٹیویشن اور اَن انسٹال ہُکس
 */
register_activation_hook( __FILE__, array( 'JWPM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JWPM_Activator', 'deactivate' ) );
register_uninstall_hook( __FILE__, 'jwpm_uninstall_plugin' );

/**
 * اَن انسٹال کال بیک
 */
function jwpm_uninstall_plugin() {
	if ( ! class_exists( 'JWPM_Activator' ) ) {
		require_once JWPM_PLUGIN_DIR . 'class-jwpm-activator.php';
	}

	JWPM_Activator::uninstall();
}

/**
 * مین پلگ اِن کلاس – اس کے ذریعے (menus)، (assets) اور (AJAX) ہُکس رجسٹر ہوں گے۔
 */
class JWPM_Jewelry_POS_Manager {

	/**
	 * @var JWPM_Jewelry_POS_Manager
	 */
	private static $instance = null;

	/**
	 * سنگلٹن انسٹینس حاصل کریں
	 *
	 * @return JWPM_Jewelry_POS_Manager
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * کنسٹرکٹر – پرائیویٹ تاکہ باہر سے نئی انسٹینس نہ بن سکے
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
		add_action( 'admin_enqueue_scripts', array( 'JWPM_Assets', 'enqueue_admin_assets' ) );
		add_action( 'init', array( 'JWPM_Ajax', 'register_ajax_hooks' ) );
	}

	/**
	 * (plugins_loaded) پر چلنے والی لاجک – یہاں (DB) ورژن وغیرہ چیک ہوں گے
	 */
	public function on_plugins_loaded() {
		JWPM_DB::maybe_upgrade();
	}

	/**
	 * ایڈمن (menus) اور سب (menus) رجسٹر کریں
	 */
	public function register_admin_menus() {

		// ٹاپ لیول (menu)
		$capability = 'manage_jwpm_sales';

		add_menu_page(
			__( 'JWPM Dashboard', 'jwpm-jewelry-pos-manager' ),
			__( 'JWPM POS', 'jwpm-jewelry-pos-manager' ),
			$capability,
			'jwpm-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-database',
			56
		);

		// ڈیش بورڈ (بعد میں بنے گا، ابھی سادہ پلیس ہولڈر)
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Dashboard', 'jwpm-jewelry-pos-manager' ),
			__( 'Dashboard', 'jwpm-jewelry-pos-manager' ),
			$capability,
			'jwpm-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		// (POS) – فی الحال پیج خالی ہوسکتا ہے، بعد میں بھرے گا
		add_submenu_page(
			'jwpm-dashboard',
			__( 'POS / Billing', 'jwpm-jewelry-pos-manager' ),
			__( 'POS', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_sales',
			'jwpm-pos',
			array( $this, 'render_pos_page' )
		);

		// اقساط
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Installments', 'jwpm-jewelry-pos-manager' ),
			__( 'Installments', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_sales',
			'jwpm-installments',
			array( $this, 'render_installments_page' )
		);

		// انوینٹری – ہمارا پہلا اصل فوکس
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Inventory / Stock', 'jwpm-jewelry-pos-manager' ),
			__( 'Inventory', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_inventory',
			'jwpm-inventory',
			array( $this, 'render_inventory_page' )
		);

		// پرچیز / سپلائر
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Purchases & Suppliers', 'jwpm-jewelry-pos-manager' ),
			__( 'Purchases', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_inventory',
			'jwpm-purchase',
			array( $this, 'render_purchase_page' )
		);

		// کسٹمرز
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Customers', 'jwpm-jewelry-pos-manager' ),
			__( 'Customers', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_customers',
			'jwpm-customers',
			array( $this, 'render_customers_page' )
		);

		// کسٹم آرڈرز
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Custom Orders', 'jwpm-jewelry-pos-manager' ),
			__( 'Custom Orders', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_orders',
			'jwpm-custom-orders',
			array( $this, 'render_custom_orders_page' )
		);

		// ریپیر جابز
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Repair Jobs', 'jwpm-jewelry-pos-manager' ),
			__( 'Repair Jobs', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_repairs',
			'jwpm-repair-jobs',
			array( $this, 'render_repair_jobs_page' )
		);

		// اکاؤنٹس
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Accounts & Cash Book', 'jwpm-jewelry-pos-manager' ),
			__( 'Accounts', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_accounts',
			'jwpm-accounts',
			array( $this, 'render_accounts_page' )
		);

		// رپورٹس
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Reports & Analytics', 'jwpm-jewelry-pos-manager' ),
			__( 'Reports', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_reports',
			'jwpm-reports',
			array( $this, 'render_reports_page' )
		);

		// سیٹنگز
		add_submenu_page(
			'jwpm-dashboard',
			__( 'JWPM Settings', 'jwpm-jewelry-pos-manager' ),
			__( 'Settings', 'jwpm-jewelry-pos-manager' ),
			'manage_jwpm_settings',
			'jwpm-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * نیچے ہر پیج کے لیے سادہ رینڈر فنکشن – بعد میں متعلقہ (admin/pages/*.php) شامل کیے جائیں گے
	 */

	public function render_dashboard_page() {
		$this->include_admin_page( 'jwpm-dashboard.php', 'jwpm-dashboard-root' );
	}

	public function render_pos_page() {
		$this->include_admin_page( 'jwpm-pos.php', 'jwpm-pos-root' );
	}

	public function render_installments_page() {
		$this->include_admin_page( 'jwpm-installments.php', 'jwpm-installments-root' );
	}

	public function render_inventory_page() {
		$this->include_admin_page( 'jwpm-inventory.php', 'jwpm-inventory-root' );
	}

	public function render_purchase_page() {
		$this->include_admin_page( 'jwpm-purchase.php', 'jwpm-purchase-root' );
	}

	public function render_customers_page() {
		$this->include_admin_page( 'jwpm-customers.php', 'jwpm-customers-root' );
	}

	public function render_custom_orders_page() {
		$this->include_admin_page( 'jwpm-custom-orders.php', 'jwpm-custom-orders-root' );
	}

	public function render_repair_jobs_page() {
		$this->include_admin_page( 'jwpm-repair-jobs.php', 'jwpm-repair-jobs-root' );
	}

	public function render_accounts_page() {
		$this->include_admin_page( 'jwpm-accounts.php', 'jwpm-accounts-root' );
	}

	public function render_reports_page() {
		$this->include_admin_page( 'jwpm-reports.php', 'jwpm-reports-root' );
	}

	public function render_settings_page() {
		$this->include_admin_page( 'jwpm-settings.php', 'jwpm-settings-root' );
	}

	/**
	 * مشترکہ ہیلپر: متعلقہ (admin/pages) فائل شامل کرے، اور روٹ (div) کی موجودگی یقینی بنائے
	 *
	 * @param string $file_name
	 * @param string $root_id
	 */
	private function include_admin_page( $file_name, $root_id ) {
		$path = JWPM_PLUGIN_DIR . 'admin/pages/' . $file_name;

		if ( file_exists( $path ) ) {
			include $path;
		} else {
			echo '<div class="notice notice-error"><p>';
			echo esc_html( sprintf( __( 'JWPM: Admin page file missing: %s', 'jwpm-jewelry-pos-manager' ), $file_name ) );
			echo '</p></div>';
			echo '<div id="' . esc_attr( $root_id ) . '"></div>';
		}
	}
}

/**
 * گلوبل فنکشن – آسانی سے مین کلاس انسٹینس حاصل کرنے کے لیے
 */
function jwpm() {
	return JWPM_Jewelry_POS_Manager::instance();
}

// پلگ اِن لوڈ کریں
jwpm();

// ✅ Syntax verified block end

