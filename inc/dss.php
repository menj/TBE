<?php
/**
 * Dead Sea Scrolls variants, shown on the Hebrew interlinear: where a scroll reads differently from the
 * Masoretic text of the page. Data: data/dss/{book}/{chapter}.json, built by tools/build_dss_variants.py from the
 * CACCHT / ETCBC Dead Sea Scrolls dataset (Martin Abegg's data), CC BY-NC 4.0.
 *
 * A "meaning" difference is a different word, one the scroll adds or leaves out, or a different form.
 * A "spelling" difference is the same word written another way. The page shows meaning differences by default
 * and spelling differences on request.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** The variants of one chapter, or null when none have been built for it. */
function tbe_dss_chapter( string $slug, int $chapter ): ?array {
	$file = TBE_DIR . '/data/dss/' . $slug . '/' . $chapter . '.json';
	if ( ! is_readable( $file ) ) {
		return null;
	}
	$data = json_decode( (string) file_get_contents( $file ), true );
	return ( is_array( $data ) && isset( $data['v'] ) ) ? $data : null;
}

/**
 * The page's Hebrew words as the comparison counted them (a maqaf or a paragraph mark splits one entry in two),
 * as a list of array( interlinear index, text ) in order.
 *
 * @return array<int,array{0:int,1:string}>
 */
function tbe_dss_parts( array $words ): array {
	$parts = array();
	foreach ( $words as $idx => $w ) {
		foreach ( (array) preg_split( '/[\x{05BE}\s]+/u', (string) $w[0] ) as $part ) {
			$letters = preg_replace( '/[^\x{05D0}-\x{05EA}]/u', '', (string) $part );
			if ( '' !== $letters && ! in_array( $letters, array( 'ס', 'פ', 'ר' ), true ) ) {
				$parts[] = array( (int) $idx, (string) $part );
			}
		}
	}
	return $parts;
}

/**
 * What to show for one verse: the kind to mark each interlinear word with, and the notes under the verse.
 *
 * @return array{words:array<int,array{kind:string,tip:string}>,notes:string}
 */
function tbe_dss_verse( array $variants, array $words, string $scroll ): array {
	$parts = tbe_dss_parts( $words );
	$marks = array();
	$notes = '';
	foreach ( $variants as $v ) {
		$kind = 'm' === $v['k'] ? 'm' : 's';
		[ $a, $b ] = $v['mt'];
		$is_add    = $a === $b;
		$covered   = array();
		for ( $i = $is_add ? max( 0, $a - 1 ) : $a; $i < ( $is_add ? $a : $b ); $i++ ) {
			if ( isset( $parts[ $i ] ) ) {
				$covered[ $parts[ $i ][0] ] = true;
			}
		}
		$hebrew = implode( ' ', array_map( static fn( $i ) => $parts[ $i ][1] ?? '', range( $a, max( $a, $b - 1 ) ) ) );
		$read   = '<bdi class="tbe-dss__he" lang="he" dir="rtl">' . esc_html( (string) $v['ds'] ) . '</bdi>';
		$mt     = '<bdi class="tbe-dss__he" lang="he" dir="rtl">' . esc_html( trim( $hebrew ) ) . '</bdi>';
		if ( 'm' === $v['k'] ) {
			if ( 'a' === $v['t'] ) {
				$text = sprintf( /* translators: %s: Hebrew words */ __( 'The scroll adds %s.', 'tbe-child' ), $read );
			} elseif ( 'o' === $v['t'] ) {
				$text = sprintf( /* translators: %s: Hebrew words */ __( 'The scroll has no %s.', 'tbe-child' ), $mt );
			} else {
				$text = sprintf( /* translators: 1: scroll reading, 2: Masoretic reading */ __( 'The scroll reads %1$s where the Masoretic text has %2$s.', 'tbe-child' ), $read, $mt );
			}
		} else {
			$text = sprintf( /* translators: 1: scroll spelling, 2: Masoretic spelling */ __( 'Same word, spelled %1$s in the scroll and %2$s in the Masoretic text.', 'tbe-child' ), $read, $mt );
		}
		$flag = '';
		if ( ! empty( $v['r'] ) ) {
			$flag = ' <span class="tbe-dss__flag">' . esc_html__( 'partly restored by editors', 'tbe-child' ) . '</span>';
		} elseif ( ! empty( $v['u'] ) ) {
			$flag = ' <span class="tbe-dss__flag">' . esc_html__( 'uncertain reading', 'tbe-child' ) . '</span>';
		}
		$notes .= '<li class="tbe-dss__item tbe-dss__item--' . $kind . '">' . $text . $flag . '</li>';
		$tip    = wp_strip_all_tags( $text );
		foreach ( array_keys( $covered ) as $idx ) {
			// A meaning difference outranks a spelling difference on the same word.
			if ( ! isset( $marks[ $idx ] ) || ( 'm' === $kind && 's' === $marks[ $idx ]['kind'] ) ) {
				$marks[ $idx ] = array( 'kind' => $kind, 'tip' => sprintf( /* translators: 1: scroll name, 2: what differs */ __( '%1$s: %2$s', 'tbe-child' ), $scroll, $tip ) );
			}
		}
	}
	return array( 'words' => $marks, 'notes' => '' === $notes ? '' : '<ul class="tbe-dss">' . $notes . '</ul>' );
}

/** How many meaning and spelling differences a chapter has. @return array{0:int,1:int} */
function tbe_dss_counts( array $dss ): array {
	$m = 0;
	$s = 0;
	foreach ( $dss['v'] as $items ) {
		foreach ( $items as $v ) {
			'm' === $v['k'] ? ++$m : ++$s;
		}
	}
	return array( $m, $s );
}
