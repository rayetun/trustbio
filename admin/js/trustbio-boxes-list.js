/* global trustbioBoxesPage, TrustBio, trustbioAdmin */
/* TrustBio Author Boxes List — page-specific JS */

(function($){
	'use strict';

	// ── Template index — keyed by position in tbTemplates array ─
	var tbTemplates = window.trustbioBoxesPage ? trustbioBoxesPage.templates : [];
	var tbNewBoxUrl = window.trustbioBoxesPage ? trustbioBoxesPage.newBoxUrl : '';

	// ── Apply: store to sessionStorage → navigate to builder ────
	function applyTemplate( id ) {
		var tmpl = null;
		for ( var i = 0; i < tbTemplates.length; i++ ) {
			if ( tbTemplates[ i ].id === id ) { tmpl = tbTemplates[ i ]; break; }
		}
		if ( ! tmpl ) { console.error( 'TrustBio: no template with id', id ); return; }
		var payload = { name: tmpl.name, settings: tmpl.settings, layout: tmpl.layout };
		try {
			sessionStorage.setItem( 'trustbio_template', JSON.stringify( payload ) );
		} catch(e) {
			console.warn( 'TrustBio: sessionStorage unavailable', e );
		}
		window.location.href = tbNewBoxUrl;
	}

	// ── Modal helpers ────────────────────────────────────────────
	function openModal( $el ) { $el.fadeIn( 180 ); $('body').css('overflow','hidden'); }
	function closeModal( $el ) { $el.fadeOut( 160 ); $('body').css('overflow',''); }
	function closeAllModals() { $('.tb-modal-overlay').each(function(){ closeModal($(this)); }); }

	// ── Add New Box button → Starter Modal ──────────────────────
	$(document).on( 'click', '#tb-new-box-btn, #tb-new-box-btn-empty', function(){
		openModal( $('#tb-new-box-modal') );
	});
	$('#tb-new-box-modal-close').on( 'click', function(){ closeModal( $('#tb-new-box-modal') ); });

	// ── Layout picker cards, matched by template id ─────────────
	$(document).on( 'click', '.tb-starter-card[data-template-id]', function(){
		var id = String( $(this).data('template-id') );
		closeAllModals();
		applyTemplate( id );
	});

	// ── Overlay click closes modal ───────────────────────────────
	$(document).on( 'click', '.tb-modal-overlay', function(e){
		if ( $(e.target).hasClass('tb-modal-overlay') ) closeModal( $(this) );
	});

	// ── Escape key ───────────────────────────────────────────────
	$(document).on( 'keydown', function(e){
		if ( e.key === 'Escape' ) closeAllModals();
	});

	// ── Saved box dropdown menus ─────────────────────────────────
	$(document).on( 'click', '.tb-box-more', function(e){
		e.stopPropagation();
		var id = $(this).data('id');
		$('.tb-box-dropdown').not('#tb-dropdown-'+id).hide();
		$('#tb-dropdown-'+id).toggle();
	});
	$(document).on( 'click', function(){ $('.tb-box-dropdown').hide(); });

	// ── Set default ───────────────────────────────────────────────
	$(document).on( 'click', '.tb-set-default-box', function(){
		var id = $(this).data('id'), nonce = $(this).data('nonce');
		$.post(trustbioAdmin.ajaxUrl,{action:'trustbio_set_default_box',nonce:nonce,id:id})
			.done(function(r){
				if(r.success){TrustBio.Notice.success(r.data.message);setTimeout(function(){location.reload();},800);}
				else{TrustBio.Notice.error(r.data?r.data.message:trustbioAdmin.i18n.error);}
			});
	});

	// ── Duplicate ─────────────────────────────────────────────────
	$(document).on( 'click', '.tb-duplicate-box', function(){
		var id = $(this).data('id'), nonce = $(this).data('nonce');
		$.post(trustbioAdmin.ajaxUrl,{action:'trustbio_duplicate_box',nonce:nonce,id:id})
			.done(function(r){
				if(r.success){TrustBio.Notice.success(r.data.message);setTimeout(function(){location.reload();},800);}
				else{TrustBio.Notice.error(r.data?r.data.message:trustbioAdmin.i18n.error);}
			});
	});

	// ── Delete ────────────────────────────────────────────────────
	$(document).on( 'click', '.tb-delete-box', function(){
		if(!window.confirm(trustbioAdmin.i18n.confirmDelete)) return;
		var id = $(this).data('id'), nonce = $(this).data('nonce');
		$.post(trustbioAdmin.ajaxUrl,{action:'trustbio_delete_box',nonce:nonce,id:id})
			.done(function(r){
				if(r.success){$('#tb-box-card-'+id).fadeOut(300,function(){$(this).remove();});TrustBio.Notice.success(r.data.message);}
				else{TrustBio.Notice.error(r.data?r.data.message:trustbioAdmin.i18n.error);}
			});
	});

})(jQuery);
