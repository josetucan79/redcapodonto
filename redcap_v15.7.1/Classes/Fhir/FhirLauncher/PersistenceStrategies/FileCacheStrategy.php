<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\PersistenceStrategies;

use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;

class FileCacheStrategy implements PersistenceStrategyInterface {
    /**
     *
     * @var FileCache
     */
    private $fileCache;
    
    /**
     * amount of seconds the file will be valid
     * 
     * @var integer
     */
    const DEFAULT_TTL = 3600;

    public function __construct() {
        $this->fileCache = new FileCache(__CLASS__);
    }
    
    /**
     * retrieve an item
     *
     * @param string $identifier
     * @return mixed
     */
    public function get($identifier) {
        $entry = $this->fileCache->get($identifier);
        return $entry;
    }

    /**
     * Destroy an item
     *
     * @param string $identifier
     * @return void
     */
    public function destroy($identifier) {
        $this->fileCache->delete($identifier);
    }

    /**
     * Save an item
     *
     * @param string $identifier
     * @return Boolean
     */
    public function save($identifier, $data, $lifespan=self::DEFAULT_TTL) {
        try {
            $this->fileCache->set($identifier, $data);
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

}