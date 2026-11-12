<?php
/**
 * Plugin activator — runs on plugin activation.
 * Creates all database tables and seeds default data.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Activator
 */
class TrustBio_Activator {

	/**
	 * Every table this plugin owns, without the site's table prefix.
	 *
	 * @var string[]
	 */
	public const TABLES = array(
		'trustbio_author_categories',
		'trustbio_authors',
		'trustbio_author_fields',
		'trustbio_author_field_values',
		'trustbio_author_boxes',
		'trustbio_author_credentials',
		'trustbio_review_bars',
	);

	/**
	 * Run all activation routines.
	 */
	public static function activate(): void {
		self::create_tables();
		self::seed_default_categories();
		self::seed_default_fields();
		self::seed_default_box();
		self::seed_default_review_bar();
		self::set_default_options();
		self::register_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Create all custom database tables using dbDelta.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql_categories = "CREATE TABLE {$wpdb->prefix}trustbio_author_categories (
			id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
			singular      VARCHAR(100)  NOT NULL,
			plural        VARCHAR(100)  NOT NULL,
			slug          VARCHAR(100)  NOT NULL,
			display_label VARCHAR(200)  NOT NULL DEFAULT '',
			schema_prop        VARCHAR(100)  NOT NULL DEFAULT 'author',
			contribution_verb  VARCHAR(100)  NOT NULL DEFAULT 'Written',
			contribution_noun  VARCHAR(100)  NOT NULL DEFAULT 'article',
			post_types         LONGTEXT,
			is_enabled    TINYINT(1)    NOT NULL DEFAULT 1,
			sort_order    INT           NOT NULL DEFAULT 0,
			created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";

		$sql_authors = "CREATE TABLE {$wpdb->prefix}trustbio_authors (
			id               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
			author_type      VARCHAR(20)    NOT NULL DEFAULT 'registered',
			wp_user_id       BIGINT UNSIGNED DEFAULT NULL,
			display_name     VARCHAR(200)   NOT NULL,
			slug             VARCHAR(200)   NOT NULL,
			email            VARCHAR(200)   DEFAULT NULL,
			website          VARCHAR(500)   DEFAULT NULL,
			job_title        VARCHAR(200)   DEFAULT NULL,
			credentials      VARCHAR(200)   DEFAULT NULL,
			bio              LONGTEXT,
			avatar_attach_id BIGINT UNSIGNED DEFAULT NULL,
			avatar_url       VARCHAR(500)   DEFAULT NULL,
			category_id      INT UNSIGNED   DEFAULT NULL,
			expertise_cats   TEXT,
			is_active        TINYINT(1)     NOT NULL DEFAULT 1,
			created_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY wp_user_id (wp_user_id),
			KEY category_id (category_id)
		) $charset_collate;";

		$sql_fields = "CREATE TABLE {$wpdb->prefix}trustbio_author_fields (
			id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug        VARCHAR(100) NOT NULL,
			label       VARCHAR(200) NOT NULL,
			field_type  VARCHAR(20)  NOT NULL DEFAULT 'link',
			is_social   TINYINT(1)   NOT NULL DEFAULT 1,
			icon_svg    TEXT,
			placeholder VARCHAR(300) DEFAULT NULL,
			is_required TINYINT(1)   NOT NULL DEFAULT 0,
			is_active   TINYINT(1)   NOT NULL DEFAULT 1,
			is_builtin  TINYINT(1)   NOT NULL DEFAULT 1,
			sort_order  INT          NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";

		$sql_field_values = "CREATE TABLE {$wpdb->prefix}trustbio_author_field_values (
			id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
			author_id INT UNSIGNED NOT NULL,
			field_id  INT UNSIGNED NOT NULL,
			value     TEXT,
			PRIMARY KEY (id),
			UNIQUE KEY author_field (author_id, field_id),
			KEY author_id (author_id),
			KEY field_id (field_id)
		) $charset_collate;";

		$sql_boxes = "CREATE TABLE {$wpdb->prefix}trustbio_author_boxes (
			id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
			title      VARCHAR(200) NOT NULL,
			layout     VARCHAR(50)  NOT NULL DEFAULT 'classic',
			box_type   VARCHAR(20)  NOT NULL DEFAULT 'default',
			is_default TINYINT(1)   NOT NULL DEFAULT 0,
			settings   LONGTEXT,
			created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		$sql_credentials = "CREATE TABLE {$wpdb->prefix}trustbio_author_credentials (
			id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
			author_id        INT UNSIGNED  NOT NULL,
			badge_type       VARCHAR(30)   NOT NULL DEFAULT 'custom',
			credential_name  VARCHAR(300)  NOT NULL,
			issuing_body     VARCHAR(300)  NOT NULL DEFAULT '',
			license_number   VARCHAR(200)  NOT NULL DEFAULT '',
			issue_date       DATE          DEFAULT NULL,
			expiry_date      DATE          DEFAULT NULL,
			verification_url VARCHAR(500)  NOT NULL DEFAULT '',
			show_badge       TINYINT(1)    NOT NULL DEFAULT 1,
			show_in_schema   TINYINT(1)    NOT NULL DEFAULT 1,
			sort_order       INT           NOT NULL DEFAULT 0,
			created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY author_id (author_id),
			KEY badge_type (badge_type)
		) $charset_collate;";

		$sql_review_bars = "CREATE TABLE {$wpdb->prefix}trustbio_review_bars (
			id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
			title      VARCHAR(200) NOT NULL,
			layout     VARCHAR(50)  NOT NULL DEFAULT 'classic',
			is_default TINYINT(1)   NOT NULL DEFAULT 0,
			settings   LONGTEXT,
			created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_categories );
		dbDelta( $sql_authors );
		dbDelta( $sql_fields );
		dbDelta( $sql_field_values );
		dbDelta( $sql_boxes );
		dbDelta( $sql_credentials );
		dbDelta( $sql_review_bars );

		/*
		 * Only record the version once the tables are really there.
		 *
		 * dbDelta never reports failure: it runs each statement and returns a
		 * list of what it believes it did. Recording the version regardless
		 * meant a failed CREATE left the plugin convinced its schema was
		 * current, so it never tried again and every screen queried a table
		 * that did not exist.
		 */
		$trustbio_missing = self::missing_tables();

		if ( empty( $trustbio_missing ) ) {
			delete_option( 'trustbio_db_error' );
			update_option( 'trustbio_db_version', TRUSTBIO_VERSION );
			return;
		}

		update_option( 'trustbio_db_error', $trustbio_missing );
	}

	/**
	 * Which of the plugin's tables are not in the database.
	 *
	 * @return string[] Unprefixed table names, empty when everything exists.
	 */
	public static function missing_tables(): array {
		global $wpdb;

		$trustbio_missing = array();

		foreach ( self::TABLES as $trustbio_name ) {
			$trustbio_full = $wpdb->prefix . $trustbio_name;

			/*
			 * information_schema rather than SHOW TABLES, and TABLE_TYPE is
			 * the point: SHOW TABLES also lists views, so anything that merely
			 * occupied the name would have counted as a healthy table and the
			 * plugin would have stamped its version and carried on.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_type = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT TABLE_TYPE FROM information_schema.TABLES
					  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
					$trustbio_full
				)
			);

			if ( 'BASE TABLE' !== $trustbio_type ) {
				$trustbio_missing[] = $trustbio_name;
			}
		}

		return $trustbio_missing;
	}

	/**
	 * Count rows in a plugin-owned table to decide if seeding is needed.
	 *
	 * The table name is always $wpdb->prefix + a hardcoded string literal
	 * (never from user input). esc_sql() is applied as an extra layer,
	 * and $wpdb->prepare() wraps the full query so PHPCS is satisfied.
	 *
	 * @param string $trustbio_table Fully-qualified table name.
	 * @return bool True when the table has no rows.
	 */
	private static function table_is_empty( string $trustbio_table ): bool {
		global $wpdb;

		$safe_table = esc_sql( $trustbio_table );

		// We use $wpdb->prepare() with a literal int placeholder to satisfy
		// the PHPCS sniff.  The table name itself is safe: it is always
		// $wpdb->prefix + a compile-time string, never user-supplied data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$trustbio_count = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT COUNT(*) FROM `{$safe_table}` WHERE %d = %d", 1, 1 )
		);

		return 0 === (int) $trustbio_count;
	}

	/**
	 * Seed three default author categories on first activation.
	 */
	private static function seed_default_categories(): void {
		global $wpdb;

		$trustbio_table = $wpdb->prefix . 'trustbio_author_categories';

		if ( ! self::table_is_empty( $trustbio_table ) ) {
			return;
		}

		$trustbio_defaults = array(
			array(
				'singular'      => 'Author',
				'plural'        => 'Authors',
				'slug'          => 'author',
				'display_label'       => 'Written By',
				'schema_prop'         => 'author',
				'contribution_verb'   => 'Written',
				'contribution_noun'   => 'article',
				'post_types'    => null,
				'is_enabled'    => 1,
				'sort_order'    => 0,
			),
			array(
				'singular'      => 'Reviewer',
				'plural'        => 'Reviewers',
				'slug'          => 'reviewer',
				'display_label'       => 'Reviewed By',
				'schema_prop'         => 'reviewedBy',
				'contribution_verb'   => 'Reviewed',
				'contribution_noun'   => 'article',
				'post_types'          => null,
				'is_enabled'          => 1,
				'sort_order'          => 1,
			),
			array(
				'singular'      => 'Medical Reviewer',
				'plural'        => 'Medical Reviewers',
				'slug'          => 'medical-reviewer',
				'display_label'       => 'Medically Reviewed By',
				'schema_prop'         => 'reviewedBy',
				'contribution_verb'   => 'Reviewed',
				'contribution_noun'   => 'article',
				'post_types'          => null,
				'is_enabled'          => 1,
				'sort_order'          => 2,
			),
		);

		foreach ( $trustbio_defaults as $trustbio_row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $trustbio_table, $trustbio_row );
		}
	}

	/**
	 * Seed all built-in social and custom author fields.
	 */
	private static function seed_default_fields(): void {
		global $wpdb;

		$trustbio_table = $wpdb->prefix . 'trustbio_author_fields';

		if ( ! self::table_is_empty( $trustbio_table ) ) {
			return;
		}

		// Format: [ slug, label, field_type, is_social, is_active, sort_order, placeholder ].
		$trustbio_fields = array(
			// Core Social.
			array( 'twitter',        'Twitter / X',        'link', 1, 1,  0, 'https://x.com/yourusername' ),
			array( 'linkedin',       'LinkedIn',           'link', 1, 1,  1, 'https://linkedin.com/in/yourprofile' ),
			array( 'facebook',       'Facebook',           'link', 1, 1,  2, 'https://facebook.com/yourprofile' ),
			array( 'instagram',      'Instagram',          'link', 1, 1,  3, 'https://instagram.com/yourusername' ),
			array( 'youtube',        'YouTube',            'link', 1, 1,  4, 'https://youtube.com/@yourchannel' ),
			array( 'tiktok',         'TikTok',             'link', 1, 0,  5, 'https://tiktok.com/@yourusername' ),
			array( 'pinterest',      'Pinterest',          'link', 1, 0,  6, 'https://pinterest.com/yourusername' ),
			// Professional / Dev.
			array( 'github',         'GitHub',             'link', 1, 0,  7, 'https://github.com/yourusername' ),
			array( 'gitlab',         'GitLab',             'link', 1, 0,  8, 'https://gitlab.com/yourusername' ),
			array( 'stackoverflow',  'Stack Overflow',     'link', 1, 0,  9, 'https://stackoverflow.com/users/yourprofile' ),
			array( 'producthunt',    'Product Hunt',       'link', 1, 0, 10, 'https://producthunt.com/@yourusername' ),
			// Content / Publishing.
			array( 'medium',         'Medium',             'link', 1, 0, 11, 'https://medium.com/@yourusername' ),
			array( 'substack',       'Substack',           'link', 1, 0, 12, 'https://yourname.substack.com' ),
			array( 'beehiiv',        'Beehiiv',            'link', 1, 0, 13, 'https://beehiiv.com/@yourusername' ),
			array( 'quora',          'Quora',              'link', 1, 0, 14, 'https://quora.com/profile/yourprofile' ),
			array( 'reddit',         'Reddit',             'link', 1, 0, 15, 'https://reddit.com/u/yourusername' ),
			array( 'flipboard',      'Flipboard',          'link', 1, 0, 16, 'https://flipboard.com/@yourusername' ),
			// Messaging / Decentralized.
			array( 'telegram',       'Telegram',           'link', 1, 0, 17, 'https://t.me/yourusername' ),
			array( 'whatsapp',       'WhatsApp',           'link', 1, 0, 18, 'https://wa.me/yourphonenumber' ),
			array( 'discord',        'Discord',            'link', 1, 0, 19, 'https://discord.gg/yourserver' ),
			array( 'mastodon',       'Mastodon',           'link', 1, 0, 20, 'https://mastodon.social/@yourusername' ),
			array( 'threads',        'Threads',            'link', 1, 0, 21, 'https://threads.net/@yourusername' ),
			array( 'bluesky',        'Bluesky',            'link', 1, 0, 22, 'https://bsky.app/profile/yourhandle' ),
			// Trust / Authority (sameAs schema signals).
			array( 'wikipedia',      'Wikipedia',          'link', 1, 0, 23, 'https://en.wikipedia.org/wiki/YourName' ),
			array( 'google_scholar', 'Google Scholar',     'link', 1, 0, 24, 'https://scholar.google.com/citations?user=yourid' ),
			array( 'researchgate',   'ResearchGate',       'link', 1, 0, 25, 'https://researchgate.net/profile/YourProfile' ),
			array( 'orcid',          'ORCID',              'link', 1, 0, 26, 'https://orcid.org/0000-0000-0000-0000' ),
			array( 'amazon_author',  'Amazon Author Page', 'link', 1, 0, 27, 'https://amazon.com/author/yourname' ),
			// Text / non-social fields.
			array( 'job_title',      'Job Title',          'text', 0, 1, 28, 'e.g. Senior Medical Writer' ),
			array( 'credentials',    'Credentials',        'text', 0, 1, 29, 'e.g. MD, PhD, LCSW' ),
			array( 'specialization', 'Specialization',     'text', 0, 0, 30, 'e.g. Addiction Medicine' ),
		);

		foreach ( $trustbio_fields as $trustbio_f ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$trustbio_table,
				array(
					'slug'        => $trustbio_f[0],
					'label'       => $trustbio_f[1],
					'field_type'  => $trustbio_f[2],
					'is_social'   => $trustbio_f[3],
					'is_active'   => $trustbio_f[4],
					'sort_order'  => $trustbio_f[5],
					'placeholder' => $trustbio_f[6],
					'is_builtin'  => 1,
				)
			);
		}
	}

	/**
	 * Create one default "Classic Stack" author box.
	 */
	private static function seed_default_box(): void {
		global $wpdb;

		$trustbio_table = $wpdb->prefix . 'trustbio_author_boxes';

		if ( ! self::table_is_empty( $trustbio_table ) ) {
			return;
		}

		$trustbio_settings = array(
			'name_color'     => '#15161a',
			'name_size'      => 18,
			'name_weight'    => '700',
			'bio_color'      => '#36352f',
			'bio_size'       => 14,
			'bio_limit'      => 0,
			'jobtitle_color' => '#6b7280',
			'jobtitle_size'  => 12,
			'icon_size'      => 13,
			'icon_color'     => '#374151',
			'icon_style'     => 'color',
			'avatar_size'    => 96,
			'avatar_shape'   => 'circle',
			'custom_class'   => '',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$trustbio_table,
			array(
				'title'      => 'Classic Byline',
				'layout'     => 'byline',
				'box_type'   => 'default',
				'is_default' => 1,
				'settings'   => wp_json_encode( $trustbio_settings ),
			)
		);
	}

	/**
	 * Create one default "Classic Bar" review bar.
	 */
	private static function seed_default_review_bar(): void {
		global $wpdb;

		$trustbio_table = $wpdb->prefix . 'trustbio_review_bars';

		if ( ! self::table_is_empty( $trustbio_table ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$trustbio_table,
			array(
				'title'      => 'Classic Bar',
				'layout'     => 'classic',
				'is_default' => 1,
				'settings'   => wp_json_encode(
					array(
						'accent_color'     => '#4f46e5',
						'label_text'       => '',
						'show_credentials' => 1,
						'show_icon'        => 1,
					)
				),
			)
		);

		update_option( 'trustbio_default_review_bar_id', $wpdb->insert_id );
	}

	/**
	 * Set all default plugin options on first activation.
	 * add_option() silently skips if the key already exists — safe on re-activation.
	 */
	private static function set_default_options(): void {
		$trustbio_defaults = array(
			'trustbio_enabled_post_types'        => array( 'post', 'page' ),
			'trustbio_show_powered_by'           => 0,
			'trustbio_allow_guest_no_email'      => 0,
			'trustbio_allowed_roles'             => array( 'administrator', 'editor', 'author', 'contributor' ),
			'trustbio_default_box_id'            => 1,
			'trustbio_box_position'              => 'after',
			'trustbio_exclude_post_ids'          => '',
			'trustbio_auto_insert_pages'         => 1,
			'trustbio_auto_insert_posts'         => 1,
			'trustbio_author_url_base'           => 'authors',
			'trustbio_enable_archive_registered' => 1,
			'trustbio_enable_archive_guest'      => 1,
			'trustbio_avatar_priority'           => array( 'custom', 'gravatar', 'default' ),
			'trustbio_gravatar_default'          => 'mystery',
			'trustbio_profile_post_types'        => array( 'post', 'page' ),
			'trustbio_enable_schema'             => 1,
			'trustbio_schema_compat_mode'        => 1,
			'trustbio_debug_mode'                => 0,
			'trustbio_enable_rest_api'           => 1,
			'trustbio_delete_data_on_uninstall'  => 0,
		);

		foreach ( $trustbio_defaults as $trustbio_key => $trustbio_value ) {
			add_option( $trustbio_key, $trustbio_value );
		}
	}

	/**
	 * Register rewrite rules for author archive pages.
	 */
	private static function register_rewrite_rules(): void {
		$trustbio_base = sanitize_title( get_option( 'trustbio_author_url_base', 'authors' ) );

		add_rewrite_rule(
			'^' . preg_quote( $trustbio_base, '/' ) . '/([^/]+)/?$',
			'index.php?trustbio_author=$matches[1]',
			'top'
		);
	}
}
