<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\Traits;

use DateTime;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Utility\UUID;

trait ContextDataTrait {

    protected function createOrderEntity(OrderContextDTO $orderContext): OrderEntity {
        // get a reference to the user
        $rewardOptionEntity = $orderContext->getRewardOption();
        $userEntity = $orderContext->getUser();
        $projectEntity = $orderContext->getProject();

        // if (!$this->entityManager->contains($rewardOptionEntity)) {
        //     error_log('RewardOptionEntity is detached');
        // }

        $now = new DateTime();
        $orderEntity = new OrderEntity();
        $orderEntity->setProject($projectEntity);
        $orderEntity->setRecordId($orderContext->getRecordId());
        $orderEntity->setArmNumber($orderContext->getArmNumber());
        // $orderEntity->setRewardOptionId($orderContext->getRewardOptionId());
        
        $orderEntity->setRewardOption($rewardOptionEntity);
        $orderEntity->setRewardId($rewardOptionEntity->getProviderProductId());
        $orderEntity->setRewardName($rewardOptionEntity->getDescription());
        $orderEntity->setRewardValue($rewardOptionEntity->getValueAmount());
        $orderEntity->setEligibilityLogic($rewardOptionEntity->getEligibilityLogic());
        $orderEntity->setStatus($orderContext->getStatus()); // make sure there is the current status

        $orderEntity->setCreatedBy($userEntity);
        $orderEntity->setUUID(UUID::make());
        $orderEntity->setCreatedAt($now);
        return $orderEntity;
    }

}
