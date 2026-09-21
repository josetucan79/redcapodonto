<?php
// Required files
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

extract($GLOBALS);

$count = 0;
$errors = array();
$csv_content = $preview = "";
$commit = false;
if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name'])) {
    $csv_content = file_get_contents($_FILES['file']['tmp_name']);
} elseif (isset($_POST['csv_content']) && $_POST['csv_content'] != '') {
    $csv_content = $_POST['csv_content'];
    $commit = true;
}

if ($csv_content != "")
{
    $data = csvToArray(removeBOM($csv_content));

    // Begin transaction
    db_query("SET AUTOCOMMIT=0");
    db_query("BEGIN");

    // Instantiate DataQuality object
    $dq = new DataQuality();

    list ($count, $errors) = $dq->uploadDQRules(PROJECT_ID, $data);
    // Build preview of changes being made
    if (!$commit && empty($errors))
    {
        $cells = "";
        foreach (array_keys($data[0]) as $this_hdr) {
            $cells .= RCView::td(array('style'=>'padding:6px;font-size:13px;border:1px solid #ccc;background-color:#000;color:#fff;font-weight:bold;'), $this_hdr);
        }
        $rows = RCView::tr(array(), $cells);

        foreach($data as $dq_rule)
        {
            $rule_name = trim($dq_rule['rule_name']);
            $rule_logic = trim($dq_rule['rule_logic']);
            $real_time_execution = (trim($dq_rule['real_time_execution']) != '') ? trim($dq_rule['real_time_execution']) : 'n';

            // Add row
            $rows .= RCView::tr(array(),
                RCView::td(array('class'=>'green'), $rule_name) .
                    RCView::td(array('class'=>'green'), $rule_logic) .
                    RCView::td(array('class'=>'green'), $real_time_execution)
                );
        }
        $preview = RCView::table(array('cellspacing'=>1), $rows);
    }
    if ($commit && empty($errors)) {
        // Commit
        $csv_content = "";
        db_query("COMMIT");
        db_query("SET AUTOCOMMIT=1");
        Logging::logEvent("", "redcap_data_quality_rules", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Upload Data Quality Rules");
    } else {
        // ERROR: Roll back all changes made and return the error message
        db_query("ROLLBACK");
        db_query("SET AUTOCOMMIT=1");
    }

    array_walk($errors, 'appendRowPrefix');

    $_SESSION['imported'] = 'dqrules';
    $_SESSION['count'] = $count;
    $_SESSION['errors'] = $errors;
    $_SESSION['csv_content'] = $csv_content;
    $_SESSION['preview'] = $preview;
}

function appendRowPrefix(&$errors, $key)
{
    global $lang;
    // Incrementing row count by 1 as first row of CSV will be header
    $row_num_prefix = "<b>{$lang['dataqueries_343']}".($key + 1)."</b>: ";
    $errors = $row_num_prefix . $errors;
}

redirect(APP_PATH_WEBROOT . 'DataQuality/index.php?pid=' . PROJECT_ID);