<?php
namespace Vanderbilt\REDCap\Classes\Rewards\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserEntity;

/**
 * Class OrderContextDTO
 *
 * Represents the context for processing an order in the approval or rejection workflow.
 * This Data Transfer Object (DTO) encapsulates all the necessary information related to
 * a specific order, including the record ID, reward option ID, arm number, and an optional comment.
 *
 * It is used in the RewardApprovalService and related strategy classes to provide a structured
 * way to pass data around without using multiple parameters, thus improving code readability
 * and maintainability.
 *
 * Usage:
 * 1. Create an instance of OrderContext with the required data:
 *    $orderContext = new OrderContext(1, 101, 1, 'Approving the order');
 *
 * 2. Pass the OrderContext instance to the RewardApprovalService methods:
 *    $rewardApprovalService = new RewardApprovalService($project_id);
 *    $rewardApprovalService->stepApproval($orderContext);
 *
 * 3. For bulk operations, create multiple OrderContext instances and pass them as an array:
 *    $orderContexts = [
 *        new OrderContext(15, 2, 1, 101, 1, 'Bulk approval 1'),
 *        new OrderContext(15, 2, 2, 102, 1, 'Bulk approval 2'),
 *        new OrderContext(15, 2, 3, 103, 1, 'Bulk approval 3'),
 *    ];
 *    $rewardApprovalService->bulkStepApproval($orderContexts);
 */
class OrderContextDTO extends BaseDTO {
    const COMMAND_EXECUTE = 'execute';
    const COMMAND_SCHEDULE = 'schedule';
    
    /** @var string*/
    private $status;
    
    /** @var OrderEntity|null*/
    private $order;
    
    /** @var RewardOptionEntity|null*/
    private $reward_option;
    
    /** @var UserEntity|null*/
    private $user;

    /** @var ProjectEntity|null*/
    private $project;

    public function __construct(
        private int $project_id,
        private int $user_id,
        private int $record_id,
        private int $reward_option_id,
        private int $arm_num,
        private string $comment,
        private string $action,
        private string $command = self::COMMAND_EXECUTE
    ) {
        $this->status = OrderEntity::STATUS_UNKNOWN;
    }

    public function getProjectId(): int { return $this->project_id; }
    public function setProjectId(int $value): void { $this->project_id = $value; }

    public function getUserId(): int { return $this->user_id; }
    public function setUserId(int $value): void { $this->user_id = $value; }

    public function getRecordId(): int { return $this->record_id; }
    public function setRecordId(int $value): void { $this->record_id = $value; }

    public function getRewardOptionId(): int { return $this->reward_option_id; }
    public function setRewardOptionId(int $value): void { $this->reward_option_id = $value; }

    public function getArmNumber(): int { return $this->arm_num; }
    public function setArmNumber(int $value): void { $this->arm_num = $value; }

    public function getComment(): string { return $this->comment; }
    public function setComment(string $value): void { $this->comment = $value; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $value): void { $this->status = $value; }

    public function getOrder(): ?OrderEntity { return $this->order; }
    public function setOrder(?OrderEntity $value): void { $this->order = $value; }

    public function getUser(): ?UserEntity { return $this->user; }
    public function setUser(?UserEntity $value): void { $this->user = $value; }

    public function getRewardOption(): ?RewardOptionEntity { return $this->reward_option; }
    public function setRewardOption(?RewardOptionEntity $value): void { $this->reward_option = $value; }

    public function getProject(): ?ProjectEntity { return $this->project; }
    public function setProject(?ProjectEntity $value): void { $this->project = $value; }

    public function setAction(string $action): void { $this->action = $action; }
    public function getAction(): ?string { return $this->action; }

    public function getCommand(): string { return $this->command; }
    
    public function setCommand(string $command): void { $this->command = $command; }
    
}
