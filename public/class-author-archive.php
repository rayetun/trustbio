<?php
/**
 * Author Archive — Phase 15.
 *
 * Handles template loading, post query, pagination, and ProfilePage schema
 * for the public author profile pages at /{author-base}/{slug}/.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Author_Archive
 */
class TrustBio_Author_Archive {

	// =========================================================
	// Template loading
	// =========================================================

	/**
	 * Intercept template_include to serve the author archive template.
	 * Hooked to template_include — must return a file path.
	 *
	 * @param string $trustbio_template Current template path.
	 * @return string
	 */
	public function intercept_template( string $trustbio_template ): string {
		$trustbio_slug = get_query_var( 'trustbio_author' );
		if ( ! $trustbio_slug ) {
			return $trustbio_template;
		}

		if ( ! get_option( 'trustbio_archive_enabled', 1 ) ) {
			return $trustbio_template;
		}

		$trustbio_author = trustbio_get_author_by_slug( sanitize_title( $trustbio_slug ) );
		if ( ! $trustbio_author ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return get_404_template();
		}

		// Make author available to the template.
		$GLOBALS['trustbio_archive_author'] = $trustbio_author;

		// Theme override: trustbio-author-{slug}.php → trustbio-author.php → plugin default.
		$trustbio_theme_templates = array(
			'trustbio-author-' . $trustbio_author->slug . '.php',
			'trustbio-author.php',
		);
		$trustbio_theme_file = locate_template( $trustbio_theme_templates );
		if ( $trustbio_theme_file ) {
			return $trustbio_theme_file;
		}

		// Plugin default template.
		return TRUSTBIO_PLUGIN_DIR . 'public/templates/author-archive.php';
	}

	// =========================================================
	// Assets
	// =========================================================

	/**
	 * Enqueue archive styles on author archive pages.
	 */
	public function enqueue_assets(): void {
		if ( ! get_query_var( 'trustbio_author' ) ) {
			return;
		}

		$trustbio_ver = defined( 'WP_DEBUG' ) && WP_DEBUG
			? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'public/css/trustbio-public.css' )
			: TRUSTBIO_VERSION;

		wp_enqueue_style(
			'trustbio-public',
			TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-public.css',
			array(),
			$trustbio_ver
		);

		wp_enqueue_style(
			'trustbio-archive',
			TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-archive.css',
			array( 'trustbio-public' ),
			$trustbio_ver
		);

		// Inject saved colours as CSS custom properties on .tba-page.
		// This overrides the CSS defaults without touching the stylesheet.
		$trustbio_hero_color   = sanitize_hex_color( get_option( 'trustbio_archive_hero_color', '#312e81' ) ) ?: '#312e81';
		$trustbio_accent_color = sanitize_hex_color( get_option( 'trustbio_archive_accent_color', '#4f46e5' ) ) ?: '#4f46e5';

		// Derive darker start and lighter end for the gradient automatically.
		$trustbio_inline_css = sprintf(
			'.tba-page{--tba-accent:%1$s;--tba-accent-light:%1$s1a;}' .
			'.tba-hero{background:linear-gradient(135deg,%2$sdd 0%%,%2$s 50%%,%2$saa 100%%);}',
			esc_attr( $trustbio_accent_color ),
			esc_attr( $trustbio_hero_color )
		);

		wp_add_inline_style( 'trustbio-archive', $trustbio_inline_css );
	}

	// =========================================================
	// Schema — ProfilePage
	// =========================================================

	/**
	 * Output ProfilePage JSON-LD for the author archive.
	 * Hooked to wp_head.
	 */
	public function output_schema(): void {
		if ( ! get_query_var( 'trustbio_author' ) ) {
			return;
		}

		if ( ! get_option( 'trustbio_enable_schema', 1 ) ) {
			return;
		}

		$trustbio_author = $GLOBALS['trustbio_archive_author'] ?? null;
		if ( ! $trustbio_author ) {
			return;
		}

		$trustbio_avatar = trustbio_get_avatar_url( $trustbio_author, 400 );
		$trustbio_url    = trustbio_get_author_url( $trustbio_author );

		$trustbio_person = array(
			'@type'  => 'Person',
			'name'   => $trustbio_author->display_name,
			'url'    => $trustbio_url,
		);

		if ( $trustbio_avatar ) {
			$trustbio_person['image'] = array( '@type' => 'ImageObject', 'url' => $trustbio_avatar );
		}
		if ( ! empty( $trustbio_author->job_title ) ) {
			$trustbio_person['jobTitle'] = $trustbio_author->job_title;
		}
		if ( ! empty( $trustbio_author->bio ) ) {
			$trustbio_person['description'] = wp_strip_all_tags( $trustbio_author->bio );
		}

		// hasCredential from Phase 11.
		$trustbio_schema_creds = TrustBio_Credentials::get_schema_credentials( $trustbio_author->id );
		if ( ! empty( $trustbio_schema_creds ) ) {
			$trustbio_person['hasCredential'] = count( $trustbio_schema_creds ) === 1
				? $trustbio_schema_creds[0]
				: $trustbio_schema_creds;
		}

		// Contribution count from Phase 14.
		if ( get_option( 'trustbio_show_contribution_count', 0 ) ) {
			$trustbio_total = TrustBio_Contribution_Counter::get_total( (int) $trustbio_author->id );
			if ( $trustbio_total > 0 ) {
				$trustbio_person['numberOfItems'] = $trustbio_total;
			}
		}

		// knowsAbout — expertise topics (v20).
		$trustbio_knows_about = TrustBio_Author_Expertise::build_knows_about( (int) $trustbio_author->id );
		if ( ! empty( $trustbio_knows_about ) ) {
			$trustbio_person['knowsAbout'] = $trustbio_knows_about;
		}

		$trustbio_schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ProfilePage',
			'mainEntity'      => $trustbio_person,
			'url'             => $trustbio_url,
			'dateModified'    => gmdate( 'c' ),
		);

		// JSON_HEX_TAG escapes < and > so no value can close this script
		// element early; see the longer note in public/class-schema.php.
		echo '<script type="application/ld+json">' . wp_json_encode( $trustbio_schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG makes a tag impossible to form.
	}

	// =========================================================
	// Post query
	// =========================================================

	/**
	 * Query published posts for a given TrustBio author.
	 *
	 * Uses a posts_where filter with a raw prepared LIKE query — identical
	 * to TrustBio_Contribution_Counter — because WP_Query meta_query with
	 * LIKE double-escapes the % wildcards inside $wpdb->prepare(), causing
	 * the query to match nothing.
	 *
	 * @param int $trustbio_author_id Author ID.
	 * @param int $trustbio_page      Current page number (1-based).
	 * @param int $trustbio_per_page  Posts per page.
	 * @return WP_Query
	 */
	public static function get_author_posts( int $trustbio_author_id, int $trustbio_page = 1, int $trustbio_per_page = 10 ): WP_Query {
		global $wpdb;

		$trustbio_enabled_types = trustbio_get_enabled_post_types();

		// Build the LIKE pattern once — used in the filter closure below.
		$trustbio_like = '%' . $wpdb->esc_like( '"author_id";i:' . $trustbio_author_id . ';' ) . '%';

		// Inject the meta LIKE condition via posts_where to bypass WP_Query
		// meta_query's internal prepare() that double-escapes % wildcards.
		$trustbio_filter = static function ( string $trustbio_where ) use ( $wpdb, $trustbio_like ): string {
			$trustbio_where .= $wpdb->prepare(
				" AND {$wpdb->posts}.ID IN (
					SELECT post_id FROM {$wpdb->postmeta}
					WHERE meta_key = '_trustbio_authors'
					AND meta_value LIKE %s
				)",
				$trustbio_like
			);
			return $trustbio_where;
		};

		add_filter( 'posts_where', $trustbio_filter );

		$trustbio_query = new WP_Query( array(
			'post_type'      => $trustbio_enabled_types,
			'post_status'    => 'publish',
			'posts_per_page' => $trustbio_per_page,
			'paged'          => $trustbio_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => false,
		) );

		remove_filter( 'posts_where', $trustbio_filter );

		return $trustbio_query;
	}

	// =========================================================
	// Title filter
	// =========================================================

	/**
	 * Set page title on author archive pages.
	 *
	 * @param array $trustbio_title Title parts.
	 * @return array
	 */
	public function filter_title( array $trustbio_title ): array {
		if ( ! get_query_var( 'trustbio_author' ) ) {
			return $trustbio_title;
		}
		$trustbio_author = $GLOBALS['trustbio_archive_author'] ?? null;
		if ( $trustbio_author ) {
			$trustbio_title['title'] = $trustbio_author->display_name;
		}
		return $trustbio_title;
	}

	// =========================================================
	// Author Archive SEO — meta description + OG tags
	// =========================================================

	/**
	 * Register SEO meta filters early (wp action) and output direct tags if
	 * no SEO plugin is active.
	 *
	 * Hooks to 'wp' so the Rank Math / Yoast filters are in place before
	 * those plugins render their head output at wp_head.
	 */
	public function output_seo_meta(): void {
		if ( ! get_query_var( 'trustbio_author' ) ) {
			return;
		}

		$trustbio_author = $GLOBALS['trustbio_archive_author'] ?? null;
		if ( ! $trustbio_author ) {
			return;
		}

		$trustbio_desc = $this->build_author_meta_description( $trustbio_author );

		// Integrate with Rank Math — fill empty descriptions only.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			add_filter(
				'rank_math/frontend/description',
				static function ( string $d ) use ( $trustbio_desc ): string {
					return $d ?: $trustbio_desc;
				},
				20
			);
		}

		// Integrate with Yoast SEO — fill empty descriptions only.
		if ( defined( 'WPSEO_VERSION' ) ) {
			add_filter(
				'wpseo_metadesc',
				static function ( string $d ) use ( $trustbio_desc ): string {
					return $d ?: $trustbio_desc;
				},
				20
			);
		}

		// Fallback: output meta tags directly when no SEO plugin is active.
		if ( ! defined( 'RANK_MATH_VERSION' ) && ! defined( 'WPSEO_VERSION' ) ) {
			add_action(
				'wp_head',
				static function () use ( $trustbio_author, $trustbio_desc ): void {
					echo '<meta name="description" content="' . esc_attr( $trustbio_desc ) . '">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<meta property="og:type"        content="profile">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<meta property="og:title"       content="' . esc_attr( $trustbio_author->display_name ) . '">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<meta property="og:description" content="' . esc_attr( $trustbio_desc ) . '">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				},
				1
			);
		}
	}

	/**
	 * Build a 160-character meta description for an author archive page.
	 *
	 * @param object $trustbio_author Author object.
	 * @return string
	 */
	private function build_author_meta_description( object $trustbio_author ): string {
		$trustbio_parts = array();

		if ( ! empty( $trustbio_author->job_title ) ) {
			$trustbio_parts[] = $trustbio_author->display_name . ' is a ' . $trustbio_author->job_title . '.';
		}

		if ( ! empty( $trustbio_author->bio ) ) {
			$trustbio_plain = wp_strip_all_tags( $trustbio_author->bio );
			if ( preg_match( '/^([^.!?]+[.!?])/u', $trustbio_plain, $trustbio_m ) ) {
				$trustbio_parts[] = trim( $trustbio_m[1] );
			} else {
				$trustbio_parts[] = mb_substr( $trustbio_plain, 0, 120 );
			}
		}

		if ( empty( $trustbio_parts ) ) {
			$trustbio_parts[] = sprintf(
				/* translators: %s: author display name */
				esc_html__( 'Author profile for %s.', 'trustbio' ),
				$trustbio_author->display_name
			);
		}

		$trustbio_desc = implode( ' ', $trustbio_parts );
		if ( mb_strlen( $trustbio_desc ) > 160 ) {
			$trustbio_desc = mb_substr( $trustbio_desc, 0, 157 ) . '...';
		}

		return $trustbio_desc;
	}
}
