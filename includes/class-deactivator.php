<?php
/**
 * Plugin deactivator — runs on plugin deactivation.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Deactivator
 */
class TrustBio_Deactivator {

	/**
	 * Flush rewrite rules on deactivation so our custom
	 * author URL routes are removed cleanly.
	 */
	public static function deactivate(): void {
		// Clear every event this plugin or its add-on may have scheduled, so no
		// cron callback is left pointing at code that is no longer loaded.
		wp_clear_scheduled_hook( 'trustbio_email_digest_run' );
		wp_unschedule_hook( 'trustbio_run_schema_export' );

		/**
		 * Fires while TrustBio is being deactivated.
		 *
		 * The TrustBio Pro add-on clears its own scheduled events here.
		 *
		 * @since 1.0.0
		 */
		do_action( 'trustbio_deactivate' );

		flush_rewrite_rules();
	}
}
