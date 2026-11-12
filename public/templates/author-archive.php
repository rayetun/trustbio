<?php
/**
 * TrustBio — Default Author Archive Template
 *
 * Theme override hierarchy:
 *   1. {theme}/trustbio-author-{slug}.php
 *   2. {theme}/trustbio-author.php
 *   3. This file (plugin default)
 *
 * Available variables:
 *   $trustbio_author — TrustBio author object (all DB columns)
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- intentional template variable
$trustbio_author = $GLOBALS['trustbio_archive_author'] ?? null;
if ( ! $trustbio_author ) {
	wp_die( esc_html__( 'Author not found.', 'trustbio' ) );
}

$trustbio_avatar_url   = trustbio_get_avatar_url( $trustbio_author, 160 );
$trustbio_author_url   = trustbio_get_author_url( $trustbio_author );
$trustbio_per_page     = (int) get_option( 'trustbio_archive_per_page', 10 );
$trustbio_current_page = max( 1, get_query_var( 'paged', 1 ) );
$trustbio_query        = TrustBio_Author_Archive::get_author_posts(
	(int) $trustbio_author->id,
	$trustbio_current_page,
	$trustbio_per_page
);

// Gather field values for social links.
global $wpdb;
$trustbio_fv_table  = $wpdb->prefix . 'trustbio_author_field_values';
$trustbio_af_table  = $wpdb->prefix . 'trustbio_author_fields';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$trustbio_field_values_raw = $wpdb->get_results(
	$wpdb->prepare(
		'SELECT af.slug, af.field_type, afv.value FROM %i afv INNER JOIN %i af ON af.id = afv.field_id WHERE afv.author_id = %d AND af.field_type = %s AND afv.value != %s ORDER BY af.sort_order ASC',
		$trustbio_fv_table,
		$trustbio_af_table,
		(int) $trustbio_author->id,
		'social',
		''
	)
);
$trustbio_social_fields = array();
foreach ( (array) $trustbio_field_values_raw as $trustbio_fv ) {
	$trustbio_social_fields[ $trustbio_fv->slug ] = $trustbio_fv->value;
}

// Credential badges.
$trustbio_badges_html = TrustBio_Credentials::render_badges( (int) $trustbio_author->id );

// Category label — find the primary category this author is most commonly assigned as.
$trustbio_counts   = TrustBio_Contribution_Counter::get_counts( (int) $trustbio_author->id );
$trustbio_top_cat  = null;
if ( ! empty( $trustbio_counts ) ) {
	arsort( $trustbio_counts );
	$trustbio_top_cat_id = (int) array_key_first( $trustbio_counts );
	if ( $trustbio_top_cat_id > 0 ) {
		$trustbio_top_cat = trustbio_get_category( $trustbio_top_cat_id );
	}
}
$trustbio_role_label = $trustbio_top_cat
	? ( $trustbio_top_cat->display_label ?: $trustbio_top_cat->singular )
	: '';

// Social icon SVG paths — same set as the public box renderer.
$trustbio_social_icons = array(
	'website'        => '<path d="M10 .4C4.698.4.4 4.698.4 10s4.298 9.6 9.6 9.6 9.6-4.298 9.6-9.6S15.302.4 10 .4zm8.531 9.006l-1.021.328a6.548 6.548 0 00-.834-2.119l.719-.719a8.519 8.519 0 011.136 2.51zM10 16.6a6.6 6.6 0 110-13.2 6.6 6.6 0 010 13.2z"/>',
	'linkedin'       => '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>',
	'twitter'        => '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.213 5.567zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
	'instagram'      => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>',
	'facebook'       => '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
	'youtube'        => '<path d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/>',
	'tiktok'         => '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>',
	'pinterest'      => '<path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/>',
	'github'         => '<path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>',
	'medium'         => '<path d="M13.54 12a6.8 6.8 0 01-6.77 6.82A6.8 6.8 0 010 12a6.8 6.8 0 016.77-6.82A6.8 6.8 0 0113.54 12zm7.42 0c0 3.54-1.51 6.42-3.38 6.42-1.87 0-3.39-2.88-3.39-6.42s1.52-6.42 3.39-6.42 3.38 2.88 3.38 6.42M24 12c0 3.17-.53 5.75-1.19 5.75-.66 0-1.19-2.58-1.19-5.75s.53-5.75 1.19-5.75C23.47 6.25 24 8.83 24 12z"/>',
);

get_header();
?>

<div class="tba-page">

	<?php
	// ── HERO SECTION ─────────────────────────────────────────────
	?>
	<div class="tba-hero">
		<div class="tba-hero__inner">

			<?php // Avatar with floating credential badges. ?>
			<div class="tba-hero__avatar-wrap">
				<img
					src="<?php echo esc_url( $trustbio_avatar_url ); ?>"
					alt="<?php echo esc_attr( $trustbio_author->display_name ); ?>"
					class="tba-hero__avatar"
					width="120" height="120"
					loading="eager"
				>
				<?php if ( $trustbio_role_label ) : ?>
					<span class="tba-hero__role-pill"><?php echo esc_html( $trustbio_role_label ); ?></span>
				<?php endif; ?>
			</div>

			<?php // Main identity block. ?>
			<div class="tba-hero__identity">

				<h1 class="tba-hero__name">
					<?php echo esc_html( $trustbio_author->display_name ); ?>
					<?php if ( $trustbio_author->credentials ) : ?>
						<span class="tba-hero__credentials"><?php echo esc_html( $trustbio_author->credentials ); ?></span>
					<?php endif; ?>
				</h1>

				<?php if ( $trustbio_author->job_title ) : ?>
					<p class="tba-hero__job"><?php echo esc_html( $trustbio_author->job_title ); ?></p>
				<?php endif; ?>

				<?php // Credential badges (Phase 11). ?>
				<?php if ( $trustbio_badges_html ) : ?>
					<div class="tba-hero__badges">
						<?php echo $trustbio_badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_badges() ?>
					</div>
				<?php endif; ?>

				<?php // Contribution count stat (Phase 14). ?>
				<?php
				$trustbio_total = TrustBio_Contribution_Counter::get_total( (int) $trustbio_author->id );
				$trustbio_noun_plural = get_option( 'trustbio_contribution_noun_plural', __( 'articles', 'trustbio' ) );
				if ( $trustbio_total > 0 ) :
					$trustbio_verb = '';
					if ( $trustbio_top_cat && ! empty( $trustbio_top_cat->contribution_verb ) ) {
						$trustbio_verb = $trustbio_top_cat->contribution_verb;
					}
				?>
				<p class="tba-hero__stat">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
					<?php
					if ( $trustbio_verb ) {
						echo esc_html( $trustbio_verb ) . ' ';
					}
					echo esc_html( number_format_i18n( $trustbio_total ) ) . ' ' . esc_html( $trustbio_noun_plural );
					?>
				</p>
				<?php endif; ?>

				<?php // Bio. ?>
				<?php if ( $trustbio_author->bio ) : ?>
					<div class="tba-hero__bio">
						<?php echo wp_kses_post( $trustbio_author->bio ); ?>
					</div>
				<?php endif; ?>

				<?php // Social links. ?>
				<?php if ( ! empty( $trustbio_social_fields ) ) : ?>
					<div class="tba-hero__social">
						<?php foreach ( $trustbio_social_fields as $trustbio_slug => $trustbio_val ) : ?>
							<?php
							$trustbio_icon_path = $trustbio_social_icons[ $trustbio_slug ] ?? '';
							if ( ! $trustbio_val ) continue;
							$trustbio_href = filter_var( $trustbio_val, FILTER_VALIDATE_URL )
								? $trustbio_val
								: 'https://' . ltrim( $trustbio_val, '/' );
							$trustbio_label = ucfirst( str_replace( '_', ' ', $trustbio_slug ) );
							?>
							<a
								href="<?php echo esc_url( $trustbio_href ); ?>"
								class="tba-hero__social-link"
								target="_blank"
								rel="noopener noreferrer nofollow"
								aria-label="<?php echo esc_attr( $trustbio_label ); ?>"
								title="<?php echo esc_attr( $trustbio_label ); ?>"
							>
								<?php if ( $trustbio_icon_path ) : ?>
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
										<?php echo $trustbio_icon_path; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG path data ?>
									</svg>
								<?php else : ?>
									<?php echo esc_html( $trustbio_label ); ?>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			</div><!-- .tba-hero__identity -->
		</div><!-- .tba-hero__inner -->
	</div><!-- .tba-hero -->

	<?php
	// ── POSTS SECTION ────────────────────────────────────────────
	?>
	<div class="tba-posts">
		<div class="tba-posts__inner">

			<?php if ( $trustbio_query->have_posts() ) : ?>

				<?php
				// Use contribution_verb (e.g. "Written", "Reviewed") + "by {name}".
				// Falls back to "Articles by {name}" when no verb is set.
				$trustbio_heading_verb = ( $trustbio_top_cat && ! empty( $trustbio_top_cat->contribution_verb ) )
					? $trustbio_top_cat->contribution_verb  // e.g. "Written", "Reviewed"
					: __( 'Articles', 'trustbio' );

				$trustbio_section_label = sprintf(
					/* translators: 1: verb e.g. "Written"  2: author name */
					__( '%1$s by %2$s', 'trustbio' ),
					$trustbio_heading_verb,
					$trustbio_author->display_name
				);
				?>

				<h2 class="tba-posts__heading"><?php echo esc_html( $trustbio_section_label ); ?></h2>

				<div class="tba-grid">
					<?php while ( $trustbio_query->have_posts() ) : $trustbio_query->the_post(); ?>

						<article class="tba-card" id="post-<?php the_ID(); ?>">

							<?php // Featured image. ?>
							<?php if ( has_post_thumbnail() ) : ?>
								<a href="<?php the_permalink(); ?>" class="tba-card__thumb" tabindex="-1" aria-hidden="true">
									<?php the_post_thumbnail( 'medium_large', array(
										'class'   => 'tba-card__img',
										'loading' => 'lazy',
										'alt'     => get_the_title(),
									) ); ?>
								</a>
							<?php else : ?>
								<div class="tba-card__thumb tba-card__thumb--placeholder" aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9l4-4 4 4 4-6 4 4"/><circle cx="8.5" cy="13.5" r="1.5"/></svg>
								</div>
							<?php endif; ?>

							<div class="tba-card__body">

								<?php // Category pill. ?>
								<?php
								$trustbio_cats = get_the_category();
								if ( ! empty( $trustbio_cats ) ) :
								?>
									<span class="tba-card__cat"><?php echo esc_html( $trustbio_cats[0]->name ); ?></span>
								<?php endif; ?>

								<?php // Title. ?>
								<h3 class="tba-card__title">
									<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
								</h3>

								<?php // Excerpt. ?>
								<?php if ( has_excerpt() || get_the_excerpt() ) : ?>
									<p class="tba-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18, '…' ) ); ?></p>
								<?php endif; ?>

								<?php // Footer: date + read time. ?>
								<div class="tba-card__footer">
									<time class="tba-card__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
										<?php echo esc_html( get_the_date() ); ?>
									</time>
									<?php
									$trustbio_words    = str_word_count( wp_strip_all_tags( get_the_content() ) );
									$trustbio_read_min = max( 1, (int) ceil( $trustbio_words / 200 ) );
									?>
									<span class="tba-card__read-time">
										<?php
										/* translators: %d: estimated minutes to read */
										echo esc_html( sprintf( _n( '%d min read', '%d min read', $trustbio_read_min, 'trustbio' ), $trustbio_read_min ) );
										?>
									</span>
								</div>

							</div><!-- .tba-card__body -->
						</article><!-- .tba-card -->

					<?php endwhile; ?>
				</div><!-- .tba-grid -->

				<?php
				// ── PAGINATION ────────────────────────────────────────
				$trustbio_big = 999999999;
				echo wp_kses_post( paginate_links( array(
					'base'      => str_replace( $trustbio_big, '%#%', esc_url( get_pagenum_link( $trustbio_big ) ) ),
					'format'    => '?paged=%#%',
					'current'   => max( 1, get_query_var( 'paged' ) ),
					'total'     => $trustbio_query->max_num_pages,
					'prev_text' => '&larr; ' . __( 'Older', 'trustbio' ),
					'next_text' => __( 'Newer', 'trustbio' ) . ' &rarr;',
					'type'      => 'list',
					'before_page_number' => '<span class="screen-reader-text">' . __( 'Page', 'trustbio' ) . ' </span>',
				) ) );
				?>

			<?php else : ?>

				<div class="tba-empty">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
						<rect x="8" y="12" width="48" height="40" rx="4"/>
						<path d="M8 22h48M22 12v10M42 12v10"/>
					</svg>
					<p><?php esc_html_e( 'No published posts yet.', 'trustbio' ); ?></p>
				</div>

			<?php endif; ?>

			<?php wp_reset_postdata(); ?>

		</div><!-- .tba-posts__inner -->
	</div><!-- .tba-posts -->

</div><!-- .tba-page -->

<?php get_footer(); ?>
