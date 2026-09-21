<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use Doctrine\ORM\Mapping as ORM;
use Vanderbilt\REDCap\Classes\ORM\Utils\SafeEntityAccessor;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;

#[ORM\Entity]
#[ORM\Table(name: 'redcap_rewards_project_providers')]
#[ORM\UniqueConstraint(name: 'unique_project_provider', columns: ['project_id', 'provider_id'])]
class ProjectProviderEntity implements LoggableEntityInterface
{

    // #[ORM\Id]
    // #[ORM\Column(name: 'project_id', type: 'integer')]
    // private int $project_id;
    
    // #[ORM\Id]
    // #[ORM\Column(name: 'provider_id', type: 'integer')]
    // private int $provider_id;
    
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: ProjectEntity::class, inversedBy: 'projectProviders')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private ProjectEntity $project;
    
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: ProviderEntity::class, inversedBy: 'projectProviders')]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'provider_id', nullable: false, onDelete: 'CASCADE')]
    private ProviderEntity $provider;

    public function getProject(): ?ProjectEntity { return $this->project; }
    public function setProject(?ProjectEntity $project): void { $this->project = $project; }
    public function getProjectId(): ?int { return SafeEntityAccessor::get($this->project, fn(ProjectEntity $project): int => $project->getProjectId()); }

    
    public function getProvider(): ?ProviderEntity { return $this->provider; }
    public function setProvider(?ProviderEntity $provider): void { $this->provider = $provider; }
    public function getProviderId(): ?int { return SafeEntityAccessor::get($this->provider, fn(ProviderEntity $provider): int => $provider->getProviderId()); }


    public function toLogArray(): array
    {
        return [
            'project_id' => $this->getProjectId(),
            'provider_id' => $this->getProviderId(),
        ];
    }

}

