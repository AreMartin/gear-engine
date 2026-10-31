/**
 * Gear Engine Triage: quiz engine.
 *
 * Renders a rule-based triage quiz into every `.gear-engine-triage` element.
 * 100% harmonized with Material Design 3 and Fysionett visual identity.
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
			if (dx > 60 && dx > dy * 1.5 && self.phase() === 'step' && self.stepIndex > 1 && !self.busy) {
				self.go(self.stepIndex - 1, 'backward');
			}
		}, { passive: true });
	};

	/* ---------- Pain Helpers ---------- */

	TriageApp.prototype.painColor = function (v) {
		if (v <= 3) { return 'var(--ge-green-800, #005236)'; }
		if (v <= 6) { return '#B5770A'; }
		return '#D92D20';
	};

	TriageApp.prototype.painWord = function (v) {
		if (v === 0) { return 'Ingen smerte'; }
		if (v <= 3) { return 'Mild smerte'; }
		if (v <= 6) { return 'Moderat smerte'; }
		if (v <= 8) { return 'Betydelig smerte'; }
		return 'Svært sterk smerte';
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
				if (!this.busy && this.stepIndex > 0) {
					this.go(this.stepIndex - 1, 'backward');
				}
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
			case 'slider-preset':
				var presetVal = parseInt(target.getAttribute('data-value'), 10);
				if (!isNaN(presetVal)) {
					this.sliderValue = presetVal;
					var sliderEl = this.el.querySelector('input[type="range"]');
					if (sliderEl) {
						sliderEl.value = presetVal;
						this.handleSliderInput(sliderEl);
					}
				}
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
		}, 320);
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
		if (out) {
			out.textContent = String(this.sliderValue);
			out.style.color = this.painColor(this.sliderValue);
		}
		var word = this.el.querySelector('[data-role="slider-word"]');
		if (word) {
			word.textContent = this.painWord(this.sliderValue);
			word.style.color = this.painColor(this.sliderValue);
		}
		input.style.setProperty('--p', (this.sliderValue * 10) + '%');
		var step = this.visibleSteps[this.stepIndex - 1];
		if (step) {
			this.answers[step.id] = this.sliderValue;
		}
		var presets = this.el.querySelectorAll('[data-action="slider-preset"]');
		var val = this.sliderValue;
		presets.forEach(function (btn) {
			var pVal = parseInt(btn.getAttribute('data-value'), 10);
			if (pVal === val) {
				btn.classList.add('is-active');
			} else {
				btn.classList.remove('is-active');
			}
		});
		var nextBtn = this.el.querySelector('[data-action="next"]');
		if (nextBtn) { nextBtn.removeAttribute('disabled'); }
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
		var isOpen = note.style.display === 'block';
		note.style.display = isOpen ? 'none' : 'block';
		toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
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
		return diagnoses[id] || diagnoses[this.config.defaultDiagnosis] || null;
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
		return '<div class="appbar">' +
			'<button type="button" class="ibtn sl" data-action="back" title="Forrige steg" aria-label="Forrige steg">' +
			icon('ChevronLeft', 22) +
			'</button>' +
			'<span class="title-l">' + esc(this.config.label || this.config.title || 'Smertesjekk') + '</span>' +
			'<span class="body-s muted num" style="font-weight:600">' + this.stepIndex + ' / ' + n + '</span>' +
			'</div>' +
			'<div class="steps-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '">' +
			'<div class="steps-fill" style="width:' + pct + '%"></div>' +
			'</div>';
	};

	TriageApp.prototype.renderWelcome = function () {
		var c = this.config;
		var resume = '';
		if (this.savedState) {
			resume = '<div class="card elevated" style="margin-bottom:16px;display:flex;gap:14px;align-items:center;background:#FFFFFF;border:1px solid var(--md-sys-color-outline-variant,#E4E7EC);border-radius:16px;padding:16px 20px">' +
				'<div style="color:var(--md-sys-color-primary,#0C2340);flex:none">' + icon('Clock', 26) + '</div>' +
				'<div style="flex:1"><strong>Du har en påbegynt kartlegging</strong><p class="body-s muted" style="margin-top:2px">Vil du fortsette der du slapp?</p></div>' +
				'<button type="button" class="btn filled primary sl" data-action="resume" style="height:38px;padding:0 16px;font-size:13.5px">Fortsett</button>' +
				'</div>';
		}
		return resume +
			'<div class="hero" style="text-align:center;padding:36px 24px">' +
			'<div style="width:56px;height:56px;border-radius:50%;background:#FFFFFF;color:var(--md-sys-color-primary,#0C2340);display:grid;place-items:center;margin:0 auto 16px;box-shadow:var(--md-sys-elevation-1)">' +
			icon('Activity', 28) +
			'</div>' +
			'<h2 class="headline-s" style="margin-bottom:8px">' + esc(c.title) + '</h2>' +
			'<p class="body-m" style="margin-bottom:24px;opacity:0.9">' + esc(c.description) + '</p>' +
			'<button type="button" class="btn filled primary sl" data-action="start" style="height:48px;font-size:15px;font-weight:600;padding:0 32px">' +
			(this.savedState ? 'Start på nytt' : 'Start kartlegging') + ' ' + icon('ChevronRight', 20) +
			'</button>' +
			'</div>';
	};

	TriageApp.prototype.renderOption = function (step, opt, active, isFlags) {
		var multi = step.type === 'multiple';
		var isNone = NONE_IDS.indexOf(opt.id) !== -1;
		var cls = 'opt sl';
		if (multi) { cls += ' multi'; }
		if (isNone) { cls += ' none'; }
		if (active) { cls += ' is-active'; }

		return '<button type="button" class="' + cls + '" data-action="' + (multi ? 'multi' : 'single') +
			'" data-step="' + esc(step.id) + '" data-option="' + esc(opt.id) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
			'<span class="oi">' + icon(opt.icon || (isFlags && !isNone ? 'AlertTriangle' : 'Activity'), 20) + '</span>' +
			'<span class="ot"><b style="color:var(--md-sys-color-on-surface,#0C2340)">' + esc(opt.title) + '</b>' +
			(opt.subtitle ? '<small>' + esc(opt.subtitle) + '</small>' : '') + '</span>' +
			'<span class="ctl">' + (active ? icon('Check', 14) : '') + '</span>' +
			'</button>';
	};

	TriageApp.prototype.renderStep = function (step) {
		var self = this;
		var n = this.visibleSteps.length;
		var isLast = this.stepIndex === n;
		var isFlags = step.flags || (step.id && step.id.toLowerCase().indexOf('flag') !== -1);

		var html = '<div class="overline" style="margin-top:4px">' + (isFlags ? 'Sikkerhet' : 'Spørsmål ' + this.stepIndex + ' av ' + n) + '</div>' +
			'<h2 class="headline-s" style="margin-top:4px">' + esc(step.title) + '</h2>';

		if (step.description) {
			html += '<p class="body-m muted" style="margin-top:6px">' + esc(step.description) + '</p>';
		}
		if (step.helpText) {
			html += '<div style="margin-top:14px;padding:12px 16px;border-radius:12px;background:var(--md-sys-color-secondary-container,#F2F4F7);display:flex;gap:12px;align-items:flex-start">' +
				'<span style="color:var(--md-sys-color-primary,#0C2340);flex:none;margin-top:2px">' + icon('Info', 20) + '</span>' +
				'<span class="body-m" style="color:var(--md-sys-color-on-surface,#0C2340)">' + esc(step.helpText) + '</span></div>';
		}

		var hasAnswer = false;

		if (step.type === 'slider') {
			var current = typeof this.answers[step.id] === 'number' ? this.answers[step.id] : (this.sliderValue || 5);
			this.sliderValue = current;
			hasAnswer = true;
			html += '<div class="painbox">' +
				'<div class="pain-val num" data-role="slider-value" style="color:' + this.painColor(current) + '">' + current + '</div>' +
				'<div class="pain-word" data-role="slider-word" style="color:' + this.painColor(current) + '">' + this.painWord(current) + '</div>' +
				'<input class="slider" type="range" min="0" max="10" step="1" value="' + current + '" style="--p:' + (current * 10) + '%" aria-label="' + esc(step.title) + '">' +
				'<div class="ticks"><span>0 (Ingen)</span><span>5 (Moderat)</span><span>10 (Verst)</span></div>' +
				'<div class="chips-row" style="justify-content:center;margin-top:16px;flex-wrap:wrap">' +
				[[0, 'Ingen'], [2, 'Mild'], [5, 'Moderat'], [7, 'Sterk'], [10, 'Verst']].map(function (x) {
					var on = current === x[0];
					return '<button type="button" class="chip sl' + (on ? ' is-active' : '') + '" data-action="slider-preset" data-value="' + x[0] + '">' + x[1] + '</button>';
				}).join('') +
				'</div></div>';
		} else {
			var selected = this.answers[step.id];
			if (step.type === 'multiple') {
				hasAnswer = Array.isArray(selected) && selected.length > 0;
			} else {
				hasAnswer = selected !== undefined && selected !== null && selected !== '';
			}
			html += '<div class="opts' + (isFlags ? ' flags' : '') + '" role="' + (step.type === 'multiple' ? 'group' : 'radiogroup') + '">';
			(step.options || []).forEach(function (opt) {
				var active = Array.isArray(selected) ? selected.indexOf(opt.id) !== -1 : selected === opt.id;
				html += self.renderOption(step, opt, active, isFlags);
			});
			html += '</div>';
		}

		// Sticky bottom dock for all steps
		html += '<div class="dock">' +
			'<button type="button" class="btn outlined btn-back sl" data-action="back" title="Forrige steg">' +
			icon('ChevronLeft', 18) + ' <span>Forrige</span>' +
			'</button>' +
			'<button type="button" class="btn filled primary sl" data-action="next"' + (hasAnswer ? '' : ' disabled') + '>' +
			(isLast ? 'Fullfør vurdering' : 'Neste steg') + ' ' + icon('ChevronRight', 18) +
			'</button>' +
			'</div>';

		return html;
	};

	TriageApp.prototype.renderAnalyzing = function () {
		return '<div style="text-align:center;padding:80px 20px" role="status" aria-live="polite">' +
			'<svg class="cpi" viewBox="0 0 48 48">' +
			'<circle cx="24" cy="24" r="20"/>' +
			'</svg>' +
			'<h2 class="headline-s" style="margin-bottom:8px">Analyserer svarene dine...</h2>' +
			'<p class="body-m muted" data-role="analyzing-text">' + esc(ANALYZING_TEXTS[0]) + '</p>' +
			'</div>';
	};

	TriageApp.prototype.renderDisclaimer = function () {
		return '<div class="card xl" style="background:#FFFFFF;border:1px solid var(--md-sys-color-outline-variant,#E4E7EC);border-radius:24px;padding:32px 24px;box-shadow:var(--md-sys-elevation-1);margin-top:20px">' +
			'<div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">' +
			'<div style="width:44px;height:44px;border-radius:50%;background:#FEF3C7;color:#92400E;display:grid;place-items:center;flex:none;border:1px solid #FDE68A">' +
			icon('AlertTriangle', 24) +
			'</div>' +
			'<div><h3 class="headline-s" style="margin:0">Viktig informasjon</h3><span class="body-s muted">Før du ser din vurdering</span></div>' +
			'</div>' +
			'<p class="body-m" style="line-height:22px;color:var(--md-sys-color-on-surface-variant,#475467)">' +
			'Dette er et digitalt kartleggingsverktøy basert på anerkjente faglige retningslinjer for primærhelsetjenesten. Resultatet er kun ment som et informativt hjelpemiddel, og erstatter ikke en individuell undersøkelse hos lege eller fysioterapeut.' +
			'</p>' +
			'<label class="sl" style="display:flex;align-items:center;gap:12px;margin:24px 0;padding:14px 16px;border-radius:12px;background:var(--md-sys-color-surface-container-low,#F8F9FA);border:1px solid var(--md-sys-color-outline-variant,#E4E7EC);cursor:pointer">' +
			'<input type="checkbox" data-role="disclaimer"' + (this.disclaimerAccepted ? ' checked' : '') + ' style="width:20px;height:20px;accent-color:var(--md-sys-color-primary,#0C2340);cursor:pointer">' +
			'<span class="body-m" style="font-weight:600;color:var(--md-sys-color-on-surface,#0C2340)">Jeg forstår og aksepterer dette.</span>' +
			'</label>' +
			'<button type="button" class="btn filled primary block sl" data-action="accept"' + (this.disclaimerAccepted ? '' : ' disabled') + ' style="height:48px;font-size:15px;font-weight:600">' +
			'Vis resultat ' + icon('ChevronRight', 18) +
			'</button>' +
			'</div>';
	};

	TriageApp.prototype.renderResult = function () {
		var res = this.getDiagnosis();
		if (!res) { return ''; }

		var isHigh = res.severity === 'Kritisk' || res.severity === 'Høy';
		var isMod = res.severity === 'Moderat';

		var isFlagStep = function (s) { return s.flags || (s.id && s.id.toLowerCase().indexOf('flag') !== -1); };
		var redFlagStep = this.visibleSteps.filter(isFlagStep)[0];
		var flagCount = 0;
		if (redFlagStep && this.answers[redFlagStep.id]) {
			var flags = Array.isArray(this.answers[redFlagStep.id]) ? this.answers[redFlagStep.id] : [this.answers[redFlagStep.id]];
			flagCount = flags.filter(function (f) { return NONE_IDS.indexOf(f) === -1; }).length;
		}

		var painStep = this.visibleSteps.filter(function (s) { return s.type === 'slider' || s.id === 'pain'; })[0];
		var painVal = painStep && this.answers[painStep.id] !== undefined ? this.answers[painStep.id] : this.sliderValue;

		var sevPill = isHigh
			? '<span class="pill err">Bør undersøkes</span>'
			: (isMod ? '<span class="pill warn">Moderat</span>' : '<span class="pill tert">Lav alvorlighet</span>');

		var html = '<div class="get-result">';

		// App bar
		html += '<div class="appbar get-noprint">' +
			'<button type="button" class="ibtn sl" data-action="reset" title="Start på nytt">' + icon('RotateCcw', 20) + '</button>' +
			'<h1 class="title-l">Ditt resultat</h1>' +
			'</div>';

		// Hero
		html += '<div class="hero' + (isHigh ? ' high' : '') + '">' +
			'<span class="pill outl" style="border-color:currentColor;color:inherit;gap:6px">' + icon('Check', 14) + ' Vurdering fullført</span>' +
			'<h2>' + esc(res.title) + '</h2>' +
			'<p>' + esc(res.patientDesc) + '</p>' +
			'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">' +
			'<span class="pill sec">Kategori: ' + esc(res.category) + '</span>' +
			sevPill +
			'</div>' +
			'</div>';

		// High severity or red flags alert
		if (isHigh || flagCount > 0) {
			html += '<div style="margin-top:14px;padding:20px;border-radius:16px;background:var(--md-sys-color-error-container,#FEE2E2);color:var(--md-sys-color-on-error-container,#991B1B);border:1px solid #FCA5A5">' +
				'<h3 class="title-m" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">' +
				icon('AlertTriangle', 20) + ' Bli undersøkt' +
				'</h3>' +
				'<p class="body-m" style="margin-bottom:16px;line-height:22px">' +
				esc(res.phase1 || 'Ved akutte eller vedvarende sterke smerter bør du oppsøke lege eller legevakt for en fysisk undersøkelse.') +
				'</p>' +
				'<div style="display:grid;gap:8px">' +
				'<a class="btn err sl" href="tel:116117" style="display:flex;background:#B42318;color:#FFFFFF;text-decoration:none">' +
				icon('Phone', 18) + ' Ring Legevakt 116 117' +
				'</a>' +
				'</div>' +
				'</div>';
		}

		// Mini stats
		html += '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:14px">' +
			'<div style="background:#FFFFFF;border:1px solid var(--md-sys-color-outline-variant,#E4E7EC);border-radius:16px;padding:16px;box-shadow:var(--md-sys-elevation-1)">' +
			'<div class="body-s muted" style="font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Røde flagg</div>' +
			'<div class="headline-s" style="color:' + (flagCount > 0 ? 'var(--md-sys-color-error,#D92D20)' : 'var(--ge-green-800,#005236)') + ';margin-top:4px">' +
			(flagCount > 0 ? flagCount + ' funnet' : 'Ingen') +
			'</div>' +
			'</div>' +
			'<div style="background:#FFFFFF;border:1px solid var(--md-sys-color-outline-variant,#E4E7EC);border-radius:16px;padding:16px;box-shadow:var(--md-sys-elevation-1)">' +
			'<div class="body-s muted" style="font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Smertenivå</div>' +
			'<div class="headline-s num" style="color:var(--md-sys-color-primary,#0C2340);margin-top:4px">' +
			(typeof painVal === 'number' ? painVal + ' / 10' : '–') +
			'</div>' +
			'</div>' +
			'</div>';

		// Differential diagnosis
		if (res.patientDiff) {
			html += '<div style="margin-top:14px;padding:16px 20px;border-radius:16px;background:#FFFFFF;border:1px solid var(--md-sys-color-outline-variant,#E4E7EC);box-shadow:var(--md-sys-elevation-1)">' +
				'<div class="body-s muted" style="font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Kan forveksles med</div>' +
				'<p class="body-m" style="margin-top:4px;color:var(--md-sys-color-on-surface,#0C2340)">' + esc(res.patientDiff) + '</p>' +
				'</div>';
		}

		// Action plan
		html += this.renderPlan(res);

		// Training program (protocol)
		var program = res.trainingProgram && safeUrl(res.trainingProgram.url) ? res.trainingProgram : null;
		if (program) {
			html += '<div class="get-noprint" style="margin-top:18px;background:var(--md-sys-color-primary-container,#EAF0F6);border:1px solid var(--ge-navy-border,#D5E1ED);box-shadow:var(--md-sys-elevation-1);border-radius:24px;padding:24px">' +
				'<span class="pill" style="background:#FFFFFF;color:var(--md-sys-color-primary,#0C2340);border:1px solid #D0DBE5;font-weight:600;box-shadow:0 1px 2px rgba(12,35,64,0.04);gap:6px">' +
				'<span style="width:6px;height:6px;border-radius:50%;background:var(--ge-green-800,#005236);display:inline-block"></span>' +
				'Neste steg · Treningsprogram' +
				'</span>' +
				'<h3 class="headline-s" style="margin:14px 0 6px;color:var(--md-sys-color-primary,#0C2340)">Klar for å komme i gang?</h3>' +
				'<p class="body-m" style="color:var(--md-sys-color-on-surface-variant,#475467);line-height:22px">' +
				esc(program.description || 'Et kvalitetssikret treningsprogram som bygger direkte på denne vurderingen.') +
				'</p>' +
				'<div style="display:flex;justify-content:flex-end;margin-top:20px">' +
				'<a class="btn filled primary sl" style="height:48px;font-size:15px;font-weight:600;padding:0 24px;text-decoration:none" href="' + esc(safeUrl(program.url)) + '" target="_blank" rel="noopener noreferrer">' +
				esc(program.title || 'Åpne treningsprogram') + ' ' + icon('ExternalLink', 18) +
				'</a>' +
				'</div></div>';
		}

		// Actions bar
		html += '<div class="get-noprint" style="margin-top:20px;display:grid;grid-template-columns:1fr 1fr;gap:12px">' +
			'<button type="button" class="btn outlined sl" data-action="print-plan" style="height:44px;font-size:14px">' +
			icon('Printer', 18) + ' Skriv ut min plan' +
			'</button>' +
			'<button type="button" class="btn outlined sl" data-action="toggle-note" aria-expanded="false" aria-controls="get-note-' + esc(this.region) + '" style="height:44px;font-size:14px">' +
			icon('Lock', 18) + ' Helsepersonell ' + icon('ChevronDown', 18) +
			'</button>' +
			'</div>';

		// Clinical note (initially hidden, expands on click)
		html += this.renderNote(res);

		// Reset button
		html += '<div class="get-noprint" style="display:flex;justify-content:center;margin-top:20px">' +
			'<button type="button" class="btn text sl" data-action="reset" style="color:var(--md-sys-color-on-surface-variant,#475467)">' +
			icon('RotateCcw', 18) + ' Start kartleggingen på nytt' +
			'</button>' +
			'</div>';

		html += '</div>';
		return html;
	};

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
			return '<li>' +
				'<span class="n">' + n + '</span>' +
				'<div><h4>' + esc(title) + '</h4>' + body + '</div>' +
				'</li>';
		};

		var html = '<div class="card elevated" style="margin-top:16px;padding:24px;border:1px solid var(--md-sys-color-outline-variant,#E4E7EC);border-radius:20px;background:#FFFFFF">' +
			'<h3 class="title-l">Din plan</h3>' +
			'<ol class="timeline">';

		if (res.phase1) {
			html += step('Dette bør du gjøre nå', '<p style="margin-top:4px">' + esc(res.phase1) + '</p>');
		}
		if (res.exercises && res.exercises.length) {
			html += step('Anbefalte tiltak', '<div class="tiltak-chips">' +
				res.exercises.map(function (ex) {
					return '<div class="tiltak-chip">' +
						'<span style="color:var(--ge-green-800,#005236);flex:none">' + icon('Check', 16) + '</span>' +
						'<span>' + esc(ex) + '</span>' +
						'</div>';
				}).join('') +
				'</div>');
		}
		html += step('Veien videre', '<p style="margin-top:4px">Når de akutte smertene legger seg, bygger du opp styrken igjen med gradert trening for å forhindre tilbakefall.</p>');
		html += '</ol></div>';

		return html;
	};

	TriageApp.prototype.renderNote = function (res) {
		var self = this;
		if (!this.noteId) {
			this.noteId = 'GE-' + String(Date.now()).slice(-6);
		}
		var date = new Date().toLocaleDateString('no-NO');
		var isFlagStep = function (s) { return s.flags || (s.id && s.id.toLowerCase().indexOf('flag') !== -1); };
		var redFlagStep = this.visibleSteps.filter(isFlagStep)[0];

		var html = '<div id="get-note-' + esc(this.region) + '" class="doc-box" data-role="note" style="display:none">' +
			'<div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid var(--md-sys-color-outline-variant,#E4E7EC);padding-bottom:12px;margin-bottom:16px">' +
			'<div>' +
			'<h4 style="font-size:16px;font-weight:700;color:var(--md-sys-color-primary,#0C2340)">Klinisk konsultasjonsnotat</h4>' +
			'<span class="body-s muted">ID: <b>' + esc(this.noteId) + '</b> · Algoritme: ' + esc(this.config.title) + '</span>' +
			'</div>' +
			'<div style="text-align:right" class="body-s muted">' +
			'<strong style="color:var(--md-sys-color-primary,#0C2340)">' + esc(date) + '</strong><br><span>Autogenerert</span>' +
			'</div></div>';

		html += '<div class="overline" style="margin-bottom:6px">1. Anamnese (pasientrapportert)</div>' +
			'<table class="get-table"><tbody>';
		this.visibleSteps.filter(function (s) { return !isFlagStep(s); }).forEach(function (step) {
			html += '<tr><th scope="row">' + esc(step.title) + '</th><td>' + esc(self.readableAnswer(step, self.answers[step.id])) + '</td></tr>';
		});
		html += '</tbody></table>';

		var next = 2;
		if (redFlagStep && this.answers[redFlagStep.id]) {
			var flags = Array.isArray(this.answers[redFlagStep.id]) ? this.answers[redFlagStep.id] : [this.answers[redFlagStep.id]];
			var real = flags.filter(function (f) { return NONE_IDS.indexOf(f) === -1; });
			html += '<div class="overline" style="margin:20px 0 6px">' + next++ + '. Sikkerhet og røde flagg</div>' +
				(real.length
					? '<div style="padding:10px 14px;border-radius:8px;background:var(--md-sys-color-error-container,#FEE2E2);color:var(--md-sys-color-on-error-container,#991B1B);font-size:13.5px;display:flex;gap:8px;align-items:center">' +
					icon('AlertTriangle', 18) + ' <b>Mulige varselsymptomer:</b> ' + esc(this.readableAnswer(redFlagStep, real)) + '</div>'
					: '<div style="padding:10px 14px;border-radius:8px;background:var(--ge-green-100,#DCF5E8);color:var(--ge-green-800,#005236);font-size:13.5px;display:flex;gap:8px;align-items:center">' +
					icon('Check', 18) + ' <b>Ingen varselsymptomer (røde flagg) avdekket.</b></div>');
		}

		html += '<div class="overline" style="margin:20px 0 6px">' + next + '. Klinisk vurdering (arbeidshypotese)</div>' +
			'<p class="body-m" style="font-weight:600;color:var(--md-sys-color-primary,#0C2340);line-height:22px;margin-bottom:12px">' +
			esc(res.clinicalHypothesis || res.patientDesc) +
			'</p>' +
			'<div style="display:flex;gap:8px;flex-wrap:wrap">' +
			(res.icpc2 ? '<span class="code-badge">ICPC-2: ' + esc(res.icpc2) + '</span>' : '') +
			(res.icd10 ? '<span class="code-badge">ICD-10: ' + esc(res.icd10) + '</span>' : '') +
			'<span class="code-badge">Kategori: ' + esc(res.category) + '</span>' +
			'</div>' +
			'<div class="get-noprint" style="display:flex;justify-content:flex-end;margin-top:16px">' +
			'<button type="button" class="btn outlined sl" data-action="print" style="height:36px;font-size:13px;padding:0 16px">' +
			icon('Printer', 16) + ' Skriv ut notat' +
			'</button>' +
			'</div>' +
			'</div>';

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
