<?php
namespace ExternalModules;

use Crypto;
use UserRights;
use ZipArchive;

if(ExternalModules::isTesting()){
	error_reporting(E_ALL);
}

require_once __DIR__ . "/AbstractExternalModule.php";
require_once __DIR__ . "/HookRunner.php";
require_once __DIR__ . "/Query.php";
require_once __DIR__ . "/framework/Framework.php";
require_once __DIR__ . "/framework/ModuleApiException.php";
require_once __DIR__ . "/edocs/AbstractEDocCopier.php";
require_once __DIR__ . "/edocs/ProjectCopyEDocCopier.php";

if(ExternalModules::isCommandLine()){
	// This is required for redcap when running on the command line (including unit testing).
	if (!defined('NOAUTH')) {
		define('NOAUTH', true);
	}
}

if(ExternalModules::isTesting()){
	ini_set('error_log', '');
	require_once __DIR__ . '/../tests/ModuleBaseTest.php';
}

/**
 * Some modules (like MyCap) include an older version of the voku/portable-utf8 composer library which automatically redirects URLs
 * that contain non UTF-8 characters when "vendor/autoload.php" runs.  The exit() call from this redirection is interpreted
 * by the framework as a module error, causing unnecessary emails.  Per Portable UTF8's docs, adding the following disables this redirection.
 */
if(!defined('PORTABLE_UTF8__DISABLE_AUTO_FILTER')){
	define('PORTABLE_UTF8__DISABLE_AUTO_FILTER', 1);
}

// This was added to fix an issue that was only occurring on Jon Swafford's Mac.
// Mark wishes we had spent more time to understand why this was required only on his local.
if (class_exists('ExternalModules\ExternalModules')) {
	return;
}

use \DateTime;
use \Exception;
use InvalidArgumentException;
use \Throwable;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;

class ExternalModules
{
	// Mark has twice started refactoring to use actual null values so that the following placeholder string is unnecessary.
	// It would be a large & risky change that affects most get & set settings methods.
	// It's do-able, but it would be time consuming, and we'd have to be very careful to test dozens of edge cases.
	const SYSTEM_SETTING_PROJECT_ID = 'NULL';

	const KEY_VERSION = 'version';
	const KEY_ENABLED = 'enabled';
	const KEY_DISCOVERABLE = 'discoverable-in-project';
	const KEY_USER_ACTIVATE_PERMISSION = 'user-activate-permission';
	const KEY_RESERVED_HIDE_FROM_NON_ADMINS_IN_PROJECT_LIST = 'reserved-hide-from-non-admins-in-project-list';
	const KEY_RESERVED_DISABLE_AUTH_API = 'reserved-disable-auth-api';
	const KEY_RESERVED_DISABLE_NO_AUTH_API = 'reserved-disable-no-auth-api';
	const KEY_CONFIG_USER_PERMISSION = 'config-require-user-permission';
	const LANGUAGE_KEY_FOUND = 'Language Key Found';
	const CSRF_MIN_FRAMEWORK_VERSION = 8;
	const PERMISSIONS_REMOVED_FRAMEWORK_VERSION = 12;
	const DEV_DIR_NAME = 'external_modules';
	const EDITOR_DIRECTORIES = [
		'.idea',
		'.vscode'
	];
	const DATACORE_EMAIL = 'datacore@vumc.org';
	const DATACORE_DEV_EMAIL_LIST = [
		'Andrew Johnson' => [
				'alternate_names' => [],
				'email_address' => 'andrew.c.johnson@vumc.org'
		],
		'Chad Lightner' => [
				'alternate_names' => [],
				'email_address' => 'chad.lightner@vumc.org'
		],
		'Eva Bascompte Moragas' => [
			'alternate_names' => ['Eva Bascompte'],
			'email_address' => 'eva.bascompte.moragas@vumc.org'
		],
		'Hannah Tompkins' => [
			'alternate_names' => ['Clint Tompkins'],
			'email_address' => 'hannah.tompkins@vumc.org'
		],
		'John Paul IV' => [
			'alternate_names' => ['John Paul'],
			'email_address' => 'john.paul.iv@vumc.org'
		],
		'Karen Palacios' => [
			'alternate_names' => [],
			'email_address' => 'karen.palacios@vumc.org'
		],
		'Kyle Chesney' => [
			'alternate_names' => [],
			'email_address' => 'kyle.chesney@vumc.org'
		],
		'Kyle McGuffin' => [
			'alternate_names' => [],
			'email_address' => 'kyle.mcguffin@vumc.org'
		],
		'Mark McEver' => [
			'alternate_names' => [],
			'email_address' => 'mark.mcever@vumc.org'
		],
		'Ryan Moore' => [
			'alternate_names' => [],
			'email_address' => 'james.r.moore@vumc.org'
		],
		'Travis Wilson' => [
			'alternate_names' => ['Travis M. Wilson'],
			'email_address' => 'travis.m.wilson@vumc.org'
		],
	];

	//region Language feature-related constants

	/**
	 * The name of the system-level language setting.
	 */
	const KEY_LANGUAGE_SYSTEM = 'reserved-language-system';
	/**
	 * The name of the project-level language setting.
	 */
	const KEY_LANGUAGE_PROJECT = 'reserved-language-project';
	/**
	 * Then name of the default language.
	 */
	const DEFAULT_LANGUAGE = 'English';
	/**
	 * The name of the language folder. This is a subfolder of a module's folder.
	 */
	const LANGUAGE_FOLDER_NAME = "lang";
	/**
	 * The prefix for all external module-related keys in the global $lang.
	 */
	const EM_LANG_PREFIX = "emlang_";
	/**
	 * The prefix for fields in config.json that contain language file keys.
	 */
	const CONFIG_TRANSLATABLE_PREFIX = "tt_";
	private static $CONFIG_TRANSLATABLE_KEYS = [
		"name", 
		"description", 
		"documentation", 
		"icon", 
		"url", 
		"required", 
		"hidden", 
		"default", 
		"cron_description"
	];
	private static $CONFIG_NONTRANSLATABLE_SECTIONS = [
		"authors",
		"permissions",
		"no-auth-pages",
		"branchingLogic",
		"compatibility"
	];

	/**
	 * List of valid characters for a language key.
	 */
	const LANGUAGE_ALLOWED_KEY_CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_";

	//endregion

	const KEY_RESERVED_IS_CRON_RUNNING = 'reserved-is-cron-running';
	const KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME = 'reserved-last-long-running-cron-notification-time';
	const KEY_RESERVED_CRON_MODIFICATION_NAME = "reserved-modification-name";

	const TEST_MODULE_PREFIX = 'unit_testing_prefix';
	const TEST_MODULE_TWO_PREFIX = 'unit_testing_prefix_two';
	const TEST_MODULE_VERSION = 'v1.0.0';

	const DISABLE_EXTERNAL_MODULE_HOOKS = 'disable-external-module-hooks';
	const RICH_TEXT_UPLOADED_FILE_LIST = 'rich-text-uploaded-file-list';

	const OVERRIDE_PERMISSION_LEVEL_SUFFIX = '_override-permission-level';
	const OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS = 'design';

	// We can't write values larger than this to the database, or they will be truncated.
	const SETTING_KEY_SIZE_LIMIT = 255;
	const SETTING_SIZE_LIMIT = 16777215;
	const LOG_MESSAGE_SIZE_LIMIT = 16777215;
	const LOG_PARAM_NAME_SIZE_LIMIT = 255;
	const LOG_PARAM_VALUE_SIZE_LIMIT = 16777215;

	const EXTERNAL_MODULES_TEMPORARY_RECORD_ID = 'external-modules-temporary-record-id';

	// Copy WordPress's time convenience constants
	const MINUTE_IN_SECONDS = 60;
	const HOUR_IN_SECONDS = 3600;
	const DAY_IN_SECONDS = 86400;
	const WEEK_IN_SECONDS = 604800;
	const MONTH_IN_SECONDS = 2592000;
	const YEAR_IN_SECONDS = 31536000;

	const COMPLETED_STATUS_WHERE_CLAUSE = "
		WHERE project_id = ?
		AND record = ?
		AND event_id = ?
		AND field_name = ?
	";

	private static $MIN_REDCAP_VERSIONS_BY_FRAMEWORK_VERSION;
	
	private static $SERVER_NAME;

	# path for the modules directory
	public static $MODULES_PATH;

	public static $lastHandleErrorResult;
	public static $exitAfterHookAction;

	private static $USERNAME;
	private static $SUPER_USER;
	private static $INCLUDED_RESOURCES;

	private static $currentHookRunner;
	private static $temporaryRecordId;
	private static $shuttingDown = false;
	private static $disablingModuleDueToException = false;

	private static $initialized = false;
	private static $activeModulePrefix;
	private static $instanceCache = array();
	private static $systemSettingCache;
	private static $idsByPrefix;

	private static $systemwideEnabledVersions;
	private static $projectEnabledDefaults;
	private static $projectEnabledOverrides;
	
	private static $deletedModules;

	/** Caches module configurations. */
	private static $configs = array();

	// Holds module prefixes for which language strings have already been added to $lang.
	private static $localizationInitialized = array();

	private static $tempPaths = [];

	private static $TEST_SETTINGS = [];

	private static $forceUsePrimaryDbConnection = false;

	/**
	 * @param string $prefix
	 *
	 * @return string
	 */
	public static function getTestModuleDirectoryPath($prefix = self::TEST_MODULE_PREFIX) {
		return static::getSafePath($prefix . '_' . self::TEST_MODULE_VERSION, __DIR__ . '/../tests/');
	}

	# two reserved settings that are there for each project
	# KEY_VERSION, if present, denotes that the project is enabled system-wide
	# KEY_ENABLED is present when enabled for each project
	# Modules can be enabled for all projects (system-wide) if KEY_ENABLED == 1 for system value
	/**
	 * @return (string|string[][]|true)[][]
	 */
	public static function getReservedSettings() {
		return array(
			array(
				'key' => self::KEY_VERSION,
				'hidden' => true,
			),
			array(
				'key' => self::KEY_ENABLED,
				//= Enable module on all projects by default: Unchecked (default) = Module must be enabled in each project individually
				'name' => self::tt("em_config_1"), 
				'type' => 'checkbox',
			),
			array(
				'key' => self::KEY_DISCOVERABLE,
				//= Make module discoverable by users: Display info on External Modules page in all projects
				'name' => self::tt("em_config_2"),
				'type' => 'checkbox'
			),
			array(
				'key' => self::KEY_USER_ACTIVATE_PERMISSION,
				//= Allow the module to be activated in projects by users with Project Setup/Design rights
				'name' => self::tt("em_config_8"),
				'type' => 'checkbox'
			),
			array(
				'key' => static::KEY_RESERVED_HIDE_FROM_NON_ADMINS_IN_PROJECT_LIST,
				'allow-project-overrides' => true,
				'name' => static::tt('em_config_9'),
				'project-name' => static::tt('em_config_10'),
				'type' => 'checkbox',
				'super-users-only' => true
			),
			array(
				'key' => self::KEY_CONFIG_USER_PERMISSION,
				//= Module configuration permissions in projects: By default, users with Project Setup/Design privileges can modify this module's project-level configuration settings. Alternatively, project users can be given explicit module-level permission (via User Rights page) in order to do so
				'name' => self::tt("em_config_3"),
				'type' => 'dropdown',
				"choices" => array(
						//= Require Project Setup/Design privilege"
						array("value" => "", "name" => self::tt("em_config_3_1")),
						//= Require module-specific user privilege
						array("value" => "true", "name" => self::tt("em_config_3_2"))
				)
            ),
            array(
                "key" => self::KEY_RESERVED_DISABLE_AUTH_API,
                "allow-project-overrides" => true,
                "super-users-only" => true,
                "name" => self::tt("em_config_11"),
                //= Disable authenticated access to this module's API methods: This effectively disables all access to module API methods in project contexts.
                "type" => "checkbox"
            ),
            array(
                "key" => self::KEY_RESERVED_DISABLE_NO_AUTH_API,
                "name" => self::tt("em_config_12"),
                //= Disable non-authenticated access to this module's API methods:
                "type" => "checkbox"
            )
		);
	}

	/**
	 * @return bool
	 */
	static function isProduction()
	{
		return ($GLOBALS['is_development_server'] ?? null) === '0';
	}

	/**
	 * @return array
	 */
	static function getAllFileSettings($config) {
		if($config === null){
			return [];
		}

		$fileFields = [];
		foreach($config as $row) {
			if($row['type'] && $row['type'] == 'sub_settings') {
				$fileFields = array_merge(self::getAllFileSettings($row['sub_settings']),$fileFields);
			}
			else if ($row['type'] && ($row['type'] == "file")) {
				$fileFields[] = $row['key'];
			}
		}
		
		return $fileFields;
	}

	/**
	 * @return (array|mixed|null)[]
	 *
	 * @param string $pid
	 */
	static function formatRawSettings($moduleDirectoryPrefix, $pid, $rawSettings){
		# for screening out files below
		$config = self::getConfig($moduleDirectoryPrefix, null, $pid);
		$files = array();
		foreach(['system-settings', 'project-settings'] as $settingsKey){
			$files = array_merge(self::getAllFileSettings($config[$settingsKey]),$files);
		}

		$settings = array();

		# returns boolean
		$isExternalModuleFile = function($key, $fileKeys): bool {
			if (in_array($key, $fileKeys)) {
				return true;
			}
			foreach ($fileKeys as $fileKey) {
				if (preg_match('/^'.$fileKey.'____\d+$/', $key)) {
					return true;
				}
			}
			return false;
		};

		# store everything BUT files and multiple instances (after the first one)
		foreach($rawSettings as $key=>$value){
			# files are stored in a separate $.ajax call
			# numeric value signifies a file present
			# empty strings signify non-existent files (systemValues or empty)
			if (!$isExternalModuleFile($key, $files) || !is_numeric($value)) {
				if($value === '') {
					$value = null;
				}

				if (preg_match("/____/", $key)) {
					$parts = preg_split("/____/", $key);
					$shortKey = array_shift($parts);

					if(!isset($settings[$shortKey])){
						$settings[$shortKey] = [];
					}

					$thisInstance = &$settings[$shortKey];
					foreach($parts as $thisIndex) {
						if(!isset($thisInstance[$thisIndex])) {
							$thisInstance[$thisIndex] = [];
						}
						$thisInstance = &$thisInstance[$thisIndex];
					}

					$thisInstance = $value;
				} else {
					$settings[$key] = $value;
				}
			}
		}

		return $settings;
	}

	// This is called from framework[v5]::setProjectSettings()
	/**
	 * @return void
	 *
	 * @param array $settings
	 */
	static function saveProjectSettings($moduleDirectoryPrefix, $pid, $settings)
	{
		self::setSettings($moduleDirectoryPrefix, $pid, $settings);
	}

	/**
	 * @return array
	 *
	 * @param string $pid
	 */
	static function saveSettings($moduleDirectoryPrefix, $pid, $rawSettings)
	{
		$settings = self::formatRawSettings($moduleDirectoryPrefix, $pid, $rawSettings);

		/**
		 * Long term it might make sense to remove the line setting $pid
		 * to the empty string at the top of save-settings.php,
		 * so that the following line can be a null check instead.
		 */
		if($pid === ''){
			$pid = static::SYSTEM_SETTING_PROJECT_ID;
		}

		return self::setSettings($moduleDirectoryPrefix, $pid, $settings);
	}

	/**
	 * @return array
	 *
	 * @param (array|mixed|null)[] $settings
	 * @param string $pid
	 */
	private static function setSettings($moduleDirectoryPrefix, $pid, $settings) {
		$overridableSettings = ExternalModules::getOverridableSettings(ExternalModules::getConfig($moduleDirectoryPrefix));
		$overridableSettingKeys = array_flip(array_column($overridableSettings, 'key'));
		
		$saveSqlByField = [];
		foreach($settings as $key => $value) {
			if($pid !== static::SYSTEM_SETTING_PROJECT_ID && isset($overridableSettingKeys[$key])){
				$systemValue = static::getSystemSetting($moduleDirectoryPrefix, $key);
				if($value === $systemValue){
					// Delete the project value, letting the system value override it.
					$value = null;
				}
			}

			$sql = self::setSetting($moduleDirectoryPrefix, $pid, $key, $value);
			if(!empty($sql)){
				$saveSqlByField[$key] = $sql;
			}
		}
		return $saveSqlByField;
	}

	// Allow the addition of further module directories on a server.  For example, you may want to have
	// a folder used for local development or controlled by a local version control repository (e.g. modules_internal, or modules_staging)
	// $external_module_alt_paths, if defined, is a pipe-delimited array of paths stored in redcap_config.
	/**
	 * @return string[]
	 */
	public static function getAltModuleDirectories()
	{
		global $external_module_alt_paths;
		$modulesDirectories = array();
		if (!empty($external_module_alt_paths)) {
			$paths = explode('|',$external_module_alt_paths);
			foreach ($paths as $path) {
				$path = trim($path);
				if($valid_path = realpath($path)) {
					array_push($modulesDirectories, $valid_path . DS);
				} else {
					// Try pre-pending APP_PATH_DOCROOT in case the path is relative to the redcap root
					$path = dirname(APP_PATH_DOCROOT) . DS . $path;
					if($valid_path = realpath($path)) {
						array_push($modulesDirectories, $valid_path . DS);
					}
				}
			}
		}
		return $modulesDirectories;
	}

	// Return array of all directories where modules are stored (including any alternate directories)
	/**
	 * @return (mixed|string)[]
	 */
	public static function getModuleDirectories()
	{
		// Get module directories
		$modulesDirectories = [dirname(APP_PATH_DOCROOT).DS.'modules'.DS, APP_PATH_EXTMOD.'example_modules'.DS];
		// Add any alternate module directories
		$modulesDirectoriesAlt = self::getAltModuleDirectories();
		foreach ($modulesDirectoriesAlt as $thisDir) {
			array_push($modulesDirectories, $thisDir);
		}
		// Return directories array
		return $modulesDirectories;
	}

	// Return array of all module sub-directories located in directories where modules are stored (including any alternate directories)
	/**
	 * @return (int|string)[]
	 */
	public static function getModulesInModuleDirectories()
	{
		$modules = array();
		// Get module sub-directories
		$modulesDirectories = self::getModuleDirectories();
		foreach ($modulesDirectories as $dir) {
			$subDirs = getDirFiles($dir);
			if(!is_array($subDirs)){
				continue;
			}

			foreach ($subDirs as $module) {
				// Use the module directory as a key to prevent duplicates from alternate module directories.
				$modules[$module] = true;
			}
		}
		// Return directories array
		return array_keys($modules);
	}

	/**
	 * @return void
	 */
	static function enableErrors(){
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);
	}

	private static function getCurrentHookRunner(): ?HookRunner{
		return self::$currentHookRunner;
	}

	/**
	 * @return void
	 *
	 * @param HookRunner|null $hookRunner
	 */
	private static function setCurrentHookRunner($hookRunner){
		self::$currentHookRunner = $hookRunner;
	}

	# initializes the External Module apparatus
	/**
	 * @return void
	 */
	static function initialize()
	{
		if(!self::isProduction()){
			// Assume this is a developer's machine
			self::enableErrors();
		}

		// Load framework strings unless they have already been loaded by REDCap
		if (!defined("EM_STRINGS_LOADED")) {
			$lang_file = __DIR__.DS."English.ini";
			$em_strings = parse_ini_file($lang_file);
			if ($em_strings !== false) {
				foreach ($em_strings as $key => $text) {
					$GLOBALS["lang"][$key] = $text;
				}
			}
		}

		// Get module directories
		$modulesDirectories = self::getModuleDirectories();

		$modulesDirectoryName = '/modules/';
		if(strpos($_SERVER['REQUEST_URI'], $modulesDirectoryName) === 0){
			// We used to throw an exception here, but we got sick of those emails (especially when bots triggered them).
			//= Requests directly to module version directories are disallowed. Please use the getUrl() method to build urls to your module pages instead.
			static::logStackTraceAndExit(self::tt("em_errors_1"));
		}

		self::$SERVER_NAME = SERVER_NAME;

		self::$MODULES_PATH = $modulesDirectories;
		self::$INCLUDED_RESOURCES = [];

		self::limitDirectFileAccess();

		register_shutdown_function(function () {
			static::onShutdown();
		});
	}

	private static function onShutdown(){
		self::$shuttingDown = true;

		foreach(static::$tempPaths as $path){
			static::rrmdir($path);
		}
	}

	/**
	 * This method is called from REDCap core.
	 * 
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	static function handleFatalError($code, $errorMessage, $file, $line){
		$activeModulePrefix = self::getActiveModulePrefix();
		if ($activeModulePrefix == null) {
			// A fatal error did not occur in the middle of a module operation.
			return false;
		}

		if($code === E_NOTICE){
			// This is just a notice, which likely means it occurred BEFORE an offending die/exit call.
			// Ignore this notice and show the general die/exit call warning instead.
			$errorMessage = null;
		}

		$hookRunner = self::getCurrentHookRunner();
		$unlockFailureMessage = '';
		if (empty($hookRunner)) {
			$message = 'Could not instantiate';
		} else {
			$hookBeingExecuted = $hookRunner->getName();
			$message = "The '" . $hookBeingExecuted . "' hook did not complete for";

			// If the current "hook" was a cron, we need to unlock it so it can run again.
			$config = self::getConfig($activeModulePrefix);
			foreach ($config['crons'] as $cron) {
				if ($cron['cron_name'] == $hookBeingExecuted) {
					try{
						// Works in cases like die()/exit() calls.
						self::unlockCron($activeModulePrefix);
					}
					catch(\Throwable $t){
						// In some cases (like out of memory errors) the database has gone away by this point.
						// To guarantee unlocking, we could write to a file instead of the DB, and detect that file on the next cron run.
						$unlockFailureMessage = "\n\nIf this is a timed cron, it could not be automatically unlocked due to the database connection being closed already.  An email will be sent at the time of each scheduled run containing a link to unlock the cron.";
					}

					break;
				}
			}
		}

		$message .= " the '$activeModulePrefix' module";

		if($errorMessage){
			$message .= " because of the following error.  Stack traces are unfortunately not available for this type of error.  If this error is caused by a manually required third party dependency, a common fix is to use composer (or any class autoloader) to require it instead:\n\n";
			$message .= 'Error Message: ' . $errorMessage . "\n";
			$message .= 'File: ' . $file . "\n";
			$message .= 'Line: ' . $line . "\n";
		} else {
			$output = ob_get_contents();
			$duplicateRequestMessage = $GLOBALS['lang']['dataqueries_352'];
			if(!empty($duplicateRequestMessage) && strpos($output, $duplicateRequestMessage) !== false){
				// REDCap detected and killed a duplicate request/query.
				// The is expected behavior.  Do not report this error.
				return true;
			}
			else{
				$message .= ", but a specific cause could not be detected.  This failure should be resolved ASAP, as it could prevent other module hooks and/or crons from running.  This could be caused by a die() or exit() call in the module which needs to be replaced with an exception to provide more details, or a \$module->exitAfterHook() call to allow other modules to execute for the current hook.";
			}
		}

		$message .= $unlockFailureMessage;

		if (basename($_SERVER['REQUEST_URI']) == 'enable-module.php') {
			// An admin was attempting to enable a module.
			// Simply display the error to the current user, instead of sending an email to all admins about it.
			echo $message;
			return true;
		}

		if (self::isSuperUser() && self::isProduction()) {
			//= The current user is a super user, so this module will be automatically disabled
			$message .= "\n".self::tt("em_errors_2")."\n";

			// We can't just call disable() from here because the database connection has been destroyed.
			// Disable this module via AJAX instead.
			?>
			<br>
			<h4 id="external-modules-message">
				<?= self::tt("em_errors_3", $activeModulePrefix) ?>
				<!--= A fatal error occurred while loading the "{0}" external module. Disabling that module... -->
			</h4>
			<script type="text/javascript">
				var request = new XMLHttpRequest();
				request.onreadystatechange = function () {
					if (request.readyState == XMLHttpRequest.DONE) {
						var messageElement = document.getElementById('external-modules-message')
						if (request.responseText == 'success') {
							messageElement.innerHTML = <?=json_encode(self::tt("em_errors_4", $activeModulePrefix))?>;
							//= The {0} external module was automatically disabled in order to allow REDCap to function properly. The REDCap administrator has been notified. Please save a copy of the above error and fix it before re-enabling the module.
						}
						else {
							//= 'An error occurred while disabling the "{0}" module:
							messageElement.innerHTML += '<br>' + <?=json_encode(self::tt("em_errors_5", $activeModulePrefix))?> + ' ' + request.responseText;
						}
					}
				};

				request.open("POST", "<?=APP_URL_EXTMOD_RELATIVE?>manager/ajax/disable-module.php?<?=self::DISABLE_EXTERNAL_MODULE_HOOKS?>");
				request.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
				request.send("module=" + <?=json_encode($activeModulePrefix)?>);
			</script>
			<?php
		}

		// Log the message as well, in case the important part gets truncated in redcap_error_log
		static::errorLog($message);
		static::handleError("REDCap External Module Error", $message, $activeModulePrefix);

		return true;
	}

	/**
	 * @return bool
	 */
	public static function shouldUseCookieforCSRFToken(){
		return 
			ExternalModules::isNoAuth()
			||
			/**
			 * We don't want to authenticate against a full REDCap user session for surveys,
			 * so treat them like NOAUTH pages to be safe.  This means ajax module requests on
			 * survey pages must be NOAUTH requests.
			 */
			ExternalModules::isSurveyPage()
		;
	}

	//region Language features

	/**
	 * Initialized the JavaScript Language store (ExternalModules.$lang).
	 *
	 * @return void
	 */
	public static function tt_initializeJSLanguageStore() {
		?>
		<script>
			(function(){
				// Ensure ExternalModules.$lang has been initialized. $lang provides localization support for all external modules.
				if(window.ExternalModules === undefined) {
					window.ExternalModules = {}
				}
				if (window.ExternalModules.$lang === undefined) {
					window.ExternalModules.$lang = {}
					var lang = window.ExternalModules.$lang
					/**
					 * Holds the strings indexed by a key.
					 */
					lang.strings = {}
					/**
					 * Returns the number of language items available.
					 * @returns {number} The number of items in the language store.
					 */
					lang.count = function() {
						var n = 0
						for (var key in this.strings) {
							if (this.strings.hasOwnProperty(key))
								n++
						}
						return n
					}
					/**
					 * Logs key and corresponding string to the console.
					 * @param {string} key The key of the language string.
					 */
					lang.log = function(key) {
						var s = this.get(key)
						if (s != null)
							console.log(key, s)
					}
					/**
					 * Logs the whole language cache to the console.
					 */
					lang.logAll = function() {
						console.log(this.strings)
					}
					/**
					 * Get a language string (translateable text) by its key.
					 * @param {string} key The key of the language string to get.
					 * @returns {string} The string stored under key, or null if the string is not found.
					 */
					lang.get = function(key) {
						if (!this.strings.hasOwnProperty(key)) {
							console.error("Key '" + key + "' does not exist in $lang.")
							return null
						}
						return this.strings[key]
					}
					/**
					 * Add a language string.
					 * @param {string} key The key for the string.
					 * @param {string} string The string to add.
					 */
					lang.add = function(key, string) {
						this.strings[key] = string
					}
					/**
					 * Remove a language string.
					 * @param {string} key The key for the string.
					 */
					lang.remove = function(key) {
						if (this.strings.hasOwnProperty(key))
							delete this.strings[key]
					}
					/**
					 * Extracts interpolation values from variable function arguments.
					 * @param {Array} inputs An array of interpolation values (must include the key as first element).
					 * @returns {Array} An array with the interpolation values.
					 */
					lang._getValues = function(inputs) {
						var values = Array()
						if (inputs.length > 1) {
							// If the first value is an array or object, use it instead.
							if (Array.isArray(inputs[1]) || typeof inputs[1] === 'object' && inputs[1] !== null) {
								values = inputs[1]
							}
							else {
								values = Array.prototype.slice.call(inputs, 1)
							}
						}
						return values
					}
					/**
					 * Get and interpolate a translation.
					 * @param {string} key The key for the string.
					 * Note: Any further arguments after key will be used for interpolation. If the first such argument is an array, it will be used as the interpolation source.
					 * @returns {string} The interpolated string.
					 */
					lang.tt = function(key) {
						var string = this.get(key)
						var values = this._getValues(arguments)
						return this.interpolate(string, values)
					}
					/**
					 * Interpolates a string using the given values.
					 * @param {string} string The string template.
					 * @param {any[] | object} values The values used for interpolation.
					 * @returns {string} The interpolated string.
					 */
					lang.interpolate = function(string, values) {
						if (typeof string == 'undefined' || string == null) {
							console.warn('$lang.interpolate() called with undefined or null.')
							return ''
						}
						// Is string not a string, or empty? Then there is nothing to do.
						if (typeof string !== 'string' || string.length == 0) {
							return string
						}
						// Placeholers are in curly braces, e.g. {0}. Optionally, a type hint can be present after a colon (e.g. {0:Date}), 
						// which is ignored however. Hints must not contain any curly braces.
						// To not replace a placeholder, the first curly can be escaped with a %-sign like so: '%{1}' (this will leave '{1}' in the text).
						// To include '%' as a literal before a curly opening brace, a double-% ('%%') must be used, i.e. '%%{0}' with value x this will result in '%x'.
						// Placeholder names can be strings (a-Z0-9_), too (need associative array then). 
						// First, parse the string.
						var allowed = '<?=self::LANGUAGE_ALLOWED_KEY_CHARS?>'
						var matches = []
						var mode = 'scan'
						var escapes = 0
						var start = 0
						var key = ''
						var hint = ''
						for (var i = 0; i < string.length; i++) {
							var c = string[i]
							if (mode == 'scan' && c == '{') {
								start = i
								key = ''
								hint = ''
								if (escapes % 2 == 0) {
									mode = 'key'
								}
								else {
									mode = 'store'
								}
							}
							if (mode == 'scan' && c == '%') {
								escapes++
							}
							else if (mode == 'scan') {
								escapes = 0
							}
							if (mode == 'hint') {
								if (c == '}') {
									mode = 'store'
								}
								else {
									hint += c
								}
							}
							if (mode == 'key') {
								if (allowed.includes(c)) {
									key += c
								}
								else if (c == ':') {
									mode = 'hint'
								}
								else if (c == '}') {
									mode = 'store'
								}
							}
							if (mode == 'store') {
								var match = {
									key: key,
									hint: hint,
									escapes: escapes,
									start: start,
									end: i
								}
								matches.push(match)
								key = ''
								hint = ''
								escapes = 0
								mode = 'scan'
							}
						}
						// Then, build the result.
						var result = ''
						if (matches.length == 0) {
							result = string
						} else {
							prevEnd = 0
							for (var i = 0; i < matches.length; i++) {
								var match = matches[i]
								var len = match.start - prevEnd - (match.escapes > 0 ? Math.max(1, match.escapes - 1) : 0)
								result += string.substr(prevEnd, len)
								prevEnd = match.end 
								if (match.key != '' && typeof values[match.key] !== 'undefined') {
									result += values[match.key]
									prevEnd++
								}
							}
							result += string.substr(prevEnd)
						}
						return result
					}
				}
			})()
		</script>
		<?php
	}

	/**
	 * Retrieve and interpolate a language string.
	 * 
	 * @param Array $args The arguments passed to tt() or tt_js(). The first element is the language key; further elements are used for interpolation.
	 * @param string $prefix A module-specific prefix used to generate a scoped key (or null if not scoped to a module; default = null).
	 * @param bool $jsEncode Indicates whether the result should be passed through json_encode() (default = false).
	 * @param bool $escapeHTML Indicates whether interpolated values should first be submitted to htmlspecialchars().
	 * 
	 * @return string The (interpolated) language string corresponding to the given key.
	 */
	public static function tt_process($args, $prefix = null, $jsEncode = false, $escapeHTML = true) {

		// Perform some checks.
		// Do not translate exception messages here to avoid potential infinite recursions.
		if (!is_array($args) || count($args) < 1 || !is_string($args[0]) || strlen($args[0]) == 0) {
			throw new Exception("Language key must be a not-empty string."); 
		}
		if (!is_null($prefix) && !(is_string($prefix) && strlen($prefix) > 0)) {
			throw new Exception("Prefix must either be null or a not-empty string.");
		}

		// Get the key (prefix if necessary).
		$original_key = $args[0];
		$key = is_null($prefix) ? $original_key : self::constructLanguageKey($prefix, $original_key);
		
		// Check if there are additional arguments beyond the first (the language file key).
		// If the first additional argument is an array, use it for interpolation.
		// Otherwise, use the arguments (minus the first, which is the key).
		$values = array();
		if (count($args) > 1) {
			$values = is_array($args[1]) ? $args[1] : array_slice($args, 1);
		}

		global $lang;

		// Get the string - if the key doesn't exist, provide a corresponding message to facilitate debugging.
		$string = $lang[$key] ?? null;
		if ($string === null) {
			$string = self::getLanguageKeyNotDefinedMessage($original_key, $prefix);
			// Clear interpolation values.
			$values = array();
		}

		// Get and return interpolated string (optionally JSON-encoded).
		$interpolated = self::interpolateLanguageString($string, $values, $escapeHTML);
		return $jsEncode ? json_encode($interpolated) : $interpolated;
	}

	/**
	 * Returns the translation for the given global language key.
	 * 
	 * @param string $key The language key.
	 * @param-7.3 mixed ...$values Optional values to be used for interpolation. If the argument after $key is an array, it's members will be used and any further arguments will be ignored. Values are submitted to htmlspecialchars() before being inserted.
	 * 
	 * @return string The translation (with interpolations).
	 */
	public static function tt($key) {
		// Get all arguments and send off for processing.
		return self::tt_process(func_get_args());
	}

	/**
	 * Returns the translation for the given global language key.
	 * 
	 * @param string $key The language key.
	 * @param-7.3 mixed ...$values Optional values to be used for interpolation. If the argument after $key is an array, it's members will be used and any further arguments will be ignored. No sanitization of values is performed.
	 * 
	 * @return string The translation (with interpolations).
	 */
	public static function tt_raw($key) {
		// Get all arguments and send off for processing.
		return self::tt_process(func_get_args(), null, false, false);
	}

	/**
	 * @return string
	 *
	 * @param string $key
	 * @param null|string $prefix
	 */
	public static function getLanguageKeyNotDefinedMessage($key, $prefix) {
		$message = "Language key '{$key}' is not defined";
		$message .= is_null($prefix) ? "." : " for module '{$prefix}'.";
		return $message;
	}

	/**
	 * Returns a JSON-encoded translation for the given global language key.
	 * 
	 * @param string $key The language key.
	 * @param-7.3 mixed ...$values Optional values to be used for interpolation. If the argument after $key is an array, it's members will be used and any further arguments will be ignored. Values are submitted to htmlspecialchars() before being inserted. 
	 * 
	 * @return string The translation (with interpolations) encoded for assignment to JS variables.
	 */
	public static function tt_js($key) {
		// Get all arguments and send off for processing.
		return self::tt_process(func_get_args(), null, true);
	}

	/**
	 * Transfers one (interpolated) or many strings (without interpolation) to the JavaScript language store.
	 *
	 * @param mixed $key (optional) The language key or an array of language keys.

	 * Note: When a single language key is given, any number of arguments can be supplied and these will be used for interpolation. When an array of keys is passed, then any further arguments will be ignored and the language strings will be transfered without interpolation. If no key or null is passed, all language strings will be transferred.
	 *
	 * @return void
	 */
	public static function tt_transferToJSLanguageStore($key = null) {
		// Get all arguments and send off for processing.
		self::tt_prepareTransfer(func_get_args(), null);

	}

	/**
	 * Handles the preparation of key/value pairs for transfer to JavaScript.
	 *
	 * @param Array $args The arguments passed to tt JavaScript shuttle functions. The first element is the language key; further elements are used for interpolation.
	 * @param string $prefix A module-specific prefix used to generate a scoped key (or null if not scoped to a module; default = null).
	 * @param boolean $escapeHTML Determines whether interpolation values are submitted to htmlspecialchars().
	 *
	 * @return void
	 */
	public static function tt_prepareTransfer($args, $prefix = null, $escapeHTML = true) {

		// Perform some checks.
		if (!is_null($prefix) && !(is_string($prefix) && strlen($prefix) > 0)) {
			throw new Exception("Prefix must either be null or a not-empty string.");
		}

		// Deconstruct $args. The first element must be key(s). 
		// Any further are interpolation values and only needed in case of a single key passed as string.
		$keys = $args[0];
		$values = array();
		// If $key is null, add all keys.
		if ($keys === null) {
			// Get all keys, unscoped - they will be prefixed later if needed.
			$keys = self::getLanguageKeys($prefix, false);
		}
		else if (!is_array($keys)) {
			// Single key, convert to array and get interpolation values.
			$keys = array($keys);
			$values = array_slice($args, 1);
			// If the first value is an array, use it as values.
			if (count($values) && is_array($values[0])) $values = $values[0];
		}

		// Prepare the transfer array and add all key/value pairs to the transfer array.
		$to_transfer = array();
		foreach ($keys as $key) {
			$scoped_key = self::constructLanguageKey($prefix, $key);
			$to_transfer[$scoped_key] = $escapeHTML ? self::tt($scoped_key, $values) : self::tt_raw($scoped_key, $values);
		}
		// Generate output as <script>-tags.
		self::tt_transferToJS($to_transfer);
	}

	/**
	 * Adds a key/value pair directly to the language store for use in the JavaScript module object. 
	 * Value can be anything (string, boolean, array).
	 *
	 * @param string $key The language key.
	 * @param mixed $value The corresponding value.
	 * @param string $prefix A module-specific prefix used to generate a scoped key (or null if not scoped to a module; default = null).
	 *
	 * @return void
	 */
	public static function tt_addToJSLanguageStore($key, $value, $prefix = null) {
		// Check that key is a string and not empty.
		if (!is_string($key) || !strlen($key) > 0) {
			throw new Exception("Key must be a not-empty string."); // Do not translate messages targeted at devs.
		}
		$scoped_key = self::constructLanguageKey($prefix, $key);
		$to_transfer = array($scoped_key => $value);
		// Generate output as <script>-tags.
		self::tt_transferToJS($to_transfer);
	}

	/**
	 * Transfers key/value pairs to the JavaScript language store.
	 *
	 * @param Array $to_transfer An associative array containing key/value pairs.
	 *
	 * @return void
	 */
	private static function tt_transferToJS($to_transfer) {
		$n = count($to_transfer);
		// Line feeds and tabs to may HTML prettier ;)
		$lf = $n > 1 ? "\n" : "";
		$tab = $n > 1 ? "\t" : "";
		if ($n) {
			echo "<script>" . $lf;
			foreach ($to_transfer as $key => $value) {
				$key = json_encode($key);
				$value = json_encode($value);
				echo $tab . "ExternalModules.\$lang.add({$key}, {$value})" . $lf;
			}
			echo "</script>" . $lf;
		}
	}

	/**
	 * Finds all available language files for a given module.
	 * 
	 * @param string $prefix The module prefix.
	 * @param string $version The version of the module.
	 * 
	 * @return Array An associative array with the language names as keys and the full path to the INI file as values.
	 */
	private static function getLanguageFiles($prefix, $version) {
		$langs = array();
		$path = self::getModuleDirectoryPath($prefix, $version) . DS . self::LANGUAGE_FOLDER_NAME . DS;
		if (is_dir($path)) {
			/**
			 * We used to use GLOB_BRACE here, but it is not compatible with non-GNU distros (e.g. Alpine and Solaris)
			 */
			$files = array_merge(
				glob($path . "*.ini"),
				glob($path . "*.Ini"),
				glob($path . "*.iNi"),
				glob($path . "*.inI"),
				glob($path . "*.INi"),
				glob($path . "*.iNI"),
				glob($path . "*.InI"),
				glob($path . "*.INI")
			);
			foreach ($files as $filename) {
				if (is_file($filename)) {
					$lang = pathinfo($filename, PATHINFO_FILENAME);
					$langs[$lang] = $filename;
				}
			}
		}
		return $langs;
	}

	/**
	 * Gets the language set for a module.
	 * 
	 * @param string $prefix The module prefix.
	 * @param int $projectId The ID of the project (or null whenn not in a project context).
	 * 
	 * @return string The language to use for the module.
	 */
	private static function getLanguageSetting($prefix, $projectId = null) {
		// Ensure system settings are already cached
		if (empty(self::$moduleLanguageSettingCache[""])) {
			self::fillModuleLanguageSettingCache(null);
		}
		// Ensure project settings are already cached
		if (empty(self::$moduleLanguageSettingCache[$projectId])) {
			self::fillModuleLanguageSettingCache($projectId);
		}
		// Use system setting as fallback
		$lang = self::$moduleLanguageSettingCache[$projectId][$prefix] ?? self::$moduleLanguageSettingCache[""][$prefix] ?? '';
		return strlen($lang) ? $lang : self::DEFAULT_LANGUAGE;
	}

	/**
	 * Fills the module language setting cache. This prevents repeated database queries.
	 *
	 * @return void
	 *
	 * @param int|null $projectId
	 */
	private static function fillModuleLanguageSettingCache($projectId) {
		self::$moduleLanguageSettingCache[$projectId] = array();

		if ($projectId === null) {
			$key = self::KEY_LANGUAGE_SYSTEM;
		}
		else{
			$key = self::KEY_LANGUAGE_PROJECT;
		}

		$result = self::getSettings([], [$projectId], [$key]);
		while ($row = $result->fetch_assoc()) {
			self::$moduleLanguageSettingCache[$projectId][$row["directory_prefix"]] = $row["value"];
		}
	}
	/** The module language setting cache, an associative array: [ pid => [ prefix => value ] ] */
	private static $moduleLanguageSettingCache = array();
	

	/**
	 * Initializes the language features for an External Module.
	 *
	 * @param string $prefix The module's unique prefix.
	 * @param string $version The version of the module.
	 *
	 * @return void
	 */
	public static function initializeLocalizationSupport($prefix, $version) {

		// Have the module's language strings already been loaded?
		if (in_array($prefix, self::$localizationInitialized)) return;

		global $lang;

		// Get project id if available.
		$projectId = isset($GLOBALS["project_id"]) ? $GLOBALS["project_id"] : null;

		$availableLangs = self::getLanguageFiles($prefix, $version);
		if (count($availableLangs) > 0) {
			$setLang = self::getLanguageSetting($prefix, $projectId);
			// Verify the set language exists as a file, or set to default language. No warnings here if they don't.
			$translationFile = array_key_exists($setLang, $availableLangs) ? $availableLangs[$setLang] : null;
			$defaultFile = array_key_exists(self::DEFAULT_LANGUAGE, $availableLangs) ? $availableLangs[self::DEFAULT_LANGUAGE] : null;
			// Read the files.
			$default = (isset($defaultFile) && file_exists($defaultFile)) ? parse_ini_file($defaultFile) : array();
			$translation = $defaultFile != $translationFile && file_exists($translationFile) ? parse_ini_file($translationFile) : array();
			$moduleLang = array_merge($default, $translation);
			// Add to global language array $lang
			foreach ($moduleLang as $key => $val) {
				$lang_key = self::constructLanguageKey($prefix, $key);
				$lang[$lang_key] = $val;
			}
		}

		// Mark module as initialized.
		array_push(self::$localizationInitialized, $prefix);
	}

	/**
	 * Generates a key for the $lang global from a module prefix and a module-scope language file key.
	 *
	 * @param null|string $prefix
	 * @param (int|string) $key
	 *
	 * @return int|string
	 */
	public static function constructLanguageKey($prefix, $key) {
		$prefix = htmlentities($prefix ?? '', ENT_QUOTES); // Avoid psalm warnings
		return empty($prefix) ? $key : self::EM_LANG_PREFIX . "{$prefix}_{$key}";
	}

	/**
	 * Gets a list of all available language keys for the given module.
	 * 
	 * @param string $prefix The unique module prefix. If null is passed, all existing language keys (unscoped) will be returned.
	 * @param bool $scoped Determines, whether the keys returned are scoped (true = default) or global, i.e. containing the module prefixes (false).
	 * 
	 * @return Array An array of language keys.
	 */
	public static function getLanguageKeys($prefix = null, $scoped = true) {
		global $lang;
		$keys = array();
		if ($prefix === null) {
			$keys = array_keys($lang);
		}
		else {
			$key_prefix = self::EM_LANG_PREFIX . $prefix . "_";
			$key_prefix_len = strlen($key_prefix);
			foreach (array_keys($lang) as $key) {
				if (substr($key, 0, $key_prefix_len) === $key_prefix) {
					array_push($keys, $scoped ? $key : substr($key, $key_prefix_len));
				}
			}
		}
		return $keys;
	}

	/**
	 * Adds a language setting to config when translation is supported by a module.
	 * 
	 * @param Array $config The config array to which to add language setting support.
	 * @param string $prefix The module prefix.
	 * @param string $version The version of the module.
	 * @param int $projectId The project id.
	 * 
	 * @return Array A config array with language selection support enabled.
	 */
	private static function addLanguageSetting($config, $prefix, $version, $projectId = null) {
		$langs = self::getLanguageFiles($prefix, $version);
		// Does the module support translation?
		if (count($langs) > 0) {
			// Build the choices.
			$choices = array();
			$langNames = array_keys($langs);
			sort($langNames);
			// Add the default language (if available) as the first choice.
			// In the project context, we cannot leave the default value blank.
			$defaultValue = $projectId == null ? "" : self::DEFAULT_LANGUAGE;
			if (in_array(self::DEFAULT_LANGUAGE, $langNames)) {
				array_push($choices, array(
					"value" => $defaultValue, "name" => self::DEFAULT_LANGUAGE
				));
			}
			foreach ($langNames as $lang) {
				if ($lang == self::DEFAULT_LANGUAGE) continue; // Skip default, it's already there.
				array_push($choices, array(
					"value" => $lang, "name" => $lang
				));
			}
			$templates = array (
				"system-settings" => array(
					"key" => self::KEY_LANGUAGE_SYSTEM,
					//= Language file: Language file to use for this module. This setting can be overridden in the project configuration of this module
					"name" => self::tt("em_config_4"), 
					"type" => "dropdown",
					"choices" => $choices
				),
				"project-settings" => array(
					"key" => self::KEY_LANGUAGE_PROJECT, 
					//= Language file: Language file to use for this module in this project (leave blank for system setting to apply)
					"name" => self::tt("em_config_5"), 
					"type" => "dropdown",
					"choices" => $choices
				)
			);
			// Check reserved keys.
			$systemSettings = $config['system-settings'];
			$projectSettings = $config['project-settings'];

			$existingSettingKeys = array();
			foreach($systemSettings as $details){
				$existingSettingKeys[$details['key']] = true;
			}
			foreach($projectSettings as $details){
				$existingSettingKeys[$details['key']] = true;
			}
			foreach (array_keys($templates) as $type) {
				$key = $templates[$type]['key'];
				if(isset($existingSettingKeys[$key])){
					//= The '{0}' setting key is reserved for internal use.  Please use a different setting key in your module.
					throw new Exception(self::tt("em_errors_6", $key)); 
				}
				// Merge arrays so that the language setting always end up at the top of the list.
				$config[$type] = array_merge(array($templates[$type]), $config[$type]);
			}
		}
		return $config;
	}

	/**
	 * Replaces placeholders in a language string with the supplied values.
	 * 
	 * @param string $string The template string.
	 * @param array $values The values to be used for interpolation. 
	 * @param bool $escapeHTML Determines whether to escape HTML in interpolation values.
	 * 
	 * @return string The result of the string interpolation.
	 */
	public static function interpolateLanguageString($string, $values, $escapeHTML = true) {

		if (count($values) == 0) return $string;

		// Placeholders are in curly braces, e.g. {0}. Optionally, a type hint can be present after a colon (e.g. {0:Date}), 
		// which is ignored however. Hints must not contain any curly braces.
		// To not replace a placeholder, the first curly can be escaped with a %-sign like so: '%{1}' (this will leave '{1}' in the text).
		// To include '%' as a literal before a curly opening brace, a double-% ('%%') must be used, i.e. '%%{0}' with value x this will result in '%x'.
		// Placeholder names can be strings (a-Z0-9_), too (need associative array then). 
		// First, parse the string.
		$matches = array();
		$mode = "scan";
		$escapes = 0;
		$start = 0;
		$key = "";
		$hint = "";
		for ($i = 0; $i < strlen($string); $i++) {
			$c = $string[$i] ?? null;
			if ($mode == "scan" && $c == "{") {
				$start = $i;
				$key = "";
				$hint = "";
				if ($escapes % 2 == 0) {
					$mode = "key";
				}
				else {
					$mode = "store";
				}
			}
			if ($mode == "scan" && $c == "%") {
				$escapes++;
			}
			else if ($mode == "scan") {
				$escapes = 0;
			}
			if ($mode == "hint") {
				if ($c == "}") {
					$mode = "store";
				}
				else {
					$hint .= $c;
				}
			}
			if ($mode == "key") {
				if (strpos(self::LANGUAGE_ALLOWED_KEY_CHARS, $c)) {
					$key .= $c;
				}
				else if ($c == ":") {
					$mode = "hint";
				}
				else if ($c == "}") {
					$mode = "store";
				}
			}
			if ($mode == "store") {
				$match = array(
					"key" => $key,
					"hint" => $hint,
					"escapes" => $escapes,
					"start" => $start,
					"end" => $i
				);
				$matches[] = $match;
				$key = "";
				$hint = "";
				$escapes = 0;
				$mode = "scan";
			}
		}
		// Then, build the result.
		$result = "";
		if (count($matches) == 0) {
			$result = $string;
		} else {
			$prevEnd = 0;
			for ($i = 0; $i < count($matches); $i++) {
				$match = $matches[$i];
				$len = $match["start"] - $prevEnd - ($match["escapes"] > 0 ? max(1, $match["escapes"] - 1) : 0);
				$result .= substr($string, $prevEnd, $len);
				$prevEnd = $match["end"];
				if ($match["key"] != "" && array_key_exists($match["key"], $values)) {
					$result .= $escapeHTML ? htmlentities($values[$match["key"]] ?? '', ENT_QUOTES) : $values[$match["key"]];
					$prevEnd++;
				}
			}
			$result .= substr($string, $prevEnd);
		}
		return $result;
	}

	/**
	 * Applies translations to a config file.
	 * 
	 * @param Array $config The configuration to translate.
	 * @param string $prefix The unique module prefix.
	 * @return Array The configuration with translations.
	 */
	public static function translateConfig(&$config, $prefix) {
		// Recursively loop through all.
		foreach ($config as $key => $val) {
			if (is_array($val) && !in_array($key, self::$CONFIG_NONTRANSLATABLE_SECTIONS, true)) {
				$config[$key] = self::translateConfig($val, $prefix);
			}
			else if (in_array($key, self::$CONFIG_TRANSLATABLE_KEYS, true)) {
				$tt_key = self::CONFIG_TRANSLATABLE_PREFIX.$key;
				if (isset($config[$tt_key])) {
					// Set the language key (in case of actual 'true', use the present value as key).
					$lang_key = ($config[$tt_key] === true) ? $val : $config[$tt_key];
					// Scope it for the module.
					$lang_key = self::constructLanguageKey($prefix, $lang_key);
					// Get the translated value.
					$config[$key] = self::tt($lang_key);
				}
			}
		}
		return $config;
	}

	//endregion

	/**
	 * Removes configuration settings that have 'hidden = true'.
	 */
	static function applyHidden($config) {
		foreach(["system-settings", "project-settings"] as $key){
			if(!isset($config[$key])){
				continue;
			}

			static::walkSubSettings($config[$key], function(&$setting){
				if (($setting["hidden"] ?? null) === true){
					return null;
				}

				return $setting;
			});
		}
		
		return $config;
	}

	public static function isSuperUser()
	{
		if (self::$SUPER_USER !== null) {
			// We're unit testing & the value is being spoofed.
			return self::$SUPER_USER;
		} else if (\UserRights::isImpersonatingUser()){
			$impersonatedUserInfo = ExternalModules::getUserInfo(ExternalModules::getUsername());
			return $impersonatedUserInfo['super_user'] === 1;
		} else {
			return defined("SUPER_USER") && SUPER_USER == 1;
		}
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	public static function setSuperUser($value)
	{
		if (!self::isTesting()) {
			throw new Exception("This method can only be used in unit tests.");
		}

		self::$SUPER_USER = $value;
	}

	# controls which module is currently being manipulated
	/**
	 * @return void
	 *
	 * @param null|string $prefix
	 */
	private static function setActiveModulePrefix($prefix)
	{
		self::$activeModulePrefix = $prefix;
	}

	# returns which module is currently being manipulated
	private static function getActiveModulePrefix()
	{
		return self::$activeModulePrefix;
	}

	/**
	 * @return string
	 */
	private static function lastTwoNodes($hostname) {
		$nodes = preg_split("/\./", $hostname);
		$count = count($nodes);
		if ($count >= 2) {
			return $nodes[$count - 2].".".$nodes[$count - 1];
		} else if ($count == 1) {
			return $nodes[$count - 1];
		}
		return "";
	}

	/**
	 * @return bool
	 */
	private static function isVanderbilt()
	{
		// We don't use REDCap's isVanderbilt() function any more because it is
		// based on $_SERVER['SERVER_NAME'], which is not set during cron jobs.
		return (strpos(self::$SERVER_NAME, "vanderbilt.edu") !== false || strpos(self::$SERVER_NAME, "vumc.org") !== false);
	}

	/**
	 * @param string $from
	 * @param (mixed|string)[] $to
	 * @param string $subject
	 * @param string $message
	 * @param string $fromName
	 */
	static function sendBasicEmail($from,$to,$subject,$message,$fromName='') {
        $email = new \Message();
        $email->setFrom($from);
		$email->setFromName($fromName);
        $email->setTo(implode(',', $to));
        $email->setSubject($subject);

        $message = str_replace("\n", "<br>", $message);
        $email->setBody($message, true);

        return $email->send();
    }

	/**
	 * @return string
	 * 
	 * @param string $name
	 */
	private static function getDatacoreDevEmail(string $name): string {
		$returnEmail = '';
		if(!empty($name)) {
			foreach(static::DATACORE_DEV_EMAIL_LIST AS $preferredName => $devDetails) {
				$nameMatches = array_merge([$preferredName], $devDetails['alternate_names']);
				$nameMatches = array_map('strtolower', $nameMatches);
				$name = strtolower($name);
				if(in_array($name, $nameMatches)) {
					$returnEmail = $devDetails['email_address'];
					break;
				}
			}
		}
		return $returnEmail;
	}

	/**
	 * @return \Message
	 *
	 * @param string $subject
	 * @param string $message
	 */
	private static function getAdminEmailMessage($subject, $message, $prefix)
	{
		global $project_contact_email;
		
		if (self::isVanderbilt()) {
			$from = static::DATACORE_EMAIL;
			$to = self::getDatacoreEmails();
		}
		else{
			$from = $project_contact_email;
			$to = [$project_contact_email];
		}

		if ($prefix) {
			try {
				$config = self::getConfig($prefix); // Admins will get the default (English) names of modules (i.e. not translated).
				$authors = $config['authors'] ?? [];
				foreach ($authors as $author) {
					if (isset($author['email']) && preg_match("/@/", $author['email'])) {
						if(self::isVanderbilt()) {
							if($author['email'] == static::DATACORE_EMAIL || $author['email'] == 'datacore@vanderbilt.edu') {
								$datacoreDevEmail = self::getDatacoreDevEmail($author['name']);
								if(!empty($datacoreDevEmail)) {
									$to[] = $datacoreDevEmail;
								}
							}
						} else {
							$parts = preg_split("/@/", $author['email']);
							if (count($parts) >= 2) {
								$domain = $parts[1];
								$authorEmail = $author['email'];

								if (self::lastTwoNodes(self::$SERVER_NAME) == $domain) {
									$to[] = $authorEmail;
								}
							}
						}
					}
				}
			} catch (Throwable $e) {
				// The problem is likely due to loading the configuration.  Ignore this Exception.
			}
		}

		$email = new \Message();
		$email->setFrom($from);
		$email->setTo(implode(',', $to));
		$email->setSubject($subject);

		$message = str_replace("\n", "<br>", $message);
		$email->setBody($message, true);

		return $email;
	}

	/**
	 * @return void
	 *
	 * @param string $subject
	 * @param string $message
	 */
	public static function sendAdminEmail($subject, $message, $prefix = null)
	{
		$email = self::getAdminEmailMessage($subject, $message, $prefix);
		$email->send();
	}

	# there are two situations which external modules are displayed
	# under a project or under the control center

	# this gets the project header
	/**
	 * @return string
	 */
	static function getProjectHeaderPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
	}

	/**
	 * @return string
	 */
	static function getProjectFooterPath()
	{
		return APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	}

	# disables a module system-wide
	/**
	 * @return void
	 *
	 * @param bool $dueToException
	 * @param string $moduleDirectoryPrefix
	 */
	static function disable($moduleDirectoryPrefix, $dueToException)
	{
		$version = self::getEnabledVersion($moduleDirectoryPrefix);

		// When a module is disabled due to certain exceptions (like invalid config.json syntax),
		// calling the disable hook would cause an infinite loop.
		if (!$dueToException) {
			self::callHook('redcap_module_system_disable', array($version), $moduleDirectoryPrefix);
		}

		// Disable any cron jobs in the crons table
		self::removeCronJobs($moduleDirectoryPrefix);
		
		// This flag allows the version system setting to be removed if the current user is not a superuser.
		// Without it, a secondary exception would occur saying that the user doesn't have access to remove this setting.
		self::$disablingModuleDueToException = $dueToException;
		self::removeSystemSetting($moduleDirectoryPrefix, self::KEY_VERSION);
		self::$disablingModuleDueToException = false;
	}

	/**
	 * @return Throwable|null
	 */
	static function enableAndCatchExceptions($moduleDirectoryPrefix, $version)
	{
		try {
			$frameworkInstance = self::getFrameworkInstance($moduleDirectoryPrefix, $version);
			if($frameworkInstance === null){
				//= This module's main class does not extend AbstractExternalModule!
				throw new Exception(self::tt("em_errors_7")); 
			}
			else{
				/**
				 * We can safely assume that the module class extends ExternalModules\AbstractExternalModule,
				 * since framework instances are created inside AbstractExternalModule::__construct().
				 */
			}

			# Attempt to create an instance of the module before enabling it system wide.
			# This should catch problems like syntax errors in module code.
			$frameworkInstance->getModuleInstance();
			
			// Ensure compatibility with PHP version and REDCap version before instantiating the module class
			self::isCompatibleWithREDCapPHP($moduleDirectoryPrefix, $version);

			$config = ExternalModules::getConfig($moduleDirectoryPrefix, $version);
			$enabledPrefix = self::getEnabledPrefixForNamespace($config['namespace']);
			if(!empty($enabledPrefix)){
				//= This module cannot be enabled because a different version of the module is already enabled under the following prefix: {0}
				throw new Exception(self::tt("em_errors_8", $enabledPrefix)); 
			}

			self::setSystemSetting($moduleDirectoryPrefix, self::KEY_VERSION, $version);
			self::cacheAllEnableData();
			self::initializeSettingDefaults($frameworkInstance);

			self::callHook('redcap_module_system_enable', array($version), $moduleDirectoryPrefix);

			self::initializeCronJobs($frameworkInstance, $moduleDirectoryPrefix);
			static::removeModuleFromREDCapRepoUpdatesInConfig($moduleDirectoryPrefix, $version);
		} catch (Throwable $e) {
			self::disable($moduleDirectoryPrefix, true); // Disable the module in case the exception occurred after it was enabled in the DB.
			self::setActiveModulePrefix(null); // Unset the active module prefix, so an error email is not sent out.
			return $e;
		}

		return null;
	}

	/**
	 * @return void
	 *
	 * @param numeric-string $project_id
	 */
	static function enableForProject($moduleDirectoryPrefix, $version, $project_id)
	{
		$frameworkInstance = self::getFrameworkInstance($moduleDirectoryPrefix, $version);
		self::initializeSettingDefaults($frameworkInstance, $project_id);
		self::setProjectSetting($moduleDirectoryPrefix, $project_id, self::KEY_ENABLED, true);
		self::cacheAllEnableData();
		self::callHook('redcap_module_project_enable', array($version, $project_id), $moduleDirectoryPrefix);
	}

	private static function getEnabledPrefixForNamespace($namespace){
		$versionsByPrefix = ExternalModules::getEnabledModules();
		foreach ($versionsByPrefix as $prefix => $version) {
			$config = ExternalModules::getConfig($prefix, $version);
			if($config['namespace'] === $namespace){
				return $prefix;
			}
		}

		return null;
	}

	# initializes any crons contained in the config, and adds them to the redcap_crons table
	# timed crons are read from the config, so they are not entered into any table
	/**
	 * @return void
	 */
	static function initializeCronJobs($frameworkInstance, $moduleDirectoryPrefix=null)
	{
		// First, try and remove any crons that exist for this module (just in case)
		self::removeCronJobs($moduleDirectoryPrefix);
		// Parse config to get cron info
		$config = $frameworkInstance->getConfig();
		if (!isset($config['crons'])) return;

		$moduleInstance = $frameworkInstance->getModuleInstance();

		// Loop through all defined crons
		foreach ($config['crons'] as $cron) 
		{
			// Make sure we have what we need
			self::validateCronAttributes($cron, $moduleInstance);
			// Add the cron
			self::addCronJobToTable($cron, $moduleInstance);
		}
	}

	# adds module cron jobs to the redcap_crons table
	static function addCronJobToTable($cron=array(), $moduleInstance=null)
	{
		// Get external module ID
		$externalModuleId = self::getIdForPrefix($moduleInstance->PREFIX);
		if (empty($externalModuleId) || empty($moduleInstance)) return;

		if (self::isValidTabledCron($cron)) {
			// Add to table
			$sql = "insert into redcap_crons (cron_name, external_module_id, cron_description, cron_frequency, cron_max_run_time) values (?, ?, ?, ?, ?)";
			try{
				ExternalModules::query($sql, [$cron['cron_name'], $externalModuleId, $cron['cron_description'], $cron['cron_frequency'], $cron['cron_max_run_time']]);
			}
			catch(Throwable $e){
				// If fails on one cron, then delete any added so far for this module
				self::removeCronJobs($moduleInstance->PREFIX);
				// Return error
				//= One or more cron jobs for this module failed to be created.
				self::errorLog(self::tt("em_errors_9"));

				throw $e;
			}
		}
	}

	# validate module config's cron jobs' attributes. pass in the $cron job as an array of attributes.
	/**
	 * @return void
	 */
	static function validateCronAttributes(&$cron=array(), $moduleInstance=null)
	{
		$isValidTabledCron = self::isValidTabledCron($cron);
		$isValidTimedCron = self::isValidTimedCron($cron);

		// Ensure certain attributes are integers
		if ($isValidTabledCron) {
			$cron['cron_frequency'] = (int)$cron['cron_frequency'];
			$cron['cron_max_run_time'] = (int)$cron['cron_max_run_time'];
		} else if ($isValidTimedCron) {
			$cron['cron_minute'] = (int) $cron['cron_minute'];
			if (isset($cron['cron_hour'])) {
				$cron['cron_hour'] = (int) $cron['cron_hour'];
			}
			if (isset($cron['cron_weekday'])) {
				$cron['cron_weekday'] = (int) $cron['cron_weekday'];
			}
			if (isset($cron['cron_monthday'])) {
				$cron['cron_monthday'] = (int) $cron['cron_monthday'];
			}
		}
		// Make sure we have what we need
		if (!isset($cron['cron_name']) || empty($cron['cron_name']) || !isset($cron['cron_description']) || !isset($cron['method'])) {
			//= Some cron job attributes in the module's config file are not correct or are missing.
			throw new Exception(self::tt("em_errors_10")); 
		}
		if ((!isset($cron['cron_frequency']) || !isset($cron['cron_max_run_time'])) && (!isset($cron['cron_hour']) && !isset($cron['cron_minute']))) {
			//= Some cron job attributes in the module's config file are not correct or are missing (cron_frequency/cron_max_run_time or hour/minute)."
			throw new Exception(self::tt("em_errors_102")); 
		}

		// Name must be no more than 100 characters
		if (strlen($cron['cron_name']) > 100) {
			//= Cron job 'name' must be no more than 100 characters.
			throw new Exception(self::tt("em_errors_11")); 
		}
		// Name must be alphanumeric with dashes or underscores (no spaces, dots, or special characters)
		if (!preg_match("/^([a-z0-9_-]+)$/", $cron['cron_name'])) {
			//= Cron job 'name' can only have lower-case letters, numbers, and underscores (i.e., no spaces, dashes, dots, or special characters).
			throw new Exception(self::tt("em_errors_12")); 
		}

		// Make sure integer attributes are integers
		if ($isValidTabledCron && $isValidTimedCron) { 
			//= Cron job attributes 'cron_frequency' and 'cron_max_run_time' cannot be set with 'cron_hour' and 'cron_minute'. Please choose one timing setting or the other, but not both.
			throw new Exception(self::tt("em_errors_13")); 
		}
		if (!$isValidTabledCron && !$isValidTimedCron) {
			//= Cron job attributes 'cron_frequency' and 'cron_max_run_time' must be numeric and greater than zero --OR-- attributes 'cron_hour' and 'cron_minute' must be numeric and valid.
			throw new Exception(self::tt("em_errors_99")); 
		}

		// If method does not exist, then disable module
		if (!empty($moduleInstance) && !method_exists($moduleInstance, $cron['method'])) {
			//= The external module '{0}_{1}' has a cron job named '{2}' that is trying to call a method '{3}', which does not exist in the module class.
			throw new Exception(self::tt("em_errors_14", 
				$moduleInstance->PREFIX, 
				$moduleInstance->VERSION, 
				$cron['cron_name'], 
				$cron['method'])); 
		}
	}

	# remove all crons for a given module
	/**
	 * @param null|string $moduleDirectoryPrefix
	 */
	static function removeCronJobs($moduleDirectoryPrefix=null)
	{
		if (empty($moduleDirectoryPrefix)) return false;
		// If a module directory has been deleted, then we have to use this alternative way to remove its crons			
		$externalModuleId = self::getIdForPrefix($moduleDirectoryPrefix);
		// Remove crons from db table
		$sql = "delete from redcap_crons where external_module_id = ?";
		return ExternalModules::query($sql, [$externalModuleId]);
	}

	# validate EVERY module config's cron jobs' attributes. fix them in the redcap_crons table if incorrect/out-of-date.
	# This method is currently called from REDCap core.
	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return string[]
	 */
	static function validateAllModuleCronJobs()
	{
		// Set array of modules that got fixed
		$fixedModules = array();
		// Get all enabled modules
		$enabledModules = self::getEnabledModules();
		// Cron items to check in db table
		$cronAttrCheck = array('cron_frequency', 'cron_max_run_time', 'cron_description');
		// Parse each enabled module's config, and see if any have cron jobs
		foreach ($enabledModules as $moduleDirectoryPrefix=>$version) {
			try {
				// First, make sure the module directory exists. If not, then disable the module.
				$modulePath = self::getModuleDirectoryPath($moduleDirectoryPrefix, $version);
				if (!$modulePath) {
					// Delete the cron jobs to prevent issues
					self::removeCronJobs($moduleDirectoryPrefix);
					// Continue with next module
					continue;
				}
				// Parse the module config to get the cron info
				$frameworkInstance = self::getFrameworkInstance($moduleDirectoryPrefix, $version);
				$config = $frameworkInstance->getConfig();
				if (!isset($config['crons'])) continue;
				
				$moduleInstance = $frameworkInstance->getModuleInstance();

				// Get external module ID
				$externalModuleId = self::getIdForPrefix($moduleInstance->PREFIX);
				// Validate each cron attributes
				foreach ($config['crons'] as $cron) {
					// Validate the cron's attributes
					self::validateCronAttributes($cron, $moduleInstance);
					if (self::isValidTabledCron($cron)) {
						// Ensure the cron job's info in the db table are all correct
						$cronInfoTable = self::getCronJobFromTable($cron['cron_name'], $externalModuleId);
						if (empty($cronInfoTable)) {
							// If this cron is somehow missing, then add it to the redcap_crons table
							self::addCronJobToTable($cron, $moduleInstance);
						}
						// If any info is different, then correct it in table
						foreach ($cronAttrCheck as $attr) {
							if ($cron[$attr] != $cronInfoTable[$attr]) {
								// Fix the cron
								if (self::updateCronJobInTable($cron, $externalModuleId)) {
									$fixedModules[] = "\"$moduleDirectoryPrefix\"";
								}
								// Go to next cron
								continue;
							}
						}
					}
				}
			} catch (Throwable $e){
				// Disable the module and send email to admin
				self::disable($moduleDirectoryPrefix, true);
				//= The '{0}' module was automatically disabled because of the following error:
				$message = self::tt("em_errors_15", $moduleDirectoryPrefix) . "\n\n$e"; 
				self::errorLog($message);
				self::handleError(
					//= REDCap External Module Automatically Disabled - {0}
					self::tt("em_errors_16", $moduleDirectoryPrefix), 
					$message, $moduleDirectoryPrefix);
			}
		}
		// Return array of fixed modules
		return array_unique($fixedModules);
	}

	# obtain the info of a cron job for a module in the redcap_crons table
	static function getCronJobFromTable($cron_name, $externalModuleId)
	{
		$sql = "select
					cron_name,
					cron_description,
					cast(cron_frequency as char) as cron_frequency,
					cast(cron_max_run_time as char) as cron_max_run_time
				from redcap_crons
				where cron_name = ? and external_module_id = ?";
		$q = ExternalModules::query($sql, [$cron_name, $externalModuleId]);
		return ($q->num_rows > 0) ? $q->fetch_assoc() : array();
	}

	# prerequisite: is a valid tabled cron
	# obtain the info of a cron job for a module in the redcap_crons table
	static function updateCronJobInTable($cron, $externalModuleId)
	{
		if (empty($cron) || empty($externalModuleId)) return false;
		$sql = "update redcap_crons set cron_frequency = ?, cron_max_run_time = ?, 
				cron_description = ?
				where cron_name = ? and external_module_id = ?";
		return ExternalModules::query($sql, [
			$cron['cron_frequency'],
			$cron['cron_max_run_time'],
			$cron['cron_description'],
			$cron['cron_name'],
			$externalModuleId
		]);
	}

	# initializes the system settings
	/**
	 * @return void
	 *
	 * @param null|numeric-string $pid
	 */
	static function initializeSettingDefaults($frameworkInstance, $pid=null)
	{
		$config = $frameworkInstance->getConfig();
		$settings = empty($pid) ? $config['system-settings'] : $config['project-settings'];
		foreach($settings as $details){
			$key = $details['key'];
			$default = $details['default'] ?? null;
			$existingValue = empty($pid) ? $frameworkInstance->getSystemSetting($key) : $frameworkInstance->getProjectSetting($key, $pid);
			if(isset($default) && $existingValue == null){
				if (empty($pid)) {
					$frameworkInstance->setSystemSetting($key, $default);
				} else {
					$frameworkInstance->setProjectSetting($key, $default, $pid);
				}
			}
		}
	}

	/**
	 * @param string $key
	 * @param string $moduleDirectoryPrefix
	 */
	static function getSystemSetting($moduleDirectoryPrefix, $key)
	{
		return self::getSetting($moduleDirectoryPrefix, self::SYSTEM_SETTING_PROJECT_ID, $key);
	}

	// static function getSystemSettings($moduleDirectoryPrefixes, $keys = null)
	// {
	// 	return self::getSettings($moduleDirectoryPrefixes, self::SYSTEM_SETTING_PROJECT_ID, $keys);
	// }

	/**
	 * @return void
	 *
	 * @param string $key
	 * @param (false|int|mixed|string)[]|int $value
	 * @param string $moduleDirectoryPrefix
	 */
	static function setSystemSetting($moduleDirectoryPrefix, $key, $value)
	{
		self::setProjectSetting($moduleDirectoryPrefix, self::SYSTEM_SETTING_PROJECT_ID, $key, $value);
	}

	/**
	 * @return void
	 *
	 * @param string $key
	 * @param string $moduleDirectoryPrefix
	 */
	static function removeSystemSetting($moduleDirectoryPrefix, $key)
	{
		self::removeProjectSetting($moduleDirectoryPrefix, self::SYSTEM_SETTING_PROJECT_ID, $key);
	}

	/**
	 * @return void
	 *
	 * @param string $key
	 * @param (mixed|string)[]|bool|null|string $value
	 * @param null|string $projectId
	 * @param string $moduleDirectoryPrefix
	 */
	static function setProjectSetting($moduleDirectoryPrefix, $projectId, $key, $value)
	{
		self::setSetting($moduleDirectoryPrefix, $projectId, $key, $value);
	}

	# value is edoc ID
	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	static function setSystemFileSetting($moduleDirectoryPrefix, $key, $value)
	{
		self::setFileSetting($moduleDirectoryPrefix, self::SYSTEM_SETTING_PROJECT_ID, $key, $value);
	}

	# value is edoc ID
	/**
	 * @return void
	 *
	 * @param null|string $projectId
	 * @param (int|string) $key
	 */
	static function setFileSetting($moduleDirectoryPrefix, $projectId, $key, $value)
	{
		// The string type parameter is only needed because of some incorrect handling on the js side that needs to be refactored.
		self::setSetting($moduleDirectoryPrefix, $projectId, $key, $value, 'string');
	}

	/**
	 * @return bool
	 *
	 * @param (int|string) $key
	 */
	static function isReservedSettingKey($key)
	{
		foreach(self::getReservedSettings() as $setting){
			if($setting['key'] == $key){
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $moduleDirectoryPrefix
	 * @param (int|string) $key
	 */
	private static function areSettingPermissionsUserBased($moduleDirectoryPrefix, $key)
	{
		if(self::isReservedSettingKey($key)){
			// Require user based setting permissions for reserved keys.
			// We don't want modules to be able to override permissions for enabling/disabling/updating modules.
			return true;
		}

		$hookRunner = self::getCurrentHookRunner();
		if ($hookRunner) {
			// We're inside a hook.  Disable user based setting permissions, leaving control up to the module author.
			// There are many cases where modules might want to use settings to track state based on the actions
			// of survey respondents or users without design rights.
			return false;
		}

		// The following might be removed in the future (since disableUserBasedSettingPermissions() has been deprecated).
		// If that happens, we should make sure to return true here to cover calls within the framework (like setting project settings via the settings dialog).
		$framework = self::getFrameworkInstance($moduleDirectoryPrefix);
		return $framework->areSettingPermissionsUserBased();
	}

	/**
	 * @return string
	 *
	 * @param null|string $projectId
	 */
	public static function getLockName($moduleId, $projectId)
	{
		return "external-module-setting-$moduleId-$projectId";
	}

	/**
	 * @return void
	 *
	 * @param (mixed|null|string)[]|false|string $valueJson
	 * @param string $prefix
	 * @param (int|string) $key
	 */
	private static function checkProjectIdSettingPermissions($prefix, $key, $valueJson, $oldValueJson)
	{
		$settingDetails = self::getSettingDetails($prefix, $key);
		if(($settingDetails['type'] ?? null) !== 'project-id'){
			return;
		}

		$value = json_decode($valueJson ?? '', true);
		$oldValue = json_decode($oldValueJson ?? '', true);

		static::checkProjectIdSettingPermissionsValue($key, $value, $oldValue);
	}

	private static function checkProjectIdSettingPermissionsValue($key, $value, $oldValue)
	{
		if(is_array($value)){
			for($i=0; $i<count($value); $i++){
				$subValue = $value[$i];
				$oldSubValue = $oldValue[$i] ?? null;

				static::checkProjectIdSettingPermissionsValue($key, $subValue, $oldSubValue);
			}
		}
		else if ($value != $oldValue && !self::hasDesignRights($value)){
			throw new Exception(self::tt('em_errors_129', $value, $key));
		}
	}

	# this is a helper method
	# call set [System,Project] Setting instead of calling this method
	/**
	 * @return Query|null
	 *
	 * @param string $type
	 * @param (mixed|null|string)[] $value
	 * @param (int|string) $key
	 * @param string $moduleDirectoryPrefix
	 * @param string $projectId
	 */
	private static function setSetting($moduleDirectoryPrefix, $projectId, $key, $value, $type = "")
	{
		if(empty($projectId)){
			/**
			 * This exception should only ever be shown if there is 
			 * a mistake during development of the framework itself.
			 * It is intended to prevent future issues like:
			 * https://redcap.vumc.org/community/post.php?id=222792
			 */
			// Disabled while we think through alternate options.  This caused: https://redcap.vumc.org/community/post.php?id=227325
			// throw new \Exception('Project ID must be specified.  For system settings, use SYSTEM_SETTING_PROJECT_ID.');
		}

		$testSettings =& static::getTestSettings($moduleDirectoryPrefix, $projectId);
		$rawValue = $value;

		$externalModuleId = self::getIdForPrefix($moduleDirectoryPrefix);
		$lockName = self::getLockName($externalModuleId, $projectId);

		/**
		 * Our mutual exclusion logic only works properly if the GET_LOCK
		 * and getSetting() queries are made against the primary DB connection.
		 * If we ever move to a multi-master setup, we will need to ensure that
		 * one of the masters is consistently considered the primary.
		 */
		static::$forceUsePrimaryDbConnection = true;
		
		// The natural solution to prevent duplicates would be a unique key.
		// That unfortunately doesn't work for the settings table since the total length of the appropriate key columns is longer than the maximum unique key length.
		// Instead, we use GET_LOCK() and check the existing value before inserting/updating to prevent duplicates.
		// This seems to work better than transactions since it has no risk of deadlock, and allows for limiting mutual exclusion to a per module and project basis (using the lock name).
		// Ideally the lock name would include the setting key as well, but lock names are limited to 64 chars.
		$result = self::query("SELECT GET_LOCK(?, ?)", [$lockName, 30]);
		$row = $result->fetch_row();
		
		if($row[0] !== 1){
			//= Lock acquisition timed out while setting a setting for module {0} and project {1}. This should not happen under normal circumstances. However, the following query may be used to manually release the lock if necessary: {2}
			throw new Exception(self::tt("em_errors_17", 
				$moduleDirectoryPrefix, 
				$projectId, 
				"SELECT RELEASE_LOCK('$lockName')")); 
		}

		try{
			$oldValue = self::getSetting($moduleDirectoryPrefix, $projectId, $key);
			
			$oldType = gettype($oldValue);
			if ($oldType == 'array' || $oldType == 'object') {
				$oldValue = json_encode($oldValue);
			}

			# if $value is an array or object, then encode as JSON
			# else store $value as type specified in gettype(...)
			if ($type === "") {
				$type = gettype($value);
			}

			if ($type == "array" || $type == "object") {
				// TODO: ideally we would also include a sql statement to update all existing type='json' module settings to json-array
				// to clean up existing entries using the non-specific 'json' format.
				$type = "json-$type";
				$value = json_encode($value);
			}

			// Triple equals includes type checking, and even order checking for complex nested arrays!
			if ($value === $oldValue) {
				// Nothing changed, so we don't need to do anything.
				return;
			}

			static::ensureSetSettingIsAllowed($moduleDirectoryPrefix, $projectId, $key, $value, $oldValue);
			
			if (!$projectId || $projectId == "" || strtoupper($projectId) === 'NULL') {
				$projectId = null;
			}
			else{
				// This used to be for preventing SQL injection, but that reason no longer makes sense now that we have prepared statements.
				// We left it in place for normalization purposes, and to prevent hook parameter injection (which could lead to other injection types).
				$projectId = self::requireInteger($projectId);
			}

			if ($type == "boolean") {
				$value = ($value) ? 'true' : 'false';
			}

			$query = ExternalModules::createQuery();
			
			if ($value === null) {
				if($testSettings !== null){
					unset($testSettings[$key]);
					return;
				}

				$query->add('
					DELETE FROM redcap_external_module_settings
					WHERE
						external_module_id = ?
						AND `key` = ?
				', [$externalModuleId, $key]);

				$query->add('AND')->addInClause('project_id', $projectId);
			} else {
				static::ensureSettingLengthLimits($moduleDirectoryPrefix, $key, $value);

				if($testSettings !== null){
					$testSettings[$key] = $rawValue;
					return;
				}

				if ($oldValue === null) {
					$query->add('
						INSERT INTO redcap_external_module_settings
							(
								`external_module_id`,
								`project_id`,
								`key`,
								`type`,
								`value`
							)
						VALUES
							(
								?,
								?,
								?,
								?,
								?
							)
					', [$externalModuleId, $projectId, $key, $type, $value]);
				} else {
					if ($key == self::KEY_ENABLED && $value == "false" && $projectId) {
						$version = self::getEnabledVersion($moduleDirectoryPrefix);
						self::callHook('redcap_module_project_disable', array($version, $projectId), $moduleDirectoryPrefix);
					}

					$query->add('
						UPDATE redcap_external_module_settings
						SET value = ?,
							type = ?
						WHERE
							external_module_id = ?
							AND `key` = ?
					', [$value, $type, $externalModuleId, $key]);

					$query->add('AND')->addInClause('project_id', $projectId);
				}
			}

			$query->execute();

			$affectedRows = $query->affected_rows;

			if ($affectedRows != 1) {
				$details = "\nQuery: " . $query->getSQL() . "\nParameters: " . json_encode($query->getParameters(), JSON_PRETTY_PRINT);

				//= Unexpected number of affected rows ({0}) on External Module setting query: {1}
				throw new Exception(self::tt("em_errors_23", $affectedRows, $details));
			}

			return $query;
		}
		catch(Throwable $e){
			throw $e;
		}
		finally{
			ExternalModules::query("SELECT RELEASE_LOCK(?)", $lockName);
			static::$forceUsePrimaryDbConnection = false;
		}
	}

	private static function ensureSetSettingIsAllowed($moduleDirectoryPrefix, $projectId, $key, $value, $oldValue)
	{
		// If module is being enabled for a project and users can activate this module on their own, then skip the user-based permissions check
		// (Not sure if this is the best insertion point for this check, but it works well enough.)
		$skipUserBasedPermissionsCheck = ($key == self::KEY_ENABLED && is_numeric($projectId)
			&& ExternalModules::getSystemSetting($moduleDirectoryPrefix, ExternalModules::KEY_USER_ACTIVATE_PERMISSION) == true && ExternalModules::hasDesignRights());

		if (
			!self::$disablingModuleDueToException && // This check is required to prevent an infinite loop in some cases.
			!$skipUserBasedPermissionsCheck &&
			self::areSettingPermissionsUserBased($moduleDirectoryPrefix, $key)
		) {
			// The code inside this "if" block might be dead because areSettingPermissionsUserBased() may always return false now.

			//= You may want to use the disableUserBasedSettingPermissions() method to disable this check and leave permissions up to the module's code.
			$errorMessageSuffix = self::tt("em_errors_18");

			if (empty($projectId)) {
				if (!defined("CRON") && !self::hasSystemSettingsSavePermission($moduleDirectoryPrefix)) {
					//= You don't have permission to save system settings! {0} 
					throw new Exception(self::tt("em_errors_19", $errorMessageSuffix)); 
				}
			}
			else if (!defined("CRON") && !self::hasProjectSettingSavePermission($moduleDirectoryPrefix, $key)) {
				//= You don't have permission to save project settings! {0}
				throw new Exception(self::tt("em_errors_20", $errorMessageSuffix)); 
			}

			self::checkProjectIdSettingPermissions($moduleDirectoryPrefix, $key, $value, $oldValue);
		}
	}

	private static function ensureSettingLengthLimits($moduleDirectoryPrefix, $key, $value){
		if (mb_strlen($key, '8bit') > self::SETTING_KEY_SIZE_LIMIT) {
			//= Cannot save the setting for prefix '{0}' and key '{1}' because the key is longer than the {2} byte limit.
			throw new Exception(self::tt("em_errors_21", 
				$moduleDirectoryPrefix, 
				$key, 
				self::SETTING_KEY_SIZE_LIMIT)); 
		}

		if (mb_strlen($value, '8bit') > self::SETTING_SIZE_LIMIT) {
			//= Cannot save the setting for prefix '{0}' and key '{1}' because the value is larger than the {2} byte limit.
			throw new Exception(self::tt("em_errors_22", 
				$moduleDirectoryPrefix, 
				$key, 
				self::SETTING_SIZE_LIMIT)); 
		}
	}

	/**
	 * @return array[]
	 */
	static function getSystemSettingsAsArray($moduleDirectoryPrefixes)
	{
		return self::getSettingsAsArray($moduleDirectoryPrefixes, self::SYSTEM_SETTING_PROJECT_ID);
	}

	/**
	 * @return array[]
	 *
	 * @param bool $includeSystemSettings
	 * @param numeric-string $projectId
	 */
	static function getProjectSettingsAsArray($moduleDirectoryPrefixes, $projectId, $includeSystemSettings = true)
	{
		if (!$projectId) {
			throw new Exception("The Project Id cannot be null!");
		}

		$projectIds = [$projectId];

		if($includeSystemSettings){
			$projectIds[] = self::SYSTEM_SETTING_PROJECT_ID;
		}

		return self::getSettingsAsArray($moduleDirectoryPrefixes, $projectIds);
	}

	/**
	 * @return array[]
	 *
	 * @param (mixed|string)[]|string $projectIds
	 */
	private static function getSettingsAsArray($moduleDirectoryPrefixes, $projectIds, $keys = [])
	{
		if(empty($moduleDirectoryPrefixes)){
			//= One or more module prefixes must be specified!
			throw new Exception(self::tt("em_errors_24")); 
		}

		$result = self::getSettings($moduleDirectoryPrefixes, $projectIds, $keys);

		$settings = array();
		while($row = self::validateSettingsRow($result->fetch_assoc())){
			$key = $row['key'];
			$value = $row['value'];

			/** @psalm-suppress EmptyArrayAccess **/
			$setting =& $settings[$key];
			if(!isset($setting)){
				$setting = array();
				$settings[$key] =& $setting;
			}

			if($row['project_id'] === null){
				$setting['system_value'] = $value;

				if(!isset($setting['value'])){
					$setting['value'] = $value;
				}
			}
			else{
				$setting['value'] = $value;
			}
		}

		return $settings;
	}

	/**
	 * @return Query
	 */
	static function createQuery()
	{
		return new Query();
	}

	/**
	 * @return Query
	 *
	 * @param array|null $moduleDirectoryPrefixes
	 * @param array|null|string $projectIds
	 * @param string[] $keys
	 */
	static function getSettingsQuery($moduleDirectoryPrefixes, $projectIds, $keys = array())
	{
		$query = self::createQuery();
		$query->add("
			SELECT directory_prefix, s.project_id, s.key, s.value, s.type
			FROM redcap_external_modules m
			JOIN redcap_external_module_settings s
				ON m.external_module_id = s.external_module_id
			WHERE true
		");

		if (!empty($moduleDirectoryPrefixes)) {
			$query->add('and')->addInClause('m.directory_prefix', $moduleDirectoryPrefixes);
		}

		if($projectIds !== null){
			if(!is_array($projectIds)){
				if(empty($projectIds)){
					// This probabaly shouldn't be a valid use case, but it's easier to add the following line
					// than verify whether it's actually used anywhere.
					$projectIds = self::SYSTEM_SETTING_PROJECT_ID;
				}

				$projectIds = [$projectIds];
			}

			if (!empty($projectIds)) {
				foreach($projectIds as &$projectId){
					if($projectId === self::SYSTEM_SETTING_PROJECT_ID){
						$projectId = null;
					}
				}
	
				$query->add('and')->addInClause('s.project_id', $projectIds);
			}
		}

		if (!empty($keys)) {
			$query->add('and')->addInClause('s.key', $keys);
		}

		return $query;
	}

	static function &getTestSettings($prefix, $projectId): ?array
	{
		/** @psalm-suppress UnsupportedPropertyReferenceUsage **/
		$settings =& static::$TEST_SETTINGS[$prefix] ?? null;
		if($settings === null){
			return $settings;
		}

		$settings =& $settings[$projectId] ?? null;
		/** @psalm-suppress ParadoxicalCondition **/
		if($settings === null){
			$settings = [];
		}

		return $settings;
	}

	/**
	 * @param array|null $moduleDirectoryPrefixes
	 * @param array|null|string $projectIds
	 * @param string[] $keys
	 */
	static function getSettings($moduleDirectoryPrefixes, $projectIds, $keys = array())
	{
		$query = self::getSettingsQuery($moduleDirectoryPrefixes, $projectIds, $keys);
		return $query->execute();
	}

	/**
	 * @param string $prefix
	 */
	static function getEnabledProjects($prefix)
	{
		return self::query("SELECT s.project_id, p.app_title as name
							FROM redcap_external_modules m
							JOIN redcap_external_module_settings s
								ON m.external_module_id = s.external_module_id
							JOIN redcap_projects p
								ON s.project_id = p.project_id
							WHERE m.directory_prefix = ?
								and p.date_deleted IS NULL
								and `key` = ?
								and value = 'true'
							ORDER BY app_title", [$prefix, self::KEY_ENABLED]);
	}

	# row contains the data type in field 'type' and the value in field 'value'
	# this makes sure that the data returned in 'value' is of that correct type
	static function validateSettingsRow($row)
	{
		if ($row == null) {
			return null;
		}

		$type = $row['type'];
		$value = $row['value'];

		if ($type == 'file') {
			// This is a carry over from the old way edoc IDs were stored.  Convert it to the new way.
			// Really this should be 'integer', but it must be 'string' currently because of some incorrect handling on the js side that needs to be corrected.
			$type = 'string';
		}

		if ($type == "json" || $type == "json-array") {
			$json = json_decode($value,true);
			if ($json !== false) {
				$value = $json;
			}
		}
		else if ($type == "boolean") {
			if ($value === "true") {
				$value = true;
			} else if ($value === "false") {
				$value = false;
			}
		}
		else if ($type == "json-object") {
			$value = json_decode($value,false);
		}
		else if (!settype($value, $type)) {
			//= Unable to set the type of '{0}' to '{1}'! This should never happen, as it means unexpected/inconsistent values exist in the database.
			throw new Exception(self::tt("em_errors_25", $value, $type)); 
		}

		$row['value'] = $value;

		return $row;
	}

	/**
	 * @param string $projectId
	 * @param string $moduleDirectoryPrefix
	 * @param string $key
	 */
	private static function getSetting($moduleDirectoryPrefix, $projectId, $key)
	{
		if(empty($key)){
			//= The setting key cannot be empty!
			throw new Exception(self::tt("em_errors_26")); 
		}

		$testSettings = static::getTestSettings($moduleDirectoryPrefix, $projectId);
		if($testSettings !== null){
			return $testSettings[$key] ?? null;
		}

		$result = self::getSettings($moduleDirectoryPrefix, $projectId, $key);

		$numRows = $result->num_rows;
		if($numRows == 1) {
			$row = self::validateSettingsRow($result->fetch_assoc());

			return $row['value'];
		}
		else if($numRows == 0){
			return null;
		}
		else{
			//= More than one ({0}) External Module setting exists for prefix '{1}', project ID '{2}', and key '{3}'! This should never happen!
			throw new Exception(self::tt("em_errors_27", 
				$numRows, 
				$moduleDirectoryPrefix, 
				$projectId, 
				$key)); 
		}
	}

	/**
	 * @param string $key
	 * @param string $moduleDirectoryPrefix
	 * @param null|numeric-string $projectId
	 */
	static function getProjectSetting($moduleDirectoryPrefix, $projectId, $key)
	{
		if (!$projectId) {
			//= The Project Id cannot be null!
			throw new Exception(self::tt("em_errors_28")); 
		}

		$value = self::getSetting($moduleDirectoryPrefix, $projectId, $key);

		if($value === null){
			$value =  self::getSystemSetting($moduleDirectoryPrefix, $key);
		}

		return $value;
	}

	/**
	 * @return void
	 *
	 * @param null|string $projectId
	 * @param string $key
	 * @param string $moduleDirectoryPrefix
	 */
	static function removeProjectSetting($moduleDirectoryPrefix, $projectId, $key){
		self::setProjectSetting($moduleDirectoryPrefix, $projectId, $key, null);
	}

	# directory name is [institution]_[module]_v[X].[Y]
	# prefix is [institution]_[module]
	# gets stored in database as module_id number
	# translates prefix string into a module_id number
	/**
	 * @param string $prefix
	 */
	public static function getIdForPrefix($prefix, $createIdOnFailure = true)
	{
		if(!isset(self::$idsByPrefix)){
			$result = self::query("SELECT external_module_id, directory_prefix FROM redcap_external_modules", []);

			$idsByPrefix = array();
			while($row = $result->fetch_assoc()){
				$idsByPrefix[$row['directory_prefix']] = $row['external_module_id'];
			}

			self::$idsByPrefix = $idsByPrefix;
		}

		$id = self::$idsByPrefix[$prefix] ?? null;
		if($id == null){
			if(!$createIdOnFailure){
				throw new Exception(self::tt('em_errors_118', $prefix));
			}

			self::query("INSERT INTO redcap_external_modules (directory_prefix) VALUES (?)", [$prefix]);
			// Cast to a string for consistency, since the select query above returns all existing ids as strings.
			$id = (string) db_insert_id();
			self::$idsByPrefix[$prefix] = $id;
		}

		return $id;
	}

	# translates a module_id number into a prefix string
	public static function getPrefixForID($id){
		$result = self::query("SELECT directory_prefix FROM redcap_external_modules WHERE external_module_id = ?", [$id]);

		$row = $result->fetch_assoc();
		if($row){
			return static::escape($row['directory_prefix']);
		}

		return null;
	}
	
	# gets the currently installed module's version based on the module prefix string
	/**
	 * @param string $prefix
	 */
	public static function getModuleVersionByPrefix($prefix){
		$sql = "SELECT s.value FROM redcap_external_modules m, redcap_external_module_settings s 
				WHERE m.external_module_id = s.external_module_id AND m.directory_prefix = ?
				AND s.project_id IS NULL AND s.`key` = ? LIMIT 1";
		
		$result = self::query($sql, [$prefix, self::KEY_VERSION]);

		return $result->fetch_row()[0] ?? null;
	}

	/**
	 * @return (false|mixed|string)[]
	 *
	 * @param int $maxLen
	 */
	public static function truncateArrayValues($array, $maxLen){
		$newArray = [];
		foreach($array as $key=>$value){
			if(is_string($value)){
				$value = substr($value, 0, $maxLen);
			}
			
			$newArray[$key] = $value;
		}
		
		return $newArray;
	}

	/**
	 * @param string $sql
	 * @param array|int|null|string $parameters
	 * 
     * @psalm-taint-sink sql $sql
	 * 
	 * Parameterized queries will actually return a StatementResult object instead of mysqli_result,
	 * but we'll pretend that's not the case.  They have the same interface.
	 * Once PHP 8.2 is the minimum supported version for REDCap, we can remove the StatementResult object per this post:
	 * https://php.watch/versions/8.2/mysqli-libmysql-no-longer-supported
	 * @return \mysqli_result
	 */
	public static function query($sql, $parameters = null, $retriesLeft = 2)
	{
		if($sql instanceof Query){
			/**
			 * The framework itself doesn't use this feature anymore, but some modules pass
			 * a Query object back into this method (e.g. vanderbilt_v4rc_r4_study_module).
			 * We might as well keep supporting this for now.
			 */
			$query = $sql;
			$sql = $query->getSQL();
			$parameters = $query->getParameters();
		}

		if($parameters === null){
			throw new Exception(ExternalModules::tt('em_errors_117'));
		}

		$handleError = function($e) use ($sql, $parameters, $retriesLeft){
			$errorCode = db_errno();

			if(in_array($errorCode, [
				1205, // Lock wait timeout exceeded; try restarting transaction
				1213, // Deadlock found when trying to get lock; try restarting transaction
			])){
				if(
					$retriesLeft > 0
					&&
					!static::isTesting() // This line simply speeds up the unit test.
				){
					sleep(3);
					return self::query($sql, $parameters, $retriesLeft-1);
				}
				else{
					$message = self::tt('em_errors_175') . ' ' . json_encode([$sql, $parameters], JSON_PRETTY_PRINT);
	
					$prefix = self::getActiveModulePrefix();
					//= REDCap External Module Deadlocked Query
					self::handleError(self::tt('em_errors_107') . " - $prefix", $message, $prefix);
				}
			}
			else if(
				$errorCode === 2006
				&& !self::$shuttingDown
				&& !self::isCommandLine() // This was added to allow crons to be marked as stopped when they crash due to an Exception (so they can restart before cron_max_run_time)
			){
				// REDCap most likely detected a duplicate request and killed it in System::killConcurrentRequests().
				// Simply ignore this error and exit like REDCap does in db_query().
				// Unset the active module prefix so the shutdown function error handling does not trigger.
				self::setActiveModulePrefix(null);
				echo "A 'MySQL server has gone away' error was detected.  It is possible that there was an actual database issue, but it is more likely that REDCap detected this request as a duplicate and killed it.";
				exit;
			}

			$message = $e->getMessage();
			$dbError = db_error();

			// Log query details instead of showing them to the user to minimize risk of exploitation (it could appear on a public URL).
			//= An error occurred while running an External Module query
			self::errorLog(self::tt("em_errors_29") . json_encode([
				'Message' => $message,
				'SQL' => $sql,
				'Parameters' => static::truncateArrayValues($parameters, 1000),
				'DB Error' => $dbError,
				'DB Code' => $errorCode,
				'Exception Code' => $e->getCode(),
				'File' => $e->getFile(),
				'Line' => $e->getLine(),
				'Trace' => $e->getTrace()
			], JSON_PRETTY_PRINT|JSON_PARTIAL_OUTPUT_ON_ERROR));

			if(empty($dbError) && $e->getMessage() === ExternalModules::tt('em_errors_108')){
				/**
				 * This occurs on Vanderbilt's production server a few times a week with the save hook on the Email Alerts module.
				 * We put up with the error emails for the better part of a year, and tried to determine a cause several times,
				 * but were unable to pinpoint it.  The current theory is that there is some kind of duplicate request/query
				 * killing at the apache or mysql level that is closing the db connection in an unusual way, preventing an
				 * error message from coming through.
				 * 
				 * For now we exit instead of throwing an exception to prevent the error email triggered by the shutdown function.
				 */
				self::logStackTraceAndExit("A query failed with an empty error.  Please report this error to ".static::DATACORE_EMAIL." with instructions on how to reproduce it if possible.");
			}

			throw $e;
		};

		try{
			$result = db_query($sql, $parameters, null, MYSQLI_STORE_RESULT, static::$forceUsePrimaryDbConnection);
		
			if($result == FALSE){
				//= Query execution failed
				$result = $handleError(new Exception(self::tt('em_errors_108')));
			}
		}
		catch(Throwable $e){
			$message = $e->getMessage();
			$dbError = db_error();

			//= An error occurred while running an External Module query
			//= (see the server error log for more details).
			$message = self::tt("em_errors_29") . "'$message'. " . self::tt("em_errors_114") . "'$dbError'. " . self::tt("em_errors_30");
			$result = $handleError(new Exception($message, 0, $e));
		}

		return $result;
	}

	/**
	 * @return never
	 *
	 * @param string $message
	 */
	private static function logStackTraceAndExit($message)
	{
		echo "$message  See the server error log for details.\n";
		static::errorLog($message . "\nStack Trace: " . json_encode(debug_backtrace(), JSON_PRETTY_PRINT));

		// Unset the active module prefix so the shutdown function error handling does not trigger.
		self::setActiveModulePrefix(null);

		exit;
	}

	/**
	 * @return string
	 *
	 * @param int $chunkNumber
	 * @param int $totalChunkCount
	 */
	static function getChunkPrefix($chunkNumber, $totalChunkCount)
	{
		return "Chunked Log Part $chunkNumber of $totalChunkCount:\n";
	}

	/**
	 * @return void
	 *
	 * @param string $message
	 */
	static function errorLog($message)
	{
		// Chunk large messages, since syslog on most systems limits each entry to 1024 characters.
		// The actual limit is a little less due to the ellipsis, but we'll use an even lower number
		// to make room for our part prefixes.
		$parts = str_split($message, 1000);
		$partCount = count($parts);

		for($n=1; $n<=count($parts); $n++){
			$part = $parts[$n-1];

			if($partCount > 1){
				$part = self::getChunkPrefix($n, $partCount) . $part;
			}

			if(self::isTesting()){
				/**
				 * @psalm-taint-escape html
				 * @psalm-taint-escape has_quotes
				 */
				$part = $part;

				echo $part . "\n";
			}
			else{
				error_log($part);
			}
		}
	}

	# converts an IN array clause into SQL
	/**
	 * @param bool $preparedStatement
	 * @param string $columnName
	 * @param (int|mixed|string)[]|int|null $array
	 */
	public static function getSQLInClause($columnName, $array, $preparedStatement = false)
	{
		if(!is_array($array)){
			$array = array($array);
		}

		$getReturnValue = function($sql, $parameters = []) use ($preparedStatement){
			if($preparedStatement){
				return [$sql, $parameters];
			}
			else{
				return $sql;
			}
		};

		if(empty($array)){
			return $getReturnValue('(false)');
		}

		// Prepared statements don't really have anything to do with this null handling,
		// we just wanted to change it going forward and prepared statements were a good opportunity to do so.
		if($preparedStatement){
			$nullValue = null;
		}
		else{
			$nullValue = 'NULL';
		}

		$columnName = db_real_escape_string($columnName);

		$valueListSql = "";
		$nullSql = "";
		$parameters = [];

		foreach($array as $item){
			if($item === $nullValue){
				$nullSql = "$columnName IS NULL";
			}
			else{
				if(!empty($valueListSql)){
					$valueListSql .= ', ';
				}

				if($preparedStatement){
					$parameters[] = $item;
					$item = '?';
				}
				else{
					$item = db_real_escape_string($item);
					$item = "'$item'";
				}

				$valueListSql .= $item;
			}
		}

		$parts = array();

		if(!empty($valueListSql)){
			$parts[] = "$columnName IN ($valueListSql)";
		}

		if(!empty($nullSql)){
			$parts[] = $nullSql;
		}

		$sql = "(" . implode(" OR ", $parts) . ")";

		return $getReturnValue($sql, $parameters);
	}

	private static function isLoginPageDisplayed(){
		return static::getUsername() === null && !static::isNoAuth() && !(defined('API') && API);
	}

    /**
     * begins execution of hook
     * helper method
     * should call callHook
     *
     * @param $prefix
     * @param $version
     * @param $arguments
     * @return mixed|void|null  result from hook or null
     * @throws Exception
     */
    private static function startHook($prefix, $version, $arguments) {
		
		// Get the hook's root name
		$hookBeingExecuted = self::getCurrentHookRunner()->getName();
		$hookName = substr($hookBeingExecuted, 7);

		$recordId = null;
		if (in_array($hookName, ['data_entry_form_top', 'data_entry_form', 'save_record', 'survey_page_top', 'survey_page', 'survey_complete'])) {
			$recordId = $arguments[1];
		}

		$hookNames = array('redcap_'.$hookName, 'hook_'.$hookName);

		if(!self::hasPermission($prefix, $version, 'redcap_'.$hookName) && !self::hasPermission($prefix, $version, 'hook_'.$hookName)){
			// To prevent unnecessary class conflicts (especially with old plugins), we should avoid loading any module classes that don't actually use this hook.
			return;
		}

		$pid = self::getProjectIdForHook($hookName, $arguments);
		if(strpos($hookName, 'every_page') === 0){
			$config = self::getConfig($prefix, $version);
			if(
				(empty($pid) && ($config['enable-every-page-hooks-on-system-pages'] ?? null) !== true)
				||
				/**
				 * The following was added to avoid exceptions from $module->getUser() by preventing every page hooks
				 * from firing when the login page is displayed, unless this flag is set.
				 */
				(static::isLoginPageDisplayed() && ($config['enable-every-page-hooks-on-login-form'] ?? null) !== true)
			){
				return;
			}
		}

		/**
		 * The project ID is not passed to the email hook like other hooks,
		 * so we detect it like we do elsewhere in the framework.
		 */
		if(static::getProjectId() === null && $hookName === 'email'){
			// The email hook is running in a system (non-project) context (either page or cron).
			if((self::getConfig($prefix, $version)['enable-email-hook-in-system-contexts'] ?? null) !== true){
				return;
			}
		}
		
		$frameworkInstance = self::getFrameworkInstance($prefix, $version);
		$frameworkInstance->setRecordId($recordId);

		$instance = $frameworkInstance->getModuleInstance();

		$result = null; // Default result value

		foreach ($hookNames as $thisHook) {
			if(method_exists($instance, $thisHook)){
				if(starts_with($thisHook, 'hook_') && static::getFrameworkVersion($instance) >= static::PERMISSIONS_REMOVED_FRAMEWORK_VERSION){
					continue;
				}

				$previousActiveModulePrefix = self::getActiveModulePrefix();
				self::setActiveModulePrefix($prefix);

				/**
				 * This rollback is likely not nearly as important as the one after the hook call below.
				 * However, it might still help quickly find any odd edge cases from a transaction
				 * being left open in REDCap core or a legacy hook.
				 * This has no effect if a transaction has not been started.
				 */
				static::query('rollback', []);

				// Buffer output so we can access for killed query detection using register_shutdown_function().
				ob_start();

				try{
					$result = $instance->$thisHook(...$arguments);
				}
				catch(Throwable $e){
					//= The '{0}' module threw the following exception when calling the hook method '{1}':
					$message = self::tt("em_errors_32", 
						$prefix, 
						$thisHook); 
					$message .= "\n\n$e";
					self::errorLog($message);
					self::handleError(
						//= REDCap External Module Hook Exception - {0}
						self::tt("em_errors_33", $prefix), 
						$message, $prefix); 
				}

				echo ob_get_clean();

				/**
				 * Rollback in case this module left a transaction open.
				 * This has no effect if a transaction has not been started.
				 * If this is the last module hook, this rollback is still important
				 * to prevent issues in REDCap core.
				 */
				static::query('rollback', []);

				// Restore the previous prefix in case we're calling a hook from within a hook for a different module.
				// This is not handled inside the HookRunner like other variables because the active module prefix
				// is used outside the context of hooks in some cases.
				self::setActiveModulePrefix($previousActiveModulePrefix);
				continue; // No need to check for the alternate hook name.
			}
		}

		$frameworkInstance->setRecordId(null);

        return $result;
	}

	/**
	 * @return null|numeric
	 *
	 * @param (int|mixed|string)[] $arguments
	 */
	private static function getProjectIdForHook($hookName, $arguments)
	{
		if($hookName === 'email'){
			return static::getProjectId();
		}

		$pid = null;
		if(!empty($arguments)){
			$firstArg = $arguments[0];
			if (is_numeric($firstArg)) {
				// As of REDCap 6.16.8, the above checks allow us to safely assume the first arg is the pid for all hooks.
				$pid = $firstArg;
			}
		}

		return $pid;
	}

	/**
	 * @return bool
	 *
	 * @param HookRunner $newHookRunner
	 */
	private static function isHookCallAllowed($previousHookRunner, $newHookRunner)
	{
		if(!empty($previousHookRunner)){
			// A hook was called from within another hook.
			$hookBeingExecuted = $previousHookRunner->getName();
			$newHook = $newHookRunner->getName();

			$emailHook = 'redcap_email';
			if($newHook === $emailHook){
				if($hookBeingExecuted === $emailHook){
					// The email hooks is being called recursively.
					// We assume we're in an infinite loop and prevent additional module hooks from running to hopefully escape it.
					// This fixes an actual issue we encountered caused by an exception inside a module's redcap_email hook.
					// When that exception was caught and sendAdminEmail() was called, the module's redcap_email hook
					// was triggered again, causing an infinite loop, and preventing framework error emails from sending.

					return false;
				}
				else{
					// The email hook is currently allowed to fire inside other hooks.
				}
			}
		}
		else if(starts_with($_SERVER['REQUEST_URI'], APP_URL_EXTMOD_RELATIVE . 'manager/ajax/list-hooks.php')){
			/**
			 * Hooks are ignored on list-hooks.php requests to prevent issues when upgrading modules with every page hooks.
			 * This return prevents the old version of the module from being loaded instead of (or addition to) the new version.
			 * This avoids 'name is already in use' errors and ensures that hooks from the new version are returned.
			 */
			return false;
		}

		return true;
	}

	/**
	 * @psalm-suppress PossiblyUnusedReturnValue
	 * @return array|null
	 *
	 * @param string $name
	 * @param (int|mixed|string)[] $arguments
	 * @param null|string $prefix
	 */
	static function callHook($name, $arguments = [], $prefix = null)
	{
		if (isset($_GET[self::DISABLE_EXTERNAL_MODULE_HOOKS])){
			return;
		}

		# We must initialize this static class here, since this method actually gets called before anything else.
		# We can't initialize sooner than this because we have to wait for REDCap to initialize it's functions and variables we depend on.
		# This method is actually called many times (once per hook), so we should only initialize once.
		if (!self::$initialized) {
			self::initialize();
			self::$initialized = true;
		}

		/**
		 * We call this to make sure the initial caching is performed outside the try catch so that any framework exceptions get thrown
		 * and prevent the page from loading instead of getting caught and emailed.  These days the only time a framework exception
		 * typically gets thrown is when there is a database connectivity issue.  We don't want to flood the admin email in that case,
		 * since they are almost certainly aware of the issue already.
		 */
		self::getSystemwideEnabledVersions();

		# Hold results for hooks that return a value
		$resultsByPrefix = array();

		$name = str_replace('redcap_', '', $name);

		$previousHookRunner = self::getCurrentHookRunner();
		$hookRunner = new HookRunner("redcap_$name");
		self::setCurrentHookRunner($hookRunner);

		try {
			$GLOBALS['__currently_inside_hook'] = true; // Set flag to prevent initiating the building of the record list cache

			if(!defined('PAGE')){
				$page = ltrim($_SERVER['REQUEST_URI'], '/');
				define('PAGE', $page);
			}
	
			$templatePath = self::getSafePath("$name.php", APP_PATH_EXTMOD . "manager/templates/hooks/");
			if(file_exists($templatePath)){
				self::safeRequire($templatePath, $arguments);
			}
	
			$pid = self::getProjectIdForHook($name, $arguments);

			if(!self::isHookCallAllowed($previousHookRunner, $hookRunner)){
				return;
			}

			if($prefix){
				$versionsByPrefix = [$prefix => self::getEnabledVersion($prefix)];
			}
			else{
				$versionsByPrefix = self::getEnabledModules($pid);
			}

			$startHook = function($prefix, $version) use ($name, &$arguments, &$resultsByPrefix): void{
				$result = self::startHook($prefix, $version, $arguments);

				// The following check assumes hook return values will either be arrays or of type boolean/string/numeric.
				// The email hook returns boolean as return type.
				// The module_randomize_record hook returns a numeric result.
				if (is_bool($result) || is_string($result) || is_numeric($result) || (!empty($result) && is_array($result))) {
					if($name === 'pdf'){
						// Updated the arguments to pass the resulting metadata & data into the next hook call.
						$metadata = $result['metadata'] ?? null;
						if(is_array($metadata)){
							$arguments[1] = $metadata;
						}
						
						$data = $result['data'] ?? null;
						if(is_array($data)){
							$arguments[2] = $data;
						}

						// Overwrite the previous result, to avoid the multi-result warning.
						$index = 0;
					}
					else{
						$index = count($resultsByPrefix);
					}

					// Lets preserve order of execution by order entered into the results array
					$resultsByPrefix[$index] = array(
						"prefix" => $prefix,
						"result" => $result
					);
				}
			};

			foreach($versionsByPrefix as $prefix=>$version){
				$startHook($prefix, $version);
			}

			$callDelayedHooks = function($lastRun) use ($startHook, $hookRunner): void{
				$prevDelayed = $hookRunner->getDelayed();
				$hookRunner->clearDelayed();
				$hookRunner->setDelayedLastRun($lastRun);
				foreach ($prevDelayed as $prefix=>$version) {
					// Modules that call delayModuleExecution() normally just "return;" afterward, effectively returning null.
					// However, they could potentially return a value after delaying, which would result in multiple entries in $resultsByPrefix for the same module.
					// This could cause filterHookResults() to trigger unnecessary warning emails, but likely won't be an issue in practice.
					$startHook($prefix, $version);
				}
			};

			$getNumDelayed = function() use ($hookRunner): int{
				return count($hookRunner->getDelayed());
			};
	
			# runs delayed modules
			# terminates if queue is 0 or if it is the same as in the previous iteration
			# (i.e., no modules completing)
			$prevNumDelayed = count($versionsByPrefix) + 1;
			while (($prevNumDelayed > $getNumDelayed()) && ($getNumDelayed() > 0)) {
			 	$prevNumDelayed = $getNumDelayed();
				$callDelayedHooks(false);
			}

			$callDelayedHooks(true);
		} catch(Throwable $e) {
			// This try/catch originally existed to identify cases where the framework itself
			// was doing something unexpected.  Such cases are rare these days, but it
			// doesn't hurt to leave this try/catch in place indefinitely just in case.

			//= REDCap External Modules threw the following exception:
			$message = self::tt("em_errors_34") . "\n\n$e";
			self::errorLog($message);
			self::handleError(
				//= REDCap External Module Exception
				self::tt("em_errors_35"),
				$message, $prefix);
		}
		finally{
			/**
			 * Run the following in a "finally" block so that it still runs even if "return" 
			 * is called in the "try" above.
			 */

			// As this is currently written, any function that returns a value cannot also exit.
			// TODO: Should we move this to a shutdown function for this hook so we can return a value?
			if(
				$hookRunner->isExitAfterHook()
				&&
				self::getActiveModulePrefix() === null // Make sure we're at the top level hook call.  Calls to exitAfterHook() in nested hook calls are ignored.
			){
				if(self::isTesting()){
					$action = ExternalModules::$exitAfterHookAction;
					$action();
				}
				else{
					exit();
				}
			}
	
			self::setCurrentHookRunner($previousHookRunner);

			if($previousHookRunner === null){
				unset($GLOBALS['__currently_inside_hook']); // Unset flag now that the hook has been called
			}
			else{
				// We were inside a nested hook, and are still inside the outer hook.
			}
		}

		// We must resolve cases where there are multiple return values.
        // We can assume we only support a single return value (easier) or we can expand our definition of hooks
        // to handle multiple return values as an array of values.  For now, let's shoot simple and just take
        // the latest one and throw a warning to the admin
		return self::filterHookResults($resultsByPrefix, $name);
	}

    /**
	 * Handle cases where there are multiple results for a hook
	 *
	 * @param mixed $results     | An array where each element is a result array from an EM with keys 'result' and 'prefix'
	 * @param string $hookName    | The hook where the results were generated.
	 *
	 * @return array|null
	 */
	static function filterHookResults($results, $hookName) {
        if (empty($results)) return null;

		// The email hook needs special attention. The final result of multiple calls to the email hook should be all 
		// individual results and'ed together.
		if ($hookName === "email") {
			$cumulative_result = array_reduce($results, function($carry, $item) {
				return $carry && $item["result"];
			}, true);
			return $cumulative_result;
		}
		else if($hookName === 'module_api_before'){
			$messages = [];
			foreach($results as $result){
				$messages[] = static::tt('em_errors_178', $result['prefix'], $result['result']);
			}

			return implode("\n\n", $messages);
		}
		else if($hookName === 'module_randomize_record'){
			foreach($results as $result){
				return $result['result'];
			}
		}
		else if(in_array($hookName, ['pdf', 'custom_verify_username'])){
			// Take the last result
			end($results);
			$last_result = current($results);
	
			/**
			 * Throw a warning if there is more than one result.
			 * This should currently only happen on the 'custom_verify_username' hook,
			 * as the 'pdf' hook should automatically daisy chain return values,
			 * so there will only ever been one.
			 */
			if (count($results) > 1) {
				//= <p>{0} return values were generated from hook {1} by the following external modules:</p>
				$message = self::tt("em_errors_36", count($results), $hookName);
				foreach ($results as $result) {
					$message .= "<p><b><u>{$result['prefix']}</u></b> => <code>" . htmlentities(json_encode($result['result'])) . "</code></div></p>";
				}
				//= <p>Only the last result from <b><u>{0}</u></b> will be used by REDCap. Consider disabling or refactoring the other external modules so this does not occur.</p>
				$message .= self::tt("em_errors_37", $last_result["prefix"]); 
	
				static::handleError(
					//= REDCap External Module Results Warning
					self::tt("em_errors_38"), 
					$message,
					implode(', ', array_column($results, 'prefix')),
				);
			}
	
			return $last_result['result'];
		}

		/**
		 * Other hooks should not return values.  If they do (and they sometimes do), ignore them and return null per the following:
		 * https://redcap.vumc.org/community/post.php?id=128636
		 */
		return null;
    }


	/**
	 * @return void
	 */
	public static function exitAfterHook(){
		self::getCurrentHookRunner()->setExitAfterHook(true);
	}

	/**
	 * @return void
	 */
	public static function redirectAfterHook($url, $forceJS = false){
		// If contents already output, use javascript to redirect instead
		if (headers_sent() || $forceJS)
		{
			$url = static::escape($url);
			echo "<script type=\"text/javascript\">window.location.href=\"$url\";</script>";
		}
		// Redirect using PHP
		else
		{
			header("Location: $url");
		}

		static::exitAfterHook();
	}

	# places module in delaying queue to be executed after all others are executed
	public static function delayModuleExecution($prefix, $version) {
		return self::getCurrentHookRunner()->delayModuleExecution($prefix, $version);
	}

	# This function exists solely to provide a scope where we don't care if local variables get overwritten by code in the required file.
	# Use the $arguments variable to pass data to the required file.
	/**
	 * @psalm-suppress PossiblyUnusedParam
	 * 
	 * @return void
	 *
	 * @param (int|mixed|string)[] $arguments
	 * @param string $path
	 */
	static function safeRequire($path, $arguments = array()){
		if (file_exists(APP_PATH_EXTMOD . $path)) {
			require APP_PATH_EXTMOD . $path;
		} else {
			require $path;
		}
	}

	# This function exists solely to provide a scope where we don't care if local variables get overwritten by code in the required file.
	# Use the $arguments variable to pass data to the required file.
	/**
	 * @psalm-suppress PossiblyUnusedParam
	 * 
	 * @return void
	 *
	 * @param string $path
	 */
	static function safeRequireOnce($path, $arguments = array()){
		if (file_exists(APP_PATH_EXTMOD . $path)) {
			$path = APP_PATH_EXTMOD . $path;
		}

		/**
		 * The current directory could be a few different things at this point.
		 * We temporarily set it to the module directory to avoid relative paths from incorrectly referencing the wrong directory.
		 * This fixed a real world case where a require call for 'vendor/autoload.php' in the module
		 * was loading the autoload.php file from somewhere other than the module.
		 */
		$originalDir = getcwd();
		chdir(dirname($path));
		require_once $path;
		chdir($originalDir);
	}

	/**
	 * This method only used by the "External Modules Submission Helper" module.
	 * It lives here to ensure that unit tests detect any breakages (like changes to README.md).
	 */
	static function getMinREDCapVersionsByFrameworkVersion()
	{
		if(self::$MIN_REDCAP_VERSIONS_BY_FRAMEWORK_VERSION === null){
			$lines = explode("\n", file_get_contents(__DIR__ . '/../docs/versions/README.md'));
			foreach($lines as $line){
				if(strpos($line, '|[Version ') === 0){
					$columns = explode('|', $line);
					
					$parts = explode('Version ', $columns[1]);
					$parts = explode(']', $parts[1]);
					$frameworkVersion = (int) $parts[0];
	
					self::$MIN_REDCAP_VERSIONS_BY_FRAMEWORK_VERSION[$frameworkVersion]['standard'] = trim($columns[2]);
					self::$MIN_REDCAP_VERSIONS_BY_FRAMEWORK_VERSION[$frameworkVersion]['lts'] = trim($columns[3]);
				}
			}
		}

		return self::$MIN_REDCAP_VERSIONS_BY_FRAMEWORK_VERSION;
	}

	/**
	 * This method only used by the "External Modules Submission Helper" module.
	 * It lives here to ensure that unit tests detect any breakages (like changes to README.md).
	 * 
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	static function getFrameworkAdjustedREDCapVersionMin($config)
	{
		$minVersion = $config['compatibility']['redcap-version-min'] ?? null;

		$frameworkVersion = $config['framework-version'] ?? null;
		if(!empty($frameworkVersion)){
			$minRedcapVersionsByFrameworkVersion = self::getMinREDCapVersionsByFrameworkVersion();
			$frameworkMinVersion = $minRedcapVersionsByFrameworkVersion[$frameworkVersion]['standard'] ?? null;
			
			// Per the unit test, this compare method works as expected for null, empty string, and "TBD".
			if(
				$minVersion === null // Avoids a PHP 8.1 warning
				||
				version_compare($frameworkMinVersion ?? '', $minVersion, '>')
			){
				$minVersion = $frameworkMinVersion;
			}
		}

		return $minVersion;
	}

	# Ensure compatibility with PHP version and REDCap version during module installation using config values
	/**
	 * @return void
	 */
	private static function isCompatibleWithREDCapPHP($moduleDirectoryPrefix, $version)
	{
		$config = self::getConfig($moduleDirectoryPrefix, $version);
		if (!isset($config['compatibility'])) return;
		$Exceptions = array();
		$compat = $config['compatibility'];
		$phpMinVersion = static::getPHPMinVersion($config, static::getComposerConfig($moduleDirectoryPrefix, $version));
		if (isset($compat['php-version-max']) && !empty($compat['php-version-max']) && !version_compare(PHP_VERSION, $compat['php-version-max'], '<=')) {
			//= This module's maximum compatible PHP version is {0}, but you are currently running PHP {1}.
			$Exceptions[] = self::tt("em_errors_39", $compat['php-version-max'], PHP_VERSION); 
		}
		elseif (!empty($phpMinVersion) && !version_compare(PHP_VERSION, $phpMinVersion, '>=')) {
			$Exceptions[] = self::tt("em_errors_173", $phpMinVersion, PHP_VERSION); 
		}
		if (isset($compat['redcap-version-max']) && !empty($compat['redcap-version-max']) && !version_compare(REDCAP_VERSION, $compat['redcap-version-max'], '<=')) {
			//= This module's maximum compatible REDCap version is {0}, but you are currently running REDCap {1}.
			$Exceptions[] = self::tt("em_errors_41", $compat['redcap-version-max'], REDCAP_VERSION); 
		}
		elseif (isset($compat['redcap-version-min']) && !empty($compat['redcap-version-min']) && !version_compare(REDCAP_VERSION, $compat['redcap-version-min'], '>=')) {
			//= This module's minimum required REDCap version is {0}, but you are currently running REDCap {1}.
			$Exceptions[] = self::tt("em_errors_42", $compat['redcap-version-min'], REDCAP_VERSION); 
		}

		if (!empty($Exceptions)) {
			//= COMPATIBILITY ERROR: This version of the module '{0}' is not compatible with your current version of PHP and/or REDCap, so cannot be installed on your REDCap server at this time. Details:
			// Remove any potential HTML tags from name for use in error messages.
			throw new Exception(self::tt("em_errors_43", strip_tags($config['name'])) . " " . implode(" ", $Exceptions));
		}
	}

	private static function getComposerConfig($moduleDirectoryPrefix, $version){
		$path = self::getModuleDirectoryPath($moduleDirectoryPrefix, $version)."/composer.json";
		
		if(!file_exists($path)){
			return null;
		}

		$composerConfig = json_decode(file_get_contents($path), true);
		if(empty($composerConfig['require'])){
			/**
			 * Only 'require-dev' dependencies exist.  Ignore composer.json, since composer dependencies are likely only used for development.
			 */
			return null;
		}

		return $composerConfig;
	}
	
	// This method is now considered publicly supported to allow modules to easily be configured/utilized by other modules and traditional plugins/hooks.
	/**
	 * @param string $prefix
	 */
	public static function getModuleInstance($prefix, $version = null)
	{
		$framework = self::getFrameworkInstance($prefix, $version);
		return $framework->getModuleInstance();
	}

	/**
	 * @param string $prefix
	 */
	public static function getFrameworkInstance($prefix, $version = null)
	{
		$previousActiveModulePrefix = self::getActiveModulePrefix();
		self::setActiveModulePrefix($prefix);

		try{
			if($version == null){
				$version = self::getEnabledVersion($prefix);

				if($version == null){
					//= Cannot create module instance, since the module with the following prefix is not enabled: {0}
					throw new Exception(self::tt("em_errors_44", $prefix)); 
				}
			}

			$instance = self::$instanceCache[$prefix][$version] ?? null;
			if($instance === null){			
				$modulePath = self::getModuleDirectoryPath($prefix, $version);
				if(!$modulePath){
					throw new Exception(self::tt("em_errors_155", $version, $prefix));
				}

				$config = self::getConfig($prefix, $version);

				$namespace = $config['namespace'] ?? null;
				if(empty($namespace)) {
					//= The '{0}' module MUST specify a 'namespace' in it's config.json file.
					throw new Exception(self::tt("em_errors_45", $prefix)); 
				}
				else if(strpos($namespace, '/') !== false){
					throw new Exception(static::tt("em_errors_170", $prefix)); 
				}

				$parts = explode('\\', $namespace);
				$className = end($parts);

				$classNameWithNamespace = "\\$namespace\\$className";

				$classFilePath = self::getSafePath("$className.php", $modulePath);

				if(!file_exists($classFilePath)){
					//= Could not find the module class file '{0}' for the module with prefix '{1}'.
					throw new Exception(self::tt("em_errors_46", 
						$classFilePath, 
						$prefix)); 
				}

				// The @ sign is used to ignore any warnings in the module's code.
				@self::safeRequireOnce($classFilePath);

				if (!class_exists($classNameWithNamespace)) {
					//= The file '{0}.php' must define the '{1}' class for the '{2}' module.
					throw new Exception(self::tt("em_errors_47", 
						$className, 
						$classNameWithNamespace, 
						$prefix)); 
				}

				if (!is_subclass_of($classNameWithNamespace, AbstractExternalModule::class)) {
					throw new Exception(self::tt("em_errors_7"));
				}

				// The module & framework instances will be cached via a cacheFrameworkInstance() call inside the module constructor,
				// See the comment in AbstractExternalModule::__construct() for details.
				// The @ sign is used to ignore any warnings in the module's code.
				@(new $classNameWithNamespace());
				$instance = self::$instanceCache[$prefix][$version];
				if ($instance == null) {
					throw new Exception(self::tt("em_errors_169", $prefix, $version));
				}
			}

			return $instance;
		}
		finally{
			// Restore the active module prefix to what it was before.
			// We restore it inside a try/finally so that exceptions in expected cases do not cause admin emails (see GitHub issue #561).
			// Calling getModuleInstance() while a module is active (inside a hook) should probably be disallowed,
			// even if it's for the same prefix that is currently active.
			// However, this seems to happen on occasion with the email alerts module,
			// so we restore what was there before just to be safe.
			self::setActiveModulePrefix($previousActiveModulePrefix);
		}
	}

	/**
	 * @return void
	 *
	 * @param AbstractExternalModule $module
	 */
	public static function cacheFrameworkInstance($module){
		self::$instanceCache[$module->PREFIX][$module->VERSION] = new Framework($module);
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 * 
	 * @param string $prefix
	 */
	public static function enableTestSettings($prefix) {
		static::$TEST_SETTINGS[$prefix] = [];
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public static function disableTestSettings($prefix) {
		unset(static::$TEST_SETTINGS[$prefix]);
	}

	# Accepts a project id as the first parameter.
	# If the project id is null, all system-wide enabled module instances are returned.
	# Otherwise, only instances enabled for the current project id are returned.
	/**
	 * @param null|numeric|string $pid
	 */
	static function getEnabledModules($pid = null)
	{
		if($pid == null){
			return self::getSystemwideEnabledVersions();
		}
		else{
			return self::getEnabledModuleVersionsForProject($pid);
		}
	}

	static function getSystemwideEnabledVersions()
	{
		if(!isset(self::$systemwideEnabledVersions)){
			self::cacheAllEnableData();
		}

		return self::$systemwideEnabledVersions;
	}

	private static function getProjectEnabledDefaults()
	{
		if(!isset(self::$projectEnabledDefaults)){
			self::cacheAllEnableData();
		}

		return self::$projectEnabledDefaults;
	}

	private static function getProjectEnabledOverrides()
	{
		if(!isset(self::$projectEnabledOverrides)){
			self::cacheAllEnableData();
		}

		return self::$projectEnabledOverrides;
	}

	# get all versions enabled for a given project
	/**
	 * @return array
	 *
	 * @param numeric|string $pid
	 */
	private static function getEnabledModuleVersionsForProject($pid)
	{
		$projectEnabledOverrides = self::getProjectEnabledOverrides();

		$enabledPrefixes = self::getProjectEnabledDefaults();
		$overrides = $projectEnabledOverrides[$pid] ?? [];
		foreach($overrides as $prefix => $value){
			if($value){
				$enabledPrefixes[$prefix] = true;
			}
			else{
				unset($enabledPrefixes[$prefix]);
			}
		}

		$systemwideEnabledVersions = self::getSystemwideEnabledVersions();

		$enabledVersions = array();
		foreach(array_keys($enabledPrefixes) as $prefix){
			$version = $systemwideEnabledVersions[$prefix] ?? null;

			// Check the version to make sure the module is not systemwide disabled.
			if(isset($version)){
				$enabledVersions[$prefix] = $version;
			}
		}

		return $enabledVersions;
	}

	/**
	 * @return bool
	 */
	private static function shouldExcludeModule($prefix, $version = null)
	{
		if ($version && strpos($_SERVER['REQUEST_URI'], '/manager/ajax/enable-module.php') !== false && static::getProjectId() === null && $prefix == ExternalModules::getPrefixFromPost() && $_POST['version'] != $version) {
            // We are in the process of switching an already enabled module from one version to another.
            // We need to exclude the old version of the module to ensure that the hook for the new version is the one that is executed.
			return true;
		}

		// The fake unit testing modules are not currently ever enabled in the DB,
		// but we may as well leave this check in place in case that changes in the future.
		$isTestPrefix = strpos($prefix, self::TEST_MODULE_PREFIX) === 0;
		if($isTestPrefix && !self::isTesting()){
			// This php process is not running unit tests.
			// Ignore the test prefix so it doesn't interfere with this process.
			return true;
		}

		return false;
	}

	/**
	 * @return bool
	 */
	static function isTesting()
	{
		$command = $_SERVER['argv'][0] ?? '';
		$command = str_replace('\\', '/', $command); // for powershell
		$parts = explode('/', $command);
		$command = end($parts);

		return self::isCommandLine() && in_array($command, ['phpunit', 'phpcs']);
	}

	/**
	 * @return bool
	 */
	static function isCommandLine(){
		return PHP_SAPI === 'cli';
	}

	# calling this method stores a local cache of all relevant data from the database
	/**
	 * @return void
	 */
	private static function cacheAllEnableData()
	{
		$systemwideEnabledVersions = array();
		$projectEnabledOverrides = array();
		$projectEnabledDefaults = array();

		$result = self::getSettings(null, null, array(self::KEY_VERSION, self::KEY_ENABLED));

		// Split results into version and enabled arrays: this seems wasteful, but using one
		// query above, we can then validate which EMs/versions are valid before we build
		// out which are enabled and how they are enabled
		$result_versions = array();
		$result_enabled = array();
		while($row = self::validateSettingsRow($result->fetch_assoc())) {
			$key = $row['key'];
			if ($key == self::KEY_VERSION) {
				if($row['project_id'] === null){
					$result_versions[] = $row;
				}
				else{
					/**
					 * Ignore this value.  KEY_VERSION settings should never exist with a project ID,
					 * but we've seen it happen before if a module manually makes setting table queries.
					 * Ignoring these values allow modules to continue functioning in that scenario.
					 */
				}
			} else if($key == self::KEY_ENABLED) {
				$result_enabled[] = $row;
			} else {
				//= Unexpected key: {0}
				throw new Exception(self::tt("em_errors_48", $key)); 
			}
		}

		// For each version, verify if the module folder exists and is valid
		foreach ($result_versions as $row) {
			$prefix = $row['directory_prefix'];
			$value = $row['value'];
			if (self::shouldExcludeModule($prefix, $value)) {
				continue;
			} else {
				$systemwideEnabledVersions[$prefix] = $value;
			}
		}

		// Set enabled arrays for EMs
		foreach ($result_enabled as $row) {
			$pid = $row['project_id'];
			$prefix = $row['directory_prefix'];
			$value = $row['value'];

			// If EM was not valid above, then skip
			if (!isset($systemwideEnabledVersions[$prefix])) {
				continue;
			}

			// Set enabled global or project
			if (isset($pid)) {
				$projectEnabledOverrides[$pid][$prefix] = $value;
			} else if ($value) {
				$projectEnabledDefaults[$prefix] = true;
			}
		}

		// Overwrite any previously cached results
		self::$systemwideEnabledVersions = $systemwideEnabledVersions;
		self::$projectEnabledDefaults = $projectEnabledDefaults;
		self::$projectEnabledOverrides = $projectEnabledOverrides;
	}

	# echo's HTML for adding an approriate resource; also prepends appropriate directory structure
	/**
	 * @return void
	 *
	 * @param string $path
	 */
	static function addResource($path)
	{
		$extension = pathinfo($path, PATHINFO_EXTENSION);

		if(substr($path,0,8) == "https://" || substr($path,0,7) == "http://") {
			$url = $path;
		}
		else {
			$path = "manager/$path";
			$fullLocalPath = __DIR__ . "/../$path";

			// Add the filemtime to the url for cache busting.
			clearstatcache(true, $fullLocalPath);
			$url = APP_URL_EXTMOD_RELATIVE . $path . '?' . filemtime($fullLocalPath);
		}

		if(in_array($url, self::$INCLUDED_RESOURCES)) return;

		if ($extension == 'css') {
			echo "<link rel='stylesheet' type='text/css' href='" . $url . "'>";
		}
		else if ($extension == 'js') {
			echo "<script type='text/javascript' src='" . $url . "'></script>";
		}
		else {
			//= Unsupported resource added: {0}
			throw new Exception(self::tt("em_errors_49", $path)); 
		}

		self::$INCLUDED_RESOURCES[] = $url;
	}

	# returns an array of links requested by the config.json
	/**
	 * @return array
	 */
	static function getLinks($prefix = null, $version = null)
	{
		$pid = self::getProjectId();

		if(isset($pid)){
			$type = 'project';
		}
		else{
			$type = 'control-center';
		}

		$links = array();
		$sortedLinks = array();

		if ($prefix === null || $version === null) {
			$versionsByPrefix = self::getEnabledModules($pid);
		} else {
			$versionsByPrefix = [$prefix => $version];
		}

		foreach($versionsByPrefix as $prefix=>$version){
			// Get links from translated configs.
			$config = ExternalModules::getConfig($prefix, $version, null, true);

			$moduleLinks = $config['links'][$type] ?? null;
			if($moduleLinks === null){
				continue;
			}

			$linkcounter = 0;
			foreach($moduleLinks as $link){
				$linkcounter++;
				
				$key = $link['key'] ?? null;
				if($key !== null && !self::isLinkKeyValid($key)){
					//= WARNING: The 'key' for the above link in 'config.json' needs to be modified to only contain valid characters ([-A-Za-z0-9]).
					$link['name'] .= '<br>' . self::tt('em_errors_140');
					$key = null;
				}

				if(empty($key)){
					$key = "link_{$type}_{$linkcounter}";
				}
				
				// Prefix key with prefix; otherwise, same-named links from different modules overwrite each other!
				$key = "{$prefix}-{$key}";
				// Ensure that a module's link keys are unique
				if (!empty($links[$key])) {
					//= Link keys must be unique. The key '{0}' has already been used.
					throw new Exception(self::tt("em_errors_141", $link['key']));
				}
				$link_type = self::getLinkType($link['url']);
				if ($link_type == "ext") {
					$link['target'] = isset($link['target']) ? $link['target'] : "_blank";
				}
				else if ($link_type == "page") {
					$link['url'] = self::getPageUrl($prefix, $link['url']);
				}
				$link['prefix'] = $prefix;
				$link['prefixedKey'] = $key;
				$links[$key] = $link;
				$sortedLinks["{$prefix}-{$linkcounter}"] = $key;
			}
		}

		ksort($sortedLinks); // Ensure order as in config.json.
		$returnSorted = function($key) use ($links) {
			return $links[$key];
		};
		return array_map($returnSorted, $sortedLinks);
	}

	/**
	 * Checks if a key is valid.
	 *
	 * @return false|int
	 */
	public static function isLinkKeyValid($key) {
		return preg_match('/^[-A-Za-z0-9]*$/', $key ?? '');
	}

	/**
	 * Determines the type of link: page, js, ext.
	 *
	 * @return string
	 */
	public static function getLinkType($url) {
		$url = strtolower($url);
		if (strpos($url, "http://") === 0 || strpos($url, "https://") === 0) return "ext";
		if (strpos($url, "javascript:") === 0) return "js";
		return "page";
	}

	/**
	 * @return string
	 *
	 * @param string $page
	 * @param string $prefix
	 * @param bool $useApiEndpoint
	 */
	public static function getPageUrl($prefix, $page, $useApiEndpoint=false)
	{
		$getParams = array();
		if (preg_match("/\.php\?.+$/", $page, $matches)) {
			$getChain = preg_replace("/\.php\?/", "", $matches[0]);
			$page = preg_replace("/\?.+$/", "", $page);
			$getPairs = explode("&", $getChain);
			foreach ($getPairs as $pair) {
				$a = explode("=", $pair);
				# implode unlikely circumstance of multiple ='s
				$b = array();
				for ($i = 1; $i < count($a); $i++) {
					$b[] = $a[$i];
				}
				$value = implode("=", $b);
				$getParams[$a[0]] = $value;
			}
			if (isset($getParams['prefix'])) {
				unset($getParams['prefix']);
			}
			if (isset($getParams['page'])) {
				unset($getParams['page']);
			}
		}
		$page = preg_replace('/\.php$/', '', $page); // remove .php extension if it exists
		$get = "";
		foreach ($getParams as $key => $value) {
			$get .= "&$key=$value";
		}

		$base = $useApiEndpoint ? self::getModuleAPIUrl() : APP_URL_EXTMOD."?";
		return $base . "prefix=" . urlencode($prefix) . "&page=" . urlencode($page) . $get;
	}

	/**
	 * @return string
	 *
	 * @param null|numeric-string $pid
	 * @param string $prefix
	 * @param string $path
	 * @param bool $noAuth
	 * @param bool $useApiEndpoint
	 */
	public static function getUrl($prefix, $path, $pid = null, $noAuth = false, $useApiEndpoint = false)
	{
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		// Include 'md' files as well to render README.md documentation.
		$isPhpPath = in_array($extension, ['php', 'md']) || (preg_match("/\.php\?/", $path));
		if ($isPhpPath || $useApiEndpoint) {
			// GET parameters after php file -OR- php extension
			$url = self::getPageUrl($prefix, $path, $useApiEndpoint);
			if ($isPhpPath) {
				if(!empty($pid) && !preg_match("/[\&\?]pid=/", $url)){
					$url .= '&pid='.$pid;
				}
				if($noAuth && !preg_match("/NOAUTH/", $url)) {
					$url .= '&NOAUTH';
				}
			}
		} else {
			// This must be a resource, like an image, PDF readme, or css/js file.
			// Go ahead and return the version specific url.
			$pathPrefix = ExternalModules::getModuleDirectoryPath($prefix);
			$url =  ExternalModules::getModuleDirectoryUrl($prefix) . $path . '?' . filemtime($pathPrefix . '/' . $path);
		}
		return $url;
	}

	/**
	 * @return string
	 */
	static function getModuleAPIUrl()
	{
		return APP_PATH_WEBROOT_FULL."api/?type=module&";
	}
	
	# Returns boolean regarding if the module is an example module in the example_modules directory.
	# $version can be provided as a string or as an array of version strings, in which it will return TRUE 
	# if at least ONE of them is in the example_modules directory.
	/**
	 * @return bool
	 *
	 * @param (int|string) $prefix
	 * @param (int|string)[] $version
	 */
	static function isExampleModule($prefix, $version=array())
	{
		if (!is_array($version) && $version == '') return false;
		if (!is_array($version)) $version = array($version);
		foreach ($version as $this_version) {
			$moduleDirName = APP_PATH_EXTMOD . 'example_modules' . DS . $prefix . "_" . $this_version;
			if (file_exists($moduleDirName) && is_dir($moduleDirName)) return true;
		}
		return false;
	}

	# returns the configs for disabled modules
	/**
	 * @return array[]
	 */
	static function getDisabledModuleConfigs($enabledModules)
	{
		$dirs = self::getModulesInModuleDirectories();

		$disabledModuleVersions = array();
		foreach ($dirs as $dir) {
			if ($dir[0] == '.') {
				// This line was added back when we had to exclude the '.' and '..' results from scandir().
				// It is only being left in place in case any existing REDCap installations have
				// come to expect "hidden" directories to be ignored.
				continue;
			}

			list($prefix, $version) = self::getParseModuleDirectoryPrefixAndVersion($dir);

			if($prefix && ($enabledModules[$prefix] ?? null) != $version) {
				$versions = $disabledModuleVersions[$prefix] ?? null;
				if(!isset($versions)){
					$versions = array();
				}

				try {
					// Do not translate the configuration as the modules will not be instantiated and thus
					// their language strings are not available.
					$config = self::getConfig($prefix, $version);
				} 
				catch (\Throwable $t) {
					$config = static::getDefaultConfigForBrokenModule($prefix . '<br>' . static::tt('em_errors_187'), '');
				}

				// Use array_merge_recursive() to show newest versions first.
				$disabledModuleVersions[$prefix] = array_merge_recursive(
					array($version => $config),
					$versions
				);
			}
		}
		
		// Make sure the version numbers for each module get sorted naturally
		foreach ($disabledModuleVersions as &$versions) {
			natcaseksort($versions, true);
		}

		// Sort modules by the name from the latest version
		uasort($disabledModuleVersions, function($a, $b){
			$getName = function($moduleDetails){
				return reset($moduleDetails)['name'];
			};

			return strcasecmp($getName($a), $getName($b));
		});

		return $disabledModuleVersions;
	}

	# Parses [institution]_[module]_v[X].[Y] into [ [institution]_[module], v[X].[Y] ]
	# e.g., vanderbilt_example_v1.0 becomes [ "vanderbilt_example", "v1.0" ]
	/**
	 * @return (null|string)[]
	 *
	 * @param int|string $directoryName
	 */
	static function getParseModuleDirectoryPrefixAndVersion($directoryName){
		$directoryName = basename($directoryName);

		$parts = explode('_', $directoryName);

		$version = array_pop($parts);
		$versionParts = explode('v', $version);
		$versionNumberParts = explode('.', $versionParts[1] ?? '');
		if(count($versionParts) != 2 || $versionParts[0] != '' || count($versionNumberParts) > 3){
			// The version is invalid.  Return null to prevent this folder from being listed.
			$version = null;
		}

		foreach($versionNumberParts as $part){
			if(!is_numeric($part)){
				$version = null;
			}
		}

		$prefix = implode('_', $parts);

		return array($prefix, $version);
	}

	static function getDefaultConfigForBrokenModule($name, $description)
	{
		$config = [
			'name' => ' ' . $name, // Prefix with a space to force sorting to the top
			'description' => $description,
		];

		$config = static::normalizeConfigSections($config);

		return $config;
	}

	# returns the config.json for a given module
	/**
	 * @param null|string $version
	 * @param null|numeric-string $pid
	 * @param bool $translate
	 * @param (int|string) $prefix
	 */
	static function getConfig($prefix, $version = null, $pid = null, $translate = false)
	{
		if(empty($prefix)){
			//= You must specify a prefix!
			throw new Exception(self::tt("em_errors_50")); 
		}
		if($version == null){
			$version = self::getEnabledVersion($prefix);
		}

		// Is the desired configuration in the cache?
		$config = self::getCachedConfig($prefix, $version, $translate);
		if($config === null) {
			// Is the non-translated config in the cache?
			$config = $translate ? self::getCachedConfig($prefix, $version, false) : null;
			if ($config === null) {
				$configFilePath = self::getModuleDirectoryPath($prefix, $version)."/config.json";
				if(file_exists($configFilePath)){
					$fileTesting = file_get_contents($configFilePath);
					if($fileTesting != "") {
						$config = json_decode($fileTesting, true);
						if($config == null){
							// Disable the module to prevent repeated errors, especially those that prevent the External Modules menu items from appearing.
							self::disable($prefix, true);
							// An error occurred while parsing a configuration file for the '{0}' external module. The module was automatically disabled in order to allow REDCap to function properly. The following file is likely not valid JSON: {1}
							static::sendAdminEmail(
								static::tt('em_errors_181'),
								static::tt(
									'em_errors_183',
									$prefix,
									$configFilePath,
								)
							);
							//= An error occurred while parsing a configuration file! The following file is likely not valid JSON: {0}
							throw new Exception(self::tt("em_errors_51", $configFilePath));
						}

						$config = self::normalizeConfigSections($config, $configFilePath);
					}
				}
				
				if($config === null){
					$config = [];
				}

				$config = static::embellishConfig($prefix, $config);
			}

			if ($translate && !empty($config)) {
				// Apply translations to config and add to cache.
				// Language settings -if available- are only ever needed in translated versions of the config.
				self::initializeLocalizationSupport($prefix, $version);
				$config = self::translateConfig($config, $prefix);
				$config = self::addLanguageSetting($config, $prefix, $version, $pid);
			}
			self::setCachedConfig($prefix, $version, $translate, $config);
		}

		return $config;
	}

	static function embellishConfig($prefix, $config){
		if(static::getFrameworkVersionForConfig($prefix, $config) < Framework::HIDDEN_SETTING_FIX_FRAMEWORK_VERSION){
			$config = self::applyHidden($config);
		}

		$config = self::addAdditionalSettings($config);

		return $config;
	}

	/**
	 * Ensures the validity of some config sections
	 * @param array $config 
	 * @param string $config_path Path to the config file - for error reporting
	 */
	static function normalizeConfigSections($config, $config_path = ''): array {
		foreach([
			'permissions',
			'system-settings',
			'project-settings',
			'no-auth-pages',
			'no-csrf-pages',
			'crons',
			self::MODULE_AUTH_AJAX_ACTIONS_SETTING,
			self::MODULE_NO_AUTH_AJAX_ACTIONS_SETTING,
			self::MODULE_API_ACTIONS_SETTING,
		] as $key) {
			$value = $config[$key] ?? null;
			if($value === null) {
				$config[$key] = array();
			}
			else if (!is_array($value)) {
				throw new Exception(self::tt("em_errors_168", $key, $config_path)); //= Configuration section '{0}' must be an array in configuration file '{1}'.
			}
		}
		// Ajax actions
		$action_sections = [ 
			self::MODULE_AUTH_AJAX_ACTIONS_SETTING, 
			self::MODULE_NO_AUTH_AJAX_ACTIONS_SETTING 
		];
		$reserved_actions = [ self::MODULE_AJAX_LOGGING_ACTION ];
		foreach ($action_sections as $section) {
			foreach ($config[$section] as $item) {
				// Item must be a string
				if (!is_string($item) || !strlen($item)) {
					throw new Exception(self::tt("em_errors_166", $item, $section, $config_path)); //= Configuration item '{0}' in section '{1}' must be of type string in configuration file '{2}'.
				}
				// It must not be a reserved action
				if (in_array($item, $reserved_actions, true)) {
					throw new Exception(self::tt("em_errors_167", $item, $section, $config_path)); //= Configuration item '{0}' in section '{1}' is reserved. Please use a different name in configuration file '{2}'.
				}
			}
		}
		// API actions must define the action and a description
		// Access modes are optional (default mode is auth only), but when specified, 
		// must be from the list of supported modes of access (auth, no-auth)
		$check_api_action = function($action, &$action_data) {
			if (!self::validateApiActionName($action)) return false;
			if (!is_array($action_data)) return false;
			if (!isset($action_data["description"]) || !is_string($action_data["description"]) || empty($action_data["description"])) return false;
			if (!isset($action_data["access"])) {
				$action_data["access"] = ["auth"];
			}
			else {
				if (!is_array($action_data["access"]) || count($action_data["access"]) == 0) return false;
				$valid_modes = ["auth", "no-auth"];
				foreach ($action_data["access"] as $mode) {
					if (!in_array($mode, $valid_modes, true)) return false;
				}
			}
			// Limit descriptions to contain only allowed tags
			$action_data["description"] = strip_tags($action_data["description"], self::API_DESCRIPTION_ALLOWED_TAGS);
			return true;
		};
		foreach ($config[self::MODULE_API_ACTIONS_SETTING] as $action => &$action_data) {
			if (!$check_api_action($action, $action_data)) {
				throw new Exception(self::tt("em_errors_188", $action, self::MODULE_API_ACTIONS_SETTING, $config_path)); //= Invalid configuration item '{0}' in section '{1}' in configuration file '{2}'. Please check the documentation for valid syntax and limitations. 
			}
		}

		return $config;
	}

	// This function should NOT be used outside the contexts in which it is currently called.
	/**
	 * @param false $translated
	 * @param int|string $prefix
	 */
	private static function getCachedConfig($prefix, $version, $translated){
		return self::$configs[$prefix][$version][$translated] ?? null;
	}

	// This function should NOT be used outside the contexts in which it is currently called.
	/**
	 * @return void
	 *
	 * @param int|string $prefix
	 * @param bool $translated
	 */
	public static function setCachedConfig($prefix, $version, $translated, $config){
		self::$configs[$prefix][$version][$translated] = $config;
	}

	public static function escape($value){
		$type = gettype($value);

		/**
		 * The unnecessary casting on these first few types exists solely to inform psalm and avoid warnings.
		 */
		if($type === 'boolean'){
			return (bool) $value;
		}
		else if($type === 'integer'){
			return (int) $value;
		}
		else if($type === 'double'){
			return (float) $value;
		}
		else if($type === 'array'){
			$newValue = [];
			foreach($value as $key=>$subValue){
				$key = static::escape($key);
				$subValue = static::escape($subValue);
				$newValue[$key] = $subValue;
			}

			return $newValue;
		}
		else if($type === 'NULL'){
			return null;
		}
		else{
			/**
			* Handle strings, resources, and custom objects (via the __toString() method. 
			* Apart from escaping, this produces that same behavior as if the $value was echoed or appended via the "." operator.
			*/
			return htmlspecialchars(''.$value, ENT_QUOTES);
		}
	}

	public static function getActiveProjectWhereClauses() {
		return "
			AND p.date_deleted IS NULL
			AND p.status IN (0,1) 
			AND p.completed_time IS NULL
		";
	}

	// This method must stay public because it is used by the Email Alerts module directly.
	/**
	 * @param null|numeric-string $pid
	 */
	public static function getAdditionalFieldChoices($configRow,$pid) {
		if($configRow['type'] == 'sub_settings') {
			foreach ($configRow['sub_settings'] as $subConfigKey => $subConfigRow) {
				$configRow['sub_settings'][$subConfigKey] = self::getAdditionalFieldChoices($subConfigRow,$pid);
				if($configRow['super-users-only'] ?? null) {
					$configRow['sub_settings'][$subConfigKey]['super-users-only'] = $configRow['super-users-only'];
				}
				if(!isset($configRow['source']) && ($configRow['sub_settings'][$subConfigKey]['source'] ?? null)) {
					$configRow['source'] = "";
				}
				$configRow["source"] = ($configRow["source"] ?? null).(($configRow["source"] ?? null) == "" ? "" : ",").($configRow['sub_settings'][$subConfigKey]['source'] ?? null);
			}
		}
		else if($configRow['type'] == 'project-id') {
			// We only show projects to which the current user has design rights 
			// since modules could make all kinds of changes to projects.
			$sql = "SELECT CAST(p.project_id as char) as project_id, p.app_title
					FROM redcap_projects p
					JOIN redcap_user_rights u ON p.project_id = u.project_id
					LEFT OUTER JOIN redcap_user_roles r ON p.project_id = r.project_id AND u.role_id = r.role_id
					WHERE u.username = ? " . static::getActiveProjectWhereClauses();

			if(!ExternalModules::isSuperUser()){
				$sql .= " AND (u.design = 1 OR r.design = 1)";
			}

			$result = ExternalModules::query($sql, ExternalModules::getUsername());

			$matchingProjects = [
				[
					"value" => "",
					//= --- None ---
					"name" => self::tt("em_config_6") 
				]
			];

			while($row = $result->fetch_assoc()) {
				$projectName = static::escape(fixUTF8($row["app_title"]));

				$otherPid = static::escape($row["project_id"]);
				$matchingProjects[] = [
					"value" => $otherPid,
					"name" => "(" . $otherPid . ") " . $projectName,
				];
			}
			$configRow['choices'] = $matchingProjects;
		}

		if(empty($pid)){
			// Return early since everything below here requires a pid.
			return $configRow;
		}
		else if ($configRow['type'] == 'user-role-list') {
				$choices = [];

				$sql = "SELECT CAST(role_id as CHAR) as role_id,role_name
						FROM redcap_user_roles
						WHERE project_id = ?
						ORDER BY role_id";
				$result = self::query($sql, [$pid]);

				while ($row = $result->fetch_assoc()) {
						$row = static::escape($row);
						$choices[] = ['value' => $row['role_id'], 'name' => strip_tags(nl2br($row['role_name']))];
				}

				$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'user-list') {
				$choices = [];

				$sql = "SELECT ur.username,ui.user_firstname,ui.user_lastname
						FROM redcap_user_rights ur, redcap_user_information ui
						WHERE ur.project_id = ?
								AND ui.username = ur.username
						ORDER BY ui.ui_id";
				$result = self::query($sql, [$pid]);

				while ($row = $result->fetch_assoc()) {
						$row = static::escape($row);
						$choices[] = ['value' => strtolower($row['username']), 'name' => $row['user_firstname'] . ' ' . $row['user_lastname']];
				}

				$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'dag-list') {
				$choices = [];

				$sql = "SELECT CAST(group_id as CHAR) as group_id,group_name
						FROM redcap_data_access_groups
						WHERE project_id = ?
						ORDER BY group_id";
				$result = self::query($sql, [$pid]);

				while ($row = $result->fetch_assoc()) {
						$row = static::escape($row);
						$choices[] = ['value' => $row['group_id'], 'name' => strip_tags(nl2br($row['group_name']))];
				}

				$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'field-list') {
			$choices = [];
			$sqlParams = [$pid];

			$enumSql = "";
			if(($configRow["validation"] ?? null) != "") {
			    if($configRow["validation"] == "date") {
                    $enumSql = " AND element_validation_type LIKE 'date_%'";
			    }
			    else if($configRow["validation"] == "datetime") {
                    $enumSql = " AND element_validation_type LIKE 'datetime_%'";
			    }
			    else {
                    $enumSql = " AND element_validation_type = ?";
                    $sqlParams[] = $configRow["validation"];
                }
			}

			$fieldTypeSql = "";
			if(($configRow["field-type"] ?? null) != "") {
			    if($configRow["field-type"] == "enum") {
                    $fieldTypeSql = " AND element_type IN ('select','radio','checkbox','yesno','truefalse')";
			    }
			    else {
                    $fieldTypeSql = " AND element_type = ?";
                    $sqlParams[] = $configRow["field-type"];
                }
			}

			$sql = "SELECT field_name,element_label
					FROM redcap_metadata
					WHERE project_id = ?".
					$enumSql.$fieldTypeSql."
					ORDER BY field_order";
			$result = self::query($sql, $sqlParams);

			while ($row = $result->fetch_assoc()) {
				$row['element_label'] = strip_tags(nl2br($row['element_label'] ?? ''));
				$row = static::escape($row); // Escape rows AFTER stripping tags
				if (mb_strlen($row['element_label']) > 30) {
					$row['element_label'] = mb_substr($row['element_label'], 0, 20) . "... " . mb_substr($row['element_label'], -8);
				}
				$choices[] = ['value' => $row['field_name'], 'name' => $row['field_name'] . " - " . htmlspecialchars($row['element_label'])];
			}

			$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'form-list') {
			$choices = [];
			$params = [ $pid ];
			$visibilityFilterSetting = $configRow['visibility-filter'] ?? '';
			switch ($visibilityFilterSetting) {
				case 'public':
					$visibilityFilterSql = ' AND form_name IN (
						SELECT form_name 
						FROM redcap_surveys 
						WHERE project_id = ?
					)';
					$params[] = $pid;
					break;
				case 'nonpublic':
					$visibilityFilterSql = ' AND form_name NOT IN (
						SELECT form_name 
						FROM redcap_surveys 
						WHERE project_id = ?
					)';
					$params[] = $pid;
					break;
				case '':
				default:
					$visibilityFilterSql = '';
					break;
			}
			$sql = "SELECT DISTINCT form_name
					FROM redcap_metadata
					WHERE project_id = ?
					" . $visibilityFilterSql . "
					ORDER BY field_order";
			$result = self::query($sql, $params);

			while ($row = $result->fetch_assoc()) {
				$row = static::escape($row);
				$choices[] = ['value' => $row['form_name'], 'name' => strip_tags(nl2br($row['form_name']))];
			}

			$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'arm-list') {
			$choices = [];

			$sql = "SELECT CAST(a.arm_id as CHAR) as arm_id, a.arm_name
					FROM redcap_events_arms a
					WHERE a.project_id = ?
					ORDER BY a.arm_id";
			$result = self::query($sql, [$pid]);

			while ($row = $result->fetch_assoc()) {
				$row = static::escape($row);
				$choices[] = ['value' => $row['arm_id'], 'name' => $row['arm_name']];
			}

			$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'event-list') {
			$choices = [];

			$sql = "SELECT CAST(e.event_id as CHAR) as event_id, e.descrip, CAST(a.arm_id as CHAR) as arm_id, a.arm_name
					FROM redcap_events_metadata e, redcap_events_arms a
					WHERE a.project_id = ?
						AND e.arm_id = a.arm_id
					ORDER BY e.event_id";
			$result = self::query($sql, [$pid]);

			while ($row = $result->fetch_assoc()) {
				$row = static::escape($row);
				$choices[] = ['value' => $row['event_id'], 'name' => "Arm: ".strip_tags(nl2br($row['arm_name']))." - Event: ".strip_tags(nl2br($row['descrip']))];
			}

			$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'report-list') {
			$choices = [];
			$visibilityFilterSetting = $configRow['visibility-filter'] ?? '';
			switch ($visibilityFilterSetting) {
				case 'public':
					$visibilityFilterSql = ' AND is_public = 1';
					break;
				case 'nonpublic':
					$visibilityFilterSql = ' AND is_public = 0';
					break;
				case '':
				default:
					$visibilityFilterSql = '';
					break;
			}

			$sql = "SELECT report_id,title
					FROM redcap_reports
					WHERE project_id = ?
					" . $visibilityFilterSql . "
					ORDER BY report_id";

			$result = self::query($sql, [$pid]);

			while ($row = $result->fetch_assoc()) {
				$row = static::escape($row);
				$choices[] = ['value' => $row['report_id'], 'name' => strip_tags(nl2br($row['title']))];
			}

			$configRow['choices'] = $choices;
		}
		else if ($configRow['type'] == 'dashboard-list') {

			$choices = [];
			$visibilityFilterSetting = $configRow['visibility-filter'] ?? '';
			switch ($visibilityFilterSetting) {
				case 'public':
					$visibilityFilterSql = ' AND is_public = 1';
					break;
				case 'nonpublic':
					$visibilityFilterSql = ' AND is_public = 0';
					break;
				case '':
				default:
					$visibilityFilterSql = '';
					break;
			}

			$sql = "SELECT dash_id, title
					FROM redcap_project_dashboards
					WHERE project_id = ?
					" . $visibilityFilterSql . "
					ORDER BY dash_id";

			$result = self::query($sql, [$pid]);

			while ($row = $result->fetch_assoc()) {
				$row       = static::escape($row);
				$choices[] = ['value' => $row['dash_id'], 'name' => strip_tags(nl2br($row['title']))];
			}

			$configRow['choices'] = $choices;
		}

		return $configRow;
	}

	# gets the version of a module
	/**
	 * @param string $prefix
	 */
	public static function getEnabledVersion($prefix)
	{
		$versionsByPrefix = self::getSystemwideEnabledVersions();
		return $versionsByPrefix[$prefix] ?? null;
	}

	private static function addAdditionalSettings($config)
	{
		if(empty($config)){
			// There was an issue loading the config.  Just return it as-is.
			return $config;
		}

		$existingSettingKeys = array();
		$getSettings = function($settingType) use ($config, &$existingSettingKeys){
			$settings = $config[$settingType] ?? null;
			if($settings === null){
				return [];
			}

			foreach($settings as $details){
				$existingSettingKeys[$details['key'] ?? null] = true;
			}

			return $settings;
		};

		$systemSettings = $getSettings('system-settings');
		$projectSettings = $getSettings('project-settings');

		$visibleReservedSettings = array();

        $module_provides_auth_api = 0;
        $module_provides_no_auth_api = 0;
		foreach ($config[self::MODULE_API_ACTIONS_SETTING] ?? [] as $action_data) {
			$module_provides_auth_api += in_array("auth", $action_data["access"]) ? 1: 0;
			$module_provides_no_auth_api += in_array("no-auth", $action_data["access"]) ? 1: 0;
		}
		foreach(self::getReservedSettings() as $details){
			$key = $details['key'];

			// If project has no project-level configuration, then do not add the reserved setting 
			// to require special user right in project to modify project config
			if ($key == self::KEY_CONFIG_USER_PERMISSION && empty($projectSettings)) continue;
            // Only add module API options when the module provides API services
            if ($key == self::KEY_RESERVED_DISABLE_AUTH_API && !$module_provides_auth_api) continue;
            if ($key == self::KEY_RESERVED_DISABLE_NO_AUTH_API && !$module_provides_no_auth_api) continue;

			if(!isset($details['hidden']) || ($details['hidden'] != true)){
				$visibleReservedSettings[] = $details;
			}
		}

		// Merge arrays so that reserved settings always end up at the top of the list.
		$config['system-settings'] = array_merge($visibleReservedSettings, $systemSettings);
		$config['project-settings'] = array_merge(static::getOverridableSettings($config), $projectSettings);

		return $config;
	}

	# formats directory name from $prefix and $version
	/**
	 * @param string $prefix
	 * @param null|string $version
	 *
	 * @return false|string
	 */
	static function getModuleDirectoryPath($prefix, $version = null){
		// This may not be the most appropriate place/way to perform this escaping, but it should have not affect on valid values.
		$prefix = htmlentities($prefix, ENT_QUOTES);

		if(self::isTesting() && in_array($prefix, [self::TEST_MODULE_PREFIX, self::TEST_MODULE_TWO_PREFIX])){
			return self::getTestModuleDirectoryPath($prefix);
		}
		
		// If the modules path is not set, then there's nothing we can do here.
		// This should never happen, but Rob encountered a case where it did, likely due to initialize() being called too late.
		// The initialize() was moved up in a later commit, but we wanted to leave this line here just in case.
		if (empty(self::$MODULES_PATH)) return false;

		if(empty($version)){
			$version = self::getEnabledVersion($prefix);
			if($version === null){
				// This module is not enabled.
				return false;		
			}
		}
		else{
			// This may not be the most appropriate place/way to perform this escaping, but it should have not affect on valid values.
			$version = htmlentities($version, ENT_QUOTES);
		}

		// Look in the main modules dir and the example modules dir
		$directoryToFind = $prefix . '_' . $version;
		foreach(self::$MODULES_PATH as $pathDir) {
			$modulePath = $pathDir . $directoryToFind;
			if (is_dir($modulePath)) {
				// If the module was downloaded from the central repo and then deleted via UI and still was found in the server,
				// that means that load balancing is happening, so we need to delete the directory on this node too.
				if (self::wasModuleDeleted($modulePath) && !self::wasModuleDownloadedFromRepo($directoryToFind)) {
					// Delete the directory on this node
					self::deleteModuleDirectory($directoryToFind, true);
					// Return false since this module should not even be on the server
					return false;
				}
				// Return path
				return static::getSafePath($modulePath, $pathDir);
			}
		}
		// If module could not be found, it may be due to load balancing, so check if it was downloaded
		// from the central ext mod repository, and redownload it
		if (!defined("REPO_EXT_MOD_DOWNLOAD") && self::wasModuleDownloadedFromRepo($directoryToFind)) {
			$moduleId = self::getRepoModuleId($directoryToFind);
			if ($moduleId !== false && isDirWritable(dirname(APP_PATH_DOCROOT).DS.'modules'.DS)) { // Make sure "modules" directory is writable before attempting to auto-download this module
				try{
					if (self::downloadModule($moduleId, true) === 'success') {
						// Return the modules directory path
						return static::getSafePath($directoryToFind, dirname(APP_PATH_DOCROOT).DS.'modules');
					}
				}
				catch(\Throwable $t){
					$version = self::getModuleVersionByPrefix($prefix);
					if($version === null){
						/**
						 * This module has already been disabled,
						 * likely by the following disable() call
						 * on a previous hook during this same request.
						 */
					}
					else{
						// Disable the module to prevent a download from being attempted on every request, and notify the admin.
						self::disable($prefix, true);
			
						self::handleError(
							self::tt('em_errors_16', $prefix),
							self::tt('em_errors_154', $prefix, $version, $moduleId) . $t->__toString(),
							$prefix
						);
					}
				}
			}
		}		
		// Still could not find it, so return false
		return false;
	}

	/**
	 * @return string
	 *
	 * @param string $prefix
	 */
	static function getModuleDirectoryUrl($prefix, $version = null)
	{
		$filePath = ExternalModules::getModuleDirectoryPath($prefix, $version);
		
		$url = APP_PATH_WEBROOT_FULL . str_replace("\\", "/", substr($filePath, strlen(dirname(APP_PATH_DOCROOT)."/"))) . "/";

		return $url;
	}

	/**
	 * @return bool
	 *
	 * @param (int|string)|null $key
	 * @param string $moduleDirectoryPrefix
	 */
	static function hasProjectSettingSavePermission($moduleDirectoryPrefix, $key = null)
	{
		if(static::isTesting() || static::isSuperUser()){
			return true;
		}

		$settingDetails = self::getSettingDetails($moduleDirectoryPrefix, $key);
		if($settingDetails['super-users-only'] ?? null){
			return false;
		}
		
		$moduleRequiresConfigUserRights = self::moduleRequiresConfigPermission($moduleDirectoryPrefix);
		$userCanConfigureModule = ((!$moduleRequiresConfigUserRights && self::hasDesignRights()) 
									|| ($moduleRequiresConfigUserRights && self::hasModuleConfigurationUserRights($moduleDirectoryPrefix)));

		if($userCanConfigureModule){
			if(!self::isSystemSetting($moduleDirectoryPrefix, $key)){
				return true;
			}

			$level = self::getSystemSetting($moduleDirectoryPrefix, $key . self::OVERRIDE_PERMISSION_LEVEL_SUFFIX);
			return $level == self::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS;
		}

		return false;
	}

	/**
	 * @return bool
	 *
	 * @param string $permissionName
	 */
	public static function hasPermission($prefix, $version, $permissionName)
	{
		$config = self::getConfig($prefix, $version);
		$permissions = $config['permissions'] ?? [];
		$frameworkVersion = static::getFrameworkVersionForPrefix($prefix, $version);
		if($frameworkVersion >= static::PERMISSIONS_REMOVED_FRAMEWORK_VERSION){
			if(!empty($permissions)){
				throw new \Exception(static::tt('em_errors_172', $prefix));
			}

			return true;
		}

		return in_array($permissionName, $permissions);
	}

	/**
	 * @return bool
	 *
	 * @param string $key
	 * @param string $moduleDirectoryPrefix
	 */
	static function isSystemSetting($moduleDirectoryPrefix, $key)
	{
		$config = self::getConfig($moduleDirectoryPrefix);

		$returnValue = false;
		static::walkSubSettings($config['system-settings'], function($setting) use ($key, &$returnValue){
			if(($setting['key'] ?? null) === $key){
				$returnValue = true;
			}

			return $setting;
		});

		return $returnValue;
	}

	/**
	 * @param string $prefix
	 * @param int|null|string $key
	 */
	static function getSettingDetails($prefix, $key)
	{
		$config = self::getConfig($prefix);

		$settingGroups = [
			$config['system-settings'] ?? null,
			$config['project-settings'] ?? null,

			// The following was added so that the recreateAllEDocs() function would work on Email Alerts module settings.
			// Adding module specific code in the framework is not a good idea, but the fixing the duplicate edocs issue
			// for the Email Alerts module seemed like the right think to do since it's so popular.
			$config['email-dashboard-settings'] ?? null
		];

		$handleSettingGroup = function($group) use ($key, &$handleSettingGroup){
			if($group === null){
				return null;
			}

			foreach($group as $details){
				if(($details['key'] ?? null) == $key){
					return $details;
				}
				else if(($details['type'] ?? null) === 'sub_settings'){
					$returnValue = $handleSettingGroup($details['sub_settings']);
					if($returnValue){
						return $returnValue;
					}
				}
			}

			return null;
		};

		foreach($settingGroups as $group){
			$returnValue = $handleSettingGroup($group);
			if($returnValue){
				return $returnValue;
			}
		}

		return null;
	}

	/**
	 * @param null|numeric-string $project_ids
	 */
	static function getUserRights($project_ids = null, $username = null){
		if($project_ids === null){
			$project_ids = self::requireProjectId();
		}

		if(!is_array($project_ids)){
			$project_ids = [$project_ids];
		}

		if($username === null){
			$username = self::getUsername();
		}

		$rightsByPid = [];
		foreach($project_ids as $project_id){
			$rights = \UserRights::getPrivileges($project_id, $username);
			$rightsByPid[$project_id] = $rights[$project_id][$username] ?? null;
		}

		if(count($project_ids) === 1){
			return $rightsByPid[$project_ids[0]];
		}
		else{
			return $rightsByPid;
		}
	}

	# returns boolean if design rights are given by REDCap for current user
	/**
	 * @return bool
	 *
	 * @param null|numeric-string $pid
	 */
	static function hasDesignRights($pid = null)
	{
		if(self::isSuperUser() || ($pid === null && self::isAdminWithModuleInstallPrivileges())){
			return true;
		}

		if($pid === null){
			$pid = self::getProjectId();
			if($pid === null){
				return false;
			}
		}

		$rights = self::getUserRights($pid);
		return ($rights['design'] ?? null) == 1;
	}

	/**
	 * @return void
	 *
	 * @param null|numeric-string $pid
	 */
	static function requireDesignRights($pid = null){
		if(!self::hasDesignRights($pid)){
			// TODO - tt
			throw new Exception("You must have design rights in order to perform this action!");
		}
	}

	# returns boolean if current user explicitly has project-level user rights to configure a module 
	# (assuming it requires explicit privileges based on system-level configuration of module)
	/**
	 * @return bool
	 *
	 * @param null|string $prefix
	 */
	static function hasModuleConfigurationUserRights($prefix=null)
	{
		// We don't need a NOAUTH check here because this method should never be called in a NOAUTH context.

		if(ExternalModules::isSuperUser()){
			return true;
		}

		if(self::getProjectId() === null){
			// REDCap::getUserRights() will crash if no pid is set, so just return false.
			return false;
		}

		$rights = \REDCap::getUserRights();
		
		$module_config = $rights[self::getUsername()]['external_module_config'] ?? [];
        	if (!is_array($module_config)) $module_config = [];

        	return in_array($prefix, $module_config);
	}

	/**
	 * @return bool
	 */
	static function hasSystemSettingsSavePermission()
	{
		return self::isTesting() || self::isSuperUser() || self::isAdminWithModuleInstallPrivileges() || self::$disablingModuleDueToException;
	}

	# there is no getInstance because settings returns an array of repeated elements
	# getInstance would merely consist of dereferencing the array; Ockham's razor

	# sets the instance to a JSON string into the database
	# $instance is 0-based index for array
	# if the old value is a number/string, etc., this function will transform it into a JSON
	# fills is with null values for non-expressed positions in the JSON before instance
	# JSON is a 0-based, one-dimensional array. It can be filled with associative arrays in
	# the form of other JSON-encoded strings.
	# This method is currently used in the Selective Email module (so don't remove it).
	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	static function setInstance($prefix, $projectId, $key, $instance, $value) {
		$instance = (int) $instance;
		$oldValue = self::getSetting($prefix, $projectId, $key);
		$json = array();
		if (gettype($oldValue) != "array") {
			if ($oldValue !== null) {
				$json[] = $oldValue;
			}
		}

		# fill in with prior values
		for ($i=count($json); $i < $instance; $i++) {
			if ((gettype($oldValue) == "array") && (count($oldValue) > $i)) {
				$json[$i] = $oldValue[$i];
			} else {
				# pad with null for prior values when $n is ahead; should never be used
				$json[$i] = null;
			}
		}

		# do not set null values for current instance; always set to empty string
		if ($value !== null) {
			$json[$instance] = $value;
		} else {
			$json[$instance] = "";
		}

		# fill in remainder if extant
		if (gettype($oldValue) == "array") {
			for ($i = $instance + 1; $i < count($oldValue); $i++) {
				$json[$i] = $oldValue[$i];
			}
		}

		#single-element JSONs are simply data values
		if (count($json) == 1) {
			self::setSetting($prefix, $projectId, $key, $json[0]);
		} else {
			self::setSetting($prefix, $projectId, $key, $json);
		}
	}

	/**
	 * @return string
	 */
	static function getManagerJSDirectory() {
		return "js/";
		# just in case absolute path is needed, I have documented it here
		// return APP_PATH_WEBROOT_PARENT."/external_modules/manager/js/";
	}

	/**
	 * @return void
	 *
	 * @param numeric $edocId
	 */
	public static function deleteEDoc($edocId){
		// Prevent SQL injection
		$edocId = intval($edocId);

		if(!$edocId){
			//= The EDoc ID specified is not valid: {0}
			throw new Exception(self::tt("em_errors_52", $edocId)); 
		}

		# flag for deletion in the edocs database
		$sql = "UPDATE `redcap_edocs_metadata`
				SET `delete_date` = NOW()
				WHERE doc_id = ?";

		self::query($sql, [$edocId]);
	}
	
	// Display alert message in Control Center if any modules have updates in the REDCap Repo
	public static function renderREDCapRepoUpdatesAlert()
	{
		// Uncomment the following to quickly test updates without having to rerun the cron.
		// (new \Jobs)->CheckREDCapRepoUpdates();

		if(!ExternalModules::isAdminWithModuleInstallPrivileges()){
			return;
		}

		if(!ExternalModules::haveUnsafeEDocReferencesBeenChecked()) {
			?>
			<div class='yellow repo-updates'>
				<b>WARNING:</b> Unsafe references exist to files uploaded for modules. See <a href="<?=APP_URL_EXTMOD_RELATIVE?>manager/show-duplicated-edocs.php">this page</a> for more details.
			</div>
			<?php
		}

		global $lang, $external_modules_updates_available;
		$moduleUpdates = json_decode($external_modules_updates_available, true);
		if (!is_array($moduleUpdates) || empty($moduleUpdates)) return;
		$links = "";
		$moduleData = array();
		$countModuleUpdates = 0;
		foreach ($moduleUpdates as $id=>$module) {
			$prefix = $module['name'];
			try{
				$config = ExternalModules::getConfig($prefix);
			}
			catch(Throwable $e){
				/**
				 * Ignore errors and let the page load.
				 * 
				 * To date we've only seen one instance of an Exception here per the following post:
				 * https://redcap.vumc.org/community/post.php?id=100956
				 */
				self::errorLog("The following exception occurred in " . __FUNCTION__ . ': ' . $e->__toString());
				continue;
			}

			if(empty($config)){
				// This module must have been deleted while it was still enabled.
				// Do not show updates for it.
				// This may only happen in the edge case where a module is manually installed that also happens to have an update in the Repo.
				// Modules that were installed using the Repo (and were still enabled) would have been automatically re-downloaded, avoiding this issue.
				continue;
			}

			$moduleData[] = $thisModuleData = "{$id},{$module['name']},v{$module['version']}";
			$links .= "<div id='repo-updates-modid-$id'><button class='update-single-module btn btn-success btn-xs' data-module-info=\"$thisModuleData\">"
				   .  "<span class='fas fa-download'></span> {$lang['global_125']}</button> {$module['title']} v{$module['version']}</div>";

			$countModuleUpdates++;
		}
		if ($countModuleUpdates === 0) return;
		$moduleData = implode(";", $moduleData);
		self::initializeJSGlobals();
		self::tt_initializeJSLanguageStore();
		self::tt_transferToJSLanguageStore(array(
			"em_manage_27",
			"em_manage_68",
			"em_manage_79",
			"em_manage_80",
			"em_manage_81",
			"em_manage_82",
		));
		self::addResource(ExternalModules::getManagerJSDirectory().'update-modules.js');
		print  "<div class='yellow repo-updates'>
					<div style='color:#A00000;'>
						<i class='fas fa-bell'></i> <span style='margin-left:3px;font-weight:bold;'>
						<span id='repo-updates-count'>$countModuleUpdates</span> " .
						//= External Module/s has/have updates available for download from the REDCap Repo.
						// An empty parameter is passed below because the language string used to have a parameter,
						// and it was easier than requiring people to update their translations.
						self::tt($countModuleUpdates == 1 ? "em_manage_1" : "em_manage_2", '') .
						" <button onclick=\"$(this).hide();$('.repo-updates-list').show();\" class='btn btn-danger btn-xs ml-2'>" .
						self::tt("em_manage_3") . //= View updates
						"</a>
					</div>
					<div class='repo-updates-list'>" . 
						self::tt("em_manage_4") . //= Updates are available for the modules listed below. You may click the button(s) to upgrade them all at once or individually. 
						"<div class='mt-3 mb-4'>
							<button id='update-all-modules' class='btn btn-primary btn-sm' data-module-info=\"$moduleData\"><span class='fas fa-download'></span> " .
							self::tt("em_manage_5") . //= Update All
							"</button>
						</div>
						$links
					</div>
				</div>";
	}
	
	// Store any json-encoded module updates passed in the URL from the REDCap Repo
	/**
	 * @param bool $redirect
	 */
	public static function storeREDCapRepoUpdatesInConfig($json="", $redirect=false)
	{
		if (!function_exists('updateConfig')) return false;
		if (empty($json)) return false;
		$json = rawurldecode(urldecode($json));
		$moduleUpdates = json_decode($json, true);
		if (!is_array($moduleUpdates)) return false;
		static::setUpdatesAvailable($json);
		if ($redirect) redirect(APP_URL_EXTMOD_RELATIVE."manager/control_center.php");
	}

	private static function setUpdatesAvailable($updates)
	{
		if(is_array($updates)){
			$updates = json_encode($updates);
		}

		updateConfig('external_modules_updates_available', $updates);
		updateConfig('external_modules_updates_available_last_check', NOW);
	}
	
	/**
	 * Remove a specific module from the JSON-encoded REDCap Repo updates config variable
	 */
	public static function removeModuleFromREDCapRepoUpdatesInConfig($prefix, $version)
	{
		global $external_modules_updates_available;

		$version = ltrim($version, 'v');

		$updates = json_decode($external_modules_updates_available, true);
		if (!is_array($updates)) return false;
		foreach($updates as $repoId=>$update){
			if($update['name'] === $prefix && $update['version'] === $version){
				unset($updates[$repoId]);
				static::setUpdatesAvailable($updates);
				return;
			}
		}
	}

	private static function parseDirectoryNameResponse($response, $module_id){
		$responseArray = json_decode($response, true) ?? [];
		$moduleFolderName = $responseArray['module_directory_name'] ?? null;
		if(empty($moduleFolderName)){
			//= The request to retrieve the name for module {0} from the repo failed: {1}.
			throw new Exception(self::tt("em_errors_165", 
				$module_id, 
				$response)); 
		}

		return $moduleFolderName;
	}

	/**
	 * @return int|string
	 *
	 * @param bool $bypass
	 * @param bool $sendUserInfo
	 */
	public static function downloadModule($module_id=null, $bypass=false, $sendUserInfo=false){
		// Ensure user has privileges to install/update modules
		if (!$bypass && !self::isAdminWithModuleInstallPrivileges()) {
		    return 'You do not have permission to download modules.';
		}
		// Set modules directory path
		$modulesDir = dirname(APP_PATH_DOCROOT).DS.'modules'.DS;
		// Validate module_id
		if (empty($module_id) || !is_numeric($module_id)) return "Invalid module ID specified.";
		$module_id = (int)$module_id;
		// Also obtain the folder name of the module
		$response = http_get(APP_URL_EXTMOD_LIB . "download.php?module_id=$module_id&name=1&returnFormat=json");
		$moduleFolderName = static::parseDirectoryNameResponse($response, $module_id);

		$moduleFolderDir = $modulesDir . $moduleFolderName . DS;
		if(file_exists($moduleFolderDir)){
			// The module has already been downloaded
			return 'success';
		}

		// The following concurrent download detect was added to prevent a download/delete loop that we believe
		// brought the production server & specific modules down a few times:
		// https://github.com/vanderbilt/redcap-external-modules/issues/136
		$tempDir = $modulesDir . $moduleFolderName . '_tmp';
		if(file_exists($tempDir)){
			if(filemtime($tempDir) < time()-30){
				// The last download process likely failed.  Removed the folder and try again.
				self::removeModuleDirectory($tempDir);
			}
		}

		if(!mkdir($tempDir)){
			// Another process just created this directory and is actively downloading the module.
			// Simply tell the user to retry if this request came from the UI.
			return 'A duplicate request was detected.  Please wait a minute, then refresh the page to see if this module has been downloaded.  If it has not, try again.';
		}

		try{
			// The temp dir was created successfully.  Open a `try` block so we can ensure it gets removed in the `finally`.

			$logDescription = "Download external module \"$moduleFolderName\" from repository";
			// This event must be allowed twice within any time frame (once for each webserver node at Vandy as of this writing).
			// The time frame is semi-arbitrary and is meant to catch the scenarios documented here:
			// https://github.com/vanderbilt/redcap-external-modules/issues/136
			// Even if #136 is completely solved, we should leave this in place to ensure possible future issues are immediately detected.
			self::throttleEvent($logDescription, 2, 3);
			\REDCap::logEvent($logDescription);

			// Send user info?
			if ($sendUserInfo) {
				$postParams = array('user'=>self::getUsername(), 'name'=>$GLOBALS['user_firstname']." ".$GLOBALS['user_lastname'], 
									'email'=>$GLOBALS['user_email'], 'institution'=>$GLOBALS['institution'], 'server'=>SERVER_NAME);
			} else {
				$postParams = array('institution'=>$GLOBALS['institution'], 'server'=>SERVER_NAME);
			}
			/**
			 * Call the module download service to download the module zip.
			 * We proxy downloads through our server since some REDCap servers might have
			 * firewall restrictions that could prevent them from accessing github directly.
			 */
			$moduleZipContents = http_post(APP_URL_EXTMOD_LIB . "download.php?module_id=$module_id", $postParams);
			// Errors?
			if (strlen($moduleZipContents) < 2000) {
				/**
				 * The response is too short to be a ZIP file.
				 * It must be an error message.
				 */
				if ($moduleZipContents == 'ERROR') {
					return 'This module could not be found in the Repo.';
				}
				else{
					return $moduleZipContents;
				}
			}

			// Place the file in the temp directory before extracting it
			$filename = APP_PATH_TEMP . date('YmdHis') . "_externalmodule_" . substr(sha1(rand()), 0, 6) . ".zip";
			if (file_put_contents($filename, $moduleZipContents) === false) {
				return "Error saving the module zip file";
			}
			// Extract the module to /redcap/modules
			$zip = new \ZipArchive;
			if ($zip->open($filename) !== TRUE) {
				return "Opening the zip file failed";
			}
			// First, we need to rename the parent folder in the zip because GitHub has it as something else
			self::normalizeModuleZip($moduleFolderName, $zip);
			$zip->close();
			// Now extract the zip to the modules folder
			$zip = new \ZipArchive;
			if (!$zip->open($filename)) {
				return "Opening the zip file the second time failed";
			}

			if(!$zip->extractTo($tempDir)){
				return 'Extracting the zip failed';
			}

			$zip->close();

			// Remove temp file
			unlink($filename);

			static::removeEditorDirectories($tempDir.DS.$moduleFolderName);

			// Move the extracted directory to it's final location
			if(!rename($tempDir.DS.$moduleFolderName, $moduleFolderDir)){
				return 'Error renaming module directory';
			};

			// Now double check that the new module directory got created
			if (!(file_exists($moduleFolderDir) && is_dir($moduleFolderDir))) {
				return "The module directory did not get created as expected.";
			}
			// Add row to redcap_external_modules_downloads table
			$sql = "insert into redcap_external_modules_downloads (module_name, module_id, time_downloaded) 
					values (?, ?, ?)
					on duplicate key update 
					module_id = ?, time_downloaded = ?, time_deleted = null";
			ExternalModules::query($sql, [$moduleFolderName, $module_id, NOW, $module_id, NOW]);

			return 'success';
		}
		finally{
			self::removeModuleDirectory($tempDir);
		}
	}

	/**
	 * @return void
	 *
	 * @param string $moduleDir
	 */
	public static function removeEditorDirectories($moduleDir){
		foreach(static::EDITOR_DIRECTORIES as $subDir){
			static::rrmdir($moduleDir.DS.$subDir);
		}
	}

	/**
	 * @return void
	 *
	 * @param \ZipArchive $zip
	 */
	public static function normalizeModuleZip($moduleFolderName, $zip){
		$containingFolder = true;
		for($i=0; $i<$zip->numFiles; $i++){
			$path = $zip->getNameIndex($i);
			if($path === 'config.json'){
				$containingFolder = false;
			}
		}

		$i = 0;
		while ($item_name = $zip->getNameIndex($i)){
			if($containingFolder){
				// Rename the containing folder.
				// This will do odd things with other files in the zip, like __MACOSX,
				// but that won't matter because only the $moduleFolderName will be copied from the temp folder.
				$item_name_end = substr($item_name, strpos($item_name, "/"));
			}
			else{
				// We have multiple root items.  Move them all into a new containing folder.
				$item_name_end = "/$item_name";
			}

			$zip->renameIndex($i++, $moduleFolderName . $item_name_end);
		}
	}

	/**
	 * @return string
	 *
	 * @param null|string $moduleFolderName
	 * @param bool $bypass
	 */
	public static function deleteModuleDirectory($moduleFolderName=null, $bypass=false){
		$logDescription = "Delete external module \"$moduleFolderName\" from system";
		// This event must be allowed twice within any time frame (once for each webserver node at Vandy as of this writing).
		// The time frame is semi-arbitrary and is meant to catch the scenarios documented here:
		// https://github.com/vanderbilt/redcap-external-modules/issues/136
		// Even if #136 is completely solved, we should leave this in place to ensure possible future issues are immediately detected.
		self::throttleEvent($logDescription, 2, 15);
		\REDCap::logEvent($logDescription);

		if(empty($moduleFolderName)){
			// Prevent the entire modules directory from being deleted.
			//= You must specify a module to delete!
			throw new Exception(self::tt("em_errors_54")); 
		}

		// Ensure user can install, update, configure, and delete modules
		if (!$bypass && !self::isAdminWithModuleInstallPrivileges()) {
		    return "0";
		}
		// Set modules directory path
		$modulesDir = dirname(APP_PATH_DOCROOT).DS.'modules'.DS;
		// First see if the module directory already exists
		$moduleFolderDir = $modulesDir . $moduleFolderName . DS;
		if (!(file_exists($moduleFolderDir) && is_dir($moduleFolderDir))) {
		   return "1";
		}
		// Delete the directory
		self::removeModuleDirectory($moduleFolderDir);
		// Return error if not deleted
		if (file_exists($moduleFolderDir) && is_dir($moduleFolderDir)) {
		   return "0";
		}
		// Add to deleted modules array
		self::$deletedModules[basename($moduleFolderDir)] = time();
		
		$sql = "update redcap_external_modules_downloads set time_deleted = ? 
				where module_name = ?";
		ExternalModules::query($sql, [NOW, $moduleFolderName]);

		// Give success message
		//= The module and its corresponding directory were successfully deleted from the REDCap server.
		return self::tt("em_manage_7"); 
	}

	# Was this module originally downloaded from the central repository of ext mods? Exclude it if the module has already been marked as deleted via the UI.
	/**
	 * @return bool
	 *
	 * @param null|string $moduleFolderName
	 */
	private static function wasModuleDownloadedFromRepo($moduleFolderName=null){
		$sql = "select 1 from redcap_external_modules_downloads 
				where module_name = ? and time_deleted is null";
		$q = ExternalModules::query($sql, [$moduleFolderName]);
		return ($q->num_rows > 0);
	}

	# Was this module, which was downloaded from the central repository of ext mods, deleted via the UI?
	/**
	 * @return bool
	 *
	 * @param string $modulePath
	 */
	private static function wasModuleDeleted($modulePath){
		$moduleFolderName = basename($modulePath);

		$deletionTimesByFolderName = self::getDeletedModules();
		$deletionTime = $deletionTimesByFolderName[$moduleFolderName] ?? null;

		if($deletionTime !== null){
			if($deletionTime > filemtime($modulePath)){
				return true;
			}
			else{
				// The directory was re-created AFTER deletion.
				// This likely means a developer recreated the directory manually via git clone instead of using the REDCap Repo to download the module.
				// We should remove this row from the module downloads table since this module is no longer managed via the REDCap Rep.
				self::query("delete from redcap_external_modules_downloads where module_name = ?", [$moduleFolderName]);
			}
		}

		return false;
	}
	
	# Obtain array of all DELETED modules (deleted via UI) that were originally downloaded from the REDCap Repo.
	private static function getDeletedModules(){
		if(!isset(self::$deletedModules)){
			$sql = "select module_name, time_deleted from redcap_external_modules_downloads 
					where time_deleted is not null";
			$q = self::query($sql, []);
			self::$deletedModules = array();
			while ($row = $q->fetch_assoc()) {
				self::$deletedModules[$row['module_name']] = strtotime($row['time_deleted']);
			}
		}
		return self::$deletedModules;
	}

	# If module was originally downloaded from the central repository of ext mods,
	# then return its module_id (from the repo)
	/**
	 * @param null|string $moduleFolderName
	 */
	public static function getRepoModuleId($moduleFolderName=null){
		$sql = "select cast(module_id as char) as module_id from redcap_external_modules_downloads where module_name = ?";
		$q = self::query($sql, [$moduleFolderName]);
		return ($q->num_rows > 0 ? $q->fetch_row()[0] : false);
	}
	
	/**
	 * @return void
	 *
	 * @param string $path
	 */
	private static function removeModuleDirectory($path){
		$modulesDir = dirname(APP_PATH_DOCROOT).DS.'modules'.DS;
		$path = self::getSafePath($path, $modulesDir);
		self::rrmdir($path);
	}
	
	# general method to delete a directory by first deleting all files inside it
	# Modified from https://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
	/**
	 * @return void
	 *
	 * @param string $dir
	 */
	static function rrmdir($dir)
	{
		if(is_file($dir) || is_link($dir)){
			// We might as well let this function work on those too.
			unlink($dir);
			return;
		}

		if(!file_exists($dir)){
			return;
		}

		$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it,
					 RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->isDir() && !$file->isLink()){
				if(!rmdir($file->getPathname())){
					static::errorLog('EM Framework could not delete directory: ' . $file->getPathname());
				}
			} else {
				if(!unlink($file->getPathname())){
					static::errorLog('EM Framework could not delete file: ' . $file->getPathname());
				}
			}
		}
		rmdir($dir);
	}
	
	// Return array of module dir prefixes for modules with a system-level value of TRUE for discoverable-in-project
	/**
	 * @return array
	 */
	public static function getDiscoverableModules()
	{
		$modules = array();
		$sql = "select m.directory_prefix, x.`value` from redcap_external_modules m, 
				redcap_external_module_settings s, redcap_external_module_settings x
				where m.external_module_id = s.external_module_id and s.project_id is null
				and s.`value` = 'true' and s.`key` = ?
                and m.external_module_id = x.external_module_id and x.project_id is null
				and x.`key` = ?";
		$q = ExternalModules::query($sql, [self::KEY_DISCOVERABLE, self::KEY_VERSION]);
		while ($row = $q->fetch_assoc()) {
			$modules[$row['directory_prefix']] = $row['value'];
		}
		return $modules;
	}
	
	// Return boolean if any projects have a system-level value of TRUE for discoverable-in-project
	/**
	 * @return bool
	 */
	public static function hasDiscoverableModules()
	{
		$modules = self::getDiscoverableModules();
		return !empty($modules);
	}

	# Return array all all module prefixes where the module requires that regular users have project-level 
	# permissions in order to configure it for the project. First provide an array of dir prefixes that you want to check.
	/**
	 * @return array
	 *
	 * @param ((int|string)|mixed)[] $prefixes
	 */
	public static function getModulesRequireConfigPermission($prefixes=array())
	{
		$modules = array();
		if (empty($prefixes)) return $modules;
		$query = ExternalModules::createQuery();
		$query->add("
			SELECT m.directory_prefix FROM redcap_external_modules m, redcap_external_module_settings s 
			WHERE m.external_module_id = s.external_module_id AND s.value = 'true'
			AND s.`key` = ?
		", [self::KEY_CONFIG_USER_PERMISSION]);
		
		$query->add('AND')->addInClause('directory_prefix', $prefixes);

		$q = $query->execute();
		while ($row = $q->fetch_assoc()) {
			$modules[] = $row['directory_prefix'];
		}
		return $modules;
	}
	
	# Return boolean if module requires that regular users have project-level 
	# permissions in order to configure it for the project.
	/**
	 * @return bool
	 *
	 * @param null|string $prefix
	 */
	public static function moduleRequiresConfigPermission($prefix=null)
	{
		$module = self::getModulesRequireConfigPermission(array($prefix));
		return !empty($module);
	}
	
	# Return array all all modules enabled in a project where the module requires that regular users have project-level 
	# permissions in order to configure it for the project. Array also contains module title.
	/**
	 * Called from REDCap core
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return (int|string)[][]
	 */
	public static function getModulesWithCustomUserRights($project_id=null)
	{
		// Place modules into an array
		$modulesAttributes = $titles = array();
		// Get modules enabled for this project
		$enabledModules = self::getEnabledModules($project_id);
		// Of the enabled projects, find those that require user permissions to configure in project
		$enabledModulesReqConfigPerm = self::getModulesRequireConfigPermission(array_keys($enabledModules));
		// Obtain the title of each module from its config
		foreach (array_keys($enabledModules) as $thisModule) {
			$config = self::getConfig($thisModule, null, $project_id, true); // Need translated config for names.
			if (!isset($config['name'])) continue;
			// Add attributes to array
			$title = trim(strip_tags($config['name']));
			$modulesAttributes[$thisModule] = array('name'=>$title, 
													'has-project-config'=>((isset($config['project-settings']) && !empty($config['project-settings'])) ? 1 : 0), 
													'require-config-perm'=>(in_array($thisModule, $enabledModulesReqConfigPerm) ? 1 : 0));
			// Add uppercase title to another array so we can sort by title
			$titles[] = strtoupper($title);
		}
		// Sort modules by title
		array_multisort($titles, SORT_REGULAR, $modulesAttributes);
		// Return modules with attributes
		return $modulesAttributes;
	}

	/**
	 * @param string $prefix
	 */
	public static function getDocumentationUrl($prefix)
	{
		$config = self::getConfig($prefix, null, null, true); // Documentation could be translated.
		$documentation = $config['documentation'] ?? null;
		if(filter_var($documentation, FILTER_VALIDATE_URL)){
			return $documentation;
		}

		if(empty($documentation)){
			$documentation = self::detectDocumentationFilename($prefix);
		}

		if(is_file(self::getModuleDirectoryPath($prefix) . "/$documentation")){
			return ExternalModules::getUrl($prefix, $documentation);
		}

		return null;
	}

	/**
	 * @return null|string
	 *
	 * @param string $prefix
	 */
	private static function detectDocumentationFilename($prefix)
	{
		foreach(glob(self::getModuleDirectoryPath($prefix) . '/*') as $path){
			$filename = basename($path);
			$lowercaseFilename = strtolower($filename);
			if(strpos($lowercaseFilename, 'readme.') === 0){
				return $filename;
			}
		}

		return null;
	}

	private static function getDatacoreEmails($to = []){
		if (self::isVanderbilt()) {
			$to[] = 'redcap-external-module-framework@vumc.org';

			if (self::$SERVER_NAME == 'redcap.vumc.org') {
				$to[] = static::DATACORE_EMAIL;
			}
		}

		return $to;
	}

	// This method is deprecated, but is still used in a couple of modules at Vandy.
	// We should likely refactor those modules to use sendAdminEmail() instead, then remove this method.
	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	public static function sendErrorEmail($email_error,$subject,$body){
		$from = \Message::useDoNotReply($GLOBALS['project_contact_email']);

		if (is_array($email_error)) {
			$emails = preg_split("/[;,]+/", $email_error);
			foreach ($emails as $to) {
				\REDCap::email($to, $from, $subject, $body);
			}
		} else if ($email_error) {
			\REDCap::email($email_error, $from, $subject, $body);
		} else if($email_error == ""){
			$emails = self::getDatacoreEmails();
			foreach ($emails as $to){
				\REDCap::email($to, $from, $subject, $body);
			}
		}
	}

	/**
	 * @return null|string
	 *
	 * @param string $extension
	 */
	public static function getContentType($extension)
	{
		$extension = strtolower($extension);

		// The following list came from https://gist.github.com/raphael-riel/1253986
		$types = array(
		    'ai'      => 'application/postscript',
		    'aif'     => 'audio/x-aiff',
		    'aifc'    => 'audio/x-aiff',
		    'aiff'    => 'audio/x-aiff',
		    'asc'     => 'text/plain',
		    'atom'    => 'application/atom+xml',
		    'au'      => 'audio/basic',
		    'avi'     => 'video/x-msvideo',
		    'bcpio'   => 'application/x-bcpio',
		    'bin'     => 'application/octet-stream',
		    'bmp'     => 'image/bmp',
		    'cdf'     => 'application/x-netcdf',
		    'cgm'     => 'image/cgm',
		    'class'   => 'application/octet-stream',
		    'cpio'    => 'application/x-cpio',
		    'cpt'     => 'application/mac-compactpro',
		    'csh'     => 'application/x-csh',
		    'css'     => 'text/css',
		    'csv'     => 'text/csv',
		    'dcr'     => 'application/x-director',
		    'dir'     => 'application/x-director',
		    'djv'     => 'image/vnd.djvu',
		    'djvu'    => 'image/vnd.djvu',
		    'dll'     => 'application/octet-stream',
		    'dmg'     => 'application/octet-stream',
		    'dms'     => 'application/octet-stream',
		    'doc'     => 'application/msword',
		    'dtd'     => 'application/xml-dtd',
		    'dvi'     => 'application/x-dvi',
		    'dxr'     => 'application/x-director',
		    'eps'     => 'application/postscript',
		    'etx'     => 'text/x-setext',
		    'exe'     => 'application/octet-stream',
		    'ez'      => 'application/andrew-inset',
		    'gif'     => 'image/gif',
		    'gram'    => 'application/srgs',
		    'grxml'   => 'application/srgs+xml',
		    'gtar'    => 'application/x-gtar',
		    'hdf'     => 'application/x-hdf',
		    'hqx'     => 'application/mac-binhex40',
		    'htm'     => 'text/html',
		    'html'    => 'text/html',
		    'ice'     => 'x-conference/x-cooltalk',
		    'ico'     => 'image/x-icon',
		    'ics'     => 'text/calendar',
		    'ief'     => 'image/ief',
		    'ifb'     => 'text/calendar',
		    'iges'    => 'model/iges',
		    'igs'     => 'model/iges',
		    'jpe'     => 'image/jpeg',
		    'jpeg'    => 'image/jpeg',
		    'jpg'     => 'image/jpeg',
		    'js'      => 'application/x-javascript',
		    'json'    => 'application/json',
		    'kar'     => 'audio/midi',
		    'latex'   => 'application/x-latex',
		    'lha'     => 'application/octet-stream',
		    'lzh'     => 'application/octet-stream',
		    'm3u'     => 'audio/x-mpegurl',
		    'man'     => 'application/x-troff-man',
		    'mathml'  => 'application/mathml+xml',
		    'me'      => 'application/x-troff-me',
		    'mesh'    => 'model/mesh',
		    'mid'     => 'audio/midi',
		    'midi'    => 'audio/midi',
		    'mif'     => 'application/vnd.mif',
		    'mov'     => 'video/quicktime',
		    'movie'   => 'video/x-sgi-movie',
		    'mp2'     => 'audio/mpeg',
		    'mp3'     => 'audio/mpeg',
		    'mpe'     => 'video/mpeg',
		    'mpeg'    => 'video/mpeg',
		    'mpg'     => 'video/mpeg',
		    'mpga'    => 'audio/mpeg',
		    'ms'      => 'application/x-troff-ms',
		    'msh'     => 'model/mesh',
		    'mxu'     => 'video/vnd.mpegurl',
		    'nc'      => 'application/x-netcdf',
		    'oda'     => 'application/oda',
		    'ogg'     => 'application/ogg',
		    'pbm'     => 'image/x-portable-bitmap',
		    'pdb'     => 'chemical/x-pdb',
		    'pdf'     => 'application/pdf',
		    'pgm'     => 'image/x-portable-graymap',
		    'pgn'     => 'application/x-chess-pgn',
		    'png'     => 'image/png',
		    'pnm'     => 'image/x-portable-anymap',
		    'ppm'     => 'image/x-portable-pixmap',
		    'ppt'     => 'application/vnd.ms-powerpoint',
		    'ps'      => 'application/postscript',
		    'qt'      => 'video/quicktime',
		    'ra'      => 'audio/x-pn-realaudio',
		    'ram'     => 'audio/x-pn-realaudio',
		    'ras'     => 'image/x-cmu-raster',
		    'rdf'     => 'application/rdf+xml',
		    'rgb'     => 'image/x-rgb',
		    'rm'      => 'application/vnd.rn-realmedia',
		    'roff'    => 'application/x-troff',
		    'rss'     => 'application/rss+xml',
		    'rtf'     => 'text/rtf',
		    'rtx'     => 'text/richtext',
		    'sgm'     => 'text/sgml',
		    'sgml'    => 'text/sgml',
		    'sh'      => 'application/x-sh',
		    'shar'    => 'application/x-shar',
		    'silo'    => 'model/mesh',
		    'sit'     => 'application/x-stuffit',
		    'skd'     => 'application/x-koan',
		    'skm'     => 'application/x-koan',
		    'skp'     => 'application/x-koan',
		    'skt'     => 'application/x-koan',
		    'smi'     => 'application/smil',
		    'smil'    => 'application/smil',
		    'snd'     => 'audio/basic',
		    'so'      => 'application/octet-stream',
		    'spl'     => 'application/x-futuresplash',
		    'src'     => 'application/x-wais-source',
		    'sv4cpio' => 'application/x-sv4cpio',
		    'sv4crc'  => 'application/x-sv4crc',
		    'svg'     => 'image/svg+xml',
		    'svgz'    => 'image/svg+xml',
		    'swf'     => 'application/x-shockwave-flash',
		    't'       => 'application/x-troff',
		    'tar'     => 'application/x-tar',
		    'tcl'     => 'application/x-tcl',
		    'tex'     => 'application/x-tex',
		    'texi'    => 'application/x-texinfo',
		    'texinfo' => 'application/x-texinfo',
		    'tif'     => 'image/tiff',
		    'tiff'    => 'image/tiff',
		    'tr'      => 'application/x-troff',
		    'tsv'     => 'text/tab-separated-values',
		    'txt'     => 'text/plain',
		    'ustar'   => 'application/x-ustar',
		    'vcd'     => 'application/x-cdlink',
		    'vrml'    => 'model/vrml',
		    'vxml'    => 'application/voicexml+xml',
		    'wav'     => 'audio/x-wav',
		    'wbmp'    => 'image/vnd.wap.wbmp',
		    'wbxml'   => 'application/vnd.wap.wbxml',
		    'wml'     => 'text/vnd.wap.wml',
		    'wmlc'    => 'application/vnd.wap.wmlc',
		    'wmls'    => 'text/vnd.wap.wmlscript',
		    'wmlsc'   => 'application/vnd.wap.wmlscriptc',
		    'wrl'     => 'model/vrml',
		    'xbm'     => 'image/x-xbitmap',
		    'xht'     => 'application/xhtml+xml',
		    'xhtml'   => 'application/xhtml+xml',
		    'xls'     => 'application/vnd.ms-excel',
		    'xml'     => 'application/xml',
		    'xpm'     => 'image/x-xpixmap',
		    'xsl'     => 'application/xml',
		    'xslt'    => 'application/xslt+xml',
		    'xul'     => 'application/vnd.mozilla.xul+xml',
		    'xwd'     => 'image/x-xwindowdump',
		    'xyz'     => 'chemical/x-xyz',
		    'zip'     => 'application/zip'
		);

		return $types[$extension] ?? null;
	}

	public static function getUsername()
	{
		if (!empty(self::$USERNAME)) {
			return self::$USERNAME;
		}

		/**
		 * This 'if' statement was added because of this community post:
		 * https://redcap.vumc.org/community/post.php?id=229536
		 * 
		 * The briefly introduced MysqliInfiniteLoopDetector class was failing with
		 * an unknown error on some systems.  It must have been failing prior to initializing
		 * the class autoloader, which had the unfortunately side effect of causing the following
		 * error handling code to fail as well, obscuring the actual error.
		 * We might as well leave this in place in case any other errors ever occur prior to
		 * the class class autoloader being initiated.
		 */
		if(class_exists('UserRights')){
			$username = \UserRights::getUsernameImpersonating();
			if(!empty($username)){
				return $username;
			}
		}

		if (defined('USERID')) {
			return USERID;
		}

		return null;
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	public static function setUsername($username)
	{
		if (!self::isTesting()) {
			throw new Exception("This method can only be used in unit tests.");
		}

		self::$USERNAME = $username;
	}

	public static function getTemporaryRecordId()
	{
		return self::$temporaryRecordId;
	}

	/**
	 * @return void
	 *
	 * @param string $temporaryRecordId
	 */
	private static function setTemporaryRecordId($temporaryRecordId)
	{
		self::$temporaryRecordId = $temporaryRecordId;
	}

	/**
	 * @return void
	 */
	public static function sharedSurveyAndDataEntryActions($recordId)
	{
		if (empty($recordId) && (self::isSurveyPage() || self::isDataEntryPage())) {
			// We're creating a new record, but don't have an id yet.
			// We must create a temporary record id and include it in the form so it can be used to retroactively change logs to the actual record id once it exists.
			$temporaryRecordId = implode('-', [self::EXTERNAL_MODULES_TEMPORARY_RECORD_ID, time(), rand()]);
			self::setTemporaryRecordId($temporaryRecordId);
			?>
			<script>
				(function () {
					$('#form').append($('<input>').attr({
						type: 'hidden',
						name: <?=json_encode(ExternalModules::EXTERNAL_MODULES_TEMPORARY_RECORD_ID)?>,
						value: <?=json_encode($temporaryRecordId)?>
					}))
				})()
			</script>
			<?php
		}
	}

	/**
	 * @return bool
	 */
	public static function isTemporaryRecordId($recordId)
	{
		return strpos($recordId ?? '', self::EXTERNAL_MODULES_TEMPORARY_RECORD_ID) === 0;
	}

	/**
	 * @return bool
	 */
	public static function isSurveyPage()
	{
		$url = $_SERVER['REQUEST_URI'];

		return
			strpos($url, APP_PATH_SURVEY) === 0
			&&
			(
				// Prevent hooks from firing for survey logo URLs (and breaking them).
				strpos($url, '__passthru=DataEntry%2Fimage_view.php') === false
				&&
				// Prevent hooks from firing for rich text file uploads (and breaking them).
				strpos($url, '__passthru=Design%2Ffile_attachment_upload.php') === false
			)
		;
	}

	/**
	 * @return bool
	 */
	private static function isDataEntryPage()
	{
		return strpos($_SERVER['REQUEST_URI'], APP_PATH_WEBROOT . 'DataEntry') === 0;
	}

	# for crons specified to run at a specific time
	/**
	 * @return bool
	 *
	 * @param (mixed|string)[] $cronAttr
	 */
	public static function isValidTimedCron($cronAttr) {
		$hour = $cronAttr['cron_hour'] ?? null;
		$minute = $cronAttr['cron_minute'] ?? null;
		$weekday = $cronAttr['cron_weekday'] ?? null;
		$monthday = $cronAttr['cron_monthday'] ?? null;

		if (!self::isValidGenericCron($cronAttr)) {
			return FALSE;
		}

		if (!empty($cronAttr['cron_frequency']) || !empty($cronAttr['cron_max_run_time'])) {
			return FALSE;
		}

		if (!is_numeric($hour) || !is_numeric($minute)) {
			return FALSE;
		}
		if ($weekday && !is_numeric($weekday)) {
			return FALSE;
		}
		if ($monthday && !is_numeric($monthday)) {
			return FALSE;
		}

		if (($hour < 0) || ($hour >= 24)) {
			return FALSE;
		}
		if (($minute < 0) || ($minute >= 60)) { 
			return FALSE;
		}

		return TRUE;
	}

	# for all generic crons; all must have the following attributes
	/**
	 * @return bool
	 *
	 * @param (mixed|string)[] $cronAttr
	 */
	private static function isValidGenericCron($cronAttr) {
		$name = $cronAttr['cron_name'] ?? null;
		$descr = $cronAttr['cron_description'] ?? null;
		$method = $cronAttr['method'] ?? null;

		if (!isset($name) || !isset($descr) || !isset($method)) {
			return FALSE; 
		}

		return TRUE;
	}

	# only for crons stored in redcap_crons table
	/**
	 * @return bool
	 *
	 * @param (mixed|string)[] $cronAttr
	 */
	public static function isValidTabledCron($cronAttr) {
		$frequency = $cronAttr['cron_frequency'] ?? null;
		$maxRunTime = $cronAttr['cron_max_run_time'] ?? null;

		if (!self::isValidGenericCron($cronAttr)) {
			return FALSE;
		}

		if (!isset($frequency) || !isset($maxRunTime)) {
			return FALSE;
		}

		if (isset($cronAttr['cron_hour']) || isset($cronAttr['cron_minute'])) {
			return FALSE;
		}

		if (!is_numeric($frequency) || !is_numeric($maxRunTime)) {
			return FALSE;
		}

		if ($frequency <= 0) {
			return FALSE;
		}
		if ($maxRunTime <= 0) {
			return FALSE;
		}

		return TRUE;
	}

	# only for timed crons
	/**
	 * @return bool
	 *
	 * @param int|null $cronStartTime
	 */
	public static function isTimeToRun($cronAttr, $cronStartTime=NULL) {
		$hour = $cronAttr['cron_hour'] ?? null;
		$minute = $cronAttr['cron_minute'] ?? null;
		$weekday = $cronAttr['cron_weekday'] ?? null;
		$monthday = $cronAttr['cron_monthday'] ?? null;

		if(!self::isValidTimedCron($cronAttr)){
			return FALSE;
		}

		$hour = (int) $hour;
		$minute = (int) $minute;
		$weekday = (int) $weekday;
		$monthday = (int) $monthday;

		// We check the cron start time instead of the current time
		// in case another module's cron job ran us into the next minute.
		if (!$cronStartTime) {
			$cronStartTime = self::getLastTimeRun();
		}
		$currentHour = (int) date('G', $cronStartTime);
		$currentMinute = (int) date('i', $cronStartTime);  // The cast is especially important here to get rid of a possible leading zero.
		$currentWeekday = (int) date('w', $cronStartTime);
		$currentMonthday = (int) date('j', $cronStartTime);

		if (isset($cronAttr['cron_weekday'])) {
			if ($currentWeekday != $weekday) {
				return FALSE;
			}
		}

		if (isset($cronAttr['cron_monthday'])) {
			if ($currentMonthday != $monthday) {
				return FALSE;
			}
		}

		return ($hour === $currentHour) && ($minute === $currentMinute);
	}

	private static function getLastTimeRun() {
		return $_SERVER["REQUEST_TIME"];
	}

	/**
	 * @return string
	 */
	public static function makeTimestamp($time = null) {
		if($time === null){
			$time = time();
		}
		
		return date("Y-m-d H:i:s", $time);
	}

	/**
	 * Called from REDCap core
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return string[]
	 */
	public static function callTimedCronMethods() {
		# get array of modules
		$enabledModules = self::getEnabledModules();
		$returnMessages = array();

		foreach ($enabledModules as $moduleDirectoryPrefix=>$version) {
			try{
				$cronName = "";

				# do not run twice in the same minute
				$cronAttrs = self::getCronSchedules($moduleDirectoryPrefix);
				$moduleId = self::getIdForPrefix($moduleDirectoryPrefix);
				if (!empty($moduleId) && !empty($cronAttrs)) {
					foreach ($cronAttrs as $cronAttr) {
						$cronName = $cronAttr['cron_name'];
						if (self::isValidTimedCron($cronAttr) && self::isTimeToRun($cronAttr)) {
							# if isTimeToRun, run method
							$cronMethod = $cronAttr['method'];
							array_push($returnMessages, "Timed cron running $cronName->$cronMethod (".self::makeTimestamp().")");
							$mssg = self::callTimedCronMethod($moduleDirectoryPrefix, $cronName);
							if ($mssg) {
								array_push($returnMessages, $mssg." (".self::makeTimestamp().")");
							}
						}
					}
				}
			} catch(Throwable $e) {
				$currentReturnMessage = "Timed Cron job \"$cronName\" failed for External Module \"{$moduleDirectoryPrefix}\"";
				$emailMessage = "$currentReturnMessage with the following Exception: $e";

				self::handleError('External Module Exception in Timed Cron Job ', $emailMessage, $moduleDirectoryPrefix);
				array_push($returnMessages, $currentReturnMessage);
			}
		}
		
		return $returnMessages;
	}

	private static function callTimedCronMethod($moduleDirectoryPrefix, $cronName)
	{
		$lockInfo = self::getCronLockInfo($moduleDirectoryPrefix);
		if($lockInfo){
			self::checkForALongRunningCronJob($moduleDirectoryPrefix, $cronName, $lockInfo);
			return "Skipping cron '$cronName' for module '$moduleDirectoryPrefix' because an existing job is already running for this module.";
		}

		try{
			self::lockCron($moduleDirectoryPrefix);

			$moduleId = self::getIdForPrefix($moduleDirectoryPrefix);
			return self::callCronMethod($moduleId, $cronName);
		}
		finally{
			self::unlockCron($moduleDirectoryPrefix);
		}
	}

	// This method is called both internally and by the REDCap Core code.
	public static function callCronMethod($moduleId, $cronName)
	{
		$originalGet = $_GET;
		$originalPost = $_POST;

		$moduleDirectoryPrefix = self::getPrefixForID($moduleId);

		if($moduleDirectoryPrefix === ExternalModules::TEST_MODULE_PREFIX && !self::isTesting()){
			return;
		}

		$processId = getmypid();
		echo "\n" . date('Y-m-d H:i:s') . " - Process $processId - Started External Module Cron - $moduleDirectoryPrefix - $cronName\n";
		flush();

		gc_collect_cycles();
		$startMemory = memory_get_usage();

		self::setActiveModulePrefix($moduleDirectoryPrefix);
		self::setCurrentHookRunner(new HookRunner($cronName));

		$returnMessage = null;
		try{
			if(self::getModuleDirectoryPath($moduleDirectoryPrefix)){
				// Call cron for this External Module
				$frameworkInstance = self::getFrameworkInstance($moduleDirectoryPrefix);
				$config = $frameworkInstance->getConfig();
				if (isset($config['crons']) && !empty($config['crons'])) {
					// Loop through all crons to find the one we're looking for
					foreach ($config['crons'] as $cronKey=>$cronAttr) {
						if (($cronAttr['cron_name'] ?? null) != $cronName) continue;

						// Find and validate the cron method in the module class
						$cronMethod = $config['crons'][$cronKey]['method'];

						// Execute the cron method in the module class
						$returnMessage = $frameworkInstance->getModuleInstance()->$cronMethod($cronAttr);
					}
				}
			}
			else{
				/**
				 * This module must have been deleted prior to disabling it.  Silently skip this cron.
				 */
			}
		}
		catch(Throwable $e){
			//= Cron job '{0}' failed for External Module '{1}'.
			$returnMessage = self::tt("em_errors_55", 
				$cronName, 
				$moduleDirectoryPrefix); 
			$emailMessage = $returnMessage . "\n\nException: " . $e;
			//= External Module Exception in Cron Job
			$emailSubject = self::tt("em_errors_56"); 
			self::handleError($emailSubject, $emailMessage, $moduleDirectoryPrefix);
		}

		self::setActiveModulePrefix(null);
		self::setCurrentHookRunner(null);

		// Restore GET & POST parameters to what they were prior to the module cron running.
		// The is especially important to prevent scenarios like a module setting $_GET['pid']
		// to make use of REDCap functionality that requires it, but not unsetting it when they're done
		// (which could affect other module crons in unexpected ways).
		$_GET = $originalGet;
		$_POST = $originalPost;

		echo "\n" . date('Y-m-d H:i:s') . " - Process $processId - Finished External Module Cron - $moduleDirectoryPrefix - $cronName\n";
		
		gc_collect_cycles();
		$memoryLeaked = memory_get_usage() - $startMemory; 
		echo "Memory Leaked: " . round($memoryLeaked/1024/1024, 2) . " MB\n";
		
		flush();
		
		return $returnMessage;
	}

	/**
	 * @return void
	 */
	private static function checkForALongRunningCronJob($moduleDirectoryPrefix, $cronName, $lockInfo) {
		/* There are currently two scenarios under which this method will get called:
		 *
		 * 1. A long running cron module method delays the start time of another cron module method in the same cron process,
		 * and that method ends up running concurrently with itself in a later cron process.  This scenario can safely be ignored.
		 *
		 * 2. A cron module method has run longer than the $notificationThreshold below.  No matter how often a job is scheduled to run,
		 * notifications for long running jobs will not be sent more often than the following threshold.  It's currently set
		 * to a little less than 24 hours to ensure that a notification is sent at least once a day for long running daily jobs
		 * (even if they were started a little late due to a previous job).
		 */
		$notificationThreshold = time() - 23*self::HOUR_IN_SECONDS;
		$jobRunningLong = $lockInfo['time'] <= $notificationThreshold;
		if($jobRunningLong){
			$lastNotificationTime = self::getSystemSetting($moduleDirectoryPrefix, self::KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME);
			$notificationNeeded = !$lastNotificationTime || $lastNotificationTime <= $notificationThreshold;
			if($notificationNeeded) {
				$url = APP_URL_EXTMOD."manager/reset_cron.php?prefix=".$moduleDirectoryPrefix;
				// The '{0}' cron job is being skipped for the '{1}' module because a previous cron for this module did not complete. Please make sure this module's configuration is correct for every project, and that it should not cause crons to run past their next start time. The previous process id was {2}. If that process is no longer running, it was likely killed, and can be manually marked as complete by running the following URL:<br><br><a href='{3}'>{4}</a><br><br>In addition, if several crons run at the same time, please consider rescheduling some of them via the <a href='{5}'>{6}</a>.
				$emailMessage = self::tt("em_errors_101",
					$cronName, $moduleDirectoryPrefix, $lockInfo['process-id'] ?? null,
					$url,
					self::tt("em_manage_91"),
					APP_URL_EXTMOD."manager/crons.php",
					self::tt("em_manage_87")); //= Manager for Timed Crons
				//= External Module Long-Running Cron
				$emailSubject = self::tt("em_errors_100"); 
				self::handleError($emailSubject, $emailMessage, $moduleDirectoryPrefix);
				self::setSystemSetting($moduleDirectoryPrefix, self::KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME, time());
			}
		}
	}

	// should be SuperUser to run
	/**
	 * @param string $modulePrefix
	 */
	public static function resetCron($modulePrefix) {
		if ($modulePrefix) {
			$moduleId = self::getIdForPrefix($modulePrefix, false);
			if ($moduleId != null) {
				$sql = "DELETE FROM redcap_external_module_settings WHERE external_module_id = ? AND `key` = ?";
				
				$query = self::createQuery();
				$query->add($sql, [$moduleId, ExternalModules::KEY_RESERVED_IS_CRON_RUNNING]);
				$query->execute();

				return $query->affected_rows;
			} else {
				// "Could not find module ID for prefix '{0}'!"
				throw new \Exception(self::tt("em_errors_118", $modulePrefix));
			}
		} else {
			throw new \Exception(self::tt("em_errors_119"));
		}
	}



	private static function getCronLockInfo($modulePrefix) {
		return self::getSystemSetting($modulePrefix, self::KEY_RESERVED_IS_CRON_RUNNING);
	}

	/**
	 * @return void
	 */
	private static function unlockCron($modulePrefix) {
		self::removeSystemSetting($modulePrefix, self::KEY_RESERVED_IS_CRON_RUNNING);
	}

	/**
	 * @return void
	 */
	private static function lockCron($modulePrefix) {
		self::setSystemSetting($modulePrefix, self::KEY_RESERVED_IS_CRON_RUNNING, [
			'process-id' => getmypid(),
			'time' => time()
		]);

		self::removeSystemSetting($modulePrefix, self::KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME);
	}

	// Throttles actions by using the redcap_log_event.description.
	// An exception is thrown if the $description occurs more than $maximumOccurrences within the past specified number of $seconds.
	/**
	 * @return void
	 *
	 * @param string $description
	 * @param int $maximumOccurrences
	 * @param int $seconds
	 */
	private static function throttleEvent($description, $maximumOccurrences, $seconds)
	{
		$logTableName = 'redcap_log_event'; // Extract the table name to avoid a HardcodedTableSniff error
		$ts = date('YmdHis', time()-$seconds);
		$result = ExternalModules::query("
			select count(*) as count
			from $logTableName l
			where description = ?
			and ts >= ?
		", [$description, $ts]);

		$row = $result->fetch_assoc();

		$occurrences = $row['count'];

		if($occurrences > $maximumOccurrences){
			//= The following action has been throttled because it is only allowed to happen {0} times within {1} seconds, but it happened {2} times: {3}
			throw new Exception(
				self::tt("em_errors_57", 
				$maximumOccurrences, 
				$seconds, 
				$occurrences, 
				$description)); 
		}
	}

	// Copied from the first comment here:
	// http://php.net/manual/en/function.array-merge-recursive.php
	/**
	 * @return array
	 */
	static function array_merge_recursive_distinct ( array &$array1, array &$array2 )
	{
	  $merged = $array1;

	  foreach ( $array2 as $key => &$value )
	  {
	    if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
	    {
	      $merged [$key] = self::array_merge_recursive_distinct ( $merged [$key], $value );
	    }
	    else
	    {
	      $merged [$key] = $value;
	    }
	  }

	  return $merged;
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	public static function dump($o){
		echo "<pre>";
		var_dump($o);
		echo "</pre>";
	}

	/**
	 * @return (int|string)|null
	 */
	private static function array_key_first(array $arr) {
        foreach($arr as $key => $unused) {
            return $key;
        }
        return NULL;
    }

	/**
	 * @return (int|string)|null
	 */
	public static function getMaxSupportedFrameworkVersion(){
		return self::array_key_first(self::getMinREDCapVersionsByFrameworkVersion());
	}

	/**
	 * @return int
	 */
	public static function getFrameworkVersion($module)
	{
		return static::getFrameworkVersionForPrefix($module->PREFIX, $module->VERSION);
	}

	public static function getFrameworkVersionForPrefix($prefix, $version)
	{
		$config = static::getConfig($prefix, $version);
		return static::getFrameworkVersionForConfig($prefix, $config);
	}

	private static function getFrameworkVersionForConfig($prefix, $config){
		$version = $config['framework-version'] ?? null;

		if($version === null){
			$version = 1;
		}
		else if(gettype($version) != 'integer'){
			//= The framework version must be specified as an integer (not a string) for the {0} module.
			throw new Exception(self::tt("em_errors_58", $prefix)); 
		}

		return $version;
	}

	/**
	 * @return int
	 *
	 * @param string $mixed
	 */
	public static function requireInteger($mixed){
		$integer = filter_var($mixed, FILTER_VALIDATE_INT);
		if($integer === false){
			//= An integer was required but the following value was specified instead: {0}
			throw new Exception(self::tt("em_errors_60", $mixed)); 
		}

		return $integer;
	}

	/**
	 * @return string
	 */
	public static function getJavascriptModuleObjectName($moduleInstance){
		$jsObjectParts = explode('\\', get_class($moduleInstance));

		// Remove the class name, since it's always the same as it's parent namespace.
		array_pop($jsObjectParts);

		// Prepend "ExternalModules" to contain all module namespaces.
		array_unshift($jsObjectParts, 'ExternalModules');

		return implode('.', $jsObjectParts);
	}

	/**
	 * @return bool
	 *
	 * @param string $routeName
	 */
	public static function isRoute($routeName){
		return ($_GET['route'] ?? null) === $routeName;
	}

	/**
	 * Called from REDCap core
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	static function copySettings($sourceProjectId, $destinationProjectId){
		// Prevent SQL Injection
		$sourceProjectId = (int) $sourceProjectId;
		$destinationProjectId = (int) $destinationProjectId;

		self::copySettingValues($sourceProjectId, $destinationProjectId);
		self::recreateAllEDocs($destinationProjectId);
	}

	/**
	 * @return void
	 *
	 * @param int $sourceProjectId
	 * @param int $destinationProjectId
	 */
	private static function copySettingValues($sourceProjectId, $destinationProjectId){
		// Prevent SQL Injection
		$destinationProjectId = (int) $destinationProjectId;

		self::query(
			"insert into redcap_external_module_settings (external_module_id, project_id, `key`, type, value)"
			. self::getSettingExportQuery("external_module_id, '$destinationProjectId', `key`, type, value", $sourceProjectId)
		, [
			// Ideally we'd pass the parameters here instead of manually appending them to the query string.
			// However, that doesn't work for combo insert/select statements in mysql.
			// The integer casts should safely protect against SQL injection in this case.
		]);
	}

	/**
	 * @return string
	 *
	 * @param string $columns
	 * @param int $pid
	 */
	static function getSettingExportQuery($columns, $pid){
		// Prevent SQL Injection
		$pid = (int) $pid;

		return "
			select $columns from redcap_external_module_settings
			where project_id = $pid and `key` != '" . ExternalModules::KEY_ENABLED . "'
		";
	}

	// We recreate edocs when copying settings between projects so that edocs removed from
	// one project are not also removed from other projects.
	// This method is currently undocumented/unsupported in modules.
	// It is public because it is used by Carl's settings import/export module.
	/**
	 * @return void
	 *
	 * @param int|null|numeric-string $pid
	 */
	static function recreateAllEDocs($pid)
	{
		$edocCopier = new ProjectCopyEDocCopier($pid);
    	$edocCopier->run();
	}

	# timespan is number of seconds
	/**
	 * @return int[]
	 *
	 * @param int $timespan
	 */
	public static function getCronConflictTimestamps($timespan) {
		$currTime = time();
		$conflicts = array();

		// keep these for debugging purposes
		$timesRun = array();
		$skipped = array();

		$enabledModules = self::getEnabledModules();
		foreach ($enabledModules as $moduleDirectoryPrefix=>$version) {
			$cronAttrs = self::getCronSchedules($moduleDirectoryPrefix);
			foreach ($cronAttrs as $cronAttr) {
				# check every minute
				for ($i = 0; $i < $timespan; $i += 60) {
					$timeToCheck = $currTime + $i;
					if (self::isTimeToRun($cronAttr, $timeToCheck)) {
						if (in_array($timeToCheck, $timesRun)) {
							array_push($conflicts, $timeToCheck);
						} else {
							array_push($timesRun, $timeToCheck);
						}
					} else {
						array_push($skipped, $timeToCheck);
					}
				}
			}
		}
		return $conflicts;
	}

	/**
	 * @return string
	 *
	 * @param null|string $pid
	 * @param string $prefix
	 */
	public static function getRichTextFileUrl($prefix, $pid, $edocId, $name)
	{
		self::requireNonEmptyValues(func_get_args());
		
		$prefix = htmlentities($prefix, ENT_QUOTES);

		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$url = ExternalModules::getModuleAPIUrl() . "page=/manager/rich-text/get-file.php&file=$edocId.$extension&prefix=$prefix&pid=$pid&NOAUTH";

		return $url;
	}

	/**
	 * @return void
	 *
	 * @param array $a
	 */
	private static function requireNonEmptyValues($a){
		foreach($a as $key=>$value){
			if(empty($value)){
				throw new Exception("The array value for key '$key' was unexpectedly empty!");
			}
		}
	}

	/**
	 * @return bool
	 */
	public static function haveUnsafeEDocReferencesBeenChecked()
	{
		$fieldName = 'external_modules_unsafe_edoc_references_checked';
		if(isset($GLOBALS[$fieldName])){
			return true;
		}

		if(empty(ExternalModules::getUnsafeEDocReferences())){
			self::query("insert into redcap_config values (?, ?)", [$fieldName, 1]);
			return true;
		}

		return false;
	}

	/**
	 * @return void
	 *
	 * @param \Closure $action
	 */
	private static function walkSubSettings(&$settings, $action){
		$iAdjustment = 0;
		foreach($settings as $i=>$setting){
			unset($settings[$i]);
			$setting = $action($setting);

			if($setting === null){
				$iAdjustment++;
				continue;
			}

			if(($setting['type'] ?? null) === 'sub_settings'){
				static::walkSubSettings($setting['sub_settings'], $action);
			}

			$settings[$i-$iAdjustment] = $setting;
		}
	}

	/**
	 * @return array[]
	 */
	public static function getUnsafeEDocReferences()
	{		
		$keysByPrefix = [];
		foreach(ExternalModules::getSystemwideEnabledVersions() as $prefix=>$version){
			$config = ExternalModules::getConfig($prefix, $version);
			foreach(['system-settings', 'project-settings', 'email-dashboard-settings'] as $settingType){
				$settings = $config[$settingType] ?? null;
				if(!$settings){
					continue;
				}

				static::walkSubSettings($settings, function($setting) use (&$keysByPrefix, $prefix){
					if($setting['type'] === 'file'){
						$keysByPrefix[$prefix][] = $setting['key'];
					}

					return $setting;
				});
			}
		}

		$edocs = [];
		$addEdoc = function($prefix, $pid, $key, $edocId) use (&$edocs): void{
			if(empty($edocId)){
				return;
			}

			$edocs[$edocId][] = [
				'prefix' => $prefix,
				'pid' => $pid,
				'key' => $key
			];
		};

		$parseRichTextValue = function($prefix, $pid, $key, $files) use ($addEdoc): void{
			foreach($files as $file){
				$addEdoc($prefix, $pid, $key, $file['edocId']);
			}
		};

		$parseFileSettingValue = function($prefix, $pid, $key, $value) use (&$parseFileSettingValue, &$addEdoc): void{
			if(is_array($value)){
				foreach($value as $subValue){
					$parseFileSettingValue($prefix, $pid, $key, $subValue);
				}
			}
			else{
				$addEdoc($prefix, $pid, $key, $value);
			}
		};

		$query = self::createQuery();
		$query->add("
			select *
			from redcap_external_module_settings
			where
		");

		$query->add("`key` = ?", ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);

		foreach($keysByPrefix as $prefix=>$keys){
			$query->add("\nor");
			
			$moduleId = ExternalModules::getIdForPrefix($prefix);

			$query->add("(");
			$query->add("external_module_id = ?", [$moduleId]);
			$query->add("and")->addInClause('`key`', $keys);
			$query->add(")");
		}

		$result = $query->execute();
		while($row = $result->fetch_assoc()){
			foreach(['external_module_id', 'project_id'] as $fieldName){
				$row[$fieldName] = (string) $row[$fieldName];
			}
			
			$prefix = ExternalModules::getPrefixForID($row['external_module_id']);
			$pid = $row['project_id'];
			$key = $row['key'];
			$value = json_decode($row['value'], true);

			if($key === ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST){
				$parseRichTextValue($prefix, $pid, $key, $value);
			}
			else{
				$parseFileSettingValue($prefix, $pid, $key, $value);
			}
		}

		$query = self::createQuery();
		$query->add("select * from redcap_edocs_metadata where ");
		$query->addInClause('doc_id', array_keys($edocs));
		$result = $query->execute();
		$sourceProjectsByEdocId = [];
		while($row = $result->fetch_assoc()){
			foreach(['doc_id', 'doc_size', 'gzipped', 'project_id'] as $fieldName){
				$row[$fieldName] = (string) $row[$fieldName];
			}

			$sourceProjectsByEdocId[$row['doc_id']] = $row['project_id'];
		}

		$unsafeReferences = [];
		ksort($edocs);
		foreach($edocs as $edocId=>$references){
			foreach($references as $reference){
				$sourcePid = $sourceProjectsByEdocId[$edocId] ?? null;
				$referencePid = $reference['pid'];
				if(
					/**
					 * The $sourcePid does not exist.
					 * Perhaps the redcap_edocs_metadata row was manually copied from a PROD to DEV/TEST server?
					 * Regardless we should probably ignore this eDoc since there's nothing we can do about it.
					 */
					$sourcePid === null
					||
					/**
					 * The eDoc is already on the expected pid.
					 */
					$referencePid === $sourcePid
				){
					continue;
				}

				$reference['edocId'] = $edocId;
				$reference['sourcePid'] = static::escape($sourcePid);
				$unsafeReferences[$referencePid][] = $reference;
			}
		}

		return $unsafeReferences;
	}

	/**
	 * @return void
	 *
	 * @param string $modulePrefix
	 */
	public static function removeModifiedCrons($modulePrefix) {
		self::removeSystemSetting($modulePrefix, self::KEY_RESERVED_CRON_MODIFICATION_NAME);
	}

	/**
	 * @param string $modulePrefix
	 */
	public static function getModifiedCrons($modulePrefix) {
		return self::getSystemSetting($modulePrefix, self::KEY_RESERVED_CRON_MODIFICATION_NAME);
	}

	# overwrites previously saved version
	/**
	 * @return void
	 *
	 * @param string $modulePrefix
	 * @param (mixed|string)[][] $cronSchedule
	 */
	public static function setModifiedCrons($modulePrefix, $cronSchedule) {
		foreach ($cronSchedule as $cronAttr) {
			if (!self::isValidTimedCron($cronAttr) && !self::isValidTabledCron($cronAttr)) {
				throw new \Exception("A cron is not valid! ".json_encode($cronAttr));
			}
		}
		
		self::setSystemSetting($modulePrefix, self::KEY_RESERVED_CRON_MODIFICATION_NAME, $cronSchedule);
	}

	/**
	 * @return array
	 *
	 * @param string $modulePrefix
	 */
	public static function getCronSchedules($modulePrefix) {
		$config = self::getConfig($modulePrefix);
		$finalVersion = array();
		if (!isset($config['crons'])) {
			return $finalVersion;	
		}

		foreach ($config['crons'] as $cronAttr) {
			$finalVersion[$cronAttr['cron_name']] = $cronAttr;
		}

		$modifications = self::getModifiedCrons($modulePrefix);
		if ($modifications) {
			foreach ($modifications as $cronAttr) {
				# overwrite config's if modifications exist
				$finalVersion[$cronAttr['cron_name']] = $cronAttr;
			}
		}
		return array_values($finalVersion);
	}

	/**
	 * @param numeric-string $project_id
	 * @param int $request_id
	 */
	public static function finalizeModuleActivationRequest($prefix, $version, $project_id, $request_id)
    {
        global $project_contact_email, $project_contact_name, $app_title;
		// If this was enabled by admin as a user request, then remove from To-Do List (if applicable)
		if (static::isSuperUser() && \ToDoList::updateTodoStatus($project_id, 'module activation', 'completed', null, $request_id))
		{
			// For To-Do List requests only, send email back to user who requested module be enabled
			try {
				$config = self::getConfig($prefix, $version); // Admins always get English names of modules.
				$module_name = strip_tags($config["name"]);
				$request_userid = \ToDoList::getRequestorByRequestId($request_id);
				$userInfo = \User::getUserInfoByUiid($request_userid);
				$project_url = APP_URL_EXTMOD . 'manager/project.php?pid=' . $project_id;

				$from = $project_contact_email;
				$fromName = $project_contact_name;
				$to = [$userInfo['user_email']];
				$subject = "[REDCap] External Module \"{$module_name}\" has been activated";
				$message = "The External Module \"<b>{$module_name}</b>\" has been successfully activated for the project named \""
					. \RCView::a(array('href' => $project_url), strip_tags($app_title)) . "\".";
				$email = self::sendBasicEmail($from, $to, $subject, $message, $fromName);
				return $email;
			} catch (Throwable $e) {
			    return false;
			}
		}
		return true;
	}

	/**
	 * Determine if the current user has privileges to install, enable, and update modules at the system level.
	 * To determine enable access on the project level, use userCanEnableDisableModule().
	 *
	 * @return bool
	 */
	public static function isAdminWithModuleInstallPrivileges()
	{
		if(
			/**
			 * If we're impersonating anyone, just assume they DON'T have this permission.
			 * We could make this smarter in the future by querying the DB
			 * to see if the user we're impersontating has this permission.
			 */
			\UserRights::isImpersonatingUser()
			||
			/**
			 * This is not defined during password reset after first time table account creation,
			 * and there may be other scenarios we haven't considered:
			 * https://redcap.vumc.org/community/post.php?id=215348&comment=215350
			 */
			!defined('ACCESS_EXTERNAL_MODULE_INSTALL')
		){
			return false;
		}

		return ACCESS_EXTERNAL_MODULE_INSTALL == '1';
	}

	/**
	 * @return bool
	 */
	public static function userCanEnableDisableModule($prefix = null)
	{
		$pid = static::getProjectId();
		if(!isset($pid)){
			// We're in the control center
			return ExternalModules::isAdminWithModuleInstallPrivileges();
		}
		else{
			// We're on a project
			return
				ExternalModules::isSuperUser()
				||
				(
					ExternalModules::hasDesignRights()
					&&
					(
						ExternalModules::isAdminWithModuleInstallPrivileges()
						||
						(
							$prefix !== null
							&&
							self::getSystemSetting($prefix, self::KEY_USER_ACTIVATE_PERMISSION) == true
						)
					)
				);
		}
	}

	/**
	 * @psalm-taint-escape file
	 * @psalm-taint-escape include
	 *
	 * @param string $path
	 * @param string $root
	 *
	 * @return string
	 */
	public static function getSafePath($path, $root){
		$root = rtrim($root, DS);
		if(!file_exists($root)){
			//= The specified root ({0}) does not exist as either an absolute path or a relative path to the module directory.
			throw new Exception(ExternalModules::tt("em_errors_103", $root));
		}

		if(
			strpos($path, $root) === 0 // The root is already included in the path.
			||
			self::isAbsolutePath($path) // It's already an absolute path.
		){
			$fullPath = $path;
		}
		else{
			$fullPath = $root . DIRECTORY_SEPARATOR . $path;
		}

		$fullPathBeforeRealPath = $fullPath;

		if(file_exists($fullPath)){
			$fullPath = realpath($fullPath);
		}
		else{
			// Also support the case where this is a path to a new file that doesn't exist yet and check it's parents.
			$dirname = dirname($fullPath);
				
			if(!file_exists($dirname)){
				//= The parent directory ({0}) does not exist.  Please create it before calling getSafePath() since the realpath() function only works on directories that exist.
				throw new Exception(ExternalModules::tt("em_errors_104", $dirname));
			}

			$fullPath = realpath($dirname) . DIRECTORY_SEPARATOR . basename($fullPath);
		}

		if(strpos($fullPath, realpath($root)) !== 0){
			//= You referenced a path ({0}) that is outside of your allowed parent directory ({1}).
			throw new Exception(ExternalModules::tt("em_errors_105", $fullPath, $root));
		}

		/**
		 * Return the original path without passing it through realpath() to avoid any issues
		 * caused by symlinks that are expected NOT to be resolved to their destinations.
		 * See the discussion on PR 469 for more details.
		 */
		return $fullPathBeforeRealPath;
	}

	/**
	 * @return bool
	 *
	 * @param string $path
	 */
	public static function isAbsolutePath($path){
		return
			($path[0] ?? null) === '/' // *nix.
			||
			($path[1] ?? null) === ':' // Windows.
		;
	}
	
	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return string[]
	 */
	public static function getTestPIDs(){
		$testPIDs = array_filter(explode(',', $GLOBALS['external_modules_test_pids'] ?? ''));

		$expectedTitles = [
			'External Module Unit Test Project 1',
			'External Module Unit Test Project 2',
			'External Module Unit Test Project 3',
		];

		$newProjectsNeeded = count($expectedTitles) - count($testPIDs);
		$setTestPIDs = false;
		while($newProjectsNeeded > 0){
			$setTestPIDs = true;

			$title = $expectedTitles[count($testPIDs)];
			$testPIDs[] = (string) static::createProject($title, 4);

			$newProjectsNeeded--;
		}

		if($setTestPIDs){
			$testPIDsString = implode(',', $testPIDs);
			$GLOBALS['external_modules_test_pids'] = $testPIDsString;
			static::query("
				insert into redcap_config
				values ('external_modules_test_pids', ?)
				on duplicate key update value = ?
			", [$testPIDsString, $testPIDsString]);
		}

		for($i=0; $i<count($testPIDs); $i++){
			$pid = $testPIDs[$i];
			$expectedTitle = $expectedTitles[$i];
			
			$r = static::query('select app_title from redcap_projects where project_id = ?', $pid);
			$row = $r->fetch_assoc();
			$actualTitle = $row['app_title'];

			if($actualTitle !== $expectedTitle){
				throw new Exception("Expected project $pid to be titled '$expectedTitle' but found '$actualTitle'.");
			}
		}

		return $testPIDs;
	}
	
	public static function addSurveyParticipant($surveyId, $eventId, $hash){
		## Insert a participant row for this survey
		$sql = "INSERT INTO redcap_surveys_participants (survey_id, event_id, participant_email, participant_identifier, hash)
		VALUES (?, ?, '', null, ?)";

		self::query($sql, [$surveyId, $eventId, $hash]);
		
		return db_insert_id();
	}

	public static function addSurveyResponse($participantId, $recordId, $returnCode, $instance = 1){
        ## Invalid instance value, throw exception
        if(!is_numeric($instance)) {
            throw new Exception("Instance ".$instance." is not numeric on record: " . $recordId);
        }

		$firstSubmitDate = "'".date('Y-m-d H:i:s')."'";
		$sqlVars = [$participantId, $recordId, $firstSubmitDate, $returnCode, $instance];
  
		$sql = "INSERT INTO redcap_surveys_response (participant_id, record, first_submit_time, return_code, instance)
					VALUES (?, ?, ?, ?, ?)";
		self::query($sql, $sqlVars);

		return db_insert_id();
	}

	/**
	 * @return void
	 *
	 * @param string $parameterName
	 */
	public static function checkForInvalidLogParameterNameCharacters($parameterName){
		if(preg_match('/[^A-Za-z0-9 _\-$]/', $parameterName) !== 0){
			throw new Exception(self::tt("em_errors_115", $parameterName));
		}
	}

	public static function convertIntsToStrings($row){
		foreach($row as $key=>$value){
			if(gettype($value) === 'integer'){
				$row[$key] = (string) $value;
			}
		}

		return $row;
	}

	public static function isControlCenterPage(){
		return
			strpos($_SERVER['PHP_SELF'], APP_PATH_WEBROOT . 'ControlCenter/') === 0
			||
			static::isManagerPage('control_center.php')
		;
	}

	/**
	 * @return bool
	 *
	 * @param string $subPath
	 */
	public static function isManagerPage($subPath = ''){
		$parts = explode('://', APP_URL_EXTMOD);
		$expectedPrefix = $parts[1] . 'manager/' . $subPath;
		$requestUrl = ($_SERVER['HTTP_HOST'] ?? null) . $_SERVER['PHP_SELF'];

		// We don't want duplicate slashes to affect URL detection.
		$requestUrl = preg_replace ('/\/+/', '/', $requestUrl);

		return strpos($requestUrl, $expectedPrefix) === 0;
	}
	
	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public static function getRecordCompleteStatus($projectId, $recordId, $eventId, $surveyFormName){
		$result = self::query(
			"select value from " . \Records::getDataTable($projectId) . " " . self::COMPLETED_STATUS_WHERE_CLAUSE, //
			[$projectId, $recordId, $eventId, "{$surveyFormName}_complete"]
		);

		$row = $result->fetch_assoc();

		return $row['value'] ?? null;
	}

	/**
	 * @return array
	 *
	 * @param int $value
	 */
	public static function setRecordCompleteStatus($projectId, $recordId, $eventId, $surveyFormName, $value){
		// Set the response as incomplete in the data table
		$sql = "UPDATE ".\Records::getDataTable($projectId)." SET value = ?" . self::COMPLETED_STATUS_WHERE_CLAUSE;

		$q = ExternalModules::createQuery();
		$q->add($sql, [$value, $projectId, $recordId, $eventId, "{$surveyFormName}_complete"]);
		$r = $q->execute();

		return [$q, $r];
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return (int|string)[]
	 */
	public static function getFormNames($pid){
		$metadata = self::getMetadata($pid);

		$formNames = [];
		foreach($metadata as $details){
			$formNames[$details['form_name']] = true;
		}

		return array_keys($formNames);
	}

	public static function getMetadata($projectId,$forms = NULL) {
		return \REDCap::getDataDictionary($projectId,"array",TRUE,NULL,$forms);
	}
	
	/**
	 * Checks whether a project id is valid.
	 * 
	 * Additional conditions can be via a second argument:
	 *  - TRUE: The project must actually exist (with any status).
	 *  - "DEV": The project must be in development mode.
	 *  - "PROD": The project must be in production mode.
	 *  - "AC": The project must be in analysis/cleanup mode.
	 *  - "DONE": The project must be completed.
	 *  - An array containing any of the states listed, e.g. ["DEV","PROD"]
	 * 
	 * @param string|int $pid The project id to check.
	 * @param bool|string|array $condition Performs additional checks depending on the value (default: false).
	 * @return bool True/False depending on whether the project id is valid or not.
	 */
	public static function isValidProjectId($pid, $condition = false) {
		// Basic check; positive integer.
		if (empty($pid) || !is_numeric($pid) || !is_int($pid * 1) || ($pid * 1 < 1)) return false;
		$valid = true;
		if ($condition !== false) {
			$limit = ["DEV", "PROD", "AC", "DONE"];
			if(is_string($condition)) {
				$limit = array ( $condition );
			}
			else if (is_array($condition)) {
				$limit = $condition;
			}
			$valid = in_array(self::getProjectStatus($pid), $limit, true);
		}
		return $valid;
	}

    /**
     * Gets the already instantiated Project by REDCap
     *
     * @param int|string $pid The project id.
     * @throws InvalidArgumentException
     */
    public static function getREDCapProjectObject($pid, $failFastOnError = true): ?\Project{
        if (!self::isValidProjectId($pid)) {
            throw new InvalidArgumentException(self::tt("em_errors_131")); //= Invalid value for project id!
        }

		$result = self::query('select 1 from redcap_projects where project_id = ?', $pid);
		if($result->fetch_assoc() === null){
			// Avoid creation of the REDCap Project object, since it will throw a bunch of PHP warnings with an invalid project ID.
			return null;
		}

        $project = @(new \Project($pid));
        if($project->table_pk === null && $failFastOnError){
            if(static::isTesting()){
                throw new \Exception('Unit test project object initialization failed, perhaps due to corrupt metadata: ' . $pid);
            }

            // The project doesn't actually exist.
            return null;
        }

        return $project;
    }

    /**
     * Gets the status of a project.
     *
     * Status can be one of the following:
     * - DEV: Development mode
     * - PROD: Production mode
     * - AC: Analysis/Cleanup mode
     * - DONE: Completed
     *
     * @param int|string $pid The project id.
     * @return null|string The status of the project. If the project does not exist, NULL is returned.
     */

    public static function getProjectStatus($pid){
        $project = self::getREDCapProjectObject($pid);
        if($project === null){
            return null;
        }

        $check_status = $project->project['status'];
        switch ($check_status) {
            case 0: $status = "DEV"; break;
            case 1: $status = "PROD"; break;
            case 2: $status = empty($project->project['completed_time']) ? "AC" : "DONE"; break;
        }

        return $status ?? null;
    }

	static function getRecordIdField($pid){
		$result = ExternalModules::query("
			select field_name
			from redcap_metadata
			where project_id = ?
			order by field_order
			limit 1
		", [$pid]);

		$row = $result->fetch_assoc();

		return $row['field_name'];
	}

	/**
	 * @param int|null|string $pid
	 */
	static function requireProjectId($pid = null)
	{
		$pid = self::detectParameter('pid', $pid);
		if(!isset($pid) && defined('PROJECT_ID')){
			// As of this writing, this is only required when called inside redcap_every_page_top while using Send-It to send a file from the File Repository.
			$pid = PROJECT_ID;
		}

		$pid = self::requireParameter('pid', $pid);

		return $pid;
	}

	/**
	 * @param string $parameterName
	 * @param int|null|string $value
	 */
	static function detectParameter($parameterName, $value = null)
	{
		if($value == null){
			$value = $_GET[$parameterName] ?? null;
		}

		if(!empty($value)){
			// Use intval() to prevent SQL injection.
			$value = intval($value);
		}

		return $value;
	}

	/**
	 * @param string $parameterName
	 */
	private static function requireParameter($parameterName, $value)
	{
		$value = self::detectParameter($parameterName, $value);

		if(!isset($value)){
			//= You must supply the following either as a GET parameter or as the last argument to this method: {0}
			throw new Exception(ExternalModules::tt("em_errors_65", $parameterName)); 
		}

		return $value;
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	public static function removeUserFromProject($projectId, $username){
		if(empty($projectId) || empty($username)){
			throw new Exception("Both a project and user must be specified!");
		}

		self::query('DELETE FROM redcap_user_rights WHERE project_id = ? and username = ?', [$projectId, $username]);
	}

	/**
	 * @return string
	 *
	 * @param (string|string[][]|true)[] $array
	 */
	static function getQuestionMarks($array){
		return implode(',', array_fill(0, count($array), '?'));
	}

	/**
	 * @param ((int|string)|mixed)[] $edocIds
	 */
	static function queryEDocs($edocIds){
		if(empty($edocIds)){
			$questionMarks = 'null';
		}
		else{
			$questionMarks = self::getQuestionMarks($edocIds);
		}

		return ExternalModules::query("select * from redcap_edocs_metadata where doc_id in ($questionMarks)", $edocIds);
	}

	/**
	 * @param numeric-string $edoc
	 *
	 * @return string
	 */
	static function getEdocPath($edoc){
		if(is_array($edoc)){
			$filename = $edoc['stored_name'];
			$pid = ($edoc['project_id'] ?? '');
		}
		else{
			$row = ExternalModules::queryEDocs([$edoc])->fetch_assoc();
			$filename = $row['stored_name'];
			$pid = ($row['project_id'] ?? '');
		}

		$edocPath = EDOC_PATH;
		$edocPath .= !empty($pid) ? \Files::getLocalStorageSubfolder($pid, true) : '' ;

		return self::getSafePath($filename, $edocPath);
	}

	/**
	 * @return string
	 */
	static function getPHPUnitPath(){
		return self::getTestVendorPath() . 'phpunit/phpunit/phpunit';
	}

	/**
	 * @return string
	 */
	static function getTestVendorPath(){
		return APP_PATH_DOCROOT . 'UnitTests/vendor/';
	}

	/**
	 * @param int|null $frameworkVersion
	 */
	static function checkCSRFToken($frameworkVersion): ?string{
		$csrfToken = $_POST['redcap_external_module_csrf_token'] ?? null;

		// Remove the token like REDCap core does with 'redcap_csrf_token'.
		// This prevents any existing module code that loops over POSTed values from potentially being affected.
		unset($_POST['redcap_external_module_csrf_token']);

		if($frameworkVersion == null){
			$errorLanguageKey = 'em_errors_156';
			$errorParam = null;
		}
		else if($frameworkVersion >= self::CSRF_MIN_FRAMEWORK_VERSION){
			$errorLanguageKey = 'em_errors_184';
			$errorParam = static::getCurrentURL();
		}
		else{
			// Token checking is not required on older framework versions.
			return null;
		}

		if(($_SERVER['REQUEST_METHOD'] ?? null) !== 'POST'){
			// CSRF tokens are not required for this request
			return null;
		}

		if(empty($csrfToken)){
			throw new Exception(self::tt($errorLanguageKey, $errorParam));
		}

		if(!defined('NOAUTH')){
			\System::forceCsrfTokenCheck($csrfToken);
		}
		else if(!hash_equals($csrfToken, ($_COOKIE['redcap_external_module_csrf_token'] ?? ''))){
			throw new Exception(self::tt('em_errors_158'));
		}

		return $csrfToken;
	}
	
	/**
	 * @return string
	 *
	 * @param AbstractExternalModule $moduleInstance
	 */
	static function getModuleDirectoryName($moduleInstance){
		$reflector = new \ReflectionClass(get_class($moduleInstance));
		return basename(dirname($reflector->getFileName()));
	}

	/**
	 * @return bool
	 */
	static function isNoAuth(){
		return isset($_GET['NOAUTH']);
	}

	static function getUserInfo($username){
		$results = ExternalModules::query("
			select *
			from redcap_user_information
			where username = ?
		", [$username]);

		return $results->fetch_assoc();
	}

	/**
	 * @return bool
	 *
	 * @param string $prefix
	 */
	static function isModuleEnabled($prefix, $pid = null) {
        if (empty($prefix)) {
            throw new InvalidArgumentException(ExternalModules::tt("em_errors_50")); //= You must specify a prefix!
        }
        if ($pid !== null && !ExternalModules::isValidProjectId($pid)) {
            throw new InvalidArgumentException(ExternalModules::tt("em_errors_131")); //= Invalid value for project id!
        }
        $enabled = ExternalModules::getEnabledModules($pid);
        return array_key_exists($prefix, $enabled);
    }

	/**
	 * @param \Closure $action
	 */
	static function processNestedSettingValues($value, $action)
	{
		if(gettype($value) === 'array'){
			/**
			 * We have no way of knowing whether these subValues are subsettings
			 * or values in any arbitrary array that a module might have saved.
			 * This currently does not matter since the actions we use only
			 * make changes for certain setting types.
			 * This may change in the future though.  We should be careful here.
			 */
			foreach($value as $key=>$subValue){
				$value[$key] = self::processNestedSettingValues($subValue, $action);
			}
		}
		else{
			$value = $action($value);
		}

		return $value;
	}

	static function processNestedSettingValuesForRow($row, $action){
		$value = $row['value'];

		if($row['type'] === 'json-array'){
			$value = json_decode($value, true);
		}
		
		$value = ExternalModules::processNestedSettingValues($value, $action);

		if($row['type'] === 'json-array'){
			$value = json_encode($value);
		}

		$row['value'] = $value;

		return $row;
	}

	/**
	 * @return string[]
	 *
	 * @param (int|string)[] $moduleNames
	 * @param array $warningMessages
	 */
	static function getSettingImportResponse($moduleNames, $warningMessages){
		return self::getSettingExportOrImportResponse('em_manage_101', $moduleNames, 'em_manage_103', $warningMessages);
	}

	/**
	 * @return string[]
	 *
	 * @param (int|string)[] $moduleNames
	 * @param array $warningMessages
	 */
	static function getSettingExportResponse($moduleNames, $warningMessages){
		$response = self::getSettingExportOrImportResponse('em_manage_106', $moduleNames, 'em_manage_107', $warningMessages);
		$response['downloadUrl'] = 'ajax/download-exported-settings.php';

		return $response;
	}

	/**
	 * @return string[]
	 *
	 * @param string $successLang
	 * @param string $warningLang
	 * @param (int|string)[] $moduleNames
	 * @param array $warningMessages
	 */
	static function getSettingExportOrImportResponse($successLang, $moduleNames, $warningLang, $warningMessages){
		$message = '';

		if(!empty($moduleNames)){
			$message .= ExternalModules::tt($successLang) . self::arrayToHTMLList($moduleNames);
		}

		if(!empty($warningMessages)){
			$message .= ExternalModules::tt($warningLang) .  self::arrayToHTMLList($warningMessages);
		}

		return [
			'message' => $message
		];
	}

	/**
	 * @return string
	 *
	 * @param array $array
	 */
	static function arrayToHTMLList($array){
		$html = "<ul>";

		foreach($array as $item){
			if(is_array($item)){
				$item = self::arrayToHTMLList($item);
			}
			else{
				$item = "<li>$item</li>";
			}

			$html .= $item;
		}

		$html .= "</ul>";

		return $html;
	}

	static function getAndClearExportedSettingsPath(){
		$sessionVarName = 'external-module-settings-export-path';
		$path = $_SESSION[$sessionVarName] ?? null;
		unset($sessionVarName);

		return $path;
	}

	/**
	 * @return void
	 *
	 * @param string $path
	 */
	static function setExportedSettingsPath($path){
		$_SESSION['external-module-settings-export-path'] = $path;
	}

	static function getSurveyHash() {
		/**
		 * It might be more appropriate to use a regex here to prevent invalid characters,
		 * but this should effectively handle injections as well.
		 */
		return htmlentities($_GET['s'] ?? '', ENT_QUOTES);
	}

	static function getSurveyQueueHash() {
		/**
		 * It might be more appropriate to use a regex here to prevent invalid characters,
		 * but this should effectively handle injections as well.
         * One might also check if the hash is valid - this would require a database query
		 */
		return htmlentities($_GET['sq'] ?? '', ENT_QUOTES);
	}

	/**
	 * @return null|numeric-string
	 */
	static function getProjectId($pid = null){
		if($pid === null){
			$pid = $_GET['pid'] ?? null;
		}
		// This might be a public dashboard or a public report
		if ($pid === null && isset($_GET["__dashboard"])) {
			$dash = new \ProjectDashboards();
			list ($pid, $_, $_) = $dash->getDashInfoFromPublicHash($_GET['__dashboard']);
		} 
		if ($pid === null && isset($_GET["__report"])) {
			list ($pid, $_, $_) = \DataExport::getReportInfoFromPublicHash($_GET['__report']);
		}

		// Require an integer to prevent any kind of injection (and make Psalm happy)
		$pid = filter_var($pid, FILTER_VALIDATE_INT);
		if($pid === false){
			return null;
		}

		// Convert back to a string to ensure backward compatibility.
		return (string) $pid;
	}

	/**
	 * @return void
	 *
	 * @param null|numeric-string $pid
	 */
	static function setProjectId($pid){
		if($pid !== null){
			$pid = (string) $pid;
		}

		$_GET['pid'] = $pid;
	}

	/**
	 * @return string
	 */
	static function getPrefix(){
		$prefix = $_GET['prefix'] ?? '';
		if(is_array($prefix)){
			return '';
		}

		/**
		 * This is not the appropriate escaping method in all scenarios, but should have no affect on valid prefixes.
		 */
		return htmlentities($prefix, ENT_QUOTES);
	}

	/**
	 * TODO Should we modify all locations that use this method to use a $_GET var and the getPrefix() method instead?
	 */
	static function getPrefixFromPost(){
		/**
		 * This is not the appropriate escaping method in all scenarios, but should have no affect on valid prefixes.
		 */
		return htmlentities($_POST['prefix'] ?? '', ENT_QUOTES);
	}

	/**
	 * @return void
	 */
	static function limitDirectFileAccess(){
		$emDirPrefix = explode('://', APP_URL_EXTMOD)[1];
		$requestUrl = ($_SERVER['HTTP_HOST'] ?? null) . $_SERVER['PHP_SELF'];

		$parts = explode($emDirPrefix, $requestUrl);
		if(
			// Is this a some other REDCap page not related to External Modules, or command line script where HTTP_HOST is not set?
			count($parts) === 1
			||
			// Is this page explicitly allowed?  Anything not explicitly allowed should be disallowed by default.
			in_array($parts[1], [
				'index.php',
				'module-ajax/jsmo-ajax.php',
				'bin/install-scan-script.php',
			])
		){ 
			// Do nothing.
		}
		else if(self::isManagerPage() && !self::isManagerPage('templates/')){
			/**
			 * Allow access to manager pages EXCEPT templates.  It's OK that some templates that don't execute this check
			 * because they don't call redcap_connect.php.  Those cannot perform any sensitive actions anyway.
			 */

			if(static::isNoAuth() || static::getUsername() !== null){				
				self::checkCSRFToken(null);
			}
			else{
				// The login form is being shown.  CSRF checking is not needed, and will break login form errors (like incorrect password).
			}
		}
		else{
			// Disallow any URL that is not explicitly allow-listed.
			throw new Exception(static::tt('em_errors_121'));
		}
	}

	/**
	 * @return string
	 *
	 * @param bool $newBehavior
	 * @param Framework|null $frameworkInstance
	 */
	static function getLinkIconHtml($link, $newBehavior = true, $frameworkInstance = null){
		$icon = $link['icon'] ?? null;

		$style = 'width: 16px; height: 16px; text-align: center;';

		$getImageIconElement = function($iconUrl) use ($style): string{
			return "<img src='$iconUrl' style='$style'>";
		};

		$iconElement = "<span style='display:inline-block;$style'>&nbsp;</span>";
		if ($icon != null) {
			if($newBehavior){
				if($frameworkInstance && file_exists($frameworkInstance->getModulePath() . '/' . $icon)){
					$iconElement = $getImageIconElement($frameworkInstance->getUrl($icon));
				}
				else{
					// Assume it is a font awesome class.
					$iconElement = "<i class='$icon' style='$style'></i>";
				}
			}
			else{
				$iconElement = $getImageIconElement(APP_PATH_WEBROOT . 'Resources/images/' . $icon . '.png');
			}
		}

		$linkUrl = $link['url'] ?? null;
		$projectId = ExternalModules::getProjectId();
		if($projectId){
			$linkUrl .= "&pid=$projectId";
		}

        $target = $link["target"] ?? null;
        $prefixedKey = $link["prefixedKey"] ?? null;
        $name = $link["name"] ?? null;

		return "
			<div>
				$iconElement
				<a href=\"$linkUrl\" target=\"$target\" data-link-key=\"$prefixedKey\">$name</a>
			</div>
		";
	}

	/**
	 * @return string
	 *
	 * @param string $labelKey
	 * @param string $icon
	 * @param string $url
	 */
	static function getMenuHeaderLink($labelKey, $icon, $url, $projectId){
		$label = ExternalModules::tt($labelKey);
		
		$style = 'float: right;';
		if($projectId === null){
			$style .= 'margin-left: 10px; margin-top: -1px; display: inline';
		}
		else{
			$style .= 'margin: 0px 5px;';
			$url .= "?pid=$projectId";
		}

		/**
		 * \r & \n are stripped separately because this file may be checked out with Windows or Unix newlines during development.
		 */
		return str_replace(["\r", "\n"], "", "
			<div class='opacity65' style='$style'>
				<i class='fas fa-$icon fs10' style='color:#000066;'></i>
				<a href='$url' style='font-size:11px;text-decoration:underline;color:#000066;font-weight:normal;'>$label</a>
			</div>
		");
	}

	/**
	 * @return string
	 */
	static function getMenuHeaderLinks($projectId){
		$links = '';

		if(ExternalModules::hasDesignRights()){
			$links .= static::getMenuHeaderLink('em_manage_112', 'list-ul', APP_URL_EXTMOD_RELATIVE . "manager/logs.php", $projectId);
		}

		// Display only to super users or to users with Design Setup rights *if* one or more modules are already enabled 
		// *or* if at least one module has been set as "discoverable".
		if (UserRights::displayExternalModulesMenuLink()){
			if($projectId === null){
				$manageUrl = "manager/control_center.php";
			}
			else{
				$manageUrl = "manager/project.php";
			}
	
			$links .= static::getMenuHeaderLink('em_manage_114', 'cube', APP_URL_EXTMOD_RELATIVE . $manageUrl, $projectId);
		}

		return $links;
	}

	/**
	 * @return false|string
	 */
	static function createTempFile(){		
		if(self::$shuttingDown){
			// Prevent modules from creating files we can't automatically delete (e.g. in their own shutdown handlers).
			throw new Exception(static::tt('em_errors_163'));
		}

		$path = tempnam(APP_PATH_TEMP, 'external-modules-');

		// Check if the filename already ends with ".tmp". If not, add it and rename file. Does not get auto-added in non-Windows OS.
		if (substr($path, -4) !== '.tmp') {
			rename($path, $path .= '.tmp');
		}

		static::$tempPaths[] = $path;

		return $path;
    }

    /**
     * @return false|string
     */
    static function createTempDir(){
		$path = static::createTempFile();
		unlink($path);
		mkdir($path);

		return $path;
    }

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 * @return void
	 */
	static function simulateShutdown($actionWhileShuttingDown = null){
		ExternalModules::onShutDown();

		if($actionWhileShuttingDown !== null){
			$actionWhileShuttingDown();
		}

		self::$shuttingDown = false;
	}

	/**
	 * @param string $title
	 * @param int $purpose
	 */
	static function createProject($title, $purpose, $project_note=null){
		global $auth_meth_global;
		$userid = ExternalModules::getUsername();

		if ($title == "" || $title == null) throw new Exception("ERROR: Title can't be null or blank!");
		$title = \Project::cleanTitle($title);
		$new_app_name = \Project::getValidProjectName($title);
		$log_event_table = \Logging::getSmallestLogEventTable();
		$data_table = \Records::getSmallestDataTable();

		if (!is_numeric($purpose) || $purpose < 0 || $purpose > 4) throw new Exception("ERROR: The purpose has to be numeric and it's value between 0 and 4.");

		$auto_inc_set = 1;

		$GLOBALS['__SALT__'] = substr(sha1(rand()), 0, 10);

		if ($userid === null) {
			$ui_id = null;
		} else {
			$user_id_result = ExternalModules::query("select ui_id from redcap_user_information where username = ? limit 1", [$userid]);
			$ui_id = $user_id_result->fetch_assoc()['ui_id'];
		}

		$projectColumns = "project_name, purpose, app_title, creation_time, created_by, auto_inc_set, project_note,auth_meth,__SALT__,log_event_table,data_table";
		$columnValues = [$new_app_name, $purpose, $title, NOW, $ui_id, $auto_inc_set, trim($project_note ?? ''), $auth_meth_global, $GLOBALS['__SALT__'], $log_event_table, $data_table];

		ExternalModules::query("insert into redcap_projects (" . $projectColumns . ") values(" . rtrim(str_repeat("?,", count($columnValues)), ",") . ")",
			$columnValues);

		// Get this new project's project_id
		$pid = db_insert_id();

		// Insert project defaults into redcap_projects
		\Project::setDefaults($pid);

		$logDescrip = "Create project";
		\Logging::logEvent("", "redcap_projects", "MANAGE", $pid, "project_id = $pid", $logDescrip);

		// Give this new project an arm and an event (default)
		\Project::insertDefaultArmAndEvent($pid);
		// Now add the new project's metadata
		$form_names = createMetadata($pid, 0);

		if ($userid !== null) {
			## USER RIGHTS
			// Insert user rights for this new project for user REQUESTING the project
			\Project::insertUserRightsProjectCreator($pid, $userid, 0, 0, $form_names);
		}

		return $pid;
	}

	static function getArmById($project, $armId){
		foreach($project->events as $arm){
			if($arm['id'] === (string) $armId){
				return $arm;
			}
		}

		return null;
	}

	static function getArmByName($project, $armName){
		foreach($project->events as $arm){
			if($arm['name'] === $armName){
				return $arm;
			}
		}

		return null;
	}

	static function convertSettingValueForExport($project, $type, $value){
		if($value === null){
			// Do nothing, important for empty sub-setting values
		}
		else if($type === 'arm-list'){
			$value = ExternalModules::getArmById($project, $value)['name'] ?? null;
		}
		else if($type === 'event-list'){
			$value = $project->getUniqueEventNames()[$value] ?? null;
		}
		else if($type === 'user-role-list'){
			$value = ExternalModules::getRoleName($project->project_id, $value);
		}
		else if($type === 'dag-list'){
			$value = $project->getGroups()[$value] ?? null;
		}

		return $value;
	}

	static function convertSettingValueForImport($project, $type, $value){
		if($value === null){
			// Do nothing, important for empty sub-setting values
		}
		else if ($type === 'arm-list'){
			$value = ExternalModules::getArmByName($project, $value)['id'] ?? null;    
		}
		else if ($type === 'event-list'){
			$eventsByName = array_flip($project->getUniqueEventNames());
			$value = $eventsByName[$value] ?? null;
		}
		else if ($type === 'user-role-list'){
			$value = ExternalModules::getRoleId($project->project_id, $value);
		}
		else if ($type === 'dag-list'){
			$groupsByName = array_flip($project->getGroups());
			$value = $groupsByName[$value] ?? null;
		}

		return $value;
	}

    static function getRoleName($pid, $roleId){
        $result = self::query("
            select role_name
            from redcap_user_roles
            where project_id = ?
                and role_id = ?
        ", [$pid, $roleId]);

        $row = $result->fetch_assoc();
        if($result->fetch_assoc() !== null){
            throw new Exception("More than one row exists for project ID " . $pid . " and role '$roleId'!");
        }

        return $row['role_name'] ?? null;
    }

	static function getRoleId($pid, $roleName){
        $result = self::query("
            select role_id
            from redcap_user_roles
            where project_id = ?
                and role_name = ?
        ", [$pid, $roleName]);

        $row = $result->fetch_assoc();
        if($result->fetch_assoc() !== null){
            throw new Exception("More than one row exists for project ID " . $pid . " and role '$roleName'!");
        }

        return $row['role_id'] ?? null;
    }

    #region JSMO Ajax Requests

    /**
     * This sections implements a general purpose AJAX request mechanism for external modules.
     * Calls to the JavascriptObjectModule.ajax(action, payload) method will initiate a call to
     * the server that will be handled by the corresponding module's implementation of the
     * redcap_module_ajax hook.
     * The framework will ensure that context is preserved and that basic security is taken
     * care of (such as spoofing module prefixes, project ids, etc.).
     */

    /** Name of the config.json setting specifying allowed ajax actions in an authenticated context */
    const MODULE_AUTH_AJAX_ACTIONS_SETTING = "auth-ajax-actions";
    
    /** Name of the config.json setting specifying allowed ajax actions in a non-authenticated context */
    const MODULE_NO_AUTH_AJAX_ACTIONS_SETTING = "no-auth-ajax-actions";
    
    /**
     * The name of the EM hook that must be implemented by modules to be able to make AJAX requests
     * from the JavascriptModuleObject.
     */
    const MODULE_AJAX_HOOK_NAME = "redcap_module_ajax";

    /**
     * The name of the logging action used in JSMO.ajax()
     */
    const MODULE_AJAX_LOGGING_ACTION = "__EM_RESERVED_AJAX_LOG_ACTION";

    /**
     * Initializes REDCap Crypto object with fixed, EM Framework-specific keys (derived from the 
     * REDCap salt and the APP_PATH_DOCROOT; thus, this will be pretty unique for each REDCap instance).
     * If deemed necessary, a further randomly generated value that is stable for a REDCap instance 
     * and stored in a config table could be used.
     * @return Crypto 
     */
    public static function initAjaxCrypto($token) {
		if(empty($token)){
			throw new Exception('A token must be specified for ajax encryption.');
		}

        $rc_salt = $GLOBALS["salt"];
        $blobkey = hash("sha256", "BlobKey-JSMO-Ajax-$rc_salt-$token");
        $hmackey = hash("sha256", "HmacKey-$blobkey");
        return \Crypto::init($blobkey, $hmackey);
    }

    /**
     * Returns a response with the specified error
     * @return array{success:bool,error:string}
     */
    public static function getFailedRequestAjaxResponse($error) {
        return array(
            "success" => false,
            "error" => $error ?? "Invalid request."
        );
    }

    /**
     * Creates an ajax response of the given type with payload and verfication data.
     * @param bool $success 
     * @param mixed $payload 
     * @param string $verification Encrypted verification data
     * @param string $error_message Error message (optional)
     * @return array{success:string,payload:mixed,verification:string} The success response with payload
     */
    public static function getAjaxResponse($success, $payload, $verification, $error_message = "") {
        $response = array(
            "success" => $success,
            "payload" => $payload,
            "verification" => $verification,
            "error" => $error_message,
        );
        return $response;
    }

    /**
     * Verifies that context information (module prefix, project id or survey hash, and user)
     * matches that included in the encrypted verification data.
     * @param array{prefix:string,project_id:string,survey_hash:string,user:string} $verification The (decrypted) verificiation data
     * @return bool Indicates whether verification succeeded
     */
    public static function checkAjaxRequestVerificationData($verification) {
        if ($verification === null) return false; // Prevent unit test warnings

        // Matching prefix?
        $prefix = self::getPrefix();
        if ($verification["prefix"] !== $prefix) return false;
        // Matching project id OR survey hash?
        $project_id = self::getProjectId();
        $survey_hash = self::getSurveyHash();
        if ($verification["project_id"] !== $project_id || $verification["survey_hash"] !== $survey_hash) return false;
        // Matching user?
        $user = self::isNoAuth() ? null : self::getUsername();
        if (\System::isSurveyRespondent($user)) {
            $user = null;
        }
        if ($verification["user"] !== $user) return false;
        // Perform more checks? Probably not, as further data won't be available
        return true;
    }

    /**
     * Checks whether the specific Ajax or API action is allowed for the module identified by prefix.
     * @param string $action 
     * @param string $prefix 
     * @param string $setting_name
     * @return bool 
     */
    public static function isValidAjaxOrApiAction($action, $prefix, $setting_name) {
        $config = self::getConfig($prefix);
        return isset($config[$setting_name]) && is_array($config[$setting_name]) && in_array($action, $config[$setting_name], true);
    }

    /**
     * Handles an ajax request that was received for a module.
     * 
     * @param array{action:string,payload:mixed,verification:string,redcap_external_module_csrf_token:string,redcap_csrf_token:string} $data The request data (action, payload, and verification)
     * @return array The response to be sent back to the browser client
     */
    public static function handleAjaxRequest($data) {
		try {
			// Check CSRF token
			$csrfToken = self::checkCSRFToken(null);

			// Check verification
			$crypto = self::initAjaxCrypto($csrfToken);
			/** @var array{action:string,payload:string,project_id:string,record:string,instrument:string,event_id:string,group_id:string,survey_hash:string,response_id:string,survey_queue_hash:string,repeat_instance:int,page:string,page_full:string} */
			$verification = $crypto->decrypt($data["verification"]);
			if (!self::checkAjaxRequestVerificationData($verification)){
				return self::getFailedRequestAjaxResponse("AJAX request verification failed");
			}
			// Instantiate module
			$prefix = $verification["prefix"];
			$user = $verification["user"];

			// Is this a special action?
			$action = $data["action"];
			if ($action == self::MODULE_AJAX_LOGGING_ACTION) {
				$framework = self::getFrameworkInstance(self::getPrefix());

				// EM Framework logging action
				// Check that ajax logging is enabled and that the module implements the ajax logging hook
				if (!$framework->isAjaxLoggingEnabled()) {
					return self::getFailedRequestAjaxResponse("The config.json setting '" . Framework::MODULE_ENABLE_AJAX_LOGGING_SETTING ."' must be set to 'true' in order to use the javascript module object's log() method.");
				}
				// Check that no-auth logging is enabled if in no-auth context
				if ($user == null) {
					$framework->requireNoAuthLoggingEnabled();
				}
				// Decode payload
				$payload = json_decode($data["payload"], true);
				$message = isset($payload["msg"]) ? ($payload["msg"] ?? "") : "";
				$parameters = isset($payload["params"]) ? ($payload["params"] ?? []) : [];
				// In case there is no record entry in parameters, add it if a record is known
				if (!isset($parameters["record"]) && !empty($verification["record"])) {
					$parameters["record"] = $verification["record"];
				}
				// Execute the logging call
				$response = $framework->logAjax($message, $parameters, $verification);
			}
			else {
				// User action
				// Does the module implement the ajax hook?
				$module = self::getModuleInstance($prefix);
				if (!method_exists($module, self::MODULE_AJAX_HOOK_NAME)) {
					return self::getFailedRequestAjaxResponse("The module '$prefix' does not implement the '" . self::MODULE_AJAX_HOOK_NAME . "' hook.");
				}

				// Is the action allowed?
				$action_list_setting_name = $user == null ? self::MODULE_NO_AUTH_AJAX_ACTIONS_SETTING : self::MODULE_AUTH_AJAX_ACTIONS_SETTING;
				if (!self::isValidAjaxOrApiAction($action, $prefix, $action_list_setting_name)) {
					return self::getFailedRequestAjaxResponse("The requested action must be specified in the '$action_list_setting_name' array in 'config.json'!");
				}

				// Decode payload
				$payload = json_decode($data["payload"], true);
				// Verification passed .. let's set some context that the framework may expect
				if ($verification['project_id'] != null) {
					self::setProjectId($verification["project_id"]);
					if (!empty($verification["instrument"]) && empty($verification["survey_hash"])) {
						$_GET["page"] = $verification["instrument"];
						if (is_numeric($verification["event_id"])) $_GET["event_id"] = $verification["event_id"];
						if (is_numeric($verification["repeat_instance"])) $_GET["instance"] = $verification["repeat_instance"];
					}
					else if (!empty($verification["survey_hash"])) {
						$_GET["s"] = $verification["survey_hash"];
					}
					else if (!empty($verification["survey_queue_hash"])) {
						$_GET["sq"] = $verification["survey_queue_hash"];
					}
				}
				else {
					// Clear some items from $_GET that must not be there outside a valid project context
					self::setProjectId(null);
				}
				// Furthermore, we are in a trusted context, thus disable the user-based checks
				// (module authors should be able to set any settings they want from PHP code)
				$module->disableUserBasedSettingPermissions();

				// Call module ajax hook
				// Signature: redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance, $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id)
				// The hook might return a result, which will be added as "payload" to the response object:
				// [
				//    "success" => true,
				//    "payload" => $response,
				//    "verification" => $verification
				// ]
				$response = $module->{self::MODULE_AJAX_HOOK_NAME}(
					$action,
					$payload,
					$verification["project_id"],
					$verification["record"],
					$verification["instrument"],
					$verification["event_id"],
					$verification["repeat_instance"],
					$verification["survey_hash"],
					$verification["response_id"],
					$verification["survey_queue_hash"],
					$verification["page"],
					$verification["page_full"],
					$user,
					$verification["group_id"]
				) ?? null;
			}

			// Update verification timestamp and random
			$verification["timestamp"] = time();
			$verification["random"] = $crypto->genKey();

			return self::getAjaxResponse(true, $response, $crypto->encrypt($verification));			
		}
		catch (\Throwable $t) {
			$message = 'The following error occurred while performing a module ajax request: ' . $t->getMessage();
			self::errorLog($message . "\nThe error occurred on the following page:" . $_SERVER['REQUEST_URI'] . "\n" . $t);
			return self::getFailedRequestAjaxResponse("$message.  See the server error log for details.");
		}
		finally{
			/**
			 * Make sure the active module prefix is unset to avoid emails from expected edge cases involving
			 * ajax requests from browser tabs opened prior to a module being disabled, deleted, etc.
			 * See GitHub issue #561 for an example.
			 */
			static::setActiveModulePrefix(null);
		}
    }

    #endregion

    #region API requests

    /**
     * This sections implements a general purpose API request mechanism for external modules.
     * Authentication via API token is taken care of by REDCap's API mechanism.
     */

    /** Name of the config.json setting specifying the API actions provided by a module */
    const MODULE_API_ACTIONS_SETTING = "api-actions";
    const API_DESCRIPTION_ALLOWED_TAGS = "<a><acronym><b><br><code><div><em><i><hr><label><li><ol><p><pre><span><strike><strong><style><sub><sup><table><tbody><td><tfoot><th><thead><tr><u><ul>";

    /** Prefix for reserved module API actions */
    const MODULE_API_RESERVED_ACTIONS_PREFIX = "__";

    /** Reserved module API actions - these will be handled by the framework if a module implements the API hook */
    const MODULE_API_RESERVED_ACTIONS = [
        "version",  // gets the module's version, the framework version, and the REDCap version
        "info", // gets full info, including name, description, and authors
        "actions", // gets a list of permitted API actions
    ];

    /**
     * The name of the EM hook that must be implemented by modules to be able handle API requests.
     */
    const MODULE_API_HOOK_NAME = "redcap_module_api";

	/**
	 * Gets the module version without the "v" prefix
	 * @param AbstractExternalModule $module 
	 * @return string
	 */
	public static function getModuleVersion($module) {
		return substr($module->VERSION, 1);
	}

    /**
     * Handles an API request that was received for a module.
     * 
     * TODO: Should API error messages be abstracted into English.ini?
     * 
     * @psalm-suppress PossiblyUnusedMethod
     * 
     * @param array $data The payload data
     * @param array $meta Metadata (project and user info)
     * @return void
     */
    public static function handleApiRequest($data, $meta) {
        try {
            $project_id = $meta["projectid"] ?? null; // Not available for NO-AUTH calls
            $user = $meta["username"] ?? null; // Not available for NO-AUTH calls
            $action = "".$meta["action"]; // Present, since required, ensure string
            $_GET["prefix"] = "".$meta["prefix"]; // Inject prefix
            $prefix = self::getPrefix(); // Sanitize prefix
            $format = $meta["format"]; // Verified/set by REDCap
            $returnFormat = $meta["returnFormat"]; // Verified/set by REDCap
            $csvDelim = $meta["csvDelim"]; // Verified/set by REDCap

            #region Handle File Uploads

            // Add any files to the the hook payload (data)
            foreach ($_FILES as $key => $file) {
                $data[$key] = $file;
            }

            #endregion

            #region Early checks

            // Check if the module is enabled
            if (!self::isModuleEnabled($prefix)) {
                throw new ModuleApiException("The module with the prefix '$prefix' is not enabled on this REDCap instance.", 404);
            }
            // When in project-context ...
            if ($project_id !== null) {
                // Does the user have module api rights? (there is always a user when in project-context)
                if (intval($meta["api_modules"]) !== 1) {
                    throw new ModuleApiException("Insufficient rights to access external module-provided API methods in this context.", 401);
                }
                // Is the module enabled in this project?
                if (!self::isModuleEnabled($prefix, $project_id)) {
                    throw new ModuleApiException("The module with the prefix '$prefix' is not enabled in this context (PID $project_id).", 404);
                }
            }
            /** @var AbstractExternalModule */
            try {
                /** @var AbstractExternalModule */
                $module = self::getModuleInstance($prefix);
            }
            catch (\Throwable $t) {
                throw new ModuleApiException("Failed to instantiate the module '$prefix'.", 501);
            }
            // Does the module implement the API hook?
            if (!method_exists($module, self::MODULE_API_HOOK_NAME)) {
                throw new ModuleApiException("The module '$prefix' does not implement the '" . self::MODULE_API_HOOK_NAME . "' hook.", 501);
            }
            // Does the action fit requirements?
            if (!(strpos($action, self::MODULE_API_RESERVED_ACTIONS_PREFIX) === 0 || self::validateApiActionName($action))) {
                throw new ModuleApiException("Invalid action name.", 400);
            }

            #endregion

            $config = self::getConfig($prefix);
            $auth_actions = [];
            $no_auth_actions = [];
            foreach ($config[self::MODULE_API_ACTIONS_SETTING] as $this_action => $this_action_data) {
                if (in_array("auth", $this_action_data["access"])) {
                    $auth_actions[$this_action] = strip_tags($this_action_data["description"]);
                }
                if (in_array("no-auth", $this_action_data["access"])) {
                    $no_auth_actions[$this_action] = strip_tags($this_action_data["description"]);
                }
            };

            #region Special actions provided by the framework
            // Special actions return some information about the module when called in an
            // authenticated context
            if (strpos($action, self::MODULE_API_RESERVED_ACTIONS_PREFIX) === 0) {
                $reserved_action = mb_substr($action, mb_strlen(self::MODULE_API_RESERVED_ACTIONS_PREFIX));
                if (in_array($reserved_action, self::MODULE_API_RESERVED_ACTIONS, true)) {
                    // The reserved actions require authentication
                    if ($user == null) {
                        throw new ModuleApiException("Reserved actions require authentication.", 401);
                    }
                    if ($reserved_action == "version") {
                        $response_data = [
                            "redcap-version" => REDCAP_VERSION,
                            "framework-version" => self::getFrameworkVersion($module),
                            "module-version" => self::getModuleVersion($module),
                        ];
                    }
                    else if ($reserved_action == "info") {
                        $response_data = [
                            "redcap-version" => REDCAP_VERSION,
                            "framework-version" => self::getFrameworkVersion($module),
                            "module-version" => self::getModuleVersion($module),
                            "name" => $config["name"] ?? "",
                            "description" => $config["description"] ?? "",
                            "authors" => $config["authors"] ?? [],
                            "auth-actions" => $auth_actions,
                            "no-auth-actions" => $no_auth_actions,
                        ];
                        if (!isset($config["include-authors-in-api-info"]) || $config["include-authors-in-api-info"] !== true) {
                            unset($response_data["authors"]);
                        }
                    }
                    else if ($reserved_action == "actions") {
                        $response_data = [
                            "auth-actions" => $auth_actions,
                            "no-auth-actions" => $no_auth_actions,
                        ];
                    }
                    else {
                        throw new ModuleApiException("Reserved action '$action' is not implemented", 501);
                    }
                    if ($returnFormat == "json") {
                        \RestUtility::sendResponse(200, json_encode($response_data, JSON_UNESCAPED_UNICODE));
                    }
                    if ($returnFormat == "xml") {
                        $xml_start = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<response>";
                        $xml_end = "\n</response>";
                        $response = $xml_start;
                        function add_xml($data) {
                            $rv = "";
                            foreach ($data as $key => $val) {
                                if (is_string($val)) {
                                    $rv .= "\n<$key><![CDATA[$val]]></$key>";
                                }
                                else if (is_numeric($val)) {
                                    $rv .= "\n<$key>$val</$key>";
                                }
                                else if (is_array($val) && $key == "authors") {
                                    $rv .= "\n<$key>";
                                    foreach ($val as $_ => $author) {
                                        $rv .= "\n<author>";
                                        $rv .= add_xml($author);
                                        $rv .= "\n</author>";
                                    }
                                    $rv .= "\n</$key>";
                                }
                            }
                            return $rv;
                        }
                        $response .= add_xml($response_data);
                        $response .= $xml_end;
                        \RestUtility::sendResponse(200, $response);
                    }
                    if ($returnFormat == "csv") {
                        $csv = self::createApiCsvString($response_data, $csvDelim, true);
                        \RestUtility::sendResponse(200, $csv);
                    }
                    throw new ModuleApiException("Return format '$returnFormat' is not implemented.", 501);
                }
                else {
                    throw new ModuleApiException("Invalid reserved action '$action'.", 400);
                }
            }
            #endregion

            #region Permissions and action checks

            // Permission check
            $sys_settings = self::getSystemSettingsAsArray($prefix);
            $auth_api_disabled = isset($sys_settings[self::KEY_RESERVED_DISABLE_AUTH_API]) 
                && ($sys_settings[self::KEY_RESERVED_DISABLE_AUTH_API]["system_value"] == true);
            $no_auth_api_disabled = isset($sys_settings[self::KEY_RESERVED_DISABLE_NO_AUTH_API]) 
                && ($sys_settings[self::KEY_RESERVED_DISABLE_NO_AUTH_API]["system_value"] == true);
            
            $proj_settings = $project_id ? self::getProjectSettingsAsArray($prefix, $project_id, false) : [
                self::KEY_RESERVED_DISABLE_AUTH_API => [ "value" => false ],
            ];
            $auth_api_enabled = (!isset($proj_settings[self::KEY_RESERVED_DISABLE_AUTH_API]) && !$auth_api_disabled)
                || ($proj_settings[self::KEY_RESERVED_DISABLE_AUTH_API]["value"] === false);

            if ($user == null) {
                if ($no_auth_api_disabled) {
                    throw new ModuleApiException("Non-authenticated API access is disabled for this module.", 403);
                }
            }
            else {
                if (!$auth_api_enabled) {
                    throw new ModuleApiException("Authenticated API access is disabled for this module.", 403);
                }
            }
            // Is the requested action allowed?
            if (!(array_key_exists($action, $auth_actions) || array_key_exists($action, $no_auth_actions))) {
                throw new ModuleApiException("The requested action is not specified in the '" . self::MODULE_API_ACTIONS_SETTING . "' array in 'config.json'!", 400);
            }
            // Is the requested action allowed in the current auth context (auth/no-auth)?
            if ($user !== null) { // auth
                if (!array_key_exists($action, $auth_actions)) {
                    throw new ModuleApiException("The requested action is not allowed for authenticated users.", 403);
                }
            }
            else { // no-auth
                if (!array_key_exists($action, $no_auth_actions)) {
                    throw new ModuleApiException("The requested action requires authentication.", 403);
                }
            }

            #endregion

            #region Hook execution and processing of hook result

            if ($project_id !== null) {
                // Set some context that the framework might expect
                self::setProjectId($project_id);
            }
            // Disable the user-based checks as module authors should be able to set any 
            // settings they want from PHP code
            $module->disableUserBasedSettingPermissions();
            // Call module API hook
            // Signature: redcap_module_api($action, $payload, $project_id, $user_id, $format, $returnFormat, $csvDelim)
            // The hook might return a result [status_code, body], which will be returned as is.
            try {
                $result = $module->{self::MODULE_API_HOOK_NAME}(
                    $action,
                    $data,
                    $project_id,
                    $user,
                    $format,
                    $returnFormat,
                    $csvDelim
                ) ?? [ "status" => 200, "body" => "" ];
            }
            catch (\Throwable $t) {
                // Exception in hook - log it!
                self::errorLog(self::tt("em_errors_32", $prefix, self::MODULE_API_HOOK_NAME) . "\n" . json_encode([
                    'Project' => $project_id,
                    'Prefix' => $prefix,
                    'Action' => $action,
                    'Payload' => $data,
                    'User' => $user,
                    'Exception Code' => $t->getCode(),
                    'File' => $t->getFile(),
                    'Line' => $t->getLine(),
                    'Trace' => $t->getTrace()
                ], JSON_PRETTY_PRINT|JSON_PARTIAL_OUTPUT_ON_ERROR));
                // Retrow
                throw new ModuleApiException(self::tt("em_errors_186"));
            }
            // Validate the response
            if (is_array($result)) {
                if (!isset($result["status"]) || (!isset($result["body"]) && !isset($result["file"]))) {
                    throw new ModuleApiException("API hook result must contain 'status' and 'body' or 'file' keys.");
                }
                else if (isset($result["body"]) && isset($result["file"])) {
                    throw new ModuleApiException("API hook result can only contain either the 'body' or the 'file' key.");
                }
                else if (isset($result["file"])) {
                    // file must specify path or contents, name, and type
                    if (
                        (!isset($result["file"]["path"]) && !isset($result["file"]["contents"])) || 
                        !isset($result["file"]["name"]) || 
                        !isset($result["file"]["type"]) 
                    ) {
                        throw new ModuleApiException("API hook file results must contain keys 'path', 'name', and 'type'.");
                    }
                    else if (!file_exists($result["file"]["path"])) {
                        throw new ModuleApiException("API hook returned a non-existant file.");
                    }
                }
            }
            else if (is_string($result) || is_int($result)) {
                $result = [ "status" => 200, "body" => $result ];
            }
            else {
                throw new ModuleApiException("API hook result must be an array with keys 'status' and 'body' or 'file', a string, an int, or null.");
            }
            // Send response
            if (isset($result["body"])) {
                \RestUtility::sendResponse($result["status"], $result["body"]);
            }
            else if (isset($result["file"]["path"])){
                \RestUtility::sendFile(200, $result["file"]["path"], $result["file"]["name"], $result["file"]["type"]);
            }
            else if (isset($result["file"]["contents"])){
                \RestUtility::sendFileContents(200, $result["file"]["contents"], $result["file"]["name"], $result["file"]["type"]);
            }

            #endregion
        }
        catch(ModuleApiException $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            \RestUtility::sendResponse($code, $message);
        }
        catch (Throwable $t) {
            // Unknown exception or error
            $message = "The API request failed: ". $t->getMessage();
            \RestUtility::sendResponse(500, $message);
        }
    }

    /**
     * Creates a CSV representation of the given data structure.
     * The data structure must be a nested array where the first dimension 
     * represents the column names and the second dimension represents the 
     * column values. The array can be staggered (i.e., not all columns must be of the same length).
     * Example:  
     * [ "ColA" => [ "A1", "A2" ],  "ColB" => [ "B1", "B2", "B3" ] ]  
     * will produce this CSV (comma set as delimiter):  
     * ColA,ColB  
     * A1,B1  
     * A2,B2  
     * ,B3  
     * @param array $data The data structure
     * @param string $delim The delimiter
     * @param bool $add_bom Whether to add (prepend) the UTF8 byte order mark (recommended for files that need to be opened my Excel)
     * @return string
     */
    static function createApiCsvString($data, $delim, $add_bom = true) {
        $csv = [];
        function make_csv($data, &$csv) {
            foreach ($data as $key => $val) {
                if (!isset($csv[$key])) {
                    $csv[$key] = [];
                }
                if (is_array($val)) {
                    foreach ($val as $i_val) {
                        if (is_array($i_val)) {
                            make_csv($i_val, $csv);
                        }
                        else {
                            $csv[$key][] = $i_val;
                        }
                    }

                }
                else {
                    // Treat as string
                    $val = "".$val;
                    $wrap_in_quotes = strpos($val, '"') !== false || strpos($val, "\n") !== false;
                    $csv[$key][] = $wrap_in_quotes 
                        ? ('"' . str_replace('"', '""', $val) . '"')
                        : $val;
                }
            }
        }
        make_csv($data, $csv);
        $n_rows = array_reduce($csv, function($c, $a) {
            return max($c, count($a));
        }, 1);
        $response = join($delim, array_keys($csv));
        for ($i = 0; $i < $n_rows; $i++) {
            $row = [];
            foreach ($csv as $vals) {
                $row[] = $vals[$i] ?? "";
            }
            $response .= "\n".join($delim, $row);
        }
        if ($add_bom) {
            $response = addBOMtoUTF8($response);
        }
        return $response;
    }

    /**
     * Checks whether any API actions are defined for a module
     * @param mixed $config The module's configuration object
     * @return bool 
     */
    static function definesApiActions($config) {
        return isset($config[self::MODULE_API_ACTIONS_SETTING]) && 
            is_array($config[self::MODULE_API_ACTIONS_SETTING]) && 
            count($config[self::MODULE_API_ACTIONS_SETTING]);
    }

    /**
     * Gets API action info for the given project
     * @param string|int $pid 
     * @return array 
     */
    static function getEnabledApiActions($pid) {
        $rv = [];
        $enabled_modules = self::getEnabledModules($pid);
        foreach ($enabled_modules as $prefix => $version) {
            $config = self::getConfig($prefix, $version, $pid, true);
            if (self::definesApiActions($config)) {
                $actions = [];
                foreach ($config[self::MODULE_API_ACTIONS_SETTING] as $action => $action_data) {
                    $access = [];
                    if (in_array("auth", $action_data["access"])) {
                        $access[] = "A";
                    }
                    if (in_array("no-auth", $action_data["access"])) {
                        $access[] = "N";
                    }
                    $actions[$action] = [
                        "name" => $action,
                        "desc" => self::getApiActionDescription($action, $config),
                        "access" => join(",", $access),
                    ];
                }
                $rv[$prefix] = [
                    "name" => $config["name"],
                    "actions" => $actions,
                ];
            }
        }
        return $rv;
    }

    /**
     * Extracts an API action description from a module config
     * @param string $action The action
     * @param array $config The module config
     * @return string The description (may contain HTML)
     */
    static function getApiActionDescription($action, $config) {
        if (isset($config[self::MODULE_API_ACTIONS_SETTING]) &&
            isset($config[self::MODULE_API_ACTIONS_SETTING][$action])) {
            return $config[self::MODULE_API_ACTIONS_SETTING][$action]["description"] ?? "";
        }
        return "";
    }

    /**
     * Checks wherther an API action fits the requirements
     * @param string $action 
     * @return bool 
     */
    static function validateApiActionName($action) {
        // String and not empty
        if (!is_string($action) || !strlen($action)) return false;
        // Upper/lower case characters, numbers, underscore, hyphen only; 
		// must start with a letter and cannot end with hyphen or underscore.
        // See https://regex101.com/r/GhO41k/1
        $re = '/^[A-Za-z]([-_A-Za-z0-9]{0,}[A-Za-z0-9]){0,1}$/m';
        if (!preg_match($re, $action)) return false;
        return true;
    }

	/**
	 * Gets API action info for the given project/or all enabled modules on the system as a HTML table
	 * @param int $pid Project ID or 0 (when called from Control Center)
	 */
	static function getApiActionsInfoTableForEnabledModules($pid) {
		$module_api_actions = \RCView::tt("em_manage_159", "i"); //= No actions have been specified.
		$api_actions = ExternalModules::getEnabledApiActions($pid);
		if (!empty($api_actions)) {
			$rows = [];
			foreach ($api_actions as $prefix => $module_data) {
				$rows[] = "<tr class='module-header'><td colspan='2'><b>" . $module_data["name"] . "</b> <i>( " . ExternalModules::tt("em_manage_160") . " " . $prefix . " )</i></td></tr>";
				foreach ($module_data["actions"] as $_ => $action) {
					$rows[] = "<tr><td>{$action["name"]} <i style='color:gray;'>({$action["access"]})</i></td><td>{$action["desc"]}</td></tr>";
				}
			}
			$module_api_actions = 
				"<table class='table table-bordered table-hover table-condensed module-api-actions mt-2'>" . implode("", $rows) . "</table>" .
				"<small>" . ExternalModules::tt("em_manage_153") . "</small>" .
				\RCView::style(<<<END
					table.module-api-actions td {
						padding: 3px 5px !important;
					}
					table.module-api-actions tr.module-header td {
						background-color: #eeeeee;
						border-top: 1px solid black;
					}
				END);
		}
		return $module_api_actions;
	}

    #endregion

	// Copied from https://stackoverflow.com/a/7775949/2044597
	static function copyRecursively($source, $destination){
		mkdir($destination);
		foreach (
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST
			) as $item
		) {
			if ($item->isDir()) {
				mkdir($destination . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
			} else {
				copy($item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
			}
		}
	}

	static function ansiToHtml($string){
		$html = str_replace("\n", "<br>\n", $string);
		$html = str_replace("\t", "&emsp;", $html);
		$html = str_replace('[0;31m', '<span style="color: #e90000">', $html);
		$html = str_replace('[1;31m', '<span style="color: #e90000">', $html);
		$html = str_replace('[30;47m', '<span style="background: #727272;" class="highlighted-text">', $html);
		$html = str_replace('[30;48;5;195m', '<span style="background: #c7ffff">', $html); // blue background at bottom of regular psalm run
		$html = str_replace('[97;41m', '<span style="background: #e90000;" class="highlighted-text">', $html); // red background for regular psalm run errors
		$html = str_replace('[0m', '</span>', $html);

		/**
		 * Not sure what these are...maybe non-standard ansi terminal codes wrapping links?
		 * The sensiolabs/ansi-to-html library couldn't translate them.
		 * Let's just remove them.
		 */
		$start = 0;
		$startMarker = ']8;;';
		$midMarker = '\\';
		$endMarker = ']8;;\\';
		while(($start = strpos($html, $startMarker, $start)) !== false){
			$mid = strpos($html, $midMarker, strlen($startMarker)) + strlen($midMarker);
			$end = strpos($html, $endMarker, strlen($startMarker));

			$replacement = substr($html, $mid, $end-$mid);
			$html = substr_replace($html, $replacement, $start, $end-$start+strlen($endMarker));
		}

		return "
			<style>
				pre{
					margin-left: 20px;
				}
				.highlighted-text{
					color: white;
					padding: 2px;
					padding-top: 1px;
				}
			</style>
			$html
		";
	}

	static function getREDCapRootPath(){
		return dirname(APP_PATH_DOCROOT) . '/';
	}

	static function getREDCapBinPath(){
		return static::getREDCapRootPath() . 'bin/';
	}

	static function getScanScriptPath(){
		return static::getREDCapBinPath() . 'scan';
	}

	static function installScanScriptIfNecessary($force = false){
		$unixScriptPath = static::getScanScriptPath();
		if(!$force && filemtime($unixScriptPath) > 1683146411){
			return true;
		}
		else if(!is_writable(static::getREDCapRootPath())){
			return false;
		}

		$binPath = static::getREDCapBinPath();
		if(!file_exists($binPath)){
			mkdir($binPath);
		}

		/**
		 * Removing "\r" is required since we've found that ExternalModules.php may use
		 * windows newlines, even on some *nix systems, which breaks the following file.
		 */
		$bytesWritten = file_put_contents($unixScriptPath, str_replace("\r", "", '#!/usr/bin/env php
<?php
require_once __DIR__ . "/../redcap_connect.php";
require_once APP_PATH_EXTMOD . "bin/scan.php";
'));

		if($bytesWritten === false){
			return false;
		}

		chmod($unixScriptPath, 0755);

		// The "%~dp0" syntax references the directory from which the batch script exists (in case it was run from a different directory).
		file_put_contents("$unixScriptPath.bat", '@php "%~dp0scan" %*');

		return true;
	}

	static function initializeJSGlobals(){
		?>
		<script>
			ExternalModules.SUPER_USER = <?=SUPER_USER?>;
			ExternalModules.IS_IMPERSONATING_USER = <?=json_encode(\UserRights::isImpersonatingUser())?>;
			ExternalModules.IS_IMPERSONATING_USER_SUPERUSER = <?=json_encode(ExternalModules::isSuperUser())?>;
			ExternalModules.KEY_ENABLED = <?=json_encode(ExternalModules::KEY_ENABLED)?>;
			ExternalModules.OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS = <?=json_encode(ExternalModules::OVERRIDE_PERMISSION_LEVEL_DESIGN_USERS)?>;
			ExternalModules.OVERRIDE_PERMISSION_LEVEL_SUFFIX = <?=json_encode(ExternalModules::OVERRIDE_PERMISSION_LEVEL_SUFFIX)?>;
			ExternalModules.APP_URL_EXTMOD_RELATIVE = <?=json_encode(APP_URL_EXTMOD_RELATIVE)?>;
			ExternalModules.LIB_URL = '<?=APP_URL_EXTMOD_LIB?>login.php?referer=<?=urlencode(APP_URL_EXTMOD)."manager/control_center.php"?>'
				+ '&php_version=<?=urlencode(PHP_VERSION)?>&redcap_version=<?=urlencode(REDCAP_VERSION)?>';
		</script>
		<?php
	}

	static function linkREDCapSourceCode(){
		$vendorPath = __DIR__ . '/../vendor';
		if(static::isProduction() || !is_writable($vendorPath)){
			return;
		}

		$redcapSourceLinkPath = "$vendorPath/redcap-source";
		if(file_exists($redcapSourceLinkPath)){
			unlink($redcapSourceLinkPath);
		}

		symlink('../../redcap_v' . REDCAP_VERSION, $redcapSourceLinkPath);
	}

	static function limitVersion($version, $count){
		$parts = explode('.', $version);
		while(count($parts) > $count){
			array_pop($parts);
		}

		return implode('.', $parts);
	}

	static function getREDCapMinPHPVersion($count = PHP_INT_MAX){
		return static::limitVersion(\System::minimum_php_version_required, $count);
	}

	static function getOverridableSettings($config){
		$settings = [];

		/**
		 * Only loop over the top level of settings, since 'allow-project-overrides' is not allowed in sub_settings.
		 */
		foreach($config['system-settings'] as $setting){
			if(($setting['allow-project-overrides'] ?? false) === true){
				$projectName = $setting['project-name'] ?? null;
				if($projectName !== null){
					$setting['name'] = $projectName;
				}

				$settings[] = $setting;
			}
		}

		return $settings;
	}

	static function getSystemValuesForOverridableSettings($prefix, $config){
		$keys = array_column(static::getOverridableSettings($config), 'key');

		if(empty($keys)){
			return [];
		}
		else{
			return static::getSettingsAsArray($prefix, static::SYSTEM_SETTING_PROJECT_ID, $keys);
		}
	}

	static function getPHPMinVersion($config, $composer){
		$configVersion = $config['compatibility']['php-version-min'] ?? null;
		$composerVersion = $composer['config']['platform']['php'] ?? null;

		if($configVersion === null){
			return $composerVersion;
		}
		else if ($composerVersion === null){
			return $configVersion;
		}
		else{
			if(empty($configVersion)){
				throw new \Exception('The "php-version-min" in config.json is set to an invalid value.');
			}
			else if(empty($composerVersion)){
				throw new \Exception('The composer platform php version is set to an invalid value.');
			} 

			if(version_compare($configVersion, $composerVersion, '<')){
				return $composerVersion;
			}
			else{
				return $configVersion;
			}
		}
	}

	/**
	 * This function exists solely to avoid psalm false positives.
	 * 
	 * @psalm-taint-escape html
	 * @psalm-taint-escape has_quotes
	 */
	static function fakeEscape($value){
		return $value;
	}

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	static function getFieldSQL($args = []){
		$projectId = static::requireProjectId($args['project_id'] ?? null);
		
		$fields = $args['fields'] ?? [];
		if(empty($fields)){
			/**
			 * A list of fields is required to return any rows,
			 * but we might as well fail gracefully if no rows are specified.
			 */
			return "select 'placeholder query that returns zero rows' from ".\Records::getDataTable($projectId)." where 1=2";
		}

		$trim = $args['trim'] ?? false;

		$columns = [];
		for($i=0; $i<count($fields); $i++){
			$fields[$i] = $field = static::sanitizeFieldName($fields[$i]);

			$column = "group_concat(if(field_name = '$field', value, null))";

			if($trim){
				$column = "trim($column)";
			}

			$column .= " as $field";

			$columns[] = $column;
		}

		$sql = "
			select
			" . implode(",\n", $columns) . "
			from ".\Records::getDataTable($projectId)."
			where
				project_id = $projectId
				and field_name in ('" . implode("','", $fields) . "')
			group by project_id, event_id, record
		";

		return $sql;
	}

	/**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
	 * @psalm-taint-escape sql
     *
     * @return null|string
     */
    static function sanitizeFieldName($fieldName){
        return preg_replace('/[^a-zA-Z_0-9]/', '', $fieldName ?? '');
    }

	#region Action Tag Descriptions

	// Modules can add Action Tags (and their descriptions) to config.json.
	// These action tags and their descriptions, along with module information are provided to REDCap for
	// display in the Action Tags popup (action_tag_explain.php) 

	/**
	 * Gets a list of module-provided action tags (considering modules that are active in the given context)
	 * 
	 * This method is called from REDCap core.
	 * @psalm-suppress PossiblyUnusedMethod
	 * 
	 * @param mixed $project_id 
	 * @return void 
	 */
	static function getActionTags($project_id = null) {
		
		$modules = self::getEnabledModules($project_id);
		$module_action_tags = [];

		foreach ($modules as $prefix => $version) {
			$config = self::getConfig($prefix, $version, $project_id, true);
			$actiontags = isset($config["action-tags"]) && is_array($config["action-tags"]) ? $config["action-tags"] : [];
			foreach ($actiontags as $at) {
				$module_action_tags[$prefix][] = [
					"module" => [
						"prefix" => $prefix,
						"version" => $version,
						"name" => $config["name"],
						"docs" => self::getDocumentationUrl($prefix)
					],
					"tag" => $at["tag"],
					"description" => $at["description"]
				];
			}
		}

		return $module_action_tags;
	}

	#endregion

	static function psalmSuppress(){
		/**
		 * Do nothing.  This method exists solely to suppress psalm UnusedVariable warnings.
		 */
	}

	static function updateProjectCache($action){
		$class = new \ReflectionClass('Project');
		$property = $class->getProperty('project_cache');
		$property->setAccessible(true);
		
		$cache = $property->getValue();
		$action($cache);
		$property->setValue(null, $cache);
	}

	static function clearProjectCache($pid){
		static::updateProjectCache(function(&$cache) use ($pid){
			unset($cache[$pid]);
		});
	}

	static function getSystemSettingCache(){
		if(!isset(static::$systemSettingCache)){
			$result = self::getSettings(null, self::SYSTEM_SETTING_PROJECT_ID, [
				ExternalModules::KEY_ENABLED,
				ExternalModules::KEY_DISCOVERABLE
			]);
	
			$cache = [];
			while($row = self::validateSettingsRow($result->fetch_assoc())){
				$prefix = $row['directory_prefix'];
				$key = $row['key'];
				$value = $row['value'];

				$cache[$prefix][$key] = $value;
			}

			static::$systemSettingCache = $cache;
		}

		return static::$systemSettingCache;
	}

	static function downloadModuleZip($modulePath, $tempPath, $exitAction){
		$zipPath = "$tempPath/module.zip";
		if(!@copy($modulePath, $zipPath)){
			$exitAction(1, 'The URL specified does not exist, or you do not have access to it.');
		}

		$zip = new ZipArchive;
		$status = $zip->open($zipPath);
		if($status !== true){
			throw new Exception('Zip open failed with status ' . $status);
		}

		$moduleFolderName = 'zip-contents';
		ExternalModules::normalizeModuleZip($moduleFolderName, $zip);
		if(!$zip->close()){
			throw new Exception('Zip close failed');
		}

		$zip = new ZipArchive;
		$status = $zip->open($zipPath);
		if($status !== true){
			throw new Exception('Zip open number two failed with status ' . $status);
		}

		if(!$zip->extractTo($tempPath)){
			throw new Exception('Zip extract failed');
		}

		if(!$zip->close()){
			throw new Exception('Zip close number two failed');
		}

		$tempPathTwo = $tempPath . '-2';
		rename($tempPath, $tempPathTwo);
		rename("$tempPathTwo/$moduleFolderName", $tempPath);
		ExternalModules::rrmdir($tempPathTwo);
	}

	static function renderComposerCompatibilityIssues($versionsByPrefix){
		try{
			$allPackages = [];
			$setVersion = function($packageName, $prefix, $version) use (&$allPackages){
				$version = ltrim($version, 'v');
				$majorVersion = explode('.', $version)[0];

				if($packageName === 'paragonie/random_compat' && $majorVersion === '9'){
					// Version 9 of this package is just a placeholder and does not conflict with any prior versions
					return;
				}
				else if($packageName === 'doctrine/inflector' && $majorVersion === '1'){
					// Version 1 uses a different namespace and does not conflict with later versions
					return;
				}

				$allPackages[$packageName][$majorVersion][$prefix] = true;
			};

			$setVersions = function($prefix, $dir) use ($setVersion){
				$composerLockPath = "$dir/composer.lock";
				if(!file_exists($composerLockPath)){
					// The module/directory does not use composer.  Nothing to do here.
					return;
				}
		
				$composerLock = json_decode(file_get_contents($composerLockPath));

				foreach($composerLock->packages as $package){
					$name = $package->name;
					$setVersion($name, $prefix, $package->version);
				}
			};

			// Include dependencies from REDCap Core
			$setVersions('REDCap Core', APP_PATH_DOCROOT . 'Libraries');
			// Include dependencies from modules
			foreach($versionsByPrefix as $prefix=>$moduleVersion){
				$setVersions($prefix, static::getModuleDirectoryPath($prefix, $moduleVersion));
			}

			$getConfig = function($prefix){
				$config = static::getConfig($prefix);
				if(!isset($config['name'])){
					// This is a fake prefix for either REDCap Core or the EM Framework.  Fudge the config.
					$config =  [
						'name' => $prefix,
						'authors' => [
							[
								'email' => static::DATACORE_EMAIL
							]
						]
					];
				}

				$config['bolded_name'] = '<b>' . $config['name'] . '</b>';

				return $config;
			};

			$moduleListToString = function($prefixes) use ($getConfig){
				$modules = [];
				foreach(array_keys($prefixes) as $prefix){
					$modules[] = $getConfig($prefix)['bolded_name'];
				}

				if(count($modules) === 1){
					return $modules[0];
				}
				else{
					$lastItem  = array_pop($modules);
					$modules[] = "and $lastItem";

					if(count($modules) === 2){
						$separator = ' ';
					}
					else{
						$separator = ', '; 
					}

					return implode($separator, $modules);
				}
			};

			$modulesByAuthor = [];
			foreach($allPackages as $packageName => $modulesByMajorVersion){
				if(
					
					// If there's only 1 major version, then no major version conflicts exist
					count($modulesByMajorVersion) === 1
					||
					/**
					 * This package uses different namespaces for each major version,
					 * so that they can safely co-exist.
					 */
					$packageName === 'phpseclib/phpseclib'
				){
					continue;
				}

				krsort($modulesByMajorVersion);
				$desiredMajorVersion = array_key_first($modulesByMajorVersion);
				$goodModules = $moduleListToString($modulesByMajorVersion[$desiredMajorVersion]);
				unset($modulesByMajorVersion[$desiredMajorVersion]);

				foreach($modulesByMajorVersion as $badVersion=>$prefixes){
					foreach(array_keys($prefixes) as $prefix){
						$config = $getConfig($prefix);
						$name = $config['bolded_name'];
						$email = $config['authors'][0]['email'];
						$author = "<a style='text-decoration: underline' href='mailto:$email'>$email</a>";
						$modulesByAuthor[$author][$name][] = "Please update the <b>$packageName</b> package from <b>$badVersion.x.x</b> to <b>$desiredMajorVersion.x.x</b> to improve compatibility with $goodModules.";
					}
				}
			}

			$content = '';
			if(!empty($modulesByAuthor)){
				echo "<div class='simpleDialog' id='external-module-composer-conflicts'>
					Potentially incompatible external module composer packages were found.
					Please update all modules to their latest versions.
					If that does not resolve this warning, please email each block of information below to the email address shown above it:
					<br><br>
				";

				echo "<ul>";
				foreach($modulesByAuthor as $author=>$conflictsByModuleName){
					echo "<li style='margin-bottom: 20px'><b>$author</b><ul>";
					foreach($conflictsByModuleName as $name=>$conflicts){
						echo "<li>$name<ul><li>" . implode('</li><li>', $conflicts) . "</li></ul></li>";
					}
					echo "</ul></li>";
				}
				echo "</ul>";
				
				echo "</div>";

				$content = '
					Potentially incompatible composer packages may cause REDCap to crash unexpectedly.
					<button
						onclick="simpleDialog(null, \'Potential Incompatibilities\', \'external-module-composer-conflicts\', \'1200\')"
						class="btn btn-danger btn-xs ml-2">
						View Details
					</button>
				';
			}
		}
		catch(\Throwable $t){
			// Prevent a corrupt composer.lock file in a module dir from causing any exceptions to crash the module list
			
			$sharedMessage = "An error occured while detecting composer dependency conflicts";
			$content = "$sharedMessage.  See the server error log for details.";
			static::errorLog("$sharedMessage: $t");
		}

		if(!empty($content)){
			?>
			<div class='yellow' style='margin-bottom: 15px'>
				<b>WARNING:</b> <?=$content?>
			</div>
			<?php
		}
	}

	private static function getLastLine($path, $seekLimit){
		$f = fopen($path, "r");
		$lastLine = '';

		for($i=-2; $i>-$seekLimit; $i--){
			fseek($f, $i, SEEK_END);
			$c = fgetc($f);

			if($c === "\n"){
				return $lastLine;
			}

			$lastLine = "$c$lastLine";
		}

		throw new \Exception('Last line not found');
	}

	static function ensureFrameworkDevCopyIsUpToDate(){
		$logsHeadPath = __DIR__ . '/../.git/logs/HEAD';
		if(!file_exists($logsHeadPath)){
			// A dev copy of the framework is not checked out.
			return;
		}

		try{
			$lastLine = static::getLastLine($logsHeadPath, 1000);
			$lastLine = explode("\t", $lastLine)[0]; // trim the text description of the HEAD change
			$parts = explode(" ", $lastLine);
			array_pop($parts); // don't need the timezone
			$commitTime = array_pop($parts);

			$oneMonthAgo = time() - 60*60*24*30*3;

			if($commitTime < $oneMonthAgo){
				?>
				<div class="yellow" style="margin-bottom: 15px">
					<b>WARNING:</b> 
					You are using a development copy of the External Module framework that is out of date.
					This may cause unexpected behavior.
					It is recommended to either update REDCap and run "git pull" inside the following directly,
					or to remove the following directory so that the version of the module framework bundled with REDCap is used instead:
					<br><br><pre>&lt;redcap-root&gt;/<?=ExternalModules::DEV_DIR_NAME?></pre>
				</div>
				<?php
			}
		}
		catch(\Exception $e){
			echo "An error occurred while detecting the latest commit.  This can be ignored, but redcap-external-module-framework@vumc.org would appreciate it if you'd work with them to resolve it.";
		}
	}

	static function getCurrentURL(){
		return (isset($_SERVER['HTTPS']) ? "https" : "http") . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
	}

	static function handleError($subject, $message, $prefix){
		$messagePrefix = "External Module Prefix: $prefix\n";
		$messagePrefix .= "Subject: $subject\n";

		if(isset($_SERVER['HTTP_HOST'])){
			$messagePrefix .= "URL: " . static::getCurrentURL() . "\n";
		}

		$messagePrefix .= "Server: " . SERVER_NAME . " (" . gethostname() . ")\n";
		$messagePrefix .= "User: " . self::getUsername() . "\n";

		$pid = static::getProjectId();
		if($pid){
			$messagePrefix .= "Project ID: $pid\n";
		}
		
		$hookRunner = self::getCurrentHookRunner();
		if ($hookRunner) {
			$seconds = time() - $hookRunner->getStartTime();
			$messagePrefix .= "Run Time: $seconds seconds\n";
		}

		$message = "$messagePrefix\n$message";

		if(static::isTesting()){
			// Report back to our test class instead of sending an email.
			static::$lastHandleErrorResult = [$subject, $message, $prefix];
			return;
		}

		\System::addErrorToRCErrorLogTable($message);

		$sql = \DBQueryTool::getRecentErrorsQuery($prefix);
		$sql .= '
			and time_of_error > NOW() - INTERVAL 1 HOUR
			limit 2 -- we only need two knows to know whether or not to email
		';

		$result = static::query($sql, []);
		$result->fetch_assoc(); // Ignore the row we just logged
		if($result->fetch_assoc() !== null){
			// We've already logged other rows and emailed the admin about this module recently.  Don't email them again.
			return;
		}

		static::sendAdminEmail(
			static::tt('em_errors_181'),
			static::tt(
				'em_errors_182',
				$prefix,
				APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . "/ControlCenter/database_query_tool.php?recent-errors-report&external-module-prefix=$prefix",
			),
			$prefix
		);
	}

	static function extractExcludingExtensions($zip, $extensions, $extractionPath){
		$normalizedExtensions = [];
		foreach($extensions as $extension){
			$normalizedExtensions[strtolower($extension)] = true;
		}

		$includedFiles = [];
		for( $i = 0; $i < $zip->numFiles; $i++ ){
			$path = $zip->statIndex($i)['name'];
			$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

			if(isset($normalizedExtensions[$extension])){
				continue;
			}

			$includedFiles[] = $path;
		}

		$zip->extractTo($extractionPath, $includedFiles);
	}
}
