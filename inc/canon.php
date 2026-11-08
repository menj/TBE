<?php
/**
 * Canons: which tradition counts which book as scripture, and the prepared sections for books beyond the 66
 * (deuterocanonical, further Orthodox and Ethiopian Orthodox). Data: data/canons.json. A book in those sections
 * whose text is in data/further/ is linked (see inc/further.php); the rest are marked "Not on the site yet".
 *
 * The 66 books follow their testament: the Old Testament is counted by the Jewish, Protestant, Catholic,
 * Orthodox and Ethiopian traditions; the New Testament by all of them except the Jewish.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

final class TBE_Canon {

	/** @return array{traditions:array<string,array>,sections:array<int,array>} */
	public static function data(): array {
		static $data = null;
		if ( null === $data ) {
			$file = TBE_DIR . '/data/canons.json';
			$json = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array();
			$data = array(
				'traditions' => (array) ( $json['traditions'] ?? array() ),
				'sections'   => (array) ( $json['sections'] ?? array() ),
			);
		}
		return $data;
	}

	/** The traditions that count one of the 66 books, in a fixed order. @return string[] */
	public static function for_book( array $book ): array {
		$all = array( 'jewish', 'protestant', 'catholic', 'orthodox', 'ethiopian' );
		return 'OT' === $book['testament'] ? $all : array_values( array_diff( $all, array( 'jewish' ) ) );
	}
}

/** Small labels naming the traditions that count a book; each explains itself on hover. */
function tbe_canon_chips( array $traditions ): string {
	$names = TBE_Canon::data()['traditions'];
	$out   = '';
	foreach ( $traditions as $key ) {
		if ( ! isset( $names[ $key ] ) ) {
			continue;
		}
		$out .= '<li class="tbe-canon__chip tbe-canon__chip--' . esc_attr( $key ) . '" tabindex="0" data-tbe-tip-title="' . esc_attr( (string) $names[ $key ]['full'] ) . '" data-tbe-tip="' . esc_attr( (string) $names[ $key ]['note'] ) . '">' . esc_html( (string) $names[ $key ]['name'] ) . '</li>';
	}
	return '' === $out ? '' : '<ul class="tbe-canon" aria-label="' . esc_attr__( 'Counted as scripture by', 'tbe-child' ) . '">' . $out . '</ul>';
}

/** A neutral label for a writing no church counts today, such as "Once read as scripture". */
function tbe_canon_badge( string $text ): string {
	return '' === $text ? '' : '<ul class="tbe-canon"><li class="tbe-canon__chip tbe-canon__chip--past">' . esc_html( $text ) . '</li></ul>';
}

/**
 * A book's name in Ge'ez script, set beside its English name when data/canons.json gives one ("geez"), with its
 * transliteration when given ("geez_latin").
 */
function tbe_canon_geez( array $book ): string {
	$geez  = trim( (string) ( $book['geez'] ?? '' ) );
	$latin = trim( (string) ( $book['geez_latin'] ?? '' ) );
	if ( '' === $geez ) {
		return '';
	}
	return ' <span class="tbe-booklist__geez" lang="gez">' . esc_html( $geez ) . '</span>' . ( '' === $latin ? '' : ' <span class="tbe-booklist__geez tbe-booklist__geez--latin" lang="gez-Latn">' . esc_html( $latin ) . '</span>' );
}

/** On a book's page: who counts it as scripture. */
function tbe_canon_line( array $book ): string {
	$chips = tbe_canon_chips( TBE_Canon::for_book( $book ) );
	return '' === $chips ? '' : '<div class="tbe-canon-line"><span class="tbe-canon-line__label">' . esc_html__( 'Counted as scripture by', 'tbe-child' ) . '</span>' . $chips . '</div>';
}

/** The filter above the book list: dim the books a tradition does not count. */
function tbe_canon_filter(): string {
	$out = '<div class="tbe-toolbar tbe-canon-filter" role="group" aria-label="' . esc_attr__( 'Show the books of one tradition', 'tbe-child' ) . '"><button type="button" class="tbe-btn" data-tbe-canon="all" aria-pressed="true">' . esc_html__( 'All books', 'tbe-child' ) . '</button>';
	foreach ( TBE_Canon::data()['traditions'] as $key => $t ) {
		$out .= '<button type="button" class="tbe-btn" data-tbe-canon="' . esc_attr( $key ) . '" aria-pressed="false" data-tbe-tip="' . esc_attr( (string) $t['note'] ) . '">' . esc_html( (string) $t['name'] ) . '</button>';
	}
	return $out . '</div>';
}

/** The prepared sections on the book list: books on the site are linked, the rest clearly marked as not here yet. */
function tbe_canon_sections(): string {
	$out = '';
	foreach ( TBE_Canon::data()['sections'] as $section ) {
		$items = '';
		foreach ( (array) $section['books'] as $book ) {
			$further = class_exists( 'TBE_Further' ) ? TBE_Further::book( (string) ( $book['slug'] ?? '' ) ) : null;
			if ( $further ) {
				$items .= '<li data-canon="' . esc_attr( implode( ' ', (array) $book['traditions'] ) ) . '"><a href="' . esc_url( TBE_Bible::url( (string) $further['slug'] ) ) . '"><span class="tbe-booklist__name">' . esc_html( (string) $book['name'] ) . tbe_canon_geez( $book ) . '</span><span class="tbe-booklist__count">' . esc_html( sprintf( /* translators: %d: number of chapters */ _n( '%d chapter', '%d chapters', (int) $further['count'], 'tbe-child' ), $further['count'] ) ) . '</span></a>' . ( ! empty( $book['note'] ) ? '<span class="tbe-booklist__note">' . esc_html( (string) $book['note'] ) . '</span>' : '' ) . tbe_canon_chips( (array) $book['traditions'] ) . '</li>';
				continue;
			}
			$items .= '<li data-canon="' . esc_attr( implode( ' ', (array) $book['traditions'] ) ) . '"><span class="tbe-booklist__item tbe-booklist__item--planned"><span class="tbe-booklist__name">' . esc_html( (string) $book['name'] ) . tbe_canon_geez( $book ) . '</span><span class="tbe-booklist__count">' . esc_html__( 'Not on the site yet', 'tbe-child' ) . '</span>' . ( ! empty( $book['note'] ) ? '<span class="tbe-booklist__note">' . esc_html( (string) $book['note'] ) . '</span>' : '' ) . '</span>' . tbe_canon_chips( (array) $book['traditions'] ) . tbe_canon_badge( (string) ( $book['badge'] ?? '' ) ) . '</li>';
		}
		$out .= '<section class="tbe-testament tbe-extra" id="' . esc_attr( (string) $section['id'] ) . '" data-canon="' . esc_attr( implode( ' ', (array) $section['traditions'] ) ) . '"><h2 class="tbe-ornament">' . esc_html( (string) $section['title'] ) . '</h2><p class="tbe-center tbe-muted tbe-extra__intro">' . esc_html( (string) $section['intro'] ) . '</p><ul class="tbe-booklist tbe-booklist--planned">' . $items . '</ul></section>';
	}
	return $out;
}
