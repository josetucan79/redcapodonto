<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Commands;

use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;

class AddRevisionCommand extends AbstractCommand {

    /**
     *
     * @var DataMart
     */
    protected $receiver;

    /**
     *
     * @var integer
     */
    private $project_id;

    /**
     *
     * @var integer
     */
    private $user_id;

    protected $criticality = self::CRITICALITY_HIGH;
    protected $action_type = self::ACTION_TYPE_AUTO;
    
    /**
     *
     * @param DataMart $receiver
     * @param string $formName
     * @param string $variableName
     * @param string $text
     */
    public function __construct($receiver, $project_id, $user_id)
    {
        parent::__construct(...func_get_args());
        $this->project_id = $project_id;
        $this->user_id = $user_id;
    }

    public function execute()
    {
        /**
         * create a basic revision
         */
        $revisionSettings = [
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
            'fields' => [
                // 'address-city',
                // 'address-country',
                // 'address-line',
                // 'address-postalCode',
                // 'address-state',
                // 'birthDate',
                // 'deceasedBoolean',
                // 'deceasedDateTime',
                // 'ethnicity',
                // 'gender',
                'id',
                'name-family',
                'name-given',
                // 'phone-home',
                // 'phone-mobile',
                // 'preferred-language',
                // 'problem-list',
                // 'race',
            ],
        ];
        return $this->receiver->addRevision($revisionSettings);
    }

    public function undo()
    {
        print 'undo';
    }

    public function __toString()
    {
        return sprintf("create a DataMart revision");
    }

}