<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules;

use Exception;
use Project;
use User;
use UserRights;
use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\Utility\TransactionHelper;

class RulesManager
{
    /**
     *
     * @var RulesRepository
     */
    private $repository;

    public function __construct()
    {
        $this->repository = RulesRepository::instance();
    }

    public static function instance() {
        return new self();
    }

    /**
     * Get all rules for a given project, ordered by priority.
     *
     * @param int $projectId
     * @return TokenRuleDTO[]
     */
    public function getRulesByProject(int $projectId): array
    {
        $rules = $this->repository->getRulesForProject($projectId);
        return $rules;
    }

    public function getGlobalRuleForProject(int $projectId) {
        return $this->repository->getGlobalRuleForProject($projectId);
    }

    public function getOrMakeGlobalRuleForProject(int $projectId) {
        $globalRule = $this->getGlobalRuleForProject($projectId);

        // make a default global rule if none exists
        if (!$globalRule) {
            $now = date('Y-m-d H:i:s');
            $globalRule = new TokenRuleDTO([
                'project_id' => $projectId,
                'user_id' => null, // Global rule
                'priority' => 0, // no priority
                'allow' => true, // Default to allow
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $globalRule;
    }


    /**
     * Add a new rule to a project.
     * Adjusts priorities to maintain continuity.
     *
     * @param TokenRuleDTO $rule
     * @return int
     */
    public function addRule(TokenRuleDTO $rule): int
    {
        $this->adjustPrioritiesForInsert($rule->getProjectId(), $rule->getPriority());
        return $this->repository->insertRule($rule);
    }

    /**
     *
     * @param integer $ruleId
     * @return TokenRuleDTO|null
     */
    public function getRule(int $ruleId) {
        $ruleData = $this->repository->getRuleById($ruleId);
        if(!$ruleData) return null;
        return new TokenRuleDTO($ruleData);

    }

    /**
     * Update an existing rule and validate the priority.
     *
     * @param TokenRuleDTO $rule
     * @return bool
     */
    public function updateRule(TokenRuleDTO $rule): bool
    {
        // $this->validatePriority($rule->getProjectId(), $rule->getPriority());
        return $this->repository->updateRule($rule);
    }

    /**
     * Delete a rule by ID and re-adjust priorities for the remaining rules.
     *
     * @param int $projectId
     * @param int $ruleId
     * @return bool
     */
    public function deleteRule(int $projectId, int $ruleId): bool
    {
        $ruleData = $this->repository->getRuleById($ruleId);
        if (!$ruleData) {
            return false; // Rule does not exist
        }

        $success = $this->repository->deleteRule($ruleId);
        if ($success) {
            $rule = new TokenRuleDTO($ruleData);
            $this->adjustPrioritiesForDelete($projectId, $rule->getPriority());
        }
        return $success;
    }

    /**
     * Adjust priorities when a new rule is inserted.
     *
     * @param int $projectId
     * @param int $insertedPriority
     */
    private function adjustPrioritiesForInsert(int $projectId, int $insertedPriority): void
    {
        $rules = $this->getRulesByProject($projectId);

        foreach ($rules as $rule) {
            if ($rule->getPriority() >= $insertedPriority) {
                $rule->setPriority($rule->getPriority() + 1);
                $this->repository->updateRule($rule);
            }
        }
    }

    /**
     * Adjust priorities when a rule is deleted.
     *
     * @param int $projectId
     * @param int $deletedPriority
     */
    private function adjustPrioritiesForDelete(int $projectId, int $deletedPriority): void
    {
        $rules = $this->getRulesByProject($projectId);

        foreach ($rules as $rule) {
            if ($rule->getPriority() > $deletedPriority) {
                $rule->setPriority($rule->getPriority() - 1);
                $this->repository->updateRule($rule);
            }
        }
    }

    /**
     * Apply changes from the frontend to the rules.
     *
     * @param int $projectId The project ID to apply to all rules.
     * @param array $changes Associative array containing created, updated, deleted, and globalRule.
     * @return array An array with the results of the applied changes.
     */
    public function applyChanges(int $projectId, array $changes): array
    {
        $results = [
            'created' => [],
            'updated' => [],
            'deleted' => [],
            'globalRule' => null,
        ];

        try {
            TransactionHelper::beginTransaction();
            $idMapping = []; // Map temporary IDs to actual IDs for reordering

            // Process created rules
            foreach ($changes['created'] ?? [] as $ruleData) {
                $ruleData['project_id'] = $projectId;
                $ruleData['priority'] = 0;
                $tempId = $ruleData['id']; //store temp id
                $ruleData['id'] = null; // set it to null
                $ruleDTO = new TokenRuleDTO($ruleData);
                $ruleId = $this->addRule($ruleDTO);
                $results['created'][] = $ruleId;

                // Map temporary ID to the actual ID
                $idMapping[$tempId] = $ruleId;
            }
    
            // Process updated rules
            foreach ($changes['updated'] ?? [] as $ruleData) {
                $ruleId = $ruleData['id'] ?? null;
                $existingRule = $this->getRule($ruleId);
                // security checks
                if(!$existingRule) throw new Exception("Rule with ID $ruleId cannot be updated; not found. ", 404);
                if($existingRule->getProjectId() !== $projectId) throw new Exception("Cannot update rule with ID $ruleId because belongs to another projects", 401);
                
                $allow = boolval($ruleData['allow'] ?? false);
                $existingRule->setAllow($allow); // only update the allow
                $success = $this->updateRule($existingRule);
                $results['updated'][] = [
                    'id' => $ruleId,
                    'success' => $success,
                ];
            }
    
            // Process deleted rules
            foreach ($changes['deleted'] ?? [] as $ruleId) {
                $success = $this->deleteRule($projectId, $ruleId);
                $results['deleted'][] = [
                    'id' => $ruleId,
                    'success' => $success,
                ];
            }
    
            // Process global rule
            if (isset($changes['globalRule'])) {
                $globalRuleData = $changes['globalRule'];
                $globalRuleData['project_id'] = $projectId;
                $globalRuleData['user_id'] = null; // Ensure it's marked as a global rule
                $globalRuleData['allow'] = $globalRuleData['allow'] ?? false;
    
                // Check if a global rule exists
                $existingGlobalRule = $this->getGlobalRuleForProject($projectId);
    
                if ($existingGlobalRule) {
                    $existingGlobalRule->setAllow($globalRuleData['allow']);
                    $success = $this->updateRule($existingGlobalRule);
                    $results['globalRule'] = [
                        'id' => $existingGlobalRule->getId(),
                        'success' => $success,
                    ];
                } else {
                    $globalRuleData['id'] = null;
                    $globalRuleDTO = new TokenRuleDTO($globalRuleData);
                    // Create a new global rule
                    $ruleId = $this->addRule($globalRuleDTO);
                    $results['globalRule'] = [
                        'id' => $ruleId,
                        'success' => true,
                    ];
                }
            }

            // Handle reordering
            if (isset($changes['order'])) {
                $normalizedOrder = [];
                foreach ($changes['order'] as $ruleId => $index) {
                    // Normalize temporary IDs to actual IDs
                    $normalizedOrder[$idMapping[$ruleId] ?? $ruleId] = $index;
                }
                $this->reorderRules($projectId, $normalizedOrder);
            }

            // cleanup
            // $this->deleteRulesNotAssigned($projectId);

            TransactionHelper::commitTransaction();
            return $results;
        } catch (\Exception $e) {
            TransactionHelper::rollbackTransaction();
            throw $e;
        }
    }

    /**
     * Reorder rules based on the order provided from the frontend.
     *
     * @param int $projectId The project ID.
     * @param array $order Associative array in the form ruleId => index.
     */
    public function reorderRules(int $projectId, array $order): void
    {
        // Fetch all current rules for the project
        $rules = $this->getRulesByProject($projectId);

        // Map rules by their IDs for quick lookup
        $rulesById = [];
        foreach ($rules as $rule) {
            $rulesById[$rule->getId()] = $rule;
        }

        $normalizedOrder = $this->normalizeOrder($order);

        // Iterate through the order array and update priorities
        foreach ($normalizedOrder as $ruleId => $newIndex) {
            // Check if the rule exists in the current project rules
            if (isset($rulesById[$ruleId])) {
                $normalizedIndex = $newIndex + 1; // add 1 because index is 0 based
                $rule = $rulesById[$ruleId];
                $rule->setPriority($normalizedIndex); // Update priority to the new index
                $this->repository->updateRule($rule); // Save the updated priority
            }
        }
    }

    /**
     * Normalizes a reordering array so that its values become sequential numbers starting from 0.
     *
     * For example, given an input:
     * [
     *     8  => 2,
     *     9  => 0,
     *     12 => 1,
     * ]
     *
     * The function will return:
     * [
     *     9  => 0, // because 0 is the smallest original value
     *     12 => 1, // because 1 is the next
     *     8  => 2, // because 2 is the largest original value
     * ]
     *
     * @param array $order The input order array (ruleId => orderValue)
     * @return array The normalized order array (ruleId => normalizedIndex)
     */
    private function normalizeOrder(array $order): array
    {
        // Sort the array by its values while preserving keys.
        // This sorts the rules in the intended order.
        asort($order);

        // Reassign sequential indices starting at 0.
        $normalized = [];
        $index = 0;
        foreach ($order as $ruleId => $originalValue) {
            $normalized[$ruleId] = $index++;
        }

        return $normalized;
    }

    public function deleteRulesNotAssigned($project_id) {
        $users = self::getProjectUsers($project_id);
        $user_ids = [];
        foreach ($users as $username => $data) {
            $ui_id = $data['ui_id'] ?? null;
            if(is_null($ui_id)) continue;
            $user_ids[] = $ui_id;
        }
        return $this->repository->deleteRulesNotAssignedToUsers($project_id, $user_ids);
    }

    public static function getProjectUsers($project_id) {
        $allowedKeys = ["project_id", "username", "ui_id"];
        $user_rights_all = UserRights::getPrivileges($project_id);
        $users = $user_rights_all[$project_id] ?? [];
        $filteredUsers = [];
        array_walk($users, function($user, $key) use ($allowedKeys, &$filteredUsers) {
            $filteredUsers[$key] = array_intersect_key($user, array_flip($allowedKeys));
            $filteredUsers[$key]['ui_id'] = User::getUIIDByUsername($key);
        });
        return $filteredUsers;
    }

    public static function getFormURL($project_id) {
        return APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . "/DynamicDataPull/token-priority?pid=$project_id";
    }
}