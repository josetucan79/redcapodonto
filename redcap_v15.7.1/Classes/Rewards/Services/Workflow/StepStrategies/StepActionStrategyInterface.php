<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;

interface StepActionStrategyInterface {
    /**
     * Executes the action (either approval or rejection) based on the current step.
     *
     * @param integer $record_id
     * @param integer $rewardOptionID
     * @param integer $arm_num
     * @return void
     */
    public function execute(OrderContextDTO $orderContext): void;
}
