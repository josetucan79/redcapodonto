<?php
namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors;

use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManager;

class TokenSelectionContext
{

    /**
     *
     * @var int|null
     */
    private $projectId;
    
    /**
     *
     * @var string|null
     */
    private $patientId;

    /**
     * @var array
     */
    private $users = [];

    /**
     * @var FhirTokenDTO[]
     */
    private $tokens = [];

    /**
     * Constructor.
     *
     * @param FhirTokenManager $tokenManager
     * @param array $users
     */
    public function __construct($projectId, $users, $tokens=[], $patientId=null)
    {
        $this->projectId = $projectId;
        $this->users = $users;
        $this->tokens = $tokens;
        $this->patientId = $patientId;
    }

    public function getProjectId(): ?int { return $this->projectId; }
    public function getPatientId(): ?string { return $this->patientId; }
    public function getUsers(): array { return $this->users; }
    /** @return FhirTokenDTO[] */
    public function getTokens(): array { return $this->tokens; }
    
    public function setProjectId($projectId): void { $this->projectId = $projectId; }
    public function setPatientId($patientId): void { $this->patientId = $patientId; }
    public function setUsers(array $users): void { $this->users = $users; }
    public function setTokens(array $tokens): void { $this->tokens = $tokens; }
}
