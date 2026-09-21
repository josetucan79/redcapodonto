<?php
namespace Vanderbilt\REDCap\Classes\SystemMonitors;

class ResourceMonitor {
    private $memoryMonitor;
    private $timeMonitor;

    public function __construct(MemoryMonitor $memoryMonitor, TimeMonitor $timeMonitor)
    {
        $this->memoryMonitor = $memoryMonitor;
        $this->timeMonitor = $timeMonitor;
    }

    /**
     * Static method to create an instance of ResourceMonitor
     *
     * @param array $config Array with 'memory' and 'time' keys
     * @return self
     */
    public static function create(array $config = [])
    {
        // Apply default values if not provided in the array
        $memoryThreshold = $config['memory'] ?? MemoryMonitor::DEFAULT_THRESHOLD;
        $timeThreshold = $config['time'] ?? TimeMonitor::DEFAULT_THRESHOLD;

        // Create MemoryMonitor and TimeMonitor instances
        $memoryMonitor = new MemoryMonitor($memoryThreshold);
        $timeMonitor = new TimeMonitor($timeThreshold);

        return new self($memoryMonitor, $timeMonitor);  // Return new ResourceMonitor instance
    }

    public function checkResources()
    {
        $memoryOk = $this->memoryMonitor->isMemoryStatusHealthy();
        $timeOk = !$this->timeMonitor->hasExceededThreshold();

        return $memoryOk && $timeOk;
    }

    public function getStatus()
    {
        return [
            'memory' => $this->memoryMonitor->isMemoryStatusHealthy() ? 'OK' : 'Exceeded',
            'time' => $this->timeMonitor->hasExceededThreshold() ? 'Exceeded' : 'OK',
        ];
    }

    /**
     *
     * @return MemoryMonitor
     */
    public function getMemoryMonitor() { return $this->memoryMonitor; }

    /**
     *
     * @return TimeMonitor
     */
    public function getTimeMonitor() { return $this->timeMonitor; }
}
