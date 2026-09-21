<?php

namespace Vanderbilt\REDCap\Classes\Fhir;

use Exception;
use Vanderbilt\REDCap\Classes\DTOs\DTO;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\FhirRequest;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;

final class FhirClientResponse extends DTO {
    /**
     * status for data fetched without HTTP errors
     */
    const STATUS_OK = 200;

    /**
     * @var integer
     */
    private $status = self::STATUS_OK;

    /**
     * @var FhirTokenDTO
     */
    private $token;

    /**
     * @var FhirRequest
     */
    private $request;

    /**
     * @var integer
     */
    private $user_id;

    /**
     * @var string
     */
    private $access_token;

    /**
     * @var integer
     */
    private $project_id;

    /**
     * @var array
     */
    private $mapping = [];

    /**
     * @var string
     */
    private $timestamp;

    /**
     * @var array
     */
    private $entries = [];

    /**
     * @var string
     */
    private $fhir_id;

    /**
     * @var string
     */
    private $resource_type;

    /**
     * @var AbstractResource
     */
    private $resource = null;

    /**
     * @var string
     */
    private $mrn;

    /**
     * @var string
     */
    private $patient_id;

    /**
     * @var Exception
     */
    private $error = null;

    /**
     *
     * @var FhirSystem
     */
    private $fhir_system;

    /**
     *
     * @param FhirSystem $fhir_system
     * @param FhirRequest $request
     * @param int $project_id
     * @param int $user_id
     */
    public function __construct($data = [])
    {
        parent::__construct($data);
        $this->updateTimestamp();
    }

    public function getMrn(): ?string { return $this->mrn; }
    public function getStatus(): string { return $this->status; }
    public function getToken(): ?FhirTokenDTO { return $this->token; }
    public function getRequest(): ?FhirRequest { return $this->request; }
    public function getUserId(): ?int { return $this->user_id; }
    public function getAccessToken(): ?string { return $this->access_token; }
    public function getProjectId(): ?int { return $this->project_id; }
    public function getMapping(): array { return $this->mapping; }
    public function getTimestamp(): ?string { return $this->timestamp; }
    public function getEntries(): array { return $this->entries; }
    public function getFhirId(): ?string { return $this->fhir_id; }
    public function getResourceType(): ?string { return $this->resource_type; }
    public function getResource(): ?AbstractResource { return $this->resource; }
    public function getPatientId(): ?string { return $this->patient_id; }
    public function getError(): ?Exception { return $this->error; }
    public function getFhirSystem(): ?FhirSystem { return $this->fhir_system; }
    public function hasError(): bool { return $this->error instanceof Exception; }

    public function setStatus(?string $value): void { $this->status = $value; }
    // public function setAccessToken(?string $value): void { $this->access_token = $value; }
    public function setProjectId(?int $value): void { $this->project_id = $value; }
    public function setMapping(array $value): void { $this->mapping = $value; }
    public function setTimestamp(?string $value): void { $this->timestamp = $value; }
    public function setEntries(?array $value): void { $this->entries = $value ?? []; }
    public function setFhirId(?string $value): void { $this->fhir_id = $value; }
    public function setUserID(?string $value) { $this->user_id = $value; }
    public function setPatientId(?string $value) { $this->patient_id = $value; }
    // public function setResourceType(?string $value) { $this->resource_type = $value; }
    public function setResource(?AbstractResource $resource): void { $this->resource = $resource; }
    public function setMrn(?string $mrn): void { $this->mrn = strval($mrn); }
    public function setError(?Exception $error): void { $this->error = $error; }


    /**
     * Sets the FHIR request and updates the resource type if the FHIR system is available.
     *
     * @param FhirRequest $request
     * @return void
     */
    public function setRequest(FhirRequest $request): void
    {
        if ($request instanceof FhirRequest) {
            $this->request = $request;
            $this->fhir_id = $this->request->extractIdentifier();
            // Try updating resource type if the FHIR system is already set.
            $this->updateResourceType();
        } else {
            $this->request = $this->fhir_id = $this->resource_type = null;
        }
    }

    /**
     * Sets the FHIR system and updates the resource type if the request is available.
     *
     * @param FhirSystem|null $fhirSystem
     * @return void
     */
    public function setFhirSystem(?FhirSystem $fhirSystem): void
    {
        $this->fhir_system = $fhirSystem;
        // Try updating resource type if the request is already set.
        $this->updateResourceType();
    }

    /**
     * Updates the resource type if both fhir_system and request are available.
     */
    private function updateResourceType(): void
    {
        if ($this->fhir_system instanceof FhirSystem && $this->request instanceof FhirRequest) {
            
            $baseUrl = $this->fhir_system->getFhirBaseUrl();
            $resourceType = $this->request->getResourceName($baseUrl);
            $this->resource_type = $resourceType ? $resourceType : $this->request->getURL();
        }
    }

    /**
     *
     * @param FhirTokenDTO $token
     * @param string $user_id
     * @return void
     */
    public function setToken(FhirTokenDTO $token)
    {
        if($token instanceof FhirTokenDTO) {
            $this->token = $token;
            $this->user_id = $token->getTokenOwner(); // override the user IDand use the one associated with the token
            $this->access_token = $token->getAccessToken();
        }else {
            $this->token = $this->access_token = null;
        }
    }

    public function updateTimestamp()
    {
        $this->timestamp = date_create()->format('Y-m-d H:i');
    }
}