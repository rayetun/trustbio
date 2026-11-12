/* global TrustBio, trustbioAdmin */
/* TrustBio Author List — page-specific JS */

(function($){
	'use strict';

	$(document).ready(function(){

		// ── Toggle Add Author panel ───────────────────────────────
		$(document).on('click','#tb-open-add-author',function(){
			$('#tb-add-author-panel').slideDown(200);
		});

		// Deep link: ?action=new opens the form straight away, so links from
		// elsewhere in the admin land on an open form rather than a list.
		if ( window.location.search.indexOf('action=new') !== -1 ) {
			$('#tb-add-author-panel').show();
			$('#tb-author-display-name').trigger('focus');
		}
		$(document).on('click','.tb-close-add-author',function(){
			$('#tb-add-author-panel').slideUp(200);
		});

		// ── Author type tab switching ─────────────────────────────
		$(document).on('click','.tb-author-type-tabs .tb-tab',function(){
			var tab=$(this).data('tab');
			$('.tb-author-type-tabs .tb-tab').removeClass('is-active');
			$(this).addClass('is-active');
			$('#tb-author-type').val(tab==='guest-no-email'?'guest':tab);
			if(tab==='registered'){
				$('#tb-wp-user-field').show();
				$('#tb-email-field').show();
			}else if(tab==='guest'){
				$('#tb-wp-user-field').hide();
				$('#tb-email-field').show();
			}else{
				$('#tb-wp-user-field').hide();
				$('#tb-email-field').hide();
			}
		});

		// ── WP user search (delegated — input may render after JS) ─
		var wpUserTimeout;
		$(document).on('input','#tb-wp-user-search',function(){
			clearTimeout(wpUserTimeout);
			var q=$(this).val().trim();
			if(q.length<2){$('#tb-wp-user-results').hide();return;}
			wpUserTimeout=setTimeout(function(){
				$('#tb-wp-user-results').html('<div class="tb-dropdown-empty"><span class="tb-spinner"></span></div>').show();
				$.post(trustbioAdmin.ajaxUrl,{action:'trustbio_search_wp_users',nonce:trustbioAdmin.nonces.searchWpUsers,q:q})
					.done(function(r){
						if(!r.success||!r.data.users.length){
							$('#tb-wp-user-results').html('<div class="tb-dropdown-empty">No users found.</div>');
							return;
						}
						var html=r.data.users.map(function(u){
							return '<div class="tb-dropdown-item" data-id="'+u.id+'" data-name="'+$('<span>').text(u.display_name).html()+'" data-email="'+$('<span>').text(u.email).html()+'" data-avatar="'+$('<span>').text(u.avatar_url).html()+'">'
								+'<img src="'+$('<span>').text(u.avatar_url).html()+'" alt="">'
								+'<div><div style="font-weight:600;font-size:13px;">'+$('<span>').text(u.display_name).html()+'</div>'
								+'<div style="font-size:11px;color:var(--tb-gray-500);">'+$('<span>').text(u.email).html()+'</div></div></div>';
						}).join('');
						$('#tb-wp-user-results').html(html).show();
					});
			},300);
		});

		$(document).on('click','#tb-wp-user-results .tb-dropdown-item',function(){
			var id=$(this).data('id'),name=$(this).data('name'),email=$(this).data('email'),avatar=$(this).data('avatar');
			$('#tb-wp-user-id').val(id);
			$('#tb-author-display-name').val(name);
			$('#tb-author-email').val(email);
			$('#tb-wp-user-search').val(name);
			$('#tb-wp-user-results').hide();
			$('#tb-wp-user-selected').html('<img src="'+$('<span>').text(avatar).html()+'" alt=""><strong>'+$('<span>').text(name).html()+'</strong>').show();
		});

		$(document).on('click',function(e){
			if(!$(e.target).closest('#tb-wp-user-search,#tb-wp-user-results').length){$('#tb-wp-user-results').hide();}
		});

		// ── Save author ───────────────────────────────────────────
		$(document).on('submit','#tb-author-form',function(e){
			e.preventDefault();
			var $btn=$('#tb-author-submit'),orig=TrustBio.Button.loading($btn);
			var data=$('#tb-author-form').serializeArray();
			data.push({name:'action',value:'trustbio_save_author'},{name:'nonce',value:trustbioAdmin.nonces.saveAuthor});
			$.post(trustbioAdmin.ajaxUrl,data).done(function(r){
				TrustBio.Button.restore($btn,orig);
				if(r.success){TrustBio.Notice.success(r.data.message);setTimeout(function(){location.reload();},800);}
				else{TrustBio.Notice.error(r.data?r.data.message:trustbioAdmin.i18n.error);}
			}).fail(function(){TrustBio.Button.restore($btn,orig);TrustBio.Notice.error(trustbioAdmin.i18n.error);});
		});

		// ── Delete author ─────────────────────────────────────────
		$(document).on('click','.tb-delete-author',function(){
			if(!window.confirm(trustbioAdmin.i18n.confirmDelete))return;
			var id=$(this).data('id'),nonce=$(this).data('nonce');
			$.post(trustbioAdmin.ajaxUrl,{action:'trustbio_delete_author',nonce:nonce,id:id}).done(function(r){
				if(r.success){$('#tb-author-row-'+id).fadeOut(300,function(){$(this).remove();});TrustBio.Notice.success(r.data.message);}
				else{TrustBio.Notice.error(r.data?r.data.message:trustbioAdmin.i18n.error);}
			});
		});

	}); // end document.ready

})(jQuery);
