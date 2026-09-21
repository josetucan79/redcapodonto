<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use Vanderbilt\REDCap\Classes\Email\Rules\RuleQuery;
use Vanderbilt\REDCap\Classes\Email\Configuration\Conditions;

class UserPrivilegesRule extends BaseStrategy
{
    const FIELD = 'user_privileges';

    const PRIVILEGE_MOBILE_APP_RIGHTS = 'mobile_app_rights';
    const PRIVILEGE_API_TOKEN = 'API_token';
    const PRIVILEGE_PROJECT_OWNER = 'project_owner';
    
    public static function privileges_options() {
        return [
            self::PRIVILEGE_MOBILE_APP_RIGHTS   => 'mobile app rights',
            self::PRIVILEGE_API_TOKEN           => 'API token',
            self::PRIVILEGE_PROJECT_OWNER       => 'project ownership',
        ];
    }

    public function toRuleQuery(): RuleQuery
    {
        // 1. Map the textual condition (e.g. "is within") to an SQL operator
        $condition = $this->getCondition();
        $params = [];
        $values = $this->getValues();
        $value = $values[0] ?? null;
        $query = '';
        switch ($value) {
            case self::PRIVILEGE_API_TOKEN:
                $query = $this->getUsersWithApiTokenQuery($condition, $params);
                break;
            case self::PRIVILEGE_MOBILE_APP_RIGHTS:
                $query = $this->getUsersWithMobileAppRightsQuery($condition, $params);
                break;
            case self::PRIVILEGE_PROJECT_OWNER:
                $query = $this->getProjectOwnersQuery($condition, $params);
                break;
            default:
                throw new \InvalidArgumentException("Invalid value provided for this rule: $value");
                break;
        }

        // $conditionExpression = Condition::fromString($condition)->applyToValues($params);
        
        // 3. Return a RuleQuery with snippet + params
        return new RuleQuery($query, $params);
    }


    private function getUsersWithApiTokenQuery($condition, &$params=[]) {
        if($condition === Conditions::HAS) $params = [1];
        else if($condition === Conditions::HAS_NOT) $params = [0];
        else throw new \InvalidArgumentException("Invalid condition: $condition");

        $query = "SELECT ui_id FROM redcap_user_information AS ui
            LEFT JOIN redcap_user_rights AS ur ON ur.username = ui.username
            GROUP BY ui_id
            HAVING MAX(IF(ur.api_token IS NOT NULL, 1, 0)) = ?";
        return $query;
    }

    private function getUsersWithMobileAppRightsQuery($condition, &$params=[]) {
        if($condition === Conditions::HAS) $params = [1];
        else if($condition === Conditions::HAS_NOT) $params = [0];
        else throw new \InvalidArgumentException("Invalid condition: $condition");

        $query = "SELECT ui_id FROM redcap_user_information AS ui
            LEFT JOIN redcap_user_rights AS ur ON ur.username = ui.username
            LEFT JOIN redcap_user_roles AS r ON r.role_id = ur.role_id
            GROUP BY ui_id
            HAVING MAX(IF(((r.role_id IS NULL AND ur.mobile_app = 1) OR (r.role_id IS NOT NULL AND r.mobile_app = 1)), 1, 0)) = ?";
        return $query;
    }

    private function getProjectOwnersQuery($condition, &$params=[]) {
        if($condition === Conditions::HAS) $params = [1];
        else if($condition === Conditions::HAS_NOT) $params = [0];
        else throw new \InvalidArgumentException("Invalid condition: $condition");

        return "SELECT ui_id FROM redcap_user_information AS ui
            LEFT JOIN redcap_user_rights ur ON ui.username = ur.username
            LEFT JOIN redcap_projects AS p ON ur.project_id = p.project_id AND p.date_deleted IS NULL AND p.completed_time IS NULL
            LEFT JOIN redcap_user_roles AS r ON r.role_id = ur.role_id
            GROUP BY ui.ui_id
            HAVING MAX(IF(
				(
					(
						(r.role_id IS NULL AND (ur.user_rights = 1 OR ur.design = 1))
						OR (r.role_id IS NOT NULL AND (r.user_rights = 1 OR r.design = 1))
					)
				), 1, 0
		    )) = ?";
    }

}
