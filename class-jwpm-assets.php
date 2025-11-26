<?php
/**
 * JWPM_Assets
 *
 * یہ کلاس (admin) سائیڈ پر تمام (JS) اور (CSS) اسٹس کو رجسٹر اور لوڈ کرتی ہے۔
 * اسی میں ہم گلوبل (jwpmCommon) اور پیج اسپیسفک ڈیٹا (nonces وغیرہ) بھی (localize) کریں گے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Assets {

	/**
	 * (admin_enqueue_scripts) ہُک سے کال ہونے والا فنکشن
	 *
	 * @param string $hook موجودہ ایڈمن پیج ہُک۔
	 */
	public static function enqueue_admin_assets( $hook ) {

		// صرف ہمارے (JWPM) کے مینو پیجز پر لوڈ ہو:
		if ( strpos( $hook, 'jwpm-' ) === false ) {
			return;
		}

		$version = defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time();

		// 🟢 یہاں سے [Common Assets] شروع ہو رہا ہے
		// (CSS)
		wp_register_style(
			'jwpm-common-css',
			JWPM_PLUGIN_URL . 'jwpm-common.css',
			array(),
			$version
		);

		wp_enqueue_style( 'jwpm-common-css' );

		// (JS)
		wp_register_script(
			'jwpm-common-js',
			JWPM_PLUGIN_URL . 'jwpm-common.js',
			array( 'jquery' ),
			$version,
			true
		);

		wp_enqueue_script( 'jwpm-common-js' );

		// گلوبل (localize) آبجیکٹ
		$global_data = array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'nonce_common'  => wp_create_nonce( 'jwpm_common_nonce' ),
			'plugin_url'    => JWPM_PLUGIN_URL,
			'current_user'  => get_current_user_id(),
			'current_time'  => current_time( 'mysql' ),
			'i18n'          => array(
				'error_generic' => __( 'Unexpected error occurred. Please try again.', 'jwpm-jewelry-pos-manager' ),
				'saving'        => __( 'Saving...', 'jwpm-jewelry-pos-manager' ),
				'loading'       => __( 'Loading...', 'jwpm-jewelry-pos-manager' ),
			),
		);

		wp_localize_script(
			'jwpm-common-js',
			'jwpmCommon',
			$global_data
		);
		// 🔴 یہاں پر [Common Assets] ختم ہو رہا ہے

		// اب دیکھتے ہیں کون سا پیج کھلا ہوا ہے تاکہ متعلقہ (JS/CSS) لوڈ کریں۔
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// 🟢 یہاں سے [Inventory Page Assets] شروع ہو رہا ہے
		if ( 'jwpm-inventory' === $page ) {

			// پیج اسپیسفک (CSS)
			wp_register_style(
				'jwpm-inventory-css',
				JWPM_PLUGIN_URL . 'assets/css/jwpm-inventory.css',
				array( 'jwpm-common-css' ),
				$version
			);
			wp_enqueue_style( 'jwpm-inventory-css' );

			// پیج اسپیسفک (JS)
			wp_register_script(
				'jwpm-inventory-js',
				JWPM_PLUGIN_URL . 'assets/js/jwpm-inventory.js',
				array( 'jwpm-common-js', 'jquery' ),
				$version,
				true
			);
			wp_enqueue_script( 'jwpm-inventory-js' );

			// انوینٹری کے لیے خاص (nonce + settings)
			$inventory_data = array(
				'nonce'          => wp_create_nonce( 'jwpm_inventory_nonce' ),
				'page'           => $page,
				'list_action'    => 'jwpm_inventory_list_items',
				'save_action'    => 'jwpm_inventory_save_item',
				'delete_action'  => 'jwpm_inventory_delete_item',
				'import_action'  => 'jwpm_inventory_import_items',
				'export_action'  => 'jwpm_inventory_export_items',
				'demo_action'    => 'jwpm_inventory_demo_items',
				'per_page'       => 50,
				'default_branch' => self::get_default_branch_id(),
			);

			wp_localize_script(
				'jwpm-inventory-js',
				'jwpmInventoryData',
				$inventory_data
			);
		}
		// 🔴 یہاں پر [Inventory Page Assets] ختم ہو رہا ہے
	}

	/**
	 * ڈیفالٹ برانچ حاصل کرنے کے لیے ہیلپر
	 *
	 * @return int
	 */
	protected static function get_default_branch_id() {
		global $wpdb;

		$tables = JWPM_DB::get_table_names();

		$branch_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id FROM {$tables['branches']} WHERE is_default = 1 ORDER BY id ASC LIMIT 1"
		);

		if ( $branch_id > 0 ) {
			return $branch_id;
		}

		// اگر کوئی ڈیفالٹ برانچ نہیں، پہلے والی برانچ لے لیں
		$branch_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id FROM {$tables['branches']} ORDER BY id ASC LIMIT 1"
		);

		return $branch_id > 0 ? $branch_id : 0;
	}
}

// ✅ Syntax verified block end
<?php
/** Part 2 — POS page assets and localized data
 *
 * یہ بلاک (POS / Sales) پیج کے لیے الگ (JS) اور (CSS) لوڈ کرتا ہے
 * اور (jwpmPosData) کے نام سے ضروری (AJAX) ایکشنز اور (nonce) کو (localize) کرتا ہے۔
 */

/**
 * POS اسٹس لوڈر
 *
 * @param string $hook موجودہ ایڈمن پیج ہُک۔
 */
function jwpm_enqueue_pos_assets( $hook ) {

	// صرف ہمارے (JWPM) کے پیجز پر چلائیں
	if ( strpos( $hook, 'jwpm-' ) === false ) {
		return;
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( 'jwpm-pos' !== $page ) {
		return;
	}

	$version = defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time();

	// 🟢 یہاں سے [POS Assets] شروع ہو رہا ہے

	// POS اسٹائل
	wp_register_style(
		'jwpm-pos-css',
		JWPM_PLUGIN_URL . 'assets/css/jwpm-pos.css',
		array( 'jwpm-common-css' ),
		$version
	);
	wp_enqueue_style( 'jwpm-pos-css' );

	// POS اسکرپٹ
	wp_register_script(
		'jwpm-pos-js',
		JWPM_PLUGIN_URL . 'assets/js/jwpm-pos.js',
		array( 'jwpm-common-js', 'jquery' ),
		$version,
		true
	);
	wp_enqueue_script( 'jwpm-pos-js' );

	// ڈیفالٹ برانچ
	if ( class_exists( 'JWPM_DB' ) ) {
		$tables = JWPM_DB::get_table_names();
		global $wpdb;

		$default_branch = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id FROM {$tables['branches']} WHERE is_default = 1 ORDER BY id ASC LIMIT 1"
		);

		if ( $default_branch <= 0 ) {
			$default_branch = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"SELECT id FROM {$tables['branches']} ORDER BY id ASC LIMIT 1"
			);
		}
	} else {
		$default_branch = 0;
	}

	// POS کے لیے خاص (AJAX + Nonce + Settings)
	$pos_data = array(
		'nonce'                   => wp_create_nonce( 'jwpm_pos_nonce' ),
		'page'                    => $page,
		'default_branch'          => $default_branch,
		'currency_symbol'         => get_woocommerce_currency_symbol() ?: 'Rs',
		'search_items_action'     => 'jwpm_pos_search_items',
		'gold_rate_action'        => 'jwpm_pos_get_gold_rate',
		'search_customer_action'  => 'jwpm_pos_search_customer',
		'complete_sale_action'    => 'jwpm_pos_complete_sale',
	);

	wp_localize_script(
		'jwpm-pos-js',
		'jwpmPosData',
		$pos_data
	);

	// 🔴 یہاں پر [POS Assets] ختم ہو رہا ہے
}
add_action( 'admin_enqueue_scripts', 'jwpm_enqueue_pos_assets' );

// ✅ Syntax verified block end
/** Part 31 — Customers Assets Enqueue */
// 🟢 یہاں سے [Customers Assets Enqueue] شروع ہو رہا ہے

if ( ! function_exists( 'jwpm_enqueue_customers_assets' ) ) {

	/**
	 * Customers Page کیلئے (JS) اور (CSS) enqueue + localized data
	 */
	function jwpm_enqueue_customers_assets( $hook_suffix ) {

		if ( ! is_admin() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( empty( $screen ) || false === strpos( $screen->id, 'jwpm-customers' ) ) {
			return;
		}

		$base_url = plugin_dir_url( __FILE__ );

		// Customers CSS
		wp_enqueue_style(
			'jwpm-customers-css',
			$base_url . 'assets/css/jwpm-customers.css',
			array( 'jwpm-common-css' ),
			defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time()
		);

		// Customers JS
		wp_enqueue_script(
			'jwpm-customers-js',
			$base_url . 'assets/js/jwpm-customers.js',
			array( 'jquery', 'jwpm-common-js' ),
			defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time(),
			true
		);

		$main_nonce   = wp_create_nonce( 'jwpm_customers_main_nonce' );
		$import_nonce = wp_create_nonce( 'jwpm_customers_import_nonce' );
		$export_nonce = wp_create_nonce( 'jwpm_customers_export_nonce' );
		$demo_nonce   = wp_create_nonce( 'jwpm_customers_demo_nonce' );

		$localized = array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'mainNonce'         => $main_nonce,
			'importNonce'       => $import_nonce,
			'exportNonce'       => $export_nonce,
			'demoNonce'         => $demo_nonce,
			'strings'           => array(
				'loading'           => __( 'کسٹمرز لوڈ ہو رہے ہیں…', 'jwpm' ),
				'saving'            => __( 'ڈیٹا محفوظ ہو رہا ہے…', 'jwpm' ),
				'saveSuccess'       => __( 'کسٹمر کامیابی سے محفوظ ہو گیا۔', 'jwpm' ),
				'saveError'         => __( 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔', 'jwpm' ),
				'deleteConfirm'     => __( 'کیا آپ واقعی اس کسٹمر کو Inactive کرنا چاہتے ہیں؟', 'jwpm' ),
				'deleteSuccess'     => __( 'کسٹمر کو Inactive کر دیا گیا۔', 'jwpm' ),
				'demoCreateSuccess' => __( 'Demo کسٹمرز بنا دیے گئے۔', 'jwpm' ),
				'demoClearSuccess'  => __( 'Demo کسٹمرز حذف ہو گئے۔', 'jwpm' ),
				'importSuccess'     => __( 'Import مکمل ہو گیا۔', 'jwpm' ),
				'importError'       => __( 'Import کے دوران مسئلہ آیا۔', 'jwpm' ),
				'noRecords'         => __( 'کوئی ریکارڈ نہیں ملا۔', 'jwpm' ),
			),
			'pagination'        => array(
				'defaultPerPage' => 20,
				'perPageOptions' => array( 20, 50, 100 ),
			),
			'capabilities'      => array(
				'canManageCustomers' => current_user_can( 'manage_options' ),
			),
		);

		wp_localize_script( 'jwpm-customers-js', 'jwpmCustomersData', $localized );
	}
}

add_action( 'admin_enqueue_scripts', 'jwpm_enqueue_customers_assets' );

// 🔴 یہاں پر [Customers Assets Enqueue] ختم ہو رہا ہے
// ✅ Syntax verified block end
/** Part 41 — Installments Assets Enqueue */
// 🟢 یہاں سے [Installments Assets Enqueue] شروع ہو رہا ہے

if ( ! function_exists( 'jwpm_enqueue_installments_assets' ) ) {

	/**
	 * Installments Page کیلئے (JS) + (CSS) enqueue اور localized data
	 */
	function jwpm_enqueue_installments_assets( $hook_suffix ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! is_admin() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( empty( $screen ) || false === strpos( $screen->id, 'jwpm-installments' ) ) {
			return;
		}

		$base_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style(
			'jwpm-installments-css',
			$base_url . 'assets/css/jwpm-installments.css',
			array( 'jwpm-common-css' ),
			defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time()
		);

		wp_enqueue_script(
			'jwpm-installments-js',
			$base_url . 'assets/js/jwpm-installments.js',
			array( 'jquery', 'jwpm-common-js' ),
			defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time(),
			true
		);

		$main_nonce   = wp_create_nonce( 'jwpm_installments_main_nonce' );
		$import_nonce = wp_create_nonce( 'jwpm_installments_import_nonce' );
		$export_nonce = wp_create_nonce( 'jwpm_installments_export_nonce' );
		$demo_nonce   = wp_create_nonce( 'jwpm_installments_demo_nonce' );

		$localized = array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'mainNonce'   => $main_nonce,
			'importNonce' => $import_nonce,
			'exportNonce' => $export_nonce,
			'demoNonce'   => $demo_nonce,
			'strings'     => array(
				'loading'           => __( 'Installments لوڈ ہو رہے ہیں…', 'jwpm' ),
				'saving'            => __( 'ڈیٹا محفوظ ہو رہا ہے…', 'jwpm' ),
				'saveSuccess'       => __( 'Installment Plan کامیابی سے محفوظ ہو گیا۔', 'jwpm' ),
				'saveError'         => __( 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔', 'jwpm' ),
				'deleteConfirm'     => __( 'کیا آپ واقعی اس Installment Contract کو Cancel کرنا چاہتے ہیں؟', 'jwpm' ),
				'deleteSuccess'     => __( 'Installment Contract کو Cancel کر دیا گیا۔', 'jwpm' ),
				'demoCreateSuccess' => __( 'Demo Installment Plans بنا دیے گئے۔', 'jwpm' ),
				'demoClearSuccess'  => __( 'Demo Installment Data حذف ہو گیا۔', 'jwpm' ),
				'importSuccess'     => __( 'Import مکمل ہو گیا۔', 'jwpm' ),
				'importError'       => __( 'Import کے دوران مسئلہ آیا۔', 'jwpm' ),
				'noRecords'         => __( 'کوئی Installment Contract نہیں ملا۔', 'jwpm' ),
				'paymentSave'       => __( 'Payment محفوظ ہو گئی۔', 'jwpm' ),
				'paymentError'      => __( 'Payment محفوظ نہیں ہو سکی۔', 'jwpm' ),
			),
			'pagination'  => array(
				'defaultPerPage' => 20,
				'perPageOptions' => array( 20, 50, 100 ),
			),
		);

		wp_localize_script( 'jwpm-installments-js', 'jwpmInstallmentsData', $localized );
	}
}

add_action( 'admin_enqueue_scripts', 'jwpm_enqueue_installments_assets' );

// 🔴 یہاں پر [Installments Assets Enqueue] ختم ہو رہا ہے
// ✅ Syntax verified block end
/** Part 41 — Installments Assets Enqueue */
// 🟢 یہاں سے [Installments Assets Enqueue] شروع ہو رہا ہے

if ( ! function_exists( 'jwpm_enqueue_installments_assets' ) ) {

	/**
	 * Installments Page کیلئے (JS) اور (CSS) enqueue + localized data
	 */
	function jwpm_enqueue_installments_assets( $hook_suffix ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! is_admin() ) {
			return;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( empty( $screen ) || false === strpos( $screen->id, 'jwpm-installments' ) ) {
			return;
		}

		$base_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style(
			'jwpm-installments-css',
			$base_url . 'assets/css/jwpm-installments.css',
			array( 'jwpm-common-css' ),
			defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time()
		);

		wp_enqueue_script(
			'jwpm-installments-js',
			$base_url . 'assets/js/jwpm-installments.js',
			array( 'jquery', 'jwpm-common-js' ),
			defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time(),
			true
		);

		$main_nonce   = wp_create_nonce( 'jwpm_installments_main_nonce' );
		$import_nonce = wp_create_nonce( 'jwpm_installments_import_nonce' );
		$export_nonce = wp_create_nonce( 'jwpm_installments_export_nonce' );
		$demo_nonce   = wp_create_nonce( 'jwpm_installments_demo_nonce' );

		global $wpdb;

		$localized = array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'mainNonce'   => $main_nonce,
			'importNonce' => $import_nonce,
			'exportNonce' => $export_nonce,
			'demoNonce'   => $demo_nonce,
			'strings'     => array(
				'loading'           => __( 'Installments لوڈ ہو رہے ہیں…', 'jwpm' ),
				'saving'            => __( 'ڈیٹا محفوظ ہو رہا ہے…', 'jwpm' ),
				'saveSuccess'       => __( 'Installment Plan محفوظ ہو گیا۔', 'jwpm' ),
				'saveError'         => __( 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔', 'jwpm' ),
				'deleteConfirm'     => __( 'کیا آپ واقعی اس قسطی معاہدے کو Cancel کرنا چاہتے ہیں؟', 'jwpm' ),
				'deleteSuccess'     => __( 'Contract کی Status اپڈیٹ ہو گئی۔', 'jwpm' ),
				'paymentSave'       => __( 'Payment محفوظ ہو گئی۔', 'jwpm' ),
				'paymentError'      => __( 'Payment محفوظ نہیں ہو سکی۔', 'jwpm' ),
				'demoCreateSuccess' => __( 'Demo Installments بنا دیے گئے۔', 'jwpm' ),
				'demoClearSuccess'  => __( 'Demo Installments حذف ہو گئے۔', 'jwpm' ),
				'importSuccess'     => __( 'Import مکمل ہو گیا۔', 'jwpm' ),
				'importError'       => __( 'Import کے دوران مسئلہ آیا۔', 'jwpm' ),
				'noRecords'         => __( 'کوئی ریکارڈ نہیں ملا۔', 'jwpm' ),
			),
			'pagination'  => array(
				'defaultPerPage' => 20,
				'perPageOptions' => array( 20, 50, 100 ),
			),
		);

		// اگر چاہیں تو future میں یہاں customers کیلئے dropdown data بھی دے سکتے ہیں۔

		wp_localize_script( 'jwpm-installments-js', 'jwpmInstallmentsData', $localized );
	}
}

add_action( 'admin_enqueue_scripts', 'jwpm_enqueue_installments_assets' );

// 🔴 یہاں پر [Installments Assets Enqueue] ختم ہو رہا ہے
// ✅ Syntax verified block end
/** Part 41 — Installments Assets Enqueue */
// 🟢 یہاں سے [Installments Assets Enqueue] شروع ہو رہا ہے

if ( ! function_exists( 'jwpm_enqueue_installments_assets' ) ) {

	/**
	 * Installments Page کیلئے (JS) اور (CSS) enqueue + localized data
	 */
	function jwpm_enqueue_installments_assets( $hook_suffix ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! is_admin() ) {
			return;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( empty( $screen ) || false === strpos( $screen->id, 'jwpm-installments' ) ) {
			return;
		}

		$base_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style(
			'jwpm-installments-css',
			$base_url . 'assets/css/jwpm-installments.css',
			array( 'jwpm-common-css' ),
			defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time()
		);

		wp_enqueue_script(
			'jwpm-installments-js',
			$base_url . 'assets/js/jwpm-installments.js',
			array( 'jquery', 'jwpm-common-js' ),
			defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time(),
			true
		);

		$main_nonce   = wp_create_nonce( 'jwpm_installments_main_nonce' );
		$import_nonce = wp_create_nonce( 'jwpm_installments_import_nonce' );
		$export_nonce = wp_create_nonce( 'jwpm_installments_export_nonce' );
		$demo_nonce   = wp_create_nonce( 'jwpm_installments_demo_nonce' );

		global $wpdb;

		$localized = array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'mainNonce'   => $main_nonce,
			'importNonce' => $import_nonce,
			'exportNonce' => $export_nonce,
			'demoNonce'   => $demo_nonce,
			'strings'     => array(
				'loading'           => __( 'Installments لوڈ ہو رہے ہیں…', 'jwpm' ),
				'saving'            => __( 'ڈیٹا محفوظ ہو رہا ہے…', 'jwpm' ),
				'saveSuccess'       => __( 'Installment Plan محفوظ ہو گیا۔', 'jwpm' ),
				'saveError'         => __( 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔', 'jwpm' ),
				'deleteConfirm'     => __( 'کیا آپ واقعی اس قسطی معاہدے کو Cancel کرنا چاہتے ہیں؟', 'jwpm' ),
				'deleteSuccess'     => __( 'Contract کی Status اپڈیٹ ہو گئی۔', 'jwpm' ),
				'paymentSave'       => __( 'Payment محفوظ ہو گئی۔', 'jwpm' ),
				'paymentError'      => __( 'Payment محفوظ نہیں ہو سکی۔', 'jwpm' ),
				'demoCreateSuccess' => __( 'Demo Installments بنا دیے گئے۔', 'jwpm' ),
				'demoClearSuccess'  => __( 'Demo Installments حذف ہو گئے۔', 'jwpm' ),
				'importSuccess'     => __( 'Import مکمل ہو گیا۔', 'jwpm' ),
				'importError'       => __( 'Import کے دوران مسئلہ آیا۔', 'jwpm' ),
				'noRecords'         => __( 'کوئی ریکارڈ نہیں ملا۔', 'jwpm' ),
			),
			'pagination'  => array(
				'defaultPerPage' => 20,
				'perPageOptions' => array( 20, 50, 100 ),
			),
		);

		// اگر چاہیں تو future میں یہاں customers کیلئے dropdown data بھی دے سکتے ہیں۔

		wp_localize_script( 'jwpm-installments-js', 'jwpmInstallmentsData', $localized );
	}
}

add_action( 'admin_enqueue_scripts', 'jwpm_enqueue_installments_assets' );

// 🔴 یہاں پر [Installments Assets Enqueue] ختم ہو رہا ہے
// ✅ Syntax verified block end
<?php
/** Part 7 — JWPM Repair Assets Loader
 * یہاں Repair Jobs پیج کے لیے (JS) / (CSS) enqueue + localize ہو رہا ہے۔
 */

// 🟢 یہاں سے [JWPM Repair Assets] شروع ہو رہا ہے

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * یہ helper موجودہ admin_enqueue_hooks کے اندر call کیا جا سکتا ہے:
 * مثال:
 * if ( isset( $_GET['page'] ) && 'jwpm-repair' === $_GET['page'] ) { jwpm_enqueue_repair_assets(); }
 */
function jwpm_enqueue_repair_assets() {
	$screen_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'jwpm-repair' !== $screen_page ) {
		return;
	}

	$plugin_url = plugin_dir_url( dirname( __FILE__ ) );

	// CSS
	wp_enqueue_style(
		'jwpm-repair',
		$plugin_url . 'assets/css/jwpm-repair.css',
		array( 'jwpm-common' ),
		defined( 'JWPM_VERSION' ) ? JWPM_VERSION : '1.0.0'
	);

	// JS
	wp_enqueue_script(
		'jwpm-repair',
		$plugin_url . 'assets/js/jwpm-repair.js',
		array( 'jquery', 'jwpm-common' ),
		defined( 'JWPM_VERSION' ) ? JWPM_VERSION : '1.0.0',
		true
	);

	$strings = array(
		'loading'        => __( 'Repair Jobs لوڈ ہو رہے ہیں…', 'jwpm' ),
		'saving'         => __( 'مرمت کا ریکارڈ محفوظ ہو رہا ہے…', 'jwpm' ),
		'saveSuccess'    => __( 'Repair job محفوظ ہو گیا۔', 'jwpm' ),
		'saveError'      => __( 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔', 'jwpm' ),
		'deleteConfirm'  => __( 'کیا آپ واقعی اس Repair job کو cancel کرنا چاہتے ہیں؟', 'jwpm' ),
		'deleteSuccess'  => __( 'Repair job cancel / update ہو گیا۔', 'jwpm' ),
		'importSuccess'  => __( 'Repair jobs import مکمل ہو گیا۔', 'jwpm' ),
		'importError'    => __( 'Import کے دوران مسئلہ آیا۔', 'jwpm' ),
		'demoCreateSuccess' => __( 'Demo Repairs بنا دیے گئے۔', 'jwpm' ),
		'demoClearSuccess'  => __( 'Demo Repairs حذف ہو گئے۔', 'jwpm' ),
		'noRecords'      => __( 'کوئی Repair job نہیں ملا۔', 'jwpm' ),
	);

	wp_localize_script(
		'jwpm-repair',
		'jwpmRepairData',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'mainNonce'  => wp_create_nonce( 'jwpm_repair_main_nonce' ),
			'importNonce'=> wp_create_nonce( 'jwpm_repair_import_nonce' ),
			'exportNonce'=> wp_create_nonce( 'jwpm_repair_export_nonce' ),
			'demoNonce'  => wp_create_nonce( 'jwpm_repair_demo_nonce' ),
			'strings'    => $strings,
			'pagination' => array(
				'defaultPerPage' => 20,
				'perPageOptions' => array( 20, 50, 100 ),
			),
		)
	);
}

// 🔴 یہاں پر [JWPM Repair Assets] ختم ہو رہا ہے
// ✅ Syntax verified block end
