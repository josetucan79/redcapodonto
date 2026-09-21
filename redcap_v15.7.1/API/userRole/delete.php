<?php
global $format, $returnFormat, $post;

// Check for required privileges
if ($post['user_rights'] != '1') die(RestUtility::sendResponse(400, $lang['api_171'], $returnFormat));

# Logging
Logging::logEvent("", "redcap_user_roles", "MANAGE", PROJECT_ID, "unique_role_name in (" . implode(", ", $post['roles']) . ")", "Delete user roles (API$playground)");

# get all the records to be exported
$content = delUserRoles();

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);

function delUserRoles()
{
	global $post, $lang, $Proj;

	if(!isset($post['roles']) || empty($post['roles']) || !is_array($post['roles'])) {
		die(RestUtility::sendResponse(400, $lang['api_167']));
	}

	// Begin transaction
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");

	$count = 0;
	$errors = array();

	$allRoles = UserRights::getRoles();
    $roles = array();
	foreach ($allRoles as $roleId => $roleInfo) {
	    $roles[$roleId] = $roleInfo['unique_role_name'];
        $roleNames[$roleId] = $roleInfo['role_name'];
    }
    foreach($post['roles'] as $role)
	{
        $rolename = trim(strip_tags(html_entity_decode($role, ENT_QUOTES)));
        $roleExists = (in_array($rolename, $roles)) ? '1' : '0';
		if ($rolename != '' && $roleExists == 1)
		{
		    $roleId = array_search($rolename, $roles);
		    $roleLabel = $roleNames[$roleId];
            UserRights::removeRole($Proj->project_id, $roleId, $roleLabel);
            $count++;
		}
		else
		{
			$errors[] = $rolename;
		}
	}

	if (!empty($errors)) {
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
		die(RestUtility::sendResponse(400, $lang['api_170'] . " " . implode(", ", $errors)));
	}

	db_query("COMMIT");
	db_query("SET AUTOCOMMIT=1");

	return $count;
}
