<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\PermissionRepository;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'redcap_rewards_permissions')]
#[ORM\UniqueConstraint(name: 'name', columns: ['name'])]
class PermissionEntity implements LoggableEntityInterface
{

    const REVIEW_ELIGIBILITY = 'review_eligibility';
    const PLACE_ORDERS = 'place_orders';
    const MANAGE_PERMISSIONS = 'manage_permissions';
    const MANAGE_PROJECT_SETTINGS = 'manage_project_settings';
    const VIEW_LOGS = 'view_logs';
    const MANAGE_REWARD_OPTIONS = 'manage_reward_options'; // create, edit, delete reward options
    const MANAGE_API_SETTINGS = 'manage_api_settings'; // client ID, client secrets, etc...
    const VIEW_ORDERS = 'view_orders';

    public static function getTypes() {
        return [
            self::REVIEW_ELIGIBILITY,
            self::PLACE_ORDERS,
            self::MANAGE_PERMISSIONS,
            self::MANAGE_PROJECT_SETTINGS,
            self::VIEW_LOGS,
            self::MANAGE_REWARD_OPTIONS,
            self::MANAGE_API_SETTINGS,
            self::VIEW_ORDERS,
        ];
    }

    #[ORM\OneToMany(mappedBy: 'permission', targetEntity: UserPermissionEntity::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $userPermissions;

    public function __construct() { $this->userPermissions = new ArrayCollection(); }

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    // --- Getters and Setters ---

    public function getId(): ?int{ return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }

    /** RELATIONSHIPS */
    public function getUserPermissions(): Collection { return $this->userPermissions; }

    public function addUserPermission(UserPermissionEntity $userPermission): void
    {
        if (!$this->userPermissions->contains($userPermission)) {
            $this->userPermissions->add($userPermission);
            $userPermission->setPermission($this);
        }
    }

    public function removeUserPermission(UserPermissionEntity $userPermission): void
    {
        if ($this->userPermissions->removeElement($userPermission)) {
            if ($userPermission->getPermission() === $this) {
                $userPermission->setPermission(null);
            }
        }
    }

    public function toLogArray(): array {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
        ];
    }
}
