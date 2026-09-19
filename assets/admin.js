(function ($) {
	'use strict';

	var $field = $('.kitmage-team-explainer-field');
	var selectors = [
		'#_wc_memberships_for_teams_has_team_membership',
		'#_wc_memberships_for_teams_team_product',
		'input[name="_wc_memberships_for_teams_has_team_membership"]',
		'input[name="_wc_memberships_for_teams_team_product"]'
	];

	if (!$field.length) {
		return;
	}

	function teamControl() {
		var $control = $(selectors.join(',')).first();

		if (!$control.length) {
			$('label').each(function () {
				if (/team membership/i.test($(this).text())) {
					$control = $('#' + $(this).attr('for'));
					return !$control.length;
				}
			});
		}

		return $control;
	}

	function toggleField() {
		var $control = teamControl();
		$field.toggle(!$control.length || $control.is(':checked'));
	}

	$(document).on('change', selectors.join(','), toggleField);
	$(document).on('woocommerce-product-type-change', toggleField);
	toggleField();
})(jQuery);
