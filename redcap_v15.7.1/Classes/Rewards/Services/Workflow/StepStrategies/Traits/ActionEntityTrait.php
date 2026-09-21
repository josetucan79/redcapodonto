<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits;

use DateTime;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;

trait ActionEntityTrait {
    protected function createActionEntity(OrderContextDTO $orderContext, string $stage, string $event): ActionEntity {
        $now = new DateTime();
        $actionEntity = new ActionEntity();
        $actionEntity->setStage($stage);
        $actionEntity->setEvent($event);
        $actionEntity->setStatus(ActionEntity::STATUS_COMPLETED); // assume completed
        $actionEntity->setRecordId($orderContext->getRecordId());
        $actionEntity->setArmNumber($orderContext->getArmNumber());
        $actionEntity->setComment($orderContext->getComment());
        
        $actionEntity->setPerformedBy($orderContext->getUser());
        $actionEntity->setProject($orderContext->getProject());
        $actionEntity->setPerformedAt($now);
        return $actionEntity;
    }

    protected function handleException(ActionEntity $actionEntity, \Throwable $th): void {
        $error = $th->getCode() . ' - ' . $th->getMessage();
        $actionEntity->setStatus(ActionEntity::STATUS_ERROR);
        $actionEntity->setDetails($error);
    }
}
