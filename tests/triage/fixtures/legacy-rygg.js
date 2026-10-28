/**
 * Legacy `analyzeSymptoms` from the prototype's config-rygg.js, kept verbatim as the
 * reference implementation for the equivalence test. Do not "improve" it.
 */
module.exports = function analyzeSymptoms(answers) {
	const { age, onset, radiation, pain_behavior, redFlags } = answers;

	if (redFlags.length > 0 && !redFlags.includes('ingen')) {
		if (redFlags.includes('cauda')) return 'cauda_equina';
		return 'red_flag_medical';
	}

	if (onset === 'akutt_traume') return 'trauma_fracture';

	if (radiation === 'forbi_kneet') {
		return 'sciatica';
	}

	if (age === 'over55' && pain_behavior.includes('staa_gaa') && radiation !== 'ingen') {
		return 'spinal_stenosis';
	}

	if (age === 'under20' || age === '20-55') {
		if (onset === 'gradvis' && pain_behavior.includes('morgen')) return 'inflammatory_back_pain';
	}

	if (onset === 'akutt_loft') return 'acute_lumbago';

	return 'unspecific_lbp';
};
