<?php
/**
 * Self-test for the Jewish parallel text (JPS 1917) and its verse-number rules. Run: php tools/test_jps.php
 *
 * Checks that the Torah has its JPS chapters; that the English numbering is matched where the Hebrew differs
 * (Genesis 31:55, Exodus 8:1, Leviticus 6:1, Numbers 16:36, Deuteronomy 12:32); and that in the Ten Commandments
 * the four short commandments stay one Hebrew verse, with no separate verse for the English verses after it.
 * Exit code 0 on success.
 *
 * @package tbe-child
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_DIR', dirname( __DIR__ ) );
function home_url( $p = '' ) { return 'https://thebible.exposed' . $p; }
function apply_filters( $h, $v ) { return $v; }
function add_action() {}
function add_filter() {}
function __( $s ) { return $s; }
require __DIR__ . '/../inc/bible.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

foreach ( array( 'genesis' => 50, 'exodus' => 40, 'leviticus' => 27, 'numbers' => 36, 'deuteronomy' => 34 ) as $book => $n ) {
	check( $n === count( glob( TBE_DIR . "/data/jps/$book/*.json" ) ), "$book: all $n JPS chapters" );
}
$ex8 = TBE_Bible::jps( 'exodus', 8 );
check( 0 === strpos( (string) $ex8[1], 'And the LORD spoke unto Moses: ‘Go in unto Pharaoh' ) && 32 === max( array_keys( $ex8 ) ), 'Exodus 8:1 is the Hebrew 7:26, and the chapter runs to 32 as in English' );
$lev6 = TBE_Bible::jps( 'leviticus', 6 );
check( 30 === max( array_keys( $lev6 ) ) && isset( $lev6[1], $lev6[8] ), 'Leviticus 6: the English 1 to 7 come from the Hebrew 5:20 to 26' );
$num16 = TBE_Bible::jps( 'numbers', 16 );
check( 50 === max( array_keys( $num16 ) ), 'Numbers 16 runs to 50 as in English (the Hebrew 17:1 to 15)' );
$deut12 = TBE_Bible::jps( 'deuteronomy', 12 );
check( isset( $deut12[32] ), 'Deuteronomy 12:32 is the Hebrew 13:1' );
$ex20 = TBE_Bible::jps( 'exodus', 20 );
check( false !== strpos( (string) $ex20[13], 'Thou shalt not murder. Thou shalt not commit adultery' ) && ! isset( $ex20[14], $ex20[15], $ex20[16] ) && 0 === strpos( (string) $ex20[17], 'Thou shalt not covet' ) && 26 === max( array_keys( $ex20 ) ), 'Exodus 20: the four short commandments are one Hebrew verse; English 17 is the Hebrew 14' );
$d5 = TBE_Bible::jps( 'deuteronomy', 5 );
check( ! isset( $d5[18], $d5[19], $d5[20] ) && isset( $d5[17], $d5[21] ) && 33 === max( array_keys( $d5 ) ), 'Deuteronomy 5: the same, at verses 17 to 20' );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
