<?php
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
	protected function setUp(): void
	{
		db_connect(false);
	}

	public static function getTestUserId1()
	{
		throw new \Exception('Not yet implemented');
	}

	public static function getTestUserId2()
	{
		throw new \Exception('Not yet implemented');
	}

	public static function update($ui_id, $field, $value)
	{
		$sql = "
			UPDATE 
			    
			SET $field = '$value'
			WHERE ui_id = $ui_id
			LIMIT 1
		";
		$q = db_query($sql);
		return ($q && $q !== false) ? db_affected_rows() : 0;
	}
	
	public static function updateAuthField($username, $field, $value)
	{
		$sql = "
			UPDATE redcap_auth
			SET $field = '$value'
			WHERE username = '$username'
			LIMIT 1
		";
		$q = db_query($sql);
		return ($q && $q !== false) ? db_affected_rows() : 0;
	}

	public static function getEmail($ui_id, $number='')
	{
		$field = "user_email$number";
		
		$sql = "
			SELECT $field
			FROM redcap_user_information
			WHERE ui_id = $ui_id
		";
		$q = db_query($sql);
		if($q && $q !== false)
		{
			return db_result($q, 0);
		}
		return null;
	}
	
	public static function getUsername($ui_id)
	{
		$sql = "
			SELECT username
			FROM redcap_user_information
			WHERE ui_id = $ui_id
		";
		$q = db_query($sql);
		if($q && $q !== false)
		{
			return db_result($q, 0);
		}
		return null;
	}
	
	public static function createUserAuth($username)
	{
		$sql = "
			INSERT INTO redcap_auth (
				username
			) VALUES (
				'$username'
			)
		";
		$q = db_query($sql);
		return ($q && $q !== false);
	}
	
	// tests

	public function testAddUser()
	{
		$this->markTestIncomplete();
		return;
		
 		$count = rowCount('redcap_auth');
 		$count2 = rowCount('redcap_user_information');

		$ui_id = UserTest::getTestUserId1();

		$this->assertGreaterThan(0, $ui_id);
		$this->assertGreaterThan($count, rowCount('redcap_auth'));
		$this->assertGreaterThan($count2, rowCount('redcap_user_information'));

		return $ui_id;
	}
	
//	public function testGetProjectUsernames()
//	{
//		$usernames = User::getProjectUsernames();
//		$this->assertEquals(array(), $usernames);
//
//		$project_id = ProjectTest::getTestProjectID1();
//
//		$ui_id = UserTest::getTestUserId1();
//		$username = UserTest::getUsername($ui_id);
//		UserRightTest::create($project_id, $username);
//		$usernames = User::getProjectUsernames(array(), false, $project_id);
//		$this->assertEquals(array($username => $username), $usernames);
//
//		$ui_id2 = UserTest::getTestUserId2();
//		$username2 = UserTest::getUsername($ui_id2);
//		UserRightTest::create($project_id, $username2);
//
//		$usernames = User::getProjectUsernames(array($username2), false, $project_id);
//		$this->assertEquals(array($username => $username), $usernames);
//	}
	
	public function testEmailInDomainAllowlist()
	{
		$GLOBALS['email_domain_allowlist'] = "";
		$this->assertNull(User::emailInDomainAllowlist());

		$GLOBALS['email_domain_allowlist'] = "foo.com\r\nbar.com";
		$this->assertTrue(User::emailInDomainAllowlist('joe@foo.com'));
	}
	
	public function testSetUserAccessDashboardViewTimestamp()
	{
		$this->markTestIncomplete();
		return;
		
		$ui_id = UserTest::getTestUserId1();
		$username = UserTest::getUsername($ui_id);

		User::setUserAccessDashboardViewTimestamp($username);

		$sql = "
			SELECT user_access_dashboard_view
			FROM redcap_user_information
			WHERE username = '$username'
		";
		$q = db_query($sql);
		
		$this->assertEquals(NOW, db_result($q, 0));		
	}
	
	public function testGetUserInfoByUsername()
	{
		$this->markTestIncomplete();
		return;
		
		$ui_id = UserTest::getTestUserId1();
		$username = UserTest::getUsername($ui_id);
		$array = array('ui_id' => $ui_id);
		$this->assertEquals($array['ui_id'], User::getUserInfo($username)['ui_id']);
	}
	
	public function testGetUserInfoByUiid()
	{
		$this->assertFalse(User::getUserInfoByUiid('foo'));

		$ui_id = $this->testAddUser();
		$array = array('ui_id' => $ui_id);
		$this->assertEquals($array['ui_id'], User::getUserInfoByUiid($ui_id)['ui_id']);
	}
	
	public function testUpdateUserFirstVisit()
	{
		$this->markTestIncomplete();
		return;
		
		$ui_id = UserTest::getTestUserId1();
		$username = UserTest::getUsername($ui_id);

		User::updateUserFirstVisit($username);

		$sql = "
			SELECT user_firstvisit
			FROM redcap_user_information
			WHERE username = '$username'
		";
		$q = db_query($sql);
		
		$this->assertEquals(NOW, db_result($q, 0));
	}
	
	public function testIsTableUser()
	{
		//$this->testEmptyUserAuth();

		$testUsername = 'redcap_unit_test_username';
		$this->assertFalse(User::isTableUser($testUsername));
		$this->createUserAuth($testUsername);
		$this->assertTrue(User::isTableUser($testUsername));

		db_query('DELETE FROM redcap_auth WHERE username = ?', $testUsername);
	}
	
	public function testSetUserEmail()
	{
		$this->markTestIncomplete();
		return;
		
		//$this->testEmptyUserInformation();
		$ui_id = UserTest::getTestUserId1();

		$s = hashStr();
		$email = "$s@$s.com";
		User::setUserEmail($ui_id, $email, 1);

		$sql = "
			SELECT user_email
			FROM redcap_user_information
			WHERE ui_id = $ui_id
		";
		$q = db_query($sql);
		$this->assertEquals($email, db_result($q, 0));
	}
}
