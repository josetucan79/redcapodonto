<?php

// Config
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Default return message
$msg = 'error';
$ridPart = '';

// ERASE the whole randomization setup and allocations
if (isset($_POST['action']) && $_POST['action'] == 'erase')
{
	// Set return message
	$msg = 'error';
    $rid = Randomization::getRid($_POST['rid']);
	if ($rid && Randomization::eraseRandomizationSetup($rid)) {
		$msg = 'erased';
		// Logging
		Logging::logEvent("", "redcap_randomization", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Erase randomization model and allocations (rid=$rid)", "rid = $rid");
	}
}


// SAVE SETUP
else if (isset($_POST['action']) && $_POST['action'] == 'realtime')
{
    // Save the realtime execution option
    echo Randomization::saveRealtimeOption($_POST);
    exit; // return with ajax response, not full redirect
}
else
{
	// Save the randomization model
    $rid = intval(Randomization::saveRandomizationSetup($_POST));
    switch ($rid) {
        case 0: $msg = 'error'; break;
        case -1: $msg = 'duplicate'; break;
        default:
            $msg = 'saved';
            $isBlinded = ($Proj->metadata[$_POST['targetField']]['element_type']=='text');
            Logging::logEvent("", "redcap_randomization", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Save randomization model".($isBlinded ? " - blinded" : "")." (rid=$rid)", "rid = $rid");
            $ridPart = "&rid=$rid";
            break;
    }
}

// Redirect back to Setup page
redirect(APP_PATH_WEBROOT . "Randomization/index.php?pid=$project_id&msg=$msg".$ridPart);