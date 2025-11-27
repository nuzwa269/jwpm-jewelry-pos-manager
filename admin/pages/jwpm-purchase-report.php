<?php
/**
 * JWPM — Purchase Report Page (Layout B — Green Smooth Analytics)
 * یہ (PHP) فائل Purchase Report کا HTML Root, Templates اور Page Registration رکھتی ہے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🟢 یہاں سے [Purchase Report Page] شروع ہو رہا ہے

/** Part 1 — Purchase Report Admin Page */

/**
 * Purchase Report menu page
 */
function jwpm_register_purchase_report_page() {

    // NOTE: یہ وہی parent-slug رکھیں جو آپ Sales Report کیلئے استعمال کرتے ہیں
    $parent_slug = 'jwpm-pos-manager';

    add_submenu_page(
        $parent_slug,
        __( 'Purchase Report', 'jwpm' ),
        __( 'Purchase Report', 'jwpm' ),
        'jwpm_view_reports',
        'jwpm-purchase-report',
        'jwpm_render_purchase_report_page',
        52
    );
}
add_action( 'admin_menu', 'jwpm_register_purchase_report_page' );



/**
 * Render Page
 */
function jwpm_render_purchase_report_page() {

    if ( ! current_user_can( 'jwpm_view_reports' ) ) {
        wp_die( __( 'آپ کو اجازت نہیں ہے۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_purchase_report_nonce' );
    ?>

    <div class="wrap jwpm-admin-page jwpm-purchase-report-page">

        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'Purchase Report', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-purchase-report-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-purchase-report"
            data-jwpm-module="reports"
        ></div>


        <!-- ======================================= -->
        <!-- Purchase Report Layout Template (Layout B) -->
        <!-- ======================================= -->
        <template id="jwpm-purchase-report-layout">

            <section class="jwpm-layout jwpm-layout--vertical">

                <!-- 🟩 GREEN SOFT HEADER STRIP -->
                <div class="jwpm-report-green-strip">
                    <h2><?php esc_html_e( 'Purchasing Analytics', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'Supplier-wise, metal-wise & trend-based purchase insights', 'jwpm' ); ?></p>
                </div>


                <!-- ======================================= -->
                <!-- Summary Cards Row                      -->
                <!-- ======================================= -->
                <div class="jwpm-report-summary">

                    <div class="jwpm-summary-card" data-role="sum-total-purchase">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Total Purchase', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-total-weight">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Total Weight (Gram)', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-supplier-count">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Suppliers', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-metal-breakdown">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Gold / Silver Split', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0%</span>
                    </div>

                </div>


                <!-- ======================================= -->
                <!-- Filters Bar                             -->
                <!-- ======================================= -->
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
                            <span><?php esc_html_e( 'Supplier', 'jwpm' ); ?></span>
                            <input type="text" class="jwpm-input" placeholder="Name / Mobile" data-role="filter-supplier" />
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Metal Type', 'jwpm' ); ?></span>
                            <select class="jwpm-select" data-role="filter-metal">
                                <option value=""><?php esc_html_e( 'All', 'jwpm' ); ?></option>
                                <option value="Gold"><?php esc_html_e( 'Gold', 'jwpm' ); ?></option>
                                <option value="Silver"><?php esc_html_e( 'Silver', 'jwpm' ); ?></option>
                            </select>
                        </label>

                    </div>


                    <div class="jwpm-toolbar-right">
                        <button class="button" data-role="purchase-export">
                            <?php esc_html_e( 'Export Excel', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="purchase-print">
                            <?php esc_html_e( 'Print', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="purchase-demo">
                            <?php esc_html_e( 'Load Demo Data', 'jwpm' ); ?>
                        </button>
                    </div>

                </header>



                <!-- ======================================= -->
                <!-- Graphs Section                          -->
                <!-- ======================================= -->
                <div class="jwpm-graphs-grid">

                    <!-- Left Graph: Monthly Purchase Line -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Monthly Purchase Trend', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-purchase-line-chart"></canvas>
                    </div>

                    <!-- Right Graph: Category/Metal Donut -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Metal Percentage (Gold / Silver)', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-purchase-donut-chart"></canvas>
                    </div>

                </div>



                <!-- ======================================= -->
                <!-- Purchase Table                          -->
                <!-- ======================================= -->
                <div class="jwpm-layout-main">

                    <table class="wp-list-table widefat fixed striped jwpm-table" data-role="purchase-table">

                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Invoice', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Supplier', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Date', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Metal', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Weight (g)', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Amount', 'jwpm' ); ?></th>
                            </tr>
                        </thead>

                        <tbody data-role="purchase-tbody">
                            <tr class="jwpm-empty-row">
                                <td colspan="6">
                                    <?php esc_html_e( 'Purchase Report خالی ہے۔', 'jwpm' ); ?>
                                </td>
                            </tr>
                        </tbody>

                    </table>

                    <div class="jwpm-pagination" data-role="purchase-pagination"></div>

                </div>

            </section>

        </template>

    </div>

    <?php
}

// 🔴 یہاں پر [Purchase Report Page] ختم ہو رہا ہے

// ✅ Syntax verified block end

