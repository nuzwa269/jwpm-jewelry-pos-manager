/** Part 40 — Installments Page Root + Templates */
// 🟢 یہاں سے [Installments Page Templates] شروع ہو رہا ہے

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jwpm_render_installments_page' ) ) {

	/**
	 * JWPM Installments Page Render
	 * Root DIV + HTML <template> blocks, اصل UI (JavaScript) سے آئے گا۔
	 */
	function jwpm_render_installments_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'آپ کو اس صفحے تک رسائی کی اجازت نہیں۔', 'jwpm' ) );
		}

		$main_nonce   = wp_create_nonce( 'jwpm_installments_main_nonce' );
		$import_nonce = wp_create_nonce( 'jwpm_installments_import_nonce' );
		$export_nonce = wp_create_nonce( 'jwpm_installments_export_nonce' );
		$demo_nonce   = wp_create_nonce( 'jwpm_installments_demo_nonce' );
		?>
		<div class="jwpm-page jwpm-page-installments-wrap">
			<noscript>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'براہ کرم (JavaScript) آن کریں، اس صفحے کیلئے ضروری ہے۔', 'jwpm' ); ?></p>
				</div>
			</noscript>

			<div
				id="jwpm-installments-root"
				data-jwpm-installments-main-nonce="<?php echo esc_attr( $main_nonce ); ?>"
				data-jwpm-installments-import-nonce="<?php echo esc_attr( $import_nonce ); ?>"
				data-jwpm-installments-export-nonce="<?php echo esc_attr( $export_nonce ); ?>"
				data-jwpm-installments-demo-nonce="<?php echo esc_attr( $demo_nonce ); ?>"
				data-jwpm-installments-page-title="<?php echo esc_attr__( 'JWPM Installments', 'jwpm' ); ?>"
			>
				<div class="jwpm-loading">
					<?php esc_html_e( 'Installments لوڈ ہو رہے ہیں…', 'jwpm' ); ?>
				</div>
			</div>

			<?php
			/**
			 * Main Layout Template
			 */
			?>
			<template id="jwpm-installments-layout-template">
				<div class="jwpm-page jwpm-page-installments">
					<header class="jwpm-page-header">
						<div class="jwpm-page-title-group">
							<h1 class="jwpm-page-title"><?php esc_html_e( 'Installments / Credit Sales', 'jwpm' ); ?></h1>
							<p class="jwpm-page-subtitle">
								<?php esc_html_e( 'تمام قسطی معاہدوں، شیڈول اور ادائیگیوں کو منظم کریں۔', 'jwpm' ); ?>
							</p>
						</div>
						<div class="jwpm-page-header-stats">
							<div class="jwpm-stat-card" data-jwpm-installments-stat="active_contracts">
								<div class="jwpm-stat-label"><?php esc_html_e( 'Active Contracts', 'jwpm' ); ?></div>
								<div class="jwpm-stat-value">0</div>
							</div>
							<div class="jwpm-stat-card" data-jwpm-installments-stat="total_outstanding">
								<div class="jwpm-stat-label"><?php esc_html_e( 'Total Outstanding', 'jwpm' ); ?></div>
								<div class="jwpm-stat-value">0</div>
							</div>
							<div class="jwpm-stat-card" data-jwpm-installments-stat="overdue_installments">
								<div class="jwpm-stat-label"><?php esc_html_e( 'Overdue Installments', 'jwpm' ); ?></div>
								<div class="jwpm-stat-value">0</div>
							</div>
						</div>
					</header>

					<section class="jwpm-toolbar jwpm-installments-toolbar">
						<div class="jwpm-toolbar-filters">
							<input
								type="search"
								class="jwpm-input"
								data-jwpm-installments-filter="search"
								placeholder="<?php echo esc_attr__( 'کسٹمر نام / موبائل / Contract Code…', 'jwpm' ); ?>"
							/>
							<select class="jwpm-select" data-jwpm-installments-filter="status">
								<option value=""><?php esc_html_e( 'Status (All)', 'jwpm' ); ?></option>
								<option value="active"><?php esc_html_e( 'Active', 'jwpm' ); ?></option>
								<option value="completed"><?php esc_html_e( 'Completed', 'jwpm' ); ?></option>
								<option value="defaulted"><?php esc_html_e( 'Defaulted', 'jwpm' ); ?></option>
								<option value="cancelled"><?php esc_html_e( 'Cancelled', 'jwpm' ); ?></option>
							</select>
							<select class="jwpm-select" data-jwpm-installments-filter="date_mode">
								<option value="sale"><?php esc_html_e( 'Sale Date', 'jwpm' ); ?></option>
								<option value="due"><?php esc_html_e( 'Due Date', 'jwpm' ); ?></option>
							</select>
							<input
								type="date"
								class="jwpm-input"
								data-jwpm-installments-filter="date_from"
							/>
							<input
								type="date"
								class="jwpm-input"
								data-jwpm-installments-filter="date_to"
							/>
						</div>
						<div class="jwpm-toolbar-actions">
							<button type="button" class="button button-primary" data-jwpm-installments-action="add">
								<?php esc_html_e( '➕ New Installment Plan', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-installments-action="receive">
								<?php esc_html_e( '💰 Receive Payment', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-installments-action="import">
								<?php esc_html_e( '⬇ Import Plans (CSV)', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-installments-action="export">
								<?php esc_html_e( '⬆ Export to Excel', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-installments-action="print">
								<?php esc_html_e( '🖨 Print List', 'jwpm' ); ?>
							</button>
							<div class="jwpm-dropdown jwpm-installments-demo-menu">
								<button type="button" class="button" data-jwpm-installments-action="demo-toggle">
									<?php esc_html_e( '🧪 Demo Data', 'jwpm' ); ?>
								</button>
								<div class="jwpm-dropdown-menu">
									<button type="button" class="jwpm-dropdown-item" data-jwpm-installments-action="demo-create">
										<?php esc_html_e( 'Demo Installments بنائیں', 'jwpm' ); ?>
									</button>
									<button type="button" class="jwpm-dropdown-item" data-jwpm-installments-action="demo-clear">
										<?php esc_html_e( 'Demo Installments حذف کریں', 'jwpm' ); ?>
									</button>
								</div>
							</div>
						</div>
					</section>

					<section class="jwpm-installments-main">
						<div class="jwpm-installments-table-wrap">
							<table class="jwpm-table jwpm-table-installments">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Contract Code', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Customer', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Phone', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Total', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Advance', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Net Amount', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Installments', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Next Due', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Outstanding', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Status', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'jwpm' ); ?></th>
									</tr>
								</thead>
								<tbody data-jwpm-installments-table-body>
									<tr class="jwpm-empty-row">
										<td colspan="11">
											<?php esc_html_e( 'کوئی قسطی معاہدہ نہیں ملا۔ اوپر سے فلٹر تبدیل کریں یا نیا پلان بنائیں۔', 'jwpm' ); ?>
										</td>
									</tr>
								</tbody>
							</table>

							<div class="jwpm-pagination" data-jwpm-installments-pagination></div>
						</div>

						<aside class="jwpm-installments-side-panel" data-jwpm-installments-side-panel hidden>
							<!-- (JavaScript) یہاں Side Panel (Overview / Schedule / Payments) رینڈر کرے گا -->
						</aside>
					</section>
				</div>
			</template>

			<?php
			/**
			 * Contracts Table Row Template
			 */
			?>
			<template id="jwpm-installments-row-template">
				<tr data-jwpm-installment-row>
					<td data-jwpm-installment-field="contract_code"></td>
					<td data-jwpm-installment-field="customer_name"></td>
					<td data-jwpm-installment-field="customer_phone"></td>
					<td data-jwpm-installment-field="total_amount"></td>
					<td data-jwpm-installment-field="advance_amount"></td>
					<td data-jwpm-installment-field="net_amount"></td>
					<td data-jwpm-installment-field="installment_count"></td>
					<td data-jwpm-installment-field="next_due_date"></td>
					<td data-jwpm-installment-field="outstanding"></td>
					<td data-jwpm-installment-field="status_badge"></td>
					<td class="jwpm-table-actions">
						<button type="button" class="button-link" data-jwpm-installments-action="view">
							<?php esc_html_e( 'View / Edit', 'jwpm' ); ?>
						</button>
						<button type="button" class="button-link" data-jwpm-installments-action="quick-receive">
							<?php esc_html_e( 'Receive', 'jwpm' ); ?>
						</button>
						<button type="button" class="button-link jwpm-text-danger" data-jwpm-installments-action="cancel-contract">
							<?php esc_html_e( 'Cancel', 'jwpm' ); ?>
						</button>
					</td>
				</tr>
			</template>

			<?php
			/**
			 * Side Panel Template (Overview / Schedule / Payments)
			 */
			?>
			<template id="jwpm-installments-panel-template">
				<div class="jwpm-side-panel-inner">
					<header class="jwpm-side-panel-header">
						<div>
							<h2 class="jwpm-side-panel-title" data-jwpm-installments-panel-title>
								<?php esc_html_e( 'New Installment Plan', 'jwpm' ); ?>
							</h2>
							<div class="jwpm-side-panel-subtitle" data-jwpm-installments-panel-subtitle></div>
						</div>
						<div class="jwpm-side-panel-header-actions">
							<span class="jwpm-status-badge" data-jwpm-installments-contract-status></span>
							<button type="button" class="jwpm-side-panel-close" data-jwpm-installments-action="close-panel" aria-label="<?php echo esc_attr__( 'بند کریں', 'jwpm' ); ?>">×</button>
						</div>
					</header>

					<div class="jwpm-side-panel-tabs">
						<button type="button" class="jwpm-tab is-active" data-jwpm-installments-tab="overview">
							<?php esc_html_e( 'Overview', 'jwpm' ); ?>
						</button>
						<button type="button" class="jwpm-tab" data-jwpm-installments-tab="schedule">
							<?php esc_html_e( 'Schedule', 'jwpm' ); ?>
						</button>
						<button type="button" class="jwpm-tab" data-jwpm-installments-tab="payments">
							<?php esc_html_e( 'Payments', 'jwpm' ); ?>
						</button>
					</div>

					<div class="jwpm-side-panel-body">
						<div class="jwpm-tab-panel is-active" data-jwpm-installments-tab-panel="overview">
							<form data-jwpm-installments-form>
								<input type="hidden" name="id" value="" data-jwpm-installments-input="id" />

								<section class="jwpm-form-section">
									<h3 class="jwpm-form-section-title"><?php esc_html_e( 'Basic Info', 'jwpm' ); ?></h3>
									<div class="jwpm-form-grid">
										<label class="jwpm-field jwpm-field-full">
											<span class="jwpm-field-label"><?php esc_html_e( 'Customer', 'jwpm' ); ?> *</span>
											<select class="jwpm-select" name="customer_id" data-jwpm-installments-input="customer_id">
												<option value=""><?php esc_html_e( 'Select customer…', 'jwpm' ); ?></option>
											</select>
										</label>
										<label class="jwpm-field">
											<span class="jwpm-field-label"><?php esc_html_e( 'Sale Date', 'jwpm' ); ?></span>
											<input type="date" class="jwpm-input" name="sale_date" data-jwpm-installments-input="sale_date" />
										</label>
										<label class="jwpm-field">
											<span class="jwpm-field-label"><?php esc_html_e( 'Total Amount', 'jwpm' ); ?></span>
											<input type="number" step="0.001" class="jwpm-input" name="total_amount" data-jwpm-installments-input="total_amount" />
										</label>
										<label class="jwpm-field">
											<span class="jwpm-field-label"><?php esc_html_e( 'Advance Amount', 'jwpm' ); ?></span>
											<input type="number" step="0.001" class="jwpm-input" name="advance_amount" data-jwpm-installments-input="advance_amount" />
										</label>
										<label class="jwpm-field">
											<span class="jwpm-field-label"><?php esc_html_e( 'Net Installment Amount', 'jwpm' ); ?></span>
											<input type="number" step="0.001" class="jwpm-input" name="net_amount" data-jwpm-installments-input="net_amount" readonly />
										</label>
										<label class="jwpm-field">
											<span class="jwpm-field-label"><?php esc_html_e( 'Installment Count', 'jwpm' ); ?></span>
											<input type="number" class="jwpm-input" name="installment_count" data-jwpm-installments-input="installment_count" />
										</label>
										<label class="jwpm-field">
											<span class="jwpm-field-label"><?php esc_html_e( 'Frequency', 'jwpm' ); ?></span>
											<select class="jwpm-select" name="installment_frequency" data-jwpm-installments-input="installment_frequency">
												<option value="monthly"><?php esc_html_e( 'Monthly', 'jwpm' ); ?></option>
												<option value="weekly"><?php esc_html_e( 'Weekly', 'jwpm' ); ?></option>
												<option value="custom"><?php esc_html_e( 'Custom', 'jwpm' ); ?></option>
											</select>
										</label>
										<label class="jwpm-field">
											<span class="jwpm-field-label"><?php esc_html_e( 'First Due Date', 'jwpm' ); ?></span>
											<input type="date" class="jwpm-input" name="start_date" data-jwpm-installments-input="start_date" />
										</label>
										<label class="jwpm-field">
											<span class="jwpm-field-label"><?php esc_html_e( 'Status', 'jwpm' ); ?></span>
											<select class="jwpm-select" name="status" data-jwpm-installments-input="status">
												<option value="active"><?php esc_html_e( 'Active', 'jwpm' ); ?></option>
												<option value="completed"><?php esc_html_e( 'Completed', 'jwpm' ); ?></option>
												<option value="defaulted"><?php esc_html_e( 'Defaulted', 'jwpm' ); ?></option>
												<option value="cancelled"><?php esc_html_e( 'Cancelled', 'jwpm' ); ?></option>
											</select>
										</label>
										<label class="jwpm-field jwpm-field-full">
											<span class="jwpm-field-label"><?php esc_html_e( 'Remarks', 'jwpm' ); ?></span>
											<textarea class="jwpm-textarea" name="remarks" rows="3" data-jwpm-installments-input="remarks"></textarea>
										</label>
									</div>
									<label class="jwpm-field-inline">
										<input type="checkbox" name="auto_generate_schedule" value="1" data-jwpm-installments-input="auto_generate_schedule" checked />
										<span class="jwpm-field-label"><?php esc_html_e( 'Schedule خود بخود بنائیں (equal installments)', 'jwpm' ); ?></span>
									</label>
								</section>
							</form>
						</div>

						<div class="jwpm-tab-panel" data-jwpm-installments-tab-panel="schedule">
							<div class="jwpm-schedule-summary">
								<span data-jwpm-installments-sched-stat="total"></span>
								<span data-jwpm-installments-sched-stat="paid"></span>
								<span data-jwpm-installments-sched-stat="pending"></span>
								<span data-jwpm-installments-sched-stat="overdue"></span>
							</div>
							<div class="jwpm-schedule-actions">
								<button type="button" class="button" data-jwpm-installments-action="recalc-even">
									<?php esc_html_e( 'Recalculate Evenly', 'jwpm' ); ?>
								</button>
								<button type="button" class="button" data-jwpm-installments-action="refresh-schedule">
									<?php esc_html_e( 'Refresh Schedule', 'jwpm' ); ?>
								</button>
							</div>
							<div class="jwpm-schedule-table-wrap">
								<table class="jwpm-table jwpm-table-schedule">
									<thead>
										<tr>
											<th>#</th>
											<th><?php esc_html_e( 'Due Date', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Amount', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Paid', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Status', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Paid Date', 'jwpm' ); ?></th>
										</tr>
									</thead>
									<tbody data-jwpm-installments-schedule-body>
										<tr class="jwpm-empty-row">
											<td colspan="6">
												<?php esc_html_e( 'ابھی کوئی Schedule موجود نہیں، Overview میں Auto-generate on save استعمال کریں۔', 'jwpm' ); ?>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>

						<div class="jwpm-tab-panel" data-jwpm-installments-tab-panel="payments">
							<div class="jwpm-payments-header">
								<button type="button" class="button button-primary" data-jwpm-installments-action="add-payment">
									<?php esc_html_e( 'Add Payment', 'jwpm' ); ?>
								</button>
							</div>
							<div class="jwpm-payments-table-wrap">
								<table class="jwpm-table jwpm-table-payments">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Date', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Amount', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Method', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Reference', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Received By', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Note', 'jwpm' ); ?></th>
										</tr>
									</thead>
									<tbody data-jwpm-installments-payments-body>
										<tr class="jwpm-empty-row">
											<td colspan="6">
												<?php esc_html_e( 'ابھی کوئی Payment درج نہیں ہوئی۔', 'jwpm' ); ?>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<footer class="jwpm-side-panel-footer">
						<button type="button" class="button button-primary" data-jwpm-installments-action="save-contract">
							<?php esc_html_e( 'Save Plan', 'jwpm' ); ?>
						</button>
						<button type="button" class="button" data-jwpm-installments-action="close-panel">
							<?php esc_html_e( 'Close', 'jwpm' ); ?>
						</button>
					</footer>
				</div>
			</template>

			<?php
			/**
			 * Payment Modal Template
			 */
			?>
			<template id="jwpm-installments-payment-modal-template">
				<div class="jwpm-modal jwpm-modal-payment" role="dialog" aria-modal="true">
					<div class="jwpm-modal-overlay" data-jwpm-installments-action="close-payment"></div>
					<div class="jwpm-modal-content">
						<header class="jwpm-modal-header">
							<h2 class="jwpm-modal-title"><?php esc_html_e( 'Receive Payment', 'jwpm' ); ?></h2>
							<button type="button" class="jwpm-modal-close" data-jwpm-installments-action="close-payment">×</button>
						</header>
						<div class="jwpm-modal-body">
							<form data-jwpm-installments-payment-form>
								<input type="hidden" name="contract_id" data-jwpm-installments-payment-input="contract_id" />
								<label class="jwpm-field">
									<span class="jwpm-field-label"><?php esc_html_e( 'Payment Date', 'jwpm' ); ?></span>
									<input type="date" class="jwpm-input" name="payment_date" data-jwpm-installments-payment-input="payment_date" />
								</label>
								<label class="jwpm-field">
									<span class="jwpm-field-label"><?php esc_html_e( 'Amount', 'jwpm' ); ?></span>
									<input type="number" step="0.001" class="jwpm-input" name="amount" data-jwpm-installments-payment-input="amount" />
								</label>
								<label class="jwpm-field">
									<span class="jwpm-field-label"><?php esc_html_e( 'Method', 'jwpm' ); ?></span>
									<select class="jwpm-select" name="method" data-jwpm-installments-payment-input="method">
										<option value="cash"><?php esc_html_e( 'Cash', 'jwpm' ); ?></option>
										<option value="card"><?php esc_html_e( 'Card', 'jwpm' ); ?></option>
										<option value="bank"><?php esc_html_e( 'Bank Transfer', 'jwpm' ); ?></option>
										<option value="other"><?php esc_html_e( 'Other', 'jwpm' ); ?></option>
									</select>
								</label>
								<label class="jwpm-field">
									<span class="jwpm-field-label"><?php esc_html_e( 'Reference No.', 'jwpm' ); ?></span>
									<input type="text" class="jwpm-input" name="reference_no" data-jwpm-installments-payment-input="reference_no" />
								</label>
								<label class="jwpm-field jwpm-field-full">
									<span class="jwpm-field-label"><?php esc_html_e( 'Note', 'jwpm' ); ?></span>
									<textarea class="jwpm-textarea" name="note" rows="3" data-jwpm-installments-payment-input="note"></textarea>
								</label>
							</form>
						</div>
						<footer class="jwpm-modal-footer">
							<button type="button" class="button button-primary" data-jwpm-installments-action="save-payment">
								<?php esc_html_e( 'Save Payment', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-installments-action="close-payment">
								<?php esc_html_e( 'Cancel', 'jwpm' ); ?>
							</button>
						</footer>
					</div>
				</div>
			</template>

			<?php
			/**
			 * Import Modal Template (Installment Plans)
			 */
			?>
			<template id="jwpm-installments-import-template">
				<div class="jwpm-modal jwpm-modal-import-installments" role="dialog" aria-modal="true">
					<div class="jwpm-modal-overlay" data-jwpm-installments-action="close-import"></div>
					<div class="jwpm-modal-content">
						<header class="jwpm-modal-header">
							<h2 class="jwpm-modal-title"><?php esc_html_e( 'Import Installment Plans (CSV)', 'jwpm' ); ?></h2>
							<button type="button" class="jwpm-modal-close" data-jwpm-installments-action="close-import">×</button>
						</header>
						<div class="jwpm-modal-body">
							<p><?php esc_html_e( 'براہ کرم (CSV) فائل اپلوڈ کریں، کم از کم Customer Phone، Total Amount اور Installment Count کالم ضروری ہیں۔', 'jwpm' ); ?></p>
							<form data-jwpm-installments-import-form>
								<input type="file" name="file" accept=".csv,text/csv" required />
								<label class="jwpm-field-inline">
									<input type="checkbox" name="skip_duplicates" value="1" checked />
									<span><?php esc_html_e( 'Contract Code کے مطابق ڈپلیکیٹ پلان کو چھوڑ دیں۔', 'jwpm' ); ?></span>
								</label>
							</form>
							<div class="jwpm-import-result" data-jwpm-installments-import-result></div>
						</div>
						<footer class="jwpm-modal-footer">
							<button type="button" class="button button-primary" data-jwpm-installments-action="do-import">
								<?php esc_html_e( 'Upload & Import', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-installments-action="close-import">
								<?php esc_html_e( 'Cancel', 'jwpm' ); ?>
							</button>
						</footer>
					</div>
				</div>
			</template>
		</div>
		<?php
	}
}

// 🔴 یہاں پر [Installments Page Templates] ختم ہو رہا ہے
// ✅ Syntax verified block end
