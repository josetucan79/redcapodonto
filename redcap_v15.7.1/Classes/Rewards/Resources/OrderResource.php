<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Resources;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Traits\CanGetUserInfoFromId;

class OrderResource extends BaseEntityResource {
    use CanGetUserInfoFromId;
    /**
     * Transforms the entity into an associative array.
     *
     * @return array The array representation of the entity.
     */
    public function toArray() {
        /** @var OrderEntity $entity */
        $entity = $this->entity();
        $actions = $entity->getActions();
        if($actions->isEmpty()) $actions = [];
        return [
            'order_id' => $entity->getOrderId(),
            'reward_option_id' => $entity->getRewardOptionId(),
            'project_id' => $entity->getProjectId(),
            'arm_number' => $entity->getArmNumber(),
            'record_id' => $entity->getRecordId(),
            'scheduled_action' => $entity->getScheduledAction(),
            'internal_reference' => $entity->getInternalReference(),
            'reference_order' => $entity->getReferenceOrder(),
            'reward_id' => $entity->getRewardId(),
            'reward_name' => $entity->getRewardName(),
            'reward_value' => floatval($entity->getRewardValue()),
            'redeem_link' => $entity->getRedeemLink(),
            'eligibility_logic' => $entity->getEligibilityLogic(),
            'actions' => ResourceFactory::create(ActionEntity::class, $actions),
            'created_by' => $this->getUserInfo($entity->getCreatedBy()),
            'created_at' => $entity->getCreatedAt(),
        ];
    }

}