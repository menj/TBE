<?php
/**
 * Search addresses (0.8.0): the behaviour of the Pretty Search Permalinks plugin, built in.
 *
 * /?s=term answers with a 301 to /search/term/, and /?s=term&paged=2 to /search/term/page/2/.
 * The base ("search") is set under Settings › Permalinks, in the Optional section, as in the
 * plugin, and kept in the option tbe_search_base. An empty search is never redirected.
 * WordPress already has the rewrite rules for the base; the theme sets the base on init and
 * flushes the rules only when the theme version changes (tbe_rewrite_version in functions.php)
 * or the base is saved on the Permalinks screen, which flushes them itself. The site's own Bible
 * search at /bible/search/ is a separate route and is not touched. While the plugin is active this file
 * does nothing.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** Whether the theme handles search addresses: off while the plugin is active. */
function tbe_search_permalinks_on() {
	return ! function_exists( 'wpseosearch_base' ) && (bool) apply_filters( 'tbe_search_permalinks_enabled', true );
}

/** The search base: lower-case letters, digits, hyphens and underscores; "search" when empty. */
function tbe_search_base() {
	$base = sanitize_key( (string) get_option( 'tbe_search_base', 'search' ) );
	return '' === $base ? 'search' : $base;
}

/**
 * The pretty address of a search, or '' when there is nothing to redirect: an empty term.
 *
 * @param string $home  Home URL with a trailing slash.
 * @param string $base  Search base.
 * @param string $term  Search term, unslashed.
 * @param int    $paged Page number.
 * @param array  $extra Other query arguments to carry over.
 * @param bool   $slash Whether the permalink structure ends in a slash.
 * @return string
 */
function tbe_search_pretty_url( $home, $base, $term, $paged = 1, $extra = array(), $slash = true ) {
	$term = trim( (string) $term );
	if ( '' === $term ) {
		return '';
	}
	// A slash stays a slash, as in get_search_link(): many servers refuse %2F in a path, and the rule search/(.+)/ takes it whole.
	$path = $base . '/' . str_replace( '%2F', '/', rawurlencode( $term ) );
	if ( $paged > 1 ) {
		$path .= '/page/' . (int) $paged;
	}
	$url = rtrim( $home, '/' ) . '/' . $path . ( $slash ? '/' : '' );
	return $extra ? $url . '?' . http_build_query( $extra, '', '&', PHP_QUERY_RFC3986 ) : $url;
}

/**
 * The other query arguments of a search, sanitised, arrays such as post_type[] kept whole.
 *
 * @param array $args Unslashed arguments.
 * @return array
 */
function tbe_search_clean_args( $args ) {
	$out = array();
	foreach ( (array) $args as $key => $value ) {
		if ( is_array( $value ) ) {
			$out[ $key ] = tbe_search_clean_args( $value );
		} elseif ( is_scalar( $value ) ) {
			$out[ $key ] = sanitize_text_field( (string) $value );
		}
	}
	return $out;
}

if ( tbe_search_permalinks_on() ) {
	add_action(
		'init',
		function () {
			global $wp_rewrite;
			$wp_rewrite->search_base = tbe_search_base();
		},
		1
	);

	// The field under Settings › Permalinks › Optional; saved with the screen's own nonce.
	add_action(
		'load-options-permalink.php',
		function () {
			if ( isset( $_POST['tbe_search_base'] ) && current_user_can( 'manage_options' ) ) {
				check_admin_referer( 'update-permalink' );
				$base = sanitize_key( wp_unslash( $_POST['tbe_search_base'] ) );
				update_option( 'tbe_search_base', '' === $base ? 'search' : $base );
				global $wp_rewrite;
				$wp_rewrite->search_base = tbe_search_base();
			}
			add_settings_field(
				'tbe_search_base',
				__( 'Search base', 'tbe-child' ),
				function () {
					printf(
						'<input type="text" name="tbe_search_base" id="tbe_search_base" value="%1$s" class="regular-text code" aria-describedby="tbe-search-base-help"><p class="description" id="tbe-search-base-help">%2$s</p>',
						esc_attr( tbe_search_base() ),
						esc_html__( 'Site search results open at /search/term/ by default. Letters, digits and hyphens only; empty keeps "search".', 'tbe-child' )
					);
				},
				'permalink',
				'optional',
				array( 'label_for' => 'tbe_search_base' )
			);
		}
	);

	// /?s=term to /search/term/ with a 301, the page number and any other query arguments kept.
	add_action(
		'template_redirect',
		function () {
			global $wp_rewrite;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public search.
			if ( ! isset( $_GET['s'] ) || ! is_search() || is_admin() || is_feed() || ! $wp_rewrite->using_permalinks() ) {
				return;
			}
			$extra = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $extra['s'], $extra['paged'] );
			$url = tbe_search_pretty_url(
				home_url( '/' ),
				tbe_search_base(),
				(string) get_query_var( 's' ),
				max( 1, (int) get_query_var( 'paged' ) ),
				tbe_search_clean_args( $extra ),
				'/' === substr( (string) get_option( 'permalink_structure' ), -1 )
			);
			if ( '' !== $url ) {
				wp_safe_redirect( $url, 301 );
				exit;
			}
		}
	);
}
