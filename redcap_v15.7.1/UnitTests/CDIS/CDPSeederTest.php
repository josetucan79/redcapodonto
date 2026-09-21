<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ClinicalDataPullSeeder;

class CDPSeederTest extends TestCase
{
	public function setUp(): void
	{
	}



	public function testGetViableProjects() {
        $seeder = new ClinicalDataPullSeeder();
        $dataTables = $seeder->getViableProjects();
		$this->assertIsArray($dataTables);
	}

	public function testGetUnregisteredRecords() {
        $seeder = new ClinicalDataPullSeeder();
        $dataTables = $seeder->getViableProjects();
		$allRecords = [];
		foreach ($dataTables as $dataTable=>$thesepids) {
			$generator = $seeder->getUnregisteredRecords($dataTable, $thesepids);
			while ($records = $generator->current()) {
				$allRecords[$records['project_id']][] = $records['record'];
				$generator->next();
			}
		}
		$this->assertIsArray($allRecords);
	}

	public function testSeedRecordsForAllProjects() {
		$seeder = new ClinicalDataPullSeeder();
		// Perform the seeding
		$recordsSeeded = $seeder->seedMrIdsAllProjects();
		$this->assertIsNumeric($recordsSeeded);
	}
	
	public function testSetQueuedFetchStatusForAllProjects() {
		$seeder = new ClinicalDataPullSeeder();
		// Perform the seeding
		$numRecordsQueued = $seeder->setQueuedFetchStatusAllProjects();
		$this->assertIsNumeric($numRecordsQueued);
	}


	
}

