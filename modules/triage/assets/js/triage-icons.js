/**
 * Gear Engine Triage: inline SVG icon set (Google Material Symbols, Outlined style).
 * Uses stroke-width linked to --ge-icon-stroke for universal thickness control in CSS.
 *
 * @package GearEngine
 */
(function (root) {
	'use strict';

	var PATHS = {
		// Material Symbol: monitor_heart / activity
		Activity: '<path d="M3 12h3.5l2.5-6 4 12 2.5-6H21"/>',
		// Material Symbol: restart_alt / rotate_left
		RotateCcw: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8m0-5v5h5"/>',
		// Material Symbol: arrow_forward
		ArrowRight: '<path d="M5 12h14m-6-6l6 6-6 6"/>',
		// Material Symbol: arrow_upward
		ArrowUp: '<path d="M12 19V5m-6 6l6-6 6 6"/>',
		// Material Symbol: arrow_downward
		ArrowDown: '<path d="M12 5v14m6-6l-6 6-6-6"/>',
		// Material Symbol: check
		Check: '<path d="M4 12.5l5.5 5.5L20 6.5"/>',
		// Material Symbol: shield / verified_user
		ShieldAlert: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="13"/><circle cx="12" cy="16.5" r="0.75" fill="currentColor"/>',
		// Material Symbol: schedule / clock
		Clock: '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/>',
		// Material Symbol: open_in_new / external
		ExternalLink: '<path d="M14 3h7v7m0-7L10 14m-3-7H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3"/>',
		// Material Symbol: chevron_left
		ChevronLeft: '<path d="M15 18l-6-6 6-6"/>',
		// Material Symbol: chevron_right
		ChevronRight: '<path d="M9 18l6-6-6-6"/>',
		// Material Symbol: expand_more / chevron_down
		ChevronDown: '<path d="M6 9l6 6 6-6"/>',
		// Material Symbol: warning / alert
		AlertTriangle: '<path d="M12 3L2 20h20L12 3zm0 6v5m0 3h.01"/>',
		// Material Symbol: print
		Printer: '<path d="M6 9V3h12v6m-12 9H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2m-12-4h12v7H6z"/>',
		// Material Symbol: location_on
		MapPin: '<path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7z"/><circle cx="12" cy="9" r="2.5"/>',
		// Material Symbol: info
		Info: '<circle cx="12" cy="12" r="9"/><line x1="12" y1="16" x2="12" y2="12"/><circle cx="12" cy="8.5" r="0.75" fill="currentColor"/>',
		// Material Symbol: lock
		Lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		// Material Symbol: adjust / target
		Target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
		// Material Symbol: person / user
		User: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		// Material Symbol: bolt / zap
		Zap: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
		// Material Symbol: local_fire_department / flame
		Flame: '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>'
	};

	root.GearEngineTriageIcons = {
		/**
		 * Return an SVG string for the named icon, falling back to Activity.
		 * Uses CSS variable --ge-icon-stroke for seamless weight/thickness control.
		 */
		get: function (name, size, className) {
			var body = PATHS[name] || PATHS.Activity;
			var sz = size || 24;
			return '<svg xmlns="http://www.w3.org/2000/svg" width="' + sz + '" height="' + sz +
				'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"' +
				(className ? ' class="' + className + '"' : '') + '>' + body + '</svg>';
		}
	};
})(typeof self !== 'undefined' ? self : this);
