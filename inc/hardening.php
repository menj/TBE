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
