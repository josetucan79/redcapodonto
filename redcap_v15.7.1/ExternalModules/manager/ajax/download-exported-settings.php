<?php namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

// Mimic data dictionary download filenames
$app_title = \REDCap::getProjectTitle();
$filename = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 30)
		  . "_ModuleSettingsExport_".date("Y-m-d");

header('Content-Disposition: attachment; filename="' . $filename . '.zip"');

$path = ExternalModules::getAndClearExportedSettingsPath();
readfile($path);
unlink($path);