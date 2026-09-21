<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Facades\PermissionsGateFacade as Gate;
use Vanderbilt\REDCap\Classes\Rewards\Resources\ResourceFactory;
use Vanderbilt\REDCap\Classes\Rewards\Utility\ProjectArmFetcher;
use Vanderbilt\REDCap\Classes\Rewards\Resources\ProjectSettingsResource;
use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject;

class ProjectSettingsService extends RepositoryService {

    /** @var int */
    protected $project_id;
    
    /** @var int */
    protected $user_id;

    /** @var ProjectSettingsValueObject */
    protected $settings;

    /**
     *
     * @param int $project_id
     * @param int $user_id
     * @param ProjectSettingsValueObject $settings
     */
    public function __construct($project_id, $user_id, $settings) {
        $this->project_id = $project_id;
        $this->user_id = $user_id;
        $this->settings = $settings;
    }


    private function getPermissions() {
        $permissionsList = [
            PermissionEntity::REVIEW_ELIGIBILITY,
            PermissionEntity::PLACE_ORDERS,
            PermissionEntity::MANAGE_PERMISSIONS,
            PermissionEntity::MANAGE_PROJECT_SETTINGS,
            PermissionEntity::VIEW_LOGS,
            PermissionEntity::MANAGE_REWARD_OPTIONS,
            PermissionEntity::MANAGE_API_SETTINGS,
            PermissionEntity::VIEW_ORDERS,
        ];
        $gate = Gate::forUser($this->user_id, $this->project_id);
        $permissions = array_reduce($permissionsList, function($carry, $permissionKey) use($gate) {
            $carry[$permissionKey] = $gate->allows($permissionKey);
            return $carry;
        }, []);
        return $permissions;
    }

    public function getSettings() {
        $permissions = $this->getPermissions();
        $arms = ProjectArmFetcher::getProjectArms($this->project_id);
        $resource = ResourceFactory::create(ProjectSettingsResource::class, $this->settings, $permissions, $arms);
        return $resource;
    }

}