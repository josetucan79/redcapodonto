<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class Labs extends Form
{

    protected $form_name = 'labs';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'labs_fhir_id',
        'code-display' => 'labs_label',
        'code-code' => 'labs_loinc_code',
        'normalized_timestamp' => 'labs_time',
        'value' => 'labs_value',
        'valueUnit' => 'labs_unit',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['labs_loinc_code', 'labs_time'];

}