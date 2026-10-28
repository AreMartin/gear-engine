/**
 * Gear Engine Triage: declarative rule evaluator.
 *
 * Pure functions with no DOM access, so it runs in the browser and in Node tests.
 *
 * Condition shapes:
 *   { field, op, value }  where op is eq | neq | in | notIn | includes | notIncludes | empty | notEmpty
 *   { all: [ ... ] }      every condition matches
 *   { any: [ ... ] }      at least one condition matches
 *   { not: { ... } }      negation
 *
 * @package GearEngine
 */
(function (root, factory) {
	'use strict';
	if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	} else {
		root.GearEngineTriageRules = factory();
	}
})(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function asArray(value) {
		return Array.isArray(value) ? value : [];
	}

	function isEmpty(value) {
		return value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0);
	}

	function matches(condition, answers) {
		if (!condition || typeof condition !== 'object') {
			return false;
		}
		if (Array.isArray(condition.all)) {
			return condition.all.every(function (c) { return matches(c, answers); });
		}
		if (Array.isArray(condition.any)) {
			return condition.any.some(function (c) { return matches(c, answers); });
		}
		if (condition.not) {
			return !matches(condition.not, answers);
		}

		var actual = answers ? answers[condition.field] : undefined;
		var expected = condition.value;

		switch (condition.op) {
			case 'eq':
				return actual === expected;
			case 'neq':
				return actual !== expected;
			case 'in':
				return asArray(expected).indexOf(actual) !== -1;
			case 'notIn':
				return asArray(expected).indexOf(actual) === -1;
			case 'includes':
				return asArray(actual).indexOf(expected) !== -1;
			case 'notIncludes':
				return asArray(actual).indexOf(expected) === -1;
			case 'empty':
				return isEmpty(actual);
			case 'notEmpty':
				return !isEmpty(actual);
			default:
				return false;
		}
	}

	/**
	 * Return the diagnosis id of the first matching rule, or the config default.
	 */
	function resolveDiagnosis(config, answers) {
		var rules = asArray(config && config.rules);
		for (var i = 0; i < rules.length; i++) {
			if (matches(rules[i].when, answers)) {
				return rules[i].diagnosis;
			}
		}
		return config ? config.defaultDiagnosis : null;
	}

	return { matches: matches, resolveDiagnosis: resolveDiagnosis };
});
