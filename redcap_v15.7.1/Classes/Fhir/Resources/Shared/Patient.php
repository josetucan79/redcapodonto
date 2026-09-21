<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\Resources\Traits\CanNormalizeTimestamp;

class Patient extends AbstractResource
{

  use CanNormalizeTimestamp;

  public function getFhirID()
  {
    return strval($this->scraper()->id); // strval equals calling ->join(' ')
  }

  public function getIdentifier($system='')
  {
    return $this->scraper()
      ->identifier
      ->where('system', '=', $system)
      ->value
      ->join(' ');
  }

  public function getIdentifiers()
  {
    return $this->scraper()
      ->identifier
      ->getData();
  }
  
  public function getNameGiven($index=0)
  {
    return $this->scraper()
      ->name
      ->where('use', '=', 'official')
      ->given
      ->join(' ');
  }
  
  public function getNameFamily($index=0)
  {
    return $this->scraper()
      ->name
      ->where('use', '=', 'official')
      ->family
      ->join(' ');
  }
  
  public function getBirthDate()
  {
    return $this->scraper()
      ->birthDate
      ->join(' ');
  }
  
  public function getGenderCode()
  {
    $valueCodeableConcept = $this->scraper()
      ->extension
      ->where('url', 'like', 'birth-?sex$')
      ->valueCodeableConcept
      ->coding->code->join();
    
    if(!empty($valueCodeableConcept)) return $valueCodeableConcept;

    $valueCode = $this->scraper()
      ->extension
      ->where('url', 'like', 'birth-?sex$')
      ->valueCode->join(' ');

    return $valueCode;
  }

  public function getGenderText()
  {
    $valueCodeableConcept = $this->scraper()
      ->extension
      ->where('url', 'like', 'birth-?sex$')
      ->valueCodeableConcept
      ->coding->display->join();
    if(!(empty($valueCodeableConcept))) return $valueCodeableConcept;

    $gender = $this->scraper()
      ->gender
      ->join(' ');
      return $gender;
  }

  public function getGender()
  {
    $getCode = function($value) {
      if(empty($value)) return '';
      $gender_mapping = [
        'female' => 'F',
        'male' => 'M',
        'f' => 'F',
        'm' => 'M',
        'unknown' => 'UNK',
        'unk' => 'UNK',
      ];
      $code = $gender_mapping[strtolower($value)] ?? 'UNK';
      return $code;
    };
    $genderCode = $this->getGenderCode();
    if($genderCode) return $genderCode;
    $genderText = $this->getGenderText();
    $code = $getCode($genderText);
    return $code;
  }

  public function getLegalSex() {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'legal-sex$')
      ->valueCodeableConcept
      ->coding->display->join();
  }

  public function getSexForClinicalUse() {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'sex-for-clinical-use$')
      ->valueCodeableConcept
      ->coding->display->join();
  }
  
  public function getRaceCode($index=0)
  {
    $data = $this->scraper()
      ->extension
      ->where('url', 'like', 'race$')
      ->any('=', 'code'); // select any 'code' child
    return $data[$index]->join(''); // only get one race
  }

  public function getEthnicityCode()
  {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'ethnicity$')
      ->any('=', 'code')->join('');
  }
  
  public function getAddressLine()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->line->join(' ');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->line->join(' ');
    }
    return $address;
  }

  public function getAddressDistrict()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->district->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->district->join('');
    }
    return $address;
  }
  
  public function getAddressCity()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->city->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->city->join('');
    }
    return $address;
  }
  
  public function getAddressState()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->state->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->state->join('');
    }
    return $address;
  }
  
  public function getAddressPostalCode()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->postalCode->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->postalCode->join('');
    }
    return $address;
  }
  
  public function getAddressCountry()
  {
    $address = $this->scraper()
      ->address
      ->where('use', '=', 'home')
      ->country->join('');
    if (!$address) {
      $address= $this->scraper()
        ->address
        ->country->join('');
    }
    return $address;
  }
  
  public function getPhoneHome($index=0)
  {
    return $this->scraper()
      ->telecom
      ->where('system', '=', 'phone')
      ->where('use', '=', 'home')
      ->value[$index]->join('');
  }
  
  public function getPhoneMobile($index=0)
  {
    return $this->scraper()
      ->telecom
      ->where('system', '=', 'phone')
      ->where('use', '=', 'mobile')
      ->value[$index]->join('');
  }
  
  /**
   * return 0 or 1, as expected by a radio in REDCap
   *
   * @return 0|1
   */
  public function isDeceased() 
  {
    $deceasedDateTime = $this->getDeceasedDateTime();
    if(!empty($deceasedDateTime)) return 1;
    return $this->getDeceasedBoolean();
  }

  /**
   * return 0 or 1, as expected by a radio in REDCap
   *
   * @return 0|1
   */
  public function getDeceasedBoolean()
  {
    $deceased = $this->scraper()->deceasedBoolean->join('');
    $booleanValue = filter_var($deceased, FILTER_VALIDATE_BOOLEAN);
    return $booleanValue ? 1 : 0;
  }
  
  public function getDeceasedDateTimeGMT()
  {
    return $this->scraper()->deceasedDateTime->join('');
  }

  function normalizedDeceasedTimestamp() {
    $date = $this->getDeceasedDateTimeGMT();
    return $this->formatTimestamp($date);
  }

  function getDeceasedDateTime() {
    $date = $this->getDeceasedDateTimeGMT();
    return $this->formatTimestamp($date, ['local'=>true]);
  }
  
  public function getPreferredLanguage()
  {
    return $this->scraper()
      ->communication
      ->where('preferred', '=', true)
      ->language->text->join('');
  }
  
  public function getEmail($index=0)
  {
    return $this->scraper()
      ->telecom
      ->where('system', '=', 'email')
      ->value[$index]->join('');
  }

  /**
   * list of general practitioners, semicolon separated
   *
   * @return string
   */
  public function getGeneralPractitioner() {
    return $this->scraper()->generalPractitioner->display->join('; ');
  }

  public function getManagingOrganization()
  {
    return $this->scraper()->managingOrganization->display->join('');
  }

  public function getPronounsCode()
  {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'pronouns-to-use-for-text$')
      ->any('=', 'code')->join('');
  }

  public function getPronouns()
  {
    return $this->scraper()
      ->extension
      ->where('url', 'like', 'pronouns-to-use-for-text$')
      ->valueCodeableConcept
      ->coding->display->join();
  }

  public function getMaritalStatus() {
    return $this->scraper()
      ->maritalStatus
      ->text->join('');
  }

  /**
   * Get a specific contact by index
   *
   * @param int $index The contact index
   * @return Contact
   */
  public function getContact($index = 0)
  {
    $contact = $this->scraper()->contact[$index];
    return new Contact($contact);
  }

  public function getData(): array
  {
      $exceptions = ['pronouns-code'];
      return $this->getDataExcept($exceptions);
  }

  public static function getPropertyExtractors(): array {
    return [
      'fhir-id'                 => fn(Patient $resource) => $resource->getFhirID(),
      'name-given'              => fn(Patient $resource) => $resource->getNameGiven(),
      'name-family'             => fn(Patient $resource) => $resource->getNameFamily(),
      'birthDate'               => fn(Patient $resource) => $resource->getBirthDate(),
      'gender'                  => fn(Patient $resource) => $resource->getGender(),
      'gender-code'             => fn(Patient $resource) => $resource->getGenderCode(),
      'gender-text'             => fn(Patient $resource) => $resource->getGenderText(),
      'legal-sex'               => fn(Patient $resource) => $resource->getLegalSex(),
      'sex-for-clinical-use'    => fn(Patient $resource) => $resource->getSexForClinicalUse(),
      'race'                    => fn(Patient $resource) => $resource->getRaceCode(0),
      'ethnicity'               => fn(Patient $resource) => $resource->getEthnicityCode(),
      'address-line'            => fn(Patient $resource) => $resource->getAddressLine(),
      'address-district'        => fn(Patient $resource) => $resource->getAddressDistrict(),
      'address-city'            => fn(Patient $resource) => $resource->getAddressCity(),
      'address-state'           => fn(Patient $resource) => $resource->getAddressState(),
      'address-postalCode'      => fn(Patient $resource) => $resource->getAddressPostalCode(),
      'address-country'         => fn(Patient $resource) => $resource->getAddressCountry(),
      'phone-home'              => fn(Patient $resource) => $resource->getPhoneHome(),
      'phone-home-2'            => fn(Patient $resource) => $resource->getPhoneHome(1),
      'phone-home-3'            => fn(Patient $resource) => $resource->getPhoneHome(2),
      'phone-mobile'            => fn(Patient $resource) => $resource->getPhoneMobile(),
      'phone-mobile-2'          => fn(Patient $resource) => $resource->getPhoneMobile(1),
      'phone-mobile-3'          => fn(Patient $resource) => $resource->getPhoneMobile(2),
      'general-practitioner'    => fn(Patient $resource) => $resource->getGeneralPractitioner(),
      'managing-organization'   => fn(Patient $resource) => $resource->getManagingOrganization(),
      'deceasedBoolean'         => fn(Patient $resource) => $resource->isDeceased(),
      'deceasedDateTime'        => fn(Patient $resource) => $resource->getDeceasedDateTime(),
      'preferred-language'      => fn(Patient $resource) => $resource->getPreferredLanguage(),
      'email'                   => fn(Patient $resource) => $resource->getEmail(),
      'email-2'                 => fn(Patient $resource) => $resource->getEmail(1),
      'email-3'                 => fn(Patient $resource) => $resource->getEmail(2),
      'pronouns'                => fn(Patient $resource) => $resource->getPronouns(),
      'pronouns-code'           => fn(Patient $resource) => $resource->getPronounsCode(),
      'marital-status'          => fn(Patient $resource) => $resource->getMaritalStatus(),
      'contact-relationship-1'  => fn(Patient $resource) => $resource->getContact(0)->getRelationship(),
      'contact-name-1'          => fn(Patient $resource) => $resource->getContact(0)->getName(),
      'contact-phone-1'         => fn(Patient $resource) => $resource->getContact(0)->getPhone(),
      'contact-relationship-2'  => fn(Patient $resource) => $resource->getContact(1)->getRelationship(),
      'contact-name-2'          => fn(Patient $resource) => $resource->getContact(1)->getName(),
      'contact-phone-2'         => fn(Patient $resource) => $resource->getContact(1)->getPhone(),
      'contact-relationship-3'  => fn(Patient $resource) => $resource->getContact(2)->getRelationship(),
      'contact-name-3'          => fn(Patient $resource) => $resource->getContact(2)->getName(),
      'contact-phone-3'         => fn(Patient $resource) => $resource->getContact(2)->getPhone(),
    ];
  }
  
}