<?php
/**
 * Self-test for the fixes from the live logs (inc/hardening.php, inc/dev-files.php, functions.php).
 * Run: php tools/test_live_logs.php
 *
 * WordPress is stubbed. The test checks that the REST user routes are refused to visitors and kept for a
 * logged-in user, that ?author=N is caught, that XML-RPC is off unless TBE_ALLOW_XMLRPC or Jetpack keeps it on,
 * the .htaccess lines the Error pages tab shows, the clean-up of the development files under its lease, the
 * incomplete-upload guard, the scanner-probe short 404 (which paths it catches and which it never does), and the
 * command-line guard on every script in tools/. Exit code 0 on success.
 *
 * @package tbe-child
 */

// Run from the command line only: on a web server this file does nothing.
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', '/srv/site/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'TBE_VERSION', '9.9.9' );

$GLOBALS['t'] = array( 'hooks' => array(), 'options' => array(), 'transients' => array(), 'user' => 0, 'env' => 'production', 'admin' => false, 'ajax' => false );
function add_filter( $h, $f, $p = 10, $a = 1 ) { $GLOBALS['t']['hooks'][ $h ][] = $f; }
function add_action( $h, $f, $p = 10, $a = 1 ) { $GLOBALS['t']['hooks'][ $h ][] = $f; }
function remove_action( $h, $f ) {
	$GLOBALS['t']['hooks'][ $h ] = array_values( array_filter( $GLOBALS['t']['hooks'][ $h ] ?? array(), static fn( $x ) => $x !== $f ) );
}
function do_action( $h ) {
	foreach ( $GLOBALS['t']['hooks'][ $h ] ?? array() as $f ) {
		call_user_func( $f );
	}
}
function t_filter( $h, ...$args ) {
	$v = $args[0];
	foreach ( $GLOBALS['t']['hooks'][ $h ] ?? array() as $f ) {
		$args[0] = $v;
		$v       = call_user_func_array( $f, $args );
	}
	return $v;
}
function is_user_logged_in() { return $GLOBALS['t']['user'] > 0; }
function is_admin() { return $GLOBALS['t']['admin']; }
function wp_doing_ajax() { return $GLOBALS['t']['ajax']; }
function wp_doing_cron() { return false; }
function wp_get_environment_type() { return $GLOBALS['t']['env']; }
function get_option( $k, $d = false ) { return $GLOBALS['t']['options'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['t']['options'][ $k ] = $v; return true; }
function add_option( $k, $v ) {
	if ( array_key_exists( $k, $GLOBALS['t']['options'] ) ) {
		return false;
	}
	$GLOBALS['t']['options'][ $k ] = $v;
	return true;
}
function get_transient( $k ) { return $GLOBALS['t']['transients'][ $k ] ?? false; }
function set_transient( $k, $v ) { $GLOBALS['t']['transients'][ $k ] = $v; return true; }
function wp_rand( $a, $b ) { return random_int( $a, $b ); }
function wp_cache_delete() { return true; }

$fail = 0;
function check( $ok, $label ) {
	global $fail;
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $label . "\n";
	$fail += $ok ? 0 : 1;
}

// The log lines go to a file of their own.
$log = sys_get_temp_dir() . '/tbe-live-log-' . getmypid();
ini_set( 'error_log', $log ); // phpcs:ignore WordPress.PHP.IniSet

require __DIR__ . '/../inc/hardening.php';

// REST users and ?author=N.
$routes = array( '/wp/v2/users' => 1, '/wp/v2/users/(?P<id>[\d]+)' => 1, '/wp/v2/users/me' => 1, '/wp/v2/posts' => 1, '/wp/v2/usersettings' => 1 );
$out    = t_filter( 'rest_endpoints', $routes );
check( array( '/wp/v2/posts', '/wp/v2/usersettings' ) === array_keys( $out ), 'a visitor gets no user route, /wp/v2/users/<id> included' );
$GLOBALS['t']['user'] = 1;
check( $routes === t_filter( 'rest_endpoints', $routes ), 'a logged-in user keeps them all, for the block editor' );
$GLOBALS['t']['user'] = 0;
check( tbe_is_author_query( array( 'author' => '1' ) ) && tbe_is_author_query( array( 'author_name' => 'admin' ) ), '?author=1 and ?author_name= are caught' );
check( ! tbe_is_author_query( array( 's' => 'author' ) ) && ! tbe_is_author_query( array() ), 'a search for the word, or no query, is left alone' );
check( false === t_filter( 'wp_sitemaps_add_provider', 'provider', 'users' ) && 'provider' === t_filter( 'wp_sitemaps_add_provider', 'provider', 'posts' ), 'no users sitemap; the others stay' );

// XML-RPC.
check( false === t_filter( 'xmlrpc_enabled', true ), 'XML-RPC is off' );
check( array() === t_filter( 'xmlrpc_methods', array( 'wp.getUsersBlogs' => 'x' ) ), 'and no method answers' );
check( array( 'A' => 1 ) === t_filter( 'wp_headers', array( 'X-Pingback' => 'x', 'A' => 1 ) ), 'the X-Pingback header goes' );
$snip = tbe_security_htaccess_snippet( '' );
check( false !== strpos( $snip, "# BEGIN Block xmlrpc.php\n<Files xmlrpc.php>" ) && false !== strpos( $snip, 'RewriteRule ^wp-includes/' ), 'the .htaccess lines deny xmlrpc.php and the core files' );
eval( 'class Jetpack {}' ); // phpcs:ignore Squiz.PHP.Eval
check( true === t_filter( 'xmlrpc_enabled', true ) && tbe_xmlrpc_allowed(), 'Jetpack keeps it on' );
$src = (string) file_get_contents( __DIR__ . '/../inc/hardening.php' );
check( false !== strpos( $src, "defined( 'TBE_ALLOW_XMLRPC' ) && TBE_ALLOW_XMLRPC" ), 'so does TBE_ALLOW_XMLRPC' );

// Scanner probes (0.10.6): the paths from the live logs get the short 404; the site's own paths never do.
$probes = array( '/.env', '/.env.production', '/.env.bak', '/sitemaps/.env', '/wp/.env', '/.git/config', '/.git/HEAD', '/.aws/credentials', '/.DS_Store', '/%2eenv', '/phpinfo.php', '/phpinfo', '/_profiler/phpinfo', '/php-info.php', '/info.php', '/test.php', '/i.php', '/server-status.php', '/webmail/phpinfo.php', '/phpinfo.php~', '/_environment', '/webroot/_environment', '/webroot/index.php/_environment', '/wp-config.php', '/wp-config.php.bak', '/wp-config.bak', '/wp-config.php~', '/x.php/extra', '/WP-LOGIN.PHP', '/wp-content/plugins/hellopress/wp_filemanager.php', '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php' );
foreach ( $probes as $p ) {
	check( tbe_is_probe( $p ), "probe: $p" );
}
$pages = array( '/', '/bible/', '/bible/john/3/', '/bible/interlinear/', '/credits/', '/commentary/', '/glossary/', '/search/grace/', '/wp-sitemap.xml', '/sitemap.xml', '/robots.txt', '/feed/', '/index.php', '/wp-login.php', '/wp-cron.php', '/xmlrpc.php', '/wp-comments-post.php', '/wp-admin/', '/wp-admin/admin-ajax.php', '/wp-admin/post.php', '/wp-json/wp/v2/posts', '/.well-known/acme-challenge/token', '/.well-known/security.txt', '/.well-known/pki-validation/x.php', '/wp-content/themes/TBE/assets/js/reader.js', '/commentary/environment-and-law/', '/image.webp' );
foreach ( $pages as $p ) {
	check( ! tbe_is_probe( $p ), "not a probe: $p" );
}
check( '/.env' === tbe_probe_path( '/.env?x=1', '/' ) && '/.env' === tbe_probe_path( '/blog/.env', '/blog/' ) && null === tbe_probe_path( '/other/.env', '/blog/' ) && null === tbe_probe_path( '/blogx/.env', '/blog' ), 'the path is read below the home address; a path outside the site is left alone' );
$docroot = sys_get_temp_dir() . '/tbe-docroot-' . getmypid();
@mkdir( $docroot . '/wp-content/plugins/p', 0700, true );
file_put_contents( $docroot . '/wp-content/plugins/p/endpoint.php', '<?php' );
$_SERVER['DOCUMENT_ROOT'] = $docroot;
check( tbe_probe_has_file( '/wp-content/plugins/p/endpoint.php', '/wp-content/plugins/p/endpoint.php' ) && ! tbe_probe_has_file( '/info.php', '/info.php' ), 'a plugin\'s own PHP endpoint, a real file, is never caught' );
unlink( $docroot . '/wp-content/plugins/p/endpoint.php' );
unset( $_SERVER['DOCUMENT_ROOT'] );
$src = (string) file_get_contents( __DIR__ . '/../inc/hardening.php' );
check( false !== strpos( $src, "add_action( 'parse_request', 'tbe_short_circuit_probe', 0 );" ), 'the probe check runs on parse_request at priority 0, before the REST API at 10' );
check( false !== strpos( $src, "! empty( \$wp->query_vars['rest_route'] )" ) && false !== strpos( $src, 'tbe_incomplete_is_login()' ), 'REST routes and the login screen are skipped' );

// The .htaccess lines.
check( '' === tbe_core_prefix( '/srv/site', '/srv/site/' ) && 'wp/' === tbe_core_prefix( '/srv/site/', '/srv/site/wp/' ) && null === tbe_core_prefix( '/srv/site/', '/opt/wp/' ), 'WordPress at the root, in a subfolder, or outside' );
$rx = array();
foreach ( tbe_core_lines( '' ) as $line ) {
	if ( preg_match( '/^RewriteRule (\S+) - \[F,L\]$/', $line, $m ) ) {
		$rx[] = '#' . $m[1] . '#';
	}
}
$denied = static function ( $p ) use ( $rx ) {
	foreach ( $rx as $r ) {
		if ( preg_match( $r, $p ) ) {
			return true;
		}
	}
	return false;
};
$deny = array( 'wp-includes/blocks/rss.php', 'wp-includes/version.php', 'wp-settings.php', 'wp-config-sample.php', 'readme.html', 'wp-admin/install.php' );
$open = array( 'wp-includes/js/tinymce/wp-tinymce.php', 'wp-includes/ms-files.php', 'wp-includes/css/dashicons.min.css', 'wp-login.php', 'index.php', 'wp-admin/index.php' );
check( count( $deny ) === count( array_filter( $deny, $denied ) ), 'the core files are denied' );
check( ! array_filter( $open, $denied ), 'TinyMCE, ms-files.php, the stylesheets and the login screen stay open' );
// Jetpack is defined now, so the xmlrpc.php block is left out.
$snip = tbe_security_htaccess_snippet( 'wp/' );
check( false === strpos( $snip, 'xmlrpc.php' ) && false !== strpos( $snip, 'RewriteRule ^wp/wp-includes/' ), 'with XML-RPC allowed the snippet names only the core files, in the subfolder' );

// Development files and the clean-up.
$root = sys_get_temp_dir() . '/tbe-live-' . getmypid();
define( 'TBE_DIR', $root );
require __DIR__ . '/../inc/dev-files.php';
foreach ( array( '.git/objects', 'tools/.cache', 'data/esv', 'inc', '.well-known' ) as $d ) {
	mkdir( "$root/$d", 0755, true );
}
foreach ( array( '.git/objects/ab', '.gitignore', '.htaccess', 'tools/test_x.php', 'tools/.htaccess', 'data/esv/john.json', 'inc/x.php', 'README.md' ) as $f ) {
	file_put_contents( "$root/$f", 'x' );
}
$list = array_map( 'basename', tbe_dev_entries() );
sort( $list );
check( array( '.git', '.gitignore', 'tools' ) === $list, 'the dot entries and tools are listed; .htaccess and .well-known are not' );
$GLOBALS['t']['env'] = 'development';
check( array() === tbe_dev_entries(), 'a development site keeps them' );
$GLOBALS['t']['env'] = 'local';
check( array() === tbe_dev_entries(), 'so does a local one' );
$GLOBALS['t']['env'] = 'production';
class T_DB {
	public $options = 'wp_options';
	public function prepare( $sql, ...$args ) {
		return $args;
	}
	public function get_var( $args ) {
		return $GLOBALS['t']['options'][ $args[0] ] ?? null;
	}
	public function query( $args ) {
		$o = &$GLOBALS['t']['options'];
		if ( 2 === count( $args ) ) {
			list( $name, $held ) = $args;
			if ( ! array_key_exists( $name, $o ) || (string) $o[ $name ] !== $held ) {
				return 0;
			}
			unset( $o[ $name ] );
			return 1;
		}
		list( $next, $name, $held ) = $args;
		if ( ! array_key_exists( $name, $o ) || (string) $o[ $name ] !== $held ) {
			return 0;
		}
		$o[ $name ] = $next;
		return 1;
	}
}
$GLOBALS['wpdb'] = new T_DB();
$GLOBALS['t']['options']['tbe_dev_files_lock'] = time() . '.111111';
tbe_cleanup_on_version_change();
check( file_exists( "$root/.git" ) && ! isset( $GLOBALS['t']['options']['tbe_dev_files_version'] ), 'a fresh lease held by another request keeps this one out' );
$stale = ( time() - 600 ) . '.123456';
$GLOBALS['t']['options']['tbe_dev_files_lock'] = $stale;
check( tbe_swap_lock( 'tbe_dev_files_lock', $stale, time() . '.1' ) && ! tbe_swap_lock( 'tbe_dev_files_lock', $stale, time() . '.2' ), 'of two requests that read the same stale lease, one wins' );
$GLOBALS['t']['options']['tbe_dev_files_lock'] = $stale;
tbe_cleanup_on_version_change();
check( ! file_exists( "$root/.git" ) && ! file_exists( "$root/.gitignore" ) && ! file_exists( "$root/tools" ), 'a stale lease is taken over and the development files go' );
check( file_exists( "$root/data/esv/john.json" ) && file_exists( "$root/inc/x.php" ) && file_exists( "$root/.htaccess" ) && file_exists( "$root/README.md" ) && is_dir( "$root/.well-known" ), 'data, the code, README.md and the server files stay' );
check( '9.9.9' === ( $GLOBALS['t']['options']['tbe_dev_files_version'] ?? '' ) && ! isset( $GLOBALS['t']['options']['tbe_dev_files_lock'] ), 'the version is recorded and the lease freed' );
mkdir( "$root/tools" );
tbe_cleanup_on_version_change();
check( is_dir( "$root/tools" ), 'once recorded, it does not run again' );
$GLOBALS['tbe_cleanup_lease']                  = ( time() - 120 ) . '.222222';
$GLOBALS['t']['options']['tbe_dev_files_lock'] = $GLOBALS['tbe_cleanup_lease'];
tbe_renew_cleanup_lease();
check( time() - (int) $GLOBALS['t']['options']['tbe_dev_files_lock'] < 60, 'a running clean-up renews its lease' );
unset( $GLOBALS['tbe_cleanup_lease'], $GLOBALS['t']['options']['tbe_dev_files_lock'] );
$outside = sys_get_temp_dir() . '/tbe-live-target-' . getmypid();
file_put_contents( $outside, 'x' );
chmod( $outside, 0600 );
symlink( $outside, "$root/.link" );
mkdir( "$root/inner" );
symlink( $outside, "$root/inner/link" );
tbe_remove_tree( "$root/.link" );
tbe_remove_tree( "$root/inner" );
clearstatcache();
check( ! is_link( "$root/.link" ) && ! file_exists( "$root/inner" ) && 0600 === ( fileperms( $outside ) & 0777 ), 'a link is removed without touching its target' );
unlink( $outside );
tbe_remove_tree( $root );
check( ! file_exists( $root ), 'the test folder is gone' );

// The incomplete-upload guard.
$fn = (string) file_get_contents( __DIR__ . '/../functions.php' );
foreach ( array( 'tbe_theme_files', 'tbe_theme_support_files', 'tbe_incomplete_is_login', 'tbe_incomplete_serves', 'tbe_incomplete_theme' ) as $name ) {
	preg_match( '/^function ' . $name . '\(.*?^}\n/ms', $fn, $m ) || exit( "not found: $name\n" );
	eval( $m[0] ); // phpcs:ignore Squiz.PHP.Eval
}
$listed = array_merge( tbe_theme_files(), tbe_theme_support_files() );
$miss   = array_diff( array_map( static fn( $f ) => basename( $f, '.php' ), glob( __DIR__ . '/../inc/*.php' ) ), $listed );
check( array() === array_values( $miss ), 'every file in inc/ is checked before any loads' . ( $miss ? ': missing ' . implode( ', ', $miss ) : '' ) );
check( false !== strpos( $fn, 'catch ( Throwable $tbe_error )' ) && false !== strpos( $fn, "header( 'Retry-After: 300' );" ), 'a half-written file is caught, and the page carries Retry-After' );
tbe_incomplete_theme( 'inc/bible.php is missing' );
tbe_incomplete_theme( 'inc/bible.php is missing' );
tbe_incomplete_theme( 'inc/reader.php is missing' );
$lines = file_exists( $log ) ? count( preg_grep( '/incomplete or being uploaded/', file( $log ) ) ) : 0;
check( 2 === $lines, 'each problem is logged once' );
$GLOBALS['t']['admin'] = true;
check( ! tbe_incomplete_serves( false, 'apache2handler' ) && tbe_incomplete_serves( true, 'apache2handler' ), 'a missing file leaves the admin alone; a broken one does not' );
$GLOBALS['t']['admin'] = false;
check( tbe_incomplete_serves( false, 'fpm-fcgi' ) && ! tbe_incomplete_serves( true, 'cli' ), 'a visitor gets the page; the command line never does' );
$GLOBALS['t']['ajax'] = true;
check( ! tbe_incomplete_serves( true, 'apache2handler' ), 'nor does AJAX' );
$GLOBALS['t']['ajax']   = false;
$_SERVER['REQUEST_URI'] = '/wp-login.php';
check( ! tbe_incomplete_serves( true, 'apache2handler' ), 'nor the login screen' );
$GLOBALS['t']['options']['whl_page'] = 'gate';
$_SERVER['REQUEST_URI']              = '/gate/';
check( ! tbe_incomplete_serves( true, 'apache2handler' ), 'nor the WPS Hide Login address' );
$_SERVER['REQUEST_URI'] = '/gateway/';
check( tbe_incomplete_serves( true, 'apache2handler' ), 'but a page that only starts like it does' );
unset( $_SERVER['REQUEST_URI'] );
@unlink( $log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

// Every script in tools/ exits outside the command line.
foreach ( glob( __DIR__ . '/*.php' ) as $f ) {
	check( false !== strpos( (string) file_get_contents( $f, false, null, 0, 2000 ), "if ( 'cli' !== PHP_SAPI ) {" ), basename( $f ) . ' has the CLI guard' );
}

echo $fail ? "$fail failed\n" : "all passed\n";
exit( $fail ? 1 : 0 );
