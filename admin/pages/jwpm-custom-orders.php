<?php
/**
 * JWPM Custom Orders Admin Page
 *
 * یہ فائل (Custom Orders Module) کے لیے (WP Admin) پیج رینڈر کرتی ہے۔
 * یہاں صرف (PHP + HTML + <template>) ہیں، باقی سارا (AJAX + UI Logic) الگ (JavaScript) میں ہو گا۔
 *
 * @package    JWPM
 * @subpackage JWPM/admin/pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 🟢 یہاں سے jwpm-custom-orders.php Admin Page شروع ہو رہا ہے

// بنیادی (capability) – چاہیں تو بعد میں Settings سے dynamic بھی بنا سکتے ہیں
$can_manage = current_user_can( 'manage_jwpm_inventory' ) || current_user_can( 'manage_options' );

// (nonce) ویلیوز جو (JavaScript) میں (AJAX) کے لیے استعمال ہوں گی
$nonce_main   = wp_create_nonce( 'jwpm_custom_orders_main_nonce' );
$nonce_import = wp_create_nonce( 'jwpm_custom_orders_import_nonce' );
$nonce_export = wp_create_nonce( 'jwpm_custom_orders_export_nonce' );

// بعد میں (JavaScript) فائل یہ handle استعمال کرے گی (آپ اپنے plugin میں اس کو register/enqueue کریں گے)
$script_handle = 'jwpm-custom-orders-js'; // مثال کے طور پر
$style_handle  = 'jwpm-custom-orders-css';

// اگر اسکرپٹ پہلے سے enqueue ہے تو اس کے ساتھ data بھیج دیں
if ( wp_script_is( $script_handle, 'enqueued' ) ) {
	wp_localize_script(
		$script_handle,
		'JWPM_CUSTOM_ORDERS_CONFIG',
		array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'nonce_main'    => $nonce_main,
			'nonce_import'  => $nonce_import,
			'nonce_export'  => $nonce_export,
			'capabilities'  => array(
				'can_manage' => (bool) $can_manage,
			),
			'i18n'          => array(
				'title'              => __( 'Custom Orders', 'jwpm-jewelry-pos-manager' ),
				'btn_add'            => __( 'New Custom Order', 'jwpm-jewelry-pos-manager' ),
				'btn_import'         => __( 'Import', 'jwpm-jewelry-pos-manager' ),
				'btn_export'         => __( 'Export', 'jwpm-jewelry-pos-manager' ),
				'btn_demo_create'    => __( 'Create Demo Data', 'jwpm-jewelry-pos-manager' ),
				'btn_demo_delete'    => __( 'Delete Demo Data', 'jwpm-jewelry-pos-manager' ),
				'btn_excel'          => __( 'Download Excel', 'jwpm-jewelry-pos-manager' ),
				'btn_print'          => __( 'Print', 'jwpm-jewelry-pos-manager' ),
				'no_permission'      => __( 'You do not have permission to manage custom orders.', 'jwpm-jewelry-pos-manager' ),
				'no_records'         => __( 'No custom orders found.', 'jwpm-jewelry-pos-manager' ),
			),
		)
	);
}

?>
<div class="wrap jwpm-admin-page jwpm-custom-orders-page">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Custom Orders', 'jwpm-jewelry-pos-manager' ); ?>
	</h1>

	<hr class="wp-header-end" />

	<?php if ( ! $can_manage ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'آپ کے پاس Custom Orders مینیج کرنے کی اجازت نہیں ہے۔', 'jwpm-jewelry-pos-manager' ); ?></p>
		</div>
	<?php endif; ?>

	<noscript>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'یہ پیج (JavaScript) کے بغیر درست طریقے سے کام نہیں کرے گا۔ براہ کرم اپنے براؤزر میں (JavaScript) آن کریں۔', 'jwpm-jewelry-pos-manager' ); ?></p>
		</div>
	</noscript>

	<!-- 🟢 یہاں سے Root Container شروع ہو رہا ہے -->
	<div id="jwpm-custom-orders-root" class="jwpm-page-root" data-can-manage="<?php echo esc_attr( $can_manage ? '1' : '0' ); ?>">

		<!-- Top Toolbar: Actions + Import/Export/Excel/Print -->
		<section class="jwpm-co-toolbar">
			<div class="jwpm-co-toolbar-left">
				<button type="button" class="button button-primary jwpm-co-btn-add" <?php disabled( ! $can_manage ); ?>>
					<?php esc_html_e( 'نیا Custom Order بنائیں', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</div>

			<div class="jwpm-co-toolbar-right">
				<button type="button" class="button jwpm-co-btn-import" <?php disabled( ! $can_manage ); ?>>
					<?php esc_html_e( 'Import', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button jwpm-co-btn-export">
					<?php esc_html_e( 'Export', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button jwpm-co-btn-demo-create" <?php disabled( ! $can_manage ); ?>>
					<?php esc_html_e( 'Demo Data بنائیں', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button jwpm-co-btn-demo-delete" <?php disabled( ! $can_manage ); ?>>
					<?php esc_html_e( 'Demo Data حذف کریں', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button jwpm-co-btn-excel">
					<?php esc_html_e( 'Excel ڈاؤن لوڈ', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button jwpm-co-btn-print">
					<?php esc_html_e( 'پرنٹ', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</div>
		</section>

		<!-- Filters Bar -->
		<section class="jwpm-co-filters">
			<div class="jwpm-co-filter-item">
				<label for="jwpm-co-filter-search"><?php esc_html_e( 'تلاش', 'jwpm-jewelry-pos-manager' ); ?></label>
				<input type="search" id="jwpm-co-filter-search" class="regular-text" placeholder="<?php esc_attr_e( 'کسٹمر نام، فون، Design Ref...', 'jwpm-jewelry-pos-manager' ); ?>" />
			</div>

			<div class="jwpm-co-filter-item">
				<label for="jwpm-co-filter-status"><?php esc_html_e( 'Status', 'jwpm-jewelry-pos-manager' ); ?></label>
				<select id="jwpm-co-filter-status">
					<option value=""><?php esc_html_e( 'سب', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="designing"><?php esc_html_e( 'Designing', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="in_progress"><?php esc_html_e( 'In Progress', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="ready"><?php esc_html_e( 'Ready', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="delivered"><?php esc_html_e( 'Delivered', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="cancelled"><?php esc_html_e( 'Cancelled', 'jwpm-jewelry-pos-manager' ); ?></option>
				</select>
			</div>

			<div class="jwpm-co-filter-item">
				<label for="jwpm-co-filter-branch"><?php esc_html_e( 'برانچ', 'jwpm-jewelry-pos-manager' ); ?></label>
				<select id="jwpm-co-filter-branch">
					<option value="0"><?php esc_html_e( 'تمام برانچز', 'jwpm-jewelry-pos-manager' ); ?></option>
					<!-- (JavaScript) کے ذریعے برانچ لسٹ لوڈ ہو گی -->
				</select>
			</div>

			<div class="jwpm-co-filter-item">
				<label><?php esc_html_e( 'Due Date', 'jwpm-jewelry-pos-manager' ); ?></label>
				<div class="jwpm-co-filter-daterange">
					<input type="date" id="jwpm-co-filter-date-from" />
					<span class="jwpm-co-filter-date-sep">—</span>
					<input type="date" id="jwpm-co-filter-date-to" />
				</div>
			</div>

			<div class="jwpm-co-filter-item jwpm-co-filter-actions">
				<button type="button" class="button jwpm-co-btn-apply-filters">
					<?php esc_html_e( 'فلٹر لگائیں', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button jwpm-co-btn-reset-filters">
					<?php esc_html_e( 'ری سیٹ', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</div>
		</section>

		<!-- List Section -->
		<section class="jwpm-co-list-section">
			<div class="jwpm-co-list-header">
				<h2><?php esc_html_e( 'Custom Orders لسٹ', 'jwpm-jewelry-pos-manager' ); ?></h2>
				<span class="jwpm-co-list-count">
					<?php esc_html_e( 'کل ریکارڈز:', 'jwpm-jewelry-pos-manager' ); ?>
					<strong class="jwpm-co-total-count">0</strong>
				</span>
			</div>

			<div class="jwpm-co-table-wrapper">
				<table class="widefat fixed striped jwpm-co-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order #', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'کسٹمر', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'فون', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Design Ref', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Estimate Weight', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Estimate Amount', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Advance', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Status', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Due Date', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'jwpm-jewelry-pos-manager' ); ?></th>
						</tr>
					</thead>
					<tbody class="jwpm-co-table-body">
						<tr class="jwpm-co-table-empty">
							<td colspan="10">
								<?php esc_html_e( 'ابھی کوئی Custom Order موجود نہیں ہے۔', 'jwpm-jewelry-pos-manager' ); ?>
							</td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<th><?php esc_html_e( 'Order #', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'کسٹمر', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'فون', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Design Ref', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Estimate Weight', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Estimate Amount', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Advance', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Status', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Due Date', 'jwpm-jewelry-pos-manager' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'jwpm-jewelry-pos-manager' ); ?></th>
						</tr>
					</tfoot>
				</table>
			</div>

			<!-- Pagination Placeholder -->
			<div class="jwpm-co-pagination">
				<button type="button" class="button jwpm-co-page-prev" disabled="disabled">&laquo;</button>
				<span class="jwpm-co-page-info">
					<?php esc_html_e( 'صفحہ', 'jwpm-jewelry-pos-manager' ); ?>
					<span class="jwpm-co-current-page">1</span>
					<?php esc_html_e( 'از', 'jwpm-jewelry-pos-manager' ); ?>
					<span class="jwpm-co-total-pages">1</span>
				</span>
				<button type="button" class="button jwpm-co-page-next" disabled="disabled">&raquo;</button>
			</div>
		</section>

		<!-- Form Modal (Add/Edit Custom Order) -->
		<section class="jwpm-co-modal jwpm-co-modal-form" aria-hidden="true" role="dialog" aria-labelledby="jwpm-co-modal-form-title">
			<div class="jwpm-co-modal-backdrop"></div>
			<div class="jwpm-co-modal-dialog" role="document">
				<header class="jwpm-co-modal-header">
					<h2 id="jwpm-co-modal-form-title">
						<?php esc_html_e( 'Custom Order تفصیل', 'jwpm-jewelry-pos-manager' ); ?>
					</h2>
					<button type="button" class="button-link jwpm-co-modal-close" aria-label="<?php esc_attr_e( 'بند کریں', 'jwpm-jewelry-pos-manager' ); ?>">×</button>
				</header>

				<div class="jwpm-co-modal-body">
					<form class="jwpm-co-form" autocomplete="off">
						<input type="hidden" name="id" class="jwpm-co-field-id" />

						<div class="jwpm-co-form-grid">
							<div class="jwpm-co-form-group">
								<label for="jwpm-co-customer-name">
									<?php esc_html_e( 'کسٹمر نام', 'jwpm-jewelry-pos-manager' ); ?>
									<span class="required">*</span>
								</label>
								<input type="text" id="jwpm-co-customer-name" name="customer_name" class="regular-text" required />
							</div>

							<div class="jwpm-co-form-group">
								<label for="jwpm-co-customer-phone">
									<?php esc_html_e( 'فون نمبر', 'jwpm-jewelry-pos-manager' ); ?>
									<span class="required">*</span>
								</label>
								<input type="text" id="jwpm-co-customer-phone" name="customer_phone" class="regular-text" required />
							</div>

							<div class="jwpm-co-form-group">
								<label for="jwpm-co-design-ref">
									<?php esc_html_e( 'Design Reference', 'jwpm-jewelry-pos-manager' ); ?>
								</label>
								<input type="text" id="jwpm-co-design-ref" name="design_reference" class="regular-text" />
							</div>

							<div class="jwpm-co-form-group">
								<label for="jwpm-co-estimate-weight">
									<?php esc_html_e( 'Estimate Weight (g)', 'jwpm-jewelry-pos-manager' ); ?>
								</label>
								<input type="number" step="0.001" min="0" id="jwpm-co-estimate-weight" name="estimate_weight" class="small-text" />
							</div>

							<div class="jwpm-co-form-group">
								<label for="jwpm-co-estimate-amount">
									<?php esc_html_e( 'Estimate Amount', 'jwpm-jewelry-pos-manager' ); ?>
								</label>
								<input type="number" step="0.01" min="0" id="jwpm-co-estimate-amount" name="estimate_amount" class="small-text" />
							</div>

							<div class="jwpm-co-form-group">
								<label for="jwpm-co-advance-amount">
									<?php esc_html_e( 'Advance Amount', 'jwpm-jewelry-pos-manager' ); ?>
								</label>
								<input type="number" step="0.01" min="0" id="jwpm-co-advance-amount" name="advance_amount" class="small-text" />
							</div>

							<div class="jwpm-co-form-group">
								<label for="jwpm-co-status">
									<?php esc_html_e( 'Status', 'jwpm-jewelry-pos-manager' ); ?>
								</label>
								<select id="jwpm-co-status" name="status">
									<option value="designing"><?php esc_html_e( 'Designing', 'jwpm-jewelry-pos-manager' ); ?></option>
									<option value="in_progress"><?php esc_html_e( 'In Progress', 'jwpm-jewelry-pos-manager' ); ?></option>
									<option value="ready"><?php esc_html_e( 'Ready', 'jwpm-jewelry-pos-manager' ); ?></option>
									<option value="delivered"><?php esc_html_e( 'Delivered', 'jwpm-jewelry-pos-manager' ); ?></option>
									<option value="cancelled"><?php esc_html_e( 'Cancelled', 'jwpm-jewelry-pos-manager' ); ?></option>
								</select>
							</div>

							<div class="jwpm-co-form-group">
								<label for="jwpm-co-due-date">
									<?php esc_html_e( 'Due Date', 'jwpm-jewelry-pos-manager' ); ?>
								</label>
								<input type="date" id="jwpm-co-due-date" name="due_date" />
							</div>

							<div class="jwpm-co-form-group jwpm-co-form-group-full">
								<label for="jwpm-co-notes">
									<?php esc_html_e( 'نوٹس / تفصیل', 'jwpm-jewelry-pos-manager' ); ?>
								</label>
								<textarea id="jwpm-co-notes" name="notes" rows="3"></textarea>
							</div>
						</div>

						<div class="jwpm-co-form-footer">
							<button type="submit" class="button button-primary jwpm-co-btn-save" <?php disabled( ! $can_manage ); ?>>
								<?php esc_html_e( 'محفوظ کریں', 'jwpm-jewelry-pos-manager' ); ?>
							</button>
							<button type="button" class="button jwpm-co-btn-cancel">
								<?php esc_html_e( 'منسوخ کریں', 'jwpm-jewelry-pos-manager' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</section>

		<!-- Import Modal -->
		<section class="jwpm-co-modal jwpm-co-modal-import" aria-hidden="true" role="dialog" aria-labelledby="jwpm-co-modal-import-title">
			<div class="jwpm-co-modal-backdrop"></div>
			<div class="jwpm-co-modal-dialog" role="document">
				<header class="jwpm-co-modal-header">
					<h2 id="jwpm-co-modal-import-title">
						<?php esc_html_e( 'Custom Orders Import', 'jwpm-jewelry-pos-manager' ); ?>
					</h2>
					<button type="button" class="button-link jwpm-co-modal-close" aria-label="<?php esc_attr_e( 'بند کریں', 'jwpm-jewelry-pos-manager' ); ?>">×</button>
				</header>

				<div class="jwpm-co-modal-body">
					<p><?php esc_html_e( 'یہاں آپ Excel/CSV فائل سے Custom Orders امپورٹ کر سکتے ہیں۔ (JavaScript) فائل اپ لوڈ کو handle کرے گی۔', 'jwpm-jewelry-pos-manager' ); ?></p>
					<input type="file" class="jwpm-co-import-file" accept=".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
					<p class="description">
						<?php esc_html_e( 'براہ کرم ٹیمپلیٹ کے مطابق ہی فائل بنائیں۔ (Sample Template) آگے جا کر فراہم کیا جائے گا۔', 'jwpm-jewelry-pos-manager' ); ?>
					</p>
					<div class="jwpm-co-import-footer">
						<button type="button" class="button button-primary jwpm-co-btn-import-confirm" <?php disabled( ! $can_manage ); ?>>
							<?php esc_html_e( 'Import شروع کریں', 'jwpm-jewelry-pos-manager' ); ?>
						</button>
						<button type="button" class="button jwpm-co-btn-import-cancel">
							<?php esc_html_e( 'منسوخ کریں', 'jwpm-jewelry-pos-manager' ); ?>
						</button>
					</div>
				</div>
			</div>
		</section>

		<!-- Loading / Toast Messages (JavaScript کے ذریعے show/hide) -->
		<div class="jwpm-co-loading-indicator" aria-hidden="true">
			<span class="spinner is-active"></span>
			<span class="jwpm-co-loading-text">
				<?php esc_html_e( 'لوڈ ہو رہا ہے، براہ کرم انتظار کریں...', 'jwpm-jewelry-pos-manager' ); ?>
			</span>
		</div>

		<div class="jwpm-co-toast jwpm-co-toast-success" aria-hidden="true"></div>
		<div class="jwpm-co-toast jwpm-co-toast-error" aria-hidden="true"></div>

	</div>
	<!-- 🔴 یہاں پر Root Container ختم ہو رہا ہے -->

	<!-- Row Template (JavaScript کے لیے) -->
	<template id="jwpm-co-row-template">
		<tr class="jwpm-co-row" data-id="{{id}}">
			<td class="column-order-code">{{order_code}}</td>
			<td class="column-customer-name">{{customer_name}}</td>
			<td class="column-customer-phone">{{customer_phone}}</td>
			<td class="column-design-ref">{{design_reference}}</td>
			<td class="column-estimate-weight">{{estimate_weight}}</td>
			<td class="column-estimate-amount">{{estimate_amount}}</td>
			<td class="column-advance-amount">{{advance_amount}}</td>
			<td class="column-status">
				<span class="jwpm-co-status-badge jwpm-co-status-{{status}}">{{status_label}}</span>
			</td>
			<td class="column-due-date">{{due_date}}</td>
			<td class="column-actions">
				<button type="button" class="button button-small jwpm-co-action-edit">
					<?php esc_html_e( 'ایڈٹ', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button button-small jwpm-co-action-delete">
					<?php esc_html_e( 'حذف', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</td>
		</tr>
	</template>

</div>

<!-- JavaScript کے fallback کے لیے config object (اگر wp_localize_script نہ چلے تو) -->
<script type="text/javascript">
window.JWPM_CUSTOM_ORDERS_CONFIG = window.JWPM_CUSTOM_ORDERS_CONFIG || {
	ajax_url: "<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>",
	nonce_main: "<?php echo esc_js( $nonce_main ); ?>",
	nonce_import: "<?php echo esc_js( $nonce_import ); ?>",
	nonce_export: "<?php echo esc_js( $nonce_export ); ?>",
	capabilities: {
		can_manage: <?php echo $can_manage ? 'true' : 'false'; ?>,
	}
};
</script>

<?php
// 🔴 یہاں پر jwpm-custom-orders.php Admin Page ختم ہو رہا ہے
// ✅ Syntax verified block end

