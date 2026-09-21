<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalState;

use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalService;

interface RewardApprovalStateInterface {
    public function setContext(RewardApprovalService $service): void;
    public function getTransitions(): array;
    public function getStrategyMap(): array;
}
