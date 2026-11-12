/* global trustbioQE */
/* TrustBio Quick Edit & Bulk Edit — post list screen JS */

(function ($) {
	'use strict';

	var cfg      = window.trustbioQE || {};
	var AJAX_URL = cfg.ajaxUrl   || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
	var QE_NONCE = cfg.qeNonce   || '';
	var BE_NONCE = cfg.beNonce   || '';
	var BULK_NONCE = cfg.bulkNonce || '';
	var GRAVATAR = 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y&s=20';

	var searchTimer;

	// ── Build an author pill ──────────────────────────────────
	function buildPill(id, name, avatar) {
		var safeAvatar = avatar || GRAVATAR;
		return $('<div class="tb-qe-pill"'
			+ ' data-id="' + id + '"'
			+ ' style="display:flex;align-items:center;gap:6px;padding:4px 8px;background:#f3f4f6;border-radius:4px;margin-bottom:4px;">'
			+ '<img src="' + $('<span>').text(safeAvatar).html() + '" width="20" height="20" style="border-radius:50%;">'
			+ '<span style="font-size:12px;font-weight:600;flex:1;">' + $('<span>').text(name).html() + '</span>'
			+ '<button type="button" class="tb-qe-remove" data-id="' + id + '"'
			+   ' style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:14px;line-height:1;padding:0 2px;">✕</button>'
			+ '</div>');
	}

	// ── Sync pills → hidden JSON ──────────────────────────────
	function syncJson($container, $input) {
		var result = [];
		$container.find('.tb-qe-pill').each(function (i) {
			result.push({ author_id: parseInt($(this).data('id'), 10), category_id: null, sort_order: i });
		});
		$input.val(JSON.stringify(result));
	}

	// ── Search handler ────────────────────────────────────────
	function doSearch(q, nonce, $results) {
		clearTimeout(searchTimer);
		if (q.length < 2) { $results.hide().empty(); return; }
		searchTimer = setTimeout(function () {
			$.post(AJAX_URL, { action: 'trustbio_search_authors', nonce: nonce, q: q })
				.done(function (r) {
					if (!r.success || !r.data.authors.length) {
						$results.html('<div style="padding:8px 10px;font-size:12px;color:#9ca3af;">No authors found.</div>').show();
						return;
					}
					var html = r.data.authors.map(function (a) {
						return '<div class="tb-search-hit"'
							+ ' data-id="'    + a.id + '"'
							+ ' data-name="'  + $('<span>').text(a.display_name).html() + '"'
							+ ' data-avatar="' + $('<span>').text(a.avatar_url || '').html() + '"'
							+ ' style="display:flex;align-items:center;gap:8px;padding:7px 10px;cursor:pointer;font-size:12px;border-bottom:1px solid #f3f4f6;">'
							+ '<img src="' + $('<span>').text(a.avatar_url || GRAVATAR).html() + '" width="20" height="20" style="border-radius:50%;">'
							+ '<strong>' + $('<span>').text(a.display_name).html() + '</strong>'
							+ '</div>';
					}).join('');
					$results.html(html).show();
				});
		}, 300);
	}

	// ── Delegated events — all elements are dynamically injected by WP ──

	// Quick Edit search input.
	$(document).on('input', '#tb-qe-search', function () {
		doSearch($(this).val().trim(), QE_NONCE, $('#tb-qe-results'));
	});

	// Bulk Edit search input.
	$(document).on('input', '#tb-be-search', function () {
		doSearch($(this).val().trim(), BE_NONCE, $('#tb-be-results'));
	});

	// Quick Edit: click result.
	$(document).on('click', '#tb-qe-results .tb-search-hit', function () {
		var id = $(this).data('id'), name = $(this).data('name'), avatar = $(this).data('avatar');
		var $assigned = $('#tb-qe-assigned'), $json = $('#tb-qe-authors-json');
		if ($assigned.find('[data-id="' + id + '"]').length) { $('#tb-qe-results').hide(); return; }
		buildPill(id, name, avatar).appendTo($assigned);
		syncJson($assigned, $json);
		$('#tb-qe-search').val('');
		$('#tb-qe-results').hide();
	});

	// Bulk Edit: click result.
	$(document).on('click', '#tb-be-results .tb-search-hit', function () {
		var id = $(this).data('id'), name = $(this).data('name'), avatar = $(this).data('avatar');
		var $assigned = $('#tb-be-assigned'), $json = $('#tb-be-authors-json');
		if ($assigned.find('[data-id="' + id + '"]').length) { $('#tb-be-results').hide(); return; }
		buildPill(id, name, avatar).appendTo($assigned);
		syncJson($assigned, $json);
		$('#tb-be-search').val('');
		$('#tb-be-results').hide();
	});

	// Remove pill — Quick Edit.
	$(document).on('click', '#tb-qe-assigned .tb-qe-remove', function () {
		var $assigned = $('#tb-qe-assigned'), $json = $('#tb-qe-authors-json');
		$(this).closest('.tb-qe-pill').remove();
		syncJson($assigned, $json);
	});

	// Remove pill — Bulk Edit.
	$(document).on('click', '#tb-be-assigned .tb-qe-remove', function () {
		var $assigned = $('#tb-be-assigned'), $json = $('#tb-be-authors-json');
		$(this).closest('.tb-qe-pill').remove();
		syncJson($assigned, $json);
	});

	// Close dropdowns on outside click.
	$(document).on('click', function (e) {
		if (!$(e.target).closest('#tb-qe-results,#tb-qe-search,#tb-be-results,#tb-be-search').length) {
			$('#tb-qe-results,#tb-be-results').hide();
		}
	});

	// Hover effect on results.
	$(document).on('mouseenter', '.tb-search-hit', function () { $(this).css('background', '#f0f4ff'); });
	$(document).on('mouseleave', '.tb-search-hit', function () { $(this).css('background', ''); });

	// Reset Quick Edit fields when row opens.
	$(document).on('click', '.editinline', function () {
		setTimeout(function () {
			$('#tb-qe-assigned').empty();
			$('#tb-qe-authors-json').val('[]');
			$('#tb-qe-search').val('').focus();
			$('#tb-qe-results').hide();
		}, 200);
	});

	// Bulk Edit save.
	$(document).on('click', '#bulk_edit', function () {
		var postIds = [];
		$('input[name="post[]"]:checked').each(function () { postIds.push($(this).val()); });
		var authorsJson = $('#tb-be-authors-json').val() || '[]';
		if (!postIds.length || authorsJson === '[]') { return; }

		$.post(AJAX_URL, {
			action      : 'trustbio_bulk_edit_save',
			nonce       : BULK_NONCE,
			post_ids    : postIds,
			authors_json: authorsJson,
		}).done(function (r) {
			if (r.success) {
				$('#tb-be-assigned').empty();
				$('#tb-be-authors-json').val('[]');
				$('#tb-be-search').val('');
			}
		});
	});

})(jQuery);
