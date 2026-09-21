$(function(){
	$('div#query_folders_popup').on('dialogclose', function(event) {
		$('#query_folders_popup').html('');
		$(this).dialog('destroy');
	});
});

function openSearchQueries()
{
	$("#menuLnkEditQueries, #menuLnkProjectQueryFolders, #menuLnkSearchQueries").hide();
	$("#searchQueryDiv").show();
	$("#searchQueries").focus();
}

function closeSearchQueries()
{
	$("#menuLnkEditQueries, #menuLnkProjectQueryFolders, #menuLnkSearchQueries").show();
	$("#searchQueryDiv").hide();
	$("#searchQueries").val('');
}

function openQueryFolders() {
	showProgress(1);
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFoldersDialog',{  },function(data){
		showProgress(0, 0);
		if (data == '') { alert(woops);return; }
		$('#query_folders_popup').html(data).dialog({ bgiframe: true, modal: true, width: 894, buttons: {
				'Close': function() {
					$('#query_folders_popup').html('');
					$(this).dialog('destroy');
				}
			}});
		setDragDropQueryFolderTable();
	});
}
function setDragDropQueryFolderTable() {
	$('table#report_folders_list tbody').sortable({
		containment:'parent',
		tolerance: 'pointer',
		update: function( event, ui ) {
			var data = $('table#report_folders_list tbody').sortable('serialize');
			$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderResort',{ data: data },function(data){
				updateQueryPanel();
				updateQueryFolderDropdown();
			});
		}
	});
}

function checkQueryFolderNameSubmit(event)
{
	if(event && event.keyCode == 13)
	{
		newQueryFolder();
	}
}

function newQueryFolder()
{
	$('#folderName').val( trim($('#folderName').val()) );
	if ($('#folderName').val() == '') {
		simpleDialog(langProjFolder05,null,'',330,"$('#folderName').focus();");
		return;
	}
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderCreate',{ folder_name: $('#folderName').val() },function(data){
		if (data != '1') { alert(woops);return; }
		$('#folderName').val('').focus();
		updateQueryPanel();
		updateQueryFolderTable();
		updateQueryFolderDropdown();
	});
}

// Update left-hand menu panel of Queries
function updateQueryPanel(folder_id, collapsed) {
	if (typeof folder_id == "undefined") folder_id = '';
	if (typeof collapsed == "undefined") collapsed = '0';
	var collapse = (collapsed == '1') ? '0' : '1';
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=viewpanel', { folder_id: folder_id, collapse: collapse }, function(data){
		$('#query_panel').html('');
		if (data != '') {
			// Update the left-hand menu
			$('#query_panel').html(data);
		}
	});
}

function updateQueryFolderTable(folder_id)
{
	if (typeof folder_id == "undefined") folder_id = '';
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderDisplayTable',{ folder_id: folder_id },function(data){
		$('#folders').html(data);
		setDragDropQueryFolderTable();
	});
}

function updateQueryFolderDropdown()
{
	var ddselected = $('#folder_id').val();
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderDisplayDropdown',{  },function(data){
		$('#select_folders').html(data)
		$('#folder_id').val(ddselected);
	});
}

function updateQueryFolderTableAssign(folder_id)
{
	var hide_assigned = $('#hide_assigned_rf').prop('checked') ? 1 : 0;
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderDisplayTableAssign',{ folder_id: folder_id, hide_assigned: hide_assigned },function(data){
		$('#query_folders_assign').html(data);
	});
}

function hideAssignedQueryFolders()
{
	var folder_id = $('#folder_id').val();
	var hide_assigned = $('#hide_assigned_rf').prop('checked') ? 1 : 0;
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderDisplayTableAssign',{ folder_id: folder_id, hide_assigned: hide_assigned },function(data){
		$('#query_folders_assign').html(data);
	});
}
function qfAssignSingle(folder_id, q_id, checked)
{
	checked = checked ? '1' : '0';
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderAssign',{ folder_id: folder_id, q_id: q_id, checked: checked },function(data){
		if (data != '1') {
			$('input#rid_'+q_id).prop('checked', false);
			alert(woops);
			return;
		}
		updateQueryPanel();
		highlightSavedQuery(q_id);
	});
}
function checkAllQueryFolders(folder_id, ids)
{
	var checkAll = $('input#checkAll').is(':checked');
	$.each(ids.split(','), function( k, v ) {
		$('input#rid_' + v).prop('checked', checkAll);
	});
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderAssign',{ folder_id: folder_id, ids: ids, checkAll: checkAll },function(data){
		updateQueryPanel();
		highlightSavedQueryAll();
	});
}

function editQueryFolderSave(folder_id)
{
	$('#rfiv_'+folder_id).val( trim($('#rfiv_'+folder_id).val()) );
	if ($('#rfiv_'+folder_id).val() == '') {
		simpleDialog(langProjFolder05,null,'',330,"$('#rfiv_"+folder_id+"').focus();");
		return;
	}
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderEdit',{ folder_id: folder_id, folder_name: $('#rfiv_'+folder_id).val() },function(data){
		$('#rft_'+folder_id).removeClass('hidden');
		$('#rfi_'+folder_id).addClass('hidden');
		if (data != '1') { alert(woops);return; }
		updateQueryPanel();
		updateQueryFolderTable();
		updateQueryFolderDropdown();
	});
}
function deleteQueryFolder(folder_id)
{
	simpleDialog(langDelFolder,langDelete,'',330,null,window.lang.global_53,"deleteQueryFolderSave("+folder_id+");",langDelete);
}

function deleteQueryFolderSave(folder_id)
{
	$.post(app_path_webroot+'ControlCenter/database_query_tool.php?action=queryFolderDelete',{ folder_id: folder_id },function(data){
		if (data != '1') { alert(woops);return; }
		var ddselected = $('#folder_id').val();
		updateQueryPanel();
		updateQueryFolderTable();
		updateQueryFolderDropdown();
		if (ddselected == folder_id) {
			$('#query_folders_assign').html('');
		}
	});
}

function highlightSavedQuery(report_id)
{
	$('#report_tr_' + report_id + ' td').effect('highlight', 1500);
	$('#report_saved_' + report_id).show().fadeOut(1500);
}

function highlightSavedQueryAll()
{
	$('#query_folders_assign tr[id^=report_tr_] td').effect('highlight', 1500);
	$('#query_folders_assign [id^=report_saved_]').show().fadeOut(1500);
}

function editFolder(folder_id)
{
	$("[id^=rft_]").removeClass('hidden');
	$("[id^=rfi_]").addClass('hidden');
	$('#rft_'+folder_id).addClass('hidden');
	$('#rfi_'+folder_id).removeClass('hidden');
	$('#rfiv_'+folder_id).focus();
}