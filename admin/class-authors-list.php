<?php
/**
 * Authors management — registered + guest authors, full CRUD + AJAX.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Authors_List
 */
class TrustBio_Authors_List {

	private string $trustbio_table;

	public function __construct() {
		global $wpdb;
		$this->trustbio_table = $wpdb->prefix . 'trustbio_authors';
	}

	public function render(): void {
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/authors-list.php';
	}

	// =========================================================
	// AJAX: Save author
	// =========================================================
	public function ajax_save(): void {
		check_ajax_referer( 'trustbio_save_author', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_save_author' );

		global $wpdb;

		$trustbio_id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$trustbio_type = sanitize_key( wp_unslash( $_POST['author_type'] ?? 'registered' ) );

		if ( ! in_array( $trustbio_type, array( 'registered', 'guest' ), true ) ) {
			$trustbio_type = 'registered';
		}

		$trustbio_data = array(
			'author_type'  => $trustbio_type,
			'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			'email'        => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'website'      => esc_url_raw( wp_unslash( $_POST['website'] ?? '' ) ),
			'job_title'    => sanitize_text_field( wp_unslash( $_POST['job_title'] ?? '' ) ),
			'credentials'  => sanitize_text_field( wp_unslash( $_POST['credentials'] ?? '' ) ),
			'bio'          => wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) ),
			'category_id'  => absint( $_POST['category_id'] ?? 0 ) ?: null,
			'is_active'    => 1,
		);

		if ( empty( $trustbio_data['display_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Display name is required.', 'trustbio' ) ) );
		}

		if ( 'registered' === $trustbio_type ) {
			$trustbio_data['wp_user_id'] = absint( $_POST['wp_user_id'] ?? 0 ) ?: null;
		}

		$trustbio_avatar_id = absint( $_POST['avatar_attach_id'] ?? 0 );
		if ( $trustbio_avatar_id ) {
			$trustbio_data['avatar_attach_id'] = $trustbio_avatar_id;
		}

		if ( $trustbio_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $this->trustbio_table, $trustbio_data, array( 'id' => $trustbio_id ), null, array( '%d' ) );
			trustbio_clear_author_cache( $trustbio_id );
			$this->save_field_values( $trustbio_id );
			wp_send_json_success( array( 'message' => __( 'Author updated successfully.', 'trustbio' ), 'id' => $trustbio_id ) );
		}

		$trustbio_data['slug'] = trustbio_generate_author_slug( $trustbio_data['display_name'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->trustbio_table, $trustbio_data );
		$trustbio_new_id = $wpdb->insert_id;
		trustbio_clear_author_cache( (int) $trustbio_new_id );

		if ( ! $trustbio_new_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create author.', 'trustbio' ) ) );
		}

		$this->save_field_values( $trustbio_new_id );
		wp_send_json_success( array( 'message' => __( 'Author created successfully.', 'trustbio' ), 'id' => $trustbio_new_id ) );
	}

	// =========================================================
	// AJAX: Delete author
	// =========================================================
	public function ajax_delete(): void {
		check_ajax_referer( 'trustbio_delete_author', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_delete_author' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid author ID.', 'trustbio' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->trustbio_table, array( 'id' => $trustbio_id ), array( '%d' ) );
		trustbio_clear_author_cache( $trustbio_id );

		$trustbio_fvt = $wpdb->prefix . 'trustbio_author_field_values';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $trustbio_fvt, array( 'author_id' => $trustbio_id ), array( '%d' ) );

		wp_send_json_success( array( 'message' => __( 'Author deleted.', 'trustbio' ) ) );
	}

	// =========================================================
	// AJAX: Toggle active/inactive
	// =========================================================
	public function ajax_toggle(): void {
		check_ajax_referer( 'trustbio_toggle_author', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_toggle_author' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid author ID.', 'trustbio' ) ) );
		}

		$trustbio_t      = $this->trustbio_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_author = $wpdb->get_row( $wpdb->prepare( "SELECT id, is_active FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );

		if ( ! $trustbio_author ) {
			wp_send_json_error( array( 'message' => __( 'Author not found.', 'trustbio' ) ) );
		}

		$trustbio_new = $trustbio_author->is_active ? 0 : 1;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $this->trustbio_table, array( 'is_active' => $trustbio_new ), array( 'id' => $trustbio_id ), array( '%d' ), array( '%d' ) );
		trustbio_clear_author_cache( $trustbio_id );

		wp_send_json_success( array(
			'message'   => $trustbio_new ? __( 'Author activated.', 'trustbio' ) : __( 'Author deactivated.', 'trustbio' ),
			'new_state' => $trustbio_new,
		) );
	}

	// =========================================================
	// AJAX: Search authors (for post editor)
	// =========================================================
	public function ajax_search(): void {
		check_ajax_referer( 'trustbio_search_authors', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'trustbio' ) ), 403 );
		}

		global $wpdb;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// Support both GET (Gutenberg sidebar fetch) and POST (classic JS $.post).
		$trustbio_q = sanitize_text_field( wp_unslash( $_REQUEST['q'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$trustbio_like = '%' . $wpdb->esc_like( $trustbio_q ) . '%';
		$trustbio_t    = $this->trustbio_table;
		$trustbio_ct   = $wpdb->prefix . 'trustbio_author_categories';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$trustbio_rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT a.id, a.display_name, a.author_type, a.avatar_attach_id, a.avatar_url, a.email, a.category_id, c.singular AS category_name FROM %i a LEFT JOIN %i c ON c.id = a.category_id WHERE a.is_active = 1 AND a.display_name LIKE %s ORDER BY a.display_name ASC LIMIT 20',
			$trustbio_t,
			$trustbio_ct,
			$trustbio_like
		) );

		$trustbio_results = array();
		foreach ( (array) $trustbio_rows as $trustbio_row ) {
			$trustbio_results[] = array(
				'id'            => (int) $trustbio_row->id,
				'display_name'  => $trustbio_row->display_name,
				'author_type'   => $trustbio_row->author_type,
				'avatar_url'    => trustbio_get_avatar_url( $trustbio_row, 40 ),
				'category_id'   => $trustbio_row->category_id,
				'category_name' => $trustbio_row->category_name,
			);
		}

		wp_send_json_success( array( 'authors' => $trustbio_results ) );
	}

	// =========================================================
	// AJAX: Get single author detail (for Gutenberg sidebar)
	// Supports GET requests (sidebar uses fetch() with GET)
	// =========================================================

	public function ajax_get_author_detail(): void {
		// check_ajax_referer checks $_REQUEST so it covers both GET and POST callers.
		check_ajax_referer( 'trustbio_search_authors', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'trustbio' ) ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above via check_ajax_referer.
		$trustbio_id = absint( $_REQUEST['id'] ?? 0 );

		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid author ID.', 'trustbio' ) ) );
		}

		$trustbio_author = trustbio_get_author( $trustbio_id );
		if ( ! $trustbio_author ) {
			wp_send_json_error( array( 'message' => __( 'Author not found.', 'trustbio' ) ) );
		}

		wp_send_json_success( array(
			'id'           => (int) $trustbio_author->id,
			'display_name' => $trustbio_author->display_name,
			'avatar_url'   => trustbio_get_avatar_url( $trustbio_author, 56 ),
			'credentials'  => $trustbio_author->credentials ?? '',
		) );
	}

	// =========================================================
	// AJAX: Search WP users (for registered author type)
	// =========================================================
	public function ajax_search_wp_users(): void {
		check_ajax_referer( 'trustbio_search_wp_users', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_search_wp_users' );

		$trustbio_q     = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
		$trustbio_roles = get_option( 'trustbio_allowed_roles', array( 'administrator', 'editor', 'author', 'contributor' ) );

		$trustbio_users = get_users( array(
			'search'         => '*' . $trustbio_q . '*',
			'search_columns' => array( 'display_name', 'user_email', 'user_login' ),
			'role__in'       => (array) $trustbio_roles,
			'number'         => 20,
			'fields'         => array( 'ID', 'display_name', 'user_email' ),
		) );

		$trustbio_results = array_map( static function ( $trustbio_u ) {
			return array(
				'id'           => (int) $trustbio_u->ID,
				'display_name' => $trustbio_u->display_name,
				'email'        => $trustbio_u->user_email,
				'avatar_url'   => get_avatar_url( $trustbio_u->user_email, array( 'size' => 40 ) ),
			);
		}, $trustbio_users );

		wp_send_json_success( array( 'users' => $trustbio_results ) );
	}

	// =========================================================
	// Get paginated authors
	// =========================================================
	public function get_paginated( int $trustbio_per_page = 20, int $trustbio_page = 1, string $trustbio_type = 'all', string $trustbio_search = '' ): array {
		global $wpdb;

		$trustbio_t      = $this->trustbio_table;
		$trustbio_ct     = $wpdb->prefix . 'trustbio_author_categories';
		$trustbio_offset = ( $trustbio_page - 1 ) * $trustbio_per_page;

		$trustbio_has_type   = 'all' !== $trustbio_type;
		$trustbio_has_search = '' !== $trustbio_search;
		$trustbio_like       = $trustbio_has_search ? '%' . $wpdb->esc_like( $trustbio_search ) . '%' : '';

		if ( $trustbio_has_type && $trustbio_has_search ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_total = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM %i a WHERE a.author_type = %s AND a.display_name LIKE %s',
				$trustbio_t, $trustbio_type, $trustbio_like
			) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_items = $wpdb->get_results( $wpdb->prepare(
				'SELECT a.*, c.singular AS category_name FROM %i a LEFT JOIN %i c ON c.id = a.category_id WHERE a.author_type = %s AND a.display_name LIKE %s ORDER BY a.display_name ASC LIMIT %d OFFSET %d',
				$trustbio_t, $trustbio_ct, $trustbio_type, $trustbio_like, $trustbio_per_page, $trustbio_offset
			) );
		} elseif ( $trustbio_has_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_total = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM %i a WHERE a.author_type = %s',
				$trustbio_t, $trustbio_type
			) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_items = $wpdb->get_results( $wpdb->prepare(
				'SELECT a.*, c.singular AS category_name FROM %i a LEFT JOIN %i c ON c.id = a.category_id WHERE a.author_type = %s ORDER BY a.display_name ASC LIMIT %d OFFSET %d',
				$trustbio_t, $trustbio_ct, $trustbio_type, $trustbio_per_page, $trustbio_offset
			) );
		} elseif ( $trustbio_has_search ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_total = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM %i a WHERE a.display_name LIKE %s',
				$trustbio_t, $trustbio_like
			) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_items = $wpdb->get_results( $wpdb->prepare(
				'SELECT a.*, c.singular AS category_name FROM %i a LEFT JOIN %i c ON c.id = a.category_id WHERE a.display_name LIKE %s ORDER BY a.display_name ASC LIMIT %d OFFSET %d',
				$trustbio_t, $trustbio_ct, $trustbio_like, $trustbio_per_page, $trustbio_offset
			) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_total = (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM %i a',
				$trustbio_t
			) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_items = $wpdb->get_results( $wpdb->prepare(
				'SELECT a.*, c.singular AS category_name FROM %i a LEFT JOIN %i c ON c.id = a.category_id ORDER BY a.display_name ASC LIMIT %d OFFSET %d',
				$trustbio_t, $trustbio_ct, $trustbio_per_page, $trustbio_offset
			) );
		}

		$trustbio_items = $trustbio_items ?: array();

		// D2 — Attach completeness signals to each author object (2 batch queries).
		if ( ! empty( $trustbio_items ) ) {
			$trustbio_batch_ids = array_map( static function ( $trustbio_row ) {
				return (int) $trustbio_row->id;
			}, $trustbio_items );
			// One %d placeholder per id; the ids themselves are bound by
			// prepare() rather than pasted into the query.
			$trustbio_batch_slots = implode( ', ', array_fill( 0, count( $trustbio_batch_ids ), '%d' ) );
			$trustbio_creds_t   = $wpdb->prefix . 'trustbio_author_credentials';
			$trustbio_fvt       = $wpdb->prefix . 'trustbio_author_field_values';
			$trustbio_ft        = $wpdb->prefix . 'trustbio_author_fields';

			// Credential counts (any active badge per author).
			/*
			 * %i for the table name, so nothing is interpolated into the
			 * SQL but the placeholder list itself. phpcs still cannot see
			 * placeholders built at runtime -- the limitation behind core
			 * ticket 54042 -- so that one sniff is silenced with a reason.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$trustbio_cred_rows = $wpdb->get_results( $wpdb->prepare( "SELECT author_id, COUNT(*) AS cnt FROM %i WHERE author_id IN ({$trustbio_batch_slots}) AND show_badge = 1 GROUP BY author_id", array_merge( array( $trustbio_creds_t ), $trustbio_batch_ids ) ), ARRAY_A );
			$trustbio_cred_map = array();
			foreach ( (array) $trustbio_cred_rows as $trustbio_cr ) {
				$trustbio_cred_map[ (int) $trustbio_cr['author_id'] ] = (int) $trustbio_cr['cnt'];
			}

			// Social link presence (any link-type field with a non-empty value).
			/*
			 * Both table names through %i. ReplacementsWrongNumber is
			 * silenced too: the sniff counts the literal placeholders
			 * and then counts ONE argument, because the arguments are
			 * passed as a single array -- which is how prepare() takes a
			 * variable-length list.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$trustbio_social_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT fv.author_id FROM %i fv INNER JOIN %i f ON f.id = fv.field_id WHERE fv.author_id IN ({$trustbio_batch_slots}) AND f.field_type = 'link' AND fv.value != ''", array_merge( array( $trustbio_fvt, $trustbio_ft ), $trustbio_batch_ids ) ) );
			$trustbio_social_set = array_flip( array_map( 'intval', (array) $trustbio_social_ids ) );

			foreach ( $trustbio_items as $trustbio_item ) {
				$trustbio_item->cred_count = $trustbio_cred_map[ (int) $trustbio_item->id ] ?? 0;
				$trustbio_item->has_social = isset( $trustbio_social_set[ (int) $trustbio_item->id ] );
			}
		}

		return array( 'items' => $trustbio_items, 'total' => $trustbio_total );
	}

	// =========================================================
	// Private: save field values after author save
	// =========================================================
	private function save_field_values( int $trustbio_author_id ): void {
		global $wpdb;

		// Nonce was already verified by the calling ajax_save() method via check_ajax_referer().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['fields'] ) || ! is_array( $_POST['fields'] ) ) {
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			return;
		}

		$trustbio_fvt = $wpdb->prefix . 'trustbio_author_field_values';
		$trustbio_ft  = $wpdb->prefix . 'trustbio_author_fields';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_all_fields = $wpdb->get_results( "SELECT id, slug, field_type FROM `{$trustbio_ft}` WHERE is_active = 1" );
		$trustbio_field_map  = array();

		foreach ( (array) $trustbio_all_fields as $trustbio_f ) {
			$trustbio_field_map[ $trustbio_f->slug ] = $trustbio_f;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
		foreach ( wp_unslash( $_POST['fields'] ) as $trustbio_slug => $trustbio_value ) {
			$trustbio_slug = sanitize_key( $trustbio_slug );
			if ( ! isset( $trustbio_field_map[ $trustbio_slug ] ) ) {
				continue;
			}

			$trustbio_fobj = $trustbio_field_map[ $trustbio_slug ];
			$trustbio_val  = 'link' === $trustbio_fobj->field_type
				? esc_url_raw( $trustbio_value )
				: sanitize_text_field( $trustbio_value );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->replace(
				$trustbio_fvt,
				array( 'author_id' => $trustbio_author_id, 'field_id' => (int) $trustbio_fobj->id, 'value' => $trustbio_val ),
				array( '%d', '%d', '%s' )
			);
		}
	}
}
