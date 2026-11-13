<?php
/**
 * Author Credential management — full CRUD + AJAX handlers.
 *
 * Credentials are professional badges displayed beside author names
 * in author boxes and encoded in JSON-LD schema as hasCredential.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Credentials
 */
class TrustBio_Credentials {

	/** @var string Credentials table name. */
	private string $trustbio_table;

	public function __construct() {
		global $wpdb;
		$this->trustbio_table = $wpdb->prefix . 'trustbio_author_credentials';
	}

	// =========================================================
	// Render
	// =========================================================

	public function render(): void {
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/credentials.php';
	}

	// =========================================================
	// AJAX: Save credential
	// =========================================================

	/**
	 * Read one credential row as an array, or null when there is none.
	 *
	 * Public because the trustbio_credential_* actions hand rows around in
	 * exactly this shape, and a listener comparing one against the table
	 * should not have to write its own query to do it.
	 *
	 * @since 1.0.1
	 *
	 * @param int $trustbio_id Credential row ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_credential( int $trustbio_id ): ?array {
		if ( $trustbio_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$trustbio_table = $wpdb->prefix . 'trustbio_author_credentials';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				"SELECT * FROM `{$trustbio_table}` WHERE id = %d",
				$trustbio_id
			),
			ARRAY_A
		);

		return is_array( $trustbio_row ) ? $trustbio_row : null;
	}

	/**
	 * Announce a credential write, with the row as it now stands.
	 *
	 * @param int                      $trustbio_id     Credential row ID.
	 * @param array<string,mixed>|null $trustbio_before The row before, null on a create.
	 * @return void
	 */
	private static function fire_saved( int $trustbio_id, ?array $trustbio_before ): void {
		$trustbio_after = self::get_credential( $trustbio_id );

		if ( null === $trustbio_after ) {
			return;
		}

		/**
		 * Fires after a credential has been created or updated.
		 *
		 * Both states are passed because a listener that only learns a row
		 * changed has to go and find out how, and by then the old values are
		 * gone. $trustbio_before is null on a create.
		 *
		 * @since 1.0.1
		 * @param int                      $credential_id The row ID.
		 * @param array<string,mixed>      $after         The row as it now stands.
		 * @param array<string,mixed>|null $before        The row as it was, or null.
		 */
		do_action( 'trustbio_credential_saved', $trustbio_id, $trustbio_after, $trustbio_before );
	}

	public function ajax_save(): void {
		check_ajax_referer( 'trustbio_save_credential', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_save_credential' );

		global $wpdb;

		$trustbio_id        = isset( $_POST['cred_id'] ) ? absint( $_POST['cred_id'] ) : ( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		$trustbio_author_id = absint( $_POST['author_id'] ?? 0 );

		if ( ! $trustbio_author_id ) {
			wp_send_json_error( array( 'message' => __( 'Author ID is required.', 'trustbio' ) ) );
		}

		// Validate badge type.
		$trustbio_valid_types = array_keys( self::get_badge_types() );
		$trustbio_badge_type  = sanitize_key( wp_unslash( $_POST['badge_type'] ?? 'custom' ) );
		if ( ! in_array( $trustbio_badge_type, $trustbio_valid_types, true ) ) {
			$trustbio_badge_type = 'custom';
		}

		$trustbio_data = array(
			'author_id'         => $trustbio_author_id,
			'badge_type'        => $trustbio_badge_type,
			'credential_name'   => sanitize_text_field( wp_unslash( $_POST['credential_name'] ?? '' ) ),
			'issuing_body'      => sanitize_text_field( wp_unslash( $_POST['issuing_body'] ?? '' ) ),
			'license_number'    => sanitize_text_field( wp_unslash( $_POST['license_number'] ?? '' ) ),
			'issue_date'        => sanitize_text_field( wp_unslash( $_POST['issue_date'] ?? '' ) ),
			'expiry_date'       => sanitize_text_field( wp_unslash( $_POST['expiry_date'] ?? '' ) ) ?: null,
			'verification_url'  => esc_url_raw( wp_unslash( $_POST['verification_url'] ?? '' ) ),
			'show_badge'        => isset( $_POST['show_badge'] ) ? 1 : 0,
			'show_in_schema'    => isset( $_POST['show_in_schema'] ) ? 1 : 0,
			'sort_order'        => absint( $_POST['sort_order'] ?? 0 ),
		);

		if ( empty( $trustbio_data['credential_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Credential name is required.', 'trustbio' ) ) );
		}

		// Auto-build NPI verification URL from license number when not manually supplied.
		if ( 'npi' === $trustbio_badge_type && ! empty( $trustbio_data['license_number'] ) && empty( $trustbio_data['verification_url'] ) ) {
			$trustbio_npi_num = preg_replace( '/\D/', '', $trustbio_data['license_number'] );
			if ( $trustbio_npi_num ) {
				$trustbio_data['verification_url'] = 'https://npiregistry.cms.hhs.gov/provider-view/' . $trustbio_npi_num;
			}
		}

		// Validate dates.
		if ( ! empty( $trustbio_data['issue_date'] ) && ! $this->is_valid_date( $trustbio_data['issue_date'] ) ) {
			$trustbio_data['issue_date'] = '';
		}
		if ( ! empty( $trustbio_data['expiry_date'] ) && ! $this->is_valid_date( $trustbio_data['expiry_date'] ) ) {
			$trustbio_data['expiry_date'] = null;
		}

		if ( $trustbio_id > 0 ) {
			// Read first: once the UPDATE has run the old values are gone, and
			// an audit trail that cannot say what a value used to be is not one.
			$trustbio_before = self::get_credential( $trustbio_id );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->trustbio_table,
				$trustbio_data,
				array( 'id' => $trustbio_id ),
				null,
				array( '%d' )
			);

			self::fire_saved( $trustbio_id, $trustbio_before );
			wp_send_json_success( array( 'message' => __( 'Credential updated.', 'trustbio' ), 'id' => $trustbio_id ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->trustbio_table, $trustbio_data );
		$trustbio_new_id = $wpdb->insert_id;

		if ( ! $trustbio_new_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save credential.', 'trustbio' ) ) );
		}

		self::fire_saved( $trustbio_new_id, null );

		wp_send_json_success( array( 'message' => __( 'Credential added.', 'trustbio' ), 'id' => $trustbio_new_id ) );
	}

	// =========================================================
	// AJAX: Delete credential
	// =========================================================

	public function ajax_delete(): void {
		check_ajax_referer( 'trustbio_delete_credential', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_delete_credential' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid credential ID.', 'trustbio' ) ) );
		}

		// The whole row, while it still exists. A deletion no one can
		// describe afterwards is the one case where an audit log is useless.
		$trustbio_before = self::get_credential( $trustbio_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->trustbio_table, array( 'id' => $trustbio_id ), array( '%d' ) );

		if ( null !== $trustbio_before ) {
			/**
			 * Fires after a credential row has been deleted.
			 *
			 * @since 1.0.1
			 * @param int                 $credential_id The row ID, now gone.
			 * @param array<string,mixed> $before        The row as it was.
			 */
			do_action( 'trustbio_credential_deleted', $trustbio_id, $trustbio_before );
		}

		wp_send_json_success( array( 'message' => __( 'Credential deleted.', 'trustbio' ) ) );
	}

	// =========================================================
	// AJAX: Reorder credentials
	// =========================================================

	public function ajax_reorder(): void {
		check_ajax_referer( 'trustbio_reorder_credentials', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_reorder_credentials' );

		global $wpdb;

		$trustbio_order = isset( $_POST['order'] ) && is_array( $_POST['order'] )
			? array_map( 'absint', wp_unslash( $_POST['order'] ) )
			: array();

		// Where each of these sat before the loop moves it. The new position
		// is the array index, so a listener can work out what moved from
		// this alone and does not need a second read.
		$trustbio_positions = array();
		foreach ( $trustbio_order as $trustbio_cred_id ) {
			$trustbio_row = self::get_credential( (int) $trustbio_cred_id );
			if ( null !== $trustbio_row ) {
				$trustbio_positions[ (int) $trustbio_row['id'] ] = (int) $trustbio_row['sort_order'];
			}
		}

		foreach ( $trustbio_order as $trustbio_pos => $trustbio_cred_id ) {
			if ( ! $trustbio_cred_id ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->trustbio_table,
				array( 'sort_order' => $trustbio_pos ),
				array( 'id' => $trustbio_cred_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		/**
		 * Fires after credentials have been re-ordered.
		 *
		 * @since 1.0.1
		 * @param int[]          $order     Credential IDs in their new order;
		 *                                  the index is the new sort_order.
		 * @param array<int,int> $positions Credential ID => sort_order before.
		 */
		do_action( 'trustbio_credentials_reordered', $trustbio_order, $trustbio_positions );

		wp_send_json_success( array( 'message' => __( 'Order saved.', 'trustbio' ) ) );
	}

	// =========================================================
	// Public: get credentials for an author
	// =========================================================

	/**
	 * Get all credentials for an author, ordered by sort_order.
	 *
	 * @param int  $trustbio_author_id Author ID.
	 * @param bool $trustbio_badge_only Return only credentials with show_badge = 1.
	 * @return array<int, object>
	 */
	public static function get_for_author( int $trustbio_author_id, bool $trustbio_badge_only = false ): array {
		global $wpdb;
		$trustbio_t = $wpdb->prefix . 'trustbio_author_credentials';

		/*
		 * Two whole queries rather than one with a WHERE fragment spliced
		 * into it. The fragment was a literal either way, so nothing could be
		 * injected through it — but a query built by concatenation has to be
		 * read to know that, and these two can be read at a glance.
		 */
		if ( $trustbio_badge_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE author_id = %d AND show_badge = 1 ORDER BY sort_order ASC, id ASC',
					$trustbio_t,
					$trustbio_author_id
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE author_id = %d ORDER BY sort_order ASC, id ASC',
				$trustbio_t,
				$trustbio_author_id
			)
		);
	}

	/**
	 * Get credentials for schema output (show_in_schema = 1).
	 *
	 * @param int $trustbio_author_id Author ID.
	 * @return array<int, object>
	 */
	public static function get_for_schema( int $trustbio_author_id ): array {
		global $wpdb;
		$trustbio_t = $wpdb->prefix . 'trustbio_author_credentials';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM %i WHERE author_id = %d AND show_in_schema = 1 ORDER BY sort_order ASC',
			$trustbio_t,
			$trustbio_author_id
		) );
	}

	// =========================================================
	// Public: badge rendering
	// =========================================================

	/**
	 * Render inline HTML badges for an author (used in author box frontend).
	 *
	 * @param int $trustbio_author_id Author ID.
	 * @return string HTML.
	 */
	public static function render_badges( int $trustbio_author_id ): string {
		$trustbio_creds = self::get_for_author( $trustbio_author_id, true );

		if ( empty( $trustbio_creds ) ) {
			return '';
		}

		$trustbio_badge_types = self::get_badge_types();
		$trustbio_html        = '';

		foreach ( $trustbio_creds as $trustbio_cred ) {
			// Check expiry.
			if ( ! empty( $trustbio_cred->expiry_date ) && strtotime( $trustbio_cred->expiry_date ) < time() ) {
				continue; // Skip expired credentials silently on frontend.
			}

			$trustbio_badge_info  = $trustbio_badge_types[ $trustbio_cred->badge_type ] ?? $trustbio_badge_types['custom'];
			$trustbio_label       = esc_html( self::badge_label( $trustbio_cred ) );

			$trustbio_tooltip     = esc_attr( $trustbio_cred->credential_name );
			if ( ! empty( $trustbio_cred->issuing_body ) ) {
				$trustbio_tooltip .= ' — ' . esc_attr( $trustbio_cred->issuing_body );
			}

			$trustbio_color      = esc_attr( $trustbio_badge_info['color'] );
			$trustbio_text_color = esc_attr( $trustbio_badge_info['text'] );
			$trustbio_icon_svg   = $trustbio_badge_info['icon'];

			$trustbio_badge_html = '<span class="trustbio-credential-badge" '
				. 'style="display:inline-flex;align-items:center;gap:3px;background:' . $trustbio_color . ';color:' . $trustbio_text_color . ';font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;letter-spacing:.05em;text-transform:uppercase;line-height:1.5;" '
				. 'title="' . $trustbio_tooltip . '">';

			if ( $trustbio_icon_svg ) {
				$trustbio_badge_html .= '<span style="display:flex;align-items:center;" aria-hidden="true">' . $trustbio_icon_svg . '</span>';
			}

			$trustbio_badge_html .= $trustbio_label;

			// Close the badge span before anything is wrapped around it. This
			// used to happen only in the else branch below, so every badge that
			// had a verification URL emitted an unclosed <span>.
			$trustbio_badge_html .= '</span>';

			if ( ! empty( $trustbio_cred->verification_url ) ) {
				// Wrap in a link if verification URL is set.
				$trustbio_badge_html = '<a href="' . esc_url( $trustbio_cred->verification_url ) . '" target="_blank" rel="noopener noreferrer nofollow" class="trustbio-credential-link" '
					. 'style="text-decoration:none;display:inline-flex;" '
					. 'title="' . esc_attr__( 'Verify credential', 'trustbio' ) . ' — ' . $trustbio_tooltip . '">'
					. $trustbio_badge_html . '</a>';
			}

			$trustbio_html .= $trustbio_badge_html;
		}

		return $trustbio_html;
	}

	/**
	 * Get credential labels for an author as a plain-text comma-separated string.
	 * Mirrors the label logic in render_badges(), skipping expired credentials.
	 *
	 * @param int $trustbio_author_id Author ID.
	 * @return string e.g. "MBA, PHD"
	 */
	public static function get_text_for_author( int $trustbio_author_id ): string {
		$trustbio_creds = self::get_for_author( $trustbio_author_id, true );
		if ( empty( $trustbio_creds ) ) {
			return '';
		}
		$trustbio_labels = array();
		foreach ( $trustbio_creds as $trustbio_cred ) {
			if ( ! empty( $trustbio_cred->expiry_date ) && strtotime( $trustbio_cred->expiry_date ) < time() ) {
				continue;
			}
			$trustbio_labels[] = self::badge_label( $trustbio_cred );
		}
		return implode( ', ', $trustbio_labels );
	}

	/**
	 * The short label shown on a credential badge.
	 *
	 * Shared by render_badges() and get_text_for_author() so the badge and the
	 * plain-text list can never disagree — they previously held two copies of
	 * this logic with a comment asking that they be kept in step.
	 *
	 * For a known badge type this is the type's own label ("MD"), not the raw
	 * slug, which was being printed lowercase and only looked right because of
	 * a CSS text-transform.
	 *
	 * For a custom credential it is the leading word rather than a fixed byte
	 * count. The old `substr( $name, 0, 6 )` cut "FRCP (Cardiology)" to the
	 * visibly broken "FRCP (", and being a BYTE cut it truncated non-Latin
	 * scripts far harder than Latin ones and could slice a UTF-8 character in
	 * half — "Dré循環器" came out as invalid bytes.
	 *
	 * @param object $trustbio_cred Credential row.
	 * @return string
	 */
	private static function badge_label( object $trustbio_cred ): string {
		$trustbio_type = (string) ( $trustbio_cred->badge_type ?? 'custom' );

		if ( 'custom' !== $trustbio_type ) {
			$trustbio_types = self::get_badge_types();
			return isset( $trustbio_types[ $trustbio_type ] )
				? (string) $trustbio_types[ $trustbio_type ]['label']
				: strtoupper( $trustbio_type );
		}

		$trustbio_name = trim( (string) $trustbio_cred->credential_name );
		if ( '' === $trustbio_name ) {
			return '';
		}

		// The leading word, so "FRCP (Cardiology)" badges as "FRCP".
		$trustbio_parts = preg_split( '/[\s(\[,;\/–—-]+/u', $trustbio_name, 2 );
		$trustbio_first = is_array( $trustbio_parts ) ? (string) $trustbio_parts[0] : '';

		if ( '' !== $trustbio_first && mb_strlen( $trustbio_first ) <= 10 ) {
			return $trustbio_first;
		}

		return mb_strlen( $trustbio_name ) > 8
			? mb_substr( $trustbio_name, 0, 8 ) . '…'
			: $trustbio_name;
	}


	/**
	 * Build the hasCredential schema array for JSON-LD output.
	 *
	 * @param int $trustbio_author_id Author ID.
	 * @return array<int, array>
	 */
	public static function get_schema_credentials( int $trustbio_author_id ): array {
		$trustbio_creds  = self::get_for_schema( $trustbio_author_id );
		$trustbio_result = array();

		foreach ( $trustbio_creds as $trustbio_cred ) {
			// Never emit a lapsed credential into the structured data. The badge and
			// text renderers drop expired credentials too, so the page and the schema
			// always agree: the site cannot claim a qualification that has expired.
			if ( ! empty( $trustbio_cred->expiry_date ) && strtotime( $trustbio_cred->expiry_date ) < time() ) {
				continue;
			}

			$trustbio_is_npi = ( 'npi' === ( $trustbio_cred->badge_type ?? '' ) );

			if ( $trustbio_is_npi ) {
				// NPI gets its own structured block per the spec: GovernmentOrganization recognizedBy,
				// credentialCategory, and the NPI number surfaced as the name.
				$trustbio_npi_num = preg_replace( '/\D/', '', (string) $trustbio_cred->license_number );
				$trustbio_npi_url = ! empty( $trustbio_cred->verification_url )
					? $trustbio_cred->verification_url
					: ( $trustbio_npi_num ? 'https://npiregistry.cms.hhs.gov/provider-view/' . $trustbio_npi_num : '' );

				$trustbio_schema_cred = array(
					'@type'              => 'EducationalOccupationalCredential',
					'credentialCategory' => 'NPI',
					'name'               => $trustbio_npi_num ? 'NPI #' . $trustbio_npi_num : $trustbio_cred->credential_name,
					'recognizedBy'       => array(
						'@type' => 'GovernmentOrganization',
						'name'  => 'CMS National Plan & Provider Enumeration System',
					),
				);

				if ( $trustbio_npi_url ) {
					$trustbio_schema_cred['url'] = $trustbio_npi_url;
				}
				if ( ! empty( $trustbio_cred->issue_date ) ) {
					$trustbio_schema_cred['dateCreated'] = $trustbio_cred->issue_date;
				}
			} else {
				$trustbio_schema_cred = array(
					'@type' => 'EducationalOccupationalCredential',
					'name'  => $trustbio_cred->credential_name,
				);

				if ( ! empty( $trustbio_cred->issuing_body ) ) {
					$trustbio_schema_cred['recognizedBy'] = array(
						'@type' => 'Organization',
						'name'  => $trustbio_cred->issuing_body,
					);
				}

				if ( ! empty( $trustbio_cred->issue_date ) ) {
					$trustbio_schema_cred['dateCreated'] = $trustbio_cred->issue_date;
				}

				if ( ! empty( $trustbio_cred->expiry_date ) ) {
					$trustbio_schema_cred['expires'] = $trustbio_cred->expiry_date;
				}

				if ( ! empty( $trustbio_cred->verification_url ) ) {
					$trustbio_schema_cred['url'] = $trustbio_cred->verification_url;
				}
			}

			$trustbio_result[] = $trustbio_schema_cred;
		}

		return $trustbio_result;
	}

	// =========================================================
	// Public: badge types registry
	// =========================================================

	/**
	 * All supported credential badge types with display config.
	 *
	 * @return array<string, array{label: string, color: string, text: string, icon: string, description: string}>
	 */
	public static function get_badge_types(): array {
		return array(
			// Medical.
			'md'      => array(
				'label'       => 'MD',
				'description' => __( 'Medical Doctor', 'trustbio' ),
				'color'       => '#1e40af',
				'text'        => '#ffffff',
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
			),
			'do'      => array(
				'label'       => 'DO',
				'description' => __( 'Doctor of Osteopathic Medicine', 'trustbio' ),
				'color'       => '#1e40af',
				'text'        => '#ffffff',
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
			),
			'rn'      => array(
				'label'       => 'RN',
				'description' => __( 'Registered Nurse', 'trustbio' ),
				'color'       => '#0891b2',
				'text'        => '#ffffff',
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
			),
			'phd'     => array(
				'label'       => 'PhD',
				'description' => __( 'Doctor of Philosophy', 'trustbio' ),
				'color'       => '#6d28d9',
				'text'        => '#ffffff',
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/></svg>',
			),
			// Legal.
			'jd'      => array(
				'label'       => 'JD',
				'description' => __( 'Juris Doctor (Attorney)', 'trustbio' ),
				'color'       => '#92400e',
				'text'        => '#ffffff',
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z" clip-rule="evenodd"/></svg>',
			),
			'esq'     => array(
				'label'       => 'ESQ',
				'description' => __( 'Esquire (Licensed Attorney)', 'trustbio' ),
				'color'       => '#92400e',
				'text'        => '#ffffff',
				'icon'        => '',
			),
			// Financial.
			'cpa'     => array(
				'label'       => 'CPA',
				'description' => __( 'Certified Public Accountant', 'trustbio' ),
				'color'       => '#065f46',
				'text'        => '#ffffff',
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/></svg>',
			),
			'cfp'     => array(
				'label'       => 'CFP',
				'description' => __( 'Certified Financial Planner', 'trustbio' ),
				'color'       => '#065f46',
				'text'        => '#ffffff',
				'icon'        => '',
			),
			// Mental health.
			'lcsw'    => array(
				'label'       => 'LCSW',
				'description' => __( 'Licensed Clinical Social Worker', 'trustbio' ),
				'color'       => '#0f766e',
				'text'        => '#ffffff',
				'icon'        => '',
			),
			'lmft'    => array(
				'label'       => 'LMFT',
				'description' => __( 'Licensed Marriage and Family Therapist', 'trustbio' ),
				'color'       => '#0f766e',
				'text'        => '#ffffff',
				'icon'        => '',
			),
			'lpcc'    => array(
				'label'       => 'LPCC',
				'description' => __( 'Licensed Professional Clinical Counselor', 'trustbio' ),
				'color'       => '#0f766e',
				'text'        => '#ffffff',
				'icon'        => '',
			),
			// Academic.
			'ms'      => array(
				'label'       => 'MS',
				'description' => __( 'Master of Science', 'trustbio' ),
				'color'       => '#4338ca',
				'text'        => '#ffffff',
				'icon'        => '',
			),
			'mba'     => array(
				'label'       => 'MBA',
				'description' => __( 'Master of Business Administration', 'trustbio' ),
				'color'       => '#4338ca',
				'text'        => '#ffffff',
				'icon'        => '',
			),
			// Healthcare identity verification.
			'npi'     => array(
				'label'       => 'NPI',
				'description' => __( 'National Provider Identifier (US healthcare)', 'trustbio' ),
				'color'       => '#0369a1',
				'text'        => '#ffffff',
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
			),
			// General / custom.
			'verified' => array(
				'label'       => 'Verified',
				'description' => __( 'Verified Expert', 'trustbio' ),
				'color'       => '#16a34a',
				'text'        => '#ffffff',
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
			),
			'custom'  => array(
				'label'       => 'Custom',
				'description' => __( 'Custom credential — label auto-set from credential name', 'trustbio' ),
				'color'       => '#374151',
				'text'        => '#ffffff',
				'icon'        => '',
			),
		);
	}

	// =========================================================
	// Private helpers
	// =========================================================

	/**
	 * Validate a date string (YYYY-MM-DD).
	 *
	 * @param string $trustbio_date Date string.
	 * @return bool
	 */
	private function is_valid_date( string $trustbio_date ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $trustbio_date ) ) {
			return false;
		}
		$trustbio_ts = strtotime( $trustbio_date );
		return false !== $trustbio_ts && $trustbio_ts > 0;
	}
}
