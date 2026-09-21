<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

$moduleDir = htmlentities($_POST['module_dir'], ENT_QUOTES);

// There is a super user check inside the following function.
print ExternalModules::deleteModuleDirectory($moduleDir);