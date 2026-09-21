<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class DeviceImplants extends Form
{

    protected $form_name = 'devices';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id'       => 'device_fhir_id',
        'device_name'   => 'device_name',
        'type'          => 'device_type',
        'model_number'  => 'device_model_number',
        'site'          => 'device_site',
        'permanence'    => 'device_permanence',
        'laterality'    => 'device_laterality',
        'radioactive'   => 'device_radioactive',
        'note_time_1'   => 'device_note_time_1',
        'note_text_1'   => 'device_note_text_1',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = [
        'device_device_name',
        'device_type',
        'device_model_number',
        'device_site',
        'device_permanence',
        'device_laterality',
        'device_radioactive',
    ];
    // protected $uniquenessFields = ['fhir_id'];
}