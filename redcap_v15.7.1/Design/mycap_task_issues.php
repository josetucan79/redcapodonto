<?php
use Vanderbilt\REDCap\Classes\MyCap\Task;

if (isset($_POST['pid']))   $_GET['pid']  = $_POST['pid'];
if (isset($_POST['page'])) 	$_GET['page'] = $_POST['page'];

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

global $Proj, $lang;

$data = array('title' => ($_GET['page'] != '') ? $lang['mycap_mobile_app_591']." \"".$Proj->forms[$_GET['page']]['menu']."\"" : $lang['mycap_mobile_app_700'],
              'payload' => '');

// Set the form menu description for the form
if (isset($_POST['action']) && $_POST['action'] == "list_issues") {
    $html = Task::listMyCapTasksIssues($_GET['page']);

    if ($html == '') {
        $html = $lang['dataqueries_190'];
    }
    $data['payload'] = $html;
}
elseif (isset($_POST['action']) && $_POST['action'] == "fix_issues") {
    Task::fixMyCapTaskErrors($_GET['page']);
    $taskErrors = Task::getMyCapTaskNonFixableErrors($_GET['page']);
    $errorsNote = '';
    if (!empty($taskErrors)) {
        $errorsNote = "<div style='color:red;font-size:13px;'>Please note, errors are not fixable. Please take appropriate actions to fix MyCap errors for this instrument.</div>";
    }
    $data = array('payload' => "<div style='color:green;font-size:13px;'><img src='".APP_PATH_IMAGES."tick.png'> ".($_GET['page'] != '' ? $lang['mycap_mobile_app_590'] : $lang['mycap_mobile_app_702'])."</div>");
}
print json_encode_rc($data);
