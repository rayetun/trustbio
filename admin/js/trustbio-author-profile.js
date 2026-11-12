/* global trustbioProfilePage, TrustBio, trustbioAdmin */
/* TrustBio Author Profile — page-specific JS */

(function($){
	'use strict';
	$(document).ready(function(){

	// ── Self-contained tab switching for the profile editor ──────────────
	// Scoped to #tb-profile-tabs-card. Does NOT rely on TBTabs.
	$('#tb-profile-tabs-card').on('click', '.tb-tab[data-tab]', function(e){
		e.preventDefault();
		e.stopPropagation();

		var target = $(this).data('tab');
		var $card  = $('#tb-profile-tabs-card');

		$card.find('.tb-tab').removeClass('is-active');
		$card.find('.tb-tab-pane').removeClass('is-active').hide();

		$(this).addClass('is-active');
		$card.find('[data-tab-pane="' + target + '"]').addClass('is-active').show();
	});

	// Initial state.
	(function(){
		var $card = $('#tb-profile-tabs-card');
		$card.find('.tb-tab-pane').hide();
		$card.find('.tb-tab-pane.is-active').show();
	})();

	// Restore from URL hash.
	var urlHash = window.location.hash.replace('#','');
	if(urlHash){
		var $ht = $('#tb-profile-tabs-card .tb-tab[data-tab="'+urlHash+'"]');
		if($ht.length) $ht.trigger('click');
	}

	// ── Form submit ───────────────────────────────────────────────────────
	// Form submit — both header button and sticky bar button.
	function submitProfile() {
		var $btn    = $('#tb-profile-save-btn');
		var $btn2   = $('#tb-save-bar-btn');
		var orig    = TrustBio.Button.loading($btn);
		var orig2   = TrustBio.Button.loading($btn2);
		var data    = $('#tb-profile-form').serializeArray();

		data.push({name:'action', value:'trustbio_save_profile'});
		data.push({name:'nonce',  value:trustbioAdmin.nonces.saveProfile});

		// Append un-checked toggles (serializeArray skips them — no toggles on this form).

		$.post(trustbioAdmin.ajaxUrl, data)
			.done(function(r){
				TrustBio.Button.restore($btn, orig);
				TrustBio.Button.restore($btn2, orig2);
				if(r.success){
					TrustBio.Notice.success(r.data.message);
					$('#tb-save-status').text('').css('color','var(--tb-success)');
					if(r.data.slug){
						$('#tb-author-slug-display').text(r.data.slug);
					}
					// Trigger WP dirty-state reset.
					$(document).trigger('trustbio:saved');
				} else {
					TrustBio.Notice.error(r.data ? r.data.message : trustbioAdmin.i18n.error);
				}
			})
			.fail(function(){
				TrustBio.Button.restore($btn, orig);
				TrustBio.Button.restore($btn2, orig2);
				TrustBio.Notice.error(trustbioAdmin.i18n.error);
			});
	}

	$('#tb-profile-form').on('submit', function(e){ e.preventDefault(); submitProfile(); });
	$('#tb-profile-save-btn').on('click', function(e){ e.preventDefault(); submitProfile(); });
	// Bottom save bar button — outside the form so needs its own click handler.
	$(document).on('click', '#tb-save-bar-btn', function(e){ e.preventDefault(); submitProfile(); });

	// Show remove button when avatar is selected.
	$(document).on('change','#tb-avatar-attach-id', function(){
		if($(this).val()){
			$('#tb-remove-avatar').show();
		}
	});

	// Remove avatar.
	$('#tb-remove-avatar').on('click',function(){
		$('#tb-avatar-attach-id').val('');
		$('#tb-avatar-url-field').val('');
		$('#tb-avatar-preview-img').attr('src', '');
		$(this).hide();
	});

	// Social field: highlight row when URL is entered.
	$(document).on('input', '.tb-social-field-item input', function(){
		var $item = $(this).closest('.tb-social-field-item');
		if($(this).val()){
			$item.addClass('has-value');
		}else{
			$item.removeClass('has-value');
		}
	});


	// ── Inline Credentials tab (Phase 11) ─────────────────────
	var tbInlineBadgeTypes = window.trustbioProfilePage ? trustbioProfilePage.badgeTypes : {};

	// Badge preview on type change.
	$('#tb-inline-badge-type').on('change', function(){
		var type = $(this).val();
		var data = tbInlineBadgeTypes[type] || tbInlineBadgeTypes['custom'];
		var label = type !== 'custom' ? type.toUpperCase() : 'CUSTOM';
		$('#tb-inline-badge-preview').css({'background': data.color, 'color': data.text}).text(label);
	});

	// Submit inline credential form.
	$('#tb-inline-cred-form').on('submit', function(e){
		e.preventDefault();
		var $btn = $('#tb-inline-cred-submit');
		var orig = TrustBio.Button.loading($btn);
		var data = $(this).serializeArray();
		data.push({name:'action', value:'trustbio_save_credential'});
		data.push({name:'nonce',  value:trustbioAdmin.nonces.saveCredential});
		if($('#tb-inline-show-badge').prop('checked'))  data.push({name:'show_badge',     value:'1'});
		if($('#tb-inline-show-schema').prop('checked')) data.push({name:'show_in_schema',  value:'1'});
		$.post(trustbioAdmin.ajaxUrl, data).done(function(r){
			TrustBio.Button.restore($btn, orig);
			if(r.success){ TrustBio.Notice.success(r.data.message); setTimeout(function(){ location.reload(); }, 700); }
			else { TrustBio.Notice.error(r.data ? r.data.message : trustbioAdmin.i18n.error); }
		}).fail(function(){ TrustBio.Button.restore($btn, orig); TrustBio.Notice.error(trustbioAdmin.i18n.error); });
	});

	// Reset inline credential form.
	$('#tb-inline-cred-reset').on('click', function(){
		$('input[name="cred_id"]', '#tb-inline-cred-form').val('0');
		$('#tb-inline-cred-submit').text(trustbioProfilePage.i18n.addCredential);
		$('#tb-inline-badge-type').val('md').trigger('change');
	});

	// Delete inline credential.
	$(document).on('click', '.tb-inline-delete-cred', function(){
		if(!window.confirm(trustbioAdmin.i18n.confirmDelete)) return;
		var id = $(this).data('id'), nonce = $(this).data('nonce');
		$.post(trustbioAdmin.ajaxUrl, {action:'trustbio_delete_credential', nonce:nonce, id:id})
			.done(function(r){
				if(r.success){ $('#tb-inline-cred-'+id).fadeOut(300, function(){$(this).remove();}); TrustBio.Notice.success(r.data.message); }
				else { TrustBio.Notice.error(r.data ? r.data.message : trustbioAdmin.i18n.error); }
			});
	});

	// ── Expertise Topics tab (v20) ────────────────────────────
	$(document).on('click', '#tb-save-expertise-btn', function(){
		var $btn      = $(this);
		var authorId  = $btn.data('author-id');
		var nonce     = $btn.data('nonce');
		var catIds    = [];
		$('#tb-expertise-list input[type="checkbox"]:checked').each(function(){
			catIds.push($(this).val());
		});
		var orig = TrustBio.Button.loading($btn);
		$.post(trustbioAdmin.ajaxUrl, {
			action      : 'trustbio_save_expertise',
			nonce       : nonce,
			author_id   : authorId,
			category_ids: catIds,
		}).done(function(r){
			TrustBio.Button.restore($btn, orig);
			if(r.success){
				$('#tb-expertise-status').text(r.data.message).css('color','var(--tb-success)');
				setTimeout(function(){ $('#tb-expertise-status').text(''); }, 3000);
			} else {
				TrustBio.Notice.error(r.data ? r.data.message : trustbioAdmin.i18n.error);
			}
		}).fail(function(){
			TrustBio.Button.restore($btn, orig);
			TrustBio.Notice.error(trustbioAdmin.i18n.error);
		});
	});

	});
})(jQuery);
