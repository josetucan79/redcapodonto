<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;

require_once dirname(__DIR__, 3) . '/Config/init_project.php';

// If user does not have Project Setup/Design rights, do not show this page
// if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$ui_id = defined('UI_ID') ? UI_ID : false;
$canView = FhirEhr::canAccessCdpDashboard($project_id, $ui_id);
if(!$canView) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

$lang['data_fetching_dashborad_title'] = 'Clinical Data Pull Dashboard';
?>
<?= sprintf('<div class="projhdr"><i class="fas fa-gauge-high"></i> %s</div>', $lang['data_fetching_dashborad_title']) ?>