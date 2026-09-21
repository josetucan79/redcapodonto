<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class ObservationSmartData extends Form
{

    protected $form_name = 'smart_data';

    // FHIR data => for fields
    protected $data_mapping = [
        'code-display' => 'smart_data_label',
        'code-code' => 'smart_data_loinc_code',
        'normalized_timestamp' => 'smart_data_time',
        'value' => 'smart_data_value',
        'valueUnit' => 'smart_data_unit',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['smart_data_loinc_code', 'smart_data_time'];

}