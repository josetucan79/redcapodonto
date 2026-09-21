<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\ExpirationSelector;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\PatientSelector;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules\PriorityRulesSelector;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules\RulesManager;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules\TokenRuleDTO;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\TokenSelectionContext;

class FhirTokenSelectionTest extends TestCase
{
	private $projectId;
	private $userId;
	private $username;

	public function setUp(): void
	{
		$this->projectId = 20;
		$this->userId = 2;
		$this->username = 'jdoe';
		if(! defined('USERID')) define('USERID', $this->username);
		if(! defined('UI_ID')) define('UI_ID', $this->userId);
	}

	private function getRulesManager($projectId, $userId, $username) {
		$rules = [
			new TokenRuleDTO([
				'id' => 1,
				'project_id' => $projectId,
				'user_id' => $userId,
				'priority' => 1,
				'allow' => true,
				'created_at' => null,
				'updated_at' =>  null,
				'username' => $username,
				'user_email' => "John@example.com",
				'user_firstname' => "John",
				'user_lastname' => "Doe",
			]),
			new TokenRuleDTO([
				'id' => 2,
				'project_id' => $projectId,
				'user_id' => 3,
				'priority' => 2,
				'allow' => false,
				'created_at' => null,
				'updated_at' =>  null,
				'username' => "luna",
				'user_email' => "luna@example.com",
				'user_firstname' => "Luna",
				'user_lastname' => "Doe",
			]),
		];

		$globalRule = new TokenRuleDTO([
			'id' => 3,
			'project_id' => $projectId,
			'user_id' => null,
			'priority' => 0,
			'allow' => false,
			'created_at' => null,
			'updated_at' =>  null,
		]);

		$rulesManagerMock = $this->createMock(RulesManager::class);

		$rulesManagerMock->method('getRulesByProject')
			// ->with($projectId)
			->willReturn($rules);

		$rulesManagerMock->method('getGlobalRuleForProject')
            // ->with($projectId)
            ->willReturn($globalRule);
		return $rulesManagerMock;
	}

	private function getExpiration($intervalString = '1 hour') {
		$date = new DateTime();
		$date->add(DateInterval::createFromDateString($intervalString));
		$expiration = $date->format('Y-m-d H:i:s');
		return $expiration;
	}

	private function getTokenSelectionContext(): TokenSelectionContext {
		$expectedTokens = [
			new FhirTokenDTO([
				'patient' => 'patient123',
				'mrn' => '444555',
				'token_owner' => 1,
				'expiration' => $this->getExpiration('6 hours'),
				'access_token' => 'acbdfgt',
				'refresh_token' => '123456',
				'ehr_id' => 1,
				'status' => FhirTokenDTO::STATUS_VALID,
			]),
			new FhirTokenDTO([
				'patient' => 'patient456',
				'mrn' => '111222',
				'token_owner' => 2,
				'expiration' => $this->getExpiration('8 hours'),
				'access_token' => 'aasasdadasa',
				'refresh_token' => '678123',
				'ehr_id' => 1,
				'status' => FhirTokenDTO::STATUS_VALID,
			]),
			new FhirTokenDTO([
				'patient' => 'patient987',
				'mrn' => '456890',
				'token_owner' => 3,
				'expiration' => $this->getExpiration('-8 hours'),
				'access_token' => 'asdasdassdasdsadasdassad',
				'refresh_token' => '978078978907890',
				'ehr_id' => 1,
				'status' => FhirTokenDTO::STATUS_VALID,
			]),
		];
		$tokenSelectionContext = new TokenSelectionContext(
			$this->projectId,
			$users = [2,4,5],
			$expectedTokens,
			$patientId = 'patient987'
		);

		return $tokenSelectionContext;
	}


	public function testPriorityRulesSelector() {
		

		$rulesManagerMock = $this->getRulesManager($this->projectId, $this->userId, $this->username);		
		$tokenSelectionContextMock = $this->getTokenSelectionContext();

		/** @var RulesManager $rulesManagerMock */
		$selector = new PriorityRulesSelector($rulesManagerMock);
		$tokens = $selector->selectToken($tokenSelectionContextMock);

		$this->assertEquals($tokens[0]->getTokenOwner(), $this->userId);
		$this->assertEquals($tokens[0]->getStatus(), FhirTokenDTO::STATUS_VALID);
		$this->assertEquals($tokens[1]->getStatus(), FhirTokenDTO::STATUS_FORBIDDEN);
	}

	public function testExpirationSelector() {	
		$tokenSelectionContextMock = $this->getTokenSelectionContext();
		
		/** @var RulesManager $rulesManagerMock */
		$selector = new ExpirationSelector();
		$tokens = $selector->selectToken($tokenSelectionContextMock);

		$this->assertEquals($tokens[0]->getTokenOwner(), $this->userId);
		$this->assertEquals($tokens[0]->getStatus(), FhirTokenDTO::STATUS_VALID);
		$this->assertEquals($tokens[2]->getStatus(), FhirTokenDTO::STATUS_EXPIRED);
	}

	public function testPatientSelector() {	
		$tokenSelectionContextMock = $this->getTokenSelectionContext();
		
		/** @var RulesManager $rulesManagerMock */
		$selector = new PatientSelector();
		$tokens = $selector->selectToken($tokenSelectionContextMock);

		$this->assertEquals($tokens[0]->getTokenOwner(), 3);
	}
	
}

