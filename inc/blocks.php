<?php
/**
 * Small server-rendered blocks used by the templates.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_block_type( 'tbe/bible', array( 'render_callback' => 'tbe_render_reader' ) );

		register_block_type(
			'tbe/passage-search',
			array(
				'render_callback' => static function (): string {
					return '<form class="tbe-search" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">'
						. '<label class="screen-reader-text" for="tbe-ref">' . esc_html__( 'Go to a passage or search', 'tbe-child' ) . '</label>'
						. '<input id="tbe-ref" type="search" name="tbe_ref" placeholder="' . esc_attr__( 'Go to a passage or search — e.g. John 1:1-18, mercy', 'tbe-child' ) . '" autocomplete="off">'
						. '<button type="submit" class="tbe-btn tbe-btn--solid">' . esc_html__( 'Go', 'tbe-child' ) . '</button>'
						. '</form>';
				},
			)
		);

		register_block_type(
			'tbe/theme-toggle',
			array(
				'render_callback' => static function (): string {
					if ( ! tbe_setting( 'allow_toggle' ) ) {
						return '';
					}
					return '<button type="button" class="tbe-btn tbe-toggle" data-tbe-theme-toggle aria-label="' . esc_attr__( 'Switch between light and dark', 'tbe-child' ) . '"><span aria-hidden="true">◐</span></button>';
				},
			)
		);

		register_block_type(
			'tbe/tagline',
			array(
				'render_callback' => static function (): string {
					return '<p class="tbe-tagline">' . esc_html( (string) tbe_setting( 'tagline' ) ) . '</p>';
				},
			)
		);

		register_block_type(
			'tbe/brandmark',
			array(
				'attributes'      => array( 'variant' => array( 'type' => 'string', 'default' => 'mark' ) ),
				'render_callback' => static function ( array $attrs ): string {
					if ( 'hero' === ( $attrs['variant'] ?? '' ) ) {
						// The hand-worn mark, coloured by the active scheme through a CSS mask.
						return '<span class="tbe-brandmark tbe-brandmark--hero" aria-hidden="true"></span>';
					}
					return '<span class="tbe-brandmark" aria-hidden="true"><svg viewBox="0 -52 340 717" focusable="false" fill="none" stroke="currentColor" stroke-width="26" stroke-linejoin="round"><path d="M135.75 210L144 216L159 202L174 217L190 204L202.25 209.5V460.75H331.25V526.25H202.25V656.25H135.75V526.25H7.75V460.75H135.75Z"/><path d="M135.75 7.75H202.25V209.5L190 204L174 217L159 202L144 216L135.75 210Z" transform="translate(-24 -40) rotate(-8 169 218)"/></svg></span>';
				},
			)
		);

		// "© 2026 The Bible Exposed", running on to "© 2026–2027" and so on as the years pass.
		register_block_type(
			'tbe/rights',
			array(
				'render_callback' => static function (): string {
					$start = 2026;
					$year  = (int) wp_date( 'Y' );
					$span  = $year > $start ? $start . '–' . $year : (string) $start;
					return '<p class="tbe-footer__rights">© ' . esc_html( $span ) . ' ' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
				},
			)
		);

		register_block_type( 'tbe/glossary', array( 'render_callback' => 'tbe_render_glossary' ) );

		register_block_type(
			'tbe/copyright',
			array(
				'render_callback' => 'tbe_copyright_html',
			)
		);
	}
);
