<?php
/**
 * Author Profile edit screen — full CRUD + AJAX handlers.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Author_Profile
 */
class TrustBio_Author_Profile {

	/** @var string Authors table name. */
	private string $trustbio_table;

	/** @var string Field values table name. */
	private string $trustbio_fv_table;

	public function __construct() {
		global $wpdb;
		$this->trustbio_table    = $wpdb->prefix . 'trustbio_authors';
		$this->trustbio_fv_table = $wpdb->prefix . 'trustbio_author_field_values';
	}

	// =========================================================
	// Render
	// =========================================================

	public function render(): void {
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/author-profile.php';
	}

	// =========================================================
	// AJAX: Save full profile (General + Social + SEO tabs)
	// =========================================================

	public function ajax_save(): void {
		check_ajax_referer( 'trustbio_save_profile', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_save_profile' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );

		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid author ID.', 'trustbio' ) ) );
		}

		// Verify the author exists.
		$trustbio_t = $this->trustbio_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, author_type FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );

		if ( ! $trustbio_existing ) {
			wp_send_json_error( array( 'message' => __( 'Author not found.', 'trustbio' ) ) );
		}

		// Build update data — General tab.
		$trustbio_data = array(
			'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			'email'        => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'website'      => esc_url_raw( wp_unslash( $_POST['website'] ?? '' ) ),
			'job_title'    => sanitize_text_field( wp_unslash( $_POST['job_title'] ?? '' ) ),
			'credentials'  => sanitize_text_field( wp_unslash( $_POST['credentials'] ?? '' ) ),
			'bio'          => wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) ),
			'category_id'  => absint( $_POST['category_id'] ?? 0 ) ?: null,
		);

		if ( empty( $trustbio_data['display_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Display name is required.', 'trustbio' ) ) );
		}

		// Avatar — from Avatar tab.
		$trustbio_avatar_attach_id = absint( $_POST['avatar_attach_id'] ?? 0 );
		$trustbio_avatar_url       = esc_url_raw( wp_unslash( $_POST['avatar_url'] ?? '' ) );

		$trustbio_data['avatar_attach_id'] = $trustbio_avatar_attach_id ?: null;
		$trustbio_data['avatar_url']       = $trustbio_avatar_url ?: null;

		// Registered author — allow updating linked WP user.
		if ( 'registered' === $trustbio_existing->author_type ) {
			$trustbio_wp_user_id = absint( $_POST['wp_user_id'] ?? 0 );
			if ( $trustbio_wp_user_id ) {
				$trustbio_data['wp_user_id'] = $trustbio_wp_user_id;
			}
		}

		// Update the author row.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->trustbio_table,
			$trustbio_data,
			array( 'id' => $trustbio_id ),
			null,
			array( '%d' )
		);

		// Save social field values — Social tab.
		$this->save_field_values( $trustbio_id );

		// Save SEO meta — stored as author meta in options (simple key-value).
		$trustbio_same_as = sanitize_textarea_field( wp_unslash( $_POST['same_as_urls'] ?? '' ) );
		update_option( 'trustbio_author_sameas_' . $trustbio_id, $trustbio_same_as );

		$trustbio_schema_job = sanitize_text_field( wp_unslash( $_POST['schema_job_title'] ?? '' ) );
		update_option( 'trustbio_author_schema_job_' . $trustbio_id, $trustbio_schema_job );

		// Also regenerate slug if display name changed.
		$trustbio_new_slug = trustbio_generate_author_slug( $trustbio_data['display_name'], $trustbio_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->trustbio_table,
			array( 'slug' => $trustbio_new_slug ),
			array( 'id' => $trustbio_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Both updates above are done; forget the cached row so the edit is
		// visible immediately on sites with a persistent object cache.
		trustbio_clear_author_cache( $trustbio_id );

		wp_send_json_success( array(
			'message' => __( 'Author profile saved successfully.', 'trustbio' ),
			'id'      => $trustbio_id,
			'slug'    => $trustbio_new_slug,
		) );
	}

	// =========================================================
	// AJAX: Handle avatar upload via WP media
	// =========================================================

	public function ajax_upload_avatar(): void {
		check_ajax_referer( 'trustbio_upload_avatar', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_upload_avatar' );

		if ( empty( $_FILES['avatar'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'trustbio' ) ) );
		}

		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$trustbio_attachment_id = media_handle_upload( 'avatar', 0 );

		if ( is_wp_error( $trustbio_attachment_id ) ) {
			wp_send_json_error( array( 'message' => $trustbio_attachment_id->get_error_message() ) );
		}

		$trustbio_url = wp_get_attachment_image_url( $trustbio_attachment_id, 'thumbnail' );

		wp_send_json_success( array(
			'attachment_id' => $trustbio_attachment_id,
			'url'           => $trustbio_url,
		) );
	}

	// =========================================================
	// Public: get author data + field values for the edit form
	// =========================================================

	/**
	 * Get all data needed to populate the edit form.
	 *
	 * @param int $trustbio_id Author ID.
	 * @return array{ author: object|null, field_values: array, same_as: string, schema_job: string }
	 */
	public function get_edit_data( int $trustbio_id ): array {
		$trustbio_author = trustbio_get_author( $trustbio_id );

		if ( ! $trustbio_author ) {
			return array(
				'author'       => null,
				'field_values' => array(),
				'same_as'      => '',
				'schema_job'   => '',
			);
		}

		return array(
			'author'       => $trustbio_author,
			'field_values' => trustbio_get_author_field_values( $trustbio_id ),
			'same_as'      => (string) get_option( 'trustbio_author_sameas_' . $trustbio_id, '' ),
			'schema_job'   => (string) get_option( 'trustbio_author_schema_job_' . $trustbio_id, '' ),
		);
	}

	// =========================================================
	// Private: save field values
	// =========================================================

	private function save_field_values( int $trustbio_author_id ): void {
		global $wpdb;

		// Nonce was already verified by the calling ajax_save() method via check_ajax_referer().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['fields'] ) || ! is_array( $_POST['fields'] ) ) {
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			return;
		}

		$trustbio_ft = $wpdb->prefix . 'trustbio_author_fields';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_all_fields = $wpdb->get_results( "SELECT id, slug, field_type FROM `{$trustbio_ft}` WHERE is_active = 1" );

		$trustbio_field_map = array();
		foreach ( (array) $trustbio_all_fields as $trustbio_f ) {
			$trustbio_field_map[ $trustbio_f->slug ] = $trustbio_f;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
		foreach ( wp_unslash( $_POST['fields'] ) as $trustbio_slug => $trustbio_value ) {
			// phpcs:enable WordPress.Security.NonceVerification.Missing
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
				$this->trustbio_fv_table,
				array(
					'author_id' => $trustbio_author_id,
					'field_id'  => (int) $trustbio_fobj->id,
					'value'     => $trustbio_val,
				),
				array( '%d', '%d', '%s' )
			);
		}
	}
}
