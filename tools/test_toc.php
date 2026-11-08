<?php
/**
 * Self-test for the contents (inc/toc.php). Run: php tools/test_toc.php
 *
 * WordPress is stubbed. The test checks that level-two anchors are the same as before level-three headings got
 * anchors of their own; that level-three headings nest under their level-two heading, a skipped level and a
 * deeper first heading nest cleanly, and depth limits the nesting; that no-toc and data-no-toc leave a heading out;
 * that an author's id is kept and reserved; that the shortcode attributes are read as WP TOC reads them; that the
 * box comes inline, sticky or collapsible; that the shortcode leaves a mark the content filter replaces; and that the
 * theme gives way to the WP TOC plugin when it is active.
 * Exit code 0 on success.
 *
 * @package tbe-child
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_DIR', dirname( __DIR__ ) );
$GLOBALS['t_filters'] = array();
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['t_filters'][ $hook ][] = $cb; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['t_filters'][ $hook ][] = $cb; }
function apply_filters( $hook, $value ) { return $value; }
function __( $s ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function sanitize_title( $s ) {
	$out = '';
	foreach ( preg_split( '//u', strtolower( (string) $s ), -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		$out .= preg_match( '/^[a-z0-9]$/', $ch ) ? $ch : ( strlen( $ch ) > 1 ? strtolower( rawurlencode( $ch ) ) : '-' );
	}
	return trim( (string) preg_replace( '/-+/', '-', $out ), '-' );
}
function tbe_setting( $k ) { return $GLOBALS['t_set'][ $k ] ?? null; }
require __DIR__ . '/../inc/article.php';
require __DIR__ . '/../inc/toc.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

// Anchors: level two first, unchanged by level-three headings; level three anchored too.
$html = '<h2>Overview</h2><h3>Overview</h3><h2>Notes</h2><h3>Detail</h3><h3>Detail</h3><h2 id="own">Kept</h2><h4>Deep</h4>';
$list = tbe_toc_heading_list( $html );
check( array( 'overview', 'overview-2', 'notes', 'detail', 'detail-2', 'own', 'deep' ) === array_column( $list, 'id' ), 'level two keeps its ids; level three and four numbered after' );
check( array( 'overview', 'notes', 'own' ) === array_column( tbe_article_heading_list( $html ), 0 ), 'the commentary helper sees the same level-two ids' );
$out = tbe_toc_apply_ids( $html );
check( 1 === substr_count( $out, 'id="own"' ) && false !== strpos( $out, '<h3 id="overview-2">' ) && false !== strpos( $out, '<h4 id="deep">' ), 'the rendered headings carry the ids, an author id once' );
check( $out === tbe_toc_apply_ids( $out ), 'anchoring twice changes nothing' );

// Contents entries and nesting.
$items = tbe_toc_items( $out );
check( array( 2, 3, 2, 3, 3, 2 ) === array_column( $items, 'level' ), 'the contents take level two and three by default' );
$tree = tbe_toc_tree( $items );
check( 3 === count( $tree ) && 1 === count( $tree[0]['children'] ) && 2 === count( $tree[1]['children'] ), 'level three nests under its level two' );
check( 3 === count( tbe_toc_tree( $items, 1 ) ) && ! tbe_toc_tree( $items, 1 )[1]['children'], 'depth 1 keeps the top level only' );
$odd = tbe_toc_tree( tbe_toc_items( '<h3>First</h3><h2>Two</h2><h4>Four</h4>', array( 2, 3, 4 ) ) );
check( 2 === count( $odd ) && 'Four' === $odd[1]['children'][0]['text'], 'a deeper first heading and a skipped level nest cleanly' );

// Leaving headings out.
$skip = tbe_toc_items( '<h2 class="wp-block-heading no-toc">Hidden</h2><h2 data-no-toc>Also hidden</h2><h2 class="not-no-tocx">Shown</h2>' );
check( array( 'Shown' ) === array_column( $skip, 'text' ), 'no-toc and data-no-toc leave a heading out, and only those' );

// Shortcode attributes.
$a = tbe_toc_atts( array( 'tags' => 'h2, H4 ,p', 'title' => '', 'depth' => '2', 'min' => '0', 'collapsible' => 'true', 'collapsed' => 'yes' ) );
check( array( 2, 4 ) === $a['levels'] && 2 === $a['depth'] && 1 === $a['min'] && $a['collapsible'] && $a['collapsed'], 'shortcode attributes are read as WP TOC reads them' );
check( array( 2, 3 ) === tbe_toc_atts( array() )['levels'] && 'Contents' === tbe_toc_atts( array() )['title'], 'defaults: h2,h3 and the title Contents' );

// The box.
$inline = tbe_toc_box( $tree, 'Contents' );
check( false !== strpos( $inline, '<ol><li><a href="#overview">Overview</a><ol><li><a href="#overview-2">' ) && false !== strpos( $inline, 'no-toc' ), 'inline: a nested list, its own title kept out of the contents' );
check( 0 === strpos( tbe_toc_box( $tree, 'Contents', 'sticky' ), '<details class="tbe-contents tbe-contents--sticky" data-tbe-contents>' ), 'sticky: the bar the commentary setting asks for' );
check( false === strpos( tbe_toc_box( $tree, 'x', 'collapsible', true ), ' open' ) && false !== strpos( tbe_toc_box( $tree, 'x', 'collapsible' ), ' open' ), 'collapsible: open unless collapsed' );
check( '' === tbe_toc_box( array(), 'x' ), 'no entries, no box' );

// Shortcodes and the content filter.
foreach ( array_slice( $GLOBALS['t_filters']['init'], -1 ) as $cb ) { // inc/toc.php's own init hook, added last
	if ( ! function_exists( 'add_shortcode' ) ) {
		function add_shortcode( $tag, $cb ) { $GLOBALS['t_short'][ $tag ] = $cb; }
	}
	$cb();
}
check( isset( $GLOBALS['t_short']['wp_toc'], $GLOBALS['t_short']['auto_toc'] ), 'both [wp_toc] and [auto_toc] are registered' );
$mark = $GLOBALS['t_short']['wp_toc']( array( 'title' => 'On this page', 'min' => '2' ) );
check( 1 === preg_match( TBE_TOC_PLACEHOLDER, $mark ), 'the shortcode leaves a mark for the content filter' );

$src = (string) file_get_contents( TBE_DIR . '/inc/toc.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $src, "add_filter( 'wp_toc_enabled'" ) && false !== strpos( $src, "defined( 'WP_TOC_VERSION' )" ), 'with the WP TOC plugin active the theme gives way, and keeps it off commentary entries' );
$fn = (string) file_get_contents( TBE_DIR . '/functions.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $fn, "'toc'" ), 'functions.php loads inc/toc.php' );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
