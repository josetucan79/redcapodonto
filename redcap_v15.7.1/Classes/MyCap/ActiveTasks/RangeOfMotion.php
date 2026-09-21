<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;
/**
 * Class RangeOfMotion
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTask
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/kneeRangeOfMotionTaskWithIdentifier:limbOption:intendedUseDescription:options:
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/shoulderRangeOfMotionTaskWithIdentifier:limbOption:intendedUseDescription:options:
 */
class RangeOfMotion
{
    const BODY_PART_KNEE = '.Knee';
    const BODY_PART_SHOULDER = '.Shoulder';
    const LIMB_OPTION_LEFT = '.Left';
    const LIMB_OPTION_RIGHT = '.Right';
    // intentionally excluding "unspecified" and "both" because it does not seem to do anything in ResearchKit

    /** @var string */
    public $bodyPart = self::BODY_PART_KNEE;

    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var string */
    public $limbOption = self::LIMB_OPTION_LEFT;

    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'flexion',
                            'field_label' => 'Flexion',
                            'field_type' => 'text',
                            'val_type' => 'float',
                            'field_annotation' => Annotation::TASK_ACTIVE_RMO_FLEXION);

        $fieldArr[] = array('field_name' => 'extension',
                            'field_label' => 'Extension',
                            'field_type' => 'text',
                            'val_type' => 'float',
                            'field_annotation' => Annotation::TASK_ACTIVE_RMO_EXTENSION);

        $fieldArr[] = array('field_name' => 'devicemotion',
                            'field_label' => 'Device Motion',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_RMO_DEVICEMOTION);
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
        if (isset($data['bodyPart'])) {
            $this->bodyPart = $data['bodyPart'];
        }
        if (isset($data['limbOption'])) {
            $this->limbOption = $data['limbOption'];
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
        if (!in_array($data['bodyPart'], array(self::BODY_PART_KNEE, self::BODY_PART_SHOULDER))) {
            $errors[] = $lang['mycap_mobile_app_336'];
        }

        if (!in_array($data['limbOption'], array(self::LIMB_OPTION_LEFT, self::LIMB_OPTION_RIGHT))) {
            $errors[] = $lang['mycap_mobile_app_337'];
        }
        return $errors;
    }
}
