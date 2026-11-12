<?php
/**
 * Post Sidebar — Classic Editor meta box + save + Gutenberg REST field registration.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Post_Sidebar
 */
class TrustBio_Post_Sidebar {

	// =========================================================
	// Classic Editor Meta Box
	// =========================================================

	/**
	 * Register the meta box on all enabled post types.
	 */
	public function register_classic_metabox(): void {
		$trustbio_post_types = get_option( 'trustbio_enabled_post_types', array( 'post', 'page' ) );
		foreach ( (array) $trustbio_post_types as $trustbio_pt ) {
			add_meta_box(
				'trustbio-authors',
				__( 'TrustBio Authors', 'trustbio' ),
				array( $this, 'render_classic_metabox' ),
				$trustbio_pt,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the Classic Editor meta box content.
	 *
	 * @param WP_Post $trustbio_post The post being edited.
	 */
	public function render_classic_metabox( WP_Post $trustbio_post ): void {
		wp_nonce_field( 'trustbio_save_post_' . $trustbio_post->ID, 'trustbio_post_nonce' );
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/post-sidebar/classic-metabox.php';
	}

	// =========================================================
	// Save post meta
	// =========================================================

	/**
	 * Save author assignments and box override from the Classic Editor meta box.
	 *
	 * @param int     $trustbio_post_id Post ID.
	 * @param WP_Post $trustbio_post    Post object.
	 */
	public function save_post_meta( int $trustbio_post_id, WP_Post $trustbio_post ): void {
		if ( ! isset( $_POST['trustbio_post_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['trustbio_post_nonce'] ) ),
			'trustbio_save_post_' . $trustbio_post_id
		) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $trustbio_post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Hide author box.
		$trustbio_hide = isset( $_POST['trustbio_hide_box'] ) ? 1 : 0;
		update_post_meta( $trustbio_post_id, '_trustbio_hide_box', $trustbio_hide );

		// Box layout override.
		$trustbio_box_id = absint( $_POST['trustbio_box_id'] ?? 0 );
		if ( $trustbio_box_id ) {
			update_post_meta( $trustbio_post_id, '_trustbio_box_id', $trustbio_box_id );
		} else {
			delete_post_meta( $trustbio_post_id, '_trustbio_box_id' );
		}

		// Sidebar box override.
		$trustbio_sidebar_box_id = absint( $_POST['trustbio_sidebar_box_id'] ?? 0 );
		if ( $trustbio_sidebar_box_id ) {
			update_post_meta( $trustbio_post_id, '_trustbio_sidebar_box_id', $trustbio_sidebar_box_id );
		} else {
			delete_post_meta( $trustbio_post_id, '_trustbio_sidebar_box_id' );
		}

		// Review bar template override.
		$trustbio_review_bar_id = absint( $_POST['trustbio_review_bar_id'] ?? 0 );
		if ( $trustbio_review_bar_id ) {
			update_post_meta( $trustbio_post_id, '_trustbio_review_bar_id', $trustbio_review_bar_id );
		} else {
			delete_post_meta( $trustbio_post_id, '_trustbio_review_bar_id' );
		}

		// Author assignments from the JSON hidden field.
		$trustbio_authors_json = sanitize_text_field( wp_unslash( $_POST['trustbio_authors_json'] ?? '' ) );
		if ( $trustbio_authors_json ) {
			$trustbio_assignments = json_decode( $trustbio_authors_json, true );
			if ( is_array( $trustbio_assignments ) ) {
				$trustbio_sanitized = array();
				foreach ( $trustbio_assignments as $trustbio_entry ) {
					$trustbio_sanitized[] = array(
						'author_id'   => absint( $trustbio_entry['author_id'] ?? 0 ),
						'category_id' => absint( $trustbio_entry['category_id'] ?? 0 ) ?: null,
						'sort_order'  => absint( $trustbio_entry['sort_order'] ?? 0 ),
					);
				}
				update_post_meta( $trustbio_post_id, '_trustbio_authors', $trustbio_sanitized );
			}
		}
	}

	// =========================================================
	// Gutenberg REST API fields
	// =========================================================

	/**
	 * Register REST API fields so Gutenberg can read/write author meta.
	 */
	public function register_rest_fields(): void {
		$trustbio_post_types = get_option( 'trustbio_enabled_post_types', array( 'post', 'page' ) );

		foreach ( (array) $trustbio_post_types as $trustbio_pt ) {
			register_rest_field(
				$trustbio_pt,
				'trustbio_authors',
				array(
					'get_callback'    => array( $this, 'rest_get_authors' ),
					'update_callback' => array( $this, 'rest_update_authors' ),
					'schema'          => array(
						'type'        => 'array',
						'description' => __( 'TrustBio author assignments.', 'trustbio' ),
						'context'     => array( 'edit' ),
					),
				)
			);

			register_rest_field(
				$trustbio_pt,
				'trustbio_hide_box',
				array(
					'get_callback'    => array( $this, 'rest_get_hide_box' ),
					'update_callback' => array( $this, 'rest_update_hide_box' ),
					'schema'          => array(
						'type'        => 'integer',
						'description' => __( 'Hide TrustBio author box on this post.', 'trustbio' ),
						'context'     => array( 'edit' ),
					),
				)
			);

			register_rest_field(
				$trustbio_pt,
				'trustbio_box_id',
				array(
					'get_callback'    => array( $this, 'rest_get_box_id' ),
					'update_callback' => array( $this, 'rest_update_box_id' ),
					'schema'          => array(
						'type'        => 'integer',
						'description' => __( 'TrustBio author box layout override.', 'trustbio' ),
						'context'     => array( 'edit' ),
					),
				)
			);

			register_rest_field(
				$trustbio_pt,
				'trustbio_sidebar_box_id',
				array(
					'get_callback'    => array( $this, 'rest_get_sidebar_box_id' ),
					'update_callback' => array( $this, 'rest_update_sidebar_box_id' ),
					'schema'          => array(
						'type'        => 'integer',
						'description' => __( 'TrustBio sidebar box layout override.', 'trustbio' ),
						'context'     => array( 'edit' ),
					),
				)
			);

			register_rest_field(
				$trustbio_pt,
				'trustbio_review_bar_id',
				array(
					'get_callback'    => array( $this, 'rest_get_review_bar_id' ),
					'update_callback' => array( $this, 'rest_update_review_bar_id' ),
					'schema'          => array(
						'type'        => 'integer',
						'description' => __( 'TrustBio review bar template override.', 'trustbio' ),
						'context'     => array( 'edit' ),
					),
				)
			);
		}
	}

	// ─── REST get callbacks ───────────────────────────────────

	/**
	 * @param array $trustbio_post REST post data.
	 * @return array
	 */
	public function rest_get_authors( array $trustbio_post ): array {
		$trustbio_val = get_post_meta( $trustbio_post['id'], '_trustbio_authors', true );
		return is_array( $trustbio_val ) ? $trustbio_val : array();
	}

	/**
	 * @param array $trustbio_post REST post data.
	 * @return int
	 */
	public function rest_get_hide_box( array $trustbio_post ): int {
		return (int) get_post_meta( $trustbio_post['id'], '_trustbio_hide_box', true );
	}

	/**
	 * @param array $trustbio_post REST post data.
	 * @return int
	 */
	public function rest_get_box_id( array $trustbio_post ): int {
		return (int) get_post_meta( $trustbio_post['id'], '_trustbio_box_id', true );
	}

	/**
	 * @param array $trustbio_post REST post data.
	 * @return int
	 */
	public function rest_get_sidebar_box_id( array $trustbio_post ): int {
		return (int) get_post_meta( $trustbio_post['id'], '_trustbio_sidebar_box_id', true );
	}

	/**
	 * @param array $trustbio_post REST post data.
	 * @return int
	 */
	public function rest_get_review_bar_id( array $trustbio_post ): int {
		return (int) get_post_meta( $trustbio_post['id'], '_trustbio_review_bar_id', true );
	}

	// ─── REST update callbacks ────────────────────────────────

	/**
	 * @param mixed   $trustbio_value New value from REST request.
	 * @param WP_Post $trustbio_post  Post object.
	 */
	public function rest_update_authors( $trustbio_value, WP_Post $trustbio_post ): void {
		if ( ! is_array( $trustbio_value ) ) {
			return;
		}
		$trustbio_sanitized = array();
		foreach ( $trustbio_value as $trustbio_entry ) {
			$trustbio_sanitized[] = array(
				'author_id'   => absint( $trustbio_entry['author_id'] ?? 0 ),
				'category_id' => absint( $trustbio_entry['category_id'] ?? 0 ) ?: null,
				'sort_order'  => absint( $trustbio_entry['sort_order'] ?? 0 ),
			);
		}
		update_post_meta( $trustbio_post->ID, '_trustbio_authors', $trustbio_sanitized );
	}

	/**
	 * @param mixed   $trustbio_value New value.
	 * @param WP_Post $trustbio_post  Post object.
	 */
	public function rest_update_hide_box( $trustbio_value, WP_Post $trustbio_post ): void {
		update_post_meta( $trustbio_post->ID, '_trustbio_hide_box', absint( $trustbio_value ) );
	}

	/**
	 * @param mixed   $trustbio_value New value.
	 * @param WP_Post $trustbio_post  Post object.
	 */
	public function rest_update_box_id( $trustbio_value, WP_Post $trustbio_post ): void {
		$trustbio_box_id = absint( $trustbio_value );
		if ( $trustbio_box_id ) {
			update_post_meta( $trustbio_post->ID, '_trustbio_box_id', $trustbio_box_id );
		} else {
			delete_post_meta( $trustbio_post->ID, '_trustbio_box_id' );
		}
	}

	/**
	 * @param mixed   $trustbio_value New value.
	 * @param WP_Post $trustbio_post  Post object.
	 */
	public function rest_update_sidebar_box_id( $trustbio_value, WP_Post $trustbio_post ): void {
		$trustbio_id = absint( $trustbio_value );
		if ( $trustbio_id ) {
			update_post_meta( $trustbio_post->ID, '_trustbio_sidebar_box_id', $trustbio_id );
		} else {
			delete_post_meta( $trustbio_post->ID, '_trustbio_sidebar_box_id' );
		}
	}

	/**
	 * @param mixed   $trustbio_value New value.
	 * @param WP_Post $trustbio_post  Post object.
	 */
	public function rest_update_review_bar_id( $trustbio_value, WP_Post $trustbio_post ): void {
		$trustbio_id = absint( $trustbio_value );
		if ( $trustbio_id ) {
			update_post_meta( $trustbio_post->ID, '_trustbio_review_bar_id', $trustbio_id );
		} else {
			delete_post_meta( $trustbio_post->ID, '_trustbio_review_bar_id' );
		}
	}

	// =========================================================
	// Gutenberg block editor assets
	// =========================================================

	/**
	 * Enqueue the Gutenberg sidebar panel script.
	 * Passes all data the React panel needs via wp_localize_script.
	 */
	public function enqueue_block_assets(): void {
		global $post;

		if ( ! $post ) {
			return;
		}

		if ( ! trustbio_is_enabled_for_post_type( $post->post_type ) ) {
			return;
		}

		// Enqueue existing admin CSS so the sidebar looks on-brand.
		wp_enqueue_style(
			'trustbio-admin',
			TRUSTBIO_PLUGIN_URL . 'admin/css/trustbio-admin.css',
			array(),
			TRUSTBIO_VERSION
		);

		// Enqueue sidebar-specific CSS (moved from JS style injection for WP.org compliance).
		wp_enqueue_style(
			'trustbio-sidebar',
			TRUSTBIO_PLUGIN_URL . 'admin/css/trustbio-sidebar.css',
			array( 'trustbio-admin' ),
			TRUSTBIO_VERSION
		);

		// Enqueue the sidebar JS (vanilla JS + WP components, no jQuery).
		wp_enqueue_script(
			'trustbio-gutenberg-sidebar',
			TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-sidebar.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' ),
			TRUSTBIO_VERSION,
			true
		);

		// Load current assignments for this post.
		$trustbio_assigned = get_post_meta( $post->ID, '_trustbio_authors', true );
		$trustbio_box_id   = (int) get_post_meta( $post->ID, '_trustbio_box_id', true );
		$trustbio_hide     = (int) get_post_meta( $post->ID, '_trustbio_hide_box', true );

		// Pre-load assigned author details so names/avatars show immediately.
		$trustbio_assigned_details = array();
		if ( is_array( $trustbio_assigned ) ) {
			foreach ( $trustbio_assigned as $trustbio_entry ) {
				$trustbio_aid    = absint( $trustbio_entry['author_id'] ?? 0 );
				$trustbio_author = $trustbio_aid ? trustbio_get_author( $trustbio_aid ) : null;
				if ( $trustbio_author ) {
					$trustbio_assigned_details[] = array(
						'id'           => $trustbio_author->id,
						'display_name' => $trustbio_author->display_name,
						'avatar_url'   => trustbio_get_avatar_url( $trustbio_author, 56 ),
						'credentials'  => $trustbio_author->credentials ?? '',
					);
				}
			}
		}

		// Get regular author boxes (default type only).
		$trustbio_box_class  = new TrustBio_Author_Box();
		$trustbio_boxes      = array_map(
			static function ( $trustbio_b ) {
				return array( 'id' => (int) $trustbio_b->id, 'title' => $trustbio_b->title );
			},
			$trustbio_box_class->get_all( 'default' )
		);

		/**
		 * Filters the sidebar author boxes offered in the editor panel.
		 *
		 * Sidebar Author Boxes are a TrustBio Pro feature, so the free plugin
		 * offers none and the panel hides the control when the list is empty.
		 *
		 * @since 1.0.0
		 * @param array<int,array> $boxes Each entry is array( 'id' => int, 'title' => string ).
		 */
		$trustbio_sidebar_boxes = (array) apply_filters( 'trustbio_editor_sidebar_boxes', array() );

		// Get review bars.
		$trustbio_review_bar_class = new TrustBio_Review_Bar();
		$trustbio_review_bars      = array_map(
			static function ( $trustbio_b ) {
				return array( 'id' => (int) $trustbio_b->id, 'title' => $trustbio_b->title );
			},
			$trustbio_review_bar_class->get_all()
		);

		$trustbio_sidebar_box_id = (int) get_post_meta( $post->ID, '_trustbio_sidebar_box_id', true );
		$trustbio_review_bar_id  = (int) get_post_meta( $post->ID, '_trustbio_review_bar_id', true );

		wp_localize_script(
			'trustbio-gutenberg-sidebar',
			'trustbioSidebar',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'postId'          => $post->ID,
				'postType'        => $post->post_type,
				'restBase'        => get_post_type_object( $post->post_type ) ? get_post_type_object( $post->post_type )->rest_base : $post->post_type . 's',
				'nonce'           => wp_create_nonce( 'trustbio_search_authors' ),
				'saveNonce'       => wp_create_nonce( 'trustbio_save_post_authors' ),
				'categories'      => trustbio_get_categories( true ),
				'boxes'           => $trustbio_boxes,
				'sidebarBoxes'    => $trustbio_sidebar_boxes,
				'reviewBars'      => $trustbio_review_bars,
				'assigned'        => is_array( $trustbio_assigned ) ? $trustbio_assigned : array(),
				'assignedDetails' => $trustbio_assigned_details,
				'boxId'           => $trustbio_box_id,
				'sidebarBoxId'    => $trustbio_sidebar_box_id,
				'reviewBarId'     => $trustbio_review_bar_id,
				'hideBox'         => $trustbio_hide,
				'i18n'            => array(
					'searchPlaceholder' => __( 'Search authors…', 'trustbio' ),
					'noAuthors'         => __( 'No authors found.', 'trustbio' ),
					'writtenBy'         => __( 'Written By', 'trustbio' ),
					'hideBox'           => __( 'Hide author box on this post', 'trustbio' ),
					'boxLayout'         => __( 'Author Box Layout', 'trustbio' ),
					'defaultLayout'     => __( '— Default Layout —', 'trustbio' ),
					'dragToReorder'     => __( 'Drag to reorder', 'trustbio' ),
					'remove'            => __( 'Remove', 'trustbio' ),
					'panel'             => __( 'TrustBio Authors', 'trustbio' ),
					'saved'             => __( 'Saved.', 'trustbio' ),
				),
			)
		);
	}

	// =========================================================
	// AJAX: Save post authors from Gutenberg (fallback)
	// =========================================================

	public function ajax_save_post_authors(): void {
		check_ajax_referer( 'trustbio_save_post_authors', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'trustbio' ) ), 403 );
		}

		$trustbio_post_id   = absint( $_POST['post_id'] ?? 0 );
		$trustbio_hide      = isset( $_POST['hide_box'] ) ? absint( $_POST['hide_box'] ) : 0;
		$trustbio_box_id    = absint( $_POST['box_id'] ?? 0 );
		$trustbio_sb_box_id = absint( $_POST['sidebar_box_id'] ?? 0 );
		$trustbio_authors   = isset( $_POST['authors'] ) && is_array( $_POST['authors'] )
			? wp_unslash( $_POST['authors'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		if ( ! $trustbio_post_id || ! current_user_can( 'edit_post', $trustbio_post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'trustbio' ) ) );
		}

		update_post_meta( $trustbio_post_id, '_trustbio_hide_box', $trustbio_hide );

		if ( $trustbio_box_id ) {
			update_post_meta( $trustbio_post_id, '_trustbio_box_id', $trustbio_box_id );
		} else {
			delete_post_meta( $trustbio_post_id, '_trustbio_box_id' );
		}

		if ( $trustbio_sb_box_id ) {
			update_post_meta( $trustbio_post_id, '_trustbio_sidebar_box_id', $trustbio_sb_box_id );
		} else {
			delete_post_meta( $trustbio_post_id, '_trustbio_sidebar_box_id' );
		}

		$trustbio_sanitized = array();
		foreach ( $trustbio_authors as $trustbio_entry ) {
			$trustbio_sanitized[] = array(
				'author_id'   => absint( $trustbio_entry['author_id'] ?? 0 ),
				'category_id' => absint( $trustbio_entry['category_id'] ?? 0 ) ?: null,
				'sort_order'  => absint( $trustbio_entry['sort_order'] ?? 0 ),
			);
		}

		update_post_meta( $trustbio_post_id, '_trustbio_authors', $trustbio_sanitized );

		wp_send_json_success( array( 'message' => __( 'Authors saved.', 'trustbio' ) ) );
	}
}
