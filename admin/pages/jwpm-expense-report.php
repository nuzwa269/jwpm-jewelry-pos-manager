<?php
/**
 * JWPM — Expense Report Page (Layout C — Purple Royal UI)
 * یہ (PHP) فائل Expense Report کی HTML Structure, Templates اور Page Registration رکھتی ہے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🟢 یہاں سے [Expense Report Page] شروع ہو رہا ہے

/** Part 1 — Expense Report Admin Page */

/**
 * Register menu page
 */
function jwpm_register_expense_report_page() {

    $parent_slug = 'jwpm-pos-manager'; // Reports parent menu

    add_submenu_page(
        $parent_slug,
        __( 'Expense Report', 'jwpm' ),
        __( 'Expense Report', 'jwpm' ),
        'jwpm_view_reports',
        'jwpm-expense-report',
        'jwpm_render_expense_report_page',
        53
    );
}
add_action( 'admin_menu', 'jwpm_register_expense_report_page' );



/**
 * Render Page
 */
function jwpm_render_expense_report_page() {

    if ( ! current_user_can( 'jwpm_view_reports' ) ) {
        wp_die( __( 'آپ کو اجازت نہیں ہے۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_expense_report_nonce' );
    ?>

    <div class="wrap jwpm-admin-page jwpm-expense-report-page">

        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'Expense Report', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-expense-report-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-expense-report"
            data-jwpm-module="reports"
        ></div>


        <!-- ======================================= -->
        <!-- Expense Report Template (Layout C) -->
        <!-- ======================================= -->

        <template id="jwpm-expense-report-layout">

            <section class="jwpm-layout jwpm-layout--vertical">

                <!-- 🟣 PURPLE ROYAL HEADER STRIP -->
                <div class="jwpm-report-purple-strip">
                    <h2><?php esc_html_e( 'Expense Analytics', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'Track monthly, category-wise & vendor-wise expenses', 'jwpm' ); ?></p>
                </div>


                <!-- ======================================= -->
                <!-- Summary Cards                          -->
                <!-- ======================================= -->
                <div class="jwpm-report-summary">

                    <div class="jwpm-summary-card" data-role="sum-total-expense">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Total Expense', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-categories">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Categories Used', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-vendors">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Vendors', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-average-expense">
                        <span class="jwpm-summary-label"><?php esc_html_e( 'Average Expense', 'jwpm' ); ?></span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                </div>



                <!-- ======================================= -->
                <!-- Filters Toolbar                         -->
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
                            <span><?php esc_html_e( 'Category', 'jwpm' ); ?></span>
                            <input type="text" class="jwpm-input" placeholder="Category" data-role="filter-category" />
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Vendor', 'jwpm' ); ?></span>
                            <input type="text" class="jwpm-input" placeholder="Vendor" data-role="filter-vendor" />
                        </label>

                    </div>


                    <div class="jwpm-toolbar-right">
                        <button class="button" data-role="expense-export">
                            <?php esc_html_e( 'Export Excel', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="expense-print">
                            <?php esc_html_e( 'Print', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="expense-demo">
                            <?php esc_html_e( 'Load Demo Data', 'jwpm' ); ?>
                        </button>
                    </div>

                </header>



                <!-- ======================================= -->
                <!-- Graphs Section (BAR + DONUT)           -->
                <!-- ======================================= -->
                <div class="jwpm-graphs-grid">

                    <!-- Bar Chart: Category Wise -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Category Wise Expense', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-expense-bar-chart"></canvas>
                    </div>

                    <!-- Donut Chart: Vendor Wise -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Vendor Breakdown', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-expense-donut-chart"></canvas>
                    </div>

                </div>


                <!-- ======================================= -->
                <!-- Expense Table                           -->
                <!-- ======================================= -->
                <div class="jwpm-layout-main">

                    <table class="wp-list-table widefat fixed striped jwpm-table" data-role="expense-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Date', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Category', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Vendor', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Amount', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Note', 'jwpm' ); ?></th>
                            </tr>
                        </thead>

                        <tbody data-role="expense-tbody">
                            <tr class="jwpm-empty-row">
                                <td colspan="5">
                                    <?php esc_html_e( 'Expense Report خالی ہے۔', 'jwpm' ); ?>
                                </td>
                            </tr>
                        </tbody>

                    </table>

                    <div class="jwpm-pagination" data-role="expense-pagination"></div>

                </div>

            </section>

        </template>

    </div>

    <?php
}

// 🔴 یہاں پر [Expense Report Page] ختم ہو رہا ہے

// ✅ Syntax verified block end

