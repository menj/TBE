<?php
/**
 * Enrich commentary HTML: scripture references become hover cards, glossary terms get tooltips.
 * Only commentary is touched. The Bible text itself is never altered.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run a callback over every eligible text node. The callback receives the DOM and the text and
 * returns a DocumentFragment to replace the node with, or null to leave it alone.
 * Text inside links, headings, code, buttons, footnote markers and existing terms is skipped.
 */
function tbe_transform_text_nodes( string $html, callable $transform ): string {
	// Hover cards and glossary tooltips need PHP's DOM extension; without it, show the text as written.
	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}
	$previous = libxml_use_internal_errors( true );
	$doc      = new DOMDocument( '1.0', 'UTF-8' );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="tbe-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	$xpath = new DOMXPath( $doc );
	$nodes = $xpath->query(
		"//div[@id='tbe-root']//text()[normalize-space() and not(ancestor::a) and not(ancestor::button) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::script) and not(ancestor::style) and not(ancestor::sup) and not(ancestor::h1 or ancestor::h2 or ancestor::h3 or ancestor::h4 or ancestor::h5 or ancestor::h6)]"
	);
	$list  = array();
	foreach ( $nodes as $node ) {
		$list[] = $node;
	}
	foreach ( $list as $node ) {
		$fragment = $transform( $doc, (string) $node->nodeValue );
		if ( $fragment ) {
			$node->parentNode->replaceChild( $fragment, $node );
		}
	}

	$root = $doc->getElementsByTagName( 'div' )->item( 0 );
	$out  = '';
	foreach ( $root->childNodes as $child ) {
		$out .= $doc->saveHTML( $child );
	}
	return $out;
}

/** Short ESV snippet for a hover card, read from the bundled text. */
function tbe_verse_snippet( array $parsed ): string {
	static $cache = array();
	$key = $parsed['book'] . ':' . $parsed['chapter'];
	if ( ! isset( $cache[ $key ] ) ) {
		$cache[ $key ] = TBE_Bible::chapter( $parsed['book'], $parsed['chapter'] ) ?: array();
	}
	$start = $parsed['vstart'] ?: 1;
	$end   = $parsed['vstart'] ? min( $parsed['vend'], $start + 2 ) : $start + 1;
	$text  = array();
	foreach ( $cache[ $key ] as $verse ) {
		if ( $verse['n'] >= $start && $verse['n'] <= $end ) {
			$text[] = preg_replace( '/\s+/u', ' ', (string) $verse['t'] );
		}
	}
	$snippet = trim( implode( ' ', $text ) );
	return mb_strlen( $snippet, 'UTF-8' ) > 320 ? mb_substr( $snippet, 0, 317, 'UTF-8' ) . '…' : $snippet;
}

/** Turn "John 3:16" and "Qur'an 2:255" in commentary into links with hover cards. */
function tbe_linkify_references( string $html ): string {
	if ( ! tbe_setting( 'ref_cards' ) ) {
		return $html;
	}
	$bible_re = '/(?<![\p{L}\p{N}])((?:[1-3]|I{1,3})\s?)?([A-Z][a-z]+\.?(?:\s(?:of\s)?[A-Z][a-z]+)?)\s(\d{1,3}):(\d{1,3})(?:\s?[-–]\s?(\d{1,3}))?(?![\p{L}\p{N}])/u';
	$quran_re = '/(?<![\p{L}\p{N}])(?:Qur[’\']?an|Quran|Q)\.?\s?(\d{1,3}):(\d{1,3})(?:\s?[-–]\s?(\d{1,3}))?(?![\p{L}\p{N}])/u';

	return tbe_transform_text_nodes(
		$html,
		static function ( DOMDocument $doc, string $text ) use ( $bible_re, $quran_re ) {
			$hits = array();

			if ( preg_match_all( $quran_re, $text, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
				foreach ( $found as $m ) {
					$ref = tbe_parse_quran_ref( $m[1][0] . ':' . $m[2][0] . ( isset( $m[3] ) ? '-' . $m[3][0] : '' ) );
					if ( $ref ) {
						$hits[] = array( $m[0][1], strlen( $m[0][0] ), 'quran', $ref, $m[0][0] );
					}
				}
			}

			if ( preg_match_all( $bible_re, $text, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
				foreach ( $found as $m ) {
					$whole  = $m[0][0];
					$offset = $m[0][1];
					$parsed = TBE_Bible::parse_reference( $whole );
					// "In John 3:16": drop the leading word and retry.
					if ( ! $parsed && preg_match( '/^[A-Za-z]+\s+(\S.*)$/u', $whole, $short ) && ! preg_match( '/^\d/', $whole ) ) {
						$offset += strlen( $whole ) - strlen( $short[1] );
						$whole   = $short[1];
						$parsed  = TBE_Bible::parse_reference( $whole );
					}
					if ( $parsed && $parsed['vstart'] ) {
						$overlap = false;
						foreach ( $hits as $h ) {
							if ( $offset < $h[0] + $h[1] && $offset + strlen( $whole ) > $h[0] ) {
								$overlap = true;
							}
						}
						if ( ! $overlap ) {
							$hits[] = array( $offset, strlen( $whole ), 'bible', $parsed, $whole );
						}
					}
				}
			}
			if ( ! $hits ) {
				return null;
			}
			usort( $hits, static fn( $a, $b ) => $a[0] <=> $b[0] );

			$fragment = $doc->createDocumentFragment();
			$cursor   = 0;
			foreach ( $hits as [ $offset, $length, $type, $data, $shown ] ) {
				$fragment->appendChild( $doc->createTextNode( substr( $text, $cursor, $offset - $cursor ) ) );
				$link = $doc->createElement( 'a' );
				$link->appendChild( $doc->createTextNode( $shown ) );
				if ( 'bible' === $type ) {
					$link->setAttribute( 'class', 'tbe-ref' );
					$link->setAttribute( 'href', TBE_Bible::url( $data['book'], $data['chapter'], $data['vstart'] ?: null ) );
					$link->setAttribute( 'data-tbe-tip-title', TBE_Bible::label( $data['book'], $data['chapter'], $data['vstart'], $data['vend'] ) );
					$snippet = tbe_verse_snippet( $data );
					if ( '' !== $snippet ) {
						$link->setAttribute( 'data-tbe-tip', $snippet );
						$link->setAttribute( 'data-tbe-tip-scripture', '1' );
					}
				} else {
					$link->setAttribute( 'class', 'tbe-ref tbe-ref--quran' );
					$link->setAttribute( 'href', tbe_quran_url( $data ) );
					$link->setAttribute( 'target', '_blank' );
					$link->setAttribute( 'rel', 'noopener' );
					$link->setAttribute( 'data-tbe-tip-title', tbe_quran_label( $data ) );
					$link->setAttribute( 'data-tbe-tip', __( 'Opens the Qur\'an on quran.com in a new tab.', 'tbe-child' ) );
				}
				$fragment->appendChild( $link );
				$cursor = $offset + $length;
			}
			$fragment->appendChild( $doc->createTextNode( substr( $text, $cursor ) ) );
			return $fragment;
		}
	);
}

/**
 * Wrap the first occurrence of each glossary term in a link that carries its tooltip.
 */
function tbe_annotate_terms( string $html ): string {
	if ( '' === trim( $html ) || ! tbe_setting( 'glossary_tooltips' ) ) {
		return $html;
	}
	$terms = tbe_glossary_terms();
	if ( ! $terms ) {
		return $html;
	}

	$map = array();
	foreach ( $terms as $i => $term ) {
		foreach ( $term['names'] as $name ) {
			$key = mb_strtolower( $name, 'UTF-8' );
			if ( '' !== $key && ! isset( $map[ $key ] ) ) {
				$map[ $key ] = $i;
			}
		}
	}
	uksort(
		$map,
		static function ( $a, $b ) {
			return mb_strlen( $b, 'UTF-8' ) <=> mb_strlen( $a, 'UTF-8' );
		}
	);
	$alternatives = implode( '|', array_map( static fn( $n ) => preg_quote( (string) $n, '/' ), array_keys( $map ) ) );
	$pattern      = '/(?<![\p{L}\p{N}])(' . $alternatives . ')(?![\p{L}\p{N}])/iu';
	if ( ! preg_match( $pattern, wp_strip_all_tags( $html ) ) ) {
		return $html;
	}

	$used = array();
	return tbe_transform_text_nodes(
		$html,
		static function ( DOMDocument $doc, string $text ) use ( $pattern, $map, $terms, &$used ) {
			if ( ! preg_match_all( $pattern, $text, $found, PREG_OFFSET_CAPTURE ) ) {
				return null;
			}
			$fragment = $doc->createDocumentFragment();
			$cursor   = 0;
			$changed  = false;
			foreach ( $found[0] as [ $matched, $offset ] ) {
				$index = $map[ mb_strtolower( $matched, 'UTF-8' ) ] ?? null;
				if ( null === $index || isset( $used[ $index ] ) ) {
					continue;
				}
				$used[ $index ] = true;
				$changed        = true;
				$fragment->appendChild( $doc->createTextNode( substr( $text, $cursor, $offset - $cursor ) ) );
				$link = $doc->createElement( 'a' );
				$link->setAttribute( 'class', 'tbe-term' );
				$link->setAttribute( 'href', $terms[ $index ]['url'] );
				$link->setAttribute( 'data-tbe-tip-title', $terms[ $index ]['title'] );
				$link->setAttribute( 'data-tbe-tip', $terms[ $index ]['tip'] );
				$link->appendChild( $doc->createTextNode( $matched ) );
				$fragment->appendChild( $link );
				$cursor = $offset + strlen( $matched );
			}
			if ( ! $changed ) {
				return null;
			}
			$fragment->appendChild( $doc->createTextNode( substr( $text, $cursor ) ) );
			return $fragment;
		}
	);
}

/** Everything commentary gets: scripture cards first, then glossary tooltips. */
function tbe_enrich_commentary( string $html ): string {
	return tbe_annotate_terms( tbe_linkify_references( $html ) );
}
