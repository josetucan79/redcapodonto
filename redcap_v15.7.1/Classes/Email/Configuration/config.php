<?php

namespace Vanderbilt\REDCap\Classes\Email\Configuration;

use Vanderbilt\REDCap\Classes\Email\Configuration\ConditionConfig as CF;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserEmailRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserStatusRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserActivityRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserExpirationRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserPrivilegesRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\ProjectPurposeRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserAuthenticationTypeRule;
use Vanderbilt\REDCap\Classes\Email\Rules\Strategies\UserInProjectRule;

/**
 * this file defines the rules available in the Email Users page
 */

$fieldsConfig = [
    new FieldConfig(
        UserStatusRule::key(),
        'User Status',
        [
            Conditions::IS      => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, UserStatusRule::status_options()),
            Conditions::IS_NOT  => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, UserStatusRule::status_options()),
        ]
    ),
    new FieldConfig(
        UserPrivilegesRule::key(),
        'User Privileges',
        [
            Conditions::HAS     => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, UserPrivilegesRule::privileges_options()),
            Conditions::HAS_NOT => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, UserPrivilegesRule::privileges_options()),
        ]
    ),
    new FieldConfig(
        UserActivityRule::key(),
        'User Activity',
        [
            Conditions::IS_WITHIN       => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, UserActivityRule::user_activity_options()),
            Conditions::IS_NOT_WITHIN   => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, UserActivityRule::user_activity_options()),
        ]
    ),
    new FieldConfig(
        UserAuthenticationTypeRule::key(),
        'User Authentication Type',
        [
            Conditions::IS     => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, UserAuthenticationTypeRule::type_options()),
            Conditions::IS_NOT => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, UserAuthenticationTypeRule::type_options()),
        ]
    ),
    new FieldConfig(
        UserEmailRule::key(),
        'User Email',
        [
            Conditions::EQUAL               => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
            Conditions::NOT_EQUAL           => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
            Conditions::CONTAINS            => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
            Conditions::DOES_NOT_CONTAIN    => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
            Conditions::BEGINS_WITH         => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
            Conditions::DOES_NOT_BEGIN_WITH => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
            Conditions::ENDS_WITH           => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
            Conditions::DOES_NOT_END_WITH   => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
            Conditions::IS_NULL             => new ConditionConfig(CF::INPUT_TYPE_NULL, false),
            Conditions::IS_NOT_NULL         => new ConditionConfig(CF::INPUT_TYPE_NULL, false),
        ]
    ),
    new FieldConfig(
        UserExpirationRule::key(),
        'User Expiration Date',
        [
            Conditions::EQUAL               => new ConditionConfig(CF::INPUT_TYPE_DATE, true),
            Conditions::NOT_EQUAL           => new ConditionConfig(CF::INPUT_TYPE_DATE, true),
            Conditions::LESS_THAN           => new ConditionConfig(CF::INPUT_TYPE_DATE, true),
            Conditions::LESS_THAN_EQUAL     => new ConditionConfig(CF::INPUT_TYPE_DATE, true),
            Conditions::GREATER_THAN        => new ConditionConfig(CF::INPUT_TYPE_DATE, true),
            Conditions::GREATER_THAN_EQUAL  => new ConditionConfig(CF::INPUT_TYPE_DATE, true),
            Conditions::IS_BETWEEN          => new ConditionConfig(CF::INPUT_TYPE_DATE_RANGE, true),
            Conditions::IS_NOT_BETWEEN      => new ConditionConfig(CF::INPUT_TYPE_DATE_RANGE, true),
        ]
    ),
    new FieldConfig(
        ProjectPurposeRule::key(),
        'Project Purpose',
        [
            Conditions::IS     => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, ProjectPurposeRule::purpose_options()),
            Conditions::IS_NOT => new ConditionConfig(CF::INPUT_TYPE_SELECT, true, ProjectPurposeRule::purpose_options()),
        ]
    ),
    new FieldConfig(
        UserInProjectRule::key(),
        'Assigned Project ID',
        [
            Conditions::IS     => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
            Conditions::IS_NOT => new ConditionConfig(CF::INPUT_TYPE_STRING, true),
        ]
    ),
];

return $fieldsConfig;