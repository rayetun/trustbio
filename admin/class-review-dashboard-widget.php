<?php
/**
 * Phase 16 — "Last Reviewed" Dashboard Widget.
 *
 * Shows editors which posts are most overdue for review,
 * with colour coding and a one-click "Mark reviewed today" action.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Review_Dashboard_Widget
 */
class TrustBio_Review_Dashboard_Widget {

	// =========================================================
	// Assets — enqueued only on the dashboard screen
	// =========================================================

	/**
	 * Enqueue the widget JS on the dashboard screen only.
	 * Hooked to admin_enqueue_scripts.
	 *
	 * @param string $trustbio_hook Current admin page hook.
	 */
	public function enqueue_assets( string $trustbio_hook ): void {
		if ( 'index.php' !== $trustbio_hook ) {
			return;
		}

		$trustbio_ver = defined( 'WP_DEBUG' ) && WP_DEBUG
			? (string) filemtime( TRUSTBIO_PLUGIN_DIR . 'admin/js/trustbio-review-widget.js' )
			: TRUSTBIO_VERSION;

		// Enqueue admin CSS so widget styles load on the dashboard.
		wp_enqueue_style(
			'trustbio-admin',
			TRUSTBIO_PLUGIN_URL . 'admin/css/trustbio-admin.css',
			array(),
			$trustbio_ver
		);

		wp_enqueue_script(
			'trustbio-review-widget',
			TRUSTBIO_PLUGIN_URL . 'admin/js/trustbio-review-widget.js',
			array( 'jquery' ),
			$trustbio_ver,
			true
		);

		wp_localize_script(
			'trustbio-review-widget',
			'trustbioWidgetI18n',
			array(
				'saving'   => __( 'Saving…', 'trustbio' ),
				'done'     => __( '✓ Done', 'trustbio' ),
				'upToDate' => __( 'Up to date', 'trustbio' ),
			)
		);
	}

	// =========================================================
	// Registration
	// =========================================================

	/**
	 * Register the dashboard widget.
	 * Hooked to wp_dashboard_setup.
	 */
	public function register(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'trustbio_review_due',
			__( 'TrustBio — Content Review Status', 'trustbio' ),
			array( $this, 'render' ),
			array( $this, 'render_control' )    // Control callback = settings form inside widget.
		);
	}

	// =========================================================
	// Widget control (settings form inside the widget)
	// =========================================================

	/**
	 * Render the widget configuration form (shown when clicking the widget's "Screen Options").
	 */
	public function render_control(): void {
		$trustbio_count = (int) get_option( 'trustbio_widget_review_count', 10 );
		$trustbio_types = trustbio_get_enabled_post_types();

		// Handle save from the widget control form.
		if ( isset( $_POST['trustbio_widget_nonce'] ) && wp_verify_nonce(
			sanitize_key( $_POST['trustbio_widget_nonce'] ),
			'trustbio_widget_save'
		) ) {
			$trustbio_count = absint( $_POST['trustbio_widget_count'] ?? 10 );
			update_option( 'trustbio_widget_review_count', max( 1, min( 50, $trustbio_count ) ) );
		}
		?>
		<p>
			<label for="tb-widget-count">
				<?php esc_html_e( 'Number of posts to show:', 'trustbio' ); ?>
			</label>
			<input
				type="number"
				id="tb-widget-count"
				name="trustbio_widget_count"
				value="<?php echo esc_attr( $trustbio_count ); ?>"
				min="1" max="50"
				style="width:60px;margin-left:6px;"
			>
		</p>
		<?php wp_nonce_field( 'trustbio_widget_save', 'trustbio_widget_nonce' ); ?>
		<?php
	}

	// =========================================================
	// Widget body
	// =========================================================

	/**
	 * Render the widget content.
	 */
	public function render(): void {
		$trustbio_count   = (int) get_option( 'trustbio_widget_review_count', 10 );
		$trustbio_stale   = (int) get_option( 'trustbio_review_stale_months', 12 );
		$trustbio_types   = trustbio_get_enabled_post_types();
		$trustbio_nonce   = wp_create_nonce( 'trustbio_mark_reviewed' );

		// Collect all candidate posts, deduplicated by post ID.
		$trustbio_seen    = array();
		$trustbio_all     = array();

		foreach ( $this->get_overdue_posts( $trustbio_types, $trustbio_count ) as $trustbio_p ) {
			if ( ! isset( $trustbio_seen[ $trustbio_p->ID ] ) ) {
				$trustbio_seen[ $trustbio_p->ID ] = true;
				$trustbio_all[] = $trustbio_p;
			}
		}
		foreach ( $this->get_never_reviewed_posts( $trustbio_types, $trustbio_count ) as $trustbio_p ) {
			if ( ! isset( $trustbio_seen[ $trustbio_p->ID ] ) ) {
				$trustbio_seen[ $trustbio_p->ID ] = true;
				$trustbio_all[] = $trustbio_p;
			}
		}
		$trustbio_all = array_slice( $trustbio_all, 0, $trustbio_count );

		if ( empty( $trustbio_all ) ) {
			echo '<div class="tb-widget-empty">'
				. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>'
				. '<p>' . esc_html__( 'All content is up to date.', 'trustbio' ) . '</p>'
				. '</div>';
			return;
		}

		$trustbio_now = time();
		echo '<div class="tb-widget-list" id="tb-review-widget-list">';

		foreach ( $trustbio_all as $trustbio_post ) {
			$trustbio_date  = get_post_meta( $trustbio_post->ID, '_trustbio_review_date', true );
			$trustbio_color = 'never'; // CSS modifier class.
			$trustbio_age   = __( 'Never reviewed', 'trustbio' );
			$trustbio_status_label = __( 'Never reviewed', 'trustbio' );

			if ( $trustbio_date ) {
				$trustbio_ts     = strtotime( $trustbio_date );
				$trustbio_months = ( $trustbio_now - $trustbio_ts ) / MONTH_IN_SECONDS;
				$trustbio_days   = (int) ( ( $trustbio_now - $trustbio_ts ) / DAY_IN_SECONDS );

				if ( $trustbio_months > $trustbio_stale * 2 ) {
					$trustbio_color        = 'red';
					$trustbio_status_label = __( 'Very overdue', 'trustbio' );
				} elseif ( $trustbio_months > $trustbio_stale ) {
					$trustbio_color        = 'amber';
					$trustbio_status_label = __( 'Overdue', 'trustbio' );
				} else {
					$trustbio_color        = 'green';
					$trustbio_status_label = __( 'Up to date', 'trustbio' );
				}

				if ( $trustbio_days === 0 ) {
					$trustbio_age = __( 'Today', 'trustbio' );
				} elseif ( $trustbio_days === 1 ) {
					$trustbio_age = __( '1 day ago', 'trustbio' );
				} elseif ( $trustbio_days < 30 ) {
					/* translators: %d: number of days */
					$trustbio_age = sprintf( _n( '%d day ago', '%d days ago', $trustbio_days, 'trustbio' ), $trustbio_days );
				} else {
					$trustbio_age = date_i18n( get_option( 'date_format' ), $trustbio_ts );
				}
			}

			$trustbio_can_edit = current_user_can( 'edit_post', $trustbio_post->ID );

			?>
			<div class="tb-widget-row" id="tb-review-row-<?php echo esc_attr( $trustbio_post->ID ); ?>">

				<span class="tb-widget-dot tb-widget-dot--<?php echo esc_attr( $trustbio_color ); ?>"
					title="<?php echo esc_attr( $trustbio_status_label ); ?>"></span>

				<span class="tb-widget-body">
					<a href="<?php echo esc_url( get_edit_post_link( $trustbio_post->ID ) ); ?>"
					   class="tb-widget-title"><?php echo esc_html( get_the_title( $trustbio_post->ID ) ); ?></a>
					<span class="tb-widget-meta">
						<span class="tb-widget-status tb-widget-status--<?php echo esc_attr( $trustbio_color ); ?>">
							<?php echo esc_html( $trustbio_status_label ); ?>
						</span>
						<span class="tb-widget-sep">·</span>
						<span class="tb-widget-age"><?php echo esc_html( $trustbio_age ); ?></span>
					</span>
				</span>

				<?php if ( $trustbio_can_edit ) : ?>
				<button type="button"
					class="tb-widget-mark"
					data-post-id="<?php echo esc_attr( $trustbio_post->ID ); ?>"
					data-nonce="<?php echo esc_attr( $trustbio_nonce ); ?>"
				><?php esc_html_e( 'Mark reviewed', 'trustbio' ); ?></button>
				<?php endif; ?>

			</div>
			<?php
		}

		echo '</div><!-- .tb-widget-list -->';

		// Legend.
		?>
		<div class="tb-widget-legend">
			<span class="tb-widget-legend__item">
				<span class="tb-widget-dot tb-widget-dot--green"></span>
				<?php esc_html_e( 'Up to date', 'trustbio' ); ?>
			</span>
			<span class="tb-widget-legend__item">
				<span class="tb-widget-dot tb-widget-dot--amber"></span>
				<?php esc_html_e( 'Overdue', 'trustbio' ); ?>
			</span>
			<span class="tb-widget-legend__item">
				<span class="tb-widget-dot tb-widget-dot--red"></span>
				<?php esc_html_e( 'Very overdue', 'trustbio' ); ?>
			</span>
			<span class="tb-widget-legend__item">
				<span class="tb-widget-dot tb-widget-dot--never"></span>
				<?php esc_html_e( 'Never reviewed', 'trustbio' ); ?>
			</span>
		</div>
		<?php
	}

		// =========================================================
	// AJAX — Mark reviewed today
	// =========================================================

	/**
	 * AJAX handler: set review date to today for a post.
	 */
	public function ajax_mark_reviewed_today(): void {
		check_ajax_referer( 'trustbio_mark_reviewed', 'nonce' );

		$trustbio_post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $trustbio_post_id || ! current_user_can( 'edit_post', $trustbio_post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'trustbio' ) ), 403 );
		}

		$trustbio_today = gmdate( 'Y-m-d' );
		update_post_meta( $trustbio_post_id, '_trustbio_review_date', $trustbio_today );

		wp_send_json_success( array(
			'date' => date_i18n( get_option( 'date_format' ), strtotime( $trustbio_today ) ),
		) );
	}

	// =========================================================
	// Queries
	// =========================================================

	/**
	 * Posts that have a review date but are overdue — oldest reviewed first.
	 *
	 * @param string[] $trustbio_types  Enabled post types.
	 * @param int      $trustbio_limit  Max rows.
	 * @return WP_Post[]
	 */
	private function get_overdue_posts( array $trustbio_types, int $trustbio_limit ): array {
		$trustbio_stale_date = gmdate( 'Y-m-d', strtotime( '-' . (int) get_option( 'trustbio_review_stale_months', 12 ) . ' months' ) );

		$trustbio_q = new WP_Query( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'post_type'      => $trustbio_types,
			'post_status'    => 'publish',
			'posts_per_page' => $trustbio_limit,
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_key'       => '_trustbio_review_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_trustbio_review_date',
					'value'   => $trustbio_stale_date,
					'compare' => '<',
					'type'    => 'DATE',
				),
			),
		) );
		return $trustbio_q->posts;
	}

	/**
	 * Published posts that have never had a review date set.
	 *
	 * @param string[] $trustbio_types  Enabled post types.
	 * @param int      $trustbio_limit  Max rows.
	 * @return WP_Post[]
	 */
	private function get_never_reviewed_posts( array $trustbio_types, int $trustbio_limit ): array {
		$trustbio_q = new WP_Query( array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'post_type'      => $trustbio_types,
			'post_status'    => 'publish',
			'posts_per_page' => $trustbio_limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => '_trustbio_review_date',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_trustbio_review_date',
					'value'   => '',
					'compare' => '=',
				),
			),
		) );
		return $trustbio_q->posts;
	}
}
