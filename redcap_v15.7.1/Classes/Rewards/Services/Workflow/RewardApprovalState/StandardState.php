<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalState;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\BuyerApprovalStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\BuyerRejectionStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\PlaceOrderStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\ReviewerApprovalStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\ReviewerRejectionStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\ReviewerRestoreStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\SendEmailStrategy;

class StandardState extends AbstractRewardApprovalState {
    protected array $strategyMap = [
        ActionEntity::EVENT_REVIEWER_APPROVAL => ReviewerApprovalStrategy::class,
        ActionEntity::EVENT_REVIEWER_REJECTION => ReviewerRejectionStrategy::class,
        ActionEntity::EVENT_REVIEWER_RESTORE=> ReviewerRestoreStrategy::class,
        ActionEntity::EVENT_BUYER_APPROVAL => BuyerApprovalStrategy::class,
        ActionEntity::EVENT_BUYER_REJECTION => BuyerRejectionStrategy::class,
        ActionEntity::EVENT_PLACE_ORDER => PlaceOrderStrategy::class,
        ActionEntity::EVENT_SEND_EMAIL => SendEmailStrategy::class,
    ];

    public function getStrategyMap(): array { return $this->strategyMap; }
    
    /* public function getTransitions(): array {
        return [
            OrderEntity::STATUS_ELIGIBLE => [
                ActionEntity::EVENT_REVIEWER_APPROVAL => OrderEntity::STATUS_REVIEWER_APPROVED,
                ActionEntity::EVENT_REVIEWER_REJECTION => OrderEntity::STATUS_REVIEWER_REJECTED,
            ],
            OrderEntity::STATUS_REVIEWER_REJECTED => [
                ActionEntity::EVENT_REVIEWER_RESTORE => function($currentState, ...$args) {
                    $orderContext = $args[0] ?? null;
                    if(!$orderContext instanceof OrderContextDTO) return OrderEntity::STATUS_UNKNOWN;
                    $status = $this->service->getStatusByIds(
                        $orderContext->getRewardOptionId(),
                        $orderContext->getProjectId(),
                        $orderContext->getArmNumber(),
                        $orderContext->getRecordId()
                    );
                    return $status;
                },
            ],
            OrderEntity::STATUS_REVIEWER_APPROVED => [
                ActionEntity::EVENT_BUYER_APPROVAL => OrderEntity::STATUS_BUYER_APPROVED,
                ActionEntity::EVENT_BUYER_REJECTION => OrderEntity::STATUS_BUYER_REJECTED,
            ],
            OrderEntity::STATUS_BUYER_REJECTED => [
                ActionEntity::EVENT_REVIEWER_APPROVAL => OrderEntity::STATUS_REVIEWER_APPROVED,
                ActionEntity::EVENT_REVIEWER_REJECTION => OrderEntity::STATUS_REVIEWER_REJECTED,
            ],
            OrderEntity::STATUS_BUYER_APPROVED => [
                ActionEntity::EVENT_PLACE_ORDER => OrderEntity::STATUS_ORDER_PLACED,
            ],
            OrderEntity::STATUS_ORDER_PLACED => [
                ActionEntity::EVENT_SEND_EMAIL => OrderEntity::STATUS_COMPLETED,
            ],
            // Self-transitions: list of actions still allowed once the order is completed
            OrderEntity::STATUS_COMPLETED => [
                ActionEntity::EVENT_SEND_EMAIL => OrderEntity::STATUS_COMPLETED,
                ActionEntity::EVENT_PLACE_ORDER => OrderEntity::STATUS_COMPLETED,
            ],
        ];
    } */
}
