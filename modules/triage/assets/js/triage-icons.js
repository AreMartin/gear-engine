/**
 * Gear Engine Triage: inline SVG icon set (Feather-style, stroke based).
 *
 * @package GearEngine
 */
(function (root) {
	'use strict';

	var PATHS = {
		Activity: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
		RotateCcw: '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><polyline points="3 3 3 8 8 8"/>',
		ArrowRight: '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
		ArrowUp: '<line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>',
		ArrowDown: '<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>',
		Check: '<polyline points="20 6 9 17 4 12"/>',
		ShieldAlert: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
		Clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
		ExternalLink: '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
		ChevronLeft: '<polyline points="15 18 9 12 15 6"/>',
		ChevronRight: '<polyline points="9 18 15 12 9 6"/>',
		ChevronDown: '<polyline points="6 9 12 15 18 9"/>',
		AlertTriangle: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
		Printer: '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
		MapPin: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
		Info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
		Lock: '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		Target: '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
		User: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		Zap: '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
		Flame: '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>'
	};

	root.GearEngineTriageIcons = {
		/**
		 * Return an SVG string for the named icon, falling back to Activity.
		 */
		get: function (name, size, className) {
			var body = PATHS[name] || PATHS.Activity;
			return '<svg xmlns="http://www.w3.org/2000/svg" width="' + (size || 24) + '" height="' + (size || 24) +
				'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"' +
				(className ? ' class="' + className + '"' : '') + '>' + body + '</svg>';
		}
	};
})(typeof self !== 'undefined' ? self : this);
