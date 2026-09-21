<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class TowerOfHanoi
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/towerOfHanoiTaskWithIdentifier:intendedUseDescription:numberOfDisks:options:
 */
class TowerOfHanoi
{
    // See ORKTowerOfHanoiStep->validateParemeters()
    const MAXIMUMNUMBEROFDISKS = 8;

    /** var string (optional) */
    public $intendedUseDescription = '';

    /** var int */
    public $numberOfDisks = 4;

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
            'field_annotation' => Annotation::TASK_ACTIVE_TOW);
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
        if (isset($data['numberOfDisks'])) {
            $this->numberOfDisks = $data['numberOfDisks'];
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

        if (!is_numeric($data['numberOfDisks'])) {
            $errors[] = $lang['mycap_mobile_app_260']." ".$lang['data_import_tool_85'];
        }

        $numberOfDisks = intval($data['numberOfDisks']);

        if ($numberOfDisks > self::MAXIMUMNUMBEROFDISKS) {
            $errors[] = $lang['mycap_mobile_app_261']." ". self::MAXIMUMNUMBEROFDISKS;
        }

        return $errors;
    }
}
