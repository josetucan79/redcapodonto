<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class TrailMaking
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/trailmakingTaskWithIdentifier:intendedUseDescription:trailmakingInstruction:trailType:options:
 */
class TrailMaking
{
    /// Trail making for Type-A trail where the pattern is 1-2-3-4-5-6-7
    const TYPE_A = '.A';
    /// Trail making for Type-B trail where the pattern is 1-A-2-B-3-C-4-D-5-E-6-F-7
    const TYPE_B = '.B';

    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var string (optional) */
    public $trailMakingInstruction = '';

    /** @var string */
    public $trailType = self::TYPE_A;

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
                            'field_annotation' => Annotation::TASK_ACTIVE_TRA);
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
        if (isset($data['trailMakingInstruction'])) {
            $this->trailMakingInstruction = $data['trailMakingInstruction'];
        }
        if (isset($data['trailType'])) {
            $this->trailType = $data['trailType'];
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

        if (!in_array($data['trailType'], [self::TYPE_A, self::TYPE_B])) {
            $errors[] = $lang['mycap_mobile_app_341'];
        }
        return $errors;
    }
}
