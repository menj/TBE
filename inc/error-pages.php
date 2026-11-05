<?php
/**
 * Themed error pages.
 *
 *  - Standalone pages for WordPress's three drop-ins in wp-content: maintenance.php (503), db-error.php
 *    (the database is down) and php-error.php (a fatal PHP error). The theme writes them itself, on
 *    activation and after an update, and removes its own copies when another theme is activated.
 *  - Static pages for the errors the web server answers before WordPress runs (400, 401, 403, 404, 500, 502,
 *    503, 504), for use with ErrorDocument lines in .htaccess. errors/ holds the copies for a site at the domain
 *    root with the theme in /wp-content/themes/tbe/; the theme writes a copy of each into wp-content/tbe-errors/
 *    with this site's home address and font path, so they also work in a subfolder. See Appearance → TBE → Error pages.
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

/** The folder in wp-content that holds this site's copies of the static pages. */
const TBE_STATIC_ERRORS = 'tbe-errors';

/** The codes the web server gets a static page for. */
function tbe_static_error_codes(): array {
	return array( '400', '401', '403', '404', '500', '502', '503', '504' );
}

/** Every file the theme writes, relative to wp-content: the three drop-ins, then the static copies. */
function tbe_error_files(): array {
	$files = array_keys( tbe_dropin_specs() );
	foreach ( tbe_static_error_codes() as $code ) {
		$files[] = TBE_STATIC_ERRORS . '/' . $code . '.html';
	}
	return $files;
}

/** The root-relative home address, with a trailing slash. */
function tbe_error_home_path(): string {
	$path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	return '' !== $path ? $path : '/';
}

/** What the files embed besides the theme version: the home address and the theme folder. */
function tbe_dropin_stamp(): string {
	return TBE_VERSION . '|' . TBE_DROPIN_VERSION . '|' . md5( home_url( '/' ) . '|' . get_stylesheet_directory_uri() );
}

/**
 * The address of Angel.woff2: root-relative when the theme is served from the site's own host, in full when a
 * plugin serves theme files from another host (a CDN), so the font is asked for where it is.
 */
function tbe_error_font_url(): string {
	$url = get_stylesheet_directory_uri() . '/assets/fonts/Angel.woff2';
	return wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ? (string) wp_parse_url( $url, PHP_URL_PATH ) : $url;
}

/**
 * Whether this site writes the shared files in wp-content: any site off a network, the main site of a network,
 * or, while the main site runs another theme, the one subsite with the lowest ID that runs this one. Without the
 * last case a network whose main site had switched away kept the files of the old version for good, and one that
 * never ran the theme on its main site never got them. One writer only, so the files never flip between
 * subsites' addresses.
 */
function tbe_error_files_owner(): bool {
	if ( ! is_multisite() || is_main_site() ) {
		return true;
	}
	$main = get_main_site_id();
	if ( tbe_error_files_site_runs_theme( $main ) ) {
		return false;
	}
	return get_current_blog_id() === tbe_error_files_first_theme_site();
}

/** Whether a site of the network runs this theme, as its stylesheet or its template. */
function tbe_error_files_site_runs_theme( int $id ): bool {
	$slug = basename( dirname( __DIR__ ) );
	return in_array( $slug, array( get_blog_option( $id, 'stylesheet' ), get_blog_option( $id, 'template' ) ), true );
}

/**
 * The sites of the network, 500 at a time, in order of ID, so a large network is read in full without loading
 * every site at once. The callback returns true to stop.
 *
 * @param callable $visit Called with each site ID.
 * @param int[]    $skip  Site IDs left out.
 */
function tbe_error_files_walk_sites( callable $visit, array $skip = array() ): void {
	$offset = 0;
	do {
		$ids = get_sites(
			array(
				'fields'       => 'ids',
				'number'       => 500,
				'offset'       => $offset,
				'orderby'      => 'id',
				'order'        => 'ASC',
				'site__not_in' => $skip,
			)
		);
		foreach ( $ids as $id ) {
			if ( $visit( (int) $id ) ) {
				return;
			}
		}
		$offset += 500;
	} while ( 500 === count( $ids ) );
}

/** The site with the lowest ID that runs this theme; 0 when none does. */
function tbe_error_files_first_theme_site(): int {
	$first = 0;
	tbe_error_files_walk_sites(
		static function ( int $id ) use ( &$first ): bool {
			if ( tbe_error_files_site_runs_theme( $id ) ) {
				$first = $id;
				return true;
			}
			return false;
		}
	);
	return $first;
}

/** Whether another site of the network, the main site included, still runs this theme. The shared files then stay. */
function tbe_error_files_in_use_elsewhere(): bool {
	if ( ! is_multisite() ) {
		return false;
	}
	$found = false;
	tbe_error_files_walk_sites(
		static function ( int $id ) use ( &$found ): bool {
			$found = tbe_error_files_site_runs_theme( $id );
			return $found;
		},
		array( get_current_blog_id() )
	);
	return $found;
}

/** Whether any of the theme's files is missing from wp-content. */
function tbe_error_files_missing(): bool {
	foreach ( tbe_error_files() as $file ) {
		if ( ! file_exists( WP_CONTENT_DIR . '/' . $file ) ) {
			return true;
		}
	}
	return false;
}

/** The text of one file: for a drop-in, headers first, then the page; for a static copy, the marker, then the page. */
function tbe_dropin_source( string $file ): string {
	$font = tbe_error_font_url();
	if ( 0 === strpos( $file, TBE_STATIC_ERRORS . '/' ) ) {
		return '<!-- TBE error page ' . TBE_DROPIN_VERSION . '. Written by the TBE theme; safe to delete. -->' . "\n" . tbe_error_page_html( basename( $file, '.html' ), (string) $font, tbe_error_home_path() ) . "\n";
	}
	$spec    = tbe_dropin_specs()[ $file ];
	$headers = "\tif ( ! headers_sent() ) {\n\t\theader( 'HTTP/1.1 " . $spec['status'] . " ' . " . ( 503 === $spec['status'] ? "'Service Unavailable'" : "'Internal Server Error'" ) . ", true, " . $spec['status'] . " );\n\t\theader( 'Content-Type: text/html; charset=utf-8' );\n"
		. ( $spec['retry'] ? "\t\theader( 'Retry-After: " . $spec['retry'] . "' );\n" : '' )
		. "\t\theader( 'X-Robots-Tag: noindex' );\n\t\theader( 'Cache-Control: no-store, max-age=0' );\n\t}\n";
	return "<?php\n// TBE error-page drop-in " . TBE_DROPIN_VERSION . '. Written by the TBE theme; safe to delete.' . "\n" . $headers . "?>\n" . tbe_error_page_html( $spec['key'], (string) $font, tbe_error_home_path() ) . "\n";
}

/**
 * 'missing', 'ours' (written by this theme, current), 'outdated' (written by this theme, an older copy), 'theme'
 * (written by another theme, marked safe to delete) or 'foreign' (anyone else's). All but 'foreign' are written:
 * a sibling theme's leftover says it is safe to delete, so from 0.10.2 it is replaced; a file with no marker is never touched.
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
	if ( ! tbe_error_files_owner() ) {
		return $result;
	}
	foreach ( tbe_error_files() as $file ) {
		$state = tbe_dropin_state( $file );
		if ( 'foreign' === $state ) {
			$result[ $file ] = 'kept';
			continue;
		}
		if ( 'ours' === $state ) {
			$result[ $file ] = 'unchanged';
			continue;
		}
		$path = WP_CONTENT_DIR . '/' . $file;
		if ( ! is_dir( dirname( $path ) ) ) {
			wp_mkdir_p( dirname( $path ) );
		}
		// A temporary name, then a rename, so WordPress never reads half a file.
		$tmp             = $path . '.' . uniqid( 'tbe-', true ) . '.tmp';
		$source          = tbe_dropin_source( $file );
		// The whole text or nothing: a short write never replaces the live file.
		$result[ $file ] = ( is_writable( dirname( $path ) ) && strlen( $source ) === @file_put_contents( $tmp, $source ) && @rename( $tmp, $path ) ) ? 'written' : 'failed'; // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		if ( file_exists( $tmp ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		}
	}
	// Only remember a clean install: a failed write (wp-content not writable) is tried again on the next admin visit.
	if ( ! in_array( 'failed', $result, true ) ) {
		update_option( 'tbe_dropins', tbe_dropin_stamp(), false );
	}
	return $result;
}

/**
 * Remove this theme's own copies (only those carrying its marker). An outdated copy counts: on switch_theme the
 * stylesheet already names the new theme, so the font URL in a current copy no longer matches. On a network the
 * files are shared: they stay while any other site, the main site included, still runs the theme, and the last
 * site to switch away removes them, even a subsite.
 */
function tbe_remove_dropins(): void {
	if ( tbe_error_files_in_use_elsewhere() ) {
		delete_option( 'tbe_dropins' );
		return;
	}
	foreach ( tbe_error_files() as $file ) {
		if ( in_array( tbe_dropin_state( $file ), array( 'ours', 'outdated' ), true ) ) {
			wp_delete_file( WP_CONTENT_DIR . '/' . $file );
		}
	}
	if ( is_dir( WP_CONTENT_DIR . '/' . TBE_STATIC_ERRORS ) ) {
		@rmdir( WP_CONTENT_DIR . '/' . TBE_STATIC_ERRORS ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- only when empty.
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
		// Also when a file has gone missing (deleted by hand, or removed by another site of a network whose scan
		// missed this one), since the stamp alone would say all is well.
		if ( current_user_can( 'manage_options' ) && tbe_error_files_owner() && tbe_setting( 'error_pages' ) && ( get_option( 'tbe_dropins' ) !== tbe_dropin_stamp() || tbe_error_files_missing() ) ) {
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
	$base  = rtrim( (string) wp_parse_url( content_url( '/' ), PHP_URL_PATH ), '/' ) . '/' . TBE_STATIC_ERRORS . '/';
	$lines = array( '# The Bible Exposed: themed pages for errors the web server answers before WordPress runs' );
	foreach ( tbe_static_error_codes() as $code ) {
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
		'theme'    => __( 'written by another theme: press the button to replace it', 'tbe-child' ),
		'foreign' => __( 'your own file; left alone', 'tbe-child' ),
	);
	echo '<h2>' . esc_html__( 'Errors WordPress answers', 'tbe-child' ) . '</h2><p>' . esc_html__( 'Three pages are written into wp-content so they can show even when WordPress itself is down: maintenance, database error and fatal error. With them come this site’s copies of the static pages for the web server. They are installed on activation and after every update, and removed if another theme is activated. A copy another theme left behind is replaced; a file of your own, with no theme’s mark, is never overwritten.', 'tbe-child' ) . '</p><table class="widefat striped tbe-table-admin"><thead><tr><th>' . esc_html__( 'File', 'tbe-child' ) . '</th><th>' . esc_html__( 'Shown when', 'tbe-child' ) . '</th><th>' . esc_html__( 'Status', 'tbe-child' ) . '</th></tr></thead><tbody>';
	$when = array(
		'maintenance.php' => __( 'WordPress is updating (503)', 'tbe-child' ),
		'db-error.php'    => __( 'the database cannot be reached (503)', 'tbe-child' ),
		'php-error.php'   => __( 'a fatal PHP error stops a page (500)', 'tbe-child' ),
	);
	foreach ( tbe_static_error_codes() as $code ) {
		/* translators: %s: HTTP status code */
		$when[ TBE_STATIC_ERRORS . '/' . $code . '.html' ] = sprintf( __( 'the web server answers %s, through the .htaccess lines below', 'tbe-child' ), $code );
	}
	foreach ( $when as $file => $label ) {
		echo '<tr><td><code>wp-content/' . esc_html( $file ) . '</code></td><td>' . esc_html( $label ) . '</td><td>' . esc_html( $words[ tbe_dropin_state( $file ) ] ) . '</td></tr>';
	}
	echo '</tbody></table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1rem"><input type="hidden" name="action" value="tbe_install_dropins">';
	wp_nonce_field( 'tbe_install_dropins' );
	echo '<button class="button button-primary">' . esc_html__( 'Install or refresh these pages', 'tbe-child' ) . '</button></form>';

	echo '<h2 style="margin-top:2rem">' . esc_html__( 'Errors the web server answers', 'tbe-child' ) . '</h2><p>' . esc_html__( 'Errors such as 400, 401, 403, 404 for files, 500, 502, 503 and 504 are produced by the web server before WordPress starts, so the theme cannot change them on its own. The theme writes a static page for each into wp-content/tbe-errors, with this site’s home address and font path. To use them, paste these lines at the top of your .htaccess file (or set each page under Error Pages in cPanel):', 'tbe-child' ) . '</p>';
	echo '<textarea readonly rows="10" class="large-text code" onclick="this.select()">' . esc_textarea( tbe_htaccess_snippet() ) . '</textarea>';
}
