<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class ToneAudiometry
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/toneAudiometryTaskWithIdentifier:intendedUseDescription:speechInstruction:shortSpeechInstruction:toneDuration:options:
 */
class ToneAudiometry
{
    // See ORKToneAudiometryStep->validateParemeters()
    const ORKTONEAUDIOMETRYTASKTONEMINIMUMDURATION = 5.0;

    /** var string (optional) */
    public $intendedUseDescription = '';

    /** var string (optional) */
    public $speechInstruction = '';

    /** var string (optional) */
    public $shortSpeechInstruction = '';

    /** var double */
    public $toneDuration = 20;

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
                            'field_annotation' => Annotation::TASK_ACTIVE_TON);
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
        if (isset($data['speechInstruction'])) {
            $this->speechInstruction = $data['speechInstruction'];
        }
        if (isset($data['shortSpeechInstruction'])) {
            $this->shortSpeechInstruction = $data['shortSpeechInstruction'];
        }
        if (isset($data['toneDuration'])) {
            $this->toneDuration = doubleval($data['toneDuration']);
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

        if (!is_numeric($data['toneDuration'])) {
            $errors[] = $lang['mycap_mobile_app_302']." ".$lang['data_import_tool_85'];
        }

        $toneDuration = doubleval($data['toneDuration']);

        if ($toneDuration < self::ORKTONEAUDIOMETRYTASKTONEMINIMUMDURATION) {
            $errors[] = $lang['mycap_mobile_app_302']." ".$lang['mycap_mobile_app_208']." ".self::ORKTONEAUDIOMETRYTASKTONEMINIMUMDURATION." ".$lang['control_center_4469'];
        }

        return $errors;
    }
}
