<?php
/**
 * Contextual help tabs for every TrustBio admin screen.
 *
 * Registered via load-{hook} so tabs only appear on our own pages.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TrustBio_Help_Tabs
 */
class TrustBio_Help_Tabs {

	/**
	 * Register load-{hook} actions for every TrustBio page.
	 *
	 * @param array<string,string> $page_hooks Map of page key → WP hook suffix.
	 */
	public function register( array $page_hooks ): void {
		$map = array(
			'dashboard'   => array( $this, 'add_dashboard_tabs' ),
			'authors'     => array( $this, 'add_authors_tabs' ),
			'settings'    => array( $this, 'add_settings_tabs' ),
			'credentials' => array( $this, 'add_credentials_tabs' ),
		);

		/**
		 * Filters the page key to help-tab callback map.
		 *
		 * The TrustBio Pro add-on registers help tabs for its own screens here.
		 *
		 * @since 1.0.0
		 * @param array<string,callable> $map        Page key => callback.
		 * @param array<string,string>   $page_hooks Page key => WP hook suffix.
		 */
		$map = (array) apply_filters( 'trustbio_help_tabs', $map, $page_hooks );

		foreach ( $map as $key => $callback ) {
			if ( isset( $page_hooks[ $key ] ) && is_callable( $callback ) ) {
				add_action( 'load-' . $page_hooks[ $key ], $callback );
			}
		}
	}

	// ── Shared sidebar ────────────────────────────────────────────

	/**
	 * Add the standard help sidebar with quick links.
	 */
	private function add_sidebar(): void {
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . esc_html__( 'TrustBio Quick Links', 'trustbio' ) . '</strong></p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=trustbio' ) ) . '">' . esc_html__( 'Dashboard', 'trustbio' ) . '</a></p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=trustbio-authors' ) ) . '">' . esc_html__( 'Authors', 'trustbio' ) . '</a></p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=trustbio-boxes' ) ) . '">' . esc_html__( 'Author Boxes', 'trustbio' ) . '</a></p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=trustbio-settings' ) ) . '">' . esc_html__( 'Settings', 'trustbio' ) . '</a></p>'
		);
	}

	// ── Dashboard ─────────────────────────────────────────────────

	/**
	 * Help tabs for the main Dashboard / Overview screen.
	 */
	public function add_dashboard_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab( array(
			'id'      => 'trustbio-dash-overview',
			'title'   => __( 'Overview', 'trustbio' ),
			'content' =>
				'<h2>' . esc_html__( 'TrustBio Overview', 'trustbio' ) . '</h2>' .
				'<p>' . esc_html__( 'This dashboard shows your site\'s E-E-A-T health at a glance. The Setup Checklist on the right guides you through initial configuration. All stat cards update in real time from your live data.', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'Green numbers mean you\'re doing well. Red numbers flag issues that need attention. Click any "View all →" link to go to the full report for that metric.', 'trustbio' ) . '</p>',
		) );

		$screen->add_help_tab( array(
			'id'      => 'trustbio-dash-eeat',
			'title'   => __( 'E-E-A-T Signals', 'trustbio' ),
			'content' =>
				'<h2>' . esc_html__( 'What Are E-E-A-T Signals?', 'trustbio' ) . '</h2>' .
				'<p>' . esc_html__( 'E-E-A-T stands for Experience, Expertise, Authoritativeness and Trustworthiness — the framework Google uses to evaluate content quality. TrustBio puts four of those signals on every post:', 'trustbio' ) . '</p>' .
				'<ul>' .
				'<li><strong>' . esc_html__( 'Author assigned', 'trustbio' ) . '</strong> — ' . esc_html__( 'A TrustBio author profile is linked to the post and output as Person schema.', 'trustbio' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Credentials', 'trustbio' ) . '</strong> — ' . esc_html__( 'The author has at least one active, non-expired credential, output as hasCredential.', 'trustbio' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Review date', 'trustbio' ) . '</strong> — ' . esc_html__( 'A last-reviewed date set in the editor, shown on the review bar.', 'trustbio' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Reviewer', 'trustbio' ) . '</strong> — ' . esc_html__( 'A separate reviewer, distinct from the author, output as reviewedBy.', 'trustbio' ) . '</li>' .
				'</ul>' .
				'<p>' . esc_html__( 'Aim for all four on anything in a Your Money or Your Life topic — health, legal, or financial content.', 'trustbio' ) . '</p>',
		) );

		$this->add_sidebar();
	}

	// ── Authors ───────────────────────────────────────────────────

	/**
	 * Help tabs for the Authors list screen.
	 */
	public function add_authors_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab( array(
			'id'      => 'trustbio-authors-overview',
			'title'   => __( 'Overview', 'trustbio' ),
			'content' =>
				'<h2>' . esc_html__( 'Authors', 'trustbio' ) . '</h2>' .
				'<p>' . esc_html__( 'Authors are the people credited on your posts. TrustBio creates a dedicated profile for each author that powers the author box, structured data schema, and E-E-A-T trust signals.', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'Click "Add Author" to create a new profile. Click any author name to edit their details. The Completeness score shows how much of their profile is filled in.', 'trustbio' ) . '</p>',
		) );

		$screen->add_help_tab( array(
			'id'      => 'trustbio-authors-types',
			'title'   => __( 'Author Types', 'trustbio' ),
			'content' =>
				'<h2>' . esc_html__( 'Registered vs Guest Authors', 'trustbio' ) . '</h2>' .
				'<p><strong>' . esc_html__( 'Registered authors', 'trustbio' ) . '</strong> ' . esc_html__( 'are linked to a WordPress user account by email. Their profile is tied to a real login — great for your own editorial team.', 'trustbio' ) . '</p>' .
				'<p><strong>' . esc_html__( 'Guest authors', 'trustbio' ) . '</strong> ' . esc_html__( 'are standalone profiles with no WordPress login required — ideal for external contributors, quoted medical experts, or reviewers who don\'t have site access.', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'Both types generate identical schema markup and contribute equally to E-E-A-T signals.', 'trustbio' ) . '</p>',
		) );

		$screen->add_help_tab( array(
			'id'      => 'trustbio-authors-completeness',
			'title'   => __( 'Completeness Score', 'trustbio' ),
			'content' =>
				'<h2>' . esc_html__( 'Profile Completeness', 'trustbio' ) . '</h2>' .
				'<p>' . esc_html__( 'The Completeness score measures how many profile fields are filled in: photo, bio, job title, credentials, social links, and expertise categories.', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'A higher score means richer schema output and a more credible author box. Aim for 80%+ on all authors who write YMYL content (health, finance, legal, safety).', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'The Status column shows whether an author is Active (appears on posts) or Inactive (hidden from all public views).', 'trustbio' ) . '</p>',
		) );

		$this->add_sidebar();
	}

	// ── Settings ──────────────────────────────────────────────────

	/**
	 * Help tabs for the Settings screen.
	 */
	public function add_settings_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab( array(
			'id'      => 'trustbio-settings-overview',
			'title'   => __( 'Overview', 'trustbio' ),
			'content' =>
				'<h2>' . esc_html__( 'TrustBio Settings', 'trustbio' ) . '</h2>' .
				'<p>' . esc_html__( 'Settings are organised into tabs on the left. Changes are saved with the "Save Settings" button that appears at the bottom of the page when you make a change.', 'trustbio' ) . '</p>' .
				'<ul>' .
				'<li><strong>' . esc_html__( 'Overview', 'trustbio' ) . '</strong> — ' . esc_html__( 'Live counts and the status of every feature module.', 'trustbio' ) . '</li>' .
				'<li><strong>' . esc_html__( 'General', 'trustbio' ) . '</strong> — ' . esc_html__( 'Post types, roles, archive pages, contribution count.', 'trustbio' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Author Boxes', 'trustbio' ) . '</strong> — ' . esc_html__( 'Default layout, position, auto-insert behaviour.', 'trustbio' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Review Bars', 'trustbio' ) . '</strong> — ' . esc_html__( 'Review bar display mode, position, stale threshold.', 'trustbio' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Advanced', 'trustbio' ) . '</strong> — ' . esc_html__( 'Schema output, REST API, debug mode.', 'trustbio' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Shortcodes', 'trustbio' ) . '</strong> — ' . esc_html__( 'Every TrustBio shortcode with its attributes.', 'trustbio' ) . '</li>' .
				'<li><strong>' . esc_html__( 'Maintenance', 'trustbio' ) . '</strong> — ' . esc_html__( 'Cache, export/import, uninstall options.', 'trustbio' ) . '</li>' .
				'</ul>',
		) );

		$screen->add_help_tab( array(
			'id'      => 'trustbio-settings-schema',
			'title'   => __( 'Schema & JSON-LD', 'trustbio' ),
			'content' =>
				'<h2>' . esc_html__( 'Schema & JSON-LD', 'trustbio' ) . '</h2>' .
				'<p>' . esc_html__( 'TrustBio outputs JSON-LD structured data in your page &lt;head&gt; to tell Google about your authors, their credentials, and the review status of each post.', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'If you use Yoast SEO or Rank Math, enable Schema Compatibility Mode (Advanced tab) to avoid duplicate author nodes. TrustBio will merge its data into the existing schema graph rather than outputting a separate block.', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'Test your schema output using Google\'s Rich Results Test at search.google.com/test/rich-results.', 'trustbio' ) . '</p>',
		) );

		$this->add_sidebar();
	}

	// ── Credentials ───────────────────────────────────────────────

	/**
	 * Help tabs for the Credentials screen.
	 */
	public function add_credentials_tabs(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab( array(
			'id'      => 'trustbio-cred-overview',
			'title'   => __( 'Overview', 'trustbio' ),
			'content' =>
				'<h2>' . esc_html__( 'Author Credentials', 'trustbio' ) . '</h2>' .
				'<p>' . esc_html__( 'Credentials are verified qualifications linked to an author — degrees, board certifications, professional licenses, and memberships. Each credential can have an expiry date.', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'Only active (non-expired) credentials count toward an author\'s E-E-A-T trust score. Expired credentials are automatically excluded.', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'Select an author from the dropdown on the left to view or add their credentials.', 'trustbio' ) . '</p>',
		) );

		$screen->add_help_tab( array(
			'id'      => 'trustbio-cred-schema',
			'title'   => __( 'Schema Impact', 'trustbio' ),
			'content' =>
				'<h2>' . esc_html__( 'How Credentials Affect Schema', 'trustbio' ) . '</h2>' .
				'<p>' . esc_html__( 'Credentials marked "Show in Schema" are included in the author\'s JSON-LD output as hasCredential properties. Google can use these to assess author expertise for YMYL content.', 'trustbio' ) . '</p>' .
				'<p>' . esc_html__( 'Best practices:', 'trustbio' ) . '</p>' .
				'<ul>' .
				'<li>' . esc_html__( 'Use the official credential abbreviation (e.g. "MD", "RD", "CFP") not the full spelled-out name.', 'trustbio' ) . '</li>' .
				'<li>' . esc_html__( 'Include the issuing organisation — this adds credibility to the schema data.', 'trustbio' ) . '</li>' .
				'<li>' . esc_html__( 'Add a verification URL if the credential is publicly verifiable online.', 'trustbio' ) . '</li>' .
				'<li>' . esc_html__( 'Only use "Show in Schema" for formal professional qualifications, not online course certificates.', 'trustbio' ) . '</li>' .
				'</ul>',
		) );

		$this->add_sidebar();
	}

	// ── Inline tooltips ───────────────────────────────────────────

	/**
	 * Print an inline help tooltip button next to a field label.
	 *
	 * Markup matches the .tb-tip component in admin/css/trustbio-admin.css.
	 * The button carries aria-label so the text is available to screen readers,
	 * and the popover itself is aria-hidden because it duplicates that label.
	 *
	 * @param string $trustbio_text Tooltip body text, already translated.
	 * @return void
	 */
	public static function tip( string $trustbio_text ): void {
		if ( '' === trim( $trustbio_text ) ) {
			return;
		}
		?>
		<button type="button" class="tb-tip" aria-label="<?php echo esc_attr( $trustbio_text ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
			<span class="tb-tip__pop" aria-hidden="true"><?php echo esc_html( $trustbio_text ); ?></span>
		</button>
		<?php
	}
}
