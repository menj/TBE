<?php
/**
 * Self-test for the login screen (inc/login.php). Run: php tools/test_login.php
 *
 * WordPress is stubbed. The test checks the switch and the step-aside while a login plugin is
 * active, the link, the mark and the name, the logo setting, the body classes, the line above the
 * form (log-in screen only, never the interim login), the stylesheet hook, and that every title
 * reads "<screen> | <site name>" under 60 characters, a long site name included.
 * Exit code 0 on success.
 *
 * @package tbe-child
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'TBE_VERSION', 'test' );
define( 'TBE_URI', 'https://tbe.test/t' );

$GLOBALS['t_set']    = array( 'login_design' => 1, 'login_logo' => '', 'login_message' => '' );
$GLOBALS['t_plugin'] = false;
$GLOBALS['t_name']   = 'The Bible Exposed';
$GLOBALS['t_enq']    = array();
$GLOBALS['t_hooks']  = array();

function add_action( $h, $f ) { $GLOBALS['t_hooks'][ $h ][] = $f; }
function add_filter( $h, $f ) { $GLOBALS['t_hooks'][ $h ][] = $f; }
function apply_filters( $h, $v ) { return 'tbe_login_plugin_active' === $h ? ( $v || $GLOBALS['t_plugin'] ) : $v; }
function tbe_setting( $k ) { return $GLOBALS['t_set'][ $k ] ?? null; }
function tbe_scheme_css() { return ':root{--tbe-accent:#6e1f2e;}'; }
function tbe_brandmark_svg() { return '<span class="tbe-brandmark" aria-hidden="true"><svg></svg></span>'; }
function home_url( $p = '' ) { return 'https://tbe.test' . $p; }
function get_bloginfo() { return $GLOBALS['t_name']; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function __( $s ) { return $s; }
function wp_enqueue_style( $h ) { $GLOBALS['t_enq'][] = $h; }
function wp_enqueue_script( $h ) { $GLOBALS['t_enq'][] = $h; }
function wp_add_inline_style( $h, $css ) { $GLOBALS['t_inline'] = $css; }
require dirname( __DIR__ ) . '/inc/login.php';

$fail = 0;
function ok( $c, $what ) {
	global $fail;
	echo ( $c ? 'ok   ' : 'FAIL ' ) . $what . "\n";
	$fail += $c ? 0 : 1;
}

ok( tbe_login_design_on(), 'on by default' );
ok( in_array( 'tbe_login_assets', $GLOBALS['t_hooks']['login_enqueue_scripts'] ?? array(), true ), 'assets on login_enqueue_scripts' );
tbe_login_assets();
ok( array( 'tbe-theme', 'tbe-login' ) === $GLOBALS['t_enq'], 'stylesheet and light/dark script enqueued' );
ok( str_contains( $GLOBALS['t_inline'], '--tbe-accent' ), 'active scheme printed' );
ok( 'https://tbe.test/' === tbe_login_header_url( 'https://wordpress.org/' ), 'mark links home' );
$h = tbe_login_header_text( 'Powered by WordPress' );
ok( str_contains( $h, 'tbe-brandmark' ) && str_contains( $h, '<span class="tbe-login-name">The Bible Exposed</span>' ), 'mark and name' );
$GLOBALS['t_set']['login_logo'] = 'https://tbe.test/logo.png';
$h = tbe_login_header_text( '' );
ok( str_contains( $h, 'src="https://tbe.test/logo.png"' ) && ! str_contains( $h, 'tbe-brandmark' ), 'logo setting replaces the mark' );
$GLOBALS['t_set']['login_logo'] = '';
ok( in_array( 'tbe-login', tbe_login_body_class( array( 'login' ) ), true ), 'body class' );

$GLOBALS['t_set']['login_message'] = 'Editors <i>only</i>';
ok( str_contains( tbe_login_message( '' ), 'Editors &lt;i&gt;only' ), 'line printed, escaped' );
$_REQUEST = array( 'action' => 'lostpassword' );
ok( '' === tbe_login_message( '' ), 'line absent on lost password' );
$_REQUEST = array( 'interim-login' => '1' );
ok( '' === tbe_login_message( '' ), 'line absent in the interim login' );
ok( in_array( 'tbe-login--interim', tbe_login_body_class( array() ), true ), 'interim class' );
$_REQUEST = array();

foreach ( array( 'The Bible Exposed', 'A Very Long Site Name That Goes On And On For Ever And Ever' ) as $name ) {
	$GLOBALS['t_name'] = $name;
	foreach ( array( 'Log In', 'Lost Password', 'Reset Password', 'Registration Form', 'Check your email', 'A screen title long enough to need cutting at a word', '' ) as $screen ) {
		$t = tbe_login_title( 'x', $screen );
		ok( mb_strlen( $t ) < 60 && 1 === substr_count( $t, ' | ' ) && '' !== explode( ' | ', $t )[0], "title: $t" );
	}
}
$GLOBALS['t_name'] = 'The Bible Exposed';
ok( 'Log In | The Bible Exposed' === tbe_login_title( 'x', 'Log In' ), 'title exact' );

$GLOBALS['t_set']['login_design'] = 0;
ok( ! tbe_login_design_on(), 'switch off' );
ok( 'https://wordpress.org/' === tbe_login_header_url( 'https://wordpress.org/' ), 'off: core link' );
ok( array( 'login' ) === tbe_login_body_class( array( 'login' ) ), 'off: no class' );
$GLOBALS['t_enq'] = array();
tbe_login_assets();
ok( array() === $GLOBALS['t_enq'], 'off: nothing enqueued' );

$GLOBALS['t_set']['login_design'] = 1;
$GLOBALS['t_plugin']              = true;
ok( ! tbe_login_design_on(), 'login plugin: theme steps aside' );
ok( 'Powered by WordPress' === tbe_login_header_text( 'Powered by WordPress' ), 'login plugin: core text kept' );

$src = file_get_contents( dirname( __DIR__ ) . '/inc/login.php' );
ok( ! preg_match( '/wp_login_url|site_url\(|login_url/', $src ), 'builds no login address (WPS Hide Login keeps working)' );

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit( $fail ? 1 : 0 );
