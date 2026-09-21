<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class ProblemList extends Form
{

    protected $form_name = 'condition_dental_finding';
    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'cond_df_fhir_id',
        'normalized_timestamp' => 'cond_df_recorded_date',
        'clinical_status' => 'cond_df_clinical_status',
        'label' => 'cond_df_label',
        'body_site_1' => 'cond_df_body_site_1',
        'recorder' => 'cond_df_recorder',
        'recorder_type' => 'cond_df_recorder_type',
        'encounter_reference' => 'cond_df_encounter_ref',
        'encounter_label' => 'cond_df_encounter_label',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['cond_df_recorded_date', 'cond_df_label', 'cond_df_recorder', 'cond_df_encounter_ref'];
}