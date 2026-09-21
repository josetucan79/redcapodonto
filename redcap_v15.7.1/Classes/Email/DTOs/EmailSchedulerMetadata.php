<?php
namespace Vanderbilt\REDCap\Classes\Email\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;


class  EmailSchedulerMetadata extends DTO
{

    private $sent = 0;
    private $not_sent = 0;
    private $processed_ui_ids = [];
    private $remaining_ui_ids = [];

    public function getSent(): int { return $this->sent; }
    public function getNotSent(): int { return $this->not_sent; }
    public function getProcessedUiIds(): array { return $this->processed_ui_ids; }
    public function getRemainingUiIds(): array { return $this->remaining_ui_ids; }


    public function setSent(int $value): void { $this->sent = $value; }
    public function setNotSent(int $value): void { $this->not_sent = $value; }
    public function setProcessedUiIds(array $value): void { $this->processed_ui_ids = $value; }
    public function setRemainingUiIds(array $value): void { $this->remaining_ui_ids = $value; }


    public function getData()
    {
        $data = [
            'sent'              => $this->getSent(),
            'not_sent'          => $this->getNotSent(),
            'processed_ui_ids'  => $this->getProcessedUiIds(),
            'remaining_ui_ids'  => $this->getRemainingUiIds(),
        ];
        return $data;
    }


}