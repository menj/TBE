<?php
/**
 * Word-by-word Hebrew interlinear (Leningrad Codex).
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
	$out .= '<p class="tbe-center"><a href="' . esc_url( TBE_Bible::url( $slug, $chapter ) ) . '">' . esc_html__( 'Back to the ESV reading page', 'tbe-child' ) . '</a></p>';

	if ( ! $data ) {
		return $out . '<p class="tbe-notice">' . esc_html__( 'The interlinear for this chapter is not available.', 'tbe-child' ) . '</p></div>';
	}

	$audio = TBE_Audio::chapter( $slug, $chapter );
	$esv   = array();
	foreach ( (array) TBE_Bible::chapter( $slug, $chapter ) as $verse ) {
		$esv[ (int) $verse['n'] ] = preg_replace( '/\s+/u', ' ', (string) $verse['t'] );
	}

	if ( $audio ) {
		$out .= '<figure class="tbe-il__audio"><figcaption>' . esc_html__( 'Listen to the Hebrew reading', 'tbe-child' ) . ' <span class="tbe-muted">' . esc_html__( 'Click a verse number or any word to hear it.', 'tbe-child' ) . '</span></figcaption>'
			. '<audio id="tbe-il-audio" controls preload="none" src="' . esc_url( $audio['url'] ) . '"></audio>';
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
	$out .= '<button type="button" class="tbe-btn" data-tbe-il-script="vowels" aria-pressed="true">' . esc_html__( 'Vowel points', 'tbe-child' ) . '</button>';
	$out .= '<button type="button" class="tbe-btn" data-tbe-il-script="cantillation" aria-pressed="true">' . esc_html__( 'Cantillation', 'tbe-child' ) . '</button>';
	if ( $audio && current_user_can( 'edit_posts' ) ) {
		$out .= '<button type="button" class="tbe-btn" data-tbe-il="review" aria-pressed="false" data-tbe-tip="' . esc_attr__( 'Editors only: outline words whose timing is only an estimate, so you can check them by ear', 'tbe-child' ) . '">' . esc_html__( 'Check timings', 'tbe-child' ) . '</button>';
	}
	$out .= '</div>';

	$out .= '<div class="tbe-il tbe-il--he tbe-il--hide-lemma tbe-il--hide-morph" id="tbe-il">';
	foreach ( $data['v'] as $verse => $words ) {
		$n    = (int) $verse;
		$line = '';
		foreach ( $words as $idx => $w ) {
			$line .= tbe_render_il_word( $w, $audio['words'][ $verse ][ $idx ] ?? null );
		}
		$out .= '<section class="tbe-il__verse" id="v' . $n . '"><p class="tbe-il__ref"><a class="tbe-vn" href="#v' . $n . '">' . ( 0 === $n ? esc_html__( 'Title', 'tbe-child' ) : $n ) . '</a>';
		if ( $audio && ! empty( $audio['words'][ $verse ] ) ) {
			$out .= '<button type="button" class="tbe-il__play" data-tbe-play-verse="' . $n . '" aria-label="' . esc_attr( sprintf( /* translators: %d: verse number */ __( 'Play verse %d', 'tbe-child' ), $n ) ) . '"><span aria-hidden="true">▶</span></button>';
		}
		if ( isset( $esv[ $n ] ) ) {
			$out .= '<span class="tbe-il__esv tbe-scripture" lang="en" dir="ltr">' . esc_html( $esv[ $n ] ) . '</span>';
		}
		$out .= '</p><div class="tbe-il__words" dir="rtl">' . $line . '</div></section>';
	}
	$out .= '</div>';

	$out .= '<p class="tbe-copyright">' . esc_html__( 'Hebrew: Westminster Leningrad Codex, tagged by Tyndale House.', 'tbe-child' ) . ' ' . wp_kses_post(
		sprintf(
			/* translators: %s: link to STEP Bible */
			__( 'Interlinear data from %s, licensed CC BY 4.0.', 'tbe-child' ),
			'<a href="https://www.STEPBible.org" rel="noopener">STEP Bible</a>'
		)
	) . '</p>';
	return $out . '</div>';
}

/** One word: gloss, Hebrew, transliteration, Strong's number, root word, grammar. */
function tbe_render_il_word( array $w, ?array $timing = null ): string {
	[ $text, $translit, $gloss, $strongs, $morph, $lemma ] = array_pad( $w, 6, '' );
	$extra = isset( $w[6] ) && is_array( $w[6] ) ? $w[6] : array();
	$parse = tbe_hebrew_morph_text( (string) $morph );
	$class = 'tbe-il__w';
	$flag  = false;
	$tip   = array();
	if ( '' !== $parse ) {
		$tip[] = $parse;
	}
	if ( '' !== $strongs ) {
		$tip[] = sprintf( /* translators: %s: Strong's number */ __( 'Strong\'s %s', 'tbe-child' ), $strongs );
	}
	if ( ! empty( $extra['v'] ) ) {
		$tip[] = sprintf( /* translators: %s: description of the variant */ __( 'Variant: %s', 'tbe-child' ), (string) $extra['v'] );
		$flag  = true;
	}
	if ( ! empty( $extra['t'] ) ) {
		$labels = array(
			'Q' => __( 'Qere: the scribal correction followed by translators', 'tbe-child' ),
			'R' => __( 'Restored from a parallel passage', 'tbe-child' ),
			'X' => __( 'Added from the Septuagint (as in the BHS apparatus)', 'tbe-child' ),
		);
		if ( isset( $labels[ $extra['t'] ] ) ) {
			$tip[] = $labels[ $extra['t'] ];
			$flag  = true;
		}
	}
	if ( $flag ) {
		$class .= ' tbe-il__w--variant';
	}

	$attrs = '';
	if ( $timing ) {
		$attrs  = ' data-t0="' . esc_attr( (string) $timing[0] ) . '" data-t1="' . esc_attr( (string) $timing[1] ) . '"' . ( empty( $timing[2] ) ? ' data-approx="1"' : '' );
		$class .= ' tbe-il__w--timed';
	}

	return sprintf(
		'<span class="%1$s" tabindex="0"%2$s data-tbe-tip-title="%3$s" data-tbe-tip="%4$s">%5$s<span class="tbe-il__gloss" dir="ltr">%6$s</span><span class="tbe-il__he" lang="he" dir="rtl" data-full="%7$s">%7$s</span><span class="tbe-il__translit" dir="ltr">%8$s</span><span class="tbe-il__strongs" dir="ltr">%9$s</span><span class="tbe-il__lemma tbe-il__he" lang="he">%10$s</span><span class="tbe-il__morph" dir="ltr">%11$s</span></span>',
		esc_attr( $class ),
		$attrs,
		esc_attr( trim( (string) $lemma . ( '' !== $gloss ? ' — ' . $gloss : '' ), ' —' ) ),
		esc_attr( implode( ' · ', $tip ) ),
		$flag ? '<span class="tbe-il__flag" aria-hidden="true">⚑</span>' : '',
		esc_html( (string) $gloss ),
		esc_attr( (string) $text ),
		esc_html( (string) $translit ),
		esc_html( (string) $strongs ),
		esc_html( (string) $lemma ),
		esc_html( $parse ?: (string) $morph )
	);
}
