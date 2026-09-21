<?php
require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Remove illegal characters (if somehow posted bypassing javascript)
$user = preg_replace("/[^a-zA-Z0-9-'\s\.@_]/", "", $_POST['username']);
if (!isset($_POST['username']) || $user != $_POST['username']) exit('');
$user = $_POST['username'];

// Get all user rights as array
$rightsAllUsers = UserRights::getRightsAllUsers();

$group_id = (isset($rightsAllUsers[$user])) ? $rightsAllUsers[$user]['group_id'] : '';
$role_id = (isset($rightsAllUsers[$user])) ? $rightsAllUsers[$user]['role_id'] : '';
$user_exists = (isset($rightsAllUsers[$user])) ? true : false;

print json_encode_rc(array('group_id' => $group_id, 'role_id' => $role_id, 'user_exists' => $user_exists));