<?php

namespace Vanderbilt\REDCap\Tests\Project;

class EventFormTest extends ProjectTest
{
	public static function create($event_id, $form_name)
	{
		$sql = "
			INSERT INTO redcap_events_forms (
				event_id, form_name
			) VALUES (
				$event_id, '$form_name'
			)
		";
		$q = db_query($sql);
        if ($q && $q !== false) {
            if (!isset(self::$redcapEventsForms[$event_id][$form_name])) {
                self::$redcapEventsForms[$event_id][] = $form_name;
            }
            return db_affected_rows();
        }
		return 0;
	}

    public static function delete($event_id, $form_name)
    {
        $sql = "DELETE FROM redcap_events_forms WHERE event_id= " . $event_id . " AND form_name=" . "'$form_name'";
        $q = db_query($sql);
        return ($q && $q !== false) ? db_affected_rows() : 0;
    }

	// tests
	
	public function testAdd()
	{
		$this->markTestIncomplete();
		return;
		
		$count = rowCount('redcap_events_forms');

		$project_id = ProjectTest::getTestProjectID1();

		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id);
		EventFormTest::create($event_id, hashStr());

		$this->assertGreaterThan($count, rowCount('redcap_events_forms'));
	}

}
