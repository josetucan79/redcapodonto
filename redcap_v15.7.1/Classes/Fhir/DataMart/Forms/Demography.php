<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class Demography extends Form
{
    protected $form_name = 'demography';
    // FHIR data => for fields
    protected $data_mapping = [
        'fhir-id'                   => 'patient_fhir_id',
        'id'                        => 'mrn',
        'address-city'              => 'address_city',
        'address-district'          => 'address_district',
        'address-country'           => 'address_country',
        'address-postalCode'        => 'address_postalcode',
        'address-state'             => 'address_state',
        'address-line'              => 'address_line',
        'birthDate'                 => 'dob',
        'name-given'                => 'first_name',
        'name-family'               => 'last_name',
        'phone-home'                => 'phone_home',
        'phone-mobile'              => 'phone_mobile',
        'gender'                    => 'sex',
        'legal-sex'                 => 'legal_sex',
        'sex-for-clinical-use'      => 'sex_for_clinical_use',
        'ethnicity'                 => 'ethnicity',
        'race'                      => 'race',
        'preferred-language'        => 'preferred_language',
        'deceasedBoolean'           => 'is_deceased',
        'deceasedDateTime'          => 'deceased_date_time',
        'email'                     => 'email',
        'email-2'                   => 'email_2',
        'email-3'                   => 'email_3',
        'general-practitioner'      => 'general_practitioner',
        'managing-organization'     => 'managing_organization',
        'pronouns'                  => 'pronouns',
        'marital-status'            => 'marital_status',
        'contact-relationship-1'    => 'contact_relationship_1',
        'contact-name-1'            => 'contact_name_1',
        'contact-phone-1'           => 'contact_phone_1',
        'contact-relationship-2'    => 'contact_relationship_2',
        'contact-name-2'            => 'contact_name_2',
        'contact-phone-2'           => 'contact_phone_2',
        'contact-relationship-3'    => 'contact_relationship_3',
        'contact-name-3'            => 'contact_name_3',
        'contact-phone-3'           => 'contact_phone_3',
    ];

    protected $uniquenessFields = [
        'dob',
        'first_name',
        'last_name',
        'sex',
        'ethnicity',
        'race',
    ];

}