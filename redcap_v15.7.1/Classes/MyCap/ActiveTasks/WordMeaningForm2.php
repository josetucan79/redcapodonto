<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

class WordMeaningForm2
{
    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'wm2_uuid',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm2_taskdata',
                            'field_label' => 'taskData',
                            'field_type' => 'file',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm2_starttime',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm2_endtime',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm2_status',
                            'field_label' => 'Status',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm2_taskname',
                            'field_label' => 'taskName',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm2_testversion',
                            'field_label' => 'testVersion',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm2_locale',
                            'field_label' => 'locale',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm2_steps',
                            'field_label' => 'steps',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'wm2_stephistory',
                            'field_label' => 'stepHistory',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'wm2_userinteractions',
                            'field_label' => 'userInteractions',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'wm2_consideredsteps',
                            'field_label' => 'consideredSteps',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'wm2_starttheta',
                            'field_label' => 'startTheta',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'val_type' => 'number',
                            'val_min' => -6,
                            'val_max' => 6);

        $fieldArr[] = array('field_name' => 'wm2_startse',
                            'field_label' => 'startSE',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'val_type' => 'number',
                            'val_min' => 0,
                            'val_max' => 10);

        $fieldArr[] = array('field_name' => 'wm2_finaltheta',
                            'field_label' => 'finalTheta',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => -6,
                            'val_max' => 6);

        $fieldArr[] = array('field_name' => 'wm2_finalse',
                            'field_label' => 'finalSE',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => 0,
                            'val_max' => 10);

        $fieldArr[] = array('field_name' => 'wm2_item_count',
                            'field_label' => 'itemCount',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 25);

        $fieldArr[] = array('field_name' => 'wm2_skip_count',
                            'field_label' => 'skipCount',
                            'field_type' => 'text',
                            'val_type' => 'integer');
        return $fieldArr;
    }

    /**
     * Assign array values to class variables to save extended config variables
     *
     * @return void
     */
    public function buildExtendedConfig($data = array())
    {
    }

    /**
     * Validate extended config variables and returns list of errors
     *
     * @return array
     */
    public static function validateExtendedConfigParams($data = array()) {
        return array();
    }
}
