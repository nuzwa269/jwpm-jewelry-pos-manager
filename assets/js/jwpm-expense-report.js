/**
 * JWPM — Expense Report JS (Layout C — Purple Royal UI)
 * Updated: Direct HTML Injection (No PHP Templates required)
 * یہ فائل Expense Report کی تمام UI Activities, AJAX, Charts اور Pagination control کرتی ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Expense Report JS] شروع ہو رہا ہے

    /** Part 1 — JS: Expense Report Page */

    const rootId = (window.jwpmExpenseReport && window.jwpmExpenseReport.rootId)
        || "jwpm-expense-report-root";

    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Expense Report root missing:", rootId);
        return;
    }

    // Localized (with fallbacks)
    const reportData = window.jwpmExpenseReport || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        actions: {},
        i18n: {
            loading: 'Loading Report...',
            error: 'Error loading report data.',
            demoConfirm: 'Do you want to generate demo expense report data?'
        }
    };
    const ajaxUrl = reportData.ajaxUrl;
    const nonce = reportData.nonce;
    const actions = reportData.actions;
    const i18n = reportData.i18n;

    // ---------------------------------------------------------
    // RENDER LAYOUT (Replaces Template Mount)
    // ---------------------------------------------------------
    function renderLayout() {
        $root.html(`
            <div class="jwpm-wrapper">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #eee;">
                    <h2 style="margin:0;">📊 Expense Report (Category & Vendor)</h2>
                    <div>
                        <button class="button" data-role="expense-export">Export</button>
                        <button class="button" data-role="expense-print">Print</button>
                        <button class="button" data-role="expense-demo">Demo Data</button>
                    </div>
                </div>

                <div style="display:flex; gap:20px; margin-bottom:20px; align-items:flex-start;">
                    
                    <div class="jwpm-card" style="padding:15px; flex:1; min-width:250px;">
                        <h4 style="margin-top:0;">Filters</h4>
                        <label>From Date:</label>
                        <input type="date" data-role="filter-from-date" class="widefat" style="margin-bottom:10px;">
                        
                        <label>To Date:</label>
                        <input type="date" data-role="filter-to-date" class="widefat" style="margin-bottom:10px;">
                        
                        <label>Category:</label>
                        <input type="text" data-role="filter-category" placeholder="Filter by Category" class="widefat" style="margin-bottom:10px;">
                        
                        <label>Vendor:</label>
                        <input type="text" data-role="filter-vendor" placeholder="Filter by Vendor" class="widefat" style="margin-bottom:10px;">
                        
                        <button class="button" onclick="jQuery('[data-role^=filter-]').val('').trigger('change');">Clear</button>
                    </div>

                    <div style="flex:2; display:flex; flex-wrap:wrap; gap:15px;">
                        <div class="jwpm-stat-card" style="flex:1; background:#8b5cf6; color:#fff;" data-role="sum-total-expense">
                            <h4 style="margin-top:0; color:#fff;">Total Expense</h4>
                            <div class="jwpm-summary-value" style="font-size:1.8em; font-weight:bold;">0.00</div>
                        </div>
                        <div class="jwpm-stat-card" style="flex:1; background:#a78bfa; color:#fff;" data-role="sum-categories">
                            <h4 style="margin-top:0; color:#fff;">Categories Count</h4>
                            <div class="jwpm-summary-value" style="font-size:1.8em; font-weight:bold;">0</div>
                        </div>
                        <div class="jwpm-stat-card" style="flex:1; background:#c084fc; color:#fff;" data-role="sum-vendors">
                            <h4 style="margin-top:0; color:#fff;">Unique Vendors</h4>
                            <div class="jwpm-summary-value" style="font-size:1.8em; font-weight:bold;">0</div>
                        </div>
                         <div class="jwpm-stat-card" style="flex:1; background:#d8b4fe; color:#333;" data-role="sum-average-expense">
                            <h4 style="margin-top:0; color:#333;">Average Expense</h4>
                            <div class="jwpm-summary-value" style="font-size:1.8em; font-weight:bold;">0.00</div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:20px; margin-bottom:20px;">
                    
                    <div class="jwpm-card" style="padding:15px; flex:1;">
                        <h4 style="margin-top:0;">Expense Distribution (By Category)</h4>
                        <canvas id="jwpm-expense-donut-chart" style="max-height:350px;"></canvas>
                    </div>
                    
                    <div class="jwpm-card" style="padding:15px; flex:1;">
                        <h4 style="margin-top:0;">Top 10 Category Spend</h4>
                        <canvas id="jwpm-expense-bar-chart" style="max-height:350px;"></canvas>
                    </div>
                </div>

                <div class="jwpm-card" style="padding:15px;">
                    <h3 style="margin-top:0;">Detailed Expense List</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Vendor</th>
                                <th style="text-align:right;">Amount</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody data-role="expense-tbody">
                            <tr><td colspan="5">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="tablenav bottom">
                    <div class="tablenav-pages" data-role="expense-pagination"></div>
                </div>
            </div>
        `);
    }

    renderLayout(); // Inject the UI immediately

    // ---------------------------------------------------------
    // Caching Elements (Post-Render)
    // ---------------------------------------------------------

    // Elements
    const $tbody = $root.find('[data-role="expense-tbody"]');
    const $pagination = $root.find('[data-role="expense-pagination"]');

    // Summary cards
    const $sumTotalExpense = $root.find('[data-role="sum-total-expense"] .jwpm-summary-value');
    const $sumCategories = $root.find('[data-role="sum-categories"] .jwpm-summary-value');
    const $sumVendors = $root.find('[data-role="sum-vendors"] .jwpm-summary-value');
    const $sumAverage = $root.find('[data-role="sum-average-expense"] .jwpm-summary-value');

    // Filters
    const $filterFrom = $root.find('[data-role="filter-from-date"]');
    const $filterTo = $root.find('[data-role="filter-to-date"]');
    const $filterCat = $root.find('[data-role="filter-category"]');
    const $filterVendor = $root.find('[data-role="filter-vendor"]');

    // Charts
    const barCanvas = document.getElementById("jwpm-expense-bar-chart");
    const donutCanvas = document.getElementById("jwpm-expense-donut-chart");

    let barChart = null;
    let donutChart = null;

    // Utilities
    function wpAjax(action, data) {
        return $.ajax({
            url: ajaxUrl,
            method: "POST",
            data: Object.assign({}, data, {
                action: action,
                nonce: nonce
            })
        });
    }

    function format(n) {
        return parseFloat(n || 0).toLocaleString("en-US", {
            minimumFractionDigits: 2
        });
    }

    function getFilters() {
        return {
            from_date: $filterFrom.val(),
            to_date: $filterTo.val(),
            category: $filterCat.val(),
            vendor: $filterVendor.val()
        };
    }

    // =============================
    // Fetch + Render (Unchanged Logic)
    // =============================
    let currentPage = 1;
    let perPage = 25;

    function loadExpenses(page = 1) {
        currentPage = page;

        $tbody.html(`<tr><td colspan="5">${i18n.loading}</td></tr>`);

        const filters = getFilters();

        wpAjax(actions.fetch, {
            page: currentPage,
            per_page: perPage,
            from_date: filters.from_date,
            to_date: filters.to_date,
            category: filters.category,
            vendor: filters.vendor
        })
            .done((res) => {

                if (!res.success) {
                    $tbody.html(`<tr><td colspan="5">${i18n.error}</td></tr>`);
                    return;
                }

                const rows = res.data.items;
                const summary = res.data.summary;
                const charts = res.data.charts;

                // Table
                if (rows.length === 0) {
                    $tbody.html(`
                        <tr class="jwpm-empty-row">
                            <td colspan="5">کوئی خرچہ (Expense) ریکارڈ نہیں ملا۔</td>
                        </tr>
                    `);
                } else {
                    renderRows(rows);
                }

                // Summary
                updateSummary(summary);

                // Pagination
                renderPagination(res.data.total, res.data.page, res.data.perPage);

                // Charts
                updateBarChart(charts.bar);
                updateDonutChart(charts.donut);

            })
            .fail(() => {
                $tbody.html(`<tr><td colspan="5">${i18n.error}</td></tr>`);
            });
    }

    function renderRows(rows) {
        let html = "";
        rows.forEach((r) => {
            html += `
                <tr>
                    <td>${r.date}</td>
                    <td>${r.category}</td>
                    <td>${r.vendor}</td>
                    <td class="jwpm-column-number">${format(r.amount)}</td>
                    <td>${r.note}</td>
                </tr>
            `;
        });
        $tbody.html(html);
    }

    function updateSummary(s) {
        $sumTotalExpense.text(format(s.total_expense));
        $sumCategories.text(s.categories); // Assuming categories count is not currency
        $sumVendors.text(s.vendors);       // Assuming vendors count is not currency
        $sumAverage.text(format(s.average_expense));
    }

    function renderPagination(total, page, perPage) {
        const pages = Math.ceil(total / perPage);
        if (pages <= 1) {
            $pagination.html("");
            return;
        }

        let html = "";
        // Simplified pagination buttons for injected layout
        if (page > 1) html += `<button class="button jwpm-page-btn" data-page="${page - 1}">« Prev</button> `;
        html += `<span style="padding:0 10px;">Page ${page} of ${pages}</span>`;
        if (page < pages) html += `<button class="button jwpm-page-btn" data-page="${page + 1}">Next »</button>`;
        
        $pagination.html(html);
    }

    $pagination.on("click", "[data-page]", function () {
        loadExpenses(parseInt($(this).data("page")));
    });

    // =============================
    // Charts (Unchanged Logic)
    // =============================
    
    // NOTE: This assumes Chart.js library is loaded globally.

    function updateBarChart(dataset) {
        if (!barCanvas || typeof Chart === 'undefined') return;
        if (barChart) barChart.destroy();

        barChart = new Chart(barCanvas, {
            type: "bar",
            data: {
                labels: dataset.labels,
                datasets: [{
                    label: "Expenses",
                    data: dataset.values,
                    backgroundColor: [
                        "#8b5cf6",
                        "#a78bfa",
                        "#c084fc",
                        "#d8b4fe"
                    ],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    function updateDonutChart(dataset) {
        if (!donutCanvas || typeof Chart === 'undefined') return;
        if (donutChart) donutChart.destroy();

        donutChart = new Chart(donutCanvas, {
            type: "doughnut",
            data: {
                labels: dataset.labels,
                datasets: [{
                    data: dataset.values,
                    backgroundColor: [
                        "#6d28d9",
                        "#8b5cf6",
                        "#a78bfa",
                        "#c084fc"
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                cutout: "55%",
                plugins: {
                    legend: { position: "bottom" }
                }
            }
        });
    }

    // =============================
    // Filters Auto Reload (Unchanged Logic)
    // =============================
    $filterFrom.on("change", () => loadExpenses(1));
    $filterTo.on("change", () => loadExpenses(1));
    $filterCat.on("input", () => loadExpenses(1));
    $filterVendor.on("input", () => loadExpenses(1));

    // =============================
    // Export (Unchanged Logic)
    // =============================
    $root.find('[data-role="expense-export"]').on("click", () => {
        wpAjax(actions.export, {})
        .done((res) => {
            if (res.success && res.data.rows) {
                window.jwpmExportToExcel("Expense Report", res.data.headers, res.data.rows);
            } else {
                alert(i18n.error);
            }
        });
    });

    // =============================
    // Print (Unchanged Logic)
    // =============================
    $root.find('[data-role="expense-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Expense Report");
    });

    // =============================
    // Demo Data (Unchanged Logic)
    // =============================
    $root.find('[data-role="expense-demo"]').on("click", function () {

        if (!confirm(i18n.demoConfirm)) return;

        wpAjax(actions.demo, {})
        .done((res) => {
            if (res.success) {
                alert(res.data.message);
                loadExpenses(1);
            } else {
                alert(i18n.error);
            }
        });
    });

    // Initial Load
    loadExpenses(1);

    // 🔴 یہاں پر [Expense Report JS] ختم ہو رہا ہے

})(jQuery);
