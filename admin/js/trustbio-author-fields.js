/* global TrustBio, trustbioAdmin */
/* TrustBio author_fields page JS */

(function($){
	'use strict';
	$(document).ready(function(){

	$('#tb-field-label').on('input',function(){
		if(!$('#tb-field-id').val()){
			$('#tb-field-slug').val($(this).val().toLowerCase().replace(/[^a-z0-9]/g,'_').replace(/_+/g,'_').replace(/^_|_$/g,''));
		}
	});

	$('#tb-field-form').on('submit',function(e){
		e.preventDefault();
		var $btn=$('#tb-field-submit'),orig=TrustBio.Button.loading($btn);
		var data=$('#tb-field-form').serializeArray();
		data.push({name:'action',value:'trustbio_save_field'},{name:'nonce',value:trustbioAdmin.nonces.saveField});
		if($('#tb-field-social').prop('checked'))data.push({name:'is_social',value:'1'});
		if($('#tb-field-required').prop('checked'))data.push({name:'is_required',value:'1'});
		if($('#tb-field-active').prop('checked'))data.push({name:'is_active',value:'1'});
		$.post(trustbioAdmin.ajaxUrl,data).done(function(r){
			TrustBio.Button.restore($btn,orig);
			if(r.success){TrustBio.Notice.success(r.data.message);setTimeout(function(){location.reload();},800);}
			else{TrustBio.Notice.error(r.data?r.data.message:trustbioAdmin.i18n.error);}
		}).fail(function(){TrustBio.Button.restore($btn,orig);TrustBio.Notice.error(trustbioAdmin.i18n.error);});
	});

	$(document).on('click','.tb-delete-field',function(){
		if(!window.confirm(trustbioAdmin.i18n.confirmDelete))return;
		var id=$(this).data('id'),nonce=$(this).data('nonce');
		$.post(trustbioAdmin.ajaxUrl,{action:'trustbio_delete_field',nonce:nonce,id:id}).done(function(r){
			if(r.success){$('#tb-field-row-'+id).fadeOut(300,function(){$(this).remove();});TrustBio.Notice.success(r.data.message);}
			else{TrustBio.Notice.error(r.data?r.data.message:trustbioAdmin.i18n.error);}
		});
	});

	if($.fn.sortable){
		$('#tb-fields-sortable').sortable({
			handle:'.tb-drag-handle',axis:'y',tolerance:'pointer',
			update:function(){
				var order=[];
				$('#tb-fields-sortable tr').each(function(){order.push($(this).data('id'));});
				$.post(trustbioAdmin.ajaxUrl,{action:'trustbio_reorder_fields',nonce:trustbioAdmin.nonces.reorderFields,order:order}).done(function(r){
					if(r.success)TrustBio.Notice.success(r.data.message);
				});
			}
		});
	}
	});
})(jQuery);
