<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * یہ کلاس Admin Area کے تمام UI، مینیوز اور Assets کو سنبھالتی ہے۔
 * ہر پیج کے لیے Root Element بناتی ہے (except وہ pages جو اپنی custom PHP template لوڈ کرتے ہیں جیسے POS, Inventory وغیرہ)۔
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
	 *
	 * یہاں ہم:
	 * - Dashboard, Settings وغیرہ کے لیے generic render_page() استعمال کریں گے
	 * - POS کے لیے render_pos_page()
	 * - Inventory کے لیے render_inventory_page() (full templates + HTML)
	 */
	public function add_menu_items() {

		// عمومی capability (ابھی کے لیے) - زیادہ تر settings / reports وغیرہ کے لیے۔
		$main_capability = 'manage_options';

		// Inventory کے لیے الگ capability (آپ activation پر یہ کسی role کو دے رہی ہوں گی)
		$inventory_capability = 'manage_jwpm_inventory';

		/**
		 * 1. Top Level Menu (JWPM Dashboard)
		 */
		add_menu_page(
			__( 'JWPM POS Manager', 'jwpm-jewelry-pos-manager' ), // Page Title
			__( 'JWPM POS', 'jwpm-jewelry-pos-manager' ),         // Menu Title
			$main_capability,
			'jwpm-dashboard',                                     // Slug
			array( $this, 'render_page' ),                        // Callback (generic)
			'dashicons-store',                                    // Icon
			26                                                    // Position
		);

		/**
		 * 2. Generic Submenu Pages (Dashboard, Customers, Installments, Purchase, Reports, Settings وغیرہ)
		 *    - یہ سب وہ پیجز ہیں جو صرف ایک Root <div> بناتے ہیں
		 *      جسے (JavaScript) بعد میں بھر دیتا ہے۔
		 *
		 *    Inventory کو ہم یہاں شامل نہیں کر رہے، کیونکہ وہ اپنی مکمل PHP template سے لوڈ ہو گا۔
		 */
		$generic_pages = array(
			'jwpm-dashboard'     => __( 'Dashboard', 'jwpm-jewelry-pos-manager' ),
			// 'jwpm-inventory'  => __( 'Inventory', 'jwpm-jewelry-pos-manager' ), // 👈 یہ اب نیچے الگ handle ہو گا
			'jwpm-customers'     => __( 'Customers', 'jwpm-jewelry-pos-manager' ),
			'jwpm-installments'  => __( 'Installments', 'jwpm-jewelry-pos-manager' ),
			'jwpm-purchase'      => __( 'Purchase', 'jwpm-jewelry-pos-manager' ),
			'jwpm-custom-orders' => __( 'Custom Orders', 'jwpm-jewelry-pos-manager' ),
			'jwpm-repairs'       => __( 'Repairs', 'jwpm-jewelry-pos-manager' ),
			'jwpm-accounts'      => __( 'Accounts', 'jwpm-jewelry-pos-manager' ),
			'jwpm-reports'       => __( 'Reports', 'jwpm-jewelry-pos-manager' ),
			'jwpm-settings'      => __( 'Settings', 'jwpm-jewelry-pos-manager' ),
		);

		foreach ( $generic_pages as $slug => $title ) {
			add_submenu_page(
				'jwpm-dashboard',                 // Parent Slug
				$title,                           // Page Title
				$title,                           // Menu Title
				$main_capability,                 // Capability
				$slug,                            // Menu Slug
				array( $this, 'render_page' )     // Generic Callback Function
			);
		}

		/**
		 * 3. Inventory Page — الگ callback کے ساتھ
		 *
		 * یہاں ہم:
		 * - menu slug: jwpm-inventory
		 * - capability: manage_jwpm_inventory
		 * - callback: render_inventory_page() (جو admin/pages/jwpm-inventory.php include کرے گا)
		 */
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Inventory / Stock', 'jwpm-jewelry-pos-manager' ), // Page Title
			__( 'Inventory', 'jwpm-jewelry-pos-manager' ),         // Menu Title
			$inventory_capability,                                // Capability (custom)
			'jwpm-inventory',                                     // Slug
			array( $this, 'render_inventory_page' )               // Callback (special for inventory)
		);

		/**
		 * 4. POS Page — الگ callback کے ساتھ تاکہ ہمارا custom layout لوڈ ہو (admin/pages/jwpm-pos.php)
		 */
		add_submenu_page(
			'jwpm-dashboard',
			__( 'Point of Sale', 'jwpm-jewelry-pos-manager' ),
			__( 'Point of Sale', 'jwpm-jewelry-pos-manager' ),
			$main_capability,
			'jwpm-pos',                        // 👈 یہی slug URL میں استعمال ہو رہا ہے
			array( $this, 'render_pos_page' )  // 👈 POS کے لیے مخصوص callback
		);
	}

	/**
	 * Default / Generic پیج رینڈرر۔
	 * یہ صرف ایک خالی `div` بناتا ہے جسے (JavaScript) (React/Vue/jQuery) پُر کرے گا۔
	 *
	 * یہ Dashboard, Customers, Installments, Reports, Settings وغیرہ پر استعمال ہو رہا ہے۔
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
	 * Inventory Page کے لیے مخصوص رینڈرر۔
	 *
	 * یہ براہِ راست admin/pages/jwpm-inventory.php لوڈ کرتا ہے جہاں:
	 * - Root: <div id="jwpm-inventory-root">
	 * - تمام <template> blocks (summary, filters, table, modals وغیرہ) موجود ہیں۔
	 *
	 * یہاں capability دوبارہ چیک کر لینا بھی محفوظ ہے (Defense in depth)۔
	 */
	public function render_inventory_page() {

		if ( ! current_user_can( 'manage_jwpm_inventory' ) ) {
			wp_die(
				esc_html__(
					'You do not have permission to access the Inventory page.',
					'jwpm-jewelry-pos-manager'
				)
			);
		}

		$path = trailingslashit( JWPM_PLUGIN_DIR ) . 'admin/pages/jwpm-inventory.php';

		if ( file_exists( $path ) ) {
			include $path;
		} else {
			// اگر کسی وجہ سے فائل نہ ملے تو developer friendly پیغام
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Inventory Page Missing', 'jwpm-jewelry-pos-manager' ); ?></h1>
				<p><?php esc_html_e( 'The admin/pages/jwpm-inventory.php file could not be found. Please verify the plugin file structure.', 'jwpm-jewelry-pos-manager' ); ?></p>
			</div>
			<?php
		}
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
