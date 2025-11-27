<?php
/**
 * JWPM — Stock Report Page (Layout E — Teal Blue Analytics)
 * یہ (PHP) فائل Stock Report کا HTML Root, Template اور Admin Menu Registration رکھتی ہے۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🟢 یہاں سے [Stock Report Page] شروع ہو رہا ہے

/** Part 1 — Stock Report Admin Page */

/**
 * Register Stock Report submenu
 */
function jwpm_register_stock_report_page() {

    // وہی parent slug جو باقی Reports کیلئے ہے
    $parent_slug = 'jwpm-pos-manager';

    add_submenu_page(
        $parent_slug,
        __( 'Stock Report', 'jwpm' ),
        __( 'Stock Report', 'jwpm' ),
        'jwpm_view_reports',
        'jwpm-stock-report',
        'jwpm_render_stock_report_page',
        55
    );
}
add_action( 'admin_menu', 'jwpm_register_stock_report_page' );



/**
 * Render Stock Report Page
 */
function jwpm_render_stock_report_page() {

    if ( ! current_user_can( 'jwpm_view_reports' ) ) {
        wp_die( __( 'آپ کو اس صفحہ تک رسائی کی اجازت نہیں ہے۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_stock_report_nonce' );
    ?>

    <div class="wrap jwpm-admin-page jwpm-stock-report-page">

        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'Stock Report', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-stock-report-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-stock-report"
            data-jwpm-module="reports"
        ></div>


        <!-- ======================================= -->
        <!-- Stock Report Template (Layout E)       -->
        <!-- ======================================= -->

        <template id="jwpm-stock-report-layout">

            <section class="jwpm-layout jwpm-layout--vertical">

                <!-- 🩵 TEAL / BLUE HEADER STRIP -->
                <div class="jwpm-report-teal-strip">
                    <h2><?php esc_html_e( 'Stock Analytics', 'jwpm' ); ?></h2>
                    <p><?php esc_html_e( 'Monitor item-wise, category-wise and metal-wise stock levels.', 'jwpm' ); ?></p>
                </div>


                <!-- ================================ -->
                <!-- Summary Cards Row               -->
                <!-- ================================ -->
                <div class="jwpm-report-summary">

                    <div class="jwpm-summary-card" data-role="sum-total-items">
                        <span class="jwpm-summary-label">
                            <?php esc_html_e( 'Total Items', 'jwpm' ); ?>
                        </span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-total-qty">
                        <span class="jwpm-summary-label">
                            <?php esc_html_e( 'Total Quantity (Pieces)', 'jwpm' ); ?>
                        </span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-total-weight">
                        <span class="jwpm-summary-label">
                            <?php esc_html_e( 'Total Weight (Gram)', 'jwpm' ); ?>
                        </span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                    <div class="jwpm-summary-card" data-role="sum-stock-value">
                        <span class="jwpm-summary-label">
                            <?php esc_html_e( 'Total Stock Value', 'jwpm' ); ?>
                        </span>
                        <span class="jwpm-summary-value">0</span>
                    </div>

                </div>



                <!-- ================================ -->
                <!-- Filters Bar                     -->
                <!-- ================================ -->
                <header class="jwpm-toolbar">

                    <div class="jwpm-field-group">

                        <label>
                            <span><?php esc_html_e( 'Category', 'jwpm' ); ?></span>
                            <input type="text" class="jwpm-input" data-role="filter-category" placeholder="<?php esc_attr_e( 'Ring / Necklace / Set', 'jwpm' ); ?>" />
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Metal Type', 'jwpm' ); ?></span>
                            <select class="jwpm-select" data-role="filter-metal">
                                <option value=""><?php esc_html_e( 'All', 'jwpm' ); ?></option>
                                <option value="Gold"><?php esc_html_e( 'Gold', 'jwpm' ); ?></option>
                                <option value="Silver"><?php esc_html_e( 'Silver', 'jwpm' ); ?></option>
                                <option value="Other"><?php esc_html_e( 'Other', 'jwpm' ); ?></option>
                            </select>
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Karat / Purity', 'jwpm' ); ?></span>
                            <input type="text" class="jwpm-input" data-role="filter-karat" placeholder="<?php esc_attr_e( '22K / 21K / 18K', 'jwpm' ); ?>" />
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Min Quantity', 'jwpm' ); ?></span>
                            <input type="number" class="jwpm-input" data-role="filter-min-qty" />
                        </label>

                    </div>

                    <div class="jwpm-toolbar-right">
                        <button class="button" data-role="stock-export">
                            <?php esc_html_e( 'Export Excel', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="stock-print">
                            <?php esc_html_e( 'Print', 'jwpm' ); ?>
                        </button>
                        <button class="button" data-role="stock-demo">
                            <?php esc_html_e( 'Load Demo Data', 'jwpm' ); ?>
                        </button>
                    </div>

                </header>



                <!-- ================================ -->
                <!-- Graphs (Bar + Donut)            -->
                <!-- ================================ -->
                <div class="jwpm-graphs-grid">

                    <!-- Bar Chart: Category-wise Stock Value -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Category Wise Stock Value', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-stock-bar-chart"></canvas>
                    </div>

                    <!-- Donut Chart: Metal Split -->
                    <div class="jwpm-graph-card">
                        <h3><?php esc_html_e( 'Metal Wise Split', 'jwpm' ); ?></h3>
                        <canvas id="jwpm-stock-donut-chart"></canvas>
                    </div>

                </div>



                <!-- ================================ -->
                <!-- Stock Table                     -->
                <!-- ================================ -->
                <div class="jwpm-layout-main">

                    <table class="wp-list-table widefat fixed striped jwpm-table" data-role="stock-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Item Code', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Item Name', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Category', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Metal', 'jwpm' ); ?></th>
                                <th><?php esc_html_e( 'Karat', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Qty', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Weight (g)', 'jwpm' ); ?></th>
                                <th class="jwpm-column-number"><?php esc_html_e( 'Stock Value', 'jwpm' ); ?></th>
                            </tr>
                        </thead>

                        <tbody data-role="stock-tbody">
                            <tr class="jwpm-empty-row">
                                <td colspan="8">
                                    <?php esc_html_e( 'Stock Report خالی ہے — Demo data لوڈ کریں یا Inventory میں آئٹمز شامل کریں۔', 'jwpm' ); ?>
                                </td>
                            </tr>
                        </tbody>

                    </table>

                    <div class="jwpm-pagination" data-role="stock-pagination"></div>

                </div>

            </section>

        </template>

    </div>

    <?php
}

// 🔴 یہاں پر [Stock Report Page] ختم ہو رہا ہے

// ✅ Syntax verified block end

