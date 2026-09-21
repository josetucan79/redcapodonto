<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow;

use Exception;
use Doctrine\ORM\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager as FacadesEntityManager;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardProviderInterface;
use Vanderbilt\REDCap\Classes\Rewards\Utility\ProjectArmFetcher;

/**
 * this service can place orders 
 */
class OrderService {


    public function __construct(
        protected EntityManager $entityManager,
        protected RewardProviderInterface $provider,
        protected $project_id,
        protected $user_id=null
    ) {}

    public function placeOrder($orderIdOrEntity) {
        $orderEntity = $orderIdOrEntity;
        if(is_numeric($orderIdOrEntity)) {
            $order_id = $orderIdOrEntity;
            $orderEntity = $this->entityManager->find(OrderEntity::class, $order_id);
        }
        if(!$orderEntity instanceof OrderEntity) return false;
        return $this->sendOrder($orderEntity);
    }

    /**
     * send an order to the provider along with an internal reference ID
     *
     * @param OrderEntity $orderEntity
     * @param string $internalRefID
     * @return OrderEntity
     */
    public function sendOrder($orderEntity, &$internalRefID=null) {
        try {
            $arm_number = $orderEntity->getArmNumber();
            $record_id = $orderEntity->getRecordId();
            $order_id = $orderEntity->getOrderId();
            $reward_option_id = $orderEntity->getRewardOptionId();
            $internalRefID = $this->makeInternalRefID($arm_number, $record_id, $reward_option_id, $order_id);
            $orderEntity->setInternalReference($internalRefID);
            // send the order; the provider will update relevant parts of the order
            $orderData = $this->provider->sendOrder($orderEntity);
            return $orderEntity;
        } catch (Exception $e) {
            // todo do something if error happens
            throw $e;
        }
    }

    /**
     *
     * @param int $arm_num
     * @param int|string $record_id
     * @param int $reward_option_id
     * @param int $order_id
     * @return string
     */
    protected function makeInternalRefID($arm_num, $record_id, $reward_option_id, $order_id) {
        $timestamp = date('YmdHis');
        $internalRefID = "P{$this->project_id}_A{$arm_num}_R{$record_id}_RO{$reward_option_id}-OI{$order_id}-{$timestamp}";
        return $internalRefID;
    }


    public static function getRewardURL($project_id, $event_id, $reward_option_id, $record) {
        $arm_number = ProjectArmFetcher::getArmNumForEventId($project_id, $event_id);
        $entityManager = FacadesEntityManager::get();
        $repo = $entityManager->getRepository(OrderEntity::class);

        $projectReference = $entityManager->getReference(ProjectEntity::class, $project_id);
        $rewardOptionReference = $entityManager->getReference(RewardOptionEntity::class, $reward_option_id);

        $order = $repo->findOneBy([
            'project' => $projectReference,
            'arm_number' => $arm_number,
            'record_id' => $record,
            'reward_option' => $rewardOptionReference,
        ]);
        if(!$order instanceof OrderEntity) return;
        return $order->getRedeemLink();
    }

}
