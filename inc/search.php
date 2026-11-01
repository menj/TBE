<?php
/**
 * Search: ESV text (bundled chapters) and commentary, on one results page.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

const TBE_SEARCH_MAX_HITS = 300;
const TBE_SEARCH_PER_PAGE = 20;

/** Split a query into words and "quoted phrases". @return string[] */
function tbe_search_terms( string $query ): array {
	$terms = array();
	if ( preg_match_all( '/"([^"]+)"|(\S+)/u', $query, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $part ) {
			$raw  = '' !== ( $part[1] ?? '' ) ? $part[1] : ( $part[2] ?? '' );
			$term = trim( preg_replace( '/\s+/u', ' ', trim( $raw, " \t\n\r\0\x0B\"“”.,;:!?()[]" ) ) );
			if ( mb_strlen( $term, 'UTF-8' ) >= 2 ) {
				$terms[] = $term;
			}
		}
	}
	return array_slice( array_values( array_unique( $terms ) ), 0, 6 );
}

/** Regex that matches a term at the start of a word, treating ' and ’ alike. */
function tbe_term_pattern( string $term ): string {
	return '/(?<![\p{L}\p{N}])' . str_replace( "'", "['’‘]", preg_quote( $term, '/' ) ) . '/iu';
}

/**
 * Find verses containing every term. Reads the bundled chapter files in canonical order.
 *
 * @return array{hits:array<int,array{book:string,chapter:int,verse:int,text:string}>,capped:bool}
 */
function tbe_search_bible( string $query ): array {
	$terms = tbe_search_terms( $query );
	if ( ! $terms ) {
		return array( 'hits' => array(), 'capped' => false );
	}
	$key    = 'tbe_s_' . md5( mb_strtolower( implode( '|', $terms ), 'UTF-8' ) . TBE_VERSION . TBE_Bible::local_chapter_count() );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$patterns = array_map( 'tbe_term_pattern', $terms );
	// Cheap pre-filter on the raw file using the longest plain-ASCII run of the first term.
	$needle = '';
	if ( preg_match_all( '/[A-Za-z]{3,}/', $terms[0], $words ) ) {
		$needle = array_reduce( $words[0], static fn( $c, $w ) => strlen( $w ) > strlen( $c ) ? $w : $c, '' );
	}

	$hits   = array();
	$capped = false;
	foreach ( TBE_Bible::books() as $slug => $book ) {
		for ( $chapter = 1; $chapter <= $book['chapters']; $chapter++ ) {
			$file = TBE_DIR . '/data/esv/' . $slug . '/' . $chapter . '.json';
			if ( ! is_readable( $file ) ) {
				continue;
			}
			$raw = (string) file_get_contents( $file );
			if ( '' !== $needle && false === stripos( $raw, $needle ) ) {
				continue;
			}
			$data = json_decode( $raw, true );
			foreach ( (array) ( $data['verses'] ?? array() ) as $verse ) {
				$text = preg_replace( '/\s+/u', ' ', (string) $verse['t'] );
				foreach ( $patterns as $pattern ) {
					if ( ! preg_match( $pattern, $text ) ) {
						continue 2;
					}
				}
				$hits[] = array( 'book' => $slug, 'chapter' => $chapter, 'verse' => (int) $verse['n'], 'text' => $text );
				if ( count( $hits ) >= TBE_SEARCH_MAX_HITS ) {
					$capped = true;
					break 3;
				}
			}
		}
	}
	$result = array( 'hits' => $hits, 'capped' => $capped );
	set_transient( $key, $result, 6 * HOUR_IN_SECONDS );
	return $result;
}

/** Escape text and wrap every match of the terms in <mark>. */
function tbe_highlight( string $text, array $terms ): string {
	$ranges = array();
	foreach ( $terms as $term ) {
		if ( preg_match_all( tbe_term_pattern( $term ), $text, $m, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m[0] as [ $match, $offset ] ) {
				// Extend to the end of the word so "love" highlights "loved".
				$end = $offset + strlen( $match );
				if ( preg_match( '/\G[\p{L}\p{N}]*/u', $text, $tail, 0, $end ) ) {
					$end += strlen( $tail[0] );
				}
				$ranges[] = array( $offset, $end );
			}
		}
	}
	usort( $ranges, static fn( $a, $b ) => $a[0] <=> $b[0] );
	$merged = array();
	foreach ( $ranges as $r ) {
		if ( $merged && $r[0] <= end( $merged )[1] ) {
			$merged[ count( $merged ) - 1 ][1] = max( end( $merged )[1], $r[1] );
		} else {
			$merged[] = $r;
		}
	}
	$out    = '';
	$cursor = 0;
	foreach ( $merged as [ $start, $end ] ) {
		$out   .= esc_html( substr( $text, $cursor, $start - $cursor ) ) . '<mark>' . esc_html( substr( $text, $start, $end - $start ) ) . '</mark>';
		$cursor = $end;
	}
	return $out . esc_html( substr( $text, $cursor ) );
}

function tbe_view_search( array $request ): string {
	$query = (string) $request['q'];
	$page  = max( 1, (int) $request['page'] );
	$out   = '<div class="tbe-reader tbe-search-page"><h1 class="tbe-title">' . esc_html__( 'Search', 'tbe-child' ) . '</h1>';
	$out  .= tbe_search_form( $query );

	$terms = tbe_search_terms( $query );
	if ( ! $terms ) {
		return $out . '<p class="tbe-muted">' . esc_html__( 'Type a word or phrase. Put a phrase in quotes. To go straight to a passage, type a reference such as John 3:16.', 'tbe-child' ) . '</p></div>';
	}

	$have = TBE_Bible::local_chapter_count();
	$all  = TBE_Bible::total_chapters();
	if ( $have < $all ) {
		$out .= '<p class="tbe-notice" role="status">' . esc_html( sprintf( /* translators: 1: chapters searched, 2: total chapters */ __( 'The ESV text is not fully loaded yet: the search covers %1$d of %2$d chapters.', 'tbe-child' ), $have, $all ) ) . '</p>';
	}

	// Verses.
	$found  = tbe_search_bible( $query );
	$total  = count( $found['hits'] );
	$slice  = array_slice( $found['hits'], ( $page - 1 ) * TBE_SEARCH_PER_PAGE, TBE_SEARCH_PER_PAGE );
	$count  = $found['capped'] ? sprintf( /* translators: %d: number of verses */ __( '%d+ verses', 'tbe-child' ), $total ) : sprintf( _n( '%d verse', '%d verses', $total, 'tbe-child' ), $total );
	$out   .= '<section class="tbe-results"><h2 class="tbe-ornament">' . esc_html__( 'In the text', 'tbe-child' ) . '</h2><p class="tbe-muted">' . esc_html( $count ) . '</p>';
	if ( $slice ) {
		$out .= '<ol class="tbe-hits">';
		foreach ( $slice as $hit ) {
			$out .= sprintf(
				'<li><a class="tbe-hit__ref" href="%1$s">%2$s</a> <span class="tbe-hit__text tbe-scripture">%3$s</span></li>',
				esc_url( TBE_Bible::url( $hit['book'], $hit['chapter'], $hit['verse'] ) ),
				esc_html( TBE_Bible::label( $hit['book'], $hit['chapter'], $hit['verse'] ) ),
				tbe_highlight( $hit['text'], $terms ) // Escaped inside.
			);
		}
		$out .= '</ol>';
		$pages = (int) ceil( $total / TBE_SEARCH_PER_PAGE );
		if ( $pages > 1 ) {
			$out .= '<nav class="tbe-pager" aria-label="' . esc_attr__( 'Result pages', 'tbe-child' ) . '">';
			$out .= $page > 1 ? '<a rel="prev" href="' . esc_url( add_query_arg( array( 'q' => $query, 'pg' => $page - 1 ), home_url( '/bible/search/' ) ) ) . '"><span aria-hidden="true">←</span> ' . esc_html__( 'Previous', 'tbe-child' ) . '</a>' : '<span></span>';
			$out .= '<span class="tbe-muted">' . esc_html( sprintf( /* translators: 1: page, 2: pages */ __( 'Page %1$d of %2$d', 'tbe-child' ), $page, $pages ) ) . '</span>';
			$out .= $page < $pages ? '<a rel="next" href="' . esc_url( add_query_arg( array( 'q' => $query, 'pg' => $page + 1 ), home_url( '/bible/search/' ) ) ) . '">' . esc_html__( 'Next', 'tbe-child' ) . ' <span aria-hidden="true">→</span></a>' : '<span></span>';
			$out .= '</nav>';
		}
	} else {
		$out .= '<p class="tbe-muted">' . esc_html__( 'No verses matched.', 'tbe-child' ) . '</p>';
	}
	$out .= '</section>';

	// Commentary.
	$posts = new WP_Query(
		array(
			'post_type'      => 'tbe_commentary',
			'post_status'    => 'publish',
			's'              => $query,
			'posts_per_page' => 10,
			'no_found_rows'  => true,
		)
	);
	$out .= '<section class="tbe-results"><h2 class="tbe-ornament">' . esc_html__( 'In the commentary', 'tbe-child' ) . '</h2>';
	if ( $posts->posts ) {
		$out .= '<ul class="tbe-hits tbe-hits--notes">';
		foreach ( $posts->posts as $post ) {
			$out .= sprintf(
				'<li><a class="tbe-hit__ref" href="%1$s">%2$s</a> <span class="tbe-muted">%3$s</span><br><span class="tbe-hit__text">%4$s</span></li>',
				esc_url( get_permalink( $post ) ),
				esc_html( get_the_title( $post ) ),
				esc_html( (string) get_post_meta( $post->ID, 'tbe_passage', true ) ),
				tbe_highlight( wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ), $terms )
			);
		}
		$out .= '</ul>';
	} else {
		$out .= '<p class="tbe-muted">' . esc_html__( 'No commentary matched.', 'tbe-child' ) . '</p>';
	}
	return $out . '</section></div>';
}

function tbe_search_form( string $query = '' ): string {
	return '<form class="tbe-search" role="search" method="get" action="' . esc_url( home_url( '/bible/search/' ) ) . '">'
		. '<label class="screen-reader-text" for="tbe-q">' . esc_html__( 'Search the text and commentary', 'tbe-child' ) . '</label>'
		. '<input id="tbe-q" type="search" name="q" value="' . esc_attr( $query ) . '" placeholder="' . esc_attr__( 'Search words or "a phrase"', 'tbe-child' ) . '">'
		. '<button type="submit" class="tbe-btn tbe-btn--solid">' . esc_html__( 'Search', 'tbe-child' ) . '</button></form>';
}
