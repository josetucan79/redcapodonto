<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

class ShapeColorSortingDeprecated
{
    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'scs_uuid_3',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_taskdata_3',
                            'field_label' => 'taskData',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'scs_starttime_3',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_endtime_3',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_status_3',
                            'field_label' => 'Status',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_taskname_3',
                            'field_label' => 'taskName',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_testversion_3',
                            'field_label' => 'testVersion',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_locale_3',
                            'field_label' => 'locale',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_steps_3',
                            'field_label' => 'steps',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'scs_stephistory_3',
                            'field_label' => 'stepHistory',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'scs_userinteractions_3',
                            'field_label' => 'userInteractions',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'scs_rawscore_3',
                            'field_label' => 'rawScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 30);

        $fieldArr[] = array('field_name' => 'scs_item_count_3',
                            'field_label' => 'itemCount',
                            'field_type' => 'text',
                            'val_type' => 'integer');

        $fieldArr[] = array('field_name' => 'scs_nanticipationpractice_3',
                            'field_label' => 'nAnticipationPractice',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 21);

        $fieldArr[] = array('field_name' => 'scs_nanticipationlive_3',
                            'field_label' => 'nAnticipationLive',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 90);

        $fieldArr[] = array('field_name' => 'scs_totalerrors_3',
                            'field_label' => 'totalErrors',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 141);

        $fieldArr[] = array('field_name' => 'scs_ratescore_3',
                            'field_label' => 'rateScore',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => 0,
                            'val_max' => 10);

        $fieldArr[] = array('field_name' => 'scs_mediancorrectrt_3',
                            'field_label' => 'medianCorrectRT',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 5000);

        $fieldArr[] = array('field_name' => 'scs_medianincorrectrt_3',
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
