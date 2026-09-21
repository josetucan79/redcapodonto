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

// Validate the doc_id, which corresponds to the redcap_edocs_metadata table primary key
if (!Files::validateDocId($post['doc_id']??null, PROJECT_ID)) {
	exit(RestUtility::sendResponse(400, "Invalid doc_id or missing doc_id"));
}

// Obtain file and store in temp
list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($post['doc_id']);

// Log the event
list ($folderName, $nothing) = FileRepository::getFolderNameAndParentFromId(FileRepository::getFolderIdByDocId($post['doc_id']));
if ($folderName == null) $folderName = $lang['docs_1131'];
Logging::logEvent("","redcap_edocs_metadata","MANAGE",$post['doc_id'],"$docName, folder $folderName","Download file from File Repository (API$playground)");

// Send the response to the requester
RestUtility::sendFileContents(200, $fileContent, $docName, $mimeType);