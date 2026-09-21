<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

class FnameTestSpanish
{
    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'fnt_uuid_sp',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_taskdata_sp',
                            'field_label' => 'taskData',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'fnt_starttime_sp',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_endtime_sp',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_status_sp',
                            'field_label' => 'Status',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_taskname_sp',
                            'field_label' => 'taskName',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_testversion_sp',
                            'field_label' => 'testVersion',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_locale_sp',
                            'field_label' => 'locale',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_steps_sp',
                            'field_label' => 'steps',
                            'field_type' => 'file',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_stephistory_sp',
                            'field_label' => 'stepHistory',
                            'field_type' => 'file',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_userinteractions_sp',
                            'field_label' => 'userInteractions',
                            'field_type' => 'file',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_rawscore_sp',
                            'field_label' => 'rawScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 36);

        $fieldArr[] = array('field_name' => 'fnt_item_count_sp',
                            'field_label' => 'itemCount',
                            'field_type' => 'text',
                            'val_type' => 'integer');

        $fieldArr[] = array('field_name' => 'fnt_fsbscore_sp',
                            'field_label' => 'fsbScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 12);

        $fieldArr[] = array('field_name' => 'fnt_fnlscore_sp',
                            'field_label' => 'fnlScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 12);

        $fieldArr[] = array('field_name' => 'fnt_fnmscore_sp',
                            'field_label' => 'fnmScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 12);

        $fieldArr[] = array('field_name' => 'fnt_finaltheta_sp',
                            'field_label' => 'finalTheta',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => -6,
                            'val_max' => 6);

        $fieldArr[] = array('field_name' => 'fnt_finalse_sp',
                            'field_label' => 'finalSe',
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
