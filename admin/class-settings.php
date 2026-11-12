<?php
/**
 * Settings page — premium SaaS-style dashboard.
 * Tabs: Overview | General | Author Boxes | Review Bars | Advanced | Shortcodes | Maintenance
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Settings
 */
class TrustBio_Settings {

	/** All toggleable feature slugs. */
	/**
	 * Every togglable module slug, free and add-on alike.
	 *
	 * Derived from get_feature_info() rather than hardcoded, because that is
	 * the list the Features screen actually renders. A hardcoded list of only
	 * the free slugs silently discarded every add-on module on save: the
	 * add-on's checkboxes came back unchecked, its toggles could never be
	 * switched on, and its screens disappeared from the nav.
	 *
	 * @return string[]
	 */
	private static function feature_slugs(): array {
		return array_keys( self::get_feature_info() );
	}

	// =========================================================
	// Page render
	// =========================================================

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access TrustBio settings.', 'trustbio' ) );
		}

		$s          = self::get_all_settings();
		$feat_opt   = get_option( 'trustbio_enabled_features', null );
		$all_on     = null === $feat_opt;
		$feat_arr   = $all_on ? self::feature_slugs() : (array) $feat_opt;
		$stats      = self::get_stats();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$wp_roles   = wp_roles()->get_names();

		trustbio_layout_start( 'trustbio-settings' );
		?>
		<nav class="trustbio-sp-htab-nav">
			<?php
			$tabs = array(
				'overview'     => array( 'icon' => 'overview',    'label' => __( 'Overview',     'trustbio' ) ),
				'general'      => array( 'icon' => 'general',     'label' => __( 'General',      'trustbio' ) ),
				'author-boxes' => array( 'icon' => 'boxes',       'label' => __( 'Author Boxes', 'trustbio' ) ),
				'review-bars'  => array( 'icon' => 'review',      'label' => __( 'Review Bars',  'trustbio' ) ),
				'advanced'     => array( 'icon' => 'advanced',    'label' => __( 'Advanced',    'trustbio' ) ),
				'shortcodes'   => array( 'icon' => 'shortcodes',  'label' => __( 'Shortcodes',  'trustbio' ) ),
				'maintenance'  => array( 'icon' => 'maintenance', 'label' => __( 'Maintenance', 'trustbio' ) ),
			);

			/*
			 * Switching a module off already removes its admin page, its nav
			 * entry and its front-end output — but its Settings tab stayed,
			 * offering settings for something that no longer runs. The add-on's
			 * modules each gate their own tab on the same toggle; the one core
			 * module with a tab of its own has to do the same.
			 */
			if ( ! trustbio_feature_enabled( 'review_bars' ) ) {
				unset( $tabs['review-bars'] );
			}

			/**
			 * Filters the Settings screen tabs.
			 *
			 * The TrustBio Pro add-on registers its own tabs here, and renders
			 * their panels on the trustbio_settings_panels action below.
			 *
			 * @since 1.0.0
			 * @param array<string,array> $tabs Tab id => icon/label.
			 */
			$core_tab_ids = array_keys( $tabs );

			$tabs = (array) apply_filters( 'trustbio_settings_tabs', $tabs );

			/*
			 * Anything the filter added is an add-on tab, so the first one of
			 * them opens a new row in the (wrapping) strip. Derived by
			 * comparing the ids before and after the filter rather than by
			 * asking add-ons to declare a group: that keeps the filter's
			 * contract exactly as documented above, and works for any add-on,
			 * including ones that are not ours.
			 */
			$tb_addon_row_opened = false;

			foreach ( $tabs as $id => $tab ) :
				$tb_is_addon = ! in_array( $id, $core_tab_ids, true );

				if ( $tb_is_addon && ! $tb_addon_row_opened ) :
					$tb_addon_row_opened = true;
					?>
					<span class="trustbio-sp-nav-break" aria-hidden="true"></span>
					<?php
				endif;
				?>
				<button type="button" class="trustbio-sp-nav-item<?php echo $tb_is_addon ? ' trustbio-sp-nav-item--addon' : ''; ?>" data-tab="<?php echo esc_attr( $id ); ?>">
					<span class="trustbio-sp-nav-icon"><?php echo self::svg_icon( $tab['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="trustbio-sp-nav-label"><?php echo esc_html( $tab['label'] ); ?></span>
				</button>
			<?php endforeach; ?>
		</nav>
		<div class="trustbio-sp-panels">
			<?php
			self::panel_overview( $stats, $feat_arr, $all_on );
			self::panel_general( $s, $post_types, $wp_roles );
			self::panel_author_boxes( $s );
			if ( trustbio_feature_enabled( 'review_bars' ) ) {
				self::panel_review_bars( $s );
			}
			self::panel_advanced( $s );
			self::panel_shortcodes();
			self::panel_maintenance( $s );

			/**
			 * Fires inside the Settings panel container.
			 *
			 * The TrustBio Pro add-on renders the panels for its own tabs here.
			 *
			 * @since 1.0.0
			 * @param array<string,mixed> $settings Current settings array.
			 */
			do_action( 'trustbio_settings_panels', $s );
			?>
		</div>
		<?php
		trustbio_layout_end();
	}

	// =========================================================
	// Panel methods
	// =========================================================

	private static function panel_overview( array $stats, array $feat_arr, bool $all_on ): void {
		?>
		<div class="trustbio-sp-panel" data-panel="overview">
			<div class="trustbio-sp-panel-header">
				<h2><?php esc_html_e( 'TrustBio Overview', 'trustbio' ); ?></h2>
				<p><?php esc_html_e( 'A summary of your E-E-A-T setup and enabled feature modules.', 'trustbio' ); ?></p>
			</div>
			<div class="trustbio-sp-stats-grid">
				<?php
				$stat_items = array(
					array( 'label' => __( 'Active Authors',   'trustbio' ), 'value' => $stats['authors'],     'color' => '#4f46e5' ),
					array( 'label' => __( 'Author Boxes',     'trustbio' ), 'value' => $stats['boxes'],       'color' => '#0891b2' ),
					array( 'label' => __( 'Review Bars',      'trustbio' ), 'value' => $stats['review_bars'], 'color' => '#059669' ),
					array( 'label' => __( 'Posts w/ Author',  'trustbio' ), 'value' => $stats['posts'],       'color' => '#d97706' ),
				);
				foreach ( $stat_items as $si ) : ?>
					<div class="trustbio-sp-stat-card">
						<div class="trustbio-sp-stat-icon" style="--card-color:<?php echo esc_attr( $si['color'] ); ?>">
							<?php echo self::svg_icon( 'stat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="trustbio-sp-stat-body">
							<div class="trustbio-sp-stat-number"><?php echo esc_html( $si['value'] ); ?></div>
							<div class="trustbio-sp-stat-label"><?php echo esc_html( $si['label'] ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<h3 class="trustbio-sp-section-title"><?php esc_html_e( 'Feature Status', 'trustbio' ); ?></h3>
			<div class="trustbio-sp-feature-grid">
				<?php foreach ( self::get_feature_info() as $slug => $fi ) :
					$trustbio_active = $all_on || in_array( $slug, $feat_arr, true );
					?>
					<div class="trustbio-sp-feature-card <?php echo $trustbio_active ? 'is-active' : 'is-inactive'; ?>">
						<div class="trustbio-sp-feature-status">
							<?php echo $trustbio_active ? self::svg_icon( 'check' ) : self::svg_icon( 'off' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="trustbio-sp-feature-info">
							<div class="trustbio-sp-feature-name"><?php echo esc_html( $fi['name'] ); ?></div>
							<div class="trustbio-sp-feature-desc"><?php echo esc_html( $fi['short'] ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function panel_general( array $s, array $post_types, array $wp_roles ): void {
		?>
		<div class="trustbio-sp-panel" data-panel="general" style="display:none">
			<div class="trustbio-sp-panel-header">
				<h2><?php esc_html_e( 'General Settings', 'trustbio' ); ?></h2>
				<p><?php esc_html_e( 'Post types, user roles, contribution display, and author archive URLs.', 'trustbio' ); ?></p>
			</div>

			<h3 class="trustbio-sp-section-title"><?php esc_html_e( 'Post Types & Roles', 'trustbio' ); ?></h3>
			<div class="trustbio-sp-settings-list">
				<?php
				self::render_checkbox_group( 'enabled_post_types', $s['enabled_post_types'], $post_types,
					__( 'Show Author Box on', 'trustbio' ), __( 'Author boxes are auto-inserted on these post types.', 'trustbio' ) );
				self::render_checkbox_group( 'allowed_roles', $s['allowed_roles'], $wp_roles,
					__( 'Allowed Editor Roles', 'trustbio' ), __( 'Roles that can assign authors to posts.', 'trustbio' ) );
				self::render_toggle( 'allow_guest_no_email', $s['allow_guest_no_email'],
					__( 'Allow Guest Without Email', 'trustbio' ), __( 'Permit creating guest authors without an email address.', 'trustbio' ) );
				self::render_toggle( 'show_powered_by', $s['show_powered_by'],
					__( 'Show "Powered by TrustBio"', 'trustbio' ), __( 'Display a small attribution link in the author box footer.', 'trustbio' ) );
				?>
			</div>

			<h3 class="trustbio-sp-section-title" style="margin-top:28px"><?php esc_html_e( 'Contribution Count', 'trustbio' ); ?></h3>
			<div class="trustbio-sp-settings-list">
				<?php
				self::render_toggle( 'show_contribution_count', $s['show_contribution_count'],
					__( 'Show Contribution Count', 'trustbio' ), __( 'Display how many posts this author has contributed.', 'trustbio' ) );
				self::render_text( 'contribution_noun_plural', $s['contribution_noun_plural'],
					__( 'Contribution Noun (plural)', 'trustbio' ), __( 'e.g. "articles", "posts", "recipes"', 'trustbio' ) );
				self::render_number( 'contribution_min_count', $s['contribution_min_count'],
					__( 'Minimum to Display', 'trustbio' ), __( 'Only show the count once it reaches this number.', 'trustbio' ), 0, 100 );
				?>
			</div>

			<h3 class="trustbio-sp-section-title" style="margin-top:28px"><?php esc_html_e( 'Author Archive & Profiles', 'trustbio' ); ?></h3>
			<div class="trustbio-sp-settings-list">
				<?php
				self::render_url_base( 'author_url_base', $s['author_url_base'],
					__( 'Author URL Base', 'trustbio' ), __( 'URL segment used for author profile pages.', 'trustbio' ) );
				self::render_toggle( 'enable_archive_registered', $s['enable_archive_registered'],
					__( 'Archive for Registered Authors', 'trustbio' ), __( 'Enable public profile pages for WP-linked authors.', 'trustbio' ) );
				self::render_toggle( 'enable_archive_guest', $s['enable_archive_guest'],
					__( 'Archive for Guest Authors', 'trustbio' ), __( 'Enable public profile pages for guest (non-WP) authors.', 'trustbio' ) );
				self::render_toggle( 'archive_enabled', $s['archive_enabled'],
					__( 'Enable Paginated Archives', 'trustbio' ), __( 'Show a paginated list of posts on each author profile page.', 'trustbio' ) );
				self::render_number( 'archive_per_page', $s['archive_per_page'],
					__( 'Posts Per Page', 'trustbio' ), __( 'How many posts to display per page in author archives.', 'trustbio' ), 1, 100 );
				self::render_color( 'archive_hero_color', $s['archive_hero_color'],
					__( 'Archive Hero Background', 'trustbio' ), __( 'Background colour for the author archive hero section.', 'trustbio' ) );
				self::render_color( 'archive_accent_color', $s['archive_accent_color'],
					__( 'Archive Accent Colour', 'trustbio' ), __( 'Accent colour for links and highlights in the archive.', 'trustbio' ) );
				self::render_select( 'gravatar_default', $s['gravatar_default'],
					__( 'Gravatar Fallback', 'trustbio' ), __( 'Default image when no Gravatar is found.', 'trustbio' ),
					array(
						'mystery'   => __( 'Mystery Person', 'trustbio' ),
						'identicon' => __( 'Identicon (geometric)', 'trustbio' ),
						'monsterid' => __( 'MonsterID', 'trustbio' ),
						'wavatar'   => __( 'Wavatar', 'trustbio' ),
						'retro'     => __( 'Retro (8-bit)', 'trustbio' ),
						'blank'     => __( 'Blank', 'trustbio' ),
					)
				);
				?>
			</div>
			<?php self::render_save_bar( 'settings' ); ?>
		</div>
		<?php
	}

	private static function panel_author_boxes( array $s ): void {
		?>
		<div class="trustbio-sp-panel" data-panel="author-boxes" style="display:none">
			<div class="trustbio-sp-panel-header">
				<h2><?php esc_html_e( 'Author Box Settings', 'trustbio' ); ?></h2>
				<p><?php esc_html_e( 'Control how and where author boxes are displayed on your posts.', 'trustbio' ); ?></p>
			</div>
			<div class="trustbio-sp-settings-list">
				<?php
				self::render_select( 'box_position', $s['box_position'],
					__( 'Auto-Insert Position', 'trustbio' ), __( 'Where the author box is inserted relative to post content.', 'trustbio' ),
					array(
						'after'  => __( 'After content', 'trustbio' ),
						'before' => __( 'Before content', 'trustbio' ),
						'both'   => __( 'Before and after', 'trustbio' ),
						'manual' => __( 'Manual only (shortcode / block)', 'trustbio' ),
					)
				);
				self::render_toggle( 'auto_insert_posts', $s['auto_insert_posts'],
					__( 'Auto-Insert on Posts', 'trustbio' ), __( 'Automatically append the author box to all posts.', 'trustbio' ) );
				self::render_toggle( 'auto_insert_pages', $s['auto_insert_pages'],
					__( 'Auto-Insert on Pages', 'trustbio' ), __( 'Automatically append the author box to all pages.', 'trustbio' ) );
				self::render_text( 'exclude_post_ids', $s['exclude_post_ids'],
					__( 'Exclude Post IDs', 'trustbio' ), __( 'Comma-separated IDs where the author box should not appear.', 'trustbio' ) );
				?>
			</div>
			<?php self::render_save_bar( 'settings' ); ?>
		</div>
		<?php
	}

	private static function panel_review_bars( array $s ): void {
		?>
		<div class="trustbio-sp-panel" data-panel="review-bars" style="display:none">
			<div class="trustbio-sp-panel-header">
				<h2><?php esc_html_e( 'Last Review Bar Settings', 'trustbio' ); ?></h2>
				<p><?php esc_html_e( 'Configure how last-reviewed dates are displayed and tracked.', 'trustbio' ); ?></p>
			</div>
			<div class="trustbio-sp-settings-list">
				<?php
				self::render_select( 'review_display', $s['review_display'],
					__( 'Review Date Display', 'trustbio' ), __( 'Where the review date appears on posts.', 'trustbio' ),
					array(
						'inline' => __( 'Inline in author box', 'trustbio' ),
						'bar'    => __( 'Separate review bar', 'trustbio' ),
						'both'   => __( 'Both inline and bar', 'trustbio' ),
						'none'   => __( 'Hidden (meta only)', 'trustbio' ),
					)
				);
				self::render_text( 'review_default_label', $s['review_default_label'],
					__( 'Default Review Label', 'trustbio' ), __( 'e.g. "Medically reviewed by" or "Fact-checked by"', 'trustbio' ) );
				self::render_number( 'review_stale_months', $s['review_stale_months'],
					__( 'Stale After (months)', 'trustbio' ), __( 'Content without a review in this many months is flagged as stale.', 'trustbio' ), 1, 60 );
				self::render_toggle( 'review_show_column', $s['review_show_column'],
					__( 'Show Column in Posts List', 'trustbio' ), __( 'Add a "Last Reviewed" column to the WP posts list table.', 'trustbio' ) );
				self::render_select( 'review_bar_position', $s['review_bar_position'],
					__( 'Review Bar Position', 'trustbio' ), __( 'Where the review bar appears within post content.', 'trustbio' ),
					array(
						'before_content' => __( 'Before content', 'trustbio' ),
						'after_content'  => __( 'After content', 'trustbio' ),
					)
				);
				self::render_toggle( 'review_with_author_box', $s['review_with_author_box'],
					__( 'Attach Bar to Author Box', 'trustbio' ), __( 'Render the review bar immediately adjacent to the author box.', 'trustbio' ) );
				?>
			</div>
			<?php self::render_save_bar( 'settings' ); ?>
		</div>
		<?php
	}

	private static function panel_advanced( array $s ): void {
		?>
		<div class="trustbio-sp-panel" data-panel="advanced" style="display:none">
			<div class="trustbio-sp-panel-header">
				<h2><?php esc_html_e( 'Advanced Settings', 'trustbio' ); ?></h2>
				<p><?php esc_html_e( 'Schema output, REST API, AI Readiness modules, and developer options.', 'trustbio' ); ?></p>
			</div>

			<h3 class="trustbio-sp-section-title"><?php esc_html_e( 'Schema / JSON-LD', 'trustbio' ); ?></h3>
			<div class="trustbio-sp-settings-list">
				<?php
				self::render_toggle( 'enable_schema', $s['enable_schema'],
					__( 'Enable Schema Output', 'trustbio' ), __( 'Inject Author/Person JSON-LD structured data into the &lt;head&gt;.', 'trustbio' ) );
				self::render_toggle( 'schema_compat_mode', $s['schema_compat_mode'],
					__( 'Schema Compatibility Mode', 'trustbio' ), __( 'Broader markup compatible with other schema plugins.', 'trustbio' ) );
				self::render_toggle( 'enable_rest_api', $s['enable_rest_api'],
					__( 'Enable REST API', 'trustbio' ), __( 'Expose TrustBio author data via the WP REST API.', 'trustbio' ) );
				self::render_toggle( 'debug_mode', $s['debug_mode'],
					__( 'Debug Mode', 'trustbio' ), __( 'Output extra diagnostic information (visible to admins only).', 'trustbio' ) );
				?>
			</div>

			<?php
			/**
			 * Fires at the bottom of the Advanced settings panel.
			 *
			 * The TrustBio Pro add-on renders its own advanced sections here.
			 *
			 * @since 1.0.0
			 * @param array<string,mixed> $settings Current settings array.
			 */
			do_action( 'trustbio_settings_advanced_sections', $s );
			?>
			<?php self::render_save_bar( 'settings' ); ?>
		</div>
		<?php
	}

	private static function panel_shortcodes(): void {
		$tbs_base = trailingslashit( home_url() );
		?>
		<div class="trustbio-sp-panel" data-panel="shortcodes" style="display:none">
			<div class="trustbio-sp-panel-header">
				<h2><?php esc_html_e( 'Shortcodes', 'trustbio' ); ?></h2>
				<p><?php esc_html_e( 'Copy and paste any shortcode into a post, page, or widget to embed TrustBio elements.', 'trustbio' ); ?></p>
			</div>

			<?php
			$tbs_groups = array(
				array(
					'title' => __( 'Author Box', 'trustbio' ),
					'items' => array(
						array(
							'tag'   => '[trustbio]',
							'desc'  => __( 'Outputs the author box for the current post. Place anywhere inside post content.', 'trustbio' ),
							'attrs' => array(
								'id="5"'                    => __( 'Show a specific author by their TrustBio author ID.', 'trustbio' ),
								'slug="dr-jane-smith"'      => __( 'Show a specific author by their URL slug.', 'trustbio' ),
								'box="Compact"'             => __( 'Use a specific author box by its title.', 'trustbio' ),
								'category="medical-review"' => __( 'Only show authors in a specific author category.', 'trustbio' ),
							),
						),
					),
				),
				array(
					'title' => __( 'Last Review Bar', 'trustbio' ),
					'items' => array(
						array(
							'tag'   => '[trustbio_review]',
							'desc'  => __( 'Inserts the last-reviewed bar. Useful when auto-insert is disabled.', 'trustbio' ),
							'attrs' => array(
								'post_id="42"'              => __( 'Show the review data for a different post.', 'trustbio' ),
								'style="inline"'            => __( 'Render as inline text instead of a bar.', 'trustbio' ),
								'label="Fact-checked by"'   => __( 'Override the review label for this instance.', 'trustbio' ),
							),
						),
					),
				),
			);

			/**
			 * Filters the documented shortcode reference groups.
			 *
			 * The TrustBio Pro add-on documents its own shortcodes here.
			 *
			 * @since 1.0.0
			 * @param array<int,array> $groups Shortcode reference groups.
			 */
			$tbs_groups = (array) apply_filters( 'trustbio_shortcode_reference', $tbs_groups );

			/*
			 * Skip anything that is not shaped like a group. An add-on that
			 * gets this filter's structure wrong should not be able to print
			 * PHP warnings across a user's Settings screen.
			 */
			$tbs_groups = array_filter(
				$tbs_groups,
				static function ( $tbs_candidate ) {
					return is_array( $tbs_candidate )
						&& ! empty( $tbs_candidate['title'] )
						&& ! empty( $tbs_candidate['items'] )
						&& is_array( $tbs_candidate['items'] );
				}
			);

			foreach ( $tbs_groups as $tbs_group ) : ?>
			<div class="trustbio-sp-sc-group">
				<h3 class="trustbio-sp-section-title"><?php echo esc_html( $tbs_group['title'] ); ?></h3>
				<?php foreach ( $tbs_group['items'] as $tbs_item ) : ?>
				<div class="trustbio-sp-sc-card">
					<div class="trustbio-sp-sc-tag-row">
						<code class="trustbio-sp-sc-tag"><?php echo esc_html( $tbs_item['tag'] ); ?></code>
						<button type="button" class="trustbio-sp-sc-copy" data-copy="<?php echo esc_attr( $tbs_item['tag'] ); ?>"><?php esc_html_e( 'Copy', 'trustbio' ); ?></button>
					</div>
					<p class="trustbio-sp-sc-desc"><?php echo esc_html( $tbs_item['desc'] ); ?></p>
					<?php if ( ! empty( $tbs_item['attrs'] ) ) : ?>
					<table class="trustbio-sp-sc-attrs">
						<thead><tr>
							<th><?php esc_html_e( 'Attribute', 'trustbio' ); ?></th>
							<th><?php esc_html_e( 'Description', 'trustbio' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $tbs_item['attrs'] as $tbs_attr => $tbs_attr_desc ) : ?>
						<tr>
							<td><code><?php echo esc_html( $tbs_attr ); ?></code></td>
							<td><?php echo esc_html( $tbs_attr_desc ); ?></td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>

		</div>
		<?php
	}

	private static function panel_maintenance( array $s ): void {
		?>
		<div class="trustbio-sp-panel" data-panel="maintenance" style="display:none">
			<div class="trustbio-sp-panel-header">
				<h2><?php esc_html_e( 'Maintenance', 'trustbio' ); ?></h2>
				<p><?php esc_html_e( 'Cache management, data export / import, and uninstall options.', 'trustbio' ); ?></p>
			</div>

			<div class="trustbio-sp-action-grid">
				<div class="trustbio-sp-action-card">
					<div class="trustbio-sp-action-card-title"><?php esc_html_e( 'Flush Rewrite Rules', 'trustbio' ); ?></div>
					<div class="trustbio-sp-action-card-desc"><?php esc_html_e( 'Regenerate URL rules after changing the author URL base.', 'trustbio' ); ?></div>
					<button type="button" id="tbsp-flush-rewrites" class="trustbio-sp-action-btn"><?php esc_html_e( 'Flush Rewrites', 'trustbio' ); ?></button>
					<span class="trustbio-sp-action-status" id="tbsp-flush-status"></span>
				</div>
				<div class="trustbio-sp-action-card">
					<div class="trustbio-sp-action-card-title"><?php esc_html_e( 'Clear Cache', 'trustbio' ); ?></div>
					<div class="trustbio-sp-action-card-desc"><?php esc_html_e( 'Delete all TrustBio transients and cached data.', 'trustbio' ); ?></div>
					<button type="button" id="tbsp-clear-cache" class="trustbio-sp-action-btn"><?php esc_html_e( 'Clear Cache', 'trustbio' ); ?></button>
					<span class="trustbio-sp-action-status" id="tbsp-cache-status"></span>
				</div>
				<div class="trustbio-sp-action-card">
					<div class="trustbio-sp-action-card-title"><?php esc_html_e( 'Export Data', 'trustbio' ); ?></div>
					<div class="trustbio-sp-action-card-desc"><?php esc_html_e( 'Download all authors, boxes, and review bars as a JSON file.', 'trustbio' ); ?></div>
					<button type="button" id="tbsp-export-data" class="trustbio-sp-action-btn"><?php esc_html_e( 'Export', 'trustbio' ); ?></button>
					<span class="trustbio-sp-action-status" id="tbsp-export-status"></span>
				</div>
			</div>

			<h3 class="trustbio-sp-section-title" style="margin-top:32px"><?php esc_html_e( 'Import Data', 'trustbio' ); ?></h3>
			<div class="trustbio-sp-settings-list">
				<div class="trustbio-sp-setting-row is-top">
					<div class="trustbio-sp-setting-info">
						<div class="trustbio-sp-setting-label"><?php esc_html_e( 'Import JSON', 'trustbio' ); ?></div>
						<div class="trustbio-sp-setting-desc"><?php esc_html_e( 'Paste a TrustBio export file. Existing records are updated; nothing is deleted.', 'trustbio' ); ?></div>
					</div>
					<div style="flex:1;max-width:400px">
						<textarea id="tbsp-import-json" class="trustbio-sp-textarea" placeholder='{"version":"1.6.0",...}'></textarea>
						<div style="margin-top:10px;display:flex;align-items:center;gap:12px">
							<button type="button" id="tbsp-import-btn" class="trustbio-sp-action-btn"><?php esc_html_e( 'Import', 'trustbio' ); ?></button>
							<span class="trustbio-sp-action-status" id="tbsp-import-status"></span>
						</div>
					</div>
				</div>
			</div>

			<h3 class="trustbio-sp-section-title" style="margin-top:32px"><?php esc_html_e( 'Danger Zone', 'trustbio' ); ?></h3>
			<div class="trustbio-sp-settings-list">
				<?php self::render_toggle( 'delete_data_on_uninstall', $s['delete_data_on_uninstall'],
					__( 'Delete All Data on Uninstall', 'trustbio' ), __( 'Permanently remove all TrustBio data when the plugin is deleted. This cannot be undone.', 'trustbio' ) ); ?>
			</div>
			<?php self::render_save_bar( 'settings' ); ?>
		</div>
		<?php
	}

	// =========================================================
	// Data helpers
	// =========================================================

	private static function get_all_settings(): array {
		return array(
			'enabled_post_types'        => trustbio_get_enabled_post_types(),
			'allowed_roles'             => (array)  get_option( 'trustbio_allowed_roles',            array( 'administrator', 'editor', 'author', 'contributor' ) ),
			'show_powered_by'           => (bool)   get_option( 'trustbio_show_powered_by',          0 ),
			'allow_guest_no_email'      => (bool)   get_option( 'trustbio_allow_guest_no_email',     0 ),
			'show_contribution_count'   => (bool)   get_option( 'trustbio_show_contribution_count',  0 ),
			'contribution_noun_plural'  => (string) get_option( 'trustbio_contribution_noun_plural', 'articles' ),
			'contribution_min_count'    => (int)    get_option( 'trustbio_contribution_min_count',   1 ),
			'archive_enabled'           => (bool)   get_option( 'trustbio_archive_enabled',          0 ),
			'archive_per_page'          => (int)    get_option( 'trustbio_archive_per_page',         10 ),
			'archive_hero_color'        => (string) get_option( 'trustbio_archive_hero_color',       '#312e81' ),
			'archive_accent_color'      => (string) get_option( 'trustbio_archive_accent_color',     '#4f46e5' ),
			'enable_archive_registered' => (bool)   get_option( 'trustbio_enable_archive_registered', 1 ),
			'enable_archive_guest'      => (bool)   get_option( 'trustbio_enable_archive_guest',     1 ),
			'author_url_base'           => (string) get_option( 'trustbio_author_url_base',          'authors' ),
			'gravatar_default'          => (string) get_option( 'trustbio_gravatar_default',         'mystery' ),
			'box_position'              => (string) get_option( 'trustbio_box_position',             'after' ),
			'auto_insert_posts'         => (bool)   get_option( 'trustbio_auto_insert_posts',        1 ),
			'auto_insert_pages'         => (bool)   get_option( 'trustbio_auto_insert_pages',        0 ),
			'exclude_post_ids'          => (string) get_option( 'trustbio_exclude_post_ids',         '' ),
			'review_display'            => (string) get_option( 'trustbio_review_display',           'inline' ),
			'review_default_label'      => (string) get_option( 'trustbio_review_default_label',     'Medically reviewed by' ),
			'review_stale_months'       => (int)    get_option( 'trustbio_review_stale_months',      12 ),
			'review_show_column'        => (bool)   get_option( 'trustbio_review_show_column',       1 ),
			'review_bar_position'       => (string) get_option( 'trustbio_review_bar_position',      'before_content' ),
			'review_with_author_box'    => (bool)   get_option( 'trustbio_review_with_author_box',   0 ),
			'enable_schema'             => (bool)   get_option( 'trustbio_enable_schema',            1 ),
			'schema_compat_mode'        => (bool)   get_option( 'trustbio_schema_compat_mode',       1 ),
			'debug_mode'                => (bool)   get_option( 'trustbio_debug_mode',               0 ),
			'enable_rest_api'           => (bool)   get_option( 'trustbio_enable_rest_api',          1 ),
			'delete_data_on_uninstall'  => (bool)   get_option( 'trustbio_delete_data_on_uninstall', 0 ),
		);
	}

	private static function get_stats(): array {
		$trustbio_cached = wp_cache_get( 'trustbio_sp_stats', 'trustbio' );
		if ( false !== $trustbio_cached ) {
			return (array) $trustbio_cached;
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$trustbio_stats = array(
			'authors'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}trustbio_authors WHERE is_active = 1" ),
			'boxes'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}trustbio_author_boxes" ),
			'review_bars' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}trustbio_review_bars" ),
			'posts'       => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_trustbio_authors'" ),
		);
		// phpcs:enable
		wp_cache_set( 'trustbio_sp_stats', $trustbio_stats, 'trustbio', 5 * MINUTE_IN_SECONDS );
		return $trustbio_stats;
	}

	public static function get_feature_info(): array {
		$trustbio_features = array(
			'review_bars'  => array( 'name' => __( 'Last Review Bars', 'trustbio' ), 'short' => __( 'Show last-reviewed dates on posts.',   'trustbio' ), 'desc' => __( 'Expert review dates with bar layouts and stale tracking.', 'trustbio' ), 'icon' => 'review' ),
			'schema'       => array( 'name' => __( 'Schema Output',     'trustbio' ), 'short' => __( 'JSON-LD author & FAQ schema in head.', 'trustbio' ), 'desc' => __( 'Structured data Google uses for E-E-A-T evaluation.',       'trustbio' ), 'icon' => 'schema' ),
		);

		/**
		 * Filters the togglable TrustBio feature modules.
		 *
		 * The TrustBio Pro add-on registers its own modules here so they get
		 * their own on/off toggle on the Features screen.
		 *
		 * @since 1.0.0
		 * @param array<string,array> $features Feature slug => name/short/desc/icon.
		 */
		return (array) apply_filters( 'trustbio_feature_modules', $trustbio_features );
	}

	// =========================================================
	// AJAX: Save settings
	// =========================================================

	/**
	 * Recursively clean a posted settings payload.
	 *
	 * Strings go through sanitize_textarea_field(), which strips tags and
	 * invalid UTF-8 while keeping the newlines a multi-line setting needs.
	 * Scalars are cast, and array keys are sanitised unless they are numeric,
	 * which would otherwise turn a list into a set of empty-string keys.
	 *
	 * @param array<mixed> $trustbio_input Raw, unslashed payload.
	 * @param int          $trustbio_depth Guard against a pathological nesting depth.
	 * @return array<mixed>
	 */
	private static function sanitize_payload( array $trustbio_input, int $trustbio_depth = 0 ): array {
		if ( $trustbio_depth > 5 ) {
			return array();
		}

		$trustbio_clean = array();

		foreach ( $trustbio_input as $trustbio_key => $trustbio_value ) {
			$trustbio_key = is_int( $trustbio_key ) ? $trustbio_key : sanitize_key( (string) $trustbio_key );

			if ( is_array( $trustbio_value ) ) {
				$trustbio_clean[ $trustbio_key ] = self::sanitize_payload( $trustbio_value, $trustbio_depth + 1 );
				continue;
			}
			if ( is_bool( $trustbio_value ) || is_int( $trustbio_value ) || is_float( $trustbio_value ) ) {
				$trustbio_clean[ $trustbio_key ] = $trustbio_value;
				continue;
			}

			$trustbio_clean[ $trustbio_key ] = sanitize_textarea_field( (string) $trustbio_value );
		}

		return $trustbio_clean;
	}

	public function ajax_save(): void {
		check_ajax_referer( 'trustbio_save_settings', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'trustbio' ) ), 403 );
		}

		/*
		 * Sanitised once, here, rather than only at each point of use.
		 *
		 * Every setting this method stores is validated again below — as a
		 * key, an absint, a hex colour or a boolean — so the plugin itself was
		 * never at risk. The payload is also handed to the
		 * trustbio_settings_saved action further down, though, and an add-on
		 * listening there received whatever was posted, untouched. Cleaning it
		 * at the door means no listener can be the weak point.
		 */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by sanitize_payload() on the next line.
		$trustbio_raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$s            = self::sanitize_payload( (array) $trustbio_raw );

		// ── General ──
		$trustbio_post_types = isset( $s['enabled_post_types'] ) && is_array( $s['enabled_post_types'] )
			? array_map( 'sanitize_key', (array) $s['enabled_post_types'] ) : array( 'post', 'page' );
		update_option( 'trustbio_enabled_post_types', $trustbio_post_types );

		$trustbio_roles = isset( $s['allowed_roles'] ) && is_array( $s['allowed_roles'] )
			? array_map( 'sanitize_key', (array) $s['allowed_roles'] ) : array( 'administrator', 'editor', 'author', 'contributor' );
		update_option( 'trustbio_allowed_roles', $trustbio_roles );

		update_option( 'trustbio_show_powered_by',         ! empty( $s['show_powered_by'] ) ? 1 : 0 );
		update_option( 'trustbio_allow_guest_no_email',    ! empty( $s['allow_guest_no_email'] ) ? 1 : 0 );
		update_option( 'trustbio_show_contribution_count', ! empty( $s['show_contribution_count'] ) ? 1 : 0 );
		update_option( 'trustbio_contribution_noun_plural', sanitize_text_field( $s['contribution_noun_plural'] ?? 'articles' ) );
		update_option( 'trustbio_contribution_min_count',  absint( $s['contribution_min_count'] ?? 1 ) );
		update_option( 'trustbio_archive_enabled',            ! empty( $s['archive_enabled'] ) ? 1 : 0 );
		update_option( 'trustbio_archive_per_page',           absint( $s['archive_per_page'] ?? 10 ) );
		update_option( 'trustbio_enable_archive_registered',  ! empty( $s['enable_archive_registered'] ) ? 1 : 0 );
		update_option( 'trustbio_enable_archive_guest',       ! empty( $s['enable_archive_guest'] ) ? 1 : 0 );
		$trustbio_hero   = sanitize_hex_color( $s['archive_hero_color']   ?? '#312e81' );
		update_option( 'trustbio_archive_hero_color',   $trustbio_hero   ?: '#312e81' );
		$trustbio_accent = sanitize_hex_color( $s['archive_accent_color'] ?? '#4f46e5' );
		update_option( 'trustbio_archive_accent_color', $trustbio_accent ?: '#4f46e5' );
		$trustbio_url_base = sanitize_title( $s['author_url_base'] ?? 'authors' );
		update_option( 'trustbio_author_url_base', $trustbio_url_base ?: 'authors' );
		$trustbio_grav = sanitize_key( $s['gravatar_default'] ?? 'mystery' );
		if ( ! in_array( $trustbio_grav, array( 'mystery', 'identicon', 'monsterid', 'wavatar', 'retro', 'blank' ), true ) ) {
			$trustbio_grav = 'mystery';
		}
		update_option( 'trustbio_gravatar_default', $trustbio_grav );

		// ── Author Boxes ──
		$trustbio_box_pos = sanitize_key( $s['box_position'] ?? 'after' );
		if ( ! in_array( $trustbio_box_pos, array( 'after', 'before', 'both', 'manual' ), true ) ) {
			$trustbio_box_pos = 'after';
		}
		update_option( 'trustbio_box_position',      $trustbio_box_pos );
		update_option( 'trustbio_auto_insert_posts',  ! empty( $s['auto_insert_posts'] ) ? 1 : 0 );
		update_option( 'trustbio_auto_insert_pages',  ! empty( $s['auto_insert_pages'] ) ? 1 : 0 );
		update_option( 'trustbio_exclude_post_ids',   preg_replace( '/[^0-9,]/', '', sanitize_text_field( $s['exclude_post_ids'] ?? '' ) ) );

		// ── Review Bars ──
		$trustbio_rev_disp = sanitize_key( $s['review_display'] ?? 'inline' );
		if ( ! in_array( $trustbio_rev_disp, array( 'inline', 'bar', 'both', 'none' ), true ) ) {
			$trustbio_rev_disp = 'inline';
		}
		update_option( 'trustbio_review_display',        $trustbio_rev_disp );
		update_option( 'trustbio_review_default_label',   sanitize_text_field( $s['review_default_label'] ?? 'Medically reviewed by' ) );
		update_option( 'trustbio_review_stale_months',    absint( $s['review_stale_months'] ?? 12 ) );
		update_option( 'trustbio_review_show_column',     ! empty( $s['review_show_column'] ) ? 1 : 0 );
		$trustbio_bar_pos = sanitize_key( $s['review_bar_position'] ?? 'before_content' );
		if ( ! in_array( $trustbio_bar_pos, array( 'before_content', 'after_content' ), true ) ) {
			$trustbio_bar_pos = 'before_content';
		}
		update_option( 'trustbio_review_bar_position',    $trustbio_bar_pos );
		update_option( 'trustbio_review_with_author_box', ! empty( $s['review_with_author_box'] ) ? 1 : 0 );

		// ── Advanced ──
		update_option( 'trustbio_enable_schema',      ! empty( $s['enable_schema'] ) ? 1 : 0 );
		update_option( 'trustbio_schema_compat_mode', ! empty( $s['schema_compat_mode'] ) ? 1 : 0 );
		update_option( 'trustbio_debug_mode',         ! empty( $s['debug_mode'] ) ? 1 : 0 );
		update_option( 'trustbio_enable_rest_api',    ! empty( $s['enable_rest_api'] ) ? 1 : 0 );

		/**
		 * Fires while TrustBio settings are being saved.
		 *
		 * The TrustBio Pro add-on persists its own settings here. The array is
		 * the posted settings payload, unslashed and recursively sanitised —
		 * strings through sanitize_textarea_field(), keys through
		 * sanitize_key(). A listener should still validate each value for its
		 * own purpose, but it is never handed raw request data.
		 *
		 * @since 1.0.0
		 * @param array<string,mixed> $posted Sanitised settings payload.
		 */
		do_action( 'trustbio_settings_saved', $s );

		update_option( 'trustbio_delete_data_on_uninstall', ! empty( $s['delete_data_on_uninstall'] ) ? 1 : 0 );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'trustbio' ) ) );
	}

	// =========================================================
	// AJAX: Save features
	// =========================================================

	public function ajax_save_features(): void {
		check_ajax_referer( 'trustbio_save_features', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'trustbio' ) ), 403 );
		}

		$trustbio_raw     = isset( $_POST['features'] ) && is_array( $_POST['features'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			? array_map( 'sanitize_key', wp_unslash( $_POST['features'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();
		$trustbio_enabled = array_values( array_intersect( $trustbio_raw, self::feature_slugs() ) );
		update_option( 'trustbio_enabled_features', $trustbio_enabled );

		wp_send_json_success( array(
			'message' => __( 'Feature settings saved. Reload the page to see menu changes.', 'trustbio' ),
		) );
	}

	// =========================================================
	// AJAX: Flush rewrites
	// =========================================================

	public function ajax_flush_rewrites(): void {
		check_ajax_referer( 'trustbio_flush_rewrites', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_flush_rewrites' );

		$trustbio_base = sanitize_title( wp_unslash( $_POST['author_url_base'] ?? 'authors' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		update_option( 'trustbio_author_url_base', $trustbio_base );
		add_rewrite_rule( '^' . preg_quote( $trustbio_base, '/' ) . '/([^/]+)/?$', 'index.php?trustbio_author=$matches[1]', 'top' );
		flush_rewrite_rules();

		wp_send_json_success( array(
			'message' => __( 'Rewrite rules flushed. Author URLs are now active.', 'trustbio' ),
			'example' => trailingslashit( home_url( $trustbio_base . '/author-slug' ) ),
		) );
	}

	// =========================================================
	// AJAX: Clear cache
	// =========================================================

	public function ajax_clear_cache(): void {
		check_ajax_referer( 'trustbio_clear_cache', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_clear_cache' );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_trustbio_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_trustbio_' ) . '%'
		) );

		wp_send_json_success( array( 'message' => __( 'Cache cleared successfully.', 'trustbio' ) ) );
	}

	// =========================================================
	// AJAX: Export data
	// =========================================================

	public function ajax_export_data(): void {
		check_ajax_referer( 'trustbio_export_data', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_export_data' );

		global $wpdb;
		$trustbio_export = array(
			'version'      => TRUSTBIO_VERSION,
			'exported'     => gmdate( 'Y-m-d H:i:s' ),
			'categories'   => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}trustbio_author_categories", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			'fields'       => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}trustbio_author_fields", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			'authors'      => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}trustbio_authors", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			'boxes'        => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}trustbio_author_boxes", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			'field_values' => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}trustbio_author_field_values", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			'credentials'  => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}trustbio_author_credentials", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			'review_bars'  => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}trustbio_review_bars", ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		);

		wp_send_json_success( array(
			'data'     => wp_json_encode( $trustbio_export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			'filename' => 'trustbio-export-' . gmdate( 'Y-m-d' ) . '.json',
		) );
	}

	// =========================================================
	// AJAX: Import data
	// =========================================================

	public function ajax_import_data(): void {
		check_ajax_referer( 'trustbio_save_settings', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_save_settings' );

		if ( empty( $_POST['import_json'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No data provided.', 'trustbio' ) ) );
		}

		$trustbio_raw  = sanitize_textarea_field( wp_unslash( $_POST['import_json'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$trustbio_data = json_decode( $trustbio_raw, true );

		if ( ! is_array( $trustbio_data ) || empty( $trustbio_data['version'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import file. Please use a file exported from TrustBio.', 'trustbio' ) ) );
		}

		global $wpdb;
		$trustbio_counts = array( 'authors' => 0, 'boxes' => 0, 'review_bars' => 0 );

		if ( ! empty( $trustbio_data['authors'] ) && is_array( $trustbio_data['authors'] ) ) {
			foreach ( $trustbio_data['authors'] as $trustbio_author ) {
				if ( empty( $trustbio_author['display_name'] ) ) { continue; }
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->replace( $wpdb->prefix . 'trustbio_authors', array(
					'id'           => absint( $trustbio_author['id'] ?? 0 ) ?: null,
					'author_type'  => in_array( $trustbio_author['author_type'] ?? '', array( 'registered', 'guest' ), true ) ? $trustbio_author['author_type'] : 'guest',
					'wp_user_id'   => ! empty( $trustbio_author['wp_user_id'] ) ? absint( $trustbio_author['wp_user_id'] ) : null,
					'display_name' => sanitize_text_field( $trustbio_author['display_name'] ),
					'slug'         => sanitize_title( $trustbio_author['slug'] ?? $trustbio_author['display_name'] ),
					'email'        => sanitize_email( $trustbio_author['email'] ?? '' ),
					'website'      => esc_url_raw( $trustbio_author['website'] ?? '' ),
					'job_title'    => sanitize_text_field( $trustbio_author['job_title'] ?? '' ),
					'bio'          => wp_kses_post( $trustbio_author['bio'] ?? '' ),
					'avatar_url'   => esc_url_raw( $trustbio_author['avatar_url'] ?? '' ),
					'is_active'    => 1,
				) );
				++$trustbio_counts['authors'];
			}
		}
		if ( ! empty( $trustbio_data['boxes'] ) && is_array( $trustbio_data['boxes'] ) ) {
			foreach ( $trustbio_data['boxes'] as $trustbio_row ) {
				if ( empty( $trustbio_row['title'] ) ) { continue; }
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->replace( $wpdb->prefix . 'trustbio_author_boxes', array(
					'id'         => absint( $trustbio_row['id'] ?? 0 ) ?: null,
					'title'      => sanitize_text_field( $trustbio_row['title'] ),
					'layout'     => sanitize_key( $trustbio_row['layout'] ?? 'classic' ),
					'is_default' => (int) ( $trustbio_row['is_default'] ?? 0 ),
					'settings'   => wp_json_encode( json_decode( $trustbio_row['settings'] ?? '{}', true ) ),
				) );
				++$trustbio_counts['boxes'];
			}
		}
		if ( ! empty( $trustbio_data['review_bars'] ) && is_array( $trustbio_data['review_bars'] ) ) {
			foreach ( $trustbio_data['review_bars'] as $trustbio_row ) {
				if ( empty( $trustbio_row['title'] ) ) { continue; }
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->replace( $wpdb->prefix . 'trustbio_review_bars', array(
					'id'         => absint( $trustbio_row['id'] ?? 0 ) ?: null,
					'title'      => sanitize_text_field( $trustbio_row['title'] ),
					'layout'     => sanitize_key( $trustbio_row['layout'] ?? 'classic' ),
					'is_default' => (int) ( $trustbio_row['is_default'] ?? 0 ),
					'settings'   => wp_json_encode( json_decode( $trustbio_row['settings'] ?? '{}', true ) ),
				) );
				++$trustbio_counts['review_bars'];
			}
		}

		wp_send_json_success( array( 'message' => sprintf(
			/* translators: %1$d: total, %2$d: authors, %3$d: boxes, %4$d: review bars */
			__( 'Import complete. %1$d items: %2$d authors, %3$d boxes, %4$d review bars.', 'trustbio' ),
			array_sum( $trustbio_counts ),
			$trustbio_counts['authors'],
			$trustbio_counts['boxes'],
			$trustbio_counts['review_bars']
		) ) );
	}

	// =========================================================
	// Render helpers
	// =========================================================

	/*
	 * These are public on purpose.
	 *
	 * An add-on renders its own panels into this screen on the
	 * trustbio_settings_panels action, and those panels have to look like the
	 * rest of it. The alternative — each add-on writing its own copy of the
	 * row markup — is what happened before, and it drifted immediately: the
	 * panels came out as bare paragraphs with the label and its explanation
	 * running together as one sentence, nothing lined up with the free rows,
	 * and the markup had to be propped up by a stylesheet rule written for
	 * that one wrong shape.
	 *
	 * The free plugin owns the design system, so it owns the row markup too.
	 * The $key passed here is the key the value arrives under in the
	 * trustbio_settings_saved payload, so an add-on should prefix its own.
	 *
	 * @since 1.0.0
	 */

	/**
	 * The label-and-explanation half of a settings row.
	 *
	 * The description is skipped when empty rather than rendered as an empty
	 * element, because not every control has something worth adding — several
	 * of the add-on's say everything they need to in the label.
	 *
	 * @param string $label Row label.
	 * @param string $desc  Explanation, or '' for none.
	 * @return void
	 */
	private static function render_setting_info( string $label, string $desc ): void {
		?>
		<div class="trustbio-sp-setting-info">
			<div class="trustbio-sp-setting-label"><?php echo esc_html( $label ); ?></div>
			<?php if ( '' !== $desc ) : ?>
				<div class="trustbio-sp-setting-desc"><?php echo esc_html( $desc ); ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_toggle( string $key, $value, string $label, string $desc ): void {
		?>
		<div class="trustbio-sp-setting-row">
			<?php self::render_setting_info( $label, $desc ); ?>
			<label class="trustbio-sp-toggle">
				<input type="checkbox" data-tb-setting="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( (bool) $value ); ?>>
				<span class="trustbio-sp-toggle-track"><span class="trustbio-sp-toggle-thumb"></span></span>
			</label>
		</div>
		<?php
	}

	public static function render_select( string $key, $value, string $label, string $desc, array $options ): void {
		?>
		<div class="trustbio-sp-setting-row">
			<?php self::render_setting_info( $label, $desc ); ?>
			<select class="trustbio-sp-select" data-tb-setting="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $options as $opt_val => $opt_label ) : ?>
					<option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $value, $opt_val ); ?>><?php echo esc_html( $opt_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}

	public static function render_number( string $key, $value, string $label, string $desc, int $min, int $max ): void {
		?>
		<div class="trustbio-sp-setting-row">
			<?php self::render_setting_info( $label, $desc ); ?>
			<div class="trustbio-sp-number-wrap">
				<button type="button" class="trustbio-sp-num-btn" data-dir="-1">−</button>
				<input type="number" class="trustbio-sp-number-input" data-tb-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (int) $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>">
				<button type="button" class="trustbio-sp-num-btn" data-dir="1">+</button>
			</div>
		</div>
		<?php
	}

	public static function render_text( string $key, $value, string $label, string $desc, string $type = 'text', string $placeholder = '' ): void {
		// Only the input types this row is laid out for; anything else is text.
		if ( ! in_array( $type, array( 'text', 'email', 'url' ), true ) ) {
			$type = 'text';
		}
		?>
		<div class="trustbio-sp-setting-row">
			<?php self::render_setting_info( $label, $desc ); ?>
			<input type="<?php echo esc_attr( $type ); ?>" class="trustbio-sp-text-input" data-tb-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>"<?php echo '' !== $placeholder ? ' placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>>
		</div>
		<?php
	}

	private static function render_color( string $key, $value, string $label, string $desc ): void {
		?>
		<div class="trustbio-sp-setting-row">
			<?php self::render_setting_info( $label, $desc ); ?>
			<div class="trustbio-sp-color-wrap">
				<input type="color" class="trustbio-sp-color-input" data-tb-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
				<input type="text" class="trustbio-sp-text-input trustbio-sp-color-text" data-tb-color-text="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" maxlength="7">
			</div>
		</div>
		<?php
	}

	private static function render_url_base( string $key, $value, string $label, string $desc ): void {
		?>
		<div class="trustbio-sp-setting-row">
			<div class="trustbio-sp-setting-info">
				<div class="trustbio-sp-setting-label"><?php echo esc_html( $label ); ?></div>
				<div class="trustbio-sp-setting-desc"><?php echo esc_html( $desc ); ?></div>
				<div class="trustbio-sp-url-preview" id="tbsp-url-preview"></div>
			</div>
			<input type="text" id="tbsp-url-base-input" class="trustbio-sp-text-input" data-tb-setting="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
		</div>
		<?php
	}

	/**
	 * A row of checkboxes sharing one key, posted as an array.
	 *
	 * $options may be slug => post type object or slug => plain label; both
	 * turn up here, and which it is used to be told by a boolean argument the
	 * caller had to remember to pass.
	 *
	 * @param string                      $key     Key the array is posted under.
	 * @param array<int,string>           $checked Currently selected slugs.
	 * @param array<string,object|string> $options Available choices.
	 * @param string                      $label   Row label.
	 * @param string                      $desc    Row description.
	 * @return void
	 */
	public static function render_checkbox_group( string $key, array $checked, $options, string $label, string $desc ): void {
		?>
		<div class="trustbio-sp-setting-row is-top">
			<?php self::render_setting_info( $label, $desc ); ?>
			<div class="trustbio-sp-checkbox-group">
				<?php foreach ( (array) $options as $slug => $obj ) :
					$trustbio_lbl = is_object( $obj )
						? ( $obj->labels->singular_name ?? $obj->labels->name ?? $slug )
						: (string) $obj;
					$trustbio_checked = in_array( (string) $slug, array_map( 'strval', $checked ), true );
					?>
					<label class="trustbio-sp-checkbox-item">
						<input type="checkbox" data-tb-group="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $trustbio_checked ); ?>>
						<?php echo esc_html( $trustbio_lbl ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * A standing note inside a settings list — guidance that belongs with the
	 * rows but is not itself a setting.
	 *
	 * @param string $text Note text.
	 * @return void
	 */
	public static function render_note( string $text ): void {
		?>
		<div class="trustbio-sp-setting-row trustbio-sp-setting-note">
			<p><?php echo esc_html( $text ); ?></p>
		</div>
		<?php
	}

	/**
	 * A row whose control is a link to another screen rather than a setting.
	 *
	 * @param string $url    Destination.
	 * @param string $button Link text.
	 * @param string $desc   What the destination is for.
	 * @return void
	 */
	public static function render_link_row( string $url, string $button, string $desc = '' ): void {
		?>
		<div class="trustbio-sp-setting-row">
			<div class="trustbio-sp-setting-info">
				<?php if ( '' !== $desc ) : ?>
					<div class="trustbio-sp-setting-desc"><?php echo esc_html( $desc ); ?></div>
				<?php endif; ?>
			</div>
			<a class="trustbio-sp-row-btn" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $button ); ?></a>
		</div>
		<?php
	}

	public static function render_save_bar( string $type ): void {
		?>
		<div class="trustbio-sp-save-bar">
			<button type="button" class="trustbio-sp-save-btn" data-save-type="<?php echo esc_attr( $type ); ?>">
				<?php esc_html_e( 'Save Changes', 'trustbio' ); ?>
			</button>
			<span class="trustbio-sp-save-status" aria-live="polite"></span>
		</div>
		<?php
	}

	// =========================================================
	// SVG icon library
	// =========================================================

	private static function svg_icon( string $name ): string {
		static $icons = null;
		if ( null === $icons ) {
			$icons = array(
				'logo'        => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
				'overview'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
				'general'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/></svg>',
				'boxes'       => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',
				'review'      => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
				'advanced'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
				'features'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
				'maintenance' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
				'ai-readiness'=> '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>',
				'shortcodes'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="12" y1="2" x2="12" y2="22"/></svg>',
				'stat'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
				'check'       => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
				'off'         => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
				'sidebar'     => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>',
				'qa'          => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
				'gap'         => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
				'ai'          => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M17 8l5-5"/><path d="M17 3h5v5"/></svg>',
				'schema'      => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
				'llms'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/></svg>',
				'validate'    => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
				'email'       => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',

				/*
				 * Names the add-on's settings tabs ask for. They existed only
				 * in trustbio_ap_svg_icon() (the admin shell's map), so six of
				 * the eight add-on tabs rendered with no icon at all beside a
				 * row of core tabs that had one.
				 *
				 * Added here rather than by delegating to that function: its
				 * icons are filled 20x20 glyphs and these are 24x24 strokes,
				 * so borrowing them would have put two icon styles in one
				 * strip. The free plugin owns the design system, so the names
				 * an add-on may use have to be defined in it.
				 */
				'shield'       => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
				'shield-check' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>',
				'authors'      => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
				'fields'       => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
			);
		}

		/*
		 * Fall back to a real icon, never to nothing. An unknown name used to
		 * return '', which leaves a labelled tab sitting flush against its
		 * neighbours' icons -- the ragged row described above. An add-on can
		 * only pass a name it hopes exists, so a miss should degrade to a
		 * neutral glyph, exactly as trustbio_ap_svg_icon() already does.
		 */
		return $icons[ $name ] ?? $icons['general'];
	}
}
