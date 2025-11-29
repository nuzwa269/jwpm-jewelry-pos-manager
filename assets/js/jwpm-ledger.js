/**
 * JWPM — Accounts Ledger JS
 * Updated: Direct HTML Injection (No PHP Templates required)
 * یہ فائل Ledger Page کا UI Behaviour، Filters, Summary, Pagination
 * اور Export / Demo Events handle کرتی ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Ledger JS] شروع ہو رہا ہے

    /** Part 1 — JS: Accounts Ledger Page */

    // Root & Config (Safe Fallbacks)
    const rootId = (window.jwpmLedger && window.jwpmLedger.rootId) || "jwpm-ledger-root";
    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Ledger root missing:", rootId);
        return;
    }

    // Localized Data (with safety checks)
    const ledgerData = window.jwpmLedger || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        actions: {},
        i18n: {
            loading: 'Loading Ledger...',
            errorGeneric: 'Error processing request.',
            demoConfirm: 'Do you want to generate demo ledger data?'
        }
    };
    const ajaxUrl = ledgerData.ajaxUrl;
    const nonce = ledgerData.nonce;
    const actions = ledgerData.actions;
    const i18n = ledgerData.i18n;

    // ---------------------------------------------------------
    // RENDER LAYOUT (Replaces Template Mount)
    // ---------------------------------------------------------
    function renderLayout() {
        $root.html(`
            <div class="jwpm-wrapper">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #eee;">
                    <h2 style="margin:0;">📜 Accounts Ledger (Debit/Credit)</h2>
                    <div>
                        <button class="button" data-role="ledger-export">Export</button>
                        <button class="button" data-role="ledger-print">Print</button>
                        <button class="button" data-role="ledger-demo">Demo Data</button>
                    </div>
                </div>

                <div style="display:flex; gap:20px; margin-bottom:20px; flex-wrap:wrap;">
                    
                    <div class="jwpm-card" style="padding:15px; flex:2; min-width:350px; display:flex; gap:10px; flex-wrap:wrap;">
                        <input type="date" data-role="filter-from-date" title="From Date">
                        <input type="date" data-role="filter-to-date" title="To Date">
                        
                        <select data-role="filter-entry-type" style="padding:6px;">
                            <option value="">All Types</option>
                            <option value="SALE">Sale</option>
                            <option value="PURCHASE">Purchase</option>
                            <option value="PAYMENT">Payment</option>
                            <option value="EXPENSE">Expense</option>
                        </select>
                        
                        <input type="text" data-role="filter-customer-id" placeholder="Customer ID/Name">
                        <input type="text" data-role="filter-supplier-id" placeholder="Supplier ID/Name">

                        <button class="button" onclick="jQuery('[data-role^=filter-]').val('').trigger('change');">Clear Filters</button>
                    </div>

                    <div style="flex:1; display:flex; flex-wrap:wrap; gap:10px;">
                        <div class="jwpm-stat-card" style="flex:1; background:#fff0e6; color:#ff9900;" data-role="summary-total-debit">
                            <div style="font-size:12px;">Total Debit</div>
                            <div class="jwpm-balance-value" style="font-size:1.5em; font-weight:bold;">0.00</div>
                        </div>
                        <div class="jwpm-stat-card" style="flex:1; background:#e6fff0; color:#059669;" data-role="summary-total-credit">
                            <div style="font-size:12px;">Total Credit</div>
                            <div class="jwpm-balance-value" style="font-size:1.5em; font-weight:bold;">0.00</div>
                        </div>
                         <div class="jwpm-card" style="flex:1 1 100%; text-align:center; background:#e6f0ff; color:#0073aa;" data-role="summary-balance">
                            <div style="font-size:12px;">Net Balance</div>
                            <div class="jwpm-balance-value" style="font-size:1.8em; font-weight:bold;">0.00</div>
                        </div>
                    </div>
                </div>
                
                <div class="jwpm-card" style="padding:15px;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Ref ID</th>
                                <th>Customer</th>
                                <th>Supplier</th>
                                <th>Description</th>
                                <th style="text-align:right;">Debit (Dr)</th>
                                <th style="text-align:right;">Credit (Cr)</th>
                            </tr>
                        </thead>
                        <tbody data-role="ledger-tbody">
                            <tr><td colspan="8">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="tablenav bottom">
                    <div class="tablenav-pages" data-role="ledger-pagination"></div>
                </div>
            </div>
        `);
    }

    renderLayout(); // Inject the UI immediately

    // ---------------------------------------------------------
    // Element Caching (Post-Render)
    // ---------------------------------------------------------

    // Elements
    const $tbody = $root.find('[data-role="ledger-tbody"]');
    const $pagination = $root.find('[data-role="ledger-pagination"]');

    // Summary
    const $sumDebit = $root.find('[data-role="summary-total-debit"] .jwpm-balance-value');
    const $sumCredit = $root.find('[data-role="summary-total-credit"] .jwpm-balance-value');
    const $sumBalance = $root.find('[data-role="summary-balance"] .jwpm-balance-value');

    // Filters
    const $filterFrom = $root.find('[data-role="filter-from-date"]');
    const $filterTo = $root.find('[data-role="filter-to-date"]');
    const $filterType = $root.find('[data-role="filter-entry-type"]');
    const $filterCustomer = $root.find('[data-role="filter-customer-id"]');
    const $filterSupplier = $root.find('[data-role="filter-supplier-id"]');

    // Utility
    function wpAjax(action, data) {
        return $.ajax({
            url: ajaxUrl,
            method: "POST",
            data: Object.assign({}, data, {
                action: action,
                nonce: nonce,
            }),
        });
    }

    function format(n) {
        return parseFloat(n).toLocaleString("en-US", { minimumFractionDigits: 2 });
    }

    function getFilters() {
        return {
            from_date: $filterFrom.val(),
            to_date: $filterTo.val(),
            entry_type: $filterType.val(),
            customer_id: $filterCustomer.val(),
            supplier_id: $filterSupplier.val(),
        };
    }

    // -----------------------------
    // Fetch Ledger (Unchanged Logic)
    // -----------------------------
    let currentPage = 1;
    let perPage = 50;

    function loadLedger(page = 1) {
        currentPage = page;

        $tbody.html(`<tr><td colspan="8">${i18n.loading}</td></tr>`);

        const filters = getFilters();

        wpAjax(actions.fetch, {
            page: currentPage,
            per_page: perPage,
            from_date: filters.from_date,
            to_date: filters.to_date,
            entry_type: filters.entry_type,
            customer_id: filters.customer_id,
            supplier_id: filters.supplier_id,
        })
            .done((res) => {
                if (!res.success) {
                    $tbody.html(`<tr><td colspan="8">${i18n.errorGeneric}</td></tr>`);
                    return;
                }

                const items = res.data.items;

                if (items.length === 0) {
                    $tbody.html(`
                        <tr class="jwpm-empty-row">
                            <td colspan="8">Ledger خالی ہے</td>
                        </tr>
                    `);
                } else {
                    renderRows(items);
                }

                updateSummary(res.data.summary);
                renderPagination(res.data.total, res.data.page, res.data.perPage);
            })
            .fail(() => {
                $tbody.html(`<tr><td colspan="8">${i18n.errorGeneric}</td></tr>`);
            });
    }

    function renderRows(rows) {
        let html = "";

        rows.forEach((r) => {
            // Apply balance color based on sign
            const balanceColor = r.balance < 0 ? 'color:red; font-weight:bold;' : 'color:green; font-weight:bold;';
            const typeClass = r.entry_type.toLowerCase();
            
            html += `
                <tr data-type="${typeClass}">
                    <td>${r.created_at}</td>
                    <td>${r.entry_type}</td>
                    <td>${r.ref_id || "-"}</td>
                    <td>${r.customer_id || "-"}</td>
                    <td>${r.supplier_id || "-"}</td>
                    <td>${r.description || "-"}</td>
                    <td class="jwpm-column-number" style="color:red;">${r.debit > 0 ? format(r.debit) : "-"}</td>
                    <td class="jwpm-column-number" style="color:green;">${r.credit > 0 ? format(r.credit) : "-"}</td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    function updateSummary(summary) {
        $sumDebit.text(format(summary.total_debit || 0));
        $sumCredit.text(format(summary.total_credit || 0));
        
        const balance = summary.balance || 0;
        const balanceText = format(Math.abs(balance));
        const color = balance < 0 ? 'red' : 'green';
        const sign = balance < 0 ? 'Dr' : 'Cr';

        $sumBalance.css('color', color).text(`${balanceText} (${sign})`);
    }

    function renderPagination(total, page, perPage) {
        const totalPages = Math.ceil(total / perPage);

        if (totalPages <= 1) {
            $pagination.html("");
            return;
        }

        let html = "";
        if (page > 1) html += `<button class="button jwpm-page-btn" data-page="${page - 1}">« Prev</button> `;
        html += `<span style="padding:0 10px;">Page ${page} of ${totalPages}</span>`;
        if (page < totalPages) html += `<button class="button jwpm-page-btn" data-page="${page + 1}">Next »</button>`;
        
        $pagination.html(html);
    }

    // Pagination click event
    $pagination.on("click", "[data-page]", function () {
        const p = parseInt($(this).data("page"));
        loadLedger(p);
    });

    // Filters Auto Reload
    $filterFrom.on("change", () => loadLedger(1));
    $filterTo.on("change", () => loadLedger(1));
    $filterType.on("change", () => loadLedger(1));
    $filterCustomer.on("input", () => loadLedger(1));
    $filterSupplier.on("input", () => loadLedger(1));

    // -----------------------------
    // Export Excel (Unchanged Logic)
    // -----------------------------
    $root.find('[data-role="ledger-export"]').on("click", function () {
        wpAjax(actions.export, {}).done((res) => {
            if (res.success && res.data.rows) {
                window.jwpmExportToExcel("Ledger", res.data.headers, res.data.rows);
            } else {
                alert(i18n.errorGeneric);
            }
        });
    });

    // -----------------------------
    // Print (Unchanged Logic)
    // -----------------------------
    $root.find('[data-role="ledger-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Ledger Records");
    });

    // -----------------------------
    // Demo Data (Unchanged Logic)
    // -----------------------------
    $root.find('[data-role="ledger-demo"]').on("click", function () {
        if (!confirm(i18n.demoConfirm)) return;

        wpAjax(actions.demo, {}).done((res) => {
            if (res.success) {
                alert(res.data.message);
                loadLedger(1);
            } else {
                alert(i18n.errorGeneric);
            }
        });
    });

    // Initial Load
    loadLedger(1);

    // 🔴 یہاں پر [Ledger JS] ختم ہو رہا ہے
})(jQuery);
