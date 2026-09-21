<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Facades;

use User;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Security\AbilityGate;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserPermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\UserRepository;

class PermissionsGateFacade
{
    protected static $gateInstance;
    protected static $user_id;

    /**
     * Initialize the Gate instance with the current user.
     *
     * @throws \Exception
     */
    public static function init($user_id=null, $project_id = null)
    {
        if (!$user_id && !defined('UI_ID')) {
            throw new \Exception('Error: must provide user ID or define UI_ID.');
        }
        if (!$project_id && !defined('PROJECT_ID')) {
            throw new \Exception('Error: must provide Project ID or define PROJECT_ID.');
        }
        self::$gateInstance = self::forUser($user_id ?? UI_ID, $project_id ?? PROJECT_ID, );
    }

    /**
     * Get the Gate instance.
     *
     * @return AbilityGate
     * @throws \Exception
     */
    protected static function instance()
    {
        if (!self::$gateInstance) {
            self::init();
        }

        return self::$gateInstance;
    }

    /**
     * Define a new ability.
     *
     * @param string $ability
     * @param callable $callback
     */
    public static function define($ability, callable $callback)
    {
        self::instance()->define($ability, $callback);
    }

    /**
     * Determine if the given ability should be granted.
     *
     * @param string $ability
     * @param mixed $user
     * @param mixed $arguments
     * @return bool
     */
    public static function allows($ability, $arguments=[])
    {
        return self::instance()->allows($ability, static::$user_id, $arguments);
    }

    /**
     * Determine if the given ability should be granted.
     *
     * @param string $ability
     * @param mixed $user
     * @param mixed ...$arguments
     * @return bool
     */
    public static function denies($ability, $arguments=[])
    {
        return self::instance()->denies($ability, static::$user_id, $arguments);
    }

    /**
     * Get a new Gate instance for the specified user.
     *
     * @param int $user_id
     * @param int $project_id
     * @return AbilityGate
     * @throws \Exception
     */
    public static function forUser($user_id, $project_id)
    {
        $gate = new AbilityGate();
        static::$user_id = $user_id;
        $user = User::getUserInfoByUiid($user_id);
        
        $gate->define('SUPER_USER', function($ability, ...$arguments) use($user) {
            return boolval($user['super_user'] ?? false);
        });

        // grant all permissions to super users
        $gate->before(function($ability, ...$arguments) use($gate) {
            if($ability ==='SUPER_USER') return;
            $is_super_user = $gate->allows('SUPER_USER', ...$arguments);
            if($is_super_user === true) return true;
            return null;
        });

        $entityManager = EntityManager::get();
        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(UserEntity::class);
        $permissionRepository = $entityManager->getRepository(PermissionEntity::class);

        // Fetch user permissions from the database
        $userPermissions = $userRepository->getPermissions($project_id, $user_id);
        $permissions = $permissionRepository->findAll();

        // Extract permission names and define them as abilities
        
        foreach ($permissions as $permission) {
            /** @var UserPermissionEntity $userPermission */
            /** @var PermissionEntity $permission */
            $permissionName = $permission->getName();
            $gate->define($permissionName, function ($ability, ...$arguments) use ($permission, $userPermissions) {
                /** @var PermissionEntity $userPermission */
                foreach ($userPermissions as $userPermission) {
                    if($userPermission->getId()==$permission->getId()) return true;
                }
                return false;
            });
        }

        return $gate;
    }
}
