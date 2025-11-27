/**
 * JWPM — Stock Report JS (Layout E — Teal Blue Analytics)
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

    const $tpl = $("#jwpm-stock-report-layout");
    if ($tpl.length === 0) {
        console.warn("JWPM Warning: Stock Report Template Missing");
        return;
    }

    // Template mount function
    const mount = window.jwpmMountTemplate || function (tpl, $target) {
        $target.html($(tpl).html());
    };
    mount($tpl, $root);


    // Localized
    const ajaxUrl = window.jwpmStockReport.ajaxUrl;
    const nonce   = window.jwpmStockReport.nonce;
    const actions = window.jwpmStockReport.actions;
    const i18n    = window.jwpmStockReport.i18n;


    // Elements
    const $tbody      = $root.find('[data-role="stock-tbody"]');
    const $pagination = $root.find('[data-role="stock-pagination"]');

    // Summary Cards
    const $sumTotalItems  = $root.find('[data-role="sum-total-items"] .jwpm-summary-value');
    const $sumTotalQty    = $root.find('[data-role="sum-total-qty"] .jwpm-summary-value');
    const $sumTotalWeight = $root.find('[data-role="sum-total-weight"] .jwpm-summary-value');
    const $sumStockValue  = $root.find('[data-role="sum-stock-value"] .jwpm-summary-value');

    // Filters
    const $filterCat  = $root.find('[data-role="filter-category"]');
    const $filterMetal= $root.find('[data-role="filter-metal"]');
    const $filterKarat= $root.find('[data-role="filter-karat"]');
    const $filterMin  = $root.find('[data-role="filter-min-qty"]');

    // Charts
    const barCanvas   = document.getElementById("jwpm-stock-bar-chart");
    const donutCanvas = document.getElementById("jwpm-stock-donut-chart");

    let barChart  = null;
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
        return parseFloat(n || 0).toLocaleString("en-US", {
            minimumFractionDigits: 2
        });
    }

    function getFilters() {
        return {
            category: $filterCat.val(),
            metal   : $filterMetal.val(),
            karat   : $filterKarat.val(),
            min_qty : $filterMin.val(),
        };
    }


    // =============================
    // Fetch + Render
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
            metal    : filters.metal,
            karat    : filters.karat,
            min_qty  : filters.min_qty
        })
        .done((res) => {

            if (!res.success) {
                $tbody.html(`<tr><td colspan="8">${i18n.error}</td></tr>`);
                return;
            }

            const rows    = res.data.items;
            const summary = res.data.summary;
            const charts  = res.data.charts;

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
        $sumTotalItems.text(format(s.total_items));
        $sumTotalQty.text(format(s.total_qty));
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
        for (let p = 1; p <= pages; p++) {
            html += `<span class="jwpm-page-btn ${p === page ? "active" : ""}" data-page="${p}">${p}</span>`;
        }

        $pagination.html(html);
    }

    $pagination.on("click", "[data-page]", function () {
        loadStock(parseInt($(this).data("page")));
    });


    // =============================
    // Charts
    // =============================

    function updateBarChart(dataset) {

        if (!barCanvas) return;
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

        if (!donutCanvas) return;
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
    // Export
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
    // Print
    // =============================
    $root.find('[data-role="stock-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Stock Report");
    });


    // =============================
    // Demo Data
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
    // ✅ Syntax verified block end

})(jQuery);

