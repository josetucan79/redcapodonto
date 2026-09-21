<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\Shared;

use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator\PropertyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Shared\Patient;

class PatientDecorator extends PropertyDecorator
{
  
  public function dataFunctions(): array {
    return [
      'fhir_id'               => function(Patient $resource) { return $resource->getFhirID(); },
      'name-given'            => function(Patient $resource) { return $resource->getNameGiven(); },
      'name-family'           => function(Patient $resource) { return $resource->getNameFamily(); },
      'birthDate'             => function(Patient $resource) { return $resource->getBirthDate(); },
      'gender'                => function(Patient $resource) { return $resource->getGender(); },
      'gender-code'           => function(Patient $resource) { return $resource->getGenderCode(); },
      'gender-text'           => function(Patient $resource) { return $resource->getGenderText(); },
      'legal-sex'             => function(Patient $resource) { return $resource->getLegalSex(); },
      'sex-for-clinical-use'  => function(Patient $resource) { return $resource->getSexForClinicalUse(); },
      'race'                  => function(Patient $resource) { return $resource->getRaceCode(0); },
      'ethnicity'             => function(Patient $resource) { return $resource->getEthnicityCode(); },
      'address-line'          => function(Patient $resource) { return $resource->getAddressLine(); },
      'address-district'      => function(Patient $resource) { return $resource->getAddressDistrict(); },
      'address-city'          => function(Patient $resource) { return $resource->getAddressCity(); },
      'address-state'         => function(Patient $resource) { return $resource->getAddressState(); },
      'address-postalCode'    => function(Patient $resource) { return $resource->getAddressPostalCode(); },
      'address-country'       => function(Patient $resource) { return $resource->getAddressCountry(); },
      'phone-home'            => function(Patient $resource) { return $resource->getPhoneHome(); },
      'phone-home-2'          => function(Patient $resource) { return $resource->getPhoneHome(1); },
      'phone-home-3'          => function(Patient $resource) { return $resource->getPhoneHome(2); },
      'phone-mobile'          => function(Patient $resource) { return $resource->getPhoneMobile(); },
      'phone-mobile-2'        => function(Patient $resource) { return $resource->getPhoneMobile(1); },
      'phone-mobile-3'        => function(Patient $resource) { return $resource->getPhoneMobile(2); },
      'general-practitioner'  => function(Patient $resource) { return $resource->getGeneralPractitioner(); },
      'managing-organization' => function(Patient $resource) { return $resource->getManagingOrganization(); },
      'deceasedBoolean'       => function(Patient $resource) { return $resource->isDeceased(); },
      'deceasedDateTime'      => function(Patient $resource) { return $resource->getDeceasedDateTime(); },
      'preferred-language'    => function(Patient $resource) { return $resource->getPreferredLanguage(); },
      'email'                 => function(Patient $resource) { return $resource->getEmail(); },
      'email-2'               => function(Patient $resource) { return $resource->getEmail(1); },
      'email-3'               => function(Patient $resource) { return $resource->getEmail(2); },
    ];
  }
  
  
}