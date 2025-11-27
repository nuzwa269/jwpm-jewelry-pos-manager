/**
 * JWPM — Dashboard JS (Final Home)
 * یہ (JavaScript) فائل Dashboard کے تمام Live Stats, Charts, Alerts, Gold API کو handle کرتی ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Dashboard Page JS] شروع ہو رہا ہے

    /** Part 1 — JS: Dashboard Page */

    const rootId =
        (window.jwpmDashboard && window.jwpmDashboard.rootId) ||
        "jwpm-dashboard-root";

    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Dashboard Root Missing:", rootId);
        return;
    }

    const $tpl = $("#jwpm-dashboard-layout");
    if ($tpl.length === 0) {
        console.warn("JWPM Warning: Dashboard Template Missing");
        return;
    }

    // Template Mount Utility
    const mount =
        window.jwpmMountTemplate ||
        function (tpl, $target) {
            $target.html($(tpl).html());
        };

    mount($tpl, $root);

    // Localized data from PHP
    const ajaxUrl = window.jwpmDashboard.ajaxUrl;
    const nonce = window.jwpmDashboard.nonce;
    const actions = window.jwpmDashboard.actions;
    const i18n = window.jwpmDashboard.i18n;


    // ---------------------------------------------------------
    // Elements
    // ---------------------------------------------------------
    const $statSale = $root.find('[data-role="stat-today-sale"] strong');
    const $statCust = $root.find('[data-role="stat-today-customers"] strong');
    const $statItems = $root.find('[data-role="stat-items-sold"] strong');
    const $statProfit = $root.find('[data-role="stat-today-profit"] strong');

    const $lowStock = $root.find('[data-role="low-stock-tbody"]');
    const $goldRateBox = $root.find('[data-role="gold-rate-box"]');

    // Chart canvases
    const lineCanvas = document.getElementById("jwpm-dashboard-line-chart");
    const barCanvas = document.getElementById("jwpm-dashboard-bar-chart");

    let lineChart = null;
    let barChart = null;


    // ---------------------------------------------------------
    // AJAX Utility
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
    // TODAY STATS LOAD
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
    // WEEKLY SALES + TOP CATEGORY CHARTS
    // ---------------------------------------------------------
    function loadCharts() {
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
    // LOW STOCK ALERTS
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
    // GOLD RATE WIDGET
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
                    <div class="jwpm-gold-rate-row">
                        <strong>24K:</strong> <span>${g["24k"]}</span>
                    </div>
                    <div class="jwpm-gold-rate-row">
                        <strong>22K:</strong> <span>${g["22k"]}</span>
                    </div>
                    <div class="jwpm-gold-rate-row">
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
    // ✅ Syntax verified block end

})(jQuery);

