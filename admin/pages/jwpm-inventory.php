<?php
/**
 * Inventory / Stock Admin Page
 *
 * یہ فائل (JWPM) انوینٹری پیج کی (HTML) اسٹرکچر اور ٹیمپلیٹس مہیا کرتی ہے۔
 * اصل (UI) رینڈرنگ اور انٹرایکشن بعد میں (JavaScript) کے ذریعے ہو گی۔
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// صرف وہی یوزر اس پیج تک پہنچ سکے جس کے پاس انوینٹری کی (capability) ہو۔
if ( ! current_user_can( 'manage_jwpm_inventory' ) ) {
	wp_die(
		esc_html__(
			'You do not have permission to access the Inventory page.',
			'jwpm-jewelry-pos-manager'
		)
	);
}

/** Part 1 — Inventory page root & UI templates (Bright UI) */
?>

<div class="wrap jwpm-inventory-wrap">
	<h1 class="jwpm-page-title">
		<?php esc_html_e( 'Inventory / Stock', 'jwpm-jewelry-pos-manager' ); ?>
	</h1>

	<?php
	// 🟢 یہاں سے [Inventory Root] شروع ہو رہا ہے
	?>
	<div
		id="jwpm-inventory-root"
		class="jwpm-inventory-root"
		data-jwpm-page="inventory"
	>
		<div class="jwpm-loading-state">
			<span class="jwpm-spinner"></span>
			<span class="jwpm-loading-text">
				<?php esc_html_e( 'Loading Inventory...', 'jwpm-jewelry-pos-manager' ); ?>
			</span>
		</div>
	</div>
	<?php
	// 🔴 یہاں پر [Inventory Root] ختم ہو رہا ہے
	?>
</div>

<?php
// 🟢 یہاں سے [Inventory Templates] شروع ہو رہا ہے
?>

<!-- Summary cards row: Total Items, Total Weight, Low Stock, Dead Stock -->
<template id="jwpm-inventory-summary-template">
	<div class="jwpm-inv-summary-row">
		<div class="jwpm-inv-summary-card jwpm-card-total-items" data-metric="total_items">
			<div class="jwpm-card-label"><?php esc_html_e( 'Total Items', 'jwpm-jewelry-pos-manager' ); ?></div>
			<div class="jwpm-card-value js-jwpm-summary-value">0</div>
		</div>
		<div class="jwpm-inv-summary-card jwpm-card-total-weight" data-metric="total_weight">
			<div class="jwpm-card-label"><?php esc_html_e( 'Total Weight', 'jwpm-jewelry-pos-manager' ); ?></div>
			<div class="jwpm-card-value js-jwpm-summary-value">0</div>
		</div>
		<div class="jwpm-inv-summary-card jwpm-card-low-stock" data-metric="low_stock">
			<div class="jwpm-card-label"><?php esc_html_e( 'Low Stock', 'jwpm-jewelry-pos-manager' ); ?></div>
			<div class="jwpm-card-value js-jwpm-summary-value">0</div>
		</div>
		<div class="jwpm-inv-summary-card jwpm-card-dead-stock" data-metric="dead_stock">
			<div class="jwpm-card-label"><?php esc_html_e( 'Dead Stock', 'jwpm-jewelry-pos-manager' ); ?></div>
			<div class="jwpm-card-value js-jwpm-summary-value">0</div>
		</div>
	</div>
</template>

<!-- Filters bar: Category, Metal, Karat, Status, Weight range, Branch, Apply/Reset -->
<template id="jwpm-inventory-filters-template">
	<div class="jwpm-inv-filters-bar">
		<div class="jwpm-inv-filters-row">
			<div class="jwpm-filter-field">
				<label for="jwpm-inv-filter-search">
					<?php esc_html_e( 'Search', 'jwpm-jewelry-pos-manager' ); ?>
				</label>
				<input
					type="text"
					id="jwpm-inv-filter-search"
					class="jwpm-input js-jwpm-filter-input"
					data-filter-key="search"
					placeholder="<?php esc_attr_e( 'SKU, Tag ID, Design, Stones…', 'jwpm-jewelry-pos-manager' ); ?>"
				/>
			</div>

			<div class="jwpm-filter-field">
				<label for="jwpm-inv-filter-category">
					<?php esc_html_e( 'Category', 'jwpm-jewelry-pos-manager' ); ?>
				</label>
				<select
					id="jwpm-inv-filter-category"
					class="jwpm-select js-jwpm-filter-input"
					data-filter-key="category"
				>
					<option value=""><?php esc_html_e( 'All', 'jwpm-jewelry-pos-manager' ); ?></option>
				</select>
			</div>

			<div class="jwpm-filter-field">
				<label for="jwpm-inv-filter-metal">
					<?php esc_html_e( 'Metal', 'jwpm-jewelry-pos-manager' ); ?>
				</label>
				<select
					id="jwpm-inv-filter-metal"
					class="jwpm-select js-jwpm-filter-input"
					data-filter-key="metal"
				>
					<option value=""><?php esc_html_e( 'All', 'jwpm-jewelry-pos-manager' ); ?></option>
				</select>
			</div>

			<div class="jwpm-filter-field">
				<label for="jwpm-inv-filter-karat">
					<?php esc_html_e( 'Karat', 'jwpm-jewelry-pos-manager' ); ?>
				</label>
				<select
					id="jwpm-inv-filter-karat"
					class="jwpm-select js-jwpm-filter-input"
					data-filter-key="karat"
				>
					<option value=""><?php esc_html_e( 'All', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="18K">18K</option>
					<option value="21K">21K</option>
					<option value="22K">22K</option>
					<option value="24K">24K</option>
				</select>
			</div>

			<div class="jwpm-filter-field">
				<label for="jwpm-inv-filter-status">
					<?php esc_html_e( 'Status', 'jwpm-jewelry-pos-manager' ); ?>
				</label>
				<select
					id="jwpm-inv-filter-status"
					class="jwpm-select js-jwpm-filter-input"
					data-filter-key="status"
				>
					<option value=""><?php esc_html_e( 'All', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="in_stock"><?php esc_html_e( 'In Stock', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="low_stock"><?php esc_html_e( 'Low Stock', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="dead_stock"><?php esc_html_e( 'Dead Stock', 'jwpm-jewelry-pos-manager' ); ?></option>
					<option value="scrap"><?php esc_html_e( 'Scrap / Old Gold', 'jwpm-jewelry-pos-manager' ); ?></option>
				</select>
			</div>

			<div class="jwpm-filter-field">
				<label for="jwpm-inv-filter-branch">
					<?php esc_html_e( 'Branch', 'jwpm-jewelry-pos-manager' ); ?>
				</label>
				<select
					id="jwpm-inv-filter-branch"
					class="jwpm-select js-jwpm-filter-input"
					data-filter-key="branch_id"
				>
					<option value=""><?php esc_html_e( 'All Branches', 'jwpm-jewelry-pos-manager' ); ?></option>
				</select>
			</div>

			<div class="jwpm-filter-actions">
				<button
					type="button"
					class="button jwpm-btn-secondary js-jwpm-filter-reset"
				>
					<?php esc_html_e( 'Reset', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button
					type="button"
					class="button button-primary jwpm-btn-primary js-jwpm-filter-apply"
				>
					<?php esc_html_e( 'Apply Filters', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</div>
		</div>

		<div class="jwpm-inv-weight-row">
			<div class="jwpm-weight-group">
				<div class="jwpm-weight-label">
					<?php esc_html_e( 'Total Weight Range', 'jwpm-jewelry-pos-manager' ); ?>
				</div>
				<div class="jwpm-weight-inputs">
					<input
						type="number"
						class="jwpm-input js-jwpm-filter-input"
						data-filter-key="weight_min"
						placeholder="<?php esc_attr_e( 'Min', 'jwpm-jewelry-pos-manager' ); ?>"
						step="0.001"
					/>
					<span class="jwpm-weight-separator">–</span>
					<input
						type="number"
						class="jwpm-input js-jwpm-filter-input"
						data-filter-key="weight_max"
						placeholder="<?php esc_attr_e( 'Max', 'jwpm-jewelry-pos-manager' ); ?>"
						step="0.001"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<!-- Main panel: tabs area + table card + actions -->
<template id="jwpm-inventory-main-template">
	<div class="jwpm-inv-main-card">
		<div class="jwpm-inv-header-row">
			<div class="jwpm-inv-tabs js-jwpm-tabs">
				<button type="button" data-tab="items" class="jwpm-tab-btn is-active">
					<?php esc_html_e( 'Items List', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" data-tab="stock" class="jwpm-tab-btn">
					<?php esc_html_e( 'Stock Movements', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" data-tab="scrap" class="jwpm-tab-btn">
					<?php esc_html_e( 'Scrap / Old Gold', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" data-tab="branch" class="jwpm-tab-btn">
					<?php esc_html_e( 'Branch Stock / Transfer', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</div>

			<div class="jwpm-inv-actions">
				<button type="button" class="button jwpm-btn-new js-jwpm-open-item-modal">
					<?php esc_html_e( 'New Item', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button jwpm-btn-import js-jwpm-open-import-modal">
					<?php esc_html_e( 'Import', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button jwpm-btn-print js-jwpm-print-table">
					<?php esc_html_e( 'Print', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button jwpm-btn-demo js-jwpm-open-demo-modal">
					<?php esc_html_e( 'Demo Data', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</div>
		</div>

		<div class="jwpm-inv-body js-jwpm-tab-body" data-tab="items">
			<table class="widefat fixed striped jwpm-inv-table js-jwpm-items-table">
				<thead>
					<tr>
						<th class="check-column">
							<input type="checkbox" class="js-jwpm-select-all" />
						</th>
						<th><?php esc_html_e( 'Photo', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Tag ID', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Category', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Karat', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Gross Wt (g)', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Net Wt (g)', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Stones', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Branch', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Status', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'jwpm-jewelry-pos-manager' ); ?></th>
					</tr>
				</thead>
				<tbody class="js-jwpm-items-tbody">
					<tr class="jwpm-table-empty">
						<td colspan="11">
							<?php esc_html_e( 'No items found. Try adjusting filters or create a new item.', 'jwpm-jewelry-pos-manager' ); ?>
						</td>
					</tr>
				</tbody>
				<tfoot>
					<tr>
						<th class="check-column"></th>
						<th><?php esc_html_e( 'Photo', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Tag ID', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Category', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Karat', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Gross Wt (g)', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Net Wt (g)', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Stones', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Branch', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Status', 'jwpm-jewelry-pos-manager' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'jwpm-jewelry-pos-manager' ); ?></th>
					</tr>
				</tfoot>
			</table>

			<div class="jwpm-inv-pagination js-jwpm-pagination">
				<button type="button" class="button js-jwpm-page-prev" disabled>
					<?php esc_html_e( 'Previous', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<span class="jwpm-page-info js-jwpm-page-info">
					<?php esc_html_e( 'Page 1 of 1', 'jwpm-jewelry-pos-manager' ); ?>
				</span>
				<button type="button" class="button js-jwpm-page-next" disabled>
					<?php esc_html_e( 'Next', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</div>
		</div>

		<!-- دوسرے ٹیبز (Stock Movements, Scrap, Branch) بعد میں JS کے ذریعے بھرے جائیں گے -->
		<div class="jwpm-inv-body js-jwpm-tab-body" data-tab="stock" hidden></div>
		<div class="jwpm-inv-body js-jwpm-tab-body" data-tab="scrap" hidden></div>
		<div class="jwpm-inv-body js-jwpm-tab-body" data-tab="branch" hidden></div>
	</div>

	<!-- Side detail panel placeholder -->
	<aside class="jwpm-inv-detail-panel js-jwpm-detail-panel" hidden>
		<button type="button" class="jwpm-detail-close js-jwpm-detail-close" aria-label="<?php esc_attr_e( 'Close details', 'jwpm-jewelry-pos-manager' ); ?>">
			&times;
		</button>
		<div class="jwpm-detail-content js-jwpm-detail-content">
			<!-- JS کے ذریعے آئٹم کی پوری ڈیٹیل یہاں رینڈر ہو گی -->
		</div>
	</aside>
</template>

<!-- Single row template for items table -->
<template id="jwpm-inventory-row-template">
	<tr data-item-id="">
		<th class="check-column">
			<input type="checkbox" class="js-jwpm-row-select" />
		</th>
		<td class="jwpm-inv-photo-cell">
			<div class="jwpm-photo-placeholder js-jwpm-photo"></div>
		</td>
		<td class="js-jwpm-tag"></td>
		<td class="js-jwpm-category"></td>
		<td class="js-jwpm-karat"></td>
		<td class="js-jwpm-gross"></td>
		<td class="js-jwpm-net"></td>
		<td class="js-jwpm-stones"></td>
		<td class="js-jwpm-branch"></td>
		<td class="js-jwpm-status">
			<span class="jwpm-status-badge js-jwpm-status-badge"></span>
		</td>
		<td class="jwpm-inv-actions-cell">
			<button type="button" class="button-link js-jwpm-view-item">
				<?php esc_html_e( 'View', 'jwpm-jewelry-pos-manager' ); ?>
			</button>
			<button type="button" class="button-link js-jwpm-edit-item">
				<?php esc_html_e( 'Edit', 'jwpm-jewelry-pos-manager' ); ?>
			</button>
			<button type="button" class="button-link js-jwpm-adjust-stock">
				<?php esc_html_e( 'Adjust', 'jwpm-jewelry-pos-manager' ); ?>
			</button>
			<button type="button" class="button-link js-jwpm-delete-item">
				<?php esc_html_e( 'Delete', 'jwpm-jewelry-pos-manager' ); ?>
			</button>
		</td>
	</tr>
</template>

<!-- Item create/edit modal -->
<template id="jwpm-inventory-item-modal-template">
	<div class="jwpm-modal jwpm-modal-item" role="dialog" aria-modal="true">
		<div class="jwpm-modal-backdrop js-jwpm-modal-close"></div>
		<div class="jwpm-modal-dialog">
			<div class="jwpm-modal-header">
				<h2 class="jwpm-modal-title js-jwpm-modal-title">
					<?php esc_html_e( 'New Inventory Item', 'jwpm-jewelry-pos-manager' ); ?>
				</h2>
				<button type="button" class="jwpm-modal-close js-jwpm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'jwpm-jewelry-pos-manager' ); ?>">
					&times;
				</button>
			</div>
			<div class="jwpm-modal-body">
				<form class="jwpm-inv-item-form js-jwpm-item-form">
					<input type="hidden" name="id" value="0" class="js-jwpm-item-id" />

					<div class="jwpm-form-grid">
						<div class="jwpm-form-field">
							<label for="jwpm-item-sku">
								<?php esc_html_e( 'SKU / Code', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="text" id="jwpm-item-sku" name="sku" class="jwpm-input" required />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-tag">
								<?php esc_html_e( 'Tag ID / Serial', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="text" id="jwpm-item-tag" name="tag_serial" class="jwpm-input" required />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-category">
								<?php esc_html_e( 'Category', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="text" id="jwpm-item-category" name="category" class="jwpm-input" />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-metal">
								<?php esc_html_e( 'Metal Type', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="text" id="jwpm-item-metal" name="metal_type" class="jwpm-input" />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-karat">
								<?php esc_html_e( 'Karat', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<select id="jwpm-item-karat" name="karat" class="jwpm-select">
								<option value=""><?php esc_html_e( 'Select', 'jwpm-jewelry-pos-manager' ); ?></option>
								<option value="18K">18K</option>
								<option value="21K">21K</option>
								<option value="22K">22K</option>
								<option value="24K">24K</option>
							</select>
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-gross">
								<?php esc_html_e( 'Gross Weight (g)', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="number" id="jwpm-item-gross" name="gross_weight" class="jwpm-input" step="0.001" min="0" />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-net">
								<?php esc_html_e( 'Net Weight (g)', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="number" id="jwpm-item-net" name="net_weight" class="jwpm-input" step="0.001" min="0" />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-stone-type">
								<?php esc_html_e( 'Stone Type', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="text" id="jwpm-item-stone-type" name="stone_type" class="jwpm-input" placeholder="<?php esc_attr_e( 'Diamond, Ruby…', 'jwpm-jewelry-pos-manager' ); ?>" />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-stone-carat">
								<?php esc_html_e( 'Stone Carat', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="number" id="jwpm-item-stone-carat" name="stone_carat" class="jwpm-input" step="0.001" min="0" />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-stone-qty">
								<?php esc_html_e( 'Stone Qty', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="number" id="jwpm-item-stone-qty" name="stone_qty" class="jwpm-input" step="1" min="0" />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-labour">
								<?php esc_html_e( 'Labour / Making Charges', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="number" id="jwpm-item-labour" name="labour_amount" class="jwpm-input" step="0.01" min="0" />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-design">
								<?php esc_html_e( 'Design Number', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<input type="text" id="jwpm-item-design" name="design_no" class="jwpm-input" />
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-status">
								<?php esc_html_e( 'Status', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<select id="jwpm-item-status" name="status" class="jwpm-select">
								<option value="in_stock"><?php esc_html_e( 'In Stock', 'jwpm-jewelry-pos-manager' ); ?></option>
								<option value="low_stock"><?php esc_html_e( 'Low Stock', 'jwpm-jewelry-pos-manager' ); ?></option>
								<option value="dead_stock"><?php esc_html_e( 'Dead Stock', 'jwpm-jewelry-pos-manager' ); ?></option>
								<option value="scrap"><?php esc_html_e( 'Scrap / Old Gold', 'jwpm-jewelry-pos-manager' ); ?></option>
							</select>
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-branch">
								<?php esc_html_e( 'Branch', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<select id="jwpm-item-branch" name="branch_id" class="jwpm-select js-jwpm-branch-select">
								<option value="0"><?php esc_html_e( 'Default', 'jwpm-jewelry-pos-manager' ); ?></option>
							</select>
						</div>

						<div class="jwpm-form-field">
							<label for="jwpm-item-notes">
								<?php esc_html_e( 'Notes', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
							<textarea id="jwpm-item-notes" name="notes" class="jwpm-textarea" rows="3"></textarea>
						</div>

						<div class="jwpm-form-field jwpm-form-field-inline">
							<label>
								<input type="checkbox" name="is_demo" value="1" />
								<?php esc_html_e( 'Mark as demo data', 'jwpm-jewelry-pos-manager' ); ?>
							</label>
						</div>
					</div>

					<div class="jwpm-modal-footer">
						<button type="button" class="button js-jwpm-modal-close">
							<?php esc_html_e( 'Cancel', 'jwpm-jewelry-pos-manager' ); ?>
						</button>
						<button type="submit" class="button button-primary js-jwpm-item-save">
							<?php esc_html_e( 'Save Item', 'jwpm-jewelry-pos-manager' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>

<!-- Import modal -->
<template id="jwpm-inventory-import-modal-template">
	<div class="jwpm-modal jwpm-modal-import" role="dialog" aria-modal="true">
		<div class="jwpm-modal-backdrop js-jwpm-modal-close"></div>
		<div class="jwpm-modal-dialog">
			<div class="jwpm-modal-header">
				<h2 class="jwpm-modal-title">
					<?php esc_html_e( 'Import Inventory Items', 'jwpm-jewelry-pos-manager' ); ?>
				</h2>
				<button type="button" class="jwpm-modal-close js-jwpm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'jwpm-jewelry-pos-manager' ); ?>">
					&times;
				</button>
			</div>
			<div class="jwpm-modal-body">
				<p>
					<?php esc_html_e( 'Upload an Excel/CSV file using the provided sample format.', 'jwpm-jewelry-pos-manager' ); ?>
				</p>
				<div class="jwpm-import-row">
					<button type="button" class="button js-jwpm-download-sample">
						<?php esc_html_e( 'Download Sample File', 'jwpm-jewelry-pos-manager' ); ?>
					</button>
				</div>
				<div class="jwpm-import-row">
					<input type="file" class="js-jwpm-import-file" accept=".csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
				</div>
				<div class="jwpm-import-row">
					<label>
						<input type="checkbox" class="js-jwpm-import-as-demo" />
						<?php esc_html_e( 'Mark imported records as demo data', 'jwpm-jewelry-pos-manager' ); ?>
					</label>
				</div>
			</div>
			<div class="jwpm-modal-footer">
				<button type="button" class="button js-jwpm-modal-close">
					<?php esc_html_e( 'Cancel', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
				<button type="button" class="button button-primary js-jwpm-start-import">
					<?php esc_html_e( 'Start Import', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</div>
		</div>
	</div>
</template>

<!-- Demo data modal -->
<template id="jwpm-inventory-demo-modal-template">
	<div class="jwpm-modal jwpm-modal-demo" role="dialog" aria-modal="true">
		<div class="jwpm-modal-backdrop js-jwpm-modal-close"></div>
		<div class="jwpm-modal-dialog">
			<div class="jwpm-modal-header">
				<h2 class="jwpm-modal-title">
					<?php esc_html_e( 'Demo Data', 'jwpm-jewelry-pos-manager' ); ?>
				</h2>
				<button type="button" class="jwpm-modal-close js-jwpm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'jwpm-jewelry-pos-manager' ); ?>">
					&times;
				</button>
			</div>
			<div class="jwpm-modal-body">
				<p><?php esc_html_e( 'You can quickly generate sample inventory items for testing, or delete all demo items.', 'jwpm-jewelry-pos-manager' ); ?></p>
				<div class="jwpm-demo-actions">
					<button type="button" class="button js-jwpm-create-demo-10">
						<?php esc_html_e( 'Create 10 demo items', 'jwpm-jewelry-pos-manager' ); ?>
					</button>
					<button type="button" class="button js-jwpm-create-demo-100">
						<?php esc_html_e( 'Create 100 demo items', 'jwpm-jewelry-pos-manager' ); ?>
					</button>
				</div>
				<div class="jwpm-demo-danger">
					<p><?php esc_html_e( 'Danger: This will permanently delete all demo items.', 'jwpm-jewelry-pos-manager' ); ?></p>
					<button type="button" class="button button-secondary js-jwpm-delete-demo-items">
						<?php esc_html_e( 'Delete demo items', 'jwpm-jewelry-pos-manager' ); ?>
					</button>
				</div>
			</div>
			<div class="jwpm-modal-footer">
				<button type="button" class="button js-jwpm-modal-close">
					<?php esc_html_e( 'Close', 'jwpm-jewelry-pos-manager' ); ?>
				</button>
			</div>
		</div>
	</div>
</template>

<?php
// 🔴 یہاں پر [Inventory Templates] ختم ہو رہا ہے

// ✅ Syntax verified block end

