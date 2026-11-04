<?php
/**
 * Unlisted posts (0.8.0): the behaviour of the Unlist Posts & Pages plugin, built in.
 *
 * An unlisted post, page or public custom post keeps its address and opens for anyone who has
 * it. Every listing leaves it out: the front page and archives, feeds, search, the core and
 * Rank Math sitemaps, the image sitemap, wp_list_pages(), the page-list block, the previous and
 * next links, the "Read next" row and REST collections. Its head carries noindex, through Rank
 * Math's robots filter when Rank Math is active.
 *
 * The IDs are kept in one option, tbe_unlisted, as the plugin keeps them in unlist_posts;
 * a site that ran the plugin has its list copied over once. While the plugin is active this file
 * does nothing, so the two never run twice. Filter tbe_unlist_enabled to switch it off.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

const TBE_UNLISTED = 'tbe_unlisted';

/** Whether the theme's unlisting runs: off while the plugin is active, or when the filter says so. */
function tbe_unlist_on() {
	if ( class_exists( 'Unlist_Posts' ) || defined( 'UNLIST_POSTS_VER' ) ) {
		return false;
	}
	return (bool) apply_filters( 'tbe_unlist_enabled', true );
}

/**
 * A clean list of positive, unique integer IDs from whatever is stored. Older plugin installs
 * stored an empty string, so anything that is not an array counts as empty.
 *
 * @param mixed $stored Stored value.
 * @return int[]
 */
function tbe_unlist_normalise( $stored ) {
	if ( ! is_array( $stored ) ) {
		return array();
	}
	$ids = array_filter(
		array_map( 'intval', $stored ),
		function ( $id ) {
			return $id > 0;
		}
	);
	return array_values( array_unique( $ids ) );
}

/** The unlisted IDs. The plugin's list is copied in the first time, when the theme has none. */
function tbe_unlisted_ids() {
	$stored = get_option( TBE_UNLISTED, null );
	if ( null === $stored ) {
		$stored = tbe_unlist_normalise( get_option( 'unlist_posts', array() ) );
		update_option( TBE_UNLISTED, $stored, true );
	}
	return tbe_unlist_normalise( $stored );
}

/**
 * Whether a post is unlisted.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function tbe_is_unlisted( $post_id ) {
	return (int) $post_id > 0 && in_array( (int) $post_id, tbe_unlisted_ids(), true );
}

/**
 * Add a post to the list or take it off.
 *
 * @param int  $post_id Post ID.
 * @param bool $unlist  True to unlist.
 */
function tbe_set_unlisted( $post_id, $unlist ) {
	$post_id = (int) $post_id;
	$ids     = tbe_unlisted_ids();
	if ( $unlist ) {
		$ids[] = $post_id;
	} else {
		$ids = array_diff( $ids, array( $post_id ) );
	}
	update_option( TBE_UNLISTED, tbe_unlist_normalise( $ids ), true );
}

/** The post types the feature covers: every public one, as in the plugin. */
function tbe_unlist_post_types() {
	return get_post_types( array( 'public' => true ), 'names', 'and' );
}

/**
 * Whether an AJAX request came from an admin screen of this site, from someone who can edit
 * posts. Such requests see unlisted posts, as in the plugin (LearnDash and the like).
 */
function tbe_unlist_admin_referer() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return false;
	}
	$referer = wp_get_referer();
	if ( ! $referer ) {
		return false;
	}
	$ref   = wp_parse_url( $referer );
	$admin = wp_parse_url( admin_url() );
	if ( ! is_array( $ref ) || ! is_array( $admin ) || empty( $ref['host'] ) || empty( $admin['host'] ) || empty( $ref['path'] ) || empty( $admin['path'] ) ) {
		return false;
	}
	if ( strtolower( $ref['host'] ) !== strtolower( $admin['host'] ) ) {
		return false;
	}
	return 0 === strpos( trailingslashit( $ref['path'] ), trailingslashit( $admin['path'] ) );
}

/** Whether the request is one in which listings keep unlisted posts: an admin screen or an admin AJAX call. */
function tbe_unlist_admin_context() {
	return is_admin() && ( ! wp_doing_ajax() || tbe_unlist_admin_referer() );
}

/**
 * The SQL fragment that leaves the unlisted IDs out.
 *
 * @param string $column Column, such as wp_posts.ID or p.ID.
 * @param int[]  $ids    The IDs.
 * @return string
 */
function tbe_unlist_sql( $column, $ids ) {
	$ids = tbe_unlist_normalise( $ids );
	return $ids ? ' AND ' . $column . ' NOT IN ( ' . implode( ', ', $ids ) . ' ) ' : '';
}

/**
 * posts_where: every query that is not singular and not from the admin leaves them out. That
 * covers the home page, archives, feeds, search, REST collections, Query Loop blocks and the
 * core sitemaps.
 *
 * @param string   $where SQL.
 * @param WP_Query $query Query.
 * @return string
 */
function tbe_unlist_where( $where, $query ) {
	$ids = tbe_unlisted_ids();
	if ( ! $ids || tbe_unlist_admin_context() || ! empty( $query->is_singular ) ) {
		return $where;
	}
	global $wpdb;
	return $where . tbe_unlist_sql( $wpdb->posts . '.ID', $ids );
}

/**
 * The previous and next post links.
 *
 * @param string $where SQL with the posts table aliased p.
 * @return string
 */
function tbe_unlist_adjacent( $where ) {
	$ids = tbe_unlisted_ids();
	return ( ! $ids || tbe_unlist_admin_context() ) ? $where : $where . tbe_unlist_sql( 'p.ID', $ids );
}

/** The queried post when it is unlisted, for the robots filters. */
function tbe_unlist_current() {
	$id = is_singular() ? get_queried_object_id() : 0;
	return $id && tbe_is_unlisted( $id );
}

if ( tbe_unlist_on() ) {
	add_action(
		'init',
		function () {
			add_filter( 'posts_where', 'tbe_unlist_where', 20, 2 );
			add_filter( 'get_next_post_where', 'tbe_unlist_adjacent', 20 );
			add_filter( 'get_previous_post_where', 'tbe_unlist_adjacent', 20 );
		}
	);

	// noindex on the unlisted post itself; Yoast's own robots array is emptied so it cannot undo it.
	add_filter(
		'wp_robots',
		function ( $robots ) {
			if ( ! tbe_unlist_current() ) {
				return $robots;
			}
			add_filter( 'wpseo_robots_array', '__return_empty_array' );
			return wp_robots_no_robots( $robots );
		}
	);
	add_filter(
		'rank_math/frontend/robots',
		function ( $robots ) {
			if ( tbe_unlist_current() ) {
				$robots['index'] = 'noindex';
			}
			return $robots;
		}
	);

	add_filter(
		'wp_list_pages_excludes',
		function ( $exclude ) {
			return array_merge( (array) $exclude, tbe_unlisted_ids() );
		}
	);

	// The page-list block and anything else built on get_pages(), which runs its own SQL.
	add_filter(
		'get_pages',
		function ( $pages ) {
			if ( tbe_unlist_admin_context() || ! tbe_unlisted_ids() ) {
				return $pages;
			}
			return array_values(
				array_filter(
					(array) $pages,
					function ( $p ) {
						return ! ( $p instanceof WP_Post && tbe_is_unlisted( $p->ID ) );
					}
				)
			);
		}
	);

	// Core sitemaps, stated as well as filtered by posts_where.
	add_filter(
		'wp_sitemaps_posts_query_args',
		function ( $args ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), tbe_unlisted_ids() );
			return $args;
		}
	);

	// Rank Math's sitemap runs its own SQL, so each entry is checked, and the exclusion list is given too.
	add_filter(
		'rank_math/sitemap/entry',
		function ( $url, $type, $object ) {
			return ( 'post' === $type && is_object( $object ) && isset( $object->ID ) && tbe_is_unlisted( $object->ID ) ) ? false : $url;
		},
		10,
		3
	);
	add_filter(
		'rank_math/sitemap/posts_to_exclude',
		function ( $ids ) {
			return array_merge( (array) $ids, tbe_unlisted_ids() );
		}
	);

	// get_posts() suppresses posts_where, so listings built on it (the commentary index, the
	// latest notes in the structured data) get the IDs as post__not_in as well.
	add_action(
		'pre_get_posts',
		function ( $query ) {
			$ids = tbe_unlisted_ids();
			if ( ! $ids || tbe_unlist_admin_context() || $query->is_singular() || $query->get( 'p' ) || $query->get( 'page_id' ) || $query->get( 'name' ) || $query->get( 'pagename' ) ) {
				return;
			}
			$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $ids ) );
		}
	);

	if ( is_admin() ) {
		require_once __DIR__ . '/unlist-admin.php';
	}
}
