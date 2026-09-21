<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\R4;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\R4\Appointment;

class AppointmentDecorator extends PropertyDecorator
{
  public function dataFunctions(): array
  {
    return [
      'fhir_id'                   => function(Appointment $resource) { return $resource->getFhirID(); },
      'status'                    => function(Appointment $resource) { return $resource->getStatus(); },
      'created'                   => function(Appointment $resource) { return $resource->getCreated(); },
      'start'                     => function(Appointment $resource) { return $resource->getStart(); },
      'end'                       => function(Appointment $resource) { return $resource->getEnd(); },
      'normalized_created'        => function(Appointment $resource) { return $resource->getNormalizedCreated(); },
      'normalized_start'          => function(Appointment $resource) { return $resource->getNormalizedStart(); },
      'normalized_end'            => function(Appointment $resource) { return $resource->getNormalizedEnd(); },
      'minutes_duration'          => function(Appointment $resource) { return $resource->getMinutesDuration(); },
      'cancellation_date'         => function(Appointment $resource) { return $resource->getCancellationDate(); },
      'practitioner'              => function(Appointment $resource) { return $resource->getPractitionerDisplay(); },
      'location'                  => function(Appointment $resource) { return $resource->getLocationDisplay(); },
      'description'               => function(Appointment $resource) { return $resource->getDescription(); },
      'patient_instruction'       => function(Appointment $resource) { return $resource->getPatientInstruction(); },
      'note_time_1'               => function(Appointment $resource) { return $resource->getNoteTime(0); },
      'note_text_1'               => function(Appointment $resource) { return $resource->getNoteText(0); },
      'appointment_type_1'        => function(Appointment $resource) { return $resource->getAppointmentType(0); },
      'service_type_1'            => function(Appointment $resource) { return $resource->getServiceType(0); },
      'cancellation_reason'       => function(Appointment $resource) { return $resource->getCancellationReason(); },
      'cancellation_reason_text'  => function(Appointment $resource) { return $resource->getCancellationReasonText(); },
    ];
  }
  
}