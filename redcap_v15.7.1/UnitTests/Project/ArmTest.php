<?php

namespace Vanderbilt\REDCap\Tests\Project;

class ArmTest extends ProjectTest
{
	public function setUp(): void
	{
		db_connect(false);
	}

	public static function update($arm_id, $field, $value)
	{
		$sql = "
			UPDATE redcap_events_arms
			SET $field = '$value'
			WHERE arm_id = $arm_id
			LIMIT 1
		";
        $q = db_query($sql);
        return ($q && $q !== false) ? db_affected_rows() : 0;
	}
	
	public static function create($project_id, $number=1)
	{
		$sql = "
			INSERT INTO redcap_events_arms (
				project_id, arm_num, arm_name
			) VALUES (
				$project_id, $number, 'Arm $number'
			)
		";
        $q = db_query($sql);
        if ($q && $q !== false) {
            $arm_id = db_insert_id();
            self::$redcapEventsArms[] = [$arm_id, $project_id];
            return $arm_id;
        }
        return 0;
	}

	// tests
	
	public function testAdd()
	{
		$this->markTestIncomplete();
		return;
		
		$count = rowCount('redcap_events_arms');

		$project_id = ProjectTest::getTestProjectID1();

		ArmTest::create($project_id);
		$this->assertGreaterThan($count, rowCount('redcap_events_arms'));
	}
	
}
