/**
 * Interlinear display options: show or hide each layer, and strip Hebrew vowel points or
 * cantillation marks. Choices are remembered in this browser. Without JS the page still reads fine.
 */
( function () {
	'use strict';

	var KEY = 'tbe-il';
	// Cantillation (accents) and vowel points / marks, as Unicode ranges.
	var CANTILLATION = /[֑-֯]/g;
	var VOWELS = /[ְ-ׇֽֿׁׂ]/g;
	var defaults = { gloss: true, translit: true, strongs: true, lemma: false, morph: false, variants: true, vowels: true, cantillation: true };

	function load() {
		try {
			return Object.assign( {}, defaults, JSON.parse( window.localStorage.getItem( KEY ) || '{}' ) );
		} catch ( e ) {
			return Object.assign( {}, defaults );
		}
	}

	function save( state ) {
		try {
			window.localStorage.setItem( KEY, JSON.stringify( state ) );
		} catch ( e ) { /* storage unavailable */ }
	}

	function apply( root, state ) {
		[ 'gloss', 'translit', 'strongs', 'lemma', 'morph' ].forEach( function ( layer ) {
			root.classList.toggle( 'tbe-il--hide-' + layer, ! state[ layer ] );
		} );
		root.classList.toggle( 'tbe-il--no-variants', ! state.variants );

		root.querySelectorAll( '.tbe-il__he[data-full]' ).forEach( function ( el ) {
			var text = el.getAttribute( 'data-full' );
			if ( ! state.cantillation ) {
				text = text.replace( CANTILLATION, '' );
			}
			if ( ! state.vowels ) {
				text = text.replace( VOWELS, '' ).replace( CANTILLATION, '' );
			}
			el.textContent = text;
		} );

		document.querySelectorAll( '[data-tbe-il]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', state[ button.getAttribute( 'data-tbe-il' ) ] ? 'true' : 'false' );
		} );
		document.querySelectorAll( '[data-tbe-il-script]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', state[ button.getAttribute( 'data-tbe-il-script' ) ] ? 'true' : 'false' );
		} );
	}

	function init() {
		var root = document.getElementById( 'tbe-il' );
		if ( ! root ) {
			return;
		}
		var state = load();
		apply( root, state );

		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-tbe-il], [data-tbe-il-script]' );
			if ( ! button ) {
				return;
			}
			var key = button.getAttribute( 'data-tbe-il' ) || button.getAttribute( 'data-tbe-il-script' );
			state[ key ] = ! state[ key ];
			// Removing vowels also hides cantillation, so switching vowels back on restores both.
			if ( 'vowels' === key && state.vowels ) {
				state.cantillation = true;
			}
			save( state );
			apply( root, state );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
