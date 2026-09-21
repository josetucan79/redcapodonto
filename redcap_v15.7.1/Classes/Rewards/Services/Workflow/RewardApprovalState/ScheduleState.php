<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalState;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\ScheduleStrategy;

class ScheduleState extends AbstractRewardApprovalState {
    protected array $strategyMap = [
        ActionEntity::EVENT_REVIEWER_APPROVAL => ScheduleStrategy::class,
        ActionEntity::EVENT_REVIEWER_REJECTION => ScheduleStrategy::class,
        ActionEntity::EVENT_REVIEWER_RESTORE=> ScheduleStrategy::class,
        ActionEntity::EVENT_BUYER_APPROVAL => ScheduleStrategy::class,
        ActionEntity::EVENT_BUYER_REJECTION => ScheduleStrategy::class,
        ActionEntity::EVENT_PLACE_ORDER => ScheduleStrategy::class,
        ActionEntity::EVENT_SEND_EMAIL => ScheduleStrategy::class,
    ];

    public function getStrategyMap(): array { return $this->strategyMap; }

    /* public function getTransitions(): array {
        return [
            OrderEntity::STATUS_ELIGIBLE => [
                ActionEntity::EVENT_REVIEWER_APPROVAL => OrderEntity::STATUS_SCHEDULED_REVIEWER_APPROVED,
                ActionEntity::EVENT_REVIEWER_REJECTION => OrderEntity::STATUS_SCHEDULED_REVIEWER_REJECTED,
            ],
            OrderEntity::STATUS_REVIEWER_APPROVED => [
                ActionEntity::EVENT_BUYER_APPROVAL => OrderEntity::STATUS_SCHEDULED_BUYER_APPROVED,
                ActionEntity::EVENT_BUYER_REJECTION => OrderEntity::STATUS_SCHEDULED_BUYER_REJECTED,
                ActionEntity::EVENT_PLACE_ORDER => OrderEntity::STATUS_SCHEDULED_ORDER_PLACED,
            ],
        ];
    } */
}