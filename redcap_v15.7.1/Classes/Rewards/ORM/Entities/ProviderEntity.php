<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;

#[ORM\Entity]
#[ORM\Table(name: 'redcap_rewards_providers')]
class ProviderEntity implements LoggableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(name: 'provider_id', type: 'integer')]
    #[ORM\GeneratedValue]
    private int $provider_id;

    #[ORM\Column(name: 'provider_name', type: 'string', length: 255)]
    private string $provider_name;

    #[ORM\Column(name: 'is_default', type: 'boolean', options: ['default' => false])]
    private bool $is_default = false;

    #[ORM\OneToMany(mappedBy: "provider", targetEntity: ProjectProviderEntity::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $projectProviders;

    #[ORM\OneToMany(mappedBy: "provider", targetEntity: ProviderSettingEntity::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $settings;

    #[ORM\OneToMany(mappedBy: "provider", targetEntity: AccessTokenEntity::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $accessTokens;

    public function __construct() {
        $this->projectProviders = new ArrayCollection();
        $this->settings = new ArrayCollection();
        $this->accessTokens = new ArrayCollection();
    }

    public function getProviderId(): ?int { return $this->provider_id; }
    public function getId(): ?int { return $this->provider_id; }

    public function getProviderName(): ?string { return $this->provider_name; }
    public function setProviderName(?string $provider_name): void { $this->provider_name = $provider_name; }

    public function getIsDefault(): ?bool { return $this->is_default; }
    public function setIsDefault(?bool $is_default): void { $this->is_default = $is_default; }

    /** RELATIONSHIPS */

    public function getProjectProviders(): Collection { return $this->projectProviders; }
    
    public function getSettings(): Collection { return $this->settings; }

    public function getAccessTokens(): Collection { return $this->accessTokens; }
    public function addAccessToken(AccessTokenEntity $token): void
    {
        if (!$this->accessTokens->contains($token)) {
            $this->accessTokens[] = $token;
            $token->setProvider($this);
        }
    }


    public function toLogArray(): array
    {
        return [
            'provider_id' => $this->provider_id ?? null,
            'provider_name' => $this->provider_name ?? null,
            'is_default' => $this->is_default ?? null,
        ];
    }


}
