<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Entities;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Vanderbilt\REDCap\Classes\Utility\TypeConverter;

#[ORM\Entity]
#[ORM\Table(name: 'redcap_rewards_logs')]
class LogEntity
{
    const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    #[ORM\Id]
    #[ORM\Column(name: 'log_id', type: 'integer')]
    #[ORM\GeneratedValue]
    private int $log_id;

    #[ORM\Column(name: 'table_name', type: 'string', length: 255)]
    private string $table_name;

    #[ORM\Column(name: 'action', type: 'string', length: 255)]
    private string $action;

    #[ORM\Column(name: 'payload', type: 'text', nullable: true)]
    private ?string $payload = null;

    #[ORM\Column(name: 'username', type: 'string', length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(name: 'project_id', type: 'integer', nullable: true)]
    private ?int $project_id = null;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    private ?DateTime $created_at = null;

    // --- Getters and Setters ---

    public function getLogId(): ?int { return $this->log_id; }
    public function getId(): ?int { return $this->log_id; }

    public function getTableName(): ?string { return $this->table_name; }
    public function setTableName(?string $value): void { $this->table_name = $value; }

    public function getAction(): ?string { return $this->action; }
    public function setAction(?string $value): void { $this->action = $value; }

    public function getPayload(): ?string { return $this->payload; }
    public function setPayload(?string $value): void { $this->payload = $value; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(?string $value): void{ $this->username = $value; }
    
    public function getProjectId(): ?int { return $this->project_id; }
    public function setProjectId(?int $project_id): void { $this->project_id = $project_id; }

    public function getCreatedAt(): ?DateTime { return $this->created_at; }
    public function setCreatedAt(DateTime|string|null $value): void { $this->created_at = TypeConverter::toDateTime($value); }
}
