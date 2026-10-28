/**
 * Gear Engine protocol page: lazy video embeds and print. No globals, no dependencies.
 */
( function () {
	'use strict';

	var ALLOWED = /^https:\/\/(exorlive\.com\/video\/|www\.youtube-nocookie\.com\/embed\/|player\.vimeo\.com\/video\/)/;

	function playVideo( box ) {
		var src = box.getAttribute( 'data-embed' ) || '';
		if ( ! ALLOWED.test( src ) ) {
			return;
		}
		var frame = document.createElement( 'iframe' );
		frame.src = src;
		frame.title = box.getAttribute( 'data-title' ) || '';
		frame.allow = 'autoplay; fullscreen; picture-in-picture';
		frame.setAttribute( 'allowfullscreen', '' );
		frame.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		box.textContent = '';
		box.appendChild( frame );
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! ( target instanceof Element ) ) {
			return;
		}
		var play = target.closest( '.gear-engine-program__play' );
		if ( play ) {
			var box = play.closest( '.gear-engine-program__video' );
			if ( box ) {
				playVideo( box );
			}
			return;
		}
		if ( target.closest( '.gear-engine-program [data-action="print"]' ) ) {
			window.print();
		}
	} );
}() );
