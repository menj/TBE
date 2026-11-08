<?php
/**
 * Self-test for the Strong's lexicon (inc/lexicon.php, data/lexicon/). Run: php tools/test_lexicon.php
 *
 * WordPress is stubbed. The test checks the data (every Strong's number the interlinear uses has an entry; known
 * words read right; references point at real chapters; Metzger's list is matched), that each kind of page renders
 * with its credit, that a definition's references become reader links and nothing else of its markup survives,
 * that numbers are read in every common spelling, and that the routes, the interlinear links and the theme's
 * file list name the lexicon.
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
function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function esc_html__( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_kses_post( $s ) { return (string) $s; }
function wp_kses( $s, $allowed ) { return strip_tags( (string) $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
function home_url( $p = '' ) { return 'https://thebible.exposed' . $p; }
final class TBE_Bible { // phpcs:ignore
	public static function book( string $slug ): ?array {
		static $books = null;
		$books = $books ?? array_column( json_decode( (string) file_get_contents( TBE_DIR . '/data/books.json' ), true ), null, 'slug' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $books[ $slug ] ?? null;
	}
	public static function url( ?string $slug = null, ?int $chapter = null, ?int $verse = null ): string {
		return home_url( '/bible/' . $slug . '/' . $chapter . '/' . ( $verse ? '#v' . $verse : '' ) );
	}
	public static function label( string $slug, int $chapter, int $vstart = 0 ): string {
		return ( self::book( $slug )['name'] ?? $slug ) . ' ' . $chapter . ( $vstart ? ':' . $vstart : '' );
	}
}
final class TBE_Interlinear { // phpcs:ignore
	public static function url( string $slug, int $chapter ): string {
		return home_url( '/bible/' . $slug . '/' . $chapter . '/interlinear/' );
	}
}
require __DIR__ . '/../inc/lexicon.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

check( 'G3056' === TBE_Lexicon::normalise( 'g3056' ) && 'H0430' === TBE_Lexicon::normalise( 'H430' ) && 'G0001' === TBE_Lexicon::normalise( 'G00001' ) && '' === TBE_Lexicon::normalise( 'X12' ), 'Strong’s numbers are read in every common spelling' );
$logos = TBE_Lexicon::entry( 'G3056' );
check( $logos && 'λόγος' === $logos['s'][0]['w'] && $logos['n'] > 300, 'G3056 is λόγος, used over 300 times' );
$elohim = TBE_Lexicon::entry( 'h430' );
check( $elohim && 'אֱלֹהִים' === $elohim['s'][0]['w'] && $elohim['n'] > 2000, 'H0430 is אֱלֹהִים, used over 2,000 times' );
check( TBE_Lexicon::entry( 'H0122' ) !== null, 'Hebrew numbers whose senses STEP splits (H0122a, b) have an entry' );
check( null === TBE_Lexicon::entry( 'G9999' ), 'a number with no entry has none' );

// Every number the interlinear uses has an entry (a sample of chapters, both testaments).
$missing = array();
foreach ( array( 'genesis/1', 'psalms/23', 'isaiah/53', 'matthew/5', 'john/1', 'romans/8', 'revelation/22' ) as $ch ) {
	$data = json_decode( (string) file_get_contents( TBE_DIR . '/data/interlinear/' . $ch . '.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( $data['v'] as $words ) {
		foreach ( $words as $w ) {
			preg_match_all( '/[GH]\d{4}/', (string) $w[3], $m );
			foreach ( $m[0] as $num ) {
				if ( ! TBE_Lexicon::entry( $num ) ) {
					$missing[ $num ] = true;
				}
			}
		}
	}
}
check( ! $missing, 'every Strong’s number in sample chapters has an entry' . ( $missing ? ': ' . implode( ', ', array_slice( array_keys( $missing ), 0, 8 ) ) : '' ) );
$bad = array_filter( $logos['r'], static fn( $r ) => ! TBE_Bible::book( $r[0] ) );
check( ! $bad && 'matthew' === $logos['r'][0][0], 'references point at real books, in canonical order' );

$def = TBE_Lexicon::definition( "<b>a word</b>: [[matthew 8 8|Mat.8:8]] <script>x</script>\nnext" );
check( false !== strpos( $def, '<b>a word</b>' ) && false !== strpos( $def, 'href="https://thebible.exposed/bible/matthew/8/#v8"' ) && false === strpos( $def, '<script>' ) && false !== strpos( $def, '<br>' ), 'definitions keep bold, link references, drop other markup, keep line breaks' );
check( 'noun, masculine' === TBE_Lexicon::part( 'G:N-M' ) && 'verb' === TBE_Lexicon::part( 'H:V' ), 'parts of speech in words' );
check( 'α' === TBE_Lexicon::initial( 'ἀγάπη' ) && 'א' === TBE_Lexicon::initial( 'אֱלֹהִים' ) && 'כ' === TBE_Lexicon::initial( 'ךְ' ), 'first letters fold accents, points and final forms' );
$alpha = TBE_Lexicon::by_letter( 'greek', 'α' );
check( count( $alpha ) > 500 && in_array( 'G0026', array_column( $alpha, 0 ), true ), 'the α list holds ἀγάπη (G0026) among its words' );

$metz = TBE_Lexicon::metzger( 'G3056' );
check( $metz && false !== strpos( (string) $metz['g'], 'word' ) && false !== strpos( (string) $metz['group'], '201 to 500' ), 'Metzger: λόγος is among the words used 201 to 500 times' );
$groups  = TBE_Lexicon::metzger_groups();
$words   = array_merge( ...array_map( static fn( $g ) => $g['words'], $groups ) );
$matched = count( array_filter( $words, static fn( $w ) => '' !== $w['n'] ) );
check( count( $groups ) >= 30 && count( $words ) > 900 && $matched / count( $words ) > 0.95, sprintf( 'Metzger: %d groups, %d words, %d matched', count( $groups ), count( $words ), $matched ) );

$html = tbe_view_lexicon_entry( $logos );
check( false !== strpos( $html, '<h1 class="tbe-title tbe-lex__head" lang="grc">λόγος</h1>' ) && false !== strpos( $html, 'Metzger, Lexical Aids' ) && false !== strpos( $html, '/bible/matthew/5/interlinear/#v32' ) && false !== strpos( $html, 'STEP Bible' ), 'an entry page renders: word, Metzger, places used and credit' );
check( false !== strpos( tbe_view_lexicon_entry( $elohim ), 'dir="rtl"' ), 'a Hebrew entry is right to left' );
check( false !== strpos( tbe_view_lexicon(), '/lexicon/hebrew/aleph/' ) && false !== strpos( tbe_view_lexicon(), '/lexicon/greek/frequency/' ), 'the lexicon home links the alphabets and Metzger’s list' );
check( false !== strpos( tbe_view_lexicon_letter( 'greek', 'alpha' ), '/lexicon/g0026/' ), 'a letter page lists its words' );
check( false !== strpos( tbe_view_lexicon_frequency(), '/lexicon/g3056/' ), 'Metzger’s list links its words' );

$read = static fn( $f ) => (string) file_get_contents( TBE_DIR . '/' . $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $read( 'inc/routes.php' ), "'^lexicon/([gh][0-9]{1,4})/?$'" ) && false !== strpos( $read( 'inc/routes.php' ), "'^lexicon/(greek|hebrew)/([a-z]+)/?$'" ), 'the routes answer the lexicon addresses' );
check( false !== strpos( $read( 'inc/interlinear.php' ), 'tbe_il_strongs_links(' ), 'the interlinear links each Strong’s number' );
check( false !== strpos( $read( 'functions.php' ), "'lexicon'" ), 'functions.php loads inc/lexicon.php' );
check( is_readable( TBE_DIR . '/data/lexicon/README.txt' ), 'the data folder says where the data comes from' );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
