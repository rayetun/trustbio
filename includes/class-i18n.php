<?php
/**
 * Internationalization class.
 *
 * Since WordPress 4.6, plugins hosted on WordPress.org no longer need to call
 * load_plugin_textdomain() — WordPress loads translations automatically from
 * the plugin's declared text domain and the /languages directory.
 *
 * This class is kept as a placeholder so the loader reference in class-trustbio.php
 * remains intact. No action is registered.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_i18n
 */
class TrustBio_i18n {
	// WordPress 4.6+ automatically loads translations for plugins.
	// No manual load_plugin_textdomain() needed when hosted on WordPress.org.
	// Translation files go in /languages/ and follow the {textdomain}-{locale}.mo naming.
}
