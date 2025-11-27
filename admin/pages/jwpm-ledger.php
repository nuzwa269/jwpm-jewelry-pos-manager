<?php
/**
 * JWPM — Accounts Ledger & Summary Admin Page
 * یہ فائل Ledger / Summary پیج کا HTML root اور templates فراہم کرتی ہے۔
 * (JavaScript) اس کے اوپر UI رینڈر کرے گا۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🟢 یہاں سے [Accounts Ledger Page] شروع ہو رہا ہے

/** Part 1 — Accounts Ledger Admin Page */

/**
 * Ledger submenu page register
 */
function jwpm_register_ledger_page() {
    // NOTE: یہاں اپنا اصل parent menu slug استعمال کریں
    // مثلاً اگر dashboard کا slug "jwpm-dashboard" ہے تو وہ رکھیں
    $parent_slug = 'jwpm-pos-manager';

    add_submenu_page(
        $parent_slug,
        __( 'Accounts - Ledger & Summary', 'jwpm' ),
        __( 'Ledger', 'jwpm' ),
        'jwpm_view_accounts',
        'jwpm-ledger',
        'jwpm_render_ledger_page',
        42
    );
}
add_action( 'admin_menu', 'jwpm_register_ledger_page' );

/**
 * Ledger page render callback
 */
function jwpm_render_ledger_page() {
    if ( ! current_user_can( 'jwpm_view_accounts' ) ) {
        wp_die( esc_html__( 'آپ کو اس صفحہ تک رسائی کی اجازت نہیں ہے۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_ledger_nonce' );
    ?>
    <div class="wrap jwpm-admin-page jwpm-ledger-page">
        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'Accounts — Ledger & Summary', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-ledger-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-ledger"
            data-jwpm-module="accounts"
        >
            <!-- JS یہاں UI ماؤنٹ کرے گا -->
        </div>

        <!-- Ledger Main Layout Template -->
        <template id="jwpm-ledger-layout">
            <section class="jwpm-layout jwpm-layout--split">
                <header class="jwpm-toolbar">
                    <div class="jwpm-toolbar-left">
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
                                <span><?php esc_html_e( 'Entry Type', 'jwpm' ); ?></span>
                                <select class="jwpm-select" data-role="filter-entry-type">
                                    <option value=""><?php esc_html_e( 'All', 'jwpm' ); ?></option>
                                    <option value="sale"><?php esc_html_e( 'Sale', 'jwpm' ); ?></option>
                                    <option value="purchase"><?php esc_html_e( 'Purchase', 'jwpm' ); ?></option>
                                    <option value="installment"><?php esc_html_e( 'Installment', 'jwpm' ); ?></option>
                                    <option value="custom"><?php esc_html_e( 'Custom Order', 'jwpm' ); ?></option>
                                    <option value="repair"><?php esc_html_e( 'Repair', 'jwpm' ); ?></option>
                                    <option value="manual"><?php esc_html_e( 'Manual', 'jwpm' ); ?></option>
                                </select>
                            </label>
                            <label>
                                <span><?php esc_html_e( 'Customer ID', 'jwpm' ); ?></span>
                                <input type="number" class="jwpm-input" data-role="filter-customer-id" placeholder="<?php esc_attr_e( 'e.g. 15', 'jwpm' ); ?>" />
                            </label>
                            <label>
                                <span><?php esc_html_e( 'Supplier ID', 'jwpm' ); ?></span>
                                <input type="number" class="jwpm-input" data-role="filter-supplier-id" placeholder="<?php esc_attr_e( 'e.g. 4', 'jwpm' ); ?>" />
                            </label>
                        </div>
                    </div>
                    <div class="jwpm-toolbar-right">
                        <button type="button" class="button" data-role="ledger-export">
                            <?php esc_html_e( 'Export Excel', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="ledger-print">
                            <?php esc_html_e( 'Print', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="ledger-demo">
                            <?php esc_html_e( 'Load Demo Ledger', 'jwpm' ); ?>
                        </button>
                    </div>
                </header>

                <div class="jwpm-layout-body">
                    <div class="jwpm-layout-main">
                        <div class="jwpm-balance-summary">
                            <div class="jwpm-balance-card" data-role="summary-total-debit">
                                <span class="jwpm-balance-label">
                                    <?php esc_html_e( 'Total Debit', 'jwpm' ); ?>
                                </span>
                                <span class="jwpm-balance-value">0</span>
                            </div>
                            <div class="jwpm-balance-card" data-role="summary-total-credit">
                                <span class="jwpm-balance-label">
                                    <?php esc_html_e( 'Total Credit', 'jwpm' ); ?>
                                </span>
                                <span class="jwpm-balance-value">0</span>
                            </div>
                            <div class="jwpm-balance-card jwpm-balance-card--highlight" data-role="summary-balance">
                                <span class="jwpm-balance-label">
                                    <?php esc_html_e( 'Net Balance (Debit - Credit)', 'jwpm' ); ?>
                                </span>
                                <span class="jwpm-balance-value">0</span>
                            </div>
                        </div>

                        <table class="wp-list-table widefat fixed striped jwpm-table" data-role="ledger-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Date', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Type', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Ref ID', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Customer ID', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Supplier ID', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Description', 'jwpm' ); ?></th>
                                    <th class="jwpm-column-number"><?php esc_html_e( 'Debit', 'jwpm' ); ?></th>
                                    <th class="jwpm-column-number"><?php esc_html_e( 'Credit', 'jwpm' ); ?></th>
                                </tr>
                            </thead>
                            <tbody data-role="ledger-tbody">
                                <tr class="jwpm-empty-row">
                                    <td colspan="8">
                                        <?php esc_html_e( 'Ledger خالی ہے، Demo data لوڈ کریں یا Modules کے ذریعے Entries بنائیں۔', 'jwpm' ); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="jwpm-pagination" data-role="ledger-pagination">
                            <!-- JS pagination controls -->
                        </div>
                    </div>

                    <aside class="jwpm-layout-side" data-role="ledger-sidepanel">
                        <div class="jwpm-sidepanel-header">
                            <h2>
                                <?php esc_html_e( 'Ledger Help / Summary Notes', 'jwpm' ); ?>
                            </h2>
                        </div>
                        <div class="jwpm-ledger-notes">
                            <p><?php esc_html_e( 'یہ Ledger خودکار طور پر Sales, Purchase, Installments, Custom Orders اور Repairs سے بھرا جا سکتا ہے۔ فی الحال Demo Data Option آپ کو sample structure دکھائے گا۔', 'jwpm' ); ?></p>
                            <ul>
                                <li><?php esc_html_e( 'Debit عموماً Customer receivable یا asset side', 'jwpm' ); ?></li>
                                <li><?php esc_html_e( 'Credit عموماً payment / income / supplier side', 'jwpm' ); ?></li>
                                <li><?php esc_html_e( 'Customer یا Supplier ID فلٹر لگا کر متعلقہ party کا ledger دیکھ سکتے ہیں۔', 'jwpm' ); ?></li>
                                <li><?php esc_html_e( 'Excel Export لے کر Detail حساب external accountant کو بھیج سکتے ہیں۔', 'jwpm' ); ?></li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </section>
        </template>
    </div>
    <?php
}

// 🔴 یہاں پر [Accounts Ledger Page] ختم ہو رہا ہے

// ✅ Syntax verified block end

