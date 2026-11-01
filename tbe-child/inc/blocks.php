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
						. '<label class="screen-reader-text" for="tbe-ref">' . esc_html__( 'Go to passage', 'tbe-child' ) . '</label>'
						. '<input id="tbe-ref" type="search" name="tbe_ref" placeholder="' . esc_attr__( 'Go to a passage — e.g. John 1:1-18', 'tbe-child' ) . '" autocomplete="off">'
						. '<button type="submit" class="tbe-btn tbe-btn--solid">' . esc_html__( 'Go', 'tbe-child' ) . '</button>'
						. '</form>';
				},
			)
		);

		register_block_type(
			'tbe/bismillah',
			array(
				'render_callback' => static function (): string {
					if ( ! tbe_setting( 'bismillah' ) ) {
						return '';
					}
					return '<div class="tbe-bismillah"><p lang="ar" dir="rtl">' . esc_html( (string) tbe_setting( 'bismillah_text' ) ) . '</p></div>';
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
			'tbe/copyright',
			array(
				'render_callback' => 'tbe_copyright_html',
			)
		);
	}
);
