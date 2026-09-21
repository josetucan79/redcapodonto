<?php
namespace ExternalModules;

use DateTime;
use Exception;
use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;
use REDCap;
use TestModule\TestModule;

/**
 * This test runs against the latest framework version according to ExternalModules::getMaxSupportedFrameworkVersion().
 */
class FrameworkTest extends BaseTest
{
	static $frameworkVersion;

	static function setUpBeforeClass(): void{
		parent::setUpBeforeClass();

		$class = get_called_class();
		if($class === 'ExternalModules\FrameworkTest'){
			$frameworkVersion = ExternalModules::getMaxSupportedFrameworkVersion();
		}
		else{
			preg_match('/[0-9]+/', $class, $matches);
			$frameworkVersion = (int) $matches[0];
		}
		
		static::$frameworkVersion = $frameworkVersion;
	}

	function getFrameworkVersion(){
		return static::$frameworkVersion;
	}

	protected function setFrameworkVersion($frameworkVersion){
		static::$frameworkVersion = $frameworkVersion;
		parent::setFrameworkVersion($frameworkVersion);
	}

    protected function getReflectionClass(){
        if($this->getFrameworkVersion() >= 5){
            /**
             * In v5 all framework methods are automatically accessible via the module object,
             * making it a safe and more effective test to run against the module instance instead of the framework instance
             */
            return $this->getInstance();
        } else {
            return $this->getFramework();
        }
	}

	function assertGetSubSettings_complexNesting2($newBehavior){
		$m = $this->getInstance();
		$this->setProjectId(TEST_SETTING_PID);

		// Taken from the API Sync module
		$this->setConfig([
			'project-settings' => json_decode('
			[
				{
					"key": "export-servers",
					"name": "Export Destinations",
					"type": "sub_settings",
					"repeatable" : true,
					"sub_settings" : [
						{
							"key": "export-projects",
							"name": "Projects",
							"type": "sub_settings",
							"repeatable" : true,
							"sub_settings" : [
								{
									"key": "export-field-list",
									"name": "Field List",
									"type": "field-list",
									"repeatable": true
								}
							]
						}
					]
				}
			]', true)
		]);

		// These values were copied directly from the database after saving them through the settings dialog (as configured by the json file above).
		$fieldNames = ["one","two"];
		$m->setProjectSetting('export-field-list', [[$fieldNames]]);

		$subSettings = $this->getSubSettings('export-servers')[0]['export-projects'];

		if($newBehavior){
			$this->assertSame($fieldNames, $subSettings[0]['export-field-list']);
		}
		else{
			// This is logically wrong, but some modules might depend on it, so make sure we're fully backward compatible.
			$this->assertSame($fieldNames, $subSettings['export-field-list'][0]);
		}
	}

	function assertIndex($expectedExcerpt = null){
        $csrfToken = $_POST['redcap_external_module_csrf_token'] ?? null;

        $m = $this->getInstance();

        $require = function() use ($m){
            return $this->captureOutput(function() use ($m){
                // Simulate this value being set in API/index.php or external_modules/redcap_connect.php,
                // even if it's null.
                $_POST['redcap_external_module_csrf_token'] = $_POST['redcap_csrf_token'] ?? null;

                // Unset the original token like REDCap does
                unset($_POST['redcap_csrf_token']);

                require __DIR__ . '/../index.php';
                if($module !== $m){
                    throw new Exception('The module instance was not defined as expected.');
                }
            });
        };

        if($expectedExcerpt){
            $this->assertThrowsException($require, $expectedExcerpt);
        }
        else{
            $expectedOutput = (string) rand();
            if(!isset($_GET['NOAUTH'])){
                $expectedOutput = $this->captureOutput(function() use ($expectedOutput){
                    // Required to prevent warnings in REDCap core.
                    global $lang;
                    global $auth_meth_global;
                    global $missingDataCodes;
                    global $sendgrid_enabled;
                    $sendgrid_enabled = null;

                    $missingDataCodes = [];

                    // The following cannot be used currently because makePanelTitle() is defined inside ProjectGeneral/header.php,
                    // preventing it from being required multiple times.
                    // $pid = $this->getProjectId();
                    // if(isset($pid)){
                    // 	// Required to prevent warnings in REDCap core.
                    // 	global $project_id;
                    // 	global $dataEntryCollapsed;
                    // 	global $user_lastname;
                    // 	global $user_firstname;
                    // 	global $redcap_version;
                    // 	global $app_title;
                    // 	global $project_contact_email;
                    // 	global $mobile_app_enabled;
                    // 	global $api_enabled;
                    // 	global $data_resolution_enabled;
                    // 	global $user_rights;
                    // 	global $randomization;
                    // 	global $date_deleted;
                    // 	global $completed_time;
                    // 	global $longitudinal;
                    // 	global $status;
                    // 	global $Proj;
                    // 	global $auth_meth;
                    // 	global $userid;
                    // 	global $user_messaging_enabled;
                    // 	global $sendgrid_enabled;
                    // 	global $surveys_enabled;
                    // 	global $repeatforms;
                    // 	global $fhir_data_mart_create_project;
                    // 	global $record_locking_pdf_vault_enabled;

                    // 	// $user_rights = \UserRights::getPrivileges($pid, ExternalModules::getUserRights($pid));
                    // 	// var_dump($user_rights);die();
                    // 	$project_id = $pid;
                    // 	$user_rights = $this->getProject()->addMissingUserRightsKeys([]);

                    // 	$Proj = ExternalModules::getREDCapProjectObject($pid);

                    // 	$headerAndFooterFolder = 'ProjectGeneral';
                    // }
                    // else{
                        $headerAndFooterFolder = 'ControlCenter';
                    // }

                    require APP_PATH_DOCROOT . "/$headerAndFooterFolder/header.php";
                    echo $expectedOutput;
                    require APP_PATH_DOCROOT . "/$headerAndFooterFolder/footer.php";
                });
            }

            $m->pageLoadOutput = $expectedOutput;
            $actualOutput = $require();
            $this->assertSame($expectedOutput, $actualOutput);

			// Make sure the token is unset.
			$this->assertFalse(array_key_exists('redcap_external_module_csrf_token', $_POST));
        }

        // Put the token back the way it was beforehand.
		$_POST['redcap_external_module_csrf_token'] = $csrfToken;
    }

	function testImportDataDictionary(){
		// BaseTest::setUp() also effectively tests importDataDictionary()

		$pid = TEST_SETTING_PID;
		$fieldName = TEST_TEXT_FIELD;

		$assert = function($expected) use ($pid, $fieldName){
			/**
			 * This asserts that both the database AND Project cache have been update.
			 */
			$this->assertSame($expected, (new \Project($pid))->metadata[$fieldName]['element_label']);
		};

		$assert('Test Text Field');

		$fakeValue = 'fake element label ' . rand();
		$this->query('update redcap_metadata set element_label = ? where project_id = ? and field_name = ?', [$fakeValue, $pid, $fieldName]);
		ExternalModules::clearProjectCache($pid);
		$assert($fakeValue);

		$this->importDataDictionary($pid, __DIR__ . '/test-project-data-dictionary.csv');
		$assert('Test Text Field');
	}

	function testImportDataDictionary_errors(){
		$this->assertThrowsException(function(){
			$this->importDataDictionary(TEST_SETTING_PID, 'some-bad-path');
		}, 'File not found for data dictionary import');

		$this->assertThrowsException(function(){
			$path = $this->createTempFile();
			$this->importDataDictionary(TEST_SETTING_PID, $path);
		}, 'The metadata specified is not valid');

		$this->assertThrowsException(function(){
			$this->importDataDictionary(null, __DIR__ . '/test-project-data-dictionary.csv');
		}, ExternalModules::tt('em_errors_131'));

		$this->assertThrowsException(function(){
			$path = $this->createTempFile();
			$metadata = file_get_contents(__DIR__ . '/test-project-data-dictionary.csv');
			$metadata = str_replace('test_record_id', '', $metadata);
			file_put_contents($path, $metadata);
			$projectIdThatShouldNotExist = PHP_INT_MAX;
			$this->importDataDictionary($projectIdThatShouldNotExist, $path);
		}, 'Each row MUST have a variable/field name');
	}

	/**
	 * @doesNotPerformAssertions
	 */
	function testCheckSettings_emptyConfig()
	{
		self::assertConfigValid([]);
	}

    function testCheckSettings_duplicateKeys()
    {
    	$assertMultipleSettingException = function($config){
			self::assertConfigInvalid($config, 'setting multiple times!');
		};

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key']
			],
			'project-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
			'project-settings' => [
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				['key' => 'some-key'],
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				],
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'system-settings' => [
				[
					'key' => 'some-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				['key' => 'some-key'],
				['key' => 'some-key'],
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				['key' => 'some-key'],
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				],
				['key' => 'some-key']
			],
		]);

		$assertMultipleSettingException([
			'project-settings' => [
				[
					'key' => 'some-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						['key' => 'some-key']
					]
				]
			],
		]);

		// Assert a double nested sub_settings
		$assertMultipleSettingException([
			'project-settings' => [
				[
					'key' => 'some-key',
					'type' => 'sub_settings',
					'sub_settings' => [
						[
							'key' => 'some-other-key',
							'type' => 'sub_settings',
							'sub_settings' => [
								[
									'key' => 'some-other-key'
								]
							]
						]
					]
				]
			],
		]);
    }

	function testCheckSettings_duplicateReservedSettings()
	{
		$assertReservedException = function($config, $pid = null){
			$config = ExternalModules::embellishConfig(TEST_MODULE_PREFIX, $config);
			$this->assertConfigInvalid($config, 'reserved for internal use');
		};

		$assertReservedException([
			'system-settings' => [
				['key' => ExternalModules::KEY_VERSION]
			]
		]);

		$assertReservedException([
			'project-settings' => [
				[
					'type' => 'sub_settings',
					'sub_settings' => [
						[
							'key' => ExternalModules::KEY_ENABLED
						]
					]
				]
			]
		]);
	}

	function testGetSettings()
	{
		$_GET['moduleDirectoryPrefix'] = TEST_MODULE_PREFIX;
		$nameHtml = "<b>Include some html tags to make sure they don't get escaped (Mark's has accidentally made changes that do that twice now)</b>";

		$assert = function($hiddenSettingsRemoved, $config) use ($nameHtml){
			$assert = function($hiddenSettingsRemoved, $config){
				$hiddenSettings = array_column($config['project-settings'], 'hidden');
				$this->assertSame($hiddenSettingsRemoved, empty($hiddenSettings));
			};
			
			$assert($hiddenSettingsRemoved, ExternalModules::embellishConfig(TEST_MODULE_PREFIX, $config));
			
			$this->setConfig($config);
			ob_start();
			require __DIR__ . '/../manager/ajax/get-settings.php';
			$rawResponse = ob_get_clean();
			$response = json_decode($rawResponse, true);
			$config = $response['config'];

			$assert(true, $config);
			$this->assertSame($nameHtml, $config['project-settings'][0]['name']);
		};

		$config = [
			'framework-version' => ExternalModules::getMaxSupportedFrameworkVersion(),
			'project-settings' => [
				[
					'name' => $nameHtml
				],
				[
					'hidden' => true
				]
			]
		];

		$assert(false, $config);
		$config['framework-version'] = Framework::HIDDEN_SETTING_FIX_FRAMEWORK_VERSION-1;
		$assert(true, $config);
	}

	/**
	 * @doesNotPerformAssertions
	 */
	function testCheckSettingKey_valid()
	{
		self::assertConfigValid([
			'system-settings' => [
				['key' => 'key1']
			],
			'project-settings' => [
				['key' => 'key-two']
			],
		]);
	}

	function testCheckSettingKey_invalidChars()
	{
		$this->assertConfigInvalid([
			'system-settings' => [
				['key' => 'A']
			]
		], ExternalModules::tt("em_errors_62", TEST_MODULE_PREFIX, 'A'));

		$this->assertConfigInvalid([
			'project-settings' => [
				['key' => '!']
			]
		], ExternalModules::tt("em_errors_62", TEST_MODULE_PREFIX, '!'));
	}

	function testIsSettingKeyValid()
	{
		$isSettingKeyValid = function($key){
			return $this->callPrivateMethodForClass($this->getFramework(), 'isSettingKeyValid', $key);
		};

		$this->assertTrue($isSettingKeyValid('a'));
		$this->assertTrue($isSettingKeyValid('2'));
		$this->assertTrue($isSettingKeyValid('-'));
		$this->assertTrue($isSettingKeyValid('_'));

		$this->assertFalse($isSettingKeyValid('A'));
		$this->assertFalse($isSettingKeyValid('!'));
		$this->assertFalse($isSettingKeyValid('"'));
		$this->assertFalse($isSettingKeyValid('\''));
		$this->assertFalse($isSettingKeyValid(' '));
	}

	private function assertConfigValid($config)
	{
		$this->setConfig($config);

		// Attempt to make a new instance of the module (which throws an exception on any config issues).
		new TestModule(TEST_MODULE_PREFIX);
	}

	private function assertConfigInvalid($config, $exceptionExcerpt)
	{
		$this->assertThrowsException(function() use ($config){
			self::assertConfigValid($config);
		}, $exceptionExcerpt);
	}

	function testQuery_trueReturnForDatalessQueries(){
		$r = $this->query('update redcap_ip_banned set time_of_ban=now() where ?=?', [1,2]);
        $this->assertTrue($r);
	}

	function testQuery_invalidQuery(){
		$this->assertThrowsException(function(){
			ob_start();
			$this->query("select * from some_table_that_does_not_exist", []);
		}, ExternalModules::tt("em_errors_29"));

		ob_end_clean();
	}

	function testQuery_paramTypes(){
		$dateTimeString = '2001-02-03 04:05:06';

		$values = [
			true,
			2,
			3.3,
			'four',
			null,
			new DateTime($dateTimeString)
		];

		$row = $this->query('select ?, ?, ?, ?, ?, ?', $values)->fetch_row();

		$values[0] = 1; // The boolean 'true' will get converted to the integer '1'.  This is excepted.
		$values[5] = $dateTimeString;

		$this->assertSame($values, $row);
	}

	function testQuery_invalidParamType(){
		$this->assertThrowsException(function(){
			ob_start();
			$invalidParam = new \stdClass();
			$this->query("select ?", [$invalidParam]);
		}, ExternalModules::tt('em_errors_109'));

		ob_end_clean();
	}
	
	function testQuery_singleParams(){
		$values = [
			rand(),
			
			// Check falsy values
			0,
			'0',
			''
		];

		foreach($values as $value){
			$row = $this->query('select ?', $value)->fetch_row();
			$this->assertSame($value, $row[0]);
		}
	}

	function testQuery_queryObject(){
		$value = rand();

		$query = ExternalModules::createQuery();
		$query->add('select ?', $value);

		$result = ExternalModules::query($query);

		$this->assertSame($value, $result->fetch_row()[0]);
	}

	function testGetSubSettings_plainOldRepeatableInsideSubSettings(){
		$m = $this->getInstance();
		$this->setProjectId(TEST_SETTING_PID);

		$this->setConfig('
			{
				"project-settings": [
					{
						"key": "one",
						"name": "one",
						"type": "sub_settings",
						"repeatable": true,
						"sub_settings": [
							{
								"key": "two",
								"name": "two",
								"type": "text",
								"repeatable": true
							}
						]
					}
				]
			}
		');

		$m->setProjectSetting('one', ["true"]);
		$m->setProjectSetting('two', [["value"]]);

		$this->assertEquals(
			[
				[
					'two' => [
						'value'
					]
				]
			],
			$m->getSubSettings('one')
		);
	}

	function testGetSubSettings_systemSettings(){
		$m = $this->getInstance();

		$this->setConfig('
			{
				"system-settings": [
					{
						"key": "one",
						"name": "one",
						"type": "sub_settings",
						"repeatable": true,
						"sub_settings": [
							{
								"key": "two",
								"name": "two",
								"type": "text",
								"repeatable": true
							}
						]
					}
				]
			}
		');

		$m->setSystemSetting('one', ["true"]);
		$m->setSystemSetting('two', [["value"]]);

		$this->assertEquals(
			[
				[
					'two' => [
						'value'
					]
				]
			],
			$m->getSubSettings('one')
		);

		$this->setFrameworkVersion(Framework::SYSTEM_SUB_SETTINGS-1);
		$this->assertThrowsException(function() use ($m){
			$m->getSubSettings('foo');
		}, ExternalModules::tt('em_errors_65', 'pid'));
	}

	function testGetProjectsWithModuleEnabled(){
		$m = $this->getInstance();

		$this->assertSame([], $this->getProjectsWithModuleEnabled());

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, true, TEST_SETTING_PID);
		$this->assertSame([TEST_SETTING_PID], $this->getProjectsWithModuleEnabled());

		$m->setProjectSetting(ExternalModules::KEY_ENABLED, false, TEST_SETTING_PID);
		$this->assertSame([], $this->getProjectsWithModuleEnabled());

		$m->setSystemSetting(ExternalModules::KEY_ENABLED, true);
		$pids = $this->getProjectsWithModuleEnabled();
		$this->assertNotContains(TEST_SETTING_PID, $pids);
		$this->assertContains(TEST_SETTING_PID_2, $pids);
		$this->assertContains(TEST_SETTING_PID_3, $pids);

		$m->removeProjectSetting(ExternalModules::KEY_ENABLED, TEST_SETTING_PID);
		$pids = $this->getProjectsWithModuleEnabled();
		$this->assertContains(TEST_SETTING_PID, $pids);
		$this->assertContains(TEST_SETTING_PID_2, $pids);
		$this->assertContains(TEST_SETTING_PID_3, $pids);

		$this->setFrameworkVersion(Framework::INCLUDE_BY_DEFAULT_IN_PROJECTS_WITH_MODULE_ENABLED_FRAMEWORK_VERSION-1);
		$this->assertSame([], $this->getProjectsWithModuleEnabled());
	}

	function testProject_getLogTableName(){
		$result = $this->query('select log_event_table from redcap_projects where project_id = ?', TEST_SETTING_PID);
		$expected = $result->fetch_assoc()['log_event_table'];
		$actual = $this->getProject(TEST_SETTING_PID)->getLogTable();
		$this->assertSame($expected, $actual);
	}

	function testProject_getProjectId(){
		$this->assertSame((int)TEST_SETTING_PID, $this->getProject(TEST_SETTING_PID)->getProjectId());
	}

	private function assertAddOrUpdateInstances($instanceData, $expected, $keyFields, $message = null){
		$this->setProjectId(TEST_SETTING_PID);
		
		// Run the assertion twice, to make sure subsequent calls with the same data have no effect.
		for($i=0; $i<2; $i++){
			$addOrUpdateResult = $this->addOrUpdateInstances($instanceData, $keyFields);
			$this->assertTrue(isset($addOrUpdateResult['item_count']), 'Make sure the underlying saveData() result is returned');

			$fields = [$this->getRecordIdField(), TEST_REPEATING_FIELD_1, TEST_REPEATING_FIELD_2, TEST_REPEATING_FIELD_3];
			$results = json_decode(\REDCap::getData($this->getFramework()->getProjectId(), 'json', null, $fields), true);

			$actual = [];
			foreach($results as $result){
				if($result['redcap_repeat_instance'] === ''){
					continue;
				}

				$actual[] = $result;
			}
			
			$this->assertSame($expected, $actual, $message);
		}
	}

	function testProject_addOrUpdateInstances(){
		$nextRecordId = rand();
		$uniqueFieldValue = rand();
		$expected = [];
		
		$createInstanceData = function($recordId, $instanceNumber) use(&$uniqueFieldValue, &$expected){
			$instanceExpected = [
				$this->getRecordIdField(TEST_SETTING_PID) => (string) $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => $instanceNumber,
				TEST_REPEATING_FIELD_1 => (string) ($uniqueFieldValue++),
				TEST_REPEATING_FIELD_2 => (string) rand(),
				TEST_REPEATING_FIELD_3 => ''
			];

			$expected[] = $instanceExpected;
			$instanceData = $instanceExpected;
			
			// Unset these so that the test verifies that they gets added appropriately.
			unset($instanceData['redcap_repeat_instrument']);
			unset($instanceData['redcap_repeat_instance']);

			return $instanceData;
		};

		$assert = function($instanceData, $message) use (&$expected){	
			$this->assertAddOrUpdateInstances($instanceData, $expected, TEST_REPEATING_FIELD_1, $message);
		};

		$recordId1 = $nextRecordId++;
		$instanceData1 = $createInstanceData($recordId1, 1);
		$assert([$instanceData1], 'Add one instance');

		$this->assertThrowsException(function() use ($assert, $instanceData1){
			$assert([$instanceData1, $instanceData1], 'An exception should be thrown before this assertion message is ever reached');
		}, ExternalModules::tt('em_errors_138'));

		$instanceData2 = $createInstanceData($recordId1, 2);
		$instanceData3 = $createInstanceData($recordId1, 3);
		$instanceData3['redcap_repeat_instrument'] = TEST_REPEATING_FORM; // Also ensure that manually specifying the form makes no difference
		$assert([$instanceData2, $instanceData3], 'Add two more instances for the same record');
		
		$updatedValue1 = (string) rand();
		$instanceData1[TEST_REPEATING_FIELD_2] = $updatedValue1;
		$expected[count($expected)-3][TEST_REPEATING_FIELD_2] = $updatedValue1;
		$updatedValue2 = (string) rand();
		$instanceData2[TEST_REPEATING_FIELD_2] = $updatedValue2;
		$expected[count($expected)-2][TEST_REPEATING_FIELD_2] = $updatedValue2;
		$assert([$instanceData1, $instanceData2], 'Updating a couple of instances');

		$instanceData4 = $createInstanceData($recordId1, 4);
		$recordId2 = $nextRecordId++;
		$record2InstanceData1 = $createInstanceData($recordId2, 1);
		$assert([$instanceData4, $record2InstanceData1], 'Adding instances for multiple records');

		$record2UpdatedValue = (string) rand();
		$record2InstanceData1[TEST_REPEATING_FIELD_2] = $record2UpdatedValue;
		$expected[count($expected)-1][TEST_REPEATING_FIELD_2] = $record2UpdatedValue;
		$assert([$record2InstanceData1], 'Updating an instance for another record');

		$duplicateInstance = $expected[count($expected)-1];
		$duplicateInstance['redcap_repeat_instance']++;
		REDCap::saveData($this->getFramework()->getProjectId(), 'json', json_encode([$duplicateInstance]));
		$this->assertThrowsException(function() use($assert, $duplicateInstance){
			$assert([$duplicateInstance], 'An exception should be thrown before this assertion message is ever reached');
		}, ExternalModules::tt('em_errors_135', TEST_REPEATING_FORM));
	}

	function testProject_addOrUpdateInstances_multipleKeys(){
		$firstInstance = [
			TEST_RECORD_ID => (string) rand(),
			'redcap_repeat_instrument' => TEST_REPEATING_FORM,
			'redcap_repeat_instance' => 1,
			TEST_REPEATING_FIELD_1 => (string) rand(),
			TEST_REPEATING_FIELD_2 => (string) rand(),
			TEST_REPEATING_FIELD_3 => (string) rand(),
		];

		$expectedResult = [
			$firstInstance
		];

		$assert = function($instances, $message) use (&$expectedResult){
			$this->assertAddOrUpdateInstances($instances, $expectedResult, [
				TEST_REPEATING_FIELD_1,
				TEST_REPEATING_FIELD_2
			], $message);
		};

		$assert($expectedResult, 'initial save');

		$firstInstance[TEST_REPEATING_FIELD_3] = (string) rand();
		$expectedResult[0] = $firstInstance;
		$assert([$firstInstance], 'update non-key value on existing instance');

		$secondInstance = $firstInstance;
		$secondInstance['redcap_repeat_instance'] = 2;
		$secondInstance[TEST_REPEATING_FIELD_1] = (string) rand();
		$secondInstance[TEST_REPEATING_FIELD_3] = (string) rand();
		$expectedResult[] = $secondInstance;
		$assert([$secondInstance], 'updating the first of two keys causes a new instance');

		$thirdInstance = $secondInstance;
		$thirdInstance['redcap_repeat_instance'] = 3;
		$thirdInstance[TEST_REPEATING_FIELD_2] = (string) rand();
		$thirdInstance[TEST_REPEATING_FIELD_3] = (string) rand();
		$expectedResult[] = $thirdInstance;
		$assert([$thirdInstance], 'updating the second of two keys causes a new instance');

		$record2Instance1 = $firstInstance;
		$record2Instance1[TEST_RECORD_ID] = (string) ($record2Instance1[TEST_RECORD_ID] + 1); // Add one so it appears next in the result list.
		$record2Instance1[TEST_REPEATING_FIELD_3] = (string) rand();
		$expectedResult[] = $record2Instance1;
		$assert([$firstInstance, $record2Instance1], 'using the same key fields on a different records results in separate instances for each record');
	}

	function testProject_addOrUpdateInstances_falsyValues(){
		$recordId = (string) rand();

		$expected = [
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 1,
				TEST_REPEATING_FIELD_1 => '0',
				TEST_REPEATING_FIELD_2 => (string) rand(),
				TEST_REPEATING_FIELD_3 => (string) rand(),
			],
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 2,
				TEST_REPEATING_FIELD_1 => '',
				TEST_REPEATING_FIELD_2 => (string) rand(),
				TEST_REPEATING_FIELD_3 => (string) rand(),
			]
		];

		$this->assertAddOrUpdateInstances($expected, $expected, [TEST_REPEATING_FIELD_1], "Make sure zero and empty string are considered separate values");
	}

	function testProject_addOrUpdateInstances_numericTypeComparison(){
		$instance = [
			TEST_RECORD_ID => rand(),
			'redcap_repeat_instrument' => TEST_REPEATING_FORM,
			'redcap_repeat_instance' => 1,
			TEST_REPEATING_FIELD_1 => 0,
			TEST_REPEATING_FIELD_2 => '',
			TEST_REPEATING_FIELD_3 => '',
		];

		$expected = $instance;
		$expected[TEST_RECORD_ID] = (string) $expected[TEST_RECORD_ID];
		$expected[TEST_REPEATING_FIELD_1] = (string) $expected[TEST_REPEATING_FIELD_1];
		
		unset($instance['redcap_repeat_instance']);

		$this->assertAddOrUpdateInstances(
			[$instance],
			[$expected], 
			[TEST_REPEATING_FIELD_1], 
			'Ensure that passing in integers instead of strings does not result in duplicate instances (relies on the duplicate call loop in assertAddOrUpdateInstances())'
		);

		$this->assertThrowsException(function(){
			$this->addOrUpdateInstances(
				[
					[
						TEST_RECORD_ID => TEST_RECORD_ID,
						'redcap_repeat_instrument' => TEST_REPEATING_FORM,
						TEST_REPEATING_FIELD_1 => '0',
						TEST_REPEATING_FIELD_2 => '',
						TEST_REPEATING_FIELD_3 => '',
					],
					[
						TEST_RECORD_ID => TEST_RECORD_ID,
						'redcap_repeat_instrument' => TEST_REPEATING_FORM,
						TEST_REPEATING_FIELD_1 => 0,
						TEST_REPEATING_FIELD_2 => '',
						TEST_REPEATING_FIELD_3 => '',
					],
				],
				[TEST_REPEATING_FIELD_1]
			);
		}, ExternalModules::tt('em_errors_138'), 'Make sure duplicate keys that vary in type are caught when passed in at the same time');
	}

	function testProject_addOrUpdateInstances_exceptions(){
		$this->setProjectId(TEST_SETTING_PID);
		$recordIdFieldName = $this->getRecordIdField();

		$assertException = function($instances, $message){
			$this->assertThrowsException(function() use ($instances){
				$this->addOrUpdateInstances($instances, TEST_REPEATING_FIELD_1);
			}, $message);
		};

		$assertException([
			[
				TEST_REPEATING_FIELD_1 => 1
			],
		], ExternalModules::tt('em_errors_134', TEST_RECORD_ID));

		$assertException([
			[
				$recordIdFieldName => 1
			],
		], ExternalModules::tt('em_errors_134', TEST_REPEATING_FIELD_1));

		$assertException([
			[
				'redcap_repeat_instrument' => 'one',
			]
		], ExternalModules::tt('em_errors_137', TEST_REPEATING_FORM, 'one'));

		$fakeFieldName = 'some_nonexistent_field';
		$results = $this->addOrUpdateInstances([
			[
				$recordIdFieldName => 'one',
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				TEST_REPEATING_FIELD_1 => 1,
				$fakeFieldName => 1
			],
		], TEST_REPEATING_FIELD_1);
		$this->assertStringContainsString("not found in the project as real data fields: $fakeFieldName", $results['errors']);

		$assertException([1,2,3], ExternalModules::tt('em_errors_136'));

		$this->assertThrowsException(function(){
			$this->addOrUpdateInstances([[]], []);
		}, ExternalModules::tt('em_errors_132'));

		$this->assertThrowsException(function(){
			$this->addOrUpdateInstances([[]], [TEST_REPEATING_FIELD_1, TEST_TEXT_FIELD]);
		}, ExternalModules::tt('em_errors_133'));

		$this->assertThrowsException(function() use ($fakeFieldName){
			$this->addOrUpdateInstances([[]], [$fakeFieldName]);
		}, ExternalModules::tt('em_errors_139', $fakeFieldName));

		$setValidation = function($project, $field, $validation){
			$this->query("
				update redcap_metadata
				set element_validation_type = ?
				where project_id = ?
				and field_name = ?
			", [$validation, $project, $field]);
		};

		$setValidation(TEST_SETTING_PID, TEST_REPEATING_FIELD_1, 'float');
		$result = $this->addOrUpdateInstances([[
			$recordIdFieldName => 'one',
			'redcap_repeat_instrument' => TEST_REPEATING_FORM,
			TEST_REPEATING_FIELD_1 => 'some non-numeric value',
		]], [TEST_REPEATING_FIELD_1]);
		
		$this->assertSame(80, strpos($result['errors'][0], 'could not be validated'));
		$setValidation(TEST_SETTING_PID, TEST_REPEATING_FIELD_1, null);
	}

	function testProject_addUser(){
		$username = $this->getRandomUsername();
		$project = $this->getProject(TEST_SETTING_PID);

		$project->removeUser($username);
		$project->addUser($username);
		$this->assertSame('0', $project->getRights($username)['design']);

		$project->removeUser($username);		
		$project->addUser($username, ['design' => 1]);
		$this->assertSame('1', $project->getRights($username)['design']);

		$project->removeUser($username);
	}

	function testProject_removeUser(){
		$username = $this->getRandomUsername();
		$project = $this->getProject(TEST_SETTING_PID);

		$project->addUser($username);
		$project->removeUser($username);
		$this->assertNull($project->getRights($username));
	}

	function testProject_getRights(){
		$username = $this->getRandomUsername();
		$project = $this->getProject(TEST_SETTING_PID);

		$project->removeUser($username);

		$value = (string) rand(0, 1);
		$project->addUser($username, ['design' => $value]);

		$this->assertSame($value, $project->getRights($username)['design']);

		$project->removeUser($username);
	}

	function testProject_setRights(){
		$username = $this->getRandomUsername();
		$project = $this->getProject(TEST_SETTING_PID);

		$project->removeUser($username);
		$project->addUser($username);
		$this->assertSame('0', $project->getRights($username)['design']);

		$project->setRights($username, ['design' => 1]);
		$this->assertSame('1', $project->getRights($username)['design']);

		$project->removeUser($username);		
	}

	function testField_getType(){
		$project = $this->getProject(TEST_SETTING_PID);

		$this->assertSame('text', $project->getField(TEST_TEXT_FIELD)->getType());

		$fieldName = 'some_field_that_does_not_exist';
		$this->assertThrowsException(function() use ($project, $fieldName){
			$project->getField($fieldName)->getType();
		}, ExternalModules::tt('em_errors_144', $fieldName, TEST_SETTING_PID));
	}

	function testProject_getRepeatingForms(){
		$secondEventId = $this->getEventIds(TEST_SETTING_PID_2)[1];
		$this->assertSame(
			[null],
			$this->getProject(TEST_SETTING_PID_2)->getRepeatingForms($secondEventId)
		);

		$this->assertSame(
			[TEST_REPEATING_FORM, TEST_REPEATING_FORM_2],
			$this->getProject(TEST_SETTING_PID)->getRepeatingForms()
		);

		$this->setProjectId(TEST_SETTING_PID);
		$this->assertSame(
			[TEST_REPEATING_FORM, TEST_REPEATING_FORM_2],
			$this->getFramework()->getRepeatingForms()
		);
	}

	function testGetFieldNames(){
		$this->setProjectId(TEST_SETTING_PID);
		$this->assertSame([TEST_REPEATING_FORM_2_FIELD_1], $this->getFieldNames(TEST_REPEATING_FORM_2));
	}

	function testForm_getFieldNames(){
		$actual = $this->getProject(TEST_SETTING_PID)->getForm(TEST_REPEATING_FORM_2)->getFieldNames();
		$this->assertSame([TEST_REPEATING_FORM_2_FIELD_1], $actual);
	}

	function testRecords_lock(){
		$this->setProjectId(TEST_SETTING_PID);
		$recordIds = [1, 2];
		$records = $this->getFramework()->records;
		
		foreach($recordIds as $recordId){
			$this->ensureRecordExists($recordId);
		}

		$records->lock($recordIds);
		foreach($recordIds as $recordId){
			$this->assertTrue($records->isLocked($recordId));
		}

		$records->unlock($recordIds);
		foreach($recordIds as $recordId){
			$this->assertFalse($records->isLocked($recordId));
		}
	}

	function testUser_isSuperUser(){
		$result = ExternalModules::query('select username from redcap_user_information where super_user = 1 limit 1', []);
		$row = $result->fetch_assoc();
		$username = $row['username'];
		
		$user = $this->getUser($username);
		$this->assertTrue($user->isSuperUser());
	}

	function testUser_getRights(){
		$project = $this->getProject(TEST_SETTING_PID);
		$username = $this->getRandomUsername();
		$project->removeUser($username);
		$project->addUser($username);

		$result = ExternalModules::query("
			select * from redcap_user_rights
			where project_id = ?
			and username = ?
		", [TEST_SETTING_PID, $username]);

		$row = $result->fetch_assoc();
		$projectId = $row['project_id'];
		$username = strtolower($row['username']);
		$expectedRights = \UserRights::getPrivileges($projectId, $username)[$projectId][$username];

		$user = $this->getUser($username);
		
		$actualRights = $user->getRights($projectId, $username);
		$this->assertSame($expectedRights, $actualRights);

		$this->setProjectId($projectId);
		$actualRights = $user->getRights(null, $username);
		$this->assertSame($expectedRights, $actualRights);
	}
	
	function testGetEventId(){
		$this->assertThrowsException(function(){
			$this->getEventId();
		}, ExternalModules::tt('em_errors_65', 'pid'));

		$expected = $this->getEventIds(TEST_SETTING_PID)[0];

		$this->assertSame($expected, $this->getEventId(TEST_SETTING_PID));

		$this->setProjectId(TEST_SETTING_PID);
		$this->assertSame($expected, $this->getEventId());

		$urlEventId = 99999999;
		$_GET['event_id'] = $urlEventId;
		$this->assertEquals($urlEventId,  $this->getEventId());
	}

    function testGetSafePath(){
        $test = function($path, $root=null){
            // Get the actual value before manipulating the parameters for testing.
            $actual = call_user_func_array([$this, 'getSafePath'], func_get_args());

            $cleanPath = function($path, $root){
                if(str_starts_with($path, $root)){
                    $path = str_replace($root, '', $path);
                }

                return $path;
            };

            $moduleDirectory = ExternalModules::getModuleDirectoryPath(TEST_MODULE_PREFIX);
            if(!$root){
                $root = $moduleDirectory;
                $path = str_replace($root, '', $path); // Turn absolute paths into relative ones
            }
            else{
                $moduleDirectory = rtrim($moduleDirectory, '/\\');

                if(!ExternalModules::isAbsolutePath($root)){
                    foreach([
                        "$moduleDirectory/$root",
                        "$moduleDirectory\\$root",
                    ] as $tempRoot){
                        $path = $cleanPath($path, $tempRoot);
                    }

                    $root = $moduleDirectory . DIRECTORY_SEPARATOR . $root;
                }
                else{
                    $path = $cleanPath($path, $root);
                }
            }
            
            $root = rtrim($root, '/\\');
            $path = ltrim($path, '/\\');
            $expected = $root . DIRECTORY_SEPARATOR . $path;

            $this->assertEquals($expected, $actual);
        };

        $test(basename(__FILE__));
        $test('.');
        $test('non-existant-file.php');
        $test('test-subdirectory');
        $test('test-file.php', 'test-subdirectory'); // relative paths

        $testDirPath = ExternalModules::getTestModuleDirectoryPath() . DIRECTORY_SEPARATOR . 'test-subdirectory' . DIRECTORY_SEPARATOR;
        $test('test-file.php', $testDirPath); // absolute path to root only
        $test($testDirPath . 'test-file.php', 'test-subdirectory'); // relative path to root only
        $test($testDirPath . 'test-file.php', $testDirPath); // absolute paths to both

        $expectedExceptions = [
            'outside of your allowed parent directory' => [
                '../index.php',
                '..',
                '../non-existant-file',
                '../../../passwd'
            ],
            'only works on directories that exist' => [
                'non-existant-directory/non-existant-file.php',
            ],
            'does not exist as either an absolute path or a relative path' => [
                ['foo', 'non-existent-root']
            ]
        ];

        foreach($expectedExceptions as $excerpt=>$calls){
            foreach($calls as $args){
                if(!is_array($args)){
                    $args = [$args];
                }    

                $this->assertThrowsException(function() use ($test, $args){
                    call_user_func_array($test, $args);
                }, $excerpt);
            }
        }
    }

	function testGetSafePath_symLinks(){
		if(PHP_OS_FAMILY === 'Windows'){
			/**
			 * Not sure why this test doesn't work in windows,
			 * file_exists() seems return false even though the link destination exists.
			 */
			$this->markTestSkipped();
		}

		$dirPath = $this->createTempDir();

		$filename = 'foo';

		$link = $this->createTempFile();
		unlink($link);
		symlink($dirPath, $link);

		// Make sure the symlink was NOT replaced with it's destination path.
		$this->assertSame("$link/$filename", $this->getSafePath($filename, $link));
	}

    function testConvertIntsToStrings(){
        $assert = function($expected, $data){
            $actual = $this->convertIntsToStrings($data);
            $this->assertSame($expected, $actual);
        };

        $assert(['1', 'b', null], [1, 'b', null]);
        $assert(['a' => '1', 'b'=>'b', 'c' => null], ['a' => 1, 'b'=>'b', 'c' => null]);
    }

    function testIsPage(){
        $path = 'foo/goo.php';

        $this->assertFalse($this->isPage($path));
        
        $_SERVER['REQUEST_URI'] = APP_PATH_WEBROOT . $path;
        $this->assertTrue($this->isPage($path));
    }

	function testIsREDCapPage(){
        $path = 'foo/goo.php';

        $this->assertFalse($this->isREDCapPage($path));
        
        $_SERVER['REQUEST_URI'] = APP_PATH_WEBROOT . $path;
        $this->assertTrue($this->isREDCapPage($path));
    }

	function testIsModulePage(){
		$assert = function($expected, $url, $param){
			parse_str(parse_url($url)['query'] ?? '', $getParams);
			foreach($getParams as $key=>$value){
				$_GET[$key] = $value;
			}

			$_SERVER['REQUEST_URI'] = APP_URL_EXTMOD_RELATIVE . $url;
			$this->assertSame($expected, $this->isModulePage($param));
		};

		$prefix = TEST_MODULE_PREFIX;
		
		$assert(false, "", null);
		$assert(false, "some-other-path", null);
		$assert(false, "?prefix=$prefix", null);
		$assert(true, "?prefix=$prefix&page=page1", null);
		$assert(false, "?prefix=some-other-prefix&page=page1", null);
		$assert(false, "?prefix=$prefix&page=page1", "page2");
		$assert(true, "?prefix=$prefix&page=page2", "page2");
		$assert(true, "?page=page2&prefix=$prefix", "page2");
		$assert(true, "?prefix=$prefix&page=dir1/page1", "dir1/page1");
		$assert(false, "index.php?prefix=$prefix&page=page1", "page1"); // While this works, it is considered invalid since getUrl() was not used to build it
		$assert(false, "some-other-url.php?prefix=$prefix&page=page1", "page1");
	}

	function testCountLogs(){
		$this->setProjectId(TEST_SETTING_PID);
		
		$whereClause = "message = ?";
		$message = rand();

		$assert = function($expected) use ($whereClause, $message){
			$actual = $this->countLogs($whereClause, $message);
			$this->assertSame($expected, $actual);
		};
		
		$assert(0);

		$this->log($message);
		$assert(1);

		$this->log($message);
		$assert(2);

		$this->getInstance()->removeLogs($whereClause, $message);
		$assert(0);
	}

	function testGetRecordIdField(){
		$metadata = ExternalModules::getMetadata(TEST_SETTING_PID);
		$expected = array_keys($metadata)[0];
		
		$this->assertThrowsException(function(){
			$this->getRecordIdField();
		}, ExternalModules::tt('em_errors_65', 'pid'));

		$this->assertSame($expected, $this->getRecordIdField(TEST_SETTING_PID));

		$this->setProjectId(TEST_SETTING_PID);
		$this->assertSame($expected, $this->getRecordIdField());
	}

	function testObjectReferencePassThrough(){
		$name = 'records';
		$expected = $this->getFramework()->{$name};
		$this->assertNotNull($expected);
		$this->assertSame($expected, $this->getInstance()->{$name});
	}

	function testGetProjectStatus(){
		$this->assertThrowsException(function(){
			$this->getProjectStatus(-1);
		}, ExternalModules::tt("em_errors_131"));

		// Test behavior for a PID that doesn't exist.
		$this->assertSame(null, $this->getProjectStatus(PHP_INT_MAX));

		$assert = function($expected, $status, $completedTime = null){
			$this->query('update redcap_projects set status = ?, completed_time = ? where project_id = ?', [$status, $completedTime, TEST_SETTING_PID]);
			ExternalModules::clearProjectCache(TEST_SETTING_PID);
			$this->assertSame($expected, $this->getProjectStatus(TEST_SETTING_PID));
		};

		$assert(null, 3); // some status that isn't checked in this method
		$assert('DONE', 2, ExternalModules::makeTimestamp());
		$assert('AC', 2);
		$assert('PROD', 1);
		$assert('DEV', 0);
	}

	function testIsPHPGreaterThan()
	{
		$isPHPGreaterThan = function($requiredVersion){
			return $this->callPrivateMethodForClass($this->getFramework(), 'isPHPGreaterThan', $requiredVersion);
		};

		$versionParts = [PHP_MAJOR_VERSION, PHP_MINOR_VERSION, PHP_RELEASE_VERSION];

		$versionParts[2] = $versionParts[2]+1;
		$higherVersion = implode('.', $versionParts);

		$versionParts[0] = $versionParts[0]-1;
		$lowerVersion = implode('.', $versionParts);

		$this->assertTrue($isPHPGreaterThan(PHP_VERSION));
		$this->assertFalse($isPHPGreaterThan($higherVersion));
		$this->assertTrue($isPHPGreaterThan($lowerVersion));
	}	

	function testQueryLogs_parameters()
	{
		$m = $this->getInstance();
		$value = rand();
		$m->log('test', [
			'value' => $value
		]);

		$result = $m->queryLogs("select count(*) as count where value = ?", $value);
		$row = $result->fetch_assoc();

		$this->assertSame(1, $row['count']);
	}

	function testQueryLogs_complexStatements()
	{
		$m = $this->getInstance();

		// Just make sure this query is parsable, and runs without an exception.
		$m->queryLogs("select 1 where a = 1 and (b = 2 or c = 3)", []);

		$this->assertTrue(true); // Each test requires an assertion
	}

	function testQueryLogs_complexSelectClauses()
	{
		$m = $this->getInstance();

		$paramName = 'some_param';
		$logId = $m->log('test', [
			$paramName => '12345'
		]);
		$whereClause = 'log_id = ?';

		// Make sure a function and an "as" clause work on a regular column.
		$result = $m->queryLogs("select length($paramName) as abc where $whereClause", $logId);
		$this->assertSame(5, $result->fetch_assoc()['abc']);

		// Make sure a function and an "as" clause work on a regular column.
		$result = $m->queryLogs("select unix_timestamp(timestamp) as abc where $whereClause", $logId);
		
		$row = $result->fetch_assoc();
		$aDayAgo = time() - ExternalModules::DAY_IN_SECONDS;
		$this->assertTrue($row['abc'] > $aDayAgo);
	}

	// function testQueryLogs_isSQLReservedWord()
	// {
	// 	$logId = $this->log('foo');

	// 	// Simply ensure this query executes successfully with 'interval' and 'day' reserved words included.
	// 	$result = $this->queryLogs('select log_id, date_sub(now(), interval ? day)', 1);
	// 	$this->assertSame($logId, $result->fetch_assoc()['log_id']);
	// }

	function testQueryLogs_multipleReferencesToSameColumn()
	{
		$m = $this->getInstance();

		// Just make sure this query is parsable, and runs without an exception.
		$m->queryLogs("select 1 where a > 1 and a < 5", []);

		$this->assertTrue(true); // Each test requires an assertion
	}

	function testQueryLogs_groupBy()
	{
		$paramName = 'some_param';
		for($i=0; $i<2; $i++){
			$this->log('some_message', [
				$paramName => 'some_value'
			]);
		}

		$assert = function($sql, $expectedCount){
			$result = $this->queryLogs($sql, []);
			$this->assertSame($expectedCount, $result->num_rows);
		};

		$sql = 'select log_id, message';
		$assert($sql, 2);
		$assert($sql . " group by $paramName", 1);
	}

	function testQueryLogs_orderBy()
	{
		$expected = [];
		$paramName = 'some_param';
		for($i=0; $i<3; $i++){
			$logId = $this->log('some message', [
				$paramName => $i
			]);

			$expected[] = [
				'log_id' => (string) $logId,
				$paramName => (string) $i
			];
		}

		$assert = function($order, $expected) use ($paramName){
			foreach(['log_id', $paramName] as $orderColumn){
				$result = $this->queryLogs("select log_id, $paramName order by $orderColumn $order", []);
	
				$actual = [];
				while($row = $result->fetch_assoc()){
					$actual[] = $row;
				}
	
				$this->assertSame($expected, $actual);
			}
		};

		$assert('asc', $expected);
		$assert('desc', array_reverse($expected));
	}

	function testQueryLogs_limit(){
		$this->log('message 1');
		$this->log('message 2');
		
		$result = $this->queryLogs('select foo limit 1', []);
		$this->assertIsArray($result->fetch_assoc());
		$this->assertNull($result->fetch_assoc());
	}

	function testQueryLogs_stars()
	{
		$m = $this->getInstance();

		// "select count(*)" should be allowed
		$result = $m->queryLogs("select count(*) as count where some_fake_parameter = 1", []);
		$row = $result->fetch_assoc();
		$this->assertSame('0', $row['count']);

		// "select *" should not be allowed
		$this->assertThrowsException(function() use ($m){
			$m->queryLogs('select * where some_fake_parameter = 1');
		}, "Columns must be explicitly defined in all log queries");
	}

	function testRemoveLogs()
	{
		// Should succeed because project_id is manually specified.
		$this->removeLogs('project_id = ?', TEST_SETTING_PID);

		$this->assertThrowsException(function(){
			$this->removeLogs('? = ?', [1, 2]);
		}, ExternalModules::tt('em_errors_162'));

		$this->setProjectId(TEST_SETTING_PID);
		$m = $this->getInstance();
		$message = rand();
		$logId1 = $m->log($message);
		$logId2 = $m->log($message);

		$m->removeLogs("log_id = ?", $logId1);

		$result = $m->queryLogs('select log_id where message = ?', $message);
		$this->assertSame($logId2, $result->fetch_assoc()['log_id']);
		
		// Make sure only one row exists
		$this->assertNull($result->fetch_assoc());

		$this->assertThrowsException(function() use ($m){
			$m->removeLogs('');
		}, 'must specify a where clause');

		$this->assertThrowsException(function() use ($m){
			$m->removeLogs('external_module_id = ?', 1);
		}, 'not allowed to prevent modules from accidentally removing logs for other modules');
	}

	function testAddRemoveLogsLimit(){
		$assert = function($input, $output){
			$select = 'SELECT ? AS log_id';
			$parsed = (new PHPSQLParser())->parse("$select $input");
			$parsed = $this->addRemoveLogsLimit($parsed);

			$expectedSql = "$select $output";
			$this->assertSame($expectedSql, (new PHPSQLCreator())->create($parsed));

			// Make sure this completes without error.
			$this->query($expectedSql, 1);
		};

		$assert('', 'LIMIT 10000', 'Automatically set a LIMIT and ORDER BY');
		$assert('LIMIT 1', 'LIMIT 1', 'Leave existing LIMIT alone');
	}

    function testIndex(){
        $this->assertIndex(ExternalModules::tt('em_errors_123'));

        $prefix = 'some_disabled_prefix';
		$this->setPrefix($prefix);
        $this->assertIndex(ExternalModules::tt('em_errors_124', $prefix));

        $prefix = TEST_MODULE_PREFIX;
        $this->setPrefix($prefix);
        $_GET['NOAUTH'] = '';
        $this->assertIndex(ExternalModules::tt('em_errors_125', $prefix));

        $pid = TEST_SETTING_PID;
        $this->setProjectId(TEST_SETTING_PID);
        unset($_GET['NOAUTH']);
        $this->assertIndex(ExternalModules::tt('em_errors_126', $prefix, $pid));

        $this->setProjectId(null);
        
        $_GET['page'] = 'some_page_that_does_not_exist';
        $this->assertIndex(ExternalModules::tt('em_errors_127'));
        $_GET['page'] = []; // We've seen requests unexpectedly specify an array in PROD.  Ensure these fail.
        $this->assertIndex(ExternalModules::tt('em_errors_127'));

        $page = 'unit_test_page';
        $_GET['page'] = $page;
        $this->assertIndex();
        
        $m = $this->getInstance();
        $m->setLinkCheckDisplayReturnValue(false);
        $this->assertIndex();

		// Set the pid to test passing it to redcap_module_link_check_display().
		$this->setProjectId(TEST_SETTING_PID);
		ExternalModules::setProjectSetting(TEST_MODULE_PREFIX, TEST_SETTING_PID, ExternalModules::KEY_ENABLED, true);

        $config = [
            'links' => [
                'project' => [
                    [
                        'name' => 'Unit Test Page',
                        'url' => $page
                    ]
                ]
            ]
        ];

        $this->setConfig($config);
        $this->assertIndex(ExternalModules::tt('em_errors_128'));

        $m->setLinkCheckDisplayReturnValue(true);
        $this->assertIndex();

		$m->setLinkCheckDisplayReturnValue(true);
        $this->assertIndex();
        
        $_GET['NOAUTH'] = '';
        $config['no-auth-pages'] = [$page];
        $this->setConfig($config);
        $this->assertIndex();
	}

	function testGetCSRFToken(){
		\Authentication::setAAFCsrfToken(null);
		$this->assertSame($this->getCSRFToken(), \System::getCSRFToken(), 'The standard REDCap CSRF token should be returned for authenticated requests');
		$this->assertSame($_POST['redcap_csrf_token'], $this->getCSRFToken());

		$_GET['NOAUTH'] = '';
		$token = $this->getCSRFToken();
		$this->assertNotEquals($token, \System::getCSRFToken(), 'Double submit cookies should be used for NOAUTH requests');
		$this->assertSame(80, strlen($token), 'Make sure the length of the token looks right');
		$this->assertSame($token, $this->getCSRFToken(), 'The token should not change once it is set');

		$this->setPrivateVariable('CSRF_DOUBLE_SUBMIT_COOKIE', null, 'ExternalModules\Framework');
		$this->assertNotEquals($token, $this->getCSRFToken(), 'The token should be set if it is null');		
	}

	function testGetChoiceLabel_data(){
		$recordId = 1;

		$this->saveData([[
			TEST_RECORD_ID => $recordId,
			TEST_CHECKBOX_FIELD . '___1' => true,
			TEST_CHECKBOX_FIELD . '___3' => true,
		]]);

		// We may want to consider this feature deprecated, since it was only ever implemented for checkboxes, and possibly only used in the Email Alerts module.
		$this->assertSame('a, c', $this->getChoiceLabel([
			'project_id' => TEST_SETTING_PID,
			'event_id' => $this->getEventId(TEST_SETTING_PID),
			'record_id' => $recordId,
			'field_name' => TEST_CHECKBOX_FIELD,
		]));
	}

	function testGetChoiceLabel_radio(){
		$assert = function($pid = null){
			// These values are defined in test-project-data-dictionary.csv.
			$value = '1';
			$expected = 'a';

			// Old syntax
			$this->assertSame($expected, $this->getChoiceLabel(TEST_RADIO_FIELD, $value, $pid));
		
			$args = [
				'field_name' => TEST_RADIO_FIELD,
				'value' => $value,
				'project_id' => $pid,
			];

			// New syntax
			$this->assertSame($expected, $this->getChoiceLabel($args));
		};
		
		$assert(TEST_SETTING_PID);

		$this->setProjectId(TEST_SETTING_PID);
		$assert();
	}

	function testGetChoiceLabel_sql(){
		$result = $this->query("
			select * from redcap_metadata
			where
				project_id = ?
				and field_name = ?
		", [TEST_SETTING_PID, TEST_SQL_FIELD]);

		$field = $result->fetch_assoc();
		$result = $this->query($field['element_enum'], []);
		$choices = $result->fetch_all();

		foreach($choices as $choice){
			$code = $choice[0];
			if($code === '' || $code === null
			){
				// Ensure historical behavior is maintained.
				$expectedLabel = '';
			}
			else{
				$expectedLabel = $choice[1];
			}

			$actualLabel = $this->getChoiceLabel($field['field_name'], $code, $field['project_id']);
			
			$this->assertSame($expectedLabel, $actualLabel, "Failed on field: " . json_encode($field));
		}
	}

	function testGetChoiceLabels(){
		$fieldName = 'test_radio_field';
		$expected = [
			1 => 'a',
			2 => 'b',
			3 => 'c'
		];
		
		$this->assertSame($expected, $this->getChoiceLabels($fieldName, TEST_SETTING_PID));

		$this->assertThrowsException(function() use ($fieldName){
			$this->getChoiceLabels($fieldName);
		}, ExternalModules::tt('em_errors_65', 'pid'));

		$this->setProjectId(TEST_SETTING_PID);
		$this->assertSame($expected, $this->getChoiceLabels($fieldName));
	}

	function testInitializeJavascriptModuleObject_partial(){
		// Spoof a survey url
		$_SERVER['REQUEST_URI'] = APP_PATH_SURVEY;
		$_GET['s'] = (string) rand();

		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		ob_start();
		$m->initializeJavascriptModuleObject();
		$output = ob_get_clean();

		$reflectionClass = new \ReflectionClass(TestModule::class);
		$namespace = $reflectionClass->getNamespaceName();
		$namespace = str_replace('\\', '.', $namespace);

		foreach([
			"const module = ExternalModules.$namespace",
			"module.log = function(message, parameters) {"
		] as $expectedExcerpt){
			$this->assertTrue(strpos($output, $expectedExcerpt) !== false, 'The output of initializeJavascriptModuleObject() does not contain the following: ' . $expectedExcerpt);
		}
	}

	function testGetFirstEventId(){
		$this->setProjectId(TEST_SETTING_PID);
		$eventId = $this->getEventId(TEST_SETTING_PID);

		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();
		$this->assertSame($eventId, $m->getFirstEventId());
	}

	function testGetIP()
	{
		$ip = '1.2.3.4';
		$_SERVER['HTTP_CLIENT_IP'] = $ip;
		$username = 'jdoe';
		ExternalModules::setUsername($username);

		$assertIp = function($expected, $param = null){
			$this->assertSame($expected, $this->callPrivateMethodForClass($this->getFramework(), 'getIP', $param));
		};

		$ipParameter = '2.3.4.5';
		$assertIp($ipParameter, $ipParameter);

		$assertIp($ip);

		$_SERVER['REQUEST_URI'] = APP_PATH_SURVEY;
		$assertIp(null);

		$_SERVER['REQUEST_URI'] = '';
		$assertIp($ip);

		ExternalModules::setUsername(null);
		$assertIp(null);

		ExternalModules::setUsername($username);
		$assertIp($ip);

		unset($_SERVER['HTTP_CLIENT_IP']);
		$assertIp(null);
	}

	function testLogAndQueryLog()
	{
		$this->setProjectId(TEST_SETTING_PID);

		$m = $this->getInstance();
		$testingModuleId = $this->getUnitTestingModuleId();

		// Remove left over messages in case this test previously failed
		$m->query('delete from redcap_external_modules_log where external_module_id = ?', [$testingModuleId]);

		$message = TEST_LOG_MESSAGE;
		$paramName1 = 'testParam1';
		$paramValue1 = rand();
		$paramName2 = 'testParam2';
		$paramValue2 = rand();
		$paramName3 = 'testParam3';

		$query = function () use ($m, $testingModuleId, $message, $paramName1, $paramName2) {
			$results = $m->queryLogs("
				select log_id,timestamp,username,ip,external_module_id,record,message,$paramName1,$paramName2
				where
					message = ?
					and timestamp > ?
				order by log_id asc
			", [$message, date('Y-m-d', time()-10)]);

			$timestampThreshold = 5;

			$rows = [];
			while ($row = $results->fetch_assoc()) {
				$currentUTCTime = new \DateTime("now", new \DateTimeZone("UTC"));
				$timeSinceLog = $currentUTCTime->getTimestamp() - strtotime($row['timestamp']);

				$this->assertTrue(gettype($row['log_id']) === 'integer');
				$this->assertEquals($testingModuleId, $row['external_module_id']);
				$this->assertEquals($message, $row['message']);

				if(!$this->didDSTHourChangeJustOccur()){
					$this->assertTrue($timeSinceLog < $timestampThreshold, "The value $timeSinceLog is expected to be less than $timestampThreshold.  Make sure your PHP timezone matches your database timezone.");
				}

				$rows[] = $row;
			}

			return $rows;
		};

		ExternalModules::setUsername(null);
		$_SERVER['HTTP_CLIENT_IP'] = null;
		$this->setRecordId(null);
		$m->log($message);

		$username = $this->getRandomUsername();

		ExternalModules::setUsername($username);
		$_SERVER['HTTP_CLIENT_IP'] = '1.2.3.4';
		$this->setRecordId('abc-' . rand()); // We prepend a string to make sure alphanumeric record ids work.
		$m->log($message, [
			$paramName1 => $paramValue1,
			$paramName2 => $paramValue2,
			$paramName3 => null
		]);

		$rows = $query();
		$this->assertEquals(2, count($rows));
		
		$row = $rows[0];
		$this->assertSame($message, $row['message']);
		$this->assertNull($row['username']);
		$this->assertNull($row['ip']);
		$this->assertNull($row['record']);
		$this->assertFalse(isset($row[$paramName1]));
		$this->assertFalse(isset($row[$paramName2]));

		$row = $rows[1];
		$this->assertEquals($username, $row['username']);
		$this->assertEquals($_SERVER['HTTP_CLIENT_IP'], $row['ip']);
		$this->assertEquals($m->getRecordId(), $row['record']);
		$this->assertEquals($paramValue1, $row[$paramName1]);
		$this->assertEquals($paramValue2, $row[$paramName2]);
		$this->assertFalse(isset($row[$paramName3]));

		$m->removeLogs("$paramName1 is null", []);
		$rows = $query();
		$this->assertEquals(1, count($rows));
		$this->assertEquals($paramValue1, $rows[0][$paramName1]);

		$m->removeLogs("message = '$message'", []);
		$rows = $query();
		$this->assertEquals(0, count($rows));
	}

	private function didDSTHourChangeJustOccur(){
		$current = date('I');
		$previous = date('I', strtotime('-1 hour'));
		
		return $current !== $previous;
	}

	function testLogAndQueryLog_allowedCharacters()
	{
		$this->setProjectId(TEST_SETTING_PID);

		$name = 'aA1 -_$';
		$value = (string) rand();
		
		$logId = $this->log('foo', [
			$name => $value,
			'goo' => 'doo'
		]);

		$whereClause = 'log_id = ?';
		$result = $this->queryLogs("select log_id, timestamp, goo, `$name` where $whereClause", $logId);
		$row = $result->fetch_assoc();
		$this->assertSame($value, $row[$name]);
		$this->removeLogs($whereClause, $logId);
	}

	function testLogAndQueryLog_disallowedCharacters()
	{
		$this->setProjectId(TEST_SETTING_PID);

		$invalidParamName = 'sql injection ; example';
		
		$assertThrowsException = function($action) use ($invalidParamName){
			$this->assertThrowsException($action, ExternalModules::tt('em_errors_115', $invalidParamName));
		};

		$assertThrowsException(function() use ($invalidParamName){
			$this->log('foo', [
				$invalidParamName => rand()
			]);
		});
		$this->removeLogs('log_id = ?', db_insert_id());

		$assertThrowsException(function() use ($invalidParamName){
			$this->queryLogs("select 1 where `$invalidParamName` is null");
		});
	}

	function testLog_timestamp()
	{
		$m = $this->getInstance();

		$timestamp = ExternalModules::makeTimestamp(time()-ExternalModules::HOUR_IN_SECONDS);
		$logId = $m->log('test', [
			'timestamp' => $timestamp
		]);
		
		$this->assertLogValues($logId, [
			'timestamp' => $timestamp
		]);
	}

	function testLog_pid()
	{
		$m = $this->getInstance();
		$message = 'test';
		$whereClause = "message = ?";
		$expectedPid = (string) rand();

		$assertRowCount = function($expectedCount) use ($m, $message, $whereClause, $expectedPid){
			$result = $m->queryLogs('select pid where ' . $whereClause, $message);
			$rows = [];
			while($row = $result->fetch_assoc()){
				$rows[] = $row;

				$pid = ExternalModules::getProjectId();
				if(!empty($pid)){
					$this->assertEquals($expectedPid, $pid);
				}
			}

			$this->assertEquals($expectedCount, count($rows));
		};

		$m->log($message);
		$this->setProjectId($expectedPid);
		$m->log($message);

		// A pid is still set, so only that row should be returned.
		$assertRowCount(1);

		// Log a message overriding the project_id to be null
		$logId = $m->log($message, ['project_id' => null]);

		// Ensure the new row is not returned since a project ID is still set
		$assertRowCount(1);
		
		// Unset the pid and make sure all three rows are returned.
		$this->setProjectId(null);
		$assertRowCount(3);

		// Ensure the project_id was correctly overridden to be null
		$row = $this->queryLogs('select log_id, project_id where log_id = ?', $logId)->fetch_assoc();
		$this->assertSame($logId, $row['log_id']);
		$this->assertSame(null, $row['project_id']);

		// Re-set the pid, remove, the pid row, and ensure it is removed
		$this->setProjectId($expectedPid);
		$m->removeLogs($whereClause, $message);
		$assertRowCount(0);

		// Unset the pid and make sure only the rows without the pid are returned
		$this->setProjectId(null);
		$assertRowCount(2);

		// Make sure removeLogs() now removes the rows without the pid.
		$m->removeLogs($whereClause . ' and project_id is null ', $message);
		$assertRowCount(0);
	}

	function testLog_emptyMessage()
	{
		$m = $this->getInstance();

		foreach ([null, ''] as $value) {
			$this->assertThrowsException(function () use ($m, $value) {
				$m->log($value);
			}, 'A message is required for log entries');
		}
	}

	function testLog_reservedParameterNames()
	{
		$m = $this->getInstance();

		$reservedParameterNames = AbstractExternalModule::$RESERVED_LOG_PARAMETER_NAMES;

		foreach ($reservedParameterNames as $name) {
			$this->assertThrowsException(function () use ($m, $name) {
				$m->log('test', [
					$name => 'test'
				]);
			}, 'parameter name is set automatically and cannot be overridden');
		}
	}

	function testLog_recordId()
	{
		$m = $this->getInstance();

		$this->setRecordId(null);
		$logId = $m->log('test');
		$this->assertLogValues($logId, [
			'record' => null
		]);

		$generateRecordId = function(){
			return 'some prefix to make sure string record ids work - ' . rand();
		};

		$message = TEST_LOG_MESSAGE;
		$recordId1 = $generateRecordId();
		$this->setRecordId($recordId1);

		$logId = $m->log($message);
		$this->assertLogValues($logId, ['record' => $recordId1]);

		// Make sure the detected record id can be overridden by developers
		$params = ['record' => $generateRecordId()];
		$logId = $m->log($message, $params);
		$this->assertLogValues($logId, $params);
	}

	// Verifies that the specified values are stored in the database under the given log id.
	private function assertLogValues($logId, $expectedValues = [])
	{
		$columnNamesSql = implode(',', array_keys($expectedValues));
		$selectSql = "select $columnNamesSql where log_id = ?";

		$m = $this->getInstance();
		$result = $m->queryLogs($selectSql, $logId);
		$log = $result->fetch_assoc();

		foreach($expectedValues as $name=>$expectedValue){
			$actualValue = $log[$name];
			$this->assertSame($expectedValue, $actualValue, "For the '$name' log parameter:");
		}
	}

	function testLog_escapedCharacters()
	{
		$m = $this->getInstance();
		$maliciousSql = "'; delete from everything";
		$m->log($maliciousSql, [
			"malicious_param" => $maliciousSql
		]);

		$selectSql = 'select message, malicious_param order by timestamp desc limit 1';
		$result = $m->queryLogs($selectSql, []);
		$row = $result->fetch_assoc();
		$this->assertSame($maliciousSql, $row['message']);
		$this->assertSame($maliciousSql, $row['malicious_param']);
	}

	function testLog_spacesInParameterNames()
	{
		$this->setProjectId(TEST_SETTING_PID);

		$m = $this->getInstance();

		$paramName = "some param";
		$paramValue = "some value";

		$m->log('test', [
			$paramName => $paramValue
		]);

		$selectSql = "select `$paramName` where `$paramName` is not null order by `$paramName`";
		$result = $m->queryLogs($selectSql, []);
		$row = $result->fetch_assoc();
		$this->assertSame($paramValue, $row[$paramName]);

		$m->removeLogs("`$paramName` is not null", []);
		$result = $m->queryLogs($selectSql, []);
		$this->assertNull($result->fetch_assoc());
	}

	function testLog_unsupportedTypes()
	{
		$this->assertThrowsException(function(){
			$m = $this->getInstance();
			$m->log('foo', [
				'some-unsupported-type' => new \stdClass()
			]);
		}, "The type 'object' for the 'some-unsupported-type' parameter is not supported");
	}

	function testLog_overridableParameters()
	{
		$m = $this->getInstance();

		$testValues = [
			'timestamp' => date("Y-m-d H:i:s"),
			'username' => $this->getRandomUsername(),
			'project_id' => 1
		];

		foreach(AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE as $name){
			$value = $testValues[$name] ?? null;
			if(empty($value)){
				$value = 'foo';
			}

			$params = [
				$name => $value
			];

			$logId = $m->log('foo', $params);
			$this->assertLogValues($logId, $params);

			// Make sure a parameter table entry was NOT made, since the value should only be stored in the main log table.
			$result = $m->query("select * from redcap_external_modules_log_parameters where log_id = ?", [$logId]);
			$row = $result->fetch_assoc();
			$this->assertNull($row);
		}
	}

	function testLog_emptyParamNames()
	{
		$this->setProjectId(TEST_SETTING_PID);

		$this->assertThrowsException(function(){
			$this->log('foo', [
				'' => rand()
			]);
		}, ExternalModules::tt('em_errors_116'));

		$this->removeLogs('log_id = ?', db_insert_id());
	}

	/**
	 * @group slow
	 */
	function testLog_maxMessageLength(){
		$message = $this->getLargeDBTestValue(ExternalModules::LOG_MESSAGE_SIZE_LIMIT);

		$this->log($message); // should run without exception
	
		$this->assertThrowsException(function() use ($message){
			$longMessage = $message . 'a';
			$this->log($longMessage);
		}, ExternalModules::tt('em_errors_159', ExternalModules::LOG_MESSAGE_SIZE_LIMIT));
	}

	function testLog_maxParamNameLength(){
		$name = str_repeat('a', ExternalModules::LOG_PARAM_NAME_SIZE_LIMIT);

		$this->log('whatever', [
			$name => rand()
		]); // should run without exception
	
		$this->assertThrowsException(function() use ($name){
			$longName = $name . 'a';
			$this->log('whatever', [
				$longName => rand()
			]);
		}, ExternalModules::tt('em_errors_160', ExternalModules::LOG_PARAM_NAME_SIZE_LIMIT));
	}

	/**
	 * @group slow
	 */
	function testLog_maxParamValueLength(){
		$value = $this->getLargeDBTestValue(ExternalModules::LOG_PARAM_VALUE_SIZE_LIMIT);

		$this->log('whatever', [
			'whatever' => $value
		]); // should run without exception
	
		$this->assertThrowsException(function() use ($value){
			$longValue = $value . 'a';
			$this->log('whatever', [
				'whatever' => $longValue
			]);
		}, ExternalModules::tt('em_errors_161', ExternalModules::LOG_PARAM_VALUE_SIZE_LIMIT));
	}

	private function getUnitTestingModuleId()
	{
		$id = ExternalModules::getIdForPrefix(TEST_MODULE_PREFIX);
		$this->assertTrue(ctype_digit($id));
		
		return $id;
	}

	function testGetPublicSurveyUrl(){
		$m = $this->getInstance();

		$result = $m->query("
			select *
			from (
				select s.project_id, h.hash, count(*)
				from redcap_surveys s
				join redcap_surveys_participants h
					on s.survey_id = h.survey_id
				join redcap_metadata m
					on m.project_id = s.project_id
					and m.form_name = s.form_name
					and field_order = 1 -- getting the first field is the easiest way to get the first form
				where participant_email is null
				group by s.form_name -- test a form name that exists on multiple projects
				order by count(*) desc
				limit 100
			) a
			order by rand() -- select a random row to make sure we often end up with a different project ID than getPublicSurveyUrl() would by default if it didn't specific a project ID in it's query
			limit 1
		", []);

		$row = $result->fetch_assoc();
		if($row === null){
			// TODO - We need to make this test create the scenario it needs instead of looking for one.
			$this->markTestSkipped();
			return;
		}

		$projectId = $row['project_id'];
		$hash = $row['hash'];

		global $Proj;
		$Proj = ExternalModules::getREDCapProjectObject($projectId);
		$this->setProjectId($projectId);
		
		$expected = APP_PATH_SURVEY_FULL . "?s=$hash";
		$actual = $m->getPublicSurveyUrl();

		$this->assertSame($expected, $actual);
	}

	function assertLogAjax($params, $noAuth, $contextRecord = null)
	{
		if($contextRecord !== null){
			$contextRecord = '' . $contextRecord;
		}

		$username = $noAuth ? null : $this->getRandomUsername(); // Which one?
		ExternalModules::setUsername($username);

		$this->setNoAuth($noAuth);
		$context = [
			"prefix" => TEST_MODULE_PREFIX,
			"user" => $username,
			"project_id" => TEST_SETTING_PID, // This one?
			"record" => $contextRecord,
		];

		$expectedRecord = $params['record'] ?? null;
		if($expectedRecord === null){
			$expectedRecord = $contextRecord;
		}

		$logId = $this->logAjax(TEST_LOG_MESSAGE, $params, $context);
		$this->assertLogValues($logId, [
			'record' => $expectedRecord,
			'username' => $username,
		]);
	}

	function testLogAjax_overridableParameters()
	{
		foreach(AbstractExternalModule::$OVERRIDABLE_LOG_PARAMETERS_ON_MAIN_TABLE as $name){
			ExternalModules::$lastHandleErrorResult = null;

			$this->assertThrowsException(function() use ($name){
				$this->assertLogAjax([$name => 'foo'], true); // Set noauth arg to true to avoid special cases like 'record'.
			}, "'$name' parameter cannot be overridden via AJAX log requests");

			$this->assertStringStartsWith('External Module Log Request Failure', ExternalModules::$lastHandleErrorResult[0]);
		}
	}

	function testLogAjax_record()
	{
		$record = "123";
		$temp_record = ExternalModules::EXTERNAL_MODULES_TEMPORARY_RECORD_ID . "-123";

		$this->assertLogAjax(['record' => $record], false, $record);

		$this->assertLogAjax([], true);

		$this->assertLogAjax([], true, rand());

		$this->assertLogAjax([], false, rand());

		$this->assertLogAjax(['record' => $temp_record], true, $temp_record);

		$this->assertLogAjax(['record' => '123'], false, '456');

		$assertRecordError = function($recordParam, $contextRecord){
			$this->assertThrowsException(
				function() use ($recordParam, $contextRecord){
					$this->assertLogAjax(['record' => $recordParam], true, $contextRecord);
				},
				"'record' parameter cannot be overridden via AJAX log requests"
			);
		};

		$assertRecordError('123', null);
		$assertRecordError('123', '');
		$assertRecordError('123', '456');
		$assertRecordError('', '456');
		$assertRecordError(null, '456');
	}

	function testResetSurveyAndGetCodes_partial(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		// Just make sure it runs without exception for now.  We can expand this test in the future.
		$m->resetSurveyAndGetCodes(TEST_SETTING_PID, 1);
		$this->expectNotToPerformAssertions();
	}

	function testCreatePassthruForm(){
		$m = $this->getInstance();
		ob_start();
		$m->createPassthruForm(TEST_SETTING_PID, 1);
		$form = ob_get_clean();
		$this->assertStringContainsString('document.passthruform.submit', $form);
	}

	function testGetValidFormEventId(){
		$pid = TEST_SETTING_PID;
		$formName = ExternalModules::getFormNames($pid)[0];
		$expected = $this->getValidFormEventId($formName, $pid);
		$actual = (string) $this->getFramework()->getEventId($pid);

		$this->assertSame($expected, $actual);
	}

	function testGetSurveyId(){
		list($surveyId, $formName) = $this->getSurveyId(TEST_SETTING_PID);
		$this->assertTrue(ctype_digit($surveyId));
		$this->assertTrue($surveyId > 0);
		$this->assertSame(ExternalModules::getFormNames(TEST_SETTING_PID)[0], $formName);
	}

	function testThrottle()
	{
		$message = 'test message';
		$logIds = [];

		$assert = function($expected, $maxOccurrences) use ($message){
			$actual = $this->throttle('message = ?', $message, 60, $maxOccurrences);
			$this->assertSame($expected, $actual);
		};

		$log = function() use ($message, &$logIds){
			$logIds[] = $this->log($message);
		};

		$setFirstLogTime = function($time) use (&$logIds){
			$timestamp = date('Y-m-d H:i:s', $time);
			$this->query('update redcap_external_modules_log set timestamp = ? where log_id = ?', [$timestamp, $logIds[0]]);
		};

		$assert(false, 1);
		$log($message);
		$assert(true, 1);
		$assert(false, 2);
		$log($message);
		$assert(true, 2);

		$setFirstLogTime(time()-58);
		$assert(true, 2);
		$setFirstLogTime(time()-61);
		$assert(false, 2);
	}
	
	function testTt(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$key = 'some_key';
		$value = rand();
		$this->spoofTranslation(TEST_MODULE_PREFIX, $key, $value);

		$key = 'some_key';
		$this->assertSame($value, $m->tt($key));
	}

	function testTt_transferToJavascriptModuleObject(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$key = 'some_key';
		$value = (string) rand();
		$this->spoofTranslation(TEST_MODULE_PREFIX, $key, $value);
		
		ob_start();
		$m->tt_transferToJavascriptModuleObject($key, $value);
		$actual = ob_get_clean();

		$this->assertJSLanguageKeyAdded($key, $value, $actual);
	}

	function testTt_addToJavascriptModuleObject(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$key = 'some_key';
		$value = (string) rand();

		ob_start();
		$m->tt_addToJavascriptModuleObject($key, $value);
		$actual = ob_get_clean();
		
		$this->assertJSLanguageKeyAdded($key, $value, $actual);
	}

	function assertJSLanguageKeyAdded($key, $value, $actual){
		$this->assertSame("<script>ExternalModules.\$lang.add(\"emlang_" . TEST_MODULE_PREFIX . "_$key\", \"$value\")</script>", $actual);
	}

	function testIsSurveyPage(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$_SERVER['REQUEST_URI'] = $m->getUrl('foo.php');
		$this->assertFalse($m->isSurveyPage());

		$_SERVER['REQUEST_URI'] = APP_PATH_SURVEY;
		$this->assertTrue($m->isSurveyPage());

		$_SERVER['REQUEST_URI'] .= '?__passthru=DataEntry%2Fimage_view.php';
		$this->assertFalse($m->isSurveyPage());
	}

	function testGetPublicSurveyHash(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$result = $m->query("
			select p.hash 
			from redcap_surveys s
			join redcap_surveys_participants p
			on s.survey_id = p.survey_id
			join redcap_metadata  m
			on m.project_id = s.project_id and m.form_name = s.form_name
			where p.participant_email is null and m.field_order = 1 and s.project_id = ?
		", TEST_SETTING_PID);

		$row = $result->fetch_assoc();

		$this->assertSame($row['hash'] ?? null, $m->getPublicSurveyHash(TEST_SETTING_PID));
	}

	function testSetRecordId(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$value = rand();
		$m->setRecordId($value);
		$this->assertSame($value, $m->getRecordId());
	}

	function testUserSettings(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$key = 'test_user_setting_key';
		
		$value = rand();
		$m->setUserSetting($key, $value);
		$this->assertSame($value, $m->getUserSetting($key));

		$m->removeUserSetting($key);
		$this->assertNull($m->getUserSetting($key));
	}

	function testGetFieldLabel(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$this->setProjectId(TEST_SETTING_PID);
		$this->assertSame('Test Text Field', $m->getFieldLabel(TEST_TEXT_FIELD));
	}

	function testGetConfig(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$config = $m->getConfig();
		$this->assertSame($this->getFrameworkVersion(), $config['framework-version']);
	}

	function testGetModuleDirectoryName(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$this->assertSame(TEST_MODULE_PREFIX . '_' . TEST_MODULE_VERSION, $m->getModuleDirectoryName());
	}

	function testGetSystemSettings(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$value = rand();
		$m->setSystemSetting(TEST_SETTING_KEY, $value);

		$expected = [
			TEST_SETTING_KEY => [
				'system_value' => $value,
				'value' => $value
			]
		];
		
		$this->assertSame($expected, $m->getSystemSettings());
	}

	function testGetModuleName(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$value = rand();
		$this->setConfig([
			'name' => $value
		]);

		$this->assertSame($value, $this->getModuleName());
	}

	function testGetMetadata(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();
		
		$metadata = $m->getMetadata(TEST_SETTING_PID);

		$this->assertSame('text', $metadata[TEST_TEXT_FIELD]['field_type']);
	}

	function testSaveData(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$recordId = 1;
		$eventId = $m->getFirstEventId(TEST_SETTING_PID);
		$value = (string) rand();
		$m->saveData(TEST_SETTING_PID, $recordId, $eventId, [
			TEST_TEXT_FIELD => $value
		]);

		$actual = json_decode(\REDCap::getData(TEST_SETTING_PID, 'json', $recordId, TEST_TEXT_FIELD), true)[0][TEST_TEXT_FIELD];

		$this->assertSame($value, $actual);
	}

	function testSaveInstanceData(){
		// Run against the module instance rather than the framework instance, even prior to v5.
		$m = $this->getInstance();

		$recordId = 1;
		$eventId = $m->getFirstEventId(TEST_SETTING_PID);
		$value = (string) rand();
		$m->saveInstanceData(TEST_SETTING_PID, $recordId, $eventId, TEST_REPEATING_FORM, [
			1 => [
				TEST_REPEATING_FIELD_1 => $value
			]
		]);

		/**
		 * The above doesn't actually save any data, I believe because the saveInstanceData() method is broken.
		 * For full backward compatibility, this test still ensures that the method call succeeds, even if it actually save any data.
		 */
		$this->expectNotToPerformAssertions();
	}
	
	private function assertQueryData($expected, $sql, $params = [], $message = 'assertQueryData() failed', $pid = TEST_SETTING_PID){
		$result = $this->getProject($pid)->queryData($sql, $params);

		$actual = [];
		while($row = $result->fetch_assoc()){
			$actual[] = $row;
		}

		$this->assertSame($expected, $actual, $message);
	}

	function testQueryData_parameters(){
		$recordId = (string) rand();
		
		$expected = [
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => ''		
			]
		];

		$this->saveData($expected);

		$sql = 'select ' . TEST_RECORD_ID . ' where ' . TEST_RECORD_ID . ' = ?';
		$this->assertQueryData($expected, $sql, $recordId);
	}

	function testQueryData_withOrWithoutBrackets(){
		$recordId = (string) rand();
		
		$expected = [
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => ''		
			]
		];

		$this->saveData($expected);

		foreach([TEST_RECORD_ID, '[' . TEST_RECORD_ID . ']'] as $fieldName){
			$sql = "select $fieldName where $fieldName = ?";
			$this->assertQueryData($expected, $sql, $recordId);
		}
	}

	function testQueryData_or(){
		$recordId1 = (string) rand();
		$recordId2 = (string) $recordId1+1;
		
		$expected = [
			[
				TEST_RECORD_ID => $recordId1,
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => ''		
			]
		];

		$this->saveData($expected, TEST_SETTING_PID);

		$this->saveData([
			[
				TEST_RECORD_ID => $recordId2,
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => ''		
			]
		], TEST_SETTING_PID_2);

		$sql = 'select ' . TEST_RECORD_ID . ' where ' . TEST_RECORD_ID . ' = ? or ' . TEST_RECORD_ID . ' = ?';

		// Ensure only the data from TEST_SETTING_PID is returned.
		// We used to have a bug where 'OR' clauses weren't appropriately wrapped in parenthesis and always queried all projects.
		$this->assertQueryData($expected, $sql, [$recordId1, $recordId2]);
	}

	function testQueryData_project(){
		$assert = function($pid, $message){
			$expected = [];
			$sql = 'select ' . TEST_RECORD_ID . ', ' . TEST_TEXT_FIELD . ' where project_id = ?';
			$this->assertQueryData($expected, $sql, $pid, $message);
			
			$expected[] = [
				TEST_RECORD_ID => (string) rand(),
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => (string) rand()
			];
				
			$this->saveData($expected, $pid);

			$this->assertQueryData($expected, $sql, [$pid], $message, $pid);
		};

		$assert(TEST_SETTING_PID, "Make sure data for one project is returned.");

		$_GET['event_id'] = $this->getEventIds(TEST_SETTING_PID_2)[1];
		$assert(TEST_SETTING_PID_2, "Make sure ONLY data for the second project is returned.");	
	}
	
	function testQueryData_records(){
		$expected = [
			[
				TEST_RECORD_ID => (string) rand(),
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',		
				TEST_TEXT_FIELD => (string) rand()
			],
			[
				TEST_RECORD_ID => (string) rand(),
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => (string) rand()
			]
		];

		\REDCap::saveData(TEST_SETTING_PID, 'json', json_encode($expected));

		foreach($expected as $record){
			$sql = 'select ' . TEST_RECORD_ID . ', ' . TEST_TEXT_FIELD . ' where ' . TEST_RECORD_ID . ' = ?';
			$recordId = $record[TEST_RECORD_ID];
			$this->assertQueryData([$record], $sql, $recordId);
		}
	}

	function testQueryData_recordIdField(){
		$record = [
			TEST_RECORD_ID => (string) rand(),
			'redcap_repeat_instrument' => '',
			'redcap_repeat_instance' => '',
			TEST_TEXT_FIELD => (string) rand()
		];

		$this->saveData([$record]);

		$assert = function($fields, $expected){
			$sql = 'select ' . implode(',', $fields);
			$this->assertQueryData($expected, $sql, []);
		};

		$assert([TEST_RECORD_ID, TEST_TEXT_FIELD], [$record]);

		$assert([TEST_RECORD_ID], [
			[
				TEST_RECORD_ID => $record[TEST_RECORD_ID],
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
			]
		]);

		$assert([TEST_TEXT_FIELD], [[TEST_TEXT_FIELD => $record[TEST_TEXT_FIELD]]]);
	}

	function testQueryData_basicSupportedFieldTypes(){
		$getRandomFieldValues = function(){
			$value = rand();
			return [$value, $value+1];
		};

		$fields = [
			TEST_TEXT_FIELD => $getRandomFieldValues(),
			TEST_RADIO_FIELD => [1,2,3],
			TEST_YESNO_FIELD => [0,1],
			TEST_CALC_FIELD => $getRandomFieldValues(),
		];

		foreach($fields as $field=>$values){
			$this->assertTrue(shuffle($values));

			$recordId = rand();
			$expected = [];
			foreach($values as $value){
				$valueRecordId = (string) ($recordId++);
				$record = [
					TEST_RECORD_ID => $valueRecordId,
					'redcap_repeat_instrument' => '',
					'redcap_repeat_instance' => '',
					$field => (string) $value
				];

				$expected[] = $record;

				if($field === TEST_CALC_FIELD){
					/**
					 * The saveData() call below will actually skip creating the record
					 * if the only field value supplied is for a calculated fields.
					 * We remove the field value to get around this.
					 */
					unset($record[$field]);
				}

				$this->saveData([$record]);

				if($field === TEST_CALC_FIELD){
					// Manually insert because saveData() doesn't support calculated fields.
					$table = \Records::getDataTable(TEST_SETTING_PID);
					$this->query("insert into $table values(?, ?, ?, ?, ?, ?)", [
						TEST_SETTING_PID,
						$this->getEventId(TEST_SETTING_PID),
						$valueRecordId,
						$field,
						$value,
						null
					]);
				}
			}

			foreach($expected as $record){
				$sql = 'select ' . TEST_RECORD_ID . ', ' . $field . ' where ' . $field . ' = ?';
				$this->assertQueryData([$record], $sql, $record[$field]);
			}
		}
	}

	function testQueryData_contains(){
		$expectedValue = (string) rand();
		
		// Save a dummy record for the assertions to return
		\REDCap::saveData(TEST_SETTING_PID, 'json', json_encode([[
			TEST_RECORD_ID => rand(),
			'redcap_repeat_instrument' => '',
			'redcap_repeat_instance' => '',
			TEST_TEXT_FIELD => $expectedValue
		]]));

		$assert = function($sql, $expectedResult) use ($expectedValue){
			$columnName = 'some_column';
			$sql = "select 1 where $sql";

			$expected = [];

			if($expectedResult){
				$expected = [
					[
						1 => '1'
					]
				];
			}

			$this->assertQueryData($expected, $sql);
		};

		$assert("contains('abc', 'b')", true);
		$assert("contains('abc', 'z')", false);

		// Make sure "not'ed" contains() calls work as expected.
		$assert("!contains('abc', 'z')", true);
	}

	function testQueryData_orderBy(){
		$saveData = [
			[
				TEST_RECORD_ID => 1,
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => 2
			],
			[
				TEST_RECORD_ID => 2,
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => 1
			]
		];

		$this->saveData($saveData);

		$expected = [
			[
				TEST_TEXT_FIELD => '2'
			],
			[
				TEST_TEXT_FIELD => '1'
			]
		];

		$sql = 'select ' . TEST_TEXT_FIELD;
		$this->assertQueryData($expected, $sql);

		$expected = array_reverse($expected);
		$this->assertQueryData($expected, $sql .' order by ' . TEST_TEXT_FIELD);
	}

	function testQueryData_exceptions(){
		$project = $this->getProject(TEST_SETTING_PID);

		$assert = function($selectFields, $whereClause, $expectedException) use ($project){
			$sql = 'select ' . implode(',', $selectFields) . ' where ' . $whereClause;

			if($expectedException === null){
				$expectedException = ExternalModules::tt('em_errors_143', $sql);
			}

			$this->assertThrowsException(function() use ($project, $sql){
				$project->queryData($sql, []);
			}, $expectedException);
		};

		$assert([TEST_SQL_FIELD], '', ExternalModules::tt('em_errors_142', 'sql', TEST_SQL_FIELD));
		$assert([TEST_REPEATING_FIELD_1, TEST_REPEATING_FORM_2_FIELD_1], '1 = 2', null);
		$assert([], '1 = 2', ExternalModules::tt('em_errors_151'));

		$this->assertThrowsException(function() use ($project){
			$project->queryData('where 1 = 2', []);
		}, ExternalModules::tt('em_errors_151'));
	}

	function testQueryData_disallowedSQL(){
		$assert = function($sql, $expectedException){
			$this->assertThrowsException(function() use ($sql){
				$this->getProject(TEST_SETTING_PID)->queryData($sql, []);
			}, $expectedException);
		};


		$assert("select foo from foo", ExternalModules::tt('em_errors_145', 'FROM'));
		$assert("select foo limit foo", ExternalModules::tt('em_errors_146', 'foo'));
		$assert("select 1 where foo in (select 1)", ExternalModules::tt('em_errors_152', 'subquery', '(select 1)'));

		// Make sure subtrees are checked as well.
		$assert("select 1 where if(foo in (select 1), 1, 2)", ExternalModules::tt('em_errors_152', 'subquery', '(select 1)'));
	}
	
	function testQueryData_multipleCallContexts(){
		$this->saveData([
			[
				TEST_RECORD_ID => '1',
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => TEST_SETTING_PID
			]
		], TEST_SETTING_PID);

		$this->saveData([
			[
				TEST_RECORD_ID => '1',
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => TEST_SETTING_PID_2
			]
		], TEST_SETTING_PID_2);

		$assert = function($pid){
			$project = $this->getProject($pid);
			$result = $project->queryData('select [' . TEST_TEXT_FIELD . ']', []);
			$row = $result->fetch_assoc();
			$this->assertSame($row, [
				TEST_TEXT_FIELD => $pid
			]);

			$this->assertNull($result->fetch_assoc());
		};

		$assert(TEST_SETTING_PID);

		// Make sure the value from the second project is returned, event thought the get param is set to the first project.
		$this->setProjectId(TEST_SETTING_PID);
		$_GET['event_id'] = $this->getEventIds(TEST_SETTING_PID_2)[0];
		$assert(TEST_SETTING_PID_2);
	}

	function testSetDAG(){
		$this->setProjectId(TEST_SETTING_PID);
		$recordId = rand();
		$groupId = $this->getTestDAGID();

		// Make sure the record exists.
		$this->saveData([[
			TEST_RECORD_ID => $recordId
		]]);

		$this->assertSame(null, $this->getDAG($recordId));
		$this->setDAG($recordId, $groupId);
		$this->assertSame((string)$groupId, $this->getDAG($recordId));
	}

	function testGetUser(){
		$this->assertThrowsException(function(){
			$this->getUser();
		}, ExternalModules::tt('em_errors_71'));

		$username = $this->getRandomUsername();
		$user = $this->getUser($username);
		$this->assertSame($username, $user->getUsername());

		ExternalModules::setUsername($username);
		$user = $this->getUser();
		$this->assertSame($username, $user->getUsername());
	}
	
	function testGetSurveyResponses(){
		$pid = (int) TEST_SETTING_PID;
		$event = $this->getEventId($pid);
		$recordId = '1';
		$instance = 1;

		$responses = $this->getSurveyResponses([
			'pid' => $pid,
			'event' => $this->getFirstEventId($pid),
			'form' => TEST_FORM,
			'record' => $recordId,
			'instance' => $instance,
		]);

		$row = $responses->fetch_assoc();
		$this->assertSame($pid, $row['project_id']);
		$this->assertSame($event, $row['event_id']);
		$this->assertSame(TEST_FORM, $row['form_name']);
		$this->assertSame($recordId, $row['record']);
		$this->assertSame($instance, $row['instance']);

		$this->assertNull($responses->fetch_assoc());

		$this->setProjectId($pid);
		$responses = $this->getSurveyResponses([]);
		$row2 = $responses->fetch_assoc();

		/**
		 * Unit tests currently only add a single survey response row,
		 * so the same data should be returned.
		 */
		$this->assertSame($row, $row2);
	}

	function testIsModuleEnabled(){
		$this->assertThrowsException(function(){
			$this->isModuleEnabled('');
		}, ExternalModules::tt("em_errors_50"));

		$this->assertThrowsException(function(){
			$this->isModuleEnabled(TEST_MODULE_PREFIX, -1);
		}, ExternalModules::tt("em_errors_131"));

		$assert = function($expected, $prefix, $pid = null){
			$this->assertSame($expected, $this->isModuleEnabled($prefix, $pid));
		};

		$assert(true, TEST_MODULE_PREFIX);
		$assert(false, 'some_prefix_that_does_not_exist');

		$assert(true, TEST_MODULE_PREFIX, TEST_SETTING_PID);
		$assert(false, 'some_prefix_that_does_not_exist', TEST_SETTING_PID);
	}

	function testCreateTempFile(){
		// We test two files to make sure we are keeping track of all files created.
		$path1 = $this->createTempFile();
		$path2 = $this->createTempFile();
		$this->assertFileExists($path1);
		$this->assertFileExists($path2);
		
		$this->simulateShutdown(function(){
			$this->assertThrowsException(function(){
				$this->createTempFile();
			}, ExternalModules::tt('em_errors_163'));
		});

		$this->assertFileDoesNotExist($path1);
		$this->assertFileDoesNotExist($path2);

		/**
		 * Call simulateShutdown() again to make sure that trying to remove paths that
		 * have already been removed does not cause any errors or warnings.
		 */
		$this->simulateShutdown();
	}

	function testCreateTempDir(){
		$path = $this->createTempDir();
		$this->assertDirectoryExists($path);
		$this->simulateShutdown();
		$this->assertDirectoryDoesNotExist($path);
	}

	function simulateShutdown($actionWhileShuttingDown = null){
		ExternalModules::simulateShutdown($actionWhileShuttingDown);
	}

	function testSanitizeAPIToken(){
		$assert = function($token, $expected = null){
			$actual = $this->sanitizeAPIToken($token);
			$this->assertSame($expected, $actual);
		};

		$assert(null, '');
		$assert("   ABCDEF   0123456789\t\n", 'ABCDEF0123456789');
	}

	function testCreateProject(){
		$this->assertThrowsException(function(){
			$this->createProject(null, -1);
		}, 'do not have');

		$username = ExternalModules::query('select username from redcap_user_information where super_user = 1 limit 1', [])->fetch_row()[0];
		ExternalModules::setUsername($username);

		$expected = (string) rand();
		$pid = $this->createProject($expected, 4);

		$actual = $this->query('select app_title from redcap_projects where project_id = ?', $pid)->fetch_row()[0];
		$this->assertSame($expected, $actual);

		$this->query('delete from redcap_projects where project_id = ?', $pid);

		// Reset AUTO_INCREMENT so PIDs don't balloon on REDCap Test.
		$this->query('alter table redcap_projects AUTO_INCREMENT = ' . (int) $pid, []);
	}

	private function assertHandleAjaxRequest($expectedError, $data){
		$csrfToken = $this->getCSRFToken();
		$_POST['redcap_external_module_csrf_token'] = $csrfToken;
		$_COOKIE['redcap_external_module_csrf_token'] = $csrfToken;
		$_SERVER['REQUEST_METHOD'] = 'POST';

		ob_start();
		$response = ExternalModules::handleAjaxRequest($data);
		$errorLogs = ob_get_clean();

		$actualError = $response['error'];
		$parts = explode('.  See the server error log for details.', $actualError);
		if(count($parts) > 1){
			$this->assertStringContainsString($parts[0], $errorLogs);
			$actualError = explode('The following error occurred while performing a module ajax request: ', $parts[0])[1];
		}
		else{
			$this->assertSame('', $errorLogs);
		}

		$this->assertSame($expectedError, $actualError);
		$this->assertSame($actualError === '', $response['success']);
	}

	function testCheckAjaxRequestVerificationData(){
		$ajaxActions = [
			'test-ajax-action'
		];

		$this->setConfig([
			// These are tested elsewhere.
			ExternalModules::MODULE_AUTH_AJAX_ACTIONS_SETTING => $ajaxActions,
			ExternalModules::MODULE_NO_AUTH_AJAX_ACTIONS_SETTING => $ajaxActions,
		]);

		$data = [
			'action' => 'test-ajax-action',
			'verification' => base64_encode('This string avoids a PHP warning'),
			'payload' => rand(),
		];

		$assert = function($expectedError = null, $resolvedByReverification = true) use (&$data){
			if($expectedError === null){
				$expectedError = 'AJAX request verification failed';
			}

			$assertVerification = function($expectedError) use (&$data){
				// Call handleAjaxRequest() to also test the place where checkAjaxRequestVerificationData() is called.
				$this->assertHandleAjaxRequest($expectedError, $data);
			};

			$assertVerification($expectedError);

			// Make sure the request succeed when the verification is updated to whatever the new context is.
			$data['verification'] = $this->getAjaxSettings()['verification'];

			if($resolvedByReverification){
				$assertVerification('');
			}
		};

		$this->setPrefix(TEST_MODULE_PREFIX);
		$assert();

		$this->setProjectId(rand());
		$assert();

		$_GET['s'] = rand();
		$assert();

		unset($_GET['s']);
		ExternalModules::setUsername('whatever');
		$assert();
	}

	function testGetAjaxSettings_user_partial(){
		$assert = function($expected){
			$settings = $this->getAjaxSettings();
			$crypto = ExternalModules::initAjaxCrypto($this->getCSRFToken());
			$verification = $crypto->decrypt($settings["verification"]);

			$this->assertSame($expected, $verification['user']);
		};

		$username = 'user' . rand();
		ExternalModules::setUsername($username);
		$assert($username);

		// The case is important because of the '$verified_context["user"] != null' check in logAjax()
		$_GET['s'] = 'whatever';
		$assert(null);
		unset($_GET['s']);
		$assert($username);

		// I tried testing survey queue hashes here, but is may require Survey::checkSurveyQueueHash() to be refactored.

		$this->setNoAuth(true);
		$assert(null);
	}

	function testHandleAjaxRequest_partial(){
		ExternalModules::setUsername('whatever');

		$data = [
			'action' => 'some fake action',
			'verification' => $this->getAjaxSettings()['verification'],
			'payload' => rand(),
		];

		$assert = function($expectedError) use (&$data){
			$this->assertHandleAjaxRequest($expectedError, $data);
		};

		$this->setPrefix(TEST_MODULE_PREFIX);

		$assert("The requested action must be specified in the '" . ExternalModules::MODULE_AUTH_AJAX_ACTIONS_SETTING . "' array in 'config.json'!");

		$data['action'] = ExternalModules::MODULE_AJAX_LOGGING_ACTION;

		/**
		 * Require this config.json flag, even on older framework versions.
		 * This was deemed worthwhile since this API is not widely used per this post:
		 * https://redcap.vumc.org/community/post.php?id=119070
		 */
		$assert("The config.json setting '" . Framework::MODULE_ENABLE_AJAX_LOGGING_SETTING ."' must be set to 'true' in order to use the javascript module object's log() method.");

		$config = [];

		$config[Framework::MODULE_ENABLE_AJAX_LOGGING_SETTING] = true;
		$this->setConfig($config);
		$assert('A message is required for log entries');

		$data['payload'] = json_encode([
			'msg' => 'whatever'
		]);
		$assert('');

		ExternalModules::setUsername(null);
		$data['verification'] = $this->getAjaxSettings()['verification'];
		$data['action'] = 'some fake action';
		$assert("The requested action must be specified in the '" . ExternalModules::MODULE_NO_AUTH_AJAX_ACTIONS_SETTING . "' array in 'config.json'!");

		$data['action'] = ExternalModules::MODULE_AJAX_LOGGING_ACTION;
		$assert("The config.json setting '" . Framework::MODULE_ENABLE_NOAUTH_LOGGING_SETTING ."' must be set to 'true' in order to perform logging in a non-authenticated context.");

		$config[Framework::MODULE_ENABLE_NOAUTH_LOGGING_SETTING] = true;
		$this->setConfig($config);
		$assert('');
		
		$this->setPrefix(TEST_MODULE_TWO_PREFIX);
		$data['verification'] = ExternalModules::getFrameworkInstance(TEST_MODULE_TWO_PREFIX)->getAjaxSettings()['verification'];
		$data['action'] = 'some fake action';
		$assert("The module '" . TEST_MODULE_TWO_PREFIX . "' does not implement the 'redcap_module_ajax' hook.");

		// TODO - Test the rest of this method.
	}

	function testHandleAjaxRequest_ensureActiveModulePrefixUnset(){
		$assert = function($prefix){
			$this->assertSame($prefix, $this->callPrivateMethodForClass(ExternalModules::class, 'getActiveModulePrefix'));
		};

		$fakePrefix = 'some prefix that does not exist';
		$this->callPrivateMethodForClass(ExternalModules::class, 'setActiveModulePrefix', $fakePrefix);
		$assert($fakePrefix);
		$this->assertHandleAjaxRequest('AJAX request verification failed', [
			'verification' => ExternalModules::getFrameworkInstance(TEST_MODULE_TWO_PREFIX)->getAjaxSettings()['verification']
		]);
		$assert(null);
	}

	function testAjax_noNewCSRFToken(){
		$assert = function($expected){
			/**
			 * This line would normally be called by redcap_connect.php,
			 * but that is only required once, so we must call it manually.
			 */
			\System::detectClientSpecs();

			$this->assertSame($GLOBALS['isAjax'], $expected);
		};

		$assert(false);
		
		// The next test would fail when EM_ENDPOINT or EM_SURVEY_ENDPOINT is not defined.
		// We cannot define those because this would break the testIndex_csrfToken test.
		// The solution is to define a new test-specific constant and adapt jsmo-ajax.php accordingly.
		if (!defined("EM_ENDPOINT_TEST")) define("EM_ENDPOINT_TEST", true);

		ob_start();
		require __DIR__ . '/../module-ajax/jsmo-ajax.php';
		ob_end_clean();
		
		$assert(true);

		// Put things back the way we found them
		unset($_SERVER['HTTP_X_REQUESTED_WITH']);
		$assert(false);
	}

	function testGetSubSettings_complexNesting2(){
		$this->assertGetSubSettings_complexNesting2(true);

		$this->setFrameworkVersion(Framework::NESTED_REPEATABLE_SETTING_FIX_FRAMEWORK_VERSION-1);
		$this->assertGetSubSettings_complexNesting2(false);
	}

	function testIndex_csrfToken(){
		$this->assertIndex_csrfToken(true);

		$this->setFrameworkVersion(ExternalModules::CSRF_MIN_FRAMEWORK_VERSION-1);
		$this->assertIndex_csrfToken(false);
	}

	/**
	 * This also effectively tests many checkCSRFToken() cases
	 */
	function assertIndex_csrfToken($requireCSRFToken){
		// This call also sets $_POST['redcap_csrf_token']
		\Authentication::setAAFCsrfToken(null);
		
		$token = $_POST['redcap_csrf_token'];
		
		$page = 'unit_test_page';
		$_GET['page'] = $page;
		$this->setPrefix(TEST_MODULE_PREFIX);

		$this->setConfig([
			'no-auth-pages' => [
				$page
			]
		]);

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_COOKIE['redcap_external_module_csrf_token'] = $token;
		$this->assertIndex(); // Valid token
		
		$expectedError = null;
		if($requireCSRFToken){
			$expectedError = ExternalModules::tt('em_errors_158');
		}
		
		$_GET['NOAUTH'] = '';
		$_POST['redcap_csrf_token'] = $token;
		$this->assertIndex(); // CSRF checking works on NOAUTH pages

		unset($_COOKIE['redcap_external_module_csrf_token']);
		$_POST['redcap_csrf_token'] = $token;
		$this->assertIndex($expectedError); // Posted token doesn't match cookie
		
		$expectedError = null;
		if($requireCSRFToken){
			$expectedError = ExternalModules::tt('em_errors_184', ExternalModules::getCurrentURL());
		}

		$this->assertIndex($expectedError);

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertIndex(); // CSRF checking is not required for GET requests

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertIndex($expectedError); // Make sure we're still failing before the next assertion

		$this->setConfig([
			'no-auth-pages' => [
				$page
			],
			'no-csrf-pages' => [
				$page
			]
		]);

		$this->assertIndex(); // CSRF tokens are not required on "no-csrf-pages"
	}

	function testRemoveLogs_returnValue(){
		$rowCount = 6;
		$message = 'foo';
		
		$this->setProjectId(TEST_SETTING_PID);

		for($i=0; $i<$rowCount; $i++){
			$this->log($message);
		}
		
		$this->assertSame(0, $this->removeLogs('?=?', [1, 2]));
		
		Framework::setRemoveLogsLimit(2);
		$this->assertSame(1, $this->removeLogs('message = ? limit 1', $message), 'Limit clause should override default limit');
		$this->assertSame(5, $this->removeLogs('message = ?', $message), 'All rows should still be deleted, even though it will take multiple delete queries');

		Framework::setRemoveLogsLimit(null);

		$this->setFrameworkVersion(9);
		$this->assertSame(true, $this->removeLogs('project_id = ?', -1));
	}

	function testLog_NOAUTH(){
		$this->assertLog_NOAUTH(true);

		$this->setFrameworkVersion(Framework::LOGGING_IMPROVEMENTS_FRAMEWORK_VERSION-1);
        $this->assertLog_NOAUTH(false);
    }

	function assertLog_NOAUTH($newBehavior){
		$_GET['NOAUTH'] = '';

		$assert = function(){
			$logId = $this->log('whatever');
			$this->assertIsInt($logId);
			$this->assertTrue($logId > 0);
		};

		/**
		 * MODULE_ENABLE_NOAUTH_LOGGING_SETTING is set to true by default for unit tests,
		 * so logging should always work by default.
		 */
		$assert();

		$this->setConfig([
			Framework::MODULE_ENABLE_NOAUTH_LOGGING_SETTING => false
		]);
		
		if(!$newBehavior){
			$assert();
		}
		else{
			$this->assertThrowsException(function(){
				$this->log('whatever');
			}, "The config.json setting '" . Framework::MODULE_ENABLE_NOAUTH_LOGGING_SETTING ."' must be set to 'true' in order to perform logging in a non-authenticated context.");
		}

		unset($_GET['NOAUTH']);
		$assert(); // Should always work in authenticated context.
	}

	private function assertGetData($recordIds, $fields, $filterLogic, $message = '', $repeatCount = 0){
		if(is_array($fields) && count($fields) === 1){
			// Test passing in single fields directly.
			$fields = $fields[0];
		}

		$result = $this->compareGetDataImplementations(TEST_SETTING_PID, 'json', $recordIds, $fields, null, null, false, false, false, $filterLogic);
		$expected = $result['php']['results'];
		$actual = $result['sql']['results'];

		$this->assertSame($expected, $actual, $message);
		$this->assertTrue($result['identical']);

		$expectedTime = $result['php']['execution-time'];
		$actualTime = $result['sql']['execution-time'];

		if(($actualTime-$expectedTime) > .1){
			if($repeatCount < 5){
				// Wait and try again.  Sometimes individual queries are much slower than they are on average (due to intermittent network or server load).
				sleep(1);
				$this->assertGetData($recordIds, $fields, $filterLogic, $message, $repeatCount+1);
			}
			else if (!function_exists('xdebug_get_profiler_filename') || xdebug_get_profiler_filename() === false){
				throw new \Exception("The new implementation took significantly longer ($actualTime seconds) than the old one ($expectedTime seconds).");
			}
		}

		return count($expected);
	}

	/**
     * @group slow
     */
	function testGetData(){
		$recordId1 = (string) rand();
		$recordId2 = $recordId1+1;
		$unusedRecordId = $recordId2+1;

		$expected = [
			[
				TEST_RECORD_ID => $recordId1,
				TEST_TEXT_FIELD => (string) rand()
			],
			[
				TEST_RECORD_ID => $recordId1,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => '1',
				TEST_REPEATING_FIELD_1 => (string) rand(),
				TEST_REPEATING_FIELD_2 => (string) rand()
			],
			[
				TEST_RECORD_ID => $recordId1,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM_2,
				'redcap_repeat_instance' => '1',
				TEST_REPEATING_FORM_2_FIELD_1 => (string) rand(),
			],
			[
				TEST_RECORD_ID => $recordId1,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM_2,
				'redcap_repeat_instance' => '2',
				TEST_REPEATING_FORM_2_FIELD_1 => (string) rand(),
			],
			[
				TEST_RECORD_ID => (string) $recordId2,
				TEST_TEXT_FIELD => (string) rand()
			],
			[
				TEST_RECORD_ID => $recordId2,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => '1',
				TEST_REPEATING_FIELD_1 => (string) rand(),
				TEST_REPEATING_FIELD_2 => (string) rand()
			],
			[
				TEST_RECORD_ID => $recordId2,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => '2',
				TEST_REPEATING_FIELD_1 => (string) rand(),
				TEST_REPEATING_FIELD_2 => (string) rand()
			],
			[
				TEST_RECORD_ID => $recordId2,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM_2,
				'redcap_repeat_instance' => '1',
				TEST_REPEATING_FORM_2_FIELD_1 => (string) rand(),
			],
		];

		$this->saveData($expected);

		$fieldGroups = [
			[TEST_RECORD_ID, TEST_TEXT_FIELD], // non-repeating
			[TEST_RECORD_ID, TEST_REPEATING_FIELD_1, TEST_REPEATING_FIELD_2], // first repeating form
			[TEST_RECORD_ID, TEST_REPEATING_FORM_2_FIELD_1] // second repeating form
		];

		$assert = function($recordIds, $fields, $filterLogic, $message, $expectedException = null){
			$rowCount = 0;
			$exception = null;
			try{
				$rowCount = $this->assertGetData($recordIds, $fields, $filterLogic, $message);
			}
			catch(\Exception $e){
				$exception = $e;
			}

			if($expectedException !== null){
				$this->assertStringContainsString($expectedException, $e->getMessage());
			}
			else if($exception !== null){
				var_dump([
					// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
					'testGetData $assert args' => func_get_args()
				]);
				
				throw $exception;
			}

			return $rowCount;
		};

		$assertionCount = 0;
		$rowCount = 0;

		$recordIdParameters = [
			null,
			$recordId1,
			$recordId2,
			$unusedRecordId,
			[$recordId1, $recordId2],
			[$recordId1, $unusedRecordId],
			[$recordId2, $unusedRecordId],
		];

		foreach($recordIdParameters as $recordIds){
			foreach($fieldGroups as $fields){
				foreach($expected as $row){
					foreach($fields as $whereField){
						$filterLogicOptions = [
							null
						];

						$isWhereFieldRepeating = in_array($whereField, [TEST_REPEATING_FIELD_1, TEST_REPEATING_FIELD_2, TEST_REPEATING_FORM_2_FIELD_1]);

						$value = $row[$whereField] ?? null;
						if($value === null && $isWhereFieldRepeating){		
							// Skip this case because REDCap::getData() will return top level record results even though that is misleading.
							// $module->getData() does not return rows in that case.
						}
						else{
							$filterLogicOptions[] = "[$whereField] = '$value'";

							if($whereField === TEST_REPEATING_FORM_2_FIELD_1){
								$repeatingField = TEST_REPEATING_FORM_2_FIELD_1;
							}
							else{
								$repeatingField = TEST_REPEATING_FIELD_1;
							}

							// Make sure empty string not equals logic for both repeating & non-repeating fields behaves as expected.
							$emptyStringNotLogic = "([" . TEST_TEXT_FIELD . '] != "" or [' . $repeatingField . '] != "")';

							$filterLogicOptions[] = "[$whereField] = '$value' and $emptyStringNotLogic";

							// Exclude cases where REDCap::getData() returns extraneous if not incorrect top level rows.
							if(!$isWhereFieldRepeating){
								$filterLogicOptions[] = "[$whereField] != '$value'";
								$filterLogicOptions[] = "[$whereField] != '$value' and $emptyStringNotLogic";
							}
						}

						foreach($filterLogicOptions as $filterLogic){
							$expectedException = null;
							if($filterLogic !== null && $whereField === TEST_RECORD_ID){
								$expectedException = ExternalModules::tt('em_errors_150');
							}

							try{
								$assertionCount++;
								$rowCount += $assert($recordIds, $fields, $filterLogic, "Selecting multiple fields (" . implode(', ', $fields) . ") with logic: $filterLogic", $expectedException);
			
								foreach($fields as $selectField){
									$assertionCount++;
									$rowCount += $assert($recordIds, [$selectField], $filterLogic, "Selecting the '$selectField' field with logic: $filterLogic", $expectedException);
								}
							}
							catch(\Exception $e){
								var_dump("Failed after $rowCount assertions.");
								throw $e;
							}
						}
					}
				}
			}
		}

		$this->assertSame(4718, $assertionCount, 'Make sure the nested loops above actually perform the expected number of assertions');
		$this->assertSame(3016, $rowCount, 'Make sure the nested loops above actually process the expected number of rows');
	}

	function testGetData_or(){
		$recordId1 = (string) rand();
		$recordId2 = $recordId1+1;

		$value1 = (string) rand();
		$value2 = (string) $value1+1;

		$this->saveData([
			[
				TEST_RECORD_ID => $recordId1,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => '1',
				TEST_REPEATING_FIELD_1 => $value1
			],
			[
				TEST_RECORD_ID => $recordId2,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => '1',
				TEST_REPEATING_FIELD_1 => $value2
			],
		]);		

		// Ensure the or clause does not cause record 2 to be returned because only record 1 is requested.
		$filterLogic = "[" . TEST_REPEATING_FIELD_1 . "] = $value1 or [" . TEST_REPEATING_FIELD_1 . "] = $value2";
		$recordIds = [$recordId1];
		$rowCount = $this->assertGetData($recordIds, TEST_RECORD_ID, $filterLogic);
		$this->assertSame(count($recordIds), $rowCount);
	}

	function testGetData_instanceSkippedAndlaterInstanceMissingFields(){
		$recordId = rand();

		$this->saveData([
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 1,
				TEST_REPEATING_FIELD_1 => 1,
				TEST_REPEATING_FIELD_3 => 2,
			],
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 3,
				TEST_REPEATING_FIELD_1 => 1
			],
		]);

		$rowCount = $this->assertGetData($recordId, [TEST_RECORD_ID, TEST_REPEATING_FIELD_1, TEST_REPEATING_FIELD_2, TEST_REPEATING_FIELD_3], "1 = 1");
		$this->assertSame(3, $rowCount);

		$this->saveData([
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 3,
				TEST_REPEATING_FIELD_3 => 2,
			],
		]);

		$rowCount = $this->assertGetData($recordId, [TEST_RECORD_ID, TEST_REPEATING_FIELD_1, TEST_REPEATING_FIELD_2, TEST_REPEATING_FIELD_3], "1 = 1");
		$this->assertSame(3, $rowCount);
	}

	function testGetData_comparingRepeatingAndNonRepeatingFields(){
		// If our joins aren't just right, non-repeating values could default to the empty string or null on repeating rows,
		// changing the meaning of comparisons.  This test asserts one such case.

		$recordId = rand();

		$this->saveData([
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => 3
			],
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 1,
				TEST_REPEATING_FIELD_1 => 2
			],
		]);

		$fieldSets = [
			[TEST_RECORD_ID],
			[TEST_REPEATING_FIELD_1],
			[TEST_TEXT_FIELD],
			[TEST_TEXT_FIELD, TEST_REPEATING_FIELD_1],
		];

		foreach($fieldSets as $fieldSet){
			$this->assertGetData([], $fieldSet, '[' . TEST_TEXT_FIELD . '] > [' . TEST_REPEATING_FIELD_1 . ']');
		}
	}

	function testGetData_repeatingFieldQueriedButNotSet(){
		$this->saveData([
			[
				TEST_RECORD_ID => rand(),
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => rand()
			],
		]);

		$this->assertGetData([], [TEST_TEXT_FIELD, TEST_REPEATING_FIELD_1], "1 = 1");
	}

	function testGetData_emptyRepeatingAndNonRepeatingFields(){
		$recordId = rand();

		$this->saveData([
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => rand()
			],
			[
				TEST_RECORD_ID => $recordId,
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 1,
				TEST_REPEATING_FIELD_1 => rand()
			],
		]);

		$this->assertGetData([], [TEST_TEXT_FIELD, TEST_REPEATING_FIELD_1], "1 = 1");
	}

	function testGetData_notLogic(){
		// The $module->getData() method is a little different than REDCap::getData() in this case.
		// REDCap misleadingly returns some record level rows that logically should not match.		

		$data = [
			[
				TEST_RECORD_ID => '1',
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 1,
				TEST_REPEATING_FIELD_1 => '1'
			],
			[
				TEST_RECORD_ID => '2',
				'redcap_repeat_instrument' => TEST_REPEATING_FORM,
				'redcap_repeat_instance' => 1,
				TEST_REPEATING_FIELD_1 => '2'
			]
		];

		$this->saveData($data);

		$expected = [
			$data[0]
		];
		
		$pid = TEST_SETTING_PID;
		$fields = [
			TEST_RECORD_ID,
			TEST_REPEATING_FIELD_1
		];

		$filterLogic = '[' . TEST_REPEATING_FIELD_1 . '] != "2"';

		$actual = json_decode($this->getData($pid, 'json', null, $fields, null, null, false, false, false, $filterLogic), true);
		
		$this->assertSame($expected, $actual);
	}

	function testGetData_v1(){
		$assert = function($methodName){
			$expected = [
				TEST_RECORD_ID => (string) rand(),
				TEST_TEXT_FIELD => (string) rand()
			];
	
			$this->saveData([$expected]);
	
			$actual = json_decode($this->getModuleInstance()->$methodName(TEST_SETTING_PID, $expected[TEST_RECORD_ID], '', 'json'), true)[0];
	
			$actual = [
				TEST_RECORD_ID => $actual[TEST_RECORD_ID],
				TEST_TEXT_FIELD => $actual[TEST_TEXT_FIELD]
			];
	
			$this->assertSame($expected, $actual);
		};

		$assert('getData_v1');
		
		$this->setFrameworkVersion(6);
		$assert('getData');
	}

	function testGetData_exceptions(){
		$this->setProjectId(TEST_SETTING_PID);

		$this->assertThrowsException(function(){
			$this->getData(1, 'array');
		}, ExternalModules::tt('em_errors_147'));

		$this->assertThrowsException(function(){
			$this->getData(1, 'json', null, [TEST_TEXT_FIELD], 123);
		}, ExternalModules::tt('em_errors_149', 'null', 'events'));

		$this->assertThrowsException(function(){
			$this->assertGetData(null, [TEST_TEXT_FIELD], '[');
		}, 'Unable to find next token');

		$this->assertThrowsException(function(){
			$this->assertGetData(null, null, '[');
		}, ExternalModules::tt('em_errors_148'));

		$invalidFieldName = 'invalid_char!';
		$this->assertThrowsException(function() use ($invalidFieldName){
			$this->assertGetData(null, $invalidFieldName, null);
		}, ExternalModules::tt('em_errors_153', $invalidFieldName));
	}

	function testGetData_simpleNotLogicWithoutARepeatingInstance(){
		$this->saveData([
			[
				TEST_RECORD_ID => '1',
				'redcap_repeat_instrument' => '',
				'redcap_repeat_instance' => '',
				TEST_TEXT_FIELD => '1'
			]
		]);

		$this->assertGetData([], TEST_RECORD_ID, '[' . TEST_REPEATING_FIELD_1 . '] != "2"');
	}
	
	function testGetData_datediff(){
		$this->saveData([[
			TEST_RECORD_ID => rand(),
			'redcap_repeat_instrument' => '',
			'redcap_repeat_instance' => '',
			TEST_TEXT_FIELD => '2020-01-01'
		]]);

		$assert = function($filterLogic){
			$rowCount = $this->assertGetData([], TEST_RECORD_ID, $filterLogic);
			$this->assertSame(1, $rowCount, 'The filter logic did not match the saved data!');
		};

		// REDCap considers a year to be or 31556952 seconds (365.2425 days) which comes out to the following in 2020 (since it's a leap year).
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-12-31 05:49:12', 'y') = 1");

		// REDCap considers a month to be 2630016 seconds (30.44 days) which comes out the following.
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-31 10:33:36', 'M') = 1");
		
		// Other units
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-02', 'd') = 1");
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-01 01:00', 'h') = 1");
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-01 00:01', 'm') = 1");
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-01 00:00:01', 's') = 1");

		// Signed values
		$assert("datediff('2020-01-02', '2020-01-01', 'd', false) = 1");
		$assert("datediff('2020-01-02', '2020-01-01', 'd', true) = -1");

		// now
		$date = (new DateTime())->format('Y-m-d H:i:s');
		$assert("datediff('$date', 'now', 'm') < 1");

		// today
		$date = (new DateTime())->format('Y-m-d');
		$assert("datediff('$date 00:01', 'today', 'm') = 1");

		// Fractional results
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-02', 'y') = 1/365.2425");
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-02', 'M') = 1/30.44");
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-01 01:00', 'd') = 1/24");
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-01 00:01', 'h') = 1/60");
		$assert("datediff([".TEST_TEXT_FIELD."], '2020-01-01 00:00:01', 'm') = 1/60");

		/**
		 * We do not have to test comparisons to empty field values because
		 * they are already ambiguous and risky in REDCap::getData() calls.
		 * They should be clarified using "[field_name] = ''" checks in the logic anyway.
		 */
	}

	function testGetData_duplicateDataRows(){
		$recordId = rand();

		// Add the record
		$this->saveData([[
			TEST_RECORD_ID => $recordId
		]]);

		$table = \Records::getDataTable(TEST_SETTING_PID);
		$insertSql = "insert into $table values (?, ?, ?, ?, ?, ?)";
		$args = [TEST_SETTING_PID, $this->getEventId(TEST_SETTING_PID), $recordId, TEST_TEXT_FIELD, 'foo', null];

		// Insert two rows
		$this->query($insertSql, $args);
		$this->query($insertSql, $args);

		// Ensure that the DISTINCT keyword works and prevents duplicate rows
		$rowCount = $this->assertGetData([], TEST_TEXT_FIELD, null);
		$this->assertSame(1, $rowCount);
	}

	function testQueryLogs_parametersArgumentRequirement()	{
		$this->assertThrowsException(function(){
			// Omitting the parameters argument should throw an exception
			$this->queryLogs('select 1');
		}, ExternalModules::tt('em_errors_117'));

		// On older framework versions, parameters are not required.
		$this->setFrameworkVersion(5);
		$this->queryLogs("select 1");
	}

	function testQueryLogs_usernameInWhere()
	{
		// this ensures that "as username" is not appended in where clauses
		$result = $this->queryLogs('select username where log_id = ? and username = "foo" ', [-1]);
		$this->assertNull($result->fetch_assoc());
	}

	function testRemoveLogs_parametersArgumentRequirement()
	{
		$this->assertThrowsException(function(){
			// Omitting the parameters argument should throw an exception
			$this->removeLogs('project_id = -1');
		}, ExternalModules::tt('em_errors_117'));

		// On older framework versions, parameters are not required.
		$this->setFrameworkVersion(5);
		$this->removeLogs("project_id = -1");
	}

	function testSetProjectSettings(){
		$assert = function($shouldWork){
			// Run against the module instance rather than the framework instance, even prior to v5.
			$m = $this->getInstance();
	
			$this->setProjectId(TEST_SETTING_PID);
	
			$value = rand();
			$m->setProjectSettings([
				TEST_SETTING_KEY => $value
			]);

			$actual = $m->getProjectSetting(TEST_SETTING_KEY);
	
			if($shouldWork){
				$this->assertSame($value, $actual);
			}
			else{
				$this->assertNotSame($value, $actual);
			}
		};

		$assert(true);

		$this->setFrameworkVersion(4);
		$assert(false);
	}

	function testGetProjectSettings(){
		$assert = function($newBehavior){
			// Run against the module instance rather than the framework instance, even prior to v5.
			$m = $this->getInstance();

			$this->setProjectId(TEST_SETTING_PID);

			$value = rand();
			$this->setProjectSetting($value);
			$array = $m->getProjectSettings();

			$actual = $array[TEST_SETTING_KEY];

			if(!$newBehavior){
				$this->assertFalse(isset($actual['system_value']));
				$actual = $actual['value'];
			}

			$this->assertSame($value, $actual);
		};

		$assert(true);

		$this->setFrameworkVersion(4);
		$assert(false);
	}

	function testGetSubSettings_complexNesting()
	{
		$assert2 = function($newBehavior){
			$m = $this->getInstance();
			$this->setProjectId(TEST_SETTING_PID);

			// This json file can be copied into a module for hands on testing/modification via the settings dialog.
			$this->setConfig(json_decode(file_get_contents(__DIR__ . '/complex-nested-settings.json'), true));

			// These values were copied directly from the database after saving them through the settings dialog (as configured by the json file above).
			$m->setProjectSetting('countries', ["true","true"]);
			$m->setProjectSetting('country-name', ["USA","Canada"]);
			$m->setProjectSetting('states', [["true","true"],["true"]]);
			$m->setProjectSetting('state-name', [["Tennessee","Alabama"],["Ontario"]]);
			$m->setProjectSetting('cities', [[["true","true"],["true"]],[["true"]]]);
			$m->setProjectSetting('city-name', [[["Nashville","Franklin"],["Huntsville"]],[["Toronto"]]]);
			$m->setProjectSetting('city-size', [[["large","small"],["medium"]],[[null]]]); // The null is an important scenario to test here, as it can change output behavior.

			$assert = function($actualCountries, $newImplementation){
				if($newImplementation){
					$states = [
						[
							"state-name" => "Tennessee",
							"cities" => [
								[
									"city-name" => "Nashville",
									"city-size" => "large"
								],
								[
									"city-name" => "Franklin",
									"city-size" => "small"
								]
							]
						],
						[
							"state-name" => "Alabama",
							"cities" => [
								[
									"city-name" => "Huntsville",
									"city-size" => "medium"
								]
							]
						]
					];
		
					$provinces = [
						[
							"state-name" => "Ontario",
							"cities" => [
								[
									"city-name" => "Toronto",
									"city-size" => null
								]
							]
						]
					];
				}
				else{
					$states = ['true', 'true'];
					$provinces = ['true'];
				}
				
				$expectedCountries = [
					[
						"states" => $states,
						"country-name" => "USA"
					],
					[
						"states" => $provinces,
						"country-name" => "Canada"
					]
				];
				$this->assertEquals($expectedCountries, $actualCountries);
			};

			$assert($this->getFramework()->getSubSettings('countries'), true);
			$assert($this->getFramework()->getSubSettings_v1('countries'), false);
			$assert($m->getSubSettings('countries'), $newBehavior);
		};

		$assert2(true);

		$this->setFrameworkVersion(4);
		$assert2(false);
	}

	function testGetSubSettings_settingMovedToSubSetting(){	
		$settingsConfig = [
			[
				'key' => TEST_SETTING_KEY
			]
		];

		$this->setConfig([
			'project-settings' => $settingsConfig
		]);

		$value = rand();
		$this->setProjectSetting($value);

		$this->assertSame($value, $this->getProjectSetting());

		$this->setConfig([
			'project-settings' => [
				[
					'key' => 'level-1',
					'type' => 'sub_settings',
					'sub_settings' => $settingsConfig
				]
			]
		]);

		$this->assertSame([[TEST_SETTING_KEY => $value]], $this->getSubSettings('level-1', TEST_SETTING_PID));
	}

	function testGetSubSettings_hidden()
	{
		$this->setProjectId(TEST_SETTING_PID);
		
		$subSettingsKey = 'sub-settings-key';
		$hiddenKey = 'hidden-key';
		$this->setConfig([
			'project-settings' => [
				[
					'key' => $subSettingsKey,
					'type' => 'sub_settings',
					'repeatable' => true,
					'sub_settings' => [
						[
							'key' => $hiddenKey,
							'type' => 'hidden'
						]
					]
				]
			]
		]);

		$values = [rand(), rand()];
		$this->setProjectSetting($hiddenKey, $values);

		$expected = [];
		foreach($values as $value){
			$expected[] = [
				'hidden-key' => $value
			];
		}

		$this->assertSame($expected, $this->getSubSettings($subSettingsKey));
	}

	function testIsSafeToForwardMethodToFramework(){
		$assert = function($passThroughAllowed){
			// The 'tt' methods are grandfathered in.
			$this->assertTrue($this->isSafeToForwardMethodToFramework('tt'));

			// This assertion specifically checks the method_exists() call in isSafeToForwardMethodToFramework()
			// to ensure infinite loops cannot occur.
			$this->assertThrowsException(function(){
				$this->getInstance()->someNonExistentMethod();
			}, 'method does not exist');

			$this->assertSame($passThroughAllowed, $this->isSafeToForwardMethodToFramework('getRecordIdField'));

			$methodName = 'getRecordIdField';
			$passThroughCall = function() use ($methodName){
				$this->getInstance()->{$methodName}(TEST_SETTING_PID);
			};
			
			if($passThroughAllowed){
				// Make sure no exception is thrown.
				$passThroughCall();
			}
			else{
				$this->assertThrowsException(function() use ($passThroughCall){
					$passThroughCall();
				}, ExternalModules::tt("em_errors_69", $methodName));
			}
		};

		$assert(true);

		$this->setFrameworkVersion(4);
		$assert(false);
	}

	function testGetSQLInClause(){
		// This method is tested more thoroughly in ExternalModulesTest.

		$assert = function($newBehavior){
			$getSQLInClause = function(){
				$clause = $this->getSQLInClause('a', [1]);
				$this->assertSame("(a IN ('1'))", $clause);
			};
	
			if(!$newBehavior){
				$getSQLInClause();
			}
			else{
				$this->assertThrowsException(function() use ($getSQLInClause){
					$getSQLInClause();
				}, ExternalModules::tt('em_errors_122'));
			}
		};

		$assert(true);

		$this->setFrameworkVersion(3);
		$assert(false);
	}

	function testQuery_noParameters(){
		$assert = function($newBehavior){
			$value = (string)rand();
			$result = $this->query("select $value", []);
			$row = $result->fetch_row();
			$this->assertSame($value, $row[0]);

			if(!$newBehavior){
				$value = (string)rand();
				$result = $this->query("select $value");
				$row = $result->fetch_row();
				$this->assertSame($value, $row[0]);	
			}
			else{
				$this->assertThrowsException((function(){
					$this->query("select 1");
				}), ExternalModules::tt('em_errors_117'));
			}
		};

		$assert(true);

		$this->setFrameworkVersion(3);
		$assert(false);
	}

	function testProject_getUsers(){
		$assert2 = function($newBehavior){
			$assert = function($actualUsers){
				$this->assertNotEmpty($actualUsers);

				$result = $this->getFramework()->query("
					select user_email
					from redcap_user_rights r
					join redcap_user_information i
						on r.username = i.username
					where project_id = ?
					order by r.username
				", TEST_SETTING_PID);

				$i = 0;
				while($row = $result->fetch_assoc()){
					$user = $actualUsers[$i];
					$username = $user->getUsername();
					$email = $actualUsers[$i]->getEmail();

					if($email === null){
						// For some reason some accounts have empty emails on REDCap Test
						continue;
					}

					$this->assertSame($row['user_email'], $email);
					$i++;
				}
			};

			$username = $this->getRandomUsername();
			$project = $this->getProject(TEST_SETTING_PID);

			$project->removeUser($username);
			$project->addUser($username);

			$actualUsers = $project->getUsers();

			$assert($actualUsers);

			$this->setProjectId(TEST_SETTING_PID);
			if($newBehavior){
				// Make sure callable from framework object directly.
				// This assertion currently covers all method forwards from the framework to project object (not just getUsers()).
				$assert($this->getFramework()->getUsers());
			}
			else{
				$this->assertThrowsException(function(){
					$this->getFramework()->getUsers();
				}, 'Call to undefined method: getUsers');
			}

			/**
			 * This probably isn't the most appropriate location for this assertion,
			 * but I like having it close to the forward related assertions above.
			 */
			$this->assertThrowsException(function(){
				$this->getFramework()->someMethodThatDoesNotExist();
			}, 'Call to undefined method: someMethodThatDoesNotExist');

			$project->removeUser($username);
		};

		$assert2(true);

		$this->setFrameworkVersion(6);
		$assert2(false);
	}

	function testProject_getFormsForEventId(){
		$p = $this->getProject(TEST_SETTING_PID);
		$this->assertSame([
			TEST_FORM,
			TEST_REPEATING_FORM,
			TEST_REPEATING_FORM_2
		], $p->getFormsForEventId($p->getEventId()));
		
		$p = $this->getProject(TEST_SETTING_PID_2);
		$this->assertSame([
			TEST_FORM,
			TEST_REPEATING_FORM,
			TEST_REPEATING_FORM_2
		], $p->getFormsForEventId($this->getEventIds(TEST_SETTING_PID_2)[0]));
	}

	function testIsSafeToForwardMethodToFramework_projectForwards(){
		$assert = function($newBehavior){
			$projectMethodName = 'getUsers';

			$action = function() use ($projectMethodName){
				$this->setProjectId(TEST_SETTING_PID);
				$m = $this->getInstance();
				$m->{$projectMethodName}();
			};

			if($newBehavior){
				$action(); // Ensure no exception thrown
			}
			else{
				$this->assertThrowsException($action, ExternalModules::tt("em_errors_69", $projectMethodName));
			}
		};

		$assert(true);

		$this->setFrameworkVersion(6);
		$assert(false);
	}

	function testGetLinkIconHtml(){
		$assert = function($newBehavior){
			$iconName = 'fas fa-whatever';
			$link = ['icon' => $iconName];
			$html = $this->getLinkIconHtml($link);

			if(!$newBehavior){
				$expected = "<img src='" . APP_PATH_IMAGES . "$iconName.png'";
			}
			else{
				$expected = "<i class='$iconName'";
			}

			$this->assertTrue(strpos($html, $expected) > 0, "Could not find '$expected' in '$html'");
		};

		$assert(true);

		$this->setFrameworkVersion(2);
		$assert(false);
	}

	/**
	 * This test ensures that no changes have been made to the constants that the javascript login page detection feature depends on.
	 */
	function testLoginPageDetection(){
		$this->assertSame(1, substr_count(
			file_get_contents(APP_PATH_DOCROOT . 'Classes/HtmlPage.php'),
			'print   \'' . Framework::REDCAP_PAGE_PREFIX . '\' . "\n"'
		));
		
		$this->assertSame(1, substr_count(
			file_get_contents(APP_PATH_DOCROOT . 'Config/init_functions.php'),
			Framework::LOGIN_PAGE_EXCERPT
		));
	}

	function testEscape(){
		$object = new class{
			function __toString(){
				return 'custom class >';
			}
		};

		$resource = fopen('php://temp', 'r');

		$examples = [
			[true],
			[1],
			[1.1],
			['a'],
			['<a', '&lt;a'],
			[['b"' => 'c\''], ['b&quot;' => 'c&#039;']], // string indexed arrays
			[['d&'], ['d&amp;']], // numerically indexed arrays
			[[['e&']], [['e&amp;']]], // nested arrays
			[$object, 'custom class &gt;'],
			[$resource, ''.$resource],
			[null],
		];

		foreach($examples as $values){
			$input = $values[0];
			$expected = $values[1] ?? $input;

			$this->assertSame($expected, $this->escape($input));
		}
	}

	function testGetProjectSetting_overrideSystemValues(){
		$systemValue = rand();
		$this->setSystemSetting($systemValue);
		$this->assertSame($systemValue, $this->getSystemSetting());
		$this->assertSame($systemValue, $this->getProjectSetting());

		$projectValue = rand();
		$this->setProjectSetting($projectValue);
		$this->assertSame($systemValue, $this->getSystemSetting());
		$this->assertSame($projectValue, $this->getProjectSetting());
	}

	function testIsAuthenticated(){
		$this->assertFalse($this->isAuthenticated());
		ExternalModules::setUsername('foo');
		$this->assertTrue($this->isAuthenticated());
		$_GET['NOAUTH'] = '';
		$this->assertFalse($this->isAuthenticated());
	}
	
	function testRedirectAfterHook(){
		$exited = null;
		ExternalModules::$exitAfterHookAction = function() use (&$exited){
			$exited = true;
		};

		$url = "https://some.test.url";
		
		$assert = function($action, $exitExpected, $expectedOutput) use (&$exited){
			$exited = false;

			ob_start();
			ExternalModules::callHook('redcap_test_call_function', [$action]);
			$actualOutput = ob_get_clean();

			$this->assertSame($exitExpected, $exited);
			$this->assertSame($expectedOutput, $actualOutput);
		};

		$assert(function(){
			// do nothing
		}, false, '');

		$assert(function() use ($url){
			$this->redirectAfterHook($url);
		}, true, '');

		$assert(function() use ($url){
			$this->redirectAfterHook($url, true);
		}, true, "<script type=\"text/javascript\">window.location.href=\"$url\";</script>");

		$assert(function(){
			// Test escaping
			$this->redirectAfterHook("<", true);
		}, true, "<script type=\"text/javascript\">window.location.href=\"&lt;\";</script>");
	}

	function testGetDataTable(){
		$this->assertThrowsException(function(){
			$this->getDataTable();
		}, ExternalModules::tt('em_errors_65', 'pid'));

		$this->setProjectId(TEST_SETTING_PID);
		$this->assertSame(TEST_DATA_TABLE, $this->getDataTable());
		$this->assertSame(TEST_DATA_TABLE, $this->getDataTable(TEST_SETTING_PID_2));
	}

	function testReplaceDataTableVar(){
		$assert = function($sql, $expected){
			$prefix = (string) rand();
			$suffix = (string) rand();
			$sql = "$prefix $sql $suffix";
			$expected = "$prefix $expected $suffix";

			$actual = $this->replaceDataTableVar(TEST_SETTING_PID, $sql);
			$this->assertSame($expected, $actual);
		};

		$testDataTable1 = $this->getDataTable(TEST_SETTING_PID);
		
		$pid2 = TEST_SETTING_PID_2;
		$pid3 = TEST_SETTING_PID_3;
		$testDataTable2 = 'redcap_data' . rand();
		$testDataTable3 = 'redcap_data' . rand();
		
		$this->setDataTableCache([
			$pid2 => $testDataTable2,
			$pid3 => $testDataTable3,
		]);

		$assert(
			"[data-table] [data-table] [data-table:$pid2] [data-table:$pid2] [data-table:$pid3] [data-table:$pid3]",
			$testDataTable1 . ' ' . $testDataTable1 . ' ' . $testDataTable2 . ' ' . $testDataTable2  . ' ' . $testDataTable3 . ' ' . $testDataTable3
		);

		$this->setDataTableCache([]);
	}

	function setDataTableCache($value){
		$this->setPrivateVariable('dataTableCache', $value, 'Records');
	}

	function testValidateS3URL(){
		$assert = function($url, $expectException){
			try{
				$this->assertSame($url, $this->validateS3URL($url));
				$this->assertFalse($expectException);
			}
			catch(\Exception $e){
				$this->assertStringContainsString(ExternalModules::tt('em_errors_179', $url), $e->getMessage());
				$this->assertTrue($expectException);
			}
		};

		$assert('https://whatever.s3.amazonaws.com', false);
		$assert('http://whatever.s3.amazonaws.com', false);
		$assert('s3://whatever.s3.amazonaws.com', false);
		$assert('https://s3.amazonaws.com/whatever', false);
		$assert('https://some-other-url.com', true);

		/**
		 * It is important that we guarantee that this method does not work on file paths
		 * because S3 urls could be passed into file_get_contents(), which also works on file paths.
		 */
		$assert('/malicious/path', true);
		$assert('../../../malicious/path', true);
	}

	function testGetDataClassical() {
		$rowsEqual = function(array $row1, array $row2): bool {
    			foreach ($row1 as $field1 => $value1) {
        			$value2 = $row2[$field1] ?? "";
        			if ("$value1" !== "$value2"){
            			return FALSE;
        			}
    			}
    			foreach ($row2 as $field2 => $value2) {
        			$value1 = $row1[$field2] ?? "";
        			if ("$value1" !== "$value2"){
            			return FALSE;
        			}
    			}
    			return TRUE;
		};

		$pid = TEST_SETTING_PID;
                $this->setProjectId(TEST_SETTING_PID);
		$fields = [$this->getRecordIdField($pid), TEST_TEXT_FIELD, TEST_RADIO_FIELD, TEST_YESNO_FIELD, TEST_CALC_FIELD, TEST_REPEATING_FIELD_1, TEST_REPEATING_FIELD_2, TEST_REPEATING_FIELD_3];
		$recordIds = [1, 2];
		$getDataValues = json_decode(\REDCap::getData($pid, 'json', $recordIds, $fields), true);
		$newGetDataValues = $this->getFramework()->getDataClassical($pid, $fields, $recordIds);

		foreach ($getDataValues as $getDataRow) {
    			$found = FALSE;
    			foreach ($newGetDataValues as $queryDataRow) {
        			if ($rowsEqual($getDataRow, $queryDataRow)) {
            				$found = TRUE;
            				break;
        			}
    			}
			$this->assertTrue($found);
		}
		
		$exceptionText = ExternalModules::tt('em_errors_180');
		$this->assertThrowsException(function(){
			$pid = TEST_SETTING_PID;
			$recordIds = [1, 2];
			$this->getFramework()->getDataClassical($pid, NULL, $recordIds);
		}, $exceptionText);
		$this->assertThrowsException(function(){
			$pid = TEST_SETTING_PID;
			$fields = [$this->getRecordIdField($pid), TEST_TEXT_FIELD, TEST_RADIO_FIELD, TEST_YESNO_FIELD, TEST_CALC_FIELD, TEST_REPEATING_FIELD_1, TEST_REPEATING_FIELD_2, TEST_REPEATING_FIELD_3];
			$this->getFramework()->getDataClassical($pid, $fields, NULL);
		}, $exceptionText);
	}
	
	function testGetSelectedCheckboxes(){
		$data = [
			'field_a___1' => true,
			'field_a___2' => true,
			'field_b___3' => true,
			'field_b___4' => true,
		];

		$this->assertSame(['1','2'], $this->getSelectedCheckboxes($data, 'field_a'));
		$this->assertSame(['3','4'], $this->getSelectedCheckboxes($data, 'field_b'));
	}
}
