<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

class SequencesSpanish
{
    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'mfs_uuid_sp',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'mfs_taskdata_sp',
                            'field_label' => 'taskData',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'mfs_starttime_sp',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'mfs_endtime_sp',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'mfs_status_sp',
                            'field_label' => 'Status',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'mfs_taskname_sp',
                            'field_label' => 'taskName',
                            'field_type' => 'text');

        $fieldArr[] = array('field_name' => 'mfs_testversion_sp',
                            'field_label' => 'testVersion',
                            'field_type' => 'text');

        $fieldArr[] = array('field_name' => 'mfs_locale_sp',
                            'field_label' => 'locale',
                            'field_type' => 'text');

        $fieldArr[] = array('field_name' => 'mfs_steps_sp',
                            'field_label' => 'steps',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'mfs_stephistory_sp',
                            'field_label' => 'stepHistory',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'mfs_userinteractions_sp',
                            'field_label' => 'userInteractions',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'mfs_rawscore_sp',
                            'field_label' => 'rawScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 30);

        $fieldArr[] = array('field_name' => 'mfs_item_count_sp',
                            'field_label' => 'itemCount',
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
