<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\UserRepository;
use Vanderbilt\REDCap\Classes\Utility\TypeConverter;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: "redcap_user_information")]
class UserEntity
{
    #[ORM\Id]
    #[ORM\Column(name: "ui_id", type: "integer")]
    #[ORM\GeneratedValue]
    private int $ui_id;

    #[ORM\Column(name: "username", type: "string", length: 191, unique: true, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(name: "user_email", type: "string", length: 255, nullable: true)]
    private ?string $user_email = null;

    #[ORM\Column(name: "user_firstname", type: "string", length: 255, nullable: true)]
    private ?string $user_firstname = null;

    #[ORM\Column(name: "user_lastname", type: "string", length: 255, nullable: true)]
    private ?string $user_lastname = null;

    #[ORM\Column(name: "super_user", type: "boolean", options: ["default" => false])]
    private bool $super_user = false;

    #[ORM\Column(name: "account_manager", type: "boolean", options: ["default" => false])]
    private bool $account_manager = false;

    #[ORM\Column(name: "user_creation", type: "datetime", nullable: true)]
    private ?DateTime $user_creation = null;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: UserPermissionEntity::class)]
    private Collection $userPermissions;

    public function __construct() { $this->userPermissions = new ArrayCollection(); }

    public function getUiId(): ?int { return $this->ui_id; }
    public function getId(): ?int { return $this->ui_id; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $username): void { $this->username = $username; }

    public function getUserEmail(): ?string { return $this->user_email; }
    public function setUserEmail(?string $user_email): void { $this->user_email = $user_email; }

    public function getUserFirstname(): ?string { return $this->user_firstname; }
    public function setUserFirstname(?string $user_firstname): void { $this->user_firstname = $user_firstname; }

    public function getUserLastname(): ?string { return $this->user_lastname; }
    public function setUserLastname(?string $user_lastname): void { $this->user_lastname = $user_lastname; }

    public function getSuperUser(): ?bool { return $this->super_user; }
    public function setSuperUser(?bool $super_user): void { $this->super_user = $super_user; }

    public function getAccountManager(): ?bool { return $this->account_manager; }
    public function setAccountManager(?bool $account_manager): void { $this->account_manager = $account_manager; }

    public function getUserCreation(): ?DateTime { return $this->user_creation; }
    public function setUserCreation(DateTime|string|null $user_creation): void { $this->user_creation = TypeConverter::toDateTime($user_creation); }

    /** RELATIONSHIPS */

    public function getUserPermissions(): Collection { return $this->userPermissions; }
}
