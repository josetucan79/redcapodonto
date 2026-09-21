<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass\DTOs;

use DateInterval;
use DateTime;
use Vanderbilt\REDCap\Classes\DTOs\DTO;

class ProtectedPatientDTO extends DTO
{

    const TTL = '10 minutes';
    
    /**
     *
     * @var DateTime
     */
    public $timestamp;

    /**
     *
     * @var string
     */
    public $mrn;

    /**
     *
     * @var string
     */
    public $fhirBtgToken;

    public function isExpired() {
        $now = new DateTime();
        $lifespanInterval = DateInterval::createFromDateString(self::TTL);
        $expirationDate = clone $this->timestamp;
        $expirationDate->add($lifespanInterval);
        return $now > $expirationDate;
    }

    public function getData()
    {
        $data = parent::getData();
        $data['isExpired'] = $this->isExpired();
        return $data;
    }
}
