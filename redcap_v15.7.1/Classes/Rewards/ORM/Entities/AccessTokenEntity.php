<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Vanderbilt\REDCap\Classes\ORM\Utils\SafeEntityAccessor;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\AccessTokenRepository;

#[ORM\Entity(repositoryClass: AccessTokenRepository::class)]
#[ORM\Table(name: 'redcap_rewards_access_token')]
class AccessTokenEntity implements LoggableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(name: 'access_token_id', type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $access_token_id = null;

    #[ORM\Column(name: 'access_token', type: 'string', nullable: true)]
    private ?string $access_token = null;

    #[ORM\Column(name: 'scope', type: 'string', nullable: true)]
    private ?string $scope = null;

    #[ORM\Column(name: 'expires_in', type: 'integer', nullable: true)]
    private ?int $expires_in = null;

    #[ORM\Column(name: 'token_type', type: 'string', nullable: true)]
    private ?string $token_type = null;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    private ?DateTime $created_at = null;

    // #[ORM\Column(name: 'project_id', type: 'integer', nullable: true)]
    // private ?int $project_id = null;
    
    // #[ORM\Column(name: 'provider_id', type: 'integer', nullable: true)]
    // private ?int $provider_id = null;
    
    #[ORM\ManyToOne(targetEntity: ProjectEntity::class)]
    #[ORM\JoinColumn(name: "project_id", referencedColumnName: "project_id", onDelete: "CASCADE")]
    private ?ProjectEntity $project = null;
    
    #[ORM\ManyToOne(targetEntity: ProviderEntity::class, cascade: ["persist"])]
    #[ORM\JoinColumn(name: "provider_id", referencedColumnName: "provider_id", onDelete: "CASCADE")]
    private ?ProviderEntity $provider = null;

    public function getAccessTokenId(): ?int { return $this->access_token_id; }
    public function getId(): ?int { return $this->access_token_id; }

    public function getAccessToken(): ?string { return $this->access_token; }
    public function setAccessToken(?string $value): void { $this->access_token = $value; }

    public function getScope(): ?string { return $this->scope; }
    public function setScope(?string $value): void { $this->scope = $value; }

    public function getExpiresIn(): ?int { return $this->expires_in; }
    public function setExpiresIn(?int $value): void { $this->expires_in = $value; }

    public function getTokenType(): ?string { return $this->token_type; }
    public function setTokenType(?string $value): void { $this->token_type = $value; }

    public function getCreatedAt(): ?DateTime { return $this->created_at; }
    public function setCreatedAt(?DateTime $value): void { $this->created_at = $value; }
    
    public function getProject(): ?ProjectEntity { return $this->project; }
    public function setProject(?ProjectEntity $project): void { $this->project = $project; }
    public function getProjectId(): ?int { return SafeEntityAccessor::get($this->project, fn(ProjectEntity $project): int => $project->getProjectId()); }
    
    public function getProvider(): ?ProviderEntity { return $this->provider; }
    public function setProvider(?ProviderEntity $provider): void { $this->provider = $provider; }
    public function getProviderId(): ?int { return SafeEntityAccessor::get($this->provider, fn(ProviderEntity $provider): int => $provider->getProviderId()); }

    public function toLogArray(): array {
        return [
            'id' => $this->getId(),
            'access_token' => $this->getAccessToken(),
            'scope' => $this->getScope(),
            'expires_in' => $this->getExpiresIn(),
            'token_type' => $this->getTokenType(),
            'created_at' => $this->getCreatedAt(),
            'project_id' => $this->getProjectId(),
            'provider_id' => $this->getProviderId(),
        ];
    }
}
