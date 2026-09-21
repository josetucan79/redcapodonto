<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use Vanderbilt\REDCap\Classes\Email\Rules\RuleQuery;
use Vanderbilt\REDCap\Classes\Email\Configuration\Condition;

class UserExpirationRule extends BaseStrategy
{
    const FIELD = 'user_expiration';

    public function toRuleQuery(): RuleQuery
    {
        // 1. Map the textual condition (e.g. "is within") to an SQL operator
        $condition = $this->getCondition();
        $params = $this->getValues();
        $conditionExpression = Condition::fromString($condition)->applyToValues($params);

        // 2. Build your partial SQL snippet
        $queryString = "SELECT ui_id, username, user_email
            FROM redcap_user_information WHERE user_lastactivity {$conditionExpression}";

        // 3. Return a RuleQuery with snippet + params
        return new RuleQuery($queryString, $params);
    }

}
