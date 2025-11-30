/**
 * JWPM Custom Orders - Admin Page JS
 *
 * یہ فائل (Custom Orders) پیج پر:
 * - لسٹ لوڈ، فلٹر، پیجینیشن
 * - Add / Edit / Delete
 * - Import / Export / Demo Data
 * - Excel Download + Print
 * کو (AJAX) کے ذریعے ہینڈل کرتی ہے۔
 */

(function (window, document) {
	'use strict';

	// 🟢 Config / Safety Checks
	var CONFIG = window.JWPM_CUSTOM_ORDERS_CONFIG || {};
	if (!CONFIG.ajax_url) {
		console.warn('JWPM Custom Orders: ajax_url missing in CONFIG.');
		return;
	}

	var root = document.getElementById('jwpm-custom-orders-root');
	if (!root) {
		console.warn('JWPM Custom Orders: root container #jwpm-custom-orders-root not found.');
		return;
	}

	// Capability
	var CAN_MANAGE = !!(CONFIG.capabilities && CONFIG.capabilities.can_manage);

	// 🟢 DOM Cache
	var tableBody = root.querySelector('.jwpm-co-table-body');
	var emptyRow  = tableBody ? tableBody.querySelector('.jwpm-co-table-empty') : null;
	var totalCountEl = root.querySelector('.jwpm-co-total-count');
	var currentPageEl = root.querySelector('.jwpm-co-current-page');
	var totalPagesEl  = root.querySelector('.jwpm-co-total-pages');

	var btnAdd        = root.querySelector('.jwpm-co-btn-add');
	var btnImport     = root.querySelector('.jwpm-co-btn-import');
	var btnExport     = root.querySelector('.jwpm-co-btn-export');
	var btnDemoCreate = root.querySelector('.jwpm-co-btn-demo-create');
	var btnDemoDelete = root.querySelector('.jwpm-co-btn-demo-delete');
	var btnExcel      = root.querySelector('.jwpm-co-btn-excel');
	var btnPrint      = root.querySelector('.jwpm-co-btn-print');

	var filterSearch = root.querySelector('#jwpm-co-filter-search');
	var filterStatus = root.querySelector('#jwpm-co-filter-status');
	var filterBranch = root.querySelector('#jwpm-co-filter-branch');
	var filterDateFrom = root.querySelector('#jwpm-co-filter-date-from');
	var filterDateTo   = root.querySelector('#jwpm-co-filter-date-to');
	var btnApplyFilters = root.querySelector('.jwpm-co-btn-apply-filters');
	var btnResetFilters = root.querySelector('.jwpm-co-btn-reset-filters');

	var btnPagePrev = root.querySelector('.jwpm-co-page-prev');
	var btnPageNext = root.querySelector('.jwpm-co-page-next');

	// Modals & UI
	var modalForm       = root.querySelector('.jwpm-co-modal-form');
	var modalImport     = root.querySelector('.jwpm-co-modal-import');
	var loadingIndicator = root.querySelector('.jwpm-co-loading-indicator');
	var toastSuccess     = root.querySelector('.jwpm-co-toast-success');
	var toastError       = root.querySelector('.jwpm-co-toast-error');

	// Form
	var formEl           = modalForm ? modalForm.querySelector('.jwpm-co-form') : null;
	var fieldId          = formEl ? formEl.querySelector('.jwpm-co-field-id') : null;
	var fieldCustomerName  = formEl ? formEl.querySelector('#jwpm-co-customer-name') : null;
	var fieldCustomerPhone = formEl ? formEl.querySelector('#jwpm-co-customer-phone') : null;
	var fieldDesignRef     = formEl ? formEl.querySelector('#jwpm-co-design-ref') : null;
	var fieldEstimateWeight = formEl ? formEl.querySelector('#jwpm-co-estimate-weight') : null;
	var fieldEstimateAmount = formEl ? formEl.querySelector('#jwpm-co-estimate-amount') : null;
	var fieldAdvanceAmount  = formEl ? formEl.querySelector('#jwpm-co-advance-amount') : null;
	var fieldStatus         = formEl ? formEl.querySelector('#jwpm-co-status') : null;
	var fieldDueDate        = formEl ? formEl.querySelector('#jwpm-co-due-date') : null;
	var fieldNotes          = formEl ? formEl.querySelector('#jwpm-co-notes') : null;

	var btnFormCancel  = formEl ? formEl.querySelector('.jwpm-co-btn-cancel') : null;

	// Import
	var importFileInput   = modalImport ? modalImport.querySelector('.jwpm-co-import-file') : null;
	var btnImportConfirm  = modalImport ? modalImport.querySelector('.jwpm-co-btn-import-confirm') : null;
	var btnImportCancel   = modalImport ? modalImport.querySelector('.jwpm-co-btn-import-cancel') : null;

	// Template
	var rowTemplate = document.getElementById('jwpm-co-row-template');

	// State
	var state = {
		page: 1,
		perPage: 20,
		totalPages: 1,
		isLoading: false
	};

	// 🟢 Helpers

	function showLoading() {
		state.isLoading = true;
		if (loadingIndicator) {
			loadingIndicator.setAttribute('aria-hidden', 'false');
			loadingIndicator.classList.add('is-active');
		}
	}

	function hideLoading() {
		state.isLoading = false;
		if (loadingIndicator) {
			loadingIndicator.setAttribute('aria-hidden', 'true');
			loadingIndicator.classList.remove('is-active');
		}
	}

	function showToastSuccess(message) {
		if (!toastSuccess) return;
		toastSuccess.textContent = message || 'کامیابی۔';
		toastSuccess.setAttribute('aria-hidden', 'false');
		toastSuccess.classList.add('is-visible');

		window.setTimeout(function () {
			toastSuccess.classList.remove('is-visible');
			toastSuccess.setAttribute('aria-hidden', 'true');
		}, 4000);
	}

	function showToastError(message) {
		if (!toastError) return;
		toastError.textContent = message || 'ایک خرابی پیدا ہو گئی ہے۔';
		toastError.setAttribute('aria-hidden', 'false');
		toastError.classList.add('is-visible');

		window.setTimeout(function () {
			toastError.classList.remove('is-visible');
			toastError.setAttribute('aria-hidden', 'true');
		}, 6000);
	}

	function safeGet(obj, key, fallback) {
		if (!obj || typeof obj[key] === 'undefined' || obj[key] === null) {
			return fallback;
		}
		return obj[key];
	}

	function openModal(modal) {
		if (!modal) return;
		modal.setAttribute('aria-hidden', 'false');
		modal.classList.add('is-open');
	}

	function closeModal(modal) {
		if (!modal) return;
		modal.setAttribute('aria-hidden', 'true');
		modal.classList.remove('is-open');
	}

	function resetForm() {
		if (!formEl) return;
		formEl.reset();
		if (fieldId) fieldId.value = '';
	}

	function buildStatusLabel(status) {
		switch (status) {
			case 'designing':    return 'Designing';
			case 'in_progress':  return 'In Progress';
			case 'ready':        return 'Ready';
			case 'delivered':    return 'Delivered';
			case 'cancelled':    return 'Cancelled';
			default:             return status || '';
		}
	}

	function formatNumber(value, decimals) {
		var num = parseFloat(value || 0);
		if (isNaN(num)) {
			return '0';
		}
		return num.toFixed(typeof decimals === 'number' ? decimals : 2);
	}

	function getFilters() {
		return {
			search: filterSearch ? filterSearch.value.trim() : '',
			status: filterStatus ? filterStatus.value : '',
			branch_id: filterBranch ? filterBranch.value : '0',
			date_from: filterDateFrom ? filterDateFrom.value : '',
			date_to: filterDateTo ? filterDateTo.value : ''
		};
	}

	/**
	 * عمومی AJAX POST helper (JSON response expected)
	 * security → nonce_main
	 */
	function ajaxPost(action, data) {
		data = data || {};
		data.action = action;
		data.security = CONFIG.nonce_main;

		return fetch(CONFIG.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: new URLSearchParams(data).toString()
		}).then(function (res) {
			if (!res.ok) {
				throw new Error('HTTP ' + res.status);
			}
			return res.json();
		});
	}

	/**
	 * Import / Export / دوسرے nonce کے لیے helper
	 */
	function ajaxPostWithNonce(action, data, nonceKey, nonceValue) {
		data = data || {};
		data.action = action;
		data[nonceKey || 'nonce'] = nonceValue;

		return fetch(CONFIG.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams(data).toString(),
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			}
		}).then(function (res) {
			if (!res.ok) {
				throw new Error('HTTP ' + res.status);
			}
			return res.json();
		});
	}

	// 🟢 Rendering

	function clearTable() {
		if (!tableBody) return;
		while (tableBody.firstChild) {
			tableBody.removeChild(tableBody.firstChild);
		}
	}

	function renderEmptyRow() {
		if (!tableBody) return;
		if (emptyRow) {
			var clone = emptyRow.cloneNode(true);
			tableBody.appendChild(clone);
		} else {
			var tr = document.createElement('tr');
			var td = document.createElement('td');
			td.colSpan = 10;
			td.textContent = 'ابھی کوئی Custom Order موجود نہیں ہے۔';
			tr.appendChild(td);
			tableBody.appendChild(tr);
		}
	}

	function renderRow(item) {
		if (!rowTemplate || !tableBody) return;
		var html = rowTemplate.innerHTML;

		var safeItem = {
			id: safeGet(item, 'id', ''),
			order_code: safeGet(item, 'order_code', ''),
			customer_name: safeGet(item, 'customer_name', ''),
			customer_phone: safeGet(item, 'customer_phone', ''),
			design_reference: safeGet(item, 'design_reference', ''),
			estimate_weight: formatNumber(safeGet(item, 'estimate_weight', 0), 3),
			estimate_amount: formatNumber(safeGet(item, 'estimate_amount', 0), 2),
			advance_amount: formatNumber(safeGet(item, 'advance_amount', 0), 2),
			status: safeGet(item, 'status', ''),
			status_label: buildStatusLabel(safeGet(item, 'status', '')),
			due_date: safeGet(item, 'due_date', '')
		};

		Object.keys(safeItem).forEach(function (key) {
			var placeholder = '{{' + key + '}}';
			html = html.split(placeholder).join(String(safeItem[key]));
		});

		var temp = document.createElement('tbody');
		temp.innerHTML = html.trim();
		var row = temp.firstElementChild;
		if (row) {
			tableBody.appendChild(row);
		}
	}

	function renderList(items, pagination) {
		if (!tableBody) return;
		clearTable();

		if (!items || !items.length) {
			renderEmptyRow();
		} else {
			items.forEach(function (item) {
				renderRow(item);
			});
		}

		if (totalCountEl) {
			totalCountEl.textContent = (pagination && typeof pagination.total !== 'undefined')
				? pagination.total
				: (items ? items.length : 0);
		}

		state.page = (pagination && pagination.page) ? pagination.page : state.page;
		state.totalPages = (pagination && pagination.total_pages) ? pagination.total_pages : 1;

		if (currentPageEl) currentPageEl.textContent = state.page;
		if (totalPagesEl) totalPagesEl.textContent = state.totalPages;

		if (btnPagePrev) {
			btnPagePrev.disabled = state.page <= 1;
		}
		if (btnPageNext) {
			btnPageNext.disabled = state.page >= state.totalPages;
		}
	}

	// 🟢 Data Loading

	function loadList(page) {
		if (!tableBody) {
			return;
		}

		if (typeof page === 'number' && page > 0) {
			state.page = page;
		}

		var filters = getFilters();

		showLoading();

		var payload = {
			page: state.page,
			per_page: state.perPage,
			search: filters.search,
			status: filters.status,
			branch_id: filters.branch_id,
			date_from: filters.date_from,
			date_to: filters.date_to
		};

		// Backend action: jwpm_custom_orders_fetch
		ajaxPost('jwpm_custom_orders_fetch', payload)
			.then(function (res) {
				if (!res || !res.success) {
					var msg = (res && res.data && res.data.message) ? res.data.message : 'لسٹ لوڈ نہیں ہو سکی۔';
					showToastError(msg);
					return;
				}

				var data = res.data || {};
				var items = data.items || [];
				var pagination = data.pagination || {
					total: data.total || items.length,
					page: data.page || state.page,
					per_page: data.per_page || state.perPage,
					total_pages: data.total_pages || 1
				};

				renderList(items, pagination);
			})
			.catch(function () {
				showToastError('کچھ خرابی کی وجہ سے لسٹ لوڈ نہیں ہو سکی۔');
			})
			.finally(function () {
				hideLoading();
			});
	}

	// 🟢 Form Handling

	function openFormForCreate() {
		if (!CAN_MANAGE) {
			showToastError('آپ کے پاس نیا Custom Order بنانے کی اجازت نہیں ہے۔');
			return;
		}
		resetForm();
		if (fieldStatus) {
			fieldStatus.value = 'designing';
		}
		openModal(modalForm);
	}

	function openFormForEdit(rowEl) {
		if (!rowEl || !CAN_MANAGE) {
			return;
		}
		var id = rowEl.getAttribute('data-id') || '';
		if (!id) return;

		// اگر backend میں single fetch endpoint الگ ہو گا تو یہاں call کریں،
		// فی الحال row سے ہی values نکالتے ہیں (simple mode).
		resetForm();
		if (fieldId) fieldId.value = id;

		var getText = function (selector) {
			var cell = rowEl.querySelector(selector);
			return cell ? cell.textContent.trim() : '';
		};

		if (fieldCustomerName)  fieldCustomerName.value  = getText('.column-customer-name');
		if (fieldCustomerPhone) fieldCustomerPhone.value = getText('.column-customer-phone');
		if (fieldDesignRef)     fieldDesignRef.value     = getText('.column-design-ref');
		if (fieldEstimateWeight) fieldEstimateWeight.value = getText('.column-estimate-weight');
		if (fieldEstimateAmount) fieldEstimateAmount.value = getText('.column-estimate-amount');
		if (fieldAdvanceAmount)  fieldAdvanceAmount.value  = getText('.column-advance-amount');
		if (fieldDueDate)        fieldDueDate.value        = getText('.column-due-date');

		// status badge پر class ہے jwpm-co-status-{{status}}
		var statusBadge = rowEl.querySelector('.jwpm-co-status-badge');
		if (statusBadge && fieldStatus) {
			var classList = Array.prototype.slice.call(statusBadge.classList);
			var status = 'designing';
			classList.forEach(function (cls) {
				if (cls.indexOf('jwpm-co-status-') === 0) {
					status = cls.replace('jwpm-co-status-', '');
				}
			});
			fieldStatus.value = status;
		}

		openModal(modalForm);
	}

	function handleFormSubmit(event) {
		if (!formEl) return;
		event.preventDefault();

		if (!CAN_MANAGE) {
			showToastError('آپ کے پاس محفوظ کرنے کی اجازت نہیں ہے۔');
			return;
		}

		var formData = new FormData(formEl);

		var payload = {
			id: formData.get('id') || '',
			customer_name: formData.get('customer_name') || '',
			customer_phone: formData.get('customer_phone') || '',
			design_reference: formData.get('design_reference') || '',
			estimate_weight: formData.get('estimate_weight') || '',
			estimate_amount: formData.get('estimate_amount') || '',
			advance_amount: formData.get('advance_amount') || '',
			status: formData.get('status') || '',
			due_date: formData.get('due_date') || '',
			notes: formData.get('notes') || ''
		};

		if (!payload.customer_name || !payload.customer_phone) {
			showToastError('کسٹمر نام اور فون نمبر لازمی ہیں۔');
			return;
		}

		showLoading();

		// Backend action: jwpm_custom_orders_save
		ajaxPost('jwpm_custom_orders_save', payload)
			.then(function (res) {
				if (!res || !res.success) {
					var msg = (res && res.data && res.data.message) ? res.data.message : 'محفوظ نہیں ہو سکا۔';
					showToastError(msg);
					return;
				}
				showToastSuccess('Custom Order کامیابی سے محفوظ ہو گیا۔');
				closeModal(modalForm);
				loadList(state.page);
			})
			.catch(function () {
				showToastError('محفوظ کرتے وقت خرابی ہوئی۔');
			})
			.finally(function () {
				hideLoading();
			});
	}

	function handleRowActionClick(event) {
		var target = event.target;
		if (!target || !tableBody) return;

		if (target.classList.contains('jwpm-co-action-edit')) {
			var rowEl = target.closest('tr.jwpm-co-row');
			if (!rowEl) return;
			openFormForEdit(rowEl);
		}

		if (target.classList.contains('jwpm-co-action-delete')) {
			if (!CAN_MANAGE) {
				showToastError('آپ کے پاس حذف کرنے کی اجازت نہیں ہے۔');
				return;
			}
			var row = target.closest('tr.jwpm-co-row');
			if (!row) return;
			var id = row.getAttribute('data-id') || '';
			if (!id) return;

			if (!window.confirm('کیا آپ واقعی اس Custom Order کو حذف کرنا چاہتے ہیں؟')) {
				return;
			}

			showLoading();
			// Backend action: jwpm_custom_orders_delete
			ajaxPost('jwpm_custom_orders_delete', { id: id })
				.then(function (res) {
					if (!res || !res.success) {
						var msg = (res && res.data && res.data.message) ? res.data.message : 'حذف نہیں ہو سکا۔';
						showToastError(msg);
						return;
					}
					showToastSuccess('ریکارڈ حذف کر دیا گیا۔');
					loadList(state.page);
				})
				.catch(function () {
					showToastError('حذف کرتے وقت خرابی ہوئی۔');
				})
				.finally(function () {
					hideLoading();
				});
		}
	}

	// 🟢 Import / Export / Demo / Excel / Print

	function openImportModal() {
		if (!CAN_MANAGE) {
			showToastError('آپ کے پاس Import کی اجازت نہیں ہے۔');
			return;
		}
		if (importFileInput) {
			importFileInput.value = '';
		}
		openModal(modalImport);
	}

	function handleImportConfirm() {
		if (!modalImport || !importFileInput) return;
		if (!CAN_MANAGE) {
			showToastError('آپ کے پاس Import کی اجازت نہیں ہے۔');
			return;
		}

		var file = importFileInput.files && importFileInput.files[0];
		if (!file) {
			showToastError('براہ کرم پہلے فائل منتخب کریں۔');
			return;
		}

		var formData = new FormData();
		formData.append('action', 'jwpm_custom_orders_import');
		formData.append('nonce', CONFIG.nonce_import || '');
		formData.append('file', file);

		showLoading();

		fetch(CONFIG.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
			.then(function (res) {
				if (!res.ok) {
					throw new Error('HTTP ' + res.status);
				}
				return res.json();
			})
			.then(function (res) {
				if (!res || !res.success) {
					var msg = (res && res.data && res.data.message) ? res.data.message : 'Import مکمل نہیں ہو سکا۔';
					showToastError(msg);
					return;
				}
				showToastSuccess('Import کامیابی سے مکمل ہو گیا۔');
				closeModal(modalImport);
				loadList(1);
			})
			.catch(function () {
				showToastError('Import کے دوران خرابی ہوئی۔');
			})
			.finally(function () {
				hideLoading();
			});
	}

	function handleExport() {
		// Backend: jwpm_custom_orders_export → JSON/CSV/Excel (server side)
		var url = CONFIG.ajax_url +
			'?action=jwpm_custom_orders_export' +
			'&nonce=' + encodeURIComponent(CONFIG.nonce_export || '');

		window.location.href = url;
	}

	function handleDemo(mode) {
		if (!CAN_MANAGE) {
			showToastError('آپ کے پاس Demo Data مینیج کرنے کی اجازت نہیں ہے۔');
			return;
		}

		showLoading();

		ajaxPostWithNonce(
			'jwpm_custom_orders_demo',
			{ mode: mode || 'create' },
			'security',
			CONFIG.nonce_main
		)
			.then(function (res) {
				if (!res || !res.success) {
					var msg = (res && res.data && res.data.message) ? res.data.message : 'Demo action مکمل نہیں ہو سکا۔';
					showToastError(msg);
					return;
				}
				var msgOk = (res.data && res.data.message)
					? res.data.message
					: (mode === 'delete' ? 'Demo Data حذف کر دیا گیا۔' : 'Demo Data بنا دیا گیا۔');
				showToastSuccess(msgOk);
				loadList(1);
			})
			.catch(function () {
				showToastError('Demo Data action کے دوران خرابی ہوئی۔');
			})
			.finally(function () {
				hideLoading();
			});
	}

	function handleExcelDownload() {
		// Excel بھی Export جیسا ہی ہے، فرق صرف format parameter کا ہے (backend میں handle کرنا ہوگا)
		var url = CONFIG.ajax_url +
			'?action=jwpm_custom_orders_export' +
			'&nonce=' + encodeURIComponent(CONFIG.nonce_export || '') +
			'&format=excel';

		window.location.href = url;
	}

	function handlePrint() {
		// سادہ print: table HTML کو نئے window میں بھیج کر print
		if (!tableBody) {
			window.print();
			return;
		}
		var table = root.querySelector('.jwpm-co-table');
		if (!table) {
			window.print();
			return;
		}

		var printWindow = window.open('', 'jwpm_co_print');
		if (!printWindow) {
			window.print();
			return;
		}

		var doc = printWindow.document;
		doc.open();
		doc.write('<html><head><title>Custom Orders</title>');
		// تھوڑا سا basic style
		doc.write('<style>table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ccc;padding:4px;font-size:12px;text-align:left;}</style>');
		doc.write('</head><body>');
		doc.write('<h2>Custom Orders</h2>');
		doc.write(table.outerHTML);
		doc.write('</body></html>');
		doc.close();

		printWindow.focus();
		printWindow.print();
	}

	// 🟢 Filters / Pagination

	function handleApplyFilters() {
		loadList(1);
	}

	function handleResetFilters() {
		if (filterSearch) filterSearch.value = '';
		if (filterStatus) filterStatus.value = '';
		if (filterBranch) filterBranch.value = '0';
		if (filterDateFrom) filterDateFrom.value = '';
		if (filterDateTo) filterDateTo.value = '';
		loadList(1);
	}

	function handlePrevPage() {
		if (state.page > 1) {
			loadList(state.page - 1);
		}
	}

	function handleNextPage() {
		if (state.page < state.totalPages) {
			loadList(state.page + 1);
		}
	}

	// 🟢 Event Bindings

	function bindEvents() {
		if (btnAdd) {
			btnAdd.addEventListener('click', openFormForCreate);
		}
		if (formEl) {
			formEl.addEventListener('submit', handleFormSubmit);
		}
		if (btnFormCancel) {
			btnFormCancel.addEventListener('click', function () {
				closeModal(modalForm);
			});
		}

		// Modal close buttons (×)
		root.addEventListener('click', function (event) {
			var target = event.target;
			if (!target) return;

			// Close icons
			if (target.classList.contains('jwpm-co-modal-close')) {
				var modal = target.closest('.jwpm-co-modal');
				closeModal(modal);
			}

			// Backdrop click
			if (target.classList.contains('jwpm-co-modal-backdrop')) {
				var parentModal = target.closest('.jwpm-co-modal');
				closeModal(parentModal);
			}
		});

		// Table row actions
		if (tableBody) {
			tableBody.addEventListener('click', handleRowActionClick);
		}

		// Import / Export / Demo / Excel / Print
		if (btnImport) {
			btnImport.addEventListener('click', openImportModal);
		}
		if (btnImportConfirm) {
			btnImportConfirm.addEventListener('click', handleImportConfirm);
		}
		if (btnImportCancel) {
			btnImportCancel.addEventListener('click', function () {
				closeModal(modalImport);
			});
		}
		if (btnExport) {
			btnExport.addEventListener('click', handleExport);
		}
		if (btnDemoCreate) {
			btnDemoCreate.addEventListener('click', function () {
				handleDemo('create');
			});
		}
		if (btnDemoDelete) {
			btnDemoDelete.addEventListener('click', function () {
				if (window.confirm('کیا آپ واقعی Demo Data حذف کرنا چاہتے ہیں؟')) {
					handleDemo('delete');
				}
			});
		}
		if (btnExcel) {
			btnExcel.addEventListener('click', handleExcelDownload);
		}
		if (btnPrint) {
			btnPrint.addEventListener('click', handlePrint);
		}

		// Filters
		if (btnApplyFilters) {
			btnApplyFilters.addEventListener('click', handleApplyFilters);
		}
		if (btnResetFilters) {
			btnResetFilters.addEventListener('click', handleResetFilters);
		}

		if (btnPagePrev) {
			btnPagePrev.addEventListener('click', handlePrevPage);
		}
		if (btnPageNext) {
			btnPageNext.addEventListener('click', handleNextPage);
		}

		// Search enter key → apply filters
		if (filterSearch) {
			filterSearch.addEventListener('keyup', function (event) {
				if (event.key === 'Enter') {
					handleApplyFilters();
				}
			});
		}
	}

	// 🟢 Init

	function init() {
		bindEvents();
		loadList(1);
	}

	// DOM ready check
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// ✅ Syntax verified block end
})(window, document);
