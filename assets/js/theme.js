/**
 * Light / dark preference. Loaded in <head> so the saved choice applies before first paint.
 */
( function () {
	'use strict';

	var KEY = 'tbe-theme';
	var root = document.documentElement;

	function read() {
		try {
			return window.localStorage.getItem( KEY );
		} catch ( e ) {
			return null;
		}
	}

	function write( value ) {
		try {
			window.localStorage.setItem( KEY, value );
		} catch ( e ) {
			/* Storage unavailable (private mode): the choice simply lasts for this page view. */
		}
	}

	/* The site is light by default (0.10.15). A dark choice saved earlier is not applied. */
	var saved = read();
	if ( 'light' === saved ) {
		root.setAttribute( 'data-theme', saved );
	}

	document.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '[data-tbe-theme-toggle]' );
		if ( ! toggle ) {
			return;
		}
		var current = root.getAttribute( 'data-theme' ) ||
			( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light' );
		var next = 'dark' === current ? 'light' : 'dark';
		root.setAttribute( 'data-theme', next );
		write( next );
	} );
}() );
