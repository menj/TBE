/**
 * Quick Edit: tick "Unlist this post" when the row's Unlisted column says so.
 * Loaded only on the post list screens (inc/unlist-admin.php).
 */
( function ( $ ) {
	'use strict';
	if ( typeof window.inlineEditPost === 'undefined' ) {
		return;
	}
	var edit = window.inlineEditPost.edit;
	window.inlineEditPost.edit = function ( id ) {
		edit.apply( this, arguments );
		var postId = parseInt( typeof id === 'object' ? this.getId( id ) : id, 10 );
		if ( ! postId ) {
			return;
		}
		var on = String( $( '#post-' + postId ).find( '.tbe-unlisted' ).data( 'unlisted' ) ) === '1';
		$( '#edit-' + postId ).find( 'input[name="tbe_unlist_quick"]' ).prop( 'checked', on );
	};
} )( jQuery );
