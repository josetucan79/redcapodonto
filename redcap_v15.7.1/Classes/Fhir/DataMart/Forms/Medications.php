<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class Medications extends Form
{

    protected $form_name = 'medications';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'medication_fhir_id',
        'display' => 'medication_label',
        'normalized_timestamp' => 'medication_date',
        'dosage' => 'medication_dosage',
        'status' => 'medication_status',
        'rxnorm_display' => 'medication_rxnorm_display',
        'rxnorm_code' => 'medication_rxnorm_code',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['medication_label', 'medication_date'];

}