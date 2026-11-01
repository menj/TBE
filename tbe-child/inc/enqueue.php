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
		wp_add_inline_style( 'tbe-base', tbe_scheme_css() );

		// Runs in <head> so a saved light/dark choice is applied before first paint.
		wp_enqueue_script( 'tbe-theme', TBE_URI . '/assets/js/theme.js', array(), TBE_VERSION, array( 'in_footer' => false ) );

		if ( tbe_request() || is_singular( 'tbe_commentary' ) ) {
			wp_enqueue_script( 'tbe-reader', TBE_URI . '/assets/js/reader.js', array(), TBE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}
);
