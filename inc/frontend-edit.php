<?php
/**
 * Front-end editing (0.10.15): a signed-in editor opens WordPress's own block editor in a drawer on a commentary
 * note, or on a Bible chapter to add a note for it, and saves through the core REST API. Nothing outside
 * WordPress is loaded: the editor is built from core's wp-block-editor, wp-components, wp-block-library and
 * wp-api-fetch packages, and only for people who may edit.
 *
 * @package TBE
 */

defined( 'ABSPATH' ) || exit;

/** What the editor on this page is for: edit this note, add a note for this chapter, or nothing. */
function tbe_frontend_edit_context(): ?array {
	if ( ! is_user_logged_in() || is_admin() ) {
		return null;
	}
	if ( is_singular( 'tbe_commentary' ) ) {
		$id = (int) get_queried_object_id();
		if ( $id && current_user_can( 'edit_post', $id ) ) {
			return array( 'mode' => 'edit', 'id' => $id, 'canPublish' => current_user_can( 'publish_post', $id ) );
		}
		return null;
	}
	$request = function_exists( 'tbe_request' ) ? tbe_request() : null;
	$type    = get_post_type_object( 'tbe_commentary' );
	if ( $request && 'chapter' === $request['view'] && $type && current_user_can( $type->cap->create_posts ) ) {
		return array(
			'mode'       => 'new',
			'id'         => 0,
			'canPublish' => current_user_can( $type->cap->publish_posts ),
			'passage'    => TBE_Bible::label( $request['book']['slug'], $request['chapter'] ),
		);
	}
	return null;
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		$context = tbe_frontend_edit_context();
		if ( ! $context ) {
			return;
		}
		$core = array( 'wp-element', 'wp-components', 'wp-block-editor', 'wp-blocks', 'wp-block-library', 'wp-format-library', 'wp-data', 'wp-api-fetch', 'wp-i18n' );
		foreach ( array( 'wp-components', 'wp-block-editor', 'wp-block-library', 'wp-format-library' ) as $style ) {
			wp_enqueue_style( $style );
		}
		tbe_enqueue_theme_style( 'tbe-frontend-edit', 'assets/css/frontend-edit.css', array( 'tbe-base', 'wp-components', 'wp-block-editor' ) );
		tbe_enqueue_theme_script( 'tbe-frontend-edit', 'assets/js/frontend-edit.js', $core, array( 'in_footer' => true ) );
		wp_add_inline_script(
			'tbe-frontend-edit',
			'window.tbeEdit = ' . wp_json_encode(
				array(
					'context' => $context,
					'i18n'    => array(
						'edit'      => __( 'Edit this note', 'tbe-child' ),
						'add'       => __( 'Add a note here', 'tbe-child' ),
						'title'     => __( 'Title', 'tbe-child' ),
						'passage'   => __( 'Passage', 'tbe-child' ),
						'hint'      => __( 'For example John 1:1-18 or Isaiah 21:13', 'tbe-child' ),
						'save'      => __( 'Save', 'tbe-child' ),
						'saving'    => __( 'Saving…', 'tbe-child' ),
						'saved'     => __( 'Saved.', 'tbe-child' ),
						'publish'   => __( 'Publish', 'tbe-child' ),
						'published' => __( 'Published. Visitors can read it now.', 'tbe-child' ),
						'close'     => __( 'Close', 'tbe-child' ),
						'loading'   => __( 'Loading…', 'tbe-child' ),
						'failed'    => __( 'Could not save. Check the passage and try again.', 'tbe-child' ),
						'draft'     => __( 'It is a draft: visitors cannot see it until it is published.', 'tbe-child' ),
						'unsaved'   => __( 'Close without saving?', 'tbe-child' ),
					),
				)
			) . ';',
			'before'
		);
	}
);

/** The editor's mount point; the script adds its button. */
add_action(
	'wp_footer',
	static function () {
		if ( tbe_frontend_edit_context() ) {
			echo '<div id="tbe-edit-root"></div>';
		}
	}
);

/** A passage the drawer sends that is not a real reference is refused, so a typo never silently clears it. */
add_filter(
	'rest_pre_insert_tbe_commentary',
	static function ( $prepared, $request ) {
		if ( $request instanceof WP_REST_Request && $request->has_param( 'tbe_passage_text' ) ) {
			$text = trim( sanitize_text_field( (string) $request->get_param( 'tbe_passage_text' ) ) );
			if ( '' !== $text && ! TBE_Bible::parse_reference( $text ) ) {
				return new WP_Error( 'tbe_bad_passage', __( 'That passage was not recognised.', 'tbe-child' ), array( 'status' => 400 ) );
			}
		}
		return $prepared;
	},
	10,
	2
);

/** The passage typed in the drawer goes through the same check as the meta box. */
add_action(
	'rest_after_insert_tbe_commentary',
	static function ( $post, $request ) {
		if ( $post instanceof WP_Post && $request instanceof WP_REST_Request && $request->has_param( 'tbe_passage_text' ) && current_user_can( 'edit_post', $post->ID ) ) {
			tbe_store_passage( (int) $post->ID, sanitize_text_field( (string) $request->get_param( 'tbe_passage_text' ) ) );
		}
	},
	10,
	2
);
