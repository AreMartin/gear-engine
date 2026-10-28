/**
 * Gutenberg block editor script: Triage.
 *
 * @package GearEngine
 */
(function (wp, data) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element) {
		return;
	}

	var el = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var Placeholder = wp.components.Placeholder;
	var regions = (data && data.regions) || ['rygg'];

	wp.blocks.registerBlockType('gear-engine/triage', {
		title: 'Triage Quiz',
		icon: 'clipboard',
		category: 'widgets',
		description: 'Rule-based triage quiz that ends in a conclusion and a printable clinical note.',
		attributes: {
			region: { type: 'string', default: 'rygg' }
		},
		edit: function (props) {
			var region = props.attributes.region;
			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Triage' },
						el(SelectControl, {
							label: 'Region',
							value: region,
							options: regions.map(function (r) { return { label: r, value: r }; }),
							onChange: function (value) { props.setAttributes({ region: value }); }
						})
					)
				),
				el(Placeholder, {
					icon: 'clipboard',
					label: 'Triage Quiz: ' + region,
					instructions: 'The quiz renders on the published page (shortcode: [gear_engine_triage region="' + region + '"]).'
				})
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp, window.gearEngineTriageBlock);
