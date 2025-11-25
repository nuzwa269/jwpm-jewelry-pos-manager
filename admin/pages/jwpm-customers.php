/** Part 30 — Customers Page Root + Templates */
// 🟢 یہاں سے [Customers Page Templates] شروع ہو رہا ہے

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'jwpm_render_customers_page' ) ) {

	/**
	 * JWPM Customers Page Render
	 * یہاں پر صرف Root DIV اور HTML <template> بلاکس ہیں، اصل UI (JavaScript) سے رینڈر ہو گا۔
	 */
	function jwpm_render_customers_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'آپ کو اس صفحے تک رسائی کی اجازت نہیں۔', 'jwpm' ) );
		}

		$main_nonce   = wp_create_nonce( 'jwpm_customers_main_nonce' );
		$import_nonce = wp_create_nonce( 'jwpm_customers_import_nonce' );
		$export_nonce = wp_create_nonce( 'jwpm_customers_export_nonce' );
		$demo_nonce   = wp_create_nonce( 'jwpm_customers_demo_nonce' );
		?>
		<div class="jwpm-page jwpm-page-customers-wrap">
			<noscript>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'براہ کرم (JavaScript) آن کریں، اس صفحے کیلئے ضروری ہے۔', 'jwpm' ); ?></p>
				</div>
			</noscript>

			<div
				id="jwpm-customers-root"
				data-jwpm-customers-main-nonce="<?php echo esc_attr( $main_nonce ); ?>"
				data-jwpm-customers-import-nonce="<?php echo esc_attr( $import_nonce ); ?>"
				data-jwpm-customers-export-nonce="<?php echo esc_attr( $export_nonce ); ?>"
				data-jwpm-customers-demo-nonce="<?php echo esc_attr( $demo_nonce ); ?>"
				data-jwpm-customers-page-title="<?php echo esc_attr__( 'JWPM Customers', 'jwpm' ); ?>"
			>
				<div class="jwpm-loading">
					<?php esc_html_e( 'کسٹمرز لوڈ ہو رہے ہیں…', 'jwpm' ); ?>
				</div>
			</div>

			<?php
			/**
			 * Main Layout Template
			 * Header + Filters + Actions + Table + Side Panel Container
			 */
			?>
			<template id="jwpm-customers-layout-template">
				<div class="jwpm-page jwpm-page-customers">
					<header class="jwpm-page-header">
						<div class="jwpm-page-title-group">
							<h1 class="jwpm-page-title"><?php esc_html_e( 'Customers', 'jwpm' ); ?></h1>
							<p class="jwpm-page-subtitle">
								<?php esc_html_e( 'تمام کسٹمر پروفائل، کریڈٹ لِمٹ اور ہسٹری کو منظم کریں۔', 'jwpm' ); ?>
							</p>
						</div>
						<div class="jwpm-page-header-stats">
							<div class="jwpm-stat-card" data-jwpm-customers-stat="total">
								<div class="jwpm-stat-label"><?php esc_html_e( 'کل کسٹمرز', 'jwpm' ); ?></div>
								<div class="jwpm-stat-value">0</div>
							</div>
							<div class="jwpm-stat-card" data-jwpm-customers-stat="active">
								<div class="jwpm-stat-label"><?php esc_html_e( 'Active کسٹمرز', 'jwpm' ); ?></div>
								<div class="jwpm-stat-value">0</div>
							</div>
						</div>
					</header>

					<section class="jwpm-toolbar jwpm-customers-toolbar">
						<div class="jwpm-toolbar-filters">
							<input
								type="search"
								class="jwpm-input"
								data-jwpm-customers-filter="search"
								placeholder="<?php echo esc_attr__( 'نام یا موبائل سے تلاش کریں…', 'jwpm' ); ?>"
							/>
							<select class="jwpm-select" data-jwpm-customers-filter="city">
								<option value=""><?php esc_html_e( 'شہر (سب)', 'jwpm' ); ?></option>
							</select>
							<select class="jwpm-select" data-jwpm-customers-filter="type">
								<option value=""><?php esc_html_e( 'کسٹمر ٹائپ (سب)', 'jwpm' ); ?></option>
								<option value="walkin"><?php esc_html_e( 'Walk-in', 'jwpm' ); ?></option>
								<option value="regular"><?php esc_html_e( 'Regular', 'jwpm' ); ?></option>
								<option value="wholesale"><?php esc_html_e( 'Wholesale', 'jwpm' ); ?></option>
								<option value="vip"><?php esc_html_e( 'VIP', 'jwpm' ); ?></option>
							</select>
							<select class="jwpm-select" data-jwpm-customers-filter="status">
								<option value=""><?php esc_html_e( 'Status (All)', 'jwpm' ); ?></option>
								<option value="active"><?php esc_html_e( 'Active', 'jwpm' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'jwpm' ); ?></option>
							</select>
						</div>
						<div class="jwpm-toolbar-actions">
							<button type="button" class="button button-primary" data-jwpm-customers-action="add">
								<?php esc_html_e( '➕ نیا کسٹمر', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-customers-action="import">
								<?php esc_html_e( '⬇ Import CSV', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-customers-action="export">
								<?php esc_html_e( '⬆ Export Excel', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-customers-action="print">
								<?php esc_html_e( '🖨 Print List', 'jwpm' ); ?>
							</button>
							<div class="jwpm-dropdown jwpm-customers-demo-menu">
								<button type="button" class="button" data-jwpm-customers-action="demo-toggle">
									<?php esc_html_e( '🧪 Demo Data', 'jwpm' ); ?>
								</button>
								<div class="jwpm-dropdown-menu">
									<button type="button" class="jwpm-dropdown-item" data-jwpm-customers-action="demo-create">
										<?php esc_html_e( 'Demo Customers بنائیں', 'jwpm' ); ?>
									</button>
									<button type="button" class="jwpm-dropdown-item" data-jwpm-customers-action="demo-clear">
										<?php esc_html_e( 'Demo Customers حذف کریں', 'jwpm' ); ?>
									</button>
								</div>
							</div>
						</div>
					</section>

					<section class="jwpm-customers-main">
						<div class="jwpm-customers-table-wrap">
							<table class="jwpm-table jwpm-table-customers">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Code', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Name', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Phone', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'City', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Type', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Credit Limit', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Current Balance', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Last Purchase', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Status', 'jwpm' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'jwpm' ); ?></th>
									</tr>
								</thead>
								<tbody data-jwpm-customers-table-body>
									<tr class="jwpm-empty-row">
										<td colspan="10">
											<?php esc_html_e( 'کوئی ریکارڈ نہیں ملا۔ اوپر سے فلٹر تبدیل کریں یا نیا کسٹمر شامل کریں۔', 'jwpm' ); ?>
										</td>
									</tr>
								</tbody>
							</table>

							<div class="jwpm-pagination" data-jwpm-customers-pagination>
								<!-- (JavaScript) یہاں pagination رینڈر کرے گا -->
							</div>
						</div>

						<aside class="jwpm-customers-side-panel" data-jwpm-customers-side-panel hidden>
							<!-- (JavaScript) یہاں Add/Edit فارم رینڈر کرے گا -->
						</aside>
					</section>
				</div>
			</template>

			<?php
			/**
			 * Customer Table Row Template
			 */
			?>
			<template id="jwpm-customers-row-template">
				<tr data-jwpm-customer-row>
					<td data-jwpm-customer-field="customer_code"></td>
					<td data-jwpm-customer-field="name"></td>
					<td data-jwpm-customer-field="phone"></td>
					<td data-jwpm-customer-field="city"></td>
					<td data-jwpm-customer-field="customer_type"></td>
					<td data-jwpm-customer-field="credit_limit"></td>
					<td data-jwpm-customer-field="current_balance"></td>
					<td data-jwpm-customer-field="last_purchase"></td>
					<td data-jwpm-customer-field="status_badge"></td>
					<td class="jwpm-table-actions">
						<button type="button" class="button-link" data-jwpm-customers-action="view">
							<?php esc_html_e( 'View/Edit', 'jwpm' ); ?>
						</button>
						<button type="button" class="button-link" data-jwpm-customers-action="quick-sale">
							<?php esc_html_e( 'Quick Sale', 'jwpm' ); ?>
						</button>
						<button type="button" class="button-link jwpm-text-danger" data-jwpm-customers-action="delete">
							<?php esc_html_e( 'Delete', 'jwpm' ); ?>
						</button>
					</td>
				</tr>
			</template>

			<?php
			/**
			 * Customer Form Panel Template (Add/Edit)
			 */
			?>
			<template id="jwpm-customers-form-template">
				<div class="jwpm-side-panel-inner">
					<header class="jwpm-side-panel-header">
						<h2 class="jwpm-side-panel-title" data-jwpm-customers-form-title>
							<?php esc_html_e( 'Add New Customer', 'jwpm' ); ?>
						</h2>
						<button type="button" class="jwpm-side-panel-close" data-jwpm-customers-action="close-panel" aria-label="<?php echo esc_attr__( 'بند کریں', 'jwpm' ); ?>">×</button>
					</header>

					<div class="jwpm-side-panel-body">
						<form data-jwpm-customers-form novalidate>
							<input type="hidden" name="id" value="" data-jwpm-customer-input="id" />

							<section class="jwpm-form-section">
								<h3 class="jwpm-form-section-title"><?php esc_html_e( 'Basic Info', 'jwpm' ); ?></h3>
								<div class="jwpm-form-grid">
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Name', 'jwpm' ); ?> *</span>
										<input type="text" class="jwpm-input" name="name" required data-jwpm-customer-input="name" />
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Phone', 'jwpm' ); ?> *</span>
										<input type="text" class="jwpm-input" name="phone" required data-jwpm-customer-input="phone" />
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'WhatsApp', 'jwpm' ); ?></span>
										<input type="text" class="jwpm-input" name="whatsapp" data-jwpm-customer-input="whatsapp" />
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Email', 'jwpm' ); ?></span>
										<input type="email" class="jwpm-input" name="email" data-jwpm-customer-input="email" />
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'City', 'jwpm' ); ?></span>
										<input type="text" class="jwpm-input" name="city" data-jwpm-customer-input="city" />
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Area', 'jwpm' ); ?></span>
										<input type="text" class="jwpm-input" name="area" data-jwpm-customer-input="area" />
									</label>
									<label class="jwpm-field jwpm-field-full">
										<span class="jwpm-field-label"><?php esc_html_e( 'Address', 'jwpm' ); ?></span>
										<textarea class="jwpm-textarea" name="address" rows="2" data-jwpm-customer-input="address"></textarea>
									</label>
								</div>
							</section>

							<section class="jwpm-form-section">
								<h3 class="jwpm-form-section-title"><?php esc_html_e( 'Profile', 'jwpm' ); ?></h3>
								<div class="jwpm-form-grid">
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'CNIC', 'jwpm' ); ?></span>
										<input type="text" class="jwpm-input" name="cnic" data-jwpm-customer-input="cnic" />
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Date of Birth', 'jwpm' ); ?></span>
										<input type="date" class="jwpm-input" name="dob" data-jwpm-customer-input="dob" />
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Gender', 'jwpm' ); ?></span>
										<select class="jwpm-select" name="gender" data-jwpm-customer-input="gender">
											<option value=""><?php esc_html_e( 'Select', 'jwpm' ); ?></option>
											<option value="male"><?php esc_html_e( 'Male', 'jwpm' ); ?></option>
											<option value="female"><?php esc_html_e( 'Female', 'jwpm' ); ?></option>
											<option value="other"><?php esc_html_e( 'Other', 'jwpm' ); ?></option>
										</select>
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Customer Type', 'jwpm' ); ?></span>
										<select class="jwpm-select" name="customer_type" data-jwpm-customer-input="customer_type">
											<option value="walkin"><?php esc_html_e( 'Walk-in', 'jwpm' ); ?></option>
											<option value="regular"><?php esc_html_e( 'Regular', 'jwpm' ); ?></option>
											<option value="wholesale"><?php esc_html_e( 'Wholesale', 'jwpm' ); ?></option>
											<option value="vip"><?php esc_html_e( 'VIP', 'jwpm' ); ?></option>
										</select>
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Status', 'jwpm' ); ?></span>
										<select class="jwpm-select" name="status" data-jwpm-customer-input="status">
											<option value="active"><?php esc_html_e( 'Active', 'jwpm' ); ?></option>
											<option value="inactive"><?php esc_html_e( 'Inactive', 'jwpm' ); ?></option>
										</select>
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Price Group', 'jwpm' ); ?></span>
										<input type="text" class="jwpm-input" name="price_group" data-jwpm-customer-input="price_group" />
									</label>
									<label class="jwpm-field jwpm-field-full">
										<span class="jwpm-field-label"><?php esc_html_e( 'Tags (comma separated)', 'jwpm' ); ?></span>
										<input type="text" class="jwpm-input" name="tags" data-jwpm-customer-input="tags" />
									</label>
								</div>
							</section>

							<section class="jwpm-form-section">
								<h3 class="jwpm-form-section-title"><?php esc_html_e( 'Financial', 'jwpm' ); ?></h3>
								<div class="jwpm-form-grid">
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Credit Limit', 'jwpm' ); ?></span>
										<input type="number" step="0.001" class="jwpm-input" name="credit_limit" data-jwpm-customer-input="credit_limit" />
									</label>
									<label class="jwpm-field">
										<span class="jwpm-field-label"><?php esc_html_e( 'Opening Balance', 'jwpm' ); ?></span>
										<input type="number" step="0.001" class="jwpm-input" name="opening_balance" data-jwpm-customer-input="opening_balance" />
										<small class="jwpm-field-help">
											<?php esc_html_e( 'صرف نئے کسٹمر کیلئے، Edit موڈ میں (JavaScript) اسے read-only کرے گا۔', 'jwpm' ); ?>
										</small>
									</label>
									<label class="jwpm-field jwpm-field-full">
										<span class="jwpm-field-label"><?php esc_html_e( 'Notes', 'jwpm' ); ?></span>
										<textarea class="jwpm-textarea" name="notes" rows="3" data-jwpm-customer-input="notes"></textarea>
									</label>
								</div>
							</section>
						</form>
					</div>

					<footer class="jwpm-side-panel-footer">
						<button type="button" class="button button-primary" data-jwpm-customers-action="save">
							<?php esc_html_e( 'Save Customer', 'jwpm' ); ?>
						</button>
						<button type="button" class="button" data-jwpm-customers-action="cancel">
							<?php esc_html_e( 'Cancel', 'jwpm' ); ?>
						</button>
					</footer>
				</div>
			</template>

			<?php
			/**
			 * Import Modal Template
			 */
			?>
			<template id="jwpm-customers-import-template">
				<div class="jwpm-modal jwpm-modal-import-customers" role="dialog" aria-modal="true">
					<div class="jwpm-modal-overlay" data-jwpm-customers-action="close-import"></div>
					<div class="jwpm-modal-content">
						<header class="jwpm-modal-header">
							<h2 class="jwpm-modal-title"><?php esc_html_e( 'Import Customers (CSV)', 'jwpm' ); ?></h2>
							<button type="button" class="jwpm-modal-close" data-jwpm-customers-action="close-import">×</button>
						</header>
						<div class="jwpm-modal-body">
							<p><?php esc_html_e( 'براہ کرم (CSV) فائل اپلوڈ کریں، کم از کم Name اور Phone کالم ضروری ہیں۔', 'jwpm' ); ?></p>
							<form data-jwpm-customers-import-form>
								<input type="file" name="file" accept=".csv,text/csv" required />
								<label class="jwpm-field-inline">
									<input type="checkbox" name="skip_duplicates" value="1" checked />
									<span><?php esc_html_e( 'Phone نمبر کے مطابق ڈپلیکیٹ ریکارڈز کو چھوڑ دیں۔', 'jwpm' ); ?></span>
								</label>
							</form>
							<div class="jwpm-import-result" data-jwpm-customers-import-result></div>
						</div>
						<footer class="jwpm-modal-footer">
							<button type="button" class="button button-primary" data-jwpm-customers-action="do-import">
								<?php esc_html_e( 'Upload & Import', 'jwpm' ); ?>
							</button>
							<button type="button" class="button" data-jwpm-customers-action="close-import">
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

// 🔴 یہاں پر [Customers Page Templates] ختم ہو رہا ہے
// ✅ Syntax verified block end

