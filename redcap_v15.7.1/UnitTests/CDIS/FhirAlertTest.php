<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirAlertManager;

class FhirAlertTest extends TestCase
{
	public function setUp(): void
	{
	}


	public function testSendAlert() {
		if(! defined('USERID')) define('USERID', 'delacqf');
		$manager = new FhirAlertManager(20);
		$result = $manager->sendNoTokenMessage();
		$this->assertTrue(true);
	}
	
}

