<?php
/**
 * The Bible Exposed — child theme of Twenty Twenty-Five.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

define( 'TBE_VERSION', '0.10.24' );
define( 'TBE_DIR', get_stylesheet_directory() );
define( 'TBE_URI', get_stylesheet_directory_uri() );

/** The theme's files in inc/, in the order they load, without ".php". */
function tbe_theme_files(): array {
	return array( 'settings', 'bible', 'canon', 'interlinear', 'dss', 'manuscripts', 'lexicon', 'audio', 'esv-api', 'quran', 'commentary', 'frontend-edit', 'entry-fields', 'starter-commentary', 'glossary', 'enrich', 'search', 'schema', 'routes', 'reader', 'blocks', 'article', 'contextual-links', 'enqueue', 'branding', 'error-pages', 'social', 'progress', 'unlist', 'search-permalinks', 'login', 'site-links', 'hardening', 'sitemaps', 'dev-files' );
}

/**
 * Files in inc/ that other files load, checked with the rest before anything loads.
 */
function tbe_theme_support_files(): array {
	return array( 'error-page-html', 'unlist-admin' );
}

/**
 * Whether this request is for the login screen: wp-login.php, or a private
 * login address (the WPS Hide Login plugin's, or the theme's own), read from
 * the stored options, since the theme's own code may be the part missing.
 * The owner must be able to log in while an upload finishes.
 *
 * @return bool
 */
function tbe_incomplete_is_login(): bool {
	$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( 'wp-login.php' === ( $GLOBALS['pagenow'] ?? '' ) || false !== strpos( $uri, 'wp-login' ) ) {
		return true;
	}
	$slugs = array( (string) get_option( 'whl_page', '' ) );
	$path = trim( (string) parse_url( $uri, PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$home = function_exists( 'home_url' ) ? trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( '' !== $home && 0 === strpos( $path . '/', $home . '/' ) ) {
		$path = trim( substr( $path, strlen( $home ) ), '/' );
	}
	foreach ( array_filter( $slugs ) as $slug ) {
		if ( $slug === $path || isset( $_GET[ $slug ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
	}
	return false;
}

/**
 * Whether tbe_incomplete_theme() sends the page to this request. The login screen, AJAX, cron and the command
 * line never get it; the admin gets it only when $all is set.
 */
function tbe_incomplete_serves( bool $all = false, string $sapi = PHP_SAPI ): bool {
	if ( wp_doing_ajax() || wp_doing_cron() || 'cli' === $sapi || tbe_incomplete_is_login() ) {
		return false;
	}
	return $all || ! is_admin();
}

/**
 * The theme caught half uploaded: an FTP upload that sends functions.php before inc/ would end every request in
 * a fatal error. Every file is checked before any loads, and each loads inside a try, so a half-written one (a
 * ParseError) is caught too. The request gets the static 503 page with Retry-After; the error log gets one line
 * per problem every ten minutes. A missing file leaves the admin alone, so the upload can finish; a file that
 * breaks part-way sends the page to the admin too, since the theme is then half set up.
 *
 * @param string $what What is wrong, for the log.
 * @param bool   $all  Send the page to the admin screens too.
 */
function tbe_incomplete_theme( string $what, bool $all = false ): void {
	$key = 'tbe_incomplete_' . md5( $what );
	if ( ! get_transient( $key ) ) {
		set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
		error_log( 'TBE: the theme is incomplete or being uploaded (' . $what . '); visitors see the 503 page until it is whole.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
	if ( ! tbe_incomplete_serves( $all ) ) {
		return;
	}
	if ( ! headers_sent() ) {
		header( 'HTTP/1.1 503 Service Unavailable', true, 503 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Retry-After: 300' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: no-store, max-age=0' );
	}
	// The site's own copy of the static page, then the theme's, then a plain one. The theme's copy names its font
	// relative to errors/, which would resolve against this request's address, so it gets the theme's real one
	// (its ?ver= stays).
	if ( is_readable( WP_CONTENT_DIR . '/tbe-errors/503.html' ) ) {
		readfile( WP_CONTENT_DIR . '/tbe-errors/503.html' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
	if ( is_readable( TBE_DIR . '/errors/503.html' ) ) {
		echo str_replace( 'url("../assets/fonts/', 'url("' . TBE_URI . '/assets/fonts/', (string) file_get_contents( TBE_DIR . '/errors/503.html' ) ); // phpcs:ignore WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions
		exit;
	}
	echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Back shortly</title></head><body><h1>Back shortly</h1><p>The site is being updated. Please try again in a few minutes.</p></body></html>';
	exit;
}

foreach ( array_merge( tbe_theme_files(), tbe_theme_support_files() ) as $tbe_file ) {
	if ( ! is_readable( TBE_DIR . '/inc/' . $tbe_file . '.php' ) ) {
		tbe_incomplete_theme( 'inc/' . $tbe_file . '.php is missing' );
		return;
	}
}
try {
	foreach ( tbe_theme_files() as $tbe_file ) {
		require_once TBE_DIR . '/inc/' . $tbe_file . '.php';
	}
} catch ( Throwable $tbe_error ) {
	tbe_incomplete_theme( $tbe_error->getMessage() . ' in ' . basename( $tbe_error->getFile() ), true );
	return;
}
unset( $tbe_file );

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/base.css', 'assets/css/scripture.css' ) );
	}
);

/** Flush rewrite rules once on activation and whenever the theme version changes. */
add_action(
	'init',
	static function () {
		if ( get_option( 'tbe_rewrite_version' ) !== TBE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'tbe_rewrite_version', TBE_VERSION, false );
		}
	},
	99
);
