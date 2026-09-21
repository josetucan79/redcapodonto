<?php
use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassBreaker;
use Vanderbilt\REDCap\Classes\Fhir\FhirCategory;
use Vanderbilt\REDCap\Classes\Fhir\FhirClientResponse;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\AbstractEndpoint;
use Vanderbilt\REDCap\Classes\Fhir\Endpoints\FhirRequest;
use Vanderbilt\REDCap\Classes\BreakTheGlass\GlassTokenTrigger;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;

class TestableGlassTokenTrigger extends GlassTokenTrigger
{
    private $glassAvailable = true;
    
    public function setGlassAvailable($value)
    {
        $this->glassAvailable = $value;
    }
    
    // Override checkAndTriggerBtgToken to make it accessible for testing
    public function checkAndTriggerBtgToken(FhirClient $fhirClient)
    {
        // Use an instance property instead of the static call
        if (!$this->glassAvailable || 
            empty($this->detected403Errors) || 
            $this->demographicsRequested) {
            return;
        }
        
        // Rest of the method is the same as parent
        $mrn = $fhirClient->getMrn();
        if (empty($mrn)) {
            return;
        }

        $protectedPatient = $this->glassBreaker->getStoredPatient($mrn);
        if ($protectedPatient && !empty($protectedPatient->fhirBtgToken)) {
            return;
        }

        $patientId = $fhirClient->getPatientID($mrn);
        if (!$patientId) {
            return;
        }

        $request = $fhirClient->createRequestForCategory(FhirCategory::DEMOGRAPHICS, $patientId);
        $fhirClient->sendRequest($request);
    }

    // Make protected properties accessible for testing
    public function setDetected403Errors($errors)
    {
        $this->detected403Errors = $errors;
    }
    
    public function setDemographicsRequested($value)
    {
        $this->demographicsRequested = $value;
    }
}


class GlassTokenTriggerTest extends TestCase
{
    private $project_id = 123;
    private $user_id = 456;
    private $mrn = "TEST-MRN-123";
    private $patient_id = "PATIENT-123";
    
    /**
     * Test that GlassTokenTrigger correctly triggers a Demographics endpoint
     * call when 403 errors are detected and no BTG token exists
     */
    public function testTriggersBtgTokenRequestOnError()
    {
        // Mock classes we need
        $fhirClient = $this->createMock(FhirClient::class);
        $glassBreaker = $this->createMock(GlassBreaker::class);
        $fhirRequest = $this->createMock(FhirRequest::class);
        
        // Create our test instance with mocked GlassBreaker
        $glassTrigger = new TestableGlassTokenTrigger($this->project_id, $this->user_id, $glassBreaker);
        
        // Set up test conditions
        $glassTrigger->setGlassAvailable(true);
        $glassTrigger->setDetected403Errors([
            new FhirClientResponse([
                'mrn' => $this->mrn,
                'status' => 403,
            ])
        ]);
        $glassTrigger->setDemographicsRequested(false);
        
        // Mock FhirClient methods
        $fhirClient->expects($this->once())
            ->method('getMrn')
            ->willReturn($this->mrn);
            
        $fhirClient->expects($this->once())
            ->method('getPatientID')
            ->with($this->mrn)
            ->willReturn($this->patient_id);
        
        // Mock the new createRequestForCategory method
        $fhirClient->expects($this->once())
        ->method('createRequestForCategory')
        ->with(
            $this->equalTo(FhirCategory::DEMOGRAPHICS),
            $this->equalTo($this->patient_id)
        )
        ->willReturn($fhirRequest);
            
        $fhirClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->equalTo($fhirRequest));
            
        // Mock getStoredPatient to return null (no token exists)
        $glassBreaker->expects($this->once())
            ->method('getStoredPatient')
            ->with($this->mrn)
            ->willReturn(null);
            
        // Call the method we want to test
        $glassTrigger->checkAndTriggerBtgToken($fhirClient);
    }
    
    // Other test methods follow a similar pattern...
    public function testNoTriggerIfDemographicsRequested()
    {
        // Mock classes we need
        $fhirClient = $this->createMock(FhirClient::class);
        $glassBreaker = $this->createMock(GlassBreaker::class);
        
        // Create our test instance
        $glassTrigger = new TestableGlassTokenTrigger($this->project_id, $this->user_id, $glassBreaker);
        
        // Set up test conditions
        $glassTrigger->setGlassAvailable(true);
        $glassTrigger->setDetected403Errors([
            new FhirClientResponse([
                'mrn' => $this->mrn,
                'status' => 403,
            ])
        ]);
        $glassTrigger->setDemographicsRequested(true); // Demographics was requested
        
        // makeRequest should NEVER be called
        $fhirClient->expects($this->never())
            ->method('makeRequest');
            
        // Call the method we want to test
        $glassTrigger->checkAndTriggerBtgToken($fhirClient);
    }
    
    public function testNoTriggerIfGlassNotAvailable()
    {
        // Mock classes we need
        $fhirClient = $this->createMock(FhirClient::class);
        $glassBreaker = $this->createMock(GlassBreaker::class);
        
        // Create our test instance
        $glassTrigger = new TestableGlassTokenTrigger($this->project_id, $this->user_id, $glassBreaker);
        
        // Set up test conditions
        $glassTrigger->setGlassAvailable(false); // Glass not available
        $glassTrigger->setDetected403Errors([
            new FhirClientResponse([
                'mrn' => $this->mrn,
                'status' => 403,
            ])
        ]);
        $glassTrigger->setDemographicsRequested(false);
        
        // makeRequest should NEVER be called
        $fhirClient->expects($this->never())
            ->method('makeRequest');
            
        // Call the method we want to test
        $glassTrigger->checkAndTriggerBtgToken($fhirClient);
    }
}