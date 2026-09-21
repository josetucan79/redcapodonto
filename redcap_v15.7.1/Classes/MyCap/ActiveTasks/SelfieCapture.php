<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

use Vanderbilt\REDCap\Classes\MyCap\Annotation;

class SelfieCapture
{
    /** @var string */
    public $identifier = 'selfiecapture';

    /** @var string */
    public $infoTitle = 'Selfie Capture';

    /** @var string */
    public $infoInstructions = 'You will take your selfie on the next screen.';

    /** @var string */
    public $captureTitle = '';

    /** @var string */
    public $captureInstructions = 'Press "Start Preview" to take your selfie.';

    /** @var int */
    public $waitTime = 10;

    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'selfie',
                            'field_label' => 'Selfie Capture',
                            'field_type' => 'file',
                            'field_annotation' => Annotation::TASK_ACTIVE_SEL);
        return $fieldArr;
    }
    /**
     * Assign array values to class variables to save extended config variables
     *
     * @return void
     */
    public function buildExtendedConfig($data = array())
    {
        if (isset($data['infoTitle'])) {
            $this->infoTitle = $data['infoTitle'];
        }
        if (isset($data['infoInstructions'])) {
            $this->infoInstructions = $data['infoInstructions'];
        }
        if (isset($data['captureTitle'])) {
            $this->captureTitle = $data['captureTitle'];
        }
        if (isset($data['captureInstructions'])) {
            $this->captureInstructions = $data['captureInstructions'];
        }
        if (isset($data['waitTime'])) {
            $this->waitTime = intval($data['waitTime']);
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

        if (empty($data['infoTitle'])) {
            $errors[] = $lang['create_project_20']." ".$lang['mycap_mobile_app_319'];
        }
        if (empty($data['infoInstructions'])) {
            $errors[] = $lang['create_project_20']." ".$lang['mycap_mobile_app_320'];
        }
        if (empty($data['captureInstructions'])) {
            $errors[] = $lang['create_project_20']." ".$lang['mycap_mobile_app_323'];
        }
        if (!is_numeric($data['waitTime'])) {
            $errors[] = $lang['mycap_mobile_app_349']." ".$lang['data_import_tool_85'];
        }
        $waitTime = intval($data['waitTime']);
        if ($waitTime <= 0) {
            $errors[] = $lang['mycap_mobile_app_349']." ".$lang['mycap_mobile_app_267']." 0.";
        }
        return $errors;
    }
}
