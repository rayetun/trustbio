<?php
/**
 * JSON-LD E-E-A-T schema output.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Schema
 */
class TrustBio_Schema {

	public function output_schema(): void {
		if ( ! is_singular() ) {
			return;
		}

		if ( ! get_option( 'trustbio_enable_schema', 1 ) ) {
			return;
		}

		$trustbio_post_id   = get_the_ID();
		$trustbio_post_type = get_post_type( $trustbio_post_id );

		if ( ! $trustbio_post_type || ! trustbio_is_enabled_for_post_type( $trustbio_post_type ) ) {
			return;
		}

		// In compatibility mode, skip if Yoast or Rank Math is handling schema.
		if ( get_option( 'trustbio_schema_compat_mode', 1 ) ) {
			if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
				// These plugins handle full Article schema — we output only Person blocks.
				$this->output_person_only( $trustbio_post_id );
				return;
			}
		}

		$this->output_full_schema( $trustbio_post_id );
		$this->print_webpage_node( $trustbio_post_id );
	}

	/**
	 * Output full Article + Person schema.
	 *
	 * @param int $trustbio_post_id Post ID.
	 */
	private function output_full_schema( int $trustbio_post_id ): void {
		$trustbio_authors = trustbio_get_post_authors( $trustbio_post_id );
		if ( empty( $trustbio_authors ) ) {
			return;
		}

		$trustbio_post        = get_post( $trustbio_post_id );
		$trustbio_schema_data = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Article',
			'headline'      => get_the_title( $trustbio_post_id ),
			'url'           => get_permalink( $trustbio_post_id ),
			'datePublished' => $trustbio_post ? gmdate( 'c', strtotime( $trustbio_post->post_date_gmt ) ) : '',
			'dateModified'  => $trustbio_post ? gmdate( 'c', strtotime( $trustbio_post->post_modified_gmt ) ) : '',
		);

		// Group authors by schema property.
		$trustbio_by_prop = array();
		foreach ( $trustbio_authors as $trustbio_entry ) {
			$trustbio_cat  = $trustbio_entry['category'];
			$trustbio_prop = $trustbio_cat ? ( $trustbio_cat->schema_prop ?: 'author' ) : 'author';
			$trustbio_person = $this->build_person_schema( $trustbio_entry['author'] );
			$trustbio_by_prop[ $trustbio_prop ][] = $trustbio_person;
		}

		foreach ( $trustbio_by_prop as $trustbio_prop => $trustbio_persons ) {
			$trustbio_schema_data[ $trustbio_prop ] = count( $trustbio_persons ) === 1
				? $trustbio_persons[0]
				: $trustbio_persons;
		}

		/*
		 * The last-reviewed date and who reviewed it.
		 *
		 * TrustBio_Review_Date::get_schema_additions() has always built these,
		 * but its only callers were the Yoast and Rank Math injectors. On a
		 * site running neither — where this method owns the Article node — the
		 * page could say an article was medically reviewed by a named clinician
		 * on a given date while the structured data said nothing about a review
		 * at all. That is most of the point of recording one.
		 *
		 * reviewedBy is only set if nothing has claimed it already: an author
		 * whose category maps to that schema property is also a reviewer, and
		 * naming the same person twice would be worse than naming them once.
		 */
		if ( class_exists( 'TrustBio_Review_Date' ) ) {
			$trustbio_review_date = new TrustBio_Review_Date();

			foreach ( $trustbio_review_date->get_schema_additions( $trustbio_post_id ) as $trustbio_key => $trustbio_value ) {
				if ( isset( $trustbio_schema_data[ $trustbio_key ] ) ) {
					continue;
				}
				$trustbio_schema_data[ $trustbio_key ] = $trustbio_value;
			}
		}

		/**
		 * Filter the full schema data before output.
		 *
		 * @param array $trustbio_schema_data The schema array.
		 * @param int   $trustbio_post_id     Post ID.
		 */
		$trustbio_schema_data = apply_filters( 'trustbio_schema_data', $trustbio_schema_data, $trustbio_post_id );

		$this->print_schema( $trustbio_schema_data );
	}

	/**
	 * Print the WebPage node that carries the last-reviewed date.
	 *
	 * lastReviewed has domain WebPage. This plugin's own output is a single
	 * Article node, so there was nowhere valid to put the date -- which is how
	 * it ended up on the Article as "dateReviewed", a property that does not
	 * exist. A small WebPage node beside the Article is the honest shape: the
	 * same URL, and an @id so a consumer can see the two describe one page.
	 *
	 * Not printed when an SEO plugin owns the page's schema. The Yoast and
	 * Rank Math injectors put the date on the WebPage node those plugins
	 * already emit, and a second one would be a duplicate.
	 *
	 * @param int $trustbio_post_id Post ID.
	 */
	private function print_webpage_node( int $trustbio_post_id ): void {
		if ( ! class_exists( 'TrustBio_Review_Date' ) ) {
			return;
		}

		$trustbio_review_date = new TrustBio_Review_Date();
		$trustbio_additions   = $trustbio_review_date->get_webpage_additions( $trustbio_post_id );

		if ( empty( $trustbio_additions ) ) {
			return;
		}

		$trustbio_url = (string) get_permalink( $trustbio_post_id );

		$trustbio_node = array_merge(
			array(
				'@context'         => 'https://schema.org',
				'@type'            => 'WebPage',
				'@id'              => $trustbio_url . '#webpage',
				'url'              => $trustbio_url,
				'name'             => (string) get_the_title( $trustbio_post_id ),
				'mainEntityOfPage' => $trustbio_url,
			),
			$trustbio_additions
		);

		/**
		 * Filters the WebPage node carrying the last-reviewed date.
		 *
		 * Return an empty array to suppress the node entirely. The Pro add-on's
		 * medical schema does exactly that on the posts where it emits a
		 * MedicalWebPage of its own, which already carries the date.
		 *
		 * @since 1.0.1
		 * @param array<string,mixed> $node    The WebPage node.
		 * @param int                 $post_id Post ID.
		 */
		$trustbio_node = (array) apply_filters( 'trustbio_webpage_schema', $trustbio_node, $trustbio_post_id );

		if ( empty( $trustbio_node ) ) {
			return;
		}

		$this->print_schema( $trustbio_node );
	}

	/**
	 * Output only Person schema blocks (when Yoast/Rank Math handles Article).
	 *
	 * @param int $trustbio_post_id Post ID.
	 */
	private function output_person_only( int $trustbio_post_id ): void {
		$trustbio_authors = trustbio_get_post_authors( $trustbio_post_id );
		if ( empty( $trustbio_authors ) ) {
			return;
		}

		foreach ( $trustbio_authors as $trustbio_entry ) {
			$trustbio_person = $this->build_person_schema( $trustbio_entry['author'] );
			$trustbio_cat    = $trustbio_entry['category'];

			$trustbio_schema = array(
				'@context' => 'https://schema.org',
				'@type'    => $trustbio_cat ? ( 'reviewedBy' === $trustbio_cat->schema_prop ? 'Person' : 'Person' ) : 'Person',
			);
			$trustbio_schema = array_merge( $trustbio_schema, $trustbio_person );

			$this->print_schema( $trustbio_schema );
		}
	}

	/**
	 * Build a Person schema object for an author.
	 *
	 * @param object $trustbio_author Author object.
	 * @return array
	 */
	private function build_person_schema( object $trustbio_author ): array {
		$trustbio_person = array(
			'@type' => 'Person',
			'name'  => $trustbio_author->display_name,
			'url'   => trustbio_get_author_url( $trustbio_author ),
		);

		if ( ! empty( $trustbio_author->job_title ) ) {
			$trustbio_person['jobTitle'] = $trustbio_author->job_title;
		}

		if ( ! empty( $trustbio_author->bio ) ) {
			$trustbio_person['description'] = wp_strip_all_tags( $trustbio_author->bio );
		}

		// Avatar image.
		$trustbio_avatar = trustbio_get_avatar_url( $trustbio_author, 200 );
		if ( $trustbio_avatar ) {
			$trustbio_person['image'] = $trustbio_avatar;
		}

		// sameAs — built from social fields that are authority signals.
		$trustbio_same_as_slugs = trustbio_get_same_as_field_slugs();
		$trustbio_field_values  = trustbio_get_author_field_values( $trustbio_author->id );

		/*
		 * sameAs is a claim that two URLs are the same entity, so a value that
		 * is not a URL must not go in it. esc_url_raw() does not enforce that:
		 * given an ORCID iD written the way ORCID prints it,
		 * 0000-0002-1825-0097, it returns http://0000-0002-1825-0097 and that
		 * gets published. trustbio_profile_url() expands a handle where the
		 * shape of the profile URL is known and returns '' where it is not.
		 */
		$trustbio_same_as = array();
		foreach ( $trustbio_same_as_slugs as $trustbio_slug ) {
			if ( empty( $trustbio_field_values[ $trustbio_slug ] ) ) {
				continue;
			}
			$trustbio_same_as[] = trustbio_profile_url(
				$trustbio_slug,
				(string) $trustbio_field_values[ $trustbio_slug ]
			);
		}

		// Additional sameAs from the SEO tab. Free text, so the same rule:
		// anything that is not a URL is dropped rather than half-repaired.
		$trustbio_extra_same_as = get_option( 'trustbio_author_sameas_' . $trustbio_author->id, '' );
		if ( $trustbio_extra_same_as ) {
			foreach ( array_filter( array_map( 'trim', explode( "\n", $trustbio_extra_same_as ) ) ) as $trustbio_url ) {
				$trustbio_same_as[] = trustbio_profile_url( '', (string) $trustbio_url );
			}
		}

		$trustbio_same_as = array_values( array_unique( array_filter( $trustbio_same_as ) ) );
		if ( ! empty( $trustbio_same_as ) ) {
			$trustbio_person['sameAs'] = $trustbio_same_as;
		}

		// hasCredential — Phase 11: E-E-A-T credential verification badges.
		$trustbio_schema_creds = TrustBio_Credentials::get_schema_credentials( $trustbio_author->id );
		if ( ! empty( $trustbio_schema_creds ) ) {
			$trustbio_person['hasCredential'] = count( $trustbio_schema_creds ) === 1
				? $trustbio_schema_creds[0]
				: $trustbio_schema_creds;
		}

		// numberOfItems — Phase 14: total published post count across all categories.
		// Note: numberOfItems is formally an ItemList property; used here as an informal
		// extension on Person. Google ignores unrecognised properties gracefully.
		if ( get_option( 'trustbio_show_contribution_count', 0 ) ) {
			$trustbio_total_posts = TrustBio_Contribution_Counter::get_total( (int) $trustbio_author->id );
			if ( $trustbio_total_posts > 0 ) {
				$trustbio_person['numberOfItems'] = $trustbio_total_posts;
			}
		}

		// knowsAbout — expertise topics from Author Expertise Topics (v20).
		$trustbio_knows_about = TrustBio_Author_Expertise::build_knows_about( (int) $trustbio_author->id );
		if ( ! empty( $trustbio_knows_about ) ) {
			$trustbio_person['knowsAbout'] = $trustbio_knows_about;
		}

		return $trustbio_person;
	}

	/**
	 * Print schema JSON-LD to the page <head>.
	 *
	 * @param array $trustbio_schema Schema array.
	 */
	private function print_schema( array $trustbio_schema ): void {
		/*
		 * JSON_HEX_TAG is what keeps this safe, and it is not optional.
		 *
		 * This data is interpolated straight into a <script> element, and
		 * JSON_UNESCAPED_SLASHES means a value containing the literal text
		 * "</script>" — an author bio, a job title, a credential name — is
		 * written out as-is and closes the element early, turning whatever
		 * follows into live markup. JSON_HEX_TAG encodes < and > as < and
		 * >, so no tag can be formed, and JSON readers decode it back to
		 * exactly the same string.
		 */
		echo '<script type="application/ld+json">' . wp_json_encode( $trustbio_schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG makes a tag impossible to form.
	}
}
