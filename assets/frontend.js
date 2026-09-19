(function ($) {
	'use strict';

	var config = window.kitMageTeamExplainer || {};
	var wrapperSelector = '#team-fields-wrapper-' + config.productId;

	function getNotice($form) {
		var $wrapper = $form.find(wrapperSelector);

		if (!$wrapper.length) {
			$wrapper = $(wrapperSelector);
		}

		if (!$wrapper.length) {
			$wrapper = $form.find('.team-fields').first();
		}

		if (!$wrapper.length) {
			$wrapper = $form;
		}

		var $notice = $wrapper.find('.team-seat-context');

		if (!$notice.length) {
			$notice = $('<div class="team-seat-context" aria-live="polite"><p></p></div>');
			var $teamNameField = $wrapper.find('#team_name_field');

			if ($teamNameField.length) {
				$teamNameField.after($notice);
			} else if ($wrapper.hasClass('team-fields')) {
				$wrapper.prepend($notice);
			} else if ($wrapper.find('.team-fields').length) {
				$wrapper.find('.team-fields').first().prepend($notice);
			} else {
				$wrapper.prepend($notice);
			}
		}

		return $notice;
	}

	function replacePlaceholders(message, values) {
		return message.replace(
			/%(max_seats|product_name|variation_name)(?:\|([^%]*))?%/g,
			function (match, key, fallback) {
				var value = values[key];

				if (value === null || typeof value === 'undefined' || value === '') {
					return typeof fallback === 'string' ? fallback : '';
				}

				return String(value);
			}
		);
	}

	function render($form, maxSeats, variationName) {
		var $notice = getNotice($form);
		var max = parseInt(maxSeats, 10);

		if (!$notice.length) {
			return;
		}

		var message = replacePlaceholders(config.message, {
			max_seats: isNaN(max) || max < 1 ? null : max,
			product_name: config.productName || null,
			variation_name: variationName || null
		});

		if (!message.trim()) {
			$notice.hide().find('p').empty();
			return;
		}

		$notice.find('p').html(message);
		$notice.show();
	}

	$(document).on('found_variation', 'form.variations_form', function (event, variation) {
		render($(this), variation.kitmage_team_max_member_count, variation.kitmage_team_variation_name);
	});

	$(document).on('reset_data hide_variation', 'form.variations_form', function () {
		$(this).add($(wrapperSelector)).find('.team-seat-context').hide().find('p').empty();
	});

	$(function () {
		if (!config.isVariable) {
			render($('form.cart'), config.maxSeats, config.variationName);
		}
	});
})(jQuery);
