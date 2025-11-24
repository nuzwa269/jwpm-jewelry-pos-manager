/**
 * JWPM Inventory JS
 *
 * Summary (Part 1 + Part 2):
 * - پیج لوڈ ہونے پر Root کو mount کرنا
 * - Templates لوڈ کرنا
 * - AJAX سے Inventory List لینا
 * - Summary Cards، Table Rows رینڈر کرنا
 * - Filters apply/reset
 * - Pagination
 * - Modals (New/Edit Item, Import, Demo Data)
 * - Save / Delete Item
 *
 * نوٹ: یہ مکمل اپڈیٹڈ فائل ہے، پرانی jwpm-inventory.js کو اسی سے ریپلیس کریں۔
 */

(function ($) {
	"use strict";

	/** Part 1 — Core helpers, state اور Initial UI **/

	// 🟢 یہاں سے [Soft Warning Helper] شروع ہو رہا ہے
	function softWarn(msg) {
		console.warn("JWPM Warning:", msg);
	}
	// 🔴 یہاں پر [Soft Warning Helper] ختم ہو رہا ہے

	// 🟢 یہاں سے [AJAX Helper] شروع ہو رہا ہے
	async function wpAjax(action, body = {}) {
		body.action = action;
		body.security = jwpmInventoryData.nonce;

		try {
			const res = await $.post(jwpmCommon.ajax_url, body);
			if (!res) {
				return { success: false, data: { message: "Empty response." } };
			}
			// (wp_send_json_success) → { success:true, data:{…} }
			// (wp_send_json_error)   → { success:false, data:{message,…} }
			return res;
		} catch (e) {
			console.error("AJAX Error:", e);
			return {
				success: false,
				data: { message: "Network error. Please check your connection." },
			};
		}
	}
	// 🔴 یہاں پر [AJAX Helper] ختم ہو رہا ہے

	// 🟢 یہاں سے [Template Mount Helper] شروع ہو رہا ہے
	function mountTemplate(tid) {
		const tpl = document.getElementById(tid);
		if (!tpl) {
			softWarn("Template not found: " + tid);
			return null;
		}
		return tpl.content.cloneNode(true);
	}
	// 🔴 یہاں پر [Template Mount Helper] ختم ہو رہا ہے

	// 🟢 یہاں سے [Toast Helper] شروع ہو رہا ہے
	function showToast(message, type = "info") {
		// سادہ (alert) fallback – بعد میں چاہیں تو خوبصورت ٹوسٹ بنا سکتے ہیں
		alert(message);
	}
	// 🔴 یہاں پر [Toast Helper] ختم ہو رہا ہے

	// 🟢 یہاں سے [JWPM_Inventory Main Object] شروع ہو رہا ہے
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
			this.bindEvents();
			this.loadItems();
		},

		// Root کے اندر Summary, Filters, Main Panel mount کریں
		renderInitialUI() {
			this.root.innerHTML = "";

			const summary = mountTemplate("jwpm-inventory-summary-template");
			const filters = mountTemplate("jwpm-inventory-filters-template");
			const main = mountTemplate("jwpm-inventory-main-template");

			if (summary) this.root.appendChild(summary);
			if (filters) this.root.appendChild(filters);
			if (main) this.root.appendChild(main);

			// Loader کو آخر میں append رہنے دیں (PHP میں تھا)
			const loader = document.createElement("div");
			loader.className = "jwpm-loading-state";
			loader.innerHTML =
				'<span class="jwpm-spinner"></span><span class="jwpm-loading-text">Loading…</span>';
			loader.style.display = "none";
			this.root.appendChild(loader);
		},

		/** Part 2 — Events, Filters, Modals, CRUD **/

		// تمام UI Events بائنڈ کریں
		bindEvents() {
			const self = this;

			// Filters — change / keyup پر apply نہیں، صرف state اپڈیٹ
			const filterInputs = this.root.querySelectorAll(".js-jwpm-filter-input");
			filterInputs.forEach((el) => {
				const key = el.dataset.filterKey;
				if (!key) return;

				const handler = () => {
					let val = el.value;
					if (el.type === "number" && val !== "") {
						val = Number(val);
					}
					self.state.filters[key] = val;
				};

				el.addEventListener("change", handler);
				if (el.tagName === "INPUT") {
					el.addEventListener("keyup", handler);
				}
			});

			// Apply Filters
			const applyBtn = this.root.querySelector(".js-jwpm-filter-apply");
			if (applyBtn) {
				applyBtn.addEventListener("click", () => {
					self.state.page = 1;
					self.loadItems();
				});
			}

			// Reset Filters
			const resetBtn = this.root.querySelector(".js-jwpm-filter-reset");
			if (resetBtn) {
				resetBtn.addEventListener("click", () => {
					self.resetFilters();
				});
			}

			// Top actions: New / Import / Print / Demo
			const newBtn = this.root.querySelector(".js-jwpm-open-item-modal");
			if (newBtn) {
				newBtn.addEventListener("click", () => {
					self.openItemModal(null);
				});
			}

			const importBtn = this.root.querySelector(".js-jwpm-open-import-modal");
			if (importBtn) {
				importBtn.addEventListener("click", () => {
					self.openImportModal();
				});
			}

			const demoBtn = this.root.querySelector(".js-jwpm-open-demo-modal");
			if (demoBtn) {
				demoBtn.addEventListener("click", () => {
					self.openDemoModal();
				});
			}

			const printBtn = this.root.querySelector(".js-jwpm-print-table");
			if (printBtn) {
				printBtn.addEventListener("click", () => {
					window.print(); // Simple fallback، بعد میں custom print بھی بنا سکتے ہیں
				});
			}

			// Pagination Events (renderPagination میں onClick بھی سیٹ ہو رہے ہیں، یہاں کچھ extra نہیں)

			// Row Actions – event delegation
			const itemsTable = this.root.querySelector(".js-jwpm-items-table");
			if (itemsTable) {
				itemsTable.addEventListener("click", (e) => {
					const btn = e.target.closest("button");
					if (!btn) return;

					const row = e.target.closest("tr");
					if (!row) return;

					const id = Number(row.dataset.itemId || 0);
					if (!id) return;

					if (btn.classList.contains("js-jwpm-view-item")) {
						self.viewItem(id);
					} else if (btn.classList.contains("js-jwpm-edit-item")) {
						self.editItem(id);
					} else if (btn.classList.contains("js-jwpm-delete-item")) {
						self.deleteItem(id);
					} else if (btn.classList.contains("js-jwpm-adjust-stock")) {
						self.adjustStock(id);
					}
				});
			}

			// Detail panel close (Esc)
			document.addEventListener("keydown", (e) => {
				if (e.key === "Escape") {
					self.closeDetailPanel();
					self.closeTopModal();
				}
			});
		},

		resetFilters() {
			// UI صاف
			const inputs = this.root.querySelectorAll(".js-jwpm-filter-input");
			inputs.forEach((el) => {
				if (el.tagName === "SELECT") {
					el.value = "";
				} else {
					el.value = "";
				}
			});

			// State صاف
			this.state.filters = {};
			this.state.page = 1;
			this.loadItems();
		},

		// انوینٹری لسٹ لوڈ کریں
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
				const msg = (res.data && res.data.message) || "Unable to load items.";
				showToast(msg, "error");
				return;
			}

			const data = res.data || {};
			const items = data.items || [];

			this.state.total = Number(data.total || 0);

			this.renderSummary(items);
			this.renderTable(items);
			this.renderPagination();
		},

		// Summary Cards میں ڈیٹا
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
			const totalEl = root.querySelector(
				'[data-metric="total_items"] .js-jwpm-summary-value'
			);
			const weightEl = root.querySelector(
				'[data-metric="total_weight"] .js-jwpm-summary-value'
			);
			const lowEl = root.querySelector(
				'[data-metric="low_stock"] .js-jwpm-summary-value'
			);
			const deadEl = root.querySelector(
				'[data-metric="dead_stock"] .js-jwpm-summary-value'
			);

			if (totalEl) totalEl.textContent = totalItems;
			if (weightEl) weightEl.textContent = totalWeight.toFixed(2);
			if (lowEl) lowEl.textContent = lowStock;
			if (deadEl) deadEl.textContent = deadStock;
		},

		// Table رینڈرنگ
		renderTable(items) {
			const tbody = this.root.querySelector(".js-jwpm-items-tbody");
			if (!tbody) return;

			tbody.innerHTML = "";

			if (!items.length) {
				tbody.innerHTML = `
					<tr class="jwpm-table-empty">
						<td colspan="11" style="text-align:center; padding:20px;">
							${"No items found. Try adjusting filters or create a new item."}
						</td>
					</tr>
				`;
				return;
			}

			items.forEach((itm) => {
				const rowFrag = mountTemplate("jwpm-inventory-row-template");
				if (!rowFrag) return;

				const tr = rowFrag.querySelector("tr");
				tr.dataset.itemId = itm.id;

				rowFrag.querySelector(".js-jwpm-tag").textContent =
					itm.tag_serial || "-";
				rowFrag.querySelector(".js-jwpm-category").textContent =
					itm.category || "-";
				rowFrag.querySelector(".js-jwpm-karat").textContent =
					itm.karat || "-";
				rowFrag.querySelector(".js-jwpm-gross").textContent =
					itm.gross_weight || "0";
				rowFrag.querySelector(".js-jwpm-net").textContent =
					itm.net_weight || "0";

				let stonesText = "-";
				if (itm.stone_type) {
					stonesText = itm.stone_type;
					if (itm.stone_carat) {
						stonesText += " (" + itm.stone_carat + ")";
					}
				}
				rowFrag.querySelector(".js-jwpm-stones").textContent = stonesText;

				rowFrag.querySelector(".js-jwpm-branch").textContent =
					itm.branch_name || itm.branch_id || "-";

				const badge = rowFrag.querySelector(".js-jwpm-status-badge");
				const st = itm.status || "in_stock";
				badge.textContent = this.prettyStatus(st);
				badge.className = "jwpm-status-badge jwpm-status-" + st;

				tbody.appendChild(rowFrag);
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
					return "Scrap / Old Gold";
				default:
					return st || "-";
			}
		},

		// Pagination info + buttons
		renderPagination() {
			const totalPages = Math.max(
				1,
				Math.ceil(this.state.total / this.state.per_page)
			);
			const info = this.root.querySelector(".js-jwpm-page-info");
			const prev = this.root.querySelector(".js-jwpm-page-prev");
			const next = this.root.querySelector(".js-jwpm-page-next");

			if (!info) return;

			info.textContent = `Page ${this.state.page} of ${totalPages}`;

			if (prev) {
				prev.disabled = this.state.page <= 1;
				prev.onclick = () => {
					if (this.state.page > 1) {
						this.state.page--;
						this.loadItems();
					}
				};
			}

			if (next) {
				next.disabled = this.state.page >= totalPages;
				next.onclick = () => {
					if (this.state.page < totalPages) {
						this.state.page++;
						this.loadItems();
					}
				};
			}
		},

		showLoading(state) {
			if (!this.root) return;
			const loader = this.root.querySelector(".jwpm-loading-state");
			if (!loader) return;
			loader.style.display = state ? "flex" : "none";
		},

		/** Modals & CRUD **/

		// نیا آئٹم یا ایڈٹ آئٹم موڈل کھولیں
		openItemModal(item) {
			const frag = mountTemplate("jwpm-inventory-item-modal-template");
			if (!frag) return;

			const modal = frag.querySelector(".jwpm-modal");
			const form = frag.querySelector(".js-jwpm-item-form");
			const titleEl = frag.querySelector(".js-jwpm-modal-title");

			// Close handlers
			const closeButtons = frag.querySelectorAll(".js-jwpm-modal-close");
			closeButtons.forEach((btn) => {
				btn.addEventListener("click", () => {
					modal.remove();
				});
			});

			// Body میں append
			document.body.appendChild(frag);

			// اگر Edit موڈ ہے تو ڈیٹا فل کریں
			if (item) {
				if (titleEl) {
					titleEl.textContent = "Edit Inventory Item";
				}
				form.querySelector(".js-jwpm-item-id").value = item.id || 0;
				form.querySelector('[name="sku"]').value = item.sku || "";
				form.querySelector('[name="tag_serial"]').value =
					item.tag_serial || "";
				form.querySelector('[name="category"]').value = item.category || "";
				form.querySelector('[name="metal_type"]').value =
					item.metal_type || "";
				form.querySelector('[name="karat"]').value = item.karat || "";
				form.querySelector('[name="gross_weight"]').value =
					item.gross_weight || "";
				form.querySelector('[name="net_weight"]').value =
					item.net_weight || "";
				form.querySelector('[name="stone_type"]').value =
					item.stone_type || "";
				form.querySelector('[name="stone_carat"]').value =
					item.stone_carat || "";
				form.querySelector('[name="stone_qty"]').value =
					item.stone_qty || "";
				form.querySelector('[name="labour_amount"]').value =
					item.labour_amount || "";
				form.querySelector('[name="design_no"]').value =
					item.design_no || "";
				form.querySelector('[name="status"]').value = item.status || "in_stock";
				const branchSelect = form.querySelector('[name="branch_id"]');
				if (branchSelect && item.branch_id) {
					branchSelect.value = item.branch_id;
				}
			}

			// Submit handler
			form.addEventListener("submit", async (e) => {
				e.preventDefault();

				const formData = new FormData(form);
				const payload = {
					id: Number(formData.get("id") || 0),
					sku: String(formData.get("sku") || ""),
					tag_serial: String(formData.get("tag_serial") || ""),
					category: String(formData.get("category") || ""),
					metal_type: String(formData.get("metal_type") || ""),
					karat: String(formData.get("karat") || ""),
					gross_weight: formData.get("gross_weight") || 0,
					net_weight: formData.get("net_weight") || 0,
					stone_type: String(formData.get("stone_type") || ""),
					stone_carat: formData.get("stone_carat") || 0,
					stone_qty: formData.get("stone_qty") || 0,
					labour_amount: formData.get("labour_amount") || 0,
					design_no: String(formData.get("design_no") || ""),
					status: String(formData.get("status") || "in_stock"),
					branch_id: formData.get("branch_id") || jwpmInventoryData.default_branch,
					is_demo: formData.get("is_demo") ? 1 : 0,
				};

				// Basic validation
				if (!payload.sku || !payload.tag_serial) {
					showToast(
						"SKU اور Tag ID دونوں لازمی ہیں (SKU and Tag ID are required).",
						"error"
					);
					return;
				}

				const res = await wpAjax(jwpmInventoryData.save_action, payload);

				if (!res.success) {
					const msg =
						(res.data && res.data.message) ||
						"Failed to save inventory item.";
					showToast(msg, "error");
					return;
				}

				showToast("Item saved successfully.", "success");
				modal.remove();
				// دوبارہ لسٹ لوڈ کریں
				this.loadItems();
			});
		},

		// Import modal
		openImportModal() {
			const frag = mountTemplate("jwpm-inventory-import-modal-template");
			if (!frag) return;

			const modal = frag.querySelector(".jwpm-modal");
			const closeButtons = frag.querySelectorAll(".js-jwpm-modal-close");
			closeButtons.forEach((btn) => {
				btn.addEventListener("click", () => modal.remove());
			});

			const downloadBtn = frag.querySelector(".js-jwpm-download-sample");
			if (downloadBtn) {
				downloadBtn.addEventListener("click", () => {
					// Developer hint: بعد میں sample (CSV/Excel) فائل جنریٹ کریں گے
					showToast("Sample download not implemented yet.", "info");
				});
			}

			const startBtn = frag.querySelector(".js-jwpm-start-import");
			if (startBtn) {
				startBtn.addEventListener("click", () => {
					// ابھی placeholder – backend بھی placeholder ہے
					showToast("Import feature coming soon.", "info");
				});
			}

			document.body.appendChild(frag);
		},

		// Demo Data modal
		openDemoModal() {
			const frag = mountTemplate("jwpm-inventory-demo-modal-template");
			if (!frag) return;

			const modal = frag.querySelector(".jwpm-modal");
			const closeButtons = frag.querySelectorAll(".js-jwpm-modal-close");
			closeButtons.forEach((btn) => {
				btn.addEventListener("click", () => modal.remove());
			});

			const create10 = frag.querySelector(".js-jwpm-create-demo-10");
			const create100 = frag.querySelector(".js-jwpm-create-demo-100");
			const deleteDemo = frag.querySelector(".js-jwpm-delete-demo-items");

			if (create10) {
				create10.addEventListener("click", () => {
					this.handleDemoAction("create_10");
				});
			}
			if (create100) {
				create100.addEventListener("click", () => {
					this.handleDemoAction("create_100");
				});
			}
			if (deleteDemo) {
				deleteDemo.addEventListener("click", () => {
					if (
						confirm(
							"کیا آپ واقعی تمام Demo Items ڈیلیٹ کرنا چاہتے ہیں؟ (Are you sure?)"
						)
					) {
						this.handleDemoAction("delete_all");
					}
				});
			}

			document.body.appendChild(frag);
		},

		async handleDemoAction(mode) {
			const res = await wpAjax(jwpmInventoryData.demo_action, { mode });

			if (!res.success) {
				const msg =
					(res.data && res.data.message) ||
					"Demo data action failed.";
				showToast(msg, "error");
				return;
			}

			showToast("Demo data action completed.", "success");
			this.loadItems();
		},

		// View item detail – فی الحال سادہ alert، بعد میں side panel استعمال کر سکتے ہیں
		viewItem(id) {
			// Future: AJAX سے واحد آئٹم لے کر detail panel میں دکھائیں
			console.log("View item", id);
		},

		// Edit item – اسی لسٹ سے تلاش کر کے موڈل کھولیں
		editItem(id) {
			const row = this.root.querySelector('tr[data-item-id="' + id + '"]');
			if (!row) {
				softWarn("Row not found for id " + id);
				return;
			}

			// row سے basic ڈیٹا نکالیں – یہ approximation ہے، better ہے backend سے fresh record لو
			const item = {
				id: id,
				tag_serial: row.querySelector(".js-jwpm-tag")?.textContent || "",
				category: row.querySelector(".js-jwpm-category")?.textContent || "",
				karat: row.querySelector(".js-jwpm-karat")?.textContent || "",
				gross_weight: row.querySelector(".js-jwpm-gross")?.textContent || "",
				net_weight: row.querySelector(".js-jwpm-net")?.textContent || "",
				stone_type: row.querySelector(".js-jwpm-stones")?.textContent || "",
				branch_id: row.querySelector(".js-jwpm-branch")?.textContent || "",
				status: row
					.querySelector(".js-jwpm-status-badge")
					?.className.replace("jwpm-status-badge", "")
					.replace("jwpm-status-", "")
					.trim(),
			};

			this.openItemModal(item);
		},

		// Delete item
		async deleteItem(id) {
			if (
				!confirm(
					"کیا آپ واقعی یہ آئٹم ڈیلیٹ کرنا چاہتے ہیں؟ (This cannot be undone.)"
				)
			) {
				return;
			}

			const res = await wpAjax(jwpmInventoryData.delete_action, { id });

			if (!res.success) {
				const msg =
					(res.data && res.data.message) || "Failed to delete item.";
				showToast(msg, "error");
				return;
			}

			showToast("Item deleted successfully.", "success");
			this.loadItems();
		},

		// Adjust Stock – Future: الگ موڈل بنائیں (ابھی placeholder)
		adjustStock(id) {
			showToast(
				"Stock Adjustment ابھی implement نہیں ہوئی (placeholder).",
				"info"
			);
		},

		// Detail panel helpers (ابھی بہت basic)
		closeDetailPanel() {
			const panel = this.root.querySelector(".js-jwpm-detail-panel");
			if (!panel) return;
			panel.hidden = true;
		},

		closeTopModal() {
			const modal = document.querySelector(".jwpm-modal:last-of-type");
			// Optional
		},
	};
	// 🔴 یہاں پر [JWPM_Inventory Main Object] ختم ہو رہا ہے

	// 🟢 یہاں سے [DOM Ready Init] شروع ہو رہا ہے
	$(document).ready(() => {
		if (typeof jwpmInventoryData === "undefined") {
			softWarn("jwpmInventoryData is not defined. Inventory JS will not run.");
			return;
		}

		JWPM_Inventory.init();
	});
	// 🔴 یہاں پر [DOM Ready Init] ختم ہو رہا ہے
})(jQuery);

// ✅ Syntax verified block end
