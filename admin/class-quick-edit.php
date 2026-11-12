<?php
/**
 * Quick Edit and Bulk Edit integration for TrustBio.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Quick_Edit
 */
class TrustBio_Quick_Edit {

	/**
	 * Register all hooks.
	 */
	public function register_hooks(): void {
		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit' ), 10, 2 );
		add_action( 'bulk_edit_custom_box', array( $this, 'render_bulk_edit' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_quick_edit' ), 10, 2 );
		add_action( 'wp_ajax_trustbio_bulk_edit_save', array( $this, 'ajax_bulk_save' ) );
		add_action( 'manage_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_pages_columns', array( $this, 'add_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add a TrustBio column to the posts/pages list table.
	 *
	 * @param array<string,string> $trustbio_cols Existing columns.
	 * @return array<string,string>
	 */
	public function add_column( array $trustbio_cols ): array {
		$trustbio_cols['trustbio_authors'] = __( 'Authors', 'trustbio' );
		return $trustbio_cols;
	}

	/**
	 * Render the TrustBio authors column cell.
	 *
	 * @param string $trustbio_col_name Column name.
	 * @param int    $trustbio_post_id  Post ID.
	 */
	public function render_column( string $trustbio_col_name, int $trustbio_post_id ): void {
		if ( 'trustbio_authors' !== $trustbio_col_name ) {
			return;
		}

		$trustbio_authors = trustbio_get_post_authors( $trustbio_post_id );
		if ( empty( $trustbio_authors ) ) {
			echo '<span style="color:#9ca3af;font-size:12px;">—</span>';
			return;
		}

		$trustbio_names = array_map(
			static function ( $trustbio_entry ) {
				return esc_html( $trustbio_entry['author']->display_name );
			},
			$trustbio_authors
		);

		echo '<span style="font-size:12px;">' . implode( ', ', $trustbio_names ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render the Quick Edit box fields.
	 *
	 * @param string $trustbio_col_name Column name that triggered this.
	 * @param string $trustbio_post_type Post type.
	 */
	public function render_quick_edit( string $trustbio_col_name, string $trustbio_post_type ): void {
		if ( 'trustbio_authors' !== $trustbio_col_name ) {
			return;
		}

		if ( ! trustbio_is_enabled_for_post_type( $trustbio_post_type ) ) {
			return;
		}

		wp_nonce_field( 'trustbio_quick_edit', 'trustbio_quick_edit_nonce' );
		$trustbio_categories = trustbio_get_categories( true );
		$trustbio_box_class  = new TrustBio_Author_Box();
		$trustbio_all_boxes  = $trustbio_box_class->get_all();
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label><span class="title"><?php esc_html_e( 'TrustBio Author', 'trustbio' ); ?></span></label>
				<div id="tb-qe-author-wrap" style="margin-top:6px;">
					<div class="tb-qe-search-wrap" style="position:relative;">
						<input type="text" id="tb-qe-search" placeholder="<?php esc_attr_e( 'Search and add author…', 'trustbio' ); ?>" autocomplete="off"
							style="width:100%;height:30px;padding:0 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;">
						<div id="tb-qe-results" style="display:none;position:absolute;left:0;right:0;top:32px;background:#fff;border:1px solid #ddd;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:99999;max-height:200px;overflow-y:auto;"></div>
					</div>
					<div id="tb-qe-assigned" style="margin-top:6px;display:flex;flex-direction:column;gap:4px;"></div>
					<input type="hidden" id="tb-qe-authors-json" name="trustbio_authors_json" value="[]">

					<div style="margin-top:8px;">
						<label style="font-size:11px;font-weight:600;color:#555;"><?php esc_html_e( 'Author Box Layout', 'trustbio' ); ?></label>
						<select name="trustbio_box_id" style="width:100%;margin-top:3px;font-size:12px;">
							<option value="0"><?php esc_html_e( '— Default Layout —', 'trustbio' ); ?></option>
							<?php foreach ( $trustbio_all_boxes as $trustbio_box ) : ?>
								<option value="<?php echo esc_attr( $trustbio_box->id ); ?>"><?php echo esc_html( $trustbio_box->title ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div style="margin-top:6px;">
						<label style="font-size:12px;display:flex;align-items:center;gap:6px;">
							<input type="checkbox" name="trustbio_hide_box" value="1">
							<?php esc_html_e( 'Hide author box', 'trustbio' ); ?>
						</label>
					</div>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render the Bulk Edit box fields.
	 *
	 * @param string $trustbio_col_name Column name.
	 * @param string $trustbio_post_type Post type.
	 */
	public function render_bulk_edit( string $trustbio_col_name, string $trustbio_post_type ): void {
		if ( 'trustbio_authors' !== $trustbio_col_name ) {
			return;
		}

		if ( ! trustbio_is_enabled_for_post_type( $trustbio_post_type ) ) {
			return;
		}

		wp_nonce_field( 'trustbio_bulk_edit', 'trustbio_bulk_edit_nonce' );
		$trustbio_categories = trustbio_get_categories( true );
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label><span class="title"><?php esc_html_e( 'TrustBio Author', 'trustbio' ); ?></span></label>
				<div style="margin-top:6px;">
					<div class="tb-be-search-wrap" style="position:relative;">
						<input type="text" id="tb-be-search" placeholder="<?php esc_attr_e( 'Add author to selected posts…', 'trustbio' ); ?>" autocomplete="off"
							style="width:100%;height:30px;padding:0 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;">
						<div id="tb-be-results" style="display:none;position:absolute;left:0;right:0;top:32px;background:#fff;border:1px solid #ddd;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:99999;max-height:200px;overflow-y:auto;"></div>
					</div>
					<div id="tb-be-assigned" style="margin-top:6px;display:flex;flex-direction:column;gap:4px;"></div>
					<input type="hidden" id="tb-be-authors-json" name="trustbio_bulk_authors_json" value="[]">
					<p style="font-size:11px;color:#777;margin:6px 0 0;"><?php esc_html_e( 'Authors will be added to all selected posts.', 'trustbio' ); ?></p>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save Quick Edit data on post save.
	 *
	 * @param int     $trustbio_post_id Post ID.
	 * @param WP_Post $trustbio_post    Post object.
	 */
	public function save_quick_edit( int $trustbio_post_id, WP_Post $trustbio_post ): void {
		if ( ! isset( $_POST['trustbio_quick_edit_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['trustbio_quick_edit_nonce'] ) ),
			'trustbio_quick_edit'
		) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $trustbio_post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Hide box.
		update_post_meta( $trustbio_post_id, '_trustbio_hide_box', isset( $_POST['trustbio_hide_box'] ) ? 1 : 0 );

		// Box ID.
		$trustbio_box_id = absint( $_POST['trustbio_box_id'] ?? 0 );
		if ( $trustbio_box_id ) {
			update_post_meta( $trustbio_post_id, '_trustbio_box_id', $trustbio_box_id );
		} else {
			delete_post_meta( $trustbio_post_id, '_trustbio_box_id' );
		}

		// Authors JSON.
		$trustbio_json = sanitize_text_field( wp_unslash( $_POST['trustbio_authors_json'] ?? '[]' ) );
		$trustbio_assignments = json_decode( $trustbio_json, true );
		if ( is_array( $trustbio_assignments ) && ! empty( $trustbio_assignments ) ) {
			$trustbio_sanitized = array_map( static function ( $trustbio_e ) {
				return array(
					'author_id'   => absint( $trustbio_e['author_id'] ?? 0 ),
					'category_id' => absint( $trustbio_e['category_id'] ?? 0 ) ?: null,
					'sort_order'  => absint( $trustbio_e['sort_order'] ?? 0 ),
				);
			}, $trustbio_assignments );
			update_post_meta( $trustbio_post_id, '_trustbio_authors', $trustbio_sanitized );
		}
	}

	/**
	 * AJAX: Save bulk edit author assignments.
	 */
	public function ajax_bulk_save(): void {
		check_ajax_referer( 'trustbio_bulk_edit_save', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'trustbio' ) ), 403 );
		}

		$trustbio_post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) )
			: array();

		$trustbio_json = sanitize_text_field( wp_unslash( $_POST['authors_json'] ?? '[]' ) );
		$trustbio_add  = json_decode( $trustbio_json, true );

		if ( ! is_array( $trustbio_add ) || empty( $trustbio_add ) ) {
			wp_send_json_error( array( 'message' => __( 'No authors to add.', 'trustbio' ) ) );
		}

		$trustbio_updated = 0;
		foreach ( $trustbio_post_ids as $trustbio_post_id ) {
			if ( ! current_user_can( 'edit_post', $trustbio_post_id ) ) {
				continue;
			}

			$trustbio_existing = get_post_meta( $trustbio_post_id, '_trustbio_authors', true );
			$trustbio_existing = is_array( $trustbio_existing ) ? $trustbio_existing : array();

			// Merge — don't duplicate.
			$trustbio_existing_ids = array_column( $trustbio_existing, 'author_id' );
			foreach ( $trustbio_add as $trustbio_new ) {
				$trustbio_aid = absint( $trustbio_new['author_id'] ?? 0 );
				if ( ! $trustbio_aid || in_array( $trustbio_aid, $trustbio_existing_ids, true ) ) {
					continue;
				}
				$trustbio_existing[] = array(
					'author_id'   => $trustbio_aid,
					'category_id' => absint( $trustbio_new['category_id'] ?? 0 ) ?: null,
					'sort_order'  => count( $trustbio_existing ),
				);
				$trustbio_existing_ids[] = $trustbio_aid;
			}

			update_post_meta( $trustbio_post_id, '_trustbio_authors', $trustbio_existing );
			++$trustbio_updated;
		}

		wp_send_json_success( array(
			/* translators: %d: number of posts updated */
			'message' => sprintf( __( 'Authors added to %d post(s).', 'trustbio' ), $trustbio_updated ),
		) );
	}

	/**
	 * Enqueue scripts for Quick/Bulk Edit on the post list screen.
	 *
	 * @param string $trustbio_hook Current admin hook.
	 */
	public function enqueue_assets( string $trustbio_hook ): void {
		if ( 'edit.php' !== $trustbio_hook ) {
			return;
		}

		// Enqueue as a proper WP-registered file — no inline <script> tags.
		wp_enqueue_script(
			'trustbio-quick-edit',
			TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-quick-edit.js',
			array( 'jquery' ),
			TRUSTBIO_VERSION,
			true
		);

		// Pass nonces and AJAX URL via wp_localize_script.
		wp_localize_script(
			'trustbio-quick-edit',
			'trustbioQE',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'qeNonce'   => wp_create_nonce( 'trustbio_search_authors' ),
				'beNonce'   => wp_create_nonce( 'trustbio_search_authors' ),
				'bulkNonce' => wp_create_nonce( 'trustbio_bulk_edit_save' ),
			)
		);
	}
}
