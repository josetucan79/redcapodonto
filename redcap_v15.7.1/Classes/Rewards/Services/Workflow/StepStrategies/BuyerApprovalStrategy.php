<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies;

use Exception;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ActionEntityTrait;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ContextDataTrait;

class BuyerApprovalStrategy extends StepActionStrategy {
    use ContextDataTrait, ActionEntityTrait;
    
    public function execute(OrderContextDTO $orderContext): void {

        // Retrieve current order
        $orderEntity = $orderContext->getOrder();
        if (!$orderEntity instanceof OrderEntity) {
            throw new Exception("Order not found in context", 404);
        }

        try {
            // Step 1: Financial Authorization Approval
            $this->approveFinancialAuthorization($orderContext);
            // reset the comment: do not store the same comment for the next actions
            $orderContext->setComment('');
            
            // Step 2: Place Order (Redeem Code Generation)
            $placeOrderStrategy = new PlaceOrderStrategy($this->entityManager, $this->stateMachine);
            $placeOrderStrategy->execute($orderContext);
            // $this->stateMachine->apply(ActionEntity::EVENT_PLACE_ORDER);

            // Step 3: Send Email
            $sendEmailStrategy = new SendEmailStrategy($this->entityManager, $this->stateMachine);
            $sendEmailStrategy->execute($orderContext);
            // $this->stateMachine->apply(ActionEntity::EVENT_SEND_EMAIL);

        } catch (\Throwable $th) {
            throw $th; // Re-throw exception to be handled elsewhere if needed
        }
    }

    protected function approveFinancialAuthorization(OrderContextDTO $orderContext): void {
        $actionEntity = $this->createActionEntity(
            $orderContext,
            ActionEntity::STAGE_FINANCIAL_AUTHORIZATION,
            ActionEntity::EVENT_BUYER_APPROVAL
        );
        $orderEntity = $orderContext->getOrder();
        
        $actionEntity->setOrder($orderEntity);

        try {
            // Perform any necessary logic for financial authorization approval
            $this->stateMachine->apply(ActionEntity::EVENT_BUYER_APPROVAL);
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