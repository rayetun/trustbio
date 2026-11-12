<?php
/**
 * Classic Editor meta box — TrustBio Authors
 * Displays inside the post/page sidebar in the Classic Editor.
 *
 * Available variables (set by render_classic_metabox()):
 * @var WP_Post $trustbio_post  The current post.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load current assignments.
$trustbio_assigned       = get_post_meta( $trustbio_post->ID, '_trustbio_authors', true );
$trustbio_assigned       = is_array( $trustbio_assigned ) ? $trustbio_assigned : array();
$trustbio_hide_box       = (int) get_post_meta( $trustbio_post->ID, '_trustbio_hide_box', true );
$trustbio_box_id         = (int) get_post_meta( $trustbio_post->ID, '_trustbio_box_id', true );
// Data for dropdowns.
$trustbio_categories = trustbio_get_categories( true );
$trustbio_box_class  = new TrustBio_Author_Box();
$trustbio_all_boxes  = $trustbio_box_class->get_all( 'default' );

// Build assigned author details.
$trustbio_assigned_details = array();
foreach ( $trustbio_assigned as $trustbio_entry ) {
	$trustbio_author = trustbio_get_author( (int) ( $trustbio_entry['author_id'] ?? 0 ) );
	if ( ! $trustbio_author ) {
		continue;
	}
	$trustbio_assigned_details[] = array(
		'author'      => $trustbio_author,
		'category_id' => $trustbio_entry['category_id'] ?? null,
		'sort_order'  => $trustbio_entry['sort_order'] ?? 0,
	);
}
?>

<div class="tb-metabox" id="tb-classic-metabox">
	<input type="hidden" id="tb-authors-json" name="trustbio_authors_json" value="<?php echo esc_attr( wp_json_encode( $trustbio_assigned ) ); ?>">

	<!-- Assigned authors list -->
	<div id="tb-assigned-list" class="tb-metabox__assigned">
		<?php if ( empty( $trustbio_assigned_details ) ) : ?>
			<p class="tb-metabox__empty"><?php esc_html_e( 'No authors assigned. Search below to add.', 'trustbio' ); ?></p>
		<?php else : ?>
			<?php foreach ( $trustbio_assigned_details as $trustbio_idx => $trustbio_item ) :
				$trustbio_a   = $trustbio_item['author'];
				$trustbio_cat = $trustbio_item['category_id'];
			?>
			<div class="tb-metabox__author" data-author-id="<?php echo esc_attr( $trustbio_a->id ); ?>">
				<div class="tb-metabox__author-drag" title="<?php esc_attr_e( 'Drag to reorder', 'trustbio' ); ?>">⠿</div>
				<img class="tb-metabox__author-avatar" src="<?php echo esc_url( trustbio_get_avatar_url( $trustbio_a, 28 ) ); ?>" alt="" width="28" height="28">
				<div class="tb-metabox__author-info">
					<strong><?php echo esc_html( $trustbio_a->display_name ); ?></strong>
					<select class="tb-metabox__cat-select" data-author-id="<?php echo esc_attr( $trustbio_a->id ); ?>">
						<option value=""><?php esc_html_e( '— Category —', 'trustbio' ); ?></option>
						<?php foreach ( $trustbio_categories as $trustbio_cat_item ) : ?>
							<option value="<?php echo esc_attr( $trustbio_cat_item->id ); ?>" <?php selected( $trustbio_cat, $trustbio_cat_item->id ); ?>>
								<?php echo esc_html( $trustbio_cat_item->singular ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="button" class="tb-metabox__remove" data-author-id="<?php echo esc_attr( $trustbio_a->id ); ?>" aria-label="<?php esc_attr_e( 'Remove author', 'trustbio' ); ?>">✕</button>
			</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<!-- Author search -->
	<div class="tb-metabox__search-wrap">
		<input type="text" id="tb-metabox-search" class="tb-metabox__search" placeholder="<?php esc_attr_e( 'Search authors…', 'trustbio' ); ?>" autocomplete="off">
		<div id="tb-metabox-results" class="tb-metabox__results" style="display:none;"></div>
	</div>

	<hr class="tb-metabox__divider">

	<!-- Regular Author Box override -->
	<div class="tb-metabox__field">
		<label class="tb-metabox__label" for="tb-metabox-box-id"><?php esc_html_e( 'Regular Author Box', 'trustbio' ); ?></label>
		<select id="tb-metabox-box-id" name="trustbio_box_id" class="tb-metabox__select">
			<option value="0"><?php esc_html_e( '— Default —', 'trustbio' ); ?></option>
			<?php foreach ( $trustbio_all_boxes as $trustbio_box ) : ?>
				<option value="<?php echo esc_attr( $trustbio_box->id ); ?>" <?php selected( $trustbio_box_id, $trustbio_box->id ); ?>>
					<?php echo esc_html( $trustbio_box->title ); ?><?php echo $trustbio_box->is_default ? ' (' . esc_html__( 'default', 'trustbio' ) . ')' : ''; ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<!-- Hide box toggle -->
	<div class="tb-metabox__field">
		<label class="tb-metabox__check-label">
			<input type="checkbox" name="trustbio_hide_box" value="1" <?php checked( $trustbio_hide_box, 1 ); ?>>
			<?php esc_html_e( 'Hide author box on this post', 'trustbio' ); ?>
		</label>
	</div>
</div><!-- .tb-metabox -->


