/**
 * JWPM — Accounts Cashbook JS
 * Updated: Direct HTML Injection (No PHP Templates required)
 */

(function ($) {
    'use strict';

    // 🟢 JWPM Cashbook Module Start

    // 1. Safe Config & Helpers
    var config = window.jwpmAccountsCashbook || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        actions: {
            fetch: 'jwpm_cashbook_fetch',
            save: 'jwpm_cashbook_save',
            delete: 'jwpm_cashbook_delete',
            export: 'jwpm_cashbook_export',
            import: 'jwpm_cashbook_import',
            demo: 'jwpm_cashbook_demo'
        },
        i18n: {
            loading: 'Loading...',
            errorGeneric: 'Error processing request',
            confirmDelete: 'Are you sure you want to delete this entry?'
        }
    };

    function formatCurrency(n) {
        return parseFloat(n).toLocaleString('en-US', { minimumFractionDigits: 2 });
    }

    function wpAjax(action, data) {
        return $.ajax({
            url: config.ajaxUrl,
            method: 'POST',
            data: Object.assign({}, data, {
                action: action,
                nonce: config.nonce,
            }),
        });
    }

    // 2. Main Class
    class JWPM_Cashbook_Page {
        constructor($root) {
            this.$root = $root;
            this.state = {
                page: 1,
                perPage: 25
            };
            this.init();
        }

        init() {
            this.renderLayout();
            this.cacheElements();
            this.bindEvents();
            this.loadTable(1);
        }

        // --- UI RENDERER ---
        renderLayout() {
            this.$root.html(`
                <div class="jwpm-wrapper">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #eee;">
                        <h2 style="margin:0;">📒 Accounts Cashbook</h2>
                        <div>
                            <button class="button button-primary" data-role="cashbook-add">+ Add Entry</button>
                            <button class="button" data-role="cashbook-export">Export</button>
                            <button class="button" data-role="cashbook-demo">Demo Data</button>
                        </div>
                    </div>

                    <div class="jwpm-card" style="display:flex; justify-content:space-between; margin-bottom:20px; padding:20px; background:#f9f9f9;">
                        <div data-role="balance-opening" style="text-align:center;">
                            <span style="display:block; color:#777; font-size:12px;">Opening Balance</span>
                            <span class="jwpm-balance-value" style="font-size:18px; font-weight:bold;">0.00</span>
                        </div>
                        <div data-role="balance-in" style="text-align:center; color:green;">
                            <span style="display:block; font-size:12px;">Total In (+)</span>
                            <span class="jwpm-balance-value" style="font-size:18px; font-weight:bold;">0.00</span>
                        </div>
                        <div data-role="balance-out" style="text-align:center; color:red;">
                            <span style="display:block; font-size:12px;">Total Out (-)</span>
                            <span class="jwpm-balance-value" style="font-size:18px; font-weight:bold;">0.00</span>
                        </div>
                        <div data-role="balance-closing" style="text-align:center; color:#0073aa;">
                            <span style="display:block; font-size:12px;">Closing Balance</span>
                            <span class="jwpm-balance-value" style="font-size:18px; font-weight:bold;">0.00</span>
                        </div>
                    </div>

                    <div class="jwpm-card" style="padding:15px; margin-bottom:20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <label>From: <input type="date" data-role="filter-from-date" style="padding:5px;"></label>
                        <label>To: <input type="date" data-role="filter-to-date" style="padding:5px;"></label>
                        
                        <select data-role="filter-type" style="padding:5px;">
                            <option value="">All Types</option>
                            <option value="in">Cash In</option>
                            <option value="out">Cash Out</option>
                        </select>
                        
                        <input type="text" data-role="filter-category" placeholder="Filter by Category..." style="padding:6px;">
                        
                        <button class="button" onclick="jQuery('[data-role=filter-from-date]').val(''); jQuery('[data-role=filter-to-date]').val('');">Clear Dates</button>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Reference</th>
                                <th>Remarks</th>
                                <th style="text-align:right;">Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody data-role="cashbook-tbody">
                            <tr><td colspan="7">Loading...</td></tr>
                        </tbody>
                    </table>

                    <div class="tablenav bottom">
                        <div class="tablenav-pages" data-role="cashbook-pagination"></div>
                    </div>

                    <div data-role="cashbook-sidepanel" style="display:none; position:fixed; top:0; right:0; width:400px; height:100%; background:#fff; box-shadow:-2px 0 5px rgba(0,0,0,0.1); z-index:9999; padding:20px; overflow-y:auto;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                            <h2 data-role="sidepanel-title" style="margin:0;">Entry</h2>
                            <button class="button" data-role="sidepanel-close">Close ❌</button>
                        </div>
                        
                        <form data-role="cashbook-form">
                            <input type="hidden" data-role="entry-id">
                            
                            <label>Date <span style="color:red">*</span></label>
                            <input type="date" data-role="field-date" class="widefat" required style="margin-bottom:10px;">
                            
                            <label>Type <span style="color:red">*</span></label>
                            <select data-role="field-type" class="widefat" style="margin-bottom:10px;">
                                <option value="in">Cash In (+)</option>
                                <option value="out">Cash Out (-)</option>
                            </select>

                            <label>Category</label>
                            <input type="text" data-role="field-category" class="widefat" list="jwpm-cats" style="margin-bottom:10px;">
                            <datalist id="jwpm-cats">
                                <option value="Sale">
                                <option value="Purchase">
                                <option value="Expense">
                                <option value="Salary">
                                <option value="Utility Bills">
                            </datalist>

                            <label>Reference (Optional)</label>
                            <input type="text" data-role="field-reference" class="widefat" style="margin-bottom:10px;">

                            <label>Amount <span style="color:red">*</span></label>
                            <input type="number" step="0.01" data-role="field-amount" class="widefat" required style="margin-bottom:10px;">

                            <label>Remarks</label>
                            <textarea data-role="field-remarks" class="widefat" style="height:80px; margin-bottom:20px;"></textarea>

                            <button type="submit" class="button button-primary button-large" style="width:100%;">Save Entry</button>
                        </form>
                    </div>
                </div>
            `);
        }

        cacheElements() {
            this.$tbody = this.$root.find('[data-role="cashbook-tbody"]');
            this.$pagination = this.$root.find('[data-role="cashbook-pagination"]');
            
            // Sidepanel
            this.$sidepanel = this.$root.find('[data-role="cashbook-sidepanel"]');
            this.$form = this.$root.find('[data-role="cashbook-form"]');
            
            // Filters
            this.$filterFrom = this.$root.find('[data-role="filter-from-date"]');
            this.$filterTo = this.$root.find('[data-role="filter-to-date"]');
            this.$filterType = this.$root.find('[data-role="filter-type"]');
            this.$filterCat = this.$root.find('[data-role="filter-category"]');
            
            // Summary
            this.$sumOpening = this.$root.find('[data-role="balance-opening"] .jwpm-balance-value');
            this.$sumIn = this.$root.find('[data-role="balance-in"] .jwpm-balance-value');
            this.$sumOut = this.$root.find('[data-role="balance-out"] .jwpm-balance-value');
            this.$sumClosing = this.$root.find('[data-role="balance-closing"] .jwpm-balance-value');
        }

        bindEvents() {
            const self = this;

            // Filters
            this.$filterFrom.on('change', () => this.loadTable(1));
            this.$filterTo.on('change', () => this.loadTable(1));
            this.$filterType.on('change', () => this.loadTable(1));
            this.$filterCat.on('input', () => {
                clearTimeout(this.timer);
                this.timer = setTimeout(() => self.loadTable(1), 500);
            });

            // Pagination
            this.$root.on('click', '.jwpm-page-btn', function() {
                self.loadTable($(this).data('page'));
            });

            // Add New
            this.$root.on('click', '[data-role="cashbook-add"]', () => {
                this.openPanel('Add Entry');
                this.$form[0].reset();
                this.$form.find('[data-role="field-date"]').val(new Date().toISOString().split('T')[0]); // Default today
                this.$form.find('[data-role="entry-id"]').val('');
            });

            // Edit
            this.$root.on('click', '[data-role="edit-entry"]', function() {
                const $tr = $(this).closest('tr');
                const json = $tr.data('json'); // Get full data object
                if(json) {
                    self.openPanel('Edit Entry');
                    self.$form.find('[data-role="entry-id"]').val(json.id);
                    self.$form.find('[data-role="field-date"]').val(json.entry_date);
                    self.$form.find('[data-role="field-type"]').val(json.type);
                    self.$form.find('[data-role="field-category"]').val(json.category);
                    self.$form.find('[data-role="field-reference"]').val(json.reference);
                    self.$form.find('[data-role="field-amount"]').val(json.amount);
                    self.$form.find('[data-role="field-remarks"]').val(json.remarks);
                }
            });

            // Delete
            this.$root.on('click', '[data-role="delete-entry"]', function() {
                if(!confirm(config.i18n.confirmDelete)) return;
                const id = $(this).closest('tr').data('id');
                
                wpAjax(config.actions.delete, { id: id }).done((res) => {
                    if(res.success) self.loadTable(self.state.page);
                    else alert('Failed to delete');
                });
            });

            // Save Form
            this.$form.on('submit', function(e) {
                e.preventDefault();
                const data = {
                    id: $(this).find('[data-role="entry-id"]').val(),
                    entry_date: $(this).find('[data-role="field-date"]').val(),
                    type: $(this).find('[data-role="field-type"]').val(),
                    category: $(this).find('[data-role="field-category"]').val(),
                    reference: $(this).find('[data-role="field-reference"]').val(),
                    amount: $(this).find('[data-role="field-amount"]').val(),
                    remarks: $(this).find('[data-role="field-remarks"]').val()
                };

                wpAjax(config.actions.save, data).done((res) => {
                    if(res.success) {
                        self.$sidepanel.hide();
                        self.loadTable(self.state.page);
                    } else {
                        alert(res.data.message || 'Error saving');
                    }
                });
            });

            // Close Panel
            this.$root.on('click', '[data-role="sidepanel-close"]', () => this.$sidepanel.hide());
            
            // Demo Data
            this.$root.on('click', '[data-role="cashbook-demo"]', () => {
                if(confirm("Generate Demo Data?")) {
                    wpAjax(config.actions.demo, {}).done((res) => {
                        alert(res.data.message);
                        self.loadTable(1);
                    });
                }
            });
            
             // Export
            this.$root.on('click', '[data-role="cashbook-export"]', () => {
                window.open(config.ajaxUrl + '?action=' + config.actions.export + '&nonce=' + config.nonce, '_blank');
            });
        }

        // --- DATA LOADING ---
        loadTable(page) {
            this.state.page = page;
            this.$tbody.html('<tr><td colspan="7" style="text-align:center;">Loading...</td></tr>');

            const payload = {
                page: page,
                per_page: this.state.perPage,
                from_date: this.$filterFrom.val(),
                to_date: this.$filterTo.val(),
                type: this.$filterType.val(),
                category: this.$filterCat.val()
            };

            wpAjax(config.actions.fetch, payload).done((res) => {
                if(!res.success) {
                    this.$tbody.html('<tr><td colspan="7" style="color:red; text-align:center;">Error loading data</td></tr>');
                    return;
                }

                this.renderRows(res.data.items);
                this.updateSummary(res.data.summary);
                this.renderPagination(res.data.total, res.data.page, res.data.perPage);
            });
        }

        renderRows(items) {
            if(!items.length) {
                this.$tbody.html('<tr><td colspan="7" style="text-align:center;">No entries found.</td></tr>');
                return;
            }

            let html = '';
            items.forEach(item => {
                const json = JSON.stringify(item).replace(/'/g, "&#39;");
                const color = item.type === 'in' ? 'green' : 'red';
                const sign = item.type === 'in' ? '+' : '-';
                
                html += `
                    <tr data-id="${item.id}" data-json='${json}'>
                        <td>${item.entry_date}</td>
                        <td style="color:${color}; font-weight:bold; text-transform:uppercase;">${item.type}</td>
                        <td>${item.category}</td>
                        <td>${item.reference || '-'}</td>
                        <td style="color:#666;">${item.remarks || ''}</td>
                        <td style="text-align:right; font-family:monospace; font-size:1.1em;">${sign} ${formatCurrency(item.amount)}</td>
                        <td>
                            <button class="button button-small" data-role="edit-entry">Edit</button>
                            <button class="button button-small" data-role="delete-entry" style="color:red;">Del</button>
                        </td>
                    </tr>
                `;
            });
            this.$tbody.html(html);
        }

        updateSummary(sum) {
            this.$sumOpening.text(formatCurrency(sum.opening || 0));
            this.$sumIn.text(formatCurrency(sum.total_in || 0));
            this.$sumOut.text(formatCurrency(sum.total_out || 0));
            this.$sumClosing.text(formatCurrency(sum.closing || 0));
        }

        renderPagination(total, page, perPage) {
            const totalPages = Math.ceil(total / perPage);
            if(totalPages <= 1) {
                this.$pagination.empty();
                return;
            }

            let html = '';
            if(page > 1) html += `<button class="button jwpm-page-btn" data-page="${page-1}">« Prev</button> `;
            html += `<span style="padding:0 10px;">Page ${page} of ${totalPages}</span>`;
            if(page < totalPages) html += ` <button class="button jwpm-page-btn" data-page="${page+1}">Next »</button>`;
            
            this.$pagination.html(html);
        }

        openPanel(title) {
            this.$sidepanel.find('[data-role="sidepanel-title"]').text(title);
            this.$sidepanel.show();
        }
    }

    // Init
    $(function() {
        if($('#jwpm-accounts-cashbook-root').length) {
            new JWPM_Cashbook_Page($('#jwpm-accounts-cashbook-root'));
        }
    });

})(jQuery);
