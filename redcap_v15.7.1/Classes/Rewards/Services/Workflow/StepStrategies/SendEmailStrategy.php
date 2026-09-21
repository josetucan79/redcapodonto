<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\ServiceFactory;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\OrderEmailService;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ActionEntityTrait;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ContextDataTrait;

class SendEmailStrategy extends StepActionStrategy {
    use ContextDataTrait, ActionEntityTrait;
    
    public function execute(OrderContextDTO $orderContext): void {
        
        $actionEntity = $this->createActionEntity(
            $orderContext,
            ActionEntity::STAGE_COMPENSATION_DELIVERY,
            ActionEntity::EVENT_SEND_EMAIL
        );
        $orderEntity = $orderContext->getOrder();
        $actionEntity->setOrder($orderEntity);

        try {
            // Send the email to the participant
            $emailService = ServiceFactory::make(OrderEmailService::class);
            $emailService->sendMessage(
                $orderContext->getArmNumber(),
                $orderContext->getRecordId(),
                $orderEntity
            );
            $this->stateMachine->apply(ActionEntity::EVENT_SEND_EMAIL);

            // Update order status to completed
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