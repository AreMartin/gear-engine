/**
 * Gear Affiliate Engine Admin Script
 */
(function($) {
	'use strict';

	$(function() {
		// 1. Manual Sync Handler
		var $syncBtn = $('#gae-sync-now-btn');
		var $syncSpinner = $('#gae-sync-spinner');
		var $syncStatus = $('#gae-sync-status-msg');

		if ($syncBtn.length) {
			$syncBtn.on('click', function(e) {
				e.preventDefault();

				if ($syncBtn.prop('disabled')) {
					return;
				}

				$syncBtn.prop('disabled', true);
				$syncSpinner.addClass('is-active');
				$syncStatus.css('color', '#475569').text(gaeAdminData.strings.syncing);

				$.ajax({
					url: gaeAdminData.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					timeout: 300000,
					data: {
						action: 'gae_trigger_manual_sync',
						nonce: gaeAdminData.syncNonce
					}
				})
				.done(function(response) {
					if (response.success) {
						$syncStatus.css('color', '#16a34a').text(response.data.message || gaeAdminData.strings.syncSuccess);
						setTimeout(function() {
							window.location.reload();
						}, 2500);
					} else {
						var err = (response.data && response.data.message) ? response.data.message : gaeAdminData.strings.syncFailed;
						$syncStatus.css('color', '#ef4444').text(err);
					}
				})
				.fail(function(xhr, status, error) {
					var errText = gaeAdminData.strings.syncFailed;
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errText = xhr.responseJSON.data.message;
					} else if (xhr.responseText && xhr.responseText.length < 250 && xhr.responseText.indexOf('<') === -1) {
						errText = xhr.responseText;
					} else if (status === 'timeout') {
						errText = 'Request timed out due to feed size. Please check your product list as items may still be processing.';
					} else if (error) {
						errText += ' (' + error + ')';
					}
					$syncStatus.css('color', '#ef4444').text(errText);
				})
				.always(function() {
					$syncBtn.prop('disabled', false);
					$syncSpinner.removeClass('is-active');
				});
			});
		}

		// 2. Refresh Gemini Models from Google AI Studio
		var $refreshModelsBtn = $('#gae-refresh-models-btn');
		var $modelsSpinner = $('#gae-models-spinner');
		var $modelsStatus = $('#gae-models-status');
		var $modelSelect = $('#gae_ai_model_select');
		var $modelInput = $('#gae_ai_model');

		if ($refreshModelsBtn.length) {
			$refreshModelsBtn.on('click', function(e) {
				e.preventDefault();

				if ($refreshModelsBtn.prop('disabled')) {
					return;
				}

				$refreshModelsBtn.prop('disabled', true);
				$modelsSpinner.addClass('is-active');
				$modelsStatus.css('color', '#475569').text(gaeAdminData.strings.fetchingModels);

				$.ajax({
					url: gaeAdminData.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'gae_refresh_gemini_models',
						nonce: gaeAdminData.refreshModelsNonce
					}
				})
				.done(function(response) {
					if (response.success && response.data.models) {
						var currentVal = $modelInput.val();
						var models = response.data.models;

						$modelSelect.empty();
						var matched = false;

						$.each(models, function(key, label) {
							var isSelected = (key === currentVal);
							if (isSelected) {
								matched = true;
							}
							$modelSelect.append(
								$('<option>', {
									value: key,
									text: label,
									selected: isSelected
								})
							);
						});

						$modelSelect.append(
							$('<option>', {
								value: 'custom',
								text: 'Custom Model Name...',
								selected: (!matched && currentVal !== '')
							})
						);

						$modelsStatus.css('color', '#16a34a').text(response.data.message || gaeAdminData.strings.fetchSuccess);
					} else {
						var errMsg = (response.data && response.data.message) ? response.data.message : gaeAdminData.strings.fetchFailed;
						$modelsStatus.css('color', '#ef4444').text(errMsg);
					}
				})
				.fail(function(xhr, status, error) {
					$modelsStatus.css('color', '#ef4444').text(gaeAdminData.strings.fetchFailed + ' (' + error + ')');
				})
				.always(function() {
					$refreshModelsBtn.prop('disabled', false);
					$modelsSpinner.removeClass('is-active');
					setTimeout(function() {
						$modelsStatus.fadeOut(400, function() {
							$(this).text('').show();
						});
					}, 4000);
				});
			});
		}
	});
})(jQuery);
