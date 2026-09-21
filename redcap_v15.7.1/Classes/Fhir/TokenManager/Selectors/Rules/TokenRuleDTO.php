<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules;

use DateTime;
use Vanderbilt\REDCap\Classes\DTOs\DTO;
use Vanderbilt\REDCap\Classes\Utility\TypeConverter;

class TokenRuleDTO extends DTO
{
    /**
     *
     * @var int
     */
    public $id;
    
    /**
     *
     * @var string
     */
    public $project_id;
    
    /**
     *
     * @var string
     */
    public $user_id; // Nullable for "Allow All" or "Disallow All" rules
    
    /**
     *
     * @var int
     */
    public $priority;
    
    /**
     *
     * @var boolean
     */
    public $allow;
    
    /**
     *
     * @var DateTime
     */
    public $created_at;
    
    /**
     *
     * @var DateTime
     */
    public $updated_at;

    /** fields from users table */
    
    /**
     *
     * @var string
     */
    public $username;
    
    /**
     *
     * @var string
     */
    public $user_email;
    
    /**
     *
     * @var string
     */
    public $user_firstname;
    
    /**
     *
     * @var string
     */
    public $user_lastname;

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getProjectId(): int { return $this->project_id; }
    public function getUserId(): ?int { return $this->user_id; }
    public function getPriority(): ?int { return $this->priority; }
    public function isAllowed(): bool { return $this->allow; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
    public function getUsername() {return $this->username; }
    public function getUserEmail() {return $this->user_email; }
    public function getUserFirstname() {return $this->user_firstname; }
    public function getUserLastname() {return $this->user_lastname; }

    public function setId(?int $id): void { $this->id = $id; }
    public function setProjectId(int $projectId): void { $this->project_id = $projectId; }
    public function setUserId(?int $userId): void { $this->user_id = $userId; }
    public function setPriority(?int $priority): void { $this->priority = TypeConverter::toInt($priority); }
    public function setAllow(bool $allow): void { $this->allow = TypeConverter::toBoolean($allow); }
    public function setCreatedAt(?string $createdAt): void { $this->created_at = TypeConverter::toDateTime($createdAt); }
    public function setUpdatedAt(?string $updatedAt): void { $this->updated_at = TypeConverter::toDateTime($updatedAt); }
    public function setUsername(?string $value) {$this->username = $value; }
    public function setUserEmail(?string $value) {$this->user_email = $value; }
    public function setUserFirstname(?string $value) {$this->user_firstname = $value; }
    public function setUserLastname(?string $value) {$this->user_lastname = $value; }
}