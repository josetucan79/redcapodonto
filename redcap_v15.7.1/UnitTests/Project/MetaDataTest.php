<?php

namespace Vanderbilt\REDCap\Tests\Project;

use PHPUnit\Framework\TestCase;

class MetaDataTest extends TestCase
{
    public static $redcapMetaDataFormsFields = [];

	protected function setUp(): void
	{
		db_connect(false);
	}

	/**
	 * This test exists solely to avoid the warning that no tests exist in this class.
	 */
	public function testAvoidWarning(){
		$this->markTestIncomplete();
	}

	public static function get($project_id)
	{
		$array = array();
		
		$sql = "
			SELECT *
			FROM redcap_metadata
			WHERE project_id = $project_id
		";
		
		$q = db_query($sql);
		
		if($q && $q !== false)
		{
			while($row = db_fetch_assoc($q))
			{
				$array[] = $row;
			}
		}
		
		return $array;
	}
	
	public static function update($project_id, $field_name, $field, $value)
	{
		$sql = "
			UPDATE redcap_metadata
			SET $field = '$value'
			WHERE project_id = $project_id
			AND field_name = '$field_name'
			LIMIT 1
		";
		$q = db_query($sql);
        if ($q && $q !== false) {
            if ($field == 'field_name' && isset(self::$redcapMetaDataFormsFields[$project_id])) {
                self::$redcapMetaDataFormsFields[$project_id][] = "{$value}";
            }
            return db_affected_rows();
        }
		return 0;
	}
	
	public static function create($project_id, $field_name='record_id', $field_order=1, $form_name=null)
	{
        if (is_null($form_name)) {
            $sql = "
			INSERT INTO redcap_metadata (
				project_id, field_name, field_order
			) VALUES (
				$project_id, '$field_name', $field_order
			)
		";
        } else {
            $sql = "
			INSERT INTO redcap_metadata (
				project_id, field_name, field_order, form_name
			) VALUES (
				$project_id, '$field_name', $field_order, '$form_name'
			)
		";
        }
		$q = db_query($sql);
        if ($q && $q !== false) {
            self::$redcapMetaDataFormsFields[$project_id][] = $field_name;
            return  db_insert_id();
        }
        return 0;
	}

    public static function delete($project_id, $field_name)
    {
        $sql = "DELETE FROM redcap_metadata WHERE project_id=" . $project_id . " AND field_name=" . "'$field_name'";
        $q = db_query($sql);
        return ($q && $q !== false) ? db_affected_rows() : 0;
    }

	// tests
	/**
	public function testGetDataDictionary()
	{
		$project_id = ProjectTest::getTestProjectID1();
		define('PROJECT_ID', $project_id);

		$fields = MetaDataTest::get($project_id);
		$form_name = hashStr();

		foreach($fields as $f)
		{
			MetaDataTest::update($project_id, $f['field_name'], 'form_name', $form_name);
		}

		//
		$results = MetaData::getDataDictionary();
		$this->assertContains($form_name, $results);

		//
		$results = MetaData::getDataDictionary('array', false, array('record_id'), array($form_name));
		$this->assertContains($form_name, $results['record_id']['form_name']);

		//
		$results = MetaData::getDataDictionary('json', false, 'record_id', $form_name);
		$this->assertContains("\"form_name\":\"$form_name\"", $results);

		//
		$results = MetaData::getDataDictionary('xml', false);
		$this->assertContains("<form_name><![CDATA[$form_name]]></form_name>", $results);
		
		undef('PROJECT_ID');
	}
	
	public function testGetDateFormatDisplay()
	{
		$a = array(
			'H:M'         => array('time'),
			'Y-M-D'       => array('date', 'date_ymd'),
			'M-D-Y'       => array('date_mdy'),
			'D-M-Y'       => array('date_dmy'),
			'Y-M-D H:M'   => array('datetime', 'datetime_ymd'),
			'M-D-Y H:M'   => array('datetime_mdy'),
			'D-M-Y H:M'   => array('datetime_dmy'),
			'Y-M-D H:M:S' => array('datetime_seconds', 'datetime_seconds_ymd'),
			'M-D-Y H:M:S' => array('datetime_seconds_mdy'),
			'D-M-Y H:M:S' => array('datetime_seconds_dmy'),
			''            => array('x')
		);

		foreach($a as $k => $v)
		{
			foreach($v as $vv)
			{
				$this->assertEquals($k, MetaData::getDateFormatDisplay($vv, true));
			}
		}
	}
	
	public function testGetFieldNames()
	{
		$project_id = ProjectTest::getTestProjectID1();
		$field_name = hashStr();		
		MetaDataTest::create($project_id, $field_name);
		MetaDataTest::update($project_id, $field_name, 'element_type', 'text');
		MetaDataTest::update($project_id, $field_name, 'field_order', 3);

		//
		$names = MetaData::getFieldNames($project_id);		
		$this->assertEquals($field_name, $names[2]);

		//
		MetaDataTest::update($project_id, $field_name, 'element_type', 'checkbox');
		MetaDataTest::update($project_id, $field_name, 'element_enum', "1, a \\n 2, b");

		$names = MetaData::getFieldNames($project_id);
		$this->assertEquals($field_name . '___2', $names[3]);
	}
	
	public function testGetCheckboxFields()
	{
		$project_id = ProjectTest::getTestProjectID1();
		$field_name = hashStr();		
		MetaDataTest::create($project_id, $field_name);
		MetaDataTest::update($project_id, $field_name, 'element_type', 'checkbox');
		MetaDataTest::update($project_id, $field_name, 'element_enum', "1, a \\n 2, b");

		$fields = MetaData::getCheckboxFields($project_id);
		$this->assertEquals('b', $fields[$field_name][2]);
	}
	
	public function testGetFields()
	{
		$project_id = ProjectTest::getTestProjectID1();
		$field_name = hashStr();		
		MetaDataTest::create($project_id, $field_name);
		MetaDataTest::update($project_id, $field_name, 'field_order', 3);
 
		// query fails on null element_type
		MetaDataTest::update($project_id, $field_name,                    'element_type', 'text');
		MetaDataTest::update($project_id, 'record_id',                    'element_type', 'text');
		MetaDataTest::update($project_id, 'my_first_instrument_complete', 'element_type', 'text');
		
		$project = new Project($project_id);

		$GLOBALS['Proj'] = $project;

		$field_names = array('record_id', 'my_first_instrument_complete', $field_name);

		// grep says this function is never called
		$fields = MetaData::getFields($project_id, false, $field_name, false, $field_names);

		$this->assertEquals('record_id',                    $fields['names'][0]);
		$this->assertEquals('my_first_instrument_complete', $fields['names'][1]);
		$this->assertEquals($field_name,                    $fields['names'][2]);

		unset($GLOBALS['Proj']);
	}

	public function testGetFields2()
	{
		$project_id = ProjectTest::getTestProjectID1();
		$field_name = hashStr();		
		MetaDataTest::create($project_id, $field_name);

		//
		MetaDataTest::update($project_id, $field_name, 'element_type', 'sql');
		MetaDataTest::update($project_id, $field_name, 'element_enum', 'select 1, 2');
		$fields = MetaData::getFields2($project_id, array($field_name));
		$this->assertEquals(2, $fields[$field_name]['enums'][1]);

		//
		MetaDataTest::update($project_id, $field_name, 'element_type', 'yesno');
		MetaDataTest::update($project_id, $field_name, 'element_enum', '');
		$fields = MetaData::getFields2($project_id, array($field_name));
		$this->assertEquals('Yes', $fields[$field_name]['enums'][1]);

		//
		MetaDataTest::update($project_id, $field_name, 'element_type', 'truefalse');
		$fields = MetaData::getFields2($project_id, array($field_name));
		$this->assertEquals('True', $fields[$field_name]['enums'][1]);

		//
		MetaDataTest::update($project_id, $field_name, 'element_type', 'slider');
		$fields = MetaData::getFields2($project_id, array($field_name));
		$this->assertEquals('0', $fields[$field_name]['element_validation_min']);
		$this->assertEquals('100', $fields[$field_name]['element_validation_max']);
	}
	
	public function testAdd()
	{
		$project_id = ProjectTest::getTestProjectID1();

		$count = rowCount('redcap_metadata');
		MetaDataTest::create($project_id, hashStr());
		$this->assertGreaterThan($count, rowCount('redcap_metadata'));
	}
	*/
}
