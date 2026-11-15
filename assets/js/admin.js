/**
 * Settings screen: show the custom-colour panel only when "Custom" is chosen,
 * and keep the Custom swatch preview in sync with the colour inputs.
 */
( function () {
	'use strict';

	/** Live value beside each range slider, and an "is-on" class on switches. */
	function enhance() {
		document.querySelectorAll( 'input[type="range"][data-tbe-range]' ).forEach( function ( range ) {
			var out = document.getElementById( range.getAttribute( 'data-tbe-range' ) );
			if ( ! out ) {
				return;
			}
			range.addEventListener( 'input', function () {
				out.textContent = range.value;
			} );
		} );
		document.querySelectorAll( '.tbe-toggle input' ).forEach( function ( box ) {
			var label = box.closest( '.tbe-toggle' );
			var sync = function () {
				label.classList.toggle( 'is-on', box.checked );
			};
			box.addEventListener( 'change', sync );
			sync();
		} );
	}

	function init() {
		enhance();
		var radios = document.querySelectorAll( '.tbe-schemes input[type="radio"]' );
		var custom = document.querySelector( '.tbe-custom' );
		if ( ! radios.length || ! custom ) {
			return;
		}

		function sync() {
			var chosen = document.querySelector( '.tbe-schemes input:checked' );
			custom.hidden = ! chosen || 'custom' !== chosen.value;
		}

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', sync );
		} );

		custom.addEventListener( 'input', function ( event ) {
			var input = event.target;
			if ( ! input.matches( 'input[type="color"]' ) ) {
				return;
			}
			var swatch = document.querySelector( '.tbe-scheme__swatches[data-scheme="custom"] i[data-role="' + input.getAttribute( 'data-role' ) + '"]' );
			if ( swatch ) {
				swatch.style.background = input.value;
			}
		} );

		sync();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
