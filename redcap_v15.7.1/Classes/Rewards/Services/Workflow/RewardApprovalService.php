<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow;

use Doctrine\ORM\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\BuyerApprovalStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\BuyerRejectionStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\PlaceOrderStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\ReviewerApprovalStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\ReviewerRejectionStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\ReviewerRestoreStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\ScheduleStrategy;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies\SendEmailStrategy;
use Vanderbilt\REDCap\Classes\Utility\REDCapData\REDCapData;

class RewardApprovalService {
    /**
     *
     * @param integer $project_id
     * @param integer|null $user_id
     */
    public function __construct(
        protected EntityManager $entityManager,
        protected int $project_id,
        protected ?int $user_id=null
    ) {}

    public function getTransitions(): array {
        return [
            OrderEntity::STATUS_ELIGIBLE => [
                ActionEntity::EVENT_REVIEWER_APPROVAL => OrderEntity::STATUS_REVIEWER_APPROVED,
                ActionEntity::EVENT_REVIEWER_REJECTION => OrderEntity::STATUS_REVIEWER_REJECTED,
            ],
            OrderEntity::STATUS_REVIEWER_REJECTED => [
                ActionEntity::EVENT_REVIEWER_RESTORE => function($currentState, ...$args) {
                    $orderContext = $args[0] ?? null;
                    if(!$orderContext instanceof OrderContextDTO) return OrderEntity::STATUS_UNKNOWN;
                    $status = $this->getStatusByIds(
                        $orderContext->getRewardOptionId(),
                        $orderContext->getProjectId(),
                        $orderContext->getArmNumber(),
                        $orderContext->getRecordId()
                    );
                    return $status;
                },
            ],
            OrderEntity::STATUS_REVIEWER_APPROVED => [
                ActionEntity::EVENT_BUYER_APPROVAL => OrderEntity::STATUS_BUYER_APPROVED,
                ActionEntity::EVENT_BUYER_REJECTION => OrderEntity::STATUS_BUYER_REJECTED,
            ],
            OrderEntity::STATUS_BUYER_REJECTED => [
                ActionEntity::EVENT_REVIEWER_APPROVAL => OrderEntity::STATUS_REVIEWER_APPROVED,
                ActionEntity::EVENT_REVIEWER_REJECTION => OrderEntity::STATUS_REVIEWER_REJECTED,
            ],
            OrderEntity::STATUS_BUYER_APPROVED => [
                ActionEntity::EVENT_PLACE_ORDER => OrderEntity::STATUS_ORDER_PLACED,
            ],
            OrderEntity::STATUS_ORDER_PLACED => [
                ActionEntity::EVENT_SEND_EMAIL => OrderEntity::STATUS_COMPLETED,
            ],
            // Self-transitions: list of actions still allowed once the order is completed
            OrderEntity::STATUS_COMPLETED => [
                ActionEntity::EVENT_SEND_EMAIL => OrderEntity::STATUS_COMPLETED,
                ActionEntity::EVENT_PLACE_ORDER => OrderEntity::STATUS_COMPLETED,
            ],
        ];
    }

    public function executeAction(string $action, OrderContextDTO $orderContext) {
        $this->enrichOrderContext($orderContext);
        $orderContext->setAction($action);    

        $currentStatus = $orderContext->getStatus();

        // Initialize state machine
        $stateMachine = new OrderStateMachine($currentStatus, $this->getTransitions());

        // Determine strategy
        $strategyClass = $this->resolveStrategy($action, $orderContext);

        // Execute strategy
        $strategy = new $strategyClass($this->entityManager, $stateMachine);
        $strategy->execute($orderContext);
    }

    /**
     *
     * @param string $action
     * @param OrderContextDTO[] $orderContextList
     * @return void
     */
    public function bulkExecuteAction($action, array $orderContextList): void {
        foreach ($orderContextList as $orderContext) {
            $this->executeAction($action, $orderContext);
        }
    }

    /**
     * Enriches the order context with current status and order information
     * 
     * @param OrderContextDTO $orderContext The context to enrich
     * @return void
     */
    protected function enrichOrderContext(OrderContextDTO $orderContext): void {
        $rewardOptionId = $orderContext->getRewardOptionId();
        $userId = $orderContext->getUserId();
        $projectID = $orderContext->getProjectId();

        // Retrieve the reward option entity
        if ($rewardOptionId) {
            $rewardOption = $this->entityManager->find(RewardOptionEntity::class, $rewardOptionId);
            if (!$rewardOption instanceof RewardOptionEntity) {
                throw new \Exception("Cannot find a reward option with ID {$rewardOptionId}", 404);
            }
            $orderContext->setRewardOption($rewardOption);
        }
        // Retrieve the project entity
        if ($projectID) {
            $project = $this->entityManager->find(ProjectEntity::class, $projectID);
            if (!$project instanceof ProjectEntity) {
                throw new \Exception("Cannot find a project with ID {$projectID}", 404);
            }
            $orderContext->setProject($project);
        }
        
        // Retrieve the user entity if needed
        if ($userId) {
            $user = $this->entityManager->find(UserEntity::class, $userId); // Adjust to your actual User entity class
            if (!$user instanceof UserEntity) {
                throw new \Exception("Cannot find a user with UI_ID {$userId}", 404);
            }
            $orderContext->setUser($user);
        }
        
        // Get current order and status using the context
        $current_order = null;
        $current_status = $this->getCurrentStatus($orderContext, $current_order);
        
        // Update the context with the status and order information
        $orderContext->setStatus($current_status);
        if ($current_order) {
            $orderContext->setOrder($current_order);
        }
    }
    

    /**
     *
     * @param OrderContextDTO $orderContext
     * @param OrderEntity|null $most_recent_order
     * @return string
     */
    public function getCurrentStatus(OrderContextDTO $orderContext, &$most_recent_order=null): string {
        // Extract needed values from context
        $project = $orderContext->getProject();
        $record_id = $orderContext->getRecordId();
        $arm_number = $orderContext->getArmNumber();
        $reward_option = $orderContext->getRewardOption();
        $reward_option_id = $orderContext->getRewardOptionId();
        
        // Step 1: start with an UNKNOWN status
        $status = OrderEntity::STATUS_UNKNOWN;
    
        // Step 2: retrieve orders
        $ordersRepo = $this->entityManager->getRepository(OrderEntity::class);
        $orders = $ordersRepo->findBy([
            'project' => $project,
            'rewardOption' => $reward_option,
            'record_id' => $record_id,
            'arm_number' => $arm_number,
        ]);
    
        // Step 3: Get the most recent non-canceled order
        $valid_orders = [];
        foreach ($orders as $order) {
            if ($order->getStatus() !== OrderEntity::STATUS_CANCELED) {
                $valid_orders[] = $order;
            }
        }
    
        // Step 4: identify status
        if (!empty($valid_orders)) {
            // Sort orders by creation date descending to get the most recent
            usort($valid_orders, function($a, $b) {
                return $b->getCreatedAt() <=> $a->getCreatedAt();
            });
            $most_recent_order = reset($valid_orders);
            $status = $most_recent_order->getStatus();
        } else {
            // If no reward option in context, retrieve it
            if (!$reward_option) {
                $rewardOptionRepo = $this->entityManager->getRepository(RewardOptionEntity::class);
                $reward_option = $rewardOptionRepo->find($reward_option_id);
                $orderContext->setRewardOption($reward_option);
            }
            $status = $this->getStatus($reward_option, $arm_number, $record_id);
        }
        return $status;
    }

    /**
     * Determine the status for the given reward option based on its eligibility.
     *
     * This method evaluates whether the provided reward option is eligible
     * for a given arm and record, optionally using additional record data.
     *
     * @param RewardOptionEntity $reward_option The reward option entity.
     * @param int                $arm_number     The arm number.
     * @param int|string         $record_id      The identifier for the record.
     * @param array|null         $record_data    Optional record data to aid in eligibility evaluation.
     *
     * @return string Returns one of the following statuses:
     *                - OrderEntity::STATUS_ELIGIBLE if eligible,
     *                - OrderEntity::STATUS_INELIGIBLE if not eligible,
     *                - OrderEntity::STATUS_INVALID if an error occurs.
     */
    public function getStatus(RewardOptionEntity $reward_option, int $arm_number, int|string $record_id, ?array $record_data=null) {
        try {
            $eligible = RewardOptionService::isEligible($reward_option, $arm_number, $record_id, $record_data);
            $status = ($eligible) ? OrderEntity::STATUS_ELIGIBLE : OrderEntity::STATUS_INELIGIBLE;
        } catch (\Throwable $th) {
            $status = OrderEntity::STATUS_INVALID;
        } finally {
            return $status;
        }
    }

    /**
     * Retrieve the reward option and associated record data using their IDs and determine the status.
     *
     * This method fetches the reward option entity by its ID and obtains the record data
     * from the project, then delegates to the getStatus() method to evaluate the reward's eligibility.
     *
     * @param int        $reward_option_id The reward option ID.
     * @param int|string $project_id       The project identifier.
     * @param int        $arm_number       The arm number.
     * @param int|string $record_id        The record identifier.
     *
     * @return string Returns one of the following statuses:
     *                - OrderEntity::STATUS_ELIGIBLE if eligible,
     *                - OrderEntity::STATUS_INELIGIBLE if not eligible,
     *                - OrderEntity::STATUS_INVALID if an error occurs.
     */
    public function getStatusByIds(int $reward_option_id, int|string $project_id, int $arm_number, int|string $record_id) {
        $reward_option = $this->entityManager->find(RewardOptionEntity::class, $reward_option_id);
        $generator = REDCapData::forProject($project_id)
            ->whereArms([$arm_number])
            ->whereRecords([$record_id])
            ->get($result);
        $record_data = $generator->current();
        return $this->getStatus($reward_option, $arm_number, $record_id, $record_data);
    }

    /* public function getOrCacheStatus($reward_option, $arm_number, $record_id, $record_data=null) {
        $filecache = new FileCache(__CLASS__);
        $hash = serialize(func_get_args());
        $key = sha1($hash);
        $status =  $filecache->get($key);
        if(!$status) {
            $status = $this->getStatus($reward_option, $arm_number, $record_id, $record_data);
            $filecache->set($key, $status);
        }
        return $status;
    } */

    /**
     *
     * @param string $action
     * @return StepActionStrategy
     */
    private function resolveStrategy(string $action, OrderContextDTO $orderContext): string {
        if($orderContext->getCommand() === OrderContextDTO::COMMAND_SCHEDULE) {
            return ScheduleStrategy::class;
        }
        $strategyMap = $this->getStrategyMap();
        if (!isset($strategyMap[$action])) {
            throw new \InvalidArgumentException("Unknown strategy for action: $action");
        }
        return $strategyMap[$action];
    }


    public function getStrategyMap(): array {
        return [
            ActionEntity::EVENT_REVIEWER_APPROVAL => ReviewerApprovalStrategy::class,
            ActionEntity::EVENT_REVIEWER_REJECTION => ReviewerRejectionStrategy::class,
            ActionEntity::EVENT_REVIEWER_RESTORE=> ReviewerRestoreStrategy::class,
            ActionEntity::EVENT_BUYER_APPROVAL => BuyerApprovalStrategy::class,
            ActionEntity::EVENT_BUYER_REJECTION => BuyerRejectionStrategy::class,
            ActionEntity::EVENT_PLACE_ORDER => PlaceOrderStrategy::class,
            ActionEntity::EVENT_SEND_EMAIL => SendEmailStrategy::class,
        ];
     }
}
