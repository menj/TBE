<?php
/**
 * Self-test for the further books (inc/further.php, data/further/, data/canons.json). Run:
 * php tools/test_further.php
 *
 * WordPress is stubbed. The test checks that 1 to 3 Meqabyan read with the chapter and verse counts of the
 * Wikisource pages; that only books listed in data/canons.json are read; that the book list links them in the
 * Ethiopian section and still marks the rest "Not on the site yet"; that a Ge'ez name shows beside its book;
 * that every chapter renders with its verses and the Wikisource credit and licence; that the pager crosses from
 * one book to the next; and that the routes, the Sources page and the theme's file list name the module.
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
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_kses_post( $s ) { return (string) $s; }
function _n( $one, $many, $n ) { return 1 === (int) $n ? $one : $many; }
function home_url( $p = '' ) { return 'https://thebible.exposed' . $p; }
function tbe_setting( $key ) { return 'verse_numbers' === $key ? true : 'verse'; }
final class TBE_Bible { // phpcs:ignore
	public static function url( ?string $slug = null, ?int $chapter = null ): string {
		return home_url( '/bible/' . ( $slug ? $slug . '/' . ( $chapter ? $chapter . '/' : '' ) : '' ) );
	}
}
require __DIR__ . '/../inc/canon.php';
require __DIR__ . '/../inc/further.php';
require __DIR__ . '/../inc/reader.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

$books = TBE_Further::books();
check( array( '1-meqabyan', '2-meqabyan', '3-meqabyan' ) === array_keys( $books ), 'the three books of Meqabyan are on the site, in canon order' );
check( 7 === $books['1-meqabyan']['count'] && 21 === $books['2-meqabyan']['count'] && 10 === $books['3-meqabyan']['count'], 'chapters: 7, 21 and 10, as on Wikisource' );
$verses = static fn( $slug ) => array_sum( array_map( static fn( $c ) => count( $c['verses'] ), $books[ $slug ]['chapters'] ) );
check( 240 === $verses( '1-meqabyan' ) && 421 === $verses( '2-meqabyan' ) && 208 === $verses( '3-meqabyan' ), 'verses: 240, 421 and 208' );
check( null === TBE_Further::book( 'genesis' ) && null === TBE_Further::book( '../canons' ) && null === TBE_Further::book( '1-enoch' ), 'only listed books with a text file are read' );

$bad = array();
foreach ( $books as $slug => $book ) {
	foreach ( $book['chapters'] as $i => $ch ) {
		if ( $ch['n'] !== $i + 1 ) {
			$bad[] = "$slug chapter $i";
		}
		$last = 0;
		foreach ( $ch['verses'] as $v ) {
			if ( $v['n'] <= $last || '' === trim( $v['t'] ) || preg_match( '/(^|[.!?"”])\s*\[?\d{1,2}\]?\.?\s+[A-Z“"]/', $v['t'] ) ) {
				$bad[] = "$slug {$ch['n']}:{$v['n']}";
			}
			$last = $v['n'];
		}
	}
}
check( ! $bad, 'every verse has text, numbers rise and no verse holds the next one (' . implode( ', ', array_slice( $bad, 0, 5 ) ) . ')' );
$one = TBE_Further::chapter( $books['1-meqabyan'], 1 );
check( str_starts_with( $one['heading'], 'These are the things Maccabeus spoke' ) && 'There was one man whose name was called Tseerutsaydan and he loved sin. He would boast in his horses\' abundance and his troops\' firmness beneath his authority.' === $one['verses'][0]['t'], '1 Meqabyan 1 keeps its heading and its first verse word for word' );
check( 17 === $one['verses'][16]['n'] && str_starts_with( $one['verses'][16]['t'], 'And he would boast' ), 'verses run together on the page are split (1 Meqabyan 1:17)' );

$list = tbe_canon_sections();
check( false !== strpos( $list, '<a href="https://thebible.exposed/bible/1-meqabyan/"><span class="tbe-booklist__name">1 Meqabyan</span><span class="tbe-booklist__count">7 chapters</span></a>' ), 'the book list links 1 Meqabyan with its chapters' );
check( 3 === substr_count( $list, 'meqabyan/"' ) && false !== strpos( $list, 'mätṣḥäfä henok</span></span><span class="tbe-booklist__count">Not on the site yet' ), 'the other prepared books are still marked "Not on the site yet"' );
check( false !== strpos( $list, 'Zëna Ayhud (Pseudo-Josephus) <span class="tbe-booklist__geez" lang="gez">ዜና፡ አይሁድ</span></span>' ), 'Zëna Ayhud carries its Ge\'ez name' );
check( false !== strpos( $list, '1 Enoch (Henok) <span class="tbe-booklist__geez" lang="gez">መጽሐፈ ሄኖክ</span> <span class="tbe-booklist__geez tbe-booklist__geez--latin" lang="gez-Latn">mätṣḥäfä henok</span>' ), '1 Enoch carries its Ge\'ez name and transliteration' );
check( false !== strpos( $list, 'Jubilees (Kufale) <span class="tbe-booklist__geez" lang="gez">መጽሐፈ ኩፋሌ</span> <span class="tbe-booklist__geez tbe-booklist__geez--latin" lang="gez-Latn">mäṣḥafä kufale</span>' ), 'Jubilees carries its Ge\'ez name and transliteration' );

$page = tbe_view_further_book( $books['2-meqabyan'] );
check( false !== strpos( $page, '<h1 class="tbe-title">2 Meqabyan</h1>' ) && false !== strpos( $page, 'https://thebible.exposed/bible/2-meqabyan/21/' ) && false === strpos( $page, 'https://thebible.exposed/bible/2-meqabyan/22/' ) && false !== strpos( $page, 'Ethiopian Orthodox books' ), 'the book page lists its chapters under its section' );
check( false !== strpos( $page, 'https://en.wikisource.org/wiki/Translation:2_Meqabyan' ) && false !== strpos( $page, 'CC BY-SA 4.0' ) && false !== strpos( $page, 'not a translation authorised' ), 'the book page credits Wikisource, gives the licence and says the translation is unofficial' );
foreach ( $books as $slug => $book ) {
	for ( $c = 1; $c <= $book['count']; $c++ ) {
		$html = tbe_view_further_chapter( $book, $c );
		$ch   = TBE_Further::chapter( $book, $c );
		if ( substr_count( $html, 'class="tbe-verse' ) !== count( $ch['verses'] ) || false === strpos( $html, 'CC BY-SA 4.0' ) || false === strpos( $html, 'wikisource.org' ) ) {
			check( false, "chapter page renders: $slug $c" );
		}
	}
}
check( true, 'every chapter renders all its verses with the credit and licence' );
check( array( array( '1-meqabyan', 7 ), array( '2-meqabyan', 2 ) ) === TBE_Further::neighbours( '2-meqabyan', 1 ) && array( array( '3-meqabyan', 9 ), null ) === TBE_Further::neighbours( '3-meqabyan', 10 ), 'the pager crosses from one book to the next and stops at the last' );
check( false !== strpos( tbe_view_further_chapter( $books['2-meqabyan'], 16 ), 'id="v10"' ) && false === strpos( tbe_view_further_chapter( $books['2-meqabyan'], 16 ), 'id="v9"' ), 'a verse Wikisource skips (2 Meqabyan 16:9) is not invented' );

$routes = (string) file_get_contents( TBE_DIR . '/inc/routes.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $routes, 'TBE_Further::book( $slug )' ) && false !== strpos( $routes, "'further-chapter'" ), 'the reader routes answer a further book at /bible/{slug}/' );
$reader = (string) file_get_contents( TBE_DIR . '/inc/reader.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $reader, 'Translation:1_Meqabyan' ), 'the Sources page credits Wikisource' );
$functions = (string) file_get_contents( TBE_DIR . '/functions.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $functions, "'further'" ), 'functions.php loads inc/further.php' );
check( is_readable( TBE_DIR . '/data/further/README.txt' ), 'the data folder says where the text comes from' );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
