<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Security;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Facades\PermissionsGateFacade as Gate;

class ActionPermissionValidator
{
    protected static array $actionPermissions = [
        ActionEntity::EVENT_REVIEWER_APPROVAL => [PermissionEntity::REVIEW_ELIGIBILITY],
        ActionEntity::EVENT_REVIEWER_REJECTION => [PermissionEntity::REVIEW_ELIGIBILITY],
        ActionEntity::EVENT_REVIEWER_RESTORE => [PermissionEntity::REVIEW_ELIGIBILITY],
        ActionEntity::EVENT_BUYER_APPROVAL => [PermissionEntity::PLACE_ORDERS],
        ActionEntity::EVENT_BUYER_REJECTION => [PermissionEntity::PLACE_ORDERS],
        ActionEntity::EVENT_PLACE_ORDER => [PermissionEntity::PLACE_ORDERS],
        ActionEntity::EVENT_SEND_EMAIL => [
            PermissionEntity::REVIEW_ELIGIBILITY,
            PermissionEntity::PLACE_ORDERS,
        ],
    ];

    public static function check(string $action): void
    {
        $permissions = self::$actionPermissions[$action] ?? null;

        if ($permissions === null) {
            throw new \Exception("Unauthorized action: $action", 401);
        }

        foreach ($permissions as $permission) {
            if (Gate::allows($permission)) {
                return; // Authorized
            }
        }

        throw new \Exception("Unauthorized access for action: $action", 401);
    }
}
