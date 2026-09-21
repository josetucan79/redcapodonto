<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Settings;

abstract class AbstractSettingsRegistry
{
    protected static array $valueObjectMap = [];
    protected static bool $initialized = false;
    
    /**
     * Ensure the registry is initialized
     */
    abstract protected static function ensureInitialized(): void;
    
    /**
     * Register a value object class for a provider type
     */
    public static function registerValueObject(string $providerType, string $className): void
    {
        static::$valueObjectMap[strtolower($providerType)] = $className;
    }
    
    /**
     * Get the value object class for a provider type
     */
    public static function getValueObjectClass(string $providerType): ?string
    {
        static::ensureInitialized();
        return static::$valueObjectMap[strtolower($providerType)] ?? null;
    }
    
    /**
     * Create a value object for a provider type
     */
    public static function createValueObject(string $providerType): ?BaseSettingsValueObject
    {
        static::ensureInitialized();
        $className = static::getValueObjectClass($providerType);
        if (!$className || !class_exists($className)) {
            return null;
        }
        
        return new $className();
    }
}