<?php


/**
 * Logging Class
 * Contains methods used with regard to logging
 */
class Logging
{
	// Set up array of pages to ignore for logging page views and counting page hits or IP hashes
	public static $noCountPages = array(
		"DataEntry/auto_complete.php", // "DataEntry/search.php",
		"ControlCenter/report_site_stats.php", "Calendar/calendar_popup_ajax.php",
		"Reports/report_builder_ajax.php", "ControlCenter/check.php", "DataEntry/image_view.php", "ProjectGeneral/project_stats_ajax.php",
		"SharedLibrary/image_loader.php", "DataExport/plot_chart.php", "Surveys/theme_view.php", "Design/logic_validate.php", 
		"Design/logic_field_suggest.php", "Messenger/messenger_ajax.php", "DataEntryController:openSurveyValuesChanged", "DataEntry/web_service_auto_suggest.php",
		"ProjectGeneral/project_menu_collapse.php", "ParcelController:list", "Composables/index.es.js.php"
	);
	
	// Set user first/last activity timestamp
	public static function setUserActivityTimestamp()
	{
		global $user_firstactivity;
		// Make sure we have valid userid
		$user_id = defined("USERID") ? USERID : "";
        // Ignore non-real users
		if ($user_id == "SYSTEM" || $user_id == "" || strpos($user_id, "[") !== false) return;
		// SET FIRST ACTIVITY TIMESTAMP
		// If this is the user's first activity to be logged in the log_event table, then log the time in the user_information table
		if ($user_firstactivity == "") {
			$sql = "update redcap_user_information set user_firstactivity = '".NOW."'
					where username = '".db_escape($user_id)."' and user_firstactivity is null and user_suspended_time is null";
			db_query($sql);
		}
		// SET LAST ACTIVITY TIMESTAMP FOR USER
		// (but NOT if they are suspended - could be confusing if last activity occurs AFTER suspension)
		$sql = "update redcap_user_information set user_lastactivity = '".NOW."'
				where username = '".db_escape($user_id)."' and user_suspended_time is null";
		db_query($sql);
	}
	
	// Set project's last activity timestamp
	public static function setProjectActivityTimestamp($project_id=null)
	{
		if (!isinteger($project_id) || $project_id < 1) return;
        // Use the current timestamp since NOW is not so accurate because it represents the script's begin time
        $trueNow = date('Y-m-d H:i:s');
        // Determine if this is an export or file download event
        $isExportEvent = (
            // UI data export page or download file page
            (defined("PAGE") && (PAGE == "DataExport/data_export_ajax.php" || in_array(PAGE, System::$fileDownloadPages)))
            // API data export, file download, or general export/read-only processes - Data, report, logging, arm, DAG, event, user, file, instrument, PDF exports, etc.
            // (missing "action" parameter assumes "export")
            || (defined("API") && !isset($_POST['data'])  && ((!isset($_POST['action']) || $_POST['action'] == "export")
                && isset($_POST['content']) && in_array($_POST['content'], System::$apiExportMethods))
            )
        );
        $last_logged_event_exclude_exports = $isExportEvent ? "" : ", last_logged_event_exclude_exports = '$trueNow'";
        // Update redcap_projects
		$sql = "update redcap_projects set last_logged_event = '$trueNow' $last_logged_event_exclude_exports where project_id = $project_id";
		db_query($sql);
	}
	
	// Logs an action. Returns log_event_id from db table.
	public static function logEvent($sql, $table, $event, $record, $display, $descrip="", $change_reason="",
									$userid_override="", $project_id_override="", $useNOW=true, $event_id_override=null, $instance=null,
									$bulkProcessing=false)
	{
		global $rc_connection;

		// Log the event in the project's log_event table
		$ts 	 	= ($useNOW && !defined("CRON") ? str_replace(array("-",":"," "), array("","",""), NOW) : date('YmdHis'));
		$page 		= (defined("PAGE") ? PAGE : (defined("PLUGIN") ? "PLUGIN" : ""));
		$userid		= ($userid_override != "" ? $userid_override : (in_array(PAGE, Authentication::$noAuthPages) ? "[non-user]" : (defined("USERID") ? USERID : "")));
		if ($userid == "" && defined("CRON")) $userid = "SYSTEM";
		$ip 	 	= (isset($userid) && System::isSurveyRespondent($userid)) ? "" : System::clientIpAddress(); // Don't log IP for survey respondents
		$event	 	= strtoupper($event);
		$event_id	= (is_numeric($event_id_override) ? $event_id_override : (isset($_GET['event_id']) && is_numeric($_GET['event_id']) ? $_GET['event_id'] : "NULL"));
		$project_id = (is_numeric($project_id_override) ? $project_id_override : (defined("PROJECT_ID") && is_numeric(PROJECT_ID) ? PROJECT_ID : 0));
		$instance   = is_numeric($instance) ? (int)$instance : 1;
		
		// Set instance (only if $instance>1)
		if ($instance > 1) {
			$display = "[instance = $instance]".($display == '' ? '' : ",\n").$display;
		}

		// Query
		$sql = "INSERT INTO ".self::getLogEventTable($project_id)."
				(project_id, ts, user, ip, page, event, object_type, sql_log, pk, event_id, data_values, description, change_reason)
				VALUES ($project_id, $ts, '".db_escape($userid)."', ".checkNull($ip).", '$page', '$event', '$table', ".checkNull($sql).",
				".checkNull($record).", $event_id, ".checkNull($display).", ".checkNull($descrip).", ".checkNull($change_reason).")";
		$q = db_query($sql);
		$log_event_id = ($q ? db_insert_id() : false);

        Records::updateRecordInRecordListCacheBasedOnAction($event, $project_id, $record, $event_id, $bulkProcessing);

		// Return log_event_id PK if true or false if failed
		return $log_event_id;
	}
	
	// Add the total execution time of this PHP script to the current script's row in log_open_requests when this script finishes
	public static function updateLogViewRequestTime()
	{
		if (!defined("LOG_VIEW_REQUEST_ID") || !defined("SCRIPT_START_TIME")) return;
		// Calculate total execution time (rounded to milliseconds)
		$total_time = round((microtime(true) - SCRIPT_START_TIME), 3);
		// Update table
		$sql = "update redcap_log_view_requests set script_execution_time = '$total_time' where lvr_id = " . LOG_VIEW_REQUEST_ID;
		db_query($sql);
        // If connected to a replica, also delete log_view and log_view_request entries as cleanup (they are only needed for real-time viewing of Database Activity Monitor)
        if (defined("LOG_VIEW_REPLICA_REQUEST_ID")) {
            if (defined("LOG_VIEW_REPLICA_ID")) {
                $sql = "delete from redcap_log_view where log_view_id = " . LOG_VIEW_REPLICA_ID; // This will cascade to delete from redcap_log_view_requests too
            } else {
                $sql = "delete from redcap_log_view_requests where lvr_id = " . LOG_VIEW_REPLICA_REQUEST_ID;
            }
            db_query($sql);
        }
	}
	
	// If the current user has any currently running queries in another mysql process, then gather them in an array
	public static function getUserCurrentQueries()
	{
		// Set conditions
		if (!(defined("UI_ID") && is_numeric(UI_ID) && !defined("API") && !defined("CRON")
            && defined("PAGE") && PAGE != 'surveys/index.php' && PAGE != 'ControlCenter/index.php')) {
			return;
		}
		// Get current mysql process id
		$mysql_process_id = db_thread_id();
		// Check the db table in the past hour (max request time)
		$oneHourAgo = date("Y-m-d H:i:s", mktime(date("H")-1,date("i"),date("s"),date("m"),date("d"),date("Y")));
		// Set array and query to see if the current user has any currently running queries
		$sql = "select r.mysql_process_id from redcap_log_view_requests r, redcap_log_view v 
				where v.log_view_id = r.log_view_id and r.script_execution_time is null 
				and r.ui_id = ".UI_ID." and v.ts > '$oneHourAgo' and v.session_id = '".Session::sessionId()."'";
		$sql .= " and v.full_url = '".db_escape(curPageURL())."'";
		$q = db_query($sql);
		if (db_num_rows($q) == 0) return;
		// Loop to gather all mysql process IDs
		$CurrentUserQueries = array();
		while ($row = db_fetch_assoc($q)) {
			if ($row['mysql_process_id'] == $mysql_process_id) continue;
			$CurrentUserQueries[$row['mysql_process_id']] = true;
		}		
		// Gather all existing mysql queries from the process list
		$sql = "show full processlist";
		$q = db_query($sql);
		$GLOBALS['CurrentUserQueries'] = array();
		if (db_num_rows($q) > 0) 
		{
			while ($row = db_fetch_assoc($q)) {
				if ($row['Id'] == $mysql_process_id || !isset($CurrentUserQueries[$row['Id']])) continue;
				$GLOBALS['CurrentUserQueries'][$row['Id']] = $row['Info'];
			}
		}
		if (!empty($GLOBALS['CurrentUserQueries'])) {
			// Set var as a quick-check reference
			$GLOBALS['REDCapCurrentUserHasQueries'] = true;
		}
	}

	// Log page and user info for page being viewed (but only for specified pages)
	public static function logPageView($event="PAGE_VIEW", $userid="", $twoFactorLoginMethod=null, $twoFactorForceLoginSuccess=false, $forceTs=null)
	{
		global $query_array, $custom_report_sql, $Proj, $isAjax, $two_factor_auth_enabled;

		// If using TWO FACTOR AUTH, then don't log "LOGIN_SUCCESS" until we do the second factor
		if ($two_factor_auth_enabled && $event == "LOGIN_SUCCESS" && $twoFactorLoginMethod == null && !$twoFactorForceLoginSuccess) {
			return;
		}

		// If user just logged in, make sure we regenerate their session ID, just in case it was tampered with
		if ($event == "LOGIN_SUCCESS") session_regenerate_id();

		// If a plugin or other page should be excluded from here, then just return
        if (defined("SKIP_LOG_PAGE_VIEW")) return;

		// Set userid as blank if USERID is not defined
		if (!defined("USERID") && $userid == "USERID") $userid = "";

		// If current page view is to be logged (i.e. if not set as noCountPages and is not a survey passthru page)
		// If this is the REDCap cron job, then skip this
		if (!defined('CRON')
            && defined("PAGE") && !in_array(PAGE, self::$noCountPages)
            && !(PAGE == 'surveys/index.php' && isset($_GET['__passthru']) && !(
                // Make sure the Return Code emailing feature is logged
                ($_GET['__passthru'] == "Surveys/email_participant_return_code.php")
                // Make sure all routes at the survey end-point get logged
                || ($_GET['__passthru'] == "index.php" && isset($_GET['route']))
            ))
			&& !(PAGE == 'surveys/index.php' && isset($_GET[Authentication::TWILIO_2FA_SUCCESS_FLAG]))
			&& !(PAGE == 'DataEntry/file_download.php' && isset($_GET['stream']))
		) {
			// Obtain browser info
			$browser = new Browser();
			$browser_name = strtolower($browser->getBrowser());
			$browser_version = isIE11compat() ? '11.0' : $browser->getVersion();
			// Do not include more than one decimal point in version
			if (substr_count($browser_version, ".") > 1) {
				$browser_version_array = explode(".", $browser_version);
				$browser_version = $browser_version_array[0] . "." . $browser_version_array[1];
			}

			// Obtain other needed values
			$ip 	 	= System::clientIpAddress();
			$page 	  	= (defined("PAGE") ? PAGE : "");
			$event	  	= strtoupper($event);
			$project_id = defined("PROJECT_ID") ? PROJECT_ID : "";
			$full_url	= curPageURL();
			$session_id = (!Session::sessionId() ? "" : Session::sessionId());

			// Defaults
			$event_id 	= "";
			$record		= "";
			$form_name 	= "";
			$miscellaneous = "";

			// Check if user's IP has been banned
			Logging::checkBannedIp($ip);
			// Save IP address as hashed value in cache table to prevent automated attacks
			Logging::storeHashedIp($ip);

			// Special logging for certain pages
			if ($event == "PAGE_VIEW") {
				switch (PAGE)
				{
					// Data Quality rule execution
					case "DataQuality/execute_ajax.php":
						$miscellaneous = "// rule_ids = '{$_POST['rule_ids']}'";
						break;
					// External Links clickthru page
					case "ExternalLinks/clickthru_logging_ajax.php":
						$miscellaneous = "// url = " . $_POST['url'];
						break;
					// Survey page
					case "surveys/index.php":
						// Set username and erase ip to maintain anonymity survey respondents
						$ip = "";
						if (isset($_GET['s']))
						{
							$userid = System::SURVEY_RESPONDENT_USERID;
							// Set all survey attributes as global variables
							Survey::setSurveyVals($_GET['s']);
							$event_id = $GLOBALS['event_id'];
							$form_name = $GLOBALS['form_name'];
							// Capture the response_id if we have it
							if (isset($_POST['__response_hash__']) && !empty($_POST['__response_hash__'])) {
								$response_id = Survey::decryptResponseHash($_POST['__response_hash__'], $GLOBALS['participant_id']);
								// Get record name
								$sql = "select r.record from redcap_surveys_participants p, redcap_surveys_response r
										where r.participant_id = p.participant_id and r.response_id = $response_id";
								$q = db_query($sql);
								$record = db_result($q, 0);
								$miscellaneous = "// response_id = $response_id";
							} elseif (isset($GLOBALS['participant_email']) && $GLOBALS['participant_email'] !== null) {
								// Get record name for existing record (non-public survey)
								$sql = "select r.record, r.response_id from redcap_surveys_participants p, redcap_surveys_response r
										where r.participant_id = p.participant_id and p.hash = '".db_escape($_GET['s'])."'
										and p.participant_id = {$GLOBALS['participant_id']}";
								$q = db_query($sql);
								$record = db_result($q, 0, 'record');
								$response_id = db_result($q, 0, 'response_id');
								$miscellaneous = "// response_id = $response_id";
							}
							// If a Post request and is NOT a normal survey page submission, then log the Post parameters passed
							if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['submit-action'])) {
								if ($miscellaneous != "") $miscellaneous .= "\n";
								$miscellaneous .= "// POST = " . print_r($_POST, true);
							}
						}
						// Calendar link
						if (isset($_GET['__calendar']) && !empty($_GET['__calendar'])) {
							$calFeed = Calendar::getFeedAttributes($_GET['__calendar']);
							$project_id = $calFeed['project_id'] ?? null;
							$record = $calFeed['record'] ?? null;
							if (isset($calFeed['userid']) && $calFeed['userid'] != '') {
								$userid = User::getUserInfoByUiid($calFeed['userid'])['username'];
							}
						}
						break;
					// API
					case "API/index.php":
					case "api/index.php":
						// If downloading file, log it
						if ($_SERVER['REQUEST_METHOD'] == 'POST') {
							// Set values needed for logging
							if (isset($_POST['token']) && !empty($_POST['token']))
							{
								$q = db_query("select project_id, username from redcap_user_rights where api_token = '" . db_escape($_POST['token']) . "'");
								$userid = db_result($q, 0, "username");
								$project_id = db_result($q, 0, "project_id");
							} elseif (isset($_GET["content"]) && $_GET["content"] == "mycap" && isset($_POST['par_code'])) {
								$q = db_query("select project_id, record from redcap_mycap_participants where code = '" . db_escape($_POST['par_code']) . "'");
								$record = db_result($q, 0, "record");
								$project_id = db_result($q, 0, "project_id");
							} elseif (isset($_GET["content"]) && $_GET["content"] == "mycap" && isset($_POST['stu_code'])) {
								$q = db_query("select project_id from redcap_mycap_projects where code = '" . db_escape($_POST['stu_code']) . "'");
								$project_id = db_result($q, 0, "project_id");
							}
							$post = $_POST;
							// Remove data from $_POST for logging (if this is an API import)
							if (isset($post['data'])) $post['data'] = '[not displayed]';
							// Log only some info
							$log_full = [ "content", "format", "returnFormat", "playground" ];
							if (isset($post["content"]) && $post["content"] == "externalModule") {
								$log_full[] = "prefix";
								$log_full[] = "action";
							}
							$log_redacted = [ "token" ];
							$miscellaneous = "// API Request: ";
							foreach ($post as $key=>$value) {
								if (in_array($key, $log_full, true) || (isset($_GET["content"]) && $_GET["content"] == "mycap")) {
									$miscellaneous .= "$key = '" . ((is_array($value)) ? implode("', '", $value) : $value) . "'; ";
								}
								else if (in_array($key, $log_redacted, true)) {
									$redacted = redactToken($value);
									$miscellaneous .= "$key = '$redacted'; ";
								}
							}
							$miscellaneous = substr($miscellaneous, 0, -2);
						}
						break;
					// Data history
					case "DataEntry/data_history_popup.php":
						if (isset($_POST['event_id']))
						{
							$form_name = $Proj->metadata[$_POST['field_name']]['form_name'];
							$event_id = $_POST['event_id'];
							$record = $_POST['record'];
							$miscellaneous = "field_name = '" . $_POST['field_name'] . "'";
						}
						break;
					// Send it download
					case "SendIt/download.php":
						// If downloading file, log it
						if ($_SERVER['REQUEST_METHOD'] == 'POST') {
							$miscellaneous = "// Download file (Send-It)";
						}
						break;
					// Send it upload
					case "SendItController:upload":
						// Get project_id
						$fileLocation = (isset($_GET['loc']) ? $_GET['loc'] : 1);
						if ($fileLocation != 1) {
							if ($fileLocation == 2) //file repository
								$query = "SELECT project_id FROM redcap_docs WHERE docs_id = '" . db_escape($_GET['id']) . "'";
							else if ($fileLocation == 3) //data entry form
								$query = "SELECT project_id FROM redcap_edocs_metadata WHERE doc_id = '" . db_escape($_GET['id']) . "'";
							$project_id = db_result(db_query($query), 0);
						}
						// If uploading file, log it
						if ($_SERVER['REQUEST_METHOD'] == 'POST') {
							$miscellaneous = "// Upload file (Send-It)";
						}
						break;
					// Data entry page and other related pages that have the same query string params
					case "DataEntry/index.php":
					case "DataEntry/check_unique_ajax.php":
					case "DataEntry/file_upload.php":
					case "DataEntry/file_download.php":
					case "DataEntry/file_delete.php":
					case "ProjectGeneral/keep_alive.php":
						if (isset($_GET['page'])) {
							$form_name = $_GET['page'];
							$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : getSingleEvent(PROJECT_ID);
							if (isset($_GET['record'])) $record = $_GET['record'];
							elseif (isset($_GET['id'])) $record = $_GET['id'];
                            if (isset($_GET['instance']) && isinteger($_GET['instance'])) $miscellaneous = "instance: ".$_GET['instance'];
						}
						break;
					// PDF form export
					case "PdfController:index":
						if (isset($_GET['page'])) $form_name = $_GET['page'];
						$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : getSingleEvent(PROJECT_ID);
						if (isset($_GET['id'])) $record = $_GET['id'];
						break;
					// Longitudinal grid
					case "DataEntry/record_home.php":
						if (isset($_GET['id'])) $record = $_GET['id'];
						break;
					// Calendar
					case "Calendar/index.php":
						// Obtain mm, dd, yyyy being viewed
                        if (!isset($_GET['year']) || !isinteger($_GET['year'])) {
                            $_GET['year'] = date("Y");
                        }
                        if (!isset($_GET['month']) || !(is_numeric($_GET['month']) && isinteger($_GET['month']*1))) {
                            $_GET['month'] = date("n")+1;
                        }
						$month = $_GET['month'] - 1;
						$year  = $_GET['year'];
						if (isset($_GET['day']) && $_GET['day'] != "") {
							$day = $_GET['day'];
						} else {
							$day = $_GET['day'] = 1;
						}
						$days_in_month = date("t", mktime(0,0,0,$month,1,$year));
						// Set values
						$view = (!isset($_GET['view']) || $_GET['view'] == "") ? "month" : $_GET['view'];
						$miscellaneous = "view: $view\ndates viewed: ";
						switch ($view) {
							case "day":
								$miscellaneous .= "$month/$day/$year";
								break;
							case "week":
								$miscellaneous .= "week of $month/$day/$year";
								break;
							default:
								$miscellaneous .= "$month/1/$year - $month/$days_in_month/$year";
						}
						break;
					// Edoc download
					case "DataEntry/file_download.php":
						$record    = $_GET['record'];
						$event_id  = $_GET['event_id'];
						$form_name = $_GET['page'];
						break;
					// Calendar pop-up
					case "Calendar/calendar_popup.php":
						// Check if has record or event
						if (isset($_GET['cal_id'])) {
							$q = db_query("select record, event_id from redcap_events_calendar where cal_id = '".db_escape($_GET['cal_id'])."'");
							$record   = db_result($q, 0, "record");
							$event_id = db_result($q, 0, "event_id");
						}
						break;
					// Scheduling module
					case "Calendar/scheduling.php":
						if (isset($_GET['record'])) {
							$record = $_GET['record'];
						}
						break;
					// Graphical Data View page
					case "Graphical/index.php":
						if (isset($_GET['page'])) {
							$form_name = $_GET['page'];
						}
						break;
					// Graphical Data View highest/lowest/missing value
					case "DataExport/stats_highlowmiss.php":
						$form_name 	= $_GET['form'];
						$miscellaneous = "field_name: '{$_GET['field']}'\n"
									   . "action: '{$_GET['svc']}'\n"
									   . "group_id: " . (($_GET['group_id'] == "undefined") ? "" : $_GET['group_id']);
						break;
					// Viewing a report
					case "DataExport/report_ajax.php":
						// Report Builder reports
						if (isset($_POST['report_id'])) {
							$report = DataExport::getReports($_POST['report_id']);
							$miscellaneous = "// Report attributes for \"" . $report['title'] . "\" (report_id = {$_POST['report_id']}):\n";
							$miscellaneous .= json_encode($report);
						}
						break;
					// Data comparison tool
					case "DataComparisonController:index":
						if (isset($_POST['record1'])) {
							list ($record1, $event_id1) = explode("[__EVTID__]", $_POST['record1']);
							if (isset($_POST['record2'])) {
								list ($record2, $event_id2) = explode("[__EVTID__]", $_POST['record2']);
								$record = "$record1 (event_id: $event_id1)\n$record2 (event_id: $event_id2)";
							} else {
								$record = "$record1 (event_id: $event_id1)";
							}
						}
						break;
					// File repository and data export docs
					case "FileRepositoryController:download":
						if (isset($_GET['id'])) {
							$miscellaneous = "// Download file from redcap_docs (docs_id = {$_GET['id']})";
						}
						break;
					// Logging page
					case "Logging/index.php":
						if (isset($_GET['record']) && $_GET['record'] != '') {
							$record = $_GET['record'];
						}
						if (isset($_GET['usr']) && $_GET['usr'] != '') {
							$miscellaneous = "// Filter by user name ('{$_GET['usr']}')";
						}
						break;
					// Email Logging page
					case "EmailLoggingController:search":
						$miscellaneous = "// Email Logging search parameters:\n" . json_encode_rc($_POST);
						break;
					// Email Logging page when viewing an individual email
					case "EmailLoggingController:view":
						$emailAttr = Message::getEmailContentByHash($_POST['hash']);
						$event_id = $emailAttr['event_id'];
						$record = $emailAttr['record'];
						$form_name = $emailAttr['instrument'];
						break;
					// Multi-Langauge Management - take note of proj/sys in form_name column
					case "MultiLanguageController:ajax":
						if (isset($_GET["context"]) && in_array($_GET["context"], ["proj","sys"])) {
							$form_name = $_GET["context"];
						}
						break;
				}
			}

			// TWO FACTOR AUTH: Set login method (e.g., SMS) for miscellaneous
			if ($two_factor_auth_enabled && $event == "LOGIN_SUCCESS" && $twoFactorLoginMethod != null) {
				$miscellaneous = $twoFactorLoginMethod;
			}

			// If forcing a specific timestamp, then set it here
			$ts = ($forceTs == null) ? NOW : $forceTs;

			// Do logging
			$sql = "insert into redcap_log_view (ts, user, event, ip, browser_name, browser_version, full_url, page, project_id, event_id,
					record, form_name, miscellaneous, session_id) values ('".db_escape($ts)."', " . checkNull($userid) . ", '".db_escape($event)."', " . checkNull($ip) . ",
					'" . db_escape($browser_name) . "', '" . db_escape($browser_version) . "',
					'" . db_escape($full_url) . "', '".db_escape($page)."', " . checkNull($project_id) . ", " . checkNull($event_id) . ", " . checkNull($record) . ",
					" . checkNull($form_name) . ", " . checkNull($miscellaneous) . ", " . checkNull($session_id) . ")";
			db_query($sql);
			if (!defined("LOG_VIEW_ID")) define("LOG_VIEW_ID", db_insert_id());

            // If currently connected to a read replica, also add to log_view_requests for the replica
            if (isset($GLOBALS['rc_replica_connection'])) {
                db_query($sql);
                if (!defined("LOG_VIEW_REPLICA_ID")) define("LOG_VIEW_REPLICA_ID", db_insert_id());
            }
		}

        // Obtain UI_ID since we don't have it when logging out
		if ($event == "LOGOUT" && !defined("UI_ID")) {
			$userInfo = User::getUserInfo($userid);
			define("UI_ID", $userInfo['ui_id']);
		}

        // Add to log_open_requests table
        $sql = "replace into redcap_log_view_requests (log_view_id, mysql_process_id, php_process_id, is_ajax, is_cron, ui_id)
				values (" . checkNull(defined("LOG_VIEW_ID") ? LOG_VIEW_ID : '') . ", " . checkNull(db_thread_id()) . ", " .
                checkNull(getmypid()) . ", " . ($isAjax ? '1' : '0') . ", " .
                checkNull(defined("CRON") ? '1' : '0') . ", " .
                checkNull(defined("UI_ID") ? UI_ID : '') . ")";
        db_query($sql);
        if (!defined("LOG_VIEW_REQUEST_ID")) define("LOG_VIEW_REQUEST_ID", db_insert_id());

        // If currently connected to a read replica, also add to log_view_requests for the replica
        if (isset($GLOBALS['rc_replica_connection']))
        {
            $sql = "replace into redcap_log_view_requests (log_view_id, mysql_process_id, php_process_id, is_ajax, is_cron, ui_id)
                    values (" . checkNull(defined("LOG_VIEW_REPLICA_ID") ? LOG_VIEW_REPLICA_ID : '') . ", " . checkNull(db_thread_id($GLOBALS['rc_replica_connection'])) . ", " .
                    checkNull(getmypid()) . ", " . ($isAjax ? '1' : '0') . ", " .
                    checkNull(defined("CRON") ? '1' : '0') . ", " .
                    checkNull(defined("UI_ID") ? UI_ID : '') . ")";
            db_query($sql);
            if (!defined("LOG_VIEW_REPLICA_REQUEST_ID")) define("LOG_VIEW_REPLICA_REQUEST_ID", db_insert_id());
        }
	}

	// Count page hits (but not for specified pages, or for AJAX requests, or for survey passthru pages)
	public static function logPageHit()
	{
		global $isAjax;
		if (!defined("CRON") && !$isAjax && defined("PAGE") && !in_array(PAGE, self::$noCountPages)
			&& !(PAGE == 'surveys/index.php' && isset($_GET['__passthru']))
			&& !(PAGE == 'DataEntry/file_download.php' && isset($_GET['stream']))
		) {
			//Add one to daily count
			$ph = db_query("update redcap_page_hits set page_hits = page_hits + 1 where date = CURRENT_DATE and page_name = '" . PAGE . "'");
			//Do insert if previous query fails (in the event of being the first person to hit that page that day)
			if (!$ph || db_affected_rows() != 1) {
				db_query("insert into redcap_page_hits (date, page_name) values (CURRENT_DATE, '" . PAGE . "')");
			}
		}
	}
	
	
	public static function renderLogRow($row, $html_output=true)
	{
		global $lang, $require_change_reason, $event_ids, $Proj, $dq_rules, $table_pk, $DDP;

        // Set CDP or DDP to display in logging if using either
        $ddpText = (is_object($DDP) && DynamicDataPull::isEnabledInSystem() && DynamicDataPull::isEnabled($Proj->project_id)) ? $lang['ws_30'] : $lang['ws_292'];

        // If the username contains a line break, that means we have more than one attributable user, such as "SYSTEM\njon.doe"
        if (isset($row['user']) && strpos($row['user'], "\n") !== false) {
            list ($user1, $user2) = explode("\n", $row['user'], 2);
            $row['user'] = "$user1".($html_output ? "<br>" : " ")."($user2)";
        }

        $id = "";

        if ($row['legacy'])
		{
			// For v2.1.0 and previous
			switch ($row['event'])
			{
				case 'UPDATE':

					$pos_set = strpos($row['sql_log'],' SET ') + 4;
					$pos_where = strpos($row['sql_log'],' WHERE ') - $pos_set;
					$sql_log = trim(substr($row['sql_log'],$pos_set,$pos_where));
					$sql_log = str_replace(",","{DELIM}",$sql_log);

					$pos_id1 = strrpos($row['sql_log']," = '") + 4;
					if (strpos($row['sql_log'],"LIMIT 1") == true) {
						$id = substr($row['sql_log'],$pos_id1,-10);
					} else {
						$id = substr($row['sql_log'],$pos_id1,-1);
					}
					$sql_log_array = explode("{DELIM}",$sql_log);
					$sql_log = '';
					foreach ($sql_log_array as $value) {
						if (substr(trim($value),-4) == 'null') $value = substr($value,0,-4)."''";
						$sql_log .= stripslashes($value) . ",<br>";
					}
					$sql_log = substr($sql_log,0,-5);
					if (strpos($row['sql_log']," redcap_auth ") == true) {
						$event = "<font color=#000066>{$lang['reporting_24']}</font>"; //User updated
					} elseif (strpos($row['sql_log'],"INSERT INTO redcap_edocs_metadata ") == true) {
						$event = "<font color=green>{$lang['reporting_39']}</font><br><font color=#000066>{$lang['reporting_25']}</font>"; //Document uploaded
						$id = substr($id,0,strpos($id,"'"));
						$sql_log = substr($sql_log,0,strpos($sql_log,"="));
					} elseif (strpos($row['sql_log'],"UPDATE redcap_edocs_metadata ") == true) {
						$event = "<font color=red>{$lang['reporting_40']}</font><br><font color=#000066>{$lang['reporting_25']}</font>"; //Document uploaded
						$id = substr($id,0,strpos($id,"'"));
						$sql_log = substr($sql_log,0,strpos($sql_log,"="));
					} else {
						$event = "<font color=#000066>{$lang['reporting_25']}</font>"; //Record updated
					}
					break;

				case 'INSERT':

					$pos1a = strpos($row['sql_log'],' (') + 2;
					$pos1b = strpos($row['sql_log'],') ') - $pos1a;
					$sql_log = trim(substr($row['sql_log'],$pos1a,$pos1b));
					$pos2a = strpos($row['sql_log'],'VALUES (') + 8;
					$sql_log2 = trim(substr($row['sql_log'],$pos2a,-1));
					$sql_log2 = str_replace(",","{DELIM}",$sql_log2);

					$pos_id1 = strpos($row['sql_log'],") VALUES ('") + 11;
					$id_a = substr($row['sql_log'],$pos_id1,-1);
					$pos_id2 = strpos($id_a,"'");
					$id = substr($row['sql_log'],$pos_id1,$pos_id2);

					$sql_log_array = explode(",",$sql_log);
					$sql_log_array2 = explode("{DELIM}",$sql_log2);
					$sql_log = '';
					for ($k = 0; $k < count($sql_log_array); $k++) {
						if (trim($sql_log_array2[$k]) == 'null') $sql_log_array2[$k] = "''";
						$sql_log .= stripslashes($sql_log_array[$k]) . " = " . stripslashes($sql_log_array2[$k]) . ",<br>";
					}
					$sql_log = substr($sql_log,0,-5);
					if (strpos($row['sql_log']," redcap_auth ") == true) {
						$event = "<font color=#A00000>{$lang['reporting_26']}</font>";
					} elseif (strpos($row['sql_log'],"INSERT INTO redcap_edocs_metadata ") == true) {
						$event = "<font color=green>{$lang['reporting_39']}</font><br><font color=#A00000>{$lang['reporting_27']}</font>"; //Document uploaded
						$sql_log1 = explode("=",$sql_log);
						if (count($sql_log1) == 2) {
							$sql_log = substr($sql_log,0,strrpos($sql_log,";")-1);
						} else {
							$sql_log = substr($sql_log,0,strrpos($sql_log,"="));
						}
					} else {
						$event = "<font color=#A00000>{$lang['reporting_27']}</font>";
					}
					break;

				case 'DATA_EXPORT':

					$pos1 = strpos($row['sql_log'],"SELECT ") + 7;
					$pos2 = strpos($row['sql_log']," FROM ") - $pos1;
					$sql_log = substr($row['sql_log'],$pos1,$pos2);
					$sql_log_array = explode(",",$sql_log);
					$sql_log = '';
					foreach ($sql_log_array as $value) {
						list ($table, $this_field) = explode(".",$value);
						if (strpos($this_field,")") === false) $sql_log .= "$this_field, ";
					}
					$sql_log = substr($sql_log,0,-2);
					$event = "<font color=green>{$lang['reporting_28']}</font>";
					$id = "";
					break;

				case 'DELETE':

					$pos1 = strpos($row['sql_log'],"'") + 1;
					$pos2 = strrpos($row['sql_log'],"'") - $pos1;
					$id = substr($row['sql_log'],$pos1,$pos2);
					$event = "<font color=red>{$lang['reporting_30']}</font>";
					$sql_log = "$table_pk = '$id'";
					break;

				case 'OTHER':

					$sql_log = "";
					$event = "<font color=gray>{$lang['reporting_31']}</font>";
					$id = "";
					break;

			}

		}








		// For v2.2.0 and up
		else
		{
			switch ($row['event']) {

				case 'UPDATE':
					//$sql_log = str_replace("\n","<br>",$row['data_values']);
					$sql_log = $row['data_values'];
					$id = $row['pk'];
					//Determine if deleted user or project record
					if ($row['object_type'] == "redcap_data")
					{
						if (System::isSurveyRespondent($row['user'])) {
							if ($row['description'] == "Delete survey response") {
								$event = "<font color=red>{$lang['survey_1332']}";
							} else {
								$event = "<font color=#000066>{$lang['reporting_47']}";
							}
						} else {
							$event  = "<font color=#000066>{$lang['reporting_25']}";
							if ($row['page'] == "DynamicDataPull/save.php") $event .= " ($ddpText)";
							// Keep DTS page reference for legacy reasons
							elseif ($row['page'] == "DTS/index.php") $event .= " (DTS)";
						}
						if (strpos($row['description'], " (import)") !== false || $row['page'] == "DataImport/index.php" || $row['page'] == "DataImportController:index") {
							$event .= " (import)";
						}
						elseif (strpos($row['description'], " (API)") !== false) {
							$event .= " (API)";
						}
						elseif ($row['description'] == "Erase survey responses and start survey over") {
							$sql_log = "{$lang['survey_1079']}\n$sql_log";
						}
						if (strpos($row['description'], " (Auto calculation)") !== false) {
							$event .= "<br>(Auto calculation)";
						}
						$event .= "</font>";
						// DQ: If fixed values via the Data Quality module, then note that
						if ($row['page'] == "DataQuality/execute_ajax.php") {
							$event  = "<font color=#000066>{$lang['reporting_25']}<br>(Data Quality)</font>";
						}
						// DAGs: If assigning to or removing from DAG
						elseif (strpos($row['description'], "Remove record from Data Access Group") !== false || strpos($row['description'], "Assign record to Data Access Group") !== false)
						{
							$event  = "<font color=#000066>{$lang['reporting_25']}";
							if ($row['page'] == "DataImport/index.php" || $row['page'] == "DataImportController:index") {
								$event .= " (import)";
							} elseif (strpos($row['description'], " (API)") !== false) {
								$event .= " (API)";
							}
							$event .= "</font>";
							$sql_log = str_replace(" (API)", "", $row['description'])."\n(" . $row['data_values'] . ")";
						}
					}
					elseif ($row['object_type'] == "redcap_user_rights" || $row['object_type'] == "redcap_user_roles")
					{
						if ($row['description'] == 'Edit user expiration') {
							// Renamed role
							$event = "<font color=#000066>{$lang['rights_204']}</font>";
						} elseif ($row['description'] == 'Rename role') {
							// Renamed role
							$event = "<font color=#000066>{$lang['rights_200']}</font>";
							$id = '';
						} elseif ($row['description'] == 'Edit role') {
							// Edited role
							$event = "<font color=#000066>{$lang['rights_196']}</font>";
							$id = '';
						} elseif ($row['description'] == 'Remove user from role') {
							// Removed user from role
							$event = "<font color=#A00000>{$lang['rights_177']}</font>";
						} elseif (substr($row['description'], 0, 4) == 'Add ') {
							// Add user or role
							$event = "<font color=green>{$row['description']}</font>";
							$id = '';
						} else {
							// Edit user
							$event = "<font color=#000066>{$lang['reporting_24']}</font>";
						}
					}
                    elseif ($row['object_type'] == "redcap_alerts")
                    {
                        $event = "<font color=green>{$lang['alerts_17']}</font><br><font color=#000066>{$lang['global_49']} $id</font>";
                        $sql_log = $row['data_values'];
                        $id = '';
                    }
					// Survey confirmation email was sent
					elseif ($row['description'] == "Send survey confirmation email to participant") {
						$event = "<font color=green>{$lang['survey_1290']}</font><br><font color=#000066>{$lang['global_49']} $id</font>";
						$sql_log = $row['data_values'];
						$id = '';
					}
					break;

				case 'INSERT':

					//$sql_log = str_replace("\n","<br>",$row['data_values']);
					$sql_log = $row['data_values'];
					$id = $row['pk'];
					//Determine if deleted user or project record
					if ($row['object_type'] == "redcap_data") {
						if (System::isSurveyRespondent($row['user'])) {
							$event = "<font color=#A00000>{$lang['reporting_46']}";
						} else {
							$event = "<font color=#A00000>{$lang['reporting_27']}";
							if ($row['page'] == "DynamicDataPull/save.php") $event .= " ($ddpText)";
						}
						if (strpos($row['description'], " (import)") !== false || $row['page'] == "DataImport/index.php" || $row['page'] == "DataImportController:index") {
							$event .= " (import)";
						}
						elseif (strpos($row['description'], '(API)') !== false) {
							$event .= " (API)";
						}
						$event .= "</font>";
					} elseif ($row['object_type'] == "redcap_user_rights") {
						if ($row['description'] == 'Add role' || $row['description'] == 'Copy role') {
							// Created role
							$event = "<font color=green>{$lang['rights_195']}</font>";
							$id = '';
						} elseif ($row['description'] == 'Assign user to role') {
							// Assigned to user role
							$event = "<font color=#000066>{$lang['rights_167']}</font>";
						} else {
							// Added user
							$event = "<font color=green>{$lang['rights_187']}</font>";
						}
						//print_array($row);
					}
					break;

				case 'DATA_EXPORT':

					// Display fields and other relevant export settings
					$sql_log = $row['data_values'];
					if (substr($sql_log, 0, 1) == '{') {
						// If string is JSON encoded (i.e. v6.0.0+), then parse JSON to display all export settings
						$sql_log_array = array();
						foreach (json_decode($sql_log, true) as $key=>$val) {
							if (is_array($val)) {
								$sql_log_array[] = "$key: \"".implode(", ", $val)."\"";
							} else {
								$sql_log_array[] = "$key: $val";
							}
						}
						$sql_log = implode(",\n", $sql_log_array);
					}
					// Set other values
					$event = "<font color=green>{$lang['reporting_28']}";
					if (strpos($row['description'], '(API)') !== false) {
						$event .= " (API)";
					} elseif (strpos($row['description'], '(API Playground)') !== false) {
						$event .= "<br>(API Playground)";
					}
					$event .= "</font>";
					$id = "";
					break;

				case 'DOC_UPLOAD':
					if (strpos($row['page'], 'Design/') === 0) {
						$sql_log = $row['description'];
						$event = "<font color=#000066>{$lang['reporting_33']}</font>";
					} else {
						$sql_log = $row['data_values'];
						$event = "<font color=green>{$lang['reporting_39']}";
						if (strpos($row['description'], '(API)') !== false) {
							$event .= " (API)";
						} elseif (strpos($row['description'], '(API Playground)') !== false) {
							$event .= "<br>(API Playground)";
						}
						$event .= "</font><br><font color=#000066>{$lang['reporting_25']}</font>";
						$id = $row['pk'];
					}
					break;

				case 'DOC_DELETE':

					$sql_log = $row['data_values'];
					$event = "<font color=red>{$lang['reporting_40']}</font><br><font color=#000066>{$lang['reporting_25']}</font>";
                    if (strpos($row['description'], ' (V') !== false) {
                        list($nothing,$version) = explode(' (V', $row['description'], 2);
                        $version = preg_replace("/[^0-9]/", "", $version);
                        $sql_log .= " (V{$version})";
                    }
					$id = $row['pk'];
					break;

				case 'DELETE':

					$sql_log = $row['data_values'];
					$id = $row['pk'];
					//Determine if deleted user or project record
					if ($row['object_type'] == "redcap_data") {
						$event = "<font color=red>{$lang['reporting_30']}";
						if (strpos($row['description'], '(API)') !== false) {
							$event .= " (API)";
						}else if (strpos($row['description'], '(API Playground)') !== false) {
							$event .= " (API Playground)";
						}
						$event .= "</font>";
					} elseif ($row['object_type'] == "redcap_user_rights") {
						if ($row['description'] == 'Delete role') {
							// Deleted role
							$event = "<font color=red>{$lang['rights_197']}</font>";
							$id = '';
						} else {
							// Deleted user
							$event = "<font color=red>{$lang['reporting_29']}</font>";
						}
					}
					break;

				case 'OTHER':
                    if (!$html_output
                        // Do not display the word "Record" before the record name in the CSV Export for certain descriptions
                        && strpos($row['description'], "Invalid SMS Response") === 0
                    ) {
                        $id = $row['pk'];
                    } else {
                        // Normal page rendering
                        $id = ($row['pk'] == "") ? "" : $lang['global_49'] . " " . $row['pk'];
                    }
					$event = "<font color=#A00000>{$row['description']}</font>";
					$sql_log = $row['data_values'];
					break;

				case 'MANAGE':
					$sql_log = $row['description'];
					$event = "<font color=#000066>{$lang['reporting_33']}</font>";
					$id = "";
					// Parse activity differently for arms, events, calendar events, and scheduling
					if (in_array($sql_log, array("Create calendar event","Delete calendar event","Edit calendar event","Create event","Edit event",
												 "Delete event","Create arm","Delete arm","Edit arm name/number"))) {
						$sql_log .= "\n(" . $row['data_values'] . ")";
					}
					// Multi-Language Management
					if ($row["sql_log"] == "MLM") {
						$mlm_cat = $row["data_values"];
						$mlm_col = strpos(strtolower($mlm_cat), "delete") === false ? "green" : "red";
                        $event .= "<br><span style='color:{$mlm_col};'>{$row["data_values"]}</span>";
					}
                    // PDF exports with data as "Data Export"
                    elseif (strpos($sql_log, "PDF (with data)") !== false) {
                        // Make sure pk/id is the record name and not the project_id
                        if ($row['project_id'] == $row['pk']) {
                            $data_values_array = explode(",\n", $row['data_values']);
                            foreach ($data_values_array as $this_array) {
                                list ($this_key, $this_value) = explode(" = ", $this_array);
                                if ($this_key != 'record') continue;
                                if (strpos($this_value, "'") !== 0) continue;
                                $row['pk'] = ltrim(rtrim($this_value, "'"), "'");
                            }
                        }
                        $event = "<font color=green>{$lang['reporting_70']}</font>";
						$id = $lang['global_49']." ".$row['pk'];
						if ($Proj->longitudinal && is_numeric($row['event_id'])) {
							$id .= " <span style='color:#777;'>(" . strip_tags(label_decode($event_ids[$row['event_id']])) . ")</span>";
							$sql_log = $row['description'] . "\n(" . $lang['api_24'] . " " .trim(str_replace("form_name =", "", $row['data_values'])) . ")";
						}
                    }
					// Downloading exported data files as "Data Export"
					elseif (strpos($sql_log, "Download exported") === 0) {
						$event = "<font color=green>{$lang['reporting_28']}</font>";
					}
					// File Repository
					elseif (strpos($row['page'], "FileRepositoryController:") === 0
                        && $sql_log != "Download CSV containing file list of all PDF Snapshot files"
                        && (
                            $sql_log == "View preview of file from File Repository via Public Link"
                            || $sql_log == "Download file from File Repository via Public Link"
                            || $sql_log == "Download file attachment"
                            || $sql_log == "Upload file to File Repository"
                            || strpos($sql_log, "Export list of files/folders from File Repository") === 0
                            || strpos($sql_log, "Download file from File Repository") === 0
                            || strpos($sql_log, "Delete file from File Repository") === 0
                        )
					) {
						$sql_log = $row['description'] . "\n(" . $row['data_values'] . ")";
					}
					// Render record name for edoc downloads
					elseif ($sql_log == "Download uploaded document") {
						$event = "<font color=#000066>$sql_log</font>";
						// Deal with legacy logging, in which the record was not known and data_values contained "doc_id = #"
						if ($row['pk'] != "") {
							$sql_log = $row['data_values'];
							$id = $row['pk'];
							$event .= "<br>{$lang['global_49']}";
						} else {
							$sql_log = "";
						}
					}
					// Mobile App file upload to mobile app archive from app
					elseif ($sql_log == "Upload document to mobile app archive") {
						$event = "<font color=green>{$lang['reporting_39']}<br>{$lang['mobile_app_21']}</font>";
					}
                    // Alerts
                    elseif (strpos($sql_log, " alert") !== false) {
                        $event .= "<br><font color=green>$sql_log</font>";
                        $sql_log = $row['data_values'];
                    }
                    // Assign user to DAG or remove from DAG
					elseif ($sql_log == "Assign user to data access group" || $sql_log == "Remove user from data access group"
						|| $sql_log == "DAG Switcher: Assign user to additional DAGs" || $sql_log == "DAG Switcher: Remove user from multiple DAG assignment") {
						$sql_log .= "\n".$row['data_values'];
					}				
					// ASI was scheduled or removed
					elseif ($sql_log == "Automatically schedule survey invitation" || $sql_log == "Automatically remove scheduled survey invitation"
						|| strpos($sql_log, "Delete scheduled survey invitation") === 0 || $sql_log == "Modify send time for scheduled survey invitation")
					{
						$asiDetails = array();
						$asiLog = explode(",\n", $row['data_values']);
						foreach ($asiLog as $val) {
							list ($key, $val) = explode(" = ", $val, 2);
							if ($key == 'survey_id') {
								$asiDetails[1] = $lang['survey_437'].$lang['colon'].' "'.RCView::escape($Proj->surveys[$val]['title']).'"';
							} elseif ($Proj->longitudinal && $key == 'event_id') {
								$asiDetails[2] = $lang['global_141'].$lang['colon'].' "'.RCView::escape($Proj->eventInfo[$val]['name_ext']).'"';
							} elseif ($key == 'record') {
								$val = substr($val, 1, -1);
								$asiDetails[0] = $lang['global_49'].$lang['colon'].' "'.RCView::escape($val).'"';
							} elseif ($key == 'instance') {
								$asiDetails[3] = $lang['data_entry_246'].$lang['colon'].' "'.RCView::escape($val).'"';
							}
						}
						ksort($asiDetails);
						$asiText = implode(", ", $asiDetails);
						$sql_log .= "\n($asiText)";
					}
					// Render Download PDF Auto-Archive File so that it displays the record name
					elseif ($sql_log == "Download PDF Auto-Archive File" || $sql_log == "Download PDF Snapshot File") {
						$sql_log .= "\n".$lang['global_49']." ".$row['pk']."";
					}
					// Render randomization of records so that it displays the record name
					elseif (strpos($sql_log, "Randomize record") === 0 || $sql_log == "Remove randomization") {
						$id = $row['pk'];
						$event = "<font color=#000066>{$lang['random_117']}</font>";
					}
					elseif (strpos($sql_log, "Save randomization execute option") === 0 || strpos($sql_log, "Update randomization allocation table") === 0) {
                        $sql_log .=  "\n(".$row['data_values'].")";
					}
					// Render the email recipient's email if "Send email"
					elseif ($sql_log == "Send email" && $row['pk'] != '') {
						$sql_log .= "\n({$lang['reporting_48']}{$lang['colon']} {$row['pk']})";
					}
					// For super user action of viewing another user's API token, add username after description for clarification
					elseif ($sql_log == "View API token of another user") {
						$sql_log .=  "\n(".$row['data_values'].")";
					}
					// Online Designer: Quick-modify field(s)
					elseif (strpos($sql_log, "Quick-modify field") === 0) {
						$sql_log .=  ";\n".$row['data_values'];
					}
					// For sending public survey invites via Twilio services
					elseif (strpos($sql_log, "Send public survey invitation to participants") === 0) {
						$sql_log .=  "\n".$row['data_values'];
					}
                    // Data Mart data fetch
                    elseif ($sql_log == "Fetch data for Clinical Data Mart") {
                        $event = "<font color=#000066>{$lang['ws_293']}</font>";
                    }
					// Re-eval ASIs
					elseif ($sql_log == "Re-evaluate automated survey invitations") {
						$sql_log = $row['data_values'];
						$id = "<font color='green'>{$lang['asi_052']}</font>";
					}
					// Make a report public
					elseif ($sql_log == "Set report as public") {
						$sql_log = $row['data_values'];
					}
					// Perform informed e-Consent or PDF Snapshots
					elseif ($sql_log == "e-Consent Certification" || $sql_log == "e-Consent PDF Snapshot Regeneration") {
                        $values = [];
                        foreach (json_decode($row['data_values'], true) as $key=>$val) {
                            $values[] = "$key = \"$val\"";
                        }
                        $event = "<font color=#A86700>$sql_log</font>";
						$sql_log .= "\n".implode("\n", $values);
                        $id = $row['pk'];
					}
					elseif ($sql_log == "Save PDF Snapshot to File Repository" || $sql_log == "Save PDF Snapshot to File Upload Field") {
                        $values = [];
                        foreach (json_decode($row['data_values'], true) as $key=>$val) {
                            $values[] = "$key = \"$val\"";
                        }
                        $event = "<font color=#C00000>".$lang['econsent_126']."</font>";
						$sql_log .= "\n".implode("\n", $values);
                        $id = $row['pk'];
					}
                    // Create/edit/delete report
                    if (strpos($sql_log, "Create report ") === 0 || strpos($sql_log, "Edit report ") === 0 || strpos($sql_log, "Delete report ") === 0 || strpos($sql_log, "Copy report ") === 0) {
                        if (substr($sql_log, -1) == ")") $sql_log = substr($sql_log, 0, -1);
                        if (strpos($row['data_values'], "fields: ") === 0) {
                            $sql_log .= ", ".$row['data_values'];
                        }
                        $sql_log .= ")";
                    }
					// Field Comment Log or Data Resolution Workflow
					elseif ($sql_log == "Edit field comment" || $sql_log == "Delete field comment" || $sql_log == "Add field comment" || $sql_log == "De-verified data value"
						|| $sql_log == "Verified data value" || (contains($sql_log, "data query") && !contains($sql_log, "belonging to deleted data quality rule"))
					) {
						// Parse JSON values
						$jsonLog = json_decode($row['data_values'],true);
						// Record
						$sql_log .= "\n({$lang['dataqueries_93']} {$row['pk']}";
						// Event name (if longitudinal)
						if ($Proj->longitudinal && is_numeric($row['event_id'])) {
							$sql_log .= ", {$lang['bottom_23']} " . strip_tags(label_decode($event_ids[$row['event_id']]));
						}
						// Field name (unless is a multi-field custom DQ rule)
						if ($jsonLog['field'] != '') {
							$sql_log .= ", {$lang['reporting_49']} ".$jsonLog['field'];
						}
						// DQ rule (if applicable)
						if ($jsonLog['rule_id'] != '') {
							$sql_log .= ", {$lang['dataqueries_169']} ".(is_numeric($jsonLog['rule_id']) ? "#" : "")
									 .  $dq_rules[$jsonLog['rule_id']]['order'];
						}
						// Field Comment text
						if ($jsonLog['comment'] != '') {
							$sql_log .= ", {$lang['dataqueries_195']}{$lang['colon']} \"".$jsonLog['comment']."\"";
						}
						$sql_log .= ")";
					}
					break;

				case 'LOCK_RECORD':
					$sql_log = $lang['reporting_44'] . $row['description'] . "\n" . $row['data_values'];
					$event = "<font color=#A86700>{$lang['reporting_41']}</font>";
					$id = $row['pk'];
					break;

				case 'ESIGNATURE':
					$sql_log = $lang['reporting_44'] . $row['description'] . "\n" . $row['data_values'];
					$event = "<font color=#008000>{$lang['global_34']}</font>";
					$id = $row['pk'];
					break;

				case 'PAGE_VIEW':
					$sql_log = $lang['reporting_45']."\n" . $row['full_url'];
					// if ($row['record'] != "") $sql_log .= ",<br>record: " . $row['record'];
					// if ($row['event_id'] != "") $sql_log .= ",<br>event_id: " . $row['event_id'];
					$event = "<font color=#000066>{$lang['reporting_43']}</font>";
					$id = "";
					$row['data_values'] = "";
					break;

			}

		}

        // Set record name, if a record-centric event
        if (isset($row['description']) && ($row['description'] == 'Send alert' || strpos($row['description'], "PDF (with data)") !== false)) {
            $this_record = $row['pk'];
        } elseif (strpos($id, "<") === false) {
            $this_record = $id;
        } else {
            $this_record = "";
        }

		// Append Event Name (if longitudinal)
		$dataEvents = array("OTHER","UPDATE","INSERT","DELETE","DOC_UPLOAD","DOC_DELETE","LOCK_RECORD","ESIGNATURE");
		if ($Proj->longitudinal && $row['legacy'] == '0'
			 && (isset($row['object_type']) && ($row['object_type'] == "redcap_data" || $row['object_type'] == "redcap_alerts" || $row['object_type'] == "") && in_array($row['event'], $dataEvents))
			 && !(strpos($row['description'], "Remove record from Data Access Group") !== false || strpos($row['description'], "Assign record to Data Access Group") !== false)
			)
		{
			// If missing, set to first event_id
			if ($row['event_id'] == "" && $row['event'] != 'OTHER') {
				$row['event_id'] = $Proj->firstEventId;
			}
			
			// If a record was deleted, don't show event name, and if multiple arms, then display the arm name from which it was deleted
			if ($row['description'] == 'Delete record') {
				if ($Proj->multiple_arms) {
					$eventInfo = $Proj->eventInfo[$row['event_id']];
					$id .= " <span style='color:#777;'>(" . strip_tags(label_decode($lang['global_08']." ".$eventInfo['arm_num'].$lang['colon']." ".$eventInfo['arm_name'])) . ")</span>";
				}
			}			
			// If event_id is not valid, then don't display event name
			elseif (isset($event_ids[$row['event_id']])) {
				$id .= " <span style='color:#777;'>(" . strip_tags(label_decode($event_ids[$row['event_id']])) . ")</span>";
			}
		}

		unset($sql_log_array);
		unset($sql_log_array2);

		// Set description
		$description = "$event<br>$id";

		// If outputting to non-html format (e.g., csv file), then remove html
		if (!$html_output)
		{
			$row['ts']   = DateTimeRC::format_ts_from_int_to_ymd($row['ts']);
			$description = strip_tags(str_replace("<br>", " ", $description));
			$sql_log 	 = filter_tags(str_replace(array("<br>","\n"), array(" "," "), label_decode($sql_log)));
		}
		// html output (i.e. Logging page)
		else
		{
			$row['ts'] = DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($row['ts']));
		}

		// Set values for this row
		$new_row = array($row['ts'], $row['user'], $description, $sql_log, $this_record);

		// If project-level flag is set, then add "reason changed" to row data
		if ($require_change_reason)
		{
			$new_row[] = $html_output ? nl2br(filter_tags($row['change_reason'])) : str_replace("\n", " ", html_entity_decode($row['change_reason']??"", ENT_QUOTES));
		}

		// Return values for this row
		return $new_row;
	}


	public static function setEventFilterSql($logtype)
	{
		switch ($logtype)
		{
			case 'page_view':
				$filter_logtype =  "AND event = 'PAGE_VIEW'";
				break;
			case 'lock_record':
				$filter_logtype =  "AND event in ('LOCK_RECORD', 'ESIGNATURE')";
				break;
			case 'manage':
				$filter_logtype =  "AND event = 'MANAGE' and description not like 'Download exported%' and description not like '%PDF (with data)%'";
				break;
			case 'export':
				$filter_logtype =  "AND (event = 'DATA_EXPORT' or (event = 'MANAGE' and (description like 'Download exported%' or description like '%PDF (with data)%')))";
				break;
			case 'record':
				$filter_logtype =  "AND (
									(
										(
											legacy = '1'
											AND
											(
												left(sql_log,".strlen("INSERT INTO redcap_data").") = 'INSERT INTO redcap_data'
												OR
												left(sql_log,".strlen("UPDATE redcap_data").") = 'UPDATE redcap_data'
												OR
												left(sql_log,".strlen("DELETE FROM redcap_data").") = 'DELETE FROM redcap_data'
											)
										)
										OR
										(legacy = '0' AND object_type = 'redcap_data')
									)
									AND
										(event != 'DATA_EXPORT')
									)";
				break;
			case 'record_add':
				$filter_logtype =  "AND (
										(legacy = '1' AND left(sql_log,".strlen("INSERT INTO redcap_data").") = 'INSERT INTO redcap_data')
										OR
										(legacy = '0' AND object_type = 'redcap_data' and event = 'INSERT')
									)";
				break;
			case 'record_edit':
				$filter_logtype =  "AND (
										(legacy = '1' AND left(sql_log,".strlen("UPDATE redcap_data").") = 'UPDATE redcap_data')
										OR
										(legacy = '0' AND object_type = 'redcap_data' and event in ('UPDATE','DOC_DELETE','DOC_UPLOAD'))
										OR
										(legacy = '0' AND page = 'PLUGIN' and event in ('OTHER'))
									)";
				break;
			case 'record_delete':
				$filter_logtype =  "AND object_type = 'redcap_data' AND event = 'DELETE'";
				break;
			case 'user':
				$filter_logtype =  "AND object_type = 'redcap_user_rights'";
				break;
			default:
				$filter_logtype = '';
		}

		return $filter_logtype;

	}

	public static function getEventById($logEventId){
		$result = db_query("select * from redcap_log_event where log_event_id = $logEventId");
		$row = db_fetch_assoc($result);

		$secondRow = db_fetch_assoc($result);
		if($secondRow !== null){
			throw new Exception("Multiple redcap_log_event rows exist for log_event_id $logEventId!");
		}

		return $row;
	}

	/**
     * convert an array to a string indenting nested values
     *
     * @param array $array
     * @param integer $indentation indent nested values
     * @return void
     */
    public static function printArray($array, $indentation=0)
    {
        if(!is_array($array)) return;
        $string = '';
        foreach($array as $key=>$value)
        {
            $string .= str_repeat("\t", $indentation); // add indentation
			if (is_array($value)) $value = json_encode_rc($value);
            $string .= sprintf("%s = '%s'", $key, $value); // print key and value
            $string .= PHP_EOL; // add end of line
            if(is_array($value)) $string .= self::printArray($value, $indentation+1);
        }
        return $string;
	}

	/**
	 * log in a text file
	 *
	 * @param string $filename
	 * @param string $data
	 * @return void
	 */
	public static function writeToFile($filePath='', $data='')
	{
		$now = date("Ymd_His");
		$row = sprintf("%s: %s%s",$now, $data, PHP_EOL);

		$filename = $filePath;
		file_put_contents ( $filename , $row , $flags=FILE_APPEND );
	}

	// Return the specific redcap_log_event* db table being used for a given project
	public static function getLogEventTable($project_id=null)
	{
		if (!is_numeric($project_id) || $project_id < 1) return 'redcap_log_event';
		$sql = "select log_event_table from redcap_projects where project_id = $project_id";
		$q = db_query($sql);
		if (!$q || db_num_rows($q) < 0) {
			return 'redcap_log_event';
		} else {
			return db_result($q, 0);
		}
	}

	// Return array of all redcap_log_event* db tables
	private static $logEventTablesExclude = array('redcap_log_event', 'redcap_log_event2', 'redcap_log_event3', 'redcap_log_event4', 'redcap_log_event5');
	public static function getLogEventTables($excludeLegacyTables=false)
	{
		$tables = array();
		$sql = "show tables like 'redcap\_log\_event%'";
		$q = db_query($sql);
		while ($row = db_fetch_array($q)) {
			$table = $row[0];
			// Do not include legacy tables (if applicable)
			if ($excludeLegacyTables && in_array($table, self::$logEventTablesExclude)) {
				continue;
			}
			// Validate it
			list ($prefix, $num) = explode("redcap_log_event", $table, 2);
			if (!($num == "" || (isinteger($num) && $num >=2 && $num <= 99))) {
				continue;
			}
			// Add to array
			$tables[] = $table;
		}
		// Sort tables alphabetically for consistency
		ksort($tables);
		// Return tables
		return $tables;
	}

	// Return an estimated row count for a given redcap_log_event* db table
	// (use MySQL EXPLAIN to do row count quickly, not not super accurate, which is fine for these purposes)
	public static function getLogEventTableRows($log_table='redcap_log_event')
	{
		$sql = "EXPLAIN SELECT COUNT(log_event_id) FROM $log_table USE INDEX (PRIMARY)";
		$q = db_query($sql);
		return db_result($q, 0, 'rows');
	}

	// Return the table name of the redcap_log_event* db table with fewest rows (based on MySQL EXPLAIN approximation)
	public static function getSmallestLogEventTable($excludeLegacyTables=true)
	{
		$tableRows = array();
		foreach (self::getLogEventTables($excludeLegacyTables) as $table) {
			$tableRows[$table] = self::getLogEventTableRows($table);
		}
		$smallest_tables = array_keys($tableRows, min($tableRows));
		$smallest_table = $smallest_tables[0];
		return $smallest_table;
	}

	// Save IP address as hashed value in cache table to prevent automated attacks
	public static function storeHashedIp($ip)
	{
		global $salt, $__SALT__, $project_contact_email, $page_hit_threshold_per_minute, $redcap_version, $rate_limiter_ip_range;

        // If user's IP is in a range, then set TRUE so that ip is in exception IPs list
        $ip_in_exception = false;
        if ($rate_limiter_ip_range != null) {
            $ip_ranges = explode(",", $rate_limiter_ip_range);
            $ip_in_exception = Authentication::ip_in_ranges(System::clientIpAddress(), $ip_ranges);
        }

		// If not a project-level page, then instead use md5 of $salt in place of $__SALT__
		$projectLevelSalt = ($__SALT__ == '') ? md5($salt) : $__SALT__;

		// Hash the IP (because we shouldn't know the IP of survey respondents)
		$ip_hash = md5($salt . $projectLevelSalt . $ip . $salt);

		// Add IP to the table for this request
		db_query("insert into redcap_ip_cache values ('$ip_hash', '" . NOW . "')");

		// Get timestamp of 1 minute ago
		$oneMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-1,date("s"),date("m"),date("d"),date("Y")));

		// Check if ip is found more than a set threshold of times in the past 1 minute
		$sql = "select count(1) from redcap_ip_cache where ip_hash = '$ip_hash' and timestamp > '$oneMinAgo'";
		$q = db_query($sql);
		$total_hits = db_result($q, 0);
		if ($ip != '' && $ip_in_exception == false && $page_hit_threshold_per_minute != '' && $page_hit_threshold_per_minute > 0 && $total_hits > $page_hit_threshold_per_minute)
		{
			// Threshold reached, so add IP to banned IP table
			db_query("insert into redcap_ip_banned values ('".db_escape($ip)."', '" . NOW . "')");

			// Also send an email to the REDCap admin to notify them of this
			$email = new Message();
			$email->setFrom(\Message::useDoNotReply($GLOBALS['project_contact_email']));
			$email->setFromName($GLOBALS['project_contact_name']);
			$email->setTo($project_contact_email);
			$email->setSubject('[REDCap] IP address banned due to suspected abuse');
			$this_user = defined("USERID") ? "named <b>".USERID."</b>" : "";
			$this_page = !defined("PROJECT_ID")
				? "<a href=\"".APP_PATH_WEBROOT_FULL."\">REDCap</a>"
				: "<a href=\"" . APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/index.php?pid=" . PROJECT_ID . "\">this REDCap project</a>";
			$msg = "REDCap administrator,<br><br>
				As of " . DateTimeRC::format_ts_from_ymd(NOW) . ", the IP address <b>$ip</b> has been permanently banned from REDCap due to suspected abuse.
				A user $this_user at that IP address was found to have accessed $this_page over $page_hit_threshold_per_minute times within the same minute. If this is incorrect,
				you may un-ban the IP address by executing the SQL query below.<br><br>
				DELETE FROM redcap_ip_banned WHERE ip = '$ip';";
			$email->setBody($msg, true);
			$email->send();
		}
	}

	// Check if IP address has been banned. If so, stop everything NOW.
	public static function checkBannedIp($ip)
	{
		// Check for IP in banned IP table
		$q = db_query("select 1 from redcap_ip_banned where ip = '".db_escape($ip)."' limit 1");
		if (db_num_rows($q) > 0)
		{
			// Output message and stop here to prevent using further server resources (in case of attack)
			header('HTTP/1.1 429'); // Set a "too many requests" HTTP error 429
			exit("Your IP address ($ip) has been banned due to suspected abuse.");
		}
	}

	// Generate filter HTML for Logging UI and API method
    public static function getFilterHTML($getArr, $isApi = false) {
        global $lang, $Proj, $user_rights;

        $project_id = PROJECT_ID;

        $dags = $Proj->getGroups();
        include APP_PATH_DOCROOT . 'Logging/filters.php';

        $onChange = ($isApi == false) ?
                        "onchange=\"window.location.href='".PAGE_FULL."?pid=$project_id&usr='+\$('#usr').val()+'&record='+\$('#record').val()+'&beginTime='+\$('#beginTime').val()+'&endTime='+\$('#endTime').val()+'&dag='+\$('#dag').val()+'&logtype='+this.value;\""
                        : "";
        $tr_filter_logging = "<tr><td style='text-align:right;padding-right:5px;'>{$lang['reporting_08']}</td><td>
		                        <select id='logtype' ";

        $_GET = $getArr;

        if ($isApi == false) $tr_filter_logging .= "class='x-form-text x-form-field'";
        $tr_filter_logging .= " style='margin-bottom:2px;font-size:13px;height:25px;' ".$onChange.">
			                        <option value='' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == '') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_09']}</option>
			                    <option value='export' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == 'export') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_10']}</option>
			                    <option value='manage' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == 'manage') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_33']}</option>
			                    <option value='user' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == 'user') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_50']}</option>
			                    <option value='record' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == 'record') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_12']}</option>
			                    <option value='record_add' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == 'record_add') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_13']}</option>
			                    <option value='record_edit' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == 'record_edit') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_14']}</option>
			                    <option value='record_delete' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == 'record_delete') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_61']}</option>
			                    <option value='lock_record' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == 'lock_record') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_34']}</option>
			                    <option value='page_view' ";

        if (isset($_GET['logtype']) && $_GET['logtype'] == 'page_view') $tr_filter_logging .= "selected";
        $tr_filter_logging .= ">{$lang['reporting_35']}</option>
		                            </select>
		                        </td></tr>";

        ## FILTER by username
        $usrOnChange = ($isApi == false) ?
                        "onchange=\"window.location.href='".PAGE_FULL."?pid=$project_id&logtype='+\$('#logtype').val()+'&dag='+\$('#dag').val()+'&record='+\$('#record').val()+'&beginTime='+\$('#beginTime').val()+'&endTime='+\$('#endTime').val()+'&usr='+this.value;\""
                        : "";

        $tr_filter_logging .= "<tr>
                                <td style='text-align:right;padding-right:5px;'>
                                    {$lang['reporting_15']}
                                </td>
                                <td>
                                    <select id='usr' ";

        if ($isApi == false) $tr_filter_logging .= "class='x-form-text x-form-field'";

        $tr_filter_logging .= " style='margin-bottom:2px;font-size:13px;height:25px;' ".$usrOnChange.">
				                    <option value='' " . (isset($_GET['usr']) && $_GET['usr'] == '' ? "selected" : "" ) . ">{$lang['reporting_16']}</option>";

        // Get usernames of ALL current users
        $all_users = array();
        $projectUsers = User::getProjectUsers($project_id);
        foreach ($projectUsers as $row) {
            $all_users[$row['username']] = $row['full_name'];
        }
        // Add [survey respondent] if the project contains surveys
        if (!empty($Proj->surveys)) {
            $all_users[System::SURVEY_RESPONDENT_USERID] = "";
        }
        // Loop through all users
        ksort($all_users);
        foreach ($all_users as $this_user=>$this_name) {
            // If in a DAG, ignore users not in their DAG
            if ($user_rights['group_id'] != "") {
                if (!in_array($this_user, $dag_users_array)) continue;
            }
            // Render option
            $tr_filter_logging .= "<option value='$this_user' ";
            if (isset($_GET['usr']) && $_GET['usr'] == $this_user) $tr_filter_logging .= "selected";
            $tr_filter_logging .= ">$this_user";
            if ($this_name != '') {
                $tr_filter_logging .= " (".RCView::escape($this_name).")";
            }
            $tr_filter_logging .= "</option>";
        }
        $tr_filter_logging .= "</select></td></tr>";

        $recordOnChange = ($isApi == false) ?
                            "window.location.href='".PAGE_FULL."?pid=$project_id&logtype='+\$('#logtype').val()+'&dag=&usr='+\$('#usr').val()+'&beginTime='+\$('#beginTime').val()+'&endTime='+\$('#endTime').val()+'&record='+this.value;"
                            : "";
        $tr_filter_logging .= "<tr>
                                    <td style='text-align:right;padding-right:5px;'>
                                        {$lang['reporting_36']}
                                    </td>
                                    <td>";
        // Retrieve list of all records
        $tr_filter_logging .= Records::renderRecordListAutocompleteDropdown(PROJECT_ID, true, 5000, 'record',
            "x-form-text x-form-field", "margin-bottom:2px;font-size:13px;height:25px;", ($_GET['record'] ?? ""), $lang['reporting_37'], $lang['alerts_205'],
            $recordOnChange, $disableRecordFilter);
        $tr_filter_logging .= "</td></tr>";

        ## Filter by DAG
        if (isset($user_rights['group_id']) && $user_rights['group_id'] == '' && !empty($dags))
        {
            $tr_filter_logging .= "<tr>
                                        <td style='text-align:right;padding-right:5px;'>
                                            {$lang['reporting_52']}
                                        </td>
				                        <td>";
            $dagOnChange = ($isApi == false) ?
                            "window.location.href='".PAGE_FULL."?pid=$project_id&logtype='+\$('#logtype').val()+'&usr='+\$('#usr').val()+'&beginTime='+\$('#beginTime').val()+'&endTime='+\$('#endTime').val()+'&record=&dag='+this.value;"
                            : "";
            $tr_filter_logging .= RCView::select(array('id'=>'dag', $disableRecordFilter=>$disableRecordFilter, 'class'=> ($isApi == false) ? 'x-form-text x-form-field' : '', 'style'=>'margin-bottom:2px;font-size:13px;height:25px;',
                                                        "onchange"=>$dagOnChange),
                                                        (array(''=>$lang['dataqueries_135'])+$dags), (isset($_GET['dag']) ? $_GET['dag'] : ""));
            $tr_filter_logging .= "</td></tr>";
        }

        # FILTER BY BEGIN AND END TIME
        //Show dropdown for displaying Begin time
        $tr_filter_logging .= "<tr>
                                    <td style='text-align:right;padding-right:5px;'>
                                        {$lang['reporting_51']}
                                    </td>
                                    <td>
                                        <span>
                                            <input autocomplete='off' type='text' ";
        if ($isApi == false) {
            $tr_filter_logging .= "class='x-form-text x-form-field'";
            $onBlurBeginTime = "onblur=\"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);\"";
            $onBlurEndTime = "onblur=\"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);\"";
        } else {
            $onBlurBeginTime = "";
            $onBlurEndTime = "";
            $projCreationTime = $Proj->project['creation_time'];
            $beginTime_userPref = (isset($_GET['beginTime']) && $_GET['beginTime'] != "") ?
                                    str_replace(array("`","="), array("",""), strip_tags(label_decode(urldecode($_GET['beginTime']))))
                                    : date('Y-m-d H:i',strtotime($projCreationTime));

            $endTime_userPref   = (isset($_GET['endTime']) && $_GET['endTime'] != "") ?
                                    str_replace(array("`","="), array("",""), strip_tags(label_decode(urldecode($_GET['endTime']))))
                                    : '';
        }
        $tr_filter_logging .= " style='width:120px;' id='beginTime' ".$onBlurBeginTime." value=\"".htmlspecialchars($beginTime_userPref, ENT_QUOTES)."\" onkeypress=\"pageLoad(event)\">
                                    <span style='margin:0 5px 0 7px;'>{$lang['data_access_groups_ajax_14']}</span>
                                    <input autocomplete='off' type='text' ";

        if ($isApi == false) $tr_filter_logging .= "class='x-form-text x-form-field'";
        $tr_filter_logging .= " style='width:120px;' id='endTime' ".$onBlurEndTime." value=\"".htmlspecialchars($endTime_userPref, ENT_QUOTES)."\" onkeypress=\"pageLoad(event)\">
                                </span>";

        if ($isApi == false) {
            $tr_filter_logging .= "<div class='btn-group ms-4 bg-white' role='group'>
                                        <button class='btn btn-outline-primary btn-xs $customRangeActive' onclick=\"$('#beginTime').next('img').trigger('click');\">
                                            {$lang['reporting_63']}
                                        </button>
                                        <button class='btn btn-outline-primary btn-xs $oneDayAgoActive' onclick=\"$('#beginTime').val('{$oneDayAgo}');$('#endTime').val('');$('#logtype').trigger('change');\">
                                            {$lang['dashboard_89']}
                                        </button>
                                        <button class='btn btn-outline-primary btn-xs $oneWeekAgoActive' onclick=\"$('#beginTime').val('{$oneWeekAgo}');$('#endTime').val('');$('#logtype').trigger('change');\">
                                            {$lang['dashboard_07']}
                                        </button>
                                        <button class='btn btn-outline-primary btn-xs $oneMonthAgoActive' onclick=\"$('#beginTime').val('{$oneMonthAgo}');$('#endTime').val('');$('#logtype').trigger('change');\">
                                            {$lang['dashboard_08']}
                                        </button>
                                        <button class='btn btn-outline-primary btn-xs $oneYearAgoActive' onclick=\"$('#beginTime').val('{$oneYearAgo}');$('#endTime').val('');$('#logtype').trigger('change');\">
                                            {$lang['dashboard_11']}
                                        </button>
                                        <button class='btn btn-outline-primary btn-xs $noLimitActive' onclick=\"$('#beginTime').val('');$('#endTime').val('');$('#logtype').trigger('change');\">
                                            {$lang['reporting_69']}
                                        </button>
                                    </div>";
        }
        $tr_filter_logging .= "</td></tr>";
        return $tr_filter_logging;
    }
}