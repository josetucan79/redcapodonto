<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

/**
 * Undocumented class
 */
class DocumentReference extends AbstractEndpoint
{


  const CATEGORY_CLINICAL_NOTE = 'clinical-note';
  const CATEGORY_OASIS = 'OASIS'; // any OASIS category
  const CATEGORY_OASIS_SOC = 'OASIS-SOC';
  const CATEGORY_OASIS_ROC = 'OASIS-ROC';
  const CATEGORY_OASIS_RECERTIFICATION = 'OASIS-RECERTIFICATION';
  const CATEGORY_OASIS_OTHER_FOLLOWUP = 'OASIS-OTHER-FOLLOWUP';
  const CATEGORY_OASIS_TRANSFER_NO_DISCHARGE = 'OASIS-TRANSFER-NO-DISCHARGE';
  const CATEGORY_OASIS_TRANSFER_DISCHARGE = 'OASIS-TRANSFER-DISCHARGE';
  const CATEGORY_OASIS_DEATH = 'OASIS-DEATH';
  const CATEGORY_OASIS_DISCHARGE = 'OASIS-DISCHARGE';
  const CATEGORY_HIS = 'HIS'; // any HIS
  const CATEGORY_HIS_ADMISSION = 'HIS-ADMISSION';
  const CATEGORY_HIS_DISCHARGE = 'HIS-DISCHARGE';
  const CATEGORY_external_ccda = 'external-ccda';
  const CATEGORY_handoff = 'handoff';
  const CATEGORY_RADIOLOGY_RESULTS = 'imaging-result';
  const CATEGORY_correspondence = 'correspondence';
  const CATEGORY_document_information = 'document-information';
  const IRF_PAI = 'IRF-PAI';
  const MDS = 'MDS';
  const CATEGORY_CLINICAL_REFERENCE = 'clinical-reference';
  const CATEGORY_PATIENT_ENETERED_QUESTIONNAIRE = 'questionnaire-response';
  const CATEGORY_GENERATED_CCDA = 'summary-document';

  const CATEGORY = 'clinical-note';

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::DOCUMENT_REFERENCE_CLINICAL_NOTES;
  }

  public function getSearchRequest($params=[])
  {
    $params['category'] = self::CATEGORY;
    return parent::getSearchRequest($params);
  }

}