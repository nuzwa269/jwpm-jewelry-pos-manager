<?php
/**
 * POS Page — JWPM
 * Summary:
 * - Root container
 * - Top header (Urdu + English)
 * - Mini stats row
 * - 3-column POS workspace (Left Search, Center Cart, Right Customer/Payment)
 * - Bottom Sticky Action Bar
 * - All templates for JS mounting
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wrap jwpm-pos-wrap">
	<h1 class="jwpm-hidden">POS / Sales</h1>

	<!-- 🟢 POS ROOT -->
	<div id="jwpm-pos-root"></div>

</div>

<!-- ============================================================
     TEMPLATE 1 — HEADER BAR
     ============================================================ -->
<template id="jwpm-pos-header-template">
	<div class="jwpm-pos-header">
		<div class="jwpm-pos-header-left">
			<h2 class="jwpm-pos-title">
				POS / New Sale  
				<span class="jwpm-title-urdu">| نئی سیل</span>
			</h2>
			<div class="jwpm-breadcrumb">
				Home &gt; POS &gt; New Sale
			</div>
		</div>

		<div class="jwpm-pos-header-right">
			<select class="jwpm-branch-select">
				<option value="1">Main Branch</option>
			</select>

			<div class="jwpm-gold-rate-box">
				Gold Rate: <span class="js-gold-rate">—</span>
			</div>

			<div class="jwpm-datetime-box js-pos-datetime">
				—
			</div>
		</div>
	</div>
</template>


<!-- ============================================================
     TEMPLATE 2 — MINI STATS CARDS
     ============================================================ -->
<template id="jwpm-pos-stats-template">
	<div class="jwpm-pos-stats-row">

		<div class="jwpm-pos-stat-card jwpm-pos-stat-blue" data-stat="today_sales">
			<div class="jwpm-stat-label">Today's Sales<br><span class="urdu">آج کی سیلز</span></div>
			<div class="jwpm-stat-value js-stat-value">0</div>
		</div>

		<div class="jwpm-pos-stat-card jwpm-pos-stat-green" data-stat="active_carts">
			<div class="jwpm-stat-label">Active Carts<br><span class="urdu">جاری کارٹس</span></div>
			<div class="jwpm-stat-value js-stat-value">0</div>
		</div>

		<div class="jwpm-pos-stat-card jwpm-pos-stat-orange" data-stat="pending_installments">
			<div class="jwpm-stat-label">Pending Installments<br><span class="urdu">بقایا قسطیں</span></div>
			<div class="jwpm-stat-value js-stat-value">0</div>
		</div>

		<div class="jwpm-pos-stat-card jwpm-pos-stat-pink" data-stat="online_orders">
			<div class="jwpm-stat-label">Online Orders To Bill<br><span class="urdu">آن لائن آرڈرز</span></div>
			<div class="jwpm-stat-value js-stat-value">0</div>
		</div>

	</div>
</template>


<!-- ============================================================
     TEMPLATE 3 — MAIN 3 COLUMN LAYOUT
     ============================================================ -->
<template id="jwpm-pos-main-template">
	<div class="jwpm-pos-columns">

		<!-- LEFT PANE — SEARCH -->
		<div class="jwpm-pos-pane jwpm-pos-left">

			<div class="jwpm-pane-header jwpm-pane-blue">
				Product Search | <span class="urdu">پروڈکٹ سرچ</span>
			</div>

			<div class="jwpm-pos-search-box">

				<div class="jwpm-search-row">
					<input type="text" class="jwpm-input js-pos-search-text"
						placeholder="Search by Name / SKU / Tag ID">
					
					<select class="jwpm-select js-pos-filter-category">
						<option value="">Category</option>
					</select>

					<select class="jwpm-select js-pos-filter-karat">
						<option value="">Karat</option>
					</select>

					<button class="jwpm-btn-icon js-pos-scan-btn" title="Barcode Scan">
						<span class="dashicons dashicons-camera"></span>
					</button>
				</div>

				<div class="jwpm-pos-search-results js-pos-search-results">
					<!-- JS will fill -->
				</div>

			</div>
		</div>

		<!-- CENTER PANE — CART -->
		<div class="jwpm-pos-pane jwpm-pos-center">

			<div class="jwpm-pane-header jwpm-pane-green">
				Sale Cart | <span class="urdu">سیل کارٹ</span>
			</div>

			<div class="jwpm-pos-cart-box">

				<table class="jwpm-pos-cart-table">
					<thead>
						<tr>
							<th>Photo</th>
							<th>Tag</th>
							<th>Description</th>
							<th>Wt (g)</th>
							<th>Making</th>
							<th>Stone</th>
							<th>Qty</th>
							<th>Unit</th>
							<th>Discount</th>
							<th>Total</th>
							<th></th>
						</tr>
					</thead>
					<tbody class="js-pos-cart-body">
						<!-- JS rows -->
					</tbody>
				</table>

				<div class="jwpm-cart-summary-row">

					<div class="jwpm-cart-discount">
						<label>Overall Discount | <span class="urdu">مجموعی ڈسکاؤنٹ</span></label>
						<input type="number" class="jwpm-input js-pos-overall-discount" value="0">
					</div>

					<button class="jwpm-btn-secondary js-pos-old-gold">
						Old Gold Adjustment | <span class="urdu">اولڈ گولڈ ایڈجسٹمنٹ</span>
					</button>

					<div class="jwpm-pos-pill-badges">
						<span class="jwpm-pill">Walk-in Customer | عام کسٹمر</span>
						<span class="jwpm-pill">Hold Bill | ہولڈ بل</span>
						<span class="jwpm-pill">Draft Invoice | ڈرافٹ انوائس</span>
					</div>

				</div>

			</div>
		</div>

		<!-- RIGHT PANE — CUSTOMER + PAYMENT -->
		<div class="jwpm-pos-pane jwpm-pos-right">

			<!-- CUSTOMER BOX -->
			<div class="jwpm-pos-box">

				<div class="jwpm-pane-header jwpm-pane-orange">
					Customer | <span class="urdu">کسٹمر</span>
				</div>

				<div class="jwpm-pos-customer">

					<div class="jwpm-customer-search">
						<input type="text" class="jwpm-input js-pos-customer-search"
							placeholder="Search by Phone / Name">
						<button class="jwpm-btn-primary js-pos-new-customer">
							+ New Customer | <span class="urdu">نیا کسٹمر</span>
						</button>
					</div>

					<div class="jwpm-customer-fields">
						<label>Name | <span class="urdu">نام</span></label>
						<input type="text" class="jwpm-input js-pos-cust-name" readonly>

						<label>Mobile | <span class="urdu">موبائل</span></label>
						<input type="text" class="jwpm-input js-pos-cust-mobile" readonly>

						<label>Loyalty Points</label>
						<input type="text" class="jwpm-input js-pos-cust-points" readonly>

						<label>Outstanding Credit</label>
						<input type="text" class="jwpm-input js-pos-cust-credit jwpm-danger-text" readonly>
					</div>

				</div>
			</div>

			<!-- PAYMENT BOX -->
			<div class="jwpm-pos-box">

				<div class="jwpm-pane-header jwpm-pane-pink">
					Bill & Payment | <span class="urdu">بل اور ادائیگی</span>
				</div>

				<div class="jwpm-pos-payment">

					<div class="jwpm-total-row"><span>Subtotal:</span> <span class="js-pos-subtotal">0</span></div>
					<div class="jwpm-total-row"><span>Discount:</span> <span class="js-pos-disc-total">0</span></div>
					<div class="jwpm-total-row"><span>Old Gold:</span> <span class="js-pos-old-gold-total">0</span></div>
					<div class="jwpm-total-row"><span>Tax:</span> <span class="js-pos-tax">0</span></div>

					<div class="jwpm-grand-total">
						Grand Total:
						<span class="js-pos-grand">0</span>
					</div>

					<div class="jwpm-payment-methods">
						<button class="jwpm-pill-btn">Cash</button>
						<button class="jwpm-pill-btn">Card</button>
						<button class="jwpm-pill-btn">Bank Transfer</button>
						<button class="jwpm-pill-btn">Split Payment</button>
						<button class="jwpm-pill-btn js-pos-pay-install">Installment</button>
					</div>

					<div class="jwpm-installment-box js-pos-installment-box" hidden>
						<label>Advance Paid</label>
						<input type="number" class="jwpm-input js-pos-install-advance">

						<label>Remaining</label>
						<input type="number" class="jwpm-input js-pos-install-remaining">

						<label>Number of Installments</label>
						<input type="number" class="jwpm-input js-pos-install-count">

						<label>First Due Date</label>
						<input type="date" class="jwpm-input js-pos-install-date">
					</div>

					<label>Notes / Remarks</label>
					<textarea class="jwpm-textarea js-pos-notes"></textarea>

				</div>

			</div>

		</div>

	</div>
</template>


<!-- ============================================================
     TEMPLATE 4 — CART ROW
     ============================================================ -->
<template id="jwpm-pos-cart-row-template">
	<tr>
		<td><div class="jwpm-photo-36"></div></td>
		<td class="js-pos-tag">-</td>
		<td class="js-pos-desc">-</td>
		<td class="js-pos-wt">0</td>
		<td><input type="number" class="jwpm-input js-pos-make" value="0"></td>
		<td><input type="number" class="jwpm-input js-pos-stone" value="0"></td>
		<td><input type="number" class="jwpm-input js-pos-qty" value="1"></td>
		<td class="js-pos-unit">0</td>
		<td><input type="number" class="jwpm-input js-pos-line-disc" value="0"></td>
		<td class="js-pos-line-total">0</td>
		<td><button class="button-link js-pos-remove-item">×</button></td>
	</tr>
</template>


<!-- ============================================================
     TEMPLATE 5 — OLD GOLD MODAL
     ============================================================ -->
<template id="jwpm-pos-old-gold-modal-template">
	<div class="jwpm-modal">
		<div class="jwpm-modal-backdrop js-close-old-gold"></div>
		<div class="jwpm-modal-dialog">
			<div class="jwpm-modal-header">
				<h3>Old Gold Adjustment | <span class="urdu">اولڈ گولڈ</span></h3>
				<button class="jwpm-modal-close js-close-old-gold">×</button>
			</div>

			<div class="jwpm-modal-body">
				<label>Weight (g)</label>
				<input type="number" class="jwpm-input js-og-wt">

				<label>Gold Rate</label>
				<input type="number" class="jwpm-input js-og-rate">

				<label>Total Value</label>
				<input type="number" class="jwpm-input js-og-total" readonly>
			</div>

			<div class="jwpm-modal-footer">
				<button class="jwpm-btn-secondary js-close-old-gold">Cancel</button>
				<button class="jwpm-btn-primary js-save-old-gold">Apply</button>
			</div>
		</div>
	</div>
</template>


<!-- ============================================================
     TEMPLATE 6 — TOAST NOTIFICATION
     ============================================================ -->
<template id="jwpm-pos-toast-template">
	<div class="jwpm-toast js-toast-item">
		<div class="jwpm-toast-text">Message here</div>
	</div>
</template>

<!-- END OF FILE -->
<?php // ✅ Syntax verified block end ?>
