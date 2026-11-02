/**
 * Reader behaviour: verse highlighting from the URL hash, text size, verse numbers, layout,
 * opening and closing notes, and linking each note to the verses it covers.
 * Everything works without JS; this enhances it.
 */
( function () {
	'use strict';

	var KEYS = { size: 'tbe-size', numbers: 'tbe-numbers', layout: 'tbe-layout', view: 'tbe-view' };
	var root = document.documentElement;

	function store( key, value ) {
		try {
			window.localStorage.setItem( key, value );
		} catch ( e ) { /* storage unavailable */ }
	}

	function load( key ) {
		try {
			return window.localStorage.getItem( key );
		} catch ( e ) {
			return null;
		}
	}

	function clearTargets() {
		document.querySelectorAll( '.tbe-verse.is-target' ).forEach( function ( el ) {
			el.classList.remove( 'is-target' );
		} );
	}

	function markRange( start, end ) {
		clearTargets();
		for ( var v = start; v <= end; v++ ) {
			var el = document.getElementById( 'v' + v );
			if ( el ) {
				el.classList.add( 'is-target' );
			}
		}
	}

	/* #v16 or #v16-18 */
	function highlightFromHash() {
		var match = /^#v(\d+)(?:-(\d+))?$/.exec( window.location.hash );
		if ( ! match ) {
			return;
		}
		var start = parseInt( match[ 1 ], 10 );
		markRange( start, match[ 2 ] ? parseInt( match[ 2 ], 10 ) : start );
		var first = document.getElementById( 'v' + start );
		if ( first ) {
			first.scrollIntoView( { block: 'center' } );
		}
	}

	/* A note: open it, highlight the verses it covers */
	function activateNote( id, scroll ) {
		document.querySelectorAll( '.tbe-box.is-active' ).forEach( function ( el ) {
			el.classList.remove( 'is-active' );
		} );
		var note = id && document.getElementById( id );
		if ( ! note || ! note.classList.contains( 'tbe-box' ) ) {
			return;
		}
		note.open = true;
		note.classList.add( 'is-active' );
		var start = parseInt( note.getAttribute( 'data-vs' ), 10 );
		var end = parseInt( note.getAttribute( 'data-ve' ), 10 ) || start;
		if ( start ) {
			markRange( start, end );
		}
		if ( scroll ) {
			note.scrollIntoView( { block: 'center' } );
		}
	}

	function applySize( scale ) {
		scale = Math.max( 0.8, Math.min( 1.6, scale ) );
		root.style.setProperty( '--tbe-scale', String( scale ) );
		store( KEYS.size, String( scale ) );
	}

	function applyNumbers( button, show ) {
		var text = document.querySelector( '.tbe-text' );
		if ( text ) {
			text.classList.toggle( 'tbe-hide-numbers', ! show );
		}
		if ( button ) {
			button.setAttribute( 'aria-pressed', show ? 'true' : 'false' );
		}
		store( KEYS.numbers, show ? '1' : '0' );
	}

	function applyLayout( button, flow ) {
		var chapter = document.querySelector( '.tbe-chapter' );
		if ( chapter ) {
			chapter.classList.toggle( 'tbe-layout-flow', flow );
			chapter.classList.toggle( 'tbe-layout-verse', ! flow );
		}
		if ( button ) {
			button.setAttribute( 'aria-pressed', flow ? 'true' : 'false' );
		}
		store( KEYS.layout, flow ? 'flow' : 'verse' );
	}

	function init() {
		var savedSize = parseFloat( load( KEYS.size ) );
		if ( savedSize ) {
			applySize( savedSize );
		}
		var numbersButton = document.querySelector( '[data-tbe-numbers]' );
		if ( numbersButton && '0' === load( KEYS.numbers ) ) {
			applyNumbers( numbersButton, false );
		}
		// Books page: dim the books a tradition does not count as scripture.
		document.querySelectorAll( '[data-tbe-canon]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var tradition = button.getAttribute( 'data-tbe-canon' );
				document.querySelectorAll( '[data-tbe-canon]' ).forEach( function ( b ) {
					b.setAttribute( 'aria-pressed', b === button ? 'true' : 'false' );
				} );
				document.querySelectorAll( 'li[data-canon], section[data-canon]' ).forEach( function ( item ) {
					var counted = 'all' === tradition || ( ' ' + item.getAttribute( 'data-canon' ) + ' ' ).indexOf( ' ' + tradition + ' ' ) > -1;
					item.classList.toggle( 'is-off', ! counted );
				} );
			} );
		} );

		// Parallel view: Christian (ESV), Jewish (JPS 1917) or both; the choice is remembered.
		var viewButtons = document.querySelectorAll( '[data-tbe-view-set]' );
		function setView( view ) {
			var text = document.querySelector( '.tbe-text[data-tbe-view]' );
			if ( ! text ) {
				return;
			}
			text.setAttribute( 'data-tbe-view', view );
			viewButtons.forEach( function ( b ) {
				b.setAttribute( 'aria-pressed', b.getAttribute( 'data-tbe-view-set' ) === view ? 'true' : 'false' );
			} );
			store( KEYS.view, view );
		}
		if ( viewButtons.length ) {
			var savedView = load( KEYS.view );
			if ( 'jps' === savedView || 'both' === savedView ) {
				setView( savedView );
			}
			viewButtons.forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					setView( b.getAttribute( 'data-tbe-view-set' ) );
				} );
			} );
		}
		// With a margin to put them in, notes start as slim tabs beside their verses; click one to read it.
		if ( window.matchMedia && window.matchMedia( '(min-width: 1340px)' ).matches ) {
			var target = window.location.hash;
			document.querySelectorAll( '.tbe-box:not(.tbe-box--intro)' ).forEach( function ( box ) {
				if ( '#' + box.id !== target ) {
					box.open = false;
				}
			} );
		}
		var layoutButton = document.querySelector( '[data-tbe-layout]' );
		if ( layoutButton && load( KEYS.layout ) ) {
			applyLayout( layoutButton, 'flow' === load( KEYS.layout ) );
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
			var layout = event.target.closest( '[data-tbe-layout]' );
			if ( layout ) {
				applyLayout( layout, 'true' !== layout.getAttribute( 'aria-pressed' ) );
				return;
			}
			var toggle = event.target.closest( '[data-tbe-toggle-notes]' );
			if ( toggle ) {
				var open = 'open' === toggle.getAttribute( 'data-tbe-toggle-notes' );
				document.querySelectorAll( '.tbe-box:not(.tbe-box--intro)' ).forEach( function ( box ) {
					box.open = open;
				} );
				return;
			}
			var mark = event.target.closest( '.tbe-mark' );
			if ( mark ) {
				activateNote( mark.getAttribute( 'href' ).slice( 1 ), false );
				return;
			}
			var summary = event.target.closest( '.tbe-box > summary' );
			if ( summary && ! summary.parentNode.classList.contains( 'tbe-box--intro' ) ) {
				// Opening a box also lights up the verses it covers.
				window.setTimeout( function () {
					if ( summary.parentNode.open ) {
						activateNote( summary.parentNode.id, false );
					}
				}, 0 );
			}
		} );

		window.addEventListener( 'hashchange', function () {
			var hash = window.location.hash.slice( 1 );
			if ( 0 === hash.indexOf( 'note-' ) ) {
				activateNote( hash, true );
			} else {
				highlightFromHash();
			}
		} );

		if ( 0 === window.location.hash.indexOf( '#note-' ) ) {
			activateNote( window.location.hash.slice( 1 ), true );
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
