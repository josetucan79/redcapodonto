<?php


// Require the System class
require_once dirname(dirname(__FILE__)) . '/Classes/System.php';
// Initialize REDCap
System::init();

use Vanderbilt\REDCap\Classes\SessionData;

//Connect to the MySQL project where the REDCap tables are kept
function db_connect($reportOnlySuperUserErrors=false, $useUpgradesUser=false, $createReadReplicaConnection=false, $forceReadReplicaConnection=false, $bypassReadReplicaLagCheck=false)
{
	global $rc_connection, $conn, $redcap_updates_user, $redcap_updates_password;
	$db_error_msg = "";
	$readReplicaIsAvailable = false;
	// For install page, do not report errors here (because messes up installation workflow)
	if (!isset($reportErrors) || $reportErrors == null) {
		$reportErrors = (basename($_SERVER['PHP_SELF']) != 'install.php');
	}
	// Include db file
	$db_conn_file = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'database.php';
	include $db_conn_file;
	// CI Alternative: Use specific environment variables (if available) instead of those in database.php
    if (isset($_SERVER['MYSQL_REDCAP_CI_HOSTNAME'])) {
        $hostname = $_SERVER['MYSQL_REDCAP_CI_HOSTNAME'];
        $username = $_SERVER['MYSQL_REDCAP_CI_USERNAME'];
        $password = $_SERVER['MYSQL_REDCAP_CI_PASSWORD'];
        $db = $_SERVER['MYSQL_REDCAP_CI_DB'];
        $salt = sha1($password);
    }
	// If using the read replica db server for reads at this endpoint, set new hostname, etc. for new connection to replica
	if (isset($read_replica_hostname) && $read_replica_hostname != '') {
		// If replica can be used at this endpoint, then make sure it does not lag behind the primary database by more than System::REPLICA_LAG_MAX seconds
		$readReplicaIsAvailable = ($forceReadReplicaConnection || System::useReadReplicaDB());
	}
	if ($readReplicaIsAvailable && $createReadReplicaConnection) {
		// Get replica connection parameters
		$hostname = $read_replica_hostname ?? "";
		$username = $read_replica_username ?? "";
		$password = $read_replica_password ?? "";
		$db = $read_replica_db ?? "";
        // Get SSL-specific connection parameters, if used
        if (isset($read_replica_db_ssl_ca) && $read_replica_db_ssl_ca != '') {
            $db_ssl_key     = $read_replica_db_ssl_key ?? '';
            $db_ssl_cert    = $read_replica_db_ssl_cert ?? '';
            $db_ssl_ca      = $read_replica_db_ssl_ca ?? '';
            $db_ssl_capath  = $read_replica_db_ssl_capath ?? null;
            $db_ssl_cipher  = $read_replica_db_ssl_cipher ?? null;
            $db_ssl_verify_server_cert = $read_replica_db_ssl_verify_server_cert ?? false;
        }
	} else {
		// Reset $rc_connection in case exists already somehow (but not when creating a replica connection)
		$rc_connection = null;
	}
	// If we already have $conn from redcap_connect.php, then don't create a new connection
	if (!$useUpgradesUser && isset($conn) && is_object($conn) && !($readReplicaIsAvailable && $createReadReplicaConnection)) {
		$rc_connection = $conn;
	} else {
		if ($useUpgradesUser) {
			// User the upgrades user/password for the connection
			$username = $redcap_updates_user;
			$password = decrypt($redcap_updates_password);
		}
		// Check the connection values
		if (!isset($db_socket)) $db_socket = null;
		if (!isset($username) || !isset($password) || !isset($db) || (!isset($hostname) && !isset($db_socket))) {
			$db_error_msg = "One or more of your database connection values (\$hostname, \$db, \$username, \$password)
							 could not be found in your database connection file [$db_conn_file]. Please make sure all four variables are
							 defined with a correct value in that file.";
		}
		// First, check that MySQLi extension is installed
		if (!function_exists('mysqli_connect')) {
			exit("<p style='margin:30px;width:700px;'><b>ERROR: MySQLi extension in PHP is not installed!</b><br>
				  REDCap 5.1.0 and later versions require the MySQLi extension in PHP. You will need to first install PHP's MySQLi
				  extension on your webserver before you can continue further.
				  <a target='_blank' href='http://php.net/manual/en/mysqli.setup.php'>Download and install the MySQLi extension</a><br><br>
				  <b>Why has this changed from previous REDCap versions?</b><br>
				  PHP 5.5 and later versions no longer support the MySQL extension, which was used in prior versions of REDCap, thus
				  REDCap now utilizes the MySQLi extension instead.
				  </p>");
		}
		if ($db_socket !== null) {
			if ($password == '') $password = null;
		}
        try {
            if (isset($db_ssl_ca) && $db_ssl_ca != '') {
                // Connect to MySQL via SSL
                defined("MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT") or define("MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT", 64);
                $this_connection = mysqli_init();
                mysqli_options($this_connection, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
                mysqli_ssl_set($this_connection, $db_ssl_key, $db_ssl_cert, $db_ssl_ca, $db_ssl_capath, $db_ssl_cipher);
                $conn_ssl = mysqli_real_connect($this_connection, remove_db_port_from_hostname($hostname), $username, $password, $db, get_db_port_by_hostname($hostname, $db_socket), $db_socket, ((isset($db_ssl_verify_server_cert) && $db_ssl_verify_server_cert) ? MYSQLI_CLIENT_SSL : MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT));
            } else {
                // Connect to MySQL normally
                $this_connection = mysqli_connect(remove_db_port_from_hostname($hostname), $username, $password, $db, get_db_port_by_hostname($hostname, $db_socket), $db_socket);
            }
        } catch(Throwable $ex) {
            $this_connection = $conn_ssl = false;
        }
		if (!$this_connection || (isset($conn_ssl) && !$conn_ssl)) {
			// If we failed to connect to the replica OR if we're using the secondary Upgrade user, then fail gracefully
			if ($useUpgradesUser || ($readReplicaIsAvailable && $createReadReplicaConnection)) {
				// Reset $hostname, $db, etc. variables because they wil be set to global scope below
				include $db_conn_file;
                // Kill replica connection variable
                if ($readReplicaIsAvailable && $createReadReplicaConnection) {
                    unset($GLOBALS['rc_replica_connection']);
                }
				// Return false to fail gracefully
				return false;
			}
			$db_error_msg = "Your REDCap database connection file [$db_conn_file] could not connect to the database server.
							 Please check the connection values in that file (\$hostname, \$db, \$username, \$password)
							 because they may be incorrect.";
		}
        // Establish replica connection and check lag time
		if ($readReplicaIsAvailable && $createReadReplicaConnection) {
            // Set replica connection as $rc_replica_connection
			$GLOBALS['rc_replica_connection'] = $this_connection;
            // If lag time is too high, do not use replica connection (if we're in a project context,
            // use the project's last write log event time instead of the standard max replica lag time)
            $pid = ((defined("API") && isset($_POST['token'])) ? Project::getProjectIdFromApiToken($_POST['token']) : ($_GET['pid'] ?? null));
            if (!$bypassReadReplicaLagCheck && System::readReplicaLagTimeIsTooHigh($pid)) {
                // Stop here to return to the first call to db_connect() for the primary db
                unset($GLOBALS['rc_replica_connection']);
                return false;
            }
		} else {
            // Set connection as $rc_connection
			$rc_connection = $this_connection;
			// Set secondary db connection variable that can be used in hooks and plugins
			if (!$useUpgradesUser) $conn = $rc_connection;
		}
	}

	// READ REPLICA CONNECTION: If we had a read replica db server that should be used for reads on certain endpoints (reports, API), create new db connection for it too
	if ($readReplicaIsAvailable) {
		if (!$createReadReplicaConnection && System::readReplicaEnabledInConfig()) {
			// Recursive call to db_connect to establish replica connection now that we have already established the primary db connection
			$readReplicaIsAvailable = db_connect(false, false, true);
		} else {
			// Reset $hostname, $db, etc. variables because they will be set to global scope below
			include $db_conn_file;
		}
	}

    // Manually set mysqli_report to OFF for compatibility with PHP 8.1, which defaults to MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT.
    mysqli_report(MYSQLI_REPORT_OFF);

    // Set certain db settings if REDCap has already been installed
    if (($rc_connection || ($readReplicaIsAvailable && $createReadReplicaConnection && $GLOBALS['rc_replica_connection'])) && basename($_SERVER['PHP_SELF']) != "install.php") {
        // Set sql_mode
        System::setDbSqlMode($createReadReplicaConnection);
        // Set binlog_format (if applicable)
        System::setDbBinLogFormat($createReadReplicaConnection);
        // Ensure that the db character set encoding is set correctly
        System::checkDbEncoding($createReadReplicaConnection);
    }

	// If there was a db connection error, then display it
	if (($reportErrors || $reportOnlySuperUserErrors) && $db_error_msg != "")
	{
		// Display message to user
		if ($reportOnlySuperUserErrors) {
			print RCView::div(array('class'=>'red', 'style'=>'margin-bottom:20px;'), "<b>ERROR:</b> $db_error_msg");
		} else {
			?>
			<div style="font: normal 12px Verdana, Arial;padding:20px;border: 1px solid red;color: #800000;max-width: 600px;background: #FFE1E1;">
				<div style="font-weight:bold;font-size:15px;padding-bottom:5px;">
					CRITICAL ERROR: REDCap server is offline!
				</div>
				<div>
					For unknown reasons, REDCap cannot communicate with its database server, which may be offline. Please contact your
					local REDCap administrator to inform them of this issue immediately. If you are a REDCap administrator, then please see this
					<a href="javascript:;" style="color:#000066;" onclick="document.getElementById('db_error_msg').style.display='block';">additional information</a>.
					We are sorry for any inconvenience.
				</div>
				<div id="db_error_msg" style="display:none;color:#333;background:#fff;padding:5px 10px 10px;margin:20px 0;border:1px solid #bbb;">
					<b>Message for REDCap administrators:</b><br/><?php echo $db_error_msg ?>
				</div>
			</div>
			<?php
		}
		exit;
	}
	// Get the SALT, which is institutional-unique alphanumeric value, and is found in the Control Center db connection file.
	// It is the first part of the total salt used for Date Shifting and (eventually) Encryption at Rest
	if ($reportErrors && (!isset($salt) || (isset($salt) && empty($salt))))
	{
		// Warn user that the SALT was not defined in the connection file and give them new salt
		exit(  "<div style='font-family:Verdana;font-size:12px;line-height:1.5em;padding:25px;'>
				<b>ERROR:</b><br>
				REDCap could not find the variable <b>\$salt</b> defined in [<font color='#800000'>$db_conn_file</font>].<br><br>
				Please open the file for editing and add the following code after your database connection variables:<br><br>
				<b>\$salt = \"".substr(hash('sha512', rand()),0,100)."\";</b>
				</div>");
	}
	// Set global variables
	$GLOBALS['hostname'] = $hostname;
	$GLOBALS['username'] = $username;
	$GLOBALS['password'] = $password;
	$GLOBALS['db'] 		 = $db;
	$GLOBALS['salt'] 	 = $salt;
	if (isset($db_ssl_ca) && $db_ssl_ca != '') {
		$GLOBALS['db_ssl_key'] = $db_ssl_key;
		$GLOBALS['db_ssl_cert'] = $db_ssl_cert;
		$GLOBALS['db_ssl_ca'] = $db_ssl_ca;
		$GLOBALS['db_ssl_capath'] = $db_ssl_capath;
		$GLOBALS['db_ssl_cipher'] = $db_ssl_cipher;
	}
	// DTS connection variables
	if (isset($dtsHostname)) {
		$GLOBALS['dtsHostname'] = $dtsHostname;
		$GLOBALS['dtsUsername'] = $dtsUsername;
		$GLOBALS['dtsPassword'] = $dtsPassword;
		$GLOBALS['dtsDb']		= $dtsDb;
	}
	// Read Replica connection variables
	if (isset($read_replica_hostname) && $read_replica_hostname != '') {
		$GLOBALS['read_replica_hostname'] = $read_replica_hostname;
		$GLOBALS['read_replica_username'] = $read_replica_username;
		$GLOBALS['read_replica_password'] = $read_replica_password;
		$GLOBALS['read_replica_db'] = $read_replica_db;
	}
	return true;
}

## ABSTRACTED DATABASE FUNCTIONS
## Replaced mysql_* functions with db_* functions, which are merely abstracted MySQLi functions
// DB: Query the database
$GLOBALS['__db_last_stmt'] = null;
$GLOBALS['__db_last_conn'] = null;
function db_query($sql, $params=[], $conn=null, $resultmode=MYSQLI_STORE_RESULT, $forceUsePrimaryDbConnection=false, $forceReplaceDataTable=false)
{
	global $rc_connection;
    $GLOBALS['__db_last_stmt'] = null;
    $GLOBALS['__db_last_conn'] = null;
	// If link identifier is explicitly specified, then use it rather than the default $rc_connection.
	if ($resultmode == null) $resultmode = MYSQLI_STORE_RESULT;
	if ($conn == null) $conn = $rc_connection;
    if (!$conn) return false;
    if (!is_string($sql)) return false;
	// Init params
	if ($params === null) $params = [];
	elseif (!is_array($params)) $params = [$params];
	$params = array_values($params);

	// Prevent queries to redcap_data table if no project_id or event_id is provided in the query
	System::checkQuery($sql);

	// If we've made a connection to a db replica for db reads and this is a SELECT query,
	// then use the read replica db connection (not only after System::init and only before.
	$useReplica = (!$forceUsePrimaryDbConnection && isset($GLOBALS['rc_replica_connection']) && defined("REDCAP_INIT_COMPLETE")
					&& !defined("ShutdownStarted") && System::isSelectQuery($sql));
	if ($useReplica) {
		$conn = $GLOBALS['rc_replica_connection'];
	}

    // Set flag of last connection used (in case utilizing the read replica)
    $GLOBALS['__db_last_conn'] = $conn;

	// Check existing queries for this user in other mysql processes
	if (isset($GLOBALS['REDCapCurrentUserHasQueries']))
	{
        // Ignore Data Quality rules being executed AND files being uploaded to the File Repository (because they can run simultaneously and will often trigger this)
        $isExempt = (defined("PAGE") && (PAGE == 'surveys/index.php' || PAGE == 'DataQuality/execute_ajax.php' || PAGE == 'FileRepositoryController:upload'));
        if (!$isExempt) {
            // The SQL returned from MySQL does not include trailing semicolons.
            // If a trailing semicolons exists, we must trim it for it to match below.
            $sql = rtrim($sql, ';');

            // For queries with parameters, we must simulate parameter insertion in order for the check below to match.
            // This will NOT work for queries that contain question marks in strings, but that is likely an insignificant edge case.
            $sqlWithParams = System::pseudoInsertQueryParameters($sql, $params);

            // Loop through all queries that user has running
            foreach ($GLOBALS['CurrentUserQueries'] as $thisMysqlProcessId=>$thisSql) {
                // Is same query already running for this user on SAME page? (prevent the current mysql process from killing itself)
                if ($thisSql == $sqlWithParams && $thisMysqlProcessId != db_thread_id()) {
                    // Kill the query in the other process
                    mysqli_query($conn, "KILL $thisMysqlProcessId", $resultmode);
                    // Remove query from array
                    unset($GLOBALS['CurrentUserQueries'][$thisMysqlProcessId]);
                }
            }
            // If no more queries are in the array, then remove var check
            if (empty($GLOBALS['CurrentUserQueries'])) {
                unset($GLOBALS['REDCapCurrentUserHasQueries']);
            }
        }
	}
	// Execute the query
	if (empty($params)) {
	    // Non-parameterized query
		// In the EM framework we attempted to switch queries without parameters to use prepared statements as well for consistent behavior in commit 5ae46e4.
		// We reverted those changes because some queries like "BEGIN" are not supported with prepared statements.
		// That is also likely a bad idea to do en masse because of some of the behavioral differences described here:
		// https://github.com/vanderbilt-redcap/external-module-framework/blob/testing/docs/framework/v4.md
		$success = mysqli_query($conn, $sql, $resultmode);
	} else {
	    // Parameterized query
		$success = System::queryWithParameters($conn, $sql, $params, $resultmode);
	}
    // Get query error, if failed
	$conn_error = $success ? null : mysqli_errno($conn);
	// If MySQL is somehow in read-only mode (or a similar issue that causes the query not to be executed), display the error so that admins will be notified.
	if (!$success && $conn_error == 1290 && !defined("ShutdownStarted"))
	{
        // Add query and error to error log
        System::addErrorToRCErrorLogTable(strip_tags(RCView::tt("dataqueries_353"))." $conn_error - ".db_error()."\nQUERY: $sql");
        // Display the error to the user
		exit(RCView::tt("dataqueries_353")." ".db_error());
	}
	// If got the error "MySQL server has gone away", then the query was either killed by another mysql process
	// using the section of code above, or the server is down, so display an error message.
	elseif (!$success && $conn_error == 2006 && !defined("ShutdownStarted"))
	{
		// If a query to the replica returned this error, assume the replica went offline, so revert to primary db and re-run this query to fail gracefully
		if ($useReplica) {
			unset($GLOBALS['rc_replica_connection']);
			return db_query($sql, $params, $rc_connection, $resultmode, true);
		}
		// Custom message for Data Quality rules (ajax calls)
		if (defined('PAGE') && PAGE == "DataQuality/execute_ajax.php")
		{
			// Get current rule_id and the ones following
			list ($rule_id, $rule_ids) = explode(",", $_POST['rule_ids'], 2);
			// Set main error msg seen in table
			$dqErrMsg = RCView::getLangStringByKey("global_01");
			// Too many concurrent queries error message
			$msg = "<div id='results_table_{$rule_id}'>
                        <p class='red' style='max-width:500px;'>
							".RCView::tt_i("dataqueries_351", array(5))."
                        </p>
                    </div>";
			// Send back JSON
			print '{"rule_id":"' . $rule_id . '",'
				. '"next_rule_ids":"' . $rule_ids . '",'
				. '"discrepancies":"1",'
				. '"discrepancies_formatted":"<span style=\"font-size:12px;\">'.$dqErrMsg.'</span>",'
				. '"dag_discrepancies":[],'
                . '"exclusion_count":0,'
				. '"title":"' . RCView::escape($_GET['error_rule_name']) . '",'
				. '"payload":"' . cleanJson($msg)  .'"}';
			exit;
		}
		// General error
        if (defined('PAGE') && (PAGE == "api/index.php" || PAGE == "API/index.php")) {
            API::outputApiErrorMsg(RCView::tt("dataqueries_352"));
        } else {
            print RCView::p(array('class'=>'red', 'style'=>'max-width:700px;'), RCView::tt("dataqueries_352"));
        }
		exit;
	}
	// Return false if failed. Return object if successful.
	return $success;
}

// same as db_query() but throws exception on error
function db_query_throw_on_error($sql, $params=[], $conn = null, $resultmode = MYSQLI_STORE_RESULT, $forceUsePrimaryDbConnection = false)
{
    $result = db_query($sql, $params, $conn, $resultmode, $forceUsePrimaryDbConnection);
    $error = db_error();
    if ($error != '') {
        throw new \Exception($sql.': '.$error);
    }
    return $result;
}

// DB: Get next result
function db_next_result() {
	return mysqli_next_result($GLOBALS['__db_last_conn'] ?? $GLOBALS['rc_connection']);
}
// DB: Execute multiple SQL queries
function db_multi_query($sql) {
	$sql_array = SQLTableCheck::parseMultipleSqlQueries($sql);
	foreach ($sql_array as $thisSql) db_query($thisSql);
	return true;
}
// DB: Get the current Mysql process/connection/thread ID
function db_thread_id($conn=null) {
	return mysqli_thread_id($conn ?? $GLOBALS['rc_connection']);
}
// DB: Get the MySQL version number to the first decimal point
function db_get_version($returnThirdPlace=false) {
	$q = db_query("select version()");
	$version = db_result($q, 0);
	$version_pieces = explode(".", $version, 3);
	$version_pieces = array_map('intval', $version_pieces);
	if (!isset($version_pieces[1])) $version_pieces[1] = '0';
	if (!isset($version_pieces[2])) $version_pieces[2] = '0';
	if (!$returnThirdPlace) {
	    unset($version_pieces[2]);
	}
	return implode(".", $version_pieces);
}
// DB: Get db server type (MySQL vs MariaDB)
function db_get_server_type($conn=null) {
    $q = db_query("select version()", [], ($conn ?? $GLOBALS['rc_connection']));
    $version = db_result($q, 0);
    return strpos($version, "MariaDB") ? "MariaDB" : "MySQL";
}
// DB: Mysql status
function db_stat($conn=null) {
	return mysqli_stat($conn ?? $GLOBALS['rc_connection']);
}
// DB: fetch_row
function db_fetch_row($q) {
	if(System::isStatementResult($q)){
		return $q->fetch_row();
	}

	return mysqli_fetch_row($q);
}
// DB: fetch_assoc
function db_fetch_assoc($q) {
	if (!is_object($q)) {
	    return array();
	}
	if (System::isStatementResult($q)){
		return $q->fetch_assoc();
	}
	return mysqli_fetch_assoc($q);
}
// Return ALL rows from query as associative array (Does not correspond to any mysqli function)
function db_fetch_assoc_all($q) {
	$rows = [];
    while ($row = db_fetch_assoc($q)) {
        $rows[] = $row;
    }
    return $rows;
}
// DB: fetch_array
function db_fetch_array($q, $resulttype = MYSQLI_BOTH) {
	if (System::isStatementResult($q)){
		return $q->fetch_array($resulttype);
	} elseif (is_object($q) && get_class($q) == 'mysqli_result') {
        // NOTE that db_fetch_array might have problems with prepared statements when using MYSQLI_ASSOC or MYSQLI_NUM
        return mysqli_fetch_array($q, $resulttype);
    }
	return false;
}
// DB: num_rows
function db_num_rows($q) {

	if(System::isStatementResult($q)){
		return $q->num_rows;
	}

    if (is_object($q) && get_class($q) == 'mysqli_result') {
        return mysqli_num_rows($q);
    }

	return false;
}
// DB: affected_rows
function db_affected_rows() {
    if ($GLOBALS['__db_last_stmt'] !== null) {
         return mysqli_stmt_affected_rows($GLOBALS['__db_last_stmt']);
    } else {
	    return mysqli_affected_rows($GLOBALS['__db_last_conn'] ?? $GLOBALS['rc_connection']);
    }
}
// insert_id
function db_insert_id() {
    if ($GLOBALS['__db_last_stmt'] !== null) {
         return mysqli_stmt_insert_id($GLOBALS['__db_last_stmt']);
    } else {
	    return mysqli_insert_id($GLOBALS['__db_last_conn'] ?? $GLOBALS['rc_connection']);
    }
}
// DB: free_result
function db_free_result($q) {
	if (System::isStatementResult($q)){
		return @$q->free_result();
	} elseif ($GLOBALS['__db_last_stmt'] !== null) {
         return mysqli_stmt_free_result($GLOBALS['__db_last_stmt']);
    } elseif (is_object($q) && get_class($q) == 'mysqli_result' && @isset($q->num_rows)) {
        return mysqli_free_result($q);
    } else {
	    return false;
    }
}
// DB: real_escape_string
function db_real_escape_string($str) {
    if (is_integer($str)) {
        $str = (string)$str;
    } elseif (is_array($str) && count($str) === 1) {
        $str = (string)array_pop($str);
    } elseif (!is_string($str)) {
        $str = '';
    }
	return mysqli_real_escape_string($GLOBALS['rc_connection'], $str);
}
// DB: error
function db_error() {
    if ($GLOBALS['__db_last_stmt'] !== null) {
         return mysqli_stmt_error($GLOBALS['__db_last_stmt']);
    } else {
	    return mysqli_error($GLOBALS['__db_last_conn'] ?? $GLOBALS['rc_connection']);
    }
}
// DB: errno
function db_errno() {
    if ($GLOBALS['__db_last_stmt'] !== null) {
         return mysqli_stmt_errno($GLOBALS['__db_last_stmt']);
    } else {
	    return mysqli_errno($GLOBALS['__db_last_conn'] ?? $GLOBALS['rc_connection']);
    }
}
// DB: field_name
function db_field_name($q, $field_number) {
	if (System::isStatementResult($q)){
		$ob = $q->fetch_field_direct($field_number);
	} else{
		$ob = mysqli_fetch_field_direct($q, $field_number);
	}
	return $ob->name;
}
/**
 * Generates a string of SQL placeholders for use in a prepared statement, particularly useful for `IN` clauses.
 * The number of placeholders generated is equal to the number of elements in the input array.
 *
 * @param array $array The array for which placeholders are to be generated.
 * @return string A string of placeholders separated by commas. Returns an empty string if the input array is empty.
 *
 * Usage example:
 * $arrayOfValues = [1, 2, 3];
 * $placeholders = dbQueryGeneratePlaceholdersForArray($arrayOfValues);
 * // $placeholders would be '?,?,?'
 */
function dbQueryGeneratePlaceholdersForArray($array) {
    if(empty($array)) return '';
    return implode(',', array_fill(0, count($array), '?'));
}
// DB: fetch_fields
function db_fetch_fields($q) {
	if (System::isStatementResult($q)){
		return $q->fetch_fields();
	}
	return mysqli_fetch_fields($q);
}
// DB: num_fields
function db_num_fields($q) {
	if (System::isStatementResult($q)){
		return $q->field_count;
	}
    if (is_object($q) && get_class($q) == 'mysqli_result') {
        return mysqli_num_fields($q);
    }
	return false;
}
// DB: fetch_object
function db_fetch_object($q) {
	if (System::isStatementResult($q)){
		return $q->fetch_object();
	}
    if (is_object($q) && get_class($q) == 'mysqli_result') {
        return mysqli_fetch_object($q);
    }
	return false;
}
// DB: result
function db_result($q, $pos=0, $field='') {
	if ($q === false || !method_exists($q, 'data_seek')){
		return false;
	}
	$i = 0;
	// If didn't specify field, assume the field in first position
	if ($field == '') $field = db_field_name($q, 0);
	// Set pointer to beginning (0)
    $q->data_seek(0);
	// Loop through fields till we get to the correct field
    while ($row = $q->fetch_array(MYSQLI_BOTH)) {
        if ($i == $pos) {
			// Set pointer to next field before exiting
			$q->data_seek($pos+1);
			// Return the value for our field
			return $row[$field];
		}
        $i++;
    }
    return false;
}

// Determine the MySQL port number from the hostname in database.php
function get_db_port_by_hostname($hostname, $db_socket=null)
{
	if ($hostname === null && $db_socket === null) return null;
	$port = '';
	if (strpos($hostname, ':') !== false) {
		list ($hostname_wo_port, $port) = explode(':', $hostname, 2);
	}
	if (!is_numeric($port)) $port = '3306'; // Default MySQL port
	return $port;
}

// Remove the MySQL port number from the hostname in database.php
function remove_db_port_from_hostname($hostname)
{
	return ($hostname === null) ? null : preg_replace("/\:.*/", '', $hostname);
}

/**
 * PROMPT USER TO LOG IN
 */
function loginFunction()
{
	global $authFail, $project_contact_email, $project_contact_name, $auth_meth_global, $login_autocomplete_disable,
		   $homepage_contact_email, $homepage_contact, $autologout_timer, $isMobileDevice, $institution,
		   $login_logo, $login_custom_text, $homepage_announcement, $homepage_announcement_login, $homepage_contact_url, $aafAccessUrl;

	if ($authFail && isset($_POST['submitted'])) {
		// If the authentication has failed after submission
		// return to try and authenticate off the next server
		return 0;
	}

	// Set defaults
	$username_placeholder = $password_placeholder = $custom_login_js = $custom_login_css = $custom_login_html = "";

	// PASSWORD RESET KEY VIA EMAIL: If temporary password flag is set and URL contains reset key and encoded username, then start their session
	if (isset($_GET['action']) && $_GET['action'] == 'passwordreset' && isset($_GET['u']) && trim($_GET['u']) != '' && isset($_GET['k']) && trim($_GET['k']) != '')
	{
		$username_decoded = base64_decode(urldecode($_GET['u']));
		$password_reset_key = rawurldecode(urldecode($_GET['k']));
		// Verify the username and password reset key
		$verifiedPasswordResetKey = Authentication::verifyPasswordResetKey($username_decoded, $password_reset_key);
		// If verified, then manually set username and password to be prefilled on login form
		if ($verifiedPasswordResetKey) {
			// Set username
			$username_placeholder = $username_decoded;
			// Generate new password
			$password_placeholder = Authentication::resetPassword($username_decoded);
			// Set JavaScript to auto submit the login form on pageload
			$custom_login_js = "<script type='text/javascript'>document.form.submit();</script>";
			// Set CSS to make page invisible so that nothing is seen
			$custom_login_css = "<style type='text/css'>body { display:none; }</style>";
			// Set temporary password in a separate password input to prevent browsers from pre-filling password
			$custom_login_html = "<input type='password' name='redcap_login_password_temp' value=\"$password_placeholder\">";
		}
	}

	// If using RSA SecurID two-factor authentication, use passcode instead of password in text
	$passwordLabel = RCView::tt("global_240");
	$passwordTextRight = "";
	$rsaLogo = "";
	if ($auth_meth_global == 'rsa') {
		$rsaLogo =  RCView::div(
			array('style'=>'text-align:center;padding-bottom:15px;'),
			RCView::img(array('src'=>'securid2.gif')));
		$passwordLabel = RCView::tt("global_241");
		$passwordTextRight = RCView::div(
			array('style'=>'color:#800000;font-size:13px;margin:4px 0;text-align:right;'), 
			RCView::tt("config_functions_92"));
	}

	// Set "forgot password?" link
	$forgotPassword = "";
	if ($auth_meth_global == "table" || Authentication::usingXandTableBasedAuth()) {
		$forgotPassword = RCView::div(array("style"=>"float:right;margin-top:10px;margin-right:10px;"),
							RCView::a(array("style"=>"font-size:12px;text-decoration:underline;","href"=>APP_PATH_WEBROOT."Authentication/password_recovery.php"), RCView::tt("pwd_reset_41"))
						  );
	}

	// REDCap Hook injection point: Pass PROJECT_ID constant (if defined).
	Hooks::call('redcap_every_page_before_render', (defined("PROJECT_ID") ? array(PROJECT_ID) : array()));

    // If we're on the login page, but logout=1 is still in the URL, remove it by redirecting to current page
    if (isset($_GET['logout']) && $_GET['logout']) {
        redirect(str_replace(["?logout=1", "&logout=1"], "", $_SERVER['REQUEST_URI']));
    }

	// Display the Login Form
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->addStylesheet("home.css", 'screen,print');
	$objHtmlPage->PrintHeader();
	// Custom CSS
	print $custom_login_css;

	print '<style type="text/css">#container{ background: url("'.APP_PATH_IMAGES.'redcap-logo-large.png") no-repeat; }</style>';

	print '<div id="left_col">';

	print '<h4 style="margin-top:60px;padding:3px;border-bottom:1px solid #AAAAAA;color:#000000;font-weight:bold;">'.RCView::tt("config_functions_45").'</h4>';

	// Institutional logo (optional)
	if (trim($login_logo) != "")
	{
		print  "<div style='margin-bottom:20px;text-align:center;'>
					<img src='$login_logo' title=\"".js_escape2(strip_tags(label_decode($institution)))."\" alt=\"".js_escape2(strip_tags(label_decode($institution)))."\" style='max-width:850px;'>
				</div>";
	}

	// Show custom login text (optional)
	if (trim($login_custom_text) != "")
	{
		print "<div style='border:1px solid #ccc;background-color:#f5f5f5;margin:15px 10px 15px 0;padding:10px;'>".nl2br(decode_filter_tags($login_custom_text))."</div>";
	}

	// Show custom homepage announcement text (optional)
	if (trim($homepage_announcement) != "" && $homepage_announcement_login == '1') {
		print RCView::div(array('style'=>'margin-bottom:10px;'), nl2br(decode_filter_tags($homepage_announcement)));
		$hide_homepage_announcement = true; // Set this so that it's not displayed elsewhere on the page
	}

	if ($auth_meth_global == 'oauth2_azure_ad_table')
	{
		?>
		<style>
		#rc-login-form { display: none; }
        </style>
		<div class="my-4 fs14">
		    <div class="mb-4"><?=RCView::tt('global_253')?></div>
		    <div>
                <span class="text-secondary my-2 me-3"><?=RCView::tt('global_257')?></span>
                <button class="btn btn-sm btn-primaryrc fs15 my-2" onclick="showProgress(1);window.location.href=app_path_webroot_full+'?action=oauth2_azure_ad_login';"><i class="fas fa-sign-in-alt"></i> <?=($GLOBALS['oauth2_azure_ad_name'] != '' ? $GLOBALS['oauth2_azure_ad_name'] : RCView::tt('global_293'))?></button>
                <span class="text-secondary mx-3 my-2 nowrap">-- <?=RCView::tt('global_46')?> --</span>
                <button class="btn btn-sm btn-rcgreen fs15 my-2" onclick="$('#rc-login-form').toggle();"><i class="fas fa-sign-in-alt"></i> <?=RCView::tt('global_258')?></button>
            </div>
        </div>
		<?php
	}
	elseif ($auth_meth_global == 'openid_connect_table')
	{
		?>
		<style>
		#rc-login-form { display: none; }
        </style>
		<div class="my-4 fs14">
		    <div class="mb-4"><?=RCView::tt('global_253')?></div>
		    <div>
                <span class="text-secondary my-2 me-3"><?=RCView::tt('global_257')?></span>
                <button class="btn btn-sm btn-primaryrc fs15 my-2" onclick="showProgress(1);window.location.href=app_path_webroot_full+'?action=openid_connect_login';"><i class="fas fa-sign-in-alt"></i> <?=($GLOBALS['openid_connect_name'] != '' ? $GLOBALS['openid_connect_name'] : RCView::tt('global_254'))?></button>
                <span class="text-secondary mx-3 my-2 nowrap">-- <?=RCView::tt('global_46')?> --</span>
                <button class="btn btn-sm btn-rcgreen fs15 my-2" onclick="$('#rc-login-form').toggle();"><i class="fas fa-sign-in-alt"></i> <?=RCView::tt('global_258')?></button>
            </div>
        </div>
		<?php
	}
	elseif ($auth_meth_global == 'aaf' || $auth_meth_global == 'aaf_table')
	{
		?>
		<div>
			<!-- nav tabs -->
			<ul class="nav nav-tabs" role="tablist">
				<li role="presentation" class="nav-item"><a href="#inst-login" class="nav-link active" data-toggle="tab" aria-controls="inst-login" role="tab" data-toggle="tab"><?=RCView::tt('global_1550')?></a>
				</li>
				<li role="presentation" class="nav-item"><a href="#non-inst-login" class="nav-link" data-toggle="tab" aria-controls="non-inst-login" role="tab" data-toggle="tab"><?=RCView::tt('global_1551')?></a>
				</li>
			</ul>

			<!-- tab panes -->
			<div class="tab-content">
				<div role="tabpanel" class="tab-pane active" id="inst-login">
                <p><?=RCView::tt('global_1552')?></p>
					<p><a href='<?php echo $GLOBALS['aafAccessUrl']; ?>'><img src='https://rapid.aaf.edu.au/aaf_service_223x54.png'/></a></p>
				</div>
				<div role="tabpanel" class="tab-pane" id="non-inst-login">
					<p><?=RCView::tt('global_1553')?></p>
		<?php
	}
	elseif ($auth_meth_global == 'shibboleth' || $auth_meth_global == 'shibboleth_table')
	{
        $shibboleth_table_config = json_decode($GLOBALS['shibboleth_table_config'], TRUE);
        $first_shib_data = $shibboleth_table_config['institutions'][0];
        // If URL for Shibboleth SP Session Initiator is defined but does not specify a target
        // parameter, add one based on the current page's pathname and querystring
        $first_shib_login_url = $first_shib_data['login_url'];
        if ($first_shib_login_url !== '' && strpos($first_shib_login_url, 'target=') === false) {
            $first_shib_login_url .= (strpos($first_shib_login_url, '?') === false ? '?' : '&') . 'target=' . urlencode($_SERVER['REQUEST_URI']);
        }

        ?>
        <div>
            <!-- nav tabs -->
            <style>
                .nav-tabs {
                    background-color: #f8f8f8;
                    padding: none;
                }
                .nav-tabs a {
                    padding: 10px;
                    text-decoration: none !important;
                    display: block;
                }
                .nav-tabs li a:hover {
                    text-decoration: none !important;
                }
                .nav-tabs li:hover a:hover {
                background-color: #e7e7e7;
                }

                /* The following modifiers would be redundant with the JS below
                but the li element for the default login must be selected
                li:active, a:active {
                background-color: #e7e7e7;
                }
                li:focus, a:focus {
                background-color: #e7e7e7;
                }
                */
                /* The CSS relational pseudo-class "has" is not enabled yet
                once usable, it can replace the CSS altering js functions below
                .active li:has(> a[aria-selected="true"]) {
                background-color: #e7e7e7;
                }
                */
            </style>

            <script>
            const shibTableConfig = <?php echo $GLOBALS["shibboleth_table_config"]; ?>;
            const defaultShibUrl = '<?php echo $_SERVER['Shib-Handler'] . "/Login?target=" . urlencode($_SERVER['REQUEST_URI']); ?>';
            const repeatShibParams = shibTableConfig.institutions;
            const shibIdPs = repeatShibParams.length;
			const defaultSelection = '<?= $auth_meth_global == 'shibboleth' && $shibboleth_table_config['splash_default'] == 'non-inst-login' ? 'inst-login0' : strip_tags($shibboleth_table_config['splash_default']); ?>';

            function appendShibOptions() {
                // Fill the login pane for each Shibboleth login option
                for (let i = 1; i < shibIdPs; i++) {
                    const thisShib = repeatShibParams[i];

                    // Fill the tab selectors for each Shibboleth login option
                    const text = thisShib['login_option'];
                    const tabOption = $('#shib_login_tab' + (i - 1)).last();
                    var nextTabOption = tabOption.clone();
                    nextTabOption.insertAfter(tabOption);
                    nextTabOption.attr('id', 'shib_login_tab' + i);
                    nextTabOption.find('a').text(text)
                                 .attr('href', '#inst-login' + i)
                                 .attr('aria-controls', 'inst-login' + i);

                    // Remove the tab title data to not clash with appendShibPaneOptions
                    delete thisShib['login_option'];

                    // Handle panes
                    const paneOption = $('#inst-login0.tab-pane');
                    var nextPaneOption = paneOption.clone();
                    nextPaneOption.insertAfter(paneOption);
                    nextPaneOption.attr('id', 'inst-login' + i);

                    for (const param of Object.keys(thisShib)) {
                        const paramValue = thisShib[param];
                        let paramTargetElement = nextPaneOption.find('#' + param);
                        switch(param) {
                            case 'login_text':
                                paramTargetElement.text(paramValue);
                                break;
                            case 'login_url':
                                // If URL for Shibboleth SP Session Initiator is defined but does not specify a target
                                // parameter, add one based on the current page's pathname and querystring
                                let login_url = paramValue;
                                if (login_url !== '' && !login_url.includes('target=')) {
                                    login_url += (login_url.includes('?') === false ? '?' : '&') + 'target=' + encodeURIComponent(window.location.pathname + window.location.search);
                                }
                                paramTargetElement.attr('href', (login_url !== '') ? login_url : defaultShibUrl);
                                break;
                            case 'login_image':
                                paramTargetElement.attr('src', paramValue);
                                break;
                            default:
                                paramTargetElement.text(paramValue);
                        }
                    }
                }
            }

            $(function() {
                appendShibOptions();
                // Activate login pane according to default
                document.getElementById(defaultSelection).setAttribute('class', 'tab-pane active show');
                // Add selected styling to li element for default
                document.querySelector('[aria-controls="' + defaultSelection + '"]').parentElement.setAttribute('style', 'background-color: #e7e7e7; text-decoration: none;');
                // BS5 hack to deal with table-based login form mistakenly appearing after the first time the Shib login tab is clicked
                $('.shib-tabs li').on('click', function(event) {
                    $('.shib-tabs li').removeClass('active').removeClass('show').css('background-color','#f8f8f8');
                    $(this).css('background-color','#e7e7e7');
                    $('.tab-pane').removeClass('active').removeClass('show');
					var tabPaneSelector = $(this).children(':first').attr('href');
					$(tabPaneSelector).addClass('active').addClass('show');
                });
            });
            </script>

			<ul class="nav nav-tabs shib-tabs" role="tablist">
				<?php if ($auth_meth_global == 'shibboleth_table') { ?>
                <li role="presentation" id="table_login_tab0">
                    <a href="#non-inst-login" aria-controls="non-inst-login" role="tab" data-toggle="tab">
                        <?php echo $shibboleth_table_config['table_login_option']; ?>
                    </a>
                </li>
				<?php } ?>
                <li role="presentation" id="shib_login_tab0">
                    <a href="#inst-login0" aria-controls="inst-login0" role="tab" data-toggle="tab">
                        <?php echo $first_shib_data['login_option']; ?>
                    </a>
                </li>
			</ul>

			<!-- tab panes -->
			<div class="tab-content">
				<div role="tabpanel" class="tab-pane" id="inst-login0">
                    <p id='login_text'>
                        <?php echo $first_shib_data['login_text']; ?>
                    </p>
                    <p>
                        <a id='login_url' href='<?php echo ( $first_shib_login_url ) ?: $_SERVER['Shib-Handler'] . '/Login?target=' . urlencode($_SERVER['REQUEST_URI']); ?>'>
                            <img id='login_image' src='<?php echo $first_shib_data['login_image']; ?>'/>
                        </a>
                    </p>
                </div>
				<div role="tabpanel" class="tab-pane" id="non-inst-login">
		<?php
	}

    print "<div id='rc-login-form'>";

	// Login instructions (default)
	if ($auth_meth_global == "table" || Authentication::usingXandTableBasedAuth()) {
        print RCView::p(array('style'=>'font-size:13px;'), RCView::tt("config_functions_129"));
	} else {
        print RCView::p(array('style'=>'font-size:13px;'),
                RCView::tt_i("config_functions_125", array(
                    RCView::a(array(
                        "style" => "font-size:13px;text-decoration:underline;",
                        "href" => trim($homepage_contact_url) == '' ? "mailto:$homepage_contact_email" : trim($homepage_contact_url)
                    ), $homepage_contact)
                ), false)
            );
    }

    print RCView::br();

	// Sanitize action URL for login form
	$loginFormActionUrl = js_escape(str_replace('`', '', $_SERVER['REQUEST_URI']));
	// Give extra room for non-English languages
    $loginLabelLeft = ($GLOBALS['language_global'] == 'English') ? 'margin-left:30px;' : '';
	$loginLabelWidth = ($GLOBALS['language_global'] == 'English') ? 'width:120px;' : 'width:150px;';
    $loginInputWidth = $isMobileDevice ? 'width:150px;' : 'width:180px;';

	print  "<center>";
	print  "<form name='form' style='max-width:350px;' method='post' action='$loginFormActionUrl'>";
	print  $rsaLogo;
	print  "<div class='input-group'>
				<div class='input-group-prepend' style='$loginLabelLeft'>
					<div class='d-flex input-group-text fs14 wrap' id='basic-addon1' style='{$loginLabelWidth}color:#333;border-top-right-radius:0;border-bottom-right-radius:0;'>".RCView::tt("global_239")."</div>
					<input type='text' class='form-control fs14' style='border-top-left-radius:0;border-bottom-left-radius:0;{$loginInputWidth}' aria-labelledby='basic-addon1' name='username' id='username' value='".RCView::escape($username_placeholder)."' tabindex='1' " . ($login_autocomplete_disable ? "autocomplete='new-password'" : "") . ">
				</div>
			</div>";
	print  "<div class='input-group' style='margin-top:10px;'>
				<div class='input-group-prepend' style='$loginLabelLeft'>
					<div class='d-flex input-group-text fs14 wrap' id='basic-addon1' style='{$loginLabelWidth}color:#333;border-top-right-radius:0;border-bottom-right-radius:0;'>$passwordLabel</div>
					<input type='password' class='form-control fs14' style='border-top-left-radius:0;border-bottom-left-radius:0;{$loginInputWidth}' aria-labelledby='basic-addon1' name='password' id='password' value='".RCView::escape($password_placeholder)."' tabindex='2' " . ($login_autocomplete_disable ? "autocomplete='new-password'" : "") . ">
				</div>
			</div>
			$passwordTextRight";
	print  "<div style='text-align:left;margin:20px 0 0 120px;'>
				<button class='btn btn-md btn-defaultrc fs14' id='login_btn' tabindex='3' onclick=\"setTimeout(function(){ $('#login_btn').prop('disabled',true); },10);\">".RCView::tt("config_functions_45")."</button>
				$forgotPassword
			</div>
			<input type='hidden' name='submitted' value='1'>
			<input type='hidden' id='redcap_login_a38us_09i85' name='redcap_login_a38us_09i85' value='".RCView::escape(Authentication::generateLoginToken())."'>";

	// FAILSAFE: If user was submitting data on form and somehow the auth session ends before it's supposed to, take posted data, encrypt it, and carry it over after new login
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && PAGE == 'DataEntry/index.php' && isset($_GET['page'])
		&& isset($_GET['event_id']) && (isset($_POST['submit-action']) || isset($_POST['redcap_login_post_encrypt_e3ai09t0y2'])))
	{
		// Encrypt the submitted values, and if login failed, preserve encrypted value
		$enc_val = isset($_POST['redcap_login_post_encrypt_e3ai09t0y2']) ? $_POST['redcap_login_post_encrypt_e3ai09t0y2'] : encrypt(serialize($_POST));
		print  "<input type='hidden' value='$enc_val' name='redcap_login_post_encrypt_e3ai09t0y2'>
				<p class='green' style='text-align:center;'>
					<i class=\"fas fa-exclamation-triangle\"></i> ".RCView::tt("config_functions_123")."
				</p>";
	}

	// Output any custom form HTML/elements
	print $custom_login_html;

	print "</form>";
	print "</center>";
	print "</div>";

    // change this so that the end of the stand form is handled correctly
    if ($auth_meth_global == 'aaf' || $auth_meth_global == 'aaf_table')
	{
        ?>			<p><?=RCView::tt("global_1554") . js_escape2(strip_tags(label_decode($institution))) . RCView::tt("global_1555"); ?></p>
				</div>
			</div>
		</div>
		<?php
	} else {
		?><br></div><?php
	}
?>

	<hr style="margin-bottom: 10px; border-top: 1px solid #AAA;">
<?php

	// Display Community Site login info or home page or Traing Resources page below (but without allowing access into projects yet)
	if (defined("COMM_DOCROOT") && file_exists(COMM_DOCROOT."login_splash.php")) {
		include COMM_DOCROOT."login_splash.php";
	}
	else if (isset($_GET['action']) && $_GET['action'] == 'training') {
		include APP_PATH_DOCROOT . "Home/training_resources.php";
	} else {
		include APP_PATH_DOCROOT . "Home/info.php";
	}

	// Put focus on username login field
	print "<script type='text/javascript'>document.getElementById('username').focus();</script>";

	// Output any custom JavaScript
	print $custom_login_js;

	// Since we're showing the login page, destroy all sessions/cookies, just in case they are left over from previous session.
	Session::destroyUserSession();

	$objHtmlPage->PrintFooter();
	exit;
}

// Returns hidden div with X number of random characters. This helps mitigate hackers attempting a BREACH attack.
// ONLY perform this if GZIP is enabled (because BREACH is only effective when HTTP compression is enabled).
function getRandomHiddenText()
{
	// If Gzip enabled, then output it
	if (defined("GZIP_ENABLED") && GZIP_ENABLED) {
		// Set max number of characters
		$maxChars = 128;
		// Get random number between 1 and $maxChars
		$numChars = random_int(1, $maxChars);
		// Build random text to place inside hidden div
		$html = generateRandomHash($numChars);
		// Return hidden div
		print RCView::div(array('id'=>'random_text_hidden_div', 'style'=>'display:none;'), $html);
	}
}

// Get currentl full URL
function curPageURL($returnFullUrl=true)
{
	$pageURL = (SSL ? 'https' : 'http') . '://';
	if (!$returnFullUrl) {
	    $pageURL = $_SERVER["REQUEST_URI"];
	} elseif (PORT == "") {
		$pageURL .= SERVER_NAME.$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= SERVER_NAME.":".PORT.$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

// Obtain and return server name (i.e. domain), server port, and if using SSL (boolean)
function getServerNamePortSSL()
{
	global $proxy_hostname, $redcap_base_url, $redcap_version;
	// Trim vars
	$redcap_base_url = trim($redcap_base_url);
	$proxy_hostname  = trim($proxy_hostname);
	if ($redcap_base_url != '')
	{
		## Parse $redcap_base_url to get hostname, ssl, and port

		// Make sure $redcap_base_url ends with a /
		$redcap_base_url .= ((substr($redcap_base_url, -1) != "/") ? "/" : "");
		// Determine if uses SSL
		// Does it actually start with http or https?
		$redcap_base_url_lower = strtolower($redcap_base_url);
		if (strpos($redcap_base_url_lower, 'http://') === 0) {
			$hostStartPos = 7;
			$ssl = false;
		}
		else if (strpos($redcap_base_url_lower, 'https://') === 0) {
			$hostStartPos = 8;
			$ssl = true;
		}
		else {
			$hostStartPos = 0;
			$ssl = $_SERVER['REQUEST_SCHEME'] == 'https';
		}
		// Remove subdirectories on the end to get server_name and port
		$hostFirstSlash = strpos($redcap_base_url, '/', $hostStartPos);
		$server_name = substr($redcap_base_url, $hostStartPos, $hostFirstSlash - $hostStartPos);
		$port = '';
		if(strstr($server_name, ':'))
		{
			list ($server_name, $port) = explode(":", $server_name, 2);
		}
		if ($port != '') $port = ":$port";
		// Set relative web path of this webpage
		$page_full = defined("CRON") ? substr($redcap_base_url, $hostFirstSlash) . "redcap_v{$redcap_version}/cron.php" : $_SERVER['PHP_SELF'];
	}
	else
	{
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
			$port = ($_SERVER['SERVER_PORT'] != 443) ? ":".$_SERVER['SERVER_PORT'] : "";
			$ssl = true;
		} else {
			$port = ($_SERVER['SERVER_PORT'] != 80)  ? ":".$_SERVER['SERVER_PORT'] : "";
			$ssl = false;
		}
		// Determine web server domain name (and remove any illegal characters)
		$server_name = RCView::escape(str_replace(array("\"", "'", "+"), array("", "", ""), label_decode(getServerName())));
		// Set relative web path of this webpage
		$page_full = $_SERVER['PHP_SELF'];
	}
	// Return values
	return array($server_name, $port, $ssl, $page_full);
}

//Function for rounding up numbers (used for showing file sizes for File fields and prevents file sizes being "0 MB")
function round_up($value, $precision=2)
{
	if ( $value < 0.01 )
	{
		return '0.01';
	}
	else
	{
		return round($value, $precision, PHP_ROUND_HALF_UP);
	}
}


// Function to obtain current event_id from query string, or if does not exist, get first event_id
function getEventId()
{
    $Proj = new Project(PROJECT_ID);
	// If we have event_id in URL
	if (isset($_GET['event_id']) && isset($Proj->eventInfo[$_GET['event_id']])) {
		return $_GET['event_id'];
	// If arm_id is in URL
	} elseif (isset($_GET['arm_id']) && is_numeric($_GET['arm_id'])) {
		return $Proj->getFirstEventIdArmId($_GET['arm_id']);
	// If arm is in URL
	} elseif (isset($_GET['arm']) && is_numeric($_GET['arm'])) {
		return $Proj->getFirstEventIdArm($_GET['arm']);
	// We have nothing so use first event_id in project
	} else {
		return $Proj->firstEventId;
	}
}

// Function to obtain current or lowest Arm number
function getArm()
{
    $arm = "";
	// If we have event_id in URL
	if (isset($_GET['event_id']) && !isset($_GET['arm']) && isinteger($_GET['event_id'])) {
		$arm = db_result(db_query("select arm_num from redcap_events_arms a, redcap_events_metadata e where a.arm_id = e.arm_id and e.event_id = " .$_GET['event_id']), 0);
	}
	// If we don't have arm in URL
	elseif (defined("PROJECT_ID") && (!isset($_GET['arm']) || $_GET['arm'] == "" || !isinteger($_GET['arm']))) {
		$arm = db_result(db_query("select min(arm_num) from redcap_events_arms where project_id = " . PROJECT_ID), 0);
	}
	// If arm is in URL
	elseif (isset($_GET['arm']) && isinteger($_GET['arm'])) {
		$arm = (int)$_GET['arm'];
        // Make sure arm actually exists
        if (defined("PROJECT_ID")) {
            $armExists = db_num_rows(db_query("select 1 from redcap_events_arms where arm_num = $arm and project_id = " . PROJECT_ID));
            if (!$armExists) {
		        $arm = db_result(db_query("select min(arm_num) from redcap_events_arms where project_id = " . PROJECT_ID), 0);
            }
        }
	}
	// Just in case arm is blank somehow
	if ($arm == "" || !isinteger($arm)) {
		$arm = 1;
	}
	return $arm;
}

// Function to obtain current arm_id, or if not current, the arm_id of lowest arm number
function getArmId($arm_id = null)
{
	global $Proj;
	// Set default value
	$armIdValidated = false;
	// Determine arm_id if not provided
	if ($arm_id == null)
	{
		// If we have event_id in URL
		if (isset($_GET['event_id']) && !isset($_GET['arm_id']) && is_numeric($_GET['event_id'])) {
			$sql = "select a.arm_id from redcap_events_arms a, redcap_events_metadata e where a.project_id = " . PROJECT_ID . "
					and a.arm_id = e.arm_id and e.event_id = " . db_escape($_GET['event_id']) . " limit 1";
			$q = db_query($sql);
			if (db_num_rows($q) > 0) {
				$arm_id = db_result($q, 0);
				$armIdValidated = true;
			}
		}
		// If arm is in URL
		elseif (isset($_GET['arm_id']) && is_numeric($_GET['arm_id'])) {
			$arm_id = $_GET['arm_id'];
		}
	}
	// Now validate the arm_id we have. If not valid, get the arm_id of lowest arm number
	if (!$armIdValidated) {
		// If arm_id/event_id is not in URL or arm_id is not numeric, then just return the arm_id of lowest arm number
		if (empty($arm_id) || !is_numeric($arm_id)) {
			$arm_id = $Proj->firstArmId;
		}
		// Since we have an arm_id now, validate that it belongs to this project
		else {
			$sql = "select arm_id from redcap_events_arms where project_id = " . PROJECT_ID . " and arm_id = $arm_id";
			if (db_num_rows(db_query($sql)) < 1) {
				$arm_id = $Proj->firstArmId;
			}
		}
	}
	return $arm_id;
}

//Remove certain characters from html strings to use in javascript (assumes will be put inside single quotes)
function js_escape($val, $remove_line_breaks=true)
{
	// Replace MS characters
	replaceMSchars($val);
	// Remove line breaks?
	if ($remove_line_breaks) {
		$repl = array("\r\n", "\r", "\n");
		$orig = array(" ", "", " ");
		$val = str_replace($repl, $orig, $val);
	}
	// Replace
	$repl = array("\t", "'");
	$orig = array(" ", "\'");
	$val = str_replace($repl, $orig, $val);
	// If ends with a backslash, then escape it
	if (substr($val, -1) == '\\') $val .= '\\';
	// Return
	return $val;
}

//Remove certain characters from html strings to use in javascript (assumes will be put inside double quotes)
function js_escape2($val, $remove_line_breaks=true)
{
	// Replace MS characters
	replaceMSchars($val);
	// Remove line breaks?
	if ($remove_line_breaks) {
		$repl = array("\r\n", "\r", "\n");
		$orig = array(" ", "", " ");
		$val = str_replace($repl, $orig, $val);
	}
	// Replace
	$repl = array("\t", '"');
	$orig = array(" ", '\"');
	$val = str_replace($repl, $orig, $val);
	// If ends with a backslash, then escape it
	if (substr($val, -1) == '\\') $val .= '\\';
	// Return
	return $val;
}

//Function to render the page title/header for individual pages
function renderPageTitle($val = "") {
	if (isset($val) && $val != "") {
		print  "<div class=\"projhdr\">$val</div>";
	}
}

// Function to parse string with fields inside [] brackets and return as array with fields.
// To return ONLY field names and remove all event names and checkbox parentheses, set to ($val, true, true, true).
function getBracketedFields($val, $removeCheckboxBranchingLogicParentheses=true, $returnFieldDotEvent=false, $removeEvent=false)
{
	$these_fields = array();
    if ($val === null || trim($val) == '') return $these_fields;
	$smartVariablesInstance = array('previous-instance', 'current-instance', 'next-instance', 'first-instance', 'last-instance');
    $val = LogicParser::removeCommentsAndSanitize($val);
	// Collect all fields in brackets
	foreach (explode("|RCSTART|", preg_replace("/(\[[^\[]*\]\[[^\[]*\]|\[[^\[]*\])/", "|RCSTART|$1|RCEND|", $val)) as $this_section)
	{
		$endpos = strpos($this_section, "|RCEND|");
		if ($endpos === false) continue;
		$this_field = substr($this_section, 1, $endpos-2);
		$this_field = str_replace("][", ".", trim($this_field));
		// Remove anything after a ] if we have a lone ]
		if (strpos($this_field, "]")) {
            list ($this_field, $nothing) = explode("]", $this_field, 2);
		}
		// Do not include this field if is blank
		if ($this_field == "" || is_numeric($this_field)) continue;
		// Do not include this field if has unique event name in it and should not be returning unique event name
		if (strpos($this_field, ".") !== false) {
			// Remove instance number and set placeholder for event (if not prepended)
			$parts = explode(".", $this_field);
			$partsLastKey = max(array_keys($parts));
			if (is_numeric($parts[$partsLastKey]) || in_array($parts[$partsLastKey], $smartVariablesInstance)) {
				unset($parts[$partsLastKey]);
			}
			$this_field = implode(".", $parts);
		}
		$dotPos = strpos($this_field, ".");
		$leftParenPos = strpos($this_field, "(");
		if ($dotPos !== false && ($leftParenPos === false || $dotPos < $leftParenPos)) {
			if (!$returnFieldDotEvent) {
				continue;
			} elseif ($removeEvent) {
				list ($this_event, $this_field) = explode(".", $this_field, 2);
			}
		}
		//Insert field into array as key to store as unique
        if (!in_array($this_field, $smartVariablesInstance)) {
			$these_fields[$this_field] = "";
		}
	}
	// Compensate for parentheses in checkbox logic
	$regexFieldCheck = "/^[a-z0-9._\(\):]+$/";
	if ($removeCheckboxBranchingLogicParentheses)
	{
		$regexFieldCheck = "/^[a-z0-9._]+$/";
		foreach ($these_fields as $this_field=>$nothing)
		{
			if (strpos($this_field, "(") !== false)
			{
				// Replace original with one that lacks parentheses
				list ($this_field2, $nothing2) = explode("(", $this_field, 2);
				unset($these_fields[$this_field]);
				$these_fields[$this_field2] = $nothing;
			}
		}
	}
	// Get array of special piping tags that can be used in place of events/fields
	$specialPipingTags = Piping::getSpecialTagsFormatted(true, false);

	// Now make sure that each field (or event.field) is the correct formatting (i.e. probably a real field)
	foreach (array_keys($these_fields) as $this_field) {
		$this_event = "";
		if (strpos($this_field, ".") !== false) {
			list ($this_event, $this_field) = explode(".", $this_field);
            if (!$removeEvent && strpos($this_field, ":") !== false) {
                unset($these_fields[$this_event.".".$this_field]);
                list ($this_field, $nothing) = explode(":", $this_field, 2);
                $these_fields[$this_event.".".$this_field] = "";
            }
		}
		if (strpos($this_field, ":") !== false) {
			unset($these_fields[$this_field]);
			list ($this_field, $nothing) = explode(":", $this_field, 2);
			$these_fields[$this_field] = "";
		}
		if (!preg_match($regexFieldCheck, $this_field) && !in_array("[".$this_field."]", $specialPipingTags)) {
			unset($these_fields[$this_field]);
		}
	}

	// Return array of fields
	return $these_fields;
}

// Return boolean if a string contains identifier fields
function containsIdentifierFields($string, $project_id)
{
	if (!isinteger($project_id)) return false;
	$fields = array_keys(getBracketedFields($string, true, true, true));
	if (empty($fields)) return false;
	$Proj = new Project($project_id);
	foreach ($fields as $field) {
		if (isset($Proj->metadata[$field]) && $Proj->metadata[$field]['field_phi'] == '1') {
			return true;
		}
	}
	return false;
}

// Remove specific HTML tags from a string - e.g., $html_tags = ["html", "body", "span", "b"];
function remove_html_tags($html_string, $html_tags)
{
    $tagStr = "";
    foreach($html_tags as $key => $value) {
        $tagStr .= $key == count($html_tags)-1 ? $value : "{$value}|";
    }
    $pat_str= array("/(<\s*\b({$tagStr})\b[^>]*>)/i", "/(<\/\s*\b({$tagStr})\b\s*>)/i");
    $result = preg_replace($pat_str, "", $html_string);
    return $result;
}


/*
 ** Give null value if equals "" (used inside queries)
 */
function checkNull($value, $replaceMSchars=true) {
	if ($value === "" || $value === null || $value === false) {
		return "NULL";
	} else {
		return "'" . db_escape($value, $replaceMSchars) . "'";
	}
}


// DETERMINE IF SERVER IS A VANDERBILT SERVER
function isVanderbilt()
{
	return (strpos(SERVER_NAME, "vanderbilt.edu") !== false || strpos(SERVER_NAME, "vumc.org") !== false);
}


/**
 * LINK TO RETURN TO PREVIOUS PAGE
 * $val corresponds to PAGE constant (i.e. relative URL from REDCap's webroot)
 */
function renderPrevPageLink($val) {
	if (isset($_GET['ref']) || $val != null) {
		$val = ($val == null) ? htmlspecialchars(str_replace(array("`","'"), array("",""), strip_tags(label_decode(rawurldecode(urldecode($_GET['ref']))))), ENT_QUOTES) : $val;
		$val = str_replace(array("(",")"), array("", ""), $val);
		if ($val == "") return;
		print  "<p style='margin:0;padding:10px;'>
					<img src='" . APP_PATH_IMAGES . "arrow_skip_180.png'>
					<a href='" . APP_PATH_WEBROOT . $val . (defined("PROJECT_ID") ? ((strpos($val, "?") === false ? "?" : "&") . "pid=" . PROJECT_ID) : "") . "'
						style='color:#2E87D2;font-weight:bold;'>".RCView::tt("config_functions_40")."</a>
				</p>";
	}
}

/**
 * BUTTON TO RETURN TO PREVIOUS PAGE
 * $val corresponds to PAGE constant (i.e. relative URL from REDCap's webroot)
 * If $val is not supplied, will use "ref" in query string.
 */
function renderPrevPageBtn($val=null,$label=null,$outputToPage=true,$btnClass='jqbutton') {
	$button = "";
	if (isset($_GET['ref']) || $val != null)
	{
		$val = ($val == null) ? htmlspecialchars(str_replace(array("`","'"), array("",""), strip_tags(label_decode(rawurldecode(urldecode($_GET['ref']))))), ENT_QUOTES) : $val;
		$val = str_replace(array("(",")"), array("", ""), $val);
		if ($val == "") return;
		// Set label
		$label = ($label == null) ? RCView::tt("config_functions_40") : $label;
		$button =  "<button class='$btnClass' style='' onclick=\"window.location.href='" .
						APP_PATH_WEBROOT . $val . (defined("PROJECT_ID") ? ((strpos($val, "?") === false ? "?" : "&") . "pid=" . PROJECT_ID) : "") . "';\">
						<i class=\"fas fa-chevron-circle-left\"></i> $label
					</button>";
	}
	// Render or return
	if ($outputToPage) {
		print $button;
	} else {
		return $button;
	}
}



/**
 * Run single-field query and return comma delimited set of values (to be used inside other query for better performance than using subqueries)
 */
function pre_query($sql)
{
	if (trim($sql) == "" || $sql == null) return "''";
	$sql = html_entity_decode($sql, ENT_QUOTES);
	$q = db_query($sql);
	$val = "";
	if ($q) {
		if (db_num_rows($q) > 0) {
			while ($row = db_fetch_array($q)) {
				$val .= "'" . db_escape($row[0]) . "', ";
			}
			$val = substr($val, 0, -2);
		}
	}
	return ($val == "") ? "''" : $val;
}

/**
 * Display query if query fails
 */
function queryFail($sql) {
	exit("<p><b>".RCView::tt("config_functions_41")."</b><br>$sql</p>");
}

/**
 * Returns first event_id of a project (if specify arm number, returns first event for that arm)
 */
function getSingleEvent($this_project_id, $arm_num = NULL) {
	if (!is_numeric($this_project_id)) return false;
	$sql = "select m.event_id from redcap_events_metadata m, redcap_events_arms a where a.arm_id = m.arm_id
			and a.project_id = $this_project_id";
	if (is_numeric($arm_num)) $sql .= " and a.arm_num = $arm_num";
	$sql .= " order by a.arm_num, m.day_offset, m.descrip limit 1";
	return db_result(db_query($sql), 0);
}

// Essentially just runs filter_tags(label_decode()), which is best if we know we're going to do filter_tags().
function decode_filter_tags($val)
{
    if ($val === null) return "";
	return filter_tags(label_decode($val, false));
}

// Replace &nbsp; with real space
function replaceNBSP($val)
{
    if ($val === null) return "";
	return str_replace(array("&amp;nbsp;", "&nbsp;"), array(" ", " "), $val);
}

// Decode limited set of html special chars rather than using html_entity_decode
function label_decode($val, $insertSpaceLessThanNumber=true)
{
    if ($val === null) return "";
	// Static arrays used for character replacing in labels/notes
	// (user str_replace instead of html_entity_decode because users may use HTML char codes in text for foreign characters)
	// $orig_chars = array("&amp;","&#38;","&#34;","&quot;","&#39;","&#039;","&#60;","&lt;","&#62;","&gt;");
	// $repl_chars = array("&"    ,"&"    ,"\""   ,"\""    ,"'"    ,"'"     ,"<"    ,"<"   ,">"    ,">"   );
	// $val = str_replace($orig_chars, $repl_chars, $val);

	// Set temporary replacement for &nbsp; HTML character code so that html_entity_decode() doesn't mangle it
	$nbsp_replacement = '|*|RC_NBSP|*|';

	// Replace &nbsp; characters
	$val = str_replace(array("&amp;nbsp;", "&nbsp;"), array($nbsp_replacement, $nbsp_replacement), $val);

	// Unescape any HTML
	$val = html_entity_decode($val, ENT_QUOTES, 'UTF-8');

	// Re-replace &nbsp; characters
	$val = str_replace($nbsp_replacement, "&nbsp;", $val);

	// If < character is followed by a number, dollar sign, or equals sign, which PHP will strip out using striptags, add space after < to prevent string truncation.
	if ($insertSpaceLessThanNumber && strpos($val, "<") !== false) {
		$val = preg_replace("/(<)(\d|\+|-|\.|\*|\\$|=)/", "< $2", $val);
	}

	// Return decoded value
	return $val;
}

// Determine if a string has some printable non-whitespace non-HTML text
function hasPrintableText($val)
{
    if ($val === null) return false;
    // If contains an image or style tag, then return true
    if (strpos($val, "<img ") !== false || strpos($val, "<style") !== false) return true;
    // Remove &nbsp; and other problematic whitespace characters
    $val = trim(replaceNBSP(strip_tags(str_replace(chr(194).chr(160), '', $val))));
    return ($val !== "");
}

// Improved replacement for strip_tags(), which can remove <= or <2 from strings.
function strip_tags2($val)
{
    if ($val === null) return "";
    // Remove &nbsp; and other problematic whitespace characters
    $val = replaceNBSP(label_decode(str_replace(chr(194).chr(160), '', $val)));
    // Perform filtering
	return strip_tags(filter_tags($val, false, false));
}

/**
 * FOR A STRING, CONVERT ALL LINE BREAKS TO SPACES, THEN REPLACE MULTIPLE SPACES WITH SINGLE SPACES, THEN TRIM
 */
function remBr($val) {
    if ($val === null) return "";
	// Replace line breaks with spaces
	$br_orig = array("\r\n", "\r", "\n");
	$br_repl = array(" ", " ", " ");
	$val = str_replace($br_orig, $br_repl, $val);
	// Replace multiple spaces with single spaces
	$val = preg_replace('/\s+/', ' ', $val);
	// Trim and return
	return trim($val);
}


/**
 * Print an array (for debugging purposes)
 */
function print_array($array, $return=false) {
    if ($return) {
	    return "<br><pre>\n".print_r($array,true)."\n</pre>\n";
    } else {
	    print "<br><pre>\n";print_r($array);print "\n</pre>\n";
    }
}


/**
 * Print an array via var_dump (for debugging purposes)
 */
function print_dump($array, $return=false) {
    if ($return) {
	    return "<br><pre>\n".var_export($array,true)."\n</pre>\n";
    } else {
	    print "<br><pre>\n";var_dump($array);print "\n</pre>\n";
    }
}


/**
 * DISPLAY ERROR MESSAGE IF CURL MODULE NOT LOADED IN PHP
 */
function curlNotLoadedMsg() {
	print  "<div class='red'>
				<img src='".APP_PATH_IMAGES."exclamation.png'>
				".RCView::tt_i("config_functions_124", array(
					RCView::a(array(
						"href" => "http://us.php.net/manual/en/book.curl.php",
						"target" => "_blank",
						"style" => "text-decoration:underline;"
					), RCView::tt("config_functions_43"))
				), false)."
			</div>";
}

/**
 * CALCULATES SIZE OF WEB SERVER DIRECTORY (since disk_total_space() function is not always reliable)
 */
function dir_size($dir) {
	$retval = 0;
	$dirhandle = @opendir($dir); // suppress warnings (e.g., permission denied)
    if ($dirhandle) {
        while (($file = readdir($dirhandle)) !== false) {
            if ($file != "." && $file != "..") {
				$path = $dir."/".$file;
				if(is_link($path)) return $retval; // do not follow symlinks
				if (is_dir($path)) {
					$retval = $retval + dir_size($path);
				} else {
					$retval = $retval + @filesize($path); // suppress warnings on failure
				}
            }
        }
    }
	if($dirhandle!==false) closedir($dirhandle);
	return $retval;
}


/**
 * CLEAN BRANCHING LOGIC OR CALC FIELD EQUATION OF ANY ERRORS IN FIELD NAME SYNTAX AND RETURN CLEANED STRING
 */
function cleanBranchingOrCalc($val) {
	return preg_replace_callback("/(\[)([^\[]*)(\])/", "branchingCleanerCallback", $val);
}
// Callback function used when cleaning branching logic
function branchingCleanerCallback($matches) {
	return "[" . preg_replace("/[^a-z0-9:A-Z\(\)\.\,_-]/", "", str_replace(" ", "", $matches[0])) . "]";
}


/**
 * PARSE THE ELEMENT_ENUM COLUMN FROM METADATA TABLE AND RETURN AS ARRAY
 * (WITH CODED VALUES AS KEY AND LABELS AS ELEMENTS)
 */
function parseEnum($select_choices = "")
{
	if (trim($select_choices??"") == "") return array();
	$array_to_fill = array();
	// Catch any line breaks (mistakenly saved instead of \n literal string)
	$select_choices = str_replace("\n", "\\n", trim($select_choices));
	$select_array = explode("\\n", $select_choices);
	// Loop through each choice
	foreach ($select_array as $key=>$value) {
		if (strpos($value,",") !== false) {
			$pos = strpos($value, ",");
			$this_value = trim(substr($value,0,$pos));
			$this_text = trim(substr($value,$pos+1));
			// If a comma was previously replaced with its corresponding HTML character code, re-add it back (especially for SQL field types)
			$this_value = str_replace("&#44;", ",", $this_value);
			$this_text = str_replace("&#44;", ",", $this_text);
		} else {
			// If a comma was previously replaced with its corresponding HTML character code, re-add it back (especially for SQL field types)
			$value = str_replace("&#44;", ",", $value);
			$this_value = $this_text = trim($value);
		}
		// If a choice is duplicated, then merge all labels together for that coded choice
		$this_value = $this_value."";
		if (isset($array_to_fill[$this_value])) {
			$array_to_fill[$this_value] .= ", $this_text";
		} else {
			$array_to_fill[$this_value] = $this_text;
		}
	}
	return $array_to_fill;
}

/**
 * PRINT VALUES OF AN EMAIL (OFTEN DISPLAYED WHEN THERE IS ERROR SENDING EMAIL)
 */
function printEmail($to, $from, $subject, $body) {
	?>
	<p>
		<b>To:</b> <?php echo $to ?><br>
		<b>From:</b> <?php echo $from ?><br>
		<b>Subject:</b> <?php echo $subject ?><br>
		<b>Message:</b><br><?php echo $body ?>
	</p>
	<?php
}

/**
 * DETERMINE MAXIMUM SIZE OF FILES THAT CAN BE UPLOADED TO WEB SERVER (IN MB)
 */
function maxUploadSize() {
	// Get server max (i.e. the lowest of two different server values)
	$max_filesize = (ini_get('upload_max_filesize') != "") ? ini_get('upload_max_filesize') : '1M';
	$max_postsize = (ini_get('post_max_size') 		!= "") ? ini_get('post_max_size') 	    : '1M';
	// If ends with G instead of M, then convert to M format
	if (stripos($max_postsize, 'g')) $max_postsize = preg_replace("/[^0-9]/", "", $max_filesize)*1024 . "M";
	if (stripos($max_filesize, 'g')) $max_filesize = preg_replace("/[^0-9]/", "", $max_filesize)*1024 . "M";
	$max_filesize = preg_replace("/[^0-9]/", "", $max_filesize);
	$max_postsize = preg_replace("/[^0-9]/", "", $max_postsize);
	// Return the smallest of the two
	return (($max_filesize > $max_postsize) ? $max_postsize : $max_filesize);
}
function maxUploadSizeFileRepository() {
	global $file_repository_upload_max;
	$file_repository_upload_max = trim($file_repository_upload_max??"");
	// Get server max (i.e. the lowest of two different server values)
	$server_max = maxUploadSize();
	// Check if we need to use manually set upload max instead
	if ($file_repository_upload_max != "" && is_numeric($file_repository_upload_max) && $file_repository_upload_max < $server_max) {
		return $file_repository_upload_max;
	} else {
		return $server_max;
	}
}
function maxUploadSizeEdoc() {
	global $edoc_upload_max;
	$edoc_upload_max = trim($edoc_upload_max??"");
	// Get server max (i.e. the lowest of two different server values)
	$server_max = maxUploadSize();
	// Check if we need to use manually set upload max instead
	if ($edoc_upload_max != "" && is_numeric($edoc_upload_max) && $edoc_upload_max < $server_max) {
		return $edoc_upload_max;
	} else {
		return $server_max;
	}
}
function maxUploadSizeAttachment() {
	global $file_attachment_upload_max;
	$file_attachment_upload_max = trim($file_attachment_upload_max??"");
	// Get server max (i.e. the lowest of two different server values)
	$server_max = maxUploadSize();
	// Check if we need to use manually set upload max instead
	if ($file_attachment_upload_max != "" && is_numeric($file_attachment_upload_max) && $file_attachment_upload_max < $server_max) {
		return $file_attachment_upload_max;
	} else {
		return $server_max;
	}
}
function maxUploadSizeSendit() {
	global $sendit_upload_max;
	$sendit_upload_max = trim($sendit_upload_max??"");
	// Get server max (i.e. the lowest of two different server values)
	$server_max = maxUploadSize();
	// Check if we need to use manually set upload max instead
	if ($sendit_upload_max != "" && is_numeric($sendit_upload_max) && $sendit_upload_max < $server_max) {
		return $sendit_upload_max;
	} else {
		return $server_max;
	}
}

// Retrieve list of all files and folders within a server directory, sorted alphabetically (output as array)
function getDirFiles($dir) {
	if (is_dir($dir)) {
		$dh = opendir($dir);
		$files = array();
		$i = 0;
        if ($dh) {
            while (false != ($filename = readdir($dh))) {
                if ($filename != "." && $filename != "..") {
                    // Make sure we do not exceed 80% memory usage. If so, return false to prevent hitting memory limit.
                    if (($i % 1000) == 0) {
                        if (memory_get_usage()/1048576 > System::getMemoryLimit()*0.8) return false;
                    }
                    $files[] = $filename;
                    $i++;
                }
            }
        }
		sort($files);
		return $files;
	} else {
		return false;
	}
}

// Output the values from a SQL field type query as an enum string
function getSqlFieldEnum($element_enum, $project_id=null, $record=null, $event_id=null, $instance=null,
						 $user=null, $participant_id=null, $form=null)
{
	//If one field in query, then show field as both coded value and displayed text.
	//If two fields in query, then show first as coded value and second as displayed text.
	if ($element_enum !== null && preg_match("/^select\s+/i", trim($element_enum)) > 0)
	{
		$element_enum = html_entity_decode($element_enum, ENT_QUOTES);
		// Replace Smart Variables
        if ($project_id === null && defined("PROJECT_ID")) $project_id = PROJECT_ID;
		$element_enum = Piping::pipeSpecialTags($element_enum, $project_id, $record, $event_id, $instance, $user, true, $participant_id, $form, false, true);
		// Execute query; Set $forceReplaceDataTable=true to auto-replace any queries from redcap_data to redcap_dataX on the fly
		$rs_temp1_sql = db_query($element_enum, [], null, MYSQLI_STORE_RESULT, false, true);
		if (!$rs_temp1_sql) return "";
		$string_record_select1 = "";
		while ($row = db_fetch_array($rs_temp1_sql, MYSQLI_NUM))
		{
			$string_record_select1 .= str_replace(",", "&#44;", $row[0]);
            $string_record_select1 .= ", " . str_replace(",", "&#44;", $row[1] ?? $row[0]) . " \\n ";
		}
		return substr($string_record_select1, 0, -4);
	}
	return "";
}

// Simple encryption
function encrypt($data, $custom_encryption_key=null, $use_mcrypt=false)
{
	// If $custom_encryption_key is not provided, then use the installation-specific $salt value
	$encryption_key = $custom_encryption_key === null ? $GLOBALS['salt'] : $custom_encryption_key;
	try {
		// Return the data
		if ($use_mcrypt) {
			return encrypt_mcrypt($data, $encryption_key);
		} elseif (openssl_loaded()) {
			return Cryptor::Encrypt($data, $encryption_key);
		} else {
			return false;
		}
	} catch (Exception $e) {
		return false;
	}
}

// Simple decryption
function decrypt($data, $custom_encryption_key=null, $use_mcrypt=false)
{
	// If $custom_encryption_key is not provided, then use the installation-specific $salt value
	$encryption_key = $custom_encryption_key === null ? $GLOBALS['salt'] : $custom_encryption_key;
	try {
		// Return the data
		if ($use_mcrypt) {
			return decrypt_mcrypt($data, $encryption_key);
		} elseif (openssl_loaded()) {
			return Cryptor::Decrypt($data, $encryption_key);
		} else {
			return false;
		}
	} catch (Exception $e) {
		return false;
	}
}

// Simple encryption using Mcrypt extension (no longer supported in PHP 7.1 and higher)
function encrypt_mcrypt($data, $custom_encryption_key=null)
{
	// $salt from db connection file
	global $salt;
	// If $custom_encryption_key is not provided, then use the installation-specific $salt value
	$this_encryption_key = $this_encryption_key_orig = ($custom_encryption_key === null) ? $salt : $custom_encryption_key;
	// Key size needed
	$ideal_key_size = mcrypt_get_key_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	// If salt is too short, keep appending it to itself till it is long enough.
	while (strlen($this_encryption_key) < $ideal_key_size) {
		$this_encryption_key .= $this_encryption_key_orig;
	}
	// If salt is longer than 32 characters, then truncate it to prevent issues
	if (strlen($this_encryption_key) > $ideal_key_size) $this_encryption_key = substr($this_encryption_key, 0, $ideal_key_size);
	// Convert the key to binary
	$this_encryption_key = @pack('H*', $this_encryption_key); // non-hex data will be null
	// Define an encryption/decryption variable beforehand
	defined("MCRYPT_IV") or define("MCRYPT_IV", mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND));
	// Encrypt and return
	return rtrim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this_encryption_key, $data, MCRYPT_MODE_ECB, MCRYPT_IV)),"\0");
}

// Simple decryption using Mcrypt extension (no longer supported in PHP 7.1 and higher)
function decrypt_mcrypt($encrypted_data, $custom_encryption_key=null)
{
	// $salt from db connection file
	global $salt;
	// If $custom_encryption_key is not provided, then use the installation-specific $salt value
	$this_encryption_key = $this_encryption_key_orig = ($custom_encryption_key === null) ? $salt : $custom_encryption_key;
	// Key size needed
	$ideal_key_size = mcrypt_get_key_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	// If salt is too short, keep appending it to itself till it is long enough.
	while (strlen($this_encryption_key) < $ideal_key_size) {
		$this_encryption_key .= $this_encryption_key_orig;
	}
	// If salt is longer than 32 characters, then truncate it to prevent issues
	if (strlen($this_encryption_key) > $ideal_key_size) $this_encryption_key = substr($this_encryption_key, 0, $ideal_key_size);
	// Convert the key to binary
	$this_encryption_key = @pack('H*', $this_encryption_key);  // non-hex data will be null
	// Define an encryption/decryption variable beforehand
	defined("MCRYPT_IV") or define("MCRYPT_IV", mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND));
	// Decrypt and return
	return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this_encryption_key, base64_decode($encrypted_data), MCRYPT_MODE_ECB, MCRYPT_IV),"\0");
}

// Function for checking if mcrypt PHP extension is loaded
function openssl_loaded($show_error=false) {
	if (!function_exists('openssl_encrypt')) {
		if ($show_error) {
			print RCView::div(array('class'=>'red'),
				RCView::tt("global_236"));
			exit;
		} else {
			return false;
		}
	} else {
		return true;
	}
}

// Checks if username and password are valid without disrupting existing REDCap authentication session
function fakeUserLoginForm() { return; }
function checkUserPassword($username, $password, $authSessionName = "login_test")
{
	// Start the session
	Session::init();

	// Get current session_id, which will get inevitably changed if auth is successful
	$old_session_id = Session::sessionId();

    // ALTERNATIVE "PASSWORD" USING 2FA ONE-TIME CODE: If we detect a 6-digit number AND it successfully verifies, then return TRUE
    $esignedWithoutPassword = ($GLOBALS['two_factor_auth_enabled'] && $GLOBALS['two_factor_auth_esign_pin']
                                && $GLOBALS['two_factor_auth_esign_once_per_session'] && $old_session_id === decrypt($password));
    $valid2FAcode = ($GLOBALS['two_factor_auth_enabled'] && $GLOBALS['two_factor_auth_esign_pin'] && strtolower($username) == USERID
                    && ($esignedWithoutPassword || Authentication::verifyTwoFactorCode($password)));
    if ($valid2FAcode) {
        // Add extra logging if password/PIN was not used (for transparency)
        if ($esignedWithoutPassword) {
            $logDescrip = "User e-signed without PIN (provided earlier in session)";
            Logging::logEvent("","redcap_auth","MANAGE",PROJECT_ID,"project_id = ".PROJECT_ID,$logDescrip);
        }
        return true;
    }

	// Set user/pass as Post values so they get processed correctly
	$_POST['password'] = $password;
	$_POST['username'] = $username;

	// Defaults
	$authenticated = false;

	// Build and return the DSN array used for Table-based, LDAP-Table, and LDAP authentication
	$dsn = Authentication::buildDsnArray();

	//if ldap and table authentication Loop through the available servers & authentication methods
	foreach ($dsn as $key=>$dsnvalue)
	{
		if (isset($a)) unset($a);

        // shibboleth_table does not load this library when attempting to esign a form
        // System::init() does load this, but for reasons unknown, it is forgotten
        if (!class_exists('Auth')) require_once dirname(dirname(__FILE__)) . '/Libraries/PEAR/Auth.php';

		$a = new Auth($dsnvalue['type'], $dsnvalue['dsnstuff'], "fakeUserLoginForm");
		$a->setSessionName($authSessionName);
		// Table-based authentication hack during login to bypass Pear DB because of SSL compatibility reasons with "MySQL over SSL"
		if ($dsnvalue['type'] == 'DB' && Authentication::verifyTableUsernamePassword($_POST['username'], $_POST['password']))
		{
			$a->setAuth($_POST['username']);
		}
		// Start auth
		$a->start();
		if ($a->getAuth()) {
			$authenticated = true;
		}
	}

	// Now that we're done, remove this part of the session to prevent conflict with REDCap user sessioning
	unset($_SESSION['_auth_'.$authSessionName]);

	// Because the session_id inevitably changes with this new auth session, change the session_id in log_view table
	// for all past page views during this session in order to maintain consistency of having one session_id per session.
	$new_session_id = Session::sessionId();
	if ($old_session_id != $new_session_id && !defined("NOAUTH"))
	{
		// Only check within past 24 hours (to reduce query time)
		$oneDayAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-1,date("Y")));
		$sql = "update redcap_log_view set session_id = '$new_session_id' where user = '".USERID."'
				and session_id = '$old_session_id' and ts > '$oneDayAgo'";
		db_query($sql);
	}

	// Return value as true/false
	return $authenticated;
}


// Obtain web path to REDCap version folder
function getVersionFolderWebPath()
{
	global $redcap_version;

	// Parse through URL to find version folder path
	$found_version_folder = false;
	$url_array = array();
	foreach (array_reverse(explode("/", PAGE_FULL)) as $this_part)
	{
		if ($this_part == "redcap_v" . $redcap_version)
		{
			$found_version_folder = true;
		}
		if ($found_version_folder)
		{
			$url_array[] = $this_part;
		}
	}
	// If ABOVE the version folder
	if (empty($url_array))
	{
		// First, make special exception if this is the survey page (i.e. .../[redcap]/surveys/index.php)
		$surveyPage  = "/surveys/index.php";
		$apiHelpPage = "/api/help/index.php";
		if (substr(PAGE_FULL, -1*strlen($surveyPage)) == $surveyPage)
		{
			return ((strlen(dirname(dirname(PAGE_FULL))) <= 1) ? "" : dirname(dirname(PAGE_FULL))) . "/redcap_v" . $redcap_version . "/";
		}
		// Check if this is the API Help file
		elseif (substr(PAGE_FULL, -1*strlen($apiHelpPage)) == $apiHelpPage)
		{
			return ((strlen(dirname(dirname(dirname(PAGE_FULL)))) <= 1) ? "" : dirname(dirname(dirname(PAGE_FULL)))) . "/redcap_v" . $redcap_version . "/";
		}
		// If user is above the version folder (i.e. /redcap/index.php, /redcap/plugins/example.php)
		else
		{
			// If 'redcap' folder is not seen in URL, then the version folder is in the server web root
			if (strlen(dirname(PAGE_FULL)) <= 1) {
				return "/redcap_v" . $redcap_version . "/";
			// This is the index.php page above the version folder
			} elseif (defined('PAGE')) {
				return dirname(PAGE_FULL) . "/redcap_v" . $redcap_version . "/";
			// Since the version folder is not one or two directories above, find it manually using other methods
			} else {
				// Make sure allow_url_fopen is enabled, else we can't properly find the version folder
				if (ini_get('allow_url_fopen') != '1')
				{
					exit('<p style="max-width:800px;"><b>Your web server does NOT have the PHP setting "allow_url_fopen" enabled.</b><br>
						REDCap cannot properly process this page because "allow_url_fopen" is not enabled.
						To enable "allow_url_fopen", simply open your web server\'s PHP.INI file for editing and change the value of "allow_url_fopen" to
						<b>On</b>. Then reboot your web server and reload this page.</p>');
				}
				// Try to find the file database.php in every directory above the current directory until it's found
				$revUrlArray = array_reverse(explode("/", PAGE_FULL));
				// Remove unneeded array elements
				array_pop($revUrlArray);
				array_shift($revUrlArray);
				// Loop through the array till we find the location of the version folder to return
				foreach ($revUrlArray as $key=>$urlPiece)
				{
					// Set subfolder path
					$subfolderPath = implode("/", array_reverse($revUrlArray));
					// Set the possible path of where to search for database.php
					$dbWebPath = (SSL ? "https" : "http") . "://" . SERVER_NAME . "$port/$subfolderPath/database.php";
					// Try to call database.php to see if it exists
					$dbWebPathContents = file_get_contents($dbWebPath);
					// If we found database.php, then return the proper path of the version folder
					if ($dbWebPathContents !== false) {
						return "/$subfolderPath/redcap_v" . $redcap_version . "/";
					}
					// Unset this array element so it does not get reused in the next loop
					unset($revUrlArray[$key]);
				}
				// Version folder was NOT found
				return "/redcap_v" . $redcap_version . "/";
			}
		}
	}
	// If BELOW the version folder
	else
	{
		return implode("/", array_reverse($url_array)) . "/";
	}
}

// Render ExtJS-like panel
function renderPanel($title, $html, $id="", $collapsed=false)
{
	$id = ($id == "") ? "" : " id=\"$id\"";
	$collapsed_style = $collapsed ? ' style="display:none;"' : '';
	return '<div class="x-panel"'.$id.'>'
		 . ((trim($title) == '') ? '' : '<div class="x-panel-header x-panel-header-leftmenu">' . $title .'</div>')
		 . '<div class="x-panel-bwrap"'.$collapsed_style.'><div class="x-panel-body"><div class="menubox">' . $html . '</div></div></div></div>';
}

function renderTable(
    $id,
    $title,
    $col_widths_headers = [],
    &$row_data = [],
    bool $show_headers = true,
    bool $enable_header_sort = true,
    bool $outputToPage = true,
    bool $initiallyHide = false
): ?string {
    // Generate unique table ID if not provided
    $id = trim($id) ?: substr(sha1(random_int(0, PHP_INT_MAX)), 0, 8);
    $table_id = "table-$id";
    $table_style = $initiallyHide ? 'style="display:none;"' : '';

    // Normalize column settings
    $normalizeColumn = function($col) {
		return [
			$col[0],                      // Header text
			$col[1] ?? 'left',            // Alignment (default: left)
			$col[2] ?? 'string',          // Data type (default: string)
			$col[3] ?? true               // Sortable (default: true)
    	];
	};

    $col_widths_headers = array_map($normalizeColumn, $col_widths_headers);

    // Generate table header
    $renderHeader = function () use ($col_widths_headers, $enable_header_sort, $table_id): string {
        return array_reduce($col_widths_headers, function (string $carry, array $col) use ($enable_header_sort, $table_id): string {
            [$header, $align, $type, $sortable] = $col;
            $sort_attr = $enable_header_sort && $sortable ? " onclick=\"SortTable('$table_id', '0', '$type');\"" : " style=\"cursor:initial;\"";
            $align_style = $align !== 'left' ? 'text-align:' . $align . ';' : '';
            return $carry . "<th$sort_attr align=\"$align\"><div style=\"$align_style\">$header</div></th>";
        }, '');
    };

    // Generate table body
    $renderBody = function () use ($row_data, $col_widths_headers): string {
		$row_settings = array_map(function ($col) { return ['align' => $col[1]]; }, $col_widths_headers);
        $body = '';
        foreach ($row_data as $row_index => $row) {
            $row_class = $row_index % 2 === 0 ? '' : ' class="erow"';
            $body .= "<tr$row_class>";
            foreach ($row as $col_index => $cell) {
                $align = $row_settings[$col_index]['align'] ?? 'left';
                $align_attr = $align !== 'left' ? " align=\"$align\"" : '';
                $body .= "<td$align_attr><div>$cell</div></td>";
            }
            $body .= "</tr>";
        }
        return $body;
    };

    // Build the complete table
    $grid = "<div id=\"$id\" class=\"d-flex flex-column ms-2 me-2\">
        <div style=\"background-color:#D7D7D7;\" class=\"font-weight-bold p-2\">
            <div class=\"ftitle\"" . (trim($title) ? '' : ' style="display:none;"') . ">$title</div>
        </div>
        <table id=\"$table_id\" class=\"table table-bordered table-striped table-hover table-sm\" $table_style>
            <thead" . ($show_headers ? '' : ' style="display:none;"') . ">
                <tr>" . $renderHeader() . "</tr>
            </thead>
            <tbody>" . $renderBody() . "</tbody>
        </table>
    </div>";

    // Output or return the grid
    if ($outputToPage) {
        echo $grid;
        return null;
    } 
    return $grid;
}

// Render ExtJS-like grid/table
function renderGrid($id, $title, $width_px='auto', $height_px='auto', $col_widths_headers=array(), &$row_data=array(),
					$show_headers=true, $enable_header_sort=true, $outputToPage=true, $initiallyHide=false)
{
	## SETTINGS
	// $col_widths_headers = array(  array($width_px, $header_text, $alignment, $data_type), ... );
	// $data_type = 'string','int','date'
	// $row_data = array(  array($col1, $col2, ...), ... );

	// Set dimensions and settings
	$width = is_numeric($width_px) ? "width: " . $width_px . "px;" : "width: 100%;";
	$height = ($height_px == 'auto') ? "" : "height: " . $height_px . "px; overflow-y: auto;";
	if (trim($id) == "") {
		$id = substr(sha1(rand()), 0, 8);
	}
	$table_id_js = "table-$id";
	$table_id = "id=\"$table_id_js\"";
	$id = "id=\"$id\"";

	// Check column values
	$row_settings = array();
	foreach ($col_widths_headers as $this_key=>$this_col)
	{
		$this_width  = is_numeric($this_col[0]) ? ($this_col[0]+10) . "px" : "100%";
		$this_header = $this_col[1];
		$this_align  = isset($this_col[2]) ? $this_col[2] : "left";
		$this_type   = isset($this_col[3]) ? $this_col[3] : "string";
		$this_sort   = isset($this_col[4]) ? $this_col[4] : true;
		// Re-assign checked values
		$col_widths_headers[$this_key] = array($this_width, $this_header, $this_align, $this_type, $this_sort);
		// Add width and alignment to other array (used when looping through each row)
		$row_settings[] = array('width'=>$this_width, 'align'=>$this_align);
	}

	// Render grid
	$id2 = preg_replace("/^id\s*=\s*[\"\']?/", "", $id);
	$id2 = preg_replace("/[\"\']$/", "", $id2);
	$id2 = "#table-".$id2;
	$grid = '
	<div class="flexigrid" ' . $id . ' style="' . $width . $height .'">
		<div class="mDiv">
			<div class="ftitle" ' . ((trim($title) != "") ? "" : 'style="display:none;"') . '>'.$title.'</div>
		</div>
		<div class="hDiv" ' . ($show_headers ? "" : 'style="display:none;"') . '>
			<div class="hDivBox">
				<table ' . ($initiallyHide ? 'style="display:none;"' : '') . '>
					<tr>';
	foreach ($col_widths_headers as $col_key=>$this_col)
	{
		$grid .= 	   '<th' . (($enable_header_sort && $this_col[4]) ? " onclick=\"SortTable('$table_id_js',$col_key,'{$this_col[3]}');\"" : " style=\"cursor:initial;\"") . ($this_col[2] == 'left' ? '' : ' align="'.$this_col[2].'"') . '>
							<div style="' . ($this_col[2] == 'left' ? '' : 'text-align:'.$this_col[2].';') . 'width:' . $this_col[0] . ';">
								' . $this_col[1] . '
							</div>
						</th>';
	}
	$grid .= 	   '</tr>
				</table>
			</div>
		</div>
		<div class="bDiv">
			<table ' . $table_id . ' ' . ($initiallyHide ? 'style="display:none;"' : '') . '>';
	$row_key_num = 0;
	if (!is_array($row_data)) $row_data = array();
	foreach ($row_data as $row_key=>$this_row)
	{
		$grid .= '<tr' . ($row_key_num%2==0 ? '' : ' class="erow"') . '>';
		foreach ($this_row as $col_key=>$this_col)
		{
			$grid .= '<td' . ((isset($row_settings[$col_key]) && $row_settings[$col_key]['align'] == 'left') ? '' : ' align="' . (isset($row_settings[$col_key]) ? $row_settings[$col_key]['align'] : '') . '"') . '>
						<div ';
			if (isset($row_settings[$col_key]) && $row_settings[$col_key]['align'] == 'center') {
				$grid .= 'class="fc" ';
			} elseif (isset($row_settings[$col_key]) && $row_settings[$col_key]['align'] == 'right') {
				$grid .= 'class="fr" ';
			}
			$grid .= 'style="width:' . (isset($row_settings[$col_key]) ? $row_settings[$col_key]['width'] : '') . ';">' . $this_col . '</div>
					  </td>';
		}
		$grid .= '</tr>';
		// Delete last row to clear up memory as we go
		unset($row_data[$row_key]);
		$row_key_num++;
	}
	$grid .= '</table>
		</div>
	</div>
	';

	// Render grid (or return as html string)
	if ($outputToPage) {
		print $grid;
		unset($grid);
	} else {
		return $grid;
	}
}

// Returns HTML table from an SQL query (can include title to display)
function queryToTable($sql,$title="",$outputToPage=false,$tableWidth=null)
{
	$QQuery = db_query($sql);
	$num_rows = db_num_rows($QQuery);
	$num_cols = db_num_fields($QQuery);
	$failedText = ($QQuery ? "" : "<span style='color:red;'>ERROR - Query failed!</span>");
	$tableWidth = (is_numeric($tableWidth) && $tableWidth > 0) ? "width:{$tableWidth}px;" : "";

	$html_string = "<table class='dt2' style='font-family:Verdana;font-size:11px;$tableWidth'>
						<tr class='grp2'><td colspan='$num_cols'>
							<div style='color:#800000;font-size:14px;max-width:700px;'>$title</div>
							<div style='font-size:11px;padding:12px 0 3px;'>
								<b>".RCView::tt("custom_reports_02")."&nbsp; <span style='font-size:13px;color:#800000'>$num_rows</span></b>
								$failedText
							</div>
						</td></tr>
						<tr class='hdr2' style='white-space:normal;'>";

	if ($num_rows > 0) {

		// Display column names as table headers
		for ($i = 0; $i < $num_cols; $i++) {

			$this_fieldname = db_field_name($QQuery,$i);
			//Display the "fieldname"
			$html_string .= "<td style='padding:5px;'>$this_fieldname</td>";
		}
		$html_string .= "</tr>";

		// Display each table row
		$j = 1;
		while ($row = db_fetch_array($QQuery)) {
			$class = ($j%2==1) ? "odd" : "even";
			$html_string .= "<tr class='$class notranslate'>";
			for ($i = 0; $i < $num_cols; $i++)
			{
				// Escape the value in case of harmful tags
				$this_value = htmlspecialchars(html_entity_decode($row[$i], ENT_QUOTES), ENT_QUOTES);
				$html_string .= "<td style='padding:3px;border-top:1px solid #CCCCCC;font-size:11px;'>$this_value</td>";
			}
			$html_string .= "</tr>";
			$j++;
		}

		$html_string .= "</table>";

	} else {

		for ($i = 0; $i < $num_cols; $i++) {

			$this_fieldname = db_field_name($QQuery,$i);

			//Display the Label and Field name
			$html_string .= "<td style='padding:5px;'>$this_fieldname</td>";
		}

		$html_string .= "</tr><tr><td colspan='$num_cols' style='font-weight:bold;padding:10px;color:#800000;'>".RCView::tt("custom_reports_06")."</td></tr></table>";

	}

	if ($outputToPage) {
		// Output table to page
		print $html_string;
	} else {
		// Return table as HTML
		return $html_string;
	}
}

// Return the SQL query results in CSV format
function queryToCsv($query)
{
	// Execute query
	$result = db_query($query);
	if (!$result) return false;
	$num_fields = db_num_fields($result);
	// Set headers
	$headers = array();
	for ($i = 0; $i < $num_fields; $i++) {
		$headers[] = db_field_name($result, $i);
	}
	// Begin writing file from query result
	$fp = fopen('php://memory', "x+");
	if ($fp && $result) {
		fputcsv($fp, $headers, User::getCsvDelimiter(), '"', '');
		while ($row = db_fetch_array($result, MYSQLI_NUM)) {
			fputcsv($fp, $row, User::getCsvDelimiter(), '"', '');
		}
		// Open file for reading and output to user
		fseek($fp, 0);
		return stream_get_contents($fp);
	}
	return false;
}

// Converts html line breaks to php line breaks (opposite of PHP's nl2br() function
function br2nl($string){
    if ($string === null) return "";
	return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
}

// Converts NL \n into CRNL \r\n (and if already has CRNL, then doesn't change it)
function nl2crnl($string){
    if ($string === null) return "";
	return str_replace(array("\r\n", "\n"), array("\n", "\r\n"), $string);
}

// Transform a string to camel case formatting (i.e. remove all non-alpha-numerics and spaces) and truncate it
function camelCase($string, $leave_spaces=false, $char_limit=30)
{
    if ($string === null) return "";
	$string = ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", $string));
	if (!$leave_spaces) {
		$string = str_replace(" ", "", $string);
	}
	return substr($string, 0, $char_limit);
}

// Transform a string to snake case from camel case formatting
function fromCamelCase($input)
{
    if ($input === null) return "";
	preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
	$ret = $matches[0];
	foreach ($ret as &$match) {
		$match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
	}
	return implode('_', $ret);
}

// Initialize auto-logout popup timer and logout reset timer listener
function initAutoLogout()
{
	global $auth_meth_global, $autologout_timer;
	// Only set auto-logout if not using "none" authentication and if timer value is set
	if ($auth_meth_global != "none" && $autologout_timer != "0" && is_numeric($autologout_timer) && !defined("NOAUTH"))
	{
		print "
		<script type='text/javascript'>
		$(function(){
			initAutoLogout(".Authentication::AUTO_LOGOUT_RESET_TIME.",$autologout_timer);
		});
		</script>";
	}
}

// Replace URLs containing old REDCap version numbers with the current version number
function replaceUrlOldRedcapVersion($val)
{
    // EMBEDDED IMAGES IN TEXT WITH OLD VERSION NUMBERS IN URL
    // Also replace the REDCap version (if is present in the URL) with the current version
    if (defined("APP_PATH_WEBROOT_FULL") && strpos($val, "/redcap_v") !== false)
    {
        // Replace version number in whole REDCap URL (and the domain must match)
        preg_match_all("/(".preg_quote(APP_PATH_WEBROOT_FULL,'/').")(redcap_v)([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2})(\/)/", $val, $matches);
        if (isset($matches[0])) {
            foreach ($matches[0] as $key=>$fullmatch) {
                $versionInUrl = $matches[3][$key];
                if ($versionInUrl != REDCAP_VERSION) {
                    $val = str_replace($fullmatch, APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/", $val);
                }
            }
        }
        // Replace version number in relative URL
        preg_match_all("/(".preg_quote("https://",'/').".+|".preg_quote("http://",'/').".+|\/)?(redcap_v)([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2})(\/)/", $val, $matches);
        //print_array($matches);
        if (isset($matches[0])) {
            foreach ($matches[0] as $key=>$fullmatch) {
                // If the match begins with http but is not the current REDCap server, skip it
                if (strpos($fullmatch, "http") === 0 && $matches[1][$key] != APP_PATH_WEBROOT_FULL) {
                    continue;
                }
                // Replace relative URL
                $versionInUrl = $matches[3][$key];
                if ($versionInUrl != REDCAP_VERSION) {
                    $val = str_replace("/redcap_v" . $versionInUrl . "/", "/redcap_v" . REDCAP_VERSION . "/", $val);
                }
            }
        }
    }
    return $val;
}

// Replace URLs containing image_view.php with survey-endpoint version for compatibility reasons
function replaceUrlImgViewEndpoint($val)
{
	if ($val === null) return "";
    // EMBEDDED IMAGES IN TEXT (reformat URL for compatibility various scenarios)
    if (strpos($val, "DataEntry/image_view.php") !== false)
    {
        // Replace URLs containing old REDCap version numbers with the current version number
        $val = replaceUrlOldRedcapVersion($val);
        // EMBEDDED IMAGES IN TEXT ON SURVEYS: If this is a survey page where we're filtering field labels, survey instructions, and other user input text, we need to replace
        // all usages of DataEntry/image_view.php (an authenticated URL) with __passthru=DataEntry%2Fimage_view.php (a survey URL) so that embedded images work correctly.
        if (defined("PAGE") && PAGE == 'surveys/index.php') {
            if (isset($_GET['s'])) {
                $val = str_replace("redcap_v".REDCAP_VERSION."/DataEntry/image_view.php?", "surveys/index.php?s={$_GET['s']}&__passthru=DataEntry%2Fimage_view.php&", $val);
            }
            // EMBEDDED IMAGES on public project dashboards
            elseif (isset($_GET['__dashboard'])) {
                $val = str_replace("redcap_v".REDCAP_VERSION."/DataEntry/image_view.php?", "surveys/index.php?__dashboard={$_GET['__dashboard']}&__passthru=DataEntry%2Fimage_view.php&", $val);
            }
            // EMBEDDED IMAGES on public reports
            elseif (isset($_GET['__report'])) {
                $val = str_replace("redcap_v".REDCAP_VERSION."/DataEntry/image_view.php?", "surveys/index.php?__report={$_GET['__report']}&__passthru=DataEntry%2Fimage_view.php&", $val);
            }
        }
    }
    // SURVEY BASE URL: Make sure that images in rich text labels point to the Survey Base URL, if applicable.
	$survey_base_url = (string)($GLOBALS['redcap_survey_base_url'] ?? '');
	if (
		$survey_base_url !== '' &&
		defined("PAGE") && PAGE == 'surveys/index.php' &&
		strpos($val, 'img src="' .APP_PATH_WEBROOT_FULL) !== false &&
		strpos($val, '__passthru=') !== false
	) {
		// See https://regex101.com/r/s6ESma/1
		$re = '/(<img\s+[^>]*src=[\'"])('.preg_quote(APP_PATH_WEBROOT_FULL, '/').')([^>\s]*__passthru[^>\s]*)/m';
		$val = preg_replace_callback(
			$re,
			function ($matches) use ($survey_base_url) {
				return $matches[1] . $survey_base_url . $matches[3];
			},
			$val
		);
    }
    return $val;
}

// Is a string UTF-8 encoded?
function isUTF8($string)
{
	return (mb_detect_encoding($string, "UTF-8", true) == "UTF-8");
}

// Replace the HREF attribute value in an HTML hyperlink if it does not begin with http or mailto
function replace_href_attribute($val) {
    // Do faster check first
    if (strpos($val, 'href') === false || strpos($val, '=') === false) {
        return $val;
    }
    // Replace any uses of "javascript:" inside href attributes
    $regex =  '/<.*?href\s*=\s*(["\']?)(.*?)(?<!\\\\)\1([^>]*)>/im';
    $n = preg_match_all($regex, $val, $matches);
    for ($i = 0; $i < $n; $i++) {
        $val = str_replace($matches[2][$i], remove_javascript_word($matches[2][$i]), $val);
        $val = str_replace($matches[3][$i], remove_javascript_word($matches[3][$i]), $val);
    }
    return $val;
}

/**
 * Removes any sequence of pure letters that matches "javascript"
 * from a string and replaces it with "removed"
 * E.g., "java2script" -> "removed"
 * @param string $in The input string
 * @return string
 */
function remove_javascript_word($in)
{
	$s = mb_strtolower($in);
    // If the HREF value begins with "#", "http://", or "https://", then it's legit.
    if ($in === '' || strpos($s, '#') === 0 || strpos($s, 'http://') === 0 || strpos($s, 'https://') === 0) {
        return $in;
    }
	$chars = [];
	$pos = [];
    // Remove HTML character codes for special white space characters that might be used inside the word "javascript"
    $s = str_ireplace(["&amp;","&#38;","&Tab;","&#9;","&#13;","&NewLine;","&#10;"], ["&","&","","","","",""], $s);
    // Replace any malformed HTML character codes that might be used inside the word "javascript"
    $s = decode_html_char_codes($s);
	// Find all pure letters and store positions in original string
	for ($i = 0; $i < mb_strlen($s); $i++) {
		if (ord($s[$i]) < 96 || ord($s[$i]) > 122) continue;
		$chars[] = $s[$i];
		$pos[] = $i;
	}
	// Create a string from the pure letters
	$j = join('', $chars);
    // If the string does not contain "javascript", return the original string as is
    if (strpos($j, 'javascript') === false) return $in;
	// Find all occurrences of "javascript" and replace with "removed"
	$start = 0;
	$end = 0;
	$out = mb_substr($in, 0, $pos[0]);
	while (($start = strpos($j, 'javascript', $end)) !== false) {
		$out = mb_substr($in, $end, $pos[$start])."removed;";
		$end = $start+10;
	}
	$out .= mb_substr($in, $pos[$end]);
	return $out;
}

// In a string, replace malformed HTML character codes (i.e., do not end with semicolon) with a well-formed version
function decode_html_char_codes($input)
{
    // Replace hex character codes without semicolon
    $input = preg_replace_callback('/&#x([0-9a-fA-F]+)(?!;)/', function($matches) {
        return mb_chr(hexdec($matches[1]), 'UTF-8');
    }, $input);

    // Replace decimal character codes without semicolon
    $input = preg_replace_callback('/&#([0-9]+)(?!;)/', function($matches) {
        return mb_chr((int)$matches[1], 'UTF-8');
    }, $input);

    // Decode well-formed entities with semicolons (just in case)
    return html_entity_decode($input, ENT_QUOTES, 'UTF-8');
}


// Filter potentially harmful html tags
function filter_tags($val, $preserve_allowed_tags=true, $filter_javascript=true, $doRecursive=true, $filter_javascript_without_tags=false)
{
    if ($val === null) return "";
    $val = html_entity_decode($val, ENT_QUOTES);
    $allowedTags = ALLOWED_TAGS."<".Piping::getFakeReplacementTagObject().">";

    // EMBEDDED MYCAP QR CODE IN TEXT (reformat URL for compatibility various scenarios)
    if ($preserve_allowed_tags && $doRecursive && ($GLOBALS['mycap_enabled_global']??false)
        && (!defined("PROJECT_ID") || (defined("PROJECT_ID") && isset($GLOBALS['mycap_enabled']) && $GLOBALS['mycap_enabled']))
        && strpos($val, "MyCap/participant_info.php") !== false)
    {
        preg_match_all('/(src)=[\"\'](.+?)[\"\'].*?/i', $val, $result);
        foreach ($result[2] as $key => $img_src)
        {
            if (strpos($img_src, "MyCap/participant_info.php") === false) continue;
            // If REDCap URL is not same server or same REDCap version, rewrite the URL for the current REDCap URL and version
            if (strpos($img_src, APP_PATH_WEBROOT_FULL) !== 0) {
                list ($nothing, $ending) = explode("MyCap/participant_info.php", $img_src, 2);
                if (defined("PAGE") && PAGE == 'surveys/index.php') {
                    // Survey passthru
                    $replaceUrl = APP_PATH_SURVEY_FULL."index.php{$ending}&s={$_GET['s']}&__passthru=".urlencode("MyCap/participant_info.php");
                } else {
                    // Normal REDCap URL
                    $replaceUrl = APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/MyCap/participant_info.php".$ending;
                }
                $val = str_replace($img_src, $replaceUrl, $val);
            }
        }
    }

    // Replace URLs containing old REDCap version numbers with the current version number
    if ($preserve_allowed_tags && $doRecursive) {
        $val = replaceUrlOldRedcapVersion($val);
    }

    // Replace URLs containing image_view.php with survey-endpoint version for compatibility reasons
    if ($preserve_allowed_tags && $doRecursive) {
        $val = replaceUrlImgViewEndpoint($val);
    }

	// Prevent strip_tags() from removing non-tags that look like tags (e.g., "<4" and "<>")
	$hasLessThan = (strpos($val, '<') !== false);
	if ($hasLessThan) {

        // Remove all line breaks/carriage returns inside a <style> block and also occurring immediately before or after the opening/closing tags
        $val = styleTagReplaceLineBreaks($val);

		// Remove any HTML comments
		if (strpos($val, '<!--') !== false) {
			$val = preg_replace("/<!--.*?-->/ms", "", $val);
            // Escape any unclosed HTML comments
			$val = preg_replace('/(<!--)(.|\s)*?(?!-->)/', '< !--$2', $val);
		}

		// Do quick replace and re-replace of <> because strip_tags will remove it
		$not_equal_replacemenet = "||--RC_NOT_EQUAL_TO--||";
		$val = str_replace("<>", $not_equal_replacemenet, $val);

		// Do quick replace and re-replace of <> because strip_tags will remove it
		$ls_equal_replacement = "||--RC_LS_EQUAL_TO--||";
		$val = str_replace("<=", $ls_equal_replacement, $val);

		// Do quick replace and re-replace of <! because strip_tags will remove it
		$ls_exclaim_replacement = "||--RC_LS_EXCLAIM--||";
		$val = str_replace("<!", $ls_exclaim_replacement, $val);

		## Do replace of legitimate tags so we can weed out the ones that *look* legitimate to browsers (e.g., "<this is not real>")
		// Set replacement strings for < and >
		$lt_realtag_replacement = "||--RC_REALTAG_LT--||";
		$gt_realtag_replacement = "||--RC_REALTAG_GT--||";
		$lt_notrealtag_replacement = "||--RC_NOTREALTAG_LT--||";

		// Build regex to replace the "<" part of all allowable HTML tags
		$tags_list = explode("><", substr($allowedTags, 1, -1));
		$regex_realtag = implode("|", $tags_list);
		$pattern = '/<\/?(' . $regex_realtag . ')[^>]*>/i';
		$replacement = function ($matches) use ($lt_realtag_replacement, $gt_realtag_replacement, $tags_list) {
			// Extract the tag name from the match
			preg_match('/<\/?(\w+)/', $matches[0], $tagMatches);
			$tagName = strtolower($tagMatches[1]);
		
			// Check if the tag name is in the allowed tags list
			if (in_array($tagName, $tags_list, true)) {
				return str_replace(['<', '>'], [$lt_realtag_replacement, $gt_realtag_replacement], $matches[0]);
			}
			// Return the original match if the tag is not in the allowed list
			return $matches[0];
		};
		$val = preg_replace_callback($pattern, $replacement, $val);

		// Any remaining "<" must not be valid tags, so put spaces directly after them
		$val = preg_replace("/(<)([^0-9])(\s?)/", $lt_notrealtag_replacement."$2$3", $val);

        // // Manually remove some tags...
		// // Due to conflict of <i> in regex mistakenly replacing for <iframe>, do this manually here to remove it
		// $val = str_ireplace([$lt_realtag_replacement."iframe", $lt_realtag_replacement."/iframe".$gt_realtag_replacement, $gt_realtag_replacement."></iframe>"], ["<iframe", "></iframe>", "></iframe>"], $val);
		// // Due to conflict of ?? in regex mistakenly replacing for <embed>, do this manually here to remove it
		// $val = str_ireplace([$lt_realtag_replacement."embed", $lt_realtag_replacement."/embed".$gt_realtag_replacement, $gt_realtag_replacement."></embed>"], ["<embed", "></embed>", "></embed>"], $val);
		// // Due to conflict of <b> in regex mistakenly replacing for <base>, do this manually here to remove it
		// $val = str_ireplace($lt_realtag_replacement."base", "<base>", $val); // Add > since it often has no closing tag

		// Do quick replace and re-replace of <# because strip_tags will remove it
		$ls_num_replacement = " ||--RC_LS_NUM--||";
		$val = preg_replace("/(<)(\d)/", "$1".$ls_num_replacement."$2", $val);
	}
	// Remove all but the allowed tags
	if ($preserve_allowed_tags) {
		$val = strip_tags($val, $allowedTags);
	} else {
		$val = strip_tags($val);
	}
	// Re-replace <>, <# and any injected javascript
	if ($hasLessThan) {
		// Re-add "<" for legitimate and legitimate-looking tags
		$val = str_replace($lt_realtag_replacement, "<", $val);
		$val = str_replace($gt_realtag_replacement, ">", $val);
		$val = str_replace(array($lt_notrealtag_replacement, "<  "), array("< ", "< "), $val); // Add space after this one because browsers *might* interpret it as a tag and make it invisible
		// Re-replace <>
		$val = str_replace($not_equal_replacemenet, "<>", $val);
		// Re-replace <=
		$val = str_replace($ls_equal_replacement, "<=", $val);
		// Re-replace <!
		$val = str_replace($ls_exclaim_replacement, "<!", $val);
		// Re-replace <#
		$val = str_replace($ls_num_replacement, "", $val);
		// If any allowed tags contain javascript inside them, then remove javascript due to security issue.
		if ($filter_javascript_without_tags || ($filter_javascript && strpos($val, '>') !== false))
		{
		    // Make sure nothing malicious is hidden as malformed HTML character codes (missing a semi-colon), so add semi-colon and then decode to clean it
            $val = html_entity_decode(preg_replace('/(&#\d{2,3});?/i', "$1;", $val), ENT_QUOTES);
			// Replace any uses of "javascript:" inside any HTML tag attributes
            $val = replace_href_attribute($val);
			// Replace any JavaScript events that are used as HTML tag attributes
			$regex = "/(<)([^<>]+)(\s*[^a-z]+)(\s+[\/]?src\s*[^\/]*\/)?(on[a-z]+\s*=)([^>]*>)?/i";
			do {
				$val = preg_replace($regex, "$1$2$3removed=$6", $val);
			} while (preg_match($regex, $val));
            // If any on-events made it through, remove them specifically
            if (preg_match("/(on[a-z]+\s*=)/i", $val)) {
                // Create a list of all JavaScript on-events, and put them in a pipe-delimited list.
                $onevents = 'onclick|ondblclick|onmousedown|onmouseup|onmouseover|onmousemove|onmouseout|onmouseenter|onmouseleave|onwheel|onkeydown|onkeypress|onkeyup|onblur|onchange|oncontextmenu|onfocus|oninput|oninvalid|onreset|onsearch|onselect|onsubmit|onafterprint|onbeforeprint|onbeforeunload|onerror|onhashchange|onload|onmessage|onoffline|ononline|onpagehide|onpageshow|onpopstate|onresize|onscroll|onstorage|onunload|ondrag|ondragend|ondragenter|ondragleave|ondragover|ondragstart|ondrop|oncopy|oncut|onpaste|onabort|oncanplay|oncanplaythrough|ondurationchange|onemptied|onended|onerror|onloadeddata|onloadedmetadata|onloadstart|onpause|onplay|onplaying|onprogress|onratechange|onseeked|onseeking|onstalled|onsuspend|ontimeupdate|onvolumechange|onwaiting|onshow|ontoggle';
                $val = preg_replace("/(($onevents)\s*=)/i", "removed=", $val);
            }
		}
	}
    // Replace any JavaScript events that are NOT used as HTML tag attributes but exist on their own in the string
    if ($filter_javascript_without_tags) {
        // Replace any uses of "javascript:" inside any HTML tag attributes
        $val = replace_href_attribute($val);
        // Replace any JavaScript events that are NOT used as HTML tag attributes but exist on their own in the string
        $regex = "/(\s*[^a-z]+)(\s+[\/]?src\s*[^\/]*\/)?(on[a-z]+\s*=)([^>]*)/i";
        do {
            $val = preg_replace($regex, "$1removed=$4", $val);
        } while (preg_match($regex, $val));
    }
	// Make sure we run this more than once because it may take a couple times to filter everything out
	if ($doRecursive) {
	    $new_val = filter_tags($val, $preserve_allowed_tags, $filter_javascript, false, $filter_javascript_without_tags);
	    if ($new_val !== $val) {
	        return filter_tags($new_val, $preserve_allowed_tags, $filter_javascript, true, $filter_javascript_without_tags);
	    }
	}
	// Return string value
	return $val;
}

// Remove all line breaks/carriage returns inside a <style> block and also occurring immediately before or after the opening/closing tags
function styleTagReplaceLineBreaks($text) {
    return preg_replace_callback('/[\n\r]*<(style)(?:(?!<\/?\1).)*?<\/\1>[\n\r]*/is', "styleTagReplaceCallback", $text);
}
function styleTagReplaceCallback($matches) {
    return trim(str_replace(["\r\n", "\n", "\r"], " ", br2nl($matches[0])));
}

// Render divs holding javascript form-validation text (when error occurs), so they get translated on the page
function renderValidationTextDivs()
{
	?>
	<!-- Add hidden text for slider accessiblity on surveys -->
	<div id="slider-0means" class="hidden"><?=RCView::tt("survey_1142")?></div>
	<div id="slider-50means" class="hidden"><?=RCView::tt("survey_1143")?></div>
	<div id="slider-100means" class="hidden"><?=RCView::tt("survey_1144")?></div>

	<!-- Text used for field validation errors -->
	<div id="valtext_divs">
		<div id="valtext_number"><?=RCView::tt("config_functions_52")?></div>
		<div id="valtext_integer"><?=RCView::tt("config_functions_53")?></div>
		<div id="valtext_vmrn"><?=RCView::tt("config_functions_54")?></div>
		<div id="valtext_rangehard"><?=RCView::tt("config_functions_56")?></div>
		<div id="valtext_rangesoft1"><?=RCView::tt("config_functions_57")?></div>
		<div id="valtext_rangesoft2"><?=RCView::tt("config_functions_58")?></div>
		<div id="valtext_time"><?=RCView::tt("config_functions_59")?></div>
		<div id="valtext_zipcode"><?=RCView::tt("config_functions_60")?></div>
		<div id="valtext_phone"><?=RCView::tt("config_functions_130")?></div>
		<div id="valtext_email"><?=RCView::tt("config_functions_62")?></div>
		<div id="valtext_regex"><?=RCView::tt("config_functions_77")?></div>
		<div id="valtext_requiredformat"><?=RCView::tt("config_functions_94")?></div>
	</div>
	<!-- Regex used for field validation -->
	<script>
		if (typeof window.REDCap == 'undefined') {
			window.REDCap = {};
		}
		window.REDCap.validations = {};
		<?php foreach (getValTypes() as $valType=>$attr) { ?>
		window.REDCap.validations['<?=$valType?>'] = <?=json_encode([
			"datatype" => $attr["data_type"],
			"regex" => $attr["regex_js"]
		])?>;
		<?php } ?>
	</script>
	<div id="valregex_divs">
	<?php foreach (getValTypes() as $valType=>$attr) { ?>
		<div id="valregex-<?php echo $valType ?>" datatype="<?php echo $attr['data_type'] ?>" label="<?php echo js_escape2($attr['validation_label']) ?>"></div>
	<?php } ?>
	</div>
	<?php
}

// Will convert a legacy field validation type (e.g., int, float, date) into a real value (e.g., integer, number, date_ymd).
// If not a legacy validation type, then will just return as-is.
function convertLegacyValidationType($legacyType)
{
	if ($legacyType == "int") {
		$realType = "integer";
	} elseif ($legacyType == "float") {
		$realType = "number";
	} elseif ($legacyType == "datetime_seconds") {
		$realType = "datetime_seconds_ymd";
	} elseif ($legacyType == "datetime") {
		$realType = "datetime_ymd";
	} elseif ($legacyType == "date") {
		$realType = "date_ymd";
	} else {
		$realType = $legacyType;
	}
	return $realType;
}

// Will convert an _mdy or _dmy date[time] validation into _ymd (often for PHP/back-end data validation purposes)
function convertDateValidtionToYMD($valType)
{
    if ($valType === null) {
        $realType = "";
    } elseif (substr($valType, 0, 16) == "datetime_seconds") {
		$realType = "datetime_seconds_ymd";
	} elseif (substr($valType, 0, 8) == "datetime") {
		$realType = "datetime_ymd";
	} elseif (substr($valType, 0, 4) == "date") {
		$realType = "date_ymd";
	} else {
		$realType = $valType;
	}
	return $realType;
}

// Render hidden divs used by showProgress() javascript function
function renderShowProgressDivs()
{
	print RCView::div(array('id'=>'working'),
			RCIcon::ProgressSpinner("me-3") .
			RCView::tt("design_08")
		) . 
		RCView::div(array('id'=>'fade'), '');
}

// Convert an array to a REDCap enum format with keys as coded value and value as lables
function arrayToEnum($array, $delimiter="\\n")
{
	$enum = array();
	foreach ($array as $key=>$val)
	{
		$enum[] = trim($key) . ", " . trim($val);
	}
	return implode(" $delimiter ", $enum);
}

// Determine web server domain name (take into account if a proxy exists)
function getServerName()
{
	// if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) return $_SERVER['HTTP_HOST']; // Do not trust HOST header for security reasons
	if (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) return $_SERVER['SERVER_NAME'];
	if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) return $_SERVER['HTTP_X_FORWARDED_HOST'];
	if (isset($_SERVER['HTTP_X_FORWARDED_SERVER']) && !empty($_SERVER['HTTP_X_FORWARDED_SERVER'])) return $_SERVER['HTTP_X_FORWARDED_SERVER'];
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
	return false;
}

// Determing IP address of server (account for proxies also)
function getServerIP()
{
	if (isset($_SERVER['SERVER_ADDR']) && !empty($_SERVER['SERVER_ADDR'])) return $_SERVER['SERVER_ADDR'];
	if (isset($_SERVER['LOCAL_ADDR']) && !empty($_SERVER['LOCAL_ADDR'])) return $_SERVER['LOCAL_ADDR'];
	if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) return $_SERVER['REMOTE_ADDR'];
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
}

//Returns file extension of an inputted file name
function getFileExt($doc_name,$outputDotIfExists=false)
{
	$dotpos = strrpos($doc_name, ".");
	if ($dotpos === false) return "";
	return substr($doc_name, $dotpos + ($outputDotIfExists ? 0 : 1), strlen($doc_name));
}

// Replace any MS Word Style characters with regular characters
// NOTE: String parameter is passed by reference
function replaceMSchars(&$str)
{
	// First, we replace any UTF-8 characters that exist.
	$search = array(            // www.fileformat.info/info/unicode/<NUM>/ <NUM> = 2018
                "\xC2\xAB",     // ? (U+00AB) in UTF-8
                "\xC2\xBB",     // ? (U+00BB) in UTF-8
                "\xE2\x80\x98", // ? (U+2018) in UTF-8
                "\xE2\x80\x99", // ? (U+2019) in UTF-8
                "\xE2\x80\x9A", // ? (U+201A) in UTF-8
                "\xE2\x80\x9B", // ? (U+201B) in UTF-8
                "\xE2\x80\x9C", // ? (U+201C) in UTF-8
                "\xE2\x80\x9D", // ? (U+201D) in UTF-8
                "\xE2\x80\x9E", // ? (U+201E) in UTF-8
                "\xE2\x80\x9F", // ? (U+201F) in UTF-8
                "\xE2\x80\xB9", // ? (U+2039) in UTF-8
                "\xE2\x80\xBA", // ? (U+203A) in UTF-8
                "\xE2\x80\x93", // ? (U+2013) in UTF-8
                "\xE2\x80\x94", // ? (U+2014) in UTF-8
                "\xE2\x80\xA6"  // ? (U+2026) in UTF-8
    );
    $replacements = array(
                "<<",
                ">>",
                "'",
                "'",
                "'",
                "'",
                '"',
                '"',
                '"',
                '"',
                "<",
                ">",
                "-",
                "-",
                "..."
    );
	$str = str_replace($search, $replacements, $str??"");
}

// Sanitize query parameters of an ARRAY and return as a comma-delimited string surrounded by single quotes
function prep_implode($array=array(), $replaceMSchars=true, $useCheckNull=false)
{
    if (!is_array($array)) $array = [];
	// Loop through array
	foreach ($array as &$str) {
		// Replace any MS Word Style characters with regular characters
		if ($replaceMSchars) replaceMSchars($str);
		// Perform escaping and return
		if ($useCheckNull) {
			$str = checkNull($str, $replaceMSchars);
		} else {
			$str = "'" . db_escape($str, $replaceMSchars) . "'";
		}
	}
	// Return as a comma-delimited string surrounded by single quotes
	return implode(", ", $array);
}

// Sanitize query parameters of a STRING
function db_escape($str, $replaceMSchars=true)
{
	// Replace any MS Word Style characters with regular characters
	if ($replaceMSchars) replaceMSchars($str);
	// Perform escaping and return
	return db_real_escape_string($str);
}

// Determine if a transaction is still active (with autocommit=0)
function db_transaction_active()
{
	$autocommmit = db_result(db_query("select @@AUTOCOMMIT"), 0);
	// If autocommit=0, then we're inside a transaction
	return ($autocommmit == '0');
}


// Render Javascript variables needed on all pages for various JS functions
function renderJsVars()
{
	global $redcap_version, $status, $isMobileDevice, $user_rights, $institution, $sendit_enabled, $super_user, $surveys_enabled, $mycap_enabled,
		   $table_pk, $table_pk_label, $longitudinal, $email_domain_allowlist, $auto_inc_set, $data_resolution_enabled, $missingDataCodes,
           $file_upload_vault_enabled, $file_upload_versioning_enabled, $secondary_pk, $csv_delimiter, $default_csv_delimiter, $project_dashboard_allow_public,
           $ai_services_enabled_global, $ai_improvetext_service_enabled;
	// Set JS value for disabling DataTables
	$datatables_disable = json_encode((defined("PROJECT_ID") && isset($GLOBALS['ui_state'][PROJECT_ID]['datatables_disable'])) ? $GLOBALS['ui_state'][PROJECT_ID]['datatables_disable'] : array());
    // Remove the query string if somehow in page_full
    $page = PAGE;
    if (strpos(PAGE, '?')) {
        list ($page, $nothing) = explode("?", PAGE, 2);
    }
	// Global: Language strings added to the global lang object
	addLangToJS(array(
        "system_config_873","system_config_874",
		"docs_1136",
		"alerts_24", // Alert
		"calendar_popup_01", // Close
		"calendar_widget_choosedatehint",
		"calendar_widget_choosetime",
		"calendar_widget_done",
		"calendar_widget_hour",
		"calendar_widget_min",
		"calendar_widget_sec",
		"calendar_widget_month_jan",
		"calendar_widget_month_feb",
		"calendar_widget_month_mar",
		"calendar_widget_month_apr",
		"calendar_widget_month_may",
		"calendar_widget_month_jun",
		"calendar_widget_month_jul",
		"calendar_widget_month_aug",
		"calendar_widget_month_sep",
		"calendar_widget_month_oct",
		"calendar_widget_month_nov",
		"calendar_widget_month_dec",
		"calendar_widget_month_day_long_sun",
		"calendar_widget_month_day_long_mon",
		"calendar_widget_month_day_long_tues",
		"calendar_widget_month_day_long_wed",
		"calendar_widget_month_day_long_thurs",
		"calendar_widget_month_day_long_fri",
		"calendar_widget_month_day_long_sat",
		"calendar_widget_month_day_short_sun",
		"calendar_widget_month_day_short_mon",
		"calendar_widget_month_day_short_tues",
		"calendar_widget_month_day_short_wed",
		"calendar_widget_month_day_short_thurs",
		"calendar_widget_month_day_short_fri",
		"calendar_widget_month_day_short_sat",
		"calendar_widget_next",
		"calendar_widget_prev",
		"dash_72",
		"dash_73",
		"dashboard_32",
		"design_401",
		"design_718",
		"design_839",
		"edit_project_186",
		"form_renderer_29",
		"global_13",
		"global_53",
		"global_146",
		"global_147",
		"global_148",
		"global_149",
		"global_150",
		"global_151",
		"global_152",
		"global_167",
		"global_168",
		"global_169",
		"global_170",
		"global_171",
		"global_172",
		"global_173",
		"design_482",
		"design_962",
		"global_132",
		"global_208",
		"global_266",
		"global_267",
		"global_272",
		'global_273',
		'global_274',
		"global_275",
		"global_309",
		"global_310",
		"global_311",
		"global_312",
		"messaging_118",
		"messaging_119",
		"messaging_177",
		'docs_83',
		'docs_81',
		'sendit_03',
		'sendit_04',
		'sendit_05',
		'period',
		"control_center_4803",
		'docs_81',
		'docs_82',
		'data_entry_63',
		'design_08',
		'survey_1609',
		'survey_1288',
		'survey_1610'
    ));

    $aiDetailsSet = AI::isServiceDetailsSet();

    if ($ai_services_enabled_global && $aiDetailsSet && $ai_improvetext_service_enabled) {
        addLangToJS(array(
           'openai_001', 'openai_002', 'openai_003', 'openai_004', 'openai_005', 'openai_006', 'openai_007', 'openai_008', 'openai_009', 'openai_010',
           'openai_011', 'openai_012', 'openai_013', 'openai_014', 'openai_015', 'openai_016', 'openai_017', 'openai_018', 'openai_019', 'openai_020',
           'openai_021', 'openai_022', 'openai_023', 'openai_024', 'openai_025', 'openai_026', 'openai_027', 'openai_028', 'openai_029', 'openai_030',
           'openai_031', 'openai_032', 'openai_033', 'openai_034', 'openai_035', 'openai_036', 'openai_037', 'openai_038', 'openai_039', 'openai_040',
           'openai_041', 'openai_042', 'openai_043', 'openai_044', 'openai_045', 'openai_046', 'openai_047', 'openai_048', 'openai_049', 'openai_050',
           'survey_281', 'global_46', 'asi_017', 'design_1259', 'mycap_mobile_app_764', 'global_53', 'docs_1102', 'openai_051', 'openai_052', 'openai_053',
           'openai_053', 'openai_054', 'openai_055', 'openai_056', 'openai_067', 'openai_068', 'openai_069'
        ));
    }
    // Output the Logic Editor code example dialog
    print RCView::div(['id'=>'rc-ace-editor-example', 'style'=>'display:none;', 'class'=>'simpleDialog'],
            RCView::div(['class'=>'mb-2'], RCView::tt('global_271')).
            "<pre># ".RCView::tt('global_268',null)."\nif ([field1] = '1' and [field2] > 7,\n\n\t// ".RCView::tt('global_269',null).
            "\n\t[score] * [factor],\n\n\t// ".RCView::tt('global_270',null)."\n\t0\n)</pre>"
    );
    // Project-level: Language strings added to the global lang object
    if (isset($_GET['pid']))
    {
        addLangToJS(array(
            "design_1051",
            "design_1052"
        ));
    }
    // Get cookie params
    $cookie_params = session_get_cookie_params();
	// Output JavaScript
	?>
	<script type="text/javascript">
	// Admin privileges
	var super_user = <?php echo (defined("SUPER_USER") && SUPER_USER == '1' ? '1' : '0') ?>;
	var super_user_not_impersonator = <?php echo (UserRights::isSuperUserNotImpersonator() ? '1' : '0') ?>;
	var admin_rights = '<?php echo (defined("ADMIN_RIGHTS") ? ADMIN_RIGHTS : '0') ?>';
	var account_manager = '<?php echo (defined("ACCOUNT_MANAGER") ? ACCOUNT_MANAGER : '0') ?>';
	var access_system_config = '<?php echo (defined("ACCESS_SYSTEM_CONFIG") ? ACCESS_SYSTEM_CONFIG : '0') ?>';
	var access_system_upgrade = '<?php echo (defined("ACCESS_SYSTEM_UPGRADE") ? ACCESS_SYSTEM_UPGRADE : '0') ?>';
	var access_external_module_install = '<?php echo (defined("ACCESS_EXTERNAL_MODULE_INSTALL") ? ACCESS_EXTERNAL_MODULE_INSTALL : '0') ?>';
	var access_admin_dashboards = '<?php echo (defined("ACCESS_ADMIN_DASHBOARDS") ? ACCESS_ADMIN_DASHBOARDS : '0') ?>';
	<?php if (isset($_GET['pid']) && defined("PROJECT_ID")) { ?>
	// Project values
	var missing_data_codes = <?php echo "[\"" . implode("\",\"", array_keys($missingDataCodes??[])) . "\"]" ?>;
	var missing_data_codes_check = <?php echo (empty($missingDataCodes) ? 'false' : 'true') ?>;
	var app_name = '<?php echo (defined("APP_NAME") ? APP_NAME : '') ?>';
	var pid = <?php echo PROJECT_ID ?>;
	var status = <?php echo $status ?>;
	var draft_mode = <?php echo ($status > 0 && $GLOBALS['draft_mode'] == '1' ? '1' : '0') ?>;
	var data_locked = <?php echo ($status == '2' && $GLOBALS['data_locked'] == '1' ? '1' : '0') ?>;
	var file_repository_enabled = <?php echo ($GLOBALS['file_repository_enabled'] == '1' ? '1' : '0') ?>;
	var table_pk  = '<?php echo $table_pk ?>'; var table_pk_label  = '<?php echo js_escape($table_pk_label) ?>';
	var longitudinal = <?php echo $longitudinal ? 1 : 0 ?>;
	var auto_inc_set = <?php echo $auto_inc_set ? 1 : 0 ?>;
	var project_dashboard_allow_public = <?php echo (isinteger($project_dashboard_allow_public) ? $project_dashboard_allow_public : 0) ?>;
	var file_upload_vault_enabled = <?php echo $file_upload_vault_enabled ? 1 : 0 ?>;
	var file_upload_versioning_enabled = <?php echo $file_upload_versioning_enabled ? 1 : 0 ?>;
	var sendgrid_enabled = <?php echo (isset($GLOBALS['sendgrid_enabled']) && $GLOBALS['sendgrid_enabled'] ? 1 : 0) ?>;
	var data_resolution_enabled = <?php echo is_numeric($data_resolution_enabled) ? $data_resolution_enabled : 0 ?>;
	var lock_record = <?php echo (isset($user_rights) && is_numeric($user_rights['lock_record']) ? $user_rights['lock_record'] : '0') ?>;
	var shared_lib_browse_url = '<?php echo SHARED_LIB_BROWSE_URL . "?callback=" . urlencode(SHARED_LIB_CALLBACK_URL . "?pid=" . (defined("PROJECT_ID") ? PROJECT_ID : "")) . "&institution=" . urlencode($institution) . "&user=" . (defined("USERID") ? md5($institution . USERID) : "") ?>';
	var redcap_colorblind = <?php echo (isset($_COOKIE['redcap_colorblind']) && $_COOKIE['redcap_colorblind'] == '1' ? '1' : '0'); ?>;
	$(function(){ $('.redcap-chart-colorblind-toggle').removeClass('invisible').find('u').html(<?=(isset($_COOKIE['redcap_colorblind']) && $_COOKIE['redcap_colorblind'] == '1') ? 'window.lang.dash_73' : 'window.lang.dash_72' ?>).click(function(){ toggleChartColorBlind(); } ); });
    var database_query_tool_enabled = <?php echo (isset($GLOBALS['database_query_tool_enabled']) && $GLOBALS['database_query_tool_enabled'] ? 1 : 0) ?>;
    var dde_user = '<?=($GLOBALS['double_data_entry'] && isset($user_rights['double_data'])) ? $user_rights['double_data'] : ''?>';
	<?php } ?>
	// System values
	var redcap_version = '<?php echo $redcap_version ?>';
	var server_name = '<?php echo (defined("SERVER_NAME") ? SERVER_NAME : "") ?>';
	var app_path_webroot = '<?php echo (defined("APP_PATH_WEBROOT") ? APP_PATH_WEBROOT : "") ?>';
    var app_path_webroot_parent = '<?php echo (defined("APP_PATH_WEBROOT_PARENT") ? APP_PATH_WEBROOT_PARENT : "") ?>';
	var app_path_webroot_full = '<?php echo (defined("APP_PATH_WEBROOT_FULL") ? APP_PATH_WEBROOT_FULL : "") ?>';
	var app_path_survey_full = '<?php echo (defined("APP_PATH_SURVEY_FULL") ? APP_PATH_SURVEY_FULL : "") ?>';
	var app_path_images = '<?php echo APP_PATH_IMAGES ?>';
	var page = '<?php echo $page ?>';
    var secondary_pk = '<?php echo $secondary_pk ?>';
	var sendit_enabled = <?php echo (isset($sendit_enabled) && is_numeric($sendit_enabled) ? $sendit_enabled : '0') ?>;
	var surveys_enabled = <?php echo (isset($surveys_enabled) && is_numeric($surveys_enabled) ? $surveys_enabled : '0') ?>;
	var mycap_enabled = <?php echo (isset($mycap_enabled) && is_numeric($mycap_enabled) ? $mycap_enabled : '0') ?>;
	var reports_allow_public = '<?php echo (isset($GLOBALS['reports_allow_public']) && isinteger($GLOBALS['reports_allow_public']) ? $GLOBALS['reports_allow_public'] : '0') ?>';
	var rich_text_image_embed_enabled = <?php echo (isset($GLOBALS['rich_text_image_embed_enabled']) && $GLOBALS['rich_text_image_embed_enabled'] == '1' ? '1' : '0') ?>;
	var rich_text_attachment_embed_enabled = <?php echo (isset($GLOBALS['rich_text_attachment_embed_enabled']) && $GLOBALS['rich_text_attachment_embed_enabled'] == '1' ? '1' : '0') ?>;
	var now = '<?php echo NOW ?>'; var now_mdy = '<?php echo date("m-d-Y H:i:s") ?>'; var now_dmy = '<?php echo date("d-m-Y H:i:s") ?>';
	var today = '<?php echo date("Y-m-d") ?>'; var today_mdy = '<?php echo date("m-d-Y") ?>'; var today_dmy = '<?php echo date("d-m-Y") ?>';
	var email_domain_allowlist = new Array(<?php echo ($email_domain_allowlist == '' ? '' : prep_implode(explode("\n", strtolower(str_replace("\r", "", $email_domain_allowlist))))) ?>);
	var user_date_format_jquery = '<?php echo DateTimeRC::get_user_format_jquery() ?>';
	var user_date_format_validation = '<?php echo strtolower(DateTimeRC::get_user_format_base()) ?>';
	var user_date_format_delimiter = '<?php echo DateTimeRC::get_user_format_delimiter() ?>';
	var csv_delimiter = '<?php echo str_replace("TAB", "\t", (isset($csv_delimiter) ? $csv_delimiter : $default_csv_delimiter)) ?>';
	var ALLOWED_TAGS = '<?php echo ALLOWED_TAGS ?>';
	var AUTOMATE_ALL = '<?php echo (defined("AUTOMATE_ALL") ? '1' : '0') ?>';
    var restricted_upload_file_types = <?php echo json_encode_rc(Files::getRestrictedUploadFileTypes()) ?>;
	var cookie_samesite = '<?=$cookie_params['samesite']?>';
	var cookie_secure = <?=($cookie_params['secure'] !== true ? 'false' : 'true')?>;
	<?php if ($_SERVER['SERVER_NAME'] == 'redcap.mc.vanderbilt.edu') { ?>document.domain = 'mc.vanderbilt.edu'; <?php } ?>
	var datatables_disable = <?php print $datatables_disable ?>;
	var fakeObjectTag = '<?php print Piping::getFakeReplacementTagObject() ?>';
    var maxUploadSizeAttachment = <?=maxUploadSizeAttachment()*1024*1024?>; // bytes
    var canConvertPdfToImages = <?=PDF::canConvertPdfToImages() ? '1' : '0'?>;
    var openAIImproveTextServiceEnabled = <?php echo ($ai_services_enabled_global && $aiDetailsSet && $ai_improvetext_service_enabled) ? 1 : 0; ?>;
	function setDatePickerDefaults() {
		$.datepicker.setDefaults({
			timeText: window.lang.global_13,
			hourText: window.lang.calendar_widget_hour,
			minuteText: window.lang.calendar_widget_min,
			closeText: window.lang.calendar_widget_done,
			prevText: window.lang.calendar_widget_prev,
			nextText: window.lang.calendar_widget_next,
			currentText: window.lang.dashboard_32,
			monthNamesShort:[
				window.lang.calendar_widget_month_jan,
				window.lang.calendar_widget_month_feb,
				window.lang.calendar_widget_month_mar,
				window.lang.calendar_widget_month_apr,
				window.lang.calendar_widget_month_may,
				window.lang.calendar_widget_month_jun,
				window.lang.calendar_widget_month_jul,
				window.lang.calendar_widget_month_aug,
				window.lang.calendar_widget_month_sep,
				window.lang.calendar_widget_month_oct,
				window.lang.calendar_widget_month_nov,
				window.lang.calendar_widget_month_dec,
			],
			dayNames:[
				window.lang.calendar_widget_month_day_long_sun,
				window.lang.calendar_widget_month_day_long_mon,
				window.lang.calendar_widget_month_day_long_tues,
				window.lang.calendar_widget_month_day_long_wed,
				window.lang.calendar_widget_month_day_long_thurs,
				window.lang.calendar_widget_month_day_long_fri,
				window.lang.calendar_widget_month_day_long_sat,
			],
			dayNamesMin:[
				window.lang.calendar_widget_month_day_short_sun,
				window.lang.calendar_widget_month_day_short_mon,
				window.lang.calendar_widget_month_day_short_tues,
				window.lang.calendar_widget_month_day_short_wed,
				window.lang.calendar_widget_month_day_short_thurs,
				window.lang.calendar_widget_month_day_short_fri,
				window.lang.calendar_widget_month_day_short_sat,
			],
			isRTL: REDCap.MultiLanguage && typeof REDCap.MultiLanguage.isRTL == 'function' ? REDCap.MultiLanguage.isRTL() : false
		});
	}
	$(function(){
		setDatePickerDefaults();
	});
	</script>
	<?php
	// Output all the JavaScript variables needed for Messenger
	// MLM - This needs to be converted to use window.lang.xy style of string handling!
	Messenger::outputJSvars();
}

// Redirects to URL provided using PHP, and if
function redirect($url, $forceJS = false)
{
	// If contents already output, use javascript to redirect instead
	if (headers_sent() || $forceJS)
	{
		exit("<script type=\"text/javascript\">window.location.href=\"$url\";</script>");
	}
	// Redirect using PHP
	else
	{
		header("Location: $url");
		exit;
	}
}

// Pre-fill metadata by getting template fields from prefill_metadata.php
function createMetadata($new_project_id)
{
	$metadata = array();
	$form_names = array();
	$metadata['Form 1'] = array(
			array("record_id", "text", "Record ID", "", "", "")
	);
	//print_array($metadata);
	$i = 1;
	// Loop through all metadata fields from prefill_metadata.php and add as new project
	foreach ($metadata as $this_form=>$v2)
	{
		$this_form_menu1 = camelCase($this_form, true);
		$this_form = $form_names[] = preg_replace("/[^a-z0-9_]/", "", str_replace(" ", "_", strtolower($this_form)));
		foreach ($v2 as $j=>$v)
		{
			$this_form_menu = ($j == 0) ? $this_form_menu1 : "";
			$check_type = ($v[1] == "text") ? "soft_typed" : "";
			// Insert fields into metadata table
			$sql = "insert into redcap_metadata
					(project_id, field_name, form_name, form_menu_description, field_order, element_type, element_label,
					 element_enum, element_validation_type, element_validation_checktype, element_preceding_header) values
					($new_project_id, ".checkNull($v[0]).", ".checkNull($this_form).", ".checkNull($this_form_menu).", ".$i++.", ".checkNull($v[1]).",
					".checkNull($v[2]).", ".checkNull(str_replace("|","\\n",$v[3])).", ".checkNull($v[4]).", ".checkNull($check_type).", ".checkNull($v[5]).")";
			db_query($sql);
		}
		// Form Status field
		$sql = "insert into redcap_metadata (project_id, field_name, form_name, field_order, element_type,
				element_label, element_enum, element_preceding_header) values ($new_project_id, '{$this_form}_complete', ".checkNull($this_form).",
				".$i++.", 'select', 'Complete?', '0, Incomplete \\\\n 1, Unverified \\\\n 2, Complete', 'Form Status')";
		db_query($sql);
	}
	// Return array of form_names to use for user_rights
	return $form_names;
}

// Find difference between two times
function timeDiff($firstTime, $lastTime, $decimalRound=null, $returnFormat='s')
{
	// convert to unix timestamps
	$firstTime = strtotime($firstTime);
	$lastTime = strtotime($lastTime);
	// perform subtraction to get the difference (in seconds) between times
	$timeDiff = $lastTime - $firstTime;
	// return the difference
	switch ($returnFormat)
	{
		case 'm':
			$timeDiff = $timeDiff/60;
			break;
		case 'h':
			$timeDiff = $timeDiff/3600;
			break;
		case 'd':
			$timeDiff = $timeDiff/3600/24;
			break;
		case 'w':
			$timeDiff = $timeDiff/3600/24/7;
			break;
		case 'y':
			$timeDiff = $timeDiff/3600/24/365;
			break;
	}
	if (is_numeric($decimalRound))
	{
		$timeDiff = round($timeDiff, $decimalRound);
	}
	return $timeDiff;
}

// Find difference between two times AND return units text (e.g. "X days, Y hours")
function timeDiffUnits($firstTime, $lastTime)
{
    $timeDiffSeconds = strtotime($lastTime)-strtotime($firstTime);
    if ($timeDiffSeconds > 604800) {
        $timeDiffUnits = '%a '.RCView::tt('survey_426').', %h '.RCView::tt('survey_427');
    } elseif ($timeDiffSeconds > 86400) {
        $timeDiffUnits = '%a '.RCView::tt('survey_426').', %h '.RCView::tt('survey_427').', %i '.RCView::tt('survey_428');
    } elseif ($timeDiffSeconds > 3600) {
        $timeDiffUnits = '%h '.RCView::tt('survey_427').', %i '.RCView::tt('survey_428');
    } elseif ($timeDiffSeconds > 60) {
        $timeDiffUnits = '%i '.RCView::tt('survey_428').', %s '.RCView::tt('control_center_4469');
    } else {
        $timeDiffUnits = '%s '.RCView::tt('control_center_4469');
    }
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$timeDiffSeconds");
    return $dtF->diff($dtT)->format($timeDiffUnits);
}

// Creates random alphanumeric string
function generateRandomHash($length=6, $addNonAlphaChars=false, $onlyHandEnterableChars=false, $alphaCharsOnly=false) {
	// Use character list that is human enterable by hand or for regular hashes (i.e. for URLs)
	if ($onlyHandEnterableChars) {
		$characters = '34789ACDEFHJKLMNPRTWXY'; // Potential characters to use (omitting 150QOIS2Z6GVU)
	} else {
		$characters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ23456789'; // Potential characters to use
		if ($addNonAlphaChars) $characters .= '~.$#@!%^&*-';
	}
	// If returning only letter, then remove all non-alphas from $characters
	if ($alphaCharsOnly) {
		$characters = preg_replace("/[^a-zA-Z]/", "", $characters);
	}
	// Build string
	$strlen_characters = strlen($characters);
    $string = '';
    for ($p = 0; $p < $length; $p++) {
        $string .= $characters[random_int(0, $strlen_characters-1)];
    }
	// If hash matches a number in Scientific Notation, then fetch another one
	// (because this could cause issues if opened in certain software - e.g. Excel)
	if (preg_match('/^\d+E\d/', $string)) {
		return generateRandomHash($length, $addNonAlphaChars, $onlyHandEnterableChars);
	} else {
		return $string;
	}
}

// Copy an edoc file on the web server. If fails, fall back to stream_copy().
function file_copy($src, $dest)
{
	if (!copy($src, $dest))
	{
		return stream_copy($src, $dest);
	}
	return true;
}

// Alternative to using copy() function, which can be disabled on some servers.
function stream_copy($src, $dest)
{
	 // Allocate more memory since stream_copy_to_stream() is a memory hog.
	$fsrc  = fopen($src,'rb');
	$fdest = fopen($dest,'w+');
    if (!$fsrc || !$fdest) return false;
	$len = stream_copy_to_stream($fsrc, $fdest);
	fclose($fsrc);
	fclose($fdest);
	// If entire file was copied (bytes are the same), return as true.
	return ($len == filesize($src));
}

// Copy an edoc_file by providing edoc_id. Returns edoc_id of new file, else False if failed. If desired, set new destination project_id.
function copyFile($edoc_id, $dest_project_id=PROJECT_ID)
{
	global $edoc_storage_option, $rc_connection;
	// Must be numeric
	if (!is_numeric($edoc_id)) return false;
	// Query the file in the edocs table
	$sql = "select * from redcap_edocs_metadata where doc_id = $edoc_id";
	$q = db_query($sql);
	if (db_num_rows($q) < 1) return false;
	// Get file info
	$edoc_info = db_fetch_assoc($q);
	// Set src and dest filenames
	$src_filename  = $edoc_info['stored_name'];
	$dest_filename = date('YmdHis') . "_pid" . $dest_project_id . "_" . generateRandomHash(6) . getFileExt($edoc_info['doc_name'], true);
	// Default value
	$copy_successful = false;
	// Copy file within defined Edocs folder
	if ($edoc_storage_option == '0' || $edoc_storage_option == '3')
	{
		$copy_successful = file_copy(EDOC_PATH . \Files::getLocalStorageSubfolder($edoc_info['project_id'], true) . $src_filename, EDOC_PATH . \Files::getLocalStorageSubfolder($dest_project_id, true) . $dest_filename);
	}
	// S3
	elseif ($edoc_storage_option == '2')
	{
		try {
			$s3 = Files::s3client();
			$s3->copyObject(array(
				'Bucket'     => $GLOBALS['amazon_s3_bucket'],
				'Key'        => $dest_filename,
				'CopySource' => "{$GLOBALS['amazon_s3_bucket']}/{$src_filename}",
			));
			$copy_successful = true;
		} catch (Aws\S3\Exception\S3Exception $e) {
		}
	}
	// Azure
	elseif ($edoc_storage_option == '4')
	{
		$blobClient = new AzureBlob();
		try {
			$blobClient->copyBlob($dest_filename, $src_filename);
			$copy_successful = true;
		} catch (Exception $e) {
		}
	}// Google
	elseif ($edoc_storage_option == '5')
	{
        $googleClient = Files::googleCloudStorageClient();
        $bucket = $googleClient->bucket($GLOBALS['google_cloud_storage_api_bucket_name']);
        $googleClient->registerStreamWrapper();
		try {
		    $contents = file_get_contents('gs://'.$GLOBALS['google_cloud_storage_api_bucket_name'].'/' . $src_filename);
            // if pid sub-folder is enabled then upload the file under pid folder
            if($GLOBALS['google_cloud_storage_api_use_project_subfolder']){
                $dest_filename = PROJECT_ID . '/' . $dest_filename;
            }
            $result = $bucket->upload($contents, array('name' => $dest_filename));
			$copy_successful = true;
		} catch (Exception $e) { }
	}
	// Use WebDAV copy methods
	else
	{
		if (!include APP_PATH_WEBTOOLS . 'webdav/webdav_connection.php') exit("ERROR: Could not read the file \"".APP_PATH_WEBTOOLS."webdav/webdav_connection.php\"");
		$wdc = new WebdavClient();
		$wdc->set_server($webdav_hostname);
		$wdc->set_port($webdav_port); $wdc->set_ssl($webdav_ssl);
		$wdc->set_user($webdav_username);
		$wdc->set_pass($webdav_password);
		$wdc->set_protocol(1); // use HTTP/1.1
		$wdc->set_debug(false); // enable debugging?
		if (!$wdc->open()) {
			sleep(1);
			return false;
		}
		if (substr($webdav_path,-1) != '/') {
			$webdav_path .= '/';
		}
		// Download source file
		if ($wdc->get($webdav_path . $src_filename, $contents) == '200')
		{
			// Copy to destination file
			$copy_successful = ($wdc->put($webdav_path . $dest_filename, $contents) == '201');
		}
		$wdc->close();
	}
	// If copied successfully, then add new row in edocs_metadata table
	if ($copy_successful)
	{
		//Copy this row in the rs_edocs table and get new doc_id number
		$sql = "insert into redcap_edocs_metadata (stored_name, mime_type, doc_name, doc_size, file_extension, project_id, stored_date)
				select '$dest_filename', mime_type, doc_name, doc_size, file_extension, '$dest_project_id', '".NOW."' from redcap_edocs_metadata
				where doc_id = $edoc_id";
		if (db_query($sql))
		{
			return db_insert_id($rc_connection);
		}
	}
	return false;
}

// Make an HTTP GET request
function http_get($url="", $timeout=null, $basic_auth_user_pass="", $headers=array(), $user_agent=null)
{
	// Try using cURL first, if installed
	if (function_exists('curl_init'))
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_VERBOSE, 0);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPGET, true);
		if (!sameHostUrl($url)) {
			curl_setopt($curl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
			curl_setopt($curl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
		}
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
		if (is_numeric($timeout)) {
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
		}
		// If using basic authentication = base64_encode(username:password)
		if ($basic_auth_user_pass != "") {
			curl_setopt($curl, CURLOPT_USERPWD, $basic_auth_user_pass);
			// curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Basic ".$basic_auth_user_pass));
		}
		// If passing headers manually, then add then
		if (!empty($headers) && is_array($headers)) {
			//curl_setopt($curl, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$authorizationBearerToken));
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		}
		// If passing user agent
		if ($user_agent != null) {
			curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
		}
		// Execute it
		$response = curl_exec($curl);
		$info = curl_getinfo($curl);
		curl_close($curl);
		// If returns certain HTTP 400 or 500 errors, return false
		if (isset($info['http_code']) && ($info['http_code'] == 404 || $info['http_code'] == 407 || $info['http_code'] >= 500)) return false;
		if ($info['http_code'] != '0') return $response;
	}
	// Try using file_get_contents if allow_url_open is enabled .
	// If curl somehow returned http status=0, then try this method.
	if (ini_get('allow_url_fopen'))
	{
		// Set http array for file_get_contents
		$http_array = array('method'=>'GET');
		if (is_numeric($timeout)) {
			$http_array['timeout'] = $timeout; // Set timeout time in seconds
		}
		// If using basic authentication (username:password)
		if ($basic_auth_user_pass != "") {
			$http_array['header'] = "Authorization: Basic " . base64_encode($basic_auth_user_pass);
		}
		// If using a proxy
		if (!sameHostUrl($url) && PROXY_HOSTNAME != '') {
			$http_array['proxy'] = str_replace(array('http://', 'https://'), array('tcp://', 'tcp://'), PROXY_HOSTNAME);
			$http_array['request_fulluri'] = true;
			if (PROXY_USERNAME_PASSWORD != '') {
				$proxy_auth = "Proxy-Authorization: Basic " . base64_encode(PROXY_USERNAME_PASSWORD);
				if (isset($http_array['header'])) {
					$http_array['header'] .= PHP_EOL . $proxy_auth;
				} else {
					$http_array['header'] = $proxy_auth;
				}
			}
		}
		// Use file_get_contents
		$content = @file_get_contents($url, false, stream_context_create(array('http'=>$http_array)));
	}
	else
	{
		$content = false;
	}
	// Return the response
	return $content;
}

// Send HTTP Post request and receive/return content
function http_post($url="", $params=array(), $timeout=null, $content_type='application/x-www-form-urlencoded', $basic_auth_user_pass="", $headers=array())
{
	// If params are given as an array, then convert to query string format, else leave as is
	if ($content_type == 'application/json') {
		// Send as JSON data
		$param_string = (is_array($params)) ? json_encode($params) : $params;
	} elseif ($content_type == 'application/x-www-form-urlencoded') {
		// Send as Form encoded data
		$param_string = (is_array($params)) ? http_build_query($params, '', '&') : $params;
	} else {
		// Send params as is (e.g., Soap XML string)
		$param_string = $params;
	}

	// Check if cURL is installed first. If so, then use cURL instead of file_get_contents.
	if (function_exists('curl_init'))
	{
		// Use cURL
		$curlpost = curl_init();
		curl_setopt($curlpost, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curlpost, CURLOPT_VERBOSE, 0);
		curl_setopt($curlpost, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curlpost, CURLOPT_AUTOREFERER, true);
		curl_setopt($curlpost, CURLOPT_MAXREDIRS, 10);
		curl_setopt($curlpost, CURLOPT_URL, $url);
		curl_setopt($curlpost, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlpost, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curlpost, CURLOPT_POSTFIELDS, $param_string);
		if (!sameHostUrl($url)) {
			curl_setopt($curlpost, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
			curl_setopt($curlpost, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
		}
		curl_setopt($curlpost, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
		if (is_numeric($timeout)) {
			curl_setopt($curlpost, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
		}
		// If using basic authentication = base64_encode(username:password)
		if ($basic_auth_user_pass != "") {
			curl_setopt($curlpost, CURLOPT_USERPWD, $basic_auth_user_pass);
			// curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Authorization: Basic ".$basic_auth_user_pass));
		}
		// If not sending as x-www-form-urlencoded, then set special header
		if ($content_type != 'application/x-www-form-urlencoded') {
			curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Content-Type: $content_type", "Content-Length: " . strlen($param_string)));
		}
		// If passing headers manually, then add then
		if (!empty($headers) && is_array($headers)) {
			curl_setopt($curlpost, CURLOPT_HTTPHEADER, $headers);
		}
		// Make the call
		$response = curl_exec($curlpost);
		$info = curl_getinfo($curlpost);
		curl_close($curlpost);
		// If returns certain HTTP 400 or 500 errors, return false
		if (isset($info['http_code']) && ($info['http_code'] == 404 || $info['http_code'] == 407 || $info['http_code'] >= 500)) return false;
		if ($info['http_code'] != '0') return $response;
	}
	// Try using file_get_contents if allow_url_open is enabled .
	// If curl somehow returned http status=0, then try this method.
	if (ini_get('allow_url_fopen'))
	{
		// Set http array for file_get_contents
		$http_array = array('method'=>'POST',
							'header'=>"Content-type: $content_type",
							'content'=>$param_string
					  );
		if (is_numeric($timeout)) {
			$http_array['timeout'] = $timeout; // Set timeout time in seconds
		}
		// If using basic authentication (username:password)
		if ($basic_auth_user_pass != "") {
			$http_array['header'] .= PHP_EOL . "Authorization: Basic " . base64_encode($basic_auth_user_pass);
		}
		// If using a proxy
		if (!sameHostUrl($url) && PROXY_HOSTNAME != '') {
			$http_array['proxy'] = str_replace(array('http://', 'https://'), array('tcp://', 'tcp://'), PROXY_HOSTNAME);
			$http_array['request_fulluri'] = true;
			if (PROXY_USERNAME_PASSWORD != '') {
				$http_array['header'] .= PHP_EOL . "Proxy-Authorization: Basic " . base64_encode(PROXY_USERNAME_PASSWORD);
			}
		}

		// Use file_get_contents
		$content = @file_get_contents($url, false, stream_context_create(array('http'=>$http_array)));

		// Return the content
		if ($content !== false) {
			return $content;
		}
		// If no content, check the headers to see if it's hiding there (why? not sure, but it happens)
		else {
		   if (empty($http_response_header)) return false;
			// If header is a true header, then return false, else return the content found in the header
			return (substr($content, 0, 5) == 'HTTP/') ? false : $content;
		}
	}
	// Return false
	return false;
}

// Send HTTP PUT request and receive/return content
function http_put($url="", $params=array(), $timeout=null, $content_type='application/x-www-form-urlencoded', $basic_auth_user_pass="")
{
	// If params are given as an array, then convert to query string format, else leave as is
	if ($content_type == 'application/json') {
		// Send as JSON data
		$param_string = (is_array($params)) ? json_encode($params) : $params;
	} else {
		// Send as Form encoded data
		$param_string = (is_array($params)) ? http_build_query($params, '', '&') : $params;
	}
	// Use cURL
	$curlpost = curl_init();
	curl_setopt($curlpost, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curlpost, CURLOPT_VERBOSE, 0);
	curl_setopt($curlpost, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($curlpost, CURLOPT_AUTOREFERER, true);
	curl_setopt($curlpost, CURLOPT_MAXREDIRS, 10);
	curl_setopt($curlpost, CURLOPT_URL, $url);
	curl_setopt($curlpost, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curlpost, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($curlpost, CURLOPT_POSTFIELDS, $param_string);
	if (!sameHostUrl($url)) {
		curl_setopt($curlpost, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
		curl_setopt($curlpost, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
	}
	curl_setopt($curlpost, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
	if (is_numeric($timeout)) {
		curl_setopt($curlpost, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
	}
	// If using basic authentication = base64_encode(username:password)
	if ($basic_auth_user_pass != "") {
		curl_setopt($curlpost, CURLOPT_USERPWD, $basic_auth_user_pass);
		// curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Authorization: Basic ".$basic_auth_user_pass));
	}
	// If not sending as x-www-form-urlencoded, then set special header
	if ($content_type != 'application/x-www-form-urlencoded') {
		curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Content-Type: $content_type", "Content-Length: " . strlen($param_string)));
	}
	$response = curl_exec($curlpost);
	$info = curl_getinfo($curlpost);
	curl_close($curlpost);
	// If returns an HTTP 404 error, return false
	if (isset($info['http_code']) && $info['http_code'] == 404) return false;
	if ($info['http_code'] != '0') return $response;
}

// Validate if string is a proper URL. Return boolean.
function isURL($url)
{
	$pattern = "/^(http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/i";
	return preg_match($pattern, $url);
}

// Retrieve all field validation types from table. Return as array.
function getValTypes($valtype=null)
{
	// Add validation type array as a global variable. If is array (already populated), then return it, otherwise generate from table.
	global $redcap_valtypes;
	// Is it populated already?
	if (!isset($redcap_valtypes) || !is_array($redcap_valtypes)) {
		// Get from table
		$sql = "select * from redcap_validation_types where validation_name is not null
				and validation_name != '' order by validation_label";
		$q = db_query($sql);
		$redcap_valtypes = array();
		while ($row = db_fetch_assoc($q))
		{
			$redcap_valtypes[$row['validation_name']] = array(
				'validation_label'=>$row['validation_label'],
				'regex_js'=>$row['regex_js'],
				'regex_php'=>$row['regex_php'],
				'data_type'=>$row['data_type'],
				'visible'=>$row['visible']
			);
		}
	}
	if ($valtype !== null) {
		return isset($redcap_valtypes[$valtype]) ? $redcap_valtypes[$valtype] : null;
	} else {
		return $redcap_valtypes;
	}
}

// Makes sure that a date in format Y-M-D format has 2-digit month and day and has a 4-digit year
function clean_date_ymd($date)
{
	global $missingDataCodes;

	$date = trim($date);
	// Ensure has 2 dashes, and if not 10-digits long, then break apart and reassemble
	if (substr_count($date, "-") == 2 && strlen($date) < 10)
	{
		// Break into components
		list ($year, $month, $day) = explode('-', $date);
		// Make sure year is 4 digits
		if (strlen($year) == 2) {
			$year = ($year < (date('y')+10)) ? "20".$year : "19".$year;
		}
		// Reassemble
		$date = sprintf("%04d-%02d-%02d", $year, $month, $day);
	}
	return $date;
}

// Detect IE version
function vIE()
{
	$browser = new Browser();
	$browser_name = strtolower($browser->getBrowser());
	$browser_version = isIE11compat() ? '11.0' : $browser->getVersion();
	return ($browser_name == 'internet explorer' && is_numeric($browser_version)) ? floatval($browser_version) : -1;
}

// Detect IE11 Compatibility Mode. Return boolean.
function isIE11compat()
{
	global $isIE;
	return ($isIE && stripos($_SERVER['HTTP_USER_AGENT'], 'Trident/7.0') !== false
		&& stripos($_SERVER['HTTP_USER_AGENT'], 'Mozilla/4.0') !== false);
}

// Display a message to the user as a colored div with option to animate and set aesthetics
function displayMsg($msgText=null, $msgId="actionMsg", $msgAlign="center", $msgClass="green", $msgIcon="tick.png", $timeVisible=7, $msgAnimate=true)
{
	// Set message text
	if ($msgText == null) {
		$msgText = "<b>".RCView::tt("setup_08")."</b> ".RCView::tt("setup_09");
	}
	// Check that timeVisible is a positive number (in seconds)
	if (!is_numeric($timeVisible) || (is_numeric($timeVisible) && $timeVisible < 0)) {
		$timeVisible = 7;
	}
	// Display the message
	?>
	<div id="<?php echo $msgId ?>" class="<?php echo $msgClass ?>" style="<?php if ($msgAnimate) echo 'display:none;'; ?>max-width:800px;padding:15px 25px;margin:20px 0;text-align:<?php echo $msgAlign ?>;">
		<img src="<?php echo APP_PATH_IMAGES . $msgIcon ?>"> <?php echo $msgText ?>
	</div>
	<?php
	// Animate the message to display and hide (if set to do so)
	if ($msgAnimate)
	{
		?>
		<!-- Animate action message -->
		<script type="text/javascript">
		$(function(){
			setTimeout(function(){
				$("#<?php echo $msgId ?>").slideToggle('normal');
			},200);
			setTimeout(function(){
				$("#<?php echo $msgId ?>").slideToggle(1200);
			},<?php echo $timeVisible*1000 ?>);
		});
		</script>
		<?php
	}
}

// Add a special header to enforce BOM (byte order mark) if the string is UTF-8 encoded file
function addBOMtoUTF8($string)
{
	if (function_exists('mb_detect_encoding') && mb_detect_encoding($string) == "UTF-8")
	{
		$string = "\xEF\xBB\xBF" . $string;
	}
	return $string;
}

// Remove BOM (byte order mark) if the string is UTF-8 encoded file
function removeBOM($string)
{
	$bom = pack("CCC", 0xef,0xbb,0xbf);
	if (substr($string, 0, 3) == $bom)
	{
		$string = substr($string, 3);
	}
	return $string;
}

// Check if a directory is writable (tries to write a file to directory as a definite confirmation)
function isDirWritable($dir, $checkDeleteFileAction=false)
{
	global $edoc_storage_option;
	$is_writable = false; // default
	if ($edoc_storage_option == '3' || (is_dir($dir) && is_writeable($dir))) // Make exception if Google Cloud Storage (3)
	{
		// Try to write a file to that directory and then delete
		$test_file_path = $dir . DS . date('YmdHis') . '_test.txt';
		$fp = fopen($test_file_path, 'w');
		if ($fp !== false && fwrite($fp, 'test') !== false)
		{
			// Set as writable
			$is_writable = true;
			// Close connection and delete file
			fclose($fp);
            // Test if the test file can now be deleted
            $is_deletable = false; // default
            try {
                $is_deletable = unlink($test_file_path);
            } catch (\Throwable $e) { }
            if ($checkDeleteFileAction) {
                // If file can't be deleted, then return false from function
                $is_writable = $is_deletable;
            }
		}
	}
	return $is_writable;
}

// REDCAP INFO: Display table of REDCap variables, constants, and settings (similar to php_info()).
// This function is just a wrapper for PluginDocs::redcap_info().
function redcap_info($displayInsideOtherPage=false, $displayHeaderLogo=true)
{
	PluginDocs::redcapInfo($displayInsideOtherPage, $displayHeaderLogo);
}

## REDCAP PLUGIN FUNCTION: Limit the plugin to specific projects
function allowProjects()
{
	$args = func_get_args();
	return call_user_func_array('REDCap::allowProjects', $args);
}

## REDCAP PLUGIN FUNCTION: Limit the plugin to specific users
function allowUsers()
{
	$args = func_get_args();
	return call_user_func_array('REDCap::allowUsers', $args);
}

// Clean and escape text to be sent as JSON
function cleanJson($val)
{
	return js_escape2(str_replace('\\', '\\\\', $val));
}

// Take a CSV formatted $_FILE that was uploaded and convert to array
function csv_file_to_array($file) // e.g. $file = $_FILES['allocFile']
{
	// If filename is blank, reload the page
	if ($file['name'] == "") exit(RCView::tt("random_13"));

	// Get field extension
	$filetype = strtolower(substr($file['name'],strrpos($file['name'],".")+1,strlen($file['name'])));

	// If not CSV, print message, exit
	if ($filetype != "csv") exit(strip_tags(RCView::tt("design_961")));

	// If CSV file, save the uploaded file (copy file from temp to folder) and prefix a timestamp to prevent file conflicts
	$file['name'] = APP_PATH_TEMP . date('YmdHis') . (defined('PROJECT_ID') ? "_pid" . PROJECT_ID : '') . "_fileupload." . $filetype;
	$file['name'] = str_replace("\\", "\\\\", $file['name']);

	// If moving or copying the uploaded file fails, print error message and exit
	if (!move_uploaded_file($file['tmp_name'], $file['name'])) {
		if (!copy($file['tmp_name'], $file['name'])) exit(RCView::tt("random_13"));
	}

	// Make sure we remove the UTF8 BOM, if exists
	file_put_contents($file['name'], removeBOM(file_get_contents($file['name'])));

	// Now read the stored CSV file into an array
	$csv_array = array();
	if (($handle = fopen($file['name'], "rb")) !== false) {
		// Loop through each row
		while (($row = fgetcsv($handle, 0, ",", '"', '')) !== false) {
			$csv_array[] = $row;
		}
		fclose($handle);
	}

	// Remove the saved file, since it's no longer needed
	unlink($file['name']);

	// Return the array
	return $csv_array;
}

// Determine if being accessed by REDCap developer
function isDev($includeVanderbiltSuperUsers=false)
{
	return (
	           (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'redcap.test')
			|| (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'localhost' && defined('DEV'))
			|| ($includeVanderbiltSuperUsers && defined('USERID') && defined('SUPER_USER') && SUPER_USER && isVanderbilt())
    );
}

// Renders the home page header and footer with the specified content provided ehre
function renderPage($content)
{
	$objHtmlPage = new HtmlPage();
	$objHtmlPage->addStylesheet("home.css", 'screen,print');
	$objHtmlPage->PrintHeader();
	print RCView::div(array('class'=>'space','style'=>'margin:10px 0;'), '&nbsp;')
		. $content
		. RCView::div(array('class'=>'space','style'=>'margin:5px 0;'), '&nbsp;');
	$objHtmlPage->PrintFooter();
	exit;
}

// Validate if an email address
function isEmail($email)
{
    if ($email == null) return false;
	return (preg_match('/^(?!\\.)((?!.*\\.{2})[a-zA-Z0-9\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}\\.!#$%&\'*+\\-\\/=?^_`{|}~\\-\\d]+)(\\.[a-zA-Z0-9\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}\\.!#$%&\'*+\\-\\/=?^_`{|}~\\-\\d]+)*@(?!\\.)([a-zA-Z0-9\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}\\-\\.\\d]+)((\\.([a-zA-Z\\x{0080}-\\x{02AF}\\x{0300}-\\x{07FF}\\x{0900}-\\x{18AF}\\x{1900}-\\x{1A1F}\\x{1B00}-\\x{1B7F}\\x{1D00}-\\x{1FFF}\\x{20D0}-\\x{214F}\\x{2C00}-\\x{2DDF}\\x{2F00}-\\x{2FDF}\\x{2FF0}-\\x{2FFF}\\x{3040}-\\x{319F}\\x{31C0}-\\x{A4CF}\\x{A700}-\\x{A71F}\\x{A800}-\\x{A82F}\\x{A840}-\\x{A87F}\\x{AC00}-\\x{D7AF}\\x{F900}-\\x{FAFF}]){2,63})+)$/u', $email));
}

// Validate if a U.S. phone number WITHOUT the country code
function isPhoneUS($phone)
{
    if ($phone == null) return false;
	// Remove non-numerals
	$phone = preg_replace("/[^0-9]/", "", $phone);
	// Validate format and length
	if (preg_match("/^(?:\(?([2-9]0[1-9]|[2-9]1[02-9]|[2-9][2-9][0-9]|800|811)\)?)\s*(?:[.-]\s*)?([0-9]{3})\s*(?:[.-]\s*)?([0-9]{4})(?:\s*(?:#|x\.?|ext\.?|extension)\s*(\d+))?$/", $phone)) {
		// Array of ALL non-valid U.S. area codes
		$nonvalid_us_area_codes = array(221,222,230,232,233,237,238,241,243,244,245,247,249,255,257,258,259,261,263,265,266,271,273,275,277,280,282,285,286,287,288,290,291,292,293,294,295,296,297,298,299,300,322,328,333,335,338,342,344,348,349,354,355,356,357,358,359,362,366,367,368,370,371,372,373,374,375,376,377,378,379,381,382,383,384,387,388,389,390,391,392,393,394,395,396,397,398,399,400,420,421,422,426,427,429,433,439,444,446,449,451,452,453,454,455,457,459,460,461,462,465,466,467,468,471,476,477,482,483,485,486,487,488,489,490,491,492,493,494,495,496,497,498,499,521,522,523,524,525,526,527,528,529,532,533,535,536,537,538,542,543,544,545,546,547,549,550,552,553,554,556,558,560,565,566,568,569,576,577,578,581,583,584,588,589,590,591,592,593,594,595,596,597,598,599,621,622,625,632,633,634,635,637,638,642,643,644,648,652,653,654,655,663,665,666,668,673,674,675,676,677,683,685,687,688,690,691,692,693,694,695,696,697,698,699,722,723,729,733,735,736,738,739,741,742,744,745,746,748,749,750,751,752,753,755,756,759,761,766,768,776,777,783,788,789,790,791,792,793,794,795,796,797,798,799,821,823,824,827,834,836,837,841,842,846,851,852,853,871,874,875,879,883,884,885,886,887,889,890,891,892,893,894,895,896,897,899,921,922,923,924,926,932,933,942,944,946,950,953,955,958,960,961,962,963,964,965,966,967,968,969,974,977,981,982,987,988,990,991,992,993,994,995,996,997,998,999);
		// Make sure area code (first 3 numbers) is valid by referencing a list since some non-U.S. numbers can look just like a U.S. number
		return !in_array(substr($phone, 0, 3), $nonvalid_us_area_codes);
	}
	return false;
	## NOTE: Generated $nonvalid_us_area_codes above by obtaining list of all valid U.S. area codes at
	## http://www.bennetyee.org/ucsd-pages/area.html (see also https://en.wikipedia.org/wiki/List_of_North_American_Numbering_Plan_area_codes)
	// $all_area_codes = array(201,...); // Copy and paste valid area codes into this array, then loop to find the missing ones
	// for ($i = min($all_area_codes); $i <= max($all_area_codes); $i++) {
		// if (!in_array($i, $all_area_codes)) print "$i, ";
	// }
}

// Get current timezone name (e.g., America/Chicago). If cannot determine, return text "[could not be determined]".
function getTimeZone()
{
	$timezone = (function_exists("date_default_timezone_get")) ? date_default_timezone_get() : ini_get('date.timezone');
	if (empty($timezone)) $timezone = RCView::getLangStringByKey("survey_298");
	return $timezone;
}

function getJSpath($js_file, $fullPathProvided=false) {
	// Cache-busting
	$objHtmlPage = new HtmlPage();
    if (!$fullPathProvided) $js_file = APP_PATH_JS.$js_file;
	$js_file = $objHtmlPage->CacheBuster($js_file);
	return $js_file;
}

// Output the script tag for a given JavaScript file
function loadJS($js_file, $outputToPage=true, $fullPathProvided=false, $asyncAttribute=false)
{
    if (is_array($js_file)) {
        list($js_file, $type) = $js_file;
    }
	// Cache-busting
	$js_file = getJSpath($js_file, $fullPathProvided);
    // Add the async attribute?
    $async = $asyncAttribute ? " async" : "";
    // type attribute
    $type = isset($type) ? " type=\"{$type}\"" : " type=\"text/javascript\"";
	// Create script tag
	$output = "<script {$type} src=\"{$js_file}\"{$async}></script>\n";
	if ($outputToPage) {
		print $output;
	} else {
		return $output;
	}
}

// Output the link/style tag for a given CSS file
function loadCSS($css_file, $outputToPage=true)
{
	// Cache-busting
	$objHtmlPage = new HtmlPage();
	$css_file = $objHtmlPage->CacheBuster(APP_PATH_CSS.$css_file);
	// Create script tag
	$output = "<link rel=\"stylesheet\" type=\"text/css\" media=\"screen,print\" href=\"" . $css_file . "\">\n";
	if ($outputToPage) {
		print $output;
	} else {
		return $output;
	}
}

// GZIP encode a string
function gzip_encode_file($file_content, $file_name, $compression_level=9) {
	$gzipped = 0;
	if (function_exists('gzcompress')) {
		$file_content = gzcompress($file_content, $compression_level);
		$file_name .= ".gz";
		$gzipped = 1;
	}
	return array($file_content, $file_name, $gzipped);
}

// GZIP decode a string
function gzip_decode_file($file_content, $file_name=null) {
	if (function_exists('gzuncompress')) {
		$file_content = gzuncompress($file_content);
		if ($file_name != null && substr($file_name, -3) == ".gz") {
			$file_name = substr($file_name, 0, -3);
		}
	}
	return array($file_content, $file_name);
}

// Delete ALL data in redcap_dataX table for a given project
function deleteProjectData($project_id)
{
    if (!isinteger($project_id) || $project_id < 1) return false;
    // Using a read replica? If so, limit to 10K rows per query to prevent replica getting overloaded.
    $usingReplica = (System::readReplicaConnVarsFound() && System::readReplicaEnabledInConfig());
    // Don't delete too many rows with single query to avoid performance issues of it hanging forever
    $maxLoops = $usingReplica ? 50000 : 5000;
    $limit = $usingReplica ? 10000 : 100000;
    // Add loop limit just in case we get stuck in infinite loop (somehow)
    $loop = 0;
    // Set query to run in each loop
    $sql = "delete from ".\Records::getDataTable($project_id)." where project_id = $project_id limit $limit";
    do {
        $loop++;
        $q = db_query($sql);
    } while ($loop <= $maxLoops && $q && db_affected_rows() > 0);
    // If we finished looping, then always return true
    return true;
}

// Permanently delete the project from all db tables right now (as opposed to flagging it for deletion later)
function deleteProjectNow($project_id, $doLogging=true)
{
	$Proj = new Project($project_id);
	// Get project title (app_title)
	$app_title = strip_tags(label_decode($Proj->project['app_title']));
	// Get logging table
	$logEventTable = Logging::getLogEventTable($project_id);
	// Get list of users with access to project
	$userList = str_replace("'", "", pre_query("select username from redcap_user_rights where project_id = $project_id and username != ''"));
	// Get count of records
	$recordCount = Records::getRecordCount($project_id);
	// Get count of fields
	$fieldCount = count($Proj->metadata);
	// Get status
	if ($Proj->project['status'] == '0') {
        $logstatus = "Development";
    } elseif ($Proj->project['status'] == '1') {
        $logstatus = "Production";
    } else {
        $logstatus = "Analysis/Cleanup";
    }

	// For uploaded edoc files, set delete_date so they'll later be auto-deleted from the server
	db_query("update redcap_edocs_metadata set delete_date = '".date('Y-m-d H:i:s')."' where project_id = $project_id and delete_date is null");
	// Do other deletions manually because some tables don't have foreign key cascade deletion set
    deleteProjectData($project_id);
	// Delete all project data and related info from ALL tables (most will be done by foreign keys automatically)
	$deletedFromRedcapProjects = db_query("delete from redcap_projects where project_id = $project_id");
	// Don't actually delete these because they are logs, but simply remove any data-related info
	db_query("update redcap_log_view set event_id = null, record = null, form_name = null, miscellaneous = null where project_id = $project_id");
	db_query("update redcap_log_view_old set event_id = null, record = null, form_name = null, miscellaneous = null where project_id = $project_id");
	db_query("update $logEventTable set event_id = null, sql_log = null, data_values = null, pk = null where project_id = $project_id and description != 'Delete project'");
	// If due to strange cascading foreign key issue that only affects certain MySQL configs (not sure why),
	// Delete from other tables manually
	if (!$deletedFromRedcapProjects)
	{
		// Disable foreign key checks (just in case)
		db_query("set foreign_key_checks = 0");
		db_query("delete from redcap_metadata where project_id = $project_id");
		db_query("delete from redcap_metadata_temp where project_id = $project_id");
	    db_query("delete from redcap_multilanguage_metadata_temp where project_id = $project_id");
	    db_query("delete from redcap_multilanguage_config_temp where project_id = $project_id");
	    db_query("delete from redcap_multilanguage_ui_temp where project_id = $project_id");
		db_query("delete from redcap_metadata_archive where project_id = $project_id");
		db_query("delete from redcap_reports where project_id = $project_id");
		// Delete docs
		db_query("delete from redcap_docs where project_id = $project_id");
		// Delete calendar events
		db_query("delete from redcap_events_calendar where project_id = $project_id");
		// Delete locking data
		db_query("delete from redcap_locking_data where project_id = $project_id");
		// Delete esignatures
		db_query("delete from redcap_esignatures where project_id = $project_id");
		// Delete survey-related info (response tracking, emails, participants) but not actual survey structure
		$event_ids = pre_query("select e.event_id from redcap_events_metadata e, redcap_events_arms a
								where e.arm_id = a.arm_id and a.project_id = $project_id");
		$survey_ids = pre_query("select survey_id from redcap_surveys where project_id = $project_id");
		if ($survey_ids != "''") {
			// Delete emails to those in Participant List
			db_query("delete from redcap_surveys_emails where survey_id in ($survey_ids)");
			// Delete survey responses
			$response_ids = pre_query("select r.response_id from redcap_surveys_response r, redcap_surveys_participants p
									   where p.participant_id = r.participant_id and p.survey_id in ($survey_ids)");
			if ($response_ids != "''") {
				db_query("delete from redcap_surveys_response where response_id in ($response_ids)");
			}
			// Delete "participants" for follow-up surveys only (do NOT delete public survey "participants" or initial survey participants)
			db_query("delete from redcap_surveys_participants where survey_id in ($survey_ids)");
			// Remove all survey invitations that were queued for records in this project
			$ss_ids = pre_query("select ss_id from redcap_surveys_scheduler where survey_id in ($survey_ids)");
			if ($ss_ids != "''") {
				db_query("delete from redcap_surveys_scheduler_queue where ss_id in ($ss_ids)");
			}
			db_query("delete from redcap_surveys_scheduler where survey_id in ($survey_ids)");
		}
		// Delete rows in redcap_multilanguage_config / ui / metadata
		db_query("delete from redcap_multilanguage_config where project_id = $project_id");
		db_query("delete from redcap_multilanguage_ui where project_id = $project_id");
		db_query("delete from redcap_multilanguage_metadata where project_id = $project_id");
		// Delete rows in redcap_surveys
		db_query("delete from redcap_surveys where project_id = $project_id");
		// Remove any randomization assignments
		db_query("delete from redcap_randomization where project_id = $project_id");
		// Delete all records in redcap_data_quality_status
		db_query("delete from redcap_data_quality_status where project_id = $project_id");
		// Delete all records in redcap_ddp_records
		db_query("delete from redcap_ddp_records where project_id = $project_id");
		// Delete all records in redcap_surveys_queue_hashes
		db_query("delete from redcap_surveys_queue_hashes where project_id = $project_id");
		// Delete records in redcap_new_record_cache
		db_query("delete from redcap_new_record_cache where project_id = $project_id");
		// Delete rows in redcap_surveys_phone_codes
		db_query("delete from redcap_surveys_phone_codes where project_id = $project_id");
		// Delete rows in redcap_events_arms
		db_query("delete from redcap_events_arms where project_id = $project_id");
		db_query("delete from redcap_events_metadata where event_id in ($event_ids)");
		// Delete row in redcap_projects
		db_query("delete from redcap_projects where project_id = $project_id");

		$project_code = db_result(db_query("select code from redcap_mycap_projects where project_id = $project_id"), 0);
		if ($project_code != "") {
			// Delete Sync issues if any
			db_query("delete from redcap_mycap_syncissues where project_code = '".$project_code."'");
		}

		// Delete row in redcap_mycap_projects
		db_query("delete from redcap_mycap_projects where project_id = $project_id");

		$task_ids = pre_query("select task_id from redcap_mycap_tasks where project_id = $project_id");
		if ($task_ids != "''") {
			// Delete task schedules if any
			db_query("delete from redcap_mycap_tasks_schedules where task_id in ($task_ids)");
		}
		// Delete row in redcap_mycap_tasks
		db_query("delete from redcap_mycap_tasks where project_id = $project_id");
		// Delete row in redcap_mycap_themes
		db_query("delete from redcap_mycap_themes where project_id = $project_id");
		// Delete row in redcap_mycap_links
		db_query("delete from redcap_mycap_links where project_id = $project_id");
		// Delete row in redcap_mycap_contacts
		db_query("delete from redcap_mycap_contacts where project_id = $project_id");
        // Delete row in redcap_mycap_aboutpages
		db_query("delete from redcap_mycap_aboutpages where project_id = $project_id");
		// Delete row in redcap_mycap_participants
		db_query("delete from redcap_mycap_participants where project_id = $project_id");
		// Delete row in redcap_mycap_messages
		db_query("delete from redcap_mycap_messages where project_id = $project_id");

		// re-enable foreign key checks (just in case)
		db_query("set foreign_key_checks = 1");
	}

	// Log the permanent deletion of the project
	if ($doLogging)
	{
		$loggingDataValues2 = $loggingDataValues = "project_id: $project_id,\nfields: $fieldCount,\nrecords: $recordCount,\nproject_status: $logstatus,\nproject_title: \"".db_escape($app_title)."\",\nproject_users: $userList";
		if (mb_strlen($loggingDataValues2) > 70) {
		    $loggingDataValues2 = mb_substr($loggingDataValues2, 0, 67)."...";
		}
		$loggingDescription = "Permanently delete project ($loggingDataValues2)";
		$loggingTable		= "redcap_projects";
		$loggingEventType	= "MANAGE";
		$loggingPage 		= (defined("CRON")) ? "cron.php" : PAGE;
		$loggingUser 		= (defined("CRON")) ? "SYSTEM"   : USERID;
		$sql = "insert into $logEventTable (project_id, ts, user, page, event, object_type, pk, data_values, description) values
                ($project_id, '".date("YmdHis")."', '$loggingUser', '$loggingPage', '$loggingEventType', '$loggingTable',
                '$project_id', '".db_escape($loggingDataValues)."', '".db_escape($loggingDescription)."')";
		db_query($sql);
	}
}

// [Retrieval of ALL records] If Custom Record Label is specified (such as "[last_name], [first_name]"), then parse and display.
function getCustomRecordLabels($custom_record_label, $event_id=null, $record=null, $removeIdentifiers=false)
{
	global $project_id_parent, $user_rights, $Proj, $double_data_entry, $table_pk;
	// Store all replaced labels in an array with record as key
	$label_array = array();
	if (!empty($custom_record_label))
	{
		// Get the variables in $custom_record_label
		$custom_record_label_fields = array_unique(array_keys(getBracketedFields($custom_record_label, true, true, true)));

		// If no fields exist in the custom record label, then return empty array
		if (empty($custom_record_label_fields)) {
            // In case the custom_record_label global variable has already had Smart Variables piped, leaving no bracketed fields, check the original value too
            $custom_record_label_fields_orig = array_unique(array_keys(getBracketedFields($Proj->project['custom_record_label'], true, true, true)));
            if (empty($custom_record_label_fields_orig)) {
                return ($record != null ? '' : array());
            }
		}

		// If user has de-id rights, then get list of fields
		$deidFieldsToRemove = (isset($user_rights['data_export_tool']) && $user_rights['data_export_tool'] > 1)
							? DataExport::deidFieldsToRemove($Proj->project_id, $custom_record_label_fields, $user_rights['forms_export'])
							: array();

		// If using DDE, then set filter logic
		$ddeFilter = ($double_data_entry && $user_rights['double_data'] != 0) ? "ends_with([$table_pk], '--{$user_rights['double_data']}')" : false;
		// Get data for all events in the current arm
		$event_ids = array_keys($Proj->events[$Proj->eventInfo[$event_id]['arm_num']]['events']);
		// Get the data
		$custom_record_label_data = Records::getData('array', $record, $custom_record_label_fields, $event_ids, $user_rights['group_id'],
										false, false, false, $ddeFilter);
		// Apply de-id rights if this is a data export (i.e., a PDF)
		$applyDeIdExportRights = (PAGE == 'PdfController:index' || $removeIdentifiers);
		// Loop through all collected data and add to $dropdownid_disptext array
		foreach (array_keys($custom_record_label_data) as $this_record)
		{
			$label_array[$this_record] = Piping::replaceVariablesInLabel($custom_record_label, $this_record, $event_id, 1, $custom_record_label_data,
											false, $Proj->project_id, false, "", 1, false, $applyDeIdExportRights);
		}
	}

	// Return array if multiple records, but return string if for only one record
	if ($record != null && !is_array($record)) {
		foreach ($label_array as $this_field_data) {
			return $this_field_data;
		}
	} else {
		return $label_array;
	}
}

// Obtain array of HTTP headers of current web request
function get_request_headers() {
    $headers = array();
    foreach($_SERVER as $key => $value) {
        if(strpos($key, 'HTTP_') === 0) {
            $headers[str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))))] = $value;
        }
    }
    return $headers;
}

// Provide array of fields and return array of all fields upon which those fields are dependent
// with regard to calc fields and branching logic, and then obtain all fields dependent
// upon THOSE until all are found many levels down.
function getDependentFields($fields, $includeFieldsInCalc=true, $includeFieldsInBranching=true, $includeFieldsInActionTag=false, $Proj2=null)
{
	// Get Proj object
	if ($Proj2 == null && defined("PROJECT_ID")) {
		global $Proj;
	} else {
		$Proj = $Proj2;
	}
	// Loop through all fields on this survey page and obtain all fields used in branching/calcs on this survey page
	$usedInBranchingCalc = $fieldsAlreadyChecked = array();
	$usedInBranchingCalcCount = 0;
	do {
		$usedInBranchingCalcCountLast = count($usedInBranchingCalc);
		foreach ($fields as $this_field) {
			// If we've already checked this field for its dependent fields, then skip it
			if (isset($fieldsAlreadyChecked[$this_field])) continue;
			if ($includeFieldsInCalc && isset($Proj->metadata[$this_field]) && ($Proj->metadata[$this_field]['element_type'] == 'calc'
			        || ($Proj->metadata[$this_field]['element_type'] == "text" &&
			                (Calculate::isCalcDateField($Proj->metadata[$this_field]['misc']) || Calculate::isCalcTextField($Proj->metadata[$this_field]['misc'])))))
			{
			    $logicToSearch = ($Proj->metadata[$this_field]['element_type'] == 'calc') ? $Proj->metadata[$this_field]['element_enum'] : $Proj->metadata[$this_field]['misc'];
				$usedInBranchingCalc = array_merge($usedInBranchingCalc, array_keys(getBracketedFields($logicToSearch, true, true, true)));
				$fieldsAlreadyChecked[$this_field] = true;
			}
			if ($includeFieldsInBranching && isset($Proj->metadata[$this_field]) && $Proj->metadata[$this_field]['branching_logic'] != '') {
				$usedInBranchingCalc = array_merge($usedInBranchingCalc, array_keys(getBracketedFields($Proj->metadata[$this_field]['branching_logic'], true, true, true)));
				$fieldsAlreadyChecked[$this_field] = true;
			}
			if ($includeFieldsInActionTag && isset($Proj->metadata[$this_field]) && $Proj->metadata[$this_field]['misc'] != '') {
				$usedInBranchingCalc = array_merge($usedInBranchingCalc, array_keys(getBracketedFields($Proj->metadata[$this_field]['misc'], true, true, true)));
				$fieldsAlreadyChecked[$this_field] = true;
			}
		}
		$fields = $usedInBranchingCalc = array_unique($usedInBranchingCalc);
		$usedInBranchingCalcCount = count($usedInBranchingCalc);
	} while ($usedInBranchingCalcCount != $usedInBranchingCalcCountLast);
	// Return array of all dependent fields
	return array_values($usedInBranchingCalc);
}

// Compares two urls to see if they have the same HOST
function sameHostUrl($url1, $url2='') {
	global $redcap_base_url;
	if ($url2 == '') $url2 = $redcap_base_url;
	$parts1 = parse_url($url1);
	$parts2 = parse_url($url2);
	$host1 = $parts1['host'];
	$host2 = $parts2['host'];
	return ($host1 && $host2 && gethostbyname($host1) == gethostbyname($host2));
}


// Better version of readfile that handles memory better when downloading large files
function readfile_chunked($filename, $retbytes=true)
{
   $chunksize = 10 * (1024*1024); // how many bytes per chunk
   $buffer = '';
   $cnt =0;
   $handle = fopen($filename, 'rb');
   if ($handle === false) {
       return false;
   }
   while (!feof($handle)) {
       $buffer = fread($handle, $chunksize);
       echo $buffer;
       ob_flush();
       flush();
       if ($retbytes) {
           $cnt += strlen($buffer);
       }
   }
   $status = fclose($handle);
   if ($retbytes && $status) {
       return $cnt; // return num. bytes delivered like readfile() does.
   }
   return $status;
}

// Get hashed password of user using login Post parameter 'username'.
// For the sole purpose of Pear Auth, we need a single function to supply as the cryptType parameter
// to be used during the login process for table-based users.
function hashPasswordPearAuthLogin($password)
{
	return Authentication::hashPassword($password, '', $_POST['username']);
}

// Replacement function for PHP's array_fill_keys() if on a PHP version less than 5.2.0
if (!function_exists('array_fill_keys')) {
	function array_fill_keys($target, $value = '') {
		$filledArray = array();
		if (is_array($target)) {
			foreach ($target as $key => $val) {
				$filledArray[$val] = $value;
			}
		}
		return $filledArray;
	}
}

// Similar to PHP's natcasesort() but sorts by keys
function natcaseksort(&$array, $reverseSort=false)
{
	$original_keys_arr = array();
    $original_values_arr = array();
	$i = 0;
    foreach ($array as $key=>$value) {
        $original_keys_arr[$i] = $key;
        $original_values_arr[$i] = $value;
		unset($array[$key]); // Conserve memory
        $i++;
    }
    natcasesort($original_keys_arr);
	if ($reverseSort) {
		$original_keys_arr = array_reverse($original_keys_arr);
	}
    $result_arr = array();
    foreach ($original_keys_arr as $key=>$value) {
        $result_arr[$original_keys_arr[$key]] = $original_values_arr[$key];
    }
    $array = $result_arr;
}

// Obtain array of column names and their default value from specified database table.
// Column name will be array key and default value will be corresponding array value.
function getTableColumns($table)
{
	$sql = "describe `$table`";
	$q = db_query($sql);
	if (!$q) return false;
	$cols = array();
	while ($row = db_fetch_assoc($q)) {
		$cols[$row['Field']] = $row['Default'];
	}
	return $cols;
}

// Return all columns of a table (except pk_id) as comma-delimited wrapped in backticks (e.g., `project_id`, `field_name`, `field`...)
function formatTableColumnsForQuery($table)
{
	$cols = getTableColumns($table);
	if (isset($cols['pk_id'])) unset($cols['pk_id']); // Remove auto-added PK
	return "`" . implode("`, `", array_keys($cols)) . "`";
}


// Performs a case insensitive match of a substring in a string (used in logic)
function contains($haystack='', $needle='')
{
	return (stripos(($haystack??""), ($needle??"")) !== false);
}


// Performs a case insensitive match of a substring in a string if NOT MATCHED (used in logic)
function not_contain($haystack='', $needle='')
{
	return (stripos(($haystack??""), ($needle??"")) === false);
}

function replace_text($haystack="", $search="", $replace="") {
	return str_replace($search, $replace, $haystack);
}

// Checks if string begins with a substring - case insensitive match (used in logic)
function starts_with($haystack='', $needle='')
{
    return ($needle === "" || stripos(($haystack??""), ($needle??"")) === 0);
}


// Checks if string ends with a substring - case insensitive match (used in logic)
function ends_with($haystack='', $needle='')
{
    return starts_with(strrev($haystack??""), strrev($needle??""));
}

// Remove the ending --# at the end of a record name for DDE
function removeDDEending($record) {
	global $double_data_entry, $user_rights;
	return ($record != '' && isset($double_data_entry) && $double_data_entry && is_array($user_rights) && $user_rights['double_data'] != 0 && substr($record, -3) == '--'.$user_rights['double_data']) ? substr($record, 0, -3) : $record;
}

// Append the ending --# to the end of a record name for DDE
function addDDEending($record) {
	global $double_data_entry, $user_rights;
	return $record . (($record != '' && isset($double_data_entry) && $double_data_entry && is_array($user_rights) && $user_rights['double_data'] != 0) ? '--'.$user_rights['double_data'] : '');
}

// Return boolean if GD2 library is installed
function gd2_enabled()
{
	if (extension_loaded('gd') && function_exists('gd_info')) {
		$ver_info = gd_info();
		preg_match('/\d/', $ver_info['GD Version'], $match);
        return ($match[0] >= 2);
	} else {
		return false;
	}
}

// Store a cookie by name, value, and expiration (0=will expire when session ends)
function savecookie()
{
	$args = func_get_args();
	call_user_func_array('Session::savecookie', $args);
}

// Delete a cookie by name
function deletecookie($name)
{
	$args = func_get_args();
	call_user_func_array('Session::deletecookie', $args);
}

// Conceal all digits of phone number except last 4 (leave non-digits as they were, but replace #'s with X's)
function concealPhone($phoneNumber)
{
	return preg_replace("/[\d]/", "X", substr($phoneNumber, 0, -4)) . substr($phoneNumber, -4);
}

// Format the display of a phone number (US and international)
function formatPhone($phoneNumber)
{
    if ($phoneNumber === null) return "";
	// If number contains an extension (denoted by a comma between the number and extension), then separate here and add later
	$phoneExtension = "";
	if (strpos($phoneNumber, ",") !== false) {
		list ($phoneNumber, $phoneExtension) = explode(",", $phoneNumber, 2);
	}
	// Format the number
    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    if (strlen($phoneNumber) > 10) {
        $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
        $areaCode = substr($phoneNumber, -10, 3);
        $nextThree = substr($phoneNumber, -7, 3);
        $lastFour = substr($phoneNumber, -4, 4);
        $phoneNumber = '+'.$countryCode.' '.$areaCode.'-'.$nextThree.'-'.$lastFour;
    }
    elseif (strlen($phoneNumber) == 10) {
        $areaCode = substr($phoneNumber, 0, 3);
        $nextThree = substr($phoneNumber, 3, 3);
        $lastFour = substr($phoneNumber, 6, 4);
        $phoneNumber = $areaCode.'-'.$nextThree.'-'.$lastFour;
    }
    elseif (strlen($phoneNumber) == 7) {
        $nextThree = substr($phoneNumber, 0, 3);
        $lastFour = substr($phoneNumber, 3, 4);
        $phoneNumber = $nextThree.'-'.$lastFour;
    }
	// If has an extension, re-add it
	if ($phoneExtension != "") $phoneNumber .= ",$phoneExtension";
	// Return formatted number
    return $phoneNumber;
}

// function similar to is_numeric but works with commas as decimals
function is_numeric_comma($val)
{
	$regex_pattern = "/^[-+]?[0-9]*,?[0-9]+([eE][-+]?[0-9]+)?$/";
	// Run the value through the regex pattern
	preg_match($regex_pattern, $val, $regex_matches);
	// Was it validated? (If so, will have a value in 0 key in array returned.)
	return isset($regex_matches[0]);
}

// chkNull function similar to JS used in calculations
function chkNull($val)
{
	if (is_numeric_comma($val)) $val = str_replace(",", ".", $val);
	return (($val !== '0' && $val !== (int)0 && $val !== (float)0
			&& ($val == '' || $val == null || $val === 'NaN' || (is_float($val) && is_nan($val)) || !is_numeric($val)))
		? NAN : $val*1);
}

// REDCap version of PHP's is_nan that returns TRUE for "" and NULL
function is_nan_null($val='')
{
	return ((is_float($val) && is_nan($val)) || $val === '' || $val === null || $val === 'NaN' || $val === 'NAN');
}

// Used to replace chkNull($arg0)==chkNull($arg1) and similar operational strings in calc fields to
// avoid NaN==NaN and similar constructs that act counter-intuitively due to PHP internal logic.
// (NOTE: This is extremely complex, so please don't modify this unless you really know what you're doing.)
function chkNullCompare($arg0=null, $arg1=null, $operator=null)
{
    // If operator somehow contains a parenthesis, then throw exception
    if ($operator == null || !in_array($operator, array("=", "==", "<>", "!=", ">", ">=", "<", "<="))) throw new Exception();

	$operatorBase = substr($operator, 0, 1);
	$arg0ChkNull = chkNull($arg0);
	$arg1ChkNull = chkNull($arg1);
	$arg0IsNan = is_float($arg0ChkNull) && is_nan($arg0ChkNull);
	$arg1IsNan = is_float($arg1ChkNull) && is_nan($arg1ChkNull);
	$arg0Func = $arg0IsNan ? "''" : $arg0ChkNull;
	$arg1Func = $arg1IsNan ? "''" : $arg1ChkNull;

	if (( $arg0IsNan && !$arg1IsNan && $operatorBase == '<') ||
		(!$arg0IsNan &&  $arg1IsNan && $operatorBase == '>'))
	{
		// If num>NAN or NAN<num, then ALWAYS return False. This is simply to emulate how the JS version behaves in this situation.
		$code = "false";
	} else {
		// Replace any NANs with '' to allow proper/expected evaluation of the two entities.
		$code = "($arg0Func $operator $arg1Func)";
	}
	eval("\$value = $code;");
	return $value;
}

/**
 * CALCULATE MYSQL SPACE USAGE
 * Returns usage in bytes
 */
function getDbSpaceUsage()
{
	global $db;
	// Get table row counts and also total MySQL space used by REDCap (only consider 'redcap_*' tables)
	$total_mysql_space = 0;
	$q = db_query("SHOW TABLE STATUS from `$db` like 'redcap_%'");
	while ($row = db_fetch_assoc($q)) {
		if (strpos($row['Name'], "_20") === false) { // Ignore timestamped archive tables
			$total_mysql_space += $row['Data_length'] + $row['Index_length'];
		}
	}
	// Return total
	return $total_mysql_space;
}

// Convert an array to CSV string
function arrayToCsv($dataset, $returnKeysAsHeaders=true, $delimiter=null, $sanitizeData=true)
{
    if ($delimiter == null) {
        $delimiter = User::getCsvDelimiter();
    }
    if ($dataset == null) $dataset = array();
    $sanitizationArray = array('=', '-', '+', '@');
	// Open connection to create file in memory and write to it
	$fp = fopen('php://memory', "x+");
	// Add header row to CSV
	if ($returnKeysAsHeaders && !empty($dataset)) {
		$dataset = array_values($dataset); // Reset keys since we'll use index 0
		if (isset($dataset[0])) {
            fputcsv($fp, array_keys($dataset[0]), $delimiter, '"', '');
		}
	}
	// Loop through array and output line as CSV
	foreach ($dataset as $line) {
        // If value begins with certain characters, then prepend value with TAB character to prevent CSV injection
        if ($sanitizeData) {
            foreach ($line as &$val) {
                if (is_string($val) && in_array(substr($val, 0, 1), $sanitizationArray)) {
                    $val = "\t" . $val;
                }
            }
	    }
        // Add row to CSV file
		fputcsv($fp, $line, $delimiter, '"', '');
	}
	// Open file for reading and output to user
	fseek($fp, 0);
	$output = trim(stream_get_contents($fp));
	fclose($fp);
	return $output;
}

// Correct any mangled UTF-8 strings (often caused by incorrectly encoded Excel files)
function fixUTF8($string, $forceFix=false)
{
	if (!MBSTRING_ENABLED) return $string;
	if ($forceFix || (mb_detect_encoding($string) == 'UTF-8' && $string."" !== mb_convert_encoding($string, 'UTF-8', 'UTF-8')."")) {
		// Convert to true UTF-8 to remove black diamond characters
		$string = utf8_encode_rc($string);
	}
	return $string;
}

// Mimics utf8_encode for PHP 8.2 compatibility (taken from symfony/polyfill-php72)
function utf8_encode_rc($string)
{
    return ($string !== null) ? mb_convert_encoding($string, 'UTF-8', 'UTF-8') : '';
}

// Encode the JSON in $item and correct any special characters that might be dropped
// returns false if bad
function json_encode_rc($item)
{
	$item_json = json_encode($item);
	$json_encode_failed = ($item_json === false || $item_json === null);
	if ($json_encode_failed && MBSTRING_ENABLED) {
        // Try this first
        $item_json = json_encode(mb_convert_encoding($item, "UTF-8", "UTF-8"));
	    $json_encode_failed = ($item_json === false || $item_json === null);
        if ($json_encode_failed) {
            // Fix if any illegal characters are preventing json encoding
            if (is_array($item) || is_object($item)) {
                foreach ($item as &$val) {
                    if (is_array($val) || is_object($val)) {
                        // Recursive
                        $val = json_encode_rc($val);
                    } else {
                        $val = fixUTF8($val, true);
                    }
                }
            } else {
                $item = fixUTF8($item, true);
            }
            $item_json = json_encode($item, JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
	} elseif ($json_encode_failed && !MBSTRING_ENABLED) {
		$item_json = json_encode($item, JSON_PARTIAL_OUTPUT_ON_ERROR);
	}
	return $item_json;
}

// Function json_last_error_msg() is only available in PHP 5.5+, so add a surrogate here.
if (!function_exists('json_last_error_msg')) {
	function json_last_error_msg() {
		static $ERRORS = array(
			JSON_ERROR_NONE => 'No error',
			JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
			JSON_ERROR_STATE_MISMATCH => 'State mismatch (invalid or malformed JSON)',
			JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
			JSON_ERROR_SYNTAX => 'Syntax error',
			JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
		);

		$error = json_last_error();
		return isset($ERRORS[$error]) ? $ERRORS[$error] : 'Unknown error';
	}
}

/**
 * recursively sanitize an array.
 * useful when json_encode to fails for invalid UTF8 charcacters
 *
 * @param Array|Object|string $item
 * @return Array|Object|string
 */
function array_sanitize_utf8($item) {
	if(is_object($item)) $item = (array) $item;
	if (is_array($item)) {
		foreach ($item as $key => $value) {
			$item[$key] = array_sanitize_utf8($value);
		}
	} else if (is_string ($item)) {
		return ensureUTF($item);
	}
	return $item;
}

/**
 * Ensures the value, when a string, is UTF-8 compliant
 * @param mixed $val The value to be checked
 * @return mixed 
 */
function ensureUTF($val) {
	if (is_string($val)) {
        $encoding = mb_detect_encoding($val, mb_detect_order(), true);
        // Check if encoding detection failed, and if so, default to UTF-8
        if ($encoding === false) {
            $encoding = 'UTF-8';
        }
        $val = mb_convert_encoding($val, "UTF-8", $encoding);
	}
	return $val;
}

// USAGE: For use in textarea/input for filter logic. textarea/input = "item"
//        1. Add this inside item: 'onkeydown' => 'logicSuggestSearchTip(this, event);'
//        2. Add this to the front of the item's onblur: 'logicHideSearchTip(this);'
//        3. Add this directly after item: logicAdd(<ITEM_ID>)
// location_id is id of the textarea or inpupt being focused upon
function logicAdd($location_id)
{
	return "<div id='LSC_id_$location_id' class='fs-item-parent fs-item'></div>";
}
function logicSearchResults($location_id, $searchTerm='', $draftModeSearch=false)
{
	global $Proj, $status, $draft_mode;
	$label_len = 15;

	// Clean $location_id
	$location_id = preg_replace("/[^a-zA-Z0-9-_]/", "", $location_id);

	// Clean search term
	$word = strip_tags(label_decode($searchTerm));
	// Remove any prepended [ bracket
	$precedingWord = "";
	if (strpos($word, "[") !== false && strpos($word, "][") === false) {
		list ($precedingWord, $word) = explode("[", $word, 2);
	}

	// Should we show draft mode fields instead of live fields
	$doDraftModeSearch = ($status > 0 && $draft_mode > 0 && $draftModeSearch);

	// If we're querying for Draft Mode fields, then show those. Otherwise, search live fields.
	$fields = $doDraftModeSearch ? array_keys($Proj->metadata_temp) : array_keys($Proj->metadata);

	// If prepended event name was provided, then use it to filter to fields in that event
	$showEvents = $Proj->longitudinal;
	$uniqueEventNames = $Proj->getUniqueEventNames();
	if ($Proj->longitudinal && strpos($word, "][") !== false) {
		// Prevent matching with event names since event name is prepended
		$showEvents = false;
		// Get the passed event name and true field name
		list ($searchTermEventName, $word) = explode("][", $word, 2);
		$searchTermEventId = array_search($searchTermEventName, $uniqueEventNames);
		// Limit fields to only this event's designated forms
		if (is_numeric($searchTermEventId)
			// Don't perform limiting if in draft mode (because we don't have a temp version of $Proj->eventForms for draft mode)
			&& !$doDraftModeSearch)
		{
			// Rebuild fields array from designated forms for this event
			$fields = array();
			foreach ($Proj->eventsForms[$searchTermEventId] as $this_form) {
				$fields = array_merge($fields, array_keys($Proj->forms[$this_form]['fields']));
			}
		}
	}

	// Create regex to apply for searching
	$regex = "/^".preg_quote($word, '/').".*$/";

	// EVENTS
	$eventTip = "";
	if ($showEvents) {
		$eventInfo = $Proj->eventInfo;
		$matches = preg_grep($regex, $uniqueEventNames);
		foreach ($matches as $key => $value) {
			if ($eventTip == "") {
				$eventTip .= RCView::div(array("class" => "fs-item-ev-hdr fs-item", "id" => "LSC_ev_".$location_id), RCView::tt("global_130"));
			}
			$event_name = mb_substr($eventInfo[$key]['name_ext'], 0, $label_len);
			if (mb_strlen($eventInfo[$key]['name_ext']) > $label_len) $event_name .= "...";
			$eventTip .= RCView::div(array("class" => "fs-item-ev fs-item", "id" => "LSC_ev_" . $location_id . "_" . $value, "onmousedown" => "logicSuggestClick('[$value]','$location_id');"), "[$value]&nbsp;&nbsp;<i>$event_name</i>");
		}
	}

	// FIELDS
	$num_fields = 0;
	$fieldTip = "";
	$matches = preg_grep($regex, $fields);
	$field_name_append = "";
	foreach ($matches as $field_name) {
		// Get field attributes
		$row = $doDraftModeSearch ? $Proj->metadata_temp[$field_name] : $Proj->metadata[$field_name];
		// Is a checkbox? If so, add parentheses inside square brackets.
		$isCheckbox = $doDraftModeSearch ? ($Proj->metadata_temp[$field_name]['element_type'] == 'checkbox') : ($Proj->metadata[$field_name]['element_type'] == 'checkbox');
		$field_name_append = $isCheckbox ? "(?)" : "";
		// Skip descriptive fields
		if ($row['element_type'] == 'descriptive') continue;
		// Field header
		if ($num_fields == 0) {
			$fieldTipBg = ($Proj->longitudinal) ? "background-color:#e0e0e0;" : "";
			$fieldTip = RCView::div(array("class" => "fs-item-fn-hdr fs-item", "id" => "LSC_fn_".$location_id, "style" => $fieldTipBg), RCView::tt("global_131"));
		}
		$row['element_label'] = strip_tags(label_decode($row['element_label']));
		$field_label = mb_substr($row['element_label'], 0, $label_len);
		if (mb_strlen($row['element_label']) > $label_len) $field_label .= "...";
		$fieldTip .= RCView::div(array("class" => "fs-item-fn fs-item", "id" => "LSC_fn_" . $location_id . "_" . $row['field_name'], "onmousedown" => "logicSuggestClick('[{$field_name}{$field_name_append}]','$location_id');"), "[$field_name]&nbsp;&nbsp;<i>$field_label</i>");
		$num_fields++;
	}

	// Matches for Smart Variables
	$smartVarTip = "";
	$smartVarsMatch = Piping::getSpecialTags($searchTerm);
	if (!empty($smartVarsMatch)) {
		$smartVarTip = RCView::div(array("class" => "fs-item-fn-hdr fs-item", "id" => "LSC_fn_".$location_id, "style" => "background-color:#e0e0e0;"), RCView::tt("global_146"));
	}
	foreach ($smartVarsMatch as $field_name) {
		$smartVarTip .= RCView::div(array("class" => "fs-item-fn fs-item", "id" => "LSC_fn_" . $location_id . "_" . $field_name, "onmousedown" => "logicSuggestClick('[{$field_name}{$field_name_append}]','$location_id');"), "[$field_name]");
	}

	// Output the content
	if (trim($eventTip.$fieldTip.$smartVarTip) == "") {
		return "";
	} else {
		return $eventTip . $fieldTip . $smartVarTip;
	}
}

// Exactly like PHP explode() but done from the right instead of left
function explode_right($delimiter, $string, $limit=2)
{
    if (is_float($string) || is_int($string)) $string = (string)$string;
    if (!is_string($string) || !is_string($delimiter)) return ["","","",""];
    try {
	    $array = array_map('strrev', explode(strrev($delimiter), strrev($string), $limit));
    } catch (Throwable $e) {
        $array = [$string];
    }
	krsort($array);
	return array_values($array);
}

// Add mime_content_type() function in not already in PHP
if(!function_exists('mime_content_type')) {

    function mime_content_type($filename) {

        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $parts = explode('.',$filename);
        $ext = strtolower(array_pop($parts));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        }
        else {
            return 'application/octet-stream';
        }
    }
}

// Replacement for PHP's deprecated create_function function
function create_function_rc()
{
	$func_name = generate_function_name();
	$args = func_get_args();
	$eval_string = "function $func_name({$args[0]}){{$args[1]}}";
    try {
        eval($eval_string);
    }
    catch (DivisionByZeroError $e)  { }
    catch (TypeError $e)  { }
    catch (ParseError $e) { }
    catch (Throwable $e)  { }
	return $func_name;
}

// Generate a new function name
function generate_function_name()
{
	$found = false;
	$func_prefix = "redcap_func_";
	do {
		$this_func = $func_prefix . substr(sha1(rand()), 0, 16);
		if (!function_exists($this_func)) {
			$found = $this_func;
		}
	} while ($found === false);
	return $found;
}

// Convert CSV string to associative array with first row of CSV as array keys
function csvToArray($csvdata, $csvDelimiter=null)
{
    if ($csvDelimiter == null) {
        $csvDelimiter = User::getCsvDelimiter();
    }
    if (is_array($csvdata)) return "";
	// Trim the data, just in case
	$csvdata = trim($csvdata ?? "");
    // If CSV file is in Japanese SJIS encoding, convert to UTF-8
    if (mb_detect_encoding($csvdata, "UTF-8, ASCII, SJIS") == "SJIS") {
        $csvdata = mb_convert_encoding($csvdata, 'UTF-8', 'SJIS');
    }
	// Add CSV string to memory file so we can parse it into an array
	$h = fopen('php://memory', "x+");
	fwrite($h, $csvdata);
	fseek($h, 0);
	// Now read the CSV file into an array
	$data = array();
	$csv_headers = null;
    $csv_headers_count = 0;
	while (($row = fgetcsv($h, 0, $csvDelimiter, '"', '')) !== false) {
		if ($csv_headers == null) {
			$csv_headers = $row;
            foreach ($csv_headers as $key=>$hdr) {
                if (trim($hdr) == '') unset($csv_headers[$key]);
            }
            $csv_headers_count = count($csv_headers);
		} else {
			// If row is completely blank, then skip it
			if (strlen(trim(implode("", $row))) > 0 && is_array($csv_headers) && is_array($row)) {
                // If row unexpectedly contains extra columns that aren't represented by the headers, ignore those superfluous columns
                $row_count = count($row);
                if ($row_count !== $csv_headers_count) {
                    if ($row_count > $csv_headers_count) {
                        // Remove extra columns for this row
                        $row = array_slice($row, 0, $csv_headers_count);
                    } else {
                        // Add extra columns to this row as blank values
                        for ($i = 0; $i < ($csv_headers_count-$row_count); $i++) {
                            $row[] = "";
                        }
                    }
                }
                // Add row to array
				$data[] = array_combine($csv_headers, $row);
			}
		}
	}
	fclose($h);
	unset($csv_headers, $row, $csvdata);
	return $data;
}

// Same as PHP's shuffle() except it additionally maintains the array keys
function shuffle_assoc(&$array)
{
	$keys = array_keys($array);
	shuffle($keys);
	foreach($keys as $key) {
		$new[$key] = $array[$key];
	}
	$array = $new;
	return true;
}

// A replacement function for using both date($format, mktime()) in PHP, which cannot go beyond year 2038.
// This function replaces date($format, mktime($hour, $minute, $second, $month, $day, $year))
function date_mktime($format='Y-m-d H:i:s', $hour=null, $minute=null, $second=null, $month=null, $day=null, $year=null)
{
	// Set any components that aren't set
	if ($hour === null) $hour = date("H");
	if ($minute === null) $minute = date("i");
	if ($second === null) $second = date("s");
	if ($month === null) $month = date("m");
	if ($day === null) $day = date("d");
	if ($year === null) $year = date("Y");
	// Days in current month
    $cal_days_in_month = getCalDaysInMonth($month, $year);
	// Deal with any values passed that are too large
    $sec_extra = 0;
    if ($day < 0) {
        $sec_extra += ($day*86400);
        $day = 0;
    }
    if ($day > $cal_days_in_month) {
        $days_over = $day - $cal_days_in_month;
        $sec_extra += ($days_over * 86400);
        $day = $cal_days_in_month;
    }
    if ($month > 12 || $month < 0) {
        $sec_extra += ($month*30.44*86400);
        $month = 0;
    }
    if ($hour > 24 || $hour < 0) {
        $sec_extra += ($hour*3600);
        $hour = 0;
    }
    if ($minute > 60 || $minute < 0) {
        $second += ($minute*60);
        $minute = 0;
    }
	if ($second > 60 || $second < 0) {
		$sec_extra += $second;
		$second = 0;
	}
	// Format components
	$month = sprintf('%02d', $month);
	$day = sprintf('%02d', $day);
	$hour = sprintf('%02d', $hour);
	$minute = sprintf('%02d', $minute);
	$second = sprintf('%02d', $second);
    // Convert to preferred format
	$date = DateTime::createFromFormat('Y-m-d H:i:s', "$year-$month-$day $hour:$minute:$second");
	if ($sec_extra != 0) {
		$interval = new DateInterval('PT' . abs(floor($sec_extra)) . 'S');
		if ($sec_extra > 0) {
			$date->add($interval);
		} else {
			$date->sub($interval);
		}
	}
    return $date->format($format);
}

// Return boolean if a date or datetime (in Y-M-D format) occurs during Daylight Saving Time
function isDST($date)
{
    if ($date == null || !is_string($date)) return false;
    // if (strlen($date) > 10) $date = left($date, 10);
    return (date('I', strtotime($date)) == '1');
}

// Determine if value is an integer
function isinteger($val)
{
    if ($val === null || $val === "" || is_array($val)) return false;
	$val = trim($val."");
	$regex = "/^[-+]?\b\d+\b$/";
	return (is_numeric($val) && $val == (int)$val && preg_match($regex, $val)
	        // If has leading zeros, then it is not an integer.
	        && !($val !== "0" && substr($val, 0, 1) === "0")
    );
}

// Update a row in the config table
function updateConfig($field, $val)
{
    $sql = "replace into redcap_config (field_name, value) 
            values ('".db_escape($field)."', '".db_escape($val)."')";
	return db_query($sql);
}

// Create a short URL
function getREDCapShortUrl($urlToShorten, $customEnding="")
{
    $url_shortener_domain = "redcap.link";
    $params = array(
        "cdn_prefix"=>$url_shortener_domain,
        "url_long"=>$urlToShorten,
        "custom_ending"=>$customEnding,
        'hostkey_hash'=>Stats::getServerKeyHash()
    );
    $json = http_post("https://$url_shortener_domain/admin_shrink_url", $params, 30, 'application/json');
    $response = json_decode($json, true);
    if ($json == "" || !is_array($response)) {
        $response = array('error'=>"Unknown error. Please try again later.");
    }
    return $response;
}

/**
 * Provide a bunch of language variables from $lang and add them as JavaScript variables
 * as part of the lang object (e.g., lang.global_01), all wrapped in <script> tags
 * @param mixed $lang_vars A single lang id or an array of ids
 * @param bool $direct_output When set to false, the script tag HTML will be returned only, and not printed.
 * @return string
 */
function addLangToJS($lang_vars, $direct_output = true) {
    if (!is_array($lang_vars)) $lang_vars = array($lang_vars);
    $jsArray = array();
    foreach ($lang_vars as $thisVar) {
		$jsArray[] = "lang.$thisVar='".RCView::tt_js($thisVar)."';";
    }
	$script = "";
	if (!empty($jsArray)) {
		$script = "\n<script type=\"text/javascript\">\nif(typeof lang=='undefined'){var lang={}};\n".implode("\n", $jsArray)."\n</script>\n";
		if ($direct_output) {
			print $script;
		}
	}
	return $script;
}

// Replacement for PHP's log() function (returns NAN if $number is not a number)
function logRC($number=null, $base=M_E)
{
	if ($number == null) return NAN;
	// If missing numeric base, then do natural log
	if (!is_numeric($base)) $base = M_E;
	// Return log
	return log($number, $base);
}

// Determine if value is a number. If user uses is_number instead of is_numeric.
function isnumber($val)
{
    $val = trim($val ?? "");
    // Check if the value is a numeric string or a number
    if (!is_numeric($val)) {
        return false;
    }
    // If begins with + or -, which is allowable, then remove that character for further processing
    $firstChar = substr($val, 0, 1);
    if ($firstChar == "+" || $firstChar == "-") {
        $val = substr($val, 1);
    }
    // Check for leading zeros (excluding the case where the value is "0")
    if (strlen($val) > 1 && substr($val, 0, 1) === '0' && strpos($val, ".") === false) {
        return false;
    }
    // Check for multiple leading zeros
    if (strlen($val) >= 2 && substr($val, 0, 2) === '00') {
        return false;
    }
    // Passed all checks, return true
    return true;
}

// Calculate the exponential of a number
function exponential($expnumber) {
	if (!is_numeric($expnumber)) return NAN;
	return exp($expnumber);
}

// Round numbers to a given decimal point (returns FALSE if $number is not a number)
function roundRC($number=null,$precision=0)
{
	if ($number === null || $number === '' || !isinteger($precision) || !is_numeric($number)) return NAN;
	return round($number, $precision);
}

// Round numbers up to a given decimal point
function roundup($number=null,$precision=0)
{
	if ($number === null || $number === '' || !isinteger($precision) || !is_numeric($number)) return NAN;
	$factor = pow(10, -1 * $precision);
	return ceil($number / $factor) * $factor;
}

// Round numbers down to a given decimal point
function rounddown($number=null,$precision=0)
{
	if ($number === null || $number === '' || !isinteger($precision) || !is_numeric($number)) return NAN;
	$factor = pow(10, -1 * $precision);
	return floor($number / $factor) * $factor;
}

// Find sum of numbers (each used as parameter or all values passed as an array)
function sum()
{
	$arg_list = func_get_args();
    if (is_array($arg_list[0])) $arg_list = $arg_list[0];
	foreach ($arg_list as $argnum=>$arg)
	{
		// Trim it first
		$arg_list[$argnum] = $arg = trim($arg);
		// Make sure it's a number, else remove it
		if (!is_numeric($arg)) unset($arg_list[$argnum]);
	}
	return (empty($arg_list) ? NAN : array_sum($arg_list));
}

// Find mean/average of numbers (each used as parameter or all values passed as an array)
function mean()
{
	$arg_list = func_get_args();
    if (is_array($arg_list[0])) $arg_list = $arg_list[0];
	foreach ($arg_list as $argnum=>$arg)
	{
		// Trim it first
		$arg_list[$argnum] = $arg = trim($arg);
		// Make sure it's a number, else remove it
		if (!is_numeric($arg)) unset($arg_list[$argnum]);
	}
	return (empty($arg_list) ? NAN : array_sum($arg_list) / count($arg_list));
}

/**
 * Median
 * number median ( number arg1, number arg2 [, number ...] )
 * number median ( array numbers )
 */
function median()
{
	$args = func_get_args();
	if (is_array($args[0])) $args = $args[0];
	$num_args = count($args);
	switch ($num_args)
	{
		case 0:
			//trigger_error('median() requires at least one parameter',E_USER_WARNING);
			return NAN;
		case 1:
			// Fall through
			if (is_array($args)) {
				$args = array_pop($args);
			}
			// Median of a single number is the number itself
			if (!is_array($args)) {
				return (is_numeric($args) ? $args : NAN);
			}
		default:
			if (!is_array($args)) {
				//trigger_error('median() requires a list of numbers to operate on or an array of numbers',E_USER_NOTICE);
				return NAN;
			}
			// Make sure all are numbers
			foreach ($args as $argnum=>$arg)
			{
				// Trim it first
				$args[$argnum] = $arg = trim($arg);
				// Make sure it's a number, else remove it
				if (!is_numeric($arg)) unset($args[$argnum]);
			}
			if (empty($args)) return NAN;
			// Sort the args
			sort($args);
			$n = count($args);
			$h = intval($n / 2);
			// Determine the median
			if($n % 2 == 0) {
				$median = ($args[$h] + $args[$h-1]) / 2;
			} else {
				$median = $args[$h];
			}
			break;
	}
	return $median;
}

// Calculate the modulo
function mod() {
	$args = func_get_args();
	if (is_array($args[0])) $args = $args[0];
	$num_args = count($args);
	// mod() requires two arguments (dividend and divisor)
	if ($num_args != 2) return NAN;
	$dividend = $args[0];
	$divisor = $args[1];
	// Both arguments must be integers
	if (!isinteger($dividend) || !isinteger($divisor)) return NAN;
	$dividend *= 1;
	$divisor *= 1;
	// Divisor must not be 0
	if ($divisor == 0) return NAN;
	return $dividend % $divisor;
}

// Return the year component of a date/datetime field
function year($val)
{
    if ($val === null) return NAN;
    $val = in_array(strtolower($val), array('now', 'today')) ? TODAY : $val;
    if (strpos($val, '-') === false) return NAN;
    $parts = explode('-', $val);
    return (isset($parts[0]) && isinteger($parts[0]*1) && (int)$parts[0] >= 1 && (int)$parts[0] <= 9999 ? $parts[0] : NAN);
}

// Return the month component of a date/datetime field
function month($val)
{
    if ($val === null) return NAN;
    $val = in_array(strtolower($val), array('now', 'today')) ? TODAY : $val;
    if (strpos($val, '-') === false) return NAN;
    $parts = explode('-', $val);
    return (isset($parts[1]) && isinteger($parts[1]*1) && (int)$parts[1] >= 1 && (int)$parts[1] <= 12 ? $parts[1] : NAN);
}

// Return the day component of a date/datetime field
function day($val)
{
    if ($val === null) return NAN;
    $val = in_array(strtolower($val), array('now', 'today')) ? TODAY : $val;
    if (strpos($val, '-') === false) return NAN;
    $parts = explode('-', $val);
    // Remove time, if exists
    if (isset($parts[2]) && strpos($parts[2], ' ') !== false) {
        $parts2 = explode(' ', $parts[2]);
		$parts[2] = $parts2[0];
    }
    return (isset($parts[2]) && isinteger($parts[2]*1) && (int)$parts[2] >= 1 && (int)$parts[2] <= 31 ? $parts[2] : NAN);
}

// Calculate number of unique values from an array of values
function unique()
{
    $args = func_get_args();
    if (isset($args[0]) && is_array($args[0])) {
        $args = $args[0];
    }
    return count(array_unique($args));
}

// Calculate standard deviation from an array of numerical values
function stdev()
{
	$std = func_get_args();
	switch (func_num_args())
	{
		case 0:
			//trigger_error('median() requires at least one parameter',E_USER_WARNING);
			return NAN;
		case 1:
			// Fall through
			if (is_array($std)) {
				$std = array_pop($std);
			}
		default:
			if (!is_array($std)) {
				return NAN;
			}
			// Make sure all are numbers
			foreach ($std as $argnum=>$arg)
			{
				// Trim it first
				$std[$argnum] = $arg = trim($arg);
				// Make sure it's a number, else remove it
				if (!is_numeric($arg)) unset($std[$argnum]);
			}
			// Stdev of one number or no numbers is undefined
			if (count($std) <= 1) return NAN;
			sort($std);
			$total = 0;
			// Count array elements
			$count_std = count($std);
			foreach ($std as $val) $total += $val;
			$mean = $total/$count_std;
			$sum = 0;
			foreach ($std as $val) $sum += pow(($val-$mean),2);
			$var = sqrt($sum/($count_std-1));
			return $var;
			break;
	}
}

// Calculate the percentile of numerical array (array must already be numerically sorted).
// Uses "continuous sample quantile - type 7", which is the default for R and Microsoft Excel.
function percentile($data=array(), $percentile=0)
{
	if (0 < $percentile && $percentile < 1) {
		$p = $percentile;
	} else if (1 < $percentile && $percentile <= 100) {
		$p = $percentile * .01;
	} else {
		return "";
	}

	// Make sure all are numbers
	foreach ($data as $key=>$val)
	{
		// Trim it first
		$data[$key] = $val = trim($val);
		// Make sure it's a number, else remove it
		if (!is_numeric($val)) unset($data[$key]);
	}

	$count = count($data);
	$allindex = ($count - 1) * $p;
	$intvalindex = intval($allindex);
	$floatval = $allindex - $intvalindex;
	sort($data);
	if (!is_float($floatval)) {
		$result = $data[$intvalindex];
	} else {
		if ($count > $intvalindex+1) {
			$result = $floatval*($data[$intvalindex+1] - $data[$intvalindex]) + $data[$intvalindex];
		} else {
			$result = $data[$intvalindex];
		}
	}
	return $result;
}

// Return true if value matches a specific Missing Data Code, else false
function hasmissingcode($val, $codes)
{
	global $missingDataCodes;
	// If null, return false
	if ($val === null || $codes === null) return false;
	// Make sure it's a string
	$val .= "";
	$codes .= "";
	// If blank, then return false
	if ($val == "") return false;
    // If value not a missing data code, return false
    if (!isset($missingDataCodes[$val])) return false;
    // Split into array and check each
    $codesArray = explode(',', str_replace(' ', '', $codes));
    foreach ($codesArray as $code) {
        if ($code == $val) return true;
    }
    return false;
}

// Return true if value is a Missing Data Code, else false
function ismissingcode($val)
{
	global $missingDataCodes;
	// If null, return false
	if ($val === null) return false;
	// Make sure it's a string
	$val .= "";
	// If blank, then return false
	if ($val == "") return false;
	// If a missing code, then return true
	return isset($missingDataCodes[$val]);
}

// Return true if value is blank/null or is a Missing Data Code, else false
function isblankormissingcode($val)
{
	global $missingDataCodes;
	// If null, return true
	if ($val === null) return true;
	// Make sure it's a string
	$val .= "";
	// If blank, then return true
	if ($val == "") return true;
	// If a missing code, then return true
	return isset($missingDataCodes[$val]);
}

// Return true if value is blank/null and not a Missing Data Code, else false
function isblanknotmissingcode($val)
{
	global $missingDataCodes;
	// If null, return true
	if ($val === null) return true;
	// Make sure it's a string
	$val .= "";
	// If blank, then return true
	if ($val == "") return true;
	// If a missing code, then return false
	return !isset($missingDataCodes[$val]);
}

// Text Calculation
function calctext($logic)
{
    return $logic;
}

// Date Calculation
function calcdate($d1, $offset, $unit='d', $datatype_return='date')
{
	global $missingDataCodes;
	// Make sure Units are provided and that dates are trimmed
	if ($unit == null || !is_numeric($offset) || is_nan($offset)) return NAN;
	// $offset = (int)$offset;
	$d1 = trim($d1);
	// Missing data codes
	if (isset($missingDataCodes) && !empty($missingDataCodes)) {
		if ($d1 != '' && isset($missingDataCodes[$d1])) $d1 = '';
	}
	if (strtolower($d1) === "today") $d1 = TODAY; elseif (strtolower($d1) === "now") $d1 = NOW;
	// Determine data type of field ("date", "time", "datetime", or "datetime_seconds")
	$numcolons = substr_count($d1, ":");
	if ($numcolons == 1) {
	    $datatype = "datetime";
	} else if ($numcolons > 1) {
		$datatype = "datetime_seconds";
	} else {
		$datatype = "date";
	}
	// If a date[time][_seconds] field, then ensure it has dashes
	if (substr($datatype, 0, 4) == "date" && strpos($d1, "-") === false) {
		return NAN;
	}
	// Make sure the date/time values aren't empty
	if ($d1 == "" || $d1 == null) {
		return NAN;
	}
	// Separate time if datetime or datetime_seconds
	$d1b = explode(" ", $d1);
	// Split into date and time (in units of seconds)
	$d1 = $d1b[0];
	$timeComp = $d1b[1] ?? "00:00:00";
    if ($datatype == "datetime") {
	    $timeComp .= ":00";
	}
	// Separate pieces of date component
	$dt1 = explode("-", $d1);
	$yyyy = $dt1[0];
	$mm = $dt1[1];
	$dd = $dt1[2];
	// Add the offset
	if ($unit == "s") {
		$time_sec = $offset;
	} else if ($unit == "m") {
 		$time_sec = $offset*60;
 	} else if ($unit == "h") {
 		$time_sec = $offset*3600;
 	} else if ($unit == "d") {
 		$time_sec = $offset*86400;
 	} else if ($unit == "M") {
 		$time_sec = $offset*2630016;
 	} else if ($unit == "y") {
 		$time_sec = $offset*31556952;
 	} else {
 	    return NAN;
 	}
	if ($datatype_return == "datetime") {
	    $dateformat = "Y-m-d H:i";
	} elseif ($datatype_return == "datetime_seconds") {
	    $dateformat = "Y-m-d H:i:s";
	} else {
	    $dateformat = "Y-m-d";
	}
    // For most accurate results, temporarily convert to UTC timezone
    $originalDate = new DateTime("{$yyyy}-{$mm}-{$dd}T{$timeComp}", new DateTimeZone("UTC"));
    //print_dump($originalDate);
    $originalDate->modify(($time_sec >= 0 ? "+" : "") . (string)round($time_sec) . " seconds");
    //print_dump($originalDate);
    $resultDate = $originalDate->format($dateformat);
    // Return result
	return $resultDate;
}

// Return the number of days in a given month YYYY-MM
function getCalDaysInMonth($month, $year)
{
    $cal_days_in_month = false;
    if (function_exists('cal_days_in_month') && isinteger($year) && isinteger(ltrim($month,"0")) && $year != '0' && $month >= 1 && $month <= 12) {
        try {
            $cal_days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        }
        catch (ValueError $e) { $cal_days_in_month = 28; }
        catch (Throwable $e)  { $cal_days_in_month = 28; }
    }
    if (!$cal_days_in_month) $cal_days_in_month = 28;
    return $cal_days_in_month;
}

// Date Differencing Functions
function datediff($d1="", $d2="", $unit=null, $returnSigned=false, $returnSigned2=false)
{
	global $missingDataCodes;
	// Make sure Units are provided and that dates are trimmed
	if ($unit == null) return NAN;
	$d1 = trim($d1);
	$d2 = trim($d2);
	// Missing data codes
	if (isset($missingDataCodes) && !empty($missingDataCodes)) {
		if ($d1 != '' && isset($missingDataCodes[$d1])) $d1 = '';
		if ($d2 != '' && isset($missingDataCodes[$d2])) $d2 = '';
	}
	// If ymd, mdy, or dmy is used as the 4th parameter, then assume user is using Calculated field syntax
	// and assume that returnSignedValue is the 5th parameter.
	if (in_array(strtolower(trim($returnSigned)), array('ymd', 'dmy', 'mdy'))) {
		$returnSigned = $returnSigned2;
	}
	// Initialize parameters first
	if (strtolower($d1) === "today") $d1 = TODAY; elseif (strtolower($d1) === "now") $d1 = NOW;
	if (strtolower($d2) === "today") $d2 = TODAY; elseif (strtolower($d2) === "now") $d2 = NOW;
	$d1isToday = ($d1 == TODAY);
	$d2isToday = ($d2 == TODAY);
	$d1isNow = ($d1 == NOW);
	$d2isNow = ($d2 == NOW);
	$returnSigned = ($returnSigned === true || $returnSigned === 'true');
	// Determine data type of field ("date", "time", "datetime", or "datetime_seconds")
	$format_checkfield = ($d1isToday ? $d2 : $d1);
	$numcolons = substr_count($format_checkfield, ":");
	if ($d1isNow || $d2isNow) {
		 $datatype = "datetime_seconds";
		if ($numcolons == 1) {
			if ($d1isNow) {
				if ($d2 != '') $d2 .= ":00";
			} else {
				if ($d1 != '') $d1 .= ":00";
			}
		} else if ($numcolons == 0) {
			if ($d1isNow) {
				if ($d2 != '') $d2 .= " 00:00:00";
			} else {
				if ($d1 != '') $d1 .= " 00:00:00";
			}
		}
        if ($d1isNow) {
            if (strlen($d2) == 8) {
                $d2 = TODAY." ".$d2;
            }
        } else {
            if (strlen($d1) == 8) {
                $d1 = TODAY." ".$d1;
            }
        }
	} elseif ($numcolons == 1) {
		if (strpos($format_checkfield, "-") !== false) {
			$datatype = "datetime";
		} else {
			$datatype = "time";
		}
	} else if ($numcolons > 1) {
		if (strpos($format_checkfield, "-") !== false) {
			$datatype = "datetime_seconds";
		} else {
			$datatype = "time";
		}
	} else {
		$datatype = "date";
	}
	// Make sure both values are same length/datatype
	if (strlen($d1) != strlen($d2)) {
        if (strlen($d1) > strlen($d2) && $d2 != '') {
            if (strlen($d1) == 16) {
                if (strlen($d2) == 10) $d2 .= " 00:00";
                $datatype = "datetime";
				$d2isNow = false;
                $d2isToday = (left($d2, 10) == TODAY);
            } else if (strlen($d1) == 19) {
                if (strlen($d2) == 10) $d2 .= " 00:00:00";
                else if (strlen($d2) == 16) $d2 .= ":00";
                $datatype = "datetime_seconds";
				$d2isNow = false;
                $d2isToday = (left($d2, 10) == TODAY);
			}
        } else if (strlen($d2) > strlen($d1) && $d1 != '') {
            if (strlen($d2) == 16) {
                if (strlen($d1) == 10) $d1 .= " 00:00";
                $datatype = "datetime";
				$d1isNow = false;
                $d1isToday = (left($d1, 10) == TODAY);
			} else if (strlen($d2) == 19) {
                if (strlen($d1) == 10) $d1 .= " 00:00:00";
                else if (strlen($d1) == 16) $d1 .= ":00";
                $datatype = "datetime_seconds";
				$d1isNow = false;
                $d1isToday = (left($d1, 10) == TODAY);
			}
        }
        $numcolons = max(substr_count($d1, ":"), substr_count($d2, ":"));
    }
	// TIME only
	if ($datatype == "time" && !$d1isToday && !$d2isToday) {
		if ($d1isNow) {
			$d2 = "$d2:00";
			$d1 = substr($d1, -8);
		} elseif ($d2isNow) {
			$d1 = "$d1:00";
			$d2 = substr($d2, -8);
		}
		// Return in specified units
		return secondDiff(timeToSeconds($d1),timeToSeconds($d2),$unit,$returnSigned);
	}
	// DATE, DATETIME, or DATETIME_SECONDS
	// If using 'today' for either date, then set format accordingly
	if (($d1isToday || $d2isToday) && $datatype == "time") {
        return NAN;
	}
	// If a date[time][_seconds] field, then ensure it has dashes
	if (substr($datatype, 0, 4) == "date" && (strpos($d1, "-") === false || strpos($d2, "-") === false)) {
		return NAN;
	}
	// Make sure the date/time values aren't empty
	if ($d1 == "" || $d2 == "" || $d1 == null || $d2 == null) {
		return NAN;
	}
	// Separate time if datetime or datetime_seconds
	$d1b = explode(" ", $d1);
	$d2b = explode(" ", $d2);
	// Split into date and time (in units of seconds)
	$d1 = $d1b[0];
	$d2 = $d2b[0];
	$d1sec = (!empty($d1b[1])) ? timeToSeconds($d1b[1]) : 0;
	$d2sec = (!empty($d2b[1])) ? timeToSeconds($d2b[1]) : 0;
    // Deal with DST, if begin date or result date differs in DST vs Standard Time
    $dstOffset = 0;
//    $d1isDST = isDST($d1 . (isset($d1b[1]) ? " ".$d1b[1] : " 00:00"));
//    $d2isDST = isDST($d2 . (isset($d2b[1]) ? " ".$d2b[1] : " 00:00"));
//    if ($d1isDST != $d2isDST && in_array($unit, ["h", "m", "s"])) {
//        $dstOffset = 3600 * ($d1isDST && !$d2isDST ? -1 : 1);
//    }
	// Separate pieces of date component
	$dt1 = explode("-", $d1);
	$dt2 = explode("-", $d2);
    // For most accurate results, temporarily convert to UTC timezone, then switch back later
    $timezone = date_default_timezone_get();
    date_default_timezone_set('UTC');
	// Convert the dates to seconds (conversion varies due to dateformat)
	$dat1 = mktime(0,0,0,(int)$dt1[1],(int)$dt1[2],(int)$dt1[0]) + $d1sec;
	$dat2 = mktime(0,0,0,(int)$dt2[1],(int)$dt2[2],(int)$dt2[0]) + $d2sec;
    // Get the result and switch back to true timezone
    date_default_timezone_set($timezone);
	// Get the difference in seconds
	$sec = $dat2 - $dat1 - $dstOffset;
	if (!$returnSigned) $sec = abs($sec);
	// Return in specified units
	if ($unit == "s") {
		return $sec;
	} else if ($unit == "m") {
		return $sec/60;
	} else if ($unit == "h") {
		return $sec/3600;
	} else if ($unit == "d") {
		return ($datatype == "date" ? round($sec/86400) : $sec/86400);
	} else if ($unit == "M") {
		return $sec/2630016; // Use 1 month = 30.44 days
	} else if ($unit == "y") {
		return $sec/31556952; // Use 1 year = 365.2425 days
	}
	return NAN;
}

// Return array of two booleans regarding if the string contains the datediff() function with "today" or "now", respectively.
function containsDatediffWithTodayOrNow($string)
{
    $ddWithToday = preg_match("/(\bdatediff\s*\(.*)([\"'])(today)([\"'])(\s*,.+\))/", $string) == '1';
    $ddWithNow = preg_match("/(\bdatediff\s*\(.*)([\"'])(now)([\"'])(\s*,.+\))/", $string) == '1';
    return [$ddWithToday, $ddWithNow];
}

// Convert military time to seconds (i.e. number of seconds since midnight)
function timeToSeconds($time) {
	if (strpos($time, ":") === false) {
		return NAN;
	}
	$timearray = explode(":", $time);
	return ($timearray[0]*3600) + ($timearray[1]*60) + (!isset($timearray[2]) ? 0 : $timearray[2]*1);
}

// Return the difference of two number values in desired units converted from seconds
function secondDiff($time1,$time2,$unit,$returnSigned) {
	$sec = $time2-$time1;
	if (!$returnSigned) $sec = abs($sec);
	// Return in specified units
	if ($unit == "s") {
		return $sec;
	} else if ($unit == "m") {
		return $sec/60;
	} else if ($unit == "h") {
		return $sec/3600;
	} else if ($unit == "d") {
		return $sec/86400;
	} else if ($unit == "M") {
		return $sec/2630016; // Use 1 month = 30.44 days
	} else if ($unit == "y") {
		return $sec/31556952; // Use 1 year = 365.2425 days
	}
	return NAN;
}

// Find min of numbers (each used as parameter or all values passed as an array)
function minRC()
{
	$arg_list = func_get_args();
    if (is_array($arg_list[0])) $arg_list = $arg_list[0];
	foreach ($arg_list as $argnum=>$arg)
	{
		// Trim it first
		$arg_list[$argnum] = $arg = trim($arg);
		// Make sure it's a number, else remove it
		if (!is_numeric($arg)) unset($arg_list[$argnum]);
	}
	return (empty($arg_list) ? NAN : min($arg_list));
}

// Find max of numbers (each used as parameter or all values passed as an array)
function maxRC()
{
	$arg_list = func_get_args();
    if (is_array($arg_list[0])) $arg_list = $arg_list[0];
	foreach ($arg_list as $argnum=>$arg)
	{
		// Trim it first
		$arg_list[$argnum] = $arg = trim($arg);
		// Make sure it's a number, else remove it
		if (!is_numeric($arg)) unset($arg_list[$argnum]);
	}
	return (empty($arg_list) ? NAN : max($arg_list));
}

// Redefine pow() to deal with negatives better for fractional exponents in calculations - e.g. (-8)^(1/3) would normally return '2'
function powRC($base, $exponent)
{
    if (!is_numeric($base) || !is_numeric($exponent)) {
        return NAN;
    }
	if ($base < 0) {
		if (abs($exponent) < 1) {
			//we're calculating nth root of $base, where n === 1/$exponent
			if ((1 / $exponent) % 2 === 0) {
				//nth root of a negative number is imaginary when n is even, we could return
				//a string like "123i" but this would completely mess up further computation
				return NAN;
			}/*else if (1 / $exponent % 2 !== 0)*/
			//nth root of a negative number when n is odd
			return -pow(abs($base), $exponent);
		}
	}/*else if ($base >=0)*/
	//run the original method, nothing will go wrong
	return pow($base, $exponent);
}

// Truncate text with ellipsis in middle
function truncateTextMiddle($this_label, $max_length=50, $chars_at_end=15) {
    $encoding = mb_detect_encoding($this_label);
    if ($encoding === false) $encoding = 'UTF-8'; // Fallback to UTF-8 if detection fails
    if (mb_strlen($this_label, $encoding) > $max_length) {
        $this_label = rtrim(mb_substr($this_label, 0, $max_length-$chars_at_end-2, $encoding)) . "..." . ltrim(mb_substr($this_label, -1*$chars_at_end, null, $encoding));
    }
    return $this_label;
}

// LEFT substring function
function left($str, $charlength) {
	$str = $str."";
	if (!isinteger($charlength) || $charlength < 1) return "";
	return mid($str, 1, $charlength);
}

// RIGHT substring function
function right($str, $charlength) {
	$str = $str."";
	$strlen = strlen($str);
	if (!isinteger($charlength) || $charlength < 1 || $charlength > $strlen) return "";
	return mid($str, $strlen+1-$charlength, $charlength);
}

// MID substring function
function mid($str, $start, $charlength) {
	$str = $str."";
	if (!isinteger($start) || $start < 1) return "";
	if (!isinteger($charlength) || $charlength < 1) return "";
	return substr($str, $start-1, $charlength);
}

// FIND string function
function find($needle, $haystack) {
    $pos = strpos(strtolower($haystack.""), strtolower($needle.""));
	return ($pos === false ? 0 : $pos+1);
}

// LENGTH string function
function length($str) {
	return strlen($str."");
}

// CONCAT string function
function concat() {
	$all = "";
	$args = func_get_args();
	$items = count($args);
	for ($i = 0; $i < $items; $i++) {
		$all .= $args[$i]."";
	}
	return $all;
}

// CONCAT_WS string function
function concat_ws() {
	$args = func_get_args();
	if (count($args) == 0) {
		return "";
	}
	$separator = $args[0];
	return join($separator, array_filter(array_slice($args, 1), function($arg) { return ($arg !== "" && !is_nan_null($arg)); }));
}

// UPPER string function
function upper($str) {
	return strtoupper($str."");
}

// LOWER string function
function lower($str) {
	return strtolower($str."");
}

// Replace an HTML link with a plain text URL (using the "src" attribute of the HTML tag)
function replaceHtmlLinkWithUrl($html, $excludeLinkLabelForSurveyLinks=false)
{
    $foundMatch = preg_match_all('/<a[^>]+href\s*=\s*([\'"])(?<href>.+?)\1[^>]*>(.*)<\/a>/i', $html, $matches, PREG_PATTERN_ORDER);
    if ($foundMatch === false) return $html;
    // Loop through results to reformat the link to be: Label (URL)
    foreach ($matches[0] as $key=>$this_match) {
        $this_link = trim($matches['href'][$key]);
        // If this is a survey link or survey queue link (probably via [survey-link] or [survey-queue-link]), include link label or not? Do not include it for SMS.
        if ($excludeLinkLabelForSurveyLinks && Survey::isSurveyLink($this_link)) {
            $matches['href'][$key] = $this_link . " ";
        } else {
            // If link and text are virtually identical, then don't replace it
            if (rtrim(str_ireplace(["https://", "http://", "www."], [""], $this_link),"/") != rtrim(str_ireplace(["https://", "http://", "www."], [""], $matches[3][$key]),"/")) {
                $matches['href'][$key] = $matches[3][$key] . " ($this_link)";
            }
        }
    }
    $html = str_replace($matches[0], $matches['href'], $html);
    return $html;
}

// Return boolean if all the values in an array are integers
function arrayHasOnlyInts($array)
{
    foreach ($array as $value)
    {
        if (!isinteger($value))
        {
             return false;
        }
    }
    return true;
}

// Return boolean if all the values in an array are numbers
function arrayHasOnlyNums($array)
{
    foreach ($array as $value)
    {
        if (!isnumber($value))
        {
             return false;
        }
    }
    return true;
}

// Convert URLs and email addresses into clickable HTML links
function linkify($string)
{
    if (strpos($string, "http:") !== false || strpos($string, "https:") !== false) {
        // Pre-format HTML links so that we ignore them
        $string = preg_replace('/\b(href\s*=\s*")(https?)(:\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ims', '-----REDCAP-LINK2$2$3$4', $string);
        $string = preg_replace('/\b(href\s*=\s*\')(https?)(:\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ims', '-----REDCAP-LINK3$2$3$4', $string);
        // Convert URLs to HTML links
        $string = preg_replace('/(\b(https?):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ims', '-----REDCAP-LINK1$1', $string);
        $string = preg_replace('/((-----REDCAP-LINK1https?):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ims', '<a href="$1" class="rc-url-to-link" target="_blank">$1</a>', $string);
        // Undo replacements
        $string = str_replace("-----REDCAP-LINK1", "", $string);
        $string = str_replace("-----REDCAP-LINK2", "href=\"", $string);
        $string = str_replace("-----REDCAP-LINK3", "href='", $string);
    }
    // Convert email addresses to mailto links (but not if they already exist as an HTML mailto link)
    if (strpos($string, "@") !== false) {
		$string = preg_replace_callback("/([^@\<\>\s]*@[^@\<\>\s]*\.[^@\<\>\s]*)/", "linkify_mailto_callback", $string);
    }
    return $string;
}
function linkify_mailto_callback($matches)
{
    $string = $matches[1];
    if (strpos($string, "mailto:") === false) {
        // If contains a period on end, remove it
        $period = '';
        if (substr($string, -1) == '.') {
            $string = substr($string, 0, -1);
            $period = '.';
        }
        // Reformat as link
        $string = '<a href="mailto:'.$string.'" class="rc-url-to-link" target="_blank">'.$string.'</a>'.$period;
    }
    return $string;
}

// Determines the field root, suffix value, and length for padding.
function determine_repeat_parts($field_name) {
    // See if it ends in digits
    $re = "/^(.+)(\\d+)?$/U";
    preg_match($re, $field_name, $matches);
    if (isset($matches[2])) {
        $match = $matches[1];
        // Determine the integer value and how many characters it is counter is (e.g. 001 vs 01 vs 1)
        $root = $matches[1];
        $suffix_value = intval($matches[2]);
        $suffix_length = strlen($matches[2]);
    } else {
        $root = $matches[1] . '_'; // The entire field + a spacer
        $suffix_value = 1;         // A blank value is considered 1
        $suffix_length = 1;        // No padding
    }
    return array($root, $suffix_value, $suffix_length);
}

// Enable an auto-appearing button to allow users to scroll to top of page
function outputButtonScrollToTop()
{
    loadJS('Libraries/floating-totop-button.min.js');
    loadCSS('floating-totop-button.min.css');
    ?>
    <script type="text/javascript">
        $(function(){
            $("body").toTopButton({ imagePath: app_path_images+'floating-totop' });
            $('.to-top-button').removeClass('hide'); // Remove "hide" class due to conflict with Bootstrap class
            $('.to-top-button img').attr('alt', '<?=RCView::tt_js('global_247')?>').attr('title', '<?=RCView::tt_js('global_247')?>');
        });
    </script>
    <?php
}

// Calc-related function that might be used when a logic/calc string is an empty string
function returnEmpty()
{
	return "";
}

// Returns boolean regarding whether we are viewing a survey page
function isSurveyPage()
{
    return (defined("PAGE") && PAGE == 'surveys/index.php' && isset($_GET['s']));
}

// Returns boolean regarding whether we are viewing a data entry form
function isDataEntryPage()
{
    return (defined("PAGE") && PAGE == 'DataEntry/index.php' && isset($_GET['id']) && isset($_GET['event_id']) && isset($_GET['page']));
}

// Return boolean if a URL or filename ends with a known video file extension
function hasVideoExtension($filename)
{
    // List of known video file extensions
    $videoExtensions = [
        '.mp4', '.avi', '.mov', '.wmv', '.flv', '.mkv', '.webm',
        '.mpg', '.mpeg', '.3gp', '.m4v', '.ogg', '.ogv', '.qt'
    ];
    // Convert the filename to lowercase for case-insensitive comparison
    $lowerCaseFilename = strtolower($filename);
    // Check if the filename ends with any of the known video extensions
    foreach ($videoExtensions as $extension) {
        if (substr($lowerCaseFilename, -strlen($extension)) === $extension) {
            return true;
        }
    }
    return false;
}

## LEGACY FUNCTIONS TO MAINTAIN IN CASE PLUGIN DEVELOPERS HAVE USED THEM
function getIpAddress() { return System::clientIpAddress(); }
function removeBOMfromUTF8() { $args = func_get_args(); return call_user_func_array('removeBOM', $args); }
function prep() { $args = func_get_args(); return call_user_func_array('db_escape', $args); }
function cleanHtml() { $args = func_get_args(); return call_user_func_array('js_escape', $args); }
function cleanHtml2() { $args = func_get_args();  return call_user_func_array('js_escape2', $args); }
function callJSfile() { $args = func_get_args();  return call_user_func_array('loadJS', $args); }
function form_renderer() { $args = func_get_args();  return call_user_func_array('DataEntry::renderForm', $args); }
function getAutoId() { $args = func_get_args();  return call_user_func_array('DataEntry::getAutoId', $args); }

// Redact an API token (for logging)
function redactToken($token) {
	if (strlen($token) < 16) return "???";
	return left($token, 3)."...".right($token,2);
}

/**
 * Adds a flash message to the session. Flash messages are meant to be used once and then removed.
 * 
 * @param string $key The key under which to store the flash message.
 * @param mixed $value The message or data to store.
 */
function flash($key, $value) {
    SessionData::getInstance()->flash($key, $value);
}

/**
 * Retrieves and removes an old value from the session. Typically used for form values to maintain state across requests.
 * 
 * @param string $key The key of the data to retrieve.
 * @param mixed $default The default value to return if the key is not found.
 * @return mixed The data from the session, or default value if not set.
 */
function old($key, $default = null) {
    return SessionData::getInstance()->old($key, $default);
}

/**
 * get the previous URL
 */
function previousURL() {
	return $_SERVER['HTTP_REFERER'];
}
function getAllTranslatedDays() {
    return ["Sunday" => RCView::tt('calendar_widget_month_day_long_sun'),
            "Monday" => RCView::tt('calendar_widget_month_day_long_mon'),
            "Tuesday" => RCView::tt('calendar_widget_month_day_long_tues'),
            "Wednesday" => RCView::tt('calendar_widget_month_day_long_wed'),
            "Thursday" => RCView::tt('calendar_widget_month_day_long_thurs'),
            "Friday" => RCView::tt('calendar_widget_month_day_long_fri'),
            "Saturday" => RCView::tt('calendar_widget_month_day_long_sat')];
}
function getTranslatedDayText($day, $format = false) {
    $day_texts = getAllTranslatedDays();

    $translated_day = "";
    if (isset($day_texts[$day])) {
        $translated_day = $day_texts[$day];

        if ($format == true && ($day == "Saturday" || $day == "Sunday")) {
            $translated_day = "<span style='color:red;'>$translated_day</span>";
        }
    }

    return $translated_day;
}

function getTranslatedShortDayText($day) {
    $day_texts = ["Sun" => RCView::tt('calendar_table_16'),
                "Mon" => RCView::tt('calendar_table_17'),
                "Tue" => RCView::tt('calendar_table_18'),
                "Wed" => RCView::tt('calendar_table_19'),
                "Thu" => RCView::tt('calendar_table_20'),
                "Fri" => RCView::tt('calendar_table_21'),
                "Sat" => RCView::tt('calendar_table_22')];

    $translated_day = "";
    if (isset($day_texts[$day])) {
        $translated_day = $day_texts[$day];
    }

    return $translated_day;
}

function getTranslatedShortMonthText($month) {
    $month_texts = ["Jan" => RCView::tt('calendar_widget_month_jan'),
                    "Feb" => RCView::tt('calendar_widget_month_feb'),
                    "Mar" => RCView::tt('calendar_widget_month_mar'),
                    "Apr" => RCView::tt('calendar_widget_month_apr'),
                    "May" => RCView::tt('calendar_widget_month_may'),
                    "Jun" => RCView::tt('calendar_widget_month_jun'),
                    "Jul" => RCView::tt('calendar_widget_month_jul'),
                    "Aug" => RCView::tt('calendar_widget_month_aug'),
                    "Sep" => RCView::tt('calendar_widget_month_sep'),
                    "Oct" => RCView::tt('calendar_widget_month_oct'),
                    "Nov" => RCView::tt('calendar_widget_month_nov'),
                    "Dec" => RCView::tt('calendar_widget_month_dec')];

    $translated_month = "";
    if (isset($month_texts[$month])) {
        $translated_month = $month_texts[$month];
    }

    return $translated_month;
}

// Function to obtain current or lowest Arm number for MyCap API call ProjectHandler::getStudyConfig
function getArmByEventId($event_id)
{
    $arm = "";
	// If we have event_id
	if (isset($event_id) && isinteger($event_id)) {
		$arm = db_result(db_query("select arm_num from redcap_events_arms a, redcap_events_metadata e where a.arm_id = e.arm_id and e.event_id = " .$event_id), 0);
	}
	// Just in case arm is blank somehow
	if ($arm == "" || !isinteger($arm)) {
		$arm = 1;
	}
	return $arm;
}