<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class AmslerGrid
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/amslerGridTaskWithIdentifier:intendedUseDescription:options:
 */
class AmslerGrid
{
    /** @var string (optional) */
    public $intendedUseDescription = '';

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'left_image',
                            'field_label' => 'Left Eye Image',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_AMS_LEFT_IMAGE);
        $fieldArr[] = array('field_name' => 'left_json',
                            'field_label' => 'Left Eye JSON',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_AMS_LEFT_JSON);
        $fieldArr[] = array('field_name' => 'right_image',
                            'field_label' => 'Right Eye Image',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_AMS_RIGHT_IMAGE);
        $fieldArr[] = array('field_name' => 'right_json',
                            'field_label' => 'Right Eye JSON',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_AMS_RIGHT_JSON);
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
    }

    /**
     * Validate extended config variables and returns list of errors
     *
     * @return array
     */
    public static function validateExtendedConfigParams($data = array()) {
        return array();
    }
}
