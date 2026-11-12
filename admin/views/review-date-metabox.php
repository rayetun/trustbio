<?php
/**
 * Classic Editor meta box: Expert Review Date.
 *
 * Variables available from TrustBio_Review_Date::render_meta_box():
 *   $trustbio_post         WP_Post
 *   $trustbio_date         string   review date (YYYY-MM-DD)
 *   $trustbio_reviewer_id  int      TrustBio author ID
 *   $trustbio_display      string   display mode
 *   $trustbio_label        string   custom label
 *   $trustbio_post_authors array    assigned authors for this post
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tb-metabox-review">

	<div class="tb-metabox-field">
		<label class="tb-metabox-label" for="trustbio_review_date">
			<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 20 20" fill="#4f46e5"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
			<?php esc_html_e( 'Review Date', 'trustbio' ); ?>
		</label>
		<input type="date"
			id="trustbio_review_date"
			name="trustbio_review_date"
			value="<?php echo esc_attr( $trustbio_date ); ?>"
			class="widefat"
			max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
		<p class="tb-metabox-hint"><?php esc_html_e( 'Date this content was last expert-reviewed.', 'trustbio' ); ?></p>
	</div>

	<div class="tb-metabox-field">
		<label class="tb-metabox-label" for="trustbio_reviewer_id">
			<?php esc_html_e( 'Reviewed By', 'trustbio' ); ?>
		</label>
		<select id="trustbio_reviewer_id" name="trustbio_reviewer_id" class="widefat">
			<option value="0"><?php esc_html_e( '— Select reviewer —', 'trustbio' ); ?></option>
			<?php foreach ( (array) $trustbio_all_authors as $trustbio_a ) : ?>
				<option value="<?php echo esc_attr( $trustbio_a->id ); ?>" <?php selected( $trustbio_reviewer_id, (int) $trustbio_a->id ); ?>>
					<?php echo esc_html( $trustbio_a->display_name ); ?>
					<?php if ( ! empty( $trustbio_a->category_name ) ) : ?>
						(<?php echo esc_html( $trustbio_a->category_name ); ?>)
					<?php endif; ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="tb-metabox-hint"><?php esc_html_e( 'Optional. The person who reviewed this content. All active TrustBio authors are listed.', 'trustbio' ); ?></p>
	</div>

	<div class="tb-metabox-field">
		<label class="tb-metabox-label" for="trustbio_review_display">
			<?php esc_html_e( 'Display', 'trustbio' ); ?>
		</label>
		<select id="trustbio_review_display" name="trustbio_review_display" class="widefat">
			<option value="global" <?php selected( $trustbio_display, 'global' ); ?>><?php esc_html_e( '— Use site default —', 'trustbio' ); ?></option>
			<option value="inline" <?php selected( $trustbio_display, 'inline' ); ?>><?php esc_html_e( 'In author box only', 'trustbio' ); ?></option>
			<option value="bar" <?php selected( $trustbio_display, 'bar' ); ?>><?php esc_html_e( 'Separate review bar', 'trustbio' ); ?></option>
			<option value="both" <?php selected( $trustbio_display, 'both' ); ?>><?php esc_html_e( 'Both (box + bar)', 'trustbio' ); ?></option>
			<option value="none" <?php selected( $trustbio_display, 'none' ); ?>><?php esc_html_e( 'Hidden on this post', 'trustbio' ); ?></option>
		</select>
	</div>

	<div class="tb-metabox-field">
		<label class="tb-metabox-label" for="trustbio_review_label">
			<?php esc_html_e( 'Custom Label', 'trustbio' ); ?>
		</label>
		<input type="text"
			id="trustbio_review_label"
			name="trustbio_review_label"
			value="<?php echo esc_attr( $trustbio_label ); ?>"
			class="widefat"
			placeholder="<?php echo esc_attr( get_option( 'trustbio_review_default_label', __( 'Medically reviewed by', 'trustbio' ) ) ); ?>">
		<p class="tb-metabox-hint"><?php esc_html_e( 'Override the default label for this post only.', 'trustbio' ); ?></p>
	</div>

	<?php if ( ! empty( $trustbio_all_review_bars ) ) : ?>
	<div class="tb-metabox-field">
		<label class="tb-metabox-label" for="trustbio_review_bar_id">
			<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 20 20" fill="#4f46e5"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
			<?php esc_html_e( 'Review Bar Template', 'trustbio' ); ?>
		</label>
		<select id="trustbio_review_bar_id" name="trustbio_review_bar_id" class="widefat">
			<option value="0"><?php esc_html_e( '— Site Default —', 'trustbio' ); ?></option>
			<?php foreach ( (array) $trustbio_all_review_bars as $trustbio_bar ) : ?>
				<option value="<?php echo esc_attr( $trustbio_bar->id ); ?>" <?php selected( $trustbio_bar_id, (int) $trustbio_bar->id ); ?>>
					<?php echo esc_html( $trustbio_bar->title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="tb-metabox-hint"><?php esc_html_e( 'Override the review bar template for this post only.', 'trustbio' ); ?></p>
	</div>
	<?php endif; ?>

</div>

