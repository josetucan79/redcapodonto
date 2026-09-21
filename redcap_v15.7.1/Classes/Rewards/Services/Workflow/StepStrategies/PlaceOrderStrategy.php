<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\ServiceFactory;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\OrderService;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ActionEntityTrait;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ContextDataTrait;

class PlaceOrderStrategy extends StepActionStrategy {
    use ContextDataTrait, ActionEntityTrait;
    
    public function execute(OrderContextDTO $orderContext): void {
        $actionEntity = $this->createActionEntity(
            $orderContext,
            ActionEntity::STAGE_COMPENSATION_DELIVERY,
            ActionEntity::EVENT_PLACE_ORDER
        );
        $orderEntity = $orderContext->getOrder();
        $actionEntity->setOrder($orderEntity);

        try {
            // Place the order using the rewards API
            $orderService = ServiceFactory::make(OrderService::class);
            $orderService->placeOrder($orderEntity); // this will store the redeem link in the order
            $redeemLink = $orderEntity->getRedeemLink();
            $this->stateMachine->apply(ActionEntity::EVENT_PLACE_ORDER);

            // Update the order with the redeem code
            $orderEntity->setStatus($this->stateMachine->getCurrentState());
            $orderEntity->clearScheduledAction();
            $this->entityManager->persist($orderEntity);
            $this->entityManager->flush();
            // Record success in action entity
            $actionEntity->setDetails("Redeem code generated: " . $redeemLink);
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