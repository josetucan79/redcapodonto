<?php
use Vanderbilt\REDCap\Classes\MyCap\MyCap;

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

if ($_GET['action'] == 'showDetails') {
    $title = "<i class='fa-solid fa-circle-arrow-right' style='vertical-align:middle;'></i> "
        . "<span style='vertical-align:middle;font-size:15px;'>Migrating Data from MyCap External Module to REDCap</span>";
    $content = MyCap::renderMigrateMyCapEMtoREDCapDialog(false, $_GET['flag']);
    $success = "";
} else if ($_GET['action'] == 'proceedMigration' && UserRights::isSuperUserNotImpersonator()) {
    global $lang;
    $output = MyCap::renderMigrateMyCapEMtoREDCapDialog(true);
    $success = ($output['success'] == true) ? 1 : 0;
    $title = ($success == 1) ? $lang['global_79'] : $lang['global_01'];
    $content = $output['message'];
} else {
    $title = "ERROR";
    $content = "Error: Only REDCap admins can migrate the MyCap External Module settings!";
    $success = 0;
}

// Return title and content
echo json_encode(array(
    'title' => $title,
    'content' => $content,
    'success' => $success
));