<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

class Sequences
{
    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'mfs_uuid',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'mfs_taskdata',
                            'field_label' => 'taskData',
                            'field_type' => 'file',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'mfs_starttime',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'mfs_endtime',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'mfs_status',
                            'field_label' => 'Status',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'mfs_taskname',
                            'field_label' => 'taskName',
                            'field_type' => 'text');

        $fieldArr[] = array('field_name' => 'mfs_testversion',
                            'field_label' => 'testVersion',
                            'field_type' => 'text');

        $fieldArr[] = array('field_name' => 'mfs_locale',
                            'field_label' => 'locale',
                            'field_type' => 'text');

        $fieldArr[] = array('field_name' => 'mfs_steps',
                            'field_label' => 'steps',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'mfs_stephistory',
                            'field_label' => 'stepHistory',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'mfs_userinteractions',
                            'field_label' => 'userInteractions',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'mfs_rawscore',
                            'field_label' => 'rawScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 30);

        $fieldArr[] = array('field_name' => 'mfs_item_count',
                            'field_label' => 'itemCount',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 30);

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
