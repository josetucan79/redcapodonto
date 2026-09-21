<?php
namespace ExternalModules;

const TEST_FORM = 'test_form';
const TEST_RECORD_ID = 'test_record_id';
const TEST_TEXT_FIELD = 'test_text_field';
const TEST_TEXT_FIELD_2 = 'test_text_field_2';
const TEST_SQL_FIELD = 'test_sql_field';
const TEST_RADIO_FIELD = 'test_radio_field';
const TEST_CHECKBOX_FIELD = 'test_checkbox_field';
const TEST_YESNO_FIELD = 'test_yesno_field';
const TEST_CALC_FIELD = 'test_calc_field';
const TEST_REPEATING_FORM = 'test_repeating_form';
const TEST_REPEATING_FIELD_1 = 'test_repeating_field_1';
const TEST_REPEATING_FIELD_2 = 'test_repeating_field_2';
const TEST_REPEATING_FIELD_3 = 'test_repeating_field_3';
const TEST_REPEATING_FORM_2 = 'test_repeating_form_2';
const TEST_REPEATING_FORM_2_FIELD_1 = 'test_repeating_form_2_field_1';

const TEST_DAG_NAME = 'Test DAG';
const TEST_ROLE_NAME = 'Test Role';

const EVENT_1 = 'Event 1';
const EVENT_2 = 'Event 2';
const NON_REPEATING = 'Non Repeating';

// These were added simply to avoid warnings from REDCap code.
$_SERVER['SERVER_NAME'] = 'unit testing';
$_SERVER['REMOTE_ADDR'] = 'unit testing';
if(!defined('PAGE')){
	define('PAGE', 'unit testing');
}

require_once __DIR__ . '/../redcap_connect.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/SharedBaseTest.php'; // Required for psalm to run

/**
 * Override the log_all_errors value from the redcap_config table.
 * We make sure log_all_errors is enabled so that any warnings are caught early
 * This is especially helpful for warnings turned errors in PHP8, like "array_keys(null)".
 */
$GLOBALS['log_all_errors'] = '1';

// Required by PHP 8
define('ACCESS_CONTROL_CENTER', true);
define('SUPER_USER', true);
define('ACCESS_ADMIN_DASHBOARDS', true);
define('ACCOUNT_MANAGER', true);
define('ADMIN_RIGHTS', true);
define('ACCESS_EXTERNAL_MODULE_INSTALL', true);
define('ACCESS_SYSTEM_CONFIG', true);
define('USERID', null);

use \Exception;
use REDCap;

const TEST_MODULE_PREFIX = ExternalModules::TEST_MODULE_PREFIX;
const TEST_MODULE_NAME = 'Unit Testing Module';
const TEST_MODULE_TWO_PREFIX = ExternalModules::TEST_MODULE_TWO_PREFIX;
const TEST_MODULE_VERSION = ExternalModules::TEST_MODULE_VERSION;
const TEST_LOG_MESSAGE = 'This is a unit test log message';
const TEST_SETTING_KEY = 'unit-test-setting-key';
const FILE_SETTING_KEY = 'unit-test-file-setting-key';
const TEST_DATA_TABLE = 'redcap_data2';
const MISSING_ARGUMENT_PLACEHOLDER = 'unit-test-missing-argument-placeholder';

require_once ExternalModules::getTestModuleDirectoryPath(TEST_MODULE_PREFIX) . '/TestModule.php';
require_once ExternalModules::getTestModuleDirectoryPath(TEST_MODULE_TWO_PREFIX) . '/TestModuleTwo.php';

$testPIDs = ExternalModules::getTestPIDs();
define('TEST_SETTING_PID', $testPIDs[0]);
define('TEST_SETTING_PID_2', $testPIDs[1]);
define('TEST_SETTING_PID_3', $testPIDs[2]);

// These are defined to avoid warnings
define('APP_NAME', null);
define('PROJECT_ID', null);
if(!isset($GLOBALS['mycap_enabled'])){
	$GLOBALS['mycap_enabled'] = 0;
}
if(!isset($GLOBALS['mycap_enabled_global'])){
	$GLOBALS['mycap_enabled_global'] = 0;
}
if(!isset($GLOBALS['restricted_upload_file_types'])){
	$GLOBALS['restricted_upload_file_types'] = 'exe, js, msi, msp, jar, bat, cmd, com, php';
}

if($GLOBALS['is_development_server'] !== '1'){
	throw new Exception('Tests are not safe on production systems, especially since some of them reset AUTO_INCREMENT.');
}

abstract class BaseTest extends SharedBaseTest
{
	protected $backupGlobals = FALSE;

	private static $originalServerArray;
	private static $testModuleInstance;
	private static $testProjectsInitialized;

	public static function setUpBeforeClass():void{
		parent::setUpBeforeClass();

		// Before any tests run, make sure all error reporting is fully enabled.
		self::ensureErrorReportingIsFullyEnabled();

		// Initialize the CSRF token for tests that require it.
		\Authentication::setAAFCsrfToken(null);
		
		foreach(ExternalModules::getTestPIDs() as $pid){
			// Use something other than the default data table, to ensure all tests take into account numbered data tables
			ExternalModules::query('update redcap_projects set data_table = ? where project_id = ?', [TEST_DATA_TABLE, $pid]);
		}
	}

	function getEventIds($projectId){	
		$sql = '	
			select event_id	
			from redcap_events_arms a	
			join redcap_events_metadata m	
				on m.arm_id = a.arm_id	
			where project_id = ?	
		';	

		$result = self::query($sql, $projectId);	

		$eventIds = [];
		while($row = $result->fetch_assoc()){
			$eventIds[] = $row['event_id'];
		}
		
		return $eventIds;
    }

	protected function spoofEnabledModules($prefixes){
		$versionsByPrefix = [];
		foreach($prefixes as $prefix){
			$versionsByPrefix[$prefix] = TEST_MODULE_VERSION;
		}

		$this->setExternalModulesProperty('systemwideEnabledVersions', $versionsByPrefix);
		$this->setExternalModulesProperty('projectEnabledOverrides', []);
	}

	protected function setUp():void{
		self::$testModuleInstance = new \TestModule\TestModule(TEST_MODULE_PREFIX);

		new TestModuleTwo(TEST_MODULE_TWO_PREFIX); // This line caches the framework instance for prefix two.
		
		$this->spoofEnabledModules([TEST_MODULE_PREFIX, TEST_MODULE_TWO_PREFIX]);

		// Simulate "Enable module on all projects by default"
		$this->setExternalModulesProperty('projectEnabledDefaults', [
			TEST_MODULE_PREFIX => true,
			TEST_MODULE_TWO_PREFIX => true,
		]);

		$this->setFrameworkVersion(ExternalModules::getMaxSupportedFrameworkVersion());

		$this->cleanupSettings();

		if(!self::$testProjectsInitialized){
			foreach([TEST_SETTING_PID, TEST_SETTING_PID_2] as $pid){
				$framework = $this->getFramework();
				
				/**
				 * This delete is important, because it ensures that the importDataDictionary() call
				 * below is succeeding (effectively making it a unit test).
				 */
				$this->query('delete from redcap_metadata where project_id = ?', $pid);

				// Fixes some inconsistencies that occasionally crash importDataDictionary()
				ExternalModules::query('
					delete from redcap_events_forms where event_id in (
						select event_id
						from redcap_events_arms a
						join redcap_events_metadata em
							on a.arm_id = em.arm_id
						where project_id = ?
					)
				', $pid);

				if($pid === TEST_SETTING_PID){
					$repeatFormsValue = 0;
				}
				else{
					$repeatFormsValue = 1;
				}

				ExternalModules::query('
					update redcap_projects
					set
						status = 1, -- Set as in production, to assert that importDataDictionary() works on production projects
						repeatforms = ?
					where project_id = ?
				', [$repeatFormsValue, $pid]);

				$framework->importDataDictionary($pid, __DIR__ . '/test-project-data-dictionary.csv');

				if($pid === TEST_SETTING_PID_2){
					$this->setupSecondProjectEvents();
				}

				$result = $this->query('
					select event_id, descrip
					from redcap_events_arms a	
					join redcap_events_metadata m	
						on m.arm_id = a.arm_id	
					where project_id = ?	
				', $pid);

				while($row = $result->fetch_assoc()){
					$eventId = $row['event_id'];
					$eventName = $row['descrip'];

					$this->query('delete from redcap_events_repeat where event_id = ?', $eventId);

					if($eventName === EVENT_1){
						$this->query('
							insert into redcap_events_repeat values
								(?, ?, null),
								(?, ?, null)
						', [
							$eventId, TEST_REPEATING_FORM,
							$eventId, TEST_REPEATING_FORM_2
						]);
					}
					else if($eventName === EVENT_2){
						$this->query('insert into redcap_events_repeat values (?, null, null)', $eventId);
					}
				}

				list($surveyId, $formName) = $framework->getSurveyId(TEST_SETTING_PID);
				if(empty($surveyId)){
					ExternalModules::query("
						insert into redcap_surveys (project_id, form_name)
						values (?, (
							select form_name from redcap_metadata where project_id = ? limit 1
						))	
					", [$pid, $pid]);
				}

				// Make sure the project cache is reset after our manual queries
				ExternalModules::clearProjectCache($pid);
			}

			self::$testProjectsInitialized = true;
		}

		// Clear the data between tests
		foreach(ExternalModules::getTestPIDs() as $pid){
			$table = \Records::getDataTable($pid);
			self::query("delete from $table where project_id = ?", $pid);
			self::query('delete from redcap_record_list where project_id = ?', $pid);
			self::query('delete from redcap_external_modules_log where external_module_id = ?', ExternalModules::getIdForPrefix(TEST_MODULE_PREFIX)); // faster than removeLogs()
			self::query("update redcap_record_counts set record_count = 0, time_of_count = ? where project_id = ?", [NOW, $pid]);
		}
	}

	protected function setFrameworkVersion($frameworkVersion){
		foreach([TEST_MODULE_PREFIX, TEST_MODULE_TWO_PREFIX] as $prefix){
			$config = [
				'framework-version' => $frameworkVersion,
				Framework::MODULE_ENABLE_NOAUTH_LOGGING_SETTING => true,
			];

			$this->setConfig($config, true, $prefix);
		}
	}

	private function setupSecondProjectEvents(){
		$pid = TEST_SETTING_PID_2;

		$project = ExternalModules::getREDCapProjectObject($pid);

		$existingEventNames = [];
		foreach($project->events as $arm){
			foreach($arm['events'] as $eventId=>$event){
				$eventName = $event['descrip'];
				if(!isset($existingEventNames[$eventName])){
					$existingEventNames[$eventName] = true;
				}
				else{
					throw new Exception("Multiple events exist for test project $pid with the name '$eventName'.  Please delete all but one of them.");
				}
			}
		}

		$desiredEvents = [
			EVENT_1 => [
				'uniqueEventName' => 'event_1_arm_1',
				'forms' => [TEST_FORM, TEST_REPEATING_FORM, TEST_REPEATING_FORM_2]
			],
			EVENT_2 => [
				'uniqueEventName' => 'event_2_arm_1',
				'forms' => [TEST_FORM, TEST_REPEATING_FORM, TEST_REPEATING_FORM_2]
			],
			NON_REPEATING => [
				'uniqueEventName' => 'non_repeating_arm_1',
				'forms' => [TEST_FORM]
			]
		];

		foreach($desiredEvents as $eventName=>$details){
			if(!isset($existingEventNames[$eventName])){
				\Event::create($pid, [
					'arm_num' => 1,
					'day_offset' => 0,
					'offset_min' => 0,
					'offset_max' => 0,
					'event_name' => $eventName
				]);
			}

			foreach($details['forms'] as $form){
				$project->addEventForms([[
					'form' => $form,
					'unique_event_name' => $details['uniqueEventName']
				]]);
			}
		}
	}

	function getFrameworkVersion(){
		return ExternalModules::getMaxSupportedFrameworkVersion();
	}

	protected function tearDown():void
	{
		// Make sure the framework version is a valid one, or the next test's setUp() call will break.
		$this->setConfig([
			'framework-version' => ExternalModules::getMaxSupportedFrameworkVersion()
		]);

		$this->setActiveModulePrefix(null);

		$path = ExternalModules::getAndClearExportedSettingsPath();
		if($path !== null && file_exists($path)){
			unlink($path);
		}

		ExternalModules::$lastHandleErrorResult = null;
	}

	private function cleanupSettings()
	{
		foreach([TEST_MODULE_PREFIX, TEST_MODULE_TWO_PREFIX] as $prefix){
			$m = ExternalModules::getModuleInstance($prefix, TEST_MODULE_VERSION);
			$m->testHookArguments = null;
			
			$moduleId = ExternalModules::getIdForPrefix($prefix);
			$lockName = ExternalModules::getLockName($moduleId, TEST_SETTING_PID);
	
			$m->query("SELECT GET_LOCK(?, 5)", [$lockName]);
			$m->query("delete from redcap_external_module_settings where external_module_id = ?", [$moduleId]);
			$m->query("SELECT RELEASE_LOCK(?)", [$lockName]);
		}

		$_GET = [];
		$_POST = [];
		$_SERVER = self::getOriginalServerArray();

		ExternalModules::setSuperUser(true);
		ExternalModules::setUsername(null);
	}

	private function getOriginalServerArray()
	{
		if(!isset(self::$originalServerArray)){
			self::$originalServerArray = $_SERVER;
		}

		return self::$originalServerArray;
	}

	protected function setSystemSetting($value)
	{
		self::getInstance()->setSystemSetting(TEST_SETTING_KEY, $value);
	}

	protected function getSystemSetting()
	{
		return self::getInstance()->getSystemSetting(TEST_SETTING_KEY);
	}

	protected function removeSystemSetting()
	{
		self::getInstance()->removeSystemSetting(TEST_SETTING_KEY);
	}

	protected function setProjectSetting($valueOrKey, $value = MISSING_ARGUMENT_PLACEHOLDER)
	{
		if($value === MISSING_ARGUMENT_PLACEHOLDER){
			$key = TEST_SETTING_KEY;
			$value = $valueOrKey;
		}
		else{
			$key = $valueOrKey;
		}

		self::getInstance()->setProjectSetting($key, $value, TEST_SETTING_PID);
	}

	protected function getProjectSetting($key = null)
	{
		if($key === null){
			$key = TEST_SETTING_KEY;
		}

		return self::getInstance()->getProjectSetting($key, TEST_SETTING_PID);
	}

	protected function removeProjectSetting()
	{
		self::getInstance()->removeProjectSetting(TEST_SETTING_KEY, TEST_SETTING_PID);
	}

	protected function getInstance()
	{
		return self::$testModuleInstance;
	}

	protected function setConfig($config, $setFrameworkVersionIfMissing = true, $prefix = TEST_MODULE_PREFIX)
	{
		if(gettype($config) === 'string'){
			$config = json_decode($config, true);
			if($config === null){
				throw new Exception("Error parsing json configuration (it's likely not valid json).");
			}
		}

		$config = ExternalModules::normalizeConfigSections($config);

		if($setFrameworkVersionIfMissing && !isset($config['framework-version'])){
			$config['framework-version'] = $this->getFrameworkVersion();
		}

		if(in_array($prefix, [TEST_MODULE_PREFIX, TEST_MODULE_TWO_PREFIX]) && !isset($config['name'])){
			$config['name'] = TEST_MODULE_NAME;
		}

		ExternalModules::setCachedConfig($prefix, TEST_MODULE_VERSION, false, $config);
		ExternalModules::setCachedConfig($prefix, TEST_MODULE_VERSION, true, ExternalModules::translateConfig($config, $prefix));

		// Re-initialize the framework in case the version changed.
		$frameworkInstance = ExternalModules::getFrameworkInstance($prefix, TEST_MODULE_VERSION);
		$this->callPrivateMethodForClass($frameworkInstance, 'initialize');
	}

	protected function callPrivateMethod($methodName)
	{
		$args = func_get_args();
		array_unshift($args, $this->getReflectionClass());

		return call_user_func_array([$this, 'callPrivateMethodForClass'], $args);
	}

	protected function callPrivateMethodForClass()
	{
		$args = func_get_args();
		$classInstanceOrName = array_shift($args); // remove the $classInstanceOrName
		$methodName = array_shift($args); // remove the $methodName

		if(gettype($classInstanceOrName) == 'string'){
			$instance = null;
		}
		else{
			$instance = $classInstanceOrName;
		}

		$class = new \ReflectionClass($classInstanceOrName);
		$method = $class->getMethod($methodName);
		$method->setAccessible(true);

		return $method->invokeArgs($instance, $args);
	}

	protected function getPrivateVariable($name)
	{
		$class = new \ReflectionClass($this->getReflectionClass());
		$property = $class->getProperty($name);
		$property->setAccessible(true);

		$instance = $this->getReflectionClass();
		if(is_string($instance)){
			$instance = null;
		}

		return $property->getValue($instance);
	}

	protected function setPrivateVariable($name, $value, $target = null)
	{
		if(!$target){
			$target = $this->getReflectionClass();
		}

		return parent::setPrivateVariable($name, $value, $target);
	}

	protected function getReflectionClass()
	{
		return $this->getInstance();
    }

	protected function runConcurrentTestProcesses($parentAction, $childAction)
	{
		// The parenthesis are included in the argument and check below so we can still filter for this function manually (WITHOUT the parenthesis)  when testing for testing and avoid triggering the recursion.
		$functionName = $this->getName() . '()';

		global $argv;
		if(end($argv) === $functionName){
			// This is the child process.
			$childAction();
		}
		else{
			// This is the parent process.

			$cmd = "php " . ExternalModules::getPHPUnitPath() . " --filter " . escapeshellarg($functionName);
			$childProcess = proc_open(
				$cmd, [
					0 => ['pipe', 'r'],
					1 => ['pipe', 'w'],
					2 => ['pipe', 'w'],
				],
				$pipes
			);

			// Gets the child status, but caches the final result since calling proc_get_status() multiple times
			// after a process ends will incorrectly return -1 for the exit code.
			$getChildStatus = function() use ($childProcess, &$lastStatus){
				if(!$lastStatus || $lastStatus['running']){
					$lastStatus = proc_get_status($childProcess);
				}

				return $lastStatus;
			};

			$isChildRunning = function() use ($getChildStatus){
				$status = $getChildStatus();
				return $status['running'];
			};

			$parentAction($isChildRunning);

			while($isChildRunning()){
				// The parent finished before the child.
				// Wait for the child to finish before continuing so that the exit code can be checked below.
				usleep(100000);
			}

			$status = $getChildStatus();
			$exitCode = $status['exitcode'];
			$output = stream_get_contents($pipes[1]);
			if($exitCode === 0){
				// Make sure the child test actually ran.
				$this->assertStringContainsString('OK (1 test,', $output);
			}
			else{
				throw new Exception("The child phpunit process for the $functionName test failed with exit code $exitCode and the following output: $output");
			}
		}
	}

	function ensureRecordExists($recordId, $pid = TEST_SETTING_PID){
		REDCap::saveData($pid, 'json', json_encode([[
			$this->getFramework()->getRecordIdField($pid) => $recordId,
		]]));
	}

	function getFramework(){
		return ExternalModules::getFrameworkInstance($this->getInstance()->PREFIX);
	}

	function __call($methodName, $args){
		$callable = [$this->getReflectionClass(), $methodName];
		if(!is_callable($callable)){
			throw new Exception("Not callable: " . $this->getReflectionClass() . '::' . $methodName);
		}

		return call_user_func_array($callable, $args);
	}

	function getActiveModulePrefix(){
		// Call this on the ExternalModules class no matter what test it is called from.
		return $this->callPrivateMethodForClass('ExternalModules\ExternalModules', 'getActiveModulePrefix');
	}

	function setActiveModulePrefix($prefix){
		// Call this on the ExternalModules class no matter what test it is called from.
		return $this->callPrivateMethodForClass('ExternalModules\ExternalModules', 'setActiveModulePrefix', $prefix);
	}

	function saveData($data, $pid = TEST_SETTING_PID){
		if(!is_array($data)){
			throw new Exception("An array of data must be specified.");
		}

		$result = \REDCap::saveData($pid, 'json', json_encode($data));
		if(!empty($result['errors']) || !empty($result['warnings'])){
			throw new Exception('Error saving data: ' . json_encode($result, JSON_PRETTY_PRINT));
		}
	}
	
	function getRandomUsernames($limit = 10, $exclude_admins = false)
	{
		// Always return lowercase usernames so that UserRights calls work properly in all cases
		$sql = "SELECT LOWER(username) as username 
				FROM redcap_user_information
				WHERE user_suspended_time IS NULL";
		if($exclude_admins){
			$sql .= " AND super_user = 0 AND admin_rights = 0";
		}
		$sql .= " ORDER BY RAND() LIMIT ?";
		$result = ExternalModules::query($sql, [$limit]);

		$usernames = [];
		while($row = $result->fetch_assoc()){
			$usernames[] = $row['username'];
		}

		return $usernames;
	}

	function getRandomUsername($exclude_admins = false)
	{
		return $this->getRandomUsernames(1, $exclude_admins)[0];
	}

	function spoofURL($url){
		$parts = explode('://', $url);
		$parts = explode('/', $parts[1]);
		
		$_SERVER['HTTP_HOST'] = array_shift($parts);

		$selfBase = '/' . implode('/', $parts);

		$_SERVER['PHP_SELF'] = $selfBase;
	}
	
	function spoofTranslation($prefix, $key, $value)
	{
		global $lang;

		if(!empty($prefix)){
			$key = ExternalModules::constructLanguageKey($prefix, $key);
		}

		return $lang[$key] = $value;
	}

	function setNoAuth($value){
		if($value){
			$_GET['NOAUTH'] = '';
		}
		else{
			unset($_GET['NOAUTH']);
		}
	}
	
	function deleteRecord($pid, $recordId){
		$this->deleteRecords($pid, [$recordId]);
	}

	function deleteRecords($pid, $recordIds){
		// Should we use REDCap::deleteRecord() instead?
		$q = ExternalModules::createQuery();
		$table = \Records::getDataTable(TEST_SETTING_PID);
		$q->add("delete from $table where project_id = ? and", [$pid]);
		$q->addInClause('record', $recordIds);
		$q->execute();
	}

	private static function ensureErrorReportingIsFullyEnabled(){
		self::assertSame(ini_get('display_errors'), '1', "The 'display_errors' value must be set to '1' in php.ini when running unit tests, to ensure that any errors/warnings/notices are seen.");

		self::assertSame(ini_get('display_startup_errors'), '1', "The 'display_startup_errors' value must be set to '1' in php.ini when running unit tests, to ensure that any errors/warnings/notices are seen.");

		self::assertSame(error_reporting(), E_ALL, "The error_reporting() value must be set to 'E_ALL' when running unit tests, to ensure that any errors/warnings/notices are seen.");

		global $log_all_errors;
		self::assertSame('1', $log_all_errors, "The 'log_all_errors' value must be set to '1' in order to run all unit tests.  This is most easily done via the redcap_config database table.  Specific tests can be run without this flag by using phpunit's '--filter' argument to match them.");
	}

	static function tearDownAfterClass():void{
		// After all tests run, make sure no stray code in the framework, REDCap core, or any modules disabled error reporting in any way.
		self::ensureErrorReportingIsFullyEnabled();
	}

	function setProjectId($pid){
		ExternalModules::setProjectId($pid);
	}

	function setPrefix($prefix){
		$_GET['prefix'] = $prefix;
	}

	function getTestDAGID(){	
		$p = ExternalModules::getREDCapProjectObject(TEST_SETTING_PID);
		
		$groupId = array_keys($p->getGroups())[0] ?? null;
		if($groupId === null){
			$m = $this->getInstance();
			$groupId = $m->createDAG(TEST_DAG_NAME);
		}

		return $groupId;
	}

	function getLargeDBTestValue($size){
		$result = ExternalModules::query("SHOW VARIABLES LIKE 'max_allowed_packet'", []);
		$row = $result->fetch_assoc();
		$maxAllowedPacket = $row['Value'];
		$extraSpaceForTheRestOfThePacket = 1024; // MySQL only allows increasing 'max_allowed_packet' in increments of 1024
		$recommendedMaxAllowedPacket = ExternalModules::SETTING_SIZE_LIMIT+1 + $extraSpaceForTheRestOfThePacket;
		if($maxAllowedPacket < $recommendedMaxAllowedPacket){
			throw new Exception("Your MySQL server's 'max_allowed_packet' setting is less than the recommended value.  Please increase this value to at least $recommendedMaxAllowedPacket for unit tests to run properly, and to avoid errors when saving large module setting values.");
		}

		return str_repeat('a', $size);
	}

	function isRunningOnCI(){
		return getenv('MYSQL_REDCAP_CI_DB') !== false;
	}
}
