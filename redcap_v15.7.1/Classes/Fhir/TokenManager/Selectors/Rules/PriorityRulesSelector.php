<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules;

use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules\RulesManager;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\AbstractTokenSelector;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\TokenSelectionContext;

class PriorityRulesSelector extends AbstractTokenSelector
{
    /**
     *
     * @var RulesManager
     */
    private $rulesManager;

    /**
     *
     * @param RulesManager $rulesManager
     */
    public function __construct(RulesManager $rulesManager)
    {
        $this->rulesManager = $rulesManager;
    }

    /**
     * Get all potentially valid tokens ordered by priority rules for a project.
     *
     * @param TokenSelectionContext $context
     * @return void Tokens ordered by priority rules.
     */
    protected function handle(TokenSelectionContext $context): void
    {
        $users = $context->getUsers();

        $projectId = $context->getProjectId();
        if(!$projectId) return;
        // Fetch all rules for the project
        $rules = $this->rulesManager->getRulesByProject($projectId);
        $globalRule = $this->rulesManager->getGlobalRuleForProject($projectId);
    
        // Separate allowed and disallowed user rules
        $rulesAllowedUsers = [];
        $rulesDisallowedUsers = [];
    
        foreach ($rules as $rule) {
            if ($rule->getUserId() !== null) {
                if ($rule->isAllowed()) {
                    $rulesAllowedUsers[] = $rule->getUserId();
                } else {
                    $rulesDisallowedUsers[] = $rule->getUserId();
                }
            }
        }

        // Ensure that both allowed and disallowed users are part of the users provided by the context
        $allowedUsers = array_intersect($rulesAllowedUsers, $users);
        $disallowedUsers = array_intersect($rulesDisallowedUsers, $users);

        // Retrieve tokens from context
        $tokens = $context->getTokens(); // Assume tokens are already set in context

        
        // Step 1: Apply allowed and disallowed rules to tokens
        foreach ($tokens as $tokenDTO) {
            $ownerId = $tokenDTO->getTokenOwner();

            // Apply user-specific disallowed rule
            if (in_array($ownerId, $disallowedUsers)) {
                $tokenDTO->setStatus(FhirTokenDTO::STATUS_FORBIDDEN);
                continue; // Skip further processing for this token
            }

            // Apply user-specific allowed rule
            if (in_array($ownerId, $allowedUsers)) {
                // $refreshResult = $tokenManager->refreshAndValidateToken($tokenDTO);
                // $tokenDTO->setStatus(FhirTokenDTO::STATUS_VALID);
                continue; // Skip further processing for this token
            }

            // Apply global rule
            if ($globalRule === null || $globalRule->isAllowed()) {
                // $refreshResult = $tokenManager->refreshAndValidateToken($tokenDTO);
                // $tokenDTO->setStatus(FhirTokenDTO::STATUS_VALID);
            } else {
                // apply disallow global rule
                $tokenDTO->setStatus(FhirTokenDTO::STATUS_FORBIDDEN);
            }
        }

        // Step 2: Reorder tokens based on rule priorities
        $orderedTokens = $this->sortTokensByRulePriority($tokens, $rules);
        $context->setTokens($orderedTokens);
    }

    private function sortTokensByRulePriority(array $tokens, array $rules): array
    {
        // Create a map of user ID to priority for quick lookup
        $userPriorityMap = [];
        foreach ($rules as $rule) {
            $userPriorityMap[$rule->getUserId()] = $rule->getPriority();
        }

        // Sort tokens based on the priority of their owners
        usort($tokens, function (FhirTokenDTO $a, FhirTokenDTO $b) use ($userPriorityMap) {
            $priorityA = $userPriorityMap[$a->getTokenOwner()] ?? PHP_INT_MAX;
            $priorityB = $userPriorityMap[$b->getTokenOwner()] ?? PHP_INT_MAX;

            return $priorityA <=> $priorityB; // Ascending order (lower priority first)
        });

        return $tokens;
    }


}