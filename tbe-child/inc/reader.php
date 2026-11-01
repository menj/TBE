<?php
/**
 * Server-side rendering of the Bible reader views.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** Render whichever view the current URL asks for. */
function tbe_render_reader(): string {
	$request = tbe_request();
	if ( ! $request ) {
		return '';
	}
	switch ( $request['view'] ) {
		case 'books':
			return tbe_view_books();
		case 'book':
			return tbe_view_book( $request['book'] );
		case 'chapter':
			return tbe_view_chapter( $request['book'], $request['chapter'] );
		default:
			return tbe_view_missing();
	}
}

function tbe_view_missing(): string {
	return '<div class="tbe-reader"><h1 class="tbe-title">' . esc_html__( 'Passage not found', 'tbe-child' ) . '</h1><p>'
		. sprintf(
			/* translators: %s: link to book list */
			esc_html__( 'That book or chapter does not exist. Try the %s.', 'tbe-child' ),
			'<a href="' . esc_url( TBE_Bible::url() ) . '">' . esc_html__( 'list of books', 'tbe-child' ) . '</a>'
		) . '</p></div>';
}

function tbe_view_books(): string {
	$out = '<div class="tbe-reader tbe-books">';
	if ( ! empty( $_GET['not_found'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$out .= '<p class="tbe-notice" role="status">' . esc_html__( 'We could not recognise that reference. Try something like "John 3:16" or "Psalm 23".', 'tbe-child' ) . '</p>';
	}
	$out .= '<h1 class="tbe-title">' . esc_html__( 'The Books of the Bible', 'tbe-child' ) . '</h1>';

	foreach ( array(
		'OT' => __( 'Old Testament', 'tbe-child' ),
		'NT' => __( 'New Testament', 'tbe-child' ),
	) as $code => $heading ) {
		$out .= '<section class="tbe-testament"><h2 class="tbe-ornament">' . esc_html( $heading ) . '</h2><ul class="tbe-booklist">';
		foreach ( TBE_Bible::books() as $book ) {
			if ( $book['testament'] !== $code ) {
				continue;
			}
			$out .= sprintf(
				'<li><a href="%s"><span class="tbe-booklist__name">%s</span><span class="tbe-booklist__count">%s</span></a></li>',
				esc_url( TBE_Bible::url( $book['slug'] ) ),
				esc_html( $book['name'] ),
				esc_html( sprintf( /* translators: %d: number of chapters */ _n( '%d chapter', '%d chapters', (int) $book['chapters'], 'tbe-child' ), $book['chapters'] ) )
			);
		}
		$out .= '</ul></section>';
	}
	return $out . '</div>';
}

function tbe_view_book( array $book ): string {
	$out  = '<div class="tbe-reader tbe-book">';
	$out .= tbe_breadcrumb( $book );
	$out .= '<h1 class="tbe-title">' . esc_html( $book['name'] ) . '</h1><ol class="tbe-chapters">';
	for ( $i = 1; $i <= $book['chapters']; $i++ ) {
		$out .= sprintf( '<li><a href="%s">%d</a></li>', esc_url( TBE_Bible::url( $book['slug'], $i ) ), $i );
	}
	return $out . '</ol></div>';
}

function tbe_breadcrumb( array $book, ?int $chapter = null ): string {
	$out = '<nav class="tbe-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'tbe-child' ) . '"><a href="' . esc_url( TBE_Bible::url() ) . '">' . esc_html__( 'Books', 'tbe-child' ) . '</a>';
	if ( $chapter ) {
		$out .= ' <span aria-hidden="true">›</span> <a href="' . esc_url( TBE_Bible::url( $book['slug'] ) ) . '">' . esc_html( $book['name'] ) . '</a>';
	}
	return $out . '</nav>';
}

function tbe_view_chapter( array $book, int $chapter ): string {
	$slug   = $book['slug'];
	$verses = TBE_Bible::chapter( $slug, $chapter );
	$out    = '<div class="tbe-reader tbe-chapter" data-book="' . esc_attr( $slug ) . '" data-chapter="' . (int) $chapter . '">';
	$out   .= tbe_breadcrumb( $book, $chapter );
	$out   .= '<h1 class="tbe-title">' . esc_html( TBE_Bible::label( $slug, $chapter ) ) . '</h1>';
	$out   .= tbe_reader_toolbar();

	if ( ! $verses ) {
		return $out . '<p class="tbe-notice">' . esc_html__( 'The text of this chapter is not available right now.', 'tbe-child' ) . '</p></div>';
	}

	$entries = tbe_commentary_for_chapter( $slug, $chapter );
	$starts  = array();
	$covered = array();
	foreach ( $entries as $entry ) {
		$vs = (int) get_post_meta( $entry->ID, 'tbe_vstart', true );
		$ve = max( $vs, (int) get_post_meta( $entry->ID, 'tbe_vend', true ) );
		if ( $vs > 0 ) {
			$starts[ $vs ][] = $entry;
			for ( $v = $vs; $v <= $ve; $v++ ) {
				$covered[ $v ] = true;
			}
		}
	}

	$wrapper_class = 'tbe-text' . ( tbe_setting( 'verse_numbers' ) ? '' : ' tbe-hide-numbers' );
	$out          .= '<div class="' . esc_attr( $wrapper_class ) . '" lang="en">' . tbe_render_verses( $verses, $starts, $covered ) . '</div>';

	$out .= tbe_render_chapter_nav( $slug, $chapter );
	$out .= tbe_render_commentary_panel( $entries, $slug, $chapter );
	return $out . '</div>';
}

function tbe_reader_toolbar(): string {
	return '<div class="tbe-toolbar" role="group" aria-label="' . esc_attr__( 'Reading tools', 'tbe-child' ) . '">'
		. '<button type="button" class="tbe-btn" data-tbe-size="-1" aria-label="' . esc_attr__( 'Smaller text', 'tbe-child' ) . '">A−</button>'
		. '<button type="button" class="tbe-btn" data-tbe-size="1" aria-label="' . esc_attr__( 'Larger text', 'tbe-child' ) . '">A+</button>'
		. '<button type="button" class="tbe-btn" data-tbe-numbers aria-pressed="' . ( tbe_setting( 'verse_numbers' ) ? 'true' : 'false' ) . '">' . esc_html__( 'Verse numbers', 'tbe-child' ) . '</button>'
		. '</div>';
}

/**
 * Turn verse records into prose paragraphs and poetry blocks.
 *
 * @param array<int,array> $verses
 * @param array<int,WP_Post[]> $starts   Entries keyed by first verse.
 * @param array<int,bool>      $covered  Verses touched by any entry.
 */
function tbe_render_verses( array $verses, array $starts, array $covered ): string {
	$out     = '';
	$para    = '';
	$in_poem = false;

	$flush = static function () use ( &$out, &$para ) {
		if ( '' !== $para ) {
			$out .= '<p class="tbe-para">' . $para . '</p>';
			$para = '';
		}
	};

	foreach ( $verses as $verse ) {
		$n        = (int) $verse['n'];
		$text     = (string) $verse['t'];
		$is_poem  = false !== strpos( $text, "\n" ) || preg_match( '/^\s{2,}/', $text );
		$classes  = 'tbe-verse' . ( isset( $covered[ $n ] ) ? ' has-note' : '' );
		$marks    = '';
		foreach ( $starts[ $n ] ?? array() as $entry ) {
			$marks .= sprintf(
				'<a class="tbe-mark" href="#note-%1$d" aria-label="%2$s">✦</a>',
				(int) $entry->ID,
				esc_attr( sprintf( /* translators: %d: verse number */ __( 'Commentary on verse %d', 'tbe-child' ), $n ) )
			);
		}
		$number = '<a class="tbe-vn" href="#v' . $n . '"><span class="screen-reader-text">' . esc_html__( 'Verse', 'tbe-child' ) . ' </span>' . $n . '</a>';

		if ( $is_poem ) {
			if ( ! $in_poem ) {
				$flush();
				$out    .= '<div class="tbe-poem">';
				$in_poem = true;
			}
			$lines = '';
			foreach ( explode( "\n", $text ) as $i => $line ) {
				$indent = min( 3, (int) floor( ( strlen( $line ) - strlen( ltrim( $line, ' ' ) ) ) / 4 ) );
				$lines .= '<span class="tbe-line tbe-i' . $indent . '">' . ( 0 === $i ? $number : '' ) . esc_html( ltrim( $line ) ) . '</span>';
			}
			$out .= '<span class="' . esc_attr( $classes ) . '" id="v' . $n . '" data-v="' . $n . '">' . $lines . $marks . '</span>';
			if ( ! empty( $verse['p'] ) ) {
				$out .= '<span class="tbe-stanza" aria-hidden="true"></span>';
			}
			continue;
		}

		if ( $in_poem ) {
			$out    .= '</div>';
			$in_poem = false;
		}
		$para .= '<span class="' . esc_attr( $classes ) . '" id="v' . $n . '" data-v="' . $n . '">' . $number . '<span class="tbe-vt">' . esc_html( $text ) . '</span>' . $marks . '</span> ';
		if ( ! empty( $verse['p'] ) ) {
			$flush();
		}
	}
	if ( $in_poem ) {
		$out .= '</div>';
	}
	$flush();
	return $out;
}

function tbe_render_chapter_nav( string $slug, int $chapter ): string {
	[ $prev, $next ] = TBE_Bible::neighbours( $slug, $chapter );
	$out = '<nav class="tbe-pager" aria-label="' . esc_attr__( 'Chapter navigation', 'tbe-child' ) . '">';
	$out .= $prev
		? '<a class="tbe-pager__prev" rel="prev" href="' . esc_url( TBE_Bible::url( $prev[0], $prev[1] ) ) . '"><span aria-hidden="true">←</span> ' . esc_html( TBE_Bible::label( $prev[0], $prev[1] ) ) . '</a>'
		: '<span></span>';
	$out .= $next
		? '<a class="tbe-pager__next" rel="next" href="' . esc_url( TBE_Bible::url( $next[0], $next[1] ) ) . '">' . esc_html( TBE_Bible::label( $next[0], $next[1] ) ) . ' <span aria-hidden="true">→</span></a>'
		: '<span></span>';
	return $out . '</nav>';
}

/** @param WP_Post[] $entries */
function tbe_render_commentary_panel( array $entries, string $slug, int $chapter ): string {
	$out  = '<section class="tbe-commentary" aria-labelledby="tbe-commentary-h"><h2 id="tbe-commentary-h" class="tbe-ornament">' . esc_html__( 'Commentary on this chapter', 'tbe-child' ) . '</h2>';
	if ( ! $entries ) {
		return $out . '<p class="tbe-muted">' . esc_html__( 'No commentary has been written for this chapter yet.', 'tbe-child' ) . '</p></section>';
	}
	foreach ( $entries as $entry ) {
		$vs    = (int) get_post_meta( $entry->ID, 'tbe_vstart', true );
		$ve    = (int) get_post_meta( $entry->ID, 'tbe_vend', true );
		$terms = get_the_terms( $entry, 'tbe_section' );
		$chips = '';
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$chips .= '<span class="tbe-chip tbe-chip--' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</span>';
			}
		}
		$out .= sprintf(
			'<article class="tbe-note" id="note-%1$d" data-vs="%2$d" data-ve="%3$d"><header><span class="tbe-note__ref">%4$s</span><h3 class="tbe-note__title"><a href="%5$s">%6$s</a></h3><div class="tbe-chips">%7$s</div></header><div class="tbe-note__body">%8$s</div></article>',
			(int) $entry->ID,
			$vs,
			max( $vs, $ve ),
			esc_html( $vs ? TBE_Bible::label( $slug, $chapter, $vs, $ve ) : TBE_Bible::label( $slug, $chapter ) ),
			esc_url( get_permalink( $entry ) ),
			esc_html( get_the_title( $entry ) ),
			$chips, // Already escaped above.
			apply_filters( 'the_content', $entry->post_content ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}
	return $out . '</section>';
}

/** The ESV copyright notice, taken from the settings screen. */
function tbe_copyright_html(): string {
	$text = (string) tbe_setting( 'copyright' );
	return '' === trim( $text ) ? '' : '<p class="tbe-copyright">' . wp_kses_post( $text ) . '</p>';
}
