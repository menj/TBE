/**
 * Tooltips: one floating, accessible element shared by glossary terms, scripture reference cards,
 * section labels, verse markers and footnote markers. Works on hover, keyboard focus and touch.
 * Without JavaScript the links still work; only the preview is missing.
 */
( function () {
	'use strict';

	var tip;
	var current = null;
	var armedTouch = null;

	function build() {
		tip = document.createElement( 'div' );
		tip.className = 'tbe-tooltip';
		tip.id = 'tbe-tooltip';
		tip.setAttribute( 'role', 'tooltip' );
		tip.hidden = true;
		document.body.appendChild( tip );
	}

	/** Text for a trigger: its own data, or for a footnote marker the note it points to. */
	function describe( el ) {
		var footnote = el.closest( 'sup[data-fn]' );
		if ( footnote ) {
			var target = document.getElementById( el.getAttribute( 'href' ).replace( /^.*#/, '' ) );
			if ( ! target ) {
				return null;
			}
			var clone = target.cloneNode( true );
			clone.querySelectorAll( 'a[href$="-link"], a[aria-label]' ).forEach( function ( a ) {
				a.remove();
			} );
			return { title: '', text: clone.textContent.trim(), scripture: false };
		}
		var text = el.getAttribute( 'data-tbe-tip' );
		if ( ! text ) {
			return null;
		}
		return {
			title: el.getAttribute( 'data-tbe-tip-title' ) || '',
			text: text,
			scripture: '1' === el.getAttribute( 'data-tbe-tip-scripture' ),
		};
	}

	function place( el ) {
		var rect = el.getBoundingClientRect();
		var scrollX = window.pageXOffset;
		var scrollY = window.pageYOffset;
		tip.style.left = '0px';
		tip.style.top = '0px';
		var width = tip.offsetWidth;
		var height = tip.offsetHeight;
		var left = rect.left + scrollX + rect.width / 2 - width / 2;
		var maxLeft = scrollX + document.documentElement.clientWidth - width - 8;
		left = Math.max( scrollX + 8, Math.min( left, maxLeft ) );
		var top = rect.top + scrollY - height - 8;
		if ( rect.top < height + 16 ) {
			top = rect.bottom + scrollY + 8;
		}
		tip.style.left = Math.round( left ) + 'px';
		tip.style.top = Math.round( top ) + 'px';
	}

	function show( el ) {
		var info = describe( el );
		if ( ! info ) {
			return;
		}
		hide();
		tip.textContent = '';
		if ( info.title ) {
			var title = document.createElement( 'strong' );
			title.className = 'tbe-tooltip__title';
			title.textContent = info.title;
			tip.appendChild( title );
		}
		var body = document.createElement( 'span' );
		body.className = 'tbe-tooltip__text' + ( info.scripture ? ' tbe-tooltip__text--scripture' : '' );
		body.textContent = info.text;
		tip.appendChild( body );
		tip.hidden = false;
		place( el );
		el.setAttribute( 'aria-describedby', 'tbe-tooltip' );
		current = el;
	}

	function hide() {
		if ( current ) {
			current.removeAttribute( 'aria-describedby' );
			current = null;
		}
		if ( tip ) {
			tip.hidden = true;
		}
	}

	function trigger( target ) {
		return target && target.closest ? target.closest( '[data-tbe-tip], sup[data-fn] a' ) : null;
	}

	function init() {
		build();

		document.addEventListener( 'mouseover', function ( event ) {
			var el = trigger( event.target );
			if ( el && el !== current ) {
				show( el );
			}
		} );
		document.addEventListener( 'mouseout', function ( event ) {
			var el = trigger( event.target );
			if ( el && el === current && ! el.contains( event.relatedTarget ) ) {
				hide();
			}
		} );
		document.addEventListener( 'focusin', function ( event ) {
			var el = trigger( event.target );
			if ( el ) {
				show( el );
			}
		} );
		document.addEventListener( 'focusout', function ( event ) {
			if ( trigger( event.target ) ) {
				hide();
			}
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				hide();
			}
		} );

		// Touch: the first tap on a link shows the preview, the second follows the link.
		document.addEventListener( 'click', function ( event ) {
			var el = trigger( event.target );
			if ( ! el ) {
				armedTouch = null;
				hide();
				return;
			}
			if ( 'A' === el.tagName && window.matchMedia && window.matchMedia( '(hover: none)' ).matches ) {
				if ( armedTouch !== el ) {
					event.preventDefault();
					armedTouch = el;
					show( el );
				}
			}
		} );
		window.addEventListener( 'scroll', hide, { passive: true } );
		window.addEventListener( 'resize', hide );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
