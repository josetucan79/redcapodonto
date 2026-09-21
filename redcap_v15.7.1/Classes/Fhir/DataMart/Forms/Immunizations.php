<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class Immunizations extends Form
{

    protected $form_name = 'immunizations';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'immunization_fhir_id',
        'text' => 'immunization_label',
        'normalized_date' =>'immunization_timestamp',
        'status' =>'immunization_status',
        'cvx_code' =>'immunization_cvx_code',
    ];
    
    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['immunization_label', 'immunization_status', 'immunization_timestamp', 'immunization_cvx_code'];
}