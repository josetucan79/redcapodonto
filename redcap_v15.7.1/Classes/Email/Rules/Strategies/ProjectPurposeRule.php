<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use InvalidArgumentException;
use Vanderbilt\REDCap\Classes\Email\Rules\RuleQuery;
use Vanderbilt\REDCap\Classes\Email\Configuration\Condition;
use Vanderbilt\REDCap\Classes\Email\Configuration\Conditions;

class ProjectPurposeRule extends BaseStrategy
{
    const FIELD = 'project_purpose';

    const PURPOSE_PRACTICE = 'practice';
    const PURPOSE_OPERATIONAL_SUPPORT = 'operational_support';
    const PURPOSE_RESEARCH = 'research';
    const PURPOSE_QUALITY_IMPROVEMENT = 'quality_improvement';
    const PURPOSE_OTHER = 'other';

    public static function purpose_options() {
        return [
            self::PURPOSE_PRACTICE            => 'Practice',
            self::PURPOSE_OPERATIONAL_SUPPORT => 'Operational Support',
            self::PURPOSE_RESEARCH            => 'Research',
            self::PURPOSE_QUALITY_IMPROVEMENT => 'Quality Improvement',
            self::PURPOSE_OTHER               => 'Other',
        ];
    }

    public function toRuleQuery(): RuleQuery
    {
        $condition = $this->getCondition();
        if(!in_array($condition, [Conditions::IS, Conditions::IS_NOT]))
            throw new InvalidArgumentException("Invalid condition $condition for the 'project purpose' rule");
    
        // 1. Map the textual condition (e.g. "is within") to an SQL operator
        $values = $this->getValues();
        $purposeCode = $this->getPurposeCode($values[0] ?? null);
        $params = [$purposeCode];
        $conditionExpression = Condition::fromString($condition)->applyToValues($params);

        // 2. Build your partial SQL snippet
        $queryString = "SELECT DISTINCT ui_id FROM redcap_user_information AS ui
            LEFT JOIN redcap_user_rights ur ON ui.username = ur.username
            LEFT JOIN redcap_projects AS p ON ur.project_id = p.project_id
            WHERE p.purpose $conditionExpression";

        // 3. Return a RuleQuery with snippet + params
        return new RuleQuery($queryString, $params);
    }
    
    private function getPurposeCode($purpose){
        $code = null;
        match ($purpose) {
            self::PURPOSE_PRACTICE              => $code = 0,
            self::PURPOSE_OTHER                 => $code = 1,
            self::PURPOSE_RESEARCH              => $code = 2,
            self::PURPOSE_QUALITY_IMPROVEMENT   => $code = 3,
            self::PURPOSE_OPERATIONAL_SUPPORT   => $code = 4,

            default => throw new InvalidArgumentException("Invalid puppose '$purpose'")
        };
        return $code;
    }

}
