<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class VitalSigns extends Form
{

    protected $form_name = 'vital_signs';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'vitals_fhir_id',
        'code-display' => 'vitals_label',
        'code-display' => 'vitals_label',
        'code-code' => 'vitals_loinc_code',
        'normalized_timestamp' => 'vitals_time',
        'value' => 'vitals_value',
        'valueUnit' => 'vitals_unit',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['vitals_loinc_code', 'vitals_time'];

}