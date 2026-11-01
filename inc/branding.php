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
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( tbe_brand_url( 'favicon.svg' ) ) );
			printf( '<link rel="icon" href="%s" sizes="32x32" type="image/png">' . "\n", esc_url( tbe_brand_url( 'favicon-32.png' ) ) );
			printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( tbe_brand_url( 'apple-touch-icon.png' ) ) );
		}
		$colors = tbe_active_colors();
		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( (string) ( $colors['accent'] ?? '#6e1f2e' ) ) );
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( tbe_brand_url( 'social-preview.png' ) ) );
		echo '<meta property="og:image:width" content="1200">' . "\n" . '<meta property="og:image:height" content="630">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( tbe_brand_url( 'social-preview.png' ) ) );
	},
	2
);

/** Browsers ask for /favicon.ico on their own; answer with the brand icon unless a Site Icon is set. */
add_action(
	'do_faviconico',
	static function () {
		if ( has_site_icon() ) {
			return;
		}
		header( 'Content-Type: image/x-icon' );
		header( 'Cache-Control: public, max-age=604800' );
		readfile( TBE_DIR . '/assets/img/brand/favicon.ico' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	},
	5
);
