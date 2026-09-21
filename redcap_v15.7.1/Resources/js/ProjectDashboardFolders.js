$(function(){
	// Project Dashboard searching
	enableDashboardSearching();
	$('div#dashboard_folders_popup').on('dialogclose', function(event) {
		$('#dashboard_folders_popup').html('');
		$(this).dialog('destroy');
	});
});

function openSearchDashboards()
{
	$("#menuLnkEditDashboards, #menuLnkProjectDashboardFolders, #menuLnkSearchDashboards").hide();
	$("#searchDashboardDiv").show();
	$("#searchDashboards").focus();
}

function closeSearchDashboards()
{
	$("#menuLnkEditDashboards, #menuLnkProjectDashboardFolders, #menuLnkSearchDashboards").show();
	$("#searchDashboardDiv").hide();
	$("#searchDashboards").val('');
}

function openDashboardFolders() {
	showProgress(1);
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFoldersDialog',{  },function(data){
		showProgress(0, 0);
		if (data == '') { alert(woops);return; }
		$('#dashboard_folders_popup').html(data).dialog({ bgiframe: true, modal: true, width: 894, buttons: {
				'Close': function() {
					$('#dashboard_folders_popup').html('');
					$(this).dialog('destroy');
				}
			}});
		setDragDropDashboardFolderTable();
	});
}
function setDragDropDashboardFolderTable() {
	$('table#report_folders_list tbody').sortable({
		containment:'parent',
		tolerance: 'pointer',
		update: function( event, ui ) {
			var data = $('table#report_folders_list tbody').sortable('serialize');
			$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderResort',{ data: data },function(data){
				updateDashboardPanel();
				updateDashboardFolderDropdown();
			});
		}
	});
}

function checkDashboardFolderNameSubmit(event)
{
	if(event && event.keyCode == 13)
	{
		newDashboardFolder();
	}
}

function newDashboardFolder()
{
	$('#folderName').val( trim($('#folderName').val()) );
	if ($('#folderName').val() == '') {
		simpleDialog(langProjFolder05,null,'',330,"$('#folderName').focus();");
		return;
	}
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderCreate',{ folder_name: $('#folderName').val() },function(data){
		if (data != '1') { alert(woops);return; }
		$('#folderName').val('').focus();
		updateDashboardPanel();
		updateDashboardFolderTable();
		updateDashboardFolderDropdown();
	});
}

// Update left-hand menu panel of Dashboards
function updateDashboardPanel(folder_id, collapsed) {
	if (typeof folder_id == "undefined") folder_id = '';
	if (typeof collapsed == "undefined") collapsed = '0';
	var collapse = (collapsed == '1') ? '0' : '1';
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:viewpanel', { folder_id: folder_id, collapse: collapse }, function(data){
		$('#dashboard_panel').remove();
		if (data != '') {
			// Update the left-hand menu
			$('#app_panel').after(data);
			// Add fade mouseover for "Edit instruments" and "Edit reports" links on project menu
			$("#menuLnkEditDashboards").mouseenter(function() {
				$(this).removeClass('opacity50');
				if (isIE) $(this).find("img").removeClass('opacity50');
			}).mouseleave(function() {
				$(this).addClass('opacity50');
				if (isIE) $(this).find("img").addClass('opacity50');
			});
			projectMenuToggle('#projMenuDashboards');
			enableDashboardSearching();
		}
	});
}
function enableDashboardSearching()
{
	if (!$('#searchDashboards').length) return;
	$('#searchDashboards').autocomplete({
		source: app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashSearch',
		minLength: 2,
		delay: 150,
		focus: function( event, ui ) {
			return false;
		},
		select: function( event, ui ) {
			window.location.href = app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:view&dash_id='+ui.item.value;
			return false;
		}
	})
		.data('ui-autocomplete')._renderItem = function( ul, item ) {
		return $("<li class='fs11'></li>")
			.data("item", item)
			.append("<a class='fs11'>"+item.label+"</a>")
			.appendTo(ul);
	};
}
function updateDashboardFolderTable(folder_id)
{
	if (typeof folder_id == "undefined") folder_id = '';
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderDisplayTable',{ folder_id: folder_id },function(data){
		$('#folders').html(data);
		setDragDropDashboardFolderTable();
	});
}

function updateDashboardFolderDropdown()
{
	var ddselected = $('#folder_id').val();
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderDisplayDropdown',{  },function(data){
		$('#select_folders').html(data)
		$('#folder_id').val(ddselected);
	});
}

function updateDashFolderTableAssign(folder_id)
{
	var hide_assigned = $('#hide_assigned_rf').prop('checked') ? 1 : 0;
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderDisplayTableAssign',{ folder_id: folder_id, hide_assigned: hide_assigned },function(data){
		$('#dash_folders_assign').html(data);
	});
}

function hideAssignedDashboardFolders()
{
	var folder_id = $('#folder_id').val();
	var hide_assigned = $('#hide_assigned_rf').prop('checked') ? 1 : 0;
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderDisplayTableAssign',{ folder_id: folder_id, hide_assigned: hide_assigned },function(data){
		$('#dash_folders_assign').html(data);
	});
}
function dfAssignSingle(folder_id, dash_id, checked)
{
	checked = checked ? '1' : '0';
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderAssign',{ folder_id: folder_id, dash_id: dash_id, checked: checked },function(data){
		if (data != '1') {
			$('input#rid_'+dash_id).prop('checked', false);
			alert(woops);
			return;
		}
		updateDashboardPanel();
		highlightSavedDashboard(dash_id);
	});
}
function checkAllDashboardFolders(folder_id, ids)
{
	var checkAll = $('input#checkAll').is(':checked');
	$.each(ids.split(','), function( k, v ) {
		$('input#rid_' + v).prop('checked', checkAll);
	});
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderAssign',{ folder_id: folder_id, ids: ids, checkAll: checkAll },function(data){
		updateDashboardPanel();
		highlightSavedDashboardAll();
	});
}

function editDashFolderSave(folder_id)
{
	$('#rfiv_'+folder_id).val( trim($('#rfiv_'+folder_id).val()) );
	if ($('#rfiv_'+folder_id).val() == '') {
		simpleDialog(langProjFolder05,null,'',330,"$('#rfiv_"+folder_id+"').focus();");
		return;
	}
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderEdit',{ folder_id: folder_id, folder_name: $('#rfiv_'+folder_id).val() },function(data){
		$('#rft_'+folder_id).removeClass('hidden');
		$('#rfi_'+folder_id).addClass('hidden');
		if (data != '1') { alert(woops);return; }
		updateDashboardPanel();
		updateDashboardFolderTable();
		updateDashboardFolderDropdown();
	});
}
function deleteDashFolder(folder_id)
{
	simpleDialog(langDelFolder,langDelete,'',330,null,window.lang.global_53,"deleteDashFolderSave("+folder_id+");",langDelete);
}

function deleteDashFolderSave(folder_id)
{
	$.post(app_path_webroot+'index.php?pid='+pid+'&route=ProjectDashController:dashFolderDelete',{ folder_id: folder_id },function(data){
		if (data != '1') { alert(woops);return; }
		var ddselected = $('#folder_id').val();
		updateDashboardPanel();
		updateDashboardFolderTable();
		updateDashboardFolderDropdown();
		if (ddselected == folder_id) {
			$('#dash_folders_assign').html('');
		}
	});
}

function highlightSavedDashboard(report_id)
{
	$('#report_tr_' + report_id + ' td').effect('highlight', 1500);
	$('#report_saved_' + report_id).show().fadeOut(1500);
}

function highlightSavedDashboardAll()
{
	$('#dash_folders_assign tr[id^=report_tr_] td').effect('highlight', 1500);
	$('#dash_folders_assign [id^=report_saved_]').show().fadeOut(1500);
}