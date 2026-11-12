<?php
/**
 * Author Box builder — full CRUD + AJAX handlers.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Author_Box
 */
class TrustBio_Author_Box {

	/** @var string Table name. */
	private string $trustbio_table;

	public function __construct() {
		global $wpdb;
		$this->trustbio_table = $wpdb->prefix . 'trustbio_author_boxes';
	}

	// =========================================================
	// Render
	// =========================================================

	public function render(): void {
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/author-box.php';
	}

	// =========================================================
	// AJAX: Save (insert or update)
	// =========================================================

	public function ajax_save(): void {
		check_ajax_referer( 'trustbio_save_box', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_save_box' );

		global $wpdb;

		$trustbio_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		// ── Core fields ──
		$trustbio_title  = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$trustbio_layout = sanitize_key( wp_unslash( $_POST['layout'] ?? 'byline' ) );
		$trustbio_type   = 'default';

		if ( empty( $trustbio_title ) ) {
			wp_send_json_error( array( 'message' => __( 'Box title is required.', 'trustbio' ) ) );
		}

		$trustbio_valid_layouts = array( 'byline', 'magazine', 'quote', 'polaroid', 'night', 'minimal-inline', 'simple', 'duo-light', 'duo-dark' );
		if ( ! in_array( $trustbio_layout, $trustbio_valid_layouts, true ) ) {
			$trustbio_layout = 'byline';
		}

		// ── Style settings ──
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$trustbio_raw_settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['settings'] ) )
			: array();
		$trustbio_settings = $this->sanitize_settings( $trustbio_raw_settings );

		$trustbio_data = array(
			'title'    => $trustbio_title,
			'layout'   => $trustbio_layout,
			'box_type' => $trustbio_type,
			'settings' => wp_json_encode( $trustbio_settings ),
		);

		if ( $trustbio_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->trustbio_table,
				$trustbio_data,
				array( 'id' => $trustbio_id ),
				null,
				array( '%d' )
			);
			wp_send_json_success( array( 'message' => __( 'Author box updated.', 'trustbio' ), 'id' => $trustbio_id ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->trustbio_table, $trustbio_data );
		$trustbio_new_id = $wpdb->insert_id;

		if ( ! $trustbio_new_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create author box.', 'trustbio' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Author box created.', 'trustbio' ), 'id' => $trustbio_new_id ) );
	}

	// =========================================================
	// AJAX: Delete
	// =========================================================

	public function ajax_delete(): void {
		check_ajax_referer( 'trustbio_delete_box', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_delete_box' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid box ID.', 'trustbio' ) ) );
		}

		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_box = $wpdb->get_row( $wpdb->prepare( "SELECT is_default FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );

		if ( $trustbio_box && $trustbio_box->is_default ) {
			wp_send_json_error( array( 'message' => __( 'The default author box cannot be deleted. Set another box as default first.', 'trustbio' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->trustbio_table, array( 'id' => $trustbio_id ), array( '%d' ) );

		wp_send_json_success( array( 'message' => __( 'Author box deleted.', 'trustbio' ) ) );
	}

	// =========================================================
	// AJAX: Set as default
	// =========================================================

	public function ajax_set_default(): void {
		check_ajax_referer( 'trustbio_set_default_box', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_set_default_box' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid box ID.', 'trustbio' ) ) );
		}

		// Clear existing default.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $this->trustbio_table, array( 'is_default' => 0 ), array( 'is_default' => 1 ), array( '%d' ), array( '%d' ) );

		// Set new default.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $this->trustbio_table, array( 'is_default' => 1 ), array( 'id' => $trustbio_id ), array( '%d' ), array( '%d' ) );

		// Also update plugin option.
		update_option( 'trustbio_default_box_id', $trustbio_id );

		wp_send_json_success( array( 'message' => __( 'Default box updated.', 'trustbio' ), 'id' => $trustbio_id ) );
	}

	// =========================================================
	// AJAX: Live preview
	// =========================================================

	public function ajax_preview(): void {
		check_ajax_referer( 'trustbio_preview_box', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_preview_box' );

		$trustbio_layout = 'byline';
		$trustbio_raw    = array();

		// box-preview.js sends all form fields as a JSON string in $_POST['settings'].
		// Decode it and extract the layout + settings[*] keys.
		$trustbio_settings_raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( is_array( $trustbio_settings_raw ) ) {
			/*
			 * Legacy path: settings sent as a PHP array. Keys are sanitised as
			 * well as values -- array_map() alone only reaches the values, and
			 * these keys are used to index the settings array.
			 *
			 * (The JSON branch below is not an unsanitised use of json_decode:
			 * decoding only turns text into an array, and every key and value
			 * that comes out of it is put through sanitize_key() and
			 * sanitize_text_field() before it is used, then validated again by
			 * sanitize_settings().)
			 */
			foreach ( (array) $trustbio_settings_raw as $trustbio_k => $trustbio_v ) {
				$trustbio_raw[ sanitize_key( (string) $trustbio_k ) ] = is_scalar( $trustbio_v )
					? sanitize_text_field( (string) $trustbio_v )
					: '';
			}
			$trustbio_layout = sanitize_key( wp_unslash( $_POST['layout'] ?? 'byline' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( is_string( $trustbio_settings_raw ) && '' !== $trustbio_settings_raw ) {
			$trustbio_decoded = json_decode( $trustbio_settings_raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $trustbio_decoded ) ) {
				// Extract layout from decoded form fields.
				if ( isset( $trustbio_decoded['layout'] ) ) {
					$trustbio_layout = sanitize_key( (string) $trustbio_decoded['layout'] );
				}
				// Extract settings[key] → key from bracket-notation form field names.
				foreach ( $trustbio_decoded as $trustbio_k => $trustbio_v ) {
					if ( preg_match( '/^settings\[([^\]]+)\]$/', $trustbio_k, $trustbio_m ) ) {
						$trustbio_raw[ sanitize_key( $trustbio_m[1] ) ] = sanitize_text_field( (string) $trustbio_v );
					}
				}
			}
		}

		$trustbio_settings = $this->sanitize_settings( $trustbio_raw );
		$trustbio_html     = $this->render_preview_html( $trustbio_layout, $trustbio_settings );

		wp_send_json_success( array( 'html' => $trustbio_html ) );
	}

	// =========================================================
	// AJAX: Duplicate box
	// =========================================================

	public function ajax_duplicate(): void {
		check_ajax_referer( 'trustbio_duplicate_box', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_duplicate_box' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid box ID.', 'trustbio' ) ) );
		}

		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_original = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );

		if ( ! $trustbio_original ) {
			wp_send_json_error( array( 'message' => __( 'Box not found.', 'trustbio' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->trustbio_table,
			array(
				'title'      => $trustbio_original->title . ' ' . __( '(Copy)', 'trustbio' ),
				'layout'     => $trustbio_original->layout,
				'box_type'   => $trustbio_original->box_type,
				'is_default' => 0,
				'settings'   => $trustbio_original->settings,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Box duplicated.', 'trustbio' ), 'id' => $wpdb->insert_id ) );
	}

	// =========================================================
	// AJAX: Wizard quick-create (onboarding step 3)
	// Creates a box with the layout's default settings.
	// =========================================================

	public function ajax_wizard_create_box(): void {
		check_ajax_referer( 'trustbio_save_box', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_save_box' );

		global $wpdb;

		$trustbio_layout = sanitize_key( wp_unslash( $_POST['layout'] ?? 'byline' ) );
		$trustbio_title  = sanitize_text_field( wp_unslash( $_POST['title'] ?? __( 'Default Author Box', 'trustbio' ) ) );

		$trustbio_valid = array( 'byline', 'magazine', 'quote', 'polaroid', 'night', 'minimal-inline', 'simple', 'duo-light', 'duo-dark' );
		if ( ! in_array( $trustbio_layout, $trustbio_valid, true ) ) {
			$trustbio_layout = 'byline';
		}

		if ( empty( $trustbio_title ) ) {
			$trustbio_title = __( 'Default Author Box', 'trustbio' );
		}

		$trustbio_settings = $this->get_default_settings( $trustbio_layout );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->trustbio_table,
			array(
				'title'      => $trustbio_title,
				'layout'     => $trustbio_layout,
				'box_type'   => 'default',
				'is_default' => 0,
				'settings'   => wp_json_encode( $trustbio_settings ),
			)
		);

		$trustbio_new_id = $wpdb->insert_id;
		if ( ! $trustbio_new_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create author box.', 'trustbio' ) ) );
		}

		// Set as default when no default exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_has_default = $wpdb->get_var( "SELECT id FROM {$this->trustbio_table} WHERE is_default = 1 AND box_type = 'default' LIMIT 1" );
		if ( ! $trustbio_has_default ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $this->trustbio_table, array( 'is_default' => 1 ), array( 'id' => $trustbio_new_id ), array( '%d' ), array( '%d' ) );
			update_option( 'trustbio_default_box_id', $trustbio_new_id );
		}

		wp_send_json_success( array(
			'message'  => __( 'Author box created.', 'trustbio' ),
			'id'       => $trustbio_new_id,
			'edit_url' => admin_url( 'admin.php?page=trustbio-boxes&action=edit&id=' . $trustbio_new_id ),
		) );
	}

	/**
	 * Get all author boxes.
	 *
	 * @param string $trustbio_box_type Filter by 'default', 'category', or 'all'.
	 * @return array<int, object>
	 */
	public function get_all( string $trustbio_box_type = 'all' ): array {
		global $wpdb;
		$trustbio_t = $this->trustbio_table;

		if ( 'all' !== $trustbio_box_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_boxes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE box_type = %s ORDER BY is_default DESC, id ASC", $trustbio_box_type ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_boxes = $wpdb->get_results( "SELECT * FROM `{$trustbio_t}` ORDER BY is_default DESC, id ASC" );
		}

		foreach ( (array) $trustbio_boxes as $trustbio_box ) {
			if ( ! empty( $trustbio_box->settings ) ) {
				$trustbio_box->settings = json_decode( $trustbio_box->settings, true );
			} else {
				$trustbio_box->settings = $this->get_default_settings();
			}
		}

		return (array) $trustbio_boxes;
	}

	/**
	 * Get a single box by ID with decoded settings.
	 *
	 * @param int $trustbio_id Box ID.
	 * @return object|null
	 */
	public function get_box( int $trustbio_id ): ?object {
		global $wpdb;
		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_box = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );

		if ( $trustbio_box ) {
			$trustbio_box->settings = ! empty( $trustbio_box->settings )
				? json_decode( $trustbio_box->settings, true )
				: $this->get_default_settings();
		}

		return $trustbio_box;
	}

	/**
	 * Find a box by the slugified version of its title.
	 *
	 * @param string $trustbio_title_slug Slugified box title.
	 * @return object|null
	 */
	public function get_box_by_title_slug( string $trustbio_title_slug ): ?object {
		$trustbio_all = $this->get_all( 'default' );
		foreach ( $trustbio_all as $trustbio_box ) {
			if ( sanitize_title( $trustbio_box->title ) === $trustbio_title_slug ) {
				return $trustbio_box;
			}
		}
		return null;
	}

	/**
	 * Get the global default author box object.
	 *
	 * @return object|null
	 */
	public function get_default_author_box_obj(): ?object {
		global $wpdb;
		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_box = $wpdb->get_row( "SELECT * FROM `{$trustbio_t}` WHERE box_type = 'default' AND is_default = 1 LIMIT 1" );

		if ( ! $trustbio_box ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_box = $wpdb->get_row( "SELECT * FROM `{$trustbio_t}` WHERE box_type = 'default' ORDER BY id ASC LIMIT 1" );
		}

		if ( $trustbio_box ) {
			$trustbio_box->settings = ! empty( $trustbio_box->settings )
				? json_decode( $trustbio_box->settings, true )
				: $this->get_default_settings();
		}

		return $trustbio_box;
	}

	// =========================================================
	// Public: card list preview + thumb HTML
	// =========================================================

	/**
	 * Render a small preview representation for the box card grid.
	 *
	 * @param object $trustbio_box Box object with decoded settings.
	 * @return string HTML.
	 */
	public function render_box_card_preview( object $trustbio_box ): string {
		$trustbio_layout = $trustbio_box->layout ?? 'byline';

		switch ( $trustbio_layout ) {
			case 'magazine':
				return '<div style="background:#0f172a;border-radius:8px;overflow:hidden;display:flex;height:80px;width:100%;">'
					. '<div style="width:34%;background:#1e293b;"></div>'
					. '<div style="flex:1;padding:10px 12px;display:flex;flex-direction:column;justify-content:center;gap:4px;">'
					. '<div style="height:3px;background:#334155;border-radius:2px;width:50%;"></div>'
					. '<div style="height:6px;background:#f1f5f9;border-radius:2px;width:70%;"></div>'
					. '<div style="height:3px;background:#475569;border-radius:2px;"></div>'
					. '<div style="height:3px;background:#475569;border-radius:2px;width:70%;"></div>'
					. '</div></div>';

			case 'quote':
				return '<div style="background:#fafaf9;border:1px solid #e7e5e4;border-radius:8px;padding:12px;display:flex;gap:10px;align-items:flex-start;">'
					. '<div style="flex:1;">'
					. '<div style="font-size:26px;line-height:1;color:#a8a29e;margin-bottom:4px;">&ldquo;</div>'
					. '<div style="height:3px;background:#a8a29e;border-radius:2px;margin-bottom:3px;"></div>'
					. '<div style="height:3px;background:#a8a29e;border-radius:2px;width:70%;"></div>'
					. '</div>'
					. '<div style="flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:3px;padding-top:4px;">'
					. '<div style="width:22px;height:22px;border-radius:50%;background:#d6d3d1;"></div>'
					. '<div style="height:3px;background:#a8a29e;border-radius:2px;width:28px;"></div>'
					. '</div></div>';

			case 'polaroid':
				return '<div style="background:#fef9c3;border-radius:8px;padding:10px 14px;display:flex;gap:10px;align-items:center;">'
					. '<div style="flex-shrink:0;transform:rotate(-5deg);">'
					. '<div style="background:#fff;padding:4px 4px 12px;border:1px solid #e5e7eb;box-shadow:2px 2px 5px rgba(0,0,0,.15);">'
					. '<div style="width:28px;height:26px;background:#fde68a;"></div>'
					. '</div></div>'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:4px;">'
					. '<div style="height:5px;background:#ca8a04;border-radius:2px;width:60%;"></div>'
					. '<div style="height:3.5px;background:#a16207;border-radius:2px;width:40%;"></div>'
					. '<div style="height:3px;background:#fef08a;border-radius:2px;"></div>'
					. '<div style="height:3px;background:#fef08a;border-radius:2px;width:75%;"></div>'
					. '</div></div>';

			case 'night':
				return '<div style="background:#0f172a;border-radius:8px;padding:10px 12px;display:flex;gap:10px;align-items:flex-start;">'
					. '<div style="width:28px;height:28px;border-radius:50%;background:#334155;flex-shrink:0;"></div>'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:4px;">'
					. '<div style="display:flex;"><div style="height:12px;background:#d97706;border-radius:4px;width:36px;"></div></div>'
					. '<div style="height:5px;background:#f1f5f9;border-radius:2px;width:65%;"></div>'
					. '<div style="height:3px;background:#475569;border-radius:2px;"></div>'
					. '<div style="height:3px;background:#475569;border-radius:2px;width:70%;"></div>'
					. '</div></div>';

			case 'minimal-inline':
				return '<div style="border-top:2px solid #111827;border-bottom:1px solid #e5e7eb;padding:10px 4px;display:flex;gap:9px;align-items:flex-start;background:transparent;">'
					. '<div style="width:24px;height:24px;border-radius:50%;background:#e5e7eb;flex-shrink:0;"></div>'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:4px;">'
					. '<div style="height:5px;background:#111827;border-radius:2px;width:55%;"></div>'
					. '<div style="height:3.5px;background:#9ca3af;border-radius:2px;width:35%;"></div>'
					. '<div style="height:3px;background:#e5e7eb;border-radius:2px;margin-top:2px;"></div>'
					. '</div></div>';

			case 'simple':
				return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;display:flex;gap:8px;align-items:flex-start;">'
					. '<div style="width:28px;height:28px;border-radius:4px;background:#e0e7ff;flex-shrink:0;"></div>'
					. '<div style="width:1px;background:#e5e7eb;align-self:stretch;"></div>'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:4px;">'
					. '<div style="height:5px;background:#3730a3;border-radius:2px;width:60%;"></div>'
					. '<div style="height:3px;background:#c7d2fe;border-radius:2px;"></div>'
					. '<div style="height:13px;background:#4f46e5;border-radius:10px;width:36px;margin-top:3px;"></div>'
					. '</div></div>';

			case 'duo-light':
				return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;display:flex;gap:6px;">'
					. '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">'
					. '<div style="width:22px;height:22px;border-radius:50%;background:#c7d2fe;"></div>'
					. '<div style="height:4px;background:#818cf8;border-radius:2px;width:70%;"></div>'
					. '<div style="height:3px;background:#e0e7ff;border-radius:2px;width:80%;"></div>'
					. '</div>'
					. '<div style="width:1px;background:#e5e7eb;align-self:stretch;"></div>'
					. '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">'
					. '<div style="width:22px;height:22px;border-radius:50%;background:#c7d2fe;"></div>'
					. '<div style="height:4px;background:#818cf8;border-radius:2px;width:70%;"></div>'
					. '<div style="height:3px;background:#e0e7ff;border-radius:2px;width:80%;"></div>'
					. '</div></div>';

			case 'duo-dark':
				return '<div style="background:#1e293b;border-radius:8px;padding:10px;display:flex;gap:6px;align-items:center;">'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:3px;">'
					. '<div style="display:flex;gap:5px;align-items:flex-start;">'
					. '<div style="width:20px;height:20px;border-radius:50%;background:#334155;flex-shrink:0;"></div>'
					. '<div style="flex:1;"><div style="height:4px;background:#e2e8f0;border-radius:2px;width:80%;margin-bottom:3px;"></div>'
					. '<div style="height:3px;background:#475569;border-radius:2px;width:65%;"></div></div></div></div>'
					. '<div style="color:#d97706;font-size:14px;font-weight:700;flex-shrink:0;">&amp;</div>'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:3px;">'
					. '<div style="display:flex;gap:5px;align-items:flex-start;">'
					. '<div style="width:20px;height:20px;border-radius:50%;background:#334155;flex-shrink:0;"></div>'
					. '<div style="flex:1;"><div style="height:4px;background:#e2e8f0;border-radius:2px;width:80%;margin-bottom:3px;"></div>'
					. '<div style="height:3px;background:#475569;border-radius:2px;width:65%;"></div></div></div></div></div>';

			default: // byline and any unrecognised layout
				$trustbio_s          = is_array( $trustbio_box->settings ) ? $trustbio_box->settings : $this->get_default_settings();
				$trustbio_text_color = $trustbio_s['name_color'] ?? '#15161a';
				$trustbio_sub_color  = $trustbio_s['bio_color'] ?? '#6b7280';
				return $this->mini_box_html(
					'background:#fff;border-radius:8px;padding:12px;width:100%;',
					$trustbio_text_color,
					$trustbio_sub_color
				);
		}
	}

	/**
	 * Build a tiny box mock-up for the card grid.
	 *
	 * @param string $trustbio_box_style Inline box CSS.
	 * @param string $trustbio_text_col  Name color.
	 * @param string $trustbio_sub_col   Bio color.
	 * @return string
	 */
	private function mini_box_html( string $trustbio_box_style, string $trustbio_text_col, string $trustbio_sub_col ): string {
		return '<div style="' . esc_attr( $trustbio_box_style ) . '">'
			. '<div style="display:flex;gap:8px;align-items:flex-start;">'
			. '<div style="width:28px;height:28px;border-radius:50%;background:#cbd5e1;flex-shrink:0;"></div>'
			. '<div style="flex:1;">'
			. '<div style="height:5px;background:' . esc_attr( $trustbio_text_col ) . ';border-radius:2px;width:70%;margin-bottom:4px;opacity:.8;"></div>'
			. '<div style="height:3px;background:' . esc_attr( $trustbio_sub_col ) . ';border-radius:2px;width:50%;margin-bottom:6px;opacity:.5;"></div>'
			. '<div style="height:2px;background:' . esc_attr( $trustbio_sub_col ) . ';border-radius:2px;opacity:.3;margin-bottom:2px;"></div>'
			. '<div style="height:2px;background:' . esc_attr( $trustbio_sub_col ) . ';border-radius:2px;opacity:.3;width:80%;"></div>'
			. '</div></div></div>';
	}

	/**
	 * Return the thumbnail inner HTML for a layout card in the picker.
	 *
	 * @param string $trustbio_layout Layout key.
	 * @return string Safe HTML (no user input).
	 */
	public function get_layout_thumb_html( string $trustbio_layout ): string {
		switch ( $trustbio_layout ) {
			case 'byline':
				return '<div class="tb-thumb-avatar"></div><div class="tb-thumb-lines" style="flex:1;"><div class="tb-thumb-line bold"></div><div class="tb-thumb-line"></div><div class="tb-thumb-line" style="width:80%;"></div></div><div style="display:flex;flex-direction:column;gap:3px;align-items:flex-end;"><div class="tb-thumb-line" style="width:30px;"></div><div style="display:flex;gap:2px;"><div style="width:8px;height:8px;border-radius:50%;background:#cbd5e1;"></div><div style="width:8px;height:8px;border-radius:50%;background:#cbd5e1;"></div></div></div>';
			case 'magazine':
				return '<div style="width:28px;height:100%;min-height:44px;background:#334155;border-radius:2px;flex-shrink:0;"></div><div style="flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:3px;padding:2px 0;"><div class="tb-thumb-line bold" style="background:#94a3b8;width:70%;"></div><div class="tb-thumb-line" style="background:#64748b;"></div><div class="tb-thumb-line" style="background:#64748b;width:85%;"></div></div>';
			case 'quote':
				return '<div class="tb-thumb-avatar"></div><div class="tb-thumb-lines" style="flex:1;"><div style="height:3px;border-radius:1px;background:#e2d8c0;margin-bottom:2px;width:90%;"></div><div class="tb-thumb-line" style="width:80%;"></div><div class="tb-thumb-line" style="width:60%;"></div></div><div style="display:flex;flex-direction:column;gap:3px;align-items:flex-end;"><div style="width:22px;height:8px;border-radius:4px;background:#334155;"></div></div>';
			case 'polaroid':
				return '<div style="background:#fff;padding:3px 3px 8px;transform:rotate(-3deg);box-shadow:0 2px 6px rgba(0,0,0,.2);flex-shrink:0;width:24px;"><div style="width:18px;height:14px;background:#cbd5e1;"></div></div><div class="tb-thumb-lines" style="flex:1;"><div class="tb-thumb-line bold"></div><div class="tb-thumb-line"></div><div class="tb-thumb-line" style="width:75%;"></div></div>';
			case 'night':
				return '<div style="background:#1e293b;border-radius:4px;padding:5px;display:flex;gap:5px;width:100%;"><div class="tb-thumb-avatar" style="background:#334155;border:1px solid #475569;"></div><div class="tb-thumb-lines" style="flex:1;"><div class="tb-thumb-line bold" style="background:#e2d4a8;"></div><div class="tb-thumb-line" style="background:#64748b;"></div><div class="tb-thumb-line" style="background:#64748b;width:85%;"></div></div></div>';
			case 'minimal-inline':
				return '<div style="width:100%;border-top:1px solid #d1d5db;border-bottom:1px solid #d1d5db;padding:4px 0;display:flex;gap:5px;align-items:center;"><div class="tb-thumb-avatar" style="width:14px;height:14px;flex-shrink:0;"></div><div class="tb-thumb-lines" style="flex:1;"><div class="tb-thumb-line bold" style="width:50%;"></div><div class="tb-thumb-line" style="width:80%;"></div></div></div>';
			case 'simple':
				return '<div class="tb-thumb-avatar" style="width:14px;height:14px;border-radius:3px;flex-shrink:0;"></div><div style="width:1px;background:#e5e7eb;align-self:stretch;"></div><div class="tb-thumb-lines" style="flex:1;"><div class="tb-thumb-line" style="width:80%;"></div></div><div style="width:16px;height:8px;border-radius:2px;background:#334155;flex-shrink:0;"></div>';
			case 'duo-light':
				return '<div style="display:grid;grid-template-columns:1fr 1px 1fr;gap:4px;width:100%;"><div style="display:flex;gap:3px;"><div class="tb-thumb-avatar" style="width:12px;height:12px;flex-shrink:0;"></div><div class="tb-thumb-lines"><div class="tb-thumb-line bold"></div><div class="tb-thumb-line"></div></div></div><div style="background:#e5e7eb;"></div><div style="display:flex;gap:3px;"><div class="tb-thumb-avatar" style="width:12px;height:12px;flex-shrink:0;"></div><div class="tb-thumb-lines"><div class="tb-thumb-line bold"></div><div class="tb-thumb-line"></div></div></div></div>';
			case 'duo-dark':
				return '<div style="background:#1e293b;border-radius:4px;padding:4px;display:grid;grid-template-columns:1fr 10px 1fr;gap:3px;width:100%;"><div style="display:flex;gap:2px;"><div class="tb-thumb-avatar" style="width:10px;height:10px;border:1px solid #475569;flex-shrink:0;"></div><div class="tb-thumb-lines"><div class="tb-thumb-line bold" style="background:#e2d4a8;"></div><div class="tb-thumb-line" style="background:#475569;"></div></div></div><div style="display:flex;align-items:center;justify-content:center;font-size:8px;color:#e2d4a8;font-style:italic;">&amp;</div><div style="display:flex;gap:2px;"><div class="tb-thumb-avatar" style="width:10px;height:10px;border:1px solid #475569;flex-shrink:0;"></div><div class="tb-thumb-lines"><div class="tb-thumb-line bold" style="background:#e2d4a8;"></div><div class="tb-thumb-line" style="background:#475569;"></div></div></div></div>';
			default:
				return '<div class="tb-thumb-avatar"></div><div class="tb-thumb-lines"><div class="tb-thumb-line bold"></div><div class="tb-thumb-line"></div></div>';
		}
	}

	// =========================================================
	// Default settings
	// =========================================================

	/**
	 * Return the default settings array for a given layout.
	 * Each layout has its own appropriate colour defaults so switching
	 * layouts resets to sensible values for that layout.
	 *
	 * @param string $trustbio_layout Layout slug.
	 * @return array<string, mixed>
	 */
	public function get_default_settings( string $trustbio_layout = 'byline' ): array {
		$trustbio_base = array(
			'bg_color'         => '',
			'border_color'     => '',
			'btn_color'        => '',
			'btn_text_color'   => '',
			'name_color'       => '#15161a',
			'name_size'        => 18,
			'name_weight'      => '700',
			'bio_color'        => '#36352f',
			'bio_size'         => 14,
			'bio_limit'        => 0,
			'jobtitle_color'   => '#6b7280',
			'jobtitle_size'    => 12,
			'icon_size'        => 13,
			'icon_color'       => '#374151',
			'custom_class'     => '',
			'font_heading'     => '',
			'font_label'       => '',
			'show_review_date' => true,
			/*
			 * sanitize_settings() falls back to this array by key, so a key it
			 * reads but this array lacks raised an undefined-index warning and
			 * sanitised to null. These are the ones that were missing.
			 */
			'avatar_shape'     => 'circle',
			'avatar_size'      => 96,
			'box_shadow'       => true,
			'icon_style'       => 'color',
			'label_color'      => '#8a877d',
			'label_size'       => 10,
			'label_weight'     => '700',
			'border_style'     => 'none',
			'border_width'     => 0,
			'border_top'       => 0,
			'border_right'     => 0,
			'border_bottom'    => 0,
			'border_left'      => 0,
			'border_radius'    => 0,
			'padding'          => 0,
		);

		/*
		 * Avatar size per layout: these are the values trustbio-public.css
		 * hard-coded before the slider was wired up, so a new box of any
		 * layout starts out looking exactly as that layout always has.
		 */
		$trustbio_layout_avatar = array(
			'byline'         => 96,
			'quote'          => 120,
			'minimal-inline' => 56,
			'simple'         => 64,
			'night'          => 72,
			'duo-light'      => 64,
			'duo-dark'       => 64,
		);

		$trustbio_layout_overrides = array(
			'magazine' => array(
				'name_color' => '#efece4',
				'bio_color'  => '#c8c4ba',
			),
			'night' => array(
				'name_color' => '#efece4',
				'bio_color'  => '#c8c4ba',
				'icon_color' => '#d9c08d',
			),
			'duo-dark' => array(
				'name_color' => '#efece4',
				'bio_color'  => '#c8c4ba',
				'icon_color' => '#d9c08d',
			),
		);

		$trustbio_overrides = $trustbio_layout_overrides[ $trustbio_layout ] ?? array();
		if ( isset( $trustbio_layout_avatar[ $trustbio_layout ] ) ) {
			$trustbio_overrides['avatar_size'] = $trustbio_layout_avatar[ $trustbio_layout ];
		}
		return array_merge( $trustbio_base, $trustbio_overrides );
	}

	// =========================================================
	// Private: sanitize settings from POST
	// =========================================================

	/**
	 * Sanitize the settings array from POST data.
	 *
	 * @param array<string, mixed> $trustbio_raw Raw POST settings.
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( array $trustbio_raw ): array {
		$trustbio_defaults = $this->get_default_settings();
		$trustbio_out      = array();

		// Colour fields.
		$trustbio_color_fields = array( 'bg_color', 'border_color', 'btn_color', 'btn_text_color', 'name_color', 'bio_color', 'label_color', 'jobtitle_color', 'icon_color' );
		foreach ( $trustbio_color_fields as $trustbio_key ) {
			$trustbio_val               = sanitize_text_field( wp_unslash( $trustbio_raw[ $trustbio_key ] ?? $trustbio_defaults[ $trustbio_key ] ) );
			$trustbio_out[ $trustbio_key ] = preg_match( '/^#[0-9a-fA-F]{3,6}$/', $trustbio_val ) ? $trustbio_val : $trustbio_defaults[ $trustbio_key ];
		}

		// Integer fields.
		$trustbio_int_fields = array( 'border_width', 'border_top', 'border_right', 'border_bottom', 'border_left', 'border_radius', 'padding', 'avatar_size', 'name_size', 'bio_size', 'bio_limit', 'label_size', 'jobtitle_size', 'icon_size' );
		foreach ( $trustbio_int_fields as $trustbio_key ) {
			$trustbio_out[ $trustbio_key ] = absint( $trustbio_raw[ $trustbio_key ] ?? $trustbio_defaults[ $trustbio_key ] );
		}

		// Boolean fields.
		$trustbio_out['box_shadow']         = ! empty( $trustbio_raw['box_shadow'] );
		$trustbio_out['show_review_date']   = ! empty( $trustbio_raw['show_review_date'] );

		// Enum fields.
		$trustbio_valid_border_styles = array( 'none', 'solid', 'dashed', 'dotted' );
		$trustbio_border_style        = sanitize_key( $trustbio_raw['border_style'] ?? 'solid' );
		$trustbio_out['border_style'] = in_array( $trustbio_border_style, $trustbio_valid_border_styles, true ) ? $trustbio_border_style : 'solid';

		$trustbio_valid_shapes      = array( 'circle', 'rounded', 'square' );
		$trustbio_avatar_shape      = sanitize_key( $trustbio_raw['avatar_shape'] ?? 'circle' );
		$trustbio_out['avatar_shape'] = in_array( $trustbio_avatar_shape, $trustbio_valid_shapes, true ) ? $trustbio_avatar_shape : 'circle';

		$trustbio_valid_weights   = array( '400', '500', '600', '700', '800' );
		$trustbio_name_weight     = sanitize_key( $trustbio_raw['name_weight'] ?? '700' );
		$trustbio_out['name_weight'] = in_array( $trustbio_name_weight, $trustbio_valid_weights, true ) ? $trustbio_name_weight : '700';

		$trustbio_label_weight     = sanitize_key( $trustbio_raw['label_weight'] ?? '700' );
		$trustbio_out['label_weight'] = in_array( $trustbio_label_weight, $trustbio_valid_weights, true ) ? $trustbio_label_weight : '700';

		$trustbio_valid_icon_styles = array( 'color', 'monochrome', 'outline' );
		$trustbio_icon_style        = sanitize_key( $trustbio_raw['icon_style'] ?? 'color' );
		$trustbio_out['icon_style'] = in_array( $trustbio_icon_style, $trustbio_valid_icon_styles, true ) ? $trustbio_icon_style : 'color';

		// Font stack fields — whitelist allowed values only.
		$trustbio_valid_fonts = array(
			'',
			'system-ui,-apple-system,sans-serif',
			'Optima,Candara,"Noto Sans",sans-serif',
			"'Palatino Linotype',Palatino,Georgia,serif",
			'inherit',
		);
		$trustbio_font_h = sanitize_text_field( wp_unslash( $trustbio_raw['font_heading'] ?? '' ) );
		$trustbio_out['font_heading'] = in_array( $trustbio_font_h, $trustbio_valid_fonts, true ) ? $trustbio_font_h : '';

		$trustbio_valid_label_fonts = array(
			'',
			'system-ui,-apple-system,sans-serif',
			'inherit',
		);
		$trustbio_font_l = sanitize_text_field( wp_unslash( $trustbio_raw['font_label'] ?? '' ) );
		$trustbio_out['font_label'] = in_array( $trustbio_font_l, $trustbio_valid_label_fonts, true ) ? $trustbio_font_l : '';

		// Custom class — sanitize each class name individually to preserve spaces.
		$trustbio_raw_class = wp_strip_all_tags( wp_unslash( $trustbio_raw['custom_class'] ?? '' ) );
		$trustbio_out['custom_class'] = implode( ' ', array_filter( array_map(
			'sanitize_html_class',
			preg_split( '/\s+/', trim( $trustbio_raw_class ) )
		) ) );

		return $trustbio_out;
	}

	// =========================================================
	// Private: render preview HTML from settings
	// =========================================================

	/**
	 * Generate the live preview HTML for a given layout and settings.
	 * Uses the real public CSS classes — layouts are purely class-driven.
	 * Only typography settings (color/size) are applied as inline overrides.
	 *
	 * @param string               $trustbio_layout   Layout slug.
	 * @param array<string, mixed> $trustbio_settings Settings array.
	 * @return string HTML.
	 */
	private function render_preview_html( string $trustbio_layout, array $trustbio_settings ): string {
		$trustbio_s = $trustbio_settings;

		// Build wrapper inline style (bg, border, radius, padding, font CSS vars).
		$trustbio_wrapper_style = trustbio_build_wrapper_style( $trustbio_s );
		$trustbio_ws            = $trustbio_wrapper_style ? ' style="' . esc_attr( $trustbio_wrapper_style ) . '"' : '';

		// Typography inline overrides — the only user-controlled CSS variables in the class-based model.
		$trustbio_ns = esc_attr( sprintf(
			'font-size:%dpx;font-weight:%s;color:%s;',
			(int) ( $trustbio_s['name_size'] ?? 18 ),
			esc_attr( $trustbio_s['name_weight'] ?? '700' ),
			esc_attr( $trustbio_s['name_color'] ?? '#15161a' )
		) );
		$trustbio_bs = esc_attr( sprintf(
			'font-size:%dpx;color:%s;',
			(int) ( $trustbio_s['bio_size'] ?? 14 ),
			esc_attr( $trustbio_s['bio_color'] ?? '#36352f' )
		) );

		$trustbio_icons = $this->get_sample_social_icons( $trustbio_s );

		// Sample data (no user input — safe to inline directly after esc_html).
		$trustbio_nm  = esc_html__( 'Dr. Sarah Johnson', 'trustbio' );
		$trustbio_job = esc_html__( 'Medical Reviewer, MD', 'trustbio' );
		$trustbio_role  = esc_html__( 'Medically Reviewed By', 'trustbio' );

		// Apply bio_limit to sample text so preview reflects the setting.
		$trustbio_bio_full = __( 'Board-certified physician specialising in addiction medicine with over 15 years of clinical experience reviewing health content for accuracy and compliance.', 'trustbio' );
		$trustbio_bio_full_s = __( 'Board-certified physician specialising in addiction medicine.', 'trustbio' );
		$trustbio_bio_limit_val = (int) ( $trustbio_s['bio_limit'] ?? 0 );
		$trustbio_bio   = esc_html( trustbio_apply_bio_limit( $trustbio_bio_full, $trustbio_bio_limit_val ) );
		$trustbio_bio_s = esc_html( trustbio_apply_bio_limit( $trustbio_bio_full_s, $trustbio_bio_limit_val ) );
		$trustbio_av64  = 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=96';
		$trustbio_av96  = 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&s=300';

		switch ( $trustbio_layout ) {

			case 'byline':
				return '<div class="trustbio-box trustbio-layout-byline"' . $trustbio_ws . '>'
					. '<img class="tb-avatar" src="' . esc_url( $trustbio_av64 ) . '" alt="" width="96" height="96">'
					. '<div class="tb-body">'
					. '<div class="tb-kicker">' . $trustbio_role . '</div>'
					. '<h2 class="tb-name" style="' . $trustbio_ns . '"><a href="#" style="color:inherit;text-decoration:none;">' . $trustbio_nm . '</a></h2>'
					. '<p class="tb-bio" style="' . $trustbio_bs . '">' . $trustbio_bio . '</p>'
					. '</div>'
					. '<div class="tb-side">'
					. '<div class="tb-meta"><span><b>42</b> ' . esc_html__( 'posts', 'trustbio' ) . '</span></div>'
					. '<div class="tb-socials">' . $trustbio_icons . '</div>'
					. '</div>'
					. '</div>';

			case 'magazine':
				return '<div class="trustbio-box trustbio-layout-magazine"' . $trustbio_ws . '>'
					. '<div class="tb-portrait-col">'
					. '<img class="tb-portrait-img" src="' . esc_url( $trustbio_av96 ) . '" alt="">'
					. '<span class="tb-badge">' . $trustbio_role . '</span>'
					. '</div>'
					. '<div class="tb-right"><div>'
					. '<div class="tb-role">' . $trustbio_job . '</div>'
					. '<h2 class="tb-name" style="' . $trustbio_ns . '"><a href="#" style="color:inherit;text-decoration:none;">' . $trustbio_nm . '</a></h2>'
					. '<p class="tb-bio" style="' . $trustbio_bs . '">' . $trustbio_bio . '</p>'
					. '</div>'
					. '<div class="tb-foot"><div class="tb-socials tb-socials--dark">' . $trustbio_icons . '</div></div>'
					. '</div>'
					. '</div>';

			case 'quote':
				return '<div class="trustbio-box trustbio-layout-quote"' . $trustbio_ws . '>'
					. '<img class="tb-avatar" src="' . esc_url( $trustbio_av64 ) . '" alt="" width="120" height="120">'
					. '<div class="tb-body">'
					. '<p class="tb-quote">' . $trustbio_bio_s . '</p>'
					. '<div class="tb-byline">'
					. '<span class="tb-byline-name"><a href="#" style="color:inherit;text-decoration:none;">' . $trustbio_nm . '</a></span>'
					. '<span class="tb-byline-sep"></span>'
					. '<span class="tb-byline-role">' . $trustbio_job . '</span>'
					. '</div>'
					. '</div>'
					. '<div class="tb-right"><div class="tb-socials">' . $trustbio_icons . '</div></div>'
					. '</div>';

			case 'polaroid':
				return '<div class="trustbio-box trustbio-layout-polaroid"' . $trustbio_ws . '>'
					. '<div class="tb-polaroid-frame">'
					. '<img class="tb-polaroid-img" src="' . esc_url( $trustbio_av96 ) . '" alt="">'
					. '<div class="tb-polaroid-cap">' . esc_html__( 'Sarah', 'trustbio' ) . '</div>'
					. '</div>'
					. '<div class="tb-body">'
					. '<span class="tb-label-pill">' . esc_html__( 'About the author', 'trustbio' ) . '</span>'
					. '<h2 class="tb-name" style="' . $trustbio_ns . '"><a href="#" style="color:inherit;text-decoration:none;">' . $trustbio_nm . '</a></h2>'
					. '<p class="tb-bio" style="' . $trustbio_bs . '">' . $trustbio_bio_s . '</p>'
					. '<div class="tb-foot">'
					. '<a href="#" class="tb-more-link">' . esc_html__( 'Read more', 'trustbio' ) . ' &rarr;</a>'
					. '<div class="tb-socials">' . $trustbio_icons . '</div>'
					. '</div>'
					. '</div>'
					. '</div>';

			case 'night':
				return '<div class="trustbio-box trustbio-layout-night"' . $trustbio_ws . '>'
					. '<div>'
					. '<div class="tb-top">'
					. '<img class="tb-avatar" src="' . esc_url( $trustbio_av64 ) . '" alt="" width="72" height="72">'
					. '<div>'
					. '<div class="tb-badge-pill"><span class="tb-badge-dot" aria-hidden="true"></span>' . $trustbio_role . '</div>'
					. '<h2 class="tb-name" style="' . $trustbio_ns . '"><a href="#" style="color:inherit;text-decoration:none;">' . $trustbio_nm . '</a></h2>'
					. '<div class="tb-role">' . $trustbio_job . '</div>'
					. '</div>'
					. '</div>'
					. '<p class="tb-bio" style="' . $trustbio_bs . '">' . $trustbio_bio . '</p>'
					. '</div>'
					. '<div class="tb-right"><div class="tb-actions">'
					. '<a href="#" class="tb-follow">' . esc_html__( 'View all posts', 'trustbio' ) . ' &rarr;</a>'
					. '<div class="tb-socials tb-socials--dark">' . $trustbio_icons . '</div>'
					. '</div></div>'
					. '</div>';

			case 'minimal-inline':
				return '<div class="trustbio-box trustbio-layout-minimal-inline"' . $trustbio_ws . '>'
					. '<img class="tb-avatar" src="' . esc_url( $trustbio_av64 ) . '" alt="" width="56" height="56">'
					. '<div class="tb-body">'
					. '<div class="tb-kicker">' . esc_html__( 'Written by', 'trustbio' ) . ' &middot; 16 May 2026</div>'
					. '<h2 class="tb-name" style="' . $trustbio_ns . '"><a href="#" style="color:inherit;text-decoration:none;">' . $trustbio_nm . '</a>'
					. '<span style="font-weight:400;color:#8a877d;"> &middot; ' . $trustbio_job . '</span></h2>'
					. '<p class="tb-bio" style="' . $trustbio_bs . '">' . $trustbio_bio_s . '</p>'
					. '</div>'
					. '<div class="tb-socials tb-socials--ghost">' . $trustbio_icons . '</div>'
					. '</div>';

			case 'simple':
				return '<div class="trustbio-box trustbio-layout-simple"' . $trustbio_ws . '>'
					. '<div class="tb-left">'
					. '<img class="tb-avatar" src="' . esc_url( $trustbio_av64 ) . '" alt="" width="64" height="64">'
					. '<div><div class="tb-role">' . $trustbio_role . '</div>'
					. '<h2 class="tb-name" style="' . $trustbio_ns . '"><a href="#" style="color:inherit;text-decoration:none;">' . $trustbio_nm . '</a></h2>'
					. '</div></div>'
					. '<div class="tb-rule" aria-hidden="true"></div>'
					. '<p class="tb-body" style="' . $trustbio_bs . '">' . $trustbio_bio_s . ' <a href="#">' . esc_html__( 'All posts &rarr;', 'trustbio' ) . '</a></p>'
					. '<div class="tb-right"><a href="#" class="tb-follow-btn">' . esc_html__( 'Follow', 'trustbio' ) . '</a></div>'
					. '</div>';

			case 'duo-light':
			case 'duo-dark':
				$trustbio_is_dark   = 'duo-dark' === $trustbio_layout;
				$trustbio_soc_cls   = $trustbio_is_dark ? ' tb-socials--dark' : '';
				$trustbio_topline   = $trustbio_is_dark
					? '<div class="tb-topline"><span class="tb-topline-label"><span class="tb-topline-dot" aria-hidden="true"></span>'
						. esc_html__( 'Reported &amp; written by · 2 authors', 'trustbio' )
						. '</span><span>' . esc_html( gmdate( 'j M Y' ) ) . '</span></div>'
					: '';
				$trustbio_gutter    = $trustbio_is_dark
					? '<div class="tb-gutter" aria-hidden="true"><span class="tb-ampersand">&amp;</span></div>'
					: '<div class="tb-divider-v" aria-hidden="true"></div>';

				$trustbio_make_col = function ( string $num, string $nm ) use ( $trustbio_av64, $trustbio_icons, $trustbio_ns, $trustbio_bs, $trustbio_bio_s, $trustbio_job, $trustbio_role, $trustbio_soc_cls ): string {
					return '<div class="tb-author-col">'
						. '<div class="tb-avatar-wrap">'
						. '<img class="tb-avatar" src="' . esc_url( $trustbio_av64 ) . '" alt="" width="64" height="64">'
						. ( $num ? '<span class="tb-author-num" aria-hidden="true">' . esc_html( $num ) . '</span>' : '' )
						. '</div>'
						. '<div class="tb-info">'
						. '<div class="tb-role">' . esc_html( $trustbio_role ) . '</div>'
						. '<h3 class="tb-name"><a href="#" style="color:inherit;text-decoration:none;' . $trustbio_ns . '">' . esc_html( $nm ) . '</a></h3>'
						. '<p class="tb-bio" style="' . $trustbio_bs . '">' . esc_html( $trustbio_bio_s ) . '</p>'
						. '<div class="tb-socials' . esc_attr( $trustbio_soc_cls ) . '">' . $trustbio_icons . '</div>'
						. '</div></div>';
				};

				return '<div class="trustbio-box trustbio-layout-' . esc_attr( $trustbio_layout ) . '"' . $trustbio_ws . '>'
					. $trustbio_topline
					. '<div class="tb-inner">'
					. $trustbio_make_col( $trustbio_is_dark ? '01' : '', 'Dr. Sarah Johnson' )
					. $trustbio_gutter
					. $trustbio_make_col( $trustbio_is_dark ? '02' : '', 'Marcus Webb' )
					. '</div></div>';

			default:
				return $this->render_preview_html( 'byline', $trustbio_settings );
		}
	}

	/**
	 * Build sample social icon buttons for the preview.
	 *
	 * @param int    $trustbio_size  Icon size in px.
	 * @param string $trustbio_style Style: color, monochrome, outline.
	 * @return string HTML.
	 */
	/**
	 * Three sample social glyphs for the live preview.
	 *
	 * Deliberately the same markup and the same SVG source the front end uses
	 * (.tb-social-link + trustbio_get_social_svg), so the chip, its border and
	 * the glyph size all come from the CSS custom properties the wrapper style
	 * already carries. The preview used to draw its own inline-styled chips,
	 * which is how "Icon Style" came to change the preview while changing
	 * nothing on the page -- and how "Icon Color" did the reverse.
	 *
	 * @param array $trustbio_s Box settings.
	 * @return string HTML.
	 */
	private function get_sample_social_icons( array $trustbio_s ): string {
		$trustbio_fill = trustbio_social_icon_fill( $trustbio_s );
		$trustbio_size = (int) ( $trustbio_s['icon_size'] ?? 13 );

		$trustbio_out = '';
		foreach ( array( 'twitter_x', 'linkedin', 'facebook' ) as $trustbio_slug ) {
			// Spans, not links: the preview is not navigable.
			$trustbio_out .= '<span class="tb-social-link">'
				. trustbio_get_social_svg( $trustbio_slug, $trustbio_size, $trustbio_fill )
				. '</span>';
		}

		return $trustbio_out;
	}
}
