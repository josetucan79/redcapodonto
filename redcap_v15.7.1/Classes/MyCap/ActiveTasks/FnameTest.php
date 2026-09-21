<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

class FnameTest
{
    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'fnt_uuid',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_taskdata',
                            'field_label' => 'taskData',
                            'field_type' => 'file',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_starttime',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_endtime',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_status',
                            'field_label' => 'Status',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_taskname',
                            'field_label' => 'taskName',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_testversion',
                            'field_label' => 'testVersion',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_locale',
                            'field_label' => 'locale',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_steps',
                            'field_label' => 'steps',
                            'field_type' => 'file',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_stephistory',
                            'field_label' => 'stepHistory',
                            'field_type' => 'file',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'fnt_userinteractions',
                            'field_label' => 'userInteractions',
                            'field_type' => 'file',
                            'field_req' => 1);
        $fieldArr[] = array('field_name' => 'fnt_rawscore',
                            'field_label' => 'rawScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 36);
        $fieldArr[] = array('field_name' => 'fnt_fsbscore',
                            'field_label' => 'fsbScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 12);
        $fieldArr[] = array('field_name' => 'fnt_fnlscore',
                            'field_label' => 'fnlScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 12);
        $fieldArr[] = array('field_name' => 'fnt_fnmscore',
                            'field_label' => 'fnmScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 12);
        $fieldArr[] = array('field_name' => 'fnt_finaltheta',
                            'field_label' => 'finalTheta',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => -6,
                            'val_max' => 6);
        $fieldArr[] = array('field_name' => 'fnt_finalse',
                            'field_label' => 'finalSe',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => 0,
                            'val_max' => 10);

        $fieldArr[] = array('field_name' => 'fnt_item_count',
                            'field_label' => 'itemCount',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 36);

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
