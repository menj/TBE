<?php
/**
 * Server-side rendering of the reader: books, chapters, verse-by-verse text with commentary
 * boxes under the verses they discuss, introductions, search and the index.
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
		case 'interlinear':
			return tbe_view_interlinear( $request['book'], $request['chapter'] );
		case 'search':
			return tbe_view_search( $request );
		case 'index':
			return tbe_view_index();
		case 'interlinear-index':
			return tbe_view_interlinear_index();
		case 'credits':
			return tbe_view_credits();
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

/** Credits and references: every source the site draws on, including each bundled commentary pack. */
function tbe_view_credits(): string {
	$out  = '<div class="tbe-reader tbe-credits">';
	$out .= '<h1 class="tbe-title">' . esc_html__( 'Credits and references', 'tbe-child' ) . '</h1>';

	$out .= '<h2 class="tbe-ornament">' . esc_html__( 'Scripture', 'tbe-child' ) . '</h2><p>' . esc_html( (string) tbe_setting( 'copyright' ) ) . '</p>';

	$out .= '<h2 class="tbe-ornament">' . esc_html__( 'Hebrew and Greek text and interlinear', 'tbe-child' ) . '</h2><p>'
		. wp_kses_post( sprintf( /* translators: %s: link */ __( 'Hebrew: Westminster Leningrad Codex. Greek: Nestle-Aland 28th edition, with variants from the SBL Greek New Testament, the Textus Receptus and others. Both tagged by Tyndale House, Cambridge. Word-by-word data from %s, licensed CC BY 4.0.', 'tbe-child' ), '<a href="https://www.STEPBible.org" rel="noopener">STEP Bible</a>' ) ) . '</p>';

	$out .= '<h2 class="tbe-ornament">' . esc_html__( 'Typeface', 'tbe-child' ) . '</h2><p>' . esc_html__( 'Bible text is set in the SBL BibLit font, by the Society of Biblical Literature.', 'tbe-child' ) . '</p>';

	$packs = tbe_packs();
	if ( $packs ) {
		$out .= '<h2 class="tbe-ornament">' . esc_html__( 'Commentary sources', 'tbe-child' ) . '</h2><ul class="tbe-credits__list">';
		foreach ( $packs as $pack ) {
			$out .= '<li>' . esc_html( (string) $pack['source'] );
			if ( ! empty( $pack['licence'] ) ) {
				$out .= ' <span class="tbe-muted">(' . esc_html( (string) $pack['licence'] ) . ')</span>';
			}
			$out .= '</li>';
		}
		$out .= '</ul>';
	}
	return $out . '</div>';
}

/** Every chapter that has a Hebrew interlinear, by book; a note marks the ones with a recorded reading. */
function tbe_view_interlinear_index(): string {
	$out  = '<div class="tbe-reader tbe-books tbe-il-index">';
	$out .= '<h1 class="tbe-title">' . esc_html__( 'Interlinear', 'tbe-child' ) . '</h1>';
	$out .= '<p class="tbe-center tbe-muted">' . esc_html__( 'The Old Testament in Hebrew and the New Testament in Greek, word by word, with transliteration, gloss, Strong\'s numbers and grammar. Chapters marked with ♪ also have a recorded reading that follows along word by word.', 'tbe-child' ) . '</p>';
	$found = false;
	foreach ( TBE_Bible::books() as $book ) {
		$items = '';
		for ( $i = 1; $i <= (int) $book['chapters']; $i++ ) {
			if ( ! TBE_Interlinear::available( $book['slug'], $i ) ) {
				continue;
			}
			$sound  = TBE_Audio::chapter( $book['slug'], $i ) ? ' <span class="tbe-il-index__note" title="' . esc_attr__( 'Recorded reading', 'tbe-child' ) . '" aria-label="' . esc_attr__( 'with recorded reading', 'tbe-child' ) . '">♪</span>' : '';
			$items .= '<li><a href="' . esc_url( TBE_Interlinear::url( $book['slug'], $i ) ) . '">' . (int) $i . $sound . '</a></li>';
		}
		if ( '' === $items ) {
			continue;
		}
		$found = true;
		$out  .= '<section class="tbe-testament"><h2 class="tbe-ornament"><a href="' . esc_url( TBE_Bible::url( $book['slug'] ) ) . '">' . esc_html( $book['name'] ) . '</a></h2><ol class="tbe-chapters">' . $items . '</ol></section>';
	}
	if ( ! $found ) {
		$out .= '<p class="tbe-notice">' . esc_html__( 'No interlinear chapters have been added yet.', 'tbe-child' ) . '</p>';
	}
	return $out . '</div>';
}

function tbe_view_books(): string {
	$out  = '<div class="tbe-reader tbe-books">';
	$out .= '<h1 class="tbe-title">' . esc_html__( 'The Books of the Bible', 'tbe-child' ) . '</h1>';
	$out .= '<p class="tbe-center"><a href="' . esc_url( home_url( '/bible/interlinear/' ) ) . '">' . esc_html__( 'Interlinear', 'tbe-child' ) . '</a> &nbsp;·&nbsp; <a href="' . esc_url( home_url( '/bible/index/' ) ) . '">' . esc_html__( 'Index of commentary', 'tbe-child' ) . '</a></p>';

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
	$out .= '<p class="tbe-eyebrow">' . esc_html( 'OT' === $book['testament'] ? __( 'Old Testament', 'tbe-child' ) : __( 'New Testament', 'tbe-child' ) ) . '</p>';
	$out .= '<h1 class="tbe-title">' . esc_html( $book['name'] ) . '</h1>';

	foreach ( tbe_commentary_for_book( $book['slug'] ) as $entry ) {
		$out .= tbe_render_box( $entry, $book['slug'], 0, true );
	}

	$out .= '<h2 class="tbe-ornament">' . esc_html__( 'Chapters', 'tbe-child' ) . '</h2><ol class="tbe-chapters">';
	for ( $i = 1; $i <= $book['chapters']; $i++ ) {
		$has  = TBE_Interlinear::available( $book['slug'], $i );
		$out .= sprintf(
			'<li%s><a href="%s"%s>%d</a></li>',
			$has ? ' class="has-interlinear"' : '',
			esc_url( TBE_Bible::url( $book['slug'], $i ) ),
			$has ? ' title="' . esc_attr__( 'Interlinear available', 'tbe-child' ) . '"' : '',
			$i
		);
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

/* Chapter page ------------------------------------------------------------ */

function tbe_view_chapter( array $book, int $chapter ): string {
	$slug   = $book['slug'];
	$verses = TBE_Bible::chapter( $slug, $chapter );
	$layout = 'flow' === tbe_setting( 'layout' ) ? 'flow' : 'verse';

	$out  = '<div class="tbe-reader tbe-chapter tbe-layout-' . esc_attr( $layout ) . '" data-book="' . esc_attr( $slug ) . '" data-chapter="' . (int) $chapter . '">';
	$out .= tbe_breadcrumb( $book, $chapter );
	$out .= '<p class="tbe-eyebrow">' . esc_html( sprintf( /* translators: %d: chapter number */ __( 'Chapter %d', 'tbe-child' ), $chapter ) ) . '</p>';
	$out .= '<h1 class="tbe-title">' . esc_html( $book['name'] ) . '</h1>';
	$out .= tbe_reader_toolbar();
	if ( TBE_Interlinear::available( $slug, $chapter ) ) {
		$out .= '<p class="tbe-center tbe-il-link"><a class="tbe-btn tbe-btn--solid" href="' . esc_url( TBE_Interlinear::url( $slug, $chapter ) ) . '">' . esc_html( 'OT' === $book['testament'] ? __( 'Hebrew interlinear', 'tbe-child' ) : __( 'Greek interlinear', 'tbe-child' ) ) . ' <span aria-hidden="true">→</span></a></p>';
		$audio = TBE_Audio::chapter( $slug, $chapter );
		if ( $audio ) {
			$out .= '<figure class="tbe-il__audio tbe-il__audio--inline"><figcaption>' . esc_html__( 'Listen to the Hebrew reading', 'tbe-child' ) . ' <a href="' . esc_url( TBE_Interlinear::url( $slug, $chapter ) ) . '">' . esc_html__( 'Follow it word by word', 'tbe-child' ) . '</a></figcaption><audio data-tbe-audio controls preload="metadata" src="' . esc_url( $audio['url'] ) . '"></audio>';
			if ( '' !== trim( (string) $audio['credit'] ) ) {
				$out .= '<p class="tbe-muted tbe-il__credit">' . esc_html( (string) $audio['credit'] ) . '</p>';
			}
			$out .= '</figure>';
		}
	}

	$entries = tbe_commentary_for_chapter( $slug, $chapter );
	$intro   = array();
	$starts  = array();
	$covered = array();
	foreach ( $entries as $entry ) {
		$vs = (int) get_post_meta( $entry->ID, 'tbe_vstart', true );
		$ve = max( $vs, (int) get_post_meta( $entry->ID, 'tbe_vend', true ) );
		if ( 0 === $vs ) {
			$intro[] = $entry;
			continue;
		}
		$starts[ $vs ][] = $entry;
		for ( $v = $vs; $v <= $ve; $v++ ) {
			$covered[ $v ] = true;
		}
	}

	foreach ( $intro as $entry ) {
		$out .= tbe_render_box( $entry, $slug, $chapter, true );
	}
	$out .= tbe_render_notes_contents( $starts, $slug, $chapter );

	if ( ! $verses ) {
		return $out . '<p class="tbe-notice">' . esc_html__( 'The text of this chapter is not available right now.', 'tbe-child' ) . '</p></div>';
	}

	$class = 'tbe-text' . ( tbe_setting( 'verse_numbers' ) ? '' : ' tbe-hide-numbers' );
	$out  .= '<div class="' . esc_attr( $class ) . '" lang="en" dir="ltr">' . tbe_render_verses( $verses, $starts, $covered, $slug, $chapter ) . '</div>';
	$out  .= tbe_render_chapter_nav( $slug, $chapter );
	return $out . '</div>';
}

function tbe_reader_toolbar(): string {
	return '<div class="tbe-toolbar" role="group" aria-label="' . esc_attr__( 'Reading tools', 'tbe-child' ) . '">'
		. '<button type="button" class="tbe-btn" data-tbe-size="-1" aria-label="' . esc_attr__( 'Smaller text', 'tbe-child' ) . '" data-tbe-tip="' . esc_attr__( 'Smaller text', 'tbe-child' ) . '">A−</button>'
		. '<button type="button" class="tbe-btn" data-tbe-size="1" aria-label="' . esc_attr__( 'Larger text', 'tbe-child' ) . '" data-tbe-tip="' . esc_attr__( 'Larger text', 'tbe-child' ) . '">A+</button>'
		. '<button type="button" class="tbe-btn" data-tbe-numbers aria-pressed="' . ( tbe_setting( 'verse_numbers' ) ? 'true' : 'false' ) . '">' . esc_html__( 'Verse numbers', 'tbe-child' ) . '</button>'
		. '<button type="button" class="tbe-btn" data-tbe-layout aria-pressed="' . ( 'flow' === tbe_setting( 'layout' ) ? 'true' : 'false' ) . '" data-tbe-tip="' . esc_attr__( 'Switch between one verse per line and continuous paragraphs', 'tbe-child' ) . '">' . esc_html__( 'Continuous text', 'tbe-child' ) . '</button>'
		. '<button type="button" class="tbe-btn" data-tbe-toggle-notes="open">' . esc_html__( 'Open all notes', 'tbe-child' ) . '</button>'
		. '<button type="button" class="tbe-btn" data-tbe-toggle-notes="close">' . esc_html__( 'Close all notes', 'tbe-child' ) . '</button>'
		. '</div>';
}

/** A short list of the notes in this chapter, linking to each box. @param array<int,WP_Post[]> $starts */
function tbe_render_notes_contents( array $starts, string $slug, int $chapter ): string {
	if ( ! $starts ) {
		return '';
	}
	ksort( $starts );
	$count = 0;
	$items = '';
	foreach ( $starts as $verse => $entries ) {
		foreach ( $entries as $entry ) {
			++$count;
			$items .= '<li><a href="#note-' . (int) $entry->ID . '"><span class="tbe-toc__ref">' . esc_html( (string) $verse ) . '</span> ' . esc_html( get_the_title( $entry ) ) . '</a></li>';
		}
	}
	return '<details class="tbe-toc"><summary>' . esc_html( sprintf( /* translators: %d: number of notes */ _n( '%d note in this chapter', '%d notes in this chapter', $count, 'tbe-child' ), $count ) ) . '</summary><ol>' . $items . '</ol></details>';
}

/**
 * Turn verse records into paragraphs and poetry, with commentary boxes after the verse they start at.
 *
 * @param array<int,array>      $verses
 * @param array<int,WP_Post[]>  $starts  Entries keyed by first verse.
 * @param array<int,bool>       $covered Verses touched by any entry.
 */
function tbe_render_verses( array $verses, array $starts, array $covered, string $slug, int $chapter ): string {
	$out     = '';
	$para    = '';
	$in_poem = false;

	$flush = static function () use ( &$out, &$para ) {
		if ( '' !== $para ) {
			$out .= '<div class="tbe-para">' . $para . '</div>';
			$para = '';
		}
	};

	// A note sits after the last verse it covers; its marker stays beside the first.
	$ends = array();
	foreach ( $starts as $first => $entries ) {
		foreach ( $entries as $entry ) {
			$last            = max( (int) $first, (int) get_post_meta( $entry->ID, 'tbe_vend', true ) );
			$ends[ $last ][] = $entry;
		}
	}

	foreach ( $verses as $verse ) {
		$n       = (int) $verse['n'];
		$text    = (string) $verse['t'];
		$is_poem = false !== strpos( $text, "\n" ) || preg_match( '/^\s{2,}/', $text );
		$classes = 'tbe-verse' . ( isset( $covered[ $n ] ) ? ' has-note' : '' );
		$marks   = '';
		$boxes   = '';
		foreach ( $starts[ $n ] ?? array() as $entry ) {
			$marks .= sprintf(
				'<a class="tbe-mark" href="#note-%1$d" aria-label="%2$s" data-tbe-tip="%3$s">✦</a>',
				(int) $entry->ID,
				esc_attr( sprintf( /* translators: %d: verse number */ __( 'Commentary on verse %d', 'tbe-child' ), $n ) ),
				esc_attr( get_the_title( $entry ) )
			);
		}
		foreach ( $ends[ $n ] ?? array() as $entry ) {
			$boxes .= tbe_render_box( $entry, $slug, $chapter );
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
			$out .= '<span class="' . esc_attr( $classes ) . '" id="v' . $n . '" data-v="' . $n . '">' . $lines . $marks . '</span>' . $boxes;
			if ( ! empty( $verse['p'] ) ) {
				$out .= '<span class="tbe-stanza" aria-hidden="true"></span>';
			}
			continue;
		}

		if ( $in_poem ) {
			$out    .= '</div>';
			$in_poem = false;
		}
		$para .= '<span class="' . esc_attr( $classes ) . '" id="v' . $n . '" data-v="' . $n . '">' . $number . '<span class="tbe-vt">' . esc_html( $text ) . '</span>' . $marks . '</span> ' . $boxes;
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
	$out  = '<nav class="tbe-pager" aria-label="' . esc_attr__( 'Chapter navigation', 'tbe-child' ) . '">';
	$out .= $prev
		? '<a class="tbe-pager__prev" rel="prev" href="' . esc_url( TBE_Bible::url( $prev[0], $prev[1] ) ) . '"><span aria-hidden="true">←</span> ' . esc_html( TBE_Bible::label( $prev[0], $prev[1] ) ) . '</a>'
		: '<span></span>';
	$out .= $next
		? '<a class="tbe-pager__next" rel="next" href="' . esc_url( TBE_Bible::url( $next[0], $next[1] ) ) . '">' . esc_html( TBE_Bible::label( $next[0], $next[1] ) ) . ' <span aria-hidden="true">→</span></a>'
		: '<span></span>';
	return $out . '</nav>' . tbe_copyright_html();
}

/* Commentary box ---------------------------------------------------------- */

/** Help text for a section label, shown as a tooltip. */
function tbe_section_help( string $slug ): string {
	$help = array(
		'textual-criticism'   => __( 'Compares manuscripts and variant readings to establish the earliest text that can be recovered.', 'tbe-child' ),
		'critical-commentary' => __( 'Examines language, context, authorship and historical background.', 'tbe-child' ),
		'islamic-perspective' => __( 'Reads the passage in light of the Qur\'an and Islamic scholarship (tafsir).', 'tbe-child' ),
		'reflection'          => __( 'Contemplation of the passage, the counterpart of tadabbur.', 'tbe-child' ),
	);
	return $help[ $slug ] ?? '';
}

/** Entry body with its footnotes, scripture cards and glossary tooltips. */
function tbe_entry_html( WP_Post $entry ): string {
	static $cache = array();
	if ( isset( $cache[ $entry->ID ] ) ) {
		return $cache[ $entry->ID ];
	}
	global $post;
	$backup = $post;
	$post   = $entry; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $entry );
	$html = apply_filters( 'the_content', $entry->post_content );
	if ( $backup instanceof WP_Post ) {
		$post = $backup; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $backup );
	} else {
		wp_reset_postdata();
	}
	$cache[ $entry->ID ] = tbe_enrich_commentary( $html );
	return $cache[ $entry->ID ];
}

/**
 * One commentary box. Introductions (book or chapter) are open and unlabelled by verse.
 */
function tbe_render_box( WP_Post $entry, string $slug, int $chapter, bool $is_intro = false ): string {
	$vs   = (int) get_post_meta( $entry->ID, 'tbe_vstart', true );
	$ve   = max( $vs, (int) get_post_meta( $entry->ID, 'tbe_vend', true ) );
	$open = $is_intro || tbe_setting( 'boxes_open' );

	if ( $is_intro ) {
		$ref = __( 'Introduction', 'tbe-child' );
	} elseif ( $vs ) {
		$ref = $vs . ( $ve > $vs ? '–' . $ve : '' );
	} else {
		$ref = tbe_passage_label( $slug, $chapter );
	}

	$chips = '';
	$terms = get_the_terms( $entry, 'tbe_section' );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $term ) {
			$chips .= '<span class="tbe-chip tbe-chip--' . esc_attr( $term->slug ) . '" data-tbe-tip="' . esc_attr( tbe_section_help( $term->slug ) ) . '" tabindex="0">' . esc_html( $term->name ) . '</span>';
		}
	}
	if ( tbe_get_variants( $entry->ID ) ) {
		$chips .= '<span class="tbe-chip tbe-chip--variants" data-tbe-tip="' . esc_attr__( 'This note includes a table of manuscript variants.', 'tbe-child' ) . '" tabindex="0">' . esc_html__( 'Variants', 'tbe-child' ) . '</span>';
	}
	if ( tbe_get_quran_refs( $entry->ID ) ) {
		$chips .= '<span class="tbe-chip tbe-chip--quran" data-tbe-tip="' . esc_attr__( 'This note cross-references the Qur\'an.', 'tbe-child' ) . '" tabindex="0">' . esc_html__( 'Qur\'an', 'tbe-child' ) . '</span>';
	}

	return sprintf(
		'<details class="tbe-box%1$s" id="note-%2$d" data-vs="%3$d" data-ve="%4$d"%5$s><summary><span class="tbe-box__ref">%6$s</span><span class="tbe-box__title">%7$s</span></summary><div class="tbe-chips">%8$s</div><div class="tbe-box__body">%9$s%10$s</div><p class="tbe-box__link"><a href="%11$s">%12$s</a></p></details>',
		$is_intro ? ' tbe-box--intro' : '',
		(int) $entry->ID,
		$vs,
		$ve,
		$open ? ' open' : '',
		esc_html( $ref ),
		esc_html( get_the_title( $entry ) ),
		$chips, // Escaped above.
		tbe_entry_html( $entry ), // phpcs:ignore WordPress.Security.EscapeOutput
		tbe_render_entry_extras( $entry->ID ), // Escaped inside.
		esc_url( get_permalink( $entry ) ),
		esc_html__( 'Permalink to this note', 'tbe-child' )
	);
}

/* Index of commentary ------------------------------------------------------ */

function tbe_view_index(): string {
	$posts = get_posts(
		array(
			'post_type'   => 'tbe_commentary',
			'post_status' => 'publish',
			'numberposts' => 1000,
			'no_found_rows' => true,
		)
	);
	$out = '<div class="tbe-reader tbe-index"><h1 class="tbe-title">' . esc_html__( 'Index of commentary', 'tbe-child' ) . '</h1>' . tbe_search_form();

	if ( ! $posts ) {
		return $out . '<p class="tbe-muted">' . esc_html__( 'No commentary has been published yet.', 'tbe-child' ) . '</p></div>';
	}

	// By passage, in canonical order.
	$by_book = array();
	foreach ( $posts as $post ) {
		$book = (string) get_post_meta( $post->ID, 'tbe_book', true );
		if ( $book ) {
			$by_book[ $book ][] = $post;
		}
	}
	$out .= '<section><h2 class="tbe-ornament">' . esc_html__( 'By passage', 'tbe-child' ) . '</h2>';
	foreach ( TBE_Bible::books() as $slug => $book ) {
		if ( empty( $by_book[ $slug ] ) ) {
			continue;
		}
		usort(
			$by_book[ $slug ],
			static function ( $a, $b ) {
				return array( (int) get_post_meta( $a->ID, 'tbe_chapter', true ), (int) get_post_meta( $a->ID, 'tbe_vstart', true ) )
					<=> array( (int) get_post_meta( $b->ID, 'tbe_chapter', true ), (int) get_post_meta( $b->ID, 'tbe_vstart', true ) );
			}
		);
		$out .= '<h3 class="tbe-index__book"><a href="' . esc_url( TBE_Bible::url( $slug ) ) . '">' . esc_html( $book['name'] ) . '</a></h3><ul class="tbe-index__list">';
		foreach ( $by_book[ $slug ] as $post ) {
			$out .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a> <span class="tbe-muted">' . esc_html( (string) get_post_meta( $post->ID, 'tbe_passage', true ) ) . '</span></li>';
		}
		$out .= '</ul>';
	}
	$out .= '</section>';

	// By theme and by section.
	foreach ( array( 'post_tag' => __( 'By tag', 'tbe-child' ), 'tbe_section' => __( 'By section', 'tbe-child' ) ) as $taxonomy => $heading ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			continue;
		}
		$out .= '<section><h2 class="tbe-ornament">' . esc_html( $heading ) . '</h2><ul class="tbe-tags">';
		foreach ( $terms as $term ) {
			$out .= '<li><a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . ' <span class="tbe-muted">' . (int) $term->count . '</span></a></li>';
		}
		$out .= '</ul></section>';
	}
	$out .= '<p class="tbe-center"><a href="' . esc_url( get_post_type_archive_link( 'tbe_term' ) ) . '">' . esc_html__( 'Index of terms', 'tbe-child' ) . '</a></p>';
	return $out . '</div>';
}

/** The ESV copyright notice, taken from the settings screen. */
function tbe_copyright_html(): string {
	$text = (string) tbe_setting( 'copyright' );
	return '' === trim( $text ) ? '' : '<p class="tbe-copyright">' . wp_kses_post( $text ) . '</p>';
}
