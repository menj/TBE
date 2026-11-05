<?php
/**
 * Reading progress bar (0.8.0): a thin bar at the top or bottom of the window fills as the reader
 * scrolls a single post, page, commentary note or Bible chapter, on the types chosen under
 * Appearance → TBE → Reading bar (never the front page). An empty colour follows the scheme.
 * The bar carries aria-hidden: the browser's scroll bar already tells every reader where they
 * are, and a live progressbar would announce itself on every scroll. While the Reading
 * Progress Bar plugin is active the theme prints no bar.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** What the bar can be shown on: post type => label, plus "bible" for the reader's chapter pages. */
function tbe_progress_type_choices(): array {
	$out = array( 'bible' => __( 'Bible chapters and interlinear pages', 'tbe-child' ) );
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $obj ) {
		if ( 'attachment' !== $name ) {
			$out[ $name ] = $obj->labels->singular_name;
		}
	}
	return $out;
}

/** Whether the bar belongs on this view. */
function tbe_progress_wanted(): bool {
	if ( function_exists( 'rp_show_it' ) || ! tbe_setting( 'progress' ) || is_front_page() ) {
		return false;
	}
	$types   = (array) tbe_setting( 'progress_types' );
	$request = function_exists( 'tbe_request' ) ? tbe_request() : null;
	if ( $request ) {
		return in_array( 'bible', $types, true ) && in_array( $request['view'], array( 'chapter', 'interlinear' ), true );
	}
	return is_singular() && in_array( get_post_type(), $types, true );
}

/** The settings as custom properties on the bar. */
function tbe_progress_css(): string {
	$rules = '--tbe-progress-h:' . max( 1, min( 12, (int) tbe_setting( 'progress_height' ) ) ) . 'px;';
	foreach ( array( 'progress_color' => '--tbe-progress-fill', 'progress_track' => '--tbe-progress-track' ) as $key => $var ) {
		$hex = sanitize_hex_color( (string) tbe_setting( $key ) );
		if ( $hex ) {
			$rules .= $var . ':' . $hex . ';';
		}
	}
	return '.tbe-progress{' . $rules . '}';
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! tbe_progress_wanted() ) {
			return;
		}
		tbe_enqueue_theme_style( 'tbe-progress', 'assets/css/progress.css', array( 'tbe-base' ) );
		wp_add_inline_style( 'tbe-progress', tbe_progress_css() );
		tbe_enqueue_theme_script( 'tbe-progress', 'assets/js/progress.js', array(), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
);

add_action(
	'wp_body_open',
	static function () {
		if ( tbe_progress_wanted() ) {
			$pos = 'bottom' === tbe_setting( 'progress_position' ) ? 'bottom' : 'top';
			printf( '<div class="tbe-progress tbe-progress--%s" aria-hidden="true" hidden><span class="tbe-progress__fill"></span></div>' . "\n", esc_attr( $pos ) );
		}
	}
);
