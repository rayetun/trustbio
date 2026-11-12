<?php
/**
 * [trustbio] shortcode handler.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Shortcode
 */
class TrustBio_Shortcode {

	/**
	 * Render the [trustbio] shortcode.
	 *
	 * @param array<string,string> $trustbio_atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( array $trustbio_atts ): string {
		$trustbio_atts = shortcode_atts(
			array(
				'id'       => '',
				'slug'     => '',
				'box'      => '',
				'category' => '',
			),
			$trustbio_atts,
			'trustbio'
		);

		wp_enqueue_style(
			'trustbio-public',
			TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-public.css',
			array(),
			TRUSTBIO_VERSION
		);

		$trustbio_public = new TrustBio_Public();

		// Specific author by ID.
		if ( ! empty( $trustbio_atts['id'] ) ) {
			$trustbio_author = trustbio_get_author( absint( $trustbio_atts['id'] ) );
			if ( ! $trustbio_author ) {
				return '';
			}
			return $trustbio_public->render_shortcode_author( $trustbio_author, $trustbio_atts );
		}

		// Specific author by slug.
		if ( ! empty( $trustbio_atts['slug'] ) ) {
			$trustbio_author = trustbio_get_author_by_slug( sanitize_title( $trustbio_atts['slug'] ) );
			if ( ! $trustbio_author ) {
				return '';
			}
			return $trustbio_public->render_shortcode_author( $trustbio_author, $trustbio_atts );
		}

		// Current post authors (optionally filtered by category).
		if ( ! in_the_loop() && ! get_the_ID() ) {
			return '';
		}

		$trustbio_post_id = get_the_ID();
		if ( ! $trustbio_post_id ) {
			return '';
		}

		// Filter by category slug if specified.
		if ( ! empty( $trustbio_atts['category'] ) ) {
			$trustbio_authors = trustbio_get_post_authors( $trustbio_post_id );
			$trustbio_cat_slug = sanitize_title( $trustbio_atts['category'] );
			$trustbio_authors  = array_filter( $trustbio_authors, static function ( $trustbio_entry ) use ( $trustbio_cat_slug ) {
				return $trustbio_entry['category'] && $trustbio_entry['category']->slug === $trustbio_cat_slug;
			} );

			if ( empty( $trustbio_authors ) ) {
				return '';
			}

			return $trustbio_public->render_authors_html( array_values( $trustbio_authors ), $trustbio_atts );
		}

		return $trustbio_public->render_post_author_box( $trustbio_post_id );
	}
}
