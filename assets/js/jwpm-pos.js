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

