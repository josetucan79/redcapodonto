$(function(){
	$('#econsent_confirm_checkbox').prop('disabled',true);
	$('#form button[name=\"submit-btn-saverecord\"]').button('disable');
	$('#econsent_confirm_checkbox_label, #econsent_confirm_checkbox').on('click', function(){	
		if ($('#econsent_confirm_checkbox').prop('checked')) {
			$('#form button[name=\"submit-btn-saverecord\"]').button('enable');
			$('#econsent_confirm_checkbox_div').removeClass('yellow').addClass('green');
		} else {
			$('#form button[name=\"submit-btn-saverecord\"]').button('disable');
			$('#econsent_confirm_checkbox_div').removeClass('green').addClass('yellow');
		}
	});
	showProgress(1,0);
	$('.inline-pdf-viewer:first iframe').attr('onload','showProgress(0,0)');
	setTimeout(function(){
		$('#econsent_confirm_checkbox').prop('disabled',false);
		$('#econsent_confirm_checkbox_label').removeClass('opacity50');
	},1000);
	// The "working" progress meter should hide when the pdf loads, but just in case, remove it after 30s.
	setTimeout('showProgress(0,0)', 30000); // If inline PDF is not displayed, then display page immediately
});

function resetSignatureValuesPrep() {
    var ob = $('#form button[name=\"submit-btn-saveprevpage\"]');
    ob.attr('onclick','return false;').on('click', function(){
        simpleDialog(null,null,'resetSignatureValuesDialog',600,null,window.lang.global_53,'resetSignatureValues();',window.lang.survey_1266);
	});
}

function resetSignatureValues() {
    $('#form').attr('action', $('#form').attr('action')+'&__es=1');
    var prevPageBtn = $('#form button[name=\"submit-btn-saveprevpage\"]');
    prevPageBtn.button("disable");
    dataEntrySubmit(prevPageBtn);
}