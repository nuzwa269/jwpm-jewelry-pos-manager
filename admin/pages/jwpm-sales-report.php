<?php
/**
 * JWPM — Sales Report Page (Layout A — Blue Premium Dashboard)
 * (PHP) فائل Sales Report کا HTML Root, Templates اور Page Registration سنبھالتی ہے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🟢 یہاں سے [Sales Report Page] شروع ہو رہا ہے

/** Part 1 — Sales Report Admin Page */

/**
 * Sales Report menu page register
 */
function jwpm_register_sales_report_page() {

    // NOTE: یہ parent-slug وہی رکھیں جو باقی reports کے ساتھ compatible ہو
    $parent_slug = 'jwpm-pos-manager';

    add_submenu_page(
        $parent_slug,
        __( 'Sales Report', 'jwpm' ),
        __( 'Sales Report', 'jwpm' ),
        'jwpm_view_reports',
        'jwpm-sales-report',
        'jwpm_render_sales_report_page',
        51
    );
}
add_action( 'admin_menu', 'jwpm_register_sales_report_page' );



/**
 * Render Page
 */
function jwpm_render_sales_report_page() {

    if ( ! current_user_can( 'jwpm_view_reports' ) ) {
        wp_die( __( 'آپ کو اس صفحے کی اجازت نہیں ہے۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_sales_report_nonce' );
    ?>

    <div class="wrap jwpm-admin-page jwpm-sales-report-page">

        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'Sales Report', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-sales-report-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-sales-report"
            data-jwpm-module="reports"
        ></div>


        <!-- ============================= -->
        <!--  Sales Report Layout Template -->
        <!-- ============================= -->
        <template id="jwpm-sales-report-layout">

            <section class="jwpm-layout jwpm-layout--vertical">

                <!-- 🔵 BLUE HEADER STRIP -->
                <div class="jwpm-report-header-strip">
                    <h2><?php esc_html_e( 'Sales Analytics Dashboard', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'View complete breakdown of daily, weekly and monthly sales.', 'jwpm' ); ?></p>
                </div>


                <!-- ========================= -->
                <!-- Summary Cards Row (Top)  -->
                <!-- ========================= -->
                <div class="jwpm-report-summary">

                    <div class="jwpm-summary-card" data-role="sum-total-sales">
                        <span class="jwpm-summary-label">
                            <?php esc_html_e( 'Total Sales', 'jwpm' ); ?>
                        </span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-total-items">
                        <span class="jwpm-summary-label">
                            <?php esc_html_e( 'Items Sold', 'jwpm' ); ?>
                        </span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-average-invoice">
                        <span class="jwpm-summary-label">
                            <?php esc_html_e( 'Average Invoice', 'jwpm' ); ?>
                        </span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-profit">
                        <span class="jwpm-summary-label">
                            <?php esc_html_e( 'Net Profit', 'jwpm' ); ?>
                        </span>
                        <span class="jwpm-summary-value">0</span>
                    </div>
                </div>


                <!-- ========================= -->
                <!-- Filters Bar              -->
                <!-- ========================= -->
                <header class="jwpm-toolbar">

                    <div class="jwpm-field-group">

                        <label>
                            <span><?php esc_html_e( 'From Date', 'jwpm' ); ?></span>
                            <input type="date" class="jwpm-input" data-role="filter-from-date" />
                        </label>

                        <label>
                            <span><?php esc_html_e( 'To Date', 'jwpm' ); ?></span>
                            <input type="date" class="jwpm-input" data-role="filter-to-date" />
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Customer', 'jwpm' ); ?></span>
                            <input type="text" class="jwpm-input" placeholder="Name / Mobile" data-role="filter-customer" />
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Invoice No', 'jwpm' ); ?></span>
                            <input type="number" class="jwpm-input" data-role="filter-invoice" />
                        </label>

                    </div>

                    <div class="jwpm-toolbar-right">
                        <button class="button" data-role="sales-export">
                            <?php esc_html_e( 'Export Excel', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="sales-print">
                            <?php esc_html_e( 'Print', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="sales-demo">
                            <?php esc_html_e( 'Load Demo Data', 'jwpm' ); ?>
                        </button>
                    </div>

                </header>



                <!-- ========================= -->
                <!-- Graphs Section           -->
                <!-- ========================= -->
                <div class="jwpm-graphs-grid">

                    <!-- Left Graph -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Daily Sales Trend', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-sales-line-chart"></canvas>
                    </div>

                    <!-- Right Graph -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Category Wise Sales', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-sales-bar-chart"></canvas>
                    </div>

                </div>



                <!-- ========================= -->
                <!-- Sales Table              -->
                <!-- ========================= -->
                <div class="jwpm-layout-main">

                    <table class="wp-list-table widefat fixed striped jwpm-table" data-role="sales-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Invoice', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Customer', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Date', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Qty', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Total', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Profit', 'jwpm' ); ?></th>
                            </tr>
                        </thead>

                        <tbody data-role="sales-tbody">
                            <tr class="jwpm-empty-row">
                                <td colspan="6">
                                    <?php esc_html_e( 'Sales Report خالی ہے — Demo data شامل کریں۔', 'jwpm' ); ?>
                                </td>
                            </tr>
                        </tbody>

                    </table>

                    <div class="jwpm-pagination" data-role="sales-pagination"></div>
                </div>

            </section>

        </template>

    </div>

    <?php
}

// 🔴 یہاں پر [Sales Report Page] ختم ہو رہا ہے

// ✅ Syntax verified block end
