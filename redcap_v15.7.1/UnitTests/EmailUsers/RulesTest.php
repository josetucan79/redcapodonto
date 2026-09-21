<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Email\Configuration\Conditions;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserAuthenticationTypeRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserPrivilegesRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserStatusRule;
use Vanderbilt\REDCap\UnitTests\Utility\UsersGenerator;
use Vanderbilt\REDCap\UnitTests\Utility\UserManager;
use Vanderbilt\REDCap\UnitTests\Utility\UserStatusManager;

/**
 * Test case for the QueryBuilder class
 */
class RulesTest extends TestCase
{
    const USERS_SEED = 12345;

    public function testCreateTestUsers() {
        $totalUsers = 100;
        $generator = new UsersGenerator();
        $users = $generator->generate($totalUsers, self::USERS_SEED);
        
        $counter = 0;
        foreach ($users as $username => $options) {
            if( ($counter++) < ($totalUsers-10) ) UserManager::createUser($username, $options);
            // last 10 users are LDAP
            else UserManager::createLdapUser($username, $options);
        }
        $this->assertCount($totalUsers, $users);
        return $users;
    }

    private function extractUsers($users, $positions) {
        // Re-index the associative array so that keys become 0,1,2,...
        $keys = array_keys($users);
        $filteredUsers = [];

        foreach ($positions as $pos) {
            if (isset($keys[$pos])) {
                $key = $keys[$pos];
                $filteredUsers[$key] = $users[$key];
            }
        }
        return $filteredUsers;
    }

    /**
     * @depends testCreateTestUsers
     */
    public function testUserStatusRule($users) {
        $userStatusRule = new UserStatusRule(Conditions::IS, [UserStatusRule::STATUS_ACTIVE]);
        $ruleQuery = $userStatusRule->toRuleQuery();

        $userStatusRule1 = new UserStatusRule(Conditions::IS_NOT, [UserStatusRule::STATUS_ACTIVE]);
        $ruleQuery1 = $userStatusRule1->toRuleQuery();
        
        $this->assertStringContainsString('user_suspended_time IS NULL', $ruleQuery->getQueryString());
        $this->assertStringContainsString('user_suspended_time IS NOT NULL', $ruleQuery1->getQueryString());
    }
    
    /**
     * @depends testCreateTestUsers
     */
    public function testCanFindProjectOwners($users) {
        $userStatusRule2 = new UserStatusRule(Conditions::IS, [UserStatusRule::STATUS_PROJECT_OWNER]);
        $ruleQuery2 = $userStatusRule2->toRuleQuery();

        // make project owners
        $positions = [0,7,8,9];
        $selectedUsers = $this->extractUsers($users, $positions);
        foreach ($selectedUsers as $username => $options) {
            UserStatusManager::makeProjectOwner($username);
        }

        // find project owners
        $result = db_query($ruleQuery2->getQueryString(), $ruleQuery2->getParams());
        $fetchedUsers = [];
        while ($row = db_fetch_assoc($result)) {
            $fetchedUsers[] = $row['ui_id'];
        }

        $found = 0;
        foreach ($selectedUsers as $username => $options) {
            // count all project owners that are found
            $ui_id = User::getUIIDByUsername($username);
            if(in_array($ui_id, $fetchedUsers)) $found++;
        }
        
        $this->assertEquals(count($selectedUsers), $found);
    }

    /**
     * @depends testCreateTestUsers
     */
    public function testCanFindCdisUsers($users) {
        $userStatusRule = new UserStatusRule(Conditions::IS, [UserStatusRule::STATUS_CDIS_USER]);
        $ruleQuery = $userStatusRule->toRuleQuery();

        // make project owners
        $positions = [25, 47, 89];
        $selectedUsers = $this->extractUsers($users, $positions);
        foreach ($selectedUsers as $username => $options) {
            UserStatusManager::makeCdisUser($username);
        }

        // find CDIS users
        $result = db_query($ruleQuery->getQueryString(), $ruleQuery->getParams());
        $fetchedUsers = [];
        while ($row = db_fetch_assoc($result)) {
            $fetchedUsers[] = $row['ui_id'];
        }

        $found = 0;
        foreach ($selectedUsers as $username => $options) {
            // count all project owners that are found
            $ui_id = User::getUIIDByUsername($username);
            if(in_array($ui_id, $fetchedUsers)) $found++;
        }
        
        $this->assertEquals(count($selectedUsers), $found);

        // verify that users not being CDIS users are not found
        $nonSelectedUsers = $this->extractUsers($users, [90,91]);
        $notFound = 0;
        foreach ($nonSelectedUsers as $username => $options) {
            // count all project owners that are found
            $ui_id = User::getUIIDByUsername($username);
            if(!in_array($ui_id, $fetchedUsers)) $notFound++;
        }

        $this->assertEquals(count($nonSelectedUsers), $notFound);
    }

    /**
     * @depends testCreateTestUsers
     */
    public function testCanFindApiTokenUsers($users) {
        $userStatusRule = new UserPrivilegesRule(Conditions::HAS, [UserPrivilegesRule::PRIVILEGE_API_TOKEN]);
        $ruleQuery = $userStatusRule->toRuleQuery();

        // make project owners
        $positions = [30, 31, 32];
        $selectedUsers = $this->extractUsers($users, $positions);
        foreach ($selectedUsers as $username => $options) {
            $apiToken = bin2hex(random_bytes(16));
            UserStatusManager::setApiToken($username, $apiToken);
        }

        // find API token users
        $result = db_query($ruleQuery->getQueryString(), $ruleQuery->getParams());
        $fetchedUsers = [];
        while ($row = db_fetch_assoc($result)) {
            $fetchedUsers[] = $row['ui_id'];
        }

        $found = 0;
        foreach ($selectedUsers as $username => $options) {
            // count all project owners that are found
            $ui_id = User::getUIIDByUsername($username);
            if(in_array($ui_id, $fetchedUsers)) $found++;
        }

        $this->assertEquals(count($selectedUsers), $found);
    }

    /**
     * @depends testCreateTestUsers
     */
    public function testCanFindLdapUsers($users) {
        $userStatusRule = new UserAuthenticationTypeRule(Conditions::IS, [UserAuthenticationTypeRule::TYPE_LDAP]);
        $ruleQuery = $userStatusRule->toRuleQuery();

        // make project owners
        $positions = [91, 92, 95];
        $selectedUsers = $this->extractUsers($users, $positions);

        // find API token users
        $result = db_query($ruleQuery->getQueryString(), $ruleQuery->getParams());
        $fetchedUsers = [];
        while ($row = db_fetch_assoc($result)) {
            $fetchedUsers[] = $row['ui_id'];
        }

        $found = 0;
        foreach ($selectedUsers as $username => $options) {
            // count all project owners that are found
            $ui_id = User::getUIIDByUsername($username);
            if(in_array($ui_id, $fetchedUsers)) $found++;
        }

        $this->assertEquals(count($selectedUsers), $found);

        $userStatusRule = new UserAuthenticationTypeRule(Conditions::IS_NOT, [UserAuthenticationTypeRule::TYPE_LDAP]);
        $ruleQuery = $userStatusRule->toRuleQuery();

        // find API token users
        $result = db_query($ruleQuery->getQueryString(), $ruleQuery->getParams());
        $fetchedUsers = [];
        while ($row = db_fetch_assoc($result)) {
            $fetchedUsers[] = $row['ui_id'];
        }

        $found = 0;
        foreach ($selectedUsers as $username => $options) {
            // count all project owners that are found
            $ui_id = User::getUIIDByUsername($username);
            if(in_array($ui_id, $fetchedUsers)) $found++;
        }

        $this->assertEquals(0, $found);
    }

    /**
     * @depends testCreateTestUsers
     */
    public function testCanFindTableUsers($users) {
        $userStatusRule = new UserAuthenticationTypeRule(Conditions::IS, [UserAuthenticationTypeRule::TYPE_TABLE_BASED]);
        $ruleQuery = $userStatusRule->toRuleQuery();

        // make project owners
        $positions = [81, 82, 83];
        $selectedUsers = $this->extractUsers($users, $positions);

        // find API token users
        $result = db_query($ruleQuery->getQueryString(), $ruleQuery->getParams());
        $fetchedUsers = [];
        while ($row = db_fetch_assoc($result)) {
            $fetchedUsers[] = $row['ui_id'];
        }

        $found = 0;
        foreach ($selectedUsers as $username => $options) {
            // count all project owners that are found
            $ui_id = User::getUIIDByUsername($username);
            if(in_array($ui_id, $fetchedUsers)) $found++;
        }

        $this->assertEquals(count($selectedUsers), $found);

        $userStatusRule = new UserAuthenticationTypeRule(Conditions::IS_NOT, [UserAuthenticationTypeRule::TYPE_TABLE_BASED]);
        $ruleQuery = $userStatusRule->toRuleQuery();

        // find API token users
        $result = db_query($ruleQuery->getQueryString(), $ruleQuery->getParams());
        $fetchedUsers = [];
        while ($row = db_fetch_assoc($result)) {
            $fetchedUsers[] = $row['ui_id'];
        }

        $found = 0;
        foreach ($selectedUsers as $username => $options) {
            // count all project owners that are found
            $ui_id = User::getUIIDByUsername($username);
            if(in_array($ui_id, $fetchedUsers)) $found++;
        }

        $this->assertEquals(0, $found);
    }

    /**
     * @depends testCreateTestUsers
     */
    public function testDeleteTestUsers($users) {
        $deleted = 0;
        foreach ($users as $username => $options) {
            $isDeleted = UserManager::deleteUser($username);
            $deleted += $isDeleted ? 1 : 0;
        }
        $this->assertEquals($deleted, count($users));
    }
}