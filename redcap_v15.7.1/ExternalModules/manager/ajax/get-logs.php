<?php namespace ExternalModules;

require_once __DIR__ . '/../../redcap_connect.php';

$pid = ExternalModules::getProjectId();
if(!empty($pid)){
	ExternalModules::requireDesignRights($pid);
}
else if(!ExternalModules::isAdminWithModuleInstallPrivileges()){
	echo 'You do not have access to this page.';
	return;
}

$parameters = [
	$_GET['messageLengthLimit'],
	$_GET['paramLengthLimit'],
	$_GET['start'],
	$_GET['end']
];

$whereClause = '
	where
		timestamp >= ? 
		and timestamp < ?
';

if(!empty($pid)){
	$whereClause .= ' and project_id = ?';
	$parameters[] = $pid;
}

$prefixes = $_GET['modules'];
if(!empty($prefixes)){
	foreach($prefixes as $prefix){
		$questionMarks[] = '?';
		$parameters[] = $prefix;
	}

	$whereClause .= ' and directory_prefix in (' . implode(',', $questionMarks) . ') ';
}

$results = ExternalModules::query("
	select
		l.log_id,
		u.username,
		timestamp,
		directory_prefix,
		project_id,
		record,
		substring(message, 1, ?) as message,
		p.name as param_name,
		substring(p.value, 1, ?) as param_value
	from redcap_external_modules_log l
	left join redcap_external_modules_log_parameters p
		on p.log_id = l.log_id
	join redcap_external_modules m
		on m.external_module_id = l.external_module_id
	left join redcap_user_information u
		on l.ui_id = u.ui_id
	$whereClause
	order by l.log_id desc
", $parameters);

$rows = [];
$lastRow = null;
while($row = $results->fetch_assoc()){
	$row = ExternalModules::escape($row);

	$paramName = $row['param_name'];
	unset($row['param_name']);
	$paramValue = $row['param_value'];
	unset($row['param_value']);

	if($row['log_id'] !== ($lastRow['log_id'] ?? null)){
		$rows[] = $row;
		$lastRow = &$rows[count($rows)-1];
	}

	if(
		$paramName !== null
		&&
		// Some params contain sensitive values per https://github.com/vanderbilt-redcap/external-module-framework/discussions/612
		ExternalModules::isAdminWithModuleInstallPrivileges()
	){
		$lastRow['params'][$paramName] = $paramValue;
	}
}

echo json_encode([
	'data' => $rows
], JSON_PRETTY_PRINT);
