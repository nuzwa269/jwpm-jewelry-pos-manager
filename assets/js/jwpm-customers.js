/** Part 10 — JWPM Customers Page Script (UI + AJAX)
 * یہاں Customers Page کے تمام (JavaScript) behaviours، AJAX calls اور UI rendering ہیں۔
 */
(function ($) {
	'use strict';

	// 🟢 یہاں سے [JWPM Customers Module] شروع ہو رہا ہے

	/**
	 * Safe config (jwpmCustomersData) اگر (PHP) سے نہ ملا ہو تو fallback
	 */
	var jwpmCustomersConfig = window.jwpmCustomersData || {
		ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
		mainNonce: '',
		importNonce: '',
		exportNonce: '',
		demoNonce: '',
		strings: {
			loading: 'کسٹمرز لوڈ ہو رہے ہیں…',
			saving: 'ڈیٹا محفوظ ہو رہا ہے…',
			saveSuccess: 'کسٹمر کامیابی سے محفوظ ہو گیا۔',
			saveError: 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔',
			deleteConfirm: 'کیا آپ واقعی اس کسٹمر کو Inactive کرنا چاہتے ہیں؟',
			deleteSuccess: 'کسٹمر کو Inactive کر دیا گیا۔',
			demoCreateSuccess: 'Demo کسٹمرز بنا دیے گئے۔',
			demoClearSuccess: 'Demo کسٹمرز حذف ہو گئے۔',
			importSuccess: 'Import مکمل ہو گیا۔',
			importError: 'Import کے دوران مسئلہ آیا۔',
			noRecords: 'کوئی ریکارڈ نہیں ملا۔'
		},
		pagination: {
			defaultPerPage: 20,
			perPageOptions: [20, 50, 100]
		},
		capabilities: {
			canManageCustomers: true
		}
	};

	/**
	 * Soft toast / اطلاع (اگر jwpmCommon موجود ہو تو اسی کا، ورنہ simple alert / console)
	 */
	function notifySuccess(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastSuccess === 'function') {
			window.jwpmCommon.toastSuccess(message);
		} else {
			window.console && console.log('[JWPM Customers] ' + message);
		}
	}

	function notifyError(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastError === 'function') {
			window.jwpmCommon.toastError(message);
		} else {
			window.console && console.error('[JWPM Customers] ' + message);
			alert(message);
		}
	}

	function notifyInfo(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastInfo === 'function') {
			window.jwpmCommon.toastInfo(message);
		} else {
			window.console && console.log('[JWPM Customers] ' + message);
		}
	}

	function confirmAction(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.confirm === 'function') {
			return window.jwpmCommon.confirm(message);
		}
		// simple confirm
		return window.confirm(message);
	}

	/**
	 * Common helper: AJAX via (jQuery)
	 */
	function ajaxRequest(action, data, options) {
		options = options || {};

		var payload = $.extend({}, data, { action: action });

		return $.ajax({
			url: jwpmCustomersConfig.ajaxUrl,
			type: options.type || 'POST',
			data: payload,
			dataType: options.dataType || 'json',
			processData: options.processData !== false,
			contentType: options.contentType !== false ? 'application/x-www-form-urlencoded; charset=UTF-8' : false
		});
	}

	/**
	 * Main Customers Page Controller
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
				},
				loading: false
			};

			this.$layout = null;
			this.$tableBody = null;
			this.$pagination = null;
			this.$sidePanel = null;
			this.$importModal = null;

			this.templates = {
				layout: document.getElementById('jwpm-customers-layout-template'),
				row: document.getElementById('jwpm-customers-row-template'),
				form: document.getElementById('jwpm-customers-form-template'),
				importModal: document.getElementById('jwpm-customers-import-template')
			};

			this.init();
		}

		JWPMCustomersPage.prototype.init = function () {
			if (!this.templates.layout) {
				notifyError('Customers layout template نہیں ملا۔');
				return;
			}

			this.renderLayout();
			this.cacheElements();
			this.bindEvents();

			this.loadCustomers();
		};

		JWPMCustomersPage.prototype.renderLayout = function () {
			var tmpl = this.templates.layout.content
				? this.templates.layout.content.cloneNode(true)
				: document.importNode(this.templates.layout, true);

			this.$root.empty().append(tmpl);
		};

		JWPMCustomersPage.prototype.cacheElements = function () {
			this.$layout = this.$root.find('.jwpm-page-customers').first();
			this.$tableBody = this.$layout.find('[data-jwpm-customers-table-body]').first();
			this.$pagination = this.$layout.find('[data-jwpm-customers-pagination]').first();
			this.$sidePanel = this.$layout.find('[data-jwpm-customers-side-panel]').first();
		};

		JWPMCustomersPage.prototype.bindEvents = function () {
			var self = this;

			// Filters
			this.$layout.on('input', '[data-jwpm-customers-filter="search"]', function () {
				self.state.filters.search = $(this).val();
				self.state.page = 1;
				self.loadCustomers();
			});

			this.$layout.on('change', '[data-jwpm-customers-filter="city"]', function () {
				self.state.filters.city = $(this).val();
				self.state.page = 1;
				self.loadCustomers();
			});

			this.$layout.on('change', '[data-jwpm-customers-filter="type"]', function () {
				self.state.filters.customer_type = $(this).val();
				self.state.page = 1;
				self.loadCustomers();
			});

			this.$layout.on('change', '[data-jwpm-customers-filter="status"]', function () {
				self.state.filters.status = $(this).val();
				self.state.page = 1;
				self.loadCustomers();
			});

			// Actions
			this.$layout.on('click', '[data-jwpm-customers-action="add"]', this.onAddCustomer.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="import"]', this.openImportModal.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="export"]', this.exportCustomers.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="print"]', this.printCustomers.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="demo-create"]', this.createDemoCustomers.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="demo-clear"]', this.clearDemoCustomers.bind(this));

			// Table actions
			this.$layout.on('click', '[data-jwpm-customers-action="view"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-customer-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.openCustomerForEdit(id);
				}
			});

			this.$layout.on('click', '[data-jwpm-customers-action="quick-sale"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-customer-row]');
				var id = parseInt($row.data('id'), 10);
				if (id && window.jwpmCommon && typeof window.jwpmCommon.quickSaleWithCustomer === 'function') {
					window.jwpmCommon.quickSaleWithCustomer(id);
				} else {
					notifyInfo('Quick Sale فیچر بعد میں POS کے ساتھ لنک کیا جائے گا۔');
				}
			});

			this.$layout.on('click', '[data-jwpm-customers-action="delete"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-customer-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.deleteCustomer(id);
				}
			});

			// Status toggle (اگر badge پر click ہو)
			this.$layout.on('click', '[data-jwpm-customer-field="status_badge"]', function () {
				var $row = $(this).closest('[data-jwpm-customer-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.toggleCustomerStatus(id);
				}
			});

			// Pagination clicks
			this.$pagination.on('click', '[data-jwpm-page]', function () {
				var page = parseInt($(this).attr('data-jwpm-page'), 10);
				if (!isNaN(page) && page >= 1 && page <= self.state.totalPages && page !== self.state.page) {
					self.state.page = page;
					self.loadCustomers();
				}
			});

			this.$pagination.on('change', '[data-jwpm-per-page]', function () {
				var per = parseInt($(this).val(), 10);
				if (!isNaN(per) && per > 0) {
					self.state.perPage = per;
					self.state.page = 1;
					self.loadCustomers();
				}
			});
		};

		JWPMCustomersPage.prototype.setLoading = function (loading) {
			this.state.loading = loading;
			if (loading) {
				this.$root.addClass('jwpm-is-loading');
			} else {
				this.$root.removeClass('jwpm-is-loading');
			}
		};

		JWPMCustomersPage.prototype.loadCustomers = function () {
			var self = this;

			this.setLoading(true);
			this.$tableBody.empty().append(
				$('<tr/>', { class: 'jwpm-loading-row' }).append(
					$('<td/>', {
						colspan: 10,
						text: jwpmCustomersConfig.strings.loading || 'لوڈ ہو رہا ہے…'
					})
				)
			);

			ajaxRequest('jwpm_get_customers', {
				nonce: jwpmCustomersConfig.mainNonce,
				search: this.state.filters.search,
				city: this.state.filters.city,
				customer_type: this.state.filters.customer_type,
				status: this.state.filters.status,
				page: this.state.page,
				per_page: this.state.perPage
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || jwpmCustomersConfig.strings.saveError);
						return;
					}

					var data = response.data;
					self.state.items = data.items || [];
					self.state.total = data.pagination ? parseInt(data.pagination.total, 10) || 0 : 0;
					self.state.page = data.pagination ? parseInt(data.pagination.page, 10) || 1 : 1;
					self.state.perPage = data.pagination ? parseInt(data.pagination.per_page, 10) || self.state.perPage : self.state.perPage;
					self.state.totalPages = data.pagination ? parseInt(data.pagination.total_page, 10) || 1 : 1;

					self.renderTable();
					self.renderStats();
					self.renderPagination();
				})
				.fail(function () {
					notifyError(jwpmCustomersConfig.strings.saveError || 'ڈیٹا لوڈ نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.renderStats = function () {
			var total = this.state.total || 0;
			var activeCount = 0;

			this.state.items.forEach(function (item) {
				if (item.status === 'active') {
					activeCount++;
				}
			});

			this.$layout
				.find('[data-jwpm-customers-stat="total"] .jwpm-stat-value')
				.text(total);
			this.$layout
				.find('[data-jwpm-customers-stat="active"] .jwpm-stat-value')
				.text(activeCount);
		};

		JWPMCustomersPage.prototype.renderTable = function () {
			var self = this;
			this.$tableBody.empty();

			if (!this.state.items || !this.state.items.length) {
				this.$tableBody.append(
					$('<tr/>', { class: 'jwpm-empty-row' }).append(
						$('<td/>', {
							colspan: 10,
							text: jwpmCustomersConfig.strings.noRecords || 'کوئی ریکارڈ نہیں ملا۔'
						})
					)
				);
				return;
			}

			if (!this.templates.row) {
				notifyError('Customers row template نہیں ملا۔');
				return;
			}

			this.state.items.forEach(function (item) {
				var tr;
				if (self.templates.row.content) {
					tr = self.templates.row.content.cloneNode(true);
					tr = $(tr).children('tr').first();
				} else {
					tr = $(document.importNode(self.templates.row, true));
				}

				tr.attr('data-jwpm-customer-row', '');
				tr.attr('data-id', item.id);

				tr.find('[data-jwpm-customer-field="customer_code"]').text(item.customer_code || '');
				tr.find('[data-jwpm-customer-field="name"]').text(item.name || '');
				tr.find('[data-jwpm-customer-field="phone"]').text(item.phone || '');
				tr.find('[data-jwpm-customer-field="city"]').text(item.city || '');
				tr.find('[data-jwpm-customer-field="customer_type"]').text(item.customer_type || '');

				tr.find('[data-jwpm-customer-field="credit_limit"]').text(item.credit_limit || '0.000');
				tr.find('[data-jwpm-customer-field="current_balance"]').text(item.current_balance || '0.000');

				var lastPurchase = item.last_purchase || '';
				tr.find('[data-jwpm-customer-field="last_purchase"]').text(lastPurchase);

				var $statusCell = tr.find('[data-jwpm-customer-field="status_badge"]');
				$statusCell
					.text(item.status === 'inactive' ? 'Inactive' : 'Active')
					.attr('data-status', item.status || 'active')
					.addClass('jwpm-status-badge');

				self.$tableBody.append(tr);
			});
		};

		JWPMCustomersPage.prototype.renderPagination = function () {
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

			var $perSelect = $('<select/>', { class: 'jwpm-select', 'data-jwpm-per-page': '1' });
			(jwpmCustomersConfig.pagination.perPageOptions || [20, 50, 100]).forEach(function (val) {
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
		 * Add / Edit Panel
		 */
		JWPMCustomersPage.prototype.openSidePanel = function () {
			if (!this.templates.form) {
				notifyError('Customer form template نہیں ملا۔');
				return;
			}

			this.$sidePanel.empty();

			var node;
			if (this.templates.form.content) {
				node = this.templates.form.content.cloneNode(true);
			} else {
				node = document.importNode(this.templates.form, true);
			}

			this.$sidePanel.append(node);
			this.$sidePanel.prop('hidden', false);
		};

		JWPMCustomersPage.prototype.closeSidePanel = function () {
			this.$sidePanel.prop('hidden', true).empty();
		};

		JWPMCustomersPage.prototype.onAddCustomer = function () {
			var self = this;
			this.openSidePanel();

			var $panel = this.$sidePanel;
			var $form = $panel.find('[data-jwpm-customers-form]').first();
			var $title = $panel.find('[data-jwpm-customers-form-title]').first();

			$title.text('Add New Customer');
			$form[0].reset();
			$form.find('[data-jwpm-customer-input="id"]').val('');
			$form.find('[data-jwpm-customer-input="opening_balance"]').prop('disabled', false);

			// panel buttons
			$panel.off('click.jwpmCustomersPanel');
			$panel.on('click.jwpmCustomersPanel', '[data-jwpm-customers-action="close-panel"], [data-jwpm-customers-action="cancel"]', this.closeSidePanel.bind(this));
			$panel.on('click.jwpmCustomersPanel', '[data-jwpm-customers-action="save"]', function (e) {
				e.preventDefault();
				self.saveCustomer($form);
			});
		};

		JWPMCustomersPage.prototype.openCustomerForEdit = function (id) {
			var self = this;
			this.openSidePanel();

			var $panel = this.$sidePanel;
			var $form = $panel.find('[data-jwpm-customers-form]').first();
			var $title = $panel.find('[data-jwpm-customers-form-title]').first();

			$title.text('Edit Customer');

			$panel.off('click.jwpmCustomersPanel');
			$panel.on('click.jwpmCustomersPanel', '[data-jwpm-customers-action="close-panel"], [data-jwpm-customers-action="cancel"]', this.closeSidePanel.bind(this));
			$panel.on('click.jwpmCustomersPanel', '[data-jwpm-customers-action="save"]', function (e) {
				e.preventDefault();
				self.saveCustomer($form);
			});

			// Opening balance edit پر disable
			$form.find('[data-jwpm-customer-input="opening_balance"]').prop('disabled', true);

			ajaxRequest('jwpm_get_customer', {
				nonce: jwpmCustomersConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success || !response.data || !response.data.item) {
						notifyError((response && response.data && response.data.message) || 'کسٹمر نہیں ملا۔');
						return;
					}

					var item = response.data.item;

					$form.find('[data-jwpm-customer-input="id"]').val(item.id || '');
					$form.find('[data-jwpm-customer-input="name"]').val(item.name || '');
					$form.find('[data-jwpm-customer-input="phone"]').val(item.phone || '');
					$form.find('[data-jwpm-customer-input="whatsapp"]').val(item.whatsapp || '');
					$form.find('[data-jwpm-customer-input="email"]').val(item.email || '');
					$form.find('[data-jwpm-customer-input="city"]').val(item.city || '');
					$form.find('[data-jwpm-customer-input="area"]').val(item.area || '');
					$form.find('[data-jwpm-customer-input="address"]').val(item.address || '');
					$form.find('[data-jwpm-customer-input="cnic"]').val(item.cnic || '');
					$form.find('[data-jwpm-customer-input="dob"]').val(item.dob || '');
					$form.find('[data-jwpm-customer-input="gender"]').val(item.gender || '');
					$form.find('[data-jwpm-customer-input="customer_type"]').val(item.customer_type || 'walkin');
					$form.find('[data-jwpm-customer-input="status"]').val(item.status || 'active');
					$form.find('[data-jwpm-customer-input="price_group"]').val(item.price_group || '');
					$form.find('[data-jwpm-customer-input="tags"]').val(item.tags || '');
					$form.find('[data-jwpm-customer-input="credit_limit"]').val(item.credit_limit || '');
					$form.find('[data-jwpm-customer-input="opening_balance"]').val(item.opening_balance || '');
					$form.find('[data-jwpm-customer-input="notes"]').val(item.notes || '');
				})
				.fail(function () {
					notifyError('کسٹمر ڈیٹا لوڈ نہیں ہو سکا۔');
				});
		};

		JWPMCustomersPage.prototype.serializeForm = function ($form) {
			var data = {};
			$.each($form.serializeArray(), function (_, field) {
				data[field.name] = field.value;
			});
			return data;
		};

		JWPMCustomersPage.prototype.saveCustomer = function ($form) {
			var self = this;

			if (!$form || !$form.length) {
				return;
			}

			var data = this.serializeForm($form);

			if (!data.name || !data.phone) {
				notifyError('Name اور Phone لازمی فیلڈز ہیں۔');
				return;
			}

			data.nonce = jwpmCustomersConfig.mainNonce;

			this.setLoading(true);
			notifyInfo(jwpmCustomersConfig.strings.saving || 'محفوظ ہو رہا ہے…');

			ajaxRequest('jwpm_save_customer', data)
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || jwpmCustomersConfig.strings.saveError);
						return;
					}
					notifySuccess(jwpmCustomersConfig.strings.saveSuccess || 'کسٹمر محفوظ ہو گیا۔');
					self.closeSidePanel();
					self.loadCustomers();
				})
				.fail(function () {
					notifyError(jwpmCustomersConfig.strings.saveError || 'محفوظ نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.deleteCustomer = function (id) {
			var self = this;
			if (!confirmAction(jwpmCustomersConfig.strings.deleteConfirm || 'کیا آپ واقعی اس کسٹمر کو Inactive کرنا چاہتے ہیں؟')) {
				return;
			}

			this.setLoading(true);

			ajaxRequest('jwpm_delete_customer', {
				nonce: jwpmCustomersConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || 'کسٹمر کو Inactive نہیں کیا جا سکا۔');
						return;
					}
					notifySuccess(jwpmCustomersConfig.strings.deleteSuccess || 'کسٹمر کو Inactive کر دیا گیا۔');
					self.loadCustomers();
				})
				.fail(function () {
					notifyError('کسٹمر کو Inactive نہیں کیا جا سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.toggleCustomerStatus = function (id) {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_toggle_customer_status', {
				nonce: jwpmCustomersConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || 'Status تبدیل نہیں ہو سکا۔');
						return;
					}
					self.loadCustomers();
				})
				.fail(function () {
					notifyError('Status تبدیل نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Import / Export / Demo / Print
		 */
		JWPMCustomersPage.prototype.openImportModal = function () {
			var self = this;

			if (!this.templates.importModal) {
				notifyError('Import modal template نہیں ملا۔');
				return;
			}

			if (this.$importModal && this.$importModal.length) {
				this.$importModal.remove();
				this.$importModal = null;
			}

			var node;
			if (this.templates.importModal.content) {
				node = this.templates.importModal.content.cloneNode(true);
			} else {
				node = document.importNode(this.templates.importModal, true);
			}

			this.$importModal = $(node);
			$('body').append(this.$importModal);

			var $modal = this.$importModal;
			var $form = $modal.find('[data-jwpm-customers-import-form]').first();
			var $result = $modal.find('[data-jwpm-customers-import-result]').first();

			function closeModal() {
				$modal.remove();
				self.$importModal = null;
			}

			$modal.on('click', '[data-jwpm-customers-action="close-import"]', function (e) {
				e.preventDefault();
				closeModal();
			});

			$modal.on('click', '[data-jwpm-customers-action="do-import"]', function (e) {
				e.preventDefault();

				var fileInput = $form.find('input[type="file"]')[0];
				if (!fileInput || !fileInput.files || !fileInput.files.length) {
					notifyError('براہ کرم (CSV) فائل منتخب کریں۔');
					return;
				}

				var formData = new FormData();
				formData.append('action', 'jwpm_import_customers');
				formData.append('nonce', jwpmCustomersConfig.importNonce);
				formData.append('file', fileInput.files[0]);

				var skipDup = $form.find('input[name="skip_duplicates"]').is(':checked') ? 1 : 0;
				formData.append('skip_duplicates', skipDup);

				$result.empty().text(jwpmCustomersConfig.strings.loading || 'Import ہو رہا ہے…');

				$.ajax({
					url: jwpmCustomersConfig.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					dataType: 'json'
				})
					.done(function (response) {
						if (!response || !response.success) {
							notifyError((response && response.data && response.data.message) || jwpmCustomersConfig.strings.importError || 'Import کے دوران مسئلہ آیا۔');
							return;
						}

						var data = response.data || {};
						var msg =
							(jwpmCustomersConfig.strings.importSuccess || 'Import مکمل ہو گیا۔') +
							' Total: ' +
							(data.total || 0) +
							', Inserted: ' +
							(data.inserted || 0) +
							', Skipped: ' +
							(data.skipped || 0);

						$result.text(msg);
						notifySuccess(msg);
						self.loadCustomers();
					})
					.fail(function () {
						notifyError(jwpmCustomersConfig.strings.importError || 'Import کے دوران مسئلہ آیا۔');
					});
			});
		};

		JWPMCustomersPage.prototype.exportCustomers = function () {
			// Export direct download via URL + nonce
			var url =
				jwpmCustomersConfig.ajaxUrl +
				'?action=jwpm_export_customers&nonce=' +
				encodeURIComponent(jwpmCustomersConfig.exportNonce);

			window.open(url, '_blank');
		};

		JWPMCustomersPage.prototype.createDemoCustomers = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_customers_demo_create', {
				nonce: jwpmCustomersConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || 'Demo کسٹمرز نہیں بن سکے۔');
						return;
					}
					notifySuccess(jwpmCustomersConfig.strings.demoCreateSuccess || 'Demo کسٹمرز بنا دیے گئے۔');
					self.loadCustomers();
				})
				.fail(function () {
					notifyError('Demo کسٹمرز نہیں بن سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.clearDemoCustomers = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_customers_demo_clear', {
				nonce: jwpmCustomersConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || 'Demo کسٹمرز حذف نہیں ہو سکے۔');
						return;
					}
					notifySuccess(jwpmCustomersConfig.strings.demoClearSuccess || 'Demo کسٹمرز حذف ہو گئے۔');
					self.loadCustomers();
				})
				.fail(function () {
					notifyError('Demo کسٹمرز حذف نہیں ہو سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.printCustomers = function () {
			var $table = this.$layout.find('.jwpm-table-customers').first();
			if (!$table.length) {
				notifyError('پرنٹ کیلئے کوئی جدول نہیں ملا۔');
				return;
			}

			var html = '<html><head><title>Customers List</title>';
			html += '<style>body{font-family:system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;font-size:12px;color:#000;padding:16px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:4px 6px;text-align:left;} th{background:#eee;} .jwpm-status-badge{font-weight:bold;}</style>';
			html += '</head><body>';
			html += '<h2>Customers List</h2>';
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

		return JWPMCustomersPage;
	})();

	/**
	 * DOM Ready — Root element mount
	 */
	$(function () {
		var $root = $('#jwpm-customers-root').first();

		if (!$root.length) {
			if (window.console) {
				console.warn('JWPM Customers: #jwpm-customers-root نہیں ملا، شاید یہ صحیح پیج نہیں۔');
			}
			return;
		}

		try {
			new JWPMCustomersPage($root);
		} catch (e) {
			console.error('JWPM Customers init error:', e);
			notifyError('Customers Page لوڈ کرتے وقت مسئلہ آیا۔');
		}
	});

	// 🔴 یہاں پر [JWPM Customers Module] ختم ہو رہا ہے
})(jQuery);

// ✅ Syntax verified block end
/** Part 10 — JWPM Customers Page Script (UI + AJAX)
 * یہاں Customers Page کے تمام (JavaScript) behaviours، AJAX calls اور UI rendering ہیں۔
 */
(function ($) {
	'use strict';

	// 🟢 یہاں سے [JWPM Customers Module] شروع ہو رہا ہے

	/**
	 * Safe config (jwpmCustomersData) اگر (PHP) سے نہ ملا ہو تو fallback
	 */
	var jwpmCustomersConfig = window.jwpmCustomersData || {
		ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
		mainNonce: '',
		importNonce: '',
		exportNonce: '',
		demoNonce: '',
		strings: {
			loading: 'کسٹمرز لوڈ ہو رہے ہیں…',
			saving: 'ڈیٹا محفوظ ہو رہا ہے…',
			saveSuccess: 'کسٹمر کامیابی سے محفوظ ہو گیا۔',
			saveError: 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔',
			deleteConfirm: 'کیا آپ واقعی اس کسٹمر کو Inactive کرنا چاہتے ہیں؟',
			deleteSuccess: 'کسٹمر کو Inactive کر دیا گیا۔',
			demoCreateSuccess: 'Demo کسٹمرز بنا دیے گئے۔',
			demoClearSuccess: 'Demo کسٹمرز حذف ہو گئے۔',
			importSuccess: 'Import مکمل ہو گیا۔',
			importError: 'Import کے دوران مسئلہ آیا۔',
			noRecords: 'کوئی ریکارڈ نہیں ملا۔'
		},
		pagination: {
			defaultPerPage: 20,
			perPageOptions: [20, 50, 100]
		},
		capabilities: {
			canManageCustomers: true
		}
	};

	/**
	 * Soft toast / اطلاع (اگر jwpmCommon موجود ہو تو اسی کا، ورنہ simple alert / console)
	 */
	function notifySuccess(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastSuccess === 'function') {
			window.jwpmCommon.toastSuccess(message);
		} else {
			window.console && console.log('[JWPM Customers] ' + message);
		}
	}

	function notifyError(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastError === 'function') {
			window.jwpmCommon.toastError(message);
		} else {
			window.console && console.error('[JWPM Customers] ' + message);
			alert(message);
		}
	}

	function notifyInfo(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastInfo === 'function') {
			window.jwpmCommon.toastInfo(message);
		} else {
			window.console && console.log('[JWPM Customers] ' + message);
		}
	}

	function confirmAction(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.confirm === 'function') {
			return window.jwpmCommon.confirm(message);
		}
		// simple confirm
		return window.confirm(message);
	}

	/**
	 * Common helper: AJAX via (jQuery)
	 */
	function ajaxRequest(action, data, options) {
		options = options || {};

		var payload = $.extend({}, data, { action: action });

		return $.ajax({
			url: jwpmCustomersConfig.ajaxUrl,
			type: options.type || 'POST',
			data: payload,
			dataType: options.dataType || 'json',
			processData: options.processData !== false,
			contentType: options.contentType !== false ? 'application/x-www-form-urlencoded; charset=UTF-8' : false
		});
	}

	/**
	 * Main Customers Page Controller
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
				},
				loading: false
			};

			this.$layout = null;
			this.$tableBody = null;
			this.$pagination = null;
			this.$sidePanel = null;
			this.$importModal = null;

			this.templates = {
				layout: document.getElementById('jwpm-customers-layout-template'),
				row: document.getElementById('jwpm-customers-row-template'),
				form: document.getElementById('jwpm-customers-form-template'),
				importModal: document.getElementById('jwpm-customers-import-template')
			};

			this.init();
		}

		JWPMCustomersPage.prototype.init = function () {
			if (!this.templates.layout) {
				notifyError('Customers layout template نہیں ملا۔');
				return;
			}

			this.renderLayout();
			this.cacheElements();
			this.bindEvents();

			this.loadCustomers();
		};

		JWPMCustomersPage.prototype.renderLayout = function () {
			var tmpl = this.templates.layout.content
				? this.templates.layout.content.cloneNode(true)
				: document.importNode(this.templates.layout, true);

			this.$root.empty().append(tmpl);
		};

		JWPMCustomersPage.prototype.cacheElements = function () {
			this.$layout = this.$root.find('.jwpm-page-customers').first();
			this.$tableBody = this.$layout.find('[data-jwpm-customers-table-body]').first();
			this.$pagination = this.$layout.find('[data-jwpm-customers-pagination]').first();
			this.$sidePanel = this.$layout.find('[data-jwpm-customers-side-panel]').first();
		};

		JWPMCustomersPage.prototype.bindEvents = function () {
			var self = this;

			// Filters
			this.$layout.on('input', '[data-jwpm-customers-filter="search"]', function () {
				self.state.filters.search = $(this).val();
				self.state.page = 1;
				self.loadCustomers();
			});

			this.$layout.on('change', '[data-jwpm-customers-filter="city"]', function () {
				self.state.filters.city = $(this).val();
				self.state.page = 1;
				self.loadCustomers();
			});

			this.$layout.on('change', '[data-jwpm-customers-filter="type"]', function () {
				self.state.filters.customer_type = $(this).val();
				self.state.page = 1;
				self.loadCustomers();
			});

			this.$layout.on('change', '[data-jwpm-customers-filter="status"]', function () {
				self.state.filters.status = $(this).val();
				self.state.page = 1;
				self.loadCustomers();
			});

			// Actions
			this.$layout.on('click', '[data-jwpm-customers-action="add"]', this.onAddCustomer.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="import"]', this.openImportModal.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="export"]', this.exportCustomers.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="print"]', this.printCustomers.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="demo-create"]', this.createDemoCustomers.bind(this));
			this.$layout.on('click', '[data-jwpm-customers-action="demo-clear"]', this.clearDemoCustomers.bind(this));

			// Table actions
			this.$layout.on('click', '[data-jwpm-customers-action="view"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-customer-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.openCustomerForEdit(id);
				}
			});

			this.$layout.on('click', '[data-jwpm-customers-action="quick-sale"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-customer-row]');
				var id = parseInt($row.data('id'), 10);
				if (id && window.jwpmCommon && typeof window.jwpmCommon.quickSaleWithCustomer === 'function') {
					window.jwpmCommon.quickSaleWithCustomer(id);
				} else {
					notifyInfo('Quick Sale فیچر بعد میں POS کے ساتھ لنک کیا جائے گا۔');
				}
			});

			this.$layout.on('click', '[data-jwpm-customers-action="delete"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-customer-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.deleteCustomer(id);
				}
			});

			// Status toggle (اگر badge پر click ہو)
			this.$layout.on('click', '[data-jwpm-customer-field="status_badge"]', function () {
				var $row = $(this).closest('[data-jwpm-customer-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.toggleCustomerStatus(id);
				}
			});

			// Pagination clicks
			this.$pagination.on('click', '[data-jwpm-page]', function () {
				var page = parseInt($(this).attr('data-jwpm-page'), 10);
				if (!isNaN(page) && page >= 1 && page <= self.state.totalPages && page !== self.state.page) {
					self.state.page = page;
					self.loadCustomers();
				}
			});

			this.$pagination.on('change', '[data-jwpm-per-page]', function () {
				var per = parseInt($(this).val(), 10);
				if (!isNaN(per) && per > 0) {
					self.state.perPage = per;
					self.state.page = 1;
					self.loadCustomers();
				}
			});
		};

		JWPMCustomersPage.prototype.setLoading = function (loading) {
			this.state.loading = loading;
			if (loading) {
				this.$root.addClass('jwpm-is-loading');
			} else {
				this.$root.removeClass('jwpm-is-loading');
			}
		};

		JWPMCustomersPage.prototype.loadCustomers = function () {
			var self = this;

			this.setLoading(true);
			this.$tableBody.empty().append(
				$('<tr/>', { class: 'jwpm-loading-row' }).append(
					$('<td/>', {
						colspan: 10,
						text: jwpmCustomersConfig.strings.loading || 'لوڈ ہو رہا ہے…'
					})
				)
			);

			ajaxRequest('jwpm_get_customers', {
				nonce: jwpmCustomersConfig.mainNonce,
				search: this.state.filters.search,
				city: this.state.filters.city,
				customer_type: this.state.filters.customer_type,
				status: this.state.filters.status,
				page: this.state.page,
				per_page: this.state.perPage
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || jwpmCustomersConfig.strings.saveError);
						return;
					}

					var data = response.data;
					self.state.items = data.items || [];
					self.state.total = data.pagination ? parseInt(data.pagination.total, 10) || 0 : 0;
					self.state.page = data.pagination ? parseInt(data.pagination.page, 10) || 1 : 1;
					self.state.perPage = data.pagination ? parseInt(data.pagination.per_page, 10) || self.state.perPage : self.state.perPage;
					self.state.totalPages = data.pagination ? parseInt(data.pagination.total_page, 10) || 1 : 1;

					self.renderTable();
					self.renderStats();
					self.renderPagination();
				})
				.fail(function () {
					notifyError(jwpmCustomersConfig.strings.saveError || 'ڈیٹا لوڈ نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.renderStats = function () {
			var total = this.state.total || 0;
			var activeCount = 0;

			this.state.items.forEach(function (item) {
				if (item.status === 'active') {
					activeCount++;
				}
			});

			this.$layout
				.find('[data-jwpm-customers-stat="total"] .jwpm-stat-value')
				.text(total);
			this.$layout
				.find('[data-jwpm-customers-stat="active"] .jwpm-stat-value')
				.text(activeCount);
		};

		JWPMCustomersPage.prototype.renderTable = function () {
			var self = this;
			this.$tableBody.empty();

			if (!this.state.items || !this.state.items.length) {
				this.$tableBody.append(
					$('<tr/>', { class: 'jwpm-empty-row' }).append(
						$('<td/>', {
							colspan: 10,
							text: jwpmCustomersConfig.strings.noRecords || 'کوئی ریکارڈ نہیں ملا۔'
						})
					)
				);
				return;
			}

			if (!this.templates.row) {
				notifyError('Customers row template نہیں ملا۔');
				return;
			}

			this.state.items.forEach(function (item) {
				var tr;
				if (self.templates.row.content) {
					tr = self.templates.row.content.cloneNode(true);
					tr = $(tr).children('tr').first();
				} else {
					tr = $(document.importNode(self.templates.row, true));
				}

				tr.attr('data-jwpm-customer-row', '');
				tr.attr('data-id', item.id);

				tr.find('[data-jwpm-customer-field="customer_code"]').text(item.customer_code || '');
				tr.find('[data-jwpm-customer-field="name"]').text(item.name || '');
				tr.find('[data-jwpm-customer-field="phone"]').text(item.phone || '');
				tr.find('[data-jwpm-customer-field="city"]').text(item.city || '');
				tr.find('[data-jwpm-customer-field="customer_type"]').text(item.customer_type || '');

				tr.find('[data-jwpm-customer-field="credit_limit"]').text(item.credit_limit || '0.000');
				tr.find('[data-jwpm-customer-field="current_balance"]').text(item.current_balance || '0.000');

				var lastPurchase = item.last_purchase || '';
				tr.find('[data-jwpm-customer-field="last_purchase"]').text(lastPurchase);

				var $statusCell = tr.find('[data-jwpm-customer-field="status_badge"]');
				$statusCell
					.text(item.status === 'inactive' ? 'Inactive' : 'Active')
					.attr('data-status', item.status || 'active')
					.addClass('jwpm-status-badge');

				self.$tableBody.append(tr);
			});
		};

		JWPMCustomersPage.prototype.renderPagination = function () {
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

			var $perSelect = $('<select/>', { class: 'jwpm-select', 'data-jwpm-per-page': '1' });
			(jwpmCustomersConfig.pagination.perPageOptions || [20, 50, 100]).forEach(function (val) {
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
		 * Add / Edit Panel
		 */
		JWPMCustomersPage.prototype.openSidePanel = function () {
			if (!this.templates.form) {
				notifyError('Customer form template نہیں ملا۔');
				return;
			}

			this.$sidePanel.empty();

			var node;
			if (this.templates.form.content) {
				node = this.templates.form.content.cloneNode(true);
			} else {
				node = document.importNode(this.templates.form, true);
			}

			this.$sidePanel.append(node);
			this.$sidePanel.prop('hidden', false);
		};

		JWPMCustomersPage.prototype.closeSidePanel = function () {
			this.$sidePanel.prop('hidden', true).empty();
		};

		JWPMCustomersPage.prototype.onAddCustomer = function () {
			var self = this;
			this.openSidePanel();

			var $panel = this.$sidePanel;
			var $form = $panel.find('[data-jwpm-customers-form]').first();
			var $title = $panel.find('[data-jwpm-customers-form-title]').first();

			$title.text('Add New Customer');
			$form[0].reset();
			$form.find('[data-jwpm-customer-input="id"]').val('');
			$form.find('[data-jwpm-customer-input="opening_balance"]').prop('disabled', false);

			// panel buttons
			$panel.off('click.jwpmCustomersPanel');
			$panel.on('click.jwpmCustomersPanel', '[data-jwpm-customers-action="close-panel"], [data-jwpm-customers-action="cancel"]', this.closeSidePanel.bind(this));
			$panel.on('click.jwpmCustomersPanel', '[data-jwpm-customers-action="save"]', function (e) {
				e.preventDefault();
				self.saveCustomer($form);
			});
		};

		JWPMCustomersPage.prototype.openCustomerForEdit = function (id) {
			var self = this;
			this.openSidePanel();

			var $panel = this.$sidePanel;
			var $form = $panel.find('[data-jwpm-customers-form]').first();
			var $title = $panel.find('[data-jwpm-customers-form-title]').first();

			$title.text('Edit Customer');

			$panel.off('click.jwpmCustomersPanel');
			$panel.on('click.jwpmCustomersPanel', '[data-jwpm-customers-action="close-panel"], [data-jwpm-customers-action="cancel"]', this.closeSidePanel.bind(this));
			$panel.on('click.jwpmCustomersPanel', '[data-jwpm-customers-action="save"]', function (e) {
				e.preventDefault();
				self.saveCustomer($form);
			});

			// Opening balance edit پر disable
			$form.find('[data-jwpm-customer-input="opening_balance"]').prop('disabled', true);

			ajaxRequest('jwpm_get_customer', {
				nonce: jwpmCustomersConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success || !response.data || !response.data.item) {
						notifyError((response && response.data && response.data.message) || 'کسٹمر نہیں ملا۔');
						return;
					}

					var item = response.data.item;

					$form.find('[data-jwpm-customer-input="id"]').val(item.id || '');
					$form.find('[data-jwpm-customer-input="name"]').val(item.name || '');
					$form.find('[data-jwpm-customer-input="phone"]').val(item.phone || '');
					$form.find('[data-jwpm-customer-input="whatsapp"]').val(item.whatsapp || '');
					$form.find('[data-jwpm-customer-input="email"]').val(item.email || '');
					$form.find('[data-jwpm-customer-input="city"]').val(item.city || '');
					$form.find('[data-jwpm-customer-input="area"]').val(item.area || '');
					$form.find('[data-jwpm-customer-input="address"]').val(item.address || '');
					$form.find('[data-jwpm-customer-input="cnic"]').val(item.cnic || '');
					$form.find('[data-jwpm-customer-input="dob"]').val(item.dob || '');
					$form.find('[data-jwpm-customer-input="gender"]').val(item.gender || '');
					$form.find('[data-jwpm-customer-input="customer_type"]').val(item.customer_type || 'walkin');
					$form.find('[data-jwpm-customer-input="status"]').val(item.status || 'active');
					$form.find('[data-jwpm-customer-input="price_group"]').val(item.price_group || '');
					$form.find('[data-jwpm-customer-input="tags"]').val(item.tags || '');
					$form.find('[data-jwpm-customer-input="credit_limit"]').val(item.credit_limit || '');
					$form.find('[data-jwpm-customer-input="opening_balance"]').val(item.opening_balance || '');
					$form.find('[data-jwpm-customer-input="notes"]').val(item.notes || '');
				})
				.fail(function () {
					notifyError('کسٹمر ڈیٹا لوڈ نہیں ہو سکا۔');
				});
		};

		JWPMCustomersPage.prototype.serializeForm = function ($form) {
			var data = {};
			$.each($form.serializeArray(), function (_, field) {
				data[field.name] = field.value;
			});
			return data;
		};

		JWPMCustomersPage.prototype.saveCustomer = function ($form) {
			var self = this;

			if (!$form || !$form.length) {
				return;
			}

			var data = this.serializeForm($form);

			if (!data.name || !data.phone) {
				notifyError('Name اور Phone لازمی فیلڈز ہیں۔');
				return;
			}

			data.nonce = jwpmCustomersConfig.mainNonce;

			this.setLoading(true);
			notifyInfo(jwpmCustomersConfig.strings.saving || 'محفوظ ہو رہا ہے…');

			ajaxRequest('jwpm_save_customer', data)
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || jwpmCustomersConfig.strings.saveError);
						return;
					}
					notifySuccess(jwpmCustomersConfig.strings.saveSuccess || 'کسٹمر محفوظ ہو گیا۔');
					self.closeSidePanel();
					self.loadCustomers();
				})
				.fail(function () {
					notifyError(jwpmCustomersConfig.strings.saveError || 'محفوظ نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.deleteCustomer = function (id) {
			var self = this;
			if (!confirmAction(jwpmCustomersConfig.strings.deleteConfirm || 'کیا آپ واقعی اس کسٹمر کو Inactive کرنا چاہتے ہیں؟')) {
				return;
			}

			this.setLoading(true);

			ajaxRequest('jwpm_delete_customer', {
				nonce: jwpmCustomersConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || 'کسٹمر کو Inactive نہیں کیا جا سکا۔');
						return;
					}
					notifySuccess(jwpmCustomersConfig.strings.deleteSuccess || 'کسٹمر کو Inactive کر دیا گیا۔');
					self.loadCustomers();
				})
				.fail(function () {
					notifyError('کسٹمر کو Inactive نہیں کیا جا سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.toggleCustomerStatus = function (id) {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_toggle_customer_status', {
				nonce: jwpmCustomersConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || 'Status تبدیل نہیں ہو سکا۔');
						return;
					}
					self.loadCustomers();
				})
				.fail(function () {
					notifyError('Status تبدیل نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Import / Export / Demo / Print
		 */
		JWPMCustomersPage.prototype.openImportModal = function () {
			var self = this;

			if (!this.templates.importModal) {
				notifyError('Import modal template نہیں ملا۔');
				return;
			}

			if (this.$importModal && this.$importModal.length) {
				this.$importModal.remove();
				this.$importModal = null;
			}

			var node;
			if (this.templates.importModal.content) {
				node = this.templates.importModal.content.cloneNode(true);
			} else {
				node = document.importNode(this.templates.importModal, true);
			}

			this.$importModal = $(node);
			$('body').append(this.$importModal);

			var $modal = this.$importModal;
			var $form = $modal.find('[data-jwpm-customers-import-form]').first();
			var $result = $modal.find('[data-jwpm-customers-import-result]').first();

			function closeModal() {
				$modal.remove();
				self.$importModal = null;
			}

			$modal.on('click', '[data-jwpm-customers-action="close-import"]', function (e) {
				e.preventDefault();
				closeModal();
			});

			$modal.on('click', '[data-jwpm-customers-action="do-import"]', function (e) {
				e.preventDefault();

				var fileInput = $form.find('input[type="file"]')[0];
				if (!fileInput || !fileInput.files || !fileInput.files.length) {
					notifyError('براہ کرم (CSV) فائل منتخب کریں۔');
					return;
				}

				var formData = new FormData();
				formData.append('action', 'jwpm_import_customers');
				formData.append('nonce', jwpmCustomersConfig.importNonce);
				formData.append('file', fileInput.files[0]);

				var skipDup = $form.find('input[name="skip_duplicates"]').is(':checked') ? 1 : 0;
				formData.append('skip_duplicates', skipDup);

				$result.empty().text(jwpmCustomersConfig.strings.loading || 'Import ہو رہا ہے…');

				$.ajax({
					url: jwpmCustomersConfig.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					dataType: 'json'
				})
					.done(function (response) {
						if (!response || !response.success) {
							notifyError((response && response.data && response.data.message) || jwpmCustomersConfig.strings.importError || 'Import کے دوران مسئلہ آیا۔');
							return;
						}

						var data = response.data || {};
						var msg =
							(jwpmCustomersConfig.strings.importSuccess || 'Import مکمل ہو گیا۔') +
							' Total: ' +
							(data.total || 0) +
							', Inserted: ' +
							(data.inserted || 0) +
							', Skipped: ' +
							(data.skipped || 0);

						$result.text(msg);
						notifySuccess(msg);
						self.loadCustomers();
					})
					.fail(function () {
						notifyError(jwpmCustomersConfig.strings.importError || 'Import کے دوران مسئلہ آیا۔');
					});
			});
		};

		JWPMCustomersPage.prototype.exportCustomers = function () {
			// Export direct download via URL + nonce
			var url =
				jwpmCustomersConfig.ajaxUrl +
				'?action=jwpm_export_customers&nonce=' +
				encodeURIComponent(jwpmCustomersConfig.exportNonce);

			window.open(url, '_blank');
		};

		JWPMCustomersPage.prototype.createDemoCustomers = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_customers_demo_create', {
				nonce: jwpmCustomersConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || 'Demo کسٹمرز نہیں بن سکے۔');
						return;
					}
					notifySuccess(jwpmCustomersConfig.strings.demoCreateSuccess || 'Demo کسٹمرز بنا دیے گئے۔');
					self.loadCustomers();
				})
				.fail(function () {
					notifyError('Demo کسٹمرز نہیں بن سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.clearDemoCustomers = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_customers_demo_clear', {
				nonce: jwpmCustomersConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError((response && response.data && response.data.message) || 'Demo کسٹمرز حذف نہیں ہو سکے۔');
						return;
					}
					notifySuccess(jwpmCustomersConfig.strings.demoClearSuccess || 'Demo کسٹمرز حذف ہو گئے۔');
					self.loadCustomers();
				})
				.fail(function () {
					notifyError('Demo کسٹمرز حذف نہیں ہو سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomersPage.prototype.printCustomers = function () {
			var $table = this.$layout.find('.jwpm-table-customers').first();
			if (!$table.length) {
				notifyError('پرنٹ کیلئے کوئی جدول نہیں ملا۔');
				return;
			}

			var html = '<html><head><title>Customers List</title>';
			html += '<style>body{font-family:system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;font-size:12px;color:#000;padding:16px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:4px 6px;text-align:left;} th{background:#eee;} .jwpm-status-badge{font-weight:bold;}</style>';
			html += '</head><body>';
			html += '<h2>Customers List</h2>';
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

		return JWPMCustomersPage;
	})();

	/**
	 * DOM Ready — Root element mount
	 */
	$(function () {
		var $root = $('#jwpm-customers-root').first();

		if (!$root.length) {
			if (window.console) {
				console.warn('JWPM Customers: #jwpm-customers-root نہیں ملا، شاید یہ صحیح پیج نہیں۔');
			}
			return;
		}

		try {
			new JWPMCustomersPage($root);
		} catch (e) {
			console.error('JWPM Customers init error:', e);
			notifyError('Customers Page لوڈ کرتے وقت مسئلہ آیا۔');
		}
	});

	// 🔴 یہاں پر [JWPM Customers Module] ختم ہو رہا ہے
})(jQuery);

// ✅ Syntax verified block end

