/**
 * Gear Engine Gear Picker Hub: Interactive Category Filter.
 * No globals, no external dependencies.
 *
 * @package GearEngine
 */
( function () {
	'use strict';

	function initHub( hub ) {
		if ( ! hub || hub.dataset.geHubInitialized ) {
			return;
		}
		hub.dataset.geHubInitialized = '1';

		var chips = hub.querySelectorAll( '.gear-engine-hub__chip' );
		var cards = hub.querySelectorAll( '.gear-engine-hub__card' );

		function selectCategory( category, updateUrl ) {
			chips.forEach( function ( chip ) {
				var matches = chip.getAttribute( 'data-category' ) === category;
				chip.classList.toggle( 'gear-engine-hub__chip--active', matches );
				chip.setAttribute( 'aria-pressed', matches ? 'true' : 'false' );
			} );

			cards.forEach( function ( card ) {
				var cardCat = card.getAttribute( 'data-category' );
				var visible = category === 'all' || cardCat === category;
				if ( visible ) {
					card.style.display = '';
					card.classList.remove( 'gear-engine-hub__card--hidden' );
				} else {
					card.style.display = 'none';
					card.classList.add( 'gear-engine-hub__card--hidden' );
				}
			} );

			if ( updateUrl && window.history && window.history.replaceState ) {
				var url = new URL( window.location.href );
				if ( category === 'all' ) {
					url.searchParams.delete( 'kategori' );
					url.searchParams.delete( 'cat' );
				} else {
					url.searchParams.set( 'kategori', category );
				}
				window.history.replaceState( {}, '', url.toString() );
			}
		}

		chips.forEach( function ( chip ) {
			chip.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var cat = chip.getAttribute( 'data-category' ) || 'all';
				selectCategory( cat, true );
			} );
		} );

		// Initial category from URL or data attribute
		var initialCat = hub.getAttribute( 'data-initial-category' ) || '';
		if ( ! initialCat ) {
			var params = new URLSearchParams( window.location.search );
			initialCat = params.get( 'kategori' ) || params.get( 'cat' ) || 'all';
		}

		var availableChip = hub.querySelector( '.gear-engine-hub__chip[data-category="' + CSS.escape( initialCat ) + '"]' );
		if ( availableChip ) {
			selectCategory( initialCat, false );
		} else {
			selectCategory( 'all', false );
		}
	}

	function initAllHubs() {
		var hubs = document.querySelectorAll( '.gear-engine-hub' );
		hubs.forEach( function ( hub ) {
			initHub( hub );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAllHubs );
	} else {
		initAllHubs();
	}
}() );

