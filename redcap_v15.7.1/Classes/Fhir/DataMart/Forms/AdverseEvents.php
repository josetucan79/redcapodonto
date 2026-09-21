<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class AdverseEvents extends Form
{

    protected $form_name = 'adverse_events';

    // FHIR data => for fields
    protected $data_mapping = [
        'actuality' => 'adv_event_actuality',
        'event' => 'adv_event_event',
        'causality' => 'adv_event_causality',
        'seriousness' => 'adv_event_seriousness',
        'severity' => 'adv_event_severity',
        'outcome' => 'adv_event_outcome',
        'studies' => 'adv_event_studies',
        'normalized_timestamp' => 'adv_event_timestamp',
    ];
    
    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['adv_event_event', 'adv_event_timestamp'];

}