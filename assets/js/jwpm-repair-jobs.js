/**
 * JWPM Repair Jobs Page Script (UI + AJAX)
 * Updated: Direct HTML Injection (No PHP Templates required)
 */
(function ($) {
	'use strict';

	// 🟢 یہاں سے [JWPM Repair Module] شروع ہو رہا ہے

	/**
	 * Safe config (jwpmRepairData) اگر (PHP) سے نہ ملا ہو تو fallback
	 */
	var jwpmRepairConfig = window.jwpmRepairData || {
		ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
		mainNonce: '',
		strings: {
			loading: 'Repair Jobs لوڈ ہو رہے ہیں…',
			saving: 'ڈیٹا محفوظ ہو رہا ہے…',
			saveSuccess: 'Repair job محفوظ ہو گیا۔',
			saveError: 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔',
			deleteConfirm: 'کیا آپ واقعی اس Repair job کو cancel کرنا چاہتے ہیں؟',
			deleteSuccess: 'Repair job cancel / update ہو گیا۔',
			noRecords: 'کوئی Repair job نہیں ملا۔'
		},
		pagination: {
			defaultPerPage: 20,
			perPageOptions: [20, 50, 100]
		}
	};

	/**
	 * Helper notifications (jwpm-common.js) کے ساتھ soft integration
	 */
	function notifySuccess(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastSuccess === 'function') {
			window.jwpmCommon.toastSuccess(message);
		} else if (window.console) {
			console.log('[JWPM Repair] ' + message);
		}
	}

	function notifyError(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastError === 'function') {
			window.jwpmCommon.toastError(message);
		} else {
			if (window.console) {
				console.error('[JWPM Repair] ' + message);
			}
			alert(message);
		}
	}

	function notifyInfo(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastInfo === 'function') {
			window.jwpmCommon.toastInfo(message);
		} else if (window.console) {
			console.log('[JWPM Repair] ' + message);
		}
	}

	function confirmAction(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.confirm === 'function') {
			return window.jwpmCommon.confirm(message);
		}
		return window.confirm(message);
	}

	/**
	 * Common (AJAX) Helper
	 */
	function ajaxRequest(action, data, options) {
		options = options || {};
		var payload = $.extend({}, data, { action: action });

		return $.ajax({
			url: jwpmRepairConfig.ajaxUrl,
			type: options.type || 'POST',
			data: payload,
			dataType: options.dataType || 'json',
			processData: options.processData !== false,
			contentType:
				options.contentType !== false
					? 'application/x-www-form-urlencoded; charset=UTF-8'
					: false
		});
	}

	function parseNumber(value) {
		if (value === null || typeof value === 'undefined') {
			return 0;
		}
		var v = parseFloat(value);
		return isNaN(v) ? 0 : v;
	}

	function formatAmount(value) {
		var n = parseNumber(value);
		return n.toFixed(2);
	}

	/**
	 * مین Repair Jobs Page Controller
	 */
	var JWPMRepairPage = (function () {
		function JWPMRepairPage($root) {
			this.$root = $root;

			this.state = {
				items: [],
				page: 1,
				perPage: jwpmRepairConfig.pagination.defaultPerPage || 20,
				total: 0,
				totalPages: 1,
				filters: {
					search: '',
					status: '',
					priority: '',
					date_from: '',
					date_to: ''
				},
				loading: false,
				currentRepairId: null
			};

			this.$layout = null;
			this.$tableBody = null;
			this.$pagination = null;
			this.$sidePanel = null;
			this.$importModal = null;
			
			// Templates now defined as methods returning HTML strings

			this.init();
		}

		// 1. HTML Layout Injection
		JWPMRepairPage.prototype.renderLayout = function () {
			this.$root.html(`
				<div class="jwpm-page-repair jwpm-wrapper">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:15px; border-bottom:1px solid #eee;">
						<h2 style="margin:0;">🛠️ Repair Jobs / Workshop Repairs</h2>
						<div>
							<button class="button button-primary" data-jwpm-repair-action="add">+ New Job</button>
							<button class="button" data-jwpm-repair-action="export">Export</button>
							<button class="button" data-jwpm-repair-action="demo-create">Demo Data</button>
						</div>
					</div>

					<div style="display:flex; gap:20px; margin-bottom:20px; flex-wrap:wrap;">
						
						<div class="jwpm-card" style="flex:1; padding:15px; background:#e6f0ff; border-left:4px solid #0073aa;">
							<div style="font-size:12px; color:#555;">In Workshop</div>
							<div data-jwpm-repair-stat="workshop" style="font-size:1.5em; color:#0073aa;">
                                <span class="jwpm-stat-value" style="font-weight:bold;">0</span>
                            </div>
						</div>
						<div class="jwpm-card" style="flex:1; padding:15px; background:#fff0e6; border-left:4px solid #ff9900;">
							<div style="font-size:12px; color:#555;">Ready (Pending Delivery)</div>
							<div data-jwpm-repair-stat="ready" style="font-size:1.5em; color:#ff9900;">
                                <span class="jwpm-stat-value" style="font-weight:bold;">0</span>
                            </div>
						</div>
						<div class="jwpm-card" style="flex:1; padding:15px; background:#ffe6e6; border-left:4px solid #d63638;">
							<div style="font-size:12px; color:#555;">Overdue Jobs</div>
							<div data-jwpm-repair-stat="overdue" style="font-size:1.5em; color:#d63638;">
                                <span class="jwpm-stat-value" style="font-weight:bold;">0</span>
                            </div>
						</div>
						<div class="jwpm-card" style="flex:1; padding:15px; background:#f0f0f1; border-left:4px solid #333;">
							<div style="font-size:12px; color:#555;">Pending Balance</div>
							<div data-jwpm-repair-stat="pending_amount" style="font-size:1.5em;">
                                <span class="jwpm-stat-value" style="font-weight:bold;">0.00</span>
                            </div>
						</div>

						<div class="jwpm-card" style="padding:15px; flex:2; display:flex; gap:10px; align-items:center;">
							<input type="text" data-jwpm-repair-filter="search" placeholder="Search Code / Tag / Customer..." style="padding:6px; width:180px;">
							<select data-jwpm-repair-filter="status" style="padding:6px;">
								<option value="">All Status</option>
								<option value="received">Received</option>
								<option value="in_workshop">In Workshop</option>
								<option value="ready">Ready</option>
								<option value="delivered">Delivered</option>
								<option value="cancelled">Cancelled</option>
							</select>
							<select data-jwpm-repair-filter="priority" style="padding:6px;">
								<option value="">All Priority</option>
								<option value="normal">Normal</option>
								<option value="urgent">Urgent</option>
								<option value="vip">VIP</option>
							</select>
							<input type="date" data-jwpm-repair-filter="date_from" title="From Date">
							<input type="date" data-jwpm-repair-filter="date_to" title="To Date">
						</div>
					</div>

					<table class="wp-list-table widefat fixed striped jwpm-table-repairs">
						<thead>
							<tr>
								<th>Job Code</th>
								<th>Tag No</th>
								<th>Customer</th>
								<th>Item Description</th>
								<th>Job Type</th>
								<th>Promised Date</th>
								<th>Charges</th>
								<th>Balance Due</th>
								<th>Status</th>
								<th>Priority</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody data-jwpm-repair-table-body>
							<tr><td colspan="11">Loading...</td></tr>
						</tbody>
					</table>

					<div class="tablenav bottom">
						<div class="tablenav-pages" data-jwpm-repair-pagination></div>
					</div>

					<div data-jwpm-repair-side-panel style="display:none; position:fixed; top:0; right:0; width:650px; height:100%; background:#fff; box-shadow:-2px 0 10px rgba(0,0,0,0.2); z-index:9999; display:flex; flex-direction:column;">
						</div>
				</div>
			`);
		};

		// 2. Templates for dynamic parts
		JWPMRepairPage.prototype.templatePanel = function (isNew = true) {
			// Tab content HTML
			const overviewTab = `
				<div data-jwpm-repair-tab-panel="overview" class="jwpm-tab-panel is-active">
					<form data-jwpm-repair-form>
						<input type="hidden" data-jwpm-repair-input="id" name="id" value="">
						<div style="display:flex; gap:10px; margin-bottom:10px;">
							<div style="flex:1;"><label>Job Code</label><input type="text" data-jwpm-repair-input="job_code" name="job_code" class="widefat" readonly style="background:#f0f0f0;"></div>
							<div style="flex:1;"><label>Tag No (Optional)</label><input type="text" data-jwpm-repair-input="tag_no" name="tag_no" class="widefat"></div>
						</div>
						<div style="display:flex; gap:10px; margin-bottom:10px;">
							<div style="flex:1;"><label>Customer Name *</label><input type="text" data-jwpm-repair-input="customer_name" name="customer_name" class="widefat" required></div>
							<div style="flex:1;"><label>Phone *</label><input type="text" data-jwpm-repair-input="customer_phone" name="customer_phone" class="widefat" required></div>
						</div>
						<label>Item Description / Type *</label>
						<input type="text" data-jwpm-repair-input="item_description" name="item_description" class="widefat" required style="margin-bottom:10px;">
						<div style="display:flex; gap:10px; margin-bottom:10px;">
							<div style="flex:1;"><label>Job Type</label><input type="text" data-jwpm-repair-input="job_type" name="job_type" class="widefat" list="job-types"></div>
							<div style="flex:1;"><label>Received Date</label><input type="date" data-jwpm-repair-input="received_date" name="received_date" class="widefat"></div>
							<div style="flex:1;"><label>Promised Date</label><input type="date" data-jwpm-repair-input="promised_date" name="promised_date" class="widefat"></div>
						</div>
						<datalist id="job-types"><option value="Polishing"><option value="Setting"><option value="Resizing"><option value="Customization"></datalist>
						
						<label>Observed Problems / Defects</label>
						<textarea data-jwpm-repair-input="problems" name="problems" class="widefat" style="margin-bottom:10px;"></textarea>

						<div style="display:flex; gap:10px; margin-top:20px; border-top:1px dashed #ccc; padding-top:10px;">
							<div style="flex:1;"><label>Estimated Charges</label><input type="number" step="0.01" data-jwpm-repair-input="estimated_charges" name="estimated_charges" class="widefat"></div>
							<div style="flex:1;"><label>Actual Charges</label><input type="number" step="0.01" data-jwpm-repair-input="actual_charges" name="actual_charges" class="widefat"></div>
							<div style="flex:1;"><label>Advance Received</label><input type="number" step="0.01" data-jwpm-repair-input="advance_amount" name="advance_amount" class="widefat"></div>
							<div style="flex:1;"><label>Balance Due</label><input type="number" data-jwpm-repair-input="balance_amount" name="balance_amount" class="widefat" readonly style="background:#eee;"></div>
						</div>
						
						<div style="margin-top:20px;"><button type="submit" class="button button-primary button-large" data-jwpm-repair-action="save-repair" style="width:100%;">Save Repair Job</button></div>
					</form>
				</div>
			`;

			const workshopTab = `
				<div data-jwpm-repair-tab-panel="workshop" class="jwpm-tab-panel" style="display:none;">
					<form data-jwpm-repair-workshop-form>
						<label>Instructions to Workshop</label>
						<textarea data-jwpm-repair-input="instructions" name="instructions" class="widefat" style="margin-bottom:10px;"></textarea>
						<label>Workshop Notes (Internal)</label>
						<textarea data-jwpm-repair-input="workshop_notes" name="workshop_notes" class="widefat" style="margin-bottom:10px;"></textarea>
						
						<div style="display:flex; gap:10px; margin-bottom:10px;">
							<div style="flex:1;"><label>Gold Weight In (g)</label><input type="number" step="0.001" data-jwpm-repair-input="gold_weight_in" name="gold_weight_in" class="widefat"></div>
							<div style="flex:1;"><label>Gold Weight Out (g)</label><input type="number" step="0.001" data-jwpm-repair-input="gold_weight_out" name="gold_weight_out" class="widefat"></div>
						</div>

						<div style="display:flex; gap:10px; margin-bottom:10px;">
							<div style="flex:1;"><label>Job Status</label>
								<select data-jwpm-repair-input="job_status" name="job_status" class="widefat">
									<option value="received">Received</option>
									<option value="in_workshop">In Workshop</option>
									<option value="ready">Ready</option>
									<option value="delivered">Delivered</option>
									<option value="cancelled">Cancelled</option>
								</select>
							</div>
							<div style="flex:1;"><label>Assigned To</label><input type="text" data-jwpm-repair-input="assigned_to" name="assigned_to" class="widefat"></div>
							<div style="flex:1;"><label>Priority</label>
								<select data-jwpm-repair-input="priority" name="priority" class="widefat">
									<option value="normal">Normal</option>
									<option value="urgent">Urgent</option>
									<option value="vip">VIP</option>
								</select>
							</div>
						</div>
						<div style="margin-top:20px;"><button type="button" class="button button-primary button-large" data-jwpm-repair-action="save-repair" style="width:100%;">Update Workshop Details</button></div>
					</form>
				</div>
			`;

			const timelineTab = `
				<div data-jwpm-repair-tab-panel="timeline" class="jwpm-tab-panel" style="display:none;">
					<div style="margin-bottom:15px; padding:10px; border:1px solid #ddd;">
						<h4 style="margin-top:0;">Add Log Entry</h4>
						<div style="display:flex; gap:10px; margin-bottom:10px;">
							<select data-jwpm-repair-log-input="status" style="flex:1;">
								<option value="received">Received</option>
								<option value="in_workshop">In Workshop</option>
								<option value="ready">Ready</option>
								<option value="delivered">Delivered</option>
								<option value="cancelled">Cancelled</option>
							</select>
							<input type="text" data-jwpm-repair-log-input="note" placeholder="Note/Update (Optional)" style="flex:2;">
						</div>
						<button class="button button-small" data-jwpm-repair-action="add-log">Add Log</button>
					</div>
					
					<h4 style="margin-top:0;">Job History</h4>
					<table class="widefat striped">
						<thead>
							<tr><th>Date/Time</th><th>Status</th><th>Note</th><th>By</th></tr>
						</thead>
						<tbody data-jwpm-repair-logs-body>
							<tr><td colspan="4">History will load here.</td></tr>
						</tbody>
					</table>
				</div>
			`;
			
			// Main panel container HTML
			return `
				<div style="padding:15px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#f5f5f5;">
					<h2 data-jwpm-repair-panel-title style="margin:0;">${isNew ? 'New Repair Job' : 'Loading...'}</h2>
					<span data-jwpm-repair-panel-tag class="jwpm-status-badge" style="margin-left:10px;"></span>
					<span data-jwpm-repair-panel-status class="jwpm-status-badge" style="margin-left:10px;"></span>
					<span data-jwpm-repair-panel-priority class="jwpm-priority-badge" style="margin-left:10px;"></span>
					<button class="button" data-jwpm-repair-action="close-panel">Close ❌</button>
				</div>
				
				<div class="jwpm-tabs" style="display:flex; border-bottom:1px solid #ccc; padding:0 15px; margin-top:10px;">
					<div class="jwpm-tab is-active" data-jwpm-repair-tab="overview" style="padding:10px 15px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent;">Overview</div>
					<div class="jwpm-tab" data-jwpm-repair-tab="workshop" style="padding:10px 15px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent;">Workshop Details</div>
					<div class="jwpm-tab" data-jwpm-repair-tab="timeline" style="padding:10px 15px; cursor:pointer; font-weight:bold; border-bottom:3px solid transparent;">Timeline/Logs</div>
				</div>

				<div style="flex:1; overflow-y:auto; padding:20px;">
					${overviewTab}
					${workshopTab}
					${timelineTab}
				</div>
			`;
		};


		JWPMRepairPage.prototype.init = function () {
			// No template check needed now
			this.renderLayout();
			this.cacheElements();
			this.bindEvents();
			this.loadRepairs();
		};

		JWPMRepairPage.prototype.cacheElements = function () {
			this.$layout = this.$root.find('.jwpm-page-repair').first();
			this.$tableBody = this.$layout.find('[data-jwpm-repair-table-body]').first();
			this.$pagination = this.$layout.find('[data-jwpm-repair-pagination]').first();
			this.$sidePanel = this.$layout.find('[data-jwpm-repair-side-panel]').first();
		};

		JWPMRepairPage.prototype.bindEvents = function () {
			var self = this;

			// Filters
			this.$layout.on('input change', '[data-jwpm-repair-filter]', function () {
				var filterName = $(this).data('jwpm-repair-filter');
				self.state.filters[filterName] = $(this).val();
				self.state.page = 1;
				
				if(filterName === 'search') {
					clearTimeout(self.searchTimer);
					self.searchTimer = setTimeout(() => self.loadRepairs(), 500);
				} else {
					self.loadRepairs();
				}
			});

			// Toolbar actions
			this.$layout.on('click', '[data-jwpm-repair-action="add"]', function () {
				self.openRepairPanel(null);
			});

			this.$layout.on('click', '[data-jwpm-repair-action="import"]', function () {
				self.openImportModal();
			});

			this.$layout.on('click', '[data-jwpm-repair-action="export"]', function () {
				self.exportRepairs();
			});

			this.$layout.on('click', '[data-jwpm-repair-action="print"]', function () {
				self.printRepairsList();
			});

			this.$layout.on('click', '[data-jwpm-repair-action="demo-create"]', function () {
				self.createDemoRepairs();
			});

			this.$layout.on('click', '[data-jwpm-repair-action="demo-clear"]', function () {
				self.clearDemoRepairs();
			});

			// Table row actions
			this.$layout.on('click', '[data-jwpm-repair-action="view"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-repair-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.openRepairPanel(id);
				}
			});

			this.$layout.on('click', '[data-jwpm-repair-action="mark-ready"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-repair-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.quickUpdateStatus(id, 'ready');
				}
			});

			this.$layout.on('click', '[data-jwpm-repair-action="mark-delivered"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-repair-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.quickUpdateStatus(id, 'delivered');
				}
			});

			this.$layout.on('click', '[data-jwpm-repair-action="print-ticket"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-repair-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.printTicket(id, $row);
				}
			});

			this.$layout.on('click', '[data-jwpm-repair-action="delete"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-repair-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.deleteRepair(id);
				}
			});

			// Status / priority badge quick change
			this.$layout.on('click', '[data-jwpm-repair-field="status_badge"]', function () {
				var $row = $(this).closest('[data-jwpm-repair-row]');
				var id = parseInt($row.data('id'), 10);
				if (!id) return;
				var current = $(this).attr('data-status') || 'received';
				var next =
					current === 'received'
						? 'in_workshop'
						: current === 'in_workshop'
						? 'ready'
						: current === 'ready'
						? 'delivered'
						: current === 'delivered'
						? 'cancelled'
						: 'received';
				self.quickUpdateStatus(id, next);
			});

			this.$layout.on('click', '[data-jwpm-repair-field="priority_badge"]', function () {
				var $row = $(this).closest('[data-jwpm-repair-row]');
				var id = parseInt($row.data('id'), 10);
				if (!id) return;
				var current = $(this).attr('data-priority') || 'normal';
				var next = current === 'normal' ? 'urgent' : current === 'urgent' ? 'vip' : 'normal';
				self.quickUpdatePriority(id, next);
			});

			// Pagination
			this.$pagination.on('click', '[data-jwpm-page]', function () {
				var page = parseInt($(this).attr('data-jwpm-page'), 10);
				if (!isNaN(page) && page >= 1 && page <= self.state.totalPages && page !== self.state.page) {
					self.state.page = page;
					self.loadRepairs();
				}
			});

			this.$pagination.on('change', '[data-jwpm-per-page]', function () {
				var per = parseInt($(this).val(), 10);
				if (!isNaN(per) && per > 0) {
					self.state.perPage = per;
					self.state.page = 1;
					self.loadRepairs();
				}
			});
			
			// Sidepanel Specific Events
			// Save repair (handles both overview form and workshop update form)
			this.$sidePanel.off('click', '[data-jwpm-repair-action="save-repair"]').on('click', '[data-jwpm-repair-action="save-repair"]', function(e) {
				e.preventDefault();
				const $form = $(this).closest('form');
				if($form.length) self.saveRepair($form);
			});

			// Auto balance calc (must be delegated since panel is dynamic)
			this.$sidePanel.off('input', '[data-jwpm-repair-input="actual_charges"], [data-jwpm-repair-input="advance_amount"]').on(
				'input',
				'[data-jwpm-repair-input="actual_charges"], [data-jwpm-repair-input="advance_amount"]',
				function () {
					const $panelForm = $(this).closest('[data-jwpm-repair-form]');
					self.recalculateAmounts($panelForm);
				}
			);

			// Timeline: add log
			this.$sidePanel.off('click', '[data-jwpm-repair-action="add-log"]').on('click', '[data-jwpm-repair-action="add-log"]', function (e) {
				e.preventDefault();
				var repairId = self.state.currentRepairId;
				if (!repairId) {
					notifyInfo('پہلے Repair job محفوظ کریں، پھر Timeline update کریں۔');
					return;
				}
				self.saveRepairLog(repairId);
			});
			
			// Tabs
			this.$sidePanel.off('click', '.jwpm-tab').on('click', '.jwpm-tab', function () {
				var tab = $(this).attr('data-jwpm-repair-tab');
				if (!tab) return;

				self.$sidePanel.find('.jwpm-tab').removeClass('is-active');
				$(this).addClass('is-active');

				self.$sidePanel.find('.jwpm-tab-panel').removeClass('is-active').hide();
				self.$sidePanel
					.find('[data-jwpm-repair-tab-panel="' + tab + '"]')
					.addClass('is-active').show();
			});
		};

		JWPMRepairPage.prototype.setLoading = function (loading) {
			this.state.loading = loading;
			if (loading) {
				this.$root.addClass('jwpm-is-loading');
			} else {
				this.$root.removeClass('jwpm-is-loading');
			}
		};

		/**
		 * Repair list load + render
		 */
		JWPMRepairPage.prototype.loadRepairs = function () {
			var self = this;

			this.setLoading(true);

			this.$tableBody.empty().append(
				$('<tr/>', { class: 'jwpm-loading-row' }).append(
					$('<td/>', {
						colspan: 11,
						text: jwpmRepairConfig.strings.loading || 'لوڈ ہو رہا ہے…'
					})
				)
			);

			ajaxRequest('jwpm_get_repairs', {
				nonce: jwpmRepairConfig.mainNonce,
				search: this.state.filters.search,
				status: this.state.filters.status,
				priority: this.state.filters.priority,
				date_from: this.state.filters.date_from,
				date_to: this.state.filters.date_to,
				page: this.state.page,
				per_page: this.state.perPage
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								jwpmRepairConfig.strings.saveError
						);
						return;
					}

					var data = response.data || {};
					self.state.items = data.items || [];
					self.state.total =
						data.pagination && typeof data.pagination.total !== 'undefined'
							? parseInt(data.pagination.total, 10) || 0
							: 0;
					self.state.page =
						data.pagination && typeof data.pagination.page !== 'undefined'
							? parseInt(data.pagination.page, 10) || 1
							: 1;
					self.state.perPage =
						data.pagination && typeof data.pagination.per_page !== 'undefined'
							? parseInt(data.pagination.per_page, 10) || self.state.perPage
							: self.state.perPage;
					self.state.totalPages =
						data.pagination && typeof data.pagination.total_page !== 'undefined'
							? parseInt(data.pagination.total_page, 10) || 1
							: 1;

					self.renderTable();
					self.renderStats();
					self.renderPagination();
				})
				.fail(function () {
					notifyError(
						jwpmRepairConfig.strings.saveError || 'Repair Jobs لوڈ نہیں ہو سکے۔'
					);
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMRepairPage.prototype.renderStats = function () {
			var inWorkshop = 0;
			var readyNotDelivered = 0;
			var overdue = 0;
			var pendingAmount = 0;

			var today = new Date();

			function parseDate(str) {
				if (!str) return null;
				var d = new Date(str);
				return isNaN(d.getTime()) ? null : d;
			}

			this.state.items.forEach(function (job) {
				var st = job.job_status || 'received';
				var promised = parseDate(job.promised_date);
				var balance = parseNumber(job.balance_amount);

				if (st === 'in_workshop') {
					inWorkshop++;
				}
				if (st === 'ready') {
					readyNotDelivered++;
				}
				if (promised && promised < today && st !== 'delivered' && st !== 'cancelled') {
					overdue++;
				}
				if (st !== 'delivered' && st !== 'cancelled') {
					pendingAmount += balance;
				}
			});

			this.$layout
				.find('[data-jwpm-repair-stat="workshop"] .jwpm-stat-value')
				.text(inWorkshop);
			this.$layout
				.find('[data-jwpm-repair-stat="ready"] .jwpm-stat-value')
				.text(readyNotDelivered);
			this.$layout
				.find('[data-jwpm-repair-stat="overdue"] .jwpm-stat-value')
				.text(overdue);
			this.$layout
				.find('[data-jwpm-repair-stat="pending_amount"] .jwpm-stat-value')
				.text(formatAmount(pendingAmount));
		};

		JWPMRepairPage.prototype.renderTable = function () {
			var self = this;
			this.$tableBody.empty();

			if (!this.state.items || !this.state.items.length) {
				this.$tableBody.append(
					$('<tr/>', { class: 'jwpm-empty-row' }).append(
						$('<td/>', {
							colspan: 11,
							text:
								jwpmRepairConfig.strings.noRecords ||
								'کوئی Repair job نہیں ملا۔'
						})
					)
				);
				return;
			}

			// Removed check for templates.row

			this.state.items.forEach(function (item) {
				var $tr = $('<tr/>', { 'data-jwpm-repair-row': '', 'data-id': item.id });
				const itemJson = JSON.stringify(item).replace(/'/g, "&#39;");
				$tr.attr('data-json', itemJson);

				$tr.append($('<td/>').text(item.job_code || ''));
				$tr.append($('<td/>').text(item.tag_no || ''));
				$tr.append($('<td/>').text(item.customer_name || ''));
				$tr.append($('<td/>').text(item.item_description || ''));
				$tr.append($('<td/>').text(item.job_type || ''));
				$tr.append($('<td/>').text(item.promised_date || ''));
				$tr.append($('<td/>', {'data-jwpm-repair-field': 'actual_charges'}).text(formatAmount(item.actual_charges)));
				$tr.append($('<td/>', {'data-jwpm-repair-field': 'balance_amount'}).text(formatAmount(item.balance_amount)));

				// Status badge
				var status = item.job_status || 'received';
				var $statusBadge = $('<span/>')
					.attr('data-status', status)
					.addClass('jwpm-status-badge')
					.text(
						status === 'in_workshop' ? 'In Workshop' : status === 'ready' ? 'Ready' : status === 'delivered' ? 'Delivered' : status === 'cancelled' ? 'Cancelled' : 'Received'
					);
				$tr.append($('<td/>', {'data-jwpm-repair-field': 'status_badge'}).append($statusBadge));

				// Priority badge
				var priority = item.priority || 'normal';
				var $priorityBadge = $('<span/>')
					.attr('data-priority', priority)
					.addClass('jwpm-priority-badge')
					.text(
						priority === 'urgent' ? 'Urgent' : priority === 'vip' ? 'VIP' : 'Normal'
					);
				$tr.append($('<td/>', {'data-jwpm-repair-field': 'priority_badge'}).append($priorityBadge));

				var $actions = $('<td/>');
				$actions.append($('<button/>', { class: 'button button-small', text: 'View', 'data-jwpm-repair-action': 'view' }));
				$actions.append($('<button/>', { class: 'button button-small', text: 'Print', 'data-jwpm-repair-action': 'print-ticket' }));
				$actions.append($('<button/>', { class: 'button button-small', text: 'Cancel', 'data-jwpm-repair-action': 'delete' }));
				$tr.append($actions);

				self.$tableBody.append($tr);
			});
		};

		JWPMRepairPage.prototype.renderPagination = function () {
			var self = this;
			var page = this.state.page;
			var totalPages = this.state.totalPages;

			this.$pagination.empty();

			if (!totalPages || totalPages <= 1) {
				return;
			}

			var $wrapper = $('<div/>', { class: 'jwpm-pagination-inner' });

			var $prev = $('<button/>', {
				type: 'button',
				class: 'button jwpm-page-prev',
				text: '«'
			}).attr('data-jwpm-page', page > 1 ? page - 1 : 1);

			if (page <= 1) {
				$prev.prop('disabled', true);
			}

			var $next = $('<button/>', {
				type: 'button',
				class: 'button jwpm-page-next',
				text: '»'
			}).attr('data-jwpm-page', page < totalPages ? page + 1 : totalPages);

			if (page >= totalPages) {
				$next.prop('disabled', true);
			}

			var $info = $('<span/>', {
				class: 'jwpm-page-info',
				text: 'Page ' + page + ' / ' + totalPages
			});

			var $perSelect = $('<select/>', {
				class: 'jwpm-select',
				'data-jwpm-per-page': '1'
			});

			(jwpmRepairConfig.pagination.perPageOptions || [20, 50, 100]).forEach(function (val) {
				var $opt = $('<option/>', {
					value: val,
					text: val + ' per page'
				});
				if (val === self.state.perPage) {
					$opt.prop('selected', true);
				}
				$perSelect.append($opt);
			});

			$wrapper.append($prev, $info, $next, $perSelect);
			this.$pagination.append($wrapper);
		};

		/**
		 * Side Panel — Overview / Workshop / Timeline
		 */
		JWPMRepairPage.prototype.openRepairPanel = function (id) {
			var self = this;
			
			// 1. Inject Panel HTML
			this.$sidePanel.empty().html(this.templatePanel(!id)).show();

			var $panel = this.$sidePanel;
			var $form = $panel.find('[data-jwpm-repair-form]').first();
			var $title = $panel.find('[data-jwpm-repair-panel-title]').first();
			var $statusBadge = $panel.find('[data-jwpm-repair-panel-status]').first();
			var $priorityBadge = $panel.find('[data-jwpm-repair-panel-priority]').first();
			var $tagBadge = $panel.find('[data-jwpm-repair-panel-tag]').first();
			
			// Tabs (bind events dynamically on panel load)
			$panel.find('.jwpm-tab.is-active').trigger('click');


			// Close actions
			$panel.on('click', '[data-jwpm-repair-action="close-panel"]', this.closeSidePanel.bind(this));

			// New repair vs existing
			if (!id) {
				this.state.currentRepairId = null;
				$title.text('New Repair Job');
				$statusBadge
					.text('Received')
					.attr('data-status', 'received')
					.addClass('jwpm-status-badge');
				$priorityBadge
					.text('Normal')
					.attr('data-priority', 'normal')
					.addClass('jwpm-priority-badge');
				$tagBadge.text('').attr('data-tag', '');
				
				// Set default dates
				$form.find('[data-jwpm-repair-input="received_date"]').val(new Date().toISOString().slice(0, 10));
				$form.find('[data-jwpm-repair-input="job_status"]').val('received');
				
				this.recalculateAmounts($form);
				this.renderLogs([]);
			} else {
				this.state.currentRepairId = id;
				// Load the rest of the details via AJAX
				this.loadRepairIntoPanel(id, $panel, $form, $title, null, $statusBadge, $priorityBadge, $tagBadge);
			}
		};

		JWPMRepairPage.prototype.closeSidePanel = function () {
			this.$sidePanel.hide().empty();
		};

		JWPMRepairPage.prototype.loadRepairIntoPanel = function (
			id,
			$panel,
			$form,
			$title,
			$subtitle, // Note: subtitle element removed in new template, ignored here.
			$statusBadge,
			$priorityBadge,
			$tagBadge
		) {
			var self = this;

			$title.text('Loading…');

			ajaxRequest('jwpm_get_repair', {
				nonce: jwpmRepairConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success || !response.data || !response.data.header) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Repair job نہیں ملا۔'
						);
						self.closeSidePanel();
						return;
					}

					var header = response.data.header;
					var logs = response.data.logs || [];

					$title.text('Repair: ' + (header.job_code || ''));
					
					// Update badges
					$tagBadge
						.text(header.tag_no || '')
						.attr('data-tag', header.tag_no || '');

					var st = header.job_status || 'received';
					$statusBadge
						.text(
							st === 'in_workshop' ? 'In Workshop' : st === 'ready' ? 'Ready' : st === 'delivered' ? 'Delivered' : st === 'cancelled' ? 'Cancelled' : 'Received'
						)
						.attr('data-status', st)
						.addClass('jwpm-status-badge');

					var priority = header.priority || 'normal';
					$priorityBadge
						.text(
							priority === 'urgent' ? 'Urgent' : priority === 'vip' ? 'VIP' : 'Normal'
						)
						.attr('data-priority', priority)
						.addClass('jwpm-priority-badge');

					// Overview form fill
					$form.find('[data-jwpm-repair-input="id"]').val(header.id || '');
					$form
						.find('[data-jwpm-repair-input="customer_name"]')
						.val(header.customer_name || '');
					$form
						.find('[data-jwpm-repair-input="customer_phone"]')
						.val(header.customer_phone || '');
					$form
						.find('[data-jwpm-repair-input="tag_no"]')
						.val(header.tag_no || '');
					$form
						.find('[data-jwpm-repair-input="job_code"]')
						.val(header.job_code || '');
					$form
						.find('[data-jwpm-repair-input="item_description"]')
						.val(header.item_description || '');
					$form
						.find('[data-jwpm-repair-input="job_type"]')
						.val(header.job_type || 'other');
					$form
						.find('[data-jwpm-repair-input="problems"]')
						.val(header.problems || '');
					$form
						.find('[data-jwpm-repair-input="instructions"]')
						.val(header.instructions || '');
					$form
						.find('[data-jwpm-repair-input="received_date"]')
						.val(header.received_date || '');
					$form
						.find('[data-jwpm-repair-input="promised_date"]')
						.val(header.promised_date || '');
					$form
						.find('[data-jwpm-repair-input="delivered_date"]')
						.val(header.delivered_date || '');
					$form
						.find('[data-jwpm-repair-input="gold_weight_in"]')
						.val(header.gold_weight_in || '');
					$form
						.find('[data-jwpm-repair-input="gold_weight_out"]')
						.val(header.gold_weight_out || '');
					$form
						.find('[data-jwpm-repair-input="estimated_charges"]')
						.val(header.estimated_charges || '');
					$form
						.find('[data-jwpm-repair-input="actual_charges"]')
						.val(header.actual_charges || '');
					$form
						.find('[data-jwpm-repair-input="advance_amount"]')
						.val(header.advance_amount || '');
					$form
						.find('[data-jwpm-repair-input="balance_amount"]')
						.val(header.balance_amount || '');
					$form
						.find('[data-jwpm-repair-input="payment_status"]')
						.val(header.payment_status || 'unpaid');
					$form
						.find('[data-jwpm-repair-input="job_status"]')
						.val(header.job_status || 'received');
					$form
						.find('[data-jwpm-repair-input="assigned_to"]')
						.val(header.assigned_to || '');
					$form
						.find('[data-jwpm-repair-input="priority"]')
						.val(header.priority || 'normal');
					$form
						.find('[data-jwpm-repair-input="workshop_notes"]')
						.val(header.workshop_notes || '');
					$form
						.find('[data-jwpm-repair-input="internal_remarks"]')
						.val(header.internal_remarks || '');

					self.recalculateAmounts($form);
					self.renderLogs(logs);
				})
				.fail(function () {
					notifyError('Repair job ڈیٹا لوڈ نہیں ہو سکا۔');
					self.closeSidePanel();
				});
		};

		JWPMRepairPage.prototype.serializeForm = function ($form) {
			var data = {};
			$.each($form.serializeArray(), function (_, field) {
				data[field.name] = field.value;
			});
			return data;
		};

		JWPMRepairPage.prototype.recalculateAmounts = function ($form) {
			if (!$form || !$form.length) return;

			var actual = parseNumber(
				$form
					.find('[data-jwpm-repair-input="actual_charges"]')
					.val()
			);
			var advance = parseNumber(
				$form
					.find('[data-jwpm-repair-input="advance_amount"]')
					.val()
			);

			var balance = actual - advance;

			$form
				.find('[data-jwpm-repair-input="balance_amount"]')
				.val(balance.toFixed(2));
		};

		/**
		 * Save Repair (Overview + Workshop)
		 */
		JWPMRepairPage.prototype.saveRepair = function ($form) {
			var self = this;

			if (!$form || !$form.length) {
				return;
			}
			
			// Collect data from the entire panel scope
			const mainForm = this.$sidePanel.find('[data-jwpm-repair-form]').first();
			
			// Combine data from all input fields within the side panel
			var data = {};
			$panel.find(':input[name]').each(function() {
				const name = $(this).attr('name');
				const value = $(this).val();
				// Use the last value if multiple forms have the same name (like job_status)
				data[name] = value; 
			});
			
			// Ensure essential fields from the overview tab are checked
			if (!data.customer_name && !data.customer_phone) {
				notifyError('Customer کا نام یا فون نمبر درج کرنا ضروری ہے۔');
				return;
			}
			
			data.nonce = jwpmRepairConfig.mainNonce;
			data.action = 'jwpm_save_repair'; // Ensure correct action is set

			this.setLoading(true);
			notifyInfo(
				jwpmRepairConfig.strings.saving ||
					'Repair job محفوظ ہو رہا ہے…'
			);

			ajaxRequest('jwpm_save_repair', data)
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								jwpmRepairConfig.strings.saveError
						);
						return;
					}

					notifySuccess(
						jwpmRepairConfig.strings.saveSuccess ||
							'Repair job محفوظ ہو گیا۔'
					);

					if (response.data && response.data.id && !self.state.currentRepairId) {
						self.state.currentRepairId = parseInt(response.data.id, 10);
						// For new entry, reload panel to show Job Code and enable Logs/Workshop
						self.openRepairPanel(self.state.currentRepairId);
					} else {
						self.closeSidePanel();
					}
					self.loadRepairs();
				})
				.fail(function () {
					notifyError(
						jwpmRepairConfig.strings.saveError ||
							'Repair job محفوظ نہیں ہو سکا۔'
					);
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Quick Status / Priority Update
		 */
		JWPMRepairPage.prototype.quickUpdateStatus = function (id, status) {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_save_repair', {
				nonce: jwpmRepairConfig.mainNonce,
				id: id,
				job_status: status,
				quick_update: 1
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Status اپڈیٹ نہیں ہو سکا۔'
						);
						return;
					}
					notifySuccess('Status اپڈیٹ ہو گیا۔');
					self.loadRepairs();
				})
				.fail(function () {
					notifyError('Status اپڈیٹ نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMRepairPage.prototype.quickUpdatePriority = function (id, priority) {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_save_repair', {
				nonce: jwpmRepairConfig.mainNonce,
				id: id,
				priority: priority,
				quick_update: 1
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Priority اپڈیٹ نہیں ہو سکی۔'
						);
						return;
					}
					notifySuccess('Priority اپڈیٹ ہو گئی۔');
					self.loadRepairs();
				})
				.fail(function () {
					notifyError('Priority اپڈیٹ نہیں ہو سکی۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Delete / Cancel Repair
		 */
		JWPMRepairPage.prototype.deleteRepair = function (id) {
			var self = this;

			if (
				!confirmAction(
					jwpmRepairConfig.strings.deleteConfirm ||
						'کیا آپ واقعی اس Repair job کو cancel کرنا چاہتے ہیں؟'
				)
			) {
				return;
			}

			this.setLoading(true);

			ajaxRequest('jwpm_delete_repair', {
				nonce: jwpmRepairConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Repair job cancel نہیں ہو سکا۔'
						);
						return;
					}
					notifySuccess(
						jwpmRepairConfig.strings.deleteSuccess ||
							'Repair job cancel / update ہو گیا۔'
					);
					self.loadRepairs();
				})
				.fail(function () {
					notifyError('Repair job cancel نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Timeline Logs — render / load / save
		 */
		JWPMRepairPage.prototype.renderLogs = function (logs) {
			var $list = this.$sidePanel.find(
				'[data-jwpm-repair-logs-body]'
			).first();
			if (!$list.length) return;

			$list.empty();

			if (!logs || !logs.length) {
				$list.append(
					$('<tr/>', { class: 'jwpm-empty-row' }).append(
						$('<td/>', {
							colspan: 4,
							text: 'ابھی کوئی history موجود نہیں۔'
						})
					)
				);
				return;
			}

			logs.forEach(function (row) {
				var $tr = $('<tr/>');
				$tr.append($('<td/>').text(row.updated_at || ''));
				$tr.append($('<td/>').text(row.status_label || row.status || ''));
				$tr.append($('<td/>').text(row.note || ''));
				$tr.append($('<td/>').text(row.updated_by || ''));
				$list.append($tr);
			});
		};

		JWPMRepairPage.prototype.loadLogs = function (repairId) {
			var self = this;
			var $list = this.$sidePanel.find(
				'[data-jwpm-repair-logs-body]'
			).first();
			if (!$list.length) return;

			$list
				.empty()
				.append(
					$('<tr/>', { class: 'jwpm-loading-row' }).append(
						$('<td/>', {
							colspan: 4,
							text: jwpmRepairConfig.strings.loading || 'لوڈ ہو رہا ہے…'
						})
					)
				);

			ajaxRequest('jwpm_get_repair_logs', {
				nonce: jwpmRepairConfig.mainNonce,
				repair_id: repairId
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'History لوڈ نہیں ہو سکی۔'
						);
						return;
					}
					var logs = (response.data && response.data.items) || [];
					self.renderLogs(logs);
				})
				.fail(function () {
					notifyError('History لوڈ نہیں ہو سکی۔');
				});
		};

		JWPMRepairPage.prototype.saveRepairLog = function (repairId) {
			var self = this;
			var $statusSelect = this.$sidePanel.find(
				'[data-jwpm-repair-log-input="status"]'
			);
			var $note = this.$sidePanel.find(
				'[data-jwpm-repair-log-input="note"]'
			);

			var status = $statusSelect.val();
			var note = $note.val();

			if (!status) {
				notifyError('Status منتخب کرنا ضروری ہے۔');
				return;
			}

			ajaxRequest('jwpm_save_repair_log', {
				nonce: jwpmRepairConfig.mainNonce,
				repair_id: repairId,
				status: status,
				note: note
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'History update محفوظ نہیں ہو سکی۔'
						);
						return;
					}

					notifySuccess('History update محفوظ ہو گیا۔');
					$note.val('');
					self.loadLogs(repairId);
					self.loadRepairs(); // Update main table status
				})
				.fail(function () {
					notifyError('History update محفوظ نہیں ہو سکی۔');
				});
		};

		/**
		 * Import / Export / Demo / Print
		 */
		JWPMRepairPage.prototype.openImportModal = function () {
			var self = this;
			
			// Inject Modal HTML
			var importModalHtml = `
				<div class="jwpm-modal-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; display:flex; align-items:center; justify-content:center;">
					<div class="jwpm-modal-content" style="background:#fff; padding:20px; border-radius:5px; width:500px;">
						<h3 style="margin-top:0;">Import Repair Jobs</h3>
						<form data-jwpm-repair-import-form>
							<label>CSV File *</label>
							<input type="file" name="file" accept=".csv" required style="margin-bottom:10px;">
							<label><input type="checkbox" name="skip_duplicates" checked> Skip duplicate job codes</label>
							<div data-jwpm-repair-import-result style="margin:10px 0; color:blue;"></div>
							<div style="display:flex; justify-content:space-between; margin-top:20px;">
								<button type="button" class="button" data-jwpm-repair-action="close-import">Cancel</button>
								<button type="button" class="button button-primary" data-jwpm-repair-action="do-import">Start Import</button>
							</div>
						</form>
					</div>
				</div>
			`;
			this.$importModal = $(importModalHtml);
			$('body').append(this.$importModal);

			var $modal = this.$importModal;
			var $form = $modal.find('[data-jwpm-repair-import-form]').first();
			var $result = $modal.find('[data-jwpm-repair-import-result]').first();

			function closeModal() {
				$modal.remove();
				self.$importModal = null;
			}

			$modal.on('click', '[data-jwpm-repair-action="close-import"]', function (e) {
				e.preventDefault();
				closeModal();
			});

			$modal.on('click', '[data-jwpm-repair-action="do-import"]', function (e) {
				e.preventDefault();

				var fileInput = $form.find('input[type="file"]')[0];
				if (!fileInput || !fileInput.files || !fileInput.files.length) {
					notifyError('براہ کرم (CSV) فائل منتخب کریں۔');
					return;
				}

				var formData = new FormData();
				formData.append('action', 'jwpm_import_repairs');
				formData.append('nonce', jwpmRepairConfig.importNonce);
				formData.append('file', fileInput.files[0]);

				var skipDup = $form.find('input[name="skip_duplicates"]').is(':checked') ? 1 : 0;
				formData.append('skip_duplicates', skipDup);

				$result.empty().text(
					jwpmRepairConfig.strings.loading || 'Import ہو رہا ہے…'
				);

				$.ajax({
					url: jwpmRepairConfig.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					dataType: 'json'
				})
					.done(function (response) {
						if (!response || !response.success) {
							notifyError(
								(response && response.data && response.data.message) ||
									jwpmRepairConfig.strings.importError ||
									'Import کے دوران مسئلہ آیا۔'
							);
							return;
						}

						var data = response.data || {};
						var msg =
							(jwpmRepairConfig.strings.importSuccess ||
								'Import مکمل ہو گیا۔') +
							' Total: ' +
							(data.total || 0) +
							', Inserted: ' +
							(data.inserted || 0) +
							', Skipped: ' +
							(data.skipped || 0);

						$result.text(msg);
						notifySuccess(msg);
						self.loadRepairs();
					})
					.fail(function () {
						notifyError(
							jwpmRepairConfig.strings.importError ||
								'Import کے دوران مسئلہ آیا۔'
						);
					});
			});
		};

		JWPMRepairPage.prototype.exportRepairs = function () {
			var url =
				jwpmRepairConfig.ajaxUrl +
				'?action=jwpm_export_repairs&nonce=' +
				encodeURIComponent(jwpmRepairConfig.exportNonce);
			window.open(url, '_blank');
		};

		JWPMRepairPage.prototype.createDemoRepairs = function () {
			var self = this;

			this.setLoading(true);

			// نوٹ: PHP سائیڈ پر demo actions main nonce سے ویری فائی ہو رہے ہیں
			ajaxRequest('jwpm_repair_demo_create', {
				nonce: jwpmRepairConfig.mainNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Demo Repairs نہیں بن سکے۔'
						);
						return;
					}
					notifySuccess(
						jwpmRepairConfig.strings.demoCreateSuccess ||
							'Demo Repairs بنا دیے گئے۔'
					);
					self.loadRepairs();
				})
				.fail(function () {
					notifyError('Demo Repairs نہیں بن سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMRepairPage.prototype.clearDemoRepairs = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_repair_demo_clear', {
				nonce: jwpmRepairConfig.mainNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Demo Repairs حذف نہیں ہو سکے۔'
						);
						return;
					}
					notifySuccess(
						jwpmRepairConfig.strings.demoClearSuccess ||
							'Demo Repairs حذف ہو گئے۔'
					);
					self.loadRepairs();
				})
				.fail(function () {
					notifyError('Demo Repairs حذف نہیں ہو سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMRepairPage.prototype.printRepairsList = function () {
			var $table = this.$layout.find('.jwpm-table-repairs').first();
			if (!$table.length) {
				notifyError('پرنٹ کے لیے کوئی جدول نہیں ملا۔');
				return;
			}

			var html = '<html><head><title>Repair Jobs List</title>';
			html +=
				'<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:12px;color:#000;padding:16px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:4px 6px;text-align:left;} th{background:#eee;} .jwpm-status-badge{font-weight:bold;}</style>';
			html += '</head><body>';
			html += '<h2>Repair Jobs / Workshop Repairs</h2>';
			html += '<p>' + new Date().toLocaleString() + '</p>';
			html += $table.prop('outerHTML');
			html += '</body></html>';

			var w = window.open('', '_blank');
			if (!w) {
				notifyError('پرنٹ ونڈو نہیں کھل سکی۔');
				return;
			}
			w.document.open();
			w.document.write(html);
			w.document.close();
			w.focus();
			w.print();
		};

		JWPMRepairPage.prototype.printTicket = function (id, $row) {
			// سادہ (ticket) print — row سے basic data لے کر چھوٹا پرنٹ جدول
			if (!$row || !$row.length) {
				notifyError('Ticket کیلئے row ڈیٹا نہیں ملا۔');
				return;
			}
			
			// Fetch data from data-json attribute if available
			const item = $row.data('json');
			if(!item) {
				notifyError('Ticket کیلئے تفصیلی ڈیٹا نہیں ملا۔');
				return;
			}

			var jobCode = item.job_code || '';
			var tagNo = item.tag_no || '';
			var customer = item.customer_name || '';
			var phone = item.customer_phone || '';
			var itemDesc = item.item_description || '';
			var jobType = item.job_type || '';
			var promised = item.promised_date || '';
			var actualCharges = formatAmount(item.actual_charges);
			var advance = formatAmount(item.advance_amount);
			var balance = formatAmount(item.balance_amount);
			var received = item.received_date || '';

			var html = '<html><head><title>Repair Ticket</title>';
			html +=
				'<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:11px;color:#000;padding:12px;} table{width:100%;border-collapse:collapse;margin-bottom:8px;} td{padding:3px 4px;vertical-align:top;} .label{width:35%;font-weight:bold;} .title{font-size:14px;font-weight:bold;margin-bottom:6px;text-align:center;} .small{font-size:10px;color:#555;} .border{border:1px solid #000;padding:6px;}</style>';
			html += '</head><body>';
			html += '<div class="border">';
			html += '<div class="title">Repair Job Ticket (' + jobCode + ')</div>';
			html += '<table>';
			html += '<tr><td class="label">Received Date</td><td>' + received + '</td></tr>';
			html += '<tr><td class="label">Promised Date</td><td>' + promised + '</td></tr>';
			html += '<tr><td class="label">Customer</td><td>' + customer + '</td></tr>';
			html += '<tr><td class="label">Phone</td><td>' + phone + '</td></tr>';
			html += '<tr><td class="label">Item/Tag</td><td>' + tagNo + ' / ' + itemDesc + '</td></tr>';
			html += '<tr><td class="label">Job Type</td><td>' + jobType + '</td></tr>';
			html += '</table>';
			
			html += '<table style="margin-top:10px;">';
			html += '<tr><td class="label">Actual Charges</td><td style="text-align:right;">' + actualCharges + '</td></tr>';
			html += '<tr><td class="label">Advance Paid</td><td style="text-align:right;">' + advance + '</td></tr>';
			html += '<tr><td class="label">Balance Due</td><td style="text-align:right;">' + balance + '</td></tr>';
			html += '</table>';
			
			html += '<table style="margin-top:15px;">';
			html += '<tr><td style="border:none;">Customer Signature: ____________________</td></tr>';
			html += '<tr><td style="border:none;">Received By: _________________________</td></tr>';
			html += '</table>';
			html += '<div class="small">' + new Date().toLocaleString() + '</div>';
			html += '</div>';
			html += '</body></html>';

			var w = window.open('', '_blank');
			if (!w) {
				notifyError('Ticket ونڈو نہیں کھل سکی۔');
				return;
			}

			w.document.open();
			w.document.write(html);
			w.document.close();
			w.focus();
			w.print();
		};

		return JWPMRepairPage;
	})();

	/**
	 * DOM Ready — Root mount
	 */
	$(function () {
		var $root = $('#jwpm-repair-root').first();

		if (!$root.length) {
			if (window.console) {
				console.warn(
					'JWPM Repair: #jwpm-repair-root نہیں ملا، شاید یہ صحیح ایڈمن پیج نہیں۔'
				);
			}
			return;
		}

		try {
			new JWPMRepairPage($root);
		} catch (e) {
			console.error('JWPM Repair init error:', e);
			notifyError('Repair Jobs Page لوڈ کرتے وقت مسئلہ آیا۔');
		}
	});

	// 🔴 یہاں پر [JWPM Repair Module] ختم ہو رہا ہے
})(jQuery);

// ✅ Syntax verified block end
