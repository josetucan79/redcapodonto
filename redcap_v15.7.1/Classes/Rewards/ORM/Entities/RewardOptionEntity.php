<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Vanderbilt\REDCap\Classes\ORM\Utils\SafeEntityAccessor;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Attributes\SoftDeleteField;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\SoftDeletableInterface;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\RewardOptionRepository;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Traits\SoftDeletableTrait;

#[ORM\Entity(repositoryClass: RewardOptionRepository::class)]
#[ORM\Table(name: "redcap_rewards_reward_option")]
class RewardOptionEntity implements LoggableEntityInterface, SoftDeletableInterface
{
    use SoftDeletableTrait;
    
    #[ORM\Id]
    #[ORM\Column(name: "reward_option_id", type: "integer")]
    #[ORM\GeneratedValue]
    private ?int $reward_option_id;

    #[ORM\ManyToOne(targetEntity: ProjectEntity::class)]
    #[ORM\JoinColumn(name: "project_id", referencedColumnName: "project_id", nullable: true, onDelete: "SET NULL")]
    private ?ProjectEntity $project = null;

    #[ORM\Column(name: "provider_product_id", type: "string", length: 255, nullable: true)]
    private ?string $provider_product_id = null;

    #[ORM\Column(name: "description", type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: "value_amount", type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?string $value_amount = null;

    #[ORM\Column(name: "eligibility_logic", type: "text", nullable: true)]
    private ?string $eligibility_logic = null;

    #[ORM\Column(name: "deleted_at", type: "datetime", nullable: true)]
    #[SoftDeleteField]
    private ?\DateTime $deleted_at = null;

    #[ORM\OneToMany(mappedBy: "rewardOption", targetEntity: OrderEntity::class)]
    private Collection $orders;

    public function __construct() { $this->orders = new ArrayCollection(); }

    public function getRewardOptionId(): ?int { return $this->reward_option_id; }
    public function getId(): ?int { return $this->reward_option_id; }

    public function getProject(): ?ProjectEntity { return $this->project; }
    public function setProject(?ProjectEntity $project): void { $this->project = $project; }
    public function getProjectId(): ?int { return SafeEntityAccessor::get($this->project, fn(ProjectEntity $project): int => $project->getProjectId()); }

    public function getProviderProductId(): ?string { return $this->provider_product_id; }
    public function setProviderProductId(?string $provider_product_id): void { $this->provider_product_id = $provider_product_id; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }

    public function getValueAmount(): ?string { return $this->value_amount; }
    public function setValueAmount(?string $value_amount): void { $this->value_amount = $value_amount; }

    public function getEligibilityLogic(): ?string { return $this->eligibility_logic; }
    public function setEligibilityLogic(?string $eligibility_logic): void { $this->eligibility_logic = $eligibility_logic; }

    public function getDeletedAt(): ?DateTime { return $this->deleted_at; }
    public function setDeletedAt(DateTime|string|null $deleted_at): void { $this->deleted_at = is_string($deleted_at) ? new \DateTime($deleted_at) : $deleted_at; }

    public function getOrders(): Collection { return $this->orders; }

    public function toLogArray(): array {
        return [
            'reward_option_id' => $this->reward_option_id,
            'project_id' => $this->getProjectId(),
            'provider_product_id' => $this->provider_product_id,
            'description' => $this->description,
            'value_amount' => $this->value_amount,
            'eligibility_logic' => $this->eligibility_logic,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
