<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager;

use DateTime;
use Vanderbilt\REDCap\Classes\DTOs\DTO;
use Vanderbilt\REDCap\Classes\Utility\TypeConverter;

class FhirTokenDTO extends DTO
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    const STATUS_VALID = 'valid';
    const STATUS_EXPIRED = 'expired';
    const STATUS_FORBIDDEN = 'forbidden';
    const STATUS_INVALID = 'invalid';
    const STATUS_REVOKED = 'revoked';
    const STATUS_PENDING = 'pending';
    const STATUS_UNKNOWN = 'unknown';

    private $patient;
    private $mrn;
    private $token_owner;
    private $expiration;
    private $access_token;
    private $refresh_token;
    private $ehr_id;
    private $status;

    // Getters
    public function getPatient() { return $this->patient; }
    public function getMrn() { return $this->mrn; }
    public function getTokenOwner() { return $this->token_owner; }
    public function getExpiration(): DateTime { return $this->expiration; }
    public function getAccessToken() { return $this->access_token; }
    public function getRefreshToken() { return $this->refresh_token; }
    public function getEhrId() { return $this->ehr_id; }
    public function getStatus() {
        if(!$this->status) {
            if (empty($this->access_token)) {
                $this->status = self::STATUS_INVALID;
            } elseif(!$this->expiration instanceof DateTime) {
                $this->status = self::STATUS_VALID;
            } else {
                $currentDate = new DateTime('now');
                if($this->expiration <= $currentDate) { // Also fix the comparison here
                    $this->status = self::STATUS_EXPIRED;
                } else {
                    $this->status = self::STATUS_VALID;
                }
            }
        }
        return $this->status;
    }


    public function getExpirationString(): ?string 
    {
        return $this->expiration ? $this->expiration->format(self::DATE_FORMAT) : null;
    }


    public function isValid(): bool
    {
        if (empty($this->access_token)) return false;
        if (empty($this->expiration)) return true;

        $now = new DateTime();
        // $expirationTime = strtotime($this->expiration);
        return $this->expiration > $now;
    }

    public function isExpired(): bool{ return !$this->isValid(); }

    public function setPatient($value): void { $this->patient = $value; }
    public function setMrn($value): void { $this->mrn = $value; }
    public function setTokenOwner($value): void { $this->token_owner = $value; }
    public function setExpiration($value): void {
        $this->expiration = TypeConverter::toDateTime($value);
        $this->updateStatus();
    }
    public function setExpirationFromSeconds(int $seconds): void { $this->setExpiration(self::calcExpirationDate($seconds)); }
    public function setAccessToken($value): void {
        $this->access_token = $value;
        $this->updateStatus();
    }
    public function setRefreshToken($value): void { $this->refresh_token = $value; }
    public function setEhrId($value): void { $this->ehr_id = $value; }
    public function setStatus($value): void { $this->status = $value; }

    /**
     * Updates the resource type if both fhir_system and request are available.
     */
    private function updateStatus(): void
    {
        if (empty($this->access_token)) {
            // no access token: invalid
            $this->status = self::STATUS_INVALID;
            return;
        }
        if(!$this->expiration instanceof DateTime) {
            // no expiration: valid
            $this->status = self::STATUS_VALID;
            return;
        }
        $currentDate = new DateTime('now');
        if($this->expiration <= $currentDate) {
            // expired
            $this->status = self::STATUS_EXPIRED;
            return;
        }

        $this->status = self::STATUS_VALID;
    }

    /**
     * calculate the expiration date using a timespan
     *
     * @param [type] $timespan
     * @return string
     */
    private static function calcExpirationDate($timespan)
    {
        $now = new \DateTime();
        $date_interval = new \DateInterval("PT{$timespan}S");
        $now->add($date_interval);

        return $now->format(self::DATE_FORMAT);
    }

    public function __toString() {
        return $this->access_token ?? '';
    }

}