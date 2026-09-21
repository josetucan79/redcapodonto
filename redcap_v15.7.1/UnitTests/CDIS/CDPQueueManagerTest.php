<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ClinicalDataPullQueueManager;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ClinicalDataPullSeeder;

class CDPQueueManagerTest extends TestCase
{


	public function testGetProjectsWithQueuedRecords() {
		$manager = new ClinicalDataPullQueueManager();
		$list = $manager->getProjectsWithQueuedRecords();
		print("List of projects with records in QUEUED status:\n");
		var_dump($list);
		$this->assertIsArray($list);
	}

	public function testFetchQueuedRecordsFromSource() {
		$seeder = new ClinicalDataPullSeeder();
		// Perform the seeding
		$recordsSeeded = $seeder->seedMrIdsAllProjects();
		$numRecordsQueued = $seeder->setQueuedFetchStatusAllProjects();
		$manager = new ClinicalDataPullQueueManager();
		$generator = $manager->fetchQueuedRecordsFromSource();
		$fetchedMrIDs = [];
		while(list($this_project_id, $mr_id, $fetched) = $generator->current()) {
			$fetchedMrIDs[$this_project_id][] = [$mr_id => $fetched];
			$generator->next();
		}
		// print("List of fetched MR IDs for each project:\n");
		// var_dump($fetchedMrIDs);
		$this->assertIsArray($fetchedMrIDs);
	}


	
}

