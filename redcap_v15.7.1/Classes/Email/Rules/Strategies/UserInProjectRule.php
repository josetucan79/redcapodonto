<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use InvalidArgumentException;
use Vanderbilt\REDCap\Classes\Email\Rules\RuleQuery;
use Vanderbilt\REDCap\Classes\Email\Configuration\Condition;
use Vanderbilt\REDCap\Classes\Email\Configuration\Conditions;

class UserInProjectRule extends BaseStrategy
{
    const FIELD = 'user_in_project';


    public function toRuleQuery(): RuleQuery
    {
        $condition = $this->getCondition();
        if(!in_array($condition, [Conditions::IS, Conditions::IS_NOT]))
            throw new InvalidArgumentException("Invalid condition $condition for the 'project purpose' rule");
    
        // 1. Map the textual condition (e.g. "is within") to an SQL operator
        $values = $this->getValues();
        $projectID = $values[0] ?? null;
        $params = [$projectID];
        $conditionExpression = Condition::fromString($condition)->applyToValues($params);

        // 2. Build your partial SQL snippet
        $queryString = "SELECT DISTINCT ui_id FROM redcap_user_information AS ui
            LEFT JOIN redcap_user_rights ur ON ui.username = ur.username
            LEFT JOIN redcap_projects AS p ON ur.project_id = p.project_id
            WHERE p.project_id $conditionExpression";

        // 3. Return a RuleQuery with snippet + params
        return new RuleQuery($queryString, $params);
    }
}
