<?php
/**
 * The links the block templates and parts carry as root paths (href="/bible/" and the like) are written out
 * from the site's home address when a block renders, so they stay inside the site when WordPress is
 * installed in a subfolder. A block template cannot call home_url(); this filter does it for them.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** The root paths the theme's templates link to: the first segment of each. */
function tbe_site_link_sections(): array {
	return array( 'bible', 'commentary', 'glossary', 'credits' );
}

/**
 * Rewrite href="/<section>/…" in a block's HTML to the full address under home_url(). A full address never
 * starts with "/", so a block nested in another (a button in a group in a template part) is rewritten once.
 * A link with "//" (another host) or into any other path is left alone.
 */
function tbe_site_links( string $html ): string {
	if ( false === strpos( $html, 'href="/' ) ) {
		return $html;
	}
	$sections = implode( '|', array_map( 'preg_quote', tbe_site_link_sections() ) );
	return (string) preg_replace_callback(
		'~href="/((?:' . $sections . ')(?:/[^"]*)?)"~',
		static function ( array $m ): string {
			return 'href="' . esc_url( home_url( '/' . $m[1] ) ) . '"';
		},
		$html
	);
}

add_filter(
	'render_block',
	static function ( $html, array $block ) {
		return in_array( $block['blockName'] ?? '', array( 'core/html', 'core/button', 'core/paragraph', 'core/navigation-link' ), true ) ? tbe_site_links( (string) $html ) : $html;
	},
	10,
	2
);
