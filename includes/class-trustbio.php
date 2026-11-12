<?php
/**
 * Core plugin class — loads all components via hooks.
 *
 * @package TrustBio
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio
 *
 * Singleton loader. Requires all class files and registers
 * admin + public hooks through their respective handler classes.
 */
class TrustBio {

	/**
	 * Singleton instance.
	 *
	 * @var TrustBio|null
	 */
	private static ?TrustBio $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return TrustBio
	 */
	public static function get_instance(): TrustBio {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — private to enforce singleton.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Load all required class files.
	 */
	private function load_dependencies(): void {
		// Core includes.
		require_once TRUSTBIO_PLUGIN_DIR . 'includes/class-i18n.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'includes/class-pro-bridge.php';

		// Admin classes.
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/partials/layout.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-admin.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-author-category.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-authors-list.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-author-fields.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-author-profile.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-author-box.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-settings.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-post-sidebar.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-quick-edit.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-credentials.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-review-date.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-review-dashboard-widget.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'includes/class-contribution-counter.php';

		// E-E-A-T modules.
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-author-expertise.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-help-tabs.php';

		// Public classes.
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/class-review-bar.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'public/class-review-renderer.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'public/class-public.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'public/class-schema.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'public/class-shortcode.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'public/class-author-archive.php';
		require_once TRUSTBIO_PLUGIN_DIR . 'public/class-author-block.php';
	}

	/**
	 * Instantiate the i18n class.
	 * Translations are auto-loaded by WordPress 4.6+ for plugins on WordPress.org.
	 * No manual hook registration needed.
	 */
	private function set_locale(): void {
		new TrustBio_i18n(); // WP 4.6+ auto-loads translations — no hook needed.
	}

	/**
	 * Register all admin-side hooks.
	 */
	private function define_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		$admin = new TrustBio_Admin();
		add_action( 'admin_menu', array( $admin, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $admin, 'add_body_class' ) );
		add_filter( 'plugin_action_links_' . TRUSTBIO_PLUGIN_BASENAME, array( $admin, 'add_action_links' ) );

		// Help tabs — registered after menus so page hooks are available.
		$help_tabs = new TrustBio_Help_Tabs();
		add_action( 'admin_menu', function () use ( $admin, $help_tabs ) {
			$help_tabs->register( $admin->get_page_hooks() );
		}, 20 );

		// Author Category AJAX handlers.
		$categories = new TrustBio_Author_Category();
		add_action( 'wp_ajax_trustbio_save_category', array( $categories, 'ajax_save' ) );
		add_action( 'wp_ajax_trustbio_delete_category', array( $categories, 'ajax_delete' ) );
		add_action( 'wp_ajax_trustbio_toggle_category', array( $categories, 'ajax_toggle' ) );

		// Author Fields AJAX handlers.
		$fields = new TrustBio_Author_Fields();
		add_action( 'wp_ajax_trustbio_save_field', array( $fields, 'ajax_save' ) );
		add_action( 'wp_ajax_trustbio_delete_field', array( $fields, 'ajax_delete' ) );
		add_action( 'wp_ajax_trustbio_toggle_field', array( $fields, 'ajax_toggle' ) );
		add_action( 'wp_ajax_trustbio_reorder_fields', array( $fields, 'ajax_reorder' ) );

		// Authors AJAX handlers.
		$authors = new TrustBio_Authors_List();
		add_action( 'wp_ajax_trustbio_save_author', array( $authors, 'ajax_save' ) );
		add_action( 'wp_ajax_trustbio_delete_author', array( $authors, 'ajax_delete' ) );
		add_action( 'wp_ajax_trustbio_toggle_author', array( $authors, 'ajax_toggle' ) );
		add_action( 'wp_ajax_trustbio_search_authors',    array( $authors, 'ajax_search' ) );
		add_action( 'wp_ajax_trustbio_search_wp_users',   array( $authors, 'ajax_search_wp_users' ) );
		add_action( 'wp_ajax_trustbio_get_author_detail', array( $authors, 'ajax_get_author_detail' ) );

		// Author Profile AJAX handlers.
		$profile = new TrustBio_Author_Profile();
		add_action( 'wp_ajax_trustbio_save_profile', array( $profile, 'ajax_save' ) );
		add_action( 'wp_ajax_trustbio_upload_avatar', array( $profile, 'ajax_upload_avatar' ) );

		// Author Box AJAX handlers.
		$boxes = new TrustBio_Author_Box();
		add_action( 'wp_ajax_trustbio_save_box', array( $boxes, 'ajax_save' ) );
		add_action( 'wp_ajax_trustbio_delete_box', array( $boxes, 'ajax_delete' ) );
		add_action( 'wp_ajax_trustbio_set_default_box', array( $boxes, 'ajax_set_default' ) );
		add_action( 'wp_ajax_trustbio_preview_box', array( $boxes, 'ajax_preview' ) );
		add_action( 'wp_ajax_trustbio_duplicate_box', array( $boxes, 'ajax_duplicate' ) );
		add_action( 'wp_ajax_trustbio_wizard_create_box', array( $boxes, 'ajax_wizard_create_box' ) );

		// Review Bar AJAX handlers.
		if ( trustbio_feature_enabled( 'review_bars' ) ) {
			$trustbio_review_bar = new TrustBio_Review_Bar();
			add_action( 'wp_ajax_trustbio_save_review_bar',        array( $trustbio_review_bar, 'ajax_save' ) );
			add_action( 'wp_ajax_trustbio_delete_review_bar',      array( $trustbio_review_bar, 'ajax_delete' ) );
			add_action( 'wp_ajax_trustbio_set_default_review_bar', array( $trustbio_review_bar, 'ajax_set_default' ) );
			add_action( 'wp_ajax_trustbio_preview_review_bar',     array( $trustbio_review_bar, 'ajax_preview' ) );
			add_action( 'wp_ajax_trustbio_duplicate_review_bar',   array( $trustbio_review_bar, 'ajax_duplicate' ) );
		}

		// Settings AJAX handlers.
		$settings = new TrustBio_Settings();
		add_action( 'wp_ajax_trustbio_save_settings',  array( $settings, 'ajax_save' ) );
		add_action( 'wp_ajax_trustbio_save_features',  array( $settings, 'ajax_save_features' ) );
		add_action( 'wp_ajax_trustbio_flush_rewrites', array( $settings, 'ajax_flush_rewrites' ) );
		add_action( 'wp_ajax_trustbio_clear_cache',    array( $settings, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_trustbio_export_data',    array( $settings, 'ajax_export_data' ) );
		add_action( 'wp_ajax_trustbio_import_data',    array( $settings, 'ajax_import_data' ) );

		// Credential Manager AJAX handlers.
		$credentials = new TrustBio_Credentials();
		add_action( 'wp_ajax_trustbio_save_credential',    array( $credentials, 'ajax_save' ) );
		add_action( 'wp_ajax_trustbio_delete_credential',  array( $credentials, 'ajax_delete' ) );
		add_action( 'wp_ajax_trustbio_reorder_credentials', array( $credentials, 'ajax_reorder' ) );

		// Expert Review Date (Phase 12).
		if ( trustbio_feature_enabled( 'review_bars' ) ) {
			$review_date = new TrustBio_Review_Date();
			$review_date->register_admin_hooks();

			$trustbio_widget = new TrustBio_Review_Dashboard_Widget();
			add_action( 'wp_dashboard_setup',                   array( $trustbio_widget, 'register' ) );
			add_action( 'admin_enqueue_scripts',                array( $trustbio_widget, 'enqueue_assets' ) );
			add_action( 'wp_ajax_trustbio_mark_reviewed_today', array( $trustbio_widget, 'ajax_mark_reviewed_today' ) );
		}

		// Contribution Counter cache busting (Phase 14).
		add_action( 'save_post',            array( 'TrustBio_Contribution_Counter', 'bust_on_post_change' ), 10, 2 );
		add_action( 'delete_post',          array( 'TrustBio_Contribution_Counter', 'bust_on_post_change' ), 10, 2 );
		add_action( 'transition_post_status', array( 'TrustBio_Contribution_Counter', 'bust_on_status_transition' ), 10, 3 );

		// Quick Edit + Bulk Edit.
		$quick_edit = new TrustBio_Quick_Edit();
		$quick_edit->register_hooks();
		$sidebar = new TrustBio_Post_Sidebar();
		add_action( 'add_meta_boxes', array( $sidebar, 'register_classic_metabox' ) );
		add_action( 'save_post', array( $sidebar, 'save_post_meta' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $sidebar, 'enqueue_block_assets' ) );
		add_action( 'wp_ajax_trustbio_save_post_authors', array( $sidebar, 'ajax_save_post_authors' ) );
		add_action( 'rest_api_init', array( $sidebar, 'register_rest_fields' ) );

		$trustbio_expertise = new TrustBio_Author_Expertise();
		add_action( 'wp_ajax_trustbio_save_expertise', array( $trustbio_expertise, 'ajax_save_expertise' ) );

		// Say so if a table could not be created, rather than letting every
		// screen fail against a table that is not there.
		add_action( 'admin_notices', array( __CLASS__, 'render_db_error_notice' ) );

		/**
		 * Fires after the free plugin has registered all of its admin hooks.
		 *
		 * The TrustBio Pro add-on registers its own admin hooks here.
		 *
		 * @since 1.0.0
		 */
		do_action( 'trustbio_register_admin_hooks' );
	}

	/**
	 * Warn an administrator when one of the plugin's tables is missing.
	 *
	 * Shown only to users who could do something about it, and only when
	 * something is actually wrong — never as a routine prompt.
	 *
	 * @return void
	 */
	public static function render_db_error_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$trustbio_missing = get_option( 'trustbio_db_error', array() );
		if ( empty( $trustbio_missing ) || ! is_array( $trustbio_missing ) ) {
			return;
		}

		/*
		 * Re-check before complaining: the tables may have been repaired
		 * since, in which case clear the flag and stay quiet.
		 *
		 * The activator is only loaded during activation, so it has to be
		 * pulled in here. That happens only on this path — when the error flag
		 * is already set — so a healthy site never loads the file.
		 */
		if ( ! class_exists( 'TrustBio_Activator' ) ) {
			require_once TRUSTBIO_PLUGIN_DIR . 'includes/class-activator.php';
		}

		if ( class_exists( 'TrustBio_Activator' ) ) {
			$trustbio_still = TrustBio_Activator::missing_tables();
			if ( empty( $trustbio_still ) ) {
				delete_option( 'trustbio_db_error' );
				update_option( 'trustbio_db_version', TRUSTBIO_VERSION );
				return;
			}
			$trustbio_missing = $trustbio_still;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p><p><code>%s</code></p></div>',
			esc_html__( 'TrustBio could not create its database tables.', 'trustbio' ),
			esc_html__( 'Deactivate and reactivate the plugin. If the problem continues, your database user may not have permission to create tables — ask your host.', 'trustbio' ),
			esc_html( implode( ', ', array_map( 'strval', $trustbio_missing ) ) )
		);
	}

	/**
	 * Register all public-facing hooks.
	 */
	private function define_public_hooks(): void {
		$public = new TrustBio_Public();
		add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_assets' ) );
		// Priority 10001: runs after Elementor's own the_content renderer (priority 9999),
		// so the author box appends to the fully-rendered HTML on Elementor pages.
		add_filter( 'the_content', array( $public, 'inject_author_box' ), 10001 );
		// Author Archive (Phase 15).
		$trustbio_archive = new TrustBio_Author_Archive();
		add_filter( 'template_include',   array( $trustbio_archive, 'intercept_template' ) );
		add_action( 'wp_enqueue_scripts', array( $trustbio_archive, 'enqueue_assets' ) );
		add_action( 'wp_head',            array( $trustbio_archive, 'output_schema' ), 6 );
		add_action( 'wp',                 array( $trustbio_archive, 'output_seo_meta' ) );
		add_filter( 'document_title_parts', array( $trustbio_archive, 'filter_title' ) );
		add_filter( 'query_vars', array( $public, 'register_query_vars' ) );

		// Schema output in <head>.
		if ( trustbio_feature_enabled( 'schema' ) ) {
			$schema = new TrustBio_Schema();
			add_action( 'wp_head', array( $schema, 'output_schema' ), 5 );
		}

		// Expert Review Date public output.
		if ( trustbio_feature_enabled( 'review_bars' ) ) {
			$review_date_pub = new TrustBio_Review_Date();
			$review_date_pub->register_public_hooks();
		}

		// Shortcodes.
		$shortcode = new TrustBio_Shortcode();
		add_shortcode( 'trustbio', array( $shortcode, 'render' ) );

		// Author Box Gutenberg Block (Phase 18).
		$trustbio_block = new TrustBio_Author_Block();
		add_action( 'init', array( $trustbio_block, 'register' ) );

		// Register custom rewrite rules.
		add_action( 'init', array( $public, 'register_rewrite_rules' ) );

		/**
		 * Fires after the free plugin has registered all of its public hooks.
		 *
		 * The TrustBio Pro add-on registers its own public hooks here.
		 *
		 * @since 1.0.0
		 */
		do_action( 'trustbio_register_public_hooks' );
	}

	/**
	 * Register hooks that must fire on all request types, including WP-Cron.
	 *
	 * WP-Cron does not run as an admin request (WP_ADMIN is not defined), so
	 * hooks that power cron jobs cannot live inside define_admin_hooks().
	 * This method is called unconditionally from the constructor.
	 */
	private function define_cron_hooks(): void {
		/**
		 * Fires on every request type, including WP-Cron.
		 *
		 * WP-Cron does not run as an admin request, so add-on cron callbacks
		 * must be registered here rather than in define_admin_hooks().
		 *
		 * @since 1.0.0
		 */
		do_action( 'trustbio_register_cron_hooks' );
	}

	/**
	 * Prevent cloning the singleton.
	 */
	private function __clone() {}
}
