<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies;

use Exception;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ActionEntityTrait;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ContextDataTrait;

class BuyerRejectionStrategy extends StepActionStrategy {
    use ContextDataTrait, ActionEntityTrait;
    
    public function execute(OrderContextDTO $orderContext): void {

        // Retrieve current order
        $orderEntity = $orderContext->getOrder();
        if (!$orderEntity instanceof OrderEntity) {
            throw new Exception("Order not found in context", 404);
        }

        try {
            // Step 1: Financial Authorization Approval
            $this->rejectFinancialAuthorization($orderContext);
        } catch (\Throwable $th) {
            throw $th; // Re-throw exception to be handled elsewhere if needed
        }
    }

    protected function rejectFinancialAuthorization(OrderContextDTO $orderContext): void {
        $actionEntity = $this->createActionEntity(
            $orderContext,
            ActionEntity::STAGE_FINANCIAL_AUTHORIZATION,
            ActionEntity::EVENT_BUYER_REJECTION
        );
        $orderEntity = $orderContext->getOrder();
        $actionEntity->setOrder($orderEntity);

        try {
            // Perform any necessary logic for financial authorization approval
            $this->stateMachine->apply(ActionEntity::EVENT_BUYER_REJECTION);
            $orderEntity->setStatus($this->stateMachine->getCurrentState());
            $orderEntity->clearScheduledAction();
            $this->entityManager->persist($orderEntity);
            $this->entityManager->flush();
        } catch (\Throwable $th) {
            $actionEntity->setStatus(ActionEntity::STATUS_ERROR);
            $this->handleException($actionEntity, $th);
            throw $th; // Re-throw to be caught in the execute method
        } finally {
            $this->entityManager->persist($actionEntity);
            $this->entityManager->flush();
        }
    }

    
}