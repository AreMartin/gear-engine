/**
 * Gear picker UI. Builds the DOM with textContent only, so product data is never parsed as HTML.
 */
( function () {
	'use strict';

	var Scoring = window.GearEnginePickerScoring;
	var NO_IMAGE = 'data:image/svg+xml;utf8,' + encodeURIComponent( '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150"><rect width="100%" height="100%" fill="#e2e8f0"/></svg>' );

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined && text !== null ) {
			node.textContent = text;
		}
		return node;
	}

	function button( className, text, onClick ) {
		var b = el( 'button', className, text );
		b.type = 'button';
		b.addEventListener( 'click', onClick );
		return b;
	}

	function money( value, cur ) {
		return Math.round( value ).toLocaleString( 'nb-NO' ) + ' ' + cur;
	}

	function externalIcon() {
		var ns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS( ns, 'svg' );
		[ [ 'width', '14' ], [ 'height', '14' ], [ 'viewBox', '0 0 24 24' ], [ 'fill', 'none' ], [ 'stroke', 'currentColor' ], [ 'stroke-width', 'var(--ge-icon-stroke, 1.75px)' ], [ 'stroke-linecap', 'round' ], [ 'stroke-linejoin', 'round' ], [ 'aria-hidden', 'true' ] ].forEach( function ( a ) {
			svg.setAttribute( a[ 0 ], a[ 1 ] );
		} );
		svg.setAttribute( 'class', 'gear-engine-picker__cta-icon' );
		[ 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6', 'M15 3h6v6', 'M10 14L21 3' ].forEach( function ( d ) {
			var path = document.createElementNS( ns, 'path' );
			path.setAttribute( 'd', d );
			svg.appendChild( path );
		} );
		return svg;
	}

	function safeUrl( url ) {
		return /^https?:\/\//i.test( url ) ? url : '#';
	}

	function init( root ) {
		var dataNode = root.querySelector( '.gear-engine-picker__data' );
		var app = root.querySelector( '.gear-engine-picker__app' );
		if ( ! dataNode || ! app ) {
			return;
		}

		var data;
		try {
			data = JSON.parse( dataNode.textContent );
		} catch ( e ) {
			return;
		}

		var config = data.config;
		var steps = config.steps;
		var filters = Scoring.defaults( config );
		var step = 0;

		function go( next ) {
			step = next;
			render();
			app.querySelector( '.gear-engine-picker__stage' ).focus();
		}

		function renderProgress( stage ) {
			var bar = el( 'div', 'gear-engine-picker__progress' );
			steps.forEach( function ( s, i ) {
				bar.appendChild( el( 'span', step > i ? 'is-done' : '' ) );
			} );
			stage.appendChild( bar );
		}

		function renderIntro( stage ) {
			var intro = config.intro;
			var box = el( 'div', 'gear-engine-picker__intro-box' );
			box.appendChild( el( 'span', 'gear-engine-picker__eyebrow', intro.eyebrow ) );
			box.appendChild( el( 'h2', 'gear-engine-picker__title', intro.title ) );
			box.appendChild( el( 'p', 'gear-engine-picker__lead', intro.desc ) );
			var list = el( 'ul', 'gear-engine-picker__benefits' );
			( intro.benefits || [] ).forEach( function ( b ) {
				list.appendChild( el( 'li', '', b ) );
			} );
			box.appendChild( list );
			box.appendChild( button( 'gear-engine-picker__primary', intro.start, function () {
				go( 1 );
			} ) );
			stage.appendChild( box );
		}

		function renderStep( stage ) {
			var current = steps[ step - 1 ];
			renderProgress( stage );
			stage.appendChild( el( 'h2', 'gear-engine-picker__title', current.title ) );

			if ( current.type === 'choice' ) {
				current.options.forEach( function ( opt ) {
					var b = button( 'gear-engine-picker__choice', null, function () {
						filters[ current.id ] = opt.val;
						go( step + 1 );
					} );
					b.appendChild( el( 'span', 'gear-engine-picker__choice-title', opt.title ) );
					if ( opt.desc ) {
						b.appendChild( el( 'span', 'gear-engine-picker__choice-desc', opt.desc ) );
					}
					stage.appendChild( b );
				} );
			} else {
				var box = el( 'div', 'gear-engine-picker__slider' );
				var value = el( 'div', 'gear-engine-picker__slider-value' );
				var num = el( 'span' );
				var show = function () {
					num.textContent = current.format ? Number( filters[ current.id ] ).toLocaleString( 'nb-NO' ) : String( filters[ current.id ] );
				};
				show();
				value.appendChild( num );
				value.appendChild( document.createTextNode( ' ' + current.unit ) );
				box.appendChild( value );
				if ( current.subtitle ) {
					box.appendChild( el( 'p', 'gear-engine-picker__slider-note', current.subtitle ) );
				}
				var input = el( 'input', 'gear-engine-picker__range' );
				input.type = 'range';
				input.min = current.min;
				input.max = current.max;
				input.step = current.step;
				input.value = filters[ current.id ];
				input.setAttribute( 'aria-label', current.title );
				input.addEventListener( 'input', function () {
					filters[ current.id ] = Number( input.value );
					show();
				} );
				box.appendChild( input );
				stage.appendChild( box );
				stage.appendChild( button( 'gear-engine-picker__primary', step === steps.length ? 'Finn mine treff' : 'Neste steg', function () {
					go( step + 1 );
				} ) );
			}

			stage.appendChild( button( 'gear-engine-picker__back', '← Tilbake', function () {
				go( step - 1 );
			} ) );
		}

		function pills( item ) {
			var wrap = el( 'div', 'gear-engine-picker__pills' );
			var estimated = item.est || [];
			var shown = function ( field ) {
				var v = item.specs[ field ];
				return v !== null && v !== undefined && v !== false && estimated.indexOf( field ) === -1;
			};
			( config.pills || [] ).forEach( function ( p ) {
				if ( ! shown( p.field ) || ( p.unless && shown( p.unless ) ) ) {
					return;
				}
				var cls = 'gear-engine-picker__pill';
				wrap.appendChild( el( 'span', cls, p.text.replace( '{v}', item.specs[ p.field ] ) ) );
			} );
			return wrap;
		}

		function card( item ) {
			var cur = item.cur || 'kr';
			var cls = 'gear-engine-picker__card' + ( item.isBestMatch ? ' is-best-match' : '' ) + ( item.isBestOffer ? ' is-best-offer' : '' );
			var c = el( 'article', cls );

			if ( item.isBestMatch ) {
				c.appendChild( el( 'div', 'gear-engine-picker__badge', 'Beste Match' ) );
			} else if ( item.isBestOffer ) {
				c.appendChild( el( 'div', 'gear-engine-picker__badge is-offer', 'Beste Tilbud' ) );
			}

			var top = el( 'div', 'gear-engine-picker__card-top' );
			var imgWrap = el( 'div', 'gear-engine-picker__img' );
			if ( item.discount > 0 ) {
				imgWrap.appendChild( el( 'span', 'gear-engine-picker__discount', '-' + item.discount + '%' ) );
			}
			var img = el( 'img' );
			img.src = item.img || NO_IMAGE;
			img.alt = item.name;
			img.loading = 'lazy';
			img.referrerPolicy = 'no-referrer';
			img.addEventListener( 'error', function () {
				img.src = NO_IMAGE;
			}, { once: true } );
			imgWrap.appendChild( img );
			top.appendChild( imgWrap );

			var details = el( 'div', 'gear-engine-picker__details' );
			var meta = el( 'div', 'gear-engine-picker__meta' );
			meta.appendChild( el( 'span', 'gear-engine-picker__shop', item.shop ) );
			meta.appendChild( el( 'span', 'gear-engine-picker__match', item.matchPercentage + '% match' ) );
			details.appendChild( meta );
			details.appendChild( el( 'h3', 'gear-engine-picker__name', item.name ) );
			details.appendChild( pills( item ) );
			if ( item.note ) {
				details.appendChild( el( 'p', 'gear-engine-picker__reasons', item.note ) );
			}
			top.appendChild( details );
			c.appendChild( top );

			var footer = el( 'div', 'gear-engine-picker__footer' );
			var price = el( 'div', 'gear-engine-picker__price' );
			if ( item.old > item.price ) {
				price.appendChild( el( 'span', 'gear-engine-picker__old-price', money( item.old, cur ) ) );
			}
			price.appendChild( el( 'span', 'gear-engine-picker__current-price' + ( item.discount > 0 ? ' is-discounted' : '' ), money( item.price, cur ) ) );
			footer.appendChild( price );

			var link = el( 'a', 'gear-engine-picker__cta', 'Til butikk' );
			link.appendChild( externalIcon() );
			link.href = safeUrl( item.url );
			link.target = '_blank';
			link.rel = 'noopener nofollow sponsored';
			footer.appendChild( link );
			c.appendChild( footer );
			return c;
		}

		function renderResults( stage ) {
			var ranked = Scoring.rank( config, data.items, filters );
			var results = ranked.items;
			var head = el( 'div', 'gear-engine-picker__results-head' );
			head.appendChild( el( 'h2', 'gear-engine-picker__title', 'Toppvalg for deg' ) );
			head.appendChild( button( 'gear-engine-picker__link', 'Endre valg', function () {
				go( 0 );
			} ) );
			stage.appendChild( head );
			stage.appendChild( el( 'p', 'gear-engine-picker__disclosure', '* Lenkene under er annonselenker. Vi får en liten provisjon ved kjøp, uten at det koster deg noe ekstra.' ) );

			if ( ranked.isFallback && results.length ) {
				var warn = el( 'div', 'gear-engine-picker__notice' );
				warn.appendChild( el( 'strong', '', 'Vi fant ingen perfekt match: ' ) );
				warn.appendChild( document.createTextNode( 'Ingen modeller traff på alle kravene dine. Her er de beste alternativene som støtter din vekt og ditt budsjett.' ) );
				stage.appendChild( warn );
			}

			if ( ! results.length ) {
				var empty = el( 'div', 'gear-engine-picker__none' );
				empty.appendChild( el( 'strong', '', 'Ingen resultater' ) );
				empty.appendChild( document.createTextNode( 'Ingen modeller matcher dine nøyaktige krav akkurat nå. Prøv å justere parameterne.' ) );
				stage.appendChild( empty );
				return;
			}
			results.forEach( function ( item ) {
				stage.appendChild( card( item ) );
			} );
		}

		function render() {
			var stage = el( 'div', 'gear-engine-picker__stage' );
			stage.tabIndex = -1;
			if ( step === 0 ) {
				renderIntro( stage );
			} else if ( step <= steps.length ) {
				renderStep( stage );
			} else {
				renderResults( stage );
			}
			app.replaceChildren( stage );
		}

		render();
	}

	function boot() {
		document.querySelectorAll( '.gear-engine-picker' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
