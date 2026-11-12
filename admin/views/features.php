<?php
/**
 * Admin view: Feature Modules management page.
 *
 * Lets the site owner switch off any module they do not need. Every module
 * listed here is fully functional — this screen is a workflow preference, not
 * a paywall. Modules contributed by the TrustBio Pro add-on appear here too,
 * via the trustbio_feature_modules filter.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-local vars

$feature_info = TrustBio_Settings::get_feature_info();
$feat_opt     = get_option( 'trustbio_enabled_features', null );
$all_on       = null === $feat_opt;
$feat_arr     = $all_on ? array_keys( $feature_info ) : (array) $feat_opt;

// Map feature slugs to nav icon names (trustbio_ap_svg_icon keys).
$icon_map = array(
	'review_bars'   => 'review',
	'schema'        => 'shield-check',
	'sidebar_boxes' => 'sidebar',
	'author_health' => 'users',
	'elementor'     => 'boxes',
);

/**
 * Filters the grouping of modules on the Features screen.
 *
 * Each group is an array of 'label', 'desc' and 'slugs'. Any module returned by
 * trustbio_feature_modules that is not claimed by a group is appended to a
 * final "Add-on Modules" group automatically.
 *
 * @since 1.0.0
 * @param array<int,array> $groups Feature groups.
 */
$tbf_groups = (array) apply_filters(
	'trustbio_feature_groups',
	array(
		array(
			'label' => __( 'Core Modules', 'trustbio' ),
			'desc'  => __( 'The foundational features every TrustBio site should have.', 'trustbio' ),
			'slugs' => array( 'schema', 'review_bars' ),
		),
	)
);

// Append any module a group did not claim, so add-on modules always show up.
$tbf_claimed = array();
foreach ( $tbf_groups as $tbf_group ) {
	$tbf_claimed = array_merge( $tbf_claimed, (array) $tbf_group['slugs'] );
}
$tbf_unclaimed = array_diff( array_keys( $feature_info ), $tbf_claimed );
if ( ! empty( $tbf_unclaimed ) ) {
	$tbf_groups[] = array(
		'label' => __( 'Add-on Modules', 'trustbio' ),
		'desc'  => __( 'Modules provided by TrustBio Pro.', 'trustbio' ),
		'slugs' => array_values( $tbf_unclaimed ),
	);
}

/*
 * What the add-on would add, for a site that does not have it.
 *
 * These are NOT modules and they are deliberately not rendered as ones. They
 * carry no toggle, no checkbox and no disabled control of any kind: each card
 * is a link to the "TrustBio Pro" screen, which is itself a description of a
 * separate plugin. Guideline 5 of the plugin directory says a hosted plugin
 * "may not contain functionality that is restricted or locked, only to be
 * made available by payment or upgrade", and a greyed-out switch beside a
 * padlock is the pattern it names: it says the feature is here and you may
 * not use it. A card that says the feature is in another plugin, and links to
 * the page describing that plugin, says something different, and true.
 *
 * The copy is short on purpose. The full free-versus-Pro comparison lives on
 * the upgrade screen, and repeating it here would turn a settings page into a
 * second sales page.
 *
 * None of this renders once Pro is licensed: its real modules arrive through
 * trustbio_feature_modules and appear above in Add-on Modules with working
 * switches, so there is nothing left to describe.
 */
$tbf_pro_cards = array();
if ( ! trustbio_is_pro() ) {
	$tbf_pro_cards = array(
		array(
			'icon' => 'sidebar',
			'name' => __( 'Sidebar Author Boxes', 'trustbio' ),
			'desc' => __( 'Five sidebar layouts, a widget and the [trustbio_sidebar] shortcode.', 'trustbio' ),
		),
		array(
			'icon' => 'shield-check',
			'name' => __( 'NPI Registry Verification', 'trustbio' ),
			'desc' => __( 'Checks a credential against the US provider registry instead of taking a typed URL on trust.', 'trustbio' ),
		),
		array(
			'icon' => 'document',
			'name' => __( 'Credential Audit', 'trustbio' ),
			'desc' => __( 'A dated, exportable record of every credential on the site and its status that day.', 'trustbio' ),
		),
		array(
			'icon' => 'shield-check',
			'name' => __( 'Credential Change Log', 'trustbio' ),
			'desc' => __( 'Every change recorded with the old value, the new value and who made it.', 'trustbio' ),
		),
		array(
			'icon' => 'email',
			'name' => __( 'Credential Expiry Reminders', 'trustbio' ),
			'desc' => __( 'Email before a credential lapses, so it is renewed rather than quietly hidden.', 'trustbio' ),
		),
		array(
			'icon' => 'review',
			'name' => __( 'Reviewer Workflow', 'trustbio' ),
			'desc' => __( 'Ask a named reviewer to check a post by a date, chase it, and record the result.', 'trustbio' ),
		),
		array(
			'icon' => 'review',
			'name' => __( 'Content Review Scheduler', 'trustbio' ),
			'desc' => __( 'Every published post ranked by how overdue its expert review is.', 'trustbio' ),
		),
		array(
			'icon' => 'users',
			'name' => __( 'Front-end Profile Editing', 'trustbio' ),
			'desc' => __( 'Authors update their own profile from the front end, into a review queue.', 'trustbio' ),
		),
		array(
			'icon' => 'shield-check',
			'name' => __( 'Medical Schema', 'trustbio' ),
			'desc' => __( 'Adds MedicalWebPage, Physician and related types for YMYL pages.', 'trustbio' ),
		),
		array(
			'icon' => 'fields',
			'name' => __( 'ORCID Publications', 'trustbio' ),
			'desc' => __( 'Pulls an author&#8217;s publication list from the ORCID iD already on their profile.', 'trustbio' ),
		),
		array(
			'icon' => 'document',
			'name' => __( 'Disclosure Blocks', 'trustbio' ),
			'desc' => __( 'Reusable funding, conflict-of-interest and medical disclaimer statements.', 'trustbio' ),
		),
		array(
			'icon' => 'users',
			'name' => __( 'Team Directory', 'trustbio' ),
			'desc' => __( 'An &#8220;our team&#8221; page built from the profiles you already keep.', 'trustbio' ),
		),
		array(
			'icon' => 'fields',
			'name' => __( 'Author Importers', 'trustbio' ),
			'desc' => __( 'Import from Co-Authors Plus, PublishPress Authors, Molongui or a CSV.', 'trustbio' ),
		),
	);
}
$tbf_pro_url = admin_url( 'admin.php?page=trustbio-upgrade' );
?>
<div class="trustbio-admin-wrap tbf-wrap">

	<!-- Page header -->
	<div class="tb-page-header">
		<div class="tb-page-header__left">
			<div class="tb-page-header__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"/></svg>
			</div>
			<div class="tb-page-header__text">
				<h1 class="tb-page-title"><?php esc_html_e( 'Feature Modules', 'trustbio' ); ?></h1>
				<p class="tb-page-subtitle"><?php esc_html_e( 'Enable only what you need — disabled modules remove their nav items and stop processing.', 'trustbio' ); ?></p>
			</div>
		</div>
		<div class="tb-page-header__actions">
			<span class="tbf-save-status"></span>
			<button type="button" class="tbf-save-btn tb-btn tb-btn-primary"><?php esc_html_e( 'Save Changes', 'trustbio' ); ?></button>
		</div>
	</div>

	<div class="tb-content-area">
		<?php foreach ( $tbf_groups as $tbf_group ) : ?>
		<div class="tbf-group">
			<div class="tbf-group-header">
				<h2 class="tbf-group-title"><?php echo esc_html( $tbf_group['label'] ); ?></h2>
				<p class="tbf-group-desc"><?php echo esc_html( $tbf_group['desc'] ); ?></p>
			</div>
			<div class="tbf-card-grid">
				<?php
				foreach ( $tbf_group['slugs'] as $tbf_slug ) :
					if ( ! isset( $feature_info[ $tbf_slug ] ) ) {
						continue;
					}
					$tbf_fi   = $feature_info[ $tbf_slug ];
					$tbf_on   = $all_on || in_array( $tbf_slug, $feat_arr, true );
					$tbf_icon = $icon_map[ $tbf_slug ] ?? 'settings';
					?>
				<div class="tbf-card <?php echo $tbf_on ? 'is-enabled' : ''; ?>">
					<div class="tbf-card__icon <?php echo $tbf_on ? 'tbf-card__icon--on' : ''; ?>">
						<?php echo trustbio_ap_svg_icon( $tbf_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="tbf-card__body">
						<div class="tbf-card__name"><?php echo esc_html( $tbf_fi['name'] ); ?></div>
						<div class="tbf-card__desc"><?php echo esc_html( $tbf_fi['desc'] ); ?></div>
					</div>
					<div class="tbf-card__footer">
						<span class="tbf-card__badge <?php echo $tbf_on ? 'tbf-card__badge--on' : 'tbf-card__badge--off'; ?>">
							<?php echo $tbf_on ? esc_html__( 'Active', 'trustbio' ) : esc_html__( 'Inactive', 'trustbio' ); ?>
						</span>
						<label class="tbf-toggle" title="<?php echo esc_attr( $tbf_fi['name'] ); ?>">
							<input type="checkbox"
								class="tbf-toggle__input"
								data-feature="<?php echo esc_attr( $tbf_slug ); ?>"
								value="1"
								<?php checked( $tbf_on ); ?>>
							<span class="tbf-toggle__track"><span class="tbf-toggle__thumb"></span></span>
						</label>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>

		<?php if ( ! empty( $tbf_pro_cards ) ) : ?>
		<div class="tbf-group tbf-group--pro">
			<div class="tbf-group-header">
				<h2 class="tbf-group-title"><?php esc_html_e( 'Available in TrustBio Pro', 'trustbio' ); ?></h2>
				<p class="tbf-group-desc">
					<?php esc_html_e( 'A separate add-on, not part of this plugin. These are not switched off here — they are not installed.', 'trustbio' ); ?>
					<a href="<?php echo esc_url( $tbf_pro_url ); ?>"><?php esc_html_e( 'See what it adds', 'trustbio' ); ?></a>
				</p>
			</div>
			<div class="tbf-card-grid">
				<?php foreach ( $tbf_pro_cards as $tbf_pc ) : ?>
				<a class="tbf-card tbf-card--pro" href="<?php echo esc_url( $tbf_pro_url ); ?>">
					<div class="tbf-card__icon">
						<?php echo trustbio_ap_svg_icon( $tbf_pc['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="tbf-card__body">
						<div class="tbf-card__name"><?php echo esc_html( $tbf_pc['name'] ); ?></div>
						<div class="tbf-card__desc"><?php echo esc_html( $tbf_pc['desc'] ); ?></div>
					</div>
					<div class="tbf-card__footer">
						<span class="tbf-card__badge tbf-card__badge--pro"><?php esc_html_e( 'Pro', 'trustbio' ); ?></span>
						<span class="tbf-card__more" aria-hidden="true"><?php esc_html_e( 'Learn more', 'trustbio' ); ?></span>
					</div>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>

</div><!-- /.tbf-wrap -->
