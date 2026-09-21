<?php
namespace ExternalModules;
require_once 'BaseTest.php';

use \Exception;
use \RecursiveDirectoryIterator;
use \REDCap;
use \ZipArchive;

class ExternalModulesTest extends BaseTest
{
	const TABLED_CRON_EXAMPLE = '{
		"cron_name": "schedulednotifications",
		"cron_description": "Daily cron to generate new notifications",
		"method": "scheduledNotifications",
		"cron_frequency": "3600",
		"cron_max_run_time": "300"
	}';

	const TIMED_CRON_EXAMPLE = '{
		"cron_name": "cron4",
		"cron_description": "Cron that runs on Mondays at 2:25 pm to do YYYY",
		"method": "some_other_method_4",
		"cron_hour": 14,
		"cron_minute": 25,
		"cron_weekday": 1
	}';

	protected function getReflectionClass()
	{
		return 'ExternalModules\ExternalModules';
	}

	protected function setUp():void
	{
		parent::setUp();

		// Loading this dependency doesn't work at the top of this file.  Not sure why...
		require_once __DIR__ . '/../vendor/squizlabs/php_codesniffer/autoload.php';
	}

	function testInitializeSettingDefaults()
	{
		$defaultValue = rand();

		$this->setConfig([
			'system-settings' => [
				[
					'key' => TEST_SETTING_KEY,
					'default' => $defaultValue
				]
			]
		]);

		$f = $this->getFramework();

		$this->assertNull($this->getSystemSetting());
		ExternalModules::initializeSettingDefaults($f);
		$this->assertSame($defaultValue, $this->getSystemSetting());

		// Make sure defaults do NOT overwrite any existing settings.
		$this->setSystemSetting(rand());
		ExternalModules::initializeSettingDefaults($f);
		$this->assertNotEquals($defaultValue, $this->getSystemSetting());
	}

	function testQuery()
	{
		// The query() method is more thoroughly tested in FrameworkV1Test.
		// We do a basic test here just to make sure any refactoring is caught.
		$value = rand();
		$r = ExternalModules::query('select ?', $value);
		$this->assertSame($value, $r->fetch_row()[0]);
	}

	function testCheckCronModifications()
	{
		$prefix = self::getInstance()->PREFIX;
		$cronAttr1 = array("cron_name" => "Test Cron 1", "cron_description" => "Test", "method" => "testMethod1", "cron_hour" => 1, "cron_minute" => 0);
		$cronAttr2 = array("cron_name" => "Test Cron 2", "cron_description" => "Test", "method" => "testMethod2", "cron_hour" => 2, "cron_minute" => 0);
		$cronAttr3 = array("cron_name" => "Test Cron 3", "cron_description" => "Test", "method" => "testMethod3", "cron_hour" => 3);
		$cronAttr4 = array("cron_name" => "Test Cron 4", "cron_description" => "Test", "method" => "testMethod4", "cron_weekday" => "2", "cron_hour" => 4, "cron_minute" => 0);
		$cronAttr5 = array("cron_name" => "Test Cron 5", "cron_description" => "Test", "method" => "testMethod5", "cron_monthday" => "1", "cron_hour" => 5, "cron_minute" => 0);
		$validCrons = array($cronAttr1, $cronAttr2, $cronAttr4, $cronAttr5);
		$invalidCrons = array($cronAttr1, $cronAttr2, $cronAttr3, $cronAttr4, $cronAttr5);

		ExternalModules::setModifiedCrons($prefix, $validCrons);
		$crons = ExternalModules::getModifiedCrons($prefix);
		$this->assertTrue($crons == $validCrons);

		$this->assertThrowsException(function() use ($invalidCrons, $prefix){
			ExternalModules::setModifiedCrons($prefix, $invalidCrons);
		}, "A cron is not valid! ".json_encode($cronAttr3));
		$crons = ExternalModules::getModifiedCrons($prefix);
		$this->assertTrue($crons != $invalidCrons);

		ExternalModules::removeModifiedCrons($prefix);
		$crons = ExternalModules::getModifiedCrons($prefix);
		$this->assertTrue(empty($crons));

		# check for config backup
		$config = [
			'system-settings' => [
				['key' => 'key1']
			],
			'project-settings' => [
				['key' => 'key-two']
			],
			'crons' => [
				[
					'cron_name' => 'Test Cron 10',
					'method' => 'testMethod10',
					'cron_description' => "descript",
					'cron_hour' => 10,
					'cron_minute' => 0,
				],
			],
		];

		$newCron = [
			'cron_name' => 'Test Cron 11',
			'method' => 'testMethod11',
			'cron_description' => "descript",
			'cron_hour' => 11,
			'cron_minute' => 0,
		];

		ExternalModules::removeModifiedCrons($prefix);
		$this->setConfig($config);
		$crons = ExternalModules::getCronSchedules($prefix);
		$this->assertTrue($crons == $config['crons']);
		ExternalModules::setModifiedCrons($prefix, $validCrons);
		$crons = ExternalModules::getCronSchedules($prefix);
		$modifiedCrons = ExternalModules::getModifiedCrons($prefix);
		$this->assertTrue($crons != $config['crons']);
		$this->assertTrue(in_array($cronAttr1, $crons));
		$this->assertTrue(in_array($cronAttr2, $crons));
		$this->assertTrue(in_array($cronAttr4, $crons));
		$this->assertTrue(in_array($cronAttr5, $crons));

		# set new crons
		array_push($config['crons'], $newCron);
		$this->setConfig($config);
		$crons = ExternalModules::getCronSchedules($prefix);
		$this->assertTrue($crons != $config['crons']);
		$this->assertTrue(in_array($cronAttr1, $crons));
		$this->assertTrue(in_array($cronAttr2, $crons));
		$this->assertTrue(in_array($cronAttr4, $crons));
		$this->assertTrue(in_array($cronAttr5, $crons));
	}


	function testGetProjectSettingsAsArray_systemOnly()
	{
		$value = rand();
		$this->setSystemSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame($value, $array[TEST_SETTING_KEY]['system_value']);
	}

	function testGetProjectSettingsAsArray_projectOnly()
	{
		$value = rand();
		$this->setProjectSetting($value);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($value, $array[TEST_SETTING_KEY]['value']);
		$this->assertFalse(isset($array[TEST_SETTING_KEY]['system_value']));
	}

	function testGetProjectSettingsAsArray_both()
	{
		$systemValue = rand();
		$projectValue = rand();

		$this->setSystemSetting($systemValue);
		$this->setProjectSetting($projectValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame($systemValue, $array[TEST_SETTING_KEY]['system_value']);

		// Re-test reversing the insert order to make sure it doesn't matter.
		$this->setProjectSetting($projectValue);
		$this->setSystemSetting($systemValue);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($projectValue, $array[TEST_SETTING_KEY]['value']);
		$this->assertSame($systemValue, $array[TEST_SETTING_KEY]['system_value']);
	}

	function testGetSystemSettingsAsArray_noPrefix()
	{
		$this->assertThrowsException(function(){
			ExternalModules::getSystemSettingsAsArray(null);
		}, 'One or more module prefixes must be specified!');
	}

	function testIsTimeToRun()
	{
		// Any date that doesn't overlap with a DST change after our $displacement should work here.
		$testDate = '2021-01-01 1:11am';
		$method = 'isTimeToRun';

		$offsets = array(
				"nul" => "assertTrue",
				"addPT1H" => "assertFalse",
				"subPT1H" => "assertFalse",
				"addPT1M" => "assertFalse",
				"subPT1M" => "assertFalse",
				"addP1D" => "assertTrue",
				"subP1D" => "assertTrue",
				);
		$defaultCron = array(
					'cron_name' => 'test_name',
					'cron_description' => 'This is a test',
					'method' => 'test_method',
					);

		foreach ($offsets as $displacement => $validationMethod) {
			$currentTime = time();
			if($currentTime%60 >= 59){
				// We don't want the clock to turn over to the next minute in the middle of this test.
				// Go ahead and wait for the next minute to come to ensure the test always passes.
				sleep(1);
				$currentTime = time();
			}

			$datetime = new \DateTime($testDate);

			// Simulate the process starting now.
			$_SERVER["REQUEST_TIME"] = $datetime->format("U");
			
			$func = substr($displacement, 0, 3);
			$offset = substr($displacement, 3);

			if ($func != "nul") {
				$datetime->$func(new \DateInterval($offset));
			}
			$cron = array(
				'cron_hour' => $datetime->format("G"),
				'cron_minute' => $datetime->format("i"),
			);
			$this->$validationMethod(self::callPrivateMethod($method, array_merge($defaultCron, $cron)), __FUNCTION__ . ' failed on: ' . json_encode([
				'cronConfig' => $cron,
				'displacement' => $displacement
			], JSON_PRETTY_PRINT));
		}

		# move forward one day => should fail on weekday
		$datetime2 = new \DateTime($testDate);
		$datetime2->add(new \DateInterval("P1D"));
		$cron2 = array(
				'cron_hour' => $datetime2->format("G"),
				'cron_minute' => $datetime2->format("i"),
				'cron_weekday' => $datetime2->format("w"),
				);
		$this->assertFalse(self::callPrivateMethod($method, array_merge($defaultCron, $cron2)));

		# move forward one week => should call cron on weekday but not monthday
		$datetime3 = new \DateTime($testDate);
		$datetime3->add(new \DateInterval("P7D"));
		$cron3 = array(
				'cron_hour' => $datetime3->format("G"),
				'cron_minute' => $datetime3->format("i"),
				'cron_weekday' => $datetime3->format("w"),
				);
		$this->assertTrue(self::callPrivateMethod($method, array_merge($defaultCron, $cron3)));

		$cron3_2 = array(
				'cron_hour' => $datetime3->format("G"),
				'cron_minute' => $datetime3->format("i"),
				'cron_monthday' => $datetime3->format("j"),
				);
		$this->assertFalse(self::callPrivateMethod($method, array_merge($defaultCron, $cron3_2)));
	}

	function callTimedCronMethod($methodName)
	{
		ob_start();
		self::callPrivateMethod('callTimedCronMethod', TEST_MODULE_PREFIX, $methodName);
		ob_end_clean();
	}

	function testCallTimedCronMethod_concurrency()
	{
		$methodName = 'redcap_test_call_function';

		$this->setConfig(['crons' => json_decode(self::TIMED_CRON_EXAMPLE, true)]);

		$callCronMethod = function($action) use ($methodName){
			$m = $this->getInstance();
			$m->function = $action;

			$this->callTimedCronMethod($methodName);
		};

		$childAction = function() use ($callCronMethod){
			$assertConcurrentCallSkipped = function($expectedSubject) use ($callCronMethod){
				$callCronMethod(function(){
					throw new Exception('This cron call should have been automatically skipped due to another recent cron call running.');
				});

				$this->assertSame($expectedSubject, ExternalModules::$lastHandleErrorResult[0]);
			};

			$assertConcurrentCallSkipped(null);

			// See the comment in checkForALongRunningCronJob() to understand why we test a little less than a day long period.
			$aLittleLessThanADay = ExternalModules::DAY_IN_SECONDS - ExternalModules::MINUTE_IN_SECONDS*5;

			$lockInfo = self::callPrivateMethod('getCronLockInfo', TEST_MODULE_PREFIX);
			$lockInfo['time'] = time() - $aLittleLessThanADay;
			ExternalModules::setSystemSetting(TEST_MODULE_PREFIX, ExternalModules::KEY_RESERVED_IS_CRON_RUNNING, $lockInfo);
			//= External Module Long-Running Cron
			$assertConcurrentCallSkipped(ExternalModules::tt("em_errors_100")); 
		};

		$parentAction = function($isChildRunning) use ($callCronMethod){
			$callCronMethod(function() use ($isChildRunning){
				while($isChildRunning()){
					sleep(.1);
				}
			});
		};

		$this->runConcurrentTestProcesses($parentAction, $childAction);
	}

	function testCallCronMethod_unlockOnException()
	{
		$this->runTestCronMethod(function(){
			throw new Exception();
		});

		//= External Module Exception in Cron Job
		$this->assertSame(ExternalModules::tt("em_errors_56"), ExternalModules::$lastHandleErrorResult[0]);

		$secondCronRan = false;
		$this->runTestCronMethod(function() use (&$secondCronRan){
			$secondCronRan = true;
		});
		$this->assertTrue($secondCronRan); // Make sure the second cron ran, meaning the cron was unlocked after the exception.
	}

	function testCallCronMethod_parameterSafety()
	{
		$this->runTestCronMethod(function(){
			// Simulate a module setting the pid within a cron, so we can assert that it doesn't make a difference outside the cron method.
			$this->setProjectId('123');
		});

		$this->assertNull(ExternalModules::getProjectId());
	}

	private function callCronMethod($methodName)
	{
		$moduleId = ExternalModules::getIdForPrefix(TEST_MODULE_PREFIX);

		ob_start();
		ExternalModules::callCronMethod($moduleId, $methodName);
		ob_end_clean();
	}

	private function runTestCronMethod($function)
	{
		$methodName = 'redcap_test_call_function';

		$this->setConfig(['crons' => [[
			'cron_name' => $methodName,
			'cron_description' => 'Test Cron',
			'method' => $methodName,
		]]]);

		$m = $this->getInstance();
		$m->function = $function;

		$this->callCronMethod($methodName);
	}

	function testCheckForALongRunningCronJob()
	{
		//= External Module Long-Running Cron
		$longRunningCronEmailSubject = ExternalModules::tt("em_errors_100"); 
		$assertLongRunningCronErrorHandled = function($expected, $lockTime) use ($longRunningCronEmailSubject){
			ExternalModules::$lastHandleErrorResult = null;
			$this->callPrivateMethod('checkForALongRunningCronJob', TEST_MODULE_PREFIX, null, ['time' => $lockTime]);
			$this->assertSame($expected, (ExternalModules::$lastHandleErrorResult[0] ?? null) === $longRunningCronEmailSubject);
		};

		// See the comment in checkForALongRunningCronJob() to understand why we test a little less than a day long period.
		$aLittleLessThanADayAgo = time() - ExternalModules::DAY_IN_SECONDS + ExternalModules::MINUTE_IN_SECONDS*5;

		$assertLongRunningCronErrorHandled(false, time() - ExternalModules::HOUR_IN_SECONDS*22);
		$assertLongRunningCronErrorHandled(true, $aLittleLessThanADayAgo);
		$assertLongRunningCronErrorHandled(false, $aLittleLessThanADayAgo); // The email should not send again (yet).

		ExternalModules::setSystemSetting(TEST_MODULE_PREFIX, ExternalModules::KEY_RESERVED_LAST_LONG_RUNNING_CRON_NOTIFICATION_TIME, $aLittleLessThanADayAgo);
		$assertLongRunningCronErrorHandled(true, $aLittleLessThanADayAgo);
	}

	function testResetCron() {
		// initial: no long-running rows deleted
		$affectedRows1 = ExternalModules::resetCron(TEST_MODULE_PREFIX);
		$this->assertSame($affectedRows1, 0);

		// long-running tripped; one long-running row deleted
		$this->callPrivateMethod('lockCron', TEST_MODULE_PREFIX, null, ['time' => time()]);
		$affectedRows2 = ExternalModules::resetCron(TEST_MODULE_PREFIX);
		$this->assertSame($affectedRows2, 1);

		// afterwards; no long-running rows deleted
		$affectedRows3 = ExternalModules::resetCron(TEST_MODULE_PREFIX);
		$this->assertSame($affectedRows3, 0);

		$randomPrefix = rand();
		$this->expectExceptionMessage($this->tt('em_errors_118', $randomPrefix));
		ExternalModules::resetCron($randomPrefix);
	}

	function testAddAdditionalSettings()
	{
		$action = function($config){
			return self::callPrivateMethod('addAdditionalSettings', $config);
		};

		// Make sure other settings are passed through without exception.
		$key = 'some-non-reserved-settings';
		$overridableSetting = [
			'key' => $key,
			'allow-project-overrides' => true,
			'name' => 'whatever',
			'project-name' => 'whatever project'
		];

		$config = $action([
			'system-settings' => [$overridableSetting]
		]);

		$systemSettings = $config['system-settings'];

		$namesByKey = [];
		foreach($systemSettings as $setting){
			$namesByKey[$setting['key']] = $setting['name'];
		}

		$this->assertSame([
			ExternalModules::KEY_ENABLED,
			ExternalModules::KEY_DISCOVERABLE,
			ExternalModules::KEY_USER_ACTIVATE_PERMISSION,
			ExternalModules::KEY_RESERVED_HIDE_FROM_NON_ADMINS_IN_PROJECT_LIST,
			$key,
		], array_keys($namesByKey));

		$this->assertSame(ExternalModules::tt('em_config_9'), $namesByKey[ExternalModules::KEY_RESERVED_HIDE_FROM_NON_ADMINS_IN_PROJECT_LIST]);

		$this->assertSame(
			[
				[
					'key' => ExternalModules::KEY_RESERVED_HIDE_FROM_NON_ADMINS_IN_PROJECT_LIST,
					'allow-project-overrides' => true,
					'name' => $this->tt('em_config_10'),
					'project-name' => $this->tt('em_config_10'),
					'type' => 'checkbox',
					'super-users-only' => true
				],
				array_merge($overridableSetting, ['name' => $overridableSetting['project-name']])
			],
			$config['project-settings']
		);

		// Ensure that empty configs aren't modified.
		$this->assertSame([], $action([]));
	}

	function testCacheAllEnableData()
	{
		$m = $this->getInstance();

		$version = rand();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, $version);

		self::callPrivateMethod('cacheAllEnableData');
		$this->assertSame($version, self::callPrivateMethod('getSystemwideEnabledVersions')[TEST_MODULE_PREFIX]);

		$m->removeSystemSetting(ExternalModules::KEY_VERSION);

		// the other values set by cacheAllEnableData() are tested via testGetEnabledModuleVersionsForProject()
	}

	function testOverwriteBlankSetting()
	{
		$m = $this->getInstance();

		$str = 'abc';
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, '');
		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $str);

		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($str, $array[TEST_SETTING_KEY]['value']);
	}

	function testGetEnabledModules()
	{
		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertFalse(isset($versionsByPrefix[TEST_MODULE_PREFIX]));
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertFalse(isset($versionsByPrefix[TEST_MODULE_PREFIX]));

		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);

		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertSame(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertFalse(isset($versionsByPrefix[TEST_MODULE_PREFIX]));

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);

		$this->cacheAllEnableData();
		$versionsByPrefix = ExternalModules::getEnabledModules();
		$this->assertSame(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
		$versionsByPrefix = ExternalModules::getEnabledModules(TEST_SETTING_PID);
		$this->assertSame(TEST_MODULE_VERSION, $versionsByPrefix[TEST_MODULE_PREFIX]);
	}

	function testGetEnabledModuleVersionsForProject_multiplePrefixesAndVersions()
	{
		$prefix1 = TEST_MODULE_PREFIX . '-1';
		$prefix2 = TEST_MODULE_PREFIX . '-2';

		ExternalModules::setSystemSetting($prefix1, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setSystemSetting($prefix2, ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		ExternalModules::setSystemSetting($prefix1, ExternalModules::KEY_ENABLED, true);
		ExternalModules::setSystemSetting($prefix2, ExternalModules::KEY_ENABLED, true);

		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertNotNull($prefixes[$prefix2]);

		ExternalModules::removeSystemSetting($prefix2, ExternalModules::KEY_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[$prefix1]);
		$this->assertFalse(isset($prefixes[$prefix2]));

		ExternalModules::removeSystemSetting($prefix1, ExternalModules::KEY_ENABLED);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertFalse(isset($prefixes[$prefix1]));

		ExternalModules::removeSystemSetting($prefix1, ExternalModules::KEY_VERSION);
		ExternalModules::removeSystemSetting($prefix2, ExternalModules::KEY_ENABLED);
	}

	function testGetEnabledModuleVersionsForProject_overrides()
	{
		$m = self::getInstance();

		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertFalse(isset($prefixes[TEST_MODULE_PREFIX]));

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);


		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertFalse(isset($prefixes[TEST_MODULE_PREFIX]));

		$m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, false);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertFalse(isset($prefixes[TEST_MODULE_PREFIX]));

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertNotNull($prefixes[TEST_MODULE_PREFIX]);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$prefixes = self::getEnabledModuleVersionsForProjectIgnoreCache();
		$this->assertFalse(isset($prefixes[TEST_MODULE_PREFIX]));
	}

	function testGetFileSettings() {
		$m = self::getInstance();					

		$edocIdSystem = (string) rand();
		$edocIdProject = (string) rand();

                # system
		ExternalModules::setSystemFileSetting($this->getInstance()->PREFIX, FILE_SETTING_KEY, $edocIdSystem);

                # project
		ExternalModules::setFileSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, FILE_SETTING_KEY, $edocIdProject);

		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertSame($edocIdProject, $array[FILE_SETTING_KEY]['value']);
		$this->assertSame($edocIdSystem, $array[FILE_SETTING_KEY]['system_value']);

		ExternalModules::removeProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, FILE_SETTING_KEY);
		ExternalModules::removeSystemSetting($this->getInstance()->PREFIX, FILE_SETTING_KEY);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);

		$this->assertFalse(isset($array[FILE_SETTING_KEY]['value']));
		$this->assertFalse(isset($array[FILE_SETTING_KEY]['system_value']));
	}

	function testGetLinks()
	{
		$config = [
			'links' => [
				'control-center' => [
					[
						'name'=>"Keyless Control Center Link Name",
						'url'=>"some/control/center/keyless-url",
						'icon'=>rand()
					],
					[
						'name'=>"Keyed (and duplicated) Control Center Link Name",
						'key'=>"control-key-1",
						'url'=>"some/control/center/keyed-url-1",
						'icon'=>rand()
					],
					[
						'name'=>"Keyed (and duplicated) Control Center Link Name",
						'key'=>"control-key-2",
						'url'=>"some/control/center/keyed-url-2",
					],
					[
						'name'=>"Link With Invalid Key",
						'key'=>"key with invalid characters!!!",
						'url'=>"some-url",
					]
				],
				'project' => [
					[
						'name'=>"Keyless Project Link Name",
						'url'=>"some/project/keyless-url",
						'icon'=>rand()
					],
					[
						'name'=>"Keyed (and duplicated) Project Link Name",
						'key'=>"keyed-project-link-1",
						'url'=>"some/project/keyed-url-1",
						'icon'=>rand()
					],
					[
						'name'=>"Keyed (and duplicated) Project Link Name",
						'key'=>"keyed-project-link-2",
						'url'=>"some/project/keyed-url-2",
					],
					[
						'name'=>"External Link",
						'key'=>"external-link",
						'url'=>"https://projectredcap.org"
					],
					[
						'name'=>"JavaScript Call",
						'key'=>"javascript-link",
						'url'=>"javascript:alert('Hi there');"
					]
				]
			]
		];

		$assert = function($type, $expectEmptyMenuLinks = false) use ($config){
			$this->cacheAllEnableData();

			$expectedLinks = [];
			$linkCounter = 0;
			foreach($config['links'][$type] as $link){
				$linkCounter++;

				$url = $link['url'];
				$linkType = $this->getLinkType($url);
				if($linkType === 'page'){
					$link['url'] = ExternalModules::getPageUrl(TEST_MODULE_PREFIX, $url);
				}
				else if($linkType === 'ext'){
					$link['target'] = '_blank';
				}

				$link['prefix'] = TEST_MODULE_PREFIX;

				$prefixedKey = $link['key'] ?? null;
				if(!$this->isLinkKeyValid($prefixedKey)){
					$link['name'] .= '<br>' . $this->tt('em_errors_140');
					$prefixedKey = null;
				}

				if($prefixedKey === null){
					$prefixedKey = "link_{$type}_{$linkCounter}";
				}

				$link['prefixedKey'] = TEST_MODULE_PREFIX . '-' . $prefixedKey;

				$linkArrayKey = TEST_MODULE_PREFIX . '-' . $linkCounter;
				$expectedLinks[$linkArrayKey] = $link;
			}

			$actualModuleLinks = $this->getLinks(TEST_MODULE_PREFIX, TEST_MODULE_VERSION);
			$this->assertSame($expectedLinks, $actualModuleLinks, 'Assert permission checking when a link is accessed');

			$allActualLinksFiltered = [];
			foreach($this->getLinks() as $key=>$link){
				if(strpos($key, TEST_MODULE_PREFIX) === 0){
					$allActualLinksFiltered[$key] = $link;
				}
			}

			if($expectEmptyMenuLinks){
				$expectedLinks = [];
			}

			$this->assertSame($expectedLinks, $allActualLinksFiltered, 'Assert that links appear (or not) in the left menu');
		};

		$this->setConfig($config);
		$assert('control-center', true);

		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$assert('control-center');

		$this->setProjectId(TEST_SETTING_PID);
		$assert('project', true);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true);
		$assert('project');
	}

	function testGetLinks_exceptions(){
		$assert = function($links, $expectedException){
			$this->assertThrowsException(function() use ($links){
				foreach(['control-center', 'project'] as $type){
					$this->setConfig([
						'links' => [
							$type => $links
						]
					]);

					$this->cacheAllEnableData();
					$this->getLinks(TEST_MODULE_PREFIX, TEST_MODULE_VERSION);
				}
			}, $expectedException);
		};

		$duplicateKey = "duplicate-key";
		$assert([
			[
				'name'=>"Some Link",
				'key'=>$duplicateKey,
				'url'=>"some-url"
			],
			[
				'name'=>"Some Link",
				'key'=>$duplicateKey,
				'url'=>"some-url"
			]
		], $this->tt('em_errors_141', $duplicateKey));
	}

	function testCallHook_enabledStates()
	{
		$pid = TEST_SETTING_PID;
		$m = $this->getInstance();
		$hookName = 'redcap_test';

		$assertHookCalled = function($called, $pid = null) use ($hookName){
			$this->assertHookCalled($hookName, $called, $pid);
		};

		$assertHookCalled(false);

		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$assertHookCalled(true);

		$assertHookCalled(false, $pid);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$assertHookCalled(true, $pid);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, $pid);
		$assertHookCalled(false, $pid);

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, false);
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, $pid);
		$assertHookCalled(true, $pid);

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, $pid);
		$assertHookCalled(false, $pid);
	}

	function testCallHook_previousActiveModulePrefix()
	{
		$previousPrefix = 'some_fake_prefix';
		$this->setActiveModulePrefix($previousPrefix);

		// Clear the config for the second test module, so only first first runs.
		$this->setConfig([], true, TEST_MODULE_TWO_PREFIX);

		$prefixInsideHook = null;
		ExternalModules::callHook('redcap_test_call_function', [function() use (&$prefixInsideHook){
			$prefixInsideHook = $this->getActiveModulePrefix();
		}]);

		// Assert that the active module prefix did indeed change.
		$this->assertSame(TEST_MODULE_PREFIX, $prefixInsideHook);

		// Assert that the previous prefix was restored (for cases like one module triggering the email hook in a second module).
		$this->assertSame($previousPrefix, $this->getActiveModulePrefix());
	}

	function testCallHook_arguments()
	{
		$m = $this->getInstance();

		$argOne = 1;
		$argTwo = 'a';
		ExternalModules::callHook('redcap_test', [$argOne, $argTwo]);
		$this->assertSame($argOne, $m->testHookArguments[0]);
		$this->assertSame($argTwo, $m->testHookArguments[1]);
	}

	function testCallHook_pdfReturnValueDaisyChaining()
	{
		$projectId = 1;
		$startingMetadata = ['starting metadata'];
		$startingData = ['starting data'];
		$expectedMetadata = $startingMetadata;
		$expectedData = $startingData;

		foreach([TEST_MODULE_PREFIX, TEST_MODULE_TWO_PREFIX] as $prefix){
			$expectedMetadata[] = 'metadata added by ' . $prefix;
			$expectedData[] = 'data added by ' . $prefix;
		}

		$expected = [
			'metadata' => $expectedMetadata,
			'data' => $expectedData
		];

		$actual = ExternalModules::callHook('redcap_pdf', [$projectId, $startingMetadata, $startingData]);
		$this->assertSame($expected, $actual);
	}

	function testCallHook_permissions()
	{
		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);

		$hookName = 'redcap_test';
		$this->assertHookCalled($hookName, true);

		$pid = TEST_SETTING_PID;
		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, $pid);

		$hookName = 'redcap_every_page_test';

		// System pages
		$this->assertHookCalled($hookName, false);
		$this->setConfig([
			'enable-every-page-hooks-on-system-pages' => true,
			'enable-every-page-hooks-on-login-form' => true,
		]);
		$this->assertHookCalled($hookName, true);
		$this->setConfig([
			'enable-every-page-hooks-on-system-pages' => false,
			'enable-every-page-hooks-on-login-form' => true,
		]);
		$this->assertHookCalled($hookName, false);
		$this->setConfig([
			'enable-every-page-hooks-on-system-pages' => true,
			'enable-every-page-hooks-on-login-form' => false,
		]);
		$this->assertHookCalled($hookName, false);
		$this->setUsername('foo');
		$this->assertHookCalled($hookName, true);
		$this->setUsername(null);
		$this->assertHookCalled($hookName, false);

		// Project pages
		$this->setUsername('foo');
		$this->assertHookCalled($hookName, true, $pid);
		$this->setUsername(null);
		$this->assertHookCalled($hookName, false, $pid);
		$this->setNoAuth(true);
		$this->assertHookCalled($hookName, true, $pid);
		$this->setNoAuth(false);
		$this->assertHookCalled($hookName, false, $pid);
		$this->setConfig(['enable-every-page-hooks-on-login-form' => true]);
		$this->assertHookCalled($hookName, true, $pid);

		$legacyHookName = 'hook_legacy_prefix';
		$this->assertHookCalled($legacyHookName, false); // Only the 'redcap_' prefix is supported in PERMISSIONS_REMOVED_FRAMEWORK_VERSION and after 

		$config['framework-version'] = ExternalModules::PERMISSIONS_REMOVED_FRAMEWORK_VERSION-1;
		$this->setConfig($config);
		$this->setUsername('foo');
		$this->assertHookCalled($hookName, false, $pid);
		$this->assertHookCalled($legacyHookName, false, $pid);
		
		$config['permissions'] = [$hookName, $legacyHookName];
		$this->setConfig($config);
		$this->assertHookCalled($hookName, true, $pid);
		$this->assertHookCalled($legacyHookName, true, $pid);
	}

	function testCallHook_setRecordId()
	{
		$m = $this->getInstance();

		$hookName = 'redcap_save_record';

		$projectId = 1;
		$recordId = rand();
		ExternalModules::callHook($hookName, [$projectId, $recordId]);
		$this->assertSame($recordId, $m->recordIdFromGetRecordId);

		// The record id should be set back to null once the hook finishes.
		$this->assertNull($m->getRecordId());
	}

	private function assertHookCall($hookName, $setup, $actionPreventingExecution)
	{
		$hookName = 'redcap_email';

		$assert = function($called) use ($hookName){
			$m = $this->getInstance();
			$m->testHookArguments = null;

			$arguments = [rand()];
			ExternalModules::callHook($hookName, $arguments);

			if($called){
				$expected = $arguments;
			}
			else{
				$expected = null;
			}

			$this->assertSame($expected, $m->testHookArguments);
		};

		$setup();
		$assert(true);

		$actionPreventingExecution();
		$assert(false);
	}

	function testCallHook_emailSystemFlag()
	{
		$assert = function($actionPreventingExecution){	
			$hookName = 'redcap_email';

			$config = [
				'enable-email-hook-in-system-contexts' => true,
			];

			$this->assertHookCall(
				$hookName,
				function() use ($config){
					$this->setConfig($config);
				},
				function() use ($config, $actionPreventingExecution){
					$actionPreventingExecution($config);
					$this->setConfig($config);
				}
			);
		};

		$assert(function(&$config){
			unset($config['enable-email-hook-in-system-contexts']);
		});

		$assert(function(&$config){
			$config['enable-email-hook-in-system-contexts'] = false;
		});		
	}

	function testCallHook_transactions()
	{
		$startTransaction = function(){
			$this->query('start transaction', []);
			$this->setSystemSetting(rand());
		};

		$startTransaction();

		ExternalModules::callHook('redcap_test_call_function', [function() use (&$valueInsideHook, $startTransaction){
			$valueInsideHook = $this->getSystemSetting();
			$startTransaction();
		}]);

		$this->assertNull($valueInsideHook, 'Make sure the transaction was rolled back before the hook was started');
		$this->assertNull($this->getSystemSetting(), 'Make sure the transaction was rolled back after hook finished');
	}

	function testCallHook_emailPID()
	{
		$assert = function($actionPreventingExecution){	
			$hookName = 'redcap_email';
			$pid = 999999999; // A $pid that would never really exist.
			$config = [];

			$this->assertHookCall(
				$hookName,
				function() use ($pid, $config){
					$this->setProjectId($pid);
					$this->setExternalModulesProperty('projectEnabledDefaults', []);
					$this->setExternalModulesProperty('projectEnabledOverrides', [$pid => [TEST_MODULE_PREFIX => true]]);
					$this->setConfig($config);
				},
				function() use ($config, $actionPreventingExecution){
					$actionPreventingExecution($config);
					$this->setConfig($config);
				}
			);
		};

		$assert(function(){
			$this->setProjectId(null);
		});

		$assert(function(){
			/**
			 * The redcap_email hook is the only hook that executes on projects without passing a $projectId parameter to the hook.
			 * This asserts that the special handling code for this hook works as expected to detect the project ID via other means,
			 * and prevent the project email hook only runs when the module is enabled on the project.
			 */
			$this->setExternalModulesProperty('projectEnabledOverrides', []);
		});

		$assert(function(){
			// The hooks should not run if the module is enabled on a different project.
			$this->setProjectId(-1);
		});
	}

	function testCallHook_insideHookFlag()
	{
		$assert = function($expected){
			$this->assertSame($expected, isset($GLOBALS['__currently_inside_hook']));
		};

		$assert(false);
		
		ExternalModules::callHook('redcap_test_call_function', [function() use ($assert) {
			$assert(true);

			// Test nested hook calls as well
			ExternalModules::callHook('redcap_test_call_function', [function() use ($assert) {
				$assert(true);
			}]);

			$assert(true);
		}]);

		$assert(false);
	}

	function testCallHook_redcap_module_api_before()
	{
		$assert = function($prefixesThatReturnErrors, $expectedReturnValue){
			$post = [
				'random_value' => rand(),
				'prefixes_that_return_errors' => $prefixesThatReturnErrors,
			];

			$actualReturnValue = ExternalModules::callHook('redcap_module_api_before', [TEST_SETTING_PID, $post]);
			$this->assertSame($expectedReturnValue, $actualReturnValue);

			$args = $this->getInstance()->testHookArguments;
			$this->assertSame(TEST_SETTING_PID, $args[0]);
			$this->assertSame($post, $args[1]);
		};

		$assert([], null);
		$post['prefixes_that_return_errors'][] = TEST_MODULE_PREFIX;
		$assert([TEST_MODULE_PREFIX], $this->tt('em_errors_178', TEST_MODULE_PREFIX, TestModuleTwo::API_FAILURE_MESSAGE));
		$post['prefixes_that_return_errors'][] = TEST_MODULE_TWO_PREFIX;
		$assert([TEST_MODULE_PREFIX, TEST_MODULE_TWO_PREFIX], 
			$this->tt('em_errors_178', TEST_MODULE_PREFIX, TestModuleTwo::API_FAILURE_MESSAGE) . "\n\n" .
			$this->tt('em_errors_178', TEST_MODULE_TWO_PREFIX, TestModuleTwo::API_FAILURE_MESSAGE)
		);
	}

	private function assertHookCalled($hookName, $called, $pid = null)
	{
		/**
		 * This string replace is important for testing 'hook_' hooks since REDCap always runs
		 * callHook() with a 'redcap_' prefix.
		 */
		$hookName = str_replace('hook_', 'redcap_', $hookName);

		$arguments = [];
		if($pid){
			$arguments[] = $pid;
		}

		$this->cacheAllEnableData();
		$m = $this->getInstance();

		$m->testHookArguments = null;
		ExternalModules::callHook($hookName, $arguments);
		if($called){
			$this->assertNotNull($m->testHookArguments, 'The hook was expected to run but did not.  This could be caused by exceptions logged above.');
		}
		else{
			$this->assertNull($m->testHookArguments, 'The hook was not expected to run but did.');
		}
	}

	// Calling this will effectively clear/reset the cache.
	private function cacheAllEnableData()
	{
		self::callPrivateMethod('cacheAllEnableData');
	}

	private function getEnabledModuleVersionsForProjectIgnoreCache()
	{
		$this->cacheAllEnableData();
		return self::callPrivateMethod('getEnabledModuleVersionsForProject', TEST_SETTING_PID);
	}

	function testSaveSettings()
	{
		$settings = [];
		$settings[TEST_SETTING_KEY] = rand();

		$repeatableSettingKey = 'test-repeatable';
		$repeatableExpected = [rand(), 'some string', 1.23];

		for($i = 0; $i<count($repeatableExpected); $i++){
			$settings[$repeatableSettingKey . '____' . $i] = $repeatableExpected[$i];
		}

		ExternalModules::saveSettings(TEST_MODULE_PREFIX, TEST_SETTING_PID, $settings);

		$this->assertSame($settings[TEST_SETTING_KEY], ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY));
		$this->assertSame($repeatableExpected, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $repeatableSettingKey));

		// cleanup
		ExternalModules::removeProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $repeatableSettingKey);
	}

	function testInstance()
	{
		$value1 = rand();
		$value2 = rand();
		$value3 = rand();
		$value4 = rand();
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 0, $value1);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertNotNull(json_encode($array));
		$this->assertSame($value1, $array[TEST_SETTING_KEY]['value']);

		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value1);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 1, $value2);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 2, $value3);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 3, $value4);
		$array = ExternalModules::getProjectSettingsAsArray($this->getInstance()->PREFIX, TEST_SETTING_PID);
		$this->assertNotNull(json_encode($array));
		$this->assertSame($value1, $array[TEST_SETTING_KEY]['value'][0]);
		$this->assertSame($value2, $array[TEST_SETTING_KEY]['value'][1]);
		$this->assertSame($value3, $array[TEST_SETTING_KEY]['value'][2]);
		$this->assertSame($value4, $array[TEST_SETTING_KEY]['value'][3]);

		ExternalModules::setProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value1);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 1, $value2);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 2, $value3);
		ExternalModules::setInstance($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, 3, $value4);
		$array = ExternalModules::getProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID,  TEST_SETTING_KEY);
		$this->assertNotNull(json_encode($array));
		$this->assertSame($value1, $array[0]);
		$this->assertSame($value2, $array[1]);
		$this->assertSame($value3, $array[2]);
		$this->assertSame($value4, $array[3]);

		ExternalModules::removeProjectSetting($this->getInstance()->PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY);
	}

	function testIsProduction()
	{
		$assertProduction = function($value){
			$GLOBALS['is_development_server'] = $value;
			$this->assertSame(!$value, $this->callPrivateMethod('isProduction'));
		};

		$assertProduction('0');
		$assertProduction('1');
	}

	function testGetAdminEmailMessage()
	{
		global $project_contact_email;

		$assertToEquals = function ($expectedTo, $expectedModuleEmail = null) {
			if ($expectedModuleEmail) {
				$this->setConfig([
					'authors' => [
						[
							'email' => $expectedModuleEmail
						],
						[
							'email' => 'someone@somewhere.edu' // we assert that this email is NOT included, because the domain doesn't match.
						]
					]
				]);
			}

			$expectedTo = implode(',', $expectedTo);

			$username = rand();
			$message = $this->callPrivateMethod('getAdminEmailMessage', 'The Subject', 'The Message', TEST_MODULE_PREFIX);
			
			$this->assertEquals($expectedTo, $message->getTo());
		};

		$this->setPrivateVariable('SERVER_NAME', 'redcaptest.vanderbilt.edu');
		$assertToEquals(['redcap-external-module-framework@vumc.org']);

		$this->setPrivateVariable('SERVER_NAME', 'redcap.vumc.org');
		$expectedTo = ['redcap-external-module-framework@vumc.org', 'datacore@vumc.org'];
		$assertToEquals($expectedTo);

		// Assert that vanderbilt module author address is NOT included, since it's always going to be datacore anyway.
		$assertToEquals($expectedTo, 'someone@vumc.org');

		$otherDomain = 'other.edu';
		$this->setPrivateVariable('SERVER_NAME', "redcap.$otherDomain");
		$assertToEquals([$project_contact_email]);

		$expectedModuleEmail = "someone@$otherDomain";
		$expectedTo = [$project_contact_email, $expectedModuleEmail];
		$assertToEquals($expectedTo, $expectedModuleEmail);
	}

	function testAreSettingPermissionsUserBased()
	{
		$m = $this->getInstance();
		$methodName = 'areSettingPermissionsUserBased';
		$hookName = 'redcap_test_call_function';

		// permissions should not be user based during hook calls
		$value = null;
		ExternalModules::callHook($hookName, [function() use ($methodName, &$value){
			$value = self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, TEST_SETTING_KEY);
		}]);
		$this->assertFalse($value);

		// We'd ideally test to ensure permissions were not user based on module pages here,
		// but we don't have a good way to test that currently.

		// modules should require user based permissions by default in other contexts (like saving settings in the settings dialog)
		$this->assertTrue(self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, TEST_SETTING_KEY));

		$m->disableUserBasedSettingPermissions();
		$this->assertFalse(self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, TEST_SETTING_KEY));

		// reserved values should always require user based permissions
		$this->assertTrue(self::callPrivateMethod($methodName, TEST_MODULE_PREFIX, ExternalModules::KEY_ENABLED));
	}

	function testGetUrl()
	{
		$m = $this->getInstance();

		$url = $m->getUrl("index.php");
		$this->assertNotNull($url);
		$url = $m->getUrl("dir/index.php");
		$this->assertNotNull($url);
	}

	function testGetParseModuleDirectoryPrefixAndVersion()
	{
		$assert = function($expected, $directoryName){
			$this->assertSame($expected, ExternalModules::getParseModuleDirectoryPrefixAndVersion($directoryName));
		};

		$assert(['somedir', 'v1'], 'somedir_v1');
		$assert(['somedir', 'v1.1'], 'somedir_v1.1');
		$assert(['somedir', 'v1.1.1'], 'somedir_v1.1.1');

		// Test underscores and dashes.
		$assert(['some_dir', 'v1.1'], 'some_dir_v1.1');
		$assert(['some-dir', 'v1.1'], 'some-dir_v1.1');

		// Test invalid values.
		$assert(['some_dir', null], 'some_dir_');
		$assert(['some_dir', null], 'some_dir_v');
		$assert(['', 'v1.0'], '_v1.0');
		$assert(['somedir', null], 'somedir_v1A');
		$assert(['somedir', null], 'somedir_vA');
		$assert(['somedir', null], 'somedir_1');
		$assert(['somedir', null], 'somedir_A');
		$assert(['somedir', null], 'somedir_v1.1.1.1');
	}

	function testGetModuleInstance()
	{
		$prefix = 'some_fake_prefix';
		$this->setActiveModulePrefix($prefix);

		$m = ExternalModules::getModuleInstance(TEST_MODULE_PREFIX);

		$this->assertSame(\TestModule\TestModule::class, get_class($m));

		// Although it perhaps shouldn't be, it is sometimes possible for getModuleInstance() to
		// be called while inside a hook (it sometimes happens in the email alerts module).
		// The getModuleInstance() function used to set the active module prefix to null on every call,
		// which is problematic for anything that relies on the active prefix.
		// This used to cause 'You must specify a prefix!' exceptions.
		// Assert that the previous prefix is restored to make sure this does not happen again.
		$this->assertSame($prefix, $this->getActiveModulePrefix());
	}

	function testGetFrameworkVersion()
	{
		$doNotIncludeFrameworkVersionValue = 'do-not-include-framework-version';

		$assertFrameworkVersion = function($jsonValue, $expected = null) use ($doNotIncludeFrameworkVersionValue){
			$config = [];
			if($jsonValue !== $doNotIncludeFrameworkVersionValue){
				$config['framework-version'] = $jsonValue;
			}

			$this->setConfig($config, false);

			$actual = ExternalModules::getFrameworkVersion($this->getInstance());
			$this->assertSame($expected, $actual);
		};

		$assertFrameworkVersion($doNotIncludeFrameworkVersionValue, 1);
		$assertFrameworkVersion(null, 1);
		$assertFrameworkVersion(1, 1);
		$assertFrameworkVersion(2, 2);

		$exceptionValues = ['', 'a', '1', '2', 1.1, true, false, []];

		foreach($exceptionValues as $value){
			$this->assertThrowsException(function() use ($assertFrameworkVersion, $value){
				$assertFrameworkVersion($value);
			}, 'must be specified as an integer');
		}
	}

	function testCopySettingValues()
	{
		$value = [rand(), rand()];
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, TEST_SETTING_KEY, $value);

		self::callPrivateMethod('copySettingValues', TEST_SETTING_PID, TEST_SETTING_PID_2);

		$this->assertSame($value, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID_2, TEST_SETTING_KEY));
	}

	private function findEDocs($count){
		$result = ExternalModules::query("
			select * from redcap_edocs_metadata
			where
				date_deleted_server is null
				and doc_size < 1000000
				and project_id = ?
		", [TEST_SETTING_PID_3]); // Unit Tests assume this function will not return any edocs from test PIDs 1 & 2

		$rows = [];
		while($row = $result->fetch_assoc()){
			$path = ExternalModules::getEdocPath($row);
			if(!file_exists($path) || filesize($path) < 100 || filesize($path) > 1000000 || pathinfo($path, PATHINFO_EXTENSION) === ''){
				/**
				 * Skip edocs that have either been deleted (likely on localhosts),
				 * are empty or so small they might not result in accurate testing,
				 * are bigger than necessary, or are missing a file extension
				 * since Files::uploadFile() requires one as of April 2025.
				 */
				continue;
			}

			$rows[] = $row;

			if(count($rows) === $count){
				break;
			}
		}

		$edocsShort = $count - count($rows);
		if($edocsShort > 0){
			// Creating edocs is especially useful inside GitHub Actions, where the DB is re-created every time
			while($edocsShort > 0){
				$this->createEdoc();
				$edocsShort--;
			}

			return $this->findEdocs($count);
		}
		
		return $rows;
	}

	function findEdoc(){
		return $this->findEdocs(1)[0];
	}

	function createEdoc(){
		$m = $this->getInstance();

		$path = $this->createTempFile();

		// As of April 2025, Files::uploadFile() doesn't work unless the file has an extension.
		$newPath = "$path.csv";
		rename($path, $newPath);
		$path = $newPath;

		file_put_contents($path, bin2hex(random_bytes(rand(50, 100))));
		
		return $m->saveFile($path, TEST_SETTING_PID_3);
	}

	function testRecreateAllEDocs_fileSettings()
	{
		$edocPath = $this->getEdocPathRoot();
		if(!is_dir($edocPath)){
			throw new Exception('The EDOC_PATH (' . $edocPath . ') is not valid!  If you\'re switching between environments (e.g. Windows & Cygwin) you may want to remove the "edoc_path" value in the "redcap_config" table so it can be automatically detected.');
		}

		$edocIds = [];
		$edocFilenames = [];

		$minEdocs = 5;
		foreach($this->findEDocs($minEdocs) as $row){
			$this->assertFalse(in_array($row['project_id'], [TEST_SETTING_PID, TEST_SETTING_PID_2]));

			// We must cast to a string because there is an issue on js handling side for file fields stored as integers.
			$edocIds[] = (string)$row['doc_id'];
			$edocFilenames[] = $row['stored_name'];
		}

		$edocsNeeded = $minEdocs - count($edocIds);
		if($edocsNeeded !== 0){
			throw new Exception("Please upload $edocsNeeded more edocs to any project in order for unit tests to run properly.");
		}

		$key1 = 'test-key-1';
		$key2 = 'test-key-2';
		$key3 = 'test-key-3';

		$this->setConfig([
			'project-settings' => [
					[
						'key' => $key1,
						'type' => 'file'
					],
					[
						'key' => 'sub-settings-key',
						'type' => 'sub_settings',
						'sub_settings' => [
							[
								'key' => $key2,
								'type' => 'file'
							]
						]
					],
					[
						'key' => $key3,
						'type' => 'text'
					]
				]
			]
		);

		$value1 = $edocIds[0];

		// simulate repeatable sub-settings
		$value2 = [
			[
				$edocIds[1],
				$edocIds[2],
				$edocIds[3],
			],
			[
				$edocIds[4],
			],
			[
				PHP_INT_MAX, // Test a non-existent ID (e.g. an eDoc that has been deleted)
			]
		];

		ExternalModules::setFileSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1, $value1);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2, $value2);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3, $value2);

		ExternalModules::recreateAllEDocs(TEST_SETTING_PID);

		$newValue1 = ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1);
		$newValue2 = ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2);

		$newEdocIds = array_merge([$newValue1], $newValue2[0], $newValue2[1]);
		for($i=0; $i<$minEdocs; $i++){
			$oldId = $edocIds[$i];
			$newId = $newEdocIds[$i];

			$this->assertEdocsEqual($oldId, $newId);
		}

		// Make sure missing edocs are copied as nulls
		$this->assertSame(PHP_INT_MAX, $value2[2][0]);
		$this->assertNull($newValue2[2][0]);

		// Make sure non-file settings are not touched.
		$this->assertSame($value2, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3));

		foreach($newEdocIds as $id){
			ExternalModules::deleteEDoc($id);
		}
	}

	private function assertEdocsEqual($expected, $actual)
	{
		// Make sure edoc IDs are stored as strings because of a bug on the js processing side for file fields that prevents integers from working.
		$this->assertSame('string', gettype($expected));
		$this->assertSame('string', gettype($actual));

		// If the expected and actual edoc IDs are the same, something in the calling test isn't right.
		$this->assertNotEquals($expected, $actual);

		$this->assertFileEquals(self::getEdocPath($expected), self::getEdocPath($actual));
	}

	private function findAnEdoc()
	{
		return $this->findEDocs(1)[0];
	}

	function testRecreateAllEDocs_richText()
	{
		$row = $this->findAnEdoc();

		$oldProjectId = $row['project_id'];
		$oldEdocId = $row['doc_id'];
		$edocName = $row['doc_name'];
		$oldFiles = [
			[
				'edocId' => $oldEdocId,
				'name' => $edocName
			],
			[
				'edocId' => PHP_INT_MAX,
				'name' => 'Some eDoc that no longer exists'
			]
		];

		$key1 = 'test-key-1';
		$key2 = 'test-key-2';
		$key3 = 'test-key-3';
		$this->setConfig([
			'project-settings' => [
					[
						'key' => $key1,
						'type' => 'rich-text'
					],
					[
						'key' => $key2,
						'type' => 'text'
					],
					[
						'key' => 'sub-settings-key',
						'type' => 'sub_settings',
						'sub_settings' => [
							[
								'key' => $key3,
								'type' => 'rich-text'
							]
						]
					],
				]
			]
		);

		$getRichTextExampleContent = function($pid, $edocId) use ($edocName){
			return '<p><img src="' . htmlspecialchars(ExternalModules::getRichTextFileUrl(TEST_MODULE_PREFIX, $pid, $edocId, $edocName)) . '" alt="" width="150" height="190" /></p>';
		};

		$oldRichTextContent = $getRichTextExampleContent($oldProjectId, $oldEdocId);

		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1, $oldRichTextContent);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2, $oldRichTextContent);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3, [$oldRichTextContent]);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST, $oldFiles);
		ExternalModules::recreateAllEDocs(TEST_SETTING_PID);
		$newFiles = ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST);

		$newRichTextContent = $getRichTextExampleContent(TEST_SETTING_PID, $newFiles[0]['edocId']);
		$this->assertSame($newRichTextContent, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key1));

		// Make sure non-rich-text fields are not changed
		$this->assertSame($oldRichTextContent, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key2));

		// Rich text content within sub_settings is also JSON escaped.  Make sure we are still able replace URLs properly.
		$this->assertSame([$newRichTextContent], ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, $key3));

		$this->assertSame(count($oldFiles), count($newFiles));
		
		$oldFile = $oldFiles[0];
		$newFile = $newFiles[0];

		$oldEdocId = (string)$oldFile['edocId'];
		$newEdocId = $newFile['edocId'];

		$this->assertEdocsEqual($oldEdocId, $newEdocId);
		$this->assertSame($oldFile['name'], $newFile['name']);

		ExternalModules::deleteEDoc($newEdocId);

		// Just leave missing edocs as-is
		$this->assertSame($oldFiles[1]['edocId'], $newFiles[1]['edocId']);
	}

	function testIsValidTabledCron(){
		$assertTabledCron = function($valid, $json){
			$this->assertSame($valid, self::callPrivateMethod('isValidTabledCron', json_decode($json, true)));
		};

		$assertTabledCron(true, self::TABLED_CRON_EXAMPLE);
		$assertTabledCron(false, self::TIMED_CRON_EXAMPLE);
	}

	function testIsValidTimedCron(){
		$assertTimedCron = function($valid, $json){
			$this->assertSame($valid, self::callPrivateMethod('isValidTimedCron', json_decode($json, true)));
		};

		$assertTimedCron(false, self::TABLED_CRON_EXAMPLE);
		$assertTimedCron(true, self::TIMED_CRON_EXAMPLE);
	}

	function testGetSQLInClause(){
		$assert = function($expected, $columnName, $array){
			$this->assertSame("($expected)", ExternalModules::getSQLInClause($columnName, $array));
		};

		$assert("column_name IN ('1')", 'column_name', 1);
		$assert("column_name IN ('1')", 'column_name', '1');
		$assert("column_name IN ('1', '2')", 'column_name', [1, 2]);
		$assert("column_name IN ('1') OR column_name IS NULL", 'column_name', [1, 'NULL']);
		$assert("column_name\\' IN ('value\\'')", 'column_name\'', ['value\'']); // make sure quotes are escaped
		$assert("false", 'column_name', []);
	}

	function testGetSQLInClause_preparedStatements(){
		$assert = function($expectedSql, $expectedParams, $columnName, $array){
			list($actualSql, $actualParams) = ExternalModules::getSQLInClause($columnName, $array, true);
			
			$this->assertSame("($expectedSql)", $actualSql);
			$this->assertSame($expectedParams, $actualParams);
		};

		$assert("column_name IN (?)", [1], 'column_name', 1);
		$assert("column_name IN (?)", ['1'], 'column_name', '1');
		$assert("column_name IN (?, ?)", [1, 2], 'column_name', [1, 2]);
		$assert("column_name IN (?) OR column_name IS NULL", [1], 'column_name', [1, null]);
		$assert("column_name IN (?)", ['NULL'], 'column_name', ['NULL']);
		$assert("column_name\\' IN (?)", ['value\''], 'column_name\'', ['value\'']); // make sure quotes are escaped
		$assert("false", [], 'column_name', []);
	}

	function testIsCompatibleWithREDCapPHP_minVersions(){
		$versionTypes = [
			'PHP' => implode('.', [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION]),
			'REDCap' => REDCAP_VERSION
		];
		
		foreach($versionTypes as $versionType=>$systemVersion){
			$settingKey = strtolower($versionType) . "-version-min";

			$test = function($configMinVersion) use ($settingKey){
				$this->setConfig([
					'compatibility' => [
						$settingKey => $configMinVersion
					]
				]);
	
				$this->callPrivateMethod('isCompatibleWithREDCapPHP', TEST_MODULE_PREFIX, TEST_MODULE_VERSION);
			};

			$assertValid = function($configMinVersion) use ($test){
				// Simply make sure the following call completes without an Exception.
				$test($configMinVersion);
			};
	
			$assertInvalid = function($configMinVersion) use ($test, $versionType){
				$expectedMessage = "$versionType version";

				$this->assertThrowsException(function() use ($configMinVersion, $test){
					$test($configMinVersion);
				}, $expectedMessage);
			};
	
			list($major, $minor, $patch) = explode('.', $systemVersion);

			$assertValid("$major.$minor.$patch");
			$assertInvalid("$major.$minor." . ($patch+1));
			$assertValid($major);
			$assertValid($major-1);
			$assertInvalid($major+1);
		}
	}

	function testGetFrameworkAdjustedREDCapVersionMin(){
		$assert = function($expected, $config){
			$actual = ExternalModules::getFrameworkAdjustedREDCapVersionMin($config);
			$this->assertSame($expected, $actual);
		};
		
		$assert('8.0.0', [
			'framework-version' => 1
		]);

		$assert('9.0.0', [
			'compatibility' => [
				'redcap-version-min' => '9.0.0'
			]
		]);

		$assert('9.0.0', [
			'framework-version' => 2,
			'compatibility' => [
				'redcap-version-min' => '9.0.0'
			]
		]);

		$assert('9.1.1', [
			'framework-version' => 3,
			'compatibility' => [
				'redcap-version-min' => '9.0.0'
			]
		]);

		$emptyVersionValues = [null, "", "TBD"];
		foreach($emptyVersionValues as $emptyVersionValue){
			$assert('9.0.0', [
				'framework-version' => $emptyVersionValue,
				'compatibility' => [
					'redcap-version-min' => '9.0.0'
				]
			]);
	
			$assert('10.8.2', [
				'framework-version' => 7,
				'compatibility' => [
					'redcap-version-min' => $emptyVersionValue
				]
			]);
		}
	}

	function testTranslateConfig()
	{
		$settingOneTranslationKey = 'setting_one_name';
		$settingTwoTranslationKey = 'setting_two_name';
		$settingOneTranslatedName =  'Establecer Uno';
		$settingTwoTranslatedName =  'Establecer Two';

		$this->spoofTranslation(TEST_MODULE_PREFIX, $settingOneTranslationKey, $settingOneTranslatedName);
		$this->spoofTranslation(TEST_MODULE_PREFIX, $settingTwoTranslationKey, $settingTwoTranslatedName);

		$config = [
			'project-settings' => [
				[
					'key' => 'setting-one',
					'name' => 'Setting One',
					'tt_name' => $settingOneTranslationKey
				],
				[
					'key' => 'sub-settings-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						[
							'key' => 'setting-two',
							'name' => 'Setting Two',
							'tt_name' => $settingTwoTranslationKey
						]
					]
				]
			]
		];

		// callPrivateMethod() didn't work here in PHP 5.6 due to a quirk of passing parameters by references.
		// There might be a way to fix it so we don't need the workaround below.
		$class = new \ReflectionClass($this->getReflectionClass());
		$method = $class->getMethod('translateConfig');
		$method->setAccessible(true);

		$translatedConfig = $method->invokeArgs($this, [&$config, TEST_MODULE_PREFIX]);
		
		// set expected changes
		$config['project-settings'][0]['name'] = $settingOneTranslatedName;
		$config['project-settings'][1]['sub_settings'][0]['name'] = $settingTwoTranslatedName;

		$this->assertSame($translatedConfig, $config);
	}

	function testTt_basic()
	{
		$key1 = 'key1';
		$value = rand();
		$this->spoofTranslation(null, $key1, $value);

		$this->assertSame($value, ExternalModules::tt($key1));

		$key2 = 'key2';

		$this->assertStringContainsString("Language key '{$key2}' is not defined", ExternalModules::tt($key2));
	}

	function testTt_specialChars()
	{
		$key = 'key';
		$valuePrefix = 'Test - ';
		$languageFileEntry = $valuePrefix . '{0}';
		$this->spoofTranslation(null, $key, $languageFileEntry);
		
		$param = '<script>alert("This code should be escaped and displayed (NOT executed).")</script>';
		$expected = $valuePrefix . htmlspecialchars($param);
		$this->assertSame($expected, ExternalModules::tt($key, $param));
	}

	function testQuery_noParameters(){
		$value = (string)rand();
		$result = ExternalModules::query("select $value", []);
		$row = $result->fetch_row();
		$this->assertSame($value, $row[0]);

		$this->assertThrowsException(function(){
			// Assert that passing a parameter array is required (even if it's empty).
			ExternalModules::query("foo");
		}, ExternalModules::tt('em_errors_117'));
	}

	function testAddSurveyParticipantAndResponse(){
		$m = $this->getInstance();
		list($surveyId, $formName) = $this->getFramework()->getSurveyId(TEST_SETTING_PID);

		$participantId = ExternalModules::addSurveyParticipant($surveyId, $this->getEventIds(TEST_SETTING_PID)[0], $m->generateUniqueRandomSurveyHash());
		$this->assertIsInt($participantId);

		$responseId = ExternalModules::addSurveyResponse($participantId, 1, generateRandomHash());
		$this->assertIsInt($responseId);

		// The following delete cascades and deletes the redcap_surveys_response row as well.
		ExternalModules::query('delete from redcap_surveys_participants where participant_id = ?', $participantId);
	}

	function testGetSettingsQuery_projectIds(){
		$assert = function($projectIds, $hasInClause, $hasNullClause){
			$query = ExternalModules::getSettingsQuery(null, $projectIds);
			$sql = $query->getSQL();
			
			$this->assertSame($hasInClause, strpos($sql, 'project_id IN ') !== false);
			$this->assertSame($hasNullClause, strpos($sql, 'project_id IS NULL') !== false);
		};
		
		$assert(null, false, false);
		$assert(ExternalModules::SYSTEM_SETTING_PROJECT_ID, false, true);
		$assert([ExternalModules::SYSTEM_SETTING_PROJECT_ID], false, true);
		$assert([ExternalModules::SYSTEM_SETTING_PROJECT_ID, 1], true, true);
		$assert([1], true, false);
		$assert(1, true, false);

		// I'm not sure if these cases are actually ever used.
		// If they are, perhaps they shouldn't be.
		$assert('', false, true);
		$assert(0, false, true);
	}

	function testCronJobMethods(){
		$m = $this->getInstance();
		$prefix = $m->PREFIX;
		$moduleId = ExternalModules::getIdForPrefix($prefix);

		$name = "UnitTestCron";
		$expectedCron = [
			"cron_name" => $name,
			"cron_description" => "This is only a test.",
			"method" =>"some_method",
			"cron_frequency" =>  "99999",
			"cron_max_run_time" => "1"
		];

		$getCron = function() use ($name, $moduleId){
			return ExternalModules::getCronJobFromTable($name, $moduleId);
		};

		try{
			ExternalModules::addCronJobToTable($expectedCron, $this->getInstance());

			unset($expectedCron['method']);
			
			$actualCron = $getCron();
			$this->assertSame($expectedCron, $actualCron);
			
			$expectedCron['cron_description'] = 'A new description.';
			ExternalModules::updateCronJobInTable($expectedCron, $moduleId);
			$actualCron = $getCron();

			$this->assertSame($expectedCron, $actualCron);
		}
		finally{
			ExternalModules::removeCronJobs($prefix);
			$this->assertTrue(empty($getCron()));
		}
	}

	function testGetPrefixForID(){
		$id = ExternalModules::getIDForPrefix(TEST_MODULE_PREFIX);
		$this->assertSame(TEST_MODULE_PREFIX, ExternalModules::getPrefixForID($id));
	}

	function testGetModuleVersionByPrefix(){
		$m = $this->getInstance();
		$m->setSystemSetting(ExternalModules::KEY_VERSION, TEST_MODULE_VERSION);
		$this->assertSame(TEST_MODULE_VERSION, ExternalModules::getModuleVersionByPrefix(TEST_MODULE_PREFIX));
	}

	function testLimitDirectFileAccess(){
		$assert = function($expectedException = null){
			if($expectedException){
				$this->assertThrowsException(function(){
					ExternalModules::limitDirectFileAccess();
				}, self::tt($expectedException));
			}
			else{
				// Make sure it runs without throwing an exception;
				ExternalModules::limitDirectFileAccess();
			}
		};

		// We should not throw an exception on command line requests where HTTP_HOST is not set.
		$this->assertFalse(isset($_SERVER['HTTP_HOST']));
		$assert();

		$_SERVER['HTTP_HOST'] = parse_url(APP_PATH_WEBROOT_FULL, PHP_URL_HOST);

		$_SERVER['PHP_SELF'] = parse_url(APP_PATH_WEBROOT_FULL, PHP_URL_PATH) . 'some-redcap-core-path';
		$assert();

		foreach([
			// Allowed paths should be explicitly listed, excluding anything else by default.
			'index.php',
			'module-ajax/jsmo-ajax.php',
			'bin/install-scan-script.php',
			'manager/any-manager-path',
		] as $path){
			$_SERVER['PHP_SELF'] = parse_url(APP_URL_EXTMOD, PHP_URL_PATH) . $path;
			$assert();
		}

		foreach([
			'manager/templates/any-template.php',
			'any-other-em-path',
		] as $path){
			$_SERVER['PHP_SELF'] = parse_url(APP_URL_EXTMOD, PHP_URL_PATH) . $path;
			$assert('em_errors_121');
		}

		/**
		 * Simulates the login form being displayed while loading a framework page.
		 * We don't want to check CSRF tokens in this case, or they will interfere
		 * with login page errors (e.g. incorrect password).
		 */
		$_SERVER['PHP_SELF'] = parse_url(APP_URL_EXTMOD, PHP_URL_PATH) . 'manager/any-manager-path';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$assert();

		// Make sure tokens are checked when logged in
		ExternalModules::setUsername('foo');
		$assert('em_errors_156');

		// OCD extra check
		ExternalModules::setUsername(null);
		$assert();

		// Make sure tokens are checked on NOAUTH pages
		$_GET['NOAUTH'] = '';
		$assert('em_errors_156');

		$csrfToken = $this->getFramework()->getCSRFToken();
		$_POST['redcap_external_module_csrf_token'] = $csrfToken;
		$_COOKIE['redcap_external_module_csrf_token'] = $csrfToken;
		$assert();
	}

	function testRequireInteger(){
		foreach([1, '1'] as $value){
			$intValue = $this->requireInteger(1);
			$this->assertEquals($value, $intValue);
		}

		foreach([1.1, '1.1'] as $value){
			$this->assertThrowsException(function() use ($value){
				$this->requireInteger($value);
			}, self::tt("em_errors_60", $value));
		}
	}
	
	function testSetRecordCompleteStatus(){
		$projectId = TEST_SETTING_PID;
		$recordId = 1;
		$eventId = $this->getFramework()->getEventId($projectId);
		$formName = ExternalModules::getFormNames($projectId)[0];

		$getValue = function() use ($projectId, $recordId, $eventId, $formName){
			return ExternalModules::getRecordCompleteStatus($projectId, $recordId, $eventId, $formName);
		};

		if($getValue() === null){
			$table = \Records::getDataTable(TEST_SETTING_PID);
			$this->query(
				"insert into $table values (?,?,?,?,?,null)",
				[$projectId, $eventId, $recordId, "{$formName}_complete", 1]
			);
		}
		else{
			ExternalModules::setRecordCompleteStatus($projectId, $recordId, $eventId, $formName, 1);
		}

		$this->assertSame('1', $getValue());
		ExternalModules::setRecordCompleteStatus($projectId, $recordId, $eventId, $formName, 0);
		$this->assertSame('0', $getValue());
	}

	function testGetRepoModuleId(){
		$fakeModuleName = "external_module_framework_fake_unit_test_module_v1.0";
		$fakeModuleId = '-1';

		$this->query('insert into redcap_external_modules_downloads values (?, ?, now(), now())', [$fakeModuleName, $fakeModuleId]);

		$this->assertSame($fakeModuleId, ExternalModules::getRepoModuleId($fakeModuleName));

		$this->query('delete from redcap_external_modules_downloads where module_id = ?', $fakeModuleId);
	}

	function testInitializeFramework_unsupportedVersions(){
		// We only test the exception case here.
		// FrameworkV1Test tests actual use cases well.
		$unsupportedFrameworkVersion = ExternalModules::getMaxSupportedFrameworkVersion() + 1;
		
		$this->assertThrowsException(function() use ($unsupportedFrameworkVersion){
			$this->setConfig([
				'framework-version' => $unsupportedFrameworkVersion
			]);
		}, self::tt('em_errors_130', $unsupportedFrameworkVersion));
	}

	function testIsHookCallAllowed(){
		$assert = function($expected, $previous, $current){
			if($previous){
				$previous = new HookRunner("redcap_$previous");
			}

			$current = new HookRunner("redcap_$current");

			$this->assertSame($expected, $this->callPrivateMethod('isHookCallAllowed', $previous, $current));
		};
		
		$assert(true, null, 'email');
		$assert(true, 'foo', 'email');
		$assert(false, 'email', 'email');
	}

	function testExitAfterHook(){
		$methodName = 'redcap_test_call_function';

		$exited = false;
		ExternalModules::$exitAfterHookAction = function() use (&$exited){
			$exited = true;
		};

		ExternalModules::callHook($methodName, [function(){
			// do nothing
		}]);
		$this->assertFalse($exited);

		ExternalModules::callHook($methodName, [function($module){
			$module->exitAfterHook();
		}]);
		$this->assertTrue($exited);
	}

	function testGetAdditionalFieldChoices_project(){
		$project = $this->getFramework()->getProject(TEST_SETTING_PID);

		$username = $this->getRandomUsername();
		ExternalModules::setUsername($username);
		ExternalModules::setSuperUser(false);

		$assert = function($expected) use ($project, $username){
			$choices = ExternalModules::getAdditionalFieldChoices(['type' => 'project-id'], null)['choices'];

			$actual = false;
			foreach($choices as $choice){
				if($choice['value'] === (string)$project->getProjectId()){
					$actual = true;
					break;
				}
			}

			$this->assertSame($expected, $actual, "Failed on user '$username'");
		};

		$project->removeUser($username);
		$assert(false);

		$project->addUser($username, [
			'design' => 1,
		]);
		$assert(true);

		$project->setRights($username, [
			'design' => 0,
		]);
		$assert(false);

		$roleName = 'Some Role';
		if($project->getRoleId($roleName) !== null){
			$project->removeRole($roleName); // remove roles added from previous tests
		}

		$project->addRole($roleName, [
			'design' => 1,
		]);
		$project->setRoleForUser($roleName, $username);
		$assert(true);

		$project->removeRole($roleName);
		$project->addRole($roleName, [
			'design' => 0,
		]);
		$project->setRoleForUser($roleName, $username);
		$assert(false);

		ExternalModules::setSuperUser(true);
		$assert(true);
	}

	private function getTestRoleId(){
		$m = $this->getInstance();
		$project = $m->getProject(TEST_SETTING_PID);

		$roleId = $project->getRoleId(TEST_ROLE_NAME);
		if($roleId === null){
			$project->addRole(TEST_ROLE_NAME, []);
		}

		return $project->getRoleId(TEST_ROLE_NAME);
	}

	private function getEdocPathRoot($project_id = ''){
		// Get this edoc path a funny way so that this doesn't count as a reference to th EDOC_PATH constant.
		$edocPath = constant('EDOC_PATH');
		$edocPath .= (!empty($project_id)) ? \Files::getLocalStorageSubfolder($project_id, true) : '' ;
		return $edocPath;
	}

	function testGetEdocPath(){
		$edoc = $this->findAnEdoc();
		$this->assertSame(
			$this->getEdocPathRoot($edoc['project_id']) . $edoc['stored_name'],
			$this->getEdocPath($edoc['doc_id'])
		);
	}

	function testErrorLog(){
		$expected = (string) rand();

		ob_start();
		ExternalModules::errorLog($expected);
		$actual = ob_get_clean();

		$this->assertSame($expected . "\n", $actual);
	}

	function testErrorLog_chunks(){
		$expected1 = '';
		$expected2 = '';
		$expected3 = '';

		$max = 2001;
		for($i=1; $i<=$max; $i++){
			if($i <= 1000){
				$expected1 .= 1;
			}
			else if($i <= 2000){
				$expected2 .= 2;
			}
			else{
				$expected3 .= 3;
			}
		}

		$expected = 
			ExternalModules::getChunkPrefix(1, 3) . $expected1 . "\n" . 
			ExternalModules::getChunkPrefix(2, 3) . $expected2 . "\n" .
			ExternalModules::getChunkPrefix(3, 3) . $expected3 . "\n"
		;

		ob_start();
		ExternalModules::errorLog($expected1 . $expected2 . $expected3);
		$actual = ob_get_clean();

		$this->assertSame($expected, $actual);
	}

	function testEnsureFrameworkVersionsFullyImplemented(){
		$redcapVersions = $this->callPrivateMethod('getMinREDCapVersionsByFrameworkVersion');
		
		$frameworkVersion = ExternalModules::getMaxSupportedFrameworkVersion();
		while($frameworkVersion >= 1){
			$this->assertNotEmpty($redcapVersions[$frameworkVersion]['standard'], "Standard version not found for v$frameworkVersion");
			$this->assertNotEmpty($redcapVersions[$frameworkVersion]['lts'], "LTS version not found for v$frameworkVersion");

			$this->assertFileExists(__DIR__ . "/../docs/versions/v$frameworkVersion.md", "Documentation must be added for framework version $frameworkVersion.");

			$frameworkVersion--;
		}
	}

	function testEnsureAllDocsPagesAreIndexed(){
		$docsPath = __DIR__ . "/../docs";
		$mainReadMe = file_get_contents("$docsPath/README.md");
		$lines = explode("\n", $mainReadMe);
		foreach($lines as $line){
			if(!starts_with($line, '[')){
				continue;
			}

			$parts = explode(']', $line);
			$parts = explode('(', $parts[1]);
			$path = explode(')', $parts[1])[0];

			$indexedFiles[$path] = true;
		}

		$iterator = new \RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsPath, RecursiveDirectoryIterator::SKIP_DOTS));
		$missingLinks = [];
		foreach($iterator as $file) {
			$relativePath = substr($file->getPathname(), strlen($docsPath)+1);

			if(
				$file->getExtension() !== 'md'
				||
				$relativePath === 'README.md'
				||
				starts_with($relativePath, 'methods/')
				||
				starts_with($relativePath, 'versions/')
				||
				starts_with($relativePath, 'exercises/')
			){
				continue;
			}

			if(!isset($indexedFiles[$relativePath])){
				$missingLinks[] = $relativePath;
			}

		}

		$this->assertSame([], $missingLinks, "Links are missing in docs/README.md is for these files:");
	}

	function testGetFrameworkInstance_missingDir(){
		$prefix = "some_nonexistent_dir";
		$version = '1.2.3';

		$this->expectExceptionMessage(ExternalModules::tt('em_errors_155', $version, $prefix));
		ExternalModules::getFrameworkInstance($prefix, $version);
	}

	function testIsManagerPage(){
		$spoofURL = function($suffix){
			$this->spoofURL(APP_URL_EXTMOD . $suffix);
		};

		$spoofURL('');
		$this->assertFalse($this->isManagerPage());

		$spoofURL('manager/');
		$this->assertTrue($this->isManagerPage());
		$this->assertFalse($this->isManagerPage('foo'));

		$spoofURL('manager/foo');
		$this->assertTrue($this->isManagerPage());
		$this->assertTrue($this->isManagerPage('foo'));

		// Make sure detection can't be curcumvented by adding any number of extra slashes
		$spoofURL('////manager/////foo');
		$this->assertTrue($this->isManagerPage());
		$this->assertTrue($this->isManagerPage('foo'));
	}

	private function exportSettings(){
		ob_start();
		require __DIR__ . '/../manager/ajax/export-settings.php';
		$parts = explode("\n{", ob_get_clean());

		if(count($parts) === 1){
			// There were no logged errors.  Insert an empty string in their place.
			array_unshift($parts, '');
		}
		else{
			// Reinsert the removed leading bracket
			$parts[1] = '{' . $parts[1];
		}
			
		return [
			'logged-errors' => $parts[0],
			'response' => json_decode($parts[1], true)
		];
	}

	private function assertExportSettings($expectedWarningMessages){
		$this->setProjectId(TEST_SETTING_PID);
		$_POST['prefixes'] = [TEST_MODULE_PREFIX];

		$this->assertSame(
			ExternalModules::getSettingExportResponse([TEST_MODULE_NAME], $expectedWarningMessages),
			$this->exportSettings()['response']
		);
	}

	private function assertImportSettings($expectedWarningMessages, $expectedModuleNames = [TEST_MODULE_NAME]){
		$_FILES['file']['tmp_name'] = ExternalModules::getAndClearExportedSettingsPath();

		ob_start();
		require __DIR__ . '/../manager/ajax/import-settings.php';
		$actual = ob_get_clean();

		$expected = json_encode(ExternalModules::getSettingImportResponse($expectedModuleNames, $expectedWarningMessages));
		$this->assertSame($expected, $actual);
	}

	function testSettingExportAndImport(){
		$assertException = function($expected){
			$result = $this->exportSettings();
			$this->assertSame($expected, $result['response']['message']);
			$this->assertStringContainsString($expected, $result['logged-errors']);
		};

		$assertException(ExternalModules::tt("em_manage_97"));

		$_POST['prefixes'] = [TEST_MODULE_PREFIX];

		$assertException(ExternalModules::tt("em_errors_65", "pid"));

		$this->setProjectId(TEST_SETTING_PID);

		$textSettingKey = 'some-value';
		$fileSettingKey = 'some-file';
		$armSettingKey = 'some-arm';
		$eventSettingKey = 'some-event';
		$roleSettingKey = 'some-role';
		$dagSettingKey = 'some-dag';
		$this->setConfig([
			'framework-version' => 9,
			'project-settings' => [
					[
						'key' => $fileSettingKey,
						'type' => 'file'
					],
					[
						'key' => $armSettingKey,
						'type' => 'arm-list'
					],
					[
						'key' => $eventSettingKey,
						'type' => 'event-list'
					],
					[
						'key' => $roleSettingKey,
						'type' => 'user-role-list'
					],
					[
						'key' => $dagSettingKey,
						'type' => 'dag-list'
					],
			]
		]);

		try{
			$m = $this->getInstance();
			$project = ExternalModules::getREDCapProjectObject(TEST_SETTING_PID);
	
			$roleName = 'Role ' . rand();
			$m->addRole($roleName, []);
			$row = $this->findAnEdoc();
	
			$expectedEdoc = $row['doc_id'];
			$edocPath = ExternalModules::getEdocPath($expectedEdoc);
	
			$expectedEdocContent = file_get_contents($edocPath);
			$this->assertTrue(strlen($expectedEdocContent) > 10); // Make sure it's a real file

			$m->setProjectSetting($fileSettingKey, $expectedEdoc);

			$textValue = [[rand(), 'a'], true, null]; // Also tests different value types and nested sub-settings
			$m->setProjectSetting($textSettingKey, $textValue);

			$armValue = $project->events[1]['id'];
			$m->setProjectSetting($armSettingKey, $armValue);

			$eventValue = $m->getEventId();
			$m->setProjectSetting($eventSettingKey, $eventValue);
	
			$roleValue = $m->getRoleId($roleName);
			$m->setProjectSetting($roleSettingKey, $roleValue);

			$dagValue = $this->getTestDAGID();
			$m->setProjectSetting($dagSettingKey, $dagValue);
	
			$this->assertExportSettings([]);
	
			foreach([
				$textSettingKey,
				$fileSettingKey,
				$armSettingKey,
				$eventSettingKey,
				$roleSettingKey,
				$dagSettingKey,
			] as $key){
				$m->removeProjectSetting($key);
				$this->assertNull($m->getProjectSetting($key));
			}
	
			$textSettingKey2 = 'some-other-value';
			$m->setProjectSetting($textSettingKey2, rand());
	
			$systemValue = rand();
			$m->setSystemSetting($textSettingKey, $systemValue);
	
			$otherProjectValue = rand();
			ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID_2, $textSettingKey, $otherProjectValue);
	
			$this->assertImportSettings([]);
	
			$this->assertSame($textValue, $m->getProjectSetting($textSettingKey));
			$this->assertSame($armValue, $m->getProjectSetting($armSettingKey));
			$this->assertSame($eventValue, $m->getProjectSetting($eventSettingKey));
			$this->assertSame($roleValue, $m->getProjectSetting($roleSettingKey));
			$this->assertSame($dagValue, $m->getProjectSetting($dagSettingKey));
	
			$actualEDocId = $m->getProjectSetting($fileSettingKey);
			$this->assertNotSame($expectedEdoc, $actualEDocId, 'Ensure a new edoc was created');
			$this->assertSame($expectedEdocContent, file_get_contents(ExternalModules::getEdocPath($actualEDocId)));
	
			$this->assertNull($m->getProjectSetting($textSettingKey2), "Make sure all settings not included in the export were removed");
			$this->assertSame($systemValue, $m->getSystemSetting($textSettingKey), "Make sure system values are NOT removed");
			$this->assertSame($otherProjectValue, ExternalModules::getProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID_2, $textSettingKey), "Make sure values for other projects are NOT removed");
		}
		finally{
			$m->removeRole($roleName);
		}
	}

	function testSettingImport_moduleNotEnabled(){
		$this->assertExportSettings([]);
		$this->spoofEnabledModules([]);
		$this->assertImportSettings([
			ExternalModules::tt("em_manage_102"),
			[
				TEST_MODULE_PREFIX
			]
		], []);
	}

	function testSettingExportAndImport_projectIDs(){
		$settingName = 'Project Dropdown ' . rand();

		$this->setConfig([
			'project-settings' => [
					[
						'key' => TEST_SETTING_KEY,
						'name' => $settingName,
						'type' => 'project-id'
					],
			]
		]);

		$this->setProjectSetting(rand());

		$this->assertExportSettings([]);
		$this->assertImportSettings([
			ExternalModules::tt('em_manage_104'),
			[TEST_MODULE_NAME],
			[[$settingName]]
		]);
		
		$this->assertNull($this->getProjectSetting());
	}

	/**
	 * This test in important since processNestedSettingValues()
	 * has no way of knowing whether arrays containt subsettings
	 * or arbitrary arrays saved by module code.
	 */
	function testSettingExportAndImport_arrayWithNumericKeysThatAreNotSubSettings(){
		$value = [
			'foo' => [
				'goo' => 'doo'
			]
		];

		$this->setProjectSetting($value);

		$this->assertExportSettings([]);
		$this->assertImportSettings([]);
		
		$this->assertSame($value, $this->getProjectSetting());
	}

	function testSettingExport_superUserOnly(){
		$this->setProjectSetting(rand());
		$this->assertExportSettings([]);

		$settingName = 'Super User Setting ' . rand();
		$this->setConfig([
			'project-settings' => [
					[
						'key' => TEST_SETTING_KEY,
						'name' => $settingName,
						'super-users-only' => true
					],
			]
		]);
		
		$this->assertExportSettings([
			ExternalModules::tt('em_manage_108'),
			[TEST_MODULE_NAME],
			[[$settingName]]
		]);
		$this->assertImportSettings([]);
	}

	function testSettingExport_richTestUploadedFileList(){
		$edoc = $this->findAnEdoc();
		$edocId = $edoc['doc_id'];
		$edocName = $edoc['doc_name'];
		
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, ExternalModules::RICH_TEXT_UPLOADED_FILE_LIST, [[
			'edocId' => $edocId,
			'name' => $edocName
		]]);

		$this->assertExportSettings([]);
		$zipPath = ExternalModules::getAndClearExportedSettingsPath();
		$dirPath = $this->createTempDir();
		$zip = new \ZipArchive;
		$openResult = $zip->open($zipPath);
		if($openResult !== true){
			throw new \Exception("Error opening zip file: $openResult");
		}
	
		$zip->extractTo($dirPath);

		$this->assertSame($edoc['doc_size'], filesize("$dirPath/edocs/$edocId/$edocName"), "Make sure edocs in the RICH_TEXT_UPLOADED_FILE_LIST are included");

		unlink($zipPath);
	}

	function testSettingImport_superUserOnly(){
		$this->setProjectSetting(rand());
		$this->assertExportSettings([]);

		$settingName = 'Super User Setting ' . rand();
		$this->setConfig([
			'project-settings' => [
					[
						'key' => TEST_SETTING_KEY,
						'name' => $settingName,
						'super-users-only' => true
					],
			]
		]);

		$this->assertImportSettings([
			ExternalModules::tt('em_manage_105'),
			[TEST_MODULE_NAME],
			[[$settingName]]
		]);
		
		$this->assertNull($this->getProjectSetting());
	}

	function testGetSettingImportResponse(){
		$assert = function($moduleNames, $warnings){
			$simpleArrayToHTMLList = function($items){
				return '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
			};

			$expectedMessage = '';

			if(!empty($moduleNames)){
				$expectedMessage .= ExternalModules::tt('em_manage_101') . $simpleArrayToHTMLList($moduleNames);
			}

			if(!empty($warnings)){
				$expectedMessage .= ExternalModules::tt('em_manage_103') . $simpleArrayToHTMLList($warnings);
			}

			$this->assertSame(
				['message' => $expectedMessage],
				ExternalModules::getSettingImportResponse($moduleNames, $warnings)
			);
		};

		$assert([], []);
		$assert([rand(), rand()], []);
		$assert([rand()], [rand()]);
		$assert([rand()], [rand(), rand()]);
	}
	
	private function assertNormalizeModuleZip($beforePaths, $afterPaths){
		$zipPath = $this->createTempFile();

		$zip = new \ZipArchive;
		$zip->open($zipPath, \ZipArchive::OVERWRITE);
		foreach($beforePaths as $filePath){
			$zip->addFromString($filePath, '');
		}

		$expectedDirName = explode('/', $afterPaths[0])[0];

		ExternalModules::normalizeModuleZip($expectedDirName, $zip);
		$zip->close();

		$zip = new \ZipArchive;
		$zip->open($zipPath);

		for($i=0; $i<count($afterPaths); $i++){
			$this->assertSame($afterPaths[$i], $zip->getNameIndex($i));	
		}

		$this->assertFalse($zip->getNameIndex(count($afterPaths)));
	}

	function testNormalizeModuleZip_containingFolder(){
		$this->assertNormalizeModuleZip(
			[
				'oldDir/config.json',
				'oldDir/some-other-file'
			],
			[
				'newDir/config.json',
				'newDir/some-other-file'
			]
		);
	}

	function testNormalizeModuleZip_containingFolderWithOtherFile(){
		$this->assertNormalizeModuleZip(
			[
				'oldDir/config.json',
				'__MACOSX'
			],
			[
				'newDir/config.json',
				'newDir__MACOSX' // This is weird, but expected and not problematic with the current implementation.
			]
		);
	}

	function testNormalizeModuleZip_noContainingFolder(){
		$this->assertNormalizeModuleZip(
			[
				'config.json',
				'some-other-file'
			],
			[
				'newDir/config.json',
				'newDir/some-other-file'
			]
		);
	}

	function testAllPossibleRepeatableDataScenarios(){
		$assert = function($message, $identifiers){
			$pid = TEST_SETTING_PID;
			$eventNames = ['event_1'];

			$hasInstrument = in_array('redcap_repeat_instrument', $identifiers);

			$hasInstances = in_array('redcap_repeat_instance', $identifiers);
			if($hasInstances){
				$instanceCount = 2;
			}
			else{
				$instanceCount = 1;
			}

			$newIdentifiers = [];
			foreach($identifiers as $name){
				if($name === 'record_id'){
					$name = TEST_RECORD_ID;
				}
				else if($name === 'redcap_event_name'){
					$pid = TEST_SETTING_PID_2;

					if(!$hasInstances){
						$eventNames = ['non_repeating'];
					}
					else if(!$hasInstrument){
						$eventNames = ['event_2'];
					}
				}
				else if($name === 'field_name'){
					if($hasInstances){
						$name = TEST_REPEATING_FIELD_1;
					}
					else{
						$name = TEST_TEXT_FIELD;
					}
				}

				$newIdentifiers[] = $name;
			}
			$identifiers = $newIdentifiers;

			$expected = [];
			$recordIds = [];
			foreach($eventNames as $eventName){
				foreach([1,2] as $recordNumber){
					for($instanceNumber=1; $instanceNumber<=$instanceCount; $instanceNumber++){
						$row = [];
						foreach($identifiers as $name){
							if($name === 'redcap_event_name'){
								$value = $eventName . '_arm_1';
							}
							else if($name === 'redcap_repeat_instrument'){
								$value = TEST_REPEATING_FORM;
							}
							else if($name === TEST_RECORD_ID){
								do{
									$value = (string) rand(10, 99);
								} while(in_array($value, $recordIds));
								
								$recordIds[] = $value;
							}
							else if($name === 'redcap_repeat_instance'){
								$value = $instanceNumber;
							}
							else{
								$value = (string) rand(100, 999);
							}

							$row[$name] = $value;
						}

						$expected[] = $row;
					}
				}
			}

			usort($expected, function($a, $b){
				return $a[TEST_RECORD_ID] - $b[TEST_RECORD_ID];
			});

			$this->saveData($expected, $pid);
			$actual = json_decode(REDCap::getData($pid, 'json', null, $identifiers), true);
			$this->deleteRecords($pid, $recordIds);

			$newActual = [];
			foreach($actual as $row){
				$newRow = [];
				$hasFieldOtherThanIdAndEvent = false;
				foreach($row as $field=>$value){
					if($value === ''){
						// Some identifier values return as empty string depending on the scenario,
						// but we don't care about that for this test.
					}
					else{
						$newRow[$field] = $value;

						if(!in_array($field, [TEST_RECORD_ID, 'redcap_event_name'])){
							$hasFieldOtherThanIdAndEvent = true;
						}	
					}
				}

				if(!$hasFieldOtherThanIdAndEvent){
					continue;
				}

				$newActual[] = $newRow;
			}
			$actual = $newActual;

			$this->assertSame($expected, $actual, "Scenario: $message");
		};

		$assert(
			"Classical without repeatable instruments",
			[
				'record_id',
				'field_name',
			]
		);

		$assert(
			"Longitudinal without repeatable instruments",
			[
				'record_id',
				'redcap_event_name',
				'field_name',
			]
		);

		$assert(
			"Classical with repeatable instruments",
			[
				'record_id',
				'redcap_repeat_instrument',
				'redcap_repeat_instance',
				'field_name',
			]
		);

		$assert(
			"Longitudinal with repeatable events",
			[
				'record_id',
				'redcap_event_name',
				'redcap_repeat_instance',
				'field_name',
			]
		);
		
		$assert(
			"Longitudinal with repeatable instruments",
			[
				'record_id',
				'redcap_event_name',
				'redcap_repeat_instrument',
				'redcap_repeat_instance', // Associated with the instrument, not the event name
				'field_name',
			]
		);
	}

	function testApplyHidden(){
		$withoutHidden = [
			[
				'type' => 'sub_settings',
				'sub_settings' => [
					[
						'name' => rand()
					]
				]
			]
		];

		$withHidden = $withoutHidden;
		array_unshift($withHidden, ['hidden' => true]);
		array_unshift($withHidden[1]['sub_settings'], ['hidden' => true]);

		$expected = ['project-settings' => $withoutHidden];
		$actual = ['project-settings' => $withHidden];

		$this->assertSame($expected, $this->applyHidden($actual));
	}

	function testGetProjectId(){
		$this->assertSame(null, ExternalModules::getProjectId());

		$pid = (string) rand();
		$this->assertSame($pid, ExternalModules::getProjectId($pid));

		$this->setProjectId($pid);
		$this->assertSame($pid, ExternalModules::getProjectId());
	}

	function testHasDesignRights_partial(){
		$assert = function($expected, $pid = TEST_SETTING_PID){
			$this->assertSame($expected, ExternalModules::hasDesignRights($pid));
		};

		$this->setSuperUser(true);
		$assert(true);

		$this->setSuperUser(false);
		$assert(false);
	}

	function testHasModuleConfigurationUserRights_partial(){
		$assert = function($expected){
			$this->assertSame($expected, ExternalModules::hasModuleConfigurationUserRights());
		};

		$this->setSuperUser(true);
		$assert(true);

		$this->setSuperUser(false);
		$assert(false);
	}
	
	function testGetDocumentationUrl_partial(){
		$url = ExternalModules::getUrl(TEST_MODULE_PREFIX, 'README.md');
		$this->assertSame($url, ExternalModules::getDocumentationUrl(TEST_MODULE_PREFIX));
	}

	function testGetRichTextFileUrl(){
		$prefix = 'a';
		$pid = rand();
		$edocId = rand();
		$name = 'b.jpg';

		$this->assertSame(
			ExternalModules::getModuleAPIUrl() . "page=/manager/rich-text/get-file.php&file=$edocId.jpg&prefix=$prefix&pid=$pid&NOAUTH",
			ExternalModules::getRichTextFileUrl($prefix, $pid, $edocId, $name)
		);
	}

	function testGetUnsafeEDocReferences(){
		$this->setConfig([
			'project-settings' => [
				[
					'key' => 'sub-settings-key',
					'type' => 'sub_settings',
					'sub_settings' => [ // test sub_settings to ensure walkSubSettings() works correctly
						[
							'key' => TEST_SETTING_KEY,
							'type' => 'file'
						]
					]
				]
			]
		]);

		$edoc = $this->findAnEdoc();
		$this->setProjectSetting($edoc['doc_id']);

		$this->assertSame([
			TEST_SETTING_PID => [
				[
					'prefix' => TEST_MODULE_PREFIX,
					'pid' => TEST_SETTING_PID,
					'key' => TEST_SETTING_KEY,
					'edocId' => (int) $edoc['doc_id'],
					'sourcePid' => (string) $edoc['project_id'],
				]
			]
		], ExternalModules::getUnsafeEDocReferences());
	}

	function testActivationRequest_partial(){
		$require = function(){
			require APP_PATH_EXTMOD . 'manager/activation-request.php';
		};

		$this->setSuperUser(true);
		$_GET['request_id'] = rand();
		$this->setProjectId((string) rand());

		$this->assertThrowsException($require, $this->tt('em_errors_50'));
	}

	/**
	 * This test was added solely so that PHP warnings would be reported during unit testing.
	 */
	function testWarningsOnPages(){
		// Define these to prevent warnings
		global
			$lang,
			$auth_meth_global,
			$external_modules_project_custom_text,
			$user_firstname,
			$user_lastname
		;

		$this->setPrefix(TEST_MODULE_PREFIX);

		foreach([
			'control_center.php' => $this->tt('em_manage_52'),
			'crons.php' => 'Manager for Timed Crons',
			'show-duplicated-edocs.php' => 'no unsafe references exist',

			// Commented because REDCap::getProjectTitle() requires the PROJECT_ID constant to be set, which could cause problems for other unit tests.
			// 'project.php' => 'some string that will never be found',
		] as $file => $expectedLanguage){
			if($file === 'project.php'){
				$this->setProjectId(TEST_SETTING_PID);
			}

			ob_start();
			require APP_PATH_EXTMOD . "manager/$file";
			$this->assertStringContainsString($expectedLanguage, ob_get_clean());
		}
	}
	
	function testRemoveEditorDirectories(){
		$dir = $this->createTempDir();
		$file = "$dir/" . rand();
		file_put_contents($file, '');

		foreach(ExternalModules::EDITOR_DIRECTORIES as $subDir){
			$subDir = "$dir/$subDir";
			mkdir($subDir);
			$subFile = "$subDir/" . rand();
			file_put_contents($subFile, '');

			$this->assertFileExists($subDir);
			$this->assertFileExists($subFile);
			$this->assertFileExists($file);

			ExternalModules::removeEditorDirectories($dir);

			$this->assertFileDoesNotExist($subDir);
			$this->assertFileExists($file);
		}
	}

	function testTruncateArrayValues(){
		$this->assertSame(
			[
				'a',
				'bb',
				'cc',
				'dd',
				[]  // Make sure non-string values are unaffected
			],
			ExternalModules::truncateArrayValues([
				'a',
				'bb',
				'ccc',
				'dddd',
				[]
			], 2)
		);
	}

	function testProcessNestedSettingValuesForRow(){
		$oldValue = rand();
		$newValue = rand();

		$action = function($value) use ($oldValue, $newValue){
			if($value === $oldValue){
				$value = $newValue;
			}

			return $value;
		};

		$assert = function($type, $before, $after) use ($action){
			$unchangedValue = rand();

			$buildArray = function($value) use ($unchangedValue){
				return [$unchangedValue, $value, $unchangedValue, $value];
			};
			
			// Build an array to make sure sub_setting recursion works, and null values are unaffected.
			$before = $buildArray($before);
			$after = $buildArray($after);

			$expectedRow = [
				'type' => $type,
				'value' => json_encode($before),
				'some_other_column' => rand()
			];

			$actualRow = ExternalModules::processNestedSettingValuesForRow($expectedRow, $action);

			$this->assertSame($after, json_decode($actualRow['value']));
		};
		
		$assert('string', $oldValue, $oldValue);
		$assert('json-array', $oldValue, $newValue);
	}

	private function getConvertSettingCases(){
		$m = $this->getInstance();
		$project = ExternalModules::getREDCapProjectObject(TEST_SETTING_PID);

		$this->setConfig([
			'framework-version' => 9
		]);

		return [
			[
				'type' => 'arm-list',
				'id' => $project->events[1]['id'],
				'name' => 'Arm 1'
			],
			[
				'type' => 'event-list',
				'id' => $m->getEventId(TEST_SETTING_PID),
				'name' => 'event_1_arm_1'
			],
			[
				'type' => 'user-role-list',
				'id' => $this->getTestRoleID(),
				'name' => TEST_ROLE_NAME
			],
			[
				'type' => 'dag-list',
				'id' => $this->getTestDAGID(),
				'name' => TEST_DAG_NAME
			],
		];
	}

	function testConvertSettingValueForExport(){
		$nonExistentId = 9999999999;
		$project = ExternalModules::getREDCapProjectObject(TEST_SETTING_PID);
		
		$assert = function($type, $input, $output) use ($project){
			$this->assertSame($output, $this->convertSettingValueForExport($project, $type, $input), 'Testing type: ' . $type);
		};

		$value = rand();
		$assert('whatever', $value, $value);

		foreach($this->getConvertSettingCases() as $case){
			$assert($case['type'], $case['id'], $case['name']);
			$assert($case['type'], $nonExistentId, null);
			$assert($case['type'], null, null);
		};
	}

	function testConvertSettingValueForImport(){
		$nonExistentName = 'Whatever';
		$project = ExternalModules::getREDCapProjectObject(TEST_SETTING_PID);
		
		$assert = function($type, $input, $output) use ($project){
			$this->assertSame($output, $this->convertSettingValueForImport($project, $type, $input), 'Testing type: ' . $type);
		};

		$value = rand();
		$assert('whatever', $value, $value);

		foreach($this->getConvertSettingCases() as $case){
			$assert($case['type'], $case['name'], $case['id']);
			$assert($case['type'], $nonExistentName, null);
			$assert($case['type'], null, null);
		};
	}

	function testFilterHookResults(){
		$assert = function($expected, $results, $hook){
			$newResults = [];
			for($i=0; $i<count($results); $i++){
				$result = $results[$i];
				$newResults[] = [
					'prefix' => "prefix-$i",
					'result' => $result
				];
			}
			$results = $newResults;

			$this->assertSame($expected, ExternalModules::filterHookResults($results, $hook));
		};

		$assert(null, [], 'email'); // Return null when there are no results.
		
		// AND the boolean return values for the email hook.
		$assert(false, [false, false], 'email');
		$assert(false, [false, true], 'email');
		$assert(false, [false, true], 'email');
		$assert(true, [true, true], 'email');

		foreach(['pdf', 'custom_verify_username'] as $hook){
			$value = rand();
			$assert($value, [$value], $hook);
			
			ExternalModules::$lastHandleErrorResult = null;
			$assert(2, [1,2], $hook);
			$this->assertSame(self::tt("em_errors_38"), ExternalModules::$lastHandleErrorResult[0]);
			$this->assertSame('prefix-0, prefix-1', ExternalModules::$lastHandleErrorResult[2]);
		}
		
		$assert(null, [1,2,3], 'whatever'); // Return null for all other hooks (since they shouldn't return results).
	}

	function testEveryPageTopScript_partial(){
		$assert = function($username, $shouldIncludeAuthenticatedActions){
			ExternalModules::setUsername($username);

			$arguments = [0]; // avoid PHP warnings

			ob_start();
			require __DIR__ . '/../manager/templates/hooks/every_page_top.php';
			$output = ob_get_clean();

			$this->assertStringContainsString('window.ExternalModules.moduleDependentRequest', $output);
			$this->assertSame($shouldIncludeAuthenticatedActions, str_contains($output, "if ($('#project-menu-logo').length > 0) {"));
		};

		$assert(null, false);
		$assert(\System::SURVEY_RESPONDENT_USERID, false);
		$assert('some_user', true);
	}

	function testDetectParameter_sqlInjection(){
		$this->setProjectId('delete * from an_important_table');
		$this->assertEquals(0, ExternalModules::detectParameter('pid'));
	}

			

	/**
	 * It's important to ensure that when the configuration dialog is used
	 * project settings that override system settings are removed from the DB,
	 * allowing the system value to be used by default.  This ensures that
	 * projects do not always unintentionally override system values every
	 * time the project configuration dialog is saved.
	 */
	function testSetSettings_projectOverrideDeletes(){
		$assert = function($sameValue, $shouldDelete){
			$systemValue = rand();
			$projectValue = $systemValue;
			if(!$sameValue){
				$projectValue++;
			}

			$this->setSystemSetting($systemValue);

			// Make sure the delete does NOT occur when null pid is passed
			$this->callPrivateMethod('setSettings', TEST_MODULE_PREFIX, ExternalModules::SYSTEM_SETTING_PROJECT_ID, [TEST_SETTING_KEY => $systemValue]);

			$this->callPrivateMethod('setSettings', TEST_MODULE_PREFIX, TEST_SETTING_PID, [TEST_SETTING_KEY => $projectValue]);
			$this->assertSame($projectValue, $this->getProjectSetting());
			
			$this->removeSystemSetting();
			$expected = $shouldDelete ? null : $projectValue;
			$this->assertSame($expected, $this->getProjectSetting());
		};

		$assert(true, false);

		$this->setConfig([
			'system-settings' => [
				[
					'key' => TEST_SETTING_KEY,
					'allow-project-overrides' => true,
				]
			]
		]);

		$assert(true, true);
		$assert(false, false);

		/**
		 * The following ensures that we never modify setSetting() to remove all matching project overrides when
		 * system values are changed because it would make it too easy for someone to temporarily
		 * or accidentally change a value, then change it back, which would effectively blow away
		 * all project override values matching both the original AND temporary/accidental values.
		 */
		$value = rand();
		$this->setProjectSetting($value);
		$this->callPrivateMethod('setSettings', TEST_MODULE_PREFIX, ExternalModules::SYSTEM_SETTING_PROJECT_ID, [TEST_SETTING_KEY => $value]);
		$this->removeSystemSetting();
		$this->assertSame($value, $this->getProjectSetting());
	}

	function testSetSetting_multipleDBConnections()
	{
		$wrappedConnection = new class{
			public $prepareCount = 0;

			function __call($name, $args){
				if($name === 'prepare'){
					$this->prepareCount++;
				}

				return call_user_func_array([$GLOBALS['rc_connection'], $name], $args);
			}
		};

		$assert = function($expectedPrepareCount) use ($wrappedConnection){
			$this->assertSame($expectedPrepareCount, $wrappedConnection->prepareCount);
		};

		$this->query('select ?', 1);
		$assert(0);

		$GLOBALS['rc_replica_connection'] =  $wrappedConnection;
		$this->query('select ?', 1);
		$assert(1);

		$this->query('update redcap_ip_banned set ip = 1 where ip = 1', []);
		$assert(1); // Updates are always run on the primary

		/**
		 * Ensure all queries within the following call run (successfully) on the primary DB.
		 * An error would occur if any query attempted to attempted to use the (currently invalid) replica.
		 */
		$this->setProjectSetting(rand());
		$assert(1); // No queries within setSetting() should occur on the replica

		$this->assertFalse($this->getPrivateVariable('forceUsePrimaryDbConnection'));

		$this->query('select ?', 1);
		$assert(2);
		
		unset($GLOBALS['rc_replica_connection']);
		$this->query('select ?', 1);
		$assert(2);
	}

	function testSetSetting_emptyProjectId(){
		foreach([0, null, ''] as $pid){
			$value = rand();
			$this->callPrivateMethod('setSetting', TEST_MODULE_PREFIX, $pid, TEST_SETTING_KEY, $value);
			$this->assertSame($value, ExternalModules::getSystemSetting(TEST_MODULE_PREFIX, TEST_SETTING_KEY));
		}
	}

	function testComposerJsons(){
		foreach([
			'composer.json',
		] as $path){
			$composer = json_decode(file_get_contents(__DIR__ . "/../$path"));
			$this->assertSame(ExternalModules::getREDCapMinPHPVersion(), $composer->config->platform->php, "When REDCap's min PHP versions is updated, we should update our composer platform version to match.");
		}
	}

	function testGetPHPMinVersion(){
		// Before updating this test, ensure that any changes to getPHPMinVersion() will not break the vanderbilt_external_modules_submission module.

		$getPHPMinVersion = function($configVersion, $composerVersion){
			$config = [];
			if($configVersion !== null){
				$config['compatibility']['php-version-min'] = $configVersion;
			}

			$composer = [];
			if($composerVersion !== null){
				$composer['config']['platform']['php'] = $composerVersion;
			}

			return $this->getPHPMinVersion($config, $composer);
		};

		$assert = function($configVersion, $composerVersion, $expected) use ($getPHPMinVersion){
			$this->assertSame($expected, $getPHPMinVersion($configVersion, $composerVersion));
		};

		$assert('1.0', '1.1', '1.1');
		$assert('1.1', '1.0', '1.1');
		$assert('1.0', null, '1.0');
		$assert(null, '1.0', '1.0');
		$assert(null, null, null);

		$assertThrowsException = function($configVersion, $composerVersion) use ($getPHPMinVersion){
			$this->assertThrowsException(function() use ($configVersion, $composerVersion, $getPHPMinVersion){
				$getPHPMinVersion($configVersion, $composerVersion, 'whatever');
			}, 'is set to an invalid value');
		};

		$assertThrowsException('', '1.0');
		$assertThrowsException('1.0', '');
	}

	function testGitHubCIPHPVersions(){
		$getPHPVersions = function($path){
			$lines = explode("\n", file_get_contents($path));
			foreach($lines as $line){
				$line = trim($line);
				if(str_starts_with($line, 'php-versions')){
					return $line;
				}
			}

			throw new \Exception('PHP versions not found!');
		};

		$redcapCIVersions = $getPHPVersions(APP_PATH_DOCROOT . '/.github/workflows/main.yml');
		$emCIVersions = $getPHPVersions(__DIR__ . '/../.github/workflows/run-tests.yml');

		if($emCIVersions === "php-versions: ['8.0.2', '8.3']"){
			/**
			 * The Scan class doesn't support PHP 8.4 yet, so we must run tests on 8.3 to allow ScanTest to fully run without skipping tests.
			 * We don't want to remove this unit test though, as we want it to keep failing on future version changes.
			 * This hack should allow it to succeed only for this specific version discrepancy.
			 */
			$emCIVersions = str_replace('8.3', '8.4', $emCIVersions);
		}

		$this->assertSame(
			$redcapCIVersions,
			$emCIVersions,
			'The PHP versions tested in GitHub CI are expected to match REDCap core'
		);
	}

	function testIsSystemSetting(){
		$systemKeys = ['system1', 'system2'];
		$projectKey = 'project1';

		$this->setConfig([
			'system-settings' => [
				[
					'key' => $systemKeys[0]
				],
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => $systemKeys[1]]
					]
				]
			],
			'project-settings' => [
				[
					'key' => $projectKey
				]
			]
		]);

		foreach($systemKeys as $key){
			$this->assertTrue($this->isSystemSetting(TEST_MODULE_PREFIX, $key));
		}

		$this->assertFalse($this->isSystemSetting(TEST_MODULE_PREFIX, $projectKey));
	}

	function testGetFieldSQL(){
		$records = [
			[
				TEST_RECORD_ID => 1,
				TEST_TEXT_FIELD => rand(),
				TEST_TEXT_FIELD_2 => rand(),
			],
			[
				TEST_RECORD_ID => 2,
				TEST_TEXT_FIELD => rand(),
				TEST_TEXT_FIELD_2 => rand(),
			],
		];

		$this->saveData($records);

		$expectedRows = [];
		$fieldNames = [];
		foreach($records as $fields){
			$expectedRow = [];
			foreach($fields as $field => $value){
				$expectedRow[] = (string) $value;
				$fieldNames[$field] = true;
			}

			$expectedRows[] = $expectedRow;
		}

		$result = $this->query($this->getFieldSQL([
			'project_id' => TEST_SETTING_PID,
			'fields' => []
		]), []);
		$this->assertNull($result->fetch_assoc());

		$result = $this->query($this->getFieldSQL([
			'project_id' => TEST_SETTING_PID,
			'fields' => array_keys($fieldNames)
		]), []);

		$actualRows = [];
		while($row = $result->fetch_row()){
			$actualRows[] = $row;
		}

		$this->assertSame($expectedRows, $actualRows);
	}

	function testSanitizeFieldName(){
		$assert = function($input, $expected){
			$this->assertSame($expected, $this->sanitizeFieldName($input));
		};

		$assert('a_B_2', 'a_B_2');
		$assert(" <>&()'\"\t\n", '');
	}
		
	function testTimeZones(){
		$sqlTime = $this->query('select now()', [])->fetch_row()[0];
		$phpTime = $this->makeTimestamp();

		$sqlParts = explode(':', $sqlTime);
		$phpParts = explode(':', $phpTime);
		
		$sqlDateAndHour = $sqlParts[0];
		$phpDateAndHour = $phpParts[0];
		$sqlMinute = $sqlParts[1];
		$phpMinute = $phpParts[1];

		if($sqlMinute !== $phpMinute){
			/**
			 * Maybe we just caught it right on the transition from 59 to 00
			 * at the end of an hour.  Wait a few seconds, then try again.
			 */
			sleep(5);
			$this->testTimeZones();
		}
		else{
			/**
			 * This assertion ensures graceful failure on timezone issues,
			 * to avoid more cryptic errors from other tests.
			 */
			$this->assertSame($sqlDateAndHour, $phpDateAndHour, "Your system's PHP and SQL timezones are out of sync!  Please modify one (or both) so that they match.");
		}
	}

	function testGetEDocName(){
		$testEdoc = $this->findEdoc();
		
		$_POST['edoc'] = $testEdoc['doc_id'];
		ob_start();
		require __DIR__ . '/../manager/ajax/get-edoc-name.php';
		$response = json_decode(ob_get_clean());
		unset($_POST['edoc']);

		$this->assertSame($testEdoc['doc_name'], $response->doc_name);
		$this->assertSame('success', $response->status);
	}

	function testSystemSettingCache(){
		$this->setPrivateVariable('systemSettingCache', null); // Other tests may have initialized the cache

		// Use an array value to ensure that validateSettingsRow() is called
		$value1 = [
			rand(),
			rand()
		];
		ExternalModules::setSystemSetting(TEST_MODULE_PREFIX, ExternalModules::KEY_DISCOVERABLE, $value1);
		$this->assertSame($value1, ExternalModules::getSystemSettingCache()[TEST_MODULE_PREFIX][ExternalModules::KEY_DISCOVERABLE]);
		$this->assertSame($value1, ExternalModules::getSystemSetting(TEST_MODULE_PREFIX, ExternalModules::KEY_DISCOVERABLE));

		$value2 = rand();
		ExternalModules::setSystemSetting(TEST_MODULE_PREFIX, ExternalModules::KEY_DISCOVERABLE, $value2);
		$this->assertSame($value2, ExternalModules::getSystemSetting(TEST_MODULE_PREFIX, ExternalModules::KEY_DISCOVERABLE));

		// For now, the cache should still contain the old value
		$this->assertSame($value1, ExternalModules::getSystemSettingCache()[TEST_MODULE_PREFIX][ExternalModules::KEY_DISCOVERABLE]);
	}

	function testNormalizeConfigSections(){
		$config = [];
		$configPath = rand();

		$config = ExternalModules::normalizeConfigSections($config);

		$this->assertNotEmpty($config);
		foreach($config as $key=>$value){
			$this->assertIsArray($value);
			$this->assertEmpty($value);
		}

		$assert = function($expectedError) use (&$config, $configPath){
			try{
				ExternalModules::normalizeConfigSections($config, $configPath);
				$actual = null;
			}
			catch(\Throwable $t){
				$actual = $t->getMessage();
			}

			$this->assertSame($expectedError, $actual);
		};

		$assert(null);

		foreach([
			ExternalModules::MODULE_AUTH_AJAX_ACTIONS_SETTING,
			ExternalModules::MODULE_NO_AUTH_AJAX_ACTIONS_SETTING
		] as $section){
			$config[$section] = 'string instead of array';
			$assert(ExternalModules::tt("em_errors_168", $section, $configPath));
			
			$invalidAction = 1;
			$config[$section] = [$invalidAction];
			$assert(ExternalModules::tt("em_errors_166", $invalidAction, $section, $configPath));
	
			$config[$section] = [ExternalModules::MODULE_AJAX_LOGGING_ACTION];
			$assert(ExternalModules::tt("em_errors_167", ExternalModules::MODULE_AJAX_LOGGING_ACTION, $section, $configPath));
			$config[$section] = [];
		}
		
		$invalidAction = "!@#$";
		$config[ExternalModules::MODULE_API_ACTIONS_SETTING] = [
			$invalidAction => [
				"description" => "This has an invalid action name.",
				"access" => ["auth"]
			]
		];
		$assert(ExternalModules::tt("em_errors_188", $invalidAction, ExternalModules::MODULE_API_ACTIONS_SETTING, $configPath));

		$config[ExternalModules::MODULE_API_ACTIONS_SETTING] = [
			"some-action" => [
				// empty description
				"description" => "",
			]
		];
		$assert(ExternalModules::tt("em_errors_188", "some-action", ExternalModules::MODULE_API_ACTIONS_SETTING, $configPath));

		$config[ExternalModules::MODULE_API_ACTIONS_SETTING] = [
			"some-action" => [
				// missing description
			]
		];
		$assert(ExternalModules::tt("em_errors_188", "some-action", ExternalModules::MODULE_API_ACTIONS_SETTING, $configPath));

		$config[ExternalModules::MODULE_API_ACTIONS_SETTING] = [
			"some-action" => [
				"description" => "Blah",
				// ensure config is valid without 'access' being specified
			]
		];
		$assert(null);
	}

	function testGetPrefix(){
		$assert = function($expected){
			$this->assertSame($expected, ExternalModules::getPrefix());
		};

		$assert('');
		$this->setPrefix([]); // We've seen requests unexpectedly specify an array in PROD.  Ensure these fail.
		$assert('');

		$prefix = '' . rand();
		$this->setPrefix($prefix);
		$assert($prefix);
	}

	/**
	 * @group slow
	 */
	function testShowSlowTestMessage()
	{
		$this->expectNotToPerformAssertions();
		register_shutdown_function(function(){
			echo "\nConsider running `phpunit --exclude slow` for much faster feedback on most tests.\n\n";
		});
	}

	function testParseDirectoryNameResponse(){
		$moduleDirectoryName = 'some_module_v' . rand();
		$assert = function($response, $successExpected) use ($moduleDirectoryName){
			$module_id = rand();

			try{
				$actual = $this->callPrivateMethod('parseDirectoryNameResponse', $response, $module_id);
				$this->assertSame($moduleDirectoryName, $actual);
				$this->assertTrue($successExpected);
			}
			catch(Exception $e){
				$this->assertSame(
					$this->tt("em_errors_165", $module_id, $response),
					$e->getMessage()
				);
				$this->assertFalse($successExpected);
			}
		};

		$assert(json_encode(['module_directory_name' => $moduleDirectoryName]), true);
		$assert(json_encode(['some_other_json_response' => 'whatever']), false);
		$assert('Any other response, including failure pages from firewalls like in community post 230870', false);
	}

	function testHandleError(){
		$expectedSubject = "Subject " . rand();
		$inputMessage = "Message " . rand();
		$expectedPrefix  = "prefix-" . rand();
		$expectedUsername = "User-" . rand();
		
		ExternalModules::setUsername($expectedUsername);
		ExternalModules::handleError($expectedSubject, $inputMessage, $expectedPrefix);

		[$actualSubject, $actualMessage, $actualPrefix] = ExternalModules::$lastHandleErrorResult;

		$this->assertSame($actualPrefix, $expectedPrefix);
		$this->assertSame($actualSubject, $expectedSubject);
		$this->assertStringContainsString("\nServer: ", $actualMessage);
		$this->assertStringContainsString("\nUser: $expectedUsername\n", $actualMessage);
		$this->assertStringContainsString($inputMessage, $actualMessage);
	}

	function testExtractExcludingExtensions(){
		$zip = new ZipArchive;
		$zipPath = ExternalModules::createTempFile();
		unlink($zipPath); // Avoid ZipArchive::open() empty file warning
		$zip->open($zipPath, ZipArchive::CREATE);

		$files = [
			'some-file.json' => true,
			'some-file.php' => false,
			'some-dir/some-file.csv' => true,
			'some-dir/some-file.PHP' => false,
		];

		foreach($files as $file=>$shouldBeExtracted){
			$zip->addFromString($file, 'asdf');
		}

		// Close & reopen so that added files can be read
		$zip->close();
		$zip->open($zipPath);

		$tempDir = ExternalModules::createTempDir();
		ExternalModules::extractExcludingExtensions($zip, ['php'], $tempDir);

		foreach($files as $file=>$shouldBeExtracted){
			$this->assertSame($shouldBeExtracted, file_exists("$tempDir/$file"), "In regard to: $file");
		}
	}

	function testHandleApiRequest_partial(){
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$data = [];
		$request_data = [
			'action' => null,
			'prefix' => null,
			'format' => null,
			'returnFormat' => null,
			'csvDelim' => null,
		];

		$assert = function($expectedExceptionExcerpt) use (&$data, &$request_data){
			$output = $this->captureOutput(function() use (&$data, &$request_data){
				ExternalModules::handleApiRequest($data, $request_data);
			});

			$this->assertStringContainsString($expectedExceptionExcerpt, $output);
		};

		$assert('The API request failed: You must specify a prefix!');
		$request_data['prefix'] = 'some_prefix_that_does_not_exist';
		$assert("The module with the prefix 'some_prefix_that_does_not_exist' is not enabled on this REDCap instance.");
		$request_data['prefix'] = TEST_MODULE_PREFIX;
		$assert('Invalid action name');
	}
}
