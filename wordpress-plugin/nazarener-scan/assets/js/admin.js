/* global NazarenerAdmin */
(function ($) {
	'use strict';

	$(function () {
		// Auto-dismiss success notices after 4 seconds
		$('.notice-success.is-dismissible').delay(4000).fadeOut(400);

		// Confirm bulk delete
		$('form').on('submit', function () {
			const bulk = $('select[name="bulk_action_top"]').val() || $('select[name="bulk_action_bottom"]').val();
			if (bulk === 'delete') {
				const checked = $('input[name="product_ids[]"]:checked').length;
				if (checked > 0 && !confirm(checked + ' Produkt(e) wirklich löschen?')) {
					return false;
				}
			}
		});

		// Highlight pending submissions row
		$('tr').each(function () {
			if ($(this).find('.ns-badge--yellow').length) {
				$(this).addClass('ns-row--pending');
			}
		});

		// Copy shortcode to clipboard
		$('.ns-shortcode').on('click', function () {
			navigator.clipboard?.writeText($(this).text()).then(function () {
				const $el = $(this);
				$el.addClass('ns-copied');
				setTimeout(function () { $el.removeClass('ns-copied'); }, 2000);
			}.bind(this));
		});
	});

}(jQuery));
