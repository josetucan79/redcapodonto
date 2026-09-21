<?php

include_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$beginTime = isset($_GET['beginTime']) ? $_GET['beginTime'] : null;
$dags = $Proj->getGroups();
include APP_PATH_DOCROOT . 'Logging/filters.php';


?>
<script type="text/javascript">
$(function() {
	$('#beginTime, #endTime').datetimepicker({
		onClose: function(){ pageLoad() },
		yearRange: '-100:+10', changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery,
		hour: currentTime('h'), minute: currentTime('m'), buttonText: 'Click to select a date/time',
		showOn: 'both', buttonImage: app_path_images+'date.png', buttonImageOnly: true, timeFormat: 'HH:mm', constrainInput: false
	});
    if ($('input#record').length) {
        $('input#record').autocomplete({
            source: app_path_webroot+'DataEntry/auto_complete.php?pid='+pid,
            minLength: 1,
            delay: 0,
            select: function( event, ui ) {
                $(this).val(ui.item.value).trigger('blur');
                return false;
            }
        })
        .data('ui-autocomplete')._renderItem = function( ul, item ) {
            return $("<li></li>")
                .data("item", item)
                .append("<a>"+item.label+"</a>")
                .appendTo(ul);
        };
    }
});
function pageLoad(event) {
	if (event != null && event.keyCode != 13) {
		return;
	}
	showProgress(1);
	window.location.href=app_path_webroot+page+'?pid='+pid+'&beginTime='+$('#beginTime').val()+'&endTime='+$('#endTime').val()+'&usr='+$('#usr').val()+'&record='+$('#record').val()+'&logtype='+$('#logtype').val()+'&dag='+$('#dag').val();
}
</script>
<style type="text/css">
select:disabled { color: #aaa; }
</style>
<?php

renderPageTitle("<div style='float:left;'>
					<i class=\"fas fa-receipt\"></i> ".$lang['app_07']."
				 </div>
				 <div style='float:right;'>	
				    <span class='text-secondary font-weight-normal fs13 me-1'>{$lang['reporting_68']}</span>				
					<button class='jqbuttonmed' style='color:#004000;' onclick=\"window.location.href=app_path_webroot+'Logging/csv_export.php?pid='+pid+'&download_all=1';\"><img src='" . APP_PATH_IMAGES . "xls.gif' style='position: relative;top: -1px;'> {$lang['reporting_67']}</button>
					<button class='jqbuttonmed' style='color:#004000;' onclick=\"window.location.href=app_path_webroot+'Logging/csv_export.php'+window.location.search+'&filters_download_all=1';\"><img src='" . APP_PATH_IMAGES . "xls.gif' style='position: relative;top: -1px;'> {$lang['reporting_66']}</button>
				 	<button class='jqbuttonmed' style='color:#004000;' onclick=\"window.location.href=app_path_webroot+'Logging/csv_export.php'+window.location.search;\"><img src='" . APP_PATH_IMAGES . "xls.gif' style='position: relative;top: -1px;'> {$lang['reporting_65']}</button>
				 </div><br><br>");

print "<p>{$lang['reporting_02']}</p>";

//If user is in DAG, only show info from that DAG and give note of that
if ($user_rights['group_id'] != "") {
	print  "<p style='color:#800000;padding-bottom:10px;'>{$lang['global_02']}: {$lang['reporting_04']}</p>";
}

print "<div>
		<table><tr><td class='blue' style='padding:8px;border-width:1px;'>
			<table border=0 cellpadding=0 cellspacing=3>";

$_GET['beginTime'] = $beginTime;
print Logging::getFilterHTML($_GET);

//Show dropdown for displaying pages at a time
print  "<tr>
			<td style='text-align:right;padding-right:5px;'>
				{$lang['reporting_17']}
			</td>
			<td style='padding-top:2px;'>
				<select name='pages' class='x-form-text x-form-field' style='margin-bottom:2px;font-size:13px;height:25px;' onchange=\"window.location.href='".PAGE_FULL."?pid=$project_id&logtype='+\$('#logtype').val()+'&dag='+\$('#dag').val()+'&usr='+\$('#usr').val()+'&beginTime='+\$('#beginTime').val()+'&endTime='+\$('#endTime').val()+'&record='+\$('#record').val()+'&limit='+this.value;\">";
## Calculate number of pages of results for dropdown
// Page view logging only
if (isset($_GET['logtype']) && $_GET['logtype'] == 'page_view') {
	if ($filter_user == '' && $filter_record == '' && $dag_users == '') {
		$sql = "SELECT count(1) as thiscount FROM redcap_log_view WHERE project_id = $project_id $filter_logtype";
	} else {
		$sql = "SELECT count(1) as thiscount FROM redcap_log_view WHERE project_id = $project_id $filter_logtype $filter_user $dag_users";
	}
	if ($beginTime_YMDts != "") $sql .= " AND ts >= '".db_escape($beginTime_YMDts)."' ";
	if ($endTime_YMDts != "") $sql .= " AND ts <= '".db_escape($endTime_YMDts)."' ";
// Regular logging view
} else {
	if ($filter_logtype == '' && $filter_user == '' && $filter_record == '' && $dag_users == '') {
		$sql = "SELECT count(1) FROM ".Logging::getLogEventTable($project_id)." WHERE project_id = $project_id";
	} else {
		$sql = "SELECT count(1) FROM ".Logging::getLogEventTable($project_id)." WHERE project_id = $project_id $filter_logtype $filter_user $filter_record $dag_users";
	}
	if ($beginTime_YMDint != "") $sql .= " AND ts >= '".db_escape($beginTime_YMDint)."' ";
	if ($endTime_YMDint != "") $sql .= " AND ts <= '".db_escape($endTime_YMDint)."' ";
}
$num_total_files = db_result(db_query($sql),0);
$num_pages = ceil($num_total_files/100);
//Loop to create options for "Displaying files" dropdown
for ($i = 1; $i <= $num_pages; $i++)
{
	$end_num = $i * 100;
	$begin_num = $end_num - 99;
	$value_num = $end_num - 100;
	if ($end_num > $num_total_files) $end_num = $num_total_files;
	$optionLabel = "$begin_num - $end_num &nbsp;({$lang['survey_132']} $i {$lang['survey_133']} $num_pages)";
	print "<option value='$value_num'" . ((isset($_GET['limit']) && $_GET['limit'] == $value_num) ? " selected " : "") . ">$optionLabel</option>";
}
print  "		</select>
			</td>
		</tr>";
print $noteDisplayingPastWeekDefault;
print  "
	</table>
</td></tr>
</table>
</div><br>";

/**
 * QUERY FOR TABLE DISPLAY
 */
$QSQL_STRING = db_query($logging_sql);
$QSQL_STRING_ERROR = db_error();

if ($QSQL_STRING_ERROR != "" && defined("SUPER_USER") && SUPER_USER) {

	print "<div class='red' style='padding:20px 20px 20px 20px;width:100%;max-width:700px;'>
		   <img src='".APP_PATH_IMAGES."exclamation.png'> <b>{$lang['global_01']}{$lang['colon']}</b> ".htmlspecialchars($QSQL_STRING_ERROR, ENT_QUOTES)."<br><br>{$lang['config_functions_41']}<br><b>".htmlspecialchars($logging_sql, ENT_QUOTES)."</b>
		   </div>";

} else if (db_num_rows($QSQL_STRING) < 1) {

	print "<div align='center' style='padding:20px 20px 20px 20px;width:100%;max-width:700px;'>
		   <span class='yellow'><img src='".APP_PATH_IMAGES."exclamation_orange.png'> {$lang['reporting_18']}</span>
		   </div>";

} else {

	//Display table
	print "<div style='max-width:800px;'>
	<table logeventtable='$log_event_table' class='form_border' style='table-layout: fixed;width:100%;'><tr>
		<td class='header' style='border-width:1px;text-align:center;padding:2px 4px 2px 4px;width:150px;'>{$lang['reporting_19']}</td>
		<td class='header' style='border-width:1px;text-align:center;padding:2px 4px 2px 4px;width:120px;'>{$lang['global_11']}</td>
		<td class='header' style='border-width:1px;text-align:center;padding:2px 4px 2px 4px;width:120px;'>{$lang['reporting_21']}</td>
		<td class='header' style='border-width:1px;text-align:center;padding:2px 4px 2px 4px;'>{$lang['reporting_22']}</td>";
		// If project-level flag is set, then add "reason changed" to row data
		if ($require_change_reason)
		{
			print  "<td class='header' style='border-width:1px;text-align:center;padding:2px 4px 2px 4px;width:120px;'>{$lang['reporting_38']}</td>";
		}
		print  "</tr>";
	
	// If filtering by record, ignore some design/setup logged events that might get returned
	$recordFilterIgnoreEvents = array("Perform instrument-event mappings");
	while ($row = db_fetch_assoc($QSQL_STRING))
	{
		// If filtering by record, ignore some design/setup logged events that might get returned
		if (isset($_GET['record']) && $_GET['record'] != '' && $row['event'] == 'MANAGE' 
			&& in_array($row['description'], $recordFilterIgnoreEvents)) continue;
		if (!SUPER_USER && (strpos($row['description'], "(Admin only) Stop viewing project as user") === 0 || strpos($row['description'], "(Admin only) View project as user") === 0)) {
			continue;
		}
		// Get values for this row
		$newrow = Logging::renderLogRow($row);
		// Render row values
		print  "<tr>
					<td ts='{$row['ts']}' class='logt' style='width:150px;'>
						{$newrow[0]}
					</td>
					<td class='logt' style='width:120px;word-break:break-all;'>
						{$newrow[1]}
					</td>
					<td class='logt' style='width:120px;'>
						".filter_tags($newrow[2])."
					</td>
					<td class='logt' style='text-align:left;word-break:break-all;'>
						".nl2br(htmlspecialchars(label_decode($newrow[3]), ENT_QUOTES))."
					</td>";
		// If project-level flag is set, then add "reason changed" to row data
		if ($require_change_reason)
		{
			print  "<td class='logt' style='text-align:left;width:120px;'>
						{$newrow[5]}
					</td>";
		}
		print  "</tr>";
	}
	print "</table></div>";

}


include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
