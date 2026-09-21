<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

// Only administrators can perform this action
if (!ExternalModules::isAdminWithModuleInstallPrivileges()) return;

$projects = ExternalModules::getEnabledProjects(ExternalModules::getPrefix());

while($project = $projects->fetch_assoc()){
	$url = APP_PATH_WEBROOT . 'ProjectSetup/index.php?pid=' . $project['project_id'];
	?><a href="<?=$url?>" style="text-decoration: underline;"><?=\RCView::escape(strip_tags($project['name']))?></a><br><?php
}