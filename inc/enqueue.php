<?php
/**
 * Front-end assets. CSS lives in /assets/css/, JavaScript in /assets/js/.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function () {
		$deps = wp_style_is( 'twentytwentyfive-style', 'registered' ) ? array( 'twentytwentyfive-style' ) : array();

		wp_enqueue_style( 'tbe-base', TBE_URI . '/assets/css/base.css', $deps, TBE_VERSION );
		wp_enqueue_style( 'tbe-reader', TBE_URI . '/assets/css/reader.css', array( 'tbe-base' ), TBE_VERSION );
		wp_enqueue_style( 'tbe-scripture', TBE_URI . '/assets/css/scripture.css', array( 'tbe-reader' ), TBE_VERSION );
		wp_enqueue_style( 'tbe-components', TBE_URI . '/assets/css/components.css', array( 'tbe-reader' ), TBE_VERSION );
		wp_add_inline_style( 'tbe-base', tbe_scheme_css() );

		// Runs in <head> so a saved light/dark choice is applied before first paint.
		wp_enqueue_script( 'tbe-theme', TBE_URI . '/assets/js/theme.js', array(), TBE_VERSION, array( 'in_footer' => false ) );

		wp_enqueue_script( 'tbe-tooltips', TBE_URI . '/assets/js/tooltips.js', array(), TBE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		if ( tbe_request() || is_singular( 'tbe_commentary' ) ) {
			wp_enqueue_script( 'tbe-reader', TBE_URI . '/assets/js/reader.js', array(), TBE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}
);

/** Start fetching the Bible font early on reader pages. */
add_action(
	'wp_head',
	static function () {
		if ( tbe_request() ) {
			printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( TBE_URI . '/assets/fonts/SBL_BLit.woff2' ) );
		}
	},
	1
);

/** A "Scripture" style for Quote blocks, so Bible quotations in commentary use the Bible font. */
add_action(
	'init',
	static function () {
		register_block_style( 'core/quote', array( 'name' => 'scripture', 'label' => __( 'Scripture', 'tbe-child' ) ) );
	}
);
