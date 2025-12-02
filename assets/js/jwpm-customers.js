(function ($) {
        'use strict';

        /**
         * JWPM Customers Module (template-driven)
         * - Uses HTML <template> blocks defined in admin/pages/jwpm-customers.php
         * - Handles listing, filtering, add/edit, delete, demo data and CSV import
         */

        var defaults = {
                ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
                mainNonce: '',
                importNonce: '',
                exportNonce: '',
                demoNonce: '',
                actions: {
                        fetch: 'jwpm_customers_fetch',
                        save: 'jwpm_customers_save',
                        delete: 'jwpm_customers_delete',
                        import: 'jwpm_customers_import',
                        export: 'jwpm_customers_export',
                        demo: 'jwpm_customers_demo',
                },
                strings: {
                        loading: 'Loading customers…',
                        saving: 'Saving…',
                        saveSuccess: 'Customer saved successfully.',
                        saveError: 'Error saving customer, please retry.',
                        deleteConfirm: 'Are you sure you want to deactivate this customer?',
                        deleteSuccess: 'Customer deactivated.',
                        demoCreateSuccess: 'Demo customers created.',
                        demoClearSuccess: 'Demo customers cleared.',
                        importSuccess: 'Import completed.',
                        importError: 'Import failed, please check CSV.',
                        noRecords: 'No records found.',
                },
                pagination: {
                        defaultPerPage: 20,
                        perPageOptions: [20, 50, 100],
                },
        };

        var config = $.extend(true, {}, defaults, window.jwpmCustomersData || {});

        function ajax(action, data) {
                data = data || {};
                data.action = action;

                return $.ajax({
                        url: config.ajaxUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: data,
                });
        }

        function cloneTemplate(id) {
                var tpl = document.getElementById(id);
                if (!tpl || !tpl.content) {
                        return $();
                }
                return $(tpl.content.cloneNode(true));
        }

        function parseCsv(text) {
                var lines = text.split(/\r?\n/).filter(function (l) {
                        return l.trim().length;
                });
                if (!lines.length) {
                        return [];
                }

                var headers = lines[0].split(',').map(function (h) {
                        return h.trim().toLowerCase();
                });
                var nameIdx = headers.indexOf('name');
                var phoneIdx = headers.indexOf('phone');
                var cityIdx = headers.indexOf('city');
                var statusIdx = headers.indexOf('status');

                if (nameIdx === -1 || phoneIdx === -1) {
                        return [];
                }

                var items = [];
                for (var i = 1; i < lines.length; i++) {
                        var cols = lines[i].split(',');
                        if (!cols[nameIdx] || !cols[phoneIdx]) {
                                continue;
                        }
                        items.push({
                                name: cols[nameIdx].trim(),
                                phone: cols[phoneIdx].trim(),
                                city: cityIdx !== -1 ? cols[cityIdx].trim() : '',
                                status: statusIdx !== -1 ? cols[statusIdx].trim() : 'active',
                        });
                }
                return items;
        }

        function CustomersPage($root) {
                this.$root = $root;
                this.state = {
                        items: [],
                        page: 1,
                        perPage: config.pagination.defaultPerPage || 20,
                        total: 0,
                        totalPages: 1,
                        filters: {
                                search: '',
                                city: '',
                                customer_type: '',
                                status: '',
                        },
                };

                this.init();
        }

        CustomersPage.prototype.init = function () {
                this.renderLayout();
                this.cache();
                this.bindEvents();
                this.loadCustomers();
        };

        CustomersPage.prototype.renderLayout = function () {
                var $layout = cloneTemplate('jwpm-customers-layout-template');
                if (!$layout.length) {
                        // Fallback minimal layout
                        this.$root.html('<div class="jwpm-page">' + (config.strings.loading || 'Loading…') + '</div>');
                        return;
                }
                this.$root.empty().append($layout);
        };

        CustomersPage.prototype.cache = function () {
                this.$tableBody = this.$root.find('[data-jwpm-customers-table-body]');
                this.$pagination = this.$root.find('[data-jwpm-customers-pagination]');
                this.$sidePanel = this.$root.find('[data-jwpm-customers-side-panel]');
                this.$statsTotal = this.$root.find('[data-jwpm-customers-stat="total"] .jwpm-stat-value');
                this.$statsActive = this.$root.find('[data-jwpm-customers-stat="active"] .jwpm-stat-value');
        };

        CustomersPage.prototype.bindEvents = function () {
                var self = this;

                // Filters
                this.$root.on('input change', '[data-jwpm-customers-filter]', function () {
                        var key = $(this).data('jwpm-customers-filter');
                        var mappedKey = key === 'type' ? 'customer_type' : key;
                        self.state.filters[mappedKey] = $(this).val();
                        self.state.page = 1;
                        self.loadCustomers();
                });

                // Toolbar actions
                this.$root.on('click', '[data-jwpm-customers-action="add"]', function () {
                        self.openForm();
                });

                this.$root.on('click', '[data-jwpm-customers-action="import"]', function () {
                        self.openImportModal();
                });

                this.$root.on('click', '[data-jwpm-customers-action="export"]', function () {
                        self.exportCustomers();
                });

                this.$root.on('click', '[data-jwpm-customers-action="print"]', function () {
                        window.print();
                });

                this.$root.on('click', '[data-jwpm-customers-action="demo-create"]', function () {
                        self.handleDemo('create');
                });

                this.$root.on('click', '[data-jwpm-customers-action="demo-clear"]', function () {
                        self.handleDemo('delete');
                });

                // Row actions
                this.$root.on('click', '[data-jwpm-customers-action="view"]', function () {
                        var item = $(this).closest('[data-jwpm-customer-row]').data('jwpm-item');
                        self.openForm(item || {});
                });

                this.$root.on('click', '[data-jwpm-customers-action="delete"]', function () {
                        var item = $(this).closest('[data-jwpm-customer-row]').data('jwpm-item');
                        if (item) {
                                self.deleteCustomer(item.id);
                        }
                });

                // Pagination
                this.$root.on('click', '.jwpm-page-btn', function () {
                        self.state.page = $(this).data('page');
                        self.loadCustomers();
                });
        };

        CustomersPage.prototype.loadCustomers = function () {
                var self = this;

                this.$tableBody.html(
                        '<tr class="jwpm-loading-row"><td colspan="10" style="text-align:center;">' +
                                (config.strings.loading || 'Loading…') +
                        '</td></tr>'
                );

                ajax(config.actions.fetch, {
                        nonce: config.mainNonce,
                        search: this.state.filters.search,
                        city: this.state.filters.city,
                        customer_type: this.state.filters.customer_type,
                        status: this.state.filters.status,
                        page: this.state.page,
                        per_page: this.state.perPage,
                })
                        .done(function (res) {
                                if (!res || !res.success) {
                                        self.renderErrorRow((res && res.data && res.data.message) || config.strings.noRecords);
                                        return;
                                }

                                var data = res.data || {};
                                self.state.items = data.items || [];
                                self.state.total = data.pagination ? data.pagination.total || 0 : 0;
                                self.state.totalPages = data.pagination ? data.pagination.total_page || 1 : 1;

                                self.renderTable();
                                self.renderPagination();
                                self.renderStats();
                        })
                        .fail(function () {
                                self.renderErrorRow(config.strings.saveError || 'Error loading data.');
                        });
        };

        CustomersPage.prototype.renderErrorRow = function (msg) {
                this.$tableBody.html(
                        '<tr class="jwpm-error-row"><td colspan="10" style="text-align:center; color:red;">' +
                                (msg || config.strings.noRecords) +
                        '</td></tr>'
                );
        };

        CustomersPage.prototype.renderTable = function () {
                var self = this;
                this.$tableBody.empty();

                if (!this.state.items.length) {
                        this.$tableBody.append(
                                '<tr class="jwpm-empty-row"><td colspan="10" style="text-align:center;">' +
                                        (config.strings.noRecords || 'No records found.') +
                                '</td></tr>'
                        );
                        return;
                }

                this.state.items.forEach(function (item) {
                        var $row = cloneTemplate('jwpm-customers-row-template');
                        $row.find('[data-jwpm-customer-field="customer_code"]').text(item.customer_code || '-');
                        $row.find('[data-jwpm-customer-field="name"]').text(item.name || '-');
                        $row.find('[data-jwpm-customer-field="phone"]').text(item.phone || '-');
                        $row.find('[data-jwpm-customer-field="city"]').text(item.city || '-');
                        $row.find('[data-jwpm-customer-field="customer_type"]').text(item.customer_type || '-');
                        $row.find('[data-jwpm-customer-field="credit_limit"]').text(item.credit_limit || '0.000');
                        $row.find('[data-jwpm-customer-field="current_balance"]').text(item.current_balance || '0.000');
                        $row.find('[data-jwpm-customer-field="last_purchase"]').text(item.last_purchase || '-');

                        var statusHtml = '<span class="jwpm-status-badge jwpm-status-' + (item.status || 'inactive') + '">' +
                                (item.status || 'inactive') +
                                '</span>';
                        $row.find('[data-jwpm-customer-field="status_badge"]').html(statusHtml);

                        $row.find('[data-jwpm-customer-row]').attr('data-id', item.id || '').data('jwpm-item', item);
                        self.$tableBody.append($row);
                });
        };

        CustomersPage.prototype.renderPagination = function () {
                var html = '';

                if (this.state.page > 1) {
                        html += '<button class="button jwpm-page-btn" data-page="' + (this.state.page - 1) + '">« Prev</button> ';
                }

                html += '<span class="description">Page ' + this.state.page + ' of ' + this.state.totalPages + '</span> ';

                if (this.state.page < this.state.totalPages) {
                        html += '<button class="button jwpm-page-btn" data-page="' + (this.state.page + 1) + '">Next »</button>';
                }

                this.$pagination.html(html);
        };

        CustomersPage.prototype.renderStats = function () {
                this.$statsTotal.text(this.state.total);
                var active = this.state.items.filter(function (item) {
                        return item.status === 'active';
                }).length;
                this.$statsActive.text(active);
        };

        CustomersPage.prototype.openForm = function (item) {
                item = item || {};
                var $panelContent = cloneTemplate('jwpm-customers-form-template');

                var $title = $panelContent.find('[data-jwpm-customers-form-title]');
                if (item.id) {
                        $title.text($title.data('edit-label') || 'Edit Customer');
                }

                var $form = $panelContent.find('[data-jwpm-customers-form]');
                $form.find('[data-jwpm-customer-input]').each(function () {
                        var key = $(this).data('jwpm-customer-input');
                        if (key && item[key] !== undefined) {
                                $(this).val(item[key]);
                        }
                });

                if (item.id) {
                        $form.find('[name="opening_balance"]').prop('disabled', true);
                }

                this.$sidePanel.html($panelContent).removeAttr('hidden');
                this.bindFormActions();
        };

        CustomersPage.prototype.bindFormActions = function () {
                var self = this;

                this.$sidePanel.off('click.formActions');
                this.$sidePanel.on('click.formActions', '[data-jwpm-customers-action="close-panel"], [data-jwpm-customers-action="cancel"]', function () {
                        self.$sidePanel.attr('hidden', true).empty();
                });

                this.$sidePanel.on('click.formActions', '[data-jwpm-customers-action="save"]', function () {
                        self.saveForm();
                });
        };

        CustomersPage.prototype.saveForm = function () {
                var self = this;
                var $form = this.$sidePanel.find('[data-jwpm-customers-form]');
                if (!$form.length) {
                                return;
                }

                var data = {};
                $form.serializeArray().forEach(function (field) {
                        data[field.name] = field.value;
                });

                if (!data.name || !data.phone) {
                        alert(config.strings.saveError || 'Name and phone are required.');
                        return;
                }

                data.nonce = config.mainNonce;

                var $saveBtn = this.$sidePanel.find('[data-jwpm-customers-action="save"]');
                var originalLabel = $saveBtn.text();
                $saveBtn.prop('disabled', true).text(config.strings.saving || 'Saving…');

                ajax(config.actions.save, data)
                        .done(function (res) {
                                if (res && res.success) {
                                        alert((res.data && res.data.message) || config.strings.saveSuccess || 'Saved');
                                        self.$sidePanel.attr('hidden', true).empty();
                                        self.loadCustomers();
                                } else {
                                        var msg = (res && res.data && res.data.message) || config.strings.saveError;
                                        alert(msg);
                                }
                        })
                        .fail(function () {
                                alert(config.strings.saveError || 'Error saving customer.');
                        })
                        .always(function () {
                                $saveBtn.prop('disabled', false).text(originalLabel);
                        });
        };

        CustomersPage.prototype.deleteCustomer = function (id) {
                var self = this;
                if (!id) {
                        return;
                }
                if (!window.confirm(config.strings.deleteConfirm || 'Are you sure?')) {
                        return;
                }

                ajax(config.actions.delete, {
                        nonce: config.mainNonce,
                        id: id,
                })
                        .done(function (res) {
                                if (res && res.success) {
                                        alert(config.strings.deleteSuccess || 'Deleted');
                                        self.loadCustomers();
                                } else {
                                        alert((res && res.data && res.data.message) || config.strings.saveError);
                                }
                        })
                        .fail(function () {
                                alert(config.strings.saveError || 'Delete failed.');
                        });
        };

        CustomersPage.prototype.handleDemo = function (mode) {
                var self = this;
                ajax(config.actions.demo, {
                        nonce: config.demoNonce || config.mainNonce,
                        mode: mode,
                })
                        .done(function (res) {
                                if (res && res.success) {
                                        var msg = mode === 'delete' ? config.strings.demoClearSuccess : config.strings.demoCreateSuccess;
                                        alert((res.data && res.data.message) || msg);
                                        self.loadCustomers();
                                } else {
                                        alert((res && res.data && res.data.message) || config.strings.saveError);
                                }
                        })
                        .fail(function () {
                                alert(config.strings.saveError || 'Demo action failed.');
                        });
        };

        CustomersPage.prototype.openImportModal = function () {
                var self = this;
                var $modal = cloneTemplate('jwpm-customers-import-template');
                if (!$modal.length) {
                        alert('Import template missing.');
                        return;
                }

                $('body').append($modal);
                var $modalRoot = $('body').children('.jwpm-modal-import-customers').last();

                var closeModal = function () {
                        $modalRoot.remove();
                };

                $modalRoot.on('click', '[data-jwpm-customers-action="close-import"]', closeModal);

                $modalRoot.on('click', '[data-jwpm-customers-action="do-import"]', function () {
                        var fileInput = $modalRoot.find('input[type="file"]')[0];
                        if (!fileInput || !fileInput.files.length) {
                                alert(config.strings.importError || 'Select a CSV file.');
                                return;
                        }
                        var reader = new FileReader();
                        reader.onload = function (e) {
                                var items = parseCsv(e.target.result || '');
                                if (!items.length) {
                                        alert(config.strings.importError || 'No valid rows found.');
                                        return;
                                }
                                ajax(config.actions.import, {
                                        nonce: config.importNonce || config.mainNonce,
                                        items_json: JSON.stringify(items),
                                })
                                        .done(function (res) {
                                                if (res && res.success) {
                                                        var message = (res.data && res.data.message) || config.strings.importSuccess;
                                                        $modalRoot.find('[data-jwpm-customers-import-result]').text(message);
                                                        self.loadCustomers();
                                                } else {
                                                        alert((res && res.data && res.data.message) || config.strings.importError);
                                                }
                                        })
                                        .fail(function () {
                                                alert(config.strings.importError || 'Import failed.');
                                        });
                        };
                        reader.readAsText(fileInput.files[0]);
                });
        };

        CustomersPage.prototype.exportCustomers = function () {
                ajax(config.actions.export, {
                        nonce: config.exportNonce || config.mainNonce,
                        status: this.state.filters.status,
                }).done(function (res) {
                        if (res && res.success && res.data && res.data.rows) {
                                var dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(res.data.rows));
                                var dl = document.createElement('a');
                                dl.setAttribute('href', dataStr);
                                dl.setAttribute('download', 'customers-export.json');
                                document.body.appendChild(dl);
                                dl.click();
                                dl.remove();
                        } else {
                                alert((res && res.data && res.data.message) || config.strings.saveError);
                        }
                }).fail(function () {
                        alert(config.strings.saveError || 'Export failed.');
                });
        };

        $(function () {
                var $root = $('#jwpm-customers-root');
                if (!$root.length) {
                        return;
                }
                new CustomersPage($root);
        });
	
})(jQuery);
