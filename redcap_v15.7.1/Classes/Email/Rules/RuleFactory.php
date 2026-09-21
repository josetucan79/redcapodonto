<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules;

use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\ProjectPurposeRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserActivityRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserAuthenticationTypeRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserEmailRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserExpirationRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserInProjectRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserPrivilegesRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserStatusRule;

class RuleFactory
{
    public static function create(string $field, string $condition, array $values): RuleNode
    {
        
        return match ($field) {
            UserActivityRule::key()             => new UserActivityRule($condition, $values),
            UserStatusRule::key()               => new UserStatusRule($condition, $values),
            UserPrivilegesRule::key()           => new UserPrivilegesRule($condition, $values),
            UserEmailRule::key()                => new UserEmailRule($condition, $values),
            ProjectPurposeRule::key()           => new ProjectPurposeRule($condition, $values),
            UserAuthenticationTypeRule::key()   => new UserAuthenticationTypeRule($condition, $values),
            UserExpirationRule::key()           => new UserExpirationRule($condition, $values),
            UserInProjectRule::key()            => new UserInProjectRule($condition, $values),

            default         => throw new \InvalidArgumentException("No rule class found for field: $field", 404)
        };
    }
}

