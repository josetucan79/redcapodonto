<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Cache\CacheLockManager;
use Vanderbilt\REDCap\Classes\Utility\FileCache\CacheItemsManager;

class CacheLockManagerTest extends TestCase {
    public function testWaitForLockReleasesAfterTimeout() {
		$cacheItemsManager = $this->createMock(CacheItemsManager::class);
		$maxWaitInterval = 2;
        $mockCacheLockManager = $this->getMockBuilder(CacheLockManager::class)
									->setConstructorArgs([$cacheItemsManager, $maxWaitInterval])
									->onlyMethods(['lockIsFree'])
									->getMock();

        // Simulate lockIsFree always returning false to trigger the timeout
        $mockCacheLockManager->method('lockIsFree')->willReturn(false);

        $startTime = microtime(true);
		/** @var CacheLockManager $mockCacheLockManager */
        $result = $mockCacheLockManager->waitForLock('test_key');
        $endTime = microtime(true);

        $elapsedTime = ($endTime - $startTime);
        print "Gave up waiting for lock after $elapsedTime seconds\n";
        $this->assertFalse($result, "Lock should be released after timeout with a false result");
        $this->assertGreaterThanOrEqual($maxWaitInterval, $elapsedTime, "The elapsed time ($elapsedTime) should be slightly greater than or equal to maxWaitInterval ($maxWaitInterval)");
    }

    public function testWaitForLockReleasesWhenLockIsFree() {
        $startTime = microtime(true);
		$cacheItemsManager = $this->createMock(CacheItemsManager::class);
		$maxWaitInterval = 10;

        $mockCacheLockManager = $this->getMockBuilder(CacheLockManager::class)
									->setConstructorArgs([$cacheItemsManager, $maxWaitInterval])
									->onlyMethods(['lockIsFree'])
									->getMock();

        // Simulate lockIsFree always returning false to trigger the timeout
        $mockCacheLockManager->method('lockIsFree')->willReturnCallback(function() use($startTime) {
            $currentTime = microtime(true);
            return $currentTime-$startTime >= 2;
        });

		/** @var CacheLockManager $mockCacheLockManager */
        $result = $mockCacheLockManager->waitForLock('test_key');
        $endTime = microtime(true);

        $elapsedTime = ($endTime - $startTime);
        print "Lock released after $elapsedTime seconds\n";
        $this->assertTrue($result, "Lock should be released after 5 seconds");
        $this->assertLessThan($maxWaitInterval, $elapsedTime, "The elapsed time ($elapsedTime) should be less than maxWaitInterval ($maxWaitInterval)");
    }

}
