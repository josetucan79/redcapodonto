<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators;

/**
 * decorator made specifically for CDP projects
 */
class FhirMetadataCdpDecorator extends FhirMetadataAbstractDecorator
{

  /**
   * apply decorator and get a new list
   *
   * @param array $list
   * @return array
   */
  public function getList()
  {
    $metadata_array = $this->fhirMetadata->getList();

    // these will be disabled (still visible)
    $disabledResources = [
      ['key'=>'smart-data', 'reason' => '`SmartData` elements are not available for `Clinical Data Pull` projects.'],
      ['key'=>'appointment-scheduled-procedures-list', 'reason' => '`Scheduled Procedure` elements are not available for `Clinical Data Pull` projects.'],
      ['key'=>'appointment-appointments-list', 'reason' => '`Appointment` elements are not available for `Clinical Data Pull` projects.'],
      // disable clinical notes:
      // ['key'=>'clinical-note-18842-5', 'reason' => 'Clinical notes are not available for `Clinical Data Pull` projects'],
      // ['key'=>'clinical-note-11488-4', 'reason' => 'Clinical notes are not available for `Clinical Data Pull` projects'],
      // ['key'=>'clinical-note-34117-2', 'reason' => 'Clinical notes are not available for `Clinical Data Pull` projects'],
      // ['key'=>'clinical-note-11506-3', 'reason' => 'Clinical notes are not available for `Clinical Data Pull` projects'],
      // ['key'=>'clinical-note-28570-0', 'reason' => 'Clinical notes are not available for `Clinical Data Pull` projects'],
      // ['key'=>'clinical-note-34111-5', 'reason' => 'Clinical notes are not available for `Clinical Data Pull` projects'],
      // ['key'=>'clinical-note-34746-8', 'reason' => 'Clinical notes are not available for `Clinical Data Pull` projects'],
      // ['key'=>'clinical-note-74213-0', 'reason' => 'Clinical notes are not available for `Clinical Data Pull` projects'],
      // ['key'=>'clinical-note-75492-9', 'reason' => 'Clinical notes are not available for `Clinical Data Pull` projects'],
    ];
    foreach ($disabledResources as $disabledResource) {
      $key = $disabledResource['key'];
      $reason = $disabledResource['reason'];
      $this->disableKey($key, $reason, $metadata_array);
    }

    // these will be deleted
    $hiddenResources = [];
    foreach ($hiddenResources as $hiddenResource) {
      $this->hideKey($hiddenResource, $metadata_array);
    }

    return $metadata_array;
  }
}