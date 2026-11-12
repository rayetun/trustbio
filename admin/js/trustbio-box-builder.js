/* global trustbioBuildPage, TrustBio, trustbioAdmin */
/* TrustBio Author Box Builder — page-specific JS */

	(function($){
		'use strict';

		// ── Load template from sessionStorage (when redirected from template picker) ──
		(function applyTemplateIfPresent(){
			if ( window.trustbioBuildPage && trustbioBuildPage.fromTemplate ) {
			try {
				var tmplRaw = sessionStorage.getItem('trustbio_template');
				if(!tmplRaw) return;
				var tmpl = JSON.parse(tmplRaw);
				sessionStorage.removeItem('trustbio_template');

				// Set the box name.
				if(tmpl.name) $('#tb-box-title').val(tmpl.name);

				var s = tmpl.settings || {};

				// Apply all settings to form fields.
				Object.keys(s).forEach(function(key){
					if(key === 'layout') return; // handled separately below
					var val = s[key];
					var $field = $('[name="settings['+key+']"]');
					if(!$field.length) return;
					if($field.attr('type')==='checkbox'){
						$field.prop('checked', val===true||val===1||val==='1');
					}else if($field.attr('type')==='radio'){
						$('[name="settings['+key+']"][value="'+val+'"]').prop('checked',true);
					}else{
						$field.val(val);
					}
					if($field.hasClass('tb-color-swatch')){
						$field.siblings('.tb-color-text').val(typeof val==='string'?val:'');
					}
				});

				// Set layout — read from top-level tmpl.layout OR s.layout.
				var layoutKey = tmpl.layout || s.layout;
				if(layoutKey){
					$('.tb-layout-card').removeClass('is-active');
					$('.tb-layout-card[data-layout="'+layoutKey+'"]').addClass('is-active');
					$('[name="layout"][value="'+layoutKey+'"]').prop('checked',true);
				}

				// Refresh icon style cards visual state.
				if(s.icon_style){
					$('.tb-icon-style-card').removeClass('is-active');
					$('.tb-icon-style-card [value="'+s.icon_style+'"]').closest('.tb-icon-style-card').addClass('is-active');
				}

				// Update border controls immediately; preview is handled by TBBoxPreview.
				updateBorderSubControls();
			} catch(err) { console.warn('TrustBio template load error:', err); }
			}
		})();

		// ── Border style toggle: hide/show sub-controls ─────────────────
		function updateBorderSubControls(){
			var style = $( '#tb-border-style-select' ).val();
			if ( 'none' === style ) {
				$( '#tb-border-sub-controls' ).slideUp( 200 );
			} else {
				$( '#tb-border-sub-controls' ).slideDown( 200 );
			}
		}
		$( '#tb-border-style-select' ).on( 'change', function(){
			updateBorderSubControls();
			triggerPreview();
		});
		updateBorderSubControls(); // run on load

		// ── Self-contained tab switching for the builder ──────────────────
		// Does NOT rely on TBTabs in trustbio-admin.js.
		// Scoped strictly to #tb-customiser-card so other tab groups are safe.
		$('#tb-customiser-card').on('click', '.tb-tab[data-tab]', function(e){
			e.preventDefault();
			e.stopPropagation(); // prevent TBTabs from also firing

			var target = $(this).data('tab');
			var $card  = $('#tb-customiser-card');

			// Deactivate all tabs and panes inside this card.
			$card.find('.tb-tab').removeClass('is-active');
			$card.find('.tb-tab-pane').removeClass('is-active').hide();

			// Activate the clicked tab and matching pane.
			$(this).addClass('is-active');
			$card.find('[data-tab-pane="' + target + '"]').addClass('is-active').show();
		});

		// Set initial state: hide all panes, show only layout.
		(function initTabs(){
			var $card = $('#tb-customiser-card');
			$card.find('.tb-tab-pane').hide();
			$card.find('.tb-tab-pane.is-active').show();
		})();

		// Restore from URL hash if present.
		var urlHash = window.location.hash.replace('#','');
		if(urlHash){
			var $hashTab = $('#tb-customiser-card .tb-tab[data-tab="'+urlHash+'"]');
			if($hashTab.length) $hashTab.trigger('click');
		}

		// ── Preview ───────────────────────────────────────────────────────
		// All preview logic lives in trustbio-box-preview.js (window.TBBoxPreview).
		// This file only calls TBBoxPreview.refresh() for special cases that
		// require extra work before refreshing (layout defaults, template load).
		function triggerPreview(){
			if ( window.TBBoxPreview ) { TBBoxPreview.refresh(); }
		}

		// Layout card selection — reset form to layout defaults then trigger preview.
		var tbLayoutDefaults = trustbioBuildPage.layoutDefaults || {};

		$(document).on('click', '.tb-layout-card', function(){
			$('.tb-layout-card').removeClass('is-active');
			$(this).addClass('is-active');
			var layout = $(this).data('layout');
			$(this).find('input[type="radio"]').prop('checked',true);

			// Apply layout defaults to all form fields.
			var defaults = tbLayoutDefaults[layout] || {};
			Object.keys(defaults).forEach(function(key){
				var val = defaults[key];
				var $field = $('[name="settings['+key+']"]');
				if(!$field.length) return;
				if($field.attr('type')==='checkbox'){
					$field.prop('checked', val === true || val === 1);
				}else if($field.attr('type')==='radio'){
					$('[name="settings['+key+']"][value="'+val+'"]').prop('checked',true);
				}else{
					$field.val(val);
				}
				// Sync colour text fields.
				if($field.hasClass('tb-color-swatch')){
					$field.siblings('.tb-color-text').val(val);
				}
			});
			triggerPreview();
		});

		// Avatar shape card selection.
		$(document).on('click', '.tb-shape-card', function(){
			$('.tb-shape-card').removeClass('is-active');
			$(this).addClass('is-active');
			$(this).find('input[type="radio"]').prop('checked',true);
		});

		// Icon style card selection.
		$(document).on('click', '.tb-icon-style-card', function(){
			$('.tb-icon-style-card').removeClass('is-active');
			$(this).addClass('is-active');
			$(this).find('input[type="radio"]').prop('checked',true);
		});

		// Color swatch <-> text input sync.
		$(document).on('input', '.tb-color-swatch', function(){
			$(this).siblings('.tb-color-text').val($(this).val());
			triggerPreview();
		});
		$(document).on('input', '.tb-color-text', function(){
			var val = $(this).val();
			if(/^#[0-9a-fA-F]{6}$/.test(val)){
				$(this).siblings('.tb-color-swatch').val(val);
				triggerPreview();
			}
		});

		// Save form — with isSaving guard to prevent duplicate boxes on rapid clicks.
		var isSaving = false;
		function saveBox(){
			if(isSaving) return;
			isSaving = true;
			var $btn  = $('#tb-box-save-btn');
			var $btn2 = $('#tb-save-bar-btn');
			var orig  = TrustBio.Button.loading($btn);
			var orig2 = TrustBio.Button.loading($btn2);
			var data  = $('#tb-box-builder-form').serializeArray();

			data.push({name:'action',value:'trustbio_save_box'});
			data.push({name:'nonce', value:trustbioAdmin.nonces.saveBox});
			// Append box_shadow checkbox state correctly.
			if($('input[name="settings[box_shadow]"]').prop('checked')){
				data.push({name:'settings[box_shadow]',value:'1'});
			}

			$.post(trustbioAdmin.ajaxUrl, data).done(function(r){
				isSaving = false;
				TrustBio.Button.restore($btn, orig);
				TrustBio.Button.restore($btn2, orig2);
				if(r.success){
					TrustBio.Notice.success(r.data.message);
					$('#tb-box-save-status').text('' + (trustbioBuildPage.i18n && trustbioBuildPage.i18n.saved ? trustbioBuildPage.i18n.saved : 'Saved') + '').css('color','var(--tb-success)');
					// After first insert, update the hidden ID so subsequent saves UPDATE not INSERT.
					if(r.data.id && !$('input[name="id"]').val()){
						$('input[name="id"]').val(r.data.id);
						// Also update the browser URL to avoid creating another new box on reload.
						if(history.replaceState){
							history.replaceState(null,'','?page=trustbio-boxes&action=edit&id='+r.data.id);
						}
					}
				}else{
					TrustBio.Notice.error(r.data ? r.data.message : trustbioAdmin.i18n.error);
				}
			}).fail(function(){
				isSaving = false;
				TrustBio.Button.restore($btn, orig);
				TrustBio.Button.restore($btn2, orig2);
				TrustBio.Notice.error(trustbioAdmin.i18n.error);
			});
		}

		$('#tb-box-builder-form').on('submit', function(e){e.preventDefault();saveBox();});
		$('#tb-box-save-btn, #tb-save-bar-btn').on('click', function(e){e.preventDefault();saveBox();});

		// Initial preview is handled by trustbio-box-preview.js on DOM ready.

	})(jQuery);
	