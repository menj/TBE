<?php
/**
 * Brand assets: favicon, touch icon, social preview and theme colour.
 * Used only when the site owner has not set a Site Icon under Appearance → Customize.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * The address of a brand file, versioned by the file's own time and size (tbe_asset_version()), so it changes only
 * once the new file is up. '' while the file is missing (an upload still running): the tag is then left out.
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

add_action(
	'wp_head',
	static function () {
		// The site name is above the fold on every page; fetch its font early so the title does not jump when it loads.
		echo tbe_font_preload( 'assets/fonts/Angel.woff2' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in tbe_font_preload().
		if ( ! has_site_icon() ) {
			tbe_brand_tag( '<link rel="icon" href="%s" type="image/svg+xml">', 'favicon.svg' );
			tbe_brand_tag( '<link rel="icon" href="%s" sizes="32x32" type="image/png">', 'favicon-32.png' );
			tbe_brand_tag( '<link rel="apple-touch-icon" href="%s">', 'apple-touch-icon.png' );
		}
		$colors = tbe_active_colors();
		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( (string) ( $colors['accent'] ?? '#6e1f2e' ) ) );
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
	if ( has_site_icon() ) {
		return;
	}
	header( 'Content-Type: image/x-icon' );
	header( 'Cache-Control: public, max-age=604800' );
	readfile( TBE_DIR . '/assets/img/brand/favicon.ico' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
// WordPress 5.4 renamed the action from do_faviconico to do_favicon; answer on both.
add_action( 'do_favicon', 'tbe_serve_favicon', 5 );
add_action( 'do_faviconico', 'tbe_serve_favicon', 5 );
