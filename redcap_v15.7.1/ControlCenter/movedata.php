<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_global.php';

// Must be an admin
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);

$backToProjBtn = "";
if (isset($_GET['project']) && isinteger($_GET['project'])) {
    $backToProjBtn = "<span class='text-secondary mr-3'>- or -</span>
                      <button class='btn btn-xs btn-defaultrc fs14' onclick=\"window.location.href=app_path_webroot+'index.php?pid={$_GET['project']}'\"><i class=\"fa-solid fa-circle-arrow-right\"></i> Return to project</button>";
}

$html = "<h4><i class=\"fa-solid fa-right-to-bracket\"></i> Move a Project's Data to Another redcap_dataX Table</h4>
        <div class='fs15 mt-3'>This page allows administrators to move the data stored in a given REDCap project to another redcap_dataX table in the database in order to 
            improve the general performance of the project. The performance improvement will depend greatly on the size and structure of the project and will also depend on many things in the 
            overall system, such as the current size of the redcap_data table and the power of the database server. 
            Note: This process will perform multiple checks to ensure that all data gets moved successfully, and if anything goes wrong,
            it will automatically roll back all changes.</div>
        <div class='fs15 mt-5'>Enter the PID of a project to move their data from the <code>redcap_data</code> table to another database table:</div>"
    . "<div class='mt-1'>
            <input type='text' class='x-form-text x-form-field fs15' placeholder='e.g., 1234' value='".(htmlspecialchars($_GET['project']??'', ENT_QUOTES))."' 
            onblur=\"window.location.href=app_path_webroot+page+'?project='+trim(this.value);\"
            onkeydown=\"if (event.which == 13) window.location.href=app_path_webroot+page+'?project='+trim(this.value);\">
            <span class='text-secondary ml-3'>- or -</span>
            <button class='btn btn-sm btn-link fs15' onclick=\"window.location.href=app_path_webroot+page\">Start Over</button>
            $backToProjBtn
        </div>";

if (isset($_GET['project']) && isinteger($_GET['project'])) {
    // PID entered
    $currentDataTable = Records::getDataTable($_GET['project']);
    $Proj = new Project($_GET['project']);
    $html .= "<hr><h4 class='mt-3 text-dangerrc'>PID {$_GET['project']}: \"".strip_tags(html_entity_decode($Proj->project['app_title'], ENT_QUOTES))."\"</h4>";
    if (empty($Proj->project)) {
        $html .= "<div class='mt-4 fs15 text-danger'><i class=\"fa-solid fa-triangle-exclamation\"></i> ERROR: This project (PID {$_GET['project']}) does not exist. Try a different PID.</div>";
    } elseif ($currentDataTable != 'redcap_data') {
        $html .= "<div class='mt-4 fs15 text-danger'><i class=\"fa-solid fa-triangle-exclamation\"></i> ERROR: This project (PID {$_GET['project']}) has already been moved (or was created already using another data table).</div>";
    } else {
        if (!isset($_GET['commit']) && $Proj->project['online_offline']) {
            $html .= "<div class='mt-4 fs15 text-danger'><i class=\"fa-solid fa-triangle-exclamation\"></i> WARNING: This project (PID {$_GET['project']}) is currently in ONLINE mode and thus could potentially have users using it right now. It is highly recommended to go to the
                <a class='fs15' href='".APP_PATH_WEBROOT."ControlCenter/edit_project.php?project={$_GET['project']}'><u>Edit Project Settings page</u></a>, and set it to OFFLINE before proceeding below.
                This will ensure that this process will not cause any issues with the project's data. Once you've moved the project's data, be sure to set it back to ONLINE mode again, or instead you may check the checkbox below to bring it back ONLINE automatically.</div>";
        }
        $newDataTable = Records::getSmallestDataTable(true);
        $setOnline = (isset($_GET['set_online']) ? "\nUPDATE redcap_projects SET online_offline = 1 WHERE project_id = {$_GET['project']};" : "");
        $sql = "
SET AUTOCOMMIT=0; BEGIN;
INSERT INTO $newDataTable SELECT * FROM redcap_data WHERE project_id = {$_GET['project']};
UPDATE redcap_projects SET data_table = '$newDataTable' WHERE project_id = {$_GET['project']};
DELETE FROM redcap_data WHERE project_id = {$_GET['project']};$setOnline
COMMIT; SET AUTOCOMMIT=1;";
        // Commit the SQL
        if (isset($_GET['commit'])) {
            // Run the queries separately for validation purposes
            $errors = false;
            db_query("SET AUTOCOMMIT=0");
            db_query("BEGIN");
            $errors = !db_query("INSERT INTO $newDataTable SELECT * FROM redcap_data WHERE project_id = {$_GET['project']};");
            if (!$errors) {
                $dataPointsMoved = db_affected_rows();
                db_query("UPDATE redcap_projects SET data_table = '$newDataTable' WHERE project_id = {$_GET['project']};");
                db_query("DELETE FROM redcap_data WHERE project_id = {$_GET['project']};");
                $dataPointsDeleted = db_affected_rows();
                $errors = ($dataPointsMoved != $dataPointsDeleted);
                if ($setOnline != '') db_query($setOnline);
            }
            db_query($errors ? "ROLLBACK" : "COMMIT");
            db_query("SET AUTOCOMMIT=1");
            // Confirm the move happened or didn't
            $newDataTableConfirm = db_result(db_query("select data_table from redcap_projects where project_id = ".$_GET['project']), 0);
            if ($newDataTableConfirm != 'redcap_data') {
                $html .= "<div class='mt-4 fs15 text-successrc'><i class=\"fa-solid fa-check\"></i> SUCCESS: This project (PID {$_GET['project']}) has had its data successfully moved to <code>$newDataTable</code>.";
                if (!$Proj->project['online_offline'] && !isset($_GET['set_online'])) $html .= "<br><b>Be sure to set the project back to ONLINE mode again.</b></div>";
            } else {
                $html .= "<div class='mt-4 fs15 text-dangerrc'><i class=\"fa-solid fa-triangle-exclamation\"></i> UNKNOWN ERROR: Something went wrong when moving the data. The project's data is still stored in <code>redcap_data</code></div>";
            }
        } else {
            $setOnlineDisabled = $Proj->project['online_offline'] ? "disabled" : "";
            $setOnlineDisabledText = $Proj->project['online_offline'] ? "opacity65" : "";
            $html .= "<div class='mt-4 fs14 $setOnlineDisabledText'><input id='set_online' type='checkbox' $setOnlineDisabled onclick=\"window.location.href=app_path_webroot+page+'?project='+getParameterByName('project')+($(this).prop('checked') ? '&set_online=1' :'');\" ".(isset($_GET['set_online']) ? "checked" : "")."> <label for='set_online' style='cursor: pointer;'>Automatically set the project back \"Online\" again when finished?</label></div>";
            $html .= "<div class='mt-4 fs15 font-weight-bold'>SQL to be executed to move the project's data:</div>";
            $html .= "<div class='mt-2 fs15'>You can manually execute the SQL below using a MySQL client OR you can click the blue button to have REDCap do it. Run this SQL manually:</div><pre class='mt-3'>$sql</pre>";
            $html .= "<div class='mt-2 ml-4 pl-3 fs15 text-secondary'>- OR -</div>";
            $html .= "<div class='mt-3 fs15'><button class='btn btn-sm btn-primaryrc' onclick=\"showProgress(1);setTimeout(function(){window.location.href=app_path_webroot+page+'?project={$_GET['project']}".(isset($_GET['set_online']) ? "&set_online=1" : "")."&commit=1';},500);\">Commit the SQL</button></div>";
        }
    }
} elseif (isset($_GET['project']) && $_GET['project'] != '' && !isinteger($_GET['project'])) {
    $html .= "<div class='mt-4 fs15 text-dangerrc'><i class=\"fa-solid fa-triangle-exclamation\"></i> ERROR: The project PID is not valid.</div>";
}

// Render page
include 'header.php';
print $html;
include 'footer.php';
