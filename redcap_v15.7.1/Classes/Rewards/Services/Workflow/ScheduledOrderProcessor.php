<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow;

use Doctrine\ORM\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalService;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager as FacadesEntityManager;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\OrderRepository;
use Vanderbilt\REDCap\Classes\Rewards\Services\ServiceFactory;
use Vanderbilt\REDCap\Classes\Rewards\Utility\RewardsFeatureChecker;
use Vanderbilt\REDCap\Classes\SystemMonitors\ResourceMonitor;
use Vanderbilt\REDCap\Classes\Utility\Context;

class ScheduledOrderProcessor
{
    const CHUNK_SIZE = 50;

    public function __construct(
        protected ResourceMonitor $resourceMonitor,
        protected int $chunkSize = self::CHUNK_SIZE,
        protected ?EntityManager $entityManager = null
    ){
        $this->entityManager = $entityManager ?? FacadesEntityManager::get();
    }

    public function run(): array
    {
        $processedProjects = [];
        $processedRecords = 0;
        $errors = [];

        $orders = $this->findScheduledOrders($this->chunkSize);

        foreach ($orders as $order) {
            // check if project is enabled for rewards
            $projectId = $order->getProjectId();
            if(!RewardsFeatureChecker::isProjectEnabled($projectId)) continue;

            if (!$this->resourceMonitor->checkResources()) {
                break;
            }

            try {
                $orderContext = new OrderContextDTO(
                    project_id: $project_id = $order->getProjectId(),
                    user_id: $user_id = $order->getCreatedBy()?->getUiId(),
                    record_id: $order->getRecordId(),
                    reward_option_id: $order->getRewardOptionId(),
                    arm_num: $order->getArmNumber(),
                    comment: '-- processed in a background process --',
                    action: $action = $order->getScheduledAction()
                );

                // set the context for the ServiceFactory
                Context::setProjectId($project_id);
                Context::setCurrentUser($user_id);

                $rewardApprovalService = ServiceFactory::make(RewardApprovalService::class);

                $rewardApprovalService->executeAction($action, $orderContext);

                $order->clearScheduledAction(); // clear after execution
                $this->saveOrder($order);

                $processedProjects[$order->getProjectId()] = true;
                $processedRecords++;
            } catch (\Throwable $e) {
                $errors[] = ("Failed to process scheduled order {$order->getId()}: " . $e->getMessage());
                continue; // skip and continue with next
            }
        }

        return [
            'projects' => count($processedProjects),
            'records' => $processedRecords,
            'errors' => $errors,
        ];
    }

    /**
     *
     * @param integer $limit
     * @return OrderEntity[]
     */
    protected function findScheduledOrders(int $limit): array
    {
        $em = $this->entityManager;
        /** @var OrderRepository $orderRepository */
        $orderRepository = $em->getRepository(OrderEntity::class);
        return $orderRepository->findScheduledOrders($limit);
    }

    protected function saveOrder($order): void
    {
        $em = $this->entityManager;
        $em->persist($order);
        $em->flush();
    }
}
