<?php
/**
 * Front-end assets. CSS lives in /assets/css/, JavaScript in /assets/js/.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * The version in a theme file's address: the time the file was last written and its size, as "time-size"
 * (0.10.6). The owner uploads by FTP, style.css and functions.php first, so a version taken from the theme went
 * out before the new files were there and visitors got 404s for the stylesheets and scripts. An address now
 * changes only once its own file has changed on the server, and a browser still fetches a changed file at once.
 * The size is there for a file caught while FTP is still writing it: the part written so far is shorter than the
 * whole file, so even when the last write falls in the same second, the finished file gets an address of its own
 * and a partial copy a browser cached is never reused. Null when the file is missing.
 *
 * @param string $rel The file's path inside the theme folder, such as assets/css/base.css.
 */
function tbe_asset_version( string $rel ): ?string {
	$file = TBE_DIR . '/' . ltrim( $rel, '/' );
	if ( ! is_file( $file ) ) {
		return null;
	}
	$time = @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$size = @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return false === $time || false === $size ? null : $time . '-' . $size;
}

/**
 * Enqueue a stylesheet from the theme, versioned by tbe_asset_version(). While the file is missing (an upload
 * still running) the handle is an empty one: the stylesheets that depend on it and its inline CSS still load,
 * and no address that answers 404 goes out.
 *
 * @param string   $handle Handle.
 * @param string   $rel    Path inside the theme folder.
 * @param string[] $deps   Handles it depends on.
 */
function tbe_enqueue_theme_style( string $handle, string $rel, array $deps = array() ): void {
	$ver = tbe_asset_version( $rel );
	if ( null === $ver ) {
		wp_register_style( $handle, false, $deps, TBE_VERSION );
		wp_enqueue_style( $handle );
		return;
	}
	wp_enqueue_style( $handle, TBE_URI . '/' . ltrim( $rel, '/' ), $deps, $ver );
}

/**
 * Enqueue a script from the theme, versioned and with the same empty handle as tbe_enqueue_theme_style()
 * while the file is missing.
 *
 * @param string     $handle Handle.
 * @param string     $rel    Path inside the theme folder.
 * @param string[]   $deps   Handles it depends on.
 * @param array|bool $args   In the footer, and the loading strategy, as wp_enqueue_script() takes them.
 */
function tbe_enqueue_theme_script( string $handle, string $rel, array $deps = array(), $args = array() ): void {
	$ver = tbe_asset_version( $rel );
	if ( null === $ver ) {
		wp_register_script( $handle, false, $deps, TBE_VERSION, $args );
		wp_enqueue_script( $handle );
		return;
	}
	wp_enqueue_script( $handle, TBE_URI . '/' . ltrim( $rel, '/' ), $deps, $ver, $args );
}

/**
 * A preload line for one of the theme's fonts, or nothing while the file is missing.
 *
 * @param string $rel Path inside the theme folder.
 */
function tbe_font_preload( string $rel ): string {
	if ( null === tbe_asset_version( $rel ) ) {
		return '';
	}
	return sprintf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( TBE_URI . '/' . ltrim( $rel, '/' ) ) );
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		$deps = wp_style_is( 'twentytwentyfive-style', 'registered' ) ? array( 'twentytwentyfive-style' ) : array();

		tbe_enqueue_theme_style( 'tbe-base', 'assets/css/base.css', $deps );
		tbe_enqueue_theme_style( 'tbe-reader', 'assets/css/reader.css', array( 'tbe-base' ) );
		tbe_enqueue_theme_style( 'tbe-scripture', 'assets/css/scripture.css', array( 'tbe-reader' ) );
		tbe_enqueue_theme_style( 'tbe-components', 'assets/css/components.css', array( 'tbe-reader' ) );
		wp_add_inline_style( 'tbe-base', tbe_scheme_css() );

		// Runs in <head> so a saved light/dark choice is applied before first paint.
		tbe_enqueue_theme_script( 'tbe-theme', 'assets/js/theme.js', array(), array( 'in_footer' => false ) );

		tbe_enqueue_theme_script( 'tbe-tooltips', 'assets/js/tooltips.js', array(), array( 'in_footer' => true, 'strategy' => 'defer' ) );

		$request = tbe_request();
		if ( $request && 'interlinear' === $request['view'] ) {
			tbe_enqueue_theme_style( 'tbe-interlinear', 'assets/css/interlinear.css', array( 'tbe-components', 'tbe-scripture' ) );
			tbe_enqueue_theme_script( 'tbe-interlinear', 'assets/js/interlinear.js', array(), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}

		// Shape and motion go last so they refine everything above.
		$polish_deps = array( 'tbe-components', 'tbe-scripture' );
		if ( $request && 'interlinear' === $request['view'] ) {
			$polish_deps[] = 'tbe-interlinear';
		}
		tbe_enqueue_theme_style( 'tbe-polish', 'assets/css/polish.css', $polish_deps );

		if ( $request && in_array( $request['view'], array( 'chapter', 'interlinear' ), true ) ) {
			tbe_enqueue_theme_script( 'tbe-audio', 'assets/js/audio.js', array(), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}

		if ( tbe_request() || is_singular( 'tbe_commentary' ) ) {
			tbe_enqueue_theme_script( 'tbe-reader', 'assets/js/reader.js', array(), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}
);

/** Start fetching the Bible font early on reader pages. */
add_action(
	'wp_head',
	static function () {
		if ( tbe_request() ) {
			echo tbe_font_preload( 'assets/fonts/SBL_BLit.woff2' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in tbe_font_preload().
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
