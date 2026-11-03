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
		{ id: 1, category: "Romaskiner", brand: "Concept2", name: "RowErg PM5 Romaskin Standard", currentPrice: 12490, originalPrice: 15600, discount: 20, shop: "Fjellsport", otherShops: 3, inStock: true, image: "https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=400&q=80", url: "#" },
		{ id: 2, category: "Tredemøller", brand: "Sole", name: "F85 Tredemølle 2024-modell m/Bluetooth og touchskjerm", currentPrice: 24900, originalPrice: 29294, discount: 15, shop: "Treningspartner", otherShops: 0, inStock: true, image: "https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=400&q=80", url: "#" },
		{ id: 3, category: "Kettlebells", brand: "Abilica", name: "Competition Kettlebell 16kg", currentPrice: 899, originalPrice: 1199, discount: 25, shop: "X-Life", otherShops: 1, inStock: true, image: "https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?auto=format&fit=crop&w=400&q=80", url: "#" },
		{ id: 4, category: "Racks og stativer", brand: "Titan Life", name: "Squat Rack Pro Hjemmegym", currentPrice: 3499, originalPrice: 4500, discount: 22, shop: "Treningspartner", otherShops: 0, inStock: true, image: "https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=400&q=80", url: "#" },
		{ id: 5, category: "Vektstenger", brand: "Gymgrossisten", name: "Olympisk Vektstang 20kg", currentPrice: 1999, originalPrice: 2499, discount: 20, shop: "Gymgrossisten", otherShops: 0, inStock: true, image: "https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=400&q=80", url: "#" },
		{ id: 7, category: "Massasje", brand: "Therabody", name: "Theragun Pro G5", currentPrice: 4999, originalPrice: 5999, discount: 16, shop: "Anton Sport", otherShops: 0, inStock: true, image: "https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=400&q=80", url: "#" },
		{ id: 8, category: "Manualer", brand: "Bowflex", name: "SelectTech 552i Justerbare Manualer", currentPrice: 2999, originalPrice: null, discount: null, shop: "Fjellsport", otherShops: 2, inStock: true, image: "https://images.unsplash.com/photo-1517502884422-41eaead166d4?auto=format&fit=crop&w=400&q=80", url: "#" }
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

		// 2. Initial state
		var initialCat = root.getAttribute('data-initial-category') || 'Alle';
		var initialView = root.getAttribute('data-initial-view') || 'grid';
		var initialLimit = parseInt(root.getAttribute('data-initial-limit'), 10) || 12;

		var state = {
			products: products,
			viewMode: initialView === 'list' ? 'list' : 'grid',
			searchQuery: '',
			activeCategory: initialCat,
			activeShops: [],
			activeBrands: [],
			minPrice: '',
			maxPrice: '',
			absMinPrice: 0,
			absMaxPrice: 100000,
			sortBy: 'relevant',
			visibleCount: initialLimit,
			showFilters: false
		};

		// Compute absolute price boundaries
		var validPrices = products
			.map(function (p) { return p.currentPrice; })
			.filter(function (p) { return !isNaN(p) && p > 0; });

		if (validPrices.length > 0) {
			state.absMinPrice = Math.floor(Math.min.apply(null, validPrices));
			state.absMaxPrice = Math.ceil(Math.max.apply(null, validPrices));
		}

		// Extract categories
		var catSet = {};
		products.forEach(function (p) {
			if (p.category && p.category !== 'Annet') {
				catSet[p.category] = true;
			}
		});
		var uniqueCategories = Object.keys(catSet).sort();
		var categories = ['Alle'].concat(uniqueCategories);

		// If initialCategory not in categories, reset to Alle
		if (categories.indexOf(state.activeCategory) === -1) {
			state.activeCategory = 'Alle';
		}

		// Render the initial static layout skeleton
		renderSkeleton(root, categories, state);

		// Cache DOM nodes for smooth targeted updates
		var searchInput = root.querySelector('.ge-comp-search-input');
		var sortSelect = root.querySelector('.ge-comp-sort-select');
		var filterToggleBtn = root.querySelector('.ge-comp-filter-toggle');
		var filterBadge = root.querySelector('#ge-comp-filter-badge');
		var viewGridBtn = root.querySelector('#ge-view-grid-btn');
		var viewListBtn = root.querySelector('#ge-view-list-btn');
		var filterPanel = root.querySelector('#ge-comp-filter-panel');
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

		// Main update function
		function updateUI(options) {
			options = options || {};

			// A. Filter logic
			var baseFiltered = state.products.filter(function (product) {
				var searchTarget = (product.name + ' ' + (product.brand || '')).toLowerCase();
				var matchesSearch = searchTarget.indexOf(state.searchQuery.toLowerCase()) !== -1;
				var matchesCategory = state.activeCategory === 'Alle' || product.category === state.activeCategory;
				return matchesSearch && matchesCategory;
			});

			// Dynamic brands & shops based on active category + search
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
				var searchTarget = (product.name + ' ' + (product.brand || '')).toLowerCase();
				var matchesSearch = searchTarget.indexOf(state.searchQuery.toLowerCase()) !== -1;
				var matchesCategory = state.activeCategory === 'Alle' || product.category === state.activeCategory;
				var matchesShop = state.activeShops.length === 0 || state.activeShops.indexOf(product.shop) !== -1;
				var matchesBrand = state.activeBrands.length === 0 || state.activeBrands.indexOf(product.brand) !== -1;

				var price = product.currentPrice;
				var minP = state.minPrice === '' ? 0 : parseInt(state.minPrice, 10);
				var maxP = state.maxPrice === '' ? Infinity : parseInt(state.maxPrice, 10);
				var matchesPrice = price >= minP && price <= maxP;

				return matchesSearch && matchesCategory && matchesShop && matchesBrand && matchesPrice;
			});

			// B. Sort logic
			var sorted = filtered.slice().sort(function (a, b) {
				if (state.sortBy === 'price_asc') return a.currentPrice - b.currentPrice;
				if (state.sortBy === 'price_desc') return b.currentPrice - a.currentPrice;
				if (state.sortBy === 'discount_desc') return (b.discount || 0) - (a.discount || 0);
				return 0;
			});

			var visibleProducts = sorted.slice(0, state.visibleCount);

			// C. Filter badge count
			var isPriceChanged = (state.minPrice !== '' && Number(state.minPrice) > state.absMinPrice) ||
				(state.maxPrice !== '' && Number(state.maxPrice) < state.absMaxPrice);
			var activeFilterCount = state.activeShops.length + state.activeBrands.length + (isPriceChanged ? 1 : 0);

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
			filterPanel.style.display = state.showFilters ? 'block' : 'none';

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
			});

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
			});

			// Update filter apply button text
			filterApplyBtn.innerHTML = 'Vis ' + sorted.length + ' treff <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>';

			// F. Results Counter
			resultsCount.textContent = 'Viser ' + visibleProducts.length + ' av ' + sorted.length + ' produkter';

			// G. Render Cards
			cardsContainer.className = state.viewMode === 'grid' ? 'ge-comp-grid' : 'ge-comp-list';

			if (visibleProducts.length > 0) {
				var cardsHtml = visibleProducts.map(function (prod) {
					return state.viewMode === 'list' ? renderListCard(prod) : renderGridCard(prod);
				}).join('');
				cardsContainer.innerHTML = cardsHtml;
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
		}

		function resetAllFilters() {
			state.activeShops = [];
			state.activeBrands = [];
			state.minPrice = '';
			state.maxPrice = '';
			state.searchQuery = '';
			state.activeCategory = 'Alle';
			state.visibleCount = initialLimit;
			searchInput.value = '';

			// Update category pill active class
			var catPills = root.querySelectorAll('.ge-cat-pill');
			catPills.forEach(function (pill) {
				if (pill.getAttribute('data-category') === 'Alle') {
					pill.classList.add('is-active');
				} else {
					pill.classList.remove('is-active');
				}
			});

			updateUI();
		}

		// 3. Attach Event Listeners

		// Category pills
		var catPills = root.querySelectorAll('.ge-cat-pill');
		catPills.forEach(function (pill) {
			pill.addEventListener('click', function () {
				var cat = pill.getAttribute('data-category');
				state.activeCategory = cat;
				state.searchQuery = '';
				state.activeBrands = [];
				state.activeShops = [];
				state.visibleCount = initialLimit;
				searchInput.value = '';

				catPills.forEach(function (p) { p.classList.remove('is-active'); });
				pill.classList.add('is-active');

				updateUI();
			});
		});

		// Search input
		searchInput.addEventListener('input', function (e) {
			state.searchQuery = e.target.value;
			state.visibleCount = initialLimit;
			updateUI();
		});

		// Sort select
		sortSelect.addEventListener('change', function (e) {
			state.sortBy = e.target.value;
			updateUI();
		});

		// Filter toggle
		filterToggleBtn.addEventListener('click', function () {
			state.showFilters = !state.showFilters;
			updateUI();
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
			state.minPrice = '';
			state.maxPrice = '';
			state.visibleCount = initialLimit;
			updateUI();
		});

		// Apply filters and smooth scroll
		filterApplyBtn.addEventListener('click', function () {
			state.showFilters = false;
			updateUI();
			setTimeout(function () {
				if (resultsCount) {
					var topPos = resultsCount.getBoundingClientRect().top + window.scrollY;
					window.scrollTo({ top: topPos - 100, behavior: 'smooth' });
				}
			}, 50);
		});

		// Initial render
		updateUI();
	}

	// Helper to render filter pills for Brands and Shops
	function renderFilterPills(container, items, activeItems, onClick) {
		container.innerHTML = '';
		items.forEach(function (item) {
			var isActive = item === 'Alle' ? activeItems.length === 0 : activeItems.indexOf(item) !== -1;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ge-filter-pill-btn' + (isActive ? ' is-active' : '');
			btn.textContent = item;
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

	// Layout 1: List Card
	function renderListCard(product) {
		var hasDiscount = product.discount && product.discount > 0;
		var safeUrl = product.url || '#';
		var safeImg = product.image && product.image.length > 5 ? product.image : '';

		return '' +
		'<a href="' + escapeHtml(safeUrl) + '" target="_blank" rel="noopener noreferrer sponsored" class="ge-card-list">' +
			'<div class="ge-card-list-img-wrap">' +
				(hasDiscount ? '<div class="ge-discount-badge">-' + product.discount + '%</div>' : '') +
				(safeImg ? '<img src="' + escapeHtml(safeImg) + '" alt="' + escapeHtml(product.name) + '" class="ge-card-img" onerror="this.style.display=\'none\';" />' : '') +
			'</div>' +
			'<div class="ge-card-list-content">' +
				'<div>' +
					'<div class="ge-list-top-row">' +
						'<span class="ge-card-brand">' + escapeHtml(product.brand || 'UTSTYR') + '</span>' +
						'<div class="ge-list-shop-badge">' + escapeHtml(product.shop || 'Nettbutikk') + '</div>' +
					'</div>' +
					'<h3 class="ge-card-title">' + escapeHtml(product.name) + '</h3>' +
				'</div>' +
				'<div class="ge-list-bottom-row">' +
					'<div>' +
						'<div class="ge-price-row">' +
							'<span class="ge-price-current">' + formatPrice(product.currentPrice) + '</span>' +
							(product.originalPrice ? '<span class="ge-price-original">' + formatPrice(product.originalPrice) + '</span>' : '') +
						'</div>' +
						'<div class="ge-mobile-shop-label">' + escapeHtml(product.shop || '') + '</div>' +
					'</div>' +
					'<button type="button" class="ge-cta-btn">' +
						'Til butikk ' + externalLinkIcon() +
					'</button>' +
				'</div>' +
			'</div>' +
		'</a>';
	}

	// Layout 2: Grid Card
	function renderGridCard(product) {
		var hasDiscount = product.discount && product.discount > 0;
		var safeUrl = product.url || '#';
		var safeImg = product.image && product.image.length > 5 ? product.image : '';
		var stockClass = product.inStock ? 'is-in-stock' : 'is-out-of-stock';

		return '' +
		'<a href="' + escapeHtml(safeUrl) + '" target="_blank" rel="noopener noreferrer sponsored" class="ge-card-grid">' +
			'<div class="ge-card-grid-img-wrap">' +
				(hasDiscount ? '<div class="ge-discount-badge">-' + product.discount + '%</div>' : '') +
				(safeImg ? '<img src="' + escapeHtml(safeImg) + '" alt="' + escapeHtml(product.name) + '" class="ge-card-img" onerror="this.style.display=\'none\';" />' : '') +
			'</div>' +
			'<div class="ge-card-content">' +
				'<span class="ge-card-brand">' + escapeHtml(product.brand || 'UTSTYR') + '</span>' +
				'<h3 class="ge-card-title">' + escapeHtml(product.name) + '</h3>' +
				'<div class="ge-card-bottom">' +
					'<div class="ge-price-row">' +
						'<span class="ge-price-current">' + formatPrice(product.currentPrice) + '</span>' +
						(product.originalPrice ? '<span class="ge-price-original">' + formatPrice(product.originalPrice) + '</span>' : '') +
					'</div>' +
					'<div class="ge-shop-strip">' +
						'<div class="ge-shop-name-wrap">' +
							shopIcon() +
							'<span>' + escapeHtml(product.shop || 'Nettbutikk') + '</span>' +
						'</div>' +
						'<div class="ge-stock-meta">' +
							'<span class="ge-stock-dot ' + stockClass + '"></span>' +
							(product.otherShops > 0 ? '<span class="ge-other-shops-badge">+ ' + product.otherShops + '</span>' : '') +
						'</div>' +
					'</div>' +
					'<button type="button" class="ge-cta-btn">' +
						'Til butikk ' + externalLinkIcon() +
					'</button>' +
				'</div>' +
			'</div>' +
		'</a>';
	}

	// Initial DOM skeleton constructor
	function renderSkeleton(root, categories, state) {
		var catButtonsHtml = categories.map(function (cat) {
			var isActive = state.activeCategory === cat;
			return '<button type="button" class="ge-cat-pill' + (isActive ? ' is-active' : '') + '" data-category="' + escapeHtml(cat) + '">' + escapeHtml(cat) + '</button>';
		}).join('');

		root.innerHTML = '' +
		'<div class="mb-5" style="margin-bottom: 24px;">' +
			'<h2 style="font-size: 28px; font-weight: 800; color: #1A2A3A; margin: 0 0 6px 0; letter-spacing: -0.02em;">Prissammenligning</h2>' +
			'<p style="font-size: 15px; color: #64748b; margin: 0;">Sammenlign priser fra våre samarbeidspartnere.</p>' +
		'</div>' +

		// Category Pills Bar
		'<div class="ge-comp-cats-bar">' + catButtonsHtml + '</div>' +

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
						'<option value="discount_desc">Størst rabatt</option>' +
					'</select>' +
					'<div class="ge-comp-sort-arrow">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>' +
					'</div>' +
				'</div>' +

				'<button type="button" class="ge-comp-filter-toggle">' +
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

				'<div class="ge-comp-view-toggle">' +
					'<button type="button" id="ge-view-grid-btn" class="ge-view-btn' + (state.viewMode === 'grid' ? ' is-active' : '') + '" title="Rutenett">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>' +
					'</button>' +
					'<button type="button" id="ge-view-list-btn" class="ge-view-btn' + (state.viewMode === 'list' ? ' is-active' : '') + '" title="Liste">' +
						'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>' +
					'</button>' +
				'</div>' +
			'</div>' +
		'</div>' +

		// Expanded Filter Drawer
		'<div id="ge-comp-filter-panel" class="ge-comp-filter-panel" style="display: none;">' +
			'<div class="ge-filter-grid">' +
				// Price Section
				'<div>' +
					'<h4 class="ge-filter-section-title">Pris</h4>' +
					'<div class="ge-filter-price-inputs">' +
						'<div class="ge-price-input-box">' +
							'<input type="number" id="ge-price-min-input" />' +
						'</div>' +
						'<span class="ge-price-dash">-</span>' +
						'<div class="ge-price-input-box">' +
							'<input type="number" id="ge-price-max-input" />' +
						'</div>' +
					'</div>' +
					'<div class="ge-slider-track-wrap">' +
						'<div class="ge-slider-base-bar"></div>' +
						'<div id="ge-slider-fill-bar" class="ge-slider-fill-bar"></div>' +
						'<input type="range" id="ge-slider-min" min="' + state.absMinPrice + '" max="' + state.absMaxPrice + '" class="dual-range-thumb" style="z-index: 3;" />' +
						'<input type="range" id="ge-slider-max" min="' + state.absMinPrice + '" max="' + state.absMaxPrice + '" class="dual-range-thumb" style="z-index: 4;" />' +
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
		'</div>' +

		// Results Counter
		'<div id="ge-comp-results-count" class="ge-comp-results-count"></div>' +

		// Cards Container
		'<div id="ge-comp-cards-container" class="' + (state.viewMode === 'grid' ? 'ge-comp-grid' : 'ge-comp-list') + '"></div>' +

		// Load More Wrap
		'<div id="ge-comp-load-more-wrap" class="ge-comp-load-more-wrap" style="display: none;"></div>';
	}

	// Auto-initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPriceComparison);
	} else {
		initPriceComparison();
	}
})();

