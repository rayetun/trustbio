<?php
/**
 * Author Expertise Topics — tag authors with WordPress category expertise areas.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Author_Expertise
 */
class TrustBio_Author_Expertise {

	/**
	 * AJAX: Save expertise topic category IDs for an author.
	 */
	public function ajax_save_expertise(): void {
		check_ajax_referer( 'trustbio_save_expertise', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'trustbio' ) ) );
		}

		$trustbio_author_id = absint( $_POST['author_id'] ?? 0 );
		if ( ! $trustbio_author_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid author ID.', 'trustbio' ) ) );
		}

		$trustbio_raw_ids = isset( $_POST['category_ids'] )
			? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['category_ids'] ) )
			: array();
		$trustbio_cat_ids = array_values( array_filter( array_map( 'absint', $trustbio_raw_ids ) ) );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'trustbio_authors',
			array( 'expertise_cats' => wp_json_encode( $trustbio_cat_ids ) ),
			array( 'id' => $trustbio_author_id ),
			array( '%s' ),
			array( '%d' )
		);

		trustbio_clear_author_cache( $trustbio_author_id );

		wp_send_json_success( array( 'message' => __( 'Expertise topics saved.', 'trustbio' ) ) );
	}

	/**
	 * Get saved expertise category IDs for an author.
	 *
	 * @param int $author_id Author ID.
	 * @return int[]
	 */
	public static function get_expertise( int $author_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$trustbio_val = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT expertise_cats FROM {$wpdb->prefix}trustbio_authors WHERE id = %d",
				$author_id
			)
		);

		if ( ! $trustbio_val ) {
			return array();
		}

		$trustbio_decoded = json_decode( $trustbio_val, true );
		return is_array( $trustbio_decoded ) ? array_map( 'intval', $trustbio_decoded ) : array();
	}

	/**
	 * Build a knowsAbout array for schema output.
	 *
	 * @param int $author_id Author ID.
	 * @return array<int, array<string, string>>
	 */
	public static function build_knows_about( int $author_id ): array {
		$trustbio_cat_ids   = self::get_expertise( $author_id );
		$trustbio_knows     = array();

		foreach ( $trustbio_cat_ids as $trustbio_cat_id ) {
			$trustbio_cat = get_category( $trustbio_cat_id );
			if ( ! $trustbio_cat || is_wp_error( $trustbio_cat ) ) {
				continue;
			}
			$trustbio_knows[] = array(
				'@type' => 'Thing',
				'name'  => $trustbio_cat->name,
				'url'   => get_category_link( $trustbio_cat_id ),
			);
		}

		return $trustbio_knows;
	}
}
