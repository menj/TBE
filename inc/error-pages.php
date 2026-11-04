<?php
/**
 * Themed error pages.
 *
 *  - Standalone pages for WordPress's three drop-ins in wp-content: maintenance.php (503), db-error.php
 *    (the database is down) and php-error.php (a fatal PHP error). The theme writes them itself, on
 *    activation and after an update, and removes its own copies when another theme is activated.
 *  - Static pages in errors/ for the errors the web server answers before WordPress runs (400, 401, 403,
 *    404, 500, 502, 503), for use with ErrorDocument lines in .htaccess. See Appearance → TBE → Error pages.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

const TBE_DROPIN_VERSION = '2';

require_once __DIR__ . '/error-page-html.php';

/* Drop-ins in wp-content -------------------------------------------------- */

function tbe_dropin_specs(): array {
	return array(
		'maintenance.php' => array( 'key' => 'maintenance', 'status' => 503, 'retry' => 300 ),
		'db-error.php'    => array( 'key' => 'db-error', 'status' => 503, 'retry' => 600 ),
		'php-error.php'   => array( 'key' => 'php-error', 'status' => 500, 'retry' => 0 ),
	);
}

/** The text of one drop-in file: headers first, then the page. */
function tbe_dropin_source( string $file ): string {
	$spec    = tbe_dropin_specs()[ $file ];
	$font    = wp_parse_url( get_stylesheet_directory_uri() . '/assets/fonts/Angel.woff2', PHP_URL_PATH );
	$headers = "\tif ( ! headers_sent() ) {\n\t\theader( 'HTTP/1.1 " . $spec['status'] . " ' . " . ( 503 === $spec['status'] ? "'Service Unavailable'" : "'Internal Server Error'" ) . ", true, " . $spec['status'] . " );\n\t\theader( 'Content-Type: text/html; charset=utf-8' );\n"
		. ( $spec['retry'] ? "\t\theader( 'Retry-After: " . $spec['retry'] . "' );\n" : '' )
		. "\t\theader( 'X-Robots-Tag: noindex' );\n\t\theader( 'Cache-Control: no-store, max-age=0' );\n\t}\n";
	return "<?php\n// TBE error-page drop-in " . TBE_DROPIN_VERSION . '. Written by the TBE theme; safe to delete.' . "\n" . $headers . "?>\n" . tbe_error_page_html( $spec['key'], (string) $font ) . "\n";
}

/**
 * 'missing', 'ours' (written by this theme, current), 'outdated' (written by this theme, an older copy), 'theme'
 * (written by another theme) or 'foreign' (anyone else's). Only 'missing', 'ours' and 'outdated' are written:
 * from 0.10.0 a file another theme wrote is left alone too, since the theme never overwrites a file it did not write.
 */
function tbe_dropin_state( string $file ): string {
	$path = WP_CONTENT_DIR . '/' . $file;
	if ( ! file_exists( $path ) ) {
		return 'missing';
	}
	$head = (string) file_get_contents( $path, false, null, 0, 400 );
	if ( false !== strpos( $head, 'Written by the TBE theme; safe to delete' ) ) {
		return (string) file_get_contents( $path ) === tbe_dropin_source( $file ) ? 'ours' : 'outdated';
	}
	return preg_match( '/Written by the .{1,60} theme; safe to delete/', $head ) ? 'theme' : 'foreign';
}

/**
 * Write the three drop-ins. Missing files, this theme's own and another theme's (which say they are safe to
 * delete) are written; a file with no such marker is somebody's own and is never touched.
 *
 * @return array<string,string> file => written | unchanged | kept | failed
 */
function tbe_install_dropins(): array {
	$result = array();
	foreach ( array_keys( tbe_dropin_specs() ) as $file ) {
		$state = tbe_dropin_state( $file );
		if ( 'foreign' === $state || 'theme' === $state ) {
			$result[ $file ] = 'kept';
			continue;
		}
		if ( 'ours' === $state ) {
			$result[ $file ] = 'unchanged';
			continue;
		}
		$path = WP_CONTENT_DIR . '/' . $file;
		// A temporary name, then a rename, so WordPress never reads half a file.
		$tmp             = $path . '.' . uniqid( 'tbe-', true ) . '.tmp';
		$source          = tbe_dropin_source( $file );
		// The whole text or nothing: a short write never replaces the live file.
		$result[ $file ] = ( is_writable( WP_CONTENT_DIR ) && strlen( $source ) === @file_put_contents( $tmp, $source ) && @rename( $tmp, $path ) ) ? 'written' : 'failed'; // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		}
	}
	// Only remember a clean install: a failed write (wp-content not writable) is tried again on the next admin visit.
	if ( ! in_array( 'failed', $result, true ) ) {
		update_option( 'tbe_dropins', TBE_VERSION . '|' . TBE_DROPIN_VERSION, false );
	}
	return $result;
}

/**
 * Remove this theme's own copies (only those carrying its marker). An outdated copy counts: on switch_theme the
 * stylesheet already names the new theme, so the font URL in a current copy no longer matches.
 */
function tbe_remove_dropins(): void {
	foreach ( array_keys( tbe_dropin_specs() ) as $file ) {
		if ( in_array( tbe_dropin_state( $file ), array( 'ours', 'outdated' ), true ) ) {
			wp_delete_file( WP_CONTENT_DIR . '/' . $file );
		}
	}
	delete_option( 'tbe_dropins' );
}

add_action(
	'after_switch_theme',
	static function () {
		if ( tbe_setting( 'error_pages' ) ) {
			tbe_install_dropins();
		}
	}
);
add_action(
	'admin_init',
	static function () {
		if ( current_user_can( 'manage_options' ) && tbe_setting( 'error_pages' ) && get_option( 'tbe_dropins' ) !== TBE_VERSION . '|' . TBE_DROPIN_VERSION ) {
			tbe_install_dropins();
		}
	},
	30
);
add_action( 'switch_theme', 'tbe_remove_dropins' );

/**
 * The 404 page: noindex, follow, and its title in the site's form, "Page not found | The Bible Exposed".
 */
add_filter(
	'wp_robots',
	static function ( array $robots ): array {
		if ( is_404() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);
add_filter(
	'document_title_separator',
	static function ( string $sep ): string {
		return is_404() ? '|' : $sep;
	}
);

/** The lines to paste into .htaccess so the web server shows the static pages for the errors it answers itself. */
function tbe_htaccess_snippet(): string {
	$base  = wp_parse_url( get_stylesheet_directory_uri(), PHP_URL_PATH ) . '/errors/';
	$lines = array( '# The Bible Exposed: themed pages for errors the web server answers before WordPress runs' );
	foreach ( array( 400, 401, 403, 404, 500, 502, 503 ) as $code ) {
		$lines[] = 'ErrorDocument ' . $code . ' ' . $base . $code . '.html';
	}
	return implode( "\n", $lines );
}

add_action(
	'admin_post_tbe_install_dropins',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'tbe-child' ), 403 );
		}
		check_admin_referer( 'tbe_install_dropins' );
		$result = tbe_install_dropins();
		wp_safe_redirect( add_query_arg( array( 'page' => 'tbe-settings', 'tab' => 'errors', 'tbe_dropins' => implode( ',', array_map( static fn( $f, $s ) => $f . ':' . $s, array_keys( $result ), $result ) ) ), admin_url( 'themes.php' ) ) );
		exit;
	}
);

function tbe_render_errors_tab(): void {
	// phpcs:disable WordPress.Security.NonceVerification
	if ( ! empty( $_GET['tbe_dropins'] ) ) {
		echo '<div class="notice notice-info inline"><p>' . esc_html( str_replace( array( ',', ':' ), array( ' · ', ' — ' ), sanitize_text_field( wp_unslash( $_GET['tbe_dropins'] ) ) ) ) . '</p></div>';
	}
	// phpcs:enable
	$words = array(
		'missing' => __( 'not installed', 'tbe-child' ),
		'ours'     => __( 'installed (this theme), current', 'tbe-child' ),
		'outdated' => __( 'installed (this theme), outdated: press the button to refresh it', 'tbe-child' ),
		'theme'    => __( 'written by another theme; left alone', 'tbe-child' ),
		'foreign' => __( 'your own file; left alone', 'tbe-child' ),
	);
	echo '<h2>' . esc_html__( 'Errors WordPress answers', 'tbe-child' ) . '</h2><p>' . esc_html__( 'Three pages are written into wp-content so they can show even when WordPress itself is down: maintenance, database error and fatal error. They are installed on activation and after every update, and removed if another theme is activated. A file the theme did not write, yours or another theme’s, is never overwritten.', 'tbe-child' ) . '</p><table class="widefat striped tbe-table-admin"><thead><tr><th>' . esc_html__( 'File', 'tbe-child' ) . '</th><th>' . esc_html__( 'Shown when', 'tbe-child' ) . '</th><th>' . esc_html__( 'Status', 'tbe-child' ) . '</th></tr></thead><tbody>';
	$when = array(
		'maintenance.php' => __( 'WordPress is updating (503)', 'tbe-child' ),
		'db-error.php'    => __( 'the database cannot be reached (503)', 'tbe-child' ),
		'php-error.php'   => __( 'a fatal PHP error stops a page (500)', 'tbe-child' ),
	);
	foreach ( $when as $file => $label ) {
		echo '<tr><td><code>wp-content/' . esc_html( $file ) . '</code></td><td>' . esc_html( $label ) . '</td><td>' . esc_html( $words[ tbe_dropin_state( $file ) ] ) . '</td></tr>';
	}
	echo '</tbody></table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1rem"><input type="hidden" name="action" value="tbe_install_dropins">';
	wp_nonce_field( 'tbe_install_dropins' );
	echo '<button class="button button-primary">' . esc_html__( 'Install or refresh these pages', 'tbe-child' ) . '</button></form>';

	echo '<h2 style="margin-top:2rem">' . esc_html__( 'Errors the web server answers', 'tbe-child' ) . '</h2><p>' . esc_html__( 'Errors such as 400, 401, 403, 404 for files, 500, 502 and 503 are produced by the web server before WordPress starts, so the theme cannot change them on its own. Static pages for each are in the theme’s errors folder. To use them, paste these lines at the top of your .htaccess file (or set each page under Error Pages in cPanel):', 'tbe-child' ) . '</p>';
	echo '<textarea readonly rows="9" class="large-text code" onclick="this.select()">' . esc_textarea( tbe_htaccess_snippet() ) . '</textarea>';
}
