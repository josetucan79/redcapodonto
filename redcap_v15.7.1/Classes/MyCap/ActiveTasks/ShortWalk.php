<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class ShortWalk
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTask
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/shortWalkTaskWithIdentifier:intendedUseDescription:numberOfStepsPerLeg:restDuration:options:
 */
class ShortWalk
{
    // See ORKWalkingStep->validateParemeters()
    const ORKSHORTWALKTASKMINIMUMNUMBEROFSTEPSPERLEG = 1;

    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var int */
    public $numberOfStepsPerLeg = 20;

    /** @var double */
    public $restDuration = 20.0;

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'outacc',
                            'field_label' => 'Outbound Accelerometer',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_SHO_OUTBOUND_ACCELEROMETER);

        $fieldArr[] = array('field_name' => 'outdevice',
                            'field_label' => 'Outbound Device Motion',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_SHO_OUTBOUND_DEVICEMOTION);

        $fieldArr[] = array('field_name' => 'returnacc',
                            'field_label' => 'Return Accelerometer',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_SHO_RETURN_ACCELEROMETER);


        $fieldArr[] = array('field_name' => 'returndevice',
                            'field_label' => 'Return Device Motion',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_SHO_RETURN_DEVICEMOTION);

        $fieldArr[] = array('field_name' => 'restacc',
                            'field_label' => 'Rest Accelerometer',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_SHO_REST_ACCELEROMETER);

        $fieldArr[] = array('field_name' => 'restdevice',
                            'field_label' => 'Rest Device Motion',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_SHO_REST_DEVICEMOTION);
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
        if (isset($data['numberOfStepsPerLeg'])) {
            $this->numberOfStepsPerLeg = intval($data['numberOfStepsPerLeg']);
        }
        if (isset($data['restDuration'])) {
            $this->restDuration = doubleval($data['restDuration']);
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
        if (!is_numeric($data['numberOfStepsPerLeg'])) {
            $errors[] = $lang['mycap_mobile_app_202']." ".$lang['data_import_tool_85'];
        }
        $numberOfStepsPerLeg = intval($data['numberOfStepsPerLeg']);
        if ($numberOfStepsPerLeg < self::ORKSHORTWALKTASKMINIMUMNUMBEROFSTEPSPERLEG) {
            $errors[] = $lang['mycap_mobile_app_202'].' '.$lang['mycap_mobile_app_208'].' '.self::ORKSHORTWALKTASKMINIMUMNUMBEROFSTEPSPERLEG;
        }
        if (!is_numeric($data['restDuration'])) {
            $errors[] = $lang['mycap_mobile_app_205']." ".$lang['data_import_tool_85'];
        }
        return $errors;
    }
}
