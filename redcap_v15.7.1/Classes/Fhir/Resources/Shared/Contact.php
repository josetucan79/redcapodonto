<?php
namespace Vanderbilt\REDCap\Classes\Fhir\Resources\Shared;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;

class Contact extends AbstractResource
{
    private $contact;
    
    public function __construct($contact)
    {
        $this->contact = $contact;
    }
    
    public function getRelationship()
    {
        $text = $this->contact
            ->relationship
            ->text
            ->join('');
        if(!empty($text)) return $text;
        return $this->contact
            ->relationship
            ->coding
            ->display
            ->join('');
    }
    
    public function getRelationshipCode()
    {
        return $this->contact
            ->relationship
            ->coding
            ->code
            ->join('');
    }
    
    public function getName()
    {
        return $this->contact
            ->name
            ->text
            ->join('');
    }
    
    public function getNameGiven()
    {
        return $this->contact
            ->name
            ->given
            ->join(' ');
    }
    
    public function getNameFamily()
    {
        return $this->contact
            ->name
            ->family
            ->join('');
    }
    
    public function getPhone($use='home|work')
    {
        return $this->contact
            ->telecom
            ->where('system', '=', 'phone')
            ->where('use', '~', $use)
            ->value
            ->join('');
    }
    
    public function getOrganization()
    {
        return $this->contact
            ->organization
            ->display
            ->join('');
    }
    
    public function getAddressLine()
    {
        return $this->contact
            ->address
            ->line
            ->join(' ');
    }
    
    public function getAddressCity()
    {
        return $this->contact
            ->address
            ->city
            ->join('');
    }
    
    public function getAddressState()
    {
        return $this->contact
            ->address
            ->state
            ->join('');
    }
    
    public function getAddressPostalCode()
    {
        return $this->contact
            ->address
            ->postalCode
            ->join('');
    }
    
    public function getAddressCountry()
    {
        return $this->contact
            ->address
            ->country
            ->join('');
    }
    
    public function getPeriodEnd()
    {
        return $this->contact
            ->period
            ->end
            ->join('');
    }

    /**
     * Returns an array mapping property keys to extractor callables.
     * Each callable accepts a Contact resource as parameter.
     *
     * @return array
     */
    public static function getPropertyExtractors(): array
    {
        return [
            'relationship'         => fn(self $resource) => $resource->getRelationship(),
            'relationship-code'    => fn(self $resource) => $resource->getRelationshipCode(),
            'name'                 => fn(self $resource) => $resource->getName(),
            'name-given'           => fn(self $resource) => $resource->getNameGiven(),
            'name-family'          => fn(self $resource) => $resource->getNameFamily(),
            'phone'                => fn(self $resource) => $resource->getPhone(),
            'organization'         => fn(self $resource) => $resource->getOrganization(),
            'address-line'         => fn(self $resource) => $resource->getAddressLine(),
            'address-city'         => fn(self $resource) => $resource->getAddressCity(),
            'address-state'        => fn(self $resource) => $resource->getAddressState(),
            'address-postalCode'   => fn(self $resource) => $resource->getAddressPostalCode(),
            'address-country'      => fn(self $resource) => $resource->getAddressCountry(),
            'period-end'           => fn(self $resource) => $resource->getPeriodEnd(),
        ];
    }
}