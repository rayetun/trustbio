<?php
/**
 * Template 01 — Classic Byline Bar
 *
 * @package TrustBio
 * @var object      $author      Author data object.
 * @var object|null $category    Author category object.
 * @var array       $settings    Box settings array.
 * @var string      $social_html Rendered social icon links.
 * @var string      $author_url  Author archive URL.
 * @var string      $avatar_url  Avatar image URL.
 * @var int         $post_id     Current post ID (0 when used via shortcode).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Apply bio character limit.
$trustbio_bio_display = trustbio_apply_bio_limit( $author->bio ?? '', (int) ( $settings['bio_limit'] ?? 0 ) );

// Kicker: use the author's category label, or job title, or fall back to "Written by".
$trustbio_kicker = '';
if ( $category ) {
	$trustbio_kicker = $category->display_label ?: $category->singular;
} elseif ( ! empty( $author->job_title ) ) {
	$trustbio_kicker = $author->job_title;
} else {
	$trustbio_kicker = __( 'Written by', 'trustbio' );
}

// Post count for meta.
$trustbio_post_count = '';
if ( ! empty( $author->wp_user_id ) ) {
	$trustbio_post_count = (int) count_user_posts( $author->wp_user_id );
}

// Name & role colours from settings.
$trustbio_name_style = sprintf(
	'font-size:%dpx;font-weight:%s;color:%s;',
	(int) ( $settings['name_size'] ?? 28 ),
	esc_attr( $settings['name_weight'] ?? '700' ),
	esc_attr( $settings['name_color'] ?? '#15161a' )
);
$trustbio_bio_style = sprintf(
	'font-size:%dpx;color:%s;',
	(int) ( $settings['bio_size'] ?? 14 ),
	esc_attr( $settings['bio_color'] ?? '#36352f' )
);
$trustbio_wrapper_style = trustbio_build_wrapper_style( $settings );
?>
<div class="trustbio-box trustbio-layout-byline"<?php if ( $trustbio_wrapper_style ) echo ' style="' . esc_attr( $trustbio_wrapper_style ) . '"'; ?>>

	<img class="tb-avatar"
	     src="<?php echo esc_url( $avatar_url ); ?>"
	     alt="<?php echo esc_attr( $author->display_name ); ?>"
	     width="96" height="96">

	<div class="tb-body">
		<?php if ( $trustbio_kicker ) : ?>
		<div class="tb-kicker"><?php echo esc_html( $trustbio_kicker ); ?></div>
		<?php endif; ?>

		<h2 class="tb-name" style="<?php echo esc_attr( $trustbio_name_style ); ?>">
			<a href="<?php echo esc_url( $author_url ); ?>" rel="author">
				<?php echo esc_html( $author->display_name ); ?>
			</a>
		</h2>
		<?php if ( $trustbio_badges_html ) : ?>
		<div class="tb-credentials" style="display:flex;flex-wrap:wrap;gap:4px;margin:4px 0 6px;">
			<?php echo $trustbio_badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $trustbio_bio_display ) ) : ?>
		<p class="tb-bio" style="<?php echo esc_attr( $trustbio_bio_style ); ?>">
			<?php echo wp_kses( $trustbio_bio_display, array( 'em' => array(), 'strong' => array(), 'a' => array( 'href' => array() ) ) ); ?>
		</p>
		<?php endif; ?>
		<?php if ( $trustbio_review_html ) : ?>
			<?php echo $trustbio_review_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>

	<div class="tb-side">
		<?php if ( $trustbio_post_count ) : ?>
		<div class="tb-meta">
			<span><b><?php echo esc_html( number_format_i18n( $trustbio_post_count ) ); ?></b> <?php esc_html_e( 'posts', 'trustbio' ); ?></span>
		</div>
		<?php endif; ?>

		<?php if ( $social_html ) : ?>
		<div class="tb-socials">
			<?php echo $social_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php endif; ?>
	</div>

</div>
