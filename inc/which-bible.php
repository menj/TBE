<?php
/**
 * "Which Bible?" (0.10.28): one page at /which-bible/ that sets out, from the site's own data, how far "the
 * Bible" is from being one book: how many books each tradition counts, which Hebrew text stands underneath, which
 * verses the earliest manuscripts lack, and which books were once read as scripture and later dropped. Every claim
 * links to the page of the site that shows the evidence. The shareable graphic for the page is
 * assets/img/which-bible.png, drawn by tools/build_which_bible_graphic.js.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** The page's address. */
function tbe_which_bible_url(): string {
	return home_url( '/which-bible/' );
}

/**
 * How many differences of meaning and of spelling the Samaritan Pentateuch comparison counts, summed over
 * data/sp/ and kept for a day. @return array{0:int,1:int}
 */
function tbe_which_bible_sp_counts(): array {
	$key    = 'tbe_sp_counts_' . TBE_VERSION;
	$cached = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$m = 0;
	$s = 0;
	foreach ( (array) glob( TBE_DIR . '/data/sp/*/*.json' ) as $file ) {
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( (array) ( $data['v'] ?? array() ) as $items ) {
			foreach ( (array) $items as $item ) {
				'm' === ( $item['k'] ?? '' ) ? ++$m : ++$s;
			}
		}
	}
	$counts = array( $m, $s );
	if ( function_exists( 'set_transient' ) ) {
		set_transient( $key, $counts, DAY_IN_SECONDS );
	}
	return $counts;
}

/** The canon rows: tradition key, name, book count as shown, and what the count rests on. @return array<int,array> */
function tbe_which_bible_canons(): array {
	$names = TBE_Canon::data()['traditions'];
	$rows  = array(
		array( 'samaritan', '5', __( 'The five books of the Torah, in the Samaritans’ own Hebrew text.', 'tbe-child' ) ),
		array( 'jewish', '24', __( 'The Tanakh: the same writings as the Protestant Old Testament, counted and ordered differently. Josephus counts 22.', 'tbe-child' ) ),
		array( 'protestant', '66', __( '39 books of the Old Testament and 27 of the New.', 'tbe-child' ) ),
		array( 'catholic', '73', __( 'The 66 and seven deuterocanonical books, fixed by the Council of Trent in 1546.', 'tbe-child' ) ),
		array( 'orthodox', '76+', __( 'The Catholic books and more from the Septuagint; the lists differ between Orthodox churches.', 'tbe-child' ) ),
		array( 'ethiopian', '81', __( 'The widest canon, with 1 Enoch, Jubilees and 1 to 3 Meqabyan; a narrower and a broader list are distinguished.', 'tbe-child' ) ),
	);
	$out = array();
	foreach ( $rows as [ $key, $count, $note ] ) {
		$out[] = array(
			'key'   => $key,
			'name'  => (string) ( $names[ $key ]['full'] ?? $key ),
			'count' => $count,
			'note'  => $note,
		);
	}
	return $out;
}

/** The page. */
function tbe_view_which_bible(): string {
	[ $meaning, $spelling ] = tbe_which_bible_sp_counts();
	$passages = TBE_Manuscripts::passages();
	$link     = static fn( string $url, string $text ): string => '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';

	$out  = '<div class="tbe-reader tbe-which">';
	$out .= '<p class="tbe-eyebrow">' . esc_html__( 'One book?', 'tbe-child' ) . '</p>';
	$out .= '<h1 class="tbe-title">' . esc_html__( 'Which Bible?', 'tbe-child' ) . '</h1>';
	$out .= '<p class="tbe-title-sub">' . esc_html__( 'Christians speak of “the Bible” as one book, fixed and agreed. The churches do not agree on how many books it has, the oldest copies do not agree on what those books say, and some books once read as scripture were later dropped. Every point below links to the evidence on this site.', 'tbe-child' ) . '</p>';

	$out .= '<figure class="tbe-which__graphic"><a href="' . esc_url( TBE_URI . '/assets/img/which-bible.png' ) . '"><img src="' . esc_url( TBE_URI . '/assets/img/which-bible.png' ) . '" width="1080" height="1350" loading="lazy" alt="' . esc_attr__( 'The graphic: “1 Bible”, then 66 books, then 66 and 73, then 5, 24, 66, 73, 76 and 81, then the oldest copies end Mark at 16:8.', 'tbe-child' ) . '"></a><figcaption>' . esc_html__( 'Share the graphic: open it and save it.', 'tbe-child' ) . '</figcaption></figure>';

	// 1. How many books?
	$out .= '<h2 class="tbe-ornament" id="books">' . esc_html__( 'How many books?', 'tbe-child' ) . '</h2>';
	$out .= '<div class="tbe-tablewrap"><table class="tbe-table tbe-which__canons"><thead><tr><th scope="col">' . esc_html__( 'Tradition', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'Books', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'What it counts', 'tbe-child' ) . '</th></tr></thead><tbody>';
	foreach ( tbe_which_bible_canons() as $row ) {
		$out .= '<tr><th scope="row">' . esc_html( $row['name'] ) . '</th><td class="tbe-which__count">' . esc_html( $row['count'] ) . '</td><td>' . esc_html( $row['note'] ) . '</td></tr>';
	}
	$out .= '</tbody></table></div>';
	$out .= '<p>' . wp_kses_post( sprintf( /* translators: %s: link to the book list */ __( 'Each tradition’s list, with the books beyond the 66 that can be read here, is on %s.', 'tbe-child' ), $link( TBE_Bible::url() . '#deuterocanon', __( 'the book list', 'tbe-child' ) ) ) ) . '</p>';

	// 2. Which text?
	$out .= '<h2 class="tbe-ornament" id="text">' . esc_html__( 'Which text?', 'tbe-child' ) . '</h2>';
	$out .= '<p>' . wp_kses_post(
		sprintf(
			/* translators: 1: meaning differences, 2: spelling differences, 3: link to Genesis 1 interlinear, 4: link to Deuteronomy 27 interlinear */
			__( 'Even the five books every tradition shares come down in more than one Hebrew text. The Samaritan Pentateuch differs from the Masoretic text, the Hebrew behind most Bibles, in %1$s places of meaning and %2$s of spelling, marked word by word on the interlinear (%3$s). At Deuteronomy 27:4 one text has the altar built on Mount Ebal, the other on Mount Gerizim (%4$s).', 'tbe-child' ),
			esc_html( number_format_i18n( $meaning ) ),
			esc_html( number_format_i18n( $spelling ) ),
			$link( TBE_Interlinear::url( 'genesis', 1 ), __( 'start at Genesis 1', 'tbe-child' ) ),
			$link( TBE_Interlinear::url( 'deuteronomy', 27 ) . '#v4', __( 'see the verse', 'tbe-child' ) )
		)
	) . '</p>';
	$out .= '<p>' . wp_kses_post( sprintf( /* translators: %s: link to Isaiah 1 interlinear */ __( 'The Dead Sea Scrolls add a third witness: the Great Isaiah Scroll, a thousand years older than the oldest complete Masoretic manuscript, differs from it again (%s). The Greek Septuagint, the Old Testament the New Testament writers quote most, differs once more.', 'tbe-child' ), $link( TBE_Interlinear::url( 'isaiah', 1 ), __( 'Isaiah 1, compared', 'tbe-child' ) ) ) ) . '</p>';

	// 3. Which verses?
	$out .= '<h2 class="tbe-ornament" id="verses">' . esc_html__( 'Which verses?', 'tbe-child' ) . '</h2>';
	$out .= '<p>' . wp_kses_post( sprintf( /* translators: 1: number of passages, 2: link to the manuscripts section */ __( 'The New Testament the churches read contains passages the earliest Greek manuscripts do not have. The %2$s follows %1$s of them, manuscript by manuscript. Among them:', 'tbe-child' ), esc_html( number_format_i18n( count( $passages ) ) ), $link( TBE_Manuscripts::url(), __( 'manuscripts section', 'tbe-child' ) ) ) ) . '</p><ul>';
	$picks = array(
		'mark-16-9-20'   => __( 'The ending of Mark: Codex Sinaiticus and Codex Vaticanus, the two oldest nearly complete Bibles, end the gospel at 16:8, with the women afraid and no risen Jesus seen.', 'tbe-child' ),
		'john-7-53-8-11' => __( 'The woman caught in adultery: absent from the earliest papyri and from Sinaiticus and Vaticanus.', 'tbe-child' ),
		'1-john-5-7-8'   => __( 'The three witnesses, the clearest statement of the Trinity in the King James Version: in no Greek manuscript before the late Middle Ages.', 'tbe-child' ),
		'luke-23-34'     => __( '“Father, forgive them”: Sinaiticus has it, Vaticanus does not.', 'tbe-child' ),
	);
	foreach ( $picks as $id => $text ) {
		if ( isset( $passages[ $id ] ) ) {
			$out .= '<li>' . $link( TBE_Manuscripts::url( $id ), TBE_Manuscripts::label( $passages[ $id ] ) ) . ': ' . esc_html( $text ) . '</li>';
		}
	}
	$out .= '</ul>';

	// 4. Which books were scripture?
	$out .= '<h2 class="tbe-ornament" id="dropped">' . esc_html__( 'Which books were once scripture?', 'tbe-child' ) . '</h2>';
	$dropped = array();
	foreach ( array( 'hermas', 'apocalypse-of-peter', 'laodiceans' ) as $slug ) {
		$book = class_exists( 'TBE_Further' ) ? TBE_Further::book( $slug ) : null;
		if ( $book ) {
			$dropped[] = $link( TBE_Bible::url( $slug ), (string) $book['name'] );
		}
	}
	$out .= '<p>' . wp_kses_post(
		sprintf(
			/* translators: %s: links to the books */
			__( 'Some books were read in churches as scripture and are in no Bible today. Codex Sinaiticus, the oldest complete New Testament, includes the Shepherd of Hermas; the Muratorian fragment accepts the Apocalypse of Peter; Laodiceans stood in Latin Bibles for a thousand years. They can be read here: %s.', 'tbe-child' ),
			implode( ', ', $dropped )
		)
	) . '</p>';

	$out .= '<h2 class="tbe-ornament" id="so">' . esc_html__( 'So which one?', 'tbe-child' ) . '</h2>';
	$out .= '<p>' . esc_html__( 'Five books, twenty-four, sixty-six, seventy-three, seventy-six or eighty-one; the Masoretic text, the Samaritan, the scrolls or the Greek; Mark with its ending or without. Each tradition answers differently, and each answer was made by people, in councils and copying rooms, centuries after the books were written.', 'tbe-child' ) . '</p>';
	return $out . '</div>';
}
