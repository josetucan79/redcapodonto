<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Settings;

trait SettingsRegistryTrait
{
    protected static $valueObjectMap;
    protected static $initialized = false;

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
    
    protected static function ensureInitialized(): void
    {
        if (self::$initialized) { 
            return; 
        }
        
        static::init();
        
        self::$initialized = true;
    }

    /**
     * Ensure the registry can init
     * This method must be implemented by classes using this trait
     */
    abstract protected static function init(): void;

}