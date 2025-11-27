/**
 * JWPM — Expense Report JS (Layout C — Purple Royal UI)
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

    const $tpl = $("#jwpm-expense-report-layout");
    if ($tpl.length === 0) {
        console.warn("JWPM Warning: Expense Report template missing");
        return;
    }

    // Mount Template
    const mount = window.jwpmMountTemplate || function (tpl, $target) {
        $target.html($(tpl).html());
    };

    mount($tpl, $root);

    // Localized
    const ajaxUrl = window.jwpmExpenseReport.ajaxUrl;
    const nonce   = window.jwpmExpenseReport.nonce;
    const actions = window.jwpmExpenseReport.actions;
    const i18n    = window.jwpmExpenseReport.i18n;

    // Elements
    const $tbody      = $root.find('[data-role="expense-tbody"]');
    const $pagination = $root.find('[data-role="expense-pagination"]');

    // Summary cards
    const $sumTotalExpense = $root.find('[data-role="sum-total-expense"] .jwpm-summary-value');
    const $sumCategories   = $root.find('[data-role="sum-categories"] .jwpm-summary-value');
    const $sumVendors      = $root.find('[data-role="sum-vendors"] .jwpm-summary-value');
    const $sumAverage      = $root.find('[data-role="sum-average-expense"] .jwpm-summary-value');

    // Filters
    const $filterFrom   = $root.find('[data-role="filter-from-date"]');
    const $filterTo     = $root.find('[data-role="filter-to-date"]');
    const $filterCat    = $root.find('[data-role="filter-category"]');
    const $filterVendor = $root.find('[data-role="filter-vendor"]');

    // Charts
    const barCanvas   = document.getElementById("jwpm-expense-bar-chart");
    const donutCanvas = document.getElementById("jwpm-expense-donut-chart");

    let barChart   = null;
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
        return parseFloat(n || 0).toLocaleString("en-US", {
            minimumFractionDigits: 2
        });
    }

    function getFilters() {
        return {
            from_date: $filterFrom.val(),
            to_date  : $filterTo.val(),
            category : $filterCat.val(),
            vendor   : $filterVendor.val()
        };
    }

    // =============================
    // Fetch + Render
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
            to_date  : filters.to_date,
            category : filters.category,
            vendor   : filters.vendor
        })
        .done((res) => {

            if (!res.success) {
                $tbody.html(`<tr><td colspan="5">${i18n.error}</td></tr>`);
                return;
            }

            const rows    = res.data.items;
            const summary = res.data.summary;
            const charts  = res.data.charts;

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
        $sumCategories.text(format(s.categories));
        $sumVendors.text(format(s.vendors));
        $sumAverage.text(format(s.average_expense));
    }

    function renderPagination(total, page, perPage) {
        const pages = Math.ceil(total / perPage);
        if (pages <= 1) {
            $pagination.html("");
            return;
        }

        let html = "";
        for (let p = 1; p <= pages; p++) {
            html += `<span class="jwpm-page-btn ${page === p ? "active" : ""}" data-page="${p}">${p}</span>`;
        }

        $pagination.html(html);
    }

    $pagination.on("click", "[data-page]", function () {
        loadExpenses(parseInt($(this).data("page")));
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
        if (!donutCanvas) return;
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
    // Filters Auto Reload
    // =============================
    $filterFrom.on("change", () => loadExpenses(1));
    $filterTo.on("change", () => loadExpenses(1));
    $filterCat.on("input", () => loadExpenses(1));
    $filterVendor.on("input", () => loadExpenses(1));

    // =============================
    // Export
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
    // Print
    // =============================
    $root.find('[data-role="expense-print"]').on("click", function () {
        window.jwpmPrintTable($root.find("table")[0], "Expense Report");
    });

    // =============================
    // Demo Data
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

    // ✅ Syntax verified block end

})(jQuery);

