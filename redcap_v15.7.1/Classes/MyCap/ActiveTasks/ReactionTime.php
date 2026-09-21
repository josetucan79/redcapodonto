<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class ReactionTime
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/reactionTimeTaskWithIdentifier:intendedUseDescription:maximumStimulusInterval:minimumStimulusInterval:thresholdAcceleration:numberOfAttempts:timeout:successSound:timeoutSound:failureSound:options:
 */
class ReactionTime
{
    // See ORKReactionTimeStep->validateParemeters()
    const MINIMUMSTIMULUSINTERVAL = 0;
    const MINIMUMTHRESHOLDACCELERATION = 0;
    const MINIMUMTIMEOUT = 0;
    const MINIMUMNUMBEROFATTEMPTS = 0;

    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var double */
    public $maximumStimulusInterval = 10;

    /** @var double */
    public $minimumStimulusInterval = 4;

    /** @var double */
    public $thresholdAcceleration = 0.5;

    /** @var int */
    public $numberOfAttempts = 3;

    /** @var double */
    public $timeout = 3;

    /** @var int */
    public $successSound = 0;

    /** @var int */
    public $timeoutSound = 0;

    /** @var int */
    public $failureSound = 0;

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'json',
                            'field_label' => 'JSON',
                            'field_type' => 'textarea',
                            'field_annotation' => Annotation::TASK_ACTIVE_REA);
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
        if (isset($data['maximumStimulusInterval'])) {
            $this->maximumStimulusInterval = doubleval($data['maximumStimulusInterval']);
        }
        if (isset($data['minimumStimulusInterval'])) {
            $this->minimumStimulusInterval = doubleval($data['minimumStimulusInterval']);
        }
        if (isset($data['thresholdAcceleration'])) {
            $this->thresholdAcceleration = doubleval($data['thresholdAcceleration']);
        }
        if (isset($data['numberOfAttempts'])) {
            $this->numberOfAttempts = intval($data['numberOfAttempts']);
        }
        if (isset($data['timeout'])) {
            $this->timeout = doubleval($data['timeout']);
        }
        if (isset($data['successSound'])) {
            $this->successSound = intval($data['successSound']);
        }
        if (isset($data['timeoutSound'])) {
            $this->timeoutSound = intval($data['timeoutSound']);
        }
        if (isset($data['failureSound'])) {
            $this->failureSound = intval($data['failureSound']);
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

        if (!is_numeric($data['maximumStimulusInterval'])) {
            $errors[] = $lang['mycap_mobile_app_263']." ".$lang['data_import_tool_85'];
        }

        if (!is_numeric($data['minimumStimulusInterval'])) {
            $errors[] = $lang['mycap_mobile_app_266']." ".$lang['data_import_tool_85'];
        }

        if (!is_numeric($data['thresholdAcceleration'])) {
            $errors[] = $lang['mycap_mobile_app_269']." ".$lang['data_import_tool_85'];
        }

        if (!is_numeric($data['numberOfAttempts'])) {
            $errors[] = $lang['mycap_mobile_app_238']." ".$lang['data_import_tool_85'];
        }

        if (!is_numeric($data['timeout'])) {
            $errors[] = $lang['mycap_mobile_app_272']." ".$lang['data_import_tool_85'];
        }

        if ($data['successSound'] != '0') {
            $errors[] = $lang['mycap_mobile_app_275'];
        }
        if ($data['timeoutSound'] != '0') {
            $errors[] = $lang['mycap_mobile_app_278'];
        }
        if ($data['failureSound'] != '0') {
            $errors[] = $lang['mycap_mobile_app_281'];
        }

        $maximumStimulusInterval = doubleval($data['maximumStimulusInterval']);
        $minimumStimulusInterval = doubleval($data['minimumStimulusInterval']);
        $thresholdAcceleration = doubleval($data['thresholdAcceleration']);
        $numberOfAttempts = intval($data['numberOfAttempts']);
        $timeout = doubleval($data['timeout']);

        if ($minimumStimulusInterval <= self::MINIMUMSTIMULUSINTERVAL) {
            $errors[] = $lang['mycap_mobile_app_266']." ".$lang['mycap_mobile_app_267']." ". self::MINIMUMSTIMULUSINTERVAL;
        }
        if ($maximumStimulusInterval < $minimumStimulusInterval) {
            $errors[] = $lang['mycap_mobile_app_264'];
        }
        if ($thresholdAcceleration <= self::MINIMUMTHRESHOLDACCELERATION) {
            $errors[] = $lang['mycap_mobile_app_269']." ".$lang['mycap_mobile_app_267']." ". self::MINIMUMTHRESHOLDACCELERATION;
        }
        if ($timeout <= self::MINIMUMTIMEOUT) {
            $errors[] = $lang['mycap_mobile_app_272']." ".$lang['mycap_mobile_app_267']." ". self::MINIMUMTIMEOUT;
        }
        if ($numberOfAttempts <= self::MINIMUMNUMBEROFATTEMPTS) {
            $errors[] = $lang['mycap_mobile_app_238']." ".$lang['mycap_mobile_app_267']." ". self::MINIMUMNUMBEROFATTEMPTS;
        }

        return $errors;
    }
}
