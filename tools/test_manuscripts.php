<?php
/**
 * Self-test for the manuscripts section (inc/manuscripts.php, data/manuscripts/passages.json). Run:
 * php tools/test_manuscripts.php
 *
 * WordPress is stubbed. The test checks the data file against what is known of the famous passages (Sinaiticus
 * and Vaticanus end Mark at 16:8; Bezae alone has John 7:53 to 8:11; a page that is lost counts neither way),
 * that every page renders with its title, table, texts and the CNTR credit, that the chapter note appears only on
 * chapters with a passage, and that the routes, the Sources page and the theme's file list name the section.
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
function home_url( $p = '' ) { return 'https://thebible.exposed' . $p; }
final class TBE_Bible { // phpcs:ignore
	public static function book( string $slug ): ?array {
		$names = array( 'matthew' => 'Matthew', 'mark' => 'Mark', 'luke' => 'Luke', 'john' => 'John', 'acts' => 'Acts', 'romans' => 'Romans', '1-john' => '1 John' );
		return isset( $names[ $slug ] ) ? array( 'slug' => $slug, 'name' => $names[ $slug ] ) : null;
	}
	public static function url( ?string $slug = null, ?int $chapter = null, ?int $verse = null ): string {
		return home_url( '/bible/' . ( $slug ? $slug . '/' . ( $chapter ? $chapter . '/' . ( $verse ? '#v' . $verse : '' ) : '' ) : '' ) );
	}
	public static function label( string $slug, int $chapter, int $vstart = 0, int $vend = 0 ): string {
		$b = self::book( $slug );
		return ( $b ? $b['name'] : $slug ) . ' ' . $chapter . ( $vstart ? ':' . $vstart . ( $vend > $vstart ? '–' . $vend : '' ) : '' );
	}
}
require __DIR__ . '/../inc/manuscripts.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

$all = TBE_Manuscripts::passages();
check( count( $all ) >= 20, 'the data file holds the passages (' . count( $all ) . ')' );
$state = static fn( $id, $sig ) => $all[ $id ]['mss'][ $sig ]['s'] ?? '';
check( 'a' === $state( 'mark-16-9-20', '01' ) && 'a' === $state( 'mark-16-9-20', '03' ), 'Sinaiticus and Vaticanus do not have Mark 16:9-20' );
check( 'p' === $state( 'mark-16-9-20', '02' ) && 'p' === $state( 'mark-16-9-20', '05' ), 'Alexandrinus and Bezae have Mark 16:9-20' );
check( 'p' === $state( 'john-7-53-8-11', '05' ) && 'a' === $state( 'john-7-53-8-11', 'P66' ), 'Bezae has John 7:53-8:11 and P66 does not' );
check( 'l' === $state( 'john-7-53-8-11', 'P45' ), 'a page that is lost counts neither way (P45 at John 7:53)' );
check( 'a' === $state( '1-john-5-7-8', '03' ) && 'p' === ( $all['1-john-5-7-8']['eds']['KJTR'] ?? '' ) && 'a' === ( $all['1-john-5-7-8']['eds']['RP'] ?? '' ), 'the three witnesses: Vaticanus and the Byzantine text lack them, the Textus Receptus has them' );
check( 'p' === $state( 'luke-23-34', '01' ) && 'a' === $state( 'luke-23-34', '03' ), 'Luke 23:34a: Sinaiticus has it, Vaticanus does not' );
check( isset( $all['mark-16-9-20']['mss']['03']['t']['mark 16:8'] ), 'the text shows the verse before the passage' );
check( false !== strpos( (string) ( $all['1-john-5-7-8']['en']['1-john 5:7']['KJV'] ?? '' ), 'Holy Ghost' ), 'the KJV wording is carried for the passage' );
check( empty( array_filter( array_keys( $all['john-7-53-8-11']['en'] ?? array() ), static fn( $k ) => 'john 7:54' === $k ) ), 'no verse past the end of a chapter is invented' );

$index = tbe_view_manuscripts();
check( false !== strpos( $index, '<h1 class="tbe-title">Manuscripts</h1>' ) && substr_count( $index, 'https://thebible.exposed/manuscripts/' ) >= 20, 'the index lists every passage' );
check( false !== strpos( $index, 'Center for New Testament Restoration' ) && false !== strpos( $index, 'CC BY-SA 4.0' ), 'the index carries the CNTR credit and licence' );
foreach ( $all as $id => $p ) {
	$html = tbe_view_manuscript_passage( $p );
	[ $title ] = TBE_Manuscripts::about( $id );
	if ( $title === $id || false === strpos( $html, esc_html( $title ) ) || false === strpos( $html, '<table class="tbe-table tbe-ms__table">' ) || false === strpos( $html, 'CC BY-SA 4.0' ) ) {
		check( false, 'passage page renders: ' . $id );
	}
}
check( true, 'every passage page renders with its title, table and credit' );
$mark = tbe_view_manuscript_passage( $all['mark-16-9-20'] );
check( false !== strpos( $mark, 'Codex Vaticanus' ) && false !== strpos( $mark, 'Luke 1:1' ) && false !== strpos( $mark, 'runs straight from the verse before' ), 'Mark 16: Vaticanus runs from 16:8 straight to Luke 1:1' );
check( '' !== tbe_manuscripts_chapter_note( 'mark', 16 ) && '' === tbe_manuscripts_chapter_note( 'mark', 1 ), 'the chapter note shows only where a passage is' );
check( 'John 7:53–8:11' === TBE_Manuscripts::label( $all['john-7-53-8-11'] ) && 'Mark 9:44, 46' === TBE_Manuscripts::label( $all['mark-9-44-46'] ), 'passage labels' );

$imgs = json_decode( (string) file_get_contents( TBE_DIR . '/data/manuscripts/images.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( is_array( $imgs ) && ! empty( $imgs['images'] ), 'the photograph list reads' );
$bad = array_filter( (array) $imgs['images'], static fn( $i ) => ! isset( $all[ $i['passage'] ?? '' ]['mss'][ $i['ms'] ?? '' ] ) || '' === trim( (string) ( $i['caption'] ?? '' ) ) );
check( ! $bad, 'every photograph names a passage and a manuscript on it, with a caption' );
$shown = TBE_Manuscripts::images( 'mark-16-9-20' );
foreach ( $shown as $i ) {
	check( preg_match( '/^(public domain|pd\b|cc0|cc by(-sa)? [0-9.]+)/i', (string) $i['license'] ) && 0 === strpos( (string) $i['page'], 'https://commons.wikimedia.org/' ), 'photograph ' . $i['src'] . ' is free and links to Commons' );
}
check( ! $shown || false !== strpos( tbe_manuscripts_gallery( 'mark-16-9-20' ), 'Wikimedia Commons' ), 'the gallery credits Commons' );
check( '' === tbe_manuscripts_gallery( 'romans-16-24' ), 'no gallery where there is no photograph' );

$app = TBE_Manuscripts::apparatus( 'mark-16-9-20' );
check( 'A' === ( $app['ubs']['rating'] ?? '' ) && false !== strpos( (string) $app['ubs']['readings'][0][1], 'ℵ B' ), 'UBS: Mark 16:9-20 is rated {A} and ℵ B end at 16:8' );
$every = array_filter( array_keys( $all ), static fn( $id ) => ! TBE_Manuscripts::apparatus( $id )['ubs'] );
check( ! $every, 'every passage has its UBS evidence' );
check( null !== TBE_Manuscripts::apparatus( 'matthew-6-13' )['swanson'] && null === TBE_Manuscripts::apparatus( 'mark-16-9-20' )['swanson'], 'Swanson covers the Matthew passages only' );
foreach ( array( 'ubs', 'swanson' ) as $book ) {
	foreach ( array_keys( $all ) as $id ) {
		$e = TBE_Manuscripts::apparatus( $id )[ $book ];
		if ( $e && ! is_readable( TBE_DIR . '/' . $e['img'] ) ) {
			check( false, "page image for $book $id" );
		}
	}
}
$html = tbe_view_manuscript_passage( $all['matthew-6-13'] );
check( false !== strpos( $html, 'Later manuscripts too' ) && false !== strpos( $html, 'Swanson' ) && false !== strpos( $html, '{A}' ) && false !== strpos( $html, 'greek-nt.pdf#page=119' ), 'the passage page shows both apparatuses, the rating and a link to the page' );

$routes = (string) file_get_contents( TBE_DIR . '/inc/routes.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $routes, "'^manuscripts/?$'" ) && false !== strpos( $routes, "'^manuscripts/([a-z0-9-]+)/?$'" ), 'the routes answer /manuscripts/ and each passage' );
$reader = (string) file_get_contents( TBE_DIR . '/inc/reader.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $reader, 'Greek manuscripts' ) && false !== strpos( $reader, 'https://greekcntr.org/' ), 'the Sources page credits CNTR' );
$functions = (string) file_get_contents( TBE_DIR . '/functions.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $functions, "'manuscripts'" ), 'functions.php loads inc/manuscripts.php' );
check( is_readable( TBE_DIR . '/data/manuscripts/README.txt' ), 'the data folder says where the data comes from' );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
