<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Validate all the parts
$pattern = '/^[a-zA-Z0-9_-]+$/';
if (!$isAjax
    || !isset($_POST['object']) || !preg_match($pattern, $_POST['object'])
    || !isset($_POST['name']) || !preg_match($pattern, $_POST['name'])
    || !isset($_POST['state']) || !in_array($_POST['state'], ['0','1'])
) {
    // Return error
    exit('0');
}
// Save it to UIState for user (not tied to a specific project)
UIState::saveUIStateValue("", $_POST['object'], $_POST['name'], $_POST['state']);
// Return 1 for success
exit('1');

