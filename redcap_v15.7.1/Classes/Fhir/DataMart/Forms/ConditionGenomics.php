<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class ConditionGenomics extends Form
{

    protected $form_name = 'condition_genomics';
    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'cond_gen_fhir_id',
        'normalized_timestamp' => 'cond_gen_recorded_date',
        'clinical_status' => 'cond_gen_clinical_status',
        'label' => 'cond_gen_label',
        'icd-10-display' => 'cond_gen_icd10_display',
        'icd-10-code' => 'cond_gen_icd10_code',
        'icd-9-display' => 'cond_gen_icd9_display',
        'icd-9-code' => 'cond_gen_icd9_code',
        'snomed-ct-display' => 'cond_gen_snomed_display',
        'snomed-ct-code' => 'cond_gen_snomed_code',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['cond_gen_recorded_date', 'cond_gen_label', 'cond_gen_icd10_code', 'cond_gen_icd9_code', 'cond_gen_snomed_code'];
}