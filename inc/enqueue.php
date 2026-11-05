<?php
/**
 * Front-end assets. CSS lives in /assets/css/, JavaScript in /assets/js/.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * The version in a theme file's address: a hash of the file's contents (0.10.6). The owner uploads by FTP,
 * style.css and functions.php first, so a version taken from the theme went out before the new files were there
 * and visitors got 404s for the stylesheets and scripts. An address now changes only once its own file has
 * changed on the server, and it changes whenever the contents do: a file caught while FTP is still writing it,
 * a file replaced twice in one second, or one uploaded with its old time kept, each gets an address of its own.
 * The hash is xxh3 where PHP has it (8.1 and later), else crc32b; both read the theme's stylesheets, scripts and
 * fonts (about 1.5 MB) in a millisecond or two, from the server's file cache. Null when the file is missing.
 *
 * @param string $rel The file's path inside the theme folder, such as assets/css/base.css.
 */
function tbe_asset_version( string $rel ): ?string {
	$file = TBE_DIR . '/' . ltrim( $rel, '/' );
	$hash = is_file( $file ) ? @hash_file( tbe_asset_hash_algo(), $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return false === $hash ? null : $hash;
}

/** The hash for asset versions: xxh3 where PHP has it, else crc32b. */
function tbe_asset_hash_algo(): string {
	return in_array( 'xxh3', hash_algos(), true ) ? 'xxh3' : 'crc32b';
}

/**
 * Enqueue a stylesheet from the theme, versioned by tbe_asset_version(), with the @font-face rules of the fonts
 * it uses (tbe_attach_font_faces()). While the file is missing (an upload still running) the handle is an empty
 * one: the stylesheets that depend on it and its inline CSS still load, and no address that answers 404 goes out.
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
		tbe_attach_font_faces( $handle );
		return;
	}
	wp_enqueue_style( $handle, TBE_URI . '/' . ltrim( $rel, '/' ), $deps, $ver );
	tbe_attach_font_faces( $handle );
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
 * The address of a theme file with its version (tbe_asset_version()), or '' while the file is missing.
 *
 * @param string $rel Path inside the theme folder.
 */
function tbe_asset_url( string $rel ): string {
	$ver = tbe_asset_version( $rel );
	return null === $ver ? '' : TBE_URI . '/' . ltrim( $rel, '/' ) . '?ver=' . rawurlencode( $ver );
}

/**
 * The theme's fonts: family, file and the characters each covers. Their @font-face rules are written here, not
 * in the stylesheets, so each address carries its file's version and a font whose file is missing (an upload
 * still running) is left out: the text falls back to the next face in its stack, and no 404 request goes out.
 */
function tbe_font_faces(): array {
	return array(
		'angel'      => array( 'Angel', 'assets/fonts/Angel.woff2', '' ),
		'naskh'      => array( 'Noto Naskh Arabic', 'assets/fonts/NotoNaskhArabic-Regular.woff2', 'U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF' ),
		'biblit'     => array( 'SBL BibLit', 'assets/fonts/SBL_BLit.woff2', '' ),
		'hebrew'     => array( 'SBL Hebrew', 'assets/fonts/SBL_Hbrw.woff2', 'U+0590-05FF, U+FB1D-FB4F' ),
		'greek'      => array( 'SBL Greek', 'assets/fonts/SBL_grk.woff2', 'U+0370-03FF, U+1F00-1FFF' ),
		'estrangela' => array( 'Estrangela', 'assets/fonts/Estrangela.woff2', 'U+0700-074F' ),
	);
}

/** The fonts each stylesheet carries the rules for. */
function tbe_font_face_handles(): array {
	return array(
		'tbe-base'      => array( 'angel' ),
		'tbe-login'     => array( 'angel' ),
		'tbe-reader'    => array( 'naskh' ),
		'tbe-scripture' => array( 'biblit', 'hebrew', 'greek', 'estrangela' ),
	);
}

/**
 * The @font-face rules for some of the theme's fonts, with versioned addresses; a font whose file is missing is
 * left out.
 *
 * @param string[] $keys Keys of tbe_font_faces().
 */
function tbe_font_face_css( array $keys ): string {
	$css   = '';
	$faces = tbe_font_faces();
	foreach ( $keys as $key ) {
		if ( ! isset( $faces[ $key ] ) ) {
			continue;
		}
		list( $family, $rel, $range ) = $faces[ $key ];
		$url = tbe_asset_url( $rel );
		if ( '' === $url ) {
			continue;
		}
		$css .= '@font-face{font-family:"' . $family . '";src:url("' . esc_url( $url ) . '") format("woff2");font-weight:normal;font-style:normal;font-display:swap' . ( '' !== $range ? ';unicode-range:' . $range : '' ) . '}';
	}
	return $css;
}

/**
 * Attach the @font-face rules to the stylesheet that uses them, once per request, when it is enqueued.
 *
 * @param string $handle Handle of a theme stylesheet.
 */
function tbe_attach_font_faces( string $handle ): void {
	static $done = array();
	if ( isset( $done[ $handle ] ) ) {
		return;
	}
	$done[ $handle ] = true;
	$keys            = tbe_font_face_handles()[ $handle ] ?? array();
	$css  = $keys ? tbe_font_face_css( $keys ) : '';
	if ( '' !== $css ) {
		wp_add_inline_style( $handle, $css );
	}
}

/**
 * A preload line for one of the theme's fonts, at the same versioned address its @font-face rule names, or
 * nothing while the file is missing.
 *
 * @param string $rel Path inside the theme folder.
 */
function tbe_font_preload( string $rel ): string {
	$url = tbe_asset_url( $rel );
	if ( '' === $url ) {
		return '';
	}
	return sprintf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( $url ) );
}

/** The editor loads base.css and scripture.css as editor styles; the fonts they use come with them. */
add_filter(
	'block_editor_settings_all',
	static function ( $settings ) {
		$css = tbe_font_face_css( array( 'angel', 'biblit', 'hebrew', 'greek', 'estrangela' ) );
		if ( '' !== $css && is_array( $settings ) ) {
			$settings['styles']   = (array) ( $settings['styles'] ?? array() );
			$settings['styles'][] = array( 'css' => $css );
		}
		return $settings;
	}
);

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
