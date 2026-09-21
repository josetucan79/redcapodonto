<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Email\Configuration\Conditions;
use Vanderbilt\REDCap\Classes\Email\Rules\GroupNode;
use Vanderbilt\REDCap\Classes\Email\Rules\QueryBuilder;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserActivityRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserEmailRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserStatusRule;
use Vanderbilt\REDCap\UnitTests\Utility\UserManager;

/**
 * Test case for the QueryBuilder class
 */
class QueryBuilderTest extends TestCase
{
    


    public function testUserStatusRule() {
        $userStatusRule = new UserStatusRule(Conditions::IS, [UserStatusRule::STATUS_ACTIVE]);
        $ruleQuery = $userStatusRule->toRuleQuery();

        $userStatusRule1 = new UserStatusRule(Conditions::IS_NOT, [UserStatusRule::STATUS_ACTIVE]);
        $ruleQuery1 = $userStatusRule1->toRuleQuery();
        
        $this->assertStringContainsString('user_suspended_time IS NULL', $ruleQuery->getQueryString());
        $this->assertStringContainsString('user_suspended_time IS NOT NULL', $ruleQuery1->getQueryString());
    }

    /**
     * Test building a query from a simple GroupNode
     */
    public function testBuildQueryFromSimpleGroup(): void
    {
        // Create rule nodes
        $userStatusRule = new UserStatusRule(Conditions::IS, [UserStatusRule::STATUS_ACTIVE]);
        $userEmailRule = new UserEmailRule(Conditions::BEGINS_WITH, ['test']);

        // Create a group node
        $groupNode = new GroupNode();
        $groupNode->addChild(GroupNode::OPERATOR_AND, $userStatusRule);
        $groupNode->addChild(GroupNode::OPERATOR_OR_NOT, $userEmailRule);

        // Build the query
        $queryBuilder = new QueryBuilder();
        $ruleQuery = $queryBuilder->buildQuery($groupNode);

        // Expected query (formatted for readability, actual will be one line)
        $subQuery = <<<QUERY
        (SELECT ui_id FROM redcap_user_information WHERE ui_id IN (SELECT ui_id FROM redcap_user_information WHERE user_suspended_time IS NULL) OR ui_id NOT IN (SELECT ui_id
            FROM redcap_user_information WHERE
            user_email LIKE ?
            OR user_email2 LIKE ?
            OR user_email3 LIKE ?))
        QUERY;
        $expectedQuery = $queryBuilder->getMainQuery() ." ".$subQuery;
        $actualQuery = $ruleQuery->getQueryString();
        $this->assertStringContainsString('user_suspended_time IS NULL', $actualQuery);
        $this->assertStringContainsString('user_email LIKE ?', $actualQuery);
    }

    /**
     * Test building a query from a more complex nested GroupNode
     */
    public function testBuildQueryFromNestedGroup(): void
    {
        // Create rule nodes
        $userStatusRule = new UserStatusRule(Conditions::IS, [UserStatusRule::STATUS_ACTIVE]);
        $userEmailRule = new UserEmailRule(Conditions::BEGINS_WITH, ['test']);
        $userActivityRule = new UserActivityRule(Conditions::IS_WITHIN, [UserActivityRule::INTERVAL_PAST_6_MONTHS, new DateTime()]);

        // Create a nested group
        $nestedGroup = new GroupNode();
        $nestedGroup->addChild(GroupNode::OPERATOR_OR, $userEmailRule);
        $nestedGroup->addChild(GroupNode::OPERATOR_AND, $userActivityRule);

        // Create a parent group
        $parentGroup = new GroupNode();
        $parentGroup->addChild(GroupNode::OPERATOR_AND, $userStatusRule);
        $parentGroup->addChild(GroupNode::OPERATOR_AND_NOT, $nestedGroup);

        // Build the query
        $queryBuilder = new QueryBuilder();
        $ruleQuery = $queryBuilder->buildQuery($parentGroup);
        $actualQuery = $ruleQuery->getQueryString();

        // We're asserting that the query was generated without errors
        // The exact query would be complex, so we're just checking it contains expected parts
        $this->assertStringContainsString('user_suspended_time IS NULL', $actualQuery);
        $this->assertStringContainsString('user_email LIKE ?', $actualQuery);
        $this->assertStringContainsString('computed_lastactivity BETWEEN ? AND ?', $actualQuery);
        $this->assertStringContainsString('AND ui_id NOT IN', $actualQuery);
    }

    /**
     * Test building a query from an empty GroupNode
     */
    public function testBuildQueryFromEmptyGroup(): void
    {
        // Create an empty group node
        $emptyGroup = new GroupNode();

        // Build the query
        $queryBuilder = new QueryBuilder();
        $ruleQuery = $queryBuilder->buildQuery($emptyGroup);
        $actualQuery = $ruleQuery->getQueryString();
        $expectedQuery = "ui_id IN (SELECT ui_id FROM redcap_user_information WHERE 1=0)";

        $this->assertStringContainsString($expectedQuery, $actualQuery, 'The query for an empty group is not valid');
    }
}