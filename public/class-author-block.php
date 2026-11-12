<?php
/**
 * Phase 18 — TrustBio Author Box Gutenberg Block.
 *
 * Server-side rendered block. No build step required.
 * JS written in vanilla ES5 + wp.* globals so it works without webpack.
 *
 * Block attributes:
 *   mode        string  'fixed' | 'current_post'
 *   author_id   integer TrustBio author ID (mode=fixed only)
 *   box_id      integer Author box design ID (0 = default)
 *   show_bio    boolean Show author bio
 *   show_social boolean Show social links
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Author_Block
 */
class TrustBio_Author_Block {

	// =========================================================
	// Registration
	// =========================================================

	/**
	 * Register the block and enqueue editor assets.
	 * Hooked to init.
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // WP < 5.0, very unlikely but safe.
		}

		register_block_type(
			'trustbio/author-box',
			array(
				'title'           => __( 'TrustBio Author Box', 'trustbio' ),
				'description'     => __( "Display a TrustBio author profile box — fixed or current post's author.", 'trustbio' ),
				'category'        => 'widgets',
				'icon'            => 'admin-users',
				'supports'        => array(
					'html'  => false,
					'align' => array( 'wide', 'full' ),
				),
				'attributes'      => array(
					'mode'           => array( 'type' => 'string',  'default' => 'current_post' ),
					'author_id'      => array( 'type' => 'integer', 'default' => 0 ),
					'box_id'         => array( 'type' => 'integer', 'default' => 0 ),
					'sidebar_box_id' => array( 'type' => 'integer', 'default' => 0 ),
					'show_bio'       => array( 'type' => 'boolean', 'default' => true ),
					'show_social'    => array( 'type' => 'boolean', 'default' => true ),
				),
				'render_callback' => array( $this, 'render' ),
				'editor_script'   => 'trustbio-author-block-editor',
				'editor_style'    => 'trustbio-author-block-editor-style',
			)
		);

		$this->register_editor_assets();
	}

	// =========================================================
	// Editor assets
	// =========================================================

	/**
	 * Register the block editor script and style.
	 * Called from register() — assets only enqueued in the editor context.
	 */
	private function register_editor_assets(): void {
		$trustbio_ver = defined( 'WP_DEBUG' ) && WP_DEBUG
			? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'public/js/trustbio-author-block.js' )
			: TRUSTBIO_VERSION;

		wp_register_script(
			'trustbio-author-block-editor',
			TRUSTBIO_PLUGIN_URL . 'public/js/trustbio-author-block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-components',
				'wp-block-editor',
				'wp-server-side-render',
				'wp-i18n',
				'wp-api-fetch',
			),
			$trustbio_ver,
			true
		);

		// Pass PHP data the block editor JS needs.
		wp_localize_script(
			'trustbio-author-block-editor',
			'trustbioBlock',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'trustbio_search_authors' ),
				'categories'   => trustbio_get_categories( true ),
				'boxes'        => $this->get_box_options(),
				'sidebarBoxes' => $this->get_sidebar_box_options(),
			)
		);

		// Minimal editor-only style.
		wp_register_style(
			'trustbio-author-block-editor-style',
			TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-public.css',
			array(),
			TRUSTBIO_VERSION
		);
	}

	/**
	 * Build regular author box options for the block editor dropdown.
	 *
	 * @return array<int, array{value:int, label:string}>
	 */
	private function get_box_options(): array {
		$trustbio_box_class = new TrustBio_Author_Box();
		$trustbio_options   = array(
			array( 'value' => 0, 'label' => __( '— Default layout —', 'trustbio' ) ),
		);
		foreach ( $trustbio_box_class->get_all( 'default' ) as $trustbio_box ) {
			$trustbio_options[] = array(
				'value' => (int) $trustbio_box->id,
				'label' => $trustbio_box->title,
			);
		}
		return $trustbio_options;
	}

	/**
	 * Build sidebar box options for the block editor dropdown.
	 *
	 * @return array<int, array{value:int, label:string}>
	 */
	private function get_sidebar_box_options(): array {
		$trustbio_options = array(
			array( 'value' => 0, 'label' => __( '— None —', 'trustbio' ) ),
		);

		/**
		 * Filters the sidebar box choices offered in the block inspector.
		 *
		 * Sidebar Author Boxes are a TrustBio Pro feature. The free plugin
		 * returns only the "None" entry, and the block hides the control when
		 * nothing else is available.
		 *
		 * @since 1.0.0
		 * @param array<int,array{value:int,label:string}> $options Choices.
		 */
		return (array) apply_filters( 'trustbio_block_sidebar_box_options', $trustbio_options );
	}

	// =========================================================
	// Server-side render callback
	// =========================================================

	/**
	 * Render the block on the frontend (and in the editor preview).
	 *
	 * @param array $trustbio_attrs Block attributes.
	 * @return string HTML output.
	 */
	public function render( array $trustbio_attrs ): string {
		$trustbio_mode           = sanitize_key( $trustbio_attrs['mode']           ?? 'current_post' );
		$trustbio_author_id      = absint( $trustbio_attrs['author_id']            ?? 0 );
		$trustbio_box_id         = absint( $trustbio_attrs['box_id']               ?? 0 );
		$trustbio_sidebar_box_id = absint( $trustbio_attrs['sidebar_box_id']       ?? 0 );
		$trustbio_show_bio       = (bool) ( $trustbio_attrs['show_bio']            ?? true );
		$trustbio_show_social    = (bool) ( $trustbio_attrs['show_social']         ?? true );

		// Resolve which author to show.
		if ( 'current_post' === $trustbio_mode ) {
			$trustbio_post_id = get_the_ID();
			if ( ! $trustbio_post_id ) {
				// In the editor with no post context — show placeholder.
				return $this->render_placeholder(
					__( 'TrustBio Author Box', 'trustbio' ),
					__( 'This block will show the current post\'s TrustBio author. Preview it on a published post.', 'trustbio' )
				);
			}
			$trustbio_post_authors = trustbio_get_post_authors( $trustbio_post_id );
			if ( empty( $trustbio_post_authors ) ) {
				return $this->render_placeholder(
					__( 'TrustBio Author Box', 'trustbio' ),
					__( 'No TrustBio author is assigned to this post.', 'trustbio' )
				);
			}
			$trustbio_author = $trustbio_post_authors[0]['author'];
		} else {
			// Fixed author mode.
			if ( ! $trustbio_author_id ) {
				return $this->render_placeholder(
					__( 'TrustBio Author Box', 'trustbio' ),
					__( 'Select an author in the block settings panel.', 'trustbio' )
				);
			}
			$trustbio_author = trustbio_get_author( $trustbio_author_id );
			if ( ! $trustbio_author ) {
				return $this->render_placeholder(
					__( 'TrustBio Author Box', 'trustbio' ),
					__( 'Author not found.', 'trustbio' )
				);
			}
		}

		// Sidebar box mode — provided by the TrustBio Pro add-on.
		if ( $trustbio_sidebar_box_id ) {
			/**
			 * Filters the rendered HTML for a sidebar box inside the block.
			 *
			 * Sidebar Author Boxes are a TrustBio Pro feature. When the add-on
			 * is not present this stays '' and the block falls through to the
			 * standard author box below, so the block still renders.
			 *
			 * @since 1.0.0
			 * @param string $html   Rendered HTML, '' when unavailable.
			 * @param int    $box_id Sidebar box ID requested by the block.
			 * @param object $author Author record.
			 */
			$trustbio_sidebar_html = (string) apply_filters(
				'trustbio_block_render_sidebar_box',
				'',
				$trustbio_sidebar_box_id,
				$trustbio_author
			);

			if ( '' !== $trustbio_sidebar_html ) {
				return $trustbio_sidebar_html;
			}
		}

		// ── Standard author box mode ─────────────────────────────
		// Enqueue frontend CSS.
		wp_enqueue_style(
			'trustbio-public',
			TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-public.css',
			array(),
			TRUSTBIO_VERSION
		);

		// Temporarily suppress bio/social if toggled off.
		$trustbio_original_bio = $trustbio_author->bio;
		if ( ! $trustbio_show_bio ) {
			$trustbio_author->bio = '';
		}
		if ( ! $trustbio_show_social ) {
			add_filter( 'trustbio_show_social_in_widget', '__return_false' );
		}

		$trustbio_atts = array(
			'id'       => (string) $trustbio_author->id,
			'slug'     => '',
			'box'      => '',
			'category' => '',
		);

		if ( $trustbio_box_id ) {
			$trustbio_box_class = new TrustBio_Author_Box();
			$trustbio_box_obj   = $trustbio_box_class->get_box( $trustbio_box_id );
			if ( $trustbio_box_obj ) {
				$trustbio_atts['box'] = $trustbio_box_obj->title;
			}
		}

		$trustbio_public = new TrustBio_Public();
		$trustbio_html   = $trustbio_public->render_shortcode_author( $trustbio_author, $trustbio_atts );

		// Restore.
		$trustbio_author->bio = $trustbio_original_bio;
		if ( ! $trustbio_show_social ) {
			remove_filter( 'trustbio_show_social_in_widget', '__return_false' );
		}

		return $trustbio_html;
	}

	/**
	 * Render a placeholder shown in the editor when no author is selected.
	 *
	 * @param string $trustbio_title   Placeholder heading.
	 * @param string $trustbio_message Placeholder message.
	 * @return string HTML.
	 */
	private function render_placeholder( string $trustbio_title, string $trustbio_message ): string {
		return '<div style="border:2px dashed #c3c4c7;border-radius:4px;padding:20px 24px;text-align:center;background:#f9f9f9;">'
			. '<p style="font-weight:600;font-size:14px;margin:0 0 6px;color:#1d2327;">' . esc_html( $trustbio_title ) . '</p>'
			. '<p style="font-size:13px;color:#6b7280;margin:0;">' . esc_html( $trustbio_message ) . '</p>'
			. '</div>';
	}
}
