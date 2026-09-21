<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use Vanderbilt\REDCap\Classes\Email\Rules\RuleQuery;
use Vanderbilt\REDCap\Classes\Email\Configuration\Condition;
use Vanderbilt\REDCap\Classes\Email\Configuration\Conditions;

class UserAuthenticationTypeRule extends BaseStrategy
{
    const FIELD = 'user_authentication_type';

    const TYPE_TABLE_BASED = 'table_based';
    const TYPE_LDAP = 'LDAP';

    public function toRuleQuery(): RuleQuery
    {
        // 1. Map the textual condition (e.g. "is within") to an SQL operator
        $condition = $this->getCondition();
        $params = [];
        $values = $this->getValues();
        $type = $values[0] ?? null;
        // $conditionExpression = Condition::fromString($condition)->applyToValues($params);
        
        // 2. Build your partial SQL snippet
        $query = '';
        match($type) {
            self::TYPE_TABLE_BASED  => $query = $this->getTableBased($condition),
            self::TYPE_LDAP         => $query = $this->getLdap($condition),
            default => throw new \InvalidArgumentException("Invalid value provided for this rule: $type"),
        };

        // 3. Return a RuleQuery with snippet + params
        return new RuleQuery($query, $params);
    }

    public function getTableBased($condition) {
        
        $query = '';
        match ($condition) {
            Conditions::IS => $query = "SELECT ui_id FROM redcap_user_information WHERE username IN (SELECT username FROM redcap_auth)",
            Conditions::IS_NOT => $query = "SELECT ui_id FROM redcap_user_information WHERE username NOT IN (SELECT username FROM redcap_auth)",
            default => throw new \InvalidArgumentException("Invalid condition: $condition"),
        };
        return $query;
    }

    public function getLdap($condition) {
        
        $query = '';
        match ($condition) {
            Conditions::IS => $query = "SELECT ui_id FROM redcap_user_information WHERE username NOT IN (SELECT username FROM redcap_auth)",
            Conditions::IS_NOT => $query = "SELECT ui_id FROM redcap_user_information WHERE username IN (SELECT username FROM redcap_auth)",
            default => throw new \InvalidArgumentException("Invalid condition: $condition"),
        };
        return $query;
    }

    public static function type_options() {
        return [
            self::TYPE_TABLE_BASED => 'table based',
            self::TYPE_LDAP        => 'LDAP'
        ];
    }
}
