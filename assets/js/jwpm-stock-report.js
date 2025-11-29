/**
 * JWPM — Stock Report JS (Layout E — Teal Blue Analytics)
 * Updated: Direct HTML Injection (No PHP Templates required)
 * یہ فائل Stock Report کی تمام charts, filters, AJAX اور pagination handle کرتی ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Stock Report JS] شروع ہو رہا ہے

    /** Part 1 — JS: Stock Report Page */

    const rootId = (window.jwpmStockReport && window.jwpmStockReport.rootId)
        || "jwpm-stock-report-root";

    const $root = $("#" + rootId);
    if ($root.length === 0) {
        console.warn("JWPM Warning: Stock Report Root Missing:", rootId);
        return;
    }

    // Localized (with safety checks)
    const reportData = window.jwpmStockReport || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        actions: {},
        i18n: {
            loading: 'Loading Stock Report...',
            error: 'Error loading report data.',
            demoConfirm: 'Do you want to generate demo stock report data?'
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
                    <h2 style="margin:0;">📈 Stock & Inventory Report</h2>
                    <div>
                        <button class="button" data-role="stock-export">Export</button>
                        <button class="button" data-role="stock-print">Print</button>
                        <button class="button" data-role="stock-demo">Demo Data</button>
                    </div>
                </div>

                <div style="display:flex; gap:20px; margin-bottom:20px; flex-wrap:wrap;">
                    
                    <div class="jwpm-card" style="padding:15px; flex:1; min-width:300px; display:flex; gap:10px; flex-wrap:wrap;">
                        <input type="text" data-role="filter-category" placeholder="Filter by Category..." style="padding:6px;">
                        
                        <select data-role="filter-metal" style="padding:6px;">
                            <option value="">All Metals</option>
                            <option value="Gold">Gold</option>
                            <option value="Silver">Silver</option>
                        </select>
                        
                        <input type="text" data-role="filter-karat" placeholder="Filter by Karat..." style="padding:6px;">
                        <input type="number" data-role="filter-min-qty" placeholder="Min Qty" style="padding:6px; width:80px;">
                        
                        <button class="button" onclick="jQuery('[data-role^=filter-]').val('').trigger('change');">Clear Filters</button>
                    </div>

                    <div style="flex:2; display:flex; flex-wrap:wrap; gap:10px;">
                        <div class="jwpm-stat-card" style="flex:1; background:#e0f7fa; color:#00bcd4;" data-role="sum-total-items">
                            <div style="font-size:12px;">Unique Items</div>
                            <div class="jwpm-summary-value" style="font-size:1.5em; font-weight:bold;">0</div>
                        </div>
                        <div class="jwpm-stat-card" style="flex:1; background:#b2ebf2; color:#0097a7;" data-role="sum-total-qty">
                            <div style="font-size:12px;">Total Qty in Stock</div>
                            <div class="jwpm-summary-value" style="font-size:1.5em; font-weight:bold;">0</div>
                        </div>
                        <div class="jwpm-stat-card" style="flex:1; background:#80deea; color:#00838f;" data-role="sum-total-weight">
                            <div style="font-size:12px;">Total Weight (g)</div>
                            <div class="jwpm-summary-value" style="font-size:1.5em; font-weight:bold;">0.00</div>
                        </div>
                         <div class="jwpm-stat-card" style="flex:1; background:#4dd0e1; color:#006064;" data-role="sum-stock-value">
                            <div style="font-size:12px;">Total Stock Value</div>
                            <div class="jwpm-summary-value" style="font-size:1.5em; font-weight:bold;">0.00</div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:20px; margin-bottom:20px;">
                    
                    <div class="jwpm-card" style="padding:15px; flex:1;">
                        <h4 style="margin-top:0;">Stock Value by Category</h4>
                        <canvas id="jwpm-stock-donut-chart" style="max-height:350px;"></canvas>
                    </div>
                    
                    <div class="jwpm-card" style="padding:15px; flex:1;">
                        <h4 style="margin-top:0;">Top 10 Stock Value Items</h4>
                        <canvas id="jwpm-stock-bar-chart" style="max-height:350px;"></canvas>
                    </div>
                </div>

                <div class="jwpm-card" style="padding:15px;">
                    <h3 style="margin-top:0;">Detailed Stock List</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Metal</th>
                                <th>Karat</th>
                                <th style="text-align:right;">Qty</th>
                                <th style="text-align:right;">Weight (g)</th>
                                <th style="text-align:right;">Value</th>
                            </tr>
                        </thead>
                        <tbody data-role="stock-tbody">
                            <tr><td colspan="8">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="tablenav bottom">
                    <div class="tablenav-pages" data-role="stock-pagination"></div>
                </div>
            </div>
        `);
    }

    renderLayout(); // Inject the UI immediately


    // ---------------------------------------------------------
    // Element Caching (Post-Render)
    // ---------------------------------------------------------
    
    // Elements
    const $tbody = $root.find('[data-role="stock-tbody"]');
    const $pagination = $root.find('[data-role="stock-pagination"]');

    // Summary Cards
    const $sumTotalItems  = $root.find('[data-role="sum-total-items"] .jwpm-summary-value');
    const $sumTotalQty    = $root.find('[data-role="sum-total-qty"] .jwpm-summary-value');
    const $sumTotalWeight = $root.find('[data-role="sum-total-weight"] .jwpm-summary-value');
    const $sumStockValue  = $root.find('[data-role="sum-stock-value"] .jwpm-summary-value');

    // Filters
    const $filterCat  = $root.find('[data-role="filter-category"]');
    const $filterMetal= $root.find('[data-role="filter-metal"]');
    const $filterKarat= $root.find('[data-role="filter-karat"]');
    const $filterMin  = $root.find('[data-role="filter-min-qty"]');

    // Charts
    const barCanvas   = document.getElementById("jwpm-stock-bar-chart");
    const donutCanvas = document.getElementById("jwpm-stock-donut-chart");

    let barChart  = null;
    let donutChart = null;

    // Utils
    function wpAjax(action, data) {
        return $.ajax({
            url: ajaxUrl,
            method: "POST",
            data: Object.assign({}, data, {
                action: action,
                nonce : nonce
            })
        });
    }

    function format(n) {
        // Ensure numbers are parsed safely before formatting
        const num = parseFloat(n || 0);
        return num.toLocaleString("en-US", {
            minimumFractionDigits: 2
        });
    }

    function getFilters() {
        return {
            category: $filterCat.val(),
            metal   : $filterMetal.val(),
            karat   : $filterKarat.val(),
            min_qty : $filterMin.val(),
        };
    }


    // =============================
    // Fetch + Render (Unchanged Logic)
    // =============================
    let currentPage = 1;
    let perPage = 30;

    function loadStock(page = 1) {

        currentPage = page;

        $tbody.html(`<tr><td colspan="8">${i18n.loading}</td></tr>`);

        const filters = getFilters();

        wpAjax(actions.fetch, {
            page: currentPage,
            per_page : perPage,
            category : filters.category,
            metal    : filters.metal,
            karat    : filters.karat,
            min_qty  : filters.min_qty
        })
        .done((res) => {

            if (!res.success) {
                $tbody.html(`<tr><td colspan="8">${i18n.error}</td></tr>`);
                return;
            }

            const rows    = res.data.items;
            const summary = res.data.summary;
            const charts  = res.data.charts;

            // Table Rows
            if (rows.length === 0) {
                $tbody.html(`
                    <tr class="jwpm-empty-row">
                        <td colspan="8">کوئی Stock ریکارڈ نہیں ملا۔</td>
                    </tr>
                `);
            } else {
                renderRows(rows);
            }

            // Summary Cards
            updateSummary(summary);

            // Pagination
            renderPagination(res.data.total, res.data.page, res.data.perPage);

            // Charts
            updateBarChart(charts.bar);
            updateDonutChart(charts.donut);

        })
        .fail(() => {
            $tbody.html(`<tr><td colspan="8">${i18n.error}</td></tr>`);
        });

    }

    function renderRows(rows) {
        let html = "";

        rows.forEach((r) => {
            html += `
                <tr>
                    <td>${r.item_code}</td>
                    <td>${r.item_name}</td>
                    <td>${r.category}</td>
                    <td>${r.metal}</td>
                    <td>${r.karat}</td>
                    <td class="jwpm-column-number">${format(r.qty)}</td>
                    <td class="jwpm-column-number">${format(r.weight)}</td>
                    <td class="jwpm-column-number">${format(r.stock_value)}</td>
                </tr>
            `;
        });

        $tbody.html(html);
    }


    function updateSummary(s) {
        $sumTotalItems.text(s.total_items || 0);
        $sumTotalQty.text(s.total_qty || 0);
        $sumTotalWeight.text(format(s.total_weight));
        $sumStockValue.text(format(s.stock_value));
    }


    // Pagination
    function renderPagination(total, page, perPage) {

        const pages = Math.ceil(total / perPage);

        if (pages <= 1) {
            $pagination.html("");
            return;
        }

        let html = "";
        if (page > 1) html += `<button class="button jwpm-page-btn" data-page="${page - 1}">« Prev</button> `;
        html += `<span style="padding:0 10px;">Page ${page} of ${pages}</span>`;
        if (page < pages) html += `<button class="button jwpm-page-btn" data-page="${page + 1}">Next »</button>`;
        
        $pagination.html(html);
    }

    $pagination.on("click", "[data-page]", function () {
        loadStock(parseInt($(this).data("page")));
    });


    // =============================
    // Charts (Unchanged Logic)
    // =============================

    function updateBarChart(dataset) {

        if (!barCanvas || typeof Chart === 'undefined') return;
        if (barChart) barChart.destroy();

        barChart = new Chart(barCanvas, {
            type: "bar",
            data: {
                labels: dataset.labels,
                datasets: [{
                    label: "Stock Value",
                    data: dataset.values,
                    backgroundColor: "#0ea5e9",
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
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
                        "#22d3ee",
                        "#0ea5e9",
                        "#0284c7"
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
    // Filters — Auto Reload
    // =============================
    $filterCat.on("input", () => loadStock(1));
    $filterMetal.on("change", () => loadStock(1));
    $filterKarat.on("input", () => loadStock(1));
    $filterMin.on("input", () => loadStock(1));


    // =============================
    // Export (Unchanged Logic)
    // =============================
    $root.find('[data-role="stock-export"]').on("click", () => {

        wpAjax(actions.export, {})
        .done((res) => {

            if (res.success && res.data.rows) {
                window.jwpmExportToExcel("Stock Report", res.data.headers, res.data.rows);
            } else {
                alert(i18n.error);
            }

        });

    });


    // =============================
    // Print (Unchanged Logic)
    // =============================
    $root.find('[data-role="stock-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Stock Report");
    });


    // =============================
    // Demo Data (Unchanged Logic)
    // =============================
    $root.find('[data-role="stock-demo"]').on("click", function () {

        if (!confirm(i18n.demoConfirm)) return;

        wpAjax(actions.demo, {})
        .done((res) => {
            if (res.success) {
                alert(res.data.message);
                loadStock(1);
            } else {
                alert(i18n.error);
            }
        });

    });


    // Initial Load
    loadStock(1);


    // 🔴 یہاں پر [Stock Report JS] ختم ہو رہا ہے
})(jQuery);
