<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class SpatialSpanMemory
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/spatialSpanMemoryTaskWithIdentifier:intendedUseDescription:initialSpan:minimumSpan:maximumSpan:playSpeed:maximumTests:maximumConsecutiveFailures:customTargetImage:customTargetPluralName:requireReversal:options:
 */
class SpatialSpanMemory
{
    // See ORKSpatialSpanMemoryStep->validateParemeters()
    const ORKSPATIALSPANMEMORYTASKMINIMUMINITIALSPAN = 2;
    const ORKSPATIALSPANMEMORYTASKMAXIMUMSPAN = 20;
    const ORKSPATIALSPANMEMORYTASKMINIMUMPLAYSPEED = 0.5;
    const ORKSPATIALSPANMEMORYTASKMAXIMUMPLAYSPEED = 20;
    const ORKSPATIALSPANMEMORYTASKMINIMUMMAXTESTS = 1;
    const ORKSPATIALSPANMEMORYTASKMINIMUMMAXCONSECUTIVEFAILURES = 1;

    /** var string (optional) */
    public $intendedUseDescription = '';

    /** var int */
    public $initialSpan = 3;

    /** var int */
    public $minimumSpan = 2;

    /** var int */
    public $maximumSpan = 15;

    /** var double */
    public $playSpeed = 1.0;

    /** var int */
    public $maxTests = 5;

    /** var int */
    public $maxConsecutiveFailures = 3;

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
                            'field_annotation' => Annotation::TASK_ACTIVE_SPA);
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
        if (isset($data['initialSpan'])) {
            $this->initialSpan = intval($data['initialSpan']);
        }
        if (isset($data['minimumSpan'])) {
            $this->minimumSpan = intval($data['minimumSpan']);
        }
        if (isset($data['maximumSpan'])) {
            $this->maximumSpan = intval($data['maximumSpan']);
        }
        if (isset($data['playSpeed'])) {
            $this->playSpeed = doubleval($data['playSpeed']);
        }
        if (isset($data['maxTests'])) {
            $this->maxTests = intval($data['maxTests']);
        }
        if (isset($data['maxConsecutiveFailures'])) {
            $this->maxConsecutiveFailures = intval($data['maxConsecutiveFailures']);
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

        if (!is_numeric($data['initialSpan'])) {
            $errors[] = $lang['mycap_mobile_app_223']." ".$lang['data_import_tool_85'];
        }
        if (!is_numeric($data['minimumSpan'])) {
            $errors[] = $lang['mycap_mobile_app_227']." ".$lang['data_import_tool_85'];
        }
        if (!is_numeric($data['maximumSpan'])) {
            $errors[] = $lang['mycap_mobile_app_229']." ".$lang['data_import_tool_85'];
        }
        if (!is_numeric($data['playSpeed'])) {
            $errors[] = $lang['mycap_mobile_app_224']." ".$lang['data_import_tool_85'];
        }
        if (!is_numeric($data['maxTests'])) {
            $errors[] = $lang['mycap_mobile_app_232']." ".$lang['data_import_tool_85'];
        }
        if (!is_numeric($data['maxConsecutiveFailures'])) {
            $errors[] = $lang['mycap_mobile_app_234']." ".$lang['data_import_tool_85'];
        }

        $initialSpan = intval($data['initialSpan']);
        $minimumSpan = intval($data['minimumSpan']);
        $maximumSpan = intval($data['maximumSpan']);
        $playSpeed = doubleval($data['playSpeed']);
        $maxTests = intval($data['maxTests']);
        $maxConsecutiveFailures = intval($data['maxConsecutiveFailures']);

        if ($initialSpan < self::ORKSPATIALSPANMEMORYTASKMINIMUMINITIALSPAN) {
            $errors[] = $lang['mycap_mobile_app_223']." ".$lang['mycap_mobile_app_208']." ". self::ORKSPATIALSPANMEMORYTASKMINIMUMINITIALSPAN;
        }
        if ($minimumSpan > $initialSpan) {
            $errors[] = $lang['mycap_mobile_app_223']." ".$lang['mycap_mobile_app_208']." ".$lang['mycap_mobile_app_227'];
        }
        if ($initialSpan > $maximumSpan) {
            $errors[] = $lang['mycap_mobile_app_229']." ".$lang['mycap_mobile_app_208']." ".$lang['mycap_mobile_app_223'];
        }
        if ($maximumSpan > self::ORKSPATIALSPANMEMORYTASKMAXIMUMSPAN) {
            $errors[] = $lang['mycap_mobile_app_214']." ".self::ORKSPATIALSPANMEMORYTASKMAXIMUMSPAN;
        }
        if ($playSpeed < self::ORKSPATIALSPANMEMORYTASKMINIMUMPLAYSPEED) {
            $errors[] = $lang['mycap_mobile_app_224']." ".$lang['mycap_mobile_app_208']." ".self::ORKSPATIALSPANMEMORYTASKMINIMUMPLAYSPEED." ".$lang['control_center_4469'];
        }
        if ($playSpeed > self::ORKSPATIALSPANMEMORYTASKMAXIMUMPLAYSPEED) {
            $errors[] = $lang['mycap_mobile_app_230']." ".self::ORKSPATIALSPANMEMORYTASKMAXIMUMPLAYSPEED." ".$lang['control_center_4469'];
        }
        if ($maxTests < self::ORKSPATIALSPANMEMORYTASKMINIMUMMAXTESTS) {
            $errors[] = $lang['mycap_mobile_app_232']." ".$lang['mycap_mobile_app_208']." ".self::ORKSPATIALSPANMEMORYTASKMINIMUMMAXTESTS;
        }
        if ($maxConsecutiveFailures < self::ORKSPATIALSPANMEMORYTASKMINIMUMMAXCONSECUTIVEFAILURES) {
            $errors[] = $lang['mycap_mobile_app_234']." ".$lang['mycap_mobile_app_208']." ".self::ORKSPATIALSPANMEMORYTASKMINIMUMMAXCONSECUTIVEFAILURES;
        }

        return $errors;
    }
}
