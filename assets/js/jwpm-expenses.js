/**
 * JWPM — Accounts Expenses JS
 * یہ فائل Expenses Page کا UI Behaviour + AJAX Calls + Table Rendering سنبھالتی ہے۔
 * Root: #jwpm-expenses-root
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Expenses JS] شروع ہو رہا ہے

    /** Part 1 — JS: Accounts Expenses Page */

    // Root & Template
    const rootId = (window.jwpmExpenses && window.jwpmExpenses.rootId) || "jwpm-expenses-root";
    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Expenses root not found:", rootId);
        return;
    }

    const $layoutTpl = $("#jwpm-expenses-layout");
    if ($layoutTpl.length === 0) {
        console.warn("JWPM Warning: Expenses layout template missing.");
        return;
    }

    const mount = window.jwpmMountTemplate || function (tpl, $target) {
        $target.html($(tpl).html());
    };

    mount($layoutTpl, $root);

    // Localized Data
    const ajaxUrl = window.jwpmExpenses.ajaxUrl;
    const nonce = window.jwpmExpenses.nonce;
    const actions = window.jwpmExpenses.actions;
    const i18n = window.jwpmExpenses.i18n;

    // Elements
    const $tbody = $root.find('[data-role="expenses-tbody"]');
    const $pagination = $root.find('[data-role="expenses-pagination"]');

    const $sidepanel = $root.find('[data-role="expenses-sidepanel"]');
    const $sidepanelTitle = $sidepanel.find('[data-role="sidepanel-title"]');
    const $form = $sidepanel.find('[data-role="expenses-form"]');
    const $expenseId = $form.find('[data-role="expense-id"]');

    // Fields
    const $fieldDate = $form.find('[data-role="field-date"]');
    const $fieldCategory = $form.find('[data-role="field-category"]');
    const $fieldVendor = $form.find('[data-role="field-vendor"]');
    const $fieldAmount = $form.find('[data-role="field-amount"]');
    const $fieldNotes = $form.find('[data-role="field-notes"]');
    const $fieldReceipt = $form.find('[data-role="field-receipt-url"]');

    // Filters
    const $filterFrom = $root.find('[data-role="filter-from-date"]');
    const $filterTo = $root.find('[data-role="filter-to-date"]');
    const $filterCategory = $root.find('[data-role="filter-category"]');
    const $filterVendor = $root.find('[data-role="filter-vendor"]');

    // Summary
    const $sumExpenses = $root.find('[data-role="expenses-total"] .jwpm-balance-value');

    // Utility: AJAX Wrapper
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

    // Utility
    function format(n) {
        return parseFloat(n).toLocaleString("en-US", { minimumFractionDigits: 2 });
    }

    function getFilters() {
        return {
            from_date: $filterFrom.val(),
            to_date: $filterTo.val(),
            category: $filterCategory.val(),
            vendor: $filterVendor.val(),
        };
    }

    // Sidepanel
    function openPanel(title) {
        $sidepanelTitle.text(title);
        $sidepanel.addClass("open");
    }
    function closePanel() {
        $sidepanel.removeClass("open");
        $form[0].reset();
        $expenseId.val("");
    }

    // -----------------------------
    // Fetch Table
    // -----------------------------
    let currentPage = 1;
    let perPage = 25;

    function loadTable(page = 1) {
        currentPage = page;

        $tbody.html(`<tr><td colspan="6">${i18n.loading}</td></tr>`);

        const filters = getFilters();

        wpAjax(actions.fetch, {
            page: currentPage,
            per_page: perPage,
            from_date: filters.from_date,
            to_date: filters.to_date,
            category: filters.category,
            vendor: filters.vendor,
        })
            .done((res) => {
                if (!res.success) {
                    $tbody.html(`<tr><td colspan="6">${i18n.errorGeneric}</td></tr>`);
                    return;
                }

                const items = res.data.items;

                if (items.length === 0) {
                    $tbody.html(`
                        <tr class="jwpm-empty-row">
                            <td colspan="6">کوئی Expense موجود نہیں۔</td>
                        </tr>
                    `);
                } else {
                    renderRows(items);
                }

                updateSummary(res.data.summary);
                renderPagination(res.data.total, res.data.page, res.data.perPage);
            })
            .fail(() => {
                $tbody.html(`<tr><td colspan="6">${i18n.errorGeneric}</td></tr>`);
            });
    }

    function renderRows(rows) {
        let html = "";

        rows.forEach((r) => {
            html += `
                <tr data-id="${r.id}">
                    <td>${r.expense_date}</td>
                    <td>${r.category}</td>
                    <td>${r.vendor || ""}</td>
                    <td>${r.notes || ""}</td>
                    <td class="jwpm-column-number">${format(r.amount)}</td>
                    <td>
                        <button class="button button-small" data-role="edit-expense">Edit</button>
                        <button class="button button-small" data-role="delete-expense">Delete</button>
                    </td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    function updateSummary(summary) {
        $sumExpenses.text(format(summary.total_amount || 0));
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

    // Pagination click
    $pagination.on("click", "[data-page]", function () {
        const p = parseInt($(this).data("page"));
        loadTable(p);
    });

    // Filters
    $filterFrom.on("change", () => loadTable(1));
    $filterTo.on("change", () => loadTable(1));
    $filterCategory.on("input", () => loadTable(1));
    $filterVendor.on("input", () => loadTable(1));

    // -----------------------------
    // Add / Edit
    // -----------------------------
    $root.find('[data-role="expense-add"]').on("click", function () {
        openPanel("Add Expense");
    });

    $tbody.on("click", '[data-role="edit-expense"]', function () {
        const $tr = $(this).closest("tr");
        const id = $tr.data("id");

        const date = $tr.children().eq(0).text().trim();
        const category = $tr.children().eq(1).text().trim();
        const vendor = $tr.children().eq(2).text().trim();
        const notes = $tr.children().eq(3).text().trim();
        const amount = $tr.children().eq(4).text().replace(/,/g, "");

        openPanel("Edit Expense");

        $expenseId.val(id);
        $fieldDate.val(date);
        $fieldCategory.val(category);
        $fieldVendor.val(vendor);
        $fieldNotes.val(notes);
        $fieldAmount.val(amount);
    });

    $tbody.on("click", '[data-role="delete-expense"]', function () {
        if (!confirm(i18n.confirmDelete)) return;

        const id = $(this).closest("tr").data("id");

        wpAjax(actions.delete, { id: id }).done((res) => {
            if (res.success) loadTable(currentPage);
            else alert(i18n.errorGeneric);
        });
    });

    // -----------------------------
    // Save
    // -----------------------------
    $form.on("submit", function (e) {
        e.preventDefault();

        wpAjax(actions.save, {
            id: $expenseId.val(),
            expense_date: $fieldDate.val(),
            category: $fieldCategory.val(),
            vendor: $fieldVendor.val(),
            amount: $fieldAmount.val(),
            notes: $fieldNotes.val(),
            receipt_url: $fieldReceipt.val(),
        })
            .done((res) => {
                if (res.success) {
                    closePanel();
                    loadTable(currentPage);
                } else {
                    alert(res.data?.message || i18n.errorGeneric);
                }
            })
            .fail(() => alert(i18n.errorGeneric));
    });

    $root.find('[data-role="cancel-expense"], [data-role="sidepanel-close"]').on("click", closePanel);

    // -----------------------------
    // Export
    // -----------------------------
    $root.find('[data-role="expense-export"]').on("click", function () {
        wpAjax(actions.export, {}).done((res) => {
            if (res.success && res.data.rows) {
                window.jwpmExportToExcel("Expenses", res.data.headers, res.data.rows);
            } else {
                alert(i18n.errorGeneric);
            }
        });
    });

    // -----------------------------
    // Import
    // -----------------------------
    $root.find('[data-role="expense-import"]').on("click", function () {
        window.jwpmImportDialog(function (rows) {
            wpAjax(actions.import, { rows: rows }).done((res) => {
                if (res.success) {
                    alert(res.data.message);
                    loadTable(1);
                } else alert(i18n.errorGeneric);
            });
        });
    });

    // -----------------------------
    // Print
    // -----------------------------
    $root.find('[data-role="expense-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Expenses Records");
    });

    // -----------------------------
    // Demo Data
    // -----------------------------
    $root.find('[data-role="expense-demo"]').on("click", function () {
        if (!confirm("Demo Data شامل کریں؟")) return;

        wpAjax(actions.demo, {}).done((res) => {
            if (res.success) {
                alert(res.data.message);
                loadTable(1);
            } else alert(i18n.errorGeneric);
        });
    });

    // Initial Load
    loadTable(1);

    // 🔴 یہاں پر [Expenses JS] ختم ہو رہا ہے

    // ✅ Syntax verified block end

})(jQuery);

