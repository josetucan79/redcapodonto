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

// Set date deletion timestamp to NOW
$sql = "UPDATE redcap_edocs_metadata SET delete_date = '".NOW."' 
		WHERE doc_id = {$post['doc_id']} AND project_id = ".PROJECT_ID;
db_query($sql);

// Set who deleted the file in the comments
$docs_id = FileRepository::getDocsIdFromDocId($post['doc_id']);
$comment = db_result(db_query("select docs_comment from redcap_docs where docs_id = $docs_id AND project_id = ".PROJECT_ID), 0);
$comment = trim(trim($comment ?? "")."\n".$lang['docs_1097']." ".USERID." ".$lang['global_15']." ".NOW.$lang['period']);
$sql2 = "UPDATE redcap_docs SET docs_comment = '".db_escape($comment)."' 
		 WHERE docs_id = $docs_id AND project_id = ".PROJECT_ID;
db_query($sql2);

// Log the event
$docName = Files::getEdocName($post['doc_id']);
list ($folderName, $nothing) = FileRepository::getFolderNameAndParentFromId(FileRepository::getFolderIdByDocId($post['doc_id']));
if ($folderName == null) $folderName = $lang['docs_1131'];
Logging::logEvent($sql.";\n$sql2","redcap_edocs_metadata","MANAGE",$post['doc_id'],"$docName, folder $folderName","Delete file from File Repository (API$playground)");

// Send the response to the requester
RestUtility::sendResponse(200);
