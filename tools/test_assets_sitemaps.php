<?php
/**
 * Self-test for 0.10.6: asset versions by content hash (inc/enqueue.php, inc/branding.php) and the sitemaps
 * (inc/sitemaps.php). Run: php tools/test_assets_sitemaps.php
 *
 * WordPress is stubbed. The test checks that every stylesheet, script and brand icon address carries its own
 * file's hash, that the address changes when the contents change and not before, that a part-written file and
 * the finished one, or two files of one size written in the same second, never share an address, that
 * /favicon.ico answers 304 to a matching ETag, that a missing file leaves an empty handle, or no tag, and no
 * address, and that no theme file is enqueued with the theme version any more. For the sitemaps it checks that
 * WordPress's own stay on when "Search engine visibility" is ticked, that an SEO plugin's sitemap wins, that the
 * filter can turn them off, and that robots.txt names the live sitemap once. Exit code 0 on success.
 *
 * @package tbe-child
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_VERSION', '9.9.9' );
$t_dir = sys_get_temp_dir() . '/tbe-assets-' . getmypid();
@mkdir( $t_dir . '/assets/css', 0700, true );
@mkdir( $t_dir . '/assets/js', 0700, true );
@mkdir( $t_dir . '/assets/fonts', 0700, true );
define( 'TBE_DIR', $t_dir );
define( 'TBE_URI', 'https://tbe.test/wp-content/themes/TBE' );

$GLOBALS['t'] = array( 'hooks' => array(), 'styles' => array(), 'scripts' => array(), 'options' => array( 'blog_public' => '0' ), 'filters' => array() );
function add_action( $h, $f, $p = 10 ) { $GLOBALS['t']['hooks'][ $h ][] = array( $f, $p ); }
function add_filter( $h, $f, $p = 10 ) { $GLOBALS['t']['hooks'][ $h ][] = array( $f, $p ); }
function apply_filters( $h, $v ) { return array_key_exists( $h, $GLOBALS['t']['filters'] ) ? $GLOBALS['t']['filters'][ $h ] : $v; }
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) {
	if ( '' !== $src ) {
		$GLOBALS['t']['styles'][ $h ] = array( $src, $deps, $ver );
	}
	$GLOBALS['t']['enqueued'][] = $h;
}
function wp_register_style( $h, $src, $deps = array(), $ver = false ) { $GLOBALS['t']['styles'][ $h ] = array( $src, $deps, $ver ); }
function wp_enqueue_script( $h, $src = '', $deps = array(), $ver = false, $args = array() ) {
	if ( '' !== $src ) {
		$GLOBALS['t']['scripts'][ $h ] = array( $src, $deps, $ver, $args );
	}
	$GLOBALS['t']['enqueued'][] = $h;
}
function wp_register_script( $h, $src, $deps = array(), $ver = false, $args = array() ) { $GLOBALS['t']['scripts'][ $h ] = array( $src, $deps, $ver, $args ); }
function esc_url( $u ) { return (string) $u; }
function wp_add_inline_style( $h, $css ) { $GLOBALS['t']['inline'][ $h ][] = $css; }
function esc_url_raw( $u ) { return (string) $u; }
function add_query_arg( $k, $v, $u ) { return $u . '?' . $k . '=' . $v; }
function home_url( $p = '' ) { return 'https://tbe.test' . $p; }
function get_option( $k, $d = false ) { return $GLOBALS['t']['options'][ $k ] ?? $d; }
function get_sitemap_url( $name ) { return 'index' === $name ? 'https://tbe.test/wp-sitemap.xml' : false; }
// WordPress's sitemap server, as core builds it: blog_public, then the wp_sitemaps_enabled filter.
class T_Sitemaps {
	public function sitemaps_enabled() {
		$v = (bool) get_option( 'blog_public' );
		foreach ( $GLOBALS['t']['hooks']['wp_sitemaps_enabled'] ?? array() as $h ) {
			$v = call_user_func( $h[0], $v );
		}
		return $v;
	}
}
function wp_sitemaps_get_server() { return new T_Sitemaps(); }

$fail = 0;
function t_hash( $s ) { return hash( in_array( 'xxh3', hash_algos(), true ) ? 'xxh3' : 'crc32b', $s ); }
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

require __DIR__ . '/../inc/enqueue.php';
require __DIR__ . '/../inc/sitemaps.php';

// Asset versions.
file_put_contents( "$t_dir/assets/css/base.css", 'body{}' );
touch( "$t_dir/assets/css/base.css", 1700000000 );
clearstatcache();
check( t_hash( 'body{}' ) === tbe_asset_version( 'assets/css/base.css' ), 'the version is a hash of the file\'s contents' );
check( null === tbe_asset_version( 'assets/css/missing.css' ), 'a missing file has no version' );
tbe_enqueue_theme_style( 'tbe-base', 'assets/css/base.css', array( 'parent' ) );
check( array( TBE_URI . '/assets/css/base.css', array( 'parent' ), t_hash( 'body{}' ) ) === $GLOBALS['t']['styles']['tbe-base'], 'a stylesheet goes out with its hash as ?ver=' );
touch( "$t_dir/assets/css/base.css", 1700000600 );
clearstatcache();
tbe_enqueue_theme_style( 'tbe-base', 'assets/css/base.css', array( 'parent' ) );
check( t_hash( 'body{}' ) === $GLOBALS['t']['styles']['tbe-base'][2], 'a new time alone keeps the address' );
file_put_contents( "$t_dir/assets/css/base.css", 'html{}' );
touch( "$t_dir/assets/css/base.css", 1700000600 );
clearstatcache();
check( t_hash( 'html{}' ) === tbe_asset_version( 'assets/css/base.css' ), 'new contents of the same size, written in the same second, get a new address, so cache busting holds' );
// A file caught part-written, then finished in the same second: the two addresses differ.
file_put_contents( "$t_dir/assets/css/base.css", 'bo' );
touch( "$t_dir/assets/css/base.css", 1700000900 );
clearstatcache();
$part = tbe_asset_version( 'assets/css/base.css' );
file_put_contents( "$t_dir/assets/css/base.css", 'body{}' );
touch( "$t_dir/assets/css/base.css", 1700000900 );
clearstatcache();
check( t_hash( 'bo' ) === $part && t_hash( 'body{}' ) === tbe_asset_version( 'assets/css/base.css' ), 'a part-written file and the finished one never share an address, even in the same second' );
tbe_enqueue_theme_style( 'tbe-reader', 'assets/css/reader.css', array( 'tbe-base' ) );
check( false === $GLOBALS['t']['styles']['tbe-reader'][0] && array( 'tbe-base' ) === $GLOBALS['t']['styles']['tbe-reader'][1] && in_array( 'tbe-reader', $GLOBALS['t']['enqueued'], true ), 'a missing stylesheet leaves an empty handle with its dependencies, and no address' );
file_put_contents( "$t_dir/assets/js/theme.js", '//' );
touch( "$t_dir/assets/js/theme.js", 1700001200 );
clearstatcache();
tbe_enqueue_theme_script( 'tbe-theme', 'assets/js/theme.js', array(), array( 'in_footer' => false ) );
check( array( TBE_URI . '/assets/js/theme.js', array(), t_hash( '//' ), array( 'in_footer' => false ) ) === $GLOBALS['t']['scripts']['tbe-theme'], 'a script goes out with its hash and its loading arguments' );
tbe_enqueue_theme_script( 'tbe-reader', 'assets/js/reader.js', array(), array( 'in_footer' => true, 'strategy' => 'defer' ) );
check( false === $GLOBALS['t']['scripts']['tbe-reader'][0], 'a missing script leaves an empty handle, and no address that answers 404' );
check( '' === tbe_font_preload( 'assets/fonts/SBL_BLit.woff2' ), 'no preload for a font that is not there yet' );
file_put_contents( "$t_dir/assets/fonts/SBL_BLit.woff2", 'x' );
clearstatcache();
touch( "$t_dir/assets/fonts/SBL_BLit.woff2", 1700001500 );
clearstatcache();
$font = TBE_URI . '/assets/fonts/SBL_BLit.woff2?ver=' . t_hash( 'x' );
check( false !== strpos( tbe_font_preload( 'assets/fonts/SBL_BLit.woff2' ), 'href="' . $font . '"' ), 'a preload once it is, at the versioned address' );

// The @font-face rules come from PHP: versioned, and only for fonts whose files are there.
$css = tbe_font_face_css( array( 'biblit', 'hebrew', 'greek', 'estrangela' ) );
check( 1 === substr_count( $css, '@font-face' ) && false !== strpos( $css, 'font-family:"SBL BibLit";src:url("' . $font . '")' ), 'a rule for the font that is there, at the address its preload names; none for the missing ones' );
file_put_contents( "$t_dir/assets/fonts/SBL_Hbrw.woff2", 'xy' );
clearstatcache();
check( false !== strpos( tbe_font_face_css( array( 'hebrew' ) ), 'unicode-range:U+0590-05FF, U+FB1D-FB4F' ), 'the character range is kept' );
$GLOBALS['t']['inline'] = array();
tbe_attach_font_faces( 'tbe-scripture' );
tbe_attach_font_faces( 'tbe-scripture' );
check( 1 === count( $GLOBALS['t']['inline']['tbe-scripture'] ?? array() ) && 2 === substr_count( $GLOBALS['t']['inline']['tbe-scripture'][0], '@font-face' ), 'the rules go inline with the stylesheet that uses them, once' );
$editor = null;
foreach ( $GLOBALS['t']['hooks']['block_editor_settings_all'] ?? array() as $h ) {
	$editor = call_user_func( $h[0], array( 'styles' => array() ) );
}
check( is_array( $editor ) && false !== strpos( (string) ( end( $editor['styles'] )['css'] ?? '' ), 'SBL BibLit' ), 'the editor gets the same rules' );
$mce = tbe_mce_css( 'https://tbe.test/wp-includes/css/dashicons.css,' . TBE_URI . '/assets/fonts/SBL_BLit.woff2?ver=6.9,' . TBE_URI . '/assets/css/scripture.css?ver=6.9' );
check( 'https://tbe.test/wp-includes/css/dashicons.css,' . $font === $mce, 'TinyMCE gets the theme\'s editor styles at their hashed addresses, a missing one left out, and other stylesheets untouched' );
$css_files = '';
foreach ( glob( __DIR__ . '/../assets/css/*.css' ) as $f ) {
	$css_files .= (string) file_get_contents( $f );
}
check( false === stripos( $css_files, '@font-face {' ) && ! preg_match( '~url\(["\']?\.\./fonts/~', $css_files ), 'no stylesheet names a font file itself any more' );
$handles = tbe_font_face_handles();
$faces   = tbe_font_faces();
check( array() === array_diff( array_merge( ...array_values( $handles ) ), array_keys( $faces ) ) && array() === array_diff( array_keys( $faces ), array_merge( ...array_values( $handles ) ) ), 'every font has a stylesheet that carries its rule' );

// No theme file is enqueued with the theme version or straight from TBE_URI any more.
$left = array();
foreach ( glob( __DIR__ . '/../inc/*.php' ) as $f ) {
	if ( preg_match_all( '~wp_enqueue_(?:style|script)\(\s*\'[^\']+\',\s*TBE_URI~', (string) file_get_contents( $f ), $m ) ) {
		$left[] = basename( $f );
	}
}
check( array() === $left, 'every theme stylesheet and script goes through the file-time helpers (' . implode( ', ', $left ) . ')' );
require __DIR__ . '/../inc/branding.php';
@mkdir( "$t_dir/assets/img/brand", 0700, true );
check( '' === tbe_brand_url( 'favicon.svg' ), 'a missing brand icon has no address' );
ob_start();
tbe_brand_tag( '<link rel="icon" href="%s">', 'favicon.svg' );
check( '' === ob_get_clean(), 'and its tag is left out' );
file_put_contents( "$t_dir/assets/img/brand/favicon.svg", '<svg/>' );
touch( "$t_dir/assets/img/brand/favicon.svg", 1700002000 );
clearstatcache();
ob_start();
tbe_brand_tag( '<link rel="icon" href="%s">', 'favicon.svg' );
check( '<link rel="icon" href="' . TBE_URI . '/assets/img/brand/favicon.svg?ver=' . t_hash( '<svg/>' ) . '">' . "\n" === ob_get_clean(), 'once it is up, the tag carries its hash' );
// /favicon.ico: missing, it is left to WordPress; present, it carries an ETag and answers 304 to it.
$fsrc = '<?php define( "ABSPATH", "/" ); define( "TBE_DIR", ' . var_export( $t_dir, true ) . ' ); define( "TBE_URI", "u" );'
	. ' function add_action() {} function add_filter() {} function has_site_icon() { return false; } function wp_unslash( $s ) { return $s; }'
	. ' function status_header( $c ) { echo "status:$c"; } function wp_register_style() {} function wp_enqueue_style() {} function wp_register_script() {} function wp_enqueue_script() {}'
	. ' require ' . var_export( __DIR__ . '/../inc/enqueue.php', true ) . '; require ' . var_export( __DIR__ . '/../inc/branding.php', true ) . ';'
	. ' $_SERVER["HTTP_IF_NONE_MATCH"] = getenv( "T_INM" ) ?: ""; tbe_serve_favicon(); echo "fell-through";';
file_put_contents( "$t_dir/fav.php", $fsrc );
$run = static function ( $inm ) use ( $t_dir ) {
	return (string) shell_exec( 'T_INM=' . escapeshellarg( $inm ) . ' ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( "$t_dir/fav.php" ) . ' 2>&1' );
};
check( 'fell-through' === $run( '' ), 'a missing /favicon.ico is left to WordPress' );
file_put_contents( "$t_dir/assets/img/brand/favicon.ico", 'ICO1' );
check( 'ICO1' === $run( '"nope"' ), 'present, the icon is sent' );
check( 'status:304' === $run( '"' . t_hash( 'ICO1' ) . '"' ), 'and a browser holding the same icon gets 304' );
unlink( "$t_dir/fav.php" );
$brand = (string) file_get_contents( __DIR__ . '/../inc/branding.php' );
check( false !== strpos( $brand, "tbe_font_preload( 'assets/fonts/Angel.woff2' )" ) && false === strpos( $brand, 'esc_url( tbe_brand_url(' ), 'the Angel preload waits for its file, and every brand tag goes through tbe_brand_tag()' );

$schema = (string) file_get_contents( __DIR__ . '/../inc/schema.php' );
check( false === strpos( $schema, "TBE_URI . '/assets/img/brand/" ) && false !== strpos( $schema, "tbe_brand_url( 'icon-512.png' )" ), 'the JSON-LD logo and image use the versioned brand addresses too' );

// Sitemaps.
$priority = null;
foreach ( $GLOBALS['t']['hooks']['wp_sitemaps_enabled'] ?? array() as $h ) {
	$priority = 'tbe_core_sitemaps_enabled' === $h[0] ? $h[1] : $priority;
}
check( 1 === $priority, 'the sitemap filter runs at priority 1, so an SEO plugin at 10 still wins' );
check( wp_sitemaps_get_server()->sitemaps_enabled(), 'with "Discourage search engines" ticked, WordPress\'s sitemaps stay on' );
check( 'https://tbe.test/wp-sitemap.xml' === tbe_active_sitemap_url(), 'the live sitemap is /wp-sitemap.xml' );
$robots = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
check( $robots . "\nSitemap: https://tbe.test/wp-sitemap.xml\n" === tbe_robots_sitemap( $robots ), 'robots.txt names it, where WordPress would not on a site marked not public' );
$core = $robots . "\nSitemap: https://tbe.test/wp-sitemap.xml\n";
check( $core === tbe_robots_sitemap( $core ), 'a line already there is not repeated' );
$GLOBALS['t']['filters']['tbe_plugin_sitemap_url'] = 'https://tbe.test/sitemap_index.xml';
check( false === tbe_core_sitemaps_enabled( false ) && true === tbe_core_sitemaps_enabled( true ), 'with an SEO plugin\'s sitemap the theme leaves WordPress\'s decision alone' );
check( 'https://tbe.test/sitemap_index.xml' === tbe_active_sitemap_url() && false !== strpos( tbe_robots_sitemap( $robots ), 'Sitemap: https://tbe.test/sitemap_index.xml' ) && false === strpos( tbe_robots_sitemap( $robots ), 'wp-sitemap' ), 'robots.txt names the plugin\'s sitemap instead' );
unset( $GLOBALS['t']['filters']['tbe_plugin_sitemap_url'] );
$GLOBALS['t']['filters']['tbe_core_sitemaps'] = false;
check( ! wp_sitemaps_get_server()->sitemaps_enabled() && '' === tbe_active_sitemap_url() && $robots === tbe_robots_sitemap( $robots ), 'tbe_core_sitemaps turns them off, and robots.txt then names none' );
unset( $GLOBALS['t']['filters']['tbe_core_sitemaps'] );
check( '' === tbe_plugin_sitemap_url(), 'no SEO plugin, no plugin sitemap' );

array_map( 'unlink', glob( "$t_dir/assets/img/brand/*" ) );
rmdir( "$t_dir/assets/img/brand" );
array_map( 'unlink', array_filter( glob( "$t_dir/assets/*/*" ), 'is_file' ) );
array_map( 'rmdir', glob( "$t_dir/assets/*" ) );
rmdir( "$t_dir/assets" );
rmdir( $t_dir );
echo $fail ? "$fail failed\n" : "all passed\n";
exit( $fail ? 1 : 0 );
