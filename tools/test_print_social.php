<?php
/**
 * Self-test for print and "Save as PDF" and for the profile icons (0.10.27). Run: php tools/test_print_social.php
 *
 * Checks that every network offered has its icon and that each icon is a plain path (no script, no event
 * attribute, a viewBox); that the print sheet is loaded last for print only and hides the site's chrome
 * while keeping the licence lines; that the reading toolbars carry the print button and the script opens the notes
 * before printing; and that the Sources page credits Font Awesome for the LinkedIn and Scribd icons and Hartija for
 * the print styles. Exit code 0 on success.
 *
 * @package tbe-child
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_DIR', dirname( __DIR__ ) );
function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function add_action() {}
function add_filter() {}
require __DIR__ . '/../inc/social.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}
$read = static fn( string $f ): string => (string) file_get_contents( TBE_DIR . '/' . $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions

$nets    = tbe_social_networks();
$missing = array_filter( array_keys( $nets ), static fn( $k ) => ! is_readable( TBE_DIR . "/assets/img/social/$k.svg" ) );
check( count( $nets ) >= 45 && ! $missing, 'every network offered has its icon (' . count( $nets ) . ' networks; missing: ' . implode( ', ', $missing ) . ')' );
check( array( 'x', 'facebook', 'instagram', 'youtube', 'tiktok', 'telegram', 'whatsapp', 'bluesky', 'threads', 'mastodon', 'github', 'substack' ) === array_slice( array_keys( $nets ), 0, 12 ), 'the twelve networks of before keep their order' );
$bad = array();
foreach ( glob( TBE_DIR . '/assets/img/social/*.svg' ) as $f ) {
	$svg = (string) file_get_contents( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( preg_match( '/<script|\son\w+\s*=|javascript:|<foreignObject/i', $svg ) || ! preg_match( '/viewBox="0 0 \d+ \d+"/', $svg ) ) {
		$bad[] = basename( $f );
	}
}
check( ! $bad, 'every icon is a plain drawing with a viewBox (' . implode( ', ', $bad ) . ')' );
$icon = tbe_social_svg( 'linkedin' );
check( 0 === strpos( $icon, '<svg class="tbe-social__icon" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false"' ) && false === strpos( $icon, '<title>' ), 'an icon is inlined decorative, its title stripped' );

$enq = $read( 'inc/enqueue.php' );
check( false !== strpos( $enq, "'tbe-print', 'assets/css/print.css', array( 'tbe-mobile-first' )" ) && false !== strpos( $enq, "->args = 'print'" ), 'the print sheet loads last, for print only' );
$css = $read( 'assets/css/print.css' );
check( (bool) preg_match( '/\.tbe-header,\s*\n\.tbe-footer,/', $css ) && false !== strpos( $css, '.tbe-copyright' ) && false !== strpos( $css, 'thead {' ) && is_readable( TBE_DIR . '/assets/css/LICENSE-HARTIJA.txt' ), 'print hides the chrome, keeps the licence lines, repeats table headers, and carries Hartija\'s licence' );
check( false !== strpos( $read( 'inc/reader.php' ), 'data-tbe-print' ) && false !== strpos( $read( 'inc/further.php' ), 'data-tbe-print' ), 'the Bible and further-book toolbars carry the print button' );
$js = $read( 'assets/js/reader.js' );
check( false !== strpos( $js, "removeAttribute( 'name' )" ) && false !== strpos( $js, 's.box.open = s.open;' ), 'a note in a named group is restored to its own state after printing' );
check( false !== strpos( $css, '.tbe-verse:target' ) && false !== strpos( $css, 'background: none !important' ), 'a highlighted verse prints without its wash' );
check( false !== strpos( $js, "'beforeprint'" ) && false !== strpos( $js, "'afterprint'" ) && false !== strpos( $js, 'window.print()' ), 'notes print open and close again afterwards' );
$src = $read( 'inc/reader.php' );
check( false !== strpos( $src, 'Font Awesome' ) && false !== strpos( $src, 'CC BY 4.0' ) && false !== strpos( $src, 'Hartija' ), 'the Sources page credits Font Awesome (LinkedIn, Scribd) and Hartija' );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
