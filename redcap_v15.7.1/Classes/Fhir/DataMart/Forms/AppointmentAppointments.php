<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class AppointmentAppointments extends Form
{

    protected $form_name = 'appointments';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'aptmnt_fhir_id',
        'status' => 'aptmnt_status',
        'normalized_created' => 'aptmnt_created',
        'normalized_start' => 'aptmnt_start',
        'normalized_end' => 'aptmnt_end',
        'minutes_duration' => 'aptmnt_minutes_duration',
        'description' => 'aptmnt_description',
        'cancellation_date' => 'aptmnt_cancellation_date',
        'cancellation_reason_text' => 'aptmnt_cancellation_reason',
        'note_time_1' => 'aptmnt_note_time_1',
        'note_text_1' => 'aptmnt_note_text_1',
        'patient_instruction' => 'aptmnt_patient_instruction',
        'appointment_type_1' => 'aptmnt_type_1',
        'service_type_1' => 'aptmnt_service_type_1',
        'practitioner' => 'aptmnt_practitioner',
        'location' => 'aptmnt_location',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['aptmnt_created', 'aptmnt_start', 'aptmnt_end'];

}