<?php
/**
 * Self-test for the error pages (inc/error-pages.php, inc/error-page-html.php). Run: php tools/test_error_pages.php
 *
 * WordPress is stubbed. The test checks that each drop-in has its own status, wording and headers and runs
 * without WordPress; that the installer writes missing and outdated copies of its own, replaces another
 * theme's marked leftover, and leaves a file with no marker alone; that every title reads "<page> | The Bible Exposed" under 60
 * characters; and that errors/ matches the builder. Exit code 0 on success.
 *
 * @package tbe-child
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_VERSION', 'test' );
$t_dir = sys_get_temp_dir() . '/tbe-err-' . getmypid();
@mkdir( $t_dir );
define( 'WP_CONTENT_DIR', $t_dir );
$GLOBALS['t_opt'] = array();
function add_action() {}
function add_filter() {}
function __( $s ) { return $s; }
function tbe_setting() { return 1; }
function get_stylesheet_directory_uri() { return 'https://tbe.test/wp-content/themes/tbe'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function update_option( $k, $v ) { $GLOBALS['t_opt'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['t_opt'][ $k ] ); }
function get_option( $k, $d = false ) { return $GLOBALS['t_opt'][ $k ] ?? $d; }
function wp_delete_file( $p ) { @unlink( $p ); }
require __DIR__ . '/../inc/error-pages.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

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
file_put_contents( "$t_dir/maintenance.php", "<?php\n// Abrahamic maintenance drop-in 2.75.0. Written by the Abrahamic theme; safe to delete.\n" );
$res = tbe_install_dropins();
check( 'written' === $res['php-error.php'] && 'ours' === tbe_dropin_state( 'php-error.php' ), 'a missing file is written and reads as current' );
check( 'kept' === $res['db-error.php'] && "<?php // someone else's\n" === file_get_contents( "$t_dir/db-error.php" ), 'a foreign file is left alone' );
check( 'written' === $res['maintenance.php'] && 'ours' === tbe_dropin_state( 'maintenance.php' ), 'another theme\'s marked leftover is replaced' );
file_put_contents( "$t_dir/php-error.php", str_replace( 'Something went wrong', 'Old words', $src['php-error.php'] ) );
check( 'outdated' === tbe_dropin_state( 'php-error.php' ), 'an older copy of its own reads as outdated' );
check( 'written' === tbe_install_dropins()['php-error.php'] && file_get_contents( "$t_dir/php-error.php" ) === $src['php-error.php'], 'the installer refreshes it' );
check( array() === glob( "$t_dir/*.tmp" ), 'no temporary file is left' );

foreach ( array( '400', '401', '403', '404', '500', '502', '503', '504' ) as $code ) {
	$h = (string) file_get_contents( __DIR__ . "/../errors/$code.html" );
	check( $h === tbe_error_page_html( $code ) . "\n", "errors/$code.html matches the builder" );
}

array_map( 'unlink', glob( "$t_dir/*" ) );
rmdir( $t_dir );
echo $fail ? "$fail failed\n" : "all passed\n";
exit( $fail ? 1 : 0 );
