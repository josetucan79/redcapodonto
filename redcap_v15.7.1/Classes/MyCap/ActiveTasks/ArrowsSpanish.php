<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

class ArrowsSpanish
{
    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'arw_uuid_sp',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'arw_taskdata_sp',
                            'field_label' => 'taskData',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'arw_starttime_sp',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'arw_endtime_sp',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'arw_status_sp',
                            'field_label' => 'Status',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'arw_taskname_sp',
                            'field_label' => 'taskName',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'arw_testversion_sp',
                            'field_label' => 'testVersion',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'arw_locale_sp',
                            'field_label' => 'locale',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'arw_steps_sp',
                            'field_label' => 'steps',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'arw_stephistory_sp',
                            'field_label' => 'stepHistory',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'arw_userinteractions_sp',
                            'field_label' => 'userInteractions',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'arw_rawscore_sp',
                            'field_label' => 'rawScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 100);

        $fieldArr[] = array('field_name' => 'arw_item_count_sp',
                            'field_label' => 'itemCount',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 100);

        $fieldArr[] = array('field_name' => 'arw_nanticipationpractice_sp',
                            'field_label' => 'nAnticipationPractice',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 16);

        $fieldArr[] = array('field_name' => 'arw_nanticipationlive_sp',
                            'field_label' => 'nAnticipationLive',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 200);

        $fieldArr[] = array('field_name' => 'arw_totalerrors_sp',
                            'field_label' => 'totalErrors',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 216);

        $fieldArr[] = array('field_name' => 'arw_ratescore_sp',
                            'field_label' => 'rateScore',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => 0,
                            'val_max' => 10);

        $fieldArr[] = array('field_name' => 'arw_mediancorrectrt_sp',
                            'field_label' => 'medianCorrectRT',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 5000);

        $fieldArr[] = array('field_name' => 'arw_medianincorrectrt_sp',
                            'field_label' => 'medianIncorrectRT',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 5000);
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
