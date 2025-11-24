<?php
/**
 * Plugin Name: Jewelry POS Management System (JWPM)
 * Plugin URI: https://example.com/jwpm-jewelry-pos-manager
 * Description: ERP-Level solution for jewelry business with POS, Inventory, CRM, Accounts, Custom Orders, Repair, and Integrations.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: jwpm
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('JWPM_VERSION', '1.0.0');
define('JWPM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JWPM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JWPM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class JWPM_Jewelry_POS_Manager {
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array('JWPM_Activator', 'activate'));
        register_deactivation_hook(__FILE__, array('JWPM_Deactivator', 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('jwpm', false, dirname(JWPM_PLUGIN_BASENAME) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize classes
        $this->initialize_classes();
        
        // Admin menu
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array('JWPM_Assets', 'enqueue'));
        
        // Register AJAX handlers
        add_action('admin_init', array('JWPM_AJAX', 'register_handlers'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-activator.php';
        require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-deactivator.php';
        require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-assets.php';
        require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-ajax.php';
        require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-db.php';
        require_once JWPM_PLUGIN_DIR . 'includes/class-jwpm-user-roles.php';
        
        // Include page classes
        require_once JWPM_PLUGIN_DIR . 'includes/pages/class-jwpm-dashboard.php';
        require_once JWPM_PLUGIN_DIR . 'includes/pages/class-jwpm-pos.php';
        require_once JWPM_PLUGIN_DIR . 'includes/pages/class-jwpm-inventory.php';
        // ... other page classes
    }
    
    /**
     * Initialize classes
     */
    private function initialize_classes() {
        // Initialize database helper
        $this->db = new JWPM_DB();
        
        // Initialize page classes
        $this->dashboard = new JWPM_Dashboard();
        $this->pos = new JWPM_POS();
        $this->inventory = new JWPM_Inventory();
        // ... other page classes
    }
    
    /**
     * Add admin menu items
     */
    public function admin_menu() {
        // Main menu
        add_menu_page(
            __('Jewelry POS Manager', 'jwpm'),
            __('Jewelry POS', 'jwpm'),
            'read',
            'jwpm-dashboard',
            array($this->dashboard, 'render_page'),
            'dashicons-cart',
            30
        );
        
        // Submenus
        add_submenu_page(
            'jwpm-dashboard',
            __('Dashboard', 'jwpm'),
            __('Dashboard', 'jwpm'),
            'read',
            'jwpm-dashboard',
            array($this->dashboard, 'render_page')
        );
        
        add_submenu_page(
            'jwpm-dashboard',
            __('POS / Billing', 'jwpm'),
            __('POS / Billing', 'jwpm'),
            'manage_jwpm_sales',
            'jwpm-pos',
            array($this->pos, 'render_page')
        );
        
        add_submenu_page(
            'jwpm-dashboard',
            __('Inventory', 'jwpm'),
            __('Inventory', 'jwpm'),
            'manage_jwpm_inventory',
            'jwpm-inventory',
            array($this->inventory, 'render_page')
        );
        
        // ... other submenu items
    }
}

// Initialize the plugin
JWPM_Jewelry_POS_Manager::get_instance();
