<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\PropertyRegistry\PropertySetInterface;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Appointment extends AbstractResource implements PropertySetInterface
{

  use CanNormalizeTimestamp;

  const TIMESTAMP_FORMAT = 'Y-m-d H:i';

  public function getFhirID()
  {
    return $this->scraper()->id->join('');
  }

  /**
   * Undocumented function
   *
   * @return string proposed | pending | booked | arrived | fulfilled | cancelled | noshow | entered-in-error | checked-in | waitlist
   */
  public function getStatus()
  {
    return $this->scraper()->status->join('');
  }

  public function getMinutesDuration()
  {
    return $this->scraper()->minutesDuration->join('');
  }

  public function getCreated()
  {
    return $this->scraper()->created->join('');
  }

  function getNormalizedCreated() {
    $timestamp = $this->getCreated();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getStart()
  {
    return $this->scraper()->start->join('');
  }
  
  function getNormalizedStart() {
    $timestamp = $this->getStart();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getEnd()
  {
    return $this->scraper()->end->join('');
  }
  
  function getNormalizedEnd() {
    $timestamp = $this->getEnd();
    return $this->getGmtTimestamp($timestamp, self::TIMESTAMP_FORMAT);
  }

  public function getCancellationDate()
  {
    return $this->scraper()->cancellationDate->join('');
  }

  public function getCancellationReason()
  {
    return $this->scraper()->cancellationReason->getData();
  }

  public function getCancellationReasonText()
  {
    return $this->scraper()->cancellationReason->text->join('');
  }

  public function getDescription()
  {
    return $this->scraper()->description->join('');
  }

  public function getNoteTime($index=0)
  {
    return $this->scraper()
      ->note[$index]->time->join('');
  }

  public function getNoteText($index=0)
  {
    return $this->scraper()
      ->note[$index]->text->join('');
  }

  public function getAppointmentType($index=0) {
    return $this->scraper()
      ->appointmentType->coding[$index]->display->join('');
  }

  public function getServiceType($index=0) {
    return $this->scraper()
      ->serviceType->coding[$index]->display->join('');
  }

  public function getActor($index=0) {
    return $this->scraper()
      ->participant[$index]->actor->display->join('');
  }

  public function getPractitionerDisplay() {
    return $this->scraper()
      ->participant->actor
      ->where('reference', 'like', '/practitioner/i')
      ->display->join('');
  }

  public function getLocationDisplay() {
    return $this->scraper()
      ->participant->actor
      ->where('reference', 'like', '/location/i')
      ->display->join('');
  }

  public function getPatientInstruction() {
    return $this->scraper()
      ->patientInstruction->join('');
  }

  /**
   * Retrieves the data for the appointment by applying property extractors.
   *
   * This method uses a set of property extractors to gather data from the appointment object.
   * Certain keys are skipped during the extraction process, as defined in the `$excludedKeys` array.
   *
   * @return array The extracted data for the appointment.
   */
  public function getData(): array
  {
    $exceptions = ['actor_1', 'actor_2', 'actor_3'];
    return $this->getDataExcept($exceptions);
    
  }

   /**
   * Returns an array mapping property keys to extractor callables.
   * Each callable accepts an AllergyIntolerance resource as parameter.
   *
   * @return array
   */
  public static function getPropertyExtractors(): array
  {
   return [
      'fhir_id'                   => fn(self $resource) => $resource->getFhirID(),
      'status'                    => fn(self $resource) => $resource->getStatus(),
      'created'                   => fn(self $resource) => $resource->getCreated(),
      'start'                     => fn(self $resource) => $resource->getStart(),
      'end'                       => fn(self $resource) => $resource->getEnd(),
      'normalized_created'        => fn(self $resource) => $resource->getNormalizedCreated(),
      'normalized_start'          => fn(self $resource) => $resource->getNormalizedStart(),
      'normalized_end'            => fn(self $resource) => $resource->getNormalizedEnd(),
      'minutes_duration'          => fn(self $resource) => $resource->getMinutesDuration(),
      'cancellation_date'         => fn(self $resource) => $resource->getCancellationDate(),
      'practitioner'              => fn(self $resource) => $resource->getPractitionerDisplay(),
      'location'                  => fn(self $resource) => $resource->getLocationDisplay(),
      'description'               => fn(self $resource) => $resource->getDescription(),
      'patient_instruction'       => fn(self $resource) => $resource->getPatientInstruction(),
      'note_time_1'               => fn(self $resource) => $resource->getNoteTime(0),
      'note_text_1'               => fn(self $resource) => $resource->getNoteText(0),
      'appointment_type_1'        => fn(self $resource) => $resource->getAppointmentType(0),
      'service_type_1'            => fn(self $resource) => $resource->getServiceType(0),
      'actor_1'                   => fn(self $resource) => $resource->getActor(0),
      'actor_2'                   => fn(self $resource) => $resource->getActor(1),
      'actor_3'                   => fn(self $resource) => $resource->getActor(2),
      'cancellation_reason'       => fn(self $resource) => $resource->getCancellationReason(),
      'cancellation_reason_text'  => fn(self $resource) => $resource->getCancellationReasonText(),
   ];
  }
  
}