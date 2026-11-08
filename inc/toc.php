<?php
/**
 * Contents (table of contents), built into the theme (0.10.21). It brings the features of WP TOC
 * (https://github.com/menj/wp-toc, GPL-3.0-or-later, by MENJ) into the theme's own contents box, so there is one
 * look and one set of anchors:
 *
 * - Every level-two and level-three heading (and level four, when asked for) in the main text of a single page
 *   gets an anchor. An id the author set is kept and reserved; made ids come from the heading's own text, letters
 *   outside ASCII kept, and are numbered when taken. Level-two ids are made first, so the anchors of 0.10.18 and
 *   earlier stay as they were.
 * - A heading with the class "no-toc" or a data-no-toc attribute is left out of the contents.
 * - Commentary entries show the contents where their template puts the tbe/contents block (inline or sticky, as
 *   set under Appearance, TBE). Pages, posts and glossary terms get the same box before the first heading once they
 *   have three entries (filter tbe_toc_post_types).
 * - [wp_toc] and [auto_toc] put the contents exactly where they are typed, with WP TOC's attributes: tags (h2,h3),
 *   title, depth, min, collapsible, collapsed. A post that has one shows no automatic contents.
 * - The structured data lists the sections as WebPageElement parts of the page, with their anchors.
 * - When the WP TOC plugin itself is active, the theme leaves posts, pages and the shortcodes to it, and keeps the
 *   plugin off commentary entries, which have their own contents. Only one contents box ever shows.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** Whether the WP TOC plugin is loaded, in which case the theme gives way outside commentary entries. */
function tbe_toc_plugin_active(): bool {
	return defined( 'WP_TOC_VERSION' );
}

/** The post types that get the automatic contents, apart from commentary entries. Filter: tbe_toc_post_types. */
function tbe_toc_post_types(): array {
	return (array) apply_filters( 'tbe_toc_post_types', array( 'post', 'page', 'tbe_term' ) );
}

/**
 * The headings of a piece of HTML, in order, each with the anchor it has or will get.
 *
 * Level-two anchors are made exactly as before 0.10.21: only level-two ids the author set are reserved, then each
 * level-two heading takes its slug in order. Only then are the deeper headings' own ids reserved and their slugs
 * made, level three first, so no deeper heading, with an id of its own or a made one, ever moves a level-two anchor
 * that links already point to. (An author id on a deeper heading that repeats a made level-two id stays as the
 * author set it, as it did before.)
 *
 * @param string $html   Stored or rendered text.
 * @param int[]  $levels Heading levels that get an anchor (all six by default, so any shortcode "tags" links).
 * @return array<int,array{level:int,id:string,text:string,own:bool,skip:bool}> Every h1-h6, in order. A heading
 *         outside $levels, left out with no-toc, or with no text has id '' unless the author set one.
 */
function tbe_toc_heading_list( string $html, array $levels = array( 1, 2, 3, 4, 5, 6 ) ): array {
	if ( ! preg_match_all( '#<h([1-6])(\s[^>]*)?>(.*?)</h\1>#is', $html, $found, PREG_SET_ORDER ) ) {
		return array();
	}
	$list = array();
	foreach ( $found as $m ) {
		$attrs  = (string) ( $m[2] ?? '' );
		$own    = preg_match( '/\sid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $idm ) ? trim( $idm[1] ) : '';
		$skip   = (bool) preg_match( '/\sclass\s*=\s*["\'][^"\']*(?<![\w-])no-toc(?![\w-])/i', $attrs ) || (bool) preg_match( '/\sdata-no-toc(\s|=|$)/i', $attrs );
		$list[] = array(
			'level' => (int) $m[1],
			'id'    => $own,
			'text'  => tbe_article_heading_text( $m[3] ),
			'own'   => '' !== $own,
			'skip'  => $skip,
		);
	}
	$seen = array();
	foreach ( $list as $h ) {
		if ( 2 === $h['level'] && $h['own'] ) {
			$seen[ $h['id'] ] = true;
		}
	}
	foreach ( array( 2, 3, 4, 5, 6, 1 ) as $level ) {
		if ( 3 === $level ) {
			foreach ( $list as $h ) {
				if ( 2 !== $h['level'] && $h['own'] ) {
					$seen[ $h['id'] ] = true;
				}
			}
		}
		if ( ! in_array( $level, $levels, true ) ) {
			continue;
		}
		foreach ( $list as $i => $h ) {
			if ( $level === $h['level'] && ! $h['own'] && ! $h['skip'] && '' !== $h['text'] ) {
				$list[ $i ]['id'] = tbe_article_slug( $h['text'], $seen );
			}
		}
	}
	return $list;
}

/** Give the headings of a piece of HTML their anchors, matching tbe_toc_heading_list(). */
function tbe_toc_apply_ids( string $html, array $levels = array( 1, 2, 3, 4, 5, 6 ) ): string {
	$list = tbe_toc_heading_list( $html, $levels );
	$i    = 0;
	return (string) preg_replace_callback(
		'#<h([1-6])(\s[^>]*)?>(.*?)</h\1>#is',
		static function ( $m ) use ( $list, &$i ) {
			$h = $list[ $i++ ] ?? null;
			if ( ! $h || $h['own'] || '' === $h['id'] ) {
				return $m[0];
			}
			return '<h' . $m[1] . ' id="' . esc_attr( $h['id'] ) . '"' . (string) ( $m[2] ?? '' ) . '>' . $m[3] . '</h' . $m[1] . '>';
		},
		$html
	);
}

/** The contents entries of a piece of HTML: headings at the given levels, with an anchor and text, not left out. */
function tbe_toc_items( string $html, array $levels = array( 2, 3 ) ): array {
	return array_values(
		array_filter(
			tbe_toc_heading_list( $html ),
			static fn( $h ) => in_array( $h['level'], $levels, true ) && ! $h['skip'] && '' !== $h['id'] && '' !== $h['text']
		)
	);
}

/** Shortcode attributes, as WP TOC takes them, made safe: levels, title, depth, min, collapsible, collapsed. */
function tbe_toc_atts( array $atts ): array {
	$atts   = array_merge(
		array(
			'tags'        => 'h2,h3',
			'title'       => __( 'Contents', 'tbe-child' ),
			'depth'       => '0',
			'min'         => '1',
			'collapsible' => 'false',
			'collapsed'   => 'false',
		),
		array_change_key_case( $atts, CASE_LOWER )
	);
	$levels = array();
	foreach ( explode( ',', strtolower( (string) $atts['tags'] ) ) as $tag ) {
		if ( preg_match( '/^\s*h([1-6])\s*$/', $tag, $m ) ) {
			$levels[] = (int) $m[1];
		}
	}
	$bool = static fn( $v ) => in_array( strtolower( trim( (string) $v ) ), array( '1', 'true', 'yes', 'on' ), true );
	return array(
		'levels'      => $levels ? array_values( array_unique( $levels ) ) : array( 2, 3 ),
		'title'       => (string) $atts['title'],
		'depth'       => max( 0, (int) $atts['depth'] ),
		'min'         => max( 1, (int) $atts['min'] ),
		'collapsible' => $bool( $atts['collapsible'] ),
		'collapsed'   => $bool( $atts['collapsed'] ),
	);
}

/**
 * The entries nested: each under the closest earlier entry of a higher level, so a skipped level (h2 then h4) or a
 * deeper first heading still nests cleanly. $depth limits the nesting (0: no limit).
 *
 * @return array<int,array{id:string,text:string,children:array}>
 */
function tbe_toc_tree( array $items, int $depth = 0 ): array {
	$root  = array( 'level' => 0, 'children' => array() );
	$stack = array( &$root );
	foreach ( $items as $item ) {
		while ( count( $stack ) > 1 && end( $stack )['level'] >= $item['level'] ) {
			array_pop( $stack );
		}
		if ( $depth > 0 && count( $stack ) > $depth ) {
			continue;
		}
		$parent               = &$stack[ count( $stack ) - 1 ];
		$parent['children'][] = array( 'level' => $item['level'], 'id' => $item['id'], 'text' => $item['text'], 'children' => array() );
		$stack[]              = &$parent['children'][ count( $parent['children'] ) - 1 ];
		unset( $parent );
	}
	return $root['children'];
}

/** The nested list of a tree. */
function tbe_toc_list( array $nodes ): string {
	$out = '<ol>';
	foreach ( $nodes as $n ) {
		$out .= '<li><a href="#' . esc_attr( $n['id'] ) . '">' . esc_html( $n['text'] ) . '</a>' . ( $n['children'] ? tbe_toc_list( $n['children'] ) : '' ) . '</li>';
	}
	return $out . '</ol>';
}

/**
 * The contents box. $style: 'inline' (a box with its title), 'sticky' (a bar that opens on a tap, commentary only)
 * or 'collapsible' (a box that opens and closes, open unless $collapsed). An empty $title hides the inline box's
 * title, as title="" does in WP TOC; the box keeps "Contents" as its accessible name, and the sticky and collapsible
 * boxes, which need a label to tap, show "Contents".
 */
function tbe_toc_box( array $tree, string $title, string $style = 'inline', bool $collapsed = false ): string {
	if ( ! $tree ) {
		return '';
	}
	static $n = 0;
	++$n;
	$hidden = '' === trim( $title );
	$title  = $hidden ? __( 'Contents', 'tbe-child' ) : $title;
	$label  = ' aria-label="' . esc_attr( $title ) . '"';
	if ( 'sticky' === $style ) {
		return '<details class="tbe-contents tbe-contents--sticky" data-tbe-contents><summary>' . esc_html( $title ) . '</summary><nav' . $label . '>' . tbe_toc_list( $tree ) . '</nav></details>';
	}
	if ( 'collapsible' === $style ) {
		return '<details class="tbe-contents tbe-contents--inline tbe-contents--collapsible"' . ( $collapsed ? '' : ' open' ) . '><summary class="tbe-contents__title">' . esc_html( $title ) . '</summary><nav' . $label . '>' . tbe_toc_list( $tree ) . '</nav></details>';
	}
	if ( $hidden ) {
		return '<nav class="tbe-contents tbe-contents--inline"' . $label . '>' . tbe_toc_list( $tree ) . '</nav>';
	}
	$id = 1 === $n ? 'tbe-contents-title' : 'tbe-contents-title-' . $n;
	return '<nav class="tbe-contents tbe-contents--inline" aria-labelledby="' . $id . '"><h2 id="' . $id . '" class="tbe-contents__title no-toc">' . esc_html( $title ) . '</h2>' . tbe_toc_list( $tree ) . '</nav>';
}

/** The styles of the contents box, on pages other than commentary entries (which load them already). */
function tbe_toc_enqueue(): void {
	if ( function_exists( 'tbe_enqueue_theme_style' ) && ! is_admin() ) {
		tbe_enqueue_theme_style( 'tbe-article', 'assets/css/article.css', array() );
	}
}

/** Whether a the_content call renders the main text of the single page being viewed. */
function tbe_toc_is_main(): bool {
	return is_singular() && is_main_query() && ! is_admin() && ! is_feed() && ! doing_filter( 'get_the_excerpt' )
		&& (int) get_the_ID() === (int) get_queried_object_id() && ! post_password_required();
}

/** Whether a post has a [wp_toc] or [auto_toc] of its own. */
function tbe_toc_has_shortcode( WP_Post $post ): bool {
	return ! tbe_toc_plugin_active() && ( has_shortcode( $post->post_content, 'wp_toc' ) || has_shortcode( $post->post_content, 'auto_toc' ) );
}

// The mark [wp_toc] leaves. wpautop runs before shortcodes, so a shortcode on its own line arrives wrapped in a
// paragraph: the wrapper goes with the mark, so the contents box never sits inside a <p>.
const TBE_TOC_PLACEHOLDER = '#(?:<p>\s*)?<!--tbe-toc:([0-9a-f]*)-->(?:\s*(?:<br\s*/?>\s*)?</p>)?#';

add_action(
	'init',
	static function () {
		if ( tbe_toc_plugin_active() ) {
			// The plugin handles posts and pages and its shortcodes; commentary entries keep the theme's contents.
			add_filter( 'wp_toc_enabled', static fn( $on, $post_id = 0 ) => 'tbe_commentary' === get_post_type( (int) $post_id ) ? false : $on, 10, 2 );
			return;
		}
		// The shortcode leaves a mark; the contents are built once the headings have their anchors (the_content, 13).
		$shortcode = static fn( $atts ) => '<!--tbe-toc:' . bin2hex( (string) wp_json_encode( is_array( $atts ) ? $atts : array() ) ) . '-->';
		add_shortcode( 'wp_toc', $shortcode );
		add_shortcode( 'auto_toc', $shortcode );
	}
);

add_filter(
	'the_content',
	static function ( $content ) {
		$content = (string) $content;
		$marked  = (bool) preg_match( TBE_TOC_PLACEHOLDER, $content );
		if ( tbe_toc_plugin_active() || ! tbe_toc_is_main() || ! tbe_setting( 'toc' ) ) {
			return $marked ? (string) preg_replace( TBE_TOC_PLACEHOLDER, '', $content ) : $content;
		}
		$post    = get_post();
		$content = tbe_toc_apply_ids( $content );
		if ( $marked ) {
			tbe_toc_enqueue();
			return (string) preg_replace_callback(
				TBE_TOC_PLACEHOLDER,
				static function ( $m ) use ( $content ) {
					// The mark is hex: an odd length or stray characters (a tampered mark) fall back to the defaults.
					$hex   = (string) $m[1];
					$raw   = ( '' !== $hex && 0 === strlen( $hex ) % 2 && ctype_xdigit( $hex ) ) ? json_decode( (string) hex2bin( $hex ), true ) : null;
					$atts  = tbe_toc_atts( is_array( $raw ) ? $raw : array() );
					$items = tbe_toc_items( $content, $atts['levels'] );
					if ( count( $items ) < $atts['min'] ) {
						return '';
					}
					return tbe_toc_box( tbe_toc_tree( $items, $atts['depth'] ), $atts['title'], $atts['collapsible'] ? 'collapsible' : 'inline', $atts['collapsed'] );
				},
				$content
			);
		}
		if ( ! $post || 'tbe_commentary' === $post->post_type || ! in_array( $post->post_type, tbe_toc_post_types(), true ) ) {
			return $content;
		}
		$items = tbe_toc_items( $content );
		if ( count( $items ) < 3 ) {
			return $content;
		}
		// Before the first heading that is in the contents.
		$pos = preg_match( '#<h' . $items[0]['level'] . '\s[^>]*id\s*=\s*(["\'])' . preg_quote( $items[0]['id'], '#' ) . '\1#', $content, $m, PREG_OFFSET_CAPTURE ) ? $m[0][1] : 0;
		tbe_toc_enqueue();
		return substr( $content, 0, $pos ) . tbe_toc_box( tbe_toc_tree( $items ), __( 'Contents', 'tbe-child' ) ) . substr( $content, $pos );
	},
	13
);

/** The entries of a tree, in order, as they show in the box. */
function tbe_toc_flatten( array $nodes ): array {
	$out = array();
	foreach ( $nodes as $n ) {
		$out[] = array( $n['id'], $n['text'] );
		$out   = array_merge( $out, tbe_toc_flatten( $n['children'] ) );
	}
	return $out;
}

/**
 * The sections of the page being viewed, for the structured data: array( id, text ) in order, exactly the entries
 * its contents box shows. A post with [wp_toc] or [auto_toc] is read with that shortcode's tags, min and depth (the
 * first one, when there are several); any other post with the defaults, from three entries up.
 *
 * @return array<int,array{0:string,1:string}>
 */
function tbe_toc_sections( WP_Post $post ): array {
	if ( ! tbe_setting( 'toc' ) || post_password_required( $post ) ) {
		return array();
	}
	$content = (string) $post->post_content;
	if ( tbe_toc_has_shortcode( $post ) && preg_match( '/\[(?:wp_toc|auto_toc)(\s[^\]]*)?\]/', $content, $sc ) ) {
		$raw   = function_exists( 'shortcode_parse_atts' ) ? shortcode_parse_atts( trim( (string) ( $sc[1] ?? '' ) ) ) : array();
		$atts  = tbe_toc_atts( is_array( $raw ) ? $raw : array() );
		$items = tbe_toc_items( $content, $atts['levels'] );
		return count( $items ) < $atts['min'] ? array() : tbe_toc_flatten( tbe_toc_tree( $items, $atts['depth'] ) );
	}
	$shows = 'tbe_commentary' === $post->post_type || ( ! tbe_toc_plugin_active() && in_array( $post->post_type, tbe_toc_post_types(), true ) );
	$items = tbe_toc_items( $content );
	if ( ! $shows || count( $items ) < 3 ) {
		return array();
	}
	return tbe_toc_flatten( tbe_toc_tree( $items ) );
}
