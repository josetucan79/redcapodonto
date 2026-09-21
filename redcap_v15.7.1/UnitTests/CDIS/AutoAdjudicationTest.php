<?php

use PHPUnit\Framework\TestCase;

class AutoAdjudicationTest extends TestCase
{

	public function testAutoAdjudication() {
		if(! defined('USERID')) define('USERID', 'delacqf');
		$jobs = new Jobs();
		$jobs->CDPAutoAdjudication();
		$jobs->ProcessQueue();
		$message = $GLOBALS['redcapCronJobReturnMsg'] ?? null;
		$this->assertIsString($message);
	}
}

