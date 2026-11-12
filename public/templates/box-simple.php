<?php
/**
 * Template 07 — Card Simple
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

// Apply bio character limit, then cap at 120 chars for single-line display.
$trustbio_bio_display = trustbio_apply_bio_limit( $author->bio ?? '', (int) ( $settings['bio_limit'] ?? 0 ) );

$trustbio_role_label = '';
if ( $category ) {
	$trustbio_role_label = $category->display_label ?: $category->singular;
} elseif ( ! empty( $author->job_title ) ) {
	$trustbio_role_label = $author->job_title;
}

// Simple layout shows a single-line bio — cap at 120 chars max.
$trustbio_short_bio = '';
if ( ! empty( $trustbio_bio_display ) ) {
	$trustbio_short_bio = wp_strip_all_tags( $trustbio_bio_display );
	if ( mb_strlen( $trustbio_short_bio ) > 120 ) {
		$trustbio_short_bio = mb_substr( $trustbio_short_bio, 0, 120 ) . '…';
	}
}

$trustbio_name_style    = 'font-size:' . (int) ( $settings['name_size'] ?? 18 ) . 'px;font-weight:' . esc_attr( $settings['name_weight'] ?? '700' ) . ';color:' . esc_attr( $settings['name_color'] ?? '#111827' ) . ';';
$trustbio_bio_style     = 'font-size:' . (int) ( $settings['bio_size'] ?? 13 ) . 'px;color:' . esc_attr( $settings['bio_color'] ?? '#4b5563' ) . ';';
$trustbio_role_style    = 'color:' . esc_attr( $settings['label_color'] ?? '#6b7280' ) . ';';
$trustbio_wrapper_style = trustbio_build_wrapper_style( $settings );
?>
<div class="trustbio-box trustbio-layout-simple"<?php if ( $trustbio_wrapper_style ) echo ' style="' . esc_attr( $trustbio_wrapper_style ) . '"'; ?>>

	<div class="tb-left">
		<img class="tb-avatar"
		     src="<?php echo esc_url( $avatar_url ); ?>"
		     alt="<?php echo esc_attr( $author->display_name ); ?>"
		     width="64" height="64">
		<div>
			<?php if ( $trustbio_role_label ) : ?>
			<div class="tb-role" style="<?php echo esc_attr( $trustbio_role_style ); ?>"><?php echo esc_html( $trustbio_role_label ); ?></div>
			<?php endif; ?>
			<h2 class="tb-name" style="<?php echo esc_attr( $trustbio_name_style ); ?>">
				<a href="<?php echo esc_url( $author_url ); ?>" rel="author" style="color:inherit;text-decoration:none;">
					<?php echo esc_html( $author->display_name ); ?>
				</a>
			</h2>
			<?php if ( $trustbio_badges_html ) : ?>
			<div class="tb-credentials" style="display:flex;flex-wrap:wrap;gap:4px;margin:3px 0 0;">
				<?php echo $trustbio_badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="tb-rule" aria-hidden="true"></div>

	<div class="tb-body" style="<?php echo esc_attr( $trustbio_bio_style ); ?>">
		<?php if ( $trustbio_short_bio ) : ?>
		<p style="margin:0 0 4px;">
			<?php echo esc_html( $trustbio_short_bio ); ?>
			<a href="<?php echo esc_url( $author_url ); ?>" rel="author">
				<?php esc_html_e( 'All posts &rarr;', 'trustbio' ); ?>
			</a>
		</p>
		<?php endif; ?>
		<?php if ( $trustbio_review_html ) : ?>
			<?php echo $trustbio_review_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>

	<div class="tb-right">
		<a href="<?php echo esc_url( $author_url ); ?>" class="tb-follow-btn" rel="author">
			<?php esc_html_e( 'Follow', 'trustbio' ); ?>
		</a>
	</div>

</div>
