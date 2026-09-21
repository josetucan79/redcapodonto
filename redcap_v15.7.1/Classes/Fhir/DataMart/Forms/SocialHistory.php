<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class SocialHistory extends Form
{

    protected $form_name = 'social_history';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'social_h_fhir_id',
        'code-display' => 'social_h_label',
        'code-code' => 'social_h_loinc_code',
        'normalized_timestamp' => 'social_h_time',
        'value' => 'social_h_value',
        'valueUnit' => 'social_h_unit',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['social_h_loinc_code', 'social_h_time'];

}