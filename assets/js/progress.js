/**
 * Reading progress bar (inc/progress.php): sets --tbe-read (0 to 1) on the bar from the scroll
 * position. A passive scroll listener asks for one animation frame at a time, so the work is
 * at most once per frame. The bar is decorative (aria-hidden), and reduced motion only drops
 * the CSS transition.
 */
( function () {
	'use strict';
	var bar = document.querySelector( '.tbe-progress' );
	if ( ! bar ) {
		return;
	}
	var root = document.documentElement;
	var queued = false;

	function update() {
		queued = false;
		var max = root.scrollHeight - window.innerHeight;
		var read = max > 0 ? Math.min( 1, Math.max( 0, ( window.scrollY || root.scrollTop ) / max ) ) : 1;
		bar.style.setProperty( '--tbe-read', read.toFixed( 4 ) );
	}

	function queue() {
		if ( ! queued ) {
			queued = true;
			window.requestAnimationFrame( update );
		}
	}

	bar.hidden = false;
	update();
	window.addEventListener( 'scroll', queue, { passive: true } );
	window.addEventListener( 'resize', queue, { passive: true } );
	window.addEventListener( 'load', queue );
} )();
