<?php namespace ExternalModules;
require_once __DIR__ . '/../../redcap_connect.php';

// Only administrators can perform this action
if (!ExternalModules::isAdminWithModuleInstallPrivileges()) return;

/**
 * @psalm-taint-escape header
 */
$prefix = ExternalModules::getPrefix();
$projects = ExternalModules::getEnabledProjects($prefix);

header("Content-Disposition: attachment; filename=\"$prefix - Design Rights Users.csv\"");

$pids = [];
$questionMarks = [];
while($project = $projects->fetch_assoc()){
    $pids[] = $project['project_id'];
    $questionMarks[] = '?';
}

$questionMarks = implode(',', $questionMarks);

// Start outputting the CSV
$handle = fopen('php://output', 'w+');
fputcsv($handle, ['Project ID', 'Project Name', 'Design Rights User Emails'], ',', '"', '');

// Only query when there are actually any project ids
if (count($pids) > 0) {
    $result = ExternalModules::query("
        select p.project_id, p.app_title, u.user_email
        from redcap_user_rights r
        join redcap_user_information u
            on u.username = r.username
        join redcap_projects p
            on r.project_id = p.project_id
        where 
            p.project_id in ($questionMarks)
            and design = 1
    ", $pids);

    if ($result->num_rows == 0) {
        fputcsv($handle, ['No design rights users found'], ',', '"', '');
    }
    while($row = $result->fetch_row()){
        fputcsv($handle, $row, ',', '"', '');
    }
}
else {
    fputcsv($handle, ['This module is not enabled on any project'], ',', '"', '');
}