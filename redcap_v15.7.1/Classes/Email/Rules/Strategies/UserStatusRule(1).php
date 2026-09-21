<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use Vanderbilt\REDCap\Classes\Email\Rules\RuleQuery;
use Vanderbilt\REDCap\Classes\Email\Configuration\Conditions;

class UserStatusRule extends BaseStrategy
{
    const FIELD = 'user_status';

    const STATUS_ACTIVE = 'active';
    const STATUS_LOGGED_IN = 'logged_in';
    const STATUS_CDIS_USER = 'CDIS_user';
    const STATUS_PROJECT_OWNER = 'project_owner';

    public static function status_options() {
        return [
            self::STATUS_ACTIVE        => 'active',
            self::STATUS_LOGGED_IN     => 'logged in',
            self::STATUS_CDIS_USER     => 'CDIS user',
            // self::STATUS_PROJECT_OWNER => 'project owner'
        ];
    }

    public function toRuleQuery(): RuleQuery
    {
        // 1. Map the textual condition (e.g. "is within") to an SQL operator
        $condition = $this->getCondition();
        $params = [];
        $values = $this->getValues();
        $status = $values[0] ?? null;
        // $conditionExpression = Condition::fromString($condition)->applyToValues($params);

        // 2. Build your partial SQL snippet
        $query = '';
        switch ($status) {
            case self::STATUS_ACTIVE:
                $query = $this->getActiveStatusQuery($condition);
                break;
            case self::STATUS_LOGGED_IN:
                $query = $this->getLoggedInQuery($condition, $params);
                break;
            case self::STATUS_CDIS_USER:
                $query = $this->getCdisUsersQuery($condition);
                break;
            case self::STATUS_PROJECT_OWNER:
                $query = $this->getProjectOwnersQuery($condition, $params);
                break;
            default:
                throw new \InvalidArgumentException("Invalid value provided for this rule: $status");
                break;
        }

        // 3. Return a RuleQuery with snippet + params
        return new RuleQuery($query, $params);
    }

    
    private function getLogoutWindow() {
        global $autologout_timer;
        return date("Y-m-d H:i:s", mktime(date("H"),date("i")-$autologout_timer,date("s"),date("m"),date("d"),date("Y")));
    }
    
    private function getActiveStatusQuery($condition) {
        $query = "SELECT ui_id FROM redcap_user_information WHERE user_suspended_time";
        match ($condition) {
            Conditions::IS => $query .= " IS NULL AND user_firstactivity IS NOT NULL",
            Conditions::IS_NOT => $query .= " IS NOT NULL",
            default => throw new \InvalidArgumentException("Invalid condition: $condition"),
        };
        return $query;
    }

    private function getLoggedInQuery($condition, &$params) {
        $sinceTimestamp = $this->getLogoutWindow();
        $params = [$sinceTimestamp];
        $baseQuery = "SELECT ui_id FROM redcap_user_information AS ui"
            ." LEFT JOIN redcap_log_view AS lv ON ui.username = lv.user"
            ." WHERE lv.ts >= ?"
            ." AND lv.user != '[survey respondent]'";
        match ($condition) {
            Conditions::IS => $query = $baseQuery,
            Conditions::IS_NOT => $query = "SELECT ui_id FROM redcap_user_information WHERE ui_id NOT IN ($baseQuery)",
            default => throw new \InvalidArgumentException("Invalid condition: $condition"),
        };
        return $query;
    }

    private function getCdisUsersQuery($condition) {
        $query = '';
        match ($condition) {
            Conditions::IS => $query = "SELECT ui_id FROM redcap_user_information AS ui
            LEFT JOIN redcap_user_rights AS ur ON ur.username = ui.username
            WHERE (
                ur.realtime_webservice_adjudicate = 1
                OR ui.ui_id IN (SELECT DISTINCT token_owner FROM redcap_ehr_access_tokens)
            )",
            Conditions::IS_NOT => $query = "SELECT ui_id 
            FROM redcap_user_information ui
            WHERE NOT EXISTS (
                SELECT 1
                FROM redcap_user_rights ur 
                WHERE ur.username = ui.username
                  AND (
                      ur.realtime_webservice_adjudicate = 1
                      OR ui.ui_id IN (SELECT DISTINCT token_owner FROM redcap_ehr_access_tokens)
                  )
            )",
            default => throw new \InvalidArgumentException("Invalid condition: $condition"),
        };
        return $query;

    }

    private function getProjectOwnersQuery($condition, &$params=[]) {
        if($condition === Conditions::IS) $params = [1];
        else if($condition === Conditions::IS_NOT) $params = [0];
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
