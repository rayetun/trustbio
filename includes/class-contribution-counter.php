<?php
/**
 * Author Contribution Counter — Phase 14.
 *
 * Counts published posts per author per category using a LIKE query
 * against the serialized _trustbio_authors post meta.
 *
 * Cache strategy: wp_cache_set() with HOUR_IN_SECONDS TTL per author.
 * Cache is busted on save_post / delete_post when the post is published
 * and the author appears in its _trustbio_authors meta.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Contribution_Counter
 */
class TrustBio_Contribution_Counter {

	private const CACHE_GROUP = 'trustbio_contributions';
	private const CACHE_TTL   = HOUR_IN_SECONDS;

	// =========================================================
	// Public API
	// =========================================================

	/**
	 * Get contribution counts for an author, keyed by category_id.
	 * Returns an array like: [ 0 => 12, 3 => 7 ]
	 * where 0 = no category assigned, 3 = category ID 3.
	 * Uses wp_cache — fast on repeated calls within the same request.
	 *
	 * @param int $trustbio_author_id TrustBio author ID.
	 * @return array<int|string, int> category_id → count map.
	 */
	public static function get_counts( int $trustbio_author_id ): array {
		$trustbio_cache_key = 'author_' . $trustbio_author_id;
		$trustbio_cached    = wp_cache_get( $trustbio_cache_key, self::CACHE_GROUP );

		if ( false !== $trustbio_cached ) {
			return (array) $trustbio_cached;
		}

		$trustbio_counts = self::query_counts( $trustbio_author_id );
		wp_cache_set( $trustbio_cache_key, $trustbio_counts, self::CACHE_GROUP, self::CACHE_TTL );

		return $trustbio_counts;
	}

	/**
	 * Get the total contribution count across all categories for an author.
	 *
	 * @param int $trustbio_author_id TrustBio author ID.
	 * @return int Total post count.
	 */
	public static function get_total( int $trustbio_author_id ): int {
		return (int) array_sum( self::get_counts( $trustbio_author_id ) );
	}

	/**
	 * Get the contribution count for a specific author + category combination.
	 * This is what the author box uses: it shows the count for the role
	 * the author holds on the current post.
	 *
	 * @param int      $trustbio_author_id   TrustBio author ID.
	 * @param int|null $trustbio_category_id Category ID, or null for uncategorised.
	 * @return int
	 */
	public static function get_count_for_category( int $trustbio_author_id, ?int $trustbio_category_id ): int {
		$trustbio_counts = self::get_counts( $trustbio_author_id );
		$trustbio_key    = $trustbio_category_id ?? 0;
		return (int) ( $trustbio_counts[ $trustbio_key ] ?? 0 );
	}

	// =========================================================
	// Cache busting
	// =========================================================

	/**
	 * Bust cache for all authors affected by a post save or delete.
	 * Called on save_post and delete_post hooks.
	 *
	 * Only busts when:
	 *   1. Post type is in enabled post types.
	 *   2. Post status is publish (or was publish before deletion).
	 *
	 * @param int     $trustbio_post_id Post ID.
	 * @param WP_Post $trustbio_post    Post object.
	 */
	public static function bust_on_post_change( int $trustbio_post_id, WP_Post $trustbio_post ): void {
		// Only care about enabled post types.
		if ( ! trustbio_is_enabled_for_post_type( $trustbio_post->post_type ) ) {
			return;
		}

		// Only bust for published posts (draft saves don't affect counts).
		if ( 'publish' !== $trustbio_post->post_status ) {
			return;
		}

		// Get all authors assigned to this post.
		$trustbio_assignments = get_post_meta( $trustbio_post_id, '_trustbio_authors', true );
		if ( ! is_array( $trustbio_assignments ) ) {
			return;
		}

		foreach ( $trustbio_assignments as $trustbio_entry ) {
			$trustbio_author_id = absint( $trustbio_entry['author_id'] ?? 0 );
			if ( $trustbio_author_id ) {
				wp_cache_delete( 'author_' . $trustbio_author_id, self::CACHE_GROUP );
			}
		}
	}

	/**
	 * Bust cache when a post transitions from published to another status.
	 * Hooked to transition_post_status.
	 *
	 * @param string  $trustbio_new_status New status.
	 * @param string  $trustbio_old_status Old status.
	 * @param WP_Post $trustbio_post       Post object.
	 */
	public static function bust_on_status_transition( string $trustbio_new_status, string $trustbio_old_status, WP_Post $trustbio_post ): void {
		// Only act when publish status is gained or lost.
		if ( $trustbio_new_status === $trustbio_old_status ) {
			return;
		}
		if ( 'publish' !== $trustbio_new_status && 'publish' !== $trustbio_old_status ) {
			return;
		}

		self::bust_on_post_change( $trustbio_post->ID, $trustbio_post );
	}

	// =========================================================
	// Query
	// =========================================================

	/**
	 * Query the DB for contribution counts.
	 *
	 * Uses a LIKE search against the serialized _trustbio_authors meta value.
	 * WordPress has serialized this format since 2.x and it is stable.
	 *
	 * The serialized array entry for author_id=3, category_id=2 looks like:
	 *   a:3:{s:9:"author_id";i:3;s:11:"category_id";i:2;s:10:"sort_order";i:0;}
	 *
	 * We match on: "author_id";i:{id}; — this is specific enough to avoid
	 * false positives from other integer values in the payload.
	 *
	 * @param int $trustbio_author_id TrustBio author ID.
	 * @return array<int|string, int>
	 */
	private static function query_counts( int $trustbio_author_id ): array {
		global $wpdb;

		$trustbio_enabled_types = trustbio_get_enabled_post_types();
		if ( empty( $trustbio_enabled_types ) ) {
			return array();
		}

		// Build placeholders for post type IN clause.
		$trustbio_placeholders = implode( ', ', array_fill( 0, count( $trustbio_enabled_types ), '%s' ) );

		// LIKE pattern: matches serialized "author_id";i:N; regardless of surrounding fields.
		$trustbio_like = $wpdb->esc_like( '"author_id";i:' . $trustbio_author_id . ';' );

		// Fetch all matching post meta values so we can extract category_id.
		// We fetch the full meta_value to deserialize and read category_id per entry.
		// Build the full SQL string before prepare() so no interpolation occurs inside prepare().
		// $wpdb->postmeta and $wpdb->posts are WP core table name properties (always safe).
		// $trustbio_placeholders is implode(',', array_fill(0, N, '%s')) — only %s tokens.
		$trustbio_sql = "SELECT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_trustbio_authors'
				  AND pm.meta_value LIKE %s
				  AND p.post_status = 'publish'
				  AND p.post_type IN ( {$trustbio_placeholders} )"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WP core props + %s placeholders only.

		$trustbio_args = array_merge(
			array( '%' . $trustbio_like . '%' ),
			$trustbio_enabled_types
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$trustbio_rows = $wpdb->get_results( $wpdb->prepare( $trustbio_sql, $trustbio_args ) );

		if ( empty( $trustbio_rows ) ) {
			return array();
		}

		// Tally counts per category_id.
		$trustbio_counts = array();

		foreach ( $trustbio_rows as $trustbio_row ) {
			$trustbio_assignments = maybe_unserialize( $trustbio_row->meta_value );
			if ( ! is_array( $trustbio_assignments ) ) {
				continue;
			}
			foreach ( $trustbio_assignments as $trustbio_entry ) {
				if ( (int) ( $trustbio_entry['author_id'] ?? 0 ) !== $trustbio_author_id ) {
					continue;
				}
				// category_id can be null (uncategorised) — store as 0.
				$trustbio_cat_id = isset( $trustbio_entry['category_id'] ) && $trustbio_entry['category_id']
					? (int) $trustbio_entry['category_id']
					: 0;

				$trustbio_counts[ $trustbio_cat_id ] = ( $trustbio_counts[ $trustbio_cat_id ] ?? 0 ) + 1;
			}
		}

		return $trustbio_counts;
	}
}
