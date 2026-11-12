<?php
/**
 * Admin view: Authors
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-local variables, not truly global
$trustbio_type_tab    = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : 'all';
$trustbio_search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$trustbio_current_p   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$trustbio_authors_class = new TrustBio_Authors_List();
$trustbio_per_page      = 20;
$trustbio_data          = $trustbio_authors_class->get_paginated( $trustbio_per_page, $trustbio_current_p, $trustbio_type_tab, $trustbio_search );
$trustbio_authors       = $trustbio_data['items'];
$trustbio_total         = $trustbio_data['total'];
$trustbio_pages         = (int) ceil( $trustbio_total / $trustbio_per_page );
$trustbio_categories    = trustbio_get_categories( true );
$trustbio_active_fields = trustbio_get_fields();

$trustbio_allow_no_email = (bool) get_option( 'trustbio_allow_guest_no_email', 0 );

$trustbio_type_tabs = array(
	'all'        => __( 'All Authors', 'trustbio' ),
	'registered' => __( 'Registered User', 'trustbio' ),
	'guest'      => __( 'Guest Author', 'trustbio' ),
);
?>
<div class="trustbio-admin-wrap">

	<div class="tb-page-header">
		<div class="tb-page-header__left">
			<div class="tb-page-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
			</div>
			<div class="tb-page-header__text">
				<h1 class="tb-page-title"><?php esc_html_e( 'Authors', 'trustbio' ); ?></h1>
				<p class="tb-page-subtitle"><?php esc_html_e( 'Manage registered and guest author profiles.', 'trustbio' ); ?></p>
			</div>
		</div>
		<div class="tb-page-header__actions">
			<button type="button" class="tb-btn tb-btn-primary" id="tb-open-add-author">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:15px;height:15px;"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Add Author', 'trustbio' ); ?>
			</button>
		</div>
	</div>

	<div class="tb-notice-area" id="tb-notice-area"></div>

	<!-- ===== ADD AUTHOR PANEL (collapsible) ===== -->
	<div id="tb-add-author-panel" class="tb-collapsible-panel" style="display:none;">
		<div class="tb-content-area">
			<div class="tb-card">
				<div class="tb-card-header">
					<h2 class="tb-card-title" id="tb-add-panel-title"><?php esc_html_e( 'Add New Author', 'trustbio' ); ?></h2>
					<button type="button" class="tb-btn tb-btn-ghost tb-close-add-author"><?php esc_html_e( '✕ Close', 'trustbio' ); ?></button>
				</div>
				<div class="tb-card-body">

					<!-- Author Type Tabs -->
					<div class="tb-author-type-tabs" style="margin-bottom:24px;">
						<div class="tb-tabs" data-tabs-wrap="author-type">
							<button type="button" class="tb-tab is-active" data-tab="registered" data-tabs-wrap="author-type"><?php esc_html_e( 'Registered User', 'trustbio' ); ?></button>
							<button type="button" class="tb-tab" data-tab="guest" data-tabs-wrap="author-type"><?php esc_html_e( 'Guest Author With Email', 'trustbio' ); ?></button>
							<?php if ( $trustbio_allow_no_email ) : ?>
							<button type="button" class="tb-tab" data-tab="guest-no-email" data-tabs-wrap="author-type"><?php esc_html_e( 'Guest Author Without Email', 'trustbio' ); ?></button>
							<?php endif; ?>
						</div>
					</div>

					<form id="tb-author-form" novalidate>
						<input type="hidden" id="tb-author-id" name="id" value="0">
						<input type="hidden" id="tb-author-type" name="author_type" value="registered">

						<div class="tb-form-cols">
							<!-- LEFT col -->
							<div>
								<!-- Registered user search (shown for registered tab) -->
								<div id="tb-wp-user-field" class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Select WordPress User', 'trustbio' ); ?> <span class="required">*</span><?php TrustBio_Help_Tabs::tip( __( 'Links this author profile to an existing WordPress user account. Their login email is used to fetch a Gravatar automatically.', 'trustbio' ) ); ?></label>
									<div class="tb-search-wrap" style="width:100%;">
										<svg class="tb-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
										<input type="text" id="tb-wp-user-search" class="tb-search-input" style="width:100%;" placeholder="<?php esc_attr_e( 'Search WordPress users…', 'trustbio' ); ?>">
									</div>
									<div id="tb-wp-user-results" class="tb-search-dropdown" style="display:none;"></div>
									<input type="hidden" id="tb-wp-user-id" name="wp_user_id" value="">
									<div id="tb-wp-user-selected" style="display:none;" class="tb-selected-user"></div>
								</div>

								<div class="tb-form-group">
									<label class="tb-label" for="tb-author-display-name"><?php esc_html_e( 'Display Name', 'trustbio' ); ?> <span class="required">*</span><?php TrustBio_Help_Tabs::tip( __( 'The name shown publicly in author boxes and schema markup. Use the author\'s full professional name for best E-E-A-T impact.', 'trustbio' ) ); ?></label>
									<input type="text" id="tb-author-display-name" name="display_name" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. Dr. John Smith', 'trustbio' ); ?>" required>
									<p class="tb-input-hint"><?php esc_html_e( 'This is all you need to save an author. Everything else can be filled in later.', 'trustbio' ); ?></p>
								</div>

								<div class="tb-form-group" id="tb-email-field">
									<label class="tb-label" for="tb-author-email"><?php esc_html_e( 'Email', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'Used to fetch a Gravatar profile photo. Not displayed publicly unless you add an email field to your author box layout.', 'trustbio' ) ); ?></label>
									<input type="email" id="tb-author-email" name="email" class="tb-input" placeholder="<?php esc_attr_e( 'author@example.com', 'trustbio' ); ?>">
								</div>

							<div class="tb-form-group">
								<label class="tb-label" for="tb-author-job"><?php esc_html_e( 'Job Title', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'Shown in the author box and included in JSON-LD schema as jobTitle. Be specific — e.g. Registered Dietitian rather than Writer — for stronger E-E-A-T signals.', 'trustbio' ) ); ?></label>
								<input type="text" id="tb-author-job" name="job_title" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. MD, Addiction Medicine', 'trustbio' ); ?>">
							</div>

								<div class="tb-form-group">
									<label class="tb-label" for="tb-author-bio"><?php esc_html_e( 'Bio', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'A short biography displayed in the author box. Focus on professional background and expertise relevant to your content topics. 50–150 words is ideal.', 'trustbio' ) ); ?></label>
									<textarea id="tb-author-bio" name="bio" class="tb-textarea" rows="5" placeholder="<?php esc_attr_e( 'Author biography…', 'trustbio' ); ?>"></textarea>
								</div>

								<details class="tb-more-details">
									<summary class="tb-more-details__summary"><?php esc_html_e( 'More details (optional)', 'trustbio' ); ?></summary>
									<div class="tb-more-details__body">
								<div class="tb-form-group">
									<label class="tb-label" for="tb-author-website"><?php esc_html_e( 'Website', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'The author\'s personal or professional website URL. Included in schema markup and can be shown as a link in the author box.', 'trustbio' ) ); ?></label>
									<input type="url" id="tb-author-website" name="website" class="tb-input" placeholder="https://">
								</div>

								<div class="tb-form-group">
									<label class="tb-label" for="tb-author-cred"><?php esc_html_e( 'Credentials', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'Free-text credentials shown in the author box (e.g. MD, FACP). For verified credentials with expiry dates and schema support, use the Credentials screen after saving.', 'trustbio' ) ); ?></label>
									<input type="text" id="tb-author-cred" name="credentials" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. MD, PhD, LCSW', 'trustbio' ); ?>">
								</div>

								<div class="tb-form-group">
									<label class="tb-label" for="tb-author-category"><?php esc_html_e( 'Default Author Category', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'Defines the author\'s role: Writer, Reviewer, Expert, etc. Controls the verb in the contribution count badge and which fields appear in the author box.', 'trustbio' ) ); ?></label>
									<select id="tb-author-category" name="category_id" class="tb-select">
										<option value=""><?php esc_html_e( '— Select Category —', 'trustbio' ); ?></option>
										<?php foreach ( $trustbio_categories as $trustbio_cat ) : ?>
											<option value="<?php echo esc_attr( $trustbio_cat->id ); ?>"><?php echo esc_html( $trustbio_cat->singular ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
									</div>
								</details>
							</div>

							<!-- RIGHT col: Avatar + Social Fields -->
							<div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Author Photo', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'Upload a professional headshot. If blank, TrustBio falls back to Gravatar, then to the default avatar set in Settings → Author Profiles.', 'trustbio' ) ); ?></label>
									<div class="tb-avatar-upload">
										<div class="tb-avatar-preview" id="tb-avatar-preview">
											<div class="tb-avatar-placeholder">
												<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:40px;height:40px;color:var(--tb-gray-300);"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
											</div>
										</div>
										<div class="tb-avatar-actions">
											<button type="button" class="tb-btn tb-btn-secondary tb-btn-sm tb-upload-avatar-btn" data-preview="#tb-avatar-preview-img" data-input="#tb-avatar-attach-id"><?php esc_html_e( 'Upload Photo', 'trustbio' ); ?></button>
											<button type="button" class="tb-btn tb-btn-ghost tb-btn-sm tb-remove-avatar-btn" data-preview="#tb-avatar-preview-img" data-input="#tb-avatar-attach-id" style="display:none;"><?php esc_html_e( 'Remove', 'trustbio' ); ?></button>
										</div>
										<input type="hidden" id="tb-avatar-attach-id" name="avatar_attach_id" value="">
									</div>
									<p class="tb-input-hint"><?php esc_html_e( 'Upload a custom photo. Falls back to Gravatar if not set.', 'trustbio' ); ?></p>
								</div>

								<?php if ( ! empty( $trustbio_active_fields ) ) : ?>
								<details class="tb-more-details">
									<summary class="tb-more-details__summary"><?php esc_html_e( 'Social & custom fields (optional)', 'trustbio' ); ?></summary>
									<div class="tb-more-details__body">
									<?php foreach ( $trustbio_active_fields as $trustbio_field ) : ?>
									<div class="tb-form-group" style="margin-bottom:12px;">
										<label class="tb-label" style="font-weight:500;" for="tb-field-<?php echo esc_attr( $trustbio_field->slug ); ?>"><?php echo esc_html( $trustbio_field->label ); ?></label>
										<?php if ( 'textarea' === $trustbio_field->field_type ) : ?>
											<textarea id="tb-field-<?php echo esc_attr( $trustbio_field->slug ); ?>" name="fields[<?php echo esc_attr( $trustbio_field->slug ); ?>]" class="tb-textarea" rows="3" placeholder="<?php echo esc_attr( $trustbio_field->placeholder ); ?>"></textarea>
										<?php else : ?>
											<input type="<?php echo 'link' === $trustbio_field->field_type ? 'url' : 'text'; ?>" id="tb-field-<?php echo esc_attr( $trustbio_field->slug ); ?>" name="fields[<?php echo esc_attr( $trustbio_field->slug ); ?>]" class="tb-input" placeholder="<?php echo esc_attr( $trustbio_field->placeholder ); ?>">
										<?php endif; ?>
									</div>
									<?php endforeach; ?>
									</div>
								</details>
								<?php endif; ?>
							</div>
						</div>

						<hr class="tb-divider">
						<div class="tb-save-bar" style="position:static;box-shadow:none;border:none;padding:0;background:transparent;">
							<div></div>
							<div class="tb-save-bar__actions">
								<button type="submit" id="tb-author-submit" class="tb-btn tb-btn-primary"><?php esc_html_e( 'Save Author', 'trustbio' ); ?></button>
								<button type="button" class="tb-btn tb-btn-ghost tb-close-add-author"><?php esc_html_e( 'Cancel', 'trustbio' ); ?></button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== AUTHORS LIST ===== -->
	<div class="tb-content-area">
		<div class="tb-card">

			<!-- Type filter tabs -->
			<div style="padding:0 4px 0;">
				<div class="tb-tabs">
					<?php foreach ( $trustbio_type_tabs as $trustbio_tkey => $trustbio_tlabel ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-authors&type=' . $trustbio_tkey ) ); ?>" class="tb-tab <?php echo $trustbio_type_tab === $trustbio_tkey ? 'is-active' : ''; ?>"><?php echo esc_html( $trustbio_tlabel ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="tb-toolbar">
				<div class="tb-toolbar__left">
					<form method="get" action="">
						<input type="hidden" name="page" value="trustbio-authors">
						<input type="hidden" name="type" value="<?php echo esc_attr( $trustbio_type_tab ); ?>">
						<div class="tb-search-wrap">
							<svg class="tb-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
							<input type="search" name="s" class="tb-search-input" placeholder="<?php esc_attr_e( 'Search authors…', 'trustbio' ); ?>" value="<?php echo esc_attr( $trustbio_search ); ?>">
						</div>
					</form>
				</div>
				<div class="tb-toolbar__right">
					<span class="tb-item-count"><?php
						/* translators: %d: number of authors */
						printf( esc_html( _n( '%d author', '%d authors', $trustbio_total, 'trustbio' ) ), (int) $trustbio_total );
					?></span>
				</div>
			</div>

			<?php if ( empty( $trustbio_authors ) ) : ?>
				<div class="tb-empty-hero">
					<div class="tb-empty-hero__icon tb-empty-hero__icon--indigo">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
					</div>
					<h3 class="tb-empty-hero__title"><?php esc_html_e( 'Add your first author', 'trustbio' ); ?></h3>
					<p class="tb-empty-hero__text"><?php esc_html_e( 'Build trust and boost E-E-A-T by adding expert authors with credentials, photos, and social profiles.', 'trustbio' ); ?></p>
					<div class="tb-empty-hero__chips">
						<span class="tb-empty-hero__chip"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php esc_html_e( 'Structured schema data', 'trustbio' ); ?></span>
						<span class="tb-empty-hero__chip"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php esc_html_e( 'Author archive pages', 'trustbio' ); ?></span>
						<span class="tb-empty-hero__chip"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php esc_html_e( 'Guest & registered authors', 'trustbio' ); ?></span>
					</div>
					<button type="button" class="tb-btn tb-btn-primary" id="tb-open-add-author"><?php esc_html_e( '+ Add First Author', 'trustbio' ); ?></button>
				</div>
			<?php else : ?>
			<div class="tb-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
				<table class="tb-table">
					<thead>
						<tr>
							<th class="tb-col-check"><input type="checkbox" class="tb-check-all"></th>
							<th><?php esc_html_e( 'Author', 'trustbio' ); ?></th>
							<th><?php esc_html_e( 'Email', 'trustbio' ); ?></th>
							<th><?php esc_html_e( 'Type', 'trustbio' ); ?></th>
							<th><?php esc_html_e( 'Category', 'trustbio' ); ?></th>
							<th><?php esc_html_e( 'Author URL', 'trustbio' ); ?></th>
							<th style="text-align:center;"><?php esc_html_e( 'Posts', 'trustbio' ); ?></th>
							<th><?php esc_html_e( 'Completeness', 'trustbio' ); ?></th>
							<th><?php esc_html_e( 'Status', 'trustbio' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $trustbio_authors as $trustbio_author ) : ?>
						<tr id="tb-author-row-<?php echo esc_attr( $trustbio_author->id ); ?>">
							<td class="tb-col-check"><input type="checkbox" value="<?php echo esc_attr( $trustbio_author->id ); ?>"></td>
							<td>
								<div class="tb-author-cell">
									<div class="tb-avatar">
										<img src="<?php echo esc_url( trustbio_get_avatar_url( $trustbio_author, 36 ) ); ?>" alt="" width="36" height="36">
									</div>
									<div>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-profile&id=' . $trustbio_author->id ) ); ?>" style="font-weight:600;color:var(--tb-gray-900);"><?php echo esc_html( $trustbio_author->display_name ); ?></a>
										<?php if ( $trustbio_author->job_title ) : ?>
											<div style="font-size:12px;color:var(--tb-gray-500);"><?php echo esc_html( $trustbio_author->job_title ); ?></div>
										<?php endif; ?>
										<div class="tb-row-actions">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-profile&id=' . $trustbio_author->id ) ); ?>" class="tb-row-action tb-row-action-edit"><?php esc_html_e( 'Edit Profile', 'trustbio' ); ?></a>
											<button type="button" class="tb-row-action tb-row-action-toggle-status" data-id="<?php echo esc_attr( $trustbio_author->id ); ?>" data-action="trustbio_toggle_author" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_toggle_author' ) ); ?>"><?php echo $trustbio_author->is_active ? esc_html__( 'Deactivate', 'trustbio' ) : esc_html__( 'Activate', 'trustbio' ); ?></button>
											<button type="button" class="tb-row-action tb-row-action-delete tb-delete-author" data-id="<?php echo esc_attr( $trustbio_author->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_delete_author' ) ); ?>"><?php esc_html_e( 'Delete', 'trustbio' ); ?></button>
										</div>
									</div>
								</div>
							</td>
							<td><?php echo $trustbio_author->email ? esc_html( $trustbio_author->email ) : '<span style="color:var(--tb-gray-400)">—</span>'; ?></td>
							<td>
								<span class="tb-badge <?php echo 'registered' === $trustbio_author->author_type ? 'tb-badge-info' : 'tb-badge-gray'; ?>">
									<?php echo 'registered' === $trustbio_author->author_type ? esc_html__( 'Registered', 'trustbio' ) : esc_html__( 'Guest', 'trustbio' ); ?>
								</span>
							</td>
							<td>
								<?php if ( $trustbio_author->category_name ) :
									$trustbio_cat_palette = array( 'indigo', 'violet', 'cyan', 'emerald', 'orange', 'rose', 'amber', 'teal' );
									$trustbio_cat_color   = $trustbio_cat_palette[ abs( crc32( $trustbio_author->category_name ) ) % 8 ];
								?>
								<span class="tb-cat-tag tb-cat-tag--<?php echo esc_attr( $trustbio_cat_color ); ?>"><?php echo esc_html( $trustbio_author->category_name ); ?></span>
								<?php else : ?>
								<span style="color:var(--tb-gray-400)">—</span>
								<?php endif; ?>
							</td>
							<td><span class="tb-text-mono" style="font-size:11px;"><?php echo esc_html( $trustbio_author->slug ); ?></span></td>
							<td style="text-align:center;">
								<?php
								$trustbio_total = TrustBio_Contribution_Counter::get_total( (int) $trustbio_author->id );
								if ( $trustbio_total > 0 ) :
								?>
								<a href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'post', 'trustbio_author' => $trustbio_author->slug ), admin_url( 'edit.php' ) ) ); ?>" style="font-weight:600;font-size:13px;"><?php echo esc_html( number_format_i18n( $trustbio_total ) ); ?></a>
								<?php else : ?>
								<span style="color:var(--tb-gray-300);font-size:12px;">0</span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$tb_cmp = array(
									__( 'Photo', 'trustbio' )       => ! empty( $trustbio_author->avatar_attach_id ) || ! empty( $trustbio_author->avatar_url ),
									__( 'Bio', 'trustbio' )         => ! empty( $trustbio_author->bio ),
									__( 'Job Title', 'trustbio' )   => ! empty( $trustbio_author->job_title ),
									__( 'Social Link', 'trustbio' ) => ! empty( $trustbio_author->has_social ),
									__( 'Credentials', 'trustbio' ) => ! empty( $trustbio_author->cred_count ),
									__( 'Expertise', 'trustbio' )   => ! empty( $trustbio_author->expertise_cats ) && '[]' !== $trustbio_author->expertise_cats,
								);
								$tb_cmp_score   = count( array_filter( $tb_cmp ) );
								$tb_cmp_pct     = (int) round( ( $tb_cmp_score / 6 ) * 100 );
								$tb_cmp_color   = $tb_cmp_score >= 5 ? '#22c55e' : ( $tb_cmp_score >= 3 ? '#f59e0b' : '#ef4444' );
								$tb_cmp_missing = implode( ', ', array_keys( array_filter( $tb_cmp, static fn( $v ) => ! $v ) ) );
								$tb_cmp_tip     = $tb_cmp_missing
									? sprintf( /* translators: %s: comma-separated list of missing profile signals */ __( 'Missing: %s', 'trustbio' ), $tb_cmp_missing )
									: __( 'Profile complete', 'trustbio' );
								?>
								<div style="min-width:84px;" title="<?php echo esc_attr( $tb_cmp_tip ); ?>">
									<div style="display:flex;align-items:center;gap:5px;">
										<div style="flex:1;height:4px;background:#f3f4f6;border-radius:2px;overflow:hidden;">
											<div style="width:<?php echo esc_attr( $tb_cmp_pct ); ?>%;height:100%;background:<?php echo esc_attr( $tb_cmp_color ); ?>;border-radius:2px;"></div>
										</div>
										<span style="font-size:11px;font-weight:600;color:<?php echo esc_attr( $tb_cmp_color ); ?>;white-space:nowrap;"><?php echo (int) $tb_cmp_score; ?>/6</span>
									</div>
								</div>
							</td>
							<td><span class="tb-badge <?php echo $trustbio_author->is_active ? 'tb-badge-success' : 'tb-badge-danger'; ?>"><?php echo $trustbio_author->is_active ? esc_html__( 'Active', 'trustbio' ) : esc_html__( 'Inactive', 'trustbio' ); ?></span></td>
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


