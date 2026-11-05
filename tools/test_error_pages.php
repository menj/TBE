<?php
/**
 * Self-test for the error pages (inc/error-pages.php, inc/error-page-html.php). Run: php tools/test_error_pages.php
 *
 * WordPress is stubbed. The test checks that each drop-in has its own status, wording and headers and runs
 * without WordPress; that the installer writes missing and outdated copies of its own, replaces another
 * theme's marked leftover, and leaves a file with no marker alone; that every title reads "<page> | The Bible Exposed" under 60
 * characters; that errors/ matches the builder and names its font relative to errors/; that no code names the
 * theme folder; and that the copies in wp-content/tbe-errors/ follow the site's home address and the real theme
 * folder (TBE, as on the live site) and are removed on a switch. Exit code 0 on success.
 *
 * @package tbe-child
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_VERSION', 'test' );
// The live folder name: upper case, as on thebible.exposed.
define( 'TBE_DIR', '/srv/site/wp-content/themes/TBE' );
$t_dir = sys_get_temp_dir() . '/tbe-err-' . getmypid();
@mkdir( $t_dir );
define( 'WP_CONTENT_DIR', $t_dir );
$GLOBALS['t_opt'] = array();
function add_action() {}
function add_filter() {}
function __( $s ) { return $s; }
function tbe_setting() { return 1; }
function get_stylesheet_directory_uri() { return empty( $GLOBALS['t_cdn'] ) ? $GLOBALS['t_root'] . '/wp-content/themes/TBE' : 'https://cdn.tbe.test/wp-content/themes/TBE'; }
function home_url( $p = '' ) { return $GLOBALS['t_root'] . $p; }
function content_url( $p = '' ) { return $GLOBALS['t_root'] . '/wp-content' . $p; }
function is_multisite() { return $GLOBALS['t_multi'] ?? false; }
function is_main_site() { return $GLOBALS['t_main'] ?? true; }
// A network: site ID => stylesheet. Site 1 is the main site; t_blog is the site the request runs on.
$GLOBALS['t_sites'] = array();
$GLOBALS['t_blog']  = 1;
function get_main_site_id() { return 1; }
function get_current_blog_id() { return $GLOBALS['t_blog']; }
function get_blog_option( $id, $k ) { return $GLOBALS['t_sites'][ $id ] ?? 'twentytwentyfive'; }
function get_sites( $a ) {
	$ids = array_keys( $GLOBALS['t_sites'] );
	sort( $ids );
	$ids = array_values( array_diff( $ids, $a['site__not_in'] ?? array() ) );
	return array_slice( $ids, $a['offset'], $a['number'] );
}
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0700, true ); }
$GLOBALS['t_root'] = 'https://tbe.test';
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function update_option( $k, $v ) { $GLOBALS['t_opt'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['t_opt'][ $k ] ); }
function get_option( $k, $d = false ) { return $GLOBALS['t_opt'][ $k ] ?? $d; }
function wp_delete_file( $p ) { @unlink( $p ); }
require __DIR__ . '/../inc/error-pages.php';

// The font's version, from the theme's own Angel.woff2.
$t_fv = substr( md5_file( __DIR__ . '/../assets/fonts/Angel.woff2' ), 0, 12 );

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

check( 12 === strlen( $t_fv ) && $t_fv === tbe_error_font_version( __DIR__ . '/../assets/fonts/Angel.woff2' ) && '' === tbe_error_font_version( __DIR__ . '/missing.woff2' ), 'the font version is the first 12 characters of its MD5, empty for a missing file' );

$src = array();
foreach ( array_keys( tbe_dropin_specs() ) as $file ) {
	$src[ $file ] = tbe_dropin_source( $file );
	check( false !== strpos( $src[ $file ], 'X-Robots-Tag: noindex' ) && false !== strpos( $src[ $file ], 'no-store' ) && false !== strpos( $src[ $file ], '<meta name="robots" content="noindex">' ), "$file sends noindex and no-store" );
	preg_match( '~<title>([^<]*)</title>~', $src[ $file ], $m );
	check( isset( $m[1] ) && mb_strlen( html_entity_decode( $m[1] ) ) < 60 && str_ends_with( $m[1], ' | The Bible Exposed' ), "$file title follows the rule" );
	$run = "$t_dir/run-$file";
	file_put_contents( $run, $src[ $file ] );
	$out = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -d display_errors=1 -d error_reporting=-1 ' . escapeshellarg( $run ) . ' 2>&1' );
	check( 0 === strpos( ltrim( $out ), '<!DOCTYPE html>' ) && ! preg_match( '/(Warning|Notice|Fatal error|Deprecated):/', $out ), "$file runs without WordPress" );
	unlink( $run );
}
check( false !== strpos( $src['maintenance.php'], "'HTTP/1.1 503 ' . 'Service Unavailable', true, 503" ) && false !== strpos( $src['maintenance.php'], 'Retry-After' ), 'maintenance.php sends 503 and Retry-After' );
check( false !== strpos( $src['db-error.php'], "'HTTP/1.1 503 ' . 'Service Unavailable', true, 503" ) && false !== strpos( $src['db-error.php'], 'We cannot reach our database' ), 'db-error.php sends 503 and names the database' );
check( false !== strpos( $src['php-error.php'], "'HTTP/1.1 500 ' . 'Internal Server Error', true, 500" ) && false === strpos( $src['php-error.php'], 'Retry-After' ) && false !== strpos( $src['php-error.php'], 'Something went wrong' ), 'php-error.php sends 500, "Something went wrong"' );

// Every variant's title tag keeps the rule, in PHP and in the script's fallback.
foreach ( array( '400', '401', '403', '404', '500', '502', '503', '504', 'maintenance', 'db-error', 'php-error' ) as $key ) {
	foreach ( tbe_error_page( $key )['variants'] as $v ) {
		$tag = tbe_error_title_tag( $key, $v['title'] );
		check( mb_strlen( $tag ) < 60, "$key: \"$tag\" is under 60" );
	}
}

check( 'missing' === tbe_dropin_state( 'php-error.php' ), 'missing reads as missing' );
file_put_contents( "$t_dir/db-error.php", "<?php // someone else's\n" );
file_put_contents( "$t_dir/maintenance.php", "<?php\n// Other maintenance drop-in 2.75.0. Written by the Other theme; safe to delete.\n" );
$res = tbe_install_dropins();
check( 'written' === $res['php-error.php'] && 'ours' === tbe_dropin_state( 'php-error.php' ), 'a missing file is written and reads as current' );
check( 'kept' === $res['db-error.php'] && "<?php // someone else's\n" === file_get_contents( "$t_dir/db-error.php" ), 'a foreign file is left alone' );
check( 'written' === $res['maintenance.php'] && 'ours' === tbe_dropin_state( 'maintenance.php' ), 'another theme\'s marked leftover is replaced' );
file_put_contents( "$t_dir/php-error.php", str_replace( 'Something went wrong', 'Old words', $src['php-error.php'] ) );
check( 'outdated' === tbe_dropin_state( 'php-error.php' ), 'an older copy of its own reads as outdated' );
check( 'written' === tbe_install_dropins()['php-error.php'] && file_get_contents( "$t_dir/php-error.php" ) === $src['php-error.php'], 'the installer refreshes it' );
check( array() === glob( "$t_dir/*.tmp" ), 'no temporary file is left' );
check( 'ours' === tbe_dropin_state( 'tbe-errors/404.html' ) && file_get_contents( "$t_dir/tbe-errors/404.html" ) === '<!-- TBE error page ' . TBE_DROPIN_VERSION . ". Written by the TBE theme; safe to delete. -->\n" . tbe_error_page_html( '404', '/wp-content/themes/TBE/assets/fonts/Angel.woff2?ver=' . $t_fv ) . "\n", 'the static copies are written, with the real theme folder in the font path' );
check( 'TBE' === tbe_theme_folder(), 'the folder name is read from the stylesheet directory, upper case kept' );
check( false !== strpos( tbe_htaccess_snippet(), 'ErrorDocument 404 /wp-content/tbe-errors/404.html' ), '.htaccess lines point at the site copies' );
$GLOBALS['t_root'] = 'https://tbe.test/blog';
check( 'outdated' === tbe_dropin_state( 'tbe-errors/404.html' ), 'a subfolder install makes the copies outdated' );
tbe_install_dropins();
$h = (string) file_get_contents( "$t_dir/tbe-errors/404.html" );
check( false !== strpos( $h, 'href="/blog/"' ) && false !== strpos( $h, 'href="/blog/bible/"' ) && false !== strpos( $h, 'url("/blog/wp-content/themes/TBE/assets/fonts/Angel.woff2?ver=' . $t_fv . '")' ) && false === strpos( $h, 'href="/bible/"' ), 'a subfolder copy has its own home links and font path' );
check( false !== strpos( (string) file_get_contents( "$t_dir/php-error.php" ), 'href="/blog/"' ), 'the drop-ins link to the subfolder home too' );
check( false !== strpos( tbe_htaccess_snippet(), 'ErrorDocument 404 /blog/wp-content/tbe-errors/404.html' ), '.htaccess lines follow wp-content' );
$GLOBALS['t_multi'] = true;
$GLOBALS['t_main']  = false;
$GLOBALS['t_root']  = 'https://tbe.test/other';
$GLOBALS['t_sites'] = array( 1 => 'TBE', 2 => 'TBE', 3 => 'TBE' );
$GLOBALS['t_blog']  = 2;
check( array() === tbe_install_dropins() && false !== strpos( (string) file_get_contents( "$t_dir/tbe-errors/404.html" ), 'href="/blog/"' ), 'a network subsite writes nothing over the main site\'s files' );
// The subsite switches away while the main site and site 3 still run the theme: the shared files stay.
$GLOBALS['t_sites'][2] = 'twentytwentyfive';
tbe_remove_dropins();
check( file_exists( "$t_dir/tbe-errors/404.html" ), 'a subsite switching away removes nothing while other sites run the theme' );
// The main site switches away while subsites still run the theme: the shared files stay too.
$GLOBALS['t_main']     = true;
$GLOBALS['t_blog']     = 1;
$GLOBALS['t_sites'][1] = 'twentytwentyfive';
tbe_remove_dropins();
check( file_exists( "$t_dir/tbe-errors/404.html" ), 'the main site switching away keeps the files while a subsite runs the theme' );
// With the main site on another theme, one writer only: the lowest-ID subsite that runs this one.
$GLOBALS['t_main']  = false;
$GLOBALS['t_sites'] = array( 1 => 'twentytwentyfive', 2 => 'twentytwentyfive', 4 => 'TBE', 7 => 'TBE' );
$GLOBALS['t_blog']  = 7;
check( ! tbe_error_files_owner() && array() === tbe_install_dropins(), 'a higher-ID subsite does not write while a lower one runs the theme' );
$GLOBALS['t_blog'] = 4;
check( tbe_error_files_owner(), 'the lowest-ID subsite that runs the theme writes when the main site runs another' );
check( 'written' === ( tbe_install_dropins()['tbe-errors/404.html'] ?? '' ) && false !== strpos( (string) file_get_contents( "$t_dir/tbe-errors/404.html" ), 'href="/other/"' ), 'that subsite writes the files with its own home address' );
// A large network is paged: the only site running the theme sits past the first 500.
$GLOBALS['t_sites'] = array_fill_keys( range( 1, 1203 ), 'twentytwentyfive' );
$GLOBALS['t_sites'][1201] = 'TBE';
$GLOBALS['t_blog']  = 1201;
check( 1201 === tbe_error_files_first_theme_site() && tbe_error_files_owner(), 'the site scan pages through every site' );
$GLOBALS['t_blog'] = 2;
check( tbe_error_files_in_use_elsewhere(), 'the in-use scan finds a site past the first 500' );
$GLOBALS['t_sites'][1201] = 'twentytwentyfive';
check( ! tbe_error_files_in_use_elsewhere(), 'no other site runs the theme: not in use' );
// The last site to switch away, a subsite, removes the files; then the main site's copies are written back for the rest of the test.
$GLOBALS['t_blog'] = 4;
tbe_remove_dropins();
check( ! file_exists( "$t_dir/tbe-errors/404.html" ), 'the last site to switch away removes the files, even a subsite' );
check( tbe_error_files_missing(), 'missing files are noticed, so the next admin page writes them back' );
$GLOBALS['t_multi'] = false;
$GLOBALS['t_root']  = 'https://tbe.test/blog';
tbe_install_dropins();
$GLOBALS['t_sites'] = array();
$GLOBALS['t_blog']  = 1;
$GLOBALS['t_root']  = 'https://tbe.test/blog';
file_put_contents( "$t_dir/tbe-errors/500.html", "<p>own</p>\n" );
tbe_remove_dropins();
check( ! file_exists( "$t_dir/tbe-errors/404.html" ) && file_exists( "$t_dir/tbe-errors/500.html" ) && file_exists( "$t_dir/db-error.php" ), 'switching away removes only the theme\'s own files' );
unlink( "$t_dir/tbe-errors/500.html" );
rmdir( "$t_dir/tbe-errors" );
$GLOBALS['t_cdn'] = true;
check( 'https://cdn.tbe.test/wp-content/themes/TBE/assets/fonts/Angel.woff2?ver=' . $t_fv === tbe_error_font_url(), 'a theme served from another host gets the full font address' );
unset( $GLOBALS['t_cdn'] );

foreach ( array( '400', '401', '403', '404', '500', '502', '503', '504' ) as $code ) {
	$h = (string) file_get_contents( __DIR__ . "/../errors/$code.html" );
	check( $h === tbe_error_page_html( $code ) . "\n", "errors/$code.html matches the builder" );
	check( false !== strpos( $h, 'url("../assets/fonts/Angel.woff2?ver=' . $t_fv . '")' ) && ! preg_match( '~themes/(tbe|TBE)/~i', $h ), "errors/$code.html names the font relative to errors/, with no theme folder" );
}

// No file in the theme names the theme folder: it is read at run time. The tests' stub addresses are left out.
$hits = array();
$top  = dirname( __DIR__ );
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $top, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	$rel = substr( (string) $f, strlen( $top ) + 1 );
	if ( preg_match( '~^(\.git/|assets/(audio|fonts|img)/|data/)~', $rel ) || ! preg_match( '~\.(php|html|js|css|json|htaccess)$|^\.htaccess$~', $rel ) || 0 === strpos( $rel, 'tools/test_' ) ) {
		continue;
	}
	if ( preg_match( '~themes/tbe/~i', (string) file_get_contents( (string) $f ) ) ) {
		$hits[] = $rel;
	}
}
check( array() === $hits, 'no code names the theme folder (' . implode( ', ', $hits ) . ')' );

array_map( 'unlink', glob( "$t_dir/*" ) );
rmdir( $t_dir );
echo $fail ? "$fail failed\n" : "all passed\n";
exit( $fail ? 1 : 0 );
