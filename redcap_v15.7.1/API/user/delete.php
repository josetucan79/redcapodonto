<?php
global $format, $returnFormat, $post;

// Check for required privileges
if ($post['user_rights'] != '1') die(RestUtility::sendResponse(400, $lang['api_103'], $returnFormat));

# Logging
Logging::logEvent("", "redcap_users", "MANAGE", PROJECT_ID, "user_id in (" . implode(", ", $post['users']) . ")", "Delete users (API$playground)");

# get all the records to be exported
$content = delUsers();

# Send the response to the requestor
RestUtility::sendResponse(200, $content, $format);


function delUsers()
{
	global $post, $lang, $Proj;

	if(!isset($post['users']) || empty($post['users']) || !is_array($post['users'])) {
		die(RestUtility::sendResponse(400, $lang['api_161']));
	}

	// Begin transaction
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");

	$count = 0;
	$errors = array();

    $ExtRes = new ExternalLinks();
    foreach($post['users'] as $user)
	{
        $username = trim(strip_tags(html_entity_decode($user, ENT_QUOTES)));
        $userRights = UserRights::getPrivileges(PROJECT_ID, $username)[PROJECT_ID];
        $userExists = (is_array($userRights[$username]) && !empty($userRights[$username])) ? '1' : '0';
		if ($username != '' && $userExists == 1)
		{
            UserRights::removePrivileges(PROJECT_ID, $username, $ExtRes);
            $count++;
		}
		else
		{
			$errors[] = $username;
		}
	}

	if (!empty($errors)) {
		db_query("ROLLBACK");
		db_query("SET AUTOCOMMIT=1");
		die(RestUtility::sendResponse(400, $lang['api_160'] . " " . implode(", ", $errors)));
	}

	db_query("COMMIT");
	db_query("SET AUTOCOMMIT=1");

	return $count;
}
