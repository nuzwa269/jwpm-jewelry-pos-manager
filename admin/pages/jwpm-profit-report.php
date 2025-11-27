<?php
/**
 * JWPM — Profit Report Page (Layout D — Orange Business Analytics)
 * یہ (PHP) فائل Profit Report کا مکمل HTML Structure, Template اور Page Registration رکھتی ہے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🟢 یہاں سے [Profit Report Page] شروع ہو رہا ہے

/** Part 1 — Profit Report Admin Page */

/**
 * Register Profit Report submenu
 */
function jwpm_register_profit_report_page() {

    $parent_slug = 'jwpm-pos-manager'; // Reports parent menu

    add_submenu_page(
        $parent_slug,
        __( 'Profit Report', 'jwpm' ),
        __( 'Profit Report', 'jwpm' ),
        'jwpm_view_reports',
        'jwpm-profit-report',
        'jwpm_render_profit_report_page',
        54
    );
}
add_action( 'admin_menu', 'jwpm_register_profit_report_page' );



/**
 * Render Profit Report Page
 */
function jwpm_render_profit_report_page() {

    if ( ! current_user_can( 'jwpm_view_reports' ) ) {
        wp_die( __( 'آپ کو اجازت نہیں ہے۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_profit_report_nonce' );
    ?>

    <div class="wrap jwpm-admin-page jwpm-profit-report-page">

        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'Profit Report', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-profit-report-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-profit-report"
            data-jwpm-module="reports"
        ></div>



        <!-- ======================================= -->
        <!-- Profit Report Template (Layout D)       -->
        <!-- ======================================= -->

        <template id="jwpm-profit-report-layout">

            <section class="jwpm-layout jwpm-layout--vertical">

                <!-- 🟧 ORANGE BUSINESS HEADER STRIP -->
                <div class="jwpm-report-orange-strip">
                    <h2><?php esc_html_e( 'Profit Analytics Dashboard', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'Track net profit, margins, cost vs sale analysis & trends', 'jwpm' ); ?></p>
                </div>



                <!-- ================================ -->
                <!-- Summary Cards Row               -->
                <!-- ================================ -->
                <div class="jwpm-report-summary">

                    <div class="jwpm-summary-card" data-role="sum-total-profit">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Net Profit', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-total-sale">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Total Sale', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-total-cost">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Total Cost', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-profit-margin">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Profit Margin %', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0%</span>
                    </div>

                </div>



                <!-- ================================ -->
                <!-- Filters Bar                     -->
                <!-- ================================ -->
                <header class="jwpm-toolbar">

                    <div class="jwpm-field-group">

                        <label>
                            <span><?php esc_html_e( 'From', 'jwpm' ); ?></span>
                            <input type="date" class="jwpm-input" data-role="filter-from-date" />
                        </label>

                        <label>
                            <span><?php esc_html_e( 'To', 'jwpm' ); ?></span>
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
                        <button class="button" data-role="profit-export">
                            <?php esc_html_e( 'Export Excel', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="profit-print">
                            <?php esc_html_e( 'Print', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="profit-demo">
                            <?php esc_html_e( 'Load Demo Data', 'jwpm' ); ?>
                        </button>
                    </div>

                </header>



                <!-- ================================ -->
                <!-- Graphs (Line + Stacked Bar)     -->
                <!-- ================================ -->
                <div class="jwpm-graphs-grid">

                    <!-- Line Graph: Profit Trend -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Profit Trend (Daily/Monthly)', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-profit-line-chart"></canvas>
                    </div>

                    <!-- Stacked Bar: Cost vs Sale -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Cost vs Sale Comparison', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-profit-bar-chart"></canvas>
                    </div>

                </div>



                <!-- ================================ -->
                <!-- Profit Table                    -->
                <!-- ================================ -->
                <div class="jwpm-layout-main">

                    <table class="wp-list-table widefat fixed striped jwpm-table" data-role="profit-table">

                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Invoice', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Customer', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Date', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Sale Amount', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Cost', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Profit', 'jwpm' ); ?></th>
                            </tr>
                        </thead>

                        <tbody data-role="profit-tbody">
                            <tr class="jwpm-empty-row">
                                <td colspan="6">
                                    <?php esc_html_e( 'Profit Report خالی ہے — Demo Data شامل کریں۔', 'jwpm' ); ?>
                                </td>
                            </tr>
                        </tbody>

                    </table>

                    <div class="jwpm-pagination" data-role="profit-pagination"></div>

                </div>

            </section>

        </template>

    </div>

    <?php
}

// 🔴 یہاں پر [Profit Report Page] ختم ہو رہا ہے

// ✅ Syntax verified block end

