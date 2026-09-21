<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\DTOs;

use Vanderbilt\REDCap\Classes\DTOs\DTO;

/**
 * some relevant data from the FHIR conformance statement
 */
final class ConformanceStatementDTO extends DTO {

    /**
     * list of known FHIR providers
     */
    const PUBLISHER_CERNER = ['cerner', 'Oracle Health'];
    const PUBLISHER_EPIC = ['epic'];
    
    /**
     *
     * @var string
     */
    public $resourceType;

    /**
     *
     * @var string
     */
    public $url;

    /**
     *
     * @var string
     */
    public $name;

    /**
     *
     * @var string
     */
    public $title;

    /**
     *
     * @var string
     */
    public $status;

    /**
     *
     * @var string
     */
    public $date;

    /**
     *
     * @var string
     */
    public $publisher;

    /**
     *
     * @var array
     */
    public $software;

    /**
     *
     * @var string
     */
    public $description;

    /**
     *
     * @var string
     */
    public $kind;

    /**
     *
     * @var string
     */
    public $fhirVersion;

    public function getPublisher() {
        if($this->publisher) return $this->publisher; //cerner
        else if($softwareName = $this->software['name'] ?? null) return $softwareName; //epic
    }

}