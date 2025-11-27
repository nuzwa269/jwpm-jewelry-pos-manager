/**
 * JWPM — Purchase Report JS (Layout B — Green Smooth Analytics)
 * یہ فائل Purchase Report کا مکمل UI Behaviour, AJAX, Graphs اور Pagination handle کرتی ہے۔
 */

(function ($) {
    "use strict";

    // 🟢 یہاں سے [Purchase Report JS] شروع ہو رہا ہے

    /** Part 1 — JS: Purchase Report Page */

    // Root mount
    const rootId = (window.jwpmPurchaseReport && window.jwpmPurchaseReport.rootId) || "jwpm-purchase-report-root";
    const $root = $("#" + rootId);

    if ($root.length === 0) {
        console.warn("JWPM Warning: Purchase Report root missing:", rootId);
        return;
    }

    const $layoutTpl = $("#jwpm-purchase-report-layout");
    if ($layoutTpl.length === 0) {
        console.warn("JWPM Warning: Purchase Report layout template missing");
        return;
    }

    // Template mount utility
    const mount = window.jwpmMountTemplate || function (tpl, $target) {
        $target.html($(tpl).html());
    };

    mount($layoutTpl, $root);

    // Localized (from PHP)
    const ajaxUrl = window.jwpmPurchaseReport.ajaxUrl;
    const nonce   = window.jwpmPurchaseReport.nonce;
    const actions = window.jwpmPurchaseReport.actions;
    const i18n    = window.jwpmPurchaseReport.i18n;

    // Elements
    const $tbody      = $root.find('[data-role="purchase-tbody"]');
    const $pagination = $root.find('[data-role="purchase-pagination"]');

    // Summary values
    const $sumTotalPurchase = $root.find('[data-role="sum-total-purchase"] .jwpm-summary-value');
    const $sumTotalWeight   = $root.find('[data-role="sum-total-weight"] .jwpm-summary-value');
    const $sumSuppliers     = $root.find('[data-role="sum-supplier-count"] .jwpm-summary-value');
    const $sumMetalSplit    = $root.find('[data-role="sum-metal-breakdown"] .jwpm-summary-value');

    // Filters
    const $filterFrom   = $root.find('[data-role="filter-from-date"]');
    const $filterTo     = $root.find('[data-role="filter-to-date"]');
    const $filterSup    = $root.find('[data-role="filter-supplier"]');
    const $filterMetal  = $root.find('[data-role="filter-metal"]');

    // Charts
    const lineCanvas  = document.getElementById("jwpm-purchase-line-chart");
    const donutCanvas = document.getElementById("jwpm-purchase-donut-chart");

    let lineChart  = null;
    let donutChart = null;

    // Utilities
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
            supplier  : $filterSup.val(),
            metal     : $filterMetal.val(),
        };
    }

    // =============================
    // Fetch + Render
    // =============================

    let currentPage = 1;
    let perPage = 30;

    function loadPurchase(page = 1) {
        currentPage = page;

        $tbody.html(`<tr><td colspan="6">${i18n.loading}</td></tr>`);

        const filters = getFilters();

        wpAjax(actions.fetch, {
            page     : currentPage,
            per_page : perPage,
            from_date: filters.from_date,
            to_date  : filters.to_date,
            supplier : filters.supplier,
            metal    : filters.metal
        })
        .done((res) => {
            if (!res.success) {
                $tbody.html(`<tr><td colspan="6">${i18n.error}</td></tr>`);
                return;
            }

            const rows    = res.data.items;
            const summary = res.data.summary;
            const charts  = res.data.charts;

            // Table render
            if (rows.length === 0) {
                $tbody.html(`
                    <tr class="jwpm-empty-row">
                        <td colspan="6">کوئی Purchase ریکارڈ نہیں ملا۔</td>
                    </tr>
                `);
            } else {
                renderRows(rows);
            }

            // Summary cards
            updateSummary(summary);

            // Pagination
            renderPagination(res.data.total, res.data.page, res.data.perPage);

            // Charts
            updateLineChart(charts.line);
            updateDonutChart(charts.donut);
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
                    <td>${r.supplier}</td>
                    <td>${r.date}</td>
                    <td>${r.metal}</td>
                    <td class="jwpm-column-number">${format(r.weight)}</td>
                    <td class="jwpm-column-number">${format(r.amount)}</td>
                </tr>
            `;
        });
        $tbody.html(html);
    }

    function updateSummary(s) {
        $sumTotalPurchase.text(format(s.total_purchase));
        $sumTotalWeight.text(format(s.total_weight));
        $sumSuppliers.text(format(s.suppliers));
        $sumMetalSplit.text(format(s.metal_split));
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
        loadPurchase(parseInt($(this).data("page")));
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
                    label: "Monthly Purchase",
                    data: dataset.values,
                    borderColor: "#16A34A",
                    backgroundColor: "rgba(22,163,74,0.15)",
                    tension: 0.35,
                    borderWidth: 2,
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

    function updateDonutChart(dataset) {
        if (!donutCanvas) return;
        if (donutChart) donutChart.destroy();

        donutChart = new Chart(donutCanvas, {
            type: "doughnut",
            data: {
                labels: dataset.labels,
                datasets: [{
                    data: dataset.values,
                    backgroundColor: ["#22C55E", "#A3E635"],
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
    // Filters Auto Reload
    // =============================

    $filterFrom.on("change", () => loadPurchase(1));
    $filterTo.on("change", () => loadPurchase(1));
    $filterSup.on("input", () => loadPurchase(1));
    $filterMetal.on("change", () => loadPurchase(1));

    // =============================
    // Export
    // =============================
    $root.find('[data-role="purchase-export"]').on("click", () => {
        wpAjax(actions.export, {})
        .done((res) => {
            if (res.success && res.data.rows) {
                window.jwpmExportToExcel("Purchase Report", res.data.headers, res.data.rows);
            } else {
                alert(i18n.error);
            }
        });
    });

    // =============================
    // Print
    // =============================
    $root.find('[data-role="purchase-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Purchase Report");
    });

    // =============================
    // Demo Data
    // =============================
    $root.find('[data-role="purchase-demo"]').on("click", function () {
        if (!confirm(i18n.demoConfirm)) return;

        wpAjax(actions.demo, {})
        .done((res) => {
            if (res.success) {
                alert(res.data.message);
                loadPurchase(1);
            } else {
                alert(i18n.error);
            }
        });
    });

    // Initial load
    loadPurchase(1);

    // 🔴 یہاں پر [Purchase Report JS] ختم ہو رہا ہے

    // ✅ Syntax verified block end

})(jQuery);

