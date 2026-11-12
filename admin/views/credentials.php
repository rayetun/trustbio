<?php
/**
 * Admin view: Credential Manager
 * Accessible from the Author Profile page and the standalone Credentials menu.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$trustbio_author_id = isset( $_GET['author_id'] ) ? absint( $_GET['author_id'] ) : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$trustbio_cred_class  = new TrustBio_Credentials();
$trustbio_badge_types = TrustBio_Credentials::get_badge_types();
$trustbio_author      = $trustbio_author_id ? trustbio_get_author( $trustbio_author_id ) : null;

// Get all authors for the selector (when no author_id in URL).
$trustbio_all_authors = array();
if ( ! $trustbio_author_id ) {
	global $wpdb;
	$trustbio_at = $wpdb->prefix . 'trustbio_authors';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$trustbio_all_authors = (array) $wpdb->get_results( "SELECT id, display_name FROM `{$trustbio_at}` WHERE is_active = 1 ORDER BY display_name ASC" );
}

$trustbio_credentials = $trustbio_author_id
	? TrustBio_Credentials::get_for_author( $trustbio_author_id )
	: array();
?>

<div class="trustbio-admin-wrap">

	<div class="tb-page-header">
		<div class="tb-page-header__left">
			<div class="tb-page-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
			</div>
			<div class="tb-page-header__text">
				<h1 class="tb-page-title"><?php esc_html_e( 'Credential Manager', 'trustbio' ); ?></h1>
				<p class="tb-page-subtitle">
					<?php if ( $trustbio_author ) : ?>
						<?php
						printf(
							/* translators: %s: author display name */
							esc_html__( 'Managing credentials for %s', 'trustbio' ),
							'<strong>' . esc_html( $trustbio_author->display_name ) . '</strong>'
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'Manage professional credentials and verification badges for your authors.', 'trustbio' ); ?>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<div class="tb-page-header__actions">
			<?php if ( $trustbio_author_id ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-profile&id=' . $trustbio_author_id ) ); ?>" class="tb-btn tb-btn-secondary">
					<?php esc_html_e( '← Back to Profile', 'trustbio' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="tb-notice-area" id="tb-notice-area"></div>

	<?php if ( ! $trustbio_author_id ) : ?>
	<!-- No author selected — show author picker -->
	<div class="tb-content-area">
		<div class="tb-card">
			<?php if ( empty( $trustbio_all_authors ) ) : ?>
			<div class="tb-card-body">
				<div class="tb-empty">
					<svg class="tb-empty__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
					<p class="tb-empty__title"><?php esc_html_e( 'No authors yet', 'trustbio' ); ?></p>
					<p class="tb-empty__text"><?php esc_html_e( 'Credentials belong to an author, so add your first author and you can attach their qualifications here.', 'trustbio' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-authors&action=new' ) ); ?>" class="tb-btn tb-btn-primary"><?php esc_html_e( 'Add your first author', 'trustbio' ); ?></a>
				</div>
			</div>
			<?php else : ?>
			<div class="tb-card-body tb-card-body--narrow">
				<p class="tb-section-label"><?php esc_html_e( 'Select an Author', 'trustbio' ); ?></p>
				<p style="font-size:13px;color:var(--tb-gray-600);margin-bottom:16px;">
					<?php esc_html_e( 'Choose an author to manage their professional credentials.', 'trustbio' ); ?>
				</p>
				<div style="display:flex;gap:10px;align-items:center;">
					<select id="tb-author-picker" class="tb-select" style="flex:1;">
						<option value=""><?php esc_html_e( '— Select Author —', 'trustbio' ); ?></option>
						<?php foreach ( $trustbio_all_authors as $trustbio_a ) : ?>
							<option value="<?php echo esc_attr( $trustbio_a->id ); ?>"><?php echo esc_html( $trustbio_a->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" id="tb-go-to-author" class="tb-btn tb-btn-primary"><?php esc_html_e( 'Manage Credentials', 'trustbio' ); ?></button>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php else : ?>

	<div class="tb-layout-split">

		<!-- LEFT: Add Credential Form -->
		<div>
			<div class="tb-card">
				<div class="tb-card-header">
					<h2 class="tb-card-title"><?php esc_html_e( 'Add Credential', 'trustbio' ); ?></h2>
				</div>
				<div class="tb-card-body">
					<form id="tb-credential-form" novalidate>
						<input type="hidden" name="id" value="0">
						<input type="hidden" name="author_id" value="<?php echo esc_attr( $trustbio_author_id ); ?>">

						<div class="tb-form-group">
							<label class="tb-label" for="tb-badge-type"><?php esc_html_e( 'Badge Type', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'Professional: formal qualifications and board certifications. Certification: course completions, certificates. Award: industry recognitions. Membership: professional body memberships.', 'trustbio' ) ); ?></label>
							<select id="tb-badge-type" name="badge_type" class="tb-select">
								<?php foreach ( $trustbio_badge_types as $trustbio_type_key => $trustbio_type_data ) : ?>
									<option value="<?php echo esc_attr( $trustbio_type_key ); ?>">
										<?php echo esc_html( strtoupper( $trustbio_type_key ) . ' — ' . $trustbio_type_data['description'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<!-- Badge preview -->
							<div id="tb-badge-preview" style="margin-top:10px;display:flex;align-items:center;gap:8px;">
								<span style="font-size:12px;color:var(--tb-gray-500);"><?php esc_html_e( 'Preview:', 'trustbio' ); ?></span>
								<span id="tb-badge-preview-pill" style="display:inline-flex;align-items:center;gap:3px;background:#1e40af;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;letter-spacing:.05em;text-transform:uppercase;">MD</span>
							</div>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-credential-name"><?php esc_html_e( 'Credential Name', 'trustbio' ); ?> <span class="required">*</span><?php TrustBio_Help_Tabs::tip( __( 'The formal name of the qualification — e.g. Registered Dietitian (RD), Certified Financial Planner (CFP). Use the official abbreviation where one exists.', 'trustbio' ) ); ?></label>
							<input type="text" id="tb-credential-name" name="credential_name" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. Board Certified in Addiction Medicine', 'trustbio' ); ?>" required>
							<p class="tb-input-hint"><?php esc_html_e( 'Full name of the credential. Shown in schema and on hover.', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-issuing-body"><?php esc_html_e( 'Issuing Body / Organization', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'The organisation that awarded this credential — e.g. American Board of Internal Medicine. Included in schema output to add credibility.', 'trustbio' ) ); ?></label>
							<input type="text" id="tb-issuing-body" name="issuing_body" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. American Board of Addiction Medicine', 'trustbio' ); ?>">
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-license-number"><?php esc_html_e( 'License / Certificate Number', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'Optional. The official license or certificate number. Not displayed publicly — stored for your records and schema output only.', 'trustbio' ) ); ?></label>
							<input type="text" id="tb-license-number" name="license_number" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. 12345 (optional, kept private)', 'trustbio' ); ?>">
							<p class="tb-input-hint"><?php esc_html_e( 'Stored securely, never displayed publicly.', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-grid-2">
							<div class="tb-form-group">
								<label class="tb-label" for="tb-issue-date"><?php esc_html_e( 'Issue Date', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'When this credential was awarded. Included in schema as the startDate. Leave blank if the issue date is not known.', 'trustbio' ) ); ?></label>
								<input type="date" id="tb-issue-date" name="issue_date" class="tb-input">
							</div>
							<div class="tb-form-group">
								<label class="tb-label" for="tb-expiry-date"><?php esc_html_e( 'Expiry Date', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'Leave blank for credentials that do not expire (e.g. a university degree). Expired credentials are automatically excluded from E-E-A-T scores and schema output.', 'trustbio' ) ); ?></label>
								<input type="date" id="tb-expiry-date" name="expiry_date" class="tb-input">
								<p class="tb-input-hint"><?php esc_html_e( 'Expired credentials are hidden from the frontend automatically.', 'trustbio' ); ?></p>
							</div>
						</div>

						<div class="tb-form-group">
							<label class="tb-label" for="tb-verification-url"><?php esc_html_e( 'Verification URL', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'A public URL where this credential can be verified online — e.g. a state medical board lookup page. Included in schema as url on the credential.', 'trustbio' ) ); ?></label>
							<input type="url" id="tb-verification-url" name="verification_url" class="tb-input" placeholder="https://verify.abam.net/lookup">
							<p class="tb-input-hint"><?php esc_html_e( 'Link to a public verification page (license lookup, board certification portal, etc.). Badge becomes clickable.', 'trustbio' ); ?></p>
						</div>

						<div class="tb-form-group">
							<label class="tb-label"><?php esc_html_e( 'Display Options', 'trustbio' ); ?><?php TrustBio_Help_Tabs::tip( __( 'Show Badge: display this credential as a visible badge in the author box. Show in Schema: include it in JSON-LD structured data. You can mix these independently.', 'trustbio' ) ); ?></label>
							<div style="display:flex;flex-direction:column;gap:10px;">
								<div class="tb-toggle-wrap">
									<label class="tb-toggle">
										<input type="checkbox" name="show_badge" id="tb-show-badge" value="1" checked>
										<span class="tb-toggle__track"></span>
									</label>
									<span class="tb-toggle-label"><?php esc_html_e( 'Show badge beside author name in author boxes', 'trustbio' ); ?></span>
								</div>
								<div class="tb-toggle-wrap">
									<label class="tb-toggle">
										<input type="checkbox" name="show_in_schema" id="tb-show-schema" value="1" checked>
										<span class="tb-toggle__track"></span>
									</label>
									<span class="tb-toggle-label"><?php esc_html_e( 'Include in JSON-LD schema (hasCredential)', 'trustbio' ); ?></span>
								</div>
							</div>
						</div>

						<hr class="tb-divider">
						<div style="display:flex;gap:10px;">
							<button type="submit" id="tb-cred-submit" class="tb-btn tb-btn-primary"><?php esc_html_e( 'Add Credential', 'trustbio' ); ?></button>
							<button type="reset" class="tb-btn tb-btn-ghost" id="tb-cred-reset"><?php esc_html_e( 'Clear', 'trustbio' ); ?></button>
						</div>
					</form>
				</div>
			</div>

			<!-- Info card -->
			<div class="tb-card tb-cred-info-card">
				<div class="tb-card-body">
					<p class="tb-section-label" style="margin-bottom:10px;"><?php esc_html_e( 'How Credentials Work', 'trustbio' ); ?></p>
					<ul class="tb-cred-info-list">
						<li>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
							<?php esc_html_e( 'Badges appear inline beside the author name in the box.', 'trustbio' ); ?>
						</li>
						<li>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
							<?php esc_html_e( 'Set a verification URL to make the badge clickable — links to the official license lookup page.', 'trustbio' ); ?>
						</li>
						<li>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
							<?php esc_html_e( 'Expired credentials are automatically hidden on the frontend.', 'trustbio' ); ?>
						</li>
						<li>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
							<?php esc_html_e( 'Schema credentials output as hasCredential in JSON-LD — a direct E-E-A-T signal for Google.', 'trustbio' ); ?>
						</li>
					</ul>
				</div>
			</div>
		</div>

		<!-- RIGHT: Credentials List -->
		<div>
			<div class="tb-card">
				<div class="tb-card-header">
					<h2 class="tb-card-title">
						<?php
						printf(
							/* translators: %s: author display name */
							esc_html__( 'Credentials for %s', 'trustbio' ),
							esc_html( $trustbio_author ? $trustbio_author->display_name : '' )
						);
						?>
					</h2>
					<span class="tb-item-count"><?php printf( esc_html( _n( '%d credential', '%d credentials', count( $trustbio_credentials ), 'trustbio' ) ), count( $trustbio_credentials ) ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?></span>
				</div>

				<?php if ( empty( $trustbio_credentials ) ) : ?>
				<div class="tb-empty">
					<div class="tb-cred-empty-icon">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
					</div>
					<p class="tb-empty__title"><?php esc_html_e( 'No credentials yet', 'trustbio' ); ?></p>
					<p class="tb-empty__text"><?php esc_html_e( 'Add professional credentials using the form. They will appear as badges beside the author name.', 'trustbio' ); ?></p>
				</div>
				<?php else : ?>
				<div class="tb-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
					<table class="tb-table">
						<thead>
							<tr>
								<th style="width:28px;"></th>
								<th><?php esc_html_e( 'Badge', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Credential', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Issuing Body', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Expiry', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Badge', 'trustbio' ); ?></th>
								<th><?php esc_html_e( 'Schema', 'trustbio' ); ?></th>
							</tr>
						</thead>
						<tbody id="tb-credentials-list">
						<?php foreach ( $trustbio_credentials as $trustbio_cred ) :
							$trustbio_badge_data = $trustbio_badge_types[ $trustbio_cred->badge_type ] ?? $trustbio_badge_types['custom'];
							$trustbio_is_expired = ! empty( $trustbio_cred->expiry_date ) && strtotime( $trustbio_cred->expiry_date ) < time();
							$trustbio_label = $trustbio_cred->badge_type !== 'custom'
								? strtoupper( $trustbio_cred->badge_type )
								: strtoupper( substr( $trustbio_cred->credential_name, 0, 6 ) );
						?>
							<tr id="tb-cred-row-<?php echo esc_attr( $trustbio_cred->id ); ?>" data-id="<?php echo esc_attr( $trustbio_cred->id ); ?>" class="<?php echo esc_attr( $trustbio_is_expired ? 'tb-cred-expired' : '' ); ?>">
								<td style="color:var(--tb-gray-300);cursor:grab;text-align:center;" class="tb-drag-handle">⠿</td>
								<td>
									<span class="trustbio-credential-badge" style="display:inline-flex;align-items:center;gap:3px;background:<?php echo esc_attr( $trustbio_badge_data['color'] ); ?>;color:<?php echo esc_attr( $trustbio_badge_data['text'] ); ?>;font-size:10px;font-weight:700;padding:3px 8px;border-radius:4px;letter-spacing:.05em;text-transform:uppercase;">
										<?php echo $trustbio_badge_data['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG from trusted internal data ?>
										<?php echo esc_html( $trustbio_label ); ?>
									</span>
								</td>
								<td>
									<strong style="display:block;font-size:13px;"><?php echo esc_html( $trustbio_cred->credential_name ); ?></strong>
									<?php if ( $trustbio_cred->verification_url ) : ?>
										<a href="<?php echo esc_url( $trustbio_cred->verification_url ); ?>" target="_blank" rel="noopener" style="font-size:11px;color:var(--tb-primary);"><?php esc_html_e( 'Verify ↗', 'trustbio' ); ?></a>
									<?php endif; ?>
									<div class="tb-row-actions">
										<button type="button" class="tb-row-action tb-row-action-edit tb-edit-credential"
											data-id="<?php echo esc_attr( $trustbio_cred->id ); ?>"
											data-badge-type="<?php echo esc_attr( $trustbio_cred->badge_type ); ?>"
											data-credential-name="<?php echo esc_attr( $trustbio_cred->credential_name ); ?>"
											data-issuing-body="<?php echo esc_attr( $trustbio_cred->issuing_body ); ?>"
											data-license-number="<?php echo esc_attr( $trustbio_cred->license_number ); ?>"
											data-issue-date="<?php echo esc_attr( $trustbio_cred->issue_date ?? '' ); ?>"
											data-expiry-date="<?php echo esc_attr( $trustbio_cred->expiry_date ?? '' ); ?>"
											data-verification-url="<?php echo esc_attr( $trustbio_cred->verification_url ); ?>"
											data-show-badge="<?php echo esc_attr( $trustbio_cred->show_badge ); ?>"
											data-show-schema="<?php echo esc_attr( $trustbio_cred->show_in_schema ); ?>"
										><?php esc_html_e( 'Edit', 'trustbio' ); ?></button>
										<button type="button" class="tb-row-action tb-row-action-delete tb-delete-credential"
											data-id="<?php echo esc_attr( $trustbio_cred->id ); ?>"
											data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_delete_credential' ) ); ?>"
										><?php esc_html_e( 'Delete', 'trustbio' ); ?></button>
									</div>
								</td>
								<td style="font-size:12px;"><?php echo $trustbio_cred->issuing_body ? esc_html( $trustbio_cred->issuing_body ) : '—'; ?></td>
								<td>
									<?php if ( $trustbio_cred->expiry_date ) : ?>
										<span class="tb-badge <?php echo esc_attr( $trustbio_is_expired ? 'tb-badge-danger' : 'tb-badge-success' ); ?>">
											<?php echo $trustbio_is_expired ? esc_html__( 'Expired', 'trustbio' ) : esc_html( date_i18n( get_option( 'date_format' ), strtotime( $trustbio_cred->expiry_date ) ) ); ?>
										</span>
									<?php else : ?>
										<span style="color:var(--tb-gray-400);font-size:12px;"><?php esc_html_e( 'No expiry', 'trustbio' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<span class="tb-badge <?php echo esc_attr( $trustbio_cred->show_badge ? 'tb-badge-success' : 'tb-badge-gray' ); ?>">
										<?php echo $trustbio_cred->show_badge ? esc_html__( 'Shown', 'trustbio' ) : esc_html__( 'Hidden', 'trustbio' ); ?>
									</span>
								</td>
								<td>
									<span class="tb-badge <?php echo esc_attr( $trustbio_cred->show_in_schema ? 'tb-badge-success' : 'tb-badge-gray' ); ?>">
										<?php echo $trustbio_cred->show_in_schema ? esc_html__( 'Yes', 'trustbio' ) : esc_html__( 'No', 'trustbio' ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="tb-cred-reorder-hint">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M5 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM12 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"/></svg>
					<?php esc_html_e( 'Drag rows to change badge display order.', 'trustbio' ); ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php endif; ?>
</div>


