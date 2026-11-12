<?php
/**
 * Author Category management — full CRUD + AJAX handlers.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Author_Category
 */
class TrustBio_Author_Category {

	/**
	 * Render the Author Categories admin page.
	 */
	public function render(): void {
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/author-category.php';
	}

	// =========================================================
	// AJAX: Save (insert or update)
	// =========================================================

	/**
	 * AJAX handler: save an author category.
	 */
	public function ajax_save(): void {
		// check_ajax_referer() is called inside trustbio_verify_ajax_request().
		// PHPCS can now statically see the nonce check happens before any $_POST read.
		check_ajax_referer( 'trustbio_save_category', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_save_category' );

		global $wpdb;

		$trustbio_id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$trustbio_data = array(
			'singular'      => sanitize_text_field( wp_unslash( $_POST['singular'] ?? '' ) ),
			'plural'        => sanitize_text_field( wp_unslash( $_POST['plural'] ?? '' ) ),
			'display_label'      => sanitize_text_field( wp_unslash( $_POST['display_label'] ?? '' ) ),
			'schema_prop'        => sanitize_key( wp_unslash( $_POST['schema_prop'] ?? 'author' ) ),
			'contribution_verb'  => sanitize_text_field( wp_unslash( $_POST['contribution_verb'] ?? 'Written' ) ),
			'contribution_noun'  => sanitize_text_field( wp_unslash( $_POST['contribution_noun'] ?? 'article' ) ),
			'is_enabled'    => isset( $_POST['is_enabled'] ) ? 1 : 0,
			'sort_order'    => absint( $_POST['sort_order'] ?? 0 ),
		);

		if ( empty( $trustbio_data['singular'] ) || empty( $trustbio_data['plural'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Singular and Plural names are required.', 'trustbio' ) ) );
		}

		$trustbio_post_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) )
			: null;
		$trustbio_data['post_types'] = $trustbio_post_types ? wp_json_encode( $trustbio_post_types ) : null;

		if ( $trustbio_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_result = $wpdb->update(
				$wpdb->prefix . 'trustbio_author_categories',
				$trustbio_data,
				array( 'id' => $trustbio_id ),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
				array( '%d' )
			);

			if ( false === $trustbio_result ) {
				wp_send_json_error( array( 'message' => __( 'Failed to update category.', 'trustbio' ) ) );
			}

			wp_send_json_success( array( 'message' => __( 'Category updated successfully.', 'trustbio' ), 'id' => $trustbio_id ) );
		}

		$trustbio_data['slug'] = $this->generate_unique_slug( sanitize_title( $trustbio_data['singular'] ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$trustbio_inserted = $wpdb->insert( $wpdb->prefix . 'trustbio_author_categories', $trustbio_data );

		if ( ! $trustbio_inserted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create category.', 'trustbio' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Category created successfully.', 'trustbio' ), 'id' => $wpdb->insert_id, 'slug' => $trustbio_data['slug'] ) );
	}

	// =========================================================
	// AJAX: Delete
	// =========================================================

	/**
	 * AJAX handler: delete an author category.
	 */
	public function ajax_delete(): void {
		check_ajax_referer( 'trustbio_delete_category', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_delete_category' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid category ID.', 'trustbio' ) ) );
		}

		$trustbio_category = trustbio_get_category( $trustbio_id );
		if ( $trustbio_category && 'author' === $trustbio_category->slug ) {
			wp_send_json_error( array( 'message' => __( 'The default "Author" category cannot be deleted.', 'trustbio' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'trustbio_author_categories', array( 'id' => $trustbio_id ), array( '%d' ) );

		wp_send_json_success( array( 'message' => __( 'Category deleted.', 'trustbio' ) ) );
	}

	// =========================================================
	// AJAX: Toggle enabled/disabled
	// =========================================================

	/**
	 * AJAX handler: toggle a category's is_enabled status.
	 */
	public function ajax_toggle(): void {
		check_ajax_referer( 'trustbio_toggle_category', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_toggle_category' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid category ID.', 'trustbio' ) ) );
		}

		$trustbio_category = trustbio_get_category( $trustbio_id );
		if ( ! $trustbio_category ) {
			wp_send_json_error( array( 'message' => __( 'Category not found.', 'trustbio' ) ) );
		}

		$trustbio_new_state = $trustbio_category->is_enabled ? 0 : 1;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'trustbio_author_categories',
			array( 'is_enabled' => $trustbio_new_state ),
			array( 'id' => $trustbio_id ),
			array( '%d' ),
			array( '%d' )
		);

		wp_send_json_success( array(
			'message'   => $trustbio_new_state ? __( 'Category enabled.', 'trustbio' ) : __( 'Category disabled.', 'trustbio' ),
			'new_state' => $trustbio_new_state,
		) );
	}

	// =========================================================
	// Private helpers
	// =========================================================

	/**
	 * Generate a unique slug for a category.
	 *
	 * @param string $trustbio_base Base slug.
	 * @return string
	 */
	private function generate_unique_slug( string $trustbio_base ): string {
		global $wpdb;
		$trustbio_t       = $wpdb->prefix . 'trustbio_author_categories';
		$trustbio_slug    = $trustbio_base;
		$trustbio_counter = 1;

		while ( true ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$trustbio_t}` WHERE slug = %s", $trustbio_slug ) );
			if ( ! $trustbio_exists ) {
				break;
			}
			$trustbio_slug = $trustbio_base . '-' . $trustbio_counter;
			++$trustbio_counter;
		}

		return $trustbio_slug;
	}

	/**
	 * Get paginated categories for the list table.
	 *
	 * @param int    $trustbio_per_page Items per page.
	 * @param int    $trustbio_page     Current page number.
	 * @param string $trustbio_search   Optional search string.
	 * @return array{ items: array, total: int }
	 */
	public function get_paginated( int $trustbio_per_page = 20, int $trustbio_page = 1, string $trustbio_search = '' ): array {
		global $wpdb;
		$trustbio_t      = $wpdb->prefix . 'trustbio_author_categories';
		$trustbio_offset = ( $trustbio_page - 1 ) * $trustbio_per_page;

		if ( $trustbio_search ) {
			$trustbio_like = '%' . $wpdb->esc_like( $trustbio_search ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$trustbio_t}` WHERE singular LIKE %s OR plural LIKE %s", $trustbio_like, $trustbio_like ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE singular LIKE %s OR plural LIKE %s ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d", $trustbio_like, $trustbio_like, $trustbio_per_page, $trustbio_offset ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$trustbio_t}`" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d", $trustbio_per_page, $trustbio_offset ) );
		}

		return array( 'items' => $trustbio_items ?: array(), 'total' => $trustbio_total );
	}
}
