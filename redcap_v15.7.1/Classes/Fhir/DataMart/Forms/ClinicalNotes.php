<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class ClinicalNotes extends Form
{

    protected $form_name = 'clinical_notes';

    // FHIR data => for fields
    protected $data_mapping = [
        'type' => 'clinical_n_type',
        'normalized_timestamp' => 'clinical_n_date',
        'practice_setting' => 'clinical_n_department',
        'author_type' => 'clinical_n_author_type',
        'author_display' => 'clinical_n_author_display',
        'attachments' => 'clinical_n_attachments',
        'html' => 'clinical_n_html',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['clinical_n_type','clinical_n_date', 'clinical_n_author_type ', 'clinical_n_author_display'];
}