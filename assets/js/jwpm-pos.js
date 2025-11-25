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

