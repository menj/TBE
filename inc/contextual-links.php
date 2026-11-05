<?php
/**
 * Contextual links (0.10.8): in the running text of a single commentary entry, the first mention of a phrase the
 * link map knows becomes a link to the page that treats it. The map is data/link-map.json, the same file the
 * owner's other themes carry; Appearance, TBE, Commentary pages switches the links on or off, caps them and adds
 * phrases of its own. The stored text is never touched: links are made as the page renders.
 *
 * The theme already turns scripture references into hover cards and glossary terms into tooltips
 * (inc/enrich.php). Those come first: this engine runs after them, at priority 100 of the_content, and leaves
 * alone any text they or the author have already linked, so "John 3:16" stays a scripture card.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** Normalise a phrase or matched text for lookup: straight apostrophes, single spaces, lower case. */
function tbe_ctx_key( string $text ): string {
	$text = str_replace( array( '&#8217;', '&#039;', '&#39;', '&rsquo;', '’', 'ʼ' ), "'", $text );
	$text = (string) preg_replace( '/(?:\s|&nbsp;|&#160;|\x{00A0})+/u', ' ', $text );
	return mb_strtolower( trim( $text ), 'UTF-8' );
}

/**
 * Patterns for the phrases, at most 150 to a pattern: one pattern of the whole map is more than PCRE compiles. A
 * chunk that fails is split in two until it compiles; a phrase that cannot compile on its own is left out.
 *
 * @param string[] $keys Normalised phrases, longest first.
 * @return string[]
 */
function tbe_ctx_patterns( array $keys ): array {
	$patterns = array();
	foreach ( array_chunk( $keys, 150 ) as $chunk ) {
		$queue = array( $chunk );
		while ( $queue ) {
			$part = array_shift( $queue );
			$alts = array();
			foreach ( $part as $key ) {
				$alt    = preg_quote( $key, '/' );
				$alt    = str_replace( "'", "(?:'|’|ʼ|&\\#8217;|&\\#0?39;|&rsquo;)", $alt );
				$alts[] = str_replace( ' ', '(?:\s|&nbsp;|&\\#160;|\x{00A0})+', $alt );
			}
			$pattern = '/(?<![\p{L}\p{N}&#])(?:' . implode( '|', $alts ) . ')(?![\p{L}\p{N}])/iu';
			if ( false !== @preg_match( $pattern, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the failure is handled here.
				$patterns[] = $pattern;
			} elseif ( count( $part ) > 1 ) {
				$half = (int) ceil( count( $part ) / 2 );
				array_push( $queue, array_slice( $part, 0, $half ), array_slice( $part, $half ) );
			}
		}
	}
	return $patterns;
}

/** A URL reduced to host and path, for comparing targets and the current page. */
function tbe_ctx_norm_url( string $url ): string {
	$parts = wp_parse_url( html_entity_decode( $url ) );
	if ( ! is_array( $parts ) || ( empty( $parts['path'] ) && empty( $parts['host'] ) ) ) {
		return '';
	}
	$host = strtolower( (string) preg_replace( '/^www\./i', '', $parts['host'] ?? (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	return $host . '/' . trim( strtolower( $parts['path'] ?? '' ), '/' );
}

/**
 * Read one "phrase | address" line from the settings. A path such as /commentary/john-1/ is a page on this site;
 * a full address must be on a host the link map names.
 *
 * @param string $line  One line.
 * @param array  $hosts Host => site key, from the map.
 * @return array{0:string,1:string,2:bool}|null Phrase, URL, whether it is this site.
 */
function tbe_ctx_parse_line( string $line, array $hosts ): ?array {
	$parts = array_map( 'trim', explode( '|', $line, 2 ) );
	if ( count( $parts ) < 2 || '' === $parts[0] || '' === $parts[1] ) {
		return null;
	}
	if ( '/' === $parts[1][0] && '/' !== ( $parts[1][1] ?? '' ) ) {
		return array( $parts[0], home_url( $parts[1] ), true );
	}
	$host = strtolower( (string) preg_replace( '/^www\./i', '', (string) wp_parse_url( $parts[1], PHP_URL_HOST ) ) );
	if ( ! preg_match( '#^https?://#i', $parts[1] ) || ! isset( $hosts[ $host ] ) ) {
		return null;
	}
	return array( $parts[0], $parts[1], false );
}

/** The hosts the link map names: host => site key. */
function tbe_ctx_hosts( array $map ): array {
	$hosts = array();
	foreach ( (array) ( $map['sites'] ?? array() ) as $key => $base ) {
		$hosts[ strtolower( (string) preg_replace( '/^www\./i', '', (string) wp_parse_url( (string) $base, PHP_URL_HOST ) ) ) ] = (string) $key;
	}
	return $hosts;
}

/** The link map, read once a request; filter tbe_contextual_links_map changes it. */
function tbe_ctx_map(): array {
	static $map = null;
	if ( null === $map ) {
		$file = TBE_DIR . '/data/link-map.json';
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$map  = (array) apply_filters( 'tbe_contextual_links_map', is_array( $data ) ? $data : array() );
	}
	return $map;
}

/**
 * The compiled phrases: phrase => array( url, own ), and the patterns. Lines from the settings come first, so they
 * win over the shared map.
 *
 * @return array{phrases:array<string,array{0:string,1:bool}>,patterns:string[]}
 */
function tbe_ctx_compiled(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$map     = tbe_ctx_map();
	$sites   = array_map( 'untrailingslashit', array_map( 'strval', (array) ( $map['sites'] ?? array() ) ) );
	$hosts   = tbe_ctx_hosts( $map );
	$self    = strtolower( (string) preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	$phrases = array();
	$add     = static function ( string $phrase, string $url, bool $own ) use ( &$phrases ) {
		$key = tbe_ctx_key( $phrase );
		if ( '' !== $key && ! isset( $phrases[ $key ] ) ) {
			$phrases[ $key ] = array( $url, $own );
		}
	};
	foreach ( preg_split( '/\r\n|\r|\n/', (string) tbe_setting( 'ctx_extra' ) ) as $line ) {
		$hit = tbe_ctx_parse_line( (string) $line, $hosts );
		if ( $hit ) {
			$add( $hit[0], $hit[1], $hit[2] );
		}
	}
	foreach ( (array) ( $map['links'] ?? array() ) as $link ) {
		$site = (string) ( $link['site'] ?? '' );
		$path = '/' . ltrim( (string) ( $link['path'] ?? '' ), '/' );
		if ( '/' === $path || ! isset( $sites[ $site ] ) ) {
			continue;
		}
		$host = strtolower( (string) preg_replace( '/^www\./i', '', (string) wp_parse_url( $sites[ $site ], PHP_URL_HOST ) ) );
		$own  = $host === $self;
		foreach ( (array) ( $link['phrases'] ?? array() ) as $phrase ) {
			$add( (string) $phrase, $own ? home_url( $path ) : $sites[ $site ] . $path, $own );
		}
	}
	$keys = array_map( 'strval', array_keys( $phrases ) );
	usort( $keys, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
	$cache = array(
		'phrases'  => $phrases,
		'patterns' => tbe_ctx_patterns( $keys ),
	);
	return $cache;
}

/**
 * The target for matched text.
 *
 * @param array  $phrases Normalised phrase => target.
 * @param string $match   Matched text.
 * @return array{0:string,1:bool}|null
 */
function tbe_ctx_lookup( array $phrases, string $match ): ?array {
	$key = tbe_ctx_key( $match );
	return $phrases[ $key ] ?? null;
}

/** Whether a tag opens an element that no link may be made inside. */
function tbe_ctx_forbids( string $tag, string $piece ): bool {
	static $tags = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'q', 'figure', 'figcaption', 'cite', 'code', 'pre', 'sup', 'sub', 'nav', 'table', 'button', 'label', 'select', 'textarea', 'summary', 'script', 'style', 'aside', 'header', 'footer' );
	// Scripture and the original languages, the passage line, the apparatus and the article's own blocks.
	static $classes = array( 'tbe-passage', 'tbe-variants', 'tbe-quranrefs', 'tbe-tablewrap', 'tbe-verse', 'tbe-ref', 'tbe-term', 'tbe-answer', 'tbe-contents', 'tbe-next', 'tbe-sources', 'tbe-related', 'wp-block-footnotes', 'is-style-scripture', 'wp-block-quote', 'wp-block-pullquote', 'wp-block-table' );
	if ( in_array( $tag, $tags, true ) ) {
		return true;
	}
	if ( preg_match( '/\blang\s*=\s*["\']?(?:ar|he|hbo|el|grc|syr)\b/i', $piece ) || preg_match( '/\bdir\s*=\s*["\']?rtl\b/i', $piece ) ) {
		return true;
	}
	if ( preg_match( '/\bclass\s*=\s*"([^"]*)"/i', $piece, $m ) || preg_match( "/\\bclass\\s*=\\s*'([^']*)'/i", $piece, $m ) ) {
		return (bool) array_intersect( preg_split( '/\s+/', trim( $m[1] ) ), $classes );
	}
	return false;
}

/**
 * Add the links to rendered content: at most one new link in a paragraph or list item, each target once, never
 * the page itself or a target the text already links to.
 *
 * @param string $content Rendered content.
 * @param string $here    The current page's URL.
 */
function tbe_ctx_apply( string $content, string $here ): string {
	$caps = array(
		1 => max( 0, min( 15, (int) tbe_setting( 'ctx_own_max' ) ) ),
		0 => tbe_setting( 'ctx_out' ) ? max( 0, min( 10, (int) tbe_setting( 'ctx_out_max' ) ) ) : 0,
	);
	$map  = tbe_ctx_compiled();
	if ( ! $map['patterns'] || ( ! $caps[0] && ! $caps[1] ) || false === strpos( $content, '<' ) ) {
		return $content;
	}
	$pieces = preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( false === $pieces ) {
		return $content;
	}
	$done = array( tbe_ctx_norm_url( $here ) => true );
	if ( preg_match_all( '/\bhref\s*=\s*["\']([^"\']+)["\']/i', $content, $hrefs ) ) {
		foreach ( $hrefs[1] as $href ) {
			$done[ tbe_ctx_norm_url( $href ) ] = true;
		}
	}
	$made   = array( 0 => 0, 1 => 0 );
	$stack  = array();
	$blocks = array();
	$void   = array( 'br', 'img', 'hr', 'wbr', 'input', 'source', 'meta', 'link', 'col', 'area', 'embed', 'track' );
	$out    = '';
	foreach ( $pieces as $piece ) {
		if ( '' === $piece ) {
			continue;
		}
		if ( '<' === $piece[0] ) {
			if ( preg_match( '#^<(/?)([a-zA-Z][a-zA-Z0-9]*)#', $piece, $m ) ) {
				$tag = strtolower( $m[2] );
				if ( '/' === $m[1] ) {
					for ( $i = count( $stack ) - 1; $i >= 0; $i-- ) {
						if ( $stack[ $i ] === $tag ) {
							array_splice( $stack, $i );
							break;
						}
					}
					if ( ( 'p' === $tag || 'li' === $tag ) && $blocks ) {
						array_pop( $blocks );
					}
				} elseif ( ! in_array( $tag, $void, true ) && '/' !== substr( $piece, -2, 1 ) ) {
					if ( $stack || tbe_ctx_forbids( $tag, $piece ) ) {
						$stack[] = $tag;
					}
					if ( 'li' === $tag ) {
						$blocks[] = array( 'li', false );
					} elseif ( 'p' === $tag ) {
						$blocks[] = array( 'p', $blocks ? end( $blocks )[1] : false );
					}
				}
			}
			$out .= $piece;
			continue;
		}
		if ( $stack || ( $blocks && end( $blocks )[1] ) || '' === trim( $piece ) || ( $made[0] >= $caps[0] && $made[1] >= $caps[1] ) ) {
			$out .= $piece;
			continue;
		}
		$linked = false;
		$pos    = 0;
		while ( true ) {
			$best = null;
			foreach ( $map['patterns'] as $pattern ) {
				if ( preg_match( $pattern, $piece, $f, PREG_OFFSET_CAPTURE, $pos ) && ( ! $best || $f[0][1] < $best[1] || ( $f[0][1] === $best[1] && strlen( $f[0][0] ) > strlen( $best[0] ) ) ) ) {
					$best = $f[0];
				}
			}
			if ( ! $best ) {
				break;
			}
			list( $match, $at ) = $best;
			$pos = $at + strlen( $match );
			$hit = tbe_ctx_lookup( $map['phrases'], $match );
			if ( ! $hit ) {
				continue;
			}
			list( $url, $own ) = $hit;
			$kind = $own ? 1 : 0;
			$norm = tbe_ctx_norm_url( $url );
			if ( $made[ $kind ] >= $caps[ $kind ] || isset( $done[ $norm ] ) ) {
				continue;
			}
			$done[ $norm ] = true;
			++$made[ $kind ];
			$linked = true;
			$piece  = substr( $piece, 0, $at ) . ( $own
				? '<a class="tbe-ctx-link" href="' . esc_url( $url ) . '">' . $match . '</a>'
				: '<a class="tbe-ctx-link tbe-ctx-link--out" href="' . esc_url( $url ) . '" rel="noopener">' . $match . '</a>' ) . substr( $piece, $pos );
			break;
		}
		for ( $i = count( $blocks ) - 1; $linked && $i >= 0; $i-- ) {
			$blocks[ $i ][1] = true;
			if ( 'li' === $blocks[ $i ][0] ) {
				break;
			}
		}
		$out .= $piece;
	}
	return $out;
}

/*
 * Priority 100: after the blocks, shortcodes, the passage line and apparatus (20) and the scripture cards and
 * glossary tooltips (99). The passage line, the variants table and the next step are skipped by their classes.
 * Block templates render the Post Content block outside a classic loop, so the queried entry is matched instead
 * of in_the_loop().
 */
add_filter(
	'the_content',
	static function ( $content ) {
		// The same test as the passage line and the glossary tooltips (tbe_is_entry_body()), so the scripture cards at
		// 99 have always run first and "John 3:16" stays a card.
		if ( ! tbe_is_entry_body() ) {
			return $content;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! apply_filters( 'tbe_contextual_links_enabled', true, $post ) ) {
			return $content;
		}
		return tbe_ctx_apply( (string) $content, (string) get_permalink( $post ) );
	},
	100
);
