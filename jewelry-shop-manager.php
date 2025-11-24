<?php
/**
 * Plugin Name: Jewelry Shop Manager (Sample)
 * Description: A beautiful, colorful, responsive sample plugin for managing a jewelry shop (products + dashboard + front-end grid).
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: jewelry-shop-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JSM_Jewelry_Shop_Manager {

    public function __construct() {
        // Hooks
        add_action( 'init', array( $this, 'register_custom_post_type' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );

        // Shortcodes
        add_shortcode( 'jsm_jewelry_grid', array( $this, 'shortcode_jewelry_grid' ) );
    }

    /**
     * Register Custom Post Type: Jewelry Product
     */
    public function register_custom_post_type() {
        $labels = array(
            'name'               => __( 'Jewelry Products', 'jewelry-shop-manager' ),
            'singular_name'      => __( 'Jewelry Product', 'jewelry-shop-manager' ),
            'add_new'            => __( 'Add New', 'jewelry-shop-manager' ),
            'add_new_item'       => __( 'Add New Jewelry Product', 'jewelry-shop-manager' ),
            'edit_item'          => __( 'Edit Jewelry Product', 'jewelry-shop-manager' ),
            'new_item'           => __( 'New Jewelry Product', 'jewelry-shop-manager' ),
            'all_items'          => __( 'All Jewelry Products', 'jewelry-shop-manager' ),
            'view_item'          => __( 'View Jewelry Product', 'jewelry-shop-manager' ),
            'search_items'       => __( 'Search Jewelry Products', 'jewelry-shop-manager' ),
            'not_found'          => __( 'No products found', 'jewelry-shop-manager' ),
            'not_found_in_trash' => __( 'No products found in Trash', 'jewelry-shop-manager' ),
            'menu_name'          => __( 'Jewelry Products', 'jewelry-shop-manager' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'show_in_menu'       => false, // ہم اپنا الگ مینو بنائیں گے
            'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
            'has_archive'        => true,
            'rewrite'            => array( 'slug' => 'jewelry' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'jsm_product', $args );

        // Simple category taxonomy
        register_taxonomy(
            'jsm_category',
            'jsm_product',
            array(
                'label'        => __( 'Jewelry Categories', 'jewelry-shop-manager' ),
                'rewrite'      => array( 'slug' => 'jewelry-category' ),
                'hierarchical' => true,
                'show_in_rest' => true,
            )
        );
    }

    /**
     * Admin Menu
     */
    public function register_admin_menu() {
        // Main menu
        add_menu_page(
            __( 'Jewelry Manager', 'jewelry-shop-manager' ),
            __( 'Jewelry Manager', 'jewelry-shop-manager' ),
            'manage_options',
            'jsm-dashboard',
            array( $this, 'render_dashboard_page' ),
            'dashicons-diamond',
            25
        );

        // Submenus
        add_submenu_page(
            'jsm-dashboard',
            __( 'Dashboard', 'jewelry-shop-manager' ),
            __( 'Dashboard', 'jewelry-shop-manager' ),
            'manage_options',
            'jsm-dashboard',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'jsm-dashboard',
            __( 'Products', 'jewelry-shop-manager' ),
            __( 'Products', 'jewelry-shop-manager' ),
            'manage_options',
            'edit.php?post_type=jsm_product'
        );

        add_submenu_page(
            'jsm-dashboard',
            __( 'Add New Product', 'jewelry-shop-manager' ),
            __( 'Add New Product', 'jewelry-shop-manager' ),
            'manage_options',
            'post-new.php?post_type=jsm_product'
        );

        add_submenu_page(
            'jsm-dashboard',
            __( 'Settings', 'jewelry-shop-manager' ),
            __( 'Settings', 'jewelry-shop-manager' ),
            'manage_options',
            'jsm-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Admin CSS/JS
     */
    public function enqueue_admin_assets( $hook ) {
        // صرف اپنے پلگ ان صفحات کے لیے
        if ( strpos( $hook, 'jsm-' ) === false && $hook !== 'toplevel_page_jsm-dashboard' ) {
            return;
        }

        // Google Fonts
        wp_enqueue_style(
            'jsm-google-fonts',
            'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
            array(),
            null
        );

        // Custom Admin CSS
        $custom_css = "
        .jsm-wrapper {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #fce3ff, #e0f3ff);
            padding: 24px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        }
        .jsm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .jsm-card {
            background: linear-gradient(145deg, #ffffff, #f6f9ff);
            border-radius: 20px;
            padding: 16px 18px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .jsm-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 30px rgba(0,0,0,0.1);
        }
        .jsm-card-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            background: rgba(255,255,255,0.8);
        }
        .jsm-card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .jsm-card-value {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .jsm-card-sub {
            font-size: 12px;
            opacity: 0.8;
        }
        .jsm-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }
        .jsm-pill {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(255,255,255,0.9);
        }
        .jsm-header {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
        }
        .jsm-header-title {
            font-size: 24px;
            font-weight: 700;
        }
        .jsm-header-sub {
            font-size: 13px;
            opacity: 0.9;
        }
        .jsm-header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .jsm-btn {
            border-radius: 999px;
            border: none;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .jsm-btn-primary {
            background: linear-gradient(135deg, #ff7ab5, #ffb44f);
            color: #fff;
            box-shadow: 0 8px 20px rgba(255,122,181,0.5);
        }
        .jsm-btn-ghost {
            background: rgba(255,255,255,0.7);
            color: #333;
        }
        .jsm-section-title {
            margin-top: 24px;
            font-size: 16px;
            font-weight: 600;
        }
        .jsm-settings-form {
            margin-top: 16px;
            display: grid;
            gap: 16px;
            max-width: 500px;
        }
        .jsm-field label {
            display: block;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .jsm-field input[type='text'],
        .jsm-field input[type='number'] {
            width: 100%;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid #d0d7ff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .jsm-note {
            font-size: 11px;
            opacity: 0.8;
            margin-top: 2px;
        }
        @media (max-width: 600px) {
            .jsm-wrapper { padding: 16px; border-radius: 16px; }
            .jsm-header-title { font-size: 20px; }
        }
        ";

        wp_add_inline_style( 'wp-components', $custom_css );
    }

    /**
     * Front-end CSS/JS for shortcode
     */
    public function enqueue_front_assets() {
        $custom_css = "
        .jsm-front-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .jsm-front-card {
            background: linear-gradient(145deg, #ffffff, #fef6ff);
            border-radius: 20px;
            padding: 14px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .jsm-front-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 26px rgba(0,0,0,0.1);
        }
        .jsm-front-image-holder {
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 10px;
            aspect-ratio: 4 / 3;
        }
        .jsm-front-image-holder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .jsm-front-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .jsm-front-meta {
            font-size: 12px;
            opacity: .85;
            margin-bottom: 6px;
        }
        .jsm-front-price {
            font-size: 18px;
            font-weight: 700;
        }
        .jsm-front-badge-row {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .jsm-front-badge {
            background: rgba(255,255,255,0.8);
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            border: 1px solid rgba(255,255,255,0.9);
        }
        ";
        wp_register_style( 'jsm-front-inline', false );
        wp_enqueue_style( 'jsm-front-inline' );
        wp_add_inline_style( 'jsm-front-inline', $custom_css );
    }

    /**
     * Dashboard Page
     */
    public function render_dashboard_page() {
        $total_products = wp_count_posts( 'jsm_product' );
        $published      = isset( $total_products->publish ) ? (int) $total_products->publish : 0;
        $drafts         = isset( $total_products->draft ) ? (int) $total_products->draft : 0;

        ?>
        <div class="wrap">
            <div class="jsm-wrapper">
                <div class="jsm-header">
                    <div>
                        <div class="jsm-header-title">Jewelry Manager Dashboard</div>
                        <div class="jsm-header-sub">
                            فزیکل + آن لائن جیولری کے لیے ایک اٹریکٹو، کلر فل اور رسپانسیو نمونہ ڈیش بورڈ۔
                        </div>
                    </div>
                    <div class="jsm-header-actions">
                        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=jsm_product' ) ); ?>" class="jsm-btn jsm-btn-primary">
                            + Add New Product
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=jsm_product' ) ); ?>" class="jsm-btn jsm-btn-ghost">
                            View All Products
                        </a>
                    </div>
                </div>

                <div class="jsm-grid">
                    <div class="jsm-card" style="background: linear-gradient(135deg,#ff9ad5,#ffcf71); color:#fff;">
                        <div class="jsm-card-badge">Overview</div>
                        <div class="jsm-card-title">Total Products</div>
                        <div class="jsm-card-value"><?php echo esc_html( $published ); ?></div>
                        <div class="jsm-card-sub">Published items visible in front-end grid.</div>
                        <div class="jsm-pill-row">
                            <span class="jsm-pill"><?php echo esc_html( $drafts ); ?> drafts</span>
                            <span class="jsm-pill">Custom Post Type</span>
                        </div>
                    </div>

                    <div class="jsm-card" style="background: linear-gradient(135deg,#8ad6ff,#c89bff); color:#fff;">
                        <div class="jsm-card-badge">Tip</div>
                        <div class="jsm-card-title">Use Shortcode</div>
                        <div class="jsm-card-value">[jsm_jewelry_grid]</div>
                        <div class="jsm-card-sub">کسی بھی پیج یا پوسٹ میں لگائیں اور پروڈکٹس کو خوبصورت گرِڈ میں شو کریں۔</div>
                        <div class="jsm-pill-row">
                            <span class="jsm-pill">Responsive</span>
                            <span class="jsm-pill">Colorful</span>
                            <span class="jsm-pill">Active</span>
                        </div>
                    </div>

                    <div class="jsm-card" style="background: linear-gradient(135deg,#6ef7c8,#5ab0ff); color:#fff;">
                        <div class="jsm-card-badge">Idea</div>
                        <div class="jsm-card-title">Next Features</div>
                        <div class="jsm-card-sub">
                            آپ اس پلگ اِن کو آگے بڑھا کر:
                        </div>
                        <ul style="margin: 6px 0 0 18px; font-size: 12px; line-height: 1.6;">
                            <li>Inventory / Stock</li>
                            <li>Customer Records</li>
                            <li>Sales & Invoices</li>
                            <li>Reports & Analytics</li>
                        </ul>
                    </div>
                </div>

                <div class="jsm-section-title">How to use (Quick Guide)</div>
                <ul style="margin: 8px 0 0 18px; font-size: 13px; line-height: 1.7;">
                    <li><strong>Step 1:</strong> <em>Jewelry Manager → Add New Product</em> میں پروڈکٹس بنائیں، تصویر اور قیمت ڈالیں۔</li>
                    <li><strong>Step 2:</strong> کوئی پیج بنائیں (مثلاً “Our Jewelry Collection”) اور اس میں شارٹ کوڈ <code>[jsm_jewelry_grid]</code> لگا دیں۔</li>
                    <li><strong>Step 3:</strong> فرنٹ اینڈ پر ایک کلر فل، رسپانسیو گرِڈ نظر آئے گی۔</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Settings Page (نمونہ)
     */
    public function render_settings_page() {
        if ( isset( $_POST['jsm_settings_nonce'] ) && wp_verify_nonce( $_POST['jsm_settings_nonce'], 'jsm_save_settings' ) ) {
            $currency_symbol = sanitize_text_field( $_POST['jsm_currency_symbol'] ?? 'Rs.' );
            $show_badges     = isset( $_POST['jsm_show_badges'] ) ? 'yes' : 'no';

            update_option( 'jsm_currency_symbol', $currency_symbol );
            update_option( 'jsm_show_badges', $show_badges );

            echo '<div class="updated"><p>Settings saved.</p></div>';
        }

        $currency_symbol = get_option( 'jsm_currency_symbol', 'Rs.' );
        $show_badges     = get_option( 'jsm_show_badges', 'yes' );
        ?>
        <div class="wrap">
            <div class="jsm-wrapper">
                <div class="jsm-header">
                    <div>
                        <div class="jsm-header-title">Jewelry Manager Settings</div>
                        <div class="jsm-header-sub">
                            یہاں سے فرنٹ اینڈ گرِڈ کے لیے کچھ بیسک آپشنز سیٹ کریں۔
                        </div>
                    </div>
                </div>

                <form method="post" class="jsm-settings-form">
                    <?php wp_nonce_field( 'jsm_save_settings', 'jsm_settings_nonce' ); ?>

                    <div class="jsm-field">
                        <label for="jsm_currency_symbol">Currency Symbol</label>
                        <input type="text" id="jsm_currency_symbol" name="jsm_currency_symbol" value="<?php echo esc_attr( $currency_symbol ); ?>" />
                        <div class="jsm-note">مثلاً: Rs., PKR, $, € وغیرہ</div>
                    </div>

                    <div class="jsm-field">
                        <label>
                            <input type="checkbox" name="jsm_show_badges" <?php checked( $show_badges, 'yes' ); ?> />
                            Show small colorful badges on products
                        </label>
                        <div class="jsm-note">یہ فرنٹ اینڈ گرِڈ میں اسٹائلش بیجز شو کرے گا (New, Hot, Sale وغیرہ)۔</div>
                    </div>

                    <button type="submit" class="jsm-btn jsm-btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Front-end Shortcode: [jsm_jewelry_grid]
     */
    public function shortcode_jewelry_grid( $atts ) {
        $atts = shortcode_atts(
            array(
                'limit' => 12,
                'cat'   => '',
            ),
            $atts,
            'jsm_jewelry_grid'
        );

        $args = array(
            'post_type'      => 'jsm_product',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $atts['limit'],
        );

        if ( ! empty( $atts['cat'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'jsm_category',
                    'field'    => 'slug',
                    'terms'    => sanitize_title( $atts['cat'] ),
                ),
            );
        }

        $query           = new WP_Query( $args );
        $currency_symbol = get_option( 'jsm_currency_symbol', 'Rs.' );
        $show_badges     = get_option( 'jsm_show_badges', 'yes' );

        ob_start();

        if ( $query->have_posts() ) {
            echo '<div class="jsm-front-grid">';
            while ( $query->have_posts() ) {
                $query->the_post();

                $price       = get_post_meta( get_the_ID(), 'jsm_price', true );
                $gold_carat  = get_post_meta( get_the_ID(), 'jsm_gold_carat', true );
                $weight      = get_post_meta( get_the_ID(), 'jsm_weight', true );
                $badge_label = get_post_meta( get_the_ID(), 'jsm_badge_label', true ); // optional

                echo '<div class="jsm-front-card">';
                echo '<div class="jsm-front-image-holder">';
                if ( has_post_thumbnail() ) {
                    the_post_thumbnail( 'medium' );
                } else {
                    echo '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:12px;opacity:.6;">No Image</div>';
                }
                echo '</div>';

                echo '<div class="jsm-front-title">' . esc_html( get_the_title() ) . '</div>';

                $meta_parts = array();
                if ( ! empty( $gold_carat ) ) {
                    $meta_parts[] = esc_html( $gold_carat ) . 'K';
                }
                if ( ! empty( $weight ) ) {
                    $meta_parts[] = esc_html( $weight ) . ' g';
                }
                if ( ! empty( $meta_parts ) ) {
                    echo '<div class="jsm-front-meta">' . implode( ' • ', $meta_parts ) . '</div>';
                }

                if ( ! empty( $price ) ) {
                    echo '<div class="jsm-front-price">' . esc_html( $currency_symbol ) . ' ' . esc_html( $price ) . '</div>';
                }

                if ( 'yes' === $show_badges ) {
                    echo '<div class="jsm-front-badge-row">';
                    if ( ! empty( $badge_label ) ) {
                        echo '<span class="jsm-front-badge">' . esc_html( $badge_label ) . '</span>';
                    }
                    echo '<span class="jsm-front-badge">Jewelry</span>';
                    echo '<span class="jsm-front-badge">Elegant</span>';
                    echo '</div>';
                }

                echo '</div>';
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p>No jewelry products found.</p>';
        }

        return ob_get_clean();
    }
}

new JSM_Jewelry_Shop_Manager();
