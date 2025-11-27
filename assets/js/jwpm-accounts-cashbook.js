/**
 * JWPM — Accounts Cashbook JS
 * یہ فائل UI Behavior + AJAX Calls ہینڈل کرتی ہے۔
 * Root: #jwpm-accounts-cashbook-root
 */

(function ($) {
    'use strict';

    // 🟢 یہاں سے [Cashbook JS] شروع ہو رہا ہے

    /** Part 1 — JS: Accounts Cashbook Page */

    // Soft warning helper
    function warnMissing(el, name) {
        if (!el || el.length === 0) {
            console.warn(`JWPM Warning: Missing element for ${name}`);
        }
    }

    // Mount root
    const rootId = (window.jwpmAccountsCashbook && window.jwpmAccountsCashbook.rootId) || 'jwpm-accounts-cashbook-root';
    const $root = $('#' + rootId);
    warnMissing($root, 'Root Element');

    if ($root.length === 0) {
        return; // Page not found — nothing to mount
    }

    // Load Layout Template
    const $layoutTpl = $('#jwpm-accounts-cashbook-layout');
    warnMissing($layoutTpl, 'Template');

    const mount = window.jwpmMountTemplate || function (tpl, $target) {
        $target.html($(tpl).html());
    };

    mount($layoutTpl, $root);

    // -----------------------------------------------------------------------
    // Elements
    // -----------------------------------------------------------------------
    const ajaxUrl = window.jwpmAccountsCashbook.ajaxUrl;
    const nonce   = window.jwpmAccountsCashbook.nonce;
    const actions = window.jwpmAccountsCashbook.actions;
    const i18n    = window.jwpmAccountsCashbook.i18n;

    const $tbody = $root.find('[data-role="cashbook-tbody"]');
    const $pagination = $root.find('[data-role="cashbook-pagination"]');

    const $sidepanel = $root.find('[data-role="cashbook-sidepanel"]');
    const $sidepanelTitle = $sidepanel.find('[data-role="sidepanel-title"]');
    const $form = $sidepanel.find('[data-role="cashbook-form"]');
    const $entryId = $form.find('[data-role="entry-id"]');

    const $fieldDate = $form.find('[data-role="field-date"]');
    const $fieldType = $form.find('[data-role="field-type"]');
    const $fieldCategory = $form.find('[data-role="field-category"]');
    const $fieldReference = $form.find('[data-role="field-reference"]');
    const $fieldAmount = $form.find('[data-role="field-amount"]');
    const $fieldRemarks = $form.find('[data-role="field-remarks"]');

    // Filters
    const $filterFrom = $root.find('[data-role="filter-from-date"]');
    const $filterTo   = $root.find('[data-role="filter-to-date"]');
    const $filterType = $root.find('[data-role="filter-type"]');
    const $filterCat  = $root.find('[data-role="filter-category"]');

    // Summary
    const $sumOpening = $root.find('[data-role="balance-opening"] .jwpm-balance-value');
    const $sumIn      = $root.find('[data-role="balance-in"] .jwpm-balance-value');
    const $sumOut     = $root.find('[data-role="balance-out"] .jwpm-balance-value');
    const $sumClosing = $root.find('[data-role="balance-closing"] .jwpm-balance-value');

    warnMissing($tbody, 'Cashbook Table Body');
    warnMissing($sidepanel, 'Sidepanel');

    // -----------------------------------------------------------------------
    // Utils
    // -----------------------------------------------------------------------

    function format(n) {
        return parseFloat(n).toLocaleString('en-US', { minimumFractionDigits: 2 });
    }

    function openPanel(title) {
        $sidepanelTitle.text(title);
        $sidepanel.addClass('open');
    }

    function closePanel() {
        $sidepanel.removeClass('open');
        $form[0].reset();
        $entryId.val('');
    }

    function getFilters() {
        return {
            from_date: $filterFrom.val(),
            to_date: $filterTo.val(),
            type: $filterType.val(),
            category: $filterCat.val(),
        };
    }

    function wpAjax(action, data) {
        return $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: Object.assign({}, data, {
                action: action,
                nonce: nonce,
            }),
        });
    }

    // -----------------------------------------------------------------------
    // Fetch Table Data
    // -----------------------------------------------------------------------

    let currentPage = 1;
    let perPage = 25;

    function loadTable(page = 1) {
        currentPage = page;

        const filters = getFilters();

        $tbody.html(`<tr><td colspan="7">${i18n.loading}</td></tr>`);

        wpAjax(actions.fetch, {
            page: currentPage,
            per_page: perPage,
            from_date: filters.from_date,
            to_date: filters.to_date,
            type: filters.type,
            category: filters.category,
        })
            .done(function (res) {
                if (!res.success) {
                    $tbody.html(`<tr><td colspan="7">${i18n.errorGeneric}</td></tr>`);
                    return;
                }

                const data = res.data.items;

                if (data.length === 0) {
                    $tbody.html(`
                        <tr class="jwpm-empty-row">
                            <td colspan="7">کوئی ریکارڈ نہیں ملا۔</td>
                        </tr>
                    `);
                } else {
                    renderRows(data);
                }

                updateSummary(res.data.summary);
                renderPagination(res.data.total, res.data.page, res.data.perPage);
            })
            .fail(function () {
                $tbody.html(`<tr><td colspan="7">${i18n.errorGeneric}</td></tr>`);
            });
    }

    function renderRows(rows) {
        let html = '';

        rows.forEach(function (row) {
            html += `
                <tr data-id="${row.id}">
                    <td>${row.entry_date}</td>
                    <td>${row.type === 'in' ? 'Cash In' : 'Cash Out'}</td>
                    <td>${row.category}</td>
                    <td>${row.reference || ''}</td>
                    <td>${row.remarks || ''}</td>
                    <td class="jwpm-column-number">${format(row.amount)}</td>
                    <td>
                        <button class="button button-small" data-role="edit-entry">Edit</button>
                        <button class="button button-small" data-role="delete-entry">Delete</button>
                    </td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    function updateSummary(sum) {
        $sumOpening.text(format(sum.opening));
        $sumIn.text(format(sum.total_in));
        $sumOut.text(format(sum.total_out));
        $sumClosing.text(format(sum.closing));
    }

    function renderPagination(total, page, perPage) {
        const totalPages = Math.ceil(total / perPage);

        if (totalPages <= 1) {
            $pagination.html('');
            return;
        }

        let html = '';

        for (let p = 1; p <= totalPages; p++) {
            html += `<span class="jwpm-page-btn ${p === page ? 'active' : ''}" data-page="${p}">${p}</span>`;
        }

        $pagination.html(html);
    }

    // Pagination click
    $pagination.on('click', '[data-page]', function () {
        const page = parseInt($(this).data('page'));
        loadTable(page);
    });

    // Filters change
    $filterFrom.on('change', () => loadTable(1));
    $filterTo.on('change', () => loadTable(1));
    $filterType.on('change', () => loadTable(1));
    $filterCat.on('input', () => loadTable(1));

    // -----------------------------------------------------------------------
    // Add New Entry
    // -----------------------------------------------------------------------
    $root.find('[data-role="cashbook-add"]').on('click', function () {
        openPanel('Add Cashbook Entry');
        $form[0].reset();
        $entryId.val('');
    });

    // Edit Entry
    $tbody.on('click', '[data-role="edit-entry"]', function () {
        const $tr = $(this).closest('tr');
        const id = $tr.data('id');

        // Read values
        const date = $tr.children().eq(0).text().trim();
        const typeText = $tr.children().eq(1).text().trim();
        const type = typeText.toLowerCase().includes('in') ? 'in' : 'out';
        const category = $tr.children().eq(2).text().trim();
        const reference = $tr.children().eq(3).text().trim();
        const remarks = $tr.children().eq(4).text().trim();
        const amount = $tr.children().eq(5).text().replace(/,/g, '');

        openPanel('Edit Entry');

        $entryId.val(id);
        $fieldDate.val(date);
        $fieldType.val(type);
        $fieldCategory.val(category);
        $fieldReference.val(reference);
        $fieldAmount.val(amount);
        $fieldRemarks.val(remarks);
    });

    // Delete Entry
    $tbody.on('click', '[data-role="delete-entry"]', function () {
        if (!confirm(i18n.confirmDelete)) {
            return;
        }

        const id = $(this).closest('tr').data('id');

        wpAjax(actions.delete, { id: id })
            .done(function (res) {
                if (res.success) {
                    loadTable(currentPage);
                } else {
                    alert(i18n.errorGeneric);
                }
            });
    });

    // -----------------------------------------------------------------------
    // Save Entry (Add/Update)
    // -----------------------------------------------------------------------
    $form.on('submit', function (e) {
        e.preventDefault();

        const payload = {
            id: $entryId.val(),
            entry_date: $fieldDate.val(),
            type: $fieldType.val(),
            category: $fieldCategory.val(),
            reference: $fieldReference.val(),
            amount: $fieldAmount.val(),
            remarks: $fieldRemarks.val(),
        };

        wpAjax(actions.save, payload)
            .done(function (res) {
                if (res.success) {
                    closePanel();
                    loadTable(currentPage);
                } else {
                    alert(res.data?.message || i18n.errorGeneric);
                }
            })
            .fail(function () {
                alert(i18n.errorGeneric);
            });
    });

    // Cancel Entry
    $root.find('[data-role="cancel-entry"], [data-role="sidepanel-close"]').on('click', function () {
        closePanel();
    });

    // -----------------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------------
    $root.find('[data-role="cashbook-export"]').on('click', function () {
        wpAjax(actions.export, {})
            .done(function (res) {
                if (res.success && res.data.rows) {
                    window.jwpmExportToExcel('Cashbook', res.data.headers, res.data.rows);
                } else {
                    alert(i18n.errorGeneric);
                }
            });
    });

    // -----------------------------------------------------------------------
    // Import
    // -----------------------------------------------------------------------
    $root.find('[data-role="cashbook-import"]').on('click', function () {
        window.jwpmImportDialog(function (rows) {
            wpAjax(actions.import, { rows: rows })
                .done(function (res) {
                    if (res.success) {
                        alert(res.data.message);
                        loadTable(1);
                    } else {
                        alert(i18n.errorGeneric);
                    }
                });
        });
    });

    // -----------------------------------------------------------------------
    // Print
    // -----------------------------------------------------------------------
    $root.find('[data-role="cashbook-print"]').on('click', function () {
        window.jwpmPrintTable($tbody.closest('table')[0], 'Cashbook Records');
    });

    // -----------------------------------------------------------------------
    // Demo Data
    // -----------------------------------------------------------------------
    $root.find('[data-role="cashbook-demo"]').on('click', function () {
        if (!confirm('Demo Data شامل کریں؟')) return;

        wpAjax(actions.demo, {})
            .done(function (res) {
                if (res.success) {
                    alert(res.data.message);
                    loadTable(1);
                } else {
                    alert(i18n.errorGeneric);
                }
            });
    });

    // Initial Load
    loadTable(1);

    // 🔴 یہاں پر [Cashbook JS] ختم ہو رہا ہے

    // ✅ Syntax verified block end

})(jQuery);

