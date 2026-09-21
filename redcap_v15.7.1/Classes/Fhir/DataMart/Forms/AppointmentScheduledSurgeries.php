<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class AppointmentScheduledSurgeries extends Form
{

    protected $form_name = 'scheduled_surgeries';

    // FHIR data => for fields
    protected $data_mapping = [
        'fhir_id' => 'aptmnt_ss_fhir_id',
        'status' => 'aptmnt_ss_status',
        'normalized_created' => 'aptmnt_ss_created',
        'normalized_start' => 'aptmnt_ss_start',
        'normalized_end' => 'aptmnt_ss_end',
        'minutes_duration' => 'aptmnt_ss_minutes_duration',
        'description' => 'aptmnt_ss_description',
        'cancellation_date' => 'aptmnt_ss_cancellation_date',
        'cancellation_reason_text' => 'aptmnt_ss_cancellation_reason',
        'note_time_1' => 'aptmnt_ss_note_time_1',
        'note_text_1' => 'aptmnt_ss_note_text_1',
        'patient_instruction' => 'aptmnt_ss_patient_instr',
        'appointment_type_1' => 'aptmnt_ss_type_1',
        'service_type_1' => 'aptmnt_ss_service_type_1',
        'practitioner' => 'aptmnt_ss_practitioner',
        'location' => 'aptmnt_ss_location',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['aptmnt_ss_created', 'aptmnt_ss_start', 'aptmnt_ss_end'];

}