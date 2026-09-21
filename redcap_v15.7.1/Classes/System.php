<?php

require_once __DIR__ . "/StatementResult.php";

use ExternalModules\ExternalModules;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\StatementResult;

/**
 * System Class
 * Contains methods used for general operations in REDCap
 */
class System
{
	/** The user id that is assigend to survey respondents */
	const SURVEY_RESPONDENT_USERID = "[survey respondent]";
	// Set the lowest version of PHP with which REDCap is compatible
	const minimum_php_version_required = '8.0.2';
	// Set a list of the recommended versions of PHP
	public static $recommendedPhpVersions = array("8.1", "8.2", "8.3", "8.4");
	// Set the lowest version of MariaDB/MySQL with which REDCap is compatible
	const minimum_mysql_version_required = '5.5.5';
	const powered_by_redcap = 'Powered by REDCap';
	// Default keywords used by the Check For Identifiers module
	const identifier_keywords_default = "name, street, address, city, county, precinct, zip, postal, date, phone, fax, mail, ssn, social security, mrn, dob, dod, medical, record, id, age";
	// Disable GZIP: List of specific pages where a file is downloaded (rather than a webpage displayed) where GZIP should be disabled
	public static $fileDownloadPages = [
        "DataExport/data_export_csv.php", "DataExport/sas_pathway_mapper.php", "DataExport/spss_pathway_mapper.php",
        "DataImportController:downloadTemplate", "Design/data_dictionary_download.php", "Design/data_dictionary_demo_download.php",
        "FileRepositoryController:download", "Locking/esign_locking_management.php", "Logging/csv_export.php",
        "Randomization/download_allocation_file.php", "Randomization/download_allocation_file_template.php",
        "Reports/report_export.php", "SendIt/download.php", "Surveys/participant_export.php", "DataEntry/file_download.php",
        "PdfController:index", "ControlCenter/pub_matching_ajax.php", "ControlCenter/create_user_bulk.php",
        "DataQuality/data_resolution_file_download.php", "DataQuality/field_comment_log_export.php",
        "DataExport/file_export_zip.php", "Design/zip_instrument_download.php", "ControlCenter/check.php",
        "AlertsController:downloadAttachment", "UserController:downloadCurrentUsersList",
        // The pages below aren't used for file downloads, but we need to disable GZIP on them anyway
        // (often because their output is so large that it uses too much memory to keep in buffer).
        "DataExport/index.php"
    ];
    // List of all API methods that have an export method
    public static $apiExportMethods = [
        'record', 'report', 'log', 'arm', 'dag', 'userDagMapping', 'event', 'exportFieldNames', 'file', 'fileRepository',
        'instrument', 'pdf', 'metadata', 'formEventMapping', 'repeatingFormsEvents', 'surveyLink', 'participantList',
        'surveyQueueLink', 'surveyReturnCode', 'user', 'userRole', 'userRoleMapping'
    ];
	// Flag for discerning if user just performed a successful login in the current request
    public static $userJustLoggedIn = false;
	// Part of the config settings encryption key for encrypting/decrypting config values, such as 3rd party passwords
	const CONFIG_SETTINGS_ENCRYPTION_KEY = "k5Vd%ttDR5H%o1@vsD^RWc$^GaCT6%GzuUUFadYiBEyZxEOXrUeaFgqQb3CYi&iEddoVjR6H*TIn";
	// List of redcap_config keys that should be stored in encrypted format
	public static $encryptedConfigSettings = array(
            "amazon_s3_secret", "azure_app_secret", "file_upload_vault_filesystem_password", "record_locking_pdf_vault_filesystem_password",
            "pdf_econsent_filesystem_password", "two_factor_auth_twilio_auth_token", "two_factor_auth_duo_skey", "oauth2_azure_ad_client_secret", "google_oauth2_client_secret",
            "google_cloud_storage_api_service_account", "google_recaptcha_secret_key", "fhir_client_secret", "mandrill_api_key", "sendgrid_api_key", "mailgun_api_key",
            "openid_connect_client_secret", "bioportal_api_token", "fieldbank_nih_cde_key", "openai_api_key", "geminiai_api_key",
            // Used in REDCap release deployment
            "redcap_version_release_api_token", "redcap_version_release_github_token", "answerhub_api_password"
    );
	private static $MYSQLI_TYPE_MAP = [
		'boolean' => 'i',
		'integer' => 'i',
		'double' => 'd',
		'string' => 's',
		'NULL' => 's',
	];
    const POST_REDIRECT_PARAM = "__redcap_post_redirect";
    const POST_REDIRECT_PARAM_RECEIVED = "__redcap_post_redirect_received";
    const POST_REDIRECT_SESSION_ID = "__redcap_redirect_session_id";
	const UNLIMITED_DELETE_OR_UPDATE_MESSAGE = 'Delete and update queries for the redcap_data table must be limited by project or event ID.';
	const REPLICA_LAG_MAX = 3; // Time (in seconds) that is considered the maximum lag time that the Read Replica DB can have in order to be utilized
    public static $replicaLagTime = null;

	/**
	 * Checks whether the given user id corresponds to the "survey respondent" user
	 * @param string $user_id 
	 * @return bool 
	 */
	public static function isSurveyRespondent($user_id) {
		return $user_id === self::SURVEY_RESPONDENT_USERID;
	}

	// Disable error reporting
	public static function setErrorReporting()
	{
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		ini_set('log_errors', 1);
		error_reporting(0);
		// Enable error reporting for CI
		if (isset($_SERVER['MYSQL_REDCAP_CI_HOSTNAME'])) {
			error_reporting(E_ERROR);
		}
		// Enable error reporting always for REDCap developers
		elseif (isDev() || isset($_GET['__display_errors'])) {
		    error_reporting(E_ALL); // For __display_errors=1, it will be reverted if current user is not an authenticated REDCap admin
		}
		// To enable all error reporting, uncomment the next line.
		// error_reporting(E_ALL);
	}

	// Disable error reporting for all EXCEPT for authenticated REDCap admins (specifically when using __display_errors=1 in the query string)
	public static function disableErrorReportingForNonAdmins()
	{
		if ((!defined("SUPER_USER") || (defined("SUPER_USER") && !SUPER_USER)) && isset($_GET['__display_errors'])) {
			error_reporting(0);
		}
	}

	// Return boolean if the read replica db connection variables are found in the database.php file. They'll be in the global scope.
	public static function readReplicaConnVarsFound()
	{
        global $read_replica_hostname, $read_replica_username, $read_replica_password, $read_replica_db;
        return (isset($read_replica_hostname) && $read_replica_hostname != '' && isset($read_replica_username) && isset($read_replica_password) && isset($read_replica_db));
	}

	// Return the lag time (Seconds_Behind_Master) in seconds for the Read Replica DB
	public static function getReadReplicaLagTime()
	{
		if (!isset($GLOBALS['rc_replica_connection'])) return false;

        // If we've already set the lag time for the current script, just return it
        if (isinteger(System::$replicaLagTime)) {
            return System::$replicaLagTime;
        }

        // Default value
        $Seconds_Behind_Master = false;

        // Get lag time from Seconds_Behind_Master
		$q = mysqli_query($GLOBALS['rc_replica_connection'], "SHOW SLAVE STATUS");
        if ($q && db_num_rows($q)) {
            $row = db_fetch_assoc($q);
            if (isset($row['Seconds_Behind_Master']) && isinteger($row['Seconds_Behind_Master'])) {
                $Seconds_Behind_Master = $row['Seconds_Behind_Master']*1;
            }
        }

        // It seems that we can't always trust Seconds_Behind_Master (not sure why), but we can always query
        // redcap_log_view on replica to compare with NOW constant to determine true lag
        $sql = "select TIMESTAMPDIFF(SECOND, ts, '".NOW."') from redcap_log_view order by log_view_id desc limit 1";
        $q = mysqli_query($GLOBALS['rc_replica_connection'], $sql);
        if ($q && db_num_rows($q)) {
            $lagTimeLogViewCompare = db_result($q, 0);
            if (isinteger($lagTimeLogViewCompare)) {
                $Seconds_Behind_Master = $lagTimeLogViewCompare*1;
                if ($Seconds_Behind_Master < 0) $Seconds_Behind_Master = 0; // In some cases, this ends up as -1.
            }
        }

        // Set value to cache the lag time in case this method is called again
        if ($Seconds_Behind_Master < 0) $Seconds_Behind_Master = 0;
        System::$replicaLagTime = $Seconds_Behind_Master;

		return $Seconds_Behind_Master;
	}

	// Determine if the Read Replica is enabled in the redcap_config table
	public static function readReplicaEnabledInConfig()
	{
        $sql = "select value from redcap_config where field_name = 'read_replica_enable'";
        $q = mysqli_query($GLOBALS['rc_connection'], $sql);
        if (!db_num_rows($q)) return false;
        $row = db_fetch_assoc($q);
        return ($row['value'] == '1');
	}

	// Determine if the Read Replica DB's lag time is too high to be utilized.
    // If $pid is provided, then use readReplicaLagTimeIsHigherThanLastWriteLogEvent() to maximize usage of the replica
    // (because it allows us to use the replica when lag is large but still less than the last write logged event).
	public static function readReplicaLagTimeIsTooHigh($pid=null)
	{
        if ($pid != null) {
            return self::readReplicaLagTimeIsHigherThanLastWriteLogEvent($pid);
        } else {
            $replicaLagTime = System::getReadReplicaLagTime();
            return (!is_numeric($replicaLagTime) || $replicaLagTime > System::REPLICA_LAG_MAX);
        }
	}

	// Determine if the Read Replica DB's lag time is higher than the time (seconds ago) of a project's last write log event.
    // If returns FALSE, then it is fine to use the replica.
	public static function readReplicaLagTimeIsHigherThanLastWriteLogEvent($project_id)
	{
        if (!isinteger($project_id) || $project_id < 0) return true;
		$replicaLagTime = System::getReadReplicaLagTime();
        $timeSinceLastWriteLogEvent = Project::timeSinceLastWriteLogEvent($project_id);
        $lagFudgeFactor = defined("API") ? 2 : 1; // To deal with rounding issues on timestamps and also to compensate for rapid write-then-read API calls, add a fudge factor to prevent pulling stale data
        return (!is_numeric($replicaLagTime) || !is_numeric($timeSinceLastWriteLogEvent) || ($replicaLagTime + $lagFudgeFactor) > $timeSinceLastWriteLogEvent);
	}

	// Improve server performance by using a READ REPLICA DATABASE (if set up in database.php) on specific endpoints/pages/processes
	public static function useReadReplicaDB()
	{
		$uri = $_SERVER['PHP_SELF'];

		// FULL LIST - Use the read replica for the following situations:
		return ((
			// PROJECT PAGES
			isset($_GET['pid']) && (
				// Project Dashboard
				(isset($_GET['route']) && isset($_GET['dash_id']) && $_GET['route'] == 'ProjectDashController:view' && ends_with($uri, 'index.php'))
                // Project-level endpoint for building the record list cache
                || (isset($_GET['route']) && $_GET['route'] == 'DataEntryController:buildRecordListCache' && ends_with($uri, 'index.php'))
                // Survey endpoint for building the record list cache (URL contains the PID)
                || (isset($_GET['__passthru']) && $_GET['__passthru'] == 'DataEntryController:buildRecordListCache' && ends_with($uri, 'surveys/index.php'))
				// Logging page and CSV export
				|| (ends_with($uri, 'Logging/index.php'))
				|| (ends_with($uri, 'Logging/csv_export.php'))
				// Add/Edit Records
				// || (ends_with($uri, 'DataEntry/record_home.php') && !isset($_GET['id']))  #### Is this too risky since users might go here right after entering data?
				// || (ends_with($uri, 'DataEntry/index.php') && !isset($_GET['id']))  #### Is this too risky since users might go here right after entering data?
				// Record Status Dashboard
				|| (ends_with($uri, 'DataEntry/record_status_dashboard.php'))
				// Data Exports, Reports, and Stats & Charts
				|| (ends_with($uri, 'DataExport/report_ajax.php'))
				|| (ends_with($uri, 'DataExport/data_export_ajax.php'))
				|| (ends_with($uri, 'DataExport/data_export_csv.php'))
				|| (ends_with($uri, 'DataExport/index.php') && isset($_GET['stats_charts']) && isset($_GET['report_id']))
				|| (ends_with($uri, 'DataExport/plot_chart.php'))
				// Search and Auto-Complete
				|| (ends_with($uri, 'DataEntry/auto_complete.php'))
				|| (ends_with($uri, 'DataEntry/search.php'))
				// Data History popup
				|| (ends_with($uri, 'DataEntry/data_history_popup.php'))
				// Scheduling (long record drop-down list)
				|| (ends_with($uri, 'Calendar/scheduling.php'))
				// Data Quality rules and Data Resolution Workflow
				|| (ends_with($uri, 'DataQuality/execute_ajax.php') && !(isset($_GET['action']) && $_GET['action'] == 'fixCalcs')) // Exclude when doing calc fixes via DQ rule H
				|| (ends_with($uri, 'DataQuality/download_dq_discrepancies.php'))
				|| (ends_with($uri, 'DataQuality/metrics.php'))
				|| (ends_with($uri, 'DataQuality/metrics_ajax.php'))
				|| (ends_with($uri, 'DataQuality/resolve.php'))
				|| (ends_with($uri, 'DataQuality/resolve_csv_export.php'))
			)
		) || (
			// NON-PROJECT PAGES
			!isset($_GET['pid']) && (
				// Control Center pages
				ends_with($uri, 'ControlCenter/stats_ajax.php')
				|| ends_with($uri, 'ControlCenter/system_stats.php')
				|| (ends_with($uri, 'ControlCenter/mysql_dashboard.php') && isset($_GET['db_server']) && $_GET['db_server'] == 'replica')
				|| ends_with($uri, 'ControlCenter/todays_activity.php')
				// Public Project Dashboard
				|| (ends_with($uri, 'surveys/index.php') && isset($_GET['__dashboard']) && is_string($_GET['__dashboard']) && !isset($_GET['a']) && !isset($_GET['sq']) && !isset($_GET['s']) && !isset($_GET['hash']))
				// Specific API exports (data, report, logging, file export, etc.)
                || (defined("API") && isset($_POST['token']) && !isset($_POST['data']) && ((!isset($_POST['action']) || $_POST['action'] == "export")
                        && isset($_POST['content']) && in_array($_POST['content'], System::$apiExportMethods)))
			)
		));
	}

	// Initialize any general request
	public static function init()
	{
        // Check minimum required PHP version
        if (version_compare(self::getPhpVersion(), self::getMinPhpVersion(), '<')) {
            $msg = "<div style='max-width:650px;'>
                        PHP COMPATIBILITY ERROR: The current version of REDCap requires PHP ".self::getMinPhpVersion()." or higher. 
                        Currently, you are running PHP ".self::getPhpVersion().". REDCap cannot function until you upgrade to one of the following PHP versions: ".implode(", ", self::$recommendedPhpVersions).".
                        It is recommended that you consult with your local IT/server admins to get your REDCap web server upgraded to one of those versions of PHP soon.
                    </div>";
            exit($msg);
        }
        // Require vendor autoload
		require_once(dirname(__DIR__)."/vendor/autoload.php");
		// Set flag to know that we've already run this method so that it doesn't get run again
		if (defined("REDCAP_INIT")) return;
		define("REDCAP_INIT", true);
		// Disable error reporting
		self::setErrorReporting();
		// Prevent caching and set various security-related headers
		self::setCacheControlAndOtherHeaders();
		// Set value used when reading uploaded CSV files
		if (version_compare(System::getPhpVersion(), '8.1.0', '<')) {
            ini_set('auto_detect_line_endings', true);
		}
		// Set mbstring substitute_character to none
		ini_set('mbstring.substitute_character', 'none');
		// Make sure the character set is UTF-8
		ini_set('default_charset', 'UTF-8');
		// Set API key for Google Maps API v3
		defined("GOOGLE_MAP_KEY") or define("GOOGLE_MAP_KEY", "AIzaSyCN9Ih8gzAxfPmvijTP8HsE0PAKU8X1Nt0");
		// Set whether or not Multibyte String extension is installed in PHP
		define("MBSTRING_ENABLED", function_exists('mb_detect_encoding'));
		// Define DIRECTORY_SEPARATOR as DS for less typing
		defined("DS") or define("DS", DIRECTORY_SEPARATOR);
		// Get current date/time to use for all database queries (rather than using MySQL's clock with now())
		define("SCRIPT_START_TIME", microtime(true));
		defined("NOW") 	 or define("NOW", date('Y-m-d H:i:s'));
		defined("TODAY") or define("TODAY", date('Y-m-d'));
		defined("today") or define("today", TODAY); // The lower-case version of the TODAY constant allows for use in Data Quality rules (e.g., datediff)
		defined("now")   or define("now", NOW); 	// The lower-case version of the NOW constant allows for use in Data Quality rules (e.g., datediff)
		// Set class autoload function
		$GLOBALS['rc_autoload_function'] = 'System::classAutoloader';
		spl_autoload_register($GLOBALS['rc_autoload_function']);
		// Call Composer autoload file
        require dirname(dirname(__FILE__)) . DS . "Libraries" . DS . "vendor" . DS . "autoload.php";
		// Make sure dot is added to include_path in case it is missing. Also add path to Classes/PEAR inside REDCap.
		set_include_path('.' . PATH_SEPARATOR .
						dirname(dirname(__FILE__)) . DS . 'Libraries' . DS . 'PEAR' . DS . PATH_SEPARATOR .
						get_include_path());
		// Increase memory limit in case needed for intensive processing
		self::increaseMemory(1024);
		// Increase initial server value to account for a lot of processing
		self::increaseMaxExecTime(1200);
		// Set the HTML tags that are allowed for use in user-defined labels/text (e.g., field labels, survey instructions)
		define('ALLOWED_TAGS', '<ruby><wbr><blockquote><address><progress><meter><abbr><input><button><col><colgroup><strike><s><style><code><video><audio><source><caption><canvas><ol><ul><li><label><pre><p><a><br><center><font><b><i><u><h6><h5><h4><h3><h2><h1><hr><table><tbody><tr><th><td><thead><tfoot><img><span><div><em><strong><acronym><sub><sup><map><area>');
		// Set error handler
		set_error_handler('System::REDCapErrorHandler');
		// Register all functions to be run at shutdown of script
		register_shutdown_function('System::beginShutdown');
		register_shutdown_function('Logging::updateLogViewRequestTime');
		register_shutdown_function('Session::writeClose');
		register_shutdown_function('System::fatalErrorShutdownHandler');
		// Set session handlers and session cookie params
        Session::preInit();
		// Enable output to buffer
		ob_start();
		// Determine and set the client's OS, browser, and if a mobile device
		self::detectClientSpecs();
		// Make initial database connection
		db_connect();
		// Set error reporting again (in case we need to reenable it for development servers)
		self::setErrorReporting();
		// Clean $_GET and $_POST to prevent XSS and SQL injection
		self::cleanGetPost();
		// Pull values from redcap_config table and set as global variables
		self::setConfigVals();
		// If the server has been flagged as a dev/test/staging server, then enable full
		// error reporting to help with developer troubleshooting
		if (isset($GLOBALS['is_development_server']) && $GLOBALS['is_development_server'] == '1') {
			error_reporting(E_ALL);
		}
		// Set Access-Control-Allow-Origin header
		self::setCrossDomainHttpAccessControl();
		// Set X-Frame-Options header
		self::setClickJackingControl();
        // Set Content-Security-Policy header
        self::setCspHeader();
		// Remove X-Powered-By header
		self::removeXPoweredByHeader();
		// Check content length max size for POST requests (e.g., if uploading massive files)
		self::checkUploadFileContentLength();
		// Prevent users from accessing Views directly in their web browser (for security reasons)
		self::preventDirectViewAccess();
        // Init Twilio framework classes (in case Twilio SMS or Voice Calls are used)
        if (isset($GLOBALS['twilio_enabled_global']) && $GLOBALS['twilio_enabled_global'] == '1' || (isset($GLOBALS['two_factor_auth_enabled']) && $GLOBALS['two_factor_auth_enabled'] == '1' && $GLOBALS['two_factor_auth_twilio_enabled'] == '1')) {
            TwilioRC::init();
        }
        // Authenticate the user (use global auth value to authenticate global pages witih $auth_meth_global variable)
        $GLOBALS['auth_meth'] = $GLOBALS['auth_meth_global']??null;
	}

	// Remove the X-Powered-By header so that it doesn't reveal the PHP version, which is the default behavior
	private static function removeXPoweredByHeader()
	{
		header_remove("X-Powered-By");
	}

	// Ensure we don't overload max_input_vars
	private static function checkPostParamCount()
	{
		global $lang;
		if (isset($_POST) && count($_POST, COUNT_RECURSIVE) > ini_get('max_input_vars'))
		{
			$max_input_vars_msg = $lang['system_config_605'] . " " . ini_get('max_input_vars') . $lang['period'];
            if (defined("API") && API) {
				API::outputApiErrorMsg($max_input_vars_msg);
			} else {
				exit($lang['global_01'].$lang['colon']." ".$max_input_vars_msg);
			}
		}
	}

	/**
	 * add HSTS security headers
	 * forces a client to access REDCap using https
	 * 
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Strict-Transport-Security
	 */
	private static function addHstsHeaders()
	{
		if(!defined("SSL") || SSL==false) return;
		if(($GLOBALS["disable_strict_transport_security_header"] ?? null) == "1") return;
		// It is recommended to set the max-age to a big value like 31536000 (12 months) or 63072000 (24 months).
		$max_age = 31536000; // The time, in seconds, that the browser should remember that a site is only to be accessed using HTTPS.
		header("strict-transport-security: max-age=$max_age; includeSubDomains");
	}

	// Creates response header with X number of random characters. This helps mitigate hackers attempting a BREACH attack.
	// ONLY perform this if GZIP is enabled (because BREACH is only effective when HTTP compression is enabled).
	private static function addHeaderRandomText()
	{
		// If Gzip enabled, then output it
		if (defined("GZIP_ENABLED") && GZIP_ENABLED) {
			// Set max number of characters
			$maxChars = 64;
			// Get random number between 1 and $maxChars
			$numChars = random_int(1, $maxChars);
			// Build random text to place inside header
			$randomText = generateRandomHash($numChars);
			// Set header
			header("REDCap-Random-Text: $randomText");
		}
	}

	// Prevent users from accessing Views directly in their web browser (for security reasons)
	private static function preventDirectViewAccess()
	{
		// Are we access a view directly? If not, then return.
		if (strpos($_SERVER['PHP_SELF'], "/redcap_v" . REDCAP_VERSION . "/Views/") === false) return;
		// We are, so redirect to Views/index.html to display error message.
		include dirname(dirname(__FILE__)) . "/Views/index.html";
		exit;
	}

	// Set cache control to prevent caching
	public static function setCacheControlAndOtherHeaders()
	{
		header("Expires: 0");
		header("Cache-control: no-store, no-cache, must-revalidate");
		header("Pragma: no-cache");
		// Also set some security-related headers
        header("X-XSS-Protection: 1; mode=block");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
		header("Permissions-Policy: accelerometer=*, autoplay=*, camera=*, encrypted-media=*, fullscreen=*, geolocation=*, gyroscope=*, magnetometer=*, microphone=*, midi=*, payment=*, picture-in-picture=*, publickey-credentials-get=*, sync-xhr=*, usb=*, xr-spatial-tracking=*");
	}

	// Set Content-Security-Policy header, but not if it exists already. This allows institutions to override REDCap's CSP header.
	public static function setCspHeader()
	{
        $headers = headers_list();
        $csp_exists = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Security-Policy') !== false) {
                $csp_exists = true;
                break;
            }
        }
        if (!$csp_exists) {
            header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval' data: blob:; script-src * 'unsafe-inline' 'unsafe-eval' data: blob:; style-src * 'unsafe-inline' data: blob:;");
        }
	}

	// Determine if the web server running PHP is any type of Windows OS (boolean)
	public static function isWindowsServer()
	{
		return ((defined('PHP_OS') && strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') || (strtoupper(substr(php_uname('s'), 0, 3)) == 'WIN'));
	}

	// Find real IP address of user
	public static function clientIpAddress()
	{
	    // Check for IP in several places
		$ip = (empty($_SERVER['HTTP_CLIENT_IP']) ? (empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? ($_SERVER['REMOTE_ADDR'] ?? null) : $_SERVER['HTTP_X_FORWARDED_FOR']) : $_SERVER['HTTP_CLIENT_IP']);
		// If we're using HTTP_X_FORWARDED_FOR, then make sure it doesn't contain spaces or commas, which can happen with certain load balances (e.g. "1.2.3.4, 5.6.7.8, 9.10.1.12")
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && $ip == $_SERVER['HTTP_X_FORWARDED_FOR'] && strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ",")) {
            list ($ip, $nothing) = explode(",", $_SERVER['HTTP_X_FORWARDED_FOR'], 2);
        }
		// If IP is "::1", which is an ipv6 loopback address for localhost, then just return 127.0.0.1 as equivalent.
		if ($ip == "::1") $ip = "127.0.0.1";
        // Check for IPv6 format
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $ip;
        }
        // Continue to check for IPv4 format
		// Some load balancers/proxies/WAFs add the port number to the HTTP_X_FORWARDED_FOR - 1.2.3.4:12345 - this needs to be removed
		if (isset($ip) && strpos($ip, ":") !== false) {
			list ($ip, $nothing) = explode(":", $ip, 2);
		}
		// Validate and return the IP
		$ip = filter_var($ip, FILTER_VALIDATE_IP);
		return ($ip === false ? '' : $ip);
	}

	// Increase PHP web server max_execution_time value in seconds, if lower (if higher, then leave as is)
	public static function increaseMaxExecTime($seconds)
	{
		if (isinteger($seconds) && ini_get('max_execution_time') < $seconds) {
			ini_set('max_execution_time', $seconds);
			@set_time_limit($seconds);
		}
	}

	// Increase PHP web server memory to a given value in MB, if lower (if higher, then leave as is)
	public static function increaseMemory($mb)
	{
		if (is_numeric($mb) && self::getMemoryLimit() < $mb) {
			ini_set('memory_limit', $mb . 'M');
		}
	}


	// Return the PHP web server memory limit in MB
	public static function getMemoryLimit()
	{
		$unitMultiplier = stripos(ini_get('memory_limit'), 'g') ? 1024 : 1;
		return preg_replace("/[^0-9]/", "", ini_get('memory_limit')) * $unitMultiplier;
	}

	// Set Access-Control-Allow-Origin header
	public static function setCrossDomainHttpAccessControl()
	{
		global $cross_domain_access_control;
		if (!isset($cross_domain_access_control) || trim($cross_domain_access_control) == '') {
			// Allow all origins
			header("Access-Control-Allow-Origin: *");
		} else {
			// Parse the domains and set each as allowed
			$cross_domain_access_control = str_replace(array("\r\n","\r"), array("\n","\n"), trim($cross_domain_access_control));
			$allowed_domains = explode("\n", $cross_domain_access_control);
			// Add self
			list ($server_name, $port, $ssl, $page_full) = getServerNamePortSSL();
			$allowed_domains[] = "http://" . $server_name;
			$allowed_domains[] = "https://" . $server_name;
			// Is HTTP_ORIGIN in our allowed list, including REDCap itself?
			if ($_SERVER['HTTP_ORIGIN'] != null && in_array($_SERVER['HTTP_ORIGIN'], $allowed_domains)) {
				header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            }
		}
	}

	// Set X-Frame-Options header
	public static function setClickJackingControl()
	{
		// If we are performing an EHR Launch for CDP, then ignore
        if (FhirLauncher::inEhrLaunchContext() != false || basename($_SERVER['PHP_SELF']) == 'ehr.php') {
			return;
		}

        // If enabled (or if on Password Recovery page), then set header
		if ($GLOBALS['clickjacking_prevention']??null == '1' || basename($_SERVER['PHP_SELF']) == 'password_recovery.php') {
			header('X-Frame-Options: SAMEORIGIN');
		}
	}

	/**
	 * AUTOLOAD CLASSES
	 * Function will autoload the proper class file when the class is called
	 */
	public static function classAutoloader($className)
	{
		// Remove namespace if prepended to class name
		$classNameArray = explode("\\", $className);
		$className = array_pop($classNameArray);
		// Main REDCap version directory
		$main_dir = dirname(dirname(__FILE__));
		// First, try Classes directory
		$classPath = $main_dir . DS . "Classes" . DS . $className . ".php";
		if (file_exists($classPath) && include_once $classPath) return;
		// Now try Controllers directory
		$classPath = $main_dir . DS . "Controllers" . DS . $className . ".php";
		if (file_exists($classPath) && include_once $classPath) return;
		// Now try Libraries directory
		$classPath = $main_dir . DS . "Libraries" . DS . $className . ".php";
		if (file_exists($classPath) && include_once $classPath) return;
	}

	// Obtain values from redcap_config table
	public static function getConfigVals()
	{
		global $db;
		$vars = array();
        $sql = "select * from redcap_config 
                order by if (field_name = 'config_settings_key', 0, 1), field_name"; // Sort to allow config_settings_key to be first (since it needs to get set to global)
		$q = db_query($sql);
		if (!$q && basename($_SERVER['PHP_SELF']) != 'install.php')
		{
			$installPage = (substr(basename(dirname($_SERVER['PHP_SELF'])), 0, 8) == 'redcap_v'
							|| substr(basename(dirname(dirname($_SERVER['PHP_SELF']))), 0, 8) == 'redcap_v')
							? '../install.php' : 'install.php';
			// If table doesn't exist or something is wrong with it, tell to re-install REDCap.
			print  "<div style='max-width:700px;'>ERROR: Could not find the \"redcap_config\" database table in the MySQL database named \"$db\"!<br><br>
					It looks like the REDCap database tables were not created during installation, which means that you may still
					need to complete the <a href='$installPage'>installation</a>. If you did complete the installation, then
					you may have accidentally created the REDCap database tables in the wrong MySQL database (if so, please check if
					they exist in the \"$db\" database).</div>";
			exit;
		}
		while ($row = db_fetch_assoc($q))
		{
            $val = $row['value'] ?? "";
			// If this is the config_settings_key setting, add it to GLOBALS for use elsewhere during processing
			if ($row['field_name'] == 'config_settings_key') {
                if ($val != '') {
	                $GLOBALS['config_settings_key'] = $val;
                } elseif ($val == '' && !isset($GLOBALS['config_settings_key'])) {
                    $val = self::getConfigSettingsKeyDb();
                }
			}
            // Make sure redcap_base_url has slash on end
			elseif ($row['field_name'] == 'redcap_base_url' && $val != '' && substr($val, -1) != '/') {
				$val .= '/';
			}
            // Do we need to encrypt/decrypt any sensitive settings (passwords stored in redcap_config)?
            if ($val != "" && in_array($row['field_name'], self::$encryptedConfigSettings))
            {
                // Is it already encrypted? If not, encrypt it and store it as encrypted.
                if (self::isConfigSettingEncrypted($val)) {
                    // Decrypt the value
					$valDecrypted = self::decryptConfigSetting($val);
					if ($valDecrypted !== false) {
						// Set non-encrypted value to use
						$val = $valDecrypted;
					}
                } else {
                    // Encrypt it and store it
					$valEncrypted = self::encryptConfigSetting($val);
					if ($valEncrypted !== false) {
						updateConfig($row['field_name'], $valEncrypted);
					}
                }
            }
            // Add non-encrypted val to array
			$vars[$row['field_name']] = $val;
		}
		// If auto logout time is set to "0" (which means 'disabled'), then set to 1 day ("1440") as the upper limit.
		if ($vars['autologout_timer'] == '0' || $vars['autologout_timer'] == '')
		{
			$vars['autologout_timer'] = 1440;
		}
		// Return variables
		return $vars;
	}

	// Obtain values from redcap_config table and set as global variables
	public static function setConfigVals()
	{
        if (basename($_SERVER['PHP_SELF']) != "install.php") {
			foreach (self::getConfigVals() as $field_name => $value) {
				// Set field as global variable
				$GLOBALS[$field_name] = $value;
				// If using a proxy server, set variable as a constant
				if ($field_name == 'proxy_hostname') {
                    defined("PROXY_HOSTNAME") or define("PROXY_HOSTNAME", ($value == "" ? "" : trim($value)));
				} elseif ($field_name == 'proxy_username_password') {
                    defined("PROXY_USERNAME_PASSWORD") or define("PROXY_USERNAME_PASSWORD", ($value == "" ? "" : trim($value)));
				}
			}
		}
		// this *EXPERIMENTAL* code can cause *SYSTEM INSTABILITY* if set to true
		if (!array_key_exists('pub_matching_experimental', $GLOBALS)) {
			$GLOBALS['pub_matching_experimental'] = false;
		}
		// Force rApache to be disabled despite back-end value (service was retired in 5.12.0)
		if ($GLOBALS['enable_plotting']??null == '1') $GLOBALS['enable_plotting'] = '2';
		// If we are automating everything (for demo purposes, etc.), then make sure certain
		// config settings are set to allow this (just in case not set manually)
		if (defined("AUTOMATE_ALL")) {
			$GLOBALS['superusers_only_create_project'] = '0';
			$GLOBALS['superusers_only_move_to_prod'] = '0';
		}
		// If using secondary MySQL user, and its password is stored in plain text (temporary), then encrypt it and replace it
		if (isset($GLOBALS['redcap_updates_password_encrypted']) && $GLOBALS['redcap_updates_password_encrypted'] == '0' && $GLOBALS['redcap_updates_password'] != '' && strpos($_SERVER['PHP_SELF'], "/ControlCenter/") !== false && basename($_SERVER['PHP_SELF']) != "install.php")
		{
			$GLOBALS['redcap_updates_password'] = encrypt($GLOBALS['redcap_updates_password']);
			$GLOBALS['redcap_updates_password_encrypted'] = '1';
			updateConfig('redcap_updates_password_encrypted', $GLOBALS['redcap_updates_password_encrypted']);
			updateConfig('redcap_updates_password', $GLOBALS['redcap_updates_password']);
			// Reload the page so that this change is reflected
			redirect($_SERVER['REQUEST_URI']);
		}
		// Set the default redcap_log_event* table to be used (will be overwritten by project-specific value in a project context)
		$GLOBALS['log_event_table'] = 'redcap_log_event';
		// Set REDCap version as a constant
        defined("REDCAP_VERSION") or define("REDCAP_VERSION", $GLOBALS['redcap_version']);
	}

	// Make sure the PHP version is compatible (only run on Upgrade and Install pages)
	public static function checkMinPhpVersion()
	{
		global $redcap_version;
		// Skip this check when on the install page
		if (basename($_SERVER['PHP_SELF']) == "install.php") return;
		// Make sure the version folder for the current version exists (in case someone accidentally removed it after upgrading)
		$redcapSubDirs = getDirFiles(dirname(dirname(dirname(__FILE__))));
		if (!in_array("redcap_v$redcap_version", $redcapSubDirs)) {
			exit("<p style='margin:30px;width:700px;'>
				<b>ERROR: REDCAP DIRECTORY IS MISSING!</b><br>
				The directory for your current REDCap version (".dirname(dirname(dirname(__FILE__))).DS."redcap_v$redcap_version".DS.")
				cannot be found. It may have been mistakenly removed.
				REDCap version $redcap_version cannot operate without its corresponding version directory.
				Please restore the redcap_v$redcap_version directory on your web server. This may require
				re-downloading the REDCap upgrade zip package and obtaining the directory from the zip file.
				</p>");
		}
		// Get version number from directory and compare to db's REDCap version.
		// If different and we are NOT on the upgrade page, then return.
		if (basename(dirname(dirname(__FILE__))) != $redcap_version && basename($_SERVER['PHP_SELF']) != "upgrade.php"
			 && basename($_SERVER['PHP_SELF']) != "install.php") return;
		// Check PHP version based on REDCap version. If outdated, display error message and stop.
		if (version_compare(System::getPhpVersion(), self::getMinPhpVersion(), '<'))
		{
			exit("<p style='margin:30px;width:750px;'>
				<b>ERROR: Current PHP version is not compatible with REDCap. Please upgrade to PHP ".System::getMinPhpVersion()." or higher.</b><br>
				You are currently running PHP ".System::getPhpVersion()." on your web server.
				REDCap ".REDCAP_VERSION." requires PHP ".System::getMinPhpVersion()." or higher. You cannot upgrade REDCap until PHP has first been upgraded.
				<a target='_blank' href='http://php.net/downloads.php'>Upgrade to PHP ".System::getMinPhpVersion()." or higher</a>
				</p>");
		}
	}

	// Get the minimum required PHP version that is supported by REDCap
	public static function getMinPhpVersion()
	{
		return self::minimum_php_version_required;
	}

	// Get the minimum required MariaDB/MySQL version that is supported by REDCap
	public static function getMinMySQLVersion()
	{
		return self::minimum_mysql_version_required;
	}

	private static function sanitizeParameters(&$array)
	{
		foreach ($array as $key=>&$value)
		{
			if (is_array($value)) {
				self::sanitizeParameters($value);
			} else {
				// Remove IE's CSS "style=x:expression(" (used for XSS attacks)
				$array[$key] = preg_replace("/(\s+)(style)(\s*)(=)(\s*)(x)(\s*)(:)(\s*)(e)([\/\*\*\/]*)(x)([\/\*\*\/]*)(p)([\/\*\*\/]*)(r)([\/\*\*\/]*)(e)([\/\*\*\/]*)(s)([\/\*\*\/]*)(s)([\/\*\*\/]*)(i)([\/\*\*\/]*)(o)([\/\*\*\/]*)(n)(\s*)(\()/i", ' (', $value);
			}
		}
	}

    // Redirects to a REDCap URL as a POST request (convert the query string params to POST params, which REDCap will convert to POST when receiving).
    // Note: This only works as a REDCap-to-REDCap redirect (not to other websites).
    public static function redirectAsPost($url, $postParams=[])
    {
        // Only allow this method to be used on the survey end-point (check 4 different variations of it)
        $allowedEndpoints = [APP_PATH_SURVEY."?", APP_PATH_SURVEY_FULL."?", APP_PATH_SURVEY."index.php?", APP_PATH_SURVEY_FULL."index.php?"];
        $endpointValid = false;
        foreach ($allowedEndpoints as $thisEndpoint) {
            if (strpos($url, $thisEndpoint) === 0) {
                $endpointValid = true;
            }
        }
        if (!$endpointValid) return;

        if (is_array($postParams)) {
            // Check the length of the encodes post values
            $encodedParams = base64_encode(encrypt(serialize($postParams)));
            // Add params to special flag in query string or session
            if (strlen($encodedParams) <= 1200) {
                // Add to query string if UR is not too long
                $url .= (strpos($url, '?') !== false ? '&' : '?') . System::POST_REDIRECT_PARAM . "=" . $encodedParams;
            } else {
                // Add as temp session value as a backup to the query string method
                $_SESSION[System::POST_REDIRECT_PARAM] = $encodedParams;
            }
        }
        // Redirect to the URL
        redirect($url);
    }

    // Convert a GET request into a POST request (not truly, but changing server constants to simulate this)
    public static function receiveAsPost()
    {
        // If already a POST request, ignore
        if (($_SERVER['REQUEST_METHOD'] ?? null) == 'POST') return;
        // Look for specific flag in query string to trigger this redirect
        if (!isset($_GET[self::POST_REDIRECT_PARAM]) && !isset($_SESSION[self::POST_REDIRECT_PARAM])) return;
        // Decrypt/unserialize the POST payload
        $decrypted = decrypt(base64_decode(isset($_GET[self::POST_REDIRECT_PARAM]) ? $_GET[self::POST_REDIRECT_PARAM] : $_SESSION[self::POST_REDIRECT_PARAM]));
        if ($decrypted === false) return;
		$postParams = unserialize($decrypted, ['allowed_classes'=>false]);
        // Remove special flag from GET
        if (isset($_GET[self::POST_REDIRECT_PARAM]))     unset($_GET[self::POST_REDIRECT_PARAM]);
        if (isset($_SESSION[self::POST_REDIRECT_PARAM])) unset($_SESSION[self::POST_REDIRECT_PARAM]);
        // Make sure everything decrypted/unserialized properly
        if (!is_array($postParams) || empty($postParams)) return;
        // Add GET params to POST
        foreach ($postParams as $key=>$val) {
            // If __session_id is passed, then store to use later when session is initialized so we can retrieve existing session info to maintain session after redirecting
            if ($key == self::POST_REDIRECT_SESSION_ID) {
                $GLOBALS[self::POST_REDIRECT_SESSION_ID] = $val;
            } else {
                $_POST[$key] = $val;
            }
        }
        // Change server variable
        $_SERVER['REQUEST_METHOD'] = 'POST';
        // Add special constant to bypass CSRF detection (since this is not really a POST request)
        define(self::POST_REDIRECT_PARAM_RECEIVED, true);
    }

	// Clean $_GET and $_POST to prevent XSS and SQL injection
	public static function cleanGetPost()
	{
		// Fix vulnerabilities for $_SERVER values that could be spoofed
		if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])
			// Do not apply this for Google App Engine because it will not work with GAE's dev environment
			&& !isset($_SERVER['APPLICATION_ID']) && !isset($_SERVER['GAE_APPLICATION']))
		{
			// Make sure we chop off end of URL if using something like .../index.php/database.php
			$_SERVER['PHP_SELF'] = substr($_SERVER['PHP_SELF'], 0, -1 * strlen($_SERVER['PATH_INFO']));
		}
		$_SERVER['PHP_SELF']     = str_replace("&amp;", "&", htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES));
		$_SERVER['QUERY_STRING']  = preg_replace("/=\s*javascript\s*:/i", "=", isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');
		$_SERVER['QUERY_STRING'] = str_replace("&amp;", "&", htmlspecialchars(isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '', ENT_QUOTES));
		$_SERVER['REQUEST_URI']  = preg_replace("/=\s*javascript\s*:/i", "=", isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
		$_SERVER['REQUEST_URI']  = str_replace("&amp;", "&", htmlspecialchars(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', ENT_QUOTES));
		// Santize $_GET array
		System::sanitizeParameters($_GET);
	}

	// Check content length max size for POST requests (e.g., if uploading massive files)
	public static function checkUploadFileContentLength()
	{
        global $lang;
		if (!(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['CONTENT_LENGTH']))) return;
		// Get server post_max_size. If ends with G instead of M, then convert to M format.
		$max_postsize = (ini_get('post_max_size') != "") ? ini_get('post_max_size') : '1';
		$max_postsize = preg_replace("/[^0-9]/", "", $max_postsize) * (stripos($max_postsize, 'g') ? 1024 : 1) * 1048576;
		if ($_SERVER['CONTENT_LENGTH'] > $max_postsize)
		{
            $max_postsize_mb = round($max_postsize/1024/1024);
			print  "<br><br>ERROR: The page you just submitted has exceeded the REDCap server's maximum submission size ($max_postsize_mb MB). 
					The request cannnot be processed. If you just uploaded a file, this error may have resulted from the file 
					being too large in its file size. A file that large simply cannnot be processed by the server, unfortunately.";
			if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
				print "<br><br><a href='".htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES)."'>Return to previous page</a>";
			}
			exit;
		}
	}

	// Determine and set the client's OS, browser, and if a mobile device
	public static function detectClientSpecs()
	{
        // Check if using Internet Explorer
        $GLOBALS['isIE'] = (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Trident') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false));

        // Detect if the current request is an AJAX call (via $_SERVER['HTTP_X_REQUESTED_WITH'])
        $GLOBALS['isAjax'] = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

        // Defaults for mobile device detection
        $GLOBALS['isTablet'] = $GLOBALS['isMobileDevice'] = $GLOBALS['isIOS'] = $GLOBALS['isIpad'] = false;
        try {
            // Detect if a mobile device (don't consider tablets mobile devices)
            $mobile_detect = new Detection\MobileDetect();
            $GLOBALS['isTablet'] = $mobile_detect->isTablet();
            $GLOBALS['isMobileDevice'] = ($mobile_detect->isMobile() && !$GLOBALS['isTablet']);
            // Detect if using iOS (or an iPad specifically)
            $GLOBALS['isIOS'] = $mobile_detect->is('iOS');
            $GLOBALS['isIpad'] = $mobile_detect->is('iPad');
        } catch (\Throwable $th) {
            // Do nothing
        }
	}

	// Redirect user to home page from a project-level page
	public static function redirectHome()
	{
		redirect(((strlen(dirname(dirname($_SERVER['PHP_SELF']))) <= 1) ? "/" : dirname(dirname($_SERVER['PHP_SELF']))));
	}

	// Run all methods for non-project-level pages
	public static function initGlobalPage()
	{
		// Initialize REDCap
		self::init();
		// Define all PHP constants used throughout the application
		self::defineAppConstants();
		// Make sure the PHP version is compatible
		self::checkMinPhpVersion();
		// Enable GZIP compression for webpages (if Zlib extension is enabled).
		self::enableGzipCompression();
		// add HSTS headers
		self::addHstsHeaders();
		// Create response header with X number of random characters
		self::addHeaderRandomText();
		// Check if the URL is pointing to the correct version of REDCap. If not, redirect to correct version.
		self::checkREDCapVersionRedirect();
		// Initialize External Modules library
		self::initExternalModules();
		// Language: Call the correct language file for global pages
		$GLOBALS['lang'] = Language::getLanguage($GLOBALS['language_global']);
		// Set pre-defined multiple choice options for Yes-No and True-False fields
		define("YN_ENUM", "1, {$GLOBALS['lang']['design_100']} \\n 0, {$GLOBALS['lang']['design_99']}");
		define("TF_ENUM", "1, {$GLOBALS['lang']['design_186']} \\n 0, {$GLOBALS['lang']['design_187']}");
		Authentication::authenticate();
        // Convert a GET request into a POST request
        self::receiveAsPost();
		// Disable error reporting for all EXCEPT for authenticated REDCap admins
		self::disableErrorReportingForNonAdmins();
		// Prevent CRSF attacks by checking a custom token
		self::checkCsrfToken();
		// Check if system has been set to Offline
		self::checkSystemStatus();
		// Count this page hit
		Logging::logPageHit();
		// Add this page viewing to log_view table
		Logging::logPageView('PAGE_VIEW', defined('USERID') ? USERID : '');
		// If the current user has any currently running queries in another mysql process, than gather than in array
		Logging::getUserCurrentQueries();
		// Ensure we don't overload max_input_vars
		self::checkPostParamCount();
		// REDCap Hook injection point: Pass NULL as project id, since this is the system context.
		Hooks::call('redcap_every_page_before_render', array(null));
		// Set flag that init is complete
		defined("REDCAP_INIT_COMPLETE") or define("REDCAP_INIT_COMPLETE", true);
	}

	// Run all methods for project-level pages
	public static function initProjectPage()
	{
		// Initialize REDCap
		self::init();
		// Define all PHP constants used throughout the application.
		self::defineAppConstants();
		// Make sure the PHP version is compatible
		self::checkMinPhpVersion();
		// add HSTS headers
		self::addHstsHeaders();
		// Enable GZIP compression for webpages (if Zlib extension is enabled).
		self::enableGzipCompression();
		// Create response header with X number of random characters
		self::addHeaderRandomText();
		// Check if the URL is pointing to the correct version of REDCap. If not, redirect to correct version.
		self::checkREDCapVersionRedirect();
		// Make sure we have either pnid or pid in query string. If not, then redirect to Home page.
		if (!Project::isProjectPage()) self::redirectHome();
		// Initialize External Modules library
		self::initExternalModules();
		// Default draft preview to disabled
		$GLOBALS["draft_preview_enabled"] = false;
		// Set Shared Library control
		$shared_library_enabled_global = $GLOBALS['shared_library_enabled'];
		// Query redcap_projects table for project-level values and set as global variables.
		$projectVals = Project::setProjectVals();
		// Bring project values into this scope
		extract($projectVals);
        // Apply any project-level db_character_set and db_collation, if set in redcap_projects table
        if (isset($project_db_character_set) && $project_db_character_set != "" && $project_db_character_set != $GLOBALS['db_character_set']) {
            self::checkDbEncoding(isset($GLOBALS['rc_replica_connection']), false);
            // Re-fetch project-level values now that we've switched db_character_set and db_collation
            $projectVals = Project::setProjectVals();
        }
		// Set array of missing data codes as global variable
		$GLOBALS['missingDataCodes'] = parseEnum($missing_data_codes);
        if (!empty($GLOBALS['missingDataCodes'])) {
            // Sanitize keys to prevent injection
            foreach (array_keys($GLOBALS['missingDataCodes']) as $mdcKey) {
                if (!is_numeric($mdcKey) && !preg_match('/^[0-9A-Za-z._\-]*$/', $mdcKey)) {
                    unset($GLOBALS['missingDataCodes'][$mdcKey]);
                }
            }
        }
		// Define constants and variables for project
		$GLOBALS['app_name'] = $_GET['pnid'] = $project_name;
		$_GET['pid'] = $project_id;
		defined("APP_NAME")   or define("APP_NAME",   $GLOBALS['app_name']);
		defined("PROJECT_ID") or define("PROJECT_ID", $project_id);
		$GLOBALS['hidden_edit'] = 0;
		// Disable Shared Library control for project if disabled globally
		if ($shared_library_enabled_global == '0') $GLOBALS['shared_library_enabled'] = '0';
		// Disable Mosio/Twilio option for project if disabled on the Edit Project Settings page
		if ($GLOBALS['twilio_enabled_global'] == '1' && $twilio_hide_in_project == '1') $GLOBALS['twilio_enabled_global'] = '0';
		if ($GLOBALS['mosio_enabled_global'] == '1' && $mosio_hide_in_project == '1') $GLOBALS['mosio_enabled_global'] = '0';
		// Check DTS global value. If disabled, then disable project-level value also.
		if (!$GLOBALS['dts_enabled_global']) $GLOBALS['dts_enabled'] = false;
		// Check randomization module's global value. If disabled, then disable project-level value also.
		if (!$GLOBALS['randomization_global']) $GLOBALS['randomization'] = 0;
		// Language: Call the correct language file for this project (default to English)
		$GLOBALS['lang'] = Language::getLanguage($project_language);
		// Set pre-defined multiple choice options for Yes-No and True-False fields
		defined("YN_ENUM") or define("YN_ENUM", "1, {$GLOBALS['lang']['design_100']} \\n 0, {$GLOBALS['lang']['design_99']}");
		defined("TF_ENUM") or define("TF_ENUM", "1, {$GLOBALS['lang']['design_186']} \\n 0, {$GLOBALS['lang']['design_187']}");
		// Object containing all project information
		$GLOBALS['Proj'] = $Proj = new Project();
		// Ensure that the field being used as the secondary id still exists as a field. If not, set $secondary_pk to blank.
		if ($secondary_pk != '' && !isset($Proj->metadata[$secondary_pk])) {
			$GLOBALS['secondary_pk'] = '';
		}
		// Determine if longitudinal (has multiple events) and multiple arms
		$GLOBALS['longitudinal'] = $longitudinal = $Proj->longitudinal;
		$GLOBALS['multiple_arms'] = $multiple_arms = $Proj->multiple_arms;
		// Protected Email Mode (global setting overrides the project-level setting)
		$GLOBALS['protected_email_mode'] = $Proj->project['protected_email_mode'];
		// Establish the record id Field Name and its Field Label
		$GLOBALS['table_pk'] = $table_pk = $Proj->table_pk;
		$GLOBALS['table_pk_phi'] = $table_pk_phi = $Proj->table_pk_phi;
		$GLOBALS['table_pk_label'] = $table_pk_label = $Proj->table_pk_label;
		// Instantiate DynamicDataPull object
		$GLOBALS['DDP'] = new DynamicDataPull(PROJECT_ID, $Proj->project['realtime_webservice_type']);
		// If surveys are not enabled global, then make sure they are also disabled for the project
		if (!$GLOBALS['enable_projecttype_singlesurveyforms']) $GLOBALS['surveys_enabled'] = 0;
		// Disable file upload version history in project if disabled at system level
		if (!Files::fileUploadVersionHistoryEnabledSystem()) $GLOBALS['file_upload_versioning_enabled'] = 0;
		// If survey_email_participant_field has a value but is no longer a real field (or is no longer email-validated), then reset it to blank.
		// Also reset to blank if surveys are not enabled for this project.
		if ($survey_email_participant_field != '' && (!isset($Proj->metadata[$survey_email_participant_field])
			|| (isset($Proj->metadata[$survey_email_participant_field])
			&& $Proj->metadata[$survey_email_participant_field]['element_validation_type'] != 'email')))
		{
			$GLOBALS['survey_email_participant_field'] = '';
		}
		// Project-level override for two-factor setting "two_factor_auth_esign_once_per_session"
		if ($GLOBALS['two_factor_auth_enabled'] == '1' && $GLOBALS['esignature_enabled_global'] == '1' && $GLOBALS['two_factor_auth_esign_pin'] == '1'
			&& in_array($two_factor_project_esign_once_per_session, ['0','1']) && $GLOBALS['two_factor_auth_esign_once_per_session'] != $two_factor_project_esign_once_per_session)
		{
			$GLOBALS['two_factor_auth_esign_once_per_session'] = $two_factor_project_esign_once_per_session;
		}
		// Disable authentication if we're on the buildRecordListCache route since it is merely a passthru with no user input and might be utilized on a NOAUTH plugin
        if (isset($_GET['route']) && isset($_GET['NOAUTH_BUILDRECORDLIST']) && $_GET['route'] == 'DataEntryController:buildRecordListCache' && strpos(PAGE_FULL, "/redcap_v".REDCAP_VERSION."/index.php") !== false) {
			define("NOAUTH", true);
        }
		// Authenticate the user
		Authentication::authenticate();
		// Disable error reporting for all EXCEPT for authenticated REDCap admins
		self::disableErrorReportingForNonAdmins();
		// Project-level user privileges
		if(!defined("ANYAUTH")){
		    $UserRights = new UserRights(true);
		}
		// SURVEY: If on survey page, start the session and manually set username to [survey respondent]
		$isSurveyPage = ((defined("PAGE") && PAGE == "surveys/index.php") || (defined("NOAUTH") && isset($_GET['s'])));
		if ($isSurveyPage)
		{
			// Initialize the PHP session for survey pages (they are different from typical REDCap sessions)
			Session::init(Session::getCookieName(true));
            // Convert a GET request into a POST request
            self::receiveAsPost();
			// Set "username" for logging purposes (static for all survey respondents) - BUT it can be overridden if $_SESSION['username'] exists
			defined("USERID") or define("USERID", strtolower(isset($_SESSION['username']) ? $_SESSION['username'] : self::SURVEY_RESPONDENT_USERID));
			// If this is a Microsoft Outlook Safe Links server making a POST request, block it here based on IP range
			$outlookSafelinksIpRange = '40.94.';
			$outlookSafelinksIpRange2 = '52.147.217.';
			if ($_SERVER['REQUEST_METHOD'] == 'POST' && (strpos(System::clientIpAddress(), $outlookSafelinksIpRange) === 0 || strpos(System::clientIpAddress(), $outlookSafelinksIpRange2) === 0)
				// Special exceptions for testing
				// && $_GET['s'] != 'ZmNRsG99fcfiBuI9' && $_GET['s'] != 'WCWRM3LTHYEX3FAD'
            ) {
				exit("BLOCKED: Sorry, but all POST requests on REDCap survey pages coming from the IP range {$outlookSafelinksIpRange}*.* "
                    ."(belonging to Microsoft Outlook Safe Links) have been blocked by REDCap. If you believe this is in error, contact your survey administrator.");
			}
		}
		// NON-SURVEY: Normal project page
		else
		{
            // Convert a GET request into a POST request
            self::receiveAsPost();
			// Prevent CRSF attacks by checking a custom token
			self::checkCsrfToken();
			// Instantiate ExternalLinks object
			$GLOBALS['ExtRes'] = new ExternalLinks();
			// If project has been scheduled for deletion, then don't display items on left-hand menu (i.e. remove user rights to everything)
			if ($GLOBALS['date_deleted'] != "" || $GLOBALS['completed_time'] != "") $GLOBALS['user_rights'] = array();
			// If using Double Data Entry, make sure users cannot use record auto numbering (since it wouldn't make sense)
			if ($GLOBALS['double_data_entry'] && $GLOBALS['auto_inc_set']) $GLOBALS['auto_inc_set'] = 0;
		}
		// Check if system has been set to Offline
		self::checkSystemStatus();
		// Check Online/Offline status of project
		self::checkOnlineStatus();
		// Count this page hit
		Logging::logPageHit();
		// Add this page viewing to log_view table
		Logging::logPageView('PAGE_VIEW', (defined("USERID") ? USERID : ""));
		// Clean up any temporary files sitting on the web server (for various reasons)
		Files::manage_temp_files();
		// If the current user has any currently running queries in another mysql process, than gather than in array
		Logging::getUserCurrentQueries();
		// Ensure repeating instance number is valid (always set default instance as 1)
		if (!isset($_GET['instance']) || !is_numeric($_GET['instance']) || $_GET['instance'] < 1) {
			$_GET['instance'] = 1;
		}
		$_GET['instance'] = (int)$_GET['instance'];
		// Ensure we don't overload max_input_vars
		self::checkPostParamCount();
		// REDCap Hook injection point: Pass PROJECT_ID constant (if defined)
		Hooks::call('redcap_every_page_before_render', array(PROJECT_ID));
		// Record List Cache: If we're on a specific page and the cache has not been built, then redirect to a specific route in order to build it
        Records::determineBuildRecordListCache();
        if ($Proj->project['mycap_enabled']) {
            // Object containing all mycap project information
            $GLOBALS['myCapProj'] = new MyCap();
        }
		// Set flag that init is complete
		defined("REDCAP_INIT_COMPLETE") or define("REDCAP_INIT_COMPLETE", true);
	}

	// Initialize External Modules library
	// Search first in /redcap/external_modules, and then in /redcap/redcap_vX.X.X/ExternalModules/
	private static function initExternalModules()
	{
		$ExtModClassPath = 'classes/ExternalModules.php';
		if (!defined("APP_PATH_EXTMOD")) {
			// First check in /redcap/external_modules (so it can serve as an override)
			if (file_exists(dirname(APP_PATH_DOCROOT) . DS . 'external_modules' . DS . $ExtModClassPath)) {
				define("APP_PATH_EXTMOD", dirname(APP_PATH_DOCROOT) . DS . 'external_modules' . DS);
				define("APP_URL_EXTMOD", APP_PATH_WEBROOT_FULL . "external_modules/");
				define("APP_URL_EXTMOD_RELATIVE", APP_PATH_WEBROOT_PARENT . "external_modules/");
				// Note that the Ext Mods installation is external/outside the REDCap version directory
				define("EXTMOD_EXTERNAL_INSTALL", true);
			} // Next check in /redcap/redcap_vX.X.X/ExternalModules/
            elseif (file_exists(APP_PATH_DOCROOT . 'ExternalModules' . DS . $ExtModClassPath)) {
				define("APP_PATH_EXTMOD", APP_PATH_DOCROOT . 'ExternalModules' . DS);
				define("APP_URL_EXTMOD", APP_PATH_WEBROOT_FULL . "redcap_v" . REDCAP_VERSION . "/ExternalModules/");
				define("APP_URL_EXTMOD_RELATIVE", APP_PATH_WEBROOT . "ExternalModules/");
				// Note that the Ext Mods installation is interal/inside the REDCap version directory
				define("EXTMOD_EXTERNAL_INSTALL", false);
			}
		}
		if (defined("APP_URL_EXTMOD"))
		{
			defined("APP_URL_EXTMOD_LIB") or define("APP_URL_EXTMOD_LIB", "https://redcap.vumc.org/consortium/modules/");
			defined("APP_PATH_MODULES") or define("APP_PATH_MODULES", dirname(APP_PATH_DOCROOT).DS.'modules'.DS);
			include_once APP_PATH_EXTMOD . $ExtModClassPath;
			// Disable authentication if accessing a module plugin page via API endpoint using NOAUTH query string param
			if (defined("API_EXTMOD") && !defined("NOAUTH") && isset($_GET['NOAUTH'])) {
				define("NOAUTH", true);
			}
		}

		self::checkForOldExternalModuleFrameworkVersionRequests();
	}

	private static function checkForOldExternalModuleFrameworkVersionRequests(){
		$parts = explode('/', APP_PATH_WEBROOT);
		$redcapVersionDirIndex = count($parts) - 2;
		$externalModulesDirIndex = $redcapVersionDirIndex + 1;
		$currentRedcapVersionDir = @$parts[$redcapVersionDirIndex];
		$requestUri = $_SERVER['REQUEST_URI'];
		$parts = explode('/', $requestUri);

		if(!isset($parts[$externalModulesDirIndex]) || (@$parts[$externalModulesDirIndex] !== 'ExternalModules')){
			// This is not a request to a bundled version of the External Module framework.
			// No further checking is required.
			return;
		}

		$redcapVersionDirFromRequest = @$parts[$redcapVersionDirIndex];
		if(strpos($redcapVersionDirFromRequest, 'redcap_v') !== 0){
			// This is not a request to a REDCap version directory.
			// No further checking is required.
			return;
		}

		if(EXTMOD_EXTERNAL_INSTALL){
			echo "The External Module framework directory is overridden on this REDCap instance.  Requests to the bundled copy of the framework are disallowed.";
			exit;
		}

		if($redcapVersionDirFromRequest !== $currentRedcapVersionDir){
			// A request is being made to an old version of the External Module framework.
			// This should not happen under normal circumstances.
			// This may be a malicious attempt to exploit bugs in older versions.
			// Redirect in case there are any legitimate cases where this might happen.
			$pos = strpos($requestUri, $redcapVersionDirFromRequest);
			$newRequestUri = substr_replace($requestUri, $currentRedcapVersionDir, $pos, strlen($redcapVersionDirFromRequest));
			header("Location: $newRequestUri");
			exit;
		}
	}

	/**
	 * ERROR HANDLING
	 */
	public static function REDCapErrorHandler($code, $message, $file, $line)
	{
		global $lang, $log_all_errors, $log_warnings, $redcapCronJobCurrent, $project_contact_email;
		$errorRendered = false;

		$errortype = array(
			E_ERROR=>"Error",
			E_WARNING=>"Warning",
			E_PARSE=>"Parsing Error",
			E_NOTICE=>"Notice",
			E_CORE_ERROR=>"Core Error",
			E_CORE_WARNING=>"Core Warning",
			E_COMPILE_ERROR=>"Compile Error",
			E_COMPILE_WARNING=>"Compile Warning",
			E_USER_ERROR=>"Error",
			E_USER_WARNING=>"Warning",
			E_USER_NOTICE=>"Notice",
			E_DEPRECATED=>"Deprecated"
		);

		if (isset($lang) && !empty($lang)) {
			$err1 = $lang['config_functions_01'];
			$err2 = $lang['config_functions_02'];
			$err3 = $lang['config_functions_03'];
			$err4 = $lang['config_functions_04'];
		} else {
			$err1 = "REDCap crashed due to an unexpected PHP fatal error!";
			$err2 = "Error message:";
			$err3 = "File:";
			$err4 = "Line:";
		}

		// If this variable is set to TRUE in database.php, then log all errors in PHP log file
		if (
			$log_all_errors
			||
			($log_warnings && $code === E_WARNING)
		)
		{
			$codeString = $errortype[$code] ?? $code;

			// Log the error
			error_log("$err2 $codeString - $message, $err3 $file, $err4 $line");
		}

		// Is this a fatal error?
		$isError = in_array($code, array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_PARSE));

		// Is this a non-fatal warning?
        $isWarning = in_array($code, array(E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING, E_DEPRECATED));

		// Should we display warnings on the page? This should always be FALSE for everyone EXCEPT the REDCap development team (typically via the PHP "DEV" constant in database.php).
        $displayWarnings = isDev(); // (error_reporting() > 0);

        // Is the code with the warning/error/notice inside the EM Framework or inside an individual external module's code?
        $isInModuleOrEMFramework = (defined("APP_PATH_MODULES") && (strpos($file, APP_PATH_MODULES) === 0 || strpos($file, APP_PATH_EXTMOD) === 0));

		// Truncate the beginning portion of paths to avoid exposing them, even to admins.
		$docRootWithoutVersionedDir = dirname(dirname(dirname(__FILE__))) . DS;
		$message = str_replace($docRootWithoutVersionedDir, '', $message);
		$file = str_replace($docRootWithoutVersionedDir, '', $file);

		// This is not a fatal error (warning or deprecated), so return false to let it fall through to the standard PHP error handler
		if ($isWarning && $displayWarnings
            // Do not ever display warnings for individual external modules or for External Module Framework
            && !$isInModuleOrEMFramework
        ) {
		    // Let standard PHP error handler output the warning (this should ONLY happen for the REDCap development team and NEVER for external module related code for ANY system)
		    return false;
		}
		// Fatal errors
		elseif ($isError)
		{
			/**
			 * It seems like we should be able to remove this method_exists() check.
			 * However, there may be cases where the ExternalModules class has not been loaded yet.
			 * The syntax "::class" is safe even if a class has not been loaded.
			 * It may be prudent to leave this in place permanently.
			 */
			if(method_exists(ExternalModules::class, 'handleFatalError') && ExternalModules::handleFatalError($code, $message, $file, $line)){
				// This error was handled by the EM Framework because it came from an External Module or the framework itself.			}
			}
			else{
				// Add error to redcap_error_log table
				self::addErrorToRCErrorLogTable("URL: {$_SERVER['REQUEST_URI']}\nError message: ".str_replace(["\t", "\r\n", "\r", "\n", "  ", "  "], " ", $message)."\nFile: $file\nLine: $line");
			}

			// Kill the MySQL process so that it doesn't continue after PHP script stops
			db_query("KILL CONNECTION_ID()");

			// If a PLUGIN calls an undefined method/function, give custom message so plugin developer may be notified
			if (defined("PLUGIN") && (strpos($message, "Call to undefined function") !== false
				|| strpos($message, "Call to undefined method") !== false))
			{
				print  "<div class='red' style='max-width:700px;'>
							<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['config_functions_87']}<br><br>
							<b>{$lang['config_functions_02']}</b> $message <br>
							<b>{$lang['config_functions_03']}</b> $file <br>
							<b>{$lang['config_functions_04']}</b> $line
						</div>";
				return;
			}

			// If a cron job fails, then email the REDCap admin
			if (defined("CRON") && $redcapCronJobCurrent != null)
			{
				$emailContents =   "<html><body style=\"font-family:arial,helvetica;\">
									REDCap Administrator,<br><br>
									The REDCap cron job named \"<b>$redcapCronJobCurrent</b>\" crashed unexpectedly on the server <b>".SERVER_NAME."</b> at <b>".NOW."</b>!<br><br>
									If you are not on the latest version of REDCap, you might consider upgrading REDCap to the latest version to see if 
									that fixes this issue. If not, you might want to post this as a Bug Report on the REDCap Community website. See details below.<br><br>
									<b>{$lang['config_functions_02']}</b> $message <br>
									<b>{$lang['config_functions_03']}</b> $file <br>
									<b>{$lang['config_functions_04']}</b> $line <br>";
				$trace = debug_backtrace();
				$emailContents .= sprintf("<b>{$lang['config_functions_126']}</b> %s <br>", print_r($trace, true));
				$emailContents .=	"</body></html>";
				$email = new Message();
				$email->setTo($project_contact_email);
				$email->setFrom(\Message::useDoNotReply($GLOBALS['project_contact_email']));
				$email->setFromName($GLOBALS['project_contact_name']);
				$email->setSubject('[REDCap] Cron job crashed!');
				$email->setBody($emailContents);
				$email->send();
			}

			// Google OAuth2 failure
			global $auth_meth_global;
			if ($auth_meth_global == 'openid_google' && strpos($message, "Error fetching OAuth2 access token") !== false)
			{
				print  "<html>
						<head><meta http-equiv='refresh' content='5' /></head>
						<body>
							<div class='red' style='max-width:700px;'>
								<b>{$lang['global_01']}{$lang['colon']} Google login failure!</b><br><br>
								We're sorry, but for unknown reasons this application is not able to connect with Google's OAuth2 authentication provider.
								Please try again in a moment, and if the issue is not resolved at that time, 
								please inform an administrator about this issue. Our apologies for any inconvenience.
							</div>
						</body>
						</html>";
				return;
			}

			// Custom message for memory overload or script timeout (all pages)
			if (defined('PAGE') && PAGE == "DataQuality/execute_ajax.php")
			{
				// Get current rule_id and the ones following
				list ($rule_id, $rule_ids) = explode(",", $_POST['rule_ids'], 2);
				// Set error message
				if (strpos($message, "Maximum execution time of") !== false) {
					// Script timeout error
					$msg = "<div id='results_table_{$rule_id}'>
								<p class='red' style='max-width:500px;'>".
									RCView::tt_i("dataqueries_354", array(
										ini_get("max_execution_time")
									))."
								</p>";
					// Set main error msg seen in table
					$dqErrMsg = RCView::getLangStringByKey("dataqueries_108");
				} else {
					// Memory overload error
					$msg = "<div id='results_table_{$rule_id}'>
								<p class='red' style='max-width:500px;'>
									<b>{$lang['global_01']}{$lang['colon']}</b> {$lang['dataqueries_32']} <b>{$_GET['error_rule_name']}</b> {$lang['dataqueries_33']}
									" . (is_numeric($rule_id) ? $lang['dataqueries_34'] : $lang['dataqueries_96']) . "
								</p>";
					// Set main error msg seen in table
					$dqErrMsg = $lang['global_01'];
				}
				// Provide super users with further context about error
				if (defined('SUPER_USER') && SUPER_USER) {
					$msg .=	"<p class='red' style='max-width:600px;'>
								<b>{$lang['config_functions_01']}</b><br><br>
								<b>{$lang['config_functions_02']}</b> $message<br>
								<b>{$lang['config_functions_03']}</b> $file<br>
								<b>{$lang['config_functions_04']}</b> $line<br>
							 </p>";
				}
				// If Rule H crashes when evaluating too many calcs on the first run, still provide an option to auto-fix them.
                // This is doable since it does this in small batches, and will at least allow users to get these fixed little by little, even though it may take a while.
				if ($rule_id == 'pd-10' && !(isset($_POST['action']) && $_POST['action'] == 'fixCalcs')) {
					$msg .= "<div class='fs15 mt-4'>
                                <img src='".APP_PATH_IMAGES."exclamation.png'>
                                <span style='margin:0 10px 0 4px;color:#800000;font-weight:normal;'>{$lang['dataqueries_292']}</span>
                                <button class='jqbuttonmed' style='border-color:#999;' onclick=executeRulesAjax('$rule_id',1,0,'fixCalcs');>{$lang['dataqueries_293']}"
                                .(($_POST['record'] == '') ? '' : " ".$lang['dataqueries_298'].' "'.RCView::escape($_POST['record']).'"')
                                ."</button>
                            </div>";
				}
				$msg .=	"</div>";
				// Send back JSON
				print '{"rule_id":"' . $rule_id . '",'
					. '"next_rule_ids":"' . $rule_ids . '",'
					. '"discrepancies":"1",'
					. '"discrepancies_formatted":"<span style=\"font-size:12px;\">'.$dqErrMsg.'</span>",'
					. '"dag_discrepancies":[],'
					. '"title":"' . RCView::escape($_GET['error_rule_name']) . '",'
					. '"payload":"' . cleanJson($msg)  .'"}';
				return;
			}

			// Return output of "0" for report and data export ajax request
			if (defined('PAGE') && (PAGE == "DataExport/data_export_ajax.php" || PAGE == "DataExport/report_ajax.php"))
			{
				exit("0");
			}

			// Render error message to super users only OR user is on Install page and can't get it to load OR GitHub Actions
			if ((defined('SUPER_USER') && SUPER_USER) || (defined('PAGE') && PAGE == "install.php") || isset($_SERVER['MYSQL_REDCAP_CI_HOSTNAME']))
			{
				?>
				<div class="red" style="margin:20px 0px;max-width:700px;">
					<b><?php echo $err1 ?></b><br><br>
					<b><?php echo $err2 ?></b> <?php echo htmlspecialchars($message) ?><br>
					<b><?php echo $err3 ?></b> <?php echo $file ?><br>
					<b><?php echo $err4 ?></b> <?php echo $line ?><br>
				</div>
				<?php
				$errorRendered = true;
			}

			// Catch any pages that timeout
			if (strpos($message, "Maximum execution time of") !== false)
			{
				// Set error message text
				$max_execution_error_msg = 	RCView::div(array('class'=>'red', 'style'=>'max-width:700px;'),
					RCView::tt_i("dataqueries_354", array(
						ini_get("max_execution_time")
					)));
				// API error only
				if (defined('PAGE') && (PAGE == "api/index.php" || PAGE == "API/index.php"))
				{
					API::outputApiErrorMsg($max_execution_error_msg);
				}
				// Non-API page
				else
				{
					exit($max_execution_error_msg);
				}
			}

			// API error only for data imports where data is not properly formatted (especially for XML)
			if (defined('PAGE') && (PAGE == "api/index.php" || PAGE == "API/index.php")
				&& strpos($message, "Cannot create references to/from string offsets nor overloaded objects") !== false)
			{
				API::outputApiErrorMsg('The data being imported is not formatted correctly');
			}

			// Custom message for memory overload (all pages)
			if (defined('PAGE') && strpos($message, "Allowed memory size of") !== false)
			{
				// Specific message for Data Import Tool
				if (PAGE == "DataImportController:index")
				{
					?>
					<div class="red" style="max-width:700px;">
						<b><?php echo $lang['global_01'] . $lang['colon'] . " " . $lang['config_functions_05'] ?></b><br>
						<?php echo $lang['config_functions_06'] ?>
					</div>
					<?php
				}
				// Specific message for PDF export
				elseif (PAGE == "PdfController:index")
				{
					?>
					<div class="red" style="max-width:700px;">
						<b><?php echo $lang['global_01'] . $lang['colon'] . " " . $lang['config_functions_80'] ?></b><br>
						<?php echo $lang['config_functions_81'] ?>
					</div>
					<?php
				}
				 // Specific message for API requests (typically import or export)
				elseif (PAGE == "api/index.php" || PAGE == "API/index.php")
				{
					exit(RestUtility::sendResponse(500, 'REDCap ran out of server memory. The request cannot be processed. Please try importing/exporting a smaller amount of data.'));
				}
				// Generic message for "out of memory" error
				else
				{
					?>
					<div class="red" style="max-width:700px;">
						<b>ERROR: REDCap ran out of memory!</b><br>
						The current web page has hit the maximum allowed memory limit (<?php echo ini_get('memory_limit') ?>B).
						<?php if (defined('SUPER_USER') && SUPER_USER) { ?>
							Super user message: You might think about increasing your web server memory used by PHP by
							changing the value of "memory_limit" in your server's PHP.INI file.
							(Don't forget to reboot the web server after making this change.)
						<?php } else { ?>
							Please contact a REDCap administrator to inform them of this issue.
						<?php } ?>
					</div>
					<?php
				}
				$errorRendered = true;
			}

			// API error only
			if (defined('PAGE') && (PAGE == "api/index.php" || PAGE == "API/index.php") && (!isset($_GET['type']) || $_GET['type'] != 'module'))
			{
				API::outputApiErrorMsg('An unknown error occurred. Please check your API parameters.');
			}

			// Give general error message to normal user or survey participant
			if (!$errorRendered)
			{
				// Add message about how to retrieve error log info from database
				$error_log_info = "";
				if (defined("RC_ERROR_LOG_ID")) {
					$error_log_info = "<div class='fs11 mt-3 mb-1 text-secondary'>{$lang['config_functions_127']}<br>
										<code class='text-secondary' style='font-family:SFMono-Regular,Menlo,Monaco,Consolas,monospace !important;'>select error from redcap_error_log where error_id = ".RC_ERROR_LOG_ID.";</code></div>";
				}
				// Render general error message
				?>
				<div class="red" style="margin:20px 0px;max-width:700px;">
					<b><?php echo $lang['config_functions_07'] ?></b><br><br>
					<?php echo $lang['config_functions_08'] . $error_log_info ?>
				</div>
				<?php
			}
		}
	}

    // Add error to redcap_error_log table
	public static function addErrorToRCErrorLogTable($errorMsg)
	{
        $sql = "insert into redcap_error_log (log_view_id, time_of_error, error) values (?, ?, ?)";
        $params = [(defined("LOG_VIEW_ID") ? LOG_VIEW_ID : null), date("Y-m-d H:i:s"), $errorMsg];
        if (db_query($sql, $params) && !defined("RC_ERROR_LOG_ID")) {
            define("RC_ERROR_LOG_ID", db_insert_id());
        }
	}

	// Method that is first called when beginning shutdown of every PHP request
	public static function beginShutdown()
	{
		define("ShutdownStarted", true);
	}

	// Method for handling fatal PHP errors
	public static function fatalErrorShutdownHandler()
	{
		// Get last error
		$last_error = @error_get_last();
		if (isset($last_error['type']) && $last_error['type'] === E_ERROR) {
			// fatal error
			self::REDCapErrorHandler(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
		}
		/**
		 * It seems like we should be able to remove this method_exists() check.
		 * However, we tried that under PR 2637 and it caused a CI failure from install.php on the following
		 * ExternalModules::handleFatalError() call because the ExternalModules class had not been loaded yet.
		 * There may be other cases where the ExternalModules class has not been loaded yet but errors could occur.
		 * The syntax "::class" is safe even if a class has not been loaded.
		 * It may be prudent to leave this in place permanently.
		 */
		else if(method_exists(ExternalModules::class, 'handleFatalError')){
			/**
			 * This could still be a die() or exit() call within a module hook,
			 * since those won't return any errors from error_get_last().
			 * This could also be some other kind of fatal error other than E_ERROR.
			 * Regardless, give the EM framework a chance to handle this scenario.
			 */
			ExternalModules::handleFatalError($last_error['type'] ?? null, $last_error['message'] ?? null, $last_error['file'] ?? null, $last_error['line'] ?? null);
		}
	}

	// Enable GZIP compression for webpages (if Zlib extension is enabled).
	// Return boolean if gzip is enabled for this "page" (i.e. request).
	public static function enableGzipCompression()
	{
		global $enable_http_compression;
		// If constants have already been set, then skip this whole function
		if (defined("GZIP_ENABLED")) return;
		// Make sure we only enable compression on visible webpages (as opposed to file downloads).
		if (!$enable_http_compression
			// Do not compress if PAGE constant is not set
			|| (!defined('PAGE'))
			// Ignore certain allowlisted pages where we don't want to use compression
			|| (defined('PAGE') && (isset($_GET['__passthru']) && PAGE == 'surveys/index.php')) // Survey page
			|| (defined('PAGE') && in_array(PAGE, System::$fileDownloadPages)) // Array of allowlisted download pages
			|| (defined('PAGE') && PAGE == 'surveys/index.php' && $_SERVER['REQUEST_METHOD'] == 'POST' && strpos($_SERVER['REQUEST_URI'], "route=SendItController") !== false) // Send-It download page
			|| (defined('API') && isset($_POST['content']) && $_POST['content'] == 'file') // API file download method
		) {
			define("GZIP_ENABLED", false);
		}
		else
		{
			// Compress the PHP output (uses up to 80% less bandwidth)
			ini_set('zlib.output_compression', 4096);
			ini_set('zlib.output_compression_level', -1);
			// Set flag if gzip is enabled on the web server
			define("GZIP_ENABLED", (function_exists('ob_gzhandler') && ini_get('zlib.output_compression')));
		}
		// Return value if gzip is now enabled
		return GZIP_ENABLED;
	}

	// Version Redirect: Make sure user is on the correct REDCap version for this project.
	// Note that $redcap_version is pulled from config table and $redcapdir_version is the version from the folder name
	// If they are not equal, then a redirect should occur so that user is accessing correct page in correct version (according to the redcap_projects table)
	public static function checkREDCapVersionRedirect()
	{
		global $redcap_version, $isAjax;
		// If we're on the LanguageCenter page, don't redirect because we may be trying to get translation file
		// to next version BEFORE we upgrade.
		if (basename(dirname($_SERVER['PHP_SELF'])) . "/" . basename($_SERVER['PHP_SELF']) == 'LanguageUpdater/index.php') {
			return;
		}
		// Set informal docroot
		$app_path_docroot = dirname(dirname(__FILE__)).DS;
		// Bypass version check for developers who are using the "codebase" directory (instead of redcap_vX.X.X) for development purposes
		if (basename($app_path_docroot) == 'codebase') return;
		// Determine if this is the API
		$isAPI = (basename(dirname($_SERVER['PHP_SELF'])) . "/" . basename($_SERVER['PHP_SELF']) == 'api/index.php');
		// Get version we're currently in from the URL
		$redcapdir_version = substr(basename($app_path_docroot), 8);
		// If URL version does not match version number in redcap_config table, redirect to correct directory.
		// Do NOT redirect if the version number is not in the URL.
		if ($redcap_version != $redcapdir_version && ($isAPI || strpos($_SERVER['PHP_SELF'], "/redcap_v{$redcapdir_version}/") !== false))
		{
			// Only redirect if version number in redcap_config table is an actual directory
			if (in_array("redcap_v" . $redcap_version, getDirFiles(dirname($app_path_docroot))))
			{
				if ($isAPI && !defined("API_EXTMOD")) {
					// API: Make Post request to the version-specific API path for the correct version
					// (This should only be used temporarily when someone has added a new version directory to their web server
					// but has not yet fully upgraded the database to the new REDCap version.)
					exit(http_post(APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/API/index.php", $_POST));
				} else {
					// Replace version number in URL, then redirect
					$redirectto = str_replace("/redcap_v" . $redcapdir_version . "/", "/redcap_v" . $redcap_version . "/", $_SERVER['REQUEST_URI']);
					// Make sure that the page we're redirecting to actually exists in the newer version (if not, redirect to home)
					$subDir = basename(dirname($_SERVER['PHP_SELF']));
					$subDir = ($subDir == "redcap_v" . $redcapdir_version) ? "" : $subDir.DS;
					$redirecttoFullPath = dirname(APP_PATH_DOCROOT).DS."redcap_v".$redcap_version.DS.$subDir.basename($_SERVER['PHP_SELF']);
					if (!file_exists($redirecttoFullPath)) {
						if (isset($_GET['pid'])) {
							// Redirect to the project's Home Page
							redirect(APP_PATH_WEBROOT."index.php?pid=".$_GET['pid']);
						} else {
							// Redirect to the REDCap Home page
							System::redirectHome();
						}
					}
					// Check if post or get request
					if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isAjax) {
						// If this was a non-ajax post request, then preserve the submitted values by building
						// an invisible form that posts itself to same page in the new version.
						$postElements = "";
						foreach ($_POST as $key=>$val) {
                            if (is_array($val)) continue;
							$postElements .= "<input type='hidden' name=\"".htmlspecialchars($key, ENT_QUOTES)."\" value=\"".htmlspecialchars($val, ENT_QUOTES)."\">";
						}
						?>
						<html><body>
						<form action="<?php echo $redirectto ?>" method="post" name="form" enctype="multipart/form-data">
							<?php echo $postElements ?>
						</form>
						<script type='text/javascript'>
						document.form.submit();
						</script>
						</body>
						</html>
						<?php
						exit;
					} else {
						// If this is a call to an External Module's API endpoint, then redirect directly inside the version
						if (defined("API_EXTMOD")) {
							$redirectto = APP_PATH_WEBROOT_FULL."redcap_v{$redcap_version}/API/index.php?".$_SERVER['QUERY_STRING'];
						}
						// Redirect to the same page in the new version
						redirect($redirectto);
					}
				}
			}
		}
	}

	// Set main directories for REDCap
	public static function defineAppConstants()
	{
		global $redcap_version, $edoc_path, $redcap_base_url, $redcap_survey_base_url, $google_cloud_storage_temp_bucket, $google_cloud_storage_edocs_bucket, $edoc_storage_option;
        $redcap_survey_base_url = trim($redcap_survey_base_url);
	    // If constants have already been set, then skip this whole function
	    if (defined("SERVER_NAME")) return;
		// Get server name (i.e. domain), server port, and if using SSL (boolean)
		list ($server_name, $port, $ssl, $page_full) = getServerNamePortSSL();
		define("SERVER_NAME", $server_name);
		define("SSL", $ssl);
		define("PORT", str_replace(":", "", $port)); // Set PORT as numeric w/o colon
		// Declare current page with full path
		define("PAGE_FULL", $page_full);
		// Check for route. If exists, set as PAGE (but only if we are on the base index.php endpoint).
        $Route = new Route(false);
        if ($Route->get() && strpos(PAGE_FULL, "/redcap_v{$redcap_version}/index.php") !== false) define("PAGE", $Route->get());
		// Declare current page path from the version folder. If in subfolder, include subfolder name.
		if (basename(dirname(PAGE_FULL)) == "redcap_v" . $redcap_version) {
			// Page in version folder
			defined("PAGE") or define("PAGE", basename(PAGE_FULL));
		} elseif (basename(dirname(dirname(PAGE_FULL))) == "redcap_v" . $redcap_version) {
			// Page in subfolder under version folder
			defined("PAGE") or define("PAGE", basename(dirname(PAGE_FULL)) . "/" . basename(PAGE_FULL));
		} else {;
			// Pages above the version folder OR for survey page OR plugins/modules
			defined("PAGE") or define("PAGE", ltrim(basename(dirname(PAGE_FULL)) . "/" . basename(PAGE_FULL), '/'));
		}
		// Define web path to REDCap version folder (if redcap_base_url is defined, then use it to determine APP_PATH_WEBROOT)
		if (isset($redcap_base_url) && !empty($redcap_base_url)) {
			$redcap_base_url .= ((substr($redcap_base_url, -1) != "/") ? "/" : "");
			$redcap_base_url_parsed = parse_url($redcap_base_url);
			// Ensure that there is a protocol present
			if (!isset($redcap_base_url_parsed['scheme'])) {
				$redcap_base_url = (SSL ? "https://" : "http://") . $redcap_base_url;
				$redcap_base_url_parsed = parse_url($redcap_base_url);
			}
			define("APP_PATH_WEBROOT", $redcap_base_url_parsed['path'] . "redcap_v{$redcap_version}/");
			// Define full web address
			define("APP_PATH_WEBROOT_FULL", $redcap_base_url);
		} else {
			define("APP_PATH_WEBROOT", getVersionFolderWebPath());
			// Define full web address
			define("APP_PATH_WEBROOT_FULL", (SSL ? "https" : "http") . "://" . SERVER_NAME . $port . ((strlen(dirname(APP_PATH_WEBROOT)) <= 1) ? "" : dirname(APP_PATH_WEBROOT)) . "/");
		}
		// Path to server folder above REDCap webroot
		define("APP_PATH_WEBROOT_PARENT", ((strlen(dirname(APP_PATH_WEBROOT)) <= 1) ? "" : dirname(APP_PATH_WEBROOT)) . "/");
		// Docroot will be used by php includes
		$redcap_version_dir = dirname(dirname(__FILE__));
		if (self::isCI()) {
            define("APP_PATH_DOCROOT", $redcap_version_dir . DS);
        } elseif (basename($redcap_version_dir) == "redcap_v" . $redcap_version || basename($redcap_version_dir) == "codebase"
			|| defined("CRON") || defined("API") || defined("API_EXTMOD") || isset($_GET['pid']) || isset($_GET['route'])
			|| strpos(PAGE_FULL, "/ControlCenter/") !== false
			|| strpos(PAGE_FULL, "/LanguageUpdater/") !== false
			|| strpos(PAGE_FULL, "/SendIt/") !== false
			|| strpos(PAGE_FULL, "/PubMatch/") !== false
			|| strpos(PAGE_FULL, "/Plugins/") !== false
			|| strpos(PAGE_FULL, "/Profile/") !== false
            || strpos(PAGE_FULL, "/Messenger/info.php") !== false
			// Not yet sure how to deal with specific global pages outside Control Center and Send-It
			|| strpos(PAGE_FULL, "/Design/action_tag_explain.php") !== false
			|| strpos(PAGE_FULL, "/Design/smart_variable_explain.php") !== false
			|| strpos(PAGE_FULL, "/DataEntry/piping_explanation.php") !== false
			|| strpos(PAGE_FULL, "/DataEntry/field_embedding_explanation.php") !== false
			|| strpos(PAGE_FULL, "/DataEntry/special_functions_explanation.php") !== false
		) {
			define("APP_PATH_DOCROOT", $redcap_version_dir . DS);
		} else {
			// If we're about to upgrade (new directory is on the server), then use redirection so that APP_PATH_DOCROOT doesn't point to new version.
			redirect(APP_PATH_WEBROOT . "Home/index.php" . ($_SERVER['QUERY_STRING'] == '' ? '' : "?" . $_SERVER['QUERY_STRING']));
		}
		// Path to REDCap temp directory
		if ($edoc_storage_option == '3') {
			// Google Cloud Storage
            $storage = new Google\Cloud\Storage\StorageClient();
            $storage->registerStreamWrapper();

			define("APP_PATH_TEMP",				"gs://$google_cloud_storage_temp_bucket/");
		} else {
			// Normal local temp directory
			define("APP_PATH_TEMP",				dirname(APP_PATH_DOCROOT) . DS . "temp" . DS);
		}
		// Webtools folder path
		define("APP_PATH_WEBTOOLS",				dirname(APP_PATH_DOCROOT) . DS . "webtools2" . DS);
		// Path to folder containing uploaded files (default is "edocs", but can be changed in Control Center system config)
		$edoc_path = trim($edoc_path);
		if ($edoc_storage_option == '3') {
			// Google Cloud Storage
			define("EDOC_PATH",					"gs://$google_cloud_storage_edocs_bucket/");
		} elseif ($edoc_path == "") {
			// Default local edocs directory
			define("EDOC_PATH",					dirname(APP_PATH_DOCROOT) . DS . "edocs" . DS);
		} else {
			// Non-default local edocs directory
			define("EDOC_PATH",					$edoc_path . ((substr($edoc_path, -1) == "/" || substr($edoc_path, -1) == "\\") ? "" : DS));
		}
		// Classes
		define("APP_PATH_CLASSES",  			APP_PATH_DOCROOT . "Classes" . DS);
		// Controllers
		define("APP_PATH_CONTROLLERS", 			APP_PATH_DOCROOT . "Controllers" . DS);
		// Views
		define("APP_PATH_VIEWS", 				APP_PATH_DOCROOT . "Views" . DS);
		// Libraries
		define("APP_PATH_LIBRARIES", 			APP_PATH_DOCROOT . "Libraries" . DS);
		// Image repository
		define("APP_PATH_IMAGES",				APP_PATH_WEBROOT . "Resources/images/");
		// CSS
		define("APP_PATH_CSS",					APP_PATH_WEBROOT . "Resources/css/");
		// External Javascript
		define("APP_PATH_JS",					APP_PATH_WEBROOT . "Resources/js/");
		// Webpack
		define("APP_PATH_WEBPACK",				APP_PATH_WEBROOT . "Resources/webpack/");
		// Survey URL
		define("APP_PATH_SURVEY",				APP_PATH_WEBROOT_PARENT . "surveys/");
        // "Custom Survey for Project Status Transitions" workaround:
        // Using the Survey Base URL feature causes the survey not to redirect correctly at
        // the main REDCap end-point due to cross-origin browser security issues. In this case, remove the Survey Base URL value if we're taking a PST survey
        // to allow it to maintain being on the regular REDCap Base URL end-point, even with redirection, embedded PDFs, etc.
        if ($redcap_survey_base_url != '' && isset($_GET['pid'])
            && isSurveyPage() && Survey::isCustomSurveyProjectStatusTransition($_GET['pid']))
        {
            $sql = "select s.form_name from redcap_surveys s, redcap_surveys_participants p 
                    where s.survey_id = p.survey_id and p.participant_id = ?";
            $form = db_result(db_query($sql, Survey::getParticipantIdFromHash($_GET['s'])));
            if ($form != null) {
                $Proj = new Project($_GET['pid']);
                if ($form == $Proj->firstForm) {
                    $redcap_survey_base_url = '';
                }
            }
        }
		// If using alternative survey base URL for Full URL
		if ($redcap_survey_base_url != '') {
			// Make sure $redcap_survey_base_url ends with a /
			$redcap_survey_base_url .= ((substr($redcap_survey_base_url, -1) != "/") ? "/" : "");
			// Full survey URL
			define("APP_PATH_SURVEY_FULL",		$redcap_survey_base_url . "surveys/");
		} else {
			// Full survey URL
			define("APP_PATH_SURVEY_FULL",		APP_PATH_WEBROOT_FULL . "surveys/");
		}
		// REDCap Consortium website domain name
		define("CONSORTIUM_WEBSITE_DOMAIN",		"https://projectredcap.org");
		// REDCap Consortium website URL
		define("CONSORTIUM_WEBSITE",			"https://redcap.vumc.org/consortium/");
		// REDCap Shared Library URLs
		define("SHARED_LIB_PATH",				CONSORTIUM_WEBSITE 	  . "library/");
		define("SHARED_LIB_BROWSE_URL",			SHARED_LIB_PATH 	  . "login.php");
		define("SHARED_LIB_UPLOAD_URL",			SHARED_LIB_PATH 	  . "upload.php");
		define("SHARED_LIB_UPLOAD_ATTACH_URL",	SHARED_LIB_PATH 	  . "upload_attachment.php");
		define("SHARED_LIB_DOWNLOAD_URL",		SHARED_LIB_PATH 	  . "get.php");
		define("SHARED_LIB_SCHEMA",				SHARED_LIB_PATH 	  . "files/SharedLibrary.xsd");
		define("SHARED_LIB_CALLBACK_URL",		APP_PATH_WEBROOT_FULL . "redcap_v" . $redcap_version . "/SharedLibrary/receiver.php");
		// REDCap version
		defined("REDCAP_VERSION") or define("REDCAP_VERSION", $redcap_version);
		// Set the text that replaces data for de-id fields
		define("DEID_TEXT", "[*DATA REMOVED*]"); // DO NOT USE ANY TO SET A VALUE - TODO - Is this obsolete?
	}

	// Check if system has been set to Offline. If so, prevent normal users from accessing site.
	public static function checkSystemStatus()
	{
		global $system_offline, $system_offline_message, $homepage_contact_email, $homepage_contact, $lang;

		$GLOBALS['delay_kickout'] = $delay_kickout = false;

		if ($system_offline && defined("PAGE")
            && PAGE != 'ControlCenter/check.php' && PAGE != 'ControlCenter/check_server_ping.php'
            && !(
                // If not an authenticated admin
                (defined('SUPER_USER') && SUPER_USER)
                || (defined('ACCESS_SYSTEM_CONFIG') && ACCESS_SYSTEM_CONFIG)
                // If not an admin viewing a survey page
                || (defined("PAGE") && PAGE == 'surveys/index.php' && Session::hasAdminSessionCookie() !== false)
            )
        ) {

			// If custom offline message is set, then display it inside red box
			$system_offline_message_text = '';
			if (isset($system_offline_message) && trim($system_offline_message) != '') {
				// Custom message
				$system_offline_message_text = nl2br(decode_filter_tags($system_offline_message));
			} else {
				// Default message
				$system_offline_message_text = RCIcon::ErrorNotificationCircle("text-danger me-1").
					RCView::tt("config_functions_36");
			}

			//To prevent loss of data, don't kick the user out until the page has been processed when on data entry page.
			if (PAGE == "DataEntry/index.php") {
				$GLOBALS['delay_kickout'] = true;
				return;
			}
			// If using the API, do not display all the HTML but just the message
			elseif (PAGE == "api/index.php" || PAGE == "API/index.php") {
				API::outputApiErrorMsg($system_offline_message_text);
			}
			// If this is the Cron, do not display all the HTML but just the message
			elseif (defined("CRON")) {
				exit(trim(strip_tags($system_offline_message_text)));
			}

			// Initialize page display object
			$objHtmlPage = new HtmlPage();
			$objHtmlPage->addStylesheet("home.css", 'screen,print');
			$objHtmlPage->PrintHeader();

			print  "<div style='padding:20px 0;'>
						<img src='" . APP_PATH_IMAGES . "redcap-logo-large.png'>
					</div>
					<div class='red redcap-offline-message' style='margin:20px 0;'>
						$system_offline_message_text
					</div>
					<p style='padding-bottom:30px;'>
						{$lang['config_functions_37']}
						<a style='font-size:13px;text-decoration:underline;' href='mailto:$homepage_contact_email'>$homepage_contact</a>.
					</p>";

			$objHtmlPage->PrintFooter();
			exit;
		}
	}

	// Check Online/Offline Status: If project has been marked as OFFLINE in Control Center, then disallow access and give explanatory message.
	public static function checkOnlineStatus()
	{
		global $delay_kickout, $online_offline, $lang, $homepage_contact_email, $homepage_contact;

		if (!$online_offline &&
            // Is not an authenticated super user
            !(defined('SUPER_USER') && SUPER_USER) &&
            // Is not a super user viewing a survey page
            !(defined("PAGE") && PAGE == 'surveys/index.php' && Session::hasAdminSessionCookie() !== false)
        ) {
			//To prevent loss of data, don't kick the user out until the page has been processed when on data entry page.
			if (PAGE != "DataEntry/index.php") {
				// Initialize page display object
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->addStylesheet("home.css", 'screen,print');
				$objHtmlPage->PrintHeader();

				print  "<div style='padding:20px 0;'>
							<a href='".APP_PATH_WEBROOT_PARENT."index.php?action=myprojects'><img src='" . APP_PATH_IMAGES . "redcap-logo-large.png'></a>
						</div>
						<div class='red' style='margin:20px 0;'>
							<img src='" . APP_PATH_IMAGES . "exclamation.png'>
							{$lang['config_functions_121']}
						</div>
						<p style='padding-bottom:30px;'>
							{$lang['config_functions_37']}
							<a style='font-size:13px;text-decoration:underline;' href='mailto:$homepage_contact_email'>$homepage_contact</a>.
						</p>";

				$objHtmlPage->PrintFooter();
				exit;
			} else {
				// Delay kickout until user has submitted their data
				$delay_kickout = true;
			}
		}
		$GLOBALS['delay_kickout'] = $delay_kickout;
	}

	/**
	 * Extract the CSRF token from various sources
	 *
	 * This function attempts to extract the CSRF token from the following locations:
	 * 1. POST data (`$_POST['redcap_csrf_token']`) - Typically sent in a form submission.
	 * 2. GET data (`$_GET['redcap_csrf_token']`) - May be included in the URL query parameters.
	 * 3. Headers (`X-CSRF-Token`) - A custom HTTP header often used for securely transmitting the token.
	 * 4. Raw input (`php://input`) - The request body, which could be JSON-encoded or URL-encoded data.
	 *    - If JSON-encoded, the function will parse it as JSON and check for the `redcap_csrf_token` key.
	 *    - If URL-encoded, the function will parse it as URL-encoded data and check for the `redcap_csrf_token` key.
	 *
	 * @return string|null The CSRF token if found in any of the sources, or null if not found.
	 */
	public static function extractCsrfToken()
	{
		// Check for CSRF token in POST data
		if (isset($_POST['redcap_csrf_token'])) {
			return $_POST['redcap_csrf_token'];
		}

		// Check for CSRF token in GET data
		if (isset($_GET['redcap_csrf_token'])) {
			return $_GET['redcap_csrf_token'];
		}

		// Check for CSRF token in headers
		$headers = getallheaders();
		if (isset($headers['X-Csrf-Token'])) {
			return $headers['X-Csrf-Token'];
		}

		// Check for CSRF token in php://input
		$inputData = file_get_contents("php://input");
		if (!empty($inputData)) {
			// Decode JSON if input is JSON-encoded
			$decodedData = json_decode($inputData, true);
			if (json_last_error() === JSON_ERROR_NONE && isset($decodedData['redcap_csrf_token'])) {
				return $decodedData['redcap_csrf_token'];
			}

			// Alternatively, check for URL-encoded data
			parse_str($inputData, $parsedData);
			if (isset($parsedData['redcap_csrf_token'])) {
				return $parsedData['redcap_csrf_token'];
			}
			else if (isset($parsedData['formdata'])) {
				$formdata = json_decode($parsedData['formdata'], true);
				if (json_last_error() === JSON_ERROR_NONE && isset($formdata['redcap_csrf_token'])) {
					return $formdata['redcap_csrf_token'];
				}
			}
		}

		// Return null if no CSRF token is found
		return null;
	}
 

	// Prevent CSRF attacks by checking a custom token
	public static function checkCsrfToken()
	{
		global $isAjax, $lang, $salt, $userid, $auth_meth_global;//***<AAF Modification>****
		// Is this an API request?
		$isApi = (defined("PAGE") && (PAGE == "api/index.php" || PAGE == "API/index.php"));
		// Is the page a REDCap plugin?
		$isExtModPage = (strpos(PAGE_FULL, "/redcap_v" . REDCAP_VERSION . "/ExternalModules/") !== false || strpos(PAGE_FULL, "/external_modules/") !== false);
		$isPlugin = defined("PLUGIN");
		$isAaf = (strpos($auth_meth_global,'aaf')>-1 && isset($_SESSION['tli']) && $_SESSION['tli']==0);//***<AAF Modification>****
		// List of specific pages exempt from creating/updating CSRF tokens
		$pagesExemptFromTokenCreate = array("Design/edit_field.php", "Reports/report_export.php",
											"DataEntry/file_upload.php", "DataEntry/file_download.php",
											"DataQuality/data_resolution_file_upload.php", "DataQuality/data_resolution_file_download.php",
											"Graphical/pdf.php/download.pdf", "PdfController:index", "DataExport/data_export_csv.php",
											"Design/file_attachment_upload.php", "DataEntry/image_view.php", "SharedLibrary/image_loader.php",
											"DataImportController:downloadTemplate", "Design/data_dictionary_download.php"
										   );
		// List of specific pages exempt from checking CSRF tokens
		$pagesExemptFromTokenCheck = array(	"SharedLibrary/image_loader.php",
											"Authentication/two_factor_check_login_status.php",
											"Authentication/two_factor_verify_code.php", "Authentication/two_factor_send_code.php",
                                            "AlertsController:saveAttachment");
		// Do not perform token check for non-Post methods, API requests, when logging in, for pages without authentication enabled,
		// or (for LDAP only) when providing user info immediately after logging in the first time.
		$exemptFromTokenCheck  = ($isExtModPage || $isPlugin || $isApi || (defined("PAGE") && in_array(PAGE, System::$fileDownloadPages)) || (defined("PAGE") && in_array(PAGE, $pagesExemptFromTokenCheck))
									|| (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'POST') || (isset($_POST['redcap_login_a38us_09i85']) && System::$userJustLoggedIn) || defined("NOAUTH")
								    // Two factor auth: code verification
								    || (defined("PAGE") && (PAGE == "Authentication/two_factor_verify_code.php" || PAGE == "Authentication/two_factor_send_code.php") && !isset($_SESSION['two_factor_auth']))
								    // In case uploading a file and exceeds PHP limits and normal error catching does not catch the error
								    || (defined("PAGE") && (PAGE == "SendItController:upload") && empty($_FILES))
                                    // In case loading a public report
                                    || (defined("PAGE") && (PAGE == "surveys/index.php") && isset($_GET['__report']) && isset($_POST['report_id']))
                                    // In case this is not a true POST request but a special REDCap GET=>POST redirect
                                    || defined(self::POST_REDIRECT_PARAM_RECEIVED)
								 );
		// Do not create/update token for Head/API/AJAX requests, when logging in, or for pages that produce downloadable files,
		// non-displayable pages, receive Post data via iframe, or have authentication disabled.
		$exemptFromTokenCreate = ( $isAjax || $isApi || (defined("PAGE") && in_array(PAGE, $pagesExemptFromTokenCreate)) || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'HEAD')
								   || isset($_POST['redcap_login_a38us_09i85']) || isset($_POST['redcap_login_openid_Re8D2_8uiMn'])
								   || (defined("NOAUTH") && !defined("EHR")) );
		// Check for CSRF token
		if (!$exemptFromTokenCheck)
		{
			// Set token value (can come from Post or Get ajax)
			$redcap_csrf_token = self::extractCsrfToken();

			self::forceCsrfTokenCheck($redcap_csrf_token);
		}
		// GENERATE A NEW CRSF TOKEN, which jquery will add to all forms on the rendered page
		if (!$exemptFromTokenCreate)
		{
			// Initialize array if does not exist
			if (!isset($_SESSION['redcap_csrf_token']) || !is_array($_SESSION['redcap_csrf_token'])) {
				$_SESSION['redcap_csrf_token'] = array();
			}
			// If more than X number of elements exist in array, then remove the oldest
			$maxTokens = 50;
			if (count($_SESSION['redcap_csrf_token']) > $maxTokens) {
				array_shift($_SESSION['redcap_csrf_token']);
			}
			// Generate token and put in array
			$_SESSION['redcap_csrf_token'][NOW] = sha1($salt . NOW . $userid);
		}
		// Lastly, remove token from Post or Get to prevent any conflict in processing
		unset($_POST['redcap_csrf_token'], $_GET['redcap_csrf_token']);
	}

	/**
	 * generate a CSRF token and save it
	 * in the current session if available
	 *
	 * @return string|false the generated token or false if the session was not available
	 */
	public static function generateCsrfToken() {
		global $salt, $userid;
		
		$now = date('Y-m-d H:i:s');
		$token = sha1($salt . $now . $userid);
		if (session_status() !== PHP_SESSION_ACTIVE) return false;
		if(!isset($_SESSION['redcap_csrf_token'])) $_SESSION['redcap_csrf_token'] = [];
		$_SESSION['redcap_csrf_token'][$now] = $token;
		return $token;
	}

	public static function forceCsrfTokenCheck($redcap_csrf_token)
	{
		global $salt, $userid, $lang;

		// Compare Post/Get token with Session token (should be the same)
		if (!isset($_SESSION['redcap_csrf_token']) || $redcap_csrf_token == null || !in_array($redcap_csrf_token, $_SESSION['redcap_csrf_token']))
		{
			// Default
			$displayError = true;
			// FAIL SAFE: Because of strange issues with the last token not getting saved to the session table,
			// do a check of all possible tokens that could have been created between now
			// and the time of the last token generated. If a match is found, then don't give user the error.
			if ($redcap_csrf_token != null && $redcap_csrf_token != "")
			{
				// Determine number of seconds passed since last token was generated
				$csrf_keys = array_keys(isset($_SESSION) && isset($_SESSION['redcap_csrf_token']) ? $_SESSION['redcap_csrf_token'] : array());
				$lastTokenTime = end($csrf_keys);
				if (empty($lastTokenTime) || $lastTokenTime == "") {
					$sec_ago = 21600; // 6 hours
				} else {
					$sec_ago = strtotime(NOW) - strtotime($lastTokenTime);
				}
				// Find time when the posted token was generated, if can be found
				for ($this_sec_ago = -10; $this_sec_ago <= $sec_ago; $this_sec_ago++)
				{
					$this_ts = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s")-$this_sec_ago,date("m"),date("d"),date("Y")));
					if ($redcap_csrf_token == sha1($salt . $this_ts . $userid))
					{
						// Found the token's timestamp, so note it and set flag to not display the error message
						$displayError = false;
						break;
					}
				}
			}
			// Display the error to the user
			if ($displayError)
			{
				// Give error message and stop (fatal error)
				$objHtmlPage = new HtmlPage();
				$objHtmlPage->PrintHeaderExt();
				$msg = "<p style='font-family:arial,helvetica;margin:20px;background-color:#FAFAFA;border:1px solid #ddd;padding:15px;font-size:13px;max-width:700px;'>
							<img src='".APP_PATH_IMAGES."exclamation.png' style='position:relative;top:3px;'>
							<b style='color:#800000;font-size:14px;'>{$lang['config_functions_64']}</b>
							<br><br>{$lang['config_functions_65']}
							<br><br>{$lang['config_functions_93']}";
				if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
					// Button to go back one page
					$msg .= " <br><br><button onclick=\"history.go(-1);return false;\">&#60;- {$lang['form_renderer_15']}</button>";
				}
				$msg .= "</p>";
				print $msg;
				// If we're inside an iframe, then do a popup alert of this message so that it's visible
				print  "<script type='text/javascript'>
						if (inIframe()) {
							alert('".js_escape(trim(strip_tags($msg)))."');
							window.parent.window.location.reload();
						}
						</script>";
				$objHtmlPage->PrintFooterExt();
				exit;
			}
		}
	}

	// Add CSRF token to all forms on the webpage using jQuery
	public static function createCsrfToken()
	{
		if (isset($_SESSION['redcap_csrf_token']))
		{
			?>
			<script type="text/javascript">
			// Add CSRF token as javascript variable and add to every form on page
			var redcap_csrf_token = '<?php echo self::getCsrfToken() ?>';

			$('html').on('submit', 'form', function(e){
				if(e.target.method === 'post' && !REDCap.appendCsrfTokenToFormComplete){
					/**
					 * Prevent forms from being submitted while pages are loading and appendCsrfTokenToForm() has not yet run,
					 * as those submissions would likely fail and show the "Multiple tabs/windows" error.
					 * A good example is larger queries that cause the database_query_tool.php page to take a few seconds to finish rendering.
					 */
					e.preventDefault()
				}
			})

			$(function(){ appendCsrfTokenToForm(); });
			</script>
			<?php
		}
	}

	// Retrieve CSRF token from session
	public static function getCsrfToken()
	{
		// Make sure the session variable exists first and is an array
		if (!isset($_SESSION['redcap_csrf_token']) || (isset($_SESSION['redcap_csrf_token']) && !is_array($_SESSION['redcap_csrf_token'])))
		{
			return false;
		}
		// Get last token in csrf token array and return it
		return end($_SESSION['redcap_csrf_token']);
	}

	// Set db sql mode setting and disable "safe updates"
	public static function setDbSqlMode($useReadReplica=false)
	{
		$sql = "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION', SESSION sql_safe_updates = 0";
		mysqli_query($useReadReplica && isset($GLOBALS['rc_replica_connection']) ? $GLOBALS['rc_replica_connection'] : $GLOBALS['rc_connection'], $sql);
	}

	// Set db binlog_format setting
	public static function setDbBinLogFormat($useReadReplica=false)
	{
		$sql = "select value from redcap_config where field_name = 'db_binlog_format'";
		$q = mysqli_query($useReadReplica && isset($GLOBALS['rc_replica_connection']) ? $GLOBALS['rc_replica_connection'] : $GLOBALS['rc_connection'], $sql);
		$db_binlog_format = db_num_rows($q) ? db_result($q, 0) : "";
        if ($db_binlog_format != '') {
			$sql = "SET SESSION binlog_format = '" . db_escape($db_binlog_format) . "'";
			mysqli_query($useReadReplica && isset($GLOBALS['rc_replica_connection']) ? $GLOBALS['rc_replica_connection'] : $GLOBALS['rc_connection'], $sql);
		}
	}

	// Checking db character encoding
	public static function checkDbEncoding($useReadReplica=false, $tryStoreDbEncoding=true)
	{
        global $db_character_set, $db_collation;
		// If db char encoding is not stored, then store it
		if (!isset($db_character_set) || $db_character_set == '') {
			// First, see if we have the encoding/collation in the config table
			list ($db_character_set, $db_collation) = self::getDbEncodingFromTable($useReadReplica);
			if ($tryStoreDbEncoding && !$useReadReplica && ($db_character_set == '' || $db_collation == '')) {
				// Encoding not set, so set it in config table
				self::storeDbEncoding();
				return true;
			}
		}
        // Try setting project-level charset/collation, if exists in redcap_projects
        if (isset($GLOBALS['project_db_character_set']) && $GLOBALS['project_db_character_set'] != null) {
            $db_character_set = $GLOBALS['project_db_character_set'];
        }
        // if (isset($GLOBALS['project_db_collation']) && $GLOBALS['project_db_collation'] != null) {
        //     $db_collation = $GLOBALS['project_db_collation'];
        // }
        // Set charset manually to maintain data consistency
        self::setDbEncoding($useReadReplica);
        // Set collation_connection
        self::setCollationConnection($useReadReplica);
		// If we got here, then all is fine
		return true;
	}

    // Set collation_connection
	public static function setCollationConnection($useReadReplica=false)
	{
        global $db_character_set, $db_collation;
        if ($db_character_set == 'utf8mb4') {
            $db_collation = 'utf8mb4_unicode_ci';
        } elseif ($db_character_set == 'utf8mb3') {
            $db_collation = 'utf8mb3_unicode_ci';
        } elseif ($db_character_set == 'utf8') {
            $db_collation = 'utf8_unicode_ci';
        } else {
            return;
        }
        $sql = "SET SESSION collation_connection = '$db_collation'";
        mysqli_query($useReadReplica && isset($GLOBALS['rc_replica_connection']) ? $GLOBALS['rc_replica_connection'] : $GLOBALS['rc_connection'], $sql);
	}

	// Set db character encoding to the value stored in the config table to maintain data consistency
	public static function setDbEncoding($useReadReplica=false)
	{
        // $db_character_set = (isset($GLOBALS['project_db_character_set']) && $GLOBALS['project_db_character_set'] != null) ? $GLOBALS['project_db_character_set'] : $GLOBALS['db_character_set'];
        global $db_character_set;
		if (!isset($db_character_set) || $db_character_set == '') return false;
        // PHP mysqli / mysql native driver does not recognize utf8mb3, set utf8mb3 to utf8
        if ($db_character_set == 'utf8mb3') $db_character_set = 'utf8';
		// Set charset
		return mysqli_set_charset($useReadReplica && isset($GLOBALS['rc_replica_connection']) ? $GLOBALS['rc_replica_connection'] : $GLOBALS['rc_connection'], $db_character_set);
	}

	// Get db character encoding and collation stored in config table
	public static function getDbEncodingFromTable($useReadReplica=false)
	{
		// Get charset
		$sql = "select * from redcap_config 
				where field_name in ('db_character_set', 'db_collation')";
		$q = mysqli_query($useReadReplica && isset($GLOBALS['rc_replica_connection']) ? $GLOBALS['rc_replica_connection'] : $GLOBALS['rc_connection'], $sql);
		if (!db_num_rows($q)) return array('', '');
		while ($row = db_fetch_assoc($q)) {
			if ($row['field_name'] == 'db_character_set') {
				$db_character_set = $row['value'];
			} else {
				$db_collation = $row['value'];
			}
		}
		return array($db_character_set, $db_collation);
	}

	// Store db character encoding in config table
	public static function storeDbEncoding()
	{
		// Do not do this if config table is empty (which means we are in the middle of installing REDCap)
		$sql = "select count(1) from redcap_config";
		$q = db_query($sql);
		if (!$q || db_result($q, 0) == 0) return false;
		// Get current char set
		global $rc_connection, $db_character_set, $db_collation;
		$db_character_set = mysqli_character_set_name($rc_connection);
		// Save current char set in table
		$sql = "replace into redcap_config (field_name, value) 
				values ('db_character_set', '".db_escape($db_character_set)."')";
		$a = db_query($sql);
		// Get current collation_connection
		$sql = "show variables where variable_name = 'collation_connection'";
		$q = db_query($sql);
		$row = db_fetch_assoc($q);
		$db_collation = $row['Value'];
        // Ensure utf8-like charsets are assigned a "_unicode_ci" collation, overriding server-level defaults.
        // This should help prevent "mismatched collation" errors.
        if ($db_character_set == 'utf8mb4') {
            $db_collation = 'utf8mb4_unicode_ci';
        } elseif ($db_character_set == 'utf8mb3') {
            $db_collation = 'utf8mb3_unicode_ci';
        } elseif ($db_character_set == 'utf8') {
            $db_collation = 'utf8_unicode_ci';
        }
		// Save current char set in table
		$sql = "replace into redcap_config (field_name, value) 
				values ('db_collation', '".db_escape($db_collation)."')";
		$b = db_query($sql);
		// Return on success
		return ($a && $b);
	}

	// Does db server have utf8mb4 charset?
	public static function dbHasUtf8mb4Encoding()
	{
		$sql = "SHOW CHARACTER SET WHERE Charset = 'utf8mb4'";
		$q = db_query($sql);
		return ($q && db_num_rows($q) > 0);
	}

	// Return boolean if REDCap is running on AWS Elastic Beanstalk
	public static function usingAwsElasticBeanstalk()
	{
		// If the following files exist OR if the quickstart config setting is set to "1", this means we're using AWS Elastic Beanstalk
		return ((isset($GLOBALS['aws_quickstart']) && $GLOBALS['aws_quickstart'] == '1') || file_exists('/var/log/eb-activity.log') || file_exists('/var/log/eb-hooks.log'));
	}

    // Return boolean if a Continuous Integration (CI) environment, such as CircleCI
    public static function isCI()
    {
        return isset($_SERVER['MYSQL_REDCAP_CI_HOSTNAME']);
    }

    // Kill any duplicate MySQL connections on a per-user basis (excluding API calls, cron jobs, EMs, DQ rules, survey pages, and File Repository uploads)
    public static function killConcurrentRequestsCron()
    {
        $killedConns = [];

        // Ignore current mysql thread
        $mysql_process_id = db_thread_id();

        // Don't kill any requests younger than X seconds old
        $killThreshold = 60; // seconds

        // If replica is available but the lag time is greater than threshold, force connect to it so we can use full processlist on this page
        $replicaAvailable = (System::readReplicaConnVarsFound() && System::readReplicaEnabledInConfig() && !isset($GLOBALS['rc_replica_connection']));

        $servers = ['primary'];
        if ($replicaAvailable) $servers[] = 'replica';

        // Loop through each server
        foreach ($servers as $server)
        {
            // If this is the replica, connect to it
            $server_conn = $GLOBALS['rc_connection'];
            if ($server == 'replica') {
                db_connect(false, false, true, true, true);
                $server_conn = $GLOBALS['rc_replica_connection'];
            }
            // Gather all currently-running mysql processes from the process list
            $mysql_process_id = db_thread_id($server_conn);
            $q = mysqli_query($server_conn, "show full processlist");
            $mysqlProcesses = [];
            while ($row = db_fetch_assoc($q)) {
                if ($row['Id'] == $mysql_process_id) continue;
                $mysqlProcesses[] = $row['Id'];
            }
            if (empty($mysqlProcesses)) continue;

            // Check the log view table for requests in the past 15 minutes
            $fiftenMinAgo = date("Y-m-d H:i:s", mktime(date("H"), date("i") - 15, date("s"), date("m"), date("d"), date("Y")));
            // Set array and query to see if the current user has any currently running queries
            $sql = "select r.lvr_id, r.mysql_process_id, v.session_id, v.ts, r.log_view_id,
                    (select max(r2.lvr_id) from redcap_log_view_requests r2, redcap_log_view v2
                        where v2.log_view_id = r2.log_view_id and r2.script_execution_time is not null and v2.session_id = v.session_id and r2.is_ajax = 0
                     ) as latest_completed_lvr_id
                    from redcap_log_view_requests r, redcap_log_view v
                    where v.log_view_id = r.log_view_id and r.script_execution_time is null 
                    and r.is_cron = 0 and r.ui_id is not null 
                    and v.ts > '$fiftenMinAgo' and v.session_id is not null
                    and v.page not in ('api/index.php', 'surveys/index.php', 'DataQuality/execute_ajax.php', 'FileRepositoryController:upload', 
                                       'external_modules/index.php', 'ExternalModules/index.php', 'ProjectGeneral/keep_alive.php', 
                                       'DataImportController:index', 'DataAccessGroupsController:switchDag', 'DataMartController:runRevision',
                                       'ControlCenterController:oneClickUpgrade', 'ControlCenterController:executeUpgradeSQL', 'ControlCenterController:autoFixTables',
                                       'ControlCenter/movedata.php', 'DynamicDataPull/fetch.php', 'DynamicDataPull/save.php', 'DataEntryController:buildRecordListCache', 'DataEntryController:renameRecord')
                    and v.full_url not like '%/plugins/%' and v.full_url not like '%/external_modules/%' and v.full_url not like '%/ExternalModules/%'
                    and r.mysql_process_id in (" . prep_implode($mysqlProcesses) . ")
                    order by v.log_view_id desc";
            $q = mysqli_query($server_conn, $sql);
            if (db_num_rows($q) == 0) continue;
            // Loop to gather all mysql process IDs
            $CurrentUserQueries = [];
            $connsToKill = [];
            while ($row = db_fetch_assoc($q))
            {
                // Make sure the request hasn't already completed while we've been looping here. If script_execution_time is not null, then the request has completed, so skip it.
                $sql = "select 1 from redcap_log_view_requests where script_execution_time is not null and lvr_id = {$row['lvr_id']}";
                if (db_num_rows(db_query($sql)) > 0) continue;
                // If we're using a replica, which will have a duplicate entry in redcap_log_view for certain requests that haven't completed yet,
                // make sure we don't kill any that has a duplicate (same time, session, URL, etc.).
                if ($replicaAvailable) {
                    $sql = "select 1 from redcap_log_view a, redcap_log_view b
                            where a.log_view_id = {$row['log_view_id']} and b.log_view_id != a.log_view_id
                            and a.ts = b.ts and a.session_id = b.session_id and a.project_id = b.project_id
                            and a.full_url = b.full_url and a.event = b.event limit 1";
                    if (db_num_rows(db_query($sql)) > 0) continue;
                }
                // Determine the age of the request
                $requestAge = strtotime(NOW) - strtotime($row['ts']); // seconds
                // Don't kill any requests younger than X seconds old
                $requestTooYoung = ($requestAge <= $killThreshold);
                // Is this running request younger than a completed request for this user/session? If so, then kill the running request (it has been abandoned).
                $newerCompletedRequestsExist = ($row['latest_completed_lvr_id'] != null && $row['latest_completed_lvr_id'] > $row['lvr_id']);
                // Is a duplicate request or is the first/only? Kill if duplicate and abandoned and more than X seconds old.
                if (!$requestTooYoung && ($newerCompletedRequestsExist || isset($CurrentUserQueries[$row['session_id']]))) {
                    // Since we ordered the query in descending chron order, we will keep the latest request and kill all the older ones
                    $connsToKill[$row['lvr_id']] = $row['mysql_process_id'];
                } else {
                    // Store the most recent request in the array to keep so that we know when we get a duplicate, which we'll need to kill
                    $CurrentUserQueries[$row['session_id']] = true;
                }
            }
            unset($CurrentUserQueries);
            if (empty($connsToKill)) continue;

//            print_array($sql);
//            print_array("Processes to kill :");
//            print_array($connsToKill);
//            continue;

            // Loop through all mysql connections to kill
            foreach ($connsToKill as $lvr_id => $mysql_process_id) {
                if (mysqli_query($server_conn, "KILL $mysql_process_id")) {
                    $killedConns[] = $mysql_process_id;

                    // Testing/debugging only
//                    $sql = "select * from redcap_log_view_requests r, redcap_log_view v
//                            where v.log_view_id = r.log_view_id and r.lvr_id = $lvr_id";
//                    $q = mysqli_query($server_conn, $sql);
//                    print_array(db_fetch_assoc($q));

                    // Delete this row in log_view_requests so that it gets ignored in the future
                    $sql = "delete from redcap_log_view_requests where lvr_id = $lvr_id";
                    mysqli_query($server_conn, $sql);
                }
            }
        }

        // Connect back to primary
        if (isset($GLOBALS['rc_replica_connection'])) {
            unset($GLOBALS['rc_replica_connection']);
            db_connect();
        }
        
        // Return
        return $killedConns;
    }

	// Kill any currently-running MySQL processes by the current user/session on THIS page.
    // If $countKeepNewest > 0, do not kill the newest X number of processes.
	public static function killConcurrentRequests($windowTimeMinutes=1, $countKeepNewest=0)
	{
		$countKeepNewest = (int)$countKeepNewest;
		$xMinAgo = date("Y-m-d H:i:s", mktime(date("H"),date("i")-$windowTimeMinutes,date("s"),date("m"),date("d"),date("Y")));
		$ui_id_clause = defined("UI_ID") ? "r.ui_id = ".UI_ID : 'isnull(r.ui_id)';
		$log_view_replica_clause = defined("LOG_VIEW_REPLICA_ID") ? "and r.log_view_id != ".LOG_VIEW_REPLICA_ID : "";
		$sql = "select v.log_view_id, r.mysql_process_id from redcap_log_view_requests r, redcap_log_view v 
                where v.log_view_id = r.log_view_id and r.script_execution_time is null 
                and $ui_id_clause and v.ts > '$xMinAgo' and v.session_id = '".Session::sessionId()."'
                and v.page = '".db_escape(PAGE)."' and r.mysql_process_id != '".db_thread_id()."' $log_view_replica_clause
                order by v.log_view_id desc";
		$q = db_query($sql, [], null, MYSQLI_STORE_RESULT, true); // Always use primary db connection (never the read replica)
		$loops = 0;
		while ($row = db_fetch_assoc($q)) {
			$loops++;
			// If we're keeping the newest ones (the first ones here since ordering desc), then keep skipping the kill process until we've hit that count
			if ($countKeepNewest > 0 && $loops < $countKeepNewest) {
				continue;
			}
		    // Kill the MySQL process and also remove from redcap_log_view_requests to help prevent re-running these after they've already been killed
			db_query("KILL ".$row['mysql_process_id'], [], $GLOBALS['rc_replica_connection'] ?? $GLOBALS['rc_connection']);
			db_query("delete from redcap_log_view_requests where log_view_id = ".$row['log_view_id'], [], null, MYSQLI_STORE_RESULT, true);
		}
	}

	// Return the Universal FROM Email address
    public static function getUniversalFromAddess()
    {
		global $from_email;
		if (!isEmail($from_email)) $from_email = '';
	    return trim($from_email);
    }

	// Check if an email address matches one listed in the Suppress Universal FROM Email address list of addresses (if enabled)
    // Returning TRUE implies to use the sender's email address and NOT the Universal FROM address
	public static function suppressUniversalFromAddress($email='')
    {
		global $from_email_domain_exclude;

		// Format all to lowercase
		$email = strtolower(trim($email??''));
		$from_email_domain_exclude = strtolower(trim($from_email_domain_exclude));
		if ($email == '') return false;

		// If no universal from address is set, return true to use sender's address
		$universal_from_email = self::getUniversalFromAddess();
		if ($universal_from_email == '') return true;

		// If the "suppress" setting is not defined, then return false to use the universal from address
		if ($universal_from_email != '' && $from_email_domain_exclude == '') return false;

		// Return true if we have a match
		$from_email_domain_exclude_array = explode("\n", str_replace("\r", "", $from_email_domain_exclude));
		list ($emailFirstPart, $emailDomain) = explode('@', $email, 2);
		return in_array($emailDomain, $from_email_domain_exclude_array);
	}

	// Get current PHP version to one decimal place
	public static function getPhpVersion($returnOnlyOneDecimal=false)
	{
		$version_pieces = explode(".", PHP_VERSION);
		$version_pieces[2] = intval($version_pieces[2]);
		$version = intval($version_pieces[0]).".".intval($version_pieces[1]);
        if (!$returnOnlyOneDecimal) {
			$version .= ".".intval($version_pieces[2]);
		}
        return $version;
	}

	// Is the current web server on a recommended PHP version? Return boolean.
	public static function isOnRecommendedPhpVersion()
	{
        return in_array(self::getPhpVersion(true), self::$recommendedPhpVersions);
	}

	// Get date of latest REDCap upgrade
	public static function getLastUpgradeDate()
	{
		$sql = "select date from redcap_history_version order by date desc limit 1";
        $q = db_query($sql);
        return (($q && db_num_rows($q) > 0) ? db_result($q, 0) : "");
	}

	// This method is tested hourly when the External Modules framework tests run.
	// This should immediately detect breakage (ex: due to the class being renamed).
	public static function isStatementResult($result){
		return (
			is_object($result)
			&&
			is_a($result, StatementResult::class)
    	);
	}

	// Determine if config setting is encrypted or not
	public static function isConfigSettingEncrypted($val)
	{
        // If it's numerical or null or less than 20 chars long or contains a space, then definitely not
        if ($val === null || $val === "" || is_numeric($val) || !preg_match("/^([a-zA-Z0-9._\+\-\/\=]{20,})$/", $val)) {
            return false;
		}
        // Last check: actually try to decrypt it. If it returns false, then it's not encrypted.
        return (self::decryptConfigSetting($val) !== false);
	}

	// Encrypt configuration passwords in redcap_config (use two keys together: one in db, one in PHP code)
	public static function encryptConfigSetting($val)
	{
		return $val == "" ? "" : encrypt($val, self::getConfigSettingsKey());
	}

	// Decrypt configuration passwords in redcap_config (use two keys together: one in db, one in PHP code)
	public static function decryptConfigSetting($val)
	{
		return $val == "" ? "" : decrypt($val, self::getConfigSettingsKey());
	}

	// Get the full encryption key for encrypting/decrypting configuration passwords in redcap_config (use two keys together: one in db, one in PHP code)
	private static function getConfigSettingsKey()
	{
        return self::CONFIG_SETTINGS_ENCRYPTION_KEY . self::getConfigSettingsKeyDb();
	}

    // Get the partial encryption key stored in redcap_config. If blank, generate it and store it there.
	private static function getConfigSettingsKeyDb()
	{
		if (!isset($GLOBALS['config_settings_key']) || $GLOBALS['config_settings_key'] == "") {
            // Generate it and set it
			$GLOBALS['config_settings_key'] = self::setConfigSettingsKeyDb();
		}
        return $GLOBALS['config_settings_key'];
	}

	// Generate and store the partial encryption key stored in redcap_config
	private static function setConfigSettingsKeyDb()
	{
        $key = generateRandomHash(64, true);
        // Save in db
		updateConfig("config_settings_key", $key);
        // Return new key
        return $key;
	}

	/**
	 * Is the provided query a SELECT query?
	 */
	public static function isSelectQuery($sql)
	{
		return preg_match('/^\s*select\s/i', $sql);
	}

	/**
	 * This function aims to block many 'redcap_data' delete & update queries that are not limited to project or event ID.
	 * It will not catch all such queries, but is better than having no safety net.
	 * Mark intentionally favored ease of understanding over maximum coverage with this solution,
	 * under the assumption that we would rather allow unlikely queries that are dangerous,
	 * that disallow legitimate queries in cases that are hard to predict.
	 * 
	 * Mark tried using greenlion/PHP-SQL-Parser here instead of regex,
	 * but it seemed to make the average query about 50% slower
	 * (as tested by running 'phpunit --exclude slow' on the module framework).
	 * The regex checks do not affect performance measurably.
	 */
	public static function checkQuery($sql){
		$originalSql = $sql;

		/**
		 * Replace any SQL strings in the query with the empty string
		 * to ensure they don't affect our matching.
		 */
		$sql = self::clearQuotedSubStrings($sql);

		if(!preg_match('/^\s*(delete|update)\s/i', $sql)){
			// This is not a 'delete' or 'update' query.
			return;
		}
		// Prefix & suffix chosen per 'Permitted characters in unquoted identifiers' from https://dev.mysql.com/doc/refman/8.0/en/identifiers.html
		else if(!preg_match('/[^[0-9a-z$_]redcap_data[0-9]*($|[^[0-9a-z$_])/i', $sql)){
			// This query does not reference 'redcap_data'.
			return;
		}
		else if(preg_match('/[^[0-9a-z$_](project_id|event_id)($|[^[0-9a-z$_])/i', $sql)){
			/**
			 * The query contains a project or event ID.  Assume it is safe.
			 * We originally also required a 'where' clause, but this didn't account for legitimate delete queries
			 * that specify the project/event ID ONLY within a join 'on' clause.
			 */
			return;
		}

		if(isset($_SERVER['HTTP_HOST'])){
			$url =(isset($_SERVER['HTTPS']) ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}
		else{
			$url = 'none';
		}

		$message = static::UNLIMITED_DELETE_OR_UPDATE_MESSAGE;

		/**
		 * The EM logging method is used because it:
		 * - Splits long messages into multiple log calls, instead of truncating them
		 * - Writes to STDOUT when unit testing 
		 */
		\ExternalModules\ExternalModules::errorLog(implode("\n", [
			"$message The following query is not allowed:",
			"  URL - $url",
			"  SQL - $originalSql"
		]));

		throw new \Exception("$message See the error log for the specific query that triggered this message.");
	}

	/**
	 * We used to use a regex here, but it crashed PROD with a segmentation fault on larger SQL queries.
	 * The issue only occurred on PHP 7.3.33 on our servers, NOT on 7.3.33-1+ubuntu20.04.1+deb.sury.org+1 on WSL.
	 */
	public static function clearQuotedSubStrings($s){
		$newS = '';
	
		$quoteType = null;
		$escaping = false;
		for($i=0; $i<strlen($s); $i++){
			$c = $s[$i];
			
			if($quoteType === null){
				if(in_array($c, ['"', "'"])){
					$quoteType = $c;
				}

				$newS .= $c;
			}
			else if($c === $quoteType && !$escaping){
				$quoteType = null;
				$newS .= $c;
			}

			if($escaping){
				$escaping = false;
			}
			else if($c === '\\'){
				$escaping = true;
			}
		}

		return $newS;
	}

	public static function queryWithParameters($conn, $sql, $parameters, $resultmode){
		$statement = $conn->prepare($sql);
		if(!$statement){
			throw new Exception('Statement preparation failed');
		}

		$GLOBALS['__db_last_stmt'] = $statement;

		if(!empty($parameters)){
			static::bindParams($statement, $parameters);
		}

		if(!$statement->execute()){
			throw new Exception('Prepared statement execution failed');
		}

		$metadata = $statement->result_metadata();
		if(!$metadata){
			// This is an INSERT, UPDATE, DELETE, or some other query type that does not return data.
			// Copy mysqli_query()'s behavior in this case.
			return empty(db_error());
		}

		/**
		 * We can't use $statement->get_result() here because it's only present with the nd_mylsqi driver (see community question 77051).
		 * 
		 * If the following post is accurate, we can remove the StatementResult object once PHP 8.2 is the minimum supported version for REDCap:
	 	 * https://php.watch/versions/8.2/mysqli-libmysql-no-longer-supported
		 */
		return new StatementResult($statement, $metadata, $resultmode);
	}

	private static function bindParams($statement, $parameters){
		$parameterReferences = [];
		$parameterTypes = [];
		foreach($parameters as $i=>$value){
			$phpType = gettype($value);
			if($phpType === 'object'){
				if($value instanceof DateTime){
					$value = $value->format("Y-m-d H:i:s");
					$parameters[$i] = $value;
					$phpType = 'string';
				}
			}

			$mysqliType = static::$MYSQLI_TYPE_MAP[$phpType] ?? null;

			if(empty($mysqliType)){
				//= The following query parameter type is not supported:
				throw new Exception("The following query parameter type is not supported: $phpType");
			}

			// bind_param and call_user_func_array require references
			$parameterReferences[] = &$parameters[$i];
			$parameterTypes[] = $mysqliType;
		}

		array_unshift($parameterReferences, implode('', $parameterTypes));
		
		if(!call_user_func_array([$statement, 'bind_param'], $parameterReferences)){
			//= Binding query parameters failed
			throw new Exception('Binding query parameters failed');
		}
	}

	/**
	 * Pseudo insert query parameters
	 * @param string $sql 
	 * @param array $params 
	 * @param bool $trim_sql When set to true, the SQL will be trimmed (i.e. leading and trailing whitespace will be removed from each line and line breaks replaced by a single space).
	 * @return string 
	 */
	public static function pseudoInsertQueryParameters($sql, $params, $trim_sql = false) {
		if ($trim_sql) {
			$sql = implode(" ", array_filter(array_map('trim', explode("\n", trim($sql)))));
		}
		$parts = explode('?', $sql);

		$sql = '';
		for($i = 0; $i<count($parts); $i++){
			if($i > count($params)-1){
				$param = '';
			}
			else{
				$param = $params[$i];
				if($param === null){
					$param = 'NULL';
				}
				else if (gettype($param) === 'string'){
					$param = "'" . db_escape($param) . "'";
				}
			}

			$sql .= $parts[$i];
			$sql .= $param;
		}

		return $sql;
	}

    // Return the name of a table's foreign key by providing the column name in that table OR the referenced column name from the other table
    // Null is returned if not found
    public static function getForeignKeyByCol($table, $col)
    {
        $fks = self::getAllForeignKeys($table);
        return $fks[$col] ?? null;
    }

    // Return as an array the names of all the foreign keys in a table with the column name as the array key
    public static function getAllForeignKeys($table)
    {
        $sql = "show create table `$table`";
        $q = db_query($sql);
        $fks = [];
        if (!$q || !db_num_rows($q)) return $fks;
        $row = db_fetch_assoc($q);
        preg_match_all('/CONSTRAINT `(.*?)` FOREIGN KEY \(`(.*?)`\) REFERENCES/', $row['Create Table'], $matches);
        if (isset($matches[1]) && is_array($matches[1]) && !empty($matches[1])) {
            foreach ($matches[1] as $key=>$fk) {
                $fks[$matches[2][$key]] = $fk;
            }
        }
        return $fks;
    }
}
