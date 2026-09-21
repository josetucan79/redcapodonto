var bgImportTable;
$(function(){
	bgImportTable = $('#background-import-table').DataTable({
		processing: true,
		pageLength: 25,
		lengthMenu: [
			[10, 25, 50, 100, 500, -1],
			[10, 25, 50, 100, 500, 'All'],
		],
		ajax: {url: app_path_webroot+'index.php?route=DataImportController:loadBackgroundImportsTable&pid='+pid, type: 'POST'},
		columns: [
			{data: "status", title: '<i class="fa-regular fa-square-check"></i>&nbsp;'+lang.data_import_tool_346, className: 'lh-1 dt-body-center dt-head-center'},
			{data: { _: "upload_time.display", sort: "upload_time.sort" }, title: '<i class="fa-regular fa-clock"></i>&nbsp;'+lang.data_import_tool_347, className: 'nowrap'},
			{data: { _: "completed_time.display", sort: "completed_time.sort" }, title: '<i class="fa-regular fa-clock"></i>&nbsp;'+lang.data_import_tool_348, className: 'nowrap'},
			{data: "filename", title: '<div class="nowrap"><i class="fa-regular fa-file"></i>&nbsp;'+lang.data_import_tool_349+'</div>', className: 'lh-1'},
			{data: "username", title: '<div class="nowrap"><i class="fa-regular fa-user"></i>&nbsp;'+lang.data_import_tool_350+'</div>', className: 'lh-1'},
			{data: { _: "records_provided.display", sort: "records_provided.sort" }, type: "num", title: lang.data_import_tool_351, className: 'lh-1 dt-body-center dt-head-center'},
			{data: { _: "records_imported.display", sort: "records_imported.sort" }, type: "num", title: lang.data_import_tool_352, className: 'lh-1 dt-body-center dt-head-center'},
			{data: { _: "total_processing_time.display", sort: "total_processing_time.sort" }, type: "num", title: lang.data_import_tool_353, className: 'dt-body-center dt-head-center lh-1'},
			{data: { _: "total_errors.display", sort: "total_errors.sort" }, type: "num", title: '<div class="nowrap"><i class="fa-solid fa-circle-exclamation"></i>&nbsp;'+lang.data_import_tool_354+'</div>', className: 'dt-body-center dt-head-center'}
		],
		aaSorting: [],
		fixedHeader: {header: true, footer: false},
		oLanguage: {"sSearch": ""},
		language: {"emptyTable": '<div class="my-3 text-secondary fs14"><i class="fa-regular fa-folder-open"></i> No imports</div>'}
	});
	$('.dataTables_filter input[type=search]').attr('placeholder',lang.email_users_112).after('<button onclick="reloadBgImportTable();" class="btn btn-xs btn-light fs13 ms-3" style="position:relative;top:-2px;"><i class="fa-solid fa-rotate-right"></i> '+lang.data_import_tool_374+'</button>');
	if (getParameterByName('async_success') != '') {
		simpleDialog(null, null, 'async_success_dialog', 600);
		modifyURL(removeParameterFromURL(window.location.href, 'async_success'));
	}
	// if (getParameterByName('import_id') != '') {
	// 	$('#import_id_'+getParameterByName('import_id')).parent().parent().find('td').effect('highlight',{},3000);
	// }
	var newUrl = removeParameterFromURL(window.location.href, 'import_id')+'&import_id=';
	modifyURL(newUrl);
	$('#view-bg-import-tab').parent().attr('href', newUrl);
});

function reloadBgImportTable()
{
	bgImportTable.ajax.reload();
}

function viewBgImportDetails(import_id)
{
	$.get(app_path_webroot+'index.php?route=DataImportController:viewBackgroundImportDetails&pid='+pid+'&import_id='+import_id, {}, function(data){
		simpleDialog(data, lang.data_import_tool_357, 'view-details-dialog', 600);
	});
}

function cancelBgImport(import_id)
{
	$.post(app_path_webroot+'index.php?route=DataImportController:cancelBackgroundImport&pid='+pid, { import_id: import_id, action: 'view'}, function(data){
		// If user is not the uploader, prevent them from doing anything
		if (data == '2') {
			simpleDialog('<div class="text-dangerrc fs14"><i class="fa-solid fa-circle-exclamation"></i> '+lang.data_import_tool_369+'</div>');
			return;
		}
		if (data == '1') {
			simpleDialog(lang.data_import_tool_392, lang.data_import_tool_391, 'cancel-import-dialog', 600, null, lang.data_import_tool_366, function () {
				// Cancel it
				$.post(app_path_webroot + 'index.php?route=DataImportController:cancelBackgroundImport&pid=' + pid, {
					import_id: import_id,
					action: 'save'
				}, function (data) {
					if (data == '1') {
						reloadBgImportTable();
						simpleDialog('<div class="text-successrc fs14"><i class="fa-solid fa-check"></i> ' + lang.data_import_tool_368 + '</div>', lang.global_79);
					} else {
						alert(woops);
					}
				});
			}, lang.data_import_tool_393);
		} else {
			alert(woops);
		}
	});
}