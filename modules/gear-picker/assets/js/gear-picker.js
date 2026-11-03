/**
 * Gear Engine gear picker: Material Design 3 interactive engine.
 * Built strictly on Material Design 3 and Fysionett.no visual identity.
 * 100% parity with material3-prototype.html.
 *
 * @package GearEngine
 */
( function () {
	'use strict';

	var Scoring = window.GearEnginePickerScoring;

	var NO_IMAGE = 'data:image/svg+xml;utf8,' + encodeURIComponent(
		'<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150"><rect width="100%" height="100%" fill="#e2e8f0"/></svg>'
	);

	var PLACEHOLDER_SVG = '<svg viewBox="0 0 140 90" width="120" height="78" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.7"><rect x="20" y="22" width="100" height="46" rx="8" stroke-dasharray="4 3"/><circle cx="50" cy="45" r="10"/><path d="M70 45h20M90 38v14"/></svg>';
	var TREAD_SVG = PLACEHOLDER_SVG;

	/* =========================================================
	   Icons (Material Symbols, stroke controlled by CSS)
	   ========================================================= */
	var ICONS = {
		back:       '<path d="M19 12H5M12 19l-7-7 7-7"/>',
		close:      '<path d="M18 6L6 18M6 6l12 12"/>',
		check:      '<path d="M5 12l5 5 9-10"/>',
		chevR:      '<path d="M9 18l6-6-6-6"/>',
		ext:        '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/>',
		compare:    '<path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7m0-18H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7m0-18v18"/>',
		speedLow:   '<circle cx="12" cy="12" r="9"/><path d="M12 12L8 9"/><circle cx="12" cy="12" r="2" fill="currentColor"/>',
		speedMid:   '<circle cx="12" cy="12" r="9"/><path d="M12 12V6.5"/><circle cx="12" cy="12" r="2" fill="currentColor"/>',
		speedHigh:  '<circle cx="12" cy="12" r="9"/><path d="M12 12l4.5-3.5"/><circle cx="12" cy="12" r="2" fill="currentColor"/>',
		fold:       '<path d="M4 19h16M7 19l4-11 4 11M14 6l3 2-3 2"/><circle cx="11" cy="8" r="1.5"/>',
		fixed:      '<rect x="3" y="16" width="18" height="3" rx="1.5"/><path d="M6 16V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v10"/><line x1="6" y1="10" x2="18" y2="10"/>',
		incline:    '<path d="M4 19h16L20 8l-8 5-8 6z"/><path d="M12 5l3 3-3 3"/>',
		flat:       '<line x1="3" y1="17" x2="21" y2="17"/><path d="M5 14h14M8 11h8"/>',
		sliders:    '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
		medical:    '<circle cx="12" cy="12" r="9.5"/><line x1="12" y1="7.5" x2="12" y2="16.5"/><line x1="7.5" y1="12" x2="16.5" y2="12"/>',
		info:       '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
		refresh:    '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
		minus:      '<line x1="5" y1="12" x2="19" y2="12"/>',
		tag:        '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
		screen:     '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
		app:        '<rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
		clock:      '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		edit:       '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'
	};

	function ic( name, size ) {
		var sz = size || 24;
		var path = ICONS[ name ] || ICONS.info;
		return '<svg width="' + sz + '" height="' + sz + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
	}

	function decodeEntities( s ) {
		if ( typeof s !== 'string' || s.indexOf( '&' ) === -1 ) {
			return s;
		}
		try {
			var txt = document.createElement( 'textarea' );
			txt.innerHTML = s;
			return txt.value || s;
		} catch ( e ) {
			return s.replace( /&#(\d+);/g, function ( m, d ) { return String.fromCharCode( d ); } )
				.replace( /&ndash;/g, '–' )
				.replace( /&mdash;/g, '—' )
				.replace( /&amp;/g, '&' )
				.replace( /&quot;/g, '"' )
				.replace( /&#039;/g, "'" );
		}
	}

	function esc( v ) {
		var s = decodeEntities( String( v == null ? '' : v ) );
		return s.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	function money( n, cur ) {
		return Math.round( Number( n ) || 0 ).toLocaleString( 'nb-NO' ) + ' ' + ( cur || 'kr' );
	}

	function cleanShopName( raw ) {
		var s = decodeEntities( String( raw || '' ) ).trim();
		var m = s.match( /\(([^)]+)\)$/ );
		return m ? m[ 1 ].trim() : s;
	}

	function initials( n ) {
		var clean = cleanShopName( n ).replace( /[()[\]{}_-]/g, ' ' ).trim();
		var parts = clean.split( /\s+/ ).filter( Boolean );
		if ( ! parts.length ) { return 'GE'; }
		return ( parts.length > 1 ? parts[ 0 ][ 0 ] + parts[ 1 ][ 0 ] : parts[ 0 ].slice( 0, 2 ) ).toUpperCase();
	}

	var KNOWN_MERCHANT_DOMAINS = {
		'treningspartner': 'treningspartner.no',
		'gymgrossisten':   'gymgrossisten.no',
		'fjellsport':      'fjellsport.no',
		'sportmann':       'sportmann.no',
		'mylna':           'mylnasport.no',
		'e-wheels':        'e-wheels.no',
		'xxl':             'xxl.no',
		'antonsport':      'antonsport.no',
		'tights':          'tights.no',
		'milrab':          'milrab.no',
		'proteinfabrikken':'proteinfabrikken.no'
	};

	function getMerchantLogo( item ) {
		if ( item.shop_logo ) {
			return item.shop_logo;
		}
		var shopName = cleanShopName( item.shop || '' );
		var lower = shopName.toLowerCase().replace( /[^a-z0-9]/g, '' );
		var domain = null;

		for ( var key in KNOWN_MERCHANT_DOMAINS ) {
			if ( lower.indexOf( key ) !== -1 || key.indexOf( lower ) !== -1 ) {
				domain = KNOWN_MERCHANT_DOMAINS[ key ];
				break;
			}
		}

		if ( ! domain && item.url && /^https?:\/\//i.test( item.url ) ) {
			try {
				var u = new URL( item.url );
				var host = u.hostname.replace( /^www\./, '' );
				if ( host && host.indexOf( 'adtr.co' ) === -1 && host.indexOf( 'tradetracker' ) === -1 && host.indexOf( 'example.com' ) === -1 ) {
					domain = host;
				}
			} catch ( e ) {}
		}

		return domain ? 'https://www.google.com/s2/favicons?domain=' + domain + '&sz=128' : null;
	}

	function renderShopAvatar( item, small ) {
		var shopName = cleanShopName( item.shop || '' );
		var inits = initials( shopName );
		var logoUrl = getMerchantLogo( item );
		var style = small ? ' style="width:28px;height:28px;font-size:11px"' : '';

		if ( logoUrl ) {
			return '<span class="avatar"' + style + '>' +
				'<img src="' + esc( logoUrl ) + '" alt="' + esc( shopName ) + '" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display=\'none\';if(this.nextElementSibling){this.nextElementSibling.style.display=\'grid\';}">' +
				'<span class="avatar-inits" style="display:none;width:100%;height:100%;place-items:center">' + inits + '</span>' +
				'</span>';
		}
		return '<span class="avatar"' + style + '><span class="avatar-inits">' + inits + '</span></span>';
	}

	function safeUrl( url ) {
		return /^https?:\/\//i.test( url ) ? url : '#';
	}

	function getSpec( item, key ) {
		if ( ! item || ! item.specs ) { return null; }
		return item.specs[ key ];
	}

	function renderProductPills( config, item, isCompact ) {
		var defs = ( config && config.pills ) || [];
		var pills = [];
		var activeFields = {};

		defs.forEach( function ( p ) {
			if ( p.unless && activeFields[ p.unless ] ) {
				return;
			}
			var val = getSpec( item, p.field );
			if ( val === null || val === undefined || val === false ) {
				return;
			}
			activeFields[ p.field ] = true;
			var text = p.text || String( val );
			if ( typeof val === 'number' || typeof val === 'string' ) {
				text = text.replace( '{v}', val );
			}
			pills.push( text );
		} );

		if ( ! pills.length ) {
			var sp = getSpec( item, 'speed' );
			var wt = getSpec( item, 'weight' );
			var fd = getSpec( item, 'foldable' );
			if ( sp ) { pills.push( 'Toppfart: ' + sp + ' km/t' ); }
			if ( wt ) { pills.push( 'Maks brukervekt: ' + wt + ' kg' ); }
			if ( fd ) { pills.push( 'Sammenleggbar' ); }
		}

		if ( isCompact && pills.length > 3 ) {
			pills = pills.slice( 0, 3 );
		}

		return pills.map( function ( txt ) {
			return '<span class="spec">' + esc( txt ) + '</span>';
		} ).join( '' );
	}

	/* =========================================================
	   Picker Component
	   ========================================================= */
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
		var steps = config.steps || [];
		var items = data.items || [];
		var filters = Scoring.defaults( config );

		// Dynamic presets mapping from config with fallback
		var PRESETS = {};
		if ( Array.isArray( config.presets ) && config.presets.length ) {
			config.presets.forEach( function ( p ) {
				PRESETS[ p.key ] = p.filters || {};
			} );
		} else {
			PRESETS = {
				budget:       { b: 15000, w: 80 },
				sprint:       { goal: 'interval', w: 80 },
				compact:      { space: 'foldable', b: 20000, w: 80 },
				rehab:        { cushion: 'rehab', w: 80 },
				foldable_15k: { space: 'foldable', b: 15000, w: 80 },
				speed_20:     { goal: 'interval', w: 80 }
			};
		}

		var state = {
			step: 0, // 0 = intro, 1..N = quiz steps, N+1 = results
			dir: 'forward',
			busy: false,
			sheet: null, // null | 'compare' | 'ranking'
			cmpId: null,
			view: 'quiz', // 'quiz' | 'deals'
			dealSort: 'pct', // 'pct' | 'amount'
			presetApplied: false
		};

		var STORAGE_KEY = 'gear_engine_picker_' + ( config.id || 'default' );

		function saveSession() {
			try {
				if ( ! window.sessionStorage ) {
					return;
				}
				if ( state.step === 0 && state.view === 'quiz' ) {
					window.sessionStorage.removeItem( STORAGE_KEY );
				} else {
					window.sessionStorage.setItem( STORAGE_KEY, JSON.stringify( {
						filters: filters,
						step: state.step,
						view: state.view,
						dealSort: state.dealSort || 'pct',
						presetApplied: !! state.presetApplied
					} ) );
				}
			} catch ( e ) {}
		}

		function loadSession() {
			try {
				if ( ! window.sessionStorage ) {
					return false;
				}
				var raw = window.sessionStorage.getItem( STORAGE_KEY );
				if ( ! raw ) {
					return false;
				}
				var saved = JSON.parse( raw );
				if ( saved && typeof saved === 'object' ) {
					if ( saved.filters && typeof saved.filters === 'object' ) {
						Object.assign( filters, saved.filters );
					}
					if ( typeof saved.step === 'number' ) {
						state.step = saved.step;
					}
					if ( saved.view ) {
						state.view = saved.view;
					}
					if ( saved.dealSort ) {
						state.dealSort = saved.dealSort;
					}
					if ( saved.presetApplied != null ) {
						state.presetApplied = saved.presetApplied;
					}
					return true;
				}
			} catch ( e ) {}
			return false;
		}

		function clearSession() {
			try {
				if ( window.sessionStorage ) {
					window.sessionStorage.removeItem( STORAGE_KEY );
				}
			} catch ( e ) {}
		}

		// Helper to fetch all discounted products sorted by discount % or kr savings
		function getDeals( sortMode ) {
			var mode = sortMode || state.dealSort || 'pct';
			return items.filter( function ( p ) {
				return ( p.old && p.old > p.price ) || ( p.discount && p.discount > 0 );
			} ).sort( function ( a, b ) {
				var da = a.discount || ( ( a.old && a.old > a.price ) ? Math.round( ( ( a.old - a.price ) / a.old ) * 100 ) : 0 );
				var db = b.discount || ( ( b.old && b.old > b.price ) ? Math.round( ( ( b.old - b.price ) / b.old ) * 100 ) : 0 );
				var saveA = ( a.old && a.old > a.price ) ? ( a.old - a.price ) : 0;
				var saveB = ( b.old && b.old > b.price ) ? ( b.old - b.price ) : 0;

				if ( mode === 'amount' ) {
					if ( saveB !== saveA ) { return saveB - saveA; }
					return db - da;
				}
				if ( db !== da ) { return db - da; }
				return saveB - saveA;
			} );
		}

		// Unique merchant count
		var uniqueShops = [];
		items.forEach( function ( p ) {
			if ( p.shop && uniqueShops.indexOf( p.shop ) === -1 ) {
				uniqueShops.push( p.shop );
			}
		} );

		function getStepIndex( id ) {
			for ( var i = 0; i < steps.length; i++ ) {
				if ( steps[ i ].id === id ) {
					return i + 1;
				}
			}
			return 1;
		}

		function getLiveCount() {
			var ranked = Scoring.rank( config, items, filters );
			var count = ranked.items ? ranked.items.length : 0;
			var total = items.length || 1;
			return {
				count: count,
				pct: Math.round( ( count / total ) * 100 )
			};
		}

		function go( next, dir ) {
			state.dir = dir || ( next < state.step ? 'backward' : 'forward' );
			state.step = next;
			state.sheet = null;
			saveSession();
			render();
			var stage = app.querySelector( '.gear-engine-picker__stage' );
			if ( stage && stage.focus ) {
				stage.focus( { preventScroll: true } );
			}
			if ( root.scrollIntoView && state.step > 0 ) {
				var rect = root.getBoundingClientRect();
				if ( rect.top < -30 ) {
					var targetTop = window.scrollY + rect.top - 24;
					window.scrollTo( { top: Math.max( 0, targetTop ), behavior: 'smooth' } );
				}
			}
		}

		function applyPreset( key ) {
			state.view = 'quiz';
			state.presetApplied = true;
			filters = Scoring.defaults( config );
			var p = PRESETS[ key ] || PRESETS.budget;
			Object.assign( filters, p );
			go( steps.length + 1, 'forward' );
		}

		function openDeals() {
			state.view = 'deals';
			state.dir = 'forward';
			state.sheet = null;
			saveSession();
			render();
			var stage = app.querySelector( '.gear-engine-picker__stage' );
			if ( stage ) { stage.focus(); }
			if ( root.scrollIntoView ) {
				root.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		}

		/* =========================================================
		   HTML Generators
		   ========================================================= */

		function renderAppbar( title, isResults ) {
			return '<div class="appbar">' +
				'<button type="button" class="ibtn sl" data-action="back" aria-label="Tilbake"' + ( state.step === 0 ? ' disabled' : '' ) + '>' + ic( 'back', 20 ) + '</button>' +
				'<div class="title-l">' + esc( title ) + '</div>' +
				( isResults
					? '<button type="button" class="ibtn sl" data-action="reset" aria-label="Start på nytt" title="Start på nytt">' + ic( 'refresh', 20 ) + '</button>'
					: '<button type="button" class="ibtn sl" data-action="reset" aria-label="Avslutt" title="Gå til start">' + ic( 'close', 20 ) + '</button>'
				) +
				'</div>';
		}

		function renderProgressBar() {
			var n = steps.length;
			var pct = Math.round( ( state.step / n ) * 100 );
			return '<div class="steps-track" role="progressbar" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100">' +
				'<div class="steps-fill" style="width:' + pct + '%"></div>' +
				'</div>';
		}

		function renderLiveStats() {
			var live = getLiveCount();
			return '<div class="grid2">' +
				'<div class="card outlined"><div class="headline-m num" id="ge-live-c" style="color:var(--md-sys-color-primary,#0C2340)">' + live.count + '</div><div class="body-s muted" id="ge-live-c-label">' + ( live.count === 1 ? 'Matchende modell' : 'Matchende modeller' ) + '</div></div>' +
				'<div class="card outlined"><div class="headline-m num" style="color:var(--md-sys-color-primary,#0C2340)">' + ( uniqueShops.length || 6 ) + '</div><div class="body-s muted">Verifiserte butikker</div></div>' +
				'</div>';
		}

		function renderLiveCard() {
			var live = getLiveCount();
			var total = items.length;
			return '<div class="live">' +
				'<span class="dotp"></span>' +
				'<span class="body-s" style="color:var(--md-sys-color-on-surface,#0C2340);flex:1">Finner modeller: <b id="ge-live-lbl">' + ( live.count === 1 ? '1 modell matcher valgene dine' : live.count + ' av ' + total + ' matcher valgene dine' ) + '</b></span>' +
				'<div class="lp"><i id="ge-live-bar" style="width:' + live.pct + '%"></i></div>' +
				'</div>';
		}

		function updateLiveElements() {
			var live = getLiveCount();
			var total = items.length;
			var c = root.querySelector( '#ge-live-c' );
			var cLabel = root.querySelector( '#ge-live-c-label' );
			var lbl = root.querySelector( '#ge-live-lbl' );
			var bar = root.querySelector( '#ge-live-bar' );
			if ( c ) { c.textContent = live.count; }
			if ( cLabel ) { cLabel.textContent = live.count === 1 ? 'Matchende modell' : 'Matchende modeller'; }
			if ( lbl ) {
				lbl.textContent = live.count === 1 ? '1 modell matcher valgene dine' : live.count + ' av ' + total + ' matcher valgene dine';
			}
			if ( bar ) { bar.style.width = live.pct + '%'; }
		}

		/* --- Intro View --- */
		function renderIntro() {
			var intro = config.intro || {};
			var sortedByDiscount = items.slice().sort( function ( a, b ) {
				var da = a.old > a.price ? ( a.old - a.price ) / a.old : 0;
				var db = b.old > b.price ? ( b.old - b.price ) / b.old : 0;
				return db - da;
			} );
			var deals = sortedByDiscount.slice( 0, 2 );

			var h = '<div style="padding-top:16px">' +
				'<div class="overline">Kjøpsguide</div>' +
				'<h1 class="headline-l" style="margin-top:4px">' + esc( config.label || 'Treningsutstyr' ) + '</h1>' +
				'<p class="body-m muted" style="margin-top:8px">Sammenlign spesifikasjoner, egenskaper og dagspriser fra ' + ( uniqueShops.length || 6 ) + ' norske butikker.</p>' +
				'<div class="chips-row" style="margin-top:16px">' +
					'<button type="button" class="chip sl sel" data-action="deals">' + ic( 'tag', 18 ) + 'Dagens tilbud</button>';

			if ( config.presets && config.presets.length ) {
				config.presets.slice( 0, 2 ).forEach( function ( pr ) {
					h += '<button type="button" class="chip sl" data-action="quick" data-preset="' + esc( pr.key ) + '">' +
						ic( pr.icon || 'sliders', 18 ) + esc( pr.label ) + '</button>';
				} );
			}

			h += '</div></div>';

			h += '<div class="card xl" style="margin-top:18px;background:var(--md-sys-color-primary-container,#EAF0F6);border:1px solid #D5E1ED;box-shadow:var(--md-sys-elevation-1,0 1px 3px rgba(0,0,0,0.06))">' +
				'<span class="pill" style="background:#FFFFFF;color:var(--md-sys-color-primary,#0C2340);border:1px solid #D0DBE5;font-weight:600;box-shadow:0 1px 2px rgba(12,35,64,0.04);gap:6px"><span style="width:6px;height:6px;border-radius:50%;background:var(--md-sys-color-tertiary,#005236)"></span>Smart kjøpsveileder · 1 min</span>' +
				'<h2 class="headline-s" style="margin:14px 0 8px;color:var(--md-sys-color-primary,#0C2340)">' + esc( intro.title || 'Usikker på hvilken modell du bør velge?' ) + '</h2>' +
				'<p class="body-m" style="color:var(--md-sys-color-on-surface-variant,#475467);line-height:22px">' + esc( intro.desc || 'Svar på noen enkle spørsmål om behov og budsjett. Vår algoritme finner de beste modellene for deg.' ) + '</p>' +
				'<button type="button" class="btn filled primary sl" style="margin-top:20px;height:48px;font-size:15px;font-weight:600;padding:0 24px" data-action="start">' +
				esc( intro.start || 'Start kjøpsveileder' ) + ' ' + ic( 'chevR', 18 ) + '</button>' +
				'</div>';

			if ( deals.length ) {
				h += '<div class="sec-head"><h3 class="title-l">Mest populære tilbud</h3><button type="button" class="btn text sl" data-action="deals">Se alle</button></div>';
				h += '<div class="deal-grid">';
				deals.forEach( function ( d ) {
					var disc = d.old > d.price ? Math.round( ( ( d.old - d.price ) / d.old ) * 100 ) : 0;

					h += '<div class="card elevated stack">' +
						'<div class="art" style="height:140px">' +
							( disc ? '<span class="pill err disc">−' + disc + '%</span>' : '' ) +
							( d.img ? '<img src="' + esc( d.img ) + '" alt="' + esc( d.name ) + '" loading="lazy">' : PLACEHOLDER_SVG ) +
						'</div>' +
						'<div class="head-row" style="margin:14px 0 4px"><span class="body-s muted" style="font-weight:700;letter-spacing:0.3px;text-transform:uppercase">' + esc( cleanShopName( d.shop ) ) + '</span><span class="pill warn">Populær</span></div>' +
						'<h4 class="title-m" style="margin:0 0 8px">' + esc( d.name ) + '</h4>' +
						'<div class="specs">' + renderProductPills( config, d, true ) + '</div>' +
						'<div class="pricebar row" style="margin-top:16px"><div class="price"><b class="num">' + money( d.price, d.cur ) + '</b>' + ( d.old > d.price ? '<s class="num">' + money( d.old, d.cur ) + '</s>' : '' ) + '</div>' +
						'<a class="btn filled sl" href="' + safeUrl( d.url ) + '" target="_blank" rel="noopener nofollow sponsored">Se deal' + ic( 'ext', 16 ) + '</a></div>' +
						'</div>';
				} );
				h += '</div>';
			}

			if ( config.presets && config.presets.length ) {
				h += '<div class="sec-head"><h3 class="title-l">Velg etter bruksområde</h3></div>' +
					'<div class="tiles">';
				config.presets.forEach( function ( pr ) {
					h += '<button type="button" class="tile sl" data-action="quick" data-preset="' + esc( pr.key ) + '"><span class="ti">' + ic( pr.icon || 'sliders', 22 ) + '</span><span><b>' + esc( pr.label ) + '</b><small>' + esc( pr.desc ) + '</small></span></button>';
				} );
				h += '</div>';
			}

			return h;
		}

		/* --- Step / Quiz View --- */
		function renderStep() {
			var s = steps[ state.step - 1 ];
			var n = steps.length;
			var isLast = state.step === n;
			var hasAnswer = filters[ s.id ] !== undefined && filters[ s.id ] !== null;

			var h = renderAppbar( 'Steg ' + state.step + ' av ' + n ) +
				renderProgressBar() +
				renderLiveStats();

			h += '<div class="overline" style="margin-top:24px">Steg ' + state.step + ' av ' + n + '</div>' +
				'<h2 class="headline-s" style="margin-top:4px">' + esc( s.title ) + '</h2>';

			if ( s.type === 'choice' ) {
				h += '<div class="opts" role="radiogroup">';
				( s.options || [] ).forEach( function ( o ) {
					var on = filters[ s.id ] === o.val;
					// Map icon
					var iconKey = 'speedMid';
					if ( o.val === 'walk' ) { iconKey = 'speedLow'; }
					else if ( o.val === 'interval' ) { iconKey = 'speedHigh'; }
					else if ( o.val === 'foldable' ) { iconKey = 'fold'; }
					else if ( o.val === 'any' ) { iconKey = 'fixed'; }
					else if ( o.val === 'yes' ) { iconKey = 'incline'; }
					else if ( o.val === 'no' ) { iconKey = 'flat'; }
					else if ( o.val === 'screen' ) { iconKey = 'screen'; }
					else if ( o.val === 'app' ) { iconKey = 'app'; }
					else if ( o.val === 'rehab' || o.val === 'soft' ) { iconKey = 'medical'; }
					else if ( o.val === 'light' ) { iconKey = 'speedLow'; }
					else if ( o.val === 'heavy' ) { iconKey = 'speedHigh'; }

					h += '<button type="button" class="opt sl' + ( on ? ' is-active' : '' ) + '" data-action="choice" data-id="' + s.id + '" data-val="' + esc( o.val ) + '" aria-pressed="' + on + '">' +
						'<span class="oi">' + ic( iconKey, 20 ) + '</span>' +
						'<span class="ot"><b>' + esc( o.title ) + '</b>' + ( o.desc ? '<small>' + esc( o.desc ) + '</small>' : '' ) + '</span>' +
						'<span class="ctl">' + ( on ? ic( 'check', 14 ) : '' ) + '</span>' +
						'</button>';
				} );
				h += '</div>' + renderLiveCard();
			} else {
				var val = filters[ s.id ] != null ? filters[ s.id ] : ( s.default || s.min );
				var pct = Math.max( 0, Math.min( 100, ( ( val - s.min ) / ( s.max - s.min ) ) * 100 ) );
				var formatted = s.format ? Number( val ).toLocaleString( 'nb-NO' ) : val;

				h += ( s.subtitle ? '<p class="body-m muted" style="margin-top:8px">' + esc( s.subtitle ) + '</p>' : '' ) +
					'<div class="card outlined" style="margin-top:20px;padding:12px 18px 20px">' +
						'<div class="big-val">' +
							'<div class="overline muted" style="margin-bottom:4px">' + ( s.id === 'b' ? 'Valgt maksgrense' : 'Valgt vekt' ) + '</div>' +
							'<div class="display-m num"><span id="ge-slider-display">' + formatted + '</span><small>' + esc( s.unit ) + '</small></div>' +
						'</div>' +
						'<input class="slider" type="range" min="' + s.min + '" max="' + s.max + '" step="' + s.step + '" value="' + val + '" data-action="slider" data-id="' + s.id + '" style="--p:' + pct + '%" aria-label="' + esc( s.title ) + '">' +
						'<div class="ticks"><span>' + ( s.format ? Number( s.min ).toLocaleString( 'nb-NO' ) : s.min ) + ' ' + esc( s.unit ) + '</span><span>' + ( s.format ? Number( s.max ).toLocaleString( 'nb-NO' ) : s.max ) + '+ ' + esc( s.unit ) + '</span></div>' +
					'</div>';

				if ( s.id === 'b' ) {
					h += '<div class="chips-row" style="margin-top:14px;justify-content:center">' +
						[ 12000, 16000, 20000, 30000 ].map( function ( x ) {
							return '<button type="button" class="chip sl' + ( val === x ? ' sel' : '' ) + '" data-action="bpreset" data-val="' + x + '">≈ ' + ( x / 1000 ) + 'k kr</button>';
						} ).join( '' ) +
						'</div>';
				}

				h += renderLiveCard();
			}

			// Dock: Show 'Neste steg' only on slider steps. On choice questions, selection auto-advances.
			if ( s.type === 'choice' ) {
				h += '<div class="dock dock--choice">' +
					'<button type="button" class="btn text btn-back sl" data-action="back" title="Forrige steg">' + ic( 'back', 18 ) + ' <span>Forrige steg</span></button>' +
					'</div>';
			} else {
				h += '<div class="dock">' +
					'<button type="button" class="btn outlined btn-back sl" data-action="back" title="Forrige steg">' + ic( 'back', 20 ) + '</button>' +
					'<button type="button" class="btn filled primary sl" data-action="next"' + ( hasAnswer ? '' : ' disabled' ) + '>' + ( isLast ? 'Se dine anbefalinger' : 'Neste steg' ) + ' ' + ic( 'chevR', 18 ) + '</button>' +
					'</div>';
			}

			return h;
		}

		/* --- Why points helper --- */
		function generateWhyPoints( item, a ) {
			var list = [];
			var pillsDefs = ( config && config.pills ) || [];
			var wt = getSpec( item, 'weight' );
			var sp = getSpec( item, 'speed' );

			// 1. Weight capacity check with safety margin
			if ( wt ) {
				var diff = ( a && a.w ) ? ( wt - a.w ) : 0;
				if ( diff >= 30 ) {
					list.push( { t: 'Tåler ' + wt + ' kg (' + diff + ' kg god sikkerhetsmargin over din vekt)' } );
				} else if ( diff > 0 ) {
					list.push( { t: 'Tåler ' + wt + ' kg (' + diff + ' kg over din vekt)' } );
				} else {
					list.push( { t: 'Maks brukervekt på ' + wt + ' kg' } );
				}
			}

			// 2. Speed / primary metric
			if ( sp ) {
				var speedDesc = sp + ' km/t toppfart';
				if ( a && a.goal === 'walk' ) {
					speedDesc += ' – jevn og stabil gange';
				} else if ( a && a.goal === 'interval' ) {
					speedDesc += ( sp >= 18 ? ' – ideell for høypuls og intervaller' : ' – god for tempo og fartsøkninger' );
				} else if ( a && a.goal ) {
					speedDesc += ( sp >= 15 ? ' – solid for regelmessig løpetrening' : ' – fin for mosjon og lett jogg' );
				}
				list.push( { t: speedDesc } );
			}

			// 3. Dynamic points based on config pills
			pillsDefs.forEach( function ( p ) {
				if ( p.field === 'weight' || p.field === 'speed' ) {
					return;
				}
				var val = getSpec( item, p.field );
				if ( val === null || val === undefined || val === false ) {
					return;
				}
				var text = p.text || String( val );
				if ( typeof val === 'number' || typeof val === 'string' ) {
					text = text.replace( '{v}', val );
				}
				list.push( { t: text } );
			} );

			// 4. Incline or frame notes if not already covered in pills
			var inc = getSpec( item, 'motor_incline' );
			if ( inc && ! pillsDefs.some( function ( p ) { return p.field === 'motor_incline'; } ) ) {
				list.push( { t: 'Motorisert stigning for effektiv motbakketrening' } );
			}

			var fd = getSpec( item, 'foldable' );
			if ( fd !== null && fd !== undefined && ! pillsDefs.some( function ( p ) { return p.field === 'foldable'; } ) ) {
				if ( fd ) {
					list.push( { t: 'Sammenleggbar ramme – enkel å oppbevare' } );
				} else {
					list.push( { t: 'Fast ramme for maksimal stabilitet under hard belastning', neutral: true } );
				}
			}

			// 5. Discount highlight
			var disc = item.discount || ( ( item.old && item.old > item.price ) ? Math.round( ( ( item.old - item.price ) / item.old ) * 100 ) : 0 );
			var save = ( item.old && item.old > item.price ) ? ( item.old - item.price ) : 0;
			if ( disc >= 15 && save > 0 ) {
				list.push( { t: 'Prisavslag på ' + disc + '% (Spar ' + money( save, item.cur ) + ')' } );
			}

			return list.slice( 0, 4 );
		}

		/* --- Results View --- */
		function renderResults() {
			var ranked = Scoring.rank( config, items, filters );
			var results = ranked.items || [];
			var best = results[ 0 ] || null;
			var others = results.slice( 1 );

			var h = renderAppbar( 'Kjøpsveileder', true ) +
				'<div class="head-row" style="margin-top:14px;align-items:center">' +
					'<div style="display:flex;align-items:center;gap:10px">' +
						'<h2 class="headline-s" style="margin:0">Dine anbefalinger</h2>' +
						'<span class="pill tert num">' + results.length + ' treff</span>' +
					'</div>' +
					'<button type="button" class="btn text sl" data-action="how-we-rank" style="height:32px;padding:0 8px;font-size:12.5px;color:var(--md-sys-color-primary,#0C2340);gap:5px;font-weight:600">' +
						ic( 'info', 16 ) + 'Slik rangerer vi' +
					'</button>' +
				'</div>';

			// Active filter criteria chips with clear affordance and edit indication
			h += '<div style="margin-top:14px">' +
				'<div class="overline muted" style="font-size:11.5px;letter-spacing:0.4px;margin-bottom:6px">Dine kriterier · Trykk for å endre:</div>' +
				'<div class="chips-row">';

			steps.forEach( function ( s, idx ) {
				var val = filters[ s.id ];
				if ( val === undefined || val === null || val === 'any' || val === '' ) {
					return;
				}
				var stepNum = idx + 1;
				var chipLabel = '';
				var chipIcon = 'sliders';

				if ( s.type === 'choice' ) {
					var opt = null;
					( s.options || [] ).forEach( function ( o ) {
						if ( o.val === val ) { opt = o; }
					} );
					if ( ! opt ) { return; }
					chipLabel = opt.title;

					if ( val === 'walk' ) { chipIcon = 'speedLow'; }
					else if ( val === 'run' ) { chipIcon = 'speedMid'; }
					else if ( val === 'interval' ) { chipIcon = 'speedHigh'; }
					else if ( val === 'foldable' ) { chipIcon = 'fold'; }
					else if ( val === 'yes' ) { chipIcon = 'incline'; }
					else if ( val === 'no' ) { chipIcon = 'flat'; }
					else if ( val === 'screen' ) { chipIcon = 'screen'; }
					else if ( val === 'app' ) { chipIcon = 'app'; }
					else if ( val === 'rehab' || val === 'soft' ) { chipIcon = 'medical'; }
					else if ( val === 'quiet' ) { chipIcon = 'speedLow'; }
					else if ( val === 'air' || val === 'water' ) { chipIcon = 'speedMid'; }
					else { chipIcon = 'check'; }

					h += '<button type="button" class="chip sl sel" data-action="jump" data-step="' + stepNum + '" title="Trykk for å endre ' + esc( s.title ) + '">' +
						ic( chipIcon, 15 ) + '<span>' + esc( chipLabel ) + '</span><span class="chip-edit">' + ic( 'edit', 12 ) + '</span></button>';
				} else if ( s.type === 'slider' ) {
					if ( s.id === 'b' ) {
						if ( state.presetApplied && val >= ( s.max || 80000 ) ) {
							return;
						}
						chipLabel = 'Maks ' + money( val );
					} else if ( s.id === 'w' ) {
						chipLabel = state.presetApplied ? ( 'Standardvekt (' + val + ' kg)' ) : ( val + ' kg' );
					} else {
						chipLabel = val + ( s.unit ? ' ' + s.unit : '' );
					}

					var sTitle = ( state.presetApplied && s.id === 'w' ) ? 'Trykk for å oppgi din faktiske vekt' : ( 'Trykk for å endre ' + s.title );
					h += '<button type="button" class="chip sl sel" data-action="jump" data-step="' + stepNum + '" title="' + esc( sTitle ) + '">' +
						'<span>' + esc( chipLabel ) + '</span><span class="chip-edit">' + ic( 'edit', 12 ) + '</span></button>';
				}
			} );
			h += '</div></div>';

			if ( state.presetApplied ) {
				h += '<div class="card filled" style="margin-top:12px;padding:12px 16px;background:var(--md-sys-color-primary-container,#EAF0F6);border:1px solid #D5E1ED;display:flex;align-items:center;gap:12px">' +
					'<span style="flex:none;color:var(--md-sys-color-primary,#0C2340)">' + ic( 'info', 18 ) + '</span>' +
					'<span class="body-s" style="color:var(--md-sys-color-on-surface,#0C2340);flex:1">' +
						'<b>Beregnet med standardvekt (' + ( filters.w || 80 ) + ' kg):</b> Veier du mer? <button type="button" class="btn text sl" data-action="jump" data-step="' + getStepIndex( 'w' ) + '" style="padding:0;text-decoration:underline;height:auto;font-size:13px;display:inline;font-weight:600;color:var(--md-sys-color-primary,#0C2340)">Juster vekten din her</button> for å sikre at utstyret har optimal kapasitet og sikkerhetsmargin.' +
					'</span>' +
				'</div>';
			}

			if ( ranked.isFallback && results.length ) {
				h += '<div class="card filled" style="margin-top:14px;background:var(--md-ext-color-warning-container,#FEF3C7);color:var(--md-ext-color-on-warning-container,#92400E);border:1px solid #FDE68A"><b>Ingen perfekt match.</b> Vi har utvidet søket litt slik at du likevel får modeller som dekker vekten din og budsjettet.</div>';
			}

			if ( ! best ) {
				return h + '<div class="card filled xl" style="margin-top:20px;text-align:center"><h3 class="title-l">Ingen modeller matcher</h3><p class="body-m muted" style="margin:8px 0 16px">Prøv å øke budsjettet eller justere kravene.</p><button type="button" class="btn filled sl" data-action="reset">Start på nytt</button></div>';
			}

			// Best Match featured card
			var dDisc = best.discount || 0;
			var whyList = generateWhyPoints( best, filters );

			h += '<article class="card elevated xl" style="margin-top:18px;border:1px solid var(--md-sys-color-outline-variant,#E4E7EC)">' +
				'<div class="head-row">' +
					'<div class="shop">' + renderShopAvatar( best ) + '<div><b>' + esc( cleanShopName( best.shop ) ) + '</b></div></div>' +
					'<span class="pill tert num">' + best.matchPercentage + '% Match</span>' +
				'</div>' +
				'<div class="best-grid">' +
					'<div class="art">' +
						'<span class="pill best badge-top-left">Beste match</span>' +
						( dDisc ? '<span class="pill err disc">−' + dDisc + '%</span>' : '' ) +
						( best.img ? '<img src="' + esc( best.img ) + '" alt="' + esc( best.name ) + '" loading="lazy">' : PLACEHOLDER_SVG ) +
					'</div>' +
					'<div><h3 class="title-l" style="line-height:28px">' + esc( best.name ) + '</h3>' +
						'<div class="specs">' + renderProductPills( config, best, false ) + '</div>' +
						'<div class="note">' + esc( best.note || 'Vår toppanbefaling basert på dine kriterier. Optimal kombinasjon av byggekvalitet, ytelse og pris.' ) + '</div>' +
						'<ul class="why">' +
							whyList.map( function ( x ) {
								return '<li class="' + ( x.neutral ? 'neutral' : '' ) + '">' + ic( x.neutral ? 'minus' : 'check', 18 ) + '<span>' + esc( x.t ) + '</span></li>';
							} ).join( '' ) +
						'</ul>' +
					'</div>' +
				'</div>' +
				'<div class="pricebar row">' +
					'<div class="price">' + ( best.old > best.price ? '<s class="num">' + money( best.old, best.cur ) + '</s>' : '' ) + '<b class="num">' + money( best.price, best.cur ) + '</b></div>' +
					'<div class="actions">' +
						( others.length ? '<button type="button" class="btn outlined sl" data-action="cmp" data-id="' + ( others[ 0 ] ? others[ 0 ].id : '' ) + '">' + ic( 'compare', 18 ) + 'Sammenlign</button>' : '' ) +
						'<a class="btn filled sl" href="' + safeUrl( best.url ) + '" target="_blank" rel="noopener nofollow sponsored">Til butikk' + ic( 'ext', 16 ) + '</a>' +
					'</div>' +
				'</div>' +
				'</article>';

			// Alternatives Grid
			if ( others.length ) {
				h += '<h3 class="title-l" style="margin:28px 0 14px">Gode alternativer innen budsjett</h3><div class="alt-grid">';
				others.forEach( function ( o ) {
					var oDisc = o.discount || 0;

					h += '<div class="card elevated alt"><div>' +
						'<div class="head-row">' +
							'<div class="shop">' + renderShopAvatar( o, true ) + '<div><b>' + esc( cleanShopName( o.shop ) ) + '</b></div></div>' +
							'<span class="pill tert num">' + o.matchPercentage + '% Match</span>' +
						'</div>' +
						'<div class="art" style="height:120px">' +
							( o.isBestOffer ? '<span class="pill deal badge-top-left">Beste tilbud</span>' : '' ) +
							( oDisc ? '<span class="pill err disc">−' + oDisc + '%</span>' : '' ) +
							( o.img ? '<img src="' + esc( o.img ) + '" alt="' + esc( o.name ) + '" loading="lazy">' : PLACEHOLDER_SVG ) +
						'</div>' +
						'<h4>' + esc( o.name ) + '</h4>' +
						'<div class="specs">' + renderProductPills( config, o, true ) + '</div></div>' +
						'<div class="pricebar"><div class="price">' + ( o.old > o.price ? '<s class="num">' + money( o.old, o.cur ) + '</s>' : '' ) + '<b class="num" style="font-size:22px;line-height:28px">' + money( o.price, o.cur ) + '</b></div>' +
						'<div class="actions"><button type="button" class="btn outlined sl" data-action="cmp" data-id="' + o.id + '">' + ic( 'compare', 18 ) + 'Sammenlign</button><a class="btn filled sl" href="' + safeUrl( o.url ) + '" target="_blank" rel="noopener nofollow sponsored">Butikk' + ic( 'ext', 16 ) + '</a></div></div></div>';
				} );
				h += '</div>';
			} else {
				h += '<div class="card filled" style="margin-top:20px;padding:16px 20px;text-align:left;background:var(--md-sys-color-surface-container-low,#F5F7FA);border:1px solid var(--md-sys-color-outline-variant,#E4E7EC)">' +
					'<div style="font-weight:600;color:var(--md-sys-color-primary,#0C2340);margin-bottom:4px">Kun 1 modell oppfyller alle dine krav</div>' +
					'<p class="body-s muted">Dette er den eneste modellen i vårt utvalg som matcher alle dine spesifikke valg (inkludert budsjett på ' + money( filters.b || 35000 ) + ( filters.w ? ' og vektkapasitet på ' + filters.w + ' kg' : '' ) + ').</p>' +
					'<div style="margin-top:10px"><button type="button" class="btn outlined sl" style="height:36px;font-size:13px;padding:0 14px" data-action="jump" data-step="' + getStepIndex( 'b' ) + '">Juster budsjett eller krav</button></div>' +
					'</div>';
			}

			// Disclosure and restart button
			h += '<div class="card outlined" style="margin-top:24px;display:flex;gap:12px;font-size:13px;line-height:19px;color:var(--md-sys-color-on-surface-variant,#475467)">' +
				'<span style="flex:none;color:var(--md-sys-color-primary,#0C2340)">' + ic( 'info', 20 ) + '</span>' +
				'<span><b style="color:var(--md-sys-color-primary,#0C2340)">Uavhengig veiledning:</b> Rangeringen beregnes algoritmisk. Lenkene til nettbutikkene er annonselenker som gir oss en liten provisjon, uten ekstra kostnad for deg.</span>' +
				'</div>' +
				'<div style="display:flex;justify-content:center;margin-top:16px"><button type="button" class="btn text sl" data-action="reset">' + ic( 'refresh', 18 ) + 'Start på nytt</button></div>';

			return h;
		}

		/* --- Deals View (Dagens tilbud) --- */
		function renderDeals() {
			var deals = getDeals();
			var best = deals[ 0 ] || null;
			var others = deals.slice( 1 );

			var h = renderAppbar( 'Dagens tilbud', true ) +
				'<div class="head-row" style="margin-top:14px;align-items:center">' +
					'<div style="display:flex;align-items:center;gap:10px">' +
						'<h2 class="headline-s" style="margin:0">Dagens beste tilbud</h2>' +
						'<span class="pill warn num" style="font-weight:700">' + deals.length + ' tilbud</span>' +
					'</div>' +
					'<button type="button" class="btn filled primary sl" data-action="start" style="height:32px;padding:0 12px;font-size:12.5px;gap:5px;font-weight:600">' +
						'Start kjøpsveileder' + ic( 'chevR', 14 ) +
					'</button>' +
				'</div>';

			// Context tags and interactive sort toggles
			var isAmountSort = state.dealSort === 'amount';
			h += '<div style="margin-top:14px">' +
				'<div class="chips-row">' +
					'<span class="chip sl" style="cursor:default;gap:6px">' + ic( 'tag', 15 ) + 'Kun nedsatte modeller</span>' +
					'<button type="button" class="chip sl' + ( ! isAmountSort ? ' sel' : '' ) + '" data-action="deal-sort" data-sort="pct" title="Sorter etter prosentvis prisavslag">' +
						'% Størst rabatt' +
					'</button>' +
					'<button type="button" class="chip sl' + ( isAmountSort ? ' sel' : '' ) + '" data-action="deal-sort" data-sort="amount" title="Sorter etter kroner spart">' +
						'kr Størst kroneavslag' +
					'</button>' +
				'</div></div>';

			// Guide & safety prompt
			h += '<div class="card filled" style="margin-top:16px;background:var(--md-sys-color-primary-container,#EAF0F6);border:1px solid #D5E1ED;display:flex;flex-direction:column;gap:12px">' +
				'<div style="display:flex;align-items:flex-start;gap:12px">' +
					'<span style="flex:none;color:var(--md-sys-color-primary,#0C2340);margin-top:2px">' + ic( 'info', 20 ) + '</span>' +
					'<div style="flex:1">' +
						'<b style="color:var(--md-sys-color-primary,#0C2340);font-size:13.5px;display:block">Usikker på om tilbudet dekker dine behov?</b>' +
						'<span class="body-s muted" style="line-height:19px;display:block;margin-top:2px">' +
							'En god pris er vel og bra, men feil dimensjonering eller for tung belastning kan gi unødig slitasje og dårlig opplevelse. Svar på noen raske spørsmål for å finne den tryggeste modellen for din kroppsvekt og treningsform.' +
						'</span>' +
					'</div>' +
				'</div>' +
				'<div style="display:flex;justify-content:flex-end">' +
					'<button type="button" class="btn outlined sl" data-action="start" style="background:#FFFFFF;height:34px;font-size:13px;padding:0 14px">' +
						'Finn modell for min vekt' + ic( 'chevR', 14 ) +
					'</button>' +
				'</div>' +
			'</div>';

			if ( ! best ) {
				return h + '<div class="card filled xl" style="margin-top:20px;text-align:center"><h3 class="title-l">Ingen aktive tilbud akkurat nå</h3><p class="body-m muted" style="margin:8px 0 16px">Bruk kjøpsveilederen for å finne de beste modellene til ordinær pris.</p><button type="button" class="btn filled sl" data-action="start">Start veileder</button></div>';
			}

			// Featured top deal card
			var bestDisc = best.discount || ( ( best.old && best.old > best.price ) ? Math.round( ( ( best.old - best.price ) / best.old ) * 100 ) : 0 );
			var bestSave = ( best.old && best.old > best.price ) ? ( best.old - best.price ) : 0;
			var topWhyPoints = generateWhyPoints( best, filters );

			var topBadgeLabel = isAmountSort
				? ( 'Dagens største kroneavslag' + ( bestSave ? ' (Spar ' + money( bestSave, best.cur ) + ')' : '' ) )
				: ( 'Dagens største prosentkutt (−' + bestDisc + '%)' );

			var topNoteText = isAmountSort
				? ( bestSave ? 'Du sparer hele ' + money( bestSave, best.cur ) + ' (' + bestDisc + '% rabatt) i forhold til veiledende førpris.' : 'Sterkt nedsatt kampanjepris hos ' + esc( cleanShopName( best.shop ) ) + '.' )
				: ( bestSave ? 'Du sparer ' + money( bestSave, best.cur ) + ' (' + bestDisc + '% rabatt) i forhold til veiledende førpris.' : 'Sterkt nedsatt kampanjepris hos ' + esc( cleanShopName( best.shop ) ) + '.' );

			var topWhyPoint = isAmountSort
				? ( 'Største besparelse i kroner og øre i dag (Spar ' + money( bestSave, best.cur ) + ')' )
				: ( 'Største prosentvise priskutt i dag (−' + bestDisc + '%)' );

			h += '<article class="card elevated xl" style="margin-top:18px;border:1px solid var(--md-sys-color-outline-variant,#E4E7EC)">' +
				'<div class="head-row">' +
					'<div class="shop">' + renderShopAvatar( best ) + '<div><b>' + esc( cleanShopName( best.shop ) ) + '</b></div></div>' +
					'<span class="pill warn num" style="font-weight:700">' + esc( topBadgeLabel ) + '</span>' +
				'</div>' +
				'<div class="best-grid">' +
					'<div class="art">' +
						'<span class="pill deal badge-top-left">Beste tilbud</span>' +
						( bestDisc ? '<span class="pill err disc">−' + bestDisc + '%</span>' : '' ) +
						( best.img ? '<img src="' + esc( best.img ) + '" alt="' + esc( best.name ) + '" loading="lazy">' : PLACEHOLDER_SVG ) +
					'</div>' +
					'<div><h3 class="title-l" style="line-height:28px">' + esc( best.name ) + '</h3>' +
						'<div class="specs">' + renderProductPills( config, best, false ) + '</div>' +
						'<div class="note" style="border-left-color:var(--md-ext-color-on-warning-container,#92400E)">' +
							esc( topNoteText ) +
						'</div>' +
						'<ul class="why">' +
							( bestSave ? '<li>' + ic( 'check', 18 ) + '<span>' + esc( topWhyPoint ) + '</span></li>' : '' ) +
							topWhyPoints.map( function ( x ) {
								return '<li class="' + ( x.neutral ? 'neutral' : '' ) + '">' + ic( x.neutral ? 'minus' : 'check', 18 ) + '<span>' + esc( x.t ) + '</span></li>';
							} ).join( '' ) +
						'</ul>' +
					'</div>' +
				'</div>' +
				'<div class="pricebar row">' +
					'<div class="price">' + ( best.old > best.price ? '<s class="num">' + money( best.old, best.cur ) + '</s>' : '' ) + '<b class="num">' + money( best.price, best.cur ) + '</b>' + ( bestSave ? '<span class="pill err" style="margin-left:6px">Spar ' + money( bestSave, best.cur ) + '</span>' : '' ) + '</div>' +
					'<div class="actions">' +
						( others.length ? '<button type="button" class="btn outlined sl" data-action="cmp" data-id="' + ( others[ 0 ] ? others[ 0 ].id : '' ) + '">' + ic( 'compare', 18 ) + 'Sammenlign</button>' : '' ) +
						'<a class="btn filled sl" href="' + safeUrl( best.url ) + '" target="_blank" rel="noopener nofollow sponsored">Til butikk' + ic( 'ext', 16 ) + '</a>' +
					'</div>' +
				'</div>' +
				'</article>';

			// Other deals grid
			if ( others.length ) {
				h += '<h3 class="title-l" style="margin:28px 0 14px">Flere gode tilbud</h3><div class="alt-grid">';
				others.forEach( function ( o ) {
					var oDisc = o.discount || ( ( o.old && o.old > o.price ) ? Math.round( ( ( o.old - o.price ) / o.old ) * 100 ) : 0 );
					var oSave = ( o.old && o.old > o.price ) ? ( o.old - o.price ) : 0;

					h += '<div class="card elevated alt"><div>' +
						'<div class="head-row">' +
							'<div class="shop">' + renderShopAvatar( o, true ) + '<div><b>' + esc( cleanShopName( o.shop ) ) + '</b></div></div>' +
							( oSave ? '<span class="pill sec num" style="font-weight:600;font-size:11.5px;color:var(--md-sys-color-primary,#0C2340)">Spar ' + money( oSave, o.cur ) + '</span>' : '' ) +
						'</div>' +
						'<div class="art" style="height:120px">' +
							( oDisc ? '<span class="pill err disc">−' + oDisc + '%</span>' : '' ) +
							( o.img ? '<img src="' + esc( o.img ) + '" alt="' + esc( o.name ) + '" loading="lazy">' : PLACEHOLDER_SVG ) +
						'</div>' +
						'<h4>' + esc( o.name ) + '</h4>' +
						'<div class="specs">' + renderProductPills( config, o, true ) + '</div></div>' +
						'<div class="pricebar"><div class="price">' + ( o.old > o.price ? '<s class="num">' + money( o.old, o.cur ) + '</s>' : '' ) + '<b class="num" style="font-size:22px;line-height:28px">' + money( o.price, o.cur ) + '</b></div>' +
						'<div class="actions"><button type="button" class="btn outlined sl" data-action="cmp" data-id="' + o.id + '">' + ic( 'compare', 18 ) + 'Sammenlign</button><a class="btn filled sl" href="' + safeUrl( o.url ) + '" target="_blank" rel="noopener nofollow sponsored">Butikk' + ic( 'ext', 16 ) + '</a></div></div></div>';
				} );
				h += '</div>';
			}

			// Disclosure and restart button
			h += '<div class="card outlined" style="margin-top:24px;display:flex;gap:12px;font-size:13px;line-height:19px;color:var(--md-sys-color-on-surface-variant,#475467)">' +
				'<span style="flex:none;color:var(--md-sys-color-primary,#0C2340)">' + ic( 'info', 20 ) + '</span>' +
				'<span><b style="color:var(--md-sys-color-primary,#0C2340)">Priser og tilbud:</b> Prisene hentes automatisk fra forhandlernes produktfeeder og oppdateres jevnlig. Lenkene er annonselenker som gir oss en provisjon.</span>' +
				'</div>' +
				'<div style="display:flex;justify-content:center;margin-top:16px"><button type="button" class="btn text sl" data-action="reset">' + ic( 'refresh', 18 ) + 'Gå til forsiden</button></div>';

			return h;
		}

		/* --- Comparison Bottom Sheet Modal --- */
		function renderCompareSheet() {
			if ( state.sheet !== 'compare' ) { return ''; }
			var res = state.view === 'deals' ? getDeals() : ( Scoring.rank( config, items, filters ).items || [] );
			var p1 = res[ 0 ] || null;
			var p2 = null;

			if ( state.cmpId ) {
				for ( var i = 0; i < res.length; i++ ) {
					if ( String( res[ i ].id ) === String( state.cmpId ) ) {
						p2 = res[ i ];
						break;
					}
				}
			}
			if ( ! p2 ) {
				p2 = res[ 1 ] || p1;
			}
			if ( ! p1 || ! p2 ) { return ''; }

			function row( label, val1, val2 ) {
				return '<tr><th>' + label + '</th><td>' + val1 + '</td><td>' + val2 + '</td></tr>';
			}

			function formatCompareVal( pDef, item ) {
				var val = getSpec( item, pDef.field );
				if ( val === null || val === undefined || val === '' ) {
					return '–';
				}
				if ( typeof val === 'boolean' ) {
					return val ? 'Ja' : 'Nei';
				}
				var txt = pDef.text || String( val );
				if ( typeof val === 'number' || typeof val === 'string' ) {
					txt = txt.replace( '{v}', val );
				}
				return esc( txt );
			}

			var specRows = '';
			var pillsDefs = ( config && config.pills ) || [];
			if ( pillsDefs.length ) {
				pillsDefs.forEach( function ( pDef ) {
					var rawLabel = pDef.label || ( pDef.text ? pDef.text.replace( /:\s*\{v\}.*$/, '' ).replace( /\{v\}%?\s*/, '' ) : pDef.field );
					var label = rawLabel.charAt( 0 ).toUpperCase() + rawLabel.slice( 1 );
					specRows += row( label, formatCompareVal( pDef, p1 ), formatCompareVal( pDef, p2 ) );
				} );
			} else {
				var p1Sp = getSpec( p1, 'speed' );
				var p2Sp = getSpec( p2, 'speed' );
				var p1Wt = getSpec( p1, 'weight' );
				var p2Wt = getSpec( p2, 'weight' );
				var p1Inc = getSpec( p1, 'motor_incline' );
				var p2Inc = getSpec( p2, 'motor_incline' );
				var p1Fd = getSpec( p1, 'foldable' );
				var p2Fd = getSpec( p2, 'foldable' );
				specRows += row( 'Toppfart', ( p1Sp ? p1Sp + ' km/t' : '–' ), ( p2Sp ? p2Sp + ' km/t' : '–' ) ) +
					row( 'Maks vekt', ( p1Wt ? p1Wt + ' kg' : '–' ), ( p2Wt ? p2Wt + ' kg' : '–' ) ) +
					row( 'Stigning', ( p1Inc ? 'Motorisert' : 'Manuell / flat' ), ( p2Inc ? 'Motorisert' : 'Manuell / flat' ) ) +
					row( 'Folding', ( p1Fd ? 'Sammenleggbar' : 'Fast ramme' ), ( p2Fd ? 'Sammenleggbar' : 'Fast ramme' ) );
			}

			var metricRow = state.view === 'deals'
				? row( 'Rabatt', '<span class="pill err num">−' + ( p1.discount || Math.round( ( ( p1.old - p1.price ) / p1.old ) * 100 ) || 0 ) + '%</span>', '<span class="pill err num">−' + ( p2.discount || Math.round( ( ( p2.old - p2.price ) / p2.old ) * 100 ) || 0 ) + '%</span>' )
				: row( 'Match', '<span class="pill tert">' + ( p1.matchPercentage || '–' ) + '%</span>', '<span class="pill sec">' + ( p2.matchPercentage || '–' ) + '%</span>' );

			return '<div class="scrim" data-action="close-sheet"></div>' +
				'<div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ge-cmp-title">' +
					'<div class="grabber"></div>' +
					'<div class="head-row" style="margin-bottom:8px">' +
						'<h3 class="title-l" id="ge-cmp-title" style="margin:0">Sammenlign spesifikasjoner</h3>' +
						'<button type="button" class="ibtn tonal sl" data-action="close-sheet" aria-label="Lukk">' + ic( 'close', 18 ) + '</button>' +
					'</div>' +
					'<table class="cmp"><tbody>' +
						row( 'Modell', '<b>' + esc( p1.name ) + '</b>', '<b>' + esc( p2.name ) + '</b>' ) +
						row( 'Forhandler', esc( cleanShopName( p1.shop ) ), esc( cleanShopName( p2.shop ) ) ) +
						metricRow +
						row( 'Pris', '<b class="num">' + money( p1.price, p1.cur ) + '</b>', '<b class="num">' + money( p2.price, p2.cur ) + '</b>' ) +
						specRows +
					'</tbody></table>' +
					'<div style="display:flex;gap:10px;margin-top:22px;justify-content:flex-end">' +
						'<button type="button" class="btn text sl" data-action="close-sheet">Lukk</button>' +
						'<a class="btn filled sl" href="' + safeUrl( p1.url ) + '" target="_blank" rel="noopener nofollow sponsored">Til ' + esc( cleanShopName( p1.shop ) ) + ic( 'ext', 16 ) + '</a>' +
					'</div>' +
				'</div>';
		}

		/* --- Ranking Transparency Bottom Sheet Modal --- */
		function renderRankingSheet() {
			return '<div class="scrim" data-action="close-sheet"></div>' +
				'<div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ge-rank-title">' +
					'<div class="grabber"></div>' +
					'<div class="head-row" style="margin-bottom:14px">' +
						'<h3 class="title-l" id="ge-rank-title" style="margin:0">Slik fungerer rangeringen</h3>' +
						'<button type="button" class="ibtn tonal sl" data-action="close-sheet" aria-label="Lukk">' + ic( 'close', 18 ) + '</button>' +
					'</div>' +
					'<div class="rank-guide" style="display:flex;flex-direction:column;gap:16px;color:var(--md-sys-color-on-surface,#0C2340)">' +
						'<div style="display:flex;gap:12px">' +
							'<span style="flex:none;color:var(--md-sys-color-tertiary,#005236);margin-top:2px">' + ic( 'check', 20 ) + '</span>' +
							'<div>' +
								'<b style="display:block;margin-bottom:2px">1. Faglige sikkerhetsmarginer først</b>' +
								'<span class="body-s muted" style="line-height:19px;display:block">Vi beregner motorkraft, toppfart og legger automatisk inn en sikkerhetsmargin på 10–20 kg over din oppgitte kroppsvekt så motoren ikke overbelastes under hard bruk.</span>' +
							'</div>' +
						'</div>' +
						'<div style="display:flex;gap:12px">' +
							'<span style="flex:none;color:var(--md-sys-color-primary,#0C2340);margin-top:2px">' + ic( 'sliders', 20 ) + '</span>' +
							'<div>' +
								'<b style="display:block;margin-bottom:2px">2. Laveste dagspris vinner ved lik match</b>' +
								'<span class="body-s muted" style="line-height:19px;display:block">Dersom to modeller har identisk matchprosent eller flere nettbutikker fører samme modell, rangeres alltid forhandleren med lavest dagspris øverst.</span>' +
							'</div>' +
						'</div>' +
						'<div style="display:flex;gap:12px">' +
							'<span style="flex:none;color:var(--md-ext-color-on-warning-container,#92400E);margin-top:2px">' + ic( 'tag', 20 ) + '</span>' +
							'<div>' +
								'<b style="display:block;margin-bottom:2px">3. Beste tilbud</b>' +
								'<span class="body-s muted" style="line-height:19px;display:block">Merkelappen «Beste tilbud» tildeles modellen innenfor budsjettet ditt med størst reelt prosentvis prisavslag mot veiledende førpris.</span>' +
							'</div>' +
						'</div>' +
						'<div style="display:flex;gap:12px">' +
							'<span style="flex:none;color:var(--md-sys-color-primary,#0C2340);margin-top:2px">' + ic( 'info', 20 ) + '</span>' +
							'<div>' +
								'<b style="display:block;margin-bottom:2px">4. 100 % uavhengig utvelgelse</b>' +
								'<span class="body-s muted" style="line-height:19px;display:block">Rangeringen beregnes rent algoritmisk basert på dine svar. Nettbutikker kan ikke betale for opprykk eller bedre plassering.</span>' +
							'</div>' +
						'</div>' +
					'</div>' +
					'<div style="display:flex;margin-top:22px;justify-content:flex-end">' +
						'<button type="button" class="btn filled primary sl" data-action="close-sheet">Forstått</button>' +
					'</div>' +
				'</div>';
		}

		/* =========================================================
		   Event Delegator
		   ========================================================= */
		function bindEvents( stage ) {
			stage.addEventListener( 'click', function ( e ) {
				var target = e.target.closest( '[data-action]' );
				if ( ! target ) { return; }

				var act = target.getAttribute( 'data-action' );

				if ( act === 'start' ) {
					state.view = 'quiz';
					state.presetApplied = false;
					go( 1, 'forward' );
				} else if ( act === 'deals' ) {
					openDeals();
				} else if ( act === 'deal-sort' ) {
					var sortType = target.getAttribute( 'data-sort' );
					if ( sortType && state.dealSort !== sortType ) {
						state.dealSort = sortType;
						saveSession();
						render();
					}
				} else if ( act === 'back' ) {
					if ( state.view === 'deals' ) {
						state.view = 'quiz';
						go( 0, 'backward' );
					} else if ( ! state.busy && state.step > 0 ) {
						go( state.step - 1, 'backward' );
					}
				} else if ( act === 'next' ) {
					if ( ! state.busy && state.step <= steps.length ) {
						go( state.step + 1, 'forward' );
					}
				} else if ( act === 'reset' ) {
					filters = Scoring.defaults( config );
					state.view = 'quiz';
					state.presetApplied = false;
					clearSession();
					go( 0, 'backward' );
				} else if ( act === 'quick' ) {
					var presetKey = target.getAttribute( 'data-preset' );
					applyPreset( presetKey );
				} else if ( act === 'jump' ) {
					state.view = 'quiz';
					var stepIdx = parseInt( target.getAttribute( 'data-step' ), 10 ) || 0;
					go( stepIdx, 'backward' );
				} else if ( act === 'cmp' ) {
					state.cmpId = target.getAttribute( 'data-id' ) || null;
					state.sheet = 'compare';
					renderSheetOverlay();
				} else if ( act === 'how-we-rank' ) {
					state.sheet = 'ranking';
					renderSheetOverlay();
				} else if ( act === 'close-sheet' ) {
					state.sheet = null;
					renderSheetOverlay();
				} else if ( act === 'bpreset' ) {
					var val = parseInt( target.getAttribute( 'data-val' ), 10 );
					filters.b = val;
					saveSession();
					var slider = stage.querySelector( '.slider' );
					if ( slider ) {
						slider.value = val;
						var sObj = steps[ state.step - 1 ];
						var pct = Math.max( 0, Math.min( 100, ( ( val - sObj.min ) / ( sObj.max - sObj.min ) ) * 100 ) );
						slider.style.setProperty( '--p', pct + '%' );
					}
					var disp = stage.querySelector( '#ge-slider-display' );
					if ( disp ) { disp.textContent = Number( val ).toLocaleString( 'nb-NO' ); }
					stage.querySelectorAll( '[data-action="bpreset"]' ).forEach( function ( b ) {
						b.classList.toggle( 'sel', b.getAttribute( 'data-val' ) === String( val ) );
					} );
					updateLiveElements();
				} else if ( act === 'choice' ) {
					if ( state.busy ) { return; }
					state.busy = true;
					state.presetApplied = false;

					var qId = target.getAttribute( 'data-id' );
					var qVal = target.getAttribute( 'data-val' );
					filters[ qId ] = qVal;

					// Immediate Option A tactile feedback
					stage.querySelectorAll( '.opt' ).forEach( function ( o ) {
						o.classList.remove( 'is-active' );
						o.setAttribute( 'aria-pressed', 'false' );
						var c = o.querySelector( '.ctl' );
						if ( c ) { c.innerHTML = ''; }
					} );
					target.classList.add( 'is-active' );
					target.setAttribute( 'aria-pressed', 'true' );
					var ctl = target.querySelector( '.ctl' );
					if ( ctl ) { ctl.innerHTML = ic( 'check', 14 ); }

					updateLiveElements();

					// Smooth, deliberate pacing (500ms gives user time to see selection register)
					window.setTimeout( function () {
						state.busy = false;
						go( state.step + 1, 'forward' );
					}, 500 );
				}
			} );

			// Slider input handling
			var sliderInput = stage.querySelector( '.slider' );
			if ( sliderInput ) {
				sliderInput.addEventListener( 'input', function () {
					var qId = sliderInput.getAttribute( 'data-id' );
					var numVal = Number( sliderInput.value );
					filters[ qId ] = numVal;
					if ( qId === 'w' ) {
						state.presetApplied = false;
					}

					var sObj = steps[ state.step - 1 ];
					var pct = Math.max( 0, Math.min( 100, ( ( numVal - sObj.min ) / ( sObj.max - sObj.min ) ) * 100 ) );
					sliderInput.style.setProperty( '--p', pct + '%' );

					var disp = stage.querySelector( '#ge-slider-display' );
					if ( disp ) {
						disp.textContent = sObj.format ? Number( numVal ).toLocaleString( 'nb-NO' ) : numVal;
					}

					// Update budget chips selection
					if ( qId === 'b' ) {
						stage.querySelectorAll( '[data-action="bpreset"]' ).forEach( function ( b ) {
							b.classList.toggle( 'sel', b.getAttribute( 'data-val' ) === String( numVal ) );
						} );
					}

					updateLiveElements();
					saveSession();
				} );
			}
		}

		function renderSheetOverlay() {
			var existing = root.querySelector( '.ge-sheet-overlay' );
			if ( ! existing ) {
				existing = document.createElement( 'div' );
				existing.className = 'ge-sheet-overlay';
				root.appendChild( existing );
			}
			if ( state.sheet === 'compare' ) {
				existing.innerHTML = renderCompareSheet();
			} else if ( state.sheet === 'ranking' ) {
				existing.innerHTML = renderRankingSheet();
			} else {
				existing.innerHTML = '';
			}
			existing.querySelectorAll( '[data-action="close-sheet"]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					state.sheet = null;
					renderSheetOverlay();
				} );
			} );
		}

		/* =========================================================
		   Main Render
		   ========================================================= */
		function render() {
			var stage = document.createElement( 'div' );
			stage.className = 'gear-engine-picker__stage gear-engine-picker__stage--' + state.dir;
			stage.tabIndex = -1;

			if ( state.view === 'deals' ) {
				stage.innerHTML = renderDeals();
			} else if ( state.step === 0 ) {
				stage.innerHTML = renderIntro();
			} else if ( state.step <= steps.length ) {
				stage.innerHTML = renderStep();
			} else {
				stage.innerHTML = renderResults();
			}

			bindEvents( stage );
			app.replaceChildren( stage );
			renderSheetOverlay();
		}

		loadSession();
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
