<?php
/**
 * JWPM — Dashboard Page (Final System Overview)
 * یہ (PHP) فائل Dashboard کا HTML Structure, Template اور Menu Registration رکھتی ہے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🟢 یہاں سے [Dashboard Page] شروع ہو رہا ہے

/** Part 1 — Dashboard Page Registration */

/**
 * Register Dashboard under main menu
 */

/**
 * Render Dashboard Page
 */
function jwpm_render_dashboard_page() {

    if ( ! current_user_can( 'jwpm_salesperson' ) ) {
        wp_die( __( 'آپ کو Dashboard دیکھنے کی اجازت نہیں ہے۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_dashboard_nonce' );
    ?>

    <div class="wrap jwpm-admin-page jwpm-dashboard-page">

        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'JWPM Dashboard', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-dashboard-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-dashboard"
            data-jwpm-module="dashboard"
        ></div>



        <!-- ========================================== -->
        <!-- DASHBOARD TEMPLATE — (Layout: Blue + Gold) -->
        <!-- ========================================== -->

        <template id="jwpm-dashboard-layout">

            <section class="jwpm-dashboard-wrapper">

                <!-- ========================================== -->
                <!-- BLUE + GOLD TOP STRIP                     -->
                <!-- ========================================== -->
                <div class="jwpm-dashboard-header-strip">
                    <h2><?php esc_html_e( 'Jewelry POS Performance Overview', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'Live analytics, sales trends, stock alerts & gold rate summary', 'jwpm' ); ?></p>
                </div>


                <!-- ========================================== -->
                <!-- TODAY STATS (4 Premium Cards)             -->
                <!-- ========================================== -->
                <div class="jwpm-today-stats-grid">

                    <div class="jwpm-stat-card" data-role="stat-today-sale">
                        <label><?php esc_html_e( 'Today Sales', 'jwpm' ); ?></label>
                        <strong>0</strong>
                    </div>

                    <div class="jwpm-stat-card" data-role="stat-today-customers">
                        <label><?php esc_html_e( 'New Customers', 'jwpm' ); ?></label>
                        <strong>0</strong>
                    </div>

                    <div class="jwpm-stat-card" data-role="stat-items-sold">
                        <label><?php esc_html_e( 'Items Sold', 'jwpm' ); ?></label>
                        <strong>0</strong>
                    </div>

                    <div class="jwpm-stat-card" data-role="stat-today-profit">
                        <label><?php esc_html_e( 'Today Profit', 'jwpm' ); ?></label>
                        <strong>0</strong>
                    </div>

                </div>



                <!-- ========================================== -->
                <!-- CHARTS — Sales Trend + Category Chart      -->
                <!-- ========================================== -->
                <div class="jwpm-charts-grid">

                    <!-- Line Chart -->
                    <div class="jwpm-chart-card">
                        <h3><?php esc_html_e( 'Weekly Sales Trend', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-dashboard-line-chart"></canvas>
                    </div>

                    <!-- Bar Chart -->
                    <div class="jwpm-chart-card">
                        <h3><?php esc_html_e( 'Top Selling Categories', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-dashboard-bar-chart"></canvas>
                    </div>

                </div>



                <!-- ========================================== -->
                <!-- LOW STOCK ALERTS                           -->
                <!-- ========================================== -->
                <div class="jwpm-dashboard-section">

                    <h3><?php esc_html_e( 'Low Stock Alerts', 'jwpm' ); ?></h3>

                    <table class="wp-list-table widefat fixed striped jwpm-table" data-role="low-stock-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Item', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Category', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Qty Left', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Weight (g)', 'jwpm' ); ?></th>
                            </tr>
                        </thead>

                        <tbody data-role="low-stock-tbody">
                            <tr class="jwpm-empty-row">
                                <td colspan="4">
                                    <?php esc_html_e( 'تمام آئٹمز کی مقدار صحیح ہے — کوئی Low Stock نہیں۔', 'jwpm' ); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>



                <!-- ========================================== -->
                <!-- GOLD RATE WIDGET                           -->
                <!-- ========================================== -->
                <div class="jwpm-dashboard-section">

                    <h3><?php esc_html_e( 'Gold Rate Summary', 'jwpm' ); ?></h3>

                    <div class="jwpm-gold-widget" data-role="gold-rate-box">
                        <span><?php esc_html_e( 'Fetching latest rates…', 'jwpm' ); ?></span>
                    </div>

                </div>



                <!-- ========================================== -->
                <!-- QUICK LINKS                                 -->
                <!-- ========================================== -->
                <div class="jwpm-quick-links-grid">

                    <a href="admin.php?page=jwpm-pos" class="jwpm-quick-card">
                        <span><?php esc_html_e( 'Open POS', 'jwpm' ); ?></span>
                    </a>

                    <a href="admin.php?page=jwpm-inventory" class="jwpm-quick-card">
                        <span><?php esc_html_e( 'Manage Inventory', 'jwpm' ); ?></span>
                    </a>

                    <a href="admin.php?page=jwpm-customers" class="jwpm-quick-card">
                        <span><?php esc_html_e( 'Customers', 'jwpm' ); ?></span>
                    </a>

                    <a href="admin.php?page=jwpm-installments" class="jwpm-quick-card">
                        <span><?php esc_html_e( 'Installments', 'jwpm' ); ?></span>
                    </a>

                    <a href="admin.php?page=jwpm-reports" class="jwpm-quick-card">
                        <span><?php esc_html_e( 'Reports', 'jwpm' ); ?></span>
                    </a>

                    <a href="admin.php?page=jwpm-settings" class="jwpm-quick-card">
                        <span><?php esc_html_e( 'Settings', 'jwpm' ); ?></span>
                    </a>

                </div>

            </section>

        </template>

    </div>

    <?php
}

// 🔴 یہاں پر [Dashboard Page] ختم ہو رہا ہے
// ✅ Syntax verified block end

