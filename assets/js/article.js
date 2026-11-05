/**
 * The sticky contents bar on a commentary entry: a tap on a section closes the bar, so the section is not hidden
 * under it, and Escape closes it too.
 */
( function () {
	var bar = document.querySelector( '[data-tbe-contents]' );
	if ( ! bar ) {
		return;
	}
	bar.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( 'a' ) ) {
			bar.open = false;
		}
	} );
	bar.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && bar.open ) {
			bar.open = false;
			bar.querySelector( 'summary' ).focus();
		}
	} );
}() );
