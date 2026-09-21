<?php
namespace Vanderbilt\REDCap\Tests;

require_once __DIR__ . '/../../redcap_connect.php';
require_once __DIR__ . '/vendor/autoload.php';

if($GLOBALS['is_development_server'] !== '1'){
	throw new \Exception('Unit tests are not allowed on production systems for safety reasons.');
}

$GLOBALS['db_file']   = dirname(APP_PATH_DOCROOT).'/database.php';
$GLOBALS['sql_file']  = APP_PATH_DOCROOT.'Resources/sql/install.sql';

function testlog($log)
{
	$l = sys_get_temp_dir() . '/test.log';
	file_put_contents($l, "$log\n", FILE_APPEND|LOCK_EX);
}

function shaStr($length=64)
{
	return substr(hash('sha512', microtime()), 0, $length);
}

function hashStr($length=16)
{
	return substr(md5(microtime()), 0, $length);
}

function rowCount($table)
{
	require $GLOBALS['db_file'];

	$c = mysqli_connect($hostname, $username, $password, $db);
	$sql = "SELECT COUNT(*) FROM $table";
	$q = mysqli_query($c, $sql);
	$row = mysqli_fetch_row($q);
	return $row[0];
}

function getFieldValue($table, $field, $where_field=null, $where_value=null)
{
	require $GLOBALS['db_file'];

	$c = mysqli_connect($hostname, $username, $password, $db);

	$where = ($where_field && $where_value) ? "WHERE $where_field = '$where_value'" : '';

	$sql = "
		SELECT $field
		FROM $table
		$where
		LIMIT 1
	";
	$q = mysqli_query($c, $sql);
	$row = mysqli_fetch_row($q);
	return $row[0];
}

function updateFieldValue($table, $field, $value, $where_field=null, $where_value=null)
{
	require $GLOBALS['db_file'];

	$c = mysqli_connect($hostname, $username, $password, $db);

	$where = ($where_field && $where_value) ? "WHERE $where_field = '$where_value'" : '';

	$sql = "
		UPDATE $table
		SET $field = '$value'
		$where
		LIMIT 1
	";
	mysqli_query($c, $sql);
	return mysqli_affected_rows($c);
}

function getAPIUrl()
{
	return getFieldValue('redcap_config', 'value', 'field_name', 'redcap_base_url') . 'api/';
}

function nextID($table, $field, $where_field=null, $where_value=null)
{
	require $GLOBALS['db_file'];
	$c = mysqli_connect($hostname, $username, $password, $db);

	$where = $where_field && $where_value ? "WHERE $where_field = '$where_value'" : '';
	
	$sql = "
		SELECT MAX($field) + 0
		FROM $table
		$where
	";

	$q = mysqli_query($c, $sql);
	if($q && $q !== false)
	{
		$row = mysqli_fetch_row($q);
		return $row[0] + 1;
	}

	return 1;
}

function now()
{
	return date('Y-m-d H:i:s');
}

function hoursAgo($hours)
{
	return date('Y-m-d H:i:s', strtotime("-$hours hour"));
}

function daysAgo($days)
{
	return date('Y-m-d', strtotime("-$days day"));
}

function email()
{
	return hashStr(8) . '@' . hashStr(8) . '.com';
}

function undef(...$str)
{
	foreach($str as $s)
	{
		//runkit_constant_remove($s);
	}
}

function badUser()
{
	return array('ui_id' => 'x');
}
