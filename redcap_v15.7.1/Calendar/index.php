<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

loadJS('Calendar.js');

renderPageTitle("<div style='float:left;'>
					<i class=\"far fa-calendar-alt\"></i> {$lang['app_08']}
				 </div>
				 <div style='float:right;font-size:14px;'>".RCView::ConsortiumVideoLink(RCView::tt("calendar_11"), "calendar02.mp4", $lang["app_08"])."</div><br><br>");

print  "<p style='max-width:900px;'>".$lang['calendar_02'];

//If multiple events exist, explain how participants may be scheduled and added to calendar
if ($longitudinal && $scheduling) {
	print  $lang['calendar_03']."<a href='".APP_PATH_WEBROOT."Calendar/scheduling.php?pid=$project_id' style='text-decoration:underline;'>".$lang['calendar_04']."</a> "
			. $lang['calendar_05'];
}
print  "</p>";

//If user is in DAG, only show calendar events from that DAG and give note of that
if ($user_rights['group_id'] != "") {
	print  "<p style='color:#C00000;'>{$lang['global_02']}{$lang['colon']} {$lang['calendar_06']}</p>";
}

// Render calendar table
include APP_PATH_DOCROOT . "Calendar/calendar_table.php";

print "<br><br>";

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';


// Function to add URL variables to tab links
function appendVarTab() {
	$val = "";
	if (isset($_GET['month']) && isset($_GET['year'])) {
		$val .= "&month={$_GET['month']}&year={$_GET['year']}";
	}
	if (isset($_GET['day']) && (!isset($_GET['view']) || $_GET['view'] != "month")) {
		$val .= "&day={$_GET['day']}";
	}
	return $val;
}
