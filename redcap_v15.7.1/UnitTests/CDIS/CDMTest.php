<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Queue\Queue;
use Vanderbilt\REDCap\Classes\Queue\Worker;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMart;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMartBackgroundRunner;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DataMartRevision;
use Vanderbilt\REDCap\Classes\Fhir\FhirUser;

class CDMTest extends TestCase
{
	public function setUp(): void
	{
	}

	public function testNextMrnIsReturned() {
		if(!isDev()) return $this->assertTrue(true, 'this should run in dev only');
		$mrn = null;
		$counter = 0;
		$mrns = [];
		$revision = DataMartRevision::get(5);
		$fhirUser = new FhirUser(2);
		do {
			$mrn = $revision->getNextMrnWithValidDateRange($fhirUser, $mrn);
			$counter++;
			if($mrn) $mrns[] = $mrn;
		} while ($mrn != null);
		$this->assertTrue(true);
	}
}

