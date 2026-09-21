<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class Stroop
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/stroopTaskWithIdentifier:intendedUseDescription:numberOfAttempts:options:
 */
class Stroop
{
    // See ORKStroopStep->validateParemeters()
    const ORKSTROOPMINIMUMATTEMPTS = 10;

    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var int */
    public $numberOfAttempts = 10;

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
                            'field_annotation' => Annotation::TASK_ACTIVE_STR);
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
        if (isset($data['numberOfAttempts'])) {
            $this->numberOfAttempts = intval($data['numberOfAttempts']);
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

        $numberOfAttempts = $data['numberOfAttempts'];
        if (!is_numeric($numberOfAttempts) || $numberOfAttempts != (int) $numberOfAttempts) {
            $errors[] = $lang['mycap_mobile_app_238']." ".$lang['data_import_tool_85'];
        }

        if ($data['numberOfAttempts'] < self::ORKSTROOPMINIMUMATTEMPTS) {
            $errors[] = $lang['mycap_mobile_app_239']. " " . self::ORKSTROOPMINIMUMATTEMPTS;
        }

        return $errors;
    }
}
