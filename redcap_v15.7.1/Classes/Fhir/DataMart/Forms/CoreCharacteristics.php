<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class CoreCharacteristics extends Form
{

    protected $form_name = 'core_characteristics';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'core_c_fhir_id',
        'code-display' => 'core_c_label',
        'code-code' => 'core_c_loinc_code',
        'normalized_timestamp' => 'core_c_time',
        'value' => 'core_c_value',
        'valueUnit' => 'core_c_unit',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['core_c_loinc_code', 'core_c_time'];

}