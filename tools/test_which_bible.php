<?php
/**
 * Self-test for the "Which Bible?" page (inc/which-bible.php, 0.10.28). Run: php tools/test_which_bible.php
 *
 * WordPress is stubbed. Checks that the page renders its four sections with the canon table (5, 24, 66, 73, 76+
 * and 81), the Samaritan counts summed from data/sp/, links to the manuscripts passages and to the books once read
 * as scripture, and the graphic; that the route, the request and the title know the page; and that the graphic and
 * the script that draws it are there. Exit code 0 on success.
 *
 * @package tbe-child
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_DIR', dirname( __DIR__ ) );
define( 'TBE_URI', 'https://thebible.exposed/wp-content/themes/TBE' );
define( 'TBE_VERSION', 'test' );
define( 'DAY_IN_SECONDS', 86400 );
function __( $s ) { return $s; }
function _n( $a, $b, $n ) { return 1 === (int) $n ? $a : $b; }
function esc_html__( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_kses_post( $s ) { return (string) $s; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function home_url( $p = '' ) { return 'https://thebible.exposed' . $p; }
function tbe_setting( $k ) { return 'verse'; }
final class TBE_Bible { // phpcs:ignore
	public static function url( ?string $slug = null, ?int $c = null ): string { return home_url( '/bible/' . ( $slug ? $slug . '/' . ( $c ? $c . '/' : '' ) : '' ) ); }
	public static function book( string $slug ): ?array { return array( 'slug' => $slug, 'name' => ucwords( str_replace( '-', ' ', $slug ) ) ); }
	public static function label( string $slug, int $c, int $a = 0, int $b = 0 ): string { return ucwords( str_replace( '-', ' ', $slug ) ) . " $c" . ( $a ? ":$a" . ( $b > $a ? "–$b" : '' ) : '' ); }
}
final class TBE_Interlinear { // phpcs:ignore
	public static function url( string $slug, int $c ): string { return home_url( "/bible/$slug/$c/interlinear/" ); }
}
require __DIR__ . '/../inc/canon.php';
require __DIR__ . '/../inc/further.php';
require __DIR__ . '/../inc/manuscripts.php';
require __DIR__ . '/../inc/which-bible.php';

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

[ $m, $s ] = tbe_which_bible_sp_counts();
check( $m > 3000 && $s > 4000, "Samaritan differences summed from data/sp/ ($m of meaning, $s of spelling)" );
$html = tbe_view_which_bible();
check( false !== strpos( $html, '<h1 class="tbe-title">Which Bible?</h1>' ), 'the page has its title' );
foreach ( array( 'books', 'text', 'verses', 'dropped' ) as $id ) {
	check( false !== strpos( $html, 'id="' . $id . '"' ), "section #$id" );
}
check( 6 === preg_match_all( '#<td class="tbe-which__count">(5|24|66|73|76\+|81)</td>#', $html ), 'the canon table: 5, 24, 66, 73, 76+ and 81' );
check( false !== strpos( $html, number_format( $m ) . ' places of meaning' ), 'the text section quotes the Samaritan count' );
check( false !== strpos( $html, '/manuscripts/mark-16-9-20/' ) && false !== strpos( $html, '/manuscripts/john-7-53-8-11/' ) && false !== strpos( $html, '/manuscripts/1-john-5-7-8/' ), 'links to the Mark ending, the woman caught in adultery and the three witnesses' );
check( false !== strpos( $html, '/bible/hermas/' ) && false !== strpos( $html, '/bible/apocalypse-of-peter/' ) && false !== strpos( $html, '/bible/laodiceans/' ), 'links to the books once read as scripture' );
check( false !== strpos( $html, 'assets/img/which-bible.png' ) && is_readable( TBE_DIR . '/assets/img/which-bible.png' ) && is_readable( TBE_DIR . '/tools/build_which_bible_graphic.js' ), 'the graphic is on the page and in the theme, with the script that draws it' );
check( false === stripos( $html, 'qur' ) && false === stripos( $html, 'islam' ), 'nothing Islamic on the page (owner\'s rule)' );
$routes = (string) file_get_contents( TBE_DIR . '/inc/routes.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
check( false !== strpos( $routes, "'^which-bible/?$'" ) && false !== strpos( $routes, "'which-bible' === \$view" ) && false !== strpos( $routes, "case 'which-bible':" ), 'the route, the request and the title know the page' );

echo $fail ? "\n$fail failed\n" : "\nall passed\n";
exit( $fail ? 1 : 0 );
