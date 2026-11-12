/* global ajaxurl, trustbioWidgetI18n */
/* TrustBio — Review Dashboard Widget JS */

(function ($) {
	'use strict';

	$(document).on('click', '.tb-widget-mark', function () {
		var $btn   = $(this);
		var postId = $btn.data('post-id');
		var nonce  = $btn.data('nonce');
		var orig   = $btn.text();

		$btn.prop('disabled', true).text(trustbioWidgetI18n.saving);

		$.post(ajaxurl, {
			action  : 'trustbio_mark_reviewed_today',
			post_id : postId,
			nonce   : nonce,
		}).done(function (r) {
			if (r.success) {
				var $row = $('#tb-review-row-' + postId);
				// Update dot to green.
				$row.find('.tb-widget-dot')
					.removeClass('tb-widget-dot--amber tb-widget-dot--red tb-widget-dot--never')
					.addClass('tb-widget-dot--green');
				// Update status label.
				$row.find('.tb-widget-status')
					.removeClass('tb-widget-status--amber tb-widget-status--red tb-widget-status--never')
					.addClass('tb-widget-status--green')
					.text(trustbioWidgetI18n.upToDate);
				// Update age label.
				$row.find('.tb-widget-age').text(r.data.date);
				// Update button.
				$btn.addClass('tb-widget-mark--done').text(trustbioWidgetI18n.done).prop('disabled', true);
			} else {
				$btn.prop('disabled', false).text(orig);
			}
		}).fail(function () {
			$btn.prop('disabled', false).text(orig);
		});
	});

})(jQuery);
