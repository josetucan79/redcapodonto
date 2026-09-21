<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Settings;

abstract class BaseSettingsValueObject
{

    // Define which properties should be serialized/deserialized
    abstract public static function getSettingKeys(): array;
    
    // Get provider type identifier
    abstract public static function getProviderType(): string;
    
    // Convert to key-value pairs
    public function toKeyValuePairs(): array
    {
        $result = [];
        foreach (static::getSettingKeys() as $key) {
            if (property_exists($this, $key)) {
                $result[$key] = $this->$key;
            }
        }
        return $result;
    }
    
    // Populate from key-value pairs
    public function populate(array $data): self
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key) && in_array($key, static::getSettingKeys())) {
                $this->$key = $value;
            }
        }
        return $this;
    }

    /**
     * Compare this settings object with another one
     * 
     * @param BaseSettingsValueObject|null $other The object to compare with
     * @return bool True if the objects have identical setting values
     */
    public function equals(?BaseSettingsValueObject $other): bool
    {
        // If comparing with null or a different class, they're not equal
        if ($other === null || get_class($this) !== get_class($other)) {
            return false;
        }
        
        // Get all setting keys for this object
        $keys = static::getSettingKeys();
        
        // Compare each property value
        foreach ($keys as $key) {
            // Skip comparing null values against empty strings (treat them as equivalent)
            $thisValue = $this->$key ?? null;
            $otherValue = $other->$key ?? null;
            
            if (is_string($thisValue) && $thisValue === '' && $otherValue === null) {
                continue;
            }
            
            if (is_string($otherValue) && $otherValue === '' && $thisValue === null) {
                continue;
            }
            
            // Handle array comparison specially
            if (is_array($thisValue) && is_array($otherValue)) {
                // Sort arrays to ensure consistent comparison
                if (array_keys($thisValue) !== range(0, count($thisValue) - 1)) {
                    // Associative array - sort by key
                    ksort($thisValue);
                    ksort($otherValue);
                } else {
                    // Sequential array - sort by value
                    sort($thisValue);
                    sort($otherValue);
                }
                
                if ($thisValue !== $otherValue) {
                    return false;
                }
            } 
            // For scalar values, do a simple comparison
            else if ($thisValue !== $otherValue) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get differences between this object and another one
     * 
     * @param BaseSettingsValueObject|null $other The object to compare with
     * @return array Array of differences with keys and both values
     */
    public function getDifferences(?BaseSettingsValueObject $other): array
    {
        if ($other === null || get_class($this) !== get_class($other)) {
            return [];
        }
        
        $differences = [];
        $keys = static::getSettingKeys();
        
        foreach ($keys as $key) {
            $thisValue = $this->$key ?? null;
            $otherValue = $other->$key ?? null;
            
            // Skip comparing null values against empty strings
            if ((is_string($thisValue) && $thisValue === '' && $otherValue === null) ||
                (is_string($otherValue) && $otherValue === '' && $thisValue === null)) {
                continue;
            }
            
            // Handle array comparison
            if (is_array($thisValue) && is_array($otherValue)) {
                // Sort arrays for consistent comparison
                if (array_keys($thisValue) !== range(0, count($thisValue) - 1)) {
                    ksort($thisValue);
                    ksort($otherValue);
                } else {
                    sort($thisValue);
                    sort($otherValue);
                }
                
                if ($thisValue !== $otherValue) {
                    $differences[$key] = [
                        'old' => $otherValue,
                        'new' => $thisValue
                    ];
                }
            } 
            // For scalar values
            else if ($thisValue !== $otherValue) {
                $differences[$key] = [
                    'old' => $otherValue,
                    'new' => $thisValue
                ];
            }
        }
        
        return $differences;
    }
}