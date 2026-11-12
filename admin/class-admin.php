<?php
/**
 * Admin menu registration, asset enqueuing, and plugin action links.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Admin
 */
class TrustBio_Admin {

	/**
	 * The admin page hook suffixes, keyed by page slug.
	 * Used to scope asset enqueuing to TrustBio pages only.
	 *
	 * @var array<string, string>
	 */
	private array $page_hooks = array();

	/**
	 * Register TrustBio's top-level menu and all sub-menus.
	 */
	public function register_menus(): void {
		// Top-level menu item.
		add_menu_page(
			__( 'TrustBio', 'trustbio' ),
			__( 'TrustBio', 'trustbio' ),
			'manage_options',
			'trustbio',
			array( $this, 'render_dashboard' ),
			$this->get_menu_icon(),
			58
		);

		// Sub-menu: Dashboard (explicit first item).
		$this->page_hooks['dashboard'] = add_submenu_page(
			'trustbio',
			__( 'Overview — TrustBio', 'trustbio' ),
			__( 'Overview', 'trustbio' ),
			'manage_options',
			'trustbio',
			array( $this, 'render_dashboard' )
		);

		// Sub-menu: Features (always visible — lets users enable/disable modules).
		$this->page_hooks['features'] = add_submenu_page(
			'trustbio',
			__( 'Feature Modules — TrustBio', 'trustbio' ),
			__( 'Features', 'trustbio' ),
			'manage_options',
			'trustbio-features',
			array( $this, 'render_features' )
		);

		// Sub-menu: Author Categories.
		$this->page_hooks['categories'] = add_submenu_page(
			'trustbio',
			__( 'Author Categories — TrustBio', 'trustbio' ),
			__( 'Author Categories', 'trustbio' ),
			'manage_options',
			'trustbio-categories',
			array( $this, 'render_categories' )
		);

		// Sub-menu: Authors.
		$this->page_hooks['authors'] = add_submenu_page(
			'trustbio',
			__( 'Authors — TrustBio', 'trustbio' ),
			__( 'Authors', 'trustbio' ),
			'manage_options',
			'trustbio-authors',
			array( $this, 'render_authors' )
		);

		// Sub-menu: Author Fields.
		// Sub-menu: Credentials. Visible, because credentials are the point of
		// the plugin. With no author_id the screen shows an author selector.
		$this->page_hooks['credentials'] = add_submenu_page(
			'trustbio',
			__( 'Credentials — TrustBio', 'trustbio' ),
			__( 'Credentials', 'trustbio' ),
			'manage_options',
			'trustbio-credentials',
			array( $this, 'render_credentials' )
		);

		$this->page_hooks['fields'] = add_submenu_page(
			'trustbio',
			__( 'Author Fields — TrustBio', 'trustbio' ),
			__( 'Author Fields', 'trustbio' ),
			'manage_options',
			'trustbio-fields',
			array( $this, 'render_fields' )
		);

		// Sub-menu: Author Profile (hidden from menu, accessible via Authors list).
		$this->page_hooks['profile'] = add_submenu_page(
			null, // Hidden from sidebar.
			__( 'Author Profile — TrustBio', 'trustbio' ),
			__( 'Author Profile', 'trustbio' ),
			'manage_options',
			'trustbio-profile',
			array( $this, 'render_profile' )
		);

		// Sub-menu: Author Boxes.
		$this->page_hooks['boxes'] = add_submenu_page(
			'trustbio',
			__( 'Author Boxes — TrustBio', 'trustbio' ),
			__( 'Author Boxes', 'trustbio' ),
			'manage_options',
			'trustbio-boxes',
			array( $this, 'render_boxes' )
		);

		// Sub-menu: Review Bars.
		if ( trustbio_feature_enabled( 'review_bars' ) ) {
			$this->page_hooks['review-bars'] = add_submenu_page(
				'trustbio',
				__( 'Last Review Bars — TrustBio', 'trustbio' ),
				__( 'Last Review Bars', 'trustbio' ),
				'manage_options',
				'trustbio-review-bars',
				array( $this, 'render_review_bars' )
			);
		}

		/**
		 * Fires after the free plugin has registered its own submenu pages.
		 *
		 * The TrustBio Pro add-on registers its pages here so they appear
		 * above Settings in the menu.
		 *
		 * @since 1.0.0
		 * @param string          $parent_slug The TrustBio top-level menu slug.
		 * @param TrustBio_Admin  $admin       The admin controller instance.
		 */
		do_action( 'trustbio_register_admin_pages', 'trustbio', $this );

		/*
		 * Sub-menu: TrustBio Pro.
		 *
		 * Registered only when Pro is absent or unlicensed, so a paying user
		 * never sees an advert for something they already have. The screen it
		 * renders is descriptive text about a separate add-on -- it contains
		 * no disabled controls, which Guideline 5 would treat as locked
		 * functionality, and it is confined to this plugin's own menu rather
		 * than placed anywhere else in wp-admin (Guideline 11).
		 */
		if ( ! trustbio_is_pro() ) {
			$this->page_hooks['upgrade'] = add_submenu_page(
				'trustbio',
				__( 'TrustBio Pro — TrustBio', 'trustbio' ),
				__( 'TrustBio Pro', 'trustbio' ),
				'manage_options',
				'trustbio-upgrade',
				array( $this, 'render_upgrade' )
			);
		}

		// Sub-menu: Settings — always last.
		$this->page_hooks['settings'] = add_submenu_page(
			'trustbio',
			__( 'Settings — TrustBio', 'trustbio' ),
			__( 'Settings', 'trustbio' ),
			'manage_options',
			'trustbio-settings',
			array( $this, 'render_settings' )
		);


		// WordPress adds a duplicate submenu item for the top-level page — remove it
		// because we already registered Dashboard explicitly above.
		// Note: This is intentionally a no-op since we registered the submenu with the
		// same slug; WordPress deduplicates it for us. Left as documentation.
	}

	/**
	 * Enqueue admin CSS and JS only on TrustBio pages.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Classic Editor meta box JS — loaded on post edit screens, not just TrustBio pages.
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			global $post;
			if ( $post && trustbio_is_enabled_for_post_type( $post->post_type ) ) {
				wp_enqueue_script(
					'trustbio-classic-metabox',
					TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-classic-metabox.js',
					array( 'jquery', 'jquery-ui-sortable' ),
					defined( 'WP_DEBUG' ) && WP_DEBUG
						? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'admin/js/trustbio-classic-metabox.js' )
						: TRUSTBIO_VERSION,
					true
				);
				wp_localize_script(
					'trustbio-classic-metabox',
					'trustbioMetabox',
					array(
						'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
						'nonce'      => wp_create_nonce( 'trustbio_search_authors' ),
						'categories' => trustbio_get_categories( true ),
					)
				);
			}
		}

		// Check if we're on any TrustBio admin page.
		if ( ! $this->is_trustbio_page( $hook_suffix ) ) {
			return;
		}

		// Determine current TrustBio page from URL since hidden pages have different hook suffixes.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$trustbio_current_page = sanitize_key( $_GET['page'] ?? '' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Use filemtime for cache busting in dev; TRUSTBIO_VERSION in production.
		$trustbio_ver = defined( 'WP_DEBUG' ) && WP_DEBUG
			? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'admin/css/trustbio-admin.css' )
			: TRUSTBIO_VERSION;

		// Main admin stylesheet — the design system.
		wp_enqueue_style(
			'trustbio-admin',
			TRUSTBIO_PLUGIN_URL . 'admin/css/trustbio-admin.css',
			array(),
			$trustbio_ver
		);

		// Shared premium layout CSS — loaded on every TrustBio page.
		$trustbio_sp_ver = defined( 'WP_DEBUG' ) && WP_DEBUG
			? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'admin/css/trustbio-settings.css' )
			: TRUSTBIO_VERSION;
		wp_enqueue_style(
			'trustbio-settings',
			TRUSTBIO_PLUGIN_URL . 'admin/css/trustbio-settings.css',
			array( 'trustbio-admin' ),
			$trustbio_sp_ver
		);

		// Dashboard stylesheet + onboarding wizard (only on the main dashboard page).
		if ( 'trustbio' === $trustbio_current_page ) {
			$trustbio_dash_ver = defined( 'WP_DEBUG' ) && WP_DEBUG
				? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'admin/css/trustbio-dashboard.css' )
				: TRUSTBIO_VERSION;
			wp_enqueue_style(
				'trustbio-dashboard',
				TRUSTBIO_PLUGIN_URL . 'admin/css/trustbio-dashboard.css',
				array( 'trustbio-admin' ),
				$trustbio_dash_ver
			);
			$trustbio_wz_ver = defined( 'WP_DEBUG' ) && WP_DEBUG
				? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'admin/css/trustbio-onboarding.css' )
				: TRUSTBIO_VERSION;
			wp_enqueue_style(
				'trustbio-onboarding',
				TRUSTBIO_PLUGIN_URL . 'admin/css/trustbio-onboarding.css',
				array( 'trustbio-admin' ),
				$trustbio_wz_ver
			);
			wp_enqueue_script(
				'trustbio-onboarding',
				TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-onboarding.js',
				array( 'trustbio-admin' ),
				$trustbio_ver,
				true
			);
			wp_localize_script(
				'trustbio-onboarding',
				'trustbioOnboarding',
				array(
					'profileBaseUrl' => esc_url_raw( admin_url( 'admin.php?page=trustbio-profile&id=' ) ),
					'i18n'           => array(
						'catNameRequired'    => __( 'Please enter a category name.', 'trustbio' ),
						'authorNameRequired' => __( 'Please enter the author\'s name.', 'trustbio' ),
						'pickLayout'         => __( 'Please select a layout.', 'trustbio' ),
						'catCreated'         => __( 'Category created:', 'trustbio' ),
						'authorCreated'      => __( 'Author created:', 'trustbio' ),
						'boxCreated'         => __( 'Author box created:', 'trustbio' ),
						'error'              => __( 'An error occurred. Please try again.', 'trustbio' ),
						'skipStep'           => __( 'Skip this step', 'trustbio' ),
					),
				)
			);
		}

		/**
		 * Fires on a TrustBio admin screen, after the free plugin's assets.
		 *
		 * The TrustBio Pro add-on enqueues the assets for its own screens here.
		 *
		 * @since 1.0.0
		 * @param string $current_page The `page` query arg of the current screen.
		 * @param string $version      Cache-busting version string.
		 */
		do_action( 'trustbio_admin_assets', $trustbio_current_page, $trustbio_ver );

		// Box preview stylesheet.
		wp_enqueue_style(
			'trustbio-box-preview',
			TRUSTBIO_PLUGIN_URL . 'admin/css/trustbio-box-preview.css',
			array( 'trustbio-admin' ),
			$trustbio_ver
		);

		// WordPress media uploader (for avatar uploads).
		wp_enqueue_media();

		// Main admin JS.
		wp_enqueue_script(
			'trustbio-admin',
			TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-admin.js',
			array( 'jquery' ),
			$trustbio_ver,
			true
		);

		// Author search JS (used on post editor via classic meta box too).
		wp_enqueue_script(
			'trustbio-author-search',
			TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-author-search.js',
			array( 'trustbio-admin' ),
			$trustbio_ver,
			true
		);

		// Box live preview JS (only on Author Boxes page).
		if ( 'trustbio-boxes' === $trustbio_current_page ) {
			// Load public CSS so the live preview panel renders the real layout styles.
			$trustbio_pub_ver = defined( 'WP_DEBUG' ) && WP_DEBUG
				? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'public/css/trustbio-public.css' )
				: TRUSTBIO_VERSION;
			wp_enqueue_style(
				'trustbio-public',
				TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-public.css',
				array(),
				$trustbio_pub_ver
			);

			wp_enqueue_script(
				'trustbio-box-preview',
				TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-box-preview.js',
				array( 'trustbio-admin' ),
				$trustbio_ver,
				true
			);

			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			$trustbio_mode = sanitize_key( $_GET['action'] ?? 'list' );
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( 'new' === $trustbio_mode || 'edit' === $trustbio_mode ) {
				wp_enqueue_script(
					'trustbio-box-builder',
					TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-box-builder.js',
					array( 'trustbio-admin', 'trustbio-box-preview' ),
					$trustbio_ver,
					true
				);

				$trustbio_box_class = new TrustBio_Author_Box();
				wp_localize_script(
					'trustbio-box-builder',
					'trustbioBuildPage',
					array(
						'fromTemplate'  => isset( $_GET['from_template'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'editBaseUrl'   => esc_url_raw( admin_url( 'admin.php?page=trustbio-boxes&action=edit&id=' ) ),
						'layoutDefaults' => array(
							'byline'         => $trustbio_box_class->get_default_settings( 'byline' ),
							'quote'          => $trustbio_box_class->get_default_settings( 'quote' ),
							'minimal-inline' => $trustbio_box_class->get_default_settings( 'minimal-inline' ),
							'simple'         => $trustbio_box_class->get_default_settings( 'simple' ),
						),
						'i18n' => array( 'saved' => __( 'Saved', 'trustbio' ) ),
					)
				);
			} else {
				wp_enqueue_script(
					'trustbio-boxes-list',
					TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-boxes-list.js',
					array( 'trustbio-admin' ),
					$trustbio_ver,
					true
				);
				$trustbio_box_class = new TrustBio_Author_Box();
				wp_localize_script(
					'trustbio-boxes-list',
					'trustbioBoxesPage',
					array(
						'templates' => array_values( $this->get_box_templates( $trustbio_box_class ) ),
						'newBoxUrl' => esc_url_raw( admin_url( 'admin.php?page=trustbio-boxes&action=new&from_template=1' ) ),
					)
				);
			}
		}

		// Review Bars page.
		if ( 'trustbio-review-bars' === $trustbio_current_page ) {
			$trustbio_rb_ver = defined( 'WP_DEBUG' ) && WP_DEBUG
				? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'public/css/trustbio-review-bars.css' )
				: TRUSTBIO_VERSION;
			wp_enqueue_style(
				'trustbio-review-bars',
				TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-review-bars.css',
				array(),
				$trustbio_rb_ver
			);
			wp_enqueue_script(
				'trustbio-review-bar',
				TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-review-bar.js',
				array( 'trustbio-admin' ),
				$trustbio_ver,
				true
			);
			$trustbio_rb_class = new TrustBio_Review_Bar();
			wp_localize_script(
				'trustbio-review-bar',
				'trustbioReviewBarAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonces'  => array(
						'preview'    => wp_create_nonce( 'trustbio_preview_review_bar' ),
						'save'       => wp_create_nonce( 'trustbio_save_review_bar' ),
						'delete'     => wp_create_nonce( 'trustbio_delete_review_bar' ),
						'setDefault' => wp_create_nonce( 'trustbio_set_default_review_bar' ),
						'duplicate'  => wp_create_nonce( 'trustbio_duplicate_review_bar' ),
					),
					'layoutDefaults' => array(
						'classic'        => $trustbio_rb_class->get_default_settings( 'classic' ),
						'minimal'        => $trustbio_rb_class->get_default_settings( 'minimal' ),
					),
					'globalLabel' => get_option( 'trustbio_review_default_label', 'Medically reviewed by' ),
					'i18n' => array(
						'saving'        => __( 'Saving…', 'trustbio' ),
						'saved'         => __( 'Saved!', 'trustbio' ),
						'save'          => __( 'Save Bar', 'trustbio' ),
						'error'         => __( 'An error occurred. Please try again.', 'trustbio' ),
						'confirmDelete' => __( 'Delete this review bar? This cannot be undone.', 'trustbio' ),
					),
				)
			);
		}

		// Author Profile page.
		if ( 'trustbio-profile' === $trustbio_current_page ) {
			wp_enqueue_script(
				'trustbio-author-profile',
				TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-author-profile.js',
				array( 'trustbio-admin' ),
				$trustbio_ver,
				true
			);
			wp_localize_script(
				'trustbio-author-profile',
				'trustbioProfilePage',
				array(
					'badgeTypes' => TrustBio_Credentials::get_badge_types(),
					'i18n'       => array(
						'addCredential'    => __( 'Add Credential', 'trustbio' ),
						'updateCredential' => __( 'Update Credential', 'trustbio' ),
					),
				)
			);
		}

		// Credentials Manager page.
		if ( 'trustbio-credentials' === $trustbio_current_page ) {
			wp_enqueue_script(
				'trustbio-credentials',
				TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-credentials.js',
				array( 'trustbio-admin' ),
				$trustbio_ver,
				true
			);
			wp_localize_script(
				'trustbio-credentials',
				'trustbioCredentialsPage',
				array(
					'badgeTypes' => TrustBio_Credentials::get_badge_types(),
					'goUrl'      => esc_url_raw( admin_url( 'admin.php?page=trustbio-credentials&author_id=' ) ),
					'i18n'       => array(
						'addCredential'    => __( 'Add Credential', 'trustbio' ),
						'updateCredential' => __( 'Update Credential', 'trustbio' ),
					),
				)
			);
		}

		// Page-specific JS mapped by URL slug.
		$trustbio_page_scripts = array(
			'trustbio-categories' => 'trustbio-author-category',
			'trustbio-fields'     => 'trustbio-author-fields',
			'trustbio-authors'    => 'trustbio-authors-list',
			'trustbio-settings'   => 'trustbio-settings',
		);
		if ( isset( $trustbio_page_scripts[ $trustbio_current_page ] ) ) {
			$trustbio_handle = $trustbio_page_scripts[ $trustbio_current_page ];
			wp_enqueue_script(
				$trustbio_handle,
				TRUSTBIO_PLUGIN_URL . 'admin/js/' . $trustbio_handle . '.js',
				array( 'trustbio-admin' ),
				$trustbio_ver,
				true
			);
		}

		// Settings page — JS data.
		if ( 'trustbio-settings' === $trustbio_current_page ) {
			wp_localize_script(
				'trustbio-settings',
				'trustbioSettings',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'siteUrl' => trailingslashit( home_url() ),
					'nonces'  => array(
						'saveSettings'  => wp_create_nonce( 'trustbio_save_settings' ),
						'saveFeatures'  => wp_create_nonce( 'trustbio_save_features' ),
						'flushRewrites' => wp_create_nonce( 'trustbio_flush_rewrites' ),
						'clearCache'    => wp_create_nonce( 'trustbio_clear_cache' ),
						'exportData'    => wp_create_nonce( 'trustbio_export_data' ),
					),
					'strings' => array(
						'saving' => __( 'Saving…',                    'trustbio' ),
						'saved'  => __( 'Saved!',                     'trustbio' ),
						'error'  => __( 'Save failed. Please retry.', 'trustbio' ),
					),
				)
			);
		}

		// Features page script + config.
		if ( 'trustbio-features' === $trustbio_current_page ) {
			wp_enqueue_script(
				'trustbio-features',
				TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-features.js',
				array( 'trustbio-admin' ),
				$trustbio_ver,
				true
			);
			wp_localize_script(
				'trustbio-features',
				'trustbioFeaturesPage',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'trustbio_save_features' ),
					'i18n'    => array(
						'saving'   => __( 'Saving…',         'trustbio' ),
						'saved'    => __( 'Saved!',          'trustbio' ),
						'save'     => __( 'Save Changes',    'trustbio' ),
						'error'    => __( 'Save failed.',    'trustbio' ),
						'reload'   => __( 'Reloading page…', 'trustbio' ),
						'active'   => __( 'Active',          'trustbio' ),
						'inactive' => __( 'Inactive',        'trustbio' ),
						'unsaved'  => __( 'Unsaved changes — press Save Changes.', 'trustbio' ),
					),
				)
			);
		}

		// Pass data to JS.
		wp_localize_script(
			'trustbio-admin',
			'trustbioAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'siteUrl'   => trailingslashit( home_url() ),
				'pluginUrl' => TRUSTBIO_PLUGIN_URL,
				'nonces'    => array(
					'saveCategory'   => wp_create_nonce( 'trustbio_save_category' ),
					'deleteCategory' => wp_create_nonce( 'trustbio_delete_category' ),
					'toggleCategory' => wp_create_nonce( 'trustbio_toggle_category' ),
					'saveField'      => wp_create_nonce( 'trustbio_save_field' ),
					'deleteField'    => wp_create_nonce( 'trustbio_delete_field' ),
					'toggleField'    => wp_create_nonce( 'trustbio_toggle_field' ),
					'reorderFields'  => wp_create_nonce( 'trustbio_reorder_fields' ),
					'saveAuthor'     => wp_create_nonce( 'trustbio_save_author' ),
					'deleteAuthor'   => wp_create_nonce( 'trustbio_delete_author' ),
					'toggleAuthor'   => wp_create_nonce( 'trustbio_toggle_author' ),
					'searchAuthors'  => wp_create_nonce( 'trustbio_search_authors' ),
					'searchWpUsers'  => wp_create_nonce( 'trustbio_search_wp_users' ),
					'saveProfile'    => wp_create_nonce( 'trustbio_save_profile' ),
					'uploadAvatar'   => wp_create_nonce( 'trustbio_upload_avatar' ),
					'saveBox'        => wp_create_nonce( 'trustbio_save_box' ),
					'deleteBox'      => wp_create_nonce( 'trustbio_delete_box' ),
					'setDefaultBox'  => wp_create_nonce( 'trustbio_set_default_box' ),
					'previewBox'     => wp_create_nonce( 'trustbio_preview_box' ),
					'duplicateBox'   => wp_create_nonce( 'trustbio_duplicate_box' ),
					'saveSettings'   => wp_create_nonce( 'trustbio_save_settings' ),
					'flushRewrites'  => wp_create_nonce( 'trustbio_flush_rewrites' ),
					'clearCache'     => wp_create_nonce( 'trustbio_clear_cache' ),
					'exportData'         => wp_create_nonce( 'trustbio_export_data' ),
					'saveCredential'     => wp_create_nonce( 'trustbio_save_credential' ),
					'deleteCredential'   => wp_create_nonce( 'trustbio_delete_credential' ),
					'reorderCredentials' => wp_create_nonce( 'trustbio_reorder_credentials' ),
					'saveExpertise'      => wp_create_nonce( 'trustbio_save_expertise' ),
				),
				'i18n'       => array(
					'confirmDelete'  => __( 'Are you sure you want to delete this? This cannot be undone.', 'trustbio' ),
					'saving'         => __( 'Saving...', 'trustbio' ),
					'saved'          => __( 'Saved successfully!', 'trustbio' ),
					'error'          => __( 'An error occurred. Please try again.', 'trustbio' ),
					'noAuthorsFound' => __( 'No authors found.', 'trustbio' ),
				),
				// Pass enabled categories to JS for the post editor author panel.
				'categories' => array_map(
					static function ( $trustbio_cat ) {
						return array(
							'id'       => (int) $trustbio_cat->id,
							'singular' => $trustbio_cat->singular,
							'label'    => $trustbio_cat->display_label ?: $trustbio_cat->singular,
						);
					},
					trustbio_get_categories( true )
				),
			)
		);
	}

	/**
	 * Get the template library data for the boxes list page.
	 * Extracted here so it can be passed via wp_localize_script.
	 *
	 * @param TrustBio_Author_Box $trustbio_box_class Author box class instance.
	 * @return array<int, array>
	 */
	private function get_box_templates( TrustBio_Author_Box $trustbio_box_class ): array {
		$trustbio_templates = array(
			array( 'id' => 'byline',         'name' => __( 'Classic Byline', 'trustbio' ),  'desc' => __( 'Avatar · name · bio · socials. The most widely used author box style.', 'trustbio' ), 'layout' => 'byline',         'settings' => $trustbio_box_class->get_default_settings( 'byline' ) ),
			array( 'id' => 'quote',          'name' => __( 'Quote Card', 'trustbio' ),      'desc' => __( 'Bio displayed as a serif pull-quote with View archive CTA.', 'trustbio' ),          'layout' => 'quote',          'settings' => $trustbio_box_class->get_default_settings( 'quote' ) ),
			array( 'id' => 'minimal-inline', 'name' => __( 'Minimal Inline', 'trustbio' ),  'desc' => __( 'Hairline rules, no background. Minimal editorial style.', 'trustbio' ),             'layout' => 'minimal-inline', 'settings' => $trustbio_box_class->get_default_settings( 'minimal-inline' ) ),
			array( 'id' => 'simple',         'name' => __( 'Card Simple', 'trustbio' ),     'desc' => __( 'Square avatar, vertical divider, short bio, Follow button.', 'trustbio' ),          'layout' => 'simple',         'settings' => $trustbio_box_class->get_default_settings( 'simple' ) ),
		);

		/**
		 * Filters the author box starter template library.
		 *
		 * The TrustBio Pro add-on appends its premium templates here.
		 *
		 * @since 1.0.0
		 * @param array<int,array>    $templates Starter templates.
		 * @param TrustBio_Author_Box $box_class Author box class instance.
		 */
		return (array) apply_filters( 'trustbio_box_starter_templates', $trustbio_templates, $trustbio_box_class );
	}

	/**
	 * Add "Settings" link on the Plugins list page.
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string>
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=trustbio-settings' ) ),
			esc_html__( 'Settings', 'trustbio' )
		);
		array_unshift( $links, $settings_link );

		/*
		 * The plugin-row upgrade link. This is the one upsell the directory
		 * treats as ordinary -- it sits on the plugin's own row, it is a
		 * link rather than a notice, and it appears once. It points at the
		 * in-plugin description screen, not straight out to a sales page, so
		 * a click stays inside wp-admin.
		 */
		if ( ! trustbio_is_pro() ) {
			$links[] = sprintf(
				'<a href="%s" style="color:#4f46e5;font-weight:600;">%s</a>',
				esc_url( admin_url( 'admin.php?page=trustbio-upgrade' ) ),
				esc_html__( 'Go Pro', 'trustbio' )
			);
		}

		return $links;
	}

	// =========================================================
	// Page Render Callbacks
	// Each loads the appropriate admin class and calls render().
	// =========================================================

	/**
	 * Render the TrustBio dashboard page.
	 */
	public function render_dashboard(): void {
		trustbio_layout_start( 'trustbio' );
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/dashboard.php';
		trustbio_layout_end();
	}

	/**
	 * Render the Features management page.
	 */
	public function render_features(): void {
		trustbio_layout_start( 'trustbio-features' );
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/features.php';
		trustbio_layout_end();
	}

	/**
	 * Render the TrustBio Pro description screen.
	 *
	 * @return void
	 */
	public function render_upgrade(): void {
		trustbio_layout_start( 'trustbio-upgrade' );
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/upgrade.php';
		trustbio_layout_end();
	}


	/**
	 * Render Author Categories page.
	 */
	public function render_categories(): void {
		trustbio_layout_start( 'trustbio-categories' );
		( new TrustBio_Author_Category() )->render();
		trustbio_layout_end();
	}

	/**
	 * Render Authors page.
	 */
	public function render_authors(): void {
		trustbio_layout_start( 'trustbio-authors' );
		( new TrustBio_Authors_List() )->render();
		trustbio_layout_end();
	}

	/**
	 * Render the Credential Manager page.
	 */
	public function render_credentials(): void {
		trustbio_layout_start( 'trustbio-credentials' );
		( new TrustBio_Credentials() )->render();
		trustbio_layout_end();
	}

	/**
	 * Render Author Fields page.
	 */
	public function render_fields(): void {
		trustbio_layout_start( 'trustbio-fields' );
		( new TrustBio_Author_Fields() )->render();
		trustbio_layout_end();
	}

	/**
	 * Render Author Profile edit page.
	 */
	public function render_profile(): void {
		trustbio_layout_start( 'trustbio-profile' );
		( new TrustBio_Author_Profile() )->render();
		trustbio_layout_end();
	}

	/**
	 * Render Author Boxes page.
	 */
	public function render_boxes(): void {
		trustbio_layout_start( 'trustbio-boxes' );
		( new TrustBio_Author_Box() )->render();
		trustbio_layout_end();
	}


	/**
	 * Render Review Bars page.
	 */
	public function render_review_bars(): void {
		trustbio_layout_start( 'trustbio-review-bars' );
		( new TrustBio_Review_Bar() )->render();
		trustbio_layout_end();
	}

	/**
	 * Render Settings page — TrustBio_Settings::render() calls layout_start/end internally.
	 */
	public function render_settings(): void {
		( new TrustBio_Settings() )->render();
	}








	// =========================================================
	// Private Helpers
	// =========================================================

	/**
	 * Add the shared body class to every TrustBio admin page.
	 *
	 * @param string $classes Space-separated admin body classes.
	 * @return string
	 */
	public function add_body_class( string $classes ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( str_starts_with( $page, 'trustbio' ) ) {
			$classes .= ' trustbio-admin-page';
		}
		return $classes;
	}

	/**
	 * Check if the current page is a TrustBio admin page.
	 *
	 * @param string $hook_suffix   Current page hook.
	 * @param string $specific_page Optional: check for a specific page key.
	 * @return bool
	 */
	private function is_trustbio_page( string $hook_suffix, string $specific_page = '' ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$trustbio_url_page = sanitize_key( $_GET['page'] ?? '' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $specific_page ) {
			// Check hook suffix match.
			if ( isset( $this->page_hooks[ $specific_page ] ) && $hook_suffix === $this->page_hooks[ $specific_page ] ) {
				return true;
			}
			// Also check URL page slug directly (handles hidden pages with null parent).
			return $trustbio_url_page === 'trustbio-' . $specific_page
				|| $trustbio_url_page === 'trustbio-' . str_replace( '_', '-', $specific_page );
		}

		// General TrustBio page check.
		return in_array( $hook_suffix, array_values( $this->page_hooks ), true )
			|| str_starts_with( $trustbio_url_page, 'trustbio' );
	}

	/**
	 * Return the registered page hook suffixes keyed by page identifier.
	 *
	 * @return array<string,string>
	 */
	public function get_page_hooks(): array {
		return $this->page_hooks;
	}

	/**
	 * Return the SVG icon for the TrustBio menu item as a base64 data URI.
	 * Using a simple shield/badge icon that represents trust/authority.
	 *
	 * @return string base64 encoded SVG data URI.
	 */
	private function get_menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
			<path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
		</svg>';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}

// NOTE: Quick Edit and Bulk Edit hooks are registered in trustbio_register_quick_edit()
// called from the admin hooks in class-trustbio.php
