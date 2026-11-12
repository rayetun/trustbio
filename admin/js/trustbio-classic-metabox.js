/* global trustbioMetabox */
/* TrustBio Classic Editor Meta Box — post sidebar JS */

(function ($) {
	'use strict';

	$(document).ready(function () {

		var cfg = window.trustbioMetabox || {};
		var AJAX_URL  = cfg.ajaxUrl  || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
		var NONCE     = cfg.nonce    || '';
		var CATS      = cfg.categories || [];
		var GRAVATAR  = 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y&s=28';

		// ── Serialize state to hidden JSON field ─────────────
		function syncJson() {
			var result = [];
			$('#tb-assigned-list .tb-metabox__author').each(function (i) {
				var authorId = parseInt($(this).data('author-id'), 10);
				var catVal   = $(this).find('.tb-metabox__cat-select').val();
				var catId    = catVal ? parseInt(catVal, 10) : null;
				result.push({ author_id: authorId, category_id: catId, sort_order: i });
			});
			$('#tb-authors-json').val(JSON.stringify(result));
		}

		// ── Build category <select> options ──────────────────
		function buildCatOptions(selectedId) {
			var html = '<option value="">— Category —</option>';
			CATS.forEach(function (c) {
				var sel = String(c.id) === String(selectedId) ? ' selected' : '';
				html += '<option value="' + c.id + '"' + sel + '>' + $('<span>').text(c.singular).html() + '</option>';
			});
			return html;
		}

		// ── Build author card HTML ────────────────────────────
		function buildCard(id, name, avatar, catId) {
			var safeAvatar = avatar || GRAVATAR;
			return $('<div class="tb-metabox__author" data-author-id="' + id + '">'
				+ '<div class="tb-metabox__author-drag" title="Drag to reorder">⠿</div>'
				+ '<img class="tb-metabox__author-avatar" src="' + $('<span>').text(safeAvatar).html() + '" alt="" width="28" height="28">'
				+ '<div class="tb-metabox__author-info">'
				+   '<strong>' + $('<span>').text(name).html() + '</strong>'
				+   '<select class="tb-metabox__cat-select" data-author-id="' + id + '">'
				+   buildCatOptions(catId)
				+   '</select>'
				+ '</div>'
				+ '<button type="button" class="tb-metabox__remove" data-author-id="' + id + '" aria-label="Remove author">✕</button>'
				+ '</div>');
		}

		// ── Author search ─────────────────────────────────────
		var searchTimer;
		$('#tb-metabox-search').on('input', function () {
			clearTimeout(searchTimer);
			var q = $(this).val().trim();
			if (q.length < 2) { $('#tb-metabox-results').hide(); return; }
			searchTimer = setTimeout(function () {
				$.post(AJAX_URL, {
					action : 'trustbio_search_authors',
					nonce  : NONCE,
					q      : q,
				}).done(function (r) {
					if (!r.success || !r.data.authors.length) {
						$('#tb-metabox-results').html('<div class="tb-metabox__result-empty">No authors found.</div>').show();
						return;
					}
					var html = r.data.authors.map(function (a) {
						return '<div class="tb-metabox__result"'
							+ ' data-id="'    + a.id + '"'
							+ ' data-name="'  + $('<span>').text(a.display_name).html() + '"'
							+ ' data-avatar="' + $('<span>').text(a.avatar_url || '').html() + '"'
							+ ' data-cat-id="' + (a.category_id || '') + '">'
							+ '<img src="' + $('<span>').text(a.avatar_url || GRAVATAR).html() + '" width="24" height="24" alt="" style="border-radius:50%;">'
							+ '<div>'
							+   '<div class="tb-metabox__result-name">' + $('<span>').text(a.display_name).html() + '</div>'
							+   '<div class="tb-metabox__result-cat">'  + $('<span>').text(a.category_name || a.author_type || '').html() + '</div>'
							+ '</div></div>';
					}).join('');
					$('#tb-metabox-results').html(html).show();
				});
			}, 300);
		});

		// Hide dropdown on outside click.
		$(document).on('click', function (e) {
			if (!$(e.target).closest('.tb-metabox__search-wrap').length) {
				$('#tb-metabox-results').hide();
			}
		});

		// ── Add author on result click ────────────────────────
		$(document).on('click', '.tb-metabox__result', function () {
			var id     = $(this).data('id');
			var name   = $(this).data('name');
			var avatar = $(this).data('avatar');
			var catId  = $(this).data('cat-id') || '';

			if ($('#tb-assigned-list [data-author-id="' + id + '"]').length) {
				$('#tb-metabox-results').hide();
				$('#tb-metabox-search').val('');
				return;
			}

			$('.tb-metabox__empty').remove();
			buildCard(id, name, avatar, catId).appendTo('#tb-assigned-list');
			syncJson();

			$('#tb-metabox-results').hide();
			$('#tb-metabox-search').val('');
		});

		// ── Remove author ─────────────────────────────────────
		$(document).on('click', '.tb-metabox__remove', function () {
			$(this).closest('.tb-metabox__author').remove();
			if ($('#tb-assigned-list .tb-metabox__author').length === 0) {
				$('#tb-assigned-list').html('<p class="tb-metabox__empty">No authors assigned. Search below to add.</p>');
			}
			syncJson();
		});

		// ── Category change ───────────────────────────────────
		$(document).on('change', '.tb-metabox__cat-select', function () {
			syncJson();
		});

		// ── Drag to reorder ───────────────────────────────────
		if ($.fn.sortable) {
			$('#tb-assigned-list').sortable({
				handle   : '.tb-metabox__author-drag',
				axis     : 'y',
				tolerance: 'pointer',
				update   : syncJson,
			});
		}

	}); // end ready

})(jQuery);
