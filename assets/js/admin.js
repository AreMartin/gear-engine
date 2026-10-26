/**
 * Gear Affiliate Engine Admin Script
 */
(function($) {
	'use strict';

	$(function() {
		var $btn = $('#gae-sync-now-btn');
		var $spinner = $('#gae-sync-spinner');
		var $status = $('#gae-sync-status-msg');

		if (!$btn.length) {
			return;
		}

		$btn.on('click', function(e) {
			e.preventDefault();

			if ($btn.prop('disabled')) {
				return;
			}

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$status.css('color', '#475569').text(gaeAdminData.strings.syncing);

			$.ajax({
				url: gaeAdminData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'gae_trigger_manual_sync',
					nonce: gaeAdminData.syncNonce
				}
			})
			.done(function(response) {
				if (response.success) {
					$status.css('color', '#16a34a').text(response.data.message || gaeAdminData.strings.syncSuccess);
					setTimeout(function() {
						window.location.reload();
					}, 2000);
				} else {
					var err = (response.data && response.data.message) ? response.data.message : gaeAdminData.strings.syncFailed;
					$status.css('color', '#ef4444').text(err);
				}
			})
			.fail(function(xhr, status, error) {
				$status.css('color', '#ef4444').text(gaeAdminData.strings.syncFailed + ' (' + error + ')');
			})
			.always(function() {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			});
		});
	});
})(jQuery);
