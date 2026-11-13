<?php
/**
 * Shared helper / utility functions for TrustBio.
 *
 * All table names are built from $wpdb->prefix + a hardcoded literal string.
 * They are never constructed from user input. The per-line phpcs suppressions
 * below are intentional and reviewed — they silence sniffs that cannot
 * statically determine a variable is safe, even when it provably is.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================
// DB helpers
// ============================================================

/**
 * Get all author categories.
 *
 * @param bool $trustbio_enabled_only Return only enabled categories.
 * @return array<int, object>
 */
function trustbio_get_categories( bool $trustbio_enabled_only = false ): array {
	global $wpdb;
	$trustbio_t = $wpdb->prefix . 'trustbio_author_categories';

	if ( $trustbio_enabled_only ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (array) $wpdb->get_results( "SELECT * FROM `{$trustbio_t}` WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC" );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	return (array) $wpdb->get_results( "SELECT * FROM `{$trustbio_t}` ORDER BY sort_order ASC, id ASC" );
}

/**
 * Get a single author category by ID.
 *
 * @param int $trustbio_id Category ID.
 * @return object|null
 */
function trustbio_get_category( int $trustbio_id ): ?object {
	global $wpdb;
	$trustbio_t = $wpdb->prefix . 'trustbio_author_categories';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );
}

/**
 * Get active author fields.
 *
 * @param bool $trustbio_social_only Return only social fields.
 * @return array<int, object>
 */
function trustbio_get_fields( bool $trustbio_social_only = false ): array {
	global $wpdb;
	$trustbio_t = $wpdb->prefix . 'trustbio_author_fields';

	if ( $trustbio_social_only ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_rows = (array) $wpdb->get_results( "SELECT * FROM `{$trustbio_t}` WHERE is_active = 1 AND is_social = 1 ORDER BY sort_order ASC" );

		/**
		 * Filters the active social profile fields.
		 *
		 * @since 1.0.0
		 * @param array<int,object> $fields Field rows, in sort order.
		 */
		return (array) apply_filters( 'trustbio_social_fields', $trustbio_rows );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$trustbio_rows = (array) $wpdb->get_results( "SELECT * FROM `{$trustbio_t}` WHERE is_active = 1 ORDER BY sort_order ASC" );

	/**
	 * Filters the active author fields.
	 *
	 * @since 1.0.0
	 * @param array<int,object> $fields Field rows, in sort order.
	 */
	return (array) apply_filters( 'trustbio_author_fields', $trustbio_rows );
}

/**
 * Get all fields including disabled ones — for admin screens.
 *
 * @return array<int, object>
 */
function trustbio_get_all_fields(): array {
	global $wpdb;
	$trustbio_t = $wpdb->prefix . 'trustbio_author_fields';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	return (array) $wpdb->get_results( "SELECT * FROM `{$trustbio_t}` ORDER BY sort_order ASC" );
}

/**
 * Get an author record by ID.
 *
 * @param int $trustbio_id Author ID.
 * @return object|null
 */
function trustbio_get_author( int $trustbio_id ): ?object {
	$trustbio_cache_key = 'trustbio_author_' . $trustbio_id;
	$trustbio_cached    = wp_cache_get( $trustbio_cache_key, 'trustbio' );
	if ( false !== $trustbio_cached ) {
		return $trustbio_cached ?: null;
	}

	global $wpdb;
	$trustbio_t = $wpdb->prefix . 'trustbio_authors';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$trustbio_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );
	wp_cache_set( $trustbio_cache_key, $trustbio_row, 'trustbio', HOUR_IN_SECONDS );
	return $trustbio_row ?: null;
}

/**
 * Forget a cached author row.
 *
 * trustbio_get_author() caches for an hour. Without this, a site running a
 * persistent object cache — Redis or Memcached, which most managed hosts
 * enable — kept serving the old row after an edit: a corrected name, bio or
 * job title would not appear on the front end for up to an hour, and a deleted
 * author carried on being rendered. Where there is no persistent cache the
 * entry only lives for the current request, which is why this went unnoticed.
 *
 * Call it after every write to the authors table.
 *
 * @param int $trustbio_id Author ID.
 * @return void
 */
function trustbio_clear_author_cache( int $trustbio_id ): void {
	wp_cache_delete( 'trustbio_author_' . $trustbio_id, 'trustbio' );

	// The Settings screen's counts include the author total.
	// trustbio_get_author_by_slug() reads the table directly and caches
	// nothing, so there is no second entry to clear.
	wp_cache_delete( 'trustbio_sp_stats', 'trustbio' );
}

/**
 * Get an author record by slug.
 *
 * @param string $trustbio_slug Author slug.
 * @return object|null
 */
function trustbio_get_author_by_slug( string $trustbio_slug ): ?object {
	global $wpdb;
	$trustbio_t = $wpdb->prefix . 'trustbio_authors';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE slug = %s", $trustbio_slug ) );
}

/**
 * Get social/field values for an author, keyed by field slug.
 *
 * @param int $trustbio_author_id Author ID.
 * @return array<string, string>
 */
function trustbio_get_author_field_values( int $trustbio_author_id ): array {
	global $wpdb;
	$trustbio_vt = $wpdb->prefix . 'trustbio_author_field_values';
	$trustbio_ft = $wpdb->prefix . 'trustbio_author_fields';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$trustbio_rows = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			"SELECT f.slug, v.value FROM `{$trustbio_vt}` v INNER JOIN `{$trustbio_ft}` f ON f.id = v.field_id WHERE v.author_id = %d",
			$trustbio_author_id
		)
	);

	$trustbio_result = array();
	foreach ( (array) $trustbio_rows as $trustbio_row ) {
		$trustbio_result[ $trustbio_row->slug ] = $trustbio_row->value;
	}
	return $trustbio_result;
}

/**
 * Get authors assigned to a post.
 *
 * @param int $trustbio_post_id Post ID.
 * @return array<int, array{author: object, category: object|null, sort_order: int}>
 */
function trustbio_get_post_authors( int $trustbio_post_id ): array {
	$trustbio_raw = get_post_meta( $trustbio_post_id, '_trustbio_authors', true );

	if ( empty( $trustbio_raw ) || ! is_array( $trustbio_raw ) ) {
		return trustbio_get_fallback_author( $trustbio_post_id );
	}

	$trustbio_result = array();
	foreach ( $trustbio_raw as $trustbio_entry ) {
		if ( ! is_array( $trustbio_entry ) || ! isset( $trustbio_entry['author_id'] ) ) {
			continue;
		}
		$trustbio_author = trustbio_get_author( (int) $trustbio_entry['author_id'] );
		if ( ! $trustbio_author ) {
			continue;
		}
		$trustbio_category = isset( $trustbio_entry['category_id'] )
			? trustbio_get_category( (int) $trustbio_entry['category_id'] )
			: null;

		$trustbio_result[] = array(
			'author'     => $trustbio_author,
			'category'   => $trustbio_category,
			'sort_order' => (int) ( $trustbio_entry['sort_order'] ?? 0 ),
		);
	}

	usort(
		$trustbio_result,
		static function ( $trustbio_a, $trustbio_b ) {
			return $trustbio_a['sort_order'] <=> $trustbio_b['sort_order'];
		}
	);

	return $trustbio_result;
}

/**
 * Build a fallback author entry from the native WordPress post_author field.
 *
 * @param int $trustbio_post_id Post ID.
 * @return array
 */
function trustbio_get_fallback_author( int $trustbio_post_id ): array {
	global $wpdb;

	$trustbio_wp_user_id = (int) get_post_field( 'post_author', $trustbio_post_id );
	if ( ! $trustbio_wp_user_id ) {
		return array();
	}

	$trustbio_t = $wpdb->prefix . 'trustbio_authors';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$trustbio_author = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE wp_user_id = %d AND is_active = 1 LIMIT 1", $trustbio_wp_user_id ) );

	if ( ! $trustbio_author ) {
		return array();
	}

	$trustbio_category = $trustbio_author->category_id
		? trustbio_get_category( (int) $trustbio_author->category_id )
		: null;

	return array(
		array(
			'author'     => $trustbio_author,
			'category'   => $trustbio_category,
			'sort_order' => 0,
		),
	);
}

/**
 * Get an author box by ID with settings decoded.
 *
 * @param int $trustbio_box_id Author Box ID.
 * @return object|null
 */
function trustbio_get_author_box( int $trustbio_box_id ): ?object {
	global $wpdb;
	$trustbio_t = $wpdb->prefix . 'trustbio_author_boxes';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$trustbio_box = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE id = %d", $trustbio_box_id ) );

	if ( $trustbio_box && ! empty( $trustbio_box->settings ) ) {
		$trustbio_box->settings = json_decode( $trustbio_box->settings, true );
	}

	return $trustbio_box;
}

/**
 * Get the global default author box.
 *
 * @return object|null
 */
function trustbio_get_default_author_box(): ?object {
	global $wpdb;
	$trustbio_t = $wpdb->prefix . 'trustbio_author_boxes';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$trustbio_box = $wpdb->get_row( "SELECT * FROM `{$trustbio_t}` WHERE is_default = 1 LIMIT 1" );

	if ( ! $trustbio_box ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_box = $wpdb->get_row( "SELECT * FROM `{$trustbio_t}` ORDER BY id ASC LIMIT 1" );
	}

	if ( $trustbio_box && ! empty( $trustbio_box->settings ) ) {
		$trustbio_box->settings = json_decode( $trustbio_box->settings, true );
	}

	return $trustbio_box;
}

// ============================================================
// Avatar helpers
// ============================================================

/**
 * Get the avatar URL for an author with fallback chain.
 *
 * @param object $trustbio_author Author object.
 * @param int    $trustbio_size   Avatar size in pixels.
 * @return string Escaped URL.
 */
function trustbio_get_avatar_url( object $trustbio_author, int $trustbio_size = 80 ): string {
	if ( ! empty( $trustbio_author->avatar_attach_id ) ) {
		$trustbio_img = wp_get_attachment_image_src( (int) $trustbio_author->avatar_attach_id, array( $trustbio_size, $trustbio_size ) );
		if ( $trustbio_img ) {
			return esc_url( $trustbio_img[0] );
		}
	}

	if ( ! empty( $trustbio_author->avatar_url ) ) {
		return esc_url( $trustbio_author->avatar_url );
	}

	if ( ! empty( $trustbio_author->email ) ) {
		return esc_url( get_avatar_url( $trustbio_author->email, array( 'size' => $trustbio_size ) ) );
	}

	if ( ! empty( $trustbio_author->wp_user_id ) ) {
		$trustbio_wp_user = get_userdata( (int) $trustbio_author->wp_user_id );
		if ( $trustbio_wp_user ) {
			return esc_url( get_avatar_url( $trustbio_wp_user->user_email, array( 'size' => $trustbio_size ) ) );
		}
	}

	return esc_url( get_avatar_url( 0, array( 'size' => $trustbio_size ) ) );
}

// ============================================================
// URL / slug helpers
// ============================================================

/**
 * Get the public profile URL for an author.
 *
 * @param object $trustbio_author Author object.
 * @return string
 */
function trustbio_get_author_url( object $trustbio_author ): string {
	$trustbio_base = sanitize_title( get_option( 'trustbio_author_url_base', 'authors' ) );
	return trailingslashit( home_url( $trustbio_base . '/' . $trustbio_author->slug ) );
}

/**
 * Generate a unique author slug.
 *
 * @param string $trustbio_name       Display name to slugify.
 * @param int    $trustbio_exclude_id Author ID to skip on edits.
 * @return string
 */
function trustbio_generate_author_slug( string $trustbio_name, int $trustbio_exclude_id = 0 ): string {
	global $wpdb;

	$trustbio_t       = $wpdb->prefix . 'trustbio_authors';
	$trustbio_base    = sanitize_title( $trustbio_name );
	$trustbio_slug    = $trustbio_base;
	$trustbio_counter = 1;

	while ( true ) {
		if ( $trustbio_exclude_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$trustbio_t}` WHERE slug = %s AND id != %d", $trustbio_slug, $trustbio_exclude_id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$trustbio_t}` WHERE slug = %s", $trustbio_slug ) );
		}

		if ( ! $trustbio_existing ) {
			break;
		}

		$trustbio_slug = $trustbio_base . '-' . $trustbio_counter;
		++$trustbio_counter;
	}

	return $trustbio_slug;
}

// ============================================================
// Schema helpers
// ============================================================

/**
 * Field slugs that map to schema.org sameAs (authority identity signals).
 *
 * @return array<string>
 */
function trustbio_get_same_as_field_slugs(): array {
	return array(
		'twitter', 'linkedin', 'facebook', 'instagram', 'youtube',
		'github', 'medium', 'substack', 'wikipedia', 'google_scholar',
		'researchgate', 'orcid', 'amazon_author', 'mastodon', 'threads', 'bluesky',
	);
}

/**
 * Every sameAs URL for an author, from their profile fields and the SEO tab.
 *
 * Lives here because two different Person nodes need it and only one of them
 * used to have it: the post-page node built by TrustBio_Schema emitted sameAs,
 * and the author archive built its own node without. So an author who listed
 * their ORCID had it published on every post they wrote and not on their own
 * profile page -- the one page that exists to establish who they are.
 *
 * Values go through trustbio_profile_url(), which expands a bare handle and
 * returns '' for anything it cannot turn into a URL, so a non-URL is dropped
 * rather than published as a false identity claim.
 *
 * @since 1.0.1
 *
 * @param object $trustbio_author Author row.
 * @return array<int,string> Absolute URLs, de-duplicated.
 */
function trustbio_build_same_as( object $trustbio_author ): array {
	$trustbio_author_id = (int) ( $trustbio_author->id ?? 0 );

	if ( $trustbio_author_id <= 0 ) {
		return array();
	}

	$trustbio_values  = trustbio_get_author_field_values( $trustbio_author_id );
	$trustbio_same_as = array();

	// The profile fields that are identity signals rather than decoration.
	foreach ( trustbio_get_same_as_field_slugs() as $trustbio_slug ) {
		if ( empty( $trustbio_values[ $trustbio_slug ] ) ) {
			continue;
		}

		$trustbio_same_as[] = trustbio_profile_url( $trustbio_slug, (string) $trustbio_values[ $trustbio_slug ] );
	}

	// Anything typed into the SEO tab. Free text, so the same rule applies:
	// what is not a URL is dropped rather than half-repaired.
	$trustbio_extra = get_option( 'trustbio_author_sameas_' . $trustbio_author_id, '' );

	if ( $trustbio_extra ) {
		foreach ( array_filter( array_map( 'trim', explode( "\n", $trustbio_extra ) ) ) as $trustbio_url ) {
			$trustbio_same_as[] = trustbio_profile_url( '', (string) $trustbio_url );
		}
	}

	/**
	 * Filters the sameAs URLs for an author.
	 *
	 * @since 1.0.1
	 * @param array<int,string> $same_as Absolute URLs.
	 * @param object            $author  Author row.
	 */
	return (array) apply_filters(
		'trustbio_author_same_as',
		array_values( array_unique( array_filter( $trustbio_same_as ) ) ),
		$trustbio_author
	);
}

/**
 * Profile URL shapes for the networks a handle can be expanded for.
 *
 * Only networks whose profile URL is "one host, one path, the handle at the
 * end" belong here. Mastodon does not qualify — a Mastodon handle carries
 * its own instance — and trustbio_profile_url() handles it separately.
 *
 * @since 1.0.1
 *
 * @return array<string,string> Field slug => sprintf pattern taking the handle.
 */
function trustbio_profile_url_patterns(): array {
	$trustbio_patterns = array(
		'twitter'        => 'https://twitter.com/%s',
		'linkedin'       => 'https://www.linkedin.com/in/%s',
		'facebook'       => 'https://www.facebook.com/%s',
		'instagram'      => 'https://www.instagram.com/%s',
		'youtube'        => 'https://www.youtube.com/@%s',
		'tiktok'         => 'https://www.tiktok.com/@%s',
		'pinterest'      => 'https://www.pinterest.com/%s',
		'github'         => 'https://github.com/%s',
		'medium'         => 'https://medium.com/@%s',
		'substack'       => 'https://%s.substack.com',
		'threads'        => 'https://www.threads.net/@%s',
		'bluesky'        => 'https://bsky.app/profile/%s',
		'orcid'          => 'https://orcid.org/%s',
		'google_scholar' => 'https://scholar.google.com/citations?user=%s',
		'researchgate'   => 'https://www.researchgate.net/profile/%s',
		'amazon_author'  => 'https://www.amazon.com/author/%s',
		// English Wikipedia. A bare Wikipedia handle is unusual — almost
		// everyone pastes the full URL, which never reaches this table — so
		// guessing en is better than refusing to build anything.
		'wikipedia'      => 'https://en.wikipedia.org/wiki/%s',
	);

	/**
	 * Filters the profile URL patterns used to expand a bare handle.
	 *
	 * @since 1.0.1
	 * @param array<string,string> $patterns Field slug => sprintf pattern.
	 */
	return (array) apply_filters( 'trustbio_profile_url_patterns', $trustbio_patterns );
}

/**
 * Whether a string is shaped like a hostname.
 *
 * Deliberately requires a dot. The point of this check is to tell
 * "rayetun.com" from "0000-0002-1825-0097", and only one of them has one.
 *
 * @since 1.0.1
 *
 * @param string $trustbio_host Candidate host.
 * @return bool
 */
function trustbio_looks_like_host( string $trustbio_host ): bool {
	return (bool) preg_match(
		'/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i',
		$trustbio_host
	);
}

/**
 * Turn a stored profile field value into a usable absolute URL.
 *
 * A profile field holds whatever the author typed. Some paste a full URL,
 * some type a bare handle, and an ORCID iD is normally written the way ORCID
 * itself prints it — 0000-0002-1825-0097, with no host at all.
 *
 * esc_url_raw() does not reject that. It returns "http://0000-0002-1825-0097",
 * which then gets published in sameAs and linked from the author archive. A
 * broken identity claim in structured data is worse than no claim, so a value
 * this function cannot turn into a real URL comes back as an empty string and
 * the caller drops it.
 *
 * @since 1.0.1
 *
 * @param string $trustbio_slug  Field slug, e.g. 'orcid'. Pass '' when the
 *                               value is not tied to a particular network.
 * @param string $trustbio_value The stored value.
 * @return string An absolute http(s) URL, or '' when none can be built.
 */
function trustbio_profile_url( string $trustbio_slug, string $trustbio_value ): string {
	$trustbio_value = trim( $trustbio_value );
	if ( '' === $trustbio_value ) {
		return '';
	}

	// Protocol-relative: a URL that left the scheme to the page. Every caller
	// here needs an absolute one.
	if ( 0 === strpos( $trustbio_value, '//' ) ) {
		$trustbio_value = 'https:' . $trustbio_value;
	}

	if ( preg_match( '#^https?://#i', $trustbio_value ) ) {
		return (string) esc_url_raw( $trustbio_value );
	}

	// Any other scheme is not a profile link: mailto:, data:, javascript:.
	// esc_url_raw() keeps some of those, so they are refused here instead.
	if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $trustbio_value ) ) {
		return '';
	}

	$trustbio_handle = ltrim( $trustbio_value, '@/' );

	// Whitespace means this is prose, not a handle — a display name typed
	// into a social field, say. Nothing addressable can be built from it.
	if ( '' === $trustbio_handle || preg_match( '/\s/', $trustbio_handle ) ) {
		return '';
	}

	$trustbio_handle_host = explode( '/', $trustbio_handle )[0];
	$trustbio_patterns    = trustbio_profile_url_patterns();

	if ( isset( $trustbio_patterns[ $trustbio_slug ] ) ) {
		/*
		 * A value like "orcid.org/0000-0002-1825-0097" is a URL missing only
		 * its scheme, not a handle. Feeding it to the pattern would produce
		 * https://orcid.org/orcid.org/0000-0002-1825-0097, so compare the
		 * first segment against the pattern's own host first. www. is
		 * stripped from both sides: people type linkedin.com/in/x as often
		 * as www.linkedin.com/in/x.
		 */
		$trustbio_pattern_host = (string) wp_parse_url( sprintf( $trustbio_patterns[ $trustbio_slug ], 'handle' ), PHP_URL_HOST );
		$trustbio_pattern_host = (string) preg_replace( '/^www\./i', '', $trustbio_pattern_host );
		$trustbio_bare_host    = (string) preg_replace( '/^www\./i', '', $trustbio_handle_host );

		if ( '' !== $trustbio_pattern_host && 0 === strcasecmp( $trustbio_bare_host, $trustbio_pattern_host ) ) {
			return (string) esc_url_raw( 'https://' . $trustbio_handle );
		}

		return (string) esc_url_raw( sprintf( $trustbio_patterns[ $trustbio_slug ], $trustbio_handle ) );
	}

	// Mastodon has no single host: the handle names its own instance, as
	// user@instance.tld. Anything else cannot be addressed.
	if ( 'mastodon' === $trustbio_slug ) {
		$trustbio_parts = explode( '@', $trustbio_handle );
		if ( 2 === count( $trustbio_parts ) && '' !== $trustbio_parts[0] && trustbio_looks_like_host( $trustbio_parts[1] ) ) {
			return (string) esc_url_raw( 'https://' . $trustbio_parts[1] . '/@' . $trustbio_parts[0] );
		}
		return '';
	}

	// A bare host, with or without a path: "rayetun.com/about".
	if ( trustbio_looks_like_host( $trustbio_handle_host ) ) {
		return (string) esc_url_raw( 'https://' . $trustbio_handle );
	}

	return '';
}

// ============================================================
// Security helpers
// ============================================================

/**
 * The HTML a TrustBio front-end block is allowed to contain.
 *
 * WordPress's post allowlist, plus the SVG elements and attributes the
 * credential badges and social icons are drawn with. A plain
 * wp_kses_post() would strip every icon in the plugin, because SVG is not
 * in the post allowlist at all.
 *
 * @return array<string,array<string,bool>>
 */
function trustbio_allowed_block_html(): array {
	$trustbio_svg_attr = array(
		'xmlns'            => true,
		'viewbox'          => true,
		'width'            => true,
		'height'           => true,
		'fill'             => true,
		'fill-rule'        => true,
		'clip-rule'        => true,
		'stroke'           => true,
		'stroke-width'     => true,
		'stroke-linecap'   => true,
		'stroke-linejoin'  => true,
		'd'                => true,
		'cx'               => true,
		'cy'               => true,
		'r'                => true,
		'x'                => true,
		'y'                => true,
		'x1'               => true,
		'y1'               => true,
		'x2'               => true,
		'y2'               => true,
		'rx'               => true,
		'ry'               => true,
		'points'           => true,
		'transform'        => true,
		'class'            => true,
		'style'            => true,
		'role'             => true,
		'focusable'        => true,
		'aria-hidden'      => true,
		'aria-label'       => true,
		'aria-labelledby'  => true,
	);

	$trustbio_allowed = wp_kses_allowed_html( 'post' );

	foreach ( array( 'svg', 'path', 'g', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'defs', 'use', 'title' ) as $trustbio_el ) {
		$trustbio_allowed[ $trustbio_el ] = $trustbio_svg_attr;
	}

	/*
	 * The post allowlist does not carry aria-* or role on ordinary elements,
	 * and the boxes use both: the badge icons are aria-hidden and the social
	 * links are labelled. Dropping them would leave the markup valid but
	 * less accessible than it was.
	 */
	foreach ( array( 'div', 'span', 'a', 'p', 'img', 'time', 'strong', 'em', 'small', 'ul', 'ol', 'li' ) as $trustbio_el ) {
		if ( ! isset( $trustbio_allowed[ $trustbio_el ] ) || ! is_array( $trustbio_allowed[ $trustbio_el ] ) ) {
			$trustbio_allowed[ $trustbio_el ] = array();
		}
		$trustbio_allowed[ $trustbio_el ] += array(
			'class'           => true,
			'style'           => true,
			'id'              => true,
			'title'           => true,
			'role'            => true,
			'aria-hidden'     => true,
			'aria-label'      => true,
			'aria-labelledby' => true,
			'datetime'        => true,
		);
	}

	/**
	 * Filter the HTML a TrustBio front-end block may contain.
	 *
	 * @since 1.0.0
	 * @param array<string,array<string,bool>> $trustbio_allowed wp_kses allowlist.
	 */
	return (array) apply_filters( 'trustbio_allowed_block_html', $trustbio_allowed );
}

/**
 * Last-pass escaping for a finished front-end block.
 *
 * Every value in these blocks is already escaped where it is interpolated,
 * field by field, as the markup is built. This is the belt to that pair of
 * braces, and it sits where it does for a reason: the author box and the
 * review bar are returned from the_content, the_title and two shortcodes,
 * so whatever they return is printed by WordPress without further
 * treatment. Constraining the finished string means a future edit to any
 * one of the builders cannot quietly put unescaped data on a page.
 *
 * It is wp_kses() with an explicit allowlist, not a pass-through: markup
 * outside the allowlist really is removed.
 *
 * @param string $trustbio_html A block of TrustBio-generated markup.
 * @return string
 */
function trustbio_kses_block( string $trustbio_html ): string {
	if ( '' === $trustbio_html ) {
		return '';
	}

	return wp_kses( $trustbio_html, trustbio_allowed_block_html() );
}


/**
 * Verify an AJAX request: nonce + capability.
 * Calls check_ajax_referer() directly so PHPCS can see the verification.
 *
 * @param string $trustbio_nonce_action Nonce action.
 * @param string $trustbio_nonce_key   $_POST key holding the nonce.
 * @param string $trustbio_capability  Required capability.
 */
function trustbio_verify_ajax_request(
	string $trustbio_nonce_action,
	string $trustbio_nonce_key = 'nonce',
	string $trustbio_capability = 'manage_options'
): void {
	check_ajax_referer( $trustbio_nonce_action, $trustbio_nonce_key );

	if ( ! current_user_can( $trustbio_capability ) ) {
		wp_send_json_error(
			array( 'message' => __( 'You do not have permission to perform this action.', 'trustbio' ) ),
			403
		);
	}
}

// ============================================================
// Misc helpers
// ============================================================

/**
 * Check if TrustBio is enabled for a given post type.
 *
 * @param string $trustbio_post_type Post type slug.
 * @return bool
 */

/**
 * Get all enabled post types as an array of slugs.
 *
 * @return array<int, string>
 */
function trustbio_get_enabled_post_types(): array {
	$trustbio_enabled = get_option( 'trustbio_enabled_post_types', array( 'post', 'page' ) );
	if ( ! is_array( $trustbio_enabled ) ) {
		$trustbio_enabled = array( 'post', 'page' );
	}
	return array_map( 'sanitize_key', $trustbio_enabled );
}

function trustbio_is_enabled_for_post_type( string $trustbio_post_type ): bool {
	$trustbio_enabled = get_option( 'trustbio_enabled_post_types', array( 'post', 'page' ) );
	return in_array( $trustbio_post_type, (array) $trustbio_enabled, true );
}

/**
 * Retrieve a TrustBio setting by short key.
 *
 * @param string $trustbio_key     Short key without the 'trustbio_' prefix.
 * @param mixed  $trustbio_default Default value.
 * @return mixed
 */
function trustbio_get_setting( string $trustbio_key, $trustbio_default = null ) {
	return get_option( 'trustbio_' . $trustbio_key, $trustbio_default );
}

/**
 * Render social icon links for an author — standalone helper for use in templates.
 * Duo templates call this directly for each author they render.
 *
 * @param object $trustbio_author Author object.
 * @param array  $trustbio_s      Box settings array.
 * @param string $trustbio_layout Layout slug (used to adjust icon size for small layouts).
 * @return string HTML — empty string when no social fields are set.
 */
function trustbio_render_social_icons_html( object $trustbio_author, array $trustbio_s, string $trustbio_layout = '' ): string {
	$trustbio_field_values  = trustbio_get_author_field_values( $trustbio_author->id );
	$trustbio_social_fields = trustbio_get_fields( true );

	if ( empty( $trustbio_social_fields ) || empty( $trustbio_field_values ) ) {
		return '';
	}

	// Duo layouts use smaller icons.
	$trustbio_is_duo      = in_array( $trustbio_layout, array( 'duo-light', 'duo-dark' ), true );
	$trustbio_icon_size   = $trustbio_is_duo ? 11 : (int) ( $trustbio_s['icon_size'] ?? 20 );
	$trustbio_icon_fill   = $trustbio_is_duo
		? ( 'duo-dark' === $trustbio_layout ? '#d9c08d' : '#374151' )
		: esc_attr( trustbio_social_icon_fill( $trustbio_s ) );

	$trustbio_html = '';
	foreach ( $trustbio_social_fields as $trustbio_field ) {
		/*
		 * What the author typed, turned into a real URL. esc_url() on the raw
		 * value does not reject a bare handle or a bare ORCID iD: it returns
		 * http://0000-0002-1825-0097 and the box links to it. A value that
		 * cannot become a URL is skipped instead of linked to nowhere.
		 */
		$trustbio_url = trustbio_profile_url(
			(string) $trustbio_field->slug,
			(string) ( $trustbio_field_values[ $trustbio_field->slug ] ?? '' )
		);
		if ( '' === $trustbio_url ) {
			continue;
		}

		$trustbio_svg = trustbio_get_social_svg( $trustbio_field->slug, $trustbio_icon_size, $trustbio_icon_fill );

		$trustbio_html .= sprintf(
			'<a href="%s" class="tb-social-link trustbio-social-%s" target="_blank" rel="noopener noreferrer nofollow" aria-label="%s">%s</a>',
			esc_url( $trustbio_url ),
			esc_attr( $trustbio_field->slug ),
			esc_attr( $trustbio_field->label ),
			$trustbio_svg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	return $trustbio_html;
}

/**
 * Return an inline SVG for a given social field slug.
 *
 * @param string $trustbio_slug  Field slug.
 * @param int    $trustbio_size  Icon size in px.
 * @param string $trustbio_color Fill colour.
 * @return string SVG HTML.
 */
function trustbio_get_social_svg( string $trustbio_slug, int $trustbio_size, string $trustbio_color ): string {
	$trustbio_s = (string) $trustbio_size;
	$trustbio_c = esc_attr( $trustbio_color );

	$trustbio_paths = array(
		'twitter'        => '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.213 5.567zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
		'twitter_x'      => '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.213 5.567zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
		'linkedin'       => '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>',
		'facebook'       => '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
		'instagram'      => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>',
		'youtube'        => '<path d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/>',
		'github'         => '<path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>',
		'tiktok'        => '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>',
		'pinterest'     => '<path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/>',
		'medium'        => '<path d="M13.54 12a6.8 6.8 0 01-6.77 6.82A6.8 6.8 0 010 12a6.8 6.8 0 016.77-6.82A6.8 6.8 0 0113.54 12zm7.42 0c0 3.54-1.51 6.42-3.38 6.42-1.87 0-3.39-2.88-3.39-6.42s1.52-6.42 3.39-6.42 3.38 2.88 3.38 6.42M24 12c0 3.17-.53 5.75-1.19 5.75-.66 0-1.19-2.58-1.19-5.75s.53-5.75 1.19-5.75C23.47 6.25 24 8.83 24 12z"/>',
	);

	$trustbio_path = $trustbio_paths[ $trustbio_slug ]
		?? '<path d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>';

	return sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" width="%s" height="%s" viewBox="0 0 24 24" fill="%s">%s</svg>',
		$trustbio_s,
		$trustbio_s,
		$trustbio_c,
		$trustbio_path
	);
}

/**
 * Return the inline review-date HTML for a specific author on a post.
 *
 * Checks if this author is the designated reviewer for the post, then
 * delegates to TrustBio_Review_Date::render_inline_review(). Returns ''
 * when the author is not the reviewer or no review date is set.
 *
 * @param int    $trustbio_post_id    Post ID.
 * @param int    $trustbio_author_id  TrustBio author ID (not WP user ID).
 * @param string $trustbio_accent_col Accent colour for the icon (hex).
 * @return string HTML or empty string.
 */
/**
 * Build an inline style string for the author box wrapper from saved settings.
 *
 * Only emits styles the user has explicitly configured so CSS-class defaults
 * are not overridden unless the admin actually changed them.
 *
 * @param array $settings Box settings array.
 * @return string Inline CSS or empty string.
 */
function trustbio_build_wrapper_style( array $settings ): string {
	$trustbio_parts = [];

	if ( ! empty( $settings['bg_color'] ) ) {
		$trustbio_parts[] = 'background:' . $settings['bg_color'];
	}

	$trustbio_bs = $settings['border_style'] ?? 'none';
	if ( 'none' !== $trustbio_bs && '' !== $trustbio_bs ) {
		$trustbio_bw = (int) ( $settings['border_width'] ?? 0 );
		$trustbio_bc = $settings['border_color'] ?? '#e5e7eb';
		$trustbio_bt = isset( $settings['border_top'] )    ? (int) $settings['border_top']    : $trustbio_bw;
		$trustbio_br = isset( $settings['border_right'] )  ? (int) $settings['border_right']  : $trustbio_bw;
		$trustbio_bb = isset( $settings['border_bottom'] ) ? (int) $settings['border_bottom'] : $trustbio_bw;
		$trustbio_bl = isset( $settings['border_left'] )   ? (int) $settings['border_left']   : $trustbio_bw;
		if ( $trustbio_bt || $trustbio_br || $trustbio_bb || $trustbio_bl ) {
			$trustbio_parts[] = "border-top:{$trustbio_bt}px {$trustbio_bs} {$trustbio_bc}";
			$trustbio_parts[] = "border-right:{$trustbio_br}px {$trustbio_bs} {$trustbio_bc}";
			$trustbio_parts[] = "border-bottom:{$trustbio_bb}px {$trustbio_bs} {$trustbio_bc}";
			$trustbio_parts[] = "border-left:{$trustbio_bl}px {$trustbio_bs} {$trustbio_bc}";
		}
	}

	$trustbio_brad = (int) ( $settings['border_radius'] ?? 0 );
	if ( $trustbio_brad ) {
		$trustbio_parts[] = "border-radius:{$trustbio_brad}px";
	}

	$trustbio_pad = (int) ( $settings['padding'] ?? 0 );
	if ( $trustbio_pad ) {
		$trustbio_parts[] = "padding:{$trustbio_pad}px";
	}

	// CSS custom properties — allow per-box font and button overrides.
	$trustbio_font_h = $settings['font_heading'] ?? '';
	if ( $trustbio_font_h ) {
		$trustbio_parts[] = '--tb-font-heading:' . $trustbio_font_h;
	}
	$trustbio_font_l = $settings['font_label'] ?? '';
	if ( $trustbio_font_l ) {
		$trustbio_parts[] = '--tb-font-label:' . $trustbio_font_l;
	}
	$trustbio_btn_bg = $settings['btn_color'] ?? '';
	if ( $trustbio_btn_bg ) {
		$trustbio_parts[] = '--tb-btn-bg:' . $trustbio_btn_bg;
	}
	$trustbio_btn_color = $settings['btn_text_color'] ?? '';
	if ( $trustbio_btn_color ) {
		$trustbio_parts[] = '--tb-btn-color:' . $trustbio_btn_color;
	}

	/*
	 * Builder controls that have no markup of their own.
	 *
	 * Avatar shape, box shadow, the social-icon treatment and the label/job
	 * title type scale were all sanitised and saved but never reached the
	 * page -- the layout stylesheet hard-coded each one. They are emitted as
	 * custom properties here instead, because every caller that renders a box
	 * already calls this function: the eight front-end templates and the
	 * builder's own live preview. One source, so the preview cannot drift
	 * from what the visitor sees.
	 *
	 * Each property is consumed in trustbio-public.css as
	 * var( --tb-x, <the value that layout used before >), so a box that never
	 * set the key keeps exactly the appearance it has today.
	 */

	// Avatar shape. Layout fallbacks differ (simple uses 14px), so only
	// override when the box actually carries a choice.
	$trustbio_shape_radius = array(
		'circle'  => '50%',
		'rounded' => '14px',
		'square'  => '0',
	);
	$trustbio_shape = $settings['avatar_shape'] ?? '';
	if ( isset( $trustbio_shape_radius[ $trustbio_shape ] ) ) {
		$trustbio_parts[] = '--tb-avatar-radius:' . $trustbio_shape_radius[ $trustbio_shape ];
	}

	// Avatar size. The slider ran from 40 to 160 and moved nothing but the
	// resolution of the Gravatar fetch; the rendered size was hard-coded per
	// layout.
	$trustbio_avatar_size = (int) ( $settings['avatar_size'] ?? 0 );
	if ( $trustbio_avatar_size ) {
		$trustbio_parts[] = '--tb-avatar-size:' . $trustbio_avatar_size . 'px';
	}

	// Box shadow. Absent means a box saved before the control worked, which
	// should keep its shadow -- so only an explicit false turns it off.
	if ( array_key_exists( 'box_shadow', $settings ) ) {
		$trustbio_parts[] = empty( $settings['box_shadow'] )
			? '--tb-shadow:none'
			: '--tb-shadow:0 30px 60px -30px rgba(20,20,20,.18), 0 8px 20px -10px rgba(20,20,20,.08)';
	}

	// Social icon chrome. The fill itself is an SVG attribute, set by
	// trustbio_social_icon_fill() so the two stay in step.
	$trustbio_icon_style = $settings['icon_style'] ?? '';
	$trustbio_icon_col   = $settings['icon_color'] ?? '#374151';
	if ( 'monochrome' === $trustbio_icon_style ) {
		$trustbio_parts[] = '--tb-icon-bg:' . $trustbio_icon_col;
		$trustbio_parts[] = '--tb-icon-border:0 solid transparent';
	} elseif ( 'outline' === $trustbio_icon_style ) {
		$trustbio_parts[] = '--tb-icon-bg:transparent';
		$trustbio_parts[] = '--tb-icon-border:1.5px solid #e5e7eb';
	} elseif ( 'color' === $trustbio_icon_style ) {
		$trustbio_parts[] = '--tb-icon-bg:#ece8e1';
		$trustbio_parts[] = '--tb-icon-border:0 solid transparent';
	}

	$trustbio_icon_size = (int) ( $settings['icon_size'] ?? 0 );
	if ( $trustbio_icon_size ) {
		$trustbio_parts[] = '--tb-icon-size:' . $trustbio_icon_size . 'px';
		// The chip grows with the glyph, as the builder preview always drew it.
		$trustbio_parts[] = '--tb-icon-chip:' . ( $trustbio_icon_size + 17 ) . 'px';
	}

	// Category label (the kicker / role line) and job title.
	$trustbio_label_color = $settings['label_color'] ?? '';
	if ( $trustbio_label_color ) {
		$trustbio_parts[] = '--tb-label-color:' . $trustbio_label_color;
	}
	$trustbio_label_size = (int) ( $settings['label_size'] ?? 0 );
	if ( $trustbio_label_size ) {
		$trustbio_parts[] = '--tb-label-size:' . $trustbio_label_size . 'px';
	}
	$trustbio_label_weight = $settings['label_weight'] ?? '';
	if ( $trustbio_label_weight ) {
		$trustbio_parts[] = '--tb-label-weight:' . $trustbio_label_weight;
	}
	$trustbio_jobtitle_size = (int) ( $settings['jobtitle_size'] ?? 0 );
	if ( $trustbio_jobtitle_size ) {
		$trustbio_parts[] = '--tb-jobtitle-size:' . $trustbio_jobtitle_size . 'px';
	}

	return implode( ';', $trustbio_parts );
}

/**
 * Fill colour for a social glyph, given the box's icon settings.
 *
 * Kept apart from the CSS custom properties because the fill is an attribute
 * on the SVG itself. Monochrome inverts: the chip takes the chosen colour and
 * the glyph goes white, which is the only style where icon_color is not the
 * glyph colour.
 *
 * @param array $settings Box settings.
 * @return string Hex colour.
 */
function trustbio_social_icon_fill( array $settings ): string {
	$trustbio_color = ( $settings['icon_color'] ?? '' ) ?: '#374151';
	return 'monochrome' === ( $settings['icon_style'] ?? '' ) ? '#ffffff' : $trustbio_color;
}

/**
 * Apply bio character limit to a bio string.
 *
 * Strips HTML tags, then truncates to $limit characters if $limit > 0.
 * Returns the original bio unchanged when $limit is 0 (no limit).
 *
 * @param string $bio   Raw bio text (may contain HTML).
 * @param int    $limit Maximum character count. 0 = no limit.
 * @return string Truncated plain-text bio, or original bio when limit is 0.
 */
function trustbio_apply_bio_limit( string $bio, int $limit ): string {
	if ( $limit <= 0 || '' === $bio ) {
		return $bio;
	}
	$plain = wp_strip_all_tags( $bio );
	if ( mb_strlen( $plain ) > $limit ) {
		return mb_substr( $plain, 0, $limit ) . '…';
	}
	return $plain;
}

/**
 * Check if a TrustBio feature module is enabled.
 *
 * Returns true when the option has never been set (backward-compatible default = all on),
 * or when the feature slug appears in the stored enabled-features array.
 *
 * @param string $trustbio_feature Feature slug.
 * @return bool
 */
function trustbio_feature_enabled( string $trustbio_feature ): bool {
	$trustbio_stored = get_option( 'trustbio_enabled_features', null );
	if ( null === $trustbio_stored ) {
		return true; // Option not set yet → all features on for existing installs.
	}
	return in_array( $trustbio_feature, (array) $trustbio_stored, true );
}

function trustbio_get_inline_review_html( int $trustbio_post_id, int $trustbio_author_id, string $trustbio_accent_col = '#6b7280' ): string {
	if ( ! $trustbio_post_id || ! $trustbio_author_id ) {
		return '';
	}
	$trustbio_reviewer_id = (int) get_post_meta( $trustbio_post_id, TrustBio_Review_Date::META_REVIEWER, true );
	if ( $trustbio_reviewer_id !== $trustbio_author_id ) {
		return '';
	}
	$trustbio_review = new TrustBio_Review_Date();
	return $trustbio_review->render_inline_review( $trustbio_post_id, $trustbio_accent_col );
}
