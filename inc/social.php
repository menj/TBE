<?php
/**
 * Profile links (0.8.0): the site's own profiles as a row of icons from the Minimalist Social
 * Icons Pack (GPL), inlined as SVG with currentColor so they follow the colour scheme. The
 * addresses are set under Appearance → TBE → Profile links. The row is the tbe/social block,
 * which the footer carries, and the [tbe_social] shortcode. A network without an address is
 * skipped; with none at all nothing is printed.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

/** The networks a profile can be on: key => name. Each has assets/img/social/{key}.svg. */
function tbe_social_networks(): array {
	return (array) apply_filters(
		'tbe_social_networks',
		array(
			'x'         => 'X',
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'youtube'   => 'YouTube',
			'tiktok'    => 'TikTok',
			'telegram'  => 'Telegram',
			'whatsapp'  => 'WhatsApp',
			'bluesky'   => 'Bluesky',
			'threads'   => 'Threads',
			'mastodon'  => 'Mastodon',
			'github'    => 'GitHub',
			'substack'  => 'Substack',
		)
	);
}

/** One icon, inline and decorative. */
function tbe_social_svg( string $key ): string {
	static $cache = array();
	if ( ! isset( $cache[ $key ] ) ) {
		$file = TBE_DIR . '/assets/img/social/' . sanitize_key( $key ) . '.svg';
		$svg  = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a bundled file.
		$svg  = (string) preg_replace( array( '#<title>.*?</title>#s', '#\s(?:role|fill|width|height)="[^"]*"#' ), '', $svg );
		$cache[ $key ] = (string) preg_replace( '#^<svg\b#', '<svg class="tbe-social__icon" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false"', trim( $svg ) );
	}
	return $cache[ $key ];
}

/** The row's markup, or '' when no address is set. */
function tbe_social_markup( ?array $profiles = null ): string {
	$profiles = $profiles ?? (array) tbe_setting( 'social_profiles' );
	$items    = '';
	foreach ( tbe_social_networks() as $key => $name ) {
		$url = isset( $profiles[ $key ] ) ? esc_url( (string) $profiles[ $key ] ) : '';
		$svg = '' !== $url ? tbe_social_svg( $key ) : '';
		if ( '' === $svg ) {
			continue;
		}
		$items .= sprintf(
			'<li><a class="tbe-social__link" href="%1$s" rel="me noopener" aria-label="%2$s">%3$s</a></li>',
			$url,
			esc_attr( $name ),
			$svg
		);
	}
	if ( '' === $items ) {
		return '';
	}
	wp_enqueue_style( 'tbe-social', TBE_URI . '/assets/css/social.css', array( 'tbe-base' ), TBE_VERSION );
	return '<nav class="tbe-social" aria-label="' . esc_attr__( 'Our profiles', 'tbe-child' ) . '"><ul class="tbe-social__list">' . $items . '</ul></nav>';
}

add_action(
	'init',
	static function () {
		register_block_type( 'tbe/social', array( 'render_callback' => static fn(): string => tbe_social_markup() ) );
		add_shortcode( 'tbe_social', static fn(): string => tbe_social_markup() );
	}
);

/* In the head when the footer will show the row, so the icons never flash unstyled. */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( array_filter( (array) tbe_setting( 'social_profiles' ) ) ) {
			wp_enqueue_style( 'tbe-social', TBE_URI . '/assets/css/social.css', array( 'tbe-base' ), TBE_VERSION );
		}
	}
);
