<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\PersistenceStrategies;

interface PersistenceStrategyInterface {
    /**
     * Retrieve an item
     *
     * @param string $identifier
     * @return mixed
     */
    public function get($identifier);

    /**
     * Destroy an item
     *
     * @param string $identifier
     * @return void
     */
    public function destroy($identifier);

    /**
     * Save an item
     *
     * @param string $identifier
     * @param mixed $data
     * @return mixed
     */
    public function save($identifier, $data);

}