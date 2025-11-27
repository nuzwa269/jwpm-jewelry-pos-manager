/**
 * JWPM — Sales Report JS (Layout A — Blue Premium Dashboard)
 * یہ فائل Sales Report کا مکمل UI Behaviour + AJAX + Graphs handle کرتی ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Sales Report JS] شروع ہو رہا ہے

    /** Part 1 — JS: Sales Report Page */

    // Root mount
    const rootId = (window.jwpmSalesReport && window.jwpmSalesReport.rootId) || "jwpm-sales-report-root";
    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Sales Report root missing:", rootId);
        return;
    }

    const $layoutTpl = $("#jwpm-sales-report-layout");
    if ($layoutTpl.length === 0) {
        console.warn("JWPM Warning: Sales Report template not found");
        return;
    }

    // Template mount
    const mount = window.jwpmMountTemplate || function (tpl, $target) {
        $target.html($(tpl).html());
    };

    mount($layoutTpl, $root);

    // Localized data
    const ajaxUrl = window.jwpmSalesReport.ajaxUrl;
    const nonce   = window.jwpmSalesReport.nonce;
    const actions = window.jwpmSalesReport.actions;
    const i18n    = window.jwpmSalesReport.i18n;


    // =============================
    // Element references
    // =============================

    // Summary Cards
    const $sumSales   = $root.find('[data-role="sum-total-sales"] .jwpm-summary-value');
    const $sumItems   = $root.find('[data-role="sum-total-items"] .jwpm-summary-value');
    const $sumInvoice = $root.find('[data-role="sum-average-invoice"] .jwpm-summary-value');
    const $sumProfit  = $root.find('[data-role="sum-profit"] .jwpm-summary-value');

    // Filters
    const $filterFrom = $root.find('[data-role="filter-from-date"]');
    const $filterTo   = $root.find('[data-role="filter-to-date"]');
    const $filterCust = $root.find('[data-role="filter-customer"]');
    const $filterInv  = $root.find('[data-role="filter-invoice"]');

    // Table + Pagination
    const $tbody      = $root.find('[data-role="sales-tbody"]');
    const $pagination = $root.find('[data-role="sales-pagination"]');

    // Chart Elements
    const lineCanvas  = document.getElementById("jwpm-sales-line-chart");
    const barCanvas   = document.getElementById("jwpm-sales-bar-chart");

    let lineChart = null;
    let barChart  = null;

    // =============================
    // Utilities
    // =============================
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
        return parseFloat(n || 0).toLocaleString("en-US", { minimumFractionDigits: 2 });
    }

    function getFilters() {
        return {
            from_date : $filterFrom.val(),
            to_date   : $filterTo.val(),
            customer  : $filterCust.val(),
            invoice   : $filterInv.val()
        };
    }


    // =============================
    // Load Table + Summary + Charts
    // =============================
    let currentPage = 1;
    let perPage = 25;

    function loadSales(page = 1) {
        currentPage = page;

        $tbody.html(`<tr><td colspan="6">${i18n.loading}</td></tr>`);

        const filters = getFilters();

        wpAjax(actions.fetch, {
            page: currentPage,
            per_page: perPage,
            from_date : filters.from_date,
            to_date   : filters.to_date,
            customer  : filters.customer,
            invoice   : filters.invoice
        })
        .done((res) => {
            if (!res.success) {
                $tbody.html(`<tr><td colspan="6">${i18n.error}</td></tr>`);
                return;
            }

            const rows = res.data.items;
            const summary = res.data.summary;
            const charts  = res.data.charts;

            // Table
            if (rows.length === 0) {
                $tbody.html(`
                    <tr class="jwpm-empty-row">
                        <td colspan="6">کوئی Sales ریکارڈ نہیں ملا۔</td>
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
                    <td>${r.qty}</td>
                    <td class="jwpm-column-number">${format(r.total)}</td>
                    <td class="jwpm-column-number">${format(r.profit)}</td>
                </tr>
            `;
        });

        $tbody.html(html);
    }


    function updateSummary(s) {
        $sumSales.text(format(s.total_sales));
        $sumItems.text(format(s.total_items));
        $sumInvoice.text(format(s.average_invoice));
        $sumProfit.text(format(s.total_profit));
    }


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
        loadSales(parseInt($(this).data("page")));
    });


    // =============================
    // Charts
    // =============================

    function updateLineChart(dataset) {
        if (!lineCanvas) return;
        if (lineChart) lineChart.destroy();

        lineChart = new Chart(lineCanvas, {
            type: "line",
            data: {
                labels: dataset.labels,
                datasets: [{
                    label: "Daily Sales",
                    data: dataset.values,
                    borderColor: "#2563EB",
                    backgroundColor: "rgba(37,99,235,0.15)",
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } }
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
                datasets: [{
                    label: "Category Sales",
                    data: dataset.values,
                    backgroundColor: [
                        "#4F46E5",
                        "#2563EB",
                        "#0EA5E9",
                        "#10B981",
                        "#F59E0B",
                        "#EF4444"
                    ],
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }


    // =============================
    // Filters Auto Reload
    // =============================
    $filterFrom.on("change", () => loadSales(1));
    $filterTo.on("change", () => loadSales(1));
    $filterCust.on("input", () => loadSales(1));
    $filterInv.on("input", () => loadSales(1));


    // =============================
    // Export
    // =============================
    $root.find('[data-role="sales-export"]').on("click", () => {
        wpAjax(actions.export, {})
        .done((res) => {
            if (res.success && res.data.rows) {
                window.jwpmExportToExcel("Sales Report", res.data.headers, res.data.rows);
            } else {
                alert(i18n.error);
            }
        });
    });


    // =============================
    // Print
    // =============================
    $root.find('[data-role="sales-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Sales Report");
    });


    // =============================
    // Demo Data
    // =============================
    $root.find('[data-role="sales-demo"]').on("click", function () {
        if (!confirm(i18n.demoConfirm)) return;

        wpAjax(actions.demo, {})
        .done((res) => {
            if (res.success) {
                alert(res.data.message);
                loadSales(1);
            } else {
                alert(i18n.error);
            }
        });
    });


    // Initial Load
    loadSales(1);

    // 🔴 یہاں پر [Sales Report JS] ختم ہو رہا ہے

    // ✅ Syntax verified block end

})(jQuery);

