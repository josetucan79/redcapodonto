<?php

namespace Vanderbilt\REDCap\Tests\FormDisplayLogic;

use Vanderbilt\REDCap\Tests\Project\EventFormTest;
use Vanderbilt\REDCap\Tests\Project\MetaDataTest;
use Vanderbilt\REDCap\Tests\Project\ProjectTest;

class FormDisplayLogicTest extends ProjectTest
{
    public static $redcapFDLConditions = [];

    public static $redcapFDLTargets = [];

    protected function setUp(): void
    {
        parent::setUp();
    }

    private static function createCondition($project_id, $control_condition)
    {
        $sql = "
			INSERT INTO redcap_form_display_logic_conditions (
				project_id, control_condition
			) VALUES (
				$project_id,'$control_condition'
			)
		";
        $q = db_query($sql);
        if ($q && $q !== false) {
            $control_id = db_insert_id();
            if (!isset(self::$redcapFDLConditions[$project_id][$control_id])) {
                self::$redcapFDLConditions[$project_id][$control_id] = $control_condition;
            }
            return $control_id;
        }
        return 0;
    }

    private static function deleteCondition($control_id)
    {
        $sql = "DELETE FROM redcap_form_display_logic_conditions WHERE control_id={$control_id}";
        $q = db_query($sql);
        return ($q && $q !== false) ? db_affected_rows() : 0;
    }

    private static function addTargetToCondition($control_id, $form_name, $event_id)
    {
        $sql = "
			INSERT INTO redcap_form_display_logic_targets (
				control_id, form_name, event_id
			) VALUES (
				$control_id,'$form_name', $event_id
			)
		";
        $q = db_query($sql);
        if ($q && $q !== false) {
            self::$redcapFDLTargets[$control_id][] = [$form_name, $event_id];
            return  db_insert_id();
        }
        return 0;
    }

    private static function removeTargetFromCondition($control_id, $form_name, $event_id)
    {
        $sql = "DELETE FROM redcap_form_display_logic_targets WHERE control_id={$control_id} AND form_name='{$form_name}' AND event_id={$event_id}";
        $q = db_query($sql);
        return ($q && $q !== false) ? db_affected_rows() : 0;
    }

    public function testFDLExportSuccessful()
    {
        $this->runSeeder();
        $project_id = self::getTestProjectID1();
        $formDisplayLogicSetup = new \FormDisplayLogicSetup($project_id);
        $formDisplayLogicSetup->export();
        $this->assertFileExists($this->fileManager::$filePathDownload);
    }

    public function testFDLImportSuccessful()
    {
        $project_id = self::getTestProjectID1();
        $this->runSeeder();
        $GLOBALS['Proj'] = new \Project($project_id, true); // load project attributes and set $Prof as a global variable
        $formDisplayLogicImportData = [];
        foreach (self::$redcapFDLTargets as $control_id => $arr1) {
            foreach ($arr1 as $arr2) {
                $control_condition = self::$redcapFDLConditions[$project_id][$control_id];
                $form_name = $arr2[0];
                $event_name = \Event::getEventNameById($project_id, (int)$arr2[1]);
                if (($GLOBALS['Proj'])->longitudinal) {
                    $formDisplayLogicImportData[] = [
                        'form_name' => $form_name,
                        'event_name' => $event_name,
                        'control_condition' => $control_condition
                    ];
                } else {
                    $formDisplayLogicImportData[] = [
                        'form_name' => $form_name,
                        'control_condition' => $control_condition
                    ];
                }
            }
        }
        // create csv import file from array
        $headers = array_keys($formDisplayLogicImportData[0]);
        $fileName = 'fdl_import_file';
        $filePath = $this->downloadsDir . '/' . $fileName . '.csv';
        $file = fopen($filePath, 'w');
        fputcsv($file, $headers, ',', '"', '');
        foreach ($formDisplayLogicImportData as $row) {
            fputcsv($file, $row, ',', '"', '');
        }
        fclose($file);
        $fileContent = file_get_contents($filePath);
        $fileSize = strlen($fileContent);
        $tempFileName = tempnam($this->downloadsDir, 'fdl_unit_tests_');
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
        $formDisplayLogicSetup = new class ($project_id) extends \FormDisplayLogicSetup {
            protected function validateControlCondition(string $controlCondition): string
            {
                return '1';
            }
        };
        $formDisplayLogicSetup->setHttpClient($this->httpClient);
        ob_start();
        // FDL import function will start new transaction; therefore we ideally would like to rollback previous transaction to avoid saving changes done up to this point.
        // However, rolling back at this point will cause call to import() method to fail as roll back operation would have removed necessary data; therefore, we must instead do the cleanup in the tearDown() method.
        $formDisplayLogicSetup->import();
        $output = ob_get_clean();
        $data = json_decode($output);
        $fromDb = \FormDisplayLogic::getFormDisplayLogicTableValues();
        $expectedControlsCount = 0;
        $expectedFormsCount = 0;
        foreach ($data->data as $fdlObj) {
            foreach ($fromDb['controls'] as $control) {
                if ($fdlObj->control_id == $control['control_id']) {
                    $expectedControlsCount++;
                    self::assertEquals($fdlObj->{'control-condition'}, $control['control-condition']);
                }
            }
            foreach ($fromDb['forms_targeted'] as $formName) {
                foreach ($fdlObj->{'form-name'} as $longFormName) {
                    if (str_contains($longFormName, $formName)) {
                        $expectedFormsCount++;
                    }
                }
            }
        }
        // we have only created 1 form in this test project and have set just 1 control condition on that form
        $this->assertEquals($expectedControlsCount, 1);
        $this->assertEquals($expectedFormsCount, 1);
    }

    /**
     * @throws \Exception
     */
    private function runSeeder()
    {
        $project_id = self::getTestProjectID1();
        // create form 1 with random name and associate it to existing event which was created during project creation as default event
        $form1_name = 'form1_abc123';
        EventFormTest::create(ProjectTest::$existingEventId, $form1_name); // default project setup does not fill out redcap_events_forms table; we do it here.
        self::setFieldNames($project_id, $form1_name); // overwrite default form name in redcap_metadata table that created during project setup with new form name.
        // get default field name
        $existingFieldName = self::getDefaultFieldName($project_id, $form1_name);
        // set field type
        MetaDataTest::update($project_id, $existingFieldName, 'element_type', 'text');
        MetaDataTest::update($project_id, $existingFieldName, 'element_validation_type', 'int');
        MetaDataTest::update($project_id, $existingFieldName, 'element_validation_checktype', 'soft_typed');
        MetaDataTest::update($project_id, $existingFieldName, 'field_req', 1);
        // add a control condition
        $control_id = self::createCondition($project_id, "$existingFieldName > 1");
        // set a target for the condition
        self::addTargetToCondition($control_id, $form1_name, ProjectTest::$existingEventId);
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
        // undo changes to redcap_events_forms table
        foreach(EventFormTest::$redcapEventsForms as $event_id => $forms) {
            foreach ($forms as $form_name) {
                EventFormTest::delete($event_id, $form_name);
            }
        }
        // undo changes to redcap_metadata table
        if (!empty(MetaDataTest::$redcapMetaDataFormsFields)) {
            foreach (MetaDataTest::$redcapMetaDataFormsFields[$project_id] as $fieldName) {
                MetaDataTest::delete($project_id, $fieldName);
            }
        }
        // remove targets from conditions
        foreach (self::$redcapFDLTargets as $controlId => $arr) {
            foreach ($arr as $formEventPair) {
                self::removeTargetFromCondition($controlId, $formEventPair[0], $formEventPair[1]);
            }
        }
        // remove conditions
        foreach (array_keys(self::$redcapFDLTargets) as $control_id) {
            self::deleteCondition($control_id);
        }

        // clean static vars
        self::$existingEventId = null;
        self::$redcapFDLTargets = [];
        self::$redcapFDLConditions = [];
        EventFormTest::$redcapEventsForms = [];
        MetaDataTest::$redcapMetaDataFormsFields = [];
    }

    private static function getDefaultFieldName($project_id, $form_name)
    {
        $sql = "
			SELECT field_name FROM redcap_metadata
			WHERE project_id = $project_id
			AND form_name = '$form_name'
			LIMIT 1
		";
        $q = db_query($sql);
        if (!db_num_rows($q)) {
            throw new \Exception("expected field name to have been created during default project initialization");
        }
        return db_result($q, 0, 'field_name');
    }
}