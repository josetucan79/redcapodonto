<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Vanderbilt\REDCap\Classes\ORM\Utils\SafeEntityAccessor;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;

/**
 * Represents an order of a reward
 */
#[ORM\Entity]
#[ORM\Table(name: 'redcap_rewards_actions')]
class ActionEntity implements LoggableEntityInterface
{
    const STAGE_ELIGIBILITY_REVIEW = 'eligibility_review';
    const STAGE_FINANCIAL_AUTHORIZATION = 'financial_authorization';
    const STAGE_COMPENSATION_DELIVERY = 'compensation_delivery';

    const EVENT_REVERT = 'revert';
    const EVENT_REVIEWER_APPROVAL = 'reviewer:approval';
    const EVENT_REVIEWER_REJECTION = 'reviewer:rejection';
    const EVENT_REVIEWER_RESTORE = 'reviewer:restore';
    const EVENT_BUYER_APPROVAL = 'buyer:approval';
    const EVENT_BUYER_REJECTION = 'buyer:rejection';
    const EVENT_PLACE_ORDER = 'place_order';
    const EVENT_SEND_EMAIL = 'send_email';
    
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ERROR = 'error';
    const STATUS_UNKNOWN = 'unknown';

    const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    #[ORM\Id]
    #[ORM\Column(name: 'action_id', type: 'integer')]
    #[ORM\GeneratedValue]
    private int $action_id;

    // #[ORM\Column(type: 'integer', nullable: true)]
    // private ?int $order_id = null;

    #[ORM\ManyToOne(targetEntity: OrderEntity::class, inversedBy: 'actions')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'order_id', nullable: true, onDelete: 'SET NULL')]
    private ?OrderEntity $order = null;

    #[ORM\ManyToOne(targetEntity: ProjectEntity::class)] // replace UserEntity with actual user entity
    #[ORM\JoinColumn(name: "project_id", referencedColumnName: "project_id", onDelete: "SET NULL")]
    private ?ProjectEntity $project = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $arm_number = 1;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $record_id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stage = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['default' => 'pending'])]
    private ?string $event = 'pending';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)] // replace UserEntity with actual user entity
    #[ORM\JoinColumn(name: "performed_by", referencedColumnName: "ui_id", onDelete: "SET NULL")]
    private ?UserEntity $performed_by = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $performed_at = null;

    public function getActionId(): ?int { return $this->action_id; }
    public function getId(): ?int { return $this->action_id; }
    
    public function getArmNumber(): ?int { return $this->arm_number; }
    public function setArmNumber(?int $value) { $this->arm_number = $value; }

    public function getRecordId(): ?string { return $this->record_id; }
    public function setRecordId(?string $value) { $this->record_id = $value; }

    public function getStage(): ?string { return $this->stage; }
    public function setStage(?string $value) { $this->stage = $value; }

    public function getEvent(): ?string { return $this->event; }
    public function setEvent(?string $value) { $this->event = $value; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $value) { $this->status = $value; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $value) { $this->comment = $value; }

    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $value) { $this->details = $value; }

    public function getPerformedAt(): ?DateTime { return $this->performed_at; }
    public function setPerformedAt(?DateTime $value) { $this->performed_at = $value; }
    
    /** RELATIONSHIPS **/

    public function getOrder(): ?OrderEntity { return $this->order; }
    public function setOrder(?OrderEntity $order): void { $this->order = $order; }
    public function getOrderId(): ?int { return SafeEntityAccessor::get($this->order, fn(OrderEntity $order): int => $order->getOrderId()); }
    
    public function getPerformedBy(): ?UserEntity { return $this->performed_by; }
    public function setPerformedBy(?UserEntity $performed_by): void { $this->performed_by = $performed_by; }
    public function getPerformedById(): ?int { return SafeEntityAccessor::get($this->performed_by, fn(UserEntity $performed_by): int => $performed_by->getUiId()); }
    
    public function getProject(): ?ProjectEntity { return $this->project; }
    public function setProject(?ProjectEntity $project): void { $this->project = $project; }
    public function getProjectId(): ?int { return SafeEntityAccessor::get($this->project, fn(ProjectEntity $project): int => $project->getProjectId()); }

    public function toLogArray(): array {
        return [
            'action_id' => $this->getId(),
            'order_id' => $this->getOrderId(),
            'project_id' => $this->getProjectId(),
            'arm_number' => $this->getArmNumber(),
            'record_id' => $this->getRecordId(),
            'stage' => $this->getStage(),
            'event' => $this->getEvent(),
            'status' => $this->getStatus(),
            'details' => $this->getDetails(),
            'performed_by_id' => $this->getPerformedById(),
            'performed_at' => $this->getPerformedAt(),
        ];
    }
}
