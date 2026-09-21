<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

class FhirMetadataEmailDecorator extends FhirMetadataAbstractDecorator
{
  /**
   * apply decorator and get a new list
   *
   * @param array $list
   * @return array
   */
  public function getList()
  {
    $systemConfig = \System::getConfigVals();
    $fhir_include_email_address = intval($systemConfig['fhir_include_email_address']);
    
    $projectConfig = \Project::getProjectVals();
    $projectEnabled = boolval($projectConfig['fhir_include_email_address_project'] ?? 0);
    $systemDisabled = $fhir_include_email_address === 0;
    $projectLevelDecision = $fhir_include_email_address === 1;
    $systemEnabled = $fhir_include_email_address === 2;
    $disabled = !$systemEnabled && ($systemDisabled || ($projectLevelDecision && !$projectEnabled));

    if ($disabled) {
      $metadata_array = $this->fhirMetadata->getList();
      $emailKeys = ['email', 'email-2', 'email-3'];
      $whereIsDisabled = [];
      if($systemDisabled) $whereIsDisabled[] = 'system level';
      if($projectLevelDecision && !$projectEnabled) $whereIsDisabled[] = 'project level';
      $reason = sprintf('`Emails` fetching has been disabled at %s.', implode(' and ', $whereIsDisabled));
      $reason .= 'Please check settings in the `CDIS settings` page and in the `edit project settings` page.';
      foreach ($emailKeys as $key) {
        if(!array_key_exists($key, $metadata_array)) continue; // only process if the key exists
        $metadata_array[$key]['disabled'] = true;
        $metadata_array[$key]['disabled_reason'] = $reason;
      }
      return $metadata_array;
    }

    return $this->fhirMetadata->getList();
  }
}