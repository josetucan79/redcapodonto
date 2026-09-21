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

// Create folder and do logging
$newFolderId = FileRepository::createFolder();
$response = [['folder_id'=>$newFolderId]];

# structure the output data accordingly
switch($format)
{
    case 'json':
        $content = json_encode_rc($response);
        break;
    case 'xml':
        $content = xml($response);
        break;
    case 'csv':
        $content = arrayToCsv($response);
        break;
}

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
            } else {
                $line .= "<$item></$item>";
            }
        }

        $output .= "<item>$line</item>\n";
    }
    $output .= "</items>\n";

    return $output;
}