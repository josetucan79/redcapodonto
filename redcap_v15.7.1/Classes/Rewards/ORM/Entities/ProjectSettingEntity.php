<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Vanderbilt\REDCap\Classes\ORM\Utils\SafeEntityAccessor;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;

#[ORM\Entity]
#[ORM\Table(name: 'redcap_rewards_project_settings')]
class ProjectSettingEntity implements LoggableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(name: 'project_setting_id', type: 'integer')]
    #[ORM\GeneratedValue]
    private int $project_setting_id;

    #[ORM\ManyToOne(targetEntity: ProjectEntity::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ProjectEntity $project;

    #[ORM\Column(name: 'setting_key', type: 'string', length: 255)]
    private string $setting_key;

    #[ORM\Column(name: 'setting_value', type: 'text', nullable: true)]
    private ?string $setting_value = null;

    public function getProjectSettingId(): ?int { return $this->project_setting_id; }
    public function getId(): ?int { return $this->project_setting_id; }

    public function getProject(): ?ProjectEntity { return $this->project; }
    public function setProject(?ProjectEntity $project): void { $this->project = $project; }
    public function getProjectId(): ?int { return SafeEntityAccessor::get($this->project, fn(ProjectEntity $project): int => $project->getProjectId()); }


    public function getSettingKey(): ?string { return $this->setting_key; }
    public function setSettingKey(?string $setting_key): void { $this->setting_key = $setting_key; }

    public function getSettingValue(): ?string { return $this->setting_value; }
    public function setSettingValue(?string $setting_value): void { $this->setting_value = $setting_value; }

    /** RELATIONSHIPS */

    public function getRelatedProviders(): Collection { return $this->project->getProviders(); }
    
    // Helper method to check if this setting is related to a specific provider
    public function isRelatedToProvider(ProviderEntity $provider): bool
    {
        foreach ($this->project->getProjectProviders() as $projectProvider) {
            if ($projectProvider->getProvider()->getProviderId() === $provider->getProviderId()) {
                return true;
            }
        }
        return false;
    }

    public function toLogArray(): array {
        return [
            'project_setting_id' => $this->getProjectSettingId(),
            'project_id' => $this->getProjectId(),
            'setting_key' => $this->getSettingKey(),
            'setting_value' => $this->getSettingValue(),
        ];
    }

}

