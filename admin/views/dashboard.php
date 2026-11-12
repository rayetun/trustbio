<?php
/**
 * Admin view: TrustBio Dashboard
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-local variables, not truly global
global $wpdb;

// ── Stats queries ──────────────────────────────────────────────────────────
$tbd_authors    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}trustbio_authors WHERE is_active = 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$tbd_categories = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}trustbio_author_categories WHERE is_enabled = 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$tbd_boxes      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}trustbio_author_boxes WHERE box_type = 'default'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$tbd_rev_bars   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}trustbio_review_bars" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

// ── Content health ─────────────────────────────────────────────────────────
$tbd_enabled_types = trustbio_get_enabled_post_types();

/*
 * One %s placeholder per post type, and the types passed as arguments.
 *
 * esc_sql() on each value did prevent injection, but an escaped string
 * pasted into SQL is not a prepared statement: the escaping and the query
 * are separated by whatever edits either of them later. Building the
 * placeholder list from the array and letting prepare() bind the values
 * keeps them together.
 */
$tbd_total_posts = 0;

/*
 * Guarded, because an empty list is reachable: a site that unticks every
 * post type gets array() back. The old concatenated version degraded to
 * IN ('') and quietly returned nought; a placeholder list would degrade to
 * IN () instead, which is a syntax error. Not running the query at all is
 * the honest version of "no post types, no posts".
 */
if ( ! empty( $tbd_enabled_types ) ) {
	$tbd_type_slots = implode( ', ', array_fill( 0, count( $tbd_enabled_types ), '%s' ) );

	/*
	 * phpcs cannot see a placeholder it did not read in the string literal,
	 * so a list of them built at runtime reads to it as an unprepared
	 * query. The list contains nothing but placeholders; every value is
	 * bound by prepare(). This is the limitation behind core ticket 54042,
	 * linked from the review notes.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$tbd_total_posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$tbd_type_slots})", $tbd_enabled_types ) );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$tbd_reviewed_posts = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value != '' AND p.post_status = 'publish'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'_trustbio_review_date'
	)
);

$tbd_stale_months = (int) get_option( 'trustbio_review_stale_months', 12 );
$tbd_stale_date   = gmdate( 'Y-m-d', strtotime( "-{$tbd_stale_months} months" ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$tbd_overdue = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value != '' AND pm.meta_value < %s AND p.post_status = 'publish'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'_trustbio_review_date',
		$tbd_stale_date
	)
);

$tbd_pct = $tbd_total_posts > 0 ? round( ( $tbd_reviewed_posts / $tbd_total_posts ) * 100 ) : 0;

// ── Recent reviewed posts ──────────────────────────────────────────────────
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$tbd_recent = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT p.ID, p.post_title, pm.meta_value AS review_date, pm2.meta_value AS reviewer_id
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} pm  ON p.ID = pm.post_id  AND pm.meta_key  = %s
		 LEFT  JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
		 WHERE p.post_status = 'publish' AND pm.meta_value != ''
		 ORDER BY pm.meta_value DESC
		 LIMIT 6", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'_trustbio_review_date',
		'_trustbio_reviewer_id'
	),
	ARRAY_A
);

// Pre-fetch all reviewer names used in the "Recently Reviewed" list — one query instead of N.
$tbd_reviewer_map = array();
$tbd_reviewer_ids = array_filter( array_unique( array_column( (array) $tbd_recent, 'reviewer_id' ) ) );
if ( ! empty( $tbd_reviewer_ids ) ) {
	// One %d placeholder per id, with the ids passed as arguments.
	$tbd_rev_ids   = array_map( 'intval', $tbd_reviewer_ids );
	$tbd_rev_slots = implode( ', ', array_fill( 0, count( $tbd_rev_ids ), '%d' ) );

	// See the note above on the runtime-built placeholder list.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$tbd_reviewer_rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, display_name FROM {$wpdb->prefix}trustbio_authors WHERE id IN ({$tbd_rev_slots})", $tbd_rev_ids ) );
	foreach ( $tbd_reviewer_rows as $tbd_rev_row ) {
		$tbd_reviewer_map[ (int) $tbd_rev_row->id ] = $tbd_rev_row->display_name;
	}
}

// ── Setup checklist state ──────────────────────────────────────────────────
$tbd_checks = array(
	'category'   => $tbd_categories > 0,
	'author'     => $tbd_authors > 0,
	'box'        => $tbd_boxes > 0,
	'schema'     => (bool) get_option( 'trustbio_enable_schema', 1 ),
	'review_bar' => $tbd_rev_bars > 0,
);
$tbd_all_done = ! in_array( false, $tbd_checks, true );

// ── Progress bar colour ────────────────────────────────────────────────────
$tbd_bar_class = $tbd_pct >= 80 ? '' : ( $tbd_pct >= 40 ? 'tbd-progress-bar__fill--warn' : 'tbd-progress-bar__fill--empty' );

// ── Quick URL helpers ──────────────────────────────────────────────────────
$tbd_url_authors  = admin_url( 'admin.php?page=trustbio-authors' );
$tbd_url_cats     = admin_url( 'admin.php?page=trustbio-categories' );
$tbd_url_boxes    = admin_url( 'admin.php?page=trustbio-boxes' );
$tbd_url_bars     = admin_url( 'admin.php?page=trustbio-review-bars' );
$tbd_url_settings = admin_url( 'admin.php?page=trustbio-settings' );
$tbd_url_new_box  = admin_url( 'admin.php?page=trustbio-boxes&action=new' );
$tbd_url_new_bar  = admin_url( 'admin.php?page=trustbio-review-bars&action=new' );

?>
<div class="trustbio-admin-wrap">

	<!-- Page header -->
	<div class="tb-page-header">
		<div class="tb-page-header__left">
			<div class="tb-page-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zm5.99 7.176A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/></svg>
			</div>
			<div class="tb-page-header__text">
				<h1 class="tb-page-title"><?php esc_html_e( 'Overview', 'trustbio' ); ?></h1>
				<p class="tb-page-subtitle"><?php esc_html_e( 'Your TrustBio E-E-A-T command centre', 'trustbio' ); ?></p>
			</div>
		</div>
		<div class="tb-page-header__actions">
			<button type="button" id="tbwz-open-btn" class="tb-btn tb-btn-secondary tb-btn-sm">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Setup Guide', 'trustbio' ); ?>
			</button>
			<a href="<?php echo esc_url( $tbd_url_settings . '#shortcodes' ); ?>" class="tb-btn tb-btn-secondary tb-btn-sm">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Shortcodes', 'trustbio' ); ?>
			</a>
			<a href="<?php echo esc_url( $tbd_url_settings ); ?>" class="tb-btn tb-btn-secondary tb-btn-sm">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
				<?php esc_html_e( 'Settings', 'trustbio' ); ?>
			</a>
		</div>
	</div>

	<div class="tb-notice-area" id="tb-notice-area"></div>

	<!-- Stats row -->
	<div class="tbd-stats-row">

		<a href="<?php echo esc_url( $tbd_url_authors ); ?>" class="tbd-stat">
			<div class="tbd-stat__icon tbd-stat__icon--indigo">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
			</div>
			<div class="tbd-stat__body">
				<div class="tbd-stat__number"><?php echo esc_html( $tbd_authors ); ?></div>
				<div class="tbd-stat__label"><?php esc_html_e( 'Authors', 'trustbio' ); ?></div>
			</div>
		</a>

		<a href="<?php echo esc_url( $tbd_url_cats ); ?>" class="tbd-stat">
			<div class="tbd-stat__icon tbd-stat__icon--violet">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/></svg>
			</div>
			<div class="tbd-stat__body">
				<div class="tbd-stat__number"><?php echo esc_html( $tbd_categories ); ?></div>
				<div class="tbd-stat__label"><?php esc_html_e( 'Categories', 'trustbio' ); ?></div>
			</div>
		</a>

		<a href="<?php echo esc_url( $tbd_url_boxes ); ?>" class="tbd-stat">
			<div class="tbd-stat__icon tbd-stat__icon--cyan">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z"/></svg>
			</div>
			<div class="tbd-stat__body">
				<div class="tbd-stat__number"><?php echo esc_html( $tbd_boxes ); ?></div>
				<div class="tbd-stat__label"><?php esc_html_e( 'Author Boxes', 'trustbio' ); ?></div>
			</div>
		</a>

		<?php if ( trustbio_feature_enabled( 'review_bars' ) ) : ?>
		<a href="<?php echo esc_url( $tbd_url_bars ); ?>" class="tbd-stat">
			<div class="tbd-stat__icon tbd-stat__icon--orange">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
			</div>
			<div class="tbd-stat__body">
				<div class="tbd-stat__number"><?php echo esc_html( $tbd_rev_bars ); ?></div>
				<div class="tbd-stat__label"><?php esc_html_e( 'Review Bars', 'trustbio' ); ?></div>
			</div>
		</a>
		<?php endif; ?>

	</div><!-- /.tbd-stats-row -->

	<!-- Main content row -->
	<div class="tbd-main-row">

		<!-- Left: Content Health + Recent Reviews -->
		<?php $tbd_review_on = trustbio_feature_enabled( 'review_bars' ); ?>
		<div class="tbd-panel">
			<div class="tbd-panel__header">
				<h2 class="tbd-panel__title">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
					<?php esc_html_e( 'Content Health', 'trustbio' ); ?>
				</h2>
				<?php if ( $tbd_review_on && $tbd_reviewed_posts > 0 && trustbio_feature_enabled( 'trust_gap' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-content-health' ) ); ?>" class="tbd-panel__action"><?php esc_html_e( 'View all posts →', 'trustbio' ); ?></a>
				<?php endif; ?>
			</div>
			<div class="tbd-panel__body">
				<?php if ( ! $tbd_review_on ) : ?>
				<div class="tbd-disabled-state">
					<div class="tbd-disabled-state__icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg></div>
					<div class="tbd-disabled-state__label"><?php esc_html_e( 'Last Review Bars', 'trustbio' ); ?></div>
					<div class="tbd-disabled-state__desc"><?php esc_html_e( 'Enable to track content review dates and monitor freshness across your posts.', 'trustbio' ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-features' ) ); ?>" class="tbd-disabled-state__btn"><?php esc_html_e( 'Enable Module →', 'trustbio' ); ?></a>
				</div>
				<?php else : ?>

				<?php if ( $tbd_total_posts > 0 ) : ?>
				<div class="tbd-health-numbers">
					<span class="tbd-health-numbers__big"><?php echo esc_html( $tbd_reviewed_posts ); ?></span>
					<span class="tbd-health-numbers__sep">/</span>
					<span class="tbd-health-numbers__total"><?php echo esc_html( $tbd_total_posts ); ?></span>
					<span class="tbd-health-numbers__label"><?php esc_html_e( 'posts reviewed', 'trustbio' ); ?></span>
				</div>
				<div class="tbd-progress-bar">
					<div class="tbd-progress-bar__fill <?php echo esc_attr( $tbd_bar_class ); ?>" style="width:<?php echo esc_attr( $tbd_pct ); ?>%"></div>
				</div>
				<div class="tbd-health-meta">
					<span><?php echo esc_html( $tbd_pct ); ?>% <?php esc_html_e( 'reviewed', 'trustbio' ); ?></span>
					<?php if ( $tbd_overdue > 0 ) : ?>
					<span class="tbd-overdue-pill">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
						<?php echo esc_html( $tbd_overdue ); ?> <?php esc_html_e( 'overdue', 'trustbio' ); ?>
					</span>
					<?php else : ?>
					<span class="tbd-up-to-date-pill">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'All up to date', 'trustbio' ); ?>
					</span>
					<?php endif; ?>
				</div>
				<?php else : ?>
				<p style="font-size:13px;color:var(--tb-gray-400);margin-bottom:18px;"><?php esc_html_e( 'No published posts found. Content health tracking will appear once you publish posts.', 'trustbio' ); ?></p>
				<?php endif; ?>

				<!-- Recent Reviews -->
				<p class="tbd-section-label"><?php esc_html_e( 'Recently Reviewed', 'trustbio' ); ?></p>
				<?php if ( ! empty( $tbd_recent ) ) : ?>
				<ul class="tbd-reviews-list">
					<?php foreach ( $tbd_recent as $tbd_item ) : ?>
					<?php
					$tbd_reviewer_name = ! empty( $tbd_item['reviewer_id'] )
						? ( $tbd_reviewer_map[ (int) $tbd_item['reviewer_id'] ] ?? '' )
						: '';
					$tbd_review_dt = ! empty( $tbd_item['review_date'] ) ? strtotime( $tbd_item['review_date'] ) : 0;
					$tbd_ago       = $tbd_review_dt ? human_time_diff( $tbd_review_dt, time() ) . ' ' . __( 'ago', 'trustbio' ) : '';
					?>
					<li class="tbd-reviews-list__item">
						<span class="tbd-reviews-list__dot"></span>
						<div class="tbd-reviews-list__body">
							<a href="<?php echo esc_url( get_edit_post_link( (int) $tbd_item['ID'] ) ); ?>" class="tbd-reviews-list__title" title="<?php echo esc_attr( $tbd_item['post_title'] ); ?>">
								<?php echo esc_html( wp_trim_words( $tbd_item['post_title'], 8, '…' ) ); ?>
							</a>
							<?php if ( $tbd_reviewer_name ) : ?>
							<div class="tbd-reviews-list__meta"><?php esc_html_e( 'by', 'trustbio' ); ?> <?php echo esc_html( $tbd_reviewer_name ); ?></div>
							<?php endif; ?>
						</div>
						<span class="tbd-reviews-list__date"><?php echo esc_html( $tbd_ago ); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php else : ?>
				<div class="tbd-empty-state">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
					<span><?php esc_html_e( 'No reviewed posts yet. Open any post and set a review date.', 'trustbio' ); ?></span>
				</div>
				<?php endif; ?>

				<?php endif; ?>
			</div>
		</div><!-- /.tbd-panel -->

		<!-- Right: Quick Actions + Setup Checklist -->
		<div class="tbd-sidebar-stack">

			<!-- Quick Actions -->
			<div class="tbd-panel">
				<div class="tbd-panel__header">
					<h2 class="tbd-panel__title">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Quick Actions', 'trustbio' ); ?>
					</h2>
				</div>
				<div class="tbd-panel__body">
					<div class="tbd-actions-list">

						<a href="<?php echo esc_url( $tbd_url_authors . '&action=new' ); ?>" class="tbd-action-btn">
							<span class="tbd-action-btn__icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6z"/><path d="M16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/></svg></span>
							<span><?php esc_html_e( 'Add New Author', 'trustbio' ); ?></span>
						</a>

						<a href="<?php echo esc_url( $tbd_url_new_box ); ?>" class="tbd-action-btn">
							<span class="tbd-action-btn__icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486z"/></svg></span>
							<span><?php esc_html_e( 'New Author Box', 'trustbio' ); ?></span>
						</a>

						<?php if ( trustbio_feature_enabled( 'review_bars' ) ) : ?>
						<a href="<?php echo esc_url( $tbd_url_new_bar ); ?>" class="tbd-action-btn">
							<span class="tbd-action-btn__icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg></span>
							<span><?php esc_html_e( 'New Review Bar', 'trustbio' ); ?></span>
						</a>
						<?php endif; ?>

						<a href="<?php echo esc_url( $tbd_url_settings . '#maintenance' ); ?>" class="tbd-action-btn">
							<span class="tbd-action-btn__icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></span>
							<span><?php esc_html_e( 'Export / Import Data', 'trustbio' ); ?></span>
						</a>

					</div>
				</div>
			</div>

			<!-- Setup Checklist -->
			<div class="tbd-panel">
				<div class="tbd-panel__header">
					<h2 class="tbd-panel__title">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Setup Checklist', 'trustbio' ); ?>
					</h2>
					<?php if ( $tbd_all_done ) : ?>
					<span class="tb-badge tb-badge-success" style="font-size:10px;"><?php esc_html_e( 'Complete', 'trustbio' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="tbd-panel__body">
					<?php
					$tbd_checklist_items = array(
						array(
							'done'  => $tbd_checks['category'],
							'label' => __( 'Create an author category', 'trustbio' ),
							'url'   => $tbd_url_cats,
							'cta'   => __( 'Create', 'trustbio' ),
						),
						array(
							'done'  => $tbd_checks['author'],
							'label' => __( 'Add your first author', 'trustbio' ),
							'url'   => $tbd_url_authors,
							'cta'   => __( 'Add', 'trustbio' ),
						),
						array(
							'done'  => $tbd_checks['box'],
							'label' => __( 'Design an author box', 'trustbio' ),
							'url'   => $tbd_url_new_box,
							'cta'   => __( 'Design', 'trustbio' ),
						),
						array(
							'done'  => $tbd_checks['schema'],
							'label' => __( 'Schema output enabled', 'trustbio' ),
							'url'   => $tbd_url_settings . '#advanced',
							'cta'   => __( 'Enable', 'trustbio' ),
						),
						array(
							'done'  => $tbd_checks['review_bar'],
							'label' => __( 'Create a last-review bar', 'trustbio' ),
							'url'   => $tbd_url_new_bar,
							'cta'   => __( 'Create', 'trustbio' ),
						),
					);
					?>
					<ul class="tbd-checklist">
						<?php foreach ( $tbd_checklist_items as $tbd_ci ) : ?>
						<li class="tbd-checklist__item <?php echo $tbd_ci['done'] ? 'tbd-checklist__item--done' : ''; ?>">
							<span class="tbd-check-icon <?php echo $tbd_ci['done'] ? 'tbd-check-icon--done' : 'tbd-check-icon--todo'; ?>">
								<?php if ( $tbd_ci['done'] ) : ?>
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
								<?php else : ?>
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v4a1 1 0 102 0V7zm-1 7a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
								<?php endif; ?>
							</span>
							<span class="tbd-checklist__label"><?php echo esc_html( $tbd_ci['label'] ); ?></span>
							<?php if ( ! $tbd_ci['done'] ) : ?>
							<a href="<?php echo esc_url( $tbd_ci['url'] ); ?>" class="tbd-checklist__link"><?php echo esc_html( $tbd_ci['cta'] ); ?> →</a>
							<?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<?php $tbd_trust_gap_on = trustbio_feature_enabled( 'trust_gap' ); ?>
		</div><!-- /.tbd-sidebar-stack -->

	</div><!-- /.tbd-main-row -->

	<div class="tbd-panels-row">
	<?php
	/**
	 * Fires at the bottom of the TrustBio Overview screen.
	 *
	 * The TrustBio Pro add-on renders its own panels here.
	 *
	 * The wrapper is not decoration: the hook used to fire straight into
	 * .trustbio-admin-wrap, so anything an add-on rendered sat 24px to the
	 * left of every row above it. The row carries the gutter, so a panel
	 * only has to render itself.
	 *
	 * @since 1.0.0
	 */
	do_action( 'trustbio_dashboard_panels' );

	?>
	</div><!-- /.tbd-panels-row -->

</div><!-- /.trustbio-admin-wrap -->

<!-- ====================================================== -->
<!-- ONBOARDING WIZARD MODAL                                -->
<!-- ====================================================== -->
<div id="tbwz-overlay" class="tbwz-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="tbwz-title">
	<div class="tbwz-card">

		<!-- Step progress header -->
		<div class="tbwz-header">
			<div class="tbwz-steps">
				<div class="tbwz-step is-active" data-step="1">
					<div class="tbwz-step__dot">1</div>
					<span class="tbwz-step__label"><?php esc_html_e( 'Category', 'trustbio' ); ?></span>
				</div>
				<div class="tbwz-step-line" data-after="1"></div>
				<div class="tbwz-step" data-step="2">
					<div class="tbwz-step__dot">2</div>
					<span class="tbwz-step__label"><?php esc_html_e( 'Author', 'trustbio' ); ?></span>
				</div>
				<div class="tbwz-step-line" data-after="2"></div>
				<div class="tbwz-step" data-step="3">
					<div class="tbwz-step__dot">3</div>
					<span class="tbwz-step__label"><?php esc_html_e( 'Author Box', 'trustbio' ); ?></span>
				</div>
				<div class="tbwz-step-line" data-after="3"></div>
				<div class="tbwz-step" data-step="4">
					<div class="tbwz-step__dot">4</div>
					<span class="tbwz-step__label"><?php esc_html_e( 'Go Live', 'trustbio' ); ?></span>
				</div>
			</div>
			<button type="button" class="tbwz-close" id="tbwz-close" aria-label="<?php esc_attr_e( 'Close', 'trustbio' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
			</button>
		</div>

		<div class="tbwz-body">

			<!-- Step 1: Create Category -->
			<div class="tbwz-pane is-active" data-pane="1">
				<div class="tbwz-pane__icon tbwz-pane__icon--violet">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z"/></svg>
				</div>
				<h2 class="tbwz-pane__title" id="tbwz-title"><?php esc_html_e( 'Create an Author Category', 'trustbio' ); ?></h2>
				<p class="tbwz-pane__text"><?php esc_html_e( 'Categories define author roles — e.g. "Medical Reviewer", "Expert Author", or "Editor".', 'trustbio' ); ?></p>
				<div class="tbwz-form">
					<div class="tbwz-form-group">
						<label for="tbwz-cat-singular"><?php esc_html_e( 'Category Name', 'trustbio' ); ?> <span>*</span></label>
						<input type="text" id="tbwz-cat-singular" placeholder="<?php esc_attr_e( 'e.g. Medical Reviewer', 'trustbio' ); ?>">
					</div>
					<div class="tbwz-form-group">
						<label for="tbwz-cat-label"><?php esc_html_e( 'Display Label', 'trustbio' ); ?></label>
						<input type="text" id="tbwz-cat-label" placeholder="<?php esc_attr_e( 'e.g. Medically reviewed by', 'trustbio' ); ?>">
						<span class="tbwz-hint"><?php esc_html_e( 'Shown before the author\'s name on post pages.', 'trustbio' ); ?></span>
					</div>
				</div>
				<div class="tbwz-error" id="tbwz-step1-error" style="display:none;"></div>
			</div>

			<!-- Step 2: Add Author -->
			<div class="tbwz-pane" data-pane="2">
				<div class="tbwz-pane__icon tbwz-pane__icon--indigo">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
				</div>
				<h2 class="tbwz-pane__title"><?php esc_html_e( 'Add Your First Author', 'trustbio' ); ?></h2>
				<p class="tbwz-pane__text"><?php esc_html_e( 'Add an expert author or reviewer. You can add more anytime from the Authors page.', 'trustbio' ); ?></p>
				<div class="tbwz-form">
					<div class="tbwz-form-group">
						<label for="tbwz-author-name"><?php esc_html_e( 'Author Name', 'trustbio' ); ?> <span>*</span></label>
						<input type="text" id="tbwz-author-name" placeholder="<?php esc_attr_e( 'e.g. Dr. Jane Smith', 'trustbio' ); ?>">
					</div>
					<div class="tbwz-form-group">
						<label for="tbwz-author-job"><?php esc_html_e( 'Job Title', 'trustbio' ); ?></label>
						<input type="text" id="tbwz-author-job" placeholder="<?php esc_attr_e( 'e.g. MD, Internal Medicine', 'trustbio' ); ?>">
					</div>
					<div class="tbwz-form-group">
						<label for="tbwz-author-email"><?php esc_html_e( 'Email', 'trustbio' ); ?></label>
						<input type="email" id="tbwz-author-email" placeholder="<?php esc_attr_e( 'author@example.com', 'trustbio' ); ?>">
					</div>
				</div>
				<div class="tbwz-error" id="tbwz-step2-error" style="display:none;"></div>
			</div>

			<!-- Step 3: Choose Author Box Layout -->
			<div class="tbwz-pane" data-pane="3">
				<div class="tbwz-pane__icon tbwz-pane__icon--cyan">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
				</div>
				<h2 class="tbwz-pane__title"><?php esc_html_e( 'Choose an Author Box Layout', 'trustbio' ); ?></h2>
				<p class="tbwz-pane__text"><?php esc_html_e( 'Pick a starting style — every color and setting can be customized after.', 'trustbio' ); ?></p>
				<div class="tbwz-layout-grid">
					<button type="button" class="tbwz-layout-card is-selected" data-layout="byline">
						<div class="tbwz-layout-card__visual">
							<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;display:flex;gap:8px;align-items:flex-start;width:100%;">
								<div style="width:22px;height:22px;border-radius:50%;background:#c7d2fe;flex-shrink:0;margin-top:2px;"></div>
								<div style="flex:1;display:flex;flex-direction:column;gap:3px;">
									<div style="height:4px;background:#818cf8;border-radius:2px;width:60%;"></div>
									<div style="height:3px;background:#c7d2fe;border-radius:2px;width:80%;"></div>
									<div style="height:3px;background:#e0e7ff;border-radius:2px;"></div>
								</div>
							</div>
						</div>
						<div class="tbwz-layout-card__label"><?php esc_html_e( 'Classic Byline', 'trustbio' ); ?></div>
						<div class="tbwz-layout-card__desc"><?php esc_html_e( 'Avatar · name · bio · socials', 'trustbio' ); ?></div>
					</button>
					<button type="button" class="tbwz-layout-card" data-layout="minimal-inline">
						<div class="tbwz-layout-card__visual">
							<div style="border-top:2px solid #e5e7eb;border-bottom:2px solid #e5e7eb;padding:8px 10px;display:flex;gap:8px;align-items:center;width:100%;box-sizing:border-box;">
								<div style="width:20px;height:20px;border-radius:50%;background:#f3f4f6;flex-shrink:0;"></div>
								<div style="flex:1;display:flex;flex-direction:column;gap:3px;">
									<div style="height:3.5px;background:#374151;border-radius:2px;width:50%;"></div>
									<div style="height:3px;background:#d1d5db;border-radius:2px;width:70%;"></div>
								</div>
							</div>
						</div>
						<div class="tbwz-layout-card__label"><?php esc_html_e( 'Minimal Inline', 'trustbio' ); ?></div>
						<div class="tbwz-layout-card__desc"><?php esc_html_e( 'Clean editorial style', 'trustbio' ); ?></div>
					</button>
					<button type="button" class="tbwz-layout-card" data-layout="quote">
						<div class="tbwz-layout-card__visual">
							<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px;width:100%;">
								<div style="height:4px;background:#c7d2fe;border-radius:2px;width:22%;margin-bottom:8px;"></div>
								<div style="height:4px;background:#e0e7ff;border-radius:2px;margin-bottom:4px;"></div>
								<div style="height:4px;background:#e0e7ff;border-radius:2px;width:72%;margin-bottom:10px;"></div>
								<div style="display:flex;align-items:center;gap:8px;">
									<div style="width:20px;height:20px;border-radius:50%;background:#c7d2fe;"></div>
									<div style="height:4px;background:#818cf8;border-radius:2px;width:38%;"></div>
								</div>
							</div>
						</div>
						<div class="tbwz-layout-card__label"><?php esc_html_e( 'Quote Card', 'trustbio' ); ?></div>
						<div class="tbwz-layout-card__desc"><?php esc_html_e( 'Bio as a pull-quote', 'trustbio' ); ?></div>
					</button>
					<button type="button" class="tbwz-layout-card" data-layout="simple">
						<div class="tbwz-layout-card__visual">
							<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;display:flex;gap:8px;align-items:center;width:100%;box-sizing:border-box;">
								<div style="width:24px;height:24px;border-radius:4px;background:#dbeafe;flex-shrink:0;"></div>
								<div style="width:1px;height:30px;background:#e5e7eb;flex-shrink:0;"></div>
								<div style="flex:1;display:flex;flex-direction:column;gap:3px;">
									<div style="height:4px;background:#2563eb;border-radius:2px;width:50%;"></div>
									<div style="height:3px;background:#bfdbfe;border-radius:2px;"></div>
								</div>
							</div>
						</div>
						<div class="tbwz-layout-card__label"><?php esc_html_e( 'Card Simple', 'trustbio' ); ?></div>
						<div class="tbwz-layout-card__desc"><?php esc_html_e( 'Square avatar, divider', 'trustbio' ); ?></div>
					</button>
				</div>
				<div class="tbwz-error" id="tbwz-step3-error" style="display:none;"></div>
			</div>

			<!-- Step 4: Go Live / Summary -->
			<div class="tbwz-pane" data-pane="4">
				<div class="tbwz-success-icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
				</div>
				<h2 class="tbwz-pane__title"><?php esc_html_e( 'Your E-E-A-T setup is ready!', 'trustbio' ); ?></h2>
				<p class="tbwz-pane__text"><?php esc_html_e( 'Here\'s what was created. You can edit anything from the TrustBio menu.', 'trustbio' ); ?></p>
				<div class="tbwz-summary">
					<div class="tbwz-summary-item" id="tbwz-sum-cat" style="display:none;"></div>
					<div class="tbwz-summary-item" id="tbwz-sum-author" style="display:none;"></div>
					<div class="tbwz-summary-item" id="tbwz-sum-box" style="display:none;"></div>
				</div>
				<div class="tbwz-finish-links">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-authors' ) ); ?>" class="tbwz-finish-link">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Manage Authors', 'trustbio' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-boxes' ) ); ?>" class="tbwz-finish-link">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Customize Box', 'trustbio' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trustbio-settings' ) ); ?>" class="tbwz-finish-link">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Settings', 'trustbio' ); ?>
					</a>
				</div>
			</div>

		</div><!-- /.tbwz-body -->

		<!-- Wizard footer / navigation -->
		<div class="tbwz-footer">
			<button type="button" id="tbwz-btn-back" class="tbwz-btn-back" style="visibility:hidden;">
				← <?php esc_html_e( 'Back', 'trustbio' ); ?>
			</button>
			<div class="tbwz-footer-right">
				<button type="button" id="tbwz-btn-skip" class="tbwz-btn-skip"><?php esc_html_e( 'Skip this step', 'trustbio' ); ?></button>
				<button type="button" id="tbwz-btn-next" class="tbwz-btn-primary">
					<?php esc_html_e( 'Continue', 'trustbio' ); ?>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
				</button>
				<button type="button" id="tbwz-btn-finish" class="tbwz-btn-primary" style="display:none;">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
					<?php esc_html_e( 'Done', 'trustbio' ); ?>
				</button>
			</div>
		</div>

	</div><!-- /.tbwz-card -->
</div><!-- /#tbwz-overlay -->
