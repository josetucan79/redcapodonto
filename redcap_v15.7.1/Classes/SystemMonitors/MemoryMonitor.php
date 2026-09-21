<?php
namespace Vanderbilt\REDCap\Classes\SystemMonitors;

class MemoryMonitor {
    const DEFAULT_THRESHOLD = 0.75;
    
    private $threshold = 0.75;
    private static $increments = [];

    public function __construct($threshold=self::DEFAULT_THRESHOLD)
    {
        $this->threshold = $threshold;
    }

    public function setMemoryThreshold($threshold)
    {
        $this->threshold = $threshold;
    }

    public static function getMemoryIncrements()
    {
        return self::$increments;
    }

    public function increaseMemoryIfNeeded($memoryMultiplier = 2)
    {
        $currentMemoryUsage = memory_get_usage(true);
        $peakMemoryUsage = memory_get_peak_usage(true);
        $memoryLimit = $this->getMemoryInBytes(ini_get('memory_limit'));

        if ($currentMemoryUsage / $memoryLimit >= $this->threshold) {
            $newMemoryLimit = $memoryLimit * $memoryMultiplier;
            self::$increments[] = $newMemoryLimit;
            $newMemoryLimitFormatted = $this->formatBytes($newMemoryLimit);
            ini_set('memory_limit', $newMemoryLimitFormatted);
            echo 'Increased memory limit to ' . $newMemoryLimitFormatted . PHP_EOL;
        }
    }

    public function isMemoryStatusHealthy()
    {
        $currentMemoryUsage = memory_get_usage(true);
        $peakMemoryUsage = memory_get_peak_usage(true);
        $memoryLimit = $this->getMemoryInBytes(ini_get('memory_limit'));
        if ($memoryLimit != 0) {
            $percentageUsed = $currentMemoryUsage / $memoryLimit;
        } else {
            $percentageUsed = 0;
        }

        return $percentageUsed < $this->threshold;
    }

    private function getMemoryInBytes($size)
    {
        $suffix = strtoupper(substr($size, -1));
        $value = (int) substr($size, 0, -1);

        switch ($suffix) {
            case 'T':
                $value *= 1024;
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
        }

        return $value;
    }

    private function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $units = ['', 'K', 'M', 'G', 'T'];

        return round(pow(1024, $base - floor($base)), $precision) .' '. $units[floor($base)];
    }
}
