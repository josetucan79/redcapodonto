<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;

class FhirMetadataDiagnosisDecorator extends FhirMetadataAbstractDecorator
{
  /**
   * apply decorator and get a new list
   * 2021-08-23 NOTE: temporary decorator for OMOP. only 1 project for vanderbilt production is allowed
   *
   * @param array $list
   * @return array
   */
  public function getList()
  {
    /**
     * check if we are in development
     * OR
     * if we are @ Vanderbilt and in a specific project
     */
    $isAllowed = function() {
      $projectConfig = \Project::getProjectVals();
      $project_id = $projectConfig['project_id'] ?? null;

      $allowedProjectIds = [137381]; // list of enabled projects
      if(isDev()) return true;
      if(defined("CRON")) return true;
      return (isVanderbilt() && in_array($project_id, $allowedProjectIds));
    };

    $metadata_array = $this->fhirMetadata->getList();
    if(!$isAllowed()) {
      unset($metadata_array['encounter-diagnosis-list']);
    }

    return $metadata_array;
  }
}