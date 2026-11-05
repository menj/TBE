<?php
/**
 * Hardening from the live logs of November 2026.
 *
 *  - The REST user list (/wp/v2/users and /wp/v2/users/<id>, with a Gravatar hash of each account's email)
 *    is refused to visitors who are not logged in, and ?author=N, which WordPress answers with a redirect
 *    to the account's archive address, goes to the home page instead. Both told a script which login names
 *    exist.
 *  - XML-RPC is off: password-guessing scripts use it to try many passwords in one request. A site that
 *    needs it (Jetpack, an old mobile app) adds define( 'TBE_ALLOW_XMLRPC', true ); to wp-config.php; it
 *    also stays on while the Jetpack plugin is active.
 *  - The theme does not write .htaccess. The Error pages tab shows the lines that deny xmlrpc.php and the
 *    WordPress files bots ask for by address (tbe_security_htaccess_snippet()).
 *  - Scanner probes get a short plain 404 (0.10.6). The logs show hundreds of requests a day for PHP files the
 *    site never had (phpinfo.php, info.php, test.php), for .env and .git files and for /_environment. Each one
 *    rendered the full theme 404 page, about 74 KB and every query of a page view. The probe is now answered
 *    on parse_request at priority 0, before the REST API, the query and the template. WordPress's entry points,
 *    the admin, the REST API, .well-known and any path with a real file behind it are never caught.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** Whether XML-RPC stays on: TBE_ALLOW_XMLRPC in wp-config.php, or the Jetpack plugin, which needs it. */
function tbe_xmlrpc_allowed(): bool {
	return ( defined( 'TBE_ALLOW_XMLRPC' ) && TBE_ALLOW_XMLRPC ) || class_exists( 'Jetpack' ) || defined( 'JETPACK__VERSION' );
}

/**
 * Remove the user routes from the REST API for visitors. Anyone logged in keeps them: the block editor reads
 * /wp/v2/users/me and the author list, and core already limits what each role may see there.
 *
 * @param array $endpoints Registered routes.
 * @return array
 */
function tbe_hide_rest_users( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}
	foreach ( array_keys( (array) $endpoints ) as $route ) {
		if ( preg_match( '#^/wp/v2/users(/|$)#', (string) $route ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'tbe_hide_rest_users' );

/** Whether a request's query arguments ask for an author by number or name: ?author=1, ?author_name=x. */
function tbe_is_author_query( array $query ): bool {
	return isset( $query['author'] ) || isset( $query['author_name'] );
}

/** ?author=N goes to the home page with a 301, before WordPress's canonical redirect reveals the account. */
function tbe_block_author_query(): void {
	if ( is_admin() || ! tbe_is_author_query( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	wp_safe_redirect( home_url( '/' ), 301 );
	exit;
}
add_action( 'template_redirect', 'tbe_block_author_query', 1 );

/** No users sitemap: it lists every account's archive address. */
add_filter(
	'wp_sitemaps_add_provider',
	static function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	10,
	2
);

/* XML-RPC ----------------------------------------------------------------- */

add_filter(
	'xmlrpc_enabled',
	static function ( $enabled ) {
		return tbe_xmlrpc_allowed() ? $enabled : false;
	}
);

add_filter(
	'xmlrpc_methods',
	static function ( $methods ) {
		return tbe_xmlrpc_allowed() ? $methods : array();
	},
	999
);

add_filter(
	'wp_headers',
	static function ( $headers ) {
		if ( ! tbe_xmlrpc_allowed() ) {
			unset( $headers['X-Pingback'], $headers['x-pingback'] );
		}
		return $headers;
	}
);

add_action(
	'wp',
	static function () {
		if ( ! tbe_xmlrpc_allowed() ) {
			remove_action( 'wp_head', 'rsd_link' );
		}
	}
);

/** With XML-RPC off, xmlrpc.php answers 403 before WordPress reads the call. */
function tbe_xmlrpc_gate(): void {
	if ( ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || tbe_xmlrpc_allowed() ) {
		return;
	}
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo 'XML-RPC is disabled on this site.';
	exit;
}
add_action( 'after_setup_theme', 'tbe_xmlrpc_gate', 1 );

/* Lines for .htaccess ----------------------------------------------------- */

/**
 * WordPress's folder relative to the folder that holds .htaccess: '' when they are the same, "wp/" when
 * WordPress sits in its own subfolder, null when WordPress is outside it.
 */
function tbe_core_prefix( string $home, string $abs ): ?string {
	$home = rtrim( str_replace( '\\', '/', $home ), '/' ) . '/';
	$abs  = rtrim( str_replace( '\\', '/', $abs ), '/' ) . '/';
	return 0 === strpos( $abs, $home ) ? (string) substr( $abs, strlen( $home ) ) : null;
}

/**
 * Lines that deny WordPress's own files that are never meant to be asked for by address: the PHP files in
 * wp-includes (bots ask for blocks/rss.php and the like, which then fail with a fatal error), wp-settings.php,
 * wp-config-sample.php, readme.html (which names the version) and wp-admin/install.php. The editor's TinyMCE
 * loader and ms-files.php, which a network's rewrite rules hand uploads to, stay reachable.
 *
 * @return string[]
 */
function tbe_core_lines( string $prefix = '' ): array {
	$p = str_replace( ' ', '\\ ', preg_quote( $prefix, '#' ) );
	return array(
		'<IfModule mod_rewrite.c>',
		'RewriteEngine On',
		'RewriteRule ^' . $p . 'wp-includes/(?!js/tinymce/|ms-files\\.php$).+\\.php$ - [F,L]',
		'RewriteRule ^' . $p . '(wp-settings|wp-config-sample)\\.php$ - [F,L]',
		'RewriteRule ^' . $p . 'readme\\.html$ - [F,L]',
		'RewriteRule ^' . $p . 'wp-admin/install\\.php$ - [F,L]',
		'</IfModule>',
	);
}

/** Lines that deny xmlrpc.php at the web server, for Apache 2.4 and 2.2. */
function tbe_xmlrpc_lines(): array {
	return array(
		'<Files xmlrpc.php>',
		'<IfModule mod_authz_core.c>',
		'Require all denied',
		'</IfModule>',
		'<IfModule !mod_authz_core.c>',
		'Order Deny,Allow',
		'Deny from all',
		'</IfModule>',
		'</Files>',
	);
}

/**
 * The lines to paste into the site's .htaccess: the xmlrpc.php block (left out while XML-RPC is allowed) and
 * the core-file block, with WordPress's own folder in front when it sits in a subfolder.
 *
 * @param string|null $prefix WordPress's folder relative to the .htaccess folder; worked out when null.
 *                            Without a folder the core block is left out.
 */
function tbe_security_htaccess_snippet( ?string $prefix = null ): string {
	if ( null === $prefix ) {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		// Null again when WordPress sits outside the .htaccess folder: the core block then has nothing to name.
		$prefix = tbe_core_prefix( get_home_path(), ABSPATH );
	}
	$lines = array();
	if ( ! tbe_xmlrpc_allowed() ) {
		$lines[] = '# BEGIN Block xmlrpc.php';
		$lines   = array_merge( $lines, tbe_xmlrpc_lines() );
		$lines[] = '# END Block xmlrpc.php';
	}
	if ( null !== $prefix ) {
		$lines[] = '# BEGIN TBE: deny direct core files';
		$lines   = array_merge( $lines, tbe_core_lines( $prefix ) );
		$lines[] = '# END TBE: deny direct core files';
	}
	return implode( "\n", $lines );
}

/* Scanner probes ---------------------------------------------------------- */

/** WordPress's own PHP entry points at the top of its folder, exactly as WordPress names them. */
function tbe_core_entry_points(): array {
	return array( 'index.php', 'wp-login.php', 'wp-cron.php', 'wp-comments-post.php', 'wp-signup.php', 'wp-activate.php', 'wp-trackback.php', 'wp-mail.php', 'wp-links-opml.php', 'xmlrpc.php', 'wp-blog-header.php', 'wp-load.php' );
}

/**
 * Whether a request path, below the home address, is a scanner probe: a dot file or folder (.env, .git/config,
 * .aws/credentials) anywhere in the path; a PHP file, or a backup of one (x.php~, x.php.bak), that is not one of
 * WordPress's entry points or in wp-admin; or a name no page of the site carries: wp-config, phpinfo and
 * _environment, bare or with an extension. A path through .well-known is never a probe.
 *
 * @param string $path Request path without the home address and the query.
 */
function tbe_is_probe( string $path ): bool {
	$path = ltrim( rawurldecode( $path ), '/' );
	// .well-known is for certificate and other checks: never caught, whatever is in it.
	if ( '' === $path || preg_match( '#(^|/)\.well-known(/|$)#i', $path ) ) {
		return false;
	}
	if ( preg_match( '#(^|/)\.[^/]#', $path ) ) {
		return true;
	}
	if ( preg_match( '#(^|/)(wp-config|phpinfo|_environment)(\.[^/]*|~)?(/|$)#i', $path ) ) {
		return true;
	}
	if ( ! preg_match( '#\.(php[0-9]?|phtml|phar)(/|$|~|\.(bak|old|orig|save|swp|txt|dist)$)#i', $path ) ) {
		return false;
	}
	// Case matters: /WP-LOGIN.PHP is no entry point on a case-sensitive server.
	if ( in_array( strtok( $path, '/' ), tbe_core_entry_points(), true ) || 0 === strpos( $path, 'wp-admin/' ) ) {
		return false;
	}
	return true;
}

/**
 * The request path below the home address, without the query; null when the request is outside the site.
 *
 * @param string $uri  The request URI.
 * @param string $home The home address's path.
 */
function tbe_probe_path( string $uri, string $home ): ?string {
	$path = (string) parse_url( $uri, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$home = '/' . trim( $home, '/' );
	if ( '/' !== $home ) {
		if ( $path !== $home && 0 !== strpos( $path, $home . '/' ) ) {
			return null;
		}
		$path = (string) substr( $path, strlen( $home ) );
	}
	return $path;
}

/**
 * Whether the path has a real file behind it, under the document root or WordPress's folder: a plugin's own PHP
 * endpoint that loads WordPress runs as before.
 *
 * @param string $full The request path as sent, home address included.
 * @param string $path The same path below the home address.
 */
function tbe_probe_has_file( string $full, string $path ): bool {
	$root  = rtrim( (string) ( $_SERVER['DOCUMENT_ROOT'] ?? '' ), '/\\' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$paths = array( rtrim( ABSPATH, '/\\' ) . '/' . ltrim( rawurldecode( $path ), '/' ) );
	if ( '' !== $root ) {
		$paths[] = $root . '/' . ltrim( rawurldecode( $full ), '/' );
	}
	foreach ( $paths as $file ) {
		if ( false === strpos( $file, "\0" ) && file_exists( $file ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Answer a probe with a short plain 404 and stop, before the REST API (parse_request at 10), the query and the
 * template. The admin, AJAX, cron, the command line, the login screen and REST routes are never caught.
 *
 * @param object|null $wp The WP object.
 */
function tbe_short_circuit_probe( $wp = null ): void {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || 'cli' === PHP_SAPI || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	if ( is_object( $wp ) && ! empty( $wp->query_vars['rest_route'] ) ) {
		return;
	}
	if ( function_exists( 'tbe_incomplete_is_login' ) && tbe_incomplete_is_login() ) {
		return;
	}
	$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = tbe_probe_path( $uri, (string) parse_url( home_url( '/' ), PHP_URL_PATH ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( null === $path || ! tbe_is_probe( $path ) || tbe_probe_has_file( (string) parse_url( $uri, PHP_URL_PATH ), $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return;
	}
	tbe_send_probe_404();
}
add_action( 'parse_request', 'tbe_short_circuit_probe', 0 );

/** The short 404 itself: ten bytes of plain text, never cached and never indexed. */
function tbe_send_probe_404(): void {
	if ( ! headers_sent() ) {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}
	echo 'Not found.';
	exit;
}
