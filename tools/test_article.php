<?php
/**
 * Self-test for the commentary page (inc/article.php) and the contextual links (inc/contextual-links.php). Run:
 * php tools/test_article.php
 *
 * WordPress is stubbed. The test checks that the contents and the rendered headings get the same ids, repeated
 * headings included; that the mid-article next step goes before the third heading only; that contextual links
 * link each target once, at most once a paragraph, never inside a link, heading, table or scripture, and never
 * the page itself; that the caps hold; and that a settings line is read only for this site or a mapped host.
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
function add_filter() {}
function add_action() {}
function apply_filters( $hook, $value ) {
	return 'tbe_contextual_links_map' === $hook && isset( $GLOBALS['t_map'] ) ? $GLOBALS['t_map'] : $value;
}
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_title( $s ) { return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); } // phpcs:ignore WordPress.WP.AlternativeFunctions
function home_url( $p = '' ) { return 'https://thebible.exposed' . $p; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function tbe_setting( $k ) { return $GLOBALS['t_set'][ $k ] ?? null; }
class WP_Post { public $post_content = ''; public $ID = 1; public $post_type = 'tbe_commentary'; } // phpcs:ignore
require __DIR__ . '/../inc/article.php';
require __DIR__ . '/../inc/contextual-links.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

// Headings and contents.
$post               = new WP_Post();
$post->post_content = '<h2 class="wp-block-heading">The first verse</h2><p>a</p><h2>Notes &amp; <em>variants</em></h2><p>b</p><h2 id="own">Kept</h2><h2>The first verse</h2><h3>Not listed</h3>';
$list               = tbe_article_headings( $post );
check( array( 'the-first-verse', 'notes-variants', 'own', 'the-first-verse-2' ) === array_column( $list, 0 ), 'the contents ids come from the heading text, a set id is kept and a repeat is numbered' );
check( 'Notes & variants' === $list[1][1], 'the contents label is the plain text' );
$html = tbe_article_heading_ids( $post->post_content );
foreach ( $list as $h ) {
	check( false !== strpos( $html, 'id="' . $h[0] . '"' ), "the rendered heading carries id {$h[0]}" );
}
check( 1 === substr_count( $html, 'id="own"' ), 'a heading with an id is left as it is' );

// Mid-article next step.
$aside = '<aside>next</aside>';
check( 1 === preg_match( '#<p>b</p><aside>next</aside><h2 id="own">#', tbe_article_insert_mid( tbe_article_heading_ids( $post->post_content ), $aside ) ), 'the mid next step goes before the third heading' );
check( '<h2>a</h2><h2>b</h2>' === tbe_article_insert_mid( '<h2>a</h2><h2>b</h2>', $aside ), 'and nowhere when there are fewer than three' );

// Contextual links.
$GLOBALS['t_map'] = array(
	'sites' => array( 'boi' => 'https://bestofislam.org', 'injil' => 'https://injil.help' ),
	'links' => array(
		array( 'site' => 'boi', 'path' => '/trinity/', 'phrases' => array( 'Trinity', 'the Trinity' ) ),
		array( 'site' => 'injil', 'path' => '/gospels/', 'phrases' => array( 'gospels' ) ),
		array( 'site' => 'injil', 'path' => '/paul/', 'phrases' => array( 'Paul' ) ),
	),
);
$GLOBALS['t_set'] = array( 'ctx_out' => 1, 'ctx_out_max' => 3, 'ctx_own_max' => 4, 'ctx_extra' => "scribes | /glossary/scribes/\nevil | https://evil.example/x" );
$in  = '<p>The Trinity and the gospels.</p><p>Again the Trinity.</p><h2>Paul</h2><table><tr><td>Paul</td></tr></table><blockquote class="wp-block-quote"><p>Paul</p></blockquote><p><a href="/x">Paul</a> wrote; scribes copied, evil too.</p><p lang="grc">Paul</p>';
$out = tbe_ctx_apply( $in, 'https://thebible.exposed/commentary/n/' );
check( 1 === substr_count( $out, 'bestofislam.org/trinity/' ), 'a target is linked once' );
check( false !== strpos( $out, '<a class="tbe-ctx-link tbe-ctx-link--out" href="https://bestofislam.org/trinity/" rel="noopener">The Trinity</a>' ), 'the longest phrase wins, and an outbound link is marked' );
check( false === strpos( $out, 'injil.help/gospels/' ), 'a paragraph gets one new link at most' );
check( false === strpos( $out, 'injil.help/paul/' ), 'nothing inside headings, tables, quotations, links or Greek' );
check( false !== strpos( $out, '<a class="tbe-ctx-link" href="https://thebible.exposed/glossary/scribes/">scribes</a>' ), 'a settings line with a path links this site' );
check( false === strpos( $out, 'evil.example' ), 'a settings line for an unmapped host is ignored' );
$GLOBALS['t_set']['ctx_out'] = 0;
$cold = tbe_ctx_apply( $in, 'https://thebible.exposed/commentary/n/' );
check( false === strpos( $cold, 'bestofislam.org' ), 'outbound links switch off' );
$self = tbe_ctx_apply( '<p>scribes</p>', 'https://thebible.exposed/glossary/scribes/' );
check( false === strpos( $self, '<a' ), 'the page never links to itself' );

echo $fail ? "$fail failed\n" : "all passed\n";
exit( $fail ? 1 : 0 );
