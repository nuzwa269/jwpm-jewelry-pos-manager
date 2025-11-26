/** Part 1 — JWPM Repair Jobs Page Script (UI + AJAX)
 * یہاں Repair Jobs / Workshop Repairs پیج کا مکمل (JavaScript) behaviour ہے۔
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
		importNonce: '',
		exportNonce: '',
		demoNonce: '',
		strings: {
			loading: 'Repair Jobs لوڈ ہو رہے ہیں…',
			saving: 'ڈیٹا محفوظ ہو رہا ہے…',
			saveSuccess: 'Repair job محفوظ ہو گیا۔',
			saveError: 'محفوظ کرتے وقت مسئلہ آیا، دوبارہ کوشش کریں۔',
			deleteConfirm: 'کیا آپ واقعی اس Repair job کو cancel کرنا چاہتے ہیں؟',
			deleteSuccess: 'Repair job cancel / update ہو گیا۔',
			importSuccess: 'Import مکمل ہو گیا۔',
			importError: 'Import کے دوران مسئلہ آیا۔',
			demoCreateSuccess: 'Demo Repairs بنا دیے گئے۔',
			demoClearSuccess: 'Demo Repairs حذف ہو گئے۔',
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

			this.templates = {
				layout: document.getElementById('jwpm-repair-layout-template'),
				row: document.getElementById('jwpm-repair-row-template'),
				panel: document.getElementById('jwpm-repair-panel-template'),
				importModal: document.getElementById('jwpm-repair-import-template')
			};

			this.init();
		}

		JWPMRepairPage.prototype.init = function () {
			if (!this.templates.layout) {
				notifyError('Repair Jobs layout template نہیں ملا۔');
				return;
			}

			this.renderLayout();
			this.cacheElements();
			this.bindEvents();
			this.loadRepairs();
		};

		JWPMRepairPage.prototype.renderLayout = function () {
			var tmpl = this.templates.layout.content
				? this.templates.layout.content.cloneNode(true)
				: document.importNode(this.templates.layout, true);

			this.$root.empty().append(tmpl);
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
			this.$layout.on('input', '[data-jwpm-repair-filter="search"]', function () {
				self.state.filters.search = $(this).val();
				self.state.page = 1;
				self.loadRepairs();
			});

			this.$layout.on('change', '[data-jwpm-repair-filter="status"]', function () {
				self.state.filters.status = $(this).val();
				self.state.page = 1;
				self.loadRepairs();
			});

			this.$layout.on('change', '[data-jwpm-repair-filter="priority"]', function () {
				self.state.filters.priority = $(this).val();
				self.state.page = 1;
				self.loadRepairs();
			});

			this.$layout.on('change', '[data-jwpm-repair-filter="date_from"]', function () {
				self.state.filters.date_from = $(this).val();
				self.state.page = 1;
				self.loadRepairs();
			});

			this.$layout.on('change', '[data-jwpm-repair-filter="date_to"]', function () {
				self.state.filters.date_to = $(this).val();
				self.state.page = 1;
				self.loadRepairs();
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

			if (!this.templates.row) {
				notifyError('Repair row template نہیں ملا۔');
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

				$tr.attr('data-jwpm-repair-row', '').attr('data-id', item.id);

				$tr
					.find('[data-jwpm-repair-field="job_code"]')
					.text(item.job_code || '');
				$tr
					.find('[data-jwpm-repair-field="tag_no"]')
					.text(item.tag_no || '');
				$tr
					.find('[data-jwpm-repair-field="customer_name"]')
					.text(item.customer_name || '');
				$tr
					.find('[data-jwpm-repair-field="customer_phone"]')
					.text(item.customer_phone || '');
				$tr
					.find('[data-jwpm-repair-field="item_description"]')
					.text(item.item_description || '');
				$tr
					.find('[data-jwpm-repair-field="job_type"]')
					.text(item.job_type || '');
				$tr
					.find('[data-jwpm-repair-field="promised_date"]')
					.text(item.promised_date || '');
				$tr
					.find('[data-jwpm-repair-field="actual_charges"]')
					.text(formatAmount(item.actual_charges));
				$tr
					.find('[data-jwpm-repair-field="balance_amount"]')
					.text(formatAmount(item.balance_amount));

				// Status badge
				var status = item.job_status || 'received';
				var $statusBadge = $tr.find(
					'[data-jwpm-repair-field="status_badge"]'
				);
				$statusBadge
					.attr('data-status', status)
					.addClass('jwpm-status-badge')
					.text(
						status === 'in_workshop'
							? 'In Workshop'
							: status === 'ready'
							? 'Ready'
							: status === 'delivered'
							? 'Delivered'
							: status === 'cancelled'
							? 'Cancelled'
							: 'Received'
					);

				// Priority badge
				var priority = item.priority || 'normal';
				var $priorityBadge = $tr.find(
					'[data-jwpm-repair-field="priority_badge"]'
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

			if (!this.templates.panel) {
				notifyError('Repair panel template نہیں ملا۔');
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
			var $form = $panel.find('[data-jwpm-repair-form]').first();
			var $title = $panel.find('[data-jwpm-repair-panel-title]').first();
			var $subtitle = $panel
				.find('[data-jwpm-repair-panel-subtitle]')
				.first();
			var $statusBadge = $panel
				.find('[data-jwpm-repair-panel-status]')
				.first();
			var $priorityBadge = $panel
				.find('[data-jwpm-repair-panel-priority]')
				.first();
			var $tagBadge = $panel
				.find('[data-jwpm-repair-panel-tag]')
				.first();

			// Tabs
			$panel.on('click', '.jwpm-tab', function () {
				var tab = $(this).attr('data-jwpm-repair-tab');
				if (!tab) return;

				$panel.find('.jwpm-tab').removeClass('is-active');
				$(this).addClass('is-active');

				$panel.find('.jwpm-tab-panel').removeClass('is-active');
				$panel
					.find('[data-jwpm-repair-tab-panel="' + tab + '"]')
					.addClass('is-active');
			});

			// Close actions
			$panel.on('click', '[data-jwpm-repair-action="close-panel"]', this.closeSidePanel.bind(this));

			// Save repair
			$panel.on('click', '[data-jwpm-repair-action="save-repair"]', function (e) {
				e.preventDefault();
				self.saveRepair($form);
			});

			// Auto balance calc (actual_charges - advance_amount)
			$panel.on(
				'input',
				'[data-jwpm-repair-input="actual_charges"], [data-jwpm-repair-input="advance_amount"]',
				function () {
					self.recalculateAmounts($form);
				}
			);

			// Timeline: add log
			$panel.on('click', '[data-jwpm-repair-action="add-log"]', function (e) {
				e.preventDefault();
				if (!self.state.currentRepairId && !id) {
					notifyInfo('پہلے Repair job محفوظ کریں، پھر Timeline update کریں۔');
					return;
				}
				var repairId = self.state.currentRepairId || id;
				self.saveRepairLog(repairId);
			});

			// New repair vs existing
			if (!id) {
				this.state.currentRepairId = null;
				$title.text('New Repair Job');
				$subtitle.text('');
				$statusBadge
					.text('Received')
					.attr('data-status', 'received')
					.addClass('jwpm-status-badge');
				$priorityBadge
					.text('Normal')
					.attr('data-priority', 'normal')
					.addClass('jwpm-priority-badge');
				$tagBadge.text('').attr('data-tag', '');

				if ($form.length && $form[0]) {
					$form[0].reset();
				}
				$form.find('[data-jwpm-repair-input="id"]').val('');
				this.recalculateAmounts($form);
				this.renderLogs([]);
			} else {
				this.state.currentRepairId = id;
				this.loadRepairIntoPanel(id, $panel, $form, $title, $subtitle, $statusBadge, $priorityBadge, $tagBadge);
			}
		};

		JWPMRepairPage.prototype.closeSidePanel = function () {
			this.$sidePanel.prop('hidden', true).empty();
		};

		JWPMRepairPage.prototype.loadRepairIntoPanel = function (
			id,
			$panel,
			$form,
			$title,
			$subtitle,
			$statusBadge,
			$priorityBadge,
			$tagBadge
		) {
			var self = this;

			$title.text('Loading…');
			$subtitle.text('');

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
					$subtitle.text(
						(header.customer_name || '') +
							(header.customer_phone ? ' • ' + header.customer_phone : '')
					);

					$tagBadge
						.text(header.tag_no || '')
						.attr('data-tag', header.tag_no || '');

					var st = header.job_status || 'received';
					$statusBadge
						.text(
							st === 'in_workshop'
								? 'In Workshop'
								: st === 'ready'
								? 'Ready'
								: st === 'delivered'
								? 'Delivered'
								: st === 'cancelled'
								? 'Cancelled'
								: 'Received'
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
			if (balance < 0) balance = 0;

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

			var data = this.serializeForm($form);
			data.nonce = jwpmRepairConfig.mainNonce;

			if (!data.customer_name && !data.customer_phone) {
				notifyError('Customer کا نام یا فون نمبر درج کرنا ضروری ہے۔');
				return;
			}

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

					if (response.data && response.data.id) {
						self.state.currentRepairId = parseInt(response.data.id, 10) || null;
					}

					self.closeSidePanel();
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
			var $form = $modal.find('[data-jwpm-repair-import-form]').first();
			var $result = $modal
				.find('[data-jwpm-repair-import-result]')
				.first();

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

				var skipDup = $form
					.find('input[name="skip_duplicates"]')
					.is(':checked')
					? 1
					: 0;
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

			var jobCode = $row.find('[data-jwpm-repair-field="job_code"]').text() || '';
			var tagNo = $row.find('[data-jwpm-repair-field="tag_no"]').text() || '';
			var customer = $row.find('[data-jwpm-repair-field="customer_name"]').text() || '';
			var phone = $row.find('[data-jwpm-repair-field="customer_phone"]').text() || '';
			var item = $row.find('[data-jwpm-repair-field="item_description"]').text() || '';
			var jobType = $row.find('[data-jwpm-repair-field="job_type"]').text() || '';
			var promised = $row.find('[data-jwpm-repair-field="promised_date"]').text() || '';
			var charges = $row.find('[data-jwpm-repair-field="actual_charges"]').text() || '';
			var advance = ''; // detail کیلئے future version میں header load کر سکتے ہیں

			var html = '<html><head><title>Repair Ticket</title>';
			html +=
				'<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:11px;color:#000;padding:12px;} table{width:100%;border-collapse:collapse;margin-bottom:8px;} td{padding:3px 4px;vertical-align:top;} .label{width:30%;font-weight:bold;} .title{font-size:14px;font-weight:bold;margin-bottom:6px;text-align:center;} .small{font-size:10px;color:#555;} .border{border:1px solid #000;padding:6px;}</style>';
			html += '</head><body>';
			html += '<div class="border">';
			html += '<div class="title">Repair Job Ticket</div>';
			html += '<table>';
			html += '<tr><td class="label">Job Code</td><td>' + jobCode + '</td></tr>';
			html += '<tr><td class="label">Tag No</td><td>' + tagNo + '</td></tr>';
			html += '<tr><td class="label">Customer</td><td>' + customer + '</td></tr>';
			html += '<tr><td class="label">Phone</td><td>' + phone + '</td></tr>';
			html += '<tr><td class="label">Item</td><td>' + item + '</td></tr>';
			html += '<tr><td class="label">Job Type</td><td>' + jobType + '</td></tr>';
			html += '<tr><td class="label">Promised Date</td><td>' + promised + '</td></tr>';
			html += '<tr><td class="label">Charges</td><td>' + charges + '</td></tr>';
			html += '<tr><td class="label">Advance</td><td>' + advance + '</td></tr>';
			html += '</table>';
			html += '<table>';
			html += '<tr><td>Customer Signature: ____________________</td></tr>';
			html += '<tr><td>Received By: _________________________</td></tr>';
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

