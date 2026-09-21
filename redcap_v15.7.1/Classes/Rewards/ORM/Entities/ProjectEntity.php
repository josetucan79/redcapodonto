<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "redcap_projects")]
class ProjectEntity
{
    #[ORM\Id]
    #[ORM\Column(name: "project_id", type: "integer")]
    #[ORM\GeneratedValue]
    private int $project_id;

    #[ORM\Column(name: "project_name", type: "string", nullable: true)]
    private string $project_name;

    #[ORM\Column(name: "app_title", type: "text", nullable: true)]
    private string $app_title;

    #[ORM\Column(name: "status", type: "integer")]
    private int $status;

    #[ORM\Column(name: "creation_time", type: "datetime", nullable: true)]
    private \DateTime $creation_time;

    #[ORM\Column(name: "production_time", type: "datetime", nullable: true)]
    private \DateTime $production_time;

    #[ORM\Column(name: "inactive_time", type: "datetime", nullable: true)]
    private \DateTime $inactive_time;

    #[ORM\Column(name: "completed_time", type: "datetime", nullable: true)]
    private \DateTime $completed_time;

    #[ORM\Column(name: "completed_by", type: "string", nullable: true)]
    private string $completed_by;

    #[ORM\Column(name: "data_locked", type: "boolean")]
    private bool $data_locked;

    #[ORM\Column(name: "log_event_table", type: "string")]
    private string $log_event_table;

    #[ORM\Column(name: "data_table", type: "string")]
    private string $data_table;

    #[ORM\Column(name: "created_by", type: "integer", nullable: true)]
    private int $created_by;

    #[ORM\Column(name: "rewards_enabled", type: "boolean")]
    private bool $rewards_enabled;

    #[ORM\OneToMany(mappedBy: "project", targetEntity: ProjectSettingEntity::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $settings;
    
    #[ORM\OneToMany(mappedBy: "project", targetEntity: ProjectProviderEntity::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $projectProviders;
    
    public function __construct() {
        $this->settings = new ArrayCollection();
        $this->projectProviders = new ArrayCollection();
    }

    public function getProjectId(): ?int { return $this->project_id; }
    public function getId(): ?int { return $this->project_id; }

    public function getProjectName(): ?string { return $this->project_name; }
    public function setProjectName(?string $project_name): void { $this->project_name = $project_name; }

    public function getAppTitle(): ?string { return $this->app_title; }
    public function setAppTitle(?string $app_title): void { $this->app_title = $app_title; }

    public function getStatus(): ?int { return $this->status; }
    public function setStatus(?int $status): void { $this->status = $status; }

    public function getCreationTime(): ?DateTime { return $this->creation_time; }
    public function setCreationTime(?DateTime $creation_time): void { $this->creation_time = $creation_time; }

    public function getProductionTime(): ?DateTime { return $this->production_time; }
    public function setProductionTime(?DateTime $production_time): void { $this->production_time = $production_time; }

    public function getInactiveTime(): ?DateTime { return $this->inactive_time; }
    public function setInactiveTime(?DateTime $inactive_time): void { $this->inactive_time = $inactive_time; }

    public function getCompletedTime(): ?DateTime { return $this->completed_time; }
    public function setCompletedTime(?DateTime $completed_time): void { $this->completed_time = $completed_time; }

    public function getCompletedBy(): ?string { return $this->completed_by; }
    public function setCompletedBy(?string $completed_by): void { $this->completed_by = $completed_by; }

    public function getDataLocked(): bool { return $this->data_locked; }
    public function setDataLocked(bool $data_locked): void { $this->data_locked = $data_locked; }

    public function getLogEventTable(): ?string { return $this->log_event_table; }
    public function setLogEventTable(?string $log_event_table): void { $this->log_event_table = $log_event_table; }

    public function getDataTable(): ?string { return $this->data_table; }
    public function setDataTable(?string $data_table): void { $this->data_table = $data_table; }

    public function getCreatedBy(): ?int { return $this->created_by; }
    public function setCreatedBy(?int $created_by): void { $this->created_by = $created_by; }

    public function getRewardsEnabled(): ?bool { return $this->rewards_enabled; }
    public function setRewardsEnabled(?bool $rewards_enabled): void { $this->rewards_enabled = $rewards_enabled; }

    /** RELATIONSHIPS */
    public function getSettings(): Collection { return $this->settings; }
    public function getProjectProviders(): Collection { return $this->projectProviders; }
    
    // Helper method to get providers through the join table
    public function getProviders(): Collection {
        $providers = new ArrayCollection();
        foreach ($this->projectProviders as $projectProvider) {
            $providers->add($projectProvider->getProvider());
        }
        return $providers;
    }
}
