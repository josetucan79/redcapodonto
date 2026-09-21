<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Cache\CacheFactory;
use Vanderbilt\REDCap\Classes\Cache\States\DisabledState;

class LoggerTest extends TestCase
{
	private $project_id=1;

	public function setUp(): void
	{
		$_SERVER['REQUEST_URI'] = 'somepage';
		// needed for the logger to work properly
		$cacheManager = CacheFactory::manager($this->project_id);
        $cacheManager->cache()->reset();
		$logger = CacheFactory::logger($this->project_id);
	}

	public function testManagerHasNoCacheMiss() {
		$cacheManager = CacheFactory::manager($this->project_id);
		$hasMissed = $cacheManager->hasCacheMiss();
		$state = $cacheManager->state();
		if($state instanceof DisabledState) $this->assertTrue($hasMissed);
		else $this->assertFalse($hasMissed);
	}

	
	public function testCanCacheData() {
		$cacheManager = CacheFactory::manager($this->project_id);
		$cacheManager->getOrSet([Records::class, 'getData'], [$this->project_id]);
		$hasMissed = $cacheManager->hasCacheMiss();
		$this->assertTrue($hasMissed);
	}

	public function testLogger() {
		$logger = CacheFactory::logger($this->project_id);
		$lastCachetime = $logger->getLastCacheTimeForProject($this->project_id);
		$this->assertIsString($lastCachetime);

	}

	
	
}

