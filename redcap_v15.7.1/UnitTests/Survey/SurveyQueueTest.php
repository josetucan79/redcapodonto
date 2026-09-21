<?php

namespace Vanderbilt\REDCap\Tests\Survey;

use Vanderbilt\REDCap\Tests\Project\EventFormTest;
use Vanderbilt\REDCap\Tests\Project\EventTest;
use Vanderbilt\REDCap\Tests\Project\MetaDataTest;
use Vanderbilt\REDCap\Tests\Project\ProjectTest;

class SurveyQueueTest extends SurveyTest
{
    protected $fileManager;

    protected static $redcapSurveysQueue = [];

    protected function setUp(): void
    {
        parent::setUp();
    }

    public static function create($survey_id, $event_id, $condition_surveycomplete_survey_id, $condition_surveycomplete_event_id, $condition_andor = 'AND', $active = 1, $auto_start = 0, $condition_logic = '')
    {
        $sql = "
			INSERT INTO redcap_surveys_queue (
				survey_id, event_id, condition_surveycomplete_survey_id, condition_surveycomplete_event_id, condition_andor, active, auto_start, condition_logic 
			) VALUES (
				$survey_id, $event_id, $condition_surveycomplete_survey_id, $condition_surveycomplete_event_id, '$condition_andor', $active, $auto_start, '$condition_logic'
			)
		";
        $q = db_query($sql);
        if ($q && $q !== false) {
            if (!isset(self::$redcapSurveysQueue[$survey_id][$event_id])) {
                self::$redcapSurveysQueue[$survey_id][$event_id] = [$condition_surveycomplete_survey_id, $condition_surveycomplete_event_id, $condition_andor, $active, $auto_start, $condition_logic];
            }
            return db_affected_rows();
        }
        return 0;
    }

    public static function delete($project_id)
    {
        $sql = "DELETE FROM redcap_surveys_queue 
            WHERE survey_id IN (
                SELECT survey_id from redcap_surveys WHERE project_id = ?
            )";
        $q = db_query($sql, [$project_id]);
        return ($q && $q !== false) ? db_affected_rows() : 0;
    }

    /**
     * @return void
     * @throws \Exception
     *
     * Tests survey queue file export with 1-arm project
     */
    public function testSurveyQueueFileExportSuccessful()
    {
        $this->runSeeder();
        $project_id = self::getTestProjectID1();
        // add surveys to queue
        foreach (array_keys(self::$redcapSurveys) as $survey_id) {
            foreach (array_keys(EventFormTest::$redcapEventsForms) as $event_id) {
                self::create($survey_id, $event_id, $survey_id, $event_id);
            }
        }
        $survey_queue = \Survey::getProjectSurveyQueue(true, true, $project_id);
        foreach (array_keys(self::$redcapSurveys) as $survey_id) {
            foreach (array_keys(EventFormTest::$redcapEventsForms) as $event_id) {
                $this->assertNotEmpty($survey_queue[$survey_id][$event_id]);
            }
        }
        $surveyQueueSetup = new \SurveyQueueSetup($project_id);
        $surveyQueueSetup->export();
        $this->assertFileExists($this->fileManager::$filePathDownload);
    }

    public function testSurveyQueueImportSuccessful()
    {
        $this->runSeeder();
        $project_id = self::getTestProjectID1();
        $eventNames = [];
        foreach (array_keys(EventFormTest::$redcapEventsForms) as $event_id) {
            $eventNames[] = \Event::getEventNameById($project_id, (int)$event_id);
        }
        $formNames = [];
        foreach (self::$redcapSurveys as $arr) {
            $formNames[] = $arr[1];
        }
        $surveyQueueImportData = [];
        foreach ($formNames as $fName) {
            foreach ($eventNames as $eName) {
                if (($GLOBALS['Proj'])->longitudinal) {
                    $surveyQueueImportData[] = [
                        'form_name' => $fName,
                        'event_name' => $eName,
                        'active' => 1,
                        'condition_surveycomplete_form_name' => $fName,
                        'condition_surveycomplete_event_name' => $eName,
                        'condition_andor' => 'AND',
                        'condition_logic' => null,
                        'auto_start' => 0,
                    ];
                } else {
                    $surveyQueueImportData[] = [
                        'form_name' => $fName,
                        'active' => 1,
                        'condition_surveycomplete_form_name' => $fName,
                        'condition_andor' => 'AND',
                        'condition_logic' => null,
                        'auto_start' => 0,
                    ];
                }
            }
        }
        // create csv import file from array
        $headers = array_keys($surveyQueueImportData[0]);
        $fileName = 'sqs_import_file';
        $filePath = $this->downloadsDir . '/' . $fileName . '.csv';
        $file = fopen($filePath, 'w');
        fputcsv($file, $headers, ',', '"', '');
        foreach ($surveyQueueImportData as $row) {
            fputcsv($file, $row, ',', '"', '');
        }
        fclose($file);
        $fileContent = file_get_contents($filePath);
        $fileSize = strlen($fileContent);
        $tempFileName = tempnam($this->downloadsDir, 'sqs_unit_tests_');
        file_put_contents($tempFileName, $fileContent);
        $_FILES = [
            'files' => [
                'name' => $fileName,
                'type' => 'text/csv',
                'tmp_name' => $tempFileName,
                'error' => 0,
                'size' => $fileSize
            ]
        ];
        $surveyQueueSetup = new \SurveyQueueSetup($project_id);
        $surveyQueueSetup->setHttpClient($this->httpClient);
        ob_start();
        // SQS import function will start new transaction; therefore we ideally would like to rollback previous transaction to avoid saving changes done up to this point.
        // However, rolling back at this point will cause call to import() method to fail as roll back operation would have removed necessary data; therefore, we must instead do the cleanup in the tearDown() method.
        $surveyQueueSetup->import();
        $output = ob_get_clean();
        $data = json_decode($output);

        $survey_queue = \Survey::getProjectSurveyQueue(true, true, $project_id);
        $this->assertIsArray($survey_queue);
        $this->assertNotEmpty($survey_queue);
        $expectedMatches = count($data->data);
        $actualMatches = 0;
        foreach ($data->data as $item) {
            $surveyId = $item->survey_id;
            $eventId = $item->event_id;
            if (isset($survey_queue[$surveyId][$eventId])) {
                $conditionSurveyId = $survey_queue[$surveyId][$eventId]['condition_surveycomplete_survey_id'];
                $conditionEventId = $survey_queue[$surveyId][$eventId]['condition_surveycomplete_event_id'];
                if ($conditionSurveyId == $surveyId && $conditionEventId == $eventId) {
                    $actualMatches++;
                }
            }
        }
        $this->assertEquals($expectedMatches, $actualMatches);
    }

    private function runSeeder()
    {
        $project_id = self::getTestProjectID1();
        $project = new \Project($project_id);
        // enable surveys in project
        self::updateFieldValue($project_id, 'surveys_enabled', 1);
        // create form 1 with random name and associate it to existing event which was created during project creation as default event
        $form1_name = 'form1_abc123';
        EventFormTest::create(ProjectTest::$existingEventId, $form1_name); // default project setup does not fill out redcap_events_forms table; we do it here.
        self::setFieldNames($project_id, $form1_name); // overwrite default form name in redcap_metadata table that created during project setup with new form name
        // set form 1 as survey
        SurveyTest::setFormAsSurvey($project_id, $form1_name);
        // check if project is longitudinal before creating second event and adding form 1 to it.
        // if this check is not performed, then \Event::getEventNameById() will return `null` for the second event id, and this `null` value, if not accounted for, may cause some issues during the tests
        if ($project->longitudinal) {
            // create a second event in same arm
            EventTest::create(self::$existingArmId, count(self::$redcapEventsMetadata) + 1); // redcap_events_metadata table
            // add form 1 to second event
            EventFormTest::create(self::$redcapEventsMetadata[1][0], $form1_name);
        }
        // create form 2
        $form2_name = 'form2_xyz789';
        // create a field in form 2
        $field_name = \SharedLibrary::getUniqueFieldName([], '', $project_id, 'record_id');
        MetaDataTest::create($project_id, $field_name, 1, $form2_name); // redcap_metadata table
        // add form 2 to default event
        EventFormTest::create(ProjectTest::$existingEventId, $form2_name); // redcap_events_forms table
        if ($project->longitudinal) {
            // add form 2 to second event
            EventFormTest::create(self::$redcapEventsMetadata[1][0], $form2_name);
        }
        // set form 2 as survey
        SurveyTest::setFormAsSurvey($project_id, $form2_name);

        // after everything has been set, we many now load project attributes and set $Prof as a global variable
        // this is needed in Survey::getSurveyId(), which itself is used in the SurveyQueueSetup::import() method.
        // this is also needed by Survey::getProjectSurveyQueue() when test project is longitudinal
        $GLOBALS['Proj'] = new \Project($project_id, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $files = glob($this->downloadsDir . '/*');
        foreach ($files as $file) {
                unlink($file);
        }
        rmdir($this->downloadsDir);

        $project_id = self::getTestProjectID1();
        // undo changes to redcap_surveys_queue table
        self::delete($project_id);
        // undo changes to redcap_surveys table
        foreach (array_keys(self::$redcapSurveys) as $survey_id) {
            SurveyTest::unsetFormAsSurvey($survey_id);
        }

        // first undo changes to redcap_events_forms table (redcap_events_forms depends on redcap_events_metadata)
        foreach(EventFormTest::$redcapEventsForms as $event_id => $forms) {
            foreach ($forms as $form_name) {
                EventFormTest::delete($event_id, $form_name);
            }
        }

        // then undo changes to redcap_events_metadata table
        foreach(EventTest::$redcapEventsMetadata as $arr) {
            if (!in_array(self::$existingEventId, $arr)) {
                EventTest::delete($arr[0]);
            }
        }

        // undo changes to redcap_metadata table
        foreach (MetaDataTest::$redcapMetaDataFormsFields[$project_id] as $fieldName) {
            MetaDataTest::delete($project_id, $fieldName);
        }

        // clean static vars
        self::$existingEventId = null;
        self::$redcapSurveys = [];
        EventTest::$redcapEventsMetadata = [];
        EventFormTest::$redcapEventsForms = [];
        MetaDataTest::$redcapMetaDataFormsFields = [];
    }
}