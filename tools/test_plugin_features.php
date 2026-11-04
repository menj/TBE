<?php
/**
 * Self-test for the unlisted posts (inc/unlist.php) and the search addresses
 * (inc/search-permalinks.php). Run: php tools/test_plugin_features.php
 *
 * WordPress is stubbed. The test checks that the stored list is cleaned, that the WHERE clause
 * leaves the unlisted IDs out of a listing and keeps them in a singular query and an admin
 * screen, that an admin AJAX call from another host still hides them, that the previous and
 * next links skip them, and that
 * the search redirect builds /search/term/ and /search/term/page/2/ and refuses an empty term.
 * Exit code 0 on success.
 *
 * @package tbe-child
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['ih_options'] = array();
$GLOBALS['ih_admin']   = false;
$GLOBALS['ih_ajax']    = false;
$GLOBALS['ih_referer'] = '';
$GLOBALS['ih_hooks']   = array();
$GLOBALS['ih_meta']    = array(); // post ID => '1'
class TBE_Test_DB {
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_col( $sql ) { return array_map( 'strval', array_keys( $GLOBALS['ih_meta'] ) ); }
}
$GLOBALS['wpdb'] = new TBE_Test_DB();
function update_post_meta( $id, $key, $value ) { $GLOBALS['ih_meta'][ (int) $id ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['ih_meta'][ (int) $id ] ); return true; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }


function add_action( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['ih_hooks'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['ih_hooks'][ $hook ][] = $cb; }
function add_shortcode( $tag, $cb ) {}
function apply_filters( $hook, $value ) { return $value; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['ih_options'] ) ? $GLOBALS['ih_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['ih_options'][ $key ] = $value; return true; }
function is_admin() { return $GLOBALS['ih_admin']; }
function wp_doing_ajax() { return $GLOBALS['ih_ajax']; }
function current_user_can( $cap ) { return true; }
function wp_get_referer() { return $GLOBALS['ih_referer']; }
function admin_url( $path = '' ) { return 'https://thebible.exposed/wp-admin/' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function get_permalink( $id ) { return 'https://thebible.exposed/page-' . (int) $id . '/'; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }

require dirname( __DIR__ ) . '/inc/unlist.php';
require dirname( __DIR__ ) . '/inc/search-permalinks.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

// The plugin's list is copied in once; a malformed list is emptied.
$GLOBALS['ih_options']['unlist_posts'] = array( '12', 7, 7, 0, -3, 'x' );
check( array( 7, 12 ) === tbe_unlisted_ids(), 'the plugin list is copied and cleaned' );
check( array( 7, 12 ) === $GLOBALS['ih_options']['tbe_unlisted'], 'the copy is stored under the theme option' );
check( array() === tbe_unlist_normalise( '' ), 'an empty-string list counts as empty' );

tbe_set_unlisted( 30, true );
tbe_set_unlisted( 7, false );
tbe_set_unlisted( 30, true );
check( array( 12, 30 ) === tbe_unlisted_ids(), 'set_unlisted adds once and removes' );
check( array( 12, 30 ) === $GLOBALS['ih_options']['unlist_posts'], 'the plugin option mirrors the theme list' );
check( array( 12 => '1', 30 => '1' ) === $GLOBALS['ih_meta'], 'each post keeps its state in post meta' );

// Two saves that each read the list before the other wrote: the meta keeps both.
$GLOBALS['ih_options']['tbe_unlisted'] = array( 12 ); // a stale list, as a racing request would leave it
tbe_set_unlisted( 44, true );
check( array( 12, 30, 44 ) === tbe_unlisted_ids(), 'a save rebuilds the list from the meta, so no edit is lost' );
tbe_set_unlisted( 44, false );

// The plugin is switched on again, changes its list, and is switched off: the theme takes the change.
$GLOBALS['ih_options']['unlist_posts'] = array( 30, 55 );
check( array( 30, 55 ) === tbe_unlisted_ids(), 'a later change by the plugin is taken over' );
check( array( 30 => '1', 55 => '1' ) === $GLOBALS['ih_meta'], 'the meta follows the plugin change' );
check( array( 30, 55 ) === tbe_unlisted_live_ids(), 'the live list is the theme list while the plugin is off' );
tbe_set_unlisted( 12, true );
tbe_set_unlisted( 55, false );
check( tbe_is_unlisted( 30 ) && ! tbe_is_unlisted( 7 ), 'is_unlisted reads the list' );

$list   = (object) array( 'is_singular' => false );
$single = (object) array( 'is_singular' => true );
$where  = tbe_unlist_where( ' AND 1=1', $list );
check( ' AND 1=1 AND wp_posts.ID NOT IN ( 12, 30 ) ' === $where, 'a listing (home, archive, feed, search, REST, sitemap) leaves them out' );
check( ' AND 1=1' === tbe_unlist_where( ' AND 1=1', $single ), 'a singular query keeps them, so the address still opens' );

$GLOBALS['ih_admin'] = true;
check( ' AND 1=1' === tbe_unlist_where( ' AND 1=1', $list ), 'an admin screen keeps them' );
$GLOBALS['ih_ajax']    = true;
$GLOBALS['ih_referer'] = 'https://elsewhere.example/wp-admin/edit.php';
check( false !== strpos( tbe_unlist_where( '', $list ), 'NOT IN' ), 'admin AJAX from another host hides them' );
$GLOBALS['ih_referer'] = 'https://thebible.exposed/wp-admin-foo/';
check( false !== strpos( tbe_unlist_where( '', $list ), 'NOT IN' ), 'a look-alike admin path hides them' );
$GLOBALS['ih_referer'] = 'https://THEBIBLE.exposed/wp-admin/edit.php';
check( '' === tbe_unlist_where( '', $list ), 'admin AJAX from this admin keeps them' );
$GLOBALS['ih_admin'] = false;
$GLOBALS['ih_ajax']  = false;

check( " AND p.post_type = 'post' AND p.ID NOT IN ( 12, 30 ) " === tbe_unlist_adjacent( " AND p.post_type = 'post'" ), 'previous and next links skip them' );

class TBE_Fake_Query {
	public $vars;
	public $single;
	public function __construct( $vars, $single ) { $this->vars = $vars; $this->single = $single; }
	public function is_singular() { return $this->single; }
	public function get( $k ) { return $this->vars[ $k ] ?? ''; }
	public function set( $k, $v ) { $this->vars[ $k ] = $v; }
}
$pre = null;
foreach ( $GLOBALS['ih_hooks']['pre_get_posts'] ?? array() as $cb ) { $pre = $cb; }
$q = new TBE_Fake_Query( array( 'post_type' => 'tbe_commentary', 'post__not_in' => array( 5 ) ), false );
$pre( $q );
check( array( 5, 12, 30 ) === $q->vars['post__not_in'], 'get_posts() listings (the commentary index) get post__not_in' );
$q = new TBE_Fake_Query( array( 'name' => 'a-note' ), false );
$pre( $q );
check( ! isset( $q->vars['post__not_in'] ), 'a query for one post by name is left alone' );

// Search addresses.
$home = 'https://thebible.exposed/';
check( 'https://thebible.exposed/search/son%20of%20man/' === tbe_search_pretty_url( $home, 'search', 'son of man' ), '/?s=term goes to /search/term/' );
check( 'https://thebible.exposed/search/mercy/page/2/' === tbe_search_pretty_url( $home, 'search', 'mercy', 2 ), 'pagination keeps /page/2/' );
check( 'https://thebible.exposed/find/a/b/' === tbe_search_pretty_url( $home, 'find', 'a/b' ), 'a custom base, and a slash kept as core keeps it' );
check( '' === tbe_search_pretty_url( $home, 'search', '   ' ), 'an empty search is not redirected' );
check( 'https://thebible.exposed/search/x?post_type=page' === tbe_search_pretty_url( $home, 'search', 'x', 1, array( 'post_type' => 'page' ), false ), 'other arguments are carried over' );
check( array( 'post_type' => array( 'post', 'page' ), 'lang' => 'en' ) === tbe_search_clean_args( array( 'post_type' => array( 'post', '<b>page</b>' ), 'lang' => 'en' ) ), 'array arguments such as post_type[] are kept' );
check( 'https://thebible.exposed/search/x/?post_type%5B0%5D=post&post_type%5B1%5D=page' === tbe_search_pretty_url( $home, 'search', 'x', 1, array( 'post_type' => array( 'post', 'page' ) ) ), 'the redirect carries them' );
check( 'search' === tbe_search_base(), 'the default base is search' );
$GLOBALS['ih_options']['tbe_search_base'] = 'Find Me!';
check( 'findme' === tbe_search_base(), 'the base is sanitised' );
check( isset( $GLOBALS['ih_hooks']['template_redirect'] ), 'the redirect is hooked' );

echo $fail ? "\n{$fail} failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
