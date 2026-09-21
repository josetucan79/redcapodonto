<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;
/**
 * Class FitnessCheck
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/fitnessCheckTaskWithIdentifier:intendedUseDescription:walkDuration:restDuration:options:
 */
class FitnessCheck
{
    // See ORKFitnessStep->validateParemeters()
    const ORKFITNESSSTEPMINIMUMDURATION = 5.0;

    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var double */
    public $walkDuration = 20;

    /** @var double */
    public $restDuration = 20;

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'pedometer',
                            'field_label' => 'Pedometer',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_FIT_WALK_PEDOMETER);

        $fieldArr[] = array('field_name' => 'walkacc',
                            'field_label' => 'Walk Accelerometer',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_FIT_WALK_ACCELEROMETER);

        $fieldArr[] = array('field_name' => 'walkdevice',
                            'field_label' => 'Walk Device Motion',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_FIT_WALK_DEVICEMOTION);


        $fieldArr[] = array('field_name' => 'walkloc',
                            'field_label' => 'Walk Location',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_FIT_WALK_LOCATION);

        $fieldArr[] = array('field_name' => 'restacc',
                            'field_label' => 'Rest Accelerometer',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_FIT_REST_ACCELEROMETER);

        $fieldArr[] = array('field_name' => 'restdevice',
                            'field_label' => 'Rest Device Motion',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_FIT_REST_DEVICEMOTION);
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
        if (isset($data['walkDuration'])) {
            $this->walkDuration = doubleval($data['walkDuration']);
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
    public static function validateExtendedConfigParams($data = array())
    {
        global $lang;
        $errors = array();
        if (!is_numeric($data['walkDuration'])) {
            $errors[] = $lang['mycap_mobile_app_212']." ".$lang['data_import_tool_85'];
        }
        if (!is_numeric($data['restDuration'])) {
            $errors[] = $lang['mycap_mobile_app_205']." ".$lang['data_import_tool_85'];
        }

        $restDuration = doubleval($data['restDuration']);

        if ($restDuration < self::ORKFITNESSSTEPMINIMUMDURATION) {
            $errors[] = $lang['mycap_mobile_app_205']." ".$lang['mycap_mobile_app_208']." " . self::ORKFITNESSSTEPMINIMUMDURATION . " ".$lang['control_center_4469'];
        }
        return $errors;
    }
}
