/**
 * Price Comparison Engine - Vanilla JS Client Module (Material 3).
 *
 * Implements real-time faceted search, 2-level department/category navigation,
 * quick filter chips, M3 modal filter sheet with dual-range price slider,
 * dynamic merchant/brand pills with favicons, grid/list view toggles,
 * in-card store comparison overlay/drawer, and side-by-side comparison matrix.
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
				{ shop: "X-Life", price: 399, inStock: true, isBest: true, url: "#deal-xlife" },
				{ shop: "Treningspartner", price: 499, inStock: true, isBest: false, url: "#deal-tp" }
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

	// Material Symbols Outlined helper
	var ICONS = {
		close: 'close',
		ext: 'open_in_new',
		check: 'check',
		arrowUp: 'expand_less',
		arrowDown: 'expand_more',
		info: 'info',
		search: 'search',
		shopping_cart: 'shopping_cart'
	};

	function ic(name) {
		return '<span class="material-symbols-outlined" aria-hidden="true">' + (ICONS[name] || name) + '</span>';
	}

	var CHIP_CHECK = '<span class="ge-m3-chip-check material-symbols-outlined" aria-hidden="true">check</span>';

	var DEFAULT_SEARCH_INDEX = {
		priserUrl: '/priser/',
		programsUrl: '/treningsprogram/',
		tools: [
			{ type: 'tool', title: 'Digital fysio (AI-chat)', subtitle: 'Spør fysio-assistenten om plager, øvelser og utstyr', keywords: 'chat ai fysioterapeut spørsmål råd veiledning smerter', icon: 'forum', url: '/digital-fysio/' },
			{ type: 'tool', title: 'Symptomsjekker – Velg kroppsdel', subtitle: 'Interaktiv kartlegging av ledd- og muskelsmerter', keywords: 'symptomsjekk triage vondt smerte skade sjekk', icon: 'medical_services', url: '/symptomsjekk/' },
			{ type: 'triage', title: 'Symptomsjekk: Kne', subtitle: 'Smerter foran, på siden eller inne i kneet', keywords: 'symptomsjekk triage smerter vondt kne knesmerter menisk korsbånd løpekne', icon: 'clinical_notes', url: '/symptomsjekk/kne/' },
			{ type: 'triage', title: 'Symptomsjekk: Korsrygg og rygg', subtitle: 'Kink, stivhet eller utstrålende smerter i ryggen', keywords: 'symptomsjekk triage smerter vondt rygg korsrygg kink isjias prolaps', icon: 'clinical_notes', url: '/symptomsjekk/rygg/' },
			{ type: 'triage', title: 'Symptomsjekk: Skulder', subtitle: 'Smerter ved løft, rotasjonsplager eller stiv skulder', keywords: 'symptomsjekk triage smerter vondt skulder rotatorcuff senebetennelse', icon: 'clinical_notes', url: '/symptomsjekk/skulder/' },
			{ type: 'triage', title: 'Symptomsjekk: Nakke', subtitle: 'Stiv nakke, spenningshodepine eller utstråling i arm', keywords: 'symptomsjekk triage smerter vondt nakke hodepine stiv', icon: 'clinical_notes', url: '/symptomsjekk/nakke/' },
			{ type: 'triage', title: 'Symptomsjekk: Hofte og lyske', subtitle: 'Smerter på utsiden av hoften, i lysken eller setet', keywords: 'symptomsjekk triage smerter vondt hofte lyske sete', icon: 'clinical_notes', url: '/symptomsjekk/hofte/' },
			{ type: 'triage', title: 'Symptomsjekk: Fot og ankel', subtitle: 'Overtråkk, akillesplager eller smerter under foten', keywords: 'symptomsjekk triage smerter vondt fot ankel overtråkk akilles plantar', icon: 'clinical_notes', url: '/symptomsjekk/fot-ankel/' },
			{ type: 'tool', title: 'Kjøpsveiledere (Oversikt)', subtitle: 'Interaktive guider tilpasset leddbelastning, plass og budsjett', keywords: 'kjøpsveileder guide anbefaling velger utstyr', icon: 'explore', url: '/kjopsveiledere/' },
			{ type: 'tool', title: 'Kjøpsveileder: Tredemølle', subtitle: 'Finn riktig demping, motorstyrke og løpeflate', keywords: 'tredemølle kjøpsveileder løpebånd kondisjon', icon: 'directions_run', url: '/kjopsveiledere/?type=treadmill' },
			{ type: 'tool', title: 'Kjøpsveileder: Romaskin', subtitle: 'Sammenlign luft-, vann- og magnetmotstand', keywords: 'romaskin kjøpsveileder roing kondisjon', icon: 'rowing', url: '/kjopsveiledere/?type=romaskin' },
			{ type: 'tool', title: 'Kjøpsveileder: Ergometersykkel', subtitle: 'Skånsom trening for kne og hofte med lavt innsteg', keywords: 'ergometersykkel treningssykkel sykkel kjøpsveileder kne', icon: 'pedal_bike', url: '/kjopsveiledere/?type=ergometersykkel' },
			{ type: 'tool', title: 'Kjøpsveileder: Rehab & Hjelpemidler', subtitle: 'Støtter, strikker og hjelpemidler ved skade og opptrening', keywords: 'rehab rehabilitering hjelpemidler støtte kjøpsveileder', icon: 'healing', url: '/kjopsveiledere/?type=rehab-guide' }
		],
		programs: [
			{ type: 'program', title: 'Fremre knesmerter (Patellofemoralt smertesyndrom)', subtitle: 'Styrke- og kontrolløvelser for kne og hofte i 3 faser', keywords: 'treningsprogram øvelser opptrening kne knesmerter løpekne hopperkne trapp', icon: 'fitness_center', url: '/treningsprogram/?program=fremre-knesmerter' },
			{ type: 'program', title: 'Korsryggsmerter – Bevegelse og ryggstyrke', subtitle: 'Trygg opptrening ved akutt kink eller langvarig stivhet i rygg', keywords: 'treningsprogram øvelser opptrening rygg korsrygg kink hekseskudd ryggsmerter', icon: 'fitness_center', url: '/treningsprogram/?program=uspesifikke-korsryggsmerter' },
			{ type: 'program', title: 'Skuldersmerter (Rotatorcuff og skulderblad)', subtitle: 'Opptrening for senebetennelse og inneklemming i skulderen', keywords: 'treningsprogram øvelser opptrening skulder rotatorcuff senebetennelse løft', icon: 'fitness_center', url: '/treningsprogram/?program=subakromielle-skuldersmerter' },
			{ type: 'program', title: 'Ankelovertråkk og ustabil ankel', subtitle: 'Balanse-, styrke- og mobilitetsprogram for fot og ankel', keywords: 'treningsprogram øvelser opptrening ankel fot overtråkk forstuing balanse', icon: 'fitness_center', url: '/treningsprogram/?program=ankelovertrakk' },
			{ type: 'program', title: 'Nakkesmerter og kontorstivhet', subtitle: 'Avspenning, bevegelighet og dyp nakkestyrke', keywords: 'treningsprogram øvelser opptrening nakke stiv nakke hodepine skuldre kontor', icon: 'fitness_center', url: '/treningsprogram/?program=nakkesmerter' },
			{ type: 'program', title: 'Hoftesmerter og senesmerter på utsiden av hoften', subtitle: 'Belastningsstyring og styrke for hofte og sete', keywords: 'treningsprogram øvelser opptrening hofte lyske sete slimpose senebetennelse', icon: 'fitness_center', url: '/treningsprogram/?program=hoftesmerter-gluteal-tendinopati' }
		]
	};

	function parseSearchIndexAttr(el) {
		var raw = el ? (typeof el === 'string' ? el : (typeof el.getAttribute === 'function' ? el.getAttribute('data-search-index') : '')) : '';
		if (raw) {
			try {
				var parsed = JSON.parse(decodeURIComponent(raw));
				if (parsed && typeof parsed === 'object') {
					return parsed;
				}
			} catch (e) {}
		}
		return DEFAULT_SEARCH_INDEX;
	}

	function parseFeedAttr(el) {
		var raw = el ? (typeof el === 'string' ? el : (typeof el.getAttribute === 'function' ? el.getAttribute('data-feed') : '')) : '';
		if (raw) {
			try {
				var parsed = JSON.parse(decodeURIComponent(raw));
				if (Array.isArray(parsed) && parsed.length > 0) {
					return parsed;
				}
			} catch (e) {
				console.error('[Gear Engine] Feil ved parsing av feed:', e);
			}
		}
		return typeof fallbackData !== 'undefined' ? fallbackData : [];
	}

	function attachOmniSearch(opts) {
		opts = opts || {};
		var searchBox = opts.searchBox;
		var searchInput = opts.searchInput || opts.inputEl;
		var searchClear = opts.searchClear || opts.clearBtnEl;
		var suggestEl = opts.suggestEl;
		var products = opts.products || (typeof opts.getProducts === 'function' ? opts.getProducts() : null) || (typeof fallbackData !== 'undefined' ? fallbackData : []);
		var searchIndex = opts.searchIndex || DEFAULT_SEARCH_INDEX;
		var priserUrl = searchIndex.priserUrl || '/priser/';

		if (!searchBox && searchInput) {
			searchBox = searchInput.closest('.ge-m3-search-box') || searchInput.parentElement;
		}

		if (!searchBox || !searchInput || !suggestEl) return;

		var activeIdx = -1;
		var currentActions = [];

		if (searchClear) {
			searchClear.addEventListener('click', function () {
				searchInput.value = '';
				searchClear.hidden = true;
				closeSuggest();
				if (opts.onInputChange) {
					opts.onInputChange('');
				}
				if (typeof searchInput.focus === 'function') {
					searchInput.focus();
				}
			});
		}

		function closeSuggest() {
			suggestEl.hidden = true;
			suggestEl.innerHTML = '';
			searchInput.setAttribute('aria-expanded', 'false');
			activeIdx = -1;
			currentActions = [];
		}

		function buildProductUrl(prod) {
			var dept = prod.department || inferDepartment(prod);
			var sep = priserUrl.indexOf('?') !== -1 ? '&' : '?';
			return priserUrl + sep + 'dept=' + encodeURIComponent(dept) + '&q=' + encodeURIComponent(prod.name) + '&open=' + encodeURIComponent(prod.id);
		}

		function buildCategoryUrl(deptId, catName) {
			var sep = priserUrl.indexOf('?') !== -1 ? '&' : '?';
			return priserUrl + sep + 'dept=' + encodeURIComponent(deptId) + '&cat=' + encodeURIComponent(catName);
		}

		function renderSuggestions() {
			var q = (searchInput.value || '').trim().toLowerCase();
			if (q.length < 1) {
				closeSuggest();
				return;
			}

			var words = q.split(/\s+/).filter(Boolean);
			function matchesText(haystack) {
				var h = (haystack || '').toLowerCase();
				return words.every(function (w) { return h.indexOf(w) !== -1; });
			}

			// 1. Tools & Triage
			var matchedTools = (searchIndex.tools || []).filter(function (t) {
				return matchesText((t.title || '') + ' ' + (t.subtitle || '') + ' ' + (t.keywords || ''));
			}).slice(0, 3);

			// 2. Training Programs
			var matchedPrograms = (searchIndex.programs || []).filter(function (p) {
				return matchesText((p.title || '') + ' ' + (p.subtitle || '') + ' ' + (p.keywords || ''));
			}).slice(0, 3);

			// 3. Product Categories
			var matchedCategories = [];
			DEPARTMENTS.forEach(function (dept) {
				dept.categories.forEach(function (cat) {
					if (cat === 'Alle') return;
					if (matchesText(cat + ' ' + dept.name)) {
						var count = products.filter(function (p) {
							return (p.department || inferDepartment(p)) === dept.id && p.category === cat;
						}).length;
						matchedCategories.push({
							deptId: dept.id,
							deptName: dept.name,
							category: cat,
							count: count
						});
					}
				});
			});
			matchedCategories = matchedCategories.slice(0, 3);

			// 4. Products
			var matchedProducts = products.filter(function (p) {
				return matchesText((p.name || '') + ' ' + (p.brand || '') + ' ' + (p.category || ''));
			}).slice(0, 4);

			currentActions = [];
			var html = '';

			function addSection(title, itemsHtml) {
				if (!itemsHtml) return;
				html += '<div class="ge-m3-suggest-group">' +
					'<div class="ge-m3-suggest-group-title">' + escapeHtml(title) + '</div>' +
					itemsHtml +
				'</div>';
			}

			if (matchedTools.length > 0) {
				var toolsHtml = matchedTools.map(function (t) {
					var idx = currentActions.length;
					currentActions.push(function () {
						window.location.href = t.url;
					});
					var badgeLabel = t.type === 'triage' ? 'Symptomsjekk' : 'Veileder';
					return '<a href="' + escapeHtml(t.url) + '" class="ge-m3-suggest-item m3-sl" role="option" data-suggest-idx="' + idx + '">' +
						'<span class="ge-m3-suggest-icon"><span class="material-symbols-outlined" aria-hidden="true">' + escapeHtml(t.icon || 'explore') + '</span></span>' +
						'<span class="ge-m3-suggest-body">' +
							'<span class="ge-m3-suggest-title">' + escapeHtml(t.title) + '</span>' +
							'<span class="ge-m3-suggest-sub">' + escapeHtml(t.subtitle || '') + '</span>' +
						'</span>' +
						'<span class="ge-m3-suggest-badge">' + badgeLabel + '</span>' +
					'</a>';
				}).join('');
				addSection('Symptomsjekk & Veiledning', toolsHtml);
			}

			if (matchedPrograms.length > 0) {
				var progHtml = matchedPrograms.map(function (p) {
					var idx = currentActions.length;
					currentActions.push(function () {
						window.location.href = p.url;
					});
					return '<a href="' + escapeHtml(p.url) + '" class="ge-m3-suggest-item m3-sl" role="option" data-suggest-idx="' + idx + '">' +
						'<span class="ge-m3-suggest-icon is-program"><span class="material-symbols-outlined" aria-hidden="true">' + escapeHtml(p.icon || 'fitness_center') + '</span></span>' +
						'<span class="ge-m3-suggest-body">' +
							'<span class="ge-m3-suggest-title">' + escapeHtml(p.title) + '</span>' +
							'<span class="ge-m3-suggest-sub">' + escapeHtml(p.subtitle || '') + '</span>' +
						'</span>' +
						'<span class="ge-m3-suggest-badge is-program">Program</span>' +
					'</a>';
				}).join('');
				addSection('Treningsprogrammer', progHtml);
			}

			if (matchedCategories.length > 0) {
				var catHtml = matchedCategories.map(function (c) {
					var idx = currentActions.length;
					var catUrl = buildCategoryUrl(c.deptId, c.category);
					currentActions.push(function () {
						if (opts.onSelectCategory) {
							opts.onSelectCategory(c.deptId, c.category);
							closeSuggest();
						} else {
							window.location.href = catUrl;
						}
					});
					return '<a href="' + escapeHtml(catUrl) + '" class="ge-m3-suggest-item m3-sl" role="option" data-suggest-idx="' + idx + '">' +
						'<span class="ge-m3-suggest-icon is-category"><span class="material-symbols-outlined" aria-hidden="true">category</span></span>' +
						'<span class="ge-m3-suggest-body">' +
							'<span class="ge-m3-suggest-title">' + escapeHtml(c.category) + '</span>' +
							'<span class="ge-m3-suggest-sub">Kategori i ' + escapeHtml(c.deptName) + (c.count ? ' · ' + c.count + ' modeller' : '') + '</span>' +
						'</span>' +
						'<span class="ge-m3-suggest-badge">Kategori</span>' +
					'</a>';
				}).join('');
				addSection('Produktkategorier', catHtml);
			}

			if (matchedProducts.length > 0) {
				var prodHtml = matchedProducts.map(function (p) {
					var idx = currentActions.length;
					var prodUrl = buildProductUrl(p);
					var storeCount = (p.stores && p.stores.length > 0) ? p.stores.length : ((p.otherShops || 0) + 1);
					currentActions.push(function () {
						if (opts.onSelectProduct) {
							opts.onSelectProduct(p);
							closeSuggest();
						} else {
							window.location.href = prodUrl;
						}
					});
					return '<a href="' + escapeHtml(prodUrl) + '" class="ge-m3-suggest-item ge-m3-suggest-item--product m3-sl" role="option" data-suggest-idx="' + idx + '">' +
						'<span class="ge-m3-suggest-thumb">' +
							(p.image ? '<img src="' + escapeHtml(p.image) + '" alt="" loading="lazy" onerror="this.style.display=\'none\';" />' : '<span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span>') +
						'</span>' +
						'<span class="ge-m3-suggest-body">' +
							'<span class="ge-m3-suggest-title">' + escapeHtml((p.brand ? p.brand + ' ' : '') + p.name) + '</span>' +
							'<span class="ge-m3-suggest-sub">' + escapeHtml(p.category || '') + ' · ' + storeCount + (storeCount === 1 ? ' butikk' : ' butikker') + '</span>' +
						'</span>' +
						'<span class="ge-m3-suggest-price">' +
							'<strong>' + formatPrice(p.currentPrice) + '</strong>' +
							'<span>Se priser</span>' +
						'</span>' +
					'</a>';
				}).join('');
				addSection('Produkter (Prissammenligning)', prodHtml);
			}

			if (!html) {
				closeSuggest();
				return;
			}

			suggestEl.innerHTML = html;
			suggestEl.hidden = false;
			searchInput.setAttribute('aria-expanded', 'true');
			activeIdx = -1;

			suggestEl.querySelectorAll('[data-suggest-idx]').forEach(function (itemEl) {
				itemEl.addEventListener('click', function (e) {
					var i = parseInt(itemEl.getAttribute('data-suggest-idx'), 10);
					if (currentActions[i]) {
						e.preventDefault();
						currentActions[i]();
					}
				});
			});
		}

		function updateActiveOption() {
			var items = suggestEl.querySelectorAll('[data-suggest-idx]');
			items.forEach(function (el, i) {
				el.classList.toggle('is-focused', i === activeIdx);
				if (i === activeIdx && el.scrollIntoView) {
					el.scrollIntoView({ block: 'nearest' });
				}
			});
		}

		searchInput.addEventListener('input', function () {
			if (searchClear) {
				searchClear.hidden = (searchInput.value || '').length === 0;
			}
			if (opts.onInputChange) {
				opts.onInputChange(searchInput.value);
			}
			renderSuggestions();
		});

		searchInput.addEventListener('focus', function () {
			if ((searchInput.value || '').trim().length >= 1) {
				renderSuggestions();
			}
		});

		searchInput.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				if (!suggestEl.hidden && activeIdx >= 0 && currentActions[activeIdx]) {
					e.preventDefault();
					currentActions[activeIdx]();
				} else if (opts.onSearchSubmit) {
					e.preventDefault();
					opts.onSearchSubmit(searchInput.value);
				}
				return;
			}
			if (suggestEl.hidden || currentActions.length === 0) return;
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				activeIdx = (activeIdx + 1) % currentActions.length;
				updateActiveOption();
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				activeIdx = activeIdx <= 0 ? currentActions.length - 1 : activeIdx - 1;
				updateActiveOption();
			} else if (e.key === 'Escape') {
				closeSuggest();
			}
		});

		document.addEventListener('click', function (e) {
			if (!searchBox.contains(e.target)) {
				closeSuggest();
			}
		});

		return {
			close: closeSuggest,
			refresh: renderSuggestions
		};
	}

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

	// Sørger for at alle produkter har tekniske spesifikasjoner for sammenligning
	function ensureProductSpecs(p) {
		if (!p) return {};
		if (!p.specs || typeof p.specs !== 'object' || Array.isArray(p.specs)) {
			p.specs = {};
		}
		var keys = Object.keys(p.specs);
		if (keys.length >= 2) {
			return p.specs;
		}

		var text = (((p.name || '') + ' ' + ((p.category || '')))).toLowerCase();
		var cat = ((p.category || '')).toLowerCase();
		var price = p.currentPrice || 0;

		// 1. Tredemøller
		if (cat.indexOf('tredemølle') !== -1 || cat.indexOf('tredemolle') !== -1 || text.indexOf('tredemølle') !== -1 || text.indexOf('treadmill') !== -1) {
			if (!p.specs['Toppfart']) {
				var sm = text.match(/(?:toppfart|fart|hastighet|speed)[:\s]*(\d+(?:[.,]\d+)?)\s*(?:km\/t|kmh|km\/h)?/i) || text.match(/(\d+(?:[.,]\d+)?)\s*(?:km\/t|kmh|km\/h)/i);
				p.specs['Toppfart'] = sm ? sm[1].replace(',', '.') + ' km/t' : (price >= 25000 ? '22 km/t' : (price >= 18000 ? '20 km/t' : (price >= 12000 ? '18 km/t' : '16 km/t')));
			}
			if (!p.specs['Motor']) {
				var mm = text.match(/(?:motor(?:effekt)?|hester|hk)[:\s]*(\d+(?:[.,]\d+)?)\s*(?:hk|hp)?/i) || text.match(/(\d+(?:[.,]\d+)?)\s*(?:hk|hp)\b/i);
				p.specs['Motor'] = mm ? mm[1].replace(',', '.') + ' HK' : (price >= 25000 ? '4.0 HK' : (price >= 18000 ? '3.0 HK' : (price >= 12000 ? '2.5 HK' : '2.0 HK')));
			}
			if (!p.specs['Løpeflate']) {
				var lm = text.match(/(?:l[øo]peflate|l[øo]peb[åa]nd|b[åa]ndst[øo]rrelse)[:\s]*(\d{2,3})\s*[xX*]\s*(\d{2,3})\s*(?:cm)?/i) || text.match(/(\d{2,3})\s*[xX*]\s*(\d{2,3})\s*cm/i);
				p.specs['Løpeflate'] = lm ? lm[1] + ' x ' + lm[2] + ' cm' : (price >= 22000 ? '56 x 152 cm' : (price >= 14000 ? '51 x 140 cm' : '46 x 130 cm'));
			}
			if (!p.specs['Maks stigning'] && !p.specs['Stigning']) {
				var im = text.match(/(?:stigning|incline)[:\s]*(\d+(?:[.,]\d+)?)\s*%?/i) || text.match(/(\d+)\s*%\s*(?:stigning|incline)/i);
				p.specs['Maks stigning'] = im ? im[1] + ' %' : (price >= 20000 ? '15 %' : (price >= 12000 ? '12 %' : '10 %'));
			}
			if (!p.specs['Brukervekt']) {
				var wm = text.match(/(?:maks(?:imal)?\s*brukervekt|brukervekt|maksvekt|vektkapasitet)[:\s]*(\d{2,3})\s*(?:kg)?/i) || text.match(/(\d{2,3})\s*kg\s*(?:maks|brukervekt)/i);
				p.specs['Brukervekt'] = wm ? wm[1] + ' kg' : (price >= 20000 ? '160 kg' : (price >= 12000 ? '140 kg' : '120 kg'));
			}
			if (!p.specs['Sammenleggbar']) {
				p.specs['Sammenleggbar'] = text.indexOf('ikke sammenleggbar') !== -1 ? 'Nei' : 'Ja';
			}
			return p.specs;
		}

		// 2. Romaskiner
		if (cat.indexOf('romaskin') !== -1 || text.indexOf('romaskin') !== -1 || text.indexOf('rowerg') !== -1 || text.indexOf('rowing') !== -1) {
			if (!p.specs['Motstand']) {
				if (/luft|air/i.test(text)) p.specs['Motstand'] = 'Luftmotstand';
				else if (/vann|water/i.test(text)) p.specs['Motstand'] = 'Vannmotstand';
				else if (/magnet|magnetic/i.test(text)) p.specs['Motstand'] = 'Magnetisk motstand';
				else p.specs['Motstand'] = price >= 10000 ? 'Luftmotstand' : 'Magnetisk motstand';
			}
			if (!p.specs['Brukervekt']) {
				var rwm = text.match(/(\d{2,3})\s*kg/i);
				p.specs['Brukervekt'] = rwm ? rwm[1] + ' kg' : (price >= 11000 ? '227 kg' : '135 kg');
			}
			if (!p.specs['Skjerm']) {
				p.specs['Skjerm'] = /pm5/i.test(text) ? 'PM5' : (/touch/i.test(text) ? 'Touchskjerm' : 'LCD-display');
			}
			if (!p.specs['Plassbesparende'] && !p.specs['Sammenleggbar']) {
				p.specs['Plassbesparende'] = 'Ja (høykant)';
			}
			return p.specs;
		}

		// 3. Sykkel / Spinning
		if (cat.indexOf('sykkel') !== -1 || text.indexOf('sykkel') !== -1 || text.indexOf('spinning') !== -1 || text.indexOf('ergometer') !== -1) {
			if (!p.specs['Svinghjul']) {
				var fwm = text.match(/(?:svinghjul|flywheel)[:\s]*(\d+(?:[.,]\d+)?)\s*kg/i) || text.match(/(\d+)\s*kg\s*svinghjul/i);
				p.specs['Svinghjul'] = fwm ? fwm[1].replace(',', '.') + ' kg' : (price >= 15000 ? '20 kg' : (price >= 8000 ? '14 kg' : '9 kg'));
			}
			if (!p.specs['Motstand']) {
				p.specs['Motstand'] = 'Magnetisk motstand';
			}
			if (!p.specs['Brukervekt']) {
				p.specs['Brukervekt'] = price >= 12000 ? '150 kg' : '130 kg';
			}
			if (!p.specs['App-støtte']) {
				p.specs['App-støtte'] = /bluetooth|zwift|kinomap|app/i.test(text) ? 'Ja (Bluetooth)' : 'Ja';
			}
			return p.specs;
		}

		// 4. Ellipsemaskiner
		if (cat.indexOf('ellipse') !== -1 || text.indexOf('ellipse') !== -1 || text.indexOf('crosstrainer') !== -1) {
			if (!p.specs['Steglengde']) p.specs['Steglengde'] = price >= 14000 ? '51 cm' : '45 cm';
			if (!p.specs['Svinghjul']) p.specs['Svinghjul'] = price >= 14000 ? '12 kg' : '9 kg';
			if (!p.specs['Brukervekt']) p.specs['Brukervekt'] = price >= 14000 ? '150 kg' : '135 kg';
			return p.specs;
		}

		// 5. Massasjepistoler
		if (cat.indexOf('massasje') !== -1 || text.indexOf('massasjepistol') !== -1 || text.indexOf('theragun') !== -1) {
			if (!p.specs['Slaglengde']) {
				var am = text.match(/(?:slaglengde|amplitude)[:\s]*(\d+(?:[.,]\d+)?)\s*mm/i) || text.match(/(\d+)\s*mm\b/i);
				p.specs['Slaglengde'] = am ? am[1] + ' mm' : (price >= 3000 ? '16 mm (Dypvev)' : '12 mm');
			}
			if (!p.specs['Batteritid']) p.specs['Batteritid'] = '180 min';
			if (!p.specs['Lydnivå']) p.specs['Lydnivå'] = 'Under 55 dB';
			return p.specs;
		}

		// 6. Styrke
		if (cat.indexOf('styrke') !== -1 || /manual|kettlebell|vektstang|rack|stativ|benk/i.test(cat + ' ' + text)) {
			if (!p.specs['Maksbelastning'] && !p.specs['Vekt']) {
				var cap = text.match(/(?:maksbelastning|kapasitet|maksvekt)[:\s]*(\d+)\s*kg/i);
				if (cap) p.specs['Maksbelastning'] = cap[1] + ' kg';
				else {
					var wvm = (p.name || '').match(/(\d+(?:[.,]\d+)?)\s*kg\b/i);
					if (wvm) p.specs['Vekt'] = wvm[1] + ' kg';
					else p.specs['Maksbelastning'] = '350 kg';
				}
			}
			if (!p.specs['Materiale']) p.specs['Materiale'] = 'Massivt stål';
			return p.specs;
		}

		// 7. Generell
		if (Object.keys(p.specs).length === 0) {
			p.specs['Kategori'] = p.category || 'Treningsutstyr';
			p.specs['Merke'] = p.brand || 'Kvalitetsutstyr';
			p.specs['Garanti'] = 'Norsk forbrukergaranti';
		}
		return p.specs;
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

	// Quick Filter Definitions per Department (Tier 1 Exposed Filters)
	var QUICK_FILTER_DEFS = {
		universal: [
			{ id: 'in_stock', label: 'Kun på lager', test: function (p) { return p.inStock === true; } },
			{ id: 'on_sale', label: 'På tilbud', test: function (p) { return (p.discount && p.discount > 0) || (p.originalPrice && p.originalPrice > p.currentPrice); } }
		],
		kondisjon: [
			{ id: 'intervall', label: '20+ km/t (intervall)', test: function (p) { return (p.specs && p.specs['Toppfart'] && parseFloat(p.specs['Toppfart']) >= 20) || (p.name && /2[0-9]\s*km|f85/i.test(p.name)); } },
			{ id: 'kraftig_motor', label: '4.0+ HK motor', test: function (p) { return p.specs && p.specs['Motor'] && parseFloat(p.specs['Motor']) >= 4.0; } },
			{ id: 'hoy_vekt', label: 'Maksvekt 150+ kg', test: function (p) { return p.specs && p.specs['Brukervekt'] && parseFloat(p.specs['Brukervekt']) >= 150; } }
		],
		styrke: [
			{ id: 'justerbar', label: 'Justerbare', test: function (p) { return (p.specs && p.specs['Plassbesparende'] === 'Ja') || (p.specs && p.specs['Type'] && p.specs['Type'].indexOf('Justerbar') !== -1) || (p.name && p.name.indexOf('Justerbare') !== -1); } },
			{ id: 'tung_last', label: '300+ kg belastning', test: function (p) { return p.specs && p.specs['Maksbelastning'] && parseFloat(p.specs['Maksbelastning']) >= 300; } },
			{ id: 'under_2000', label: 'Under 2 000,-', test: function (p) { return p.currentPrice < 2000; } }
		],
		rehabilitering: [
			{ id: 'neoprenfri', label: 'Uten neopren', test: function (p) { return p.specs && p.specs['Neopren'] === 'Nei'; } },
			{ id: 'patella', label: 'Patella-avlastning', test: function (p) { return p.specs && p.specs['Støttenivå'] && p.specs['Støttenivå'].indexOf('Patella') !== -1; } },
			{ id: 'under_1000', label: 'Under 1 000,-', test: function (p) { return p.currentPrice < 1000; } }
		],
		restitusjon: [
			{ id: 'dypvev', label: 'Dypvev (14+ mm)', test: function (p) { return (p.specs && p.specs['Slaglengde'] && parseFloat(p.specs['Slaglengde']) >= 14) || (p.name && /g5|theragun/i.test(p.name)); } },
			{ id: 'lang_batteri', label: '150+ min batteri', test: function (p) { return p.specs && p.specs['Batteri'] && parseFloat(p.specs['Batteri']) >= 150; } }
		]
	};

	function formatPrice(price) {
		if (price === null || price === undefined || isNaN(price) || !price) {
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

	// Merchant domain resolution & avatars (Parity with Prototype & Gear Picker)
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
			return '<span class="ge-m3-shop-pill-avatar">' +
				'<img src="' + escapeHtml(logoUrl) + '" alt="' + escapeHtml(shopName) + '" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display=\'none\';if(this.nextElementSibling){this.nextElementSibling.style.display=\'inline-flex\';}" />' +
				'<span class="ge-m3-shop-pill-inits" style="display:none;">' + escapeHtml(inits) + '</span>' +
			'</span>';
		}
		return '<span class="ge-m3-shop-pill-avatar"><span class="ge-m3-shop-pill-inits">' + escapeHtml(inits) + '</span></span>';
	}

	function renderMerchantLogoHtml(shopName) {
		var logoUrl = getMerchantFavicon(shopName);
		var logo = logoUrl
			? '<img src="' + escapeHtml(logoUrl) + '" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-flex\';" />'
			: '';
		return '<span class="ge-m3-merchant-logo" aria-hidden="true">' +
			logo +
			'<span class="ge-m3-merchant-logo-fallback material-symbols-outlined">storefront</span>' +
		'</span>';
	}

	// Filter chips for brands and shops (selected state retains shop logos)
	function renderPills(container, items, activeItems, onToggle, isShop) {
		container.innerHTML = '';
		items.forEach(function (item) {
			var isActive = item === 'Alle' ? activeItems.length === 0 : activeItems.indexOf(item) !== -1;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ge-m3-filter-pill ge-filter-pill-btn m3-sl' + (isShop && item !== 'Alle' ? ' ge-m3-filter-pill-shop' : '') + (isActive ? ' is-active' : '');
			btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');

			if (isShop && item !== 'Alle') {
				btn.innerHTML = CHIP_CHECK + renderShopAvatarHtml(item) + '<span>' + escapeHtml(item) + '</span>';
			} else {
				btn.innerHTML = CHIP_CHECK + '<span>' + escapeHtml(item) + '</span>';
			}

			btn.addEventListener('click', function () { onToggle(item); });
			container.appendChild(btn);
		});
	}

	function stockHtml(inStock, cls) {
		return '<span class="' + cls + ' ' + (inStock ? 'is-in-stock' : 'is-out-of-stock') + '" title="' + (inStock ? 'På lager' : 'Utsolgt') + '">' +
			'<span class="ge-m3-stock-dot"></span><span class="ge-m3-stock-label">' + (inStock ? 'På lager' : 'Utsolgt') + '</span>' +
		'</span>';
	}

	// Drawer inside list cards & overlay inside grid cards
	function renderListDrawerHtml(p, isOverlay) {
		var stores = (p.stores && p.stores.length > 0) ? p.stores : [
			{ shop: p.shop || 'Nettbutikk', price: p.currentPrice, inStock: p.inStock, isBest: true, url: p.url || '#' }
		];
		var lowestPrice = p.currentPrice || stores[0].price;
		var maxPrice = stores[stores.length - 1].price;

		var h = '<div class="ge-m3-ldrawer' + (isOverlay ? ' ge-m3-stores-table ge-m3-stores-popover ge-m3-grid-stores' : '') + '">' +
			'<div class="ge-m3-ldrawer-head">' +
				'<div class="ge-m3-ldrawer-info">' +
					'<span class="ge-m3-brand">' + escapeHtml(p.brand || 'UTSTYR') + '</span>' +
					'<h4 class="ge-m3-ldrawer-title">' + escapeHtml(p.name) + '</h4>' +
					'<span class="ge-m3-ldrawer-meta">Prissammenligning (' + stores.length + ' butikker)</span>' +
					'<span class="ge-m3-ldrawer-range">Fra ' + formatPrice(lowestPrice) + ' til ' + formatPrice(maxPrice) + '</span>' +
				'</div>' +
				'<button type="button" class="ge-m3-icon-btn m3-sl" data-close-id="' + p.id + '" title="Lukk forhandlerliste" aria-label="Lukk">' + ic('close') + '</button>' +
			'</div>' +
			'<div class="ge-m3-ldrawer-rows">';

		stores.forEach(function (st) {
			var isBest = st.price === lowestPrice;
			var rowClass = 'ge-m3-ldrow ge-m3-ldrow-link' + (isBest ? ' is-best-deal' : '');
			var storeAction = isOverlay ? ic('shopping_cart') : 'Kjøp';
			h += '<a href="' + escapeHtml(st.url || '#') + '" target="_blank" rel="noopener sponsored" aria-label="Kjøp hos ' + escapeHtml(st.shop) + ' for ' + formatPrice(st.price) + (st.inStock ? ', på lager' : ', utsolgt') + '" class="' + rowClass + '">' +
				(isBest ? '<span class="ge-m3-store-tag">Best pris</span>' : '') +
				'<span class="ge-m3-ldrow-name">' +
					renderMerchantLogoHtml(st.shop) +
					escapeHtml(st.shop) +
					'<span class="ge-m3-ldrow-dot ' + (st.inStock ? 'is-in-stock' : 'is-out-of-stock') + '" title="' + (st.inStock ? 'På lager' : 'Utsolgt') + '"></span>' +
					'<span class="ge-m3-sr-only">' + (st.inStock ? 'På lager' : 'Utsolgt') + '</span>' +
				'</span>' +
				'<span class="ge-m3-ldrow-right">' +
					'<span class="ge-m3-ldrow-price">' + formatPrice(st.price) + '</span>' +
					'<span class="ge-m3-store-btn ge-m3-btn ge-m3-btn--filled m3-sl" aria-hidden="true">' + storeAction + '</span>' +
				'</span>' +
			'</a>';
		});

		return h + '</div></div>';
	}

	function renderStoresDrawer(p, isOverlay) {
		if (!p.stores || p.stores.length === 0) return '';
		return renderListDrawerHtml(p, isOverlay);
	}

	function compareToggleHtml(p, isChecked) {
		return '<label class="ge-m3-cmp-toggle ge-card-cmp-label">' +
			'<input type="checkbox" class="ge-m3-cmp-chk ge-comp-chk" data-id="' + p.id + '" ' + (isChecked ? 'checked' : '') + ' />' +
			'<span class="ge-m3-cmp-box ge-list-compare-box" aria-hidden="true"></span>' +
			'<span>Sammenlign</span>' +
		'</label>';
	}

	function priceRowHtml(p, showSavings, save) {
		return '<div class="ge-m3-price-row">' +
			'<span class="ge-m3-price-current">' + formatPrice(p.currentPrice) + '</span>' +
			(p.originalPrice ? '<span class="ge-m3-price-original">' + formatPrice(p.originalPrice) + '</span>' : '') +
			(showSavings ? '<span class="ge-m3-savings-chip ge-savings-chip">Spar ' + formatPrice(save) + '</span>' : '') +
		'</div>';
	}

	// Retailer disclosure row (several stores) or static row (one store)
	function shopRowHtml(p, storeCount, isOpen, titleText, isList) {
		var listCls = isList ? ' ge-m3-shop-row--list ge-list-shop-row' : '';
		var hasStores = storeCount > 1 && p.stores && p.stores.length > 1;
		if (hasStores) {
			return '<button type="button" class="ge-m3-shop-compare-trigger m3-sl' + listCls + (isOpen ? ' is-open' : '') + '" data-id="' + p.id + '" aria-expanded="' + (isOpen ? 'true' : 'false') + '" title="' + escapeHtml(titleText) + '">' +
				'<span class="ge-m3-shop-name">' +
					renderMerchantLogoHtml(p.shop) +
					'<span class="ge-m3-shop-line"><span class="ge-m3-shop-prefix">Best pris:</span> <b>' + escapeHtml(p.shop || 'Nettbutikk') + '</b></span>' +
				'</span>' +
				(isList
					? '<span class="ge-m3-compare-badge">' + storeCount + ' butikker ' + (isOpen ? ic('arrowUp') : ic('arrowDown')) + '</span>'
					: '<span class="ge-m3-compare-badge" title="' + storeCount + ' butikker" aria-label="' + storeCount + ' butikker">' + storeCount + (isOpen ? ic('arrowUp') : ic('arrowDown')) + '</span>') +
			'</button>';
		}
		return '<div class="ge-m3-single-shop-row' + listCls + '">' +
			'<div class="ge-m3-shop-name">' +
				renderMerchantLogoHtml(p.shop) +
				'<b>' + escapeHtml(p.shop || 'Nettbutikk') + '</b>' +
			'</div>' +
			stockHtml(p.inStock, 'ge-m3-stock-badge') +
		'</div>';
	}

	// RENDER 1: GRID CARD
	function renderGridCardHtml(p, state) {
		var hasDisc = p.discount && p.discount > 0;
		var storeCount = (p.stores && p.stores.length > 0) ? p.stores.length : ((p.otherShops || 0) + 1);
		var isOpen = !!(state.openStores && state.openStores[p.id]);
		var isChecked = state.selectedForCompare.indexOf(p.id) !== -1;
		var save = (p.originalPrice && p.originalPrice > p.currentPrice) ? (p.originalPrice - p.currentPrice) : 0;
		var showSavings = state.sortBy === 'savings_desc' && save > 0;
		var safeUrl = p.url || '#';
		var safeImg = p.image && p.image.length > 5 ? p.image : '';

		return '' +
		'<div class="ge-m3-card-grid ge-card-grid">' +
			compareToggleHtml(p, isChecked) +
			'<div class="ge-m3-img-wrap ge-card-grid-img-wrap" data-detail-id="' + p.id + '" title="Vis produktdetaljer og spesifikasjoner">' +
				(hasDisc ? '<span class="ge-m3-deal-chip ge-discount-badge">-' + p.discount + '%</span>' : '') +
				(safeImg ? '<img src="' + escapeHtml(safeImg) + '" alt="' + escapeHtml(p.name) + '" class="ge-m3-product-img" onerror="this.style.display=\'none\';" />' : '') +
			'</div>' +
			'<div class="ge-m3-card-content">' +
				'<span class="ge-m3-brand">' + escapeHtml(p.brand || 'UTSTYR') + '</span>' +
				'<h3 class="ge-m3-card-title" data-detail-id="' + p.id + '" tabindex="0">' + escapeHtml(p.name) + '</h3>' +
				'<div class="ge-m3-card-bottom">' +
					priceRowHtml(p, showSavings, save) +
					shopRowHtml(p, storeCount, isOpen, 'Klikk for å sammenligne priser fra alle ' + storeCount + ' butikker', false) +
					(isOpen && storeCount > 1 && p.stores && p.stores.length > 1 ? renderStoresDrawer(p, true) : '') +
					'<a href="' + escapeHtml(safeUrl) + '" target="_blank" rel="noopener sponsored" class="ge-m3-grid-cta" aria-label="Se tilbud hos ' + escapeHtml(p.shop || 'butikk') + '">' +
						'Til butikk ' + ic('ext') +
					'</a>' +
				'</div>' +
			'</div>' +
		'</div>';
	}

	// RENDER 2: LIST CARD (2-column responsive card layout)
	function renderListCardHtml(p, state) {
		var hasDisc = p.discount && p.discount > 0;
		var storeCount = (p.stores && p.stores.length > 0) ? p.stores.length : ((p.otherShops || 0) + 1);
		var isOpen = !!(state.openStores && state.openStores[p.id]);
		var isChecked = state.selectedForCompare.indexOf(p.id) !== -1;
		var save = (p.originalPrice && p.originalPrice > p.currentPrice) ? (p.originalPrice - p.currentPrice) : 0;
		var showSavings = state.sortBy === 'savings_desc' && save > 0;
		var safeUrl = p.url || '#';
		var safeImg = p.image && p.image.length > 5 ? p.image : '';

		return '' +
		'<div class="ge-m3-card-list ge-card-list">' +
			'<div class="ge-m3-list-main-row ge-list-main-row">' +
				'<div class="ge-m3-list-img-wrap" data-detail-id="' + p.id + '" title="Vis produktdetaljer og spesifikasjoner">' +
					(hasDisc ? '<span class="ge-m3-deal-chip ge-discount-badge">-' + p.discount + '%</span>' : '') +
					(safeImg ? '<img src="' + escapeHtml(safeImg) + '" alt="' + escapeHtml(p.name) + '" class="ge-m3-product-img" onerror="this.style.display=\'none\';" />' : '') +
				'</div>' +
				'<div class="ge-m3-list-content">' +
					'<div class="ge-m3-list-top">' +
						'<span class="ge-m3-brand">' + escapeHtml(p.brand || 'UTSTYR') + '</span>' +
						compareToggleHtml(p, isChecked) +
					'</div>' +
					'<h3 class="ge-m3-card-title" data-detail-id="' + p.id + '" tabindex="0">' + escapeHtml(p.name) + '</h3>' +
					'<div class="ge-m3-list-action-row">' +
						priceRowHtml(p, showSavings, save) +
						'<a href="' + escapeHtml(safeUrl) + '" target="_blank" rel="noopener sponsored" class="ge-m3-cta-btn ge-m3-btn ge-m3-btn--filled ge-m3-list-cta m3-sl">' +
							'Til butikk ' + ic('ext') +
						'</a>' +
					'</div>' +
				'</div>' +
			'</div>' +
			shopRowHtml(p, storeCount, isOpen, 'Klikk for å sammenligne forhandlere', true) +
			(isOpen && storeCount > 1 && p.stores && p.stores.length > 1 ? '<div class="ge-m3-list-drawer-wrap">' + renderStoresDrawer(p, false) + '</div>' : '') +
		'</div>';
	}

	function initPriceComparison() {
		var root = document.getElementById('fysionett-prissok-root');
		if (!root) return;

		root.classList.add('ge-m3-comp-wrapper');

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

		// Ensure every product has a normalized stores array and specs
		products.forEach(function (p) {
			if (!p.department) {
				p.department = inferDepartment(p);
			}
			ensureProductSpecs(p);
			if (!Array.isArray(p.stores) || p.stores.length === 0) {
				p.stores = [
					{
						shop: p.shop || 'Nettbutikk',
						price: p.currentPrice || 0,
						inStock: p.inStock !== false,
						isBest: true,
						url: p.url || '#'
					}
				];
			}
		});

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
		var initialOpenId = (urlParams && urlParams.get('open')) ? parseInt(urlParams.get('open'), 10) : 0;

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
			openStores: initialOpenId > 0 ? (function () { var o = {}; o[initialOpenId] = true; return o; })() : {},
			activeProductId: initialOpenId > 0 ? initialOpenId : null,
			minPrice: '',
			maxPrice: '',
			absMinPrice: 0,
			absMaxPrice: 100000,
			sortBy: 'relevant',
			visibleCount: initialLimit,
			showFilters: false
		};

		function computeDepartmentPriceBounds() {
			var validPrices = state.products
				.filter(function (p) {
					var d = p.department || inferDepartment(p);
					return d === state.activeDepartment;
				})
				.map(function (p) { return p.currentPrice; })
				.filter(function (p) { return !isNaN(p) && p > 0; });

			if (validPrices.length === 0) {
				validPrices = state.products
					.map(function (p) { return p.currentPrice; })
					.filter(function (p) { return !isNaN(p) && p > 0; });
			}

			if (validPrices.length > 0) {
				state.absMinPrice = Math.floor(Math.min.apply(null, validPrices));
				state.absMaxPrice = Math.ceil(Math.max.apply(null, validPrices));
			}
		}

		computeDepartmentPriceBounds();

		// Render the initial static layout skeleton matching the Material 3 prototype
		renderSkeleton(root, state);

		var filterHost = root.closest('.ge-price-comp-wrapper') || root;
		var deptTrack = root.querySelector('#ge-dept-track');
		var catsBar = root.querySelector('#ge-comp-cats-bar');
		var deptAlert = root.querySelector('#ge-dept-alert');
		var searchBox = root.querySelector('#ge-search-box');
		var headerSearchSlot = root.querySelector('#ge-header-search-slot');
		var mobileSearchSlot = root.querySelector('#ge-mobile-search-slot');
		var controls = root.querySelector('.ge-m3-controls');
		var resultsCountWrap = root.querySelector('.ge-m3-results-count');
		var searchInput = root.querySelector('#ge-search-input');
		var searchClear = root.querySelector('#ge-search-clear');
		var sortSelect = root.querySelector('#ge-sort-select');
		var filterToggleBtn = root.querySelector('#ge-filter-toggle');
		var filterBadge = root.querySelector('#ge-comp-filter-badge');
		var viewGridBtn = root.querySelector('#ge-view-grid-btn');
		var viewListBtn = root.querySelector('#ge-view-list-btn');
		var resultsCount = root.querySelector('#ge-comp-results-count');
		var cardsContainer = root.querySelector('#ge-comp-cards-container');
		var loadMoreWrap = root.querySelector('#ge-comp-load-more-wrap');
		var guideBanner = root.querySelector('#ge-guide-banner');
		var guideDismiss = root.querySelector('#ge-guide-dismiss');

		if (guideDismiss && guideBanner) {
			guideDismiss.addEventListener('click', function () {
				guideBanner.hidden = true;
			});
		}

		// Filter sheet elements (mounted on filterHost outside container-type for clean fixed positioning)
		var filterScrim = filterHost.querySelector('#ge-comp-filter-scrim');
		var filterPanel = filterHost.querySelector('#ge-comp-filter-panel');
		var filterCloseBtn = filterHost.querySelector('#ge-filter-close-btn');
		var quickFiltersPanelSlot = filterHost.querySelector('#ge-comp-quick-filters-panel-slot');
		var quickFiltersWrap = filterHost.querySelector('#ge-comp-quick-filters');
		var priceMinInput = filterHost.querySelector('#ge-price-min-input');
		var priceMaxInput = filterHost.querySelector('#ge-price-max-input');
		var sliderMin = filterHost.querySelector('#ge-slider-min');
		var sliderMax = filterHost.querySelector('#ge-slider-max');
		var sliderFillBar = filterHost.querySelector('#ge-slider-fill-bar');
		var brandsWrap = filterHost.querySelector('#ge-filter-brands-wrap');
		var shopsWrap = filterHost.querySelector('#ge-filter-shops-wrap');
		var filterResetBtn = filterHost.querySelector('#ge-filter-reset-btn');
		var filterApplyBtn = filterHost.querySelector('#ge-filter-apply-btn');

		if (searchInput && state.searchQuery) {
			searchInput.value = state.searchQuery;
		}

		// Placement: search is always positioned in header slot directly beneath title and subtitle
		function syncSearchPlacement() {
			if (!searchBox) return;
			var target = headerSearchSlot || root.querySelector('#ge-header-search-slot');
			if (target && searchBox.parentNode !== target) {
				target.appendChild(searchBox);
			}
		}

		function syncQuickFiltersPlacement() {
			syncSearchPlacement();
			var isMobileLayout = root.clientWidth <= 599;
			root.classList.toggle('is-mobile-layout', isMobileLayout);
			if (quickFiltersWrap && quickFiltersPanelSlot && quickFiltersWrap.parentNode !== quickFiltersPanelSlot) {
				quickFiltersPanelSlot.appendChild(quickFiltersWrap);
			}
		}

		// Render Level 1: Main Department Tabs with text indicator underline
		function renderDepartmentTabs() {
			if (!deptTrack) return;
			deptTrack.innerHTML = '';

			DEPARTMENTS.forEach(function (dept) {
				var isActive = state.activeDepartment === dept.id;
				var count = state.products.filter(function (p) {
					var d = p.department || inferDepartment(p);
					return d === dept.id;
				}).length;

				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'ge-m3-dept-btn ge-dept-btn m3-sl' + (isActive ? ' is-active' : '');
				btn.setAttribute('data-department', dept.id);
				btn.setAttribute('role', 'tab');
				btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
				if (count === 0 && !isActive) {
					btn.disabled = true;
					btn.setAttribute('aria-disabled', 'true');
				}
				btn.innerHTML = '<span>' + escapeHtml(dept.name) + '</span>' +
					(count > 0 ? '<span class="ge-m3-dept-count ge-dept-badge">' + count + '</span>' : '');

				btn.addEventListener('click', function () {
					if (state.activeDepartment === dept.id) return;
					switchDepartment(dept.id);
				});
				deptTrack.appendChild(btn);
			});
		}

		// Render Level 2: Sub-category Chips for Active Department with counts
		function renderCategoryChips() {
			if (!catsBar) return;
			catsBar.innerHTML = '';

			var currentDeptObj = DEPARTMENTS.filter(function (d) {
				return d.id === state.activeDepartment;
			})[0] || DEPARTMENTS[0];

			var cats = currentDeptObj.categories.slice();

			// Append any extra product categories for this department from feed
			state.products.forEach(function (p) {
				var d = p.department || inferDepartment(p);
				if (d === state.activeDepartment && p.category && p.category !== 'Annet' && cats.indexOf(p.category) === -1) {
					cats.push(p.category);
				}
			});

			function countFor(cat) {
				return state.products.filter(function (p) {
					var d = p.department || inferDepartment(p);
					return d === state.activeDepartment && (cat === 'Alle' || p.category === cat);
				}).length;
			}

			cats.forEach(function (cat) {
				var isActive = state.activeCategory === cat;
				var count = countFor(cat);
				if (count === 0 && cat !== 'Alle' && !isActive) {
					return;
				}

				var chip = document.createElement('button');
				chip.type = 'button';
				chip.className = 'ge-m3-chip ge-cat-pill ge-m3-cat-link m3-sl' + (isActive ? ' is-active' : '');
				chip.setAttribute('data-category', cat);
				chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
				chip.innerHTML = '<span>' + escapeHtml(cat) + '</span>' +
					(count > 0 || cat === 'Alle' ? '<span class="ge-m3-cat-count">' + count + '</span>' : '');

				chip.addEventListener('click', function () {
					switchCategory(cat);
				});
				catsBar.appendChild(chip);
			});
		}

		// Render Tier 1: Quick Filter Chips
		function renderQuickFilters() {
			if (!quickFiltersWrap) return;
			quickFiltersWrap.innerHTML = '';

			var allDefs = (QUICK_FILTER_DEFS.universal || []).concat(QUICK_FILTER_DEFS[state.activeDepartment] || []);

			allDefs.forEach(function (def) {
				var isActive = !!state.activeQuickFilters[def.id];
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'ge-m3-qchip ge-quick-chip m3-sl' + (isActive ? ' is-active' : '');
				btn.setAttribute('data-filter-id', def.id);
				btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
				btn.innerHTML = CHIP_CHECK + '<span>' + escapeHtml(def.label) + '</span>';

				btn.addEventListener('click', function () {
					if (state.activeQuickFilters[def.id]) {
						delete state.activeQuickFilters[def.id];
					} else {
						state.activeQuickFilters[def.id] = true;
					}
					renderQuickFilters();
					state.visibleCount = initialLimit;
					updateUI();
					var refocused = quickFiltersWrap.querySelector('[data-filter-id="' + def.id + '"]');
					if (refocused) refocused.focus();
				});
				quickFiltersWrap.appendChild(btn);
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

			computeDepartmentPriceBounds();
			if (sliderMin && sliderMax) {
				sliderMin.min = state.absMinPrice;
				sliderMin.max = state.absMaxPrice;
				sliderMax.min = state.absMinPrice;
				sliderMax.max = state.absMaxPrice;
			}

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

				if (state.activeProductId) {
					url.searchParams.set('open', String(state.activeProductId));
				} else {
					url.searchParams.delete('open');
				}

				if (url.toString() !== window.location.href) {
					window.history.pushState({
						dept: state.activeDepartment,
						cat: state.activeCategory,
						q: state.searchQuery,
						open: state.activeProductId || null
					}, '', url.toString());
				}
			} catch (e) {}
		}

		renderDepartmentTabs();
		renderCategoryChips();
		renderQuickFilters();
		syncQuickFiltersPlacement();

		if (typeof ResizeObserver !== 'undefined') {
			new ResizeObserver(syncQuickFiltersPlacement).observe(root);
		} else {
			window.addEventListener('resize', syncQuickFiltersPlacement);
		}

		// Comparison Dock & Modal DOM nodes
		var floatingDock = document.getElementById('ge-floating-cmp-bar');
		var cmpModal = document.getElementById('ge-cmp-modal');
		var productModal = document.getElementById('ge-product-modal');

		if (!floatingDock) {
			var dockEl = document.createElement('aside');
			dockEl.id = 'ge-floating-cmp-bar';
			dockEl.className = 'ge-m3-cmp-bar ge-comp-dock';
			dockEl.style.display = 'none';
			dockEl.setAttribute('role', 'region');
			dockEl.setAttribute('aria-label', 'Sammenligning');
			dockEl.innerHTML = '<span class="ge-m3-cmp-bar-count">' +
				'<span class="material-symbols-outlined" aria-hidden="true">compare_arrows</span>' +
				'<span><b id="ge-cmp-selected-count">0</b> modeller valgt</span>' +
			'</span>' +
			'<span id="ge-dock-thumbs" class="ge-m3-cmp-bar-products" aria-label="Valgte produkter"></span>' +
			'<button type="button" id="ge-cmp-open-modal-btn" class="ge-m3-cmp-bar-btn ge-m3-btn ge-m3-btn--filled m3-sl" aria-label="Sammenlign spesifikasjoner">' +
				'<span class="ge-m3-cmp-action-full">Sammenlign spesifikasjoner</span>' +
				'<span class="ge-m3-cmp-action-compact">Sammenlign</span>' +
			'</button>' +
			'<button type="button" id="ge-cmp-clear-btn" class="ge-m3-btn ge-m3-btn--text m3-sl">Nullstill</button>';
			filterHost.appendChild(dockEl);
			floatingDock = dockEl;
		}

		if (!cmpModal) {
			var modalEl = document.createElement('div');
			modalEl.id = 'ge-cmp-modal';
			modalEl.className = 'ge-m3-modal-scrim ge-cmp-modal-scrim';
			modalEl.style.display = 'none';
			modalEl.setAttribute('role', 'dialog');
			modalEl.setAttribute('aria-modal', 'true');
			modalEl.setAttribute('aria-labelledby', 'ge-cmp-modal-title');
			modalEl.innerHTML = '<div class="ge-m3-modal-card ge-cmp-modal-card">' +
				'<div class="ge-m3-modal-head">' +
					'<div>' +
						'<h2 id="ge-cmp-modal-title" class="ge-m3-modal-title">Sammenlign modeller side-om-side</h2>' +
						'<p class="ge-m3-modal-supporting">Strukturerte nøkkeltall og prissammenligning for de valgte modellene</p>' +
					'</div>' +
					'<div class="ge-m3-modal-actions">' +
						'<label class="ge-m3-diff-toggle ge-cmp-diff-toggle m3-sl" title="Skjul spesifikasjoner der modellene har like verdier">' +
							'<input type="checkbox" id="ge-cmp-diff-only" class="ge-m3-diff-checkbox" />' +
							CHIP_CHECK +
							'<span>Vis kun ulikheter</span>' +
						'</label>' +
						'<button type="button" id="ge-modal-close-btn" class="ge-m3-modal-close ge-m3-icon-btn m3-sl" title="Lukk sammenligning" aria-label="Lukk sammenligning">' + ic('close') + '</button>' +
					'</div>' +
				'</div>' +
				'<div id="ge-cmp-modal-body" class="ge-m3-modal-body"></div>' +
			'</div>';
			filterHost.appendChild(modalEl);
			cmpModal = modalEl;
		}

		if (!productModal) {
			var prodModalEl = document.createElement('div');
			prodModalEl.id = 'ge-product-modal';
			prodModalEl.className = 'ge-m3-modal-scrim ge-product-modal-scrim';
			prodModalEl.style.display = 'none';
			prodModalEl.setAttribute('role', 'dialog');
			prodModalEl.setAttribute('aria-modal', 'true');
			prodModalEl.setAttribute('aria-labelledby', 'ge-product-modal-title');
			prodModalEl.innerHTML = '<div class="ge-m3-modal-card ge-product-modal-card">' +
				'<div id="ge-product-modal-content"></div>' +
			'</div>';
			filterHost.appendChild(prodModalEl);
			productModal = prodModalEl;
		}

		var productModalContent = document.getElementById('ge-product-modal-content');

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
				root.classList.add('has-compare-bar');
				if (dockSelectedCount) dockSelectedCount.textContent = count;
				if (dockBtnCount) dockBtnCount.textContent = count;
				if (dockThumbs) {
					dockThumbs.innerHTML = state.products
						.filter(function (prod) { return state.selectedForCompare.indexOf(prod.id) !== -1; })
						.map(function (prod) {
							return '<img class="ge-m3-cmp-bar-product" src="' + escapeHtml(prod.image) + '" alt="' + escapeHtml(prod.name) + '" title="' + escapeHtml(prod.name) + '" onerror="this.style.display=\'none\';" />';
						})
						.join('');
				}
			} else {
				floatingDock.style.display = 'none';
				root.classList.remove('has-compare-bar');
				if (dockThumbs) dockThumbs.innerHTML = '';
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

			if (diffOnlyChk) {
				diffOnlyChk.checked = onlyDiff;
				var toggleLabel = diffOnlyChk.closest('.ge-m3-diff-toggle, .ge-cmp-diff-toggle');
				if (toggleLabel) toggleLabel.classList.toggle('is-active', onlyDiff);
			}

			var specKeys = [];
			selected.forEach(function (p) {
				ensureProductSpecs(p);
				if (p.specs && typeof p.specs === 'object' && !Array.isArray(p.specs)) {
					for (var k in p.specs) {
						if (Object.prototype.hasOwnProperty.call(p.specs, k) && specKeys.indexOf(k) === -1) {
							specKeys.push(k);
						}
					}
				}
			});

			var priorityKeys = [
				'Toppfart', 'Motor', 'Løpeflate', 'Maks stigning', 'Stigning', 'Brukervekt', 'Motstand',
				'Svinghjul', 'Steglengde', 'Slaglengde', 'Batteri', 'Maksbelastning',
				'Skjerm', 'Sammenleggbar', 'Plassbesparende', 'Bluetooth / App', 'App-støtte',
				'Støttenivå', 'Materiale', 'Setehøyde', 'Lydnivå', 'Vekt', 'Kategori', 'Merke', 'Garanti'
			];
			specKeys.sort(function (a, b) {
				var idxA = priorityKeys.indexOf(a);
				var idxB = priorityKeys.indexOf(b);
				if (idxA === -1 && idxB === -1) return a.localeCompare(b);
				if (idxA === -1) return 1;
				if (idxB === -1) return -1;
				return idxA - idxB;
			});

			var colWidth = selected.length <= 2 ? 'calc(' + (100 / selected.length) + '% - ' + (150 / selected.length) + 'px)' : '260px';

			function isLowestOf(p) { return selected.length > 1 && p.currentPrice === minPrice; }
			function cell(p, inner) {
				return '<td class="matrix-prod-col' + (isLowestOf(p) ? ' is-best-col' : '') + '">' + inner + '</td>';
			}

			var h = '<div class="ge-m3-matrix-wrap ge-matrix-wrap"><table class="ge-m3-matrix-table ge-matrix-table">';
			h += '<colgroup>';
			h += '<col style="width:150px; min-width:150px;">';
			selected.forEach(function () {
				h += '<col style="width:' + colWidth + '; min-width:260px;">';
			});
			h += '</colgroup>';

			// 1. Header row with compact cards
			h += '<tr class="matrix-header-row"><th class="matrix-label-col"><span class="ge-m3-matrix-count">Modeller (' + selected.length + ')</span></th>';
			selected.forEach(function (p) {
				var isLowest = isLowestOf(p);
				h += '<td class="matrix-prod-col' + (isLowest ? ' is-best-col' : '') + '">' +
					'<div class="ge-m3-matrix-card-top ge-matrix-card-top' + (isLowest ? ' is-best-deal' : '') + '">' +
						(isLowest ? '<span class="ge-m3-matrix-best-tag ge-matrix-best-tag">Best pris</span>' : '') +
						'<button type="button" class="ge-m3-matrix-remove-btn ge-matrix-remove-btn ge-m3-icon-btn m3-sl" data-remove-id="' + p.id + '" title="Fjern fra sammenligning" aria-label="Fjern ' + escapeHtml(p.name) + ' fra sammenligning">' +
							ic('close') +
						'</button>' +
						'<div class="ge-m3-matrix-thumb">' +
							(p.image ? '<img src="' + escapeHtml(p.image) + '" alt="' + escapeHtml(p.name) + '" onerror="this.style.display=\'none\';" />' : '') +
						'</div>' +
						'<span class="ge-m3-matrix-brand">' + escapeHtml(p.brand || 'UTSTYR') + '</span>' +
						'<h4 class="ge-m3-matrix-prod-title" title="' + escapeHtml(p.name) + '">' + escapeHtml(p.name) + '</h4>' +
						'<div class="ge-m3-matrix-price-row">' +
							'<span class="ge-m3-matrix-price">' + formatPrice(p.currentPrice) + '</span>' +
							(p.discount ? '<span class="ge-m3-spec-pill deal">-' + p.discount + '%</span>' : '') +
						'</div>' +
						'<a href="' + escapeHtml(p.url || '#') + '" target="_blank" rel="noopener sponsored" class="ge-m3-matrix-cta ge-m3-btn ge-m3-btn--filled m3-sl">' +
							'<span>Gå til ' + escapeHtml(p.shop || 'butikk') + '</span> ' + ic('ext') +
						'</a>' +
					'</div>' +
				'</td>';
			});
			h += '</tr>';

			// 2. Beste dagspris
			var isPriceIdentical = selected.every(function (p) { return p.currentPrice === selected[0].currentPrice; });
			if (!onlyDiff || !isPriceIdentical) {
				h += '<tr><th class="matrix-label-col">Beste dagspris</th>';
				selected.forEach(function (p) {
					h += cell(p,
						'<b class="ge-m3-matrix-price-cell">' + formatPrice(p.currentPrice) + '</b>' +
						(isLowestOf(p) ? ' <span class="ge-m3-spec-pill tert">Best pris</span>' : '')
					);
				});
				h += '</tr>';
			}

			// 3. Forhandler
			var isShopIdentical = selected.every(function (p) {
				var cA = p.stores ? p.stores.length : ((p.otherShops || 0) + 1);
				var cB = selected[0].stores ? selected[0].stores.length : ((selected[0].otherShops || 0) + 1);
				return p.shop === selected[0].shop && cA === cB;
			});
			if (!onlyDiff || !isShopIdentical) {
				h += '<tr><th class="matrix-label-col">Forhandler</th>';
				selected.forEach(function (p) {
					var count = p.stores ? p.stores.length : ((p.otherShops || 0) + 1);
					h += cell(p,
						'<span>' + escapeHtml(p.shop || 'Ukjent') + '</span> ' +
						(count > 1 ? '<span class="ge-m3-matrix-muted">(' + count + ' butikker)</span>' : '<span class="ge-m3-matrix-muted ge-m3-matrix-ok">(Eneste forhandler)</span>')
					);
				});
				h += '</tr>';
			}

			// 4. Førpris / Tilbud
			var isOrigIdentical = selected.every(function (p) { return (p.originalPrice || 0) === (selected[0].originalPrice || 0); });
			if (!onlyDiff || !isOrigIdentical) {
				h += '<tr><th class="matrix-label-col">Førpris / Tilbud</th>';
				selected.forEach(function (p) {
					if (p.originalPrice && p.originalPrice > p.currentPrice) {
						var save = p.originalPrice - p.currentPrice;
						h += cell(p,
							'<span class="ge-m3-matrix-muted"><s>' + formatPrice(p.originalPrice) + '</s></span>' +
							'<span class="ge-m3-spec-pill deal">Spar ' + formatPrice(save) + '</span>'
						);
					} else {
						h += cell(p, '<span class="ge-m3-matrix-muted">Ordinær pris</span>');
					}
				});
				h += '</tr>';
			}

			// 5. Lagerstatus
			var isStockIdentical = selected.every(function (p) { return p.inStock === selected[0].inStock; });
			if (!onlyDiff || !isStockIdentical) {
				h += '<tr><th class="matrix-label-col">Lagerstatus</th>';
				selected.forEach(function (p) {
					h += cell(p,
						'<span class="ge-m3-matrix-stock ' + (p.inStock ? 'is-in-stock' : 'is-out-of-stock') + '">' +
							'<span class="ge-m3-stock-dot"></span>' + (p.inStock ? 'På lager' : 'Utsolgt') +
						'</span>'
					);
				});
				h += '</tr>';
			}

			// 6. Kategori
			var isCatIdentical = selected.every(function (p) { return p.category === selected[0].category; });
			if (!onlyDiff || !isCatIdentical) {
				h += '<tr><th class="matrix-label-col">Kategori</th>';
				selected.forEach(function (p) {
					h += cell(p, '<span class="ge-m3-spec-pill sec">' + escapeHtml(p.category || 'Annet') + '</span>');
				});
				h += '</tr>';
			}

			// 7. Vær obs på (Anti-Fit)
			var allAntiFitNotes = selected.map(function (p) { return getAntiFitNotes(p).join(' | '); });
			var isAntiFitIdentical = allAntiFitNotes.every(function (n) { return n === allAntiFitNotes[0]; });
			if (!onlyDiff || !isAntiFitIdentical) {
				h += '<tr><th class="matrix-label-col">' +
					'<div class="ge-m3-matrix-label-with-icon">' + ic('info') + '<span>Vær obs på</span></div>' +
				'</th>';
				selected.forEach(function (p) {
					var notes = getAntiFitNotes(p);
					var hasRealNotes = notes.length > 0 && notes[0] !== 'Ingen spesielle forbehold registrert for standard bruk.';

					var boxClass = 'ge-m3-antifit-box ge-antifit-box' + (hasRealNotes ? '' : ' is-clean');
					var headerHtml = hasRealNotes
						? '<div class="ge-m3-antifit-header">' + ic('info') + ' <span>Obs</span></div>'
						: '<div class="ge-m3-antifit-header">' + ic('check') + ' <span>Allround-modell</span></div>';

					var noteHtml = notes.map(function (n) {
						var bullet = hasRealNotes ? '•' : '✓';
						return '<div class="ge-m3-antifit-note">' +
							'<span class="ge-m3-antifit-bullet">' + bullet + '</span>' +
							'<span>' + escapeHtml(n) + '</span>' +
						'</div>';
					}).join('');

					h += cell(p, '<div class="' + boxClass + '">' + headerHtml + noteHtml + '</div>');
				});
				h += '</tr>';
			}

			// 8. Dynamic Specs
			var higherBetterKeys = ['Toppfart', 'Motor', 'Brukervekt', 'Slaglengde', 'Maksbelastning', 'Batteri', 'Maks stigning', 'Svinghjul', 'Steglengde'];
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
					var hasDiff = selected.some(function (p) {
						var v = (p.specs && p.specs[key]) ? p.specs[key] : null;
						return v && parseFloat(v) !== maxNum;
					});
					if (!hasDiff) maxNum = -1;
				}

				h += '<tr><th class="matrix-label-col">' + escapeHtml(key) + '</th>';
				selected.forEach(function (p) {
					var rawVal = (p.specs && p.specs[key]) ? escapeHtml(p.specs[key]) : '<span class="ge-m3-matrix-muted">—</span>';
					var isWin = false;
					if (maxNum > -1 && p.specs && p.specs[key]) {
						var parsed = parseFloat(p.specs[key]);
						if (!isNaN(parsed) && parsed === maxNum) isWin = true;
					}

					h += cell(p, rawVal + (isWin ? ' <span class="ge-m3-spec-win ge-spec-win">Høyest</span>' : ''));
				});
				h += '</tr>';
			});

			// 9. Bunnrad med handlingsknapp
			h += '<tr class="matrix-action-row"><th class="matrix-label-col">Handling</th>';
			selected.forEach(function (p) {
				h += cell(p,
					'<a href="' + escapeHtml(p.url || '#') + '" target="_blank" rel="noopener sponsored" class="ge-m3-matrix-cta ge-m3-btn ge-m3-btn--tonal m3-sl">' +
						'<span>Se tilbud</span> ' + ic('ext') +
					'</a>'
				);
			});
			h += '</tr>';

			h += '</table></div>';

			if (selected.length > 1 && priceDiff > 0) {
				h += '<div class="ge-m3-matrix-summary">' +
					'<span>Prisspenn i sammenligningen: <b>' + formatPrice(minPrice) + '</b> – <b>' + formatPrice(maxPrice) + '</b></span>' +
					'<span class="ge-m3-matrix-summary-diff">Prisforskjell: ' + formatPrice(priceDiff) + '</span>' +
				'</div>';
			}

			cmpModalBody.innerHTML = h;

			var compareTable = cmpModalBody.querySelector('.ge-m3-matrix-table');
			if (compareTable) {
				var tableRows = compareTable.querySelectorAll('tr');
				var mobileList = document.createElement('div');
				mobileList.className = 'ge-m3-mobile-compare-list';
				mobileList.setAttribute('aria-label', 'Sammenligning per modell');
				mobileList.innerHTML = selected.map(function (p, productIndex) {
					var productCell = tableRows[0].cells[productIndex + 1];
					var specRows = [];
					for (var rowIndex = 1; rowIndex < tableRows.length; rowIndex++) {
						var row = tableRows[rowIndex];
						if (row.classList.contains('matrix-action-row')) continue;
						var labelCell = row.cells[0];
						var valueCell = row.cells[productIndex + 1];
						if (!labelCell || !valueCell) continue;
						specRows.push(
							'<div class="ge-m3-mobile-spec-row"><dt>' + labelCell.innerHTML + '</dt><dd>' + valueCell.innerHTML + '</dd></div>'
						);
					}

					return '<section class="ge-m3-mobile-product-card" aria-label="Sammenligning: ' + escapeHtml(p.name) + '">' +
						'<div class="ge-m3-mobile-product-head">' + (productCell ? productCell.innerHTML : '') + '</div>' +
						(specRows.length
							? '<dl class="ge-m3-mobile-product-specs">' + specRows.join('') + '</dl>'
							: '<p class="ge-m3-mobile-empty-specs">Ingen forskjeller å vise for denne modellen med valgt filter.</p>') +
					'</section>';
				}).join('');
				var matrixWrap = compareTable.parentNode;
				matrixWrap.parentNode.insertBefore(mobileList, matrixWrap.nextSibling);
			}

			// Attach remove button listeners
			cmpModalBody.querySelectorAll('.ge-m3-matrix-remove-btn').forEach(function (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					var id = parseInt(btn.getAttribute('data-remove-id'), 10);
					var idx = state.selectedForCompare.indexOf(id);
					if (idx !== -1) {
						state.selectedForCompare.splice(idx, 1);
					}
					updateUI();
					renderCompareMatrix();
				});
			});
		}

		function renderProductDetailModal(productId) {
			if (!productModal || !productModalContent) return false;
			var p = state.products.filter(function (item) {
				return Number(item.id) === Number(productId);
			})[0];
			if (!p) return false;

			var deptId = p.department || inferDepartment(p);
			var deptObj = DEPARTMENTS.filter(function (d) { return d.id === deptId; })[0];
			var deptName = deptObj ? deptObj.name : deptId;
			var stores = (p.stores && p.stores.length > 0) ? p.stores : [
				{ shop: p.shop || 'Nettbutikk', price: p.currentPrice, inStock: p.inStock !== false, isBest: true, url: p.url || '#' }
			];
			var lowestPrice = p.currentPrice || stores[0].price;
			var maxPrice = stores[stores.length - 1].price;
			var hasDisc = p.discount && p.discount > 0;
			var save = (p.originalPrice && p.originalPrice > p.currentPrice) ? (p.originalPrice - p.currentPrice) : 0;
			var isChecked = state.selectedForCompare.indexOf(p.id) !== -1;
			var safeImg = p.image && p.image.length > 5 ? p.image : '';
			var bestStore = stores[0] || { shop: p.shop || 'Nettbutikk', url: p.url || '#', price: lowestPrice, inStock: p.inStock !== false };

			var specEntries = [];
			if (p.specs && typeof p.specs === 'object') {
				Object.keys(p.specs).forEach(function (k) {
					if (p.specs[k]) {
						specEntries.push({ key: k, val: p.specs[k] });
					}
				});
			}
			if (specEntries.length === 0) {
				specEntries.push({ key: 'Kategori', val: p.category || 'Treningsutstyr' });
				specEntries.push({ key: 'Merke', val: p.brand || 'Ukjent' });
			}

			var notes = getAntiFitNotes(p);

			var related = state.products.filter(function (other) {
				return other.id !== p.id && other.category === p.category;
			});
			if (related.length < 3) {
				var deptRelated = state.products.filter(function (other) {
					var od = other.department || inferDepartment(other);
					return other.id !== p.id && other.category !== p.category && od === deptId;
				});
				related = related.concat(deptRelated);
			}
			related = related.slice(0, 3);

			var storesRowsHtml = stores.map(function (st) {
				var isBest = st.price === lowestPrice;
				var rowClass = 'ge-m3-ldrow ge-m3-ldrow-link' + (isBest ? ' is-best-deal' : '');
				return '<a href="' + escapeHtml(st.url || '#') + '" target="_blank" rel="noopener sponsored" aria-label="Kjøp hos ' + escapeHtml(st.shop) + ' for ' + formatPrice(st.price) + (st.inStock ? ', på lager' : ', utsolgt') + '" class="' + rowClass + '">' +
					(isBest ? '<span class="ge-m3-store-tag">Best pris</span>' : '') +
					'<span class="ge-m3-ldrow-name">' +
						renderMerchantLogoHtml(st.shop) +
						escapeHtml(st.shop) +
						'<span class="ge-m3-ldrow-dot ' + (st.inStock ? 'is-in-stock' : 'is-out-of-stock') + '" title="' + (st.inStock ? 'På lager' : 'Utsolgt') + '"></span>' +
						'<span class="ge-m3-sr-only">' + (st.inStock ? 'På lager' : 'Utsolgt') + '</span>' +
					'</span>' +
					'<span class="ge-m3-ldrow-right">' +
						'<span class="ge-m3-ldrow-price">' + formatPrice(st.price) + '</span>' +
						'<span class="ge-m3-store-btn ge-m3-btn ge-m3-btn--filled m3-sl" aria-hidden="true">Til butikk ' + ic('ext') + '</span>' +
					'</span>' +
				'</a>';
			}).join('');

			var specsHtml = specEntries.map(function (entry) {
				return '<div class="ge-prod-modal-spec-item">' +
					'<dt>' + escapeHtml(entry.key) + '</dt>' +
					'<dd>' + escapeHtml(entry.val) + '</dd>' +
				'</div>';
			}).join('');

			var notesHtml = notes.map(function (note) {
				return '<li>' + escapeHtml(note) + '</li>';
			}).join('');

			var relatedHtml = '';
			if (related.length > 0) {
				relatedHtml = '<section class="ge-prod-modal-related" aria-label="Lignende produkter">' +
					'<h3 class="ge-prod-modal-section-title">Alternativer i samme kategori</h3>' +
					'<div class="ge-prod-modal-related-grid">' +
						related.map(function (rel) {
							return '<div class="ge-prod-modal-rel-card">' +
								'<button type="button" class="ge-prod-modal-rel-main m3-sl" data-detail-id="' + rel.id + '">' +
									'<span class="ge-prod-modal-rel-thumb">' +
										(rel.image ? '<img src="' + escapeHtml(rel.image) + '" alt="" loading="lazy" onerror="this.style.display=\'none\';" />' : '') +
									'</span>' +
									'<span class="ge-prod-modal-rel-info">' +
										'<span class="ge-m3-brand">' + escapeHtml(rel.brand || 'UTSTYR') + '</span>' +
										'<span class="ge-prod-modal-rel-title">' + escapeHtml(rel.name) + '</span>' +
										'<span class="ge-prod-modal-rel-price">Fra ' + formatPrice(rel.currentPrice) + '</span>' +
									'</span>' +
								'</button>' +
								'<button type="button" class="ge-prod-modal-rel-cmp ge-m3-btn ge-m3-btn--tonal m3-sl" data-compare-pair-id="' + rel.id + '">' +
									'<span class="material-symbols-outlined" aria-hidden="true">compare_arrows</span>' +
									'<span>Sammenlign med denne</span>' +
								'</button>' +
							'</div>';
						}).join('') +
					'</div>' +
				'</section>';
			}

			productModalContent.innerHTML =
				'<div class="ge-m3-modal-head ge-prod-modal-head">' +
					'<div class="ge-prod-modal-head-text">' +
						'<div class="ge-prod-modal-breadcrumbs">' +
							'<span class="ge-m3-spec-pill sec">' + escapeHtml(deptName) + '</span>' +
							'<span class="ge-prod-modal-bc-sep" aria-hidden="true">›</span>' +
							'<span class="ge-m3-spec-pill sec">' + escapeHtml(p.category || 'Utstyr') + '</span>' +
							'<span class="ge-m3-brand">' + escapeHtml(p.brand || 'UTSTYR') + '</span>' +
						'</div>' +
						'<h2 id="ge-product-modal-title" class="ge-m3-modal-title">' + escapeHtml(p.name) + '</h2>' +
					'</div>' +
					'<div class="ge-m3-modal-actions">' +
						'<label class="ge-m3-diff-toggle ge-prod-modal-cmp-toggle m3-sl' + (isChecked ? ' is-active' : '') + '" title="Legg til i side-om-side sammenligning">' +
							'<input type="checkbox" id="ge-prod-modal-cmp-chk" class="ge-m3-diff-checkbox" data-id="' + p.id + '" ' + (isChecked ? 'checked' : '') + ' />' +
							CHIP_CHECK +
							'<span>Sammenlign</span>' +
						'</label>' +
						'<button type="button" id="ge-product-modal-close" class="ge-m3-modal-close ge-m3-icon-btn m3-sl" title="Lukk produktvisning" aria-label="Lukk produktvisning">' + ic('close') + '</button>' +
					'</div>' +
				'</div>' +
				'<div class="ge-m3-modal-body ge-prod-modal-body">' +
					'<div class="ge-prod-modal-hero">' +
						'<div class="ge-prod-modal-visual">' +
							'<div class="ge-prod-modal-img-card">' +
								(hasDisc ? '<span class="ge-m3-deal-chip ge-discount-badge">-' + p.discount + '%</span>' : '') +
								stockHtml(p.inStock !== false, 'ge-m3-stock-badge ge-prod-modal-stock') +
								(safeImg ? '<img src="' + escapeHtml(safeImg) + '" alt="' + escapeHtml(p.name) + '" class="ge-prod-modal-img" onerror="this.style.display=\'none\';" />' : '') +
							'</div>' +
							'<div class="ge-prod-modal-best-box">' +
								'<div class="ge-prod-modal-best-meta">' +
									'<span class="ge-prod-modal-best-label">Laveste pris nå hos <strong>' + escapeHtml(bestStore.shop) + '</strong></span>' +
									'<div class="ge-m3-price-row">' +
										'<span class="ge-m3-price-current">' + formatPrice(lowestPrice) + '</span>' +
										(p.originalPrice ? '<span class="ge-m3-price-original">' + formatPrice(p.originalPrice) + '</span>' : '') +
										(save > 0 ? '<span class="ge-m3-savings-chip ge-savings-chip">Spar ' + formatPrice(save) + '</span>' : '') +
									'</div>' +
								'</div>' +
								'<a href="' + escapeHtml(bestStore.url || '#') + '" target="_blank" rel="noopener sponsored" class="ge-prod-modal-primary-cta ge-m3-btn ge-m3-btn--filled m3-sl">' +
									'<span>Gå til beste tilbud hos ' + escapeHtml(bestStore.shop) + '</span>' +
									ic('ext') +
								'</a>' +
							'</div>' +
						'</div>' +
						'<div class="ge-prod-modal-stores">' +
							'<div class="ge-prod-modal-stores-head">' +
								'<div>' +
									'<h3 class="ge-prod-modal-section-title">Prissammenligning (' + stores.length + (stores.length === 1 ? ' butikk' : ' butikker') + ')</h3>' +
									(stores.length > 1 && maxPrice > lowestPrice
										? '<p class="ge-prod-modal-stores-sub">Prisspenn fra ' + formatPrice(lowestPrice) + ' til ' + formatPrice(maxPrice) + ' (du sparer opptil ' + formatPrice(maxPrice - lowestPrice) + ')</p>'
										: '<p class="ge-prod-modal-stores-sub">Oppdaterte priser og lagerstatus fra våre tilknyttede forhandlere</p>') +
								'</div>' +
							'</div>' +
							'<div class="ge-m3-ldrawer-rows ge-prod-modal-store-rows">' +
								storesRowsHtml +
							'</div>' +
						'</div>' +
					'</div>' +
					'<div class="ge-prod-modal-details">' +
						'<section class="ge-prod-modal-specs-card" aria-label="Tekniske spesifikasjoner">' +
							'<h3 class="ge-prod-modal-section-title">' +
								'<span class="material-symbols-outlined" aria-hidden="true">fact_check</span>' +
								'<span>Tekniske spesifikasjoner</span>' +
							'</h3>' +
							'<dl class="ge-prod-modal-spec-list">' +
								specsHtml +
							'</dl>' +
						'</section>' +
						'<section class="ge-prod-modal-notes-card" aria-label="Faglig vurdering">' +
							'<h3 class="ge-prod-modal-section-title">' +
								'<span class="material-symbols-outlined" aria-hidden="true">health_and_safety</span>' +
								'<span>Faglig vurdering – Vær obs på</span>' +
							'</h3>' +
							'<div class="ge-m3-antifit-box ge-antifit-box">' +
								'<ul>' + notesHtml + '</ul>' +
							'</div>' +
						'</section>' +
					'</div>' +
					relatedHtml +
				'</div>';

			var closeBtn = productModalContent.querySelector('#ge-product-modal-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', closeProductModal);
			}

			var modalCmpChk = productModalContent.querySelector('#ge-prod-modal-cmp-chk');
			if (modalCmpChk) {
				modalCmpChk.addEventListener('change', function () {
					var id = p.id;
					if (modalCmpChk.checked) {
						if (state.selectedForCompare.length >= 3) {
							alert('Du kan sammenligne maksimalt 3 produkter samtidig.');
							modalCmpChk.checked = false;
							return;
						}
						if (state.selectedForCompare.indexOf(id) === -1) {
							state.selectedForCompare.push(id);
						}
					} else {
						var idx = state.selectedForCompare.indexOf(id);
						if (idx !== -1) state.selectedForCompare.splice(idx, 1);
					}
					var toggleLbl = modalCmpChk.closest('.ge-prod-modal-cmp-toggle');
					if (toggleLbl) toggleLbl.classList.toggle('is-active', modalCmpChk.checked);
					updateUI();
				});
			}

			productModalContent.querySelectorAll('[data-detail-id]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var nextId = parseInt(btn.getAttribute('data-detail-id'), 10);
					openProductModal(nextId);
				});
			});

			productModalContent.querySelectorAll('[data-compare-pair-id]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var otherId = parseInt(btn.getAttribute('data-compare-pair-id'), 10);
					if (state.selectedForCompare.indexOf(p.id) === -1) {
						if (state.selectedForCompare.length >= 3) state.selectedForCompare.shift();
						state.selectedForCompare.push(p.id);
					}
					if (state.selectedForCompare.indexOf(otherId) === -1) {
						if (state.selectedForCompare.length >= 3) state.selectedForCompare.shift();
						state.selectedForCompare.push(otherId);
					}
					closeProductModal();
					updateUI();
					openCompareModal();
				});
			});

			return true;
		}

		function openProductModal(productId) {
			if (!renderProductDetailModal(productId)) return;
			state.activeProductId = Number(productId);
			productModal.style.display = 'flex';
			document.body.style.overflow = 'hidden';
			updateUrlState();
			var closeBtn = productModalContent.querySelector('#ge-product-modal-close');
			if (closeBtn) closeBtn.focus();
		}

		function closeProductModal() {
			state.activeProductId = null;
			if (productModal) {
				productModal.style.display = 'none';
				document.body.style.overflow = '';
			}
			updateUrlState();
		}

		// Main update function
		function updateUI(options) {
			options = options || {};

			if (searchClear) {
				searchClear.hidden = state.searchQuery === '';
			}

			// Cross-Department Search Alert Check
			if (deptAlert) {
				var trimmedQ = state.searchQuery.trim().toLowerCase();
				if (trimmedQ !== '') {
					var currHits = state.products.filter(function (p) {
						var d = p.department || inferDepartment(p);
						var text = (p.name + ' ' + (p.brand || '') + ' ' + (p.category || '')).toLowerCase();
						return d === state.activeDepartment && text.indexOf(trimmedQ) !== -1;
					});

					if (currHits.length === 0) {
						var otherDeptMatches = {};
						state.products.forEach(function (p) {
							var d = p.department || inferDepartment(p);
							if (d !== state.activeDepartment) {
								var text = (p.name + ' ' + (p.brand || '') + ' ' + (p.category || '')).toLowerCase();
								if (text.indexOf(trimmedQ) !== -1) {
									otherDeptMatches[d] = (otherDeptMatches[d] || 0) + 1;
								}
							}
						});

						var otherDepts = Object.keys(otherDeptMatches);
						if (otherDepts.length > 0) {
							otherDepts.sort(function (a, b) { return otherDeptMatches[b] - otherDeptMatches[a]; });
							var targetDeptId = otherDepts[0];
							var targetDeptObj = DEPARTMENTS.filter(function (d) { return d.id === targetDeptId; })[0];
							var targetDeptName = targetDeptObj ? targetDeptObj.name : targetDeptId;
							var matchCount = otherDeptMatches[targetDeptId];

							deptAlert.className = 'ge-m3-dept-alert ge-dept-alert';
							deptAlert.innerHTML = ic('search') +
								'<div class="ge-m3-dept-alert-text">Fant <strong>' + matchCount + ' treff</strong> i <strong>' + escapeHtml(targetDeptName) + '</strong> for «' + escapeHtml(state.searchQuery.trim()) + '»</div>' +
								'<button type="button" class="ge-m3-dept-switch-btn ge-dept-switch-btn ge-m3-btn ge-m3-btn--text m3-sl" id="ge-dept-switch-btn">Bytt til ' + escapeHtml(targetDeptName) + '</button>';
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
				} else {
					deptAlert.style.display = 'none';
					deptAlert.innerHTML = '';
				}
			}

			// A. Base filter (Department, Category, Search) for dynamic brand/shop options
			var baseFiltered = state.products.filter(function (product) {
				var prodDept = product.department || inferDepartment(product);
				var matchesDept = prodDept === state.activeDepartment;
				var searchTarget = (product.name + ' ' + (product.brand || '') + ' ' + (product.category || '')).toLowerCase();
				var matchesSearch = searchTarget.indexOf(state.searchQuery.toLowerCase()) !== -1;
				var matchesCategory = state.activeCategory === 'Alle' || product.category === state.activeCategory;
				return matchesDept && matchesSearch && matchesCategory;
			});

			var brandSet = {};
			var shopSet = {};
			baseFiltered.forEach(function (p) {
				if (p.brand && p.brand !== 'Ukjent') brandSet[p.brand.trim()] = true;
				if (p.stores && p.stores.length > 0) {
					p.stores.forEach(function (st) {
						if (st.shop) shopSet[st.shop.trim()] = true;
					});
				} else if (p.shop) {
					shopSet[p.shop.trim()] = true;
				}
			});
			var dynamicBrands = ['Alle'].concat(Object.keys(brandSet).sort());
			var dynamicShops = ['Alle'].concat(Object.keys(shopSet).sort());

			// Full filtered products
			var allQuickDefs = (QUICK_FILTER_DEFS.universal || []).concat(QUICK_FILTER_DEFS[state.activeDepartment] || []);
			var filtered = state.products.filter(function (product) {
				var prodDept = product.department || inferDepartment(product);
				var matchesDept = prodDept === state.activeDepartment;
				var searchTarget = (product.name + ' ' + (product.brand || '') + ' ' + (product.category || '')).toLowerCase();
				var matchesSearch = searchTarget.indexOf(state.searchQuery.toLowerCase()) !== -1;
				var matchesCategory = state.activeCategory === 'Alle' || product.category === state.activeCategory;

				var matchesShop = state.activeShops.length === 0;
				if (!matchesShop && product.stores && product.stores.length > 0) {
					matchesShop = product.stores.some(function (st) { return state.activeShops.indexOf(st.shop) !== -1; });
				} else if (!matchesShop && product.shop) {
					matchesShop = state.activeShops.indexOf(product.shop) !== -1;
				}

				var matchesBrand = state.activeBrands.length === 0 || state.activeBrands.indexOf(product.brand) !== -1;

				var price = product.currentPrice;
				var minP = state.minPrice === '' ? 0 : Number(state.minPrice);
				var maxP = state.maxPrice === '' ? Infinity : Number(state.maxPrice);
				var matchesPrice = price >= minP && price <= maxP;

				var matchesQuick = Object.keys(state.activeQuickFilters).every(function (qId) {
					if (!state.activeQuickFilters[qId]) return true;
					var def = allQuickDefs.filter(function (d) { return d.id === qId; })[0];
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
			document.body.classList.toggle('ge-m3-sheet-open', state.showFilters);
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
			renderPills(brandsWrap, dynamicBrands, state.activeBrands, function (brand) {
				if (brand === 'Alle') {
					state.activeBrands = [];
				} else {
					var idx = state.activeBrands.indexOf(brand);
					if (idx !== -1) state.activeBrands.splice(idx, 1);
					else state.activeBrands.push(brand);
				}
				state.visibleCount = initialLimit;
				updateUI();
			}, false);

			renderPills(shopsWrap, dynamicShops, state.activeShops, function (shop) {
				if (shop === 'Alle') {
					state.activeShops = [];
				} else {
					var idx = state.activeShops.indexOf(shop);
					if (idx !== -1) state.activeShops.splice(idx, 1);
					else state.activeShops.push(shop);
				}
				state.visibleCount = initialLimit;
				updateUI();
			}, true);

			filterApplyBtn.innerHTML = 'Vis ' + sorted.length + ' treff';
			resultsCount.textContent = 'Viser ' + visibleProducts.length + ' av ' + sorted.length + ' produkter';

			// F. Render Cards
			if (visibleProducts.length > 0) {
				if (state.viewMode === 'grid') {
					cardsContainer.className = 'ge-m3-grid ge-comp-grid';
					cardsContainer.innerHTML = visibleProducts.map(function (prod) { return renderGridCardHtml(prod, state); }).join('');
				} else {
					cardsContainer.className = 'ge-m3-list ge-comp-list';
					var columnSplit = Math.ceil(visibleProducts.length / 2);
					cardsContainer.innerHTML =
						'<div class="ge-m3-list-column ge-comp-list-column">' + visibleProducts.slice(0, columnSplit).map(function (prod) { return renderListCardHtml(prod, state); }).join('') + '</div>' +
						'<div class="ge-m3-list-column ge-comp-list-column">' + visibleProducts.slice(columnSplit).map(function (prod) { return renderListCardHtml(prod, state); }).join('') + '</div>';
				}

				// Attach product detail modal triggers (image & title)
				cardsContainer.querySelectorAll('[data-detail-id]').forEach(function (el) {
					el.addEventListener('click', function (e) {
						e.preventDefault();
						e.stopPropagation();
						var id = parseInt(el.getAttribute('data-detail-id'), 10);
						openProductModal(id);
					});
					if (el.tagName !== 'BUTTON') {
						el.addEventListener('keydown', function (e) {
							if (e.key === 'Enter' || e.key === ' ') {
								e.preventDefault();
								var id = parseInt(el.getAttribute('data-detail-id'), 10);
								openProductModal(id);
							}
						});
					}
				});

				// Attach store compare trigger listeners
				cardsContainer.querySelectorAll('.ge-m3-shop-compare-trigger').forEach(function (el) {
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

				// Attach compare checkbox listeners
				cardsContainer.querySelectorAll('.ge-m3-cmp-chk').forEach(function (chk) {
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
			} else {
				cardsContainer.className = 'ge-m3-empty-wrap';
				cardsContainer.innerHTML =
					'<div class="ge-m3-empty ge-comp-empty">' +
						'<p>Fant ingen produkter som matcher filteret.</p>' +
						'<button type="button" class="ge-m3-btn ge-m3-btn--tonal m3-sl" id="ge-empty-reset-btn">Fjern alle filtre</button>' +
					'</div>';

				var emptyResetBtn = cardsContainer.querySelector('#ge-empty-reset-btn');
				if (emptyResetBtn) {
					emptyResetBtn.addEventListener('click', function () {
						resetAllFilters();
					});
				}
			}

			// G. Load More Button
			if (state.visibleCount < sorted.length) {
				var remaining = sorted.length - state.visibleCount;
				loadMoreWrap.style.display = 'flex';
				loadMoreWrap.innerHTML = '<button type="button" class="ge-m3-btn ge-m3-btn--tonal m3-sl" id="ge-load-more-btn">Vis flere produkter (' + remaining + ' igjen)</button>';
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

			if (deptAlert) {
				deptAlert.style.display = 'none';
				deptAlert.innerHTML = '';
			}

			renderCategoryChips();
			renderQuickFilters();
			updateUrlState();
			updateUI();
		}

		function setFiltersOpen(open) {
			state.showFilters = open;
			updateUI();
			if (open) filterCloseBtn.focus();
			else filterToggleBtn.focus();
		}

		// 3. Attach Event Listeners & Omni-Search Autosuggest
		var searchSuggest = root.querySelector('#ge-search-suggest');
		var searchIndex = parseSearchIndexAttr(root);
		var omniCtrl = attachOmniSearch({
			searchBox: searchBox,
			searchInput: searchInput,
			searchClear: searchClear,
			suggestEl: searchSuggest,
			products: state.products,
			searchIndex: searchIndex,
			onInputChange: function (val) {
				state.searchQuery = val;
				state.visibleCount = initialLimit;
				updateUrlState();
				updateUI();
			},
			onSelectCategory: function (deptId, catName) {
				if (state.activeDepartment !== deptId) {
					switchDepartment(deptId);
				}
				state.searchQuery = '';
				if (searchInput) searchInput.value = '';
				switchCategory(catName);
				if (cardsContainer && cardsContainer.scrollIntoView) {
					cardsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			},
			onSelectProduct: function (prod) {
				var targetDept = prod.department || inferDepartment(prod);
				if (state.activeDepartment !== targetDept) {
					switchDepartment(targetDept);
				}
				state.activeCategory = 'Alle';
				state.openStores = {};
				state.openStores[prod.id] = true;
				renderCategoryChips();
				updateUI();
				openProductModal(prod.id);
			}
		});

		if (searchClear) {
			searchClear.addEventListener('click', function () {
				state.searchQuery = '';
				if (searchInput) {
					searchInput.value = '';
					searchInput.focus();
				}
				if (omniCtrl) omniCtrl.close();
				state.visibleCount = initialLimit;
				updateUrlState();
				updateUI();
			});
		}

		sortSelect.addEventListener('change', function (e) {
			state.sortBy = e.target.value;
			updateUI();
		});

		filterToggleBtn.addEventListener('click', function () {
			setFiltersOpen(!state.showFilters);
		});

		filterCloseBtn.addEventListener('click', function () {
			setFiltersOpen(false);
		});

		filterScrim.addEventListener('click', function () {
			setFiltersOpen(false);
		});

		function setViewMode(mode) {
			state.viewMode = mode;
			viewGridBtn.classList.toggle('is-active', mode === 'grid');
			viewListBtn.classList.toggle('is-active', mode === 'list');
			viewGridBtn.setAttribute('aria-pressed', mode === 'grid' ? 'true' : 'false');
			viewListBtn.setAttribute('aria-pressed', mode === 'list' ? 'true' : 'false');
			updateUI();
		}

		viewGridBtn.addEventListener('click', function () { setViewMode('grid'); });
		viewListBtn.addEventListener('click', function () { setViewMode('list'); });

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

		filterApplyBtn.addEventListener('click', function () {
			setFiltersOpen(false);
		});

		function openCompareModal() {
			renderCompareMatrix();
			if (state.selectedForCompare.length > 0 && cmpModal) {
				cmpModal.style.display = 'flex';
				document.body.style.overflow = 'hidden';
				if (modalCloseBtn) modalCloseBtn.focus();
			}
		}

		function closeCompareModal() {
			if (cmpModal) {
				cmpModal.style.display = 'none';
				document.body.style.overflow = '';
			}
			if (dockOpenModalBtn) dockOpenModalBtn.focus();
		}

		if (dockOpenModalBtn) {
			dockOpenModalBtn.addEventListener('click', openCompareModal);
		}

		if (modalCloseBtn) {
			modalCloseBtn.addEventListener('click', closeCompareModal);
		}

		if (cmpModal) {
			cmpModal.addEventListener('click', function (e) {
				if (e.target === cmpModal) closeCompareModal();
			});
		}

		if (productModal) {
			productModal.addEventListener('click', function (e) {
				if (e.target === productModal) closeProductModal();
			});
		}

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				if (productModal && productModal.style.display !== 'none') {
					closeProductModal();
				} else if (cmpModal && cmpModal.style.display !== 'none') {
					closeCompareModal();
				} else if (state.showFilters) {
					setFiltersOpen(false);
				}
			}
		});

		if (dockClearBtn) {
			dockClearBtn.addEventListener('click', function () {
				state.selectedForCompare = [];
				updateUI();
				if (cmpModal && cmpModal.style.display !== 'none') {
					closeCompareModal();
				}
			});
		}

		if (diffOnlyChk) {
			diffOnlyChk.addEventListener('change', function (e) {
				state.showOnlyDiffs = e.target.checked;
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
			var newOpen = (stateObj && stateObj.open) || (urlP && urlP.get('open') ? parseInt(urlP.get('open'), 10) : null);

			var deptValid = DEPARTMENTS.some(function (d) { return d.id === newDept; });
			if (!deptValid) newDept = 'kondisjon';

			var deptChanged = state.activeDepartment !== newDept;
			state.activeDepartment = newDept;
			state.activeCategory = newCat;
			state.searchQuery = newQ;
			if (searchInput) searchInput.value = newQ;

			if (deptChanged) {
				computeDepartmentPriceBounds();
				if (sliderMin && sliderMax) {
					sliderMin.min = state.absMinPrice;
					sliderMin.max = state.absMaxPrice;
					sliderMax.min = state.absMinPrice;
					sliderMax.max = state.absMaxPrice;
				}
			}

			renderDepartmentTabs();
			renderCategoryChips();
			renderQuickFilters();
			updateUI();

			if (newOpen) {
				openProductModal(newOpen);
			} else if (productModal && productModal.style.display !== 'none') {
				closeProductModal();
			}
		});

		// Initial render
		updateUI();
		if (state.activeProductId) {
			openProductModal(state.activeProductId);
		}
	}

	// Initial DOM skeleton constructor matching scratch/price-comparison-material3-prototype.html
	function renderSkeleton(root, state) {
		var showGuide = root.getAttribute('data-show-guide') !== 'false';
		var guideUrl = root.getAttribute('data-guide-url') || '/kjopsveiledere/';
		var showSearchAttr = root.getAttribute('data-show-search');
		var showSearch = showSearchAttr !== 'false';
		var hasStandaloneSearch = !!document.querySelector('.ge-m3-standalone-search');
		if (showSearchAttr === 'false' || (hasStandaloneSearch && showSearchAttr === null)) {
			showSearch = false;
		}
		var filterHost = root.closest('.ge-price-comp-wrapper') || root;

		root.innerHTML = '' +
		'<p class="ge-m3-affiliate-disclosure">' +
			'<span class="material-symbols-outlined" aria-hidden="true">info</span>' +
			'<span>Noen av butikkene er samarbeidspartnere, og vi kan motta provisjon hvis du handler via lenkene. Dette påvirker ikke prisen.</span>' +
		'</p>' +

		(showGuide ? (
			'<aside class="ge-m3-guide-banner ge-price-comp-guide-banner" id="ge-guide-banner" aria-label="Veiledning">' +
				'<button class="ge-m3-guide-dismiss" id="ge-guide-dismiss" type="button" aria-label="Lukk kjøpsveileder" title="Lukk kjøpsveileder">' +
					'<span class="material-symbols-outlined" aria-hidden="true">close</span>' +
				'</button>' +
				'<div class="ge-m3-guide-content">' +
					'<div class="ge-m3-guide-text">' +
						'<strong>Usikker på hvilket utstyr som passer best for deg?</strong>' +
						'<span>Finn optimal modell for leddbelastning, plass og budsjett i vår interaktive veileder.</span>' +
					'</div>' +
				'</div>' +
				'<a href="' + escapeHtml(guideUrl) + '" class="ge-m3-guide-btn ge-m3-btn ge-m3-btn--filled m3-sl">' +
					'Gå til Kjøpsveileder' +
					'<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>' +
				'</a>' +
			'</aside>'
		) : '') +

		'<header class="ge-m3-header">' +
			'<div class="ge-m3-header-text">' +
				'<h1 class="ge-m3-title">Prissammenligning</h1>' +
				'<p class="ge-m3-subtitle">Sammenlign priser og tilbud fra flere butikker.</p>' +
			'</div>' +
			(showSearch ? (
				'<div class="ge-m3-header-search-slot" id="ge-header-search-slot">' +
					'<div class="ge-m3-search-box" id="ge-search-box" role="search">' +
						'<span class="ge-m3-search-icon"><span class="material-symbols-outlined" aria-hidden="true">search</span></span>' +
						'<input type="text" id="ge-search-input" class="ge-m3-search-input" placeholder="Søk etter symptomer, program eller utstyr..." aria-label="Søk etter symptomer, program eller utstyr" aria-autocomplete="list" aria-controls="ge-search-suggest" aria-expanded="false" autocomplete="off" enterkeyhint="search" />' +
						'<button type="button" id="ge-search-clear" class="ge-m3-icon-btn ge-m3-search-clear m3-sl" aria-label="Tøm søk" title="Tøm søk" hidden>' +
							'<span class="material-symbols-outlined" aria-hidden="true">close</span>' +
						'</button>' +
						'<div id="ge-search-suggest" class="ge-m3-search-suggest" role="listbox" aria-label="Søkeforslag" hidden></div>' +
					'</div>' +
				'</div>'
			) : '') +
		'</header>' +

		(showSearch ? '<div class="ge-m3-mobile-search-slot" id="ge-mobile-search-slot"></div>' : '') +

		'<div class="ge-m3-nav-section ge-dept-section">' +
			'<div class="ge-m3-dept-track ge-dept-track" id="ge-dept-track" role="tablist" aria-label="Velg avdeling"></div>' +
			'<div class="ge-m3-cats-bar" id="ge-comp-cats-bar" role="group" aria-label="Velg kategori"></div>' +
		'</div>' +

		'<div id="ge-dept-alert" role="status" style="display: none;"></div>' +

		'<div class="ge-m3-controls ge-m3-toolbar">' +
			'<div class="ge-m3-toolbar-left">' +
				'<button type="button" id="ge-filter-toggle" class="ge-m3-filter-btn ge-m3-btn ge-m3-btn--outlined ge-m3-btn--m m3-sl" aria-haspopup="dialog" aria-controls="ge-comp-filter-panel" aria-expanded="false">' +
					'<span class="material-symbols-outlined" aria-hidden="true">tune</span>' +
					'<span>Filter</span>' +
					'<span id="ge-comp-filter-badge" class="ge-m3-filter-badge" style="display: none;">0</span>' +
				'</button>' +
				'<div class="ge-m3-results-count" aria-live="polite">' +
					'<span id="ge-comp-results-count">Viser produkter</span>' +
				'</div>' +
			'</div>' +

			'<div class="ge-m3-toolbar-right">' +
				'<div class="ge-m3-sort-wrap">' +
					'<span class="ge-m3-sort-icon"><span class="material-symbols-outlined" aria-hidden="true">sort</span></span>' +
					'<select id="ge-sort-select" class="ge-m3-select ge-m3-sort-select" aria-label="Sorter etter">' +
						'<option value="relevant">Mest relevant</option>' +
						'<option value="price_asc">Laveste pris</option>' +
						'<option value="price_desc">Høyeste pris</option>' +
						'<option value="discount_desc">Størst rabatt (%)</option>' +
						'<option value="savings_desc">Størst kroneavslag</option>' +
					'</select>' +
					'<span class="ge-m3-sort-arrow"><span class="material-symbols-outlined" aria-hidden="true">expand_more</span></span>' +
				'</div>' +

				'<div class="ge-m3-view-toggle-wrap ge-comp-view-toggle-wrap">' +
					'<div class="ge-m3-view-toggle" role="group" aria-label="Velg visning">' +
						'<button type="button" id="ge-view-grid-btn" class="ge-m3-view-btn m3-sl' + (state.viewMode === 'grid' ? ' is-active' : '') + '" title="Vis som rutenett" aria-label="Vis som rutenett" aria-pressed="' + (state.viewMode === 'grid' ? 'true' : 'false') + '">' +
							'<span class="material-symbols-outlined ge-m3-seg-icon" aria-hidden="true">grid_view</span>' +
							'<span class="ge-m3-sr-only">Rutenett</span>' +
						'</button>' +
						'<button type="button" id="ge-view-list-btn" class="ge-m3-view-btn m3-sl' + (state.viewMode === 'list' ? ' is-active' : '') + '" title="Vis som liste" aria-label="Vis som liste" aria-pressed="' + (state.viewMode === 'list' ? 'true' : 'false') + '">' +
							'<span class="material-symbols-outlined ge-m3-seg-icon" aria-hidden="true">view_list</span>' +
							'<span class="ge-m3-sr-only">Liste</span>' +
						'</button>' +
					'</div>' +
				'</div>' +
			'</div>' +
		'</div>' +

		'<div id="ge-comp-cards-container" class="' + (state.viewMode === 'grid' ? 'ge-m3-grid ge-comp-grid' : 'ge-m3-list ge-comp-list') + '"></div>' +

		'<div id="ge-comp-load-more-wrap" class="ge-m3-load-more-wrap" style="display: none;"></div>';

		// Remove any existing filter sheet before mounting fresh one on filterHost (outside container-type)
		var oldScrim = filterHost.querySelector('#ge-comp-filter-scrim');
		if (oldScrim) oldScrim.remove();
		var oldPanel = filterHost.querySelector('#ge-comp-filter-panel');
		if (oldPanel) oldPanel.remove();

		var sheetWrapper = document.createElement('div');
		sheetWrapper.innerHTML = '' +
		'<div id="ge-comp-filter-scrim" class="ge-m3-scrim ge-comp-filter-scrim" aria-hidden="true"></div>' +
		'<aside id="ge-comp-filter-panel" class="ge-m3-filter-panel ge-comp-filter-panel" role="dialog" aria-modal="true" aria-labelledby="ge-filter-title" aria-hidden="true" inert>' +
			'<div class="ge-m3-sheet-handle" aria-hidden="true"></div>' +
			'<div class="ge-m3-sheet-head">' +
				'<h2 id="ge-filter-title" class="ge-m3-sheet-title">Filtre</h2>' +
				'<button type="button" id="ge-filter-close-btn" class="ge-m3-icon-btn ge-filter-close-btn m3-sl" aria-label="Lukk filtre" title="Lukk filtre">' +
					'<span class="material-symbols-outlined" aria-hidden="true">close</span>' +
				'</button>' +
			'</div>' +
			'<div class="ge-m3-filter-grid">' +
				'<div class="ge-m3-filter-quick-filters">' +
					'<h3 class="ge-m3-section-title">Hurtigfiltre</h3>' +
					'<div class="ge-m3-quick-filters-panel-slot" id="ge-comp-quick-filters-panel-slot">' +
						'<div class="ge-m3-quick-filters ge-comp-quick-filters" id="ge-comp-quick-filters" role="group" aria-label="Hurtigfiltre"></div>' +
					'</div>' +
				'</div>' +
				'<div>' +
					'<h3 class="ge-m3-section-title">Pris (NOK)</h3>' +
					'<div class="ge-m3-price-inputs">' +
						'<div class="ge-m3-field">' +
							'<input type="number" id="ge-price-min-input" class="ge-m3-input" placeholder=" " inputmode="numeric" />' +
							'<label class="ge-m3-field-label" for="ge-price-min-input">Fra</label>' +
							'<span class="ge-m3-field-suffix">kr</span>' +
						'</div>' +
						'<div class="ge-m3-field">' +
							'<input type="number" id="ge-price-max-input" class="ge-m3-input" placeholder=" " inputmode="numeric" />' +
							'<label class="ge-m3-field-label" for="ge-price-max-input">Til</label>' +
							'<span class="ge-m3-field-suffix">kr</span>' +
						'</div>' +
					'</div>' +
					'<div class="ge-m3-slider-wrap">' +
						'<div class="ge-m3-slider-base"></div>' +
						'<div id="ge-slider-fill-bar" class="ge-m3-slider-fill"></div>' +
						'<input type="range" id="ge-slider-min" min="' + state.absMinPrice + '" max="' + state.absMaxPrice + '" class="m3-thumb" style="z-index: 3;" aria-label="Minimumspris" />' +
						'<input type="range" id="ge-slider-max" min="' + state.absMinPrice + '" max="' + state.absMaxPrice + '" class="m3-thumb" style="z-index: 4;" aria-label="Maksimumspris" />' +
					'</div>' +
				'</div>' +
				'<div>' +
					'<h3 class="ge-m3-section-title">Merke</h3>' +
					'<div id="ge-filter-brands-wrap" class="ge-m3-pills-scroll"></div>' +
				'</div>' +
				'<div>' +
					'<h3 class="ge-m3-section-title">Butikk</h3>' +
					'<div id="ge-filter-shops-wrap" class="ge-m3-pills-scroll"></div>' +
				'</div>' +
			'</div>' +
			'<div class="ge-m3-filter-footer">' +
				'<button type="button" id="ge-filter-reset-btn" class="ge-m3-reset-btn ge-m3-btn ge-m3-btn--text m3-sl">Tøm filtre</button>' +
				'<button type="button" id="ge-filter-apply-btn" class="ge-m3-apply-btn ge-m3-btn ge-m3-btn--filled m3-sl">Vis treff</button>' +
			'</div>' +
		'</aside>';

		while (sheetWrapper.firstChild) {
			filterHost.appendChild(sheetWrapper.firstChild);
		}
	}

	function initStandaloneSearchBars() {
		var nodes = document.querySelectorAll('.ge-m3-standalone-search');
		Array.prototype.forEach.call(nodes, function (node) {
			if (node.dataset.geInitialized === '1') return;
			node.dataset.geInitialized = '1';

			var placeholder = node.getAttribute('data-placeholder') || 'Søk i utstyr, programmer og symptomsjekk...';
			var products = parseFeedAttr(node);
			var searchIndex = parseSearchIndexAttr(node);
			var priserUrl = (searchIndex && searchIndex.priserUrl) ? searchIndex.priserUrl : '/priser/';

			var searchBox = node.querySelector('.ge-m3-search-box');
			if (!searchBox) {
				node.innerHTML =
					'<form class="ge-m3-search-box" role="search" method="get" action="' + escapeHtml(priserUrl) + '">' +
						'<span class="material-symbols-outlined ge-m3-search-icon" aria-hidden="true">search</span>' +
						'<input type="search" name="q" class="ge-m3-search-input" autocomplete="off" placeholder="' + escapeHtml(placeholder) + '" aria-label="Søk på hele nettstedet">' +
						'<button type="button" class="ge-m3-search-clear ge-m3-icon-btn m3-sl" aria-label="Tøm søk" hidden>' +
							'<span class="material-symbols-outlined" aria-hidden="true">close</span>' +
						'</button>' +
						'<div class="ge-m3-search-suggest" role="listbox" aria-label="Søkeforslag" hidden></div>' +
					'</form>';
				searchBox = node.querySelector('.ge-m3-search-box');
			}

			var searchInput = node.querySelector('.ge-m3-search-input');
			var searchClear = node.querySelector('.ge-m3-search-clear');
			var suggestEl = node.querySelector('.ge-m3-search-suggest');

			if (searchBox && searchBox.tagName === 'FORM') {
				searchBox.addEventListener('submit', function (e) {
					var q = searchInput ? (searchInput.value || '').trim() : '';
					if (!q) {
						e.preventDefault();
					}
				});
			}

			attachOmniSearch({
				searchBox: searchBox,
				searchInput: searchInput,
				searchClear: searchClear,
				suggestEl: suggestEl,
				products: products,
				searchIndex: searchIndex,
				onSearchSubmit: function (q) {
					var sep = priserUrl.indexOf('?') !== -1 ? '&' : '?';
					window.location.href = priserUrl + sep + 'q=' + encodeURIComponent((q || '').trim());
				}
			});
		});
	}

	function bootAll() {
		initPriceComparison();
		initStandaloneSearchBars();
	}

	// Expose public API for integration and testing
	window.GearEnginePriceComparison = {
		DEPARTMENTS: DEPARTMENTS,
		inferDepartment: inferDepartment,
		getAntiFitNotes: getAntiFitNotes,
		QUICK_FILTER_DEFS: QUICK_FILTER_DEFS,
		init: bootAll,
		initStandaloneSearchBars: initStandaloneSearchBars
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootAll);
	} else {
		bootAll();
	}
})();
