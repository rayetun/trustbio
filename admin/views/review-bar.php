<?php
/**
 * Admin view: Review Bars
 * Mode 1: list (card grid)
 * Mode 2: builder (edit / new review bar)
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$trustbio_mode   = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
$trustbio_bar_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$trustbio_bar_class = new TrustBio_Review_Bar();

// ── BUILDER MODE ──────────────────────────────────────────────
if ( 'edit' === $trustbio_mode || 'new' === $trustbio_mode ) {
	$trustbio_is_new    = 'new' === $trustbio_mode;
	$trustbio_edit_bar  = $trustbio_is_new ? null : $trustbio_bar_class->get_box( $trustbio_bar_id );
	$trustbio_defaults  = $trustbio_bar_class->get_default_settings();
	$trustbio_s         = $trustbio_edit_bar ? $trustbio_edit_bar->settings : $trustbio_defaults;

	// Helper: get setting value with fallback.
	$trustbio_get = static function ( string $trustbio_key ) use ( $trustbio_s, $trustbio_defaults ) {
		return $trustbio_s[ $trustbio_key ] ?? $trustbio_defaults[ $trustbio_key ] ?? '';
	};

	$trustbio_current_layout = $trustbio_edit_bar ? ( $trustbio_edit_bar->layout ?? 'classic' ) : 'classic';

	?>
	<div class="trustbio-admin-wrap">

		<!-- ── Builder Header ── -->
		<div class="tb-page-header">
			<div class="tb-page-header__left">
				<div class="tb-page-header__icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 11-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd"/></svg>
				</div>
				<div class="tb-page-header__text">
					<h1 class="tb-page-title">
						<?php echo $trustbio_is_new ? esc_html__( 'New Review Bar', 'trustbio' ) : esc_html__( 'Edit Review Bar', 'trustbio' ); ?>
					</h1>
					<?php if ( ! $trustbio_is_new && $trustbio_edit_bar ) : ?>
					<p class="tb-page-subtitle"><?php echo esc_html( $trustbio_edit_bar->title ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="tb-page-header__actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-review-bars' ) ); ?>" class="tb-btn tb-btn-secondary">
					<?php esc_html_e( '← All Review Bars', 'trustbio' ); ?>
				</a>
				<button type="button" id="tbr-save-bar-btn" class="tb-btn tb-btn-primary">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
					<?php esc_html_e( 'Save Bar', 'trustbio' ); ?>
				</button>
			</div>
		</div>

		<div class="tb-notice-area" id="tbr-notice-area"></div>

		<form id="tbr-builder-form" novalidate>
			<input type="hidden" name="id" value="<?php echo esc_attr( $trustbio_bar_id ); ?>">

			<div class="tb-builder-layout">

				<!-- ── LEFT: Controls ── -->
				<div class="tb-builder-controls">

					<!-- Bar Name -->
					<div class="tb-card">
						<div class="tb-card-body">
							<div class="tb-form-group" style="margin-bottom:0;">
								<label class="tb-label" for="tbr-bar-title"><?php esc_html_e( 'Bar Name', 'trustbio' ); ?> <span class="required">*</span></label>
								<input type="text" id="tbr-bar-title" name="title" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. My Review Bar', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_bar ? esc_attr( $trustbio_edit_bar->title ) : ''; ?>" required>
							</div>
						</div>
					</div>

					<!-- Layout Picker -->
					<div class="tb-card">
						<div class="tb-card-body">
							<p class="tb-section-label" style="margin-bottom:14px;"><?php esc_html_e( 'Select a Layout', 'trustbio' ); ?></p>
							<div class="tb-layout-picker">
								<?php
								$trustbio_layouts = array(
									'classic' => array(
										'name' => __( 'Classic Bar', 'trustbio' ),
										'desc' => __( 'Checkmark icon + accent border. Healthline-style.', 'trustbio' ),
									),
									'minimal' => array(
										'name' => __( 'Minimal Inline', 'trustbio' ),
										'desc' => __( 'Plain text byline. Zero visual weight.', 'trustbio' ),
									),
								);

								/**
								 * Filters the review bar layouts offered in the builder.
								 *
								 * The TrustBio Pro add-on appends its premium layouts here.
								 *
								 * @since 1.0.0
								 * @param array<string,array> $layouts Layout slug => name/desc.
								 */
								$trustbio_layouts = (array) apply_filters( 'trustbio_review_bar_layouts', $trustbio_layouts );
								foreach ( $trustbio_layouts as $trustbio_lkey => $trustbio_ldata ) :
									$trustbio_is_active = $trustbio_lkey === $trustbio_current_layout;
								?>
								<label class="tb-layout-card tbr-layout-card <?php echo $trustbio_is_active ? 'is-active' : ''; ?>" data-layout="<?php echo esc_attr( $trustbio_lkey ); ?>">
									<input type="radio" name="layout" value="<?php echo esc_attr( $trustbio_lkey ); ?>" <?php checked( $trustbio_current_layout, $trustbio_lkey ); ?> style="display:none;">
									<div class="tb-layout-card__thumb tb-layout-thumb-<?php echo esc_attr( $trustbio_lkey ); ?>">
										<?php echo $trustbio_bar_class->render_card_preview( (object) array( 'layout' => $trustbio_lkey, 'settings' => $trustbio_bar_class->get_default_settings( $trustbio_lkey ), 'is_default' => 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
									<div class="tb-layout-card__label"><?php echo esc_html( $trustbio_ldata['name'] ); ?></div>
									<div class="tb-layout-card__desc"><?php echo esc_html( $trustbio_ldata['desc'] ); ?></div>
								</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<!-- Appearance -->
					<div class="tb-card">
						<div class="tb-card-body">
							<p class="tb-section-label" style="margin-bottom:14px;"><?php esc_html_e( 'Appearance', 'trustbio' ); ?></p>

							<div class="tb-form-group">
								<label class="tb-label"><?php esc_html_e( 'Accent Color', 'trustbio' ); ?></label>
								<div class="tb-color-row">
									<input type="color" class="tb-color-swatch tbr-preview-trigger" name="settings[accent_color]" value="<?php echo esc_attr( $trustbio_get( 'accent_color' ) ?: '#4f46e5' ); ?>">
									<input type="text" class="tb-input tb-color-text" value="<?php echo esc_attr( $trustbio_get( 'accent_color' ) ); ?>" maxlength="7" placeholder="#4f46e5">
								</div>
							</div>

							<div class="tb-form-group">
								<label class="tb-label" for="tbr-label-text"><?php esc_html_e( 'Label Text', 'trustbio' ); ?></label>
								<input type="text" id="tbr-label-text" name="settings[label_text]" class="tb-input tbr-preview-trigger" placeholder="<?php esc_attr_e( 'Use site default label', 'trustbio' ); ?>" value="<?php echo esc_attr( $trustbio_get( 'label_text' ) ); ?>">
							</div>

							<div class="tb-form-group" style="margin-bottom:0;">
								<label class="tb-toggle-wrap">
									<label class="tb-toggle">
										<input type="checkbox" name="settings[show_credentials]" value="1" class="tbr-preview-trigger" <?php checked( (int) $trustbio_get( 'show_credentials' ), 1 ); ?>>
										<span class="tb-toggle__track"></span>
									</label>
									<span class="tb-toggle-label"><?php esc_html_e( 'Show MD, PhD etc. after reviewer name.', 'trustbio' ); ?></span>
								</label>
							</div>

						</div>
					</div>

					<!-- Display Options -->
					<div class="tb-card">
						<div class="tb-card-body">
							<p class="tb-section-label" style="margin-bottom:14px;"><?php esc_html_e( 'Display Options', 'trustbio' ); ?></p>

							<div class="tbr-option-group" data-show-for="classic minimal">
								<div class="tb-form-group" style="margin-bottom:0;">
									<label class="tb-toggle-wrap">
										<label class="tb-toggle">
											<input type="checkbox" name="settings[show_icon]" value="1" class="tbr-preview-trigger" <?php checked( (int) $trustbio_get( 'show_icon' ), 1 ); ?>>
											<span class="tb-toggle__track"></span>
										</label>
										<span class="tb-toggle-label"><?php esc_html_e( 'Show Checkmark Icon', 'trustbio' ); ?></span>
									</label>
								</div>
							</div>

							<div class="tbr-option-group" data-show-for="badge-strip authority-card">
								<div class="tb-form-group" style="margin-bottom:0;">
									<label class="tb-toggle-wrap">
										<label class="tb-toggle">
											<input type="checkbox" name="settings[show_avatar]" value="1" class="tbr-preview-trigger" <?php checked( (int) $trustbio_get( 'show_avatar' ), 1 ); ?>>
											<span class="tb-toggle__track"></span>
										</label>
										<span class="tb-toggle-label"><?php esc_html_e( 'Show Reviewer Avatar', 'trustbio' ); ?></span>
									</label>
								</div>
							</div>

							<div class="tbr-option-group" data-show-for="badge-strip pill-duo">
								<div class="tb-form-group" style="margin-bottom:0;">
									<label class="tb-label" for="tbr-badge-text"><?php esc_html_e( 'Badge Label Text', 'trustbio' ); ?></label>
									<input type="text" id="tbr-badge-text" name="settings[badge_text]" class="tb-input tbr-preview-trigger" placeholder="<?php esc_attr_e( 'e.g. REVIEWED', 'trustbio' ); ?>" value="<?php echo esc_attr( $trustbio_get( 'badge_text' ) ); ?>">
								</div>
							</div>

							<div class="tbr-option-group" data-show-for="pill-duo">
								<div class="tb-form-group" style="margin-bottom:0;">
									<label class="tb-label" for="tbr-pill-style"><?php esc_html_e( 'Pill Style', 'trustbio' ); ?></label>
									<select id="tbr-pill-style" name="settings[pill_style]" class="tb-select tbr-preview-trigger">
										<option value="filled" <?php selected( $trustbio_get( 'pill_style' ), 'filled' ); ?>><?php esc_html_e( 'Filled (solid color)', 'trustbio' ); ?></option>
										<option value="outlined" <?php selected( $trustbio_get( 'pill_style' ), 'outlined' ); ?>><?php esc_html_e( 'Outlined (border only)', 'trustbio' ); ?></option>
									</select>
								</div>
							</div>

							<div class="tbr-option-group" data-show-for="authority-card">
								<div class="tb-form-group" style="margin-bottom:0;">
									<label class="tb-label" for="tbr-corner-radius"><?php esc_html_e( 'Corner Radius (px)', 'trustbio' ); ?></label>
									<input type="number" id="tbr-corner-radius" name="settings[corner_radius]" class="tb-input tbr-preview-trigger" min="0" max="24" style="max-width:100px;" value="<?php echo esc_attr( $trustbio_get( 'corner_radius' ) ); ?>">
								</div>
							</div>

							<div class="tbr-option-group" data-show-for="dark-shield">
								<div class="tb-form-group" style="margin-bottom:0;">
									<label class="tb-label"><?php esc_html_e( 'Dark Background Color', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" class="tb-color-swatch tbr-preview-trigger" name="settings[bg_color]" value="<?php echo esc_attr( $trustbio_get( 'bg_color' ) ?: '#111827' ); ?>">
										<input type="text" class="tb-input tb-color-text" value="<?php echo esc_attr( $trustbio_get( 'bg_color' ) ); ?>" maxlength="7" placeholder="#111827">
									</div>
								</div>
							</div>

						</div>
					</div>

				</div><!-- .tb-builder-controls -->

				<!-- ── RIGHT: Live Preview ── -->
				<div class="tb-builder-preview-col">
					<div class="tb-card tb-builder-preview-card">
						<div class="tb-card-header">
							<h3 class="tb-card-title" style="font-size:13px;"><?php esc_html_e( 'Live Preview', 'trustbio' ); ?></h3>
							<div style="display:flex;align-items:center;gap:6px;">
								<span class="tb-preview-updating" id="tbr-preview-spinner" style="display:none;"><span class="tb-spinner"></span></span>
								<button type="button" class="tb-btn tb-btn-ghost tb-btn-sm" id="tbr-refresh-preview">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
									<?php esc_html_e( 'Refresh Preview', 'trustbio' ); ?>
								</button>
							</div>
						</div>
						<div class="tb-card-body tb-card-body--flush">
							<div class="tb-box-preview-wrap" id="tbr-preview-panel">
								<div class="tb-box-preview-inner" id="tbr-preview-content">
									<div class="tb-empty" style="padding:40px 20px;text-align:center;">
										<span class="tb-spinner"></span>
										<p style="margin-top:10px;font-size:13px;color:var(--tb-gray-400);"><?php esc_html_e( 'Loading preview…', 'trustbio' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div><!-- .tb-builder-preview-col -->

			</div><!-- .tb-builder-layout -->

			<!-- Sticky Save Bar -->
			<div class="tb-save-bar">
				<span class="tb-save-bar__status" id="tbr-save-status"></span>
				<div class="tb-save-bar__actions">
					<button type="submit" id="tbr-save-bar-btn" class="tb-btn tb-btn-primary"><?php esc_html_e( 'Save Bar', 'trustbio' ); ?></button>
				</div>
			</div>
		</form>

	</div><!-- .trustbio-admin-wrap -->

	<?php
	return; // Do not render the list below.
}

// ── LIST MODE ─────────────────────────────────────────────────
$trustbio_bar_class = new TrustBio_Review_Bar();
$trustbio_all_bars  = $trustbio_bar_class->get_all();

?>

<div class="trustbio-admin-wrap">

	<div class="tb-page-header">
		<div class="tb-page-header__left">
			<div class="tb-page-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 11-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd"/></svg>
			</div>
			<div class="tb-page-header__text">
				<h1 class="tb-page-title"><?php esc_html_e( 'Last Review Bars', 'trustbio' ); ?></h1>
				<p class="tb-page-subtitle"><?php esc_html_e( 'Design expert review bar templates shown on your posts.', 'trustbio' ); ?></p>
			</div>
		</div>
		<div class="tb-page-header__actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-review-bars&action=new' ) ); ?>" class="tb-btn tb-btn-primary">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:15px;height:15px;"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( '+ New Review Bar', 'trustbio' ); ?>
			</a>
		</div>
	</div>

	<div class="tb-notice-area" id="tbr-notice-area"></div>

	<!-- ===== SAVED REVIEW BARS LIST ===== -->
	<div class="tb-content-area">
		<?php if ( empty( $trustbio_all_bars ) ) : ?>
			<div class="tb-card">
				<div class="tb-empty-hero">
					<div class="tb-empty-hero__icon tb-empty-hero__icon--orange">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
					</div>
					<h3 class="tb-empty-hero__title"><?php esc_html_e( 'Show expert review status', 'trustbio' ); ?></h3>
					<p class="tb-empty-hero__text"><?php esc_html_e( 'Add a "Medically reviewed by" or "Fact-checked by" bar to your posts. 6 trust-signal layouts to choose from.', 'trustbio' ); ?></p>
					<div class="tb-empty-hero__chips">
						<span class="tb-empty-hero__chip"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php esc_html_e( '6 unique layouts', 'trustbio' ); ?></span>
						<span class="tb-empty-hero__chip"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php esc_html_e( 'Reviewer photo & name', 'trustbio' ); ?></span>
						<span class="tb-empty-hero__chip"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php esc_html_e( 'Stale content alerts', 'trustbio' ); ?></span>
					</div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-review-bars&action=new' ) ); ?>" class="tb-btn tb-btn-primary"><?php esc_html_e( '+ Create First Review Bar', 'trustbio' ); ?></a>
				</div>
			</div>
		<?php else : ?>

		<div class="tb-boxes-grid">
			<?php foreach ( $trustbio_all_bars as $trustbio_bar ) : ?>
			<div class="tb-box-card <?php echo $trustbio_bar->is_default ? 'is-default' : ''; ?>" id="tbr-bar-card-<?php echo esc_attr( $trustbio_bar->id ); ?>">
				<?php if ( $trustbio_bar->is_default ) : ?>
				<div class="tb-box-card__default-badge"><?php esc_html_e( '★ Default', 'trustbio' ); ?></div>
				<?php endif; ?>
				<div class="tb-box-card__preview">
					<?php echo $trustbio_bar_class->render_card_preview( $trustbio_bar ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="tb-box-card__footer">
					<div class="tb-box-card__info">
						<span class="tb-box-card__title"><?php echo esc_html( $trustbio_bar->title ); ?></span>
						<span class="tb-box-card__layout tb-text-mono"><?php echo esc_html( $trustbio_bar->layout ); ?></span>
					</div>
					<div class="tb-box-card__actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-review-bars&action=edit&id=' . $trustbio_bar->id ) ); ?>" class="tb-btn tb-btn-secondary tb-btn-sm"><?php esc_html_e( 'Edit', 'trustbio' ); ?></a>
						<button type="button" class="tb-btn tb-btn-ghost tb-btn-sm tbr-bar-more" data-id="<?php echo esc_attr( $trustbio_bar->id ); ?>" data-default="<?php echo esc_attr( $trustbio_bar->is_default ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
						</button>
					</div>
				</div>
				<div class="tb-box-dropdown" id="tbr-dropdown-<?php echo esc_attr( $trustbio_bar->id ); ?>" style="display:none;">
					<?php if ( ! $trustbio_bar->is_default ) : ?>
					<button type="button" class="tb-dropdown-item tbr-set-default" data-id="<?php echo esc_attr( $trustbio_bar->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_set_default_review_bar' ) ); ?>"><?php esc_html_e( 'Set as Default', 'trustbio' ); ?></button>
					<?php endif; ?>
					<button type="button" class="tb-dropdown-item tbr-duplicate-bar" data-id="<?php echo esc_attr( $trustbio_bar->id ); ?>"><?php esc_html_e( 'Duplicate', 'trustbio' ); ?></button>
					<?php if ( ! $trustbio_bar->is_default ) : ?>
					<button type="button" class="tb-dropdown-item tb-dropdown-danger tbr-delete-bar" data-id="<?php echo esc_attr( $trustbio_bar->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_delete_review_bar' ) ); ?>"><?php esc_html_e( 'Delete', 'trustbio' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

		<?php endif; // end empty check ?>
	</div><!-- .tb-content-area -->

</div><!-- .trustbio-admin-wrap -->
