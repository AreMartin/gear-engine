/* Protocol Manager: batch auto-fill runner. One request per protocol keeps
 * the work under the server timeout, even with a large exercise library. */
( function () {
	'use strict';

	function postFill( button ) {
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

		function disableAll() {
			buttons.forEach( function ( button ) {
				button.disabled = true;
			} );
		}

		function step() {
			if ( index >= buttons.length ) {
				status.textContent = failed > 0
					? 'Auto-fill finished with ' + failed + ' failed request(s). Reloading…'
					: 'Auto-fill finished. Reloading…';
				window.setTimeout( function () { window.location.reload(); }, 400 );
				return;
			}

			var button = buttons[ index ];
			status.textContent = 'Auto-filling ' + ( index + 1 ) + ' of ' + buttons.length + '…';

			postFill( button ).then( function () {
				index += 1;
				step();
			}, function () {
				failed += 1;
				index += 1;
				step();
			} );
		}

		disableAll();
		step();
	}

	function init() {
		var perRow = Array.prototype.slice.call( document.querySelectorAll( '.gear-engine-autofill-btn' ) );
		var all = document.getElementById( 'gear-engine-autofill-all' );
		var status = document.getElementById( 'gear-engine-autofill-status' );
		if ( ! perRow.length || ! status ) {
			return;
		}

		perRow.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				runQueue( [ button ], status );
			} );
		} );

		if ( all ) {
			all.addEventListener( 'click', function () {
				runQueue( perRow, status );
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
