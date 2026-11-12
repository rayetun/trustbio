<?php
/**
 * Public-facing functionality.
 * Handles frontend box injection, rewrite rules, author archives.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Public
 */
class TrustBio_Public {

	// =========================================================
	// Asset Enqueuing
	// =========================================================

	public function enqueue_assets(): void {
		if ( ! is_singular() ) {
			return;
		}

		$trustbio_post_type = get_post_type();
		if ( $trustbio_post_type && ! trustbio_is_enabled_for_post_type( $trustbio_post_type ) ) {
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
	}

	// =========================================================
	// Content Injection
	// =========================================================

	/**
	 * Inject author boxes into post content via the_content filter.
	 *
	 * @param string $trustbio_content The post content.
	 * @return string Modified content.
	 */
	public function inject_author_box( string $trustbio_content ): string {
		// Only run on the main query for singular posts.
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $trustbio_content;
		}

		$trustbio_post_id   = get_the_ID();
		$trustbio_post_type = get_post_type( $trustbio_post_id );

		// Check if enabled for this post type.
		if ( ! $trustbio_post_type || ! trustbio_is_enabled_for_post_type( $trustbio_post_type ) ) {
			return $trustbio_content;
		}

		// Skip auto-inject on Elementor/page-builder pages — use [trustbio] shortcode instead.
		if ( 'builder' === get_post_meta( $trustbio_post_id, '_elementor_edit_mode', true ) ) {
			return $trustbio_content;
		}

		// Check per-post hide flag.
		if ( get_post_meta( $trustbio_post_id, '_trustbio_hide_box', true ) ) {
			return $trustbio_content;
		}

		// Check excluded post IDs.
		$trustbio_exclude_raw = get_option( 'trustbio_exclude_post_ids', '' );
		if ( $trustbio_exclude_raw ) {
			$trustbio_excluded = array_map( 'absint', explode( ',', $trustbio_exclude_raw ) );
			if ( in_array( $trustbio_post_id, $trustbio_excluded, true ) ) {
				return $trustbio_content;
			}
		}

		// Check position setting.
		$trustbio_position = get_option( 'trustbio_box_position', 'after' );
		if ( 'manual' === $trustbio_position ) {
			return $trustbio_content;
		}

		// Check auto-insert settings.
		if ( 'post' === $trustbio_post_type && ! get_option( 'trustbio_auto_insert_posts', 1 ) ) {
			return $trustbio_content;
		}
		if ( 'page' === $trustbio_post_type && ! get_option( 'trustbio_auto_insert_pages', 1 ) ) {
			return $trustbio_content;
		}

		// Render the author box HTML.
		$trustbio_box_html = $this->render_post_author_box( $trustbio_post_id );

		if ( empty( $trustbio_box_html ) ) {
			return $trustbio_content;
		}

		// Inject based on position.
		if ( 'before' === $trustbio_position ) {
			return $trustbio_box_html . $trustbio_content;
		} elseif ( 'both' === $trustbio_position ) {
			return $trustbio_box_html . $trustbio_content . $trustbio_box_html;
		}

		// Default: after.
		return $trustbio_content . $trustbio_box_html;
	}

	// =========================================================
	// Box Rendering Engine
	// =========================================================

	/** Maps layout slug → template file (relative to public/templates/). */
	private const LAYOUT_TEMPLATES = array(
		'byline'         => 'box-byline.php',
		'quote'          => 'box-quote.php',
		'minimal-inline' => 'box-minimal-inline.php',
		'simple'         => 'box-simple.php',
	);

	/**
	 * All known layout slug → template filename pairs, including add-on layouts.
	 *
	 * @return array<string,string>
	 */
	private static function get_layout_templates(): array {
		/**
		 * Filters the layout slug → template filename map.
		 *
		 * The TrustBio Pro add-on registers its premium layouts here.
		 *
		 * @since 1.0.0
		 * @param array<string,string> $templates Layout slug => template filename.
		 */
		return (array) apply_filters( 'trustbio_box_templates', self::LAYOUT_TEMPLATES );
	}

	/**
	 * Resolve a layout slug to an existing template file path.
	 *
	 * Falls back to the Classic Byline template, which always ships with the
	 * free plugin, so a box saved with an add-on layout still renders if the
	 * add-on is no longer present.
	 *
	 * @param string $trustbio_layout   Layout slug.
	 * @param string $trustbio_fallback Fallback layout slug.
	 * @return string Absolute path to an existing template file, or '' if none.
	 */
	private static function resolve_template( string $trustbio_layout, string $trustbio_fallback = 'byline' ): string {
		$trustbio_templates = self::get_layout_templates();

		foreach ( array( $trustbio_layout, $trustbio_fallback, 'byline' ) as $trustbio_try ) {
			if ( empty( $trustbio_templates[ $trustbio_try ] ) ) {
				continue;
			}
			/**
			 * Filters the directory a layout's template is loaded from.
			 *
			 * @since 1.0.0
			 * @param string $dir    Directory with a trailing slash.
			 * @param string $layout Layout slug being resolved.
			 */
			$trustbio_dir  = (string) apply_filters(
				'trustbio_box_template_dir',
				TRUSTBIO_PLUGIN_DIR . 'public/templates/',
				$trustbio_try
			);
			$trustbio_path = $trustbio_dir . $trustbio_templates[ $trustbio_try ];
			if ( file_exists( $trustbio_path ) ) {
				return $trustbio_path;
			}
		}

		return '';
	}

	/**
	 * Two-author layouts that receive an $authors array instead of a single $author.
	 *
	 * @return array<int,string>
	 */
	private static function get_duo_layouts(): array {
		/**
		 * Filters the list of layouts that render two authors in one box.
		 *
		 * @since 1.0.0
		 * @param array<int,string> $layouts Layout slugs.
		 */
		return (array) apply_filters( 'trustbio_duo_layouts', array() );
	}

	/**
	 * Render all author boxes for a given post ID.
	 *
	 * @param int $trustbio_post_id Post ID.
	 * @return string HTML.
	 */
	public function render_post_author_box( int $trustbio_post_id ): string {
		$trustbio_authors = trustbio_get_post_authors( $trustbio_post_id );

		if ( empty( $trustbio_authors ) ) {
			return '';
		}

		// Determine which box to use.
		$trustbio_box_id = (int) get_post_meta( $trustbio_post_id, '_trustbio_box_id', true );
		if ( ! $trustbio_box_id ) {
			$trustbio_box_id = (int) get_option( 'trustbio_default_box_id', 0 );
		}

		$trustbio_box_class = new TrustBio_Author_Box();
		$trustbio_box       = $trustbio_box_id
			? $trustbio_box_class->get_box( $trustbio_box_id )
			: $trustbio_box_class->get_default_author_box_obj();

		if ( ! $trustbio_box ) {
			return '';
		}

		$trustbio_s      = is_array( $trustbio_box->settings ) ? $trustbio_box->settings : $trustbio_box_class->get_default_settings();
		$trustbio_layout = $trustbio_box->layout ?? 'byline';

		$trustbio_raw_classes = $trustbio_s['custom_class'] ?? '';
		$trustbio_extra_class = '';
		if ( $trustbio_raw_classes ) {
			$trustbio_extra_class = implode( ' ', array_filter( array_map(
				'sanitize_html_class',
				preg_split( '/\s+/', trim( $trustbio_raw_classes ) )
			) ) );
		}

		$trustbio_html = '<div class="trustbio-author-section' . ( $trustbio_extra_class ? ' ' . esc_attr( $trustbio_extra_class ) : '' ) . '">';

		if ( in_array( $trustbio_layout, self::get_duo_layouts(), true ) ) {
			$trustbio_count = count( $trustbio_authors );
			if ( 4 === $trustbio_count ) {
				// 4 authors: two duo boxes (2+2).
				$trustbio_html .= $this->render_two_author_box( array_slice( $trustbio_authors, 0, 2 ), $trustbio_layout, $trustbio_s, $trustbio_post_id );
				$trustbio_html .= $this->render_two_author_box( array_slice( $trustbio_authors, 2, 2 ), $trustbio_layout, $trustbio_s, $trustbio_post_id );
			} elseif ( $trustbio_count <= 2 ) {
				// 1 or 2 authors: duo template (handles single-column fallback internally).
				$trustbio_html .= $this->render_two_author_box( $trustbio_authors, $trustbio_layout, $trustbio_s, $trustbio_post_id );
			} else {
				// 3 or 5+ authors: fall back to individual byline boxes.
				$trustbio_html .= '<div class="trustbio-stack">';
				foreach ( $trustbio_authors as $trustbio_entry ) {
					do_action( 'trustbio_before_box', $trustbio_entry['author'], $trustbio_post_id );
					$trustbio_html .= $this->render_single_box( $trustbio_entry['author'], $trustbio_entry['category'], 'byline', $trustbio_s, $trustbio_post_id );
					do_action( 'trustbio_after_box', $trustbio_entry['author'], $trustbio_post_id );
				}
				$trustbio_html .= '</div>';
			}
		} else {
			$trustbio_html .= '<div class="trustbio-stack">';
			foreach ( $trustbio_authors as $trustbio_entry ) {
				do_action( 'trustbio_before_box', $trustbio_entry['author'], $trustbio_post_id );
				$trustbio_html .= $this->render_single_box(
					$trustbio_entry['author'],
					$trustbio_entry['category'],
					$trustbio_layout,
					$trustbio_s,
					$trustbio_post_id
				);
				do_action( 'trustbio_after_box', $trustbio_entry['author'], $trustbio_post_id );
			}
			$trustbio_html .= '</div>';
		}

		if ( get_option( 'trustbio_show_powered_by', 0 ) ) {
			$trustbio_html .= '<p class="trustbio-powered-by"><a href="https://wordpress.org/plugins/trustbio/" rel="nofollow">Powered by TrustBio</a></p>';
		}

		$trustbio_html .= '</div>';

		/**
		 * Filter the complete author box HTML output.
		 *
		 * @param string $trustbio_html    The rendered HTML.
		 * @param int    $trustbio_post_id The post ID.
		 * @param array  $trustbio_authors Array of author entries.
		 */
		/*
		 * Through the allowlist before the filter runs.
		 *
		 * This string is returned from the_content and from the [trustbio]
		 * shortcode, so WordPress prints it untouched. Every value in it is
		 * already escaped where it is interpolated; constraining the
		 * finished markup means no later edit to a box template can quietly
		 * undo that.
		 *
		 * Deliberately before apply_filters(), not after: what an add-on
		 * returns from a documented extension point is the add-on's
		 * responsibility, exactly as with the_content itself, and filtering
		 * it here would silently break integrations that are doing nothing
		 * wrong.
		 */
		$trustbio_html = trustbio_kses_block( $trustbio_html );

		return apply_filters( 'trustbio_box_output', $trustbio_html, $trustbio_post_id, $trustbio_authors );
	}

	/**
	 * Render a single author box via its layout template.
	 *
	 * @param object      $author     Author object.
	 * @param object|null $category   Author category object.
	 * @param string      $layout     Layout slug.
	 * @param array       $settings   Box settings.
	 * @param int         $post_id    Post ID.
	 * @return string HTML.
	 */
	private function render_single_box(
		object $author,
		?object $category,
		string $layout,
		array $settings,
		int $post_id
	): string {
		$trustbio_template_path = self::resolve_template( $layout );

		if ( '' === $trustbio_template_path ) {
			return '';
		}

		$avatar_url = trustbio_get_avatar_url( $author, (int) ( $settings['avatar_size'] ?? 96 ) );
		$author_url = trustbio_get_author_url( $author );
		$social_html = $this->render_social_icons( $author, $settings );

		// Credential badges and review date are shared across templates.
		$trustbio_badges_html = TrustBio_Credentials::render_badges( $author->id );

		$trustbio_review_html = '';
		if ( $post_id > 0 && ( $settings['show_review_date'] ?? true ) ) {
			$trustbio_reviewer_id = (int) get_post_meta( $post_id, TrustBio_Review_Date::META_REVIEWER, true );
			if ( $trustbio_reviewer_id > 0 && $trustbio_reviewer_id === (int) $author->id ) {
				$trustbio_review      = new TrustBio_Review_Date();
				$trustbio_review_html = $trustbio_review->render_inline_review( $post_id, $settings['jobtitle_color'] ?? '#6b7280' );
			}
		}

		// Contribution count badge.
		$trustbio_contribution_html = '';
		if ( get_option( 'trustbio_show_contribution_count', 0 ) ) {
			$trustbio_min_count = (int) get_option( 'trustbio_contribution_min_count', 1 );
			$trustbio_cat_id    = $category ? (int) $category->id : null;
			$trustbio_count     = TrustBio_Contribution_Counter::get_count_for_category( (int) $author->id, $trustbio_cat_id );

			if ( $trustbio_count >= $trustbio_min_count ) {
				$trustbio_verb = ( $category && ! empty( $category->contribution_verb ) )
					? $category->contribution_verb
					: __( 'Written', 'trustbio' );

				$trustbio_noun = 1 === $trustbio_count
					? get_option( 'trustbio_contribution_noun', __( 'article', 'trustbio' ) )
					: get_option( 'trustbio_contribution_noun_plural', __( 'articles', 'trustbio' ) );

				$trustbio_contribution_html = '<p class="trustbio-box__contribution" style="font-size:11px;color:' . esc_attr( $settings['jobtitle_color'] ?? '#6b7280' ) . ';margin:2px 0 6px;display:flex;align-items:center;gap:4px;">'
					. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:11px;height:11px;flex-shrink:0;"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>'
					. esc_html( $trustbio_verb ) . ' '
					. esc_html( number_format_i18n( $trustbio_count ) ) . ' '
					. esc_html( $trustbio_noun )
					. '</p>';
			}
		}

		ob_start();
		// Variables available inside each template:
		// $author, $category, $settings, $social_html, $author_url, $avatar_url,
		// $post_id, $trustbio_badges_html, $trustbio_review_html, $trustbio_contribution_html
		// Each template computes $trustbio_bio_display via trustbio_apply_bio_limit().
		include $trustbio_template_path;
		return ob_get_clean() ?: '';
	}

	/**
	 * Render a two-author layout (duo-light / duo-dark).
	 *
	 * @param array  $trustbio_entries Up to 2 author entry arrays.
	 * @param string $trustbio_layout  Layout slug.
	 * @param array  $trustbio_s       Box settings.
	 * @param int    $trustbio_post_id Post ID.
	 * @return string HTML.
	 */
	private function render_two_author_box(
		array $trustbio_entries,
		string $trustbio_layout,
		array $trustbio_s,
		int $trustbio_post_id
	): string {
		$trustbio_template_path = self::resolve_template( $trustbio_layout, 'duo-light' );

		if ( '' === $trustbio_template_path ) {
			return '';
		}

		// Template vars: $authors (array of entries), $settings, $post_id.
		$authors  = $trustbio_entries;
		$settings = $trustbio_s;
		$post_id  = $trustbio_post_id;

		ob_start();
		include $trustbio_template_path;
		return ob_get_clean() ?: '';
	}

	/**
	 * Render social icon links for an author.
	 *
	 * @param object $trustbio_author Author object.
	 * @param array  $trustbio_s      Box settings.
	 * @return string HTML.
	 */
	private function render_social_icons( object $trustbio_author, array $trustbio_s ): string {
		if ( ! apply_filters( 'trustbio_show_social_in_widget', true ) ) {
			return '';
		}
		return trustbio_render_social_icons_html( $trustbio_author, $trustbio_s );
	}

	// =========================================================
	// Shortcode Helpers
	// =========================================================

	/**
	 * Render a box for a specific author (from shortcode).
	 *
	 * @param object               $trustbio_author Author object.
	 * @param array<string,string> $trustbio_atts   Shortcode attributes.
	 * @return string HTML.
	 */
	public function render_shortcode_author( object $trustbio_author, array $trustbio_atts ): string {
		$trustbio_box_class = new TrustBio_Author_Box();
		$trustbio_box       = ! empty( $trustbio_atts['box'] )
			? $trustbio_box_class->get_box_by_title_slug( sanitize_title( $trustbio_atts['box'] ) )
			: $trustbio_box_class->get_default_author_box_obj();

		if ( ! $trustbio_box ) {
			$trustbio_box = $trustbio_box_class->get_default_author_box_obj();
		}

		if ( ! $trustbio_box ) {
			return '';
		}

		$trustbio_s      = is_array( $trustbio_box->settings ) ? $trustbio_box->settings : $trustbio_box_class->get_default_settings();
		$trustbio_layout = $trustbio_box->layout ?? 'byline';

		$trustbio_html  = '<div class="trustbio-author-section">';
		$trustbio_html .= '<div class="trustbio-stack">';
		$trustbio_html .= $this->render_single_box( $trustbio_author, null, $trustbio_layout, $trustbio_s, 0 );
		$trustbio_html .= '</div></div>';

		// A shortcode return value is printed as it stands.
		return trustbio_kses_block( $trustbio_html );
	}

	/**
	 * Render an array of author entries as a box section.
	 *
	 * @param array                $trustbio_authors Author entries.
	 * @param array<string,string> $trustbio_atts    Shortcode attributes.
	 * @return string HTML.
	 */
	public function render_authors_html( array $trustbio_authors, array $trustbio_atts ): string {
		$trustbio_box_class = new TrustBio_Author_Box();
		$trustbio_box       = $trustbio_box_class->get_default_author_box_obj();

		if ( ! $trustbio_box ) {
			return '';
		}

		$trustbio_s      = is_array( $trustbio_box->settings ) ? $trustbio_box->settings : $trustbio_box_class->get_default_settings();
		$trustbio_layout = $trustbio_box->layout ?? 'byline';

		$trustbio_html = '<div class="trustbio-author-section">';

		if ( in_array( $trustbio_layout, self::get_duo_layouts(), true ) ) {
			$trustbio_sc_count = count( $trustbio_authors );
			if ( 4 === $trustbio_sc_count ) {
				$trustbio_html .= $this->render_two_author_box( array_slice( $trustbio_authors, 0, 2 ), $trustbio_layout, $trustbio_s, 0 );
				$trustbio_html .= $this->render_two_author_box( array_slice( $trustbio_authors, 2, 2 ), $trustbio_layout, $trustbio_s, 0 );
			} elseif ( $trustbio_sc_count <= 2 ) {
				$trustbio_html .= $this->render_two_author_box( $trustbio_authors, $trustbio_layout, $trustbio_s, 0 );
			} else {
				$trustbio_html .= '<div class="trustbio-stack">';
				foreach ( $trustbio_authors as $trustbio_entry ) {
					$trustbio_html .= $this->render_single_box( $trustbio_entry['author'], $trustbio_entry['category'], 'byline', $trustbio_s, 0 );
				}
				$trustbio_html .= '</div>';
			}
		} else {
			$trustbio_html .= '<div class="trustbio-stack">';
			foreach ( $trustbio_authors as $trustbio_entry ) {
				$trustbio_html .= $this->render_single_box(
					$trustbio_entry['author'],
					$trustbio_entry['category'],
					$trustbio_layout,
					$trustbio_s,
					0
				);
			}
			$trustbio_html .= '</div>';
		}

		$trustbio_html .= '</div>';

		// A shortcode return value is printed as it stands.
		return trustbio_kses_block( $trustbio_html );
	}

	// =========================================================
	// Rewrite Rules & Archive
	// =========================================================

	public function register_rewrite_rules(): void {
		$trustbio_base = sanitize_title( get_option( 'trustbio_author_url_base', 'authors' ) );
		add_rewrite_rule(
			'^' . preg_quote( $trustbio_base, '/' ) . '/([^/]+)/?$',
			'index.php?trustbio_author=$matches[1]',
			'top'
		);
	}

	public function register_query_vars( array $trustbio_vars ): array {
		$trustbio_vars[] = 'trustbio_author';
		return $trustbio_vars;
	}

	public function handle_author_archive(): void {
		$trustbio_slug = get_query_var( 'trustbio_author' );
		if ( ! $trustbio_slug ) {
			return;
		}

		$trustbio_author = trustbio_get_author_by_slug( sanitize_title( $trustbio_slug ) );
		if ( ! $trustbio_author ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		// Set up template variables and load theme template.
		set_query_var( 'trustbio_author_obj', $trustbio_author );

		// Try to load a theme template, otherwise use our own.
		$trustbio_templates = array(
			'trustbio-author-' . $trustbio_author->slug . '.php',
			'trustbio-author.php',
			'author.php',
			'index.php',
		);

		$trustbio_template = locate_template( $trustbio_templates );
		if ( $trustbio_template ) {
			load_template( $trustbio_template );
			exit;
		}
	}
}
