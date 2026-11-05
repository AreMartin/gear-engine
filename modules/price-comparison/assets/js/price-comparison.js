/**
 * Price Comparison Engine - Vanilla JS Client Module.
 *
 * Implements real-time faceted search, category filtering, dual-range
 * price slider, sorting, dynamic merchant/brand pills, and grid/list view toggles.
 *
 * @package GearEngine
 */

(function () {
	'use strict';

	// Fallback dataset matching the Unsplash reference data.
	var fallbackData = [
		{
			id: 1,
			department: "kondisjon",
			category: "Romaskiner",
			brand: "Concept2",
			name: "RowErg PM5 Romaskin Standard",
			currentPrice: 12490,
			originalPrice: 15600,
			discount: 20,
			shop: "Fjellsport",
			otherShops: 3,
			stores: [
				{ shop: "Fjellsport", price: 12490, inStock: true, isBest: true, url: "#deal-fjellsport" },
				{ shop: "Treningspartner", price: 12990, inStock: true, isBest: false, url: "#deal-tp" },
				{ shop: "X-Life", price: 13490, inStock: true, isBest: false, url: "#deal-xlife" },
				{ shop: "Anton Sport", price: 13990, inStock: false, isBest: false, url: "#deal-anton" }
			],
			inStock: true,
			image: "https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Motstand": "Luftmotstand",
				"Brukervekt": "227 kg",
				"Skjerm": "PM5",
				"Plassbesparende": "Ja (delt i 2)",
				"Setehøyde": "36 cm"
			}
		},
		{
			id: 2,
			department: "kondisjon",
			category: "Tredemøller",
			brand: "Sole",
			name: "F85 Tredemølle 2024-modell m/Bluetooth og touchskjerm",
			currentPrice: 24900,
			originalPrice: 29294,
			discount: 15,
			shop: "Treningspartner",
			otherShops: 2,
			stores: [
				{ shop: "Treningspartner", price: 24900, inStock: true, isBest: true, url: "#deal-tp" },
				{ shop: "Gymgrossisten", price: 26490, inStock: true, isBest: false, url: "#deal-gg" },
				{ shop: "Fjellsport", price: 27990, inStock: false, isBest: false, url: "#deal-fjellsport" }
			],
			inStock: true,
			image: "https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Toppfart": "22 km/t",
				"Motor": "4.0 HK",
				"Løpeflate": "56 x 152 cm",
				"Maks stigning": "15 %",
				"Brukervekt": "165 kg",
				"Vekt": "125 kg"
			}
		},
		{
			id: 3,
			department: "styrke",
			category: "Kettlebells",
			brand: "Abilica",
			name: "Competition Kettlebell 16kg",
			currentPrice: 899,
			originalPrice: 1199,
			discount: 25,
			shop: "X-Life",
			otherShops: 1,
			stores: [
				{ shop: "X-Life", price: 899, inStock: true, isBest: true, url: "#deal-xlife" },
				{ shop: "Treningspartner", price: 999, inStock: true, isBest: false, url: "#deal-tp" }
			],
			inStock: true,
			image: "https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Vekt": "16 kg",
				"Type": "Konkurranse (stål)",
				"Grep": "33 mm"
			}
		},
		{
			id: 4,
			department: "styrke",
			category: "Racks og stativer",
			brand: "Titan Life",
			name: "Squat Rack Pro Hjemmegym",
			currentPrice: 3499,
			originalPrice: 4500,
			discount: 22,
			shop: "Treningspartner",
			otherShops: 0,
			inStock: true,
			image: "https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Maksbelastning": "300 kg",
				"Høyde": "180 cm",
				"Bredde": "120 cm",
				"Vekt": "55 kg"
			}
		},
		{
			id: 5,
			department: "styrke",
			category: "Vektstenger",
			brand: "Gymgrossisten",
			name: "Olympisk Vektstang 20kg",
			currentPrice: 1999,
			originalPrice: 2499,
			discount: 20,
			shop: "Gymgrossisten",
			otherShops: 0,
			inStock: true,
			image: "https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Vekt": "20 kg",
				"Lengde": "220 cm",
				"Grepsdiameter": "28 mm",
				"Maksbelastning": "680 kg"
			}
		},
		{
			id: 7,
			department: "restitusjon",
			category: "Massasjepistoler",
			brand: "Therabody",
			name: "Theragun Pro G5",
			currentPrice: 4999,
			originalPrice: 5999,
			discount: 16,
			shop: "Anton Sport",
			otherShops: 0,
			inStock: true,
			image: "https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Slaglengde": "16 mm",
				"Kraft": "27 kg motstandskraft",
				"Batteri": "150 min",
				"Hastighet": "5 trinn"
			}
		},
		{
			id: 8,
			department: "styrke",
			category: "Manualer",
			brand: "Bowflex",
			name: "SelectTech 552i Justerbare Manualer",
			currentPrice: 2999,
			originalPrice: null,
			discount: null,
			shop: "Fjellsport",
			otherShops: 2,
			stores: [
				{ shop: "Fjellsport", price: 2999, inStock: true, isBest: true, url: "#deal-fjellsport" },
				{ shop: "Treningspartner", price: 3299, inStock: true, isBest: false, url: "#deal-tp" },
				{ shop: "Gymgrossisten", price: 3499, inStock: true, isBest: false, url: "#deal-gg" }
			],
			inStock: true,
			image: "https://images.unsplash.com/photo-1517502884422-41eaead166d4?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Vektintervall": "2 - 24 kg",
				"Antall trinn": "15",
				"Plassbesparende": "Ja"
			}
		},
		{
			id: 9,
			department: "rehabilitering",
			category: "Knestøtter",
			brand: "Bauerfeind",
			name: "GenuTrain Knestøtte Aktiv V2",
			currentPrice: 849,
			originalPrice: 1099,
			discount: 22,
			shop: "Fjellsport",
			otherShops: 1,
			inStock: true,
			image: "https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Type": "Aktiv støtte",
				"Neopren": "Nei",
				"Sideavstivere": "Ja"
			}
		},
		{
			id: 10,
			department: "rehabilitering",
			category: "Treningsstrikker",
			brand: "Abilica",
			name: "Rubber Band Treningsstrikk 3-pk",
			currentPrice: 249,
			originalPrice: 349,
			discount: 28,
			shop: "Treningspartner",
			otherShops: 0,
			inStock: true,
			image: "https://images.unsplash.com/photo-1598289431512-b97b0917affc?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Motstand": "Lett, Medium, Hard",
				"Materiale": "Lateksfritt gummi",
				"Lengde": "30 cm"
			}
		},
		{
			id: 11,
			department: "restitusjon",
			category: "Foam Rollers",
			brand: "TriggerPoint",
			name: "GRID Foam Roller 33cm",
			currentPrice: 399,
			originalPrice: 499,
			discount: 20,
			shop: "X-Life",
			otherShops: 1,
			stores: [
				{ shop: "X-Life", price: 899, inStock: true, isBest: true, url: "#deal-xlife" },
				{ shop: "Treningspartner", price: 999, inStock: true, isBest: false, url: "#deal-tp" }
			],
			inStock: true,
			image: "https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=400&q=80",
			url: "#",
			specs: {
				"Lengde": "33 cm",
				"Diameter": "14 cm",
				"Maksbelastning": "225 kg",
				"Materiale": "EVA-skum"
			}
		}
	];

	// 4 Faglige Hovedavdelinger med underkategorier
	var DEPARTMENTS = [
		{
			id: "kondisjon",
			name: "Kondisjon",
			categories: ["Alle", "Tredemøller", "Romaskiner", "Treningssykler", "Ellipsemaskiner"]
		},
		{
			id: "styrke",
			name: "Styrke",
			categories: ["Alle", "Manualer", "Vektstenger", "Kettlebells", "Racks og stativer"]
		},
		{
			id: "rehabilitering",
			name: "Rehabilitering",
			categories: ["Alle", "Knestøtter", "Ankelstøtter", "Ryggbelter", "Treningsstrikker"]
		},
		{
			id: "restitusjon",
			name: "Restitusjon",
			categories: ["Alle", "Massasjepistoler", "Foam Rollers", "TENS-apparater"]
		}
	];

	// Deterministisk mapping til avdeling for alle produkter
	function inferDepartment(product) {
		if (product && product.department) {
			return String(product.department).toLowerCase();
		}
		var text = (((product && product.category) || '') + ' ' + ((product && product.name) || '')).toLowerCase();
		if (/tredem[øo]lle|romaskin|sykkel|spinningsykkel|ellipse|ski/i.test(text)) {
			return 'kondisjon';
		}
		if (/manual|dumbbell|vektstang|barbell|kettlebell|rack|stativ|vektplate|benk/i.test(text)) {
			return 'styrke';
		}
		if (/knest[øo]tte|ankelst[øo]tte|ryggbelte|st[øo]tte|strikk|powerband|rehab/i.test(text)) {
			return 'rehabilitering';
		}
		if (/massasje|theragun|foam|tens|restitusjon/i.test(text)) {
			return 'restitusjon';
		}
		return 'kondisjon';
	}

	// Anti-Fit Rule Engine (Deterministic expert advice)
	function getAntiFitNotes(p) {
		var notes = [];
		var cat = (p.category || '').toLowerCase();
		var specs = p.specs || {};

		// 1. Tredemøller
		if (cat.indexOf('tredemølle') !== -1 || cat.indexOf('tredemolle') !== -1) {
			if (specs['Løpeflate']) {
				var m = specs['Løpeflate'].match(/(\d+)\s*[xX*]\s*(\d+)/);
				if (m) {
					var w = parseInt(m[1], 10);
					var l = parseInt(m[2], 10);
					if (l < 140 || w < 48) {
						notes.push('Vurder lengre løpeflate (>140 cm) hvis du er over 185 cm og skal løpe i høyt tempo.');
					}
				}
			}
			if (specs['Toppfart']) {
				var speed = parseFloat(specs['Toppfart']);
				if (speed > 0 && speed < 18) {
					notes.push('Maksfart under 18 km/t egner seg best for gange og rolig jogg, ikke raske intervaller.');
				}
			}
			if (specs['Vekt']) {
				var weight = parseFloat(specs['Vekt']);
				if (weight >= 90) {
					notes.push('Veier over 90 kg – krever ofte 2 personer ved innbæring og montering.');
				}
			} else if (p.name && p.name.indexOf('F85') !== -1) {
				notes.push('Veier over 100 kg – krever bærehjelp ved mottak og montering.');
			}
		}

		// 2. Romaskiner
		if (cat.indexOf('romaskin') !== -1) {
			if (specs['Motstand'] && specs['Motstand'].toLowerCase().indexOf('luft') !== -1) {
				notes.push('Luftmotstand gir mest dynamisk rotak, men lager mer susing enn magnetbrems.');
			}
			if (specs['Brukervekt']) {
				var maxW = parseFloat(specs['Brukervekt']);
				if (maxW > 0 && maxW < 130) {
					notes.push('Maks brukervekt kan være i snaueste laget for tyngre utøvere.');
				}
			}
		}

		// 3. Vektutstyr / Stativer
		if (cat.indexOf('kettlebell') !== -1 || cat.indexOf('vektstang') !== -1 || cat.indexOf('manual') !== -1 || cat.indexOf('rack') !== -1) {
			if (specs['Type'] && specs['Type'].toLowerCase().indexOf('konkurranse') !== -1) {
				notes.push('Konkurranseformat har faste dimensjoner uansett vekt – ideelt for teknikk, men tar mer plass.');
			} else if (specs['Maksbelastning']) {
				var load = parseFloat(specs['Maksbelastning']);
				if (load >= 300) {
					notes.push('Kraftig stålkonstruksjon – sjekk underlag og gulvbeskyttelse ved tyngre løft.');
				}
			}
		}

		// 4. Skadestøtter: Knestøtter
		if (cat.indexOf('knestøtte') !== -1 || cat.indexOf('knestotte') !== -1) {
			if (specs['Neopren'] === 'Ja') {
				notes.push('Inneholder neopren – vurder pustende strikk ved overfølsomhet eller eksem.');
			}
			if (specs['Støttenivå'] && specs['Støttenivå'].indexOf('Patella') !== -1) {
				notes.push('Spesialutviklet for patellofemorale smerter og hopperkne, ikke egnet for leddbåndskader.');
			}
		}

		// 5. Skadestøtter: Ankelstøtter
		if (cat.indexOf('ankelstøtte') !== -1 || cat.indexOf('ankelstotte') !== -1) {
			notes.push('Snøring og sidestropper bygger litt i bredden – sjekk at skoen har god plass.');
		}

		// 6. Skadestøtter: Ryggbelter
		if (cat.indexOf('ryggbelte') !== -1) {
			if (specs['Neopren'] === 'Ja') {
				notes.push('Inneholder neopren for varmeeffekt – anbefales for kortere økter, ikke kontinuerlig hverdagsbruk.');
			}
			notes.push('Bør kombineres med aktive kjerneøvelser fremfor passiv heldagsbruk.');
		}

		// 7. Massasjepistoler
		if (cat.indexOf('massasje') !== -1) {
			if (specs['Slaglengde']) {
				var stroke = parseFloat(specs['Slaglengde']);
				if (stroke >= 16) {
					notes.push('16 mm slaglengde gir svært dyp behandling – unngå direkte over beinfremspring.');
				} else if (stroke > 0 && stroke < 12) {
					notes.push('Mindre slaglengde gir lett vibrasjonsmassasje, men når ikke like dypt som 16 mm.');
				}
			}
		}

		// 8. Foam Rollers
		if (cat.indexOf('foam') !== -1) {
			notes.push('Fast multi-densitet EVA gir intens trykkbelastning – kan oppleves hard for nybegynnere.');
		}

		// 9. TENS
		if (cat.indexOf('tens') !== -1) {
			notes.push('Må ikke brukes ved pacemaker, metallimplantater i behandlingsområdet eller graviditet.');
		}

		if (notes.length === 0) {
			notes.push('Ingen spesielle forbehold registrert for standard bruk.');
		}

		return notes;
	}

	var QUICK_FILTER_DEFS = [
		{
			id: 'in_stock',
			label: 'Kun på lager',
			department: 'all',
			test: function (p) { return p.inStock === true; }
		},
		{
			id: 'on_sale',
			label: 'På tilbud',
			department: 'all',
			test: function (p) {
				return (p.discount && p.discount > 0) || (p.originalPrice && p.originalPrice > p.currentPrice);
			}
		},
		{
			id: 'intervall',
			label: '20+ km/t',
			department: 'kondisjon',
			test: function (p) {
				return (p.specs && p.specs['Toppfart'] && parseFloat(p.specs['Toppfart']) >= 20) ||
					(p.name && /2[0-9]\s*km/i.test(p.name)) ||
					(p.name && /f85/i.test(p.name));
			}
		},
		{
			id: 'justerbar',
			label: 'Justerbare',
			department: 'styrke',
			test: function (p) {
				return (p.specs && p.specs['Plassbesparende'] === 'Ja') ||
					(p.specs && p.specs['Type'] && p.specs['Type'].indexOf('Justerbar') !== -1) ||
					(p.name && /justerbar/i.test(p.name));
			}
		},
		{
			id: 'neoprenfri',
			label: 'Uten neopren',
			department: 'rehabilitering',
			test: function (p) {
				return p.specs && p.specs['Neopren'] === 'Nei';
			}
		},
		{
			id: 'dypvev',
			label: 'Dypvev',
			department: 'restitusjon',
			test: function (p) {
				return (p.specs && p.specs['Slaglengde'] && parseFloat(p.specs['Slaglengde']) >= 14) ||
					(p.name && /g5|theragun/i.test(p.name));
			}
		}
	];

	function formatPrice(price) {
		if (price === null || price === undefined || isNaN(price)) {
			return '';
		}
		return Number(price).toLocaleString('no-NO') + ',-';
	}

	function escapeHtml(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function initPriceComparison() {
		var root = document.getElementById('fysionett-prissok-root');
		if (!root) return;

		// 1. Parse feed data
		var rawData = root.getAttribute('data-feed');
		var products = fallbackData;
		if (rawData) {
			try {
				var parsed = JSON.parse(decodeURIComponent(rawData));
				if (Array.isArray(parsed) && parsed.length > 0) {
					products = parsed;
				}
			} catch (e) {
				console.error('[Gear Engine] Feil ved parsing av feed:', e);
			}
		}

		// 2. Initial state & URL Parameters
		var urlParams = null;
		try {
			urlParams = new URLSearchParams(window.location.search);
		} catch (e) {}

		var initialDeptAttr = root.getAttribute('data-initial-department') || 'kondisjon';
		var initialCatAttr = root.getAttribute('data-initial-category') || 'Alle';

		var initialDept = (urlParams && urlParams.get('dept')) ? urlParams.get('dept') : initialDeptAttr;
		var initialCat = (urlParams && urlParams.get('cat')) ? urlParams.get('cat') : initialCatAttr;
		var initialQ = (urlParams && urlParams.get('q')) ? urlParams.get('q') : '';

		// If category set without department, infer department from category
		if (initialCat !== 'Alle' && (!urlParams || !urlParams.get('dept')) && !root.getAttribute('data-initial-department')) {
			DEPARTMENTS.forEach(function (d) {
				if (d.categories.indexOf(initialCat) !== -1) {
					initialDept = d.id;
				}
			});
		}

		// Verify initialDept is valid
		var deptExists = DEPARTMENTS.some(function (d) { return d.id === initialDept; });
		if (!deptExists) {
			initialDept = 'kondisjon';
		}

		var initialView = root.getAttribute('data-initial-view') || 'grid';
		var initialLimit = parseInt(root.getAttribute('data-initial-limit'), 10) || 12;

		var state = {
			products: products,
			viewMode: initialView === 'list' ? 'list' : 'grid',
			activeDepartment: initialDept,
			activeCategory: initialCat,
			searchQuery: initialQ,
			activeShops: [],
			activeBrands: [],
			activeQuickFilters: {},
			selectedForCompare: [],
			showOnlyDiffs: false,
			openStores: {},
			minPrice: '',
			maxPrice: '',
			absMinPrice: 0,
			absMaxPrice: 100000,
			sortBy: 'relevant',
			visibleCount: initialLimit,
			showFilters: false
		};

		// Compute initial absolute price boundaries for active department
		var validPrices = products
			.filter(function (p) {
				var d = p.department || inferDepartment(p);
				return d === state.activeDepartment;
			})
			.map(function (p) { return p.currentPrice; })
			.filter(function (p) { return !isNaN(p) && p > 0; });

		if (validPrices.length === 0) {
			validPrices = products
				.map(function (p) { return p.currentPrice; })
				.filter(function (p) { return !isNaN(p) && p > 0; });
		}

		if (validPrices.length > 0) {
			state.absMinPrice = Math.floor(Math.min.apply(null, validPrices));
			state.absMaxPrice = Math.ceil(Math.max.apply(null, validPrices));
		}

		// Render the initial static layout skeleton
		renderSkeleton(root, state);

		// Render Level 1: Main Department Tabs
		function renderDepartmentTabs() {
			var track = root.querySelector('#ge-dept-track');
			if (!track) return;
			track.innerHTML = '';

			DEPARTMENTS.forEach(function (dept) {
				var isActive = state.activeDepartment === dept.id;
				var count = state.products.filter(function (p) {
					var d = p.department || inferDepartment(p);
					return d === dept.id;
				}).length;

				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'ge-dept-btn' + (isActive ? ' is-active' : '');
				btn.setAttribute('data-department', dept.id);
				btn.setAttribute('role', 'tab');
				btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
				btn.innerHTML = '<span>' + escapeHtml(dept.name) + '</span>' +
					'<span class="ge-dept-badge">' + count + '</span>';

				btn.addEventListener('click', function () {
					switchDepartment(dept.id);
				});
				track.appendChild(btn);
			});
		}

		// Render Level 2: Sub-category Chips for Active Department
		function renderCategoryChips() {
			var bar = root.querySelector('#ge-comp-cats-bar');
			if (!bar) return;
			bar.innerHTML = '';

			var currentDeptObj = DEPARTMENTS.filter(function (d) {
				return d.id === state.activeDepartment;
			})[0] || DEPARTMENTS[0];

			var cats = currentDeptObj.categories.slice();

			// Also append any extra product categories for this department from feed
			state.products.forEach(function (p) {
				var d = p.department || inferDepartment(p);
				if (d === state.activeDepartment && p.category && p.category !== 'Annet' && cats.indexOf(p.category) === -1) {
					cats.push(p.category);
				}
			});

			cats.forEach(function (cat) {
				var isActive = state.activeCategory === cat;
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'ge-cat-pill' + (isActive ? ' is-active' : '');
				btn.setAttribute('data-category', cat);
				btn.setAttribute('role', 'tab');
				btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
				btn.textContent = cat;

				btn.addEventListener('click', function () {
					switchCategory(cat);
				});
				bar.appendChild(btn);
			});
		}

		// Render Tier 1: Quick Filter Chips (Exposed on page)
		function renderQuickFilters() {
			var wrap = root.querySelector('#ge-comp-quick-filters');
			if (!wrap) return;
			wrap.innerHTML = '';

			var activeDefs = QUICK_FILTER_DEFS.filter(function (def) {
				return def.department === 'all' || def.department === state.activeDepartment;
			});

			activeDefs.forEach(function (def) {
				var isActive = !!state.activeQuickFilters[def.id];
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'ge-quick-chip' + (isActive ? ' is-active' : '');
				btn.innerHTML = (isActive ? '<span class="ge-quick-chip-check">✓</span> ' : '') + '<span>' + escapeHtml(def.label) + '</span>';
				btn.addEventListener('click', function () {
					if (state.activeQuickFilters[def.id]) {
						delete state.activeQuickFilters[def.id];
					} else {
						state.activeQuickFilters[def.id] = true;
					}
					renderQuickFilters();
					state.visibleCount = initialLimit;
					updateUI();
				});
				wrap.appendChild(btn);
			});
		}

		// Navigation action: Switch Department
		function switchDepartment(deptId) {
			if (state.activeDepartment === deptId) return;
			state.activeDepartment = deptId;
			state.activeCategory = 'Alle';
			state.activeBrands = [];
			state.activeShops = [];
			state.activeQuickFilters = {};
			state.minPrice = '';
			state.maxPrice = '';
			state.visibleCount = initialLimit;

			// Recompute min/max prices for new department
			var deptPrices = state.products
				.filter(function (p) {
					var d = p.department || inferDepartment(p);
					return d === state.activeDepartment;
				})
				.map(function (p) { return p.currentPrice; })
				.filter(function (p) { return !isNaN(p) && p > 0; });

			if (deptPrices.length > 0) {
				state.absMinPrice = Math.floor(Math.min.apply(null, deptPrices));
				state.absMaxPrice = Math.ceil(Math.max.apply(null, deptPrices));
				if (sliderMin && sliderMax) {
					sliderMin.min = state.absMinPrice;
					sliderMin.max = state.absMaxPrice;
					sliderMax.min = state.absMinPrice;
					sliderMax.max = state.absMaxPrice;
				}
			}

			var deptAlert = root.querySelector('#ge-dept-alert');
			if (deptAlert) {
				deptAlert.style.display = 'none';
				deptAlert.innerHTML = '';
			}

			renderDepartmentTabs();
			renderCategoryChips();
			renderQuickFilters();
			updateUrlState();
			updateUI();
		}

		// Navigation action: Switch Category
		function switchCategory(cat) {
			state.activeCategory = cat;
			state.activeBrands = [];
			state.activeShops = [];
			state.activeQuickFilters = {};
			state.visibleCount = initialLimit;

			var deptAlert = root.querySelector('#ge-dept-alert');
			if (deptAlert) deptAlert.style.display = 'none';

			renderCategoryChips();
			renderQuickFilters();
			updateUrlState();
			updateUI();
		}

		// Hybrid URL State Synchronization (history.pushState)
		function updateUrlState() {
			if (typeof window === 'undefined' || !window.history || !window.history.pushState) return;
			try {
				var url = new URL(window.location.href);
				if (state.activeDepartment && state.activeDepartment !== 'kondisjon') {
					url.searchParams.set('dept', state.activeDepartment);
				} else {
					url.searchParams.delete('dept');
				}

				if (state.activeCategory && state.activeCategory !== 'Alle') {
					url.searchParams.set('cat', state.activeCategory);
				} else {
					url.searchParams.delete('cat');
				}

				if (state.searchQuery && state.searchQuery.trim() !== '') {
					url.searchParams.set('q', state.searchQuery.trim());
				} else {
					url.searchParams.delete('q');
				}

				if (url.toString() !== window.location.href) {
					window.history.pushState({
						dept: state.activeDepartment,
						cat: state.activeCategory,
						q: state.searchQuery
					}, '', url.toString());
				}
			} catch (e) {}
		}

		renderDepartmentTabs();
		renderCategoryChips();
		renderQuickFilters();

		var quickFiltersWrap = root.querySelector('#ge-comp-quick-filters');
		var quickFiltersSlot = root.querySelector('#ge-comp-quick-filters-slot');
		var quickFiltersPanelSlot = root.querySelector('#ge-comp-quick-filters-panel-slot');
		function syncQuickFiltersPlacement() {
			if (!quickFiltersWrap || !quickFiltersSlot || !quickFiltersPanelSlot) return;
			var target = root.clientWidth <= 599 ? quickFiltersPanelSlot : quickFiltersSlot;
			if (quickFiltersWrap.parentNode !== target) {
				target.appendChild(quickFiltersWrap);
			}
		}
		syncQuickFiltersPlacement();
		if (typeof ResizeObserver !== 'undefined') {
			new ResizeObserver(syncQuickFiltersPlacement).observe(root);
		} else {
			window.addEventListener('resize', syncQuickFiltersPlacement);
		}

		// Cache DOM nodes for smooth targeted updates
		var searchInput = root.querySelector('.ge-comp-search-input');
		if (searchInput && state.searchQuery) {
			searchInput.value = state.searchQuery;
		}
		var sortSelect = root.querySelector('.ge-comp-sort-select');
		var filterToggleBtn = root.querySelector('.ge-comp-filter-toggle');
		var filterBadge = root.querySelector('#ge-comp-filter-badge');
		var viewGridBtn = root.querySelector('#ge-view-grid-btn');
		var viewListBtn = root.querySelector('#ge-view-list-btn');
		var filterPanel = root.querySelector('#ge-comp-filter-panel');
		var filterScrim = root.querySelector('#ge-comp-filter-scrim');
		var filterCloseBtn = root.querySelector('#ge-filter-close-btn');
		var filterHost = root.closest('.ge-price-comp-wrapper') || root;
		var priceMinInput = root.querySelector('#ge-price-min-input');
		var priceMaxInput = root.querySelector('#ge-price-max-input');
		var sliderMin = root.querySelector('#ge-slider-min');
		var sliderMax = root.querySelector('#ge-slider-max');
		var sliderFillBar = root.querySelector('#ge-slider-fill-bar');
		var brandsWrap = root.querySelector('#ge-filter-brands-wrap');
		var shopsWrap = root.querySelector('#ge-filter-shops-wrap');
		var filterResetBtn = root.querySelector('#ge-filter-reset-btn');
		var filterApplyBtn = root.querySelector('#ge-filter-apply-btn');
		var resultsCount = root.querySelector('#ge-comp-results-count');
		var cardsContainer = root.querySelector('#ge-comp-cards-container');
		var loadMoreWrap = root.querySelector('#ge-comp-load-more-wrap');

		// Comparison Dock & Modal DOM nodes
		var floatingDock = document.getElementById('ge-floating-cmp-bar');
		var cmpModal = document.getElementById('ge-cmp-modal');

		if (!floatingDock) {
			var dockEl = document.createElement('aside');
			dockEl.id = 'ge-floating-cmp-bar';
			dockEl.className = 'ge-comp-dock';
			dockEl.style.display = 'none';
			dockEl.setAttribute('aria-label', 'Sammenligningsdokk');
			dockEl.innerHTML = '<div class="ge-dock-info">' +
				'<span class="ge-dock-count"><b id="ge-cmp-selected-count">0</b> modeller valgt (maks 3)</span>' +
				'<div class="ge-dock-thumbs" id="ge-dock-thumbs"></div>' +
			'</div>' +
			'<div class="ge-dock-actions">' +
				'<button type="button" id="ge-cmp-open-modal-btn" class="ge-dock-btn">Sammenlign nå (<span id="ge-dock-btn-count">0</span>)</button>' +
				'<button type="button" id="ge-cmp-clear-btn" class="ge-dock-clear-btn">Nullstill</button>' +
			'</div>';
			root.appendChild(dockEl);
			floatingDock = dockEl;
		}

		if (!cmpModal) {
			var modalEl = document.createElement('div');
			modalEl.id = 'ge-cmp-modal';
			modalEl.className = 'ge-cmp-modal-scrim';
			modalEl.style.display = 'none';
			modalEl.setAttribute('role', 'dialog');
			modalEl.setAttribute('aria-modal', 'true');
			modalEl.setAttribute('aria-labelledby', 'ge-cmp-modal-title');
			modalEl.innerHTML = '<div class="ge-cmp-modal-card">' +
				'<div class="ge-cmp-modal-head">' +
					'<div>' +
						'<h3 id="ge-cmp-modal-title" class="ge-cmp-modal-title">Sammenlign modeller side-om-side</h3>' +
						'<span class="ge-cmp-modal-subtitle">Strukturerte nøkkeltall og prissammenligning for de valgte modellene</span>' +
					'</div>' +
					'<div class="ge-cmp-modal-controls">' +
						'<label class="ge-cmp-diff-toggle" title="Skjul spesifikasjoner der modellene har like verdier">' +
							'<input type="checkbox" id="ge-cmp-diff-only" class="ge-cmp-diff-checkbox" />' +
							'<span>Vis kun ulikheter</span>' +
						'</label>' +
						'<button type="button" id="ge-modal-close-btn" class="ge-cmp-modal-close" title="Lukk sammenligning" aria-label="Lukk">&times;</button>' +
					'</div>' +
				'</div>' +
				'<div id="ge-cmp-modal-body" class="ge-cmp-modal-body"></div>' +
			'</div>';
			root.appendChild(modalEl);
			cmpModal = modalEl;
		}

		var dockSelectedCount = document.getElementById('ge-cmp-selected-count');
		var dockThumbs = document.getElementById('ge-dock-thumbs');
		var dockBtnCount = document.getElementById('ge-dock-btn-count');
		var dockOpenModalBtn = document.getElementById('ge-cmp-open-modal-btn');
		var dockClearBtn = document.getElementById('ge-cmp-clear-btn');
		var cmpModalBody = document.getElementById('ge-cmp-modal-body');
		var modalCloseBtn = document.getElementById('ge-modal-close-btn');
		var diffOnlyChk = document.getElementById('ge-cmp-diff-only');

		function updateDock() {
			if (!floatingDock) return;
			var count = state.selectedForCompare.length;
			if (count > 0) {
				floatingDock.style.display = 'flex';
				if (dockSelectedCount) dockSelectedCount.textContent = count;
				if (dockBtnCount) dockBtnCount.textContent = count;
				if (dockThumbs) {
					dockThumbs.innerHTML = '';
					state.selectedForCompare.forEach(function (id) {
						var p = state.products.filter(function (item) { return item.id === id; })[0];
						if (p && p.image) {
							var span = document.createElement('span');
							span.className = 'ge-dock-thumb';
							span.title = p.name;
							span.innerHTML = '<img src="' + escapeHtml(p.image) + '" alt="' + escapeHtml(p.name) + '" onerror="this.style.display=\'none\';" />';
							dockThumbs.appendChild(span);
						}
					});
				}
			} else {
				floatingDock.style.display = 'none';
			}
		}

		function renderCompareMatrix() {
			if (!cmpModalBody) return;

			var selected = state.products.filter(function (p) {
				return state.selectedForCompare.indexOf(p.id) !== -1;
			});

			if (selected.length === 0) {
				if (cmpModal) {
					cmpModal.style.display = 'none';
					document.body.style.overflow = '';
				}
				return;
			}

			var prices = selected.map(function (p) { return p.currentPrice; });
			var minPrice = Math.min.apply(null, prices);
			var maxPrice = Math.max.apply(null, prices);
			var priceDiff = maxPrice - minPrice;
			var onlyDiff = !!state.showOnlyDiffs;

			// Sync checkbox state in modal header
			if (diffOnlyChk) {
				diffOnlyChk.checked = onlyDiff;
				var toggleLabel = diffOnlyChk.closest('.ge-cmp-diff-toggle');
				if (toggleLabel) {
					if (onlyDiff) {
						toggleLabel.classList.add('is-active');
					} else {
						toggleLabel.classList.remove('is-active');
					}
				}
			}

			// Collect unique spec keys across selected
			var specKeys = [];
			selected.forEach(function (p) {
				if (p.specs) {
					for (var k in p.specs) {
						if (p.specs.hasOwnProperty(k) && specKeys.indexOf(k) === -1) {
							specKeys.push(k);
						}
					}
				}
			});

			var colWidth = selected.length <= 2 ? 'calc(' + (100 / selected.length) + '% - ' + (145 / selected.length) + 'px)' : '250px';

			var h = '<div class="ge-matrix-wrap"><table class="ge-matrix-table">';
			h += '<colgroup>';
			h += '<col style="width:145px; min-width:145px;">';
			selected.forEach(function () {
				h += '<col style="width:' + colWidth + '; min-width:220px;">';
			});
			h += '</colgroup>';

			// 1. Header row with cards
			h += '<tr class="matrix-header-row"><th class="matrix-label-col"><span style="font-size:11px; color:#64748b;">Modeller (' + selected.length + ')</span></th>';
			selected.forEach(function (p) {
				var isLowest = selected.length > 1 && p.currentPrice === minPrice;
				h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '">' +
					'<div class="ge-matrix-card-top">' +
						(isLowest ? '<span class="ge-matrix-best-tag">Best pris</span>' : '') +
						'<button type="button" class="ge-matrix-remove-btn" data-remove-id="' + p.id + '" title="Fjern fra sammenligning" aria-label="Fjern">&times;</button>' +
						'<div class="ge-matrix-thumb">' +
							(p.image ? '<img src="' + escapeHtml(p.image) + '" alt="' + escapeHtml(p.name) + '" onerror="this.style.display=\'none\';" />' : '') +
						'</div>' +
						'<span class="ge-matrix-brand">' + escapeHtml(p.brand || 'UTSTYR') + '</span>' +
						'<h4 class="ge-matrix-prod-title" title="' + escapeHtml(p.name) + '">' + escapeHtml(p.name) + '</h4>' +
						'<div class="ge-matrix-price-row">' +
							'<span class="ge-matrix-price">' + formatPrice(p.currentPrice) + '</span>' +
							(p.discount ? '<span class="ge-matrix-spec-pill deal">-' + p.discount + '%</span>' : '') +
						'</div>' +
						'<a href="' + escapeHtml(p.url || '#') + '" target="_blank" rel="noopener noreferrer sponsored" class="ge-matrix-cta">' +
							'<span>Gå til ' + escapeHtml(p.shop || 'butikk') + '</span> ' + externalLinkIcon() +
						'</a>' +
					'</div>' +
				'</td>';
			});
			h += '</tr>';

			// 2. Dagspris / Prissammenligning
			var isPriceIdentical = selected.every(function (p) { return p.currentPrice === selected[0].currentPrice; });
			if (!onlyDiff || !isPriceIdentical) {
				h += '<tr><th class="matrix-label-col">Beste dagspris</th>';
				selected.forEach(function (p) {
					var isLowest = selected.length > 1 && p.currentPrice === minPrice;
					h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '">' +
						'<b style="font-size:15px; color:#0C2340; font-variant-numeric:tabular-nums;">' + formatPrice(p.currentPrice) + '</b>' +
						(isLowest ? ' <span class="ge-matrix-spec-pill tert" style="margin-left:4px;">Best pris</span>' : '') +
					'</td>';
				});
				h += '</tr>';
			}

			// 3. Forhandler
			var isShopIdentical = selected.every(function (p) {
				return p.shop === selected[0].shop && (p.otherShops || 0) === (selected[0].otherShops || 0);
			});
			if (!onlyDiff || !isShopIdentical) {
				h += '<tr><th class="matrix-label-col">Forhandler</th>';
				selected.forEach(function (p) {
					var isLowest = selected.length > 1 && p.currentPrice === minPrice;
					var count = (p.otherShops || 0) + 1;
					h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '">' +
						'<span style="font-weight:600; color:#0C2340;">' + escapeHtml(p.shop || 'Ukjent') + '</span> ' +
						(count > 1 ? '<span style="font-size:11px; color:#64748b;">(' + count + ' butikker)</span>' : '<span style="font-size:11px; color:#059669;">(Eneste forhandler)</span>') +
					'</td>';
				});
				h += '</tr>';
			}

			// 4. Førpris / Tilbud
			var isOrigIdentical = selected.every(function (p) { return (p.originalPrice || 0) === (selected[0].originalPrice || 0); });
			if (!onlyDiff || !isOrigIdentical) {
				h += '<tr><th class="matrix-label-col">Førpris / Tilbud</th>';
				selected.forEach(function (p) {
					var isLowest = selected.length > 1 && p.currentPrice === minPrice;
					if (p.originalPrice && p.originalPrice > p.currentPrice) {
						var save = p.originalPrice - p.currentPrice;
						h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '">' +
							'<s style="color:#94a3b8; font-size:12px; margin-right:4px;">' + formatPrice(p.originalPrice) + '</s>' +
							'<span class="ge-matrix-spec-pill deal">Spar ' + formatPrice(save) + '</span>' +
						'</td>';
					} else {
						h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '"><span style="color:#94a3b8; font-size:12px;">Ordinær pris</span></td>';
					}
				});
				h += '</tr>';
			}

			// 5. Lagerstatus
			var isStockIdentical = selected.every(function (p) { return p.inStock === selected[0].inStock; });
			if (!onlyDiff || !isStockIdentical) {
				h += '<tr><th class="matrix-label-col">Lagerstatus</th>';
				selected.forEach(function (p) {
					var isLowest = selected.length > 1 && p.currentPrice === minPrice;
					h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '">' +
						'<span style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600;">' +
							'<span class="ge-stock-dot ' + (p.inStock ? 'is-in-stock' : 'is-out-of-stock') + '"></span>' +
							(p.inStock ? 'På lager' : 'Utsolgt') +
						'</span>' +
					'</td>';
				});
				h += '</tr>';
			}

			// 6. Kategori
			var isCatIdentical = selected.every(function (p) { return p.category === selected[0].category; });
			if (!onlyDiff || !isCatIdentical) {
				h += '<tr><th class="matrix-label-col">Kategori</th>';
				selected.forEach(function (p) {
					var isLowest = selected.length > 1 && p.currentPrice === minPrice;
					h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '"><span class="ge-matrix-spec-pill sec">' + escapeHtml(p.category || 'Annet') + '</span></td>';
				});
				h += '</tr>';
			}

			// 7. Vær obs på (Anti-Fit)
			var allAntiFitNotes = selected.map(function (p) { return getAntiFitNotes(p).join(' | '); });
			var isAntiFitIdentical = allAntiFitNotes.every(function (n) { return n === allAntiFitNotes[0]; });
			if (!onlyDiff || !isAntiFitIdentical) {
				h += '<tr><th class="matrix-label-col">' +
					'<div style="display:flex; align-items:center; gap:6px; color:#0C2340; font-weight:700;">' +
						'<span style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#f1f5f9; color:#475569; font-size:11px;">ℹ</span>' +
						'<span>Vær obs på</span>' +
					'</div>' +
				'</th>';
				selected.forEach(function (p) {
					var isLowest = selected.length > 1 && p.currentPrice === minPrice;
					var notes = getAntiFitNotes(p);
					var hasRealNotes = notes.length > 0 && notes[0] !== 'Ingen spesielle forbehold registrert for standard bruk.';
					var boxClass = 'ge-antifit-box' + (hasRealNotes ? '' : ' is-clean');
					var headerHtml = hasRealNotes
						? '<div class="ge-antifit-header"><span>ℹ</span> <span>Obs</span></div>'
						: '<div class="ge-antifit-header"><span>✓</span> <span>Allround-modell</span></div>';
					var noteHtml = notes.map(function (n) {
						var bullet = hasRealNotes ? '•' : '✓';
						return '<div class="ge-antifit-note">' +
							'<span class="ge-antifit-bullet">' + bullet + '</span>' +
							'<span>' + escapeHtml(n) + '</span>' +
						'</div>';
					}).join('');

					h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '">' +
						'<div class="' + boxClass + '">' +
							headerHtml +
							noteHtml +
						'</div>' +
					'</td>';
				});
				h += '</tr>';
			}

			// 8. Dynamic Specs (med vinner-merking for målbare numeriske verdier)
			var higherBetterKeys = ['Toppfart', 'Motor', 'Brukervekt', 'Slaglengde', 'Maksbelastning', 'Batteri'];
			specKeys.forEach(function (key) {
				var isHigherBetter = higherBetterKeys.indexOf(key) !== -1;
				var val0 = (selected[0].specs && selected[0].specs[key]) ? selected[0].specs[key] : '—';
				var isSpecIdentical = selected.every(function (p) {
					var v = (p.specs && p.specs[key]) ? p.specs[key] : '—';
					return v === val0;
				});

				if (onlyDiff && isSpecIdentical) return;

				var maxNum = -1;
				if (isHigherBetter && selected.length > 1) {
					selected.forEach(function (p) {
						var v = (p.specs && p.specs[key]) ? p.specs[key] : null;
						if (v) {
							var num = parseFloat(v);
							if (!isNaN(num) && num > maxNum) maxNum = num;
						}
					});
					// Only award if there is actual variation
					var hasDiff = selected.some(function (p) {
						var v = (p.specs && p.specs[key]) ? p.specs[key] : null;
						return v && parseFloat(v) !== maxNum;
					});
					if (!hasDiff) maxNum = -1;
				}

				h += '<tr><th class="matrix-label-col">' + escapeHtml(key) + '</th>';
				selected.forEach(function (p) {
					var isLowest = selected.length > 1 && p.currentPrice === minPrice;
					var rawVal = (p.specs && p.specs[key]) ? escapeHtml(p.specs[key]) : '<span style="color:#94a3b8; font-weight:normal;">—</span>';
					var isWin = false;
					if (maxNum > -1 && p.specs && p.specs[key]) {
						var parsed = parseFloat(p.specs[key]);
						if (!isNaN(parsed) && parsed === maxNum) isWin = true;
					}

					h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '" style="font-weight:600; color:#0C2340;">' +
						rawVal + (isWin ? ' <span class="ge-spec-win">Høyest</span>' : '') +
					'</td>';
				});
				h += '</tr>';
			});

			// 9. Bunnrad med handlingsknapp
			h += '<tr style="background:#f8fafc;"><th class="matrix-label-col" style="background:#f8fafc;">Handling</th>';
			selected.forEach(function (p) {
				var isLowest = selected.length > 1 && p.currentPrice === minPrice;
				h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '" style="' + (isLowest ? 'background:#F1F8F4;' : 'background:#f8fafc;') + '">' +
					'<a href="' + escapeHtml(p.url || '#') + '" target="_blank" rel="noopener noreferrer sponsored" class="ge-matrix-cta" style="height:34px; font-size:12px;">' +
						'<span>Se tilbud</span> ' + externalLinkIcon() +
					'</a>' +
				'</td>';
			});
			h += '</tr>';

			h += '</table></div>';

			// Summary footer
			if (selected.length > 1 && priceDiff > 0) {
				h += '<div class="ge-matrix-summary">' +
					'<span>Prisspenn i sammenligningen: <b>' + formatPrice(minPrice) + '</b> – <b>' + formatPrice(maxPrice) + '</b></span>' +
					'<span class="ge-matrix-summary-diff">Prisforskjell: ' + formatPrice(priceDiff) + '</span>' +
				'</div>';
			}

			cmpModalBody.innerHTML = h;

			var compareTable = cmpModalBody.querySelector('.ge-matrix-table');
			if (compareTable) {
				var tableRows = compareTable.querySelectorAll('tr');
				var mobileList = document.createElement('div');
				mobileList.className = 'ge-mobile-compare-list';
				mobileList.setAttribute('aria-label', 'Sammenligning per modell');

				selected.forEach(function (product, productIndex) {
					var productCell = tableRows[0].cells[productIndex + 1];
					var productHeader = productCell ? productCell.querySelector('.ge-matrix-card-top') : null;
					if (!productHeader) return;

					var specRows = [];
					for (var rowIndex = 1; rowIndex < tableRows.length; rowIndex++) {
						var row = tableRows[rowIndex];
						if (row.classList.contains('matrix-action-row')) continue;

						var labelCell = row.cells[0];
						var valueCell = row.cells[productIndex + 1];
						if (!labelCell || !valueCell) continue;

						var specRow = document.createElement('div');
						specRow.className = 'ge-mobile-spec-row';
						var label = document.createElement('dt');
						label.innerHTML = labelCell.innerHTML;
						var value = document.createElement('dd');
						value.innerHTML = valueCell.innerHTML;
						specRow.appendChild(label);
						specRow.appendChild(value);
						specRows.push(specRow);
					}

					var card = document.createElement('section');
					card.className = 'ge-mobile-product-card';
					card.setAttribute('aria-label', 'Sammenligning: ' + product.name);

					var header = document.createElement('div');
					header.className = 'ge-mobile-product-head';
					header.appendChild(productHeader.cloneNode(true));
					card.appendChild(header);

					if (specRows.length) {
						var specs = document.createElement('dl');
						specs.className = 'ge-mobile-product-specs';
						specRows.forEach(function (specRow) { specs.appendChild(specRow); });
						card.appendChild(specs);
					} else {
						var empty = document.createElement('p');
						empty.className = 'ge-mobile-empty-specs';
						empty.textContent = 'Ingen spesifikasjoner å vise med valgt filter.';
						card.appendChild(empty);
					}

					mobileList.appendChild(card);
				});

				var matrixWrap = compareTable.closest('.ge-matrix-wrap');
				if (matrixWrap && mobileList.childElementCount) {
					matrixWrap.parentNode.insertBefore(mobileList, matrixWrap.nextSibling);
				}
			}

			// Attach remove button listeners
			cmpModalBody.querySelectorAll('.ge-matrix-remove-btn').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var id = parseInt(btn.getAttribute('data-remove-id'), 10);
					var idx = state.selectedForCompare.indexOf(id);
					if (idx !== -1) {
						state.selectedForCompare.splice(idx, 1);
					}
					var chk = root.querySelector('.ge-comp-chk[data-id="' + id + '"]');
					if (chk) chk.checked = false;
					updateDock();
					renderCompareMatrix();
				});
			});
		}

		// Main update function
		function updateUI(options) {
			options = options || {};

			// Cross-Department Search Alert Check
			var deptAlert = root.querySelector('#ge-dept-alert');
			if (deptAlert) {
				var trimmedQ = state.searchQuery.trim().toLowerCase();
				if (trimmedQ.length >= 2) {
					var otherDeptMatches = {};
					state.products.forEach(function (p) {
						var d = p.department || inferDepartment(p);
						if (d !== state.activeDepartment) {
							var sTarget = (p.name + ' ' + (p.brand || '') + ' ' + (p.category || '')).toLowerCase();
							if (sTarget.indexOf(trimmedQ) !== -1) {
								otherDeptMatches[d] = (otherDeptMatches[d] || 0) + 1;
							}
						}
					});

					// Check current department matches with search query
					var currentDeptMatches = state.products.filter(function (p) {
						var d = p.department || inferDepartment(p);
						if (d !== state.activeDepartment) return false;
						var sTarget = (p.name + ' ' + (p.brand || '') + ' ' + (p.category || '')).toLowerCase();
						return sTarget.indexOf(trimmedQ) !== -1;
					}).length;

					var otherDepts = Object.keys(otherDeptMatches);
					if (currentDeptMatches === 0 && otherDepts.length > 0) {
						otherDepts.sort(function (a, b) { return otherDeptMatches[b] - otherDeptMatches[a]; });
						var targetDeptId = otherDepts[0];
						var targetDeptObj = DEPARTMENTS.filter(function (d) { return d.id === targetDeptId; })[0];
						var targetDeptName = targetDeptObj ? targetDeptObj.name : targetDeptId;
						var matchCount = otherDeptMatches[targetDeptId];

						deptAlert.innerHTML = '<span>Fant <b>' + matchCount + ' treff</b> i <b>' + escapeHtml(targetDeptName) + '</b> for &laquo;' + escapeHtml(state.searchQuery.trim()) + '&raquo;</span>' +
							'<button type="button" class="ge-dept-switch-btn" id="ge-dept-switch-btn">' +
								'<span>Bytt til ' + escapeHtml(targetDeptName) + '</span>' +
								'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>' +
							'</button>';
						deptAlert.style.display = 'flex';

						var switchBtn = deptAlert.querySelector('#ge-dept-switch-btn');
						if (switchBtn) {
							switchBtn.addEventListener('click', function () {
								switchDepartment(targetDeptId);
							});
						}
					} else {
						deptAlert.style.display = 'none';
						deptAlert.innerHTML = '';
					}
				} else {
					deptAlert.style.display = 'none';
					deptAlert.innerHTML = '';
				}
			}

			// A. Filter logic (Department, Category, Search, Brand, Shop, Price, Quick Filters)
			var baseFiltered = state.products.filter(function (product) {
				var prodDept = product.department || inferDepartment(product);
				var matchesDept = prodDept === state.activeDepartment;
				var searchTarget = (product.name + ' ' + (product.brand || '')).toLowerCase();
				var matchesSearch = searchTarget.indexOf(state.searchQuery.toLowerCase()) !== -1;
				var matchesCategory = state.activeCategory === 'Alle' || product.category === state.activeCategory;
				return matchesDept && matchesSearch && matchesCategory;
			});

			// Dynamic brands & shops based on active department + category + search
			var brandSet = {};
			var shopSet = {};
			baseFiltered.forEach(function (p) {
				if (p.brand && p.brand !== 'Ukjent') brandSet[p.brand.trim()] = true;
				if (p.shop) shopSet[p.shop.trim()] = true;
			});
			var dynamicBrands = ['Alle'].concat(Object.keys(brandSet).sort());
			var dynamicShops = ['Alle'].concat(Object.keys(shopSet).sort());

			// Full filtered products
			var filtered = state.products.filter(function (product) {
				var prodDept = product.department || inferDepartment(product);
				var matchesDept = prodDept === state.activeDepartment;
				var searchTarget = (product.name + ' ' + (product.brand || '')).toLowerCase();
				var matchesSearch = searchTarget.indexOf(state.searchQuery.toLowerCase()) !== -1;
				var matchesCategory = state.activeCategory === 'Alle' || product.category === state.activeCategory;
				var matchesShop = state.activeShops.length === 0 || state.activeShops.indexOf(product.shop) !== -1;
				var matchesBrand = state.activeBrands.length === 0 || state.activeBrands.indexOf(product.brand) !== -1;

				var price = product.currentPrice;
				var minP = state.minPrice === '' ? 0 : parseInt(state.minPrice, 10);
				var maxP = state.maxPrice === '' ? Infinity : parseInt(state.maxPrice, 10);
				var matchesPrice = price >= minP && price <= maxP;

				var matchesQuick = Object.keys(state.activeQuickFilters).every(function (qId) {
					if (!state.activeQuickFilters[qId]) return true;
					var def = QUICK_FILTER_DEFS.filter(function (d) { return d.id === qId; })[0];
					return def ? def.test(product) : true;
				});

				return matchesDept && matchesSearch && matchesCategory && matchesShop && matchesBrand && matchesPrice && matchesQuick;
			});

			// B. Sort logic
			var sorted = filtered.slice().sort(function (a, b) {
				if (state.sortBy === 'price_asc') return a.currentPrice - b.currentPrice;
				if (state.sortBy === 'price_desc') return b.currentPrice - a.currentPrice;
				if (state.sortBy === 'discount_desc') return ((b.discount || 0) - (a.discount || 0)) || (a.currentPrice - b.currentPrice);
				if (state.sortBy === 'savings_desc') {
					var savA = (a.originalPrice && a.originalPrice > a.currentPrice) ? (a.originalPrice - a.currentPrice) : 0;
					var savB = (b.originalPrice && b.originalPrice > b.currentPrice) ? (b.originalPrice - b.currentPrice) : 0;
					return (savB - savA) || (a.currentPrice - b.currentPrice);
				}
				return 0;
			});

			var visibleProducts = sorted.slice(0, state.visibleCount);

			// C. Filter badge count
			var isPriceChanged = (state.minPrice !== '' && Number(state.minPrice) > state.absMinPrice) ||
				(state.maxPrice !== '' && Number(state.maxPrice) < state.absMaxPrice);
			var quickCount = Object.keys(state.activeQuickFilters).length;
			var activeFilterCount = state.activeShops.length + state.activeBrands.length + (isPriceChanged ? 1 : 0) + quickCount;

			if (activeFilterCount > 0) {
				filterBadge.style.display = 'inline-flex';
				filterBadge.textContent = activeFilterCount;
				filterToggleBtn.classList.add('is-active');
			} else {
				filterBadge.style.display = 'none';
				if (!state.showFilters) {
					filterToggleBtn.classList.remove('is-active');
				}
			}

			if (state.showFilters) {
				filterToggleBtn.classList.add('is-active');
			}

			// D. Update Filter Panel Visibility & Sliders
			filterPanel.classList.toggle('is-open', state.showFilters);
			filterScrim.classList.toggle('is-visible', state.showFilters);
			filterPanel.inert = !state.showFilters;
			filterPanel.setAttribute('aria-hidden', state.showFilters ? 'false' : 'true');
			filterToggleBtn.setAttribute('aria-expanded', state.showFilters ? 'true' : 'false');
			document.body.classList.toggle('ge-price-comp-filter-open', state.showFilters);
			filterHost.classList.toggle('ge-price-comp-filter-host-open', state.showFilters);

			var curMin = state.minPrice === '' ? state.absMinPrice : Number(state.minPrice);
			var curMax = state.maxPrice === '' ? state.absMaxPrice : Number(state.maxPrice);
			var minPct = state.absMaxPrice > state.absMinPrice ? ((curMin - state.absMinPrice) / (state.absMaxPrice - state.absMinPrice)) * 100 : 0;
			var maxPct = state.absMaxPrice > state.absMinPrice ? ((curMax - state.absMinPrice) / (state.absMaxPrice - state.absMinPrice)) * 100 : 100;

			sliderFillBar.style.left = minPct + '%';
			sliderFillBar.style.right = (100 - maxPct) + '%';
			sliderMin.value = curMin;
			sliderMax.value = curMax;

			if (!options.fromPriceInput) {
				priceMinInput.value = curMin;
				priceMaxInput.value = curMax;
			}

			// E. Update Brand & Shop Pills
			renderFilterPills(brandsWrap, dynamicBrands, state.activeBrands, function (brand) {
				if (brand === 'Alle') {
					state.activeBrands = [];
				} else {
					var idx = state.activeBrands.indexOf(brand);
					if (idx !== -1) {
						state.activeBrands.splice(idx, 1);
					} else {
						state.activeBrands.push(brand);
					}
				}
				state.visibleCount = initialLimit;
				updateUI();
			}, false);

			renderFilterPills(shopsWrap, dynamicShops, state.activeShops, function (shop) {
				if (shop === 'Alle') {
					state.activeShops = [];
				} else {
					var idx = state.activeShops.indexOf(shop);
					if (idx !== -1) {
						state.activeShops.splice(idx, 1);
					} else {
						state.activeShops.push(shop);
					}
				}
				state.visibleCount = initialLimit;
				updateUI();
			}, true);

			// Update filter apply button text
			filterApplyBtn.innerHTML = 'Vis ' + sorted.length + ' treff <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';

			// F. Results Counter
			resultsCount.textContent = 'Viser ' + visibleProducts.length + ' av ' + sorted.length + ' produkter';

			// G. Render Cards
			cardsContainer.className = state.viewMode === 'grid' ? 'ge-comp-grid' : 'ge-comp-list';

			if (visibleProducts.length > 0) {
				var cardsHtml;
				if (state.viewMode === 'list') {
					var columnSplit = Math.ceil(visibleProducts.length / 2);
					cardsHtml =
						'<div class="ge-comp-list-column">' + visibleProducts.slice(0, columnSplit).map(function (prod) { return renderListCard(prod, state); }).join('') + '</div>' +
						'<div class="ge-comp-list-column">' + visibleProducts.slice(columnSplit).map(function (prod) { return renderListCard(prod, state); }).join('') + '</div>';
				} else {
					cardsHtml = visibleProducts.map(function (prod) { return renderGridCard(prod, state); }).join('');
				}
				cardsContainer.innerHTML = cardsHtml;

				// Attach compare checkbox listeners
				cardsContainer.querySelectorAll('.ge-comp-chk').forEach(function (chk) {
					chk.addEventListener('change', function (e) {
						e.stopPropagation();
						var id = parseInt(chk.getAttribute('data-id'), 10);
						if (chk.checked) {
							if (state.selectedForCompare.length >= 3) {
								alert('Du kan sammenligne maksimalt 3 produkter samtidig.');
								chk.checked = false;
								return;
							}
							if (state.selectedForCompare.indexOf(id) === -1) {
								state.selectedForCompare.push(id);
							}
						} else {
							var idx = state.selectedForCompare.indexOf(id);
							if (idx !== -1) {
								state.selectedForCompare.splice(idx, 1);
							}
						}
						updateDock();
						if (cmpModal && cmpModal.style.display === 'flex') {
							renderCompareMatrix();
						}
					});
				});

				// Attach store compare trigger listeners
				cardsContainer.querySelectorAll('.ge-shop-compare-trigger').forEach(function (el) {
					el.addEventListener('click', function (e) {
						e.preventDefault();
						e.stopPropagation();
						var id = parseInt(el.getAttribute('data-id'), 10);
						state.openStores[id] = !state.openStores[id];
						updateUI();
					});
				});

				// Attach popover close clicks
				cardsContainer.querySelectorAll('[data-close-id]').forEach(function (btn) {
					btn.addEventListener('click', function (e) {
						e.preventDefault();
						e.stopPropagation();
						var id = parseInt(btn.getAttribute('data-close-id'), 10);
						state.openStores[id] = false;
						updateUI();
					});
				});
			} else {
				cardsContainer.innerHTML =
					'<div class="ge-comp-empty">' +
						'<p>Fant ingen produkter som matcher filteret.</p>' +
						'<button type="button" class="ge-comp-empty-reset-btn" id="ge-empty-reset-btn">Fjern alle filtre</button>' +
					'</div>';

				var emptyResetBtn = cardsContainer.querySelector('#ge-empty-reset-btn');
				if (emptyResetBtn) {
					emptyResetBtn.addEventListener('click', function () {
						resetAllFilters();
					});
				}
			}

			// H. Load More Button
			if (state.visibleCount < sorted.length) {
				var remaining = sorted.length - state.visibleCount;
				loadMoreWrap.style.display = 'flex';
				loadMoreWrap.innerHTML = '<button type="button" class="ge-comp-load-more-btn" id="ge-load-more-btn">Vis flere produkter (' + remaining + ' igjen)</button>';
				var loadMoreBtn = loadMoreWrap.querySelector('#ge-load-more-btn');
				if (loadMoreBtn) {
					loadMoreBtn.addEventListener('click', function () {
						state.visibleCount += initialLimit;
						updateUI();
					});
				}
			} else {
				loadMoreWrap.style.display = 'none';
				loadMoreWrap.innerHTML = '';
			}

			// Sync comparison dock state
			updateDock();
		}

		function resetAllFilters() {
			state.activeShops = [];
			state.activeBrands = [];
			state.activeQuickFilters = {};
			state.openStores = {};
			state.minPrice = '';
			state.maxPrice = '';
			state.searchQuery = '';
			state.activeCategory = 'Alle';
			state.visibleCount = initialLimit;
			if (searchInput) searchInput.value = '';

			var deptAlert = root.querySelector('#ge-dept-alert');
			if (deptAlert) {
				deptAlert.style.display = 'none';
				deptAlert.innerHTML = '';
			}

			renderCategoryChips();
			renderQuickFilters();
			updateUrlState();
			updateUI();
		}

		// 3. Attach Event Listeners

		// Search input
		if (searchInput) {
			searchInput.addEventListener('input', function (e) {
				state.searchQuery = e.target.value;
				state.visibleCount = initialLimit;
				updateUrlState();
				updateUI();
			});
		}

		// Sort select
		sortSelect.addEventListener('change', function (e) {
			state.sortBy = e.target.value;
			updateUI();
		});

		// Filter toggle
		filterToggleBtn.addEventListener('click', function () {
			state.showFilters = !state.showFilters;
			updateUI();
			if (state.showFilters) {
				filterCloseBtn.focus();
			}
		});

		filterCloseBtn.addEventListener('click', function () {
			state.showFilters = false;
			updateUI();
			filterToggleBtn.focus();
		});

		filterScrim.addEventListener('click', function () {
			state.showFilters = false;
			updateUI();
			filterToggleBtn.focus();
		});

		document.addEventListener('keydown', function (e) {
			if (state.showFilters && e.key === 'Escape') {
				state.showFilters = false;
				updateUI();
				filterToggleBtn.focus();
			}
		});

		// View mode buttons
		viewGridBtn.addEventListener('click', function () {
			state.viewMode = 'grid';
			viewGridBtn.classList.add('is-active');
			viewListBtn.classList.remove('is-active');
			updateUI();
		});

		viewListBtn.addEventListener('click', function () {
			state.viewMode = 'list';
			viewListBtn.classList.add('is-active');
			viewGridBtn.classList.remove('is-active');
			updateUI();
		});

		// Dual Range Slider
		sliderMin.addEventListener('input', function (e) {
			var val = Math.min(Number(e.target.value), (state.maxPrice !== '' ? Number(state.maxPrice) : state.absMaxPrice) - 1);
			state.minPrice = val;
			state.visibleCount = initialLimit;
			updateUI();
		});

		sliderMax.addEventListener('input', function (e) {
			var val = Math.max(Number(e.target.value), (state.minPrice !== '' ? Number(state.minPrice) : state.absMinPrice) + 1);
			state.maxPrice = val;
			state.visibleCount = initialLimit;
			updateUI();
		});

		// Number inputs
		priceMinInput.addEventListener('change', function (e) {
			state.minPrice = e.target.value;
			state.visibleCount = initialLimit;
			updateUI({ fromPriceInput: true });
		});

		priceMaxInput.addEventListener('change', function (e) {
			state.maxPrice = e.target.value;
			state.visibleCount = initialLimit;
			updateUI({ fromPriceInput: true });
		});

		// Reset filters in drawer
		filterResetBtn.addEventListener('click', function () {
			state.activeShops = [];
			state.activeBrands = [];
			state.activeQuickFilters = {};
			state.minPrice = '';
			state.maxPrice = '';
			state.visibleCount = initialLimit;
			renderQuickFilters();
			updateUI();
		});

		// Apply filters and smooth scroll
		filterApplyBtn.addEventListener('click', function () {
			state.showFilters = false;
			updateUI();
			filterToggleBtn.focus();
			setTimeout(function () {
				if (resultsCount) {
					var topPos = resultsCount.getBoundingClientRect().top + window.scrollY;
					window.scrollTo({ top: topPos - 100, behavior: 'smooth' });
				}
			}, 50);
		});

		// Comparison Dock & Modal Events
		if (dockOpenModalBtn) {
			dockOpenModalBtn.addEventListener('click', function () {
				renderCompareMatrix();
				if (cmpModal) {
					cmpModal.style.display = 'flex';
					document.body.style.overflow = 'hidden';
				}
			});
		}

		if (modalCloseBtn) {
			modalCloseBtn.addEventListener('click', function () {
				if (cmpModal) {
					cmpModal.style.display = 'none';
					document.body.style.overflow = '';
				}
			});
		}

		if (cmpModal) {
			cmpModal.addEventListener('click', function (e) {
				if (e.target === cmpModal) {
					cmpModal.style.display = 'none';
					document.body.style.overflow = '';
				}
			});
		}

		document.addEventListener('keydown', function (e) {
			if ((e.key === 'Escape' || e.keyCode === 27) && cmpModal && cmpModal.style.display === 'flex') {
				cmpModal.style.display = 'none';
				document.body.style.overflow = '';
			}
		});

		if (dockClearBtn) {
			dockClearBtn.addEventListener('click', function () {
				state.selectedForCompare = [];
				root.querySelectorAll('.ge-comp-chk').forEach(function (c) { c.checked = false; });
				updateDock();
				if (cmpModal && cmpModal.style.display === 'flex') {
					cmpModal.style.display = 'none';
					document.body.style.overflow = '';
				}
			});
		}

		if (diffOnlyChk) {
			diffOnlyChk.addEventListener('change', function (e) {
				state.showOnlyDiffs = e.target.checked;
				var toggleLabel = diffOnlyChk.closest('.ge-cmp-diff-toggle');
				if (toggleLabel) {
					if (state.showOnlyDiffs) {
						toggleLabel.classList.add('is-active');
					} else {
						toggleLabel.classList.remove('is-active');
					}
				}
				renderCompareMatrix();
			});
		}

		// Popstate listener for browser Back/Forward navigation
		window.addEventListener('popstate', function (e) {
			var stateObj = e.state;
			var urlP = null;
			try {
				urlP = new URLSearchParams(window.location.search);
			} catch (err) {}

			var newDept = (stateObj && stateObj.dept) || (urlP && urlP.get('dept')) || initialDeptAttr;
			var newCat = (stateObj && stateObj.cat) || (urlP && urlP.get('cat')) || initialCatAttr;
			var newQ = (stateObj && typeof stateObj.q === 'string') ? stateObj.q : ((urlP && urlP.get('q')) || '');

			var deptValid = DEPARTMENTS.some(function (d) { return d.id === newDept; });
			if (!deptValid) newDept = 'kondisjon';

			var deptChanged = state.activeDepartment !== newDept;
			state.activeDepartment = newDept;
			state.activeCategory = newCat;
			state.searchQuery = newQ;
			if (searchInput) searchInput.value = newQ;

			if (deptChanged) {
				var deptPrices = state.products
					.filter(function (p) {
						var d = p.department || inferDepartment(p);
						return d === state.activeDepartment;
					})
					.map(function (p) { return p.currentPrice; })
					.filter(function (p) { return !isNaN(p) && p > 0; });

				if (deptPrices.length > 0) {
					state.absMinPrice = Math.floor(Math.min.apply(null, deptPrices));
					state.absMaxPrice = Math.ceil(Math.max.apply(null, deptPrices));
					if (sliderMin && sliderMax) {
						sliderMin.min = state.absMinPrice;
						sliderMin.max = state.absMaxPrice;
						sliderMax.min = state.absMinPrice;
						sliderMax.max = state.absMaxPrice;
					}
				}
			}

			renderDepartmentTabs();
			renderCategoryChips();
			renderQuickFilters();
			updateUI();
		});

		// Initial render
		updateUI();
	}

	// Merchant domain resolution & avatars (Parity with Gear Picker)
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
		'proteinfabrikken':'proteinfabrikken.no',
		'x-life':          'x-life.no',
		'xlife':           'x-life.no'
	};

	function cleanShopName(raw) {
		var s = String(raw || '').trim();
		var m = s.match(/\(([^)]+)\)$/);
		return m ? m[1].trim() : s;
	}

	function getShopInitials(n) {
		var clean = cleanShopName(n).replace(/[()[\]{}_-]/g, ' ').trim();
		var parts = clean.split(/\s+/).filter(Boolean);
		if (!parts.length) { return 'GE'; }
		return (parts.length > 1 ? parts[0][0] + parts[1][0] : parts[0].slice(0, 2)).toUpperCase();
	}

	function getMerchantFavicon(shopName) {
		var cleaned = cleanShopName(shopName);
		var lower = cleaned.toLowerCase().replace(/[^a-z0-9]/g, '');
		var domain = null;

		for (var key in KNOWN_MERCHANT_DOMAINS) {
			if (lower.indexOf(key) !== -1 || key.indexOf(lower) !== -1) {
				domain = KNOWN_MERCHANT_DOMAINS[key];
				break;
			}
		}

		if (!domain && shopName && shopName.indexOf('.') !== -1) {
			domain = shopName.toLowerCase().trim();
		}

		if (!domain && lower.length > 0) {
			domain = lower + '.no';
		}

		return domain ? 'https://www.google.com/s2/favicons?domain=' + domain + '&sz=128' : null;
	}

	function renderShopAvatarHtml(shopName) {
		var inits = getShopInitials(shopName);
		var logoUrl = getMerchantFavicon(shopName);

		if (logoUrl) {
			return '<span class="ge-shop-pill-avatar">' +
				'<img src="' + escapeHtml(logoUrl) + '" alt="' + escapeHtml(shopName) + '" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display=\'none\';if(this.nextElementSibling){this.nextElementSibling.style.display=\'inline-flex\';}" />' +
				'<span class="ge-shop-pill-inits" style="display:none;">' + escapeHtml(inits) + '</span>' +
			'</span>';
		}
		return '<span class="ge-shop-pill-avatar"><span class="ge-shop-pill-inits">' + escapeHtml(inits) + '</span></span>';
	}

	// Helper to render filter pills for Brands and Shops (with M3 Avatar support for Shops)
	function renderFilterPills(container, items, activeItems, onClick, isShop) {
		container.innerHTML = '';
		items.forEach(function (item) {
			var isActive = item === 'Alle' ? activeItems.length === 0 : activeItems.indexOf(item) !== -1;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ge-filter-pill-btn' + (isShop ? ' ge-filter-pill-shop' : '') + (isActive ? ' is-active' : '');
			
			if (isShop && item !== 'Alle') {
				btn.innerHTML = renderShopAvatarHtml(item) + '<span>' + escapeHtml(item) + '</span>';
			} else {
				btn.textContent = item;
			}

			btn.addEventListener('click', function () {
				onClick(item);
			});
			container.appendChild(btn);
		});
	}

	// Helper: SVG External Link
	function externalLinkIcon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
			'<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>' +
			'<polyline points="15 3 21 3 21 9"></polyline>' +
			'<line x1="10" y1="14" x2="21" y2="3"></line>' +
		'</svg>';
	}

	// Helper: SVG Store Icon
	function shopIcon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ge-shop-icon">' +
			'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>' +
			'<polyline points="9 22 9 12 15 12 15 22"></polyline>' +
		'</svg>';
	}

	// Store Comparison Table Renderer (with in-card popover support and M3 avatars)
	function renderStoresDrawer(p, isOverlay) {
		if (!p.stores || p.stores.length === 0) return '';
		var lowestPrice = p.currentPrice;
		var maxPrice = p.stores[p.stores.length - 1].price;

		var h = '<div class="ge-stores-table' + (isOverlay ? ' ge-stores-popover' : '') + '">';
		
		// Header with Brand, Name, Store count, Price range, and Close button
		h += '<div class="ge-popover-header">' +
			'<div class="ge-popover-prod-info">' +
				'<span class="ge-popover-brand">' + escapeHtml(p.brand || 'UTSTYR') + '</span>' +
				'<h4 class="ge-popover-title" title="' + escapeHtml(p.name) + '">' + escapeHtml(p.name) + '</h4>' +
				'<div class="ge-popover-meta">' +
					'<span>Prissammenligning (' + p.stores.length + ' butikker)</span>' +
					'<span class="ge-popover-price-range">Fra ' + formatPrice(lowestPrice) + ' til ' + formatPrice(maxPrice) + '</span>' +
				'</div>' +
			'</div>' +
			'<button type="button" class="ge-popover-close" data-close-id="' + p.id + '" title="Lukk forhandlerliste" aria-label="Lukk">' +
				'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' +
			'</button>' +
		'</div>';

		p.stores.forEach(function(st) {
			var isBest = st.price === lowestPrice;

			h += '<div class="ge-store-row' + (isBest ? ' is-best-deal' : '') + '">' +
				(isBest ? '<span class="ge-store-tag">Best pris</span>' : '') +
				'<div class="ge-store-left">' +
					'<span class="ge-stock-dot ' + (st.inStock ? 'is-in-stock' : 'is-out-of-stock') + '" title="' + (st.inStock ? 'På lager' : 'Ikke på lager') + '"></span>' +
					renderShopAvatarHtml(st.shop) +
					'<b title="' + escapeHtml(st.shop) + '">' + escapeHtml(st.shop) + '</b>' +
				'</div>' +
				'<div class="ge-store-right">' +
					'<span class="ge-store-price">' + formatPrice(st.price) + '</span>' +
					'<a href="' + escapeHtml(st.url || '#') + '" target="_blank" rel="noopener noreferrer sponsored" class="ge-store-btn">Kjøp</a>' +
				'</div>' +
			'</div>';
		});

		h += '<button type="button" class="ge-popover-close-btn" data-close-id="' + p.id + '">' +
			'<span>Lukk forhandlerliste</span> <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>' +
		'</button>';

		h += '</div>';
		return h;
	}

	// Layout 1: List Card (2-Column Responsive Card Layout)
	function renderListCard(product, state) {
		var hasDiscount = product.discount && product.discount > 0;
		var safeUrl = product.url || '#';
		var safeImg = product.image && product.image.length > 5 ? product.image : '';
		var stockClass = product.inStock ? 'is-in-stock' : 'is-out-of-stock';
		var save = (product.originalPrice && product.originalPrice > product.currentPrice) ? (product.originalPrice - product.currentPrice) : 0;
		var showSavings = state && state.sortBy === 'savings_desc' && save > 0;
		var isChecked = state && state.selectedForCompare && state.selectedForCompare.indexOf(product.id) !== -1;
		var storeCount = (product.stores && product.stores.length > 0) ? product.stores.length : ((product.otherShops || 0) + 1);
		var isOpen = !!(state && state.openStores && state.openStores[product.id]);
		var hasStoreComparison = storeCount > 1 && product.stores && product.stores.length > 1;
		var shopRow;

		if (hasStoreComparison) {
			shopRow = '<button type="button" class="ge-shop-compare-trigger ge-list-shop-row ge-list-shop-row-trigger' + (isOpen ? ' is-open' : '') + '" data-id="' + product.id + '" aria-expanded="' + (isOpen ? 'true' : 'false') + '" title="Klikk for å sammenligne forhandlere">' +
				'<span class="ge-list-shop-name">' +
					renderShopAvatarHtml(product.shop) +
					'<span class="ge-list-shop-line"><span>Best pris:</span> <b>' + escapeHtml(product.shop || 'Nettbutikk') + '</b></span>' +
				'</span>' +
				'<span class="ge-compare-badge">' + storeCount + ' butikker ' + (isOpen ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>') + '</span>' +
			'</button>';
		} else {
			shopRow = '<div class="ge-list-shop-row ge-list-shop-row-static">' +
				'<span class="ge-list-shop-name">' +
					renderShopAvatarHtml(product.shop) +
					'<b>' + escapeHtml(product.shop || 'Nettbutikk') + '</b>' +
					(product.otherShops > 0 ? '<span class="ge-list-other-shops">(+' + product.otherShops + ')</span>' : '') +
				'</span>' +
				'<span class="ge-list-stock-state ' + stockClass + '"><span class="ge-stock-dot ' + stockClass + '"></span><span class="ge-list-stock-label">' + (product.inStock ? 'På lager' : 'Utsolgt') + '</span></span>' +
			'</div>';
		}

		return '' +
		'<div class="ge-card-list">' +
			'<div class="ge-list-main-row">' +
				'<div class="ge-card-list-img-wrap">' +
					(hasDiscount ? '<div class="ge-discount-badge">-' + product.discount + '%</div>' : '') +
					(safeImg ? '<img src="' + escapeHtml(safeImg) + '" alt="' + escapeHtml(product.name) + '" class="ge-card-img" onerror="this.style.display=\'none\';" />' : '') +
				'</div>' +
				'<div class="ge-card-list-content">' +
					'<div class="ge-list-top-row">' +
						'<span class="ge-card-brand">' + escapeHtml(product.brand || 'UTSTYR') + '</span>' +
						'<label class="ge-card-cmp-label" title="Legg til i sammenligning">' +
							'<input type="checkbox" class="ge-comp-chk" data-id="' + product.id + '"' + (isChecked ? ' checked' : '') + ' />' +
							'<span class="ge-list-compare-box" aria-hidden="true"></span>' +
							'<span>Sammenlign</span>' +
						'</label>' +
					'</div>' +
					'<h3 class="ge-card-title"><a href="' + escapeHtml(safeUrl) + '" target="_blank" rel="noopener noreferrer sponsored" class="ge-card-title-link">' + escapeHtml(product.name) + '</a></h3>' +
					'<div class="ge-list-action-row">' +
						'<div class="ge-price-row">' +
							'<span class="ge-price-current">' + formatPrice(product.currentPrice) + '</span>' +
							(product.originalPrice ? '<span class="ge-price-original">' + formatPrice(product.originalPrice) + '</span>' : '') +
							(showSavings ? '<span class="ge-savings-chip">Spar ' + formatPrice(save) + '</span>' : '') +
						'</div>' +
						'<a href="' + escapeHtml(safeUrl) + '" target="_blank" rel="noopener noreferrer sponsored" class="ge-cta-btn ge-list-cta">' +
							'Til butikk ' + externalLinkIcon() +
						'</a>' +
					'</div>' +
				'</div>' +
			'</div>' +
			shopRow +
			(isOpen && hasStoreComparison ? '<div class="ge-list-drawer-wrap">' + renderStoresDrawer(product, false) + '</div>' : '') +
		'</div>';
	}

	// Layout 2: Grid Card
	function renderGridCard(product, state) {
		var hasDiscount = product.discount && product.discount > 0;
		var safeUrl = product.url || '#';
		var safeImg = product.image && product.image.length > 5 ? product.image : '';
		var stockClass = product.inStock ? 'is-in-stock' : 'is-out-of-stock';
		var save = (product.originalPrice && product.originalPrice > product.currentPrice) ? (product.originalPrice - product.currentPrice) : 0;
		var showSavings = state && state.sortBy === 'savings_desc' && save > 0;
		var isChecked = state && state.selectedForCompare && state.selectedForCompare.indexOf(product.id) !== -1;
		var storeCount = (product.stores && product.stores.length > 0) ? product.stores.length : ((product.otherShops || 0) + 1);
		var isOpen = !!(state && state.openStores && state.openStores[product.id]);

		return '' +
		'<div class="ge-card-grid">' +
			'<div class="ge-card-cmp-wrap">' +
				'<label class="ge-card-cmp-label" title="Legg til i sammenligning">' +
					'<input type="checkbox" class="ge-comp-chk" data-id="' + product.id + '"' + (isChecked ? ' checked' : '') + ' />' +
					'<span>Sammenlign</span>' +
				'</label>' +
			'</div>' +
			'<div class="ge-card-grid-img-wrap">' +
				(hasDiscount ? '<div class="ge-discount-badge">-' + product.discount + '%</div>' : '') +
				(safeImg ? '<img src="' + escapeHtml(safeImg) + '" alt="' + escapeHtml(product.name) + '" class="ge-card-img" onerror="this.style.display=\'none\';" />' : '') +
			'</div>' +
			'<div class="ge-card-content">' +
				'<span class="ge-card-brand">' + escapeHtml(product.brand || 'UTSTYR') + '</span>' +
				'<h3 class="ge-card-title">' +
					'<a href="' + escapeHtml(safeUrl) + '" target="_blank" rel="noopener noreferrer sponsored" class="ge-card-title-link">' + escapeHtml(product.name) + '</a>' +
				'</h3>' +
				'<div class="ge-card-bottom">' +
					'<div class="ge-price-row">' +
						'<span class="ge-price-current">' + formatPrice(product.currentPrice) + '</span>' +
						(product.originalPrice ? '<span class="ge-price-original">' + formatPrice(product.originalPrice) + '</span>' : '') +
						(showSavings ? '<span class="ge-savings-chip">Spar ' + formatPrice(save) + '</span>' : '') +
					'</div>' +
					(storeCount > 1 && product.stores && product.stores.length > 1 ? (
						'<div class="ge-shop-compare-trigger ' + (isOpen ? 'is-open' : '') + '" data-id="' + product.id + '" title="Klikk for å sammenligne priser fra alle ' + storeCount + ' butikker">' +
							'<div class="ge-shop-name-wrap" style="gap: 6px;">' +
								renderShopAvatarHtml(product.shop) +
								'<span>Best pris: ' + escapeHtml(product.shop || 'Nettbutikk') + '</span>' +
							'</div>' +
							'<span class="ge-compare-badge">' + storeCount + ' butikker ' + (isOpen ? '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>') + '</span>' +
						'</div>' +
						(isOpen ? renderStoresDrawer(product, true) : '')
					) : (
						'<div class="ge-shop-strip">' +
							'<div class="ge-shop-name-wrap" style="gap: 6px;">' +
								renderShopAvatarHtml(product.shop) +
								'<span>' + escapeHtml(product.shop || 'Nettbutikk') + '</span>' +
							'</div>' +
							'<div class="ge-stock-meta">' +
								'<span class="ge-stock-dot ' + stockClass + '"></span>' +
								(product.otherShops > 0 ? '<span class="ge-other-shops-badge">+ ' + product.otherShops + '</span>' : '') +
							'</div>' +
						'</div>'
					)) +
					'<a href="' + escapeHtml(safeUrl) + '" target="_blank" rel="noopener noreferrer sponsored" class="ge-cta-btn">' +
						'Gå til ' + escapeHtml(product.shop || 'butikk') + ' ' + externalLinkIcon() +
					'</a>' +
				'</div>' +
			'</div>' +
		'</div>';
	}

	// Initial DOM skeleton constructor
	function renderSkeleton(root, state) {
		root.innerHTML = '' +
		'<div class="mb-5" style="margin-bottom: 24px;">' +
			'<h2 style="font-size: 28px; font-weight: 800; color: #0C2340; margin: 0 0 6px 0; letter-spacing: -0.02em;">Prissammenligning</h2>' +
			'<p style="font-size: 15px; color: #64748b; margin: 0;">Sammenlign priser fra våre samarbeidspartnere.</p>' +
		'</div>' +

		// Level 1 Department Tabs & Level 2 Category Chips
		'<div class="ge-dept-section">' +
			'<div class="ge-dept-track" id="ge-dept-track" role="tablist" aria-label="Hovedavdelinger"></div>' +
			'<div class="ge-comp-cats-bar" id="ge-comp-cats-bar" role="tablist" aria-label="Kategorier"></div>' +
		'</div>' +

		// Cross-Department Search Alert Banner
		'<div class="ge-dept-alert" id="ge-dept-alert" style="display: none;" role="alert"></div>' +

		// Quick Filter Chips (Tier 1 Exposed Filters)
		'<div id="ge-comp-quick-filters-slot" class="ge-comp-quick-filters-slot">' +
			'<div class="ge-comp-quick-filters" id="ge-comp-quick-filters" role="group" aria-label="Hurtigfiltre"></div>' +
		'</div>' +

		// Controls Bar: Search, Sort, Filter Toggle, View Toggle
		'<div class="ge-comp-controls">' +
			'<div class="ge-comp-search-box">' +
				'<div class="ge-comp-search-icon">' +
					'<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>' +
				'</div>' +
				'<input type="text" class="ge-comp-search-input" placeholder="Søk etter produkter og merker..." />' +
			'</div>' +

			'<div class="ge-comp-action-row">' +
				'<div class="ge-comp-sort-wrap">' +
					'<select class="ge-comp-sort-select">' +
						'<option value="relevant">Mest relevant</option>' +
						'<option value="price_asc">Laveste pris</option>' +
						'<option value="price_desc">Høyeste pris</option>' +
						'<option value="discount_desc">Størst rabatt (%)</option>' +
						'<option value="savings_desc">Størst kroneavslag</option>' +
					'</select>' +
					'<div class="ge-comp-sort-arrow">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>' +
					'</div>' +
				'</div>' +

				'<button type="button" class="ge-comp-filter-toggle" aria-haspopup="dialog" aria-controls="ge-comp-filter-panel" aria-expanded="false">' +
					'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
						'<line x1="21" y1="4" x2="14" y2="4"></line><line x1="10" y1="4" x2="3" y2="4"></line>' +
						'<line x1="21" y1="12" x2="12" y2="12"></line><line x1="8" y1="12" x2="3" y2="12"></line>' +
						'<line x1="21" y1="20" x2="16" y2="20"></line><line x1="12" y1="20" x2="3" y2="20"></line>' +
						'<line x1="14" y1="1" x2="14" y2="7"></line><line x1="8" y1="9" x2="8" y2="15"></line>' +
						'<line x1="16" y1="17" x2="16" y2="23"></line>' +
					'</svg>' +
					'<span>Filter</span>' +
					'<span id="ge-comp-filter-badge" class="ge-comp-filter-badge" style="display: none;">0</span>' +
				'</button>' +

				'<div class="ge-comp-view-toggle-wrap">' +
					'<span class="ge-comp-view-label">Visning:</span>' +
					'<div class="ge-comp-view-toggle" role="group" aria-label="Velg visning">' +
						'<button type="button" id="ge-view-grid-btn" class="ge-view-btn' + (state.viewMode === 'grid' ? ' is-active' : '') + '" title="Vis som rutenett">' +
							'<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>' +
							'<span class="ge-view-btn-text">Rutenett</span>' +
						'</button>' +
						'<button type="button" id="ge-view-list-btn" class="ge-view-btn' + (state.viewMode === 'list' ? ' is-active' : '') + '" title="Vis som liste">' +
							'<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>' +
							'<span class="ge-view-btn-text">Liste</span>' +
						'</button>' +
					'</div>' +
				'</div>' +
			'</div>' +
		'</div>' +

		// Responsive modal filter sheet
		'<div id="ge-comp-filter-scrim" class="ge-comp-filter-scrim" aria-hidden="true"></div>' +
		'<aside id="ge-comp-filter-panel" class="ge-comp-filter-panel" role="dialog" aria-modal="true" aria-labelledby="ge-filter-title" aria-hidden="true" inert>' +
			'<div class="ge-filter-sheet-handle" aria-hidden="true"></div>' +
			'<div class="ge-filter-sheet-head">' +
				'<h3 id="ge-filter-title" class="ge-filter-sheet-title">Filtre</h3>' +
				'<button type="button" id="ge-filter-close-btn" class="ge-filter-close-btn" aria-label="Lukk filtre" title="Lukk filtre">' +
					'<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' +
				'</button>' +
			'</div>' +
			'<div class="ge-filter-grid">' +
				'<div class="ge-filter-quick-filters">' +
					'<h4 class="ge-filter-section-title">Hurtigfiltre</h4>' +
					'<div id="ge-comp-quick-filters-panel-slot" class="ge-filter-quick-filters-slot"></div>' +
				'</div>' +
				// Price Section
				'<div>' +
					'<h4 class="ge-filter-section-title">Pris (NOK)</h4>' +
					'<div class="ge-filter-price-inputs">' +
						'<div class="ge-price-input-box">' +
							'<input type="number" id="ge-price-min-input" placeholder=" " inputmode="numeric" aria-label="Minimumspris" />' +
							'<label for="ge-price-min-input">Fra</label>' +
							'<span aria-hidden="true">kr</span>' +
						'</div>' +
						'<div class="ge-price-input-box">' +
							'<input type="number" id="ge-price-max-input" placeholder=" " inputmode="numeric" aria-label="Maksimumspris" />' +
							'<label for="ge-price-max-input">Til</label>' +
							'<span aria-hidden="true">kr</span>' +
						'</div>' +
					'</div>' +
					'<div class="ge-slider-track-wrap">' +
						'<div class="ge-slider-base-bar"></div>' +
						'<div id="ge-slider-fill-bar" class="ge-slider-fill-bar"></div>' +
						'<input type="range" id="ge-slider-min" min="' + state.absMinPrice + '" max="' + state.absMaxPrice + '" class="dual-range-thumb" aria-label="Minimumspris" style="z-index: 3;" />' +
						'<input type="range" id="ge-slider-max" min="' + state.absMinPrice + '" max="' + state.absMaxPrice + '" class="dual-range-thumb" aria-label="Maksimumspris" style="z-index: 4;" />' +
					'</div>' +
				'</div>' +

				// Brands Section
				'<div>' +
					'<h4 class="ge-filter-section-title">Merke</h4>' +
					'<div id="ge-filter-brands-wrap" class="ge-filter-pills-wrap"></div>' +
				'</div>' +

				// Shops Section
				'<div>' +
					'<h4 class="ge-filter-section-title">Butikk</h4>' +
					'<div id="ge-filter-shops-wrap" class="ge-filter-pills-wrap"></div>' +
				'</div>' +
			'</div>' +

			// Footer Buttons
			'<div class="ge-filter-footer">' +
				'<button type="button" id="ge-filter-reset-btn" class="ge-filter-reset-btn">Tøm filtre</button>' +
				'<button type="button" id="ge-filter-apply-btn" class="ge-filter-apply-btn">Vis treff</button>' +
			'</div>' +
		'</aside>' +

		// Results Counter
		'<div id="ge-comp-results-count" class="ge-comp-results-count"></div>' +

		// Cards Container
		'<div id="ge-comp-cards-container" class="' + (state.viewMode === 'grid' ? 'ge-comp-grid' : 'ge-comp-list') + '"></div>' +

		// Load More Wrap
		'<div id="ge-comp-load-more-wrap" class="ge-comp-load-more-wrap" style="display: none;"></div>';
	}

	// Expose public API for integration and testing
	window.GearEnginePriceComparison = {
		DEPARTMENTS: DEPARTMENTS,
		inferDepartment: inferDepartment,
		getAntiFitNotes: getAntiFitNotes,
		QUICK_FILTER_DEFS: QUICK_FILTER_DEFS,
		init: initPriceComparison
	};

	// Auto-initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPriceComparison);
	} else {
		initPriceComparison();
	}
})();
