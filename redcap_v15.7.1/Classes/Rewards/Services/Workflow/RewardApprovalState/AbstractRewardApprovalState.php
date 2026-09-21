<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalState;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalService;

abstract class AbstractRewardApprovalState implements RewardApprovalStateInterface
{
    protected RewardApprovalService $service;

    public function __construct(?RewardApprovalService $service = null)
    {
        if ($service) {
            $this->setContext($service);
        }
    }

    public function setContext(RewardApprovalService $service): void
    {
        $this->service = $service;
    }

    public function getTransitions(): array {
        return [
            OrderEntity::STATUS_ELIGIBLE => [
                ActionEntity::EVENT_REVIEWER_APPROVAL => OrderEntity::STATUS_REVIEWER_APPROVED,
                ActionEntity::EVENT_REVIEWER_REJECTION => OrderEntity::STATUS_REVIEWER_REJECTED,
            ],
            OrderEntity::STATUS_REVIEWER_REJECTED => [
                ActionEntity::EVENT_REVIEWER_RESTORE => function($currentState, ...$args) {
                    $orderContext = $args[0] ?? null;
                    if(!$orderContext instanceof OrderContextDTO) return OrderEntity::STATUS_UNKNOWN;
                    $status = $this->service?->getStatusByIds(
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
    }

}
