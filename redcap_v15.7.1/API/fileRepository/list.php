<?php

# get project information
$Proj = new Project();
$longitudinal = $Proj->longitudinal;

// Get user's user rights
$user_rights = UserRights::getPrivileges(PROJECT_ID, USERID);
$user_rights = $user_rights[PROJECT_ID][strtolower(USERID)];

// If user doesn't have access to the File Repository, then return error
if ($user_rights['file_repository'] == '0') {
	exit(RestUtility::sendResponse(403, 'The API request cannot complete because currently you do not have "File Repository" rights, which are required for this operation.'));
}

// Check folder_id, if passed. Check file's DAG access and role access.
$folder_id = (isset($post['folder_id']) && isinteger($post['folder_id'])) ? $post['folder_id'] : null;
if (!FileRepository::userHasFolderAccess($folder_id)) {
	exit(defined("API") ? RestUtility::sendResponse(400, "The File Repository folder folder_id={$folder_id} does not exist or else you do not have permission to that folder because it is DAG-restricted or Role-restricted.") : '0');
}

// Get folders
$folders = [];
$dagsql = ($user_rights['group_id'] == "") ? "" : "and (dag_id is null or dag_id = ".$user_rights['group_id'].")";
$rolesql = ($user_rights['role_id'] == "") ? "" : "and (role_id is null or role_id = ".$user_rights['role_id'].")";
$sql = "select folder_id, name, dag_id, role_id from redcap_docs_folders where project_id = ".PROJECT_ID." $dagsql $rolesql
        and parent_folder_id " . (isinteger($folder_id) ? "= $folder_id" : "is null")." and deleted = 0";
$q = db_query($sql);
while ($row = db_fetch_assoc($q))
{
    $folders[$row['folder_id']] = strip_tags(label_decode($row['name']));
}
natcasesort($folders);
$foldersSorted = [];
foreach ($folders as $thisFolderId=>$thisName) {
    if ($format == 'csv') {
        $row = ['folder_id'=>$thisFolderId, 'doc_id'=>'', 'name'=>$thisName];
    } else {
        $row = ['folder_id'=>$thisFolderId, 'name'=>$thisName];
    }
    $foldersSorted[] = $row;
}
$folders = $foldersSorted;

// Get files for current folder
$files = [];
$sql = "select e.doc_id, d.docs_name
        from redcap_docs_to_edocs de, redcap_edocs_metadata e, redcap_docs d
        left join redcap_docs_folders_files ff on ff.docs_id = d.docs_id
        left join redcap_docs_folders f on ff.folder_id = f.folder_id
        where d.project_id = ".PROJECT_ID." and d.export_file = 0
        and de.docs_id = d.docs_id and de.doc_id = e.doc_id and e.date_deleted_server is null 
        and e.delete_date is null and ff.folder_id " . (isinteger($folder_id) ? "= $folder_id" : "is null");
$q = db_query($sql);
while ($row = db_fetch_assoc($q))
{
    $files[$row['doc_id']] = strip_tags(label_decode($row['docs_name']));
}
natcasesort($files);
$filesSorted = [];
foreach ($files as $thisFileId=>$thisName) {
    if ($format == 'csv') {
        $row = ['folder_id'=>'', 'doc_id'=>$thisFileId, 'name'=>$thisName];
    } else {
        $row = ['doc_id'=>$thisFileId, 'name'=>$thisName];
    }
    $filesSorted[] = $row;
}
$files = $filesSorted;

// Combine files and folders
$items = array_merge($folders, $files);

# structure the output data accordingly
switch($format)
{
    case 'json':
        $content = json_encode_rc($items);
        break;
    case 'xml':
        $content = xml($items);
        break;
    case 'csv':
        $content = arrayToCsv($items);
        break;
}

// Log the event
list ($folderName, $nothing) = FileRepository::getFolderNameAndParentFromId($folder_id);
if ($folderName == null) $folderName = $lang['docs_1131'];
Logging::logEvent("","redcap_edocs_metadata","MANAGE",$folder_id,"folder $folderName","Export list of files/folders from File Repository (API$playground)");

// Send the response to the requester
RestUtility::sendResponse(200, $content, $format);


function xml($dataset)
{
    $output = '<?xml version="1.0" encoding="UTF-8" ?>';
    $output .= "\n<items>\n";

    foreach ($dataset as $row)
    {
        $line = '';
        foreach ($row as $item => $value) {
            if (($item == 'folder_id' || $item == 'doc_id') && isinteger($value)) {
                $line .= "<$item>$value</$item>";
            } elseif ($value != "") {
                $line .= "<$item><![CDATA[" . html_entity_decode($value, ENT_QUOTES) . "]]></$item>";
            } else {
                $line .= "<$item></$item>";
            }
        }

        $output .= "<item>$line</item>\n";
    }
    $output .= "</items>\n";

    return $output;
}