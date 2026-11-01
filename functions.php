<?php
/**
 * The Bible Exposed — child theme of Twenty Twenty-Five.
 *
 * @package tbe-child
 */

defined( 'ABSPATH' ) || exit;

define( 'TBE_VERSION', '0.3.0' );
define( 'TBE_DIR', get_stylesheet_directory() );
define( 'TBE_URI', get_stylesheet_directory_uri() );

foreach ( array( 'settings', 'bible', 'interlinear', 'audio', 'esv-api', 'quran', 'commentary', 'entry-fields', 'glossary', 'enrich', 'search', 'schema', 'routes', 'reader', 'blocks', 'enqueue' ) as $tbe_file ) {
	require_once TBE_DIR . '/inc/' . $tbe_file . '.php';
}
unset( $tbe_file );

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/base.css', 'assets/css/scripture.css' ) );
	}
);

/** Flush rewrite rules once on activation and whenever the theme version changes. */
add_action(
	'init',
	static function () {
		if ( get_option( 'tbe_rewrite_version' ) !== TBE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'tbe_rewrite_version', TBE_VERSION, false );
		}
	},
	99
);
