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
        } else if (typeof jwpmCommon !== 'undefined' && jwpmCommon.nonce_common) {
            body.security = jwpmCommon.nonce_common;
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
