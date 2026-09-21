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

// Import the file (with or without folder_id parameter) and return appropriate response
FileRepository::upload();