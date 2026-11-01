<?php
/**
 * Pretty URLs for the reader: /bible/, /bible/john/, /bible/john/3/
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		add_rewrite_rule( '^bible/?$', 'index.php?tbe_view=books', 'top' );
		add_rewrite_rule( '^bible/search/?$', 'index.php?tbe_view=search', 'top' );
		add_rewrite_rule( '^credits/?$', 'index.php?tbe_view=credits', 'top' );
		add_rewrite_rule( '^bible/index/?$', 'index.php?tbe_view=index', 'top' );
		add_rewrite_rule( '^bible/interlinear/?$', 'index.php?tbe_view=interlinear-index', 'top' );
		add_rewrite_rule( '^bible/([a-z0-9-]+)/?$', 'index.php?tbe_view=book&tbe_book=$matches[1]', 'top' );
		add_rewrite_rule( '^bible/([a-z0-9-]+)/([0-9]+)/interlinear/?$', 'index.php?tbe_view=interlinear&tbe_book=$matches[1]&tbe_chapter=$matches[2]', 'top' );
		add_rewrite_rule( '^bible/([a-z0-9-]+)/([0-9]+)/?$', 'index.php?tbe_view=chapter&tbe_book=$matches[1]&tbe_chapter=$matches[2]', 'top' );
	}
);

add_filter(
	'query_vars',
	static function ( array $vars ): array {
		return array_merge( $vars, array( 'tbe_view', 'tbe_book', 'tbe_chapter', 'q' ) );
	}
);

/** Which reader view is requested, validated against the real book list. */
function tbe_request(): ?array {
	static $request = false;
	if ( false !== $request ) {
		return $request;
	}
	$view = (string) get_query_var( 'tbe_view' );
	if ( '' === $view ) {
		return $request = null;
	}
	if ( 'books' === $view || 'index' === $view || 'interlinear-index' === $view || 'credits' === $view ) {
		return $request = array( 'view' => $view );
	}
	if ( 'search' === $view ) {
		return $request = array(
			'view' => 'search',
			'q'    => trim( sanitize_text_field( wp_unslash( (string) get_query_var( 'q' ) ) ) ),
			'page' => isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1, // phpcs:ignore WordPress.Security.NonceVerification
		);
	}
	$book = TBE_Bible::book( sanitize_key( (string) get_query_var( 'tbe_book' ) ) );
	if ( ! $book ) {
		return $request = array( 'view' => 'missing' );
	}
	if ( 'book' === $view ) {
		return $request = array( 'view' => 'book', 'book' => $book );
	}
	$chapter = (int) get_query_var( 'tbe_chapter' );
	if ( $chapter < 1 || $chapter > $book['chapters'] ) {
		return $request = array( 'view' => 'missing' );
	}
	return $request = array( 'view' => 'interlinear' === $view ? 'interlinear' : 'chapter', 'book' => $book, 'chapter' => $chapter );
}

/** The "go to passage or search" box: a reference jumps to the reader, anything else searches. */
add_action(
	'template_redirect',
	static function () {
		if ( empty( $_GET['tbe_ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$input  = sanitize_text_field( wp_unslash( $_GET['tbe_ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$parsed = TBE_Bible::parse_reference( $input );
		if ( $parsed ) {
			wp_safe_redirect( TBE_Bible::url( $parsed['book'], $parsed['chapter'], $parsed['vstart'] ?: null ), 302 );
		} else {
			wp_safe_redirect( add_query_arg( 'q', $input, home_url( '/bible/search/' ) ), 302 );
		}
		exit;
	},
	1
);

/** Make WordPress treat reader URLs as a normal 200 page that uses our template. */
add_action(
	'template_redirect',
	static function () {
		$request = tbe_request();
		if ( ! $request ) {
			return;
		}
		global $wp_query;
		if ( 'missing' === $request['view'] ) {
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		$wp_query->is_404  = false;
		$wp_query->is_page = false;
		$wp_query->is_home = true;
		status_header( 200 );
	},
	5
);

foreach ( array( 'home', 'frontpage', '404' ) as $tbe_type ) {
	add_filter(
		$tbe_type . '_template_hierarchy',
		static function ( array $templates ): array {
			return tbe_request() ? array_merge( array( 'tbe-reader' ), $templates ) : $templates;
		}
	);
}
unset( $tbe_type );

add_filter(
	'document_title_parts',
	static function ( array $parts ): array {
		$request = tbe_request();
		if ( ! $request ) {
			return $parts;
		}
		switch ( $request['view'] ) {
			case 'books':
				$parts['title'] = __( 'The Books of the Bible', 'tbe-child' );
				break;
			case 'search':
				$parts['title'] = '' !== $request['q'] ? sprintf( /* translators: %s: search words */ __( 'Search: %s', 'tbe-child' ), $request['q'] ) : __( 'Search', 'tbe-child' );
				break;
			case 'index':
				$parts['title'] = __( 'Index of commentary', 'tbe-child' );
				break;
			case 'credits':
				$parts['title'] = __( 'Credits and references', 'tbe-child' );
				break;
			case 'interlinear-index':
				$parts['title'] = __( 'Hebrew interlinear', 'tbe-child' );
				break;
			case 'book':
				$parts['title'] = $request['book']['name'];
				break;
			case 'chapter':
				$parts['title'] = TBE_Bible::label( $request['book']['slug'], $request['chapter'] );
				break;
			case 'interlinear':
				$parts['title'] = sprintf( /* translators: %s: passage such as Genesis 1 */ __( 'Interlinear: %s', 'tbe-child' ), TBE_Bible::label( $request['book']['slug'], $request['chapter'] ) );
				break;
			default:
				$parts['title'] = __( 'Passage not found', 'tbe-child' );
		}
		return $parts;
	}
);

add_filter(
	'body_class',
	static function ( array $classes ): array {
		$request = tbe_request();
		if ( $request ) {
			$classes[] = 'tbe-reader-page';
			$classes[] = 'tbe-view-' . $request['view'];
		}
		return $classes;
	}
);
