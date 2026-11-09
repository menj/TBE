<?php
/**
 * The Strong's lexicon: a page for every Hebrew and Greek Strong's number at /lexicon/{number}/, lists by letter
 * at /lexicon/greek/{letter}/ and /lexicon/hebrew/{letter}/, and Metzger's list of the Greek words by frequency
 * at /lexicon/greek/frequency/.
 *
 * Data: data/lexicon/{g|h}/{n}.json and data/lexicon/{g|h}-index.json, built by tools/build_lexicon.py from
 * STEPBible's brief lexicons (TBESG and TBESH, CC BY 4.0) and the interlinear; data/lexicon/metzger.json, built
 * by tools/build_metzger.py from Bruce M. Metzger, Lexical Aids for Students of New Testament Greek (used by the
 * owner's permission).
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

final class TBE_Lexicon {

	/** "G3056", "H0430" from "g3056", "G3056", "h430" and the like; '' when it is not a Strong's number. */
	public static function normalise( string $num ): string {
		if ( ! preg_match( '/^([gh])0*(\d{1,4})$/i', trim( $num ), $m ) ) {
			return '';
		}
		return strtoupper( $m[1] ) . str_pad( $m[2], 4, '0', STR_PAD_LEFT );
	}

	/** The entry for a number: senses, occurrences and references; null when there is none. */
	public static function entry( string $num ): ?array {
		$num = self::normalise( $num );
		if ( '' === $num ) {
			return null;
		}
		static $shards = array();
		$key = strtolower( $num[0] ) . '/' . intdiv( (int) substr( $num, 1 ), 100 );
		if ( ! isset( $shards[ $key ] ) ) {
			$file            = TBE_DIR . '/data/lexicon/' . $key . '.json';
			$read            = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
			$shards[ $key ] = is_array( $read ) ? $read : array();
		}
		$entry = $shards[ $key ][ $num ] ?? null;
		return is_array( $entry ) && ! empty( $entry['s'] ) ? $entry + array( 'num' => $num ) : null;
	}

	public static function url( string $num = '' ): string {
		$num = self::normalise( $num );
		return home_url( '/lexicon/' . ( '' !== $num ? strtolower( $num ) . '/' : '' ) );
	}

	/** 'greek' or 'hebrew' for a number. */
	public static function language( string $num ): string {
		return 'H' === strtoupper( $num[0] ?? '' ) ? 'hebrew' : 'greek';
	}

	/** Every number of one language, as [number, word, gloss, count], sorted by the word. */
	public static function index( string $lang ): array {
		static $cache = array();
		if ( ! isset( $cache[ $lang ] ) ) {
			$file            = TBE_DIR . '/data/lexicon/' . ( 'hebrew' === $lang ? 'h' : 'g' ) . '-index.json';
			$read            = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
			$cache[ $lang ] = is_array( $read ) ? $read : array();
		}
		return $cache[ $lang ];
	}

	/** The letters of each alphabet, with the name used in the address. @return array<string,string> name => letter */
	public static function letters( string $lang ): array {
		if ( 'hebrew' === $lang ) {
			return array( 'aleph' => 'א', 'bet' => 'ב', 'gimel' => 'ג', 'dalet' => 'ד', 'he' => 'ה', 'vav' => 'ו', 'zayin' => 'ז', 'chet' => 'ח', 'tet' => 'ט', 'yod' => 'י', 'kaf' => 'כ', 'lamed' => 'ל', 'mem' => 'מ', 'nun' => 'נ', 'samekh' => 'ס', 'ayin' => 'ע', 'pe' => 'פ', 'tsadi' => 'צ', 'qof' => 'ק', 'resh' => 'ר', 'shin' => 'ש', 'tav' => 'ת' );
		}
		return array( 'alpha' => 'α', 'beta' => 'β', 'gamma' => 'γ', 'delta' => 'δ', 'epsilon' => 'ε', 'zeta' => 'ζ', 'eta' => 'η', 'theta' => 'θ', 'iota' => 'ι', 'kappa' => 'κ', 'lambda' => 'λ', 'mu' => 'μ', 'nu' => 'ν', 'xi' => 'ξ', 'omicron' => 'ο', 'pi' => 'π', 'rho' => 'ρ', 'sigma' => 'σ', 'tau' => 'τ', 'upsilon' => 'υ', 'phi' => 'φ', 'chi' => 'χ', 'psi' => 'ψ', 'omega' => 'ω' );
	}

	/** The first letter of a word, accents, breathings and points removed, final forms folded. */
	public static function initial( string $word ): string {
		$plain = class_exists( 'Normalizer' ) ? (string) Normalizer::normalize( $word, Normalizer::FORM_D ) : $word;
		$plain = (string) preg_replace( '/[\p{Mn}\x{0591}-\x{05C7}\s\-ʼ\'’,]+/u', '', $plain );
		$first = mb_strtolower( mb_substr( $plain, 0, 1 ) );
		return strtr( $first, array( 'ς' => 'σ', 'ך' => 'כ', 'ם' => 'מ', 'ן' => 'נ', 'ף' => 'פ', 'ץ' => 'צ' ) );
	}

	/** The rows of the index that start with a letter. */
	public static function by_letter( string $lang, string $letter ): array {
		return array_values( array_filter( self::index( $lang ), static fn( $r ) => self::initial( (string) $r[1] ) === $letter ) );
	}

	/** A part-of-speech code from the lexicon ("G:N-M", "H:V") in words. */
	public static function part( string $code ): string {
		$code  = preg_replace( '/^[GH]:/', '', $code );
		$parts = explode( '-', (string) $code );
		$names = array(
			'N'    => __( 'noun', 'tbe-child' ),
			'V'    => __( 'verb', 'tbe-child' ),
			'A'    => __( 'adjective', 'tbe-child' ),
			'ADV'  => __( 'adverb', 'tbe-child' ),
			'CONJ' => __( 'conjunction', 'tbe-child' ),
			'PREP' => __( 'preposition', 'tbe-child' ),
			'T'    => __( 'article', 'tbe-child' ),
			'P'    => __( 'pronoun', 'tbe-child' ),
			'R'    => __( 'pronoun', 'tbe-child' ),
			'D'    => __( 'pronoun', 'tbe-child' ),
			'I'    => __( 'pronoun', 'tbe-child' ),
			'INJ'  => __( 'interjection', 'tbe-child' ),
			'PRT'  => __( 'particle', 'tbe-child' ),
			'COND' => __( 'conditional', 'tbe-child' ),
			'NEG'  => __( 'negative', 'tbe-child' ),
			'ARAM' => __( 'Aramaic', 'tbe-child' ),
			'HEB'  => __( 'Hebrew', 'tbe-child' ),
		);
		$genders = array( 'M' => __( 'masculine', 'tbe-child' ), 'F' => __( 'feminine', 'tbe-child' ), 'N' => __( 'neuter', 'tbe-child' ) );
		$words   = array();
		if ( isset( $names[ $parts[0] ?? '' ] ) ) {
			$words[] = $names[ $parts[0] ];
		}
		if ( 'N' === ( $parts[0] ?? '' ) && isset( $genders[ $parts[1] ?? '' ] ) ) {
			$words[] = $genders[ $parts[1] ];
		}
		if ( in_array( 'L', $parts, true ) || in_array( 'P', array_slice( $parts, 1 ), true ) ) {
			$words[] = in_array( 'L', $parts, true ) ? __( 'place name', 'tbe-child' ) : __( 'personal name', 'tbe-child' );
		}
		return implode( ', ', $words );
	}

	/** A definition as safe HTML: its <b>, <i> and line breaks, and [[slug c v|label]] as links to the reader. */
	public static function definition( string $text ): string {
		$html = wp_kses( $text, array( 'b' => array(), 'i' => array() ) );
		$html = (string) preg_replace_callback(
			'/\[\[([a-z0-9-]+) (\d+) (\d+)\|([^\]]*)\]\]/',
			static fn( $m ) => TBE_Bible::book( $m[1] ) ? '<a class="tbe-ref" href="' . esc_url( TBE_Bible::url( $m[1], (int) $m[2], (int) $m[3] ) ) . '">' . $m[4] . '</a>' : $m[4],
			$html
		);
		return nl2br( $html, false );
	}

	/**
	 * The Brown-Driver-Briggs article for a Hebrew number, as paragraphs of simple HTML built by tools/build_bdb.py
	 * (Hebrew spans, glosses in italics, sense numbers in bold); an empty list when there is none (0.10.22).
	 *
	 * @return string[]
	 */
	public static function bdb( string $num ): array {
		$num = self::normalise( $num );
		if ( '' === $num || 'H' !== $num[0] ) {
			return array();
		}
		static $shards = array();
		$key = intdiv( (int) substr( $num, 1 ), 100 );
		if ( ! isset( $shards[ $key ] ) ) {
			$file           = TBE_DIR . '/data/lexicon/bdb/' . $key . '.json';
			$read           = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
			$shards[ $key ] = is_array( $read ) ? $read : array();
		}
		return array_values( array_filter( (array) ( $shards[ $key ][ $num ] ?? array() ), 'is_string' ) );
	}

	/** Metzger's entry for a Greek number: his frequency group, gloss and page; null when he does not list it. */
	public static function metzger( string $num ): ?array {
		static $data = null;
		if ( null === $data ) {
			$file = TBE_DIR . '/data/lexicon/metzger.json';
			$read = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
			$data = is_array( $read ) ? $read : array();
		}
		$e = $data['words'][ self::normalise( $num ) ] ?? null;
		return is_array( $e ) ? $e : null;
	}

	/** Metzger's whole list, in his order: groups of words by how often they occur. */
	public static function metzger_groups(): array {
		$file = TBE_DIR . '/data/lexicon/metzger.json';
		$read = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_array( $read ) ? (array) ( $read['groups'] ?? array() ) : array();
	}
}

/** The credit on every lexicon page. */
function tbe_lexicon_credit(): string {
	return '<p class="tbe-muted tbe-lex__credit">' . wp_kses_post(
		sprintf(
			/* translators: %s: link to STEPBible */
			__( 'Definitions from the Translators Brief lexicons of %s (CC BY 4.0): the Greek after Abbott-Smith’s Manual Greek Lexicon of the New Testament, the Hebrew after the abridged Brown-Driver-Briggs. Counts and passages from the site’s interlinear. Word frequencies from Bruce M. Metzger, Lexical Aids for Students of New Testament Greek.', 'tbe-child' ),
			'<a href="https://www.STEPBible.org" rel="noopener">STEP Bible</a>'
		)
	) . '</p>';
}

/** The look-up box: a Strong's number goes straight to its page. */
function tbe_lexicon_lookup(): string {
	return '<form class="tbe-lex__lookup" method="get" action="' . esc_url( TBE_Lexicon::url() ) . '" role="search"><label for="tbe-lex-n">' . esc_html__( 'Strong’s number', 'tbe-child' ) . '</label> <input id="tbe-lex-n" name="n" type="text" inputmode="text" placeholder="G3056 or H430" pattern="[GgHh]\s*0*[0-9]{1,4}" required> <button class="tbe-btn" type="submit">' . esc_html__( 'Look up', 'tbe-child' ) . '</button></form>';
}

/** /lexicon/: the two alphabets, the look-up box and Metzger's list. */
function tbe_view_lexicon(): string {
	$out  = '<div class="tbe-reader tbe-lex">';
	$out .= '<h1 class="tbe-title">' . esc_html__( 'Lexicon', 'tbe-child' ) . '</h1>';
	$out .= '<p class="tbe-center tbe-muted">' . esc_html__( 'Every Hebrew and Greek word of the Bible by its Strong’s number: what it means, how often it is used, and where.', 'tbe-child' ) . '</p>';
	$out .= tbe_lexicon_lookup();
	foreach ( array( 'hebrew' => __( 'Hebrew and Aramaic', 'tbe-child' ), 'greek' => __( 'Greek', 'tbe-child' ) ) as $lang => $name ) {
		$out .= '<section class="tbe-testament"><h2 class="tbe-ornament">' . esc_html( $name ) . '</h2><ol class="tbe-lex__letters" lang="' . ( 'hebrew' === $lang ? 'he' : 'grc' ) . '"' . ( 'hebrew' === $lang ? ' dir="rtl"' : '' ) . '>';
		foreach ( TBE_Lexicon::letters( $lang ) as $slug => $letter ) {
			$out .= '<li><a href="' . esc_url( home_url( '/lexicon/' . $lang . '/' . $slug . '/' ) ) . '" title="' . esc_attr( $slug ) . '">' . esc_html( $letter ) . '</a></li>';
		}
		$out .= '</ol></section>';
	}
	if ( TBE_Lexicon::metzger_groups() ) {
		$out .= '<p class="tbe-center"><a class="tbe-btn tbe-btn--solid" href="' . esc_url( home_url( '/lexicon/greek/frequency/' ) ) . '">' . esc_html__( 'Greek words by how often they occur (Metzger)', 'tbe-child' ) . '</a></p>';
	}
	return $out . tbe_lexicon_credit() . '</div>';
}

/** /lexicon/{greek|hebrew}/{letter}/: every word that starts with the letter. */
function tbe_view_lexicon_letter( string $lang, string $slug ): string {
	$letters = TBE_Lexicon::letters( $lang );
	$letter  = $letters[ $slug ];
	$rows    = TBE_Lexicon::by_letter( $lang, $letter );
	$he      = 'hebrew' === $lang;
	$out     = '<div class="tbe-reader tbe-lex tbe-lex--list">';
	$out    .= '<nav class="tbe-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'tbe-child' ) . '"><a href="' . esc_url( TBE_Lexicon::url() ) . '">' . esc_html__( 'Lexicon', 'tbe-child' ) . '</a></nav>';
	$out    .= '<p class="tbe-eyebrow">' . esc_html( $he ? __( 'Hebrew and Aramaic', 'tbe-child' ) : __( 'Greek', 'tbe-child' ) ) . '</p>';
	$out    .= '<h1 class="tbe-title"><span lang="' . ( $he ? 'he' : 'grc' ) . '">' . esc_html( $letter ) . '</span> <span class="tbe-muted">' . esc_html( ucfirst( $slug ) ) . '</span></h1>';
	$out    .= '<ol class="tbe-lex__letters tbe-lex__letters--small" lang="' . ( $he ? 'he' : 'grc' ) . '"' . ( $he ? ' dir="rtl"' : '' ) . '>';
	foreach ( $letters as $s => $l ) {
		$out .= '<li' . ( $s === $slug ? ' aria-current="page"' : '' ) . '><a href="' . esc_url( home_url( '/lexicon/' . $lang . '/' . $s . '/' ) ) . '">' . esc_html( $l ) . '</a></li>';
	}
	$out .= '</ol><p class="tbe-center tbe-muted">' . esc_html( sprintf( /* translators: %d: number of words */ _n( '%d word', '%d words', count( $rows ), 'tbe-child' ), count( $rows ) ) ) . '</p><ul class="tbe-lex__list">';
	foreach ( $rows as $r ) {
		$out .= '<li><a href="' . esc_url( TBE_Lexicon::url( (string) $r[0] ) ) . '"><span class="tbe-lex__word" lang="' . ( $he ? 'he' : 'grc' ) . '"' . ( $he ? ' dir="rtl"' : '' ) . '>' . esc_html( (string) $r[1] ) . '</span> <span class="tbe-lex__gloss">' . esc_html( (string) $r[2] ) . '</span></a> <span class="tbe-muted tbe-lex__num">' . esc_html( (string) $r[0] ) . ( $r[3] ? ' · ' . (int) $r[3] . '×' : '' ) . '</span></li>';
	}
	return $out . '</ul>' . tbe_lexicon_credit() . '</div>';
}

/** /lexicon/greek/frequency/: Metzger's groups, each word linked to its entry. */
function tbe_view_lexicon_frequency(): string {
	$out  = '<div class="tbe-reader tbe-lex tbe-lex--list">';
	$out .= '<nav class="tbe-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'tbe-child' ) . '"><a href="' . esc_url( TBE_Lexicon::url() ) . '">' . esc_html__( 'Lexicon', 'tbe-child' ) . '</a></nav>';
	$out .= '<h1 class="tbe-title">' . esc_html__( 'Greek words by how often they occur', 'tbe-child' ) . '</h1>';
	$out .= '<p class="tbe-center tbe-muted">' . esc_html__( 'Bruce M. Metzger’s list of the words of the Greek New Testament, from the most common down to those used ten times, with his short meanings and, in brackets, English words that come from them. Learn the first groups and you can read most of the New Testament.', 'tbe-child' ) . '</p>';
	foreach ( TBE_Lexicon::metzger_groups() as $g ) {
		$out .= '<section class="tbe-lex__group"><h2 class="tbe-ornament">' . esc_html( (string) $g['title'] ) . '</h2><ul class="tbe-lex__list">';
		foreach ( (array) $g['words'] as $w ) {
			$word = '<span class="tbe-lex__word" lang="grc">' . esc_html( (string) $w['w'] ) . '</span> <span class="tbe-lex__gloss">' . esc_html( (string) $w['g'] ) . '</span>';
			$out .= '<li>' . ( ! empty( $w['n'] ) ? '<a href="' . esc_url( TBE_Lexicon::url( (string) $w['n'] ) ) . '">' . $word . '</a> <span class="tbe-muted tbe-lex__num">' . esc_html( (string) $w['n'] ) . '</span>' : $word ) . '</li>';
		}
		$out .= '</ul></section>';
	}
	return $out . tbe_lexicon_credit() . '</div>';
}

/** /lexicon/{number}/: one word. */
function tbe_view_lexicon_entry( array $e ): string {
	$num   = (string) $e['num'];
	$he    = 'H' === $num[0];
	$lang  = $he ? 'he' : 'grc';
	$dir   = $he ? ' dir="rtl"' : '';
	$first = $e['s'][0];
	$out   = '<div class="tbe-reader tbe-lex tbe-lex--entry">';
	$out  .= '<nav class="tbe-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'tbe-child' ) . '"><a href="' . esc_url( TBE_Lexicon::url() ) . '">' . esc_html__( 'Lexicon', 'tbe-child' ) . '</a> <span aria-hidden="true">›</span> <a href="' . esc_url( home_url( '/lexicon/' . TBE_Lexicon::language( $num ) . '/' . array_search( TBE_Lexicon::initial( (string) $first['w'] ), TBE_Lexicon::letters( TBE_Lexicon::language( $num ) ), true ) . '/' ) ) . '">' . esc_html( $he ? __( 'Hebrew and Aramaic', 'tbe-child' ) : __( 'Greek', 'tbe-child' ) ) . '</a></nav>';
	$out  .= '<p class="tbe-eyebrow">' . esc_html( sprintf( /* translators: %s: Strong's number */ __( 'Strong’s %s', 'tbe-child' ), $num ) ) . '</p>';
	$out  .= '<h1 class="tbe-title tbe-lex__head" lang="' . $lang . '"' . $dir . '>' . esc_html( (string) $first['w'] ) . '</h1>';
	$meta  = array_filter( array( (string) $first['t'], (string) $first['g'], TBE_Lexicon::part( (string) $first['p'] ) ) );
	$out  .= '<p class="tbe-center tbe-lex__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
	$count = (int) $e['n'];
	$out  .= '<p class="tbe-center tbe-muted">' . esc_html(
		$count
			? sprintf( /* translators: 1: number of times, 2: Old or New Testament */ _n( 'Used %1$d time in the %2$s.', 'Used %1$d times in the %2$s.', $count, 'tbe-child' ), $count, $he ? __( 'Hebrew Old Testament', 'tbe-child' ) : __( 'Greek New Testament', 'tbe-child' ) )
			: __( 'Not used in the text of the interlinear.', 'tbe-child' )
	) . '</p>';

	$metz = $he ? null : TBE_Lexicon::metzger( $num );
	if ( $metz ) {
		$out .= '<aside class="tbe-lex__metzger"><p><strong>' . esc_html__( 'Metzger, Lexical Aids:', 'tbe-child' ) . '</strong> <a href="' . esc_url( home_url( '/lexicon/greek/frequency/' ) ) . '">' . esc_html( (string) $metz['group'] ) . '</a>. <span lang="grc">' . esc_html( (string) $metz['w'] ) . '</span>, ' . esc_html( (string) $metz['g'] )
			. ( ! empty( $metz['p'] ) ? ' <a class="tbe-muted" href="' . esc_url( home_url( '/pdf/lexical-aids.pdf' ) . '#page=' . (int) $metz['p'] ) . '">' . esc_html__( '(see the page)', 'tbe-child' ) . '</a>' : '' ) . '</p></aside>';
	}

	foreach ( $e['s'] as $i => $s ) {
		if ( count( $e['s'] ) > 1 ) {
			$out .= '<h2 class="tbe-lex__sense"><span lang="' . $lang . '"' . $dir . '>' . esc_html( (string) $s['w'] ) . '</span> <span class="tbe-muted">' . esc_html( trim( (string) $s['g'] . ' · ' . (string) $s['e'], ' ·' ) ) . '</span></h2>';
		}
		if ( ! empty( $s['d'] ) ) {
			$out .= '<div class="tbe-lex__def">' . TBE_Lexicon::definition( (string) $s['d'] ) . '</div>';
		}
	}

	$bdb = $he ? TBE_Lexicon::bdb( $num ) : array();
	if ( $bdb ) {
		$allowed = array(
			'span'  => array( 'lang' => true, 'dir' => true ),
			'i'     => array(),
			'b'     => array(),
			'small' => array(),
		);
		$out .= '<section class="tbe-lex__bdb"><h2 class="tbe-ornament">' . esc_html__( 'Brown-Driver-Briggs', 'tbe-child' ) . '</h2>';
		foreach ( $bdb as $para ) {
			$out .= '<p>' . wp_kses( $para, $allowed ) . '</p>';
		}
		$out .= '<p class="tbe-muted tbe-lex__bdb-credit">' . esc_html__( 'From Brown, Driver and Briggs, A Hebrew and English Lexicon of the Old Testament (Oxford: Clarendon Press, 1906), public domain, as set out in the Enhanced Brown-Driver-Briggs pages at ericlevy.com.', 'tbe-child' ) . '</p></section>';
	}

	if ( ! empty( $e['r'] ) ) {
		$out   .= '<h2 class="tbe-ornament">' . esc_html__( 'Where it is used', 'tbe-child' ) . '</h2><p class="tbe-lex__refs">';
		$links  = array();
		$last   = '';
		foreach ( $e['r'] as $r ) {
			[ $slug, $c, $v ] = $r;
			$label   = $slug === $last ? $c . ':' . $v : TBE_Bible::label( (string) $slug, (int) $c, (int) $v );
			$last    = (string) $slug;
			$links[] = '<a href="' . esc_url( TBE_Interlinear::url( (string) $slug, (int) $c ) . '#v' . (int) $v ) . '">' . esc_html( $label ) . '</a>';
		}
		$out .= implode( ', ', $links );
		$more = $count - count( $e['r'] );
		$out .= $more > 0 ? ' ' . esc_html( sprintf( /* translators: %d: number of further places */ __( 'and %d more.', 'tbe-child' ), $more ) ) : '';
		$out .= '</p><p class="tbe-muted">' . esc_html__( 'Each link opens the word-by-word page of the chapter.', 'tbe-child' ) . '</p>';
	}
	return $out . tbe_lexicon_credit() . '</div>';
}
