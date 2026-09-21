<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class ConditionReasonForVisit extends Form
{

    protected $form_name = 'condition_reason_for_visit';
    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'cond_rfv_fhir_id',
        'normalized_timestamp' => 'cond_rfv_recorded_date',
        'clinical_status' => 'cond_rfv_clinical_status',
        'label' => 'cond_rfv_label',
        'recorder' => 'cond_rfv_recorder',
        'recorder_type' => 'cond_rfv_recorder_type',
        'encounter_reference' => 'cond_rfv_encounter_ref',
        'encounter_label' => 'cond_rfv_encounter_label',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['cond_rfv_recorded_date', 'cond_rfv_encounter_label', 'cond_rfv_recorder', 'cond_rfv_encounter_ref'];
}