<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Vanderbilt\REDCap\Classes\ORM\Utils\SafeEntityAccessor;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\OrderRepository;
use Vanderbilt\REDCap\Classes\Utility\TypeConverter;

/**
 * Represents an order of a reward
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'redcap_rewards_orders')]
class OrderEntity implements LoggableEntityInterface
{
    const STATUS_INVALID = 'invalid';
    const STATUS_ELIGIBLE = 'eligible';
    const STATUS_INELIGIBLE = 'ineligible';
    const STATUS_REVIEWER_APPROVED = 'reviewer:approved';
    const STATUS_REVIEWER_REJECTED = 'reviewer:rejected';
    const STATUS_BUYER_APPROVED = 'buyer:approved';
    const STATUS_BUYER_REJECTED = 'buyer:rejected';
    const STATUS_ORDER_PLACED = 'order:placed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PENDING = 'pending';
    const STATUS_CANCELED = 'canceled';

    // scheduled statuses set via bulk feature
    const STATUS_SCHEDULED_REVIEWER_APPROVED = 'scheduled:reviewer:approved';
    const STATUS_SCHEDULED_REVIEWER_REJECTED = 'scheduled:reviewer:rejected';
    const STATUS_SCHEDULED_BUYER_APPROVED = 'scheduled:buyer:approved';
    const STATUS_SCHEDULED_BUYER_REJECTED = 'scheduled:buyer:rejected';
    const STATUS_SCHEDULED_ORDER_PLACED = 'scheduled:order:placed';
    
    const STATUS_ERROR = 'error'; // maybe not necessary
    const STATUS_UNKNOWN = 'unknown'; // maybe not necessary

    const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    #[ORM\Id]
    #[ORM\Column(name: "order_id", type: "integer")]
    #[ORM\GeneratedValue]
    private ?int $order_id = null;

    #[ORM\ManyToOne(targetEntity: RewardOptionEntity::class, inversedBy: "orders")]
    #[ORM\JoinColumn(name: "reward_option_id", referencedColumnName: "reward_option_id", onDelete: "SET NULL")]
    private ?RewardOptionEntity $rewardOption = null;

    #[ORM\ManyToOne(targetEntity: ProjectEntity::class)]
    #[ORM\JoinColumn(name: "project_id", referencedColumnName: "project_id", onDelete: "SET NULL")]
    private ?ProjectEntity $project = null;

    #[ORM\Column(name: "arm_number", type: "integer", options: ["default" => 1])]
    private int $arm_number = 1;

    #[ORM\Column(name: "record_id", type: "integer", nullable: true)]
    private ?int $record_id = null;

    #[ORM\Column(name: "internal_reference", type: "string", length: 255, nullable: true)]
    private ?string $internal_reference = null;

    #[ORM\Column(name: "reference_order", type: "string", length: 255, nullable: true)]
    private ?string $reference_order = null;

    #[ORM\Column(name: "eligibility_logic", type: "text", nullable: true)]
    private ?string $eligibility_logic = null;

    #[ORM\Column(name: "reward_id", type: "string", length: 255, nullable: true)]
    private ?string $reward_id = null;

    #[ORM\Column(name: "reward_name", type: "string", length: 255, nullable: true)]
    private ?string $reward_name = null;

    #[ORM\Column(name: "reward_value", type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?string $reward_value = null;

    #[ORM\Column(name: "redeem_link", type: "text", nullable: true)]
    private ?string $redeem_link = null;

    #[ORM\Column(name: "status", type: "string", length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: "scheduled_action", type: "string", length: 255, nullable: true)]
    private ?string $scheduled_action = null;

    #[ORM\Column(name: "uuid", type: "string", length: 36)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)] // replace UserEntity with actual user entity
    #[ORM\JoinColumn(name: "created_by", referencedColumnName: "ui_id", onDelete: "SET NULL")]
    private ?UserEntity $createdBy = null;

    #[ORM\Column(name: "created_at", type: "datetime", nullable: true)]
    private ?DateTime $created_at = null;

    #[ORM\OneToMany(mappedBy: "order", targetEntity: ActionEntity::class)]
    private Collection $actions;

    public function __construct() { $this->actions = new ArrayCollection(); }

    public function getOrderId(): ?int { return $this->order_id; }
    public function getId(): ?int { return $this->order_id; }

    public function getArmNumber(): ?int { return $this->arm_number; }
    public function setArmNumber(?int $arm_number): void { $this->arm_number = $arm_number; }

    public function getRecordId(): ?int { return $this->record_id; }
    public function setRecordId(?int $record_id): void { $this->record_id = $record_id; }

    public function getInternalReference(): ?string { return $this->internal_reference; }
    public function setInternalReference(?string $internal_reference): void { $this->internal_reference = $internal_reference; }

    public function getReferenceOrder(): ?string { return $this->reference_order; }
    public function setReferenceOrder(?string $reference_order): void { $this->reference_order = $reference_order; }

    public function getEligibilityLogic(): ?string { return $this->eligibility_logic; }
    public function setEligibilityLogic(?string $eligibility_logic): void { $this->eligibility_logic = $eligibility_logic; }

    public function getRewardId(): ?string { return $this->reward_id; }
    public function setRewardId(?string $reward_id): void { $this->reward_id = $reward_id; }

    public function getRewardName(): ?string { return $this->reward_name; }
    public function setRewardName(?string $reward_name): void { $this->reward_name = $reward_name; }

    public function getRewardValue(): ?string { return $this->reward_value; }
    public function setRewardValue(?string $reward_value): void { $this->reward_value = $reward_value; }

    public function getRedeemLink(): ?string { return $this->redeem_link; }
    public function setRedeemLink(?string $redeem_link): void { $this->redeem_link = $redeem_link; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $status): void { $this->status = $status; }

    public function getScheduledAction(): ?string { return $this->scheduled_action; }
    public function setScheduledAction(?string $scheduled_action): void { $this->scheduled_action = $scheduled_action; }
    public function clearScheduledAction(): void { $this->scheduled_action = null; }

    public function getUuid(): ?string { return $this->uuid; }
    public function setUuid(?string $uuid): void { $this->uuid = $uuid; }

    public function getCreatedBy(): ?UserEntity { return $this->createdBy; }
    public function setCreatedBy(?UserEntity $createdBy): void { $this->createdBy = $createdBy; }
    public function getCreatedById(): ?int { return SafeEntityAccessor::get($this->createdBy, fn(UserEntity $createdBy): int => $createdBy->getUiId()); }

    public function getCreatedAt(): ?DateTime { return $this->created_at; }
    public function setCreatedAt(DateTime|string|null $created_at): void { $this->created_at = TypeConverter::toDateTime($created_at); }
    
    /** RELATIONSHIPS */
    public function getProject(): ?ProjectEntity { return $this->project; }
    public function setProject(?ProjectEntity $project): void { $this->project = $project; }
    public function getProjectId(): ?int { return SafeEntityAccessor::get($this->project, fn(ProjectEntity $project): int => $project->getProjectId()); }
    
    public function getRewardOption(): ?RewardOptionEntity { return $this->rewardOption; }
    public function setRewardOption(?RewardOptionEntity $rewardOption): void { $this->rewardOption = $rewardOption; }
    public function getRewardOptionId(): ?int { return SafeEntityAccessor::get($this->rewardOption, fn(RewardOptionEntity $rewardOption): int => $rewardOption->getRewardOptionId()); }


    public function getActions(): Collection { return $this->actions; }

    public function addAction(ActionEntity $action): void
    {
        if (!$this->actions->contains($action)) {
            $this->actions[] = $action;
            $action->setOrder($this);
        }
    }

    public function removeAction(ActionEntity $action): void
    {
        if ($this->actions->removeElement($action)) {
            if ($action->getOrder() === $this) {
                $action->setOrder(null);
            }
        }
    }

    /** @return ActionEntity[] */

    /** @param ActionEntity[] $entities */
    public function setActions($entities=[]) {
        foreach ($entities as $entity) {
            $this->addAction($entity);
        }
    }

    public function toLogArray(): array {
        return [
            'order_id' => $this->getOrderId(),
            'arm_number' => $this->getArmNumber(),
            'record_id' => $this->getRecordId(),
            'internal_reference' => $this->getInternalReference(),
            'reference_order' => $this->getReferenceOrder(),
            'eligibility_logic' => $this->getEligibilityLogic(),
            'reward_id' => $this->getRewardId(),
            'reward_name' => $this->getRewardName(),
            'reward_value' => $this->getRewardValue(),
            'redeem_link' => $this->getRedeemLink(),
            'status' => $this->getStatus(),
            'uuid' => $this->getUuid(),
            'created_by' => $this->getCreatedById(),
            'created_at' => $this->getCreatedAt(),
            'project_id' => $this->getProjectId(),
            'reward_option_id' => $this->getRewardOptionId(),
        ];
    }
}
