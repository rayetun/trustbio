<?php
/**
 * Review Bar builder — full CRUD + AJAX handlers.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Review_Bar
 */
class TrustBio_Review_Bar {

	/** @var string Table name. */
	private string $trustbio_table;

	public function __construct() {
		global $wpdb;
		$this->trustbio_table = $wpdb->prefix . 'trustbio_review_bars';
	}

	// =========================================================
	// Render
	// =========================================================

	public function render(): void {
		require_once TRUSTBIO_PLUGIN_DIR . 'admin/views/review-bar.php';
	}

	// =========================================================
	// AJAX: Save (insert or update)
	// =========================================================

	public function ajax_save(): void {
		check_ajax_referer( 'trustbio_save_review_bar', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_save_review_bar' );

		global $wpdb;

		$trustbio_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$trustbio_title  = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$trustbio_layout = sanitize_key( wp_unslash( $_POST['layout'] ?? 'classic' ) );

		if ( empty( $trustbio_title ) ) {
			wp_send_json_error( array( 'message' => __( 'Bar title is required.', 'trustbio' ) ) );
		}

		$trustbio_valid_layouts = array( 'classic', 'minimal', 'badge-strip', 'pill-duo', 'authority-card', 'dark-shield' );
		if ( ! in_array( $trustbio_layout, $trustbio_valid_layouts, true ) ) {
			$trustbio_layout = 'classic';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$trustbio_raw_settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['settings'] ) )
			: array();
		$trustbio_settings = $this->sanitize_settings( $trustbio_raw_settings, $trustbio_layout );

		$trustbio_data = array(
			'title'    => $trustbio_title,
			'layout'   => $trustbio_layout,
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
			wp_send_json_success( array( 'message' => __( 'Review bar updated.', 'trustbio' ), 'id' => $trustbio_id ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->trustbio_table, $trustbio_data );
		$trustbio_new_id = $wpdb->insert_id;

		if ( ! $trustbio_new_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create review bar.', 'trustbio' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Review bar created.', 'trustbio' ), 'id' => $trustbio_new_id ) );
	}

	// =========================================================
	// AJAX: Delete
	// =========================================================

	public function ajax_delete(): void {
		check_ajax_referer( 'trustbio_delete_review_bar', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_delete_review_bar' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid bar ID.', 'trustbio' ) ) );
		}

		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_bar = $wpdb->get_row( $wpdb->prepare( "SELECT is_default FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );

		if ( ! $trustbio_bar ) {
			wp_send_json_error( array( 'message' => __( 'Review bar not found.', 'trustbio' ) ) );
		}

		if ( $trustbio_bar->is_default ) {
			wp_send_json_error( array( 'message' => __( 'The default review bar cannot be deleted. Set another bar as default first.', 'trustbio' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$this->trustbio_table,
			array( 'id' => $trustbio_id ),
			array( '%d' )
		);

		wp_send_json_success( array( 'message' => __( 'Review bar deleted.', 'trustbio' ) ) );
	}

	// =========================================================
	// AJAX: Set as default
	// =========================================================

	public function ajax_set_default(): void {
		check_ajax_referer( 'trustbio_set_default_review_bar', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_set_default_review_bar' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid bar ID.', 'trustbio' ) ) );
		}

		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "UPDATE `{$trustbio_t}` SET is_default = 0 WHERE is_default = 1" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $this->trustbio_table, array( 'is_default' => 1 ), array( 'id' => $trustbio_id ), array( '%d' ), array( '%d' ) );

		update_option( 'trustbio_default_review_bar_id', $trustbio_id );

		wp_send_json_success( array( 'message' => __( 'Default review bar updated.', 'trustbio' ), 'id' => $trustbio_id ) );
	}

	// =========================================================
	// AJAX: Live preview
	// =========================================================

	public function ajax_preview(): void {
		check_ajax_referer( 'trustbio_preview_review_bar', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_preview_review_bar' );

		$trustbio_layout = 'classic';
		$trustbio_raw    = array();

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
			$trustbio_layout = sanitize_key( wp_unslash( $_POST['layout'] ?? 'classic' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( is_string( $trustbio_settings_raw ) && '' !== $trustbio_settings_raw ) {
			$trustbio_decoded = json_decode( $trustbio_settings_raw, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $trustbio_decoded ) ) {
				if ( isset( $trustbio_decoded['layout'] ) ) {
					$trustbio_layout = sanitize_key( (string) $trustbio_decoded['layout'] );
				}
				foreach ( $trustbio_decoded as $trustbio_k => $trustbio_v ) {
					if ( preg_match( '/^settings\[([^\]]+)\]$/', $trustbio_k, $trustbio_m ) ) {
						$trustbio_raw[ sanitize_key( $trustbio_m[1] ) ] = sanitize_text_field( (string) $trustbio_v );
					}
				}
			}
		}

		$trustbio_settings = $this->sanitize_settings( $trustbio_raw, $trustbio_layout );
		$trustbio_html     = $this->render_preview_html( $trustbio_layout, $trustbio_settings );

		wp_send_json_success( array( 'html' => $trustbio_html ) );
	}

	// =========================================================
	// AJAX: Duplicate
	// =========================================================

	public function ajax_duplicate(): void {
		check_ajax_referer( 'trustbio_duplicate_review_bar', 'nonce' );
		trustbio_verify_ajax_request( 'trustbio_duplicate_review_bar' );

		global $wpdb;

		$trustbio_id = absint( $_POST['id'] ?? 0 );
		if ( ! $trustbio_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid bar ID.', 'trustbio' ) ) );
		}

		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_bar = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );

		if ( ! $trustbio_bar ) {
			wp_send_json_error( array( 'message' => __( 'Review bar not found.', 'trustbio' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->trustbio_table,
			array(
				'title'      => $trustbio_bar->title . __( ' (Copy)', 'trustbio' ),
				'layout'     => $trustbio_bar->layout,
				'settings'   => $trustbio_bar->settings,
				'is_default' => 0,
			)
		);

		$trustbio_new_id = $wpdb->insert_id;

		if ( ! $trustbio_new_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to duplicate review bar.', 'trustbio' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Review bar duplicated.', 'trustbio' ), 'id' => $trustbio_new_id ) );
	}

	// =========================================================
	// Public: get all review bars
	// =========================================================

	/**
	 * Get all review bars.
	 *
	 * @return array<int, object>
	 */
	public function get_all(): array {
		global $wpdb;
		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_bars = $wpdb->get_results( "SELECT * FROM `{$trustbio_t}` ORDER BY is_default DESC, id ASC" );

		foreach ( (array) $trustbio_bars as $trustbio_bar ) {
			if ( ! empty( $trustbio_bar->settings ) ) {
				$trustbio_bar->settings = json_decode( $trustbio_bar->settings, true );
			} else {
				$trustbio_bar->settings = $this->get_default_settings( $trustbio_bar->layout ?? 'classic' );
			}
		}

		return (array) $trustbio_bars;
	}

	/**
	 * Get a single review bar by ID with decoded settings.
	 *
	 * @param int $trustbio_id Bar ID.
	 * @return object|null
	 */
	public function get_box( int $trustbio_id ): ?object {
		global $wpdb;
		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_bar = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$trustbio_t}` WHERE id = %d", $trustbio_id ) );

		if ( $trustbio_bar ) {
			$trustbio_bar->settings = ! empty( $trustbio_bar->settings )
				? json_decode( $trustbio_bar->settings, true )
				: $this->get_default_settings( $trustbio_bar->layout ?? 'classic' );
		}

		return $trustbio_bar;
	}

	/**
	 * Get the default review bar object.
	 *
	 * @return object|null
	 */
	public function get_default_bar(): ?object {
		global $wpdb;
		$trustbio_t = $this->trustbio_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$trustbio_bar = $wpdb->get_row( "SELECT * FROM `{$trustbio_t}` WHERE is_default = 1 LIMIT 1" );

		if ( ! $trustbio_bar ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$trustbio_bar = $wpdb->get_row( "SELECT * FROM `{$trustbio_t}` ORDER BY id ASC LIMIT 1" );
		}

		if ( $trustbio_bar ) {
			$trustbio_bar->settings = ! empty( $trustbio_bar->settings )
				? json_decode( $trustbio_bar->settings, true )
				: $this->get_default_settings( $trustbio_bar->layout ?? 'classic' );
		}

		return $trustbio_bar;
	}

	// =========================================================
	// Default settings
	// =========================================================

	/**
	 * Return the default settings array for a given review bar layout.
	 *
	 * @param string $layout Layout slug.
	 * @return array<string, mixed>
	 */
	public function get_default_settings( string $layout = 'classic' ): array {
		$base = array(
			'accent_color'      => '#4f46e5',
			'label_text'        => '',
			'show_credentials'  => 1,
		);

		switch ( $layout ) {
			case 'classic':
				return array_merge( $base, array( 'show_icon' => 1 ) );
			case 'minimal':
				return array_merge( $base, array( 'show_icon' => 1, 'accent_color' => '#6b7280' ) );
			case 'badge-strip':
				return array_merge( $base, array( 'show_avatar' => 1, 'badge_text' => 'REVIEWED' ) );
			case 'pill-duo':
				return array_merge( $base, array( 'badge_text' => '✓ Verified', 'pill_style' => 'filled', 'accent_color' => '#059669' ) );
			case 'authority-card':
				return array_merge( $base, array( 'show_avatar' => 1, 'corner_radius' => 8 ) );
			case 'dark-shield':
				return array_merge( $base, array( 'bg_color' => '#111827' ) );
			default:
				return $base;
		}
	}

	// =========================================================
	// Private: sanitize settings from POST
	// =========================================================

	/**
	 * Sanitize the review bar settings array from POST data.
	 *
	 * @param array<string, mixed> $trustbio_raw Raw POST settings.
	 * @param string               $trustbio_layout Layout slug.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $trustbio_raw, string $trustbio_layout ): array {
		$trustbio_out      = $this->get_default_settings( $trustbio_layout );

		$trustbio_color_fields = array( 'accent_color', 'bg_color' );
		foreach ( $trustbio_color_fields as $trustbio_key ) {
			if ( ! array_key_exists( $trustbio_key, $trustbio_raw ) ) {
				continue;
			}
			$trustbio_val = sanitize_text_field( wp_unslash( $trustbio_raw[ $trustbio_key ] ) );
			if ( preg_match( '/^#[0-9a-fA-F]{3,6}$/', $trustbio_val ) ) {
				$trustbio_out[ $trustbio_key ] = $trustbio_val;
			}
		}

		if ( array_key_exists( 'corner_radius', $trustbio_raw ) ) {
			$trustbio_out['corner_radius'] = min( 24, max( 0, absint( $trustbio_raw['corner_radius'] ) ) );
		}

		$trustbio_bool_fields = array( 'show_credentials', 'show_icon', 'show_avatar' );
		foreach ( $trustbio_bool_fields as $trustbio_key ) {
			if ( ! array_key_exists( $trustbio_key, $trustbio_raw ) ) {
				continue;
			}
			$trustbio_out[ $trustbio_key ] = ( '' !== $trustbio_raw[ $trustbio_key ] && '0' !== (string) $trustbio_raw[ $trustbio_key ] ) ? 1 : 0;
		}

		$trustbio_text_fields = array( 'label_text', 'badge_text' );
		foreach ( $trustbio_text_fields as $trustbio_key ) {
			if ( ! array_key_exists( $trustbio_key, $trustbio_raw ) ) {
				continue;
			}
			$trustbio_out[ $trustbio_key ] = sanitize_text_field( wp_unslash( $trustbio_raw[ $trustbio_key ] ) );
		}

		if ( array_key_exists( 'pill_style', $trustbio_raw ) ) {
			$trustbio_pill = sanitize_text_field( wp_unslash( $trustbio_raw['pill_style'] ) );
			$trustbio_out['pill_style'] = in_array( $trustbio_pill, array( 'filled', 'outlined' ), true ) ? $trustbio_pill : 'filled';
		}

		return $trustbio_out;
	}

	// =========================================================
	// Public: card list preview + thumb HTML
	// =========================================================

	/**
	 * Render a small card thumbnail preview for the bar grid.
	 *
	 * @param object $trustbio_bar Bar object with decoded settings.
	 * @return string HTML.
	 */
	public function render_card_preview( object $trustbio_bar ): string {
		$trustbio_layout = $trustbio_bar->layout ?? 'classic';
		$trustbio_accent = $trustbio_bar->settings['accent_color'] ?? '#4f46e5';

		switch ( $trustbio_layout ) {

			case 'classic':
				return '<div style="border-left:4px solid ' . esc_attr( $trustbio_accent ) . ';background:#f9fafb;border-radius:4px;padding:8px 10px;height:70px;display:flex;align-items:center;gap:8px;">'
					. '<div style="width:20px;height:20px;border-radius:50%;background:' . esc_attr( $trustbio_accent ) . ';flex-shrink:0;display:flex;align-items:center;justify-content:center;">'
					. '<div style="width:6px;height:6px;border-right:2px solid #fff;border-bottom:2px solid #fff;transform:rotate(45deg);margin-bottom:2px;"></div>'
					. '</div>'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:4px;">'
					. '<div style="height:4px;background:#111827;border-radius:2px;width:55%;"></div>'
					. '<div style="height:3px;background:#9ca3af;border-radius:2px;width:40%;"></div>'
					. '</div>'
					. '</div>';

			case 'minimal':
				return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:4px;padding:8px 10px;height:70px;display:flex;align-items:center;gap:8px;">'
					. '<div style="width:16px;height:16px;border-radius:50%;border:2px solid ' . esc_attr( $trustbio_accent ) . ';flex-shrink:0;display:flex;align-items:center;justify-content:center;">'
					. '<div style="width:5px;height:5px;border-right:1px solid ' . esc_attr( $trustbio_accent ) . ';border-bottom:1px solid ' . esc_attr( $trustbio_accent ) . ';transform:rotate(45deg);margin-bottom:1px;"></div>'
					. '</div>'
					. '<div style="height:4px;background:#374151;border-radius:2px;width:60%;"></div>'
					. '</div>';

			case 'badge-strip':
				return '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;height:70px;display:flex;align-items:center;gap:8px;">'
					. '<div style="width:28px;height:28px;border-radius:50%;background:#d1d5db;flex-shrink:0;"></div>'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:4px;">'
					. '<div style="height:3px;background:#111827;border-radius:2px;width:50%;"></div>'
					. '<div style="display:inline-flex;">'
					. '<div style="background:' . esc_attr( $trustbio_accent ) . ';border-radius:3px;padding:1px 5px;height:10px;width:36px;"></div>'
					. '</div>'
					. '</div>'
					. '</div>';

			case 'pill-duo':
				return '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;height:70px;display:flex;flex-direction:column;justify-content:center;gap:6px;">'
					. '<div style="height:3px;background:#374151;border-radius:2px;width:50%;"></div>'
					. '<div style="display:flex;gap:5px;">'
					. '<div style="background:' . esc_attr( $trustbio_accent ) . ';border-radius:999px;height:14px;width:44px;"></div>'
					. '<div style="background:transparent;border:1px solid ' . esc_attr( $trustbio_accent ) . ';border-radius:999px;height:14px;width:36px;"></div>'
					. '</div>'
					. '</div>';

			case 'authority-card':
				return '<div style="background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.10);padding:8px 10px;height:70px;display:flex;align-items:center;gap:8px;">'
					. '<div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#c7d2fe,#818cf8);flex-shrink:0;"></div>'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:3px;">'
					. '<div style="height:4px;background:#111827;border-radius:2px;width:55%;"></div>'
					. '<div style="height:3px;background:#9ca3af;border-radius:2px;width:35%;"></div>'
					. '<div style="height:3px;background:#d1d5db;border-radius:2px;width:45%;"></div>'
					. '</div>'
					. '</div>';

			default: // dark-shield
				$trustbio_bg = $trustbio_bar->settings['bg_color'] ?? '#111827';
				return '<div style="background:' . esc_attr( $trustbio_bg ) . ';border-radius:6px;padding:8px 10px;height:70px;display:flex;align-items:center;gap:8px;">'
					. '<div style="width:20px;height:24px;background:' . esc_attr( $trustbio_accent ) . ';clip-path:polygon(50% 0%,100% 25%,100% 75%,50% 100%,0% 75%,0% 25%);flex-shrink:0;"></div>'
					. '<div style="flex:1;display:flex;flex-direction:column;gap:4px;">'
					. '<div style="height:4px;background:#fff;border-radius:2px;width:55%;opacity:.9;"></div>'
					. '<div style="height:3px;background:rgba(255,255,255,.45);border-radius:2px;width:40%;"></div>'
					. '</div>'
					. '</div>';
		}
	}

	// =========================================================
	// Private: render preview HTML from settings
	// =========================================================

	/**
	 * Generate the live preview HTML for a review bar layout and settings.
	 *
	 * @param string               $trustbio_layout   Layout slug.
	 * @param array<string, mixed> $trustbio_settings Settings array.
	 * @return string HTML.
	 */
	public function render_preview_html( string $trustbio_layout, array $trustbio_settings ): string {
		$reviewer               = new stdClass();
		$reviewer->display_name = 'Dr. Jane Smith';
		$reviewer->credentials  = 'MD, PhD';
		$reviewer->slug         = 'preview';

		$settings             = $trustbio_settings;
		$label                = ! empty( $settings['label_text'] ) ? $settings['label_text'] : __( 'Medically reviewed by', 'trustbio' );
		$accent               = sanitize_hex_color( $settings['accent_color'] ?? '' ) ?: '#4f46e5';
		$reviewer_avatar_url  = ! empty( $settings['show_avatar'] )
			? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y&s=80'
			: '';
		$reviewer_credentials = $reviewer->credentials . ', MBA';
		$formatted_date       = 'April 30, 2026';
		$raw_date             = '2026-04-30';

		wp_enqueue_style( 'trustbio-review-bars' );

		$trustbio_layout_file = TRUSTBIO_PLUGIN_DIR . 'public/templates/review/bar-' . $trustbio_layout . '.php';

		ob_start();
		if ( file_exists( $trustbio_layout_file ) ) {
			include $trustbio_layout_file;
		}
		return ob_get_clean();
	}
}
