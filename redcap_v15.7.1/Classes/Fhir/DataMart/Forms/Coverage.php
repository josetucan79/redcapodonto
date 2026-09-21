<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class Coverage extends Form
{

    protected $form_name = 'coverage';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'cvrg_fhir_id',
        'status' => 'cvrg_status',
        'network' => 'cvrg_network',
        'period_start' => 'cvrg_period_start',
        'period_end' => 'cvrg_period_end',
        'order' => 'cvrg_order',
        'plan_name' => 'cvrg_plan_name',
        'payor_1' => 'cvrg_payor',
        'type_text' => 'cvrg_type',
        'cost_to_beneficiary' => 'cvrg_cost_to_beneficiary',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['cvrg_network', 'cvrg_period_start', 'cvrg_period_end', 'cvrg_plan_name'];

}