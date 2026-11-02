<?php
/**
 * Word-by-word interlinear: Hebrew (Leningrad Codex) and Greek (NA28 with variants).
 * Data: STEPBible (https://www.STEPBible.org), CC BY 4.0, based on work at Tyndale House Cambridge.
 * Built by tools/build_interlinear.py into data/interlinear/{book}/{chapter}.json.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

final class TBE_Interlinear {

	/** @return array{lang:string,v:array<string,array>}|null */
	public static function chapter( string $slug, int $chapter ): ?array {
		$file = TBE_DIR . '/data/interlinear/' . $slug . '/' . $chapter . '.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $file ), true );
		return ( is_array( $data ) && ! empty( $data['v'] ) ) ? $data : null;
	}

	public static function available( string $slug, int $chapter ): bool {
		return is_readable( TBE_DIR . '/data/interlinear/' . $slug . '/' . $chapter . '.json' );
	}

	public static function url( string $slug, int $chapter ): string {
		return home_url( '/bible/' . $slug . '/' . $chapter . '/interlinear/' );
	}
}

/* Grammar labels ---------------------------------------------------------- */

/** Hebrew morphology (ETCBC / OpenScriptures style), e.g. "HR/Ncfsa" or "HVqp3ms". */
function tbe_hebrew_morph_text( string $code ): string {
	if ( '' === $code ) {
		return '';
	}
	if ( 'H' === $code[0] ) {
		$code = substr( $code, 1 );
	}
	$parts = array();
	foreach ( explode( '/', $code ) as $segment ) {
		$text = tbe_hebrew_morph_segment( $segment );
		if ( '' !== $text ) {
			$parts[] = $text;
		}
	}
	return implode( ' + ', $parts );
}

function tbe_hebrew_morph_segment( string $s ): string {
	if ( '' === $s ) {
		return '';
	}
	$pos = array(
		'A' => 'adjective', 'C' => 'conjunction', 'D' => 'adverb', 'N' => 'noun', 'P' => 'pronoun',
		'R' => 'preposition', 'S' => 'suffix', 'T' => 'particle', 'V' => 'verb',
	);
	$gender = array( 'm' => 'masculine', 'f' => 'feminine', 'b' => 'masculine or feminine', 'c' => 'common' );
	$number = array( 's' => 'singular', 'p' => 'plural', 'd' => 'dual' );
	$state  = array( 'a' => 'absolute', 'c' => 'construct', 'd' => 'determined' );
	$stems  = array(
		'q' => 'Qal', 'N' => 'Niphal', 'p' => 'Piel', 'P' => 'Pual', 'h' => 'Hiphil', 'H' => 'Hophal', 't' => 'Hithpael',
		'o' => 'Polel', 'O' => 'Polal', 'r' => 'Hithpolel', 'm' => 'Poel', 'M' => 'Poal', 'k' => 'Palel', 'K' => 'Pulal',
		'Q' => 'Qal passive', 'l' => 'Pilpel', 'L' => 'Polpal', 'f' => 'Hithpalpel', 'D' => 'Nithpael', 'j' => 'Pealal',
		'i' => 'Pilel', 'u' => 'Hothpaal', 'c' => 'Tiphil', 'v' => 'Hishtaphel', 'w' => 'Nithpalel', 'y' => 'Nithpoel', 'z' => 'Hithpoel',
	);
	$forms  = array(
		'p' => 'perfect', 'q' => 'sequential perfect', 'i' => 'imperfect', 'w' => 'sequential imperfect', 'h' => 'cohortative',
		'j' => 'jussive', 'v' => 'imperative', 'r' => 'active participle', 's' => 'passive participle',
		'a' => 'infinitive absolute', 'c' => 'infinitive construct',
	);
	$persons = array( '1' => 'first person', '2' => 'second person', '3' => 'third person' );

	$tail = static function ( string $rest ) use ( $gender, $number, $state, $persons ): array {
		$out = array();
		$g   = $n = $st = $p = false;
		foreach ( str_split( $rest ) as $ch ) {
			if ( ctype_digit( $ch ) && ! $p && isset( $persons[ $ch ] ) ) {
				$out[] = $persons[ $ch ];
				$p     = true;
			} elseif ( ! $g && isset( $gender[ $ch ] ) ) {
				$out[] = $gender[ $ch ];
				$g     = true;
			} elseif ( ! $n && isset( $number[ $ch ] ) ) {
				$out[] = $number[ $ch ];
				$n     = true;
			} elseif ( ! $st && isset( $state[ $ch ] ) ) {
				$out[] = $state[ $ch ];
				$st    = true;
			}
		}
		return $out;
	};

	$first = $s[0];
	$rest  = substr( $s, 1 );
	// Lowercase one-letter segments are prefixes (conjunction, article, preposition...).
	if ( 1 === strlen( $s ) ) {
		$prefix = array( 'c' => 'conjunction', 'd' => 'article', 'r' => 'preposition', 'i' => 'interrogative', 'm' => 'preposition (min)' );
		return $pos[ strtoupper( $s ) ] ?? ( $prefix[ $s ] ?? '' );
	}
	if ( ! isset( $pos[ $first ] ) ) {
		return '';
	}
	$out = array( $pos[ $first ] );
	switch ( $first ) {
		case 'N':
			$types = array( 'c' => 'common', 'g' => 'gentilic', 'p' => 'proper name' );
			if ( isset( $types[ $rest[0] ?? '' ] ) ) {
				$out[] = $types[ $rest[0] ];
				$rest  = substr( $rest, 1 );
			}
			$out = array_merge( $out, $tail( $rest ) );
			break;
		case 'V':
			if ( isset( $stems[ $rest[0] ?? '' ] ) ) {
				$out[] = $stems[ $rest[0] ];
				$rest  = substr( $rest, 1 );
			}
			if ( isset( $forms[ $rest[0] ?? '' ] ) ) {
				$out[] = $forms[ $rest[0] ];
				$rest  = substr( $rest, 1 );
			}
			$out = array_merge( $out, $tail( $rest ) );
			break;
		case 'A':
			$types = array( 'a' => 'adjective', 'c' => 'cardinal number', 'g' => 'gentilic', 'o' => 'ordinal number' );
			if ( isset( $types[ $rest[0] ?? '' ] ) ) {
				$out[0] = $types[ $rest[0] ];
				$rest   = substr( $rest, 1 );
			}
			$out = array_merge( $out, $tail( $rest ) );
			break;
		case 'P':
			$types = array( 'd' => 'demonstrative', 'f' => 'indefinite', 'i' => 'interrogative', 'p' => 'personal', 'r' => 'relative' );
			if ( isset( $types[ $rest[0] ?? '' ] ) ) {
				array_unshift( $out, $types[ $rest[0] ] );
				$rest = substr( $rest, 1 );
			}
			$out = array_merge( $out, $tail( $rest ) );
			break;
		case 'T':
			$types = array(
				'a' => 'affirmation', 'd' => 'definite article', 'e' => 'exhortation', 'i' => 'interrogative', 'j' => 'interjection',
				'm' => 'demonstrative', 'n' => 'negative', 'o' => 'direct object marker', 'r' => 'relative',
			);
			if ( isset( $types[ $rest[0] ?? '' ] ) ) {
				$out = array( $types[ $rest[0] ] );
			}
			break;
		case 'R':
			if ( 'd' === ( $rest[0] ?? '' ) ) {
				$out[] = 'with the article';
			}
			break;
		case 'S':
			$types = array( 'd' => 'directional he', 'h' => 'paragogic he', 'n' => 'paragogic nun', 'p' => 'pronominal suffix' );
			if ( isset( $types[ $rest[0] ?? '' ] ) ) {
				$out = array( $types[ $rest[0] ] );
				$rest = substr( $rest, 1 );
			}
			$out = array_merge( $out, $tail( $rest ) );
			break;
	}
	return implode( ' · ', $out );
}

/** Greek morphology (Robinson), e.g. "N-NSF", "V-PAI-3S", "V-PAP-NSM". */
function tbe_greek_morph_text( string $code ): string {
	if ( '' === $code ) {
		return '';
	}
	$words = array(
		'ADV' => 'adverb', 'CONJ' => 'conjunction', 'COND' => 'conditional', 'PRT' => 'particle', 'PREP' => 'preposition',
		'INJ' => 'interjection', 'ARAM' => 'Aramaic word', 'HEB' => 'Hebrew word',
	);
	if ( isset( $words[ $code ] ) ) {
		return $words[ $code ];
	}
	$pos = array(
		'N' => 'noun', 'V' => 'verb', 'A' => 'adjective', 'T' => 'article', 'P' => 'personal pronoun', 'R' => 'relative pronoun',
		'C' => 'reciprocal pronoun', 'D' => 'demonstrative pronoun', 'K' => 'correlative pronoun', 'I' => 'interrogative pronoun',
		'X' => 'indefinite pronoun', 'Q' => 'correlative or interrogative pronoun', 'F' => 'reflexive pronoun', 'S' => 'possessive adjective',
	);
	$case   = array( 'N' => 'nominative', 'G' => 'genitive', 'D' => 'dative', 'A' => 'accusative', 'V' => 'vocative' );
	$num    = array( 'S' => 'singular', 'P' => 'plural' );
	$gen    = array( 'M' => 'masculine', 'F' => 'feminine', 'N' => 'neuter' );
	$tense  = array( 'P' => 'present', 'I' => 'imperfect', 'F' => 'future', 'A' => 'aorist', 'R' => 'perfect', 'L' => 'pluperfect' );
	$voice  = array(
		'A' => 'active', 'M' => 'middle', 'P' => 'passive', 'E' => 'middle or passive', 'D' => 'middle deponent', 'O' => 'passive deponent',
		'N' => 'middle or passive deponent', 'Q' => 'impersonal active', 'X' => 'no voice',
	);
	$mood   = array( 'I' => 'indicative', 'S' => 'subjunctive', 'O' => 'optative', 'M' => 'imperative', 'N' => 'infinitive', 'P' => 'participle', 'R' => 'imperative participle' );
	$person = array( '1' => 'first person', '2' => 'second person', '3' => 'third person' );
	$extra  = array( 'P' => 'person', 'L' => 'place', 'T' => 'title', 'C' => 'comparative', 'S' => 'superlative' );

	$parts = explode( '-', $code );
	$first = $parts[0];
	if ( ! isset( $pos[ $first ] ) ) {
		return '';
	}
	$out = array( $pos[ $first ] );

	// Case-number-gender token, e.g. NSM; also 1NS / 2PS style person-number for pronouns.
	$cng = static function ( string $t ) use ( $case, $num, $gen, $person ): array {
		$o = array();
		if ( 3 === strlen( $t ) && isset( $case[ $t[0] ] ) ) {
			$o = array( $case[ $t[0] ], $num[ $t[1] ] ?? '', $gen[ $t[2] ] ?? '' );
		} elseif ( 3 === strlen( $t ) && isset( $person[ $t[0] ] ) ) {
			$o = array( $person[ $t[0] ], $num[ $t[2] ] ?? '', '' );
			$o[] = isset( $case[ $t[1] ] ) ? $case[ $t[1] ] : '';
		}
		return array_values( array_filter( $o ) );
	};

	if ( 'V' === $first ) {
		$tvm = $parts[1] ?? '';
		$tvm = ltrim( $tvm, '2' ) !== $tvm ? 'second ' . substr( $tvm, 1 ) : $tvm;
		$second = '' !== $tvm && 0 === strpos( $tvm, 'second ' );
		$core   = $second ? substr( $tvm, 7 ) : $tvm;
		if ( 3 === strlen( $core ) ) {
			$out[] = ( $second ? 'second ' : '' ) . ( $tense[ $core[0] ] ?? '' );
			$out[] = $voice[ $core[1] ] ?? '';
			$out[] = $mood[ $core[2] ] ?? '';
		}
		$tail = $parts[2] ?? '';
		if ( 2 === strlen( $tail ) && isset( $person[ $tail[0] ] ) ) {
			$out[] = $person[ $tail[0] ];
			$out[] = $num[ $tail[1] ] ?? '';
		} elseif ( '' !== $tail ) {
			$out = array_merge( $out, $cng( $tail ) );
		}
	} else {
		if ( isset( $parts[1] ) ) {
			$out = array_merge( $out, $cng( $parts[1] ) );
		}
		foreach ( array_slice( $parts, 2 ) as $flag ) {
			if ( isset( $extra[ $flag ] ) ) {
				$out[] = $extra[ $flag ];
			}
		}
	}
	return implode( ' · ', array_values( array_filter( $out ) ) );
}

/* View -------------------------------------------------------------------- */

function tbe_interlinear_layers(): array {
	return array(
		'gloss'    => __( 'English', 'tbe-child' ),
		'translit' => __( 'Transliteration', 'tbe-child' ),
		'strongs'  => __( 'Strong\'s', 'tbe-child' ),
		'lemma'    => __( 'Root word', 'tbe-child' ),
		'morph'    => __( 'Grammar', 'tbe-child' ),
	);
}

function tbe_view_interlinear( array $book, int $chapter ): string {
	$slug = $book['slug'];
	$data = TBE_Interlinear::chapter( $slug, $chapter );

	$out  = '<div class="tbe-reader tbe-il-page">';
	$out .= tbe_breadcrumb( $book, $chapter );
	$out .= '<p class="tbe-eyebrow">' . esc_html( sprintf( /* translators: %d: chapter number */ __( 'Interlinear · Chapter %d', 'tbe-child' ), $chapter ) ) . '</p>';
	$out .= '<h1 class="tbe-title">' . esc_html( $book['name'] ) . '</h1>';
	$out .= tbe_book_subtitle( $book );
	$out .= '<p class="tbe-center"><a href="' . esc_url( TBE_Bible::url( $slug, $chapter ) ) . '">' . esc_html__( 'Back to the ESV reading page', 'tbe-child' ) . '</a></p>';

	if ( ! $data ) {
		return $out . '<p class="tbe-notice">' . esc_html__( 'The interlinear for this chapter is not available.', 'tbe-child' ) . '</p></div>';
	}

	$hebrew = 'he' === $data['lang'];
	$audio  = TBE_Audio::chapter( $slug, $chapter );
	$esv    = array();
	foreach ( (array) TBE_Bible::chapter( $slug, $chapter ) as $verse ) {
		$esv[ (int) $verse['n'] ] = preg_replace( '/\s+/u', ' ', (string) $verse['t'] );
	}

	if ( $audio ) {
		$out .= '<figure class="tbe-il__audio"><figcaption>' . esc_html( $hebrew ? __( 'Listen to the Hebrew reading', 'tbe-child' ) : __( 'Listen to the Greek reading', 'tbe-child' ) ) . ' <span class="tbe-muted">' . esc_html__( 'Click a verse number or any word to hear it.', 'tbe-child' ) . '</span></figcaption>'
			. '<audio id="tbe-il-audio" data-tbe-audio controls preload="metadata" src="' . esc_url( $audio['url'] ) . '"></audio>';
		if ( '' !== trim( (string) $audio['credit'] ) ) {
			$out .= '<p class="tbe-muted tbe-il__credit">' . esc_html( (string) $audio['credit'] ) . '</p>';
		}
		$out .= '</figure>';
	}

	$out .= '<div class="tbe-toolbar" role="group" aria-label="' . esc_attr__( 'Interlinear display options', 'tbe-child' ) . '">';
	foreach ( tbe_interlinear_layers() as $key => $label ) {
		$on   = in_array( $key, array( 'gloss', 'translit', 'strongs' ), true );
		$out .= '<button type="button" class="tbe-btn" data-tbe-il="' . esc_attr( $key ) . '" aria-pressed="' . ( $on ? 'true' : 'false' ) . '">' . esc_html( $label ) . '</button>';
	}
	if ( $hebrew ) {
		$out .= '<button type="button" class="tbe-btn" data-tbe-il-script="vowels" aria-pressed="true">' . esc_html__( 'Vowel points', 'tbe-child' ) . '</button>';
		$out .= '<button type="button" class="tbe-btn" data-tbe-il-script="cantillation" aria-pressed="true">' . esc_html__( 'Cantillation', 'tbe-child' ) . '</button>';
	} else {
		$out .= '<button type="button" class="tbe-btn" data-tbe-il="variants" aria-pressed="true" data-tbe-tip="' . esc_attr__( 'Outline words that are not in the NA28 critical text and flag places where manuscripts differ', 'tbe-child' ) . '">' . esc_html__( 'Show variants', 'tbe-child' ) . '</button>';
	}
	$dss = $hebrew ? tbe_dss_chapter( $slug, $chapter ) : null;
	if ( $dss ) {
		$out .= '<button type="button" class="tbe-btn tbe-btn--dss" data-tbe-il="dss" aria-pressed="true" data-tbe-tip="' . esc_attr__( 'Mark words where a Dead Sea Scroll reads differently from the Masoretic text', 'tbe-child' ) . '">' . esc_html__( 'Dead Sea Scrolls', 'tbe-child' ) . '</button>';
		$out .= '<button type="button" class="tbe-btn tbe-btn--dss" data-tbe-il="dssSpelling" aria-pressed="false" data-tbe-tip="' . esc_attr__( 'Also show differences of spelling only', 'tbe-child' ) . '">' . esc_html__( 'Spelling too', 'tbe-child' ) . '</button>';
	}
	if ( $audio && current_user_can( 'edit_posts' ) ) {
		$out .= '<button type="button" class="tbe-btn" data-tbe-il="review" aria-pressed="false" data-tbe-tip="' . esc_attr__( 'Editors only: outline words whose timing is only an estimate, so you can check them by ear', 'tbe-child' ) . '">' . esc_html__( 'Check timings', 'tbe-child' ) . '</button>';
	}
	$out .= '</div>';

	$class = 'tbe-il tbe-il--' . ( $hebrew ? 'he' : 'gr' ) . ' tbe-il--hide-lemma tbe-il--hide-morph';
	$out  .= '<div class="' . esc_attr( $class ) . '" id="tbe-il">';

	if ( $dss ) {
		[ $meaning, $spelling ] = tbe_dss_counts( $dss );
		$out .= '<p class="tbe-dss-summary"><strong>' . esc_html( sprintf( /* translators: %s: scroll name */ __( 'Compared with the %s', 'tbe-child' ), (string) $dss['scroll'] ) ) . '.</strong> ' . esc_html( sprintf( /* translators: 1: meaning differences, 2: spelling differences */ _n( '%1$d difference of meaning in this chapter, and %2$d of spelling.', '%1$d differences of meaning in this chapter, and %2$d of spelling.', $meaning, 'tbe-child' ), $meaning, $spelling ) ) . '</p>';
	}
	$counts = array( 'variant' => 0 );
	foreach ( $data['v'] as $verse => $words ) {
		$n    = (int) $verse;
		$line = '';
		$show = $dss ? tbe_dss_verse( (array) ( $dss['v'][ (string) $verse ] ?? array() ), $words, (string) $dss['scroll'] ) : array( 'words' => array(), 'notes' => '' );
		foreach ( $words as $idx => $w ) {
			$line .= tbe_render_il_word( $w, $hebrew, $counts, $audio['words'][ $verse ][ $idx ] ?? null, $show['words'][ $idx ] ?? null );
		}
		$out .= '<section class="tbe-il__verse" id="v' . $n . '"><p class="tbe-il__ref"><a class="tbe-vn" href="#v' . $n . '">' . ( 0 === $n ? esc_html__( 'Title', 'tbe-child' ) : $n ) . '</a>';
		if ( $audio && ! empty( $audio['words'][ $verse ] ) ) {
			$out .= '<button type="button" class="tbe-il__play" data-tbe-play-verse="' . $n . '" aria-label="' . esc_attr( sprintf( /* translators: %d: verse number */ __( 'Play verse %d', 'tbe-child' ), $n ) ) . '"><span aria-hidden="true">▶</span></button>';
		}
		if ( isset( $esv[ $n ] ) ) {
			$out .= '<span class="tbe-il__esv tbe-scripture" lang="en" dir="ltr">' . esc_html( $esv[ $n ] ) . '</span>';
		}
		$out .= '</p><div class="tbe-il__words" dir="' . ( $hebrew ? 'rtl' : 'ltr' ) . '">' . $line . '</div>' . $show['notes'] . '</section>';
	}
	$out .= '</div>';

	$source = $hebrew
		? __( 'Hebrew: Westminster Leningrad Codex, tagged by Tyndale House.', 'tbe-child' )
		: __( 'Greek: NA28 with variants from SBLGNT, the Textus Receptus and others, tagged by Tyndale House.', 'tbe-child' );
	$out   .= '<p class="tbe-copyright">' . esc_html( $source ) . ' ' . wp_kses_post(
		sprintf(
			/* translators: %s: link to STEP Bible */
			__( 'Interlinear data from %s, licensed CC BY 4.0.', 'tbe-child' ),
			'<a href="https://www.STEPBible.org" rel="noopener">STEP Bible</a>'
		)
	) . '</p>';
	return $out . '</div>';
}

/** One word: gloss, original, transliteration, Strong's number, root word, grammar. */
function tbe_render_il_word( array $w, bool $hebrew, array &$counts, ?array $timing = null, ?array $dss = null ): string {
	[ $text, $translit, $gloss, $strongs, $morph, $lemma ] = array_pad( $w, 6, '' );
	$extra  = isset( $w[6] ) && is_array( $w[6] ) ? $w[6] : array();
	$parse  = $hebrew ? tbe_hebrew_morph_text( (string) $morph ) : tbe_greek_morph_text( (string) $morph );
	$class  = 'tbe-il__w';
	$notes  = array();
	$tip    = array();
	if ( '' !== $parse ) {
		$tip[] = $parse;
	}
	if ( '' !== $strongs ) {
		$tip[] = sprintf( /* translators: %s: Strong's number */ __( 'Strong\'s %s', 'tbe-child' ), ltrim( (string) $strongs, 'HG0' ) ? $strongs : $strongs );
	}

	if ( ! $hebrew && ! empty( $extra['n'] ) ) {
		$class .= ' tbe-il__w--outside';
		$tip[]  = sprintf( /* translators: %s: manuscript editions */ __( 'Not in the NA28 critical text. Found in: %s', 'tbe-child' ), str_replace( '+', ', ', (string) ( $extra['e'] ?? '' ) ) );
	}
	if ( ! empty( $extra['v'] ) ) {
		$class .= ' tbe-il__w--variant';
		$tip[]  = sprintf( /* translators: %s: description of the variant */ __( 'Variant: %s', 'tbe-child' ), (string) $extra['v'] );
		$notes[] = '<span class="tbe-il__flag" aria-hidden="true">⚑</span>';
		++$counts['variant'];
	} elseif ( ! $hebrew && ! empty( $extra['e'] ) && empty( $extra['n'] ) ) {
		$tip[] = sprintf( /* translators: %s: manuscript editions */ __( 'Read by: %s', 'tbe-child' ), str_replace( '+', ', ', (string) $extra['e'] ) );
	}
	if ( $hebrew && ! empty( $extra['t'] ) ) {
		$labels = array(
			'Q' => __( 'Qere: the scribal correction followed by translators', 'tbe-child' ),
			'R' => __( 'Restored from a parallel passage', 'tbe-child' ),
			'X' => __( 'Added from the Greek tradition (as in the BHS apparatus)', 'tbe-child' ),
		);
		if ( isset( $labels[ $extra['t'] ] ) ) {
			$class .= ' tbe-il__w--variant';
			$tip[]  = $labels[ $extra['t'] ];
			$notes[] = '<span class="tbe-il__flag" aria-hidden="true">⚑</span>';
			++$counts['variant'];
		}
	}

	if ( $dss ) { // a Dead Sea Scroll reads differently here
		$class .= ' tbe-il__w--dss tbe-il__w--dss-' . $dss['kind'];
		$tip[]  = $dss['tip'];
	}

	$attrs = '';
	if ( $timing ) {
		$attrs  = ' data-t0="' . esc_attr( (string) $timing[0] ) . '" data-t1="' . esc_attr( (string) $timing[1] ) . '"' . ( empty( $timing[2] ) ? ' data-approx="1"' : '' );
		$class .= ' tbe-il__w--timed';
	}
	$script_class = $hebrew ? 'tbe-il__he' : 'tbe-il__gr';
	$script_lang  = $hebrew ? 'he' : 'grc';
	$word_html    = '<span class="' . $script_class . '" lang="' . $script_lang . '" dir="' . ( $hebrew ? 'rtl' : 'ltr' ) . '"' . ( $hebrew ? ' data-full="' . esc_attr( (string) $text ) . '"' : '' ) . '>' . esc_html( (string) $text ) . '</span>';

	return sprintf(
		'<span class="%1$s" tabindex="0"%13$s data-tbe-tip-title="%2$s" data-tbe-tip="%3$s">%4$s<span class="tbe-il__gloss" dir="ltr">%5$s</span>%6$s<span class="tbe-il__translit" dir="ltr">%7$s</span><span class="tbe-il__strongs" dir="ltr">%8$s</span><span class="tbe-il__lemma %9$s" lang="%10$s">%11$s</span><span class="tbe-il__morph" dir="ltr">%12$s</span></span>',
		esc_attr( $class ),
		esc_attr( trim( (string) $lemma . ( '' !== $gloss ? ' — ' . $gloss : '' ), ' —' ) ),
		esc_attr( implode( ' · ', $tip ) ),
		implode( '', $notes ),
		esc_html( (string) $gloss ),
		$word_html,
		esc_html( (string) $translit ),
		esc_html( (string) $strongs ),
		esc_attr( $script_class ),
		esc_attr( $script_lang ),
		esc_html( (string) $lemma ),
		esc_html( $parse ?: (string) $morph ),
		$attrs
	);
}
