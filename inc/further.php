<?php
/**
 * Further books: books counted as scripture by some churches beyond the 66, read on the site from data/further/.
 * The first are the three books of Meqabyan of the Ethiopian Orthodox Tewahedo canon, in the English translation
 * from Ge'ez made by Wikisource contributors (CC BY-SA 4.0), built by tools/build_meqabyan.py (0.10.22).
 *
 * A book is readable when data/further/{slug}.json exists and its slug is listed in one of the prepared sections
 * of data/canons.json. It answers at /bible/{slug}/ and /bible/{slug}/{chapter}/, the same addresses as the 66,
 * and the book list links it in its section in place of "Not on the site yet".
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

final class TBE_Further {

	/** A further book by slug, with its chapters, or null. Only slugs listed in data/canons.json are read. */
	public static function book( string $slug ): ?array {
		static $cache = array();
		if ( array_key_exists( $slug, $cache ) ) {
			return $cache[ $slug ];
		}
		$cache[ $slug ] = null;
		if ( ! in_array( $slug, self::listed(), true ) ) {
			return null;
		}
		$file = TBE_DIR . '/data/further/' . $slug . '.json';
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $data ) || empty( $data['chapters'] ) ) {
			return null;
		}
		$data['slug']     = $slug;
		$data['chapters'] = array_values( (array) $data['chapters'] );
		$data['count']    = count( $data['chapters'] );
		return $cache[ $slug ] = $data;
	}

	/** The slugs of every book in the prepared sections of data/canons.json, in their order. @return string[] */
	public static function listed(): array {
		$slugs = array();
		foreach ( TBE_Canon::data()['sections'] as $section ) {
			foreach ( (array) $section['books'] as $book ) {
				$slugs[] = (string) ( $book['slug'] ?? '' );
			}
		}
		return array_values( array_filter( $slugs ) );
	}

	/** The further books on the site, in the order of data/canons.json. @return array<string,array> */
	public static function books(): array {
		$out = array();
		foreach ( self::listed() as $slug ) {
			$book = self::book( $slug );
			if ( $book ) {
				$out[ $slug ] = $book;
			}
		}
		return $out;
	}

	/** One chapter: array{n:int,heading:string,verses:array<int,array{n:int,t:string}>}, or null. */
	public static function chapter( array $book, int $chapter ): ?array {
		return $book['chapters'][ $chapter - 1 ] ?? null;
	}

	/** The canon entry for a book (traditions, language, note). */
	public static function canon( string $slug ): array {
		foreach ( TBE_Canon::data()['sections'] as $section ) {
			foreach ( (array) $section['books'] as $book ) {
				if ( $slug === ( $book['slug'] ?? '' ) ) {
					return $book + array( 'section' => (string) $section['id'] );
				}
			}
		}
		return array();
	}

	/** The chapters before and after, crossing into the next further book: [ [slug, chapter]|null, ... ]. */
	public static function neighbours( string $slug, int $chapter ): array {
		$books = self::books();
		$slugs = array_keys( $books );
		$i     = (int) array_search( $slug, $slugs, true );
		$prev  = $chapter > 1 ? array( $slug, $chapter - 1 ) : ( $i > 0 ? array( $slugs[ $i - 1 ], $books[ $slugs[ $i - 1 ] ]['count'] ) : null );
		$next  = $chapter < $books[ $slug ]['count'] ? array( $slug, $chapter + 1 ) : ( isset( $slugs[ $i + 1 ] ) ? array( $slugs[ $i + 1 ], 1 ) : null );
		return array( $prev, $next );
	}

	public static function label( string $slug, int $chapter = 0 ): string {
		$book = self::book( $slug );
		return ( $book ? (string) $book['name'] : $slug ) . ( $chapter ? ' ' . $chapter : '' );
	}
}

/** Where the translation comes from and under which licence, shown on every page of a further book. */
function tbe_further_credit( array $book ): string {
	$src = (array) ( $book['source'] ?? array() );
	return '<aside class="tbe-notice tbe-further__credit"><p>' . wp_kses_post(
		sprintf(
			/* translators: 1: link to the Wikisource page, 2: link to the licence */
			__( 'A working English translation from Ge’ez by Wikisource contributors (%1$s), shared under %2$s. It is not a translation authorised by the Ethiopian Orthodox Tewahedo Church. Verse numbers follow the Wikisource page; where it skips a number, so does this page.', 'tbe-child' ),
			'<a href="' . esc_url( (string) ( $src['url'] ?? '' ) ) . '" rel="noopener">' . esc_html( (string) ( $src['title'] ?? '' ) ) . '</a>',
			'<a href="' . esc_url( (string) ( $src['licence_url'] ?? '' ) ) . '" rel="noopener">' . esc_html( (string) ( $src['licence'] ?? '' ) ) . '</a>'
		)
	) . '</p></aside>';
}

function tbe_further_breadcrumb( array $book, int $chapter = 0 ): string {
	$out = '<nav class="tbe-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'tbe-child' ) . '"><a href="' . esc_url( TBE_Bible::url() ) . '">' . esc_html__( 'Books', 'tbe-child' ) . '</a>';
	if ( $chapter ) {
		$out .= ' <span aria-hidden="true">›</span> <a href="' . esc_url( TBE_Bible::url( (string) $book['slug'] ) ) . '">' . esc_html( (string) $book['name'] ) . '</a>';
	}
	return $out . '</nav>';
}

/** The eyebrow over a further book: the section of the book list it belongs to. */
function tbe_further_eyebrow( array $book ): string {
	foreach ( TBE_Canon::data()['sections'] as $section ) {
		if ( ( TBE_Further::canon( (string) $book['slug'] )['section'] ?? '' ) === $section['id'] ) {
			return '<p class="tbe-eyebrow">' . esc_html( (string) $section['title'] ) . '</p>';
		}
	}
	return '';
}

function tbe_view_further_book( array $book ): string {
	$canon = TBE_Further::canon( (string) $book['slug'] );
	$out   = '<div class="tbe-reader tbe-book tbe-further">';
	$out  .= tbe_further_breadcrumb( $book );
	$out  .= tbe_further_eyebrow( $book );
	$out  .= '<h1 class="tbe-title">' . esc_html( (string) $book['name'] ) . '</h1>';
	if ( ! empty( $canon['geez'] ) ) {
		$out .= '<p class="tbe-title-sub"><span lang="gez">' . esc_html( (string) $canon['geez'] ) . '</span>' . ( empty( $canon['geez_latin'] ) ? '' : ' <i lang="gez-Latn">' . esc_html( (string) $canon['geez_latin'] ) . '</i>' ) . '</p>';
	}
	if ( ! empty( $canon['note'] ) ) {
		$out .= '<p class="tbe-title-sub">' . esc_html( (string) $canon['note'] ) . '</p>';
	}
	$chips = tbe_canon_chips( (array) ( $canon['traditions'] ?? array() ) );
	if ( '' !== $chips ) {
		$out .= '<div class="tbe-canon-line"><span class="tbe-canon-line__label">' . esc_html__( 'Counted as scripture by', 'tbe-child' ) . '</span>' . $chips . '</div>';
	}
	$out .= tbe_further_credit( $book );
	$out .= '<h2 class="tbe-ornament">' . esc_html__( 'Chapters', 'tbe-child' ) . '</h2><ol class="tbe-chapters">';
	for ( $i = 1; $i <= $book['count']; $i++ ) {
		$out .= '<li><a href="' . esc_url( TBE_Bible::url( (string) $book['slug'], $i ) ) . '">' . $i . '</a></li>';
	}
	return $out . '</ol></div>';
}

function tbe_view_further_chapter( array $book, int $chapter ): string {
	$slug   = (string) $book['slug'];
	$ch     = (array) TBE_Further::chapter( $book, $chapter );
	$layout = 'flow' === tbe_setting( 'layout' ) ? 'flow' : 'verse';
	$out    = '<div class="tbe-reader tbe-chapter tbe-further tbe-layout-' . esc_attr( $layout ) . '" data-book="' . esc_attr( $slug ) . '" data-chapter="' . (int) $chapter . '">';
	$out   .= tbe_further_breadcrumb( $book, $chapter );
	$out   .= '<p class="tbe-eyebrow">' . esc_html( sprintf( /* translators: %d: chapter number */ __( 'Chapter %d', 'tbe-child' ), $chapter ) ) . '</p>';
	$out   .= '<h1 class="tbe-title">' . esc_html( (string) $book['name'] ) . '</h1>';
	$out   .= '<div class="tbe-toolbar" role="group" aria-label="' . esc_attr__( 'Reading tools', 'tbe-child' ) . '">'
		. '<button type="button" class="tbe-btn" data-tbe-size="-1" aria-label="' . esc_attr__( 'Smaller text', 'tbe-child' ) . '" data-tbe-tip="' . esc_attr__( 'Smaller text', 'tbe-child' ) . '">A−</button>'
		. '<button type="button" class="tbe-btn" data-tbe-size="1" aria-label="' . esc_attr__( 'Larger text', 'tbe-child' ) . '" data-tbe-tip="' . esc_attr__( 'Larger text', 'tbe-child' ) . '">A+</button>'
		. '<button type="button" class="tbe-btn" data-tbe-numbers aria-pressed="' . ( tbe_setting( 'verse_numbers' ) ? 'true' : 'false' ) . '">' . esc_html__( 'Verse numbers', 'tbe-child' ) . '</button>'
		. '<button type="button" class="tbe-btn" data-tbe-layout aria-pressed="' . ( 'flow' === $layout ? 'true' : 'false' ) . '" data-tbe-tip="' . esc_attr__( 'Switch between one verse per line and continuous paragraphs', 'tbe-child' ) . '">' . esc_html__( 'Continuous text', 'tbe-child' ) . '</button>'
		. '</div>';
	if ( 1 === $chapter ) {
		$out .= tbe_further_credit( $book );
	}
	$class = 'tbe-text' . ( tbe_setting( 'verse_numbers' ) ? '' : ' tbe-hide-numbers' );
	$out  .= '<div class="' . esc_attr( $class ) . '" lang="en" dir="ltr">';
	if ( '' !== (string) ( $ch['heading'] ?? '' ) ) {
		$out .= '<p class="tbe-further__heading">' . esc_html( (string) $ch['heading'] ) . '</p>';
	}
	$out .= tbe_render_verses( (array) ( $ch['verses'] ?? array() ), array(), array(), $slug, $chapter ) . '</div>';

	[ $prev, $next ] = TBE_Further::neighbours( $slug, $chapter );
	$out .= '<nav class="tbe-pager" aria-label="' . esc_attr__( 'Chapter navigation', 'tbe-child' ) . '">';
	$out .= $prev ? '<a class="tbe-pager__prev" rel="prev" href="' . esc_url( TBE_Bible::url( $prev[0], $prev[1] ) ) . '"><span aria-hidden="true">←</span> ' . esc_html( TBE_Further::label( $prev[0], $prev[1] ) ) . '</a>' : '<span></span>';
	$out .= $next ? '<a class="tbe-pager__next" rel="next" href="' . esc_url( TBE_Bible::url( $next[0], $next[1] ) ) . '">' . esc_html( TBE_Further::label( $next[0], $next[1] ) ) . ' <span aria-hidden="true">→</span></a>' : '<span></span>';
	$out .= '</nav>';
	if ( 1 !== $chapter ) {
		$out .= '<p class="tbe-copyright">' . wp_kses_post( sprintf( /* translators: 1: link to the Wikisource page, 2: licence */ __( 'Translation: %1$s, %2$s.', 'tbe-child' ), '<a href="' . esc_url( (string) ( $book['source']['url'] ?? '' ) ) . '" rel="noopener">Wikisource</a>', esc_html( (string) ( $book['source']['licence'] ?? '' ) ) ) ) . '</p>';
	}
	return $out . '</div>';
}
