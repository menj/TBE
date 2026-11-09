<?php
/**
 * Self-test for the starter pack sync rules (inc/starter-commentary.php): what a sync does with a bundled
 * entry that changes, and with one the bundle drops. Pure decisions, with WordPress stubbed.
 * Run: php tools/test_pack_sync.php
 *
 * @package tbe-child
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_DIR', dirname( __DIR__ ) );
function add_action() {}
function add_filter() {}
class WP_Post {}
require dirname( __DIR__ ) . '/inc/starter-commentary.php';

$fail  = 0;
$check = static function ( $name, $got, $want ) use ( &$fail ) {
	if ( $got !== $want ) {
		++$fail;
		echo "FAIL $name: got " . var_export( $got, true ) . ', want ' . var_export( $want, true ) . "\n";
	}
};

// tbe_pack_decide( exists, entry hash, stored hash, edited, locked, ack )
$check( 'a new entry is created', tbe_pack_decide( false, 'b', '', false, false, '' ), 'create' );
$check( 'an entry from this version is current', tbe_pack_decide( true, 'b', 'b', true, false, '' ), 'current' );
$check( 'an untouched entry updates', tbe_pack_decide( true, 'b', 'a', false, false, '' ), 'update' );
$check( 'an edited entry is never overwritten, the owner is told', tbe_pack_decide( true, 'b', 'a', true, false, '' ), 'newer' );
$check( 'an edited entry already decided for this version stays quiet', tbe_pack_decide( true, 'b', 'a', true, false, 'b' ), 'kept' );
$check( 'a decision for an older version does not hide a newer one', tbe_pack_decide( true, 'c', 'a', true, false, 'b' ), 'newer' );
$check( 'a locked entry the owner never edited still updates', tbe_pack_decide( true, 'b', 'a', false, true, '' ), 'update' );
$check( 'a locked edited entry stays and is left out of the notice', tbe_pack_decide( true, 'b', 'a', true, true, '' ), 'kept' );

// tbe_pack_decide_removed( edited, locked, ack )
$check( 'an untouched dropped entry goes to the Trash', tbe_pack_decide_removed( false, false, '' ), 'trash' );
$check( 'an edited dropped entry is kept and listed', tbe_pack_decide_removed( true, false, '' ), 'removed' );
$check( 'a locked untouched dropped entry goes to the Trash', tbe_pack_decide_removed( false, true, '' ), 'trash' );
$check( 'a locked edited dropped entry is kept and left out of the notice', tbe_pack_decide_removed( true, true, '' ), 'kept' );
$check( 'a decided dropped entry stays quiet', tbe_pack_decide_removed( true, false, 'removed' ), 'kept' );

$src = (string) file_get_contents( dirname( __DIR__ ) . '/inc/starter-commentary.php' );
$check( 'an entry with no recorded body hash counts as edited', false !== strpos( $src, "'' === \$recorded ||" ), true );
$check( 'the sync deletes nothing for good', false === strpos( $src, 'wp_delete_post' ), true );

echo $fail ? "$fail failed\n" : "test_pack_sync: all passed\n";
exit( $fail ? 1 : 0 );
