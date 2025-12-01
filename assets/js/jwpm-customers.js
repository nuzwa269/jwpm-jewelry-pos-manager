(function ($) {
	'use strict';

	// 🟢 یہاں سے [JWPM Customers Module] شروع ہو رہا ہے

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
		if (type === 'error') {
			alert(message);
		} else {
			console.log('[JWPM Customers]: ' + message);
		}
	}

	function ajaxRequest(action, data) {
		data = data || {};
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
				perPage: jwpmCustomersConfig.pagination.defaultPerPage || 20,
				total: 0,
				totalPages: 1,
				filters: {
					search: '',
					city: '',
					customer_type: '',
					status: ''
				}
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
			this.$root.html(
				'<div class="jwpm-page-customers jwpm-wrapper">' +
					'<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:15px;">' +
						'<h2 style="margin:0;">👥 Customers Management</h2>' +
						'<div>' +
							'<button class="button button-primary" data-jwpm-customers-action="add">+ Add Customer</button> ' +
							'<button class="button" data-jwpm-customers-action="import">Import CSV</button> ' +
							'<button class="button" data-jwpm-customers-action="demo-create">Demo Data</button>' +
						'</div>' +
					'</div>' +

					'<div class="jwpm-card" style="padding:15px; margin-bottom:20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">' +
						'<input type="text" data-jwpm-customers-filter="search" placeholder="Search Name / Phone..." style="padding:6px; width:200px;">' +
						'<select data-jwpm-customers-filter="city" style="padding:6px;">' +
							'<option value="">All Cities</option>' +
							'<option value="Lahore">Lahore</option>' +
							'<option value="Karachi">Karachi</option>' +
							'<option value="Islamabad">Islamabad</option>' +
						'</select>' +
						'<select data-jwpm-customers-filter="customer_type" style="padding:6px;">' +
							'<option value="">All Types</option>' +
							'<option value="walkin">Walk-in</option>' +
							'<option value="regular">Regular</option>' +
							'<option value="wholesale">Wholesale</option>' +
							'<option value="vip">VIP</option>' +
						'</select>' +
						'<select data-jwpm-customers-filter="status" style="padding:6px;">' +
							'<option value="">All Status</option>' +
							'<option value="active">Active</option>' +
							'<option value="inactive">Inactive</option>' +
						'</select>' +

						'<div style="margin-left:auto; font-weight:bold; color:#666;">' +
							'Total: <span data-stat="total">0</span> | Active: <span data-stat="active">0</span>' +
						'</div>' +
					'</div>' +

					'<table class="wp-list-table widefat fixed striped jwpm-table-customers">' +
						'<thead>' +
							'<tr>' +
								'<th>Code</th>' +
								'<th>Name</th>' +
								'<th>Phone</th>' +
								'<th>City</th>' +
								'<th>Type</th>' +
								'<th>Balance</th>' +
								'<th>Status</th>' +
								'<th>Actions</th>' +
							'</tr>' +
						'</thead>' +
						'<tbody data-jwpm-customers-table-body>' +
							'<tr><td colspan="8">Loading...</td></tr>' +
						'</tbody>' +
					'</table>' +

					'<div class="tablenav bottom">' +
						'<div class="tablenav-pages" data-jwpm-customers-pagination></div>' +
					'</div>' +

					'<div data-jwpm-customers-side-panel style="display:none; position:fixed; top:0; right:0; width:400px; height:100%; background:#fff; box-shadow:-2px 0 5px rgba(0,0,0,0.1); z-index:9999; padding:20px; overflow-y:auto;"></div>' +
				'</div>'
			);
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
				var $el = $(this);
				var type = $el.data('jwpm-customers-filter');

				self.state.filters[type] = $el.val();
				self.state.page = 1;

				// Debounce search
				if (type === 'search') {
					clearTimeout(self.searchTimer);
					self.searchTimer = setTimeout(function () {
						self.loadCustomers();
					}, 500);
				} else {
					self.loadCustomers();
				}
			});

			// Buttons
			this.$root.on('click', '[data-jwpm-customers-action="add"]', function () {
				self.openForm();
			});

			this.$root.on('click', '[data-jwpm-customers-action="import"]', function () {
				alert('Import feature coming soon.');
			});

			// Demo
			this.$root.on('click', '[data-jwpm-customers-action="demo-create"]', function () {
				if (confirm('Create Demo Customers?')) {
					self.createDemoData();
				}
			});

			// Row Actions
			this.$root.on('click', '[data-action="edit"]', function () {
				var id = $(this).closest('tr').data('id');
				self.openForm(id);
			});

			this.$root.on('click', '[data-action="delete"]', function () {
				var id = $(this).closest('tr').data('id');
				self.deleteCustomer(id);
			});

			// Pagination
			this.$root.on('click', '.jwpm-page-btn', function () {
				self.state.page = $(this).data('page');
				self.loadCustomers();
			});
		};

		// 2. Load Data (AJAX → jwpm_customers_fetch)
		JWPMCustomersPage.prototype.loadCustomers = function () {
			var self = this;

			this.$tableBody.html(
				'<tr><td colspan="8" style="text-align:center;">' +
					(jwpmCustomersConfig.strings.loading || 'Loading Customers...') +
				'</td></tr>'
			);

			ajaxRequest('jwpm_customers_fetch', {
				nonce: jwpmCustomersConfig.mainNonce || '',
				search: this.state.filters.search,
				city: this.state.filters.city,
				customer_type: this.state.filters.customer_type,
				status: this.state.filters.status,
				page: this.state.page,
				per_page: this.state.perPage
			})
				.done(function (res) {
					if (!res || !res.success) {
						self.$tableBody.html(
							'<tr><td colspan="8" style="color:red; text-align:center;">Error loading data.</td></tr>'
						);
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
					self.$tableBody.html(
						'<tr><td colspan="8" style="color:red; text-align:center;">Error loading data (AJAX failed).</td></tr>'
					);
				});
		};

		// 3. Render Table
		JWPMCustomersPage.prototype.renderTable = function () {
			var self = this;

			this.$tableBody.empty();

			if (!this.state.items.length) {
				this.$tableBody.append(
					'<tr><td colspan="8" style="text-align:center;">' +
						(jwpmCustomersConfig.strings.noRecords || 'No customers found.') +
					'</td></tr>'
				);
				return;
			}

			this.state.items.forEach(function (item) {
				var json = JSON.stringify(item).replace(/'/g, '&#39;');
				var statusColor = item.status === 'active' ? 'green' : 'red';

				var html =
					'<tr data-id="' + (item.id || '') + '" data-json=\'' + json + '\'>' +
						'<td>' + (item.customer_code || '-') + '</td>' +
						'<td><strong>' + (item.name || '-') + '</strong></td>' +
						'<td>' + (item.phone || '-') + '</td>' +
						'<td>' + (item.city || '-') + '</td>' +
						'<td>' + (item.customer_type || 'walkin') + '</td>' +
						'<td>' + (item.current_balance || '0.000') + '</td>' +
						'<td style="color:' + statusColor + '; font-weight:bold;">' + (item.status || '-') + '</td>' +
						'<td>' +
							'<button class="button button-small" data-action="edit">Edit</button> ' +
							'<button class="button button-small" data-action="delete" style="color:#a00;">Del</button>' +
						'</td>' +
					'</tr>';

				self.$tableBody.append(html);
			});
		};

		JWPMCustomersPage.prototype.renderStats = function () {
			this.$totalStat.text(this.state.total);

			var active = 0;
			this.state.items.forEach(function (item) {
				if (item.status === 'active') {
					active++;
				}
			});

			this.$activeStat.text(active + ' (on page)');
		};

		JWPMCustomersPage.prototype.renderPagination = function () {
			var html = '';

			if (this.state.page > 1) {
				html +=
					'<button class="button jwpm-page-btn" data-page="' +
					(this.state.page - 1) +
					'">« Prev</button> ';
			}

			html +=
				'<span class="description">Page ' +
				this.state.page +
				' of ' +
				this.state.totalPages +
				'</span> ';

			if (this.state.page < this.state.totalPages) {
				html +=
					'<button class="button jwpm-page-btn" data-page="' +
					(this.state.page + 1) +
					'">Next »</button>';
			}

			this.$pagination.html(html);
		};

		// 4. Add/Edit Form (Side Panel) → Save via jwpm_customers_save
		JWPMCustomersPage.prototype.openForm = function (id) {
			var item = {};
			var title = 'Add New Customer';

			if (id) {
				var $row = this.$tableBody.find('tr[data-id="' + id + '"]');
				try {
					item = $row.data('json') || {};
					title = 'Edit Customer';
				} catch (e) {
					item = {};
				}
			}

			var val = function (k) {
				return item[k] || '';
			};

			var html =
				'<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:10px;">' +
					'<h2 style="margin:0;">' + title + '</h2>' +
					'<button class="button" type="button" data-jwpm-customers-action="close-panel">Close ❌</button>' +
				'</div>' +

				'<form id="jwpm-customer-form">' +
					'<input type="hidden" name="id" value="' + val('id') + '">' +
					'<input type="hidden" name="nonce" value="' + (jwpmCustomersConfig.mainNonce || '') + '">' +

					'<label>Name <span style="color:red">*</span></label>' +
					'<input type="text" name="name" class="widefat" value="' + val('name') + '" required style="margin-bottom:10px;">' +

					'<label>Phone <span style="color:red">*</span></label>' +
					'<input type="text" name="phone" class="widefat" value="' + val('phone') + '" required style="margin-bottom:10px;">' +

					'<label>City</label>' +
					'<input type="text" name="city" class="widefat" value="' + val('city') + '" style="margin-bottom:10px;">' +

					'<label>Address</label>' +
					'<textarea name="address" class="widefat" style="margin-bottom:10px;">' + val('address') + '</textarea>' +

					'<div style="display:flex; gap:10px; margin-bottom:10px;">' +
						'<div style="flex:1;">' +
							'<label>CNIC</label>' +
							'<input type="text" name="cnic" class="widefat" value="' + val('cnic') + '">' +
						'</div>' +
						'<div style="flex:1;">' +
							'<label>Opening Balance</label>' +
							'<input type="number" step="0.001" name="opening_balance" class="widefat" value="' + val('opening_balance') + '" ' +
								(id ? 'disabled' : '') +
							'>' +
						'</div>' +
					'</div>' +

					'<label>Status</label>' +
					'<select name="status" class="widefat" style="margin-bottom:10px;">' +
						'<option value="active" ' + (val('status') === 'active' ? 'selected' : '') + '>Active</option>' +
						'<option value="inactive" ' + (val('status') === 'inactive' ? 'selected' : '') + '>Inactive</option>' +
					'</select>' +

					'<label>Customer Type</label>' +
					'<select name="customer_type" class="widefat" style="margin-bottom:20px;">' +
						'<option value="walkin" ' + (val('customer_type') === 'walkin' ? 'selected' : '') + '>Walk-in</option>' +
						'<option value="regular" ' + (val('customer_type') === 'regular' ? 'selected' : '') + '>Regular</option>' +
						'<option value="wholesale" ' + (val('customer_type') === 'wholesale' ? 'selected' : '') + '>Wholesale</option>' +
						'<option value="vip" ' + (val('customer_type') === 'vip' ? 'selected' : '') + '>VIP</option>' +
					'</select>' +

					'<button type="submit" class="button button-primary button-large" style="width:100%;">Save Customer</button>' +
				'</form>';

			this.$sidePanel.html(html).show();

			var self = this;

			// Close panel
			this.$sidePanel
				.off('click.jwpmClosePanel')
				.on('click.jwpmClosePanel', '[data-jwpm-customers-action="close-panel"]', function () {
					self.$sidePanel.hide();
				});

			// Submit → jwpm_customers_save
			$('#jwpm-customer-form')
				.off('submit')
				.on('submit', function (e) {
					e.preventDefault();

					var formDataArray = $(this).serializeArray();
					var data = {};

					formDataArray.forEach(function (field) {
						data[field.name] = field.value;
					});

					data.nonce = data.nonce || (jwpmCustomersConfig.mainNonce || '');

					ajaxRequest('jwpm_customers_save', data)
						.done(function (res) {
							if (res && res.success) {
								alert(jwpmCustomersConfig.strings.saveSuccess || 'Customer Saved!');
								self.$sidePanel.hide();
								self.loadCustomers();
							} else {
								var msg =
									(res && res.data && res.data.message) ||
									(jwpmCustomersConfig.strings.saveError || 'Error saving customer.');
								alert(msg);
							}
						})
						.fail(function () {
							alert(jwpmCustomersConfig.strings.saveError || 'Error saving customer (AJAX failed).');
						});
				});
		};

		// 5. Delete → action: jwpm_customers_delete
		JWPMCustomersPage.prototype.deleteCustomer = function (id) {
			if (!id) {
				return;
			}

			if (!confirm(jwpmCustomersConfig.strings.deleteConfirm || 'Are you sure?')) {
				return;
			}

			var self = this;

			ajaxRequest('jwpm_customers_delete', {
				nonce: jwpmCustomersConfig.mainNonce || '',
				id: id
			})
				.done(function (res) {
					if (res && res.success) {
						alert(jwpmCustomersConfig.strings.deleteSuccess || 'Customer Deleted');
						self.loadCustomers();
					} else {
						alert('Failed to delete.');
					}
				})
				.fail(function () {
					alert('Failed to delete (AJAX failed).');
				});
		};

		// Demo Data → action: jwpm_customers_demo (mode=create)
		JWPMCustomersPage.prototype.createDemoData = function () {
			var self = this;

			ajaxRequest('jwpm_customers_demo', {
				nonce: jwpmCustomersConfig.mainNonce || '',
				mode: 'create'
			})
				.done(function (res) {
					if (res && res.success) {
						alert(res.data && res.data.message ? res.data.message : 'Demo Data Created');
						self.loadCustomers();
					} else {
						alert('Failed to create demo data.');
					}
				})
				.fail(function () {
					alert('Failed to create demo data (AJAX failed).');
				});
		};

		return JWPMCustomersPage;
	})();

	// Init on DOM Ready
	$(function () {
		var $root = $('#jwpm-customers-root');

		if (!$root.length) {
			console.warn('[JWPM Customers] Root element #jwpm-customers-root نہیں ملا۔');
			return;
		}

		new JWPMCustomersPage($root);
	});

	// 🔴 یہاں پر [JWPM Customers Module] ختم ہو رہا ہے
	// ✅ Syntax verified block end

})(jQuery);
