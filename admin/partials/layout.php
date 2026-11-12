<?php
/**
 * Shared admin layout — dark topbar + page-nav sidebar.
 * Call trustbio_layout_start($page_slug) before page content and trustbio_layout_end() after.
 *
 * @package TrustBio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Open the shared TrustBio admin shell: topbar + sidebar + content area.
 *
 * @param string $page_slug Active page slug (e.g. 'trustbio-authors').
 */
function trustbio_layout_start( string $page_slug = '' ): void {
	/*
	 * This used to call remove_all_actions() on admin_notices,
	 * all_admin_notices and network_admin_notices.
	 *
	 * It was removed for two reasons. It never worked: wp-admin/admin.php
	 * loads admin-header.php, which fires those hooks, before it calls a
	 * page's own callback, so by the time this function runs the notices have
	 * already been printed. And had it worked it would have been wrong —
	 * throwing away every other plugin's messages, and WordPress's own update
	 * and security notices, on screens the user may well be reading them on.
	 */

	if ( '' === $page_slug ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_slug = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
	}

	$nav_items = trustbio_ap_nav_items();
	?>
	<div class="trustbio-ap-page">

	<header class="trustbio-ap-topbar">
		<div class="trustbio-ap-topbar-brand">
			<span class="trustbio-ap-topbar-logo">
				<?php
				/*
				 * The plugin's own mark, the same one the directory listing
				 * uses. Decorative: the plugin name is the next element, so a
				 * screen reader would otherwise hear "TrustBio" twice.
				 */
				?>
				<img src="<?php echo esc_url( TRUSTBIO_PLUGIN_URL . 'admin/images/trustbio-mark.svg' ); ?>"
					width="24" height="24" alt="" decoding="async">
			</span>
			<span class="trustbio-ap-topbar-name"><?php esc_html_e( 'TrustBio', 'trustbio' ); ?></span>
			<span class="trustbio-ap-topbar-version">v<?php echo esc_html( TRUSTBIO_VERSION ); ?></span>
		</div>
		<div class="trustbio-ap-topbar-right">
			<a href="https://wordpress.org/support/plugin/trustbio/" target="_blank" rel="noopener" class="trustbio-ap-topbar-btn"><?php esc_html_e( 'Support', 'trustbio' ); ?></a>
		</div>
	</header>

	<div class="trustbio-ap-layout">

	<nav class="trustbio-ap-sidebar">
		<?php foreach ( $nav_items as $item ) :
			if ( ! empty( $item['section'] ) ) : ?>
			<div class="trustbio-ap-nav-section"><?php echo esc_html( $item['label'] ); ?></div>
			<?php continue; endif;
			if ( ! empty( $item['feature'] ) && ! trustbio_feature_enabled( $item['feature'] ) ) { continue; }
			$trustbio_is_active = ( $page_slug === $item['slug'] ) ? ' is-active' : '';
		?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item['slug'] ) ); ?>" class="trustbio-ap-nav-item<?php echo esc_attr( $trustbio_is_active ); ?><?php echo empty( $item['class'] ) ? '' : ' ' . esc_attr( $item['class'] ); ?>">
			<span class="trustbio-ap-nav-icon"><?php echo trustbio_ap_svg_icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span><?php echo esc_html( $item['label'] ); ?></span>
		</a>
		<?php endforeach; ?>
	</nav>

	<main class="trustbio-ap-content">
	<?php
}

/**
 * Close the shared TrustBio admin shell.
 */
function trustbio_layout_end(): void {
	?>
	</main><!-- /.trustbio-ap-content -->
	</div><!-- /.trustbio-ap-layout -->
	</div><!-- /.trustbio-ap-page -->
	<?php
}

/**
 * Return the ordered list of sidebar nav items.
 *
 * @return array<int, array<string, string>>
 */
function trustbio_ap_nav_items(): array {
	$trustbio_items = array(
		array( 'slug' => 'trustbio',               'label' => __( 'Overview',      'trustbio' ), 'icon' => 'home' ),
		array( 'slug' => 'trustbio-features',      'label' => __( 'Features',      'trustbio' ), 'icon' => 'features' ),
		array( 'slug' => 'trustbio-categories',    'label' => __( 'Categories',    'trustbio' ), 'icon' => 'tag' ),
		array( 'slug' => 'trustbio-authors',       'label' => __( 'Authors',       'trustbio' ), 'icon' => 'users' ),
		array( 'slug' => 'trustbio-credentials',   'label' => __( 'Credentials',   'trustbio' ), 'icon' => 'shield-check' ),
		array( 'slug' => 'trustbio-fields',        'label' => __( 'Author Fields', 'trustbio' ), 'icon' => 'fields' ),
		array( 'slug' => 'trustbio-boxes',         'label' => __( 'Author Boxes',  'trustbio' ), 'icon' => 'boxes' ),
		array( 'slug' => 'trustbio-review-bars',   'label' => __( 'Review Bars',   'trustbio' ), 'icon' => 'review', 'feature' => 'review_bars' ),
	);

	/**
	 * Filters the TrustBio admin sidebar nav items.
	 *
	 * The TrustBio Pro add-on appends its own screens here. Every item must
	 * point at a page that is actually registered, so the nav never shows a
	 * link that leads nowhere.
	 *
	 * Each item is array( 'slug', 'label', 'icon' ) plus an optional 'feature'
	 * slug, which hides the item when that module is switched off. An item of
	 * array( 'section' => true, 'label' => '' ) renders a divider.
	 *
	 * @since 1.0.0
	 * @param array<int,array<string,mixed>> $items Nav items, in display order.
	 */
	$trustbio_items = (array) apply_filters( 'trustbio_admin_nav_items', $trustbio_items );

	// Settings always sits last, below a divider.
	$trustbio_items[] = array( 'section' => true, 'label' => '' );
	$trustbio_items[] = array( 'slug' => 'trustbio-settings', 'label' => __( 'Settings', 'trustbio' ), 'icon' => 'settings' );

	// One nav entry for the add-on, and only while it is not installed. It
	// points at a page describing Pro, not at a locked feature.
	if ( ! trustbio_is_pro() ) {
		$trustbio_items[] = array(
			'slug'  => 'trustbio-upgrade',
			'label' => __( 'TrustBio Pro', 'trustbio' ),
			'icon'  => 'bolt',
			'class' => 'is-upgrade',
		);
	}

	return $trustbio_items;
}

/**
 * Return an inline SVG for the given icon name.
 *
 * @param string $name Icon identifier.
 * @return string Safe SVG markup.
 */
function trustbio_ap_svg_icon( string $name ): string {
	static $icons = null;
	if ( null === $icons ) {
		$icons = array(
			'logo'         => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
			'home'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>',
			'tag'          => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
			'users'        => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>',
			'fields'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>',
			'boxes'        => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
			'sidebar'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm2 1v8h3V6H4zm5 0v8h7V6H9z" clip-rule="evenodd"/></svg>',
			'review'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
			'bolt'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>',
			'gap'          => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
			'ai'           => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/></svg>',
			'document'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>',
			'shield-check' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
			'features'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clip-rule="evenodd"/></svg>',
			'email'        => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>',
			'settings'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>',
		);
	}
	return $icons[ $name ] ?? $icons['settings'];
}
