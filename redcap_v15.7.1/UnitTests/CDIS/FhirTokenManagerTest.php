<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenManagerFactory;

class FhirTokenManagerTest extends TestCase
{

	const PATIENT_ID = '1234567890test';
	const ACCESS_TOKEN = '1234567890testaccesstoken123';

	public function testRemoveCachedPatient() {
		$fhirSystem = FhirSystem::getDefault();
		if(!$fhirSystem) return $this->assertTrue(true, 'No FHIR system available; ending test.');
		$ehrID = $fhirSystem->getEhrId();
		$insertQuery = "INSERT INTO `redcap_ehr_access_tokens` (`patient`, `access_token`, `ehr_id`) VALUES (?, ?, ?)";
		$result = db_query($insertQuery, [self::PATIENT_ID, self::ACCESS_TOKEN, $ehrID]);

		$tokenManager = FhirTokenManagerFactory::create($fhirSystem);
		$removed = $tokenManager->removeCachedPatient(self::PATIENT_ID);
		$this->assertTrue($removed);
	}

	public function testDeleteAccessToken() {
		$fhirSystem = FhirSystem::getDefault();
		if(!$fhirSystem) return $this->assertTrue(true, 'No FHIR system available; ending test.');
		$ehrID = $fhirSystem->getEhrId();
		$insertQuery = "INSERT INTO `redcap_ehr_access_tokens` (`patient`, `access_token`, `ehr_id`) VALUES (?, ?, ?)";
		$result = db_query($insertQuery, [self::PATIENT_ID, self::ACCESS_TOKEN, $ehrID]);

		$tokenManager = FhirTokenManagerFactory::create($fhirSystem);
		$removed = $tokenManager->deleteAccessToken(self::ACCESS_TOKEN);
		$this->assertTrue($removed);
	}

	public function testCanRefreshToken() {
		$fhirSystem = FhirSystem::getDefault();
		if(!$fhirSystem) return $this->assertTrue(true, 'No FHIR system available; ending test.');
		$userid = 2;
		$tokenManager = FhirTokenManagerFactory::create($fhirSystem, $userid, $project_id=999);
		$token = $tokenManager->getToken();
		if(!$token) return $this->assertTrue(true, 'No token available; ending test.');

		$access_token = $token->getAccessToken();
		$expires_in = $token->getExpiration();
		$this->assertIsString($access_token);
		$this->assertTrue($expires_in instanceof DateTime);
	}
	
}

