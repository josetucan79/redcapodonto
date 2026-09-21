<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints\R4;

use Vanderbilt\REDCap\Classes\Fhir\Endpoints\EndpointIdentifier;

/**
 * Undocumented class
 */
class DocumentReferenceClinicalNotes extends DocumentReference
{

  const CATEGORY = 'clinical-note';

  public function getResourceIdentifier()
  {
    return EndpointIdentifier::DOCUMENT_REFERENCE_CLINICAL_NOTES;
  }

}