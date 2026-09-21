<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class ProblemList extends Form
{

    protected $form_name = 'problem_list';
    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'problem_list_fhir_id',
        'timestamp' => 'problem_recorded_date',
        'clinical_status' => 'problem_clinical_status',
        'icd-10-display' => 'problem_icd10_display',
        'icd-10-code' => 'problem_icd10_code',
        'icd-9-display' => 'problem_icd9_display',
        'icd-9-code' => 'problem_icd9_code',
        'snomed-ct-display' => 'problem_snomed_display',
        'snomed-ct-code' => 'problem_snomed_code',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['problem_recorded_date', 'problem_icd10_code', 'problem_icd9_code', 'problem_snomed_code'];
}