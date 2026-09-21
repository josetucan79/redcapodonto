<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

/**
 * Class PSAT
 * @package Vanderbilt\REDCap\Classes\MyCap\ActiveTasks
 * @see http://researchkit.org/docs/Classes/ORKOrderedTask.html#//api/name/PSATTaskWithIdentifier:intendedUseDescription:presentationMode:interStimulusInterval:stimulusDuration:seriesLength:options:
 */
class PSAT
{
    // See ORKPSATStep->validateParemeters()
    const ORKPSATINTERSTIMULUSMINIMUMINTERVAL = 1.0;
    const ORKPSATINTERSTIMULUSMAXIMUMINTERVAL = 5.0;
    const ORKPSATSTIMULUSMINIMUMDURATION = 0.2;
    const ORKPSATSERIEMINIMUMLENGTH = 10;
    const ORKPSATSERIEMAXIMUMLENGTH = 120;

    const MODE_AUDITORYANDVISUAL = '.AuditoryAndVisual';
    const MODE_AUDITORY = '.Auditory';
    const MODE_VISUAL = '.Visual';

    /** @var string (optional) */
    public $intendedUseDescription = '';

    /** @var string */
    public $presentationMode = self::MODE_AUDITORYANDVISUAL;

    /** @var double */
    public $interStimulusInterval = 1.0;

    /** @var double */
    public $stimulusDuration = 0.8;

    /** @var int */
    public $seriesLength = 60;

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
                            'field_annotation' => Annotation::TASK_ACTIVE_PSA);
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
        if (isset($data['presentationMode'])) {
            $this->presentationMode = $data['presentationMode'];
        }
        if (isset($data['interStimulusInterval'])) {
            $this->interStimulusInterval = doubleval($data['interStimulusInterval']);
        }
        if (isset($data['stimulusDuration'])) {
            $this->stimulusDuration = doubleval($data['stimulusDuration']);
        }
        if (isset($data['seriesLength'])) {
            $this->seriesLength = intval($data['seriesLength']);
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

        if (!in_array($data['presentationMode'], [self::MODE_AUDITORYANDVISUAL, self::MODE_AUDITORY, self::MODE_VISUAL])) {
            $errors[] = $lang['missing_data_mdc_inv']." ".$lang['mycap_mobile_app_247']." ". $data['presentationMode'];
        }

        if (!is_numeric($data['interStimulusInterval'])) {
            $errors[] = $lang['mycap_mobile_app_252']." ".$lang['data_import_tool_85'];
        }

        if (!is_numeric($data['stimulusDuration'])) {
            $errors[] = $lang['mycap_mobile_app_255']." ".$lang['data_import_tool_85'];
        }

        if (!is_numeric($data['seriesLength'])) {
            $errors[] = $lang['mycap_mobile_app_257']." ".$lang['data_import_tool_85'];
        }

        $presentationMode = $data['presentationMode'];
        $interStimulusInterval = doubleval($data['interStimulusInterval']);
        $stimulusDuration = doubleval($data['stimulusDuration']);
        $seriesLength = intval($data['seriesLength']);

        if ($interStimulusInterval < self::ORKPSATINTERSTIMULUSMINIMUMINTERVAL ||
            $interStimulusInterval > self::ORKPSATINTERSTIMULUSMAXIMUMINTERVAL) {
            $errors[] = $lang['mycap_mobile_app_252']." ".$lang['mycap_mobile_app_253']." ".self::ORKPSATINTERSTIMULUSMINIMUMINTERVAL." ".
                        $lang['control_center_4469']." ".$lang['config_functions_90']." ".self::ORKPSATINTERSTIMULUSMAXIMUMINTERVAL." ".
                        $lang['control_center_4469'];
        }

        if (($presentationMode == self::MODE_AUDITORYANDVISUAL || $presentationMode == self::MODE_VISUAL) &&
            ($stimulusDuration < self::ORKPSATSTIMULUSMINIMUMDURATION || $stimulusDuration > $interStimulusInterval)) {
            $errors[] = $lang['mycap_mobile_app_255']." ".$lang['mycap_mobile_app_253']." ".self::ORKPSATSTIMULUSMINIMUMDURATION." ".
                        $lang['control_center_4469']." ".$lang['config_functions_90']." ".$interStimulusInterval." ".$lang['control_center_4469'];
        }

        if ($seriesLength < self::ORKPSATSERIEMINIMUMLENGTH ||
            $seriesLength > self::ORKPSATSERIEMAXIMUMLENGTH) {
            $errors[] = $lang['mycap_mobile_app_257']." ".$lang['mycap_mobile_app_253']." ".self::ORKPSATSERIEMINIMUMLENGTH." ".
                $lang['mycap_mobile_app_258']." ".$lang['config_functions_90']." ".self::ORKPSATSERIEMAXIMUMLENGTH." ".$lang['mycap_mobile_app_258'];
        }
        return $errors;
    }
}
