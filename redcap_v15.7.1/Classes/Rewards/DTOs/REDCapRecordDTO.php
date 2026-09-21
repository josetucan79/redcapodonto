<?php
namespace Vanderbilt\REDCap\Classes\Rewards\DTOs;

use Vanderbilt\REDCap\Classes\Rewards\Utility\ProjectArmFetcher;
use Vanderbilt\REDCap\Classes\Rewards\Utility\SmartVarialblesUtility;

/**
 * Class REDCapRecordDTO
 *
 * Data Transfer Object for REDCap Records with associated reward options and reviews.
 *
 * @package Vanderbilt\REDCap\Classes\Rewards\DTOs
 */
class REDCapRecordDTO {

    private $event_id;


    private $_previewCache;
    private $_participantDetailsCache;

    public function __construct(
        private int $project_id,
        private int $arm_number,
        private string $recordID,
        private array $recordData,
        private string $previewExpression,
        private string $participantDetails
    ) {
        $event_ids = ProjectArmFetcher::getArmEventsIds($project_id, $arm_number);
        $this->event_id = $event_ids[0] ?? null;
    }


    /**
     * Get a preview based on a template expression.
     *
     * @return string
     */
    public function getPreview() {
        if(!$this->_previewCache) {

            $this->_previewCache = SmartVarialblesUtility::replace($this->previewExpression, $this->project_id, $this->getRecordId(), $this->event_id);
        }
        return $this->_previewCache;
    }

    /**
     * Get a preview based on a template expression.
     *
     * @return string
     */
    public function getParticipantDetails() {
        if(!$this->_participantDetailsCache) {

            $this->_participantDetailsCache = SmartVarialblesUtility::replace($this->participantDetails, $this->project_id, $this->getRecordId(), $this->event_id);
        }
        return $this->_participantDetailsCache;
    }

    /**
     * Get the record ID.
     *
     * @return string|int
     */
    public function getRecordId() { return $this->recordID; }

    /**
     * Get the record data.
     *
     * @return array
     */
    public function getRecordData() { return $this->recordData; }


    /**
     * Get the data in a specific event.
     * Defaults to the first event if no event_id is provided.
     *
     * @param int|null $event_id
     * @return array
     */
    public function getEventData($event_id = null) {
        $data = $this->getRecordData();
        $result = [];
    
        // If event_id is null, use the first event_id
        if (is_null($event_id)) {
            reset($data);
            $event_id = key($data);
        }
    
        if (isset($data[$event_id])) {
            $result = $data[$event_id];
        }
    
        return $result;
    }
    
    /**
     * Get the link to the record.
     *
     * @return string
     */
    public function getRecordLink() {
        $record_id = htmlspecialchars(removeDDEending($this->recordID), ENT_QUOTES);
        $baseURL = trim(APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION, '/');
        return "$baseURL/DataEntry/record_home.php?pid=$this->project_id&id=$record_id";
    }

}
