<?php

use Vanderbilt\REDCap\Classes\MyCap\MyCapConfiguration;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\Participant;

// Config for non-project pages
require dirname(dirname(__FILE__)) . "/Config/init_global.php";

//If user is not a super user, go back to Home page
if (!defined('ACCESS_ADMIN_DASHBOARDS') || !ACCESS_ADMIN_DASHBOARDS) redirect(APP_PATH_WEBROOT);

// Must be accessed via AJAX
if (!$isAjax) exit("ERROR!");

if (isset($_GET['action']) && $_GET['action'] == 'listParticipants') {
    $par_arr[] = array("id" => '', "name" => $lang['mycap_mobile_app_485']);
    $sel_proj_code = $_GET['selected_project'];
    $sel_proj_id = (int) MyCap::getProjectIdByCode($sel_proj_code);
    if($sel_proj_id > 0) {
        $sql = "SELECT code, record, event_id FROM redcap_mycap_participants WHERE project_id = '".$sel_proj_id."' AND is_deleted = 0 ORDER BY record";
        $q = db_query($sql);

        $myCapProj = new MyCap($sel_proj_id);
        $condition = $myCapProj->project['participant_allow_condition'];
        while ($row = db_fetch_assoc($q))
        {
            $logicTest = true;

            if ($condition != '') {
                $logicTest = \REDCap::evaluateLogic($condition, $sel_proj_id, $row['record']);
            }
            if ($logicTest == false) {
                unset($row); continue;
            }
            $identifier = Participant::getParticipantIdentifier($row['record'], $sel_proj_id, null, $row['event_id']);
            $par_arr[] = array("id" => $row['code'], "name" => $identifier." [".$row['code']."]");
        }
    }
    echo json_encode($par_arr);
} else {
    $mc = new MyCapConfiguration();
    $mc->displayAPIResults($_GET['project_code'], $_GET['par_code']);
}
exit;
