<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Endpoints;

use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;

/**
 * Class EndpointsHelper
 *
 * This class provides useful functions for visiting endpoints before making a FHIR request.
 *
 * @package Vanderbilt\REDCap\Classes\Fhir\Endpoints
 */
class EndpointsHelper 
{

  /**
     * Get the IRB number of the project.
     *
     * @return string|null The IRB number of the project, or null if not applicable.
     */
  function getProjectIrbNumber() {
      $projectVals = \Project::getProjectVals();
      $irbNumber = $projectVals['project_irb_number'] ?? '';
      $purpose = intval($projectVals['purpose'] ?? '');
      if($purpose!=2 || empty($irbNumber)) return;
      return $irbNumber;
  }

  /**
     * Get the FHIR study ID.
     *
     * @param FhirClient $fhirClient The FHIR client instance.
     * @param string $studyID The study ID to retrieve the FHIR ID for.
     *
     * @return string|null The FHIR study ID, or null if not found.
     */
  function getFhirStudyID($fhirClient, $studyID) {
    if(!$studyID) return '';
    $fileCache = new FileCache(__CLASS__);
    $studyIdCacheKey = 'study_id_'.$studyID;
    if(!$studyFhirId=$fileCache->get($studyIdCacheKey)) {
        $studyFhirId = $fhirClient->getStudyFhirId($studyID);
        if(!$studyFhirId) return;
        $fileCache->set($studyIdCacheKey, $studyFhirId);
    }
    return $studyFhirId;
  }

}