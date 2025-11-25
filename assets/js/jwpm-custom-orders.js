/** Part 4 — JWPM Custom Orders Page Script (UI + AJAX)
 * یہاں Custom / Design Orders پیج کا مکمل (JavaScript) behaviour ہے۔
 */
(function ($) {
	'use strict';

	// 🟢 یہاں سے [JWPM Custom Orders Module] شروع ہو رہا ہے

	/**
	 * Safe config (jwpmCustomOrdersData) اگر (PHP) سے نہ ملا ہو تو fallback
	 */
	var jwpmCustomOrdersConfig = window.jwpmCustomOrdersData || {
		ajaxUrl: window.ajaxurl || '/wp-admin/admin-ajax.php',
		mainNonce: '',
		importNonce: '',
		exportNonce: '',
		demoNonce: '',
		strings: {
			loading: 'Custom Orders لوڈ ہو رہے ہیں…',
			saving: 'ڈیٹا محفوظ ہو رہا ہے…',
			saveSuccess: 'Custom Order محفوظ ہو گیا۔',
			saveError: 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔',
			deleteConfirm: 'کیا آپ واقعی اس Custom Order کو Cancel کرنا چاہتے ہیں؟',
			deleteSuccess: 'Custom Order کی Status اپڈیٹ ہو گئی۔',
			fileUploadError: 'فائل اپلوڈ نہیں ہو سکی۔',
			fileDeleteError: 'فائل حذف نہیں ہو سکی۔',
			stageSaveError: 'Stage محفوظ نہیں ہو سکی۔',
			demoCreateSuccess: 'Demo Custom Orders بنا دیے گئے۔',
			demoClearSuccess: 'Demo Custom Orders حذف ہو گئے۔',
			importSuccess: 'Import مکمل ہو گیا۔',
			importError: 'Import کے دوران مسئلہ آیا۔',
			noRecords: 'کوئی Custom Order نہیں ملا۔'
		},
		pagination: {
			defaultPerPage: 20,
			perPageOptions: [20, 50, 100]
		}
	};

	/**
	 * چھوٹے Helper — Notifications
	 */
	function notifySuccess(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastSuccess === 'function') {
			window.jwpmCommon.toastSuccess(message);
		} else if (window.console) {
			console.log('[JWPM Custom Orders] ' + message);
		}
	}

	function notifyError(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastError === 'function') {
			window.jwpmCommon.toastError(message);
		} else {
			if (window.console) {
				console.error('[JWPM Custom Orders] ' + message);
			}
			alert(message);
		}
	}

	function notifyInfo(message) {
		if (window.jwpmCommon && typeof window.jwpmCommon.toastInfo === 'function') {
			window.jwpmCommon.toastInfo(message);
		} else if (window.console) {
			console.log('[JWPM Custom Orders] ' + message);
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
			url: jwpmCustomOrdersConfig.ajaxUrl,
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
		return n.toFixed(3);
	}

	/**
	 * مین Custom Orders Page Controller
	 */
	var JWPMCustomOrdersPage = (function () {
		function JWPMCustomOrdersPage($root) {
			this.$root = $root;

			this.state = {
				items: [],
				page: 1,
				perPage: jwpmCustomOrdersConfig.pagination.defaultPerPage || 20,
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
				currentOrderId: null
			};

			this.$layout = null;
			this.$tableBody = null;
			this.$pagination = null;
			this.$sidePanel = null;
			this.$importModal = null;

			this.templates = {
				layout: document.getElementById('jwpm-custom-orders-layout-template'),
				row: document.getElementById('jwpm-custom-orders-row-template'),
				panel: document.getElementById('jwpm-custom-orders-panel-template'),
				importModal: document.getElementById('jwpm-custom-orders-import-template')
			};

			this.init();
		}

		JWPMCustomOrdersPage.prototype.init = function () {
			if (!this.templates.layout) {
				notifyError('Custom Orders layout template نہیں ملا۔');
				return;
			}

			this.renderLayout();
			this.cacheElements();
			this.bindEvents();
			this.loadOrders();
		};

		JWPMCustomOrdersPage.prototype.renderLayout = function () {
			var tmpl = this.templates.layout.content
				? this.templates.layout.content.cloneNode(true)
				: document.importNode(this.templates.layout, true);

			this.$root.empty().append(tmpl);
		};

		JWPMCustomOrdersPage.prototype.cacheElements = function () {
			this.$layout = this.$root.find('.jwpm-page-custom-orders').first();
			this.$tableBody = this.$layout.find('[data-jwpm-custom-orders-table-body]').first();
			this.$pagination = this.$layout.find('[data-jwpm-custom-orders-pagination]').first();
			this.$sidePanel = this.$layout.find('[data-jwpm-custom-orders-side-panel]').first();
		};

		JWPMCustomOrdersPage.prototype.bindEvents = function () {
			var self = this;

			// Filters
			this.$layout.on('input', '[data-jwpm-custom-orders-filter="search"]', function () {
				self.state.filters.search = $(this).val();
				self.state.page = 1;
				self.loadOrders();
			});

			this.$layout.on('change', '[data-jwpm-custom-orders-filter="status"]', function () {
				self.state.filters.status = $(this).val();
				self.state.page = 1;
				self.loadOrders();
			});

			this.$layout.on('change', '[data-jwpm-custom-orders-filter="priority"]', function () {
				self.state.filters.priority = $(this).val();
				self.state.page = 1;
				self.loadOrders();
			});

			this.$layout.on('change', '[data-jwpm-custom-orders-filter="date_from"]', function () {
				self.state.filters.date_from = $(this).val();
				self.state.page = 1;
				self.loadOrders();
			});

			this.$layout.on('change', '[data-jwpm-custom-orders-filter="date_to"]', function () {
				self.state.filters.date_to = $(this).val();
				self.state.page = 1;
				self.loadOrders();
			});

			// Toolbar actions
			this.$layout.on('click', '[data-jwpm-custom-orders-action="add"]', function () {
				self.openOrderPanel(null);
			});

			this.$layout.on('click', '[data-jwpm-custom-orders-action="import"]', function () {
				self.openImportModal();
			});

			this.$layout.on('click', '[data-jwpm-custom-orders-action="export"]', function () {
				self.exportOrders();
			});

			this.$layout.on('click', '[data-jwpm-custom-orders-action="print"]', function () {
				self.printOrders();
			});

			this.$layout.on('click', '[data-jwpm-custom-orders-action="demo-create"]', function () {
				self.createDemoOrders();
			});

			this.$layout.on('click', '[data-jwpm-custom-orders-action="demo-clear"]', function () {
				self.clearDemoOrders();
			});

			// Table row actions
			this.$layout.on('click', '[data-jwpm-custom-orders-action="view"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-custom-orders-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.openOrderPanel(id);
				}
			});

			this.$layout.on('click', '[data-jwpm-custom-orders-action="mark-ready"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-custom-orders-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.quickUpdateStatus(id, 'ready');
				}
			});

			this.$layout.on('click', '[data-jwpm-custom-orders-action="mark-delivered"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-custom-orders-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.quickUpdateStatus(id, 'delivered');
				}
			});

			this.$layout.on('click', '[data-jwpm-custom-orders-action="delete"]', function (e) {
				e.preventDefault();
				var $row = $(this).closest('[data-jwpm-custom-orders-row]');
				var id = parseInt($row.data('id'), 10);
				if (id) {
					self.deleteOrder(id);
				}
			});

			// Status / priority badge quick change
			this.$layout.on('click', '[data-jwpm-custom-orders-field="status_badge"]', function () {
				var $row = $(this).closest('[data-jwpm-custom-orders-row]');
				var id = parseInt($row.data('id'), 10);
				if (!id) return;
				var current = $(this).attr('data-status') || 'draft';
				var next =
					current === 'draft'
						? 'design_approved'
						: current === 'design_approved'
						? 'in_production'
						: current === 'in_production'
						? 'ready'
						: current === 'ready'
						? 'delivered'
						: 'draft';
				self.quickUpdateStatus(id, next);
			});

			this.$layout.on('click', '[data-jwpm-custom-orders-field="priority_badge"]', function () {
				var $row = $(this).closest('[data-jwpm-custom-orders-row]');
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
					self.loadOrders();
				}
			});

			this.$pagination.on('change', '[data-jwpm-per-page]', function () {
				var per = parseInt($(this).val(), 10);
				if (!isNaN(per) && per > 0) {
					self.state.perPage = per;
					self.state.page = 1;
					self.loadOrders();
				}
			});
		};

		JWPMCustomOrdersPage.prototype.setLoading = function (loading) {
			this.state.loading = loading;
			if (loading) {
				this.$root.addClass('jwpm-is-loading');
			} else {
				this.$root.removeClass('jwpm-is-loading');
			}
		};

		/**
		 * Orders List Load + Render
		 */
		JWPMCustomOrdersPage.prototype.loadOrders = function () {
			var self = this;

			this.setLoading(true);

			this.$tableBody.empty().append(
				$('<tr/>', { class: 'jwpm-loading-row' }).append(
					$('<td/>', {
						colspan: 11,
						text: jwpmCustomOrdersConfig.strings.loading || 'لوڈ ہو رہا ہے…'
					})
				)
			);

			ajaxRequest('jwpm_get_custom_orders', {
				nonce: jwpmCustomOrdersConfig.mainNonce,
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
								jwpmCustomOrdersConfig.strings.saveError
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
						jwpmCustomOrdersConfig.strings.saveError || 'Custom Orders لوڈ نہیں ہو سکے۔'
					);
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomOrdersPage.prototype.renderStats = function () {
			var active = 0;
			var dueThisWeek = 0;
			var overdue = 0;
			var pendingAmount = 0;

			var today = new Date();
			var weekAhead = new Date();
			weekAhead.setDate(today.getDate() + 7);

			function parseDate(str) {
				if (!str) return null;
				var d = new Date(str);
				return isNaN(d.getTime()) ? null : d;
			}

			this.state.items.forEach(function (order) {
				var st = order.status || 'draft';
				var delDate = parseDate(order.delivery_date);
				var net = parseNumber(order.net_amount);
				var delivered = st === 'delivered';

				if (st === 'design_approved' || st === 'in_production') {
					active++;
				}

				if (delDate && !delivered) {
					if (delDate >= today && delDate <= weekAhead) {
						dueThisWeek++;
					}
					if (delDate < today) {
						overdue++;
					}
				}

				if (!delivered) {
					pendingAmount += net;
				}
			});

			this.$layout
				.find('[data-jwpm-custom-orders-stat="active"] .jwpm-stat-value')
				.text(active);
			this.$layout
				.find('[data-jwpm-custom-orders-stat="due_week"] .jwpm-stat-value')
				.text(dueThisWeek);
			this.$layout
				.find('[data-jwpm-custom-orders-stat="overdue"] .jwpm-stat-value')
				.text(overdue);
			this.$layout
				.find('[data-jwpm-custom-orders-stat="pending_amount"] .jwpm-stat-value')
				.text(formatAmount(pendingAmount));
		};

		JWPMCustomOrdersPage.prototype.renderTable = function () {
			var self = this;
			this.$tableBody.empty();

			if (!this.state.items || !this.state.items.length) {
				this.$tableBody.append(
					$('<tr/>', { class: 'jwpm-empty-row' }).append(
						$('<td/>', {
							colspan: 11,
							text:
								jwpmCustomOrdersConfig.strings.noRecords ||
								'کوئی Custom Order نہیں ملا۔'
						})
					)
				);
				return;
			}

			if (!this.templates.row) {
				notifyError('Custom Orders row template نہیں ملا۔');
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

				$tr.attr('data-jwpm-custom-orders-row', '').attr('data-id', item.id);

				$tr
					.find('[data-jwpm-custom-orders-field="order_code"]')
					.text(item.order_code || '');
				$tr
					.find('[data-jwpm-custom-orders-field="customer_name"]')
					.text(item.customer_name || '');
				$tr
					.find('[data-jwpm-custom-orders-field="customer_phone"]')
					.text(item.customer_phone || '');
				$tr
					.find('[data-jwpm-custom-orders-field="design_title"]')
					.text(item.design_title || '');
				$tr
					.find('[data-jwpm-custom-orders-field="metal_karat"]')
					.text(
						(item.metal_type || '') +
							(item.karat ? ' ' + item.karat : '')
					);
				$tr
					.find('[data-jwpm-custom-orders-field="expected_weight"]')
					.text(formatAmount(item.expected_weight));
				$tr
					.find('[data-jwpm-custom-orders-field="estimate_amount"]')
					.text(formatAmount(item.estimate_amount));
				$tr
					.find('[data-jwpm-custom-orders-field="advance_amount"]')
					.text(formatAmount(item.advance_amount));
				$tr
					.find('[data-jwpm-custom-orders-field="delivery_date"]')
					.text(item.delivery_date || '');

				// Status badge
				var status = item.status || 'draft';
				var $statusBadge = $tr.find(
					'[data-jwpm-custom-orders-field="status_badge"]'
				);
				$statusBadge
					.attr('data-status', status)
					.addClass('jwpm-status-badge')
					.text(
						status === 'design_approved'
							? 'Design OK'
							: status === 'in_production'
							? 'In Production'
							: status === 'ready'
							? 'Ready'
							: status === 'delivered'
							? 'Delivered'
							: status === 'cancelled'
							? 'Cancelled'
							: 'Draft'
					);

				// Priority badge
				var priority = item.priority || 'normal';
				var $priorityBadge = $tr.find(
					'[data-jwpm-custom-orders-field="priority_badge"]'
				);
				$priorityBadge
					.attr('data-priority', priority)
					.addClass('jwpm-priority-badge')
					.text(
						priority === 'urgent'
							? 'Urgent'
							: priority === 'vip'
							? 'VIP'
							: 'Normal'
					);

				self.$tableBody.append($tr);
			});
		};

		JWPMCustomOrdersPage.prototype.renderPagination = function () {
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

			(jwpmCustomOrdersConfig.pagination.perPageOptions || [20, 50, 100]).forEach(function (val) {
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
		 * Side Panel — Overview / Files / Stages
		 */
		JWPMCustomOrdersPage.prototype.openOrderPanel = function (id) {
			var self = this;

			if (!this.templates.panel) {
				notifyError('Custom Orders panel template نہیں ملا۔');
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
			var $form = $panel.find('[data-jwpm-custom-orders-form]').first();
			var $title = $panel.find('[data-jwpm-custom-orders-panel-title]').first();
			var $subtitle = $panel
				.find('[data-jwpm-custom-orders-panel-subtitle]')
				.first();
			var $statusBadge = $panel
				.find('[data-jwpm-custom-orders-panel-status]')
				.first();
			var $priorityBadge = $panel
				.find('[data-jwpm-custom-orders-panel-priority]')
				.first();

			// Tabs
			$panel.on('click', '.jwpm-tab', function () {
				var tab = $(this).attr('data-jwpm-custom-orders-tab');
				if (!tab) return;

				$panel.find('.jwpm-tab').removeClass('is-active');
				$(this).addClass('is-active');

				$panel.find('.jwpm-tab-panel').removeClass('is-active');
				$panel
					.find('[data-jwpm-custom-orders-tab-panel="' + tab + '"]')
					.addClass('is-active');
			});

			// Close actions
			$panel.on('click', '[data-jwpm-custom-orders-action="close-panel"]', this.closeSidePanel.bind(this));

			// Save order
			$panel.on('click', '[data-jwpm-custom-orders-action="save-order"]', function (e) {
				e.preventDefault();
				self.saveOrder($form);
			});

			// Overview auto net amount calc (estimate - advance)
			$panel.on(
				'input',
				'[data-jwpm-custom-orders-input="estimate_amount"], [data-jwpm-custom-orders-input="advance_amount"]',
				function () {
					self.recalculateAmounts($form);
				}
			);

			// Files tab: upload + delete
			$panel.on('click', '[data-jwpm-custom-orders-action="upload-file"]', function (e) {
				e.preventDefault();
				var $file = $panel.find(
					'[data-jwpm-custom-orders-files-input="file"]'
				);
				if (!$file.length || !$file[0].files || !$file[0].files.length) {
					notifyError('براہ کرم فائل منتخب کریں۔');
					return;
				}
				if (!self.state.currentOrderId && !id) {
					notifyInfo('پہلے Order محفوظ کریں، پھر فائل اپلوڈ کریں۔');
					return;
				}
				var orderId = self.state.currentOrderId || id;
				self.uploadFile(orderId, $file[0].files[0]);
			});

			$panel.on('click', '[data-jwpm-custom-orders-action="delete-file"]', function (e) {
				e.preventDefault();
				var fileId = parseInt($(this).attr('data-file-id'), 10);
				if (!fileId) return;
				self.deleteFile(fileId);
			});

			// Stages tab: add stage update
			$panel.on('click', '[data-jwpm-custom-orders-action="add-stage"]', function (e) {
				e.preventDefault();
				if (!self.state.currentOrderId && !id) {
					notifyInfo('پہلے Order محفوظ کریں، پھر Stage add کریں۔');
					return;
				}
				var orderId = self.state.currentOrderId || id;
				self.saveStageUpdate(orderId);
			});

			// New order vs existing
			if (!id) {
				this.state.currentOrderId = null;
				$title.text('New Custom Order');
				$subtitle.text('');
				$statusBadge
					.text('Draft')
					.attr('data-status', 'draft')
					.addClass('jwpm-status-badge');
				$priorityBadge
					.text('Normal')
					.attr('data-priority', 'normal')
					.addClass('jwpm-priority-badge');

				if ($form.length && $form[0]) {
					$form[0].reset();
				}
				$form.find('[data-jwpm-custom-orders-input="id"]').val('');
				this.recalculateAmounts($form);
				this.renderFiles([]);
				this.renderStages([]);
			} else {
				this.state.currentOrderId = id;
				this.loadOrderIntoPanel(id, $panel, $form, $title, $subtitle, $statusBadge, $priorityBadge);
			}
		};

		JWPMCustomOrdersPage.prototype.closeSidePanel = function () {
			this.$sidePanel.prop('hidden', true).empty();
		};

		JWPMCustomOrdersPage.prototype.loadOrderIntoPanel = function (
			id,
			$panel,
			$form,
			$title,
			$subtitle,
			$statusBadge,
			$priorityBadge
		) {
			var self = this;

			$title.text('Loading…');
			$subtitle.text('');

			ajaxRequest('jwpm_get_custom_order', {
				nonce: jwpmCustomOrdersConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success || !response.data || !response.data.header) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Custom Order نہیں ملا۔'
						);
						self.closeSidePanel();
						return;
					}

					var header = response.data.header;
					var files = response.data.files || [];
					var stages = response.data.stages || [];

					$title.text('Order: ' + (header.order_code || ''));
					$subtitle.text(
						(header.customer_name || '') +
							(header.customer_phone ? ' • ' + header.customer_phone : '')
					);

					var st = header.status || 'draft';
					statusBadge
						.text(
							st === 'design_approved'
								? 'Design OK'
								: st === 'in_production'
								? 'In Production'
								: st === 'ready'
								? 'Ready'
								: st === 'delivered'
								? 'Delivered'
								: st === 'cancelled'
								? 'Cancelled'
								: 'Draft'
						)
						.attr('data-status', st)
						.addClass('jwpm-status-badge');

					var priority = header.priority || 'normal';
					$priorityBadge
						.text(
							priority === 'urgent'
								? 'Urgent'
								: priority === 'vip'
								? 'VIP'
								: 'Normal'
						)
						.attr('data-priority', priority)
						.addClass('jwpm-priority-badge');

					// Overview form fill
					$form.find('[data-jwpm-custom-orders-input="id"]').val(header.id || '');
					$form
						.find('[data-jwpm-custom-orders-input="customer_id"]')
						.val(header.customer_id || '');
					$form
						.find('[data-jwpm-custom-orders-input="customer_name"]')
						.val(header.customer_name || '');
					$form
						.find('[data-jwpm-custom-orders-input="customer_phone"]')
						.val(header.customer_phone || '');
					$form
						.find('[data-jwpm-custom-orders-input="order_date"]')
						.val(header.order_date || '');
					$form
						.find('[data-jwpm-custom-orders-input="delivery_date"]')
						.val(header.delivery_date || '');
					$form
						.find('[data-jwpm-custom-orders-input="design_title"]')
						.val(header.design_title || '');
					$form
						.find('[data-jwpm-custom-orders-input="design_type"]')
						.val(header.design_type || '');
					$form
						.find('[data-jwpm-custom-orders-input="metal_type"]')
						.val(header.metal_type || 'gold');
					$form
						.find('[data-jwpm-custom-orders-input="karat"]')
						.val(header.karat || '');
					$form
						.find('[data-jwpm-custom-orders-input="expected_weight"]')
						.val(header.expected_weight || '');
					$form
						.find('[data-jwpm-custom-orders-input="final_weight"]')
						.val(header.final_weight || '');
					$form
						.find('[data-jwpm-custom-orders-input="estimate_amount"]')
						.val(header.estimate_amount || '');
					$form
						.find('[data-jwpm-custom-orders-input="advance_amount"]')
						.val(header.advance_amount || '');
					$form
						.find('[data-jwpm-custom-orders-input="net_amount"]')
						.val(header.net_amount || '');
					$form
						.find('[data-jwpm-custom-orders-input="status"]')
						.val(header.status || 'draft');
					$form
						.find('[data-jwpm-custom-orders-input="assigned_to"]')
						.val(header.assigned_to || '');
					$form
						.find('[data-jwpm-custom-orders-input="priority"]')
						.val(header.priority || 'normal');
					$form
						.find('[data-jwpm-custom-orders-input="remarks"]')
						.val(header.remarks || '');

					self.recalculateAmounts($form);
					self.renderFiles(files);
					self.renderStages(stages);
				})
				.fail(function () {
					notifyError('Custom Order ڈیٹا لوڈ نہیں ہو سکا۔');
					self.closeSidePanel();
				});
		};

		JWPMCustomOrdersPage.prototype.serializeForm = function ($form) {
			var data = {};
			$.each($form.serializeArray(), function (_, field) {
				data[field.name] = field.value;
			});
			return data;
		};

		JWPMCustomOrdersPage.prototype.recalculateAmounts = function ($form) {
			if (!$form || !$form.length) return;

			var estimate = parseNumber(
				$form
					.find('[data-jwpm-custom-orders-input="estimate_amount"]')
					.val()
			);
			var advance = parseNumber(
				$form
					.find('[data-jwpm-custom-orders-input="advance_amount"]')
					.val()
			);

			var net = estimate - advance;
			if (net < 0) net = 0;

			$form
				.find('[data-jwpm-custom-orders-input="net_amount"]')
				.val(net.toFixed(3));
		};

		/**
		 * Save Order (Overview)
		 */
		JWPMCustomOrdersPage.prototype.saveOrder = function ($form) {
			var self = this;

			if (!$form || !$form.length) {
				return;
			}

			var data = this.serializeForm($form);
			data.nonce = jwpmCustomOrdersConfig.mainNonce;

			if (!data.customer_name && !data.customer_id) {
				notifyError('Customer کا نام یا ریکارڈ منتخب کرنا ضروری ہے۔');
				return;
			}

			this.setLoading(true);
			notifyInfo(
				jwpmCustomOrdersConfig.strings.saving ||
					'Custom Order محفوظ ہو رہا ہے…'
			);

			ajaxRequest('jwpm_save_custom_order', data)
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								jwpmCustomOrdersConfig.strings.saveError
						);
						return;
					}

					notifySuccess(
						jwpmCustomOrdersConfig.strings.saveSuccess ||
							'Custom Order محفوظ ہو گیا۔'
					);

					if (response.data && response.data.id) {
						self.state.currentOrderId = parseInt(response.data.id, 10) || null;
					}

					self.closeSidePanel();
					self.loadOrders();
				})
				.fail(function () {
					notifyError(
						jwpmCustomOrdersConfig.strings.saveError ||
							'Custom Order محفوظ نہیں ہو سکا۔'
					);
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Quick Status / Priority Update
		 * (same action: jwpm_save_custom_order — partial update)
		 */
		JWPMCustomOrdersPage.prototype.quickUpdateStatus = function (id, status) {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_save_custom_order', {
				nonce: jwpmCustomOrdersConfig.mainNonce,
				id: id,
				status: status,
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
					self.loadOrders();
				})
				.fail(function () {
					notifyError('Status اپڈیٹ نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomOrdersPage.prototype.quickUpdatePriority = function (id, priority) {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_save_custom_order', {
				nonce: jwpmCustomOrdersConfig.mainNonce,
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
					self.loadOrders();
				})
				.fail(function () {
					notifyError('Priority اپڈیٹ نہیں ہو سکی۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Delete / Cancel Order
		 */
		JWPMCustomOrdersPage.prototype.deleteOrder = function (id) {
			var self = this;

			if (
				!confirmAction(
					jwpmCustomOrdersConfig.strings.deleteConfirm ||
						'کیا آپ واقعی اس Custom Order کو Cancel کرنا چاہتے ہیں؟'
				)
			) {
				return;
			}

			this.setLoading(true);

			ajaxRequest('jwpm_delete_custom_order', {
				nonce: jwpmCustomOrdersConfig.mainNonce,
				id: id
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Custom Order حذف نہیں ہو سکا۔'
						);
						return;
					}
					notifySuccess(
						jwpmCustomOrdersConfig.strings.deleteSuccess ||
							'Custom Order کی Status اپڈیٹ ہو گئی۔'
					);
					self.loadOrders();
				})
				.fail(function () {
					notifyError('Custom Order حذف نہیں ہو سکا۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		/**
		 * Design Files — List / Upload / Delete
		 */
		JWPMCustomOrdersPage.prototype.renderFiles = function (files) {
			var $list = this.$sidePanel.find(
				'[data-jwpm-custom-orders-files-body]'
			).first();
			if (!$list.length) return;

			$list.empty();

			if (!files || !files.length) {
				$list.append(
					$('<tr/>', { class: 'jwpm-empty-row' }).append(
						$('<td/>', {
							colspan: 5,
							text: 'ابھی کوئی فائل اپلوڈ نہیں ہوئی۔'
						})
					)
				);
				return;
			}

			files.forEach(function (file) {
				var $tr = $('<tr/>');
				var type = file.file_type || 'file';
				var icon = type.indexOf('image') !== -1 ? '🖼' : '📄';

				$tr.append($('<td/>').text(icon));
				$tr.append($('<td/>').text(file.file_name || ''));
				$tr.append($('<td/>').text(file.file_type || ''));
				$tr.append($('<td/>').text(file.uploaded_at || ''));

				var $actions = $('<td/>', { class: 'jwpm-table-actions' });
				if (file.file_url) {
					$actions.append(
						$('<a/>', {
							href: file.file_url,
							target: '_blank',
							class: 'button-link',
							text: 'View'
						})
					);
				}
				$actions.append(
					$('<button/>', {
						type: 'button',
						class: 'button-link jwpm-text-danger',
						'data-jwpm-custom-orders-action': 'delete-file',
						'data-file-id': file.id,
						text: 'Remove'
					})
				);

				$tr.append($actions);
				$list.append($tr);
			});
		};

		JWPMCustomOrdersPage.prototype.loadFiles = function (orderId) {
			var self = this;
			var $list = this.$sidePanel.find(
				'[data-jwpm-custom-orders-files-body]'
			).first();
			if (!$list.length) return;

			$list
				.empty()
				.append(
					$('<tr/>', { class: 'jwpm-loading-row' }).append(
						$('<td/>', {
							colspan: 5,
							text: jwpmCustomOrdersConfig.strings.loading || 'لوڈ ہو رہا ہے…'
						})
					)
				);

			ajaxRequest('jwpm_get_custom_order_files', {
				nonce: jwpmCustomOrdersConfig.mainNonce,
				order_id: orderId
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Files لوڈ نہیں ہو سکیں۔'
						);
						return;
					}
					var files = (response.data && response.data.items) || [];
					self.renderFiles(files);
				})
				.fail(function () {
					notifyError('Files لوڈ نہیں ہو سکیں۔');
				});
		};

		JWPMCustomOrdersPage.prototype.uploadFile = function (orderId, file) {
			var self = this;
			var $result = this.$sidePanel.find(
				'[data-jwpm-custom-orders-files-result]'
			).first();

			var formData = new FormData();
			formData.append('action', 'jwpm_upload_custom_order_file');
			formData.append('nonce', jwpmCustomOrdersConfig.mainNonce);
			formData.append('order_id', orderId);
			formData.append('file', file);

			if ($result.length) {
				$result.text(
					jwpmCustomOrdersConfig.strings.loading || 'اپلوڈ ہو رہا ہے…'
				);
			}

			$.ajax({
				url: jwpmCustomOrdersConfig.ajaxUrl,
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
								jwpmCustomOrdersConfig.strings.fileUploadError ||
								'فائل اپلوڈ نہیں ہو سکی۔'
						);
						return;
					}
					notifySuccess('فائل اپلوڈ ہو گئی۔');
					if ($result.length) {
						$result.text('فائل اپلوڈ ہو گئی۔');
					}
					self.loadFiles(orderId);
				})
				.fail(function () {
					notifyError(
						jwpmCustomOrdersConfig.strings.fileUploadError ||
							'فائل اپلوڈ نہیں ہو سکی۔'
					);
				});
		};

		JWPMCustomOrdersPage.prototype.deleteFile = function (fileId) {
			var self = this;
			if (
				!confirmAction('کیا آپ واقعی اس فائل کو حذف کرنا چاہتے ہیں؟')
			) {
				return;
			}

			ajaxRequest('jwpm_delete_custom_order_file', {
				nonce: jwpmCustomOrdersConfig.mainNonce,
				id: fileId
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								jwpmCustomOrdersConfig.strings.fileDeleteError ||
								'فائل حذف نہیں ہو سکی۔'
						);
						return;
					}
					notifySuccess('فائل حذف ہو گئی۔');
					if (self.state.currentOrderId) {
						self.loadFiles(self.state.currentOrderId);
					}
				})
				.fail(function () {
					notifyError(
						jwpmCustomOrdersConfig.strings.fileDeleteError ||
							'فائل حذف نہیں ہو سکی۔'
					);
				});
		};

		/**
		 * Stages — Timeline / History
		 */
		JWPMCustomOrdersPage.prototype.renderStages = function (stages) {
			var $list = this.$sidePanel.find(
				'[data-jwpm-custom-orders-stages-body]'
			).first();
			if (!$list.length) return;

			$list.empty();

			if (!stages || !stages.length) {
				$list.append(
					$('<tr/>', { class: 'jwpm-empty-row' }).append(
						$('<td/>', {
							colspan: 4,
							text: 'ابھی کوئی Stage update نہیں ہوا۔'
						})
					)
				);
				return;
			}

			stages.forEach(function (row) {
				var $tr = $('<tr/>');
				$tr.append($('<td/>').text(row.updated_at || ''));
				$tr.append($('<td/>').text(row.stage_label || row.stage || ''));
				$tr.append($('<td/>').text(row.status_label || row.status || ''));
				$tr.append($('<td/>').text(row.notes || ''));
				$list.append($tr);
			});
		};

		JWPMCustomOrdersPage.prototype.loadStages = function (orderId) {
			var self = this;
			var $list = this.$sidePanel.find(
				'[data-jwpm-custom-orders-stages-body]'
			).first();
			if (!$list.length) return;

			$list
				.empty()
				.append(
					$('<tr/>', { class: 'jwpm-loading-row' }).append(
						$('<td/>', {
							colspan: 4,
							text: jwpmCustomOrdersConfig.strings.loading || 'لوڈ ہو رہا ہے…'
						})
					)
				);

			ajaxRequest('jwpm_get_custom_order_stages', {
				nonce: jwpmCustomOrdersConfig.mainNonce,
				order_id: orderId
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Stages لوڈ نہیں ہو سکے۔'
						);
						return;
					}
					var stages = (response.data && response.data.items) || [];
					self.renderStages(stages);
				})
				.fail(function () {
					notifyError('Stages لوڈ نہیں ہو سکے۔');
				});
		};

		JWPMCustomOrdersPage.prototype.saveStageUpdate = function (orderId) {
			var self = this;
			var $stageSelect = this.$sidePanel.find(
				'[data-jwpm-custom-orders-stage-input="stage"]'
			);
			var $statusSelect = this.$sidePanel.find(
				'[data-jwpm-custom-orders-stage-input="status"]'
			);
			var $notes = this.$sidePanel.find(
				'[data-jwpm-custom-orders-stage-input="notes"]'
			);

			var stage = $stageSelect.val();
			var status = $statusSelect.val();
			var notes = $notes.val();

			if (!stage || !status) {
				notifyError('Stage اور Status منتخب کرنا ضروری ہے۔');
				return;
			}

			ajaxRequest('jwpm_save_custom_order_stage', {
				nonce: jwpmCustomOrdersConfig.mainNonce,
				order_id: orderId,
				stage: stage,
				status: status,
				notes: notes
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								jwpmCustomOrdersConfig.strings.stageSaveError ||
								'Stage محفوظ نہیں ہو سکی۔'
						);
						return;
					}

					notifySuccess('Stage update محفوظ ہو گیا۔');
					$notes.val('');
					self.loadStages(orderId);
				})
				.fail(function () {
					notifyError(
						jwpmCustomOrdersConfig.strings.stageSaveError ||
							'Stage محفوظ نہیں ہو سکی۔'
					);
				});
		};

		/**
		 * Import / Export / Demo / Print
		 */
		JWPMCustomOrdersPage.prototype.openImportModal = function () {
			var self = this;

			if (!this.templates.importModal) {
				notifyError('Custom Orders import modal template نہیں ملا۔');
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
			var $form = $modal.find('[data-jwpm-custom-orders-import-form]').first();
			var $result = $modal
				.find('[data-jwpm-custom-orders-import-result]')
				.first();

			function closeModal() {
				$modal.remove();
				self.$importModal = null;
			}

			$modal.on('click', '[data-jwpm-custom-orders-action="close-import"]', function (e) {
				e.preventDefault();
				closeModal();
			});

			$modal.on('click', '[data-jwpm-custom-orders-action="do-import"]', function (e) {
				e.preventDefault();

				var fileInput = $form.find('input[type="file"]')[0];
				if (!fileInput || !fileInput.files || !fileInput.files.length) {
					notifyError('براہ کرم (CSV) فائل منتخب کریں۔');
					return;
				}

				var formData = new FormData();
				formData.append('action', 'jwpm_import_custom_orders');
				formData.append('nonce', jwpmCustomOrdersConfig.importNonce);
				formData.append('file', fileInput.files[0]);

				var skipDup = $form
					.find('input[name="skip_duplicates"]')
					.is(':checked')
					? 1
					: 0;
				formData.append('skip_duplicates', skipDup);

				$result.empty().text(
					jwpmCustomOrdersConfig.strings.loading || 'Import ہو رہا ہے…'
				);

				$.ajax({
					url: jwpmCustomOrdersConfig.ajaxUrl,
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
									jwpmCustomOrdersConfig.strings.importError ||
									'Import کے دوران مسئلہ آیا۔'
							);
							return;
						}

						var data = response.data || {};
						var msg =
							(jwpmCustomOrdersConfig.strings.importSuccess ||
								'Import مکمل ہو گیا۔') +
							' Total: ' +
							(data.total || 0) +
							', Inserted: ' +
							(data.inserted || 0) +
							', Skipped: ' +
							(data.skipped || 0);

						$result.text(msg);
						notifySuccess(msg);
						self.loadOrders();
					})
					.fail(function () {
						notifyError(
							jwpmCustomOrdersConfig.strings.importError ||
								'Import کے دوران مسئلہ آیا۔'
						);
					});
			});
		};

		JWPMCustomOrdersPage.prototype.exportOrders = function () {
			var url =
				jwpmCustomOrdersConfig.ajaxUrl +
				'?action=jwpm_export_custom_orders&nonce=' +
				encodeURIComponent(jwpmCustomOrdersConfig.exportNonce);
			window.open(url, '_blank');
		};

		JWPMCustomOrdersPage.prototype.createDemoOrders = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_custom_orders_demo_create', {
				nonce: jwpmCustomOrdersConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Demo Custom Orders نہیں بن سکے۔'
						);
						return;
					}
					notifySuccess(
						jwpmCustomOrdersConfig.strings.demoCreateSuccess ||
							'Demo Custom Orders بنا دیے گئے۔'
					);
					self.loadOrders();
				})
				.fail(function () {
					notifyError('Demo Custom Orders نہیں بن سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomOrdersPage.prototype.clearDemoOrders = function () {
			var self = this;

			this.setLoading(true);

			ajaxRequest('jwpm_custom_orders_demo_clear', {
				nonce: jwpmCustomOrdersConfig.demoNonce
			})
				.done(function (response) {
					if (!response || !response.success) {
						notifyError(
							(response && response.data && response.data.message) ||
								'Demo Custom Orders حذف نہیں ہو سکے۔'
						);
						return;
					}
					notifySuccess(
						jwpmCustomOrdersConfig.strings.demoClearSuccess ||
							'Demo Custom Orders حذف ہو گئے۔'
					);
					self.loadOrders();
				})
				.fail(function () {
					notifyError('Demo Custom Orders حذف نہیں ہو سکے۔');
				})
				.always(function () {
					self.setLoading(false);
				});
		};

		JWPMCustomOrdersPage.prototype.printOrders = function () {
			var $table = this.$layout.find('.jwpm-table-custom-orders').first();
			if (!$table.length) {
				notifyError('پرنٹ کیلئے کوئی جدول نہیں ملا۔');
				return;
			}

			var html = '<html><head><title>Custom Orders List</title>';
			html +=
				'<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:12px;color:#000;padding:16px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:4px 6px;text-align:left;} th{background:#eee;} .jwpm-status-badge{font-weight:bold;}</style>';
			html += '</head><body>';
			html += '<h2>Custom / Design Orders</h2>';
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

		return JWPMCustomOrdersPage;
	})();

	/**
	 * DOM Ready — Root mount
	 */
	$(function () {
		var $root = $('#jwpm-custom-orders-root').first();

		if (!$root.length) {
			if (window.console) {
				console.warn(
					'JWPM Custom Orders: #jwpm-custom-orders-root نہیں ملا، شاید یہ صحیح ایڈمن پیج نہیں۔'
				);
			}
			return;
		}

		try {
			new JWPMCustomOrdersPage($root);
		} catch (e) {
			console.error('JWPM Custom Orders init error:', e);
			notifyError('Custom Orders Page لوڈ کرتے وقت مسئلہ آیا۔');
		}
	});

	// 🔴 یہاں پر [JWPM Custom Orders Module] ختم ہو رہا ہے
})(jQuery);

// ✅ Syntax verified block end

