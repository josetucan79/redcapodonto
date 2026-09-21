<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use DateTime;
use Exception;
use InvalidArgumentException;
use Vanderbilt\REDCap\Classes\Email\Rules\RuleQuery;
use Vanderbilt\REDCap\Classes\Email\Configuration\Condition;
use Vanderbilt\REDCap\Classes\Email\Configuration\Conditions;

class UserActivityRule extends BaseStrategy
{
    const FIELD = 'user_activity';

    const INTERVAL_PAST_WEEK = 'past_week';
    const INTERVAL_PAST_MONTH = 'past_month';
    const INTERVAL_PAST_3_MONTHS = 'past_3_months';
    const INTERVAL_PAST_6_MONTHS = 'past_6_months';
    const INTERVAL_PAST_12_MONTHS = 'past_12_months';

    public static function user_activity_options() {
        return [
            self::INTERVAL_PAST_WEEK        => 'past week',
            self::INTERVAL_PAST_MONTH       => 'past month',
            self::INTERVAL_PAST_3_MONTHS    => 'past 3 months',
            self::INTERVAL_PAST_6_MONTHS    => 'past 6 months',
            self::INTERVAL_PAST_12_MONTHS   => 'past 12 months',
        ];
    }

    private function convertInterval($intervalKey) {
        $intervals = static::user_activity_options();
        $interval = null;
        match ($intervalKey) {
            self::INTERVAL_PAST_WEEK        => $interval = '1 week',
            self::INTERVAL_PAST_MONTH       => $interval = '1 month',
            self::INTERVAL_PAST_3_MONTHS    => $interval = '3 months',
            self::INTERVAL_PAST_6_MONTHS    => $interval = '6 months',
            self::INTERVAL_PAST_12_MONTHS   => $interval = '12 months',
            default => throw new InvalidArgumentException("Invalid interval $intervalKey", 400)
        };
        return $interval;
    }

    public function toRuleQuery(): RuleQuery
    {
        // 1. Map the textual condition (e.g. "is within") to an SQL operator
        $allowedConditions = [Conditions::IS_WITHIN, Conditions::IS_NOT_WITHIN];
        $condition = $this->getCondition();
        if(!in_array($condition, $allowedConditions)) throw new Exception("Condition $condition is not allowed in the user activity rule", 400);

        
        $values = $this->getValues();
        $intervalValue = $values[0] ?? null;
        $params = [];
        $params[] = $this->convertInterval($intervalValue);
        $params[] = $values[1] ?? new DateTime('now'); // apply current time if none provided
        $conditionExpression = Condition::fromString($condition)->applyToValues($params);

        // 2. Build your partial SQL snippet
        $queryString = "SELECT ui_id"
            . " FROM ("
                 . "SELECT ui_id,"
                    . " GREATEST("
                    . " COALESCE(user_lastactivity, CAST('2004-08-01 00:00:00' AS DATETIME)),"
                    . " COALESCE(user_lastlogin, CAST('2004-08-01 00:00:00' AS DATETIME))"
                    . " ) AS computed_lastactivity"
                 ." FROM redcap_user_information"
            . ") AS sub"
            . " WHERE computed_lastactivity {$conditionExpression}";

        // 3. Return a RuleQuery with snippet + params
        return new RuleQuery($queryString, $params);
    }

}
