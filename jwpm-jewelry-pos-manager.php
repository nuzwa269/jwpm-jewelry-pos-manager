<?php
/**
 * Plugin Name: Jewelry POS Management System (JWPM)
 * Plugin URI:  https://example.com/jwpm-jewelry-pos-manager
 * Description: ERP-Level solution for jewelry business with POS, Inventory, CRM, Accounts, Custom Orders, Repair, and Integrations.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jwpm
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define('JWPM_VERSION', '1.0.0');
define('JWPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JWPM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JWPM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
final class JWPM_Jewelry_POS_Manager {

    /**
     * The single instance of the class.
     */
    private static $instance = null;

    /**
     * Ensures only one instance of the class is loaded.
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required core files.
     */
    public function includes() {
        // Core Classes
        require_once JWPM_PLUGIN_DIR . 'class-jwpm-activator.php';
        require_once JWPM_PLUGIN_DIR . 'class-jwpm-assets.php';
        require_once JWPM_PLUGIN_DIR . 'class-jwpm-ajax.php';
        require_once JWPM_PLUGIN_DIR . 'class-jwpm-db.php';
        
        // Admin Page Classes
        if (is_admin()) {
            require_once JWPM_PLUGIN_DIR . 'admin/pages/jwpm-dashboard.php';
            require_once JWPM_PLUGIN_DIR . 'admin/pages/jwpm-pos.php';
            require_once JWPM_PLUGIN_DIR . 'admin/pages/jwpm-inventory.php';
            // ... require other page files as needed
        }
    }

    /**
     * Initialize WordPress hooks.
     */
    public function init_hooks() {
        register_activation_hook(__FILE__, array('JWPM_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('JWPM_Deactivator', 'deactivate'));

        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array('JWPM_Assets', 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array('JWPM_Assets', 'enqueue_scripts'));
        add_action('wp_ajax_jwpm_search_customers', array('JWPM_AJAX', 'search_customers'));
        // ... register other AJAX actions
    }

    /**
     * Load the plugin text domain for translation.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('jwpm', false, dirname(JWPM_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Add admin menu pages.
     */
    public function add_admin_menu() {
        // Main Menu
        add_menu_page(
            __('Jewelry POS Manager', 'jwpm'),
            __('Jewelry POS', 'jwpm'),
            'read',
            'jwpm-dashboard',
            array('JWPM_Dashboard', 'render_page'),
            'dashicons-cart',
            30
        );

        // Submenu for Dashboard
        add_submenu_page(
            'jwpm-dashboard',
            __('Dashboard', 'jwpm'),
            __('Dashboard', 'jwpm'),
            'read',
            'jwpm-dashboard',
            array('JWPM_Dashboard', 'render_page')
        );
        
        // Submenu for POS
        add_submenu_page(
            'jwpm-dashboard',
            __('POS / Billing', 'jwpm'),
            __('POS / Billing', 'jwpm'),
            'manage_jwpm_sales',
            'jwpm-pos',
            array('JWPM_POS', 'render_page')
        );
        
        // ... add other submenu pages
    }
}

/**
 * Begins execution of the plugin.
 */
function jwpm_jewelry_pos_manager() {
    return JWPM_Jewelry_POS_Manager::instance();
}

// Let's get this party started
jwpm_jewelry_pos_manager();
