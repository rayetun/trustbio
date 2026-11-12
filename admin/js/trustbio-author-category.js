/* global TrustBio, trustbioAdmin */
/* TrustBio author_category page JS */

(function($){
	'use strict';
	$(document).ready(function(){
	$('#tb-cat-pt-all').on('change',function(){
		if($(this).prop('checked')){$('#tb-pt-list').addClass('tb-hidden');$('#tb-pt-list input').prop('checked',false);}
		else{$('#tb-pt-list').removeClass('tb-hidden');}
	});
	$('#tb-pt-list input[type="checkbox"]').on('change',function(){
		if($(this).prop('checked')){$('#tb-cat-pt-all').prop('checked',false);}
	});
	$('#tb-category-form').on('submit',function(e){
		e.preventDefault();
		var $btn=$("#tb-cat-submit"),orig=TrustBio.Button.loading($btn);
		var data=$('#tb-category-form').serializeArray();
		data.push({name:'action',value:'trustbio_save_category'});
		data.push({name:'nonce',value:trustbioAdmin.nonces.saveCategory});
		if($('#tb-cat-enabled').prop('checked'))data.push({name:'is_enabled',value:'1'});
		$.post(trustbioAdmin.ajaxUrl,data).done(function(r){
			TrustBio.Button.restore($btn,orig);
			if(r.success){TrustBio.Notice.success(r.data.message);setTimeout(function(){location.reload();},800);}
			else{TrustBio.Notice.error(r.data?r.data.message:trustbioAdmin.i18n.error);}
		}).fail(function(){TrustBio.Button.restore($btn,orig);TrustBio.Notice.error(trustbioAdmin.i18n.error);});
	});
	$(document).on('click','.tb-delete-category',function(){
		if(!window.confirm(trustbioAdmin.i18n.confirmDelete))return;
		var id=$(this).data('id'),nonce=$(this).data('nonce');
		$.post(trustbioAdmin.ajaxUrl,{action:'trustbio_delete_category',nonce:nonce,id:id}).done(function(r){
			if(r.success){$('#tb-cat-row-'+id).fadeOut(300,function(){$(this).remove();});TrustBio.Notice.success(r.data.message);}
			else{TrustBio.Notice.error(r.data?r.data.message:trustbioAdmin.i18n.error);}
		});
	});
	});
})(jQuery);
