<?php
namespace Vanderbilt\REDCap\Classes\BreakTheGlass;

use DateTime;
use Vanderbilt\REDCap\Classes\BreakTheGlass\DTOs\ProtectedPatientDTO;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;

/**
 * This class handles persistence and retrieval of ProtectedPatientDTO instances.
 * It uses file-based caching to store and retrieve protected patient data.
 */
class ProtectedPatientManager
{
    /** @var int */
    private $project_id;

    public function __construct($project_id)
    {
        $this->project_id = $project_id;
    }

    /**
     * Retrieve the full list of protected patients from the cache.
     *
     * @return ProtectedPatientDTO[]
     */
    public function getProtectedMrnList()
    {
        $list = $this->loadProtectedList();
        return is_array($list) ? $list : [];
    }

    /**
     * Store or update a protected patient entry.
     *
     * @param string $mrn
     * @param string|null $fhirBtgToken
     */
    public function storeProtectedPatient($mrn, $fhirBtgToken = null)
    {
        $list = $this->getProtectedMrnList();
        $protectedPatient = new ProtectedPatientDTO([
            'mrn' => $mrn,
            'timestamp' => new DateTime(),
            'fhirBtgToken' => $fhirBtgToken,
        ]);

        // Update or insert the protected patient
        $list[$mrn] = $protectedPatient;
        $this->saveProtectedList($list);
    }

    /**
     * Remove a protected patient from the cache.
     *
     * @param string $mrn
     */
    public function removeProtectedPatient($mrn)
    {
        $list = $this->getProtectedMrnList();
        if (isset($list[$mrn])) {
            unset($list[$mrn]);
            $this->saveProtectedList($list);
        }
    }

    /**
     * Load the protected patient list from cache.
     *
     * @return ProtectedPatientDTO[]|null
     */
    private function loadProtectedList()
    {
        $fileCache = $this->getCache();
        $encryptedList = $fileCache->get('list');

        if (!$encryptedList) return [];

        $decoded = decrypt($encryptedList);
        return unserialize($decoded, ['allowed_classes' => [ProtectedPatientDTO::class, DateTime::class]]);
    }

    /**
     * Save the protected patient list to cache.
     *
     * @param ProtectedPatientDTO[] $list
     */
    private function saveProtectedList($list)
    {
        $ttl = 60 * 60 * 24; // 1 day
        $fileCache = $this->getCache();
        $fileCache->set('list', encrypt(serialize($list)), $ttl);
    }

    /**
     * Return a FileCache instance for this project.
     *
     * @return FileCache
     */
    private function getCache()
    {
        return new FileCache(__CLASS__ . "-" . $this->project_id);
    }
}