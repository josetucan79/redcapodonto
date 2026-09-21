<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class ConditionInfection extends Form
{

    protected $form_name = 'condition_infection';
    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'cond_inf_fhir_id',
        'normalized_timestamp' => 'cond_inf_recorded_date',
        'clinical_status' => 'cond_inf_clinical_status',
        'label' => 'cond_inf_label',
        'icd-10-display' => 'cond_inf_icd10_display',
        'icd-10-code' => 'cond_inf_icd10_code',
        'icd-9-display' => 'cond_inf_icd9_display',
        'icd-9-code' => 'cond_inf_icd9_code',
        'snomed-ct-display' => 'cond_inf_snomed_display',
        'snomed-ct-code' => 'cond_inf_snomed_code',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['cond_inf_recorded_date', 'cond_inf_label', 'cond_inf_icd10_code', 'cond_inf_icd9_code', 'cond_inf_snomed_code'];
}