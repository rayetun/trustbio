<?php
/**
 * Admin view: Author Boxes
 * Mode 1: list (card grid)
 * Mode 2: builder (edit / new box)
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$trustbio_mode   = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
$trustbio_box_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$trustbio_tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'default';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$trustbio_box_class = new TrustBio_Author_Box();

// ── BUILDER MODE ──────────────────────────────────────────────
if ( 'edit' === $trustbio_mode || 'new' === $trustbio_mode ) {
	$trustbio_is_new    = 'new' === $trustbio_mode;
	$trustbio_edit_box  = $trustbio_is_new ? null : $trustbio_box_class->get_box( $trustbio_box_id );
	$trustbio_defaults  = $trustbio_box_class->get_default_settings();
	$trustbio_s         = $trustbio_edit_box ? $trustbio_edit_box->settings : $trustbio_defaults;

	// Helper: get setting value with fallback.
	$trustbio_get = static function ( string $key ) use ( $trustbio_s, $trustbio_defaults ) {
		return $trustbio_s[ $key ] ?? $trustbio_defaults[ $key ];
	};

	?>
	<div class="trustbio-admin-wrap">

		<!-- ── Builder Header ── -->
		<div class="tb-page-header">
			<div class="tb-page-header__left">
				<div class="tb-page-header__icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
				</div>
				<div class="tb-page-header__text">
					<h1 class="tb-page-title">
						<?php echo $trustbio_is_new ? esc_html__( 'New Author Box', 'trustbio' ) : esc_html__( 'Edit Author Box', 'trustbio' ); ?>
					</h1>
					<?php if ( ! $trustbio_is_new && $trustbio_edit_box ) : ?>
					<p class="tb-page-subtitle"><?php echo esc_html( $trustbio_edit_box->title ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="tb-page-header__actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-boxes' ) ); ?>" class="tb-btn tb-btn-secondary">
					<?php esc_html_e( '← All Boxes', 'trustbio' ); ?>
				</a>
				<button type="button" id="tb-box-save-btn" class="tb-btn tb-btn-primary">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
					<?php esc_html_e( 'Save Box', 'trustbio' ); ?>
				</button>
			</div>
		</div>

		<div class="tb-notice-area" id="tb-notice-area"></div>

		<form id="tb-box-builder-form" novalidate>
			<input type="hidden" name="id" value="<?php echo esc_attr( $trustbio_box_id ); ?>">

			<div class="tb-builder-layout">

				<!-- ── LEFT: Controls ── -->
				<div class="tb-builder-controls">

					<!-- Box Name -->
					<div class="tb-card">
						<div class="tb-card-body">
							<div class="tb-form-group" style="margin-bottom:0;">
								<label class="tb-label" for="tb-box-title"><?php esc_html_e( 'Box Name', 'trustbio' ); ?> <span class="required">*</span></label>
								<input type="text" id="tb-box-title" name="title" class="tb-input" placeholder="<?php esc_attr_e( 'e.g. Classic Stack', 'trustbio' ); ?>" value="<?php echo $trustbio_edit_box ? esc_attr( $trustbio_edit_box->title ) : ''; ?>" required>
							</div>
						</div>
					</div>

					<!-- Customiser Tabs -->
					<div class="tb-card tb-tabs-container" id="tb-customiser-card">
						<div class="tb-tabs" style="padding:0 4px 0;">
							<button type="button" class="tb-tab is-active" data-tab="layout"><?php esc_html_e( 'Layout', 'trustbio' ); ?></button>
							<button type="button" class="tb-tab" data-tab="style"><?php esc_html_e( 'Style', 'trustbio' ); ?></button>
							<button type="button" class="tb-tab" data-tab="typography"><?php esc_html_e( 'Typography', 'trustbio' ); ?></button>
							<button type="button" class="tb-tab" data-tab="icons"><?php esc_html_e( 'Social Icons', 'trustbio' ); ?></button>
							<button type="button" class="tb-tab" data-tab="advanced"><?php esc_html_e( 'Advanced', 'trustbio' ); ?></button>
						</div>

						<!-- TAB: Layout -->
						<div class="tb-tab-pane is-active" data-tab-pane="layout" style="padding:20px;">
							<p class="tb-section-label" style="margin-bottom:14px;"><?php esc_html_e( 'Select a Layout', 'trustbio' ); ?></p>
							<div class="tb-layout-picker">
								<?php
								$trustbio_layouts = array(
									'byline'         => array( 'name' => __( 'Classic Byline', 'trustbio' ), 'desc' => __( 'Avatar · name · bio · socials', 'trustbio' ) ),
									'quote'          => array( 'name' => __( 'Quote Card', 'trustbio' ),     'desc' => __( 'Bio as serif pull-quote', 'trustbio' ) ),
									'minimal-inline' => array( 'name' => __( 'Minimal Inline', 'trustbio' ), 'desc' => __( 'Hairline rules · no background', 'trustbio' ) ),
									'simple'         => array( 'name' => __( 'Card Simple', 'trustbio' ),    'desc' => __( 'Square avatar · divider · follow', 'trustbio' ) ),
								);

								/**
								 * Filters the author box layouts offered in the builder.
								 *
								 * The TrustBio Pro add-on appends its premium layouts here.
								 *
								 * @since 1.0.0
								 * @param array<string,array> $layouts Layout slug => name/desc.
								 */
								$trustbio_layouts = (array) apply_filters( 'trustbio_box_layouts', $trustbio_layouts );
								$trustbio_current_layout = $trustbio_edit_box ? $trustbio_edit_box->layout : 'byline';
								foreach ( $trustbio_layouts as $trustbio_lkey => $trustbio_ldata ) :
									$trustbio_is_active = $trustbio_lkey === $trustbio_current_layout;
								?>
								<label class="tb-layout-card <?php echo $trustbio_is_active ? 'is-active' : ''; ?>" data-layout="<?php echo esc_attr( $trustbio_lkey ); ?>">
									<input type="radio" name="layout" value="<?php echo esc_attr( $trustbio_lkey ); ?>" <?php checked( $trustbio_current_layout, $trustbio_lkey ); ?> style="display:none;">
									<div class="tb-layout-card__thumb tb-layout-thumb-<?php echo esc_attr( $trustbio_lkey ); ?>">
										<?php echo $this->get_layout_thumb_html( $trustbio_lkey ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
									<div class="tb-layout-card__label"><?php echo esc_html( $trustbio_ldata['name'] ); ?></div>
									<div class="tb-layout-card__desc"><?php echo esc_html( $trustbio_ldata['desc'] ); ?></div>
								</label>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- TAB: Style -->
						<div class="tb-tab-pane" data-tab-pane="style" style="padding:20px;">

							<p class="tb-section-label"><?php esc_html_e( 'Box Background & Border', 'trustbio' ); ?></p>

							<div class="tb-form-grid-2">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Background Color', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" class="tb-color-swatch" value="<?php echo esc_attr( $trustbio_get( 'bg_color' ) ?: '#ffffff' ); ?>">
										<input type="text" name="settings[bg_color]" class="tb-input tb-color-text tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'bg_color' ) ); ?>" placeholder="None (transparent)" maxlength="7">
									</div>
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Border Color', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" class="tb-color-swatch" value="<?php echo esc_attr( $trustbio_get( 'border_color' ) ?: '#e5e7eb' ); ?>">
										<input type="text" name="settings[border_color]" class="tb-input tb-color-text tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'border_color' ) ); ?>" placeholder="#e5e7eb" maxlength="7">
									</div>
								</div>
							</div>

							<div class="tb-form-grid-3">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Border Width (px)', 'trustbio' ); ?></label>
									<input type="number" name="settings[border_width]" class="tb-input tb-preview-trigger" min="0" max="8" value="<?php echo esc_attr( $trustbio_get( 'border_width' ) ); ?>">
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Border Radius (px)', 'trustbio' ); ?></label>
									<input type="number" name="settings[border_radius]" class="tb-input tb-preview-trigger" min="0" max="40" value="<?php echo esc_attr( $trustbio_get( 'border_radius' ) ); ?>">
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Padding (px)', 'trustbio' ); ?></label>
									<input type="number" name="settings[padding]" class="tb-input tb-preview-trigger" min="0" max="60" value="<?php echo esc_attr( $trustbio_get( 'padding' ) ); ?>">
								</div>
							</div>

							<div class="tb-form-group">
								<label class="tb-label"><?php esc_html_e( 'Border Style', 'trustbio' ); ?></label>
								<select name="settings[border_style]" id="tb-border-style-select" class="tb-select tb-preview-trigger">
									<?php foreach ( array( 'none' => __( 'None', 'trustbio' ), 'solid' => __( 'Solid', 'trustbio' ), 'dashed' => __( 'Dashed', 'trustbio' ), 'dotted' => __( 'Dotted', 'trustbio' ) ) as $trustbio_bv => $trustbio_bl ) : ?>
										<option value="<?php echo esc_attr( $trustbio_bv ); ?>" <?php selected( $trustbio_get( 'border_style' ), $trustbio_bv ); ?>><?php echo esc_html( $trustbio_bl ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div id="tb-border-sub-controls" <?php echo 'none' === $trustbio_get( 'border_style' ) ? 'style="display:none;"' : ''; ?>>
								<p class="tb-section-label"><?php esc_html_e( 'Border Width per Side (px)', 'trustbio' ); ?></p>
								<div class="tb-form-grid-2">
									<div class="tb-form-group">
										<label class="tb-label" style="font-size:11px;">↑ <?php esc_html_e( 'Top', 'trustbio' ); ?></label>
										<input type="number" name="settings[border_top]" class="tb-input tb-preview-trigger" min="0" max="20" value="<?php echo esc_attr( $trustbio_get( 'border_top' ) ); ?>">
									</div>
									<div class="tb-form-group">
										<label class="tb-label" style="font-size:11px;">→ <?php esc_html_e( 'Right', 'trustbio' ); ?></label>
										<input type="number" name="settings[border_right]" class="tb-input tb-preview-trigger" min="0" max="20" value="<?php echo esc_attr( $trustbio_get( 'border_right' ) ); ?>">
									</div>
									<div class="tb-form-group">
										<label class="tb-label" style="font-size:11px;">↓ <?php esc_html_e( 'Bottom', 'trustbio' ); ?></label>
										<input type="number" name="settings[border_bottom]" class="tb-input tb-preview-trigger" min="0" max="20" value="<?php echo esc_attr( $trustbio_get( 'border_bottom' ) ); ?>">
									</div>
									<div class="tb-form-group">
										<label class="tb-label" style="font-size:11px;">← <?php esc_html_e( 'Left', 'trustbio' ); ?></label>
										<input type="number" name="settings[border_left]" class="tb-input tb-preview-trigger" min="0" max="20" value="<?php echo esc_attr( $trustbio_get( 'border_left' ) ); ?>">
									</div>
								</div>
								<p class="tb-input-hint"><?php esc_html_e( 'Set only one side for accent-border layouts (e.g. left:4 for Banner style).', 'trustbio' ); ?></p>
							</div>

							<div class="tb-form-group">
								<div class="tb-toggle-wrap">
									<label class="tb-toggle">
										<input type="checkbox" name="settings[box_shadow]" class="tb-preview-trigger" value="1" <?php checked( $trustbio_get( 'box_shadow' ), true ); ?>>
										<span class="tb-toggle__track"></span>
									</label>
									<span class="tb-toggle-label"><?php esc_html_e( 'Show box shadow', 'trustbio' ); ?></span>
								</div>
							</div>

							<hr class="tb-divider">
							<p class="tb-section-label"><?php esc_html_e( 'Button', 'trustbio' ); ?></p>
							<div class="tb-form-grid-2">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Button Background', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" class="tb-color-swatch" value="<?php echo esc_attr( $trustbio_get( 'btn_color' ) ?: '#15161a' ); ?>">
										<input type="text" name="settings[btn_color]" class="tb-input tb-color-text tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'btn_color' ) ); ?>" placeholder="Default (#15161a)" maxlength="7">
									</div>
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Button Text Color', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" class="tb-color-swatch" value="<?php echo esc_attr( $trustbio_get( 'btn_text_color' ) ?: '#ffffff' ); ?>">
										<input type="text" name="settings[btn_text_color]" class="tb-input tb-color-text tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'btn_text_color' ) ); ?>" placeholder="Default (#ffffff)" maxlength="7">
									</div>
								</div>
							</div>
							<p class="tb-input-hint"><?php esc_html_e( 'Applies to Follow, View all posts, and CTA buttons.', 'trustbio' ); ?></p>

							<hr class="tb-divider">
							<p class="tb-section-label"><?php esc_html_e( 'Avatar', 'trustbio' ); ?></p>

							<div class="tb-form-grid-2">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Avatar Size (px)', 'trustbio' ); ?></label>
									<input type="range" name="settings[avatar_size]" class="tb-range tb-preview-trigger" min="40" max="160" value="<?php echo esc_attr( $trustbio_get( 'avatar_size' ) ); ?>" id="tb-avatar-size-range">
									<div style="text-align:right;font-size:12px;color:var(--tb-gray-500);margin-top:2px;"><span id="tb-avatar-size-range-display"><?php echo esc_html( $trustbio_get( 'avatar_size' ) ); ?></span>px</div>
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Avatar Shape', 'trustbio' ); ?></label>
									<div class="tb-shape-picker">
										<?php foreach ( array( 'circle' => __( 'Circle', 'trustbio' ), 'rounded' => __( 'Rounded', 'trustbio' ), 'square' => __( 'Square', 'trustbio' ) ) as $trustbio_sv => $trustbio_sl ) : ?>
										<label class="tb-shape-card <?php echo esc_attr( $trustbio_get( 'avatar_shape' ) === $trustbio_sv ? 'is-active' : '' ); ?>">
											<input type="radio" name="settings[avatar_shape]" class="tb-preview-trigger" value="<?php echo esc_attr( $trustbio_sv ); ?>" <?php checked( $trustbio_get( 'avatar_shape' ), $trustbio_sv ); ?>>
											<span class="tb-shape-thumb tb-shape-<?php echo esc_attr( $trustbio_sv ); ?>"></span>
											<span class="tb-shape-label"><?php echo esc_html( $trustbio_sl ); ?></span>
										</label>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>

						<!-- TAB: Typography -->
						<div class="tb-tab-pane" data-tab-pane="typography" style="padding:20px;">

							<p class="tb-section-label"><?php esc_html_e( 'Font Style', 'trustbio' ); ?></p>
							<div class="tb-form-grid-2">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Heading Font', 'trustbio' ); ?></label>
									<select name="settings[font_heading]" class="tb-select tb-preview-trigger">
										<option value="" <?php selected( $trustbio_get( 'font_heading' ), '' ); ?>><?php esc_html_e( 'Classic Serif (default)', 'trustbio' ); ?></option>
										<option value="system-ui,-apple-system,sans-serif" <?php selected( $trustbio_get( 'font_heading' ), 'system-ui,-apple-system,sans-serif' ); ?>><?php esc_html_e( 'Modern Sans', 'trustbio' ); ?></option>
										<option value="Optima,Candara,&quot;Noto Sans&quot;,sans-serif" <?php selected( $trustbio_get( 'font_heading' ), 'Optima,Candara,"Noto Sans",sans-serif' ); ?>><?php esc_html_e( 'Humanist', 'trustbio' ); ?></option>
										<option value="'Palatino Linotype',Palatino,Georgia,serif" <?php selected( $trustbio_get( 'font_heading' ), "'Palatino Linotype',Palatino,Georgia,serif" ); ?>><?php esc_html_e( 'Elegant Serif', 'trustbio' ); ?></option>
										<option value="inherit" <?php selected( $trustbio_get( 'font_heading' ), 'inherit' ); ?>><?php esc_html_e( 'Inherit from Theme', 'trustbio' ); ?></option>
									</select>
									<p class="tb-input-hint"><?php esc_html_e( 'Used for author name across all layouts.', 'trustbio' ); ?></p>
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Label Font', 'trustbio' ); ?></label>
									<select name="settings[font_label]" class="tb-select tb-preview-trigger">
										<option value="" <?php selected( $trustbio_get( 'font_label' ), '' ); ?>><?php esc_html_e( 'Monospace (default)', 'trustbio' ); ?></option>
										<option value="system-ui,-apple-system,sans-serif" <?php selected( $trustbio_get( 'font_label' ), 'system-ui,-apple-system,sans-serif' ); ?>><?php esc_html_e( 'Same as Heading', 'trustbio' ); ?></option>
										<option value="inherit" <?php selected( $trustbio_get( 'font_label' ), 'inherit' ); ?>><?php esc_html_e( 'Inherit from Theme', 'trustbio' ); ?></option>
									</select>
									<p class="tb-input-hint"><?php esc_html_e( 'Used for role labels and kicker text.', 'trustbio' ); ?></p>
								</div>
							</div>

							<hr class="tb-divider">
							<p class="tb-section-label"><?php esc_html_e( 'Author Name', 'trustbio' ); ?></p>
							<div class="tb-form-grid-3">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Color', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" name="settings[name_color]" class="tb-color-swatch tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'name_color' ) ); ?>">
										<input type="text" class="tb-input tb-color-text" value="<?php echo esc_attr( $trustbio_get( 'name_color' ) ); ?>" maxlength="7">
									</div>
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Font Size (px)', 'trustbio' ); ?></label>
									<input type="number" name="settings[name_size]" class="tb-input tb-preview-trigger" min="12" max="32" value="<?php echo esc_attr( $trustbio_get( 'name_size' ) ); ?>">
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Weight', 'trustbio' ); ?></label>
									<select name="settings[name_weight]" class="tb-select tb-preview-trigger">
										<?php foreach ( array( '400' => 'Regular', '500' => 'Medium', '600' => 'SemiBold', '700' => 'Bold', '800' => 'ExtraBold' ) as $trustbio_wv => $trustbio_wl ) : ?>
											<option value="<?php echo esc_attr( $trustbio_wv ); ?>" <?php selected( $trustbio_get( 'name_weight' ), $trustbio_wv ); ?>><?php echo esc_html( $trustbio_wl ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>

							<p class="tb-section-label" style="margin-top:8px;"><?php esc_html_e( 'Job Title', 'trustbio' ); ?></p>
							<div class="tb-form-grid-2">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Color', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" name="settings[jobtitle_color]" class="tb-color-swatch tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'jobtitle_color' ) ); ?>">
										<input type="text" class="tb-input tb-color-text" value="<?php echo esc_attr( $trustbio_get( 'jobtitle_color' ) ); ?>" maxlength="7">
									</div>
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Font Size (px)', 'trustbio' ); ?></label>
									<input type="number" name="settings[jobtitle_size]" class="tb-input tb-preview-trigger" min="10" max="20" value="<?php echo esc_attr( $trustbio_get( 'jobtitle_size' ) ); ?>">
								</div>
							</div>

							<p class="tb-section-label" style="margin-top:8px;"><?php esc_html_e( 'Bio / Description', 'trustbio' ); ?></p>
							<div class="tb-form-grid-2">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Color', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" name="settings[bio_color]" class="tb-color-swatch tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'bio_color' ) ); ?>">
										<input type="text" class="tb-input tb-color-text" value="<?php echo esc_attr( $trustbio_get( 'bio_color' ) ); ?>" maxlength="7">
									</div>
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Font Size (px)', 'trustbio' ); ?></label>
									<input type="number" name="settings[bio_size]" class="tb-input tb-preview-trigger" min="11" max="20" value="<?php echo esc_attr( $trustbio_get( 'bio_size' ) ); ?>">
								</div>
							</div>

							<div class="tb-form-group">
								<label class="tb-label" for="tb-bio-limit"><?php esc_html_e( 'Bio Character Limit', 'trustbio' ); ?></label>
								<input type="number" id="tb-bio-limit" name="settings[bio_limit]" class="tb-input tb-preview-trigger" min="0" max="2000" value="<?php echo esc_attr( $trustbio_get( 'bio_limit' ) ); ?>" style="max-width:120px;">
								<p class="tb-input-hint"><?php esc_html_e( 'Truncate bio to this many characters in the author box. Set to 0 for no limit.', 'trustbio' ); ?></p>
							</div>

							<p class="tb-section-label" style="margin-top:8px;"><?php esc_html_e( 'Category Label', 'trustbio' ); ?></p>
							<div class="tb-form-grid-3">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Color', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" name="settings[label_color]" class="tb-color-swatch tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'label_color' ) ); ?>">
										<input type="text" class="tb-input tb-color-text" value="<?php echo esc_attr( $trustbio_get( 'label_color' ) ); ?>" maxlength="7">
									</div>
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Font Size (px)', 'trustbio' ); ?></label>
									<input type="number" name="settings[label_size]" class="tb-input tb-preview-trigger" min="9" max="16" value="<?php echo esc_attr( $trustbio_get( 'label_size' ) ); ?>">
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Weight', 'trustbio' ); ?></label>
									<select name="settings[label_weight]" class="tb-select tb-preview-trigger">
										<?php foreach ( array( '400' => 'Regular', '600' => 'SemiBold', '700' => 'Bold', '800' => 'ExtraBold' ) as $trustbio_wv => $trustbio_wl ) : ?>
											<option value="<?php echo esc_attr( $trustbio_wv ); ?>" <?php selected( $trustbio_get( 'label_weight' ), $trustbio_wv ); ?>><?php echo esc_html( $trustbio_wl ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
						</div>

						<!-- TAB: Social Icons -->
						<div class="tb-tab-pane" data-tab-pane="icons" style="padding:20px;">

							<div class="tb-form-grid-2">
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Icon Size (px)', 'trustbio' ); ?></label>
									<input type="range" name="settings[icon_size]" class="tb-range tb-preview-trigger" min="12" max="32" value="<?php echo esc_attr( $trustbio_get( 'icon_size' ) ); ?>" id="tb-icon-size-range">
									<div style="text-align:right;font-size:12px;color:var(--tb-gray-500);margin-top:2px;"><span id="tb-icon-size-range-display"><?php echo esc_html( $trustbio_get( 'icon_size' ) ); ?></span>px</div>
								</div>
								<div class="tb-form-group">
									<label class="tb-label"><?php esc_html_e( 'Icon Color', 'trustbio' ); ?></label>
									<div class="tb-color-row">
										<input type="color" name="settings[icon_color]" class="tb-color-swatch tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'icon_color' ) ?: '#374151' ); ?>">
										<input type="text" class="tb-input tb-color-text" value="<?php echo esc_attr( $trustbio_get( 'icon_color' ) ?: '#374151' ); ?>" maxlength="7" placeholder="#374151">
									</div>
									<p class="tb-input-hint"><?php esc_html_e( 'Applies to Filled Color and Outline styles.', 'trustbio' ); ?></p>
								</div>
							</div>

							<div class="tb-form-group">
								<label class="tb-label"><?php esc_html_e( 'Icon Style', 'trustbio' ); ?></label>
								<div class="tb-icon-style-picker">
									<?php
									$trustbio_icon_styles = array(
										'color'      => __( 'Filled Color', 'trustbio' ),
										'monochrome' => __( 'Monochrome', 'trustbio' ),
										'outline'    => __( 'Outline', 'trustbio' ),
									);
									foreach ( $trustbio_icon_styles as $trustbio_iv => $trustbio_il ) :
									?>
									<label class="tb-icon-style-card <?php echo esc_attr( $trustbio_get( 'icon_style' ) === $trustbio_iv ? 'is-active' : '' ); ?>">
										<input type="radio" name="settings[icon_style]" class="tb-preview-trigger" value="<?php echo esc_attr( $trustbio_iv ); ?>" <?php checked( $trustbio_get( 'icon_style' ), $trustbio_iv ); ?>>
										<span class="tb-icon-style-demo tb-icon-demo-<?php echo esc_attr( $trustbio_iv ); ?>">
											<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.213 5.567zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
										</span>
										<span><?php echo esc_html( $trustbio_il ); ?></span>
									</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<!-- TAB: Advanced -->
						<div class="tb-tab-pane" data-tab-pane="advanced" style="padding:20px;">

							<p class="tb-section-label"><?php esc_html_e( 'Review Date', 'trustbio' ); ?></p>
							<div class="tb-form-group">
								<div class="tb-toggle-wrap">
									<label class="tb-toggle">
										<input type="hidden" name="settings[show_review_date]" value="0">
										<input type="checkbox" name="settings[show_review_date]" class="tb-preview-trigger" value="1" <?php checked( $trustbio_get( 'show_review_date' ), true ); ?>>
										<span class="tb-toggle__track"></span>
									</label>
									<span class="tb-toggle-label"><?php esc_html_e( 'Show Last Reviewed Date', 'trustbio' ); ?></span>
								</div>
								<p class="tb-input-hint"><?php esc_html_e( 'Displays the review date set via the Review Date meta box, when the post\'s reviewer matches this author.', 'trustbio' ); ?></p>
							</div>

							<hr class="tb-divider">
							<div class="tb-form-group">
								<label class="tb-label" for="tb-custom-class"><?php esc_html_e( 'Additional CSS Class(es)', 'trustbio' ); ?></label>
								<input type="text" id="tb-custom-class" name="settings[custom_class]" class="tb-input tb-preview-trigger" value="<?php echo esc_attr( $trustbio_get( 'custom_class' ) ); ?>" placeholder="my-box special-reviewer">
								<p class="tb-input-hint"><?php esc_html_e( 'Space-separated CSS classes added to the author box wrapper. Style them in Appearance → Customize → Additional CSS.', 'trustbio' ); ?></p>
							</div>
							<div class="tb-form-group" style="background:#f8faff;border:1px solid #e0e7ff;border-radius:8px;padding:14px;">
								<p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#3730a3;"><?php esc_html_e( 'How to add custom styles', 'trustbio' ); ?></p>
								<p style="margin:0;font-size:12px;color:#4b5563;line-height:1.6;"><?php esc_html_e( 'Go to Appearance → Customize → Additional CSS and target your author box:', 'trustbio' ); ?></p>
								<code style="display:block;margin-top:8px;background:#e0e7ff;padding:8px;border-radius:4px;font-size:11px;">.trustbio-box { /* your styles */ }<br>.my-box { /* class from above field */ }</code>
							</div>
						</div>
					</div><!-- .tb-card (tabs) -->
				</div><!-- .tb-builder-controls -->

				<!-- ── RIGHT: Live Preview ── -->
				<div class="tb-builder-preview-col">
					<div class="tb-card tb-builder-preview-card">
						<div class="tb-card-header">
							<h3 class="tb-card-title" style="font-size:13px;"><?php esc_html_e( 'Live Preview', 'trustbio' ); ?></h3>
							<div style="display:flex;align-items:center;gap:6px;">
								<span class="tb-preview-updating" id="tb-preview-spinner" style="display:none;"><span class="tb-spinner"></span></span>
								<button type="button" class="tb-btn tb-btn-ghost tb-btn-sm" id="tb-refresh-preview">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/></svg>
								</button>
							</div>
						</div>
						<div class="tb-card-body tb-card-body--flush">
							<div class="tb-box-preview-wrap" id="tb-live-preview">
								<div class="tb-box-preview-inner" id="tb-preview-content">
									<div class="tb-empty" style="padding:40px 20px;">
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
				<span class="tb-save-bar__status" id="tb-box-save-status"></span>
				<div class="tb-save-bar__actions">
					<button type="submit" id="tb-save-bar-btn" class="tb-btn tb-btn-primary"><?php esc_html_e( 'Save Box', 'trustbio' ); ?></button>
				</div>
			</div>
		</form>

	</div><!-- .trustbio-admin-wrap -->

	

	

	<?php
	return; // Do not render the list below.
}
// ── LIST MODE ─────────────────────────────────────────────────
$trustbio_all_boxes = $trustbio_box_class->get_all( 'default' );

?>

<div class="trustbio-admin-wrap">

	<div class="tb-page-header">
		<div class="tb-page-header__left">
			<div class="tb-page-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
			</div>
			<div class="tb-page-header__text">
				<h1 class="tb-page-title"><?php esc_html_e( 'Author Boxes', 'trustbio' ); ?></h1>
				<p class="tb-page-subtitle"><?php esc_html_e( 'Design and manage author box layouts for your site.', 'trustbio' ); ?></p>
			</div>
		</div>
		<div class="tb-page-header__actions">
			<button type="button" id="tb-new-box-btn" class="tb-btn tb-btn-primary">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:15px;height:15px;"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Add New Box', 'trustbio' ); ?>
			</button>
		</div>
	</div>

	<div class="tb-notice-area" id="tb-notice-area"></div>

	<!-- ===== SAVED BOXES LIST ===== -->
	<div class="tb-content-area">
		<?php if ( empty( $trustbio_all_boxes ) ) : ?>
			<div class="tb-card">
				<div class="tb-empty-hero">
					<div class="tb-empty-hero__icon tb-empty-hero__icon--violet">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
					</div>
					<h3 class="tb-empty-hero__title"><?php esc_html_e( 'Design your first author box', 'trustbio' ); ?></h3>
					<p class="tb-empty-hero__text"><?php esc_html_e( 'Choose from 9 professional layouts. Every setting is fully customizable — colors, fonts, social icons, and more.', 'trustbio' ); ?></p>
					<div class="tb-empty-hero__chips">
						<span class="tb-empty-hero__chip"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php esc_html_e( '9 unique layouts', 'trustbio' ); ?></span>
						<span class="tb-empty-hero__chip"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php esc_html_e( 'Live preview builder', 'trustbio' ); ?></span>
						<span class="tb-empty-hero__chip"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><?php esc_html_e( 'Auto-insert on posts', 'trustbio' ); ?></span>
					</div>
					<button type="button" id="tb-new-box-btn-empty" class="tb-btn tb-btn-primary"><?php esc_html_e( '+ Create First Author Box', 'trustbio' ); ?></button>
				</div>
			</div>
		<?php else : ?>

		<div class="tb-boxes-grid">
			<?php foreach ( $trustbio_all_boxes as $trustbio_box ) : ?>
			<div class="tb-box-card <?php echo $trustbio_box->is_default ? 'is-default' : ''; ?>" id="tb-box-card-<?php echo esc_attr( $trustbio_box->id ); ?>">
				<?php if ( $trustbio_box->is_default ) : ?>
				<div class="tb-box-card__default-badge"><?php esc_html_e( '★ Default', 'trustbio' ); ?></div>
				<?php endif; ?>
				<div class="tb-box-card__preview">
					<?php echo $trustbio_box_class->render_box_card_preview( $trustbio_box ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="tb-box-card__footer">
					<div class="tb-box-card__info">
						<span class="tb-box-card__title"><?php echo esc_html( $trustbio_box->title ); ?></span>
						<span class="tb-box-card__layout tb-text-mono"><?php echo esc_html( $trustbio_box->layout ); ?></span>
					</div>
					<div class="tb-box-card__actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-boxes&action=edit&id=' . $trustbio_box->id ) ); ?>" class="tb-btn tb-btn-secondary tb-btn-sm"><?php esc_html_e( 'Edit', 'trustbio' ); ?></a>
						<button type="button" class="tb-btn tb-btn-ghost tb-btn-sm tb-box-more" data-id="<?php echo esc_attr( $trustbio_box->id ); ?>" data-default="<?php echo esc_attr( $trustbio_box->is_default ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
						</button>
					</div>
				</div>
				<div class="tb-box-dropdown" id="tb-dropdown-<?php echo esc_attr( $trustbio_box->id ); ?>" style="display:none;">
					<?php if ( ! $trustbio_box->is_default ) : ?>
					<button type="button" class="tb-dropdown-item tb-set-default-box" data-id="<?php echo esc_attr( $trustbio_box->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_set_default_box' ) ); ?>"><?php esc_html_e( 'Set as Default', 'trustbio' ); ?></button>
					<?php endif; ?>
					<button type="button" class="tb-dropdown-item tb-duplicate-box" data-id="<?php echo esc_attr( $trustbio_box->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_duplicate_box' ) ); ?>"><?php esc_html_e( 'Duplicate', 'trustbio' ); ?></button>
					<?php if ( ! $trustbio_box->is_default ) : ?>
					<button type="button" class="tb-dropdown-item tb-dropdown-danger tb-delete-box" data-id="<?php echo esc_attr( $trustbio_box->id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'trustbio_delete_box' ) ); ?>"><?php esc_html_e( 'Delete', 'trustbio' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>

		<?php endif; // end empty check ?>
	</div><!-- .tb-content-area -->

</div><!-- .trustbio-admin-wrap -->

<!-- ===================================================== -->
<!-- CHOOSE A LAYOUT MODAL (9 layouts)                     -->
<!-- ===================================================== -->
<div id="tb-new-box-modal" class="tb-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="tb-new-box-modal-title">
	<div class="tb-modal tb-starter-modal tb-starter-modal--wide">
		<div class="tb-starter-modal__header">
			<div class="tb-starter-modal__title-wrap">
				<div class="tb-starter-modal__icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
				</div>
				<div>
					<h2 id="tb-new-box-modal-title"><?php esc_html_e( 'Choose a Layout', 'trustbio' ); ?></h2>
					<p><?php esc_html_e( 'Pick a design — every setting is fully customizable after.', 'trustbio' ); ?></p>
				</div>
			</div>
			<button type="button" class="tb-starter-modal__close" id="tb-new-box-modal-close" aria-label="<?php esc_attr_e( 'Close', 'trustbio' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
			</button>
		</div>
		<div class="tb-starter-modal__body tb-starter-modal__body--scroll">
			<div class="tb-starter-grid">

				<!-- 0: Classic Byline -->
				<button type="button" class="tb-starter-card" data-template-id="byline">
					<div class="tb-starter-card__visual">
						<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;display:flex;gap:10px;align-items:flex-start;width:100%;">
							<div style="width:28px;height:28px;border-radius:50%;background:#c7d2fe;flex-shrink:0;margin-top:2px;"></div>
							<div style="flex:1;display:flex;flex-direction:column;gap:4px;">
								<div style="height:5px;background:#818cf8;border-radius:2px;width:65%;"></div>
								<div style="height:3.5px;background:#a5b4fc;border-radius:2px;width:45%;"></div>
								<div style="height:3px;background:#e0e7ff;border-radius:2px;margin-top:3px;"></div>
								<div style="height:3px;background:#e0e7ff;border-radius:2px;width:80%;"></div>
							</div>
						</div>
					</div>
					<div class="tb-starter-card__label"><?php esc_html_e( 'Classic Byline', 'trustbio' ); ?></div>
					<div class="tb-starter-card__desc"><?php esc_html_e( 'Avatar · name · bio · socials', 'trustbio' ); ?></div>
				</button>


				<!-- 2: Quote Card -->
				<button type="button" class="tb-starter-card" data-template-id="quote">
					<div class="tb-starter-card__visual">
						<div style="background:#fafaf9;border:1px solid #e7e5e4;border-radius:8px;padding:10px 12px;display:flex;gap:10px;align-items:flex-start;width:100%;">
							<div style="flex:1;">
								<div style="font-size:24px;line-height:1;color:#a8a29e;margin-bottom:4px;font-family:Georgia,serif;">"</div>
								<div style="height:3px;background:#a8a29e;border-radius:2px;margin-bottom:3px;"></div>
								<div style="height:3px;background:#a8a29e;border-radius:2px;width:70%;margin-bottom:3px;"></div>
								<div style="height:3px;background:#d4c5c0;border-radius:2px;width:50%;"></div>
							</div>
							<div style="display:flex;flex-direction:column;align-items:center;gap:4px;padding-top:4px;flex-shrink:0;">
								<div style="width:22px;height:22px;border-radius:50%;background:#d6d3d1;"></div>
								<div style="height:3px;background:#a8a29e;border-radius:2px;width:28px;"></div>
							</div>
						</div>
					</div>
					<div class="tb-starter-card__label"><?php esc_html_e( 'Quote Card', 'trustbio' ); ?></div>
					<div class="tb-starter-card__desc"><?php esc_html_e( 'Bio as serif pull-quote + CTA', 'trustbio' ); ?></div>
				</button>



				<!-- 5: Minimal Inline -->
				<button type="button" class="tb-starter-card" data-template-id="minimal-inline">
					<div class="tb-starter-card__visual">
						<div style="border-top:2px solid #111827;border-bottom:1px solid #e5e7eb;padding:10px 4px;display:flex;gap:9px;align-items:flex-start;width:100%;">
							<div style="width:24px;height:24px;border-radius:50%;background:#e5e7eb;flex-shrink:0;"></div>
							<div style="flex:1;display:flex;flex-direction:column;gap:4px;">
								<div style="height:5px;background:#111827;border-radius:2px;width:55%;"></div>
								<div style="height:3.5px;background:#9ca3af;border-radius:2px;width:35%;"></div>
								<div style="height:3px;background:#e5e7eb;border-radius:2px;margin-top:2px;"></div>
								<div style="height:3px;background:#e5e7eb;border-radius:2px;width:75%;"></div>
							</div>
						</div>
					</div>
					<div class="tb-starter-card__label"><?php esc_html_e( 'Minimal Inline', 'trustbio' ); ?></div>
					<div class="tb-starter-card__desc"><?php esc_html_e( 'Hairline rules, no background', 'trustbio' ); ?></div>
				</button>

				<!-- 6: Card Simple -->
				<button type="button" class="tb-starter-card" data-template-id="simple">
					<div class="tb-starter-card__visual">
						<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;display:flex;gap:8px;align-items:flex-start;width:100%;">
							<div style="width:28px;height:28px;border-radius:4px;background:#e0e7ff;flex-shrink:0;"></div>
							<div style="width:1px;background:#e5e7eb;align-self:stretch;"></div>
							<div style="flex:1;display:flex;flex-direction:column;gap:4px;">
								<div style="height:5px;background:#3730a3;border-radius:2px;width:60%;"></div>
								<div style="height:3px;background:#c7d2fe;border-radius:2px;"></div>
								<div style="height:3px;background:#c7d2fe;border-radius:2px;width:70%;"></div>
								<div style="height:13px;background:#4f46e5;border-radius:10px;width:36px;margin-top:3px;"></div>
							</div>
						</div>
					</div>
					<div class="tb-starter-card__label"><?php esc_html_e( 'Card Simple', 'trustbio' ); ?></div>
					<div class="tb-starter-card__desc"><?php esc_html_e( 'Square avatar · divider · Follow button', 'trustbio' ); ?></div>
				</button>



			</div><!-- .tb-starter-grid -->
		</div><!-- .tb-starter-modal__body -->
	</div><!-- .tb-starter-modal -->
</div><!-- #tb-new-box-modal -->


