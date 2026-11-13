<?php
/**
 * Plugin Name:       TrustBio — Author Credentials, Medical Reviewer Bylines & E-E-A-T Author Box
 * Plugin URI:        https://wordpress.org/plugins/trustbio/
 * Description:       Show who wrote and who reviewed every post. Verified author credentials with expiry dates, reviewer bylines, last-review bars, co-authors, guest authors and automatic hasCredential / reviewedBy schema.
 * Version:           1.0.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Md Rayhan Uddin
 * Author URI:        https://rayetun.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       trustbio
 * Domain Path:       /languages
 *
 * @package TrustBio
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version.
define( 'TRUSTBIO_VERSION', '1.0.1' );

// Plugin root file path.
define( 'TRUSTBIO_PLUGIN_FILE', __FILE__ );

// Plugin directory path.
define( 'TRUSTBIO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Plugin directory URL.
define( 'TRUSTBIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Plugin basename.
define( 'TRUSTBIO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum WordPress version required.
define( 'TRUSTBIO_MIN_WP', '6.2' );

// Minimum PHP version required.
define( 'TRUSTBIO_MIN_PHP', '7.4' );

/**
 * Check PHP and WordPress version compatibility before loading.
 */
function trustbio_check_compatibility() {
	$compatible = true;

	if ( version_compare( PHP_VERSION, TRUSTBIO_MIN_PHP, '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: 1: Required PHP version, 2: Current PHP version */
					esc_html__( 'TrustBio requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade PHP to activate TrustBio.', 'trustbio' ),
					esc_html( TRUSTBIO_MIN_PHP ),
					esc_html( PHP_VERSION )
				);
				echo '</p></div>';
			}
		);
		$compatible = false;
	}

	if ( version_compare( get_bloginfo( 'version' ), TRUSTBIO_MIN_WP, '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				printf(
					/* translators: 1: Required WP version, 2: Current WP version */
					esc_html__( 'TrustBio requires WordPress %1$s or higher. You are running WordPress %2$s. Please upgrade WordPress to activate TrustBio.', 'trustbio' ),
					esc_html( TRUSTBIO_MIN_WP ),
					esc_html( get_bloginfo( 'version' ) )
				);
				echo '</p></div>';
			}
		);
		$compatible = false;
	}

	return $compatible;
}

/**
 * Load the plugin after compatibility check passes.
 */
function trustbio_init() {
	if ( ! trustbio_check_compatibility() ) {
		return;
	}

	// Load core files.
	require_once TRUSTBIO_PLUGIN_DIR . 'includes/class-trustbio.php';
	require_once TRUSTBIO_PLUGIN_DIR . 'includes/helpers.php';

	// Boot the plugin.
	TrustBio::get_instance();
}
add_action( 'plugins_loaded', 'trustbio_init' );

/**
 * Activation hook — runs when admin clicks "Activate".
 */
function trustbio_activate() {
	require_once TRUSTBIO_PLUGIN_DIR . 'includes/class-activator.php';
	TrustBio_Activator::activate();
}
register_activation_hook( __FILE__, 'trustbio_activate' );

/**
 * Deactivation hook — runs when admin clicks "Deactivate".
 */
function trustbio_deactivate() {
	require_once TRUSTBIO_PLUGIN_DIR . 'includes/class-deactivator.php';
	TrustBio_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'trustbio_deactivate' );
