<?php
namespace Vanderbilt\REDCap\Classes\Cache\Helpers;

use Throwable;
use Vanderbilt\REDCap\Classes\Cache\Exceptions\SerializationMemoryException;

/**
 * Helper class for memory-safe serialization of large data structures
 */
class MemorySafeSerializer
{
    const MEMORY_SAFETY_THRESHOLD = 0.8; // Use 80% of available memory as threshold
    const CHUNK_SIZE = 8192; // 8KB chunks for incremental processing

    /**
     * Check if we're approaching memory limits
     *
     * @return bool
     */
    private function isApproachingMemoryLimit() {
        $memoryLimit = $this->getMemoryLimitInBytes();
        $currentUsage = memory_get_usage(true);
        
        // If memory limit is unlimited (-1), use peak usage as a guide
        if ($memoryLimit === -1) {
            $peakUsage = memory_get_peak_usage(true);
            return $currentUsage > ($peakUsage * self::MEMORY_SAFETY_THRESHOLD);
        }
        
        return $currentUsage > ($memoryLimit * self::MEMORY_SAFETY_THRESHOLD);
    }

    /**
     * Get memory limit in bytes
     *
     * @return int
     */
    private function getMemoryLimitInBytes() {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit == -1) {
            return -1; // Unlimited
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;
        
        switch ($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }
        
        return $value;
    }

    /**
     * Incrementally serialize data with memory monitoring
     *
     * @param mixed $data
     * @return string
     * @throws SerializationMemoryException
     */
    public function serialize($data) {
        $dataType = gettype($data);

        // Check memory before starting
        if ($this->isApproachingMemoryLimit()) {
            throw new SerializationMemoryException(
                "Memory limit approaching before serialization",
                SerializationMemoryException::REASON_MEMORY_LIMIT,
                $dataType,
                memory_get_usage(true)
            );
        }
        
        // For scalars (numbers, booleans, null), always serialize directly
        if (is_scalar($data) || is_null($data)) {
            return $this->attemptDirectSerialization($data, "scalar data");
        }
        
        // For arrays and strings, use incremental approach
        if (is_array($data)) {
            return $this->incrementalArraySerialization($data);
        }
        
        if (is_string($data)) {
            return $this->incrementalStringSerialization($data);
        }
        
        // For objects and other types, use direct serialization
        $context = is_object($data) ? "object data" : "data";
        return $this->attemptDirectSerialization($data, $context);
    }

    /**
     * Standard unserialize wrapper
     *
     * @param string $data
     * @param array $allowedClasses
     * @return mixed
     * @throws SerializationMemoryException
     */
    public function unserialize($data, $allowedClasses = []) {
        try {
            $result = unserialize($data, ['allowed_classes' => $allowedClasses]);
            
            // unserialize() returns false for invalid data, but also for serialized false
            // We need to distinguish between these cases
            if ($result === false && $data !== serialize(false)) {
                throw new SerializationMemoryException(
                    "Invalid serialized data format",
                    SerializationMemoryException::REASON_SERIALIZATION_ERROR,
                    'string',
                    memory_get_usage(true)
                );
            }
            
            return $result;
        } catch (SerializationMemoryException $e) {
            // Re-throw our own exceptions without modification
            throw $e;
        } catch (Throwable $e) {
            // This catches PHP errors/warnings that might be converted to exceptions
            throw new SerializationMemoryException(
                "Failed to unserialize data: " . $e->getMessage(),
                SerializationMemoryException::REASON_SERIALIZATION_ERROR,
                'string',
                memory_get_usage(true),
                $e
            );
        }
    }


    /**
     * Attempt direct serialization with consistent error handling
     *
     * @param mixed $data
     * @param string $context
     * @return string
     * @throws SerializationMemoryException
     */
    private function attemptDirectSerialization($data, $context) {
        try {
            return serialize($data);
        } catch (Throwable $e) {
            throw new SerializationMemoryException(
                "Failed to serialize {$context}: " . $e->getMessage(),
                SerializationMemoryException::REASON_SERIALIZATION_ERROR,
                gettype($data),
                memory_get_usage(true),
                $e
            );
        }
    }

    /**
     * Incrementally serialize an array, processing chunks and monitoring memory
     *
     * @param array $data
     * @return string
     * @throws SerializationMemoryException
     */
    private function incrementalArraySerialization($data) {
        if (empty($data)) {
            return serialize($data);
        }
        
        $totalItems = count($data);
        $chunkSize = max(1, min(100, intval($totalItems / 10))); // Start with 10% chunks, min 1, max 100
        $processedData = [];
        $startMemory = memory_get_usage(true);
        
        // Process array in chunks
        $chunks = array_chunk($data, $chunkSize, true);
        
        foreach ($chunks as $chunkIndex => $chunk) {
            // Check memory before processing each chunk
            $this->checkMemoryLimit("array serialization at chunk {$chunkIndex}", 'array');
            
            // Process this chunk
            foreach ($chunk as $key => $value) {
                $processedData[$key] = $value;
                
                // Check memory every few items within the chunk
                if (count($processedData) % 10 === 0) {
                    $this->checkMemoryIncrease($startMemory, "array processing at item " . count($processedData), 'array');
                }
            }
        }
        
        // Final serialization attempt
        return $this->attemptDirectSerialization($processedData, "final array");
    }

    /**
     * Incrementally serialize a string, processing chunks and monitoring memory
     *
     * @param string $data
     * @return string
     * @throws SerializationMemoryException
     */
    private function incrementalStringSerialization($data) {
        $dataLength = strlen($data);
        
        // For small strings, serialize directly
        if ($dataLength < self::CHUNK_SIZE) {
            return $this->attemptDirectSerialization($data, "small string");
        }
        
        // For large strings, process in chunks to monitor memory
        $chunkSize = self::CHUNK_SIZE;
        $processedString = '';
        $startMemory = memory_get_usage(true);
        
        for ($offset = 0; $offset < $dataLength; $offset += $chunkSize) {
            // Check memory before processing each chunk
            $this->checkMemoryLimit("string serialization at offset {$offset}", 'string');
            
            // Get chunk
            $chunk = substr($data, $offset, $chunkSize);
            $processedString .= $chunk;
            
            // Check memory every few chunks
            if (($offset / $chunkSize) % 10 === 0 && $offset > 0) {
                $this->checkMemoryIncrease($startMemory, "string processing at offset {$offset}", 'string');
            }
        }
        
        // Final serialization attempt
        return $this->attemptDirectSerialization($processedString, "final string");
    }

    /**
     * Check if memory limit is approaching and throw exception if needed
     *
     * @param string $context
     * @param string $dataType
     * @throws SerializationMemoryException
     */
    private function checkMemoryLimit($context, $dataType) {
        if ($this->isApproachingMemoryLimit()) {
            throw new SerializationMemoryException(
                "Memory limit approaching during {$context}",
                SerializationMemoryException::REASON_MEMORY_LIMIT,
                $dataType,
                memory_get_usage(true)
            );
        }
    }

    /**
     * Check memory increase and throw exception if too large
     *
     * @param int $startMemory
     * @param string $context
     * @param string $dataType
     * @throws SerializationMemoryException
     */
    private function checkMemoryIncrease($startMemory, $context, $dataType) {
        $currentMemory = memory_get_usage(true);
        $memoryIncrease = $currentMemory - $startMemory;
        
        // If memory usage increased significantly, check if we should stop
        if ($memoryIncrease > ($this->getMemoryLimitInBytes() * 0.1)) {
            if ($this->isApproachingMemoryLimit()) {
                throw new SerializationMemoryException(
                    "Memory limit approaching during {$context}",
                    SerializationMemoryException::REASON_DATA_TOO_LARGE,
                    $dataType,
                    $currentMemory
                );
            }
        }
    }
}