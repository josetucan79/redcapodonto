<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Mapper;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ClinicalDataPullSeeder;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ClinicalDataPullQueueManager;

class CDPTest extends TestCase
{
	public function setUp(): void
	{
	}


	public function _testHandleSettingsModificationInMapper() {
		if(! defined('USERID')) define('USERID', 'delacqf');
		$mapper = new Mapper(new Project($project_id=20), REDCapConfigDTO::fromDB());
		$mapper->handleSettingModification();
		$this->assertTrue(true);
	}

	public function testSeedCdpRecords() {
        $seeder = new ClinicalDataPullSeeder();
        $seeded = $seeder->seedMrIdsAllProjects();
		$this->assertIsNumeric($seeded);
	}

	/**
	 * force the status to QUEUE for numRecords elements
	 *
	 * @param integer $numRecords
	 * @return integer
	 */
	private function queueLastNRecords($numRecords) {
		$sql = "UPDATE redcap_ddp_records
			SET fetch_status = 'QUEUED'
			ORDER BY mr_id DESC
			LIMIT ?";
		$result = db_query($sql, [$numRecords]);
		return db_affected_rows($result);
	}

	private function countQueuedRecords() {
		$sql = "SELECT count(1) AS total FROM redcap_ddp_records
			WHERE fetch_status = 'QUEUED'";
		$result = db_query($sql);
		if($result && ($row = db_fetch_assoc($result))) {
			return intval($row['total'] ?? 0);
		}
		return 0;
	}

	public function _testCdpQueueManager() {
		$queueManager = new ClinicalDataPullQueueManager();
		$generator = $queueManager->fetchQueuedRecordsFromSource();
		$maxToFetch = 2;

		$totalUpdated = $this->queueLastNRecords($maxToFetch);
		$totalQueued = $this->countQueuedRecords();
		if($totalQueued>$maxToFetch) $totalQueued = $maxToFetch;
		
		$totalFetched = 0;
		foreach ($generator as $value) {
			list($projectID, $mr_id, $fetched) = $value;
			$totalFetched++;
			if($totalFetched >= $maxToFetch) break;
		}
		$this->assertSame($totalQueued, $totalFetched);
	}

	public function _testQueueNewlyCreatedRecord() {
		$project_id = 20;
		$record_id = DataEntry::getAutoId($project_id, true);
		$project = new Project($project_id);
		$event_id = $project->firstEventId;
		$data = [
			$record_id => [
				$event_id => [
					'mrn' => '206921',
				],
				'repeat_instances' => []
			]
		];
		$saveResults = Records::saveData($project_id, 'array', $data);
		$this->assertTrue(true);
	}


	
}

