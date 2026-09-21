<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class TimedWalk
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/timedWalkTaskWithIdentifier:intendedUseDescription:distanceInMeters:timeLimit:includeAssistiveDeviceForm:options:
 */
class TimedWalk
{
    // See ORKTimedWalkStep->validateParemeters()
    const ORKTIMEDWALKMINIMUMDISTANCEINMETERS = 1.0;
    const ORKTIMEDWALKMAXIMUMDISTANCEINMETERS = 10000.0;
    const ORKTIMEDWALKMINIMUMDURATION = 1.0;

    /** var string (optional) */
    public $intendedUseDescription = '';

    /** var double */
    public $distanceInMeters = 100.0;

    /** var double */
    public $timeLimit = 180;

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'trial1',
                            'field_label' => 'Trial 1 Distance',
                            'field_type' => 'text',
                            'field_annotation' => Annotation::TASK_ACTIVE_TIM_TRIAL1);

        $fieldArr[] = array('field_name' => 'turnaround',
                            'field_label' => 'Turn Around Distance',
                            'field_type' => 'text',
                            'field_annotation' => Annotation::TASK_ACTIVE_TIM_TURNAROUND);

        $fieldArr[] = array('field_name' => 'trial2',
                            'field_label' => 'Trial 2 Distance',
                            'field_type' => 'text',
                            'field_annotation' => Annotation::TASK_ACTIVE_TIM_TRIAL2);

        return $fieldArr;
    }

    /**
     * Assign array values to class variables to save extended config variables
     *
     * @return void
     */
    public function buildExtendedConfig($data = array())
    {
        if (isset($data['intendedUseDescription'])) {
            $this->intendedUseDescription = $data['intendedUseDescription'];
        }
        if (isset($data['distanceInMeters'])) {
            $this->distanceInMeters = doubleval($data['distanceInMeters']);
        }
        if (isset($data['timeLimit'])) {
            $this->timeLimit = doubleval($data['timeLimit']);
        }
    }

    /**
     * Validate extended config variables and returns list of errors
     *
     * @return array
     */
    public static function validateExtendedConfigParams($data = array()) {
        global $lang;
        $errors = array();
        if (!is_numeric($data['distanceInMeters'])) {
            $errors[] = $lang['mycap_mobile_app_216']." ".$lang['data_import_tool_85'];
        }

        if (!is_numeric($data['timeLimit'])) {
            $errors[] = $lang['survey_1106']." ".$lang['data_import_tool_85'];
        }

        $distanceInMeters = doubleval($data['distanceInMeters']);
        $timeLimit = doubleval($data['timeLimit']);

        if ($distanceInMeters < self::ORKTIMEDWALKMINIMUMDISTANCEINMETERS ||
            $distanceInMeters > self::ORKTIMEDWALKMAXIMUMDISTANCEINMETERS) {
            $errors[] = $lang['mycap_mobile_app_217']." ". self::ORKTIMEDWALKMINIMUMDISTANCEINMETERS." ".$lang['mycap_mobile_app_218']." ".self::ORKTIMEDWALKMAXIMUMDISTANCEINMETERS." ".$lang['mycap_mobile_app_219'];
        }

        if ($timeLimit < self::ORKTIMEDWALKMINIMUMDURATION) {
            $errors[] = 'Time limit cannot be shorter than ' . self::ORKTIMEDWALKMINIMUMDURATION . ' seconds';
        }
        return $errors;
    }
}
