<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (isset($_POST["collapse-all-fields"])) {
    if ($_POST["collapse-all-fields"] == "1") {
        UIState::saveUIStateValue($project_id, "codebook", "collapse-all-fields", "1");
    } else {
        UIState::removeUIStateValue($project_id, 'codebook', "collapse-all-fields");
    }
}
if (isset($_POST["collapse-all-tables"])) {
    if ($_POST["collapse-all-tables"] == "1") {
        UIState::saveUIStateValue($project_id, "codebook", "collapse-all-tables", "1");
    } else {
        UIState::removeUIStateValue($project_id, 'codebook', "collapse-all-tables");
    }
}
// No return value or error checks