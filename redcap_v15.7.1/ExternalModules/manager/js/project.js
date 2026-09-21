$(function() {

	 var reloadPage = function(){
		  $('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
		 window.location.reload();
	 }

	$('.external-modules-disable-button').click(function (event) {	
		var button = $(event.target);
		var row = button.closest('tr');
		var module = row.data('module');
		var version = row.data('version');
		$('#external-modules-disable-confirm-modal').modal('show');
		$('#external-modules-disable-confirm-module-name').html(module);
		$('#external-modules-disable-confirm-module-version').html(version);
	});
		
	$('#external-modules-disable-button-confirmed').click(function (event) {
		var button = $(event.target);
		button.attr('disabled', true);
		var module = $('#external-modules-disable-confirm-module-name').text();
		$.post('ajax/disable-module.php?pid=' + ExternalModules.PID, { module: module }, function(data){
		   if (data == 'success') {
				reloadPage();
		   }
		   else {
				//= An error occurred while enabling the module:
				var message = ExternalModules.$lang.tt('em_manage_69')+' '+data;
				console.log('AJAX request error while enabling a module:', data); // The intent is to have the data object logged to the console, and not the message?
				alert(message);
		   }
		});
	});

	$('.external-module-activation-request .enable-button').click(function(event) {
		var row = $(event.target).closest('tr');
		var prefix = row.data('module');
		var version = row.data('version');
		ExternalModules.adminActivateModule(prefix, version, getParameterByName('request_id'));
	});

	// To-Do List: Load activation request in a dialog
	if (inIframe() && super_user && getParameterByName('request_id') != '' && getParameterByName('prefix') != '') {
		simpleDialog(null,null,'external-module-activation-request-dialog',600,null,'Cancel',"ExternalModules.adminActivateModule('"+getParameterByName('prefix')+"', '"+$('#external-module-version').val()+"', '"+getParameterByName('request_id')+"');","Enable");
	}
});

ExternalModules.adminActivateModule = function(prefix, version, request_id){
	$('.external-module-activation-request .enable-button').prop('disabled', true);
	
	const onError = function (message) {
		//= An error occurred while enabling the module:
		console.log('AJAX Request Error:', message);
		alert('ERROR: '+message);
	}

	const onSuccess = () => {
		if (inIframe()) {
			closeToDoListFrame();
		} else {
			simpleDialog("The external module has been successfully enabled for the project", "SUCCESS", null, null, function() {
				window.location.href = 'project.php?pid='+pid;
			});
		}
	}

	ExternalModules.enableModule(prefix, version, false, request_id, onSuccess, onError)
}
