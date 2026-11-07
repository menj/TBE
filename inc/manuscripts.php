<?php
/**
 * Manuscripts: the places where the early Greek manuscripts of the New Testament disagree, shown manuscript by
 * manuscript, at /manuscripts/ and /manuscripts/{passage}/.
 *
 * Data: data/manuscripts/passages.json, built by tools/build_manuscripts.py from the Center for New Testament
 * Restoration's transcriptions (Alan Bunning, CC BY-SA 4.0) and the public-domain English translations on its
 * translation pages. For each passage and each early manuscript that covers the place, the file says whether the
 * passage is written (p), the page survives without it (a), some of its verses are written (s), or the page is
 * lost (l); and it carries the manuscript's own text of the passage and of the verses either side.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

final class TBE_Manuscripts {

	/** The whole data file, or an empty list when it is missing. */
	public static function data(): array {
		static $data = null;
		if ( null === $data ) {
			$file = TBE_DIR . '/data/manuscripts/passages.json';
			$read = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
			$data = is_array( $read ) && isset( $read['passages'] ) ? $read : array( 'passages' => array() );
		}
		return $data;
	}

	/** @return array<string,array> The passages keyed by id, in the file's order. */
	public static function passages(): array {
		$out = array();
		foreach ( self::data()['passages'] as $p ) {
			if ( TBE_Bible::book( (string) $p['book'] ) ) {
				$out[ (string) $p['id'] ] = $p;
			}
		}
		return $out;
	}

	public static function passage( string $id ): ?array {
		return self::passages()[ $id ] ?? null;
	}

	public static function url( ?string $id = null ): string {
		return home_url( '/manuscripts/' . ( $id ? $id . '/' : '' ) );
	}

	/** "Mark 16:9–20", "John 7:53–8:11", "Mark 9:44, 46". */
	public static function label( array $p ): string {
		[ $c1, $v1, $c2, $v2 ] = array_map( 'intval', $p['ref'] );
		$book = TBE_Bible::book( (string) $p['book'] );
		$name = $book ? $book['name'] : (string) $p['book'];
		if ( ! empty( $p['only'] ) ) {
			return $name . ' ' . $c1 . ':' . implode( ', ', array_map( 'intval', $p['only'] ) );
		}
		if ( $c1 !== $c2 ) {
			return $name . ' ' . $c1 . ':' . $v1 . '–' . $c2 . ':' . $v2;
		}
		return TBE_Bible::label( (string) $p['book'], $c1, $v1, $v2 );
	}

	/** A data key such as "luke 1:1" as a reference, "Luke 1:1". */
	public static function key_label( string $key ): string {
		if ( ! preg_match( '/^([a-z0-9-]+) (\d+):(\d+)$/', $key, $m ) ) {
			return $key;
		}
		return TBE_Bible::label( $m[1], (int) $m[2], (int) $m[3] );
	}

	/** The passages that touch one chapter. */
	public static function for_chapter( string $slug, int $chapter ): array {
		return array_filter(
			self::passages(),
			static fn( $p ) => $p['book'] === $slug && (int) $p['ref'][0] <= $chapter && (int) $p['ref'][2] >= $chapter
		);
	}

	/**
	 * What each passage is: a title and one plain sentence. Written for the site; the counts come from the data.
	 *
	 * @return array{0:string,1:string}
	 */
	public static function about( string $id ): array {
		$about = array(
			'mark-16-9-20'   => array( __( 'The long ending of Mark', 'tbe-child' ), __( 'Twelve verses after Mark 16:8 that tell of Jesus appearing after the empty tomb, the commission, the signs that follow believers and the ascension.', 'tbe-child' ) ),
			'john-7-53-8-11' => array( __( 'The woman caught in adultery', 'tbe-child' ), __( 'The story that ends “Let him who is without sin among you cast the first stone”, set between John 7:52 and 8:12.', 'tbe-child' ) ),
			'luke-22-43-44'  => array( __( 'The angel and the sweat like blood', 'tbe-child' ), __( 'Two verses on the Mount of Olives: an angel strengthens Jesus, and his sweat falls like drops of blood.', 'tbe-child' ) ),
			'luke-23-34'     => array( __( '“Father, forgive them”', 'tbe-child' ), __( 'The prayer from the cross, “Father, forgive them, for they know not what they do”, at the start of Luke 23:34.', 'tbe-child' ) ),
			'matthew-6-13'   => array( __( 'The ending of the Lord’s Prayer', 'tbe-child' ), __( 'The closing words “For thine is the kingdom, and the power, and the glory, for ever. Amen.”', 'tbe-child' ) ),
			'1-john-5-7-8'   => array( __( 'The three witnesses in heaven', 'tbe-child' ), __( 'The words “in heaven, the Father, the Word, and the Holy Ghost: and these three are one”, the only verse that names the three together as one.', 'tbe-child' ) ),
			'john-5-4'       => array( __( 'The angel at the pool', 'tbe-child' ), __( 'The verse that explains the pool of Bethesda: an angel stirred the water, and the first one in was healed.', 'tbe-child' ) ),
			'acts-8-37'      => array( __( 'The Ethiopian’s confession', 'tbe-child' ), __( 'Philip’s condition for baptism and the answer “I believe that Jesus Christ is the Son of God.”', 'tbe-child' ) ),
			'matthew-17-21'  => array( __( 'Prayer and fasting', 'tbe-child' ), __( '“This kind goes not out but by prayer and fasting.”', 'tbe-child' ) ),
			'matthew-18-11'  => array( __( 'To save the lost', 'tbe-child' ), __( '“For the Son of man is come to save that which was lost.”', 'tbe-child' ) ),
			'matthew-23-14'  => array( __( 'Widows’ houses', 'tbe-child' ), __( 'A woe on those who devour widows’ houses and make long prayers for show.', 'tbe-child' ) ),
			'mark-7-16'      => array( __( 'Ears to hear', 'tbe-child' ), __( '“If any man have ears to hear, let him hear.”', 'tbe-child' ) ),
			'mark-9-44-46'   => array( __( 'Where the worm dies not', 'tbe-child' ), __( 'Two verses that repeat “where their worm dies not, and the fire is not quenched”, which also stands in Mark 9:48.', 'tbe-child' ) ),
			'mark-11-26'     => array( __( 'Forgiveness withheld', 'tbe-child' ), __( '“But if ye do not forgive, neither will your Father which is in heaven forgive your trespasses.”', 'tbe-child' ) ),
			'mark-15-28'     => array( __( 'Numbered with the transgressors', 'tbe-child' ), __( 'A verse that applies Isaiah 53:12 to the crucifixion.', 'tbe-child' ) ),
			'luke-17-36'     => array( __( 'Two in the field', 'tbe-child' ), __( '“Two men shall be in the field; the one shall be taken, and the other left.”', 'tbe-child' ) ),
			'luke-23-17'     => array( __( 'The release at the feast', 'tbe-child' ), __( 'The note that Pilate had to release one prisoner at the feast.', 'tbe-child' ) ),
			'acts-15-34'     => array( __( 'Silas stays', 'tbe-child' ), __( '“It pleased Silas to abide there still.”', 'tbe-child' ) ),
			'acts-28-29'     => array( __( 'The Jews depart', 'tbe-child' ), __( 'The Jews leave Paul in Rome, arguing among themselves.', 'tbe-child' ) ),
			'romans-16-24'   => array( __( 'A second grace', 'tbe-child' ), __( '“The grace of our Lord Jesus Christ be with you all. Amen.”', 'tbe-child' ) ),
		);
		return $about[ $id ] ?? array( $id, '' );
	}

	/**
	 * The name and date of a manuscript, by its number. Others show the number alone.
	 *
	 * @return array{0:string,1:string}
	 */
	public static function witness( string $sig ): array {
		$known = array(
			'01'  => array( __( 'Codex Sinaiticus', 'tbe-child' ), __( '4th century', 'tbe-child' ) ),
			'02'  => array( __( 'Codex Alexandrinus', 'tbe-child' ), __( '5th century', 'tbe-child' ) ),
			'03'  => array( __( 'Codex Vaticanus', 'tbe-child' ), __( '4th century', 'tbe-child' ) ),
			'04'  => array( __( 'Codex Ephraemi Rescriptus', 'tbe-child' ), __( '5th century', 'tbe-child' ) ),
			'05'  => array( __( 'Codex Bezae', 'tbe-child' ), __( 'about 400', 'tbe-child' ) ),
			'029' => array( __( 'Codex Borgianus', 'tbe-child' ), __( '5th century', 'tbe-child' ) ),
			'032' => array( __( 'Codex Washingtonianus', 'tbe-child' ), __( 'about 400', 'tbe-child' ) ),
			'P45' => array( __( 'Papyrus 45', 'tbe-child' ), __( '3rd century', 'tbe-child' ) ),
			'P46' => array( __( 'Papyrus 46', 'tbe-child' ), __( 'about 200', 'tbe-child' ) ),
			'P66' => array( __( 'Papyrus 66', 'tbe-child' ), __( 'about 200', 'tbe-child' ) ),
			'P69' => array( __( 'Papyrus 69', 'tbe-child' ), __( '3rd century', 'tbe-child' ) ),
			'P75' => array( __( 'Papyrus 75', 'tbe-child' ), __( '3rd century', 'tbe-child' ) ),
		);
		if ( isset( $known[ $sig ] ) ) {
			return $known[ $sig ];
		}
		$name = 'P' === $sig[0] ? sprintf( /* translators: %s: papyrus number */ __( 'Papyrus %s', 'tbe-child' ), substr( $sig, 1 ) ) : sprintf( /* translators: %s: manuscript number */ __( 'Manuscript %s', 'tbe-child' ), $sig );
		return array( $name, '' );
	}

	/**
	 * The photographs of one passage's manuscripts, from data/manuscripts/images.json: only those fetched (with a
	 * licence) whose file is in the theme.
	 */
	public static function images( string $id ): array {
		static $all = null;
		if ( null === $all ) {
			$file = TBE_DIR . '/data/manuscripts/images.json';
			$read = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
			$all  = is_array( $read ) ? (array) ( $read['images'] ?? array() ) : array();
		}
		return array_values(
			array_filter(
				$all,
				static fn( $i ) => ( $i['passage'] ?? '' ) === $id && ! empty( $i['license'] ) && ! empty( $i['src'] ) && is_readable( TBE_DIR . '/' . $i['src'] )
			)
		);
	}

	/** The printed editions, in the order shown. */
	public static function editions(): array {
		return array(
			'KJTR' => __( 'Textus Receptus (the Greek behind the King James Version)', 'tbe-child' ),
			'RP'   => __( 'Byzantine text (Robinson and Pierpont)', 'tbe-child' ),
			'WH'   => __( 'Westcott and Hort (1881)', 'tbe-child' ),
			'SR'   => __( 'Statistical Restoration (CNTR)', 'tbe-child' ),
		);
	}

	/** How many early manuscripts have the passage, lack it, have part of it. @return array{p:int,a:int,s:int,l:int} */
	public static function counts( array $p ): array {
		$c = array( 'p' => 0, 'a' => 0, 's' => 0, 'l' => 0 );
		foreach ( (array) $p['mss'] as $m ) {
			++$c[ $m['s'] ];
		}
		return $c;
	}

	/** The word for a state. */
	public static function state_label( string $state ): string {
		switch ( $state ) {
			case 'p':
				return __( 'Has it', 'tbe-child' );
			case 'a':
				return __( 'Does not have it', 'tbe-child' );
			case 's':
				return __( 'Has some of it', 'tbe-child' );
			default:
				return __( 'Page lost', 'tbe-child' );
		}
	}

	/** Manuscripts in a sensible order: the great codices by number, then the papyri, then the rest. */
	public static function order( array $mss ): array {
		uksort(
			$mss,
			static function ( $a, $b ) {
				$rank = static fn( $s ) => 'P' === $s[0] ? array( 1, (int) substr( $s, 1 ) ) : array( 0, (int) $s );
				return $rank( (string) $a ) <=> $rank( (string) $b );
			}
		);
		return $mss;
	}
}

/** The credit every manuscripts page carries, as the CNTR licence asks. */
function tbe_manuscripts_credit(): string {
	return '<p class="tbe-muted tbe-ms__credit">' . wp_kses_post(
		sprintf(
			/* translators: 1: link to CNTR, 2: link to the licence */
			__( 'Manuscript transcriptions by Alan Bunning and the %1$s, used under %2$s. The tables on this page are built from them and are shared under the same licence. English wording from the public-domain KJV, World English Bible and Berean Standard Bible.', 'tbe-child' ),
			'<a href="https://greekcntr.org/manuscripts/" rel="noopener">Center for New Testament Restoration</a>',
			'<a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="noopener">CC BY-SA 4.0</a>'
		)
	) . '</p>';
}

/** The photographs of a passage's manuscripts, each with its caption and its Commons credit. */
function tbe_manuscripts_gallery( string $id ): string {
	$images = TBE_Manuscripts::images( $id );
	if ( ! $images ) {
		return '';
	}
	$uri = defined( 'TBE_URI' ) ? TBE_URI : '';
	$out = '<h2 class="tbe-ornament">' . esc_html__( 'The pages themselves', 'tbe-child' ) . '</h2><div class="tbe-ms__gallery">';
	foreach ( $images as $i ) {
		[ $name ] = TBE_Manuscripts::witness( (string) $i['ms'] );
		$credit   = sprintf(
			/* translators: 1: licence, 2: link to the file on Wikimedia Commons */
			__( '%1$s, from %2$s.', 'tbe-child' ),
			esc_html( (string) $i['license'] ),
			'<a href="' . esc_url( (string) $i['page'] ) . '" rel="noopener">' . esc_html__( 'Wikimedia Commons', 'tbe-child' ) . '</a>'
		);
		$out .= '<figure class="tbe-ms__figure"><a href="' . esc_url( (string) $i['page'] ) . '" rel="noopener"><img src="' . esc_url( $uri . '/' . $i['src'] ) . '" width="' . (int) $i['width'] . '" height="' . (int) $i['height'] . '" loading="lazy" decoding="async" alt="' . esc_attr( sprintf( /* translators: %s: manuscript name */ __( 'A page of %s', 'tbe-child' ), $name ) ) . '"></a>'
			. '<figcaption>' . esc_html( (string) $i['caption'] ) . ' <span class="tbe-muted">' . $credit . '</span></figcaption></figure>';
	}
	return $out . '</div>';
}

/** /manuscripts/: every passage, with how many early manuscripts have it. */
function tbe_view_manuscripts(): string {
	$out  = '<div class="tbe-reader tbe-ms">';
	$out .= '<h1 class="tbe-title">' . esc_html__( 'Manuscripts', 'tbe-child' ) . '</h1>';
	$out .= '<p class="tbe-center tbe-muted">' . esc_html__( 'Places where the Greek manuscripts of the New Testament disagree: verses and words that the King James Version prints and the earliest surviving copies leave out. Each page sets the manuscripts side by side, in their own words, line by line.', 'tbe-child' ) . '</p>';
	$passages = TBE_Manuscripts::passages();
	if ( ! $passages ) {
		return $out . '<p class="tbe-notice">' . esc_html__( 'The manuscript data has not been built yet.', 'tbe-child' ) . '</p></div>';
	}
	$out .= '<div class="tbe-tablewrap" role="region" tabindex="0" aria-label="' . esc_attr__( 'Passages', 'tbe-child' ) . '"><table class="tbe-table tbe-ms__index"><thead><tr><th scope="col">' . esc_html__( 'Passage', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'Early manuscripts that have it', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'That do not', 'tbe-child' ) . '</th></tr></thead><tbody>';
	foreach ( $passages as $id => $p ) {
		[ $title ] = TBE_Manuscripts::about( $id );
		$c         = TBE_Manuscripts::counts( $p );
		$out      .= '<tr><td><a href="' . esc_url( TBE_Manuscripts::url( $id ) ) . '">' . esc_html( TBE_Manuscripts::label( $p ) ) . '</a><br><span class="tbe-muted">' . esc_html( $title ) . '</span></td>'
			. '<td>' . (int) ( $c['p'] + $c['s'] ) . '</td><td>' . (int) $c['a'] . '</td></tr>';
	}
	$out .= '</tbody></table></div>';
	$out .= '<p class="tbe-muted">' . esc_html__( 'Early manuscripts are the Greek copies made up to about AD 400 that survive at this place. A copy whose page is lost here is not counted either way.', 'tbe-child' ) . '</p>';
	return $out . tbe_manuscripts_credit() . '</div>';
}

/** /manuscripts/{passage}/: the evidence, manuscript by manuscript. */
function tbe_view_manuscript_passage( array $p ): string {
	$id               = (string) $p['id'];
	[ $title, $what ] = TBE_Manuscripts::about( $id );
	[ $c1 ]           = array_map( 'intval', $p['ref'] );
	$c                = TBE_Manuscripts::counts( $p );

	$out  = '<div class="tbe-reader tbe-ms tbe-ms--passage">';
	$out .= '<nav class="tbe-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'tbe-child' ) . '"><a href="' . esc_url( TBE_Manuscripts::url() ) . '">' . esc_html__( 'Manuscripts', 'tbe-child' ) . '</a></nav>';
	$out .= '<p class="tbe-eyebrow">' . esc_html( TBE_Manuscripts::label( $p ) ) . '</p>';
	$out .= '<h1 class="tbe-title">' . esc_html( $title ) . '</h1>';
	$out .= '<p class="tbe-center">' . esc_html( $what ) . '</p>';
	$out .= '<p class="tbe-center tbe-ms__summary"><strong>' . esc_html(
		sprintf(
			/* translators: 1: manuscripts that have it, 2: manuscripts that do not */
			__( 'Of the early manuscripts that survive here, %1$d have it and %2$d do not.', 'tbe-child' ),
			$c['p'] + $c['s'],
			$c['a']
		)
	) . '</strong></p>';
	$out .= '<p class="tbe-center"><a class="tbe-btn" href="' . esc_url( TBE_Bible::url( (string) $p['book'], $c1, (int) $p['ref'][1] ) ) . '">' . esc_html__( 'Read it in the Bible', 'tbe-child' ) . '</a></p>';

	// The wording in question, in English.
	$en = (array) ( $p['en'] ?? array() );
	if ( $en ) {
		$out .= '<h2 class="tbe-ornament">' . esc_html__( 'The words in question', 'tbe-child' ) . '</h2><div class="tbe-ms__en">';
		foreach ( $en as $key => $tr ) {
			$kjv = (string) ( $tr['KJV'] ?? '' );
			if ( '' === $kjv ) {
				continue;
			}
			$out .= '<p><span class="tbe-vn">' . esc_html( TBE_Manuscripts::key_label( (string) $key ) ) . '</span> ' . esc_html( $kjv ) . '</p>';
		}
		$long = count( $en ) > 2;
		$out .= '<p class="tbe-muted">' . esc_html__( 'King James Version. Modern translations made from the early manuscripts print these words in brackets, move them to a footnote, or leave them out.', 'tbe-child' ) . '</p>';
		$out .= ( $long ? '<details class="tbe-ms__more"><summary>' . esc_html__( 'Two modern translations', 'tbe-child' ) . '</summary>' : '' ) . '<ul class="tbe-ms__modern">';
		foreach ( array( 'WEB' => __( 'World English Bible', 'tbe-child' ), 'BSB' => __( 'Berean Standard Bible', 'tbe-child' ) ) as $code => $name ) {
			$texts = array();
			foreach ( $en as $key => $tr ) {
				$texts[] = (string) ( $tr[ $code ] ?? '' );
			}
			$has  = array_filter( $texts, static fn( $t ) => '' !== $t );
			$out .= '<li><strong>' . esc_html( $name ) . ':</strong> ' . ( $has ? esc_html( implode( ' ', $has ) ) : '<em>' . esc_html__( 'not printed in the text', 'tbe-child' ) . '</em>' ) . '</li>';
		}
		$out .= '</ul>' . ( $long ? '</details>' : '' ) . '</div>';
	}

	$out .= tbe_manuscripts_gallery( $id );

	// The table.
	$out .= '<h2 class="tbe-ornament">' . esc_html__( 'The early manuscripts', 'tbe-child' ) . '</h2>';
	$out .= '<div class="tbe-tablewrap" role="region" tabindex="0" aria-label="' . esc_attr__( 'Early manuscripts', 'tbe-child' ) . '"><table class="tbe-table tbe-ms__table"><thead><tr><th scope="col">' . esc_html__( 'Manuscript', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'Date', 'tbe-child' ) . '</th><th scope="col">' . esc_html__( 'This passage', 'tbe-child' ) . '</th></tr></thead><tbody>';
	foreach ( TBE_Manuscripts::order( (array) $p['mss'] ) as $sig => $m ) {
		[ $name, $date ] = TBE_Manuscripts::witness( (string) $sig );
		$note = ! empty( $m['c'] ) ? ' <span class="tbe-muted">' . esc_html__( '(a later hand corrected it)', 'tbe-child' ) . '</span>' : '';
		$out .= '<tr class="tbe-ms__row tbe-ms__row--' . esc_attr( (string) $m['s'] ) . '"><th scope="row"><a href="#ms-' . esc_attr( (string) $sig ) . '">' . esc_html( $name ) . '</a> <span class="tbe-muted">' . esc_html( (string) $sig ) . '</span></th><td>' . esc_html( $date ) . '</td><td><span class="tbe-ms__state">' . esc_html( TBE_Manuscripts::state_label( (string) $m['s'] ) ) . '</span>' . $note . '</td></tr>';
	}
	$out .= '</tbody></table></div>';

	// The printed editions.
	$out .= '<h2 class="tbe-ornament">' . esc_html__( 'The printed Greek editions', 'tbe-child' ) . '</h2><ul class="tbe-ms__eds">';
	foreach ( TBE_Manuscripts::editions() as $code => $name ) {
		$state = (string) ( $p['eds'][ $code ] ?? '' );
		if ( '' === $state ) {
			continue;
		}
		$out .= '<li class="tbe-ms__ed tbe-ms__ed--' . esc_attr( $state ) . '"><strong>' . esc_html( $name ) . ':</strong> ' . esc_html( 'p' === $state ? __( 'prints it as part of the text', 'tbe-child' ) : __( 'does not print it as part of the text', 'tbe-child' ) ) . '</li>';
	}
	$out .= '</ul><p class="tbe-muted">' . esc_html__( 'Westcott and Hort print some of these passages in double brackets, as later additions; here that counts as not part of the text.', 'tbe-child' ) . '</p>';

	// Each manuscript's own words.
	$out .= '<h2 class="tbe-ornament">' . esc_html__( 'What each manuscript says', 'tbe-child' ) . '</h2>';
	$out .= '<p class="tbe-muted">' . esc_html__( 'The first scribe’s text, line by line as it stands on the page, in the capital-less Greek of the transcription. The verses either side are shown so you can see where the passage would go.', 'tbe-child' ) . '</p>';
	foreach ( TBE_Manuscripts::order( (array) $p['mss'] ) as $sig => $m ) {
		if ( empty( $m['t'] ) ) {
			continue;
		}
		[ $name, $date ] = TBE_Manuscripts::witness( (string) $sig );
		$out .= '<details class="tbe-ms__text" id="ms-' . esc_attr( (string) $sig ) . '"><summary><strong>' . esc_html( $name ) . '</strong> <span class="tbe-muted">' . esc_html( trim( $sig . ( '' !== $date ? ', ' . $date : '' ) ) ) . '</span> · ' . esc_html( TBE_Manuscripts::state_label( (string) $m['s'] ) ) . '</summary>';
		foreach ( (array) $m['t'] as $key => $lines ) {
			$inside = isset( $m['v'][ preg_replace( '/^[a-z0-9-]+ /', '', (string) $key ) ] ) && 0 === strpos( (string) $key, (string) $p['book'] . ' ' );
			$out   .= '<div class="tbe-ms__verse' . ( $inside ? ' tbe-ms__verse--in' : '' ) . '"><p class="tbe-ms__ref">' . esc_html( TBE_Manuscripts::key_label( (string) $key ) ) . '</p><p class="tbe-ms__greek" lang="grc">' . implode( '<br>', array_map( 'esc_html', (array) $lines ) ) . '</p></div>';
		}
		if ( 'a' === $m['s'] ) {
			$out .= '<p class="tbe-ms__gap">' . esc_html__( 'The passage is not in this manuscript: the text runs straight from the verse before it to the verse after.', 'tbe-child' ) . '</p>';
		}
		$out .= '</details>';
	}
	return $out . tbe_manuscripts_credit() . '</div>';
}

/** Under a chapter's heading: a line for each manuscripts page about it. */
function tbe_manuscripts_chapter_note( string $slug, int $chapter ): string {
	$out = '';
	foreach ( TBE_Manuscripts::for_chapter( $slug, $chapter ) as $id => $p ) {
		$c    = TBE_Manuscripts::counts( $p );
		$out .= '<li><a href="' . esc_url( TBE_Manuscripts::url( $id ) ) . '">' . esc_html( TBE_Manuscripts::label( $p ) ) . '</a>: ' . esc_html(
			sprintf(
				/* translators: 1: manuscripts that have it, 2: manuscripts that do not */
				__( '%1$d early manuscripts have it, %2$d do not', 'tbe-child' ),
				$c['p'] + $c['s'],
				$c['a']
			)
		) . '</li>';
	}
	return '' === $out ? '' : '<aside class="tbe-ms__note" aria-label="' . esc_attr__( 'Manuscripts', 'tbe-child' ) . '"><p><strong>' . esc_html__( 'The manuscripts differ here.', 'tbe-child' ) . '</strong></p><ul>' . $out . '</ul></aside>';
}
