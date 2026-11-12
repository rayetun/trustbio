<?php
/**
 * Admin view: TrustBio Pro — what the paid add-on adds.
 *
 * This screen is informational and nothing else. It renders no disabled
 * controls, no greyed-out widgets and no "locked" switches, because
 * Guideline 5 of the plugin directory is explicit that a plugin hosted there
 * "may not contain functionality that is restricted or locked, only to be
 * made available by payment or upgrade", and Guideline 9 separately bars
 * implying a user must pay to unlock features that ship in the box. A
 * greyed-out Pro control is the canonical form of both.
 *
 * What IS permitted, and what this page therefore is: a description of a
 * separate add-on, on the plugin's own settings screen, non-blocking and not
 * repeated elsewhere in wp-admin (Guideline 11). Every Pro feature named here
 * lives in TrustBio Pro, a separate plugin that is not hosted on
 * WordPress.org; none of its code ships in this plugin.
 *
 * The page is not registered at all once Pro is active and licensed — see
 * TrustBio_Admin::register_menu().
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-local vars

/*
 * The add-on is sold through Freemius, so the checkout page IS the product
 * page -- it lists the plans, the per-site tiers and the prices. There is no
 * query string on it: no affiliate tag, no campaign parameter, nothing that
 * carries anything about this site to the payment provider. See the
 * "External Services" section of readme.txt, which discloses this link.
 */
$tbu_url = 'https://checkout.freemius.com/plugin/44707/plan/78805/';

/*
 * Feature copy is written from what each module actually does, in the shape
 * "here is where the free plugin stops, here is what the add-on adds". That
 * framing is the honest one and it also keeps the page from reading as a
 * list of things being withheld.
 */
$tbu_groups = array(
	array(
		'title'    => __( 'Verification', 'trustbio' ),
		'icon'     => 'shield-check',
		'features' => array(
			array(
				'name' => __( 'NPI Registry verification', 'trustbio' ),
				'free' => __( 'You can type a verification URL against a credential.', 'trustbio' ),
				'pro'  => __( 'Checks the claim against the CMS National Provider Identifier registry and records what came back.', 'trustbio' ),
			),
			array(
				'name' => __( 'Credential change log', 'trustbio' ),
				'free' => __( 'Anyone with manage_options can edit a credential; no record is kept.', 'trustbio' ),
				'pro'  => __( 'Every create, edit and delete is logged with who did it and when.', 'trustbio' ),
			),
			array(
				'name' => __( 'Credential audit', 'trustbio' ),
				'free' => __( 'The Credentials screen shows the current state.', 'trustbio' ),
				'pro'  => __( 'A dated, exportable record of every credential on the site and its status on the day it was taken.', 'trustbio' ),
			),
			array(
				'name' => __( 'Expiry reminders', 'trustbio' ),
				'free' => __( 'A lapsed credential is hidden on the page and in the JSON-LD.', 'trustbio' ),
				'pro'  => __( 'Email reminders before it lapses, so it is renewed rather than silently dropped.', 'trustbio' ),
			),
		),
	),
	array(
		'title'    => __( 'Editorial workflow', 'trustbio' ),
		'icon'     => 'review',
		'features' => array(
			array(
				'name' => __( 'Reviewer workflow', 'trustbio' ),
				'free' => __( 'You can display "medically reviewed by X on <date>".', 'trustbio' ),
				'pro'  => __( 'Ask a named reviewer to check a post by a date, chase it when it slips, and record the result.', 'trustbio' ),
			),
			array(
				'name' => __( 'Content review scheduler', 'trustbio' ),
				'free' => __( 'The last review date is stored and shown.', 'trustbio' ),
				'pro'  => __( 'Recurring review cycles across the whole library, so nothing quietly goes stale.', 'trustbio' ),
			),
			array(
				'name' => __( 'Front-end profile editing', 'trustbio' ),
				'free' => __( 'Profiles are edited in wp-admin by someone with manage_options.', 'trustbio' ),
				'pro'  => __( 'Authors update their own bio, links and photo from the front end, without an admin account.', 'trustbio' ),
			),
			array(
				'name' => __( 'Author health and leaderboard', 'trustbio' ),
				'free' => __( 'Each author shows a completeness count.', 'trustbio' ),
				'pro'  => __( 'A composite E-E-A-T score per author, ranked, with the gaps listed.', 'trustbio' ),
			),
		),
	),
	array(
		'title'    => __( 'Structured data and publishing', 'trustbio' ),
		'icon'     => 'document',
		'features' => array(
			array(
				'name' => __( 'Deeper medical schema', 'trustbio' ),
				'free' => __( 'Emits an Article with author, reviewer and review date.', 'trustbio' ),
				'pro'  => __( 'Adds Physician, MedicalWebPage and related types for YMYL sites.', 'trustbio' ),
			),
			array(
				'name' => __( 'ORCID publications', 'trustbio' ),
				'free' => __( 'An author\'s ORCID iD is stored and emitted in sameAs.', 'trustbio' ),
				'pro'  => __( 'Pulls their publication list from ORCID and renders it on the profile.', 'trustbio' ),
			),
			array(
				'name' => __( 'Disclosure blocks', 'trustbio' ),
				'free' => __( 'Disclaimers are written by hand into each post.', 'trustbio' ),
				'pro'  => __( 'Reusable funding, conflict-of-interest and medical disclaimer blocks.', 'trustbio' ),
			),
			array(
				'name' => __( 'Team directory', 'trustbio' ),
				'free' => __( 'Authors appear under posts, in boxes and on archives.', 'trustbio' ),
				'pro'  => __( 'A single "our team" page listing everyone, grouped and filterable.', 'trustbio' ),
			),
		),
	),
	array(
		'title'    => __( 'Placement and migration', 'trustbio' ),
		'icon'     => 'boxes',
		'features' => array(
			array(
				'name' => __( 'Sidebar author boxes', 'trustbio' ),
				'free' => __( 'Boxes render with the post content.', 'trustbio' ),
				'pro'  => __( 'A widget and shortcode for sidebars, with their own builder.', 'trustbio' ),
			),
			array(
				'name' => __( 'Elementor integration', 'trustbio' ),
				'free' => __( 'Authors are assigned in the block editor and the classic editor.', 'trustbio' ),
				'pro'  => __( 'A TrustBio section in Elementor\'s Document Settings panel.', 'trustbio' ),
			),
			array(
				'name' => __( 'Author importers', 'trustbio' ),
				'free' => __( 'Authors are added by hand.', 'trustbio' ),
				'pro'  => __( 'One-click import from Co-Authors Plus, PublishPress Authors, Molongui and CSV.', 'trustbio' ),
			),
			array(
				'name' => __( 'Extra layouts', 'trustbio' ),
				'free' => __( 'Eight author box layouts and five review bar layouts.', 'trustbio' ),
				'pro'  => __( 'Further layouts for both, in the same builders.', 'trustbio' ),
			),
		),
	),
);

$tbu_faqs = array(
	array(
		'q' => __( 'Does the free plugin stop working, or expire?', 'trustbio' ),
		'a' => __( 'No. Everything in TrustBio works forever, on any number of sites, with no account and no key. Nothing here is time-limited and nothing is switched off later. The features listed on this page are not in this plugin at all — they are in a separate add-on.', 'trustbio' ),
	),
	array(
		'q' => __( 'Is Pro a replacement for this plugin?', 'trustbio' ),
		'a' => __( 'No, it is an add-on. You keep TrustBio installed and updated from WordPress.org, and Pro installs alongside it. If you ever deactivate Pro, the free plugin and all your data carry on exactly as before.', 'trustbio' ),
	),
	array(
		'q' => __( 'What happens to my data if I upgrade, then cancel?', 'trustbio' ),
		'a' => __( 'Authors, credentials, categories, boxes and review bars all live in the free plugin\'s own tables, so they are unaffected. You lose the Pro screens, not your content.', 'trustbio' ),
	),
	array(
		'q' => __( 'Why is Pro not on WordPress.org?', 'trustbio' ),
		'a' => __( 'The directory hosts free plugins, and its guidelines do not allow a hosted plugin to carry locked or paid-only functionality. Keeping the paid code in a separate add-on is the arrangement the guidelines point to, and it is why this page describes Pro rather than showing you a disabled version of it.', 'trustbio' ),
	),
);
?>
<div class="trustbio-admin-wrap tbu">

	<div class="tb-page-header">
		<div class="tb-page-header__main">
			<div class="tb-page-header__icon"><?php echo trustbio_ap_svg_icon( 'bolt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div>
				<h1 class="tb-page-header__title"><?php esc_html_e( 'TrustBio Pro', 'trustbio' ); ?></h1>
				<p class="tb-page-header__sub"><?php esc_html_e( 'A separate add-on for sites that need verification, review cycles and deeper structured data.', 'trustbio' ); ?></p>
			</div>
		</div>
		<div class="tb-page-header__actions">
			<a href="<?php echo esc_url( $tbu_url ); ?>" target="_blank" rel="noopener" class="tb-btn tb-btn-primary">
				<?php esc_html_e( 'Get TrustBio Pro', 'trustbio' ); ?>
			</a>
		</div>
	</div>

	<div class="tb-content-area">

		<div class="tbu-note">
			<?php echo trustbio_ap_svg_icon( 'shield-check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p>
				<?php esc_html_e( 'Everything TrustBio does is free and stays free. Nothing on this page is disabled inside this plugin waiting for a key — the features below ship in a separate add-on, and this screen is here to describe them so you can decide whether you need it.', 'trustbio' ); ?>
			</p>
		</div>

		<?php foreach ( $tbu_groups as $tbu_group ) : ?>
		<div class="tb-card tbu-group">
			<div class="tb-card-header">
				<span class="tbu-group__icon"><?php echo trustbio_ap_svg_icon( $tbu_group['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<h2 class="tb-card-title"><?php echo esc_html( $tbu_group['title'] ); ?></h2>
			</div>
			<div class="tb-card-body">
				<table class="tbu-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Feature', 'trustbio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'TrustBio (free)', 'trustbio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'TrustBio Pro', 'trustbio' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $tbu_group['features'] as $tbu_f ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $tbu_f['name'] ); ?></th>
							<td><?php echo esc_html( $tbu_f['free'] ); ?></td>
							<td class="tbu-pro-cell"><?php echo esc_html( $tbu_f['pro'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php endforeach; ?>

		<div class="tb-card">
			<div class="tb-card-header">
				<h2 class="tb-card-title"><?php esc_html_e( 'Questions', 'trustbio' ); ?></h2>
			</div>
			<div class="tb-card-body">
				<?php foreach ( $tbu_faqs as $tbu_faq ) : ?>
				<details class="tbu-faq">
					<summary><?php echo esc_html( $tbu_faq['q'] ); ?></summary>
					<p><?php echo esc_html( $tbu_faq['a'] ); ?></p>
				</details>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="tbu-cta">
			<img class="tbu-cta__mark"
				src="<?php echo esc_url( TRUSTBIO_PLUGIN_URL . 'admin/images/trustbio-mark.svg' ); ?>"
				width="52" height="52" alt="" decoding="async">
			<h2><?php esc_html_e( 'TrustBio Pro', 'trustbio' ); ?></h2>
			<p><?php esc_html_e( 'Pro is a separate plugin, sold and licensed through Freemius. Plans, per-site pricing and checkout are on their page.', 'trustbio' ); ?></p>
			<?php /* Its own class, not .tb-btn-primary: that one forces white text with !important, which is invisible on this panel. */ ?>
			<a href="<?php echo esc_url( $tbu_url ); ?>" target="_blank" rel="noopener" class="tb-btn tbu-btn-light">
				<?php esc_html_e( 'Get TrustBio Pro', 'trustbio' ); ?>
			</a>
		</div>

	</div>
</div>
