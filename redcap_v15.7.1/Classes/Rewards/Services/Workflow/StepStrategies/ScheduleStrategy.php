<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ActionEntityTrait;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits\ContextDataTrait;
use Vanderbilt\REDCap\Classes\Rewards\Utility\OrderStageResolver;

class ScheduleStrategy extends StepActionStrategy {
    use ContextDataTrait, ActionEntityTrait;

    public function execute(OrderContextDTO $orderContext): void {
        try {
            $action = $orderContext->getAction(); // assume the DTO carries this
            if (!$action) {
                throw new \InvalidArgumentException('Scheduled action is missing from context.');
            }
            $currentStatus = $orderContext->getStatus();
            $stage = OrderStageResolver::inferStageFromStatus($currentStatus);

            $actionEntity = $this->createActionEntity(
                $orderContext,
                $stage, //ActionEntity::STAGE_SCHEDULING,
                $action
            );

            $orderEntity = $orderContext->getOrder();
            if (!$orderEntity) {
                $orderEntity = $this->createOrderEntity($orderContext);
            }
            
            // $orderEntity->setStatus($scheduledStatus);
            $orderEntity->setScheduledAction($action);
            // Apply scheduled status
            // $orderEntity->setStatus($scheduledStatus);

            // Save order
            $this->entityManager->persist($orderEntity);
            $this->entityManager->flush();

            // Link action to order
            $actionEntity->setOrder($orderEntity);
        } catch (\Throwable $th) {
            $this->handleException($actionEntity ?? null, $th);
        } finally {
            $this->entityManager->persist($actionEntity);
            $this->entityManager->flush();
        }
    }

}
