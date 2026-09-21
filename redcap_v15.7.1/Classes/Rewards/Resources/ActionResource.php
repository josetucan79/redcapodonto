<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Resources;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Traits\CanGetUserInfoFromId;

class ActionResource extends BaseEntityResource {
    use CanGetUserInfoFromId;

    /**
     * Transforms the entity into an associative array.
     *
     * @return array The array representation of the entity.
     */
    public function toArray() {
        /** @var ActionEntity $entity */
        $entity = $this->entity();
        return [
            'action_id' => $entity->getActionId(),
            'order_id' => $entity->getOrderId(),
            'project_id' => $entity->getProjectId(),
            'arm_number' => $entity->getArmNumber(),
            'record_id' => $entity->getRecordId(),
            'stage' => $entity->getStage(),
            'event' => $entity->getEvent(),
            'status' => $entity->getStatus(),
            'comment' => $entity->getComment(),
            'details' => $entity->getDetails(),
            'performed_by' => $this->getUserInfo($entity->getPerformedBy()),
            'performed_at' => $entity->getPerformedAt(),
        ];
    }

}