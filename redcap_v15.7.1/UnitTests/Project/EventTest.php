<?php

namespace Vanderbilt\REDCap\Tests\Project;

class EventTest extends ProjectTest
{
	protected function setUp(): void
	{
		db_connect(false);
	}

	public static function update($event_id, $field, $value)
	{
		$sql = "
			UPDATE redcap_events_metadata
			SET $field = '$value'
			WHERE event_id = $event_id
			LIMIT 1
		";
		$q = db_query($sql);
        return ($q && $q !== false) ? db_affected_rows() : 0;
	}

	public static function create($arm_id, $number=1)
	{
		$sql = "
			INSERT INTO redcap_events_metadata (
				arm_id, descrip
			) VALUES (
				$arm_id, 'Event $number'
			)
		";
		$q = db_query($sql);
        if ($q && $q !== false) {
            $event_id = db_insert_id();
            self::$redcapEventsMetadata[] = [$event_id, $arm_id];
            return $event_id;
        }
		return 0;
	}

    public static function delete($event_id)
    {
        $sql = "DELETE FROM redcap_events_metadata WHERE event_id={$event_id}";
        $q = db_query($sql);
        return ($q && $q !== false) ? db_affected_rows() : 0;
    }
	
	public function testGetEventIdByKey()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$arm_id = ArmTest::create($project_id);
		EventTest::create($arm_id);

		$this->assertEquals(1, count(\Event::getEventIdByKey($project_id, array($arm_id))));
	}

	public function testGetEventNameById()
	{
		$project_id = ProjectTest::getTestProjectID1();
		$project = new \Project($project_id);

		$event_id = array_keys($project->getUniqueEventNames())[0];
		$name = \Event::getEventNameById($project_id, $event_id);
		$this->assertEquals('event_1_arm_1', $name);
	}

	public function testGetUniqKeysEmptyProj()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$arm_id = ArmTest::create($project_id);
		EventTest::create($arm_id);

		$this->assertEquals('event_1_arm_1', array_values(\Event::getUniqueKeys($project_id))[0]);
	}

	public function testGetUniqKeys()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$arm_id = ArmTest::create($project_id);
		EventTest::create($arm_id);

		$project = new \Project($project_id);
		$GLOBALS['Proj'] = $project;

		$this->assertEquals('event_1_arm_1', array_values(\Event::getUniqueKeys($project_id))[0]);

		unset($GLOBALS['Proj']);
	}

	public function testGetEventsByProject()
	{
		$this->markTestIncomplete();
		return;
		
		$project_id = ProjectTest::getTestProjectID1();

		$arm_id = ArmTest::create($project_id);
		EventTest::create($arm_id);

		$this->assertEquals(1, count(\Event::getEventsByProject($project_id)));
	}

	public function testCreateEvent()
	{
		$this->markTestIncomplete();
		return;
		
		$count = rowCount('redcap_events_metadata');

		$project_id = ProjectTest::getTestProjectID1();

		$arm_id = ArmTest::create($project_id);
		EventTest::create($arm_id);

		$this->assertGreaterThan($count, rowCount('redcap_events_metadata'));
	}

}
