/**
 * JWPM — Accounts Ledger JS
 * یہ فائل Ledger Page کا UI Behaviour، Filters, Summary, Pagination
 * اور Export / Demo Events handle کرتی ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Ledger JS] شروع ہو رہا ہے

    /** Part 1 — JS: Accounts Ledger Page */

    // Root + Template
    const rootId = (window.jwpmLedger && window.jwpmLedger.rootId) || "jwpm-ledger-root";
    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Ledger root missing:", rootId);
        return;
    }

    const $layoutTpl = $("#jwpm-ledger-layout");
    if ($layoutTpl.length === 0) {
        console.warn("JWPM Warning: Ledger layout template missing");
        return;
    }

    const mount = window.jwpmMountTemplate || function (tpl, $target) {
        $target.html($(tpl).html());
    };

    mount($layoutTpl, $root);

    // Localized Data
    const ajaxUrl = window.jwpmLedger.ajaxUrl;
    const nonce = window.jwpmLedger.nonce;
    const actions = window.jwpmLedger.actions;
    const i18n = window.jwpmLedger.i18n;

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
    // Fetch Ledger
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
            html += `
                <tr>
                    <td>${r.created_at}</td>
                    <td>${r.entry_type}</td>
                    <td>${r.ref_id || ""}</td>
                    <td>${r.customer_id || ""}</td>
                    <td>${r.supplier_id || ""}</td>
                    <td>${r.description || ""}</td>
                    <td class="jwpm-column-number">${format(r.debit)}</td>
                    <td class="jwpm-column-number">${format(r.credit)}</td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    function updateSummary(summary) {
        $sumDebit.text(format(summary.total_debit || 0));
        $sumCredit.text(format(summary.total_credit || 0));
        $sumBalance.text(format(summary.balance || 0));
    }

    function renderPagination(total, page, perPage) {
        const totalPages = Math.ceil(total / perPage);

        if (totalPages <= 1) {
            $pagination.html("");
            return;
        }

        let html = "";
        for (let p = 1; p <= totalPages; p++) {
            html += `<span class="jwpm-page-btn ${p === page ? "active" : ""}" data-page="${p}">${p}</span>`;
        }

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
    // Export Excel
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
    // Print
    // -----------------------------
    $root.find('[data-role="ledger-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Ledger Records");
    });

    // -----------------------------
    // Demo Data
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

    // ✅ Syntax verified block end

})(jQuery);

