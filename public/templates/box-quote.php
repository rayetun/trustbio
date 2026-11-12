<?php
/**
 * Template 03 — Quote Card
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

// Apply bio character limit, then trim to 180 chars max for the pull-quote.
$trustbio_bio_display = trustbio_apply_bio_limit( $author->bio ?? '', (int) ( $settings['bio_limit'] ?? 0 ) );

$trustbio_quote = '';
if ( ! empty( $trustbio_bio_display ) ) {
	$trustbio_quote = wp_strip_all_tags( $trustbio_bio_display );
	if ( mb_strlen( $trustbio_quote ) > 180 ) {
		$trustbio_quote = mb_substr( $trustbio_quote, 0, 180 ) . '…';
	}
}

$trustbio_role_label = '';
if ( $category ) {
	$trustbio_role_label = $category->display_label ?: $category->singular;
} elseif ( ! empty( $author->job_title ) ) {
	$trustbio_role_label = $author->job_title;
}

$trustbio_name_style    = 'font-size:' . (int) ( $settings['name_size'] ?? 16 ) . 'px;font-weight:' . esc_attr( $settings['name_weight'] ?? '700' ) . ';color:' . esc_attr( $settings['name_color'] ?? '#111827' ) . ';';
$trustbio_bio_style     = 'font-size:' . (int) ( $settings['bio_size'] ?? 15 ) . 'px;color:' . esc_attr( $settings['bio_color'] ?? '#4b5563' ) . ';';
$trustbio_role_style    = 'color:' . esc_attr( $settings['label_color'] ?? '#6b7280' ) . ';';
$trustbio_wrapper_style = trustbio_build_wrapper_style( $settings );
?>
<div class="trustbio-box trustbio-layout-quote"<?php if ( $trustbio_wrapper_style ) echo ' style="' . esc_attr( $trustbio_wrapper_style ) . '"'; ?>>

	<img class="tb-avatar"
	     src="<?php echo esc_url( $avatar_url ); ?>"
	     alt="<?php echo esc_attr( $author->display_name ); ?>"
	     width="120" height="120">

	<div class="tb-body">
		<?php if ( $trustbio_quote ) : ?>
		<p class="tb-quote" style="<?php echo esc_attr( $trustbio_bio_style ); ?>"><?php echo esc_html( $trustbio_quote ); ?></p>
		<?php endif; ?>

		<div class="tb-byline">
			<span class="tb-byline-name" style="<?php echo esc_attr( $trustbio_name_style ); ?>">
				<a href="<?php echo esc_url( $author_url ); ?>" rel="author" style="color:inherit;text-decoration:none;">
					<?php echo esc_html( $author->display_name ); ?>
				</a>
			</span>
			<?php if ( $trustbio_badges_html ) : ?>
			<div class="tb-credentials" style="display:flex;flex-wrap:wrap;gap:4px;margin:2px 0 0;">
				<?php echo $trustbio_badges_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php endif; ?>
			<?php if ( $trustbio_role_label ) : ?>
			<span class="tb-byline-sep"></span>
			<span class="tb-byline-role" style="<?php echo esc_attr( $trustbio_role_style ); ?>"><?php echo esc_html( $trustbio_role_label ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $trustbio_review_html ) : ?>
			<?php echo $trustbio_review_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>

	<?php if ( $social_html ) : ?>
	<div class="tb-right">
		<div class="tb-socials">
			<?php echo $social_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php endif; ?>

</div>
