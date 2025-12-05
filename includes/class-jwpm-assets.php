<?php
/**
 * JWPM_Assets
 *
 * یہ کلاس (admin) سائیڈ پر تمام (JS) اور (CSS) ایسٹس کو رجسٹر اور لوڈ کرتی ہے۔
 * یہ یقینی بناتی ہے کہ صرف متعلقہ پیجز پر ہی اسکرپٹس لوڈ ہوں۔
 *
 * @package    JWPM
 * @subpackage JWPM/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JWPM_Assets {

	/**
	 * کنسٹرکٹر۔
	 * یہاں admin_enqueue_scripts ہک کو رجسٹر کیا جاتا ہے۔
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * (admin_enqueue_scripts) ہُک سے کال ہونے والا مرکزی فنکشن۔
	 * یہ کامن Assets لوڈ کرتا ہے اور پھر پیج کی بنیاد پر مخصوص Assets لوڈ کرتا ہے۔
	 *
	 * @param string $hook موجودہ ایڈمن پیج ہُک۔
	 */
	public function enqueue_admin_assets( $hook ) {

		// 1. چیک کریں کہ کیا یہ JWPM کا پیج ہے؟
		// اگر ہک کے نام میں 'jwpm' نہیں ہے تو واپسی۔
		if ( strpos( $hook, 'jwpm' ) === false ) {
			return;
		}

		$version = defined( 'JWPM_VERSION' ) ? JWPM_VERSION : time();
		
		/**
		 * ---------------------------------------------------------------------
		 * Common Assets (تمام JWPM پیجز کے لیے)
		 * ---------------------------------------------------------------------
		 */
		
		// CSS
		wp_enqueue_style(
			'jwpm-common-css',
			JWPM_PLUGIN_URL . 'assets/css/jwpm-common.css',
			array(),
			$version
		);

		// JS
		wp_enqueue_script(
			'jwpm-common-js',
			JWPM_PLUGIN_URL . 'assets/js/jwpm-common.js',
			array( 'jquery' ),
			$version,
			true
		);

		// Global Data (Localization)
		$global_data = array(
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'nonce_common' => wp_create_nonce( 'jwpm_common_nonce' ),
			'plugin_url'   => JWPM_PLUGIN_URL,
			'current_user' => get_current_user_id(),
			'current_time' => current_time( 'mysql' ),
			'i18n'         => array(
				'error_generic' => __( 'Unexpected error occurred. Please try again.', 'jwpm-jewelry-pos-manager' ),
				'saving'        => __( 'Saving...', 'jwpm-jewelry-pos-manager' ),
				'loading'       => __( 'Loading...', 'jwpm-jewelry-pos-manager' ),
			),
		);

		wp_localize_script( 'jwpm-common-js', 'jwpmCommon', $global_data );

		/**
		 * ---------------------------------------------------------------------
		 * Page Specific Assets Logic
		 * ---------------------------------------------------------------------
		 */
		
		// URL سے موجودہ پیج کا slug حاصل کریں
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		switch ( $page ) {

			// ⭐ Dashboard Page
			case 'jwpm-dashboard':
				$this->enqueue_dashboard_assets( $version, $page );
				break;

			case 'jwpm-inventory':
				$this->enqueue_inventory_assets( $version, $page );
				break;

			case 'jwpm-pos':
				$this->enqueue_pos_assets( $version, $page );
				break;
			
			case 'jwpm-customers':
				$this->enqueue_customers_assets( $version );
				break;

			case 'jwpm-installments':
				$this->enqueue_installments_assets( $version );
				break;
				
			case 'jwpm-repair-jobs':
			case 'jwpm-repairs': 
				$this->enqueue_repair_assets( $version );
				break;

			// Accounts Module Pages
			case 'jwpm-accounts-cashbook':
			case 'jwpm-cashbook': // Alias support
				$this->enqueue_accounts_cashbook_assets( $version );
				break;
			
			case 'jwpm-accounts-expenses':
			case 'jwpm-expenses':
				$this->enqueue_expenses_assets( $version );
				break;

			case 'jwpm-accounts-ledger':
			case 'jwpm-ledger': // Alias support
				$this->enqueue_ledger_assets( $version );
				break;
				
			// Future pages can be added here
		}
	}

	/**
	 * ڈیفالٹ برانچ حاصل کرنے کے لیے ہیلپر
	 *
	 * @return int
	 */
	protected function get_default_branch_id() {
		if ( ! class_exists( 'JWPM_DB' ) ) {
			return 0;
		}

		global $wpdb;
		$tables = JWPM_DB::get_table_names();

		// ٹیبل کی موجودگی کا چیک
		if ( ! isset( $tables['branches'] ) ) {
			return 0;
		}
		
		// 1. ڈیفالٹ برانچ تلاش کریں
		$branch_id = (int) $wpdb->get_var( 
			$wpdb->prepare( "SELECT id FROM {$tables['branches']} WHERE is_default = 1 ORDER BY id ASC LIMIT 1", array() )
		);

		if ( $branch_id > 0 ) {
			return $branch_id;
		}

		// 2. اگر ڈیفالٹ نہیں ملی تو پہلی دستیاب برانچ لے لیں
		$branch_id = (int) $wpdb->get_var( 
			"SELECT id FROM {$tables['branches']} ORDER BY id ASC LIMIT 1"
		);

		return $branch_id > 0 ? $branch_id : 0;
	}

	/**
	 * --------------------------------------------------------------------------
	 * ⭐ Dashboard Assets (New)
	 * --------------------------------------------------------------------------
	 */
	protected function enqueue_dashboard_assets( $version, $page ) {

		// Chart.js Vendor (اگر پہلے کہیں اور رجسٹر نہ ہو)
		if ( ! wp_script_is( 'jwpm-chart-js', 'registered' ) ) {
			wp_register_script(
				'jwpm-chart-js',
				JWPM_PLUGIN_URL . 'vendor/chart.js/chart.umd.js',
				array(),
				'4.4.0',
				true
			);
		}

		// Dashboard JS
		wp_enqueue_script(
			'jwpm-dashboard-js',
			JWPM_PLUGIN_URL . 'assets/js/jwpm-dashboard.js',
			array( 'jquery', 'jwpm-common-js', 'jwpm-chart-js' ),
			$version,
			true
		);

		// Dashboard CSS
		wp_enqueue_style(
			'jwpm-dashboard-css',
			JWPM_PLUGIN_URL . 'assets/css/jwpm-dashboard.css',
			array( 'jwpm-common-css' ),
			$version
		);

		// Nonce
		$nonce = wp_create_nonce( 'jwpm_dashboard_nonce' );

		// Settings (Dashboard کو Settings کے مطابق چلانے کے لیے)
		$settings_general   = get_option( 'jwpm_settings_general', array() );
		$settings_inventory = get_option( 'jwpm_settings_inventory', array() );
		$settings_gold      = get_option( 'jwpm_settings_gold_rate', array() );

		$localized = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => $nonce,
			'rootId'  => 'jwpm-dashboard-root',

			'actions' => array(
				'today_stats' => 'jwpm_dashboard_today_stats',
				'charts'      => 'jwpm_dashboard_charts',
				'low_stock'   => 'jwpm_dashboard_low_stock',
				'gold_rate'   => 'jwpm_dashboard_gold_rate',
			),

			'i18n' => array(
				'loadingGold' => __( 'گولڈ ریٹ لوڈ ہو رہا ہے…', 'jwpm-jewelry-pos-manager' ),
				'goldError'   => __( 'گولڈ ریٹ لوڈ کرتے ہوئے مسئلہ آیا۔', 'jwpm-jewelry-pos-manager' ),
				'noLowStock'  => __( 'تمام آئٹمز محفوظ مقدار میں موجود ہیں۔', 'jwpm-jewelry-pos-manager' ),
			),

			'settings' => array(
				'general'   => $settings_general,
				'inventory' => $settings_inventory,
				'gold_rate' => $settings_gold,
			),
		);

		wp_localize_script(
			'jwpm-dashboard-js',
			'jwpmDashboard',
			$localized
		);
	}

	/**
	 * --------------------------------------------------------------------------
	 * Page-Specific Asset Methods
	 * --------------------------------------------------------------------------
	 */

	protected function enqueue_inventory_assets( $version, $page ) {
		wp_enqueue_style( 'jwpm-inventory-css', JWPM_PLUGIN_URL . 'assets/css/jwpm-inventory.css', array( 'jwpm-common-css' ), $version );
		wp_enqueue_script( 'jwpm-inventory-js', JWPM_PLUGIN_URL . 'assets/js/jwpm-inventory.js', array( 'jwpm-common-js', 'jquery' ), $version, true );
		
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
			'default_branch' => $this->get_default_branch_id(),
		);
		wp_localize_script( 'jwpm-inventory-js', 'jwpmInventoryData', $inventory_data );
	}

	protected function enqueue_pos_assets( $version, $page ) {
		wp_enqueue_style( 'jwpm-pos-css', JWPM_PLUGIN_URL . 'assets/css/jwpm-pos.css', array( 'jwpm-common-css' ), $version );
		wp_enqueue_script( 'jwpm-pos-js', JWPM_PLUGIN_URL . 'assets/js/jwpm-pos.js', array( 'jwpm-common-js', 'jquery' ), $version, true );
		
		$default_branch  = $this->get_default_branch_id();
		$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'Rs';
		
		$pos_data = array(
			'nonce'                  => wp_create_nonce( 'jwpm_pos_nonce' ),
			'page'                   => $page,
			'default_branch'         => $default_branch,
			'currency_symbol'        => $currency_symbol,
			'search_items_action'    => 'jwpm_pos_search_items',
			'gold_rate_action'       => 'jwpm_pos_get_gold_rate',
			'search_customer_action' => 'jwpm_pos_search_customer',
			'complete_sale_action'   => 'jwpm_pos_complete_sale',
			'today_stats_action'     => 'jwpm_pos_get_today_stats',
		);
		wp_localize_script( 'jwpm-pos-js', 'jwpmPosData', $pos_data );
	}
	
	protected function enqueue_customers_assets( $version ) {
		wp_enqueue_style( 'jwpm-customers-css', JWPM_PLUGIN_URL . 'assets/css/jwpm-customers.css', array( 'jwpm-common-css' ), $version );
		wp_enqueue_script( 'jwpm-customers-js', JWPM_PLUGIN_URL . 'assets/js/jwpm-customers.js', array( 'jquery', 'jwpm-common-js' ), $version, true );
		
		$localized = array(
			'mainNonce'   => wp_create_nonce( 'jwpm_customers_main_nonce' ),
			'importNonce' => wp_create_nonce( 'jwpm_customers_import_nonce' ),
			'exportNonce' => wp_create_nonce( 'jwpm_customers_export_nonce' ),
			'demoNonce'   => wp_create_nonce( 'jwpm_customers_demo_nonce' ),
			'actions'     => array(
				'fetch'  => 'jwpm_customers_fetch',
				'save'   => 'jwpm_customers_save',
				'delete' => 'jwpm_customers_delete',
				'import' => 'jwpm_customers_import',
				'export' => 'jwpm_customers_export',
				'demo'   => 'jwpm_customers_demo',
			),
			'strings'     => array(
				'loading'           => __( 'کسٹمرز لوڈ ہو رہے ہیں…', 'jwpm-jewelry-pos-manager' ),
				'saving'            => __( 'ڈیٹا محفوظ ہو رہا ہے…', 'jwpm-jewelry-pos-manager' ),
				'saveSuccess'       => __( 'کسٹمر کامیابی سے محفوظ ہو گیا۔', 'jwpm-jewelry-pos-manager' ),
				'saveError'         => __( 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔', 'jwpm-jewelry-pos-manager' ),
				'deleteConfirm'     => __( 'کیا آپ واقعی اس کسٹمر کو Inactive کرنا چاہتے ہیں؟', 'jwpm-jewelry-pos-manager' ),
				'deleteSuccess'     => __( 'کسٹمر کو Inactive کر دیا گیا۔', 'jwpm-jewelry-pos-manager' ),
				'demoCreateSuccess' => __( 'Demo کسٹمرز بنا دیے گئے۔', 'jwpm-jewelry-pos-manager' ),
				'demoClearSuccess'  => __( 'Demo کسٹمرز حذف ہو گئے۔', 'jwpm-jewelry-pos-manager' ),
				'importSuccess'     => __( 'Import مکمل ہو گیا۔', 'jwpm-jewelry-pos-manager' ),
				'importError'       => __( 'Import کے دوران مسئلہ آیا۔', 'jwpm-jewelry-pos-manager' ),
				'noRecords'         => __( 'کوئی ریکارڈ نہیں ملا۔', 'jwpm-jewelry-pos-manager' ),
			),
			'pagination' => array(
				'defaultPerPage' => 20,
				'perPageOptions' => array( 20, 50, 100 ),
			),
			'capabilities' => array(
				'canManageCustomers' => current_user_can( 'manage_jwpm_customers' ),
			),
		);
		wp_localize_script( 'jwpm-customers-js', 'jwpmCustomersData', $localized );
	}

	protected function enqueue_installments_assets( $version ) {
		wp_enqueue_style( 'jwpm-installments-css', JWPM_PLUGIN_URL . 'assets/css/jwpm-installments.css', array( 'jwpm-common-css' ), $version );
		wp_enqueue_script( 'jwpm-installments-js', JWPM_PLUGIN_URL . 'assets/js/jwpm-installments.js', array( 'jquery', 'jwpm-common-js' ), $version, true );
		
		$localized = array(
			'mainNonce'   => wp_create_nonce( 'jwpm_installments_main_nonce' ),
			'importNonce' => wp_create_nonce( 'jwpm_installments_import_nonce' ),
			'exportNonce' => wp_create_nonce( 'jwpm_installments_export_nonce' ),
			'demoNonce'   => wp_create_nonce( 'jwpm_installments_demo_nonce' ),
			'actions'     => array(
				'fetch'  => 'jwpm_installments_fetch',
				'save'   => 'jwpm_installments_save',
				'delete' => 'jwpm_installments_delete',
				'import' => 'jwpm_installments_import',
				'export' => 'jwpm_installments_export',
				'demo'   => 'jwpm_installments_demo',
				'pay'    => 'jwpm_installments_record_payment',
			),
			'strings'     => array(
				'loading'           => __( 'Installments لوڈ ہو رہے ہیں…', 'jwpm-jewelry-pos-manager' ),
				'saving'            => __( 'ڈیٹا محفوظ ہو رہا ہے…', 'jwpm-jewelry-pos-manager' ),
				'saveSuccess'       => __( 'Installment Plan محفوظ ہو گیا۔', 'jwpm-jewelry-pos-manager' ),
				'saveError'         => __( 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔', 'jwpm-jewelry-pos-manager' ),
				'deleteConfirm'     => __( 'کیا آپ واقعی اس قسطی معاہدے کو Cancel کرنا چاہتے ہیں؟', 'jwpm-jewelry-pos-manager' ),
				'deleteSuccess'     => __( 'Contract کی Status اپڈیٹ ہو گئی۔', 'jwpm-jewelry-pos-manager' ),
				'paymentSave'       => __( 'Payment محفوظ ہو گئی۔', 'jwpm-jewelry-pos-manager' ),
				'paymentError'      => __( 'Payment محفوظ نہیں ہو سکی۔', 'jwpm-jewelry-pos-manager' ),
				'demoCreateSuccess' => __( 'Demo Installments بنا دیے گئے۔', 'jwpm-jewelry-pos-manager' ),
				'demoClearSuccess'  => __( 'Demo Installments حذف ہو گئے۔', 'jwpm-jewelry-pos-manager' ),
				'importSuccess'     => __( 'Import مکمل ہو گیا۔', 'jwpm-jewelry-pos-manager' ),
				'importError'       => __( 'Import کے دوران مسئلہ آیا۔', 'jwpm-jewelry-pos-manager' ),
				'noRecords'         => __( 'کوئی ریکارڈ نہیں ملا۔', 'jwpm-jewelry-pos-manager' ),
			),
			'pagination' => array(
				'defaultPerPage' => 20,
				'perPageOptions' => array( 20, 50, 100 ),
			),
		);
		wp_localize_script( 'jwpm-installments-js', 'jwpmInstallmentsData', $localized );
	}
	
	protected function enqueue_repair_assets( $version ) {
		wp_enqueue_style( 'jwpm-repair-css', JWPM_PLUGIN_URL . 'assets/css/jwpm-repair.css', array( 'jwpm-common-css' ), $version );
		wp_enqueue_script( 'jwpm-repair-js', JWPM_PLUGIN_URL . 'assets/js/jwpm-repair.js', array( 'jquery', 'jwpm-common-js' ), $version, true );
		
		$strings = array(
			'loading'           => __( 'Repair Jobs لوڈ ہو رہے ہیں…', 'jwpm-jewelry-pos-manager' ),
			'saving'            => __( 'مرمت کا ریکارڈ محفوظ ہو رہا ہے…', 'jwpm-jewelry-pos-manager' ),
			'saveSuccess'       => __( 'Repair job محفوظ ہو گیا۔', 'jwpm-jewelry-pos-manager' ),
			'saveError'         => __( 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔', 'jwpm-jewelry-pos-manager' ),
			'deleteConfirm'     => __( 'کیا آپ واقعی اس Repair job کو cancel کرنا چاہتے ہیں؟', 'jwpm-jewelry-pos-manager' ),
			'deleteSuccess'     => __( 'Repair job cancel / update ہو گیا۔', 'jwpm-jewelry-pos-manager' ),
			'importSuccess'     => __( 'Repair jobs import مکمل ہو گیا۔', 'jwpm-jewelry-pos-manager' ),
			'importError'       => __( 'Import کے دوران مسئلہ آیا۔', 'jwpm-jewelry-pos-manager' ),
			'demoCreateSuccess' => __( 'Demo Repairs بنا دیے گئے۔', 'jwpm-jewelry-pos-manager' ),
			'demoClearSuccess'  => __( 'Demo Repairs حذف ہو گئے۔', 'jwpm-jewelry-pos-manager' ),
			'noRecords'         => __( 'کوئی Repair job نہیں ملا۔', 'jwpm-jewelry-pos-manager' ),
		);
		
		wp_localize_script(
			'jwpm-repair-js',
			'jwpmRepairData',
			array(
				'mainNonce'   => wp_create_nonce( 'jwpm_repair_main_nonce' ),
				'importNonce' => wp_create_nonce( 'jwpm_repair_import_nonce' ),
				'exportNonce' => wp_create_nonce( 'jwpm_repair_export_nonce' ),
				'demoNonce'   => wp_create_nonce( 'jwpm_repair_demo_nonce' ),
				'actions'     => array(
					'fetch'  => 'jwpm_repair_fetch',
					'save'   => 'jwpm_repair_save',
					'delete' => 'jwpm_repair_delete',
					'import' => 'jwpm_repair_import',
					'export' => 'jwpm_repair_export',
					'demo'   => 'jwpm_repair_demo',
				),
				'strings'    => $strings,
				'pagination' => array(
					'defaultPerPage' => 20,
					'perPageOptions' => array( 20, 50, 100 ),
				),
			)
		);
	}
	
	protected function enqueue_accounts_cashbook_assets( $version ) {
		wp_enqueue_script( 'jwpm-accounts-cashbook-js', JWPM_PLUGIN_URL . 'assets/js/jwpm-accounts-cashbook.js', array( 'jquery', 'jwpm-common-js' ), $version, true );
		wp_enqueue_style( 'jwpm-accounts-cashbook-css', JWPM_PLUGIN_URL . 'assets/css/jwpm-accounts-cashbook.css', array( 'jwpm-common-css' ), $version );
		
		$localized = array(
			'nonce'   => wp_create_nonce( 'jwpm_cashbook_nonce' ),
			'actions' => array(
				'fetch'  => 'jwpm_cashbook_fetch',
				'save'   => 'jwpm_cashbook_save',
				'delete' => 'jwpm_cashbook_delete',
				'import' => 'jwpm_cashbook_import',
				'export' => 'jwpm_cashbook_export',
				'demo'   => 'jwpm_cashbook_demo',
			),
			'rootId' => 'jwpm-accounts-cashbook-root',
			'i18n'   => array(
				'loading'       => __( 'لوڈ ہو رہا ہے...', 'jwpm-jewelry-pos-manager' ),
				'saving'        => __( 'محفوظ کیا جا رہا ہے...', 'jwpm-jewelry-pos-manager' ),
				'deleting'      => __( 'حذف کیا جا رہا ہے...', 'jwpm-jewelry-pos-manager' ),
				'confirmDelete' => __( 'کیا آپ واقعی یہ ریکارڈ حذف کرنا چاہتے ہیں؟', 'jwpm-jewelry-pos-manager' ),
				'errorGeneric'  => __( 'کچھ غلط ہو گیا، دوبارہ کوشش کریں۔', 'jwpm-jewelry-pos-manager' ),
			),
		);
		wp_localize_script( 'jwpm-accounts-cashbook-js', 'jwpmAccountsCashbook', $localized );
	}
	
	protected function enqueue_expenses_assets( $version ) {
		wp_enqueue_script( 'jwpm-expenses-js', JWPM_PLUGIN_URL . 'assets/js/jwpm-expenses.js', array( 'jquery', 'jwpm-common-js' ), $version, true );
		wp_enqueue_style( 'jwpm-expenses-css', JWPM_PLUGIN_URL . 'assets/css/jwpm-expenses.css', array( 'jwpm-common-css' ), $version );
		
		$localized = array(
			'nonce'   => wp_create_nonce( 'jwpm_expenses_nonce' ),
			'actions' => array(
				'fetch'  => 'jwpm_expenses_fetch',
				'save'   => 'jwpm_expenses_save',
				'delete' => 'jwpm_expenses_delete',
				'import' => 'jwpm_expenses_import',
				'export' => 'jwpm_expenses_export',
				'demo'   => 'jwpm_expenses_demo',
			),
			'rootId' => 'jwpm-expenses-root',
			'i18n'   => array(
				'loading'       => __( 'لوڈ ہو رہا ہے...', 'jwpm-jewelry-pos-manager' ),
				'saving'        => __( 'محفوظ کیا جا رہا ہے...', 'jwpm-jewelry-pos-manager' ),
				'deleting'      => __( 'حذف کیا جا رہا ہے...', 'jwpm-jewelry-pos-manager' ),
				'confirmDelete' => __( 'کیا آپ واقعی یہ Expense حذف کرنا چاہتے ہیں؟', 'jwpm-jewelry-pos-manager' ),
				'errorGeneric'  => __( 'کچھ غلط ہو گیا، دوبارہ کوشش کریں۔', 'jwpm-jewelry-pos-manager' ),
			),
		);
		wp_localize_script( 'jwpm-expenses-js', 'jwpmExpenses', $localized );
	}
	
	protected function enqueue_ledger_assets( $version ) {
		wp_enqueue_script( 'jwpm-ledger-js', JWPM_PLUGIN_URL . 'assets/js/jwpm-ledger.js', array( 'jquery', 'jwpm-common-js' ), $version, true );
		wp_enqueue_style( 'jwpm-ledger-css', JWPM_PLUGIN_URL . 'assets/css/jwpm-ledger.css', array( 'jwpm-common-css' ), $version );
		
		$localized = array(
			'nonce'   => wp_create_nonce( 'jwpm_ledger_nonce' ),
			'actions' => array(
				'fetch'  => 'jwpm_ledger_fetch',
				'export' => 'jwpm_ledger_export',
				'demo'   => 'jwpm_ledger_demo',
			),
			'rootId' => 'jwpm-ledger-root',
			'i18n'   => array(
				'loading'      => __( 'لوڈ ہو رہا ہے...', 'jwpm-jewelry-pos-manager' ),
				'errorGeneric' => __( 'کچھ غلط ہو گیا، دوبارہ کوشش کریں۔', 'jwpm-jewelry-pos-manager' ),
				'demoConfirm'  => __( 'کیا آپ Demo Ledger data شامل کرنا چاہتے ہیں؟', 'jwpm-jewelry-pos-manager' ),
			),
		);
		wp_localize_script( 'jwpm-ledger-js', 'jwpmLedger', $localized );
	}
}

// ✅ Syntax verified block end
