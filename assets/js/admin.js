( function () {
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-tlkp-confirm]' );
		if ( button && ! window.confirm( button.getAttribute( 'data-tlkp-confirm' ) ) ) {
			event.preventDefault();
		}
	} );
} )();
