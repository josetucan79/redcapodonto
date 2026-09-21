<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class Diagnosis extends Form
{

    protected $form_name = 'diagnosis';
    // FHIR data => for fields
    protected $data_mapping = [
        'icd-10-display' => 'diagnosis_icd10_display',
        'icd-10-code' => 'diagnosis_icd10_code',
        'icd-9-display' => 'diagnosis_icd9_display',
        'icd-9-code' => 'diagnosis_icd9_code',
        'snomed-ct-display' => 'diagnosis_snomed_display',
        'snomed-ct-code' => 'diagnosis_snomed_code',
        'note' => 'diagnosis_note',
        'encounter_reference' => 'diagnosis_encounter',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['diagnosis_encounter', 'diagnosis_icd10_code', 'diagnosis_icd9_code', 'diagnosis_snomed_code', 'diagnosis_note'];
}