<?php
namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

$prefix = ExternalModules::getPrefix();
$pid = ExternalModules::getProjectId($_POST['pid']);

echo json_encode(array(
    'status' => 'success',
    'url' => ExternalModules::getPageUrl($prefix, $_POST['page'])."&pid=".$pid
));