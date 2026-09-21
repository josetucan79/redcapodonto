<?php

namespace Vanderbilt\REDCap\Tests\Survey;

use Vanderbilt\REDCap\Tests\Project\ProjectTest;
use Vanderbilt\REDCap\Tests\Project\EventFormTest;

class SurveyTest extends ProjectTest
{
    protected static $redcapSurveys = [];
    protected function setUp(): void
    {
        parent::setUp();
    }

    public static function setFormAsSurvey($project_id, $form_name)
    {
        if (self::$surveysEnabled[$project_id] == 0) {
            throw new \Exception("to set form as survey, surveys must be enabled in the project");
        }
        $title = ucfirst($form_name);
        $sql = "
			INSERT INTO redcap_surveys (
				project_id, form_name, title
			) VALUES (
				$project_id, '$form_name', '$title'
			)
		";
        $q = db_query($sql);
        if ($q && $q !== false) {
            $survey_id = db_insert_id();
            self::$redcapSurveys[$survey_id] = [$project_id, $form_name];
            return $survey_id;
        }
        return 0;
    }

    public static function unsetFormAsSurvey($survey_id)
    {
        $sql = "DELETE FROM redcap_surveys WHERE survey_id = {$survey_id}";
        $q = db_query($sql);
        return ($q && $q !== false) ? db_affected_rows() : 0;
    }

    public function testGetSurveys()
    {
        // this call triggers chain of operations that insert default event and arm into database using \Project::insertDefaultArmAndEvent
        $project_id = self::getTestProjectID1();
        // enable surveys in project
        self::updateFieldValue($project_id, 'surveys_enabled', 1);
        // set a form
        $form_name = 'form_abc123';
        EventFormTest::create(ProjectTest::$existingEventId, $form_name);
        self::setFieldNames($project_id, $form_name);
        SurveyTest::setFormAsSurvey($project_id, $form_name);
        $this->assertEquals(1, count((new \Project($project_id, true))->surveys));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}