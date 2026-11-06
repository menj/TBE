<?php
/**
 * Self-test for the template links (inc/site-links.php). Run: php tools/test_site_links.php
 *
 * WordPress is stubbed. The test checks that every root link in the block templates and parts is rewritten
 * under the home address of a site at the root and of one in a subfolder, that a rewritten link is not
 * rewritten again, and that other links are left alone. Exit code 0 on success.
 *
 * @package tbe-child
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
function add_filter() {}
function esc_url( $u ) { return $u; }
function home_url( $p = '' ) { return $GLOBALS['t_home'] . $p; }
require __DIR__ . '/../inc/site-links.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

$files = array_merge( glob( __DIR__ . '/../templates/*.html' ), glob( __DIR__ . '/../parts/*.html' ) );
$all   = '';
foreach ( $files as $f ) {
	$all .= (string) file_get_contents( $f );
}
// The header menu's links are navigation-link blocks: their "url" attribute renders as href.
$all = (string) preg_replace( '~"url":"(/[^"]*)"~', 'href="$1"', $all );
preg_match_all( '~href="/[^"]*"~', $all, $m );
check( count( $m[0] ) >= 20, 'the templates and parts carry ' . count( $m[0] ) . ' root links, menu included' );

foreach ( array( 'https://tbe.test', 'https://tbe.test/blog' ) as $home ) {
	$GLOBALS['t_home'] = $home;
	$out = tbe_site_links( $all );
	check( ! preg_match( '~href="/[^/]~', $out ), "no root link is left under $home" );
	check( false !== strpos( $out, 'href="' . $home . '/bible/interlinear/"' ) && false !== strpos( $out, 'href="' . $home . '/sources/"' ), "links point under $home" );
	check( tbe_site_links( $out ) === $out, "a rewritten link is not rewritten again under $home" );
}
$GLOBALS['t_home'] = 'https://tbe.test/blog';
$keep = '<a href="//cdn.test/bible/">x</a><a href="/wp-admin/">y</a><a href="/bibleish/">z</a><a href="#top">t</a>';
check( tbe_site_links( $keep ) === $keep, 'other hosts, other paths and fragments are left alone' );

echo $fail ? "$fail failed\n" : "all passed\n";
exit( $fail ? 1 : 0 );
