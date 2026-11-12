/* global TrustBio, trustbioAdmin, trustbioOnboarding */
/* TrustBio Onboarding Wizard JS */

(function($){
	'use strict';

	var tbWz = {
		currentStep : 1,
		totalSteps  : 4,
		state       : { catId: 0, authorId: 0, boxId: 0, catName: '', authorName: '', boxName: '' },

		init: function(){
			// Open wizard.
			$(document).on('click', '#tbwz-open-btn', function(){
				tbWz.open();
			});

			// Close wizard.
			// The X button holds an <svg>, so a click lands on the path, not
			// the button -- which is why the shared `e.target === this` guard
			// below made it unclickable. That guard belongs to the overlay
			// alone, where it stops a click inside the dialog from closing it.
			$(document).on('click', '#tbwz-close', function(){
				tbWz.close();
			});
			$(document).on('click', '.tbwz-overlay', function(e){
				if(e.target === this){ tbWz.close(); }
			});
			$(document).on('keydown', function(e){
				if(e.key === 'Escape'){ tbWz.close(); }
			});

			// Navigation.
			$(document).on('click', '#tbwz-btn-next',   function(){ tbWz.next(); });
			$(document).on('click', '#tbwz-btn-back',   function(){ tbWz.back(); });
			$(document).on('click', '#tbwz-btn-skip',   function(){ tbWz.skip(); });
			$(document).on('click', '#tbwz-btn-finish', function(){ tbWz.close(); });

			// Layout card selection (step 3).
			$(document).on('click', '.tbwz-layout-card', function(){
				$('.tbwz-layout-card').removeClass('is-selected');
				$(this).addClass('is-selected');
			});
		},

		open: function(){
			tbWz.currentStep = 1;
			tbWz.state = { catId: 0, authorId: 0, boxId: 0, catName: '', authorName: '', boxName: '' };
			tbWz.renderStep(1);
			$('#tbwz-overlay').fadeIn(200);
			$('body').css('overflow', 'hidden');
		},

		close: function(){
			$('#tbwz-overlay').fadeOut(150);
			$('body').css('overflow', '');
		},

		next: function(){
			var step = tbWz.currentStep;
			if(step === 1){ tbWz.step1Submit(); }
			else if(step === 2){ tbWz.step2Submit(); }
			else if(step === 3){ tbWz.step3Submit(); }
		},

		back: function(){
			if(tbWz.currentStep > 1){
				tbWz.renderStep(tbWz.currentStep - 1);
			}
		},

		skip: function(){
			tbWz.renderStep(tbWz.currentStep + 1);
		},

		renderStep: function(n){
			tbWz.currentStep = n;

			// Update progress indicators.
			for(var i = 1; i <= tbWz.totalSteps; i++){
				var $dot = $('.tbwz-step[data-step="'+i+'"]');
				var $line = $('.tbwz-step-line[data-after="'+i+'"]');
				$dot.removeClass('is-active is-done');
				if(i < n){ $dot.addClass('is-done'); if($line.length){ $line.addClass('is-done'); } }
				else if(i === n){ $dot.addClass('is-active'); }
				else { if($line.length){ $line.removeClass('is-done'); } }
			}

			// Show/hide panes.
			$('.tbwz-pane').removeClass('is-active');
			$('.tbwz-pane[data-pane="'+n+'"]').addClass('is-active');

			// Back button.
			$('#tbwz-btn-back').css('visibility', n > 1 && n < tbWz.totalSteps ? 'visible' : 'hidden');

			// Footer buttons.
			if(n === tbWz.totalSteps){
				$('#tbwz-btn-next').hide();
				$('#tbwz-btn-skip').hide();
				$('#tbwz-btn-finish').show();
			} else {
				$('#tbwz-btn-next').show();
				$('#tbwz-btn-skip').show();
				$('#tbwz-btn-finish').hide();
				// Step 4 doesn't need skip.
				if(n === 3){
					$('#tbwz-btn-skip').text(trustbioOnboarding.i18n.skipStep);
				} else {
					$('#tbwz-btn-skip').text(trustbioOnboarding.i18n.skipStep);
				}
			}

			// Step-specific setup.
			if(n === 4){ tbWz.renderStep4Summary(); }
		},

		// ── Step 1: Create category ───────────────────────────
		step1Submit: function(){
			var singular = $.trim($('#tbwz-cat-singular').val());
			var label    = $.trim($('#tbwz-cat-label').val());

			if(!singular){
				$('#tbwz-step1-error').text(trustbioOnboarding.i18n.catNameRequired).show();
				return;
			}
			$('#tbwz-step1-error').hide();

			var $btn = $('#tbwz-btn-next');
			var orig = TrustBio.Button.loading($btn);

			$.post(trustbioAdmin.ajaxUrl, {
				action         : 'trustbio_save_category',
				nonce          : trustbioAdmin.nonces.saveCategory,
				id             : 0,
				singular       : singular,
				plural         : singular + 's',
				slug           : singular.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, ''),
				display_label  : label || singular,
				schema_prop    : 'reviewedBy',
				contribution_verb : 'Reviewed by',
				contribution_noun : 'Reviews',
				post_types     : ['post'],
				is_enabled     : 1,
			}).done(function(r){
				TrustBio.Button.restore($btn, orig);
				if(r.success){
					tbWz.state.catId   = r.data.id || 0;
					tbWz.state.catName = singular;
					// Pre-fill category in step 2's category select if visible.
					$('#tbwz-author-category').val(tbWz.state.catId);
					tbWz.renderStep(2);
				} else {
					$('#tbwz-step1-error').text(r.data ? r.data.message : trustbioOnboarding.i18n.error).show();
				}
			}).fail(function(){
				TrustBio.Button.restore($btn, orig);
				$('#tbwz-step1-error').text(trustbioOnboarding.i18n.error).show();
			});
		},

		// ── Step 2: Add author ────────────────────────────────
		step2Submit: function(){
			var name = $.trim($('#tbwz-author-name').val());
			var job  = $.trim($('#tbwz-author-job').val());
			var email= $.trim($('#tbwz-author-email').val());

			if(!name){
				$('#tbwz-step2-error').text(trustbioOnboarding.i18n.authorNameRequired).show();
				return;
			}
			$('#tbwz-step2-error').hide();

			var $btn = $('#tbwz-btn-next');
			var orig = TrustBio.Button.loading($btn);

			$.post(trustbioAdmin.ajaxUrl, {
				action       : 'trustbio_save_author',
				nonce        : trustbioAdmin.nonces.saveAuthor,
				id           : 0,
				author_type  : 'guest',
				display_name : name,
				job_title    : job,
				email        : email,
				category_id  : tbWz.state.catId || '',
			}).done(function(r){
				TrustBio.Button.restore($btn, orig);
				if(r.success){
					tbWz.state.authorId   = r.data.id || 0;
					tbWz.state.authorName = name;
					tbWz.renderStep(3);
				} else {
					$('#tbwz-step2-error').text(r.data ? r.data.message : trustbioOnboarding.i18n.error).show();
				}
			}).fail(function(){
				TrustBio.Button.restore($btn, orig);
				$('#tbwz-step2-error').text(trustbioOnboarding.i18n.error).show();
			});
		},

		// ── Step 3: Create author box from template ───────────
		step3Submit: function(){
			var $selected = $('.tbwz-layout-card.is-selected');
			if(!$selected.length){
				$('#tbwz-step3-error').text(trustbioOnboarding.i18n.pickLayout).show();
				return;
			}
			$('#tbwz-step3-error').hide();

			var layout    = $selected.data('layout');
			var boxTitle  = (tbWz.state.authorName || 'Author') + ' Box';

			var $btn = $('#tbwz-btn-next');
			var orig = TrustBio.Button.loading($btn);

			$.post(trustbioAdmin.ajaxUrl, {
				action : 'trustbio_wizard_create_box',
				nonce  : trustbioAdmin.nonces.saveBox,
				layout : layout,
				title  : boxTitle,
			}).done(function(r){
				TrustBio.Button.restore($btn, orig);
				if(r.success){
					tbWz.state.boxId   = r.data.id || 0;
					tbWz.state.boxName = boxTitle;
					tbWz.state.boxEditUrl = r.data.edit_url || '';
					tbWz.renderStep(4);
				} else {
					$('#tbwz-step3-error').text(r.data ? r.data.message : trustbioOnboarding.i18n.error).show();
				}
			}).fail(function(){
				TrustBio.Button.restore($btn, orig);
				$('#tbwz-step3-error').text(trustbioOnboarding.i18n.error).show();
			});
		},

		// ── Step 4: Summary ───────────────────────────────────
		renderStep4Summary: function(){
			var checkSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';

			if(tbWz.state.catId){
				$('#tbwz-sum-cat').html(checkSvg + '<span>' + trustbioOnboarding.i18n.catCreated + ' <strong>' + tbWz.esc(tbWz.state.catName) + '</strong></span>').show();
			} else {
				$('#tbwz-sum-cat').hide();
			}
			if(tbWz.state.authorId){
				var profileUrl = trustbioOnboarding.profileBaseUrl + tbWz.state.authorId;
				$('#tbwz-sum-author').html(checkSvg + '<span>' + trustbioOnboarding.i18n.authorCreated + ' <strong><a href="'+profileUrl+'" target="_blank">' + tbWz.esc(tbWz.state.authorName) + '</a></strong></span>').show();
			} else {
				$('#tbwz-sum-author').hide();
			}
			if(tbWz.state.boxId){
				$('#tbwz-sum-box').html(checkSvg + '<span>' + trustbioOnboarding.i18n.boxCreated + ' <strong><a href="'+tbWz.state.boxEditUrl+'" target="_blank">' + tbWz.esc(tbWz.state.boxName) + '</a></strong></span>').show();
			} else {
				$('#tbwz-sum-box').hide();
			}
		},

		esc: function(str){
			return $('<span>').text(str).html();
		},
	};

	$(document).ready(function(){ tbWz.init(); });

})(jQuery);
