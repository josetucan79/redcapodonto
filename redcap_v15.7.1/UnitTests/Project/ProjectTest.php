<?php

namespace Vanderbilt\REDCap\Tests\Project;

use PHPUnit\Framework\TestCase;

class ProjectTest extends TestCase
{
    protected $baseTestPath;

    protected $downloadsDir;

    protected $fileManager;

    protected $httpClient;

	protected static $testProjectIds;

    public static $existingArmId = null;

    public static $existingEventId = null;

    public static $redcapEventsMetadata = [];

    public static $redcapEventsArms = [];

    public static $redcapEventsForms = [];

    public static $surveysEnabled = [];

    /**
     * @throws \Exception
     */
    protected function setUp(): void
	{
		db_connect(false);
        // run test within transaction so can be rolled back at end of test and leave database unmodified, ready for next test.
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");
        $this->baseTestPath = realpath(\APP_PATH_DOCROOT . 'UnitTests');
        // set downloads directory
        $this->downloadsDir = realpath(\APP_PATH_TEMP) . '/downloads';
        if (!is_dir($this->downloadsDir)) {
            if (!mkdir($this->downloadsDir)) {
                throw new \Exception("write permission needed on " . realpath(\APP_PATH_TEMP));
            }
        }
        $this->fileManager = new class extends \FileManager {
            public static $filePathDownload;

            public static function forceDownload($filename, $data)
            {
                self::$filePathDownload = realpath(\APP_PATH_TEMP) . '/downloads/' . $filename;
                file_put_contents(self::$filePathDownload, $data);
            }

        };
        $this->httpClient = new class () extends \HttpClient {
            public static function printJSON($response, $status_code=200)
            {
                print json_encode_rc( $response );
            }
        };
        \FileManager::setInstance($this->fileManager);
	}

	/**
	 * This test exists solely to avoid the warning that no tests exist in this class.
	 */
	public function testAvoidWarning(){
		$this->markTestIncomplete();
	}

    /**
     * @return mixed
     * @throws \Exception
     *
     *  \ExternalModules\ExternalModules::getTestPIDs() by default creates a project with 1 arm and 1 event; if \ExternalModules\ExternalModules::getTestPIDs() is updated to create more default arms and/or events then
     *  code in this method must also be updated accordingly.
     */
	public static function getTestProjectID1()
	{
		if(!isset(static::$testProjectIds)){
			/**
			 * We might as well re-use the test projects that the module framework has been using for years.
			 */
			static::$testProjectIds = \ExternalModules\ExternalModules::getTestPIDs();
		}
        $project_id = static::$testProjectIds[0];

        // 1 arm and 1 event was inserted by default upon project creation
        $sql1 = "SELECT arm_id FROM redcap_events_arms WHERE project_id=$project_id ORDER BY arm_id DESC LIMIT 1";
        $q1 = db_query($sql1);
        if (!self::$existingArmId) {
            self::$existingArmId = db_result($q1, 0, 'arm_id');
        }

        $sql2 = "SELECT event_id FROM redcap_events_metadata WHERE arm_id=". self::$existingArmId . " ORDER BY event_id DESC LIMIT 1;";
        $q2 = db_query($sql2);
        if (!self::$existingEventId) {
            self::$existingEventId = db_result($q2, 0, 'event_id');
        }
        if (!in_array([self::$existingEventId, self::$existingArmId], self::$redcapEventsMetadata)) {
            self::$redcapEventsMetadata[] = [self::$existingEventId, self::$existingArmId];
        }
        if (!in_array([self::$existingArmId, $project_id], self::$redcapEventsArms)) {
            self::$redcapEventsArms[] = [self::$existingArmId, $project_id];
        }
		return $project_id;
	}

	public static function setFieldNames($project_id, $form_name)
	{	
		foreach(MetaDataTest::get($project_id) as $f)
		{
			MetaDataTest::update($f['project_id'], $f['field_name'], 'form_name', $form_name);
		}
	}
	
	public static function updateFieldValue($project_id, $field, $value)
	{
		$sql = "
			UPDATE redcap_projects
			SET $field = '$value'
			WHERE project_id = $project_id
			LIMIT 1
		";
		$q = db_query($sql);
        if ($q && $q !== false) {
            if ($field == 'surveys_enabled') {
                if ($value == 1) {
                    self::$surveysEnabled[$project_id] = true;
                } else {
                    self::$surveysEnabled[$project_id] = false;
                }
            }
            return db_affected_rows();
        }
		return 0;
	}

    protected static function rollBack()
    {
        db_query("ROLLBACK");
        db_query("SET AUTOCOMMIT=1");
    }


    protected function tearDown(): void
    {
        parent::tearDown();
        self::rollBack();
    }

    // tests
	/**
	public function testNumEventsFormDesignated()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$form_name = hashStr();
		EventFormTest::create($event_id, $form_name);
		ProjectTest::setFieldNames($project_id, $form_name);
		
		$project = new Project($project_id);

		$this->assertEquals($form_name, $project->eventsForms[$event_id][0]);
		$this->assertEquals(1, $project->numEventsFormDesignated($form_name));
	}
	
	public function testIsRepeatingFormsEvent()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$form_name = hashStr();
		EventRepeatTest::create($event_id, $form_name);
		
		$project = new Project($project_id);
		$GLOBALS['Proj'] = $project;

		$this->assertTrue($project->isRepeatingFormOrEvent($event_id, $form_name));

		unset($GLOBALS['Proj']);
	}
	
	public function testGetAttributesApiExportProjectInfo()
	{
		$this->assertEquals('project_id', Project::getAttributesApiExportProjectInfo()['project_id']);
	}
	
	public function testGetRepeatingFormsEvents()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$form_name = hashStr();
		EventRepeatTest::create($event_id, $form_name);
		
		$project = new Project($project_id);
		$GLOBALS['Proj'] = $project;

		$this->assertEquals(true, isset($project->getRepeatingFormsEvents()[$event_id][$form_name]));

		unset($GLOBALS['Proj']);
	}
	
	public function testGetExtendedCheckboxFieldname()
	{
		$this->assertEquals(
			'field_name___raw_coded_value',
			Project::getExtendedCheckboxFieldname('field_name', 'raw_Coded!-value')
		);
	}
	
	public function testGetProjGetEventsByArmNum()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);

		$this->assertEquals($event_id, $project->getEventsByArmNum('2')[0]);
	}
	
	public function testGetProjGetNextField()
	{
		$project_id = ProjectTest::getTestProjectID1();

		MetaDataTest::create($project_id, 'another_field', 2);

		$fields = MetaDataTest::get($project_id);

		$f1 = $fields[0];
		$f2 = $fields[1];

		$project = new Project($project_id);

		$this->assertEquals($f2['field_name'], $project->getNextField($f1['field_name']));
	}

	public function testGetProjGetPrevField()
	{
		$project_id = ProjectTest::getTestProjectID1();

		MetaDataTest::create($project_id, 'another_field', 2);

		$fields = MetaDataTest::get($project_id);

		$f1 = $fields[0];
		$f2 = $fields[1];

		$project = new Project($project_id);

		$this->assertEquals($f1['field_name'], $project->getPrevField($f2['field_name']));
	}

	public function testGetProjMatrixGroupName()
	{
		$project_id = ProjectTest::getTestProjectID1();

		MetaDataTest::create($project_id, 'another_field1', 3);
		MetaDataTest::create($project_id, 'another_field2', 4);
		MetaDataTest::create($project_id, 'another_field3', 5);

		$fields = MetaDataTest::get($project_id);

		$f1 = $fields[1];
		$f2 = $fields[0];
		$f3 = $fields[2];

		$same  = hashStr();

		MetaDataTest::update($f1['project_id'], $f1['field_name'], 'grid_name', hashStr());
		MetaDataTest::update($f2['project_id'], $f2['field_name'], 'grid_name', $same);
		MetaDataTest::update($f3['project_id'], $f3['field_name'], 'grid_name', $same);

		$project = new Project($project_id);

		// TODO: fix random failures
		//$this->assertGreaterThan(0, $project->fixOrphanedMatrixFields());
	}

	public function testGetProjIsFirstEventIdInArmInvalidEventId()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertFalse($project->isFirstEventIdInArm(8675309));
	}

	public function testGetProjIsFirstEventIdInArm()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertTrue($project->isFirstEventIdInArm($event_id));
	}
	
	public function testGetProjFirstEventIdFromInvalidArmId()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertGreaterThan(0, $project->getFirstEventIdArmId(8675309));
		$this->assertLessThan($event_id, $project->getFirstEventIdArmId(8675309));
	}
	
	public function testGetProjFirstEventIdFromArmId()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals($event_id, $project->getFirstEventIdArmId($arm_id));
	}
	
	public function testGetProjFirstEventIdFromInvalidArmNum()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertGreaterThan(0, $project->getFirstEventIdArm(8675309));
		$this->assertLessThan($event_id, $project->getFirstEventIdArm(8675309));
	}
	
	public function testGetProjFirstEventIdFromArmByInvalidEventId()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertFalse($event_id == $project->getFirstEventIdInArmByEventId('x'));
	}
	
	public function testGetProjFirstEventIdFromArmByEventId()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals($event_id, $project->getFirstEventIdInArmByEventId($event_id));
	}
	
	public function testGetProjFirstEventIdFromArmNum()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals($event_id, $project->getFirstEventIdArm(2));
	}
	
	public function testGetProjGetGroupsUserById()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$group_id = GroupTest::create($project_id, hashStr());

		$username = hashStr();
		UserRightTest::create($project_id, $username);
		UserRightTest::update($project_id, $username, 'group_id', $group_id);
		
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals($username, $project->getGroupUsers($group_id)[0]);
	}
	
	public function testGetProjGetGroupsUsers()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$group_id = GroupTest::create($project_id, hashStr());

		$username = hashStr();
		UserRightTest::create($project_id, $username);
		UserRightTest::update($project_id, $username, 'group_id', $group_id);
		
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals($username, $project->getGroupUsers()[$group_id][0]);
	}
	
	public function testGetProjResetGroupsGetGroups()
	{
		$project_id = ProjectTest::getTestProjectID1();

		GroupTest::create($project_id, hashStr());
		GroupTest::create($project_id, hashStr());
		
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);

		$project->resetGroups();
		$this->assertEquals(2, count($project->getGroups()));
	}

	public function testGetProjGetGroupsWithEmptyNames()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$group_id = GroupTest::create($project_id, '');
		
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertGreaterThan(0, strlen($project->getUniqueGroupNames()[$group_id]));
	}
	
	public function testGetProjUniqueGroupNameExists()
	{
		$project_id = ProjectTest::getTestProjectID1();

		GroupTest::create($project_id, hashStr());
		$name = hashStr();
		GroupTest::create($project_id, $name);
		
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertTrue($project->uniqueGroupNameExists($name));
	}
	
	public function testGetProjValidateGroupId()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$name = hashStr();
		$group_id = GroupTest::create($project_id,$name);
		
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertTrue($project->validateGroupId($group_id));
	}
	
	public function testGetProjGetGroupById()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$name = hashStr();
		$group_id = GroupTest::create($project_id,$name);
		
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals($name, $project->getGroups($group_id));
	}
	
	public function testGetProjGetGroups()
	{
		$project_id = ProjectTest::getTestProjectID1();

		GroupTest::create($project_id, hashStr());
		GroupTest::create($project_id, hashStr());
		
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->getGroups()));
	}
	
	public function testGetProjIsFollowupSurvey()
	{
		$project_id = ProjectTest::getTestProjectID1();
		$form_name = hashStr();
		$survey_id = SurveyTest::create($project_id, $form_name);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertTrue($project->isFollowupSurvey($survey_id));
	}
	
	public function testGetProjValidateEventIdSurveyId()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$form_name = hashStr();
		$survey_id = SurveyTest::create($project_id, $form_name);

		ProjectTest::updateFieldValue($project_id, 'surveys_enabled', 1);
		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);

		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		EventFormTest::create($event_id, $form_name);
		
		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'form_name', $form_name);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertTrue($project->validateEventIdSurveyId($event_id, $survey_id));
	}
	
	public function testGetProjValidateEventId()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertTrue($project->validateEventId($event_id));
	}
	
	public function testGetProjValidateSurveyId()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$form_name = hashStr();
		$survey_id = SurveyTest::create($project_id, $form_name);

		ProjectTest::updateFieldValue($project_id, 'surveys_enabled', 1);

		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'form_name', $form_name);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertTrue($project->validateSurveyId($survey_id));
	}

	public function testGetProjFormsFromLibrary()
	{
		$project_id = ProjectTest::getTestProjectID1();

		LibraryMapTest::create($project_id, hashStr(), 1, 1);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(1, $project->formsFromLibrary());
	}

	public function testGetProjFixEmptyUniqueEventName()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		EventTest::update($event_id, 'descrip', '');
		ArmTest::update($arm_id, 'arm_name', '');

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertGreaterThan(0, strlen($project->getUniqueEventNames($event_id)));
	}

	public function testGetProjUniqueEventNameExists()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertTrue($project->uniqueEventNameExists('event_2_arm_2'));
	}

	public function testGetProjEventIdFromUniqueEventName()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals($event_id, $project->getEventIdUsingUniqueEventName('event_2_arm_2'));
	}

	public function testGetProjValidateFormEvent()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$project = new Project($project_id);

		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));
		$this->assertTrue($project->validateFormEvent($form_name, $event_id));
	}

	public function testGetProjWithFormStatusField()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$project = new Project($project_id);

		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));

		$f = MetaDataTest::get($project_id)[0];
		$this->assertTrue($project->isFormStatus($f['field_name']));
	}

	public function testGetProjWithMultipleChoiceField()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'element_type', 'radio');

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));
		$this->assertTrue($project->isMultipleChoice($f['field_name']));
	}

	public function testGetProjWithCheckboxField()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'element_type', 'checkbox');

		$project = new Project($project_id);

		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));
		$this->assertTrue($project->isCheckbox($f['field_name']));
	}

	public function testGetProjWithSurveysEnabledDraftMode()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$form_name = hashStr();
		$survey_id = SurveyTest::create($project_id, $form_name);

		ProjectTest::updateFieldValue($project_id, 'surveys_enabled', 1);
		ProjectTest::updateFieldValue($project_id, 'status', 1);
		ProjectTest::updateFieldValue($project_id, 'draft_mode', 1);

		MetaDataTest::create($project_id, hashStr());
		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'form_name', $form_name);
		MetaDataTest::update($f['project_id'], $f['field_name'], 'form_menu_description', 'not blank');

		MetaDataTempTest::create($project_id, hashStr());
		$f = MetaDataTempTest::get($project_id)[0];
		MetaDataTempTest::update($f['project_id'], $f['field_name'], 'form_name', $form_name);
		MetaDataTempTest::update($f['project_id'], $f['field_name'], 'form_menu_description', 'not blank');

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals($survey_id, $project->forms_temp[$form_name]['survey_id']);
	}

	public function testGetProjWithSurveyAutoNumberingAndBranching()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'surveys_enabled', 1);

		$f = MetaDataTest::get($project_id)[0];
		$form_name = hashStr();

		MetaDataTest::update($f['project_id'], $f['field_name'], 'form_name', $form_name);
		MetaDataTest::update($f['project_id'], $f['field_name'], 'branching_logic', 1);

		$survey_id = SurveyTest::create($project_id, $form_name);
		SurveyTest::update($survey_id, 'question_auto_numbering', 1);

		$project = new Project($project_id);

		$this->assertEquals($project_id, $project->project_id);
		$this->assertGreaterThan(0, count($project->surveys));
		$this->assertEquals(0, $project->surveys[$survey_id]['question_auto_numbering']);
	}

	public function testGetProjWithBlankSurveyName()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'surveys_enabled', 1);

		$f = MetaDataTest::get($project_id)[0];
		$form_name = hashStr();
		MetaDataTest::update($f['project_id'], $f['field_name'], 'form_name', $form_name);

		$survey_id = SurveyTest::create($project_id, '');

		$project = new Project($project_id);

		$this->assertEquals($project_id, $project->project_id);
		$this->assertGreaterThan(0, count($project->surveys));
		$this->assertEquals($form_name, $project->surveys[$survey_id]['form_name']);
	}

	public function testGetProjMultipleEvents()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$form_name = hashStr();
		EventFormTest::create($event_id, $form_name);
		ProjectTest::setFieldNames($project_id, $form_name);
		
		$project = new Project($project_id);

		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->eventInfo));
	}

	public function testGetProjWithSurveysEnabled()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'surveys_enabled', 1);

		$f = MetaDataTest::get($project_id)[0];
		$form_name = hashStr();
		MetaDataTest::update($f['project_id'], $f['field_name'], 'form_name', $form_name);

		SurveyTest::create($project_id, $form_name);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertGreaterThan(0, count($project->surveys));
	}

	public function testGetProjDraftWithBranchLogic()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'status', 1);
		ProjectTest::updateFieldValue($project_id, 'draft_mode', 1);

		MetaDataTempTest::create($project_id, hashStr());

		$f = MetaDataTempTest::get($project_id)[0];
		MetaDataTempTest::update($f['project_id'], $f['field_name'], 'branching_logic', hashStr());

		$project = new Project($project_id);

		$this->assertEquals($project_id, $project->project_id);

		$field_name = str_replace('_complete', '', $f['form_name']);
		$this->assertEquals(1, $project->forms_temp[$field_name]['has_branching']);
	}

	public function testGetProjDraftWithGridName()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'status', 1);
		ProjectTest::updateFieldValue($project_id, 'draft_mode', 1);

		MetaDataTempTest::create($project_id, hashStr());

		$grid_name = hashStr();

		$f = MetaDataTempTest::get($project_id)[0];
		MetaDataTempTest::update($f['project_id'], $f['field_name'], 'grid_name', $grid_name);

		$project = new Project($project_id);

		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(1, count($project->metadata_temp));
		$this->assertEquals(1, count($project->matrixGroupNamesTemp[$grid_name]));
	}

	public function testGetProjDraftFixSectionHeader()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'status', 1);
		ProjectTest::updateFieldValue($project_id, 'draft_mode', 1);
		MetaDataTempTest::create($project_id, hashStr());

		$str = hashStr();

		$f = MetaDataTempTest::get($project_id)[0];
		MetaDataTempTest::update($f['project_id'], $f['field_name'], 'form_name', $str);
		MetaDataTempTest::update($f['project_id'], $f['field_name'], 'field_name', $str . '_complete');
		MetaDataTempTest::update($f['project_id'], $f['field_name'], 'element_preceding_header', 'not Form Status');

		$project = new Project($project_id);
		$f = MetaDataTempTest::get($project_id)[0];

		$this->assertTrue(isset($project->metadata_temp));
		$this->assertEquals(1, count($project->metadata_temp));
		$this->assertEquals($f['element_preceding_header'], 'Form Status');
	}

	public function testGetProjDraftUpgradeValidationTypeToYMD()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'status', 1);
		ProjectTest::updateFieldValue($project_id, 'draft_mode', 1);
		MetaDataTempTest::create($project_id, hashStr());

		$f = MetaDataTempTest::get($project_id)[0];
		MetaDataTempTest::update($f['project_id'], $f['field_name'], 'element_type', 'text');
		MetaDataTempTest::update($f['project_id'], $f['field_name'], 'element_validation_type', 'date');

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(1, count($project->metadata_temp));

		foreach($project->metadata_temp as $k => $field)
		{
			if($field['element_type'] == 'text')
			{
				$this->assertEquals('date_ymd', $field['element_validation_type']);
			}
		}
	}

	public function testGetProjDraftModeBlankFieldName()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'status', 1);
		ProjectTest::updateFieldValue($project_id, 'draft_mode', 1);
		MetaDataTempTest::create($project_id, '');

		$project = new Project($project_id);

		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(0, count($project->metadata_temp));
	}

	public function testGetProjDraftMode()
	{
		$project_id = ProjectTest::getTestProjectID1();

		ProjectTest::updateFieldValue($project_id, 'status', 1);
		ProjectTest::updateFieldValue($project_id, 'draft_mode', 1);
		MetaDataTempTest::create($project_id, hashStr());

		$project = new Project($project_id);

		$this->assertTrue(isset($project->metadata_temp));
		$this->assertEquals(1, count($project->metadata_temp));
	}

	public function testGetProjWithBranchLogic()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'branching_logic', hashStr());

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));

		$field_name = str_replace('_complete', '', $f['field_name']);
		$this->assertEquals(1, $project->forms[$field_name]['has_branching']);
	}

	public function testGetProjFieldReorder()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$grid_name = hashStr();

		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'field_order', 0);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));

		$f = MetaDataTest::get($project_id)[0];
		$this->assertEquals(1, $f['field_order']);
	}

	public function testGetProjWithGridNameAndRank()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$grid_name = hashStr();

		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'grid_name', $grid_name);
		MetaDataTest::update($f['project_id'], $f['field_name'], 'grid_rank', 1);

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));

		$this->assertEquals(1, count($project->matrixGroupNames[$grid_name]));
		$this->assertEquals(1, count($project->matrixGroupHasRanking));
	}

	public function testGetProjSetHasFileUploadFields()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'element_type', 'file');

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));

		$this->assertTrue($project->hasFileUploadFields);
	}

	public function testGetProjUpgradeValidationTypeToYMD()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'element_type', 'text');
		MetaDataTest::update($f['project_id'], $f['field_name'], 'element_validation_type', 'date');

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));

		foreach($project->metadata as $k => $field)
		{
			if($field['element_type'] == 'text')
			{
				$this->assertEquals('date_ymd', $field['element_validation_type']);
			}
		}
	}

	public function testGetProjWithFormMenuDescSet()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$f = MetaDataTest::get($project_id)[0];
		MetaDataTest::update($f['project_id'], $f['field_name'], 'form_name', 'not blank');
		MetaDataTest::update($f['project_id'], $f['field_name'], 'form_menu_description', 'not blank');

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));
	}

	public function testGetProjWithNoFields()
	{
		$project_id = ProjectTest::getTestProjectID1();

		MetaDataTest::del($project_id);
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(0, count($project->metadata));
	}

	public function testGetProjWithBlankFieldName()
	{
		$project_id = ProjectTest::getTestProjectID1();

		MetaDataTest::create($project_id, '');
		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));
	}

	public function testGetProj()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$project = new Project($project_id);
		$this->assertEquals($project_id, $project->project_id);
		$this->assertEquals(2, count($project->metadata));
	}

	public function testGetEmptyProj()
	{
		//$this->setExpectedException('Exception');
		$project = new Project();
	}
	*/
}
