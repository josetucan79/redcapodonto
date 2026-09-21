<?php
namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\Forms;

class Allergies extends Form
{

    protected $form_name = 'allergies';
    // FHIR data => form fields
    protected $data_mapping = [
        'fhir_id' => 'allergy_fhir_id',
        'normalized_timestamp' => 'allergy_recorded_date',
        'snomed_display' => 'allergy_snomed_display',
        'snomed_code' => 'allergy_snomed_code',
        'fda_unii_display' => 'allergy_fdaunii_display',
        'fda_unii_code' => 'allergy_fdaunii_code',
        'ndf_rt_display' => 'allergy_ndfrt_display',
        'ndf_rt_code' => 'allergy_ndfrt_code',
        'rxnorm_display' => 'allergy_rxnorm_display',
        'rxnorm_code' => 'allergy_rxnorm_code',
    ];

    /**
     * keys check if the data in the instance is similar
     * to the one provided
     *
     * @var array $keys
     */
    protected $uniquenessFields = ['allergy_recorded_date', 'allergy_snomed_code', 'allergy_fdaunii_code', 'allergy_ndfrt_code'];

}