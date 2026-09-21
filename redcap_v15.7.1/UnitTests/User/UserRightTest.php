<?php

use Vanderbilt\REDCap\Tests\Project\ProjectTest;
use Vanderbilt\REDCap\Tests\Project\ArmTest;
use Vanderbilt\REDCap\Tests\Project\EventTest;
use Vanderbilt\REDCap\Tests\Project\EventFormTest;
// @todo: PSR-1 rule, PHP file should not have side effects - define needed functions in the class or import as library
//require_once dirname(__FILE__) . '/../../Config/init_functions.php';

class UserRightTest extends ProjectTest
{
	public function setUp(): void
	{
		db_connect(false);
	}
	
	public static function getAPIToken($username, $project_id)
	{
		$sql = "
			SELECT api_token
			FROM redcap_user_rights
			WHERE username = '$username'
			AND project_id = $project_id
		";
		$q = db_query($sql);
		return db_result($q, 0);	
	}
	
	public static function create($project_id, $username)
	{
		$sql = "
			INSERT INTO redcap_user_rights (
				project_id, username, api_import, api_export, api_modules
			) VALUES (
				$project_id, '$username', 1, 1, 1
			)
		";
		db_query($sql);
	}

	public static function update($project_id, $username, $field, $value)
	{
		$sql = "
			UPDATE redcap_user_rights
			SET $field = $value
			WHERE project_id = $project_id
			AND username = '$username'
			LIMIT 1
		";
		$q = db_query($sql);
		return ($q && $q !== false) ? db_affected_rows() : 0;
	}
	
	public function testSetFormLevelPrivileges()
	{
		$project_id = ProjectTest::getTestProjectID1();
		define('PROJECT_ID', $project_id);

		ProjectTest::updateFieldValue($project_id, 'repeatforms', 1);
		$arm_id = ArmTest::create($project_id, 2);
		$event_id = EventTest::create($arm_id, 2);

		$form_name = 'formX_abc123';
		EventFormTest::create($event_id, $form_name);

		$proj = new Project($project_id);

		$GLOBALS['Proj'] = $proj;

		$GLOBALS['user_rights'] = array(
			'data_entry' => '[foo,129][bar,130]',

			// Adding these avoids unit test warnings
			'data_export_instruments' => null,
			'data_export_tool' => null,
		);
		$ur = new UserRights();
		$GLOBALS['user_rights'] = $ur->setFormLevelPrivileges($GLOBALS['user_rights']);

		// This additionally tests that "view-edit" is encoded as "130" and "read-only" as "129"
		$this->assertEquals(UserRights::encodeDataViewingRights("view-edit"), $GLOBALS['user_rights']['forms']['bar']);
		$this->assertEquals(UserRights::encodeDataViewingRights("read-only"), $GLOBALS['user_rights']['forms']['foo']);

		unset($GLOBALS['Proj'], $GLOBALS['user_rights']);

        // @todo: undef() function currently not doing anything
//		undef('PROJECT_ID');
	}
	
//	public function testGetRoles()
//	{
//		$project_id = ProjectTest::getTestProjectID1();
//		define('PROJECT_ID', $project_id);
//
//		$roles = UserRights::getRoles();
//		$this->assertEquals(0, count($roles));
//
//		$roles = UserRights::getRoles();
//		$this->assertEquals(1, count($roles));
//
//		undef('PROJECT_ID');
//	}
	
	public function testGetRightsAllUsers()
	{
		$this->markTestIncomplete();
		return;
		
		$project_id = ProjectTest::getTestProjectID1();

		$users = UserRights::getRightsAllUsers();
		$this->assertEquals(0, count($users));

		//
		$ui_id = UserTest::getTestUserId1();
		$username = UserTest::getUsername($ui_id);
		UserRightTest::create($project_id, $username);

		$users = UserRights::getRightsAllUsers();
		$this->assertEquals(1, count($users));

        // @todo: undef() function currently not doing anything
//		undef('PROJECT_ID');
	}
	
	public function testGetSuperUserPrivileges()
	{
		$this->markTestIncomplete();
		return;
		
		$project_id = ProjectTest::getTestProjectID1();

		$ui_id = UserTest::getTestUserId1();
		$username = UserTest::getUsername($ui_id);
		
		UserRightTest::create($project_id, $username);
		UserRightTest::update($project_id, $username, 'data_quality_resolution', 2);

		$project = new Project($project_id);
		$GLOBALS['Proj'] = $project;

		if(!defined('PROJECT_ID')){
			define('PROJECT_ID', $project_id);
		}
		define('APP_NAME', 'x');

		$users = UserRights::getRightsAllUsers();
		$this->assertEquals(2, $users[$username]['data_quality_resolution']);

        // @todo: undef() function currently not doing anything
//		undef('SUPER_USER');

		// TODO: why is SUPER_USER still defined?
		//$result = $ur->checkPrivileges('x');

        // @todo: undef() function currently not doing anything
//		undef('PROJECT_ID', 'APP_NAME');
	}
	
	public function testCheckPrivileges()
	{
		$project_id = ProjectTest::getTestProjectID1();
		
		$project = new Project($project_id);
		$GLOBALS['Proj'] = $project;

		$ur = new UserRights();
		$this->assertTrue($ur->checkPrivileges($project->project['project_name']));
	}
	
	public function testCreateUserRight()
	{
		$this->markTestIncomplete();
		return;
		
		$ui_id = UserTest::getTestUserId1();
		$username = UserTest::getUsername($ui_id);

		$project_id = ProjectTest::getTestProjectID1();

		$count = rowCount('redcap_user_rights');

		UserRightTest::create($project_id, $username);
		
		$this->assertGreaterThan($count, rowCount('redcap_user_rights'));

		$db = new RedCapDB();
		$db->setAPIToken($username, $project_id);

		return UserRightTest::getAPIToken($username, $project_id);
	}

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}

