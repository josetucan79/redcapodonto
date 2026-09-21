<?php

namespace Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;

class ShapeColorSorting
{
    /**
     * Return list of pre-defined fields for this active task
     *
     * @return array
     */
    public function getFormFields()
    {
        $fieldArr[] = array('field_name' => 'scs_uuid',
                            'field_label' => 'UUID',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_taskdata',
                            'field_label' => 'taskData',
                            'field_type' => 'file',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_starttime',
                            'field_label' => 'startTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_endtime',
                            'field_label' => 'endTime',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_status',
                            'field_label' => 'Status',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_taskname',
                            'field_label' => 'taskName',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_testversion',
                            'field_label' => 'testVersion',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_locale',
                            'field_label' => 'locale',
                            'field_type' => 'text',
                            'field_req' => 1);

        $fieldArr[] = array('field_name' => 'scs_steps',
                            'field_label' => 'steps',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'scs_stephistory',
                            'field_label' => 'stepHistory',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'scs_userinteractions',
                            'field_label' => 'userInteractions',
                            'field_type' => 'file');

        $fieldArr[] = array('field_name' => 'scs_rawscore',
                            'field_label' => 'rawScore',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 30);

        $fieldArr[] = array('field_name' => 'scs_item_count',
                            'field_label' => 'itemCount',
                            'field_type' => 'text',
                            'field_req' => 1,
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 60);

        $fieldArr[] = array('field_name' => 'scs_nanticipationlive',
                            'field_label' => 'nAnticipationLive',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 90);

        $fieldArr[] = array('field_name' => 'scs_totalerrors',
                            'field_label' => 'totalErrors',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 141);

        $fieldArr[] = array('field_name' => 'scs_ratescore',
                            'field_label' => 'rateScore',
                            'field_type' => 'text',
                            'val_type' => 'number',
                            'val_min' => 0,
                            'val_max' => 10);

        $fieldArr[] = array('field_name' => 'scs_mediancorrectrt',
                            'field_label' => 'medianCorrectRT',
                            'field_type' => 'text',
                            'val_type' => 'integer',
                            'val_min' => 0,
                            'val_max' => 5000);

        $fieldArr[] = array('field_name' => 'scs_medianincorrectrt',
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
