<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ActionEntityTrait;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ContextDataTrait;

class ReviewerRejectionStrategy extends StepActionStrategy {
    use ContextDataTrait, ActionEntityTrait;

    public function execute(OrderContextDTO $orderContext): void {
        try {
            $actionEntity = $this->createActionEntity(
                $orderContext,
                ActionEntity::STAGE_ELIGIBILITY_REVIEW,
                ActionEntity::EVENT_REVIEWER_REJECTION
            );

            $orderEntity = $orderContext->getOrder();
            if(!$orderEntity) {
                // create a default OrderEntity
                $orderEntity = $this->createOrderEntity($orderContext);
            }
            // set the status
            $this->stateMachine->apply(ActionEntity::EVENT_REVIEWER_REJECTION);
            $orderEntity->setStatus($this->stateMachine->getCurrentState());
            $orderEntity->clearScheduledAction();
            $this->entityManager->persist($orderEntity);
            $this->entityManager->flush();

            // Associate the order ID with the action entity
            $actionEntity->setOrder($orderEntity);

        } catch (\Throwable $th) {
            $this->handleException($actionEntity, $th);
        } finally {
            $this->entityManager->persist($actionEntity);
            $this->entityManager->flush();
        }
    }
}