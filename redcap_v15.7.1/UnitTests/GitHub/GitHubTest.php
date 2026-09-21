<?php

use PHPUnit\Framework\TestCase;
use ExternalModules\ExternalModules;
use Vanderbilt\REDCap\Classes\Cache\CacheManager;

class GitHubTest extends TestCase
{

	public function testCreation() {
		$path = ExternalModules::getPHPUnitPath();
		$this->assertTrue(true);
	}

	
	
}

