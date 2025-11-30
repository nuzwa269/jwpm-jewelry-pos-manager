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
		
        // Nonce Handling (Global or Localized)
        if (typeof jwpmInventoryData !== 'undefined' && jwpmInventoryData.nonce) {
            body.security = jwpmInventoryData.nonce;
			body.nonce    = jwpmInventoryData.nonce; 
        } else if (typeof jwpmCommon !== 'undefined' && jwpmCommon.nonce_common) {
            body.security = jwpmCommon.nonce_common;
			body.nonce    = jwpmCommon.nonce_common; 
        }

        // URL Handling
        const url = (typeof jwpmCommon !== 'undefined') ? jwpmCommon.ajax_url : ajaxurl;

		try {
			const res = await $.post(url, body);
			if (!res) {
				return { success: false, data: { message: "Empty response." } };
			}
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
			// اگر ٹیمپلیٹ نہیں ملتا تو ہم JS میں ہی basic structure بنا لیتے ہیں تاکہ UI خالی نہ رہے۔
            // یہ ایک Fallback طریقہ ہے۔
            if (tid === "jwpm-inventory-main-template") {
                const div = document.createElement('div');
                div.innerHTML = `
                    <div class="jwpm-card">
                        <div class="jwpm-loading-state">
                            <p>Templates not loaded properly. Please check footer scripts.</p>
                        </div>
                        <table class="wp-list-table widefat fixed striped js-jwpm-items-table">
                            <thead>
                                <tr>
                                    <th>Tag ID</th>
                                    <th>Category</th>
                                    <th>Karat</th>
                                    <th>Gross Wt</th>
                                    <th>Net Wt</th>
                                    <th>Stones</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody class="js-jwpm-items-tbody"></tbody>
                        </table>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <span class="displaying-num js-jwpm-page-info"></span>
                                <span class="pagination-links">
                                    <button class="button js-jwpm-page-prev">« Prev</button>
                                    <button class="button js-jwpm-page-next">Next »</button>
                                </span>
                            </div>
                        </div>
                    </div>
                `;
                return div; // Return DOM element instead of document fragment
            }
			softWarn("Template not found: " + tid);
			return null;
		}
		return tpl.content.cloneNode(true);
	}
	// 🔴 یہاں پر [Template Mount Helper] ختم ہو رہا ہے

	// 🟢 یہاں سے [Toast Helper] شروع ہو رہا ہے
	function showToast(message, type = "info") {
		alert(message);
	}
	// 🔴 یہاں پر [Toast Helper] ختم ہو رہا ہے

	// 🟢 یہاں سے [JWPM_Inventory Main Object] شروع ہو رہا ہے
	const JWPM_Inventory = {
		root: null,
		state: {
			page: 1,
			per_page: (typeof jwpmInventoryData !== 'undefined') ? jwpmInventoryData.per_page : 50,
			total: 0,
			filters: {},
		},

		init() {
			this.root = document.getElementById("jwpm-inventory-root");
			if (!this.root) {
				return; // Silently fail if not on inventory page
			}

			this.renderInitialUI();
			this.bindEvents();
			this.loadItems();
		},

		// Root کے اندر UI بنائیں۔
        // چونکہ ہم نے HTML ٹیمپلیٹس PHP فائل میں شامل نہیں کیے (کیونکہ ہم JS Based UI بنا رہے ہیں)،
        // اس لیے ہم یہاں براہ راست HTML inject کریں گے۔
		renderInitialUI() {
			this.root.innerHTML = `
                <div class="jwpm-wrapper">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2>📦 Inventory Management</h2>
                        <div class="jwpm-actions">
                            <button class="jwpm-button js-jwpm-open-item-modal">+ New Item</button>
                            <button class="button js-jwpm-open-import-modal">Import</button>
                            <button class="button js-jwpm-open-demo-modal">Demo Data</button>
                        </div>
                    </div>

                    <div class="jwpm-card" style="padding:15px; margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <input type="text" class="js-jwpm-filter-input" data-filter-key="search" placeholder="Search by SKU / Tag..." style="padding:5px;">
                        
                        <select class="js-jwpm-filter-input" data-filter-key="category" style="padding:5px;">
                            <option value="">All Categories</option>
                            <option value="Ring">Ring</option>
                            <option value="Bangle">Bangle</option>
                            <option value="Necklace">Necklace</option>
                            <option value="Earring">Earring</option>
                        </select>

                        <select class="js-jwpm-filter-input" data-filter-key="status" style="padding:5px;">
                            <option value="">All Status</option>
                            <option value="in_stock">In Stock</option>
                            <option value="sold">Sold</option>
                            <option value="scrap">Scrap</option>
                        </select>

                        <button class="button button-primary js-jwpm-filter-apply">Apply Filters</button>
                        <button class="button js-jwpm-filter-reset">Reset</button>
                    </div>

                    <div class="jwpm-loading-state" style="display:none; text-align:center; padding:20px;">
                        <span class="spinner is-active" style="float:none;"></span> Loading Inventory...
                    </div>

                    <table class="wp-list-table widefat fixed striped js-jwpm-items-table">
                        <thead>
                            <tr>
                                <th>Tag / SKU</th>
                                <th>Category</th>
                                <th>Metal / Karat</th>
                                <th>Gross Wt</th>
                                <th>Net Wt</th>
                                <th>Stones</th>
                                <th>Design No</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody class="js-jwpm-items-tbody">
                            </tbody>
                    </table>

                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num js-jwpm-page-info"></span>
                            <span class="pagination-links">
                                <button class="button js-jwpm-page-prev" disabled>« Prev</button>
                                <button class="button js-jwpm-page-next" disabled>Next »</button>
                            </span>
                        </div>
                    </div>
                </div>
            `;
		},

		/** Part 2 — Events, Filters, Modals, CRUD **/

		bindEvents() {
			const self = this;

			// Filters State Update
			const filterInputs = this.root.querySelectorAll(".js-jwpm-filter-input");
			filterInputs.forEach((el) => {
				el.addEventListener("change", () => {
                    const key = el.dataset.filterKey;
					self.state.filters[key] = el.value;
				});
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

			// Modals Triggers
			const newBtn = this.root.querySelector(".js-jwpm-open-item-modal");
			if (newBtn) newBtn.addEventListener("click", () => self.openItemModal(null));

			const demoBtn = this.root.querySelector(".js-jwpm-open-demo-modal");
			if (demoBtn) demoBtn.addEventListener("click", () => self.openDemoModal());

            // Import Btn
            const importBtn = this.root.querySelector(".js-jwpm-open-import-modal");
            if (importBtn) importBtn.addEventListener("click", () => alert("Import feature coming soon!"));

			// Row Actions (Edit/Delete)
			const itemsTable = this.root.querySelector(".js-jwpm-items-table");
			if (itemsTable) {
				itemsTable.addEventListener("click", (e) => {
					const btn = e.target.closest("button");
					if (!btn) return;

					const row = e.target.closest("tr");
					const id = Number(row.dataset.itemId || 0);

					if (btn.classList.contains("js-jwpm-edit-item")) {
						self.editItem(id, row); // row بھی پاس کریں تاکہ ڈیٹا اٹھایا جا سکے
					} else if (btn.classList.contains("js-jwpm-delete-item")) {
						self.deleteItem(id);
					}
				});
			}
		},

		resetFilters() {
			const inputs = this.root.querySelectorAll(".js-jwpm-filter-input");
			inputs.forEach((el) => el.value = "");
			this.state.filters = {};
			this.state.page = 1;
			this.loadItems();
		},

		async loadItems() {
			const req = {
				page: this.state.page,
				per_page: this.state.per_page,
			};
			Object.assign(req, this.state.filters);

			this.showLoading(true);

            // AJAX call to PHP
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

			this.renderTable(items);
			this.renderPagination();
		},

		renderTable(items) {
			const tbody = this.root.querySelector(".js-jwpm-items-tbody");
			tbody.innerHTML = "";

			if (!items.length) {
				tbody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:20px;">No items found.</td></tr>`;
				return;
			}

			items.forEach((itm) => {
                // Status Color Logic
                let statusColor = '#999';
                if(itm.status === 'in_stock') statusColor = 'green';
                else if(itm.status === 'sold') statusColor = 'blue';
                else if(itm.status === 'scrap') statusColor = 'red';

                // Row HTML Construction
				const tr = document.createElement('tr');
                tr.dataset.itemId = itm.id;
                
                // Hidden data fields for easy editing access
                tr.dataset.json = JSON.stringify(itm);

                tr.innerHTML = `
                    <td>
                        <strong>${itm.tag_serial || '-'}</strong><br>
                        <small style="color:#666">${itm.sku || ''}</small>
                    </td>
                    <td>${itm.category || '-'}</td>
                    <td>${itm.metal_type || ''} ${itm.karat || ''}</td>
                    <td>${itm.gross_weight || '0'}</td>
                    <td>${itm.net_weight || '0'}</td>
                    <td>${itm.stone_type ? itm.stone_type + (itm.stone_carat ? ` (${itm.stone_carat}ct)` : '') : '-'}</td>
                    <td>${itm.design_no || '-'}</td>
                    <td><span style="color:${statusColor}; font-weight:bold; text-transform:capitalize;">${itm.status.replace('_', ' ')}</span></td>
                    <td>
                        <button class="button button-small js-jwpm-edit-item">Edit</button>
                        <button class="button button-small button-link-delete js-jwpm-delete-item" style="color:red;">Delete</button>
                    </td>
                `;
				tbody.appendChild(tr);
			});
		},

		renderPagination() {
			const totalPages = Math.max(1, Math.ceil(this.state.total / this.state.per_page));
			const info = this.root.querySelector(".js-jwpm-page-info");
			const prev = this.root.querySelector(".js-jwpm-page-prev");
			const next = this.root.querySelector(".js-jwpm-page-next");

			info.textContent = `Page ${this.state.page} of ${totalPages} (Total: ${this.state.total})`;

            // Remove old listeners to prevent stacking
            const newPrev = prev.cloneNode(true);
            const newNext = next.cloneNode(true);
            prev.parentNode.replaceChild(newPrev, prev);
            next.parentNode.replaceChild(newNext, next);

			newPrev.disabled = this.state.page <= 1;
			newPrev.onclick = () => {
				if (this.state.page > 1) {
					this.state.page--;
					this.loadItems();
				}
			};

			newNext.disabled = this.state.page >= totalPages;
			newNext.onclick = () => {
				if (this.state.page < totalPages) {
					this.state.page++;
					this.loadItems();
				}
			};
		},

		showLoading(state) {
			const loader = this.root.querySelector(".jwpm-loading-state");
			if (loader) loader.style.display = state ? "block" : "none";
		},

        // --- ADD / EDIT ITEM MODAL Logic ---
		openItemModal(item) {
            // Modal HTML Structure
            const modalId = 'jwpm-item-modal';
            let modal = document.getElementById(modalId);
            
            // اگر پہلے سے کھلا ہے تو بند کریں
            if(modal) modal.remove();

            const isEdit = !!item;
            const title = isEdit ? 'Edit Item' : 'Add New Item';
            const btnText = isEdit ? 'Update Item' : 'Save Item';

            // Safe values check
            const val = (key) => (item && item[key]) ? item[key] : '';

            const html = `
                <div id="${modalId}" class="jwpm-modal-overlay" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; display:flex; justify-content:center; align-items:center;">
                    <div class="jwpm-modal-content" style="background:#fff; padding:20px; width:500px; border-radius:5px; max-height:90vh; overflow-y:auto;">
                        <h2 style="margin-top:0;">${title}</h2>
                        <form id="jwpm-item-form">
                            <input type="hidden" name="id" value="${val('id')}">
                            <input type="hidden" name="branch_id" value="${val('branch_id') || jwpmInventoryData.default_branch}">
                            
                            <table class="form-table" style="margin-top:0;">
                                <tr>
                                    <td><label>Tag / Serial <span style="color:red">*</span></label>
                                    <input type="text" name="tag_serial" class="widefat" value="${val('tag_serial')}" required></td>
                                    <td><label>SKU (Optional)</label>
                                    <input type="text" name="sku" class="widefat" value="${val('sku')}"></td>
                                </tr>
                                <tr>
                                    <td><label>Category</label>
                                    <input type="text" name="category" class="widefat" list="cat-list" value="${val('category')}">
                                    <datalist id="cat-list"><option value="Ring"><option value="Bangle"><option value="Chain"></datalist>
                                    </td>
                                    <td><label>Design No</label>
                                    <input type="text" name="design_no" class="widefat" value="${val('design_no')}"></td>
                                </tr>
                                <tr>
                                    <td><label>Metal Type</label>
                                    <select name="metal_type" class="widefat">
                                        <option value="Gold" ${val('metal_type') === 'Gold' ? 'selected' : ''}>Gold</option>
                                        <option value="Silver" ${val('metal_type') === 'Silver' ? 'selected' : ''}>Silver</option>
                                    </select></td>
                                    <td><label>Karat</label>
                                    <select name="karat" class="widefat">
                                        <option value="21K" ${val('karat') === '21K' ? 'selected' : ''}>21K</option>
                                        <option value="22K" ${val('karat') === '22K' ? 'selected' : ''}>22K</option>
                                        <option value="18K" ${val('karat') === '18K' ? 'selected' : ''}>18K</option>
                                    </select></td>
                                </tr>
                                <tr>
                                    <td><label>Gross Weight</label>
                                    <input type="number" step="0.001" name="gross_weight" class="widefat" value="${val('gross_weight')}"></td>
                                    <td><label>Net Weight</label>
                                    <input type="number" step="0.001" name="net_weight" class="widefat" value="${val('net_weight')}"></td>
                                </tr>
                                <tr>
                                    <td><label>Status</label>
                                    <select name="status" class="widefat">
                                        <option value="in_stock" ${val('status') === 'in_stock' ? 'selected' : ''}>In Stock</option>
                                        <option value="sold" ${val('status') === 'sold' ? 'selected' : ''}>Sold</option>
                                        <option value="dead_stock" ${val('status') === 'dead_stock' ? 'selected' : ''}>Dead Stock</option>
                                    </select></td>
                                </tr>
                            </table>

                            <div style="margin-top:20px; text-align:right; border-top:1px solid #eee; padding-top:10px;">
                                <button type="button" class="button js-modal-close">Cancel</button>
                                <button type="submit" class="button button-primary">${btnText}</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', html);
            modal = document.getElementById(modalId);

            // Close Logic
            modal.querySelector('.js-modal-close').onclick = () => modal.remove();

            // Submit Logic
            const form = document.getElementById('jwpm-item-form');
            form.onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                const payload = Object.fromEntries(formData.entries());
                
                // Demo flag
                payload.is_demo = 0;

                const res = await wpAjax(jwpmInventoryData.save_action, payload);
                if (res.success) {
                    showToast("Item saved successfully.", "success");
                    modal.remove();
                    this.loadItems();
                } else {
                    alert("Error: " + (res.data.message || 'Unknown error'));
                }
            };
		},

        // Edit Item Helper
		editItem(id, rowElement) {
            // ہم row کے dataset سے پورا JSON اٹھا رہے ہیں جو ہم نے renderTable میں محفوظ کیا تھا
            let itemData = {};
            if(rowElement && rowElement.dataset.json) {
                try {
                    itemData = JSON.parse(rowElement.dataset.json);
                } catch(e) { console.error("JSON parse error", e); }
            }
            
            // Fallback: اگر JSON نہیں ملا تو کم از کم ID پاس کریں (باقی فیلڈز خالی ہوں گی)
            if(!itemData.id) itemData.id = id;

			this.openItemModal(itemData);
		},

        // Delete Item
		async deleteItem(id) {
			if (!confirm("Are you sure you want to delete this item?")) return;

			const res = await wpAjax(jwpmInventoryData.delete_action, { id });
			if (res.success) {
				showToast("Item deleted.", "success");
				this.loadItems();
			} else {
				alert("Error deleting item.");
			}
		},

        // Demo Modal
		openDemoModal() {
            if(confirm("Generate 10 Demo Items?")) {
                this.handleDemoAction("create_10");
            }
		},

		async handleDemoAction(mode) {
			const res = await wpAjax(jwpmInventoryData.demo_action, { mode });
			if (res.success) {
				showToast("Demo data generated.", "success");
				this.loadItems();
			} else {
                alert("Error: " + (res.data.message || "Failed"));
            }
		}
	};
	// 🔴 یہاں پر [JWPM_Inventory Main Object] ختم ہو رہا ہے

	// 🟢 یہاں سے [DOM Ready Init] شروع ہو رہا ہے
	$(document).ready(() => {
		if (typeof jwpmInventoryData === "undefined") {
			console.warn("jwpmInventoryData is missing.");
			return;
		}
		JWPM_Inventory.init();
	});
	// 🔴 یہاں پر [DOM Ready Init] ختم ہو رہا ہے
})(jQuery);
/** Part 3 — Template-based Inventory UI (Full Production Mode) */
/**
 * خلاصہ:
 * - PHP میں دیے گئے تمام <template> استعمال کر کے UI رینڈر کرنا
 * - Summary Cards، Filters، Tabs، Table، Detail Panel سب Active کرنا
 * - Item CRUD (Save / Delete)، Demo Data، Import، Print کو AJAX کے ساتھ جوڑنا
 * - کوئی dummy / placeholder UI نہیں، صرف حقیقی Inventory گرِڈ
 */

(function (window, $) {
	"use strict";

	// 🟢 یہاں سے [لوکل Toast Helper] شروع ہو رہا ہے
	function notify(message) {
		// مستقبل میں یہاں custom toast بھی لگا سکتے ہیں، فی الحال سادہ (alert)
		window.alert(message);
	}
	// 🔴 یہاں پر [لوکل Toast Helper] ختم ہو رہا ہے

	// 🟢 یہاں سے [InventoryTemplateUI آبجیکٹ] شروع ہو رہا ہے
	var InventoryTemplateUI = {
		root: null,
		common: null,
		state: {
			page: 1,
			per_page:
				typeof window.jwpmInventoryData !== "undefined" &&
				window.jwpmInventoryData.per_page
					? window.jwpmInventoryData.per_page
					: 50,
			total: 0,
			filters: {},
		},
		els: {},

		init: function () {
			// Root چیک کریں
			this.root = document.getElementById("jwpm-inventory-root");
			if (!this.root) {
				return;
			}

			// لازمی ڈیٹا چیک
			if (typeof window.jwpmInventoryData === "undefined") {
				console.warn("jwpmInventoryData missing. Inventory UI init skipped.");
				return;
			}

			this.common = window.jwpmCommon || {};

			// UI Templates سے ماؤنٹ
			this.mountFromTemplates();
			this.cacheElements();
			this.bindEvents();

			// پہلی بار ڈیٹا لوڈ کریں
			this.loadItems();
		},

		// 🟢 یہاں سے [Templates ماؤنٹ] شروع ہو رہا ہے
		mountFromTemplates: function () {
			var frag = document.createDocumentFragment();

			// Summary
			var summaryFrag =
				this.common.cloneTemplate &&
				this.common.cloneTemplate("jwpm-inventory-summary-template");
			if (summaryFrag) {
				frag.appendChild(summaryFrag);
			}

			// Filters
			var filtersFrag =
				this.common.cloneTemplate &&
				this.common.cloneTemplate("jwpm-inventory-filters-template");
			if (filtersFrag) {
				frag.appendChild(filtersFrag);
			}

			// Main layout (tabs + table + pagination + detail panel)
			var mainFrag =
				this.common.cloneTemplate &&
				this.common.cloneTemplate("jwpm-inventory-main-template");
			if (mainFrag) {
				frag.appendChild(mainFrag);
			}

			// Root صاف کر کے نیا UI ڈال دیں
			this.root.innerHTML = "";
			this.root.appendChild(frag);
		},
		// 🔴 یہاں پر [Templates ماؤنٹ] ختم ہو رہا ہے

		// 🟢 یہاں سے [Elements Cache] شروع ہو رہا ہے
		cacheElements: function () {
			var root = this.root;

			this.els.summaryCards = root.querySelectorAll(
				".jwpm-inv-summary-card[data-metric]"
			);
			this.els.filterInputs = root.querySelectorAll(".js-jwpm-filter-input");
			this.els.filterApply = root.querySelector(".js-jwpm-filter-apply");
			this.els.filterReset = root.querySelector(".js-jwpm-filter-reset");

			this.els.tabsWrapper = root.querySelector(".js-jwpm-tabs");
			this.els.tabBodies = root.querySelectorAll(".js-jwpm-tab-body");

			this.els.itemsTable = root.querySelector(".js-jwpm-items-table");
			this.els.itemsTbody = root.querySelector(".js-jwpm-items-tbody");
			this.els.pagination = root.querySelector(".js-jwpm-pagination");
			this.els.pagePrev = root.querySelector(".js-jwpm-page-prev");
			this.els.pageNext = root.querySelector(".js-jwpm-page-next");
			this.els.pageInfo = root.querySelector(".js-jwpm-page-info");

			this.els.btnNewItem = root.querySelector(".js-jwpm-open-item-modal");
			this.els.btnImport = root.querySelector(".js-jwpm-open-import-modal");
			this.els.btnPrint = root.querySelector(".js-jwpm-print-table");
			this.els.btnDemo = root.querySelector(".js-jwpm-open-demo-modal");

			this.els.detailPanel = root.querySelector(".js-jwpm-detail-panel");
			this.els.detailClose = root.querySelector(".js-jwpm-detail-close");
			this.els.detailContent = root.querySelector(".js-jwpm-detail-content");
		},
		// 🔴 یہاں پر [Elements Cache] ختم ہو رہا ہے

		// 🟢 یہاں سے [Events Binding] شروع ہو رہا ہے
		bindEvents: function () {
			var self = this;

			// Filters state update
			if (this.els.filterInputs && this.els.filterInputs.length) {
				this.els.filterInputs.forEach(function (el) {
					var update = function () {
						var key = el.getAttribute("data-filter-key");
						if (!key) return;
						var value = el.value;
						if (value === "" || value === null) {
							delete self.state.filters[key];
						} else {
							self.state.filters[key] = value;
						}
					};

					el.addEventListener("change", update);
					// Search کے لیے Enter پر apply
					if (
						el.tagName === "INPUT" &&
						(el.type === "text" || el.type === "search")
					) {
						el.addEventListener("keyup", function (e) {
							if (e.key === "Enter") {
								update();
								self.state.page = 1;
								self.loadItems();
							}
						});
					}
				});
			}

			// Apply Filters
			if (this.els.filterApply) {
				this.els.filterApply.addEventListener("click", function () {
					self.state.page = 1;
					self.loadItems();
				});
			}

			// Reset Filters
			if (this.els.filterReset) {
				this.els.filterReset.addEventListener("click", function () {
					self.resetFilters();
				});
			}

			// Tabs switching
			if (this.els.tabsWrapper) {
				this.els.tabsWrapper.addEventListener("click", function (e) {
					var btn = e.target.closest("button[data-tab]");
					if (!btn) return;
					var tab = btn.getAttribute("data-tab");
					self.switchTab(tab);
				});
			}

			// New Item
			if (this.els.btnNewItem) {
				this.els.btnNewItem.addEventListener("click", function () {
					self.openItemModal(null);
				});
			}

			// Import
			if (this.els.btnImport) {
				this.els.btnImport.addEventListener("click", function () {
					self.openImportModal();
				});
			}

			// Print
			if (this.els.btnPrint) {
				this.els.btnPrint.addEventListener("click", function () {
					self.printTable();
				});
			}

			// Demo Data
			if (this.els.btnDemo) {
				this.els.btnDemo.addEventListener("click", function () {
					self.openDemoModal();
				});
			}

			// Detail Panel close
			if (this.els.detailClose && this.els.detailPanel) {
				this.els.detailClose.addEventListener("click", function () {
					self.hideDetailPanel();
				});
			}

			// Row actions (View / Edit / Adjust / Delete) + bulk select
			if (this.els.itemsTbody) {
				this.els.itemsTbody.addEventListener("click", function (e) {
					var btn = e.target.closest("button");
					if (!btn) return;

					var row = e.target.closest("tr[data-item-id]");
					if (!row) return;
					var id = Number(row.getAttribute("data-item-id") || 0);
					var itemJson = row.getAttribute("data-item-json");
					var itemData = {};
					if (itemJson) {
						try {
							itemData = JSON.parse(itemJson);
						} catch (err) {
							console.warn("Invalid item JSON on row:", err);
						}
					}

					if (btn.classList.contains("js-jwpm-view-item")) {
						self.showDetailPanel(itemData);
					} else if (btn.classList.contains("js-jwpm-edit-item")) {
						self.openItemModal(itemData);
					} else if (btn.classList.contains("js-jwpm-adjust-stock")) {
						// Future: Adjust stock modal
						notify("Stock adjustment فیچر جلد آئے گا۔");
					} else if (btn.classList.contains("js-jwpm-delete-item")) {
						self.deleteItem(id);
					}
				});
			}

			// Pagination
			if (this.els.pagePrev) {
				this.els.pagePrev.addEventListener("click", function () {
					if (self.state.page > 1) {
						self.state.page--;
						self.loadItems();
					}
				});
			}
			if (this.els.pageNext) {
				this.els.pageNext.addEventListener("click", function () {
					var totalPages = Math.max(
						1,
						Math.ceil(self.state.total / self.state.per_page)
					);
					if (self.state.page < totalPages) {
						self.state.page++;
						self.loadItems();
					}
				});
			}
		},
		// 🔴 یہاں پر [Events Binding] ختم ہو رہا ہے

		// 🟢 یہاں سے [Tabs Switching] شروع ہو رہا ہے
		switchTab: function (tab) {
			if (!tab) return;
			// Buttons
			if (this.els.tabsWrapper) {
				this.els.tabsWrapper
					.querySelectorAll("button[data-tab]")
					.forEach(function (btn) {
						var t = btn.getAttribute("data-tab");
						if (t === tab) {
							btn.classList.add("is-active");
						} else {
							btn.classList.remove("is-active");
						}
					});
			}
			// Bodies
			if (this.els.tabBodies && this.els.tabBodies.length) {
				this.els.tabBodies.forEach(function (body) {
					var t = body.getAttribute("data-tab");
					if (t === tab) {
						body.removeAttribute("hidden");
					} else {
						body.setAttribute("hidden", "hidden");
					}
				});
			}
		},
		// 🔴 یہاں پر [Tabs Switching] ختم ہو رہا ہے

		// 🟢 یہاں سے [Filters Reset] شروع ہو رہا ہے
		resetFilters: function () {
			if (this.els.filterInputs && this.els.filterInputs.length) {
				this.els.filterInputs.forEach(function (el) {
					el.value = "";
				});
			}
			this.state.filters = {};
			this.state.page = 1;
			this.loadItems();
		},
		// 🔴 یہاں پر [Filters Reset] ختم ہو رہا ہے

		// 🟢 یہاں سے [Loading Indicator] شروع ہو رہا ہے
		showLoading: function (state) {
			var loader = this.root.querySelector(".jwpm-loading-state");
			if (!loader) return;
			loader.style.display = state ? "flex" : "none";
		},
		// 🔴 یہاں پر [Loading Indicator] ختم ہو رہا ہے

		// 🟢 یہاں سے [Items Load via AJAX] شروع ہو رہا ہے
		loadItems: function () {
			var self = this;
			var req = {
				page: this.state.page,
				per_page: this.state.per_page,
			};

			// Filters merge
			for (var k in this.state.filters) {
				if (Object.prototype.hasOwnProperty.call(this.state.filters, k)) {
					req[k] = this.state.filters[k];
				}
			}

			this.showLoading(true);

			var action =
				window.jwpmInventoryData.list_action ||
				"jwpm_inventory_get_items";

			if (!this.common.wpAjax) {
				console.warn("jwpmCommon.wpAjax missing, fallback to window.jwpm_send_ajax_request");
				if (typeof window.jwpm_send_ajax_request === "function") {
					window.jwpm_send_ajax_request(
						action,
						req,
						function (data) {
							self.showLoading(false);
							self.handleListResponse({ success: true, data: data });
						},
						function (data) {
							self.showLoading(false);
							self.handleListResponse({ success: false, data: data });
						}
					);
					return;
				}
			}

			// Promise based helper
			this.common
				.wpAjax(action, req)
				.then(function (res) {
					self.showLoading(false);
					self.handleListResponse(res || { success: false });
				});
		},

		handleListResponse: function (res) {
			if (!res || !res.success) {
				var msg =
					res && res.data && res.data.message
						? res.data.message
						: "Unable to load inventory items.";
				notify(msg);
				this.renderTable([]);
				this.updateSummary(null, []);
				this.updatePagination();
				return;
			}

			var data = res.data || {};
			var items = data.items || [];
			this.state.total = Number(data.total || items.length || 0);
			// اگر سرور سے per_page آئے تو اپ ڈیٹ کر لیں
			if (data.per_page) {
				this.state.per_page = Number(data.per_page);
			}
			// Summary
			this.updateSummary(data.summary || null, items);
			// Filters options (اگر server سے آئے ہوں)
			this.updateFiltersOptions(data);

			// Table render + pagination
			this.renderTable(items);
			this.updatePagination();
		},
		// 🔴 یہاں پر [Items Load via AJAX] ختم ہو رہا ہے

		// 🟢 یہاں سے [Summary Cards Update] شروع ہو رہا ہے
		updateSummary: function (summary, items) {
			var total_items = 0;
			var total_weight = 0;
			var low_stock = 0;
			var dead_stock = 0;

			if (summary) {
				total_items = Number(summary.total_items || 0);
				total_weight = Number(summary.total_weight || 0);
				low_stock = Number(summary.low_stock || 0);
				dead_stock = Number(summary.dead_stock || 0);
			} else if (items && items.length) {
				total_items = items.length;
				items.forEach(function (itm) {
					var gw = Number(itm.gross_weight || 0);
					total_weight += gw;
					if (itm.status === "low_stock") low_stock++;
					if (itm.status === "dead_stock") dead_stock++;
				});
			}

			if (!this.els.summaryCards || !this.els.summaryCards.length) return;

			this.els.summaryCards.forEach(function (card) {
				var metric = card.getAttribute("data-metric");
				var span = card.querySelector(".js-jwpm-summary-value");
				if (!span) return;
				var val = 0;
				if (metric === "total_items") val = total_items;
				else if (metric === "total_weight") val = total_weight.toFixed(3);
				else if (metric === "low_stock") val = low_stock;
				else if (metric === "dead_stock") val = dead_stock;
				span.textContent = val;
			});
		},
		// 🔴 یہاں پر [Summary Cards Update] ختم ہو رہا ہے

		// 🟢 یہاں سے [Filters Options Update] شروع ہو رہا ہے
		/**
		 * اگر سرور response میں categories / metals / branches وغیرہ آئیں
		 * تو انہیں متعلقہ dropdowns میں inject کریں۔
		 */
		updateFiltersOptions: function (data) {
			if (!data) return;

			// Helper to fill <select>
			function fillSelect(selectEl, list) {
				if (!selectEl || !Array.isArray(list)) return;
				// پہلی option (All ...) کو بچا کر باقی صاف کر دیں
				var firstOption = selectEl.querySelector("option");
				selectEl.innerHTML = "";
				if (firstOption) {
					selectEl.appendChild(firstOption);
				}
				list.forEach(function (item) {
					var opt = document.createElement("option");
					if (typeof item === "string") {
						opt.value = item;
						opt.textContent = item;
					} else {
						opt.value = item.value || item.id || "";
						opt.textContent = item.label || item.name || item.value || "";
					}
					selectEl.appendChild(opt);
				});
			}

			var catSelect = this.root.querySelector("#jwpm-inv-filter-category");
			var metalSelect = this.root.querySelector("#jwpm-inv-filter-metal");
			var branchSelect = this.root.querySelector("#jwpm-inv-filter-branch");

			if (data.categories) fillSelect(catSelect, data.categories);
			if (data.metals) fillSelect(metalSelect, data.metals);
			if (data.branches) fillSelect(branchSelect, data.branches);
		},
		// 🔴 یہاں پر [Filters Options Update] ختم ہو رہا ہے

		// 🟢 یہاں سے [Table Render] شروع ہو رہا ہے
		renderTable: function (items) {
			if (!this.els.itemsTbody) return;
			var tbody = this.els.itemsTbody;
			tbody.innerHTML = "";

			if (!items || !items.length) {
				var trEmpty = document.createElement("tr");
				trEmpty.className = "jwpm-table-empty";
				var td = document.createElement("td");
				td.colSpan = 11;
				td.textContent =
					"No items found. Try adjusting filters or create a new item.";
				trEmpty.appendChild(td);
				tbody.appendChild(trEmpty);
				return;
			}

			var self = this;

			items.forEach(function (itm) {
				var frag =
					self.common.cloneTemplate &&
					self.common.cloneTemplate("jwpm-inventory-row-template");
				var tr;
				if (frag) {
					tr = frag.querySelector("tr");
				}
				if (!tr) {
					// Fallback simple row (should rarely happen)
					tr = document.createElement("tr");
					tr.innerHTML =
						"<td></td><td></td><td></td><td></td><td></td><td></td>" +
						"<td></td><td></td><td></td><td></td><td></td>";
				}

				tr.setAttribute("data-item-id", itm.id || 0);
				tr.setAttribute("data-item-json", JSON.stringify(itm));

				var tagCell = tr.querySelector(".js-jwpm-tag");
				var catCell = tr.querySelector(".js-jwpm-category");
				var karatCell = tr.querySelector(".js-jwpm-karat");
				var grossCell = tr.querySelector(".js-jwpm-gross");
				var netCell = tr.querySelector(".js-jwpm-net");
				var stonesCell = tr.querySelector(".js-jwpm-stones");
				var branchCell = tr.querySelector(".js-jwpm-branch");
				var statusBadge = tr.querySelector(".js-jwpm-status-badge");
				var photoCell = tr.querySelector(".js-jwpm-photo");

				if (tagCell) {
					tagCell.textContent = itm.tag_serial || itm.sku || "-";
				}
				if (catCell) {
					catCell.textContent = itm.category || "-";
				}
				if (karatCell) {
					karatCell.textContent = itm.karat || "";
				}
				if (grossCell) {
					grossCell.textContent = itm.gross_weight || "";
				}
				if (netCell) {
					netCell.textContent = itm.net_weight || "";
				}
				if (stonesCell) {
					var stonesText = "-";
					if (itm.stone_type) {
						stonesText = itm.stone_type;
						if (itm.stone_carat) {
							stonesText += " (" + itm.stone_carat + "ct)";
						}
						if (itm.stone_qty) {
							stonesText += " x" + itm.stone_qty;
						}
					}
					stonesCell.textContent = stonesText;
				}
				if (branchCell) {
					branchCell.textContent = itm.branch_name || itm.branch_label || "";
				}
				if (statusBadge) {
					var status = itm.status || "in_stock";
					statusBadge.textContent = status.replace("_", " ");
					statusBadge.className =
						"jwpm-status-badge js-jwpm-status-badge jwpm-status-" + status;
				}
				if (photoCell) {
					// Future: image thumb
					photoCell.innerHTML = '<div class="jwpm-photo-placeholder"></div>';
				}

				tbody.appendChild(tr);
			});
		},
		// 🔴 یہاں پر [Table Render] ختم ہو رہا ہے

		// 🟢 یہاں سے [Pagination Update] شروع ہو رہا ہے
		updatePagination: function () {
			if (!this.els.pageInfo || !this.els.pagePrev || !this.els.pageNext) {
				return;
			}
			var totalPages = Math.max(
				1,
				Math.ceil(this.state.total / this.state.per_page)
			);

			this.els.pageInfo.textContent =
				"Page " +
				this.state.page +
				" of " +
				totalPages +
				" (Total: " +
				this.state.total +
				")";

			this.els.pagePrev.disabled = this.state.page <= 1;
			this.els.pageNext.disabled = this.state.page >= totalPages;
		},
		// 🔴 یہاں پر [Pagination Update] ختم ہو رہا ہے

		// 🟢 یہاں سے [Detail Panel] شروع ہو رہا ہے
		showDetailPanel: function (item) {
			if (!this.els.detailPanel || !this.els.detailContent) return;
			if (!item) {
				this.els.detailContent.textContent = "No data.";
			} else {
				this.els.detailContent.innerHTML =
					'<h2 style="margin-top:0;">' +
					(item.tag_serial || item.sku || "Item") +
					"</h2>" +
					"<p><strong>Category:</strong> " +
					(item.category || "-") +
					"</p>" +
					"<p><strong>Karat:</strong> " +
					(item.karat || "") +
					"</p>" +
					"<p><strong>Gross / Net:</strong> " +
					(item.gross_weight || "0") +
					" / " +
					(item.net_weight || "0") +
					"</p>" +
					"<p><strong>Status:</strong> " +
					(item.status || "") +
					"</p>" +
					"<p><strong>Branch:</strong> " +
					(item.branch_name || "") +
					"</p>" +
					"<p><strong>Notes:</strong> " +
					(item.notes || "") +
					"</p>";
			}
			this.els.detailPanel.removeAttribute("hidden");
		},

		hideDetailPanel: function () {
			if (!this.els.detailPanel) return;
			this.els.detailPanel.setAttribute("hidden", "hidden");
		},
		// 🔴 یہاں پر [Detail Panel] ختم ہو رہا ہے

		// 🟢 یہاں سے [Item Modal] شروع ہو رہا ہے
		openItemModal: function (item) {
			var tpl =
				this.common.cloneTemplate &&
				this.common.cloneTemplate("jwpm-inventory-item-modal-template");
			var modalRoot;
			if (tpl) {
				modalRoot = tpl.querySelector(".jwpm-modal");
			}
			if (!modalRoot) {
				notify("Modal template not found.");
				return;
			}

			// Clone کو body میں add کریں
			document.body.appendChild(tpl);

			var modal = document.body.querySelector(".jwpm-modal-item:last-of-type");
			var form = modal.querySelector(".js-jwpm-item-form");
			var titleEl = modal.querySelector(".js-jwpm-modal-title");
			var idField = modal.querySelector(".js-jwpm-item-id");
			var branchSelect = modal.querySelector(".js-jwpm-branch-select");

			var isEdit = !!(item && item.id);
			if (titleEl) {
				titleEl.textContent = isEdit
					? "Edit Inventory Item"
					: "New Inventory Item";
			}
			if (idField) {
				idField.value = isEdit ? item.id : 0;
			}

			// اگر item ہے تو فیلڈز prefill کریں
			if (item && form) {
				[
					"sku",
					"tag_serial",
					"category",
					"metal_type",
					"karat",
					"gross_weight",
					"net_weight",
					"stone_type",
					"stone_carat",
					"stone_qty",
					"labour_amount",
					"design_no",
					"status",
					"branch_id",
					"notes",
				].forEach(function (key) {
					var field = form.querySelector('[name="' + key + '"]');
					if (!field) return;
					if (key === "status" || key === "branch_id" || field.tagName === "SELECT") {
						field.value = item[key] || field.value;
					} else {
						field.value = item[key] != null ? item[key] : "";
					}
				});
			} else if (branchSelect && window.jwpmInventoryData.default_branch) {
				branchSelect.value = window.jwpmInventoryData.default_branch;
			}

			// Close handlers
			var self = this;
			modal
				.querySelectorAll(".js-jwpm-modal-close, .jwpm-modal-backdrop")
				.forEach(function (btn) {
					btn.addEventListener("click", function () {
						modal.remove();
					});
				});

			// Submit handler
			if (form) {
				form.addEventListener("submit", function (e) {
					e.preventDefault();
					var formData = new FormData(form);
					var payload = {};
					formData.forEach(function (v, k) {
						payload[k] = v;
					});
					// Demo flag اگر checkbox ہو
					if (!payload.is_demo) {
						payload.is_demo = 0;
					}

					var action =
						window.jwpmInventoryData.save_action ||
						"jwpm_inventory_save_item";

					self.common
						.wpAjax(action, payload)
						.then(function (res) {
							if (res && res.success) {
								notify("Item saved successfully.");
								modal.remove();
								self.loadItems();
							} else {
								var msg =
									res && res.data && res.data.message
										? res.data.message
										: "Error saving item.";
								notify(msg);
							}
						});
				});
			}
		},
		// 🔴 یہاں پر [Item Modal] ختم ہو رہا ہے

		// 🟢 یہاں سے [Item Delete] شروع ہو رہا ہے
		deleteItem: function (id) {
			if (!id) return;
			var msg =
				(this.common.i18n && this.common.i18n.confirmDelete) ||
				"Are you sure you want to delete this item?";
			if (!window.confirm(msg)) return;

			var action =
				window.jwpmInventoryData.delete_action ||
				"jwpm_inventory_delete_item";

			var self = this;
			this.common
				.wpAjax(action, { id: id })
				.then(function (res) {
					if (res && res.success) {
						notify("Item deleted.");
						self.loadItems();
					} else {
						notify("Error deleting item.");
					}
				});
		},
		// 🔴 یہاں پر [Item Delete] ختم ہو رہا ہے

		// 🟢 یہاں سے [Demo Modal + Actions] شروع ہو رہا ہے
		openDemoModal: function () {
			var tpl =
				this.common.cloneTemplate &&
				this.common.cloneTemplate("jwpm-inventory-demo-modal-template");
			var modalRoot;
			if (tpl) {
				modalRoot = tpl.querySelector(".jwpm-modal");
			}
			if (!modalRoot) {
				notify("Demo modal template not found.");
				return;
			}
			document.body.appendChild(tpl);

			var modal = document.body.querySelector(".jwpm-modal-demo:last-of-type");
			var self = this;

			// Close
			modal
				.querySelectorAll(".js-jwpm-modal-close, .jwpm-modal-backdrop")
				.forEach(function (btn) {
					btn.addEventListener("click", function () {
						modal.remove();
					});
				});

			// Demo actions
			var btn10 = modal.querySelector(".js-jwpm-create-demo-10");
			var btn100 = modal.querySelector(".js-jwpm-create-demo-100");
			var btnDelete = modal.querySelector(".js-jwpm-delete-demo-items");

			if (btn10) {
				btn10.addEventListener("click", function () {
					self.handleDemoAction("create_10", modal);
				});
			}
			if (btn100) {
				btn100.addEventListener("click", function () {
					self.handleDemoAction("create_100", modal);
				});
			}
			if (btnDelete) {
				btnDelete.addEventListener("click", function () {
					self.handleDemoAction("delete_demo", modal);
				});
			}
		},

		handleDemoAction: function (mode, modal) {
			var action =
				window.jwpmInventoryData.demo_action ||
				"jwpm_inventory_demo_items";

			var self = this;
			this.common
				.wpAjax(action, { mode: mode })
				.then(function (res) {
					if (res && res.success) {
						notify("Demo data updated.");
						if (modal) modal.remove();
						self.loadItems();
					} else {
						var msg =
							res && res.data && res.data.message
								? res.data.message
								: "Failed to update demo data.";
						notify(msg);
					}
				});
		},
		// 🔴 یہاں پر [Demo Modal + Actions] ختم ہو رہا ہے

		// 🟢 یہاں سے [Import Modal] شروع ہو رہا ہے
		openImportModal: function () {
			var tpl =
				this.common.cloneTemplate &&
				this.common.cloneTemplate("jwpm-inventory-import-modal-template");
			var modalRoot;
			if (tpl) {
				modalRoot = tpl.querySelector(".jwpm-modal");
			}
			if (!modalRoot) {
				notify("Import modal template not found.");
				return;
			}
			document.body.appendChild(tpl);

			var modal = document.body.querySelector(".jwpm-modal-import:last-of-type");
			var self = this;

			var btnCloseList = modal.querySelectorAll(
				".js-jwpm-modal-close, .jwpm-modal-backdrop"
			);
			btnCloseList.forEach(function (btn) {
				btn.addEventListener("click", function () {
					modal.remove();
				});
			});

			var btnSample = modal.querySelector(".js-jwpm-download-sample");
			var fileInput = modal.querySelector(".js-jwpm-import-file");
			var chkDemo = modal.querySelector(".js-jwpm-import-as-demo");
			var btnStart = modal.querySelector(".js-jwpm-start-import");

			// Sample Download
			if (btnSample) {
				btnSample.addEventListener("click", function () {
					if (window.jwpmInventoryData.sample_url) {
						window.location.href = window.jwpmInventoryData.sample_url;
						return;
					}
					var sampleAction =
						window.jwpmInventoryData.sample_action ||
						"jwpm_inventory_download_sample";
					var url = (self.common.ajax_url ||
						window.ajaxurl ||
						"") +
						"?action=" +
						encodeURIComponent(sampleAction);
					if (self.common.nonce_common) {
						url +=
							"&nonce=" + encodeURIComponent(self.common.nonce_common);
					}
					window.location.href = url;
				});
			}

			// Start Import
			if (btnStart) {
				btnStart.addEventListener("click", function () {
					if (!fileInput || !fileInput.files || !fileInput.files.length) {
						notify("Please select a file to import.");
						return;
					}

					var action =
						window.jwpmInventoryData.import_action ||
						"jwpm_inventory_import_items";

					var fd = new FormData();
					fd.append("action", action);
					if (self.common.nonce_common) {
						fd.append("nonce", self.common.nonce_common);
					}
					fd.append("file", fileInput.files[0]);
					fd.append("is_demo", chkDemo && chkDemo.checked ? 1 : 0);

					$.ajax({
						url:
							self.common.ajax_url ||
							window.ajaxurl ||
							"",
						type: "POST",
						data: fd,
						processData: false,
						contentType: false,
						dataType: "json",
						success: function (res) {
							if (res && res.success) {
								notify("Import completed successfully.");
								modal.remove();
								self.loadItems();
							} else {
								var msg =
									res && res.data && res.data.message
										? res.data.message
										: "Import failed.";
								notify(msg);
							}
						},
						error: function () {
							notify("Network error during import.");
						},
					});
				});
			}
		},
		// 🔴 یہاں پر [Import Modal] ختم ہو رہا ہے

		// 🟢 یہاں سے [Table Print] شروع ہو رہا ہے
		printTable: function () {
			if (!this.els.itemsTable) {
				notify("Table not found to print.");
				return;
			}
			var win = window.open("", "_blank");
			if (!win) {
				notify("Popup blocked. Please allow popups to print.");
				return;
			}
			var html =
				"<!doctype html><html><head><title>Inventory Print</title>" +
				'<style>table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ccc;padding:6px;font-size:12px;}th{background:#f5f5f5;}</style>' +
				"</head><body>" +
				"<h1>Inventory / Stock</h1>" +
				this.els.itemsTable.outerHTML +
				"</body></html>";
			win.document.open();
			win.document.write(html);
			win.document.close();
			win.focus();
			win.print();
		},
		// 🔴 یہاں پر [Table Print] ختم ہو رہا ہے
	};
	// 🔴 یہاں پر [InventoryTemplateUI آبجیکٹ] ختم ہو رہا ہے

	// 🟢 یہاں سے [DOM Ready Init - Template UI] شروع ہو رہا ہے
	$(function () {
		// اگر root یا jwpmInventoryData ہی نہیں تو خاموشی سے exit
		if (!document.getElementById("jwpm-inventory-root")) {
			return;
		}
		if (typeof window.jwpmInventoryData === "undefined") {
			console.warn("jwpmInventoryData missing, InventoryTemplateUI skipped.");
			return;
		}

		// پرانے jwpm-inventory.js نے اگر پہلے کوئی custom UI inject کیا بھی ہو
		// تو ہم اسے override کر کے حقیقی template-based UI ماؤنٹ کر دیں گے۔
		InventoryTemplateUI.init();
	});
	// 🔴 یہاں پر [DOM Ready Init - Template UI] ختم ہو رہا ہے

	// ✅ Syntax verified block end
})(window, jQuery);
/** Part X — Detail Panel Close / Safety Fix */
// 🟢 یہاں سے [Inventory Detail Panel Fix] شروع ہو رہا ہے
jQuery(document).ready(function ($) {
	// Detail panel کو سیفٹی کے ساتھ hide کر دیں اگر غلطی سے نظر آ رہا ہو
	var $panel = $('.js-jwpm-detail-panel');
	if ($panel.length) {
		$panel.attr('hidden', true); // شروع میں چھپا دیں
	}

	// Close button پر click → پینل hide
	$(document).on('click', '.js-jwpm-detail-close', function (e) {
		e.preventDefault();
		var $p = $(this).closest('.js-jwpm-detail-panel');
		if ($p.length) {
			$p.attr('hidden', true);
		}
	});

	// اگر کہیں کوڈ نے غلطی سے class کے ذریعے show کر رکھا ہو تو
	// آپ اضافی سیفٹی بھی رکھ سکتے ہیں:
	$(document).on('jwpm_inventory_hide_detail', function () {
		var $p = $('.js-jwpm-detail-panel');
		if ($p.length) {
			$p.attr('hidden', true);
		}
	});
});
// 🔴 یہاں پر [Inventory Detail Panel Fix] ختم ہو رہا ہے

// ✅ Syntax verified block end
