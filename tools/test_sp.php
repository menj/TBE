<?php
/**
 * Self-test for the Samaritan Pentateuch comparison (data/sp/, inc/dss.php). Run: php tools/test_sp.php
 *
 * WordPress is stubbed. The test checks that every chapter of Genesis to Deuteronomy has its comparison, that a
 * scroll still wins where one is compared (Isaiah), that known Samaritan readings are found (the longer Genesis
 * 1:14; Deuteronomy 27:4 has Gerizim where the Masoretic text has Ebal), and that the notes speak of the
 * Samaritan text, not a scroll. Exit code 0 on success.
 *
 * @package tbe-child
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_DIR', dirname( __DIR__ ) );
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
require __DIR__ . '/../inc/dss.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

$counts = array( 'genesis' => 50, 'exodus' => 40, 'leviticus' => 27, 'numbers' => 36, 'deuteronomy' => 34 );
$missing = array();
foreach ( $counts as $book => $n ) {
	for ( $c = 1; $c <= $n; $c++ ) {
		if ( ! tbe_dss_is_sp( tbe_dss_chapter( $book, $c ) ) ) {
			$missing[] = "$book $c";
		}
	}
}
check( ! $missing, 'every chapter of the Pentateuch has its Samaritan comparison (' . implode( ', ', array_slice( $missing, 0, 3 ) ) . ')' );
check( 'dss' === ( tbe_dss_chapter( 'isaiah', 1 )['source'] ?? '' ) && null === tbe_dss_chapter( 'joshua', 1 ), 'Isaiah keeps its scroll; books outside both have none' );
$gen = tbe_dss_chapter( 'genesis', 1 );
check( 'Samaritan Pentateuch' === $gen['scroll'] && in_array( 'להאיר על הארצ ולהבדיל', array_column( $gen['v']['14'] ?? array(), 'ds' ), true ), 'Genesis 1:14: the Samaritan text adds "to give light upon the earth"' );
$deut = tbe_dss_chapter( 'deuteronomy', 27 );
check( (bool) array_filter( $deut['v']['4'] ?? array(), static fn( $v ) => false !== strpos( (string) $v['ds'], 'גריזימ' ) || false !== strpos( (string) $v['ds'], 'גרזימ' ) ), 'Deuteronomy 27:4: Gerizim where the Masoretic text has Ebal' );
$words = array( array( 'בְּרֵאשִׁית' ), array( 'בָּרָא' ) );
$show  = tbe_dss_verse( array( array( 'k' => 'm', 't' => 'r', 'mt' => array( 1, 2 ), 'ds' => 'ברא', 'r' => 0, 'u' => 0 ) ), $words, 'Samaritan Pentateuch', true );
check( false !== strpos( $show['notes'], 'The Samaritan text reads' ) && false === strpos( $show['notes'], 'scroll' ) && 0 === strpos( $show['words'][1]['tip'], 'Samaritan Pentateuch: ' ), 'the notes speak of the Samaritan text' );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
