/**
 * Legacy `analyzeSymptoms` from the prototype's config-skulder.js, kept verbatim as the
 * reference implementation for the equivalence test. Do not "improve" it.
 */
module.exports = function analyzeSymptoms(ans) {
	const redFlags = ans.red_flags || [];
	if (redFlags.length > 0 && !redFlags.includes('none')) {
		return 'medical_referral';
	}

	const functionalLoss = ans.functional_loss || [];
	const traumaDetails = ans.trauma_details || [];
	const location = ans.location || [];
	const painPattern = ans.pain_pattern || [];

	if (ans.onset === 'acute') {
		if (traumaDetails.includes('out_of_socket') || functionalLoss.includes('instability')) return 'instability';
		if (functionalLoss.includes('weakness')) return 'acute_cuff_tear';
		if (traumaDetails.includes('direct_blow') && location.includes('superior')) return 'ac_joint_trauma';
	}

	if (functionalLoss.includes('stiffness')) {
		if (ans.age === 'over55' && ans.onset === 'gradual') return 'osteoarthritis';
		return 'frozen_shoulder';
	}

	if (location.includes('radiating')) return 'cervical_referred';

	if (location.includes('superior') && painPattern.includes('cross_body') && ans.onset === 'gradual') return 'ac_joint_degeneration';

	if (location.includes('lateral') || painPattern.includes('arc') || painPattern.includes('overhead')) return 'saps';

	return 'non_specific';
};
