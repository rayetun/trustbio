<?php
/**
 * Admin view: Author Profile Edit Screen
 * Tabs: General | Avatar | Social Profiles | SEO & Schema
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$trustbio_author_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// No author ID — redirect to authors list.
if ( ! $trustbio_author_id ) {
	wp_safe_redirect( admin_url( 'admin.php?page=trustbio-authors' ) );
	exit;
}

$trustbio_profile_class = new TrustBio_Author_Profile();
$trustbio_edit_data     = $trustbio_profile_class->get_edit_data( $trustbio_author_id );
$trustbio_author        = $trustbio_edit_data['author'];

// Author not found — redirect.
if ( ! $trustbio_author ) {
	wp_safe_redirect( admin_url( 'admin.php?page=trustbio-authors' ) );
	exit;
}

$trustbio_field_values = $trustbio_edit_data['field_values'];
$trustbio_same_as      = $trustbio_edit_data['same_as'];
$trustbio_schema_job   = $trustbio_edit_data['schema_job'];

// Data for form selects.
$trustbio_categories    = trustbio_get_categories( true );
$trustbio_all_fields    = trustbio_get_all_fields();
$trustbio_social_fields = array();
$trustbio_text_fields   = array();

foreach ( $trustbio_all_fields as $trustbio_f ) {
	if ( $trustbio_f->is_social ) {
		$trustbio_social_fields[] = $trustbio_f;
	} else {
		$trustbio_text_fields[] = $trustbio_f;
	}
}

// Avatar URL for display.
$trustbio_avatar_url = trustbio_get_avatar_url( $trustbio_author, 120 );
$trustbio_author_url = trustbio_get_author_url( $trustbio_author );

// WP user display name if registered.
$trustbio_wp_user = null;
if ( 'registered' === $trustbio_author->author_type && $trustbio_author->wp_user_id ) {
	$trustbio_wp_user = get_userdata( (int) $trustbio_author->wp_user_id );
}

// Schema property options for reference.
$trustbio_schema_props = array(
	'author'      => 'author',
	'reviewedBy'  => 'reviewedBy',
	'contributor' => 'contributor',
	'editor'      => 'editor',
);

// Same-as authority fields (used for sameAs display note).
$trustbio_same_as_slugs = trustbio_get_same_as_field_slugs();
?>
<div class="trustbio-admin-wrap">

	<!-- ===== PAGE HEADER ===== -->
	<div class="tb-page-header">
		<div class="tb-page-header__left">
			<div class="tb-page-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
			</div>
			<div class="tb-page-header__text">
				<h1 class="tb-page-title">
					<?php
					printf(
						/* translators: %s: author display name */
						esc_html__( 'Edit Profile: %s', 'trustbio' ),
						esc_html( $trustbio_author->display_name )
					);
					?>
				</h1>
				<p class="tb-page-subtitle">
					<a href="<?php echo esc_url( $trustbio_author_url ); ?>" target="_blank" style="color:var(--tb-primary);">
						<?php echo esc_html( $trustbio_author_url ); ?>
					</a>
					&nbsp;·&nbsp;
					<span class="tb-badge <?php echo 'registered' === $trustbio_author->author_type ? 'tb-badge-info' : 'tb-badge-gray'; ?>">
						<?php echo 'registered' === $trustbio_author->author_type ? esc_html__( 'Registered User', 'trustbio' ) : esc_html__( 'Guest Author', 'trustbio' ); ?>
					</span>
				</p>
			</div>
		</div>
		<div class="tb-page-header__actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-authors' ) ); ?>" class="tb-btn tb-btn-secondary">
				<?php esc_html_e( '← All Authors', 'trustbio' ); ?>
			</a>
			<button type="button" id="tb-profile-save-btn" class="tb-btn tb-btn-primary">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Save Profile', 'trustbio' ); ?>
			</button>
		</div>
	</div>

	<div class="tb-notice-area" id="tb-notice-area"></div>

	<!-- ===== MAIN PROFILE FORM ===== -->
	<form id="tb-profile-form" novalidate>
		<input type="hidden" name="id" value="<?php echo esc_attr( $trustbio_author->id ); ?>">
		<input type="hidden" name="author_type" value="<?php echo esc_attr( $trustbio_author->author_type ); ?>">

		<div class="tb-profile-layout">

			<!-- ===== LEFT: Avatar Card + Mini Info ===== -->
			<div class="tb-profile-sidebar">

				<!-- Avatar card -->
				<div class="tb-card tb-profile-avatar-card">
					<div class="tb-profile-avatar-wrap">
						<div class="tb-profile-avatar-img-wrap" id="tb-avatar-display">
							<img src="<?php echo esc_url( $trustbio_avatar_url ); ?>" alt="<?php echo esc_attr( $trustbio_author->display_name ); ?>" id="tb-avatar-preview-img" width="120" height="120">
						</div>
						<div class="tb-profile-avatar-info">
							<strong class="tb-profile-name"><?php echo esc_html( $trustbio_author->display_name ); ?></strong>
							<?php if ( $trustbio_author->job_title ) : ?>
								<p class="tb-profile-job"><?php echo esc_html( $trustbio_author->job_title ); ?></p>
							<?php endif; ?>
							<?php if ( $trustbio_wp_user ) : ?>
								<p class="tb-profile-wp-user">
									<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
									<?php echo esc_html( $trustbio_wp_user->display_name ); ?>
								</p>
							<?php endif; ?>
						</div>
					</div>

					<hr class="tb-divider">

					<div style="display:flex;flex-direction:column;gap:8px;">
						<button type="button" class="tb-btn tb-btn-secondary tb-btn-sm tb-upload-avatar-btn" data-preview="#tb-avatar-preview-img" data-input="#tb-avatar-attach-id">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
							<?php esc_html_e( 'Upload Photo', 'trustbio' ); ?>
						</button>
						<button type="button" class="tb-btn tb-btn-ghost tb-btn-sm tb-remove-avatar-btn" id="tb-remove-avatar" data-preview="#tb-avatar-preview-img" data-input="#tb-avatar-attach-id" <?php echo $trustbio_author->avatar_attach_id ? '' : 'style="display:none;"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded safe HTML attribute ?>>
							<?php esc_html_e( 'Remove Photo', 'trustbio' ); ?>
						</button>
						<p class="tb-input-hint" style="margin:0;"><?php esc_html_e( 'Falls back to Gravatar if no photo is uploaded.', 'trustbio' ); ?></p>
					</div>

					<input type="hidden" id="tb-avatar-attach-id" name="avatar_attach_id" value="<?php echo esc_attr( $trustbio_author->avatar_attach_id ?? '' ); ?>">
					<input type="hidden" name="avatar_url" id="tb-avatar-url-field" value="<?php echo esc_attr( $trustbio_author->avatar_url ?? '' ); ?>">
				</div>

				<!-- Quick stats card -->
				<div class="tb-card">
					<div class="tb-card-body tb-card-body--compact">
						<p class="tb-section-label" style="margin-bottom:12px;"><?php esc_html_e( 'Author Info', 'trustbio' ); ?></p>
						<div class="tb-profile-meta-list">
							<div class="tb-profile-meta-item">
								<span class="tb-profile-meta-label"><?php esc_html_e( 'Slug', 'trustbio' ); ?></span>
								<span class="tb-text-mono" id="tb-author-slug-display"><?php echo esc_html( $trustbio_author->slug ); ?></span>
							</div>
							<div class="tb-profile-meta-item">
								<span class="tb-profile-meta-label"><?php esc_html_e( 'Type', 'trustbio' ); ?></span>
								<span><?php echo 'registered' === $trustbio_author->author_type ? esc_html__( 'Registered', 'trustbio' ) : esc_html__( 'Guest', 'trustbio' ); ?></span>
							</div>
							<?php if ( $trustbio_author->category_id ) :
								$trustbio_cat = trustbio_get_category( (int) $trustbio_author->category_id );
								if ( $trustbio_cat ) : ?>
							<div class="tb-profile-meta-item">
								<span class="tb-profile-meta-label"><?php esc_html_e( 'Category', 'trustbio' ); ?></span>
								<span><?php echo esc_html( $trustbio_cat->singular ); ?></span>
							</div>
							<?php endif; endif; ?>
							<div class="tb-profile-meta-item">
								<span class="tb-profile-meta-label"><?php esc_html_e( 'Status', 'trustbio' ); ?></span>
								<span class="tb-badge <?php echo esc_attr( $trustbio_author->is_active ? 'tb-badge-success' : 'tb-badge-danger' ); ?>">
									<?php echo $trustbio_author->is_active ? esc_html__( 'Active', 'trustbio' ) : esc_html__( 'Inactive', 'trustbio' ); ?>
								</span>
							</div>
							<?php
							$trustbio_sidebar_creds = TrustBio_Credentials::get_for_author( $trustbio_author->id, true );
							if ( ! empty( $trustbio_sidebar_creds ) ) :
								$trustbio_sidebar_badge_types = TrustBio_Credentials::get_badge_types();
							?>
							<div class="tb-profile-meta-item" style="flex-direction:column;align-items:flex-start;gap:6px;">
								<span class="tb-profile-meta-label"><?php esc_html_e( 'Credentials', 'trustbio' ); ?></span>
								<div style="display:flex;flex-wrap:wrap;gap:4px;">
									<?php foreach ( $trustbio_sidebar_creds as $trustbio_sc ) :
										$trustbio_sc_data = $trustbio_sidebar_badge_types[ $trustbio_sc->badge_type ] ?? $trustbio_sidebar_badge_types['custom'];
										$trustbio_sc_label = $trustbio_sc->badge_type !== 'custom' ? strtoupper( $trustbio_sc->badge_type ) : strtoupper( substr( $trustbio_sc->credential_name, 0, 5 ) );
									?>
									<span style="display:inline-flex;align-items:center;background:<?php echo esc_attr( $trustbio_sc_data['color'] ); ?>;color:<?php echo esc_attr( $trustbio_sc_data['text'] ); ?>;font-size:9px;font-weight:700;padding:2px 6px;border-radius:3px;letter-spacing:.05em;text-transform:uppercase;" title="<?php echo esc_attr( $trustbio_sc->credential_name ); ?>"><?php echo esc_html( $trustbio_sc_label ); ?></span>
									<?php endforeach; ?>
								</div>
							</div>
							<?php endif; ?>
							<div class="tb-profile-meta-item">
								<span class="tb-profile-meta-label"><?php esc_html_e( 'Added', 'trustbio' ); ?></span>
								<span><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $trustbio_author->created_at ) ) ); ?></span>
							</div>
						</div>
					</div>
				</div>

			</div><!-- .tb-profile-sidebar -->

			<!-- ===== RIGHT: Tabbed Form ===== -->
			<div class="tb-profile-main" data-tabs-wrap="profile-tabs">

				<!-- Tab navigation -->
				<div class="tb-card" id="tb-profile-tabs-card" style="overflow:visible;">
					<div class="tb-tabs" style="padding:0 4px 0;">
						<button type="button" class="tb-tab is-active" data-tab="general">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
							<?php esc_html_e( 'General', 'trustbio' ); ?>
						</button>
						<button type="button" class="tb-tab" data-tab="social">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"/></svg>
							<?php esc_html_e( 'Social Profiles', 'trustbio' ); ?>
						</button>
						<button type="button" class="tb-tab" data-tab="seo">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/></svg>
							<?php esc_html_e( 'SEO & Schema', 'trustbio' ); ?>
						</button>
						<button type="button" class="tb-tab" data-tab="credentials">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
							<?php esc_html_e( 'Credentials', 'trustbio' ); ?>
							<?php
							$trustbio_cred_count = count( TrustBio_Credentials::get_for_author( $trustbio_author->id ) );
							if ( $trustbio_cred_count > 0 ) :
							?>
							<span class="tb-tab-badge"><?php echo esc_html( $trustbio_cred_count ); ?></span>
							<?php endif; ?>
						</button>
						<button type="button" class="tb-tab" data-tab="expertise">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>
							<?php esc_html_e( 'Expertise', 'trustbio' ); ?>
						</button>
					</div>

					<!-- ── TAB 1: GENERAL ── -->
					<div class="tb-tab-pane is-active" data-tab-pane="general" style="padding:24px;">

						<div class="tb-form-grid-2">

							<div class="tb-form-group">
								<label class="tb-label" for="tb-display-name"><?php esc_html_e( 'Display Name', 'trustbio' ); ?> <span class="required">*</span></label>
								<input type="text" id="tb-display-name" name="display_name" class="tb-input" value="<?php echo esc_attr( $trustbio_author->display_name ); ?>" required>
							</div>

							<div class="tb-form-group">
								<label class="tb-label" for="tb-author-category"><?php esc_html_e( 'Default Author Category', 'trustbio' ); ?></label>
								<select id="tb-author-category" name="category_id" class="tb-select">
									<option value=""><?php esc_html_e( '— None —', 'trustbio' ); ?></option>
									<?php foreach ( $trustbio_categories as $trustbio_cat ) : ?>
										<option value="<?php echo esc_attr( $trustbio_cat->id ); ?>" <?php selected( $trustbio_author->category_id, $trustbio_cat->id ); ?>>
											<?php echo esc_html( $trustbio_cat->singular ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="tb-input-hint"><?php esc_html_e( 'Default category used when this author is added to a new post.', 'trustbio' ); ?></p>
							</div>

							<div class="tb-form-group">
								<label class="tb-label" for="tb-email"><?php esc_html_e( 'Email', 'trustbio' ); ?></label>
								<input type="email" id="tb-email" name="email" class="tb-input" value="<?php echo esc_attr( $trustbio_author->email ?? '' ); ?>" placeholder="author@example.com">
								<p class="tb-input-hint"><?php esc_html_e( 'Used for Gravatar if no custom photo is uploaded.', 'trustbio' ); ?></p>
							</div>

							<div class="tb-form-group">
								<label class="tb-label" for="tb-website"><?php esc_html_e( 'Website', 'trustbio' ); ?></label>
								<input type="url" id="tb-website" name="website" class="tb-input" value="<?php echo esc_attr( $trustbio_author->website ?? '' ); ?>" placeholder="https://">
							</div>

							<div class="tb-form-group">
								<label class="tb-label" for="tb-job-title"><?php esc_html_e( 'Job Title', 'trustbio' ); ?></label>
								<input type="text" id="tb-job-title" name="job_title" class="tb-input" value="<?php echo esc_attr( $trustbio_author->job_title ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. MD, Addiction Medicine', 'trustbio' ); ?>">
							</div>

							<div class="tb-form-group">
								<label class="tb-label" for="tb-credentials"><?php esc_html_e( 'Credentials', 'trustbio' ); ?></label>
								<input type="text" id="tb-credentials" name="credentials" class="tb-input" value="<?php echo esc_attr( $trustbio_author->credentials ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. MD, PhD, LCSW', 'trustbio' ); ?>">
							</div>

						</div>

						<div class="tb-form-group" style="margin-top:8px;">
							<label class="tb-label" for="tb-bio"><?php esc_html_e( 'Biographical Info', 'trustbio' ); ?></label>
							<textarea id="tb-bio" name="bio" class="tb-textarea" rows="7" placeholder="<?php esc_attr_e( 'Write a short biography about this author…', 'trustbio' ); ?>"><?php echo esc_textarea( $trustbio_author->bio ?? '' ); ?></textarea>
							<p class="tb-input-hint"><?php esc_html_e( 'HTML is allowed. Shown below the author name in the author box.', 'trustbio' ); ?></p>
						</div>

						<?php if ( ! empty( $trustbio_text_fields ) ) : ?>
						<hr class="tb-divider">
						<p class="tb-section-label"><?php esc_html_e( 'Additional Fields', 'trustbio' ); ?></p>
						<div class="tb-form-grid-2">
							<?php foreach ( $trustbio_text_fields as $trustbio_field ) : ?>
							<div class="tb-form-group">
								<label class="tb-label" for="tb-f-<?php echo esc_attr( $trustbio_field->slug ); ?>"><?php echo esc_html( $trustbio_field->label ); ?></label>
								<?php if ( 'textarea' === $trustbio_field->field_type ) : ?>
									<textarea id="tb-f-<?php echo esc_attr( $trustbio_field->slug ); ?>" name="fields[<?php echo esc_attr( $trustbio_field->slug ); ?>]" class="tb-textarea" rows="3" placeholder="<?php echo esc_attr( $trustbio_field->placeholder ?? '' ); ?>"><?php echo esc_textarea( $trustbio_field_values[ $trustbio_field->slug ] ?? '' ); ?></textarea>
								<?php else : ?>
									<input type="text" id="tb-f-<?php echo esc_attr( $trustbio_field->slug ); ?>" name="fields[<?php echo esc_attr( $trustbio_field->slug ); ?>]" class="tb-input" value="<?php echo esc_attr( $trustbio_field_values[ $trustbio_field->slug ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $trustbio_field->placeholder ?? '' ); ?>">
								<?php endif; ?>
							</div>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>

					</div><!-- /tab general -->

					<!-- ── TAB 2: SOCIAL PROFILES ── -->
					<div class="tb-tab-pane" data-tab-pane="social" style="padding:24px;">

						<?php if ( empty( $trustbio_social_fields ) ) : ?>
							<div class="tb-empty">
								<p class="tb-empty__title"><?php esc_html_e( 'No social fields active', 'trustbio' ); ?></p>
								<p class="tb-empty__text">
									<?php
									printf(
										/* translators: %s: link to Author Fields page */
										esc_html__( 'Go to %s to activate social profile fields.', 'trustbio' ),
										'<a href="' . esc_url( admin_url( 'admin.php?page=trustbio-fields' ) ) . '">' . esc_html__( 'Author Fields', 'trustbio' ) . '</a>'
									);
									?>
								</p>
							</div>
						<?php else : ?>

						<p style="font-size:13px;color:var(--tb-gray-600);margin-bottom:24px;">
							<?php esc_html_e( 'Social profile URLs marked with ★ are used as sameAs authority signals in JSON-LD schema output.', 'trustbio' ); ?>
						</p>

						<div class="tb-social-fields-grid">
							<?php foreach ( $trustbio_social_fields as $trustbio_field ) :
								$trustbio_is_sameas = in_array( $trustbio_field->slug, $trustbio_same_as_slugs, true );
								$trustbio_val       = $trustbio_field_values[ $trustbio_field->slug ] ?? '';
							?>
							<div class="tb-social-field-item <?php echo esc_attr( $trustbio_val ? 'has-value' : '' ); ?>">
								<label class="tb-label" for="tb-sf-<?php echo esc_attr( $trustbio_field->slug ); ?>">
									<?php echo esc_html( $trustbio_field->label ); ?>
									<?php if ( $trustbio_is_sameas ) : ?>
										<span class="tb-sameas-star" title="<?php esc_attr_e( 'Used in schema sameAs', 'trustbio' ); ?>">★</span>
									<?php endif; ?>
								</label>
								<div class="tb-input-group">
									<svg class="tb-input-group__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/></svg>
									<input
										type="url"
										id="tb-sf-<?php echo esc_attr( $trustbio_field->slug ); ?>"
										name="fields[<?php echo esc_attr( $trustbio_field->slug ); ?>]"
										class="tb-input"
										value="<?php echo esc_attr( $trustbio_val ); ?>"
										placeholder="<?php echo esc_attr( $trustbio_field->placeholder ?? 'https://' ); ?>"
									>
								</div>
							</div>
							<?php endforeach; ?>
						</div>

						<?php endif; ?>

					</div><!-- /tab social -->

					<!-- ── TAB 3: SEO & SCHEMA ── -->
					<div class="tb-tab-pane" data-tab-pane="seo" style="padding:24px;">

						<div class="tb-seo-info-banner">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>
							<div>
								<strong><?php esc_html_e( 'How E-E-A-T Schema Works', 'trustbio' ); ?></strong>
								<p><?php esc_html_e( 'TrustBio auto-generates JSON-LD structured data for this author on every post they are assigned to. Social profile URLs from the Social Profiles tab are automatically added to the sameAs array. The fields below give you additional control.', 'trustbio' ); ?></p>
							</div>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-schema-job"><?php esc_html_e( 'Schema jobTitle Override', 'trustbio' ); ?></label>
							<input type="text" id="tb-schema-job" name="schema_job_title" class="tb-input" value="<?php echo esc_attr( $trustbio_schema_job ); ?>" placeholder="<?php echo esc_attr( $trustbio_author->job_title ?? '' ); ?>">
							<p class="tb-input-hint"><?php esc_html_e( 'Override the job title used in JSON-LD schema only. Leave blank to use the Job Title from the General tab.', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-same-as"><?php esc_html_e( 'Additional sameAs URLs', 'trustbio' ); ?></label>
							<textarea id="tb-same-as" name="same_as_urls" class="tb-textarea" rows="5" placeholder="https://en.wikipedia.org/wiki/Author-Name&#10;https://scholar.google.com/..."><?php echo esc_textarea( $trustbio_same_as ); ?></textarea>
							<p class="tb-input-hint"><?php esc_html_e( 'One URL per line. Added to the schema sameAs array alongside social profile URLs. Use for Wikipedia, Google Knowledge Panel, academic profiles, etc.', 'trustbio' ); ?></p>
						</div>

						<hr class="tb-divider">

						<div class="tb-schema-preview-box">
							<p class="tb-section-label"><?php esc_html_e( 'Schema Preview (read-only)', 'trustbio' ); ?></p>
							<div class="tb-schema-code">
								<pre id="tb-schema-preview"><?php
								// Build a preview of what the schema will look like.
								$trustbio_schema_preview = array(
									'@type'    => 'Person',
									'name'     => $trustbio_author->display_name,
									'url'      => trustbio_get_author_url( $trustbio_author ),
									'jobTitle' => $trustbio_schema_job ?: ( $trustbio_author->job_title ?? '' ),
									'sameAs'   => array_values( array_filter( array_map( 'esc_url_raw', array_filter( array_map( 'trim',
										array_merge(
											array_values( array_intersect_key(
												$trustbio_field_values,
												array_flip( $trustbio_same_as_slugs )
											) ),
											explode( "\n", $trustbio_same_as )
										)
									) ) ) ) ),
								);
								echo esc_html( wp_json_encode( $trustbio_schema_preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
								?></pre>
							</div>
							<a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer" class="tb-btn tb-btn-ghost tb-btn-sm" style="margin-top:10px;">
								<?php esc_html_e( 'Test with Google Rich Results ↗', 'trustbio' ); ?>
							</a>
						</div>

					</div><!-- /tab seo -->


				</form><!-- /tb-profile-form -->
					<!-- ── TAB 4: CREDENTIALS ── -->
					<div class="tb-tab-pane" data-tab-pane="credentials" style="padding:24px;display:none;">

						<?php
						$trustbio_tab_creds    = TrustBio_Credentials::get_for_author( $trustbio_author->id );
						$trustbio_tab_badges   = TrustBio_Credentials::get_badge_types();
						?>

						<div class="tb-cred-tab-layout">
							<!-- Add form -->
							<div class="tb-cred-tab-form">
								<p class="tb-section-label" style="margin-bottom:14px;"><?php esc_html_e( 'ADD CREDENTIAL', 'trustbio' ); ?></p>
								<form id="tb-inline-cred-form" novalidate>
									<input type="hidden" name="cred_id" value="0">
									<input type="hidden" name="author_id" value="<?php echo esc_attr( $trustbio_author->id ); ?>">
									<div class="tb-form-group">
										<label class="tb-label"><?php esc_html_e( 'Badge Type', 'trustbio' ); ?></label>
										<select id="tb-inline-badge-type" name="badge_type" class="tb-select">
											<?php foreach ( $trustbio_tab_badges as $trustbio_btk => $trustbio_btd ) : ?>
											<option value="<?php echo esc_attr( $trustbio_btk ); ?>"><?php echo esc_html( strtoupper( $trustbio_btk ) . ' — ' . $trustbio_btd['description'] ); ?></option>
											<?php endforeach; ?>
										</select>
										<div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
											<span style="font-size:11px;color:var(--tb-gray-500);"><?php esc_html_e( 'Preview:', 'trustbio' ); ?></span>
											<span id="tb-inline-badge-preview" style="display:inline-flex;align-items:center;gap:3px;background:#1e40af;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;letter-spacing:.05em;text-transform:uppercase;">MD</span>
										</div>
									</div>
									<div class="tb-form-group">
										<label class="tb-label"><?php esc_html_e( 'Credential Name', 'trustbio' ); ?> *</label>
										<input type="text" name="credential_name" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. Board Certified in Addiction Medicine', 'trustbio' ); ?>" required>
									</div>
									<div class="tb-form-group">
										<label class="tb-label"><?php esc_html_e( 'Issuing Body', 'trustbio' ); ?></label>
										<input type="text" name="issuing_body" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. American Board of Addiction Medicine', 'trustbio' ); ?>">
									</div>
									<div class="tb-form-group">
										<label class="tb-label"><?php esc_html_e( 'Verification URL', 'trustbio' ); ?></label>
										<input type="url" name="verification_url" class="tb-input" placeholder="https://verify.example.org/lookup">
									</div>
									<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
										<div class="tb-form-group">
											<label class="tb-label"><?php esc_html_e( 'Issue Date', 'trustbio' ); ?></label>
											<input type="date" name="issue_date" class="tb-input">
										</div>
										<div class="tb-form-group">
											<label class="tb-label"><?php esc_html_e( 'Expiry Date', 'trustbio' ); ?></label>
											<input type="date" name="expiry_date" class="tb-input">
										</div>
									</div>
									<div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">
										<label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
											<label class="tb-toggle"><input type="checkbox" name="show_badge" value="1" id="tb-inline-show-badge" checked><span class="tb-toggle__track"></span></label>
											<?php esc_html_e( 'Show badge', 'trustbio' ); ?>
										</label>
										<label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
											<label class="tb-toggle"><input type="checkbox" name="show_in_schema" value="1" id="tb-inline-show-schema" checked><span class="tb-toggle__track"></span></label>
											<?php esc_html_e( 'Include in schema', 'trustbio' ); ?>
										</label>
									</div>
									<div style="display:flex;gap:10px;">
										<button type="submit" id="tb-inline-cred-submit" class="tb-btn tb-btn-primary tb-btn-sm"><?php esc_html_e( 'Add Credential', 'trustbio' ); ?></button>
										<button type="reset" id="tb-inline-cred-reset" class="tb-btn tb-btn-ghost tb-btn-sm"><?php esc_html_e( 'Clear', 'trustbio' ); ?></button>
									</div>
								</form>
							</div>

							<!-- Existing credentials list -->
							<div class="tb-cred-tab-list">
								<p class="tb-section-label" style="margin-bottom:14px;"><?php esc_html_e( 'EXISTING CREDENTIALS', 'trustbio' ); ?></p>
								<div id="tb-inline-creds-list">
								<?php if ( empty( $trustbio_tab_creds ) ) : ?>
									<p style="font-size:13px;color:var(--tb-gray-400);font-style:italic;"><?php esc_html_e( 'No credentials added yet. Use the form to add the first one.', 'trustbio' ); ?></p>
								<?php else : ?>
									<?php foreach ( $trustbio_tab_creds as $trustbio_tc ) :
										$trustbio_tc_badge = $trustbio_tab_badges[ $trustbio_tc->badge_type ] ?? $trustbio_tab_badges['custom'];
										$trustbio_tc_label = $trustbio_tc->badge_type !== 'custom' ? strtoupper( $trustbio_tc->badge_type ) : strtoupper( substr( $trustbio_tc->credential_name, 0, 5 ) );
										$trustbio_tc_expired = ! empty( $trustbio_tc->expiry_date ) && strtotime( $trustbio_tc->expiry_date ) < time();
									?>
									<div class="tb-inline-cred-row<?php echo $trustbio_tc_expired ? ' tb-cred-expired' : ''; ?>" id="tb-inline-cred-<?php echo esc_attr( $trustbio_tc->id ); ?>">
										<span style="display:inline-flex;align-items:center;gap:3px;background:<?php echo esc_attr( $trustbio_tc_badge['color'] ); ?>;color:<?php echo esc_attr( $trustbio_tc_badge['text'] ); ?>;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;letter-spacing:.05em;text-transform:uppercase;flex-shrink:0;"><?php echo esc_html( $trustbio_tc_label ); ?></span>
										<div class="tb-inline-cred-info">
											<strong><?php echo esc_html( $trustbio_tc->credential_name ); ?></strong>
											<?php if ( $trustbio_tc->issuing_body ) : ?><span><?php echo esc_html( $trustbio_tc->issuing_body ); ?></span><?php endif; ?>
											<?php if ( $trustbio_tc_expired ) : ?><span class="tb-badge tb-badge-danger" style="font-size:9px;"><?php esc_html_e( 'Expired', 'trustbio' ); ?></span><?php endif; ?>
										</div>
										<button type="button" class="tb-btn tb-btn-ghost tb-btn-sm tb-inline-delete-cred" data-id="<?php echo esc_attr( $trustbio_tc->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_delete_credential' ) ); ?>" style="margin-left:auto;flex-shrink:0;" title="<?php esc_attr_e( 'Delete', 'trustbio' ); ?>">
											<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;color:var(--tb-danger);"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
										</button>
									</div>
									<?php endforeach; ?>
								<?php endif; ?>
								</div>
							</div>
						</div>

					</div><!-- /tab: credentials -->

					<!-- ── TAB 5: EXPERTISE TOPICS ── -->
					<div class="tb-tab-pane" data-tab-pane="expertise" style="padding:24px;display:none;">
						<?php
						$trustbio_expertise_ids  = TrustBio_Author_Expertise::get_expertise( $trustbio_author->id );
						$trustbio_all_categories = get_categories( array( 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
						?>
						<p style="margin:0 0 16px;color:var(--tb-gray-600);font-size:13px;">
							<?php esc_html_e( 'Select the topic areas this author specialises in. Checked categories appear as knowsAbout in JSON-LD schema, signalling E-E-A-T expertise to search engines and AI engines.', 'trustbio' ); ?>
						</p>

						<?php if ( empty( $trustbio_all_categories ) ) : ?>
							<p class="tb-muted"><?php esc_html_e( 'No categories found. Create some WordPress categories first.', 'trustbio' ); ?></p>
						<?php else : ?>
							<div id="tb-expertise-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;margin-bottom:20px;">
								<?php foreach ( $trustbio_all_categories as $trustbio_exp_cat ) : ?>
									<label style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--tb-gray-200);border-radius:6px;cursor:pointer;font-size:13px;background:#fff;">
										<input type="checkbox"
											name="trustbio_expertise_cats[]"
											value="<?php echo esc_attr( $trustbio_exp_cat->term_id ); ?>"
											<?php checked( in_array( (int) $trustbio_exp_cat->term_id, $trustbio_expertise_ids, true ) ); ?>
											style="margin:0;">
										<?php echo esc_html( $trustbio_exp_cat->name ); ?>
										<?php if ( $trustbio_exp_cat->count > 0 ) : ?>
											<span style="margin-left:auto;background:var(--tb-gray-100);color:var(--tb-gray-500);font-size:11px;padding:1px 6px;border-radius:99px;"><?php echo esc_html( $trustbio_exp_cat->count ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</div>
							<div style="display:flex;gap:8px;align-items:center;">
								<button type="button" id="tb-save-expertise-btn" class="tb-btn tb-btn-primary"
									data-author-id="<?php echo esc_attr( $trustbio_author->id ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_save_expertise' ) ); ?>">
									<?php esc_html_e( 'Save Expertise Topics', 'trustbio' ); ?>
								</button>
								<span id="tb-expertise-status" style="font-size:13px;"></span>
							</div>
						<?php endif; ?>
					</div><!-- /tab: expertise -->

				</div><!-- .tb-card (tabs) -->

			</div><!-- .tb-profile-main -->

		</div><!-- .tb-profile-layout -->

		<!-- ===== STICKY SAVE BAR ===== -->
		<div class="tb-save-bar">
			<span class="tb-save-bar__status" id="tb-save-status"></span>
			<div class="tb-save-bar__actions">
				<a href="<?php echo esc_url( $trustbio_author_url ); ?>" target="_blank" class="tb-btn tb-btn-secondary tb-btn-sm">
					<?php esc_html_e( 'View Profile ↗', 'trustbio' ); ?>
				</a>
				<button type="submit" id="tb-save-bar-btn" class="tb-btn tb-btn-primary">
					<?php esc_html_e( 'Save Profile', 'trustbio' ); ?>
				</button>
			</div>
		</div>


</div><!-- .trustbio-admin-wrap -->


