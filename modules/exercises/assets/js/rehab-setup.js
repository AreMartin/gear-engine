/* Rehab Setup: drives the same per-protocol auto-fill endpoint the Protocol
 * Manager uses, one request per protocol so large setups never time out. */
( function () {
	'use strict';

	function postFill( button, status ) {
		var body = new FormData();
		body.append( 'action', 'gear_engine_autofill_protocol' );
		body.append( 'term_id', button.getAttribute( 'data-term-id' ) );
		body.append( '_wpnonce', button.getAttribute( 'data-nonce' ) );
		body.append( 'gear_engine_format', 'json' );
		body.append( '_wp_http_referer', window.location.href );

		return fetch( button.getAttribute( 'data-admin-url' ), {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}
			return response.json();
		} );
	}

	function runQueue( buttons, status ) {
		var index = 0;
		var failed = 0;

		buttons.forEach( function ( button ) {
			button.disabled = true;
		} );

		function step() {
			if ( index >= buttons.length ) {
				status.textContent = failed > 0
					? 'Finished with ' + failed + ' failed request(s). Reloading…'
					: 'Auto-fill finished. Reloading…';
				window.setTimeout( function () { window.location.reload(); }, 400 );
				return;
			}
			status.textContent = 'Auto-filling ' + ( index + 1 ) + ' of ' + buttons.length + '…';
			postFill( buttons[ index ], status ).then( function () {
				index += 1;
				step();
			}, function () {
				failed += 1;
				index += 1;
				step();
			} );
		}

		step();
	}

	function init() {
		var perProtocol = Array.prototype.slice.call( document.querySelectorAll( '.gear-engine-rehab-fill-one' ) );
		var all = document.getElementById( 'gear-engine-rehab-fill-all' );
		var status = document.getElementById( 'gear-engine-rehab-fill-status' );
		if ( ! perProtocol.length || ! status || ! all ) {
			return;
		}
		perProtocol.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				runQueue( [ button ], status );
			} );
		} );
		all.addEventListener( 'click', function () {
			runQueue( perProtocol, status );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
