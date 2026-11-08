<?php
/**
 * Self-test for the commentary page (inc/article.php) and the contextual links (inc/contextual-links.php). Run:
 * php tools/test_article.php
 *
 * WordPress is stubbed. The test checks that the contents and the rendered headings get the same ids, repeated
 * headings included; that the mid-article next step goes before the third heading only; that contextual links
 * link each target once, at most once a paragraph, never inside a link, heading, table or scripture, never a
 * chapter and verse, and never the page itself; that each cap holds; that a ">" inside an attribute does not
 * split a tag; and that a settings line is read only for this site or a mapped host.
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
// Like WordPress: ASCII lower-cased, other letters percent-encoded, everything else a dash.
function sanitize_title( $s ) {
	$out = '';
	foreach ( preg_split( '//u', strtolower( (string) $s ), -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		$out .= preg_match( '/^[a-z0-9]$/', $ch ) ? $ch : ( strlen( $ch ) > 1 ? strtolower( rawurlencode( $ch ) ) : '-' );
	}
	return trim( (string) preg_replace( '/-+/', '-', $out ), '-' );
}
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); } // phpcs:ignore WordPress.WP.AlternativeFunctions
function home_url( $p = '' ) { return 'https://thebible.exposed' . $p; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/' ); }
function tbe_setting( $k ) { return $GLOBALS['t_set'][ $k ] ?? null; }
class WP_Post { public $post_content = ''; public $ID = 1; public $post_type = 'tbe_commentary'; } // phpcs:ignore
require __DIR__ . '/../inc/article.php';
require __DIR__ . '/../inc/toc.php';
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

// An id the author set is reserved, so a later heading with the same text gets another.
$clash = '<h2 id="introduction">Custom</h2><h2>Introduction</h2><h2>Introduction</h2>';
check( array( 'introduction', 'introduction-2', 'introduction-3' ) === array_column( tbe_article_heading_list( $clash ), 0 ), 'an author-set id is never made again' );
check( 1 === substr_count( tbe_article_heading_ids( $clash ), 'id="introduction"' ), 'and the rendered headings agree' );

// A heading in another script keeps its letters in the id, so its contents link matches.
$greek = tbe_article_heading_list( '<h2>Λόγος</h2>' );
check( 'λόγος' === $greek[0][0] || 'Λόγος' === $greek[0][0], 'a Greek heading id keeps its letters (' . $greek[0][0] . ')' );

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
		array( 'site' => 'injil', 'path' => '/john-3-16/', 'phrases' => array( 'John 3:16' ) ),
		array( 'site' => 'boi', 'path' => '/tawhid/', 'phrases' => array( 'tawhid' ) ),
		array( 'site' => 'boi', 'path' => '/salah/', 'phrases' => array( 'salah' ) ),
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
$refs = tbe_ctx_apply( '<p>See John 3:16 today.</p>', 'https://thebible.exposed/commentary/n/' );
check( false === strpos( $refs, '<a' ), 'a chapter and verse is never a contextual link' );
$attr = tbe_ctx_apply( '<p title="a > b tawhid">x</p>', 'https://thebible.exposed/commentary/n/' );
check( '<p title="a > b tawhid">x</p>' === $attr, 'a ">" inside an attribute does not split the tag' );
$GLOBALS['t_set']['ctx_out_max'] = 1;
$capped = tbe_ctx_apply( '<p>tawhid</p><p>salah</p><p>gospels</p>', 'https://thebible.exposed/commentary/n/' );
check( 1 === substr_count( $capped, 'tbe-ctx-link--out' ), 'the cap on outbound links holds' );
$GLOBALS['t_set']['ctx_own_max'] = 0;
$GLOBALS['t_set']['ctx_out_max'] = 3;
$own0 = tbe_ctx_apply( '<p>scribes</p><p>tawhid</p>', 'https://thebible.exposed/commentary/n/' );
check( false === strpos( $own0, 'glossary/scribes' ) && 1 === substr_count( $own0, 'tbe-ctx-link--out' ), 'the cap on links to this site holds on its own' );
$GLOBALS['t_set']['ctx_own_max'] = 4;
$GLOBALS['t_set']['ctx_out'] = 0;
$cold = tbe_ctx_apply( $in, 'https://thebible.exposed/commentary/n/' );
check( false === strpos( $cold, 'bestofislam.org' ), 'outbound links switch off' );
$self = tbe_ctx_apply( '<p>scribes</p>', 'https://thebible.exposed/glossary/scribes/' );
check( false === strpos( $self, '<a' ), 'the page never links to itself' );

echo $fail ? "$fail failed\n" : "all passed\n";
exit( $fail ? 1 : 0 );
