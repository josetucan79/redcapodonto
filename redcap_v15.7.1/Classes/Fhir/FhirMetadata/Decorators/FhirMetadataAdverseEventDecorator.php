<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

class FhirMetadataAdverseEventDecorator extends FhirMetadataAbstractDecorator
{
  /**
   * apply decorator and get a new list
   *
   * @param array $list
   * @return array
   */
  public function getList()
  {
    $projectConfig = \Project::getProjectVals();
    $irbNumber = $projectConfig['project_irb_number'] ?? 0;
    $purpose = intval($projectConfig['purpose'] ?? 0);
    $metadata_array = $this->fhirMetadata->getList();
    $adverseEventEntry = $metadata_array['adverse-events-list'] ?? null;
    $disabled = $adverseEventEntry['disabled'] ?? true;
    if(is_null($adverseEventEntry) || $disabled === true) return $metadata_array;

    if($adverseEventEntry && ($purpose!==2 || !$irbNumber)) {
      //do something with adverse event
      $metadata_array['adverse-events-list']['disabled'] = true;
      $metadata_array['adverse-events-list']['disabled_reason'] = '`Adverse Events` are only available for `research` projects where an IRB number is specified. Also, the IRB number must match the research study ID.';
      // unset($metadata_array['adverse-events-list']);
    }

    return $metadata_array;
  }
}