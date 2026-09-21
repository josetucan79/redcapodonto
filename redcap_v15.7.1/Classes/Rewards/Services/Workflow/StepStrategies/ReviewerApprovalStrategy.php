<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ActionEntityTrait;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ContextDataTrait;

class ReviewerApprovalStrategy extends StepActionStrategy {
    use ContextDataTrait, ActionEntityTrait;

    public function execute(OrderContextDTO $orderContext): void {
        try {
            // get the reard option entity
            
            $actionEntity = $this->createActionEntity(
                $orderContext,
                ActionEntity::STAGE_ELIGIBILITY_REVIEW,
                ActionEntity::EVENT_REVIEWER_APPROVAL
            );
            
            $orderEntity = $orderContext->getOrder();
            if(!$orderEntity) {
                // create a default OrderEntity
                $orderEntity = $this->createOrderEntity($orderContext);
            }
            // set the status
            $this->stateMachine->apply(ActionEntity::EVENT_REVIEWER_APPROVAL);
            $orderEntity->setStatus($this->stateMachine->getCurrentState());
            $orderEntity->clearScheduledAction();
        
            // save
            $this->entityManager->persist($orderEntity);
            $this->entityManager->flush();

            // set the relationship between the order and the action
            $actionEntity->setOrder($orderEntity);
        } catch (\Throwable $th) {
            $this->handleException($actionEntity, $th);
        } finally {
            $this->entityManager->persist($actionEntity);
            $this->entityManager->flush();
        }
    }
}
