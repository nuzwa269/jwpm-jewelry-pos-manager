/**
 * JWPM Customers Page Script (UI + AJAX)
 * Updated to use Direct HTML Injection (No PHP Templates required)
 */
(function ($) {
    'use strict';

    // 🟢 JWPM Customers Module Start

    /**
     * Configuration & Fallbacks
     */
    var jwpmCustomersConfig = window.jwpmCustomersData || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        mainNonce: '',
        strings: {
            loading: 'Loading Customers...',
            saving: 'Saving Data...',
            saveSuccess: 'Customer saved successfully.',
            saveError: 'Error saving data.',
            deleteConfirm: 'Are you sure you want to deactivate this customer?',
            deleteSuccess: 'Customer deactivated.',
            noRecords: 'No records found.'
        },
        pagination: { defaultPerPage: 20 }
    };

    /**
     * Helpers
     */
    function notify(type, message) {
        if(type === 'error') alert(message);
        else console.log('[JWPM]: ' + message);
    }

    function ajaxRequest(action, data) {
        data.action = action;
        return $.ajax({
            url: jwpmCustomersConfig.ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json'
        });
    }

    /**
     * Main Controller
     */
    var JWPMCustomersPage = (function () {
        function JWPMCustomersPage($root) {
            this.$root = $root;
            this.state = {
                items: [],
                page: 1,
                perPage: 20,
                total: 0,
                totalPages: 1,
                filters: { search: '', city: '', status: '' }
            };

            this.init();
        }

        JWPMCustomersPage.prototype.init = function () {
            this.renderLayout();
            this.cacheElements();
            this.bindEvents();
            this.loadCustomers();
        };

        // 1. Render Main Layout (Direct HTML)
        JWPMCustomersPage.prototype.renderLayout = function () {
            this.$root.html(`
                <div class="jwpm-page-customers jwpm-wrapper">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:15px;">
                        <h2 style="margin:0;">👥 Customers Management</h2>
                        <div>
                            <button class="button button-primary" data-jwpm-customers-action="add">+ Add Customer</button>
                            <button class="button" data-jwpm-customers-action="import">Import CSV</button>
                            <button class="button" data-jwpm-customers-action="demo-create">Demo Data</button>
                        </div>
                    </div>

                    <div class="jwpm-card" style="padding:15px; margin-bottom:20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="text" data-jwpm-customers-filter="search" placeholder="Search Name / Phone..." style="padding:6px; width:200px;">
                        <select data-jwpm-customers-filter="city" style="padding:6px;">
                            <option value="">All Cities</option>
                            <option value="Lahore">Lahore</option>
                            <option value="Karachi">Karachi</option>
                            <option value="Islamabad">Islamabad</option>
                        </select>
                        <select data-jwpm-customers-filter="status" style="padding:6px;">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        
                        <div style="margin-left:auto; font-weight:bold; color:#666;">
                            Total: <span data-stat="total">0</span> | Active: <span data-stat="active">0</span>
                        </div>
                    </div>

                    <table class="wp-list-table widefat fixed striped jwpm-table-customers">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>Type</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody data-jwpm-customers-table-body>
                            <tr><td colspan="8">Loading...</td></tr>
                        </tbody>
                    </table>

                    <div class="tablenav bottom">
                        <div class="tablenav-pages" data-jwpm-customers-pagination></div>
                    </div>

                    <div data-jwpm-customers-side-panel style="display:none; position:fixed; top:0; right:0; width:400px; height:100%; background:#fff; box-shadow:-2px 0 5px rgba(0,0,0,0.1); z-index:9999; padding:20px; overflow-y:auto;">
                        </div>
                </div>
            `);
        };

        JWPMCustomersPage.prototype.cacheElements = function () {
            this.$tableBody = this.$root.find('[data-jwpm-customers-table-body]');
            this.$pagination = this.$root.find('[data-jwpm-customers-pagination]');
            this.$sidePanel = this.$root.find('[data-jwpm-customers-side-panel]');
            this.$totalStat = this.$root.find('[data-stat="total"]');
            this.$activeStat = this.$root.find('[data-stat="active"]');
        };

        JWPMCustomersPage.prototype.bindEvents = function () {
            var self = this;

            // Filters
            this.$root.on('input change', '[data-jwpm-customers-filter]', function () {
                var type = $(this).data('jwpm-customers-filter');
                self.state.filters[type] = $(this).val();
                self.state.page = 1;
                // Debounce search
                if(type === 'search') {
                    clearTimeout(self.searchTimer);
                    self.searchTimer = setTimeout(function(){ self.loadCustomers(); }, 500);
                } else {
                    self.loadCustomers();
                }
            });

            // Buttons
            this.$root.on('click', '[data-jwpm-customers-action="add"]', function() { self.openForm(); });
            this.$root.on('click', '[data-jwpm-customers-action="import"]', function() { alert("Import feature coming soon."); });
            
            // Demo
            this.$root.on('click', '[data-jwpm-customers-action="demo-create"]', function() { 
                if(confirm("Create Demo Customers?")) self.createDemoData(); 
            });

            // Row Actions
            this.$root.on('click', '[data-action="edit"]', function() {
                var id = $(this).closest('tr').data('id');
                self.openForm(id);
            });

            this.$root.on('click', '[data-action="delete"]', function() {
                var id = $(this).closest('tr').data('id');
                self.deleteCustomer(id);
            });

            // Pagination
            this.$root.on('click', '.jwpm-page-btn', function() {
                self.state.page = $(this).data('page');
                self.loadCustomers();
            });
        };

        // 2. Load Data
        JWPMCustomersPage.prototype.loadCustomers = function () {
            var self = this;
            this.$tableBody.html('<tr><td colspan="8" style="text-align:center;">Loading...</td></tr>');

            ajaxRequest('jwpm_get_customers', {
                nonce: jwpmCustomersConfig.mainNonce,
                search: this.state.filters.search,
                city: this.state.filters.city,
                status: this.state.filters.status,
                page: this.state.page,
                per_page: this.state.perPage
            }).done(function (res) {
                if (!res.success) {
                    self.$tableBody.html('<tr><td colspan="8" style="color:red;">Error loading data.</td></tr>');
                    return;
                }
                var data = res.data;
                self.state.items = data.items || [];
                self.state.total = data.pagination.total || 0;
                self.state.totalPages = data.pagination.total_page || 1;
                
                self.renderTable();
                self.renderPagination();
                self.renderStats();
            });
        };

        // 3. Render Table
        JWPMCustomersPage.prototype.renderTable = function () {
            var self = this;
            this.$tableBody.empty();

            if (!this.state.items.length) {
                this.$tableBody.append('<tr><td colspan="8" style="text-align:center;">No customers found.</td></tr>');
                return;
            }

            this.state.items.forEach(function (item) {
                // Store full item data in row for easy access
                var json = JSON.stringify(item).replace(/'/g, "&#39;"); 
                
                var statusColor = item.status === 'active' ? 'green' : 'red';
                
                var html = `
                    <tr data-id="${item.id}" data-json='${json}'>
                        <td>${item.customer_code || '-'}</td>
                        <td><strong>${item.name}</strong></td>
                        <td>${item.phone}</td>
                        <td>${item.city || '-'}</td>
                        <td>${item.customer_type || 'Walk-in'}</td>
                        <td>${item.current_balance || '0.00'}</td>
                        <td style="color:${statusColor}; font-weight:bold;">${item.status}</td>
                        <td>
                            <button class="button button-small" data-action="edit">Edit</button>
                            <button class="button button-small" data-action="delete" style="color:#a00;">Del</button>
                        </td>
                    </tr>
                `;
                self.$tableBody.append(html);
            });
        };

        JWPMCustomersPage.prototype.renderStats = function() {
            this.$totalStat.text(this.state.total);
            // Count active in current page (approximation)
            var active = this.state.items.filter(i => i.status === 'active').length;
            this.$activeStat.text(active + " (on page)");
        };

        JWPMCustomersPage.prototype.renderPagination = function() {
            var html = '';
            if(this.state.page > 1) html += `<button class="button jwpm-page-btn" data-page="${this.state.page - 1}">« Prev</button>`;
            html += ` <span class="description">Page ${this.state.page} of ${this.state.totalPages}</span> `;
            if(this.state.page < this.state.totalPages) html += `<button class="button jwpm-page-btn" data-page="${this.state.page + 1}">Next »</button>`;
            
            this.$pagination.html(html);
        };

        // 4. Add/Edit Form (Side Panel)
        JWPMCustomersPage.prototype.openForm = function (id) {
            var item = {};
            var title = "Add New Customer";

            if (id) {
                // Find data from existing row to avoid extra AJAX call
                var $row = this.$tableBody.find(`tr[data-id="${id}"]`);
                try {
                    item = $row.data('json') || {};
                    title = "Edit Customer";
                } catch(e) {}
            }

            var val = function(k) { return item[k] || ''; };

            var html = `
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:10px;">
                    <h2 style="margin:0;">${title}</h2>
                    <button class="button" onclick="jQuery('[data-jwpm-customers-side-panel]').hide()">Close ❌</button>
                </div>

                <form id="jwpm-customer-form">
                    <input type="hidden" name="id" value="${val('id')}">
                    <input type="hidden" name="nonce" value="${jwpmCustomersConfig.mainNonce}">

                    <label>Name <span style="color:red">*</span></label>
                    <input type="text" name="name" class="widefat" value="${val('name')}" required style="margin-bottom:10px;">

                    <label>Phone <span style="color:red">*</span></label>
                    <input type="text" name="phone" class="widefat" value="${val('phone')}" required style="margin-bottom:10px;">

                    <label>City</label>
                    <input type="text" name="city" class="widefat" value="${val('city')}" style="margin-bottom:10px;">
                    
                    <label>Address</label>
                    <textarea name="address" class="widefat" style="margin-bottom:10px;">${val('address')}</textarea>

                    <div style="display:flex; gap:10px; margin-bottom:10px;">
                        <div style="flex:1;">
                            <label>CNIC</label>
                            <input type="text" name="cnic" class="widefat" value="${val('cnic')}">
                        </div>
                        <div style="flex:1;">
                            <label>Opening Balance</label>
                            <input type="number" name="opening_balance" class="widefat" value="${val('opening_balance')}" ${id ? 'disabled' : ''}>
                        </div>
                    </div>

                    <label>Status</label>
                    <select name="status" class="widefat" style="margin-bottom:20px;">
                        <option value="active" ${val('status') === 'active' ? 'selected' : ''}>Active</option>
                        <option value="inactive" ${val('status') === 'inactive' ? 'selected' : ''}>Inactive</option>
                    </select>

                    <button type="submit" class="button button-primary button-large" style="width:100%;">Save Customer</button>
                </form>
            `;

            this.$sidePanel.html(html).show();

            // Bind Form Submit
            var self = this;
            $('#jwpm-customer-form').on('submit', function(e) {
                e.preventDefault();
                var formData = $(this).serialize();
                
                $.post(jwpmCustomersConfig.ajaxUrl + '?action=jwpm_save_customer', formData, function(res) {
                    if(res.success) {
                        alert("Customer Saved!");
                        self.$sidePanel.hide();
                        self.loadCustomers();
                    } else {
                        alert("Error: " + (res.data.message || 'Unknown error'));
                    }
                });
            });
        };

        // 5. Delete
        JWPMCustomersPage.prototype.deleteCustomer = function (id) {
            if(!confirm("Are you sure?")) return;
            var self = this;
            ajaxRequest('jwpm_delete_customer', { 
                nonce: jwpmCustomersConfig.mainNonce, 
                id: id 
            }).done(function(res) {
                if(res.success) {
                    alert("Customer Deleted");
                    self.loadCustomers();
                } else {
                    alert("Failed to delete.");
                }
            });
        };

        // Demo Data
        JWPMCustomersPage.prototype.createDemoData = function () {
            var self = this;
            ajaxRequest('jwpm_customers_demo_create', { nonce: jwpmCustomersConfig.demoNonce })
                .done(function() { 
                    alert("Demo Data Created"); 
                    self.loadCustomers(); 
                });
        };

        return JWPMCustomersPage;
    })();

    // Init on DOM Ready
    $(function () {
        var $root = $('#jwpm-customers-root');
        if ($root.length) {
            new JWPMCustomersPage($root);
        }
    });

})(jQuery);
