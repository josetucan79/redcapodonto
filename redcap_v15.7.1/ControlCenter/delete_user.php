<?php


require dirname(dirname(__FILE__)) . "/Config/init_global.php";

if ((SUPER_USER || ACCOUNT_MANAGER) && isset($_POST['username'])) 
{
	// Make sure that account managers cannot delete administrator accounts
	$thisUserInfo = User::getUserInfo($_POST['username']);
	if ($thisUserInfo === false || (ACCOUNT_MANAGER && $thisUserInfo['super_user'])) {
		exit('0');
	}
	// Add project-level logged event for all projects that this user will be removed from
	$q2 = db_query("select project_id from redcap_user_rights where username = '".db_escape($_POST['username'])."'");
	while ($row = db_fetch_assoc($q2)) {
		// Remove user from user rights table
		$sql = "delete from redcap_user_rights where username = '".db_escape($_POST['username'])."' and project_id = ".$row['project_id'];
		db_query($sql);
		Logging::logEvent($sql,"redcap_user_rights","delete",$_POST['username'],"user = '".db_escape($_POST['username'])."'","Delete user from REDCap", "", "", $row['project_id']);
	}
	// Remove user from user info table
	$q1 = db_query("delete from redcap_user_information where username = '".db_escape($_POST['username'])."'");
	$q1_rows = db_affected_rows();
	// Remove user from auth table (in case if using Table-based authentication)
	$q3 = db_query("delete from redcap_auth where username = '".db_escape($_POST['username'])."'");
	// Remove user from table
	$q4 = db_query("delete from redcap_user_allowlist where username = '".db_escape($_POST['username'])."'");
	// Remove user from table
	$q5 = db_query("delete from redcap_auth_history where username = '".db_escape($_POST['username'])."'");
	// Remove user from table
	$q6 = db_query("delete from redcap_external_links_users where username = '".db_escape($_POST['username'])."'");
	// Remove user from table
	db_query("delete from redcap_data_access_groups_users where username = '".db_escape($_POST['username'])."'");
    // If user is anyone's user sponsor, remove them as a sponsor in all user accounts
    db_query("update redcap_user_information set user_sponsor = null where user_sponsor = '".db_escape($_POST['username'])."'");

	// If all queries ran as expected, give positive response
	if ($q1_rows == 1 && $q1 && $q2 && $q3 && $q4 && $q5 && $q6) {
		// Logging
		Logging::logEvent("","redcap_user_information\nredcap_user_rights\nredcap_auth\nredcap_auth_history\nredcap_external_links_users","MANAGE",$_POST['username'],"username = '".db_escape($_POST['username'])."'","Delete user from REDCap");
		// Give positive response
		exit("1");
	}
}

exit("0");
