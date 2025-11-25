/**
 * JWPM POS — JavaScript
 *
 * Part 1 — POS Core Initialization + UI Mount + Header/Stats Render
 *
 * Summary:
 * - Root mount
 * - Header, Stats, 3-Column layout inject
 * - Gold Rate load (basic)
 * - Live Date/Time clock
 * - Empty cart initialization
 *
 * اگلے پارٹس میں:
 * Part 2 — Product Search Logic
 * Part 3 — Cart Logic
 * Part 4 — Customer Search
 * Part 5 — Payment & Installments
 */

(function ($) {
	"use strict";

	// Soft warning
	function softWarn(msg) {
		console.warn("JWPM-POS:", msg);
	}

	// AJAX Helper
	async function wpAjax(action, data = {}) {
		data.action   = action;
		data.security = jwpmPosData.nonce;

		try {
			const res = await $.post(ajaxurl, data);
			if (!res) return { success: false };
			return res;
		} catch (e) {
			console.error("POS AJAX Error:", e);
			return { success: false };
		}
	}

	// Template mount helper
	function mountTemplate(id) {
		const tpl = document.getElementById(id);
		if (!tpl) {
			softWarn("Template not found: " + id);
			return null;
		}
		return tpl.content.cloneNode(true);
	}

	// Main POS App Object
	const JWPM_POS = {
		root: null,
		state: {
			cart: [],
			gold_rate: 0,
			branch_id: jwpmPosData.default_branch || 0,
		},

		/** Initialize POS page */
		init() {
			this.root = document.getElementById("jwpm-pos-root");
			if (!this.root) {
				softWarn("POS root not found (#jwpm-pos-root)");
				return;
			}

			this.renderInitialUI();
			this.startClock();
			this.loadGoldRate();
		},

		/** Render header + stats + main layout */
		renderInitialUI() {
			this.root.innerHTML = "";

			this.root.appendChild(mountTemplate("jwpm-pos-header-template"));
			this.root.appendChild(mountTemplate("jwpm-pos-stats-template"));
			this.root.appendChild(mountTemplate("jwpm-pos-main-template"));
		},

		/** Date/Time Clock */
		startClock() {
			const el = this.root.querySelector(".js-pos-datetime");
			if (!el) return;

			function update() {
				const now = new Date();
				el.textContent =
					now.toLocaleDateString() +
					" " +
					now.toLocaleTimeString();
			}

			update();
			setInterval(update, 1000);
		},

		/** Load Gold Rate */
		async loadGoldRate() {
			const res = await wpAjax(jwpmPosData.gold_rate_action, {});
			if (res.success && res.data) {
				this.state.gold_rate = Number(res.data.rate || 0);

				const box = this.root.querySelector(".js-gold-rate");
				if (box) box.textContent = this.state.gold_rate;
			}
		}
	};

	/** DOM Ready */
	$(document).ready(() => {
		JWPM_POS.init();
	});

})(jQuery);

// 🔴 Part 1 End — POS Initialization
// ✅ Syntax verified block end
/**
 * Part 2 — POS Product Search (Left Pane: AJAX + Results + Click)
 *
 * Summary:
 * - Left pane search logic
 * - Typing debounce
 * - AJAX → jwpm_pos_search_items
 * - Render search results list (photo, design, tag, karat, weight, status)
 * - Row click → fire custom event for Cart (next Part handle کرے گا)
 */

(function ($) {
	"use strict";

	// 🟢 یہاں سے [POS Search Helpers] شروع ہو رہا ہے

	function posSoftWarn(msg) {
		console.warn("JWPM-POS Search:", msg);
	}

	async function posAjax(action, data = {}) {
		if (typeof jwpmPosData === "undefined") {
			return { success: false, data: { message: "jwpmPosData not defined." } };
		}

		data.action   = action;
		data.security = jwpmPosData.nonce;

		try {
			const res = await $.post(ajaxurl, data);
			if (!res) {
				return { success: false, data: { message: "Empty response." } };
			}
			return res;
		} catch (e) {
			console.error("POS Search AJAX Error:", e);
			return { success: false, data: { message: "Network error." } };
		}
	}

	// 🟣 منتخب آئٹمز کو میموری میں رکھنے کے لیے لوکل کیش
	const itemCache = {};

	// 🔴 یہاں پر [POS Search Helpers] ختم ہو رہا ہے


	// 🟢 یہاں سے [POS Search Init] شروع ہو رہا ہے

	$(document).ready(function () {
		if (typeof jwpmPosData === "undefined") {
			posSoftWarn("jwpmPosData is not available (Part 2).");
			return;
		}

		const $root          = $("#jwpm-pos-root");
		if (!$root.length) {
			posSoftWarn("#jwpm-pos-root not found (Part 2).");
			return;
		}

		const $searchInput   = $root.find(".js-pos-search-text");
		const $catSelect     = $root.find(".js-pos-filter-category");
		const $karatSelect   = $root.find(".js-pos-filter-karat");
		const $scanBtn       = $root.find(".js-pos-scan-btn");
		const $resultsHolder = $root.find(".js-pos-search-results");
		const $branchSelect  = $root.closest(".wrap").find(".jwpm-branch-select");

		let searchTimer = null;

		function getBranchId() {
			const v = $branchSelect.val();
			if (v) return parseInt(v, 10) || 0;
			return jwpmPosData.default_branch || 0;
		}

		// سرچ ٹرگر کرنے والا فنکشن (debounce کے ساتھ)
		function scheduleSearch() {
			if (! $searchInput.length && ! $catSelect.length && ! $karatSelect.length) {
				return;
			}
			if (searchTimer) {
				clearTimeout(searchTimer);
			}
			searchTimer = setTimeout(runSearch, 350);
		}

		async function runSearch() {
			if (!$resultsHolder.length) return;

			const keyword  = $searchInput.val();
			const category = $catSelect.val();
			const karat    = $karatSelect.val();
			const branchId = getBranchId();

			$resultsHolder
				.addClass("jwpm-pos-search-loading")
				.html('<div class="jwpm-pos-search-status">Searching…</div>');

			const res = await posAjax(jwpmPosData.search_items_action, {
				keyword: keyword,
				category: category,
				karat: karat,
				branch_id: branchId
			});

			if (!res.success) {
				const msg = (res.data && res.data.message) || "Search failed.";
				$resultsHolder.html(
					'<div class="jwpm-pos-search-error">' + msg + "</div>"
				);
				return;
			}

			const items = (res.data && res.data.items) || [];
			renderSearchResults(items);
		}

		function renderSearchResults(items) {
			$resultsHolder.removeClass("jwpm-pos-search-loading").empty();
			Object.keys(itemCache).forEach(function (k) {
				delete itemCache[k];
			});

			if (!items.length) {
				$resultsHolder.html(
					'<div class="jwpm-pos-no-results">No items found. Try different search or filters.</div>'
				);
				return;
			}

			items.forEach(function (item) {
				itemCache[item.id] = item;

				const statusClass = getStatusClass(item.status);

				const $row = $(`
					<div class="jwpm-pos-result-row" data-item-id="${item.id}">
						<div class="jwpm-pos-result-photo">
							<div class="jwpm-photo-32"></div>
						</div>
						<div class="jwpm-pos-result-main">
							<div class="jwpm-pos-result-line1">
								<span class="jwpm-pos-result-design">${escapeHtml(item.category || "")}</span>
								<span class="jwpm-pos-result-tag">${escapeHtml(item.tag_serial || "")}</span>
							</div>
							<div class="jwpm-pos-result-line2">
								<span class="jwpm-pos-result-karat">${escapeHtml(item.karat || "")}</span>
								<span class="jwpm-pos-result-weight">${Number(item.net_weight || 0).toFixed(3)} g</span>
							</div>
						</div>
						<div class="jwpm-pos-result-status">
							<span class="jwpm-pos-status-badge ${statusClass}">
								${prettyStatus(item.status)}
							</span>
						</div>
					</div>
				`);

				$row.on("click", function () {
					const id = $(this).data("item-id");
					const fullItem = itemCache[id];
					if (!fullItem) {
						posSoftWarn("Clicked item not found in cache: " + id);
						return;
					}

					// Custom event: اگلے Part میں Cart اس event کو handle کرے گا
					$(document).trigger("jwpm_pos_item_selected", [fullItem]);
				});

				$resultsHolder.append($row);
			});
		}

		function prettyStatus(status) {
			switch (status) {
				case "in_stock":
					return "In Stock";
				case "low_stock":
					return "Low Stock";
				case "dead_stock":
					return "Dead Stock";
				case "scrap":
					return "Scrap";
				default:
					return status || "-";
			}
		}

		function getStatusClass(status) {
			switch (status) {
				case "in_stock":
					return "jwpm-pos-status-in";
				case "low_stock":
					return "jwpm-pos-status-low";
				case "dead_stock":
					return "jwpm-pos-status-dead";
				case "scrap":
					return "jwpm-pos-status-scrap";
				default:
					return "";
			}
		}

		function escapeHtml(str) {
			if (typeof str !== "string") return "";
			return str
				.replace(/&/g, "&amp;")
				.replace(/</g, "&lt;")
				.replace(/>/g, "&gt;")
				.replace(/"/g, "&quot;")
				.replace(/'/g, "&#039;");
		}

		// Event bindings

		if ($searchInput.length) {
			$searchInput.on("keyup", scheduleSearch);
		}
		if ($catSelect.length) {
			$catSelect.on("change", scheduleSearch);
		}
		if ($karatSelect.length) {
			$karatSelect.on("change", scheduleSearch);
		}
		if ($branchSelect.length) {
			$branchSelect.on("change", scheduleSearch);
		}
		if ($scanBtn.length) {
			$scanBtn.on("click", function () {
				// مستقبل میں Barcode Scanner integrate ہو گا، فی الحال صرف ایک soft پیام
				alert("Barcode Scan integration is not implemented yet.");
			});
		}

		// ابتدائی لوڈ پر ایک بار سرچ
		scheduleSearch();
	});

	// 🔴 یہاں پر [POS Search Init] ختم ہو رہا ہے

})(jQuery);

// 🔴 Part 2 End — POS Product Search
// ✅ Syntax verified block end
/** Part 3 — POS Cart Logic */
/**
 * POS Cart Logic:
 * - search results سے آنے والے jwpm_pos_item_selected event پر item کو cart میں add کرتا ہے
 * - qty / discount update پر line total دوبارہ calculate کرتا ہے
 * - remove row, overall discount اور old gold amount کو grand total میں شامل کرتا ہے
 */

// 🟢 یہاں سے POS Cart Logic شروع ہو رہا ہے
(function (window, document) {
    'use strict';

    if (!window.JWPMPos) {
        console.warn('JWPM POS: JWPMPos root object نہیں ملا، Cart Logic initialise نہیں ہو سکی۔');
        return;
    }

    var Pos = window.JWPMPos;

    // اگر state object موجود نہیں تو بنا لیں
    Pos.state = Pos.state || {};
    if (!Array.isArray(Pos.state.cart)) {
        Pos.state.cart = [];
    }

    // اندرونی state
    var cartItems = Pos.state.cart;
    var cartRowCounter = Pos.__cartRowCounter || 0;

    // DOM selectors — یہ IDs / classes POS Templates کے ساتھ match ہونی چاہئیں
    var selectors = {
        cartBody: '#jwpm-pos-cart-body',
        cartRowTemplate: '#jwpm-pos-cart-row-template',
        subtotal: '#jwpm-pos-subtotal',
        overallDiscount: '#jwpm-pos-overall-discount',
        grandTotal: '#jwpm-pos-grand-total',
        overallDiscountInput: '#jwpm-pos-overall-discount-input',
        oldGoldNet: '#jwpm-pos-old-gold-net'
    };

    // چھوٹے utility helpers
    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function getNumericFromElement(el) {
        if (!el) {
            return 0;
        }
        if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
            return parseFloat(el.value || '0') || 0;
        }
        var raw = el.textContent || '';
        raw = raw.replace(/[^\d\.\-]/g, '');
        return parseFloat(raw || '0') || 0;
    }

    function setNumericToElement(el, value) {
        if (!el) {
            return;
        }
        var num = isFinite(value) ? value : 0;
        if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
            el.value = num.toFixed(2);
        } else {
            el.textContent = formatCurrency(num);
        }
    }

    function formatCurrency(amount) {
        var num = isFinite(amount) ? amount : 0;
        if (Pos.utils && typeof Pos.utils.formatCurrency === 'function') {
            return Pos.utils.formatCurrency(num);
        }
        return num.toFixed(2);
    }

    function findCartItemByRowId(rowId) {
        rowId = String(rowId);
        for (var i = 0; i < cartItems.length; i++) {
            if (String(cartItems[i]._rowId) === rowId) {
                return cartItems[i];
            }
        }
        return null;
    }

    function removeCartItemByRowId(rowId) {
        rowId = String(rowId);
        for (var i = 0; i < cartItems.length; i++) {
            if (String(cartItems[i]._rowId) === rowId) {
                cartItems.splice(i, 1);
                break;
            }
        }
    }

    function getOldGoldNetAmount() {
        var el = qs(selectors.oldGoldNet);
        if (!el) {
            return 0;
        }
        return getNumericFromElement(el);
    }

    // -------------------------------------------------------------
    // Item select → Cart میں add کرنا
    // -------------------------------------------------------------
    function handleItemSelected(event) {
        var detail = event.detail || {};
        // کچھ implementations میں detail.item ہو سکتا ہے
        var item = detail.item || detail || {};
        if (!item.id && !item.item_id && !item.sku) {
            console.warn('JWPM POS: منتخب item میں id / sku موجود نہیں، Cart میں نہیں ڈالا گیا۔', item);
            return;
        }

        var itemId = item.id || item.item_id || item.sku;
        var existing = null;

        for (var i = 0; i < cartItems.length; i++) {
            if (String(cartItems[i].id) === String(itemId)) {
                existing = cartItems[i];
                break;
            }
        }

        if (existing) {
            // پہلے سے موجود ہو تو qty بڑھا دیں
            existing.qty = (existing.qty || 0) + 1;
            updateRowFromCartItem(existing);
            recalcTotals();
            return;
        }

        // نیا کارٹ item
        var unitPrice = parseFloat(item.unit_price || item.price || item.sale_price || '0') || 0;
        var newCartItem = {
            _rowId: (++cartRowCounter),
            id: itemId,
            name: item.name || item.title || 'Item',
            code: item.code || item.sku || '',
            karat: item.karat || item.carat || '',
            weight: parseFloat(item.weight || '0') || 0,
            unitPrice: unitPrice,
            qty: 1,
            lineDiscount: 0,
            lineTotal: unitPrice
        };

        cartItems.push(newCartItem);
        Pos.__cartRowCounter = cartRowCounter;

        appendCartRow(newCartItem);
        recalcTotals();
    }

    // -------------------------------------------------------------
    // DOM میں row append کرنا
    // -------------------------------------------------------------
    function appendCartRow(cartItem) {
        var cartBody = qs(selectors.cartBody);
        if (!cartBody) {
            console.warn('JWPM POS: Cart body element نہیں ملا، row append نہیں ہو سکی۔');
            return;
        }

        var tpl = qs(selectors.cartRowTemplate);
        var row;

        if (tpl && tpl.content && tpl.content.firstElementChild) {
            row = tpl.content.firstElementChild.cloneNode(true);
        } else {
            // fallback — simple tr create اگر template نہ ملے
            row = document.createElement('tr');
            row.innerHTML = '' +
                '<td class="jwpm-pos-cart-name"></td>' +
                '<td class="jwpm-pos-cart-code"></td>' +
                '<td><input type="number" min="1" step="1" class="jwpm-pos-cart-qty" /></td>' +
                '<td class="jwpm-pos-cart-unit"></td>' +
                '<td><input type="number" step="0.01" class="jwpm-pos-cart-discount" /></td>' +
                '<td class="jwpm-pos-cart-total"></td>' +
                '<td><button type="button" class="button button-link-delete jwpm-pos-cart-remove">&times;</button></td>';
        }

        row.dataset.cartRowId = String(cartItem._rowId);

        var nameCell = row.querySelector('.jwpm-pos-cart-name');
        var codeCell = row.querySelector('.jwpm-pos-cart-code');
        var qtyInput = row.querySelector('.jwpm-pos-cart-qty');
        var unitCell = row.querySelector('.jwpm-pos-cart-unit');
        var discountInput = row.querySelector('.jwpm-pos-cart-discount');
        var totalCell = row.querySelector('.jwpm-pos-cart-total');

        if (nameCell) {
            nameCell.textContent = cartItem.name;
        }
        if (codeCell) {
            codeCell.textContent = cartItem.code || '';
        }
        if (qtyInput) {
            qtyInput.value = cartItem.qty;
        }
        if (unitCell) {
            unitCell.textContent = formatCurrency(cartItem.unitPrice);
        }
        if (discountInput) {
            discountInput.value = cartItem.lineDiscount.toFixed(2);
        }
        if (totalCell) {
            totalCell.textContent = formatCurrency(cartItem.lineTotal);
        }

        cartBody.appendChild(row);
    }

    // -------------------------------------------------------------
    // Row کو cart item سے sync کرنا (جب qty / discount change ہو)
    // -------------------------------------------------------------
    function updateRowFromCartItem(cartItem) {
        var cartBody = qs(selectors.cartBody);
        if (!cartBody) {
            return;
        }

        var row = cartBody.querySelector('tr[data-cart-row-id="' + cartItem._rowId + '"]');
        if (!row) {
            return;
        }

        var qtyInput = row.querySelector('.jwpm-pos-cart-qty');
        var discountInput = row.querySelector('.jwpm-pos-cart-discount');
        var totalCell = row.querySelector('.jwpm-pos-cart-total');

        if (qtyInput) {
            qtyInput.value = cartItem.qty;
        }
        if (discountInput) {
            discountInput.value = cartItem.lineDiscount.toFixed(2);
        }
        if (totalCell) {
            totalCell.textContent = formatCurrency(cartItem.lineTotal);
        }
    }

    // -------------------------------------------------------------
    // Row کی line total calculation
    // -------------------------------------------------------------
    function recalcRowTotals(cartItem) {
        var qty = parseFloat(cartItem.qty || 0) || 0;
        if (qty < 0) {
            qty = 0;
        }
        var unitPrice = parseFloat(cartItem.unitPrice || 0) || 0;
        var discount = parseFloat(cartItem.lineDiscount || 0) || 0;

        var gross = qty * unitPrice;
        if (discount > gross) {
            discount = gross;
        }

        cartItem.qty = qty;
        cartItem.lineDiscount = discount;
        cartItem.lineTotal = Math.max(gross - discount, 0);
    }

    // -------------------------------------------------------------
    // Cart کی مجموعی totals calculation
    // -------------------------------------------------------------
    function recalcTotals() {
        var subtotal = 0;
        for (var i = 0; i < cartItems.length; i++) {
            recalcRowTotals(cartItems[i]);
            subtotal += cartItems[i].lineTotal;
        }

        var overallDiscountEl = qs(selectors.overallDiscountInput) || qs(selectors.overallDiscount);
        var overallDiscount = getNumericFromElement(overallDiscountEl);
        if (overallDiscount > subtotal) {
            overallDiscount = subtotal;
        }

        var oldGoldNet = getOldGoldNetAmount();

        var grandTotal = subtotal - overallDiscount - oldGoldNet;
        if (grandTotal < 0) {
            grandTotal = 0;
        }

        // UI میں update
        setNumericToElement(qs(selectors.subtotal), subtotal);
        setNumericToElement(qs(selectors.overallDiscount), overallDiscount);
        setNumericToElement(qs(selectors.grandTotal), grandTotal);

        // state میں بھی totals رکھ لیں تاکہ payment step میں استعمال ہو
        Pos.state.cartTotals = {
            subtotal: subtotal,
            overallDiscount: overallDiscount,
            oldGoldNet: oldGoldNet,
            grandTotal: grandTotal
        };
    }

    // -------------------------------------------------------------
    // Cart body پر event delegation (qty / discount / remove)
    // -------------------------------------------------------------
    function handleCartBodyInput(event) {
        var target = event.target;
        if (!target) {
            return;
        }

        var row = target.closest('tr[data-cart-row-id]');
        if (!row) {
            return;
        }

        var rowId = row.dataset.cartRowId;
        var cartItem = findCartItemByRowId(rowId);
        if (!cartItem) {
            return;
        }

        if (target.classList.contains('jwpm-pos-cart-qty')) {
            var qty = parseFloat(target.value || '0') || 0;
            if (qty < 0) {
                qty = 0;
            }
            cartItem.qty = qty;
        } else if (target.classList.contains('jwpm-pos-cart-discount')) {
            var discount = parseFloat(target.value || '0') || 0;
            if (discount < 0) {
                discount = 0;
            }
            cartItem.lineDiscount = discount;
        } else {
            return;
        }

        recalcRowTotals(cartItem);
        updateRowFromCartItem(cartItem);
        recalcTotals();
    }

    function handleCartBodyClick(event) {
        var target = event.target;
        if (!target) {
            return;
        }

        if (!target.classList.contains('jwpm-pos-cart-remove') &&
            !(target.closest && target.closest('.jwpm-pos-cart-remove'))) {
            return;
        }

        var row = target.closest('tr[data-cart-row-id]');
        if (!row) {
            return;
        }

        var rowId = row.dataset.cartRowId;
        removeCartItemByRowId(rowId);
        row.parentNode.removeChild(row);
        recalcTotals();
    }

    // Overall discount input change
    function handleOverallDiscountChange() {
        recalcTotals();
    }

    // Old Gold Modal کسی اور JS Part میں event emit کرے گا
    // ہم صرف event سنتے ہیں اور totals دوبارہ calculate کرتے ہیں
    function handleOldGoldUpdated() {
        recalcTotals();
    }

    // -------------------------------------------------------------
    // Initialisation
    // -------------------------------------------------------------
    function initCartModule() {
        var cartBody = qs(selectors.cartBody);
        if (!cartBody) {
            console.warn('JWPM POS: Cart body element نہیں ملا، Cart Module محدود موڈ میں چلے گا۔');
        } else {
            cartBody.addEventListener('input', handleCartBodyInput);
            cartBody.addEventListener('click', handleCartBodyClick);
        }

        var overallDiscountInput = qs(selectors.overallDiscountInput) || qs(selectors.overallDiscount);
        if (overallDiscountInput) {
            overallDiscountInput.addEventListener('input', handleOverallDiscountChange);
            overallDiscountInput.addEventListener('change', handleOverallDiscountChange);
        }

        // custom events
        document.addEventListener('jwpm_pos_item_selected', handleItemSelected);
        document.addEventListener('jwpm_pos_old_gold_updated', handleOldGoldUpdated);

        // اگر پہلے سے cartItems میں data موجود ہے (مثلاً demo data) تو اسے بھی DOM میں render کر دیں
        if (cartItems.length && cartBody) {
            for (var i = 0; i < cartItems.length; i++) {
                if (!cartItems[i]._rowId) {
                    cartItems[i]._rowId = (++cartRowCounter);
                }
                appendCartRow(cartItems[i]);
            }
            Pos.__cartRowCounter = cartRowCounter;
            recalcTotals();
        }

        // Public API expose
        Pos.cart = Pos.cart || {};
        Pos.cart.recalcTotals = recalcTotals;
        Pos.cart.getItems = function () {
            return cartItems.slice();
        };
        Pos.cart.clear = function () {
            cartItems.length = 0;
            var body = qs(selectors.cartBody);
            if (body) {
                while (body.firstChild) {
                    body.removeChild(body.firstChild);
                }
            }
            recalcTotals();
        };
    }

    // اگر Pos میں onReady hook ہو تو وہ استعمال کریں، ورنہ DOMContentLoaded
    if (typeof Pos.onReady === 'function') {
        Pos.onReady(initCartModule);
    } else {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCartModule);
        } else {
            initCartModule();
        }
    }

})(window, document);
// 🔴 یہاں پر POS Cart Logic ختم ہو رہا ہے

// ✅ Syntax verified block end
/** Part 4 — POS Customer Search & Loading */
/**
 * Customer Search & Loading:
 * - کسٹمر سرچ باکس سے (AJAX) کے ذریعے کسٹمر لسٹ لاتا ہے
 * - سرچ رزلٹس میں سے کسٹمر select کر کے POS state میں سیٹ کرتا ہے
 * - منتخب کسٹمر کی تفصیل Customer Summary panel میں دکھاتا ہے
 * - custom event: jwpm_pos_customer_selected emit کرتا ہے
 */

// 🟢 یہاں سے POS Customer Search & Loading شروع ہو رہا ہے
(function (window, document) {
    'use strict';

    if (!window.JWPMPos) {
        console.warn('JWPM POS: JWPMPos root object نہیں ملا، Customer Module initialise نہیں ہو سکا۔');
        return;
    }

    var Pos = window.JWPMPos;
    var jwpmPosData = window.jwpmPosData || window.JWPM_POS_DATA || {};

    Pos.state = Pos.state || {};
    Pos.state.customer = Pos.state.customer || null;

    var selectors = {
        searchInput: '#jwpm-pos-customer-search-input',
        searchClear: '#jwpm-pos-customer-search-clear',
        resultContainer: '#jwpm-pos-customer-results',
        rowTemplate: '#jwpm-pos-customer-row-template',
        noResults: '#jwpm-pos-customer-no-results',

        selectedWrapper: '#jwpm-pos-customer-selected-wrapper',
        selectedName: '#jwpm-pos-customer-name',
        selectedPhone: '#jwpm-pos-customer-phone',
        selectedEmail: '#jwpm-pos-customer-email',
        selectedIdHidden: '#jwpm-pos-customer-id',
        selectedLoyalty: '#jwpm-pos-customer-loyalty',

        clearSelected: '#jwpm-pos-customer-clear-selected'
    };

    var cssClasses = {
        row: 'jwpm-pos-customer-row',
        rowSelected: 'is-selected'
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector) || []);
    }

    function getAjaxConfig() {
        var actions = jwpmPosData.actions || {};
        var nonces = jwpmPosData.nonces || {};

        return {
            url: jwpmPosData.ajax_url || window.ajaxurl || '',
            action: actions.customer_search || 'jwpm_pos_customer_search',
            nonce: nonces.customer_search || jwpmPosData.nonce_customer_search || ''
        };
    }

    function ensureDebounce(fn, wait) {
        if (Pos.utils && typeof Pos.utils.debounce === 'function') {
            return Pos.utils.debounce(fn, wait);
        }
        var timeout;
        return function () {
            var ctx = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                fn.apply(ctx, args);
            }, wait);
        };
    }

    function wpAjaxRequest(payload) {
        if (Pos.utils && typeof Pos.utils.wpAjax === 'function') {
            return Pos.utils.wpAjax(payload);
        }

        var url = payload.url || (jwpmPosData.ajax_url || window.ajaxurl || '');
        var data = payload.data || {};

        var body = new URLSearchParams();
        Object.keys(data).forEach(function (key) {
            if (data[key] !== undefined && data[key] !== null) {
                body.append(key, data[key]);
            }
        });

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) {
            return res.json();
        });
    }

    function buildCustomerRow(customer) {
        var tpl = qs(selectors.rowTemplate);
        var row;

        if (tpl && tpl.content && tpl.content.firstElementChild) {
            row = tpl.content.firstElementChild.cloneNode(true);
        } else {
            row = document.createElement('div');
            row.className = cssClasses.row;
            row.innerHTML =
                '<div class="jwpm-pos-customer-row-main">' +
                    '<div class="jwpm-pos-customer-row-name"></div>' +
                    '<div class="jwpm-pos-customer-row-phone"></div>' +
                '</div>';
        }

        row.classList.add(cssClasses.row);
        row.dataset.customerId = String(customer.id || customer.customer_id || '');
        row.__jwpmCustomer = customer;

        var nameEl = row.querySelector('.jwpm-pos-customer-row-name');
        var phoneEl = row.querySelector('.jwpm-pos-customer-row-phone');

        if (nameEl) {
            nameEl.textContent = customer.name || customer.full_name || 'Customer';
        }
        if (phoneEl) {
            phoneEl.textContent = customer.phone || customer.mobile || '';
        }

        return row;
    }

    function clearResults(message) {
        var container = qs(selectors.resultContainer);
        if (!container) {
            return;
        }
        container.innerHTML = '';

        var noRes = qs(selectors.noResults);
        if (noRes) {
            noRes.textContent = message || '';
            noRes.style.display = message ? '' : 'none';
        } else if (message) {
            var div = document.createElement('div');
            div.className = 'jwpm-pos-customer-empty';
            div.textContent = message;
            container.appendChild(div);
        }
    }

    function renderResults(list) {
        var container = qs(selectors.resultContainer);
        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (!list || !list.length) {
            clearResults('کوئی کسٹمر نہیں ملا۔');
            return;
        }

        var frag = document.createDocumentFragment();
        list.forEach(function (customer) {
            var row = buildCustomerRow(customer);
            frag.appendChild(row);
        });

        container.appendChild(frag);

        var current = Pos.state.customer;
        if (current && current.id) {
            qsa('.' + cssClasses.row, container).forEach(function (row) {
                var rowCust = row.__jwpmCustomer || {};
                if (String(rowCust.id || rowCust.customer_id || '') === String(current.id)) {
                    row.classList.add(cssClasses.rowSelected);
                }
            });
        }
    }

    function setSelectedCustomer(customer) {
        if (!customer) {
            Pos.state.customer = null;

            var wrap = qs(selectors.selectedWrapper);
            var nameEl = qs(selectors.selectedName);
            var phoneEl = qs(selectors.selectedPhone);
            var emailEl = qs(selectors.selectedEmail);
            var idHidden = qs(selectors.selectedIdHidden);
            var loyaltyEl = qs(selectors.selectedLoyalty);

            if (wrap) {
                wrap.classList.remove('has-customer');
            }
            if (nameEl) {
                nameEl.textContent = 'Guest';
            }
            if (phoneEl) {
                phoneEl.textContent = '';
            }
            if (emailEl) {
                emailEl.textContent = '';
            }
            if (loyaltyEl) {
                loyaltyEl.textContent = '';
            }
            if (idHidden) {
                idHidden.value = '';
            }

            document.dispatchEvent(new CustomEvent('jwpm_pos_customer_selected', {
                detail: { customer: null }
            }));

            highlightSelectedRow(null);
            return;
        }

        var normalized = {
            id: customer.id || customer.customer_id || '',
            name: customer.name || customer.full_name || '',
            phone: customer.phone || customer.mobile || '',
            email: customer.email || '',
            loyalty_points: customer.loyalty_points || customer.points || 0,
            raw: customer
        };

        Pos.state.customer = normalized;

        var wrapSel = qs(selectors.selectedWrapper);
        var nameSel = qs(selectors.selectedName);
        var phoneSel = qs(selectors.selectedPhone);
        var emailSel = qs(selectors.selectedEmail);
        var idHiddenSel = qs(selectors.selectedIdHidden);
        var loyaltySel = qs(selectors.selectedLoyalty);

        if (wrapSel) {
            wrapSel.classList.add('has-customer');
        }
        if (nameSel) {
            nameSel.textContent = normalized.name || 'Customer';
        }
        if (phoneSel) {
            phoneSel.textContent = normalized.phone || '';
        }
        if (emailSel) {
            emailSel.textContent = normalized.email || '';
        }
        if (loyaltySel) {
            loyaltySel.textContent = String(normalized.loyalty_points || 0);
        }
        if (idHiddenSel) {
            idHiddenSel.value = String(normalized.id || '');
        }

        document.dispatchEvent(new CustomEvent('jwpm_pos_customer_selected', {
            detail: { customer: normalized }
        }));

        highlightSelectedRow(normalized.id);
    }

    function highlightSelectedRow(customerId) {
        var container = qs(selectors.resultContainer);
        if (!container) {
            return;
        }

        qsa('.' + cssClasses.row, container).forEach(function (row) {
            row.classList.remove(cssClasses.rowSelected);
            var cust = row.__jwpmCustomer || {};
            var id = cust.id || cust.customer_id || '';
            if (customerId && String(id) === String(customerId)) {
                row.classList.add(cssClasses.rowSelected);
            }
        });
    }

    function handleResultsClick(event) {
        var target = event.target;
        if (!target) {
            return;
        }

        var row = target.closest('.' + cssClasses.row);
        if (!row || !row.__jwpmCustomer) {
            return;
        }

        setSelectedCustomer(row.__jwpmCustomer);
    }

    function handleSearchTermChange(value) {
        var term = (value || '').trim();
        var minChars = 2;

        if (!term) {
            clearResults('');
            return;
        }

        if (term.length < minChars) {
            clearResults('کم از کم ' + minChars + ' حروف ٹائپ کریں۔');
            return;
        }

        var ajaxCfg = getAjaxConfig();
        if (!ajaxCfg.url) {
            console.warn('JWPM POS: (ajax_url) نہیں ملا، Customer Search نہیں چل سکے گی۔');
            return;
        }

        clearResults('تلاش جاری ہے...');

        wpAjaxRequest({
            url: ajaxCfg.url,
            data: {
                action: ajaxCfg.action,
                nonce: ajaxCfg.nonce,
                term: term
            }
        }).then(function (response) {
            if (!response) {
                clearResults('سرور سے غلط جواب ملا۔');
                return;
            }
            if (response.success === false) {
                clearResults(response.data && response.data.message ? response.data.message : 'کچھ مسئلہ آ گیا، دوبارہ کوشش کریں۔');
                return;
            }

            var list = response.data && (response.data.customers || response.data.items || response.data) || [];
            if (!Array.isArray(list)) {
                list = [];
            }

            if (!list.length) {
                clearResults('کوئی کسٹمر نہیں ملا۔');
                return;
            }

            renderResults(list);
        }).catch(function (err) {
            console.error('JWPM POS: Customer Search error', err);
            clearResults('سرور سے رابطہ نہ ہو سکا۔');
        });
    }

    var debouncedSearch = ensureDebounce(function (value) {
        handleSearchTermChange(value);
    }, 300);

    function handleSearchInput(event) {
        var value = event.target ? event.target.value : '';
        debouncedSearch(value);
    }

    function handleSearchClear() {
        var input = qs(selectors.searchInput);
        if (input) {
            input.value = '';
        }
        clearResults('');
    }

    function handleClearSelectedCustomer() {
        setSelectedCustomer(null);
    }

    function mountExistingCustomerFromData() {
        var existing = Pos.state.customer;
        if (!existing && jwpmPosData.current_customer) {
            existing = jwpmPosData.current_customer;
        }
        if (existing) {
            setSelectedCustomer(existing);
        }
    }

    function initCustomerModule() {
        var searchInput = qs(selectors.searchInput);
        if (!searchInput) {
            console.warn('JWPM POS: Customer search input نہیں ملا، Customer Module محدود موڈ میں چلے گا۔');
        } else {
            searchInput.addEventListener('input', handleSearchInput);
            searchInput.addEventListener('change', handleSearchInput);
        }

        var searchClear = qs(selectors.searchClear);
        if (searchClear) {
            searchClear.addEventListener('click', handleSearchClear);
        }

        var resultContainer = qs(selectors.resultContainer);
        if (resultContainer) {
            resultContainer.addEventListener('click', handleResultsClick);
        }

        var clearSelBtn = qs(selectors.clearSelected);
        if (clearSelBtn) {
            clearSelBtn.addEventListener('click', handleClearSelectedCustomer);
        }

        mountExistingCustomerFromData();

        Pos.customer = Pos.customer || {};
        Pos.customer.getSelected = function () {
            return Pos.state.customer || null;
        };
        Pos.customer.setSelected = function (customer) {
            setSelectedCustomer(customer || null);
        };
    }

    if (typeof Pos.onReady === 'function') {
        Pos.onReady(initCustomerModule);
    } else {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCustomerModule);
        } else {
            initCustomerModule();
        }
    }

})(window, document);
// 🔴 یہاں پر POS Customer Search & Loading ختم ہو رہا ہے

// ✅ Syntax verified block end
/** Part 5 — POS Payment + Installments */
/**
 * Payment + Installments Module:
 * - Cart totals سے grand total لے کر Total Due سیٹ کرتا ہے
 * - Cash / Card / Bank / Pending / Mixed ادائیگی ہینڈل کرتا ہے
 * - Remaining Due calculate کرتا ہے
 * - Installment mode میں advance + قسطوں کی تعداد کے حساب سے پلان بناتا ہے (simple preview)
 * - Pos.state.payment میں سب کچھ محفوظ کرتا ہے
 * - custom event: jwpm_pos_payment_updated emit کرتا ہے
 */

// 🟢 یہاں سے POS Payment + Installments شروع ہو رہا ہے
(function (window, document) {
    'use strict';

    if (!window.JWPMPos) {
        console.warn('JWPM POS: JWPMPos root object نہیں ملا، Payment Module initialise نہیں ہو سکا۔');
        return;
    }

    var Pos = window.JWPMPos;
    var jwpmPosData = window.jwpmPosData || window.JWPM_POS_DATA || {};

    Pos.state = Pos.state || {};
    Pos.state.payment = Pos.state.payment || {
        method: 'cash',
        totals: {
            totalDue: 0,
            amountPaid: 0,
            remainingDue: 0
        },
        split: {
            cash: 0,
            card: 0,
            bank: 0,
            pending: 0
        },
        installment: {
            enabled: false,
            count: 0,
            advance: 0,
            perInstallment: 0,
            schedule: []
        }
    };

    var selectors = {
        // بنیادی totals
        totalDue: '#jwpm-pos-total-due',
        amountPaid: '#jwpm-pos-amount-paid',
        remainingDue: '#jwpm-pos-remaining-due',

        // method selection (radio / buttons)
        paymentMethodGroup: '[name="jwpm-pos-payment-method"]',

        // amounts
        cashAmount: '#jwpm-pos-pay-cash-amount',
        cardAmount: '#jwpm-pos-pay-card-amount',
        bankAmount: '#jwpm-pos-pay-bank-amount',
        pendingAmount: '#jwpm-pos-pay-pending-amount',

        // installments
        installmentEnable: '#jwpm-pos-installment-enable',
        installmentCount: '#jwpm-pos-installment-count',
        installmentAdvance: '#jwpm-pos-installment-advance',
        installmentTableBody: '#jwpm-pos-installment-body',
        installmentPerAmount: '#jwpm-pos-installment-per-amount',

        // helper / status
        paymentNotice: '#jwpm-pos-payment-notice'
    };

    var cssClasses = {
        methodActive: 'is-active',
        installmentRow: 'jwpm-pos-installment-row'
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector) || []);
    }

    function getNumericValue(el) {
        if (!el) {
            return 0;
        }
        var v;
        if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
            v = el.value;
        } else {
            v = el.textContent;
        }
        v = (v || '').toString().replace(/[^\d\.\-]/g, '');
        var num = parseFloat(v);
        return isFinite(num) ? num : 0;
    }

    function setNumericText(el, value, asCurrency) {
        if (!el) {
            return;
        }
        var num = isFinite(value) ? value : 0;
        if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
            el.value = num.toFixed(2);
        } else {
            if (asCurrency) {
                el.textContent = formatCurrency(num);
            } else {
                el.textContent = num.toFixed(2);
            }
        }
    }

    function formatCurrency(amount) {
        var num = isFinite(amount) ? amount : 0;
        if (Pos.utils && typeof Pos.utils.formatCurrency === 'function') {
            return Pos.utils.formatCurrency(num);
        }
        return num.toFixed(2);
    }

    function getCartTotals() {
        // Cart Module Part 3 میں Pos.state.cartTotals set کرتا ہے
        var ct = Pos.state.cartTotals || {};
        return {
            subtotal: ct.subtotal || 0,
            overallDiscount: ct.overallDiscount || 0,
            oldGoldNet: ct.oldGoldNet || 0,
            grandTotal: ct.grandTotal || 0
        };
    }

    // -------------------------------------------------------------
    // Cart recalc hook wrap (cart totals update کے بعد payment کو بھی خبر)
    // -------------------------------------------------------------
    function installCartTotalsHook() {
        if (!Pos.cart || typeof Pos.cart.recalcTotals !== 'function') {
            return;
        }

        if (Pos.cart.__recalcPatched) {
            return;
        }

        var original = Pos.cart.recalcTotals;
        Pos.cart.recalcTotals = function () {
            var result = original.apply(Pos.cart, arguments);
            var totals = Pos.state.cartTotals || {};
            try {
                document.dispatchEvent(new CustomEvent('jwpm_pos_cart_totals_updated', {
                    detail: { totals: totals }
                }));
            } catch (e) {
                // ignore
            }
            // Payment بھی sync ہو جائے
            syncFromCartTotals();
            return result;
        };

        Pos.cart.__recalcPatched = true;
    }

    // Cart totals سے totalDue sync کرنا
    function syncFromCartTotals() {
        var ct = getCartTotals();
        var totalDue = ct.grandTotal || 0;

        var payment = Pos.state.payment;
        if (!payment || !payment.totals) {
            payment = Pos.state.payment = {
                method: 'cash',
                totals: {
                    totalDue: totalDue,
                    amountPaid: 0,
                    remainingDue: totalDue
                },
                split: {
                    cash: 0,
                    card: 0,
                    bank: 0,
                    pending: totalDue
                },
                installment: {
                    enabled: false,
                    count: 0,
                    advance: 0,
                    perInstallment: 0,
                    schedule: []
                }
            };
        }

        payment.totals.totalDue = totalDue;

        // اگر amountPaid خالی ہو تو remainingDue کو totalDue کے برابر رکھیں
        if (!payment.totals.amountPaid && !payment.split.cash && !payment.split.card && !payment.split.bank) {
            payment.totals.amountPaid = 0;
            payment.totals.remainingDue = totalDue;
            payment.split.pending = totalDue;
        }

        renderPaymentToUI();
    }

    // -------------------------------------------------------------
    // Payment method selection
    // -------------------------------------------------------------
    function getSelectedMethod() {
        var radios = qsa(selectors.paymentMethodGroup);
        var method = 'cash';
        radios.forEach(function (r) {
            if (r.checked) {
                method = r.value || method;
            }
        });
        return method;
    }

    function setSelectedMethod(method) {
        var radios = qsa(selectors.paymentMethodGroup);
        var found = false;
        radios.forEach(function (r) {
            if ((r.value || '') === method) {
                r.checked = true;
                found = true;
            }
            var label = r.closest('label') || r.closest('button');
            if (label) {
                label.classList.toggle(cssClasses.methodActive, r.checked);
            }
        });

        if (!found && radios.length) {
            radios[0].checked = true;
            var label0 = radios[0].closest('label') || radios[0].closest('button');
            if (label0) {
                label0.classList.add(cssClasses.methodActive);
            }
        }
    }

    // -------------------------------------------------------------
    // Installments helpers
    // -------------------------------------------------------------
    function buildInstallmentSchedule(baseAmount, count) {
        var schedule = [];
        if (!count || count < 1 || !baseAmount) {
            return schedule;
        }

        var per = baseAmount / count;
        per = Math.max(per, 0);

        var today = new Date();
        for (var i = 0; i < count; i++) {
            var d = new Date(today.getTime());
            // simple 30 days increment
            d.setDate(d.getDate() + (30 * (i + 1)));
            schedule.push({
                index: i + 1,
                amount: per,
                dueDate: d
            });
        }

        return schedule;
    }

    function renderInstallmentSchedule(schedule) {
        var tbody = qs(selectors.installmentTableBody);
        if (!tbody) {
            return;
        }

        tbody.innerHTML = '';

        if (!schedule || !schedule.length) {
            return;
        }

        var frag = document.createDocumentFragment();

        schedule.forEach(function (item) {
            var tr = document.createElement('tr');
            tr.className = cssClasses.installmentRow;

            var idxTd = document.createElement('td');
            var dateTd = document.createElement('td');
            var amtTd = document.createElement('td');

            idxTd.textContent = String(item.index);
            dateTd.textContent = formatInstallmentDate(item.dueDate);
            amtTd.textContent = formatCurrency(item.amount);

            tr.appendChild(idxTd);
            tr.appendChild(dateTd);
            tr.appendChild(amtTd);
            frag.appendChild(tr);
        });

        tbody.appendChild(frag);
    }

    function formatInstallmentDate(d) {
        if (!(d instanceof Date)) {
            return '';
        }
        // simple DD/MM/YYYY
        var day = String(d.getDate()).padStart(2, '0');
        var month = String(d.getMonth() + 1).padStart(2, '0');
        var year = d.getFullYear();
        return day + '/' + month + '/' + year;
    }

    // -------------------------------------------------------------
    // Core payment recalculation
    // -------------------------------------------------------------
    function recalcPayment() {
        var payment = Pos.state.payment || {};
        payment.method = getSelectedMethod();

        var totalDue = getCartTotals().grandTotal || 0;

        // amounts from inputs
        var cash = getNumericValue(qs(selectors.cashAmount));
        var card = getNumericValue(qs(selectors.cardAmount));
        var bank = getNumericValue(qs(selectors.bankAmount));
        var pendingInput = getNumericValue(qs(selectors.pendingAmount));

        // method کے لحاظ سے کچھ auto adjustments
        if (payment.method === 'cash') {
            // صرف cash allow، باقی 0
            card = 0;
            bank = 0;
            pendingInput = 0;
        } else if (payment.method === 'card') {
            cash = 0;
            bank = 0;
            pendingInput = 0;
        } else if (payment.method === 'bank') {
            cash = 0;
            card = 0;
            pendingInput = 0;
        } else if (payment.method === 'pending') {
            cash = 0;
            card = 0;
            bank = 0;
        } else {
            // mixed mode — کچھ خاص نہیں، user خود split کرے گا
        }

        if (cash < 0) cash = 0;
        if (card < 0) card = 0;
        if (bank < 0) bank = 0;
        if (pendingInput < 0) pendingInput = 0;

        var amountPaid = cash + card + bank;
        var remainingDue = totalDue - amountPaid;

        if (remainingDue < 0) {
            remainingDue = 0;
        }

        // اگر pendingInput manual دیا ہو تو اسے override کے طور پر treat کریں
        var pending = pendingInput || remainingDue;

        // اگر method pending ہو تو amountPaid = 0, pending = totalDue
        if (payment.method === 'pending') {
            amountPaid = 0;
            pending = totalDue;
            remainingDue = totalDue;
        }

        payment.totals = {
            totalDue: totalDue,
            amountPaid: amountPaid,
            remainingDue: remainingDue
        };

        payment.split = {
            cash: cash,
            card: card,
            bank: bank,
            pending: pending
        };

        // Installment section
        var enableEl = qs(selectors.installmentEnable);
        var countEl = qs(selectors.installmentCount);
        var advEl = qs(selectors.installmentAdvance);

        var enabled = !!(enableEl && enableEl.checked);
        var count = countEl ? parseInt(countEl.value || '0', 10) : 0;
        var advance = advEl ? getNumericValue(advEl) : 0;

        if (count < 0) count = 0;
        if (advance < 0) advance = 0;

        // installment base amount = remainingDue - advance
        var baseForInstall = remainingDue - advance;
        if (baseForInstall < 0) {
            baseForInstall = 0;
        }

        var perInstall = 0;
        var schedule = [];

        if (enabled && count > 0 && baseForInstall > 0) {
            schedule = buildInstallmentSchedule(baseForInstall, count);
            if (schedule.length) {
                perInstall = schedule[0].amount || 0;
            }
        }

        payment.installment = {
            enabled: enabled,
            count: enabled ? count : 0,
            advance: enabled ? advance : 0,
            perInstallment: enabled ? perInstall : 0,
            schedule: enabled ? schedule : []
        };

        Pos.state.payment = payment;

        renderPaymentToUI();
        notifyPaymentUpdated();
    }

    function renderPaymentToUI() {
        var payment = Pos.state.payment || {};
        var totals = payment.totals || {};
        var split = payment.split || {};
        var inst = payment.installment || {};

        // totals
        setNumericText(qs(selectors.totalDue), totals.totalDue || 0, true);
        setNumericText(qs(selectors.amountPaid), totals.amountPaid || 0, true);
        setNumericText(qs(selectors.remainingDue), totals.remainingDue || 0, true);

        // split (inputs میں بھی sync)
        setNumericText(qs(selectors.cashAmount), split.cash || 0, false);
        setNumericText(qs(selectors.cardAmount), split.card || 0, false);
        setNumericText(qs(selectors.bankAmount), split.bank || 0, false);
        setNumericText(qs(selectors.pendingAmount), split.pending || 0, false);

        // method highlight
        setSelectedMethod(payment.method || 'cash');

        // installments UI
        var enableEl = qs(selectors.installmentEnable);
        var countEl = qs(selectors.installmentCount);
        var advEl = qs(selectors.installmentAdvance);
        var perEl = qs(selectors.installmentPerAmount);

        if (enableEl) {
            enableEl.checked = !!inst.enabled;
        }
        if (countEl) {
            countEl.value = inst.enabled ? (inst.count || 0) : (countEl.value || '');
        }
        if (advEl) {
            setNumericText(advEl, inst.enabled ? (inst.advance || 0) : 0, false);
        }
        if (perEl) {
            setNumericText(perEl, inst.enabled ? (inst.perInstallment || 0) : 0, true);
        }

        renderInstallmentSchedule(inst.enabled ? inst.schedule || [] : []);

        // payment notice (simple hint)
        var notice = qs(selectors.paymentNotice);
        if (notice) {
            if (totals.remainingDue > 0 && !inst.enabled && split.pending <= 0) {
                notice.textContent = 'ابھی کچھ رقم باقی ہے، آپ Pending یا Installments منتخب کر سکتے ہیں۔';
            } else if (inst.enabled && totals.remainingDue > 0) {
                notice.textContent = 'قسطوں کی رقم اور تاریخوں کو چیک کر لیں، پھر سیل مکمل کریں۔';
            } else {
                notice.textContent = '';
            }
        }
    }

    function notifyPaymentUpdated() {
        try {
            document.dispatchEvent(new CustomEvent('jwpm_pos_payment_updated', {
                detail: {
                    payment: Pos.state.payment || {}
                }
            }));
        } catch (e) {
            // ignore
        }
    }

    // -------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------
    function handleMethodChange(event) {
        var target = event.target;
        if (!target) return;
        if (target.name !== 'jwpm-pos-payment-method') return;
        recalcPayment();
    }

    function handleAmountInput() {
        recalcPayment();
    }

    function handleInstallmentToggle() {
        recalcPayment();
    }

    function handleInstallmentFieldsChange() {
        recalcPayment();
    }

    function handleCartTotalsUpdated() {
        syncFromCartTotals();
    }

    // -------------------------------------------------------------
    // Initialisation
    // -------------------------------------------------------------
    function initPaymentModule() {
        installCartTotalsHook();
        syncFromCartTotals();

        // method change
        qsa(selectors.paymentMethodGroup).forEach(function (r) {
            r.addEventListener('change', handleMethodChange);
        });

        // amounts
        var fields = [
            selectors.cashAmount,
            selectors.cardAmount,
            selectors.bankAmount,
            selectors.pendingAmount
        ];
        fields.forEach(function (sel) {
            var el = qs(sel);
            if (el) {
                el.addEventListener('input', handleAmountInput);
                el.addEventListener('change', handleAmountInput);
            }
        });

        // installments
        var enableEl = qs(selectors.installmentEnable);
        if (enableEl) {
            enableEl.addEventListener('change', handleInstallmentToggle);
        }
        var countEl = qs(selectors.installmentCount);
        if (countEl) {
            countEl.addEventListener('input', handleInstallmentFieldsChange);
            countEl.addEventListener('change', handleInstallmentFieldsChange);
        }
        var advEl = qs(selectors.installmentAdvance);
        if (advEl) {
            advEl.addEventListener('input', handleInstallmentFieldsChange);
            advEl.addEventListener('change', handleInstallmentFieldsChange);
        }

        // cart totals updated event (Part 3 override کے ساتھ extra safety)
        document.addEventListener('jwpm_pos_cart_totals_updated', handleCartTotalsUpdated);

        // پہلی بار recalc
        recalcPayment();

        // Public API
        Pos.payment = Pos.payment || {};
        Pos.payment.getSummary = function () {
            return Pos.state.payment || {};
        };
        Pos.payment.recalc = function () {
            recalcPayment();
        };
    }

    if (typeof Pos.onReady === 'function') {
        Pos.onReady(initPaymentModule);
    } else {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPaymentModule);
        } else {
            initPaymentModule();
        }
    }

})(window, document);
// 🔴 یہاں پر POS Payment + Installments ختم ہو رہا ہے

// ✅ Syntax verified block end
/** Part 6 — POS Complete Sale Request */
/**
 * Complete Sale Request:
 * - Cart + Payment + Customer + Meta کو ایک مکمل payload میں جمع کرتا ہے
 * - Basic validation کرتا ہے (cart empty, totalDue > 0 وغیرہ)
 * - AJAX کے ذریعے "complete sale" endpoint پر request بھیجتا ہے
 * - Success پر cart / payment reset، اور optional redirect / print
 * - custom events:
 *      - jwpm_pos_before_complete_sale
 *      - jwpm_pos_sale_completed
 *      - jwpm_pos_sale_failed
 */

// 🟢 یہاں سے POS Complete Sale Request شروع ہو رہا ہے
(function (window, document) {
    'use strict';

    if (!window.JWPMPos) {
        console.warn('JWPM POS: JWPMPos root object نہیں ملا، Complete Sale Module initialise نہیں ہو سکا۔');
        return;
    }

    var Pos = window.JWPMPos;
    var jwpmPosData = window.jwpmPosData || window.JWPM_POS_DATA || {};

    var selectors = {
        completeBtn: '#jwpm-pos-complete-sale-btn',
        errorNotice: '#jwpm-pos-complete-sale-error',
        loadingOverlay: '#jwpm-pos-loading-overlay',

        saleNote: '#jwpm-pos-sale-note',
        saleDate: '#jwpm-pos-sale-date',
        saleRef: '#jwpm-pos-sale-ref',
        saleBranch: '#jwpm-pos-sale-branch',
        saleType: '#jwpm-pos-sale-type', // e.g. "Cash", "Credit", "Old Gold Exchange"
        OldGoldSummary: '#jwpm-pos-old-gold-summary' // can be hidden field / textarea (JSON/string)
    };

    var cssClasses = {
        btnLoading: 'is-loading',
        disabled: 'is-disabled',
        overlayVisible: 'is-visible'
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function getAjaxConfig() {
        var actions = jwpmPosData.actions || {};
        var nonces = jwpmPosData.nonces || {};
        return {
            url: jwpmPosData.ajax_url || window.ajaxurl || '',
            action: actions.complete_sale || 'jwpm_pos_complete_sale',
            nonce: nonces.complete_sale || jwpmPosData.nonce_complete_sale || ''
        };
    }

    function wpAjaxRequest(payload) {
        if (Pos.utils && typeof Pos.utils.wpAjax === 'function') {
            return Pos.utils.wpAjax(payload);
        }

        var url = payload.url || (jwpmPosData.ajax_url || window.ajaxurl || '');
        var data = payload.data || {};

        var body = new URLSearchParams();
        Object.keys(data).forEach(function (key) {
            if (data[key] !== undefined && data[key] !== null) {
                body.append(key, typeof data[key] === 'object'
                    ? JSON.stringify(data[key])
                    : data[key]
                );
            }
        });

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function (res) {
            return res.json();
        });
    }

    // -------------------------------------------------------------
    // Payload Builders
    // -------------------------------------------------------------
    function gatherCartPayload() {
        var items = [];
        if (Pos.cart && typeof Pos.cart.getItems === 'function') {
            var cartItems = Pos.cart.getItems() || [];
            items = cartItems.map(function (it) {
                return {
                    row_id: it._rowId || null,
                    id: it.id || it.item_id || null,
                    name: it.name || '',
                    code: it.code || '',
                    karat: it.karat || '',
                    weight: it.weight || 0,
                    unit_price: it.unitPrice || 0,
                    qty: it.qty || 0,
                    line_discount: it.lineDiscount || 0,
                    line_total: it.lineTotal || 0
                };
            });
        }

        var totals = Pos.state.cartTotals || {
            subtotal: 0,
            overallDiscount: 0,
            oldGoldNet: 0,
            grandTotal: 0
        };

        return {
            items: items,
            totals: {
                subtotal: totals.subtotal || 0,
                overall_discount: totals.overallDiscount || 0,
                old_gold_net: totals.oldGoldNet || 0,
                grand_total: totals.grandTotal || 0
            }
        };
    }

    function gatherPaymentPayload() {
        var summary = {};
        if (Pos.payment && typeof Pos.payment.getSummary === 'function') {
            summary = Pos.payment.getSummary() || {};
        } else {
            summary = Pos.state.payment || {};
        }

        var totals = summary.totals || {};
        var split = summary.split || {};
        var inst = summary.installment || {};

        return {
            method: summary.method || 'cash',
            totals: {
                total_due: totals.totalDue || 0,
                amount_paid: totals.amountPaid || 0,
                remaining_due: totals.remainingDue || 0
            },
            split: {
                cash: split.cash || 0,
                card: split.card || 0,
                bank: split.bank || 0,
                pending: split.pending || 0
            },
            installment: {
                enabled: !!inst.enabled,
                count: inst.count || 0,
                advance: inst.advance || 0,
                per_installment: inst.perInstallment || 0,
                schedule: (inst.schedule || []).map(function (row) {
                    return {
                        index: row.index || 0,
                        amount: row.amount || 0,
                        due_date: row.dueDate instanceof Date
                            ? row.dueDate.toISOString()
                            : (row.dueDate || '')
                    };
                })
            }
        };
    }

    function gatherCustomerPayload() {
        var customer = null;
        if (Pos.customer && typeof Pos.customer.getSelected === 'function') {
            customer = Pos.customer.getSelected();
        } else {
            customer = Pos.state.customer || null;
        }

        if (!customer) {
            return {
                id: null,
                type: 'guest',
                name: 'Guest',
                phone: '',
                email: '',
                loyalty_points: 0
            };
        }

        return {
            id: customer.id || null,
            type: 'registered',
            name: customer.name || '',
            phone: customer.phone || '',
            email: customer.email || '',
            loyalty_points: customer.loyalty_points || 0,
            raw: customer.raw || customer
        };
    }

    function gatherMetaPayload() {
        var noteEl = qs(selectors.saleNote);
        var dateEl = qs(selectors.saleDate);
        var refEl = qs(selectors.saleRef);
        var branchEl = qs(selectors.saleBranch);
        var typeEl = qs(selectors.saleType);
        var oldGoldSummaryEl = qs(selectors.OldGoldSummary);

        var meta = {
            note: noteEl ? noteEl.value || '' : '',
            sale_date: dateEl ? (dateEl.value || '') : '',
            reference_no: refEl ? (refEl.value || '') : '',
            branch: branchEl ? (branchEl.value || '') : '',
            sale_type: typeEl ? (typeEl.value || '') : '',
            old_gold_summary: oldGoldSummaryEl ? (oldGoldSummaryEl.value || oldGoldSummaryEl.textContent || '') : ''
        };

        // کچھ global context بھی شامل کر دیں (اگر available ہو)
        if (jwpmPosData.context) {
            meta.context = jwpmPosData.context;
        }
        if (jwpmPosData.pos_register_id) {
            meta.pos_register_id = jwpmPosData.pos_register_id;
        }

        return meta;
    }

    // -------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------
    function validateSalePayload(sale) {
        var errors = [];

        // Cart
        if (!sale.cart || !Array.isArray(sale.cart.items) || !sale.cart.items.length) {
            errors.push('Please add at least one item to the cart.');
        }

        var totalDue = (sale.payment && sale.payment.totals && sale.payment.totals.total_due) || 0;
        if (totalDue <= 0) {
            errors.push('Total amount must be greater than zero.');
        }

        // Simple sanity check: اگر pending / installment دونوں نہیں ہیں اور remaining_due > 0 ہو
        var remainingDue = (sale.payment && sale.payment.totals && sale.payment.totals.remaining_due) || 0;
        var payMethod = (sale.payment && sale.payment.method) || 'cash';
        var instEnabled = sale.payment && sale.payment.installment && sale.payment.installment.enabled;

        if (!instEnabled && payMethod !== 'pending' && remainingDue > 0.01) {
            errors.push('Some amount is still unpaid. Please adjust payment or mark as pending/installment.');
        }

        // اگر installment enabled ہے لیکن count zero ہے
        if (instEnabled && (!sale.payment.installment.count || sale.payment.installment.count < 1)) {
            errors.push('Installment count must be at least 1.');
        }

        return {
            valid: !errors.length,
            errors: errors
        };
    }

    // -------------------------------------------------------------
    // UI Helpers
    // -------------------------------------------------------------
    function showError(message) {
        var box = qs(selectors.errorNotice);
        if (!box) {
            if (message) {
                alert(message); // fall-back, صرف emergency کیلئے
            }
            return;
        }

        box.textContent = message || '';
        box.style.display = message ? '' : 'none';
    }

    function showErrors(list) {
        if (!list || !list.length) {
            showError('');
            return;
        }
        // ایک لائن میں جوائن، UI انگریزی میں رہے گا
        showError(list.join(' '));
    }

    function setLoadingState(isLoading) {
        var btn = qs(selectors.completeBtn);
        var overlay = qs(selectors.loadingOverlay);

        if (btn) {
            btn.disabled = !!isLoading;
            btn.classList.toggle(cssClasses.btnLoading, !!isLoading);
            btn.classList.toggle(cssClasses.disabled, !!isLoading);
        }
        if (overlay) {
            overlay.classList.toggle(cssClasses.overlayVisible, !!isLoading);
        }
    }

    function showToast(type, message) {
        // اگر Pos.utils.toast موجود ہو تو وہ استعمال کریں، ورنہ console
        if (Pos.utils && typeof Pos.utils.toast === 'function') {
            Pos.utils.toast(type, message);
        } else if (message) {
            if (type === 'error') {
                console.error('JWPM POS:', message);
            } else {
                console.log('JWPM POS:', message);
            }
        }
    }

    // -------------------------------------------------------------
    // Main Complete Sale Flow
    // -------------------------------------------------------------
    function buildSalePayload() {
        var cartPayload = gatherCartPayload();
        var paymentPayload = gatherPaymentPayload();
        var customerPayload = gatherCustomerPayload();
        var metaPayload = gatherMetaPayload();

        return {
            cart: cartPayload,
            payment: paymentPayload,
            customer: customerPayload,
            meta: metaPayload
        };
    }

    function handleCompleteSaleClick(event) {
        event.preventDefault();

        var ajaxCfg = getAjaxConfig();
        if (!ajaxCfg.url) {
            showError('AJAX URL is missing. Please check POS settings.');
            console.warn('JWPM POS: ajax_url missing, cannot complete sale.');
            return;
        }

        var salePayload = buildSalePayload();
        var validation = validateSalePayload(salePayload);

        if (!validation.valid) {
            showErrors(validation.errors);
            showToast('error', 'Please fix the highlighted issues before completing the sale.');
            return;
        }

        showErrors([]);
        setLoadingState(true);

        try {
            document.dispatchEvent(new CustomEvent('jwpm_pos_before_complete_sale', {
                detail: { sale: salePayload }
            }));
        } catch (e) {
            // ignore
        }

        wpAjaxRequest({
            url: ajaxCfg.url,
            data: {
                action: ajaxCfg.action,
                nonce: ajaxCfg.nonce,
                sale: salePayload
            }
        }).then(function (response) {
            setLoadingState(false);

            if (!response) {
                showError('Unexpected server response. Please try again.');
                showToast('error', 'Unexpected server response.');
                try {
                    document.dispatchEvent(new CustomEvent('jwpm_pos_sale_failed', {
                        detail: { sale: salePayload, response: response }
                    }));
                } catch (e) {}
                return;
            }

            if (response.success === false) {
                var msg = (response.data && (response.data.message || response.data.error)) ||
                    'Could not complete the sale. Please try again.';
                showError(msg);
                showToast('error', msg);
                try {
                    document.dispatchEvent(new CustomEvent('jwpm_pos_sale_failed', {
                        detail: { sale: salePayload, response: response }
                    }));
                } catch (e) {}
                return;
            }

            // Success
            showError('');
            var successMsg = (response.data && response.data.message) || 'Sale completed successfully.';
            showToast('success', successMsg);

            try {
                document.dispatchEvent(new CustomEvent('jwpm_pos_sale_completed', {
                    detail: { sale: salePayload, response: response }
                }));
            } catch (e) {}

            // Cart & Payment reset (simple version)
            if (Pos.cart && typeof Pos.cart.clear === 'function') {
                Pos.cart.clear();
            }

            if (Pos.payment && typeof Pos.payment.recalc === 'function') {
                // payment state کو fresh cart totals کے ساتھ sync کرنے کیلئے
                Pos.payment.recalc();
            } else {
                // fallback: state reset
                Pos.state.payment = {
                    method: 'cash',
                    totals: {
                        totalDue: 0,
                        amountPaid: 0,
                        remainingDue: 0
                    },
                    split: {
                        cash: 0,
                        card: 0,
                        bank: 0,
                        pending: 0
                    },
                    installment: {
                        enabled: false,
                        count: 0,
                        advance: 0,
                        perInstallment: 0,
                        schedule: []
                    }
                };
            }

            // Inputs clear
            var noteEl = qs(selectors.saleNote);
            if (noteEl) {
                noteEl.value = '';
            }
            var refEl = qs(selectors.saleRef);
            if (refEl) {
                refEl.value = '';
            }

            // If server sent redirect url (e.g. invoice print)
            if (response.data && response.data.redirect_url) {
                window.location.href = response.data.redirect_url;
                return;
            }

            // اگر popup invoice open کرنی ہو (optional)
            if (response.data && response.data.invoice_url && response.data.open_invoice === true) {
                window.open(response.data.invoice_url, '_blank');
            }

        }).catch(function (error) {
            setLoadingState(false);
            console.error('JWPM POS: Complete sale error', error);
            showError('Could not connect to the server. Please try again.');
            showToast('error', 'Network error while completing the sale.');

            try {
                document.dispatchEvent(new CustomEvent('jwpm_pos_sale_failed', {
                    detail: { sale: salePayload, error: error }
                }));
            } catch (e) {}
        });
    }

    // -------------------------------------------------------------
    // Initialisation
    // -------------------------------------------------------------
    function initCompleteSaleModule() {
        var btn = qs(selectors.completeBtn);
        if (!btn) {
            console.warn('JWPM POS: Complete Sale button نہیں ملا، sale submit دستی طور پر ہینڈل کرنا ہو گا۔');
            return;
        }

        btn.addEventListener('click', handleCompleteSaleClick);
    }

    if (typeof Pos.onReady === 'function') {
        Pos.onReady(initCompleteSaleModule);
    } else {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCompleteSaleModule);
        } else {
            initCompleteSaleModule();
        }
    }

})(window, document);
// 🔴 یہاں پر POS Complete Sale Request ختم ہو رہا ہے

// ✅ Syntax verified block end

