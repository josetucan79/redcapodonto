<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Resources;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\REDCapRecordDTO;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\BaseProviderProjectSettingsEntity;

class REDCapRecordResource extends BaseResource {
    
    /**
     *
     * @var int
     */
    protected $project_id;

    /**
     *
     * @var int
     */
    protected $arm_number;

    /**
     *
     * @var REDCapRecordDTO
     */
    protected $record;
    
    /**
     *
     * @var RewardOptionEntity[]
     */
    protected $rewardOptions;

    /**
     *
     * @param REDCapRecordDTO $record
     * @param integer $project_id
     * @param integer $arm_number
     * @param BaseProviderProjectSettingsEntity $projectSettings
     * @param RewardOptionEntity[] $rewardOptions
     */
    public function __construct($record, int $project_id, int $arm_number, $rewardOptions) {
        $this->project_id = $project_id;
        $this->arm_number = $arm_number;
        $this->record = $record;
        $this->rewardOptions = $rewardOptions;
    }

    public function getProjectId() { return $this->project_id; }
    public function getArmNumber() { return $this->arm_number; }
    public function getRecord() { return $this->record; }
    public function getRewardOptions() { return $this->rewardOptions; }

    /**
     * Transforms the entity into an associative array.
     *
     * @return array The array representation of the entity.
     */
    public function toArray() {
        $record = $this->record;
        $record_id = $record->getRecordId();
        
        return [
            'project_id' => $this->project_id,
            'arm_number' => $this->arm_number,
            'record_id' => $record_id,
            'data' => $record->getRecordData(),
            'link' => $record->getRecordLink(),
            'preview' => $record->getPreview(),
            'participant_details' => $record->getParticipantDetails(),
            'reward_options' => $this->rewardOptions,
        ];
    }
}