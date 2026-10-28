/**
 * Gear Engine Triage: quiz engine.
 *
 * Renders a rule-based triage quiz into every `.gear-engine-triage` element.
 * Each element carries its JSON config in a `script.gear-engine-triage__config` child.
 * All dynamic text is escaped before it is written to the DOM.
 *
 * @package GearEngine
 */
(function (root) {
	'use strict';

	var Rules = root.GearEngineTriageRules;
	var Icons = root.GearEngineTriageIcons;
	if (!Rules || !Icons) {
		return;
	}

	var STORAGE_PREFIX = 'gear_engine_triage_';
	var NONE_IDS = ['ingen', 'none'];
	var ANALYZING_TEXTS = [
		'Gjennomgår dine svar...',
		'Krysssjekker mot kliniske retningslinjer...',
		'Klargjør skreddersydde tiltak...'
	];

	function esc(value) {
		return String(value === null || value === undefined ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function safeUrl(url) {
		return typeof url === 'string' && /^https?:\/\//i.test(url.trim()) ? url.trim() : '';
	}

	function clone(value) {
		return JSON.parse(JSON.stringify(value));
	}

	function icon(name, size, className) {
		return Icons.get(name, size, className);
	}

	function severityClass(severity) {
		if (severity === 'Kritisk' || severity === 'Høy') {
			return 'is-high';
		}
		return severity === 'Moderat' ? 'is-moderate' : 'is-low';
	}

	function TriageApp(el, config, region) {
		this.el = el;
		this.config = config;
		this.region = region;
		this.storageKey = STORAGE_PREFIX + region;
		this.answers = clone(config.defaultAnswers || {});
		this.stepIndex = 0;
		this.visibleSteps = [];
		this.disclaimerAccepted = false;
		this.direction = 'none';
		this.busy = false;
		this.timers = [];
		this.savedState = null;
		this.sliderValue = 5;
		this.noteId = '';
	}

	TriageApp.prototype.init = function () {
		var self = this;
		this.savedState = this.loadState();
		this.updateVisibleSteps();

		this.el.addEventListener('click', function (event) {
			var target = event.target.closest('[data-action]');
			if (target && self.el.contains(target)) {
				self.handleAction(target.getAttribute('data-action'), target, event);
			}
		});
		this.el.addEventListener('input', function (event) {
			if (event.target.matches('input[type="range"]')) {
				self.handleSliderInput(event.target);
			}
		});
		this.el.addEventListener('change', function (event) {
			if (event.target.matches('[data-role="disclaimer"]')) {
				self.disclaimerAccepted = event.target.checked;
				self.direction = 'none';
				self.render();
				var box = self.el.querySelector('[data-role="disclaimer"]');
				if (box) { box.focus(); }
			}
		});
		this.bindSwipe();
		this.render();
	};

	/* ---------- State ---------- */

	TriageApp.prototype.loadState = function () {
		try {
			var parsed = JSON.parse(window.localStorage.getItem(this.storageKey));
			if (parsed && parsed.stepIndex > 0 && parsed.answers && typeof parsed.answers === 'object') {
				return parsed;
			}
		} catch (e) {
			// Storage unavailable or corrupt; start fresh.
		}
		return null;
	};

	TriageApp.prototype.saveState = function () {
		try {
			if (this.stepIndex > 0 && this.stepIndex <= this.visibleSteps.length) {
				window.localStorage.setItem(this.storageKey, JSON.stringify({ answers: this.answers, stepIndex: this.stepIndex }));
			}
		} catch (e) {
			// Ignore storage errors.
		}
	};

	TriageApp.prototype.clearState = function () {
		this.savedState = null;
		try {
			window.localStorage.removeItem(this.storageKey);
		} catch (e) {
			// Ignore storage errors.
		}
	};

	/* ---------- Flow ---------- */

	TriageApp.prototype.updateVisibleSteps = function () {
		var answers = this.answers;
		this.visibleSteps = (this.config.steps || []).filter(function (step) {
			return !step.visibleIf || Rules.matches(step.visibleIf, answers);
		});
	};

	TriageApp.prototype.phase = function () {
		var n = this.visibleSteps.length;
		if (this.stepIndex === 0) { return 'welcome'; }
		if (this.stepIndex <= n) { return 'step'; }
		if (this.stepIndex === n + 1) { return 'analyzing'; }
		if (this.stepIndex === n + 2) { return 'disclaimer'; }
		return 'result';
	};

	TriageApp.prototype.go = function (index, direction) {
		this.stepIndex = Math.max(0, index);
		this.direction = direction;
		this.updateVisibleSteps();
		this.saveState();
		this.render();
		this.scrollIntoView();
	};

	TriageApp.prototype.later = function (fn, ms) {
		this.timers.push(window.setTimeout(fn, ms));
	};

	TriageApp.prototype.clearTimers = function () {
		this.timers.forEach(function (t) { window.clearTimeout(t); });
		this.timers = [];
	};

	TriageApp.prototype.scrollIntoView = function () {
		var top = this.el.getBoundingClientRect().top + window.scrollY - 40;
		if (window.scrollY > top + 50) {
			window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
		}
	};

	TriageApp.prototype.bindSwipe = function () {
		var self = this;
		var startX = 0;
		var startY = 0;
		this.el.addEventListener('touchstart', function (e) {
			if (e.target.tagName === 'INPUT') { return; }
			startX = e.changedTouches[0].screenX;
			startY = e.changedTouches[0].screenY;
		}, { passive: true });
		this.el.addEventListener('touchend', function (e) {
			if (e.target.tagName === 'INPUT') { return; }
			var dx = e.changedTouches[0].screenX - startX;
			var dy = Math.abs(e.changedTouches[0].screenY - startY);
			// Only a mostly horizontal swipe goes back, so scrolling never triggers it.
			if (dx > 60 && dx > dy * 1.5 && self.phase() === 'step' && self.stepIndex > 1 && !self.busy) {
				self.go(self.stepIndex - 1, 'backward');
			}
		}, { passive: true });
	};

	/* ---------- Actions ---------- */

	TriageApp.prototype.handleAction = function (action, target) {
		var step;
		switch (action) {
			case 'start':
				this.clearState();
				this.answers = clone(this.config.defaultAnswers || {});
				this.go(1, 'forward');
				break;
			case 'resume':
				if (this.savedState) {
					this.answers = Object.assign(clone(this.config.defaultAnswers || {}), this.savedState.answers);
					this.updateVisibleSteps();
					var resumeAt = Math.min(this.savedState.stepIndex, this.visibleSteps.length);
					this.savedState = null;
					this.go(resumeAt, 'forward');
				}
				break;
			case 'back':
				if (!this.busy) { this.go(this.stepIndex - 1, 'backward'); }
				break;
			case 'single':
				this.selectSingle(target.getAttribute('data-step'), target.getAttribute('data-option'));
				break;
			case 'multi':
				this.toggleMulti(target.getAttribute('data-step'), target.getAttribute('data-option'));
				break;
			case 'next':
				step = this.visibleSteps[this.stepIndex - 1];
				if (step && step.type === 'slider') {
					this.answers[step.id] = this.sliderValue;
				}
				if (!this.busy) { this.advance(); }
				break;
			case 'accept':
				if (this.disclaimerAccepted) { this.go(this.stepIndex + 1, 'forward'); }
				break;
			case 'toggle-note':
				this.toggleNote();
				break;
			case 'print':
				window.print();
				break;
			case 'print-plan':
				this.printPlan();
				break;
			case 'reset':
				this.reset();
				break;
		}
	};

	TriageApp.prototype.advance = function () {
		this.go(this.stepIndex + 1, 'forward');
		if (this.phase() === 'analyzing') {
			this.clearState();
			this.startAnalyzing();
		}
	};

	TriageApp.prototype.selectSingle = function (stepId, optionId) {
		var self = this;
		if (this.busy) { return; }
		this.answers[stepId] = optionId;
		this.direction = 'none';
		this.render();
		this.busy = true;
		this.later(function () {
			self.busy = false;
			self.advance();
		}, 350);
	};

	TriageApp.prototype.toggleMulti = function (stepId, optionId) {
		var current = Array.isArray(this.answers[stepId]) ? this.answers[stepId] : [];
		if (NONE_IDS.indexOf(optionId) !== -1) {
			this.answers[stepId] = [optionId];
		} else {
			var withoutNone = current.filter(function (id) { return NONE_IDS.indexOf(id) === -1; });
			this.answers[stepId] = withoutNone.indexOf(optionId) !== -1
				? withoutNone.filter(function (id) { return id !== optionId; })
				: withoutNone.concat(optionId);
		}
		this.updateVisibleSteps();
		this.direction = 'none';
		this.saveState();
		this.render();
	};

	TriageApp.prototype.handleSliderInput = function (input) {
		this.sliderValue = parseInt(input.value, 10) || 0;
		var out = this.el.querySelector('[data-role="slider-value"]');
		if (out) { out.textContent = String(this.sliderValue); }
		input.style.setProperty('--get-fill', (this.sliderValue * 10) + '%');
	};

	TriageApp.prototype.startAnalyzing = function () {
		var self = this;
		var el = null;
		ANALYZING_TEXTS.slice(1).forEach(function (text, i) {
			self.later(function () {
				el = self.el.querySelector('[data-role="analyzing-text"]');
				if (el) { el.textContent = text; }
			}, (i + 1) * 1000);
		});
		this.later(function () { self.go(self.stepIndex + 1, 'forward'); }, 3200);
	};

	TriageApp.prototype.toggleNote = function () {
		var note = this.el.querySelector('[data-role="note"]');
		var toggle = this.el.querySelector('[data-action="toggle-note"]');
		if (!note || !toggle) { return; }
		var open = note.classList.toggle('is-open');
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
	};

	TriageApp.prototype.reset = function () {
		this.clearTimers();
		this.busy = false;
		this.answers = clone(this.config.defaultAnswers || {});
		this.disclaimerAccepted = false;
		this.noteId = '';
		this.clearState();
		this.go(0, 'backward');
	};

	/* ---------- Result helpers ---------- */

	TriageApp.prototype.getDiagnosis = function () {
		var diagnoses = this.config.diagnoses || {};
		var id = Rules.resolveDiagnosis(this.config, this.answers);
		var res = diagnoses[id] || diagnoses[this.config.defaultDiagnosis] || null;
		return res;
	};

	TriageApp.prototype.readableAnswer = function (step, value) {
		if (step.type === 'slider') { return value + ' / 10'; }
		if (value === null || value === undefined || (Array.isArray(value) && value.length === 0)) {
			return 'Ikke oppgitt';
		}
		var title = function (id) {
			if (NONE_IDS.indexOf(id) !== -1) { return 'Ingen / Ikke aktuelt'; }
			var opt = (step.options || []).filter(function (o) { return o.id === id; })[0];
			return opt ? opt.title : id;
		};
		if (Array.isArray(value)) {
			var real = value.filter(function (v) { return NONE_IDS.indexOf(v) === -1; });
			return real.length ? real.map(title).join(' + ') : 'Ingen rapportert';
		}
		return title(value);
	};

	/* ---------- Rendering ---------- */

	TriageApp.prototype.render = function () {
		var phase = this.phase();
		var html = '';
		var n = this.visibleSteps.length;

		if (phase === 'step') {
			html += this.renderProgress();
		}
		html += '<div class="get-stage get-anim-' + esc(this.direction) + '" data-phase="' + phase + '" tabindex="-1">';
		if (phase === 'welcome') { html += this.renderWelcome(); }
		else if (phase === 'step') { html += this.renderStep(this.visibleSteps[this.stepIndex - 1]); }
		else if (phase === 'analyzing') { html += this.renderAnalyzing(); }
		else if (phase === 'disclaimer') { html += this.renderDisclaimer(); }
		else { html += this.renderResult(); }
		html += '</div>';

		this.el.setAttribute('data-steps', String(n));
		this.el.querySelectorAll('.get-app').forEach(function (node) { node.remove(); });
		var app = document.createElement('div');
		app.className = 'get-app';
		app.innerHTML = html;
		this.el.appendChild(app);

		if (this.direction !== 'none') {
			var stage = app.querySelector('.get-stage');
			if (stage) { stage.focus({ preventScroll: true }); }
		}
	};

	TriageApp.prototype.renderProgress = function () {
		var n = this.visibleSteps.length;
		var pct = Math.round((this.stepIndex / n) * 100);
		return '<div class="get-progress">' +
			'<div class="get-progress__row">' +
			'<button type="button" class="get-link" data-action="back">' + icon('ChevronLeft', 18) + ' Tilbake</button>' +
			'<span class="get-progress__count">Steg ' + this.stepIndex + ' av ' + n + '</span></div>' +
			'<div class="get-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '">' +
			'<div class="get-progress__fill" style="width:' + pct + '%"></div></div></div>';
	};

	TriageApp.prototype.renderWelcome = function () {
		var c = this.config;
		var resume = '';
		if (this.savedState) {
			resume = '<div class="get-resume">' + icon('Clock', 24) +
				'<div><strong>Du har en påbegynt kartlegging</strong><p>Vil du fortsette der du slapp?</p></div>' +
				'<button type="button" class="get-btn get-btn--primary" data-action="resume">Fortsett</button></div>';
		}
		return resume +
			'<div class="get-card get-card--center">' +
			'<div class="get-hero-icon">' + icon('Activity', 40) + '</div>' +
			'<h2 class="get-title">' + esc(c.title) + '</h2>' +
			'<p class="get-lead">' + esc(c.description) + '</p>' +
			'<button type="button" class="get-btn get-btn--navy get-btn--block" data-action="start">' +
			(this.savedState ? 'Start på nytt' : 'Start kartlegging') + ' ' + icon('ChevronRight', 20) + '</button></div>';
	};

	TriageApp.prototype.renderOption = function (step, opt, active) {
		var multi = step.type === 'multiple';
		return '<button type="button" class="get-option' + (active ? ' is-active' : '') + '" data-action="' + (multi ? 'multi' : 'single') +
			'" data-step="' + esc(step.id) + '" data-option="' + esc(opt.id) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
			'<span class="get-option__icon">' + icon(opt.icon || 'Activity', 26) + '</span>' +
			'<span class="get-option__text"><span class="get-option__title">' + esc(opt.title) + '</span>' +
			(opt.subtitle ? '<span class="get-option__sub">' + esc(opt.subtitle) + '</span>' : '') + '</span>' +
			'<span class="get-option__mark get-option__mark--' + (multi ? 'box' : 'radio') + '">' + (active ? icon('Check', 14) : '') + '</span></button>';
	};

	TriageApp.prototype.renderStep = function (step) {
		var self = this;
		var html = '<h2 class="get-question">' + esc(step.title) + '</h2>';
		if (step.description) { html += '<p class="get-lead">' + esc(step.description) + '</p>'; }
		if (step.type === 'multiple') { html += '<span class="get-hint">Du kan velge flere</span>'; }
		if (step.helpText) { html += '<div class="get-help">' + icon('Info', 22) + '<p>' + esc(step.helpText) + '</p></div>'; }

		if (step.type === 'slider') {
			var current = typeof this.answers[step.id] === 'number' ? this.answers[step.id] : 5;
			this.sliderValue = current;
			html += '<div class="get-card get-card--center">' +
				'<input type="range" min="0" max="10" step="1" value="' + current + '" style="--get-fill:' + (current * 10) + '%" aria-label="' + esc(step.title) + '">' +
				'<div class="get-slider__scale"><span>0</span><span class="get-slider__value" data-role="slider-value">' + current + '</span><span>10</span></div></div>';
		} else {
			var selected = this.answers[step.id];
			html += '<div class="get-options">';
			(step.options || []).forEach(function (opt) {
				var active = Array.isArray(selected) ? selected.indexOf(opt.id) !== -1 : selected === opt.id;
				html += self.renderOption(step, opt, active);
			});
			html += '</div>';
		}

		if (step.type === 'multiple' || step.type === 'slider') {
			var ready = step.type === 'slider' || (Array.isArray(this.answers[step.id]) && this.answers[step.id].length > 0);
			html += '<button type="button" class="get-btn get-btn--navy get-btn--block" data-action="next"' + (ready ? '' : ' disabled') + '>Neste ' + icon('ChevronRight', 20) + '</button>';
		}
		return html;
	};

	TriageApp.prototype.renderAnalyzing = function () {
		return '<div class="get-card get-card--center" role="status" aria-live="polite">' +
			'<div class="get-hero-icon get-pulse">' + icon('Activity', 40) + '</div>' +
			'<h2 class="get-title">Analyserer...</h2>' +
			'<p class="get-lead" data-role="analyzing-text">' + esc(ANALYZING_TEXTS[0]) + '</p></div>';
	};

	TriageApp.prototype.renderDisclaimer = function () {
		return '<div class="get-card get-card--center get-dim"><div class="get-hero-icon get-hero-icon--solid">' + icon('Check', 40) + '</div>' +
			'<h2 class="get-title">Vurderingen er klar</h2></div>' +
			'<div class="get-sheet" role="dialog" aria-modal="false" aria-labelledby="get-disclaimer-title">' +
			'<div class="get-sheet__head">' + icon('AlertTriangle', 28) + '<div><h3 id="get-disclaimer-title">Viktig informasjon</h3>' +
			'<p>Dette er et digitalt verktøy basert på medisinske retningslinjer. Resultatet er kun ment som et informativt hjelpemiddel, og erstatter ikke en fysisk undersøkelse.</p></div></div>' +
			'<label class="get-check"><input type="checkbox" data-role="disclaimer"' + (this.disclaimerAccepted ? ' checked' : '') + '> <span>Jeg forstår og aksepterer dette.</span></label>' +
			'<button type="button" class="get-btn get-btn--navy get-btn--block" data-action="accept"' + (this.disclaimerAccepted ? '' : ' disabled') + '>Vis resultat</button></div>';
	};

	TriageApp.prototype.renderResult = function () {
		var res = this.getDiagnosis();
		if (!res) { return ''; }
		var html = '<div class="get-result">';
		var program = res.trainingProgram && safeUrl(res.trainingProgram.url) ? res.trainingProgram : null;

		html += '<div class="get-result__head get-noprint get-pp"><span class="get-badge">' + icon('Check', 14) + ' Vurdering fullført</span>' +
			'<h2 class="get-title">' + esc(res.title) + '</h2>' +
			'<div class="get-tags"><span class="get-tag">Kategori: ' + esc(res.category) + '</span>' +
			'<span class="get-tag get-tag--severity ' + severityClass(res.severity) + '"><i></i> Alvorlighetsgrad: ' + esc(res.severity) + '</span></div></div>';

		html += '<div class="get-card get-noprint get-pp"><h3>Hva betyr dette?</h3><p>' + esc(res.patientDesc) + '</p>' +
			(res.patientDiff ? '<p class="get-muted"><strong>Kan forveksles med:</strong> ' + esc(res.patientDiff) + '</p>' : '') + '</div>';

		html += this.renderPlan(res);

		if (program) {
			html += '<div class="get-card get-card--cta get-noprint"><h3>Klar for å komme i gang?</h3>' +
				'<p>Vi har satt sammen et kvalitetssikret treningsprogram som bygger direkte på denne vurderingen.</p>' +
				'<a class="get-btn get-btn--primary get-btn--block" href="' + esc(safeUrl(program.url)) + '" target="_blank" rel="noopener noreferrer">' +
				esc(program.title) + ' ' + icon('ExternalLink', 18) + '</a></div>';
		}

		html += '<button type="button" class="get-note-toggle get-noprint" data-action="toggle-note" aria-expanded="false" aria-controls="get-note-' + esc(this.region) + '">' +
			icon('Lock', 20) + '<span>Vis klinisk notat</span><span class="get-pill">Helsepersonell</span>' + icon('ChevronDown', 20, 'get-chevron') + '</button>';
		html += this.renderNote(res);

		html += '<div class="get-actions get-noprint">' +
			'<button type="button" class="get-btn get-btn--ghost" data-action="print-plan">' + icon('Printer', 18) + ' Skriv ut min plan</button>' +
			'<button type="button" class="get-btn get-btn--ghost" data-action="print">' + icon('Printer', 18) + ' Skriv ut klinisk notat</button>' +
			'<button type="button" class="get-btn get-btn--ghost" data-action="reset">' + icon('RotateCcw', 18) + ' Start på nytt</button></div></div>';
		return html;
	};

	/* Prints the patient's own plan instead of the clinical note. */
	TriageApp.prototype.printPlan = function () {
		var el = this.el;
		var cls = 'get-print-plan';
		var clear = function () {
			el.classList.remove(cls);
			window.removeEventListener('afterprint', clear);
		};
		el.classList.add(cls);
		window.addEventListener('afterprint', clear);
		window.print();
	};

	TriageApp.prototype.renderPlan = function (res) {
		var n = 0;
		var step = function (title, body) {
			n++;
			return '<div class="get-plan__step"><div class="get-plan__head"><span class="get-plan__num">' + n + '</span><h4>' + esc(title) + '</h4></div>' + body + '</div>';
		};
		var html = '<div class="get-plan get-noprint get-pp"><h3>Din handlingsplan</h3>';
		if (res.phase1) {
			html += step('Dette bør du gjøre nå', '<p>' + esc(res.phase1) + '</p>');
		}
		if (res.exercises && res.exercises.length) {
			html += step('Anbefalte tiltak', '<ul>' + res.exercises.map(function (ex) {
				return '<li>' + icon('Check', 16) + '<span>' + esc(ex) + '</span></li>';
			}).join('') + '</ul>');
		}
		html += step('Veien videre', '<p>Når de mest akutte smertene legger seg, er det essensielt å bygge opp igjen vevsstyrken med gradert trening for å unngå at problemet kommer tilbake.</p>');
		return html + '</div>';
	};

	TriageApp.prototype.renderNote = function (res) {
		var self = this;
		if (!this.noteId) {
			this.noteId = 'GE-' + String(Date.now()).slice(-6);
		}
		var date = new Date().toLocaleDateString('no-NO');
		var isFlagStep = function (s) { return s.id === 'redFlags' || s.id === 'red_flags'; };
		var redFlagStep = this.visibleSteps.filter(isFlagStep)[0];

		var html = '<div id="get-note-' + esc(this.region) + '" class="get-note" data-role="note">' +
			'<div class="get-note__inner"><div class="get-note__head"><div><h4>Klinisk oppsummering</h4>' +
			'<span class="get-mono">ID: ' + esc(this.noteId) + '</span> <span class="get-muted">Algoritme: ' + esc(this.config.title) + '</span></div>' +
			'<div class="get-note__date"><strong>' + esc(date) + '</strong><span>Autogenerert utskrift</span></div></div>';

		html += '<h5>1. Anamnese (pasientrapportert)</h5><table class="get-table"><tbody>';
		this.visibleSteps.filter(function (s) { return !isFlagStep(s); }).forEach(function (step) {
			html += '<tr><th scope="row">' + esc(step.title) + '</th><td>' + esc(self.readableAnswer(step, self.answers[step.id])) + '</td></tr>';
		});
		html += '</tbody></table>';

		var next = 2;
		if (redFlagStep && this.answers[redFlagStep.id]) {
			var flags = this.answers[redFlagStep.id];
			var real = flags.filter(function (f) { return NONE_IDS.indexOf(f) === -1; });
			html += '<h5>' + next++ + '. Sikkerhet og røde flagg</h5>' +
				(real.length
					? '<p class="get-flag is-alert">' + icon('AlertTriangle', 18) + ' MULIGE VARSELSYMPTOMER: ' + esc(this.readableAnswer(redFlagStep, real)) + '</p>'
					: '<p class="get-flag is-ok">' + icon('Check', 18) + ' Ingen varselsymptomer (røde flagg) avdekket.</p>');
		}

		html += '<h5>' + next + '. Klinisk vurdering (arbeidshypotese)</h5><div class="get-hypothesis"><p>' + esc(res.clinicalHypothesis) + '</p>' +
			'<div class="get-codes"><span>ICPC-2 <b class="get-mono">' + esc(res.icpc2) + '</b></span><span>ICD-10 <b class="get-mono">' + esc(res.icd10) + '</b></span></div></div>' +
			'</div></div>';
		return html;
	};

	function boot() {
		document.querySelectorAll('.gear-engine-triage').forEach(function (el) {
			if (el.getAttribute('data-initialized')) { return; }
			var script = el.querySelector('script.gear-engine-triage__config');
			if (!script) { return; }
			try {
				var config = JSON.parse(script.textContent);
				el.setAttribute('data-initialized', 'true');
				new TriageApp(el, config, el.getAttribute('data-region') || 'default').init();
			} catch (e) {
				el.insertAdjacentHTML('beforeend', '<p class="get-error">Kunne ikke laste kartleggingen.</p>');
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(window);
