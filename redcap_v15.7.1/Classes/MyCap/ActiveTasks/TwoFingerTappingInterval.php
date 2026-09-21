<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;


use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class TwoFingerTappingInterval
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/twoFingerTappingIntervalTaskWithIdentifier:intendedUseDescription:duration:handOptions:options:
 */
class TwoFingerTappingInterval
{
    // See ORKTappingIntervalStep->validateParemeters()
    const ORKTWOFINGERTAPPINGMINIMUMDURATION = 5.0;
    const HANDOPTIONS_BOTH = '.Both';
    const HANDOPTIONS_LEFT = '.Left';
    const HANDOPTIONS_RIGHT = '.Right';

    /** var string */
    public $intendedUseDescription = '';

    /** var double */
    public $duration = 20;

    /** @var string */
    public $handOptions = self::HANDOPTIONS_BOTH;

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'leftjson',
                            'field_label' => 'Left Hand JSON',
                            'field_type' => 'textarea',
                            'field_annotation' => Annotation::TASK_ACTIVE_TWO_LEFT);

        $fieldArr[] = array('field_name' => 'leftaccelerometer',
                            'field_label' => 'Left Hand Accelerometer',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_TWO_LEFT_ACCELEROMETER);

        $fieldArr[] = array('field_name' => 'rightjson',
                            'field_label' => 'Right Hand JSON',
                            'field_type' => 'textarea',
                            'field_annotation' => Annotation::TASK_ACTIVE_TWO_RIGHT);

        $fieldArr[] = array('field_name' => 'rightaccelerometer',
                            'field_label' => 'Right Hand Accelerometer',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_TWO_RIGHT_ACCELEROMETER);
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
        if (isset($data['duration'])) {
            $this->duration = doubleval($data['duration']);
        }
        if (isset($data['handOptions'])) {
            $this->handOptions = $data['handOptions'];
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
        if (!is_numeric($data['duration'])) {
            $errors[] = $lang['mycap_mobile_app_207']." ".$lang['data_import_tool_85'];
        }

        $duration = doubleval($data['duration']);

        if ($duration < self::ORKTWOFINGERTAPPINGMINIMUMDURATION) {
            $errors[] = $lang['mycap_mobile_app_207'].' '.$lang['mycap_mobile_app_208'].' '. self::ORKTWOFINGERTAPPINGMINIMUMDURATION . ' '.$lang['control_center_4469'];
        }

        if (isset($data['handOptions'])) {
            if (!in_array($data['handOptions'], [
                self::HANDOPTIONS_BOTH,
                self::HANDOPTIONS_LEFT,
                self::HANDOPTIONS_RIGHT
            ])) {
                $errors[] = $lang['mycap_mobile_app_339'].' ' . $data['handOptions'];
            }
        } else {
            $errors[] = $lang['mycap_mobile_app_340'];
        }
        return $errors;
    }
}
