<?php
/**
 * The commentary page (0.10.8): the pieces around a single entry, in reading order. Breadcrumbs, the dek, the
 * dates and reading time, the answer box, the contents, the notes and sources, the next step and related entries
 * from the same book. Each is a small server-rendered block, placed by templates/single-tbe_commentary.html.
 * Appearance, TBE, Commentary pages switches them. No byline: the author is named in the structured data only.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** The commentary entry a block is about: the post in the loop, else the queried one. */
function tbe_article_post(): ?WP_Post {
	$post = get_post();
	if ( ! $post instanceof WP_Post || 'tbe_commentary' !== $post->post_type ) {
		$post = is_singular( 'tbe_commentary' ) ? get_queried_object() : null;
	}
	return $post instanceof WP_Post && 'tbe_commentary' === $post->post_type ? $post : null;
}

/** Whether a the_content call is the main entry of a single commentary page. */
function tbe_article_is_main(): bool {
	return tbe_is_entry_body();
}

/* Headings ---------------------------------------------------------------- */

/**
 * An id for a heading: its own text as a slug, numbered when that id is taken.
 *
 * @param string $text Heading text.
 * @param array  $seen Ids taken so far, as keys; the new one is added.
 */
function tbe_article_slug( string $text, array &$seen ): string {
	// sanitize_title() percent-encodes letters outside ASCII; the id keeps them as letters, so "#" links to a
	// Hebrew or Greek heading match it exactly.
	$base = rawurldecode( sanitize_title( $text ) );
	$base = '' !== $base ? $base : 'section';
	$slug = $base;
	for ( $n = 2; isset( $seen[ $slug ] ); $n++ ) {
		$slug = $base . '-' . $n;
	}
	$seen[ $slug ] = true;
	return $slug;
}

/** Plain text of a piece of heading HTML. */
function tbe_article_heading_text( string $html ): string {
	return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
}

/**
 * The ids of the level-two headings in a piece of HTML, in order: the author's own id where a heading has one,
 * else one made from its text. The author's ids are reserved first, so a made id never repeats one. A heading
 * with no text gets ''.
 *
 * @param string $html Stored or rendered text.
 * @return array<int,array{0:string,1:string,2:bool}> Id, text, whether the author set the id.
 */
function tbe_article_heading_list( string $html ): array {
	if ( ! preg_match_all( '#<h2(\s[^>]*)?>(.*?)</h2>#is', $html, $found, PREG_SET_ORDER ) ) {
		return array();
	}
	$seen = array();
	$own  = array();
	foreach ( $found as $i => $m ) {
		if ( preg_match( '/\sid\s*=\s*["\']([^"\']+)["\']/i', (string) ( $m[1] ?? '' ), $idm ) ) {
			$own[ $i ]          = $idm[1];
			$seen[ $idm[1] ] = true;
		}
	}
	$out = array();
	foreach ( $found as $i => $m ) {
		$text  = tbe_article_heading_text( $m[2] );
		$out[] = isset( $own[ $i ] ) ? array( $own[ $i ], $text, true ) : array( '' === $text ? '' : tbe_article_slug( $text, $seen ), $text, false );
	}
	return $out;
}

/**
 * The level-two headings of an entry, in order: array( id, text ). Read from the stored text, so the contents can
 * be printed before the body; tbe_article_heading_ids() gives the rendered headings the same ids.
 *
 * @param WP_Post $post Entry.
 * @return array<int,array{0:string,1:string}>
 */
function tbe_article_headings( WP_Post $post ): array {
	$out = array();
	foreach ( tbe_article_heading_list( (string) $post->post_content ) as $h ) {
		if ( '' !== $h[1] ) {
			$out[] = array( $h[0], $h[1] );
		}
	}
	return $out;
}

/** Give each level-two heading of the rendered body an id, matching tbe_article_headings(). */
function tbe_article_heading_ids( string $html ): string {
	$list = tbe_article_heading_list( $html );
	$i    = 0;
	return (string) preg_replace_callback(
		'#<h2(\s[^>]*)?>(.*?)</h2>#is',
		static function ( $m ) use ( $list, &$i ) {
			$h = $list[ $i++ ] ?? array( '', '', true );
			if ( $h[2] || '' === $h[0] ) {
				return $m[0];
			}
			return '<h2 id="' . esc_attr( $h[0] ) . '"' . (string) ( $m[1] ?? '' ) . '>' . $m[2] . '</h2>';
		},
		$html
	);
}

add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! tbe_article_is_main() ) {
			return $content;
		}
		$content = tbe_article_heading_ids( (string) $content );
		if ( tbe_setting( 'mid_next' ) ) {
			$content = tbe_article_insert_mid( $content, tbe_render_next_step( array( 'variant' => 'mid' ) ) );
		}
		return $content;
	},
	12
);

/** Put the mid-article next step before the third level-two heading, when there is one. */
function tbe_article_insert_mid( string $html, string $aside ): string {
	if ( '' === $aside || ! preg_match_all( '#<h2[\s>]#i', $html, $found, PREG_OFFSET_CAPTURE ) || count( $found[0] ) < 3 ) {
		return $html;
	}
	$at = (int) $found[0][2][1];
	return substr( $html, 0, $at ) . $aside . substr( $html, $at );
}

/* Body: tables and notes -------------------------------------------------- */

/**
 * A table in an entry scrolls on its own on a narrow screen. The figure becomes a labelled, focusable region so a
 * keyboard can scroll it too; the label is the caption, if there is one.
 */
add_filter(
	'render_block_core/table',
	static function ( $html ) {
		if ( ! is_singular( 'tbe_commentary' ) || false !== strpos( (string) $html, 'role="region"' ) ) {
			return $html;
		}
		$label = preg_match( '#<figcaption[^>]*>(.*?)</figcaption>#is', (string) $html, $m ) ? tbe_article_heading_text( $m[1] ) : '';
		$label = '' !== $label ? $label : __( 'Table', 'tbe-child' );
		return (string) preg_replace( '#<figure\b([^>]*)>#i', '<figure$1 role="region" tabindex="0" aria-label="' . esc_attr( wp_html_excerpt( $label, 120, '…' ) ) . '">', (string) $html, 1 );
	}
);

/**
 * Whether the page's template prints the notes and sources block after the body. Only then can the footnotes
 * move there: a block placed before Post Content renders before the list exists.
 */
function tbe_article_template_has_sources(): bool {
	$tpl     = (string) ( $GLOBALS['_wp_current_template_content'] ?? '' );
	$body    = strpos( $tpl, '<!-- wp:post-content' );
	$sources = strpos( $tpl, '<!-- wp:tbe/sources' );
	return false !== $body && false !== $sources && $sources > $body;
}

/**
 * The entry's footnotes move into the notes and sources block below the body, when the template has one. The list
 * is kept for that block; its ids, and the numbers in the text that point to them, stay as WordPress wrote them.
 */
add_filter(
	'render_block_core/footnotes',
	static function ( $html ) {
		if ( ! tbe_article_is_main() || ! tbe_article_template_has_sources() ) {
			return $html;
		}
		if ( '' !== trim( (string) $html ) ) {
			$GLOBALS['tbe_article_footnotes'] = (string) $html;
		}
		return '';
	}
);

/**
 * The entry's footnotes as plain text with the first web address in each, read from the meta WordPress stores
 * them in. Used for the visible list's fallback and the Article citation.
 *
 * @return array<int,array{text:string,url:string}>
 */
function tbe_article_footnotes( int $post_id ): array {
	// WordPress keeps the footnotes as a JSON string; an array (from an import or another plugin) is read as it is.
	$raw  = get_post_meta( $post_id, 'footnotes', true );
	$rows = is_array( $raw ) ? $raw : json_decode( is_string( $raw ) ? $raw : '', true );
	$out  = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$html = (string) ( $row['content'] ?? '' );
		$text = tbe_article_heading_text( $html );
		if ( '' === $text ) {
			continue;
		}
		$url   = preg_match( '#href=["\'](https?://[^"\']+)["\']#i', $html, $m ) ? $m[1] : '';
		$out[] = array( 'text' => $text, 'url' => $url );
	}
	return $out;
}

/* The pieces --------------------------------------------------------------- */

/** Breadcrumbs: the same trail as the BreadcrumbList in the structured data. */
function tbe_render_breadcrumbs(): string {
	$post = tbe_article_post();
	if ( ! $post ) {
		return '';
	}
	return sprintf(
		'<nav class="tbe-crumbs" aria-label="%1$s"><ol><li><a href="%2$s">%3$s</a></li><li><a href="%4$s">%5$s</a></li><li><span aria-current="page">%6$s</span></li></ol></nav>',
		esc_attr__( 'Breadcrumb', 'tbe-child' ),
		esc_url( home_url( '/' ) ),
		esc_html( get_bloginfo( 'name' ) ),
		esc_url( home_url( '/commentary/' ) ),
		esc_html__( 'Commentary', 'tbe-child' ),
		esc_html( get_the_title( $post ) )
	);
}

/** The dek: the entry's excerpt, when the author wrote one. */
function tbe_render_dek(): string {
	$post = tbe_article_post();
	$text = $post ? trim( (string) $post->post_excerpt ) : '';
	return '' === $text ? '' : '<p class="tbe-dek">' . esc_html( $text ) . '</p>';
}

/** Minutes to read an entry, at 220 words a minute. */
function tbe_article_minutes( WP_Post $post ): int {
	// Every run of non-space characters is a word, so Hebrew, Greek and Arabic count too.
	$words = (int) preg_match_all( '/\S+/u', wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
	return max( 1, (int) ceil( $words / 220 ) );
}

/** The meta row: published, updated when it is a later day, last reviewed when set, and reading time. */
function tbe_render_article_meta(): string {
	$post = tbe_article_post();
	if ( ! $post ) {
		return '';
	}
	$items   = array();
	$items[] = sprintf( '<span class="tbe-meta__item">%1$s <time datetime="%2$s">%3$s</time></span>', esc_html__( 'Published', 'tbe-child' ), esc_attr( (string) get_post_time( 'c', true, $post ) ), esc_html( (string) get_the_date( '', $post ) ) );
	if ( get_the_modified_date( 'Y-m-d', $post ) !== get_the_date( 'Y-m-d', $post ) && (int) get_post_modified_time( 'U', true, $post ) > (int) get_post_time( 'U', true, $post ) ) {
		$items[] = sprintf( '<span class="tbe-meta__item">%1$s <time datetime="%2$s">%3$s</time></span>', esc_html__( 'Updated', 'tbe-child' ), esc_attr( (string) get_post_modified_time( 'c', true, $post ) ), esc_html( (string) get_the_modified_date( '', $post ) ) );
	}
	$reviewed = (string) get_post_meta( $post->ID, 'tbe_reviewed', true );
	if ( tbe_article_valid_date( $reviewed ) ) {
		// Noon on that day in the site's own time zone, so the date shown is the date saved, in any zone.
		$day     = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $reviewed . ' 12:00', wp_timezone() );
		$stamp   = $day ? $day->getTimestamp() : 0;
		$items[] = sprintf( '<span class="tbe-meta__item">%1$s <time datetime="%2$s">%3$s</time></span>', esc_html__( 'Last reviewed', 'tbe-child' ), esc_attr( $reviewed ), esc_html( wp_date( (string) get_option( 'date_format' ), $stamp ) ) );
	}
	$minutes = tbe_article_minutes( $post );
	/* translators: %d: minutes of reading. */
	$items[] = '<span class="tbe-meta__item">' . esc_html( sprintf( _n( '%d minute to read', '%d minutes to read', $minutes, 'tbe-child' ), $minutes ) ) . '</span>';
	return '<p class="tbe-meta">' . implode( '', $items ) . '</p>';
}

/** Whether a string is a real calendar date written Y-m-d. */
function tbe_article_valid_date( string $date ): bool {
	return (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/** Key points from a block attribute or the entry's "Key points" field, one a line. @return string[] */
function tbe_article_points( string $text ): array {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) ) ) );
}

/**
 * The answer box: "In short", a one-line summary when the block gives one, and the key points. The template's box
 * reads the entry's Key points field; a box placed in the text can carry its own summary and points.
 *
 * @param array $attrs summary, points.
 */
function tbe_render_answer_box( array $attrs = array() ): string {
	$post = tbe_article_post();
	if ( ! $post || ! tbe_setting( 'answer_box' ) ) {
		return '';
	}
	$summary = trim( (string) ( $attrs['summary'] ?? '' ) );
	$points  = tbe_article_points( (string) ( $attrs['points'] ?? '' ) );
	if ( '' === $summary && ! $points ) {
		$points = tbe_article_points( (string) get_post_meta( $post->ID, 'tbe_takeaways', true ) );
	}
	if ( '' === $summary && ! $points ) {
		return '';
	}
	// Each box on the page gets its own heading id: the template's box and one placed in the text can both show.
	static $count = 0;
	++$count;
	$hid = 1 === $count ? 'tbe-answer-title' : 'tbe-answer-title-' . $count;
	$out = '<section class="tbe-answer" aria-labelledby="' . $hid . '"><h2 id="' . $hid . '" class="tbe-answer__title">' . esc_html__( 'In short', 'tbe-child' ) . '</h2>';
	if ( '' !== $summary ) {
		$out .= '<p class="tbe-answer__summary">' . esc_html( $summary ) . '</p>';
	}
	if ( $points ) {
		$out .= '<ul class="tbe-answer__points">';
		foreach ( $points as $point ) {
			$out .= '<li>' . esc_html( $point ) . '</li>';
		}
		$out .= '</ul>';
	}
	return $out . '</section>';
}

/** The contents: a list of the entry's sections, from three level-two headings up. Inline or sticky. */
function tbe_render_contents(): string {
	$post = tbe_article_post();
	if ( ! $post || ! tbe_setting( 'toc' ) ) {
		return '';
	}
	$headings = tbe_article_headings( $post );
	if ( count( $headings ) < 3 ) {
		return '';
	}
	$items = '';
	foreach ( $headings as $h ) {
		$items .= '<li><a href="#' . esc_attr( $h[0] ) . '">' . esc_html( $h[1] ) . '</a></li>';
	}
	if ( 'sticky' === tbe_setting( 'toc_style' ) ) {
		return '<details class="tbe-contents tbe-contents--sticky" data-tbe-contents><summary>' . esc_html__( 'Contents', 'tbe-child' ) . '</summary><nav aria-label="' . esc_attr__( 'Contents', 'tbe-child' ) . '"><ol>' . $items . '</ol></nav></details>';
	}
	return '<nav class="tbe-contents tbe-contents--inline" aria-labelledby="tbe-contents-title"><h2 id="tbe-contents-title" class="tbe-contents__title">' . esc_html__( 'Contents', 'tbe-child' ) . '</h2><ol>' . $items . '</ol></nav>';
}

/** The sources of an entry: the passage in the ESV, the word-by-word data, the Qur'an references. @return string[] HTML list items. */
function tbe_article_source_items( WP_Post $post ): array {
	$items   = array();
	$book    = (string) get_post_meta( $post->ID, 'tbe_book', true );
	$chapter = (int) get_post_meta( $post->ID, 'tbe_chapter', true );
	$vstart  = (int) get_post_meta( $post->ID, 'tbe_vstart', true );
	if ( $book && TBE_Bible::book( $book ) ) {
		$url     = $chapter ? TBE_Bible::url( $book, $chapter, $vstart ?: null ) : TBE_Bible::url( $book );
		$items[] = sprintf(
			/* translators: %s: passage link. */
			esc_html__( '%s, The Holy Bible, English Standard Version (Crossway, 2001)', 'tbe-child' ),
			'<a href="' . esc_url( $url ) . '">' . esc_html( (string) get_post_meta( $post->ID, 'tbe_passage', true ) ) . '</a>'
		);
		if ( $chapter && TBE_Interlinear::available( $book, $chapter ) ) {
			$greek   = 'NT' === ( TBE_Bible::book( $book )['testament'] ?? '' );
			$items[] = sprintf(
				/* translators: 1: link to the word-by-word page, 2: data set name. */
				esc_html__( '%1$s, from %2$s (CC BY 4.0)', 'tbe-child' ),
				'<a href="' . esc_url( TBE_Interlinear::url( $book, $chapter ) ) . '">' . esc_html( $greek ? __( 'The Greek word by word', 'tbe-child' ) : __( 'The Hebrew word by word', 'tbe-child' ) ) . '</a>',
				esc_html( $greek ? 'STEPBible TAGNT' : 'STEPBible TAHOT' )
			);
		}
	}
	foreach ( tbe_get_quran_refs( $post->ID ) as $row ) {
		$ref = tbe_parse_quran_ref( (string) $row['ref'] );
		if ( ! $ref ) {
			continue;
		}
		$item = '<a class="tbe-ref tbe-ref--quran" href="' . esc_url( tbe_quran_url( $ref ) ) . '" target="_blank" rel="noopener">' . esc_html( tbe_quran_label( $ref ) ) . '</a>';
		if ( '' !== (string) $row['note'] ) {
			$item .= ': <span class="tbe-muted">' . tbe_annotate_terms( esc_html( (string) $row['note'] ) ) . '</span>';
		}
		$items[] = $item;
	}
	return $items;
}

/** Notes and sources: the entry's footnotes, then its sources. The same notes feed the Article citation. */
function tbe_render_sources(): string {
	$post = tbe_article_post();
	if ( ! $post ) {
		return '';
	}
	$notes = (string) ( $GLOBALS['tbe_article_footnotes'] ?? '' );
	$items = tbe_article_source_items( $post );
	if ( '' === $notes && ! $items ) {
		return '';
	}
	$out = '<section class="tbe-sources" aria-labelledby="tbe-sources-title"><h2 id="tbe-sources-title" class="tbe-sources__title">' . esc_html__( 'Notes and sources', 'tbe-child' ) . '</h2>';
	if ( '' !== $notes ) {
		$out .= '<h3 class="tbe-sources__sub">' . esc_html__( 'Notes', 'tbe-child' ) . '</h3>' . $notes;
	}
	if ( $items ) {
		$out .= '<h3 class="tbe-sources__sub">' . esc_html__( 'Sources', 'tbe-child' ) . '</h3><ul class="tbe-sources__list"><li>' . implode( '</li><li>', $items ) . '</li></ul>';
	}
	return $out . '</section>';
}

/**
 * The next entry in reading order through the same book: by chapter, then first verse. Mirror copies of
 * cross-references are skipped, as they are in the lists.
 */
function tbe_article_next_in_book( WP_Post $post ): ?WP_Post {
	$book = (string) get_post_meta( $post->ID, 'tbe_book', true );
	if ( ! $book ) {
		return null;
	}
	$here  = array( (int) get_post_meta( $post->ID, 'tbe_chapter', true ), (int) get_post_meta( $post->ID, 'tbe_vstart', true ), $post->ID );
	$best  = null;
	$bestk = null;
	foreach ( tbe_article_book_entries( $book ) as $entry ) {
		$key = array( (int) get_post_meta( $entry->ID, 'tbe_chapter', true ), (int) get_post_meta( $entry->ID, 'tbe_vstart', true ), $entry->ID );
		if ( $entry->ID !== $post->ID && $key > $here && ( null === $bestk || $key < $bestk ) ) {
			$best  = $entry;
			$bestk = $key;
		}
	}
	return $best;
}

/** Published entries on one book, without mirror copies. @return WP_Post[] */
function tbe_article_book_entries( string $book ): array {
	static $cache = array();
	if ( ! isset( $cache[ $book ] ) ) {
		$cache[ $book ] = get_posts(
			tbe_hide_mirror_notes(
				array(
					'post_type'              => 'tbe_commentary',
					'post_status'            => 'publish',
					'numberposts'            => -1,
					'no_found_rows'          => true,
					'update_post_term_cache' => true,
					'meta_query'             => array( array( 'key' => 'tbe_book', 'value' => $book ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			)
		);
	}
	return $cache[ $book ];
}

/**
 * The next step: keep reading. The end variant points to the passage in the reader, the word-by-word page and the
 * next entry on the book; the mid variant is one line. "Ask a question" shows when the settings name a page.
 *
 * @param array $attrs variant: end or mid.
 */
function tbe_render_next_step( array $attrs = array() ): string {
	$post = tbe_article_post();
	if ( ! $post || ! tbe_setting( 'next_step' ) ) {
		return '';
	}
	$mid     = 'mid' === ( $attrs['variant'] ?? 'end' );
	$book    = (string) get_post_meta( $post->ID, 'tbe_book', true );
	$chapter = (int) get_post_meta( $post->ID, 'tbe_chapter', true );
	$vstart  = (int) get_post_meta( $post->ID, 'tbe_vstart', true );
	$label   = (string) get_post_meta( $post->ID, 'tbe_passage', true );
	$data    = $book ? TBE_Bible::book( $book ) : null;
	$ask     = esc_url( (string) tbe_setting( 'ask_url' ) );
	if ( ! $data ) {
		if ( $mid ) {
			return '';
		}
		$read_url  = home_url( '/commentary/' );
		$read_text = __( 'Browse the commentary', 'tbe-child' );
		$lead      = __( 'Every note is filed under the verses it discusses.', 'tbe-child' );
	} else {
		$read_url  = $chapter ? TBE_Bible::url( $book, $chapter, $vstart ?: null ) : TBE_Bible::url( $book );
		/* translators: %s: passage, such as John 1:1–18. */
		$read_text = sprintf( __( 'Read %s', 'tbe-child' ), $label );
		/* translators: %s: passage. */
		$lead      = sprintf( __( 'Read %s in the ESV, with every note filed under its verse.', 'tbe-child' ), $label );
	}
	if ( $mid ) {
		return '<aside class="tbe-next tbe-next--mid" aria-label="' . esc_attr__( 'Next step', 'tbe-child' ) . '"><p>' . sprintf(
			/* translators: %s: passage link. */
			esc_html__( 'Check each claim against the text: read %s beside these notes.', 'tbe-child' ),
			'<a href="' . esc_url( $read_url ) . '">' . esc_html( $label ) . '</a>'
		) . '</p></aside>';
	}
	$out  = '<aside class="tbe-next" aria-labelledby="tbe-next-title"><p class="tbe-next__kicker">' . esc_html__( 'Next step', 'tbe-child' ) . '</p>';
	$out .= '<h2 id="tbe-next-title" class="tbe-next__title">' . esc_html__( 'Read the passage', 'tbe-child' ) . '</h2>';
	$out .= '<p class="tbe-next__lead">' . esc_html( $lead ) . '</p><p class="tbe-next__actions"><a class="tbe-btn tbe-btn--solid" href="' . esc_url( $read_url ) . '">' . esc_html( $read_text ) . '</a>';
	if ( $data && $chapter && TBE_Interlinear::available( $book, $chapter ) ) {
		$out .= '<a class="tbe-btn" href="' . esc_url( TBE_Interlinear::url( $book, $chapter ) ) . '">' . esc_html( 'NT' === $data['testament'] ? __( 'Read the Greek word by word', 'tbe-child' ) : __( 'Read the Hebrew word by word', 'tbe-child' ) ) . '</a>';
	}
	if ( '' !== $ask ) {
		$out .= '<a class="tbe-btn tbe-btn--icon" href="' . $ask . '"><svg class="tbe-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7.5l8.5 6 8.5-6"/></svg>' . esc_html__( 'Ask a question', 'tbe-child' ) . '</a>';
	}
	$out .= '</p>';
	$next = tbe_article_next_in_book( $post );
	if ( $next ) {
		$out .= '<p class="tbe-next__path">' . sprintf(
			/* translators: 1: book name, 2: link to the next entry, 3: its passage. */
			esc_html__( 'Next on %1$s: %2$s (%3$s)', 'tbe-child' ),
			esc_html( $data['name'] ),
			'<a href="' . esc_url( get_permalink( $next ) ) . '">' . esc_html( get_the_title( $next ) ) . '</a>',
			esc_html( (string) get_post_meta( $next->ID, 'tbe_passage', true ) )
		) . '</p>';
	}
	return $out . '</aside>';
}

/**
 * The term ids of an entry in one taxonomy, from the term cache the entry query primed.
 *
 * @param WP_Post $post     Entry.
 * @param string  $taxonomy Taxonomy.
 * @return int[]
 */
function tbe_article_term_ids( WP_Post $post, string $taxonomy ): array {
	$terms = get_the_terms( $post, $taxonomy );
	return is_array( $terms ) ? array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ) : array();
}

/**
 * Related entries on the same book, scored: 3 for the same chapter, 2 for each shared tag, 1 for each shared
 * section; ties go to the nearer chapter, then the nearer verse, then the older entry. Entries with no shared chapter, tag or section score 0 and still count,
 * after the rest, since the book is shared.
 *
 * @return WP_Post[]
 */
function tbe_article_related( WP_Post $post, int $limit ): array {
	$book = (string) get_post_meta( $post->ID, 'tbe_book', true );
	if ( ! $book || $limit < 1 ) {
		return array();
	}
	$chapter  = (int) get_post_meta( $post->ID, 'tbe_chapter', true );
	$verse    = (int) get_post_meta( $post->ID, 'tbe_vstart', true );
	$tags     = tbe_article_term_ids( $post, 'post_tag' );
	$sections = tbe_article_term_ids( $post, 'tbe_section' );
	$scored   = array();
	foreach ( tbe_article_book_entries( $book ) as $entry ) {
		if ( $entry->ID === $post->ID ) {
			continue;
		}
		$ch         = (int) get_post_meta( $entry->ID, 'tbe_chapter', true );
		$score      = ( $ch === $chapter ? 3 : 0 )
			+ 2 * count( array_intersect( $tags, tbe_article_term_ids( $entry, 'post_tag' ) ) )
			+ count( array_intersect( $sections, tbe_article_term_ids( $entry, 'tbe_section' ) ) );
		$vs         = (int) get_post_meta( $entry->ID, 'tbe_vstart', true );
		$scored[]   = array( $score, -abs( $ch - $chapter ), -abs( $vs - $verse ), -$entry->ID, $entry );
	}
	// Highest score, then the nearer chapter, then the nearer verse, then the older entry: always one order.
	usort( $scored, static fn( $a, $b ) => array( $b[0], $b[1], $b[2], $b[3] ) <=> array( $a[0], $a[1], $a[2], $a[3] ) );
	return array_map( static fn( $s ) => $s[4], array_slice( $scored, 0, $limit ) );
}

/** Related entries on the same book, as cards. */
function tbe_render_related(): string {
	$post = tbe_article_post();
	if ( ! $post ) {
		return '';
	}
	$related = tbe_article_related( $post, max( 0, min( 6, (int) tbe_setting( 'related_count' ) ) ) );
	if ( ! $related ) {
		return '';
	}
	$book = TBE_Bible::book( (string) get_post_meta( $post->ID, 'tbe_book', true ) );
	/* translators: %s: book name. */
	$out  = '<section class="tbe-related" aria-labelledby="tbe-related-title"><h2 id="tbe-related-title" class="tbe-related__title">' . esc_html( sprintf( __( 'More on %s', 'tbe-child' ), $book ? $book['name'] : '' ) ) . '</h2><ul class="tbe-related__list">';
	foreach ( $related as $entry ) {
		$excerpt = trim( (string) $entry->post_excerpt );
		$out    .= '<li class="tbe-related__card"><p class="tbe-related__ref">' . esc_html( (string) get_post_meta( $entry->ID, 'tbe_passage', true ) ) . '</p>';
		$out    .= '<h3 class="tbe-related__name"><a href="' . esc_url( get_permalink( $entry ) ) . '">' . esc_html( get_the_title( $entry ) ) . '</a></h3>';
		if ( '' !== $excerpt ) {
			$out .= '<p class="tbe-related__excerpt">' . esc_html( wp_trim_words( $excerpt, 24 ) ) . '</p>';
		}
		$out .= '</li>';
	}
	return $out . '</ul></section>';
}

add_action(
	'init',
	static function () {
		$blocks = array(
			'tbe/breadcrumbs'  => array( 'render_callback' => 'tbe_render_breadcrumbs' ),
			'tbe/dek'          => array( 'render_callback' => 'tbe_render_dek' ),
			'tbe/article-meta' => array( 'render_callback' => 'tbe_render_article_meta' ),
			'tbe/answer-box'   => array(
				'attributes'      => array(
					'summary' => array( 'type' => 'string', 'default' => '' ),
					'points'  => array( 'type' => 'string', 'default' => '' ),
				),
				'render_callback' => 'tbe_render_answer_box',
			),
			'tbe/contents'     => array( 'render_callback' => 'tbe_render_contents' ),
			'tbe/sources'      => array( 'render_callback' => 'tbe_render_sources' ),
			'tbe/next-step'    => array(
				'attributes'      => array( 'variant' => array( 'type' => 'string', 'default' => 'end' ) ),
				'render_callback' => 'tbe_render_next_step',
			),
			'tbe/related'      => array( 'render_callback' => 'tbe_render_related' ),
		);
		foreach ( $blocks as $name => $args ) {
			register_block_type( $name, $args );
		}

		// The FAQ: a Details block in the "FAQ" style. Only those on the page feed the FAQPage data.
		register_block_style( 'core/details', array( 'name' => 'faq', 'label' => __( 'FAQ', 'tbe-child' ) ) );
		register_block_pattern_category( 'tbe', array( 'label' => __( 'The Bible Exposed', 'tbe-child' ) ) );
		register_block_pattern(
			'tbe/faq',
			array(
				'title'       => __( 'Questions and answers', 'tbe-child' ),
				'description' => __( 'A heading and two questions, each a Details block in the FAQ style. The questions show on the page and feed the FAQPage data.', 'tbe-child' ),
				'categories'  => array( 'tbe' ),
				'postTypes'   => array( 'tbe_commentary', 'post', 'page' ),
				'content'     => '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Questions readers ask', 'tbe-child' ) . '</h2><!-- /wp:heading -->'
					. '<!-- wp:details {"className":"is-style-faq"} --><details class="wp-block-details is-style-faq"><summary>' . esc_html__( 'The question, as a reader would ask it', 'tbe-child' ) . '</summary><!-- wp:paragraph --><p>' . esc_html__( 'The answer, in one or two sentences.', 'tbe-child' ) . '</p><!-- /wp:paragraph --></details><!-- /wp:details -->'
					. '<!-- wp:details {"className":"is-style-faq"} --><details class="wp-block-details is-style-faq"><summary>' . esc_html__( 'A second question', 'tbe-child' ) . '</summary><!-- wp:paragraph --><p>' . esc_html__( 'Its answer.', 'tbe-child' ) . '</p><!-- /wp:paragraph --></details><!-- /wp:details -->',
			)
		);

		foreach ( array( 'tbe_takeaways', 'tbe_reviewed' ) as $key ) {
			register_post_meta(
				'tbe_commentary',
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
				)
			);
		}
	}
);

/* The entry screen: key points and the review date ------------------------- */

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box( 'tbe_article', __( 'Key points and review', 'tbe-child' ), 'tbe_article_box', 'tbe_commentary', 'side', 'default' );
	}
);

function tbe_article_box( WP_Post $post ): void {
	wp_nonce_field( 'tbe_article', 'tbe_article_nonce' );
	?>
	<p>
		<label for="tbe-takeaways"><?php esc_html_e( 'Key points, one a line', 'tbe-child' ); ?></label>
		<textarea id="tbe-takeaways" name="tbe_takeaways" rows="5" class="widefat"><?php echo esc_textarea( (string) get_post_meta( $post->ID, 'tbe_takeaways', true ) ); ?></textarea>
	</p>
	<p class="description"><?php esc_html_e( 'Shown in the "In short" box near the top. The excerpt shows under the title.', 'tbe-child' ); ?></p>
	<p>
		<label for="tbe-reviewed"><?php esc_html_e( 'Last reviewed', 'tbe-child' ); ?></label>
		<input type="date" id="tbe-reviewed" name="tbe_reviewed" class="widefat" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, 'tbe_reviewed', true ) ); ?>">
	</p>
	<p class="description"><?php esc_html_e( 'Optional. The date you last checked the note against its sources.', 'tbe-child' ); ?></p>
	<?php
}

add_action(
	'save_post_tbe_commentary',
	static function ( int $post_id ) {
		if ( ! isset( $_POST['tbe_article_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tbe_article_nonce'] ) ), 'tbe_article' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$points = implode( "\n", tbe_article_points( sanitize_textarea_field( wp_unslash( $_POST['tbe_takeaways'] ?? '' ) ) ) );
		$date   = sanitize_text_field( wp_unslash( $_POST['tbe_reviewed'] ?? '' ) );
		foreach ( array( 'tbe_takeaways' => $points, 'tbe_reviewed' => tbe_article_valid_date( $date ) ? $date : '' ) as $key => $value ) {
			if ( '' !== $value ) {
				update_post_meta( $post_id, $key, $value );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}
);

/* Structured data from the same pieces -------------------------------------- */

/**
 * The FAQ on an entry: each Details block in the FAQ style, its summary and its answer as plain text.
 *
 * @param array $blocks Parsed blocks.
 * @return array<int,array{0:string,1:string}>
 */
function tbe_article_faq( array $blocks ): array {
	$items = array();
	foreach ( $blocks as $block ) {
		$class = (string) ( $block['attrs']['className'] ?? '' );
		if ( 'core/details' === ( $block['blockName'] ?? '' ) && preg_match( '/(?:^|\s)is-style-faq(?:\s|$)/', $class ) ) {
			if ( preg_match( '#<summary[^>]*>(.*?)</summary>#is', (string) $block['innerHTML'], $q ) ) {
				$answer = '';
				foreach ( (array) $block['innerBlocks'] as $inner ) {
					$answer .= ' ' . render_block( $inner );
				}
				$question = tbe_article_heading_text( $q[1] );
				$answer   = tbe_article_heading_text( $answer );
				if ( '' !== $question && '' !== $answer ) {
					$items[] = array( $question, $answer );
				}
			}
			continue;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$items = array_merge( $items, tbe_article_faq( (array) $block['innerBlocks'] ) );
		}
	}
	return $items;
}

/**
 * Whether the text holds an answer-box block that shows by itself: one with its own summary or points.
 *
 * @param array $blocks Parsed blocks.
 */
function tbe_article_has_answer_block( array $blocks ): bool {
	foreach ( $blocks as $block ) {
		if ( 'tbe/answer-box' === ( $block['blockName'] ?? '' ) && ( '' !== trim( (string) ( $block['attrs']['summary'] ?? '' ) ) || tbe_article_points( (string) ( $block['attrs']['points'] ?? '' ) ) ) ) {
			return true;
		}
		if ( ! empty( $block['innerBlocks'] ) && tbe_article_has_answer_block( (array) $block['innerBlocks'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Nodes the commentary page adds to its graph: the FAQPage when the entry shows questions, and the speakable parts
 * of the page (the dek and the answer box) when they are there.
 *
 * @param WP_Post $post    Entry.
 * @param string  $url     Page URL.
 * @param string  $page_id The WebPage node's @id.
 * @return array{nodes:array,speakable:string[]}
 */
function tbe_article_ld( WP_Post $post, string $url, string $page_id ): array {
	$nodes = array();
	$faq   = tbe_article_faq( parse_blocks( (string) $post->post_content ) );
	if ( $faq ) {
		$questions = array();
		foreach ( $faq as $item ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $item[0],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $item[1] ),
			);
		}
		$nodes[] = array(
			'@type'      => 'FAQPage',
			'@id'        => $url . '#faq',
			'url'        => $url,
			'name'       => get_the_title( $post ),
			'isPartOf'   => tbe_ld_ref( $page_id ),
			'mainEntity' => $questions,
		);
	}
	$speakable = array();
	if ( '' !== trim( (string) $post->post_excerpt ) ) {
		$speakable[] = '.tbe-dek';
	}
	if ( tbe_setting( 'answer_box' ) && ( '' !== trim( (string) get_post_meta( $post->ID, 'tbe_takeaways', true ) ) || tbe_article_has_answer_block( parse_blocks( (string) $post->post_content ) ) ) ) {
		$speakable[] = '.tbe-answer';
	}
	return array( 'nodes' => $nodes, 'speakable' => $speakable );
}

/* Assets ------------------------------------------------------------------- */

add_action(
	'wp_enqueue_scripts',
	static function () {
		$request = function_exists( 'tbe_request' ) ? tbe_request() : null;
		if ( $request && 'chapter' === $request['view'] ) {
			// The chapter page shows the related-essay cards (0.10.16).
			tbe_enqueue_theme_style( 'tbe-article', 'assets/css/article.css', array( 'tbe-polish' ) );
		}
		if ( ! is_singular( 'tbe_commentary' ) ) {
			return;
		}
		tbe_enqueue_theme_style( 'tbe-article', 'assets/css/article.css', array( 'tbe-polish' ) );
		if ( tbe_setting( 'toc' ) && 'sticky' === tbe_setting( 'toc_style' ) ) {
			tbe_enqueue_theme_script( 'tbe-article', 'assets/js/article.js', array(), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	},
	20
);
