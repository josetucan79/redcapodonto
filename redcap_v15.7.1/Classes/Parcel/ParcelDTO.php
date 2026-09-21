<?php
namespace Vanderbilt\REDCap\Classes\Parcel;

use DateTime;
use DateInterval;
use DateTimeZone;
use Vanderbilt\REDCap\Classes\DTOs\DTO;

class ParcelDTO extends DTO
{
    const MAX_SUMMARY_LENGTH = 50;
    /**
     *
     * @var string
     */
    public $id;

    /**
     *
     * @var string
     */
    public $to;

    /**
     *
     * @var string
     */
    public $from;

    /**
     *
     * @var string
     */
    public $subject;

    /**
     *
     * @var string
     */
    public $body;

    /**
     *
     * @var string
     */
    public $lifespan;

    /**
     *
     * @var boolean
     */
    public $read = false;

    /**
     *
     * @var string
     */
    public $createdAt;

    public function __construct($data=[]) {
        parent::__construct($data);
        $this->createdAt = new DateTime();
    }


    /**
     * check expiration
     *
     * @return boolean
     */
    public function isExpired() {
        $expiration = $this->expiration();
        if($expiration===false) return false;
        $now = new DateTime();
        $expired = $now > $expiration;
        return $expired;
    }

    public function expiration() {
        $lifespan = $this->lifespan;
        if(empty($lifespan)) return false;
        if(!($lifespan instanceof DateInterval)) {
            $lifespan = DateInterval::createFromDateString($lifespan);
        }
        $createdAt = $this->createdAt;
        if(empty($createdAt) || !($createdAt instanceof DateTime)) return false; // no valid creation date, cannot calc the expiration
        $expiration = clone $createdAt;
        $expiration->add($lifespan);
        return $expiration;
    }

    public function getURL() {
        $baseURL = PostMaster::getBaseURL().PostMaster::CONTROLLER_ACTION_SHOW;
        $queryParams = http_build_query(['id'=>$this->id]);
        $url = $baseURL."&$queryParams";
        return $url;
    }

    public function summary() {
        $body = strip_tags($this->body);
        return strlen($body) > self::MAX_SUMMARY_LENGTH ? substr($body,0,50)."..." : $body;
    }

    public function jsonSerialize(): array
    {
        $formatDate = function($date) {
            if(!($this->createdAt instanceof DateTime)) return $date;
            return $date->format('Y-m-d H:i:s');
        };
        $data = parent::jsonSerialize();
        $data['summary'] = $this->summary();
        $data['createdAt'] = $formatDate($this->createdAt);
        $data['expiration'] = $formatDate($this->expiration());
        return $data;
    }

    /**
     * compare two parcels
     * @param ParcelDTO $a
     * @param ParcelDTO $b
     * @return int comparison result:
     *  -1 first less than the second
     *  0 first equal to the second
     *  1 first greater than the second
     */
    public static function compareByCreationDate($a, $b) {
        $dateA = $a->createdAt;
        $dateB = $b->createdAt;
        if(!($dateA) instanceof DateTime) return -1;
        if(!($dateB) instanceof DateTime) return 1;
        if ($dateA == $dateB) return 0;
        return ($dateA < $dateB) ? -1 : 1;
    }
}