/**
 * JWPM — Accounts Expenses JS
 * Updated: Direct HTML Injection (No PHP Templates required)
 * یہ فائل Expenses Page کا UI Behaviour + AJAX Calls + Table Rendering سنبھالتی ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Expenses JS] شروع ہو رہا ہے

    // 1. Root & Config (Safe Fallbacks)
    const rootId = (window.jwpmExpenses && window.jwpmExpenses.rootId) || "jwpm-expenses-root";
    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Expenses root not found:", rootId);
        return;
    }

    // Localized Data (with safety checks)
    const expensesData = window.jwpmExpenses || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        actions: {},
        i18n: {
            loading: 'Loading Expenses...',
            errorGeneric: 'Error processing request.',
            confirmDelete: 'Are you sure you want to delete this expense?'
        }
    };
    const ajaxUrl = expensesData.ajaxUrl;
    const nonce = expensesData.nonce;
    const actions = expensesData.actions;
    const i18n = expensesData.i18n;

    // ---------------------------------------------------------
    // RENDER LAYOUT (Replaces Template Mount)
    // ---------------------------------------------------------
    function renderLayout() {
        $root.html(`
            <div class="jwpm-wrapper">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #eee;">
                    <h2 style="margin:0;">💸 Expenses Management</h2>
                    <div>
                        <button class="button button-primary" data-role="expense-add">+ Add Expense</button>
                        <button class="button" data-role="expense-export">Export</button>
                        <button class="button" data-role="expense-demo">Demo Data</button>
                    </div>
                </div>

                <div class="jwpm-card" style="margin-bottom:20px; padding:20px; text-align:center; background:#ffeded; border:1px solid #ff000030;">
                    <span style="color:#777; display:block;">Total Expenses in Period</span>
                    <h3 data-role="expenses-total" style="margin:5px 0; color:#d63638; font-size:2em;">
                        <span class="jwpm-balance-value">0.00</span>
                    </h3>
                </div>

                <div class="jwpm-card" style="padding:15px; margin-bottom:20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <label>From: <input type="date" data-role="filter-from-date" style="padding:5px;"></label>
                    <label>To: <input type="date" data-role="filter-to-date" style="padding:5px;"></label>
                    
                    <input type="text" data-role="filter-category" placeholder="Filter by Category..." style="padding:6px;">
                    <input type="text" data-role="filter-vendor" placeholder="Filter by Vendor..." style="padding:6px;">
                    
                    <button class="button" onclick="jQuery('[data-role^=filter-][type=date], [data-role^=filter-][type=text]').val('').trigger('change');">Clear Filters</button>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Vendor</th>
                            <th>Notes</th>
                            <th style="text-align:right;">Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody data-role="expenses-tbody">
                        <tr><td colspan="6">Loading...</td></tr>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <div class="tablenav-pages" data-role="expenses-pagination"></div>
                </div>

                <div data-role="expenses-sidepanel" class="jwpm-sidepanel" style="display:none; position:fixed; top:0; right:0; width:400px; height:100%; background:#fff; box-shadow:-2px 0 5px rgba(0,0,0,0.1); z-index:9999; padding:20px; overflow-y:auto;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                        <h2 data-role="sidepanel-title" style="margin:0;">Add Expense</h2>
                        <button class="button" data-role="sidepanel-close">Close ❌</button>
                    </div>
                    
                    <form data-role="expenses-form">
                        <input type="hidden" data-role="expense-id">
                        
                        <label>Date <span style="color:red">*</span></label>
                        <input type="date" data-role="field-date" class="widefat" required style="margin-bottom:10px;">
                        
                        <label>Category <span style="color:red">*</span></label>
                        <input type="text" data-role="field-category" class="widefat" list="jwpm-expense-cats" required style="margin-bottom:10px;">
                        <datalist id="jwpm-expense-cats">
                            <option value="Salary">
                            <option value="Utility Bills">
                            <option value="Rent">
                            <option value="Maintenance">
                            <option value="Marketing">
                        </datalist>

                        <label>Vendor / Recipient</label>
                        <input type="text" data-role="field-vendor" class="widefat" style="margin-bottom:10px;">

                        <label>Amount <span style="color:red">*</span></label>
                        <input type="number" step="0.01" data-role="field-amount" class="widefat" required style="margin-bottom:10px;">
                        
                        <label>Receipt URL / Link</label>
                        <input type="url" data-role="field-receipt-url" class="widefat" style="margin-bottom:10px;">

                        <label>Notes</label>
                        <textarea data-role="field-notes" class="widefat" style="height:80px; margin-bottom:20px;"></textarea>

                        <button type="submit" class="button button-primary button-large" style="width:100%;">Save Expense</button>
                    </form>
                </div>
            </div>
        `);
    }
    
    // Inject Layout before continuing with existing logic
    renderLayout();

    // ---------------------------------------------------------
    // Caching Elements (Post-Render)
    // ---------------------------------------------------------
    
    // Elements (Now cached against the injected HTML)
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
    
    // ---------------------------------------------------------
    // Utility: AJAX Wrapper (Unchanged)
    // ---------------------------------------------------------
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

    // Utility (Unchanged)
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

    // Sidepanel (Unchanged)
    function openPanel(title) {
        $sidepanelTitle.text(title);
        $sidepanel.addClass("open").show(); // Use show() for injected element
        $form[0].reset();
        $expenseId.val("");
    }
    function closePanel() {
        $sidepanel.removeClass("open").hide(); // Use hide() for injected element
        $form[0].reset();
        $expenseId.val("");
    }

    // -----------------------------
    // Fetch Table (Unchanged Logic)
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
            // Store full data in row for easier editing, similar to other modules
            const json = JSON.stringify(r).replace(/'/g, "&#39;"); 

            html += `
                <tr data-id="${r.id}" data-json='${json}'>
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
        // Simplified pagination buttons for injected layout
        if (page > 1) html += `<button class="button jwpm-page-btn" data-page="${page - 1}">« Prev</button> `;
        html += `<span style="padding:0 10px;">Page ${page} of ${totalPages}</span>`;
        if (page < totalPages) html += `<button class="button jwpm-page-btn" data-page="${page + 1}">Next »</button>`;
        
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
    // Add / Edit (Logic Modified for JSON data attribute)
    // -----------------------------
    $root.find('[data-role="expense-add"]').on("click", function () {
        openPanel("Add Expense");
        $fieldDate.val(new Date().toISOString().split('T')[0]); // Default to today
    });

    $tbody.on("click", '[data-role="edit-expense"]', function () {
        const $tr = $(this).closest("tr");
        const data = $tr.data("json"); // Get data from injected attribute

        if (!data) return; 

        openPanel("Edit Expense");

        $expenseId.val(data.id);
        $fieldDate.val(data.expense_date);
        $fieldCategory.val(data.category);
        $fieldVendor.val(data.vendor);
        $fieldNotes.val(data.notes);
        $fieldAmount.val(data.amount);
        $fieldReceipt.val(data.receipt_url); // Populate receipt field
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
    // Save (Unchanged Logic)
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
    // Export (Unchanged Logic)
    // -----------------------------
    $root.find('[data-role="expense-export"]').on("click", function () {
        wpAjax(actions.export, {}).done((res) => {
            if (res.success && res.data.rows) {
                // Assumes jwpmExportToExcel is available via jwpm-common.js
                window.jwpmExportToExcel("Expenses", res.data.headers, res.data.rows);
            } else {
                alert(i18n.errorGeneric);
            }
        });
    });

    // -----------------------------
    // Import (Unchanged Logic)
    // -----------------------------
    $root.find('[data-role="expense-import"]').on("click", function () {
        // Assumes jwpmImportDialog is available via jwpm-common.js
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
    // Print (Unchanged Logic)
    // -----------------------------
    $root.find('[data-role="expense-print"]').on("click", function () {
        // Assumes jwpmPrintTable is available via jwpm-common.js
        window.jwpmPrintTable($root.find("table")[0], "Expenses Records");
    });

    // -----------------------------
    // Demo Data (Unchanged Logic)
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

})(jQuery);
