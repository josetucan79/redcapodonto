<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Cache\CacheFactory;
use Vanderbilt\REDCap\Classes\Cache\CacheManager;
use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Utility\StorageInfo;

class RapidRetrievalTest extends TestCase
{
	public function setUp(): void
	{
	}

	public function testCreation() {
		$cacheManager = CacheFactory::manager($project_id=1);
		$this->assertTrue($cacheManager instanceof CacheManager);
	}

	public function testUsedStorageIsNumeric() {
		$config = REDCapConfigDTO::fromDB();
		$cacheDir = $config->cache_files_filesystem_path;
		$cacheDir = empty($cacheDir) ? APP_PATH_TEMP : $cacheDir;

		$usedPercentage = StorageInfo::getUsagePercent($cacheDir);
		$this->assertIsNumeric($usedPercentage);
	}

	public function testCanCheckStorageHealth() {
		$config = REDCapConfigDTO::fromDB();
		$cacheDir = $config->cache_files_filesystem_path;
		$cacheDir = empty($cacheDir) ? APP_PATH_TEMP : $cacheDir;

		$healthy = StorageInfo::isStatusHealthy($cacheDir);
		$this->assertIsBool($healthy);
	}
	
}

