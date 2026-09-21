<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow;

use Design;
use REDCap;
use Records;
use Exception;
use LogicParser;
use LogicException;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Utility\ProjectArmFetcher;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;

class RewardOptionService {

    const LOGIC_SYNTAX_ERRORS = 'syntax error';
    const LOGIC_ILLEGAL_FUNCTIONS = 'illegal fucntions';

    protected static $logicValidationCache = [];


    /**
     * Checks the reward option for eligibility based on the given record ID and reward option ID.
     *
     * @param int $rewardOptionID The ID of the reward option to check.
     * @param int $arm_number
     * @param int $recordID The ID of the record to check eligibility for.
     * @return RewardOptionEntity The reward option resource if found and eligible.
     * @throws \Exception If the reward option is not found or does not meet eligibility criteria.
     */
    public static function getValidRewardOption($rewardOptionID, $arm_number, $recordID) {
        $entityManager = EntityManager::get();
        $rewardOptionsRepo = $entityManager->getRepository(RewardOptionEntity::class);
        $rewardOption = $rewardOptionsRepo->find($rewardOptionID);
        if(!$rewardOption instanceof RewardOptionEntity) throw new Exception("Not found", 404);
        try {
            // rewardOption is a resource; need to extract the entity
            $isEligible = self::isEligible($rewardOption, $arm_number, $recordID);
            if(!$isEligible) throw new Exception("This option does not meet eligibility criteria", 403);
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            throw new Exception("There is an error in the logic for this reward - $errorMessage", 400);
        }
        return $rewardOption;
    }

    /**
     * Checks the reward option for eligibility based on the given record ID and reward option ID.
     *
     * @param int $rewardOptionID The ID of the reward option to check.
     * @param int $arm_number
     * @param int $recordID The ID of the record to check eligibility for.
     * @return RewardOptionEntity The reward option resource if found and eligible.
     * @throws \Exception If the reward option is not found or does not meet eligibility criteria.
     */
    public static function getRewardOption($project_id, $event_id, $recordID, $index=1) {
        try {
            $entityManager = EntityManager::get();
            $rewardOptionsRepo = $entityManager->getRepository(RewardOptionEntity::class);
            $rewardOptions = $rewardOptionsRepo->findBy(['project_id' => $project_id, 'deleted_at' => 'IS NULL']);
            $adjustedIndex = $index>0 ? $index-1 : 0;
            $rewardOption = $rewardOptions[$adjustedIndex] ?? null;
            if(!$rewardOption instanceof RewardOptionEntity) throw new Exception("Not found", 404);

            $arm_number = ProjectArmFetcher::getArmNumForEventId($project_id, $event_id);
            // rewardOption is a resource; need to extract the entity
            $isEligible = self::isEligible($rewardOption, $arm_number, $recordID);
            if(!$isEligible) throw new Exception("This option does not meet eligibility criteria", 403);
            return $rewardOption;
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            throw new Exception("There is an error in the logic for this reward - $errorMessage", 400);
        }
    }

    public static function validateLogic($logic) {
        $parser = new LogicParser();
		try {
			$parser->parse($logic, null, true, false, false, true);
            return true;
		}
		catch (LogicException $e) {
			if (count($parser->illegalFunctionsAttempted) === 0) {
				// Contains syntax errors
				return self::LOGIC_SYNTAX_ERRORS;
			}
			else {
				// Contains illegal functions
                return self::LOGIC_ILLEGAL_FUNCTIONS;
			}
		}
    }

    /**
     * Check if a reward option is eligible.
     *
     * @param RewardOptionEntity $rewardOption
     * @param int $arm_number
     * @param int|string $record_id
     * @return boolean
     */
    public static function isEligible($rewardOption, $arm_number, $record_id, $record_data=null): bool {
        $project_id = $rewardOption->getProjectId();
        $optionID = $rewardOption->getRewardOptionId();
        $logic = $rewardOption->getEligibilityLogic();

        if (empty($logic)) {
            throw new Exception("Error: missing logic for the eligibility criteria in reward option ID $optionID", 400);
        }

        // Check cache for validation results
        if (!isset(self::$logicValidationCache[$optionID])) {
            $error_fields = Design::validateBranchingCalc($logic, true);
            if (!empty($error_fields)) {
                throw new Exception("Errors found in the logic for the eligibility criteria in reward option ID $optionID", 400);
            }

            $validLogic = static::validateLogic($logic);
            if ($validLogic !== true) {
                throw new Exception("Logic is not valid for reward option ID $optionID", 400);
            }

            // Store the successful validation in cache
            self::$logicValidationCache[$optionID] = true;
        }

        // At this point, logic is known to be valid and no errors are present.
        $event_name = ProjectArmFetcher::getProjectArmUniqueName($project_id, $arm_number);
        // format data the way it is expected by REDCap::evaluateLogic
        $inputData = ($record_data !== null) ? [$record_id => $record_data] : null;
        $result = REDCap::evaluateLogic(
            $logic,
            $project_id,
            $record_id,
            $event_name,
            1,
            "",
            "",
            $inputData,
            false,
            true
        );

        return (bool) $result;
    }


    /**
     * Fetch record data from REDCap
     *
     * @return array
     */
    protected static function fetchRecordData($project_id, $records=[]): array {
        $record_data = (array) Records::getData([
            'project_id' => $project_id,
            'return_format' => 'array',
            'fields' => [],
            'records' => $records,
        ]);
        return $record_data;
    }


}