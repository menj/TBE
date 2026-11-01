/**
 * Commentary editor: add and remove rows in the variants and Qur'an repeaters.
 */
( function () {
	'use strict';

	var counters = {};

	function init() {
		document.addEventListener( 'click', function ( event ) {
			var add = event.target.closest( '[data-add]' );
			if ( add ) {
				var key = add.getAttribute( 'data-add' );
				var list = document.querySelector( '.tbe-repeater[data-repeater="' + key + '"]' );
				var template = document.getElementById( 'tbe-' + key + '-template' );
				if ( ! list || ! template ) {
					return;
				}
				counters[ key ] = ( counters[ key ] || list.children.length ) + 1;
				var html = template.innerHTML.replace( /__i__/g, 'n' + counters[ key ] + '_' + Date.now() );
				list.insertAdjacentHTML( 'beforeend', html );
				var first = list.lastElementChild && list.lastElementChild.querySelector( 'input' );
				if ( first ) {
					first.focus();
				}
				return;
			}
			var remove = event.target.closest( '.tbe-remove' );
			if ( remove ) {
				var row = remove.closest( '.tbe-row' );
				if ( row ) {
					row.remove();
				}
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
