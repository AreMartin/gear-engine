/**
 * Gear Engine protocol page: lazy video embeds, radio selection sync, and print. No globals, no dependencies.
 *
 * @package GearEngine
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

	function syncRadioSelection( radio ) {
		if ( ! radio || ! radio.name ) {
			return;
		}
		var form = radio.closest( '.gear-engine-program__questions' );
		if ( ! form ) {
			return;
		}
		var radios = form.querySelectorAll( 'input[type="radio"][name="' + CSS.escape( radio.name ) + '"]' );
		radios.forEach( function ( r ) {
			var opt = r.closest( '.gear-engine-program__option' );
			if ( opt ) {
				opt.classList.toggle( 'gear-engine-program__option--selected', r.checked );
			}
		} );
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

	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		if ( target instanceof HTMLInputElement && target.type === 'radio' && target.closest( '.gear-engine-program' ) ) {
			syncRadioSelection( target );
		}
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		var checked = document.querySelectorAll( '.gear-engine-program input[type="radio"]:checked' );
		checked.forEach( function ( r ) {
			syncRadioSelection( r );
		} );
	} );
}() );
