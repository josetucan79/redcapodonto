<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class Procedures extends Form
{

    protected $form_name = 'procedures';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'procedure_fhir_id',
        'status' => 'procedure_status',
        'category' => 'procedure_category',
        'code' => 'procedure_code',
        'cpt-display' => 'procedure_cpt_display',
        'cpt-code' => 'procedure_cpt_code',
        'encounter_reference' => 'procedure_encounter',
        'reason' => 'procedure_reason',
        'performed-date-time' => 'procedure_date',
        'note' => 'procedure_note',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['procedure_code', 'procedure_date', 'procedure_category'];
    // protected $uniquenessFields = ['fhir_id'];
}