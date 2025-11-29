/**
 * JWPM — Dashboard JS (Final Home)
 * Updated: Direct HTML Injection (No PHP Templates required)
 * NOTE: Requires Chart.js to be loaded globally.
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Dashboard Page JS] شروع ہو رہا ہے

    const rootId =
        (window.jwpmDashboard && window.jwpmDashboard.rootId) ||
        "jwpm-dashboard-root";

    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Dashboard Root Missing:", rootId);
        return;
    }

    // Localized data from PHP (with fallbacks)
    const dashboardData = window.jwpmDashboard || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        actions: {},
        i18n: {
            loadingGold: 'Loading Gold Rate...',
            goldError: 'Error loading Gold Rate.',
            noLowStock: 'No low stock items found.'
        }
    };
    const ajaxUrl = dashboardData.ajaxUrl;
    const nonce = dashboardData.nonce;
    const actions = dashboardData.actions;
    const i18n = dashboardData.i18n;

    // ---------------------------------------------------------
    // RENDER LAYOUT (Replaces Template Mount)
    // ---------------------------------------------------------
    function renderLayout() {
        $root.html(`
            <div class="jwpm-dashboard-wrapper jwpm-wrapper">
                <div style="display:flex; gap:15px; margin-bottom:20px; flex-wrap:wrap;">
                    <div class="jwpm-stat-card" style="flex:1;">
                        <h4 style="margin-top:0;">Today's Sale</h4>
                        <div data-role="stat-today-sale" style="font-size:2em; color:#2563eb;"><strong>0.00</strong></div>
                    </div>
                    <div class="jwpm-stat-card" style="flex:1;">
                        <h4 style="margin-top:0;">New Customers</h4>
                        <div data-role="stat-today-customers" style="font-size:2em; color:#059669;"><strong>0</strong></div>
                    </div>
                    <div class="jwpm-stat-card" style="flex:1;">
                        <h4 style="margin-top:0;">Items Sold</h4>
                        <div data-role="stat-items-sold" style="font-size:2em; color:#ca8a04;"><strong>0</strong></div>
                    </div>
                    <div class="jwpm-stat-card" style="flex:1;">
                        <h4 style="margin-top:0;">Today's Profit</h4>
                        <div data-role="stat-today-profit" style="font-size:2em; color:#dc2626;"><strong>0.00</strong></div>
                    </div>
                </div>

                <div style="display:flex; gap:15px;">
                    
                    <div style="flex:2; display:flex; flex-direction:column; gap:20px;">
                        
                        <div class="jwpm-card" style="padding:15px; flex:1;">
                            <h4 style="margin-top:0;">Weekly Sales Trend</h4>
                            <canvas id="jwpm-dashboard-line-chart" style="max-height:300px;"></canvas>
                        </div>
                        
                        <div class="jwpm-card" style="padding:15px; flex:1;">
                            <h4 style="margin-top:0;">Top Selling Categories</h4>
                            <canvas id="jwpm-dashboard-bar-chart" style="max-height:300px;"></canvas>
                        </div>
                    </div>

                    <div style="flex:1; display:flex; flex-direction:column; gap:20px;">
                        
                        <div class="jwpm-card" style="padding:15px;">
                            <h4 style="margin-top:0; color:#dc2626;">🚨 Low Stock Alert</h4>
                            <table class="wp-list-table widefat striped">
                                <thead>
                                    <tr><th>Item/Tag</th><th>Category</th><th>Qty</th><th>Wt (g)</th></tr>
                                </thead>
                                <tbody data-role="low-stock-tbody">
                                    <tr><td colspan="4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="jwpm-card" style="padding:15px;">
                            <h4 style="margin-top:0;">Today's Gold Rate (per Tola)</h4>
                            <div data-role="gold-rate-box" style="line-height:1.8;">
                                <span>${i18n.loadingGold}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }

    renderLayout(); // Inject the UI immediately

    // ---------------------------------------------------------
    // Caching Elements (Post-Render)
    // ---------------------------------------------------------
    const $statSale = $root.find('[data-role="stat-today-sale"] strong');
    const $statCust = $root.find('[data-role="stat-today-customers"] strong');
    const $statItems = $root.find('[data-role="stat-items-sold"] strong');
    const $statProfit = $root.find('[data-role="stat-today-profit"] strong');

    const $lowStock = $root.find('[data-role="low-stock-tbody"]');
    const $goldRateBox = $root.find('[data-role="gold-rate-box"]');

    // Chart canvases (must be obtained after render)
    const lineCanvas = document.getElementById("jwpm-dashboard-line-chart");
    const barCanvas = document.getElementById("jwpm-dashboard-bar-chart");

    let lineChart = null;
    let barChart = null;

    // ---------------------------------------------------------
    // AJAX Utility (Unchanged)
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

    // ---------------------------------------------------------
    // TODAY STATS LOAD (Unchanged Logic)
    // ---------------------------------------------------------
    function loadTodayStats() {
        wpAjax(actions.today_stats, {})
            .done((res) => {
                if (!res.success) return;

                const d = res.data;

                $statSale.text(d.today_sale);
                $statCust.text(d.new_customers);
                $statItems.text(d.items_sold);
                $statProfit.text(d.today_profit);
            })
            .fail(() => console.warn("Failed to load today stats"));
    }

    // ---------------------------------------------------------
    // WEEKLY SALES + TOP CATEGORY CHARTS (Unchanged Logic)
    // ---------------------------------------------------------
    // NOTE: This assumes Chart.js library is loaded.
    if (typeof Chart === 'undefined') {
        console.error("JWPM: Chart.js library is required for dashboard charts.");
    }
    
    function loadCharts() {
        if (typeof Chart === 'undefined') return;

        wpAjax(actions.charts, {})
            .done((res) => {
                if (!res.success) return;
                const charts = res.data;
                updateLineChart(charts.weekly);
                updateBarChart(charts.categories);
            })
            .fail(() => console.warn("Chart loading failed"));
    }

    function updateLineChart(dataset) {
        if (!lineCanvas) return;
        if (lineChart) lineChart.destroy();

        lineChart = new Chart(lineCanvas, {
            type: "line",
            data: {
                labels: dataset.labels,
                datasets: [
                    {
                        label: "Sales",
                        data: dataset.values,
                        borderColor: "#2563eb",
                        backgroundColor: "rgba(37,99,235,0.15)",
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
            },
        });
    }

    function updateBarChart(dataset) {
        if (!barCanvas) return;
        if (barChart) barChart.destroy();

        barChart = new Chart(barCanvas, {
            type: "bar",
            data: {
                labels: dataset.labels,
                datasets: [
                    {
                        label: "Sales",
                        data: dataset.values,
                        backgroundColor: "#fbbf24",
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: { beginAtZero: true },
                },
            },
        });
    }

    // ---------------------------------------------------------
    // LOW STOCK ALERTS (Unchanged Logic)
    // ---------------------------------------------------------
    function loadLowStock() {
        wpAjax(actions.low_stock, {})
            .done((res) => {
                if (!res.success) return;
                const rows = res.data;
                if (!rows.length) {
                    $lowStock.html(`
                        <tr class="jwpm-empty-row">
                            <td colspan="4">${i18n.noLowStock}</td>
                        </tr>
                    `);
                    return;
                }

                let html = "";
                rows.forEach((r) => {
                    html += `
                        <tr>
                            <td>${r.item}</td>
                            <td>${r.category}</td>
                            <td>${r.qty}</td>
                            <td>${r.weight}</td>
                        </tr>
                    `;
                });
                $lowStock.html(html);
            })
            .fail(() => console.warn("Failed to load low stock"));
    }

    // ---------------------------------------------------------
    // GOLD RATE WIDGET (Unchanged Logic)
    // ---------------------------------------------------------
    function loadGoldRate() {
        $goldRateBox.html(`<span>${i18n.loadingGold}</span>`);

        wpAjax(actions.gold_rate, {})
            .done((res) => {
                if (!res.success) {
                    $goldRateBox.html(`<span>${i18n.goldError}</span>`);
                    return;
                }

                const g = res.data;

                $goldRateBox.html(`
                    <div class="jwpm-gold-rate-row" style="display:flex; justify-content:space-between;">
                        <strong>24K:</strong> <span>${g["24k"]}</span>
                    </div>
                    <div class="jwpm-gold-rate-row" style="display:flex; justify-content:space-between;">
                        <strong>22K:</strong> <span>${g["22k"]}</span>
                    </div>
                    <div class="jwpm-gold-rate-row" style="display:flex; justify-content:space-between;">
                        <strong>21K:</strong> <span>${g["21k"]}</span>
                    </div>
                `);
            })
            .fail(() => {
                $goldRateBox.html(`<span>${i18n.goldError}</span>`);
            });
    }

    // ---------------------------------------------------------
    // LIVE AUTO REFRESH
    // ---------------------------------------------------------
    setInterval(() => {
        loadTodayStats();
        loadLowStock();
        loadGoldRate();
    }, 60000); // refresh every 1 minute

    // ---------------------------------------------------------
    // INITIAL LOAD
    // ---------------------------------------------------------
    loadTodayStats();
    loadCharts();
    loadLowStock();
    loadGoldRate();

    // 🔴 یہاں پر [Dashboard Page JS] ختم ہو رہا ہے
})(jQuery);
