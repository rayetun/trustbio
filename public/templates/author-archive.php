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

/*
 * Social links.
 *
 * Social-ness is the is_social column on the field, which is what
 * trustbio_get_fields( true ) reads. This template filtered on
 * af.field_type = 'social' instead -- a type no field has ever had, the
 * types being 'link' and 'text' -- so the query matched nothing and the
 * archive rendered no social links at all. Going through the helper also
 * means the trustbio_social_fields filter applies here, as it already did
 * in the author box.
 */
$trustbio_social_fields = array();
$trustbio_field_values  = trustbio_get_author_field_values( (int) $trustbio_author->id );
foreach ( trustbio_get_fields( true ) as $trustbio_field ) {
	$trustbio_slug = (string) ( $trustbio_field->slug ?? '' );
	if ( '' === $trustbio_slug || empty( $trustbio_field_values[ $trustbio_slug ] ) ) {
		continue;
	}
	$trustbio_social_fields[ $trustbio_slug ] = $trustbio_field_values[ $trustbio_slug ];
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

// Social icons come from trustbio_get_social_svg(), the same set the author
// box draws, so the two cannot drift apart again.

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
							$trustbio_href = trustbio_profile_url( $trustbio_slug, (string) $trustbio_val );
							if ( '' === $trustbio_href ) {
								continue;
							}
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
								<?php
								echo trustbio_get_social_svg( $trustbio_slug, 15, 'currentColor' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded SVG path data, attributes escaped in trustbio_get_social_svg()
								?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			</div><!-- .tba-hero__identity -->
		</div><!-- .tba-hero__inner -->
	</div><!-- .tba-hero -->

	<?php
	/**
	 * Fires on the author archive between the hero and the post list.
	 *
	 * The place for an add-on to put a block of its own about this
	 * author. Before this existed the only way in was to buffer the
	 * whole template and splice markup into it by string match, which
	 * broke silently the moment a theme overrode the template.
	 *
	 * @since 1.0.1
	 * @param object $trustbio_author The author being shown, all DB columns.
	 */
	do_action( 'trustbio_archive_after_hero', $trustbio_author );
	?>

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
