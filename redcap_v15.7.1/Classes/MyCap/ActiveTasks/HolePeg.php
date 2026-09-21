<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class HolePeg
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/holePegTestTaskWithIdentifier:intendedUseDescription:dominantHand:numberOfPegs:threshold:rotated:timeLimit:options:
 */
class HolePeg
{
    const HAND_LEFT = '.Left';
    const HAND_RIGHT = '.Right';

    const ORKHOLEPEGTESTMINIMUMNUMBEROFPEGS = 1;
    const ORKHOLEPEGTESTMINIMUMTHRESHOLD = 0.0;
    const ORKHOLEPEGTESTMAXIMUMTHRESHOLD = 1.0;
    const ORKHOLEPEGTESTMINIMUMDURATION = 1.0;

    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var string */
    public $dominantHand = self::HAND_LEFT;

    /** @var int  */
    public $numberOfPegs = 9;

    /** @var double */
    public $threshold = 0.2;

    /** @var bool */
    public $rotated = false;

    /** @var int in seconds */
    public $timeLimit = 300;

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'dom_place',
                            'field_label' => 'Dominant Hand Place JSON',
                            'field_type' => 'textarea',
                            'field_annotation' => Annotation::TASK_ACTIVE_HOL_DOMINANT_PLACE);
        $fieldArr[] = array('field_name' => 'dom_remove',
                            'field_label' => 'Dominant Hand Remove JSON',
                            'field_type' => 'textarea',
                            'field_annotation' => Annotation::TASK_ACTIVE_HOL_DOMINANT_REMOVE);
        $fieldArr[] = array('field_name' => 'nondom_place',
                            'field_label' => 'Non-dominant Hand Place JSON',
                            'field_type' => 'textarea',
                            'field_annotation' => Annotation::TASK_ACTIVE_HOL_NONDOMINANT_PLACE);
        $fieldArr[] = array('field_name' => 'nondom_remove',
                            'field_label' => 'Non-dominant Hand Remove JSON',
                            'field_type' => 'textarea',
                            'field_annotation' => Annotation::TASK_ACTIVE_HOL_NONDOMINANT_REMOVE);
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
        if (isset($data['dominantHand'])) {
            $this->dominantHand = $data['dominantHand'];
        }
        if (isset($data['numberOfPegs'])) {
            $this->numberOfPegs = intval($data['numberOfPegs']);
        }
        if (isset($data['threshold'])) {
            $this->threshold = doubleval($data['threshold']);
        }
        if (isset($data['timeLimit'])) {
            $this->timeLimit = doubleval($data['timeLimit']);
        }
        if (isset($data['rotated'])) {
            $this->rotated = boolval($data['rotated']);
        }
    }

    /**
     * Validate extended config variables and returns list of errors
     *
     * @return array
     */
    public static function validateExtendedConfigParams($data = array())
    {
        global $lang;
        $errors = array();

        if (!in_array($data['dominantHand'], [self::HAND_LEFT, self::HAND_RIGHT])) {
            $errors[] = $lang['mycap_mobile_app_346'];
        }

        if ($data['numberOfPegs'] <= self::ORKHOLEPEGTESTMINIMUMNUMBEROFPEGS) {
            $errors[] = $lang['mycap_mobile_app_306']." ".$lang['mycap_mobile_app_267']." ".self::ORKHOLEPEGTESTMINIMUMNUMBEROFPEGS;
        }

        if ($data['threshold'] <= self::ORKHOLEPEGTESTMINIMUMTHRESHOLD) {
            $errors[] = $lang['mycap_mobile_app_308']." ".$lang['mycap_mobile_app_267']." ".self::ORKHOLEPEGTESTMINIMUMTHRESHOLD;
        }

        if ($data['threshold'] >= self::ORKHOLEPEGTESTMAXIMUMTHRESHOLD) {
            $errors[] = $lang['mycap_mobile_app_309']." ".self::ORKHOLEPEGTESTMAXIMUMTHRESHOLD;
        }

        if ($data['timeLimit'] <= self::ORKHOLEPEGTESTMINIMUMDURATION) {
            $errors[] = $lang['survey_1106']." ".$lang['mycap_mobile_app_267']." ".self::ORKHOLEPEGTESTMINIMUMDURATION;
        }

        return $errors;
    }
}
