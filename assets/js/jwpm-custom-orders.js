/**
 * JWPM Custom Orders Script
 * Updated: Direct HTML Injection (No PHP Templates required)
 */
(function ($) {
    'use strict';

    // 🟢 JWPM Custom Orders Module Start

    // 1. Config & Fallbacks
    var config = window.jwpmCustomOrdersData || {
        ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        strings: {
            loading: 'Loading...',
            saving: 'Saving...',
            error: 'Error processing request.'
        },
        pagination: { defaultPerPage: 20 }
    };

    function formatCurrency(n) {
        return parseFloat(n || 0).toFixed(2); // 2 decimals for currency
    }
    function formatWeight(n) {
        return parseFloat(n || 0).toFixed(3); // 3 decimals for gold weight
    }

    function ajaxRequest(action, data) {
        data.action = action;
        data.nonce = config.nonce; // Auto inject nonce
        return $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json'
        });
    }

    // 2. Main Class
    class JWPM_CustomOrders_Page {
        constructor($root) {
            this.$root = $root;
            this.state = {
                page: 1,
                perPage: 20,
                currentOrderId: null,
                filters: { search: '', status: '', priority: '', date_from: '', date_to: '' }
            };
            this.init();
        }

        init() {
            this.renderLayout();
            this.cacheElements();
            this.bindEvents();
            this.loadOrders();
        }

        // --- UI RENDERER ---
        renderLayout() {
            this.$root.html(`
                <div class="jwpm-wrapper">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #eee;">
                        <h2 style="margin:0;">💎 Custom / Design Orders</h2>
                        <div>
                            <button class="button button-primary" data-action="add">+ New Order</button>
                            <button class="button" data-action="export">Export</button>
                            <button class="button" data-action="demo">Demo Data</button>
                        </div>
                    </div>

                    <div class="jwpm-card" style="display:flex; gap:20px; margin-bottom:20px; padding:20px; background:#f9f9f9; flex-wrap:wrap;">
                        <div class="jwpm-stat-box" style="flex:1; text-align:center; border-right:1px solid #ddd;">
                            <span style="color:#777;">Active Orders</span>
                            <h3 style="margin:5px 0; color:#0073aa;" data-stat="active">0</h3>
                        </div>
                        <div class="jwpm-stat-box" style="flex:1; text-align:center; border-right:1px solid #ddd;">
                            <span style="color:#777;">Due This Week</span>
                            <h3 style="margin:5px 0; color:#e6a700;" data-stat="due">0</h3>
                        </div>
                        <div class="jwpm-stat-box" style="flex:1; text-align:center; border-right:1px solid #ddd;">
                            <span style="color:#777;">Overdue</span>
                            <h3 style="margin:5px 0; color:#d63638;" data-stat="overdue">0</h3>
                        </div>
                        <div class="jwpm-stat-box" style="flex:1; text-align:center;">
                            <span style="color:#777;">Pending Payment</span>
                            <h3 style="margin:5px 0; color:#333;" data-stat="pending">0.00</h3>
                        </div>
                    </div>

                    <div class="jwpm-card" style="padding:15px; margin-bottom:20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="text" data-filter="search" placeholder="Search Code / Customer..." style="padding:6px; width:200px;">
                        
                        <select data-filter="status" style="padding:6px;">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="design_approved">Design Approved</option>
                            <option value="in_production">In Production</option>
                            <option value="ready">Ready</option>
                            <option value="delivered">Delivered</option>
                        </select>

                        <select data-filter="priority" style="padding:6px;">
                            <option value="">All Priority</option>
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                            <option value="vip">VIP</option>
                        </select>

                        <input type="date" data-filter="date_from" title="From Date">
                        <input type="date" data-filter="date_to" title="To Date">
                        
                        <button class="button" onclick="jQuery('[data-filter]').val('').trigger('change');">Clear</button>
                    </div>

                    <table class="wp-list-table widefat fixed striped jwpm-custom-orders-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Design / Title</th>
                                <th>Metal</th>
                                <th>Exp. Wt</th>
                                <th>Est. Amount</th>
                                <th>Advance</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody data-role="tbody">
                            <tr><td colspan="11">Loading...</td></tr>
                        </tbody>
                    </table>

                    <div class="tablenav bottom">
                        <div class="tablenav-pages" data-role="pagination"></div>
                    </div>

                    <div data-role="sidepanel" style="display:none; position:fixed; top:0; right:0; width:600px; height:100%; background:#fff; box-shadow:-2px 0 10px rgba(0,0,0,0.2); z-index:9999; display:flex; flex-direction:column;">
                        </div>
                </div>
            `);
        }

        cacheElements() {
            this.$tbody = this.$root.find('[data-role="tbody"]');
            this.$pagination = this.$root.find('[data-role="pagination"]');
            this.$sidePanel = this.$root.find('[data-role="sidepanel"]');
            // Stats
            this.$statActive = this.$root.find('[data-stat="active"]');
            this.$statDue = this.$root.find('[data-stat="due"]');
            this.$statOverdue = this.$root.find('[data-stat="overdue"]');
            this.$statPending = this.$root.find('[data-stat="pending"]');
        }

        bindEvents() {
            const self = this;

            // Filters
            this.$root.on('input change', '[data-filter]', function () {
                var type = $(this).data('filter');
                self.state.filters[type] = $(this).val();
                self.state.page = 1;
                if(type === 'search') {
                    clearTimeout(self.timer);
                    self.timer = setTimeout(() => self.loadOrders(), 500);
                } else {
                    self.loadOrders();
                }
            });

            // Pagination
            this.$root.on('click', '.jwpm-page-btn', function() {
                self.state.page = $(this).data('page');
                self.loadOrders();
            });

            // Actions
            this.$root.on('click', '[data-action="add"]', () => this.openPanel(null));
            this.$root.on('click', '[data-action="export"]', () => window.open(`${config.ajaxUrl}?action=jwpm_export_custom_orders&nonce=${config.nonce}`, '_blank'));
            this.$root.on('click', '[data-action="demo"]', () => this.createDemo());

            // Row Actions
            this.$root.on('click', '[data-role="edit-order"]', function() {
                self.openPanel($(this).closest('tr').data('id'));
            });
            this.$root.on('click', '[data-role="delete-order"]', function() {
                self.deleteOrder($(this).closest('tr').data('id'));
            });

            // Side Panel Events
            this.$sidePanel.on('click', '[data-role="close-panel"]', () => this.$sidePanel.hide());
            
            // Tabs
            this.$sidePanel.on('click', '.jwpm-tab', function() {
                $('.jwpm-tab').removeClass('active-tab');
                $(this).addClass('active-tab');
                $('.jwpm-tab-content').hide();
                $(`#tab-${$(this).data('tab')}`).show();
            });

            // Save Order Form
            this.$sidePanel.on('submit', '#jwpm-order-form', function(e) {
                e.preventDefault();
                self.saveOrder($(this));
            });

            // Auto Calc Net Amount
            this.$sidePanel.on('input', '[name="estimate_amount"], [name="advance_amount"]', function() {
                const est = parseFloat($('[name="estimate_amount"]').val()) || 0;
                const adv = parseFloat($('[name="advance_amount"]').val()) || 0;
                $('[name="net_amount"]').val((est - adv).toFixed(2));
            });

            // Upload File
            this.$sidePanel.on('click', '#btn-upload-file', function(e) {
                e.preventDefault();
                self.uploadFile();
            });
            this.$sidePanel.on('click', '[data-role="delete-file"]', function() {
                self.deleteFile($(this).data('id'));
            });

            // Add Stage
            this.$sidePanel.on('click', '#btn-add-stage', function(e) {
                e.preventDefault();
                self.addStage();
            });
        }

        // --- DATA LOADING ---
        loadOrders() {
            this.$tbody.html('<tr><td colspan="11" style="text-align:center;">Loading...</td></tr>');
            
            const payload = {
                page: this.state.page,
                per_page: this.state.perPage,
                ...this.state.filters
            };

            ajaxRequest('jwpm_get_custom_orders', payload).done((res) => {
                if(!res.success) {
                    this.$tbody.html('<tr><td colspan="11" style="color:red; text-align:center;">Error loading data</td></tr>');
                    return;
                }
                this.renderTable(res.data.items);
                this.renderStats(res.data.items); // Simple stats from current page/data logic
                this.renderPagination(res.data.pagination);
            });
        }

        renderStats(items) {
            // Note: Ideally stats should come from server for accuracy, but using client logic for now
            let active = 0, due = 0, overdue = 0, pending = 0;
            const today = new Date();
            
            items.forEach(i => {
                if(['draft','design_approved','in_production'].includes(i.status)) active++;
                if(i.delivery_date) {
                    const d = new Date(i.delivery_date);
                    if(d < today && i.status !== 'delivered') overdue++;
                }
                if(i.status !== 'delivered') pending += (parseFloat(i.net_amount) || 0);
            });

            this.$statActive.text(active);
            this.$statOverdue.text(overdue);
            this.$statPending.text(formatCurrency(pending));
            // 'Due' logic simplified
            this.$statDue.text(items.filter(i => i.status !== 'delivered' && i.delivery_date).length); 
        }

        renderTable(items) {
            if(!items.length) {
                this.$tbody.html('<tr><td colspan="11" style="text-align:center;">No orders found.</td></tr>');
                return;
            }

            let html = '';
            items.forEach(item => {
                const statusColors = {
                    'draft': '#777', 'design_approved': 'orange', 'in_production': 'blue', 'ready': 'purple', 'delivered': 'green', 'cancelled': 'red'
                };
                
                html += `
                    <tr data-id="${item.id}">
                        <td><strong>${item.order_code || '-'}</strong></td>
                        <td>${item.customer_name}<br><small>${item.customer_phone || ''}</small></td>
                        <td>${item.design_title}</td>
                        <td>${item.metal_type} ${item.karat}</td>
                        <td>${formatWeight(item.expected_weight)}</td>
                        <td>${formatCurrency(item.estimate_amount)}</td>
                        <td>${formatCurrency(item.advance_amount)}</td>
                        <td>${item.delivery_date || '-'}</td>
                        <td><span class="jwpm-status-badge" style="color:${statusColors[item.status] || '#000'}; border:1px solid ${statusColors[item.status]}; padding:2px 5px; border-radius:3px; font-size:10px; text-transform:uppercase;">${item.status.replace('_', ' ')}</span></td>
                        <td>${item.priority === 'urgent' ? '🔥 Urgent' : (item.priority === 'vip' ? '⭐ VIP' : 'Normal')}</td>
                        <td>
                            <button class="button button-small" data-role="edit-order">View</button>
                            <button class="button button-small" data-role="delete-order" style="color:red;">&times;</button>
                        </td>
                    </tr>
                `;
            });
            this.$tbody.html(html);
        }

        renderPagination(pg) {
            if(!pg || pg.total_page <= 1) {
                this.$pagination.empty();
                return;
            }
            let html = `Page ${pg.page} of ${pg.total_page} `;
            if(pg.page > 1) html += `<button class="button jwpm-page-btn" data-page="${pg.page-1}">«</button> `;
            if(pg.page < pg.total_page) html += `<button class="button jwpm-page-btn" data-page="${pg.page+1}">»</button>`;
            this.$pagination.html(html);
        }

        // --- SIDE PANEL ---
        openPanel(id) {
            this.state.currentOrderId = id;
            
            // Basic Panel Structure
            const html = `
                <div style="padding:15px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#f5f5f5;">
                    <h2 style="margin:0;">${id ? 'Edit Order' : 'New Order'}</h2>
                    <button class="button" data-role="close-panel">Close ❌</button>
                </div>
                
                <div class="jwpm-tabs" style="display:flex; border-bottom:1px solid #ccc; padding:0 15px; margin-top:10px;">
                    <div class="jwpm-tab active-tab" data-tab="overview" style="padding:10px 15px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent;">Overview</div>
                    <div class="jwpm-tab" data-tab="files" style="padding:10px 15px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent;">Files & Designs</div>
                    <div class="jwpm-tab" data-tab="stages" style="padding:10px 15px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent;">Stage History</div>
                </div>

                <div style="flex:1; overflow-y:auto; padding:20px;">
                    <div id="tab-overview" class="jwpm-tab-content">
                        <form id="jwpm-order-form">
                            <input type="hidden" name="id" value="${id || ''}">
                            <div style="display:flex; gap:10px;">
                                <div style="flex:1;">
                                    <label>Customer Name *</label>
                                    <input type="text" name="customer_name" class="widefat" required>
                                </div>
                                <div style="flex:1;">
                                    <label>Phone</label>
                                    <input type="text" name="customer_phone" class="widefat">
                                </div>
                            </div>
                            <br>
                            <label>Design Title / Description</label>
                            <input type="text" name="design_title" class="widefat">
                            
                            <div style="display:flex; gap:10px; margin-top:10px;">
                                <div style="flex:1;">
                                    <label>Metal</label>
                                    <select name="metal_type" class="widefat"><option value="gold">Gold</option><option value="silver">Silver</option></select>
                                </div>
                                <div style="flex:1;">
                                    <label>Karat</label>
                                    <select name="karat" class="widefat"><option value="21K">21K</option><option value="22K">22K</option><option value="18K">18K</option></select>
                                </div>
                                <div style="flex:1;">
                                    <label>Exp. Weight (g)</label>
                                    <input type="number" step="0.001" name="expected_weight" class="widefat">
                                </div>
                            </div>

                            <div style="display:flex; gap:10px; margin-top:10px;">
                                <div style="flex:1;">
                                    <label>Est. Amount</label>
                                    <input type="number" name="estimate_amount" class="widefat">
                                </div>
                                <div style="flex:1;">
                                    <label>Advance</label>
                                    <input type="number" name="advance_amount" class="widefat">
                                </div>
                                <div style="flex:1;">
                                    <label>Net Due</label>
                                    <input type="number" name="net_amount" class="widefat" readonly style="background:#eee;">
                                </div>
                            </div>

                            <div style="display:flex; gap:10px; margin-top:10px;">
                                <div style="flex:1;">
                                    <label>Delivery Date</label>
                                    <input type="date" name="delivery_date" class="widefat">
                                </div>
                                <div style="flex:1;">
                                    <label>Status</label>
                                    <select name="status" class="widefat">
                                        <option value="draft">Draft</option>
                                        <option value="design_approved">Design Approved</option>
                                        <option value="in_production">In Production</option>
                                        <option value="ready">Ready</option>
                                        <option value="delivered">Delivered</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div style="flex:1;">
                                    <label>Priority</label>
                                    <select name="priority" class="widefat"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="vip">VIP</option></select>
                                </div>
                            </div>
                            
                            <br>
                            <button type="submit" class="button button-primary button-large" style="width:100%;">Save Order</button>
                        </form>
                    </div>

                    <div id="tab-files" class="jwpm-tab-content" style="display:none;">
                        ${!id ? '<p>Please save order first.</p>' : `
                            <div style="margin-bottom:10px; padding:10px; background:#f0f0f1;">
                                <input type="file" id="file-upload-input">
                                <button class="button" id="btn-upload-file">Upload</button>
                            </div>
                            <table class="widefat striped">
                                <thead><tr><th>File</th><th>Date</th><th>Action</th></tr></thead>
                                <tbody id="jwpm-files-list"><tr><td>Loading...</td></tr></tbody>
                            </table>
                        `}
                    </div>

                    <div id="tab-stages" class="jwpm-tab-content" style="display:none;">
                         ${!id ? '<p>Please save order first.</p>' : `
                            <div style="margin-bottom:10px; padding:10px; background:#f0f0f1;">
                                <select id="stage-select" style="width:40%;">
                                    <option value="CAD Design">CAD Design</option>
                                    <option value="Printing">Printing / Wax</option>
                                    <option value="Casting">Casting</option>
                                    <option value="Filing">Filing / Mounting</option>
                                    <option value="Stone Setting">Stone Setting</option>
                                    <option value="Polishing">Polishing</option>
                                </select>
                                <select id="stage-status" style="width:30%;">
                                    <option value="Started">Started</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Issue">Issue</option>
                                </select>
                                <button class="button" id="btn-add-stage">Update Stage</button>
                                <input type="text" id="stage-notes" placeholder="Optional notes..." style="width:100%; margin-top:5px;">
                            </div>
                            <table class="widefat striped">
                                <thead><tr><th>Date</th><th>Stage</th><th>Status</th><th>Notes</th></tr></thead>
                                <tbody id="jwpm-stages-list"><tr><td>Loading...</td></tr></tbody>
                            </table>
                         `}
                    </div>
                </div>
            `;
            
            this.$sidePanel.html(html).show();
            
            // CSS for tabs
            $('<style>.active-tab{border-bottom:3px solid #0073aa !important; color:#0073aa;}</style>').appendTo('head');

            if(id) {
                this.loadOrderDetails(id);
            }
        }

        loadOrderDetails(id) {
            ajaxRequest('jwpm_get_custom_order', { id: id }).done((res) => {
                if(res.success) {
                    const h = res.data.header;
                    const f = $('#jwpm-order-form');
                    // Fill Form
                    f.find('[name="customer_name"]').val(h.customer_name);
                    f.find('[name="customer_phone"]').val(h.customer_phone);
                    f.find('[name="design_title"]').val(h.design_title);
                    f.find('[name="metal_type"]').val(h.metal_type);
                    f.find('[name="karat"]').val(h.karat);
                    f.find('[name="expected_weight"]').val(h.expected_weight);
                    f.find('[name="estimate_amount"]').val(h.estimate_amount);
                    f.find('[name="advance_amount"]').val(h.advance_amount);
                    f.find('[name="net_amount"]').val(h.net_amount);
                    f.find('[name="delivery_date"]').val(h.delivery_date);
                    f.find('[name="status"]').val(h.status);
                    f.find('[name="priority"]').val(h.priority);

                    // Load Files List
                    this.renderFilesList(res.data.files || []);
                    // Load Stages List
                    this.renderStagesList(res.data.stages || []);
                }
            });
        }

        renderFilesList(files) {
            const $el = $('#jwpm-files-list');
            if(!files.length) { $el.html('<tr><td colspan="3">No files uploaded.</td></tr>'); return; }
            let html = '';
            files.forEach(f => {
                html += `<tr>
                    <td><a href="${f.file_url}" target="_blank">${f.file_name}</a></td>
                    <td>${f.uploaded_at}</td>
                    <td><button class="button-link delete-file" data-role="delete-file" data-id="${f.id}" style="color:red;">Del</button></td>
                </tr>`;
            });
            $el.html(html);
        }

        renderStagesList(stages) {
            const $el = $('#jwpm-stages-list');
            if(!stages.length) { $el.html('<tr><td colspan="4">No history.</td></tr>'); return; }
            let html = '';
            stages.forEach(s => {
                html += `<tr>
                    <td>${s.updated_at}</td>
                    <td>${s.stage}</td>
                    <td><strong>${s.status}</strong></td>
                    <td>${s.notes || '-'}</td>
                </tr>`;
            });
            $el.html(html);
        }

        saveOrder($form) {
            const data = $form.serializeArray();
            ajaxRequest('jwpm_save_custom_order', data).done((res) => {
                if(res.success) {
                    alert("Order Saved!");
                    this.$sidePanel.hide();
                    this.loadOrders();
                } else {
                    alert("Error saving");
                }
            });
        }

        deleteOrder(id) {
            if(!confirm("Cancel this order?")) return;
            ajaxRequest('jwpm_delete_custom_order', { id: id }).done((res) => {
                if(res.success) {
                    this.loadOrders();
                } else alert("Failed");
            });
        }

        // Files & Stages Logic
        uploadFile() {
            const file = $('#file-upload-input')[0].files[0];
            if(!file) return alert("Choose a file");
            
            const fd = new FormData();
            fd.append('action', 'jwpm_upload_custom_order_file');
            fd.append('nonce', config.nonce);
            fd.append('order_id', this.state.currentOrderId);
            fd.append('file', file);

            $.ajax({
                url: config.ajaxUrl, type: 'POST', data: fd,
                processData: false, contentType: false
            }).done((res) => {
                if(res.success) {
                    alert("Uploaded");
                    this.loadOrderDetails(this.state.currentOrderId); // Reload to see file
                } else alert(res.data.message);
            });
        }

        deleteFile(fileId) {
            if(!confirm("Delete file?")) return;
            ajaxRequest('jwpm_delete_custom_order_file', { id: fileId }).done((res) => {
                if(res.success) this.loadOrderDetails(this.state.currentOrderId);
            });
        }

        addStage() {
            const stage = $('#stage-select').val();
            const status = $('#stage-status').val();
            const notes = $('#stage-notes').val();
            
            ajaxRequest('jwpm_save_custom_order_stage', {
                order_id: this.state.currentOrderId,
                stage: stage, status: status, notes: notes
            }).done((res) => {
                if(res.success) {
                    alert("Stage Updated");
                    this.loadOrderDetails(this.state.currentOrderId);
                }
            });
        }
        
        createDemo() {
            if(confirm("Create Demo Orders?")) {
                ajaxRequest('jwpm_custom_orders_demo_create', {}).done(() => {
                    alert("Demo Data Created");
                    this.loadOrders();
                });
            }
        }
    }

    // Init
    $(function() {
        if($('#jwpm-custom-orders-root').length) {
            new JWPM_CustomOrders_Page($('#jwpm-custom-orders-root'));
        }
    });

})(jQuery);
