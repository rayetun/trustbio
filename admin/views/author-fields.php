<?php
/**
 * Admin view: Author Fields
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
// Reason: $_GET values used only for read-only display. All writes use AJAX with nonce.
$trustbio_edit_id   = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$trustbio_filter    = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';
$trustbio_current_p = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$trustbio_fields_class = new TrustBio_Author_Fields();
$trustbio_edit_field   = null;

if ( $trustbio_edit_id ) {
	global $wpdb;
	$trustbio_ft = $wpdb->prefix . 'trustbio_author_fields';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$trustbio_edit_field = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_ft}` WHERE id = %d", $trustbio_edit_id ) );
}

$trustbio_per_page = 30;
$trustbio_data     = $trustbio_fields_class->get_paginated( $trustbio_per_page, $trustbio_current_p, $trustbio_filter );
$trustbio_fields   = $trustbio_data['items'];
$trustbio_total    = $trustbio_data['total'];
$trustbio_pages    = (int) ceil( $trustbio_total / $trustbio_per_page );

$trustbio_filter_tabs = array(
	'all'      => __( 'All', 'trustbio' ),
	'active'   => __( 'Active', 'trustbio' ),
	'disabled' => __( 'Disabled', 'trustbio' ),
	'social'   => __( 'Social', 'trustbio' ),
	'text'     => __( 'Text Fields', 'trustbio' ),
);
?>
<div class="trustbio-admin-wrap">

	<div class="tb-page-header">
		<div class="tb-page-header__left">
			<div class="tb-page-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/></svg>
			</div>
			<div class="tb-page-header__text">
				<h1 class="tb-page-title"><?php esc_html_e( 'Author Fields', 'trustbio' ); ?></h1>
				<p class="tb-page-subtitle"><?php esc_html_e( 'Manage social profiles and custom fields shown in author boxes.', 'trustbio' ); ?></p>
			</div>
		</div>
	</div>

	<div class="tb-notice-area" id="tb-notice-area"></div>

	<div class="tb-layout-split">

		<!-- LEFT: Form -->
		<div>
			<div class="tb-card">
				<div class="tb-card-header">
					<h2 class="tb-card-title"><?php echo $trustbio_edit_id ? esc_html__( 'Edit Field', 'trustbio' ) : esc_html__( 'Add New Field', 'trustbio' ); ?></h2>
				</div>
				<div class="tb-card-body">
					<form id="tb-field-form" novalidate>
						<input type="hidden" id="tb-field-id" name="id" value="<?php echo esc_attr( $trustbio_edit_id ); ?>">

						<div class="tb-form-group">
							<label class="tb-label" for="tb-field-label"><?php esc_html_e( 'Label', 'trustbio' ); ?> <span class="required">*</span></label>
							<input type="text" id="tb-field-label" name="label" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. LinkedIn', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_field ? esc_attr( $trustbio_edit_field->label ) : ''; ?>" required>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-field-slug"><?php esc_html_e( 'Slug', 'trustbio' ); ?></label>
							<input type="text" id="tb-field-slug" class="tb-input tb-text-mono" placeholder="<?php esc_attr_e( 'auto-generated', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_field ? esc_attr( $trustbio_edit_field->slug ) : ''; ?>" readonly>
							<p class="tb-input-hint"><?php esc_html_e( 'Auto-generated from the label. Cannot be changed after creation.', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-field-type"><?php esc_html_e( 'Field Type', 'trustbio' ); ?></label>
							<select id="tb-field-type" name="field_type" class="tb-select">
								<option value="link" <?php selected( $trustbio_edit_field ? $trustbio_edit_field->field_type : 'link', 'link' ); ?>><?php esc_html_e( 'Link (URL)', 'trustbio' ); ?></option>
								<option value="text" <?php selected( $trustbio_edit_field ? $trustbio_edit_field->field_type : '', 'text' ); ?>><?php esc_html_e( 'Text', 'trustbio' ); ?></option>
								<option value="textarea" <?php selected( $trustbio_edit_field ? $trustbio_edit_field->field_type : '', 'textarea' ); ?>><?php esc_html_e( 'Textarea', 'trustbio' ); ?></option>
							</select>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-field-placeholder"><?php esc_html_e( 'Placeholder', 'trustbio' ); ?></label>
							<input type="text" id="tb-field-placeholder" name="placeholder" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. https://linkedin.com/in/yourprofile', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_field ? esc_attr( $trustbio_edit_field->placeholder ) : ''; ?>">
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-field-order"><?php esc_html_e( 'Sort Order', 'trustbio' ); ?></label>
							<input type="number" id="tb-field-order" name="sort_order" class="tb-input" min="0" value="<?php echo $trustbio_edit_field ? esc_attr( $trustbio_edit_field->sort_order ) : '99'; ?>" style="max-width:100px;">
						</div>

						<div class="tb-form-group">
							<label class="tb-label"><?php esc_html_e( 'Options', 'trustbio' ); ?></label>
							<div style="display:flex;flex-direction:column;gap:10px;">
								<div class="tb-toggle-wrap">
									<label class="tb-toggle">
										<input type="checkbox" name="is_social" id="tb-field-social" value="1" <?php checked( $trustbio_edit_field ? $trustbio_edit_field->is_social : 1, 1 ); ?>>
										<span class="tb-toggle__track"></span>
									</label>
									<span class="tb-toggle-label"><?php esc_html_e( 'Show as social icon in author box', 'trustbio' ); ?></span>
								</div>
								<div class="tb-toggle-wrap">
									<label class="tb-toggle">
										<input type="checkbox" name="is_required" id="tb-field-required" value="1" <?php checked( $trustbio_edit_field ? $trustbio_edit_field->is_required : 0, 1 ); ?>>
										<span class="tb-toggle__track"></span>
									</label>
									<span class="tb-toggle-label"><?php esc_html_e( 'Required field', 'trustbio' ); ?></span>
								</div>
								<div class="tb-toggle-wrap">
									<label class="tb-toggle">
										<input type="checkbox" name="is_active" id="tb-field-active" value="1" <?php checked( $trustbio_edit_field ? $trustbio_edit_field->is_active : 1, 1 ); ?>>
										<span class="tb-toggle__track"></span>
									</label>
									<span class="tb-toggle-label"><?php esc_html_e( 'Active (visible in author profiles)', 'trustbio' ); ?></span>
								</div>
							</div>
						</div>

						<hr class="tb-divider">
						<div style="display:flex;gap:10px;">
							<button type="submit" id="tb-field-submit" class="tb-btn tb-btn-primary">
								<?php echo $trustbio_edit_id ? esc_html__( 'Update Field', 'trustbio' ) : esc_html__( 'Add Field', 'trustbio' ); ?>
							</button>
							<button type="reset" class="tb-btn tb-btn-ghost"><?php esc_html_e( 'Clear', 'trustbio' ); ?></button>
						</div>
					</form>
				</div>
			</div>

			<div class="tb-card">
				<div class="tb-card-body">
					<p class="tb-section-label"><?php esc_html_e( 'Built-in Fields', 'trustbio' ); ?></p>
					<p style="font-size:13px;color:var(--tb-gray-600);margin:0;"><?php esc_html_e( 'Built-in fields can be enabled or disabled but not deleted. Custom fields you add can be fully managed.', 'trustbio' ); ?></p>
				</div>
			</div>
		</div>

		<!-- RIGHT: Table -->
		<div>
			<div class="tb-card tb-card--overflow">

				<div style="padding:0 4px 0;">
					<div class="tb-tabs">
						<?php foreach ( $trustbio_filter_tabs as $trustbio_fkey => $trustbio_flabel ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-fields&filter=' . $trustbio_fkey ) ); ?>" class="tb-tab <?php echo $trustbio_filter === $trustbio_fkey ? 'is-active' : ''; ?>"><?php echo esc_html( $trustbio_flabel ); ?></a>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="tb-toolbar">
					<div class="tb-toolbar__left">
						<span style="font-size:12px;color:var(--tb-gray-500);"><?php esc_html_e( 'Drag rows to change display order', 'trustbio' ); ?></span>
					</div>
					<div class="tb-toolbar__right">
						<span class="tb-item-count"><?php
						/* translators: %d: number of author fields */
						printf( esc_html( _n( '%d field', '%d fields', $trustbio_total, 'trustbio' ) ), (int) $trustbio_total );
					?></span>
					</div>
				</div>

				<?php if ( empty( $trustbio_fields ) ) : ?>
					<div class="tb-empty"><p class="tb-empty__title"><?php esc_html_e( 'No fields found', 'trustbio' ); ?></p></div>
				<?php else : ?>
				<div class="tb-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
					<table class="tb-table">
						<thead>
							<tr>
								<th style="width:28px;"></th>
								<th><?php esc_html_e( 'Label', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Slug', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Type', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Social', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Required', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Status', 'trustbio' ); ?></th>
							</tr>
						</thead>
						<tbody id="tb-fields-sortable">
						<?php foreach ( $trustbio_fields as $trustbio_field ) : ?>
							<tr id="tb-field-row-<?php echo esc_attr( $trustbio_field->id ); ?>" data-id="<?php echo esc_attr( $trustbio_field->id ); ?>">
								<td style="color:var(--tb-gray-300);cursor:grab;text-align:center;" class="tb-drag-handle">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M7 2a2 2 0 11-4 0 2 2 0 014 0zm0 8a2 2 0 11-4 0 2 2 0 014 0zm0 8a2 2 0 11-4 0 2 2 0 014 0zm6-16a2 2 0 11-4 0 2 2 0 014 0zm0 8a2 2 0 11-4 0 2 2 0 014 0zm0 8a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
								</td>
								<td>
									<strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-fields&edit=' . $trustbio_field->id ) ); ?>"><?php echo esc_html( $trustbio_field->label ); ?></a></strong>
									<?php if ( $trustbio_field->is_builtin ) : ?><span class="tb-builtin-badge"><?php esc_html_e( 'built-in', 'trustbio' ); ?></span><?php endif; ?>
									<div class="tb-row-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-fields&edit=' . $trustbio_field->id ) ); ?>" class="tb-row-action tb-row-action-edit"><?php esc_html_e( 'Edit', 'trustbio' ); ?></a>
										<button type="button" class="tb-row-action tb-row-action-toggle-status" data-id="<?php echo esc_attr( $trustbio_field->id ); ?>" data-action="trustbio_toggle_field" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_toggle_field' ) ); ?>"><?php echo $trustbio_field->is_active ? esc_html__( 'Deactivate', 'trustbio' ) : esc_html__( 'Activate', 'trustbio' ); ?></button>
										<?php if ( ! $trustbio_field->is_builtin ) : ?>
										<button type="button" class="tb-row-action tb-row-action-delete tb-delete-field" data-id="<?php echo esc_attr( $trustbio_field->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_delete_field' ) ); ?>"><?php esc_html_e( 'Delete', 'trustbio' ); ?></button>
										<?php endif; ?>
									</div>
								</td>
								<td><span class="tb-text-mono"><?php echo esc_html( $trustbio_field->slug ); ?></span></td>
								<td><?php echo esc_html( ucfirst( $trustbio_field->field_type ) ); ?></td>
								<td><span class="tb-badge <?php echo $trustbio_field->is_social ? 'tb-badge-success' : 'tb-badge-gray'; ?>"><?php echo $trustbio_field->is_social ? esc_html__( 'Yes', 'trustbio' ) : esc_html__( 'No', 'trustbio' ); ?></span></td>
								<td><span class="tb-badge <?php echo $trustbio_field->is_required ? 'tb-badge-warning' : 'tb-badge-gray'; ?>"><?php echo $trustbio_field->is_required ? esc_html__( 'Required', 'trustbio' ) : esc_html__( 'Optional', 'trustbio' ); ?></span></td>
								<td><span class="tb-badge <?php echo $trustbio_field->is_active ? 'tb-badge-success' : 'tb-badge-danger'; ?>"><?php echo $trustbio_field->is_active ? esc_html__( 'Active', 'trustbio' ) : esc_html__( 'Disabled', 'trustbio' ); ?></span></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php if ( $trustbio_pages > 1 ) : ?>
				<div class="tb-pagination"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'prev_text' => '&laquo;', 'next_text' => '&raquo;', 'total' => $trustbio_pages, 'current' => $trustbio_current_p ) ) ); ?></div>
				<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

