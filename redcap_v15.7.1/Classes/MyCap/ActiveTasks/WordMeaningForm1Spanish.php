<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

class WordMeaningForm1Spanish
{
    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'wm1_uuid_sp',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm1_taskdata_sp',
                            'field_label' => 'taskData',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'wm1_starttime_sp',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm1_endtime_sp',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm1_status_sp',
                            'field_label' => 'taskStatus',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm1_taskname_sp',
                            'field_label' => 'taskName',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm1_testversion_sp',
                            'field_label' => 'testVersion',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm1_locale_sp',
                            'field_label' => 'locale',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'wm1_steps_sp',
                            'field_label' => 'steps',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'wm1_stephistory_sp',
                            'field_label' => 'stepHistory',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'wm1_userinteractions_sp',
                            'field_label' => 'userInteractions',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'wm1_consideredsteps_sp',
                            'field_label' => 'consideredSteps',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'wm1_starttheta_sp',
                            'field_label' => 'startTheta',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'val_type' => 'number',
                            'val_min' => -6,
                            'val_max' => 6);

        $fieldArr[] = array('field_name' => 'wm1_startse_sp',
                            'field_label' => 'startSE',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'val_type' => 'number',
                            'val_min' => 0,
                            'val_max' => 10);

        $fieldArr[] = array('field_name' => 'wm1_finaltheta_sp',
                            'field_label' => 'finalTheta',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => -6,
                            'val_max' => 6);

        $fieldArr[] = array('field_name' => 'wm1_item_count_sp',
                            'field_label' => 'itemCount',
                            'field_type' => 'text',
                            'val_type' => 'integer');

        $fieldArr[] = array('field_name' => 'wm1_skip_count_sp',
                            'field_label' => 'skipCount',
                            'field_type' => 'text',
                            'val_type' => 'integer');

        $fieldArr[] = array('field_name' => 'wm1_finalse_sp',
                            'field_label' => 'finalSE',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => 0,
                            'val_max' => 10);
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
