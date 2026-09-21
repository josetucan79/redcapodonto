<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class Encounters extends Form
{
    protected $form_name = 'encounters';
    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'encounter_fhir_id',
        'type' => 'encounter_type',
        'reason' => 'encounter_reason',
        'class' => 'encounter_class',
        'status' => 'encounter_status',
        'location' => 'encounter_location',
        'normalized_period-start' => 'encounter_period_start',
        'normalized_period-end' => 'encounter_period_end',
    ];


    protected $uniquenessFields = ['encounter_fhir_id', 'encounter_type', 'encounter_location', 'encounter_period_start', 'encounter_period_end'];
}