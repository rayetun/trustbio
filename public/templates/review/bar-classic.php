<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$trustbio_show_icon  = ! isset( $settings['show_icon'] ) || $settings['show_icon'];
$trustbio_show_creds = ! empty( $settings['show_credentials'] );
$trustbio_style      = '--tbr-color:' . $accent . ';';
?>
<div class="tbr-bar tbr-classic" style="<?php echo esc_attr( $trustbio_style ); ?>" itemscope itemtype="https://schema.org/Review">

	<?php if ( $trustbio_show_icon ) : ?>
	<span class="tbr-icon" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
			<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
		</svg>
	</span>
	<?php endif; ?>

	<?php $trustbio_display_label = $reviewer ? $label : trim( preg_replace( '/\s+by\s*$/iu', '', $label ) ); ?>
	<span class="tbr-text">
		<?php echo esc_html( $trustbio_display_label ); ?>

		<?php if ( $reviewer ) : ?>
			<?php $trustbio_reviewer_url = trustbio_get_author_url( $reviewer ); ?>
			<a href="<?php echo esc_url( $trustbio_reviewer_url ); ?>" class="tbr-name" rel="author" itemprop="author"><?php echo esc_html( $reviewer->display_name ); ?></a><?php if ( $trustbio_show_creds && ! empty( $reviewer_credentials ) ) : ?>, <span class="tbr-creds"><?php echo esc_html( $reviewer_credentials ); ?></span><?php endif; ?>
		<?php endif; ?>
		<?php echo ' ' . esc_html__( 'on', 'trustbio' ) . ' '; ?>

		<time class="tbr-date" datetime="<?php echo esc_attr( $raw_date ); ?>" itemprop="datePublished"><?php echo esc_html( $formatted_date ); ?></time>
	</span>

</div>
