<?php
/** Part 4 — JWPM Repair Jobs Admin Page
 * یہاں Repair Jobs / Workshop Repairs پیج کے لیے HTML Root + Templates ہیں۔
 */

// 🟢 یہاں سے [JWPM Repair Admin Page] شروع ہو رہا ہے

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ایڈمن پیج رینڈر فنکشن
 * اسے main plugin / menu registration میں use کریں:
 * add_submenu_page(..., 'Repair Jobs', ..., 'manage_options', 'jwpm-repair', 'jwpm_render_repair_page');
 */
function jwpm_render_repair_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'آپ کو اس صفحہ تک رسائی کی اجازت نہیں۔', 'jwpm' ) );
	}
	?>
	<div class="wrap jwpm-page-repair-wrap">
		<h1 class="screen-reader-text">
			<?php esc_html_e( 'Repair Jobs / Workshop Repairs', 'jwpm' ); ?>
		</h1>

		<div id="jwpm-repair-root"></div>

		<?php
		// Nonces — JS میں localize بھی ہوں گے، مگر HTML میں بھی گریس فل fallback کے لیے:
		wp_nonce_field( 'jwpm_repair_main_nonce', 'jwpm_repair_main_nonce_field' );
		wp_nonce_field( 'jwpm_repair_import_nonce', 'jwpm_repair_import_nonce_field' );
		wp_nonce_field( 'jwpm_repair_export_nonce', 'jwpm_repair_export_nonce_field' );
		wp_nonce_field( 'jwpm_repair_demo_nonce', 'jwpm_repair_demo_nonce_field' );
		?>

		<!-- Layout Template -->
		<template id="jwpm-repair-layout-template">
			<div class="jwpm-page-repair">
				<!-- Header -->
				<header class="jwpm-page-header">
					<div class="jwpm-page-title-group">
						<h2 class="jwpm-page-title"><?php esc_html_e( 'Repair Jobs / Workshop Repairs', 'jwpm' ); ?></h2>
						<p class="jwpm-page-subtitle">
							<?php esc_html_e( 'مرمت کے آرڈرز، ورکشاپ اسٹیٹس اور Delivery Status ایک ہی جگہ۔', 'jwpm' ); ?>
						</p>
					</div>
					<div class="jwpm-page-header-stats">
						<div class="jwpm-stat-card" data-jwpm-repair-stat="workshop">
							<div class="jwpm-stat-label"><?php esc_html_e( 'Jobs In Workshop', 'jwpm' ); ?></div>
							<div class="jwpm-stat-value">0</div>
						</div>
						<div class="jwpm-stat-card" data-jwpm-repair-stat="ready">
							<div class="jwpm-stat-label"><?php esc_html_e( 'Ready (Not Delivered)', 'jwpm' ); ?></div>
							<div class="jwpm-stat-value">0</div>
						</div>
						<div class="jwpm-stat-card" data-jwpm-repair-stat="overdue">
							<div class="jwpm-stat-label"><?php esc_html_e( 'Overdue Repairs', 'jwpm' ); ?></div>
							<div class="jwpm-stat-value">0</div>
						</div>
							<div class="jwpm-stat-card" data-jwpm-repair-stat="pending_amount">
							<div class="jwpm-stat-label"><?php esc_html_e( 'Pending Charges', 'jwpm' ); ?></div>
							<div class="jwpm-stat-value">0.000</div>
						</div>
					</div>
				</header>

				<!-- Toolbar -->
				<section class="jwpm-toolbar jwpm-repair-toolbar" aria-label="<?php esc_attr_e( 'Repair filters and actions', 'jwpm' ); ?>">
					<div class="jwpm-toolbar-filters">
						<input type="search"
							class="jwpm-input"
							placeholder="<?php esc_attr_e( 'Search: customer / phone / tag / code', 'jwpm' ); ?>"
							data-jwpm-repair-filter="search" />

						<select class="jwpm-select" data-jwpm-repair-filter="status">
							<option value=""><?php esc_html_e( 'All Status', 'jwpm' ); ?></option>
							<option value="received"><?php esc_html_e( 'Received', 'jwpm' ); ?></option>
							<option value="in_workshop"><?php esc_html_e( 'In Workshop', 'jwpm' ); ?></option>
							<option value="ready"><?php esc_html_e( 'Ready', 'jwpm' ); ?></option>
							<option value="delivered"><?php esc_html_e( 'Delivered', 'jwpm' ); ?></option>
							<option value="cancelled"><?php esc_html_e( 'Cancelled', 'jwpm' ); ?></option>
						</select>

						<select class="jwpm-select" data-jwpm-repair-filter="priority">
							<option value=""><?php esc_html_e( 'All Priority', 'jwpm' ); ?></option>
							<option value="normal"><?php esc_html_e( 'Normal', 'jwpm' ); ?></option>
							<option value="urgent"><?php esc_html_e( 'Urgent', 'jwpm' ); ?></option>
							<option value="vip"><?php esc_html_e( 'VIP', 'jwpm' ); ?></option>
						</select>

						<input type="date"
							class="jwpm-input"
							aria-label="<?php esc_attr_e( 'Promised from', 'jwpm' ); ?>"
							data-jwpm-repair-filter="date_from" />
						<input type="date"
							class="jwpm-input"
							aria-label="<?php esc_attr_e( 'Promised to', 'jwpm' ); ?>"
							data-jwpm-repair-filter="date_to" />
					</div>

					<div class="jwpm-toolbar-actions">
						<button type="button"
							class="button button-primary"
							data-jwpm-repair-action="add">
							<?php esc_html_e( '➕ New Repair Job', 'jwpm' ); ?>
						</button>

						<button type="button"
							class="button"
							data-jwpm-repair-action="print">
							<?php esc_html_e( '🖨 Print Repairs', 'jwpm' ); ?>
						</button>

						<button type="button"
							class="button"
							data-jwpm-repair-action="export">
							<?php esc_html_e( '⬆ Export Excel', 'jwpm' ); ?>
						</button>

						<button type="button"
							class="button"
							data-jwpm-repair-action="import">
							<?php esc_html_e( '⬇ Import CSV', 'jwpm' ); ?>
						</button>

						<div class="jwpm-dropdown">
							<button type="button" class="button">
								<?php esc_html_e( '🧪 Demo Data', 'jwpm' ); ?>
							</button>
							<div class="jwpm-dropdown-menu">
								<button type="button"
									class="jwpm-dropdown-item"
									data-jwpm-repair-action="demo-create">
									<?php esc_html_e( 'Create Demo Repairs', 'jwpm' ); ?>
								</button>
								<button type="button"
									class="jwpm-dropdown-item"
									data-jwpm-repair-action="demo-clear">
									<?php esc_html_e( 'Clear Demo Repairs', 'jwpm' ); ?>
								</button>
							</div>
						</div>
					</div>
				</section>

				<!-- Main: Table + Side Panel -->
				<section class="jwpm-repair-main">
					<!-- Table -->
					<div class="jwpm-repair-table-wrap">
						<table class="wp-list-table widefat fixed striped jwpm-table jwpm-table-repairs" aria-label="<?php esc_attr_e( 'Repair Jobs', 'jwpm' ); ?>">
							<thead>
							<tr>
								<th><?php esc_html_e( 'Job Code / Tag', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Phone', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Item', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Job Type', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Promised Date', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Job Status', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Charges', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Balance', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Priority', 'jwpm' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'jwpm' ); ?></th>
							</tr>
							</thead>
							<tbody data-jwpm-repair-table-body>
							<tr class="jwpm-loading-row">
								<td colspan="11">
									<?php esc_html_e( 'Repair Jobs لوڈ ہو رہے ہیں…', 'jwpm' ); ?>
								</td>
							</tr>
							</tbody>
						</table>

						<div class="jwpm-pagination" data-jwpm-repair-pagination></div>
					</div>

					<!-- Side Panel (dynamic) -->
					<aside class="jwpm-repair-side-panel" data-jwpm-repair-side-panel hidden></aside>
				</section>
			</div>
		</template>

		<!-- Row Template -->
		<template id="jwpm-repair-row-template">
			<tr data-jwpm-repair-row>
				<td>
					<span data-jwpm-repair-field="job_code"></span><br />
					<small data-jwpm-repair-field="tag_no" class="description"></small>
				</td>
				<td data-jwpm-repair-field="customer_name"></td>
				<td data-jwpm-repair-field="customer_phone"></td>
				<td data-jwpm-repair-field="item_description"></td>
				<td data-jwpm-repair-field="job_type"></td>
				<td data-jwpm-repair-field="promised_date"></td>
				<td>
					<span data-jwpm-repair-field="status_badge"></span>
				</td>
				<td data-jwpm-repair-field="actual_charges"></td>
				<td data-jwpm-repair-field="balance_amount"></td>
				<td>
					<span data-jwpm-repair-field="priority_badge"></span>
				</td>
				<td class="jwpm-table-actions">
					<a href="#"
						class="button-link"
						data-jwpm-repair-action="view"><?php esc_html_e( 'View', 'jwpm' ); ?></a>
					<a href="#"
						class="button-link"
						data-jwpm-repair-action="mark-ready"><?php esc_html_e( 'Ready', 'jwpm' ); ?></a>
					<a href="#"
						class="button-link"
						data-jwpm-repair-action="mark-delivered"><?php esc_html_e( 'Delivered', 'jwpm' ); ?></a>
					<a href="#"
						class="button-link"
						data-jwpm-repair-action="print-ticket"><?php esc_html_e( 'Ticket', 'jwpm' ); ?></a>
					<a href="#"
						class="button-link jwpm-text-danger"
						data-jwpm-repair-action="delete"><?php esc_html_e( 'Cancel', 'jwpm' ); ?></a>
				</td>
			</tr>
		</template>

		<!-- Side Panel Template -->
		<template id="jwpm-repair-panel-template">
			<div class="jwpm-side-panel-inner">
				<header class="jwpm-side-panel-header">
					<div>
						<h3 class="jwpm-side-panel-title" data-jwpm-repair-panel-title>New Repair Job</h3>
						<p class="jwpm-side-panel-subtitle" data-jwpm-repair-panel-subtitle></p>
					</div>
					<div class="jwpm-side-panel-header-meta">
						<span data-jwpm-repair-panel-tag class="jwpm-priority-badge" aria-label="<?php esc_attr_e( 'Tag No', 'jwpm' ); ?>"></span>
						<span data-jwpm-repair-panel-status class="jwpm-status-badge" aria-label="<?php esc_attr_e( 'Job Status', 'jwpm' ); ?>"></span>
						<span data-jwpm-repair-panel-priority class="jwpm-priority-badge" aria-label="<?php esc_attr_e( 'Priority', 'jwpm' ); ?>"></span>
						<button type="button"
							class="jwpm-side-panel-close"
							aria-label="<?php esc_attr_e( 'Close', 'jwpm' ); ?>"
							data-jwpm-repair-action="close-panel">&times;</button>
					</div>
				</header>

				<nav class="jwpm-side-panel-tabs" aria-label="<?php esc_attr_e( 'Repair detail tabs', 'jwpm' ); ?>">
					<button type="button"
						class="jwpm-tab is-active"
						data-jwpm-repair-tab="overview">
						<?php esc_html_e( 'Overview', 'jwpm' ); ?>
					</button>
					<button type="button"
						class="jwpm-tab"
						data-jwpm-repair-tab="workshop">
						<?php esc_html_e( 'Workshop', 'jwpm' ); ?>
					</button>
					<button type="button"
						class="jwpm-tab"
						data-jwpm-repair-tab="timeline">
						<?php esc_html_e( 'Timeline', 'jwpm' ); ?>
					</button>
				</nav>

				<div class="jwpm-side-panel-body">
					<form data-jwpm-repair-form autocomplete="off">
						<input type="hidden" name="id" value="" data-jwpm-repair-input="id" />

						<!-- Overview Tab -->
						<div class="jwpm-tab-panel is-active" data-jwpm-repair-tab-panel="overview">
							<section class="jwpm-form-section">
								<h4 class="jwpm-form-section-title"><?php esc_html_e( 'Customer & Item', 'jwpm' ); ?></h4>
								<div class="jwpm-form-grid">
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Customer Name', 'jwpm' ); ?>
										</label>
										<input type="text"
											class="jwpm-input"
											name="customer_name"
											data-jwpm-repair-input="customer_name" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Phone', 'jwpm' ); ?>
										</label>
										<input type="text"
											class="jwpm-input"
											name="customer_phone"
											data-jwpm-repair-input="customer_phone" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Tag No', 'jwpm' ); ?>
										</label>
										<input type="text"
											class="jwpm-input"
											name="tag_no"
											data-jwpm-repair-input="tag_no" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Job Code (optional)', 'jwpm' ); ?>
										</label>
										<input type="text"
											class="jwpm-input"
											name="job_code"
											data-jwpm-repair-input="job_code" />
									</div>
									<div class="jwpm-field jwpm-field-full">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Item Description', 'jwpm' ); ?>
										</label>
										<input type="text"
											class="jwpm-input"
											name="item_description"
											data-jwpm-repair-input="item_description" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Job Type', 'jwpm' ); ?>
										</label>
										<select class="jwpm-select"
											name="job_type"
											data-jwpm-repair-input="job_type">
											<option value="resize"><?php esc_html_e( 'Resize', 'jwpm' ); ?></option>
											<option value="stone_setting"><?php esc_html_e( 'Stone Setting', 'jwpm' ); ?></option>
											<option value="solder"><?php esc_html_e( 'Solder / Join', 'jwpm' ); ?></option>
											<option value="polish"><?php esc_html_e( 'Polish / Finish', 'jwpm' ); ?></option>
											<option value="other"><?php esc_html_e( 'Other', 'jwpm' ); ?></option>
										</select>
									</div>
								</div>
							</section>

							<section class="jwpm-form-section">
								<h4 class="jwpm-form-section-title"><?php esc_html_e( 'Problem & Instructions', 'jwpm' ); ?></h4>
								<div class="jwpm-form-grid">
									<div class="jwpm-field jwpm-field-full">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Problems / Issues', 'jwpm' ); ?>
										</label>
										<textarea class="jwpm-textarea"
											rows="3"
											name="problems"
											data-jwpm-repair-input="problems"></textarea>
									</div>
									<div class="jwpm-field jwpm-field-full">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Customer Instructions', 'jwpm' ); ?>
										</label>
										<textarea class="jwpm-textarea"
											rows="3"
											name="instructions"
											data-jwpm-repair-input="instructions"></textarea>
									</div>
								</div>
							</section>

							<section class="jwpm-form-section">
								<h4 class="jwpm-form-section-title"><?php esc_html_e( 'Dates & Charges', 'jwpm' ); ?></h4>
								<div class="jwpm-form-grid">
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Received Date', 'jwpm' ); ?>
										</label>
										<input type="date"
											class="jwpm-input"
											name="received_date"
											data-jwpm-repair-input="received_date" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Promised Date', 'jwpm' ); ?>
										</label>
										<input type="date"
											class="jwpm-input"
											name="promised_date"
											data-jwpm-repair-input="promised_date" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Delivered Date', 'jwpm' ); ?>
										</label>
										<input type="date"
											class="jwpm-input"
											name="delivered_date"
											data-jwpm-repair-input="delivered_date" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Gold Weight IN', 'jwpm' ); ?>
										</label>
										<input type="number"
											step="0.001"
											class="jwpm-input"
											name="gold_weight_in"
											data-jwpm-repair-input="gold_weight_in" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Gold Weight OUT', 'jwpm' ); ?>
										</label>
										<input type="number"
											step="0.001"
											class="jwpm-input"
											name="gold_weight_out"
											data-jwpm-repair-input="gold_weight_out" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Estimated Charges', 'jwpm' ); ?>
										</label>
										<input type="number"
											step="0.01"
											class="jwpm-input"
											name="estimated_charges"
											data-jwpm-repair-input="estimated_charges" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Actual Charges', 'jwpm' ); ?>
										</label>
										<input type="number"
											step="0.01"
											class="jwpm-input"
											name="actual_charges"
											data-jwpm-repair-input="actual_charges" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Advance Amount', 'jwpm' ); ?>
										</label>
										<input type="number"
											step="0.01"
											class="jwpm-input"
											name="advance_amount"
											data-jwpm-repair-input="advance_amount" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Balance Amount', 'jwpm' ); ?>
										</label>
										<input type="number"
											step="0.01"
											class="jwpm-input"
											name="balance_amount"
											data-jwpm-repair-input="balance_amount"
											readonly />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Payment Status', 'jwpm' ); ?>
										</label>
										<select class="jwpm-select"
											name="payment_status"
											data-jwpm-repair-input="payment_status">
											<option value="unpaid"><?php esc_html_e( 'Unpaid', 'jwpm' ); ?></option>
											<option value="partial"><?php esc_html_e( 'Partial', 'jwpm' ); ?></option>
											<option value="paid"><?php esc_html_e( 'Paid', 'jwpm' ); ?></option>
										</select>
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Job Status', 'jwpm' ); ?>
										</label>
										<select class="jwpm-select"
											name="job_status"
											data-jwpm-repair-input="job_status">
											<option value="received"><?php esc_html_e( 'Received', 'jwpm' ); ?></option>
											<option value="in_workshop"><?php esc_html_e( 'In Workshop', 'jwpm' ); ?></option>
											<option value="ready"><?php esc_html_e( 'Ready', 'jwpm' ); ?></option>
											<option value="delivered"><?php esc_html_e( 'Delivered', 'jwpm' ); ?></option>
											<option value="cancelled"><?php esc_html_e( 'Cancelled', 'jwpm' ); ?></option>
										</select>
									</div>
								</div>
							</section>
						</div>

						<!-- Workshop Tab -->
						<div class="jwpm-tab-panel" data-jwpm-repair-tab-panel="workshop">
							<section class="jwpm-form-section">
								<h4 class="jwpm-form-section-title"><?php esc_html_e( 'Workshop Details', 'jwpm' ); ?></h4>
								<div class="jwpm-form-grid">
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Assigned To (Goldsmith/Workshop)', 'jwpm' ); ?>
										</label>
										<input type="text"
											class="jwpm-input"
											name="assigned_to"
											data-jwpm-repair-input="assigned_to" />
									</div>
									<div class="jwpm-field">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Priority', 'jwpm' ); ?>
										</label>
										<select class="jwpm-select"
											name="priority"
											data-jwpm-repair-input="priority">
											<option value="normal"><?php esc_html_e( 'Normal', 'jwpm' ); ?></option>
											<option value="urgent"><?php esc_html_e( 'Urgent', 'jwpm' ); ?></option>
											<option value="vip"><?php esc_html_e( 'VIP', 'jwpm' ); ?></option>
										</select>
									</div>
									<div class="jwpm-field jwpm-field-full">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Workshop Notes', 'jwpm' ); ?>
										</label>
										<textarea class="jwpm-textarea"
											rows="3"
											name="workshop_notes"
											data-jwpm-repair-input="workshop_notes"></textarea>
									</div>
									<div class="jwpm-field jwpm-field-full">
										<label class="jwpm-field-label">
											<?php esc_html_e( 'Internal Remarks (not on ticket)', 'jwpm' ); ?>
										</label>
										<textarea class="jwpm-textarea"
											rows="3"
											name="internal_remarks"
											data-jwpm-repair-input="internal_remarks"></textarea>
									</div>
								</div>
							</section>
						</div>

						<!-- Timeline Tab -->
						<div class="jwpm-tab-panel" data-jwpm-repair-tab-panel="timeline">
							<section class="jwpm-form-section">
								<h4 class="jwpm-form-section-title"><?php esc_html_e( 'Timeline / History', 'jwpm' ); ?></h4>

								<div class="jwpm-repair-timeline-header">
									<div class="jwpm-repair-timeline-mini-form">
										<select class="jwpm-select"
											data-jwpm-repair-log-input="status">
											<option value="received"><?php esc_html_e( 'Received', 'jwpm' ); ?></option>
											<option value="in_workshop"><?php esc_html_e( 'In Workshop', 'jwpm' ); ?></option>
											<option value="ready"><?php esc_html_e( 'Ready', 'jwpm' ); ?></option>
											<option value="delivered"><?php esc_html_e( 'Delivered', 'jwpm' ); ?></option>
											<option value="cancelled"><?php esc_html_e( 'Cancelled', 'jwpm' ); ?></option>
										</select>
										<input type="text"
											class="jwpm-input"
											placeholder="<?php esc_attr_e( 'Note (optional)', 'jwpm' ); ?>"
											data-jwpm-repair-log-input="note" />
										<button type="button"
											class="button button-secondary"
											data-jwpm-repair-action="add-log">
											<?php esc_html_e( 'Add Update', 'jwpm' ); ?>
										</button>
									</div>
								</div>

								<div class="jwpm-repair-logs-table-wrap">
									<table class="jwpm-table">
										<thead>
										<tr>
											<th><?php esc_html_e( 'Date / Time', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Status', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Note', 'jwpm' ); ?></th>
											<th><?php esc_html_e( 'Updated By', 'jwpm' ); ?></th>
										</tr>
										</thead>
										<tbody data-jwpm-repair-logs-body>
										<tr class="jwpm-empty-row">
											<td colspan="4">
												<?php esc_html_e( 'ابھی کوئی history موجود نہیں۔', 'jwpm' ); ?>
											</td>
										</tr>
										</tbody>
									</table>
								</div>
							</section>
						</div>
					</form>
				</div>

				<footer class="jwpm-side-panel-footer">
					<button type="button"
						class="button button-secondary"
						data-jwpm-repair-action="close-panel">
						<?php esc_html_e( 'Close', 'jwpm' ); ?>
					</button>
					<button type="button"
						class="button button-primary"
						data-jwpm-repair-action="save-repair">
						<?php esc_html_e( 'Save Repair', 'jwpm' ); ?>
					</button>
				</footer>
			</div>
		</template>

		<!-- Import Modal Template -->
		<template id="jwpm-repair-import-template">
			<div class="jwpm-modal" role="dialog" aria-modal="true" aria-labelledby="jwpm-repair-import-title">
				<div class="jwpm-modal-overlay" data-jwpm-repair-action="close-import"></div>
				<div class="jwpm-modal-content">
					<header class="jwpm-modal-header">
						<h3 class="jwpm-modal-title" id="jwpm-repair-import-title">
							<?php esc_html_e( 'Import Repairs (CSV)', 'jwpm' ); ?>
						</h3>
						<button type="button"
							class="jwpm-modal-close"
							aria-label="<?php esc_attr_e( 'Close', 'jwpm' ); ?>"
							data-jwpm-repair-action="close-import">&times;</button>
					</header>
					<div class="jwpm-modal-body">
						<form data-jwpm-repair-import-form enctype="multipart/form-data">
							<p><?php esc_html_e( 'CSV فائل منتخب کریں جس میں Repair Jobs کا ڈیٹا ہو۔', 'jwpm' ); ?></p>
							<p>
								<input type="file" name="file" accept=".csv,text/csv" />
							</p>
							<label>
								<input type="checkbox" name="skip_duplicates" value="1" checked />
								<?php esc_html_e( 'اگر Job Code پہلے سے موجود ہو تو اسے skip کریں۔', 'jwpm' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Required columns: job_code, customer_name, customer_phone, item_description, job_type, received_date, promised_date, estimated_charges, actual_charges, advance_amount, job_status, priority', 'jwpm' ); ?>
							</p>
						</form>
						<div class="jwpm-repair-import-result" data-jwpm-repair-import-result></div>
					</div>
					<footer class="jwpm-modal-footer">
						<button type="button"
							class="button button-secondary"
							data-jwpm-repair-action="close-import">
							<?php esc_html_e( 'Close', 'jwpm' ); ?>
						</button>
						<button type="button"
							class="button button-primary"
							data-jwpm-repair-action="do-import">
							<?php esc_html_e( 'Start Import', 'jwpm' ); ?>
						</button>
					</footer>
				</div>
			</div>
		</template>
	</div>
	<?php
}

// 🔴 یہاں پر [JWPM Repair Admin Page] ختم ہو رہا ہے
// ✅ Syntax verified block end

