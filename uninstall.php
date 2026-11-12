<?php
/**
 * TrustBio Uninstall Script
 *
 * Runs only when a user clicks "Delete" on the Plugins screen.
 * Data is removed only if the admin has opted in via Settings → Maintenance.
 * All variables prefixed with $trustbio_ to satisfy WordPress.NamingConventions.PrefixAllGlobals.
 *
 * @package TrustBio
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! get_option( 'trustbio_delete_data_on_uninstall', 0 ) ) {
	return;
}

global $wpdb;

// Drop custom tables (dependents first, then parents).
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$trustbio_tables = array(
	$wpdb->prefix . 'trustbio_author_field_values',
	$wpdb->prefix . 'trustbio_author_credentials',
	$wpdb->prefix . 'trustbio_review_bars',
	$wpdb->prefix . 'trustbio_author_fields',
	$wpdb->prefix . 'trustbio_author_boxes',
	$wpdb->prefix . 'trustbio_authors',
	$wpdb->prefix . 'trustbio_author_categories',
);

foreach ( $trustbio_tables as $trustbio_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	// %i is the identifier placeholder, so the table name is quoted by
	// prepare() rather than escaped by hand and concatenated.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $trustbio_table ) );
}

// Delete all plugin options.
$trustbio_options = array(
	'trustbio_db_version',
	'trustbio_db_error',
	'trustbio_enabled_features',
	'trustbio_enabled_post_types',
	'trustbio_show_powered_by',
	'trustbio_allow_guest_no_email',
	'trustbio_allowed_roles',
	'trustbio_default_box_id',
	'trustbio_default_sidebar_box_id',
	'trustbio_default_review_bar_id',
	'trustbio_box_position',
	'trustbio_exclude_post_ids',
	'trustbio_auto_insert_pages',
	'trustbio_auto_insert_posts',
	'trustbio_author_url_base',
	'trustbio_enable_archive_registered',
	'trustbio_enable_archive_guest',
	'trustbio_avatar_priority',
	'trustbio_gravatar_default',
	'trustbio_profile_post_types',
	'trustbio_enable_schema',
	'trustbio_schema_compat_mode',
	'trustbio_debug_mode',
	'trustbio_enable_rest_api',
	'trustbio_delete_data_on_uninstall',
	// Review Date.
	'trustbio_review_display',
	'trustbio_review_default_label',
	'trustbio_review_stale_months',
	'trustbio_review_show_column',
	'trustbio_review_bar_color',
	'trustbio_review_bar_position',
	'trustbio_review_with_author_box',
	// Review Dashboard Widget.
	'trustbio_widget_review_count',
	'trustbio_widget_default_mode',
	'trustbio_widget_default_show_bio',
	'trustbio_widget_default_show_social',
	// Author Archive.
	'trustbio_archive_enabled',
	'trustbio_archive_per_page',
	'trustbio_archive_hero_color',
	'trustbio_archive_accent_color',
	// Contribution Count.
	'trustbio_show_contribution_count',
	'trustbio_contribution_noun',
	'trustbio_contribution_noun_plural',
	'trustbio_contribution_min_count',
);

foreach ( $trustbio_options as $trustbio_option ) {
	delete_option( $trustbio_option );
}

/*
 * Delete the per-author option families. These keys carry an author ID suffix,
 * so they cannot be listed above and have to be matched with LIKE.
 */
$trustbio_option_prefixes = array(
	'trustbio_author_expertise_',
	'trustbio_author_sameas_',
	'trustbio_author_schema_job_',
);

foreach ( $trustbio_option_prefixes as $trustbio_prefix ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$trustbio_keys = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->esc_like( $trustbio_prefix ) . '%'
		)
	);
	foreach ( $trustbio_keys as $trustbio_key ) {
		delete_option( $trustbio_key );
	}
}

// Delete transients, including the per-post schema preview cache.
$trustbio_transients = array(
	'trustbio_dashboard_attention',
	'trustbio_llmstxt_cache',
);

foreach ( $trustbio_transients as $trustbio_transient ) {
	delete_transient( $trustbio_transient );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$trustbio_transient_keys = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->esc_like( '_transient_trustbio_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_trustbio_' ) . '%'
	)
);
foreach ( $trustbio_transient_keys as $trustbio_transient_key ) {
	delete_option( $trustbio_transient_key );
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

// Remove all post meta.
$trustbio_meta_keys = array(
	'_trustbio_authors',
	'_trustbio_hide_box',
	'_trustbio_box_id',
	'_trustbio_sidebar_box_id',
	'_trustbio_review_bar_id',
	'_trustbio_review_date',
	'_trustbio_reviewer_id',
	'_trustbio_review_display',
	'_trustbio_review_label',
);

foreach ( $trustbio_meta_keys as $trustbio_meta_key ) {
	delete_post_meta_by_key( $trustbio_meta_key );
}

// Clear any scheduled events this plugin registered.
wp_clear_scheduled_hook( 'trustbio_email_digest_run' );
wp_unschedule_hook( 'trustbio_run_schema_export' );

flush_rewrite_rules();
