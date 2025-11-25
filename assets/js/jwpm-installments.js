/** Part 11 — JWPM Installments Page Script (UI + AJAX)
 * یہاں Installments / Credit Sales پیج کا پورا (JavaScript) behaviour ہے۔
 */
(function ($) {
	'use strict';

	// 🟢 یہاں سے [JWPM Installments Module] شروع ہو رہا ہے

	/**
	 * Safe config (jwpmInstallmentsData) اگر (PHP) سے نہ ملا ہو تو fallback
	 */
	var jwpmInstallmentsConfig = window.jwpmInstallmentsData || {
		ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
		mainNonce: '',
		importNonce: '',
		exportNonce: '',
		demoNonce: '',
		strings: {
			loading: 'Installments لوڈ ہو رہے ہیں…',
			saving: 'ڈیٹا محفوظ ہو رہا ہے…',
			saveSuccess: 'Installment Plan محفوظ ہو گیا۔',
			saveError: 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔',
			deleteConfirm: 'کیا آپ واقعی اس قسطی معاہدے کو Cancel کرنا چاہتے ہیں؟',
			deleteSuccess: 'Contract کی Status اپڈیٹ ہو گئی۔',
			paymentSave: 'Payment محفوظ ہو گئی۔',
			paymentError: 'Payment محفوظ نہیں ہو سکی۔',
			demoCreateSuccess: 'Demo Installments بنا دیے گئے۔',
			demoClearSuccess: 'Demo Installments حذف ہو گئے۔',
			importSuccess: 'Import مکمل ہو گیا۔',
			importError: 'Import کے دوران مسئلہ آیا۔',
			noRecords: 'کوئی ریکارڈ نہیں ملا۔'
		},
		pagination: {
			defaultPerPage: 20,
			perPageOptions: [20, 50, 100]
		}
	};

	/**
	 * Soft toast / اطلاع
	 */
	function notifySuccess(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastSuccess === 'function') {
			window.jwpmCommon.toastSuccess(message);
		} else if (window.console) {
			console.log('[JWPM Installments] ' + message);
		}
	}

	function notifyError(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastError === 'function') {
			window.jwpmCommon.toastError(message);
		} else {
			if (window.console) {
				console.error('[JWPM Installments] ' + message);
			}
			alert(message);
		}
	}

	function notifyInfo(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastInfo === 'function') {
			window.jwpmCommon.toastInfo(message);
		} else if (window.console) {
			console.log('[JWPM Installments] ' + message);
		}
	}

	function confirmAction(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.confirm === 'function') {
			return window.jwpmCommon.confirm(message);
		}
		return window.confirm(message);
	}

	/**
	 * Common helper: AJAX via (jQuery)
	 */
	function ajaxRequest(action, data, options) {
		options = options || {};
		var payload = $.extend({}, data, { action: action });

		return $.ajax({
			url: jwpmInstallmentsConfig.ajaxUrl,
			type: options.type || 'POST',
			data: payload,
			dataType: options.dataType || 'json',
			processData: options.processData !== false,
			contentType: options.contentType !== false ? 'application/x-www-form-urlencoded; charset=UTF-8' : false
		});
	}

	/**
	 * Helpers
	 */
	function parseNumber(value) {
		if (value === null || typeof value === 'undefined') {
			return 0;
		}
		var v = parseFloat(value);
		return isNaN(v) ? 0 : v;
	}

	function formatAmount(value) {
		var n = parseNumber(value);
		return n.toFixed(3);
	}

	/**
	 * Main Page Controller
	 */
	var JWPMInstallmentsPage = (function () {
		function JWPMInstallmentsPage($root) {
			this.$root = $root;

			this.state = {
				items: [],
				page: 1,
				perPage: jwpmInstallmentsConfig.pagination.defaultPerPage || 20,
				total: 0,
				totalPages: 1,
				filters: {
					search: '',
					status: '',
					date_mode: 'sale',
					date_from: '',
					date_to: ''
				},
				loading: false,
				currentContractId: null
			};

			this.$layout = null;
			this.$tableBody = null;
			this.$pagination = null;
			this.$sidePanel = null;
			this.$importModal = null;
			this.$paymentModal = null;

			this.templates = {
				layout: document.getElementById('jwpm-installments-layout-template'),
				row: document.getElementById('jwpm-installments-row-template'),
				panel: document.getElementById('jwpm-installments-panel-template'),
				paymentModal: document.getElementById('jwpm-installments-payment-modal-template'),
				importModal: document.getElementById('jwpm-installments-import-template')
			};

			this.init();
		}

		JWPMInstallmentsPage.prototype.init = function () {
			if (!this.templates.layout) {
				notifyError('Installments layout template نہیں ملا۔');
				return;
			}

			this.renderLayout();
			this.cacheElements();
			this.bindEvents();
			this.loadInstallments();
		};

		JWPMInstallmentsPage.prototype.renderLayout = function () {
			var tmpl = this.templates.layout.content
				? this.templates.layout.content.cloneNode(true)
				: document.importNode(this.templates.layout, true);

			this.$root.empty().append(tmpl);
		};

		JWPMInstallmentsPage.prototype.cacheElements = function () {
			this.$layout = this.$root.find('.jwpm-page-installments').first();
			this.$tableBody = this.$layout.find('[data-jwpm-installments-table-body]').first();
			this.$pagination = this.$layout.find('[data-jwpm-installments-pagination]').first();
			this.$sidePanel = this.$layout.find('[data-jwpm-installments-side-panel]').first();
		};

		JWPMInstallmentsPage.prototype.bindEvents = function () {
			var self = this;

			// Filters
			this.$layout.on('input', '[data-jwpm-installments-filter="search"]', function () {
				self.state.filters.search = $(this).val();
				self.state.page = 1;
				self.loadInstallments();
			});

			this.$layout.on('change', '[data-jwpm-installments-filter="status"]', function () {
				self.state.filters.status = $(this).val();
				self.state.page = 1;
				self.loadInstallments();
			});

			this.$layout.on('change', '[data-jwpm-installments-filter="date_mode"]', function () {
				self.state.filters.date_mode = $(this).val() || 'sale';
				self.state.page = 1;
				self.loadInstallments();
			});

			this.$layout.on('change', '[data-jwpm-installments-filter="date_from"]', function () {
				self.state.filters.date_from = $(this).val();
				self.state.page = 1;
				self.loadInstallments();
			});

			this.$layout.on('change', '[data-jwpm-installments-filter="date_to"]', function () {
				self.state.filters.date_to = $(this).val();
				self.state.page = 1;
				self.loadInstallments();
			});

			// Toolbar actions
			this.$layout.on('click', '[data-jwpm-installments-action="add"]', function () {
				self.openContractPanel(null);
			});

			this.$layout.on('click', '[data-jwpm-installments-action="receive"]', function () {
				if (!self.state.currentContractId) {
					notifyInfo('کوئی Contract منتخب نہیں، براہ کرم Table سے View/Receive استعمال کریں۔');
					return;
				}
				self.openPaymentModal(self.state.currentContractId);
			});

			this.$layout.on('click', '[data-jwpm-installments-action="import"]', function () {
				self.openImportModal();
			});

			this.$layout.on('click', '[data-jwpm-installments-action="export"]', function () {
				self.exportInstallments();
			});

			this.$layout.on('click', '[data-jwpm-installments-action="print"]', function () {
				self.printInstallments();
			});

			this.$layout.on('click', '[data-jwpm-installments-action="demo-create"]', function () {
				self.createDemoInstallments();
			});

			this.$layout.on('click', '[data-jwpm-installments-action="demo-clear"]', function () {
				self.clearDemoInstallments();
			});

			// Table row actions
			this.$layout.on('click', '[data-jwpm-installments-action="view"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-installment-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.openContractPanel(id);
				}
			});

			this.$layout.on('click', '[data-jwpm-installments-action="quick-receive"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-installment-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.state.currentContractId = id;
					self.openPaymentModal(id);
				}
			});

			this.$layout.on('click', '[data-jwpm-installments-action="cancel-contract"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-installment-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.updateContractStatus(id, 'cancelled');
				}
			});

			// Status badge click (toggle e.g. active/completed)
			this.$layout.on('click', '[data-jwpm-installment-field="status_badge"]', function () {
				var $row = $(this).closest('[data-jwpm-installment-row]');
				var id = parseInt($row.data('id'), 10);
				if (!id) {
					return;
				}
				var current = $(this).attr('data-status') || 'active';
				var next = current === 'active' ? 'completed' : 'active';
				self.updateContractStatus(id, next);
			});

			// Pagination
			this.$pagination.on('click', '[data-jwpm-page]', function () {
				var page = parseInt($(this).attr('data-jwpm-page'), 10);
				if (!isNaN(page) && page >= 1 && page <= self.state.totalPages && page !== self.state.page) {
					self.state.page = page;
					self.loadInstallments();
				}
			});

			this.$pagination.on('change', '[data-jwpm-per-page]', function () {
				var per = parseInt($(this).val(), 10);
				if (!isNaN(per) && per > 0) {
					self.state.perPage = per;
					self.state.page = 1;
					self.loadInstallments();
				}
			});
		};

		JWPMInstallmentsPage.prototype.setLoading = function (loading) {
			this.state.loading = loading;
			if (loading) {
				this.$root.addClass('jwpm-is-loading');
			} else {
				this.$root.removeClass('jwpm-is-loading');
			}
		};

		/**
		 * List loading + rendering
		 */
		JWPMInstallmentsPage.prototype.loadInstallments = function () {
			var self = this;

			this.setLoading(true);

			this.$tableBody.empty().append(
				$('<tr/>', { class: 'jwpm-loading-row' }).append(
					$('<td/>', {
						colspan: 11,
						text: jwpmInstallmentsConfig.strings.loading || 'لوڈ ہو رہا ہے…'
					})
				)
			);

			ajaxRequest('jwpm_get_installments', {
				nonce: jwpmInstallmentsConfig.mainNonce,
				search: this.state.filters.search,
				status: this.state.filters.status,
				date_mode: this.state.filters.date_mode,
				date_from: this.state.filters.date_from,
				date_to: this.state.filters.date_to,
				page: this.state.page,
				per_page: this.state.perPage
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								jwpmInstallmentsConfig.strings.saveError
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
					notifyError(jwpmInstallmentsConfig.strings.saveError || 'ڈیٹا لوڈ نہیں ہو سکا۔');
				})
				.always(
					function () {
						self.setLoading(false);
					}
				);
		};

		JWPMInstallmentsPage.prototype.renderStats = function () {
			var activeContracts = 0;
			var totalOutstanding = 0;
			var overdueInstallments = 0; // فی الحال 0، بعد میں schedule سے نکالیں گے

			this.state.items.forEach(function (item) {
				if (item.status === 'active') {
					activeContracts++;
				}
				totalOutstanding += parseNumber(item.current_outstanding);
			});

			this.$layout
				.find('[data-jwpm-installments-stat="active_contracts"] .jwpm-stat-value')
				.text(activeContracts);
			this.$layout
				.find('[data-jwpm-installments-stat="total_outstanding"] .jwpm-stat-value')
				.text(formatAmount(totalOutstanding));
			this.$layout
				.find('[data-jwpm-installments-stat="overdue_installments"] .jwpm-stat-value')
				.text(overdueInstallments);
		};

		JWPMInstallmentsPage.prototype.renderTable = function () {
			var self = this;
			this.$tableBody.empty();

			if (!this.state.items || !this.state.items.length) {
				this.$tableBody.append(
					$('<tr/>', { class: 'jwpm-empty-row' }).append(
						$('<td/>', {
							colspan: 11,
							text: jwpmInstallmentsConfig.strings.noRecords || 'کوئی ریکارڈ نہیں ملا۔'
						})
					)
				);
				return;
			}

			if (!this.templates.row) {
				notifyError('Installments row template نہیں ملا۔');
				return;
			}

			this.state.items.forEach(function (item) {
				var $tr;

				if (self.templates.row.content) {
					var node = self.templates.row.content.cloneNode(true);
					$tr = $(node).children('tr').first();
				} else {
					$tr = $(document.importNode(self.templates.row, true));
				}

				$tr.attr('data-jwpm-installment-row', '');
				$tr.attr('data-id', item.id);

				$tr.find('[data-jwpm-installment-field="contract_code"]').text(item.contract_code || '');
				$tr.find('[data-jwpm-installment-field="customer_name"]').text(item.customer_name || '');
				$tr.find('[data-jwpm-installment-field="customer_phone"]').text(item.customer_phone || '');
				$tr.find('[data-jwpm-installment-field="total_amount"]').text(formatAmount(item.total_amount));
				$tr.find('[data-jwpm-installment-field="advance_amount"]').text(formatAmount(item.advance_amount));
				$tr.find('[data-jwpm-installment-field="net_amount"]').text(formatAmount(item.net_amount));
				$tr
					.find('[data-jwpm-installment-field="installment_count"]')
					.text(item.installment_count || 0);
				$tr.find('[data-jwpm-installment-field="next_due_date"]').text(item.start_date || '');
				$tr
					.find('[data-jwpm-installment-field="outstanding"]')
					.text(formatAmount(item.current_outstanding));

				var status = item.status || 'active';
				var $badge = $tr.find('[data-jwpm-installment-field="status_badge"]');
				$badge
					.attr('data-status', status)
					.addClass('jwpm-status-badge')
					.text(
						status === 'completed'
							? 'Completed'
							: status === 'defaulted'
							? 'Defaulted'
							: status === 'cancelled'
							? 'Cancelled'
							: 'Active'
					);

				self.$tableBody.append($tr);
			});
		};

		JWPMInstallmentsPage.prototype.renderPagination = function () {
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

			(jwpmInstallmentsConfig.pagination.perPageOptions || [20, 50, 100]).forEach(function (val) {
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
		 * Side Panel (Overview / Schedule / Payments)
		 */
		JWPMInstallmentsPage.prototype.openContractPanel = function (id) {
			var self = this;

			if (!this.templates.panel) {
				notifyError('Installments panel template نہیں ملا۔');
				return;
			}

			this.$sidePanel.empty();

			var node;
			if (this.templates.panel.content) {
				node = this.templates.panel.content.cloneNode(true);
			} else {
				node = document.importNode(this.templates.panel, true);
			}

			this.$sidePanel.append(node);
			this.$sidePanel.prop('hidden', false);

			var $panel = this.$sidePanel;
			var $form = $panel.find('[data-jwpm-installments-form]').first();
			var $title = $panel.find('[data-jwpm-installments-panel-title]').first();
			var $statusBadge = $panel.find('[data-jwpm-installments-contract-status]').first();

			// Tab switching
			$panel.on('click', '.jwpm-tab', function () {
				var tab = $(this).attr('data-jwpm-installments-tab');
				if (!tab) return;

				$panel.find('.jwpm-tab').removeClass('is-active');
				$(this).addClass('is-active');

				$panel.find('.jwpm-tab-panel').removeClass('is-active');
				$panel
					.find('[data-jwpm-installments-tab-panel="' + tab + '"]')
					.addClass('is-active');
			});

			// Close buttons
			$panel.on(
				'click',
				'[data-jwpm-installments-action="close-panel"]',
				this.closeSidePanel.bind(this)
			);

			// Save contract
			$panel.on('click', '[data-jwpm-installments-action="save-contract"]', function (e) {
				e.preventDefault();
				self.saveContract($form);
			});

			// Overview auto net amount calc
			$panel.on(
				'input',
				'[data-jwpm-installments-input="total_amount"], [data-jwpm-installments-input="advance_amount"]',
				function () {
					var total = parseNumber(
						$form.find('[data-jwpm-installments-input="total_amount"]').val()
					);
					var adv = parseNumber(
						$form.find('[data-jwpm-installments-input="advance_amount"]').val()
					);
					var net = total - adv;
					if (net < 0) net = 0;
					$form
						.find('[data-jwpm-installments-input="net_amount"]')
						.val(net.toFixed(3));
				}
			);

			// Schedule tab simple actions
			$panel.on('click', '[data-jwpm-installments-action="recalc-even"]', function () {
				notifyInfo('Recalculate Evenly فیچر بعد میں تفصیل سے implement ہو گا (فی الحال صرف display)۔');
			});

			$panel.on('click', '[data-jwpm-installments-action="refresh-schedule"]', function () {
				if (self.state.currentContractId) {
					self.loadSchedule(self.state.currentContractId);
				}
			});

			// Payments tab: Add Payment button
			$panel.on('click', '[data-jwpm-installments-action="add-payment"]', function () {
				if (!self.state.currentContractId) {
					notifyInfo('کوئی Contract منتخب نہیں، پہلے Table سے contract کھولیں۔');
					return;
				}
				self.openPaymentModal(self.state.currentContractId);
			});

			if (!id) {
				// New contract
				this.state.currentContractId = null;
				$title.text('New Installment Plan');
				$statusBadge.text('Active').attr('data-status', 'active').addClass('jwpm-status-badge');
				$form[0].reset();
				$form.find('[data-jwpm-installments-input="id"]').val('');
				$form.find('[data-jwpm-installments-input="auto_generate_schedule"]').prop('checked', true);
			} else {
				this.state.currentContractId = id;
				this.loadContractIntoPanel(id, $panel, $form, $title, $statusBadge);
			}
		};

		JWPMInstallmentsPage.prototype.closeSidePanel = function () {
			this.$sidePanel.prop('hidden', true).empty();
		};

		JWPMInstallmentsPage.prototype.loadContractIntoPanel = function (
			id,
			$panel,
			$form,
			$title,
			$statusBadge
		) {
			var self = this;

			$title.text('Loading…');
			$form[0].reset();

			ajaxRequest('jwpm_get_installment', {
				nonce: jwpmInstallmentsConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success || !response.data || !response.data.item) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Contract نہیں ملا۔'
						);
						self.closeSidePanel();
						return;
					}

					var item = response.data.item;

					$title.text('Contract: ' + (item.contract_code || ''));
					var status = item.status || 'active';
					statusBadge
						.text(
							status === 'completed'
								? 'Completed'
								: status === 'defaulted'
								? 'Defaulted'
								: status === 'cancelled'
								? 'Cancelled'
								: 'Active'
						)
						.attr('data-status', status)
						.addClass('jwpm-status-badge');

					$form.find('[data-jwpm-installments-input="id"]').val(item.id || '');
					$form
						.find('[data-jwpm-installments-input="customer_id"]')
						.val(item.customer_id || '');
					$form
						.find('[data-jwpm-installments-input="sale_date"]')
						.val(item.sale_date || '');
					$form
						.find('[data-jwpm-installments-input="total_amount"]')
						.val(item.total_amount || '');
					$form
						.find('[data-jwpm-installments-input="advance_amount"]')
						.val(item.advance_amount || '');
					$form
						.find('[data-jwpm-installments-input="net_amount"]')
						.val(item.net_amount || '');
					$form
						.find('[data-jwpm-installments-input="installment_count"]')
						.val(item.installment_count || '');
					$form
						.find('[data-jwpm-installments-input="installment_frequency"]')
						.val(item.installment_frequency || 'monthly');
					$form
						.find('[data-jwpm-installments-input="start_date"]')
						.val(item.start_date || '');
					$form
						.find('[data-jwpm-installments-input="status"]')
						.val(item.status || 'active');
					$form
						.find('[data-jwpm-installments-input="remarks"]')
						.val(item.remarks || '');

					// Schedule + payments بھی لوڈ کریں
					self.loadSchedule(id);
					self.loadPayments(id);
				})
				.fail(function () {
					notifyError('Contract ڈیٹا لوڈ نہیں ہو سکا۔');
					self.closeSidePanel();
				});
		};

		JWPMInstallmentsPage.prototype.serializeForm = function ($form) {
			var data = {};
			$.each($form.serializeArray(), function (_, field) {
				data[field.name] = field.value;
			});
			return data;
		};

		JWPMInstallmentsPage.prototype.saveContract = function ($form) {
			var self = this;

			if (!$form || !$form.length) {
				return;
			}

			var data = this.serializeForm($form);

			if (!data.customer_id) {
				notifyError('Customer منتخب کرنا ضروری ہے۔');
				return;
			}

			data.nonce = jwpmInstallmentsConfig.mainNonce;

			this.setLoading(true);
			notifyInfo(jwpmInstallmentsConfig.strings.saving || 'محفوظ ہو رہا ہے…');

			ajaxRequest('jwpm_save_installment', data)
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								jwpmInstallmentsConfig.strings.saveError
						);
						return;
					}
					notifySuccess(
						jwpmInstallmentsConfig.strings.saveSuccess || 'Installment Plan محفوظ ہو گیا۔'
					);
					self.closeSidePanel();
					self.loadInstallments();
				})
				.fail(function () {
					notifyError(
						jwpmInstallmentsConfig.strings.saveError || 'Contract محفوظ نہیں ہو سکا۔'
					);
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Schedule
		 */
		JWPMInstallmentsPage.prototype.loadSchedule = function (contractId) {
			var self = this;
			var $body = this.$sidePanel.find('[data-jwpm-installments-schedule-body]').first();
			var $statsWrapper = this.$sidePanel.find('.jwpm-schedule-summary').first();

			if (!$body.length) {
				return;
			}

			$body
				.empty()
				.append(
					$('<tr/>', { class: 'jwpm-loading-row' }).append(
						$('<td/>', {
							colspan: 6,
							text: jwpmInstallmentsConfig.strings.loading || 'لوڈ ہو رہا ہے…'
						})
					)
				);

			ajaxRequest('jwpm_get_installment_schedule', {
				nonce: jwpmInstallmentsConfig.mainNonce,
				contract_id: contractId
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Schedule لوڈ نہیں ہو سکا۔'
						);
						return;
					}

					var items = (response.data && response.data.items) || [];

					$body.empty();

					if (!items.length) {
						$body.append(
							$('<tr/>', { class: 'jwpm-empty-row' }).append(
								$('<td/>', {
									colspan: 6,
									text: 'ابھی کوئی Schedule موجود نہیں۔'
								})
							)
						);
					} else {
						var today = new Date();
						var total = items.length;
						var paid = 0;
						var pending = 0;
						var overdue = 0;

						items.forEach(function (row) {
							var tr = $('<tr/>');
							tr.append($('<td/>').text(row.installment_no || ''));
							tr.append($('<td/>').text(row.due_date || ''));
							tr.append($('<td/>').text(formatAmount(row.amount)));
							tr.append($('<td/>').text(formatAmount(row.paid_amount)));

							var st = row.status || 'pending';
							var $st = $('<span/>')
								.addClass('jwpm-status-badge')
								.attr('data-status', st)
								.text(st === 'paid' ? 'Paid' : st === 'late' ? 'Late' : 'Pending');
							tr.append($('<td/>').append($st));

							tr.append($('<td/>').text(row.paid_date || ''));

							$body.append(tr);

							if (st === 'paid') {
								paid++;
							} else if (st === 'pending') {
								pending++;
								if (row.due_date) {
									var d = new Date(row.due_date);
									if (d < today) {
										overdue++;
									}
								}
							} else if (st === 'late') {
								overdue++;
							}
						});

						if ($statsWrapper && $statsWrapper.length) {
							$statsWrapper
								.find('[data-jwpm-installments-sched-stat="total"]')
								.text('Total: ' + total);
							$statsWrapper
								.find('[data-jwpm-installments-sched-stat="paid"]')
								.text('Paid: ' + paid);
							$statsWrapper
								.find('[data-jwpm-installments-sched-stat="pending"]')
								.text('Pending: ' + pending);
							$statsWrapper
								.find('[data-jwpm-installments-sched-stat="overdue"]')
								.text('Overdue: ' + overdue);
						}
					}
				})
				.fail(function () {
					notifyError('Schedule لوڈ نہیں ہو سکا۔');
				});
		};

		/**
		 * Payments
		 */
		JWPMInstallmentsPage.prototype.loadPayments = function (contractId) {
			var self = this;
			var $body = this.$sidePanel.find('[data-jwpm-installments-payments-body]').first();

			if (!$body.length) {
				return;
			}

			$body
				.empty()
				.append(
					$('<tr/>', { class: 'jwpm-loading-row' }).append(
						$('<td/>', {
							colspan: 6,
							text: jwpmInstallmentsConfig.strings.loading || 'لوڈ ہو رہا ہے…'
						})
					)
				);

			ajaxRequest('jwpm_get_installment_payments', {
				nonce: jwpmInstallmentsConfig.mainNonce,
				contract_id: contractId
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Payments لوڈ نہیں ہو سکے۔'
						);
						return;
					}

					var items = (response.data && response.data.items) || [];
					$body.empty();

					if (!items.length) {
						$body.append(
							$('<tr/>', { class: 'jwpm-empty-row' }).append(
								$('<td/>', {
									colspan: 6,
									text: 'ابھی کوئی Payment درج نہیں ہوئی۔'
								})
							)
						);
					} else {
						items.forEach(function (row) {
							var tr = $('<tr/>');
							tr.append($('<td/>').text(row.payment_date || ''));
							tr.append($('<td/>').text(formatAmount(row.amount)));
							tr.append($('<td/>').text(row.method || ''));
							tr.append($('<td/>').text(row.reference_no || ''));
							tr.append($('<td/>').text(row.received_by || ''));
							tr.append($('<td/>').text(row.note || ''));
							$body.append(tr);
						});
					}
				})
				.fail(function () {
					notifyError('Payments لوڈ نہیں ہو سکے۔');
				});
		};

		/**
		 * Contract Status Update
		 */
		JWPMInstallmentsPage.prototype.updateContractStatus = function (id, status) {
			var self = this;
			if (
				status === 'cancelled' &&
				!confirmAction(
					jwpmInstallmentsConfig.strings.deleteConfirm ||
						'کیا آپ واقعی اس قسطی معاہدے کو Cancel کرنا چاہتے ہیں؟'
				)
			) {
				return;
			}

			this.setLoading(true);

			ajaxRequest('jwpm_update_installment_status', {
				nonce: jwpmInstallmentsConfig.mainNonce,
				id: id,
				status: status
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Status اپڈیٹ نہیں ہو سکا۔'
						);
						return;
					}
					notifySuccess(
						jwpmInstallmentsConfig.strings.deleteSuccess || 'Status اپڈیٹ ہو گیا۔'
					);
					self.loadInstallments();
				})
				.fail(function () {
					notifyError('Status اپڈیٹ نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Payment Modal
		 */
		JWPMInstallmentsPage.prototype.openPaymentModal = function (contractId) {
			var self = this;

			if (!this.templates.paymentModal) {
				notifyError('Payment modal template نہیں ملا۔');
				return;
			}

			if (this.$paymentModal && this.$paymentModal.length) {
				this.$paymentModal.remove();
				this.$paymentModal = null;
			}

			var node;
			if (this.templates.paymentModal.content) {
				node = this.templates.paymentModal.content.cloneNode(true);
			} else {
				node = document.importNode(this.templates.paymentModal, true);
			}

			this.$paymentModal = $(node);
			$('body').append(this.$paymentModal);

			var $modal = this.$paymentModal;
			var $form = $modal.find('[data-jwpm-installments-payment-form]').first();

			$form.find('[data-jwpm-installments-payment-input="contract_id"]').val(contractId);
			var today = new Date().toISOString().slice(0, 10);
			$form.find('[data-jwpm-installments-payment-input="payment_date"]').val(today);

			function closeModal() {
				$modal.remove();
				self.$paymentModal = null;
			}

			$modal.on('click', '[data-jwpm-installments-action="close-payment"]', function (e) {
				e.preventDefault();
				closeModal();
			});

			$modal.on('click', '[data-jwpm-installments-action="save-payment"]', function (e) {
				e.preventDefault();

				var data = {};
				$.each($form.serializeArray(), function (_, field) {
					data[field.name] = field.value;
				});

				data.nonce = jwpmInstallmentsConfig.mainNonce;

				if (!data.amount || parseNumber(data.amount) <= 0) {
					notifyError('Amount صفر سے زیادہ ہونی چاہئے۔');
					return;
				}

				ajaxRequest('jwpm_add_installment_payment', data)
					.done(function (response) {
						if (!response || !response.success) {
							notifyError(
								(response && response.data && response.data.message) ||
									jwpmInstallmentsConfig.strings.paymentError
							);
							return;
						}
						notifySuccess(
							jwpmInstallmentsConfig.strings.paymentSave || 'Payment محفوظ ہو گئی۔'
						);
						closeModal();
						if (self.state.currentContractId) {
							self.loadSchedule(self.state.currentContractId);
							self.loadPayments(self.state.currentContractId);
						}
						self.loadInstallments();
					})
					.fail(function () {
						notifyError(
							jwpmInstallmentsConfig.strings.paymentError ||
								'Payment محفوظ نہیں ہو سکی۔'
						);
					});
			});
		};

		/**
		 * Import / Export / Demo / Print
		 */
		JWPMInstallmentsPage.prototype.openImportModal = function () {
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
			var $form = $modal.find('[data-jwpm-installments-import-form]').first();
			var $result = $modal.find('[data-jwpm-installments-import-result]').first();

			function closeModal() {
				$modal.remove();
				self.$importModal = null;
			}

			$modal.on('click', '[data-jwpm-installments-action="close-import"]', function (e) {
				e.preventDefault();
				closeModal();
			});

			$modal.on('click', '[data-jwpm-installments-action="do-import"]', function (e) {
				e.preventDefault();

				var fileInput = $form.find('input[type="file"]')[0];
				if (!fileInput || !fileInput.files || !fileInput.files.length) {
					notifyError('براہ کرم (CSV) فائل منتخب کریں۔');
					return;
				}

				var formData = new FormData();
				formData.append('action', 'jwpm_import_installments');
				formData.append('nonce', jwpmInstallmentsConfig.importNonce);
				formData.append('file', fileInput.files[0]);

				var skipDup = $form.find('input[name="skip_duplicates"]').is(':checked') ? 1 : 0;
				formData.append('skip_duplicates', skipDup);

				$result.empty().text(
					jwpmInstallmentsConfig.strings.loading || 'Import ہو رہا ہے…'
				);

				$.ajax({
					url: jwpmInstallmentsConfig.ajaxUrl,
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
									jwpmInstallmentsConfig.strings.importError ||
									'Import کے دوران مسئلہ آیا۔'
							);
							return;
						}

						var data = response.data || {};
						var msg =
							(jwpmInstallmentsConfig.strings.importSuccess ||
								'Import مکمل ہو گیا۔') +
							' Total: ' +
							(data.total || 0) +
							', Inserted: ' +
							(data.inserted || 0) +
							', Skipped: ' +
							(data.skipped || 0);

						$result.text(msg);
						notifySuccess(msg);
						self.loadInstallments();
					})
					.fail(function () {
						notifyError(
							jwpmInstallmentsConfig.strings.importError ||
								'Import کے دوران مسئلہ آیا۔'
						);
					});
			});
		};

		JWPMInstallmentsPage.prototype.exportInstallments = function () {
			var url =
				jwpmInstallmentsConfig.ajaxUrl +
				'?action=jwpm_export_installments&nonce=' +
				encodeURIComponent(jwpmInstallmentsConfig.exportNonce);

			window.open(url, '_blank');
		};

		JWPMInstallmentsPage.prototype.createDemoInstallments = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_installments_demo_create', {
				nonce: jwpmInstallmentsConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Demo Installments نہیں بن سکے۔'
						);
						return;
					}
					notifySuccess(
						jwpmInstallmentsConfig.strings.demoCreateSuccess ||
							'Demo Installments بنا دیے گئے۔'
					);
					self.loadInstallments();
				})
				.fail(function () {
					notifyError('Demo Installments نہیں بن سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMInstallmentsPage.prototype.clearDemoInstallments = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_installments_demo_clear', {
				nonce: jwpmInstallmentsConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Demo Installments حذف نہیں ہو سکے۔'
						);
						return;
					}
					notifySuccess(
						jwpmInstallmentsConfig.strings.demoClearSuccess ||
							'Demo Installments حذف ہو گئے۔'
					);
					self.loadInstallments();
				})
				.fail(function () {
					notifyError('Demo Installments حذف نہیں ہو سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMInstallmentsPage.prototype.printInstallments = function () {
			var $table = this.$layout.find('.jwpm-table-installments').first();
			if (!$table.length) {
				notifyError('پرنٹ کیلئے کوئی جدول نہیں ملا۔');
				return;
			}

			var html = '<html><head><title>Installments List</title>';
			html +=
				'<style>body{font-family:system-ui, -apple-system, BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:12px;color:#000;padding:16px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:4px 6px;text-align:left;} th{background:#eee;} .jwpm-status-badge{font-weight:bold;}</style>';
			html += '</head><body>';
			html += '<h2>Installments / Credit Sales</h2>';
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

		return JWPMInstallmentsPage;
	})();

	/**
	 * DOM Ready — Root mount
	 */
	$(function () {
		var $root = $('#jwpm-installments-root').first();

		if (!$root.length) {
			if (window.console) {
				console.warn(
					'JWPM Installments: #jwpm-installments-root نہیں ملا، شاید یہ صحیح ایڈمن پیج نہیں۔'
				);
			}
			return;
		}

		try {
			new JWPMInstallmentsPage($root);
		} catch (e) {
			console.error('JWPM Installments init error:', e);
			notifyError('Installments Page لوڈ کرتے وقت مسئلہ آیا۔');
		}
	});

	// 🔴 یہاں پر [JWPM Installments Module] ختم ہو رہا ہے
})(jQuery);

// ✅ Syntax verified block end

