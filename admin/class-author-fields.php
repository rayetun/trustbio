<?php
/**
 * Author Fields management — full CRUD + AJAX handlers.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Author_Fields
 */
class TrustBio_Author_Fields {

	/** @var string Table name. */
	private string $trustbio_table;

	public function __construct() {
		global $wpdb;
		$this->trustbio_table = $wpdb->prefix . 'trustbio_author_fields';
	}

	public function render(): void {
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/author-fields.php';
	}

	// =========================================================
	// AJAX: Save field
	// =========================================================
	public function ajax_save(): void {
		check_ajax_referer( 'trustbio_save_field', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_save_field' );

		global $wpdb;

		$trustbio_id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$trustbio_data = array(
			'label'      => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			'field_type' => sanitize_key( wp_unslash( $_POST['field_type'] ?? 'link' ) ),
			'is_social'  => isset( $_POST['is_social'] ) ? 1 : 0,
			'is_required'=> isset( $_POST['is_required'] ) ? 1 : 0,
			'is_active'  => isset( $_POST['is_active'] ) ? 1 : 0,
			'placeholder'=> sanitize_text_field( wp_unslash( $_POST['placeholder'] ?? '' ) ),
			'sort_order' => absint( $_POST['sort_order'] ?? 0 ),
		);

		if ( empty( $trustbio_data['label'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Label is required.', 'trustbio' ) ) );
		}

		$trustbio_data['field_type'] = in_array( $trustbio_data['field_type'], array( 'link', 'text', 'textarea' ), true )
			? $trustbio_data['field_type'] : 'link';

		if ( $trustbio_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $this->trustbio_table, $trustbio_data, array( 'id' => $trustbio_id ), null, array( '%d' ) );
			wp_send_json_success( array( 'message' => __( 'Field updated.', 'trustbio' ), 'id' => $trustbio_id ) );
		}

		$trustbio_data['slug']      = $this->generate_unique_slug( sanitize_title( $trustbio_data['label'] ) );
		$trustbio_data['is_builtin'] = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->trustbio_table, $trustbio_data );
		wp_send_json_success( array( 'message' => __( 'Field created.', 'trustbio' ), 'id' => $wpdb->insert_id, 'slug' => $trustbio_data['slug'] ) );
	}

	// =========================================================
	// AJAX: Delete field (user-added only)
	// =========================================================
	public function ajax_delete(): void {
		check_ajax_referer( 'trustbio_delete_field', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_delete_field' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field ID.', 'trustbio' ) ) );
		}

		$trustbio_t = $this->trustbio_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_field = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );

		if ( $trustbio_field && $trustbio_field->is_builtin ) {
			wp_send_json_error( array( 'message' => __( 'Built-in fields cannot be deleted. You can disable them instead.', 'trustbio' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->trustbio_table, array( 'id' => $trustbio_id ), array( '%d' ) );
		wp_send_json_success( array( 'message' => __( 'Field deleted.', 'trustbio' ) ) );
	}

	// =========================================================
	// AJAX: Toggle active/inactive
	// =========================================================
	public function ajax_toggle(): void {
		check_ajax_referer( 'trustbio_toggle_field', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_toggle_field' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field ID.', 'trustbio' ) ) );
		}

		$trustbio_t = $this->trustbio_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_field = $wpdb->get_row( $wpdb->prepare( "SELECT id, is_active FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );
		if ( ! $trustbio_field ) {
			wp_send_json_error( array( 'message' => __( 'Field not found.', 'trustbio' ) ) );
		}

		$trustbio_new = $trustbio_field->is_active ? 0 : 1;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $this->trustbio_table, array( 'is_active' => $trustbio_new ), array( 'id' => $trustbio_id ), array( '%d' ), array( '%d' ) );

		wp_send_json_success( array(
			'message'   => $trustbio_new ? __( 'Field activated.', 'trustbio' ) : __( 'Field deactivated.', 'trustbio' ),
			'new_state' => $trustbio_new,
		) );
	}

	// =========================================================
	// AJAX: Reorder fields via drag-and-drop
	// =========================================================
	public function ajax_reorder(): void {
		check_ajax_referer( 'trustbio_reorder_fields', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_reorder_fields' );

		global $wpdb;

		$trustbio_order = isset( $_POST['order'] ) && is_array( $_POST['order'] )
			? array_map( 'absint', wp_unslash( $_POST['order'] ) )
			: array();

		foreach ( $trustbio_order as $trustbio_position => $trustbio_field_id ) {
			if ( ! $trustbio_field_id ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $this->trustbio_table, array( 'sort_order' => $trustbio_position ), array( 'id' => $trustbio_field_id ), array( '%d' ), array( '%d' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Order saved.', 'trustbio' ) ) );
	}

	// =========================================================
	// Private helpers
	// =========================================================
	private function generate_unique_slug( string $trustbio_base ): string {
		global $wpdb;
		$trustbio_t       = $this->trustbio_table;
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
	 * Get paginated fields for the list table.
	 *
	 * @param int    $trustbio_per_page Items per page.
	 * @param int    $trustbio_page     Current page.
	 * @param string $trustbio_filter   Status filter: 'all','active','disabled','social','text'.
	 * @return array{ items: array, total: int }
	 */
	public function get_paginated( int $trustbio_per_page = 30, int $trustbio_page = 1, string $trustbio_filter = 'all' ): array {
		global $wpdb;
		$trustbio_t      = $this->trustbio_table;
		$trustbio_offset = ( $trustbio_page - 1 ) * $trustbio_per_page;
		/*
		 * The filter decides which COLUMN is compared and to what. Both are
		 * now placeholders bound by prepare() rather than a WHERE fragment
		 * pasted into the query: the column is chosen from this fixed map,
		 * never from the request, and the value goes through %d.
		 *
		 * The old version concatenated literal strings and so was never
		 * injectable either — but a query assembled by concatenation has to
		 * be read carefully to know that, and the next person to add a case
		 * to the switch would have had nothing stopping them interpolating a
		 * variable into it.
		 */
		$trustbio_filters = array(
			'active'   => array( 'is_active', 1 ),
			'disabled' => array( 'is_active', 0 ),
			'social'   => array( 'is_social', 1 ),
			'text'     => array( 'is_social', 0 ),
		);

		if ( isset( $trustbio_filters[ $trustbio_filter ] ) ) {
			list( $trustbio_col, $trustbio_val ) = $trustbio_filters[ $trustbio_filter ];

			// %i is the identifier placeholder, added in WordPress 6.2 —
			// which is this plugin's minimum.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_total = (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE %i = %d', $trustbio_t, $trustbio_col, $trustbio_val )
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE %i = %d ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d',
					$trustbio_t,
					$trustbio_col,
					$trustbio_val,
					$trustbio_per_page,
					$trustbio_offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $trustbio_t ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$trustbio_items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY sort_order ASC, id ASC LIMIT %d OFFSET %d',
					$trustbio_t,
					$trustbio_per_page,
					$trustbio_offset
				)
			);
		}

		return array( 'items' => $trustbio_items ?: array(), 'total' => $trustbio_total );
	}
}
