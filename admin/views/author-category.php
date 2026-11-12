<?php
/**
 * Admin view: Author Categories
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
// Reason: $_GET values here are used only for read-only display (edit ID, search,
// pagination). All write operations go through AJAX with full nonce verification
// in class-author-category.php. No data is written based on these $_GET reads.
$trustbio_edit_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$trustbio_edit_cat = $trustbio_edit_id ? trustbio_get_category( $trustbio_edit_id ) : null;
$trustbio_per_page    = 20;
$trustbio_current_pag = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$trustbio_search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$trustbio_cat_class  = new TrustBio_Author_Category();
$trustbio_data       = $trustbio_cat_class->get_paginated( $trustbio_per_page, $trustbio_current_pag, $trustbio_search );
$trustbio_categories = $trustbio_data['items'];
$trustbio_total      = $trustbio_data['total'];
$trustbio_num_pages  = (int) ceil( $trustbio_total / $trustbio_per_page );

$trustbio_schema_props = array(
	'author'      => __( 'author — standard post author', 'trustbio' ),
	'reviewedBy'  => __( 'reviewedBy — medical / clinical reviewer', 'trustbio' ),
	'contributor' => __( 'contributor — contributor or co-author', 'trustbio' ),
	'editor'      => __( 'editor — editor or managing editor', 'trustbio' ),
);

$trustbio_post_types = get_post_types( array( 'public' => true ), 'objects' );
?>
<div class="trustbio-admin-wrap">

	<div class="tb-page-header">
		<div class="tb-page-header__left">
			<div class="tb-page-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm14 1a1 1 0 11-2 0 1 1 0 012 0zM2 13a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2zm14 1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"/></svg>
			</div>
			<div class="tb-page-header__text">
				<h1 class="tb-page-title"><?php esc_html_e( 'Author Categories', 'trustbio' ); ?></h1>
				<p class="tb-page-subtitle"><?php esc_html_e( 'Define roles like Writer, Medical Reviewer, Clinical Reviewer and more.', 'trustbio' ); ?></p>
			</div>
		</div>
		<div class="tb-page-header__actions">
			<?php if ( $trustbio_edit_id ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-categories' ) ); ?>" class="tb-btn tb-btn-secondary">
					<?php esc_html_e( '← Back to List', 'trustbio' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="tb-notice-area" id="tb-notice-area"></div>

	<div class="tb-layout-split">

		<!-- LEFT: Form -->
		<div>
			<div class="tb-card">
				<div class="tb-card-header">
					<h2 class="tb-card-title"><?php echo $trustbio_edit_id ? esc_html__( 'Edit Category', 'trustbio' ) : esc_html__( 'Add New Category', 'trustbio' ); ?></h2>
				</div>
				<div class="tb-card-body">
					<form id="tb-category-form" novalidate>
						<input type="hidden" id="tb-cat-id" name="id" value="<?php echo esc_attr( $trustbio_edit_id ); ?>">

						<div class="tb-form-group">
							<label class="tb-label" for="tb-cat-singular"><?php esc_html_e( 'Singular Name', 'trustbio' ); ?> <span class="required">*</span></label>
							<input type="text" id="tb-cat-singular" name="singular" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. Medical Reviewer', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_cat ? esc_attr( $trustbio_edit_cat->singular ) : ''; ?>" required>
							<p class="tb-input-hint"><?php esc_html_e( 'Used when there is a single author in this category.', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-cat-plural"><?php esc_html_e( 'Plural Name', 'trustbio' ); ?> <span class="required">*</span></label>
							<input type="text" id="tb-cat-plural" name="plural" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. Medical Reviewers', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_cat ? esc_attr( $trustbio_edit_cat->plural ) : ''; ?>" required>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-cat-label"><?php esc_html_e( 'Display Label', 'trustbio' ); ?></label>
							<input type="text" id="tb-cat-label" name="display_label" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. Medically Reviewed By', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_cat ? esc_attr( $trustbio_edit_cat->display_label ) : ''; ?>">
							<p class="tb-input-hint"><?php esc_html_e( 'Text shown above the author box on the frontend. Leave blank to use the Singular Name.', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-cat-schema"><?php esc_html_e( 'Schema Property', 'trustbio' ); ?></label>
							<select id="tb-cat-schema" name="schema_prop" class="tb-select">
								<?php foreach ( $trustbio_schema_props as $trustbio_val => $trustbio_label ) : ?>
									<option value="<?php echo esc_attr( $trustbio_val ); ?>" <?php selected( $trustbio_edit_cat ? $trustbio_edit_cat->schema_prop : 'author', $trustbio_val ); ?>><?php echo esc_html( $trustbio_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="tb-input-hint"><?php esc_html_e( 'Controls how this category appears in JSON-LD schema output.', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-cat-verb"><?php esc_html_e( 'Contribution Verb', 'trustbio' ); ?></label>
							<input type="text" id="tb-cat-verb" name="contribution_verb" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. Written, Reviewed, Edited', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_cat ? esc_attr( $trustbio_edit_cat->contribution_verb ?? 'Written' ) : 'Written'; ?>">
							<p class="tb-input-hint"><?php esc_html_e( 'Past-tense verb shown in the contribution count badge. e.g. "Written 47 articles" or "Reviewed 12 articles".', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-cat-noun"><?php esc_html_e( 'Contribution Noun', 'trustbio' ); ?></label>
							<input type="text" id="tb-cat-noun" name="contribution_noun" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. article', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_cat ? esc_attr( $trustbio_edit_cat->contribution_noun ?? 'article' ) : 'article'; ?>">
							<p class="tb-input-hint"><?php esc_html_e( 'Singular noun for the count badge. Plural is set in Settings → General. e.g. "article" → "Written 47 articles".', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-group">
							<label class="tb-label"><?php esc_html_e( 'Post Types', 'trustbio' ); ?></label>
							<?php
							$trustbio_saved_types = $trustbio_edit_cat && $trustbio_edit_cat->post_types
								? json_decode( $trustbio_edit_cat->post_types, true )
								: array();
							?>
							<label class="tb-checkbox-label">
								<input type="checkbox" id="tb-cat-pt-all" class="tb-pt-all-toggle" <?php checked( empty( $trustbio_saved_types ) ); ?>>
								<?php esc_html_e( 'All post types', 'trustbio' ); ?>
							</label>
							<div id="tb-pt-list" class="tb-pt-list <?php echo empty( $trustbio_saved_types ) ? 'tb-hidden' : ''; ?>">
								<?php foreach ( $trustbio_post_types as $trustbio_pt ) : ?>
									<label class="tb-checkbox-label">
										<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $trustbio_pt->name ); ?>" <?php checked( in_array( $trustbio_pt->name, $trustbio_saved_types, true ) ); ?>>
										<?php echo esc_html( $trustbio_pt->label ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-cat-order"><?php esc_html_e( 'Sort Order', 'trustbio' ); ?></label>
							<input type="number" id="tb-cat-order" name="sort_order" class="tb-input" min="0" value="<?php echo $trustbio_edit_cat ? esc_attr( $trustbio_edit_cat->sort_order ) : '0'; ?>" style="max-width:100px;">
						</div>

						<div class="tb-form-group">
							<label class="tb-label"><?php esc_html_e( 'Status', 'trustbio' ); ?></label>
							<div class="tb-toggle-wrap">
								<label class="tb-toggle">
									<input type="checkbox" name="is_enabled" id="tb-cat-enabled" value="1" <?php checked( $trustbio_edit_cat ? $trustbio_edit_cat->is_enabled : 1, 1 ); ?>>
									<span class="tb-toggle__track"></span>
								</label>
								<span class="tb-toggle-label"><?php esc_html_e( 'Enable this category', 'trustbio' ); ?></span>
							</div>
						</div>

						<hr class="tb-divider">

						<div style="display:flex;gap:10px;align-items:center;">
							<button type="submit" id="tb-cat-submit" class="tb-btn tb-btn-primary">
								<?php echo $trustbio_edit_id ? esc_html__( 'Update Category', 'trustbio' ) : esc_html__( 'Add Category', 'trustbio' ); ?>
							</button>
							<?php if ( $trustbio_edit_id ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-categories' ) ); ?>" class="tb-btn tb-btn-ghost"><?php esc_html_e( 'Cancel', 'trustbio' ); ?></a>
							<?php else : ?>
								<button type="reset" class="tb-btn tb-btn-ghost"><?php esc_html_e( 'Clear', 'trustbio' ); ?></button>
							<?php endif; ?>
						</div>
					</form>
				</div>
			</div>
		</div>

		<!-- RIGHT: Table -->
		<div>
			<div class="tb-card tb-card--overflow">
				<div class="tb-toolbar">
					<div class="tb-toolbar__left">
						<form method="get" action="">
							<input type="hidden" name="page" value="trustbio-categories">
							<div class="tb-search-wrap">
								<svg class="tb-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
								<input type="search" name="s" class="tb-search-input" placeholder="<?php esc_attr_e( 'Search categories…', 'trustbio' ); ?>" value="<?php echo esc_attr( $trustbio_search ); ?>">
							</div>
						</form>
					</div>
					<div class="tb-toolbar__right">
						<span class="tb-item-count"><?php
						/* translators: %d: number of author categories */
						printf( esc_html( _n( '%d category', '%d categories', $trustbio_total, 'trustbio' ) ), (int) $trustbio_total );
					?></span>
					</div>
				</div>

				<?php if ( empty( $trustbio_categories ) ) : ?>
					<div class="tb-empty">
						<p class="tb-empty__title"><?php esc_html_e( 'No categories yet', 'trustbio' ); ?></p>
						<p class="tb-empty__text"><?php esc_html_e( 'Add your first author category using the form on the left.', 'trustbio' ); ?></p>
					</div>
				<?php else : ?>
				<div class="tb-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
					<table class="tb-table">
						<thead>
							<tr>
								<th class="tb-col-check"><input type="checkbox" class="tb-check-all"></th>
								<th><?php esc_html_e( 'Name', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Plural', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Display Label', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Schema', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Slug', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Status', 'trustbio' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $trustbio_categories as $trustbio_cat ) : ?>
							<tr id="tb-cat-row-<?php echo esc_attr( $trustbio_cat->id ); ?>">
								<td class="tb-col-check"><input type="checkbox" value="<?php echo esc_attr( $trustbio_cat->id ); ?>"></td>
								<td>
									<strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-categories&edit=' . $trustbio_cat->id ) ); ?>"><?php echo esc_html( $trustbio_cat->singular ); ?></a></strong>
									<div class="tb-row-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-categories&edit=' . $trustbio_cat->id ) ); ?>" class="tb-row-action tb-row-action-edit"><?php esc_html_e( 'Edit', 'trustbio' ); ?></a>
										<button type="button" class="tb-row-action tb-row-action-toggle-status" data-id="<?php echo esc_attr( $trustbio_cat->id ); ?>" data-action="trustbio_toggle_category" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_toggle_category' ) ); ?>">
											<?php echo $trustbio_cat->is_enabled ? esc_html__( 'Disable', 'trustbio' ) : esc_html__( 'Enable', 'trustbio' ); ?>
										</button>
										<?php if ( 'author' !== $trustbio_cat->slug ) : ?>
										<button type="button" class="tb-row-action tb-row-action-delete tb-delete-category" data-id="<?php echo esc_attr( $trustbio_cat->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_delete_category' ) ); ?>">
											<?php esc_html_e( 'Delete', 'trustbio' ); ?>
										</button>
										<?php endif; ?>
									</div>
								</td>
								<td><?php echo esc_html( $trustbio_cat->plural ); ?></td>
								<td><em><?php echo $trustbio_cat->display_label ? esc_html( $trustbio_cat->display_label ) : '<span style="color:var(--tb-gray-400)">—</span>'; ?></em></td>
								<td><span class="tb-text-mono"><?php echo esc_html( $trustbio_cat->schema_prop ); ?></span></td>
								<td><span class="tb-text-mono"><?php echo esc_html( $trustbio_cat->slug ); ?></span></td>
								<td><span class="tb-badge <?php echo $trustbio_cat->is_enabled ? 'tb-badge-success' : 'tb-badge-danger'; ?>"><?php echo $trustbio_cat->is_enabled ? esc_html__( 'Enabled', 'trustbio' ) : esc_html__( 'Disabled', 'trustbio' ); ?></span></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php if ( $trustbio_num_pages > 1 ) : ?>
				<div class="tb-pagination"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'prev_text' => '&laquo;', 'next_text' => '&raquo;', 'total' => $trustbio_num_pages, 'current' => $trustbio_current_pag ) ) ); ?></div>
				<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

