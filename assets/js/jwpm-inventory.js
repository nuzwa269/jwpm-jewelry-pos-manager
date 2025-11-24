/**
 * JWPM Inventory JS
 *
 * Summary:
 * - پیج لوڈ ہونے پر Root کو mount کرنا
 * - Templates لوڈ کرنا
 * - AJAX سے Inventory List لینا
 * - Summary Cards، Table Rows رینڈر کرنا
 *
 * یہ صرف بنیادی بنیاد ہے (Part 1)،
 * آگے Modals، Save/Delete، Pagination، Filters (Part 2+) میں آئیں گے۔
 */

(function ($) {
	"use strict";

	// Soft warning utility
	function softWarn(msg) {
		console.warn("JWPM Warning:", msg);
	}

	// AJAX helper
	async function wpAjax(action, body = {}) {
		body.action = action;
		body.security = jwpmInventoryData.nonce;

		try {
			const res = await $.post(jwpmCommon.ajax_url, body);
			if (!res) return { success: false, message: "Empty response." };
			return res;
		} catch (e) {
			console.error("AJAX Error:", e);
			return { success: false, message: "Network error." };
		}
	}

	// Template mount helper
	function mountTemplate(tid) {
		const tpl = document.getElementById(tid);
		if (!tpl) {
			softWarn("Template not found: " + tid);
			return null;
		}
		return tpl.content.cloneNode(true);
	}

	// Main App Object
	const JWPM_Inventory = {
		root: null,
		state: {
			page: 1,
			per_page: jwpmInventoryData.per_page || 50,
			total: 0,
			filters: {},
		},

		init() {
			this.root = document.getElementById("jwpm-inventory-root");
			if (!this.root) {
				softWarn("#jwpm-inventory-root missing.");
				return;
			}

			this.renderInitialUI();
			this.loadItems();
		},

		// Inject templates into root
		renderInitialUI() {
			this.root.innerHTML = "";

			// Summary Cards
			this.root.appendChild(mountTemplate("jwpm-inventory-summary-template"));

			// Filters
			this.root.appendChild(mountTemplate("jwpm-inventory-filters-template"));

			// Main panel (Tabs + Table)
			this.root.appendChild(mountTemplate("jwpm-inventory-main-template"));
		},

		// Load Inventory list
		async loadItems() {
			const req = {
				page: this.state.page,
				per_page: this.state.per_page,
			};

			Object.assign(req, this.state.filters);

			this.showLoading(true);

			const res = await wpAjax(jwpmInventoryData.list_action, req);

			this.showLoading(false);

			if (!res.success) {
				alert("Failed: " + (res.data?.message || "Unable to load items."));
				return;
			}

			const data = res.data;
			this.state.total = data.total;

			this.renderSummary(data.items || []);
			this.renderTable(data.items || []);
			this.renderPagination();
		},

		// Summary Cards Logic
		renderSummary(items) {
			let totalItems = items.length;
			let totalWeight = 0;
			let lowStock = 0;
			let deadStock = 0;

			items.forEach((i) => {
				totalWeight += Number(i.net_weight || 0);

				if (i.status === "low_stock") lowStock++;
				if (i.status === "dead_stock") deadStock++;
			});

			const root = this.root;
			root.querySelector('[data-metric="total_items"] .js-jwpm-summary-value').textContent =
				totalItems;
			root.querySelector('[data-metric="total_weight"] .js-jwpm-summary-value').textContent =
				totalWeight.toFixed(2);
			root.querySelector('[data-metric="low_stock"] .js-jwpm-summary-value').textContent =
				lowStock;
			root.querySelector('[data-metric="dead_stock"] .js-jwpm-summary-value').textContent =
				deadStock;
		},

		// Table rendering
		renderTable(items) {
			const tbody = this.root.querySelector(".js-jwpm-items-tbody");
			if (!tbody) return;

			tbody.innerHTML = "";

			if (!items.length) {
				tbody.innerHTML = `
          <tr><td colspan="11" style="text-align:center; padding:20px;">
            No items found.
          </td></tr>
        `;
				return;
			}

			items.forEach((itm) => {
				const row = mountTemplate("jwpm-inventory-row-template");
				const tr = row.querySelector("tr");

				tr.dataset.itemId = itm.id;

				// Cells
				row.querySelector(".js-jwpm-tag").textContent = itm.tag_serial || "-";
				row.querySelector(".js-jwpm-category").textContent = itm.category || "-";
				row.querySelector(".js-jwpm-karat").textContent = itm.karat || "-";
				row.querySelector(".js-jwpm-gross").textContent = itm.gross_weight || "0";
				row.querySelector(".js-jwpm-net").textContent = itm.net_weight || "0";
				row.querySelector(".js-jwpm-stones").textContent = itm.stone_type || "-";
				row.querySelector(".js-jwpm-branch").textContent = itm.branch_id || "-";

				// Status badge
				const badge = row.querySelector(".js-jwpm-status-badge");
				badge.textContent = this.prettyStatus(itm.status);
				badge.className = "jwpm-status-badge jwpm-status-" + itm.status;

				tbody.appendChild(row);
			});
		},

		prettyStatus(st) {
			switch (st) {
				case "in_stock":
					return "In Stock";
				case "low_stock":
					return "Low Stock";
				case "dead_stock":
					return "Dead Stock";
				case "scrap":
					return "Scrap";
				default:
					return st;
			}
		},

		renderPagination() {
			const totalPages = Math.max(1, Math.ceil(this.state.total / this.state.per_page));
			const info = this.root.querySelector(".js-jwpm-page-info");
			const prev = this.root.querySelector(".js-jwpm-page-prev");
			const next = this.root.querySelector(".js-jwpm-page-next");

			if (!info) return;

			info.textContent = `Page ${this.state.page} of ${totalPages}`;

			if (prev) prev.disabled = this.state.page <= 1;
			if (next) next.disabled = this.state.page >= totalPages;

			if (prev) {
				prev.onclick = () => {
					if (this.state.page > 1) {
						this.state.page--;
						this.loadItems();
					}
				};
			}

			if (next) {
				next.onclick = () => {
					this.state.page++;
					this.loadItems();
				};
			}
		},

		showLoading(state) {
			if (!this.root) return;
			const loader = this.root.querySelector(".jwpm-loading-state");
			if (!loader) return;

			loader.style.display = state ? "flex" : "none";
		},
	};

	// DOM ready
	$(document).ready(() => {
		JWPM_Inventory.init();
	});
})(jQuery);

// ✅ Syntax verified block end

