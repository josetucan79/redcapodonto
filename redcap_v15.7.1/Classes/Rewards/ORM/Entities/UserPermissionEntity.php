<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use Doctrine\ORM\Mapping as ORM;
use Vanderbilt\REDCap\Classes\ORM\Utils\SafeEntityAccessor;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;

#[ORM\Entity]
#[ORM\Table(name: 'redcap_rewards_user_permissions')]
class UserPermissionEntity implements LoggableEntityInterface
{

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $project_id;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: UserEntity::class, inversedBy: "userPermissions")]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "ui_id", onDelete: "CASCADE")]
    private UserEntity $user;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: PermissionEntity::class)]
    #[ORM\JoinColumn(name: 'permission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?PermissionEntity $permission = null;

    // Optional: Add user and project relations later if needed
    // #[ORM\ManyToOne(...)] for user_id → redcap_user_information
    // #[ORM\ManyToOne(...)] for project_id → redcap_projects

    public function getUser(): ?UserEntity { return $this->user; }
    public function setUser(?UserEntity $user): void { $this->user = $user; }
    public function getUserId(): ?int { return SafeEntityAccessor::get($this->user, fn(UserEntity $user): int => $user->getUiId()); }

    public function getProjectId(): ?int { return $this->project_id; }
    public function setProjectId(?int $project_id): void { $this->project_id = $project_id; }

    public function getPermission(): ?PermissionEntity { return $this->permission; }
    public function setPermission(?PermissionEntity $permission): void { $this->permission = $permission; }
    public function getPermissionId(): ?int { return SafeEntityAccessor::get($this->permission, fn(PermissionEntity $permission): int => $permission->getId()); }


    public function toLogArray(): array {
        return [
            'user_id' => $this->getUserId(),
            'project_id' => $this->getProjectId(),
            'permission_id' => $this->getPermissionId(),
        ];
    }

}
