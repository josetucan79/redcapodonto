<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Facades;

use User;
use Project;
use UserRights;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\TangoProvider;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserPermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\CriteriaChecker;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\CriterionResult;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\ConnectionCriterion;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\BuyerRoleAssignedCriterion;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\ReviewerRoleAssignedCriterion;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\EmailFieldDesignatedCriterion;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\APISettingsConfiguredCriterion;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\EmailTemplateSmartVariableCriterion;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\ProjectSettingsConfiguredCriterion;
use Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria\RewardsOptionsManagerRoleAssignedCriterion;

/**
 * Class CriteriaManager
 *
 * This class acts as a facade for managing and checking various criteria.
 * It simplifies the process of setting up and executing criteria checks by
 * encapsulating the creation and configuration of the CriteriaChecker and
 * its associated Criterion objects.
 *
 * This class is versatile and can be used in different scenarios, such as
 * determining page access, API access, or displaying specific content in the
 * user interface based on the criteria results.
 */
class CriteriaManager {
    
    /**
     * @var CriteriaChecker $criteriaChecker An instance of CriteriaChecker that manages the criteria.
     */
    private $criteriaChecker;

    /**
     * CriteriaManager constructor.
     *
     * Initializes the CriteriaChecker and configures it with the necessary criteria for the given project.
     * Optionally, a translation array can be provided to localize the descriptions of the criteria.
     *
     * @param int $project_id The ID of the project for which the criteria should be checked.
     * @param array $lang (optional) An associative array of translations for the criteria descriptions.
     *                    If not provided, default English descriptions will be used.
     */
    public function __construct($project_id, $userid, $lang=[]) {
        // Initialize project and settings manager
        $project = new Project($project_id);
        $settings = ProjectSettings::get($project_id);

        /** @var TangoProvider $provider */
        $provider = RewardsProvider::make($project_id);
        $entityManager = EntityManager::get();
        $userPermissionRepo = $entityManager->getRepository(UserPermissionEntity::class);
        
        /* 
        // alternative to userPermissionRepo in criteria using permissions
        $user_ids = $this->getUserIdsInProject($project_id);
        $gates = array_map(function ($user_id) use($project_id) {
            return PermissionsGateFacade::forUser($user_id, $project_id);
        }, $user_ids);
        */
        
        // Initialize criteria
        $emailDesignatedCriterion = new EmailFieldDesignatedCriterion($project);
        $projectSettingsConfiguredCriterion = new ProjectSettingsConfiguredCriterion($settings);
        $emailTemplateSmartVariableCriterion = new EmailTemplateSmartVariableCriterion($settings);
        $apiSettingsConfiguredCriterion = new APISettingsConfiguredCriterion($project_id);
        $connectionCriterion = new ConnectionCriterion($provider);
        $buyerRoleAssignedCriterion = new BuyerRoleAssignedCriterion($project_id, $userPermissionRepo);
        $reviewerRoleAssignedCriterion = new ReviewerRoleAssignedCriterion($project_id, $userPermissionRepo);
        $rewardsOptionsManagerRoleAssignedCriterion = new RewardsOptionsManagerRoleAssignedCriterion($project_id, $userPermissionRepo);

        // Initialize and configure criteria checker
        $this->criteriaChecker = new CriteriaChecker();
        $this->criteriaChecker
            ->add($emailDesignatedCriterion)
            ->add($projectSettingsConfiguredCriterion)
            ->add($emailTemplateSmartVariableCriterion)
            ->add($apiSettingsConfiguredCriterion)
            ->add($buyerRoleAssignedCriterion)
            ->add($reviewerRoleAssignedCriterion)
            ->add($rewardsOptionsManagerRoleAssignedCriterion)
            ->add($connectionCriterion);

        // Apply the translation array to all criteria if provided
        if (!empty($lang)) $this->criteriaChecker->applyLang($lang);
    }

    private function getUserIdsInProject($project_id) {
        $allUsers = array_keys(UserRights::getPrivileges($project_id)[$project_id] ?? []);
        $user_ids = array_map(function($username) { return User::getUIIDByUsername($username);}, $allUsers);
        return $user_ids;
    }

    /**
     * Checks all configured criteria.
     *
     * @param bool|null &$valid This reference parameter will be set to true if all criteria are met,
     *                          false otherwise. If not provided, the method will still function correctly.
     * @return CriterionResult[] An array of CriterionResult objects representing the results of each criterion check.
     */
    public function checkCriteria(&$valid=null) {
        $results = $this->criteriaChecker->checkAll();

        $valid = true;
        foreach ($results as $result) {
            if (!$result->isMet()) {
                $valid = false;
                break;
            }
        }

        return $results;
    }
}
