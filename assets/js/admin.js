/**
 * Gear Affiliate Engine Admin Script
 */
(function($) {
	'use strict';

	$(function() {
		// Category Presets
		var defaultCatPresets = [
			'Tredemølle',
			'Romaskin',
			'Spinningsykkel',
			'Treningsbenk',
			'Vektstang',
			'Vektvest',
			'Crosstrainer',
			'Styrkepakke',
			'Manualer',
			'Kettlebell'
		];

		// Parse initial categories
		var initialCats = [];
		if (gaeAdminData && gaeAdminData.allowedCategories) {
			initialCats = gaeAdminData.allowedCategories.split(/[\r\n,]+/).map(function(s) {
				return $.trim(s);
			}).filter(function(s) {
				return s.length > 0;
			});
		}

		// Merge presets so user can easily toggle inactive ones on
		var allCategoryNames = [];
		$.each(initialCats, function(i, cat) {
			if (allCategoryNames.indexOf(cat) === -1) {
				allCategoryNames.push(cat);
			}
		});
		$.each(defaultCatPresets, function(i, preset) {
			var exists = false;
			$.each(allCategoryNames, function(j, existing) {
				if (existing.toLowerCase() === preset.toLowerCase()) {
					exists = true;
					return false;
				}
			});
			if (!exists) {
				allCategoryNames.push(preset);
			}
		});

		var $pillsWrap = $('#gae-category-pills');
		var $catTextarea = $('#gae_allowed_categories');

		function renderCategoryPills() {
			if (!$pillsWrap.length) return;
			$pillsWrap.empty();

			$.each(allCategoryNames, function(i, cat) {
				var isActive = false;
				$.each(initialCats, function(j, act) {
					if (act.toLowerCase() === cat.toLowerCase()) {
						isActive = true;
						return false;
					}
				});

				var $pill = $('<span class="gae-pill' + (isActive ? ' is-active' : '') + '"></span>');
				$pill.append('<span class="gae-pill-check">✓</span>');
				$pill.append('<span class="gae-pill-label">' + $('<div>').text(cat).html() + '</span>');

				// Allow custom categories to be deleted
				var isDefault = false;
				$.each(defaultCatPresets, function(k, def) {
					if (def.toLowerCase() === cat.toLowerCase()) {
						isDefault = true;
						return false;
					}
				});

				if (!isDefault) {
					var $remove = $('<span class="gae-pill-remove" title="Remove category">×</span>');
					$remove.on('click', function(e) {
						e.stopPropagation();
						allCategoryNames = allCategoryNames.filter(function(c) {
							return c.toLowerCase() !== cat.toLowerCase();
						});
						initialCats = initialCats.filter(function(c) {
							return c.toLowerCase() !== cat.toLowerCase();
						});
						renderCategoryPills();
						syncCatTextarea();
					});
					$pill.append($remove);
				}

				$pill.on('click', function() {
					$(this).toggleClass('is-active');
					syncCatTextarea();
				});

				$pillsWrap.append($pill);
			});
		}

		function getActiveCategories() {
			var active = [];
			$pillsWrap.find('.gae-pill.is-active').each(function() {
				active.push($.trim($(this).find('.gae-pill-label').text()));
			});
			return active;
		}

		function syncCatTextarea() {
			var active = getActiveCategories();
			if ($catTextarea.length) {
				$catTextarea.val(active.join(', '));
			}
		}

		renderCategoryPills();

		// Add Category button
		$('#gae-add-cat-btn').on('click', function(e) {
			e.preventDefault();
			var val = $.trim($('#gae-new-cat-input').val());
			if (!val) return;

			var exists = false;
			$.each(allCategoryNames, function(i, c) {
				if (c.toLowerCase() === val.toLowerCase()) {
					exists = true;
					return false;
				}
			});

			if (!exists) {
				allCategoryNames.push(val);
			}
			if (initialCats.indexOf(val) === -1) {
				initialCats.push(val);
			}
			$('#gae-new-cat-input').val('');
			renderCategoryPills();
			syncCatTextarea();
		});

		$('#gae-new-cat-input').on('keypress', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				$('#gae-add-cat-btn').trigger('click');
			}
		});

		// Select All / Deselect All
		$('#gae-select-all-cats').on('click', function(e) {
			e.preventDefault();
			$pillsWrap.find('.gae-pill').addClass('is-active');
			syncCatTextarea();
		});

		$('#gae-clear-all-cats').on('click', function(e) {
			e.preventDefault();
			$pillsWrap.find('.gae-pill').removeClass('is-active');
			syncCatTextarea();
		});

		// Blocked Keywords Chips
		var blockedKeywords = [];
		if (gaeAdminData && gaeAdminData.excludedKeywords) {
			blockedKeywords = gaeAdminData.excludedKeywords.split(/[\r\n,]+/).map(function(s) {
				return $.trim(s);
			}).filter(function(s) {
				return s.length > 0;
			});
		}

		var $chipsWrap = $('#gae-keyword-chips');
		var $kwTextarea = $('#gae_excluded_keywords');

		function renderKeywordChips() {
			if (!$chipsWrap.length) return;
			$chipsWrap.empty();

			$.each(blockedKeywords, function(i, kw) {
				var $chip = $('<span class="gae-chip"></span>');
				$chip.append('<span class="gae-chip-label">' + $('<div>').text(kw).html() + '</span>');
				var $remove = $('<span class="gae-chip-remove" title="Unblock keyword">×</span>');
				$remove.on('click', function() {
					blockedKeywords.splice(i, 1);
					renderKeywordChips();
					syncKwTextarea();
				});
				$chip.append($remove);
				$chipsWrap.append($chip);
			});
		}

		function syncKwTextarea() {
			if ($kwTextarea.length) {
				$kwTextarea.val(blockedKeywords.join(', '));
			}
		}

		renderKeywordChips();

		// Add Blocked Keyword button
		$('#gae-add-kw-btn').on('click', function(e) {
			e.preventDefault();
			var val = $.trim($('#gae-new-kw-input').val());
			if (!val) return;

			var exists = false;
			$.each(blockedKeywords, function(i, k) {
				if (k.toLowerCase() === val.toLowerCase()) {
					exists = true;
					return false;
				}
			});

			if (!exists) {
				blockedKeywords.push(val);
			}
			$('#gae-new-kw-input').val('');
			renderKeywordChips();
			syncKwTextarea();
		});

		$('#gae-new-kw-input').on('keypress', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				$('#gae-add-kw-btn').trigger('click');
			}
		});

		// Limit Selector Buttons
		$('.gae-limit-btn').on('click', function(e) {
			e.preventDefault();
			var lim = $(this).data('limit');
			$('#gae-quick-limit-input').val(lim);
			$('.gae-limit-btn').removeClass('is-active');
			$(this).addClass('is-active');

			var $formMaxInput = $('#gae_max_items_per_sync');
			if ($formMaxInput.length) {
				$formMaxInput.val(lim);
			}
		});

		$('#gae-quick-limit-input').on('input change', function() {
			var val = parseInt($(this).val(), 10) || 0;
			$('.gae-limit-btn').removeClass('is-active');
			$('.gae-limit-btn[data-limit="' + val + '"]').addClass('is-active');

			var $formMaxInput = $('#gae_max_items_per_sync');
			if ($formMaxInput.length) {
				$formMaxInput.val(val);
			}
		});

		// Synchronize Quick Filters with Bottom Settings Form
		$('#gae-quick-in-stock').on('change', function() {
			var checked = $(this).is(':checked');
			$('#gae_only_in_stock').val(checked ? '1' : '0').prop('checked', checked);
		});

		$('#gae-quick-min-price').on('input change', function() {
			$('#gae_min_price').val($(this).val());
		});

		$('#gae-quick-max-price').on('input change', function() {
			$('#gae_max_price').val($(this).val());
		});

		$('#gae-quick-min-discount').on('input change', function() {
			$('#gae_min_discount_pct').val($(this).val());
		});

		// ==========================================
		// Partner Feeds Card Interactive Management
		// ==========================================
		function updateFeedCounters() {
			var totalSlots = 5;
			var visibleSlots = $('.gae-feed-row:not(.gae-slot-hidden)').length;
			var activeSlots = 0;

			$('.gae-feed-row').each(function() {
				var isChecked = $(this).find('.gae-feed-enabled-toggle').is(':checked');
				var hasUrl = $.trim($(this).find('.gae-feed-url-input').val()).length > 0;
				if (isChecked && hasUrl) {
					activeSlots++;
				}
			});

			$('#gae-feed-active-badge').text(activeSlots + ' / ' + totalSlots + ' Active');

			var remaining = totalSlots - visibleSlots;
			var $addBtn = $('#gae-add-feed-btn');
			if (remaining > 0) {
				$addBtn.prop('disabled', false).find('.gae-add-feed-text').text('Add Partner Feed (' + remaining + ' slots remaining)');
			} else {
				$addBtn.prop('disabled', true).find('.gae-add-feed-text').text('All 5 Slots In Use');
			}
		}

		// Toggle switch active/paused
		$(document).on('change', '.gae-feed-enabled-toggle', function() {
			var isChecked = $(this).is(':checked');
			var $row = $(this).closest('.gae-feed-row');
			var $pill = $row.find('.gae-status-pill');

			if (isChecked) {
				$row.removeClass('is-paused');
				$pill.removeClass('is-paused').addClass('is-active').text('Active');
			} else {
				$row.addClass('is-paused');
				$pill.removeClass('is-active').addClass('is-paused').text('Paused');
			}
			updateFeedCounters();
		});

		// Auto-detect network when typing/pasting URL
		$(document).on('input paste change', '.gae-feed-url-input', function() {
			var val = $(this).val();
			var $row = $(this).closest('.gae-feed-row');
			var $select = $row.find('.gae-network-select');

			if (val.indexOf('adtraction.com') !== -1) {
				$select.val('Adtraction');
			} else if (val.indexOf('tradetracker.') !== -1) {
				$select.val('TradeTracker');
			}
			updateFeedCounters();
		});

		// Add Feed Button
		$('#gae-add-feed-btn').on('click', function(e) {
			e.preventDefault();
			var $hiddenRow = $('.gae-feed-row.gae-slot-hidden').first();
			if ($hiddenRow.length) {
				$hiddenRow.removeClass('gae-slot-hidden');
				$hiddenRow.find('.gae-feed-name-input').focus();
				updateFeedCounters();
			}
		});

		// Remove Feed Button
		$(document).on('click', '.gae-remove-feed-btn', function(e) {
			e.preventDefault();
			var $row = $(this).closest('.gae-feed-row');
			$row.find('.gae-feed-name-input').val('');
			$row.find('.gae-feed-url-input').val('');
			$row.find('.gae-network-select').val('Adtraction');
			$row.find('.gae-feed-test-result').empty();
			$row.find('.gae-feed-enabled-toggle').prop('checked', false).trigger('change');

			var visibleRows = $('.gae-feed-row:not(.gae-slot-hidden)').length;
			if (visibleRows > 1) {
				$row.addClass('gae-slot-hidden');
				$('#gae-feeds-tbody').append($row);
			}
			updateFeedCounters();
		});

		// Test Feed Connection Probe Button
		$(document).on('click', '.gae-test-feed-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $row = $btn.closest('.gae-feed-row');
			var url = $.trim($row.find('.gae-feed-url-input').val());
			var $result = $row.find('.gae-feed-test-result');

			if (!url) {
				$result.html('<span class="gae-badge-error">⚠ Enter URL first</span>');
				return;
			}

			$btn.prop('disabled', true);
			$result.html('<span class="spinner is-active" style="float:none; margin:0 4px 0 0; vertical-align:middle;"></span> <span style="color:#64748b;">Testing...</span>');

			$.ajax({
				url: gaeAdminData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'gae_test_feed_url',
					nonce: gaeAdminData.syncNonce,
					url: url
				}
			})
			.done(function(response) {
				if (response.success) {
					$result.html('<span class="gae-badge-success">✓ ' + $('<div>').text(response.data.message).html() + '</span>');
				} else {
					var err = (response.data && response.data.message) ? response.data.message : 'Connection failed';
					$result.html('<span class="gae-badge-error">⚠ ' + $('<div>').text(err).html() + '</span>');
				}
			})
			.fail(function(xhr, status, error) {
				$result.html('<span class="gae-badge-error">⚠ Request failed (' + error + ')</span>');
			})
			.always(function() {
				$btn.prop('disabled', false);
			});
		});

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

				var postData = {
					action: 'gae_trigger_manual_sync',
					nonce: gaeAdminData.syncNonce,
					max_items: $('#gae-quick-limit-input').val(),
					target_feed: $('input[name="gae_quick_feed"]:checked').val() || 'all',
					only_in_stock: $('#gae-quick-in-stock').is(':checked') ? 1 : 0,
					min_price: $('#gae-quick-min-price').val(),
					max_price: $('#gae-quick-max-price').val(),
					min_discount_pct: $('#gae-quick-min-discount').val(),
					categories: getActiveCategories().join(', '),
					excluded_keywords: blockedKeywords.join(', ')
				};

				$.ajax({
					url: gaeAdminData.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					timeout: 300000,
					data: postData
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
					if (!xhr.responseJSON && xhr.responseText) {
						var jsonMatch = xhr.responseText.match(/\{.*"success".*\}/s);
						if (jsonMatch) {
							try {
								var parsed = JSON.parse(jsonMatch[0]);
								if (parsed && parsed.success) {
									$syncStatus.css('color', '#16a34a').text(parsed.data.message || gaeAdminData.strings.syncSuccess);
									setTimeout(function() {
										window.location.reload();
									}, 2500);
									return;
								} else if (parsed && parsed.data && parsed.data.message) {
									$syncStatus.css('color', '#ef4444').text(parsed.data.message);
									return;
								}
							} catch (e) {}
						}
					}

					var errText = gaeAdminData.strings.syncFailed;
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errText = xhr.responseJSON.data.message;
					} else if (xhr.status === 0 || status === 'timeout') {
						errText = 'Sync completed or processing in background. Refreshing dashboard...';
						$syncStatus.css('color', '#16a34a').text(errText);
						setTimeout(function() {
							window.location.reload();
						}, 2500);
						return;
					} else if (xhr.responseText) {
						var stripped = xhr.responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
						if (stripped.length > 0 && stripped.length < 300) {
							errText = stripped;
						} else if (error) {
							errText += ' (' + error + ' - HTTP ' + xhr.status + ')';
						}
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

		// 2. Save Filters as Defaults Handler
		var $saveFiltersBtn = $('#gae-save-filters-btn');
		if ($saveFiltersBtn.length) {
			$saveFiltersBtn.on('click', function(e) {
				e.preventDefault();
				if ($saveFiltersBtn.prop('disabled')) return;

				$saveFiltersBtn.prop('disabled', true);
				$syncSpinner.addClass('is-active');
				$syncStatus.css('color', '#475569').text(gaeAdminData.strings.savingFilters);

				var postData = {
					action: 'gae_save_dashboard_filters',
					nonce: gaeAdminData.syncNonce,
					max_items: $('#gae-quick-limit-input').val(),
					only_in_stock: $('#gae-quick-in-stock').is(':checked') ? 1 : 0,
					min_price: $('#gae-quick-min-price').val(),
					max_price: $('#gae-quick-max-price').val(),
					min_discount_pct: $('#gae-quick-min-discount').val(),
					categories: getActiveCategories().join(', '),
					excluded_keywords: blockedKeywords.join(', ')
				};

				$.ajax({
					url: gaeAdminData.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: postData
				})
				.done(function(response) {
					if (response.success) {
						$syncStatus.css('color', '#16a34a').text(response.data.message || gaeAdminData.strings.filtersSaved);
					} else {
						$syncStatus.css('color', '#ef4444').text(response.data.message || 'Error saving filters.');
					}
				})
				.fail(function(xhr, status, error) {
					$syncStatus.css('color', '#ef4444').text('Save failed: ' + error);
				})
				.always(function() {
					$saveFiltersBtn.prop('disabled', false);
					$syncSpinner.removeClass('is-active');
					setTimeout(function() {
						$syncStatus.fadeOut(400, function() {
							$(this).text('').show();
						});
					}, 3500);
				});
			});
		}

		// 3. Clear All Synced Products Handler
		var $clearBtn = $('#gae-clear-products-btn');
		if ($clearBtn.length) {
			$clearBtn.on('click', function(e) {
				e.preventDefault();
				if (!confirm(gaeAdminData.strings.confirmClear)) {
					return;
				}

				$clearBtn.prop('disabled', true);
				$syncSpinner.addClass('is-active');
				$syncStatus.css('color', '#ef4444').text(gaeAdminData.strings.clearing);

				$.ajax({
					url: gaeAdminData.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'gae_clear_all_products',
						nonce: gaeAdminData.syncNonce
					}
				})
				.done(function(response) {
					if (response.success) {
						$syncStatus.css('color', '#16a34a').text(response.data.message || gaeAdminData.strings.clearSuccess);
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						$syncStatus.css('color', '#ef4444').text(response.data.message || gaeAdminData.strings.clearFailed);
					}
				})
				.fail(function(xhr, status, error) {
					$syncStatus.css('color', '#ef4444').text('Error: ' + error);
				})
				.always(function() {
					$clearBtn.prop('disabled', false);
					$syncSpinner.removeClass('is-active');
				});
			});
		}

		// 4. Refresh Gemini Models from Google AI Studio
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
