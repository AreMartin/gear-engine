( function () {
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.gear-engine-guide__copy' );
		if ( ! button ) {
			return;
		}
		var text = button.getAttribute( 'data-copy' ) || '';
		var original = button.textContent;
		var done = function () {
			button.textContent = '✓';
			setTimeout( function () {
				button.textContent = original;
			}, 1200 );
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}
		var area = document.createElement( 'textarea' );
		area.value = text;
		document.body.appendChild( area );
		area.select();
		document.execCommand( 'copy' );
		document.body.removeChild( area );
		done();
	} );
}() );
