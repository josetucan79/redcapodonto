<?php
namespace Vanderbilt\REDCap\Classes\Utility;

class StorageInfo {

    public static function getTotalSpace($directory)
    {
        return disk_total_space($directory);
    }
    
    public static function getFreeSpace($directory)
    {
        return disk_free_space($directory);
    }

    public static function getUsagePercent($directory) {
        $total = static::getTotalSpace($directory);
        $free = static::getFreeSpace($directory);
        if($total===0) return 1; // avoid division by zero
        $used = $total - $free;
        return $used/$total;
    }

    /* public function increaseMemoryIfNeeded($memoryMultiplier = 2)
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
    } */

    public static function isStatusHealthy($directory, $threshold=0.75)
    {
        $usagePercent = static::getUsagePercent($directory);
        return $usagePercent < $threshold;
    }

    public static function getMemoryInBytes($size)
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

    public static function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $units = ['', 'K', 'M', 'G', 'T'];

        return round(pow(1024, $base - floor($base)), $precision) .' '. $units[floor($base)];
    }
}
