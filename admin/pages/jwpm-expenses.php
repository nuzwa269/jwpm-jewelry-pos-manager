<?php
/**
 * JWPM — Accounts Expenses Admin Page
 * یہ فائل Expenses Management پیج کا HTML root اور templates فراہم کرتی ہے۔
 * JS اس کے اوپر UI رینڈر کرے گا۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 🟢 یہاں سے [Accounts Expenses Page] شروع ہو رہا ہے

/** Part 1 — Accounts Expenses Admin Page */

/**
 * Expenses submenu page register
 */
function jwpm_register_expenses_page() {
    // NOTE: اپنے اصل parent menu slug کے مطابق ایڈجسٹ کریں
    $parent_slug = 'jwpm-pos-manager';

    add_submenu_page(
        $parent_slug,
        __( 'Accounts - Expenses', 'jwpm' ),
        __( 'Expenses', 'jwpm' ),
        'jwpm_view_accounts',
        'jwpm-expenses',
        'jwpm_render_expenses_page',
        41
    );
}
add_action( 'admin_menu', 'jwpm_register_expenses_page' );

/**
 * Expenses page render callback
 */
function jwpm_render_expenses_page() {
    if ( ! current_user_can( 'jwpm_view_accounts' ) ) {
        wp_die( esc_html__( 'آپ کو اس صفحہ تک رسائی کی اجازت نہیں ہے۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_expenses_nonce' );
    ?>
    <div class="wrap jwpm-admin-page jwpm-expenses-page">
        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'Accounts — Expenses Management', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-expenses-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-expenses"
            data-jwpm-module="accounts"
        >
            <!-- JS یہاں UI ماؤنٹ کرے گا -->
        </div>

        <!-- Expenses Main Layout Template -->
        <template id="jwpm-expenses-layout">
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
                                <span><?php esc_html_e( 'Category', 'jwpm' ); ?></span>
                                <input type="text" class="jwpm-input" data-role="filter-category" placeholder="<?php esc_attr_e( 'Category...', 'jwpm' ); ?>" />
                            </label>
                            <label>
                                <span><?php esc_html_e( 'Vendor', 'jwpm' ); ?></span>
                                <input type="text" class="jwpm-input" data-role="filter-vendor" placeholder="<?php esc_attr_e( 'Vendor...', 'jwpm' ); ?>" />
                            </label>
                        </div>
                    </div>
                    <div class="jwpm-toolbar-right">
                        <button type="button" class="button button-primary" data-role="expense-add">
                            <?php esc_html_e( 'Add Expense', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="expense-import">
                            <?php esc_html_e( 'Import', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="expense-export">
                            <?php esc_html_e( 'Export Excel', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="expense-print">
                            <?php esc_html_e( 'Print', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="expense-demo">
                            <?php esc_html_e( 'Load Demo Data', 'jwpm' ); ?>
                        </button>
                    </div>
                </header>

                <div class="jwpm-layout-body">
                    <div class="jwpm-layout-main">
                        <div class="jwpm-balance-summary">
                            <div class="jwpm-balance-card jwpm-balance-card--highlight" data-role="expenses-total">
                                <span class="jwpm-balance-label">
                                    <?php esc_html_e( 'Total Expenses', 'jwpm' ); ?>
                                </span>
                                <span class="jwpm-balance-value">0</span>
                            </div>
                        </div>

                        <table class="wp-list-table widefat fixed striped jwpm-table" data-role="expenses-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Date', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Category', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Vendor', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Notes', 'jwpm' ); ?></th>
                                    <th class="jwpm-column-number"><?php esc_html_e( 'Amount', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'jwpm' ); ?></th>
                                </tr>
                            </thead>
                            <tbody data-role="expenses-tbody">
                                <tr class="jwpm-empty-row">
                                    <td colspan="6">
                                        <?php esc_html_e( 'کوئی Expense ریکارڈ موجود نہیں، نئی انٹری شامل کریں۔', 'jwpm' ); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="jwpm-pagination" data-role="expenses-pagination">
                            <!-- JS pagination controls -->
                        </div>
                    </div>

                    <aside class="jwpm-layout-side" data-role="expenses-sidepanel">
                        <div class="jwpm-sidepanel-header">
                            <h2 data-role="sidepanel-title">
                                <?php esc_html_e( 'Add Expense', 'jwpm' ); ?>
                            </h2>
                            <button type="button" class="jwpm-close" data-role="sidepanel-close">&times;</button>
                        </div>
                        <form class="jwpm-form" data-role="expenses-form">
                            <input type="hidden" data-role="expense-id" value="" />
                            <div class="jwpm-form-grid">
                                <label>
                                    <span><?php esc_html_e( 'Date', 'jwpm' ); ?></span>
                                    <input type="date" class="jwpm-input" data-role="field-date" required />
                                </label>
                                <label>
                                    <span><?php esc_html_e( 'Category', 'jwpm' ); ?></span>
                                    <input type="text" class="jwpm-input" data-role="field-category" required />
                                </label>
                                <label>
                                    <span><?php esc_html_e( 'Vendor', 'jwpm' ); ?></span>
                                    <input type="text" class="jwpm-input" data-role="field-vendor" />
                                </label>
                                <label>
                                    <span><?php esc_html_e( 'Amount', 'jwpm' ); ?></span>
                                    <input type="number" step="0.01" min="0" class="jwpm-input" data-role="field-amount" required />
                                </label>
                                <label class="jwpm-field-full">
                                    <span><?php esc_html_e( 'Notes', 'jwpm' ); ?></span>
                                    <textarea class="jwpm-textarea" rows="3" data-role="field-notes"></textarea>
                                </label>
                                <label class="jwpm-field-full">
                                    <span><?php esc_html_e( 'Receipt URL (optional)', 'jwpm' ); ?></span>
                                    <input type="url" class="jwpm-input" data-role="field-receipt-url" placeholder="<?php esc_attr_e( 'https://...', 'jwpm' ); ?>" />
                                </label>
                            </div>

                            <div class="jwpm-form-actions">
                                <button type="submit" class="button button-primary" data-role="save-expense">
                                    <?php esc_html_e( 'Save Expense', 'jwpm' ); ?>
                                </button>
                                <button type="button" class="button" data-role="cancel-expense">
                                    <?php esc_html_e( 'Cancel', 'jwpm' ); ?>
                                </button>
                            </div>
                        </form>
                    </aside>
                </div>
            </section>
        </template>
    </div>
    <?php
}

// 🔴 یہاں پر [Accounts Expenses Page] ختم ہو رہا ہے

// ✅ Syntax verified block end
