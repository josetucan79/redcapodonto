var uploadFiles = new Array();
var fileRepoTable;

$(function()
{
	// Load the files in the current directory
	loadFileRepoTable(getParameterByName('folder_id'), getParameterByName('type'), getParameterByName('recycle_bin'));
});

// Init file uploading
function initUploading()
{
	$('#file-repository-file-input').unbind('change');
	$('#file-repository-file-input').change(function(){
		// Loop through each file dropped
		for (var i=0; i < $(this)[0].files.length; i++) {
			uploadFiles.push($(this)[0].files[i]);
		}
		// Reset file input value to blank to prevent duplicates later
		$(this).val('');
		// Start uploading, beginning with the first in the array
		if (uploadFiles.length > 0) {
			uploadToFileRepository(uploadFiles.shift());
		}
	});
}

// Edit file comment
function fileRepoEditComment(doc_id)
{
	if (!isinteger(doc_id)) return;
	var filename = $('#file-download-'+doc_id).text();
	var comment_id = 'frc-'+doc_id;
	var comment = br2nl($('#'+comment_id).html());
	comment = comment.replace(/&nbsp;/g, '').trim();
	simpleDialog('<div class="fs14">'+lang.docs_1142+' "<b>'+htmlspecialchars(filename)+'</b>"'+lang.docs_1143+'</div><div class="mt-3">'+lang.docs_1141+'</div><div class="mt-1"><textarea id="fr-new-comment" class="x-form-field notesbox">'+comment+'</textarea></div>',
		lang.docs_1139, null, 380, null, lang.global_53, function(){
		comment = $('#fr-new-comment').val();
		$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:editComment', { doc_id: doc_id, comment: $('#fr-new-comment').val() }, function(data){
			if (data == '' || data == '0') {
				alert(woops);
				return;
			}
			if (comment == '') comment = '&nbsp;&nbsp;&nbsp;&nbsp;';
			$('#'+comment_id).text(comment);
			$('#'+comment_id).effect('highlight',{},3000);
		})
	}, lang.folders_11);
}

// Delete file permanently (admins only)
function fileRepoDeleteNow(doc_id)
{
	if (!isinteger(doc_id)) return;
	var filename = $('#file-download-'+doc_id).text();
	simpleDialog('<div class="fs14">'+lang.docs_46+'<div class="fs14 text-dangerrc my-3"><i class="fa-solid fa-triangle-exclamation"></i> '+lang.docs_1145+'</div><div class="mt-3">'+lang.sendit_32+' <code class="fs15 boldish">'+filename+'</code></div></div>',
		lang.form_renderer_52, null, 450, null, lang.global_53, function(){
		$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:deleteNow', { delete: doc_id }, function(data){
			if (data == '' || data == '0') {
				alert(woops);
				return;
			}
			Swal.fire(
				lang.docs_1146,
				'',
				'success'
			);
			reloadFileRepoTable();
		});
	}, lang.global_19);
}

// Download file
function fileRepoDownload(doc_id, param_name)
{
	if (!isinteger(doc_id)) return;
	if (typeof param_name == 'undefined') param_name = 'id';
	window.location.href = app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:download&'+param_name+'='+doc_id;
}

// Delete FILE w/ confirmation
function fileRepoDelete(doc_id)
{
	var filename = $('#file-download-'+doc_id).text();
	simpleDialog('<div class="fs14">'+lang.docs_46+'<div class="mt-3 boldish">'+lang.docs_1148+'</div><div class="mt-3">'+lang.sendit_32+' <code class="fs15 boldish">'+htmlspecialchars(filename)+'</code></div></div>', lang.form_renderer_52, null, null, null, lang.global_53, "fileRepoDeleteDo("+doc_id+");", lang.global_19);
}
function fileRepoDeleteDo(doc_id)
{
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:delete', { delete: doc_id },function(data){
		if (data == '0') {
			alert(woops);
			return;
		}
		reloadFileRepoTable();
	});
}

// Download MULTIPLE FILES in a zip
function downloadMultiple()
{
	var ids=[], folders=[], i=0, thisid;
	// Make sure something is checked
	if (!$('input.folder-select[type=checkbox]:checked, input.file-select[type=checkbox]:checked').length) {
		simpleDialog('<div class="fs14">'+lang.docs_1127+'</div>');
		return;
	}
	// Get ids of the files/folders
	$('input.folder-select[type=checkbox]:checked').each(function(){
		thisid = $(this).prop('id').replace('folder-select-','');
		if (isinteger(thisid)) folders[i++] = thisid;
	});
	i = 0;
	$('input.file-select[type=checkbox]:checked').each(function(){
		thisid = $(this).prop('id').replace('file-select-','');
		if (isinteger(thisid)) ids[i++] = thisid;
	});
	window.location.href = app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:downloadMultiple&docs='+ids.join(',')+'&folders='+folders.join(',')+'&current_folder='+getParameterByName('folder_id');
}

// Move MULTIPLE FILES w/ confirmation
function moveMultiple()
{
	var ids=[], folders=[], i=0, thisid;
	// Make sure something is checked
	if (!$('input.folder-select[type=checkbox]:checked, input.file-select[type=checkbox]:checked').length) {
		simpleDialog('<div class="fs14">'+lang.docs_1127+'</div>');
		return;
	}
	// Get ids of the files/folders
	$('input.folder-select[type=checkbox]:checked').each(function(){
		thisid = $(this).prop('id').replace('folder-select-','');
		if (isinteger(thisid)) folders[i++] = thisid;
	});
	i = 0;
	$('input.file-select[type=checkbox]:checked').each(function(){
		thisid = $(this).prop('id').replace('file-select-','');
		if (isinteger(thisid)) ids[i++] = thisid;
	});
	showProgress(1);
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:getFolderDropdown', { current_folder: getParameterByName('folder_id') },function(data){
		showProgress(0,0);
		if (data == '0' || data == '') {
			alert(woops);
			return;
		}
		simpleDialog('<div class="fs14">'+lang.docs_1130+'<div class="mt-3 mb-2"><b class="me-1 nowrap">'+lang.docs_1132+"</b> "+data+'</div></div>', lang.docs_1128, null, 650, null, lang.global_53, "moveMultipleDo('"+ids.join(',')+"','"+folders.join(',')+"');", lang.docs_1129);
	});
}
function moveMultipleDo(docs, folders)
{
	var new_folder = $('#filerepo-folder-list').val();
	if (new_folder == getParameterByName('folder_id')) return;
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:move', { folders: folders, docs: docs, new_folder: new_folder, current_folder: getParameterByName('folder_id') },function(data){
		if (data == '0' || data == '') {
			alert(woops);
			return;
		}
		simpleDialog('<div class="fs14">'+lang.docs_1133+'</div>', lang.global_79);
		reloadFileRepoTable();
	});
}

// Delete MULTIPLE FILES w/ confirmation
function deleteMultiple()
{
	var ids = [], i=0, thisid;
	// Folders can't be deleted this way, so return error, if some folders are checked
	if ($('input.folder-select[type=checkbox]:checked').length) {
		$('input.folder-select[type=checkbox]:checked').prop('checked',false);
		simpleDialog('<div class="fs14">'+lang.docs_1117+'</div>');
		return;
	}
	// Get ids of the files
	$('input.file-select[type=checkbox]:checked').each(function(){
		thisid = $(this).prop('id').replace('file-select-','');
		if (isinteger(thisid)) ids[i++] = thisid;
	});
	if (!ids.length) {
		simpleDialog('<div class="fs14">'+lang.docs_1120+'</div>');
		return;
	}
	simpleDialog('<div class="fs14">'+lang.docs_1119+' '+ids.length+lang.period+'</div>', lang.docs_1118, null, null, null, lang.global_53, "deleteMultipleDo('"+ids.join(',')+"');", lang.global_19);
}
function deleteMultipleDo(ids)
{
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:deleteMultiple', { delete: ids },function(data){
		if (data == '0' || data == '') {
			alert(woops);
			return;
		}
		simpleDialog('<div class="fs14">'+lang.docs_1123+'</div>', lang.global_79);
		reloadFileRepoTable();
	});
}



// Restore FILE w/ confirmation
function fileRepoRestore(doc_id)
{
	var filename = $('#file-download-'+doc_id).text();
	simpleDialog('<div class="fs14">'+lang.docs_1090+'<div class="mt-3">'+lang.sendit_32+' <code class="fs15 boldish">'+filename+'</code></div><div class="mt-4 fs12 text-secondary">'+lang.docs_1093+'</div></div>', lang.docs_1088, null, null, null, lang.global_53, "fileRepoRestoreDo("+doc_id+");", lang.docs_1089);
}
function fileRepoRestoreDo(doc_id)
{
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:restore', { doc_id: doc_id },function(data){
		if (data == '0') {
			alert(woops);
			return;
		} else if (data == '1') {
			simpleDialog(lang.docs_1091, lang.global_79);
			reloadFileRepoTable();
		} else {
			simpleDialog(data, lang.global_01);
		}
	});
}

// Delete file FOLDER w/ confirmation
function fileRepoDeleteFolder(folder_id, file_count)
{
	var folderName = $('#file-folder-'+folder_id).text();
	if (file_count > 0) {
		simpleDialog('<div class="fs14">'+lang.docs_100+'<div class="mt-3">'+lang.docs_99+' <code class="fs15 boldish">'+htmlspecialchars(folderName)+'</code></div></div>', lang.docs_101, null, 550);
	} else {
		// Error: Cannot delete folder that still has files in it
		simpleDialog('<div class="fs14">'+lang.docs_98+'<div class="mt-3">'+lang.docs_99+' <code class="fs15 boldish">'+htmlspecialchars(folderName)+'</code></div></div>', lang.docs_97, null, 500, null, lang.global_53, "fileRepoDeleteFolderDo("+folder_id+");", lang.global_19);
	}
}
function fileRepoDeleteFolderDo(folder_id)
{
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:deleteFolder', { delete: folder_id },function(data){
		if (data == '0') {
			alert(woops);
			return;
		}
		reloadFileRepoTable();
	});
}

// Rename file
function fileRepoRename(doc_id)
{
	var filename = $('#file-download-'+doc_id).text().trim();
	simpleDialog('<div class="fs14">'+lang.docs_87+' <code class="fs15 nowrap">'+htmlspecialchars(filename)+'</code>'+lang.questionmark+'<div class="mt-4 mb-2">'+lang.docs_88+'<input id="file-rename-input" type="text" class="ms-2 x-form-text x-form-field fs14" style="width:400px;" value="'+htmlspecialchars(filename)+'"></div></div>', lang.docs_86, null, 600, null, lang.global_53, "fileRepoRenameDo("+doc_id+");", lang.docs_86);
}
function fileRepoRenameDo(doc_id)
{
	var filename = $('#file-rename-input').val().trim();
	// If the filename was not changed, then do nothing
	if (filename == $('#file-download-'+doc_id).text().trim()) return;
	// Rename via AJAX
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:rename', { doc_id: doc_id, folder_id: getParameterByName('folder_id'), name: filename },function(data){
		if (data == '0') {
			alert(woops);
			return;
		} else if (data == '2') {
			simpleDialog(lang.docs_1125, '"'+htmlspecialchars(filename)+'" '+lang.docs_1126, null, null, "fileRepoRename("+doc_id+");", );
			return;
		} else if (data == '3') {
			simpleDialog(lang.docs_1153+' <b>'+getfileextension(htmlspecialchars(filename))+'</b>'+lang.period, lang.docs_1152, null, null, "fileRepoRename("+doc_id+");", );
			return;
		}
		reloadFileRepoTable();
	});
}

// Rename folder
function fileRepoFolderRename(folder_id)
{
	var foldername = $('#file-folder-'+folder_id).text();
	simpleDialog('<div class="fs14">'+lang.docs_104+' <code class="fs15 nowrap">'+htmlspecialchars(foldername)+'</code>'+lang.questionmark+'<div class="mt-4 mb-2">'+lang.docs_103+'<input id="folder-rename-input" type="text" class="ms-2 x-form-text x-form-field fs14" maxlength="150" style="width:400px;" value="'+htmlspecialchars(foldername)+'"></div></div>', lang.docs_102, null, 600, null, lang.global_53, "fileRepoFolderRenameDo("+folder_id+");", lang.docs_102);
}
function fileRepoFolderRenameDo(folder_id)
{
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:renameFolder', { folder_id: folder_id, name: $('#folder-rename-input').val() },function(data){
		if (data == '1') {
			reloadFileRepoTable();
		} else if (data == '0') {
			alert(woops);
		} else {
			simpleDialog(data, lang.global_01, null, null, "fileRepoFolderRename("+folder_id+");");
		}
	});
}

// Init the file/folder "select all" checkbox in the table header
function initFileSelectAllCheckbox()
{
	// Check all checkbox
	var ob = $('input.file-select-all');
	ob.unbind('click');
	ob.click(function(){
		// Check all the checkboxes
		var checkAll = ($('.file-select, .folder-select').length != $('.file-select:checked, .folder-select:checked').length);
		var ob2 = $('input.file-select:visible, input.folder-select:visible');
		ob2.prop('checked', checkAll);
		if (checkAll) {
			ob2.removeClass('opacity50');
			// Enable move/delete/download buttons
			enableFileRepoExtraBtns(true);
		} else {
			ob2.addClass('opacity50');
			// Disable move/delete/download buttons
			enableFileRepoExtraBtns(false);
		}
	});
	// Opacity effect on file-select checkboxes
	var ob2 = $('#file-repository-table tbody tr');
	ob2.unbind('mouseenter');
	ob2.mouseenter(function() {
		$(this).find(".file-select, .folder-select").removeClass('opacity50');
	}).mouseleave(function() {
		$(this).find(".file-select:not(:checked), .folder-select:not(:checked)").addClass('opacity50');
	});
	// Init click for row-level checkboxes
	var ob3 = $('.file-select, .folder-select');
	ob3.unbind('click');
	ob3.click(function(data){
		// If any checkboxes are checked, then enable move/delete/download buttons
		enableFileRepoExtraBtns( ($('.file-select:checked, .folder-select:checked').length > 0) );
	});
}

// Enable/disable the extra buttons (delete/move/download files)
function enableFileRepoExtraBtns(enable)
{
	if (enable) {
		$('#extra-btns').removeClass('opacity50').find('button').prop('disabled',false);
	} else {
		$('#extra-btns').addClass('opacity50').find('button').prop('disabled',true);
	}
}

// Init rename icon
function initFileRenameIcon()
{
	var ob = $('#file-repository-table tbody tr').find('td:eq(1)');
	ob.unbind('mouseenter');
	// Show "rename file" pencil during hover of filename
	ob.mouseenter(function() {
		$(this).find(".file-rename").show();
		$(this).find(".folder-rename").removeClass('invisible');
	}).mouseleave(function() {
		$(this).find(".file-rename").hide();
		$(this).find(".folder-rename").addClass('invisible');
	});
}

// Get table column defs by type
function getTableColumnsByType(type, recycle_bin)
{
	var columns;
	if (recycle_bin == '1') {
		// Columns: Recycle Bin
		columns = [
			{data: "0", title: lang.docs_77, width: '50%'},
			{data: { _: "1.display", sort: "1.sort" }, type: "num", title: lang.docs_78},
			{data: { _: "2.display", sort: "2.sort" }, type: "num", title: lang.docs_79},
			{data: "3", title: lang.docs_80},
			{data: { _: "4.display", sort: "4.sort" }, type: "num", title: lang.docs_1094, className: 'dt-body-center dt-head-center'},
			{data: { _: "5.display", sort: "5.sort" }, type: "num", title: "<div class='fs11 font-weight-normal' style='line-height:1;'>"+lang.docs_1095+"</div>", className: 'dt-body-center dt-head-center'},
			{data: "6", title: "<div class='font-weight-normal text-successrc' style='line-height:1.1;'>"+lang.docs_1096+"</div>"+(super_user_not_impersonator ? "<div class='font-weight-normal text-secondary my-1'>- "+lang.global_47+"- </div><div class='font-weight-normal text-dangerrc' style='line-height:1.1;'>"+lang.docs_72+"</div>" : ""), className: 'dt-body-center dt-head-center', orderable: false}
		];
	} else if (type == 'export') {
		// Columns: Export Files
		columns = [
			{data: "0", title: "", orderable: false},
			{data: "1", title: lang.docs_77, width: '60%'},
			{data: { _: "2.display", sort: "2.sort" }, type: "num", title: lang.docs_78},
			{data: { _: "3.display", sort: "3.sort" }, type: "num", title: lang.docs_105},
			{data: "4", title: lang.docs_80},
			{data: "5", title: "", className: 'dt-body-right', orderable: false}
		];
	} else if (type == 'pdf_archive') {
		// Columns: eConsent PDFs
		if (pdf_econsent_system_ip) {
			columns = [
				{data: "0", title: "", orderable: false},
				{data: "1", title: lang.docs_77},
				{data: "2", title: '<div class="lineheight10 fs11">'+lang.econsent_181+'</div>', className: 'dt-body-center dt-head-center wrap'},
				{data: "3", title: lang.global_49},
				{data: "4", title: lang.survey_1586},
				{data: { _: "5.display", sort: "5.sort" }, type: "num", title: lang.survey_1585},
				{data: "6", title: lang.survey_1172},
				{data: "7", title: lang.survey_1221}, // IP address
				{data: "8", title: lang.survey_1173},
				{data: "9", title: lang.survey_1174},
				{data: { _: "10.display", sort: "10.sort" }, type: "num", title: lang.docs_78}
			];
		} else {
			columns = [
				{data: "0", title: "", orderable: false},
				{data: "1", title: lang.docs_77},
				{data: "2", title: '<div class="lineheight10 fs11">'+lang.econsent_181+'</div>', className: 'dt-body-center dt-head-center wrap'},
				{data: "3", title: lang.global_49},
				{data: "4", title: lang.survey_1586},
				{data: { _: "5.display", sort: "5.sort" }, type: "num", title: lang.survey_1585},
				{data: "6", title: lang.survey_1172},
				{data: "7", title: lang.survey_1173},
				{data: "8", title: lang.survey_1174},
				{data: { _: "9.display", sort: "9.sort" }, type: "num", title: lang.docs_78}
			];
		}
	} else if (type == 'record_lock_pdf_archive') {
		// Columns: Record-locking PDFs
		columns = [
			{data: "0", title: "", orderable: false},
			{data: "1", title: lang.docs_77, width: '40%'},
			{data: "2", title: lang.global_49},
			{data: { _: "3.display", sort: "3.sort" }, type: "num", title: lang.data_entry_491},
			{data: { _: "4.display", sort: "4.sort" }, type: "num", title: lang.docs_78}
		];
	} else {
		// Columns: User Files
		columns = [
			{data: "0", title: "<input type='checkbox' class='file-select-all'>", className: 'dt-body-center dt-head-center', orderable: false},
			{data: "1", title: lang.docs_77, width: '55%'},
			{data: { _: "2.display", sort: "2.sort" }, type: "num", title: lang.docs_78},
			{data: { _: "3.display", sort: "3.sort" }, type: "num", title: lang.docs_79},
			{data: "4", title: lang.docs_80},
			{data: "5", title: "<div class='font-weight-normal text-secondary'>"+lang.design_174+"</div>", className: 'dt-body-center dt-head-center', orderable: false},
			{data: "6", title: "<div class='font-weight-normal text-secondary'>"+lang.global_19+"</div>", className: 'dt-body-center dt-head-center', orderable: false},
			{data: "7", title: "<code class='d-block font-weight-normal text-secondary fs10' style='line-height:1.1;'>doc_id / folder_id</code>", className: 'dt-body-center dt-head-center', orderable: false}
		];
	}
	return columns;
}

// Load the files table
function loadFileRepoTable(folder_id, type, recycle_bin)
{
	if (typeof folder_id == 'undefined') folder_id = '';
	if (typeof type == 'undefined') type = '';
	if (typeof recycle_bin == 'undefined' || !isinteger(recycle_bin)) recycle_bin = '0';
	var typeChanged = (type != getParameterByName('type') || recycle_bin != getParameterByName('recycle_bin'));
	// Set URLs if folder_id or type are defined
	var ajaxUrl = app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:getFileList';
	var currentURL = window.location.href;
	currentURL = removeParameterFromURL(currentURL, 'folder_id');
	currentURL = removeParameterFromURL(currentURL, 'type');
	currentURL = removeParameterFromURL(currentURL, 'recycle_bin');
	if (folder_id != '') {
		currentURL += "&folder_id="+folder_id;
		ajaxUrl += "&folder_id="+folder_id;
	}
	if (type != '') {
		currentURL += "&type="+type;
		ajaxUrl += "&type="+type;
	}
	if (recycle_bin == '1') {
		currentURL += "&recycle_bin="+1;
		ajaxUrl += "&recycle_bin="+1;
	}
	modifyURL(currentURL);
	// DataTable
	if (typeChanged || fileRepoTable == null) { // If we're changing "type", then redo DataTable call because of the column changes, etc.
		try {
			fileRepoTable.destroy();
			fileRepoTable = null;
		} catch (e) {
			fileRepoTable = null;
		}
		$('#file-repository-table').html('');
		fileRepoTable = $('#file-repository-table').DataTable({
			processing: true,
			pageLength: 25,
			lengthMenu: [
				[10, 25, 50, 100, 500, -1],
				[10, 25, 50, 100, 500, 'All'],
			],
			ajax: {url: ajaxUrl, type: 'POST'},
			columns: getTableColumnsByType(type, recycle_bin),
			aaSorting: [],
			fixedHeader: {header: true, footer: false},
			drawCallback: function (settings) {
				initUploading();
				getBreadcrumbs(getParameterByName('folder_id'), getParameterByName('type'), getParameterByName('recycle_bin'));
				initFileRenameIcon();
				initFileSelectAllCheckbox();
			},
			oLanguage: {"sSearch": ""},
			language: {"emptyTable": '<div class="my-3 text-secondary fs14"><i class="fa-regular fa-folder-open"></i> ' + lang.docs_93 + '</div>'}
		});
		// Add drag and drop area
		$('#file-repository-drop-area-parent').remove();
		var dropAreaHtml = "<div id=\"file-repository-drop-area-parent\">" +
			"				<div id=\"file-repository-drop-area\" class='"+(type==''?'':'hide')+"'>" +
			"                <div id=\"file-repository-drop-message\">" +
			"                    <i class=\"fa-solid fa-cloud-arrow-up fs16 me-1\"></i>" +
			"                    <span id=\"file-repository-drop-message-text\">"+lang.docs_83+"</span>" +
			"                </div>" +
			"            	 <input id=\"file-repository-file-input\" class=\"file-repository-file-input\" type=\"file\" multiple>" +
			"        		</div>" +
			"				<div id='file-repository-drop-area-btns'>" +
			"					<button class='btn btn-xs fs14 btn-primaryrc "+(type==''?'':'hide')+"' onclick=\"$('#file-repository-file-input').trigger('click');\"><i class=\"fa-solid fa-upload\"></i> Select files to upload</button>" +
			"					<button class='btn btn-xs fs14 btn-rcgreen ms-2 "+(type==''?'':'hide')+"' onclick=\"createFolder();\"><i class=\"fa-solid fa-folder-plus\"></i> "+lang.docs_95+"</button>" +
			"					<span id='extra-btns' class='opacity50'>" +
			"						<button class='btn btn-xs fs14 btn-defaultrc ms-2 "+(type==''?'':'hide')+"' disabled onclick=\"downloadMultiple();\"><i class=\"fa-solid fa-download\"></i> "+lang.api_46+"</button>" +
			"						<button class='btn btn-xs fs14 btn-defaultrc ms-2 "+(type==''?'':'hide')+"' disabled onclick=\"deleteMultiple();\"><i class=\"fa-regular fa-trash-can\"></i> "+lang.design_170+"</button>" +
			"						<button class='btn btn-xs fs14 btn-defaultrc ms-2 "+(type==''?'':'hide')+"' disabled onclick=\"moveMultiple();\"><i class=\"fa-solid fa-arrows-up-down-left-right\"></i> "+lang.design_172+"</button>" +
			"					</span>" +
			"				</div>" +
			"			</div>"
		dropAreaHtml += outputCurrentStorageUsage(true);
		$('.dataTables_length').before(dropAreaHtml);
		if (file_repository_enabled == '0') {
			$('#file-repository-drop-area, #file-repository-drop-area-btns button, #file-repository-space-usage-display').remove();
		}
		// Customize search box
		$('.dataTables_filter input[type=search]').attr('placeholder',lang.docs_94);
		$('#file-repository-drop-area-btns').append( $('.dataTables_filter:first').detach() );
		$('#file-repository-drop-area-btns').append( $('.dataTables_length:first').detach() );
		// Breadcrumb links
		var breadCrumbHtml = "<div id='ItemListBreadcrumbParent' "+(isNumeric(maxStorageSizeFileRepository) ? "style='padding-top:0;'" : "")+">" +
			"<div class='ItemListBreadcrumb'><a href='javascript:;' onclick=\"loadFileRepoTable('','',0);\" class='ItemListBreadcrumb-link'>"+lang.docs_92+"</a></div>" +
			"<div class='ItemListBreadcrumbs'></div>" +
			"</div>";
		$('#file-repository-table').before(breadCrumbHtml);
		// Hide drag-n-drop area for the special folders
		if (type != '' || recycle_bin == '1') {
			$('#file-repository-drop-area, #file-repository-drop-area-btns button, input.file-select-all').addClass('hide');
		} else {
			$('#file-repository-drop-area, #file-repository-drop-area-btns button, input.file-select-all').removeClass('hide');
		}

	} else {
		fileRepoTable.ajax.url(ajaxUrl).load();
		resetFileRepoButtonsCheckboxes();
	}
	$('[data-toggle="popover"]').hover(function(e) {
		// Show popup
		popover = new bootstrap.Popover(e.target, {
			html: true,
			content: $(this).data('content')
		});

		popover.show();
	}, function() {
		// Hide popup
		bootstrap.Popover.getOrCreateInstance(this).dispose();
	});
	if (type == 'attachments') displayFileAttachmentFolderWarning();
}


// Display dialog when viewing Misc File Attachments to warn users about deleting files
function displayFileAttachmentFolderWarning()
{
	simpleDialog('<div class="fs14">'+lang.docs_1149+'</div>', lang.global_03, null, 600, null, lang.docs_1150);
}

// Reset all the button and checkbox states when changing folders (when folder is clicked)
function resetFileRepoButtonsCheckboxes() {
	$('input.file-select-all').prop('checked',false);
	enableFileRepoExtraBtns(false);
}

// Output the HTML div for the max storage and current usage
function outputCurrentStorageUsage(outputParentDiv) {
	if (typeof outputParentDiv == 'undefined') outputParentDiv = true;
	var popover = "<i class=\"fa-solid fa-circle-info ms-2\" data-toggle=\"popover\" data-trigger=\"hover\" data-content=\"" + htmlspecialchars(lang.docs_1122) + "\"></i>";
	var loadingImg = '<div id="file-repository-space-usage-loading"><img src="' + app_path_images + 'loader.gif"><div>'+lang.docs_1147+'</div></div>';
	var content = loadingImg;
	if (isNumeric(maxStorageSizeFileRepository)) {
		var percent = round(currentStorageSizeFileRepository/maxStorageSizeFileRepository*100,1);
		if (percent > 100) percent = 100;
		content += "<div id=\"file-repository-space-usage-display\" "+(percent > 90 ? "class='text-danger'" : "")+">"
				+ round(currentStorageSizeFileRepository / 1024 / 1024, 1) + " MB " + lang.survey_133 + " "
				+ round(maxStorageSizeFileRepository / 1024 / 1024, 1) + " MB " + lang.docs_1121 + " ("+percent+"%)" + popover + "</div>";
	} else {
		content += "<div id=\"file-repository-space-usage-display\">" + round(currentStorageSizeFileRepository / 1024 / 1024, 1) + " MB " + lang.docs_1121 + popover + "</div>";
	}
	if (outputParentDiv) {
		content = "<div id='file-repository-space-usage-display-parent'>"+content+"</div>";
	}
	return content;
}

// Refresh the current usage JS var and UI
function refreshCurrentStorageUsage() {
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:getCurrentUsage',{},function(data) {
		if (!isNumeric(data)) return;
		currentStorageSizeFileRepository = data;
		$('#file-repository-space-usage-display-parent').html( outputCurrentStorageUsage(false) );
		$('[data-toggle="popover"]').hover(function(e) {
			// Show popup
			popover = new bootstrap.Popover(e.target, {
				html: true,
				content: $(this).data('content')
			});
			popover.show();
		}, function() {
			// Hide popup
			bootstrap.Popover.getOrCreateInstance(this).dispose();
		});
		$('#file-repository-space-usage-loading').css('visibility', (uploadFiles.length > 0 ? 'visible' : 'hidden'));
	});
}

// Refresh the files table
function reloadFileRepoTable()
{
	fileRepoTable.ajax.url(window.location.href.replace('FileRepositoryController:index','FileRepositoryController:getFileList')).load(null, false);
	if (getParameterByName('type') == '') {
		$('#file-repository-drop-area-parent').removeClass('hide');
	} else {
		$('#file-repository-drop-area-parent').addClass('hide');
	}
	resetFileRepoButtonsCheckboxes();
	refreshCurrentStorageUsage();
}

// Open dialog to create new folder
function createFolder()
{
	var currentFolderDagRestricted = ($('.ItemListBreadcrumbs .Breadcrumb-Dag-Restriction').length > 0);
	var currentFolderRoleRestricted = ($('.ItemListBreadcrumbs .Breadcrumb-Role-Restriction').length > 0);
	var currentFolderAdminRestricted = ($('.ItemListBreadcrumbs .Breadcrumb-Admin-Restriction').length > 0);
	simpleDialog('<div class="fs14">'+lang.docs_106+' <div class="mt-4 mb-2"><b>'+lang.docs_107+'</b><input id="new-folder-name" maxlength=150 type="text" class="ms-2 x-form-text x-form-field fs14" style="width:300px;"></div>'
				+ ((dagCount > 0 && !currentFolderDagRestricted && !currentFolderAdminRestricted) ? '<div class="ms-2 mt-4 fs14"><div class="boldish text-successrc"><i class="fas fa-users me-1"></i>'+lang.docs_1082+'</div><div class="mt-1">'+lang.docs_1084+' '+dagDropdown+'</div></div>' : '')
				+ ((roleCount > 0 && !currentFolderRoleRestricted && !currentFolderAdminRestricted) ? '<div class="ms-2 mt-4 fs14"><div class="boldish text-primaryrc"><i class="fas fa-user-tag me-1"></i>'+lang.docs_1086+'</div><div class="mt-1">'+lang.docs_1084+' '+roleDropdown+'</div></div>' : '')
				+ (super_user_not_impersonator && !currentFolderAdminRestricted ? '<div class="ms-2 mt-4 fs14"><div class="boldish text-dangerrc"><i class="fas fa-user-shield me-1"></i>'+lang.docs_1155+'<input type="checkbox" id="admin_only" style="position:relative;top:2px;margin-left:7px;transform:scale(1.1,1.1);-webkit-transform: scale(1.1,1.1);"></div></div>' : '')
				+ '</div>', lang.docs_95, null, 550, null, lang.global_53, function(){
		if ($('#new-folder-name').val().trim() == '') {
			createFolder();
			return;
		}
		var dag_id = $('#new-folder-dag').length ? $('#new-folder-dag').val() : '';
		var role_id = $('#new-folder-role').length ? $('#new-folder-role').val() : '';
		var admin_only = $('#admin_only').length && $('#admin_only').prop('checked') ? '1' : '0';
		if (admin_only == '1' && (dag_id != '' || role_id != '')) {
			simpleDialog(lang.docs_1157, lang.global_01, null, null, "createFolder();");
			return;
		}
		$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:createFolder',{ folder_id: getParameterByName('folder_id'), name: $('#new-folder-name').val(), dag_id: dag_id, role_id: role_id, admin_only: admin_only },function(data){
			if (data == '1') {
				reloadFileRepoTable();
			} else if (data == '0') {
				alert(woops);
			} else {
				simpleDialog(data, lang.global_01, null, null, "createFolder();");
			}
		});
	}, lang.docs_95);
}

// Open file share dialog
function fileRepoGetPublicLink(doc_id, filename)
{
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:share',{ doc_id: doc_id },function(data){
		if (data == '' || data == '0') {
			alert(woops);
		} else {
			simpleDialog(data, '<i class="fa-solid fa-arrow-up-from-bracket me-2"></i>'+lang.docs_1099+' "'+filename+'"', null, 750, function() {
				$(this).remove();
			});
		}
	});
}

// Copy the API token to the user's clipboard
function copyLinkToClipboard()
{
	copyTextToClipboard($('#filePublicLink').val());
	// Create progress element that says "Copied!" when clicked
	var rndm = Math.random()+"";
	var copyid = 'clip'+rndm.replace('.','');
	var clipSaveHtml = '<span class="clipboardSaveProgress" id="'+copyid+'">'+lang.docs_1102+'</span>';
	$('#filePublicLinkBtn').after(clipSaveHtml);
	$('#'+copyid).toggle('fade','fast');
	setTimeout(function(){
		$('#'+copyid).toggle('fade','fast',function(){
			$('#'+copyid).remove();
		});
	},2000);
}

// Retrieve the HTML for the breadcrumb links
function getBreadcrumbs(folder_id, type, recycle_bin)
{
	$.post(app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:getBreadcrumbs',{ folder_id: folder_id, type: type, recycle_bin: recycle_bin },function(data){
		$('.ItemListBreadcrumbs').html(data);
	});
}

// Upload single file via AJAX
function uploadToFileRepository(file)
{
	// Current folder
	var folder_id = getParameterByName('folder_id');
	// Add file to formdata
	var fd = new FormData();
	fd.append('file', file);
	fd.append('folder_id', folder_id);
	fd.append('redcap_csrf_token', redcap_csrf_token);
	// Make sure file size is not too large
	if (file.size >= maxUploadSizeFileRepository) {
		showToast(lang.docs_81, '<code>'+file.name+'</code><br>'+lang.sendit_03+' (<b>'+roundup(file.size/1024/1024,3)+' MB</b>)'+lang.period+' '+lang.sendit_04+' '+roundup(maxUploadSizeFileRepository/1024/1024)+' MB '+lang.sendit_05, 'error', 15000);
		return;
	}
	// Make sure adding this file will not exceed the project's storage limit
	if (isNumeric(maxStorageSizeFileRepository) && (file.size*1+1*currentStorageSizeFileRepository) > maxStorageSizeFileRepository) {
		showToast(lang.docs_81, '<code>'+file.name+'</code><br>'+lang.docs_1111+' <b>'+round(maxStorageSizeFileRepository/1024/1024)+' MB</b>'+lang.period+' '+lang.docs_1112, 'error', 10000);
		return;
	}
	// Show loading image
	$('#file-repository-space-usage-loading').css('visibility', 'visible');
	// Set toast
	var toastId = showToast("<span class='file-upload-progress-perc'>0</span>% "+lang.docs_74, '<span class="fs12">'+lang.sendit_32+' <code>'+file.name+'</code></span>', 'dark', 3600000);
	// Upload the file via AJAX
	$.ajax({
		url: app_path_webroot + 'index.php?pid=' + pid + '&route=FileRepositoryController:upload',
		type: 'post',
		data: fd,
		contentType: false,
		processData: false,
		success: function (response) {
			if (response == '0') {
				// Error
				$('#'+toastId).remove();
				showToast(lang.docs_81, '<span class="fs12">'+lang.docs_82+' <code class="fs13">'+file.name+'</code></span>', 'error', 6000);
			} else if (isinteger(response)) {
				// Success
				reloadFileRepoTable();
				// Any more files to upload? If so, continue with the next.
				if (uploadFiles.length > 0) {
					uploadToFileRepository(uploadFiles.shift());
				} else {
					// Hide loading image
					$('#file-repository-space-usage-loading').css('visibility', 'hidden');
				}
			} else {
				// Specific error returned
				$('#'+toastId).remove();
				showToast(lang.docs_81, response, 'error', 6000);
			}
		},
		error: function (response) {
			// Error
			$('#'+toastId).remove();
			showToast(lang.docs_81, '<span class="fs12">'+lang.docs_82+' <code class="fs13">'+file.name+'</code></span>', 'error', 6000);
		},
		xhr: function() {
			var xhr = new window.XMLHttpRequest();
			xhr.upload.addEventListener("progress", function(evt){
				if (evt.lengthComputable) {
					var percentComplete = (evt.loaded / evt.total) * 100;
					// Upload the percentage complete
					$('#'+toastId+' .file-upload-progress-perc').html(roundup(percentComplete));
					// If completed, then hide toast
					if (roundup(percentComplete) == 100) {
						setTimeout("$('#"+toastId+" button').click();", 3500);
					}
				}
			}, false);
			return xhr;
		}
	});
}