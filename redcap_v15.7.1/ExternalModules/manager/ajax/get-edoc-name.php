<?php namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';
require_once APP_PATH_DOCROOT.'Classes/Files.php';

ExternalModules::requireDesignRights();

$edoc = $_POST['edoc'];

$doc_name = "";
if (($edoc) && (is_numeric($edoc))) {
	$ary = \Files::getEdocContentsAttributes((integer) $edoc);
	$doc_name = $ary[1];
}

header('Content-type: application/json');
echo json_encode(array(
        'doc_name' => $doc_name,
        'status' => 'success'
));
