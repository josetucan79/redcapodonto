<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use Vanderbilt\REDCap\Classes\Email\Rules\RuleQuery;
use Vanderbilt\REDCap\Classes\Email\Configuration\Condition;

class UserEmailRule extends BaseStrategy
{
    const FIELD = 'user_email';

    public function toRuleQuery(): RuleQuery
    {
        // 1. Map the textual condition (e.g. "is within") to an SQL operator
        $condition = $this->getCondition();
        $params = $this->getValues();
        $conditionExpression = Condition::fromString($condition)->applyToValues($params);

        // 2. Build your partial SQL snippet
        $queryString = "SELECT ui_id
            FROM redcap_user_information WHERE
            user_email {$conditionExpression}
            OR user_email2 {$conditionExpression}
            OR user_email3 {$conditionExpression}";
        
        // i need the same params 3 times, one time per user_email field
        $repeatedParams = array_merge($params, $params, $params);

        // 3. Return a RuleQuery with snippet + params
        return new RuleQuery($queryString, $repeatedParams);
    }

}
