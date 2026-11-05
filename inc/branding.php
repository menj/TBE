<?php
/**
 * Brand assets: favicon, touch icon, web app manifest, social preview and theme colour.
 * The icons are used only when the site owner has not set a Site Icon under Appearance → Customize.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * The address of a brand file, versioned by a hash of the file (tbe_asset_version()), so it changes only once the
 * new file is up. '' while the file is missing (an upload still running): the tag is then left out.
 */
function tbe_brand_url( string $file ): string {
	$ver = tbe_asset_version( 'assets/img/brand/' . $file );
	return null === $ver ? '' : add_query_arg( 'ver', $ver, TBE_URI . '/assets/img/brand/' . $file );
}

/**
 * Print one brand tag, or nothing while its file is missing.
 *
 * @param string $format printf format with one %s for the address.
 * @param string $file   File in assets/img/brand/.
 */
function tbe_brand_tag( string $format, string $file ): void {
	$url = tbe_brand_url( $file );
	if ( '' !== $url ) {
		printf( $format . "\n", esc_url( $url ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- the format is the theme's own; the address is escaped.
	}
}

/**
 * The icon tags while no Site Icon is set (WordPress prints its own once one is), and the theme colour.
 * On the site, the login screen and the admin.
 */
function tbe_brand_icon_tags(): void {
	if ( ! has_site_icon() ) {
		tbe_brand_tag( '<link rel="icon" href="%s" type="image/svg+xml">', 'favicon.svg' );
		tbe_brand_tag( '<link rel="icon" href="%s" sizes="32x32" type="image/png">', 'favicon-32.png' );
		tbe_brand_tag( '<link rel="apple-touch-icon" href="%s">', 'apple-touch-icon.png' );
		tbe_brand_tag( '<link rel="manifest" href="%s">', 'site.webmanifest' );
	}
	$colors = tbe_active_colors();
	printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( (string) ( $colors['accent'] ?? '#6e1f2e' ) ) );
}
add_action( 'login_head', 'tbe_brand_icon_tags' );
add_action( 'admin_head', 'tbe_brand_icon_tags' );

add_action(
	'wp_head',
	static function () {
		// The site name is above the fold on every page; fetch its font early so the title does not jump when it loads.
		echo tbe_font_preload( 'assets/fonts/Angel.woff2' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in tbe_font_preload().
		tbe_brand_icon_tags();
		// An SEO plugin that writes its own social tags can switch these off: add_filter( 'tbe_social_meta', '__return_false' ).
		if ( ! apply_filters( 'tbe_social_meta', ! defined( 'WPSEO_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) ) ) {
			return;
		}
		if ( '' === tbe_brand_url( 'social-preview.png' ) ) {
			return;
		}
		tbe_brand_tag( '<meta property="og:image" content="%s">', 'social-preview.png' );
		echo '<meta property="og:image:width" content="1200">' . "\n" . '<meta property="og:image:height" content="630">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		tbe_brand_tag( '<meta name="twitter:image" content="%s">', 'social-preview.png' );
	},
	2
);

/** Browsers ask for /favicon.ico on their own; answer with the brand icon unless a Site Icon is set. */
function tbe_serve_favicon(): void {
	$file = TBE_DIR . '/assets/img/brand/favicon.ico';
	$etag = tbe_asset_version( 'assets/img/brand/favicon.ico' );
	// While the file is missing (an upload still running), WordPress answers as it would without the theme.
	if ( has_site_icon() || null === $etag ) {
		return;
	}
	// Browsers ask for /favicon.ico at a fixed address, so it cannot carry a version: a day's cache, then a
	// check against the file's hash, which answers 304 while it is unchanged and sends a changed icon at once.
	$etag = '"' . $etag . '"';
	header( 'Content-Type: image/x-icon' );
	header( 'Cache-Control: public, max-age=86400' );
	header( 'ETag: ' . $etag );
	// If-None-Match may list several validators, weak ones (W/"...") or *.
	$sent = array_map(
		static fn( string $v ): string => (string) preg_replace( '#^W/#', '', trim( $v ) ),
		explode( ',', (string) wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	);
	if ( in_array( '*', $sent, true ) || in_array( $etag, $sent, true ) ) {
		status_header( 304 );
		exit;
	}
	status_header( 200 );
	header( 'Content-Length: ' . (string) filesize( $file ) );
	// A HEAD request gets the headers alone.
	if ( 'HEAD' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	exit;
}
// WordPress 5.4 renamed the action from do_faviconico to do_favicon; answer on both.
add_action( 'do_favicon', 'tbe_serve_favicon', 5 );
add_action( 'do_faviconico', 'tbe_serve_favicon', 5 );
// WordPress ends a HEAD request before do_favicon, with an empty HTML answer. template_redirect runs first, so
// a HEAD request gets the icon's headers too.
add_action(
	'template_redirect',
	static function () {
		if ( is_favicon() ) {
			tbe_serve_favicon();
		}
	},
	0
);
