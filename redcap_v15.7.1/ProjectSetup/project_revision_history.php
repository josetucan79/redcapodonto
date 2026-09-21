<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


// Use ui_id from redcap_user_information to retrieve Username+First+Last
function getUsernameFirstLast($ui_id)
{
	global $ui_ids;
	// Must be numeric
	if (!is_numeric($ui_id))   return false;
	// If already called, retrieve from array instead of querying
	if (isset($ui_ids[$ui_id])) return $ui_ids[$ui_id];
	// Get from table
	$sql = "select concat(username,' (',user_firstname,' ',user_lastname,')') from redcap_user_information where ui_id = $ui_id";
	$q = db_query($sql);
	if (db_num_rows($q) > 0) {
		// Add to array if called again
		$ui_ids[$ui_id] = db_result($q, 0);
		// Return query result
		return $ui_ids[$ui_id];
	}
	return false;
}

// Obtain any data dictionary snapshots
$dd_snapshots = array();
$sql = "select d.*, e.stored_date, if (i.username is null, '', concat(i.username,' (',i.user_firstname,' ',i.user_lastname,')')) as username 
		from redcap_edocs_metadata e, redcap_data_dictionaries d 
		left join redcap_user_information i on i.ui_id = d.ui_id
		where d.project_id = $project_id and d.project_id = e.project_id and e.doc_id = d.doc_id 
		and e.delete_date is null order by d.doc_id";
$q = db_query($sql);
while ($row = db_fetch_assoc($q))
{
	$dd_snapshots[] = array('id'=>$row['doc_id'], 'username'=>$row['username'], 'time'=>$row['stored_date']);
}

// Array for storing username and first/last to reduce number of queries if lots of revisions exist
$ui_ids = array();
// Get username/name of project creator
$creatorName = empty($created_by) ? "" : $lang['rev_history_14'] . " <span style='color:#800000;'>" . getUsernameFirstLast($created_by) . "</span>";
// Create array with times of creation, production, and production revisions
$revision_info = array();
// Creation time
$revision_info[$creation_time] = array("", $lang['rev_history_01'], DateTimeRC::format_ts_from_ymd($creation_time), "-", $creatorName);
$revision_info[$creation_time][] = "";
// Get prod time and revisions, if any
if ($status < 1)
{
	// Add any data dictionary uploads
	foreach ($dd_snapshots as $row) {
		$snapshotUser = $row['username'] == '' ? "" : $lang['design_686'] . " <span style='color:#800000;'>" . $row['username'] . "</span>";

        $compareLabel = DataDictionaryRevisions::getCompareRevisionIcon("snapshot_".$row['id']);
		$revision_info[$row['time']] = array($compareLabel, "<span class='dd_snapshot' style='color:#777;'>" . $lang['design_685'] . "</span>",
							"<span style='color:#777;'>" . DateTimeRC::format_ts_from_ymd($row['time']) . "</span>",
							"<img src='" . APP_PATH_IMAGES . "xls.gif'> <a style='color:green;font-size:11px;' href='" . APP_PATH_WEBROOT."DataEntry/file_download.php?pid=$project_id&doc_id_hash=".Files::docIdHash($row['id'])."&id=".$row['id']."'>{$lang['rev_history_04']}</a>", $snapshotUser);
        $revision_info[$row['time']][] = "snapshot_".$row['id'];
	}
	// Add production time to table
	$revision_info[NOW] = array("", $lang['design_695'], "<span style='line-height:19px;'>-</span>", "<img src='" . APP_PATH_IMAGES . "xls.gif'> <a style='color:green;font-size:11px;' href='" . APP_PATH_WEBROOT . "Design/data_dictionary_download.php?pid=$project_id&fileid=data_dictionary'>{$lang['rev_history_04']}</a>", "");
    $revision_info[NOW][] = "dev_current";
}
else
{
	// Retrieve person who moved to production
	$sql = "select concat(u.username,' (',u.user_firstname,' ',u.user_lastname,')') 
            from redcap_user_information u, ".Logging::getLogEventTable($project_id)." l
			where u.username = l.user and l.description in ('Move project to production status', 'Move project to production status (delete all records)')
			and l.project_id = $project_id and l.ts = '".str_replace(array(' ',':','-'), array('','',''), $production_time)."' 
			order by log_event_id desc limit 1";
	$q = db_query($sql);
	$moveProdName = (db_num_rows($q) > 0) ? $lang['rev_history_18'] . " <span style='color:#800000;'>" . db_result($q, 0) . "</span>" : "";
	// Production time
	$revision_info[$production_time] = array("", $lang['rev_history_02'], DateTimeRC::format_ts_from_ymd($production_time), "<span style='line-height:19px;'>-</span>", $moveProdName);
    $revision_info[$production_time][] = "";
	// Get revisions
	$revnum = 1;
	$revTimes = array($production_time);
	$sql = "select p.pr_id, p.ts_approved, p.ui_id_requester, p.ui_id_approver,
			if(p.ts_req_approval = p.ts_approved,1,0) as automatic
			from redcap_metadata_prod_revisions p
			where p.project_id = $project_id and p.ts_approved is not null order by p.pr_id";
	$q = db_query($sql);
	while ($row = db_fetch_assoc($q))
	{
		// Get username/name of project creator
		$requesterName = getUsernameFirstLast($row['ui_id_requester']);
		if (!empty($requesterName)) $requesterName = $lang['rev_history_15'] . " <span style='color:#800000;'>$requesterName</span>";
		// Get username/name of approver if not approved automatically
		if ($row['automatic']) {
			$approverName = $lang['rev_history_16'];
		} else {
			// Get username/name of approver
			$approverName = getUsernameFirstLast($row['ui_id_approver']);
			if (!empty($approverName)) $approverName = $lang['rev_history_17'] . " <span style='color:#800000;'>$approverName</span>";
		}
        $compareLabel = DataDictionaryRevisions::getCompareRevisionIcon($row['pr_id']);
		// Add to array
		$revision_info[$row['ts_approved']] = array($compareLabel, $lang['rev_history_03']." #".$revnum, DateTimeRC::format_ts_from_ymd($row['ts_approved']),
								 "<img src='" . APP_PATH_IMAGES . "xls.gif'> <a style='color:green;font-size:11px;' href='" . APP_PATH_WEBROOT . "Design/data_dictionary_download.php?pid=$project_id&rev_id={$row['pr_id']}&fileid=data_dictionary".($revnum > 1 ? "&revnum=".($revnum-1) : "")."'>{$lang['rev_history_04']}</a>",
								 "$requesterName<br>$approverName");
        $revision_info[$row['ts_approved']][] = $row['pr_id'];
		// Get last rev time for use later
		$revTimes[] = $row['ts_approved'];
		// Increate counter
		$revnum++;
	}
	
	
	// Get max array key
	$maxKey = max(array_keys($revision_info));
	// Push all data dictionary links up one row in table (because each represents when each was archived, so it's off one)
	$lastkey = null;
	foreach ($revision_info as $key=>$attr) {
		// Skip first one
		if ($lastkey !== null) {
			// Set previous item's 1st attribute to current one -- Compare Icon
            $revision_info[$lastkey][0] = $revision_info[$key][0];
            // Set previous item's 3rd attribute to current one -- Download link
            $revision_info[$lastkey][3] = $revision_info[$key][3];
            // Set previous item's 1st attribute to current one -- Revision Number
            $revision_info[$lastkey][5] = $revision_info[$key][5];
		}
		// Set for next loop
		$lastkey = $key;
	}
    $compareLabel = DataDictionaryRevisions::getCompareRevisionIcon('');
	// Now fix the last entry with current DD link and append "current" to current revision label
    $revision_info[$maxKey][0] = $compareLabel;
	$revision_info[$maxKey][1] .= " ".$lang['rev_history_05'];
	$revision_info[$maxKey][3] = "<img src='" . APP_PATH_IMAGES . "xls.gif'> <a style='color:green;font-size:11px;' href='" . APP_PATH_WEBROOT . "Design/data_dictionary_download.php?pid=$project_id&fileid=data_dictionary'>{$lang['rev_history_04']}</a>";
    $revision_info[$maxKey][5] = "";
	// If currently in draft mode, give row to download current
	if ($draft_mode > 0)
	{
		$revision_info[NOW] = array("",$lang['rev_history_06'], "-",
								 "<img src='" . APP_PATH_IMAGES . "xls.gif'> <a style='color:green;font-size:11px;' href='" . APP_PATH_WEBROOT . "Design/data_dictionary_download.php?pid=$project_id&fileid=data_dictionary&draft'>{$lang['rev_history_04']}</a>", "");
        $revision_info[NOW][] = "draft";
	}

	// Add any remaining dd snapshots
	foreach ($dd_snapshots as $row) {
		$snapshotUser = $row['username'] == '' ? "" : $lang['design_686'] . " <span style='color:#800000;'>" . $row['username'] . "</span>";

        $compareLabel = DataDictionaryRevisions::getCompareRevisionIcon("snapshot_".$row['id']);
		$revision_info[$row['time']] = array($compareLabel,"<span class='dd_snapshot' style='color:#777;'>" . $lang['design_685'] . "</span>",
							"<span style='color:#777;'>" . DateTimeRC::format_ts_from_ymd($row['time']) . "</span>", 
							"<img src='" . APP_PATH_IMAGES . "xls.gif'> <a style='color:green;font-size:11px;' href='" . APP_PATH_WEBROOT."DataEntry/file_download.php?pid=$project_id&doc_id_hash=".Files::docIdHash($row['id'])."&id=".$row['id']."'>{$lang['rev_history_04']}</a>", $snapshotUser);
        $revision_info[$row['time']][] = "snapshot_".$row['id'];
	}
	
	// Reorder array by timestamp
	ksort($revision_info);	
}

## Get production revision stats
// Time since creation
$timeSinceCreation = User::number_format_user(timeDiff($creation_time,NOW,1,'d'),1);
if ($status > 0 && $production_time != "")
{
	$timeInDevelopment = User::number_format_user(timeDiff($creation_time,$production_time,1,'d'),1);
	$timeInProduction = User::number_format_user(timeDiff($production_time,NOW,1,'d'),1);
	if ($revnum > 1)
	{
		$timeSinceLastRev = User::number_format_user(timeDiff($revTimes[$revnum-1],NOW,1,'d'),1);
		// Average rev time: Create array of times between revisions
		$revTimeDiffs = array();
		$lasttime = "";
		foreach ($revTimes as $thistime)
		{
			if ($lasttime != "") {
				$revTimeDiffs[] = timeDiff($lasttime,$thistime,1,'d');
			}
			$lasttime = $thistime;
		}
		$avgTimeBetweenRevs = User::number_format_user(round(array_sum($revTimeDiffs) / count($revTimeDiffs), 1),1);
		// Median rev time
		rsort($revTimeDiffs);
		$mdnTimeBetweenRevs = User::number_format_user($revTimeDiffs[round(count($revTimeDiffs) / 2) - 1],1);
	}
}


## HISTORY TABLE
// Table columns
$col_widths_headers = array(
                        array(30, "col1"),  // Compare icon column
						array(170, "col2"),
						array(120, "col3", "center"),
						array(170, "col4", "center"),
						array(295, "col5"),
                        array(1, "col6"),   // Revision Id column
					);
$snapshot_btn_disabled = empty($dd_snapshots) ? "disabled" : "";
$title = RCView::div(array(),
			RCView::div(array('style'=>'float:left;font-size:13px;margin-top:3px;'),
				$lang['app_18']
			) .
			RCView::div(array('style'=>'float:right;'),
				RCView::button(array('id'=>'hide_snapshots_btn', 'class'=>'btn btn-defaultrc btn-xs', 'style'=>'background-color:#eee;font-weight:normal;font-size:11px;', $snapshot_btn_disabled=>$snapshot_btn_disabled, 'onclick'=>"$('#hide_snapshots_btn').hide();$('#show_snapshots_btn').show();$('.dd_snapshot').each(function(){ $(this).parents('tr:first').hide('fast'); });"), 
					RCView::span(array('class'=>'fas fa-camera', 'style'=>'top:2px;'), '') .
					RCView::span(array('style'=>'vertical-align:middle;margin-left:4px;'), $lang['design_693'])
				) .
				RCView::button(array('id'=>'show_snapshots_btn', 'class'=>'btn btn-defaultrc btn-xs', 'style'=>'background-color:#eee;font-weight:normal;font-size:11px;display:none;', 'onclick'=>"$('#hide_snapshots_btn').show();$('#show_snapshots_btn').hide();$('.dd_snapshot').each(function(){ $(this).parents('tr:first').show('fast'); });"), 
					RCView::span(array('class'=>'fas fa-camera', 'style'=>'top:2px;'), '') .
					RCView::span(array('style'=>'vertical-align:middle;margin-left:4px;'), $lang['design_694'])
				)
			) .
			RCView::div(array('class'=>'clear'), '')
		 );
// Get html for table

if (isset($_GET['action'])) {
    $ddRevisions = new DataDictionaryRevisions($revision_info);
    switch ($_GET['action']) {
        case "listRevisions":
            $list = $ddRevisions->getRemainingRevisionsList($_GET['selectedRevId']);
            print $list;
            exit;
        case "compareRevisions":
            $selectedRevId = $tempSelectedRevId = $_GET['selectedRevId'];
            $compareRevId = $_GET['compareRevId'];
            // Always compare latest revision with other revision, no matter in which sequense user selected
            $selectedRevTime = $ddRevisions->getRevisionTimestamp($selectedRevId);
            $compareRevTime = $ddRevisions->getRevisionTimestamp($compareRevId);

            if ($compareRevTime == $selectedRevTime) {
                $compareId = ltrim($compareRevId, "snapshot_");
                $selectedId = ltrim($selectedRevId, "snapshot_");
                if ($compareId > $selectedId) {
                    $selectedRevId = $compareRevId;
                    $compareRevId = $tempSelectedRevId;
                }
            } else if ($compareRevTime > $selectedRevTime) {
                $selectedRevId = $compareRevId;
                $compareRevId = $tempSelectedRevId;
            }
            list($title, $html) = $ddRevisions->renderChangesTable($selectedRevId, $compareRevId);
            // Return as JSON
            print json_encode_rc(array('title' => $title, 'content' => $html));
            exit;
        case "downloadRevisions":
            Logging::logEvent("", "redcap_data_dictionaries", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Export Revisions");

            $data = $ddRevisions->getComparisonOfChangesRows($_GET['selectedRevId'], $_GET['compareRevId']);
            $content = (!empty($data)) ? arrayToCsv($data) : '';

            $project_title = REDCap::getProjectTitle();
            $filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($project_title, ENT_QUOTES)))), 0, 30)
                ."_ComparisonRevisions_".date("Y-m-d").".csv";

            header('Pragma: anytextexeptno-cache', true);
            header("Content-type: application/csv");
            header('Content-Disposition: attachment; filename=' . $filename);
            echo addBOMtoUTF8($content);
            exit;
        default:
            exit("ERROR!");
    }
}
$compareImgCount = 0;
foreach ($revision_info as $key => $revision) {
    if (trim($revision[0]) != '') {
        $compareImgCount++;
    }
    // Remove last column "revision id" which was included for compare revisions functionality
    unset($revision_info[$key]['5']);
}
$tableWidth = 850;
// Remove first column if there is only one compare icon (no revision available for comparing)
if ($compareImgCount < 2) {
    $tableWidth = 750;
    foreach ($revision_info as $key => $revision) {
        unset($revision_info[$key]['0']);
    }
}
$revTable = renderGrid("prodrevisions", $title, $tableWidth, "auto", $col_widths_headers, $revision_info, false, false, false);


## STATS TABLE
// Stats data
$revision_stats   = array();
$revision_stats[] = array($lang['rev_history_07'], "$timeSinceCreation days");
if ($status > 0 && $production_time != "")
{
	$revision_stats[] = array($lang['rev_history_08'], "$timeInDevelopment days");
	$revision_stats[] = array($lang['rev_history_09'], "$timeInProduction days");
	if ($revnum > 1)
	{
		$revision_stats[] = array($lang['rev_history_10'], "$timeSinceLastRev days");
		$revision_stats[] = array($lang['rev_history_11'], "$avgTimeBetweenRevs days / $mdnTimeBetweenRevs days");
	}
}
// Table columns
$col_widths_headers = array(
						array(220, "col1"),
						array(130, "col2", "center")
					);
// Get html for table
$revStats = renderGrid("revstats",
			RCView::div(array('style'=>'font-size:13px;margin:2px 0;'),
				$lang['rev_history_12']
			), 375, "auto", $col_widths_headers, $revision_stats, false, false, false);











// Render page (except don't show headers in ajax mode)
if (!$isAjax)
{
	include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	// TABS
	include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
}
// Instructions
print "<p>{$lang['rev_history_13']} {$lang['rev_history_42']}</p>";
// Hide project title in hidden div (for ajax only to use in dialog title)
print "<div id='revHistPrTitle' style='display:none;'>".RCView::escape($app_title)."</div>";
// Revision history table and revision stats table
print "$revTable<br>$revStats";
// Footer
if (!$isAjax) include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
?>
<style type="text/css">
    .compare-revision-menu {
        display: none;
        z-index: 1000;
        position: absolute;
        overflow: hidden;
        border: 1px solid #CCC;
        white-space: nowrap;
        font-family: sans-serif;
        background: #FFF;
        color: #333;
        border-radius: 5px;
        padding: 0;
    }
    /* Each of the items in the list */
    .compare-revision-menu li.revision-title {
        padding: 8px 12px;
        cursor: pointer;
        list-style-type: none;
        transition: all .3s ease;
        user-select: none;
    }
    .compare-revision-menu li.revision-title:hover {
        background-color: #DEF;
    }
    .compare-revision-menu li.heading {
        background-color: #D7D7D7;
        font-weight: bold;
        color:#111; font-family:"Open Sans",tahoma,Helvetica,Arial,Helvetica,sans-serif;text-align:left;padding:6px;
        border-bottom: 1px solid #ccc;
    }
    .bg-color-desc {
        width:30px;height:18px;border:1px solid #000; display: inline-block; vertical-align: top;
    }
    .preview-change-text {
        margin: 5px 0 15px;
        border: 1px solid #bbb;
        padding: 5px;
        background: #eee;
        color: #000;
    }
</style>
<script>
    var pid = '<?php echo $_GET['pid']; ?>';
    var from = '<?php echo isset($_GET['from']) ? $_GET['from'] : '' ; ?>';
    $(function () {
        $(".revision-list").bind("click", function (event) {
            event.preventDefault();

            selectedRevId = $(this).attr("id");
            $.get(app_path_webroot+'ProjectSetup/project_revision_history.php?action=listRevisions&selectedRevId='+selectedRevId+'&pid='+pid,{},function(data){
                $(".compare-revision-menu").html(data);
            });
            $(".compare-revision-menu").finish().toggle(100);
            if (from == 'cc') {
                // Set top and left according to popup height/left in control center
                $(".compare-revision-menu").css({
                    top: ((event.pageY - $("#revHist").parent().offset().top) - 30 )+ "px",
                    left: (event.pageX - $("#revHist").parent().offset().left) + "px"
                });
            } else {
                $(".compare-revision-menu").css({
                    top: event.pageY + "px",
                    left: event.pageX + "px"
                });
            }
        });

        // If the document is clicked somewhere
        $(document).bind("mousedown", function (event) {
            // If the clicked element is not the menu
            if (!$(event.target).parents(".compare-revision-menu").length > 0) {
                // Hide it
                $(".compare-revision-menu").html('').hide(100);
            }
        });

        // If the menu element is clicked
        $(document).on("click", ".compare-revision-menu li.revision-title",function() {
            var compareRevId = $(this).attr("data-action");

            // Make ajax call
            $.get(app_path_webroot+'ProjectSetup/project_revision_history.php', { pid: pid, 'action': 'compareRevisions', selectedRevId: selectedRevId, compareRevId: compareRevId },
                function(data) {
                    var json_data = JSON.parse(data);
                    simpleDialog(json_data.content, json_data.title,'compare_revision_popup',$(window).width()-50);
                    $('#compare_revision_popup').height('auto');
                    fitDialog($('#compare_revision_popup'));
                });

            // Hide it AFTER the action was triggered
            $(".compare-revision-menu").html('').hide(100);
        });
    });
</script>
<ul class="compare-revision-menu"></ul>