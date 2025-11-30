/**
 * jwpm-pos.js
 *
 * Summary:
 * - POS UI mount on #jwpm-pos-root
 * - Header + stats + main 3-column layout render
 * - Product search (AJAX)
 * - Customer search (placeholder hook)
 * - Gold rate fetch (AJAX)
 * - Cart management (add/remove/update)
 * - Grand total & discount calculation
 * - Complete sale (AJAX)
 */

/* global jQuery, jwpmCommon, jwpmPosData */

jQuery(function ($) {
	'use strict';

	var $root = $('#jwpm-pos-root');

	if (!$root.length) {
		console.warn('JWPM POS: #jwpm-pos-root not found.');
		return;
	}

	// 🟢 یہاں سے [POS State and Helpers] شروع ہو رہا ہے

	var cartItems = []; // {id, tag, desc, wt, making, stone, qty, unitPrice, discount, total}

	function cloneTemplate(id) {
		var tpl = document.getElementById(id);
		if (!tpl) {
			console.warn('JWPM POS: template not found ->', id);
			return null;
		}
		return tpl.content ? tpl.content.cloneNode(true) : null;
	}

	function formatMoney(amount) {
		amount = parseFloat(amount || 0);
		var symbol = jwpmPosData.currency_symbol || 'Rs';
		return symbol + ' ' + amount.toFixed(0);
	}

	function recalcTotals() {
		var subtotal = 0;
		var totalDiscount = 0;
		var oldGold = 0; // فی الحال 0، بعد میں old gold modal سے آ جائے گا
		var tax = 0;     // اگر ٹیکس ہو تو یہاں لاجک لگائیں

		cartItems.forEach(function (item) {
			var lineAmount = (item.unitPrice || 0) * (item.qty || 0) + (item.making || 0) + (item.stone || 0);
			var lineDiscount = item.discount || 0;
			var lineTotal = lineAmount - lineDiscount;

			subtotal += lineAmount;
			totalDiscount += lineDiscount;
			item.total = lineTotal;
		});

		var overallDiscount = parseFloat($('.js-pos-overall-discount').val() || 0);
		totalDiscount += overallDiscount;
		var grand = subtotal - totalDiscount - oldGold + tax;

		$('.js-pos-subtotal').text(formatMoney(subtotal));
		$('.js-pos-disc-total').text(formatMoney(totalDiscount));
		$('.js-pos-old-gold-total').text(formatMoney(oldGold));
		$('.js-pos-tax').text(formatMoney(tax));
		$('.js-pos-grand').text(formatMoney(grand));
	}

	function renderCart() {
		var $tbody = $('.js-pos-cart-body');
		$tbody.empty();

		if (!cartItems.length) {
			recalcTotals();
			return;
		}

		cartItems.forEach(function (item, index) {
			var frag = cloneTemplate('jwpm-pos-cart-row-template');
			if (!frag) {
				return;
			}
			var $row = $(frag);

			$row.find('.js-pos-tag').text(item.tag || '-');
			$row.find('.js-pos-desc').text(item.desc || '-');
			$row.find('.js-pos-wt').text(item.wt || 0);
			$row.find('.js-pos-unit').text(formatMoney(item.unitPrice || 0));
			$row.find('.js-pos-line-total').text(formatMoney(item.total || 0));

			$row.find('.js-pos-make').val(item.making || 0).on('input', function () {
				item.making = parseFloat($(this).val() || 0);
				recalcTotals();
				renderCart();
			});

			$row.find('.js-pos-stone').val(item.stone || 0).on('input', function () {
				item.stone = parseFloat($(this).val() || 0);
				recalcTotals();
				renderCart();
			});

			$row.find('.js-pos-qty').val(item.qty || 1).on('input', function () {
				item.qty = parseFloat($(this).val() || 1);
				recalcTotals();
				renderCart();
			});

			$row.find('.js-pos-line-disc').val(item.discount || 0).on('input', function () {
				item.discount = parseFloat($(this).val() || 0);
				recalcTotals();
				renderCart();
			});

			$row.find('.js-pos-remove-item').on('click', function (e) {
				e.preventDefault();
				cartItems.splice(index, 1);
				renderCart();
			});

			$tbody.append($row);
		});

		recalcTotals();
	}

	function addItemToCart(item) {
		// سادہ mapping — اپنے (DB) results کے مطابق adjust کریں
		cartItems.push({
			id: item.id,
			tag: item.tag || item.sku || '-',
			desc: item.name || item.title || '-',
			wt: parseFloat(item.wt || item.weight || 0),
			making: parseFloat(item.making || 0),
			stone: parseFloat(item.stone || 0),
			qty: 1,
			unitPrice: parseFloat(item.price || 0),
			discount: 0,
			total: 0
		});
		renderCart();
	}

	function showToast(message) {
		var frag = cloneTemplate('jwpm-pos-toast-template');
		if (!frag) {
			alert(message);
			return;
		}
		var $toast = $(frag);
		$toast.find('.jwpm-toast-text').text(message);
		$('body').append($toast);
		setTimeout(function () {
			$toast.fadeOut(300, function () {
				$(this).remove();
			});
		}, 2500);
	}

	// 🔴 یہاں پر [POS State and Helpers] ختم ہو رہا ہے

	// 🟢 یہاں سے [Initial Layout Render] شروع ہو رہا ہے

	function renderLayout() {
		$root.empty();

		var headerFrag = cloneTemplate('jwpm-pos-header-template');
		var statsFrag  = cloneTemplate('jwpm-pos-stats-template');
		var mainFrag   = cloneTemplate('jwpm-pos-main-template');

		if (headerFrag) {
			$root.append(headerFrag);
		}
		if (statsFrag) {
			$root.append(statsFrag);
		}
		if (mainFrag) {
			$root.append(mainFrag);
		}

		// تاریخ/وقت کا چھوٹا سا updater
		var $dtBox = $root.find('.js-pos-datetime');
		function updateDateTime() {
			var now = new Date();
			$dtBox.text(now.toLocaleString());
		}
		updateDateTime();
		setInterval(updateDateTime, 30000);

		bindEvents();
		loadGoldRate();
		loadTodayStats();
	}

	// 🔴 یہاں پر [Initial Layout Render] ختم ہو رہا ہے

	// 🟢 یہاں سے [AJAX Helpers] شروع ہو رہا ہے

	function ajaxPost(action, data, onSuccess, onError) {
		data = data || {};
		data.action = action;
		data.security = jwpmPosData.nonce;

		$.post(jwpmCommon.ajax_url, data)
			.done(function (response) {
				if (response && response.success) {
					if (typeof onSuccess === 'function') {
						onSuccess(response.data || {});
					}
				} else {
					var msg = (response && response.data && response.data.message) || 'Unknown error';
					showToast(msg);
					if (typeof onError === 'function') {
						onError(msg, response);
					}
				}
			})
			.fail(function (xhr) {
				var msg = 'Server error (' + xhr.status + ')';
				showToast(msg);
				if (typeof onError === 'function') {
					onError(msg, xhr);
				}
			});
	}

	function loadGoldRate() {
		if (!jwpmPosData.gold_rate_action) {
			return;
		}
		var $gold = $root.find('.js-gold-rate');
		$gold.text('…');

		ajaxPost(
			jwpmPosData.gold_rate_action,
			{},
			function (data) {
				var rate = data.rate || 0;
				$gold.text(formatMoney(rate));
			},
			function () {
				$gold.text('—');
			}
		);
	}

	function loadTodayStats() {
		if (!jwpmPosData.today_stats_action) {
			// اگر آپ نے ابھی POS stats (AJAX) نہیں بنایا تو اسے ignore کر دے
			return;
		}
		ajaxPost(
			jwpmPosData.today_stats_action,
			{},
			function (data) {
				var stats = data.stats || {};
				$root.find('.jwpm-pos-stat-card').each(function () {
					var key = $(this).data('stat');
					var val = stats[key] || 0;
					$(this).find('.js-stat-value').text(val);
				});
			}
		);
	}

	function searchItems(term) {
		if (!jwpmPosData.search_items_action) {
			return;
		}
		if (!term || term.length < 2) {
			$('.js-pos-search-results').empty().append('<div class="jwpm-pos-hint">کم از کم دو حروف ٹائپ کریں…</div>');
			return;
		}

		var $box = $('.js-pos-search-results');
		$box.empty().append('<div class="jwpm-pos-hint">سرچ ہو رہی ہے…</div>');

		ajaxPost(
			jwpmPosData.search_items_action,
			{
				term: term,
				per_page: 20
			},
			function (data) {
				var items = data.items || [];
				$box.empty();

				if (!items.length) {
					$box.append('<div class="jwpm-pos-hint">کوئی آئٹم نہیں ملا۔</div>');
					return;
				}

				items.forEach(function (item) {
					var $row = $('<div class="jwpm-pos-search-row"></div>');
					var label = (item.tag || item.sku || '-') + ' — ' + (item.name || item.title || '');
					var price = formatMoney(item.price || 0);

					$row.append('<div class="jwpm-pos-search-label">' + label + '</div>');
					$row.append('<div class="jwpm-pos-search-price">' + price + '</div>');

					$row.on('click', function () {
						addItemToCart(item);
					});

					$box.append($row);
				});
			}
		);
	}

	function searchCustomer(term) {
		if (!jwpmPosData.search_customer_action) {
			return;
		}
		if (!term || term.length < 3) {
			return;
		}

		ajaxPost(
			jwpmPosData.search_customer_action,
			{
				term: term
			},
			function (data) {
				var customers = data.customers || [];
				if (!customers.length) {
					showToast('کوئی کسٹمر نہیں ملا۔');
					return;
				}
				// فی الحال پہلا کسٹمر auto-fill
				var c = customers[0];
				$('.js-pos-cust-name').val(c.name || '');
				$('.js-pos-cust-mobile').val(c.mobile || '');
				$('.js-pos-cust-points').val(c.points || 0);
				$('.js-pos-cust-credit').val(c.outstanding || 0);
			}
		);
	}

	function completeSale() {
		if (!jwpmPosData.complete_sale_action) {
			showToast('POS sale action missing.');
			return;
		}
		if (!cartItems.length) {
			showToast('کوئی آئٹم کارٹ میں نہیں ہے۔');
			return;
		}

		var itemsPayload = cartItems.map(function (item) {
			return {
				id: item.id,
				qty: item.qty,
				price: item.unitPrice,
				making: item.making,
				stone: item.stone,
				weight: item.wt
			};
		});

		var paidAmount = 0; // فی الحال 0، آگے آپ فیلڈز سے ٹوٹل لے سکتے ہیں۔
		var notes = $('.js-pos-notes').val() || '';

		var payload = {
			customer_id: 0, // اگر آپ کسٹمر آئی ڈی لے رہے ہیں تو یہاں set کریں
			discount: parseFloat($('.js-pos-overall-discount').val() || 0),
			paid_amount: paidAmount,
			payment_mode: 'cash',
			notes: notes,
			items: itemsPayload
		};

		ajaxPost(
			jwpmPosData.complete_sale_action,
			payload,
			function (data) {
				showToast(data.message || 'سیل کامیابی سے مکمل ہو گئی۔');
				cartItems = [];
				renderCart();
			}
		);
	}

	// 🔴 یہاں پر [AJAX Helpers] ختم ہو رہا ہے

	// 🟢 یہاں سے [Event Bindings] شروع ہو رہا ہے

	function bindEvents() {
		// سرچ فیلڈ
		$root.on('input', '.js-pos-search-text', function () {
			var term = $(this).val();
			searchItems(term);
		});

		// مجموعی ڈسکاؤنٹ
		$root.on('input', '.js-pos-overall-discount', function () {
			recalcTotals();
		});

		// کسٹمر سرچ
		$root.on('input', '.js-pos-customer-search', function () {
			var term = $(this).val();
			searchCustomer(term);
		});

		// Installment mode toggle
		$root.on('click', '.js-pos-pay-install', function (e) {
			e.preventDefault();
			var $box = $('.js-pos-installment-box');
			$box.prop('hidden', !$box.prop('hidden'));
		});

		// مکمل سیل — فی الحال ایک سادہ event، آپ اپنے UI کے کسی بھی button پر bind کر سکتے ہیں
		// مثال: future میں .js-pos-complete-sale کلاس والا button add کریں
		$root.on('click', '.js-pos-complete-sale', function (e) {
			e.preventDefault();
			completeSale();
		});
	}

	// 🔴 یہاں پر [Event Bindings] ختم ہو رہا ہے

	// POS کو initialize کریں
	renderLayout();
});

// ✅ Syntax verified block end
