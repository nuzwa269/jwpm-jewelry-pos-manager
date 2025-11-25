/** Part 7 — JWPM Purchase Page Script (UI + AJAX)
 * یہاں Purchases & Suppliers پیج کا مکمل (JavaScript) behaviour ہے۔
 */
(function ($) {
	'use strict';

	// 🟢 یہاں سے [JWPM Purchase Module] شروع ہو رہا ہے

	/**
	 * Safe config (jwpmPurchaseData) اگر (PHP) سے نہ ملا ہو تو fallback
	 */
	var jwpmPurchaseConfig = window.jwpmPurchaseData || {
		ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
		mainNonce: '',
		importNonce: '',
		exportNonce: '',
		demoNonce: '',
		strings: {
			loading: 'Purchases لوڈ ہو رہے ہیں…',
			saving: 'ڈیٹا محفوظ ہو رہا ہے…',
			saveSuccess: 'Purchase محفوظ ہو گیا۔',
			saveError: 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔',
			deleteConfirm: 'کیا آپ واقعی اس Purchase کو Cancel/حذف کرنا چاہتے ہیں؟',
			deleteSuccess: 'Purchase کی Status اپڈیٹ ہو گئی۔',
			paymentSave: 'Payment محفوظ ہو گئی۔',
			paymentError: 'Payment محفوظ نہیں ہو سکی۔',
			demoCreateSuccess: 'Demo Purchases بنا دیے گئے۔',
			demoClearSuccess: 'Demo Purchases حذف ہو گئے۔',
			importSuccess: 'Import مکمل ہو گیا۔',
			importError: 'Import کے دوران مسئلہ آیا۔',
			noRecords: 'کوئی Purchase ریکارڈ نہیں ملا۔'
		},
		pagination: {
			defaultPerPage: 20,
			perPageOptions: [20, 50, 100]
		}
	};

	/**
	 * چھوٹے Helper — Notification
	 */
	function notifySuccess(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastSuccess === 'function') {
			window.jwpmCommon.toastSuccess(message);
		} else if (window.console) {
			console.log('[JWPM Purchase] ' + message);
		}
	}

	function notifyError(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastError === 'function') {
			window.jwpmCommon.toastError(message);
		} else {
			if (window.console) {
				console.error('[JWPM Purchase] ' + message);
			}
			alert(message);
		}
	}

	function notifyInfo(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastInfo === 'function') {
			window.jwpmCommon.toastInfo(message);
		} else if (window.console) {
			console.log('[JWPM Purchase] ' + message);
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
			url: jwpmPurchaseConfig.ajaxUrl,
			type: options.type || 'POST',
			data: payload,
			dataType: options.dataType || 'json',
			processData: options.processData !== false,
			contentType: options.contentType !== false ? 'application/x-www-form-urlencoded; charset=UTF-8' : false
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
		return n.toFixed(3);
	}

	/**
	 * مین Purchase Page Controller
	 */
	var JWPMPurchasePage = (function () {
		function JWPMPurchasePage($root) {
			this.$root = $root;

			this.state = {
				items: [],
				page: 1,
				perPage: jwpmPurchaseConfig.pagination.defaultPerPage || 20,
				total: 0,
				totalPages: 1,
				filters: {
					search: '',
					supplier_id: '',
					status: '',
					date_from: '',
					date_to: ''
				},
				loading: false,
				currentPurchaseId: null
			};

			this.$layout = null;
			this.$tableBody = null;
			this.$pagination = null;
			this.$sidePanel = null;
			this.$importModal = null;
			this.$paymentModal = null;

			this.templates = {
				layout: document.getElementById('jwpm-purchase-layout-template'),
				row: document.getElementById('jwpm-purchase-row-template'),
				panel: document.getElementById('jwpm-purchase-panel-template'),
				paymentModal: document.getElementById('jwpm-purchase-payment-modal-template'),
				importModal: document.getElementById('jwpm-purchase-import-template')
			};

			this.init();
		}

		JWPMPurchasePage.prototype.init = function () {
			if (!this.templates.layout) {
				notifyError('Purchase layout template نہیں ملا۔');
				return;
			}

			this.renderLayout();
			this.cacheElements();
			this.bindEvents();
			this.loadPurchases();
		};

		JWPMPurchasePage.prototype.renderLayout = function () {
			var tmpl = this.templates.layout.content
				? this.templates.layout.content.cloneNode(true)
				: document.importNode(this.templates.layout, true);

			this.$root.empty().append(tmpl);
		};

		JWPMPurchasePage.prototype.cacheElements = function () {
			this.$layout = this.$root.find('.jwpm-page-purchase').first();
			this.$tableBody = this.$layout.find('[data-jwpm-purchase-table-body]').first();
			this.$pagination = this.$layout.find('[data-jwpm-purchase-pagination]').first();
			this.$sidePanel = this.$layout.find('[data-jwpm-purchase-side-panel]').first();
		};

		JWPMPurchasePage.prototype.bindEvents = function () {
			var self = this;

			// Filters
			this.$layout.on('input', '[data-jwpm-purchase-filter="search"]', function () {
				self.state.filters.search = $(this).val();
				self.state.page = 1;
				self.loadPurchases();
			});

			this.$layout.on('change', '[data-jwpm-purchase-filter="supplier"]', function () {
				self.state.filters.supplier_id = $(this).val();
				self.state.page = 1;
				self.loadPurchases();
			});

			this.$layout.on('change', '[data-jwpm-purchase-filter="status"]', function () {
				self.state.filters.status = $(this).val();
				self.state.page = 1;
				self.loadPurchases();
			});

			this.$layout.on('change', '[data-jwpm-purchase-filter="date_from"]', function () {
				self.state.filters.date_from = $(this).val();
				self.state.page = 1;
				self.loadPurchases();
			});

			this.$layout.on('change', '[data-jwpm-purchase-filter="date_to"]', function () {
				self.state.filters.date_to = $(this).val();
				self.state.page = 1;
				self.loadPurchases();
			});

			// Toolbar actions
			this.$layout.on('click', '[data-jwpm-purchase-action="add"]', function () {
				self.openPurchasePanel(null);
			});

			this.$layout.on('click', '[data-jwpm-purchase-action="pay"]', function () {
				if (!self.state.currentPurchaseId) {
					notifyInfo('کوئی Purchase منتخب نہیں، براہ کرم Table سے View/Pay استعمال کریں۔');
					return;
				}
				self.openPaymentModal(self.state.currentPurchaseId);
			});

			this.$layout.on('click', '[data-jwpm-purchase-action="import"]', function () {
				self.openImportModal();
			});

			this.$layout.on('click', '[data-jwpm-purchase-action="export"]', function () {
				self.exportPurchases();
			});

			this.$layout.on('click', '[data-jwpm-purchase-action="print"]', function () {
				self.printPurchases();
			});

			this.$layout.on('click', '[data-jwpm-purchase-action="demo-create"]', function () {
				self.createDemoPurchases();
			});

			this.$layout.on('click', '[data-jwpm-purchase-action="demo-clear"]', function () {
				self.clearDemoPurchases();
			});

			// Table row actions
			this.$layout.on('click', '[data-jwpm-purchase-action="view"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-purchase-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.openPurchasePanel(id);
				}
			});

			this.$layout.on('click', '[data-jwpm-purchase-action="pay-row"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-purchase-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.state.currentPurchaseId = id;
					self.openPaymentModal(id);
				}
			});

			this.$layout.on('click', '[data-jwpm-purchase-action="delete"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-purchase-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.deletePurchase(id);
				}
			});

			// Payment status badge click (toggle quick)
			this.$layout.on('click', '[data-jwpm-purchase-field="status_badge"]', function () {
				var $row = $(this).closest('[data-jwpm-purchase-row]');
				var id = parseInt($row.data('id'), 10);
				if (!id) return;
				var current = $(this).attr('data-status') || 'unpaid';
				var next = current === 'unpaid' ? 'partial' : current === 'partial' ? 'paid' : 'unpaid';
				self.updatePaymentStatus(id, next);
			});

			// Pagination
			this.$pagination.on('click', '[data-jwpm-page]', function () {
				var page = parseInt($(this).attr('data-jwpm-page'), 10);
				if (!isNaN(page) && page >= 1 && page <= self.state.totalPages && page !== self.state.page) {
					self.state.page = page;
					self.loadPurchases();
				}
			});

			this.$pagination.on('change', '[data-jwpm-per-page]', function () {
				var per = parseInt($(this).val(), 10);
				if (!isNaN(per) && per > 0) {
					self.state.perPage = per;
					self.state.page = 1;
					self.loadPurchases();
				}
			});
		};

		JWPMPurchasePage.prototype.setLoading = function (loading) {
			this.state.loading = loading;
			if (loading) {
				this.$root.addClass('jwpm-is-loading');
			} else {
				this.$root.removeClass('jwpm-is-loading');
			}
		};

		/**
		 * Purchases List Load + Render
		 */
		JWPMPurchasePage.prototype.loadPurchases = function () {
			var self = this;

			this.setLoading(true);

			this.$tableBody.empty().append(
				$('<tr/>', { class: 'jwpm-loading-row' }).append(
					$('<td/>', {
						colspan: 11,
						text: jwpmPurchaseConfig.strings.loading || 'لوڈ ہو رہا ہے…'
					})
				)
			);

			ajaxRequest('jwpm_get_purchases', {
				nonce: jwpmPurchaseConfig.mainNonce,
				search: this.state.filters.search,
				supplier_id: this.state.filters.supplier_id,
				status: this.state.filters.status,
				date_from: this.state.filters.date_from,
				date_to: this.state.filters.date_to,
				page: this.state.page,
				per_page: this.state.perPage
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								jwpmPurchaseConfig.strings.saveError
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
					notifyError(jwpmPurchaseConfig.strings.saveError || 'ڈیٹا لوڈ نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMPurchasePage.prototype.renderStats = function () {
			var todayTotal = 0;
			var monthTotal = 0;
			var totalPayable = 0;

			var todayStr = new Date().toISOString().slice(0, 10);
			var thisMonth = todayStr.substring(0, 7); // YYYY-MM

			this.state.items.forEach(function (item) {
				var billDate = item.bill_date || '';
				var net = parseNumber(item.net_payable);
				var balance = parseNumber(item.balance_amount);

				if (billDate === todayStr) {
					todayTotal += net;
				}
				if (billDate && billDate.substring(0, 7) === thisMonth) {
					monthTotal += net;
				}
				totalPayable += balance;
			});

			this.$layout
				.find('[data-jwpm-purchase-stat="today"] .jwpm-stat-value')
				.text(formatAmount(todayTotal));
			this.$layout
				.find('[data-jwpm-purchase-stat="month"] .jwpm-stat-value')
				.text(formatAmount(monthTotal));
			this.$layout
				.find('[data-jwpm-purchase-stat="payable"] .jwpm-stat-value')
				.text(formatAmount(totalPayable));
		};

		JWPMPurchasePage.prototype.renderTable = function () {
			var self = this;
			this.$tableBody.empty();

			if (!this.state.items || !this.state.items.length) {
				this.$tableBody.append(
					$('<tr/>', { class: 'jwpm-empty-row' }).append(
						$('<td/>', {
							colspan: 11,
							text: jwpmPurchaseConfig.strings.noRecords || 'کوئی Purchase ریکارڈ نہیں ملا۔'
						})
					)
				);
				return;
			}

			if (!this.templates.row) {
				notifyError('Purchase row template نہیں ملا۔');
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

				$tr.attr('data-jwpm-purchase-row', '').attr('data-id', item.id);

				$tr.find('[data-jwpm-purchase-field="purchase_code"]').text(item.purchase_code || '');
				$tr.find('[data-jwpm-purchase-field="supplier_name"]').text(item.supplier_name || '');
				$tr.find('[data-jwpm-purchase-field="bill_no"]').text(item.bill_no || '');
				$tr.find('[data-jwpm-purchase-field="bill_date"]').text(item.bill_date || '');
				$tr
					.find('[data-jwpm-purchase-field="total_items"]')
					.text(item.total_items || 0);
				$tr
					.find('[data-jwpm-purchase-field="net_weight"]')
					.text(formatAmount(item.net_weight));
				$tr
					.find('[data-jwpm-purchase-field="net_payable"]')
					.text(formatAmount(item.net_payable));
				$tr
					.find('[data-jwpm-purchase-field="paid_amount"]')
					.text(formatAmount(item.paid_amount));
				$tr
					.find('[data-jwpm-purchase-field="balance_amount"]')
					.text(formatAmount(item.balance_amount));

				var status = item.payment_status || 'unpaid';
				var $badge = $tr.find('[data-jwpm-purchase-field="status_badge"]');
				$badge
					.attr('data-status', status)
					.addClass('jwpm-status-badge')
					.text(
						status === 'paid'
							? 'Paid'
							: status === 'partial'
							? 'Partial'
							: 'Unpaid'
					);

				self.$tableBody.append($tr);
			});
		};

		JWPMPurchasePage.prototype.renderPagination = function () {
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

			(jwpmPurchaseConfig.pagination.perPageOptions || [20, 50, 100]).forEach(function (val) {
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
		 * Side Panel — Overview / Items / Payments
		 */
		JWPMPurchasePage.prototype.openPurchasePanel = function (id) {
			var self = this;

			if (!this.templates.panel) {
				notifyError('Purchase panel template نہیں ملا۔');
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
			var $form = $panel.find('[data-jwpm-purchase-form]').first();
			var $title = $panel.find('[data-jwpm-purchase-panel-title]').first();
			var $statusBadge = $panel.find('[data-jwpm-purchase-payment-status]').first();

			// Tabs
			$panel.on('click', '.jwpm-tab', function () {
				var tab = $(this).attr('data-jwpm-purchase-tab');
				if (!tab) return;

				$panel.find('.jwpm-tab').removeClass('is-active');
				$(this).addClass('is-active');

				$panel.find('.jwpm-tab-panel').removeClass('is-active');
				$panel
					.find('[data-jwpm-purchase-tab-panel="' + tab + '"]')
					.addClass('is-active');
			});

			// Close actions
			$panel.on('click', '[data-jwpm-purchase-action="close-panel"]', this.closeSidePanel.bind(this));

			// Save Purchase
			$panel.on('click', '[data-jwpm-purchase-action="save-purchase"]', function (e) {
				e.preventDefault();
				self.savePurchase($form);
			});

			// Overview auto-calculation (net_payable, balance)
			$panel.on(
				'input',
				'[data-jwpm-purchase-input="total_amount"], [data-jwpm-purchase-input="discount_amount"], [data-jwpm-purchase-input="paid_amount"]',
				function () {
					self.recalculateHeaderTotals($form);
				}
			);

			// Items tab: add row
			$panel.on('click', '[data-jwpm-purchase-action="add-item"]', function (e) {
				e.preventDefault();
				self.addItemRow();
			});

			// Items tab: delete row
			$panel.on('click', '[data-jwpm-purchase-action="delete-item-row"]', function (e) {
				e.preventDefault();
				$(this).closest('tr').remove();
				self.recalculateItemsSummary();
			});

			// Items tab: وزن / rate change → line amount calc
			$panel.on(
				'input',
				'[data-jwpm-purchase-item-field="net_weight"], [data-jwpm-purchase-item-field="rate_per_gram"]',
				function () {
					self.recalculateItemRow($(this).closest('tr'));
					self.recalculateItemsSummary();
				}
			);

			// Payments tab: Add Payment button
			$panel.on('click', '[data-jwpm-purchase-action="add-payment"]', function () {
				if (!self.state.currentPurchaseId && !id) {
					notifyInfo('پہلے Purchase محفوظ کریں، پھر Payment add کریں۔');
					return;
				}
				var pid = self.state.currentPurchaseId || id;
				self.openPaymentModal(pid);
			});

			if (!id) {
				// New Purchase
				this.state.currentPurchaseId = null;
				$title.text('New Purchase');
				$statusBadge
					.text('Unpaid')
					.attr('data-status', 'unpaid')
					.addClass('jwpm-status-badge');
				if ($form.length && $form[0]) {
					$form[0].reset();
				}
				$form.find('[data-jwpm-purchase-input="id"]').val('');
				this.initItemsTableEmpty();
				this.initPaymentsTableEmpty();
				this.recalculateHeaderTotals($form);
			} else {
				this.state.currentPurchaseId = id;
				this.loadPurchaseIntoPanel(id, $panel, $form, $title, $statusBadge);
			}
		};

		JWPMPurchasePage.prototype.closeSidePanel = function () {
			this.$sidePanel.prop('hidden', true).empty();
		};

		JWPMPurchasePage.prototype.initItemsTableEmpty = function () {
			var $body = this.$sidePanel.find('[data-jwpm-purchase-items-body]').first();
			if (!$body.length) return;
			$body.empty();
			this.addItemRow();
		};

		JWPMPurchasePage.prototype.initPaymentsTableEmpty = function () {
			var $body = this.$sidePanel.find('[data-jwpm-purchase-payments-body]').first();
			if (!$body.length) return;
			$body.empty().append(
				$('<tr/>', { class: 'jwpm-empty-row' }).append(
					$('<td/>', {
						colspan: 5,
						text: 'ابھی کوئی Payment درج نہیں ہوئی۔'
					})
				)
			);
		};

		JWPMPurchasePage.prototype.loadPurchaseIntoPanel = function (
			id,
			$panel,
			$form,
			$title,
			$statusBadge
		) {
			var self = this;

			$title.text('Loading…');

			ajaxRequest('jwpm_get_purchase', {
				nonce: jwpmPurchaseConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success || !response.data || !response.data.header) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Purchase نہیں ملا۔'
						);
						self.closeSidePanel();
						return;
					}

					var header = response.data.header;
					var items = response.data.items || [];
					var payments = response.data.payments || [];

					$title.text('Purchase: ' + (header.purchase_code || ''));
					var st = header.payment_status || 'unpaid';
					statusBadge
						.text(
							st === 'paid'
								? 'Paid'
								: st === 'partial'
								? 'Partial'
								: 'Unpaid'
						)
						.attr('data-status', st)
						.addClass('jwpm-status-badge');

					// Header form fill
					$form.find('[data-jwpm-purchase-input="id"]').val(header.id || '');
					$form
						.find('[data-jwpm-purchase-input="supplier_id"]')
						.val(header.supplier_id || '');
					$form
						.find('[data-jwpm-purchase-input="bill_no"]')
						.val(header.bill_no || '');
					$form
						.find('[data-jwpm-purchase-input="bill_date"]')
						.val(header.bill_date || '');
					$form
						.find('[data-jwpm-purchase-input="purchase_type"]')
						.val(header.purchase_type || 'gold');
					$form
						.find('[data-jwpm-purchase-input="gross_weight"]')
						.val(header.gross_weight || '');
					$form
						.find('[data-jwpm-purchase-input="net_weight"]')
						.val(header.net_weight || '');
					$form
						.find('[data-jwpm-purchase-input="fine_gold_qty"]')
						.val(header.fine_gold_qty || '');
					$form
						.find('[data-jwpm-purchase-input="total_items"]')
						.val(header.total_items || '');
					$form
						.find('[data-jwpm-purchase-input="total_amount"]')
						.val(header.total_amount || '');
					$form
						.find('[data-jwpm-purchase-input="discount_amount"]')
						.val(header.discount_amount || '');
					$form
						.find('[data-jwpm-purchase-input="net_payable"]')
						.val(header.net_payable || '');
					$form
						.find('[data-jwpm-purchase-input="paid_amount"]')
						.val(header.paid_amount || '');
					$form
						.find('[data-jwpm-purchase-input="balance_amount"]')
						.val(header.balance_amount || '');
					$form
						.find('[data-jwpm-purchase-input="payment_status"]')
						.val(header.payment_status || 'unpaid');
					$form
						.find('[data-jwpm-purchase-input="notes"]')
						.val(header.notes || '');

					// Items
					self.renderItems(items);
					// Payments
					self.renderPayments(payments);
					// Summary recalc
					self.recalculateItemsSummary();
					self.recalculateHeaderTotals($form);
				})
				.fail(function () {
					notifyError('Purchase ڈیٹا لوڈ نہیں ہو سکا۔');
					self.closeSidePanel();
				});
		};

		JWPMPurchasePage.prototype.serializeForm = function ($form) {
			var data = {};
			$.each($form.serializeArray(), function (_, field) {
				data[field.name] = field.value;
			});
			return data;
		};

		JWPMPurchasePage.prototype.recalculateHeaderTotals = function ($form) {
			if (!$form || !$form.length) return;
			var total = parseNumber(
				$form.find('[data-jwpm-purchase-input="total_amount"]').val()
			);
			var discount = parseNumber(
				$form.find('[data-jwpm-purchase-input="discount_amount"]').val()
			);
			var paid = parseNumber(
				$form.find('[data-jwpm-purchase-input="paid_amount"]').val()
			);

			var net = total - discount;
			if (net < 0) net = 0;
			var balance = net - paid;
			if (balance < 0) balance = 0;

			$form.find('[data-jwpm-purchase-input="net_payable"]').val(net.toFixed(3));
			$form.find('[data-jwpm-purchase-input="balance_amount"]').val(balance.toFixed(3));

			var $status = $form.find('[data-jwpm-purchase-input="payment_status"]');
			if (net === 0) {
				$status.val('unpaid');
			} else if (balance === 0 && paid > 0) {
				$status.val('paid');
			} else if (paid > 0 && balance > 0) {
				$status.val('partial');
			}
		};

		/**
		 * Items table logic
		 */
		JWPMPurchasePage.prototype.addItemRow = function (item) {
			var $body = this.$sidePanel.find('[data-jwpm-purchase-items-body]').first();
			if (!$body.length) return;

			if ($body.find('.jwpm-empty-row').length) {
				$body.empty();
			}

			var $tr = $('<tr/>');

			$tr.append(
				$('<td/>').append(
					$('<span/>', {
						'data-jwpm-purchase-item-field': 'index'
					})
				)
			);

			$tr.append(
				$('<td/>').append(
					$('<input/>', {
						type: 'text',
						class: 'jwpm-input',
						'data-jwpm-purchase-item-field': 'description',
						value: (item && item.description) || ''
					})
				)
			);

			$tr.append(
				$('<td/>').append(
					$('<input/>', {
						type: 'text',
						class: 'jwpm-input',
						'data-jwpm-purchase-item-field': 'karat',
						value: (item && item.karat) || ''
					})
				)
			);

			$tr.append(
				$('<td/>').append(
					$('<input/>', {
						type: 'number',
						step: '0.001',
						class: 'jwpm-input',
						'data-jwpm-purchase-item-field': 'gross_weight',
						value: (item && item.gross_weight) || ''
					})
				)
			);

			$tr.append(
				$('<td/>').append(
					$('<input/>', {
						type: 'number',
						step: '0.001',
						class: 'jwpm-input',
						'data-jwpm-purchase-item-field': 'stone_weight',
						value: (item && item.stone_weight) || ''
					})
				)
			);

			$tr.append(
				$('<td/>').append(
					$('<input/>', {
						type: 'number',
						step: '0.001',
						class: 'jwpm-input',
						'data-jwpm-purchase-item-field': 'net_weight',
						value: (item && item.net_weight) || ''
					})
				)
			);

			$tr.append(
				$('<td/>').append(
					$('<input/>', {
						type: 'number',
						step: '0.001',
						class: 'jwpm-input',
						'data-jwpm-purchase-item-field': 'rate_per_gram',
						value: (item && item.rate_per_gram) || ''
					})
				)
			);

			$tr.append(
				$('<td/>').append(
					$('<input/>', {
						type: 'number',
						step: '0.001',
						class: 'jwpm-input',
						readonly: true,
						'data-jwpm-purchase-item-field': 'line_amount',
						value: (item && item.line_amount) || ''
					})
				)
			);

			$tr.append(
				$('<td/>', { class: 'jwpm-table-actions' }).append(
					$('<button/>', {
						type: 'button',
						class: 'button-link jwpm-text-danger',
						'data-jwpm-purchase-action': 'delete-item-row',
						text: 'Remove'
					})
				)
			);

			$body.append($tr);
			this.refreshItemsRowIndex();
			if (!item) {
				this.recalculateItemRow($tr);
			}
			this.recalculateItemsSummary();
		};

		JWPMPurchasePage.prototype.refreshItemsRowIndex = function () {
			var $body = this.$sidePanel.find('[data-jwpm-purchase-items-body]').first();
			if (!$body.length) return;
			var idx = 1;
			$body.find('tr').each(function () {
				$(this)
					.find('[data-jwpm-purchase-item-field="index"]')
					.text(idx++);
			});
		};

		JWPMPurchasePage.prototype.recalculateItemRow = function ($tr) {
			var net = parseNumber(
				$tr.find('[data-jwpm-purchase-item-field="net_weight"]').val()
			);
			var rate = parseNumber(
				$tr.find('[data-jwpm-purchase-item-field="rate_per_gram"]').val()
			);
			var line = net * rate;
			$tr.find('[data-jwpm-purchase-item-field="line_amount"]').val(line.toFixed(3));
		};

		JWPMPurchasePage.prototype.recalculateItemsSummary = function () {
			var $body = this.$sidePanel.find('[data-jwpm-purchase-items-body]').first();
			if (!$body.length) return;

			var totalItems = 0;
			var totalNetWeight = 0;
			var totalAmount = 0;

			$body.find('tr').each(function () {
				var $tr = $(this);
				var desc = $tr.find('[data-jwpm-purchase-item-field="description"]').val();
				if (!desc) {
					return;
				}
				totalItems++;
				totalNetWeight += parseNumber(
					$tr.find('[data-jwpm-purchase-item-field="net_weight"]').val()
				);
				totalAmount += parseNumber(
					$tr.find('[data-jwpm-purchase-item-field="line_amount"]').val()
				);
			});

			var $summary = this.$sidePanel.find('[data-jwpm-purchase-items-summary]').first();
			if ($summary.length) {
				$summary
					.find('[data-jwpm-purchase-items-stat="count"]')
					.text('Items: ' + totalItems);
				$summary
					.find('[data-jwpm-purchase-items-stat="net_weight"]')
					.text('Net Wt: ' + totalNetWeight.toFixed(3));
				$summary
					.find('[data-jwpm-purchase-items-stat="amount"]')
					.text('Amount: ' + totalAmount.toFixed(3));
			}

			// Header کے total_amount / total_items بھی اپڈیٹ کریں
			var $form = this.$sidePanel.find('[data-jwpm-purchase-form]').first();
			if ($form.length) {
				$form
					.find('[data-jwpm-purchase-input="total_items"]')
					.val(totalItems);
				$form
					.find('[data-jwpm-purchase-input="total_amount"]')
					.val(totalAmount.toFixed(3));
				this.recalculateHeaderTotals($form);
			}
		};

		JWPMPurchasePage.prototype.collectItemsPayload = function () {
			var items = [];
			var $body = this.$sidePanel.find('[data-jwpm-purchase-items-body]').first();
			if (!$body.length) return items;

			$body.find('tr').each(function () {
				var $tr = $(this);
				var desc = $tr.find('[data-jwpm-purchase-item-field="description"]').val();
				if (!desc) {
					return;
				}
				items.push({
					description: desc,
					karat: $tr
						.find('[data-jwpm-purchase-item-field="karat"]')
						.val(),
					gross_weight: $tr
						.find('[data-jwpm-purchase-item-field="gross_weight"]')
						.val(),
					stone_weight: $tr
						.find('[data-jwpm-purchase-item-field="stone_weight"]')
						.val(),
					net_weight: $tr
						.find('[data-jwpm-purchase-item-field="net_weight"]')
						.val(),
					rate_per_gram: $tr
						.find('[data-jwpm-purchase-item-field="rate_per_gram"]')
						.val(),
					line_amount: $tr
						.find('[data-jwpm-purchase-item-field="line_amount"]')
						.val()
				});
			});

			return items;
		};

		JWPMPurchasePage.prototype.renderItems = function (items) {
			var self = this;
			var $body = this.$sidePanel.find('[data-jwpm-purchase-items-body]').first();
			if (!$body.length) return;

			$body.empty();

			if (!items || !items.length) {
				this.addItemRow();
				return;
			}

			items.forEach(function (item) {
				self.addItemRow(item);
			});
			this.recalculateItemsSummary();
		};

		/**
		 * Payments (log)
		 */
		JWPMPurchasePage.prototype.renderPayments = function (payments) {
			var $body = this.$sidePanel.find('[data-jwpm-purchase-payments-body]').first();
			if (!$body.length) return;

			$body.empty();

			if (!payments || !payments.length) {
				this.initPaymentsTableEmpty();
				return;
			}

			payments.forEach(function (row) {
				var $tr = $('<tr/>');
				$tr.append($('<td/>').text(row.payment_date || ''));
				$tr.append($('<td/>').text(formatAmount(row.amount)));
				$tr.append($('<td/>').text(row.method || ''));
				$tr.append($('<td/>').text(row.reference_no || ''));
				$tr.append($('<td/>').text(row.note || ''));
				$body.append($tr);
			});
		};

		JWPMPurchasePage.prototype.loadPayments = function (purchaseId) {
			var self = this;
			var $body = this.$sidePanel.find('[data-jwpm-purchase-payments-body]').first();
			if (!$body.length) return;

			$body
				.empty()
				.append(
					$('<tr/>', { class: 'jwpm-loading-row' }).append(
						$('<td/>', {
							colspan: 5,
							text: jwpmPurchaseConfig.strings.loading || 'لوڈ ہو رہا ہے…'
						})
					)
				);

			ajaxRequest('jwpm_get_purchase_payments', {
				nonce: jwpmPurchaseConfig.mainNonce,
				purchase_id: purchaseId
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Payments لوڈ نہیں ہو سکے۔'
						);
						return;
					}
					var payments = (response.data && response.data.items) || [];
					self.renderPayments(payments);
				})
				.fail(function () {
					notifyError('Payments لوڈ نہیں ہو سکے۔');
				});
		};

		/**
		 * Save Purchase (Header + Items)
		 */
		JWPMPurchasePage.prototype.savePurchase = function ($form) {
			var self = this;

			if (!$form || !$form.length) {
				return;
			}

			var data = this.serializeForm($form);

			if (!data.supplier_id) {
				notifyError('Supplier منتخب کرنا ضروری ہے۔');
				return;
			}

			// Items as JSON
			var items = this.collectItemsPayload();
			data.items_json = JSON.stringify(items || []);
			data.nonce = jwpmPurchaseConfig.mainNonce;

			this.setLoading(true);
			notifyInfo(jwpmPurchaseConfig.strings.saving || 'محفوظ ہو رہا ہے…');

			ajaxRequest('jwpm_save_purchase', data)
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								jwpmPurchaseConfig.strings.saveError
						);
						return;
					}
					notifySuccess(
						jwpmPurchaseConfig.strings.saveSuccess || 'Purchase محفوظ ہو گیا۔'
					);
					self.closeSidePanel();
					self.loadPurchases();
				})
				.fail(function () {
					notifyError(
						jwpmPurchaseConfig.strings.saveError || 'Purchase محفوظ نہیں ہو سکا۔'
					);
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Payment Status Update (table badge)
		 */
		JWPMPurchasePage.prototype.updatePaymentStatus = function (id, status) {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_update_purchase_status', {
				nonce: jwpmPurchaseConfig.mainNonce,
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
					notifySuccess('Payment status اپڈیٹ ہو گیا۔');
					self.loadPurchases();
				})
				.fail(function () {
					notifyError('Status اپڈیٹ نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Delete / Cancel Purchase
		 */
		JWPMPurchasePage.prototype.deletePurchase = function (id) {
			var self = this;

			if (
				!confirmAction(
					jwpmPurchaseConfig.strings.deleteConfirm ||
						'کیا آپ واقعی اس Purchase کو Cancel کرنا چاہتے ہیں؟'
				)
			) {
				return;
			}

			this.setLoading(true);

			ajaxRequest('jwpm_delete_purchase', {
				nonce: jwpmPurchaseConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Purchase حذف نہیں ہو سکا۔'
						);
						return;
					}
					notifySuccess(
						jwpmPurchaseConfig.strings.deleteSuccess || 'Purchase کی Status اپڈیٹ ہو گئی۔'
					);
					self.loadPurchases();
				})
				.fail(function () {
					notifyError('Purchase حذف نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Payment Modal
		 */
		JWPMPurchasePage.prototype.openPaymentModal = function (purchaseId) {
			var self = this;

			if (!this.templates.paymentModal) {
				notifyError('Purchase payment modal template نہیں ملا۔');
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
			var $form = $modal.find('[data-jwpm-purchase-payment-form]').first();

			$form.find('[data-jwpm-purchase-payment-input="purchase_id"]').val(purchaseId);
			var today = new Date().toISOString().slice(0, 10);
			$form
				.find('[data-jwpm-purchase-payment-input="payment_date"]')
				.val(today);

			function closeModal() {
				$modal.remove();
				self.$paymentModal = null;
			}

			$modal.on('click', '[data-jwpm-purchase-action="close-payment"]', function (e) {
				e.preventDefault();
				closeModal();
			});

			$modal.on('click', '[data-jwpm-purchase-action="save-payment"]', function (e) {
				e.preventDefault();

				var data = {};
				$.each($form.serializeArray(), function (_, field) {
					data[field.name] = field.value;
				});

				data.nonce = jwpmPurchaseConfig.mainNonce;

				if (!data.amount || parseNumber(data.amount) <= 0) {
					notifyError('Amount صفر سے زیادہ ہونی چاہئے۔');
					return;
				}

				ajaxRequest('jwpm_add_purchase_payment', data)
					.done(function (response) {
						if (!response || !response.success) {
							notifyError(
								(response && response.data && response.data.message) ||
									jwpmPurchaseConfig.strings.paymentError
							);
							return;
						}
						notifySuccess(
							jwpmPurchaseConfig.strings.paymentSave || 'Payment محفوظ ہو گئی۔'
						);
						closeModal();
						if (self.state.currentPurchaseId) {
							self.loadPayments(self.state.currentPurchaseId);
						}
						self.loadPurchases();
					})
					.fail(function () {
						notifyError(
							jwpmPurchaseConfig.strings.paymentError ||
								'Payment محفوظ نہیں ہو سکی۔'
						);
					});
			});
		};

		/**
		 * Import / Export / Demo / Print
		 */
		JWPMPurchasePage.prototype.openImportModal = function () {
			var self = this;

			if (!this.templates.importModal) {
				notifyError('Purchase import modal template نہیں ملا۔');
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
			var $form = $modal.find('[data-jwpm-purchase-import-form]').first();
			var $result = $modal.find('[data-jwpm-purchase-import-result]').first();

			function closeModal() {
				$modal.remove();
				self.$importModal = null;
			}

			$modal.on('click', '[data-jwpm-purchase-action="close-import"]', function (e) {
				e.preventDefault();
				closeModal();
			});

			$modal.on('click', '[data-jwpm-purchase-action="do-import"]', function (e) {
				e.preventDefault();

				var fileInput = $form.find('input[type="file"]')[0];
				if (!fileInput || !fileInput.files || !fileInput.files.length) {
					notifyError('براہ کرم (CSV) فائل منتخب کریں۔');
					return;
				}

				var formData = new FormData();
				formData.append('action', 'jwpm_import_purchases');
				formData.append('nonce', jwpmPurchaseConfig.importNonce);
				formData.append('file', fileInput.files[0]);

				var skipDup = $form.find('input[name="skip_duplicates"]').is(':checked') ? 1 : 0;
				formData.append('skip_duplicates', skipDup);

				$result.empty().text(
					jwpmPurchaseConfig.strings.loading || 'Import ہو رہا ہے…'
				);

				$.ajax({
					url: jwpmPurchaseConfig.ajaxUrl,
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
									jwpmPurchaseConfig.strings.importError ||
									'Import کے دوران مسئلہ آیا۔'
							);
							return;
						}

						var data = response.data || {};
						var msg =
							(jwpmPurchaseConfig.strings.importSuccess ||
								'Import مکمل ہو گیا۔') +
							' Total: ' +
							(data.total || 0) +
							', Inserted: ' +
							(data.inserted || 0) +
							', Skipped: ' +
							(data.skipped || 0);

						$result.text(msg);
						notifySuccess(msg);
						self.loadPurchases();
					})
					.fail(function () {
						notifyError(
							jwpmPurchaseConfig.strings.importError ||
								'Import کے دوران مسئلہ آیا۔'
						);
					});
			});
		};

		JWPMPurchasePage.prototype.exportPurchases = function () {
			var url =
				jwpmPurchaseConfig.ajaxUrl +
				'?action=jwpm_export_purchases&nonce=' +
				encodeURIComponent(jwpmPurchaseConfig.exportNonce);
			window.open(url, '_blank');
		};

		JWPMPurchasePage.prototype.createDemoPurchases = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_purchase_demo_create', {
				nonce: jwpmPurchaseConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Demo Purchases نہیں بن سکے۔'
						);
						return;
					}
					notifySuccess(
						jwpmPurchaseConfig.strings.demoCreateSuccess ||
							'Demo Purchases بنا دیے گئے۔'
					);
					self.loadPurchases();
				})
				.fail(function () {
					notifyError('Demo Purchases نہیں بن سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMPurchasePage.prototype.clearDemoPurchases = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_purchase_demo_clear', {
				nonce: jwpmPurchaseConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Demo Purchases حذف نہیں ہو سکے۔'
						);
						return;
					}
					notifySuccess(
						jwpmPurchaseConfig.strings.demoClearSuccess ||
							'Demo Purchases حذف ہو گئے۔'
					);
					self.loadPurchases();
				})
				.fail(function () {
					notifyError('Demo Purchases حذف نہیں ہو سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMPurchasePage.prototype.printPurchases = function () {
			var $table = this.$layout.find('.jwpm-table-purchases').first();
			if (!$table.length) {
				notifyError('پرنٹ کیلئے کوئی جدول نہیں ملا۔');
				return;
			}

			var html = '<html><head><title>Purchases List</title>';
			html +=
				'<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:12px;color:#000;padding:16px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:4px 6px;text-align:left;} th{background:#eee;} .jwpm-status-badge{font-weight:bold;}</style>';
			html += '</head><body>';
			html += '<h2>Purchases & Suppliers</h2>';
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

		return JWPMPurchasePage;
	})();

	/**
	 * DOM Ready — Root mount
	 */
	$(function () {
		var $root = $('#jwpm-purchase-root').first();

		if (!$root.length) {
			if (window.console) {
				console.warn(
					'JWPM Purchase: #jwpm-purchase-root نہیں ملا، شاید یہ صحیح ایڈمن پیج نہیں۔'
				);
			}
			return;
		}

		try {
			new JWPMPurchasePage($root);
		} catch (e) {
			console.error('JWPM Purchase init error:', e);
			notifyError('Purchase Page لوڈ کرتے وقت مسئلہ آیا۔');
		}
	});

	// 🔴 یہاں پر [JWPM Purchase Module] ختم ہو رہا ہے
})(jQuery);

// ✅ Syntax verified block end

