<?php
/**
 * Template 06 — Minimal Inline
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

$trustbio_role_label = '';
if ( $category ) {
	$trustbio_role_label = $category->display_label ?: $category->singular;
} elseif ( ! empty( $author->job_title ) ) {
	$trustbio_role_label = $author->job_title;
}

// Kicker: "Written by · DATE" or just "Written by".
$trustbio_kicker_parts = [ __( 'Written by', 'trustbio' ) ];
if ( $post_id ) {
	$trustbio_kicker_date = get_the_date( 'j M Y', $post_id );
	if ( $trustbio_kicker_date ) {
		$trustbio_kicker_parts[] = $trustbio_kicker_date;
	}
}
$trustbio_kicker = implode( ' · ', $trustbio_kicker_parts );

$trustbio_name_style    = 'font-size:' . (int) ( $settings['name_size'] ?? 18 ) . 'px;font-weight:' . esc_attr( $settings['name_weight'] ?? '700' ) . ';color:' . esc_attr( $settings['name_color'] ?? '#111827' ) . ';';
$trustbio_bio_style     = 'font-size:' . (int) ( $settings['bio_size'] ?? 14 ) . 'px;color:' . esc_attr( $settings['bio_color'] ?? '#36352f' ) . ';';
$trustbio_role_style    = 'color:' . esc_attr( $settings['label_color'] ?? '#8a877d' ) . ';';
$trustbio_wrapper_style = trustbio_build_wrapper_style( $settings );
?>
<div class="trustbio-box trustbio-layout-minimal-inline"<?php if ( $trustbio_wrapper_style ) echo ' style="' . esc_attr( $trustbio_wrapper_style ) . '"'; ?>>

	<img class="tb-avatar"
	     src="<?php echo esc_url( $avatar_url ); ?>"
	     alt="<?php echo esc_attr( $author->display_name ); ?>"
	     width="56" height="56">

	<div class="tb-body">
		<div class="tb-kicker"><?php echo esc_html( $trustbio_kicker ); ?></div>
		<h2 class="tb-name" style="<?php echo esc_attr( $trustbio_name_style ); ?>">
			<a href="<?php echo esc_url( $author_url ); ?>" rel="author" style="color:inherit;text-decoration:none;">
				<?php echo esc_html( $author->display_name ); ?>
			</a>
			<?php if ( $trustbio_role_label ) : ?>
			<span style="font-weight:400;<?php echo esc_attr( $trustbio_role_style ); ?>"> &middot; <?php echo esc_html( $trustbio_role_label ); ?></span>
			<?php endif; ?>
		</h2>
		<?php if ( $trustbio_badges_html ) : ?>
		<div class="tb-credentials" style="display:flex;flex-wrap:wrap;gap:4px;margin:2px 0 6px;">
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

	<?php if ( $social_html ) : ?>
	<div class="tb-socials tb-socials--ghost">
		<?php echo $social_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php endif; ?>

</div>
