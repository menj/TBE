/**
 * Recorded Hebrew reading: play, pause and stop, a seek bar and the time.
 * Works on any <audio data-tbe-audio>. Without JavaScript the browser's own player stays.
 */
( function () {
	'use strict';

	function clock( seconds ) {
		if ( ! isFinite( seconds ) || seconds < 0 ) {
			seconds = 0;
		}
		var m = Math.floor( seconds / 60 );
		var s = Math.floor( seconds % 60 );
		return m + ':' + ( s < 10 ? '0' : '' ) + s;
	}

	function build( audio ) {
		var bar = document.createElement( 'div' );
		bar.className = 'tbe-player';
		bar.setAttribute( 'role', 'group' );
		bar.setAttribute( 'aria-label', 'Audio controls' );
		bar.innerHTML =
			'<button type="button" class="tbe-btn tbe-player__play" aria-label="Play"><span aria-hidden="true">▶</span> <span class="tbe-player__label">Play</span></button>' +
			'<button type="button" class="tbe-btn tbe-player__stop" aria-label="Stop"><span aria-hidden="true">■</span> <span class="tbe-player__label">Stop</span></button>' +
			'<input type="range" class="tbe-player__seek" min="0" max="100" step="0.1" value="0" aria-label="Position">' +
			'<span class="tbe-player__time" aria-live="off">0:00 / 0:00</span>';
		audio.removeAttribute( 'controls' );
		audio.preload = 'metadata';
		audio.parentNode.insertBefore( bar, audio.nextSibling );

		var play = bar.querySelector( '.tbe-player__play' );
		var stop = bar.querySelector( '.tbe-player__stop' );
		var seek = bar.querySelector( '.tbe-player__seek' );
		var time = bar.querySelector( '.tbe-player__time' );
		var scrubbing = false;

		function paint() {
			var playing = ! audio.paused && ! audio.ended;
			var word = playing ? 'Pause' : 'Play';
			play.setAttribute( 'aria-label', word );
			play.querySelector( '.tbe-player__label' ).textContent = word;
			play.querySelector( '[aria-hidden]' ).textContent = playing ? '❚❚' : '▶';
			bar.classList.toggle( 'is-playing', playing );
			if ( isFinite( audio.duration ) && audio.duration > 0 ) {
				seek.max = audio.duration;
			}
			if ( ! scrubbing ) {
				seek.value = audio.currentTime;
			}
			time.textContent = clock( audio.currentTime ) + ' / ' + clock( audio.duration );
		}

		play.addEventListener( 'click', function () {
			if ( audio.paused || audio.ended ) {
				var started = audio.play();
				if ( started && started.catch ) {
					started.catch( function () {} );
				}
			} else {
				audio.pause();
			}
		} );

		stop.addEventListener( 'click', function () {
			audio.pause();
			audio.currentTime = 0;
			audio.dispatchEvent( new CustomEvent( 'tbe:audio-stop' ) );
			paint();
		} );

		seek.addEventListener( 'input', function () {
			scrubbing = true;
			time.textContent = clock( parseFloat( seek.value ) ) + ' / ' + clock( audio.duration );
		} );
		seek.addEventListener( 'change', function () {
			audio.currentTime = parseFloat( seek.value );
			scrubbing = false;
			paint();
		} );

		[ 'play', 'pause', 'ended', 'timeupdate', 'loadedmetadata', 'durationchange', 'seeked' ].forEach( function ( name ) {
			audio.addEventListener( name, paint );
		} );
		audio.addEventListener( 'play', function () {
			document.querySelectorAll( 'audio[data-tbe-audio]' ).forEach( function ( other ) {
				if ( other !== audio && ! other.paused ) {
					other.pause();
				}
			} );
		} );
		paint();
	}

	function init() {
		document.querySelectorAll( 'audio[data-tbe-audio]' ).forEach( build );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
