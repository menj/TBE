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
	var defaults = { gloss: true, translit: true, strongs: true, lemma: false, morph: false, variants: true, vowels: true, cantillation: true, review: false };

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
		root.classList.toggle( 'tbe-il--review', !! state.review );

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


	/* Recorded reading: play a word, a verse, or the whole chapter, with the current word lit up. */
	function initAudio() {
		var audio = document.getElementById( 'tbe-il-audio' );
		if ( ! audio ) {
			return;
		}
		var words = [].slice.call( document.querySelectorAll( '.tbe-il__w[data-t0]' ) ).map( function ( el ) {
			return { el: el, t0: parseFloat( el.getAttribute( 'data-t0' ) ), t1: parseFloat( el.getAttribute( 'data-t1' ) ) };
		} );
		var stopAt = null;
		var lit = null;
		var frame = 0;
		var ours = false;

		function highlight( time ) {
			var now = null;
			for ( var i = 0; i < words.length; i++ ) {
				if ( time >= words[ i ].t0 && time < words[ i ].t1 ) {
					now = words[ i ].el;
					break;
				}
			}
			if ( now !== lit ) {
				if ( lit ) {
					lit.classList.remove( 'is-playing' );
				}
				if ( now ) {
					now.classList.add( 'is-playing' );
					var box = now.getBoundingClientRect();
					if ( box.top < 80 || box.bottom > window.innerHeight - 40 ) {
						now.scrollIntoView( { block: 'center', behavior: 'smooth' } );
					}
				}
				lit = now;
			}
		}

		function tick() {
			var time = audio.currentTime;
			if ( null !== stopAt && time >= stopAt ) {
				audio.pause();
				stopAt = null;
			}
			highlight( time );
			if ( ! audio.paused ) {
				frame = window.requestAnimationFrame( tick );
			}
		}

		function playRange( from, to ) {
			ours = true;
			stopAt = to;
			audio.currentTime = from;
			var started = audio.play();
			if ( started && started.catch ) {
				started.catch( function () { stopAt = null; } );
			}
		}

		audio.addEventListener( 'play', function () {
			if ( ! ours ) {
				stopAt = null; // the visitor used the player's own controls
			}
			ours = false;
			window.cancelAnimationFrame( frame );
			frame = window.requestAnimationFrame( tick );
		} );
		audio.addEventListener( 'seeked', function () { highlight( audio.currentTime ); } );
		audio.addEventListener( 'ended', function () { highlight( -1 ); } );

		document.addEventListener( 'click', function ( event ) {
			var verseButton = event.target.closest( '[data-tbe-play-verse]' );
			if ( verseButton ) {
				var units = document.querySelectorAll( '#v' + verseButton.getAttribute( 'data-tbe-play-verse' ) + ' .tbe-il__w[data-t0]' );
				if ( units.length ) {
					playRange( parseFloat( units[ 0 ].getAttribute( 'data-t0' ) ), parseFloat( units[ units.length - 1 ].getAttribute( 'data-t1' ) ) );
				}
				return;
			}
			var unit = event.target.closest( '.tbe-il__w[data-t0]' );
			if ( unit ) {
				playRange( parseFloat( unit.getAttribute( 'data-t0' ) ), parseFloat( unit.getAttribute( 'data-t1' ) ) );
			}
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( ( 'Enter' === event.key || ' ' === event.key ) && event.target.matches && event.target.matches( '.tbe-il__w[data-t0]' ) ) {
				event.preventDefault();
				playRange( parseFloat( event.target.getAttribute( 'data-t0' ) ), parseFloat( event.target.getAttribute( 'data-t1' ) ) );
			}
		} );
	}

	function init() {
		var root = document.getElementById( 'tbe-il' );
		if ( ! root ) {
			return;
		}
		var state = load();
		apply( root, state );
		initAudio();

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
