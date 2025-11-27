/**
 * JWPM — Profit Report JS (Layout D — Orange Business Analytics)
 * یہ (JavaScript) فائل Profit Report کا مکمل UI Behaviour, Charts, AJAX اور Pagination کنٹرول کرتی ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Profit Report JS] شروع ہو رہا ہے

    /** Part 1 — JS: Profit Report Page */

    const rootId = (window.jwpmProfitReport && window.jwpmProfitReport.rootId)
        || "jwpm-profit-report-root";

    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Profit Report Root Missing:", rootId);
        return;
    }

    const $tpl = $("#jwpm-profit-report-layout");
    if ($tpl.length === 0) {
        console.warn("JWPM Warning: Profit Report Template Missing");
        return;
    }

    // Template mount utility
    const mount = window.jwpmMountTemplate || function (tpl, $target) {
        $target.html($(tpl).html());
    };

    mount($tpl, $root);

    // Localized Data (from PHP)
    const ajaxUrl = window.jwpmProfitReport.ajaxUrl;
    const nonce   = window.jwpmProfitReport.nonce;
    const actions = window.jwpmProfitReport.actions;
    const i18n    = window.jwpmProfitReport.i18n;


    // --------------------------------------------------
    // Elements
    // --------------------------------------------------

    const $tbody      = $root.find('[data-role="profit-tbody"]');
    const $pagination = $root.find('[data-role="profit-pagination"]');

    // Summary Cards
    const $sumProfit = $root.find('[data-role="sum-total-profit"] .jwpm-summary-value');
    const $sumSale   = $root.find('[data-role="sum-total-sale"] .jwpm-summary-value');
    const $sumCost   = $root.find('[data-role="sum-total-cost"] .jwpm-summary-value');
    const $sumMargin = $root.find('[data-role="sum-profit-margin"] .jwpm-summary-value');

    // Filters
    const $filterFrom = $root.find('[data-role="filter-from-date"]');
    const $filterTo   = $root.find('[data-role="filter-to-date"]');
    const $filterCust = $root.find('[data-role="filter-customer"]');
    const $filterInv  = $root.find('[data-role="filter-invoice"]');

    // Charts
    const lineCanvas = document.getElementById("jwpm-profit-line-chart");
    const barCanvas  = document.getElementById("jwpm-profit-bar-chart");

    let lineChart = null;
    let barChart  = null;


    // --------------------------------------------------
    // Utilities
    // --------------------------------------------------

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
            to_date  : $filterTo.val(),
            customer : $filterCust.val(),
            invoice  : $filterInv.val()
        };
    }


    // --------------------------------------------------
    // Load + Render
    // --------------------------------------------------

    let currentPage = 1;
    let perPage = 25;

    function loadProfit(page = 1) {

        currentPage = page;

        $tbody.html(`<tr><td colspan="6">${i18n.loading}</td></tr>`);

        const filters = getFilters();

        wpAjax(actions.fetch, {
            page: currentPage,
            per_page: perPage,
            from_date: filters.from_date,
            to_date  : filters.to_date,
            customer : filters.customer,
            invoice  : filters.invoice
        })
        .done((res) => {

            if (!res.success) {
                $tbody.html(`<tr><td colspan="6">${i18n.error}</td></tr>`);
                return;
            }

            const rows    = res.data.items;
            const summary = res.data.summary;
            const charts  = res.data.charts;

            // Table Rows
            if (rows.length === 0) {
                $tbody.html(`
                    <tr class="jwpm-empty-row">
                        <td colspan="6">کوئی Profit ریکارڈ نہیں ملا۔</td>
                    </tr>
                `);
            } else {
                renderRows(rows);
            }

            // Summary Cards
            updateSummary(summary);

            // Pagination
            renderPagination(res.data.total, res.data.page, res.data.perPage);

            // Graphs
            updateLineChart(charts.line);
            updateBarChart(charts.bar);
        })
        .fail(() => {
            $tbody.html(`<tr><td colspan="6">${i18n.error}</td></tr>`);
        });

    }

    function renderRows(rows) {

        let html = "";

        rows.forEach((r) => {
            html += `
                <tr>
                    <td>${r.invoice_no}</td>
                    <td>${r.customer}</td>
                    <td>${r.date}</td>
                    <td class="jwpm-column-number">${format(r.sale_amount)}</td>
                    <td class="jwpm-column-number">${format(r.cost)}</td>
                    <td class="jwpm-column-number">${format(r.profit)}</td>
                </tr>
            `;
        });

        $tbody.html(html);
    }


    function updateSummary(s) {
        $sumProfit.text(format(s.total_profit));
        $sumSale.text(format(s.total_sale));
        $sumCost.text(format(s.total_cost));
        $sumMargin.text(s.margin_percent + "%");
    }


    // --------------------------------------------------
    // Pagination
    // --------------------------------------------------

    function renderPagination(total, page, perPage) {
        const pages = Math.ceil(total / perPage);

        if (pages <= 1) {
            $pagination.html("");
            return;
        }

        let html = "";

        for (let p = 1; p <= pages; p++) {
            html += `<span class="jwpm-page-btn ${p === page ? "active" : ""}" data-page="${p}">${p}</span>`;
        }

        $pagination.html(html);
    }

    $pagination.on("click", "[data-page]", function () {
        loadProfit(parseInt($(this).data("page")));
    });


    // --------------------------------------------------
    // Charts
    // --------------------------------------------------

    function updateLineChart(dataset) {

        if (!lineCanvas) return;
        if (lineChart) lineChart.destroy();

        lineChart = new Chart(lineCanvas, {
            type: "line",
            data: {
                labels: dataset.labels,
                datasets: [{
                    label: "Profit Trend",
                    data: dataset.values,
                    borderColor: "#f97316",
                    backgroundColor: "rgba(249,115,22,0.18)",
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
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
                        label: "Sale",
                        data: dataset.sale_values,
                        backgroundColor: "#fb923c"
                    },
                    {
                        label: "Cost",
                        data: dataset.cost_values,
                        backgroundColor: "#fed7aa"
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: "bottom" }
                },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });
    }


    // --------------------------------------------------
    // Filters Auto Reload
    // --------------------------------------------------
    $filterFrom.on("change", () => loadProfit(1));
    $filterTo.on("change", () => loadProfit(1));
    $filterCust.on("input", () => loadProfit(1));
    $filterInv.on("input", () => loadProfit(1));


    // --------------------------------------------------
    // Export
    // --------------------------------------------------
    $root.find('[data-role="profit-export"]').on("click", () => {

        wpAjax(actions.export, {})
        .done((res) => {

            if (res.success && res.data.rows) {
                window.jwpmExportToExcel("Profit Report", res.data.headers, res.data.rows);
            } else {
                alert(i18n.error);
            }

        });

    });


    // --------------------------------------------------
    // Print
    // --------------------------------------------------
    $root.find('[data-role="profit-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Profit Report");
    });


    // --------------------------------------------------
    // Demo Data
    // --------------------------------------------------
    $root.find('[data-role="profit-demo"]').on("click", function () {

        if (!confirm(i18n.demoConfirm)) return;

        wpAjax(actions.demo, {})
        .done((res) => {
            if (res.success) {
                alert(res.data.message);
                loadProfit(1);
            } else {
                alert(i18n.error);
            }
        });

    });


    // Initial Load
    loadProfit(1);


    // 🔴 یہاں پر [Profit Report JS] ختم ہو رہا ہے
    // ✅ Syntax verified block end

})(jQuery);

