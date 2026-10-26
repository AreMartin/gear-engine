/**
 * Gutenberg Native Block: Gear Product Card (gae/product-card)
 */
(function(wp) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useBlockProps = (wp.blockEditor && wp.blockEditor.useBlockProps) ? wp.blockEditor.useBlockProps : function(p) { return p || {}; };
	var InspectorControls = wp.blockEditor.InspectorControls || wp.editor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var Spinner = wp.components.Spinner;
	var Button = wp.components.Button;
	var serverSideRender = wp.serverSideRender;
	var apiFetch = wp.apiFetch;

	var defaultProducts = [
		{ label: '-- Select Exercise Gear --', value: 0 },
		{ label: 'NordicTrack T Series 6.5S Treadmill (Test #1)', value: 1 },
		{ label: 'Concept2 RowErg Romaskin PM5 (Test #2)', value: 2 }
	];

	registerBlockType('gae/product-card', {
		apiVersion: 2,
		title: 'Gear Product Card',
		description: 'Display an affiliate exercise equipment card with AI Insights and a high-contrast green CTA.',
		icon: 'heart',
		category: 'widgets',
		keywords: ['affiliate', 'exercise', 'gear', 'treadmill', 'card', 'adtraction'],
		supports: {
			html: false,
			align: ['wide', 'full']
		},
		attributes: {
			productId: {
				type: 'number',
				default: 0
			},
			showAiSummary: {
				type: 'boolean',
				default: true
			},
			customPitch: {
				type: 'string',
				default: ''
			},
			buttonText: {
				type: 'string',
				default: 'Order now'
			}
		},

		edit: function(props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var productId = attributes.productId;

			// Attach Gutenberg block props so clicking directly in the canvas selects the block.
			var blockProps = useBlockProps({
				className: 'gae-block-editor-wrapper'
			});

			var productState = useState(defaultProducts);
			var products = productState[0];
			var setProducts = productState[1];

			var loadingState = useState(true);
			var isLoading = loadingState[0];
			var setIsLoading = loadingState[1];

			// Load exercise gear items from REST API.
			useEffect(function() {
				apiFetch({ path: '/wp/v2/exercise_gear?per_page=100&_fields=id,title' })
					.then(function(items) {
						var options = [{ label: '-- Select Exercise Gear --', value: 0 }];
						var seenIds = {};

						if (Array.isArray(items) && items.length > 0) {
							items.forEach(function(item) {
								var title = (item.title && item.title.rendered) ? item.title.rendered : ('Product #' + item.id);
								var txt = document.createElement('textarea');
								txt.innerHTML = title;
								options.push({ label: txt.value, value: item.id });
								seenIds[item.id] = true;
							});
						}

						// Include test products if not already in returned list
						if (!seenIds[1]) {
							options.push({ label: 'NordicTrack T Series 6.5S Treadmill (Test #1)', value: 1 });
						}
						if (!seenIds[2]) {
							options.push({ label: 'Concept2 RowErg Romaskin PM5 (Test #2)', value: 2 });
						}

						setProducts(options);
						setIsLoading(false);
					})
					.catch(function() {
						setProducts(defaultProducts);
						setIsLoading(false);
					});
			}, []);

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Product Settings', initialOpen: true },
						isLoading
							? el(Spinner, null)
							: el(SelectControl, {
									label: 'Selected Gear Item',
									value: productId,
									options: products,
									onChange: function(val) {
										setAttributes({ productId: parseInt(val, 10) });
									}
							  }),
						el(ToggleControl, {
							label: 'Show AI Insights Box',
							checked: attributes.showAiSummary,
							onChange: function(val) {
								setAttributes({ showAiSummary: val });
							}
						}),
						attributes.showAiSummary &&
							el(TextareaControl, {
								label: 'Custom AI Pitch Override (Optional)',
								value: attributes.customPitch,
								help: 'Leave empty to automatically display AI Studio generated insights.',
								onChange: function(val) {
									setAttributes({ customPitch: val });
								}
							}),
						el(TextControl, {
							label: 'CTA Button Text',
							value: attributes.buttonText,
							onChange: function(val) {
								setAttributes({ buttonText: val });
							}
						})
					)
				),
				productId > 0
					? el(
							'div',
							{ className: 'gae-editor-preview-wrap' },
							serverSideRender
								? el(serverSideRender, {
										block: 'gae/product-card',
										attributes: attributes
								  })
								: el('p', null, 'Product #' + productId + ' selected.')
					  )
					: el(
							'div',
							{ className: 'gae-block-placeholder' },
							el('span', { className: 'dashicons dashicons-heart', style: { fontSize: '38px', width: '38px', height: '38px', color: '#16a34a' } }),
							el('h3', { style: { margin: '8px 0 6px 0', fontSize: '18px', fontWeight: '600' } }, 'Gear Affiliate Product Card'),
							el('p', { style: { margin: '0 0 16px 0', color: '#64748b', fontSize: '13px' } }, 'Select an exercise gear item below or in the sidebar to render the product card:'),
							isLoading
								? el(Spinner, null)
								: el(
										'div',
										{ style: { maxWidth: '360px', margin: '0 auto', textAlign: 'left' } },
										el(SelectControl, {
											label: 'Choose Product',
											value: productId,
											options: products,
											onChange: function(val) {
												setAttributes({ productId: parseInt(val, 10) });
											}
										})
								  )
					  )
			);
		},

		save: function() {
			// Dynamic block rendered via PHP server-side callback.
			return null;
		}
	});
})(window.wp);
