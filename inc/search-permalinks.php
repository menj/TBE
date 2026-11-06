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

/**
 * The first path segment of every rewrite base set on this site: the category and tag bases
 * from Settings › Permalinks (their defaults are in the fixed list), and the rewrite slug of
 * each registered post type and taxonomy.
 *
 * @return string[]
 */
function tbe_search_live_slugs() {
	$slugs = array();
	foreach ( array( 'category_base', 'tag_base' ) as $opt ) {
		$value = function_exists( 'get_option' ) ? get_option( $opt ) : '';
		if ( is_string( $value ) ) {
			$slugs[] = $value;
		}
	}
	$objects = array();
	if ( function_exists( 'get_post_types' ) ) {
		$objects = array_merge( $objects, array_values( (array) get_post_types( array(), 'objects' ) ) );
	}
	if ( function_exists( 'get_taxonomies' ) ) {
		$objects = array_merge( $objects, array_values( (array) get_taxonomies( array(), 'objects' ) ) );
	}
	foreach ( $objects as $obj ) {
		if ( is_object( $obj ) && is_array( $obj->rewrite ?? null ) && isset( $obj->rewrite['slug'] ) && is_string( $obj->rewrite['slug'] ) ) {
			$slugs[] = $obj->rewrite['slug'];
		}
	}
	$out = array();
	foreach ( $slugs as $slug ) {
		$first = strtolower( (string) strtok( trim( $slug, '/' ), '/' ) );
		if ( '' !== $first ) {
			$out[] = $first;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Slugs the search base may not take: the live rewrite slugs, the theme's own routes (the Bible reader and its
 * interlinear, credits, commentary and its sections, the glossary), which have rewrite rules
 * of higher priority, and the paths WordPress keeps for itself. Filter tbe_search_reserved_bases.
 *
 * @return string[]
 */
function tbe_search_reserved_bases() {
	return (array) apply_filters(
		'tbe_search_reserved_bases',
		array_merge(
			tbe_search_live_slugs(),
			array( 'bible', 'interlinear', 'credits', 'sources', 'commentary', 'section', 'glossary', 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'page', 'comments', 'category', 'tag', 'author', 'embed', 'trackback', 'attachment', 'type' )
		)
	);
}

/**
 * A base as it is stored: lower-case letters, digits and hyphens. Empty or reserved gives ''.
 *
 * @param mixed $raw Submitted or stored value.
 * @return string
 */
function tbe_search_clean_base( $raw ) {
	if ( ! is_string( $raw ) ) {
		return '';
	}
	$base = str_replace( '_', '', sanitize_key( $raw ) );
	if ( in_array( $base, tbe_search_reserved_bases(), true ) ) {
		return '';
	}
	// A page whose address is the base would lose its children to the search rule.
	return ( function_exists( 'get_page_by_path' ) && get_page_by_path( $base ) ) ? '' : $base;
}

/** The search base; "search" when the stored value is empty or reserved. */
function tbe_search_base() {
	$base = tbe_search_clean_base( get_option( 'tbe_search_base', 'search' ) );
	return '' === $base ? tbe_search_fallback_base() : $base;
}

/**
 * The base used when the chosen one is empty or refused: the first of search, find and
 * search-results that is free (a post type, a taxonomy or a page may take "search" itself),
 * else search-results-2, search-results-3 and on.
 *
 * @return string
 */
function tbe_search_fallback_base() {
	foreach ( array( 'search', 'find', 'search-results' ) as $candidate ) {
		if ( '' !== tbe_search_clean_base( $candidate ) ) {
			return $candidate;
		}
	}
	// All three are taken: number the last until one is free.
	for ( $n = 2; $n < 100; $n++ ) {
		if ( '' !== tbe_search_clean_base( 'search-results-' . $n ) ) {
			return 'search-results-' . $n;
		}
	}
	return 'search-results-' . substr( md5( home_url( '/' ) ), 0, 8 ); // Stable per site, so rules and links agree.
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

	// The saved rules carry the base they were built with. When the base in force differs (a
	// stored base newly refused, or one changed outside the Permalinks screen), flush once.
	add_action(
		'init',
		function () {
			// The base can change here: a post type registered on init after priority 1 may have
			// taken it. Set it again on every load, so links and any flush use the base in force.
			global $wp_rewrite;
			$base                    = tbe_search_base();
			$wp_rewrite->search_base = $base;
			if ( get_option( 'tbe_search_rules_base' ) !== $base ) {
				flush_rewrite_rules( false );
				update_option( 'tbe_search_rules_base', $base, false );
			}
		},
		99
	);

	// The field under Settings › Permalinks › Optional; saved with the screen's own nonce.
	add_action(
		'load-options-permalink.php',
		function () {
			if ( isset( $_POST['tbe_search_base'] ) && current_user_can( 'manage_options' ) ) {
				check_admin_referer( 'update-permalink' );
				// A posted array (tbe_search_base[]=x) would make sanitize_key() throw on PHP 8.
				$posted = wp_unslash( $_POST['tbe_search_base'] );
				$raw    = is_string( $posted ) ? sanitize_key( $posted ) : '';
				$base = tbe_search_clean_base( $raw );
				if ( '' !== $raw && '' === $base ) {
					add_settings_error(
						'tbe_search_base',
						'tbe_search_base_reserved',
						/* translators: 1: the refused base, 2: the base used instead */
						sprintf( __( 'The search base "%1$s" is taken by another part of the site, so "%2$s" is used instead.', 'tbe-child' ), str_replace( '_', '', $raw ), tbe_search_fallback_base() ),
						'warning'
					);
					// The Permalinks screen redirects after saving; settings_errors() reads this transient there.
					set_transient( 'settings_errors', get_settings_errors(), 30 );
				}
				update_option( 'tbe_search_base', '' === $base ? tbe_search_fallback_base() : $base );
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
						esc_html__( 'Site search results open at /search/term/ by default. Lower-case letters, digits and hyphens; anything else is removed. Empty, or a word another part of the site uses (bible, commentary, glossary and the like), keeps "search".', 'tbe-child' )
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
