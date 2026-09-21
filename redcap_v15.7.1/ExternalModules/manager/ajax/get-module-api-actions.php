<?php
namespace ExternalModules;

require_once __DIR__ . '/../../redcap_connect.php';

if(!\UserRights::displayExternalModulesMenuLink()){
	//= You don't have permission to manage external modules on this project.
	echo ExternalModules::tt("em_errors_72"); 
	return;
}

$pid = intval($_POST["pid"]);

echo json_encode([
	"title" => ExternalModules::tt("em_manage_156"),
	"content" => ExternalModules::getApiActionsInfoTableForEnabledModules($pid),
], JSON_UNESCAPED_UNICODE);
