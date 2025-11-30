<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * یہ کلاس Admin Area کے تمام UI، مینیوز اور Assets کو سنبھالتی ہے۔
 * یہ ہر صفحے کے لیے ایک Root Element فراہم کرتی ہے تاکہ (JavaScript) وہاں لوڈ ہو سکے۔
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Admin {

	/**
	 * پلگ ان کا نام (ID)۔
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * پلگ ان کا ورژن۔
	 *
	 * @var string
	 */
	private $version;

	/**
	 * کلاس کنسٹرکٹر
	 *
	 * @param string $plugin_name پلگ ان کا نام۔
	 * @param string $version     پلگ ان کا ورژن۔
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * ایڈمن (CSS) رجسٹر اور Enqueue کریں۔
	 */
	public function enqueue_styles() {
		// اگر آپ کو global admin (CSS) چاہیے ہو تو یہاں enqueue کریں۔
		// wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/jwpm-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * ایڈمن (JavaScript) رجسٹر اور Enqueue کریں۔
	 */
	public function enqueue_scripts() {
		// اگر آپ کو global admin (JS) چاہیے ہو تو یہاں enqueue کریں۔
		// wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/jwpm-admin.js', array( 'jquery' ), $this->version, false );
		
		// مثال کے طور پر:
		/*
		wp_localize_script(
			$this->plugin_name,
			'jwpmScript',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'jwpm_nonce' ),
			)
		);
		*/
	}

	/**
	 * مرکزی JWPM مینو اور تمام ذیلی مینیوز (Submenus) کو رجسٹر کریں۔
	 * نوٹ: یہ فنکشن Loader کے ذریعے 'admin_menu' ہک پر کال ہوتا ہے۔
	 */
	public function add_menu_items() {

		// فی الحال 'manage_options' (صرف Administrator)
		// بعد میں آپ custom capability مثلاً 'manage_jwpm_all' استعمال کر سکتے ہیں۔
		$capability = 'manage_options';

		// 1. مرکزی مینو پیج (Top Level Menu)
		add_menu_page(
			__( 'JWPM POS Manager', 'jwpm-jewelry-pos-manager' ), // Page Title
			__( 'JWPM POS', 'jwpm-jewelry-pos-manager' ),         // Menu Title
			$capability,
			'jwpm-dashboard',                                     // Slug
			array( $this, 'render_page' ),                        // Callback
			'dashicons-store',                                    // Icon
			26                                                    // Position
		);

		// 2. عام ذیلی صفحات (Submenus) کی فہرست (POS کو یہاں سے الگ رکھیں گے)
		$pages = array(
			'jwpm-dashboard'     => __( 'Dashboard', 'jwpm-jewelry-pos-manager' ),
			'jwpm-inventory'     => __( 'Inventory', 'jwpm-jewelry-pos-manager' ),
			'jwpm-customers'     => __( 'Customers', 'jwpm-jewelry-pos-manager' ),
			'jwpm-installments'  => __( 'Installments', 'jwpm-jewelry-pos-manager' ),
			'jwpm-purchase'      => __( 'Purchase', 'jwpm-jewelry-pos-manager' ),
			'jwpm-custom-orders' => __( 'Custom Orders', 'jwpm-jewelry-pos-manager' ),
			'jwpm-repairs'       => __( 'Repairs', 'jwpm-jewelry-pos-manager' ),
			'jwpm-accounts'      => __( 'Accounts', 'jwpm-jewelry-pos-manager' ),
			'jwpm-reports'       => __( 'Reports', 'jwpm-jewelry-pos-manager' ),
			'jwpm-settings'      => __( 'Settings', 'jwpm-jewelry-pos-manager' ),
		);

		foreach ( $pages as $slug => $title ) {
			add_submenu_page(
				'jwpm-dashboard',                 // Parent Slug
				$title,                           // Page Title
				$title,                           // Menu Title
				$capability,                      // Capability
				$slug,                            // Menu Slug
				array( $this, 'render_page' )     // Generic Callback Function
			);
		}

		// 3. POS Page — الگ callback کے ساتھ تاکہ ہمارا custom layout لوڈ ہو (admin/pages/jwpm-pos.php)
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Point of Sale', 'jwpm-jewelry-pos-manager' ),
			__( 'Point of Sale', 'jwpm-jewelry-pos-manager' ),
			$capability,
			'jwpm-pos',                        // 👈 یہی slug URL میں استعمال ہو رہا ہے
			array( $this, 'render_pos_page' )  // 👈 POS کے لیے مخصوص callback
		);
	}

	/**
	 * Default / Generic پیج رینڈرر۔
	 * یہ صرف ایک خالی `div` بناتا ہے جسے (JavaScript) (React/Vue/jQuery) پُر کرے گا۔
	 */
	public function render_page() {
		
		// URL سے موجودہ پیج کا slug حاصل کریں
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'jwpm-dashboard';

		// روٹ ID بنائیں (مثال: jwpm-inventory-root)
		$clean_slug = str_replace( 'jwpm-', '', $page );
		
		// اگر ڈیش بورڈ ہے تو اسے dashboard ہی رہنے دیں
		if ( 'dashboard' === $clean_slug || empty( $clean_slug ) ) {
			$root_id = 'jwpm-dashboard-root';
		} else {
			$root_id = sprintf( 'jwpm-%s-root', $clean_slug );
		}

		?>
		<div class="wrap" id="jwpm-admin-app-wrapper">
			<div id="<?php echo esc_attr( $root_id ); ?>">
				<h1><?php esc_html_e( 'Loading JWPM...', 'jwpm-jewelry-pos-manager' ); ?></h1>
				<p><?php esc_html_e( 'If this takes too long, please check your JavaScript console.', 'jwpm-jewelry-pos-manager' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * POS Page کے لیے مخصوص رینڈرر۔
	 * یہ براہِ راست admin/pages/jwpm-pos.php لوڈ کرتا ہے۔
	 */
	public function render_pos_page() {
		include JWPM_PLUGIN_DIR . 'admin/pages/jwpm-pos.php';
	}
}

// ✅ Syntax verified block end
