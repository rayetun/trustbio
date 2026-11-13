<?php
/**
 * Expert Review Date & Freshness — Phase 12.
 *
 * Adds "Last Reviewed By / On" metadata to posts and pages.
 * Outputs a review bar on the frontend and injects the review signals into
 * JSON-LD: reviewedBy on the article, lastReviewed on the page.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Review_Date
 */
class TrustBio_Review_Date {

	// Meta key constants.
	const META_DATE     = '_trustbio_review_date';
	const META_REVIEWER = '_trustbio_reviewer_id';
	const META_DISPLAY  = '_trustbio_review_display';
	const META_LABEL    = '_trustbio_review_label';
	const META_BAR_ID   = '_trustbio_review_bar_id';

	// =========================================================
	// Admin Hooks
	// =========================================================

	public function register_admin_hooks(): void {
		// Classic Editor meta box.
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post',      array( $this, 'save_meta_box' ), 10, 2 );

		// Posts list column.
		add_filter( 'manage_posts_columns',        array( $this, 'add_column' ) );
		add_filter( 'manage_pages_columns',        array( $this, 'add_column' ) );
		add_action( 'manage_posts_custom_column',  array( $this, 'render_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column',  array( $this, 'render_column' ), 10, 2 );

		// AJAX: Gutenberg sidebar save.
		add_action( 'wp_ajax_trustbio_save_review_date', array( $this, 'ajax_save' ) );

		// Register REST meta for Gutenberg sidebar.
		add_action( 'init', array( $this, 'register_post_meta' ) );

		// Enqueue sidebar data.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_sidebar_data' ) );
	}

	// =========================================================
	// Public Hooks
	// =========================================================

	public function register_public_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		// Priority 10001: after Elementor's the_content renderer (priority 9999).
		add_filter( 'the_content',        array( $this, 'inject_review_bar' ), 10001 );
		add_filter( 'the_title',          array( $this, 'inject_below_title' ), 20 );
		add_shortcode( 'trustbio_review', array( $this, 'render_shortcode' ) );

		// Schema injection.
		add_filter( 'wpseo_schema_article',    array( $this, 'inject_yoast_schema' ), 10, 1 );
		// lastReviewed is a WebPage property, so it goes on Yoast's WebPage
		// piece, not its Article piece. Two filters, two nodes.
		add_filter( 'wpseo_schema_webpage',    array( $this, 'inject_yoast_webpage_schema' ), 10, 1 );
		/*
		 * Priority 20, and it has to be later than 10.
		 *
		 * Rank Math builds its own graph in Frontend::add_schema(), which is
		 * also on priority 10. Within one priority WordPress runs callbacks in
		 * registration order, and TrustBio registers first -- so at priority 10
		 * this ran against a graph that did not have its WebPage or article
		 * node yet, matched nothing, and returned the data untouched. No error,
		 * no warning, just no review date in the structured data of any site
		 * running Rank Math.
		 *
		 * Anything above 10 is ordered by WordPress rather than by which
		 * plugin happened to load first, which is the actual fix.
		 */
		add_filter( 'rank_math/json_ld',       array( $this, 'inject_rankmath_schema' ), 20, 2 );
	}

	// =========================================================
	// Post Meta Registration
	// =========================================================

	public function register_post_meta(): void {
		$trustbio_post_types = trustbio_get_enabled_post_types();

		foreach ( $trustbio_post_types as $trustbio_post_type ) {
			register_post_meta(
				$trustbio_post_type,
				self::META_DATE,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				$trustbio_post_type,
				self::META_REVIEWER,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'integer',
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
			register_post_meta(
				$trustbio_post_type,
				self::META_DISPLAY,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
					'sanitize_callback' => 'sanitize_key',
				)
			);
			register_post_meta(
				$trustbio_post_type,
				self::META_LABEL,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				$trustbio_post_type,
				self::META_BAR_ID,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'integer',
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}

	// =========================================================
	// Classic Editor Meta Box
	// =========================================================

	public function register_meta_box(): void {
		$trustbio_post_types = trustbio_get_enabled_post_types();

		foreach ( $trustbio_post_types as $trustbio_post_type ) {
			add_meta_box(
				'trustbio_review_date',
				'<span style="display:flex;align-items:center;gap:8px;">'
				. '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="#4f46e5"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>'
				. esc_html__( 'TrustBio — Content Review Date', 'trustbio' )
				. '</span>',
				array( $this, 'render_meta_box' ),
				$trustbio_post_type,
				'side',
				'high'
			);
		}
	}

	public function render_meta_box( WP_Post $trustbio_post ): void {
		wp_nonce_field( 'trustbio_review_date_save', 'trustbio_review_date_nonce' );

		$trustbio_date        = get_post_meta( $trustbio_post->ID, self::META_DATE, true );
		$trustbio_reviewer_id = (int) get_post_meta( $trustbio_post->ID, self::META_REVIEWER, true );
		$trustbio_display     = get_post_meta( $trustbio_post->ID, self::META_DISPLAY, true ) ?: 'global';
		$trustbio_label       = get_post_meta( $trustbio_post->ID, self::META_LABEL, true );
		$trustbio_bar_id = (int) get_post_meta( $trustbio_post->ID, self::META_BAR_ID, true );

		// All active TrustBio authors — reviewer is independent of who is assigned to this post.
		global $wpdb;
		$trustbio_at = $wpdb->prefix . 'trustbio_authors';
		$trustbio_ct = $wpdb->prefix . 'trustbio_author_categories';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$trustbio_all_authors = $wpdb->get_results( $wpdb->prepare(
			'SELECT a.id, a.display_name, c.singular AS category_name FROM %i a LEFT JOIN %i c ON c.id = a.category_id WHERE a.is_active = 1 ORDER BY a.display_name ASC',
			$trustbio_at,
			$trustbio_ct
		) );

		$trustbio_all_review_bars = ( new TrustBio_Review_Bar() )->get_all();

		require TRUSTBIO_PLUGIN_DIR . 'admin/views/review-date-metabox.php';
	}

	public function save_meta_box( int $trustbio_post_id, WP_Post $trustbio_post ): void {
		if ( ! isset( $_POST['trustbio_review_date_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['trustbio_review_date_nonce'] ) ),
			'trustbio_review_date_save'
		) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $trustbio_post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( 'auto-draft' === $trustbio_post->post_status ) {
			return;
		}

		$this->save_review_meta( $trustbio_post_id, $_POST );
	}

	// =========================================================
	// AJAX: Gutenberg sidebar
	// =========================================================

	public function ajax_save(): void {
		check_ajax_referer( 'trustbio_save_review_date', 'nonce' );

		$trustbio_post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $trustbio_post_id || ! current_user_can( 'edit_post', $trustbio_post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'trustbio' ) ) );
		}

		$this->save_review_meta( $trustbio_post_id, $_POST );
		wp_send_json_success( array( 'message' => __( 'Review date saved.', 'trustbio' ) ) );
	}

	/**
	 * Save review meta fields from POST data.
	 *
	 * @param int   $trustbio_post_id Post ID.
	 * @param array $trustbio_data    POST data.
	 */
	// Nonce verified by callers.
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	private function save_review_meta( int $trustbio_post_id, array $trustbio_data ): void {
		$trustbio_date = sanitize_text_field( $trustbio_data['trustbio_review_date'] ?? '' );
		if ( $trustbio_date && ! $this->is_valid_date( $trustbio_date ) ) {
			$trustbio_date = '';
		}
		update_post_meta( $trustbio_post_id, self::META_DATE, $trustbio_date );

		$trustbio_reviewer = absint( $trustbio_data['trustbio_reviewer_id'] ?? 0 );
		update_post_meta( $trustbio_post_id, self::META_REVIEWER, $trustbio_reviewer );

		$trustbio_display_options = array( 'global', 'inline', 'bar', 'both', 'none' );
		$trustbio_display = sanitize_key( $trustbio_data['trustbio_review_display'] ?? 'global' );
		if ( ! in_array( $trustbio_display, $trustbio_display_options, true ) ) {
			$trustbio_display = 'global';
		}
		update_post_meta( $trustbio_post_id, self::META_DISPLAY, $trustbio_display );

		$trustbio_label = sanitize_text_field( $trustbio_data['trustbio_review_label'] ?? '' );
		update_post_meta( $trustbio_post_id, self::META_LABEL, $trustbio_label );

		$trustbio_bar_id = absint( $trustbio_data['trustbio_review_bar_id'] ?? 0 );
		if ( $trustbio_bar_id ) {
			update_post_meta( $trustbio_post_id, self::META_BAR_ID, $trustbio_bar_id );
		} else {
			delete_post_meta( $trustbio_post_id, self::META_BAR_ID );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	// =========================================================
	// Posts List Column
	// =========================================================

	public function add_column( array $trustbio_cols ): array {
		if ( ! get_option( 'trustbio_review_show_column', 1 ) ) {
			return $trustbio_cols;
		}
		// Insert before last column.
		$trustbio_new = array();
		foreach ( $trustbio_cols as $trustbio_k => $trustbio_v ) {
			if ( 'date' === $trustbio_k ) {
				$trustbio_new['trustbio_reviewed'] = '<svg xmlns="http://www.w3.org/2000/svg" style="width:14px;height:14px;vertical-align:-2px;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg> ' . esc_html__( 'Last Reviewed', 'trustbio' );
			}
			$trustbio_new[ $trustbio_k ] = $trustbio_v;
		}
		return $trustbio_new;
	}

	public function render_column( string $trustbio_col, int $trustbio_post_id ): void {
		if ( 'trustbio_reviewed' !== $trustbio_col ) {
			return;
		}

		$trustbio_date    = get_post_meta( $trustbio_post_id, self::META_DATE, true );
		$trustbio_stale   = (int) get_option( 'trustbio_review_stale_months', 12 );
		$trustbio_edit_url = admin_url( 'post.php?post=' . $trustbio_post_id . '&action=edit' );

		if ( ! $trustbio_date ) {
			printf(
				'<span style="color:#9ca3af;font-size:12px;">%s</span>',
				esc_html__( '— Never', 'trustbio' )
			);
			return;
		}

		$trustbio_ts       = strtotime( $trustbio_date );
		$trustbio_months   = ( time() - $trustbio_ts ) / MONTH_IN_SECONDS;
		$trustbio_color    = '#16a34a'; // Green.
		if ( $trustbio_months > $trustbio_stale * 2 ) {
			$trustbio_color = '#dc2626'; // Red.
		} elseif ( $trustbio_months > $trustbio_stale ) {
			$trustbio_color = '#d97706'; // Amber.
		}

		printf(
			'<span style="color:%s;font-size:12px;font-weight:600;">%s</span>',
			esc_attr( $trustbio_color ),
			esc_html( date_i18n( get_option( 'date_format' ), $trustbio_ts ) )
		);
	}

	// =========================================================
	// Gutenberg Sidebar Data
	// =========================================================

	public function enqueue_sidebar_data(): void {
		global $post;
		if ( ! $post ) {
			return;
		}

		// All active authors for the reviewer dropdown — independent of post assignment.
		global $wpdb;
		$trustbio_at_s = $wpdb->prefix . 'trustbio_authors';
		$trustbio_ct_s = $wpdb->prefix . 'trustbio_author_categories';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$trustbio_all_authors_s = $wpdb->get_results( $wpdb->prepare(
			'SELECT a.id, a.display_name, c.singular AS category_name FROM %i a LEFT JOIN %i c ON c.id = a.category_id WHERE a.is_active = 1 ORDER BY a.display_name ASC',
			$trustbio_at_s,
			$trustbio_ct_s
		) );

		$trustbio_review_bars_s = ( new TrustBio_Review_Bar() )->get_all();

		wp_add_inline_script(
			'trustbio-sidebar',
			'window.trustbioReviewData = ' . wp_json_encode( array(
				'date'        => get_post_meta( $post->ID, self::META_DATE, true ),
				'reviewerId'  => (int) get_post_meta( $post->ID, self::META_REVIEWER, true ),
				'display'     => get_post_meta( $post->ID, self::META_DISPLAY, true ) ?: 'global',
				'label'       => get_post_meta( $post->ID, self::META_LABEL, true ),
				'reviewBarId' => (int) get_post_meta( $post->ID, self::META_BAR_ID, true ),
				'authors'     => array_map( static fn( $trustbio_e ) => array(
					'id'           => (int) $trustbio_e->id,
					'name'         => $trustbio_e->display_name,
					'categoryName' => $trustbio_e->category_name ?: '',
				), $trustbio_all_authors_s ),
				'reviewBars'  => array_map( static fn( $trustbio_b ) => array(
					'id'    => (int) $trustbio_b->id,
					'title' => $trustbio_b->title,
				), $trustbio_review_bars_s ),
				'nonce'       => wp_create_nonce( 'trustbio_save_review_date' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'globalLabel' => get_option( 'trustbio_review_default_label', __( 'Medically reviewed by', 'trustbio' ) ),
				'displayOptions' => array(
					'global' => __( 'Use site default', 'trustbio' ),
					'inline' => __( 'In author box only', 'trustbio' ),
					'bar'    => __( 'Separate review bar', 'trustbio' ),
					'both'   => __( 'Both (in box + bar)', 'trustbio' ),
					'none'   => __( 'Hidden on this post', 'trustbio' ),
				),
			) ) . ';',
			'before'
		);
	}

	// =========================================================
	// Frontend: Styles
	// =========================================================

	public function enqueue_styles(): void {
		if ( ! is_singular() ) {
			return;
		}
		// Styles are in the main public CSS file — no extra enqueue needed.
	}

	// =========================================================
	// Frontend: Content Injection
	// =========================================================

	public function inject_review_bar( string $trustbio_content ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $trustbio_content;
		}

		$trustbio_post_id = get_the_ID();

		// Skip auto-inject on Elementor/page-builder pages — use [trustbio_review] shortcode instead.
		if ( 'builder' === get_post_meta( $trustbio_post_id, '_elementor_edit_mode', true ) ) {
			return $trustbio_content;
		}
		$trustbio_display = $this->get_effective_display( $trustbio_post_id );
		$trustbio_position = sanitize_key( get_option( 'trustbio_review_bar_position', 'before_content' ) );

		// 'none' → hidden. 'inline' handled inside reviewer's author box.
		// 'below_title' → injected via inject_below_title(), not here.
		if ( 'none' === $trustbio_display || 'inline' === $trustbio_display ) {
			return $trustbio_content;
		}
		if ( 'below_title' === $trustbio_position ) {
			return $trustbio_content; // Handled by inject_below_title().
		}

		$trustbio_bar_html = $this->render_review_bar( $trustbio_post_id );
		if ( ! $trustbio_bar_html ) {
			return $trustbio_content;
		}

		if ( 'after_content' === $trustbio_position ) {
			return $trustbio_content . $trustbio_bar_html;
		}

		// On Elementor pages: always append after fully-rendered content.
		if ( 'builder' === get_post_meta( $trustbio_post_id, '_elementor_edit_mode', true ) ) {
			return $trustbio_content . $trustbio_bar_html;
		}

		// Default: before content.
		return $trustbio_bar_html . $trustbio_content;
	}

	/**
	 * Inject review bar immediately below the post title.
	 * Only fires on singular posts when position = 'below_title'.
	 * Uses the_title filter — fires after the title, before content.
	 *
	 * @param string $trustbio_title Post title.
	 * @return string Modified title with bar appended.
	 */
	public function inject_below_title( string $trustbio_title ): string {
		/*
		 * $trustbio_title is returned exactly as it arrived, never escaped.
		 * the_title has already been through WordPress's own filters and is
		 * escaped by whatever finally prints it; running esc_html() here would
		 * double-encode it and turn every apostrophe and ampersand in a post
		 * title into &#039; and &amp;. The only thing this method adds is
		 * $trustbio_bar_html, which TrustBio_Review_Renderer escapes field by
		 * field as it builds it.
		 */

		// Only on singular pages in the main loop — not in nav menus, widgets, etc.
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $trustbio_title;
		}

		// Never in a feed or an email: the bar is markup, and a feed reader
		// gets the title as text.
		if ( is_feed() || is_admin() ) {
			return $trustbio_title;
		}

		/*
		 * Once per post, whatever the theme does.
		 *
		 * the_title fires every time a template asks for the title, and themes
		 * routinely ask more than once in the same loop — for an image alt, an
		 * og:title, a breadcrumb. Without this guard those extra calls each
		 * appended another review bar, and a bar landing inside an attribute
		 * put raw markup where only text belongs.
		 */
		static $trustbio_done = array();

		$trustbio_id = get_the_ID();
		if ( ! $trustbio_id || isset( $trustbio_done[ $trustbio_id ] ) ) {
			return $trustbio_title;
		}
		if ( 'below_title' !== sanitize_key( get_option( 'trustbio_review_bar_position', 'before_content' ) ) ) {
			return $trustbio_title;
		}

		$trustbio_post_id = $trustbio_id;
		$trustbio_display = $this->get_effective_display( $trustbio_post_id );
		if ( 'none' === $trustbio_display || 'inline' === $trustbio_display ) {
			return $trustbio_title;
		}

		$trustbio_bar_html = $this->render_review_bar( $trustbio_post_id );
		if ( ! $trustbio_bar_html ) {
			return $trustbio_title;
		}

		$trustbio_done[ $trustbio_id ] = true;

		return $trustbio_title . $trustbio_bar_html;
	}

	/**
	 * Render the review bar HTML for a post.
	 *
	 * @param int $trustbio_post_id Post ID.
	 * @return string HTML.
	 */
	public function render_review_bar( int $trustbio_post_id ): string {
		$trustbio_renderer = new TrustBio_Review_Renderer();

		/*
		 * Through the allowlist on the way out.
		 *
		 * Every value in the bar is escaped where it is interpolated, but
		 * this string is returned from the_content, the_title and the
		 * [trustbio_review] shortcode, so WordPress prints it without
		 * further treatment. Constraining the finished markup here means no
		 * future edit inside the renderer or its templates can put
		 * unescaped data on a page.
		 */
		return trustbio_kses_block( $trustbio_renderer->render( $trustbio_post_id ) );
	}


	/**
	 * Render the inline review line (used inside the author box by class-public.php).
	 *
	 * @param int    $trustbio_post_id Post ID.
	 * @param string $trustbio_color   Text colour hex for dark/light boxes.
	 * @return string HTML.
	 */
	public function render_inline_review( int $trustbio_post_id, string $trustbio_color = '#6b7280' ): string {
		$trustbio_display = $this->get_effective_display( $trustbio_post_id );

		// Only render the inline line when display mode is 'inline' or 'both'.
		// 'bar' and 'none' skip the inline — bar handles its own output.
		if ( 'none' === $trustbio_display || 'bar' === $trustbio_display ) {
			return '';
		}

		$trustbio_date = get_post_meta( $trustbio_post_id, self::META_DATE, true );
		if ( ! $trustbio_date ) {
			return '';
		}

		$trustbio_formatted = date_i18n( get_option( 'date_format' ), strtotime( $trustbio_date ) );

		// Wording: "Last reviewed April 30, 2026"
		// The author box already shows the reviewer's name and credentials.
		// Repeating "Reviewed by [name]" inside their own box is redundant and confusing.
		return sprintf(
			'<p class="trustbio-box__review-date" style="font-size:11px;color:%s;margin:4px 0 8px;display:flex;align-items:center;gap:4px;">'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:11px;height:11px;flex-shrink:0;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>'
			. '<time datetime="%s">%s %s</time></p>',
			esc_attr( $trustbio_color ),
			esc_attr( $trustbio_date ),
			esc_html__( 'Last reviewed', 'trustbio' ),
			esc_html( $trustbio_formatted )
		);
	}

	// =========================================================
	// Shortcode: [trustbio_review]
	// =========================================================

	/**
	 * Render the [trustbio_review] shortcode.
	 *
	 * Usage:
	 *   [trustbio_review]                   — auto for current post
	 *   [trustbio_review post_id="5"]        — specific post
	 *   [trustbio_review style="bar"]        — force bar style
	 *   [trustbio_review label="Reviewed by"] — custom label
	 *
	 * @param array<string,string> $trustbio_atts Shortcode attributes.
	 * @return string HTML.
	 */
	public function render_shortcode( array $trustbio_atts ): string {
		$trustbio_atts = shortcode_atts(
			array(
				'post_id' => '',
				'style'   => 'bar',
				'label'   => '',
			),
			$trustbio_atts,
			'trustbio_review'
		);

		$trustbio_post_id = ! empty( $trustbio_atts['post_id'] )
			? absint( $trustbio_atts['post_id'] )
			: get_the_ID();

		if ( ! $trustbio_post_id ) {
			return '';
		}

		$trustbio_date = get_post_meta( $trustbio_post_id, self::META_DATE, true );
		if ( ! $trustbio_date ) {
			return '';
		}

		// Enqueue public CSS.
		wp_enqueue_style( 'trustbio-public', TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-public.css', array(), TRUSTBIO_VERSION );

		$trustbio_label_override = sanitize_text_field( $trustbio_atts['label'] );
		if ( $trustbio_label_override ) {
			// Temporarily override label for this render.
			add_filter( 'trustbio_review_label', static fn() => $trustbio_label_override, 999 );
		}

		$trustbio_style = sanitize_key( $trustbio_atts['style'] );
		if ( 'inline' === $trustbio_style ) {
			$trustbio_output = $this->render_inline_review( $trustbio_post_id );
		} else {
			$trustbio_output = $this->render_review_bar( $trustbio_post_id );
		}

		if ( $trustbio_label_override ) {
			remove_all_filters( 'trustbio_review_label', 999 );
		}

		// Through the allowlist: a shortcode's return value is printed by
		// WordPress as it stands. The bar path is already filtered inside
		// render_review_bar(); wp_kses() is idempotent, so running it again
		// here costs nothing and covers the inline path as well.
		return trustbio_kses_block( $trustbio_output );
	}

	// =========================================================
	// Schema Injection
	// =========================================================

	/**
	 * Inject reviewedBy into Yoast SEO Article schema.
	 *
	 * Only reviewedBy: its domain is CreativeWork, which an Article is.
	 * The review date goes to inject_yoast_webpage_schema() instead.
	 *
	 * @param array $trustbio_schema Yoast schema data.
	 * @return array Modified schema.
	 */
	public function inject_yoast_schema( array $trustbio_schema ): array {
		if ( ! is_singular() ) {
			return $trustbio_schema;
		}

		$trustbio_additions = $this->get_schema_additions( get_the_ID() );
		if ( empty( $trustbio_additions ) ) {
			return $trustbio_schema;
		}

		return array_merge( $trustbio_schema, $trustbio_additions );
	}

	/**
	 * Inject lastReviewed into Yoast SEO's WebPage schema.
	 *
	 * lastReviewed has domain WebPage. Yoast emits a WebPage node of its own,
	 * so the date belongs there rather than on the Article beside it.
	 *
	 * @param array $trustbio_schema Yoast WebPage schema data.
	 * @return array Modified schema.
	 */
	public function inject_yoast_webpage_schema( array $trustbio_schema ): array {
		if ( ! is_singular() ) {
			return $trustbio_schema;
		}

		$trustbio_additions = $this->get_webpage_additions( (int) get_the_ID() );
		if ( empty( $trustbio_additions ) ) {
			return $trustbio_schema;
		}

		return array_merge( $trustbio_schema, $trustbio_additions );
	}

	/**
	 * Inject the review signals into Rank Math's JSON-LD graph.
	 *
	 * Rank Math hands over the whole graph, so both nodes are reachable in
	 * one pass: reviewedBy onto the article, lastReviewed onto the page.
	 *
	 * @param array  $trustbio_json_ld Rank Math JSON-LD data.
	 * @param object $trustbio_entity  Rank Math entity.
	 * @return array Modified schema.
	 */
	public function inject_rankmath_schema( array $trustbio_json_ld, $trustbio_entity ): array {
		if ( ! is_singular() ) {
			return $trustbio_json_ld;
		}

		$trustbio_post_id = (int) get_the_ID();
		$trustbio_article = $this->get_schema_additions( $trustbio_post_id );
		$trustbio_webpage = $this->get_webpage_additions( $trustbio_post_id );

		if ( empty( $trustbio_article ) && empty( $trustbio_webpage ) ) {
			return $trustbio_json_ld;
		}

		/*
		 * MedicalWebPage used to be listed here as an article type. It is a
		 * WebPage, not a CreativeWork article, so it sits in the page list now
		 * -- which is also the only place it can carry lastReviewed.
		 */
		$trustbio_article_types = array( 'Article', 'NewsArticle', 'BlogPosting' );
		$trustbio_page_types    = array(
			'WebPage', 'ItemPage', 'AboutPage', 'ContactPage',
			'CollectionPage', 'ProfilePage', 'FAQPage', 'MedicalWebPage',
		);

		$trustbio_article_done = empty( $trustbio_article );
		$trustbio_webpage_done = empty( $trustbio_webpage );

		foreach ( $trustbio_json_ld as &$trustbio_item ) {
			if ( ! is_array( $trustbio_item ) || ! isset( $trustbio_item['@type'] ) ) {
				continue;
			}

			// @type may be a list: "@type": [ "Article", "NewsArticle" ].
			$trustbio_types = (array) $trustbio_item['@type'];

			if ( ! $trustbio_article_done && array_intersect( $trustbio_types, $trustbio_article_types ) ) {
				$trustbio_item         = array_merge( $trustbio_item, $trustbio_article );
				$trustbio_article_done = true;
			}

			if ( ! $trustbio_webpage_done && array_intersect( $trustbio_types, $trustbio_page_types ) ) {
				$trustbio_item         = array_merge( $trustbio_item, $trustbio_webpage );
				$trustbio_webpage_done = true;
			}

			if ( $trustbio_article_done && $trustbio_webpage_done ) {
				break;
			}
		}
		unset( $trustbio_item );

		return $trustbio_json_ld;
	}

	/**
	 * Article-domain review additions for a post: reviewedBy, and nothing
	 * else. See get_webpage_additions() for the date.
	 *
	 * @param int $trustbio_post_id Post ID.
	 * @return array Schema properties to merge.
	 */
	public function get_schema_additions( int $trustbio_post_id ): array {
		if ( ! get_option( 'trustbio_enable_schema', 1 ) ) {
			return array();
		}

		$trustbio_date = get_post_meta( $trustbio_post_id, self::META_DATE, true );
		if ( ! $trustbio_date ) {
			return array();
		}

		/*
		 * dateReviewed is gone from here, and was never a schema.org
		 * property: nothing that validates against the vocabulary ever read
		 * it, so the review date it carried was dropped on the floor. The
		 * real property is lastReviewed, whose domain is WebPage and not
		 * Article, so it cannot be renamed in place -- it is returned by
		 * get_webpage_additions() and printed on a WebPage node.
		 *
		 * reviewedBy stays. Its domain is CreativeWork, which an Article is.
		 */
		$trustbio_additions = array();

		$trustbio_reviewer_id = (int) get_post_meta( $trustbio_post_id, self::META_REVIEWER, true );
		if ( $trustbio_reviewer_id ) {
			$trustbio_reviewer = trustbio_get_author( $trustbio_reviewer_id );
			if ( $trustbio_reviewer ) {
				$trustbio_person = array(
					'@type'    => 'Person',
					'name'     => $trustbio_reviewer->display_name,
					'url'      => trustbio_get_author_url( $trustbio_reviewer ),
				);
				if ( ! empty( $trustbio_reviewer->job_title ) ) {
					$trustbio_person['jobTitle'] = $trustbio_reviewer->job_title;
				}

				// Add credentials to reviewer schema.
				$trustbio_schema_creds = TrustBio_Credentials::get_schema_credentials( $trustbio_reviewer_id );
				if ( ! empty( $trustbio_schema_creds ) ) {
					$trustbio_person['hasCredential'] = count( $trustbio_schema_creds ) === 1
						? $trustbio_schema_creds[0]
						: $trustbio_schema_creds;
				}

				$trustbio_additions['reviewedBy'] = $trustbio_person;
			}
		}

		return $trustbio_additions;
	}

	/**
	 * The stored last-reviewed date for a post, or '' when there is none.
	 *
	 * @param int $trustbio_post_id Post ID.
	 * @return string Date as stored, or ''.
	 */
	public function get_review_date( int $trustbio_post_id ): string {
		if ( ! get_option( 'trustbio_enable_schema', 1 ) ) {
			return '';
		}

		return (string) get_post_meta( $trustbio_post_id, self::META_DATE, true );
	}

	/**
	 * WebPage-domain review additions for a post.
	 *
	 * Just lastReviewed today. It lives apart from get_schema_additions()
	 * because the two belong on different nodes, and a caller that merges
	 * both into one node publishes an invalid claim.
	 *
	 * @param int $trustbio_post_id Post ID.
	 * @return array<string,string> Schema properties for a WebPage node.
	 */
	public function get_webpage_additions( int $trustbio_post_id ): array {
		$trustbio_date = $this->get_review_date( $trustbio_post_id );

		return '' === $trustbio_date ? array() : array( 'lastReviewed' => $trustbio_date );
	}

	// =========================================================
	// Helpers
	// =========================================================

	/**
	 * Get the effective display mode for a post.
	 * 'global' means read the site-wide setting.
	 *
	 * @param int $trustbio_post_id Post ID.
	 * @return string Display mode: inline|bar|both|none.
	 */
	public function get_effective_display( int $trustbio_post_id ): string {
		$trustbio_per_post = get_post_meta( $trustbio_post_id, self::META_DISPLAY, true );

		if ( $trustbio_per_post && 'global' !== $trustbio_per_post ) {
			return $trustbio_per_post;
		}

		return sanitize_key( get_option( 'trustbio_review_display', 'inline' ) );
	}

	/**
	 * Get the review label for a post.
	 *
	 * @param int $trustbio_post_id Post ID.
	 * @return string Label string.
	 */
	public function get_label( int $trustbio_post_id ): string {
		$trustbio_label = get_post_meta( $trustbio_post_id, self::META_LABEL, true );
		if ( ! $trustbio_label ) {
			$trustbio_label = get_option( 'trustbio_review_default_label', __( 'Medically reviewed by', 'trustbio' ) );
		}

		/**
		 * Filter the review label for a post.
		 *
		 * @param string $trustbio_label   The label string.
		 * @param int    $trustbio_post_id Post ID.
		 */
		return (string) apply_filters( 'trustbio_review_label', $trustbio_label, $trustbio_post_id );
	}

	/**
	 * Validate a date string (YYYY-MM-DD).
	 *
	 * @param string $trustbio_date Date string.
	 * @return bool
	 */
	private function is_valid_date( string $trustbio_date ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $trustbio_date ) ) {
			return false;
		}
		return false !== strtotime( $trustbio_date );
	}
}
