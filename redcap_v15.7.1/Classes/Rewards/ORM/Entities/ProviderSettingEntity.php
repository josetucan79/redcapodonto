<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use Doctrine\ORM\Mapping as ORM;
use Vanderbilt\REDCap\Classes\ORM\Utils\SafeEntityAccessor;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;

#[ORM\Entity]
#[ORM\Table(name: "redcap_rewards_settings")]
class ProviderSettingEntity implements LoggableEntityInterface
{
    #[ORM\Id]
    #[ORM\Column(name: "setting_id", type: "integer")]
    #[ORM\GeneratedValue]
    private int $setting_id;

    #[ORM\ManyToOne(targetEntity: ProviderEntity::class)]
    #[ORM\JoinColumn(name: "provider_id", referencedColumnName: "provider_id", nullable: false, onDelete: "CASCADE")]
    private ProviderEntity $provider;

    #[ORM\Column(name: "setting_key", type: "string", length: 255)]
    private string $setting_key;

    #[ORM\Column(name: "setting_value", type: "string", length: 255, nullable: true)]
    private ?string $setting_value = null;

    public function getSettingId(): ?int { return $this->setting_id; }
    public function getId(): ?int { return $this->setting_id; }

    public function getProvider(): ?ProviderEntity { return $this->provider; }
    public function setProvider(?ProviderEntity $provider): void { $this->provider = $provider; }
    public function getProviderId(): ?int { return SafeEntityAccessor::get($this->provider, fn(ProviderEntity $provider): int => $provider->getProviderId()); }


    public function getSettingKey(): ?string { return $this->setting_key; }
    public function setSettingKey(?string $setting_key): void { $this->setting_key = $setting_key; }

    public function getSettingValue(): ?string { return $this->setting_value; }
    public function setSettingValue(?string $setting_value): void { $this->setting_value = $setting_value; }

    public function toLogArray(): array {
        return [
            'setting_id' => $this->getSettingId(),
            'provider_id' => $this->getProviderId(),
            'setting_key' => $this->getSettingKey(),
            'setting_value' => $this->getSettingValue(),
        ];
    }
}
