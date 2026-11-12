/* global trustbioCredentialsPage, TrustBio, trustbioAdmin */
/* TrustBio Credentials Manager — page-specific JS */

(function($){
	'use strict';

	// ── Author picker navigation ────────────────────────────────
	var $picker = document.getElementById('tb-author-picker');
	var $goBtn  = document.getElementById('tb-go-to-author');
	if ($picker && $goBtn) {
		$goBtn.addEventListener('click', function(){
			var id = $picker.value;
			if (id && window.trustbioCredentialsPage) {
				window.location.href = trustbioCredentialsPage.goUrl + id;
			}
		});
	}

	var badgeTypes = window.trustbioCredentialsPage ? trustbioCredentialsPage.badgeTypes : {};

	// Button labels. Localised server-side; the fallbacks keep the button
	// readable if the page is ever rendered without the localize block.
	var TB_I18N = $.extend(
		{ addCredential: 'Add Credential', updateCredential: 'Update Credential' },
		( window.trustbioCredentialsPage && trustbioCredentialsPage.i18n ) || {}
	);

	// ── Badge preview on type change ────────────────────────────
	$('#tb-badge-type').on('change', function(){
		var type = $(this).val();
		var data = badgeTypes[type] || badgeTypes['custom'];
		var label = type !== 'custom' ? type.toUpperCase() : 'CUSTOM';
		$('#tb-badge-preview-pill')
			.css({'background': data.color, 'color': data.text})
			.text(label);
	});

	// ── Edit credential — populate form ─────────────────────────
	$(document).on('click', '.tb-edit-credential', function(){
		var $btn = $(this);
		$('input[name="id"]').val($btn.data('id'));
		$('#tb-badge-type').val($btn.data('badge-type')).trigger('change');
		$('#tb-credential-name').val($btn.data('credential-name'));
		$('#tb-issuing-body').val($btn.data('issuing-body'));
		$('#tb-license-number').val($btn.data('license-number'));
		$('#tb-issue-date').val($btn.data('issue-date'));
		$('#tb-expiry-date').val($btn.data('expiry-date'));
		$('#tb-verification-url').val($btn.data('verification-url'));
		$('#tb-show-badge').prop('checked', $btn.data('show-badge') == 1);
		$('#tb-show-schema').prop('checked', $btn.data('show-schema') == 1);
		$('#tb-cred-submit').text(TB_I18N.updateCredential);
		// Scroll to form.
		$('#tb-credential-form')[0].scrollIntoView({behavior:'smooth', block:'start'});
	});

	// ── Reset form ────────────────────────────────────────────────
	$('#tb-cred-reset').on('click', function(){
		$('input[name="id"]').val('0');
		$('#tb-cred-submit').text(TB_I18N.addCredential);
		$('#tb-badge-type').val('md').trigger('change');
	});

	// ── Submit form ────────────────────────────────────────────────
	$('#tb-credential-form').on('submit', function(e){
		e.preventDefault();
		var $btn = $('#tb-cred-submit');
		var orig = TrustBio.Button.loading($btn);
		var data = $(this).serializeArray();
		data.push({name:'action', value:'trustbio_save_credential'});
		data.push({name:'nonce',  value:trustbioAdmin.nonces.saveCredential});
		if($('#tb-show-badge').prop('checked'))  data.push({name:'show_badge',    value:'1'});
		if($('#tb-show-schema').prop('checked')) data.push({name:'show_in_schema', value:'1'});

		$.post(trustbioAdmin.ajaxUrl, data).done(function(r){
			TrustBio.Button.restore($btn, orig);
			if(r.success){
				TrustBio.Notice.success(r.data.message);
				setTimeout(function(){ location.reload(); }, 700);
			} else {
				TrustBio.Notice.error(r.data ? r.data.message : trustbioAdmin.i18n.error);
			}
		}).fail(function(){
			TrustBio.Button.restore($btn, orig);
			TrustBio.Notice.error(trustbioAdmin.i18n.error);
		});
	});

	// ── Delete credential ─────────────────────────────────────────
	$(document).on('click', '.tb-delete-credential', function(){
		if(!window.confirm(trustbioAdmin.i18n.confirmDelete)) return;
		var id = $(this).data('id'), nonce = $(this).data('nonce');
		$.post(trustbioAdmin.ajaxUrl, {action:'trustbio_delete_credential', nonce:nonce, id:id})
			.done(function(r){
				if(r.success){
					$('#tb-cred-row-'+id).fadeOut(300, function(){ $(this).remove(); });
					TrustBio.Notice.success(r.data.message);
				} else {
					TrustBio.Notice.error(r.data ? r.data.message : trustbioAdmin.i18n.error);
				}
			});
	});

	// ── Drag to reorder ───────────────────────────────────────────
	if($.fn.sortable){
		$('#tb-credentials-list').sortable({
			handle: '.tb-drag-handle',
			axis: 'y',
			tolerance: 'pointer',
			update: function(){
				var order = [];
				$('#tb-credentials-list tr').each(function(){ order.push($(this).data('id')); });
				$.post(trustbioAdmin.ajaxUrl, {
					action: 'trustbio_reorder_credentials',
					nonce:  trustbioAdmin.nonces.reorderCredentials,
					order:  order
				}).done(function(r){
					if(r.success) TrustBio.Notice.success(r.data.message);
				});
			}
		});
	}

})(jQuery);
