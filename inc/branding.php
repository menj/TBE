<?php
/**
 * Brand assets: favicon, touch icon, social preview and theme colour.
 * Used only when the site owner has not set a Site Icon under Appearance → Customize.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

function tbe_brand_url( string $file ): string {
	return TBE_URI . '/assets/img/brand/' . $file;
}

add_action(
	'wp_head',
	static function () {
		// The site name is above the fold on every page; fetch its font early so the title does not jump when it loads.
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( TBE_URI . '/assets/fonts/Angel.woff2' ) );
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( tbe_brand_url( 'favicon.svg' ) ) );
			printf( '<link rel="icon" href="%s" sizes="32x32" type="image/png">' . "\n", esc_url( tbe_brand_url( 'favicon-32.png' ) ) );
			printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( tbe_brand_url( 'apple-touch-icon.png' ) ) );
		}
		$colors = tbe_active_colors();
		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( (string) ( $colors['accent'] ?? '#6e1f2e' ) ) );
		// An SEO plugin that writes its own social tags can switch these off: add_filter( 'tbe_social_meta', '__return_false' ).
		if ( ! apply_filters( 'tbe_social_meta', ! defined( 'WPSEO_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) ) ) {
			return;
		}
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( tbe_brand_url( 'social-preview.png' ) ) );
		echo '<meta property="og:image:width" content="1200">' . "\n" . '<meta property="og:image:height" content="630">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( tbe_brand_url( 'social-preview.png' ) ) );
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
