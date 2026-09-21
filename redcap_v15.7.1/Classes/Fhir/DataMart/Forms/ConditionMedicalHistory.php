<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class ConditionMedicalHistory extends Form
{

    protected $form_name = 'condition_medical_history';
    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'cond_mh_fhir_id',
        'normalized_timestamp' => 'cond_mh_recorded_date',
        'clinical_status' => 'cond_mh_clinical_status',
        'label' => 'cond_mh_label',
        'icd-10-display' => 'cond_mh_icd10_display',
        'icd-10-code' => 'cond_mh_icd10_code',
        'icd-9-display' => 'cond_mh_icd9_display',
        'icd-9-code' => 'cond_mh_icd9_code',
        'snomed-ct-display' => 'cond_mh_snomed_display',
        'snomed-ct-code' => 'cond_mh_snomed_code',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['cond_mh_recorded_date', 'cond_mh_label', 'cond_mh_icd10_code', 'cond_mh_icd9_code', 'cond_mh_snomed_code'];
}