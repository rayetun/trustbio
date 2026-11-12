<?php
/**
 * Pro add-on bridge — the single contract between free TrustBio and TrustBio Pro.
 *
 * The free plugin ships NO premium code. Every premium feature lives in the
 * separate TrustBio Pro add-on, which is not hosted on WordPress.org. This file
 * documents the extension points that add-on hooks into, and provides the
 * helpers the free plugin uses to decide whether Pro is present.
 *
 * ── FREE FEATURES (implemented in this plugin) ──────────────────────
 * - Author profiles: unlimited registered + guest authors, 30+ social fields,
 *   user-defined custom fields, custom avatar with Gravatar fallback
 * - Author Categories with schema property mapping (author, reviewedBy,
 *   contributor, editor) and contribution verb/noun
 * - Credential badges (15 types + custom) with hasCredential JSON-LD, issuing
 *   body, licence number, verification URL and optional expiry
 * - Author expertise topics with knowsAbout JSON-LD
 * - Author boxes: 4 layouts (Classic Byline, Card Simple, Minimal Inline,
 *   Quote Card) with a live-preview builder and full styling control
 * - Multiple co-authors per post with drag-to-reorder, per-post overrides
 * - Author archive pages with configurable URL base
 * - Person / sameAs / hasCredential / reviewedBy / ProfilePage JSON-LD, with
 *   Yoast SEO and Rank Math duplicate avoidance
 * - Last Review Bars: 2 layouts (Classic, Minimal) with per-post review date,
 *   reviewer and label, plus the review dashboard widget
 * - Gutenberg block and sidebar panel, Classic Editor meta box, Quick Edit,
 *   Bulk Edit, shortcodes, REST API
 * - Full JSON import/export of every data table
 *
 * ── PRO FEATURES (live in the TrustBio Pro add-on) ───────────────────
 * - Sidebar Author Boxes: 5 layouts, widget and [trustbio_sidebar] shortcode
 * - A team directory page listing every author in a grid
 * - 5 premium author box layouts: Magazine Split, Studio Polaroid, Night
 *   Edition, Two Authors Light, Two Authors Dark
 * - 4 premium review bar layouts: Badge Strip, Pill Duo, Authority Card,
 *   Dark Shield
 * - Credential expiry reminders by email
 * - Reviewer workflow: assign a reviewer with a due date, notify them, and let
 *   them mark the post reviewed
 * - Author Health and Author Leaderboard screens
 * - Importers for other author plugins and CSV
 * - Elementor document settings and Elementor widgets
 *
 * ── EXTENSION POINTS ────────────────────────────────────────────────────────
 *
 * Filters:
 *   trustbio_is_pro               ( bool )                      Pro announces itself here.
 *   trustbio_box_layouts          ( array $layouts )            Add box layouts to the builder.
 *   trustbio_box_templates        ( array $slug_to_file )       Map a layout slug to a template file.
 *   trustbio_box_template_dir     ( string $dir, string $slug ) Where a layout's template lives.
 *   trustbio_review_bar_layouts   ( array $layouts )            Add review bar layouts.
 *   trustbio_feature_modules      ( array $features )           Add rows to the Features screen.
 *   trustbio_social_fields        ( array $fields )             Add or remove social fields.
 *   trustbio_schema_data          ( array $data )               Alter schema before output.
 *   trustbio_box_output           ( string $html )              Alter the finished box HTML.
 *   trustbio_review_label         ( string $label )             Alter the default review label.
 *
 * Actions:
 *   trustbio_register_admin_pages ( string $parent_slug )       Add submenu pages.
 *   trustbio_admin_assets         ( string $hook_suffix )       Enqueue assets on a TrustBio screen.
 *   trustbio_settings_panels      ( array $settings )           Render extra settings panels.
 *   trustbio_settings_saved       ( array $posted )             React to a settings save.
 *   trustbio_dashboard_panels     ()                            Render extra Overview panels.
 *   trustbio_before_box           ( object $author )            Before each author box.
 *   trustbio_after_box            ( object $author )            After each author box.
 *
 * Rendering a settings panel:
 *   A panel rendered on trustbio_settings_panels must use this plugin's own
 *   rows, not markup of its own, or it will not look like the screen it is
 *   rendering into and its controls will not be saved. TrustBio_Settings
 *   exposes them:
 *
 *     TrustBio_Settings::render_toggle( $key, $value, $label, $desc )
 *     TrustBio_Settings::render_select( $key, $value, $label, $desc, $options )
 *     TrustBio_Settings::render_number( $key, $value, $label, $desc, $min, $max )
 *     TrustBio_Settings::render_text( $key, $value, $label, $desc, $type, $placeholder )
 *     TrustBio_Settings::render_checkbox_group( $key, $checked, $options, $label, $desc )
 *     TrustBio_Settings::render_note( $text )
 *     TrustBio_Settings::render_link_row( $url, $button, $desc )
 *     TrustBio_Settings::render_save_bar( 'settings' )
 *
 *   $key is the key the value arrives under in the trustbio_settings_saved
 *   payload, so prefix your own. The screen saves over AJAX and collects by
 *   [data-tb-setting]; a control these helpers did not render is not posted
 *   at all, so read the payload the action hands you and never $_POST. Leave
 *   a stored value alone when its key is absent, or an unrelated save will
 *   clear your panel.
 *
 * ── HOW THE PRO ADD-ON INTEGRATES ───────────────────────────────────────────
 *
 * TrustBio Pro is a SEPARATE plugin that:
 * 1. Declares `Requires Plugins: trustbio` in its header.
 * 2. Boots on `plugins_loaded` at priority 20, after this plugin's init.
 * 3. Announces itself with `add_filter( 'trustbio_is_pro', '__return_true' )`
 *    only once its licence check passes.
 * 4. Registers every premium feature through the hooks listed above.
 *
 * @package TrustBio
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Pro_Bridge
 */
class TrustBio_Pro_Bridge {

	/**
	 * Whether the Pro add-on is active and licensed.
	 *
	 * Always false in the free plugin. The Pro add-on returns true by adding
	 * `add_filter( 'trustbio_is_pro', '__return_true' )` after its own licence
	 * check passes.
	 *
	 * @return bool
	 */
	public static function is_pro(): bool {
		return (bool) apply_filters( 'trustbio_is_pro', false );
	}

}

/**
 * Whether the TrustBio Pro add-on is active and licensed.
 *
 * @return bool
 */
function trustbio_is_pro(): bool {
	return TrustBio_Pro_Bridge::is_pro();
}
