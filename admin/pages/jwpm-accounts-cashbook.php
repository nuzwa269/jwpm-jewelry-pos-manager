<?php
/**
 * JWPM — Accounts Cashbook Admin Page
 * یہ فائل Cashbook پیج کا HTML root اور templates فراہم کرتی ہے۔
 * JS اس کے اوپر UI رینڈر کرے گا۔
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access نہیں
}

// 🟢 یہاں سے [Accounts Cashbook Page] شروع ہو رہا ہے

/** Part 1 — Accounts Cashbook Admin Page */

/**
 * Cashbook submenu page register
 * نوٹ: parent slug ضرورت کے مطابق ایڈجسٹ کریں (مثلاً jwpm-dashboard وغیرہ)
 */
function jwpm_register_accounts_cashbook_page() {
    // اگر آپ کے مین مینو کا slug مختلف ہے تو یہاں تبدیل کریں:
    $parent_slug = 'jwpm-pos-manager'; // ⚠️ NOTE: اپنے اصل parent menu slug کے مطابق اپ ڈیٹ کریں

    add_submenu_page(
        $parent_slug,
        __( 'Accounts - Cashbook', 'jwpm' ),
        __( 'Cashbook', 'jwpm' ),
        'jwpm_view_accounts',
        'jwpm-accounts-cashbook',
        'jwpm_render_accounts_cashbook_page',
        40
    );
}
add_action( 'admin_menu', 'jwpm_register_accounts_cashbook_page' );

/**
 * Cashbook page render callback
 */
function jwpm_render_accounts_cashbook_page() {
    if ( ! current_user_can( 'jwpm_view_accounts' ) ) {
        wp_die( esc_html__( 'آپ کو اس صفحہ تک رسائی کی اجازت نہیں ہے۔', 'jwpm' ) );
    }

    $nonce = wp_create_nonce( 'jwpm_cashbook_nonce' );
    ?>
    <div class="wrap jwpm-admin-page jwpm-accounts-cashbook-page">
        <h1 class="jwpm-page-title">
            <?php esc_html_e( 'Accounts — Cashbook / Daily Cash', 'jwpm' ); ?>
        </h1>

        <div
            id="jwpm-accounts-cashbook-root"
            data-jwpm-nonce="<?php echo esc_attr( $nonce ); ?>"
            data-jwpm-page="jwpm-accounts-cashbook"
            data-jwpm-module="accounts"
        >
            <!-- JS یہاں UI ماؤنٹ کرے گا -->
        </div>

        <!-- Cashbook Main Layout Template -->
        <template id="jwpm-accounts-cashbook-layout">
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
                                <span><?php esc_html_e( 'Type', 'jwpm' ); ?></span>
                                <select class="jwpm-select" data-role="filter-type">
                                    <option value=""><?php esc_html_e( 'All', 'jwpm' ); ?></option>
                                    <option value="in"><?php esc_html_e( 'Cash In', 'jwpm' ); ?></option>
                                    <option value="out"><?php esc_html_e( 'Cash Out', 'jwpm' ); ?></option>
                                </select>
                            </label>
                            <label>
                                <span><?php esc_html_e( 'Category', 'jwpm' ); ?></span>
                                <input type="text" class="jwpm-input" data-role="filter-category" placeholder="<?php esc_attr_e( 'Category...', 'jwpm' ); ?>" />
                            </label>
                        </div>
                    </div>
                    <div class="jwpm-toolbar-right">
                        <button type="button" class="button button-primary" data-role="cashbook-add">
                            <?php esc_html_e( 'Add Entry', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="cashbook-import">
                            <?php esc_html_e( 'Import', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="cashbook-export">
                            <?php esc_html_e( 'Export Excel', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="cashbook-print">
                            <?php esc_html_e( 'Print', 'jwpm' ); ?>
                        </button>
                        <button type="button" class="button" data-role="cashbook-demo">
                            <?php esc_html_e( 'Load Demo Data', 'jwpm' ); ?>
                        </button>
                    </div>
                </header>

                <div class="jwpm-layout-body">
                    <div class="jwpm-layout-main">
                        <div class="jwpm-balance-summary">
                            <div class="jwpm-balance-card" data-role="balance-opening">
                                <span class="jwpm-balance-label"><?php esc_html_e( 'Opening Balance', 'jwpm' ); ?></span>
                                <span class="jwpm-balance-value">0</span>
                            </div>
                            <div class="jwpm-balance-card" data-role="balance-in">
                                <span class="jwpm-balance-label"><?php esc_html_e( 'Total Cash In', 'jwpm' ); ?></span>
                                <span class="jwpm-balance-value">0</span>
                            </div>
                            <div class="jwpm-balance-card" data-role="balance-out">
                                <span class="jwpm-balance-label"><?php esc_html_e( 'Total Cash Out', 'jwpm' ); ?></span>
                                <span class="jwpm-balance-value">0</span>
                            </div>
                            <div class="jwpm-balance-card jwpm-balance-card--highlight" data-role="balance-closing">
                                <span class="jwpm-balance-label"><?php esc_html_e( 'Closing Balance', 'jwpm' ); ?></span>
                                <span class="jwpm-balance-value">0</span>
                            </div>
                        </div>

                        <table class="wp-list-table widefat fixed striped jwpm-table" data-role="cashbook-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Date', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Type', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Category', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Reference', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Remarks', 'jwpm' ); ?></th>
                                    <th class="jwpm-column-number"><?php esc_html_e( 'Amount', 'jwpm' ); ?></th>
                                    <th><?php esc_html_e( 'Actions', 'jwpm' ); ?></th>
                                </tr>
                            </thead>
                            <tbody data-role="cashbook-tbody">
                                <tr class="jwpm-empty-row">
                                    <td colspan="7">
                                        <?php esc_html_e( 'کوئی ریکارڈ موجود نہیں، نئی انٹری شامل کریں۔', 'jwpm' ); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="jwpm-pagination" data-role="cashbook-pagination">
                            <!-- JS pagination controls -->
                        </div>
                    </div>

                    <aside class="jwpm-layout-side" data-role="cashbook-sidepanel">
                        <div class="jwpm-sidepanel-header">
                            <h2 data-role="sidepanel-title">
                                <?php esc_html_e( 'Add Cashbook Entry', 'jwpm' ); ?>
                            </h2>
                            <button type="button" class="jwpm-close" data-role="sidepanel-close">&times;</button>
                        </div>
                        <form class="jwpm-form" data-role="cashbook-form">
                            <input type="hidden" data-role="entry-id" value="" />
                            <div class="jwpm-form-grid">
                                <label>
                                    <span><?php esc_html_e( 'Date', 'jwpm' ); ?></span>
                                    <input type="date" class="jwpm-input" data-role="field-date" required />
                                </label>
                                <label>
                                    <span><?php esc_html_e( 'Type', 'jwpm' ); ?></span>
                                    <select class="jwpm-select" data-role="field-type" required>
                                        <option value="in"><?php esc_html_e( 'Cash In', 'jwpm' ); ?></option>
                                        <option value="out"><?php esc_html_e( 'Cash Out', 'jwpm' ); ?></option>
                                    </select>
                                </label>
                                <label>
                                    <span><?php esc_html_e( 'Category', 'jwpm' ); ?></span>
                                    <input type="text" class="jwpm-input" data-role="field-category" required />
                                </label>
                                <label>
                                    <span><?php esc_html_e( 'Reference', 'jwpm' ); ?></span>
                                    <input type="text" class="jwpm-input" data-role="field-reference" />
                                </label>
                                <label>
                                    <span><?php esc_html_e( 'Amount', 'jwpm' ); ?></span>
                                    <input type="number" step="0.01" min="0" class="jwpm-input" data-role="field-amount" required />
                                </label>
                                <label class="jwpm-field-full">
                                    <span><?php esc_html_e( 'Remarks', 'jwpm' ); ?></span>
                                    <textarea class="jwpm-textarea" rows="3" data-role="field-remarks"></textarea>
                                </label>
                            </div>

                            <div class="jwpm-form-actions">
                                <button type="submit" class="button button-primary" data-role="save-entry">
                                    <?php esc_html_e( 'Save Entry', 'jwpm' ); ?>
                                </button>
                                <button type="button" class="button" data-role="cancel-entry">
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

// 🔴 یہاں پر [Accounts Cashbook Page] ختم ہو رہا ہے

// ✅ Syntax verified block end

