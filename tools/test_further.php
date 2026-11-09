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

$books  = TBE_Further::books();
$verses = static fn( $slug ) => array_sum( array_map( static fn( $c ) => count( $c['verses'] ), $books[ $slug ]['chapters'] ) );
check( array( 'tobit', 'judith', 'wisdom', 'sirach', 'baruch', '1-maccabees', '2-maccabees', 'esther-additions', 'daniel-additions', '1-esdras', 'prayer-of-manasseh', '1-enoch', 'jubilees', '1-meqabyan', '2-meqabyan', '3-meqabyan', '4-ezra', 'hermas', 'apocalypse-of-peter' ) === array_keys( $books ), 'the KJV Apocrypha, 1 Enoch, Jubilees, 1 to 3 Meqabyan, Ezra Sutu\'el, Hermas and the Apocalypse of Peter are on the site, in canon order' );
check( 51 === $books['sirach']['count'] && 12 === $books['4-ezra']['count'] && '2 Esdras 3' === $books['4-ezra']['chapters'][0]['title'] && 'Susanna' === $books['daniel-additions']['chapters'][1]['title'], 'KJV Apocrypha: Sirach 51 chapters; Ezra Sutu\'el is 2 Esdras 3 to 14; the Additions to Daniel by part' );
check( ! preg_grep( '/\\\\/', array_column( array_merge( ...array_column( $books['sirach']['chapters'], 'verses' ) ), 't' ) ), 'KJV Apocrypha: no USFM marker is left in the text' );
check( 105 === $books['1-enoch']['count'] && $verses( '1-enoch' ) > 1000 && str_starts_with( $books['1-enoch']['chapters'][0]['verses'][0]['t'], 'The word of the blessing of Enoch, how he blessed the elect and the righteous, who were to exist in the time of trouble; rejecting all the wicked and ungodly.' ), '1 Enoch: Laurence\'s 105 chapters, and its first verse as printed' );
check( 'Laurence has no chapter XI.' === $books['1-enoch']['chapters'][10]['heading'] && ! $books['1-enoch']['chapters'][10]['verses'], '1 Enoch: chapter XI, which Laurence does not have, says so' );
check( 50 === $books['jubilees']['count'] && $verses( 'jubilees' ) > 1250, 'Jubilees: 50 chapters and its verses (' . $verses( 'jubilees' ) . ')' );
check( 27 === $books['hermas']['count'] && 'Vision 1' === $books['hermas']['chapters'][0]['title'] && 'Parable 10' === $books['hermas']['chapters'][26]['title'], 'Hermas: five Visions, twelve Mandates and ten Parables' );
$v3 = array_column( $books['hermas']['chapters'][2]['verses'], 'l' );
check( in_array( '6[14]:1', $v3, true ) && 1 === count( array_keys( $v3, '5[13]:1', true ) ), 'Hermas: the repeated reference in Vision 3 is numbered on (6[14])' );
check( 19 === $books['apocalypse-of-peter']['count'] && 'Ethiopic, chapter 1' === $books['apocalypse-of-peter']['chapters'][0]['title'] && 33 === end( $books['apocalypse-of-peter']['chapters'][17]['verses'] )['n'], 'Apocalypse of Peter: the seventeen Ethiopic chapters, then the Akhmim fragment to verse 33 and the quoted passages' );
check( ! preg_grep( '/^\\d+$|\\b(Ps|Matt) \\d+:/', array_column( array_merge( ...array_column( array_slice( $books['apocalypse-of-peter']['chapters'], 0, 17 ), 'verses' ) ), 't' ) ), 'Apocalypse of Peter: no footnote of the printed page is left in the text' );
check( 7 === $books['1-meqabyan']['count'] && 21 === $books['2-meqabyan']['count'] && 10 === $books['3-meqabyan']['count'], 'chapters: 7, 21 and 10, as on Wikisource' );
check( 240 === $verses( '1-meqabyan' ) && 421 === $verses( '2-meqabyan' ) && 208 === $verses( '3-meqabyan' ), 'verses: 240, 421 and 208' );
check( null === TBE_Further::book( 'genesis' ) && null === TBE_Further::book( '../canons' ) && null === TBE_Further::book( 'sinodos' ), 'only listed books with a text file are read' );

$bad = array();
foreach ( array( '1-meqabyan', '2-meqabyan', '3-meqabyan' ) as $slug ) {
	$book = $books[ $slug ];
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
check( 3 === substr_count( $list, 'meqabyan/"' ) && false !== strpos( $list, 'bible/jubilees/"' ) && false !== strpos( $list, 'bible/hermas/"' ) && false !== strpos( $list, 'Jubilees (Kufale) <span class="tbe-booklist__geez" lang="gez">መጽሐፈ ኩፋሌ</span> <span class="tbe-booklist__geez tbe-booklist__geez--latin" lang="gez-Latn">mäṣḥafä kufale</span></span><span class="tbe-booklist__count">50 chapters' ), 'the other prepared books are still marked "Not on the site yet"' );
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
		if ( substr_count( $html, 'class="tbe-verse' ) !== count( $ch['verses'] ) || false === stripos( $html, esc_html( (string) $book['source']['licence'] ) ) || false === strpos( $html, (string) $book['source']['url'] ) ) {
			check( false, "chapter page renders: $slug $c" );
		}
	}
}
check( true, 'every chapter renders all its verses with the credit and licence' );
check( false !== strpos( tbe_view_further_chapter( $books['hermas'], 3 ), '>6[14]:1</a>' ) && false !== strpos( tbe_view_further_chapter( $books['hermas'], 3 ), '<p class="tbe-eyebrow">Vision 3</p>' ), 'Hermas shows its printed references and part titles' );
check( 'Shepherd of Hermas, Mandate 4' === TBE_Further::label( 'hermas', 9 ), 'a titled chapter is labelled by its title' );
check( false !== strpos( tbe_further_credit( $books['jubilees'] ), 'public domain' ) && false !== strpos( tbe_further_credit( $books['jubilees'] ), 'not a translation authorised' ) && false === strpos( tbe_further_credit( $books['hermas'] ), 'Tewahedo' ), 'credits: Jubilees public domain and unofficial; Hermas makes no claim about the Ethiopian church' );
check( array( array( '1-meqabyan', 7 ), array( '2-meqabyan', 2 ) ) === TBE_Further::neighbours( '2-meqabyan', 1 ) && array( array( '3-meqabyan', 9 ), array( '4-ezra', 1 ) ) === TBE_Further::neighbours( '3-meqabyan', 10 ) && array( array( '4-ezra', 11 ), null ) === TBE_Further::neighbours( '4-ezra', 12 ) && array( array( '3-meqabyan', 10 ), null ) !== TBE_Further::neighbours( 'hermas', 1 ) && null === TBE_Further::neighbours( 'hermas', 1 )[0], 'the pager crosses from one book to the next of its section and stops at the last' );
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
