/**
 * Reader behaviour: verse highlighting from the URL hash, text size, verse-number toggle,
 * and linking commentary to the verses it covers. Everything works without JS; this enhances it.
 */
( function () {
	'use strict';

	var SIZE_KEY = 'tbe-size';
	var NUMBERS_KEY = 'tbe-numbers';
	var root = document.documentElement;

	function store( key, value ) {
		try {
			window.localStorage.setItem( key, value );
		} catch ( e ) { /* ignore */ }
	}

	function load( key ) {
		try {
			return window.localStorage.getItem( key );
		} catch ( e ) {
			return null;
		}
	}

	/* Highlight #v16 or #v16-18 --------------------------------------------- */
	function clearTargets() {
		document.querySelectorAll( '.tbe-verse.is-target' ).forEach( function ( el ) {
			el.classList.remove( 'is-target' );
		} );
	}

	function highlightFromHash() {
		var match = /^#v(\d+)(?:-(\d+))?$/.exec( window.location.hash );
		clearTargets();
		if ( ! match ) {
			return;
		}
		var start = parseInt( match[ 1 ], 10 );
		var end = match[ 2 ] ? parseInt( match[ 2 ], 10 ) : start;
		for ( var v = start; v <= end; v++ ) {
			var el = document.getElementById( 'v' + v );
			if ( el ) {
				el.classList.add( 'is-target' );
			}
		}
		var first = document.getElementById( 'v' + start );
		if ( first ) {
			first.scrollIntoView( { block: 'center' } );
		}
	}

	/* Text size -------------------------------------------------------------- */
	function applySize( scale ) {
		scale = Math.max( 0.8, Math.min( 1.6, scale ) );
		root.style.setProperty( '--tbe-scale', String( scale ) );
		store( SIZE_KEY, String( scale ) );
	}

	/* Verse numbers ---------------------------------------------------------- */
	function applyNumbers( button, show ) {
		var text = document.querySelector( '.tbe-text' );
		if ( text ) {
			text.classList.toggle( 'tbe-hide-numbers', ! show );
		}
		if ( button ) {
			button.setAttribute( 'aria-pressed', show ? 'true' : 'false' );
		}
		store( NUMBERS_KEY, show ? '1' : '0' );
	}

	/* Commentary markers light up the verses they cover --------------------- */
	function markActiveNote( id ) {
		document.querySelectorAll( '.tbe-note.is-active' ).forEach( function ( el ) {
			el.classList.remove( 'is-active' );
		} );
		var note = id && document.getElementById( id );
		if ( ! note ) {
			return;
		}
		note.classList.add( 'is-active' );
		clearTargets();
		var start = parseInt( note.getAttribute( 'data-vs' ), 10 );
		var end = parseInt( note.getAttribute( 'data-ve' ), 10 ) || start;
		for ( var v = start; v <= end; v++ ) {
			var el = document.getElementById( 'v' + v );
			if ( el ) {
				el.classList.add( 'is-target' );
			}
		}
	}

	function init() {
		var savedSize = parseFloat( load( SIZE_KEY ) );
		if ( savedSize ) {
			applySize( savedSize );
		}

		var numbersButton = document.querySelector( '[data-tbe-numbers]' );
		if ( numbersButton && '0' === load( NUMBERS_KEY ) ) {
			applyNumbers( numbersButton, false );
		}

		document.addEventListener( 'click', function ( event ) {
			var sizeButton = event.target.closest( '[data-tbe-size]' );
			if ( sizeButton ) {
				var current = parseFloat( getComputedStyle( root ).getPropertyValue( '--tbe-scale' ) ) || 1;
				applySize( current + parseInt( sizeButton.getAttribute( 'data-tbe-size' ), 10 ) * 0.1 );
				return;
			}
			var numbers = event.target.closest( '[data-tbe-numbers]' );
			if ( numbers ) {
				applyNumbers( numbers, 'true' !== numbers.getAttribute( 'aria-pressed' ) );
				return;
			}
			var mark = event.target.closest( '.tbe-mark' );
			if ( mark ) {
				markActiveNote( mark.getAttribute( 'href' ).slice( 1 ) );
			}
		} );

		window.addEventListener( 'hashchange', function () {
			var hash = window.location.hash.slice( 1 );
			if ( 0 === hash.indexOf( 'note-' ) ) {
				markActiveNote( hash );
			} else {
				highlightFromHash();
			}
		} );

		if ( 0 === window.location.hash.indexOf( '#note-' ) ) {
			markActiveNote( window.location.hash.slice( 1 ) );
		} else {
			highlightFromHash();
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
