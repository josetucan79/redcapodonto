<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow;

use DateTime;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Repositories\BaseRepository;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\OrderProcessingService;

class OrderSchedulerService {

    /**
     *  @var BaseRepository<OrderEntity>
     */
    protected $orderRepo;

    /**
     *  @var OrderProcessingService
     */
    protected $orderProcessingService;

    /**
     *  @var OrderEmailService
     */
    protected $orderEmailService;

    /**
     *  @var int
     */
    protected $project_id;

    /** @var int */
    protected $user_id;

    /**
     *
     * @param BaseRepository<OrderEntity> $orderRepo
     * @param OrderProcessingService $orderProcessingService
     * @param OrderEmailService $orderEmailService
     * @param int $project_id
     * @param int $user_id
     */
    public function __construct($orderRepo, $orderProcessingService, $orderEmailService,$project_id, $user_id = null) {
        $this->orderRepo = $orderRepo;
        $this->orderEmailService = $orderEmailService;
        $this->orderProcessingService = $orderProcessingService;
        $this->project_id = $project_id;
        $this->user_id = $user_id;
    }

    public function scheduleOrder($record_id, $rewardOptionID, $arm_num = 1) {
        $rewardOption = RewardOptionService::getValidRewardOption($rewardOptionID, $arm_num, $record_id);
        return $this->createScheduledEntity($arm_num, $record_id, $rewardOption);
    }

    public function processScheduledOrder($orderID) {
        $orderEntity = $this->orderRepo->read($orderID);
        if (!$orderEntity instanceof OrderEntity) return false;
        if ($orderEntity->getStatus() !== OrderEntity::STATUS_SCHEDULED) return false;

        try {
            //code...
            $rewardOptionID = $orderEntity->getRewardOptionId();
            $arm_number = $orderEntity->getArmNumber();
            $recordID = $orderEntity->getRecordId();
            // Get the reward option and send the order to the provider using OrderProcessingService
            $rewardOption = RewardOptionService::getValidRewardOption($rewardOptionID, $arm_number, $recordID);
            $rewardData = $this->orderProcessingService->sendOrder($rewardOption, $orderEntity->getArmNumber(), $orderEntity->getRecordId(), $internalRefID);
            $referenceOrderID = $rewardData['referenceOrderID'] ?? null;
            $redemptionLink = $rewardData['reward']['credentials']['Redemption Link'] ?? 'no redeem code';
            
            // Update the order and set status to completed
            $orderEntity->setReferenceOrder($referenceOrderID);
            $orderEntity->setRedeemLink($redemptionLink);
            $orderEntity->setInternalReference($internalRefID);
            $orderEntity->setStatus(OrderEntity::STATUS_COMPLETED);
        } catch (\Throwable $th) {
            $orderEntity->setStatus(OrderEntity::STATUS_ERROR);
        }
        return $this->orderRepo->update($orderEntity);
    }

    protected function createScheduledEntity($arm_num, $record_id, $rewardOption) {
        $now = new DateTime();
        $providerProductId = $rewardOption->getProviderProductId();
        $description = $rewardOption->getDescription();
        $value_amount = $rewardOption->getValueAmount();
        $rewardOptionId = $rewardOption->getRewardOptionId();

        // Create a new scheduled order entity
        $orderEntity = new OrderEntity();
        $orderEntity->setRewardId($providerProductId);
        $orderEntity->setProjectId($this->project_id);
        $orderEntity->setArmNumber($arm_num);
        $orderEntity->setRecordId($record_id);
        $orderEntity->setRewardOptionId($rewardOptionId);
        $orderEntity->setRewardName($description);
        $orderEntity->setRewardValue($value_amount);
        $orderEntity->setStatus(OrderEntity::STATUS_SCHEDULED);
        $orderEntity->setCreatedBy($this->user_id);
        $orderEntity->setCreatedAt($now);

        return $this->orderRepo->create($orderEntity);
    }
}
